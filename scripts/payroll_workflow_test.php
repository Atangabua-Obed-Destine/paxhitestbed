<?php

/**
 * The payroll workflow, end to end, through the real routes.
 *
 * A payroll is generated for a month from the staff member's salary and the
 * rates in force, saved, paid against a bank account, posted to the ledger,
 * itemised for the tax authorities, printed as a payslip, reported, and if
 * need be reversed. Each of those is a screen or a form, and each is driven
 * here exactly as the Bursar drives it: the generate page is rendered and its
 * own figures are posted back, the pay form is submitted with a bank account,
 * and what the books then hold is checked against figures worked out
 * independently from the rate tables.
 *
 * Everything runs inside one transaction that is rolled back at the end, so
 * the testbed is left as it was found.
 *
 *   php scripts/payroll_workflow_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payroll;
use App\Models\PayrollTaxLine;
use App\Models\TransactionMapping;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;
$findings = [];

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function finding(string $what): void
{
    global $findings;
    $findings[] = $what;
    echo "  NOTE  $what\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

// --- tooling -----------------------------------------------------------------

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
if (!$admin) {
    echo "no Super Admin to drive the screens as\n";
    exit(0);
}
Auth::guard('web')->login($admin);

/** A request through the whole stack, as the browser would send it. */
$call = function (string $method, string $path, array $data = []) use ($kernel) {
    if ($method !== 'GET') {
        $data['_token'] = csrf_token();
    }
    $request = Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/staff/payroll');

    return $kernel->handle($request);
};

/** The value of a named form input in rendered HTML. */
$input = function (string $html, string $name): ?string {
    if (preg_match('/<input\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*\bvalue="([^"]*)"/', $html, $m)) {
        return html_entity_decode($m[1]);
    }
    if (preg_match('/<input\b[^>]*\bvalue="([^"]*)"[^>]*\bname="' . preg_quote($name, '/') . '"/', $html, $m)) {
        return html_entity_decode($m[1]);
    }

    return null;
};

$money = fn ($n) => number_format((float) $n, 2);

// --- the month and the people --------------------------------------------------

$year = (int) date('Y');
$month = (int) date('m');
$salaryMonth = sprintf('%04d-%02d-01', $year, $month);
$monthDate = Carbon::parse($salaryMonth);

$adminIds = DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')
    ->whereIn('r.name', ['Super Admin', 'Admin'])->pluck('mr.model_id');

$staff = App\User::where('status', 1)->where('salary_type', 1)->where('basic_salary', '>', 0)
    ->whereNotIn('id', $adminIds)
    ->whereHas('bankAccounts', fn ($q) => $q->where('status', 1))
    ->whereDoesntHave('exemptTaxes')
    ->whereDoesntHave('payrolls', fn ($q) => $q->where('salary_month', $salaryMonth))
    ->orderBy('basic_salary')
    ->get()
    ->first(fn ($u) => $u->basic_salary >= 100000);

$hourly = App\User::where('status', 1)->where('salary_type', 2)->where('basic_salary', '>', 0)
    ->whereNotIn('id', $adminIds)->first();

if (!$staff) {
    echo "no monthly-paid staff member with a bank account and no payroll this month\n";
    exit(0);
}

echo 'month: ' . $monthDate->format('F Y') . "\n";
echo 'staff: ' . $staff->staff_id . ' ' . $staff->first_name . ' ' . $staff->last_name
    . ' at ' . $money($staff->basic_salary) . "/month\n";
if ($hourly) {
    echo 'hourly: ' . $hourly->staff_id . ' ' . $hourly->first_name . ' at ' . $money($hourly->basic_salary) . "/hour\n";
}

$before = [
    'payrolls' => Payroll::count(),
    'details' => DB::table('payroll_details')->count(),
    'tax_lines' => PayrollTaxLine::count(),
    'journal' => JournalEntry::count(),
    'journal_lines' => JournalEntryLine::count(),
    'transactions' => DB::table('transactions')->count(),
    'mappings' => TransactionMapping::count(),
    'print_settings' => DB::table('print_settings')->count(),
    'hourly_attendance' => DB::table('staff_hourly_attendances')->count(),
];

DB::beginTransaction();

