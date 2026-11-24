<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffLoan;
use App\Models\StaffHourlyAttendance;
use App\Models\TaxGroup;
use App\User;
use Carbon\Carbon;

/**
 * Every figure on a payroll, worked out on the server.
 *
 * The payroll screen used to compute the figures in JavaScript and post them
 * back in readonly inputs, and the server rechecked only the tax. So a form
 * edited in the browser could raise the gross, overstate the allowances, set
 * the basic salary to 1, or under-declare the earning to cut the tax, and it
 * was saved. This class is now the only place a payroll figure comes from:
 * the screen asks it for a preview as lines are typed, and saving asks it
 * again and stores its answer.
 *
 * What it takes from the person generating the payroll is only what they
 * genuinely decide: which allowances and deductions apply and how much each
 * one is. Everything else comes from the staff record, the attendance, the
 * allowance-type settings and the tax rates in force for the month.
 */
class PayrollCalculator
{
    /** The group that must have rates in force before any payroll can be taxed. */
    public const GROUP_INCOME_TAX = 1;

    /** Below this a figure is treated as nothing (half a centime). */
    private const EPSILON = 0.005;

    public function __construct(private PayrollTaxBreakdownService $breakdown)
    {
    }

    /**
     * Work out a payroll.
     *
     * @param  array<int, array{title:mixed, amount:mixed}>  $allowances
     * @param  array<int, array{title:mixed, amount:mixed}>  $deductions
     * @param  Payroll|null  $existing  the payroll being regenerated, if any:
     *         its own allowance titles stay acceptable even if the type has
     *         since been renamed or switched off.
     * @return array{
     *     salary_type:int, basic_salary:float, hours_worked:?float, sessions_without_times:int,
     *     total_earning:float, allowances:array, deductions:array,
     *     total_allowance:float, taxable_allowance:float, total_deduction:float,
     *     taxable_salary:float, gross_salary:float, tax_lines:array,
     *     tax:float, employer_tax:float, net_salary:float, total_cost:float,
     *     effective_rate:float, problems:array<int,string>, notes:array<int,string>
     * }
     */
    public function calculate(User $user, $salaryMonth, array $allowances = [], array $deductions = [], ?Payroll $existing = null): array
    {
        $month = Carbon::parse($salaryMonth)->startOfMonth();
        $problems = [];
        $notes = [];

        // --- What the staff member earned this month --------------------------
        $salaryType = (int) $user->salary_type;
        $basic = round((float) ($user->basic_salary ?? 0), 2);
        $hours = null;
        $withoutTimes = 0;
        $paidDays = null;
        $periodDays = null;

        if ($salaryType === 2) {
            // Hourly staff are paid for the hours they were present. The
            // screen used to put the hourly rate itself down as the month's
            // pay, so somebody on 2,000 an hour was paid 2,000 for the month.
            [$hours, $withoutTimes] = $this->hoursWorked($user->id, $month);
            $earning = round($basic * $hours, 2);

            if ($withoutTimes > 0) {
                $notes[] = trans_choice(
                    '{1} :count attended session has no start or end time, so it is not counted in the hours.|[2,*] :count attended sessions have no start or end time, so they are not counted in the hours.',
                    $withoutTimes,
                    ['count' => $withoutTimes]
                );
            }
        } else {
            $earning = $basic;

            // A fixed salary for a month the staff member only partly worked,
            // when the school prorates: the share of the month between the
            // joining date and the leaving date on the staff record.
            [$paidDays, $periodDays, $prorationNote] = $this->proration($user, $month);
            if ($paidDays !== null) {
                $earning = $periodDays > 0 ? round($basic * $paidDays / $periodDays, 2) : 0.0;
                $notes[] = $prorationNote;
            }
        }

        if ($basic <= 0) {
            $problems[] = __('This staff member has no salary set on their staff record. Set it before generating a payroll.');
        }

        // --- Allowances and deductions -----------------------------------------
        $knownTitles = AllowanceType::query()->get(['title', 'status', 'is_taxable'])->keyBy('title');
        $ownTitles = $existing
            ? $existing->details()->where('status', 1)->get(['title', 'is_taxable'])->keyBy('title')
            : collect();

        $allowanceLines = [];
        foreach ($this->lines($allowances, __('allowance'), $problems) as [$title, $amount]) {
            $type = $knownTitles->get($title);
            $own = $ownTitles->get($title);

            if ((!$type || (int) $type->status !== 1) && !$own) {
                $problems[] = __('":title" is not one of the allowance types. Choose one from the list.', ['title' => $title]);
                continue;
            }

            // The type's setting today, or for a title that no longer has a
            // type, whatever was recorded when this payroll was generated.
            $taxable = $type ? (bool) $type->is_taxable : (bool) ($own->is_taxable ?? false);

            $allowanceLines[] = ['title' => $title, 'amount' => $amount, 'is_taxable' => $taxable];
        }

        $deductionLines = [];
        foreach ($this->lines($deductions, __('deduction'), $problems) as [$title, $amount, $loanId]) {
            if ($loanId) {
                // A loan repayment: it must be this person's loan, still open,
                // and take no more than is left once other unpaid payrolls'
                // instalments are counted.
                $loan = StaffLoan::find($loanId);

                if (!$loan || (int) $loan->user_id !== (int) $user->id || $loan->status !== 'active') {
                    $problems[] = __('":title" is not an open loan of this staff member.', ['title' => $title]);
                    continue;
                }

                $available = $loan->available($existing?->id);
                if ($amount - $available > self::EPSILON) {
                    $problems[] = __('":title" has :left left to repay, less than the :amount entered.', [
                        'title' => $title,
                        'left' => number_format($available, 2),
                        'amount' => number_format($amount, 2),
                    ]);
                    continue;
                }

                $deductionLines[] = ['title' => $loan->title, 'amount' => $amount, 'loan_id' => $loan->id];
                continue;
            }

            $deductionLines[] = ['title' => $title, 'amount' => $amount, 'loan_id' => null];
        }

        $totalAllowance = round(array_sum(array_column($allowanceLines, 'amount')), 2);
        $taxableAllowance = round(array_sum(array_map(
            fn ($l) => $l['is_taxable'] ? $l['amount'] : 0,
            $allowanceLines
        )), 2);
        $totalDeduction = round(array_sum(array_column($deductionLines, 'amount')), 2);

        // --- Tax -----------------------------------------------------------------
        $taxableSalary = round($earning + $taxableAllowance, 2);
        $taxLines = [];

        $tax = 0.0;
        $employerTax = 0.0;

        try {
            ['lines' => $taxLines, 'employee' => $tax, 'employer' => $employerTax]
                = $this->taxFor($user->id, $taxableSalary, $month);
        } catch (\RuntimeException $e) {
            $problems[] = $e->getMessage();
        }

        // --- The pay -----------------------------------------------------------
        $gross = round($earning + $totalAllowance - $totalDeduction, 2);
        $net = round($gross - $tax, 2);

        if ($net < -self::EPSILON) {
            $problems[] = __('The deductions and tax (:out) come to more than the pay (:in), so the net pay would be :net. Reduce the deductions.', [
                'out' => number_format($totalDeduction + $tax, 2),
                'in' => number_format($earning + $totalAllowance, 2),
                'net' => number_format($net, 2),
            ]);
        }

        return [
            'salary_type' => $salaryType,
            'basic_salary' => $basic,
            'hours_worked' => $hours,
            'sessions_without_times' => $withoutTimes,
            'paid_days' => $paidDays,
            'period_days' => $periodDays,
            'total_earning' => $earning,
            'allowances' => $allowanceLines,
            'deductions' => $deductionLines,
            'total_allowance' => $totalAllowance,
            'taxable_allowance' => $taxableAllowance,
            'total_deduction' => $totalDeduction,
            'taxable_salary' => $taxableSalary,
            'gross_salary' => $gross,
            'tax_lines' => $taxLines,
            'tax' => $tax,
            'employer_tax' => $employerTax,
            'net_salary' => $net,
            'total_cost' => round($net + $tax + $employerTax, 2),
            'effective_rate' => $taxableSalary > 0 ? round($tax / $taxableSalary * 100, 2) : 0.0,
            'problems' => array_values(array_unique($problems)),
            'notes' => $notes,
        ];
    }

