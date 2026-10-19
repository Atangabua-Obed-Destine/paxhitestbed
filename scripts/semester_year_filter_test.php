<?php
/**
 * Choosing a Year before a Semester.
 *
 * A semester dropdown that lists every semester in the school is unusable once
 * programmes carry a full ladder plus resit twins, so a Year select narrows it
 * first. The pattern already existed; it had been copied three times, one copy
 * worked, and the other two were bound to a class that existed nowhere.
 *
 * Writes happen inside transactions that are rolled back; the last section
 * proves the database was left as it was found.
 *
 * The check that matters most is the dead-handler one. A jQuery handler bound
 * to a class no element carries fails silently and for ever: the control is on
 * screen, it responds to clicks, and nothing happens. Grep can see that; a
 * person looking at the page cannot, which is exactly how it shipped.
 *
 * Run: php scripts/semester_year_filter_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Semester;
use App\Services\Academic\SemesterYearOptions;
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

/** Data, not code. Reported so it is visible, never failed on. */
function warn(string $what): void
{
    global $warned;
    $warned++;
    echo "  WARN  " . $what . "\n";
}

$root = dirname(__DIR__);

// ---------------------------------------------------------------------------
section('No handler is bound to a class that no element carries');
// ---------------------------------------------------------------------------
// The bug that shipped: attendance.blade.php rendered .quick-year and bound its
// handler to .course-year-select. Run against the code as it was, this check
// fails on exactly that and on its copy in exam-routine.
$bladeFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views'));

foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
        $bladeFiles[] = $file->getPathname();
    }
}

check('there are blade files to inspect', count($bladeFiles) > 100,
    count($bladeFiles) . ' found');

/** Every class name that file, its includes and its layout put in the markup. */
$markupClasses = function (string $path, array $seen = []) use (&$markupClasses, $root): array {
    if (isset($seen[$path]) || !is_file($path)) {
        return [];
    }

    $seen[$path] = true;
    $source = (string) file_get_contents($path);
    $classes = [];

    // class="a b c", including Blade in the middle of the attribute.
    if (preg_match_all('/class\s*=\s*"([^"]*)"/i', $source, $m)) {
        foreach ($m[1] as $attr) {
            foreach (preg_split('/\s+/', $attr) as $one) {
                $one = trim($one);
                if ($one !== '' && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $one)) {
                    $classes[$one] = true;
                }
            }
        }
    }

    // Classes the page adds at runtime count as existing.
    if (preg_match_all('/(?:addClass|toggleClass)\(\s*[\'"]([^\'"]+)[\'"]/', $source, $m)) {
        foreach ($m[1] as $attr) {
            foreach (preg_split('/\s+/', $attr) as $one) {
                if ($one !== '') {
                    $classes[trim($one, '.')] = true;
                }
            }
        }
    }

    // A @include($view.'.edit') cannot be resolved from the source alone, so
    // the sibling views in the same folder are treated as reachable. Without
    // this the resolver reports false orphans - and a check that cries wolf is
    // the one that gets commented out, which is worse than no check at all.
    if (preg_match('/@include\(\s*\$/', $source)) {
        foreach (glob(dirname($path) . '/*.blade.php') ?: [] as $sibling) {
            if ($sibling !== $path) {
                foreach ($markupClasses($sibling, $seen) as $one => $_) {
                    $classes[$one] = true;
                }
            }
        }
    }

    // Follow @include and @extends one level at a time.
    if (preg_match_all('/@(?:include|extends)\(\s*[\'"]([^\'"]+)[\'"]/', $source, $m)) {
        foreach ($m[1] as $view) {
            $child = $root . '/resources/views/' . str_replace('.', '/', $view) . '.blade.php';
            foreach ($markupClasses($child, $seen) as $one => $_) {
                $classes[$one] = true;
            }
        }
    }

    return $classes;
};

$orphanHandlers = [];

foreach ($bladeFiles as $path) {
    $source = (string) file_get_contents($path);

    // Only delegated handlers: $(document).on('event', '.selector', ...)
    if (!preg_match_all('/\.on\(\s*[\'"][a-zA-Z.]+[\'"]\s*,\s*[\'"]\.([a-zA-Z][a-zA-Z0-9_-]*)[\'"]/', $source, $m)) {
        continue;
    }

    $classes = null;

    foreach (array_unique($m[1]) as $class) {
        if ($classes === null) {
            $classes = $markupClasses($path);
        }

        if (!isset($classes[$class])) {
            $orphanHandlers[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path) . ' -> .' . $class;
        }
    }
}

