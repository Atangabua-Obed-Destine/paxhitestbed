<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\StaffTaxExemption;
use App\Models\TaxGroup;
use App\Models\TaxSetting;
use Carbon\Carbon;

/**
 * What each individual tax takes from a salary.
 *
 * The payroll has always known its total — `payrolls.tax` — and never the
 * parts. The parts are what a declaration to DGI or CNPS is made of, and what
 * decides which liability account each franc accrues to, so they have to be
 * computed once and recorded rather than re-derived on demand.
 *
 * ONE WARNING BEFORE CHANGING ANYTHING HERE. The same rules are implemented in
 * three other places:
 *
 *   - TaxGroup::calculateTax() — the banded part, which this calls rather than
 *     reimplements
 *   - resources/views/admin/payroll/generate.blade.php — the payroll screen
 *   - StaffTaxReportController::calculateStaffTaxes() — the distribution report
 *
 * They agree today only because they were hand-synchronised, and the figures
 * this service produces must reconcile exactly to the total the payroll screen
 * stored — a backfill that disagrees is refused rather than written. So a
 * change here that is not mirrored there does not produce a slightly different
 * number; it stops payrolls from being recorded at all. `scripts/staff_tax_test.php`
 * and `scripts/tax_remittance_test.php` pin all four to each other.
 *
 * Consolidating the other two is worthwhile and is deliberately not done here:
 * doing it inside a change that also moves money between liability accounts
 * would make both harder to verify.
 */
class PayrollTaxBreakdownService
{
    /**
     * The itemised taxes for a payroll, as at its own salary month.
     *
     * Deliberately keyed off the payroll's month and not today's date: a
     * payroll paid in August is a record of August's rules, and a rate changed
     * in October must not rewrite it.
     *
     * @return array<int, array{tax_group_id:?int, tax_setting_id:?int, label:string,
     *                          employee:float, employer:float, liability_account_id:?int}>
     */
    public function forPayroll(Payroll $payroll): array
    {
        return $this->forSalary(
            (float) $payroll->total_earning,
            Carbon::parse($payroll->salary_month),
            $this->exemptionsFor($payroll->user_id, $payroll->salary_month)
        );
    }

    /** The exemptions in force for a staff member in a given month. */
    public function exemptionsFor($userId, $salaryMonth)
    {
        return StaffTaxExemption::where('user_id', $userId)
            ->notExpired(Carbon::parse($salaryMonth))
            ->get()
            ->keyBy('tax_setting_id');
    }