    /**
     * The tax due on a taxable pay for a month, itemised.
     *
     * Refuses (RuntimeException) rather than answering zero when the rates do
     * not cover the month or the pay; see assertRatesCover().
     *
     * @return array{lines: array, employee: float, employer: float}
     */
    public function taxFor(int $userId, float $taxableSalary, $salaryMonth): array
    {
        $month = Carbon::parse($salaryMonth)->startOfMonth();

        $this->assertRatesCover($taxableSalary, $month);

        $lines = array_map(function ($line) {
            $line['employee'] = round((float) $line['employee'], 2);
            $line['employer'] = round((float) $line['employer'], 2);

            return $line;
        }, $this->breakdown->forSalary($taxableSalary, $month, $this->breakdown->exemptionsFor($userId, $month)));

        // Summed from the lines as rounded, so the lines printed on the
        // payslip add up to the tax exactly.
        return [
            'lines' => $lines,
            'employee' => round(array_sum(array_column($lines, 'employee')), 2),
            'employer' => round(array_sum(array_column($lines, 'employer')), 2),
        ];
    }

    /**
     * Hours an hourly staff member was present in a month, and how many
     * attended sessions could not be counted because they carry no times.
     *
     * @return array{0: float, 1: int}
     */
    public function hoursWorked(int $userId, Carbon $month): array
    {
        $sessions = StaffHourlyAttendance::where('user_id', $userId)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->where('attendance', 1)
            ->get(['start_time', 'end_time']);

        $minutes = 0;
        $withoutTimes = 0;

        foreach ($sessions as $session) {
            if (!$session->start_time || !$session->end_time) {
                $withoutTimes++;
                continue;
            }

            $start = Carbon::parse($session->start_time);
            $end = Carbon::parse($session->end_time);

            if ($end->lessThanOrEqualTo($start)) {
                $withoutTimes++;
                continue;
            }

            $minutes += $start->diffInMinutes($end);
        }

        return [round($minutes / 60, 2), $withoutTimes];
    }

