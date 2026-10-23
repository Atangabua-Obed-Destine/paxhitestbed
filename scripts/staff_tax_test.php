<?php
/**
 * Staff tax is calculated, configured and posted correctly.
 *
 * Three things here were wrong in ways that produced a plausible number rather
 * than an error, which is why they survived:
 *
 *   The council surcharge charged 10% of SALARY instead of 10% of the income
 *   tax, because a dependent tax filed inside a tax group is handed the salary
 *   and its dependency is never read.
 *
 *   Withheld tax was credited to 441 Etat - TVA, because the account search
 *   ended in a LIKE '44%' that matched the VAT account.
 *
 *   Bracket selection ignores max_amount, so a salary with no band of its own
 *   is quietly charged the band below it.
 *
 * The same rules are implemented three times — in TaxGroup, in the payroll
 * screen and in the tax report — so several cases below exist purely to pin
 * those three to each other.
 *
 * Every mutation runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/staff_tax_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TaxGroup;
use App\Models\TaxSetting;
use App\Services\PayrollAccountingService;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

/** The employee tax stack, computed the way payroll does it. */
function taxFor($salary): array
{
    $groups = TaxGroup::getEffectiveGroups(now());
    $base = TaxSetting::getEffectiveBrackets(now());
    $dependent = TaxSetting::getEffectiveDependentBrackets(now());

    $groupResults = [];
    $total = 0;
    $parts = [];

    foreach ($groups as $group) {
        $result = $group->calculateTax($salary);
        $groupResults[$group->id] = $result;
        $parts[$group->title] = $result['amount'];
        $total += $result['amount'];
    }

    foreach ($base as $tax) {
        if ($tax->min_amount <= $salary && $tax->max_amount >= $salary) {
            $amount = $tax->calculateEmployeeContribution($salary);
            $parts[$tax->title] = $amount;
            $total += $amount;
        }
    }

    foreach ($dependent as $tax) {
        if ($tax->min_amount > $salary || $tax->max_amount < $salary) {
            continue;
        }

        $source = $groupResults[$tax->depends_on_id]['amount'] ?? 0;
        $amount = ($source / 100) * $tax->percentange;
        $parts[$tax->title] = $amount;
        $total += $amount;
    }

    return ['total' => $total, 'parts' => $parts];
}

echo "\n== The council surcharge is charged on the tax, not the salary ==\n";

$act = TaxSetting::where('title', 'Additional Council Tax')->first();

check('it is configured as dependent', $act && $act->is_dependent);
check('it depends on the income tax group', $act && $act->depends_on_type === 'tax_group'
    && (int) $act->depends_on_id === 1);
check('it belongs to no tax group', $act && $act->tax_group_id === null,
    'group ' . ($act->tax_group_id ?? 'null'));

// A dependent tax inside a group is handed the salary, so 10% of 180,000
// produced 18,000 — a surcharge 22x larger than the tax it surcharges.
//
// The figures here were re-based when the income tax was configured from the
// DGI barème (see tax:configure-cameroon). They used to read 7,929 at 180,000,
// which was the old sample-point table's answer and 252 more than the barème
// charges; the stack was 21,282 for the same reason. The rule being checked is
// unchanged — the surcharge is a tenth of the income tax, not a tenth of pay.
$irpp = TaxGroup::find(1)->calculateTax(180000)['amount'];
$stack = taxFor(180000);

check('income tax at 180,000 matches the barème', abs($irpp - 7677) < 1, (string) $irpp);
check('the surcharge is 10% of the income tax',
    abs(($stack['parts']['Additional Council Tax'] ?? -1) - $irpp / 10) < 0.5,
    number_format($stack['parts']['Additional Council Tax'] ?? -1, 2));
check('and not 10% of salary', ($stack['parts']['Additional Council Tax'] ?? 0) < 1000);
check('the whole employee stack is 21,005', abs($stack['total'] - 21005) < 1,
    number_format($stack['total'], 2));

// Below the surcharge's own floor it must not apply at all.
$low = taxFor(50000);
check('no surcharge below its floor', ($low['parts']['Additional Council Tax'] ?? 0) == 0);

