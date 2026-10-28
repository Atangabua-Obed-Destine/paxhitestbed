<?php

/**
 * Form A2 access control: searchable filters, a search box, and pagination.
 *
 * The screen listed every Year 1 / Semester 1 enrolment on one page with
 * plain dropdown filters. It now pages the list, adds a search on matricule
 * or name, and makes the filters searchable (select2). What has to hold:
 *
 *  - Paging changes nothing about who is listed: the pages, joined, are the
 *    same students in the same order as the single unpaged list.
 *  - The KPI cards count every student matching the filters, not just the
 *    page on screen.
 *  - Page links keep the filters and search; a page past the end (after
 *    narrowing the filters) falls back to the last page.
 *  - The search finds a student by matricule and by name.
 *  - The filters are searchable, and the programme list, refilled by AJAX
 *    when the faculty changes, refreshes its select2 box.
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

function xpath(string $html): DOMXPath
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

/** The enrolment ids listed on the page, in order. */
function listedIds(string $html): array
{
    $ids = [];
    foreach (xpath($html)->query('//input[contains(@class,"row-checkbox")]') as $box) {
        $ids[] = (int) $box->getAttribute('value');
    }

    return $ids;
}

/** The KPI cards, label => number. */
function kpis(string $html): array
{
    $cards = [];
    foreach (xpath($html)->query('//div[contains(@class,"card-body")][h6 and h3]') as $card) {
        $cards[trim($card->getElementsByTagName('h6')->item(0)->textContent)]
            = (int) trim($card->getElementsByTagName('h3')->item(0)->textContent);
    }

    return $cards;
}

$admin = App\User::whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "No Super Admin to render the screen as.\n";
    exit(1);
}

app('session.store')->start();
Auth::guard('web')->login($admin);

$get = function (array $query = []) use ($kernel) {
    $request = Illuminate\Http\Request::create('/admin/form-a2-access?' . http_build_query($query), 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

// The students the screen is about, counted independently of the controller.
$expected = DB::table('student_enrolls as e')
    ->join('semesters as s', 's.id', '=', 'e.semester_id')
    ->where('s.year', 1)->where('s.semester_type', 1)
    ->where(fn ($q) => $q->whereNull('s.is_resit')->orWhere('s.is_resit', 0))
    ->count();

// ---------------------------------------------------------------------------
section('The list is paged without losing or repeating anyone');
// ---------------------------------------------------------------------------

$first = $get();
$html = $first->getContent();

check('the screen answers 200', $first->getStatusCode() === 200, 'HTTP ' . $first->getStatusCode());

if ($expected <= 25) {
    skip('paging', "only $expected Year 1 / Semester 1 enrolments; need more than 25");
} else {
    $all = listedIds($get(['per_page' => 100])->getContent());
    $joined = [];
    $pages = (int) ceil(count($all) / 25);

    for ($page = 1; $page <= $pages; $page++) {
        $joined = array_merge($joined, listedIds($get(['page' => $page])->getContent()));
    }

    check('a page holds 25 students', count(listedIds($html)) === 25);
    check('every Year 1 / Semester 1 enrolment is listed across the pages',
        count($joined) === $expected, count($joined) . " of $expected");
    check('no student appears twice', count($joined) === count(array_unique($joined)));
    check('the pages, joined, are the same list in the same order', $joined === $all);
    check('the page says how much of the list it shows',
        str_contains($html, "Showing 1–25 of $expected student(s)"));
    check('there are page links', xpath($html)->query('//a[contains(@class,"page-link")]')->length > 0);
    check('50 per page can be chosen', count(listedIds($get(['per_page' => 50])->getContent())) === min(50, $expected));
    check('an unlisted page size falls back to 25', count(listedIds($get(['per_page' => 7])->getContent())) === 25);

    $last = $get(['page' => 999])->getContent();
    check('a page past the end shows the last page',
        count(listedIds($last)) === $expected - 25 * ($pages - 1));
}

// ---------------------------------------------------------------------------
section('The KPIs count every match, not just the page');
// ---------------------------------------------------------------------------

$cards = kpis($html);

check('the total card counts every student', ($cards['Total (Y1S1)'] ?? null) === $expected,
    ($cards['Total (Y1S1)'] ?? 'missing') . " vs $expected");
check('the cards are the same on a later page', $expected <= 25 || kpis($get(['page' => 2])->getContent()) === $cards);

// ---------------------------------------------------------------------------
section('Page links keep the filters and the search');
// ---------------------------------------------------------------------------

$session = DB::table('student_enrolls as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
    ->where('s.year', 1)->where('s.semester_type', 1)
    ->select('e.session_id', DB::raw('count(*) c'))->groupBy('e.session_id')->orderByDesc('c')->first();

if (!$session || $session->c <= 25) {
    skip('filtered page links', 'no session with more than 25 Year 1 / Semester 1 enrolments');
} else {
    $filtered = $get(['session_id' => $session->session_id, 'q' => '']);
    $link = xpath($filtered->getContent())->query('//a[contains(@class,"page-link")]')->item(0);
    $href = html_entity_decode($link ? $link->getAttribute('href') : '');

    check('a page link carries the session filter', str_contains($href, 'session_id=' . $session->session_id), $href);
}

// ---------------------------------------------------------------------------
section('The search box');
// ---------------------------------------------------------------------------

$sample = DB::table('student_enrolls as e')
    ->join('semesters as s', 's.id', '=', 'e.semester_id')
    ->join('students as st', 'st.id', '=', 'e.student_id')
    ->where('s.year', 1)->where('s.semester_type', 1)
    ->whereNotNull('e.matricule')->where('e.matricule', '!=', '')
    ->select('e.id', 'e.matricule', 'st.first_name', 'st.last_name')
    ->first();

if (!$sample) {
    skip('search', 'no Year 1 / Semester 1 enrolment with a matricule');
} else {
    $byMatricule = listedIds($get(['q' => $sample->matricule])->getContent());
    check('a matricule finds its student', in_array($sample->id, $byMatricule, true));
    check('and narrows the list', count($byMatricule) < $expected);

    $byName = listedIds($get(['q' => $sample->first_name . ' ' . $sample->last_name])->getContent());
    check('a full name finds the student', in_array($sample->id, $byName, true));

    $byLast = listedIds($get(['q' => $sample->last_name])->getContent());
    check('a last name alone finds the student', in_array($sample->id, $byLast, true));

    $nothing = $get(['q' => 'zz-no-such-student-zz'])->getContent();
    check('a search with no match says so', listedIds($nothing) === []
        && str_contains($nothing, 'No students match these filters.'));
}

// ---------------------------------------------------------------------------
section('The filters are searchable');
// ---------------------------------------------------------------------------

$x = xpath($html);

foreach (['session_id' => 'session', 'faculty_id' => 'faculty', 'program_id' => 'programme',
          'payment_status' => 'payment status', 'bypass_status' => 'access status'] as $name => $label) {
    $select = $x->query('//select[@name="' . $name . '"]')->item(0);
    $classes = $select ? preg_split('/\s+/', $select->getAttribute('class')) : [];

    check("the $label filter is searchable", in_array('select2', $classes, true));
}

check('the programme list refreshes its box when the faculty changes',
    str_contains($html, "\$('#program_id').trigger('change.select2');"));

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
