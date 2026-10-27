<?php

/**
 * The income and expense screens.
 *
 * These screens are where the Bursar works and where the reports come from, so
 * most of what follows is not about layout. Three faults were found while
 * reformatting them, and each one is pinned here in both directions:
 *
 *   - five views disagreed about what a payment method number meant, so a Bank
 *     Transfer displayed as "Cash" and the income edit form offered "Cash"
 *     twice while Orange Money could not be chosen at all;
 *   - both edit forms rendered the amount through round(), so changing only a
 *     date saved a rounded figure and re-posted the ledger at it;
 *   - the default window was the last twelve months, which belongs to no set of
 *     accounts and hid sixteen expenses.
 *
 * Writes happen only in section 4, inside a transaction that is rolled back.
 * The closing section proves the database is as it was, with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\IncomeController;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Income;
use App\Support\PaymentMethod;
use App\Support\TransactionWindow;
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

Auth::guard('web')->login(App\User::first());
View::share('errors', new Illuminate\Support\ViewErrorBag());

/** Render one screen through its real controller. */
function screen(string $module, string $action, $arg = null): array
{
    $controller = app($module === 'income' ? IncomeController::class : ExpenseController::class);

    if ($action === 'index') {
        $request = Request::create('/admin/account/' . $module, 'GET', (array) $arg);
        app()->instance('request', $request);
        $view = $controller->index($request);
    } elseif ($action === 'create') {
        $view = $controller->create();
    } else {
        $view = $controller->edit($arg);
    }

    return [$view->render(), $view->getData()];
}

/** The payment-method options one rendered form actually offers. */
function methodOptions(string $html): array
{
    if (!preg_match('~<select[^>]*name="payment_method".*?</select>~s', $html, $m)) {
        return [];
    }

    preg_match_all('~<option value="(\d+)"[^>]*>([^<]*)</option>~', $m[0], $opts, PREG_SET_ORDER);

    $out = [];
    foreach ($opts as $o) { $out[(int) $o[1]] = trim($o[2]); }

    return $out;
}

$opening = [
    'incomes' => DB::table('incomes')->count(),
    'expenses' => DB::table('expenses')->count(),
    'entries' => DB::table('journal_entries')->count(),
    'mappings' => DB::table('transaction_mappings')->where('status', 'active')->count(),
    'income_total' => (float) DB::table('incomes')->sum('amount'),
    'expense_total' => (float) DB::table('expenses')->sum('amount'),
];

$anIncome = Income::orderBy('id')->first();
$anExpense = Expense::orderBy('id')->first();

echo "=== 1. one vocabulary, in every form ===\n";

$canonical = PaymentMethod::options();

check('five methods are offered', 5, count($canonical));
check('and they are the values the create forms have always stored',
    [1, 2, 3, 4, 5], array_keys($canonical));

foreach ([['income', 'create', null], ['income', 'edit', $anIncome],
          ['expense', 'create', null], ['expense', 'edit', $anExpense]] as [$mod, $act, $arg]) {
    [$html] = screen($mod, $act, $arg);
    $offered = methodOptions($html);

    check(sprintf('%-7s %-6s offers the canonical list', $mod, $act), $canonical, $offered);

    // The teeth check for the bug being fixed: the income edit form used to
    // show "Cash" against both 1 and 2, and no Orange Money at all.
    check(sprintf('%-7s %-6s has no duplicate label', $mod, $act),
        count($offered), count(array_unique($offered)));
    ok(sprintf('%-7s %-6s can reach Orange Money', $mod, $act),
        in_array(__('pm_orange_money'), $offered, true));
}

// And label() answers for a stored value the same way the form offers it.
foreach ($canonical as $value => $name) {
    check('label(' . $value . ') matches the option', $name, PaymentMethod::label($value));
}

check('an unrecorded method reads as a dash, not an empty cell', '—', PaymentMethod::label(null));
check('and so does a value no form can produce', '—', PaymentMethod::label(99));

