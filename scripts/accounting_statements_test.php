<?php

/**
 * The financial statements agree with the ledger, and with each other.
 *
 * Balance sheet, trial balance, income statement (both views), cash flow
 * statement (screen, PDF, CSV, comparative), the aging reports and every
 * export, held to figures computed here from the raw posted lines — never
 * read back from the code under test.
 *
 * Then a handful of entries that used to break them — a supplier invoice and
 * its payment, depreciation, tax owed, an overdrawn bank, and money left on a
 * heading account — each followed by the same checks.
 *
 * Everything runs inside one transaction that is rolled back.
 *
 *   php scripts/accounting_statements_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $what . (!$ok && $detail !== '' ? " — $detail" : '') . "\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

$close = fn ($a, $b, $tol = 0.01) => abs((float) $a - (float) $b) <= $tol;
$fmt = fn ($v) => number_format((float) $v, 2);

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
Auth::guard('web')->login($admin);

$call = function (string $method, string $path, array $data = [], bool $json = false) use ($kernel) {
    app('session.store')->forget('errors');
    if ($method !== 'GET') {
        $data['_token'] = csrf_token();
    }
    // Money-posting screens go through the review dialog (RequireReview);
    // these steps act as if it had been confirmed.
    if ($method !== 'GET' && !array_key_exists('reviewed', $data)) {
        $data['reviewed'] = 1;
    }
    $request = Request::create($path, $method, $data);
    if ($json) {
        $request->headers->set('Accept', 'application/json');
    }
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/dashboard');

    return $kernel->handle($request);
};
$view = function ($response) {
    $o = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;

    return $o instanceof Illuminate\View\View ? $o : null;
};
$body = function ($response) {
    if ($response instanceof Symfony\Component\HttpFoundation\StreamedResponse) {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    return (string) $response->getContent();
};
$csv = fn (string $text) => array_map('str_getcsv', array_values(array_filter(explode("\n", str_replace("\r", '', ltrim($text, "\xEF\xBB\xBF"))), 'strlen')));

$today = date('Y-m-d');
$year = FiscalYear::forDate($today);
$yearStart = $year->start_date->format('Y-m-d');
$acct = DB::table('chart_of_accounts')->pluck('id', 'account_code');
// Old codes also find their renumbered account (53 -> 552, 40 -> 401...), so
// this test reads the same on either side of accounts:syscohada. 12 is not
// aliased: the test uses it as retained earnings, which the new 12 is.
$acct = $acct->all();
foreach (\App\Console\Commands\ChartSyscohada::MOVES as [$from, , $to]) {
    if ($from !== '12' && isset($acct[$to]) && \App\Console\Commands\ChartSyscohada::current($from) === $to) {
        $acct[$from] = $acct[$to];
    }
}
$acct = collect($acct);


/** Raw ledger: code => debit minus credit, posted and live, up to a date (and from one). */
$raw = function (?string $to = null, ?string $from = null) {
    $rows = DB::select("
        SELECT a.account_code code, a.normal_balance nb, a.opening_balance ob,
               COALESCE(SUM(l.debit), 0) d, COALESCE(SUM(l.credit), 0) c
        FROM chart_of_accounts a
        LEFT JOIN journal_entry_lines l ON l.account_id = a.id AND EXISTS (
            SELECT 1 FROM journal_entries je WHERE je.id = l.journal_entry_id AND je.is_posted = 1
              AND je.deleted_at IS NULL AND je.entry_date <= ? AND je.entry_date >= ?)
        GROUP BY a.id, a.account_code, a.normal_balance, a.opening_balance", [$to ?? '9999-12-31', $from ?? '0001-01-01']);
    $out = [];
    foreach ($rows as $r) {
        $opening = $from ? 0.0 : (float) $r->ob * ($r->nb === 'debit' ? 1 : -1);
        $out[(string) $r->code] = round($opening + (float) $r->d - (float) $r->c, 2);
    }

    return $out;
};
$sumWhere = fn (array $ledger, callable $keep) => round(array_sum(array_filter($ledger, $keep, ARRAY_FILTER_USE_BOTH)), 2);
$isClass = fn (string ...$classes) => fn ($v, $code) => in_array(((string) $code)[0], $classes, true);

/** Every statement, held to the raw ledger. Called on live data and after each fixture entry. */
$statementsAgree = function (string $label) use ($call, $view, $body, $raw, $sumWhere, $isClass, $close, $fmt, $today, $yearStart, $year) {
    $L = $raw($today);

    // Trial balance
    $tb = $view($call('GET', '/admin/general-ledger/trial-balance'))->getData()['accounts'];
    $tbDebit = round($tb->sum('period_debit'), 2);
    $tbCredit = round($tb->sum('period_credit'), 2);
    $rawTotals = DB::selectOne('SELECT COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c FROM journal_entry_lines l JOIN journal_entries je ON je.id = l.journal_entry_id AND je.is_posted = 1 AND je.deleted_at IS NULL');
    check("$label: trial balance debits equal credits", $close($tbDebit, $tbCredit), 'debits ' . $fmt($tbDebit) . ', credits ' . $fmt($tbCredit));
    check("$label: trial balance totals are every posted line", $close($tbDebit, $rawTotals->d) && $close($tbCredit, $rawTotals->c), 'trial balance ' . $fmt($tbDebit) . ', ledger ' . $fmt($rawTotals->d));
    $carrying = collect($L)->filter(fn ($v) => abs($v) > 0.004)->keys()->map(fn ($k) => (string) $k);
    $missing = $carrying->diff($tb->pluck('account_code')->map(fn ($k) => (string) $k));
    check("$label: every account with a balance is on the trial balance", $missing->isEmpty(), 'missing ' . $missing->implode(', '));

    // Balance sheet
    $bs = $view($call('GET', '/admin/general-ledger/balance-sheet'))->getData();
    $assets = $sumWhere($L, $isClass('2', '3'))
        + $sumWhere($L, fn ($v, $c) => in_array(((string) $c)[0], ['4', '5'], true) && ($v > 0 || in_array(substr((string) $c, 0, 2), ['49', '59'], true)));
    $result = -$sumWhere($L, $isClass('6', '7', '8'));
    check("$label: balance sheet balances", $close($bs['totalAssets'], $bs['totalLiabilities']) && $close($bs['difference'], 0), 'assets ' . $fmt($bs['totalAssets']) . ', liabilities and equity ' . $fmt($bs['totalLiabilities']));
    check("$label: total assets are classes 2, 3 and the debit balances of 4 and 5 (" . $fmt($assets) . ')', $close($bs['totalAssets'], $assets), 'shown ' . $fmt($bs['totalAssets']));
    check("$label: result shown on the passif is income less expenses (" . $fmt($result) . ')', $close($bs['yearResult'] + $bs['priorResult'], $result), 'shown ' . $fmt($bs['yearResult'] + $bs['priorResult']));
    $yearOnly = -$sumWhere($raw($today, $yearStart), $isClass('6', '7', '8'));
    check("$label: the year's result is this exercice's alone (" . $fmt($yearOnly) . ')', $close($bs['yearResult'], $yearOnly), 'shown ' . $fmt($bs['yearResult']));
    $dep = $bs['fixedAssets']->filter(fn ($a) => str_starts_with((string) $a->account_code, '28'));
    check("$label: depreciation reduces fixed assets", $dep->every(fn ($a) => $a->balance <= 0), $dep->map(fn ($a) => $a->account_code . ' ' . $a->balance)->implode(', '));

    // Income statement, accounting view, for the exercice
    $is = $view($call('GET', '/admin/general-ledger/income-statement?start_date=' . $yearStart . '&end_date=' . $today))->getData();
    check("$label: income statement for the year ends in the same result as the balance sheet", $close($is['netProfit'], $bs['yearResult']), 'income statement ' . $fmt($is['netProfit']) . ', balance sheet ' . $fmt($bs['yearResult']));

    // Cash flow statement for the exercice
    $r = $call('GET', '/admin/accounting-reports/cash-flow-statement?fiscal_year_id=' . $year->id);
    $cf = $view($r)->getData()['statement'];
    $cashEnd = $sumWhere($raw($year->end_date->format('Y-m-d')), fn ($v, $c) => ((string) $c)[0] === '5' && !str_starts_with((string) $c, '59'));
    $cashBegin = $sumWhere($raw(date('Y-m-d', strtotime($yearStart . ' -1 day'))), fn ($v, $c) => ((string) $c)[0] === '5' && !str_starts_with((string) $c, '59'));
    check("$label: cash flow ends at the cash in the ledger (" . $fmt($cashEnd) . ')', $close($cf['ending_cash_balance'], $cashEnd), 'shown ' . $fmt($cf['ending_cash_balance']));
    check("$label: cash flow begins at the cash before the exercice (" . $fmt($cashBegin) . ')', $close($cf['beginning_cash_balance'], $cashBegin), 'shown ' . $fmt($cf['beginning_cash_balance']));
    check("$label: its sections explain the change in cash", $cf['is_balanced'] && $close($cf['net_change_in_cash'], $cashEnd - $cashBegin), 'sections ' . $fmt($cf['net_change_in_cash']) . ', cash moved ' . $fmt($cashEnd - $cashBegin));
    check("$label: the cash flow screen shows those figures", str_contains($body($r), format_amount(abs($cf['ending_cash_balance']))));

    return compact('bs', 'cf', 'L');
};

DB::beginTransaction();
$counts = fn () => ['entries' => DB::table('journal_entries')->count(), 'lines' => DB::table('journal_entry_lines')->count(), 'mappings' => DB::table('default_account_mappings')->count()];
$before = $counts();

try {
    section('1. The statements on the books as they stand');
    $live = $statementsAgree('live');

    section('2. The income statement defaults to the books');
    $r = $call('GET', '/admin/general-ledger/income-statement');
    check('the default view is the accounting view', $view($r)?->name() === 'admin.general-ledger.income-statement-journal', $view($r)?->name() ?? 'none');
    $r = $call('GET', '/admin/general-ledger/income-statement?use_journal_entries=0&start_date=' . $yearStart . '&end_date=' . $today);
    $m = $view($r)->getData();
    check('the module summary is still there on request', $view($r)->name() === 'admin.general-ledger.income-statement');
    $paid = DB::table('payrolls')->where('status', 1)->whereNotNull('pay_date')->whereBetween('pay_date', [$yearStart, $today]);
    $loans = App\Models\PayrollSetting::current()->advance_account_id
        ? (float) DB::table('payroll_details')->whereIn('payroll_id', (clone $paid)->pluck('id'))->where('status', 0)->whereNotNull('staff_loan_id')->sum('amount') : 0.0;
    $payrollCost = round((float) (clone $paid)->sum('gross_salary') + (float) (clone $paid)->sum('employer_tax') + $loans, 2);
    check('module summary: payroll is the salary cost the ledger books (' . $fmt($payrollCost) . '), not net pay', $close($m['payrollTotal'], $payrollCost), 'shown ' . $fmt($m['payrollTotal']));
    $ledgerPayroll = DB::table('journal_entry_lines as l')->join('journal_entries as je', 'je.id', '=', 'l.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
        ->where('je.is_posted', 1)->whereNull('je.deleted_at')->whereIn('je.reference_type', ['payroll', 'payroll_reversal'])
        ->whereBetween('je.entry_date', [$yearStart, $today])->where('a.account_code', 'like', '6%')->sum(DB::raw('l.debit - l.credit'));
    check('module summary: payroll equals what payroll posted to class 6 (' . $fmt($ledgerPayroll) . ')', $close($m['payrollTotal'], $ledgerPayroll), 'shown ' . $fmt($m['payrollTotal']));
    $capitalCats = DB::table('default_account_mappings as m')->join('chart_of_accounts as a', 'a.id', '=', 'm.debit_account_id')
        ->where('m.mapping_type', 'expense_category')->where('a.account_code', 'like', '2%')->pluck('m.category_id');
    $capex = (float) DB::table('expenses')->where('status', 1)->whereIn('category_id', $capitalCats)->whereBetween('date', [$yearStart, $today])->sum('amount');
    check('module summary: equipment bought (' . $fmt($capex) . ') is kept out of expenses', $close($m['capitalExpenditure'], $capex)
        && $close($m['operatingExpensesTotal'], (float) DB::table('expenses')->where('status', 1)->whereNotIn('category_id', $capitalCats)->whereBetween('date', [$yearStart, $today])->sum('amount')),
        'capital ' . $fmt($m['capitalExpenditure']) . ', operating ' . $fmt($m['operatingExpensesTotal']));
    $depr = -array_sum(array_filter($raw($today, $yearStart), fn ($v, $c) => in_array(substr((string) $c, 0, 2), ['68', '69'], true), ARRAY_FILTER_USE_BOTH));
    check('module summary: depreciation from the ledger is an expense (' . $fmt(-$depr) . ')', $close($m['depreciationTotal'], -$depr), 'shown ' . $fmt($m['depreciationTotal']));

    section('3. The comparative cash flow compares with the year before, by date');
    $previous = FiscalYear::whereDate('end_date', '<', $year->start_date)->orderByDesc('end_date')->first();
    $r = $call('GET', '/admin/accounting-reports/comparative-cash-flow?fiscal_year_id=' . $year->id);
    $cmp = $view($r)->getData()['statement'];
    check('the previous year is ' . ($previous->name ?? 'none'), ($cmp['periods']['previous']['name'] ?? null) === ($previous->name ?? __('No earlier year')), json_encode($cmp['periods']));
    check('its net change matches the single-year statement', $close($cmp['net_change']['current'], $live['cf']['net_change_in_cash']));
    check('the comparative screen shows both years', str_contains($body($r), $year->name) && (!$previous || str_contains($body($r), $previous->name)));

    section('4. Student fee aging lists what is overdue');
    // A fee counts only when it still owes something; summed per student.
    $perFee = DB::table('fees as f')->join('student_enrolls as e', 'e.id', '=', 'f.student_enroll_id')->join('students as s', 's.id', '=', 'e.student_id')
        ->whereNotIn('f.status', [1, 3])->whereDate('f.due_date', '<=', $today)
        ->whereRaw('(f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0) - COALESCE(f.paid_amount,0)) > 0')
        ->select('s.id', 's.first_name', DB::raw('SUM(f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0) - COALESCE(f.paid_amount,0)) as owed'))
        ->groupBy('s.id', 's.first_name')->get();
    $r = $call('GET', '/admin/accounting-reports/student-fee-aging');
    $aging = $view($r)->getData()['report'];
    check('one row per student who owes (' . $perFee->count() . ')', count($aging['details']) === $perFee->count(), count($aging['details']) . ' rows');
    check('the total owed is ' . $fmt($perFee->sum('owed')), $close($aging['summary']['total'], $perFee->sum('owed')), 'shown ' . $fmt($aging['summary']['total']));
    check('the age bands add up to the total', $close(array_sum(array_intersect_key($aging['summary'], array_flip(['current', 'days_31_60', 'days_61_90', 'days_91_120', 'over_120']))), $aging['summary']['total']));
    check('the screen lists the students', $perFee->isEmpty() || str_contains($body($r), e($perFee->first()->first_name)));

    section('5. Every export returns its file');
    $exports = [
        ['/admin/general-ledger/balance-sheet/export-pdf', 'pdf'],
        ['/admin/general-ledger/income-statement/export-pdf?start_date=' . $yearStart, 'pdf'],
        ['/admin/general-ledger/account/' . $acct['571'] . '/export-pdf', 'pdf'],
        ['/admin/accounting-reports/export-pdf?report=cash-flow&fiscal_year_id=' . $year->id, 'pdf'],
        ['/admin/accounting-reports/export-pdf?report=comparative-cash-flow&fiscal_year_id=' . $year->id, 'pdf'],
        ['/admin/accounting-reports/export-pdf?report=budget_vs_actual&fiscal_year_id=' . $year->id, 'pdf'],
        ['/admin/accounting-reports/export-pdf?report=student_fee_aging', 'pdf'],
        ['/admin/accounting-reports/export-pdf?report=receivables_aging', 'pdf'],
        ['/admin/accounting-reports/export-pdf?report=payables_aging', 'pdf'],
    ];
    foreach ($exports as [$path, $type]) {
        $res = $call('GET', $path);
        check("$path is a $type file", $res->getStatusCode() === 200 && str_contains((string) $res->headers->get('content-type'), $type) && strlen($body($res)) > 1000,
            $res->getStatusCode() . ' ' . $res->headers->get('content-type'));
    }
    // The PDFs print the same figures as the screens (read from the HTML they are drawn from).
    $pdfHtml = view('admin.accounting.reports.pdf.cash-flow', ['data' => $live['cf'], 'reportType' => 'cash_flow'])->render();
    check('the cash flow PDF prints the ending cash', str_contains($pdfHtml, format_amount(abs($live['cf']['ending_cash_balance']))));
    $bsPdf = view('admin.general-ledger.balance-sheet-pdf', $live['bs'] + ['asOfDate' => $today, 'comparativeDate' => null])->render();
    check('the balance sheet PDF prints the total assets', str_contains($bsPdf, format_amount($live['bs']['totalAssets'], true)));

    $rows = $csv($body($call('GET', '/admin/accounting-reports/export-excel?report=cash-flow&fiscal_year_id=' . $year->id)));
    check('cash flow CSV: header and a line per item', ($rows[0][0] ?? '') === 'Section' && count($rows) >= 5, count($rows) . ' rows');
    check('cash flow CSV: ends at the same cash', $close(end($rows)[2] ?? null, $live['cf']['ending_cash_balance']));
    $rows = $csv($body($call('GET', '/admin/accounting-reports/export-excel?report=student_fee_aging')));
    check('fee aging CSV: one row per student plus header and total', count($rows) === count($aging['details']) + 2, count($rows) . ' rows');
    $rows = $csv($body($call('GET', '/admin/general-ledger/trial-balance/export-excel')));
    $t = end($rows);
    check('trial balance CSV: closing debits equal closing credits', $close($t[6], $t[7]), json_encode($t));
    check('trial balance CSV: no "-0"', !str_contains(json_encode($rows), '"-0"'));
    $rows = $csv($body($call('GET', '/admin/general-ledger/balance-sheet/export-excel')));
    $totals = array_values(array_filter($rows, fn ($r) => in_array($r[3] ?? '', ['Total assets', 'Total liabilities and equity'], true)));
    check('balance sheet CSV: totals equal', count($totals) === 2 && $close($totals[0][4], $totals[1][4]), json_encode($totals));
    $rows = $csv($body($call('GET', '/admin/general-ledger/income-statement/export-excel?start_date=' . $yearStart)));
    check('income statement CSV: net result matches the balance sheet', $close(end($rows)[3] ?? null, $live['bs']['yearResult']), json_encode(end($rows)));
    $rows = $csv($body($call('GET', '/admin/accounting-reports/export-excel?report=budget_vs_actual&fiscal_year_id=' . $year->id)));
    check('budget CSV: has its lines', ($rows[0][0] ?? '') === 'Code' && count($rows) > 1, count($rows) . ' rows');

    section('6. Heading accounts are refused');
    $n = DB::table('journal_entries')->count();
    $call('POST', '/admin/journal-entries', ['entry_date' => $today, 'fiscal_year_id' => $year->id, 'journal_type' => 'general', 'description' => 'TEST heading', 'post_immediately' => 1,
        'lines' => [['account_id' => $acct['24'], 'debit' => 1000, 'credit' => 0], ['account_id' => $acct['571'], 'debit' => 0, 'credit' => 1000]]]);
    check('a manual entry on a heading is refused', DB::table('journal_entries')->count() === $n);
    $res = $call('POST', '/admin/accounting/mappings/save-default', ['mappings' => [['mapping_type' => 'income_category', 'category_id' => 2,
        'debit_account_id' => $acct['571'], 'credit_account_id' => $acct['7']]]], true);
    check('a category mapping to a heading is refused', $res->getStatusCode() === 422, (string) $res->getStatusCode());
    check('no mapping points at a heading', !DB::table('default_account_mappings as m')->join('chart_of_accounts as a', fn ($j) => $j->on('a.id', '=', 'm.debit_account_id')->orOn('a.id', '=', 'm.credit_account_id'))
        ->where('a.account_category', '!=', 'detail')->exists());

    section('7. Entries that used to break the statements');
    $post = function (string $description, array $lines) use ($call, $today, $year) {
        $before = DB::table('journal_entries')->max('id');
        $call('POST', '/admin/journal-entries', ['entry_date' => $today, 'fiscal_year_id' => $year->id, 'journal_type' => 'general',
            'description' => $description, 'post_immediately' => 1,
            'lines' => array_map(fn ($l) => ['account_id' => $l[0], 'debit' => $l[1], 'credit' => $l[2], 'description' => $description], $lines)]);

        return DB::table('journal_entries')->where('id', '>', $before)->where('is_posted', 1)->exists();
    };
    $cfLine = fn ($cf, $type) => (float) (collect($cf['operating_activities']['items'])->firstWhere('type', $type)['amount'] ?? 0);

    check('a supplier invoice posts', $post('TEST supplier invoice', [[$acct['628'], 100000, 0], [$acct['40'], 0, 100000]]));
    $s = $statementsAgree('supplier invoice');
    check('the supplier is owed 100,000 on the passif', $close($s['bs']['payables']->firstWhere('account_code', \App\Console\Commands\ChartSyscohada::current('40'))->balance ?? 0, ($live['bs']['payables']->firstWhere('account_code', \App\Console\Commands\ChartSyscohada::current('40'))->balance ?? 0) + 100000));
    check('cash flow: owing the supplier more is cash kept, +100,000', $close($cfLine($s['cf'], 'payables') - $cfLine($live['cf'], 'payables'), 100000));
    check('cash flow: no cash moved', $close($s['cf']['net_change_in_cash'], $live['cf']['net_change_in_cash']));

    check('the supplier is paid', $post('TEST supplier paid', [[$acct['40'], 100000, 0], [$acct['571'], 0, 100000]]));
    $s2 = $statementsAgree('supplier paid');
    check('cash flow: paying the supplier is cash out, -100,000', $close($s2['cf']['net_change_in_cash'] - $live['cf']['net_change_in_cash'], -100000));

    check('tax owed on a salary posts', $post('TEST tax owed', [[$acct['661'], 50000, 0], [$acct['443'], 0, 50000]]));
    $s3 = $statementsAgree('tax owed');
    check('cash flow: owing the State more is cash kept, +50,000 (it used to show as cash out)', $close($cfLine($s3['cf'], 'state') - $cfLine($s2['cf'], 'state'), 50000),
        'state line moved by ' . $fmt($cfLine($s3['cf'], 'state') - $cfLine($s2['cf'], 'state')));

    check('depreciation posts', $post('TEST depreciation', [[$acct['681'], 40000, 0], [$acct['28'], 0, 40000]]));
    $s4 = $statementsAgree('depreciation');
    check('fixed assets fall by 40,000', $close($s4['bs']['fixedAssets']->sum('balance'), $s3['bs']['fixedAssets']->sum('balance') - 40000));
    check('cash flow: depreciation is added back, no cash moved', $close($s4['cf']['net_change_in_cash'], $s3['cf']['net_change_in_cash']));

    $bank = $s4['L']['521'] ?? 0;
    check('the bank is overdrawn by 1,000,000', $post('TEST overdraft', [[$acct['628'], $bank + 1000000, 0], [$acct['521'], 0, $bank + 1000000]]));
    $s5 = $statementsAgree('overdraft');
    check('the overdrawn bank is a debt (Trésorerie-Passif), not a negative asset', $close($s5['bs']['treasuryLiabilities']->firstWhere('account_code', '521')->balance ?? 0, 1000000)
        && !$s5['bs']['cashAndBanks']->contains('account_code', '521'));

    // Money already sitting on a heading, as the data had before the refusal.
    $entry = DB::table('journal_entries')->insertGetId(['entry_number' => 'TEST-HEAD', 'entry_date' => $today, 'fiscal_year_id' => $year->id, 'journal_type' => 'general',
        'description' => 'TEST old heading posting', 'total_debit' => 5000, 'total_credit' => 5000, 'is_posted' => 1, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('journal_entry_lines')->insert([
        ['journal_entry_id' => $entry, 'line_number' => 1, 'account_id' => $acct['24'], 'debit' => 5000, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['journal_entry_id' => $entry, 'line_number' => 2, 'account_id' => $acct['571'], 'debit' => 0, 'credit' => 5000, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $s6 = $statementsAgree('money on a heading');
    check('the heading is on the balance sheet, marked', ($h = $s6['bs']['fixedAssets']->firstWhere('account_code', '24')) && !$h->is_postable);
    check('and on the trial balance', $view($call('GET', '/admin/general-ledger/trial-balance'))->getData()['accounts']->contains('account_code', '24'));
    // Take the test heading posting back out: the closing below must not
    // stumble over it (it is not one of the books' real faults).
    DB::table('journal_entry_lines')->where('journal_entry_id', $entry)->delete();
    DB::table('journal_entries')->where('id', $entry)->delete();

    section('8. Closing a year, and reopening it');
    $prior = FiscalYear::whereDate('end_date', '<', $year->start_date)->orderByDesc('end_date')->first();
    if (!$prior) {
        check('there is an earlier fiscal year to close', false, 'none on this database');
    } else {
        $pFrom = $prior->start_date->format('Y-m-d');
        $pTo = $prior->end_date->format('Y-m-d');
        $isPrior = fn () => $view($call('GET', '/admin/general-ledger/income-statement?start_date=' . $pFrom . '&end_date=' . $pTo))->getData();
        $priorIsBefore = $isPrior();
        $priorResultRaw = -$sumWhere($raw($pTo, $pFrom), $isClass('6', '7', '8'));
        check('before closing, the ' . $prior->name . ' income statement shows its result (' . $fmt($priorResultRaw) . ')', $close($priorIsBefore['netProfit'], $priorResultRaw), 'shown ' . $fmt($priorIsBefore['netProfit']));

        $newClosing = function (FiscalYear $fy) use ($acct, $admin) {
            $c = App\Models\YearEndClosing::create([
                'fiscal_year_id' => $fy->id, 'closing_date' => $fy->end_date->format('Y-m-d'),
                'retained_earnings_account_id' => $acct['12'], 'income_summary_account_id' => $acct['111'],
                'status' => App\Models\YearEndClosing::STATUS_DRAFT,
                'checklist' => array_map(fn () => true, App\Models\YearEndClosing::DEFAULT_CHECKLIST),
                'created_by' => $admin->id,
            ]);
            $c->start();

            return $c;
        };
        $refused = function (callable $f) {
            try {
                $f();

                return null;
            } catch (Throwable $e) {
                return $e->getMessage();
            }
        };

        // Oldest first.
        $current = $newClosing($year);
        $msg = $refused(fn () => $current->generateClosingEntries());
        check('closing ' . $year->name . ' is refused while ' . $prior->name . ' is open', $msg !== null && str_contains($msg, $prior->name), (string) $msg);

        // Not on broken books.
        $bad = DB::table('journal_entries')->insertGetId(['entry_number' => 'TEST-BAD', 'entry_date' => $pTo, 'fiscal_year_id' => $prior->id, 'journal_type' => 'general',
            'description' => 'TEST unbalanced', 'total_debit' => 10, 'total_credit' => 10, 'is_posted' => 1, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('journal_entry_lines')->insert(['journal_entry_id' => $bad, 'line_number' => 1, 'account_id' => $acct['571'], 'debit' => 10, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $priorClosing = $newClosing($prior);
        $msg = $refused(fn () => $priorClosing->generateClosingEntries());
        check('closing is refused while the ledger has a critical fault', $msg !== null && str_contains($msg, 'Ledger health'), (string) $msg);
        DB::table('journal_entry_lines')->where('journal_entry_id', $bad)->delete();
        DB::table('journal_entries')->where('id', $bad)->delete();

        // Close the earlier year.
        $msg = $refused(fn () => $priorClosing->fresh()->generateClosingEntries());
        check($prior->name . ' closes', $msg === null, (string) $msg);
        $priorClosing = $priorClosing->fresh();
        $closingEntry = JournalEntry::find($priorClosing->closing_journal_entry_id);
        check('its closing entry is posted, dated ' . $pTo . ', and balances', $closingEntry && $closingEntry->is_posted && $closingEntry->entry_date->format('Y-m-d') === $pTo && $close($closingEntry->lines()->sum('debit'), $closingEntry->lines()->sum('credit')));
        check('classes 6, 7 and 8 of ' . $prior->name . ' are now at zero in the ledger', $close($sumWhere($raw($pTo, $pFrom), $isClass('6', '7', '8')), 0));
        $msg = $refused(fn () => $priorClosing->approve($admin->id));
        check('and the closing is approved, closing the year', $msg === null && $prior->fresh()->is_closed, (string) $msg);

        $after = $isPrior();
        check('the ' . $prior->name . ' income statement still shows its result after closing', $close($after['netProfit'], $priorResultRaw), 'shown ' . $fmt($after['netProfit']));
        $statementsAgree('after closing ' . $prior->name);
        $bsEnd = $view($call('GET', '/admin/general-ledger/balance-sheet?as_of_date=' . $pTo))->getData();
        check('the balance sheet at ' . $pTo . ' balances', $close($bsEnd['difference'], 0), $fmt($bsEnd['difference']));
        check('and carries the result in equity (account 12), not as an unclosed result', $close($bsEnd['priorResult'] + $bsEnd['yearResult'], 0)
            && $close($bsEnd['equity']->firstWhere('account_code', '12')->balance ?? 0, $priorResultRaw), 'equity 12: ' . $fmt($bsEnd['equity']->firstWhere('account_code', '12')->balance ?? 0));
        $cfPrior = app(App\Services\Accounting\CashFlowService::class)->generateCashFlowStatement($prior->id);
        $cfResult = (float) (collect($cfPrior['operating_activities']['items'])->firstWhere('type', 'net_income')['amount'] ?? 0);
        check('the cash flow result for ' . $prior->name . ' still equals its income statement', $close($cfResult, $priorResultRaw) && $cfPrior['is_balanced'], 'cash flow result ' . $fmt($cfResult));

        // Un-posting is guarded.
        $msg = $refused(fn () => $closingEntry->fresh()->unpost());
        check('a system-written entry cannot be un-posted by hand', $msg !== null);
        $res = $call('POST', '/admin/journal-entries/' . $closingEntry->id . '/unpost');
        check('nor through the screen', (bool) $closingEntry->fresh()->is_posted);
        $manual = DB::table('journal_entries')->where('entry_number', 'like', 'JE-%')->where('is_system_generated', 0)->where('is_posted', 1)
            ->where('fiscal_year_id', $prior->id)->value('id');
        if ($manual) {
            $msg = $refused(fn () => JournalEntry::find($manual)->unpost());
            check('an entry in a closed year cannot be un-posted', $msg !== null, (string) $msg);
        }
        check('un-posting needs its own permission', DB::table('permissions')->where('name', 'journal-entry-unpost')->exists() && $admin->can('journal-entry-unpost'));

        // Reopen in order: the later year first.
        $msg = $refused(fn () => $current->fresh()->generateClosingEntries());
        check($year->name . ' can now be closed', $msg === null, (string) $msg);
        $current->fresh()->approve($admin->id);
        $msg = $refused(fn () => $priorClosing->fresh()->reverse('test'));
        check('reopening ' . $prior->name . ' is refused while ' . $year->name . ' is closed', $msg !== null, (string) $msg);
        $msg = $refused(fn () => $current->fresh()->reverse('test'));
        check($year->name . ' reopens', $msg === null && !$year->fresh()->is_closed, (string) $msg);
        $msg = $refused(fn () => $priorClosing->fresh()->reverse('test'));
        check('then ' . $prior->name . ' reopens', $msg === null && !$prior->fresh()->is_closed, (string) $msg);
        $reversal = JournalEntry::where('reversed_entry_id', $closingEntry->id)->first();
        check('by a posted reversal linked to the closing entry, which is marked reversed', $reversal && $reversal->is_posted && $closingEntry->fresh()->is_reversed
            && $reversal->reference_type === 'year_end_closing_reversal', $reversal ? $reversal->entry_number : 'no reversal');
        check('and its result is back in classes 6, 7 and 8', $close(-$sumWhere($raw($pTo, $pFrom), $isClass('6', '7', '8')), $priorResultRaw));
        check('the income statement for ' . $prior->name . ' is unchanged', $close($isPrior()['netProfit'], $priorResultRaw));
        $statementsAgree('after reopening');
    }

    section('9. One numbering series, by the entry\'s own year');
    $y = (int) date('Y') + 5;
    check('the first entry of an empty year is JE-' . $y . '-0001', JournalEntry::generateEntryNumber($y . '-03-01') === 'JE-' . $y . '-0001', JournalEntry::generateEntryNumber($y . '-03-01'));
    foreach (['9999', '10000'] as $n) {
        DB::table('journal_entries')->insert(['entry_number' => 'JE-' . $y . '-' . $n, 'entry_date' => $y . '-03-01', 'journal_type' => 'general', 'description' => 'TEST numbering',
            'total_debit' => 0, 'total_credit' => 0, 'is_posted' => 0, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
    }
    check('after JE-' . $y . '-10000 comes 10001, not a repeat (numbers are compared as numbers)', JournalEntry::generateEntryNumber($y . '-06-01') === 'JE-' . $y . '-10001', JournalEntry::generateEntryNumber($y . '-06-01'));
    check('a back-dated entry is numbered in its own year', str_starts_with(JournalEntry::generateEntryNumber(($y - 1) . '-12-31'), 'JE-' . ($y - 1) . '-'));
} catch (Throwable $e) {
    check('no exception', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    DB::rollBack();
}

section('Nothing left behind');
check('entries, lines and mappings are as they were', $counts() == $before, json_encode([$before, $counts()]));

echo "\naccounting_statements_test: passed $passed, failed $failed\n";
exit($failed ? 1 : 0);
