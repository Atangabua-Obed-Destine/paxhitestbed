<?php
/**
 * Telling a student there are courses waiting, and showing them how to register.
 *
 * Writes happen inside transactions that are rolled back; the last section
 * proves the database was left as it was found.
 *
 * The check that matters most is the one comparing the service against the
 * registration page itself: the popup exists only to send a student to that
 * page, so a popup that disagrees with the page is worse than no popup.
 *
 * Run: php scripts/course_registration_prompt_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\CourseRegistrationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

app('session.store')->start();
$status = app(CourseRegistrationStatus::class);

$visit = function (string $path, Student $student) use ($kernel) {
    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($student);

    return $kernel->handle($request);
};

// Subjects found by what they ARE, never by asking the service under test.
$students = Student::whereHas('currentEnroll')->orderBy('id')->get();

$withCourses = null;
$withNone = null;

foreach ($students as $candidate) {
    $answer = $status->for($candidate);

    if (!$withCourses && in_array($answer['state'], [CourseRegistrationStatus::OPEN, CourseRegistrationStatus::PARTLY_DONE], true)) {
        $withCourses = $candidate;
    }

    if (!$withNone && in_array($answer['state'], [CourseRegistrationStatus::DONE, CourseRegistrationStatus::NOTHING_OFFERED], true)) {
        $withNone = $candidate;
    }
}

echo 'Subjects: with courses waiting ' . ($withCourses->student_id ?? '-')
    . ', with none ' . ($withNone->student_id ?? '-') . "\n";

// ---------------------------------------------------------------------------
section('The service agrees with the page it sends students to');
// ---------------------------------------------------------------------------
// The whole point of the popup is to send a student to the registration page.
// If the two disagree about how many courses are waiting, the student is being
// lied to by one of them.
$compared = 0;
$disagreements = [];

foreach ($students as $student) {
    $answer = $status->for($student);
    $response = $visit('/student/course-registration', $student);

    if ($response->getStatusCode() !== 200) {
        // Redirected: the portal is gating them, which the service must agree
        // with by not saying courses are open.
        if (in_array($answer['state'], [CourseRegistrationStatus::OPEN, CourseRegistrationStatus::PARTLY_DONE], true)) {
            $disagreements[] = $student->student_id . ': service says "' . $answer['state']
                . '" but the page redirects (' . $response->getStatusCode() . ')';
        }

        continue;
    }

    $compared++;
    $html = $response->getContent();
    $offered = [];

    if (preg_match('/id="subjects".*?<\/select>/s', $html, $m)) {
        preg_match_all('/<option value="(\d+)"/', $m[0], $o);
        $offered = $o[1];
    }

    if (count($offered) !== $answer['available']) {
        $disagreements[] = $student->student_id . ': page offers ' . count($offered)
            . ', service says ' . $answer['available'];
    }
}

check('every student gets the same count from the service and the page',
    $disagreements === [],
    implode('; ', array_slice($disagreements, 0, 5)));

check('the comparison actually covered students', $compared > 10,
    'only ' . $compared . ' pages opened — the check proves little on so few');

// ---------------------------------------------------------------------------
section('What each student is told');
// ---------------------------------------------------------------------------
if (!$withCourses) {
    skip('no student currently has courses to register');
} else {
    $answer = $status->for($withCourses);

    check('a student with courses waiting is told so',
        in_array($answer['state'], [CourseRegistrationStatus::OPEN, CourseRegistrationStatus::PARTLY_DONE], true));

    check('and the count is named, not implied',
        str_contains($answer['headline'], (string) $answer['available']),
        'headline: ' . $answer['headline']);

    check('and they are pointed at the registration page',
        str_contains((string) $answer['action'], 'course-registration'),
        'action: ' . var_export($answer['action'], true));

    check('the service says they need attention',
        $status->needsAttention($answer));
}

if (!$withNone) {
    skip('no student has finished registering');
} else {
    $answer = $status->for($withNone);

    check('a student with nothing to register is not prompted',
        !$status->needsAttention($answer),
        'state ' . $answer['state']);
}

// ---------------------------------------------------------------------------
section('A resit semester is not a registration semester');
// ---------------------------------------------------------------------------
$resitStudent = null;

foreach ($students as $candidate) {
    $enroll = $status->currentEnrollment($candidate);

    if ($enroll && optional($enroll->semester)->is_resit) {
        $resitStudent = $candidate;
        break;
    }
}

if (!$resitStudent) {
    skip('no student is in a resit semester');
} else {
    $answer = $status->for($resitStudent);

    check('a resit semester offers nothing to register',
        $answer['available'] === 0,
        $answer['available'] . ' offered');

    check('and the student is not prompted',
        !$status->needsAttention($answer),
        'state ' . $answer['state']);

    // The courses are stopped by assignableSubjects() either way. What this
    // branch decides is what the student is TOLD - "it is a resit semester",
    // not "no courses are open to you", which would send them to the office
    // asking why.
    check('and is told it is a resit semester, not that nothing was set up',
        $answer['state'] === CourseRegistrationStatus::RESIT,
        'state ' . $answer['state'] . ' — ' . $answer['headline']);
}

// ---------------------------------------------------------------------------
section('A course already passed is not offered again');
// ---------------------------------------------------------------------------
// No student here currently has a published pass on a course still on offer, so
// the case is built rather than hoped for.
if (!$withCourses) {
    skip('no student with courses to register');
} else {
    DB::beginTransaction();

    try {
        $before = $status->for($withCourses);
        $enroll = $before['enrollment'];
        $target = $before['assignable']->first();

        if (!$target) {
            skip('nothing on offer to mark as passed');
        } else {
            DB::table('subject_markings')->insert([
                'student_enroll_id' => $enroll->id,
                'subject_id' => $target->id,
                'total_marks' => 75,
                'validated' => 1,
                'status' => 1,
                'workflow_state' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $after = $status->for(Student::find($withCourses->id), StudentEnroll::find($enroll->id));

            check('a course passed at 50% or more drops off the list',
                !$after['assignable']->contains(fn ($s) => $s->id === $target->id),
                'course #' . $target->id . ' (' . $target->code . ') is still being offered after a published 75%');

            check('and the count falls by exactly one',
                $after['available'] === $before['available'] - 1,
                'was ' . $before['available'] . ', now ' . $after['available']);
        }
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A student the portal is gating is not sent on a wasted trip');
// ---------------------------------------------------------------------------
// Telling someone to go and register when the portal will redirect them to the
// payment page is worse than saying nothing.
$gated = null;

foreach ($students as $candidate) {
    if ($status->for($candidate)['state'] === CourseRegistrationStatus::BLOCKED_BY_FEE) {
        $gated = $candidate;
        break;
    }
}

if (!$gated) {
    skip('no student is behind the platform fee gate');
} else {
    $answer = $status->for($gated);

    check('a gated student is never told courses are open',
        !$status->needsAttention($answer),
        'state ' . $answer['state']);

    check('they are told what actually stands in the way',
        str_contains(strtolower($answer['headline']), 'fee'),
        'headline: ' . $answer['headline']);

    check('and sent to pay rather than to register',
        str_contains((string) $answer['action'], 'platform-fee'),
        'action: ' . var_export($answer['action'], true));

    check('and the page really does refuse them',
        $visit('/student/course-registration', $gated)->getStatusCode() === 302,
        'the service must agree with the gate, not guess at it');
}

// ---------------------------------------------------------------------------
section('Registering removes the prompt');
// ---------------------------------------------------------------------------
if (!$withCourses) {
    skip('no student with courses to register');
} else {
    DB::beginTransaction();

    try {
        $before = $status->for($withCourses);
        $enroll = $before['enrollment'];

        // Register every course on offer, the way the page does.
        foreach ($before['assignable'] as $subject) {
            DB::table('student_enroll_subject')->insert([
                'student_enroll_id' => $enroll->id,
                'subject_id' => $subject->id,
            ]);
        }

        $after = $status->for(Student::find($withCourses->id), StudentEnroll::find($enroll->id));

        check('once everything is registered the prompt stops',
            !$status->needsAttention($after),
            'state ' . $after['state'] . ', ' . $after['available'] . ' still offered');

        check('and the student is told they are done',
            $after['state'] === CourseRegistrationStatus::DONE,
            'state ' . $after['state']);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The dashboard actually shows it');
// ---------------------------------------------------------------------------
if (!$withCourses) {
    skip('no student with courses to register');
} else {
    $html = $visit('/student/dashboard', $withCourses)->getContent();

    check('the popup is on the dashboard',
        str_contains($html, 'courseRegistrationModal'));

    check('a banner stays behind when the popup is closed',
        str_contains($html, __('Register my courses')),
        'a popup that has been dismissed is gone; the banner is what remains');

    // Looked for inside the modal, not anywhere on the page: the banner links
    // there too, so a page-wide search passes even with the popup's button gone.
    $modalHtml = '';

    if (preg_match('/id="courseRegistrationModal".*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s', $html, $m)) {
        $modalHtml = $m[0];
    }

    check('the popup itself carries a button to the registration page',
        str_contains($modalHtml, 'course-registration') && str_contains($modalHtml, '<a '),
        'a popup with no way out of it is just an interruption');

    check('it says what happens if they do nothing',
        str_contains($html, __('Until you register, you will not appear on the class list or the exam list for these courses.')),
        'a prompt without a consequence is easy to ignore');

    // Bootstrap 5 dropped the jQuery plugin and loads in the footer. A script
    // that runs before it cannot open anything.
    $bootstrap = strpos($html, 'bootstrap.min.js');
    $handler = strpos($html, 'courseRegistrationPrompt');

    check('the script runs after Bootstrap is loaded',
        $bootstrap !== false && $handler !== false && $handler > $bootstrap,
        'bootstrap at ' . var_export($bootstrap, true) . ', script at ' . var_export($handler, true));
}

if ($withNone) {
    $html = $visit('/student/dashboard', $withNone)->getContent();

    check('a student with nothing to register sees no popup',
        !str_contains($html, 'courseRegistrationModal'),
        'nobody should be nagged about work they have finished');
}

// ---------------------------------------------------------------------------
section('The page explains itself');
// ---------------------------------------------------------------------------
$anyStudent = $withCourses ?: $withNone;

if (!$anyStudent) {
    skip('no student to open the page as');
} else {
    $response = $visit('/student/course-registration', $anyStudent);
    $html = $response->getContent();

    check('the page opens', $response->getStatusCode() === 200,
        'status ' . $response->getStatusCode());

    check('it explains how to register',
        str_contains($html, __('How to register your courses')));

    check('it says why registering matters',
        str_contains($html, __('Registering tells the school which classes you are taking this semester. Until you do it, you will not appear on the class list or the exam list for those courses, and no marks can be recorded for you.')));

    check('it explains the labels a student will not know',
        str_contains($html, __('Carry-over')) && str_contains($html, __('University Requirement')),
        'the course list is full of words nobody explains anywhere else');

    // The button renders as "<i class=...></i> Assign", so the old needle
    // '>Assign<' could never match and the check could never fail.
    check('the button says what it does',
        str_contains($html, __('Register these courses'))
            && !preg_match('/<button[^>]*>\s*<i[^>]*><\/i>\s*' . preg_quote(__('Assign'), '/') . '\s*<\/button>/', $html),
        '"Assign" is the admin word for this; a student is registering');

    // That advice only appears when there IS nothing to choose from, so it has
    // to be looked for on a student in that position - not on this one.
    if (!$withNone) {
        skip('no student with an empty list to check the advice on');
    } else {
        check('and it tells them what to do when the list is empty',
            str_contains($visit('/student/course-registration', $withNone)->getContent(),
                __('If you believe courses should be listed here, speak to your department - only the school can set which courses your semester offers.')));
    }
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
if ($withCourses) {
    $answer = $status->for(Student::find($withCourses->id));

    check('the subject student still has their courses waiting',
        $status->needsAttention($answer),
        'state ' . $answer['state'] . ' — the rolled-back registration must not have stuck');
}

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