try {

// ---------------------------------------------------------------------------
section('The ground is ready');
// ---------------------------------------------------------------------------

$config = app(App\Services\PayrollAccountingService::class)->checkConfiguration();
check('the payroll accounting is configured', $config['configured'], implode('; ', $config['issues']));
foreach ($config['issues'] as $issue) {
    finding('configuration: ' . $issue);
}

$fiscal = App\Models\FiscalYear::forDate(date('Y-m-d'));
check('a fiscal year covers today', $fiscal !== null, $fiscal->name ?? 'none');
$period = App\Models\FiscalYear::periodForDate(date('Y-m-d'));
check('and an open accounting period does too', $period !== null && !$period->is_closed, $period->name ?? 'none');

$incomeTax = App\Models\TaxGroup::getEffectiveGroups($monthDate)->first(fn ($g) => (int) $g->id === 1);
check('income tax rates are in force for the month', $incomeTax !== null && $incomeTax->brackets->where('status', 1)->count() > 0);

$printSetting = DB::table('print_settings')->where('slug', 'pay-slip')->first();
if (!$printSetting) {
    finding('No pay-slip print setting exists (Staff > Pay Slip Setting). The payslip print route needs one, and the print button on the payroll list is hidden until it exists.');
}

// ---------------------------------------------------------------------------
section('The payroll list offers the staff to pay');
// ---------------------------------------------------------------------------

$response = $call('GET', '/admin/staff/payroll', ['salary_type' => 1, 'month' => $month, 'year' => $year]);
$html = $response->getContent();
check('the list renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());

$expectedStaff = App\User::where('status', 1)->where('salary_type', 1)->whereNotIn('id', $adminIds)->count();
$generateLinks = preg_match_all('#/admin/staff/payroll-generate/\d+/' . $month . '/' . $year . '#', $html);
check(
    'every active monthly-paid staff member gets a generate link',
    $generateLinks === $expectedStaff,
    "$generateLinks links for $expectedStaff staff"
);
check('the chosen staff member is among them', strpos($html, "/admin/staff/payroll-generate/{$staff->id}/$month/$year") !== false);

// ---------------------------------------------------------------------------
section('Generating: the page works the figures out from the rates');
// ---------------------------------------------------------------------------

$response = $call('GET', "/admin/staff/payroll-generate/{$staff->id}/$month/$year");
$html = $response->getContent();
check('the generate page renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());

$form = [];
foreach (['user_id', 'basic_salary', 'total_earning', 'total_allowance', 'total_deduction', 'gross_salary', 'tax', 'employer_tax', 'net_salary', 'total_cost', 'salary_month', 'salary_type'] as $name) {
    $form[$name] = $input($html, $name);
}
check('the form carries every figure the server will validate', !in_array(null, $form, true),
    implode(', ', array_keys(array_filter($form, fn ($v) => $v === null))) . ' missing');
check('the salary month is the one asked for', $form['salary_month'] === $salaryMonth, (string) $form['salary_month']);
check('the earning is the basic salary', (float) $form['total_earning'] == (float) $staff->basic_salary,
    $form['total_earning'] . ' vs ' . $staff->basic_salary);

// The tax, worked out here from the rate tables rather than by asking the
// service: the one band whose floor is nearest below the pay, less the band's
// tax-free portion; the standalone contributions by rate or fixed sum; the
// council tax as a tenth of the income tax.
$salary = (float) $staff->basic_salary;
$expectedEmployee = 0.0;
$expectedEmployer = 0.0;
$expectedByAccount = [];
$byAccount = function (?int $account, float $amount) use (&$expectedByAccount) {
    $expectedByAccount[$account ?? 0] = ($expectedByAccount[$account ?? 0] ?? 0) + $amount;
};
$incomeTaxCharge = 0.0;

foreach (DB::table('tax_groups')->where('status', 1)->get() as $group) {
    $band = DB::table('tax_settings')->where('tax_group_id', $group->id)->where('status', 1)
        ->where('min_amount', '<=', $salary)
        ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $salaryMonth))
        ->orderByDesc('min_amount')->first();
    if (!$band) {
        continue;
    }
    $taxable = max(0, $salary - (float) $band->max_no_taxable_amount);
    $employee = in_array($band->paid_by, ['employee', 'both']) ? ($band->tax_type == 2 ? (float) $band->fixed_amount : $taxable * $band->percentange / 100) : 0;
    $employer = in_array($band->paid_by, ['employer', 'both']) ? ($band->tax_type == 2 ? (float) $band->employer_fixed_amount : $taxable * $band->employer_percentage / 100) : 0;
    if ((int) $group->id === 1) {
        $incomeTaxCharge = $employee + $employer;
    }
    $expectedEmployee += $employee;
    $expectedEmployer += $employer;
    $byAccount($band->liability_account_id ?: $group->liability_account_id, $employee + $employer);
}
foreach (DB::table('tax_settings')->whereNull('tax_group_id')->where('status', 1)
    ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $salaryMonth))
    ->get() as $tax) {
    if ($salary < $tax->min_amount || $salary > $tax->max_amount) {
        continue;
    }
    $base = $tax->is_dependent ? $incomeTaxCharge : max(0, $salary - (float) $tax->max_no_taxable_amount);
    $employee = in_array($tax->paid_by, ['employee', 'both']) ? ($tax->tax_type == 2 ? (float) $tax->fixed_amount : $base * $tax->percentange / 100) : 0;
    $employer = in_array($tax->paid_by, ['employer', 'both']) ? ($tax->tax_type == 2 ? (float) $tax->employer_fixed_amount : $base * $tax->employer_percentage / 100) : 0;
    $expectedEmployee += $employee;
    $expectedEmployer += $employer;
    $byAccount($tax->liability_account_id, $employee + $employer);
}