echo "\n== The payroll screen and the tax report agree ==\n";

// The rules live in three places. They agree today only because they were
// hand-synchronised, so this is the guard that catches the next divergence.
$reportController = new App\Http\Controllers\Admin\StaffTaxReportController();
$method = new ReflectionMethod($reportController, 'calculateStaffTaxes');
$method->setAccessible(true);

$groups = TaxGroup::getEffectiveGroups(now());
$standalone = TaxSetting::active()->standalone()->effectiveOn(now())->ordered()->get();

$mismatch = [];

foreach (App\User::where('status', '1')->where('basic_salary', '>', 0)->get() as $user) {
    $exemptions = App\Models\StaffTaxExemption::where('user_id', $user->id)
        ->get()->keyBy('tax_setting_id');

    $reported = $method->invoke($reportController, $user, $groups, $standalone, $exemptions);
    $direct = taxFor($user->basic_salary);

    if (abs($reported['employee_tax_total'] - $direct['total']) > 0.01) {
        $mismatch[] = $user->id . ': report ' . round($reported['employee_tax_total'], 2)
            . ' vs ' . round($direct['total'], 2);
    }
}

check('every staff member gets the same figure both ways', $mismatch === [],
    implode('; ', array_slice($mismatch, 0, 3)));

echo "\n== Withheld tax is a withholding, not VAT ==\n";

$service = app(PayrollAccountingService::class);
$find = new ReflectionMethod($service, 'findTaxPayableAccount');
$find->setAccessible(true);
$account = $find->invoke($service);

check('an account is found', $account !== null);
check('it is not the VAT account', $account && $account->account_code !== '441',
    $account->account_code ?? 'null');
check('it is a withholding account', $account && $account->account_code === '443',
    ($account->account_code ?? '?') . ' ' . ($account->account_name ?? ''));
check('it is a class 4 liability', $account && $account->class_number == 4
    && $account->account_type === 'liability');
check('and it is postable', $account && $account->account_category === 'detail');

echo "\n== Uncovered salary ranges are detected ==\n";

// This section used to assert that the income tax table HAD five holes —
// 90,001-109,899 and the rest — because that was the shape of the installed
// configuration, and the gaps were the defect being documented. The table is
// now the DGI barème, five contiguous bands from zero, so there are none.
//
// The detector still matters, so it is proved against a hole made on purpose
// inside a transaction rather than against a live misconfiguration. That is
// the stronger test: it holds whatever the live table happens to be.
$coverage = TaxGroup::find(1)->coverageGaps();

check('the income tax table has no holes', $coverage['gaps'] === [],
    count($coverage['gaps']) . ' found: ' . json_encode(array_slice($coverage['gaps'], 0, 2)));
check('and no salary in it falls in one',
    collect([0, 63500, 150000, 400000, 1000000, 2000000])
        ->every(fn ($s) => TaxGroup::find(1)->gapFor($s) === null));

$inGap = App\User::where('status', '1')->where('basic_salary', '>', 0)->get()
    ->filter(fn ($u) => TaxGroup::find(1)->gapFor($u->basic_salary) !== null);

check('no current salary falls in a hole', $inGap->isEmpty(), $inGap->count() . ' do');

// Now break it on purpose and prove the detector still sees it.
DB::beginTransaction();

try {
    $band = TaxSetting::where('tax_group_id', 1)->where('status', 1)
        ->orderBy('min_amount')->first();
    $originalMax = $band->max_amount;

    // Pull the first band's ceiling down, leaving a hole before the second.
    $band->max_amount = $originalMax - 10000;
    $band->save();

    $broken = TaxGroup::find(1)->coverageGaps();

    check('a hole made on purpose is detected', count($broken['gaps']) === 1,
        count($broken['gaps']) . ' found');
    check('it names the range', ($broken['gaps'][0]['from'] ?? null) == $originalMax - 9999);
    check('and the band a salary in it would be charged under',
        !empty($broken['gaps'][0]['charged_as']));
    check('a salary inside the hole is flagged',
        TaxGroup::find(1)->gapFor($originalMax - 5000) !== null);
    check('a salary outside it is not',
        TaxGroup::find(1)->gapFor(100000) === null);
} finally {
    DB::rollBack();
}

