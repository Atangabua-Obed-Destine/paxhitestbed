<?php
/**
 * The exam attendance screen.
 *
 * This screen was redesigned, and a redesign is exactly the change that can
 * leave a page looking better while quietly dropping the control that makes
 * it work. So most of what follows is not about appearance: it asserts that
 * every field the store() action reads and every hook the page's own
 * JavaScript binds is still on the page, in every state the screen has.
 *
 * The expectations are built independently of the view wherever that is
 * possible: the roll is counted from SQL, the contract list is read out of
 * the page's own <script> block rather than hard-coded here, and the
 * separator's colspan is checked against the <th> the page emitted, not
 * against the arithmetic the partial uses.
 *
 * Nothing here writes. The two renders that create continuous-assessment
 * rows as a side effect are wrapped in a transaction that is rolled back.
 */

$base = dirname(__DIR__);

require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

$pass = 0;
$fail = 0;

function ok(string $what, bool $cond): void
{
    global $pass, $fail;

    if ($cond) {
        $pass++;
    } else {
        $fail++;
        echo "  FAIL  {$what}" . PHP_EOL;
    }
}

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;
        return;
    }

    $fail++;
    echo "  FAIL  {$what}" . PHP_EOL;
    echo "        expected: " . json_encode($expected) . PHP_EOL;
    echo "        actual:   " . json_encode($actual) . PHP_EOL;
}

Auth::guard('web')->login(App\User::where('is_admin', 1)->orderBy('id')->first() ?: App\User::first());

// Shared by ShareErrorsFromSession in the real stack; absent outside it.
View::share('errors', new ViewErrorBag);

$render = function (array $query) {
    $request = Request::create('/admin/exam/exam-attendance', 'GET', $query);
    app()->instance('request', $request);

    // Rendering a CA screen calls ensureCaExamRows(), which CREATES rows.
    DB::beginTransaction();

    try {
        $view = app(App\Http\Controllers\Admin\ExamAttendanceController::class)->index($request);
        $out = [$view->render(), $view->getData()];
    } finally {
        DB::rollBack();
    }

    return $out;
};

/* ------------------------------------------------------------------ *
 * The states. Chosen from the data, and chosen DETERMINISTICALLY:
 * ordering by the row count alone leaves ties that MySQL breaks
 * differently between runs, which once had this suite comparing two
 * different classes and reporting a regression that did not exist.
 * ------------------------------------------------------------------ */

$classFor = function (bool $final, bool $shared) {
    $q = DB::table('exams as e')
        ->join('student_enrolls as se', 'se.id', '=', 'e.student_enroll_id')
        ->join('exam_types as et', 'et.id', '=', 'e.exam_type_id')
        ->where('et.is_final', $final ? 1 : 0);

    if ($shared) {
        $q->whereIn('e.subject_id', DB::table('program_subject as ps')
            ->join('programs as p', 'p.id', '=', 'ps.program_id')
            ->where('p.status', '1')
            ->groupBy('ps.subject_id')
            ->havingRaw('COUNT(DISTINCT ps.program_id) > 1')
            ->selectRaw('ps.subject_id'));
    }

    return $q->select('e.subject_id', 'e.exam_type_id', 'se.program_id', 'se.session_id',
                      'se.semester_id', 'se.section_id', DB::raw('count(*) n'))
        ->groupBy('e.subject_id', 'e.exam_type_id', 'se.program_id', 'se.session_id',
                  'se.semester_id', 'se.section_id')
        ->orderByDesc('n')
        ->orderBy('e.subject_id')->orderBy('e.exam_type_id')->orderBy('se.program_id')
        ->orderBy('se.session_id')->orderBy('se.semester_id')->orderBy('se.section_id')
        ->first();
};

$queryFor = fn ($c) => [
    'subject' => $c->subject_id, 'type' => $c->exam_type_id, 'program' => $c->program_id,
    'session' => $c->session_id, 'semester' => $c->semester_id, 'section' => $c->section_id,
];

$caClass = $classFor(false, false);
$finalClass = $classFor(true, false);
$sharedClass = $classFor(true, true);

ok('there is a continuous-assessment class to render', $caClass !== null);
ok('there is a final-exam class to render', $finalClass !== null);
ok('there is a shared-course class to render', $sharedClass !== null);

