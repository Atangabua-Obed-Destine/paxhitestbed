<?php
/**
 * An approved application can actually be converted.
 *
 * Application 116 was approved all the way through and the Create student
 * record button did nothing whatsoever — no request, no error on screen,
 * nothing in the log. The conversion form is `needs-validation novalidate`, so
 * the browser's own prompt is suppressed and the theme's form-validation.js
 * cancels the submit when a required control is empty. The empty one was Batch,
 * which is empty on EVERY application: the applicant form never collects a
 * batch, so applications.batch_id is null for all of them.
 *
 * Two things are checked here, and the second is the one that would have caught
 * the bug in the first place:
 *
 *   - the modal opens with a batch already chosen;
 *   - no required control in that modal is rendered empty, with the two the
 *     modal fills by JavaScript named explicitly so the exemption cannot
 *     quietly grow.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/application_conversion_batch_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\Batch;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\ApplicationApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$approvals = app(ApplicationApprovalService::class);

$superAdmin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
    ->where('status', '1')
    ->first() ?: App\User::where('is_admin', 1)->where('status', '1')->first();

Auth::guard('web')->login($superAdmin);

/** The conversion modal's markup, as the admin's browser receives it. */
function conversionModal(int $applicationId): string
{
    $request = Request::create('/admin/admission/application/' . $applicationId . '/edit', 'GET');
    $request->setLaravelSession(app('session.store'));

    $html = app(Illuminate\Contracts\Http\Kernel::class)->handle($request)->getContent();

    preg_match('~id="convert-application-form"[\s\S]*?</form>~', $html, $match);

    return $match[0] ?? '';
}

function selectMarkup(string $modal, string $id): string
{
    preg_match('~<select[^>]*id="' . $id . '"[\s\S]*?</select>~', $modal, $match);

    return $match[0] ?? '';
}

// ---------------------------------------------------------------------------

echo "\n== The modal opens ready to submit ==\n";

$withoutBatch = Application::whereNull('batch_id')->where('stage', '!=', 'draft')->orderByDesc('id')->first()
    ?: Application::whereNull('batch_id')->orderByDesc('id')->first();

$newestBatch = Batch::where('status', '1')->orderByDesc('id')->first();

if (!$withoutBatch || !$newestBatch) {
    echo "  SKIP  need an application without a batch, and an active batch\n";
    echo "\n$passed passed, $failed failed\n";
    exit($failed > 0 ? 1 : 0);
}

$modal = conversionModal($withoutBatch->id);
$batchSelect = selectMarkup($modal, 'convert_batch');

check('the conversion modal renders', $modal !== '' && $batchSelect !== '');

preg_match('~data-selected="([^"]*)"~', $batchSelect, $selected);

check('an application with no batch of its own still gets one chosen',
    !empty($selected[1]), 'data-selected=' . var_export($selected[1] ?? null, true));
check('and it is the newest active batch',
    (string) ($selected[1] ?? '') === (string) $newestBatch->id,
    ($selected[1] ?? 'none') . ' vs ' . $newestBatch->id);
check('the option is marked selected, so the value is there before any JavaScript runs',
    preg_match('~<option[^>]*value="' . $newestBatch->id . '"[^>]*selected~', $batchSelect) === 1,
    $batchSelect);

// The check that would have caught this bug: nothing required may render empty.
echo "\n== No required field is rendered empty ==\n";

// Semester and Section are filled by the modal's own JavaScript when it opens,
// from the chosen programme. Named here so the exemption cannot quietly grow.
$filledByJavascript = ['semester', 'section'];

preg_match_all('~<(?:select|input)[^>]*required[^>]*>~', $modal, $controls);

$empty = [];

foreach ($controls[0] as $tag) {
    preg_match('~name="([^"]+)"~', $tag, $name);
    preg_match('~value="([^"]*)"~', $tag, $value);
    preg_match('~data-selected="([^"]*)"~', $tag, $dataSelected);

    $field = $name[1] ?? '?';

    if (in_array($field, $filledByJavascript, true)) {
        continue;
    }

    if (empty($value[1]) && empty($dataSelected[1]) && !str_contains($tag, 'selected')) {
        $empty[] = $field;
    }
}

check('every required control carries a value when the modal opens',
    $empty === [], 'empty: ' . implode(', ', $empty));
