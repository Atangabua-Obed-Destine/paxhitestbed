<?php

/**
 * Closing resit requests for a sitting.
 *
 * Once the school has drawn up a resit timetable it cannot keep taking new
 * requests for it, but students went on asking because nothing on their portal
 * said otherwise. A window is closed per academic session and semester type —
 * the grain a resit sitting actually has.
 *
 * The rule that matters most is the one that is easiest to get wrong:
 * **declining stays open**. A student must still be able to settle a failed
 * course by carrying it over; closing that too would leave them with no move
 * at all and their progression stuck for good.
 *
 * Every write happens inside a transaction that is rolled back.
 *
 *   php scripts/resit_window_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ResitRequest;
use App\Models\ResitRequestWindow;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;

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
    echo "\n" . $title . "\n";
}

app('session.store')->start();

// What the school has configured for itself, before this suite touches anything.
// The closing section compares against these rather than against zero.
$windowsAtStart = ResitRequestWindow::count();
$closedAtStart = ResitRequestWindow::where('is_open', false)->count();

if ($windowsAtStart > 0) {
    echo "the school has $windowsAtStart configured window(s), $closedAtStart of them closed
";
}

/**
 * A student with a failed course they have neither requested nor declined —
 * found from the records, so these checks cannot be skipped by a regression in
 * the very thing they test.
 */
$subject = null;

// The resit page shows the student's CURRENT enrolment, so the page checks
// below only mean anything for a student whose unsettled course is on it.
// Current enrolments are tried first and the fallback is noted, rather than
// quietly testing a page that is showing a different semester.
$subjectIsCurrent = false;

foreach (Student::whereHas('enrolls')->limit(80)->get() as $candidate) {
    $enrolments = $candidate->currentEnroll
        ? collect([$candidate->currentEnroll])->merge($candidate->enrolls()->with('semester')->get())
        : $candidate->enrolls()->with('semester')->get();

    foreach ($enrolments as $enrollment) {
        if (!$enrollment->session_id || !optional($enrollment->semester)->semester_type) {
            continue;
        }

        $failedMark = SubjectMarking::where('student_enroll_id', $enrollment->id)
            ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
            ->where('total_marks', '<', 50)
            ->first();

        if (!$failedMark) {
            continue;
        }

        $alreadyAsked = ResitRequest::where('student_enroll_id', $enrollment->id)
            ->where('subject_id', $failedMark->subject_id)
            ->whereNotIn('workflow_state', [
                ResitRequest::STATE_CANCELLED,
                ResitRequest::STATE_REJECTED,
                ResitRequest::STATE_DECLINED,
            ])
            ->exists();

        if ($alreadyAsked) {
            continue;
        }

        $isCurrent = optional($candidate->currentEnroll)->id === $enrollment->id;

        // Keep looking for one on a current enrolment; settle for any only if
        // nothing better turns up.
        if (!$subject || ($isCurrent && !$subjectIsCurrent)) {
            $subject = [$candidate, $enrollment, $failedMark];
            $subjectIsCurrent = $isCurrent;
        }

        if ($subjectIsCurrent) {
            break 2;
        }
    }
}

if (!$subject) {
    echo "no student has an unsettled failed course to work with\n";
    exit(0);
}

[$student, $enrollment, $failedMark] = $subject;
$semesterType = (int) $enrollment->semester->semester_type;

echo $student->student_id . " has an unsettled failed course in "
    . ($enrollment->semester->title ?? '?') . " / " . ($enrollment->session->title ?? '?') . "\n";

/** Open or close the sitting this student's course belongs to. */
function setWindow(int $sessionId, int $semesterType, bool $open, ?string $note = null): void
{
    ResitRequestWindow::updateOrCreate(
        ['session_id' => $sessionId, 'semester_type' => $semesterType],
        ['is_open' => $open, 'note' => $note]
    );
}

/** Post a resit request as this student. */
function requestResit($student, $enrollment, $failedMark, $kernel)
{
    $request = Request::create('/student/resit/store', 'POST', [
        '_token' => csrf_token(),
        'student_enroll_id' => $enrollment->id,
        'subject_id' => $failedMark->subject_id,
        'session_id' => $enrollment->session_id,
    ]);
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($student);

    return $kernel->handle($request);
}

