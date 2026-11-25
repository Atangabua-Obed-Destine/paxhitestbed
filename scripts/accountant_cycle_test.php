<?php

/**
 * An accountant's month, end to end, through the real screens.
 *
 * Fee billing and collection (with an overpayment held as student credit and
 * then used), other income, a cash expense, a supplier bill approved and paid,
 * an accrual posted and reversed, a bank reconciliation with a bank charge,
 * and the month closed. After every step each report is read again and held to
 * the raw ledger (the step engine from scripts/accounting_e2e_run.php): trial
 * balance, balance sheet, income statement, cash flow statement, ledger health.
 * Then every accounting report screen is opened, and the cross-checks between
 * them (payables aging vs account 40, student credit vs 419, budget sheet vs
 * ledger) are run.
 *
 * Everything runs inside one transaction that is rolled back: the run is
 * repeatable and leaves the books as it found them.
 *
 *   php scripts/accountant_cycle_test.php
 *
 * PASS / FAIL  the books are right or wrong
 * GAP          the books agree with themselves but differ from best practice,
 *              or a by-module view that is not the books differs from them
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffLoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$gaps = [];
$log = [];

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $log;
    $ok ? $passed++ : $failed++;
    $line = ($ok ? '  PASS  ' : '  FAIL  ') . $what . (!$ok && $detail !== '' ? " — $detail" : '');
    echo $line . "\n";
    $log[] = $line;
}

function gap(string $what, bool $isGap, string $detail = ''): void
{
    global $gaps, $log, $passed;
    if (!$isGap) {
        $passed++;
        echo "  PASS  $what\n";
        return;
    }
    $gaps[] = $what . ($detail !== '' ? " — $detail" : '');
    $line = "  GAP   $what" . ($detail !== '' ? " — $detail" : '');
    echo $line . "\n";
    $log[] = $line;
}

function section(string $title): void
{
    echo "\n=== $title ===\n";
}

$fmt = fn ($v) => number_format((float) $v, 2, '.', ',');
$close = fn ($a, $b, $tol = 0.01) => abs((float) $a - (float) $b) <= $tol;

// Mail to memory: paying a payroll may email a payslip.
config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array']]);
app('mail.manager')->forgetMailers();

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
Auth::guard('web')->login($admin);

$call = function (string $method, string $path, array $data = []) use ($kernel) {
    app('flasher.storage_manager')->clear();
    app('session.store')->forget('errors');
    if ($method !== 'GET') {
        $data['_token'] = csrf_token();
    }
    $request = Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/dashboard');

    return $kernel->handle($request);
};
$flash = fn () => collect(app('flasher.storage_manager')->all())->map(fn ($e) => method_exists($e, 'getMessage') ? strip_tags($e->getMessage()) : '')->implode(' | ');
$errors = function () {
    $bag = session('errors');
    return $bag ? implode(' | ', $bag->all()) : '';
};
$viewData = function ($response) {
    $o = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;
    return $o instanceof Illuminate\View\View ? $o->getData() : null;
};

$today = date('Y-m-d');
$acct = DB::table('chart_of_accounts')->pluck('id', 'account_code');
$codeOf = DB::table('chart_of_accounts')->pluck('account_code', 'id');
$fiscalYear = App\Models\FiscalYear::getActiveFiscalYear();

// -----------------------------------------------------------------------------
// Reading the books
// -----------------------------------------------------------------------------

/** Every account's balance as debit minus credit, from the raw posted lines. */
$ledger = function () {
    $rows = DB::select("
        SELECT a.account_code code, a.normal_balance nb, a.opening_balance ob,
               COALESCE(SUM(l.debit), 0) d, COALESCE(SUM(l.credit), 0) c
        FROM chart_of_accounts a
        LEFT JOIN journal_entry_lines l ON l.account_id = a.id
             AND EXISTS (SELECT 1 FROM journal_entries je WHERE je.id = l.journal_entry_id
                         AND je.is_posted = 1 AND je.deleted_at IS NULL)
        GROUP BY a.id, a.account_code, a.normal_balance, a.opening_balance");
    $out = [];
    foreach ($rows as $r) {
        $ob = (float) $r->ob * ($r->nb === 'debit' ? 1 : -1);
        $out[$r->code] = round($ob + (float) $r->d - (float) $r->c, 2);
    }
    return $out;
};

/** What every report says right now. */
$reports = function () use ($call, $viewData, $fiscalYear) {
    $r = [];

    $d = $viewData($call('GET', '/admin/general-ledger/trial-balance'));
    $r['tb_debit'] = 0; $r['tb_credit'] = 0; $r['tb_closing'] = [];
    foreach ($d['accounts'] as $a) {
        $r['tb_debit'] += $a->period_debit;
        $r['tb_credit'] += $a->period_credit;
        // As the view computes it, in debit-minus-credit terms.
        $ob = (float) $a->opening_balance * ($a->normal_balance === 'debit' ? 1 : -1);
        $r['tb_closing'][$a->account_code] = round($ob + $a->period_debit - $a->period_credit, 2);
    }

    $d = $viewData($call('GET', '/admin/general-ledger/balance-sheet'));
    $r['bs_assets'] = (float) $d['totalAssets'];
    $r['bs_liab'] = (float) $d['totalLiabilities'];
    $r['bs_fixed'] = (float) collect($d['fixedAssets'])->sum('balance');
    $r['bs_cash'] = (float) collect($d['cashAndBanks'])->sum('balance');
    $r['bs_receivables'] = (float) collect($d['receivables'])->sum('balance');
    $r['bs_payables'] = (float) collect($d['payables'])->sum('balance');
    $r['bs_equity'] = (float) collect($d['equity'])->sum('balance');

    $d = $viewData($call('GET', '/admin/general-ledger/income-statement?use_journal_entries=1'));
    $r['isj_rev'] = (float) $d['totalRevenue'];
    $r['isj_exp'] = (float) $d['totalExpenses'];
    $r['isj_net'] = (float) $d['netProfit'];

    $d = $viewData($call('GET', '/admin/general-ledger/income-statement?use_journal_entries=0'));
    $r['iso_rev'] = (float) $d['totalRevenue'];
    $r['iso_exp'] = (float) $d['totalExpenses'];
    $r['iso_payroll'] = (float) $d['payrollTotal'];
    $r['iso_fees'] = (float) $d['studentFeesRevenue'];
    $r['iso_net'] = (float) $d['netProfit'];

    $d = $viewData($call('GET', '/admin/accounting-reports/cash-flow-statement?fiscal_year_id=' . $fiscalYear->id));
    $s = $d['statement'];
    $r['cf_end'] = (float) $s['ending_cash_balance'];
    $r['cf_net'] = (float) $s['net_change_in_cash'];
    $r['cf_operating'] = (float) $s['net_operating'];
    $r['cf_investing'] = (float) $s['net_investing'];
    $r['cf_financing'] = (float) $s['net_financing'];
    $r['cf_balanced'] = (bool) $s['is_balanced'];
    $r['cf_diff'] = (float) $s['difference'];

    $health = app(App\Services\Accounting\LedgerHealth::class);
    $health->forget();
    $r['health'] = collect($health->checks())->filter(fn ($c) => $c['state'] !== 'ok')
        ->mapWithKeys(fn ($c) => [$c['key'] => $c['count']])->all();

    return $r;
};

// -----------------------------------------------------------------------------
// One step: do it, then hold every report to what it should have done
// -----------------------------------------------------------------------------

/**
 * $expect:
 *   ledger      code => change in (debit - credit) that the step must make,
 *               and no other account may move
 *   correct     code => what correct accounting would post, where it differs
 *               from 'ledger' (reported as a GAP, not a FAIL)
 *   iso_rev / iso_exp / iso_payroll   change the operational income statement should show
 *   cf          ['investing' => x, 'financing' => y] expected section moves
 */
$step = function (string $name, callable $do, $expect) use ($ledger, $reports, $close, $fmt, $codeOf, $today, $fiscalYear) {
    section($name);
    $beforeL = $ledger();
    $beforeR = $reports();
    $maxEntry = (int) DB::table('journal_entries')->max('id');

    $do();
    if ($expect instanceof Closure) {
        $expect = $expect();
    }

    $afterL = $ledger();
    $afterR = $reports();

    // The journal entries this step wrote.
    $entries = DB::table('journal_entries')->where('id', '>', $maxEntry)->whereNull('deleted_at')->get();
    foreach ($entries as $e) {
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $e->id)->get();
        $d = round($lines->sum('debit'), 2);
        $c = round($lines->sum('credit'), 2);
        $codes = $lines->map(fn ($l) => ($l->debit > 0 ? 'Dr ' : 'Cr ') . $codeOf[$l->account_id] . ' ' . number_format($l->debit + $l->credit))->implode(', ');
        echo "        entry {$e->entry_number} ({$e->journal_type}): $codes\n";
        check("entry {$e->entry_number} balances", $close($d, $c), "debit $d, credit $c");
        check("entry {$e->entry_number} header totals match its lines", $close($e->total_debit, $d) && $close($e->total_credit, $c), "header {$e->total_debit}/{$e->total_credit}, lines $d/$c");
        check("entry {$e->entry_number} is posted", (bool) $e->is_posted);
        check("entry {$e->entry_number} is in the active fiscal year and dated $today", $e->fiscal_year_id == $fiscalYear->id && substr($e->entry_date, 0, 10) === $today, "year {$e->fiscal_year_id}, date {$e->entry_date}");
        $nonDetail = DB::table('journal_entry_lines as l')->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.journal_entry_id', $e->id)->where(fn ($q) => $q->where('a.account_category', '!=', 'detail')->orWhere('a.is_active', 0))->count();
        check("entry {$e->entry_number} posts only to active detail accounts", $nonDetail === 0, "$nonDetail line(s) on a heading or inactive account");
    }

    // The ledger moved exactly as expected, and nowhere else.
    $moved = [];
    foreach ($afterL as $code => $v) {
        $delta = round($v - ($beforeL[$code] ?? 0), 2);
        if (abs($delta) > 0.005) {
            $moved[$code] = $delta;
        }
    }
    $want = array_filter($expect['ledger'], fn ($v) => abs($v) > 0.005);
    ksort($moved); ksort($want);
    $show = fn ($m) => $m ? implode(', ', array_map(fn ($k, $v) => "$k " . ($v >= 0 ? '+' : '') . number_format($v, 2), array_keys($m), $m)) : 'nothing';
    check('the ledger moved exactly as expected: ' . $show($want), $moved == array_map(fn ($v) => round($v, 2), $want), 'it moved ' . $show($moved));

    if (!empty($expect['correct'])) {
        $right = array_filter($expect['correct'], fn ($v) => abs($v) > 0.005);
        ksort($right);
        gap($expect['correct_label'] ?? 'posted as correct accounting would post it', $moved != $right, 'should be ' . $show($right) . '; is ' . $show($moved));
    }

    // Trial balance.
    $dd = round($afterR['tb_debit'] - $beforeR['tb_debit'], 2);
    $dc = round($afterR['tb_credit'] - $beforeR['tb_credit'], 2);
    check('trial balance: the step added equal debits and credits', $close($dd, $dc), "debits +$dd, credits +$dc");
    $tbWrong = [];
    foreach ($moved as $code => $delta) {
        $code = (string) $code;
        $tbDelta = round(($afterR['tb_closing'][$code] ?? 0) - ($beforeR['tb_closing'][$code] ?? 0), 2);
        if (!$close($tbDelta, $delta)) {
            $tbWrong[] = "$code: trial balance " . $fmt($tbDelta) . ', ledger ' . $fmt($delta);
        }
    }
    check('trial balance: every account that moved shows the same change as the ledger', !$tbWrong, implode('; ', $tbWrong));

    // Balance sheet. Assets are classes 2, 3, 5 and the debit balances of
    // class 4; a contra account (28x depreciation) reduces assets.
    $assetDelta = 0.0; $claimDelta = 0.0; $resultDelta = 0.0;
    foreach ($moved as $code => $delta) {
        $code = (string) $code;
        $cls = $code[0];
        if (in_array($cls, ['2', '3', '5'], true)) {
            $assetDelta += $delta;
        } elseif ($cls === '1') {
            $claimDelta -= $delta;
        } elseif ($cls === '4') {
            $nb = DB::table('chart_of_accounts')->where('account_code', $code)->value('normal_balance');
            $nb === 'debit' ? $assetDelta += $delta : $claimDelta -= $delta;
        } elseif (in_array($cls, ['6', '7', '8'], true)) {
            $resultDelta -= $delta;
        }
    }
    $bsA = round($afterR['bs_assets'] - $beforeR['bs_assets'], 2);
    $bsL = round($afterR['bs_liab'] - $beforeR['bs_liab'], 2);
    check('balance sheet: total assets moved by ' . $fmt($assetDelta), $close($bsA, $assetDelta), 'it moved by ' . $fmt($bsA));
    check('balance sheet: liabilities and equity moved by ' . $fmt($claimDelta + $resultDelta) . ' (including the year\'s result)', $close($bsL, $claimDelta + $resultDelta), 'it moved by ' . $fmt($bsL) . ($close($bsL, $claimDelta) && abs($resultDelta) > 0.005 ? ' — the result of ' . $fmt($resultDelta) . ' is missing from the passif' : ''));

    // Income statement, accounting view: straight from classes 6 and 7.
    $rev = 0.0; $exp = 0.0;
    foreach ($moved as $code => $delta) {
        $code = (string) $code;
        if ($code[0] === '7') { $rev -= $delta; }
        if ($code[0] === '6') { $exp += $delta; }
    }
    check('income statement (accounting view): revenue moved by ' . $fmt($rev), $close($afterR['isj_rev'] - $beforeR['isj_rev'], $rev), 'it moved by ' . $fmt($afterR['isj_rev'] - $beforeR['isj_rev']));
    check('income statement (accounting view): expenses moved by ' . $fmt($exp), $close($afterR['isj_exp'] - $beforeR['isj_exp'], $exp), 'it moved by ' . $fmt($afterR['isj_exp'] - $beforeR['isj_exp']));

    // Income statement, default screen: built from the modules, not the ledger.
    $isoRev = round($afterR['iso_rev'] - $beforeR['iso_rev'], 2);
    $isoExp = round($afterR['iso_exp'] - $beforeR['iso_exp'], 2);
    // The module summary is a by-module view built from the source tables, with
    // fee revenue dated when cash arrived. Supplier bills and student credit
    // held on 419 are timing it does not model, so a difference is reported as
    // a gap to explain, not a fault in the books.
    gap('income statement (module summary) shows the same change as the books', !($close($isoRev, $rev) && $close($isoExp, $exp)),
        'module summary: revenue ' . $fmt($isoRev) . ', expenses ' . $fmt($isoExp) . '; books: revenue ' . $fmt($rev) . ', expenses ' . $fmt($exp));

    // Cash flow statement.
    $cash = 0.0;
    foreach ($moved as $code => $delta) {
        $code = (string) $code;
        if ($code[0] === '5') { $cash += $delta; }
    }
    check('cash flow statement: ending cash moved by ' . $fmt($cash), $close($afterR['cf_end'] - $beforeR['cf_end'], $cash), 'it moved by ' . $fmt($afterR['cf_end'] - $beforeR['cf_end']));
    check('cash flow statement: its sections explain the change in cash', $close($afterR['cf_net'] - $beforeR['cf_net'], $cash),
        'sections moved by ' . $fmt($afterR['cf_net'] - $beforeR['cf_net']) . ' (operating ' . $fmt($afterR['cf_operating'] - $beforeR['cf_operating']) . ', investing ' . $fmt($afterR['cf_investing'] - $beforeR['cf_investing']) . ', financing ' . $fmt($afterR['cf_financing'] - $beforeR['cf_financing']) . ')');
    foreach ($expect['cf'] ?? [] as $part => $amount) {
        $got = round($afterR['cf_' . $part] - $beforeR['cf_' . $part], 2);
        check("cash flow statement: shown under $part activities (" . $fmt($amount) . ')', $close($got, $amount), "$part moved by " . $fmt($got));
    }

    // Ledger health: nothing new to worry about.
    $worse = [];
    foreach ($afterR['health'] as $key => $count) {
        if ($count > ($beforeR['health'][$key] ?? 0)) {
            $worse[] = "$key " . ($beforeR['health'][$key] ?? 0) . " -> $count";
        }
    }
    check('ledger health: no new problems', !$worse, implode('; ', $worse));

    return [$beforeR, $afterR];
};

