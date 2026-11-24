<?php

/**
 * End-to-end run of the accounting module on the testbed, with REAL, committed
 * transactions. Nothing is rolled back: every record made here stays, and is
 * labelled "E2E" so it can be found again.
 *
 * Each step goes through the real screens (routes), then every report is read
 * again and compared with what the step should have done to it. The expected
 * figures are written out by hand for each step and checked against the raw
 * ledger, not read back from the reports under test.
 *
 * Results are of two kinds:
 *   PASS / FAIL  the books are internally right or wrong (a report disagrees
 *                with the ledger, an entry does not balance, a total is off)
 *   GAP          the books agree with themselves, but not with how the
 *                transaction should be recorded under OHADA / for the school
 *
 * Run once. A second run makes a second set of records.
 *
 *   php scripts/accounting_e2e_run.php
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
    check('income statement (module summary) shows the same change as the books', $close($isoRev, $rev) && $close($isoExp, $exp),
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
check('income statement: the module summary and the accounting view agree', $close($start['iso_net'], $start['isj_net']), 'default ' . $fmt($start['iso_net']) . ', accounting ' . $fmt($start['isj_net']));

// -----------------------------------------------------------------------------
// Setup: a student, two staff, and the payroll settings loans need
// -----------------------------------------------------------------------------

section('Setup: an E2E student and two E2E staff');
$student = App\Models\Student::where('student_id', 'E2E-STU-01')->first();
if (!$student) {
    $student = App\Models\Student::query()->orderBy('id')->firstOrFail()->replicate();
    $student->student_id = 'E2E-STU-01';
    $student->first_name = 'E2E';
    $student->last_name = 'Test Student';
    $student->email = 'e2e.student@example.invalid';
    $student->phone = '600000001';
    $student->password = bcrypt(Illuminate\Support\Str::random(32));
    foreach (['password_text', 'photo', 'signature', 'registration_no'] as $col) {
        if (Illuminate\Support\Facades\Schema::hasColumn('students', $col)) {
            $student->{$col} = $col === 'registration_no' ? 'E2E-STU-01' : null;
        }
    }
    $student->save();
}
$enroll = App\Models\StudentEnroll::where('student_id', $student->id)->first();
if (!$enroll) {
    $enroll = App\Models\StudentEnroll::where('status', 1)->where('program_id', 9)->orderByDesc('id')->firstOrFail()->replicate();
    $enroll->student_id = $student->id;
    $enroll->save();
}
check('E2E student and enrolment exist', $student->exists && $enroll->exists, '');
echo "        student #{$student->id}, enrolment #{$enroll->id}\n";

$makeStaff = function ($staffId, $last, $salary) {
    $u = App\User::where('staff_id', $staffId)->first();
    if ($u) {
        return $u;
    }
    $u = App\User::findOrFail(19)->replicate();
    $u->staff_id = $staffId;
    $u->first_name = 'E2E';
    $u->last_name = $last;
    $u->email = strtolower(str_replace(' ', '.', $last)) . '.e2e@example.invalid';
    $u->phone = '600000002';
    $u->photo = null;
    $u->signature = null;
    $u->password = bcrypt(Illuminate\Support\Str::random(32));
    $u->password_text = null;
    $u->two_factor_enabled = 0;
    $u->two_factor_enabled_at = null;
    $u->login = 0;
    $u->last_seen_at = null;
    $u->salary_type = 1;
    $u->basic_salary = $salary;
    $u->contract_type = 1;
    $u->ending_date = null;
    $u->save();
    App\Models\StaffBankAccount::create([
        'user_id' => $u->id, 'bank_name' => 'E2E Bank', 'account_name' => "E2E $last",
        'account_number' => '0000 9999 0000', 'branch' => 'Main', 'ifsc_code' => '', 'is_default' => 1, 'status' => 1,
    ]);
    return $u;
};
$one = $makeStaff('E2E-STF-01', 'Staff One', 300000);
$two = $makeStaff('E2E-STF-02', 'Staff Two', 180000);
$twoBank = App\Models\StaffBankAccount::where('user_id', $two->id)->value('id');
check('E2E staff exist', $one->exists && $two->exists);
echo "        staff #{$one->id} (300,000), #{$two->id} (180,000)\n";

// Loans need the two accounts on the Payroll Settings page. The testbed's
// staff-advance account is 412 "Avances au personnel et tiers"; loans are
// paid out of 571 Caisse Principale.
$settings = PayrollSetting::current();
$originalSettings = ['require_approval' => false, 'advance_account_id' => null, 'advance_payment_account_id' => null];
$form = [
    'require_approval' => 1, 'carry_over_lines' => (int) $settings->carry_over_lines,
    'prorate_partial_months' => (int) $settings->prorate_partial_months, 'proration_basis' => $settings->proration_basis,
    'staff_payslips_enabled' => (int) $settings->staff_payslips_enabled, 'email_payslip_on_pay' => 0,
    'bank_file_columns' => $settings->bankColumns(), 'bank_file_delimiter' => $settings->bank_file_delimiter,
    'bank_file_header' => (int) $settings->bank_file_header, 'bank_file_reference' => $settings->bank_file_reference,
    'bank_file_decimals' => (int) $settings->bank_file_decimals,
    'advance_account_id' => $acct['412'], 'advance_payment_account_id' => $acct['571'],
];
$call('POST', '/admin/staff/payroll-setting', $form);
PayrollSetting::flush();
check('payroll settings saved: approval on, loans booked to 412 from 571', PayrollSetting::current()->advance_account_id == $acct['412'] && PayrollSetting::current()->require_approval, $errors() . ' ' . $flash());

// -----------------------------------------------------------------------------
// The transactions
// -----------------------------------------------------------------------------

if (DB::table('journal_entries')->where('reference_number', 'E2E-CAP')->whereNull('deleted_at')->exists()) {
    section('1. Capital paid into the bank: already recorded by an earlier run (JE-2026-0001), not repeated');
} else $step('1. Capital paid into the bank (manual journal entry, 2,000,000)', function () use ($call, $acct, $today, $fiscalYear, $errors) {
    $call('POST', '/admin/journal-entries', [
        'entry_date' => $today, 'fiscal_year_id' => $fiscalYear->id, 'journal_type' => 'opening',
        'description' => 'E2E capital contribution', 'reference_number' => 'E2E-CAP', 'post_immediately' => 1,
        'lines' => [
            ['account_id' => $acct['521'], 'description' => 'E2E capital into bank', 'debit' => 2000000, 'credit' => 0],
            ['account_id' => $acct['101'], 'description' => 'E2E capital', 'debit' => 0, 'credit' => 2000000],
        ],
    ]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['521' => 2000000, '101' => -2000000], 'iso_rev' => 0, 'iso_exp' => 0, 'cf' => ['financing' => 2000000]]);

section('Refusals: a manual entry that does not balance, and one on a heading account');
$countBefore = DB::table('journal_entries')->count();
$call('POST', '/admin/journal-entries', [
    'entry_date' => $today, 'fiscal_year_id' => $fiscalYear->id, 'journal_type' => 'general', 'description' => 'E2E unbalanced', 'post_immediately' => 1,
    'lines' => [['account_id' => $acct['571'], 'debit' => 1000, 'credit' => 0], ['account_id' => $acct['758'], 'debit' => 0, 'credit' => 900]],
]);
check('an unbalanced entry is refused', DB::table('journal_entries')->count() === $countBefore, 'it was saved');
$earlierHeading = DB::table('journal_entries')->where('description', 'E2E heading account')->first();
if ($earlierHeading) {
    check('an entry on a heading account (24 Materiel) is refused', false, 'an earlier run saved it as ' . $earlierHeading->entry_number . ' (then unposted and deleted it); not repeated');
} else {
$call('POST', '/admin/journal-entries', [
    'entry_date' => $today, 'fiscal_year_id' => $fiscalYear->id, 'journal_type' => 'general', 'description' => 'E2E heading account', 'post_immediately' => 1,
    'lines' => [['account_id' => $acct['24'], 'debit' => 1000, 'credit' => 0], ['account_id' => $acct['571'], 'debit' => 0, 'credit' => 1000]],
]);
$headingEntry = DB::table('journal_entries')->where('description', 'E2E heading account')->whereNull('deleted_at')->first();
check('an entry on a heading account (24 Materiel) is refused', !$headingEntry, 'it was saved as ' . ($headingEntry->entry_number ?? ''));
if ($headingEntry) {
    // Take it back out so it does not distort the steps that follow.
    JournalEntry::find($headingEntry->id)?->unpost($admin->id);
    JournalEntry::find($headingEntry->id)?->delete();
}
}

$existingFee = App\Models\Fee::where('student_enroll_id', $enroll->id)->first();
if ($existingFee) {
    section('2-4. Fee assigned and paid: already done by an earlier run, not repeated');
    $fee = $existingFee;
} else {
$feeBefore = (int) DB::table('fees')->max('id');
$step('2. Fee assigned to the E2E student (150,000 first instalment): nothing is paid yet', function () use ($call, $enroll, $today, $errors) {
    $call('POST', '/admin/fees-student-quick-assign', [
        'student' => $enroll->id, 'category' => 5, 'amount' => 150000, 'type' => 1,
        'assign_date' => $today, 'due_date' => date('Y-m-d', strtotime('+30 days')),
    ]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => [], 'iso_rev' => 0, 'iso_exp' => 0,
    'correct' => ['411' => 150000, '711' => -150000],
    'correct_label' => 'a fee charged is recorded as owed (Dr 411 / Cr 7xx) when it is assigned']);
$fee = App\Models\Fee::where('id', '>', $feeBefore)->where('student_enroll_id', $enroll->id)->first();
check('the fee exists', (bool) $fee);

$step('3. Fee part-paid in cash (100,000)', function () use ($call, $fee, $today, $flash, $errors) {
    $call('POST', '/admin/fees-student-pay', ['fee_id' => $fee->id, 'pay_date' => $today, 'payment_method' => 1, 'paid_amount' => 100000, 'note' => 'E2E part payment']);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['571' => 100000, '711' => -100000], 'iso_rev' => 100000, 'iso_exp' => 0]);

$step('4. Fee balance paid by bank transfer (50,000)', function () use ($call, $fee, $today, $flash, $errors) {
    $call('POST', '/admin/fees-student-pay', ['fee_id' => $fee->id, 'pay_date' => $today, 'payment_method' => 2, 'paid_amount' => 50000, 'note' => 'E2E bank payment']);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['571' => 50000, '711' => -50000], 'iso_rev' => 50000, 'iso_exp' => 0,
    'correct' => ['521' => 50000, '711' => -50000],
    'correct_label' => 'money paid by bank transfer lands in the bank account (521), not the cash box']);
$fee->refresh();
check('the fee is now fully paid', (int) $fee->status === 1 && $close($fee->paid_amount, 150000), "status {$fee->status}, paid {$fee->paid_amount}");
}

if (App\Models\Income::where('title', 'E2E hall rental')->exists()) {
    section('5. Other income: already done by an earlier run, not repeated');
} else $step('5. Other income received in cash (250,000, "Produits divers")', function () use ($call, $today, $errors) {
    $call('POST', '/admin/account/income', ['category' => 2, 'title' => 'E2E hall rental', 'amount' => 250000, 'date' => $today, 'payment_method' => 1, 'reference' => 'E2E-INC']);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['571' => 250000, '758' => -250000], 'iso_rev' => 250000, 'iso_exp' => 0]);

if (App\Models\Expense::where('title', 'E2E projector')->exists()) {
    section('6. Expense: already done by an earlier run, not repeated');
} else $step('6. Expense paid in cash (120,000, other external services)', function () use ($call, $today, $errors) {
    $call('POST', '/admin/account/expense', ['category' => 2, 'title' => 'E2E plumbing', 'amount' => 120000, 'date' => $today, 'payment_method' => 1, 'reference' => 'E2E-EXP']);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['628' => 120000, '571' => -120000], 'iso_rev' => 0, 'iso_exp' => 120000]);
$expense = App\Models\Expense::where('title', 'E2E plumbing')->latest('id')->first();

if (App\Models\Expense::where('title', 'E2E projector')->exists()) {
    section('7. Equipment: already done by an earlier run, not repeated');
} else $step('7. Equipment bought in cash (300,000, capital expenditure)', function () use ($call, $today, $errors) {
    $call('POST', '/admin/account/expense', ['category' => 20, 'title' => 'E2E projector', 'amount' => 300000, 'date' => $today, 'payment_method' => 1, 'reference' => 'E2E-CAPEX']);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['241' => 300000, '571' => -300000], 'iso_rev' => 0, 'iso_exp' => 0, 'cf' => ['investing' => -300000]]);

if (DB::table('journal_entries')->where('reference_number', 'E2E-DEP')->whereNull('deleted_at')->exists()) {
    section('8. Depreciation: already done by an earlier run, not repeated');
} else $step('8. Depreciation for the year (manual journal entry, 25,000)', function () use ($call, $acct, $today, $fiscalYear, $errors) {
    $call('POST', '/admin/journal-entries', [
        'entry_date' => $today, 'fiscal_year_id' => $fiscalYear->id, 'journal_type' => 'adjustment',
        'description' => 'E2E depreciation of equipment', 'reference_number' => 'E2E-DEP', 'post_immediately' => 1,
        'lines' => [
            ['account_id' => $acct['681'], 'description' => 'E2E depreciation', 'debit' => 25000, 'credit' => 0],
            ['account_id' => $acct['28'], 'description' => 'E2E accumulated depreciation', 'debit' => 0, 'credit' => 25000],
        ],
    ]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['681' => 25000, '28' => -25000], 'iso_rev' => 0, 'iso_exp' => 25000]);

if (StaffLoan::where('user_id', $one->id)->where('title', 'E2E salary advance')->exists()) {
    section('9. Salary advance: already done by an earlier run, not repeated');
} else $step('9. Salary advance given to E2E Staff One in cash (60,000, repaid 20,000 a month)', function () use ($call, $one, $today, $errors, $flash) {
    $call('POST', '/admin/staff/staff-loan', ['user_id' => $one->id, 'kind' => 'advance', 'title' => 'E2E salary advance', 'amount' => 60000, 'instalment' => 20000, 'start_month' => date('Y-m'), 'given_on' => $today]);
    if ($e = $errors()) { echo "        refused: $e\n"; }
}, ['ledger' => ['412' => 60000, '571' => -60000], 'iso_rev' => 0, 'iso_exp' => 0]);
$loan = StaffLoan::where('user_id', $one->id)->latest('id')->first();
check('the advance exists, with 60,000 to repay', $loan && $close($loan->balance(), 60000), $loan ? 'balance ' . $loan->balance() : 'none');

// Payroll for this month.
$month = date('Y-m-01');
$pOne = Payroll::where('user_id', $one->id)->where('salary_month', $month)->first();
$pTwo = Payroll::where('user_id', $two->id)->where('salary_month', $month)->first();
if ($pOne && $pTwo) {
    section('10. Payroll generated, edited and approved: already done by an earlier run');
} else {
section('10. Payroll for ' . date('F Y') . ': generate, edit, approve');
$month = date('Y-m-01');
$call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => (int) date('m'), 'year' => (int) date('Y'), 'users' => [$one->id, $two->id]]);
$pOne = Payroll::where('user_id', $one->id)->where('salary_month', $month)->first();
$pTwo = Payroll::where('user_id', $two->id)->where('salary_month', $month)->first();
check('both payrolls generated', $pOne && $pTwo, json_encode(session('payroll_bulk_result')));
check('the advance repayment was added to Staff One\'s payroll by itself', $pOne && $pOne->details()->where('staff_loan_id', $loan->id)->where('amount', 20000)->exists());

// Staff One: housing allowance, union dues, the advance repayment.
$lines = ['user_id' => $one->id, 'salary_month' => $month,
    'allowance_titles' => ['Housing Allowance'], 'allowances' => [50000],
    'deduction_titles' => ['E2E salary advance', 'Union dues'], 'deductions' => [20000, 2000], 'deduction_loans' => [$loan->id, '']];
$preview = json_decode($call('POST', '/admin/staff/payroll-preview', $lines)->getContent(), true);
$call('POST', '/admin/staff/payroll', $lines + ['net_salary' => $preview['net_salary'] ?? 0, 'tax' => $preview['tax'] ?? 0]);
$linesTwo = ['user_id' => $two->id, 'salary_month' => $month,
    'allowance_titles' => ['Transport Allowance'], 'allowances' => [15000],
    'deduction_titles' => [], 'deductions' => [], 'deduction_loans' => []];
$previewTwo = json_decode($call('POST', '/admin/staff/payroll-preview', $linesTwo)->getContent(), true);
$call('POST', '/admin/staff/payroll', $linesTwo + ['net_salary' => $previewTwo['net_salary'] ?? 0, 'tax' => $previewTwo['tax'] ?? 0]);
$pOne->refresh(); $pTwo->refresh();

// The pay worked out by hand.
check('Staff One: gross is 300,000 + 50,000 - 22,000 = 328,000', $close($pOne->gross_salary, 328000), 'gross ' . $pOne->gross_salary);
check('Staff One: net is gross less tax', $close($pOne->net_salary, $pOne->gross_salary - $pOne->tax), "net {$pOne->net_salary}, tax {$pOne->tax}");
check('Staff Two: gross is 180,000 + 15,000 = 195,000', $close($pTwo->gross_salary, 195000), 'gross ' . $pTwo->gross_salary);
check('Staff Two: net is gross less tax', $close($pTwo->net_salary, $pTwo->gross_salary - $pTwo->tax), "net {$pTwo->net_salary}, tax {$pTwo->tax}");
echo "        Staff One: gross {$pOne->gross_salary}, tax {$pOne->tax}, employer {$pOne->employer_tax}, net {$pOne->net_salary}\n";
echo "        Staff Two: gross {$pTwo->gross_salary}, tax {$pTwo->tax}, employer {$pTwo->employer_tax}, net {$pTwo->net_salary}\n";

$call('POST', '/admin/staff/payroll-pay/' . $pOne->id, ['pay_date' => $today, 'payment_method' => 1, 'note' => 'E2E']);
check('paying before approval is refused (approval is on)', Payroll::find($pOne->id)->status == 0, $flash());
$call('POST', '/admin/staff/payroll-bulk', ['action' => 'approve', 'month' => (int) date('m'), 'year' => (int) date('Y'), 'users' => [$one->id, $two->id]]);
check('both approved', Payroll::find($pOne->id)->isApproved() && Payroll::find($pTwo->id)->isApproved(), json_encode(session('payroll_bulk_result')));
}

/** What a paid payroll should post, worked out from its own figures. */
$payrollPosting = function (Payroll $p, float $loanPart, string $cashCode) {
    $p->refresh();
    $l = [];
    $tax = (float) $p->tax; $er = (float) $p->employer_tax;
    $l['661'] = round((float) $p->gross_salary + $loanPart, 2);
    if ($er > 0) { $l['664'] = $er; }
    $l[$cashCode] = -round((float) $p->net_salary, 2);
    // Every withholding and the employer share go to the authority's account.
    foreach (DB::table('payroll_tax_lines')->where('payroll_id', $p->id)->get() as $t) {
        $code = DB::table('chart_of_accounts')->where('id', $t->liability_account_id)->value('account_code') ?? '443';
        $l[$code] = round(($l[$code] ?? 0) - (float) $t->employee_amount - (float) $t->employer_amount, 2);
    }
    if ($loanPart > 0) { $l['412'] = -$loanPart; }
    return $l;
};
$sum = function (array ...$maps) {
    $out = [];
    foreach ($maps as $m) { foreach ($m as $k => $v) { $out[$k] = round(($out[$k] ?? 0) + $v, 2); } }
    return $out;
};

