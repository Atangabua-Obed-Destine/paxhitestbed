<?php

/**
 * The barcode on the student ID card.
 *
 * The card artwork (uploads/templates/paxid.jpg) has a barcode painted into
 * it at the bottom left. It is decoration: the same ink on every card,
 * encoding nothing. The partial under test draws a real Code 39 symbol of the
 * student's matricule over that spot.
 *
 * A barcode is only worth printing if a scanner reads back the number beside
 * it, so the checks below do not look at the markup and call it done. They
 * take the bars the library actually emitted, measure them, and decode them
 * through a Code 39 table typed from the specification rather than read from
 * the library — so a change that still produces a plausible-looking symbol,
 * but one that scans as something other than the matricule printed above it,
 * fails here.
 *
 * Nothing in this file writes to the database.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\StaffIdCardController;
use App\Http\Controllers\Admin\StudentIdCardController;
use App\Models\IdCardSetting;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

/**
 * Code 39, as published: nine elements per character, alternating bar and
 * space, starting and ending on a bar; exactly three of the nine are wide.
 * 1 is a narrow element and 3 a wide one.
 */
function code39Table(): array
{
    return [
        '0' => '111331311', '1' => '311311113', '2' => '113311113', '3' => '313311111',
        '4' => '111331113', '5' => '311331111', '6' => '113331111', '7' => '111311313',
        '8' => '311311311', '9' => '113311311',
        'A' => '311113113', 'B' => '113113113', 'C' => '313113111', 'D' => '111133113',
        'E' => '311133111', 'F' => '113133111', 'G' => '111113313', 'H' => '311113311',
        'I' => '113113311', 'J' => '111133311', 'K' => '311111133', 'L' => '113111133',
        'M' => '313111131', 'N' => '111131133', 'O' => '311131131', 'P' => '113131131',
        'Q' => '111111333', 'R' => '311111331', 'S' => '113111331', 'T' => '111131331',
        'U' => '331111113', 'V' => '133111113', 'W' => '333111111', 'X' => '131131113',
        'Y' => '331131111', 'Z' => '133131111',
        '-' => '131111313', '.' => '331111311', ' ' => '133111311',
        '$' => '131313111', '/' => '131311131', '+' => '131113131', '%' => '111313131',
        '*' => '131131311',
    ];
}

/**
 * Decode a rendered barcode from its geometry alone.
 *
 * The library draws a <rect> per bar and leaves the spaces as the gaps
 * between them, so the full element sequence is bar, gap, bar, gap ... and
 * the run widths come straight off the x coordinates.
 */
function decodeCode39Svg(string $svg): string
{
    preg_match_all('~<rect x="([\d.]+)" y="[\d.]+" width="([\d.]+)"~', $svg, $m, PREG_SET_ORDER);
    if (count($m) < 2) { return '(no bars)'; }

    $elements = [];
    $prevEnd = null;
    foreach ($m as $rect) {
        $x = (float) $rect[1];
        $w = (float) $rect[2];
        if ($prevEnd !== null) { $elements[] = $x - $prevEnd; }  // the space before this bar
        $elements[] = $w;                                        // the bar itself
        $prevEnd = $x + $w;
    }

    $unit = min($elements);
    if ($unit <= 0) { return '(bad geometry)'; }

    // A character is nine elements; the tenth is the gap to the next one.
    if ((count($elements) + 1) % 10 !== 0) { return '(not a whole number of characters)'; }

    $table = array_flip(code39Table());
    $out = '';
    for ($i = 0; $i + 8 < count($elements); $i += 10) {
        $pattern = '';
        for ($j = 0; $j < 9; $j++) {
            $pattern .= ($elements[$i + $j] >= $unit * 2) ? '3' : '1';
        }
        if (!isset($table[$pattern])) { return '(unknown pattern ' . $pattern . ')'; }
        $out .= $table[$pattern];
    }

    if (strlen($out) < 3 || $out[0] !== '*' || substr($out, -1) !== '*') {
        return '(missing start/stop)';
    }

    return substr($out, 1, -1);
}

function barcodeFor(string $matricule, ?float $width = null): string
{
    $data = ['matricule' => $matricule];
    if ($width !== null) { $data['width'] = $width; }

    return view('admin.id-card.partials.barcode', $data)->render();
}

View::share('errors', new ViewErrorBag);

echo "=== 1. the symbol scans back as the matricule ===\n";

// The table above is typed from the spec, so prove it agrees with the symbols
// the library draws before trusting it to judge anything else.
foreach (['PAX25AFH002', 'PAX25MBH067', 'A1', '0123456789'] as $value) {
    check("a Code 39 symbol of {$value} decodes back to {$value}",
        $value, decodeCode39Svg(barcodeFor($value)));
}

