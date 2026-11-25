<?php

/**
 * The payroll cannot be paid on figures the server did not work out.
 *
 * payroll_workflow_test drives the happy path. This suite tries the holes the
 * end-to-end review of 21 November 2026 found, through the real routes:
 *
 *   - a form posted with figures other than the page's own (gross raised,
 *     allowances overstated, earning cut to lower the tax, basic salary set
 *     to 1, net pay below zero) is refused, not saved;
 *   - an allowance type set as taxable is taxed, one that is not is not, and
 *     the payroll keeps what it was taxed on when the setting later changes;
 *   - hourly staff are paid rate x hours present;
 *   - the payslip prints the taxes actually charged, every one of them,
 *     adding up to the tax withheld;
 *   - unpay keeps the payroll paid when the ledger reversal fails;
 *   - the loose ends: no error logged on generate, no transaction left open,
 *     no error page on the unrouted actions, no paying twice, no paying into
 *     another staff member's account, and only this staff member's attendance.
 *
 * Expected figures are worked out here from the breakdown service and the
 * rate tables, not read back from the calculator under test.
 *
 * Everything runs inside one transaction that is rolled back.
 *
 *   php scripts/payroll_integrity_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AllowanceType;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\PayrollTaxLine;
use App\Models\TransactionMapping;
use App\Services\PayrollAccountingService;
use App\Services\PayrollTaxBreakdownService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $what . (!$ok && $detail !== '' ? " — $detail" : '') . "\n";
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
Auth::guard('web')->login($admin);

$call = function (string $method, string $path, array $data = [], array $headers = []) use ($kernel) {
    if ($method !== 'GET') {
        $data['_token'] = csrf_token();
    }
    // Money-posting screens go through the review dialog (RequireReview);
    // these steps act as if it had been confirmed.
    if ($method !== 'GET' && !array_key_exists('reviewed', $data)) {
        $data['reviewed'] = 1;
    }
    $request = Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/staff/payroll');
    foreach ($headers as $k => $v) {
        $request->headers->set($k, $v);
    }

    return $kernel->handle($request);
};

$input = function (string $html, string $name): ?string {
    if (preg_match('/<input\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*\bvalue="([^"]*)"/', $html, $m)) {
        return html_entity_decode($m[1]);
    }
    if (preg_match('/<input\b[^>]*\bvalue="([^"]*)"[^>]*\bname="' . preg_quote($name, '/') . '"/', $html, $m)) {
        return html_entity_decode($m[1]);
    }

    return null;
};

$m = fn ($n) => number_format((float) $n, 2);
$close = fn ($a, $b, $tol = 0.51) => abs((float) $a - (float) $b) <= $tol;

$year = (int) date('Y');
$month = (int) date('m');
$salaryMonth = sprintf('%04d-%02d-01', $year, $month);
$monthDate = Carbon::parse($salaryMonth);

$adminIds = DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')
    ->whereIn('r.name', ['Super Admin', 'Admin'])->pluck('mr.model_id');

$staff = App\User::where('status', 1)->where('salary_type', 1)->where('basic_salary', '>=', 100000)
    ->whereNotIn('id', $adminIds)
    ->whereHas('bankAccounts', fn ($q) => $q->where('status', 1))
    ->whereDoesntHave('exemptTaxes')
    ->whereDoesntHave('payrolls', fn ($q) => $q->where('salary_month', $salaryMonth))
    ->orderBy('basic_salary')->first();

if (!$staff) {
    echo "no monthly-paid staff member on 100,000 or more with a bank account and no payroll this month\n";
    exit(0);
}

$breakdown = app(PayrollTaxBreakdownService::class);
$exemptions = $breakdown->exemptionsFor($staff->id, $monthDate);
/** The tax on a pay, worked out from the rate tables directly. */
$expectTax = function (float $taxable) use ($breakdown, $monthDate, $exemptions) {
    $lines = $breakdown->forSalary($taxable, $monthDate, $exemptions);

    return [
        'employee' => array_sum(array_map(fn ($l) => round($l['employee'], 2), $lines)),
        'employer' => array_sum(array_map(fn ($l) => round($l['employer'], 2), $lines)),
        'labels' => array_column($lines, 'label'),
    ];
};

$logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
$logBefore = is_file($logFile) ? filesize($logFile) : 0;
$levelBefore = DB::transactionLevel();
$typesBefore = AllowanceType::orderBy('id')->pluck('is_taxable', 'id')->all();

echo 'month: ' . $monthDate->format('F Y') . "\n";
echo 'staff: ' . $staff->staff_id . ' at ' . $m($staff->basic_salary) . "/month\n";

DB::beginTransaction();

try {
    $basic = (float) $staff->basic_salary;
    $payrollFor = fn () => Payroll::where('user_id', $staff->id)->where('salary_month', $salaryMonth)->first();

    // -------------------------------------------------------------------------
    section('The page and the server agree before anything is typed');
    // -------------------------------------------------------------------------

    $response = $call('GET', "/admin/staff/payroll-generate/{$staff->id}/$month/$year");
    $html = $response->getContent();
    check('the generate page renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());

    $onPage = [];
    foreach (['basic_salary', 'total_earning', 'total_allowance', 'total_deduction', 'gross_salary', 'tax', 'employer_tax', 'net_salary', 'total_cost'] as $n) {
        $onPage[$n] = (float) $input($html, $n);
    }
    $plain = $expectTax($basic);
    check('it shows the basic salary as the earning', $close($onPage['total_earning'], $basic, 0.5));
    check('and the tax the rate tables give on it', $close($onPage['tax'], $plain['employee'], 0.5), $m($onPage['tax']) . ' vs ' . $m($plain['employee']));

    $attendances = $response->original->getData()['attendances'];
    check('it loads this staff member\'s attendance only', $attendances->every(fn ($a) => (int) $a->user_id === (int) $staff->id), $attendances->pluck('user_id')->unique()->implode(','));

    $panelLabels = [];
    preg_match_all('/<div class="tax-name">([^<\']+)<\/div>/', $html, $mm);
    $panelLabels = array_map('trim', $mm[1]);
    check('its tax panel lists every tax charged, the dependent ones included', $panelLabels === $plain['labels'], implode(' | ', $panelLabels));

    // -------------------------------------------------------------------------
    section('A form posted with doctored figures is refused');
    // -------------------------------------------------------------------------

    $transport = AllowanceType::where('title', 'Transport Allowance')->first() ?? AllowanceType::where('status', 1)->first();
    $transport->is_taxable = false;
    $transport->save();

    $form = [
        'user_id' => $staff->id, 'salary_type' => 1, 'salary_month' => $salaryMonth,
        'basic_salary' => $basic, 'total_earning' => $basic,
        'allowance_titles' => [$transport->title], 'allowances' => [40000],
        'deduction_titles' => [], 'deductions' => [],
        'total_allowance' => 40000, 'total_deduction' => 0,
        'gross_salary' => $basic + 40000,
        'tax' => round($plain['employee']), 'employer_tax' => round($plain['employer']),
    ];
    $form['net_salary'] = $form['gross_salary'] - $form['tax'];
    $form['total_cost'] = $form['net_salary'] + $form['tax'] + $form['employer_tax'];

    $tries = [
        'gross raised by 500,000' => fn ($f) => array_merge($f, ['gross_salary' => $f['gross_salary'] + 500000, 'net_salary' => $f['net_salary'] + 500000]),
        'an allowance total of 540,000 over lines of 40,000' => fn ($f) => array_merge($f, ['total_allowance' => 540000, 'gross_salary' => $basic + 540000, 'net_salary' => $basic + 540000 - $f['tax']]),
        'the earning posted as 20,000 with the tax on 20,000' => function ($f) use ($expectTax, $basic) {
            $low = $expectTax(20000);

            return array_merge($f, ['total_earning' => 20000, 'tax' => round($low['employee']), 'employer_tax' => round($low['employer']), 'net_salary' => $f['gross_salary'] - round($low['employee'])]);
        },
        'the basic salary posted as 1' => fn ($f) => array_merge($f, ['basic_salary' => 1]),
        'the net pay posted 100,000 higher' => fn ($f) => array_merge($f, ['net_salary' => $f['net_salary'] + 100000]),
    ];

    foreach ($tries as $what => $doctor) {
        DB::statement('SAVEPOINT probe');
        $call('POST', '/admin/staff/payroll', $doctor($form));
        check("$what: refused", $payrollFor() === null, 'saved with gross ' . $m(optional($payrollFor())->gross_salary) . ', net ' . $m(optional($payrollFor())->net_salary));
        DB::statement('ROLLBACK TO SAVEPOINT probe');
    }

    // Deductions larger than the pay: the page's own, consistent figures.
    $neg = array_merge($form, ['deduction_titles' => ['Advance'], 'deductions' => [400000], 'total_deduction' => 400000]);
    $neg['gross_salary'] = $basic + 40000 - 400000;
    $neg['net_salary'] = $neg['gross_salary'] - $neg['tax'];
    DB::statement('SAVEPOINT probe');
    $call('POST', '/admin/staff/payroll', $neg);
    check('deductions that take the net pay below zero: refused', $payrollFor() === null);
    DB::statement('ROLLBACK TO SAVEPOINT probe');

    // A made-up allowance title could otherwise dodge the tax setting.
    DB::statement('SAVEPOINT probe');
    $call('POST', '/admin/staff/payroll', array_merge($form, ['allowance_titles' => ['ZZ Not A Type']]));
    check('an allowance that is not one of the types: refused', $payrollFor() === null);
    DB::statement('ROLLBACK TO SAVEPOINT probe');

    DB::statement('SAVEPOINT probe');
    $call('POST', '/admin/staff/payroll', array_merge($form, ['allowances' => [-5000], 'total_allowance' => -5000, 'gross_salary' => $basic - 5000, 'net_salary' => $basic - 5000 - $form['tax']]));
    check('a negative allowance: refused', $payrollFor() === null);
    DB::statement('ROLLBACK TO SAVEPOINT probe');

    // Teeth: the page's own figures are saved, so the refusals above are not
    // simply refusing everything.
    $call('POST', '/admin/staff/payroll', $form);
    $payroll = $payrollFor();
    check('the page\'s own figures: saved', $payroll !== null);
    if ($payroll) {
        check('with the basic from the staff record', $close($payroll->basic_salary, $basic, 0.01));
        check('gross is earning plus allowances less deductions', $close($payroll->gross_salary, $basic + 40000, 0.01), $m($payroll->gross_salary));
        check('an untaxed allowance leaves the tax on the basic', $close($payroll->tax, $plain['employee']), $m($payroll->tax) . ' vs ' . $m($plain['employee']));
        check('and records what it was taxed on', $close($payroll->taxable_salary, $basic, 0.01), $m($payroll->taxable_salary));
        check('and that the allowance was not taxed', $payroll->details()->where('status', 1)->first()->is_taxable === false
            || (int) $payroll->details()->where('status', 1)->first()->is_taxable === 0);
    }

    // -------------------------------------------------------------------------
    section('An allowance type set as taxable is taxed');
    // -------------------------------------------------------------------------

    $transport->is_taxable = true;
    $transport->save();
    $taxed = $expectTax($basic + 40000);

    $preview = json_decode($call('POST', '/admin/staff/payroll-preview', [
        'user_id' => $staff->id, 'salary_month' => $salaryMonth,
        'allowance_titles' => [$transport->title], 'allowances' => [40000],
    ], ['Accept' => 'application/json'])->getContent(), true);

    check('the live preview taxes basic plus the allowance', $close($preview['taxable_salary'] ?? -1, $basic + 40000, 0.01), $m($preview['taxable_salary'] ?? 0));
    check('at the rate tables\' figure for that pay', $close($preview['tax'] ?? -1, $taxed['employee']), $m($preview['tax'] ?? 0) . ' vs ' . $m($taxed['employee']));
    check('which is more than on the basic alone', ($preview['tax'] ?? 0) > $plain['employee']);

    // The page left open from before the switch is now stale, and saving it
    // is refused rather than quietly re-taxed.
    $call('POST', '/admin/staff/payroll', $form);
    check('a page opened before the setting changed is refused', $close($payrollFor()->tax, $plain['employee']), 'tax now ' . $m($payrollFor()->tax));

    $fresh = $call('GET', "/admin/staff/payroll-generate/{$staff->id}/$month/$year")->getContent();
    $again = $form;
    foreach (['total_earning', 'total_allowance', 'gross_salary', 'tax', 'employer_tax', 'net_salary', 'total_cost'] as $n) {
        $again[$n] = (float) $input($fresh, $n);
    }
    check('the regenerated page shows the allowance as taxed', str_contains($fresh, $transport->title . ' · '), 'no "taxed" marker');
    check('and the higher tax', $close($again['tax'], $taxed['employee'], 0.5), $m($again['tax']));

    $call('POST', '/admin/staff/payroll', $again);
    $payroll = $payrollFor()->fresh();
    check('saving it taxes the allowance', $close($payroll->tax, $taxed['employee']), $m($payroll->tax) . ' vs ' . $m($taxed['employee']));
    check('records the taxable pay as basic plus allowance', $close($payroll->taxable_salary, $basic + 40000, 0.01));
    check('and marks the allowance line as taxed', (bool) $payroll->details()->where('status', 1)->first()->is_taxable);
    $lines = PayrollTaxLine::where('payroll_id', $payroll->id)->get();
    check('its itemised taxes add up to the tax charged', $close($lines->sum('employee_amount'), $payroll->tax) && $close($lines->sum('employer_amount'), $payroll->employer_tax));

    $transport->is_taxable = false;
    $transport->save();
    check('switching the type back does not rewrite the saved payroll', $close($payroll->fresh()->tax, $taxed['employee'])
        && $breakdown->reconciles($payroll->fresh(), $breakdown->forPayroll($payroll->fresh())),
        'the breakdown no longer reconciles');

    // -------------------------------------------------------------------------
    section('The payslip prints what was charged');
    // -------------------------------------------------------------------------

    if (!App\Models\PrintSetting::where('slug', 'pay-slip')->exists()) {
        App\Models\PrintSetting::create(['slug' => 'pay-slip', 'title' => 'Pay Slip', 'width' => '800px', 'height' => 'auto', 'status' => 1]);
    }
    $slip = $call('GET', "/admin/staff/payroll-print/{$payroll->id}");
    $slipHtml = $slip->getContent();
    check('the payslip renders', $slip->getStatusCode() === 200, 'status ' . $slip->getStatusCode());

    $dp = (int) (App\Models\Setting::first()->decimal_place ?? 2);
    $printed = 0.0;
    $missing = [];
    foreach ($lines->where('employee_amount', '>', 0) as $line) {
        $pattern = '/' . preg_quote(e($line->label), '/') . '[^<]*(?:<small>[^<]*<\/small>)?\s*<\/div>\s*<div class="sal-amount deduct-amount">([0-9.,]+)<\/div>/s';
        if (preg_match($pattern, $slipHtml, $hit)) {
            $printed += (float) str_replace(',', '', $hit[1]);
        } else {
            $missing[] = $line->label;
        }
    }
    check('every tax on the payroll is on the payslip, the Additional Council Tax included', $missing === [], implode(', ', $missing));
    check('and the printed taxes add up to the tax withheld', $close($printed, $payroll->tax, 1), $m($printed) . ' vs ' . $m($payroll->tax));
    check('the taxed allowance is marked on it', (bool) preg_match('/' . preg_quote(e($transport->title), '/') . '\s*<small[^>]*>\(taxed\)/', $slipHtml));

    // Change a rate after the fact: an old payslip must not move.
    $rate = App\Models\TaxSetting::where('tax_group_id', 1)->where('status', 1)->first();
    $rate->percentange = (float) $rate->percentange + 5;
    $rate->save();
    $slipAfter = $call('GET', "/admin/staff/payroll-print/{$payroll->id}")->getContent();
    // The print time and the toast messages of earlier requests are not
    // part of the payslip.
    $strip = fn ($h) => preg_replace(['/Printed on:.*?<\/div>/s', '/<script[^>]*flasher-js.*?<\/script>/s'], '', $h);
    check('a rate changed afterwards does not change the payslip', $strip($slipAfter) === $strip($slipHtml));
    $rate->percentange = (float) $rate->percentange - 5;
    $rate->save();

    // -------------------------------------------------------------------------
    section('Paying');
    // -------------------------------------------------------------------------

    $bank = $staff->bankAccounts()->where('status', 1)->orderByDesc('is_default')->first();
    $other = App\Models\StaffBankAccount::where('user_id', '!=', $staff->id)->first();
    $pay = fn ($bankId) => $call('POST', "/admin/staff/payroll-pay/{$payroll->id}", ['pay_date' => date('Y-m-d'), 'payment_method' => 4, 'bank_account_id' => $bankId]);

    if ($other) {
        $pay($other->id);
        check('paying into another staff member\'s account is refused', (int) $payroll->fresh()->status === 0);
    } else {
        skip('no other staff member has a bank account');
    }

    $pay($bank->id);
    check('the payroll is paid', (int) $payroll->fresh()->status === 1);
    $entries = fn () => JournalEntry::where('reference_type', 'payroll')->where('reference_id', $payroll->id)->where('is_reversed', false)->count();
    check('and posted to the ledger once', $entries() === 1, $entries() . ' entries');

    $pay($bank->id);
    check('paying it again is refused and posts nothing more', $entries() === 1, $entries() . ' entries');

    $call('POST', '/admin/staff/payroll', $again);
    check('a paid payroll cannot be regenerated over', (int) $payrollFor()->status === 1 && $close($payrollFor()->tax, $payroll->tax));

    // -------------------------------------------------------------------------
    section('Unpay keeps the books and the payroll in step');
    // -------------------------------------------------------------------------

    // A ledger whose reversal fails, as it would in a closed period.
    $real = app(PayrollAccountingService::class);
    app()->instance(PayrollAccountingService::class, new class extends PayrollAccountingService {
        public function __construct()
        {
        }

        public function reversePayrollJournalEntry(App\Models\Payroll $payroll, int $userId): ?App\Models\JournalEntry
        {
            return null;
        }
    });
    $call('POST', "/admin/staff/payroll-unpay/{$payroll->id}");
    check('when the ledger cannot reverse the payment, the payroll stays paid', (int) $payroll->fresh()->status === 1);
    check('and keeps its itemised taxes', PayrollTaxLine::where('payroll_id', $payroll->id)->count() === $lines->count());
    check('and no transaction is left open', DB::transactionLevel() === 1, 'level ' . DB::transactionLevel());

    app()->instance(PayrollAccountingService::class, $real);
    // The router keeps the controller it built for a route between requests
    // in one process, with the fake ledger injected; let it build a new one.
    app('router')->getRoutes()->getByName('admin.payroll.unpay')->controller = null;
    $call('POST', "/admin/staff/payroll-unpay/{$payroll->id}");
    $mapping = TransactionMapping::where('transaction_type', 'payroll')->where('transaction_id', $payroll->id)->first();
    check('with a working ledger it is unpaid', (int) $payroll->fresh()->status === 0);
    check('and the ledger entry is reversed', $mapping && $mapping->status === 'reversed');

    $call('POST', "/admin/staff/payroll-unpay/{$payroll->id}");
    check('unpaying an unpaid payroll leaves no transaction open', DB::transactionLevel() === 1, 'level ' . DB::transactionLevel());

    // -------------------------------------------------------------------------
    section('Hourly-paid staff are paid for their hours');
    // -------------------------------------------------------------------------

    $hourly = App\User::where('status', 1)->where('salary_type', 2)->where('basic_salary', '>', 0)
        ->whereNotIn('id', $adminIds)
        ->whereDoesntHave('payrolls', fn ($q) => $q->where('salary_month', $salaryMonth))->first();

    if ($hourly) {
        $rateHour = (float) $hourly->basic_salary;
        $context = DB::table('student_enrolls')->whereNotNull('session_id')->whereNotNull('section_id')
            ->select('session_id', 'program_id', 'semester_id', 'section_id')->first();
        $subjectId = DB::table('subjects')->min('id');
        $session = fn ($day, $start, $end, $attendance = 1) => DB::table('staff_hourly_attendances')->insert([
            'user_id' => $hourly->id, 'subject_id' => $subjectId,
            'session_id' => $context->session_id, 'program_id' => $context->program_id,
            'semester_id' => $context->semester_id, 'section_id' => $context->section_id,
            'date' => $monthDate->copy()->addDays($day)->format('Y-m-d'),
            'start_time' => $start, 'end_time' => $end, 'attendance' => $attendance,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $session(2 + $i, '08:00:00', '12:00:00');       // 5 x 4 h present
        }
        $session(8, '08:00:00', '10:30:00');                  // 2.5 h present
        $session(9, '08:00:00', '12:00:00', 2);               // absent: not paid
        $session(10, null, null);                             // present, no times

        $hHtml = $call('GET', "/admin/staff/payroll-generate/{$hourly->id}/$month/$year")->getContent();
        $hEarning = (float) $input($hHtml, 'total_earning');
        check('the earning is the rate times the 22.5 hours present', $close($hEarning, round($rateHour * 22.5), 0.5), $m($hEarning) . ' vs ' . $m($rateHour * 22.5));
        check('the page says how many hours at what rate', str_contains($hHtml, '22.5 hours present this month'));
        check('and warns about the session with no times', str_contains($hHtml, 'has no start or end time'));

        $hForm = ['user_id' => $hourly->id, 'salary_month' => $salaryMonth, 'allowance_titles' => [], 'allowances' => []];
        foreach (['basic_salary', 'total_earning', 'total_allowance', 'total_deduction', 'gross_salary', 'tax', 'employer_tax', 'net_salary'] as $n) {
            $hForm[$n] = $input($hHtml, $n);
        }
        $call('POST', '/admin/staff/payroll', $hForm);
        $hp = Payroll::where('user_id', $hourly->id)->where('salary_month', $salaryMonth)->first();
        if ($hp) {
            check('saved with the hours and the hourly rate', $close($hp->hours_worked, 22.5, 0.001) && $close($hp->basic_salary, $rateHour, 0.01));
            $hTax = $breakdown->forSalary(round($rateHour * 22.5, 2), $monthDate, $breakdown->exemptionsFor($hourly->id, $monthDate));
            check('and taxed on what was earned, not on the rate', $close($hp->tax, array_sum(array_map(fn ($l) => round($l['employee'], 2), $hTax))));
        } else {
            $err = collect(session('flasher::envelopes', []))->map(fn ($e) => method_exists($e, 'getMessage') ? $e->getMessage() : '')->implode(' ');
            check('the hourly payroll is saved', false, $err ?: 'not saved');
        }
    } else {
        skip('no hourly-paid staff member without a payroll this month');
    }

    // -------------------------------------------------------------------------
    section('Loose ends');
    // -------------------------------------------------------------------------

    foreach (['GET' => "/admin/staff/payroll/{$payroll->id}", 'DELETE' => "/admin/staff/payroll/{$payroll->id}", 'PUT' => "/admin/staff/payroll/{$payroll->id}"] as $verb => $path) {
        $code = $call($verb, $path)->getStatusCode();
        check("$verb $path answers without an error page", $code < 500, "status $code");
    }
} finally {
    DB::rollBack(0);
}

$log = is_file($logFile) ? (string) file_get_contents($logFile, false, null, $logBefore) : '';
check('generating a payroll logged no auto-mapping error', !str_contains($log, 'Auto-mapping failed for payroll'));

// -----------------------------------------------------------------------------
section('Nothing was left behind');
// -----------------------------------------------------------------------------

check('no payroll for this month was left', !Payroll::where('user_id', $staff->id)->where('salary_month', $salaryMonth)->exists());
check('the allowance types are as they were', AllowanceType::orderBy('id')->pluck('is_taxable', 'id')->all() === $typesBefore);
check('the transaction level is back where it started', DB::transactionLevel() === $levelBefore);

echo "\n----\npassed $passed, failed $failed, skipped $skipped\n";
exit($failed === 0 ? 0 : 1);