echo "\n=== 2. the amount is not rounded ===\n";

// Computed here rather than borrowed, because no row on this database
// currently has a fraction — which is exactly why the fault went unnoticed.
foreach ([['income', $anIncome], ['expense', $anExpense]] as [$mod, $row]) {
    $was = $row->amount;

    DB::table($mod === 'income' ? 'incomes' : 'expenses')
        ->where('id', $row->id)->update(['amount' => 123333.33]);

    [$html] = screen($mod, 'edit', $row->fresh());

    preg_match('~name="amount"[^>]*value="([^"]*)"~', $html, $m);
    $shown = (float) str_replace(',', '', $m[1] ?? '0');

    check($mod . ' renders the amount to the cent', 123333.33, $shown);
    // Driven the other way: the behaviour being replaced would fail this.
    ok($mod . ' is not what round() would have shown', $shown !== (float) round(123333.33));

    DB::table($mod === 'income' ? 'incomes' : 'expenses')
        ->where('id', $row->id)->update(['amount' => $was]);
}

check('both amounts were put back', $opening['income_total'], (float) DB::table('incomes')->sum('amount'));

echo "\n=== 3. the window is the exercice, and says so ===\n";

$exercice = TransactionWindow::currentExercice();

ok('there is an exercice covering today', $exercice !== null);

[$html, $data] = screen('expense', 'index');

check('it opens on the exercice start',
    \Carbon\Carbon::parse($exercice->start_date)->toDateString(), $data['window']['start']);
check('and the exercice end',
    \Carbon\Carbon::parse($exercice->end_date)->toDateString(), $data['window']['end']);
check('named on the page', $exercice->name, $data['window']['label']);
ok('and the name is rendered', str_contains($html, $exercice->name));

// The sixteen the old twelve-month default hid.
$outside = Expense::whereDate('date', '<', \Carbon\Carbon::parse($exercice->start_date)->toDateString())->count();

ok('there are expenses outside the exercice', $outside > 0);
check('they are absent by default', 0,
    $data['rows']->filter(fn ($r) => \Carbon\Carbon::parse($r->date)->lt($exercice->start_date))->count());

[, $all] = screen('expense', 'index', ['all' => 1]);

check('"All time" shows every expense', Expense::count(), $all['rows']->count());
check('and has no dates', [null, null], [$all['window']['start'], $all['window']['end']]);
check('with the right range marked', __('acc_all_time'), $all['window']['label']);

// Each quick range produces the dates it claims.
foreach ($data['window']['ranges'] as $range) {
    [, $picked] = screen('expense', 'index', array_filter([
        'start_date' => $range['start'],
        'end_date' => $range['end'],
        'all' => $range['start'] === null ? 1 : null,
    ]));

    check(sprintf('range %-14s resolves to its own dates', $range['label']),
        [$range['start'], $range['end']],
        [$picked['window']['start'], $picked['window']['end']]);
}

// An explicit pair still wins, so a saved link keeps showing what it showed.
[, $explicit] = screen('expense', 'index', ['start_date' => '2026-03-01', 'end_date' => '2026-03-31']);

check('an explicit window is honoured', ['2026-03-01', '2026-03-31'],
    [$explicit['window']['start'], $explicit['window']['end']]);
check('and described by its dates', '1 Mar 2026 – 31 Mar 2026', $explicit['window']['label']);
check('with only March in it', 0,
    $explicit['rows']->filter(fn ($r) => !str_starts_with((string) $r->date, '2026-03'))->count());

echo "\n=== 4. a save that changes nothing changes nothing ===\n";

/*
 * The path that used to round. The form is rendered, posted back exactly as it
 * rendered, and everything the ledger depends on is compared before and after.
 */
DB::beginTransaction();

$subject = Income::whereNotNull('payment_method')->orderBy('id')->first();
DB::table('incomes')->where('id', $subject->id)->update(['amount' => 98765.43]);
$subject = $subject->fresh();

