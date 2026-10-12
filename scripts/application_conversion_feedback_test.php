<?php

/**
 * What the administrator is told when an application becomes a student.
 *
 * "Approve & create student record" is the most consequential click on the
 * admission screen: it gives the last approval, issues a matricule, creates an
 * enrolment, charges the fees, and sends the student their login. It used to
 * report all of that as "Created successfully" and then move the administrator
 * to a different page, so the one thing they needed to know — whether a
 * matricule had actually been issued, and to whom — was not on screen.
 *
 * These checks are about the report, not the record: that every outcome is
 * named, that a failed e-mail is admitted rather than logged in silence, and
 * that a failure to create leaves nothing half-done behind.
 *
 * Every write happens inside a transaction that is rolled back.
 *
 *   php scripts/application_conversion_feedback_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendPassword;
use App\Models\Application;
use App\Models\MailSetting;
use App\Models\Student;
use App\Services\StudentCredentialsMailer;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

app('session.store')->start();

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "no Super Admin to act as\n";
    exit(0);
}

Auth::guard('web')->login($admin);

/** Every toast queued during the request, flattened to plain strings. */
function toasts(): array
{
    $out = [];

    foreach (app('flasher.storage_manager')->all() as $envelope) {
        $notification = $envelope->getNotification();
        $out[] = ['type' => $notification->getType(), 'message' => $notification->getMessage()];
    }

    return $out;
}

function toastText(array $toasts): string
{
    return implode(' | ', array_column($toasts, 'message'));
}

$mailSettingBefore = MailSetting::where('status', '1')->first();

// ---------------------------------------------------------------------------
section('The page itself reports where the application stands');
// ---------------------------------------------------------------------------
// Applications are linked to students by registration_no, not a relation.
$application = Application::get()->first(function ($candidate) {
    return !Student::where('registration_no', $candidate->registration_no)->exists();
}) ?? Application::first();

if (!$application) {
    echo "no applications to work with\n";
    exit(0);
}

$request = Request::create('/admin/admission/application/' . $application->id . '/edit', 'GET');
$request->setLaravelSession(app('session.store'));
$page = $kernel->handle($request)->getContent();

check('the application page renders', $page !== '' && !str_contains($page, 'Whoops'));

check('it says which approval is being waited on, or that all are in',
    str_contains($page, 'Waiting on:')
        || str_contains($page, 'Every approval is in')
        || str_contains($page, 'This application was refused')
        || str_contains($page, 'creating the record gives'),
    'an admin who cannot see what is blocking the button has nothing to act on');

// ---------------------------------------------------------------------------
section('Toasts actually reach the browser');
// ---------------------------------------------------------------------------
// The whole question is moot if the notifier is not rendered, so it is proved
// rather than assumed.
Flasher\Laravel\Facade\Flasher::addSuccess('CONVERSION-PROBE-TOAST', 'probe');

$request = Request::create('/admin/admission/application', 'GET');
$request->setLaravelSession(app('session.store'));
$listPage = $kernel->handle($request)->getContent();

check('the notifier is injected into admin pages', str_contains($listPage, 'flasher'));
check('and a queued message is carried into the html',
    str_contains($listPage, 'CONVERSION-PROBE-TOAST'),
    'a flash nobody renders is the same as no feedback at all');

// ---------------------------------------------------------------------------
section('A converted application reports what it produced');
// ---------------------------------------------------------------------------
$converted = Student::whereNotNull('student_id')->where('student_id', '!=', '')->first();