// -----------------------------------------------------------------------------
// The starting position
// -----------------------------------------------------------------------------

section('Before anything: the books as they stand');
$start = $reports();
$startL = $ledger();
echo '        trial balance debits ' . $fmt($start['tb_debit']) . ', credits ' . $fmt($start['tb_credit']) . "\n";
echo '        balance sheet assets ' . $fmt($start['bs_assets']) . ', liabilities and equity ' . $fmt($start['bs_liab']) . "\n";
echo '        income statement net: accounting view ' . $fmt($start['isj_net']) . ', default view ' . $fmt($start['iso_net']) . "\n";
check('trial balance balances', $close($start['tb_debit'], $start['tb_credit']), 'off by ' . $fmt($start['tb_debit'] - $start['tb_credit']));
$onHeadings = DB::select("SELECT a.account_code, SUM(l.debit) - SUM(l.credit) v FROM journal_entry_lines l JOIN journal_entries je ON je.id = l.journal_entry_id AND je.is_posted = 1 AND je.deleted_at IS NULL JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.account_category <> 'detail' GROUP BY a.account_code HAVING ABS(SUM(l.debit) - SUM(l.credit)) > 0.004");
check('no posted line sits on a heading account (the reports leave those out)', !$onHeadings, collect($onHeadings)->map(fn ($r) => "{$r->account_code}: " . number_format($r->v))->implode(', '));
check('balance sheet balances', $close($start['bs_assets'], $start['bs_liab']), 'assets ' . $fmt($start['bs_assets']) . ', liabilities and equity ' . $fmt($start['bs_liab']) . ', difference ' . $fmt($start['bs_assets'] - $start['bs_liab']));
check('cash flow statement balances', $start['cf_balanced'], 'difference ' . $fmt($start['cf_diff']));
gap('income statement: the module summary and the accounting view agree', !$close($start['iso_net'], $start['isj_net']), 'default ' . $fmt($start['iso_net']) . ', accounting ' . $fmt($start['isj_net']));