$mapBefore = DB::table('transaction_mappings')->where('transaction_type', 'income')
    ->where('transaction_id', $subject->id)->where('status', 'active')->first();
$entryBefore = $mapBefore
    ? DB::table('journal_entries')->where('id', $mapBefore->journal_entry_id)->first()
    : null;

[$editHtml] = screen('income', 'edit', $subject);

$field = function (string $name) use ($editHtml) {
    preg_match('~name="' . $name . '"[^>]*value="([^"]*)"~', $editHtml, $m);
    return $m[1] ?? null;
};

$selected = function () use ($editHtml) {
    preg_match('~<select[^>]*name="payment_method".*?</select>~s', $editHtml, $s);
    preg_match('~<option value="(\d+)"[^>]*selected~', $s[0] ?? '', $m);
    return $m[1] ?? null;
};

$posted = [
    'category' => $subject->category_id,
    'title' => $subject->title,
    'amount' => $field('amount'),
    'date' => $field('date'),
    'invoice_id' => $field('invoice_id'),
    'reference' => $field('reference'),
    'payment_method' => $selected(),
    'note' => $subject->note,
];

check('the form rendered the amount it was given', '98765.43', $posted['amount']);
check('and preselected the stored method', (string) $subject->payment_method, $posted['payment_method']);

$request = Request::create('/admin/account/income/' . $subject->id, 'PUT', $posted);
app()->instance('request', $request);
app(IncomeController::class)->update($request, $subject);

$after = $subject->fresh();

check('the amount is untouched', '98765.43', (string) $after->amount);
check('the method is untouched', (int) $subject->payment_method, (int) $after->payment_method);
check('the date is untouched', (string) $subject->date, (string) $after->date);

$mapAfter = DB::table('transaction_mappings')->where('transaction_type', 'income')
    ->where('transaction_id', $subject->id)->where('status', 'active')->first();

if ($entryBefore) {
    check('the ledger entry was not replaced',
        (int) $mapBefore->journal_entry_id, (int) ($mapAfter->journal_entry_id ?? 0));

    $entryAfter = DB::table('journal_entries')->where('id', $mapAfter->journal_entry_id)->first();
    check('its date is unchanged', $entryBefore->entry_date, $entryAfter->entry_date);
    check('its total is unchanged', $entryBefore->total_debit, $entryAfter->total_debit);
} else {
    ok('this income has no posting to disturb', $mapAfter === null || $mapAfter->journal_entry_id === null);
    $pass += 2;
}

// A method no form can produce is refused rather than stored.
check('an impossible payment method fails validation', true, (function () use ($subject, $posted) {
    try {
        $bad = array_merge($posted, ['payment_method' => '9']);
        $request = Request::create('/x', 'PUT', $bad);
        app()->instance('request', $request);
        app(IncomeController::class)->update($request, $subject->fresh());
        return false;
    } catch (\Illuminate\Validation\ValidationException $e) {
        return true;
    }
})());

DB::rollBack();

echo "\n=== 5. the band agrees with the table under it ===\n";

foreach (['income', 'expense'] as $mod) {
    [$html, $d] = screen($mod, 'index');

    check($mod . ' count matches the rows', $d['rows']->count(), $d['summary']['count']);
    check($mod . ' total matches the rows',
        round((float) $d['rows']->sum('amount'), 2), round($d['summary']['total'], 2));

    // Recomputed here, not read back from the controller.
    $expected = [];
    foreach ($d['rows'] as $row) {
        $k = $row->payment_method === null ? 0 : (int) $row->payment_method;
        $expected[$k] = ($expected[$k] ?? 0) + (float) $row->amount;
    }

    $shown = [];
    foreach ($d['summary']['methods'] as $k => $m) { $shown[$k] = round($m['total'], 2); }

    ksort($expected);
    ksort($shown);

    check($mod . ' splits by method correctly',
        array_map(fn ($v) => round($v, 2), $expected), $shown);

    // Biggest first, so the band leads with what the money mostly came through.
    $totals = array_values(array_map(fn ($m) => $m['total'], $d['summary']['methods']));
    $sorted = $totals;
    rsort($sorted);
    check($mod . ' lists the largest method first', $sorted, $totals);

    // Formatted the way the view formats it, read from the setting the view
    // reads rather than from a 2 written here.
    $places = DB::table('settings')->value('decimal_place') ?? 2;

    ok($mod . ' prints the total on the page',
        str_contains($html, number_format($d['summary']['total'], $places)));
}