check(
    'the tax on the page is what the rate tables say (' . $money($expectedEmployee) . ')',
    abs((float) $form['tax'] - $expectedEmployee) <= 2.0,
    'page ' . $form['tax'] . ' vs tables ' . $money($expectedEmployee)
);
check(
    'and so is the employer\'s share (' . $money($expectedEmployer) . ')',
    abs((float) $form['employer_tax'] - $expectedEmployer) <= 2.0,
    'page ' . $form['employer_tax'] . ' vs tables ' . $money($expectedEmployer)
);

// ---------------------------------------------------------------------------
section('Saving: the server recomputes and refuses a tampered figure');
// ---------------------------------------------------------------------------

$allowances = ['Transport Allowance' => 40000, 'Housing Allowance' => 25000];
$post = $form + [
    'allowance_titles' => array_keys($allowances),
    'allowances' => array_values($allowances),
    'deduction_titles' => [],
    'deductions' => [],
];
$post['total_allowance'] = array_sum($allowances);
$post['gross_salary'] = (float) $form['total_earning'] + array_sum($allowances);
$post['net_salary'] = $post['gross_salary'] - (float) $form['tax'];
$post['total_cost'] = $post['net_salary'] + (float) $form['tax'] + (float) $form['employer_tax'];

// Teeth first: a tax that disagrees with the rates must not be saved.
$tampered = $post;
$tampered['tax'] = (float) $form['tax'] + 500;
$response = $call('POST', '/admin/staff/payroll', $tampered);
check('a tax 500 francs off the configured rates is refused', !Payroll::where('user_id', $staff->id)->where('salary_month', $salaryMonth)->exists()
    && $response->getStatusCode() === 302);

// And a month for which no rates exist is refused rather than taxed at zero.
$earliest = DB::table('tax_settings')->where('tax_group_id', 1)->min('effective_from');
if ($earliest) {
    $unrated = Carbon::parse($earliest)->subMonth()->format('Y-m-01');
    $blind = $post;
    $blind['salary_month'] = $unrated;
    $call('POST', '/admin/staff/payroll', $blind);
    check('a month before any income tax rate existed is refused', !Payroll::where('user_id', $staff->id)->where('salary_month', $unrated)->exists());
} else {
    skip('the income tax rates carry no effective date, so there is no unrated month to try');
}

$response = $call('POST', '/admin/staff/payroll', $post);
$payroll = Payroll::where('user_id', $staff->id)->where('salary_month', $salaryMonth)->first();
check('the payroll is saved', $payroll !== null && $response->getStatusCode() === 302);

