<?php
/**
 * Sending a student record that was never enrolled back to its application.
 *
 * No student on this database is in that state, so the case is CONSTRUCTED
 * inside a transaction that is rolled back. The last section proves the database
 * was left as it was found.
 *
 * Run: php scripts/unconvert_student_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\PaymentReceipt;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\UnconvertStudent;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

$service = app(UnconvertStudent::class);

/**
 * Put a student back to the state this feature is for: converted, but with
 * nothing behind them. Only ever called inside a rolled-back transaction.
 */
function stripHistory(int $studentId): void
{
    StudentEnroll::where('student_id', $studentId)->delete();
    PaymentReceipt::where('student_id', $studentId)->delete();
    App\Models\StudentCredit::where('student_id', $studentId)->delete();
    App\Models\Transaction::where('transactionable_type', Student::class)
        ->where('transactionable_id', $studentId)->delete();
}

// A student made from an application, found by what they ARE.
$student = Student::whereNotNull('registration_no')
    ->whereIn('registration_no', Application::whereNotNull('registration_no')->pluck('registration_no'))
    ->whereHas('studentEnrolls')
    ->orderBy('id')
    ->first();

if (!$student) {
    echo "No student that came from an application.\n";
    exit(1);
}

$application = Application::where('registration_no', $student->registration_no)->first();

echo 'Subject: student #' . $student->id . ' (' . $student->student_id . ') '
    . 'from application #' . $application->id . ', stage ' . $application->stage
    . ', status ' . $application->status . "\n";

// ---------------------------------------------------------------------------
section('An enrolled student is never sent back');
// ---------------------------------------------------------------------------
$reasons = $service->blockers($student);

check('a student with an enrolment is refused',
    $reasons !== [],
    'this is for records that were never enrolled; an enrolled one must be corrected, not deleted');

check('and the refusal says so',
    (bool) array_filter($reasons, fn ($r) => str_contains(strtolower($r), 'enrolment')),
    implode(' ', $reasons));

// ---------------------------------------------------------------------------
section('A record that was never really made');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // Exactly the state the school is left in: a student converted onto the
    // wrong programme whose enrolment never took.
    stripHistory($student->id);

    $fresh = Student::find($student->id);

    check('it can be sent back',
        $service->canUnconvert($fresh),
        implode(' ', $service->blockers($fresh)));

    check('and the application it came from is found',
        optional($service->applicationFor($fresh))->id === $application->id,
        'matched on registration number, the only link between the two tables');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Money stops it, every time');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    stripHistory($student->id);

    App\Models\StudentCredit::create([
        'student_id' => $student->id,
        'original_amount' => 1000,
        'remaining_amount' => 1000,
        'source_type' => App\Models\StudentCredit::SOURCE_ADMIN_ADJUSTMENT,
        'status' => App\Models\StudentCredit::STATUS_AVAILABLE,
        'note' => 'UNCONVERT-TEST',
    ]);

    $fresh = Student::find($student->id);
    $reasons = $service->blockers($fresh);

    check('a credit on the student stops it',
        $reasons !== [],
        'anything in the accounts means the record must be corrected, not deleted');

    check('and the refusal names it',
        (bool) array_filter($reasons, fn ($r) => str_contains(strtolower($r), 'credit')),
        implode(' ', $reasons));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Nothing to go back to');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    stripHistory($student->id);

    $orphan = Student::find($student->id);
    $orphan->registration_no = null;
    $orphan->save();

    check('a student with no matching application is refused',
        !$service->canUnconvert(Student::find($student->id)),
        'there would be nothing to send them back to');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Through the screen, where it actually happens');
// ---------------------------------------------------------------------------
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no Super Admin to act as');
} else {
    Illuminate\Support\Facades\Auth::guard('web')->login($admin);

    DB::beginTransaction();

    try {
        stripHistory($student->id);

        // The list must offer it before anything else.
        $listRequest = Illuminate\Http\Request::create('/admin/admission/student', 'GET');
        $listRequest->setLaravelSession(app('session.store'));
        $list = $kernel->handle($listRequest)->getContent();

        check('the student list offers the button',
            (bool) preg_match('/btn-unconvert[^>]*data-student="' . $student->id . '"/s', $list),
            'a stranded record the registrar cannot act on is no better than a hidden one');

        $request = Illuminate\Http\Request::create(
            '/admin/admission/student/' . $student->id . '/unconvert', 'POST',
            ['_token' => csrf_token(), 'reason' => 'admitted onto the wrong programme']);
        $request->setLaravelSession(app('session.store'));
        $response = $kernel->handle($request);

        check('the student record is gone',
            !Student::whereKey($student->id)->exists(),
            'status ' . $response->getStatusCode());

        $after = Application::find($application->id);

        check('the application is waiting for admission again',
            $after->stage === 'decision_pending',
            'stage is ' . $after->stage);

        check('and no longer reads as converted',
            (int) $after->status === 1,
            'status is ' . $after->status);

        check('and no longer reads as decided',
            $after->decision_at === null && $after->completed_at === null,
            'decision_at ' . var_export($after->decision_at, true)
                . ', completed_at ' . var_export($after->completed_at, true));

        check('the reason is written onto the application',
            $after->statusUpdates()->where('note', 'like', '%wrong programme%')->exists(),
            'admissions has to be able to see what happened and why');

        check('the applicant keeps everything they submitted',
            $after->documents()->count() > 0 || $application->documents()->count() === 0,
            'the application is put back, not thrown away');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
$restored = Student::find($student->id);

check('the student still exists', $restored !== null);

check('with their enrolments',
    StudentEnroll::where('student_id', $student->id)->count() > 0,
    StudentEnroll::where('student_id', $student->id)->count() . ' enrolments');

$app = Application::find($application->id);

check('and the application is as it was',
    $app->stage === $application->stage && (int) $app->status === (int) $application->status,
    'stage ' . $app->stage . ', status ' . $app->status);

check('no test receipt remains',
    PaymentReceipt::where('payment_reference', 'UNCONVERT-TEST')->count() === 0);

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
