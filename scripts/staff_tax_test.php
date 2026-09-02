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
$irpp = TaxGroup::find(1)->calculateTax(180000)['amount'];
$stack = taxFor(180000);

check('income tax at 180,000 is unchanged', abs($irpp - 7929) < 0.01, (string) $irpp);
check('the surcharge is 10% of the income tax', abs(($stack['parts']['Additional Council Tax'] ?? -1) - 792.9) < 0.5,
    number_format($stack['parts']['Additional Council Tax'] ?? -1, 2));
check('and not 10% of salary', ($stack['parts']['Additional Council Tax'] ?? 0) < 1000);
check('the whole employee stack is 21,282', abs($stack['total'] - 21282) < 1,
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

$coverage = TaxGroup::find(1)->coverageGaps();

check('the income tax table has holes', count($coverage['gaps']) === 5,
    count($coverage['gaps']) . ' found');
check('the widest is 120,001-169,899', collect($coverage['gaps'])
    ->contains(fn ($g) => $g['from'] == 120001 && $g['to'] == 169899));
check('a hole names the band actually charged', collect($coverage['gaps'])
    ->every(fn ($g) => !empty($g['charged_as'])));
check('the top band has a ceiling', $coverage['open_top'] !== null
    && $coverage['open_top']['from'] == 180001);

// Every current salary sits exactly on a band, which is why nothing is
// mischarged today — and why this is brittle rather than broken.
$inGap = App\User::where('status', '1')->where('basic_salary', '>', 0)->get()
    ->filter(fn ($u) => TaxGroup::find(1)->gapFor($u->basic_salary) !== null);

check('no current salary falls in a hole', $inGap->isEmpty(), $inGap->count() . ' do');
check('but a salary between bands is detected', TaxGroup::find(1)->gapFor(150000) !== null);
check('and one above the top band is too', TaxGroup::find(1)->gapFor(400000) !== null);
check('a covered salary is not flagged', TaxGroup::find(1)->gapFor(180000) === null);

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
check('withheld tax sits in the withholding account', $net('443') < 0,
    number_format($net('443'), 2));
check('and it equals the tax actually deducted',
    abs(abs($net('443')) - App\Models\Payroll::where('status', 1)->sum('tax')) < 1,
    number_format(abs($net('443')), 2) . ' vs ' . number_format(App\Models\Payroll::where('status', 1)->sum('tax'), 2));

echo "\n$passed passed, $failed failed\n";
