<?php
/**
 * Course registration deadlines: set per academic session and semester type,
 * shown to students as a countdown, and shutting the portal's register and
 * drop actions once passed.
 *
 * Every write happens inside a transaction that is rolled back; the last
 * section proves the database was left as it was found.
 *
 * The checks that matter most are the server-side ones: a form opened before
 * the deadline and submitted after it must be refused, whatever the page
 * showed. Each of those is given teeth by first proving the same request
 * succeeds without the deadline.
 *
 * Run: php scripts/course_registration_deadline_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CourseRegistrationDeadline;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Subject;
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

$status = app(CourseRegistrationStatus::class);

// ---------------------------------------------------------------------------
// Fixtures: real students of the current session, chosen by what they can do.
// ---------------------------------------------------------------------------
$session = App\Models\Session::where('current', 1)->first();

$reach = fn (int $type) => StudentEnroll::where('session_id', $session->id)
    ->whereNotNull('student_id')->where('status', 1)
    ->whereHas('semester', fn ($q) => $q->where('semester_type', $type)->where('is_resit', 0));

// Someone with courses on offer and nothing registered, with portal access.
$registrant = null;
$registered = null;
$secondYear = null;

foreach ((clone $reach(1))->with('semester')->get() as $enrolment) {
    $student = Student::find($enrolment->student_id);
    $answer = $status->for($student);

    if (!$registrant && $answer['state'] === CourseRegistrationStatus::OPEN) {
        $registrant = $student;
    }

    if (!$registered && $answer['registered'] > 0 && $answer['state'] !== CourseRegistrationStatus::BLOCKED_BY_FEE) {
        $registered = $student;
    }

    if (!$secondYear && (int) $enrolment->semester->year > 1 && $answer['state'] !== CourseRegistrationStatus::BLOCKED_BY_FEE) {
        $secondYear = $student;
    }
}

if (!$registrant) {
    echo "no first-semester student of {$session->title} with courses open to register\n";
    exit(1);
}

$elsewhere = StudentEnroll::where('session_id', '!=', $session->id)
    ->whereNotNull('student_id')->where('status', 1)
    ->whereHas('semester', fn ($q) => $q->where('semester_type', 1)->where('is_resit', 0))
    ->first();

$resit = StudentEnroll::whereNotNull('student_id')->whereHas('semester', fn ($q) => $q->where('is_resit', 1))->first();

$deadlinesBefore = CourseRegistrationDeadline::count();
$pivotBefore = DB::table('student_enroll_subject')->count();

// The school may have a real deadline set. Everything below runs inside one
// outer transaction that starts from none, so the suite reads the same
// whatever the testbed holds; the inner transactions become savepoints.
DB::beginTransaction();
CourseRegistrationDeadline::where('session_id', $session->id)->delete();

echo "session {$session->title}; registrant {$registrant->student_id}; registered " . ($registered->student_id ?? '-')
    . "; second year " . ($secondYear->student_id ?? '-') . "\n";

// HTTP through the kernel, as whoever is logged in.
app('session.store')->start();
$call = function (string $method, string $path, array $body = []) use ($kernel) {
    $req = Request::create($path, $method, $method === 'POST' ? array_merge($body, ['_token' => csrf_token()]) : $body);
    $req->setLaravelSession(app('session.store'));
    return $kernel->handle($req);
};
$asStudent = function (Student $student) {
    Auth::guard('web')->logout();
    Auth::guard('student')->login($student);
    session()->forget('selected_enrollment_id');
};

// The portal's first-instalment gate would redirect most first-year students
// to the fees page before any of this is reached. Lifted for the registrant
// inside each transaction, exactly as the school lifts it for a real case.
$unlock = fn () => StudentEnroll::where('student_id', $registrant->id)->update(['bypass_payment_restriction' => 1]);
$text = fn ($res) => preg_replace('/\s+/', ' ', strip_tags($res->getContent()));

$setDeadline = fn (int $type, $closesAt, ?string $note = null) => CourseRegistrationDeadline::updateOrCreate(
    ['session_id' => $session->id, 'semester_type' => $type],
    ['closes_at' => $closesAt, 'note' => $note]
);

// ---------------------------------------------------------------------------
section('No deadline means nothing changes');
// ---------------------------------------------------------------------------
$open = $status->for($registrant);
check('a student with courses on offer is told to register', $open['state'] === CourseRegistrationStatus::OPEN, $open['state']);
check('and carries no deadline', $open['deadline'] === null);
check('the portal allows registration', CourseRegistrationDeadline::allowsRegistration($open['enrollment']));
check('the status answer has a closed state to give', defined(CourseRegistrationStatus::class . '::CLOSED'));

// ---------------------------------------------------------------------------
section('A deadline reaches every student of its semester type and nobody else');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $deadline = $setDeadline(1, now()->subDay());

    $after = $status->for($registrant);
    check('a first-semester year-one student is closed', $after['state'] === CourseRegistrationStatus::CLOSED, $after['state']);
    check('with nothing left to choose from', $after['available'] === 0 && $after['assignable']->isEmpty());
    check('and the headline names the moment', str_contains($after['headline'], 'closed on'), $after['headline']);
    check('a student who registered nothing is told it is a problem', $status->missedDeadline($after));

    if ($secondYear) {
        $y2 = $status->for($secondYear);
        check('a first-semester year-two student is closed too: the type, not the year', $y2['state'] === CourseRegistrationStatus::CLOSED, $y2['state']);
    } else {
        skip('no year-two first-semester student to check');
    }

    if ($registered) {
        $done = $status->for($registered);
        check('a student with courses already registered is closed but not alarmed',
            $done['state'] === CourseRegistrationStatus::CLOSED && !$status->missedDeadline($done), $done['state']);
    } else {
        skip('no first-semester student with courses registered');
    }

    if ($elsewhere) {
        check('a first-semester student of another session is untouched',
            CourseRegistrationDeadline::forEnrollment($elsewhere) === null);
    } else {
        skip('no first-semester enrolment in another session');
    }

    if ($resit) {
        check('a resit enrolment is never governed by a deadline', CourseRegistrationDeadline::forEnrollment($resit) === null);
    } else {
        skip('no resit enrolment');
    }

    check('the second-semester type is not affected by a first-semester deadline',
        CourseRegistrationDeadline::for($session->id, 2) === null);

    $independent = (clone $reach(1))->count();
    check('the deadline\'s reach equals an independent count of that semester type',
        $deadline->enrolments()->count() === $independent, $deadline->enrolments()->count() . ' vs ' . $independent);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('While it is still open, the student is only told');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $setDeadline(1, now()->addDays(30));
    $a = $status->for($registrant);
    check('thirty days out: still open to register', $a['state'] === CourseRegistrationStatus::OPEN, $a['state']);
    check('the deadline travels with the answer', $a['deadline'] instanceof CourseRegistrationDeadline);
    check('urgency is "open", coloured green', $a['deadline']->urgency() === 'open' && $a['deadline']->colour() === 'success');
    check('the countdown counts days', str_contains($a['deadline']->countdown(), 'days left'), $a['deadline']->countdown());

    $setDeadline(1, now()->addDays(3));
    $d = CourseRegistrationDeadline::for($session->id, 1);
    check('three days out: "soon", coloured amber', $d->urgency() === 'soon' && $d->colour() === 'warning', $d->urgency());

    $setDeadline(1, now()->addHours(20));
    $d = CourseRegistrationDeadline::for($session->id, 1);
    check('twenty hours out: "imminent", coloured red', $d->urgency() === 'imminent' && $d->colour() === 'danger', $d->urgency());
    check('and the countdown switches to hours', str_contains($d->countdown(), 'hours left'), $d->countdown());

    $setDeadline(1, now()->subHours(3));
    $d = CourseRegistrationDeadline::for($session->id, 1);
    check('three hours ago: "closed", coloured dark', $d->urgency() === 'closed' && $d->colour() === 'dark', $d->urgency());
    check('and the countdown says how long ago', str_starts_with($d->countdown(), 'closed'), $d->countdown());

    // The screens, with a week to go.
    $setDeadline(1, now()->addDays(5));
    $unlock();
    $asStudent($registrant);
    $res = $call('GET', '/student');
    $t = $text($res);
    check('the dashboard shows the deadline and the countdown',
        $res->getStatusCode() === 200 && str_contains($t, 'Register before') && str_contains($t, 'days left'), 'HTTP ' . $res->getStatusCode());
    check('and still offers the button', str_contains($t, 'Register my courses'));

    $res = $call('GET', '/student/course-registration');
    $t = $text($res);
    check('the registration page shows the deadline', str_contains($t, 'Register before') && str_contains($t, 'days left'));
    check('and the course chooser', str_contains($res->getContent(), 'id="subjects"'));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('After it passes, the door is shut on the server');
// ---------------------------------------------------------------------------
$subject = $open['assignable']->first();
$enrolment = $open['enrollment'];
$countOf = fn (StudentEnroll $e) => DB::table('student_enroll_subject')->where('student_enroll_id', $e->id)->count();

// Teeth: the same request goes through without a deadline.
DB::beginTransaction();

try {
    $unlock();
    $asStudent($registrant);
    $before = $countOf($enrolment);
    $res = $call('POST', '/student/course-registration', ['subjects' => [$subject->id]]);
    check('(teeth) without a deadline the registration request is accepted',
        $res->getStatusCode() === 302 && $countOf($enrolment) === $before + 1, 'HTTP ' . $res->getStatusCode() . ', count ' . $countOf($enrolment));
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    $setDeadline(1, now()->subMinute(), 'See the Registrar, room 4.');
    $unlock();
    $asStudent($registrant);
    $before = $countOf($enrolment);
    $res = $call('POST', '/student/course-registration', ['subjects' => [$subject->id]]);
    check('after the deadline the same request is refused', $res->getStatusCode() === 302 && $countOf($enrolment) === $before,
        'HTTP ' . $res->getStatusCode() . ', count ' . $countOf($enrolment));
    check('and the student is sent back to the registration page',
        str_contains((string) $res->headers->get('Location'), 'course-registration'));
} finally {
    DB::rollBack();
}

if ($registered) {
    $theirs = $status->for($registered)['enrollment'];
    $dropTarget = $theirs->subjects->first();

    // Make the course droppable for the test: optional, so the "required"
    // rule does not refuse it first. Rolled back with everything else.
    $makeDroppable = fn () => Subject::where('id', $dropTarget->id)->update(['subject_type' => 0]);

    DB::beginTransaction();

    try {
        $makeDroppable();
        $asStudent($registered);
        $before = $countOf($theirs);
        $res = $call('POST', '/student/course-registration/drop', ['subject_id' => $dropTarget->id]);
        check('(teeth) without a deadline the drop request is accepted',
            $res->getStatusCode() === 302 && $countOf($theirs) === $before - 1, 'HTTP ' . $res->getStatusCode() . ', count ' . $countOf($theirs));
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        $makeDroppable();
        $setDeadline(1, now()->subMinute());
        $asStudent($registered);
        $before = $countOf($theirs);
        $res = $call('POST', '/student/course-registration/drop', ['subject_id' => $dropTarget->id]);
        check('after the deadline the same drop is refused', $res->getStatusCode() === 302 && $countOf($theirs) === $before,
            'HTTP ' . $res->getStatusCode() . ', count ' . $countOf($theirs));
    } finally {
        DB::rollBack();
    }
} else {
    skip('no registered student to try dropping with');
}

// ---------------------------------------------------------------------------
section('The screens say so');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $setDeadline(1, now()->subDay(), 'See the Registrar, room 4.');
    $unlock();
    $asStudent($registrant);
    $res = $call('GET', '/student');
    $t = $text($res);
    check('the dashboard tells a student who registered nothing that it is closed',
        $res->getStatusCode() === 200 && str_contains($t, 'Course registration closed on'), 'HTTP ' . $res->getStatusCode());
    check('in the school\'s own words', str_contains($t, 'See the Registrar, room 4.'));
    check('and no longer offers the register button', !str_contains($t, 'Register my courses'));

    $res = $call('GET', '/student/course-registration');
    $html = $res->getContent();
    $t = $text($res);
    check('the registration page shows the closed notice', str_contains($t, 'Course registration closed on'));
    // The guide's step 3 still names the button in prose; it is the control
    // itself that must be gone.
    check('the course chooser is gone', !str_contains($html, 'id="subjects"') && !str_contains($html, 'data-bs-target="#confirmModal"'));

    if ($registered) {
        $asStudent($registered);
        $res = $call('GET', '/student/course-registration');
        $html = $res->getContent();
        check('a registered student sees their courses locked, no Drop button',
            !str_contains($html, 'btn-outline-danger btn-drop-subject') && str_contains($html, 'the course registration deadline has passed'));
    } else {
        skip('no registered student to check the locked Drop buttons on');
    }

    // The admin's chip.
    Auth::guard('student')->logout();
    $admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

    if (!$admin) {
        skip('no Super Admin to view the session screen as');
    } else {
        Auth::guard('web')->login($admin);
        $res = $call('GET', '/admin/academic/session');
        $t = $text($res);
        $unregistered = (clone $reach(1))->doesntHave('subjects')->count();
        $total = (clone $reach(1))->count();
        check('the session screen shows the first-semester chip as closed',
            $res->getStatusCode() === 200 && preg_match('/First\s+Closed\s+\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/', $t) === 1, 'HTTP ' . $res->getStatusCode());
        check('with the count of students it caught', str_contains($t, "{$unregistered}/{$total} unregistered"), "$unregistered/$total");
        check('and the second-semester chip still reads No deadline', preg_match('/Second\s+No deadline/', $t) === 1);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The deadline follows the student to every page');
// ---------------------------------------------------------------------------
// A student held at the fees page or the platform-fee page never reaches the
// dashboard banner. The strip in the layout has to reach them there.
$strip = fn ($res) => substr_count($res->getContent(), 'id="registration-deadline-strip"');

$platformBlocked = null;
$quiet = null;

foreach ((clone $reach(1))->get() as $enrolment) {
    $student = Student::find($enrolment->student_id);
    $answer = $status->for($student);

    if (!$platformBlocked && $answer['state'] === CourseRegistrationStatus::BLOCKED_BY_FEE) {
        $platformBlocked = $student;
    }

    if (!$quiet && $answer['state'] === CourseRegistrationStatus::NOTHING_OFFERED && $student->id !== $registrant->id) {
        $asStudent($student);

        if ($call('GET', '/student')->getStatusCode() === 200) {
            $quiet = $student;
        }
    }
}

DB::beginTransaction();

try {
    $setDeadline(1, now()->addDays(5));

    // The registrant, NOT unlocked: the fees gate sends them to the fees page.
    $asStudent($registrant);
    $res = $call('GET', '/student/course-registration');
    $gated = $res->getStatusCode() === 302 && str_contains((string) $res->headers->get('Location'), '/student/fees');

    if (!$gated) {
        skip('the registrant is not held at the fees page, so that case cannot be shown');
    } else {
        $res = $call('GET', '/student/fees');
        $t = $text($res);
        check('a student held at the fees page sees the countdown there',
            $res->getStatusCode() === 200 && $strip($res) === 1 && str_contains($t, 'closes') && str_contains($t, 'days left'), 'HTTP ' . $res->getStatusCode() . ', strips ' . $strip($res));
        check('told they have registered nothing yet', str_contains($t, 'not registered any course yet'));
    }

    if ($platformBlocked) {
        $asStudent($platformBlocked);
        $res = $call('GET', '/student/platform-fee/payment');
        check('a student held at the platform-fee page sees it there too',
            $res->getStatusCode() === 200 && $strip($res) === 1 && str_contains($text($res), 'days left'), 'HTTP ' . $res->getStatusCode() . ', strips ' . $strip($res));
    } else {
        skip('no student blocked by the platform fee');
    }

    if ($quiet) {
        $asStudent($quiet);
        $res = $call('GET', '/student');
        check('a student with no courses on offer yet still sees it on the dashboard',
            $res->getStatusCode() === 200 && $strip($res) === 1, 'HTTP ' . $res->getStatusCode() . ', strips ' . $strip($res));
    } else {
        skip('no first-semester student with nothing offered to check the quiet dashboard with');
    }

    // Not said twice: the dashboard banner already carries the countdown.
    $unlock();
    $asStudent($registrant);
    $res = $call('GET', '/student');
    $t = $text($res);
    check('the dashboard with the registration banner does not repeat it as a strip',
        $strip($res) === 0 && str_contains($t, 'Register before'), 'strips ' . $strip($res));

    if ($registered) {
        $asStudent($registered);
        $res = $call('GET', '/student/course-registration');
        check('a registered student is told changes are possible until then',
            $strip($res) === 1 && str_contains($text($res), 'Changes are possible until then'));
    }

    // After it passes: loud for the student with nothing, silent for the rest.
    $setDeadline(1, now()->subDay());
    StudentEnroll::where('student_id', $registrant->id)->update(['bypass_payment_restriction' => 0]);
    $asStudent($registrant);
    $res = $call('GET', '/student/fees');
    check('after the deadline, the fees page tells a student with nothing registered that it is closed',
        $strip($res) === 1 && str_contains($text($res), 'closed on'), 'strips ' . $strip($res));

    if ($registered) {
        $asStudent($registered);
        $res = $call('GET', '/student/course-registration');
        check('but a registered student is not nagged on every page', $strip($res) === 0, 'strips ' . $strip($res));
    }

    $setDeadline(2, now()->addDays(5));
    CourseRegistrationDeadline::where('session_id', $session->id)->where('semester_type', 1)->delete();
    $asStudent($registrant);
    check('a second-semester deadline is not shown to a first-semester student', $strip($call('GET', '/student/fees')) === 0);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Extending reopens it');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $setDeadline(1, now()->subDay());
    check('closed', $status->for($registrant)['state'] === CourseRegistrationStatus::CLOSED);
    $setDeadline(1, now()->addDays(2));
    $a = $status->for($registrant);
    check('moving the date later opens it again', $a['state'] === CourseRegistrationStatus::OPEN, $a['state']);
    check('flagged as imminent/soon rather than silently', in_array($a['deadline']->urgency(), ['soon', 'imminent'], true));
    CourseRegistrationDeadline::where('session_id', $session->id)->delete();
    check('removing it puts things back to no deadline', $status->for($registrant)['deadline'] === null);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The admin endpoint');
// ---------------------------------------------------------------------------
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no Super Admin to act as');
} else {
    DB::beginTransaction();

    try {
        Auth::guard('student')->logout();
        Auth::guard('web')->login($admin);
        $url = '/admin/academic/session-registration-deadline/' . $session->id;
        $when = now()->addDays(10)->format('Y-m-d\TH:i');

        $res = $call('POST', $url, ['semester_type' => 1, 'closes_at' => $when, 'note' => 'Room 4']);
        $row = CourseRegistrationDeadline::for($session->id, 1);
        check('saving creates the deadline', $res->getStatusCode() === 302 && $row !== null, 'HTTP ' . $res->getStatusCode());
        check('at the moment given, with the note and who set it',
            $row && $row->closes_at->format('Y-m-d\TH:i') === $when && $row->note === 'Room 4' && (int) $row->set_by === (int) $admin->id);

        $later = now()->addDays(20)->format('Y-m-d\TH:i');
        $call('POST', $url, ['semester_type' => 1, 'closes_at' => $later]);
        check('saving again moves the same row rather than adding one',
            CourseRegistrationDeadline::where('session_id', $session->id)->where('semester_type', 1)->count() === 1
            && CourseRegistrationDeadline::for($session->id, 1)->closes_at->format('Y-m-d\TH:i') === $later);
        check('and an empty note clears the old one', CourseRegistrationDeadline::for($session->id, 1)->note === null);

        $res = $call('POST', $url, ['semester_type' => 2]);
        check('a save without a date is refused', $res->getStatusCode() === 302 && CourseRegistrationDeadline::for($session->id, 2) === null
            && session()->has('errors'), 'HTTP ' . $res->getStatusCode());

        $res = $call('POST', $url, ['semester_type' => 1, 'remove' => 1]);
        check('remove deletes it', $res->getStatusCode() === 302 && CourseRegistrationDeadline::for($session->id, 1) === null);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
DB::rollBack();
check('deadline rows as before', CourseRegistrationDeadline::count() === $deadlinesBefore);
check('registered-course rows as before', DB::table('student_enroll_subject')->count() === $pivotBefore);
check('the subject made droppable is back as it was', !$registered || (int) Subject::find($dropTarget->id)->subject_type === (int) $dropTarget->subject_type);

echo "\n----\npassed $passed, failed $failed, skipped $skipped\n";
exit($failed === 0 ? 0 : 1);