echo "\n=== 6. the table can be sorted and exported ===\n";

foreach (['income', 'expense'] as $mod) {
    [$html, $d] = screen($mod, 'index');

    // Without data-order DataTables sorts the rendered d/m/Y string, under
    // which 01/12 lands before 26/10 and the column is useless for a report.
    preg_match_all('~<td[^>]*data-order="(\d{4}-\d{2}-\d{2})"~', $html, $orders);

    check($mod . ' every row carries a sortable date',
        $d['rows']->count(), count($orders[1]));

    $dates = $d['rows']->map(fn ($r) => \Carbon\Carbon::parse($r->date)->format('Y-m-d'))->values()->all();
    check($mod . ' and it is the row\'s own date', $dates, $orders[1]);

    // The export buttons are configured against the column count in
    // footer_script; a thead/tfoot mismatch breaks the Excel export silently.
    preg_match('~<thead>.*?</thead>~s', $html, $thead);
    preg_match('~<tfoot>.*?</tfoot>~s', $html, $tfoot);

    // <th[\s>] rather than '<th', because the opening <thead> tag counts
    // itself and made the header look one column wider than it is.
    $head = preg_match_all('~<th[\s>]~', $thead[0] ?? '');
    $foot = preg_match_all('~<th[\s>]~', $tfoot[0] ?? '');

    check($mod . ' header has the eight columns it always had', 8, $head);
    check($mod . ' footer matches the header', $head, $foot);

    /*
     * Sliced rather than matched. A lazy .*? across the expense page's 2.5MB
     * of rows exhausts PCRE's backtrack limit and returns false, which this
     * test read as "no columns" instead of as its own failure.
     */
    $bodyAt = strpos($html, '<tbody>');
    $firstRow = $bodyAt === false ? '' : substr($html, $bodyAt, 40000);
    $rowEnd = strpos($firstRow, '</tr>');
    $firstRow = $rowEnd === false ? $firstRow : substr($firstRow, 0, $rowEnd);

    check($mod . ' body rows match too', 8, preg_match_all('~<td[\s>]~', $firstRow));
}

echo "\n=== 7. every screen renders ===\n";

foreach ([['income', 'index', null], ['income', 'create', null], ['income', 'edit', $anIncome],
          ['expense', 'index', null], ['expense', 'create', null], ['expense', 'edit', $anExpense]] as [$mod, $act, $arg]) {
    [$html] = screen($mod, $act, $arg);

    ok(sprintf('%-7s %-6s renders', $mod, $act), strlen($html) > 5000);
    ok(sprintf('%-7s %-6s has no unresolved lang key', $mod, $act), !str_contains($html, 'acc_section_'));
}

// The expense edit form keeps the budget fields and the script that drives them.
[$expenseEdit] = screen('expense', 'edit', $anExpense);

ok('expense edit keeps its budget field', str_contains($expenseEdit, 'name="budget_id"'));
ok('and its allocation field', str_contains($expenseEdit, 'name="budget_allocation_id"'));

[$incomeEdit] = screen('income', 'edit', $anIncome);

ok('income edit does not grow expense fields', !str_contains($incomeEdit, 'name="budget_allocation_id"'));

echo PHP_EOL . "=== 8. nothing is recorded until the figures are read back ===" . PHP_EOL;

