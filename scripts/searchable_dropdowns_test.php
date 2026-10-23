<?php

/**
 * Searchable dropdowns (issue #1).
 *
 * Long dropdowns (courses, lecturers, rooms) must be type-to-search rather
 * than lists scanned by eye. The admin theme already ships select2 and turns
 * every `.select2` on the page into a searchable dropdown, but only once, on
 * page load. Anything drawn after that is left a plain select.
 *
 * Class schedule, add / edit (admin/routine/class-routine/create):
 *  - Every row gave its dropdowns the same ids (id="subject", "teacher",
 *    "room"). select2 4.0 keys its state on the element id, so each row
 *    initialised tore down the one before it: on a page of 23 rows, exactly
 *    one course, one lecturer and one room dropdown ended up searchable.
 *    Carrying the select2 class is not enough; the ids must be unique.
 *  - "Add New" builds a row in JavaScript after load, which the page-load
 *    init never sees. Those dropdowns were plain selects, and the row is
 *    exactly the one used to add a class to a day that already has some.
 *
 * Joint class schedule: the same three dropdowns, rendered by the server.
 *
 * Exam schedule: each saved exam is its own form, and every one of them used
 * id="subject", "teachers", "rooms". Same teardown: 1 of 19 searchable.
 *
 * Course filters (exam marking / attendance / results, subject marking,
 * reports, student attendance, imports, result contribution, course
 * marksheet): the course list is refilled by AJAX when the session or
 * programme changes. select2 does not notice a refill, so each refill now
 * refreshes the box, or it keeps showing the course from before the refill.
 *
 * The course marksheet loaded jQuery a second time after the footer, which
 * replaced it with a bare copy and wiped out select2 and DataTables. No page
 * here may load jQuery after select2.
 *
 * Dropdowns in modals (kiosk "Add Extra Class", tax exemptions): select2 has
 * to attach its dropdown to the modal or the search box cannot take focus.
 * The kiosk listens for native change events, which the jQuery change that
 * select2 fires never reaches, so a pick is re-sent as a native change.
 *
 * Reads only. Writes: none.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

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

function skip(string $label, string $why): void
{
    global $skipped;

    $skipped++;
    echo "  SKIP  $label  ($why)\n";
}

/**
 * The selects with this name that the server drew, and whether each one
 * carries the select2 class. Selects inside <script> templates are not
 * elements to the parser, so only the real, rendered rows are counted.
 */
function renderedSelects(string $html, string $name): array
{
    $found = [];
    foreach (selectNodes($html, $name) as $select) {
        $classes = preg_split('/\s+/', (string) $select->getAttribute('class'));
        $found[] = in_array('select2', $classes, true);
    }

    return $found;
}

/** The ids of the rendered selects with this name, in page order. */
function renderedSelectIds(string $html, string $name): array
{
    $ids = [];
    foreach (selectNodes($html, $name) as $select) {
        $ids[] = (string) $select->getAttribute('id');
    }

    return $ids;
}

function selectNodes(string $html, string $name): iterable
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    return (new DOMXPath($dom))->query('//select[@name="' . $name . '"]');
}

$admin = App\User::whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "No Super Admin to render the screens as.\n";
    exit(1);
}

app('session.store')->start();
Auth::guard('web')->login($admin);

$get = function (string $url) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

// ---------------------------------------------------------------------------
section('Class schedule: add / edit');
// ---------------------------------------------------------------------------

// The busiest real timetable, so there are existing rows as well as empty days.
$busiest = DB::table('class_routines')->where('status', '1')
    ->select('program_id', 'session_id', 'semester_id', 'section_id', DB::raw('count(*) c'))
    ->groupBy('program_id', 'session_id', 'semester_id', 'section_id')
    ->orderByDesc('c')->first();