$oneBank = App\Models\StaffBankAccount::where('user_id', $one->id)->value('id');
if ((int) $pOne->fresh()->status === 1) {
    section('11. Staff One paid: already done by an earlier run');
} else $step('11. Staff One paid in cash', function () use ($call, $pOne, $today, $oneBank, $errors, $flash) {
    $call('POST', '/admin/staff/payroll-pay/' . $pOne->id, ['pay_date' => $today, 'payment_method' => 1, 'bank_account_id' => $oneBank, 'note' => 'E2E cash']);
    if ($e = $errors() . $flash()) { echo "        said: $e
"; }
}, function () use ($payrollPosting, $pOne) {
    return ['ledger' => $payrollPosting($pOne, 20000, '571')];
});
$pOne->refresh();
check('Staff One is paid', (int) $pOne->status === 1);
check("Staff One's tax lines add up to the payroll's tax", $close(DB::table('payroll_tax_lines')->where('payroll_id', $pOne->id)->sum('employee_amount'), $pOne->tax));
check('the advance now has 40,000 left', $close($loan->fresh()->balance(), 40000), 'balance ' . $loan->fresh()->balance());
gap('the 2,000 union dues withheld from Staff One is recorded as owed to the union', true,
    'it is taken off the gross and posted nowhere: the salary cost shows 348,000 instead of 350,000, and nothing says 2,000 is owed');
gap('an employer social contribution (CNPS) is charged on salaries', (float) $pOne->employer_tax == 0.0,
    'no CNPS rate is configured (employer share, or the employee 4.2%), so payroll books no social charges and 431 CNPS never moves');

if ((int) $pTwo->fresh()->status === 1) {
    section('12-14. Staff Two paid, reversed, paid again: already done by an earlier run');
    $pTwo->refresh();
} else {
$step('12. Staff Two paid by bank transfer', function () use ($call, $pTwo, $today, $twoBank) {
    $call('POST', '/admin/staff/payroll-pay/' . $pTwo->id, ['pay_date' => $today, 'payment_method' => 2, 'bank_account_id' => $twoBank, 'note' => 'E2E bank']);
}, function () use ($payrollPosting, $pTwo) {
    $posting = $payrollPosting($pTwo, 0, '571');
    $right = $posting; unset($right['571']); $right['521'] = $posting['571'];
    return ['ledger' => $posting, 'correct' => $right, 'correct_label' => 'a salary paid by bank transfer is credited to the bank (521), not the cash box'];
});
$pTwo->refresh();
check('Staff Two is paid', (int) $pTwo->status === 1);
$e12 = $payrollPosting($pTwo, 0, '571');

$step('13. Staff Two\'s payment reversed', function () use ($call, $pTwo) {
    $call('POST', '/admin/staff/payroll-unpay/' . $pTwo->id, ['note' => 'E2E reversal']);
}, ['ledger' => array_map(fn ($v) => -$v, $e12)]);
check('Staff Two\'s payroll is unpaid again', Payroll::find($pTwo->id)->status == 0);

$step('14. Staff Two paid again, by bank', function () use ($call, $pTwo, $today, $twoBank) {
    $call('POST', '/admin/staff/payroll-approve/' . $pTwo->id);
    $call('POST', '/admin/staff/payroll-pay/' . $pTwo->id, ['pay_date' => $today, 'payment_method' => 2, 'bank_account_id' => $twoBank, 'note' => 'E2E bank again']);
}, ['ledger' => $e12]);

}

// What was withheld for the month, paid to each body it is owed to: the
// State (443) and CNPS (431) are separate payments, each against its own
// liability.
$owedBy = DB::table('payroll_tax_lines as t')->join('chart_of_accounts as a', 'a.id', '=', 't.liability_account_id')
    ->whereIn('t.payroll_id', [$pOne->id, $pTwo->id])
    ->groupBy('a.account_code')->select('a.account_code', DB::raw('ROUND(SUM(t.employee_amount + t.employer_amount), 2) as owed'))
    ->pluck('owed', 'account_code');
foreach ($owedBy as $code => $owed) {
    $code = (string) $code;
    $ref = 'E2E-TAX-' . $code;
    if (DB::table('tax_remittances')->where('reference', $ref)->exists()) {
        section("15. Payroll withholdings on $code paid: already done by an earlier run");
        continue;
    }
    $step("15. The month's withholdings owed on $code paid (" . number_format($owed, 2) . ')', function () use ($call, $acct, $owed, $code, $ref, $month, $today, $errors, $flash) {
        $call('POST', '/admin/staff/tax-remittance', ['liability_account_id' => $acct[$code], 'salary_month' => $month, 'amount' => $owed, 'payment_date' => $today, 'source_account_id' => $acct['571'], 'reference' => $ref, 'note' => 'E2E remittance', 'allow_additional' => 1]);
        if ($e = $errors() . $flash()) { echo "        said: $e\n"; }
    }, ['ledger' => [$code => (float) $owed, '571' => -(float) $owed]]);
}

if (!$expense) {
    section('16. Expense deleted: already done by an earlier run, not repeated');
} else $step('16. The 120,000 plumbing expense deleted (entered by mistake)', function () use ($call, $expense) {
    $call('DELETE', '/admin/account/expense/' . $expense->id);
}, ['ledger' => ['628' => -120000, '571' => 120000], 'iso_rev' => 0, 'iso_exp' => -120000]);

// -----------------------------------------------------------------------------
// After everything
// -----------------------------------------------------------------------------

section('After everything: the whole run, start to finish');
$end = $reports();
$endL = $ledger();
$moved = [];
foreach ($endL as $code => $v) {
    $d = round($v - ($startL[$code] ?? 0), 2);
    if (abs($d) > 0.005) { $moved[$code] = $d; }
}
ksort($moved);
echo '        net change by account: ' . json_encode($moved) . "\n";
check('the run as a whole added equal debits and credits', $close(array_sum($moved), 0), 'off by ' . $fmt(array_sum($moved)));
$cashMoved = array_sum(array_filter($moved, fn ($k) => ((string) $k)[0] === '5', ARRAY_FILTER_USE_KEY));
check('cash and bank after the run moved by ' . $fmt($cashMoved) . ' in the ledger and on the balance sheet', $close($end['bs_cash'] - $start['bs_cash'], $cashMoved), 'balance sheet moved by ' . $fmt($end['bs_cash'] - $start['bs_cash']));
check('trial balance balances', $close($end['tb_debit'], $end['tb_credit']), 'off by ' . $fmt($end['tb_debit'] - $end['tb_credit']));
check('balance sheet balances', $close($end['bs_assets'], $end['bs_liab']), 'assets ' . $fmt($end['bs_assets']) . ', liabilities and equity ' . $fmt($end['bs_liab']) . ', difference ' . $fmt($end['bs_assets'] - $end['bs_liab']));
check('cash flow statement balances', $end['cf_balanced'], 'difference ' . $fmt($end['cf_diff']));

// Put the payroll settings back as they were.
$call('POST', '/admin/staff/payroll-setting', array_merge($form, [
    'require_approval' => (int) $originalSettings['require_approval'],
    'advance_account_id' => $originalSettings['advance_account_id'] ?? $acct['412'],
    'advance_payment_account_id' => $originalSettings['advance_payment_account_id'] ?? $acct['571'],
]));
PayrollSetting::flush();
echo "\n        payroll settings: approval back to " . (PayrollSetting::current()->require_approval ? 'on' : 'off')
    . '; loan accounts left at 412 / 571 so the E2E advance stays booked' . "\n";

section('Summary');
echo "passed $passed, failed $failed, gaps " . count($gaps) . "\n";
file_put_contents(__DIR__ . '/../storage/app/accounting-e2e-result.txt', implode("\n", $log) . "\n\npassed $passed, failed $failed, gaps " . count($gaps) . "\n");
