<?php

namespace App\Services\Accounting;

use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\DepreciationSchedule;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DepreciationService
{
    /**
     * Calculate depreciation for all active assets for a given period
     *
     * @param Carbon|string $date The date for which to calculate depreciation
     * @param bool $autoPost Whether to automatically post the depreciation entries
     * @return array Results of the depreciation calculation
     */
    public function calculateMonthlyDepreciation($date = null, $autoPost = false)
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();
        $results = [
            'processed' => 0,
            'posted' => 0,
            'skipped' => 0,
            'errors' => [],
            'schedules' => [],
        ];

        /*
         * The fiscal year the depreciation DATE falls in, not whichever is
         * active. A run for December, executed in January, is dated December
         * and belongs to December's exercice; filing it under the active year
         * would misstate the charge and leave the period null besides, since no
         * period of the new year can contain a December date.
         */
        $fiscalYear = FiscalYear::forDate($date);
        if (!$fiscalYear) {
            throw new Exception(__('No fiscal year covers :date.',
                ['date' => \Carbon\Carbon::parse($date)->format('j F Y')]));
        }

        $accountingPeriod = AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        // Get all depreciable assets
        $assets = FixedAsset::depreciable()->get();

        foreach ($assets as $asset) {
            try {
                // Already charged (or waiting to be posted) for this month:
                // running the calculation twice must not charge twice.
                $existing = DepreciationSchedule::where('fixed_asset_id', $asset->id)
                    ->whereDate('depreciation_date', $date->copy()->endOfMonth()->toDateString())->first();
                if ($existing && $existing->status !== DepreciationSchedule::STATUS_REVERSED) {
                    if ($autoPost && $existing->status === DepreciationSchedule::STATUS_PENDING) {
                        $existing->post(auth()->id());
                        $results['posted']++;
                    } else {
                        $results['skipped']++;
                    }
                    continue;
                }

                // Check if depreciation is due for this asset
                if (!$asset->isDepreciationDue($date)) {
                    $results['skipped']++;
                    continue;
                }

                // Calculate depreciation amount
                $amount = $asset->calculateMonthlyDepreciation();
                
                if ($amount <= 0) {
                    $results['skipped']++;
                    continue;
                }

                // Create depreciation schedule record
                $schedule = $this->createDepreciationSchedule($asset, $amount, $date->copy(), $fiscalYear, $accountingPeriod, $existing);
                $results['schedules'][] = $schedule;
                $results['processed']++;

                // Post if auto-post is enabled
                if ($autoPost) {
                    $schedule->post(auth()->id());
                    $results['posted']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = [
                    'asset_id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'error' => $e->getMessage(),
                ];
                Log::error('Depreciation calculation error for asset ' . $asset->asset_code, [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Create a depreciation schedule record
     *
     * @param FixedAsset $asset
     * @param float $amount
     * @param Carbon $date
     * @param FiscalYear $fiscalYear
     * @param AccountingPeriod|null $accountingPeriod
     * @return DepreciationSchedule
     */
    protected function createDepreciationSchedule(
        FixedAsset $asset,
        float $amount,
        Carbon $date,
        FiscalYear $fiscalYear,
        ?AccountingPeriod $accountingPeriod = null,
        ?DepreciationSchedule $reversed = null
    ): DepreciationSchedule {
        $values = [
            'fixed_asset_id' => $asset->id,
            'depreciation_date' => $date->endOfMonth(),
            'depreciation_amount' => $amount,
            'accumulated_depreciation_before' => $asset->accumulated_depreciation,
            'accumulated_depreciation_after' => $asset->accumulated_depreciation + $amount,
            'book_value_before' => $asset->book_value,
            'book_value_after' => $asset->book_value - $amount,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $accountingPeriod?->id,
            'status' => DepreciationSchedule::STATUS_PENDING,
            'created_by' => auth()->id(),
        ];

        // A month whose charge was reversed is charged again on the same row
        // (one row per asset per month is a unique key).
        if ($reversed) {
            $reversed->fill($values + ['journal_entry_id' => null, 'posted_at' => null, 'posted_by' => null])->save();

            return $reversed;
        }

        return DepreciationSchedule::create($values);
    }

    /**
     * Post pending depreciation entries for a period
     *
     * @param int|null $accountingPeriodId
     * @return array
     */
    public function postPendingDepreciation(?int $accountingPeriodId = null)
    {
        $results = [
            'posted' => 0,
            'errors' => [],
        ];

        $query = DepreciationSchedule::pending();
        
        if ($accountingPeriodId) {
            $query->forPeriod($accountingPeriodId);
        }

        $schedules = $query->get();

        DB::beginTransaction();
        try {
            foreach ($schedules as $schedule) {
                try {
                    $schedule->post(auth()->id());
                    $results['posted']++;
                } catch (Exception $e) {
                    $results['errors'][] = [
                        'schedule_id' => $schedule->id,
                        'asset_code' => $schedule->fixedAsset->asset_code ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Generate depreciation forecast for an asset
     *
     * @param FixedAsset $asset
     * @param int $months Number of months to forecast
     * @return array
     */
    public function generateForecast(FixedAsset $asset, int $months = 12)
    {
        $forecast = [];
        $currentBookValue = $asset->book_value;
        $currentAccumulatedDepreciation = $asset->accumulated_depreciation;
        $currentDate = $asset->last_depreciation_date 
            ? Carbon::parse($asset->last_depreciation_date)->addMonth() 
            : Carbon::parse($asset->depreciation_start_date);

        for ($i = 0; $i < $months; $i++) {
            if ($currentBookValue <= $asset->salvage_value) {
                break;
            }

            // Clone asset for calculation
            $tempAsset = clone $asset;
            $tempAsset->book_value = $currentBookValue;
            $tempAsset->accumulated_depreciation = $currentAccumulatedDepreciation;

            $depreciationAmount = $tempAsset->calculateMonthlyDepreciation();
            
            if ($depreciationAmount <= 0) {
                break;
            }

            $forecast[] = [
                'period' => $currentDate->format('Y-m'),
                'depreciation_amount' => $depreciationAmount,
                'accumulated_depreciation' => $currentAccumulatedDepreciation + $depreciationAmount,
                'book_value' => max($currentBookValue - $depreciationAmount, $asset->salvage_value),
            ];

            $currentAccumulatedDepreciation += $depreciationAmount;
            $currentBookValue = max($currentBookValue - $depreciationAmount, $asset->salvage_value);
            $currentDate->addMonth();
        }

        return $forecast;
    }

    /**
     * Get depreciation summary by category
     *
     * @param int $fiscalYearId
     * @return array
     */
    public function getSummaryByCategory(int $fiscalYearId)
    {
        return DepreciationSchedule::where('fiscal_year_id', $fiscalYearId)
            ->where('status', DepreciationSchedule::STATUS_POSTED)
            ->join('fixed_assets', 'depreciation_schedules.fixed_asset_id', '=', 'fixed_assets.id')
            ->join('fixed_asset_categories', 'fixed_assets.category_id', '=', 'fixed_asset_categories.id')
            ->select(
                'fixed_asset_categories.id',
                'fixed_asset_categories.name',
                'fixed_asset_categories.code',
                DB::raw('COUNT(DISTINCT fixed_assets.id) as asset_count'),
                DB::raw('SUM(depreciation_schedules.depreciation_amount) as total_depreciation'),
                DB::raw('SUM(fixed_assets.acquisition_cost) as total_acquisition_cost'),
                DB::raw('SUM(fixed_assets.accumulated_depreciation) as total_accumulated_depreciation'),
                DB::raw('SUM(fixed_assets.book_value) as total_book_value')
            )
            ->groupBy('fixed_asset_categories.id', 'fixed_asset_categories.name', 'fixed_asset_categories.code')
            ->get();
    }

    /**
     * Dispose of an asset
     *
     * @param FixedAsset $asset
     * @param Carbon|string $disposalDate
     * @param float $disposalValue
     * @param string|null $reason
     * @return JournalEntry
     */
    public function disposeAsset(FixedAsset $asset, $disposalDate, float $disposalValue, ?string $reason = null, ?int $paymentAccountId = null)
    {
        $disposalDate = Carbon::parse($disposalDate);
        $category = $asset->category;

        if (!$category || !$category->assetAccount || !$category->accumulatedDepreciationAccount) {
            throw new Exception(__('asset_accounts_not_configured'));
        }

        DB::beginTransaction();
        try {
            // Create disposal journal entry
            // A real entry: numbered, in the year and month of the disposal
            // date, posted once its lines are in (it had columns journal
            // entries do not have, and no number, so it could not be saved).
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber($disposalDate->toDateString()),
                'entry_date' => $disposalDate->toDateString(),
                'reference_number' => 'DISP-' . $asset->asset_code,
                'description' => __('disposal_of_asset') . ' ' . $asset->name . ($reason ? ' - ' . $reason : ''),
                'reference_type' => 'asset_disposal',
                'reference_id' => $asset->id,
                'journal_type' => 'adjustment',
                'fiscal_year_id' => FiscalYear::forDate($disposalDate)?->id,
                'accounting_period_id' => FiscalYear::periodForDate($disposalDate)?->id,
                'total_debit' => 0,
                'total_credit' => 0,
                'is_posted' => false,
                'is_system_generated' => true,
                'created_by' => auth()->id(),
            ]);

            // The lines, numbered. Proceeds go to the cash box or bank they
            // were paid into; the difference against book value is a gain
            // (82) or a loss (81). Every line must find its account, or the
            // entry would not balance: say which one is missing.
            $lines = [];
            $accumulated = round((float) $asset->accumulated_depreciation, 2);
            if ($accumulated > 0) {
                $lines[] = [$category->accumulated_depreciation_account_id, $accumulated, 0, __('Accumulated depreciation removed')];
            }
            if ($disposalValue > 0) {
                $cashAccountId = app(\App\Services\Accounting\CashAccounts::class)->forPaymentAccount($paymentAccountId)
                    ?? \App\Models\AccountingSetting::getValue('default_cash_account_id');
                if (!$cashAccountId) {
                    throw new Exception(__('Choose the cash box or bank the proceeds were paid into.'));
                }
                $lines[] = [(int) $cashAccountId, round($disposalValue, 2), 0, __('Disposal proceeds')];
            }
            $lines[] = [$category->asset_account_id, 0, round((float) $asset->acquisition_cost, 2), __('Asset removed at cost')];

            $gainLoss = round($disposalValue - ((float) $asset->acquisition_cost - $accumulated), 2);
            if ($gainLoss != 0) {
                $code = $gainLoss > 0 ? '82%' : '81%';
                $accountId = \App\Models\ChartOfAccount::where('account_code', 'like', $code)->where('account_category', 'detail')->orderBy('account_code')->value('id');
                if (!$accountId) {
                    throw new Exception(__('No account :code in the chart for the :what on disposal.', [
                        'code' => rtrim($code, '%'), 'what' => $gainLoss > 0 ? __('gain') : __('loss')]));
                }
                $lines[] = $gainLoss > 0
                    ? [$accountId, 0, $gainLoss, __('Gain on disposal')]
                    : [$accountId, abs($gainLoss), 0, __('Loss on disposal')];
            }

            foreach ($lines as $i => [$accountId, $debit, $credit, $text]) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $i + 1,
                    'account_id' => $accountId,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $text . ' — ' . $asset->name,
                ]);
            }

            // Totals from the lines, then post: post() refuses an unbalanced entry.
            $journalEntry->load('lines');
            $journalEntry->total_debit = round((float) $journalEntry->lines->sum('debit'), 2);
            $journalEntry->total_credit = round((float) $journalEntry->lines->sum('credit'), 2);
            $journalEntry->save();
            $journalEntry->post(auth()->id());

            // Update asset record
            $asset->dispose($disposalDate, $disposalValue, $reason, $journalEntry->id);

            DB::commit();
            return $journalEntry;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get asset register report
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAssetRegister(array $filters = [])
    {
        $query = FixedAsset::with(['category', 'department', 'custodian']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['acquisition_date_from'])) {
            $query->whereDate('acquisition_date', '>=', $filters['acquisition_date_from']);
        }

        if (!empty($filters['acquisition_date_to'])) {
            $query->whereDate('acquisition_date', '<=', $filters['acquisition_date_to']);
        }

        return $query->orderBy('asset_code')->get();
    }

    /**
     * Get depreciation schedule report
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDepreciationScheduleReport(array $filters = [])
    {
        $query = DepreciationSchedule::with(['fixedAsset.category', 'journalEntry']);

        if (!empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (!empty($filters['accounting_period_id'])) {
            $query->where('accounting_period_id', $filters['accounting_period_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['asset_id'])) {
            $query->where('fixed_asset_id', $filters['asset_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('fixedAsset', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('depreciation_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('depreciation_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('depreciation_date', 'desc')->get();
    }
}