if ($payroll) {
    check('as unpaid', (int) $payroll->status === 0);
    check('with the gross as basic plus allowances', (float) $payroll->gross_salary == $post['gross_salary'], $payroll->gross_salary);
    check('with the server\'s own tax', abs((float) $payroll->tax - $expectedEmployee) <= 0.51, $payroll->tax . ' vs ' . $money($expectedEmployee));
    check('with the employer\'s share', abs((float) $payroll->employer_tax - $expectedEmployer) <= 0.51, $payroll->employer_tax);
    check('net is gross less tax', abs((float) $payroll->net_salary - ((float) $payroll->gross_salary - (float) $payroll->tax)) < 0.01);
    check('total cost is net plus both taxes', abs((float) $payroll->total_cost - ((float) $payroll->net_salary + (float) $payroll->tax + (float) $payroll->employer_tax)) < 0.01);
    check('the two allowances are itemised', $payroll->details()->where('status', 1)->count() === 2
        && (float) $payroll->details()->where('status', 1)->sum('amount') == array_sum($allowances));

    $lines = PayrollTaxLine::where('payroll_id', $payroll->id)->get();
    check('the tax is itemised at the moment of saving', $lines->isNotEmpty(), $lines->count() . ' lines');
    check('and the items add up to the tax charged',
        abs($lines->sum('employee_amount') - (float) $payroll->tax) <= 0.51
        && abs($lines->sum('employer_amount') - (float) $payroll->employer_tax) <= 0.51);
    check('every item names the account it is owed to', $lines->every(fn ($l) => $l->liability_account_id !== null));

    check('an unpaid payroll has not been posted to the ledger', !TransactionMapping::where('transaction_type', 'payroll')->where('transaction_id', $payroll->id)->exists()
        && !JournalEntry::where('reference_type', 'payroll')->where('reference_id', $payroll->id)->exists());
    $log = @file_get_contents(storage_path('logs/laravel-' . date('Y-m-d') . '.log')) ?: '';
    if (strpos($log, "Auto-mapping failed for payroll:{$payroll->id}") !== false) {
        finding('Generating a payroll logs an ERROR every time: PayrollObserver tries to post the payroll to the ledger at creation, before it is paid, and only fails because there is no pay date yet (transaction_date cannot be null). It is the pay action that posts; the observer should not. If a pay date were ever present at creation the payroll would be posted twice.');
    }
    finding('Allowances are not taxed: the tax is computed on the basic salary alone (' . $money($payroll->total_earning)
        . '), while the gross paid is ' . $money($payroll->gross_salary) . '. If allowances are taxable here, that is a policy gap, not a bug in the flow.');
}

// Saving again for the same month updates the same row rather than adding one.
$again = $post;
$again['allowances'] = [40000, 30000];
$again['total_allowance'] = 70000;
$again['gross_salary'] = (float) $form['total_earning'] + 70000;
$again['net_salary'] = $again['gross_salary'] - (float) $form['tax'];
$again['total_cost'] = $again['net_salary'] + (float) $form['tax'] + (float) $form['employer_tax'];
$call('POST', '/admin/staff/payroll', $again);
$count = Payroll::where('user_id', $staff->id)->where('salary_month', $salaryMonth)->count();
$payroll = $payroll ? $payroll->fresh() : null;
check('generating the same month again replaces the payroll, not duplicates it', $count === 1 && $payroll && (float) $payroll->total_allowance == 70000.0,
    "$count rows, allowance " . ($payroll->total_allowance ?? '?'));
check('and its itemised taxes are rewritten, not stacked', $payroll && PayrollTaxLine::where('payroll_id', $payroll->id)->count() === $lines->count());

// ---------------------------------------------------------------------------
section('The list shows the payroll, ready to pay');
// ---------------------------------------------------------------------------

$response = $call('GET', '/admin/staff/payroll', ['salary_type' => 1, 'month' => $month, 'year' => $year]);
$html = $response->getContent();
check('the staff member\'s row now offers Pay', $payroll && strpos($html, "#payModal-{$staff->id}") !== false);
check('and not yet Unpay', $payroll && strpos($html, "#unpayModal-{$staff->id}") === false);
check('and is marked Generated', strpos($html, __('status_generated')) !== false);
if (!$printSetting) {
    check('without a pay-slip setting the list has no print link at all (see finding)', strpos($html, '/admin/staff/payroll-print/') === false);
}

// ---------------------------------------------------------------------------
section('Paying: the money moves and the books follow');
// ---------------------------------------------------------------------------

$bank = $staff->bankAccounts()->where('status', 1)->orderByDesc('is_default')->first();
$payPath = "/admin/staff/payroll-pay/{$payroll->id}";

// Teeth: tomorrow is not a pay date, and a bank account is required.
$call('POST', $payPath, ['pay_date' => date('Y-m-d', strtotime('+1 day')), 'payment_method' => 4, 'bank_account_id' => $bank->id]);
check('a pay date in the future is refused', (int) $payroll->fresh()->status === 0);
$call('POST', $payPath, ['pay_date' => date('Y-m-d'), 'payment_method' => 4]);
check('paying without a bank account is refused', (int) $payroll->fresh()->status === 0);