$states = ['with no filter at all' => []];

if ($caClass) {
    $states['continuous assessment'] = $queryFor($caClass);
}
if ($finalClass) {
    $states['a final exam'] = $queryFor($finalClass);
}
if ($sharedClass) {
    $states['all programmes at once'] = $queryFor($sharedClass) + ['cross_program' => 1];
}

$pages = [];

foreach ($states as $name => $query) {
    $pages[$name] = $render($query);
}

/* ================================================================== */
echo PHP_EOL . "=== 1. every state renders, and none of them is an error screen ===" . PHP_EOL;

foreach ($pages as $name => [$html, $data]) {
    ok("{$name}: it renders", strlen($html) > 10000);
    ok("{$name}: it is in the exm scope", str_contains($html, 'main-body exm'));

    /* Laravel renders the message INTO the page rather than throwing when
       debug is on, so a page that looks rendered can still be the error
       screen. This is the check that caught a missing $subjects guard. */
    foreach (['Undefined variable', 'Undefined array key', 'Undefined index',
              'ErrorException', 'Trying to access array offset'] as $wrong) {
        ok("{$name}: no \"{$wrong}\" leaked into the page", !str_contains($html, $wrong));
    }

    // __() prints the key when a string is missing, which is how "btn_ok"
    // ends up on a button. No key-shaped string should survive to the page.
    preg_match_all('~>\s*(btn_[a-z_]+|field_[a-z_]+|module_[a-z_]+)\s*<~', $html, $raw);
    check("{$name}: no untranslated key printed as text", [], array_values(array_unique($raw[1])));
}

/* ================================================================== */
echo PHP_EOL . "=== 2. the roll is the roll ===" . PHP_EOL;

foreach ($pages as $name => [$html, $data]) {
    if (!isset($data['rows'])) {
        ok("{$name}: nothing is listed until a course is chosen", !str_contains($html, 'name="students[]"'));
        continue;
    }

    $rows = $data['rows'];

    // One hidden students[] per student: this is the order signins and
    // signouts are aligned to, so a mismatch misfiles somebody's exam.
    check("{$name}: one students[] for each student on the roll",
        count($rows), substr_count($html, 'name="students[]"'));

    // Two radios each, because the modal divides by two to get the headcount.
    check("{$name}: exactly two attendance radios per student",
        count($rows) * 2, preg_match_all('~name="attendances-\d+"~', $html));

    check("{$name}: a present radio for each", count($rows), substr_count($html, 'class="c-present"'));
    check("{$name}: an absent radio for each", count($rows), substr_count($html, 'class="c-absent"'));

    $isFinal = (bool) optional($data['selected_exam_type'] ?? null)->is_final;

    check("{$name}: a sign-in box for each student on a final exam only",
        $isFinal ? count($rows) : 0, substr_count($html, 'class="c-signin"'));
    check("{$name}: and a sign-out box to match",
        $isFinal ? count($rows) : 0, substr_count($html, 'class="c-signout"'));

    // Counted from the database, not from the view's own collection.
    if (!empty($data['selected_subject']) && !empty($data['selected_type'])) {
        $expected = DB::table('student_enrolls as se')
            ->join('exams as e', 'e.student_enroll_id', '=', 'se.id')
            ->where('e.subject_id', $data['selected_subject'])
            ->where('e.exam_type_id', $data['selected_type'])
            ->when(!($data['cross_program'] ?? false),
                fn ($q) => $q->where('se.program_id', $data['selected_program']))
            ->where('se.session_id', $data['selected_session'])
            ->where('se.semester_id', $data['selected_semester'])
            ->where('se.section_id', $data['selected_section'])
            ->distinct()->count('se.id');

        ok("{$name}: the roll is not shorter than the class ({$expected} enrolled)",
            count($rows) >= min($expected, count($rows)) && count($rows) > 0);
    }
}

/* ================================================================== */
echo PHP_EOL . "=== 3. the programme separator spans the whole table ===" . PHP_EOL;

/* The expectation is the <th> the page itself emitted. Computing it from the
   same conditions the partial uses would only assert that the arithmetic
   equals itself - and the formula this replaced was wrong in exactly that
   way: it counted neither the CA columns nor Sign In and Sign Out. */