check('the table is contiguous again after the rollback',
    TaxGroup::find(1)->coverageGaps()['gaps'] === []);

// The top band is deliberately bounded at fifty million rather than left open,
// so coverageGaps() reports a ceiling. Nothing is paid anywhere near it; the
// point is that the bound is a decision rather than an oversight.
check('the top band is bounded, and the bound is reported',
    $coverage['open_top'] !== null && $coverage['open_top']['from'] == 50000001,
    json_encode($coverage['open_top']));

echo "\n== The configuration refuses the combination that caused this ==\n";

DB::beginTransaction();
try {
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $admin = App\User::where('is_admin', 1)->first();
    Illuminate\Support\Facades\Auth::guard('web')->login($admin);

    $get = Illuminate\Http\Request::create('/admin/staff/tax-setting', 'GET');
    $get->setLaravelSession(app('session.store'));
    $kernel->handle($get);
    $token = app('session.store')->token();

    $before = TaxSetting::count();

    $post = Illuminate\Http\Request::create('/admin/staff/tax-setting', 'POST', [
        '_token' => $token,
        'title' => 'ZZ Dependent In Group',
        'tax_group_id' => 1,
        'is_dependent' => 1,
        'depends_on_type' => 'tax_group',
        'depends_on_id' => 1,
        'tax_type' => 1,
        'paid_by' => 'employee',
        'min_amount' => 0,
        'max_amount' => 1000000,
        'percentange' => 10,
    ]);
    $post->setLaravelSession(app('session.store'));
    $kernel->handle($post);

    check('a dependent tax cannot be saved into a group', TaxSetting::count() === $before,
        'count went ' . $before . ' -> ' . TaxSetting::count());
    check('nothing by that name exists',
        TaxSetting::where('title', 'ZZ Dependent In Group')->doesntExist());
} finally {
    DB::rollBack();
}

echo "\n== The ledger is intact ==\n";

$t = DB::table('journal_entry_lines')->selectRaw('SUM(debit) d, SUM(credit) c')->first();
check('debits equal credits', abs($t->d - $t->c) < 0.01,
    number_format($t->d, 2) . ' vs ' . number_format($t->c, 2));

$unbalanced = DB::table('journal_entry_lines')->select('journal_entry_id')
    ->groupBy('journal_entry_id')
    ->havingRaw('ABS(SUM(debit) - SUM(credit)) > 0.01')->get()->count();
check('every entry balances on its own', $unbalanced === 0, $unbalanced . ' unbalanced');

// The original misposting is left in place and reversed rather than deleted,
// so the test is whether the VAT account is square once every payroll entry
// and its reversal are counted — not whether a line ever touched it.
$payrollEntries = DB::table('journal_entries')
    ->where('reference_type', 'LIKE', 'payroll%')->pluck('id');

$net = function (string $code) use ($payrollEntries) {
    $id = App\Models\ChartOfAccount::where('account_code', $code)->value('id');

    $row = DB::table('journal_entry_lines')
        ->whereIn('journal_entry_id', $payrollEntries)
        ->where('account_id', $id)
        ->selectRaw('COALESCE(SUM(debit), 0) d, COALESCE(SUM(credit), 0) c')->first();

    return round($row->d - $row->c, 2);
};

check('payroll leaves the VAT account square', $net('441') == 0.0,
    number_format($net('441'), 2));

echo "\n== A paid payroll credits each authority what it is owed ==\n";

// These used to read whatever the installation happened to hold, and on this
// one they read nothing at all: no payroll has ever been paid here, so every
// figure came back 0.00 and three checks failed for want of data rather than
// for want of correctness.
//
// So the payroll is built and paid here, inside a transaction that is rolled
// back. That tests the posting itself — which is the thing that has to be
// right — instead of testing whether somebody has run payroll lately.

DB::beginTransaction();