if (!$busiest) {
    skip('class schedule screen', 'no class routines in this database');
} else {
    $filters = [
        'faculty' => DB::table('programs')->where('id', $busiest->program_id)->value('faculty_id'),
        'program' => $busiest->program_id,
        'session' => $busiest->session_id,
        'semester' => $busiest->semester_id,
        'section' => $busiest->section_id,
    ];

    $page = $get('/admin/routine/class-routine/create?' . http_build_query($filters));
    $html = $page->getContent();

    check('the add / edit screen answers 200', $page->getStatusCode() === 200,
        'HTTP ' . $page->getStatusCode());

    check('the select2 library is on the page',
        str_contains($html, 'plugins/select2/js/select2.full.min.js'));

    foreach (['subject[]' => 'course', 'teacher[]' => 'lecturer', 'room[]' => 'room'] as $name => $label) {
        $selects = renderedSelects($html, $name);

        check("every rendered $label dropdown is marked for select2",
            count($selects) > 0 && !in_array(false, $selects, true),
            sprintf('%d of %d', count(array_filter($selects)), count($selects)));

        $ids = renderedSelectIds($html, $name);
        $shared = array_unique(array_diff_assoc($ids, array_unique($ids)));

        check("every rendered $label dropdown has its own id",
            count($ids) > 0 && !in_array('', $ids, true) && $shared === [],
            $shared ? sprintf('%d rows share id "%s"; select2 keeps only one of them',
                count(array_keys($ids, reset($shared), true)), reset($shared)) : 'missing ids');
    }

    // The "Add New" handler, as the browser receives it.
    $from = strpos($html, "on('click', '#addField'");
    $to = $from === false ? false : strpos($html, '// remove Field', $from);
    $handler = ($from !== false && $to !== false) ? substr($html, $from, $to - $from) : '';

    check('the "Add New" handler is on the page', $handler !== '');

    foreach (['subject[]' => 'course', 'teacher[]' => 'lecturer', 'room[]' => 'room'] as $name => $label) {
        check("a new row's $label dropdown is marked for select2",
            (bool) preg_match('/<select class="form-control select2" name="' . preg_quote($name, '/') . '"/', $handler));

        // A fixed id here would collide with the rows already on the page.
        $field = rtrim($name, '[]');
        check("a new row's $label dropdown gets a fresh id each time",
            str_contains($handler, 'name="' . $name . '" id="' . $field . "-new-' + newFieldCount + '\"")
            && str_contains($handler, 'newFieldCount++'));
    }

    $appendAt = strpos($handler, '.append($newField)');
    $initAt = strpos($handler, "\$newField.find('.select2').select2()");

    check('a new row is made searchable once it is on the page',
        $appendAt !== false && $initAt !== false && $initAt > $appendAt,
        'select2 must run on the appended row, after the append');
}

// ---------------------------------------------------------------------------
section('Joint class schedule');
// ---------------------------------------------------------------------------

$page = $get('/admin/routine/class-routine-joint');
$html = $page->getContent();

check('the joint schedule screen answers 200', $page->getStatusCode() === 200,
    'HTTP ' . $page->getStatusCode());

foreach (['subject' => 'course', 'teacher' => 'lecturer', 'room' => 'room'] as $name => $label) {
    $selects = renderedSelects($html, $name);

    check("the $label dropdown is searchable",
        count($selects) === 1 && $selects[0] === true);
}

/**
 * True when nothing loads jQuery again after select2. A second jQuery
 * replaces the one select2 attached itself to, so every searchable dropdown
 * on the page silently falls back to a plain select.
 */
function jqueryNotReloaded(string $html): bool
{
    $select2At = strpos($html, 'plugins/select2/js/select2.full.min.js');

    return $select2At !== false
        && strpos($html, 'plugins/jquery/js/jquery.min.js', $select2At) === false;
}

// ---------------------------------------------------------------------------
section('Exam schedule: add / edit');
// ---------------------------------------------------------------------------

$busiest = DB::table('exam_routines as r')->join('programs as p', 'p.id', '=', 'r.program_id')
    ->where('r.status', '1')
    ->select('r.program_id', 'p.faculty_id', 'r.session_id', 'r.semester_id', 'r.section_id',
        'r.exam_type_id', DB::raw('count(*) c'))
    ->groupBy('r.program_id', 'p.faculty_id', 'r.session_id', 'r.semester_id', 'r.section_id', 'r.exam_type_id')
    ->orderByDesc('c')->first();

if (!$busiest) {
    skip('exam schedule screen', 'no exam routines in this database');
} else {
    $page = $get('/admin/routine/exam-routine/create?' . http_build_query([
        'faculty' => $busiest->faculty_id,
        'program' => $busiest->program_id,
        'session' => $busiest->session_id,
        'semester' => $busiest->semester_id,
        'section' => $busiest->section_id,
        'type' => $busiest->exam_type_id,
    ]));
    $html = $page->getContent();

    check('the exam schedule screen answers 200', $page->getStatusCode() === 200,
        'HTTP ' . $page->getStatusCode());

    foreach (['subject' => 'course', 'teachers[]' => 'supervisor', 'rooms[]' => 'room'] as $name => $label) {
        $selects = renderedSelects($html, $name);

        check("every $label dropdown is marked for select2",
            count($selects) > 1 && !in_array(false, $selects, true),
            sprintf('%d of %d', count(array_filter($selects)), count($selects)));

        $ids = renderedSelectIds($html, $name);
        $shared = array_unique(array_diff_assoc($ids, array_unique($ids)));

        check("every $label dropdown has its own id",
            !in_array('', $ids, true) && $shared === [],
            $shared ? sprintf('%d forms share id "%s"', count(array_keys($ids, reset($shared), true)), reset($shared)) : 'missing ids');
    }
}