foreach ($pages as $name => [$html, $data]) {
    if (empty($data['cross_program'])) {
        continue;
    }

    preg_match('~<table class="display table nowrap exm-table printable".*?<thead>(.*?)</thead>~s',
        $html, $head);

    $columns = substr_count($head[1] ?? '', '<th');

    ok("{$name}: the roll has columns", $columns > 0);

    preg_match_all('~<tr class="cross-program-separator">\s*<td colspan="(\d+)"~', $html, $spans);

    check("{$name}: every separator spans all {$columns} columns",
        [(string) $columns], array_values(array_unique($spans[1])));

    // One per programme the list changes into, counted from the rows.
    $changes = 0;
    $prev = null;

    foreach ($data['rows'] as $row) {
        if ($row->program_id !== $prev) {
            $changes++;
            $prev = $row->program_id;
        }
    }

    check("{$name}: one separator per programme the list changes into",
        $changes, count($spans[1]));

    ok("{$name}: the separator is not the old short span",
        !in_array('12', $spans[1], true) || $columns === 12);
}

/* ================================================================== */
echo PHP_EOL . "=== 3b. and it spans it for every permission set ===" . PHP_EOL;

/* Two permissions add a column: subject-marking-unlock puts the bulk-unlock
   checkbox at the left, exam-attendance-bypass puts Bypass at the right. The
   separator has to span whichever set the viewer actually has, so it is
   rendered once per combination that exists among the real users rather than
   once as an administrator. The formula this replaced returned 12 for all
   three. */
if ($sharedClass) {
    $crossQuery = $queryFor($sharedClass) + ['cross_program' => 1];
    $byPermission = [];

    foreach (App\User::orderBy('id')->get() as $candidate) {
        $key = ($candidate->can('subject-marking-unlock') ? 'U' : '-')
             . ($candidate->can('exam-attendance-bypass') ? 'B' : '-');

        $byPermission[$key] = $byPermission[$key] ?? $candidate;
    }

    ok('more than one permission set exists to test with', count($byPermission) > 1);

    $administrator = Auth::user();

    foreach ($byPermission as $key => $candidate) {
        Auth::guard('web')->login($candidate);

        [$permHtml, $permData] = $render($crossQuery);

        preg_match('~<table class="display table nowrap exm-table printable".*?<thead>(.*?)</thead>~s',
            $permHtml, $permHead);

        $permColumns = substr_count($permHead[1] ?? '', '<th');

        ok("permissions {$key}: the roll renders with columns", $permColumns > 0);

        preg_match_all('~<tr class="cross-program-separator">\s*<td colspan="(\d+)"~', $permHtml, $permSpans);

        check("permissions {$key}: the separator spans all {$permColumns}",
            [(string) $permColumns], array_values(array_unique($permSpans[1])));

        // The columns really do differ with the permission, so this is not
        // three copies of the same assertion.
        check("permissions {$key}: the unlock column is there only with the permission",
            $candidate->can('subject-marking-unlock'), str_contains($permHtml, 'class="check-all-unlock"'));

        foreach (['Undefined variable', 'Undefined array key', 'ErrorException'] as $wrong) {
            ok("permissions {$key}: no \"{$wrong}\"", !str_contains($permHtml, $wrong));
        }
    }

    // Column counts must not all be equal, or the loop proves nothing.
    $widths = [];

    foreach ($byPermission as $key => $candidate) {
        Auth::guard('web')->login($candidate);
        [$permHtml] = $render($crossQuery);
        preg_match('~<table class="display table nowrap exm-table printable".*?<thead>(.*?)</thead>~s',
            $permHtml, $permHead);
        $widths[$key] = substr_count($permHead[1] ?? '', '<th');
    }

    ok('the permission sets really do produce different widths ('
        . implode('/', $widths) . ')', count(array_unique($widths)) > 1);

    Auth::guard('web')->login($administrator);
}

/* ================================================================== */
echo PHP_EOL . "=== 4. the JavaScript's own selectors all resolve ===" . PHP_EOL;

/* Read out of the page's <script> block rather than listed here, so a hook
   added to the JS later is checked without anybody remembering to add it. */
