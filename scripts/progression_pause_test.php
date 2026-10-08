<?php

/**
 * Holding students back from a year that is not ready for them.
 *
 * The school is still configuring next year's courses. Until it has, a student
 * whose marks are published is invited to move into a semester with no courses
 * in it — one already did — and students who passed everything configured so far
 * read as ready to graduate.
 *
 * So each academic year carries its own gate, in the shape the session screen
 * already uses for admissions. This suite covers the gate, the two places a
 * student meets it, and the fact that it holds on the server as well as on the
 * screen.
 *
 * Every write happens inside a transaction that is rolled back.
 *
 *   php scripts/progression_pause_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Academic\SemesterProgressionService;

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


/**
 * Set a session's gate, and make sure the model matches the database.
 *
 * Written through the query builder on purpose. A rolled-back transaction
 * leaves the in-memory model holding the value it was given, so a later
 * $session->update() with that same value finds nothing dirty, issues no SQL,
 * and silently leaves the database as it was — which made this suite pass
 * sections that were never actually exercised.
 */
function setGate(Session $session, bool $open, ?string $note = null): void
{
    Session::whereKey($session->id)->update([
        'progression_open' => $open ? 1 : 0,
        'progression_note' => $note,
    ]);

    $session->refresh();
}

app('session.store')->start();