$journalBefore = JournalEntry::count();
$response = $call('POST', $payPath, ['pay_date' => date('Y-m-d'), 'payment_method' => 4, 'bank_account_id' => $bank->id, 'note' => 'ZZ payroll workflow test']);
$payroll = $payroll->fresh();
check('the payroll is paid', (int) $payroll->status === 1 && $response->getStatusCode() === 302);
check('with today as the pay date', $payroll->pay_date === date('Y-m-d'), (string) $payroll->pay_date);
check('against the chosen bank account', (int) $payroll->bank_account_id === (int) $bank->id);

$transaction = DB::table('transactions')->where('amount', $payroll->net_salary)->where('type', 2)->orderByDesc('id')->first();
check('a staff transaction records the net paid', $transaction !== null);

$mapping = TransactionMapping::where('transaction_type', 'payroll')->where('transaction_id', $payroll->id)->first();
$entry = $mapping && $mapping->journal_entry_id ? JournalEntry::with('lines')->find($mapping->journal_entry_id) : null;
check('a journal entry was posted for it', $entry !== null && $entry->is_posted, $entry ? 'entry ' . $entry->entry_number : 'none');

if ($entry) {
    $debits = (float) $entry->lines->sum('debit');
    $credits = (float) $entry->lines->sum('credit');
    check('the entry balances', abs($debits - $credits) < 0.01, "$debits vs $credits");
    check('dated the pay date, in its fiscal year and period',
        $entry->entry_date->format('Y-m-d') === $payroll->pay_date && (int) $entry->fiscal_year_id === (int) $fiscal->id
        && (int) $entry->accounting_period_id === (int) $period->id);
    check('the debits are the gross pay plus the employer\'s charges',
        abs($debits - ((float) $payroll->gross_salary + (float) $payroll->employer_tax)) < 0.01,
        $money($debits) . ' vs ' . $money($payroll->gross_salary + $payroll->employer_tax));

    $cashAccount = (int) DB::table('default_account_mappings')->where('mapping_type', 'payroll')->where('status', 'active')->value('credit_account_id');
    $cashCredit = (float) $entry->lines->where('account_id', $cashAccount)->sum('credit');
    check('the cash account is credited with exactly the net pay', abs($cashCredit - (float) $payroll->net_salary) < 0.01,
        $money($cashCredit) . ' vs ' . $money($payroll->net_salary));

    $liabilityCredits = $entry->lines->where('account_id', '!=', $cashAccount)->where('credit', '>', 0)->groupBy('account_id')->map(fn ($g) => (float) $g->sum('credit'));
    check('the rest is credited to the tax authorities, employee and employer sides together',
        abs($liabilityCredits->sum() - ((float) $payroll->tax + (float) $payroll->employer_tax)) < 0.01,
        $money($liabilityCredits->sum()) . ' vs ' . $money($payroll->tax + $payroll->employer_tax));

    $taxLines = PayrollTaxLine::where('payroll_id', $payroll->id)->get();
    $owedByAccount = $taxLines->groupBy('liability_account_id')->map(fn ($g) => (float) $g->sum('employee_amount') + (float) $g->sum('employer_amount'));
    $split = true;
    foreach ($owedByAccount as $account => $owed) {
        if (abs(($liabilityCredits[$account] ?? 0) - $owed) > 0.51) {
            $split = false;
        }
    }
    check('and each authority\'s account is credited with its own items (' . $owedByAccount->keys()->map(fn ($a) => DB::table('chart_of_accounts')->where('id', $a)->value('account_code'))->implode(', ') . ')', $split,
        json_encode($liabilityCredits) . ' vs ' . json_encode($owedByAccount));
    check('the authorities\' split agrees with the independent reckoning', collect($expectedByAccount)->every(fn ($amt, $acc) => abs(($owedByAccount[$acc] ?? 0) - $amt) <= 2.0),
        json_encode($expectedByAccount));
}

// ---------------------------------------------------------------------------
section('A paid payroll is history');
// ---------------------------------------------------------------------------

$response = $call('GET', "/admin/staff/payroll-generate/{$staff->id}/$month/$year");
check('the generate page for a paid month sends the user back', $response->getStatusCode() === 302);

