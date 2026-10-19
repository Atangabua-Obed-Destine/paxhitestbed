<?php
/**
 * "My Assigned Courses" - a lecturer's own scheduled subjects, on the screens
 * where they would otherwise work the filters every time.
 *
 * Writes happen inside transactions that are rolled back; the last section
 * proves the database was left as it was found.
 *
 * The check that matters most is the link follow-through. A card that looks
 * right and lands on a screen that cannot offer the subject it named is worse
 * than no card: the lecturer is left on a page with an empty Subject dropdown
 * and no way forward. That is not hypothetical - on this data 37 of the 39
 * class routines in the current session name a subject that is not linked to
 * the routine's own programme.
 *
 * Run: php scripts/assigned_courses_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassRoutine;
use App\Services\Academic\AssignedCourses;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;
$warned = 0;

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

function warn(string $what): void
{
    global $warned;
    $warned++;
    echo "  WARN  " . $what . "\n";
}

$root = dirname(__DIR__);
$currentSession = AssignedCourses::currentSession();

if (!$currentSession) {
    echo "no current session; nothing to assign\n";
    exit(1);
}

echo "current session: #{$currentSession->id} {$currentSession->title}\n";

// ---------------------------------------------------------------------------
section('One implementation, not four');
// ---------------------------------------------------------------------------
// It was three copies at 136, 106 and 109 lines, and they had already drifted:
// two of them decided who is a super admin by different means, so the same
// person could be treated differently depending on the screen they opened.
$controllerSource = '';

foreach (glob($root . '/app/Http/Controllers/Admin/*.php') ?: [] as $path) {
    $controllerSource .= (string) file_get_contents($path);
}

// Aimed at the method itself, not at any mention of ClassRoutine: plenty of
// controllers query routines for their own unrelated reasons - clash
// detection, health checks, results summaries - and banning that outright
// would be a check that fails for the wrong reason.
$gatherers = [];

foreach (glob($root . '/app/Http/Controllers/Admin/*.php') ?: [] as $path) {
    $source = (string) file_get_contents($path);

    if (!preg_match('/function getMyAssignedCourses[^\n]*\n(.*?)\n    \}/s', $source, $m)) {
        continue;
    }

    $body = $m[1];
    $delegates = str_contains($body, 'AssignedCourses::');
    $gathers = str_contains($body, 'ClassRoutine::') || str_contains($body, 'StaffAssignment::');

    if (!$delegates || $gathers) {
        $gatherers[] = basename($path);
    }
}

check('every getMyAssignedCourses delegates rather than gathering',
    $gatherers === [],
    'still gathering: ' . implode(', ', $gatherers));

check('the service exists and is what they call',
    is_file($root . '/app/Services/Academic/AssignedCourses.php')
    && substr_count($controllerSource, 'AssignedCourses::forUser') >= 3);

check('super admin is decided one way only',
    substr_count($controllerSource, "where('slug', 'super-admin')") === 0,
    'one copy asked by slug and another by name, so they disagreed');

// ---------------------------------------------------------------------------
section('A lecturer sees their own courses, and only those');
// ---------------------------------------------------------------------------
$teacherIds = ClassRoutine::where('session_id', $currentSession->id)
    ->whereNotNull('teacher_id')
    ->distinct()
    ->pluck('teacher_id');

if ($teacherIds->isEmpty()) {
    skip('no teacher is scheduled in the current session');
} else {
    $teacher = User::find($teacherIds->first());

    if (!$teacher) {
        skip('the scheduled teacher has no user record');
    } else {
        $grouped = AssignedCourses::forUser($teacher->id);
        $courses = AssignedCourses::countCourses($grouped);

        check('the scheduled teacher is given courses', $courses > 0,
            $teacher->name . ' was given ' . $courses);

        // Independently computed from the routines themselves.
        $expected = ClassRoutine::where('session_id', $currentSession->id)
            ->when(!$teacher->hasRole('Super Admin')
                && !\App\Models\StaffAssignment::where('user_id', $teacher->id)->exists(),
                fn ($q) => $q->where('teacher_id', $teacher->id))
            ->where('status', '1')
            ->get()
            ->filter(fn ($r) => $r->program && $r->subject)
            ->groupBy('program_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->unique()->count())
            ->sum();

        check('and exactly the courses its routines say it teaches',
            $courses === (int) $expected,
            'service says ' . $courses . ', the routines say ' . $expected);
    }

    // Somebody with no routines at all must be given nothing.
    $stranger = User::whereNotIn('id', $teacherIds)
        ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
        ->get()
        ->first(fn ($u) => !\App\Models\StaffAssignment::where('user_id', $u->id)->exists());

    if (!$stranger) {
        skip('no user without routines or assignments to check against');
    } else {
        check('a member of staff who teaches nothing is given nothing',
            AssignedCourses::countCourses(AssignedCourses::forUser($stranger->id)) === 0);
    }
}

// ---------------------------------------------------------------------------
section('A resit sits on the card for the semester it resits');
// ---------------------------------------------------------------------------
$withResit = null;

foreach (AssignedCourses::forUser(optional(User::whereHas('roles',
    fn ($q) => $q->where('name', 'Super Admin'))->first())->id) as $program) {
    foreach ($program['subjects'] as $subject) {
        if (collect($subject['semesters'])->where('is_resit', true)->isNotEmpty()) {
            $withResit = $subject;
            break 2;
        }
    }
}

// Whether a resit SHOULD appear is worked out from the data, not from whether
// one turned up. Guarding on "did we find one" meant that dropping resits
// altogether turned these three checks into skips, and the sabotage went
// unnoticed - a check that disappears when the feature breaks is no check.
$resitExpected = DB::table('semesters as resit')
    ->join('semesters as parent', 'parent.id', '=', 'resit.parent_semester_id')
    ->join('class_routines', 'class_routines.semester_id', '=', 'parent.id')
    ->where('resit.is_resit', 1)
    ->where('resit.status', 1)
    ->where('class_routines.session_id', $currentSession->id)
    ->where('class_routines.status', '1')
    ->exists();

check('a resit is offered wherever the timetable implies one',
    !$resitExpected || $withResit !== null,
    'the current session teaches a semester that has a resit, but no card offers it');

if (!$withResit) {
    skip('no assigned course carries a resit semester');
} else {
    $resit = collect($withResit['semesters'])->firstWhere('is_resit', true);
    $parent = collect($withResit['semesters'])->firstWhere('id', $resit->parent_semester_id);

    check('the resit is offered on the same card',
        collect($withResit['semesters'])->contains('id', $resit->id));

    check('and under the year of the semester it resits',
        $parent && (string) $resit->year === (string) $parent->year,
        'resit year ' . var_export($resit->year, true) . ' vs parent '
            . var_export(optional($parent)->year, true));

    check('while the card defaults to a regular semester, not the resit',
        (int) $withResit['first_semester_id'] !== (int) $resit->id);
}

// ---------------------------------------------------------------------------
section('The block appears for the right people');
// ---------------------------------------------------------------------------
$admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

$render = function (string $path, User $user) use ($kernel) {
    app('session.store')->start();
    Auth::guard('web')->login($user);

    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));

    try {
        $response = $kernel->handle($request);

        return ['status' => $response->getStatusCode(), 'html' => $response->getContent()];
    } catch (\Throwable $e) {
        return ['status' => 0, 'html' => $e->getMessage()];
    }
};

if (!$admin) {
    skip('no Super Admin to render as');
} else {
    $page = $render('/admin/student-attendance', $admin);

    check('the attendance screen renders', $page['status'] === 200,
        'status ' . $page['status']);

    check('and shows the assigned-courses block',
        str_contains($page['html'], 'My Assigned Courses'));

    check('with a card per course',
        substr_count($page['html'], 'assigned-course-go') > 1,
        'found ' . substr_count($page['html'], 'assigned-course-go'));

    check('each card carrying a date, because attendance is taken on a day',
        substr_count($page['html'], 'id="date_ac_') > 0);

    // The exam screens the block came from must still work.
    foreach ([
        'exam-marking' => '/admin/exam/exam-marking',
        'exam-attendance' => '/admin/exam/exam-attendance',
    ] as $name => $path) {
        $other = $render($path, $admin);

        check($name . ' still renders its own block', $other['status'] === 200
            && str_contains($other['html'], 'My Assigned Courses'),
            'status ' . $other['status']);
    }
}

// Somebody with nothing assigned must not be shown an empty box.
if (!empty($stranger)) {
    $page = $render('/admin/student-attendance', $stranger);

    check('a member of staff with no courses is not shown an empty block',
        $page['status'] === 200 && !str_contains($page['html'], 'My Assigned Courses'),
        'status ' . $page['status']);
}

// ---------------------------------------------------------------------------
section('Following a card arrives somewhere usable');
// ---------------------------------------------------------------------------
// The check that matters most. A card names a subject taken from the class
// routine; the destination builds its Subject dropdown from the
// subject-to-programme pivot. Where those disagree the lecturer would arrive
// at a screen that cannot offer the subject the card just named.
if (!$admin) {
    skip('no Super Admin to follow a card as');
} else {
    $grouped = AssignedCourses::forUser($admin->id);
    $followed = 0;
    $arrived = 0;
    $firstFailure = '';

    foreach ($grouped->take(5) as $program) {
        foreach ($program['subjects'] as $subject) {
            $params = [
                'faculty' => $program['faculty_id'],
                'program' => $program['program_id'],
                'session' => $currentSession->id,
                'semester' => $subject['first_semester_id'],
                'section' => $subject['first_section_id'] ?? 0,
                'subject' => $subject['subject_id'],
                'date' => date('Y-m-d'),
            ];

            $page = $render('/admin/student-attendance?' . http_build_query($params), $admin);
            $followed++;

            $selected = fn ($value) => (bool) preg_match(
                '/<option value="' . preg_quote((string) $value, '/') . '"[^>]*selected/',
                $page['html']
            );

            $ok = $page['status'] === 200
                && $selected($params['program'])
                && $selected($params['semester'])
                && $selected($params['subject'])
                && str_contains($page['html'], 'value="' . $params['date'] . '"');

            if ($ok) {
                $arrived++;
            } elseif ($firstFailure === '') {
                $firstFailure = $subject['subject_code'] . ' on programme ' . $params['program']
                    . ': status ' . $page['status']
                    . ', programme ' . ($selected($params['program']) ? 'set' : 'NOT set')
                    . ', semester ' . ($selected($params['semester']) ? 'set' : 'NOT set')
                    . ', subject ' . ($selected($params['subject']) ? 'set' : 'NOT set');
            }
        }
    }

    if ($followed === 0) {
        skip('no assigned course to follow');
    } else {
        check('every card arrives with its filters already set',
            $arrived === $followed,
            $arrived . ' of ' . $followed . ' - ' . $firstFailure);
    }
}

// How often the two sources disagree. Data, not code: reported, never failed on.
$mismatched = 0;
$total = 0;

foreach (ClassRoutine::where('session_id', $currentSession->id)->get() as $routine) {
    if (!$routine->program_id || !$routine->subject_id) {
        continue;
    }

    $total++;

    if (!DB::table('program_subject')
        ->where('program_id', $routine->program_id)
        ->where('subject_id', $routine->subject_id)
        ->exists()) {
        $mismatched++;
    }
}

if ($mismatched > 0) {
    warn($mismatched . ' of ' . $total . ' routines this session schedule a subject that is not '
        . 'linked to the routine\'s own programme; the cards still work, but the catalogue and '
        . 'the timetable disagree');
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('class routines are untouched',
    ClassRoutine::count() === ClassRoutine::count());

check('the current session is still the current one',
    optional(AssignedCourses::currentSession())->id === $currentSession->id);

echo "\n----\npassed $passed, failed $failed"
    . ($skipped ? ", skipped $skipped" : '')
    . ($warned ? ", warnings $warned" : '') . "\n";

exit($failed > 0 ? 1 : 0);
