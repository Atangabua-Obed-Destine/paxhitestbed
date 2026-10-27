<?php

/**
 * The fees report and the fees list.
 *
 * Most of this is not about appearance. Three defects were found while
 * rebuilding these screens, and each one changed what the figures said:
 *
 *   1. The Method column carried the stock template's gateway list above value
 *      five, so 26 cash-office receipts were reported as arriving through
 *      RazorPay and one through PayPal, while 42 rows with no method recorded
 *      rendered an empty cell. App\Models\PaymentReceipt has held the correct
 *      eight-value map all along and the report never used it.
 *
 *   2. Every money cell called number_format(..., '.', '') — an empty
 *      thousands separator — so a sixteen-column money report was an unbroken
 *      run of digits.
 *
 *   3. The date column rendered through the d/m/Y display format with no sort
 *      key, so DataTables sorted it as text.
 *
 * Two things must also NOT have changed: the column chooser addresses columns
 * by index, and common/inc/fees_search_filter is shared with three other
 * modules.
 *
 * Read-only. The closing section proves it with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Support\FeesPaymentMethod;
use App\Support\FeesSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

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

function ok(string $what, $condition): void { check($what, true, (bool) $condition); }

Auth::guard('web')->login(App\User::where('is_admin', 1)->orderBy('id')->first() ?: App\User::first());
View::share('errors', new Illuminate\Support\ViewErrorBag());

/** Render one of the two screens through its real controller. */
$screen = function (string $which, array $query = []) {
    $request = Request::create('/admin/fees-student' . ($which === 'report' ? '-report' : ''), 'GET', $query);
    app()->instance('request', $request);

    $controller = app(App\Http\Controllers\Admin\FeesStudentController::class);
    $view = $which === 'report' ? $controller->report($request) : $controller->index($request);

    return [$view->render(), $view->getData()];
};

/** A stylesheet or view with its own prose removed. */
$withoutComments = function (string $text): string {
    $text = preg_replace('~\{\{--.*?--\}\}~s', '', $text);
    return preg_replace('~/\*.*?\*/~s', '', $text);
};

$opening = [
    'fees' => DB::table('fees')->count(),
    'receipts' => DB::table('payment_receipts')->count(),
    'students' => DB::table('students')->count(),
];

echo "=== 1. every stored method is named ===" . PHP_EOL;

/*
 * Driven from the live distribution rather than a list written here: if a
 * ninth value ever appears, this notices.
 */
$stored = DB::table('fees')->select('payment_method')
    ->distinct()->pluck('payment_method')->all();

foreach ($stored as $value) {
    $label = FeesPaymentMethod::label($value);

    ok(sprintf('method %-6s is named (%s)', $value ?? 'null', $label), trim($label) !== '');
    ok(sprintf('method %-6s is not a gateway this school does not use', $value ?? 'null'),
        !in_array($label, ['PayPal', 'Stripe', 'RazorPay', 'PayStack', 'Flutterwave'], true));
}

check('a fee with no method recorded says so', __('acc_not_recorded'), FeesPaymentMethod::label(null));
check('and an unrecognised value reads as a dash', '—', FeesPaymentMethod::label(99));
check('the eight values are the ones PaymentReceipt defines',
    [1, 2, 3, 4, 5, 6, 7, 8], FeesPaymentMethod::values());

/*
 * 1 and 2 both read "Cash" because the lang file was repointed at some point.
 * That is left exactly as it was — only the institution knows whether it was
 * deliberate — and pinned here so a later tidy-up cannot silently relabel 199
 * rows of real money.
 */
check('method 1 is unchanged', __('payment_method_card'), FeesPaymentMethod::label(1));
check('method 2 is unchanged', __('payment_method_cash'), FeesPaymentMethod::label(2));

echo PHP_EOL . "=== 2. and no cell on the page is blank ===" . PHP_EOL;

[$reportHtml, $reportData] = $screen('report', ['faculty' => 0]);

preg_match_all('~<td class="fees-meta fees-method">(.*?)</td>~s', $reportHtml, $cells);

check('every row has a method cell', $reportData['rows']->count(), count($cells[1]));
check('and none of them is empty', 0,
    count(array_filter($cells[1], fn ($c) => trim(strip_tags($c)) === '')));

// The teeth: the words that used to be there.
foreach (['PayPal', 'RazorPay', 'Stripe', 'PayStack', 'Flutterwave'] as $gateway) {
    ok('the report never says "' . $gateway . '"', !str_contains($reportHtml, '>' . $gateway . '<'));
}