if (!$converted) {
    skip('no converted student to read a report from');
} else {
    DB::beginTransaction();

    try {
        Mail::fake();

        $outcome = app(StudentCredentialsMailer::class)->send($converted, 'a-test-password');

        check('a student with an address is sent their login',
            $outcome['sent'] === true,
            $outcome['reason'] ?? '');

        $sent = collect(Mail::sent(SendPassword::class))->first();

        check('the email carries the matricule they sign in with',
            $sent && ($sent->data['student_id'] ?? null) === $converted->student_id,
            'a login email without the matricule is not usable');

        check('and the password given to it',
            $sent && ($sent->data['password'] ?? null) === 'a-test-password');
    } finally {
        DB::rollBack();
    }

    // Each refusal names its own cause, or the administrator learns nothing
    // they can act on.
    DB::beginTransaction();

    try {
        Mail::fake();

        $noEmail = clone $converted;
        $noEmail->email = '';
        $outcome = app(StudentCredentialsMailer::class)->send($noEmail, 'x');

        check('no address is reported as a missing address',
            $outcome['sent'] === false && stripos($outcome['reason'], 'email address') !== false,
            $outcome['reason'] ?? 'no reason');

        $noPassword = clone $converted;
        $noPassword->password_text = null;
        $outcome = app(StudentCredentialsMailer::class)->send($noPassword);

        check('no recoverable password is reported as such',
            $outcome['sent'] === false && stripos($outcome['reason'], 'password') !== false,
            $outcome['reason'] ?? 'no reason');

        $corrupt = clone $converted;
        $corrupt->password_text = 'not-a-valid-encrypted-value';
        $outcome = app(StudentCredentialsMailer::class)->send($corrupt);

        check('an undecryptable password is reported, not thrown',
            $outcome['sent'] === false && $outcome['reason'] !== null);
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        Mail::fake();
        MailSetting::where('status', '1')->update(['sender_email' => '']);

        $outcome = app(StudentCredentialsMailer::class)->send($converted->fresh(), 'x');

        check('unconfigured mail is reported, not silently skipped',
            $outcome['sent'] === false && stripos($outcome['reason'], 'mail settings') !== false,
            $outcome['reason'] ?? 'no reason');

        Mail::assertNothingSent();
        check('and nothing is attempted', true);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A conversion that cannot proceed says why and changes nothing');
// ---------------------------------------------------------------------------
$unapproved = Application::get()->first(function ($candidate) {
    return !$candidate->isFullyApproved()
        && !Student::where('registration_no', $candidate->registration_no)->exists();
});

if (!$unapproved) {
    skip('every application is either approved or already converted');
} else {
    DB::beginTransaction();

    try {
        Mail::fake();

        $studentsBefore = Student::count();

        $request = Request::create('/admin/admission/application', 'POST', [
            '_token' => csrf_token(),
            'registration_no' => $unapproved->registration_no,
            'first_name' => $unapproved->first_name,
            'last_name' => $unapproved->last_name,
            'email' => $unapproved->email,
            'admission_date' => now()->format('Y-m-d'),
        ]);
        $request->setLaravelSession(app('session.store'));
        $response = $kernel->handle($request);

        check('no student is created from an application that is not approved',
            Student::count() === $studentsBefore,
            'approvals that can be skipped are not approvals');

        check('the administrator is told, not left guessing',
            stripos(toastText(toasts()), 'approv') !== false
                || stripos(toastText(toasts()), 'refus') !== false
                || stripos(toastText(toasts()), 'cannot') !== false,
            'toasts were: ' . toastText(toasts()));

        Mail::assertNothingSent();
        check('and nothing is emailed to anybody', true);

        check('they are returned to the form with their input',
            $response->isRedirect(),
            'status ' . $response->getStatusCode());
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A real conversion, and what it reports');
// ---------------------------------------------------------------------------
// The whole question, answered by doing it rather than by reading the source:
// an application is carried through every approval but the last, converted for
// real, and the toasts it produces are read back. Rolled back afterwards, so
// no matricule is spent and no student is left behind.
$batch = App\Models\Batch::where('status', 1)->latest('id')->first();
$session = App\Models\Session::where('status', 1)->latest('id')->first();
$semester = App\Models\Semester::where('is_resit', 0)->orderBy('id')->first();
$section = App\Models\Section::first();

$convertible = Application::get()->first(function ($candidate) {
    return !Student::where('registration_no', $candidate->registration_no)->exists();
});

if (!$convertible || !$batch || !$session || !$semester || !$section) {
    skip('no unconverted application, or no batch/session/semester/section to admit into');
} else {
    DB::beginTransaction();

    try {
        Mail::fake();
        app('flasher.storage_manager')->clear();

        // Every approval but the last. Creating the record gives that one,
        // which is the behaviour being checked.
        $service = app(App\Services\ApplicationApprovalService::class);
        $final = Application::finalApprovalStep();

        foreach (Application::approvalStepKeys() as $step) {
            if ($step !== $final) {
                $service->approve($convertible, $step, $admin, 'conversion feedback test');
            }
        }

        $program = App\Models\Program::find($convertible->program_id) ?? App\Models\Program::first();
        $before = Student::count();

        $request = Request::create('/admin/admission/application', 'POST', [
            '_token' => csrf_token(),
            'registration_no' => $convertible->registration_no,
            'first_name' => $convertible->first_name,
            'last_name' => $convertible->last_name,
            'email' => $convertible->email,
            'gender' => $convertible->gender ?: 1,
            'dob' => optional($convertible->dob)->format('Y-m-d') ?: '2000-01-01',
            'phone' => $convertible->phone ?: '600000000',
            'admission_date' => now()->format('Y-m-d'),
            'batch' => $batch->id,
            'program' => optional($program)->id,
            'session' => $session->id,
            'semester' => $semester->id,
            'section' => $section->id,
        ]);
        $request->setLaravelSession(app('session.store'));
        $response = $kernel->handle($request);

        $student = Student::where('registration_no', $convertible->registration_no)->first();
        $reported = toastText(toasts());

        check('the student record is created', $student !== null && Student::count() === $before + 1);

        if (!$student) {
            skip('nothing created, so there is no report to read');
        } else {
            // The matricule is the thing an administrator would otherwise have
            // to go and look up, so it is the thing the message must carry.
            check('the toast names the matricule that was issued',
                str_contains($reported, $student->student_id),
                'reported: ' . $reported);

            check('and names the student it belongs to',
                str_contains($reported, trim($convertible->first_name . ' ' . $convertible->last_name)),
                'reported: ' . $reported);

            check('it says the final approval was recorded',
                stripos($reported, 'final approval was recorded') !== false,
                'the button gives an approval as well as creating a record — reported: ' . $reported);

            check('the portal login outcome is reported',
                stripos($reported, 'portal login details') !== false,
                'reported: ' . $reported);

            check('the acceptance letter outcome is reported',
                stripos($reported, 'acceptance letter') !== false,
                'reported: ' . $reported);

            // Back to the application, where the matricule and the letter
            // buttons are — not off to a list that shows neither.
            // To the list, not back to the application. Returning to the
            // application left the administrator in front of a page that still
            // offered to create the record they had just created.
            check('the administrator is returned to the application list',
                rtrim((string) $response->headers->get('Location'), '/') === rtrim(route('admin.application.index'), '/'),
                'went to: ' . $response->headers->get('Location'));

            // The page the administrator lands on has to carry the news, or
            // the redirect has simply thrown the outcome away.
            $listRequest = Request::create('/admin/admission/application', 'GET');
            $listRequest->setLaravelSession(app('session.store'));
            $listHtml = $kernel->handle($listRequest)->getContent();

            check('and the toast survives the redirect onto that page',
                str_contains($listHtml, $student->student_id),
                'the matricule should be in the notification rendered on the list');

            check('the matricule actually reached the enrolment too',
                App\Models\StudentEnroll::where('student_id', $student->id)
                    ->where('matricule', $student->student_id)->exists(),
                'a toast naming a matricule the enrolment does not carry would be a lie');
        }
    } finally {
        DB::rollBack();
    }

    check('the conversion was rolled back, leaving no student behind',
        !Student::where('registration_no', $convertible->registration_no)->exists(),
        'this suite must not spend a real matricule');
}

// ---------------------------------------------------------------------------
section('The report names the student, not just "created"');
// ---------------------------------------------------------------------------
// Read from the source rather than run, because a real conversion issues a
// matricule and charges fees, which this suite will not do to live data.
$controller = file_get_contents(app_path('Http/Controllers/Admin/ApplicationController.php'));

check('the success message carries the matricule',
    str_contains($controller, "admitted as :matricule"),
    '"Created successfully" does not tell an admin whether a matricule was issued');

check('and says when the final approval was recorded too',
    str_contains($controller, 'final approval was recorded'),
    'the button gives an approval as well as creating a record; both should be reported');

check('the login email outcome is reported, not only logged',
    str_contains($controller, 'Portal login details emailed to')
        && str_contains($controller, 'Portal login details were not sent'),
    'a student who never gets their login cannot sign in, and nobody would know');

check('a second conversion of the same application is refused plainly',
    str_contains($controller, 'A student record already exists for this application'),
    'without this it was stopped only by a unique-email error, which explains nothing');

check('a failed conversion rolls its transaction back',
    (bool) preg_match('/Application conversion failed/', $controller)
        && (bool) preg_match('/DB::rollBack\(\);\s*\n\s*\n\s*\\\\Illuminate\\\\Support\\\\Facades\\\\Log::error\(\'Application conversion failed/', $controller),
    'it used to leave the transaction open and write nothing down');

// ---------------------------------------------------------------------------
section('A converted application stops offering to convert');
// ---------------------------------------------------------------------------
// The complaint this came from: after converting, the page still showed
// "Create student record", so it read as though nothing had happened.
$done = Student::whereNotNull('registration_no')->where('registration_no', '!=', '')->get()
    ->first(fn ($candidate) => Application::where('registration_no', $candidate->registration_no)->exists());

if (!$done) {
    skip('no application has been converted yet');
} else {
    $sourceApplication = Application::where('registration_no', $done->registration_no)->first();

    $request = Request::create('/admin/admission/application/' . $sourceApplication->id . '/edit', 'GET');
    $request->setLaravelSession(app('session.store'));
    $html = $kernel->handle($request)->getContent();

    // Scripts are stripped first: the page's own JavaScript mentions the modal
    // selector, and counting that as a button would hide a real regression.
    $withoutScripts = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);

    check('no button on the page opens the conversion form any more',
        preg_match_all('#<button[^>]*convertApplicationModal[^>]*>#i', $withoutScripts) === 0,
        'a second press is only stopped by an error message, which is not a design');

    check('the page says it has been converted',
        str_contains($html, 'has been converted'));

    check('and names the matricule that came out of it',
        str_contains($html, $done->student_id),
        'looking for ' . $done->student_id);

    check('with a way through to the student record',
        str_contains($html, 'Open the student record'));
}

// ---------------------------------------------------------------------------
section('Credentials are not emailed from inside the transaction');
// ---------------------------------------------------------------------------
// Mail cannot be rolled back. Sent before the commit, a later failure left a
// student holding the password to an account that no longer existed.
$storePortion = substr($controller, strpos($controller, 'public function store'),
    strpos($controller, 'public function show') - strpos($controller, 'public function store'));

$commitAt = strpos($storePortion, 'DB::commit();');
$credentialsAt = strpos($storePortion, 'StudentCredentialsMailer::class');

check('the login email is sent after the commit',
    $commitAt !== false && $credentialsAt !== false && $credentialsAt > $commitAt,
    'commit at ' . var_export($commitAt, true) . ', credentials at ' . var_export($credentialsAt, true));

check('and so is the acceptance letter',
    ($letterAt = strpos($storePortion, 'AcceptanceLetterService::class')) !== false
        && $letterAt > $commitAt);

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('the mail settings are as they were',
    optional(MailSetting::where('status', '1')->first())->sender_email
        === optional($mailSettingBefore)->sender_email);

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