// ---------------------------------------------------------------------------
section('Course filters');
// ---------------------------------------------------------------------------

$filterPages = [
    '/admin/exam/exam-marking' => 'exam marking',
    '/admin/exam/exam-attendance' => 'exam attendance',
    '/admin/exam/exam-result' => 'exam result',
    '/admin/exam/subject-marking' => 'subject marking',
    '/admin/report/subject' => 'subject report',
    '/admin/exam/student-exam-config' => 'student exam config',
    '/admin/download/assignment/create' => 'assignment',
    '/admin/student-attendance' => 'student attendance',
    '/admin/student-attendance-report' => 'student attendance report',
    '/admin/student-attendance-import' => 'student attendance import',
    '/admin/exam/exam-attendance-import' => 'exam attendance import',
    '/admin/student-attendance-bulk-migration' => 'attendance bulk migration',
    '/admin/exam/result-contribution' => 'result contribution',
    '/admin/exam/results-summary/course-marksheet' => 'course marksheet',
    '/admin/report/subject-attendance' => 'subject attendance report',
];

foreach ($filterPages as $url => $label) {
    $page = $get($url);
    $html = $page->getContent();
    $selects = renderedSelects($html, 'subject');

    check("$label: the course dropdown is searchable",
        $page->getStatusCode() === 200 && count($selects) === 1 && $selects[0] === true,
        'HTTP ' . $page->getStatusCode() . ', ' . count($selects) . ' course dropdown(s)');

    check("$label: jQuery is not loaded again after select2", jqueryNotReloaded($html));
}

// Each AJAX refill of a course list must refresh its select2 box. The views
// are read directly: the refill code is spread across shared includes and
// pages, and each copy has to carry the refresh.
$refills = [
    'common/inc/subject_search_filter.blade.php' => 1,
    'common/inc/assignment_search_filter.blade.php' => 1,
    'admin/student-attendance/index.blade.php' => 3,
    'admin/student-attendance/report.blade.php' => 3,
    'admin/report/subject-attendance.blade.php' => 4,
    'admin/student-attendance/import.blade.php' => 1,
    'admin/exam/import.blade.php' => 1,
    'admin/student-attendance/bulk-migration.blade.php' => 2,
    'admin/result-contribution/index.blade.php' => 2,
    'admin/results-summary/course-marksheet.blade.php' => 2,
];

foreach ($refills as $view => $expected) {
    $source = (string) file_get_contents($base . '/resources/views/' . $view);
    $found = substr_count($source, "trigger('change.select2')");

    check("$view refreshes the course box after every refill", $found === $expected,
        "$found of $expected refill points");
}

// ---------------------------------------------------------------------------
section('Dropdowns in modals');
// ---------------------------------------------------------------------------

$page = $get('/admin/class-session/kiosk');
$html = $page->getContent();

check('the kiosk answers 200', $page->getStatusCode() === 200, 'HTTP ' . $page->getStatusCode());
check('kiosk: the extra-class course picker is searchable inside its modal',
    str_contains($html, "\$('#extraSubject').select2({ dropdownParent: \$('#extraClassModal')"));
check('kiosk: a pick reaches the native change listeners',
    str_contains($html, "on('select2:select select2:clear'")
    && str_contains($html, "this.dispatchEvent(new Event('change'))"));
check('kiosk: reopening the modal resets the course box',
    (bool) preg_match("/extraClassForm'\)\.reset\(\);\s*\\\$\('#extraSubject'\)\.trigger\('change\.select2'\);/", $html));

$page = $get('/admin/staff/tax-setting');
$html = $page->getContent();
$staffPickers = substr_count($html, 'class="form-control exemption-staff"');

check('the tax settings screen answers 200', $page->getStatusCode() === 200, 'HTTP ' . $page->getStatusCode());
check('tax exemptions: every staff picker is marked', $staffPickers > 0
    && $staffPickers === substr_count($html, 'id="exemptionsModal-'));
check('tax exemptions: each picker is made searchable inside its own modal',
    str_contains($html, "\$(this).select2({ dropdownParent: \$(this).closest('.modal')"));

// ---------------------------------------------------------------------------
section('Staff filter');
// ---------------------------------------------------------------------------

$page = $get('/admin/audit-log');
$html = $page->getContent();
$selects = renderedSelects($html, 'user_id');

check('audit log: the user dropdown is searchable',
    $page->getStatusCode() === 200 && count($selects) === 1 && $selects[0] === true,
    'HTTP ' . $page->getStatusCode());

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