/** Decline the course as this student. */
function declineCourse($student, $enrollment, $failedMark, $kernel)
{
    $request = Request::create('/student/resit/decline', 'POST', [
        '_token' => csrf_token(),
        'student_enroll_id' => $enrollment->id,
        'subject_id' => $failedMark->subject_id,
    ]);
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($student);

    return $kernel->handle($request);
}

function requestCount($enrollment, $failedMark, array $states = []): int
{
    return ResitRequest::where('student_enroll_id', $enrollment->id)
        ->where('subject_id', $failedMark->subject_id)
        ->when($states, fn ($q) => $q->whereIn('workflow_state', $states))
        ->count();
}

// ---------------------------------------------------------------------------
section('The window itself');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    ResitRequestWindow::where('session_id', $enrollment->session_id)
        ->where('semester_type', $semesterType)->delete();

    check('with no window set, requests are open',
        ResitRequestWindow::acceptsRequests($enrollment->session_id, $semesterType));

    setWindow($enrollment->session_id, $semesterType, false);
    check('closing the window stops them',
        !ResitRequestWindow::acceptsRequests($enrollment->session_id, $semesterType));

    check('a closed window always says something',
        trim(ResitRequestWindow::noteFor($enrollment->session_id, $semesterType)) !== '');

    setWindow($enrollment->session_id, $semesterType, false, 'Resit timetable is already published.');
    check('and says what the school wrote',
        ResitRequestWindow::noteFor($enrollment->session_id, $semesterType)
            === 'Resit timetable is already published.');

    // The other semester type is a separate sitting.
    $otherType = $semesterType === 1 ? 2 : 1;
    check('closing one semester type does not close the other',
        ResitRequestWindow::acceptsRequests($enrollment->session_id, $otherType));

    setWindow($enrollment->session_id, $semesterType, true);
    check('opening it again lets requests through',
        ResitRequestWindow::acceptsRequests($enrollment->session_id, $semesterType));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A closed window actually refuses the request');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    setWindow($enrollment->session_id, $semesterType, false, 'Requests closed for this sitting.');

    $before = requestCount($enrollment, $failedMark);
    requestResit($student, $enrollment, $failedMark, $kernel);

    check('no resit request is created',
        requestCount($enrollment, $failedMark) === $before,
        requestCount($enrollment, $failedMark) . ' vs ' . $before);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Declining still works, because it has to');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    setWindow($enrollment->session_id, $semesterType, false, 'Requests closed for this sitting.');

    declineCourse($student, $enrollment, $failedMark, $kernel);

    check('the course can still be carried over',
        requestCount($enrollment, $failedMark, [ResitRequest::STATE_DECLINED]) > 0,
        'a student with no move at all would be stuck for good');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('An open window is unaffected');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    setWindow($enrollment->session_id, $semesterType, true);

    $before = requestCount($enrollment, $failedMark);
    requestResit($student, $enrollment, $failedMark, $kernel);

    check('the request goes through as before',
        requestCount($enrollment, $failedMark) > $before,
        requestCount($enrollment, $failedMark) . ' vs ' . $before);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('What the student sees');
// ---------------------------------------------------------------------------
/**
 * A student whose resit page actually offers the button.
 *
 * Selected by rendering the page with that student's own window forced open,
 * inside a transaction that is rolled back. The page draws its failed courses
 * from the enrolment it is showing, and for a student already sitting in a resit
 * semester that list is empty however many courses they failed earlier — so the
 * button has to be seen, not assumed. Forcing the window open first is what
 * makes this a search for a usable subject rather than a search for a school
 * that happens to have left the window open: once the school closes its own
 * windows, every candidate would otherwise be filtered out and the section
 * would skip exactly when it is most worth running.
 */
$pageSubject = null;

DB::beginTransaction();