$edit = $post + ['bonus' => 0, 'note' => 'ZZ edit'];
$edit['gross_salary'] = 999999;
$edit['net_salary'] = 999999 - (float) $form['tax'];
$edit['_method'] = 'PUT';
$call('POST', "/admin/staff/payroll/{$payroll->id}", $edit);
check('editing a paid payroll is refused', (float) $payroll->fresh()->gross_salary == (float) $payroll->gross_salary);

$response = $call('GET', '/admin/staff/payroll', ['salary_type' => 1, 'month' => $month, 'year' => $year]);
$html = $response->getContent();
check('the list now offers Unpay instead of Pay', strpos($html, "#unpayModal-{$staff->id}") !== false && strpos($html, "#payModal-{$staff->id}") === false);
check('and is marked Paid', strpos($html, __('status_paid')) !== false);

// ---------------------------------------------------------------------------
section('The tax owed shows up for remittance');
// ---------------------------------------------------------------------------

$remit = app(App\Services\TaxRemittanceService::class);
$owing = collect($remit->outstanding());
$monthKey = $owing->pluck('month')->first(fn ($m) => str_starts_with((string) $m, $monthDate->format('Y-m'))) ?? $monthDate->format('Y-m');
$taxLines = PayrollTaxLine::where('payroll_id', $payroll->id)->get();
$owedByAccount = $taxLines->groupBy('liability_account_id')->map(fn ($g) => (float) $g->sum('employee_amount') + (float) $g->sum('employer_amount'));
$allOwing = true;
foreach ($owedByAccount as $account => $owed) {
    $row = $owing->first(fn ($r) => (int) $r['account_id'] === (int) $account && (string) $r['month'] === (string) $monthKey);
    if (!$row || $row['outstanding'] + 0.01 < $owed) {
        $allOwing = false;
    }
}
check('each authority shows at least this payroll\'s share outstanding for the month', $allOwing,
    'month ' . $monthKey . ', rows ' . json_encode($owing->where('month', $monthKey)->map(fn ($r) => [$r['account_id'] => $r['outstanding']])->values()));