    /**
     * The itemised taxes on a salary under the rules effective on a date.
     *
     * The three buckets and the order they run in mirror the payroll screen
     * exactly, because the total has to match what that screen stored:
     * grouped taxes, then ungrouped base taxes, then the dependent ones —
     * which are a percentage of another tax and so can only be worked out once
     * the others are known.
     */
    public function forSalary(float $salary, Carbon $date, $exemptions = null): array
    {
        $exemptions = $exemptions ?: collect();
        $lines = [];

        // --- Grouped taxes: income tax, local development, housing, CRTV ----
        // A group charges the one band whose floor is closest below the
        // salary. Dependent rows never live in a group — the migration that
        // moved the council tax out made sure of that — so nothing here has to
        // handle them.
        $groupResults = [];

        foreach (TaxGroup::getEffectiveGroups($date) as $group) {
            $result = $group->calculateTax($salary, $exemptions);
            $bracket = $group->applicableBracket($salary);

            $employer = 0.0;

            if ($bracket && in_array($bracket->paid_by, ['employer', 'both'], true)
                && !$this->isExempt($bracket->id, $exemptions)) {
                $employer = (float) $bracket->calculateEmployerContribution($salary);
            }

            $groupResults[$group->id] = [
                'employee' => (float) $result['amount'],
                'employer' => $employer,
            ];

            if ($result['amount'] == 0.0 && $employer == 0.0) {
                // Nothing was charged. A zero row on a declaration is noise.
                continue;
            }

            $lines[] = [
                'tax_group_id' => (int) $group->id,
                'tax_setting_id' => $bracket ? (int) $bracket->id : null,
                'label' => $group->title,
                'employee' => (float) $result['amount'],
                'employer' => $employer,
                'liability_account_id' => $this->liabilityFor($bracket, $group),
            ];
        }

        // --- Ungrouped base taxes: employment fund, social insurance --------
        $standaloneResults = [];

        foreach (TaxSetting::getEffectiveBrackets($date) as $tax) {
            $standaloneResults[$tax->id] = ['employee' => 0.0, 'employer' => 0.0];

            // Ungrouped taxes read both bounds, unlike the banded ones. Same
            // column, two meanings — long-standing, and not this change's to
            // settle.
            if ($salary < $tax->min_amount || $salary > $tax->max_amount) {
                continue;
            }

            [$employee, $employer] = $this->amountsFor($tax, $salary, $exemptions);

            $standaloneResults[$tax->id] = ['employee' => $employee, 'employer' => $employer];

            if ($employee == 0.0 && $employer == 0.0) {
                continue;
            }

            $lines[] = [
                'tax_group_id' => null,
                'tax_setting_id' => (int) $tax->id,
                'label' => $tax->title,
                'employee' => $employee,
                'employer' => $employer,
                'liability_account_id' => $this->liabilityFor($tax, null),
            ];
        }

        // --- Dependent taxes: a percentage of another tax -------------------
        // The additional council tax is 10% of the income tax, not of the
        // salary. Its base is the source's whole assessed charge, both sides.
        foreach (TaxSetting::getEffectiveDependentBrackets($date) as $tax) {
            if ($salary < $tax->min_amount || $salary > $tax->max_amount) {
                continue;
            }

            $source = 0.0;

            if ($tax->depends_on_type === 'tax_group' && isset($groupResults[$tax->depends_on_id])) {
                $source = $groupResults[$tax->depends_on_id]['employee']
                        + $groupResults[$tax->depends_on_id]['employer'];
            } elseif ($tax->depends_on_type === 'tax_setting' && isset($standaloneResults[$tax->depends_on_id])) {
                $source = $standaloneResults[$tax->depends_on_id]['employee']
                        + $standaloneResults[$tax->depends_on_id]['employer'];
            }

            [$employee, $employer] = $this->amountsFor($tax, $source, $exemptions, true);

            if ($employee == 0.0 && $employer == 0.0) {
                continue;
            }

            $lines[] = [
                'tax_group_id' => null,
                'tax_setting_id' => (int) $tax->id,
                'label' => $tax->title,
                'employee' => $employee,
                'employer' => $employer,
                'liability_account_id' => $this->liabilityFor($tax, null),
            ];
        }

        return $lines;
    }

    /** Both sides of a single ungrouped tax, exemptions honoured. */
    private function amountsFor(TaxSetting $tax, float $base, $exemptions, bool $isDependent = false): array
    {
        $employee = 0.0;
        $employer = 0.0;
        $paidBy = $tax->paid_by ?: 'employee';

        if ($this->isExempt($tax->id, $exemptions)) {
            // An exemption with a custom rate charges that rate; a plain one
            // charges nothing. The employer side is zero either way, which
            // matches the payslip.
            $exemption = $exemptions->get($tax->id);

            if ($exemption && $exemption->custom_percentage && $tax->tax_type == 1) {
                $taxable = $isDependent ? $base : max(0, $base - ($tax->max_no_taxable_amount ?? 0));
                $employee = ($taxable / 100) * $exemption->custom_percentage;
            } elseif ($exemption && $exemption->custom_fixed_amount && $tax->tax_type == 2) {
                $employee = (float) $exemption->custom_fixed_amount;
            }

            return [$employee, $employer];
        }

        // A dependent tax's base is another tax's charge, which has no
        // tax-free allowance to subtract — the allowance belongs to salary.
        $taxable = $isDependent ? $base : max(0, $base - ($tax->max_no_taxable_amount ?? 0));

        if ($paidBy === 'employee' || $paidBy === 'both') {
            $employee = $tax->tax_type == 2
                ? (float) ($tax->fixed_amount ?? 0)
                : ($taxable / 100) * (float) ($tax->percentange ?? 0);
        }

        if ($paidBy === 'employer' || $paidBy === 'both') {
            $employer = $tax->tax_type == 2
                ? (float) ($tax->employer_fixed_amount ?? 0)
                : ($taxable / 100) * (float) ($tax->employer_percentage ?? 0);
        }

        return [$employee, $employer];
    }