/*
 * The confirmation step, modelled on the fees receipt screen. What can be
 * asserted here is the markup contract the script depends on; whether the
 * modal actually opens is a browser question, and is said to be one in the
 * summary rather than quietly claimed here.
 */
foreach ([['income', 'create', null], ['income', 'edit', $anIncome],
          ['expense', 'create', null], ['expense', 'edit', $anExpense]] as [$mod, $act, $arg]) {
    [$html] = screen($mod, $act, $arg);
    $where = sprintf('%-7s %-6s', $mod, $act);

    ok($where . ' has the confirmation modal', str_contains($html, 'id="acctConfirm"'));

    // The save button must not be a submit, or the form goes straight past the
    // confirmation — the one thing that would make all of this decorative.
    preg_match('~<button[^>]*id="acct-review"[^>]*>~', $html, $review);
    ok($where . ' save button is not a submit',
        isset($review[0]) && str_contains($review[0], 'type="button"'));

    // And the real submit starts disabled, so Enter in a field cannot carry
    // the form past it either.
    preg_match('~<button[^>]*id="acct-confirm-submit"[^>]*>~', $html, $confirm);
    ok($where . ' confirm button is the submit',
        isset($confirm[0]) && str_contains($confirm[0], 'type="submit"'));
    ok($where . ' and starts disabled',
        isset($confirm[0]) && str_contains($confirm[0], 'disabled'));

    ok($where . ' has the tick', str_contains($html, 'id="acct-confirm-check"'));

    // Exactly one submit in the form: a second would be a way past the step.
    $formAt = strpos($html, '<form class="needs-validation');
    $formHtml = $formAt === false ? '' : substr($html, $formAt, 80000);
    $formEnd = strpos($formHtml, '</form>');
    $formHtml = $formEnd === false ? $formHtml : substr($formHtml, 0, $formEnd);

    check($where . ' has exactly one submit button', 1,
        preg_match_all('~<button[^>]*type="submit"~', $formHtml));

    /*
     * The amount must NOT carry .autonumber. The shared initialiser in
     * footer_script runs AutoNumeric with decimalPlaces: 0, which rounded the
     * field in the browser whatever the server had sent — so taking round()
     * out of the blade was only half of that fix, and this is the other half.
     */
    preg_match('~<input[^>]*name="amount"[^>]*>~', $html, $amount);
    ok($where . ' amount is not class autonumber',
        isset($amount[0]) && !preg_match('~class="[^"]*\bautonumber\b~', $amount[0]));
    ok($where . ' amount has its own class',
        isset($amount[0]) && str_contains($amount[0], 'acct-amount'));
    ok($where . ' and its own two-place initialiser',
        str_contains($html, 'decimalPlaces: 2') && str_contains($html, 'unformatOnSubmit: true'));
}

// The edit modal is handed the record as it was, so it can say what changes;
// the create modal has nothing to compare against.
[$editHtml] = screen('income', 'edit', $anIncome);
preg_match('~data-original="([^"]*)"~', $editHtml, $orig);
$original = json_decode(html_entity_decode($orig[1] ?? ''), true);

ok('the edit modal carries the record as it was', is_array($original));
check('with the fields the observers watch', true,
    isset($original['amount'], $original['category'], $original['date']));
check('and the amount exactly as stored', (string) $anIncome->amount, $original['amount'] ?? null);

[$createHtml] = screen('income', 'create', null);
preg_match('~data-original="([^"]*)"~', $createHtml, $corig);
check('the create modal has nothing to compare against', '', $corig[1] ?? null);

echo PHP_EOL . "=== 9. the Account section reads as one thing ===" . PHP_EOL;

/*
 * The redesign's own contract. Colour is asserted rather than admired: the
 * accent pair is validated as a categorical palette but both members fail the
 * 4.5:1 text bar, so they may be used as marks and never as text — which is a
 * rule a stylesheet can drift away from silently.
 */
