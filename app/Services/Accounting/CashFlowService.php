<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class CashFlowService
{
    /**
     * Cash flow categories
     */
    const CATEGORY_OPERATING = 'operating';
    const CATEGORY_INVESTING = 'investing';
    const CATEGORY_FINANCING = 'financing';

    /**
     * Generate cash flow statement
     *
     * @param int $fiscalYearId
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @return array
     */
    public function generateCashFlowStatement($fiscalYearId, $startDate = null, $endDate = null)
    {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);
        
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::parse($fiscalYear->start_date);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::parse($fiscalYear->end_date);

        // Get beginning and ending cash balances
        $beginningCashBalance = $this->getCashBalance($fiscalYearId, $startDate->copy()->subDay());
        $endingCashBalance = $this->getCashBalance($fiscalYearId, $endDate);

        $statement = [
            'fiscal_year' => $fiscalYear->name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'beginning_cash_balance' => $beginningCashBalance,
            'ending_cash_balance' => $endingCashBalance,
            'operating_activities' => $this->getOperatingActivities($fiscalYearId, $startDate, $endDate),
            'investing_activities' => $this->getInvestingActivities($fiscalYearId, $startDate, $endDate),
            'financing_activities' => $this->getFinancingActivities($fiscalYearId, $startDate, $endDate),
        ];

        // Calculate totals
        $statement['net_operating'] = array_sum(array_column($statement['operating_activities']['items'], 'amount'));
        $statement['net_investing'] = array_sum(array_column($statement['investing_activities']['items'], 'amount'));
        $statement['net_financing'] = array_sum(array_column($statement['financing_activities']['items'], 'amount'));
        $statement['net_change_in_cash'] = $statement['net_operating'] + $statement['net_investing'] + $statement['net_financing'];
        
        // Verification
        $statement['calculated_ending_balance'] = $beginningCashBalance + $statement['net_change_in_cash'];
        $statement['difference'] = $endingCashBalance - $statement['calculated_ending_balance'];
        $statement['is_balanced'] = abs($statement['difference']) < 0.01;

        return $statement;
    }

    /**
     * Get operating activities (Indirect Method)
     *
     * @param int $fiscalYearId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getOperatingActivities($fiscalYearId, Carbon $startDate, Carbon $endDate)
    {
        $items = [];

        // Start with Net Income
        $netIncome = $this->getNetIncome($fiscalYearId, $startDate, $endDate);
        $items[] = [
            'description' => __('net_income'),
            'amount' => $netIncome,
            'type' => 'start',
        ];

        // Adjustments for non-cash items

        // Add back Depreciation & Amortization (Class 68 in OHADA)
        $depreciation = $this->getAccountMovement('68%', $fiscalYearId, $startDate, $endDate);
        if ($depreciation != 0) {
            $items[] = [
                'description' => __('depreciation_amortization'),
                'amount' => $depreciation,
                'type' => 'adjustment',
            ];
        }

        // Changes in operating assets and liabilities

        // Decrease (Increase) in Accounts Receivable (Class 41)
        $arChange = $this->getBalanceChange('41%', $fiscalYearId, $startDate, $endDate);
        if ($arChange != 0) {
            $items[] = [
                'description' => __('change_in_accounts_receivable'),
                'amount' => -$arChange, // Negative because increase uses cash
                'type' => 'change',
            ];
        }

        // Decrease (Increase) in Inventory (Class 3)
        $inventoryChange = $this->getBalanceChange('3%', $fiscalYearId, $startDate, $endDate);
        if ($inventoryChange != 0) {
            $items[] = [
                'description' => __('change_in_inventory'),
                'amount' => -$inventoryChange,
                'type' => 'change',
            ];
        }

        // Decrease (Increase) in Prepaid Expenses (Class 47)
        $prepaidChange = $this->getBalanceChange('47%', $fiscalYearId, $startDate, $endDate);
        if ($prepaidChange != 0) {
            $items[] = [
                'description' => __('change_in_prepaid_expenses'),
                'amount' => -$prepaidChange,
                'type' => 'change',
            ];
        }

        // Increase (Decrease) in Accounts Payable (Class 40)
        $apChange = $this->getBalanceChange('40%', $fiscalYearId, $startDate, $endDate);
        if ($apChange != 0) {
            $items[] = [
                'description' => __('change_in_accounts_payable'),
                'amount' => $apChange,
                'type' => 'change',
            ];
        }

        // Increase (Decrease) in Accrued Expenses (Class 42, 43, 44)
        $accruedChange = $this->getBalanceChange('42%', $fiscalYearId, $startDate, $endDate)
            + $this->getBalanceChange('43%', $fiscalYearId, $startDate, $endDate)
            + $this->getBalanceChange('44%', $fiscalYearId, $startDate, $endDate);
        if ($accruedChange != 0) {
            $items[] = [
                'description' => __('change_in_accrued_liabilities'),
                'amount' => $accruedChange,
                'type' => 'change',
            ];
        }

        return [
            'title' => __('cash_flows_from_operating_activities'),
            'items' => $items,
            'total' => array_sum(array_column($items, 'amount')),
        ];
    }

    /**
     * Get investing activities
     *
     * @param int $fiscalYearId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getInvestingActivities($fiscalYearId, Carbon $startDate, Carbon $endDate)
    {
        $items = [];

        // Purchase of Fixed Assets (Class 2 debits)
        $assetPurchases = $this->getAccountMovement('2%', $fiscalYearId, $startDate, $endDate, 'debit');
        if ($assetPurchases != 0) {
            $items[] = [
                'description' => __('purchase_of_fixed_assets'),
                'amount' => -$assetPurchases, // Outflow
                'type' => 'outflow',
            ];
        }

        // Sale of Fixed Assets (proceeds from disposal)
        $assetSales = $this->getDisposalProceeds($fiscalYearId, $startDate, $endDate);
        if ($assetSales != 0) {
            $items[] = [
                'description' => __('proceeds_from_sale_of_assets'),
                'amount' => $assetSales, // Inflow
                'type' => 'inflow',
            ];
        }

        // Investment in Securities (Class 26, 27)
        $investmentPurchases = $this->getAccountMovement('26%', $fiscalYearId, $startDate, $endDate, 'debit')
            + $this->getAccountMovement('27%', $fiscalYearId, $startDate, $endDate, 'debit');
        if ($investmentPurchases != 0) {
            $items[] = [
                'description' => __('purchase_of_investments'),
                'amount' => -$investmentPurchases,
                'type' => 'outflow',
            ];
        }

        // Sale of Investments
        $investmentSales = $this->getAccountMovement('26%', $fiscalYearId, $startDate, $endDate, 'credit')
            + $this->getAccountMovement('27%', $fiscalYearId, $startDate, $endDate, 'credit');
        if ($investmentSales != 0) {
            $items[] = [
                'description' => __('proceeds_from_sale_of_investments'),
                'amount' => $investmentSales,
                'type' => 'inflow',
            ];
        }

        return [
            'title' => __('cash_flows_from_investing_activities'),
            'items' => $items,
            'total' => array_sum(array_column($items, 'amount')),
        ];
    }

    /**
     * Get financing activities
     *
     * @param int $fiscalYearId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getFinancingActivities($fiscalYearId, Carbon $startDate, Carbon $endDate)
    {
        $items = [];

        // Proceeds from Loans (Class 16 - Long-term debt)
        $loanProceeds = $this->getAccountMovement('16%', $fiscalYearId, $startDate, $endDate, 'credit');
        if ($loanProceeds != 0) {
            $items[] = [
                'description' => __('proceeds_from_borrowings'),
                'amount' => $loanProceeds,
                'type' => 'inflow',
            ];
        }

        // Repayment of Loans
        $loanRepayments = $this->getAccountMovement('16%', $fiscalYearId, $startDate, $endDate, 'debit');
        if ($loanRepayments != 0) {
            $items[] = [
                'description' => __('repayment_of_borrowings'),
                'amount' => -$loanRepayments,
                'type' => 'outflow',
            ];
        }

        // Short-term borrowings (Class 56)
        $shortTermBorrowings = $this->getBalanceChange('56%', $fiscalYearId, $startDate, $endDate);
        if ($shortTermBorrowings != 0) {
            $items[] = [
                'description' => __('net_short_term_borrowings'),
                'amount' => $shortTermBorrowings,
                'type' => 'change',
            ];
        }

        // Capital contributions (Class 10)
        $capitalContributions = $this->getAccountMovement('10%', $fiscalYearId, $startDate, $endDate, 'credit');
        if ($capitalContributions != 0) {
            $items[] = [
                'description' => __('capital_contributions'),
                'amount' => $capitalContributions,
                'type' => 'inflow',
            ];
        }

        // Dividends paid (Class 12 or 46)
        $dividendsPaid = $this->getAccountMovement('129%', $fiscalYearId, $startDate, $endDate, 'debit')
            + $this->getAccountMovement('46%', $fiscalYearId, $startDate, $endDate, 'debit');
        if ($dividendsPaid != 0) {
            $items[] = [
                'description' => __('dividends_paid'),
                'amount' => -$dividendsPaid,
                'type' => 'outflow',
            ];
        }

        return [
            'title' => __('cash_flows_from_financing_activities'),
            'items' => $items,
            'total' => array_sum(array_column($items, 'amount')),
        ];
    }

    /**
     * Get cash balance as of a date
     *
     * @param int $fiscalYearId
     * @param Carbon $asOfDate
     * @return float
     */
    protected function getCashBalance($fiscalYearId, Carbon $asOfDate)
    {
        // Cash accounts in OHADA: Class 5 (excluding 59 - Provisions)
        $cashAccounts = ChartOfAccount::where('account_code', 'like', '5%')
            ->where('account_code', 'not like', '59%')
            ->pluck('id');

        $debits = JournalEntryLine::whereIn('account_id', $cashAccounts)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->sum('debit');

        $credits = JournalEntryLine::whereIn('account_id', $cashAccounts)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->sum('credit');

        return $debits - $credits; // Cash is a debit-normal account
    }

    /**
     * Get net income for period
     *
     * @param int $fiscalYearId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    protected function getNetIncome($fiscalYearId, Carbon $startDate, Carbon $endDate)
    {
        // Revenue accounts (Class 7)
        $revenueAccounts = ChartOfAccount::where('account_code', 'like', '7%')->pluck('id');
        
        $revenueCredits = JournalEntryLine::whereIn('account_id', $revenueAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId, $startDate, $endDate) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('is_posted', true)
                  ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->sum('credit');

        $revenueDebits = JournalEntryLine::whereIn('account_id', $revenueAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId, $startDate, $endDate) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('is_posted', true)
                  ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->sum('debit');

        $totalRevenue = $revenueCredits - $revenueDebits;

        // Expense accounts (Class 6)
        $expenseAccounts = ChartOfAccount::where('account_code', 'like', '6%')->pluck('id');
        
        $expenseDebits = JournalEntryLine::whereIn('account_id', $expenseAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId, $startDate, $endDate) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('is_posted', true)
                  ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->sum('debit');

        $expenseCredits = JournalEntryLine::whereIn('account_id', $expenseAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId, $startDate, $endDate) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('is_posted', true)
                  ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->sum('credit');

        $totalExpenses = $expenseDebits - $expenseCredits;

        return $totalRevenue - $totalExpenses;
    }

    /**
     * Get account movement for period
     *
     * @param string $codePattern
     * @param int $fiscalYearId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string|null $side 'debit', 'credit', or null for net
     * @return float
     */
    protected function getAccountMovement($codePattern, $fiscalYearId, Carbon $startDate, Carbon $endDate, $side = null)
    {
        $accounts = ChartOfAccount::where('account_code', 'like', $codePattern)->pluck('id');

        $query = JournalEntryLine::whereIn('account_id', $accounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId, $startDate, $endDate) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('is_posted', true)
                  ->whereBetween('entry_date', [$startDate, $endDate]);
            });

        if ($side === 'debit') {
            return $query->sum('debit');
        } elseif ($side === 'credit') {
            return $query->sum('credit');
        } else {
            return $query->sum('debit') - $query->sum('credit');
        }
    }

    /**
     * Get balance change for account pattern
     *
     * @param string $codePattern
     * @param int $fiscalYearId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    protected function getBalanceChange($codePattern, $fiscalYearId, Carbon $startDate, Carbon $endDate)
    {
        $accounts = ChartOfAccount::where('account_code', 'like', $codePattern)->pluck('id');

        // Get beginning balance
        $beginningDebits = JournalEntryLine::whereIn('account_id', $accounts)
            ->whereHas('journalEntry', function ($q) use ($startDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<', $startDate);
            })
            ->sum('debit');

        $beginningCredits = JournalEntryLine::whereIn('account_id', $accounts)
            ->whereHas('journalEntry', function ($q) use ($startDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<', $startDate);
            })
            ->sum('credit');

        $beginningBalance = $beginningDebits - $beginningCredits;

        // Get ending balance
        $endingDebits = JournalEntryLine::whereIn('account_id', $accounts)
            ->whereHas('journalEntry', function ($q) use ($endDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<=', $endDate);
            })
            ->sum('debit');

        $endingCredits = JournalEntryLine::whereIn('account_id', $accounts)
            ->whereHas('journalEntry', function ($q) use ($endDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<=', $endDate);
            })
            ->sum('credit');

        $endingBalance = $endingDebits - $endingCredits;

        return $endingBalance - $beginningBalance;
    }

    /**
     * Get disposal proceeds from fixed assets
     *
     * @param int $fiscalYearId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    protected function getDisposalProceeds($fiscalYearId, Carbon $startDate, Carbon $endDate)
    {
        // Get from FixedAsset model if available
        if (class_exists('\App\Models\FixedAsset')) {
            return \App\Models\FixedAsset::whereBetween('disposal_date', [$startDate, $endDate])
                ->where('status', 'disposed')
                ->sum('disposal_value');
        }

        return 0;
    }

    /**
     * Generate comparative cash flow statement
     *
     * @param int $fiscalYearId
     * @param int|null $previousFiscalYearId
     * @return array
     */
    public function generateComparativeCashFlowStatement($fiscalYearId, $previousFiscalYearId = null)
    {
        $currentStatement = $this->generateCashFlowStatement($fiscalYearId);

        if (!$previousFiscalYearId) {
            $previousFiscalYear = FiscalYear::where('id', '<', $fiscalYearId)
                ->orderBy('id', 'desc')
                ->first();
            $previousFiscalYearId = $previousFiscalYear?->id;
        }

        if ($previousFiscalYearId) {
            $previousStatement = $this->generateCashFlowStatement($previousFiscalYearId);
        } else {
            $previousStatement = null;
        }

        return [
            'current' => $currentStatement,
            'previous' => $previousStatement,
            'variance' => $previousStatement ? $this->calculateVariance($currentStatement, $previousStatement) : null,
        ];
    }

    /**
     * Calculate variance between two statements
     *
     * @param array $current
     * @param array $previous
     * @return array
     */
    protected function calculateVariance(array $current, array $previous)
    {
        return [
            'net_operating' => [
                'amount' => $current['net_operating'] - $previous['net_operating'],
                'percentage' => $previous['net_operating'] != 0 
                    ? (($current['net_operating'] - $previous['net_operating']) / abs($previous['net_operating'])) * 100 
                    : 0,
            ],
            'net_investing' => [
                'amount' => $current['net_investing'] - $previous['net_investing'],
                'percentage' => $previous['net_investing'] != 0 
                    ? (($current['net_investing'] - $previous['net_investing']) / abs($previous['net_investing'])) * 100 
                    : 0,
            ],
            'net_financing' => [
                'amount' => $current['net_financing'] - $previous['net_financing'],
                'percentage' => $previous['net_financing'] != 0 
                    ? (($current['net_financing'] - $previous['net_financing']) / abs($previous['net_financing'])) * 100 
                    : 0,
            ],
            'net_change_in_cash' => [
                'amount' => $current['net_change_in_cash'] - $previous['net_change_in_cash'],
                'percentage' => $previous['net_change_in_cash'] != 0 
                    ? (($current['net_change_in_cash'] - $previous['net_change_in_cash']) / abs($previous['net_change_in_cash'])) * 100 
                    : 0,
            ],
        ];
    }
}