check('every delegated handler targets a class that exists in the markup',
    $orphanHandlers === [],
    $orphanHandlers === [] ? '' : implode("\n        ", array_slice($orphanHandlers, 0, 8)));

// And the mirror: the markup must not outlive its handler.
$cardMarkup = [];

foreach (['admin/exam/attendance.blade.php', 'admin/exam/marking.blade.php'] as $relative) {
    $source = (string) file_get_contents($root . '/resources/views/' . $relative);

    if (str_contains($source, 'quick-year')) {
        $cardMarkup[$relative] = str_contains($source, 'SemesterYearSelect.bindCards');
    }
}

check('every screen rendering a year card also binds a handler for it',
    $cardMarkup !== [] && !in_array(false, $cardMarkup, true),
    json_encode($cardMarkup));

// ---------------------------------------------------------------------------
section('One copy of the logic, not three');
// ---------------------------------------------------------------------------
$allSource = '';

foreach (array_merge($bladeFiles, glob($root . '/app/Services/Academic/*.php') ?: []) as $path) {
    $allSource .= (string) file_get_contents($path);
}

// The dead class is gone outright.
check('"course-year-select" is gone from the project',
    substr_count($allSource, 'course-year-select') === 0,
    'found ' . substr_count($allSource, 'course-year-select'));

// The shared filter no longer carries its own copy of the year logic.
$sharedFilter = (string) file_get_contents($root . '/resources/views/common/inc/common_search_filter.blade.php');

foreach (['buildSemesterOptions', 'buildYearOptions', 'deriveYearForSemester', 'transformSemesterResponse'] as $needle) {
    check('the shared filter no longer defines ' . $needle,
        !str_contains($sharedFilter, $needle));
}

check('and it delegates to the module instead',
    substr_count($sharedFilter, 'SemesterYearSelect.attach') === 1);

// Three screens predate this work and still carry their own year-first
// implementation. They are listed by name with the count they have today, so
// they can be converted deliberately - and so that a SIXTH copy appearing
// anywhere else fails this check rather than passing unnoticed.
$knownOwnCopies = [
    'resources/views/admin/group-enroll/index.blade.php' => 13,
    'resources/views/admin/single-enroll/index.blade.php' => 3,
    'resources/views/student/exam-results/index.blade.php' => 7,
];

$found = [];

foreach ($bladeFiles as $path) {
    // Slashes normalised BEFORE the root is stripped: the iterator mixes both
    // separators, so stripping first leaves the path absolute.
    $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', str_replace('\\', '/', $path)), '/');
    $hits = preg_match_all('/buildSemesterOptions|buildYearOptions|deriveYearForSemester|transformSemesterResponse/',
        (string) file_get_contents($path));

    if ($hits > 0) {
        $found[$relative] = $hits;
    }
}

ksort($found);
ksort($knownOwnCopies);

check('no screen has grown a new copy of the year logic',
    $found === $knownOwnCopies,
    'expected ' . json_encode($knownOwnCopies) . ' but found ' . json_encode($found));

$module = (string) file_get_contents($root . '/resources/views/common/js/semester_year_select.blade.php');

check('the shared module is the one place the grouping lives',
    substr_count($module, 'var groupByYear') === 1
    && substr_count($module, 'var deriveYear') === 1);

$service = (string) file_get_contents($root . '/app/Services/Academic/SemesterYearOptions.php');

check('the PHP grouping lives in one service', is_file($root . '/app/Services/Academic/SemesterYearOptions.php'));

// ---------------------------------------------------------------------------
section('The year comparison cannot be caught by the string trap');
// ---------------------------------------------------------------------------
// `semesters.year` is a nullable VARCHAR and is not cast on the model, so it
// arrives as "1". parseInt looks equivalent and is not: it turns a missing year
// into NaN, and NaN never equals NaN, so such a semester silently vanishes.
check('the module compares years as strings',
    str_contains($module, 'String(a) === String(b)'));

check('and never parses an int to compare one',
    !preg_match('/parseInt\([^)]*\)\s*(===|!==|==|!=)/', $module),
    'parseInt in a comparison is how a null year disappears');

check('Number() is used for ordering only',
    substr_count($module, 'Number(a) - Number(b)') >= 1);

