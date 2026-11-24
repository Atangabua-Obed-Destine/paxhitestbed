<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\StaffLoan;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saving a payroll, for one staff member from the payroll page or for many at
 * once from the list. Both go through here so a bulk-generated payroll is
 * exactly what the page would have saved.
 */
class PayrollWriter
{
    public function __construct(private PayrollCalculator $calculator)
    {
    }

    /**
     * Store the figures PayrollCalculator worked out, with their lines and
     * itemised taxes, in one transaction.
     *
     * Saving new figures clears any approval: what was approved is no longer
     * what would be paid.
     */
    public function save(User $user, string $salaryMonth, array $calc, int $byUserId): Payroll
    {
        if (!empty($calc['problems'])) {
            throw new \RuntimeException(implode(' ', $calc['problems']));
        }

        return DB::transaction(function () use ($user, $salaryMonth, $calc, $byUserId) {
            $existing = Payroll::where('user_id', $user->id)->where('salary_month', $salaryMonth)->lockForUpdate()->first();

            if ($existing && (int) $existing->status === 1) {
                throw new \RuntimeException(__('This payroll has been paid and can no longer be edited. Reverse the payment first.'));
            }

            $payroll = Payroll::updateOrCreate(
                ['user_id' => $user->id, 'salary_month' => $salaryMonth],
                [
                    'basic_salary' => $calc['basic_salary'],
                    'salary_type' => $calc['salary_type'],
                    'total_earning' => $calc['total_earning'],
                    'hours_worked' => $calc['hours_worked'],
                    'paid_days' => $calc['paid_days'] ?? null,
                    'period_days' => $calc['period_days'] ?? null,
                    'total_allowance' => $calc['total_allowance'],
                    'bonus' => 0,
                    'total_deduction' => $calc['total_deduction'],
                    'gross_salary' => $calc['gross_salary'],
                    'taxable_salary' => $calc['taxable_salary'],
                    'tax' => $calc['tax'],
                    'employer_tax' => $calc['employer_tax'],
                    'net_salary' => $calc['net_salary'],
                    'total_cost' => $calc['total_cost'],
                    'status' => '0',
                    'approved_by' => null,
                    'approved_at' => null,
                    'created_by' => $byUserId,
                ]
            );

            PayrollDetail::where('payroll_id', $payroll->id)->delete();

            foreach ($calc['allowances'] as $line) {
                PayrollDetail::create([
                    'payroll_id' => $payroll->id,
                    'title' => $line['title'],
                    'amount' => $line['amount'],
                    'is_taxable' => $line['is_taxable'],
                    'status' => '1',
                ]);
            }

            foreach ($calc['deductions'] as $line) {
                PayrollDetail::create([
                    'payroll_id' => $payroll->id,
                    'staff_loan_id' => $line['loan_id'] ?? null,
                    'title' => $line['title'],
                    'amount' => $line['amount'],
                    'status' => '0',
                ]);
            }

            // Itemise the tax in the same transaction that charged it.
            $breakdown = app(PayrollTaxBreakdownService::class);
            $recorded = $breakdown->record($payroll);

            if (!$recorded['reconciled']) {
                throw new \RuntimeException(sprintf(
                    'The tax breakdown (%s) does not add up to the tax charged (%s).',
                    number_format($breakdown->totals($recorded['lines'])['employee'], 2),
                    number_format((float) $payroll->tax, 2)
                ));
            }

            return $payroll->fresh();
        });
    }

    /**
     * The lines a payroll starts from when it is generated in bulk: last
     * month's allowances and deductions (if the school carries them over), and
     * every open loan's instalment for the month.
     *
     * Loan lines are never carried over as plain deductions: they are worked
     * out afresh so a loan stops being deducted once it is repaid.
     *
     * @return array{allowances: array, deductions: array, from: ?string}
     */
    public function startingLines(User $user, string $salaryMonth, ?Payroll $existing = null): array
    {
        $allowances = [];
        $deductions = [];
        $from = null;

        if (PayrollSetting::current()->carry_over_lines) {
            $previous = Payroll::with('details')
                ->where('user_id', $user->id)
                ->where('salary_month', '<', $salaryMonth)
                ->orderByDesc('salary_month')
                ->first();

            if ($previous) {
                $from = Carbon::parse($previous->salary_month)->format('F Y');
                foreach ($previous->details as $detail) {
                    if ($detail->staff_loan_id) {
                        continue;
                    }
                    $row = ['title' => $detail->title, 'amount' => $detail->amount];
                    if ((int) $detail->status === 1) {
                        $allowances[] = $row;
                    } else {
                        $deductions[] = $row;
                    }
                }
            }
        }

        foreach (StaffLoan::dueLines($user->id, $salaryMonth, $existing?->id) as $line) {
            $deductions[] = $line;
        }

        return ['allowances' => $allowances, 'deductions' => $deductions, 'from' => $from];
    }

    /** Generate one staff member's payroll from its starting lines. */
    public function generateFromStartingLines(User $user, string $salaryMonth, int $byUserId): array
    {
        $existing = Payroll::where('user_id', $user->id)->where('salary_month', $salaryMonth)->first();
        $lines = $this->startingLines($user, $salaryMonth, $existing);

        $calc = $this->calculator->calculate($user, $salaryMonth, $lines['allowances'], $lines['deductions'], $existing);

        if (!empty($calc['problems'])) {
            return ['payroll' => null, 'calc' => $calc, 'from' => $lines['from']];
        }

        return ['payroll' => $this->save($user, $salaryMonth, $calc, $byUserId), 'calc' => $calc, 'from' => $lines['from']];
    }
}
