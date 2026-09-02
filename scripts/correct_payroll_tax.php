<?php
/**
 * Re-state payrolls whose tax was computed before the council-surcharge fix.
 *
 * The surcharge was charged on salary instead of on the income tax, so every
 * payroll generated before the fix over-deducted. A posted payroll is also in
 * the ledger, so this does not edit rows: it drives the existing unpay/pay
 * path, which reverses the original journal entry and posts a fresh one. The
 * result is a correction with an audit trail rather than a silent rewrite.
 *
 * Reports what it would do and changes nothing unless --commit is given.
 *
 * Usage: php scripts/correct_payroll_tax.php [--commit]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payroll;
use App\Models\TaxGroup;
use App\Models\TaxSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$commit = in_array('--commit', $argv, true);

// A payroll can be correct in amount and still be posted to the wrong account —
// the withheld-tax account was only fixed after these were re-stated — so
// re-posting has to be requestable on its own.
$repost = in_array('--repost', $argv, true);

/** The employee and employer tax stack, as the payroll screen computes it. */
function stackFor($salary, $exemptions): array
{
    $groups = TaxGroup::getEffectiveGroups(now());
    $base = TaxSetting::getEffectiveBrackets(now());
    $dependent = TaxSetting::getEffectiveDependentBrackets(now());

    $groupResults = [];
    $employee = 0;
    $employer = 0;

    foreach ($groups as $group) {
        $result = $group->calculateTax($salary, $exemptions);
        $employee += $result['amount'];

        $bracket = $group->applicableBracket($salary);
        $groupEmployer = 0;

        if ($bracket && in_array($bracket->paid_by, ['employer', 'both'], true)
            && !$exemptions->has($bracket->id)) {
            $groupEmployer = $bracket->calculateEmployerContribution($salary);
        }

        $result['employer'] = $groupEmployer;
        $employer += $groupEmployer;
        $groupResults[$group->id] = $result;
    }

    foreach ($base as $tax) {
        if ($tax->min_amount > $salary || $tax->max_amount < $salary) {
            continue;
        }

        if (!$exemptions->has($tax->id)) {
            $employee += $tax->calculateEmployeeContribution($salary);
            $employer += $tax->calculateEmployerContribution($salary);
        }
    }

    foreach ($dependent as $tax) {
        if ($tax->min_amount > $salary || $tax->max_amount < $salary) {
            continue;
        }

        // The surcharge is a percentage of the source tax as assessed, both
        // sides — this is the step that was skipped when it sat in a group.
        $source = ($groupResults[$tax->depends_on_id]['amount'] ?? 0)
            + ($groupResults[$tax->depends_on_id]['employer'] ?? 0);

        if ($exemptions->has($tax->id)) {
            continue;
        }

        if (in_array($tax->paid_by, ['employee', 'both'], true)) {
            $employee += $tax->tax_type == 2
                ? $tax->fixed_amount
                : ($source / 100) * $tax->percentange;
        }

        if (in_array($tax->paid_by, ['employer', 'both'], true)) {
            $employer += $tax->tax_type == 2
                ? $tax->employer_fixed_amount
                : ($source / 100) * $tax->employer_percentage;
        }
    }

    return ['employee' => round($employee), 'employer' => round($employer, 2)];
}

echo "\n" . ($commit ? "Writing." : "Reporting only. Nothing will be written. Re-run with --commit to apply.") . "\n\n";

$payrolls = Payroll::with('user')->orderBy('id')->get();
$toFix = [];

foreach ($payrolls as $payroll) {
    $exemptions = App\Models\StaffTaxExemption::where('user_id', $payroll->user_id)
        ->get()->keyBy('tax_setting_id');

    $correct = stackFor($payroll->total_earning, $exemptions);
    $newNet = round($payroll->gross_salary - $correct['employee']);
    $newCost = round($payroll->gross_salary + $correct['employer'], 2);

    $changed = abs($payroll->tax - $correct['employee']) > 0.5
        || abs($payroll->employer_tax - $correct['employer']) > 0.5;

    printf("  payroll #%-3d %-24s %s  %s\n", $payroll->id,
        substr(trim($payroll->user->first_name . ' ' . $payroll->user->last_name), 0, 24),
        substr($payroll->salary_month, 0, 7),
        $payroll->status == 1 ? 'POSTED' : 'unposted');
    printf("      tax          %12s -> %-12s %s\n",
        number_format($payroll->tax), number_format($correct['employee']),
        $changed ? '(' . number_format($payroll->tax - $correct['employee']) . ' over-deducted)' : '(unchanged)');
    printf("      employer tax %12s -> %s\n",
        number_format($payroll->employer_tax), number_format($correct['employer']));
    printf("      net          %12s -> %s\n",
        number_format($payroll->net_salary), number_format($newNet));
    printf("      total cost   %12s -> %s\n",
        number_format($payroll->total_cost), number_format($newCost));

    if ($changed || ($repost && $payroll->status == 1)) {
        $toFix[] = [$payroll, $correct, $newNet, $newCost];
    }
}

if ($toFix === []) {
    echo "\nNothing to correct.\n";
    exit(0);
}

printf("\n%d payroll(s) %s.\n", count($toFix), $repost ? 'will be re-posted' : 'need correcting');

if (!$commit) {
    echo "\nNothing was written.\n";
    exit(0);
}

// Acting as the admin: unpay() and pay() both record who did it.
Auth::guard('web')->login(App\User::where('is_admin', 1)->firstOrFail());
$controller = app(App\Http\Controllers\Admin\PayrollController::class);

foreach ($toFix as [$payroll, $correct, $newNet, $newCost]) {
    DB::transaction(function () use ($payroll, $correct, $newNet, $newCost, $controller) {
        $wasPosted = $payroll->status == 1;
        $payDate = $payroll->pay_date;
        $method = $payroll->payment_method;
        $bank = $payroll->bank_account_id;

        if ($wasPosted) {
            // Reverses the journal entry and records the reversal.
            $controller->unpay(new Illuminate\Http\Request(), $payroll->id);
            $payroll->refresh();
        }

        $payroll->tax = $correct['employee'];
        $payroll->employer_tax = $correct['employer'];
        $payroll->net_salary = $newNet;
        $payroll->total_cost = $newCost;
        $payroll->save();

        if ($wasPosted) {
            $controller->pay(Illuminate\Http\Request::create('/x', 'POST', [
                'pay_date' => $payDate,
                'payment_method' => $method,
                'bank_account_id' => $bank,
            ]), $payroll->id);
        }
    });

    printf("  corrected payroll #%d\n", $payroll->id);
}

echo "\nDone.\n";