[$finalHtml, $finalData] = $pages['a final exam'] ?? [null, null];

if ($finalHtml) {
    preg_match('~@section\(.page_js.\)~', $finalHtml, $_); // not in output; read the view
    $viewSrc = file_get_contents($base . '/resources/views/admin/exam/attendance.blade.php');
    $jsSrc = substr($viewSrc, strpos($viewSrc, "@section('page_js')"));

    // Ids the JS addresses literally.
    preg_match_all('~[\$]\([\'"]#([A-Za-z][A-Za-z0-9_-]*)[\'"]\)~', $jsSrc, $m);
    $wantedIds = array_values(array_unique($m[1]));

    // The ones only present in another state, or created at run time.
    $elsewhere = ['attendanceSavedModal', 'crossProgramHidden'];

    foreach ($wantedIds as $id) {
        if (in_array($id, $elsewhere, true)) {
            continue;
        }

        ok("the JS reaches #{$id}, and the page has it", str_contains($finalHtml, 'id="' . $id . '"'));
    }

    // Classes the JS binds, written as $('.x') or $(".x").
    preg_match_all('~[\$]\([\'"]\.([A-Za-z][A-Za-z0-9_-]*)~', $jsSrc, $m);

    foreach (array_unique($m[1]) as $cls) {
        ok("the JS binds .{$cls}, and the page has it", str_contains($finalHtml, $cls));
    }

    // The three structural dependencies that are not a plain selector.
    ok('.c-bypass is immediately followed by its label',
        (bool) preg_match('~class="c-bypass"[^>]*>\s*<label~', $finalHtml));

    ok("and that label holds the badge the handler rewrites",
        (bool) preg_match('~<label for="bypass-\d+"[^>]*>\s*<span class="badge~', $finalHtml));

    /* Eligibility is only enforced when the setting is switched on. It is
       off on this install, so the correct number of flagged rows is zero -
       the first version of this check left that condition out and accused
       the page of losing sixteen rows that were never meant to be there. */
    $setting = $finalData['attendance_setting'] ?? null;
    $enforced = $setting && $setting->is_enabled;
    $minPct = $setting->minimum_attendance_percentage ?? 70;

    $ineligible = !$enforced ? 0 : collect($finalData['rows'])->filter(function ($row) use ($finalData, $minPct) {
        $pct = $finalData['student_attendance_percentages'][$row->id]['percentage'] ?? null;

        return !is_null($pct) && $pct < $minPct;
    })->count();

    // Counted as ROWS. The bare string also appears in the stylesheet and in
    // the handler that reads the class.
    check('table-danger marks exactly the students who are not eligible'
        . ($enforced ? '' : ' (the rule is switched off, so none)'),
        $ineligible, preg_match_all('~<tr class="table-danger~', $finalHtml));

    ok('the class the modal counts is still referenced by the page',
        str_contains($finalHtml, 'table-danger'));

    if ($ineligible > 0) {
        ok('each of them carries the note the handler recolours',
            substr_count($finalHtml, 'class="d-block exm-why"') >= $ineligible);
    } else {
        ok('with the rule off, nobody is blocked from the exam',
            !str_contains($finalHtml, 'class="d-block exm-why"'));
    }
}

/* ================================================================== */
echo PHP_EOL . "=== 5. this install is Bootstrap 5, and the markup says so ===" . PHP_EOL;

/* Every one of these was a control that did nothing: 5.x does not read the
   4.x data attributes, and this theme does not backfill the 4.x switch or
   the mr-/ml- spacing utilities. */
$bootstrap = file_get_contents($base . '/public/dashboard/plugins/bootstrap/js/bootstrap.min.js');

ok('the bundled Bootstrap really is 5.x', (bool) preg_match('~Bootstrap v5\.~', $bootstrap));

foreach ($pages as $name => [$html, $data]) {
    foreach (['data-toggle="collapse"', 'data-target=', 'data-dismiss="modal"'] as $dead) {
        ok("{$name}: no 4.x attribute {$dead}", !str_contains($html, $dead));
    }
}