    /**
     * The part of a month a fixed-salary staff member is paid for, when the
     * school prorates part months: from the joining date (if it falls in the
     * month) to the leaving date (if it falls in the month).
     *
     * @return array{0:?float, 1:?float, 2:?string} paid days, days in the
     *         period, and a note for the page; nulls when the whole month is
     *         paid or proration is off.
     */
    public function proration(User $user, Carbon $month): array
    {
        $settings = PayrollSetting::current();

        if (!$settings->prorate_partial_months) {
            return [null, null, null];
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->startOfDay();

        $from = $start->copy();
        $to = $end->copy();

        if ($user->joining_date) {
            $joined = Carbon::parse($user->joining_date)->startOfDay();
            if ($joined->gt($start) && $joined->lte($end)) {
                $from = $joined;
            } elseif ($joined->gt($end)) {
                $from = $end->copy()->addDay();
            }
        }

        if ($user->ending_date) {
            $left = Carbon::parse($user->ending_date)->startOfDay();
            if ($left->gte($start) && $left->lt($end)) {
                $to = $left;
            } elseif ($left->lt($start)) {
                $to = $start->copy()->subDay();
            }
        }

        if ($from->eq($start) && $to->eq($end)) {
            return [null, null, null];
        }

        $working = $settings->proration_basis === 'working_days';
        $count = function (Carbon $a, Carbon $b) use ($working): int {
            if ($b->lt($a)) {
                return 0;
            }
            if (!$working) {
                return $a->diffInDays($b) + 1;
            }
            $n = 0;
            for ($d = $a->copy(); $d->lte($b); $d->addDay()) {
                if (!$d->isWeekend()) {
                    $n++;
                }
            }
            return $n;
        };

        $period = $count($start, $end);
        $paid = $count($from, $to);
        $unit = $working ? __('working days') : __('days');

        $note = __('Paid for :paid of :period :unit (:from to :to), from the joining and leaving dates on the staff record.', [
            'paid' => $paid,
            'period' => $period,
            'unit' => $unit,
            'from' => $from->format('j M'),
            'to' => $to->lt($from) ? $from->format('j M') : $to->format('j M'),
        ]);

        return [(float) $paid, (float) $period, $note];
    }

    /**
     * The rows of an allowance or deduction list, cleaned: blank rows are
     * dropped (as the form always did), anything else must be a title and a
     * non-negative amount.
     *
     * @return array<int, array{0:string, 1:float}>
     */
    private function lines(array $rows, string $kind, array &$problems): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $raw = $row['amount'] ?? null;

            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }

            $raw = str_replace([',', ' '], '', (string) $raw);

            if (!is_numeric($raw)) {
                $problems[] = __('":amount" is not an amount (:kind ":title").', ['amount' => $row['amount'], 'kind' => $kind, 'title' => $title]);
                continue;
            }

            $amount = round((float) $raw, 2);

            if ($amount < 0) {
                $problems[] = __('An amount cannot be negative (:kind ":title").', ['kind' => $kind, 'title' => $title]);
                continue;
            }

            if ($title === '') {
                $problems[] = __('Every :kind needs a title.', ['kind' => $kind]);
                continue;
            }

            if ($amount <= self::EPSILON) {
                continue;
            }

            $loanId = isset($row['loan_id']) && is_numeric($row['loan_id']) && (int) $row['loan_id'] > 0 ? (int) $row['loan_id'] : null;

            $clean[] = [$title, $amount, $loanId];
        }

        return $clean;
    }

    /**
     * Refuse to tax a month the rates do not cover.
     *
     * No income tax in force must not read as "no tax is due": rates carry an
     * effective_from, so a payroll back-dated before the rates were configured
     * would otherwise come out short, look deliberate, and be paid. And a pay
     * that falls in a hole between bands is charged the band below it, which
     * looks normal on the payslip, so it is refused instead.
     */
    private function assertRatesCover(float $taxableSalary, Carbon $month): void
    {
        $incomeTax = TaxGroup::getEffectiveGroups($month)
            ->first(fn ($group) => (int) $group->id === self::GROUP_INCOME_TAX);

        // Read as the loaded relation: getEffectiveGroups() eager-loads only
        // the bands in force on this date.
        $bandsInForce = $incomeTax ? $incomeTax->brackets->where('status', 1)->count() : 0;

        if (!$incomeTax || $bandsInForce === 0) {
            throw new \RuntimeException(sprintf(
                'No income tax rates are in force for %s, so the tax on this payroll cannot be worked out. Configure rates effective from that month before generating it.',
                $month->format('F Y')
            ));
        }

        if ($taxableSalary <= 0) {
            return;
        }

        $gap = $incomeTax->gapFor($taxableSalary);

        if ($gap !== null) {
            $range = $gap['to'] === null
                ? 'anything above ' . number_format($gap['from'])
                : number_format($gap['from']) . ' to ' . number_format($gap['to']);

            throw new \RuntimeException(sprintf(
                'Pay of %s is not covered by the income tax table (%s is uncovered), so it would be charged under "%s", the band below it. Correct the bands before generating this payroll.',
                number_format($taxableSalary),
                $range,
                $gap['charged_as'] ?? 'the band below'
            ));
        }
    }
}