$response = $call('GET', '/admin/staff/tax-remittance');
check('the remittance screen renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());

// ---------------------------------------------------------------------------
section('Reporting');
// ---------------------------------------------------------------------------

$response = $call('GET', '/admin/staff/payroll-report', ['month' => $month, 'year' => $year]);
$html = $response->getContent();
check('the payroll report renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
check('and lists the staff member', strpos($html, $staff->last_name) !== false || strpos($html, $staff->first_name) !== false);
check('with the net paid', strpos($html, number_format((float) $payroll->net_salary, 2)) !== false || strpos($html, number_format((float) $payroll->net_salary)) !== false);

$response = $call('GET', '/admin/report/payroll', ['month' => $month, 'year' => $year]);
check('the salary-paid report renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());

// ---------------------------------------------------------------------------
section('The payslip');
// ---------------------------------------------------------------------------

$response = $call('GET', "/admin/staff/payroll-print/{$payroll->id}");
if (!$printSetting) {
    check('without a pay-slip setting the payslip cannot be printed (see finding)', $response->getStatusCode() === 404, 'status ' . $response->getStatusCode());

    // Configure one through the real screen, as the admin would.
    $response = $call('POST', '/admin/staff/pay-slip-setting', [
        'id' => -1, 'slug' => 'pay-slip', 'title' => 'ZZ Pay Slip',
        'header_left' => 'ZZ header', 'header_center' => '', 'header_right' => '',
        'body' => '', 'footer_left' => 'ZZ footer', 'footer_center' => '', 'footer_right' => '',
        'width' => 800, 'prefix' => 'PS',
    ]);
    $created = DB::table('print_settings')->where('slug', 'pay-slip')->exists();
    check('a pay-slip setting can be created from Staff > Pay Slip Setting', $created, 'status ' . $response->getStatusCode());
    $response = $call('GET', "/admin/staff/payroll-print/{$payroll->id}");
}
$html = $response->getContent();
check('the payslip renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
check('with the staff member', strpos($html, $staff->last_name) !== false || strpos($html, $staff->first_name) !== false);
check('the net pay', strpos($html, number_format((float) $payroll->net_salary, 2)) !== false || strpos($html, number_format((float) $payroll->net_salary)) !== false);
check('and each allowance', strpos($html, 'Transport Allowance') !== false && strpos($html, 'Housing Allowance') !== false);
$taxLabels = PayrollTaxLine::where('payroll_id', $payroll->id)->pluck('label');
check('and every tax by name', $taxLabels->every(fn ($l) => strpos($html, $l) !== false),
    $taxLabels->reject(fn ($l) => strpos($html, $l) !== false)->implode(', ') . ' missing');

// ---------------------------------------------------------------------------
section('Reversing: unpay puts everything back');
// ---------------------------------------------------------------------------

$balancesBefore = [];
foreach ($entry->lines as $line) {
    $balancesBefore[$line->account_id] = ($balancesBefore[$line->account_id] ?? 0) + $line->debit - $line->credit;
}

$response = $call('POST', "/admin/staff/payroll-unpay/{$payroll->id}");
$payroll = $payroll->fresh();
check('the payroll is unpaid again', (int) $payroll->status === 0 && $payroll->pay_date === null && $response->getStatusCode() === 302);

$reversal = JournalEntry::where('reference_type', 'payroll_reversal')->where('reference_id', $payroll->id)->with('lines')->orderByDesc('id')->first();
check('a reversal entry was posted', $reversal !== null && $reversal->is_posted);
if ($reversal) {
    $net = [];
    foreach ($entry->lines as $line) {
        $net[$line->account_id] = ($net[$line->account_id] ?? 0) + $line->debit - $line->credit;
    }
    foreach ($reversal->lines as $line) {
        $net[$line->account_id] = ($net[$line->account_id] ?? 0) + $line->debit - $line->credit;
    }
    check('and it cancels the original on every account', collect($net)->every(fn ($v) => abs($v) < 0.01), json_encode($net));
    check('dated today, in today\'s fiscal year', $reversal->entry_date->format('Y-m-d') === date('Y-m-d') && (int) $reversal->fiscal_year_id === (int) $fiscal->id);
}
check('the mapping is marked reversed', optional($mapping->fresh())->status === 'reversed');
check('the itemised taxes are cleared, so nothing is owed for it', PayrollTaxLine::where('payroll_id', $payroll->id)->doesntExist());
check('a reversing staff transaction is recorded', DB::table('transactions')->where('amount', $payroll->net_salary)->where('type', 1)->exists());

$owingAfter = collect($remit->outstanding());
$dropped = true;
foreach ($owedByAccount as $account => $owed) {
    $was = $owing->first(fn ($r) => (int) $r['account_id'] === (int) $account && (string) $r['month'] === (string) $monthKey);
    $now = $owingAfter->first(fn ($r) => (int) $r['account_id'] === (int) $account && (string) $r['month'] === (string) $monthKey);
    if ($was && abs((($was['outstanding']) - ($now['outstanding'] ?? 0)) - $owed) > 0.51) {
        $dropped = false;
    }
}
check('and the remittance screen owes that much less', $dropped);

$level = DB::transactionLevel();
$call('POST', "/admin/staff/payroll-unpay/{$payroll->id}");
check('unpaying twice changes nothing', JournalEntry::where('reference_type', 'payroll_reversal')->where('reference_id', $payroll->id)->count() === 1);
if (DB::transactionLevel() > $level) {
    finding('Unpaying an already-unpaid payroll returns from inside a database transaction without closing it (PayrollController::unpay, the "already unpaid" branch). Harmless on the web, where the request ends, but any later work on the same connection runs inside that open transaction.');
    DB::rollBack($level);
}

// ---------------------------------------------------------------------------
section('Paying again after a reversal');
// ---------------------------------------------------------------------------

$call('POST', $payPath, ['pay_date' => date('Y-m-d'), 'payment_method' => 2, 'bank_account_id' => $bank->id]);
$payroll = $payroll->fresh();
check('the payroll can be paid again', (int) $payroll->status === 1);
$second = TransactionMapping::where('transaction_type', 'payroll')->where('transaction_id', $payroll->id)->first();
$secondEntry = $second && $second->journal_entry_id ? JournalEntry::find($second->journal_entry_id) : null;
check('with a fresh posted entry', $secondEntry !== null && $secondEntry->is_posted && (int) $secondEntry->id !== (int) $entry->id);
check('and the taxes itemised once more', abs(PayrollTaxLine::where('payroll_id', $payroll->id)->sum('employee_amount') - (float) $payroll->tax) <= 0.51);

$ledgerNet = [];
foreach (JournalEntry::whereIn('reference_type', ['payroll', 'payroll_reversal'])->where('reference_id', $payroll->id)->with('lines')->get() as $e) {
    foreach ($e->lines as $line) {
        $ledgerNet[$line->account_id] = ($ledgerNet[$line->account_id] ?? 0) + $line->debit - $line->credit;
    }
}
check('across pay, unpay and pay the ledger carries exactly one payroll',
    abs(($ledgerNet[$cashAccount] ?? 0) + (float) $payroll->net_salary) < 0.01, json_encode($ledgerNet));

// ---------------------------------------------------------------------------
section('Hourly-paid staff');
// ---------------------------------------------------------------------------

if ($hourly) {
    $response = $call('GET', "/admin/staff/payroll-generate/{$hourly->id}/$month/$year");
    $html = $response->getContent();
    check('the generate page renders for an hourly-paid staff member', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
    $earning = (float) $input($html, 'total_earning');

    // Give them twenty hours this month and see whether the page notices.
    $context = DB::table('student_enrolls')->whereNotNull('session_id')->whereNotNull('section_id')
        ->select('session_id', 'program_id', 'semester_id', 'section_id')->first();
    $subjectId = DB::table('subjects')->min('id');
    for ($i = 0; $i < 5; $i++) {
        DB::table('staff_hourly_attendances')->insert([
            'user_id' => $hourly->id, 'subject_id' => $subjectId,
            'session_id' => $context->session_id, 'program_id' => $context->program_id,
            'semester_id' => $context->semester_id, 'section_id' => $context->section_id,
            'date' => $monthDate->copy()->addDays(2 + $i)->format('Y-m-d'),
            'start_time' => '08:00:00', 'end_time' => '12:00:00', 'attendance' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $response = $call('GET', "/admin/staff/payroll-generate/{$hourly->id}/$month/$year");
    $withHours = (float) $input($response->getContent(), 'total_earning');

    if ($earning == (float) $hourly->basic_salary && $withHours == $earning) {
        finding('Hourly-paid staff: the generate page puts the hourly rate itself (' . $money($hourly->basic_salary)
            . ') as the month\'s earning and ignores the hours worked (20 hours recorded made no difference). An hourly staff member would be paid one hour.');
        check('hours worked change an hourly earning', false, $money($earning) . ' before and after 20 hours');
    } else {
        check('hours worked change an hourly earning', $withHours > $earning, $money($earning) . ' -> ' . $money($withHours));
    }
} else {
    skip('no hourly-paid staff member to try');
}

// ---------------------------------------------------------------------------
section('Loose ends in the routing');
// ---------------------------------------------------------------------------

foreach (['GET' => "/admin/staff/payroll/{$payroll->id}", 'DELETE' => "/admin/staff/payroll/{$payroll->id}"] as $verb => $path) {
    try {
        $response = $call($verb, $path);
        $code = $response->getStatusCode();
    } catch (\Throwable $e) {
        $code = 500;
    }
    if ($code >= 500) {
        finding("$verb $path is routed (resource route) but the controller has no such action: it errors instead of answering. Nothing on the screens links to it.");
    }
}
check('no screen links to the unimplemented show, edit or delete actions',
    !preg_match('#/admin/staff/payroll/\d+/edit|data-method="delete"#', $call('GET', '/admin/staff/payroll', ['salary_type' => 1, 'month' => $month, 'year' => $year])->getContent()));

} finally {
    // To level 0, not one level: the "already unpaid" branch of unpay()
    // returns from inside a transaction it opened and never closes, which
    // leaves this suite one savepoint deeper than it started.
    DB::rollBack(0);
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------

$after = [
    'payrolls' => Payroll::count(),
    'details' => DB::table('payroll_details')->count(),
    'tax_lines' => PayrollTaxLine::count(),
    'journal' => JournalEntry::count(),
    'journal_lines' => JournalEntryLine::count(),
    'transactions' => DB::table('transactions')->count(),
    'mappings' => TransactionMapping::count(),
    'print_settings' => DB::table('print_settings')->count(),
    'hourly_attendance' => DB::table('staff_hourly_attendances')->count(),
];
foreach ($before as $table => $n) {
    check("$table: $n as before", $after[$table] === $n, 'now ' . $after[$table]);
}

if ($findings) {
    echo "\nFindings\n";
    foreach ($findings as $i => $f) {
        echo '  ' . ($i + 1) . ". $f\n";
    }
}

echo "\n----\npassed $passed, failed $failed, skipped $skipped\n";
exit($failed === 0 ? 0 : 1);
