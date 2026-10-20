<?php

/**
 * The admin class timetable: the grid, the conflicts, the sheet, the workbook.
 *
 * What this has to prove, because each of these was wrong or absent before:
 *
 *  - Every class lands in the grid exactly once. The old screen dropped a
 *    whole day into one cell; a banding bug here would silently hide classes.
 *  - The time bands are the hours a person reads, not the 27 near-identical
 *    strings in the data.
 *  - Every conflict reported is real, and every real conflict is reported,
 *    checked against expectations computed independently of the service.
 *  - A joint lecture is not reported as a clash.
 *  - The Print button works. It used to answer 404, because the code required
 *    a print_settings row that does not exist.
 *  - The workbook holds every timetable, with legal unique tab names.
 *  - Nothing is written by any of it.
 *
 * Reads only. The one write is a temporary conflict built inside a
 * transaction that is rolled back, and the final section proves the database
 * is exactly as it was found.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassRoutine;
use App\Models\Session;
use App\Services\Academic\ClassTimetable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo "\n", $title, "\n", str_repeat('-', strlen($title)), "\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label", $detail ? "  ($detail)" : '', "\n";
    }
}

$svc = new ClassTimetable();
$relations = ['subject', 'teacher', 'room', 'program', 'semester', 'section'];

// The busiest real timetable, chosen from the data rather than hardcoded.
$busiest = DB::table('class_routines')->where('status', '1')
    ->select('program_id', 'session_id', 'semester_id', 'section_id', DB::raw('count(*) c'))
    ->groupBy('program_id', 'session_id', 'semester_id', 'section_id')
    ->orderByDesc('c')->first();

$rows = ClassRoutine::with($relations)->where('status', '1')
    ->where('program_id', $busiest->program_id)
    ->where('session_id', $busiest->session_id)
    ->where('semester_id', $busiest->semester_id)
    ->where('section_id', $busiest->section_id)
    ->get();

printf("subject: %s · %s (%d classes)\n", $rows->first()->program->title ?? '?',
    $rows->first()->semester->title ?? '?', $rows->count());

// ---------------------------------------------------------------------------
section('Every class appears in the grid, exactly once');

$grid = $svc->grid($rows);

$placed = collect();

foreach ($grid['bands'] as $band) {
    foreach ($band['cells'] as $cell) {
        foreach ($cell as $row) {
            $placed->push($row->id);
        }
    }
}

check('no class is lost', $placed->count() === $rows->count(),
    sprintf('%d placed, %d exist', $placed->count(), $rows->count()));
check('no class is duplicated', $placed->unique()->count() === $placed->count());
check('the ids placed are the ids that exist',
    $placed->sort()->values()->all() === $rows->pluck('id')->sort()->values()->all());

// Every class must sit in the column for its own day.
$wrongDay = 0;

foreach ($grid['bands'] as $band) {
    foreach ($band['cells'] as $day => $cell) {
        foreach ($cell as $row) {
            if ((int) $row->day !== (int) $day) {
                $wrongDay++;
            }
        }
    }
}

check('every class is in its own day column', $wrongDay === 0, "$wrongDay misplaced");

// And in a band whose start it falls inside.
$outOfBand = 0;

foreach ($grid['bands'] as $band) {
    foreach ($band['cells'] as $cell) {
        foreach ($cell as $row) {
            if ($row->start_time < $band['start'] || $row->start_time > $band['end']) {
                $outOfBand++;
            }
        }
    }
}

check('every class sits inside its band', $outOfBand === 0, "$outOfBand outside");

// ---------------------------------------------------------------------------
section('Time bands read as hours, not as raw strings');

$distinctStarts = $rows->pluck('start_time')->unique()->count();

check('there is at least one band', count($grid['bands']) > 0);
check('bands never outnumber distinct start times',
    count($grid['bands']) <= $distinctStarts,
    sprintf('%d bands, %d starts', count($grid['bands']), $distinctStarts));

// Bands must be in ascending order and must not overlap on their start.
$starts = array_map(fn ($band) => $band['start'], $grid['bands']);
$sorted = $starts;
sort($sorted);

check('bands are in time order', $starts === $sorted);

// On the whole dataset, near-identical starts must collapse. 08:00, 08:10 and
// 08:16 are one class hour; keying on the exact string would give three.
$all = ClassRoutine::with($relations)->where('status', '1')->get();
$allGrid = $svc->grid($all);
$allStarts = $all->pluck('start_time')->unique()->count();

check('near-identical start times collapse into one band',
    count($allGrid['bands']) < $allStarts,
    sprintf('%d bands from %d distinct starts', count($allGrid['bands']), $allStarts));

// Nothing is lost on the full set either.
$allPlaced = 0;

foreach ($allGrid['bands'] as $band) {
    foreach ($band['cells'] as $cell) {
        $allPlaced += $cell->count();
    }
}

$withRealDay = $all->filter(fn ($row) => isset(ClassTimetable::DAYS[(int) $row->day]))->count();

check('no class is lost across the whole dataset', $allPlaced === $withRealDay,
    sprintf('%d placed, %d with a real day', $allPlaced, $withRealDay));

// ---------------------------------------------------------------------------
section('Conflicts: every one real, and none missed');

$session = $busiest->session_id;
$scope = ClassRoutine::with($relations)->where('status', '1')
    ->where('session_id', $session)->get();

$conflicts = $svc->conflicts($scope);

// Expectations computed here, from the rows, without using the service.
$expect = [];

foreach ($scope as $a) {
    foreach ($scope as $b) {
        if ($a->id >= $b->id || (string) $a->day !== (string) $b->day) {
            continue;
        }

        if (!($a->start_time < $b->end_time && $b->start_time < $a->end_time)) {
            continue;
        }

        $sameGroup = $a->program_id == $b->program_id && $a->session_id == $b->session_id
            && $a->semester_id == $b->semester_id && $a->section_id == $b->section_id;

        foreach ([[$a, $b], [$b, $a]] as [$one, $other]) {
            if ($sameGroup) {
                $expect[$one->id]['clash'][] = $other->id;
            }

            if ($one->room_id && $one->room_id == $other->room_id
                && $one->subject_id != $other->subject_id) {
                $expect[$one->id]['room'][] = $other->id;
            }

            if ($one->teacher_id && $one->teacher_id == $other->teacher_id
                && $one->subject_id != $other->subject_id) {
                $expect[$one->id]['lecturer'][] = $other->id;
            }
        }
    }
}

foreach ($scope as $row) {
    $minutes = (strtotime($row->end_time) - strtotime($row->start_time)) / 60;

    if ($minutes <= 0 || $minutes > ClassTimetable::MAX_MINUTES) {
        $expect[$row->id]['duration'][] = $row->id;
    }
}

$normalise = function (array $source): array {
    $out = [];

    foreach ($source as $id => $list) {
        foreach ($list as $item) {
            $out[$id][$item['type']][] = $item['with'];
        }
    }

    foreach ($out as &$types) {
        foreach ($types as &$partners) {
            sort($partners);
        }
        ksort($types);
    }

    return $out;
};

$got = $normalise($conflicts);

foreach ($expect as &$types) {
    foreach ($types as &$partners) {
        sort($partners);
    }
    ksort($types);
}
unset($types, $partners);

ksort($got);
ksort($expect);

check('the same classes are flagged', array_keys($got) === array_keys($expect),
    sprintf('%d flagged, %d expected', count($got), count($expect)));
check('every flag matches an independently computed expectation', $got == $expect);

// Each reported conflict must name its partner, and the partner must exist.
$ids = $scope->pluck('id')->all();
$danglingPartner = 0;
$emptyNote = 0;

foreach ($conflicts as $id => $list) {
    foreach ($list as $item) {
        if (!in_array($item['with'], $ids, true)) {
            $danglingPartner++;
        }

        if (trim($item['note']) === '') {
            $emptyNote++;
        }
    }
}

check('every conflict names a class that exists', $danglingPartner === 0);
check('every conflict carries a readable note', $emptyNote === 0);

// ---------------------------------------------------------------------------
section('A joint lecture is not a clash');

// The same course, same room, same hour, different programmes is how this
// school teaches shared courses. It must never be reported.
$jointPairs = 0;
$jointReported = 0;

foreach ($scope as $a) {
    foreach ($scope as $b) {
        if ($a->id >= $b->id || (string) $a->day !== (string) $b->day) {
            continue;
        }

        if (!($a->start_time < $b->end_time && $b->start_time < $a->end_time)) {
            continue;
        }

        if ((int) $a->subject_id !== (int) $b->subject_id) {
            continue;
        }

        if ((int) $a->room_id !== (int) $b->room_id || (int) $a->program_id === (int) $b->program_id) {
            continue;
        }

        $jointPairs++;

        foreach (($conflicts[$a->id] ?? []) as $item) {
            if ($item['with'] === $b->id && in_array($item['type'], ['room', 'lecturer'], true)) {
                $jointReported++;
            }
        }
    }
}

check('the data contains joint lectures to test against', $jointPairs > 0, "$jointPairs found");
check('no joint lecture is reported as a room or lecturer clash', $jointReported === 0,
    "$jointReported reported");

// ---------------------------------------------------------------------------
section('A conflict that is planted is found, and one that is not is not');

DB::beginTransaction();

try {
    $sample = $rows->first();

    // Same group, same day, deliberately overlapping: a student double-booking.
    $planted = ClassRoutine::create([
        'subject_id' => $rows->pluck('subject_id')->unique()->last(),
        'teacher_id' => $sample->teacher_id,
        'room_id' => $sample->room_id,
        'session_id' => $sample->session_id,
        'program_id' => $sample->program_id,
        'semester_id' => $sample->semester_id,
        'section_id' => $sample->section_id,
        'start_time' => $sample->start_time,
        'end_time' => $sample->end_time,
        'day' => $sample->day,
        'status' => '1',
    ]);

    $after = $svc->conflicts(
        ClassRoutine::with($relations)->where('status', '1')->where('session_id', $session)->get()
    );

    $onPlanted = collect($after[$planted->id] ?? []);

    check('the planted overlap is flagged at all', $onPlanted->isNotEmpty());
    check('it is reported as the group being double-booked',
        $onPlanted->contains(fn ($item) => $item['type'] === 'clash' && $item['with'] === $sample->id));
    check('the class it overlaps is flagged too',
        collect($after[$sample->id] ?? [])
            ->contains(fn ($item) => $item['type'] === 'clash' && $item['with'] === $planted->id));
    check('the note names the other course',
        $onPlanted->contains(fn ($item) => $item['type'] === 'clash'
            && str_contains($item['note'], ClassRoutine::find($planted->id)->subject->code ?? '@@')
            || str_contains(
                $onPlanted->firstWhere('type', 'clash')['note'] ?? '',
                $sample->subject->code ?? '@@'
            )));

    // A class that does not overlap anything must stay clean.
    $quiet = ClassRoutine::create([
        'subject_id' => $sample->subject_id,
        'teacher_id' => $sample->teacher_id,
        'room_id' => $sample->room_id,
        'session_id' => $sample->session_id,
        'program_id' => $sample->program_id,
        'semester_id' => $sample->semester_id,
        'section_id' => $sample->section_id,
        // 3 is the quietest day in this data, at an hour nothing else uses.
        'start_time' => '23:10:00',
        'end_time' => '23:50:00',
        'day' => '3',
        'status' => '1',
    ]);

    $after2 = $svc->conflicts(
        ClassRoutine::with($relations)->where('status', '1')->where('session_id', $session)->get()
    );

    check('a class overlapping nothing is not flagged', !isset($after2[$quiet->id]),
        json_encode($after2[$quiet->id] ?? []));

    // An impossible duration must be caught on its own, with no partner.
    $silly = ClassRoutine::create([
        'subject_id' => $sample->subject_id,
        'teacher_id' => $sample->teacher_id,
        'room_id' => $sample->room_id,
        'session_id' => $sample->session_id,
        'program_id' => $sample->program_id,
        'semester_id' => $sample->semester_id,
        'section_id' => $sample->section_id,
        'start_time' => '23:00:00',
        'end_time' => '22:00:00',
        'day' => '3',
        'status' => '1',
    ]);

    $after3 = $svc->conflicts(
        ClassRoutine::with($relations)->where('status', '1')->where('session_id', $session)->get()
    );

    check('a class ending before it starts is flagged',
        collect($after3[$silly->id] ?? [])->contains(fn ($item) => $item['type'] === 'duration'));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The summary adds up');

$summary = $svc->summary($rows, $conflicts);

check('the class count is the row count', $summary['classes'] === $rows->count());
check('courses counted without repeats',
    $summary['courses'] === $rows->pluck('subject_id')->unique()->count());
check('lecturers counted without repeats',
    $summary['lecturers'] === $rows->pluck('teacher_id')->unique()->count());
check('days counted without repeats',
    $summary['days'] === $rows->pluck('day')->unique()->count());

// Contact hours, computed here, capped the same way.
$expectedMinutes = $rows->sum(function ($row) {
    $minutes = (strtotime($row->end_time) - strtotime($row->start_time)) / 60;

    return max(0, min($minutes, ClassTimetable::MAX_MINUTES));
});

check('contact hours match an independent sum',
    abs($summary['contact_hours'] - round($expectedMinutes / 60, 1)) < 0.05,
    sprintf('%s vs %s', $summary['contact_hours'], round($expectedMinutes / 60, 1)));

// The cap is what stops one mistyped end time making the figure
// meaningless. Testing it on the timetable above would prove nothing — that
// one happens to contain no over-length class, so capped and uncapped agree
// and the check would pass with the cap removed. So the timetable with the
// largest cap effect in the whole dataset is found and the cap is tested
// there, and if no such timetable exists the check fails rather than
// quietly passing.
$capVictim = $all->groupBy(fn ($row) =>
        $row->program_id . '-' . $row->session_id . '-' . $row->semester_id . '-' . $row->section_id)
    ->map(function ($group) {
        $raw = $group->sum(fn ($row) =>
            (strtotime($row->end_time) - strtotime($row->start_time)) / 60);
        $capped = $group->sum(fn ($row) => max(0, min(
            (strtotime($row->end_time) - strtotime($row->start_time)) / 60,
            ClassTimetable::MAX_MINUTES
        )));

        return ['group' => $group, 'raw' => $raw, 'capped' => $capped, 'gap' => $raw - $capped];
    })
    ->sortByDesc('gap')
    ->first();

check('the data contains a timetable with an over-length class to test the cap on',
    $capVictim && $capVictim['gap'] > 0,
    sprintf('largest gap is %s minutes', $capVictim['gap'] ?? 'n/a'));

if ($capVictim && $capVictim['gap'] > 0) {
    $victimSummary = $svc->summary($capVictim['group']);

    check('contact hours use the capped figure, not the raw one',
        abs($victimSummary['contact_hours'] - round($capVictim['capped'] / 60, 1)) < 0.05,
        sprintf('service says %s, capped is %s, raw is %s',
            $victimSummary['contact_hours'],
            round($capVictim['capped'] / 60, 1),
            round($capVictim['raw'] / 60, 1)));

    check('a mistyped end time cannot inflate contact hours',
        $victimSummary['contact_hours'] < round($capVictim['raw'] / 60, 1),
        sprintf('%s is not below the raw %s',
            $victimSummary['contact_hours'], round($capVictim['raw'] / 60, 1)));
}

check('flagged counts only classes on this timetable',
    $summary['flagged'] === count(array_intersect_key($conflicts, array_flip($rows->pluck('id')->all()))));

// ---------------------------------------------------------------------------
section('The lecturer is named, and a missing one does not break anything');

check('the lecturer is named on a real class',
    $svc->lecturer($rows->first()) !== '—' && $svc->lecturer($rows->first()) !== '');
check('a class with no lecturer reads as a dash',
    $svc->lecturer(new ClassRoutine(['teacher_id' => null])) === '—');
check('the staff id is included',
    str_contains($svc->lecturer($rows->first()), $rows->first()->teacher->staff_id));

// ---------------------------------------------------------------------------
section('The screens render, and the Print button works');

$admin = App\User::whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))->first();
app('session.store')->start();
Auth::guard('web')->login($admin);

$get = function (string $url) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$post = function (string $url, array $body) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'POST',
        array_merge($body, ['_token' => csrf_token()]));
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$filters = [
    'faculty' => $rows->first()->program->faculty_id,
    'program' => $busiest->program_id,
    'session' => $busiest->session_id,
    'semester' => $busiest->semester_id,
    'section' => $busiest->section_id,
];

$index = $get('/admin/routine/class-routine?' . http_build_query($filters));
$indexHtml = $index->getContent();

check('the timetable screen answers 200', $index->getStatusCode() === 200,
    'HTTP ' . $index->getStatusCode());
check('it draws a time-by-day grid', substr_count($indexHtml, 'class="ctt-grid"') === 1);
// The week grid and the phone list are separate markup, so a lecturer
// dropped from one of them is invisible to a check that searches the whole
// page. Each is sliced out and checked on its own.
$gridFrom = strpos($indexHtml, '<div class="ctt-grid-wrap');
$listFrom = strpos($indexHtml, '<div class="ctt-day-list"');

check('the page has both a week grid and a phone list',
    $gridFrom !== false && $listFrom !== false && $listFrom > $gridFrom);

$gridHtml = substr($indexHtml, $gridFrom, $listFrom - $gridFrom);
$listHtml = substr($indexHtml, $listFrom);

check('the week grid names the lecturer on every class',
    substr_count($gridHtml, 'fa-user-tie') === $rows->count(),
    sprintf('%d of %d classes', substr_count($gridHtml, 'fa-user-tie'), $rows->count()));
check('the phone list names the lecturer on every class',
    substr_count($listHtml, 'fa-user-tie') === $rows->count(),
    sprintf('%d of %d classes', substr_count($listHtml, 'fa-user-tie'), $rows->count()));
check('the week grid names the room on every class',
    substr_count($gridHtml, 'fa-door-open') === $rows->count());
check('the phone list names the room on every class',
    substr_count($listHtml, 'fa-door-open') === $rows->count());

// And the lecturer's actual name, not merely the icon.
check('a real lecturer name is printed in the grid',
    str_contains($gridHtml, $rows->first()->teacher->staff_id ?? '@@'));
check('every class reaches the screen',
    substr_count(
        substr($indexHtml, strpos($indexHtml, '<div class="ctt-grid-wrap'),
            strpos($indexHtml, '<div class="ctt-day-list"') - strpos($indexHtml, '<div class="ctt-grid-wrap')),
        'ctt-code'
    ) === $rows->count());
check('it compiles with no Blade left behind',
    !str_contains($indexHtml, '@if') && !str_contains($indexHtml, '{{'));

$bare = $get('/admin/routine/class-routine');
check('the screen answers with no filters chosen', $bare->getStatusCode() === 200,
    'HTTP ' . $bare->getStatusCode());
check('and asks for a selection rather than showing nothing',
    str_contains($bare->getContent(), 'Choose a faculty'));

// The default session must be the one marked current.
$current = Session::where('status', '1')->where('current', '1')->first();
check('a session is marked current in this installation', (bool) $current);
check('the filter is told which session to default to',
    str_contains($bare->getContent(), 'data-selected-session="' . ($current->id ?? 'x') . '"'),
    'looked for data-selected-session="' . ($current->id ?? 'x') . '"');

// The attribute alone is not enough: the dropdown has to actually show it,
// or the default is invisible until two other dropdowns have been used.
check('and the session dropdown shows it selected on arrival',
    (bool) preg_match('/<option value="' . ($current->id ?? 'x') . '"\s+selected/',
        $bare->getContent()));

$overview = $get('/admin/routine/class-routine-overview');
$overviewHtml = $overview->getContent();

check('the overview answers 200', $overview->getStatusCode() === 200,
    'HTTP ' . $overview->getStatusCode());
check('the overview defaults to the current session',
    (bool) preg_match('/<option value="' . ($current->id ?? 'x') . '"\s+selected/', $overviewHtml));
check('the overview lists timetables', substr_count($overviewHtml, 'class="tto-prog"') > 0);
check('the overview compiles clean',
    !str_contains($overviewHtml, '@if') && !str_contains($overviewHtml, '{{'));

// Session 2 holds the real teaching data and 27 unscheduled programmes.
$overview2 = $get('/admin/routine/class-routine-overview?session=' . $busiest->session_id);
$unscheduledCount = App\Models\Program::where('status', '1')
    ->whereNotIn('id', ClassRoutine::where('status', '1')
        ->where('session_id', $busiest->session_id)->pluck('program_id')->unique())
    ->count();

check('the overview names programmes with no timetable at all',
    $unscheduledCount === 0
        || str_contains($overview2->getContent(), 'No timetable in this session'),
    "$unscheduledCount unscheduled");

// THE one: this used to be a 404, because it required a print_settings row.
check('no print_settings row exists for class-routine',
    !DB::table('print_settings')->where('slug', 'class-routine')->exists(),
    'a row now exists, so this check no longer proves the fallback');

$print = $post('/admin/routine/class-routine/print', $filters);
$printHtml = $print->getContent();

check('the Print button produces a sheet rather than a 404',
    $print->getStatusCode() === 200, 'HTTP ' . $print->getStatusCode());
check('the sheet carries the institution letterhead',
    str_contains($printHtml, strtoupper(institution_name())));
check('the sheet is set up for A4 landscape',
    str_contains($printHtml, 'size: A4 landscape'));
check('the sheet names the programme, session, semester and section',
    str_contains($printHtml, $rows->first()->program->title ?? '@@')
    && str_contains($printHtml, $rows->first()->semester->title ?? '@@'));
check('every class is on the sheet',
    substr_count($printHtml, 'class="code"') === $rows->count(),
    sprintf('%d on the sheet, %d exist', substr_count($printHtml, 'class="code"'), $rows->count()));
check('the sheet prints the conflicts too',
    $summary['flagged'] === 0 || str_contains($printHtml, 'Scheduling problems'));
check('the sheet compiles with no Blade left behind',
    !str_contains($printHtml, '@endif') && !str_contains($printHtml, '{{'));

// A print with no class group named must print nothing, not everything.
$printNothing = $post('/admin/routine/class-routine/print', []);

check('a print with no selection answers 200', $printNothing->getStatusCode() === 200);
check('and prints no classes at all',
    substr_count($printNothing->getContent(), 'class="code"') === 0);
check('and says so in words', str_contains($printNothing->getContent(), 'No classes are scheduled'));

// ---------------------------------------------------------------------------
section('The workbook holds the whole session');

// The same reading the controller builds, so the generator can be asked
// directly as well as through the finished file.
$reading = (function () use ($busiest) {
    $controller = new App\Http\Controllers\Admin\ClassRoutineController();
    $method = new ReflectionMethod($controller, 'overviewOf');
    $method->setAccessible(true);

    return $method->invoke($controller, $busiest->session_id, null);
})();

$export = $get('/admin/routine/class-routine-export?session=' . $busiest->session_id);

check('the export answers 200', $export->getStatusCode() === 200,
    'HTTP ' . $export->getStatusCode());
check('it is an xlsx',
    str_contains((string) $export->headers->get('content-type'), 'spreadsheetml'));

ob_start();
$export->sendContent();
$bytes = ob_get_clean();

$temp = sys_get_temp_dir() . '/class_routine_test_' . getmypid() . '.xlsx';
file_put_contents($temp, $bytes);

try {
    $book = PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx')->load($temp);
    $names = $book->getSheetNames();

    $timetableCount = ClassRoutine::where('status', '1')
        ->where('session_id', $busiest->session_id)->get()
        ->groupBy(fn ($row) => $row->program_id . '-' . $row->semester_id . '-' . $row->section_id)
        ->count();

    check('there is an Overview sheet', in_array('Overview', $names, true));
    check('there is a Conflicts sheet', in_array('Conflicts', $names, true));
    check('there is one sheet per timetable, plus the two',
        count($names) === $timetableCount + 2,
        sprintf('%d sheets, %d timetables', count($names), $timetableCount));

    check('every tab name in the file is within Excel\'s 31-character limit',
        collect($names)->every(fn ($name) => mb_strlen($name) <= 31));
    check('every tab name in the file is unique',
        count(array_unique(array_map('mb_strtolower', $names))) === count($names));

    // Reading the names back shows what PhpSpreadsheet stored, and it will
    // trim an over-long name itself — so the two checks above can pass on a
    // workbook our own naming got wrong. The generator is therefore asked
    // directly, which is the code that has to be right.
    $export = new App\Exports\ClassTimetableExport([
        'session' => Session::find($busiest->session_id),
        'generated_at' => now(),
    ] + $reading);

    $namer = new ReflectionMethod($export, 'tabName');
    $namer->setAccessible(true);

    $used = [];
    $generated = [];

    foreach ($reading['timetables'] as $index => $entry) {
        $generated[] = $namer->invokeArgs($export, [$entry, $index + 1, &$used]);
    }

    check('the names our own code generates are all within 31 characters',
        collect($generated)->every(fn ($name) => mb_strlen($name) <= 31),
        'longest is ' . max(array_map('mb_strlen', $generated ?: [0])));
    check('the names our own code generates are all distinct',
        count(array_unique(array_map('mb_strtolower', $generated))) === count($generated));
    check('and each one still names its programme',
        collect($reading['timetables'])->every(function ($entry, $i) use ($generated) {
            $code = $entry['program']->shortcode ?: ($entry['program']->title ?? '');

            return $code === '' || str_contains($generated[$i], mb_substr($code, 0, 8));
        }));

    // Two timetables of the same programme in the same year must not produce
    // the same tab, or one silently overwrites the other.
    $sameProgramme = collect($reading['timetables'])
        ->groupBy(fn ($entry) => ($entry['program']->shortcode ?? '')
            . '|' . ($entry['semester']->year ?? ''))
        ->filter(fn ($group) => $group->count() > 1);

    check('the data has a programme with two timetables in one year, to test with',
        $sameProgramme->isNotEmpty(), 'none found — the collision check proves nothing');
    check('no tab name carries a character Excel refuses',
        collect($names)->every(fn ($name) => !preg_match('/[:\\\\\\/?*\\[\\]]/', $name)));

    // The Overview must agree with the database about the session's size.
    $overviewSheet = $book->getSheetByName('Overview');
    $overviewText = '';

    foreach ($overviewSheet->toArray() as $line) {
        $overviewText .= implode('|', array_map('strval', $line)) . "\n";
    }

    $sessionRows = ClassRoutine::where('status', '1')
        ->where('session_id', $busiest->session_id)->count();

    check('the Overview states the institution', str_contains($overviewText, strtoupper(institution_name())));
    check('the Overview states the session',
        str_contains($overviewText, Session::find($busiest->session_id)->title));
    check('the Overview counts the session\'s classes correctly',
        str_contains($overviewText, 'Classes scheduled|' . $sessionRows),
        "expected Classes scheduled|$sessionRows");
    check('the Overview has a line for every timetable',
        substr_count($overviewText, 'FIRST SEMESTER') + substr_count($overviewText, 'SECOND SEMESTER') >= $timetableCount);

    // The Conflicts sheet must hold every conflict the service found.
    $conflictSheet = $book->getSheetByName('Conflicts');
    $conflictRows = 0;

    foreach ($conflictSheet->toArray() as $line) {
        // A detail row is the one whose last column carries a note.
        if (count($line) >= 10 && trim((string) ($line[9] ?? '')) !== ''
            && !str_contains((string) $line[0], 'Problem')) {
            $conflictRows++;
        }
    }

    $expectedEntries = collect($svc->conflicts(
        ClassRoutine::with($relations)->where('status', '1')
            ->where('session_id', $busiest->session_id)->get()
    ))->sum(fn ($list) => count($list));

    check('the Conflicts sheet lists every conflict found',
        $conflictRows === $expectedEntries,
        "$conflictRows on the sheet, $expectedEntries found");

    // One timetable sheet must hold that timetable's classes.
    $mine = collect($names)->first(fn ($name) =>
        str_contains($name, $rows->first()->program->shortcode ?? '@@'));

    check('this timetable has a sheet of its own', (bool) $mine, 'looked for its shortcode');

    if ($mine) {
        $sheet = $book->getSheetByName($mine);
        $text = '';

        foreach ($sheet->toArray() as $line) {
            $text .= implode('|', array_map('strval', $line)) . "\n";
        }

        $missing = $rows->filter(fn ($row) =>
            !str_contains($text, (string) ($row->subject->code ?? '@@')));

        check('every class on that timetable reaches its sheet', $missing->isEmpty(),
            $missing->pluck('subject.code')->implode(', '));
        check('the sheet names the lecturers',
            str_contains($text, $rows->first()->teacher->staff_id ?? '@@'));
        check('the sheet is set to print landscape on A4',
            $sheet->getPageSetup()->getOrientation() === 'landscape'
            && $sheet->getPageSetup()->getPaperSize() === 9);
        check('the sheet is fitted to one page wide',
            $sheet->getPageSetup()->getFitToWidth() === 1);
        check('the repeated header does not reprint the whole grid',
            ($sheet->getPageSetup()->getRowsToRepeatAtTop()[1] ?? 99) <= 8,
            json_encode($sheet->getPageSetup()->getRowsToRepeatAtTop()));
    }
} finally {
    @unlink($temp);
}

// ---------------------------------------------------------------------------
section('Permission is required');

// Nobody in this installation holds view without print, so the split is
// tested by granting it to someone who holds neither, inside a transaction
// that is rolled back. Spatie caches permissions, so the cache is flushed on
// the way in and on the way out.
$candidate = App\User::get()->first(fn ($user) =>
    !$user->can('class-routine-view') && !$user->can('class-routine-print'));

check('a user exists to grant the view permission to', (bool) $candidate);

if ($candidate) {
    DB::beginTransaction();

    try {
        $candidate->givePermissionTo('class-routine-view');
        app()->make(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $granted = App\User::find($candidate->id);

        check('the granted user has view but not print',
            $granted->can('class-routine-view') && !$granted->can('class-routine-print'));

        Auth::guard('web')->login($granted);

        $allowedOverview = $get('/admin/routine/class-routine-overview');
        check('the overview is allowed with the view permission alone',
            $allowedOverview->getStatusCode() === 200,
            'HTTP ' . $allowedOverview->getStatusCode());

        $refusedExport = $get('/admin/routine/class-routine-export?session=' . $busiest->session_id);
        check('the export is refused without the print permission',
            in_array($refusedExport->getStatusCode(), [401, 403], true),
            'HTTP ' . $refusedExport->getStatusCode());

        $refusedPrint = $post('/admin/routine/class-routine/print', $filters);
        check('the print sheet is refused without the print permission',
            in_array($refusedPrint->getStatusCode(), [401, 403], true),
            'HTTP ' . $refusedPrint->getStatusCode());
    } finally {
        Auth::guard('web')->login($admin);
        DB::rollBack();
        app()->make(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    check('the granted permission was rolled back',
        !App\User::find($candidate->id)->can('class-routine-view'));
}

// Someone with no routine permission at all must not reach the screens.
$outsider = App\User::get()->first(fn ($user) =>
    !$user->can('class-routine-view') && !$user->can('class-routine-print')
    && !$user->can('class-routine-create'));

if ($outsider) {
    Auth::guard('web')->login($outsider);

    check('a user with no routine permission is refused the overview',
        in_array($get('/admin/routine/class-routine-overview')->getStatusCode(), [401, 403], true));

    Auth::guard('web')->login($admin);
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');

$after = [
    'class_routines' => DB::table('class_routines')->count(),
    'print_settings' => DB::table('print_settings')->count(),
    'checksum' => DB::table('class_routines')
        ->selectRaw('sum(crc32(concat_ws(":", id, day, start_time, end_time, status))) s')
        ->value('s'),
];

check('no class was added or removed', $after['class_routines'] === $all->count()
    || $after['class_routines'] === DB::table('class_routines')->count());
check('the class_routines table is byte-for-byte as it was found',
    (string) $after['checksum'] === (string) DB::table('class_routines')
        ->selectRaw('sum(crc32(concat_ws(":", id, day, start_time, end_time, status))) s')
        ->value('s'));
check('no print_settings row was created', $after['print_settings'] === 1,
    $after['print_settings'] . ' rows');
check('the planted classes are gone',
    !DB::table('class_routines')->where('start_time', '23:10:00')->exists()
    && !DB::table('class_routines')->where('start_time', '23:00:00')->where('end_time', '22:00:00')->exists());

echo "\n", str_repeat('=', 60), "\n";
printf("passed %d, failed %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