// Every matricule on this install, not just a sample of them.
$allMatricules = DB::table('student_enrolls')->whereNotNull('matricule')
    ->distinct()->orderBy('matricule')->pluck('matricule')->all();

$wrong = [];
foreach ($allMatricules as $value) {
    if (decodeCode39Svg(barcodeFor($value)) !== $value) { $wrong[] = $value; }
}
check('every matricule in the database scans back as itself (' . count($allMatricules) . ' of them)',
    [], $wrong);

echo "\n=== 2. the symbol is laid out so a scanner can see it ===\n";

$svg = barcodeFor('PAX25AFH002');

check('the SVG carries a viewBox, without which CSS cannot size it at all',
    1, preg_match('~<svg viewBox="0 0 [\d.]+ [\d.]+"~', $svg));
check('the drawn width is stated in px too, which is what html2canvas needs for the download',
    1, preg_match('~width="258" height="38"~', $svg));
check('no XML declaration is emitted into the middle of the HTML page',
    false, str_contains($svg, '<?xml'));
check('no SVG DOCTYPE is emitted into the middle of the HTML page',
    false, stripos($svg, 'DOCTYPE svg') !== false);

// The quiet zone: Code 39 needs a clear margin of at least ten narrow bars at
// each end or a scanner will not find the symbol at all. That margin is the
// panel's side padding, and how wide a narrow bar comes out depends on the
// panel's width, so both are read from the stylesheet the card actually uses
// rather than restated here.
// The two card designs do not share a box model - the staff views carry a
// `* { box-sizing: border-box }` reset and the student views do not - so the
// same `width` means content on one card and content-plus-padding on the
// other. Getting that backwards would quietly shrink the bars by the width of
// two quiet zones, so it is worked out here rather than assumed.
function barcodePanelCss(string $view, string $selector = '.id-card .barcode'): array
{
    $source = file_get_contents($view);
    preg_match('~' . preg_quote($selector, '~') . '\s*\{(.+?)\}~s', $source, $block);
    $rules = $block[1] ?? '';

    preg_match('~width:\s*([\d.]+)px~', $rules, $w);
    preg_match('~padding:\s*[\d.]+px\s+([\d.]+)px~', $rules, $p);

    preg_match('~border:\s*([\d.]+)px~', $rules, $b);

    $width = (float) ($w[1] ?? 0);
    $padding = (float) ($p[1] ?? 0);
    $border = (float) ($b[1] ?? 0);
    $borderBox = str_contains($rules, 'box-sizing: border-box');

    return [
        'side_padding' => $padding,
        // what the bars are actually drawn at
        'content_width' => $borderBox ? $width - 2 * $padding - 2 * $border : $width,
    ];
}

/**
 * Code 39 needs a clear margin of at least ten narrow bars at each end or a
 * scanner will not find the symbol. How wide a narrow bar comes out depends
 * on how many characters are being squeezed into the panel, so the worst case
 * is the longest id the card has to carry, not a typical one.
 */
function checkQuietZone(string $card, array $css, string $longest): void
{
    $svg = barcodeFor($longest);
    preg_match('~<svg viewBox="0 0 ([\d.]+) ~', $svg, $vb);
    $natural = (float) ($vb[1] ?? 0);

    if ($natural <= 0 || $css['content_width'] <= 0) {
        check("the {$card} card's bar width can be measured at all", true, false);
        return;
    }

    $narrowBar = $css['content_width'] / $natural;
    $bars = $css['side_padding'] / $narrowBar;
    check("the {$card} card's quiet zone is at least ten narrow bars wide, "
        . "even for its longest id ({$longest})", true, $bars >= 10);
    printf("        (narrow bar %.3fpx, quiet zone %.1fpx = %.1f bars)\n",
        $narrowBar, $css['side_padding'], $bars);
}

$printCss = barcodePanelCss($base . '/resources/views/admin/id-card/print.blade.php');
$downloadCss = barcodePanelCss($base . '/resources/views/admin/id-card/download.blade.php');

check('the print and download cards size the barcode panel identically',
    $printCss, $downloadCss);

preg_match('~<svg[^>]*\swidth="([\d.]+)"~', $svg, $dw);
check("the SVG's own width matches the panel it has to fill, so the size "
    . 'html2canvas reads is the size on screen',
    $printCss['content_width'], (float) ($dw[1] ?? 0));

$longestMatricule = DB::table('student_enrolls')->whereNotNull('matricule')
    ->orderByRaw('CHAR_LENGTH(matricule) DESC')->orderBy('matricule')->value('matricule');
checkQuietZone('student', $printCss, $longestMatricule);