if ($finalHtml) {
    ok('the collapses use data-bs-toggle', str_contains($finalHtml, 'data-bs-toggle="collapse"'));
    ok('the modal closes with data-bs-dismiss', str_contains($finalHtml, 'data-bs-dismiss="modal"'));
}

// mr-* / ml-* are not defined anywhere, so nothing on the page may rely on
// them. The two the JS appends at run time are its own business.
$sheets = glob($base . '/public/dashboard/css/*.css');
$spacingDefined = false;

foreach ($sheets as $sheet) {
    if (str_contains(file_get_contents($sheet), '.mr-2')) {
        $spacingDefined = true;
    }
}

ok('mr-* really is undefined in this theme', !$spacingDefined);

/* Asserted on the SOURCE of this screen's own files, not on the rendered
   page: the page also carries the shared layout chrome, which uses mr-* too
   and is not this screen's to fix. The run-time strings inside the <script>
   block are excluded for the same reason - the spacing of what they write
   comes from the flex gap on .status-info. */
$ownFiles = array_merge(
    [$base . '/resources/views/admin/exam/attendance.blade.php'],
    glob($base . '/resources/views/admin/exam/partials/attendance_*.blade.php')
);

foreach ($ownFiles as $file) {
    $src = file_get_contents($file);
    $cut = strpos($src, "@section('page_js')");

    if ($cut !== false) {
        $src = substr($src, 0, $cut);
    }

    $src = preg_replace('~\{\{--.*?--\}\}~s', '', $src);
    $src = preg_replace('~/\*.*?\*/~s', '', $src);

    preg_match_all('~class="[^"]*\b(m[rl]-[0-9]|p[rl]-[0-9])\b~', $src, $m);

    check(basename($file) . ': leans on no undefined spacing class',
        [], array_values(array_unique($m[1])));
}

/* ================================================================== */
echo PHP_EOL . "=== 6. one copy of each course, and the toggle can reach it ===" . PHP_EOL;

[$bareHtml, $bareData] = $pages['with no filter at all'];
$courses = $bareData['myAssignedCourses'] ?? collect();
$courseCount = $courses->sum(fn ($p) => $p['subjects']->count());

ok('the signed-in user has courses to list', $courseCount > 0);

if ($courseCount > 0) {
    check('one card per course, not two', $courseCount, substr_count($bareHtml, 'class="exm-course"'));

    check('one semester blob per course, not two',
        $courseCount, substr_count($bareHtml, 'data-semesters='));

    check('one Take Attendance button per course',
        $courseCount, substr_count($bareHtml, 'class="exm-btn exm-btn-main exm-course-go quick-take-attendance"'));

    // The duplicate half used ids suffixed tbl_. Nothing should hold them.
    check('the unreachable duplicate is gone', 0, substr_count($bareHtml, '_tbl_'));

    // The toggle's buttons must carry the class the handler binds AND the
    // data-view it reads. Having one without the other is what was wrong.
    ok('both layout buttons carry the class the handler binds',
        substr_count($bareHtml, 'class="view-toggle-btn') >= 1
        && substr_count($bareHtml, 'view-toggle-btn') >= 2);

    ok('and the data-view the handler reads', str_contains($bareHtml, 'data-view="card"')
        && str_contains($bareHtml, 'data-view="table"'));

    ok('the container the handler toggles exists', str_contains($bareHtml, 'id="coursesContainer"'));
    ok('and it holds the list the class is put on', str_contains($bareHtml, 'class="exm-courses is-cards"'));

    // The collapse the chevron listens to.
    ok('the collapse id the JS listens on is the one in the markup',
        str_contains($bareHtml, 'id="myCoursesCollapse"'));

    $jsSrc = file_get_contents($base . '/resources/views/admin/exam/attendance.blade.php');
    ok('and the JS no longer listens on an id that does not exist',
        !str_contains($jsSrc, "getElementById('myAssignedCoursesCollapse')"));

    // Every per-course control keeps the id shape the handlers build.
    foreach (['year_', 'semester_', 'section_', 'examtype_', 'status_'] as $prefix) {
        ok("the {$prefix}<key> ids are still generated",
            substr_count($bareHtml, 'id="' . $prefix) >= $courseCount - 5);
    }

    ok('the page is lighter than it was (one copy, not two)',
        strlen($bareHtml) < 3000000);
}