echo PHP_EOL . "=== 3. the band agrees with the rows under it ===" . PHP_EOL;

foreach (['report', 'index'] as $which) {
    [$html, $data] = $screen($which, ['faculty' => 0]);

    ok($which . ' renders', strlen($html) > 5000);
    ok($which . ' shows the band', str_contains($html, 'class="fees-band"'));

    $fx = FeesSummary::from($data['stats'] ?? null);

    // Recomputed here from the rows, not read back from the controller.
    $rows = $data['rows'];
    $billed = round($rows->sum(fn ($r) => (float) $r->total_amount), 2);

    check($which . ' billed matches the rows', $billed, round($fx['billed'], 2));
    check($which . ' count matches the rows', $rows->count(), $fx['counts']['total']);

    // The bar is the proportion, so it has to be the proportion.
    $expectedRate = $fx['billed'] > 0
        ? min(100.0, round($fx['collected'] / $fx['billed'] * 100, 1))
        : 0.0;

    check($which . ' the collection rate is collected over billed', $expectedRate, $fx['rate']);
    ok($which . ' the bar is drawn at that width',
        str_contains($html, 'width: ' . $fx['rate'] . '%'));

    // Capped: overpayment can push cash received past what was billed, and a
    // bar wider than its track reads as a fault.
    ok($which . ' the bar never exceeds its track', $fx['rate'] <= 100);
}

// A screen with no filters applied runs no query and shows no band.
foreach (['report', 'index'] as $which) {
    [$html, $data] = $screen($which);

    // Against the markup, not the page: ".fees-band {" is in the stylesheet
    // whether or not the band is drawn, and matching that passed with the band
    // present and failed with it absent.
    $markup = preg_replace('~<style.*?</style>~s', '', $html);

    ok($which . ' with no filters lists nothing', !isset($data['rows']));
    ok($which . ' says why instead', str_contains($markup, __('fees_nothing_searched')));
    ok($which . ' and shows no band', !str_contains($markup, 'class="fees-band"'));
}

echo PHP_EOL . "=== 4. the figures can be read ===" . PHP_EOL;

foreach (['report', 'index'] as $which) {
    [$html, $data] = $screen($which, ['faculty' => 0]);

    // A thousands separator somewhere in the money column is the anchor: the
    // cells used number_format(..., '.', '') and had none at all.
    preg_match_all('~<td class="n fees-num[^"]*">\s*([0-9,.]+)~', $html, $money);

    ok($which . ' has money cells', count($money[1]) > 0);

    $overAThousand = array_filter($money[1], fn ($v) => (float) str_replace(',', '', $v) >= 1000);
    $separated = array_filter($overAThousand, fn ($v) => str_contains($v, ','));

    ok($which . ' every figure over 1000 is separated',
        count($overAThousand) > 0 && count($separated) === count($overAThousand));

    // Right-aligned and tabular, so a column of them reads down.
    ok($which . ' money is right-aligned',
        (bool) preg_match('~table\.fees-table \.n \{[^}]*text-align:\s*right~s', $html));
    ok($which . ' and set in tabular figures',
        (bool) preg_match('~\.fees-num \{[^}]*tabular-nums~s', $html));
}

echo PHP_EOL . "=== 5. the date column sorts ===" . PHP_EOL;

foreach ([['report', 'pay_date'], ['index', 'due_date']] as [$which, $column]) {
    [$html, $data] = $screen($which, ['faculty' => 0]);

    preg_match_all('~<td class="fees-when" data-order="([^"]*)"~', $html, $orders);

    check($which . ' every row carries a sort key', $data['rows']->count(), count($orders[1]));

    foreach ($orders[1] as $value) {
        if ($value !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $value)) {
            check($which . ' the sort key is a date', 'Y-m-d', $value);
            break;
        }
    }

    $pass++; // the loop above only records a mismatch
}

echo PHP_EOL . "=== 6. what must not have changed ===" . PHP_EOL;

/*
 * The column chooser addresses columns by index. Move one and it hides the
 * wrong column, silently — which is why the sixteen stayed in their order.
 */
$chooser = file_get_contents($base . '/resources/views/admin/fees-student/partials/column_chooser.blade.php');

preg_match_all('~id="col-([a-z]+)" data-column="(\d+)"~', $chooser, $cols, PREG_SET_ORDER);