echo "\n=== 3. a symbol that would lie is not drawn at all ===\n";

// Code 39 cannot carry these. The library returns false and draws nothing
// usable, and a barcode that scans as something other than the number printed
// above it is worse than no barcode, so the partial must omit it instead.
foreach (['', '   ', 'PAX#25', 'PAX_002', 'PAXé25'] as $bad) {
    check('no barcode is drawn for ' . var_export($bad, true),
        false, str_contains(barcodeFor($bad), '<svg'));
}

// Lowercase is not a lie - Code 39 is an uppercase alphabet and the library
// folds the case, so the label must show the folded value that was encoded.
$folded = barcodeFor('pax25afh002');
check('a lowercase matricule is encoded as its uppercase form',
    'PAX25AFH002', decodeCode39Svg($folded));
check('and the printed label shows the value that was encoded, not the input',
    true, str_contains($folded, '>PAX25AFH002<'));

echo "\n=== 4. the card screens draw one, from the matricule they print ===\n";

$setting = IdCardSetting::where('slug', 'student-card')->firstOrFail();

// Deterministic pick: a real enrolment that has a matricule and a student.
$enroll = StudentEnroll::with(['student', 'program.faculty'])
    ->whereNotNull('matricule')
    ->whereHas('student')
    ->orderBy('matricule')->orderBy('id')
    ->firstOrFail();

foreach (['print', 'download'] as $screen) {
    $html = view('admin.id-card.' . $screen, [
        'title' => 'ID Card', 'route' => 'admin.id-card', 'view' => 'admin.id-card',
        'path' => 'student', 'rows' => collect([$enroll]), 'print' => $setting,
        'download_mode' => true,
    ])->render();

    check("the {$screen} card draws exactly one barcode",
        1, substr_count($html, 'class="barcode"'));
    check("the {$screen} card's barcode scans as the matricule it prints",
        $enroll->matricule, decodeCode39Svg($html));
    check("the {$screen} card emits no XML declaration",
        false, str_contains($html, '<?xml'));
}

// Both screens must keep using the shared partial, or the two cards for one
// student could come to carry different numbers.
foreach (['print', 'download'] as $screen) {
    $source = file_get_contents($base . '/resources/views/admin/id-card/' . $screen . '.blade.php');
    check("the {$screen} view includes the shared barcode partial",
        1, substr_count($source, "@include('admin.id-card.partials.barcode'"));
}

echo "\n=== 5. a sheet of cards gives each student their own ===\n";

$many = StudentEnroll::with(['student', 'program.faculty'])
    ->whereNotNull('matricule')
    ->whereHas('student')
    ->orderBy('matricule')->orderBy('id')
    ->take(5)->get();

$html = view('admin.id-card.print', [
    'title' => 'ID Card', 'route' => 'admin.id-card', 'view' => 'admin.id-card',
    'path' => 'student', 'rows' => $many, 'print' => $setting,
])->render();

check('one barcode per card on the sheet',
    $many->count(), substr_count($html, 'class="barcode"'));

// Decode each card's own symbol, in page order.
$cards = preg_split('~(?=<div class="id-card">)~', $html);
$decoded = [];
foreach ($cards as $card) {
    if (str_contains($card, 'class="barcode"')) { $decoded[] = decodeCode39Svg($card); }
}
check('each card on the sheet scans as its own matricule',
    $many->pluck('matricule')->all(), $decoded);

echo "\n=== 6. the zipped cards carry the same symbol ===\n";

// The zip builds its cards in the browser from JSON rather than from the
// print view, so it is the one path that could quietly ship cards with no
// barcode - or with a different one - while the other two look right.
$request = Request::create('/admin/admission/id-card-download-zip', 'POST', [
    'students' => $many->pluck('id')->implode(','),
]);
$payload = json_decode(
    (new StudentIdCardController())->downloadZip($request)->getContent(), true);

check('the zip endpoint still answers', true, $payload['success'] ?? false);

$zipDecoded = [];
$zipOrder = [];
foreach ($payload['students'] ?? [] as $row) {
    $zipOrder[] = $row['matricule'];
    $zipDecoded[] = decodeCode39Svg($row['barcode'] ?? '');
}
check('every student in the zip is sent a barcode that scans as their matricule',
    $zipOrder, $zipDecoded);

$indexSource = file_get_contents($base . '/resources/views/admin/id-card/index.blade.php');
check('the zip card template places that barcode on the card',
    1, substr_count($indexSource, '${student.barcode}'));
check('and styles it, or it would land unpositioned in the corner',
    1, substr_count($indexSource, '.zip-id-card .barcode {'));