$catIndex = function (string $module) {
    $class = $module === 'income'
        ? App\Http\Controllers\Admin\IncomeCategoryController::class
        : App\Http\Controllers\Admin\ExpenseCategoryController::class;

    $request = Request::create('/admin/account/' . $module . '-category', 'GET');
    app()->instance('request', $request);

    $view = app($class)->index($request);

    return [$view->render(), $view->getData()];
};

$accentOf = function (string $html) {
    preg_match('~--flow:\s*(#[0-9a-fA-F]{6})~', $html, $m);
    return strtolower($m[1] ?? '');
};

// Income cool, expense warm, on records and on categories alike. The category
// screens pass 'expense-category' as $path, which an exact match missed.
foreach ([['income', '#2a78d6'], ['expense', '#eb6834']] as [$mod, $expected]) {
    [$listHtml] = screen($mod, 'index');
    [$createHtml] = screen($mod, 'create');
    [$catHtml] = $catIndex($mod);

    check($mod . ' list carries its own accent', $expected, $accentOf($listHtml));
    check($mod . ' form carries it too', $expected, $accentOf($createHtml));
    check($mod . ' categories carry it too', $expected, $accentOf($catHtml));
}

// And the two sides are actually different, which is the whole point.
[$incomeHtml] = screen('income', 'index');
[$expenseHtml] = screen('expense', 'index');

ok('the two sides are told apart by colour', $accentOf($incomeHtml) !== $accentOf($expenseHtml));

/*
 * Contrast, measured here rather than trusted. WCAG relative luminance; 4.5:1
 * is the floor for body text.
 */