try {
    $admin = App\User::where('is_admin', 1)->first();
    $subject = App\User::where('status', '1')->where('basic_salary', '>=', 100000)->first();
    $salary = (float) $subject->basic_salary;

    $breakdown = app(App\Services\PayrollTaxBreakdownService::class);
    $month = Carbon\Carbon::parse('2026-10-01');
    $lines = $breakdown->forSalary($salary, $month);
    $totals = $breakdown->totals($lines);

    $payroll = App\Models\Payroll::create([
        'user_id' => $subject->id,
        'basic_salary' => $salary,
        'salary_type' => 1,
        'total_earning' => $salary,
        'total_allowance' => 0,
        'bonus' => 0,
        'total_deduction' => 0,
        'gross_salary' => $salary,
        'tax' => round($totals['employee'], 2),
        'employer_tax' => round($totals['employer'], 2),
        'net_salary' => round($salary - $totals['employee'], 2),
        'total_cost' => round($salary + $totals['employer'], 2),
        'salary_month' => $month->toDateString(),
        // A paid payroll carries the date it was paid, and the posting needs
        // it: it is the journal entry's own date and what the accounting
        // period is looked up by. Without it the entry is simply not created.
        'pay_date' => $month->copy()->endOfMonth()->toDateString(),
        'payment_method' => 'bank',
        'status' => 1,
        'created_by' => $admin->id,
    ]);

    $recorded = $breakdown->record($payroll);
    check('the breakdown reconciles with what was charged', $recorded['reconciled']);

    $entry = app(PayrollAccountingService::class)
        ->createPayrollJournalEntry($payroll, $admin->id);

    check('a journal entry is posted', $entry !== null);

    if ($entry) {
        $entryLines = DB::table('journal_entry_lines')->where('journal_entry_id', $entry->id);

        $sum = $entryLines->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
        check('it balances', abs($sum->d - $sum->c) < 0.01,
            number_format($sum->d, 2) . ' vs ' . number_format($sum->c, 2));

        $credited = function (string $code) use ($entry) {
            $id = App\Models\ChartOfAccount::where('account_code', $code)->value('id');
            $row = DB::table('journal_entry_lines')
                ->where('journal_entry_id', $entry->id)->where('account_id', $id)
                ->selectRaw('COALESCE(SUM(credit),0) c, COALESCE(SUM(debit),0) d')->first();

            return round($row->c - $row->d, 2);
        };

        $state = $credited('443');
        $social = $credited('431');

        check('the State is credited its withholdings', $state > 0, number_format($state, 2));
        check('the social fund is credited the contributions', $social > 0, number_format($social, 2));
        check('nothing is credited to the VAT account', $credited('441') == 0.0,
            number_format($credited('441'), 2));

        // The split has to be real, not everything piled on one side.
        check('the State does not hold the social share too',
            $state < (float) $payroll->tax,
            'the social contribution is still being credited to the State');

        // And together they must account for every franc withheld, from both
        // sides, or something has gone somewhere it does not belong.
        $withheld = (float) $payroll->tax + (float) $payroll->employer_tax;

        check('the two together hold everything withheld',
            abs(($state + $social) - $withheld) < 1,
            number_format($state + $social, 2) . ' vs ' . number_format($withheld, 2));

        // The itemisation is what drives that split, so it must agree with it.
        $byAccount = DB::table('payroll_tax_lines')->where('payroll_id', $payroll->id)
            ->groupBy('liability_account_id')
            ->selectRaw('liability_account_id, SUM(employee_amount + employer_amount) total')
            ->pluck('total', 'liability_account_id');

        check('the itemisation agrees with the State credit',
            abs((float) ($byAccount[145] ?? 0) - $state) < 1,
            number_format((float) ($byAccount[145] ?? 0), 2) . ' vs ' . number_format($state, 2));
        check('and with the social credit',
            abs((float) ($byAccount[142] ?? 0) - $social) < 1,
            number_format((float) ($byAccount[142] ?? 0), 2) . ' vs ' . number_format($social, 2));
    }
} finally {
    DB::rollBack();
}

check('nothing was left in the ledger',
    DB::table('journal_entries')->where('reference_type', 'LIKE', 'payroll%')->count()
        === $payrollEntries->count());
check('and no payroll was left behind',
    App\Models\Payroll::where('salary_month', '2026-10-01')->count() === 0);

echo "\n$passed passed, $failed failed\n";
