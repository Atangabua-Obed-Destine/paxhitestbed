<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FiscalYear;
use App\Models\Fee;
use App\Models\FeesCategory;
use App\Models\PaymentReceipt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Payroll;
use Carbon\Carbon;
use PDF;

class GeneralLedgerController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:general-ledger-view', ['only' => ['index', 'show', 'accountLedger']]);
        $this->middleware('permission:general-ledger-export', ['only' => ['export', 'exportPdf']]);
        $this->middleware('permission:trial-balance-view', ['only' => ['trialBalance']]);
        $this->middleware('permission:trial-balance-export', ['only' => ['trialBalanceExport']]);
        $this->middleware('permission:balance-sheet-view', ['only' => ['balanceSheet']]);
        $this->middleware('permission:balance-sheet-export', ['only' => ['balanceSheetExport']]);
        $this->middleware('permission:income-statement-view', ['only' => ['incomeStatement']]);
        $this->middleware('permission:income-statement-export', ['only' => ['incomeStatementExport']]);
        $this->middleware('permission:cash-flow-view', ['only' => ['cashFlow']]);
        $this->middleware('permission:cash-flow-export', ['only' => ['cashFlowExport']]);
    }

    /**
     * Display general ledger index
     */
    public function index()
    {
        $data['title'] = __('general_ledger');
        
        // Get all detail accounts for the dropdown
        $data['accounts'] = ChartOfAccount::where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
        
        // Get fiscal years
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();
        $data['activeFiscalYear'] = FiscalYear::getActiveFiscalYear();
        
        return view('admin.general-ledger.index', $data);
    }

    /**
     * Display ledger for a specific account
     */
    public function account(Request $request, $accountId)
    {
        $data['title'] = __('account_ledger');
        $data['account'] = ChartOfAccount::with('parent')->findOrFail($accountId);
        
        // Get date range
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        
        // Get opening balance (all posted entries before start date)
        $openingBalance = $startDate ? $this->calculateOpeningBalance($accountId, $startDate) : $data['account']->opening_balance;
        $data['openingBalance'] = $openingBalance;
        
        // Get transactions for the period
        $query = JournalEntryLine::with(['journalEntry.fiscalYear', 'journalEntry.creator'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                $q->where('is_posted', true);
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            })
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.entry_number')
            ->select('journal_entry_lines.*');
        
        $data['transactions'] = $query->get();
        
        // Calculate closing balance
        $runningBalance = $openingBalance;
        if ($data['account']->normal_balance === 'debit') {
            $runningBalance += ($data['transactions']->sum('debit') - $data['transactions']->sum('credit'));
        } else {
            $runningBalance += ($data['transactions']->sum('credit') - $data['transactions']->sum('debit'));
        }
        $data['closingBalance'] = $runningBalance;
        
        return view('admin.general-ledger.account', $data);
    }

    /**
     * Display ledger for all accounts by class
     */
    public function byClass(Request $request, $classNumber)
    {
        $data['title'] = __('accounts_by_class');
        $data['classNumber'] = $classNumber;
        
        // Get class names
        $classNames = [
            1 => ['en' => 'Resource Accounts (Capitaux)', 'fr' => 'Comptes de Capitaux'],
            2 => ['en' => 'Fixed Assets (Immobilisations)', 'fr' => "Comptes d'Immobilisations"],
            3 => ['en' => 'Inventory and In-progress (Stocks)', 'fr' => 'Comptes de Stocks'],
            4 => ['en' => 'Third Party Accounts (Tiers)', 'fr' => 'Comptes de Tiers'],
            5 => ['en' => 'Financial Accounts (Trésorerie)', 'fr' => 'Comptes de Trésorerie'],
            6 => ['en' => 'Expense Accounts (Charges)', 'fr' => 'Comptes de Charges'],
            7 => ['en' => 'Revenue Accounts (Produits)', 'fr' => 'Comptes de Produits'],
            8 => ['en' => 'Off-Balance Sheet Accounts', 'fr' => 'Comptes Spéciaux'],
        ];
        
        $data['className'] = $classNames[$classNumber]['en'] ?? 'Class ' . $classNumber;
        $data['className_fr'] = $classNames[$classNumber]['fr'] ?? 'Classe ' . $classNumber;
        
        // Get date range
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        
        // Get all accounts in this class (including header, summary, and detail)
        $data['accounts'] = ChartOfAccount::where('account_code', 'LIKE', $classNumber . '%')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
        
        // Add balance for each account
        $data['accounts']->transform(function($account) use ($startDate, $endDate) {
            // Only calculate for detail accounts
            if ($account->account_category == 'detail') {
                $account->opening_balance = $startDate ? $this->calculateOpeningBalance($account->id, $startDate) : $account->opening_balance;
                $account->total_debit = $this->calculatePeriodDebit($account->id, $startDate, $endDate);
                $account->total_credit = $this->calculatePeriodCredit($account->id, $startDate, $endDate);
            } else {
                $account->opening_balance = 0;
                $account->total_debit = 0;
                $account->total_credit = 0;
            }
            
            return $account;
        });
        
        return view('admin.general-ledger.by-class', $data);
    }

    /**
     * Display trial balance
     */
    public function trialBalance(Request $request)
    {
        $data['title'] = __('trial_balance');
        
        // Get fiscal year and date range
        $fiscalYearId = $request->input('fiscal_year_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        
        // Get all detail accounts with balances
        $data['accounts'] = ChartOfAccount::where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
        
        // Calculate balances for each account
        $data['accounts']->transform(function($account) use ($startDate, $endDate) {
            // Opening balance (before period)
            $account->opening_balance = $startDate ? $this->calculateOpeningBalance($account->id, $startDate) : $account->opening_balance;
            
            // Period movements
            $account->period_debit = $this->calculatePeriodDebit($account->id, $startDate, $endDate);
            $account->period_credit = $this->calculatePeriodCredit($account->id, $startDate, $endDate);
            
            return $account;
        });
        
        // Remove accounts with zero balance and no movement
        $data['accounts'] = $data['accounts']->filter(function($account) {
            return $account->opening_balance != 0 || $account->period_debit != 0 || $account->period_credit != 0;
        });
        
        // Get fiscal years for filter
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();
        
        return view('admin.general-ledger.trial-balance', $data);
    }

    /**
     * Calculate opening balance for an account
     */
    private function calculateOpeningBalance($accountId, $beforeDate)
    {
        $account = ChartOfAccount::find($accountId);
        if (!$account) {
            return 0;
        }
        
        $lines = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function($q) use ($beforeDate) {
                $q->where('is_posted', true)
                  ->where('entry_date', '<', $beforeDate);
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
        
        $totalDebit = $lines->total_debit ?? 0;
        $totalCredit = $lines->total_credit ?? 0;
        
        if ($account->normal_balance === 'debit') {
            return $account->opening_balance + $totalDebit - $totalCredit;
        } else {
            return $account->opening_balance + $totalCredit - $totalDebit;
        }
    }

    /**
     * Calculate period debit for an account
     */
    private function calculatePeriodDebit($accountId, $startDate = null, $endDate = null)
    {
        $query = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                $q->where('is_posted', true);
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            });
        
        return $query->sum('debit');
    }

    /**
     * Calculate period credit for an account
     */
    private function calculatePeriodCredit($accountId, $startDate = null, $endDate = null)
    {
        $query = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                $q->where('is_posted', true);
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            });
        
        return $query->sum('credit');
    }

    /**
     * Export account ledger to PDF
     */
    public function exportAccountPDF(Request $request, $accountId)
    {
        // This would use PDF generation - implementation depends on your PDF library
        // For now, returning a message
        return response()->json([
            'message' => 'PDF export will be implemented with your PDF library'
        ]);
    }

    /**
     * Export trial balance to Excel
     */
    public function exportTrialBalanceExcel(Request $request)
    {
        // This would use Excel export - implementation depends on your Excel library
        return response()->json([
            'message' => 'Excel export will be implemented with Maatwebsite\Excel'
        ]);
    }

    /**
     * Display Balance Sheet (OHADA format)
     */
    public function balanceSheet(Request $request)
    {
        $data['title'] = __('balance_sheet');
        
        // Get date range
        $asOfDate = $request->input('as_of_date', date('Y-m-d'));
        $comparativeDate = $request->input('comparative_date');
        
        $data['asOfDate'] = $asOfDate;
        $data['comparativeDate'] = $comparativeDate;
        
        // ASSETS (ACTIF)
        // Class 2: Fixed Assets (Immobilisations)
        $data['fixedAssets'] = $this->getClassBalances(2, $asOfDate);
        $data['fixedAssets_comparative'] = $comparativeDate ? $this->getClassBalances(2, $comparativeDate) : [];
        
        // Class 3: Inventory & Stocks
        $data['inventory'] = $this->getClassBalances(3, $asOfDate);
        $data['inventory_comparative'] = $comparativeDate ? $this->getClassBalances(3, $comparativeDate) : [];
        
        // Class 4: Receivables (Créances)
        $data['receivables'] = $this->getClassBalances(4, $asOfDate, true); // Only debit accounts
        $data['receivables_comparative'] = $comparativeDate ? $this->getClassBalances(4, $comparativeDate, true) : [];
        
        // Class 5: Cash & Banks
        $data['cashAndBanks'] = $this->getClassBalances(5, $asOfDate);
        $data['cashAndBanks_comparative'] = $comparativeDate ? $this->getClassBalances(5, $comparativeDate) : [];
        
        // LIABILITIES & EQUITY (PASSIF)
        // Class 1: Capital & Equity (Capitaux Propres)
        $data['equity'] = $this->getClassBalances(1, $asOfDate);
        $data['equity_comparative'] = $comparativeDate ? $this->getClassBalances(1, $comparativeDate) : [];
        
        // Class 4: Payables (Dettes)
        $data['payables'] = $this->getClassBalances(4, $asOfDate, false, true); // Only credit accounts
        $data['payables_comparative'] = $comparativeDate ? $this->getClassBalances(4, $comparativeDate, false, true) : [];
        
        // Calculate totals
        $data['totalAssets'] = $this->calculateTotal([
            $data['fixedAssets'],
            $data['inventory'],
            $data['receivables'],
            $data['cashAndBanks']
        ]);
        
        $data['totalLiabilities'] = $this->calculateTotal([
            $data['equity'],
            $data['payables']
        ]);
        
        if ($comparativeDate) {
            $data['totalAssets_comparative'] = $this->calculateTotal([
                $data['fixedAssets_comparative'],
                $data['inventory_comparative'],
                $data['receivables_comparative'],
                $data['cashAndBanks_comparative']
            ]);
            
            $data['totalLiabilities_comparative'] = $this->calculateTotal([
                $data['equity_comparative'],
                $data['payables_comparative']
            ]);
        }
        
        // Get fiscal years for filter
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();
        
        return view('admin.general-ledger.balance-sheet', $data);
    }

    /**
     * Display Income Statement (Compte de Résultat) - Operational Data Only
     * Shows actual school revenue and expenses from operational modules
     */
    public function incomeStatement(Request $request)
    {
        $data['title'] = __('income_statement');
        
        // Get date range
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date', date('Y-m-d'));
        
        // Check if user wants to use journal entries (accounting view) or operational data
        $useJournalEntries = $request->input('use_journal_entries', false);
        
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        $data['useJournalEntries'] = $useJournalEntries;
        
        // If using journal entries, use accounting-based report
        if ($useJournalEntries) {
            return $this->incomeStatementFromJournalEntries($request, $data);
        }
        
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        
        // =================================================================
        // REVENUE SECTION - All Income Sources
        // =================================================================
        
        // 1. STUDENT FEES REVENUE
        // The system uses TWO sources for fee payments:
        // a) fees table with paid_amount (OLD/MAIN system - most data here)
        // b) payment_receipts table (NEW system - may have some data)
        // We need to aggregate from BOTH to get accurate totals
        
        // A. From fees table (main source - paid_amount field)
        $feesTableQuery = \App\Models\Fee::where('paid_amount', '>', 0);
        
        // Date filter: use pay_date for fees table
        if ($startDate) {
            $feesTableQuery->whereDate('pay_date', '>=', $startDate);
        }
        if ($endDate) {
            $feesTableQuery->whereDate('pay_date', '<=', $endDate);
        }
        
        $feesTableTotal = $feesTableQuery->sum('paid_amount');
        
        // B. From payment_receipts table (new system - verified payments only)
        $paymentReceiptsQuery = PaymentReceipt::where('verification_status', 'verified');
        
        if ($startDate) {
            $paymentReceiptsQuery->where('payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $paymentReceiptsQuery->where('payment_date', '<=', $endDate);
        }
        
        $paymentReceiptsTotal = $paymentReceiptsQuery->sum('amount');
        
        // Total student fees revenue (combine both sources)
        $data['studentFeesRevenue'] = $feesTableTotal + $paymentReceiptsTotal;
        
        // Breakdown by fee category (FeesCategory)
        // Show ALL active fee categories (including those with zero payments)
        
        // Start with all active fee categories
        $allFeeCategories = \App\Models\FeesCategory::where('status', 1)
            ->select('id as category_id', 'title as category_name')
            ->get();
        
        // Get from fees table grouped by category
        $feesByCategory = \App\Models\Fee::where('fees.paid_amount', '>', 0)
            ->when($startDate, function($q) use ($startDate) {
                return $q->whereDate('fees.pay_date', '>=', $startDate);
            })
            ->when($endDate, function($q) use ($endDate) {
                return $q->whereDate('fees.pay_date', '<=', $endDate);
            })
            ->join('fees_categories', 'fees.category_id', '=', 'fees_categories.id')
            ->where('fees_categories.status', 1)
            ->selectRaw('fees_categories.id as category_id, SUM(fees.paid_amount) as total')
            ->groupBy('fees_categories.id')
            ->pluck('total', 'category_id');
        
        // Get from payment_receipts table grouped by category
        $paymentReceiptsByCategory = PaymentReceipt::where('payment_receipts.verification_status', 'verified')
            ->when($startDate, function($q) use ($startDate) {
                return $q->where('payment_receipts.payment_date', '>=', $startDate);
            })
            ->when($endDate, function($q) use ($endDate) {
                return $q->where('payment_receipts.payment_date', '<=', $endDate);
            })
            ->join('fees', 'payment_receipts.fee_id', '=', 'fees.id')
            ->join('fees_categories', 'fees.category_id', '=', 'fees_categories.id')
            ->where('fees_categories.status', 1)
            ->selectRaw('fees_categories.id as category_id, SUM(payment_receipts.amount) as total')
            ->groupBy('fees_categories.id')
            ->pluck('total', 'category_id');
        
        // Merge totals for each category
        foreach ($allFeeCategories as $category) {
            $feesTotal = $feesByCategory->get($category->category_id, 0);
            $receiptsTotal = $paymentReceiptsByCategory->get($category->category_id, 0);
            $category->total = $feesTotal + $receiptsTotal;
        }
        
        $data['feesByCategory'] = $allFeeCategories->sortByDesc('total')->values();
        
        // 2. OTHER INCOME (from incomes table)
        // Get all approved/active income
        $incomesQuery = Income::where('status', 1)
            ->where('date', '<=', $endDate);
        if ($startDate) {
            $incomesQuery->where('date', '>=', $startDate);
        }
        $data['otherIncomeTotal'] = $incomesQuery->sum('amount');
        
        // Breakdown by income category
        // Show ALL active income categories (including those with zero income)
        $data['incomeByCategory'] = \App\Models\IncomeCategory::where('income_categories.status', 1)
            ->leftJoin('incomes', function($join) use ($startDate, $endDate) {
                $join->on('income_categories.id', '=', 'incomes.category_id')
                    ->where('incomes.status', 1)
                    ->where('incomes.date', '<=', $endDate);
                if ($startDate) {
                    $join->where('incomes.date', '>=', $startDate);
                }
            })
            ->selectRaw('income_categories.id as category_id, income_categories.title as category_name, COALESCE(SUM(incomes.amount), 0) as total')
            ->groupBy('income_categories.id', 'income_categories.title')
            ->orderByDesc('total')
            ->get();
        
        // TOTAL REVENUE
        $data['totalRevenue'] = $data['studentFeesRevenue'] + $data['otherIncomeTotal'];
        
        // =================================================================
        // EXPENSES SECTION - All Cost Categories
        // =================================================================
        
        // 1. OPERATING EXPENSES (from expenses table)
        // Get all approved expenses
        $expensesQuery = Expense::where('status', 1)
            ->where('date', '<=', $endDate);
        if ($startDate) {
            $expensesQuery->where('date', '>=', $startDate);
        }
        $data['operatingExpensesTotal'] = $expensesQuery->sum('amount');
        
        // Breakdown by expense category
        // Show ALL active expense categories (including those with zero expenses)
        $data['expensesByCategory'] = \App\Models\ExpenseCategory::where('expense_categories.status', 1)
            ->leftJoin('expenses', function($join) use ($startDate, $endDate) {
                $join->on('expense_categories.id', '=', 'expenses.category_id')
                    ->where('expenses.status', 1)
                    ->where('expenses.date', '<=', $endDate);
                if ($startDate) {
                    $join->where('expenses.date', '>=', $startDate);
                }
            })
            ->selectRaw('expense_categories.id as category_id, expense_categories.title as category_name, COALESCE(SUM(expenses.amount), 0) as total')
            ->groupBy('expense_categories.id', 'expense_categories.title')
            ->orderByDesc('total')
            ->get();
        
        // 2. PAYROLL & SALARIES (from payrolls table)
        // Get all staff salary payments
        $payrollsQuery = Payroll::whereNotNull('pay_date')
            ->where('pay_date', '<=', $endDate);
        if ($startDate) {
            $payrollsQuery->where('pay_date', '>=', $startDate);
        }
        $data['payrollTotal'] = $payrollsQuery->sum('net_salary');
        
        // Payroll details breakdown
        $data['payrollBreakdown'] = Payroll::whereNotNull('pay_date')
            ->where('pay_date', '<=', $endDate)
            ->when($startDate, function($q) use ($startDate) {
                return $q->where('pay_date', '>=', $startDate);
            })
            ->selectRaw('
                COUNT(*) as staff_count,
                SUM(basic_salary) as total_basic_salary,
                SUM(total_allowance) as total_allowances,
                SUM(bonus) as total_bonuses,
                SUM(total_deduction) as total_deductions,
                SUM(tax) as total_tax,
                SUM(net_salary) as total_net_salary
            ')
            ->first();
        
        // TOTAL EXPENSES
        $data['totalExpenses'] = $data['operatingExpensesTotal'] + $data['payrollTotal'];
        
        // =================================================================
        // FINANCIAL METRICS & CALCULATIONS
        // =================================================================
        
        // Gross Profit (Revenue - Operating Expenses excluding payroll)
        $data['grossProfit'] = $data['totalRevenue'] - $data['operatingExpensesTotal'];
        
        // Operating Profit/Loss (Revenue - All Expenses)
        $data['operatingProfit'] = $data['totalRevenue'] - $data['totalExpenses'];
        
        // Net Profit/Loss (same as operating profit since no non-operating items)
        $data['netProfit'] = $data['operatingProfit'];
        
        // Financial Ratios
        $data['operatingMargin'] = $data['totalRevenue'] > 0 
            ? ($data['operatingProfit'] / $data['totalRevenue']) * 100 
            : 0;
        
        $data['payrollAsPercentOfRevenue'] = $data['totalRevenue'] > 0 
            ? ($data['payrollTotal'] / $data['totalRevenue']) * 100 
            : 0;
        
        $data['operatingExpensesAsPercentOfRevenue'] = $data['totalRevenue'] > 0 
            ? ($data['operatingExpensesTotal'] / $data['totalRevenue']) * 100 
            : 0;
        
        // Get fiscal years for filter
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();
        
        return view('admin.general-ledger.income-statement', $data);
    }

    /**
     * Generate Income Statement from Journal Entries (Accounting View)
     * This method uses posted journal entries to calculate the income statement,
     * ensuring all figures match the chart of accounts balances
     */
    private function incomeStatementFromJournalEntries(Request $request, $data)
    {
        $startDate = $data['startDate'];
        $endDate = $data['endDate'];
        
        // Get all REVENUE accounts (Class 7 in OHADA)
        $revenueAccountsQuery = ChartOfAccount::where('class_number', 7)
            ->where('is_active', true)
            ->where('account_category', 'detail');
        
        $revenueAccounts = $revenueAccountsQuery->get();
        
        // Calculate revenue for each account from journal entry lines
        $revenueByAccount = [];
        $totalRevenue = 0;
        
        foreach ($revenueAccounts as $account) {
            // Get credits minus debits for revenue accounts (normal balance is credit)
            $credits = JournalEntryLine::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '<=', $endDate);
                    if ($startDate) {
                        $q->where('entry_date', '>=', $startDate);
                    }
                })
                ->where('account_id', $account->id)
                ->sum('credit');
            
            $debits = JournalEntryLine::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '<=', $endDate);
                    if ($startDate) {
                        $q->where('entry_date', '>=', $startDate);
                    }
                })
                ->where('account_id', $account->id)
                ->sum('debit');
            
            $balance = $credits - $debits; // Revenue is credit balance
            
            if ($balance != 0) {
                $revenueByAccount[] = [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'amount' => $balance
                ];
                $totalRevenue += $balance;
            }
        }
        
        // Get all EXPENSE accounts (Class 6 in OHADA)
        $expenseAccountsQuery = ChartOfAccount::where('class_number', 6)
            ->where('is_active', true)
            ->where('account_category', 'detail');
        
        $expenseAccounts = $expenseAccountsQuery->get();
        
        // Calculate expenses for each account from journal entry lines
        $expensesByAccount = [];
        $totalExpenses = 0;
        
        foreach ($expenseAccounts as $account) {
            // Get debits minus credits for expense accounts (normal balance is debit)
            $debits = JournalEntryLine::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '<=', $endDate);
                    if ($startDate) {
                        $q->where('entry_date', '>=', $startDate);
                    }
                })
                ->where('account_id', $account->id)
                ->sum('debit');
            
            $credits = JournalEntryLine::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '<=', $endDate);
                    if ($startDate) {
                        $q->where('entry_date', '>=', $startDate);
                    }
                })
                ->where('account_id', $account->id)
                ->sum('credit');
            
            $balance = $debits - $credits; // Expense is debit balance
            
            if ($balance != 0) {
                $expensesByAccount[] = [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'amount' => $balance
                ];
                $totalExpenses += $balance;
            }
        }
        
        // Get OTHER RESULTS accounts (Class 8 in OHADA)
        $otherResultsAccountsQuery = ChartOfAccount::where('class_number', 8)
            ->where('is_active', true)
            ->where('account_category', 'detail');
        
        $otherResultsAccounts = $otherResultsAccountsQuery->get();
        
        $otherResultsByAccount = [];
        $totalOtherResults = 0;
        
        foreach ($otherResultsAccounts as $account) {
            $debits = JournalEntryLine::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '<=', $endDate);
                    if ($startDate) {
                        $q->where('entry_date', '>=', $startDate);
                    }
                })
                ->where('account_id', $account->id)
                ->sum('debit');
            
            $credits = JournalEntryLine::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->where('is_posted', true)
                      ->where('entry_date', '<=', $endDate);
                    if ($startDate) {
                        $q->where('entry_date', '>=', $startDate);
                    }
                })
                ->where('account_id', $account->id)
                ->sum('credit');
            
            // For class 8, it can be either income or expense
            $balance = $credits - $debits;
            
            if ($balance != 0) {
                $otherResultsByAccount[] = [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'amount' => $balance
                ];
                $totalOtherResults += $balance;
            }
        }
        
        // Prepare data for view
        $data['revenueByAccount'] = collect($revenueByAccount)->sortByDesc('amount')->values();
        $data['expensesByAccount'] = collect($expensesByAccount)->sortByDesc('amount')->values();
        $data['otherResultsByAccount'] = collect($otherResultsByAccount)->sortByDesc('amount')->values();
        
        $data['totalRevenue'] = $totalRevenue;
        $data['totalExpenses'] = $totalExpenses;
        $data['totalOtherResults'] = $totalOtherResults;
        
        // Calculate profit/loss
        $data['operatingProfit'] = $totalRevenue - $totalExpenses;
        $data['netProfit'] = $data['operatingProfit'] + $totalOtherResults;
        
        // Financial metrics
        $data['operatingMargin'] = $totalRevenue > 0 
            ? ($data['operatingProfit'] / $totalRevenue) * 100 
            : 0;
        
        // Get fiscal years for filter
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();
        
        // Use a different view for journal-based income statement
        return view('admin.general-ledger.income-statement-journal', $data);
    }

    /**
     * Get balances for all accounts in a class
     */
    private function getClassBalances($classNumber, $asOfDate, $debitOnly = false, $creditOnly = false, $startDate = null)
    {
        $query = ChartOfAccount::where('account_code', 'LIKE', $classNumber . '%')
            ->where('account_category', 'detail')
            ->where('is_active', true);
        
        if ($debitOnly) {
            $query->where('normal_balance', 'debit');
        }
        
        if ($creditOnly) {
            $query->where('normal_balance', 'credit');
        }
        
        $accounts = $query->orderBy('account_code')->get();
        
        // Calculate balance for each account
        $accounts->transform(function($account) use ($asOfDate, $startDate) {
            if ($startDate) {
                // For income statement - period activity only
                $account->period_debit = $this->calculatePeriodDebit($account->id, $startDate, $asOfDate);
                $account->period_credit = $this->calculatePeriodCredit($account->id, $startDate, $asOfDate);
                
                if ($account->normal_balance === 'debit') {
                    $account->balance = $account->period_debit - $account->period_credit;
                } else {
                    $account->balance = $account->period_credit - $account->period_debit;
                }
            } else {
                // For balance sheet - cumulative balance
                $account->balance = $this->calculateOpeningBalance($account->id, Carbon::parse($asOfDate)->addDay()->format('Y-m-d'));
            }
            
            return $account;
        });
        
        // Filter out zero balances
        return $accounts->filter(function($account) {
            return $account->balance != 0;
        });
    }

    /**
     * Get breakdown by major categories
     */
    private function getClassBreakdown($classNumber, $startDate, $endDate)
    {
        $breakdown = [];
        
        // Get all accounts
        $accounts = ChartOfAccount::where('account_code', 'LIKE', $classNumber . '%')
            ->where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
        
        foreach ($accounts as $account) {
            $categoryCode = substr($account->account_code, 0, 2); // First 2 digits
            
            if (!isset($breakdown[$categoryCode])) {
                $breakdown[$categoryCode] = [
                    'name' => $account->parent ? $account->parent->account_name : 'Other',
                    'accounts' => collect(),
                    'total' => 0
                ];
            }
            
            $debit = $this->calculatePeriodDebit($account->id, $startDate, $endDate);
            $credit = $this->calculatePeriodCredit($account->id, $startDate, $endDate);
            
            if ($account->normal_balance === 'debit') {
                $balance = $debit - $credit;
            } else {
                $balance = $credit - $debit;
            }
            
            if ($balance != 0) {
                $account->balance = $balance;
                $breakdown[$categoryCode]['accounts']->push($account);
                $breakdown[$categoryCode]['total'] += $balance;
            }
        }
        
        return collect($breakdown)->filter(function($item) {
            return $item['total'] != 0;
        });
    }

    /**
     * Calculate total from multiple account collections
     */
    private function calculateTotal($collections)
    {
        $total = 0;
        
        foreach ($collections as $collection) {
            if ($collection) {
                $total += $collection->sum('balance');
            }
        }
        
        return $total;
    }

    /**
     * Export Balance Sheet to PDF
     */
    public function exportBalanceSheetPDF(Request $request)
    {
        $asOfDate = $request->input('as_of_date', date('Y-m-d'));
        $comparativeDate = $request->input('comparative_date');
        
        // Get all balance sheet data
        $data = $this->getBalanceSheetData($asOfDate, $comparativeDate);
        $data['asOfDate'] = $asOfDate;
        $data['comparativeDate'] = $comparativeDate;
        
        $pdf = PDF::loadView('admin.general-ledger.balance-sheet-pdf', $data);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->download('balance-sheet-' . $asOfDate . '.pdf');
    }

    /**
     * Export Balance Sheet to Excel
     */
    public function exportBalanceSheetExcel(Request $request)
    {
        // Implementation with Maatwebsite\Excel
        return response()->json([
            'message' => 'Excel export functionality - use Maatwebsite\Excel package'
        ]);
    }

    /**
     * Export Income Statement to PDF
     */
    public function exportIncomeStatementPDF(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date', date('Y-m-d'));
        
        // Get all income statement data
        $data = $this->getIncomeStatementData($startDate, $endDate);
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        
        $pdf = PDF::loadView('admin.general-ledger.income-statement-pdf', $data);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->download('income-statement-' . $endDate . '.pdf');
    }

    /**
     * Export Income Statement to Excel
     */
    public function exportIncomeStatementExcel(Request $request)
    {
        // Implementation with Maatwebsite\Excel
        return response()->json([
            'message' => 'Excel export functionality - use Maatwebsite\Excel package'
        ]);
    }

    /**
     * Get Balance Sheet data for export
     */
    private function getBalanceSheetData($asOfDate, $comparativeDate = null)
    {
        $data = [];
        
        // Assets
        $data['fixedAssets'] = $this->getClassBalances(2, $asOfDate);
        $data['inventory'] = $this->getClassBalances(3, $asOfDate);
        $data['receivables'] = $this->getClassBalances(4, $asOfDate, true);
        $data['cashAndBanks'] = $this->getClassBalances(5, $asOfDate);
        
        // Liabilities & Equity
        $data['equity'] = $this->getClassBalances(1, $asOfDate);
        $data['payables'] = $this->getClassBalances(4, $asOfDate, false, true);
        
        // Calculate totals
        $data['totalAssets'] = $this->calculateTotal([
            $data['fixedAssets'],
            $data['inventory'],
            $data['receivables'],
            $data['cashAndBanks']
        ]);
        
        $data['totalLiabilities'] = $this->calculateTotal([
            $data['equity'],
            $data['payables']
        ]);
        
        if ($comparativeDate) {
            $data['fixedAssets_comparative'] = $this->getClassBalances(2, $comparativeDate);
            $data['inventory_comparative'] = $this->getClassBalances(3, $comparativeDate);
            $data['receivables_comparative'] = $this->getClassBalances(4, $comparativeDate, true);
            $data['cashAndBanks_comparative'] = $this->getClassBalances(5, $comparativeDate);
            $data['equity_comparative'] = $this->getClassBalances(1, $comparativeDate);
            $data['payables_comparative'] = $this->getClassBalances(4, $comparativeDate, false, true);
            
            $data['totalAssets_comparative'] = $this->calculateTotal([
                $data['fixedAssets_comparative'],
                $data['inventory_comparative'],
                $data['receivables_comparative'],
                $data['cashAndBanks_comparative']
            ]);
            
            $data['totalLiabilities_comparative'] = $this->calculateTotal([
                $data['equity_comparative'],
                $data['payables_comparative']
            ]);
        }
        
        return $data;
    }

    /**
     * Get Income Statement data for export
     */
    private function getIncomeStatementData($startDate, $endDate)
    {
        $data = [];
        
        $data['expenses'] = $this->getClassBalances(6, $endDate, false, false, $startDate);
        $data['revenue'] = $this->getClassBalances(7, $endDate, false, false, $startDate);
        $data['otherResults'] = $this->getClassBalances(8, $endDate, false, false, $startDate);
        
        $data['totalExpenses'] = $this->calculateTotal([$data['expenses']]);
        $data['totalRevenue'] = $this->calculateTotal([$data['revenue']]);
        $data['totalOtherResults'] = $this->calculateTotal([$data['otherResults']]);
        
        $data['netProfit'] = $data['totalRevenue'] + $data['totalOtherResults'] - $data['totalExpenses'];
        
        
                $data['expensesByCategory'] = $this->getClassBreakdown(6, $startDate, $endDate);
        $data['revenueByCategory'] = $this->getClassBreakdown(7, $startDate, $endDate);
        
        return $data;
    }
}