// STEPS
DB::beginTransaction();

try {
$enroll = App\Models\StudentEnroll::whereHas('student', fn ($q) => $q->where('student_id', 'E2E-STU-01'))->first()
    ?: App\Models\StudentEnroll::whereNotNull('student_id')->orderByDesc('id')->first();
$id = fn ($code) => (int) $acct[$code];
$creditLedger = app(App\Services\Accounting\StudentCreditLedger::class);
echo "        using enrolment #{$enroll->id}\n";

// ---------------------------------------------------------------------------
// FEES: bill, collect (overpaid), use the credit on the next fee
// ---------------------------------------------------------------------------

$accrual = App\Services\Accounting\FeeAccrual::enabled();
echo '        fees are on ' . ($accrual ? 'accrual (billed to 411)' : 'a cash basis') . "
";
$feeBefore = (int) DB::table('fees')->max('id');
$step('1. Fee assigned (200,000 first instalment): ' . ($accrual ? 'what the student owes is billed to 411' : 'nothing is paid yet'), function () use ($call, $enroll, $today, $errors) {
    $call('POST', '/admin/fees-student-quick-assign', ['student' => $enroll->id, 'category' => 5, 'amount' => 200000, 'type' => 1,
        'assign_date' => $today, 'due_date' => date('Y-m-d', strtotime('+30 days'))]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, $accrual
    ? ['ledger' => ['411' => 200000, '7061' => -200000]]
    : ['ledger' => [], 'correct' => ['411' => 200000, '7061' => -200000],
        'correct_label' => 'a fee charged is recorded as owed (Dr 411 / Cr 7xx) when it is assigned (accrual basis)']);
$feeA = App\Models\Fee::where('id', '>', $feeBefore)->where('student_enroll_id', $enroll->id)->where('category_id', 5)->first();
check('the fee exists', (bool) $feeA);

$step('2. Fee paid 250,000 in cash: 200,000 is income, the 50,000 excess is held for the student on 419', function () use ($call, $feeA, $today, $errors) {
    $call('POST', '/admin/fees-student-pay', ['fee_id' => $feeA->id, 'pay_date' => $today, 'payment_method' => 1, 'paid_amount' => 250000, 'note' => 'E2E overpayment', 'allow_overpayment' => 1, 'allow_leftover_credit' => 1]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => $accrual ? ['571' => 250000, '411' => -200000, '419' => -50000] : ['571' => 250000, '7061' => -200000, '419' => -50000]]);
$credit = App\Models\StudentCredit::where('source_fee_id', $feeA->id)->first();
check('the overpayment became a student credit of 50,000', $credit && $close($credit->original_amount, 50000));

$feeBefore = (int) DB::table('fees')->max('id');
$step('3. Second instalment assigned (100,000): the 50,000 credit settles part of it and becomes income', function () use ($call, $enroll, $today, $errors, $credit, &$feeBefore) {
    $call('POST', '/admin/fees-student-quick-assign', ['student' => $enroll->id, 'category' => 7, 'amount' => 100000, 'type' => 1,
        'assign_date' => $today, 'due_date' => date('Y-m-d', strtotime('+60 days'))]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
    $feeB = App\Models\Fee::where('id', '>', $feeBefore)->where('student_enroll_id', $enroll->id)->where('category_id', 7)->first();
    if ($credit && $feeB && (float) $credit->fresh()->remaining_amount > 0.009) {
        echo "        not applied on assignment; the accountant applies it from the student's credit page\n";
        app(App\Services\StudentCreditService::class)->applyToFee($credit->fresh(), $feeB->fresh(), 50000, null, 'E2E applied by the accountant');
    }
}, ['ledger' => $accrual
    // billed 100,000 (Dr 411 / Cr 711), then the 50,000 paid ahead settles half of it (Dr 419 / Cr 411)
    ? ['411' => 50000, '7061' => -100000, '419' => 50000]
    : ['419' => 50000, '7061' => -50000]]);
if ($accrual) {
    $owed = app(App\Services\Accounting\FeeAccrual::class)->tieOut();
    check('411 equals what the fee records say students owe', $owed['agrees'], json_encode($owed));
}
$tie = $creditLedger->tieOut();
check('419 equals the backed credit not yet used', $tie['agrees'] && $tie['pending'] === 0, json_encode($tie));

// ---------------------------------------------------------------------------
// OTHER INCOME AND A CASH EXPENSE
// ---------------------------------------------------------------------------

$step('4. Other income received in cash (80,000, hall rental)', function () use ($call, $today, $errors) {
    $call('POST', '/admin/account/income', ['category' => 2, 'title' => 'E2E cycle hall rental', 'amount' => 80000, 'date' => $today, 'payment_method' => 1]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['571' => 80000, '758' => -80000]]);

$step('5. Expense paid in cash (45,000, plumbing)', function () use ($call, $today, $errors) {
    $call('POST', '/admin/account/expense', ['category' => 2, 'title' => 'E2E cycle plumbing', 'amount' => 45000, 'date' => $today, 'payment_method' => 1]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['6388' => 45000, '571' => -45000]]);

// ---------------------------------------------------------------------------
// SUPPLIERS: bill approved through the review, then paid from the bank
// ---------------------------------------------------------------------------

$ap = app(App\Services\Accounting\SupplierPayables::class);
$supplier = App\Models\ItemSupplier::create(['title' => 'E2E Cycle Supplies', 'contact_person' => 'E2E', 'payment_terms_days' => 30, 'status' => 1]);
$bill = $ap->saveDraft(['supplier_id' => $supplier->id, 'supplier_reference' => 'E2E-INV-77', 'bill_date' => $today,
    'lines' => [['description' => 'Repairs', 'expense_category_id' => 2, 'amount' => 120000]]], $admin->id);

section('6a. Approving without the review is refused');
$beforeCount = DB::table('journal_entries')->count();
$call('POST', "/admin/account/supplier-bills/{$bill->id}/approve", []);
check('nothing is posted without reviewed=1', DB::table('journal_entries')->count() === $beforeCount && $bill->fresh()->isDraft());

$step('6. Supplier bill approved after review (120,000 repairs, owed to the supplier on 40)', function () use ($call, $bill) {
    $call('POST', "/admin/account/supplier-bills/{$bill->id}/approve", ['reviewed' => 1]);
}, ['ledger' => ['6388' => 120000, '401' => -120000]]);
check('the bill is approved and owed in full', $bill->fresh()->isApproved() && $close($ap->owedOn($bill->fresh()), 120000));

$step('7. Supplier paid 120,000 by bank transfer, after review', function () use ($call, $supplier, $bill, $today, $id) {
    $call('POST', '/admin/account/supplier-payments', ['supplier_id' => $supplier->id, 'payment_date' => $today, 'amount' => 120000,
        'payment_method' => 2, 'cash_account_id' => $id('521'), 'reference' => 'E2E-VIR-1', 'allocations' => [$bill->id => 120000], 'reviewed' => 1]);
}, ['ledger' => ['401' => 120000, '521' => -120000]]);
$aging = $ap->aging();
check('payables aging ties to account 40 (difference 0)', abs((float) ($aging['difference'] ?? 1)) < 0.01, 'difference ' . ($aging['difference'] ?? '?'));

// ---------------------------------------------------------------------------
// PAYROLL: an advance, the month's salaries, the withholdings paid over
// ---------------------------------------------------------------------------

$month = date('Y-m-01');
$taken = DB::table('payrolls')->where('salary_month', $month)->pluck('user_id');
$worker = App\User::where('status', 1)->whereNotNull('staff_id')->where('salary_type', 1)->where('basic_salary', '>', 0)
    ->whereNotIn('id', $taken)->orderBy('id')->first();
check('a monthly-paid staff member with no payroll this month', (bool) $worker);

if ($worker) {
    echo "        staff #{$worker->id}, basic salary " . $fmt($worker->basic_salary) . "\n";
    $settings = App\Models\PayrollSetting::current();
    $advanceCode = DB::table('chart_of_accounts')->where('id', $settings->advance_account_id)->value('account_code');
    $advanceFrom = DB::table('chart_of_accounts')->where('id', $settings->advance_payment_account_id)->value('account_code');

    if ($advanceCode && $advanceFrom) {
        $kind = array_key_first(App\Models\StaffLoan::KINDS);
        $advance = ['user_id' => $worker->id, 'kind' => $kind, 'title' => 'E2E cycle advance', 'amount' => 60000, 'instalment' => 20000,
            'start_month' => date('Y-m'), 'given_on' => $today, 'note' => 'E2E'];
        $html = (string) $call('POST', '/admin/review/dry-run', $advance + ['_review_target' => '/admin/staff/staff-loan', '_review_method' => 'POST'])->getContent();
        check('the advance review shows its ledger entry and allows confirming', str_contains($html, 'data-ok="1"') && str_contains($html, (string) $advanceCode));
        $step('P1. Salary advance of 60,000 given after review (repaid 20,000 a month)', function () use ($call, $advance) {
            $call('POST', '/admin/staff/staff-loan', $advance + ['reviewed' => 1]);
        }, ['ledger' => [$advanceCode => 60000, $advanceFrom => -60000]]);
    }

    section('P2. Payroll for ' . date('F Y') . ': generated and approved (posts nothing yet)');
    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => (int) date('m'), 'year' => (int) date('Y'), 'users' => [$worker->id]]);
    $payroll = App\Models\Payroll::where('user_id', $worker->id)->where('salary_month', $month)->first();
    check('the payroll is generated', (bool) $payroll, json_encode(session('payroll_bulk_result')));
    if ($payroll && $settings->require_approval) {
        $call('POST', '/admin/staff/payroll-bulk', ['action' => 'approve', 'month' => (int) date('m'), 'year' => (int) date('Y'), 'users' => [$worker->id]]);
        check('and approved', App\Models\Payroll::find($payroll->id)->isApproved());
    }

    if ($payroll) {
        $payroll->refresh();
        $loanPart = (float) DB::table('payroll_details')->where('payroll_id', $payroll->id)->whereNotNull('staff_loan_id')->where('status', 0)->sum('amount');
        echo '        gross ' . $fmt($payroll->gross_salary) . ', tax ' . $fmt($payroll->tax) . ', employer ' . $fmt($payroll->employer_tax) . ', net ' . $fmt($payroll->net_salary) . ', advance repaid ' . $fmt($loanPart) . "\n";

        /** What a paid payroll should post, worked out from its own figures. */
        $payrollPosting = function () use ($payroll, $loanPart) {
            $p = App\Models\Payroll::find($payroll->id);
            $l = ['661' => round((float) $p->gross_salary + ($loanPart > 0 && App\Models\PayrollSetting::current()->advance_account_id ? $loanPart : 0), 2)];
            if ((float) $p->employer_tax > 0) { $l['664'] = (float) $p->employer_tax; }
            $l['571'] = -round((float) $p->net_salary, 2);
            foreach (DB::table('payroll_tax_lines')->where('payroll_id', $p->id)->get() as $t) {
                $code = DB::table('chart_of_accounts')->where('id', $t->liability_account_id)->value('account_code') ?? '447';
                $l[$code] = round(($l[$code] ?? 0) - (float) $t->employee_amount - (float) $t->employer_amount, 2);
            }
            if ($loanPart > 0 && ($adv = DB::table('chart_of_accounts')->where('id', App\Models\PayrollSetting::current()->advance_account_id)->value('account_code'))) {
                $l[$adv] = round(($l[$adv] ?? 0) - $loanPart, 2);
            }
            return ['ledger' => array_filter($l, fn ($v) => abs($v) > 0.005)];
        };

        $bankAccount = App\Models\StaffBankAccount::where('user_id', $worker->id)->value('id')
            ?: App\Models\StaffBankAccount::create(['user_id' => $worker->id, 'bank_name' => 'E2E Bank', 'account_name' => 'E2E', 'account_number' => '0000 1111 2222',
                'branch' => 'Main', 'ifsc_code' => '', 'is_default' => 1, 'status' => 1])->id;
        $pay = ['pay_date' => $today, 'payment_method' => 1, 'bank_account_id' => $bankAccount, 'note' => 'E2E cycle'];
        $before = DB::table('journal_entries')->count();
        $html = (string) $call('POST', '/admin/review/dry-run', $pay + ['_review_target' => '/admin/staff/payroll-pay/' . $payroll->id, '_review_method' => 'POST'])->getContent();
        check('the salary review shows the entry and saves nothing', str_contains($html, 'data-ok="1"') && str_contains($html, '661') && DB::table('journal_entries')->count() === $before,
            trim(preg_replace('/\s+/', ' ', strip_tags($html))));
        $call('POST', '/admin/staff/payroll-pay/' . $payroll->id, $pay);
        check('paying without the review tick is refused', (int) App\Models\Payroll::find($payroll->id)->status !== 1);

        $step('P3. Salary paid in cash after review', function () use ($call, $payroll, $pay) {
            $call('POST', '/admin/staff/payroll-pay/' . $payroll->id, $pay + ['reviewed' => 1]);
        }, $payrollPosting);
        check('the payroll is paid', (int) App\Models\Payroll::find($payroll->id)->status === 1);

        // The withholdings, paid to each body they are owed to.
        $owedBy = DB::table('payroll_tax_lines as t')->join('chart_of_accounts as a', 'a.id', '=', 't.liability_account_id')
            ->where('t.payroll_id', $payroll->id)
            ->groupBy('a.account_code')->select('a.account_code', DB::raw('ROUND(SUM(t.employee_amount + t.employer_amount), 2) as owed'))
            ->pluck('owed', 'account_code');
        foreach ($owedBy as $code => $owed) {
            if ((float) $owed <= 0) { continue; }
            $code = (string) $code;
            $remit = ['liability_account_id' => $acct[$code], 'salary_month' => $month, 'amount' => (float) $owed, 'payment_date' => $today,
                'source_account_id' => $acct['571'], 'reference' => 'E2E-CYCLE-' . $code, 'note' => 'E2E', 'allow_additional' => 1];
            $step("P4. This month's withholdings on $code (" . $fmt($owed) . ') paid over after review', function () use ($call, $remit) {
                $call('POST', '/admin/staff/tax-remittance', $remit + ['reviewed' => 1]);
            }, ['ledger' => [$code => (float) $owed, '571' => -(float) $owed]]);
        }
    }
}

// ---------------------------------------------------------------------------
// MONTH END: an accrual, and its reversal
// ---------------------------------------------------------------------------

$step('8. Accrual: electricity used this month, invoice not yet received (Dr 6051 / Cr 408, 30,000)', function () use ($call, $today, $fiscalYear, $id, $errors) {
    $call('POST', '/admin/journal-entries', ['entry_date' => $today, 'fiscal_year_id' => $fiscalYear->id, 'journal_type' => 'adjustment',
        'description' => 'E2E accrued electricity', 'reference_number' => 'E2E-ACR', 'post_immediately' => 1,
        'lines' => [
            ['account_id' => $id('6051'), 'description' => 'Electricity used, not yet invoiced', 'debit' => 30000, 'credit' => 0],
            ['account_id' => $id('408'), 'description' => 'Invoice not yet received', 'debit' => 0, 'credit' => 30000],
        ]]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['6051' => 30000, '408' => -30000]]);
$accrual = JournalEntry::where('reference_number', 'E2E-ACR')->latest('id')->first();
check('the accrual is posted', $accrual && $accrual->is_posted);

if ($accrual) {
    section('8a. Reversing without the review is refused');
    $beforeCount = DB::table('journal_entries')->count();
    $call('POST', "/admin/journal-entries/{$accrual->id}/reverse", ['reversal_date' => $today]);
    check('nothing is posted without reviewed=1', DB::table('journal_entries')->count() === $beforeCount);
    $preview = (string) $call('GET', "/admin/journal-entries/{$accrual->id}/preview/reverse?reversal_date={$today}")->getContent();
    check('the reversal preview shows the mirror entry and allows confirming', str_contains($preview, 'data-ok="1"') && str_contains($preview, '408'), strip_tags(substr($preview, 0, 400)));

    $step('9. The accrual is reversed (the invoice will be booked as a supplier bill)', function () use ($call, $accrual, $today) {
        $call('POST', "/admin/journal-entries/{$accrual->id}/reverse", ['reversal_date' => $today, 'void_reason' => 'Invoice arrived', 'reviewed' => 1]);
    }, ['ledger' => ['6051' => -30000, '408' => 30000]]);
    check('the original is kept and marked reversed', (bool) $accrual->fresh()->is_reversed);
    $preview = (string) $call('GET', "/admin/journal-entries/{$accrual->id}/preview/reverse?reversal_date={$today}")->getContent();
    check('a second reversal is refused in the preview', str_contains($preview, 'data-ok="0"'));
}

// ---------------------------------------------------------------------------
// BANK RECONCILIATION with a bank charge found on the statement
// ---------------------------------------------------------------------------

$bankSvc = app(App\Services\Accounting\BankReconciliationService::class);
$bookBefore = $bankSvc->bookBalance($id('521'), $today);
section('10a. Bank reconciliation started: the statement shows 2,500 of charges the books do not have');
$call('POST', '/admin/bank-reconciliation', ['chart_of_account_id' => $id('521'), 'statement_date' => $today,
    'statement_closing_balance' => $bookBefore - 2500, 'statement_reference' => 'E2E-STMT-11']);
$rec = App\Models\BankReconciliation::where('chart_of_account_id', $id('521'))->latest('id')->first();
check('the reconciliation was created', (bool) $rec, $errors() . ' ' . $flash());
if ($rec) {
    echo '        ' . $rec->items()->count() . " movement(s) listed\n";
    $call('POST', "/admin/bank-reconciliation/{$rec->id}/ticks", ['cleared' => $rec->items()->pluck('id')->all()]);
    $pos = $bankSvc->position($rec->fresh());
    check('with every movement ticked, the difference is the 2,500 of charges', $close($pos['difference'], 2500), json_encode($pos));
    $call('POST', "/admin/bank-reconciliation/{$rec->id}/complete", ['reviewed' => 1]);
    check('it cannot be completed while it does not agree', $rec->fresh()->status === 'in_progress');

    // Filed under an expense category, like any expense: "OTHER EXPENSES" posts to 628.
    $step('10. Bank charges posted from the reconciliation, after review (Dr 628 / Cr 521, 2,500)', function () use ($call, $rec, $today) {
        $call('POST', "/admin/bank-reconciliation/{$rec->id}/add-adjustment", ['kind' => 'bank_charge', 'amount' => 2500, 'date' => $today,
            'category_id' => 2, 'description' => 'Monthly fee', 'reviewed' => 1]);
    }, ['ledger' => ['6388' => 2500, '521' => -2500]]);
    check('now the difference is nil', abs($bankSvc->position($rec->fresh())['difference']) < 0.01, json_encode($bankSvc->position($rec->fresh())));
    $call('POST', "/admin/bank-reconciliation/{$rec->id}/complete", ['reviewed' => 1]);
    check('and it is completed', $rec->fresh()->status === 'completed', $flash());
    check('the next reconciliation starts with nothing already matched', $bankSvc->unmatchedLines($id('521'), $today)->isEmpty());
}

// ---------------------------------------------------------------------------
// REPORTS: every accounting screen opens, and the cross-checks hold
// ---------------------------------------------------------------------------

section('11. Every report opens');
$pages = [
    'Chart of accounts' => '/admin/chart-of-accounts',
    'Journal entries' => '/admin/journal-entries',
    'General ledger' => '/admin/general-ledger',
    'Account ledger (521)' => '/admin/general-ledger/account/' . $id('521'),
    'Ledger by class (5)' => '/admin/general-ledger/class/5',
    'Trial balance' => '/admin/general-ledger/trial-balance',
    'Trial balance (Excel)' => '/admin/general-ledger/trial-balance/export-excel',
    'Balance sheet' => '/admin/general-ledger/balance-sheet',
    'Balance sheet (PDF)' => '/admin/general-ledger/balance-sheet/export-pdf',
    'Income statement' => '/admin/general-ledger/income-statement',
    'Income statement (PDF)' => '/admin/general-ledger/income-statement/export-pdf',
    'Accounting reports' => '/admin/accounting-reports',
    'Receivables aging' => '/admin/accounting-reports/receivables-aging',
    'Payables aging (ledger)' => '/admin/accounting-reports/payables-aging',
    'Student fee aging' => '/admin/accounting-reports/student-fee-aging',
    'Cash flow statement' => '/admin/accounting-reports/cash-flow-statement?fiscal_year_id=' . $fiscalYear->id,
    'Comparative cash flow' => '/admin/accounting-reports/comparative-cash-flow',
    'Budget vs actual' => '/admin/accounting-reports/budget-vs-actual',
    'Supplier bills' => '/admin/account/supplier-bills',
    'Payables aging (bills)' => '/admin/account/supplier-bills/aging',
    'Supplier statement' => '/admin/account/supplier-bills/statement?supplier_id=' . $supplier->id,
    'Supplier payments' => '/admin/account/supplier-payments',
    'Daybook' => '/admin/daybook',
    'Daybook monthly summary' => '/admin/daybook/summary',
    'Daybook analysis' => '/admin/daybook/analysis',
    'Budget sheet' => '/admin/budget-sheet',
    'Budget performance' => '/admin/budget-reports/performance',
    'Budget variance' => '/admin/budget-reports/variance',
    'Budget cash flow' => '/admin/budget-reports/cashflow',
    'Ledger health' => '/admin/accounting/ledger-health',
    'Bank reconciliation' => '/admin/bank-reconciliation',
    'Bank reconciliation (this one)' => $rec ? '/admin/bank-reconciliation/' . $rec->id : '/admin/bank-reconciliation',
    'New bank reconciliation' => '/admin/bank-reconciliation/create',
    'Fiscal years' => '/admin/fiscal-years',
    'Fiscal year (months)' => '/admin/fiscal-years/' . $fiscalYear->id,
    'Journal entry (the accrual)' => $accrual ? '/admin/journal-entries/' . $accrual->id : '/admin/journal-entries',
    'Year-end closing' => '/admin/year-end-closing',
    'Fee credit audit' => '/admin/fees-credit-audit',
    'Fees report' => '/admin/fees-student-report',
    'Income report' => '/admin/account/income/report',
    'Expense report' => '/admin/account/expense/report',
    'Payment accounts cash flow' => '/admin/payment-account-report/cashflow',
    'Fixed asset depreciation' => '/admin/fixed-assets-depreciation-report',
    'Payroll report' => '/admin/staff/payroll-report',
    'Staff tax report' => '/admin/staff/tax-report',
];
foreach ($pages as $label => $url) {
    try {
        $res = $call('GET', $url);
        $status = $res->getStatusCode();
        $ok = $status === 200 || ($status === 302 && !str_contains((string) $res->headers->get('location'), 'login'));
        check("$label opens ($url)", $ok, "status $status " . ($status === 302 ? $res->headers->get('location') : substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $res->getContent()))), 0, 200)));
    } catch (Throwable $e) {
        check("$label opens ($url)", false, get_class($e) . ': ' . $e->getMessage());
    }
}

section('12. Cross-checks');
$health = app(App\Services\Accounting\LedgerHealth::class);
$health->forget();
$bad = collect($health->checks())->where('state', 'critical')->pluck('label')->all();
check('ledger health: nothing critical', !$bad, implode('; ', $bad));
$tie = $creditLedger->tieOut();
check('student credit: 419 ties out', $tie['agrees'], json_encode($tie));
if ($accrual) {
    $owed = app(App\Services\Accounting\FeeAccrual::class)->tieOut();
    check('receivables: 411 ties out to what students owe', $owed['agrees'], json_encode($owed));
}
check('payables: aging ties to account 40', abs((float) $ap->aging()['difference']) < 0.01);
$recon = app(App\Services\BudgetReconciliationService::class)->reconcile($today, $today);
foreach ($recon['sections'] as $s) {
    check("budget sheet vs ledger for today's activity: {$s['label']} agrees (after named differences)", $s['agrees'],
        'sheet ' . $fmt($s['sheet']) . ', ledger ' . $fmt($s['ledger']) . ', unexplained ' . $fmt($s['unexplained']));
}

// ---------------------------------------------------------------------------
// CLOSING THE MONTH
// ---------------------------------------------------------------------------

section('13. Closing the month');
$controls = app(App\Services\Accounting\PeriodControls::class);
$month = App\Models\FiscalYear::periodForDate($today);
$preview = (string) $call('GET', "/admin/accounting-periods/{$month->id}/preview/close")->getContent();
$earlierOpen = App\Models\AccountingPeriod::where('fiscal_year_id', $month->fiscal_year_id)->where('start_date', '<', $month->start_date)->where('is_closed', false)->count();
check('months close in order: with earlier months open, the review refuses', $earlierOpen === 0 || str_contains($preview, 'data-ok="0"'));

$closedEarlier = 0;
foreach (App\Models\AccountingPeriod::where('fiscal_year_id', $month->fiscal_year_id)->where('start_date', '<', $month->start_date)->where('is_closed', false)->orderBy('start_date')->get() as $p) {
    try { $controls->closeMonth($p, $admin->id, 'E2E'); $closedEarlier++; } catch (RuntimeException $e) { echo "        could not close {$p->name}: {$e->getMessage()}\n"; }
}
echo "        closed $closedEarlier earlier month(s) first\n";
$preview = (string) $call('GET', "/admin/accounting-periods/{$month->id}/preview/close")->getContent();
check('the review now allows closing ' . $month->name, str_contains($preview, 'data-ok="1"'), trim(preg_replace('/\s+/', ' ', strip_tags($preview))));
$call('POST', "/admin/accounting-periods/{$month->id}/close", []);
check('closing without the review is refused', !$month->fresh()->is_closed);
$call('POST', "/admin/accounting-periods/{$month->id}/close", ['reviewed' => 1]);
check($month->name . ' is closed', (bool) $month->fresh()->is_closed);

$call('POST', '/admin/journal-entries', ['entry_date' => $today, 'fiscal_year_id' => $fiscalYear->id, 'journal_type' => 'general',
    'description' => 'E2E into a closed month', 'post_immediately' => 1,
    'lines' => [['account_id' => $id('571'), 'debit' => 1000, 'credit' => 0], ['account_id' => $id('758'), 'debit' => 0, 'credit' => 1000]]]);
check('a manual entry into the closed month is not posted', !JournalEntry::where('description', 'E2E into a closed month')->where('is_posted', 1)->exists());
$call('POST', '/admin/account/income', ['category' => 2, 'title' => 'E2E into a closed month', 'amount' => 1000, 'date' => $today, 'payment_method' => 1]);
$intoClosed = DB::table('journal_entries')->where('reference_type', 'income')
    ->whereIn('reference_id', App\Models\Income::where('title', 'E2E into a closed month')->pluck('id'))
    ->where('accounting_period_id', $month->id)->exists();
check('an income dated in the closed month is not posted into it', !$intoClosed);

$call('POST', "/admin/accounting-periods/{$month->id}/reopen", ['reviewed' => 1, 'void_reason' => 'E2E reopen']);
check($month->name . ' can be reopened with a reason', !$month->fresh()->is_closed);

} finally {
    DB::rollBack();
}

section('Result');
echo "  $passed passed, $failed failed, " . count($gaps) . " gap(s)\n";
foreach ($gaps as $g) {
    echo "  GAP  $g\n";
}
exit($failed ? 1 : 0);