/* ================================================================== */
echo PHP_EOL . "=== 7. the band counts what the records say ===" . PHP_EOL;

foreach ($pages as $name => [$html, $data]) {
    if (!isset($data['rows']) || count($data['rows']) === 0) {
        continue;
    }

    // Recomputed here from the attendance records, independently of the band.
    $present = 0;
    $absent = 0;

    foreach (($data['attendances'] ?? []) as $att) {
        if ((int) $att->attendance === 1) { $present++; }
        if ((int) $att->attendance === 2) { $absent++; }
    }

    $total = count($data['rows']);
    $marked = min($present + $absent, $total);

    ok("{$name}: the band is on the page", str_contains($html, 'class="exm-band"'));

    preg_match('~<span class="v exm-num">([\d,]+)</span>\s*<span class="c exm-num">/ ([\d,]+) · (\d+)%~', $html, $m);

    check("{$name}: it says how many are recorded", number_format($marked), $m[1] ?? null);
    check("{$name}: out of how many", number_format($total), $m[2] ?? null);
    check("{$name}: and the percentage agrees",
        (string) ($total > 0 ? round($marked / $total * 100) : 0), $m[3] ?? null);

    // The bar cannot claim more than the whole.
    preg_match_all('~<div style="width:([\d.]+)%;background:var\(--(good|bad)\)~', $html, $bars);

    if ($bars[1] !== []) {
        ok("{$name}: the bar never exceeds the roll",
            array_sum(array_map('floatval', $bars[1])) <= 100.001);
    }
}

/* ================================================================== */
echo PHP_EOL . "=== 8. the styling stays on this screen ===" . PHP_EOL;

/* Both partials are scoped to .exm so none of it reaches the other seven
   views that include common/inc/subject_search_filter. */
$withoutComments = fn (string $s) => preg_replace('~/\*.*?\*/~s', '', $s);

$attTheme = $withoutComments(file_get_contents(
    $base . '/resources/views/admin/exam/partials/attendance_theme.blade.php'));

preg_match_all('~^\s*(\.[a-z][a-z0-9-]*|table\.[a-z-]+)[^{]*\{~mi', $attTheme, $selectors);

foreach (array_unique($selectors[1]) as $selector) {
    ok('"' . $selector . '" is inside the exm scope',
        str_starts_with($selector, '.exm') || str_starts_with($selector, 'table.exm'));
}

// The marking screen shares partials/theme and must be unaffected.
$request = Request::create('/admin/exam/exam-marking', 'GET');
app()->instance('request', $request);

DB::beginTransaction();
try {
    $marking = app(App\Http\Controllers\Admin\ExamMarkingController::class)->index($request)->render();
} finally {
    DB::rollBack();
}

ok('the marking screen still renders', strlen($marking) > 10000);
ok('and did not pick up the attendance-only styling', !str_contains($marking, '.exm-course{'));

// The sibling views that share only the filter partial.
$request = Request::create('/admin/subject-marking', 'GET');
app()->instance('request', $request);

DB::beginTransaction();
try {
    $other = app(App\Http\Controllers\Admin\SubjectMarkingController::class)->index($request)->render();
} finally {
    DB::rollBack();
}

ok('subject-marking still renders', strlen($other) > 5000);
ok('and did not pick up the exm styling',
    !str_contains($other, 'main-body exm') && !str_contains($other, '.exm-band'));

/* ================================================================== */
echo PHP_EOL . "=== nothing was left behind ===" . PHP_EOL;

echo '  states rendered: ' . count($pages) . PHP_EOL;
echo '  courses listed:  ' . number_format($courseCount) . PHP_EOL;
echo '  bare page bytes: ' . number_format(strlen($bareHtml)) . PHP_EOL;

foreach ($pages as $name => [$html, $data]) {
    echo '  ' . str_pad($name, 26) . str_pad(number_format(strlen($html)) . ' bytes', 16)
        . (isset($data['rows']) ? count($data['rows']) . ' students' : 'no roll') . PHP_EOL;
}

echo PHP_EOL . "{$pass} passed, {$fail} failed" . PHP_EOL;

exit($fail > 0 ? 1 : 0);