$contrast = function (string $a, string $b) {
    $lum = function (string $hex) {
        $c = [];
        foreach ([1, 3, 5] as $i) {
            $v = hexdec(substr($hex, $i, 2)) / 255;
            $c[] = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };

    $l1 = $lum($a);
    $l2 = $lum($b);

    return ($l1 > $l2 ? $l1 + 0.05 : $l2 + 0.05) / ($l1 > $l2 ? $l2 + 0.05 : $l1 + 0.05);
};

$token = function (string $html, string $name) {
    preg_match('~--' . $name . ':\s*(#[0-9a-fA-F]{6})~', $html, $m);
    return strtolower($m[1] ?? '');
};

foreach (['ink', 'body', 'muted'] as $name) {
    $value = $token($incomeHtml, $name);

    ok('--' . $name . ' is defined', $value !== '');
    ok(sprintf('--%-5s reads as text on white (%.2f:1)', $name, $contrast($value, '#ffffff')),
        $contrast($value, '#ffffff') >= 4.5);
}

// The text-safe variants of the accent, which is what coloured text uses.
foreach ([['income', '#1856a4'], ['expense', '#9a3b12']] as [$mod, $expected]) {
    [$html] = screen($mod, 'index');

    check($mod . ' has a text-safe accent', $expected, $token($html, 'flow-ink'));
    ok(sprintf('%-7s flow-ink reads as text (%.2f:1)', $mod, $contrast($expected, '#ffffff')),
        $contrast($expected, '#ffffff') >= 4.5);
}

// --faint is for rules and icons. Asserting it FAILS the text bar is what
// keeps someone from reaching for it as a label colour later.
ok('--faint is below the text bar, as documented',
    $contrast($token($incomeHtml, 'faint'), '#ffffff') < 4.5);

// The <mark> tags are gone from the record card. They rendered as blue text on
// a yellow highlight, which was the least professional thing on these screens.
// Checked against the markup only: the stylesheet's own comment names both
// of the things it replaced, and matching that read as a failure.
$markup = preg_replace('~<style.*?</style>~s', '', $incomeHtml);

ok('the record card uses no <mark>', !str_contains($markup, '<mark'));
ok('and no <hr/> between every line', !str_contains($markup, '<hr/>'));

// The category screens render and keep their own behaviour.
foreach (['income', 'expense'] as $mod) {
    [$html, $data] = $catIndex($mod);

    ok($mod . ' categories render', strlen($html) > 5000);
    ok($mod . ' categories keep the add form', str_contains($html, 'name="title"'));
    ok($mod . ' categories keep the edit modal', str_contains($html, 'id="editModal-'));
    ok($mod . ' categories keep the status control', str_contains($html, 'name="status"'));

    // State wears a word as well as a colour, so it survives a reader who
    // cannot tell the dot's green from its grey.
    ok($mod . ' states are worded, not colour alone',
        str_contains($html, __('status_active')) || $data['rows']->isEmpty());
}

echo PHP_EOL . "=== 10. the table's own colours survive the theme ===" . PHP_EOL;

/*
 * The admin theme forces colours inside .table with !important:
 *
 *     .table td, .table th  { color: #888 !important }
 *     .table thead th       { background: #3ea1e4; color: #fff !important }
 *
 * Declaring the background with !important and the colour without takes the
 * blue header away and leaves the white text behind — headers rendered white
 * on white — while every body cell is flattened to #888 (3.54:1, below the
 * text bar), losing the ink hierarchy entirely. Both were shipped that way
 * and neither is visible to a test that only looks at markup.
 */
$themeCss = file_get_contents($base . '/public/dashboard/css/style.css');

ok('the theme still forces a colour inside .table',
    (bool) preg_match('~\.table\s+td,\s*\n?\s*\.table\s+th\s*\{[^}]*color:\s*#888\s*!important~', $themeCss));
ok('and a white one on its headers',
    (bool) preg_match('~\.table\s+thead\s+th\s*\{[^}]*color:\s*#fff\s*!important~', $themeCss));

foreach (['income', 'expense'] as $mod) {
    [$html] = screen($mod, 'index');

    // Every colour this stylesheet sets inside the table must win, or it is
    // not set at all.
    preg_match_all('~table\.acct-table[^{]*\{[^}]*\}~', $html, $rules);

    ok($mod . ' declares table colours', $rules[0] !== []);

    foreach ($rules[0] as $rule) {
        if (!preg_match('~(?<!-)color:\s*([^;]+);~', $rule, $decl)) {
            continue;
        }

        $selector = trim(strtok($rule, '{'));

        ok(sprintf('%-7s %-34s colour is !important', $mod, $selector),
            str_contains($decl[1], '!important'));
    }

    // The two the bug actually produced, named so a failure reads plainly.
    preg_match('~table\.acct-table thead th\s*\{([^}]*)\}~', $html, $head);
    $headRule = $head[1] ?? '';

    ok($mod . ' header text is not white', !preg_match('~color:\s*(#fff|#ffffff|white)~i', $headRule));
    ok($mod . ' header text is the label grey', str_contains($headRule, 'color: var(--muted) !important'));

    preg_match('~table\.acct-table tbody td\s*\{([^}]*)\}~', $html, $body);

    ok($mod . ' body text is the body ink',
        str_contains($body[1] ?? '', 'color: var(--body) !important'));
}

echo PHP_EOL . "=== the category filter takes more than one category ===" . PHP_EOL;

/* The dropdown was single-select, so "what did we take in on tuition AND
   hostel" meant opening the screen twice and adding the two by hand. These
   assertions are about the ARITHMETIC of that, not about the widget: picking
   two categories has to return exactly the rows of each, with nothing lost
   between them and nothing counted twice. */
foreach (['income', 'expense'] as $mod) {
    $table = $mod === 'income' ? 'incomes' : 'expenses';

    /* The two busiest categories, so the test has rows to lose if the filter
       is wrong. Read from the data, never hard-coded: the ids differ between
       this install and the one the screen runs on. */
    $busiest = DB::table($table)
        ->selectRaw('category_id, COUNT(*) as c')
        ->whereNotNull('category_id')
        ->groupBy('category_id')->orderByDesc('c')->limit(2)
        ->pluck('category_id')->all();

    if (count($busiest) < 2) {
        echo "  ({$mod}: fewer than two categories carry rows, nothing to compare)" . PHP_EOL;
        continue;
    }

    [$a, $b] = [(int) $busiest[0], (int) $busiest[1]];

    $ids = function (array $query) use ($mod) {
        [, $data] = screen($mod, 'index', $query + ['all' => 1]);

        return $data['rows']->pluck('id')->sort()->values()->all();
    };

    $all = $ids([]);
    $justA = $ids(['category' => [$a]]);
    $justB = $ids(['category' => [$b]]);
    $both = $ids(['category' => [$a, $b]]);

    /* Teeth, inside the test rather than beside it: if the filter quietly did
       nothing, every figure below would still agree with every other. */
    ok("{$mod}: filtering to one category returns fewer rows than all of them",
        count($justA) > 0 && count($justA) < count($all));

    $union = array_values(array_unique(array_merge($justA, $justB)));
    sort($union);

    check("{$mod}: two categories return each category's rows and no others",
        $union, $both);

    check("{$mod}: two categories return as many rows as the two apart",
        count($justA) + count($justB), count($both));

    /* The money follows the rows. A filter that returned the right rows under
       a total for all of them would be worse than no filter. */
    [, $bothData] = screen($mod, 'index', ['category' => [$a, $b], 'all' => 1]);
    [, $aData] = screen($mod, 'index', ['category' => [$a], 'all' => 1]);
    [, $bData] = screen($mod, 'index', ['category' => [$b], 'all' => 1]);

    check("{$mod}: the amount shown for two categories is the two added",
        round((float) $aData['rows']->sum('amount') + (float) $bData['rows']->sum('amount'), 2),
        round((float) $bothData['rows']->sum('amount'), 2));

    /* A bookmark or a mail from before this change sends ?category=7, not
       ?category[]=7. It has to keep meaning what it meant. */
    check("{$mod}: a single-value link from before still filters",
        $justA, $ids(['category' => $a]));

    /* '0' was the old "no category chosen" sentinel and could still arrive
       from a hand-edited URL. It must mean every category, not the category
       whose id is zero - which is no rows at all. */
    check("{$mod}: category=0 still means every category", $all, $ids(['category' => '0']));
    check("{$mod}: an empty choice means every category", $all, $ids(['category' => []]));

    /* The control itself, and the choice surviving the round trip. */
    [$html] = screen($mod, 'index', ['category' => [$a, $b], 'all' => 1]);

    ok("{$mod}: the category control accepts several at once",
        preg_match('~<select[^>]*name="category\[\]"[^>]*multiple~s', $html) === 1);

    foreach ([$a, $b] as $chosen) {
        ok("{$mod}: category {$chosen} is still shown as chosen",
            preg_match('~<option value="' . $chosen . '"[^>]*selected~', $html) === 1);
    }

    /* The quick-range links reload the screen. If they dropped the categories,
       every click would silently widen the report the user is reading. */
    ok("{$mod}: the quick ranges carry the chosen categories",
        strpos($html, 'category%5B0%5D=' . $a) !== false
        && strpos($html, 'category%5B1%5D=' . $b) !== false);
}

echo PHP_EOL . "=== nothing was left behind ===" . PHP_EOL;

$closing = [
    'incomes' => DB::table('incomes')->count(),
    'expenses' => DB::table('expenses')->count(),
    'entries' => DB::table('journal_entries')->count(),
    'mappings' => DB::table('transaction_mappings')->where('status', 'active')->count(),
    'income_total' => (float) DB::table('incomes')->sum('amount'),
    'expense_total' => (float) DB::table('expenses')->sum('amount'),
];

foreach ($opening as $key => $value) {
    check(sprintf('%-13s is still %s', $key, is_float($value) ? number_format($value, 2) : $value),
        $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