try {
    foreach (Student::whereHas('enrolls')->limit(80)->get() as $candidate) {
        $enrol = $candidate->currentEnroll;

        if (!$enrol || !$enrol->session_id || !optional($enrol->semester)->semester_type) {
            continue;
        }

        setWindow($enrol->session_id, (int) $enrol->semester->semester_type, true);

        $request = Request::create('/student/resit', 'GET');
        $request->setLaravelSession(app('session.store'));
        Auth::guard('student')->login($candidate);

        if (str_contains($kernel->handle($request)->getContent(), 'Request Resit')) {
            $pageSubject = [$candidate, $enrol];
            break;
        }
    }
} finally {
    DB::rollBack();
}

if (!$pageSubject) {
    skip('no student\'s resit page currently offers the request button');
} else {
[$pageStudent, $pageEnrollment] = $pageSubject;
$pageSemesterType = (int) $pageEnrollment->semester->semester_type;

DB::beginTransaction();

try {
    setWindow($pageEnrollment->session_id, $pageSemesterType, false, 'Resit timetable is already published.');

    $request = Request::create('/student/resit', 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($pageStudent);
    $closedPage = $kernel->handle($request)->getContent();

    check('the page says requests are closed',
        str_contains($closedPage, 'Resit requests are closed for this semester'));
    check('and gives the school\'s reason',
        str_contains($closedPage, 'Resit timetable is already published.'));
    check('it tells them they can still carry a course over',
        stripos($closedPage, 'carry a course over') !== false);

    setWindow($pageEnrollment->session_id, $pageSemesterType, true);

    $request = Request::create('/student/resit', 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($pageStudent);
    $openPage = $kernel->handle($request)->getContent();

    check('with the window open the notice is gone',
        !str_contains($openPage, 'Resit requests are closed for this semester'));
    check('and the request button is offered again',
        substr_count($openPage, 'Request Resit') > substr_count($closedPage, 'Request Resit'),
        'closed page had ' . substr_count($closedPage, 'Request Resit')
            . ', open page ' . substr_count($openPage, 'Request Resit'));
} finally {
    DB::rollBack();
}
}

// ---------------------------------------------------------------------------
section('The admin switch');
// ---------------------------------------------------------------------------
$admin = \App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no admin to act as');
} else {
    DB::beginTransaction();

    try {
        ResitRequestWindow::where('session_id', $enrollment->session_id)
            ->where('semester_type', $semesterType)->delete();

        $toggle = function (array $extra = []) use ($kernel, $admin, $enrollment, $semesterType) {
            $request = Request::create('/admin/exam/resit-requests/toggle-window', 'POST', array_merge([
                '_token' => csrf_token(),
                'session_id' => $enrollment->session_id,
                'semester_type' => $semesterType,
            ], $extra));
            $request->setLaravelSession(app('session.store'));
            Auth::guard('web')->login($admin);

            return $kernel->handle($request);
        };

        $toggle();
        check('the first toggle closes it',
            !ResitRequestWindow::acceptsRequests($enrollment->session_id, $semesterType));

        $toggle();
        check('the next opens it again',
            ResitRequestWindow::acceptsRequests($enrollment->session_id, $semesterType));

        $toggle();
        $toggle(['keep_state' => 1, 'note' => 'Back in January.']);

        check('the note can be saved without flipping the switch',
            !ResitRequestWindow::acceptsRequests($enrollment->session_id, $semesterType),
            'editing the wording should not reopen the window');
        check('and it is what students are told',
            ResitRequestWindow::noteFor($enrollment->session_id, $semesterType) === 'Back in January.');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
// Counted, not assumed to be zero: the school configures real windows on this
// screen, and asserting an empty table would fail the moment the feature is
// actually used.
check('the suite left the school\'s own windows exactly as it found them',
    ResitRequestWindow::count() === $windowsAtStart
        && ResitRequestWindow::where('is_open', false)->count() === $closedAtStart,
    ResitRequestWindow::count() . ' row(s) now, ' . $windowsAtStart . ' before; '
        . ResitRequestWindow::where('is_open', false)->count() . ' closed, ' . $closedAtStart . ' before');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
