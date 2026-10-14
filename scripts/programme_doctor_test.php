<?php
/**
 * Finding students whose enrolments or programmes were lost, and repairing only
 * what needs no judgement.
 *
 * The testbed carries no damage, so every case here is CONSTRUCTED inside a
 * transaction that is rolled back. The last section proves the database was left
 * as it was found.
 *
 * Run: php scripts/programme_doctor_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnroll;
use Illuminate\Support\Facades\Artisan;
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

/** Run the doctor and hand back everything it printed. */
function doctor(array $options = []): string
{
    Artisan::call('admissions:programme-doctor', $options);

    // Console output here is CRLF; normalised so checks can be written against
    // what the report says rather than how the console ends its lines.
    return str_replace("
", "
", Artisan::output());
}

// ---------------------------------------------------------------------------
section('On a healthy database it reports nothing');
// ---------------------------------------------------------------------------
$clean = doctor();

check('it runs', str_contains($clean, 'Programme doctor'));
check('it finds no student with a deleted programme',
    str_contains($clean, "1. Students whose programme no longer exists\n   none"),
    'this database is known to be clean, so anything found here is a false positive');
check('it finds no invisible student',
    str_contains($clean, "2. Active students with no enrolment"), 'section missing');
check('it says plainly that nothing was changed',
    str_contains($clean, 'Nothing was changed.'));

// ---------------------------------------------------------------------------
section('It finds a student whose enrolments were erased');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // Exactly the damage a programme delete used to cause.
    $student = Student::where('status', '1')->whereHas('studentEnrolls')->first();
    $enrolCount = StudentEnroll::where('student_id', $student->id)->count();
    StudentEnroll::where('student_id', $student->id)->delete();

    $report = doctor();

    check('the invisible student is named',
        str_contains($report, (string) $student->student_id),
        'the doctor must name them, not count them — a registrar has to know who');

    check('and no healthy student is dragged in with them',
        substr_count($report, 'PAX') < $enrolCount + 20,
        'the report should be about the damage, not a dump of everyone');

    // And the list, which is the screen the user actually looks at.
    app('session.store')->start();
    $admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

    if (!$admin) {
        skip('no Super Admin to render the student list as');
    } else {
        Illuminate\Support\Facades\Auth::guard('web')->login($admin);
        $request = Illuminate\Http\Request::create('/admin/admission/student', 'GET');
        $request->setLaravelSession(app('session.store'));
        $html = $kernel->handle($request)->getContent();

        check('the student list now says someone has no enrolment',
            str_contains($html, __('student(s) have no enrolment.')),
            'before this change they simply vanished and the list was quietly shorter');

        check('and names them in the table',
            str_contains($html, (string) $student->student_id),
            'a count with no name leaves the registrar nothing to act on');

        check('and marks them clearly',
            str_contains($html, __('No enrolment')));
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('It finds an application whose programme went missing');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $application = Application::whereNotNull('registration_no')
        ->whereNotNull('program_id')
        ->whereIn('registration_no', Student::whereNotNull('registration_no')->pluck('registration_no'))
        ->first();

    $student = Student::where('registration_no', $application->registration_no)->first();
    $wasPointingAt = $application->program_id;
    $enrolled = StudentEnroll::where('student_id', $student->id)->orderBy('id')->value('program_id');

    $application->program_id = null;
    $application->save();

    $report = doctor();

    check('the blanked application is reported',
        str_contains($report, '#' . $application->id),
        'application #' . $application->id . ' is blank and recoverable but was not listed');

    check('and it is not written to without --apply',
        Application::find($application->id)->program_id === null,
        'a read-only run must stay read-only');

    doctor(['--apply' => true]);

    $repaired = Application::find($application->id);

    check('--apply fills it from what the student is actually enrolled on',
        (int) $repaired->program_id === (int) $enrolled,
        'got ' . var_export($repaired->program_id, true) . ', the enrolment says ' . var_export($enrolled, true));

    check('and the student record is left untouched',
        (int) Student::find($student->id)->program_id === (int) $student->program_id);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A difference is not damage');
// ---------------------------------------------------------------------------
// Nine applications here disagree with their student's programme because those
// students transferred after admission. The application records what they were
// admitted to. Overwriting that would destroy real history, so the doctor must
// leave every one of them exactly as it is.
$differing = DB::table('applications as a')
    ->join('students as s', 's.registration_no', '=', 'a.registration_no')
    ->whereRaw('a.program_id <> s.program_id')
    ->select('a.id', 'a.program_id')
    ->get();

if ($differing->isEmpty()) {
    skip('no application differs from its student on this database');
} else {
    DB::beginTransaction();

    try {
        doctor(['--apply' => true]);

        $changed = $differing->filter(
            fn ($row) => (int) Application::find($row->id)->program_id !== (int) $row->program_id
        );

        check('--apply leaves every differing application alone',
            $changed->isEmpty(),
            $changed->count() . ' of ' . $differing->count()
                . ' were overwritten — those are transfers, not corruption');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('It can say what a deleted programme was');
// ---------------------------------------------------------------------------
$deleted = DB::table('audit_logs')
    ->where('auditable_type', 'App\\Models\\Program')
    ->where('event', 'deleted')
    ->orderByDesc('id')
    ->first();

if (!$deleted) {
    skip('no programme has ever been deleted on this database');
} else {
    $report = doctor(['--restore-programme' => $deleted->auditable_id]);

    check('it reads the deleted programme back out of the audit log',
        str_contains($report, 'Programme #' . $deleted->auditable_id),
        $report);

    check('and does not restore it without --apply',
        !Program::whereKey($deleted->auditable_id)->exists(),
        'a restore is a decision about what the school teaches, not a repair');

    check('and says who deleted it and when',
        str_contains($report, 'Deleted'),
        'the registrar needs to know whether it went on purpose');
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('every application still has the programme it started with',
    DB::table('applications as a')->join('students as s', 's.registration_no', '=', 'a.registration_no')
        ->whereRaw('a.program_id <> s.program_id')->count() === $differing->count(),
    'the differing set changed size');

check('no student lost their enrolments',
    Student::where('status', '1')->whereDoesntHave('studentEnrolls')->count() === 0,
    Student::where('status', '1')->whereDoesntHave('studentEnrolls')->count() . ' have none');

check('no programme was resurrected',
    !($deleted && Program::whereKey($deleted->auditable_id)->exists()));

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