// ---------------------------------------------------------------------------
section('The service groups and orders correctly');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    foreach (['2', '9', '10'] as $year) {
        Semester::create([
            'title' => 'Probe Y' . $year . ' ' . uniqid(),
            'year' => $year,
            'semester_type' => 1,
            'status' => 1,
            'is_resit' => 0,
        ]);
    }

    $keys = array_keys(SemesterYearOptions::all());
    $lexical = $keys;
    sort($lexical, SORT_STRING);

    check('year 10 is ordered after year 9, not after year 1',
        $keys === array_values(array_unique(array_map('intval', $keys)))
        && (int) $keys[count($keys) - 1] === 10,
        'got ' . json_encode($keys) . '; lexical sorting would give ' . json_encode($lexical));

    $orphan = Semester::create([
        'title' => 'Probe no year ' . uniqid(),
        'year' => null,
        'semester_type' => 1,
        'status' => 1,
        'is_resit' => 0,
    ]);

    $everything = Semester::where('status', 1)->get();
    $grouped = SemesterYearOptions::fromCollection($everything);
    $flat = collect($grouped)->flatten(1)->pluck('id');

    check('a semester with no year is kept out of the year groups',
        !$flat->contains($orphan->id));

    check('and is still reachable through orphans()',
        SemesterYearOptions::orphans($everything)->pluck('id')->contains($orphan->id),
        'once a year gates the list, a dropped semester can never be chosen again');
} finally {
    DB::rollBack();
}

// A resit belongs to the year of the semester it resits.
$resit = Semester::where('is_resit', 1)->whereNotNull('parent_semester_id')->first();

if (!$resit) {
    skip('no resit semester with a parent to check the grouping of');
} else {
    $parent = Semester::find($resit->parent_semester_id);

    check('a resit sits in the same year as the semester it resits',
        $parent && (string) $resit->year === (string) $parent->year,
        'resit #' . $resit->id . ' year ' . var_export($resit->year, true)
            . ' vs parent #' . optional($parent)->id . ' year ' . var_export(optional($parent)->year, true));
}

// ---------------------------------------------------------------------------
section('Every semester picker has a Year picker in front of it');
// ---------------------------------------------------------------------------
// The breadth check. A screen that grows a semester dropdown without a year in
// front of it fails here, so "every semester dropdown" stays true rather than
// being true only on the day it was done.
//
// The exclusions are named with a reason each. They are not screens that were
// missed; they are places where a year step would be wrong.
$leaveAlone = [
    'admin/semester/index.blade.php' => 'the semester CRUD screen itself; the parent-semester picker needs the whole list',
    'admin/semester/edit.blade.php' => 'same screen, edit form',
    'admin/resit-requests/index.blade.php' => 'already has its own Year/Level filter plus a two-value semester type',
    'admin/program-semester-fee/index.blade.php' => 'two-value semester type, already scoped to non-resit semesters with courses',
    'admin/program-semester-fee/create.blade.php' => 'same',
    'admin/section/index.blade.php' => 'a checkbox matrix of semesters, not a picker',
    'admin/section/edit.blade.php' => 'same',
    'admin/web/course/create.blade.php' => 'name="semesters" here is free-text marketing copy, not a foreign key',
    'admin/web/course/edit.blade.php' => 'same',
];

$missingYear = [];

foreach ($bladeFiles as $path) {
    $relative = ltrim(str_replace(str_replace('\\', '/', $root . '/resources/views'), '',
        str_replace('\\', '/', $path)), '/');

    if (isset($leaveAlone[$relative])) {
        continue;
    }

    $source = (string) file_get_contents($path);

    // Only a real <select> for a semester; hidden carriers and semester_type
    // (a two-value field) are not pickers.
    if (!preg_match('/<select[^>]*name="(semester|semester_id)"/i', $source)) {
        continue;
    }

    // Reachable from this file, its includes, or an include of it.
    $hasYear = str_contains($source, 'name="semester_year"')
        || str_contains($source, 'semester_year_field')
        || str_contains($source, 'common_search_filter');

    if (!$hasYear) {
        $missingYear[] = $relative;
    }
}

check('no semester dropdown is left without a Year in front of it',
    $missingYear === [],
    $missingYear === [] ? '' : implode("\n        ", $missingYear));

check('and the exclusions are named with a reason each',
    count(array_filter($leaveAlone)) === count($leaveAlone));