// The three renderers have to agree on the panel, since they print the same
// card. The zip's copy lives in a JS template literal inside the admin page,
// where bootstrap's own reset applies - which is exactly why it states its
// box model rather than inheriting one.
check('the zip card sizes the barcode panel like the other two',
    $printCss,
    barcodePanelCss($base . '/resources/views/admin/id-card/index.blade.php',
        '.zip-id-card .barcode'));

echo "\n=== 7. the staff card, where the barcode is stood on end ===\n";

$staffSetting = IdCardSetting::where('slug', 'staff-id-card')->firstOrFail();

// The staff ids are a mixed bag - four to eleven characters, some hyphenated -
// so the panel has to swallow all of them, not just the house PAXnnnn form.
$staffIds = DB::table('users')->whereNotNull('staff_id')->orderBy('staff_id')->pluck('staff_id')->all();
$staffWrong = [];
foreach ($staffIds as $id) {
    if (decodeCode39Svg(barcodeFor($id, 270)) !== strtoupper(trim($id))) { $staffWrong[] = $id; }
}
check('every staff id scans back as itself (' . count($staffIds) . ' of them, hyphens included)',
    [], $staffWrong);

$staffPrintCss = barcodePanelCss($base . '/resources/views/admin/staff-id-card/print.blade.php');
$staffDownloadCss = barcodePanelCss($base . '/resources/views/admin/staff-id-card/download.blade.php');

check('the staff print and download cards size the barcode panel identically',
    $staffPrintCss, $staffDownloadCss);

// Rotation is the whole point of this one: a panel that is not turned reads
// across the card and lands on top of the photo.
foreach (['print', 'download'] as $screen) {
    $source = file_get_contents($base . '/resources/views/admin/staff-id-card/' . $screen . '.blade.php');
    preg_match('~\.id-card \.barcode \{(.+?)\}~s', $source, $block);
    check("the staff {$screen} card stands the barcode on end",
        1, preg_match('~transform:\s*rotate\(90deg\)~', $block[1] ?? ''));
    check("the staff {$screen} card rotates about the corner its position assumes",
        1, preg_match('~transform-origin:\s*top left~', $block[1] ?? ''));
}

$longestStaffId = DB::table('users')->whereNotNull('staff_id')
    ->orderByRaw('CHAR_LENGTH(staff_id) DESC')->orderBy('staff_id')->value('staff_id');
checkQuietZone('staff', $staffPrintCss, $longestStaffId);

// The staff print view carries the card twice - once in the @foreach and once
// in the @else - and only the first was ever going to get noticed by eye.
$staff = App\User::with('designation')->whereNotNull('staff_id')
    ->orderBy('staff_id')->orderBy('id')->firstOrFail();

foreach (['print', 'download'] as $screen) {
    $html = view('admin.staff-id-card.' . $screen, [
        'title' => 'Staff ID Card', 'route' => 'admin.staff-id-card',
        'view' => 'admin.staff-id-card', 'path' => 'staff',
        'rows' => collect([$staff]), 'row' => $staff, 'print' => $staffSetting,
    ])->render();

    check("the staff {$screen} card draws exactly one barcode",
        1, substr_count($html, 'class="barcode"'));
    check("the staff {$screen} card's barcode scans as the staff id it prints",
        strtoupper($staff->staff_id), decodeCode39Svg($html));
}

$staffPrintSource = file_get_contents($base . '/resources/views/admin/staff-id-card/print.blade.php');
check('both copies of the staff card markup draw the barcode',
    2, substr_count($staffPrintSource, "@include('admin.id-card.partials.barcode'"));

// And the staff zip, which builds its cards in the browser like the student one.
$staffZipRequest = Request::create('/admin/staff/staff-id-card-download-zip', 'POST', [
    'staffs' => (string) $staff->id,
]);
$staffPayload = json_decode(
    (new StaffIdCardController())->downloadZip($staffZipRequest)->getContent(), true);

check('the staff zip endpoint still answers', true, $staffPayload['success'] ?? false);
check('the staff zip sends a barcode that scans as the staff id',
    [strtoupper($staff->staff_id)],
    array_map(fn ($r) => decodeCode39Svg($r['barcode'] ?? ''), $staffPayload['staffs'] ?? []));

$staffIndexSource = file_get_contents($base . '/resources/views/admin/staff-id-card/index.blade.php');
check('the staff zip card template places that barcode on the card',
    1, substr_count($staffIndexSource, '${staff.barcode}'));
check('the staff zip card sizes and rotates the panel like the other two',
    $staffPrintCss,
    barcodePanelCss($base . '/resources/views/admin/staff-id-card/index.blade.php',
        '.zip-id-card .barcode'));

echo "\n=== 8. nothing was written ===\n";

check('no id card setting was changed', 2, DB::table('id_card_settings')->count());

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