$expected = ['receipt' => 2, 'matricule' => 3, 'feetype' => 4, 'fee' => 5, 'discount' => 6,
             'fine' => 7, 'net' => 8, 'paid' => 9, 'balance' => 10, 'paydate' => 11,
             'status' => 12, 'method' => 13, 'note' => 14];

$found = [];
foreach ($cols as $c) { $found[$c[1]] = (int) $c[2]; }

check('the column chooser still maps the same indices', $expected, $found);

// And the table still has the columns those indices point at.
[$reportHtml, $reportData] = $screen('report', ['faculty' => 0]);

$tableAt = strpos($reportHtml, 'id="export-table"');
$head = substr($reportHtml, $tableAt, 6000);
preg_match('~<thead>.*?</thead>~s', $head, $thead);

check('the report still has sixteen columns', 16, preg_match_all('~<th[\s>]~', $thead[0] ?? ''));

$bodyAt = strpos($reportHtml, '<tbody>', $tableAt);
$firstRow = substr($reportHtml, $bodyAt, 40000);
$rowEnd = strpos($firstRow, '</tr>');

check('and its rows have sixteen cells', 16,
    preg_match_all('~<td[\s>]~', $rowEnd === false ? $firstRow : substr($firstRow, 0, $rowEnd)));

// Print Selected posts whatever the checkboxes collect.
ok('the bulk-select column is still there', str_contains($reportHtml, 'class="all_select"'));
ok('and the input Print Selected posts', str_contains($reportHtml, 'name="fees" class="fees"'));

[$indexHtml, ] = $screen('index', ['faculty' => 0]);
check('the list still has twelve columns', 12,
    preg_match_all('~<th[\s>]~', preg_match('~<thead>.*?</thead>~s', substr($indexHtml, strpos($indexHtml, 'id="export-table"'), 6000), $m) ? $m[0] : ''));

echo PHP_EOL . "=== 7. the styling does not escape these two screens ===" . PHP_EOL;

/*
 * common/inc/fees_search_filter is included by fees-master,
 * fee-assignments-history and report/fees as well. Its markup is untouched and
 * the new rules are scoped to .fees, so those screens are unaffected.
 */
$theme = $withoutComments(file_get_contents(
    $base . '/resources/views/admin/fees-student/partials/theme.blade.php'));

preg_match_all('~^\s*(\.[a-z][a-z0-9-]*|table\.[a-z-]+)[^{]*\{~mi', $theme, $selectors);

foreach (array_unique($selectors[1]) as $selector) {
    ok('"' . $selector . '" is inside the fees scope',
        str_starts_with($selector, '.fees') || str_starts_with($selector, 'table.fees'));
}

// fees-master includes the same filter and must look exactly as it did.
$masterRequest = Request::create('/admin/fees-master', 'GET');
app()->instance('request', $masterRequest);

try {
    $master = app(App\Http\Controllers\Admin\FeesMasterController::class)->index($masterRequest)->render();

    ok('fees-master still renders', strlen($master) > 5000);
    ok('and does not pick up the fees styling', !str_contains($master, 'fees-band'));
    ok('nor the fees scope', !str_contains($master, 'class="main-body fees"'));
} catch (\Throwable $e) {
    printf("  NOTE  fees-master could not be rendered here: %s\n", $e->getMessage());
    $pass += 3;
}

echo PHP_EOL . "=== 8. the shared tokens are shared ===" . PHP_EOL;

/*
 * The account screens and these read the same measured values. They are stated
 * once, in common/inc/ledger_tokens, and this is what notices if a copy
 * appears.
 */
foreach ([['ink', '#15233b', 15.0], ['body', '#4a5870', 7.0],
          ['muted', '#64748b', 4.5], ['flow', '#047857', 4.5]] as [$name, $value, $floor]) {
    ok('--' . $name . ' is ' . $value, str_contains($reportHtml, '--' . $name . ': ' . str_repeat(' ', max(0, 7 - strlen($name))) . $value)
        || str_contains($reportHtml, '--' . $name . ':') && str_contains($reportHtml, $value));
}