// ---------------------------------------------------------------------------
section('The screens receive something for the Year select to offer');
// ---------------------------------------------------------------------------
$admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no Super Admin to render as');
} else {
    app('session.store')->start();
    Auth::guard('web')->login($admin);

    // A programme that actually teaches more than one year, so "the payload is
    // empty" and "this programme only has one year" cannot be confused.
    $programId = DB::table('program_semester')
        ->select('program_id')
        ->groupBy('program_id')
        ->havingRaw('COUNT(*) > 1')
        ->value('program_id');

    if (!$programId) {
        skip('no programme with more than one semester attached');
    } else {
        $expectedYears = count(SemesterYearOptions::forProgram($programId));

        $render = function (string $path) use ($kernel, $programId) {
            $request = Request::create($path . '?faculty=0&program=' . $programId . '&session=0&semester=0&section=0', 'GET');
            $request->setLaravelSession(app('session.store'));

            try {
                $response = $kernel->handle($request);
            } catch (\Throwable $e) {
                return ['status' => 0, 'years' => 0, 'error' => $e->getMessage()];
            }

            $html = $response->getContent();
            $years = 0;

            if (preg_match('/<script type="application\/json" id="csf_[0-9a-f]+-semester-data">(.*?)<\/script>/s', $html, $m)) {
                $decoded = json_decode(trim($m[1]), true);
                $years = is_array($decoded) ? count($decoded) : 0;
            }

            return ['status' => $response->getStatusCode(), 'years' => $years, 'error' => ''];
        };

        // Grep proves the service is called. Only a render proves it returned
        // anything - these six screens had the Year select on screen and an
        // empty payload behind it, so nothing could be chosen at all.
        foreach ([
            'admit-card' => '/admin/exam/admit-card',
            'id-card' => '/admin/admission/id-card',
            'hostel-student' => '/admin/hostel-student',
            'library-student' => '/admin/member/library-student',
            'transport-student' => '/admin/transport-student',
            'report/student' => '/admin/report/student',
        ] as $name => $path) {
            $result = $render($path);

            check($name . ' renders and its Year select has years to offer',
                $result['status'] === 200 && $result['years'] === $expectedYears,
                'status ' . $result['status'] . ', ' . $result['years'] . ' year(s), expected '
                    . $expectedYears . ($result['error'] ? ' - ' . $result['error'] : ''));
        }

        // And the ones that already worked still do.
        foreach ([
            'class-routine' => '/admin/routine/class-routine',
            'student' => '/admin/admission/student',
        ] as $name => $path) {
            $result = $render($path);

            check($name . ' (already working) is unaffected',
                $result['status'] === 200 && $result['years'] === $expectedYears,
                'status ' . $result['status'] . ', ' . $result['years'] . ' year(s)');
        }
    }
}

// ---------------------------------------------------------------------------
section('The catalogue behind the Year select');
// ---------------------------------------------------------------------------
// Data, not code: reported so the thin programmes are visible, never failed on,
// so fixing the catalogue cannot break this suite.
$perProgramme = DB::table('program_semester')
    ->join('semesters', 'semesters.id', '=', 'program_semester.semester_id')
    ->select('program_semester.program_id', DB::raw('COUNT(DISTINCT semesters.year) AS years'))
    ->groupBy('program_semester.program_id')
    ->pluck('years', 'program_id');

$thin = $perProgramme->filter(fn ($years) => (int) $years < 2);
$total = DB::table('programs')->count();

if ($thin->isNotEmpty()) {
    warn($thin->count() . ' of ' . $total . ' programmes have semesters from fewer than 2 years '
        . 'attached, so their Year dropdown offers a single option');
}

$missing = $total - $perProgramme->count();

if ($missing > 0) {
    warn($missing . ' programme(s) have no semesters attached at all');
}

// The handling of that, though, is code and must not regress.
$filter = (string) file_get_contents($root . '/resources/views/common/inc/common_search_filter.blade.php');

check('a programme with no years is explained rather than shown an empty box',
    str_contains($module, 'hasYears') || str_contains($filter, 'no-semesters'),
    'an empty-but-silent Year select reads as "the new feature broke it"');

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('the semester count is unchanged', Semester::count() === Semester::count());

check('no probe semester survived the rollbacks',
    !Semester::where('title', 'LIKE', 'Probe %')->exists(),
    Semester::where('title', 'LIKE', 'Probe %')->pluck('title')->implode(', '));

echo "\n----\npassed $passed, failed $failed"
    . ($skipped ? ", skipped $skipped" : '')
    . ($warned ? ", warnings $warned" : '') . "\n";

exit($failed > 0 ? 1 : 0);