    /** An exemption counts only while it has not expired. */
    private function isExempt($taxSettingId, $exemptions): bool
    {
        if (!$exemptions || !$exemptions->has($taxSettingId)) {
            return false;
        }

        $exemption = $exemptions->get($taxSettingId);

        return !($exemption && method_exists($exemption, 'isExpired') && $exemption->isExpired());
    }

    /**
     * The account a tax accrues to.
     *
     * The bracket's own setting wins over its group's, so one band can be
     * pointed elsewhere without splitting the group. Null is a legitimate
     * answer — the posting code then falls back to findTaxPayableAccount(),
     * which is what it did before any of this existed.
     */
    private function liabilityFor($setting, $group): ?int
    {
        if ($setting && !empty($setting->liability_account_id)) {
            return (int) $setting->liability_account_id;
        }

        if ($group && !empty($group->liability_account_id)) {
            return (int) $group->liability_account_id;
        }

        return null;
    }

    /** What the itemised lines come to, as the payroll would record it. */
    public function totals(array $lines): array
    {
        return [
            'employee' => round(array_sum(array_column($lines, 'employee')), 2),
            'employer' => round(array_sum(array_column($lines, 'employer')), 2),
        ];
    }

    /**
     * Do the itemised lines add up to what the payroll actually charged?
     *
     * The payroll screen computed the total independently, and this service
     * recomputes the parts. If they disagree the parts are wrong — the total
     * is the figure the staff member was actually paid against — and writing
     * them would put a plausible but false breakdown on a tax declaration.
     *
     * Rounding is why the tolerance is not zero: the screen rounds the total
     * once, this sums unrounded parts, so 21,281.90 against a stored 21,282 is
     * agreement, not a discrepancy.
     */
    public function reconciles(Payroll $payroll, array $lines, float $tolerance = 0.51): bool
    {
        $totals = $this->totals($lines);

        return abs($totals['employee'] - (float) $payroll->tax) <= $tolerance
            && abs($totals['employer'] - (float) ($payroll->employer_tax ?? 0)) <= $tolerance;
    }

    /**
     * Write the itemised taxes for a payroll, replacing anything already held.
     *
     * Refuses rather than guesses when the parts do not add up to the payroll's
     * own total — see reconciles(). The caller decides what to do about that;
     * the payroll itself is not blocked, because the money has already moved
     * and a missing breakdown is recoverable while a wrong one is not.
     *
     * @return array{written:int, lines:array, reconciled:bool}
     */
    public function record(Payroll $payroll): array
    {
        $lines = $this->forPayroll($payroll);

        if (!$this->reconciles($payroll, $lines)) {
            return ['written' => 0, 'lines' => $lines, 'reconciled' => false];
        }

        $this->clear($payroll);

        foreach ($lines as $line) {
            \App\Models\PayrollTaxLine::create([
                'payroll_id' => $payroll->id,
                'user_id' => $payroll->user_id,
                'salary_month' => $payroll->salary_month,
                'tax_group_id' => $line['tax_group_id'],
                'tax_setting_id' => $line['tax_setting_id'],
                'label' => $line['label'],
                'employee_amount' => round($line['employee'], 2),
                'employer_amount' => round($line['employer'], 2),
                'liability_account_id' => $line['liability_account_id'],
            ]);
        }

        return ['written' => count($lines), 'lines' => $lines, 'reconciled' => true];
    }

    /** Forget a payroll's itemised taxes — it is no longer a paid payroll. */
    public function clear(Payroll $payroll): void
    {
        \App\Models\PayrollTaxLine::where('payroll_id', $payroll->id)->delete();
    }
}