check('and the fields filled by JavaScript are only the two expected',
    count($filledByJavascript) === 2 && $filledByJavascript === ['semester', 'section']);

// ---------------------------------------------------------------------------

echo "\n== An application that names its own batch keeps it ==\n";

DB::beginTransaction();

try {
    DB::table('applications')->where('id', $withoutBatch->id)->update(['batch_id' => $newestBatch->id]);

    $ownBatch = selectMarkup(conversionModal($withoutBatch->id), 'convert_batch');
    preg_match('~data-selected="([^"]*)"~', $ownBatch, $own);

    check('the application\'s own batch is used',
        (string) ($own[1] ?? '') === (string) $newestBatch->id,
        $own[1] ?? 'none');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------

echo "\n== The conversion itself still works ==\n";

// A pristine application, made here when the database has none free.
require_once __DIR__ . '/support/application_fixtures.php';

$convertible = pendingUnconvertedApplication();

$sessionRow = App\Models\Session::first();
$semester = App\Models\Semester::first();
$section = App\Models\Section::first();
$program = $convertible ? (App\Models\Program::find($convertible->program_id) ?: App\Models\Program::first()) : null;

if (!$convertible || !$sessionRow || !$semester || !$section || !$program) {
    echo "  SKIP  need a convertible application and the enrolment lookups\n";
} else {
    DB::beginTransaction();

    try {
        $session = app('session.store');
        $session->start();

        foreach (Application::approvalStepKeys() as $step) {
            $approvals->approve($convertible->fresh(), $step, $superAdmin);
        }

        $payload = [
            '_token' => $session->token(),
            'registration_no' => $convertible->registration_no,
            'batch' => $newestBatch->id,
            'program' => $program->id,
            'session' => $sessionRow->id,
            'semester' => $semester->id,
            'section' => $section->id,
            'first_name' => $convertible->first_name ?: 'Batch',
            'last_name' => $convertible->last_name ?: 'Test',
            'email' => 'batch.default.' . uniqid() . '@example.test',
            'phone' => '000000000',
            'gender' => $convertible->gender ?: 1,
            'dob' => '2000-01-01',
            'admission_date' => now()->format('Y-m-d'),
        ];

        $post = Request::create('/admin/admission/application', 'POST', $payload);
        $post->setLaravelSession($session);
        $kernel->handle($post);

        $student = Student::where('registration_no', $convertible->registration_no)->first();

        check('the student record is created', $student !== null);

        if ($student) {
            $enroll = StudentEnroll::where('student_id', $student->id)->orderByDesc('id')->first();

            check('with a matricule', !empty($student->student_id), (string) $student->student_id);
            check('and the batch that was chosen', (int) $student->batch_id === (int) $newestBatch->id,
                $student->batch_id . ' vs ' . $newestBatch->id);
            check('and an enrolment', $enroll !== null);
            check('with no courses registered for them',
                $enroll && DB::table('student_enroll_subject')->where('student_enroll_id', $enroll->id)->count() === 0);
        }
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------

echo "\n== A blocked submit says why ==\n";

// Read from the source: there is no browser here to click anything. What is
// asserted is that the script still refuses an invalid submit, and no longer
// does it in silence.
$validation = file_get_contents(__DIR__ . '/../public/dashboard/js/pages/form-validation.js');

check('an invalid submit is still prevented',
    str_contains($validation, 'event.preventDefault()') && str_contains($validation, 'checkValidity() === false'));

// The call site, not merely the helper. A reporter that nothing calls is
// exactly as silent as no reporter at all — and the first version of this test
// passed with the call removed.
check('the cancelled submit actually calls the reporter',
    preg_match('~event\.preventDefault\(\);\s*event\.stopPropagation\(\);\s*reportFirstInvalid\(form\);~', $validation) === 1);

check('the reporter brings the field holding it up into view', str_contains($validation, 'scrollIntoView'));
check('focuses it', str_contains($validation, '.focus('));
check('and asks the browser to explain it, which novalidate had suppressed',
    str_contains($validation, 'reportValidity'));
check('a visible field is preferred over a hidden one', str_contains($validation, 'offsetParent'));

// Anything this suite made for itself goes now.
removeFixtures();

check('the suite left no fixtures of its own behind', fixturesWereRemoved());

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
