<?php

namespace App\Services\EdutrustPay;

use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Services\DaybookService;
use Carbon\Carbon;
use EdutrustPay\Contract\BudgetSheet;
use EdutrustPay\Contract\Money;

/**
 * This institution's budget sheet at the end of one month, as its bursar reads it.
 *
 * Built by DaybookService::monthlySummary(), the same code behind the budget sheet
 * screen here, and on the same budget that screen would open for that month, so
 * the body and the bursar always read the same sheet.
 */
class BudgetSheetExporter
{
    public function __construct(private DaybookService $daybook)
    {
    }

    /**
     * @return array<string, mixed>|null  null when no budget covers the month
     */
    public function document(string $period, int $sequence = 1): ?array
    {
        $settings = app(SettingsResolver::class)->resolve();

        if ($settings === null) {
            throw new \RuntimeException('No EdutrustPay credentials are configured.');
        }

        $month = Carbon::createFromFormat('!Y-m', $period);

        // Matches MonthLinkController::budgetCovering(): where overlapping budgets
        // both cover a month, the newest wins there, so it wins here too.
        $budget = Budget::where('is_institutional', true)
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $month)
            ->orderByDesc('id')
            ->first();

        $accountingPeriod = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $month)
            ->orderBy('start_date')
            ->first();

        if (! $budget || ! $accountingPeriod) {
            return null;
        }

        $summary = $this->daybook->monthlySummary($budget, $accountingPeriod);
        $money = fn ($value) => Money::of(number_format((float) $value, 2, '.', ''));

        $lines = array_values(array_map(fn (array $row) => [
            'code' => $row['line']->code,
            'name' => (string) $row['line']->name,
            'section' => (string) $row['line']->section,
            'heading' => (bool) $row['is_header'],
            'budget' => $money($row['budget']),
            'month' => $money($row['monthly']),
            'to_date' => $money($row['cumulative']),
        ], $summary['lines']));

        return BudgetSheet::build($settings['institution_ref'], $period, $sequence, gmdate('Y-m-d\TH:i:s\Z'), 'XAF', [
            'title' => (string) ($budget->title ?: $budget->budget_code ?: __('Budget')),
            'starts' => Carbon::parse($budget->start_date)->toDateString(),
            'ends' => Carbon::parse($budget->end_date)->toDateString(),
        ], $lines);
    }
}