/** Ask the portal what it would tell this student. */
function eligibilityFor(Student $student, $kernel): array
{
    $request = Request::create('/student/progression/check-eligibility', 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($student);

    return json_decode($kernel->handle($request)->getContent(), true) ?: [];
}

/** A student the portal currently offers progression to. */
$ready = null;
$notReady = null;

foreach (Student::whereHas('enrolls')->get() as $candidate) {
    $verdict = eligibilityFor($candidate, $kernel);

    if (($verdict['eligible'] ?? null) === true && !$ready) {
        $ready = $candidate;
    } elseif (($verdict['eligible'] ?? null) === false && !$notReady) {
        $notReady = $candidate;
    }

    if ($ready && $notReady) {
        break;
    }
}

if (!$ready) {
    echo "no student is currently offered progression, so there is nothing to pause\n";
    exit(0);
}

$targetSession = app(SemesterProgressionService::class)->targetSessionFor($ready->currentEnroll);

echo $ready->student_id . " is offered progression into " . ($targetSession->title ?? '?') . "\n";

// ---------------------------------------------------------------------------
section('The gate');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    setGate($targetSession, true);
    check('a year that is open allows progression', $targetSession->fresh()->allowsProgression());

    setGate($targetSession, false);
    check('a year that is closed does not', !$targetSession->fresh()->allowsProgression());

    check('a closed year still says something to the student',
        trim($targetSession->fresh()->progressionNote()) !== '');

    setGate($targetSession, false, 'Courses are still being set up.');
    check('and says what the school wrote when it wrote something',
        $targetSession->fresh()->progressionNote() === 'Courses are still being set up.');

    setGate($targetSession, false, '   ');
    check('a note of nothing but spaces falls back to the default',
        $targetSession->fresh()->progressionNote() !== '   ');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('What the student is told');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    setGate($targetSession, true);
    $open = eligibilityFor($ready, $kernel);

    check('with the year open they are offered progression', ($open['eligible'] ?? null) === true);
    check('and nothing says it is paused', ($open['paused'] ?? null) !== true);

    setGate($targetSession, false, 'Courses for this year are still being set up.');
    $closed = eligibilityFor($ready, $kernel);

    check('with the year closed they are not', ($closed['eligible'] ?? null) === false);
    check('and they are told it is paused, not that they do not qualify',
        ($closed['paused'] ?? null) === true, json_encode($closed['message'] ?? null));
    check('the message names the year', str_contains($closed['message'] ?? '', $targetSession->title));
    check('and carries the school\'s own words',
        ($closed['paused_note'] ?? '') === 'Courses for this year are still being set up.');
    check('their carry-over courses are still reported',
        array_key_exists('carry_over_courses', $closed));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The same answer however the portal asks');
// ---------------------------------------------------------------------------
// A student arriving fresh has no enrolment chosen, so the service walks their
// active enrolments instead of being handed one. That path used to flatten
// every answer into a generic "not yet eligible", losing the paused flag and
// the school's note — so the portal said one thing to a student with a single
// enrolment and another to a student with two.
DB::beginTransaction();

try {
    setGate($targetSession, false, 'Still being set up.');

    $service = app(\App\Services\Student\ProgressionEligibilityService::class);

    $chosen = $service->checkEligibility($ready, $ready->currentEnroll->id);
    $walked = $service->checkEligibility($ready, null);

    check('asked about one enrolment, it says paused', ($chosen['paused'] ?? null) === true);
    check('asked to work it out itself, it still says paused',
        ($walked['paused'] ?? null) === true, json_encode($walked['message'] ?? null));
    check('and carries the school\'s note either way',
        ($walked['paused_note'] ?? null) === 'Still being set up.',
        json_encode($walked['paused_note'] ?? null));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A student who could not progress anyway keeps their own reason');
// ---------------------------------------------------------------------------
if (!$notReady) {
    skip('every student is currently eligible');
} else {
    DB::beginTransaction();

    try {
        $theirTarget = app(SemesterProgressionService::class)->targetSessionFor($notReady->currentEnroll);
        $before = eligibilityFor($notReady, $kernel)['message'] ?? null;

        if ($theirTarget) {
            setGate($theirTarget, false);
        }

        $after = eligibilityFor($notReady, $kernel);

        check('they are not told it is merely paused', ($after['paused'] ?? null) !== true,
            'they would have been told: ' . ($after['message'] ?? ''));
        check('their own reason is unchanged', ($after['message'] ?? null) === $before,
            'was "' . $before . '", now "' . ($after['message'] ?? '') . '"');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The gate holds on the server, not just on the screen');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    setGate($targetSession, false);

    $enrolmentsBefore = StudentEnroll::where('student_id', $ready->id)->count();
    $currentBefore = $ready->fresh()->currentEnroll->id;

    // Post straight at the action, as someone bypassing the button would.
    $request = Request::create('/student/progression/proceed', 'POST', ['_token' => csrf_token()]);
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($ready);
    $response = $kernel->handle($request);

    check('the request is refused', $response->getStatusCode() >= 400,
        (string) $response->getStatusCode());
    check('no enrolment is created',
        StudentEnroll::where('student_id', $ready->id)->count() === $enrolmentsBefore,
        StudentEnroll::where('student_id', $ready->id)->count() . ' vs ' . $enrolmentsBefore);
    check('and they are still where they were',
        $ready->fresh()->currentEnroll->id === $currentBefore);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Opening the year puts everything back');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    setGate($targetSession, false);
    $wasPaused = (eligibilityFor($ready, $kernel)['paused'] ?? null) === true;

    setGate($targetSession, true);
    $now = eligibilityFor($ready, $kernel);

    check('paused while closed', $wasPaused);
    check('offered again the moment it opens', ($now['eligible'] ?? null) === true);
    check('with the target year named as before',
        ($now['target_session_title'] ?? null) === $targetSession->title);
} finally {
    DB::rollBack();
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
        setGate($targetSession, true);

        $toggle = function () use ($kernel, $admin, $targetSession) {
            $request = Request::create('/admin/academic/session-toggle-progression/' . $targetSession->id, 'GET');
            $request->setLaravelSession(app('session.store'));
            Auth::guard('web')->login($admin);

            return $kernel->handle($request);
        };

        $toggle();
        check('the toggle closes the year', !$targetSession->fresh()->allowsProgression());

        $toggle();
        check('and opens it again', $targetSession->fresh()->allowsProgression());

        // The note is set from the same screen.
        $request = Request::create('/admin/academic/session-progression-note/' . $targetSession->id, 'POST', [
            '_token' => csrf_token(),
            'progression_note' => 'Back in November.',
        ]);
        $request->setLaravelSession(app('session.store'));
        Auth::guard('web')->login($admin);
        $kernel->handle($request);

        check('the note can be set from the screen',
            $targetSession->fresh()->progression_note === 'Back in November.');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A newly created year starts closed');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $fresh = Session::create([
        'title' => 'Suite Test Year',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'status' => 1,
    ]);

    check('because a year that has just been made has no courses in it yet',
        !$fresh->fresh()->allowsProgression());
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('"Eligible for graduation" is qualified while the year is unfinished');
// ---------------------------------------------------------------------------
// A student who has passed every course that exists so far reads as ready to
// graduate — but the courses that would prove otherwise have not been created
// yet. The verdict is left alone and a caveat added beside it.
$graduating = null;

foreach (Student::whereHas('enrolls')->get() as $candidate) {
    $request = Request::create('/student/course-registration', 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($candidate);

    if (str_contains($kernel->handle($request)->getContent(), 'eligible for graduation')) {
        $graduating = $candidate;
        break;
    }
}

if (!$graduating) {
    skip('no student currently reads as eligible for graduation');
} else {
    $theirTarget = app(SemesterProgressionService::class)->targetSessionFor($graduating->currentEnroll);

    $render = function () use ($kernel, $graduating) {
        $request = Request::create('/student/course-registration', 'GET');
        $request->setLaravelSession(app('session.store'));
        Auth::guard('student')->login($graduating);

        return $kernel->handle($request)->getContent();
    };

    DB::beginTransaction();

    try {
        setGate($theirTarget, true);
        $open = $render();

        check('with the year open, the verdict stands on its own',
            str_contains($open, 'eligible for graduation') && !str_contains($open, 'Not final yet.'));

        setGate($theirTarget, false);
        $closed = $render();

        check('with the year closed, the verdict is still shown',
            str_contains($closed, 'eligible for graduation'));
        check('but marked as not final', str_contains($closed, 'Not final yet.'));
        check('explaining that next year\'s courses are still being set up',
            str_contains($closed, 'still being set up'));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Existing years were left alone by the migration');
// ---------------------------------------------------------------------------
check('every session that existed before is still open',
    Session::where('progression_open', 1)->count() === Session::count(),
    Session::where('progression_open', 0)->count() . ' closed');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