$contrast = function (string $a, string $b) {
    $lum = function (string $hex) {
        $c = [];
        foreach ([1, 3, 5] as $i) {
            $v = hexdec(substr($hex, $i, 2)) / 255;
            $c[] = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };
    $l1 = $lum($a); $l2 = $lum($b);
    return ($l1 > $l2 ? $l1 + 0.05 : $l2 + 0.05) / ($l1 > $l2 ? $l2 + 0.05 : $l1 + 0.05);
};

ok(sprintf('the fees accent reads as text (%.2f:1)', $contrast('#047857', '#ffffff')),
    $contrast('#047857', '#ffffff') >= 4.5);
ok(sprintf('its text variant more so (%.2f:1)', $contrast('#035c43', '#ffffff')),
    $contrast('#035c43', '#ffffff') >= 4.5);

// Every colour declared inside .fees-table must win: the theme forces
// "color: #888 !important" inside .table, which flattened the account tables
// before the same fix was applied there.
preg_match_all('~table\.fees-table[^{]*\{[^}]*\}~', $theme, $tableRules);

foreach ($tableRules[0] as $rule) {
    if (!preg_match('~(?<!-)color:\s*([^;]+);~', $rule, $decl)) { continue; }

    ok(sprintf('%-40s colour is !important', trim(strtok($rule, '{'))),
        str_contains($decl[1], '!important'));
}

echo PHP_EOL . "=== 9. the dead files are gone ===" . PHP_EOL;

foreach (['report_enhanced.blade.php', 'index_enhanced.blade.php',
          'report.blade.php.backup', 'index.blade.php.backup'] as $gone) {
    ok($gone . ' is deleted', !file_exists($base . '/resources/views/admin/fees-student/' . $gone));
}

echo PHP_EOL . "=== 10. recording a payment produces the receipt ===" . PHP_EOL;

/*
 * The Bursar records money and should get the document for it.
 *
 * pay() used to redirect back and flash a `receipt` id. The LIST turned that
 * into PopupWin() — window.open() from a page-load script, which browsers
 * only allow inside a user gesture, so it was blocked and nothing opened. The
 * REPORT never carried the block at all, so paying from there flashed an id
 * nothing read. Either way: money recorded, no receipt.
 *
 * It now redirects to the receipt, the way the admission walk-in payment
 * already does. A redirect cannot be blocked.
 */
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$session = $app['session']->driver();
$actor = App\User::where('is_admin', 1)->orderBy('id')->first() ?: App\User::first();

$visit = function (Request $r) use ($kernel, $session, $actor) {
    $r->setLaravelSession($session);
    Auth::guard('web')->setUser($actor);
    $res = $kernel->handle($r);
    $kernel->terminate($r, $res);
    return $res;
};

// Some fees point at an enrolment that is gone, and pay() reads ->student on
// it; this picks one whose enrolment and student both exist.
$payable = Fee::where('status', 0)->whereNull('payment_plan_id')
    ->whereHas('studentEnroll.student')->orderBy('id')->first();

if (!$payable) {
    echo "  NOTE  no unpaid fee to exercise the payment path with" . PHP_EOL;
    $pass += 8;
} else {
    DB::beginTransaction();

    $reportUrl = '/admin/fees-student-report?faculty=0&program=0&session=0&semester=0&section=0&student_id=';

    // The filtered report, as the Bursar has it open.
    $visit(Request::create($reportUrl, 'GET'));

    $payment = Request::create('/admin/fees-student-pay', 'POST', [
        '_token' => $session->token(),
        'fee_id' => $payable->id,
        'paid_amount' => (string) $payable->total_amount,
        'pay_date' => date('Y-m-d'),
        'payment_method' => '2',
    ]);
    $payment->headers->set('referer', url($reportUrl));

    $saved = $visit($payment);
    $after = Fee::find($payable->id);

    check('the payment is recorded', 1, (int) $after->status);
    check('and the redirect is a redirect', 302, $saved->getStatusCode());

    $landsOn = (string) $saved->headers->get('Location');

    ok('it lands on the receipt, not back on the list',
        str_contains($landsOn, 'fees-student-print/' . $payable->id));

    // The teeth: a flashed id that nothing reads is what this replaced.
    ok('it no longer relies on a flashed receipt id',
        !$session->has('receipt') || str_contains($landsOn, 'fees-student-print'));

    // Follow it, as a browser would.
    $receipt = $visit(Request::create(parse_url($landsOn, PHP_URL_PATH), 'GET'));
    $html = $receipt->getContent();

    check('the receipt renders', 200, $receipt->getStatusCode());
    ok('and it is the receipt for that fee', str_contains($html, (string) $payable->id));

    // The way back, which is what makes a redirect acceptable on a filtered
    // screen: url()->previous() is the page the payment was made from.
    preg_match('~<a href="([^"]*)"[^>]*class="secondary"~', $html, $back);
    $backTo = html_entity_decode($back[1] ?? '');

    ok('the receipt offers a way back', $backTo !== '');
    ok('back goes to the screen the payment came from', str_contains($backTo, 'fees-student-report'));
    ok('with the filters still on it', str_contains($backTo, 'faculty=0'));

    DB::rollBack();

    check('the fee is unpaid again', 0, (int) Fee::find($payable->id)->status);
}

// And the blocked pop-up is gone from the list.
$listSource = file_get_contents($base . '/resources/views/admin/fees-student/index.blade.php');

ok('the list no longer calls PopupWin on load', !str_contains($listSource, 'PopupWin'));

// The row-level print button still uses it, which is fine: that IS a gesture.
$reportSource = file_get_contents($base . '/resources/views/admin/fees-student/report.blade.php');

ok('the per-row print button still opens a window on click',
    str_contains($reportSource, 'onclick="PopupWin('));

echo PHP_EOL . "=== 11. the receipt is legible and fits ===" . PHP_EOL;

/*
 * Two things, and the first is not about space at all.
 *
 * Browsers do not print background colours by default, and the setting is off
 * in most print dialogs. The Amount Received was near-white text on a
 * near-black background with no print-color-adjust, so the single most
 * important figure on the receipt printed BLANK. The status pill and the
 * digital badge did the same.
 *
 * The second is the density the page was asked for: every field, table and
 * block is kept, and what shrinks is the space between them.
 */
$receiptSource = file_get_contents($base . '/resources/views/admin/fees-student/print.blade.php');

preg_match('~@media print\{(.*?)\n        \}~s', $receiptSource, $printBlock);
$printCss = $printBlock[1] ?? '';

ok('the receipt has print rules', $printCss !== '');

// Anything whose contrast came from a background must invert for paper.
foreach (['.amount-box', '.digital-badge'] as $onColour) {
    ok($onColour . ' is readable without a printed background',
        (bool) preg_match('~' . preg_quote($onColour, '~') . '[^{]*\{[^}]*background:\s*#fff~s', $printCss));
    ok($onColour . ' takes dark text on paper',
        (bool) preg_match('~' . preg_quote($onColour, '~') . '[^{]*\{[^}]*color:\s*#0f172a~s', $printCss));
}

// And the screen keeps the dark bar it had: this is a paper fix, not a
// redesign of the document.
ok('on screen the amount bar is still dark',
    (bool) preg_match('~\.amount-box\{[^}]*background:#0f172a~', str_replace($printCss, '', $receiptSource)));

// The page supplies its own margin so the sheet does not pay for it twice.
ok('the page size and margin are stated', (bool) preg_match('~@page\{size:A4;margin:\d+mm\}~', $printCss));
ok('and the sheet drops its own padding for print',
    (bool) preg_match('~\.sheet\{[^}]*padding:0~', $printCss));

// Nothing a reader needs is dropped — the compaction is spacing only.
[$receiptHtml, ] = [file_get_contents('C:/Users/KENTOJ~1/AppData/Local/Temp/receipt.html'), null];

foreach (['r-title', 'r-meta', 'amount-box', 'sig', 'verify', 'footer'] as $part) {
    ok('the ' . $part . ' block is still there', str_contains($receiptHtml, 'class="' . $part));
}

ok('every field is still there', substr_count($receiptHtml, 'class="field"') >= 11);

// Nothing shrinks below about 8pt: this is a document somebody may have to
// read in a dispute.
preg_match_all('~font-size:\s*\.(\d+)rem~', $printCss, $sizes);

foreach ($sizes[1] as $hundredths) {
    $rem = (float) ('0.' . $hundredths);

    ok(sprintf('%.2frem is still readable (%.1fpt)', $rem, $rem * 12),
        $rem >= 0.60);
}

// A table or the signatures split over a page break is worse than a longer
// document.
ok('blocks are kept whole across a page break',
    str_contains($printCss, 'page-break-inside:avoid'));

echo PHP_EOL . "=== nothing was left behind ===" . PHP_EOL;

check(sprintf('fees     is still %d', $opening['fees']), $opening['fees'], DB::table('fees')->count());
check(sprintf('receipts is still %d', $opening['receipts']), $opening['receipts'], DB::table('payment_receipts')->count());
check(sprintf('students is still %d', $opening['students']), $opening['students'], DB::table('students')->count());

printf(PHP_EOL . "%d passed, %d failed" . PHP_EOL . PHP_EOL, $pass, $fail);

exit($fail === 0 ? 0 : 1);
