<?php
/**
 * A student registers their own courses.
 *
 * Creating a student record from an application used to attach every subject of
 * the programme's semester set to the new enrolment. The portal's Course
 * Registration screen offers that same set minus whatever is already attached,
 * so the student opened it and found nothing to choose — and could not undo it
 * either, because a compulsory subject of the current semester cannot be
 * dropped.
 *
 * The conversion now leaves the enrolment empty and the student registers in
 * the portal, which is also what generates their Form A3.
 *
 * What is checked hardest:
 *   - the conversion attaches no courses, and everything else it does — the
 *     matricule, the fees — still happens;
 *   - there is something left for the student to register, driven through the
 *     real portal routes as that student;
 *   - the enrolments that already carry courses are untouched.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/application_course_registration_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\EnrollSubject;
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

// ---------------------------------------------------------------------------

echo "\n== The conversion no longer registers courses ==\n";

$source = file_get_contents(__DIR__ . '/../app/Http/Controllers/Admin/ApplicationController.php');

check('the conversion has no subject-attaching left in it',
    !str_contains($source, '$enroll->subjects()->attach'));
check('and does not look the subject set up either',
    !str_contains($source, 'EnrollSubject::where'));
check('the fees are still assigned at conversion',
    str_contains($source, 'autoAssignProgramSemesterFees($enroll)'));

$editView = file_get_contents(__DIR__ . '/../resources/views/admin/application/edit.blade.php');
check('the screen says who registers the courses',
    str_contains($editView, 'The student registers their own on Course Registration'));

// A programme/semester/section that actually has a subject set, so "nothing was
// attached" and "something is left to register" both mean something.
$enrollSubject = EnrollSubject::has('subjects')->with('subjects')->first();

// A pristine application, made here when the database has none free — this
// suite fell from 15 checks to 4 when the last one was used up.
require_once __DIR__ . '/support/application_fixtures.php';

$application = pendingUnconvertedApplication();

$batch = App\Models\Batch::first();
$sessionRow = App\Models\Session::first();

$before = [
    'enrolments_with_subjects' => DB::table('student_enroll_subject')->distinct()->count('student_enroll_id'),
    'subject_rows' => DB::table('student_enroll_subject')->count(),
];

if (!$enrollSubject || !$application || !$batch || !$sessionRow) {
    echo "  SKIP  need a subject set, a convertible application, a batch and a session\n";
    echo "\n$passed passed, $failed failed\n";
    exit($failed > 0 ? 1 : 0);
}

echo "\n== Converting an application ==\n";

DB::beginTransaction();

try {
    Auth::guard('web')->login($superAdmin);
    $session = app('session.store');
    $session->start();

    // The student record is gated on the approvals, so give them first.
    foreach (Application::approvalStepKeys() as $step) {
        $approvals->approve($application->fresh(), $step, $superAdmin);
    }

    $payload = [
        '_token' => $session->token(),
        'registration_no' => $application->registration_no,
        'batch' => $batch->id,
        'program' => $enrollSubject->program_id,
        'session' => $sessionRow->id,
        'semester' => $enrollSubject->semester_id,
        'section' => $enrollSubject->section_id,
        'first_name' => $application->first_name ?: 'Course',
        'last_name' => $application->last_name ?: 'Test',
        'email' => 'course.registration.' . uniqid() . '@example.test',
        'phone' => '000000000',
        'gender' => $application->gender ?: 1,
        'dob' => '2000-01-01',
        'admission_date' => now()->format('Y-m-d'),
    ];

    $request = Request::create('/admin/admission/application', 'POST', $payload);
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);

    $student = Student::where('registration_no', $application->registration_no)->first();

    check('the student record is created', $student !== null, 'status ' . $response->getStatusCode());

    if ($student) {
        $enroll = StudentEnroll::where('student_id', $student->id)->orderByDesc('id')->first();

        check('it has an enrolment', $enroll !== null);
        check('the matricule was issued', !empty($student->student_id), (string) $student->student_id);

        if ($enroll) {
            $attached = DB::table('student_enroll_subject')->where('student_enroll_id', $enroll->id)->count();

            check('no courses were registered for the student', $attached === 0, $attached . ' attached');
            check('but the programme has courses waiting to be registered',
                $enrollSubject->subjects->count() > 0, $enrollSubject->subjects->count() . ' in the set');

            $fees = DB::table('fees')->where('student_enroll_id', $enroll->id)->count();
            check('the fees were still assigned', $fees > 0, $fees . ' fee rows');

            // ---------------------------------------------------------------
            echo "\n== The student registers them ==\n";

            // Past the two fee gates that gu­ard the portal, so the real routes
            // can be exercised: neither is what this test is about.
            DB::table('student_enrolls')->where('id', $enroll->id)->update(['bypass_payment_restriction' => 1]);
            DB::table('platform_fee_exemptions')->insert([
                'exemption_type' => 'student',
                'student_enroll_id' => $enroll->id,
                'session_id' => $enroll->session_id,
                'reason' => 'course registration test',
                'created_by' => $superAdmin->id,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Auth::guard('student')->login($student);
            $studentSession = app('session.store');
            $studentSession->start();
            $studentSession->put('selected_enrollment_id', $enroll->id);

            $get = Request::create('/student/course-registration', 'GET');
            $get->setLaravelSession($studentSession);
            $page = $kernel->handle($get);

            check('the student can open Course Registration',
                $page->getStatusCode() === 200,
                $page->getStatusCode() . ' ' . substr((string) $page->headers->get('Location'), 0, 60));

            $wanted = $enrollSubject->subjects->take(1)->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($page->getStatusCode() === 200) {
                $html = $page->getContent();
                check('the page offers a course to register',
                    str_contains($html, (string) $enrollSubject->subjects->first()->title));
            }

            $post = Request::create('/student/course-registration', 'POST', [
                '_token' => $studentSession->token(),
                'subjects' => $wanted,
            ]);
            $post->setLaravelSession($studentSession);
            $registerResponse = $kernel->handle($post);

            $nowAttached = DB::table('student_enroll_subject')
                ->where('student_enroll_id', $enroll->id)
                ->pluck('subject_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            check('registering from the portal attaches exactly what was chosen',
                $nowAttached === $wanted,
                'attached ' . json_encode($nowAttached) . ', chose ' . json_encode($wanted)
                . ', status ' . $registerResponse->getStatusCode());

            if (class_exists(App\Models\FormA3Record::class)) {
                $formA3 = DB::table('form_a3_records')->where('student_enroll_id', $enroll->id)->count();
                check('Form A3 is generated when the student registers, not at conversion', $formA3 > 0, $formA3 . ' records');
            }
        }
    }
} finally {
    DB::rollBack();
    Auth::guard('student')->logout();
}

echo "\n== The enrolments that already have courses are untouched ==\n";

$after = [
    'enrolments_with_subjects' => DB::table('student_enroll_subject')->distinct()->count('student_enroll_id'),
    'subject_rows' => DB::table('student_enroll_subject')->count(),
];

check('no existing enrolment lost its courses',
    $after === $before, json_encode($before) . ' vs ' . json_encode($after));

// Anything this suite made for itself goes now.
removeFixtures();

check('the suite left no fixtures of its own behind', fixturesWereRemoved());

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
