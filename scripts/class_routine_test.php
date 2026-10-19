<?php
/**
 * The student timetable.
 *
 * It was a seven-column table with every day's classes stacked inside one
 * cell, wrapped in a horizontal scroller - so on a phone a student scrolled
 * sideways through seven columns to read five lines of small text per class,
 * with nothing to say which day was today or what was next.
 *
 * Writes: none. This screen only reads.
 *
 * The check that matters most is the day mapping. This timetable numbers
 * Saturday as 1 and Friday as 7, which is not how PHP numbers a week; get it
 * wrong and every student is shown the wrong day's classes while the page
 * still looks perfectly correct.
 *
 * Run: php scripts/class_routine_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Student\ClassRoutineController as Routine;
use App\Models\ClassRoutine;
use App\Models\Student;
use App\Models\StudentEnroll;
use Carbon\Carbon;
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

$root = dirname(__DIR__);

// ---------------------------------------------------------------------------
section('The week is numbered the way this timetable numbers it');
// ---------------------------------------------------------------------------
// 1 is Saturday, 7 is Friday - the order the admin screen uses. PHP's own
// date('w') counts Sunday as 0, so the two must be translated, not assumed.
$expected = [
    '2026-10-17' => [1, 'Saturday'],
    '2026-10-18' => [2, 'Sunday'],
    '2026-10-19' => [3, 'Monday'],
    '2026-10-20' => [4, 'Tuesday'],
    '2026-10-21' => [5, 'Wednesday'],
    '2026-10-22' => [6, 'Thursday'],
    '2026-10-23' => [7, 'Friday'],
];

foreach ($expected as $date => [$number, $name]) {
    $got = Routine::todayNumber(Carbon::parse($date));

    check($name . ' is day ' . $number, $got === $number,
        $date . ' gave day ' . $got . ' (' . __(Routine::DAYS[$got] ?? '?') . ')');
}

check('the labels agree with the admin timetable',
    __(Routine::DAYS[1]) === __('day_saturday') && __(Routine::DAYS[7]) === __('day_friday'));

// ---------------------------------------------------------------------------
section('What is on now, and what is next');
// ---------------------------------------------------------------------------
// The question the page exists to answer. Built on a day with real classes so
// the gaps between them are exercised, not just the ends.
$busiest = DB::table('class_routines')
    ->select('session_id', 'program_id', 'semester_id', 'section_id', DB::raw('COUNT(*) c'))
    ->where('status', '1')
    ->groupBy('session_id', 'program_id', 'semester_id', 'section_id')
    ->orderByDesc('c')
    ->first();

if (!$busiest) {
    skip('no class routine rows to reason about');
} else {
    $rows = ClassRoutine::where('status', '1')
        ->where('session_id', $busiest->session_id)
        ->where('program_id', $busiest->program_id)
        ->where('semester_id', $busiest->semester_id)
        ->where('section_id', $busiest->section_id)
        ->with('subject')
        ->get();

    // A weekday that actually has more than one class, so there is a gap.
    $day = collect($rows)->groupBy('day')->filter(fn ($g) => $g->count() > 1)->keys()->first();

    if ($day === null) {
        skip('no weekday with more than one class');
    } else {
        $todays = $rows->where('day', $day)->sortBy('start_time')->values();
        $first = $todays->first();
        $last = $todays->last();

        $at = fn ($time) => Routine::nowAndNext($rows, (int) $day, Carbon::parse('2026-10-21 ' . $time));

        // Before anything starts.
        $before = $at(date('H:i', strtotime($first->start_time) - 1800));
        check('before the first class, it points at the first class',
            !$before['current'] && $before['next'] && $before['next']->id === $first->id
                && !$before['done']);

        // During the first class.
        $during = $at(date('H:i', strtotime($first->start_time) + 600));
        check('during a class, that class is the one showing',
            $during['current'] && $during['current']->id === $first->id);

        // After everything.
        $after = $at(date('H:i', strtotime($last->end_time) + 1800));
        check('after the last class, the day is reported finished',
            !$after['current'] && !$after['next'] && $after['done'],
            'a finished day must say so rather than point at a class days away');

        // A day with nothing on it is not a finished day.
        $emptyDay = collect(Routine::DAYS)->keys()
            ->first(fn ($n) => $rows->where('day', $n)->isEmpty());

        if ($emptyDay === null) {
            skip('every weekday has classes, so the empty-day state cannot be checked');
        } else {
            $empty = Routine::nowAndNext($rows, (int) $emptyDay, Carbon::parse('2026-10-21 09:00'));

            check('a day with no classes is not reported as finished',
                !$empty['current'] && !$empty['next'] && !$empty['done'],
                '"that is all for today" and "no classes today" are different things');
        }
    }
}

// ---------------------------------------------------------------------------
section('The page a student actually gets');
// ---------------------------------------------------------------------------
$student = Student::whereHas('studentEnrolls')->get()
    ->first(function ($candidate) {
        $enroll = $candidate->studentEnrolls->first();

        return $enroll && ClassRoutine::where('status', '1')
            ->where('session_id', $enroll->session_id)
            ->where('program_id', $enroll->program_id)
            ->where('semester_id', $enroll->semester_id)
            ->where('section_id', $enroll->section_id)
            ->exists();
    });

$render = function (Student $as, ?int $enrollmentId = null) use ($kernel) {
    app('session.store')->start();
    Auth::guard('student')->login($as);

    if ($enrollmentId) {
        session(['selected_enrollment_id' => $enrollmentId]);
    }

    $request = Request::create('/student/class-routine', 'GET');
    $request->setLaravelSession(app('session.store'));

    try {
        $response = $kernel->handle($request);

        return ['status' => $response->getStatusCode(), 'html' => $response->getContent()];
    } catch (\Throwable $e) {
        return ['status' => 0, 'html' => $e->getMessage()];
    }
};

if (!$student) {
    skip('no student whose programme has a published timetable');
} else {
    $page = $render($student);

    check('it renders', $page['status'] === 200, 'status ' . $page['status']);

    check('every weekday is offered, not just the ones with classes',
        substr_count($page['html'], 'id="tt-panel-') === 7,
        'found ' . substr_count($page['html'], 'id="tt-panel-'));

    check('today is marked', str_contains($page['html'], 'is-today'));

    check('exactly one day is open at a time',
        substr_count($page['html'], 'tt-panel active') === 1,
        'found ' . substr_count($page['html'], 'tt-panel active'));

    check('it answers "what now" before anything else',
        strpos($page['html'], 'tt-now') < strpos($page['html'], 'tt-days'),
        'the now/next card must come before the day picker');

    $hero = collect(['In class now', 'Up next', 'That is all for today', 'No classes today'])
        ->filter(fn ($state) => str_contains($page['html'], $state));

    check('and says exactly one of the four things it can say',
        $hero->count() === 1,
        'found: ' . $hero->implode(', '));
}

// A student whose timetable is not published yet.
$without = Student::whereHas('studentEnrolls')->get()
    ->first(function ($candidate) {
        $enroll = $candidate->studentEnrolls->first();

        return $enroll && !ClassRoutine::where('status', '1')
            ->where('session_id', $enroll->session_id)
            ->where('program_id', $enroll->program_id)
            ->where('semester_id', $enroll->semester_id)
            ->where('section_id', $enroll->section_id)
            ->exists();
    });

if (!$without) {
    skip('every student has a timetable, so the empty state cannot be checked');
} else {
    $page = $render($without, $without->studentEnrolls->first()->id);

    check('a student with no timetable still gets a page', $page['status'] === 200,
        'status ' . $page['status']);

    check('and is told why it is empty rather than shown a blank grid',
        str_contains($page['html'], 'No timetable yet'));
}

// ---------------------------------------------------------------------------
section('It is built for a phone');
// ---------------------------------------------------------------------------
$view = (string) file_get_contents($root . '/resources/views/student/class-routine/index.blade.php');

check('the seven-column week table is no longer what a phone gets',
    !str_contains($view, 'class="table class-routine-table"'),
    'that table in a horizontal scroller is what made this unusable');

check('the week grid is held back for wide screens',
    str_contains($view, '@media (min-width:992px)')
    && str_contains($view, '.tt-week { display:none; }'),
    'on a phone the grid must not be the primary view');

check('the day picker scrolls rather than wrapping',
    str_contains($view, 'overflow-x:auto'));

check('day buttons meet a 44px tap target',
    str_contains($view, 'min-height:62px') || str_contains($view, 'min-height:44px'));

check('and it still prints as a week',
    str_contains($view, '@media print'));

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
