<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DefaultAccountMapping;
use App\Models\TransactionMapping;
use App\Models\ChartOfAccount;
use App\Models\FeesCategory;
use App\Models\IncomeCategory;
use App\Models\ExpenseCategory;
use App\Models\Fee;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AccountMappingController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:transaction-mapping-view', ['only' => ['transactions']]);
        $this->middleware('permission:transaction-mapping-settings', ['only' => ['settings', 'saveDefault']]);
        $this->middleware('permission:transaction-mapping-manage', ['only' => ['saveMapping', 'deleteMapping']]);
        $this->middleware('permission:transaction-mapping-remap', ['only' => ['remapTransaction']]);
    }

    /**
     * Display the default mappings settings page
     */
    public function settings()
    {
        // Get all active categories
        $feeCategories = FeesCategory::where('status', '1')->get();
        $incomeCategories = IncomeCategory::where('status', '1')->get();
        $expenseCategories = ExpenseCategory::where('status', '1')->get();

        // Get all postable accounts (detail accounts only)
        $accounts = ChartOfAccount::active()
            ->postable()
            ->orderBy('account_code')
            ->get();

        // Get existing default mappings
        $existingMappings = DefaultAccountMapping::with(['debitAccount', 'creditAccount'])
            ->get()
            ->groupBy('mapping_type');

        return view('admin.accounting.mappings.settings', compact(
            'feeCategories',
            'incomeCategories',
            'expenseCategories',
            'accounts',
            'existingMappings'
        ));
    }

    /**
     * Save default mappings
     */
    public function saveDefaultMappings(Request $request)
    {
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.mapping_type' => 'required|in:fee_category,income_category,expense_category,payroll,payroll_tax,payroll_staff_payable,payroll_allowance,payroll_deduction',
            'mappings.*.category_id' => 'nullable|integer',
            'mappings.*.debit_account_id' => 'required|exists:chart_of_accounts,id',
            'mappings.*.credit_account_id' => 'required|exists:chart_of_accounts,id',
            'mappings.*.description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->mappings as $mappingData) {
                // Ensure category_id is null if empty (not empty string)
                $categoryId = !empty($mappingData['category_id']) ? $mappingData['category_id'] : null;
                
                // Find or create mapping
                $mapping = DefaultAccountMapping::updateOrCreate(
                    [
                        'mapping_type' => $mappingData['mapping_type'],
                        'category_id' => $categoryId,
                    ],
                    [
                        'debit_account_id' => $mappingData['debit_account_id'],
                        'credit_account_id' => $mappingData['credit_account_id'],
                        'description' => $mappingData['description'] ?? null,
                        'status' => 'active',
                        'created_by' => $mappingData['created_by'] ?? Auth::id(),
                        'updated_by' => Auth::id(),
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default mappings saved successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error saving mappings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display all transactions with mapping status
     */
    public function transactionsList(Request $request)
    {
        $type = $request->get('type', 'all');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $status = $request->get('status', 'all'); // all, mapped, unmapped

        // Build transactions array
        $transactions = [];

        // Fetch fees
        if ($type === 'all' || $type === 'fee') {
            $fees = Fee::with(['category', 'studentEnroll.student'])
                ->when($dateFrom, fn($q) => $q->where('pay_date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('pay_date', '<=', $dateTo))
                ->get();

            foreach ($fees as $fee) {
                $mapping = TransactionMapping::where('transaction_type', 'fee')
                    ->where('transaction_id', $fee->id)
                    ->first();

                if ($status === 'mapped' && !$mapping) continue;
                if ($status === 'unmapped' && $mapping) continue;

                // Get student name safely
                $studentName = 'N/A';
                if ($fee->studentEnroll && $fee->studentEnroll->student) {
                    $studentName = trim($fee->studentEnroll->student->first_name . ' ' . $fee->studentEnroll->student->last_name);
                }

                $transactions[] = [
                    'id' => $fee->id,
                    'type' => 'fee',
                    'type_label' => 'Student Fee',
                    'date' => $fee->pay_date,
                    'description' => $fee->category->name ?? 'Fee Payment',
                    'reference' => 'Student: ' . $studentName,
                    'amount' => $fee->paid_amount,
                    'is_mapped' => !is_null($mapping),
                    'mapping' => $mapping,
                ];
            }
        }

        // Fetch income
        if ($type === 'all' || $type === 'income') {
            $incomes = Income::with('category')
                ->when($dateFrom, fn($q) => $q->where('date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('date', '<=', $dateTo))
                ->get();

            foreach ($incomes as $income) {
                $mapping = TransactionMapping::where('transaction_type', 'income')
                    ->where('transaction_id', $income->id)
                    ->first();

                if ($status === 'mapped' && !$mapping) continue;
                if ($status === 'unmapped' && $mapping) continue;

                $transactions[] = [
                    'id' => $income->id,
                    'type' => 'income',
                    'type_label' => 'Income',
                    'date' => $income->date,
                    'description' => $income->category->name ?? 'Income',
                    'reference' => $income->description ?? '',
                    'amount' => $income->amount,
                    'is_mapped' => !is_null($mapping),
                    'mapping' => $mapping,
                ];
            }
        }

        // Fetch expenses
        if ($type === 'all' || $type === 'expense') {
            $expenses = Expense::with('category')
                ->when($dateFrom, fn($q) => $q->where('date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('date', '<=', $dateTo))
                ->get();

            foreach ($expenses as $expense) {
                $mapping = TransactionMapping::where('transaction_type', 'expense')
                    ->where('transaction_id', $expense->id)
                    ->first();

                if ($status === 'mapped' && !$mapping) continue;
                if ($status === 'unmapped' && $mapping) continue;

                $transactions[] = [
                    'id' => $expense->id,
                    'type' => 'expense',
                    'type_label' => 'Expense',
                    'date' => $expense->date,
                    'description' => $expense->category->name ?? 'Expense',
                    'reference' => $expense->description ?? '',
                    'amount' => $expense->amount,
                    'is_mapped' => !is_null($mapping),
                    'mapping' => $mapping,
                ];
            }
        }

        // Fetch payrolls (only paid payrolls should appear in transactions)
        if ($type === 'all' || $type === 'payroll') {
            $payrolls = Payroll::with('user')
                ->where('status', '1') // Only paid payrolls
                ->whereNotNull('pay_date') // Ensure pay_date exists
                ->when($dateFrom, fn($q) => $q->where('pay_date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('pay_date', '<=', $dateTo))
                ->get();

            foreach ($payrolls as $payroll) {
                $mapping = TransactionMapping::where('transaction_type', 'payroll')
                    ->where('transaction_id', $payroll->id)
                    ->first();

                if ($status === 'mapped' && !$mapping) continue;
                if ($status === 'unmapped' && $mapping) continue;

                // Get staff name safely
                $staffName = 'N/A';
                if ($payroll->user) {
                    $staffName = trim($payroll->user->name ?? ($payroll->user->first_name . ' ' . $payroll->user->last_name));
                }

                // Format salary month
                $salaryMonth = $payroll->salary_month ? date('F Y', strtotime($payroll->salary_month)) : 'N/A';

                $transactions[] = [
                    'id' => $payroll->id,
                    'type' => 'payroll',
                    'type_label' => 'Payroll',
                    'date' => $payroll->pay_date,
                    'description' => 'Salary Payment - ' . $salaryMonth,
                    'reference' => 'Staff: ' . $staffName . ' | Net: ' . number_format($payroll->net_salary, 0) . ' | Tax: ' . number_format($payroll->tax, 0),
                    'amount' => $payroll->net_salary,
                    'is_mapped' => !is_null($mapping),
                    'mapping' => $mapping,
                ];
            }
        }

        // Fetch payment plan payments
        if ($type === 'all' || $type === 'payment_plan_payment') {
            $payments = \App\Models\PaymentPlanPayment::with(['installment.paymentPlan.fee.category', 'installment.paymentPlan.student'])
                ->when($dateFrom, fn($q) => $q->where('payment_date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('payment_date', '<=', $dateTo))
                ->get();

            foreach ($payments as $payment) {
                $mapping = TransactionMapping::where('transaction_type', 'payment_plan_payment')
                    ->where('transaction_id', $payment->id)
                    ->first();

                if ($status === 'mapped' && !$mapping) continue;
                if ($status === 'unmapped' && $mapping) continue;

                $student = $payment->installment->paymentPlan->student ?? null;
                $fee = $payment->installment->paymentPlan->fee ?? null;
                $installmentNo = $payment->installment->installment_number ?? '?';

                $transactions[] = [
                    'id' => $payment->id,
                    'type' => 'payment_plan_payment',
                    'type_label' => 'Payment Plan',
                    'date' => $payment->payment_date,
                    'description' => sprintf(
                        'Installment #%s - %s',
                        $installmentNo,
                        $fee->category->name ?? 'Fee'
                    ),
                    'reference' => 'Student: ' . ($student ? ($student->first_name . ' ' . $student->last_name) : 'N/A'),
                    'amount' => $payment->amount,
                    'is_mapped' => !is_null($mapping),
                    'mapping' => $mapping,
                ];
            }
        }

        // Sort by date descending
        usort($transactions, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // Implement pagination
        $perPage = 50; // Show 50 records per page
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $totalRecords = count($transactions);
        $totalPages = ceil($totalRecords / $perPage);
        
        $paginatedTransactions = array_slice($transactions, $offset, $perPage);

        // Get accounts for mapping modal
        $accounts = ChartOfAccount::active()->postable()->orderBy('account_code')->get();

        return view('admin.accounting.mappings.transactions', compact('paginatedTransactions', 'accounts', 'totalRecords', 'totalPages', 'currentPage', 'perPage'));
    }

    /**
     * Map a transaction to accounts
     */
    public function mapTransaction(Request $request)
    {
        $request->validate([
            'transaction_type' => 'required|in:fee,income,expense,payroll',
            'transaction_id' => 'required|integer',
            'debit_account_id' => 'required|exists:chart_of_accounts,id',
            'credit_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Get the transaction details
            $transaction = $this->getTransaction($request->transaction_type, $request->transaction_id);
            
            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            // Create or update the mapping
            $mapping = TransactionMapping::updateOrCreate(
                [
                    'transaction_type' => $request->transaction_type,
                    'transaction_id' => $request->transaction_id,
                ],
                [
                    'debit_account_id' => $request->debit_account_id,
                    'credit_account_id' => $request->credit_account_id,
                    'amount' => $transaction['amount'],
                    'transaction_date' => $transaction['date'],
                    'description' => $request->description ?? $transaction['description'],
                    'mapped_at' => now(),
                    'mapped_by' => Auth::id(),
                    'status' => 'active',
                ]
            );

            // Create journal entry
            $journalEntry = $this->createJournalEntry($mapping, $transaction);
            
            // Update mapping with journal entry id
            $mapping->journal_entry_id = $journalEntry->id;
            $mapping->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction mapped successfully and journal entry created!',
                'journal_entry_id' => $journalEntry->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error mapping transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing mapping
     */
    public function updateMapping(Request $request, $mappingId)
    {
        $request->validate([
            'debit_account_id' => 'required|exists:chart_of_accounts,id',
            'credit_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $mapping = TransactionMapping::findOrFail($mappingId);

            // If journal entry exists, unpost it first
            if ($mapping->journalEntry && $mapping->journalEntry->is_posted) {
                $mapping->journalEntry->unpost();
            }

            // Delete old journal entry if exists
            if ($mapping->journal_entry_id) {
                JournalEntry::find($mapping->journal_entry_id)?->delete();
            }

            // Update mapping
            $mapping->debit_account_id = $request->debit_account_id;
            $mapping->credit_account_id = $request->credit_account_id;
            $mapping->description = $request->description;
            $mapping->mapped_at = now();
            $mapping->mapped_by = Auth::id();
            $mapping->save();

            // Get transaction details
            $transaction = $this->getTransaction($mapping->transaction_type, $mapping->transaction_id);

            // Create new journal entry
            $journalEntry = $this->createJournalEntry($mapping, $transaction);
            
            // Update mapping with new journal entry id
            $mapping->journal_entry_id = $journalEntry->id;
            $mapping->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mapping updated successfully and journal entry recreated!',
                'journal_entry_id' => $journalEntry->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating mapping: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction details by type and ID
     */
    private function getTransaction($type, $id)
    {
        switch ($type) {
            case 'fee':
                $fee = Fee::with('category', 'studentEnroll.student')->find($id);
                return $fee ? [
                    'amount' => $fee->paid_amount,
                    'date' => $fee->pay_date,
                    'description' => ($fee->category->name ?? 'Fee') . ' - ' . ($fee->studentEnroll->student->name ?? 'Student')
                ] : null;

            case 'income':
                $income = Income::with('category')->find($id);
                return $income ? [
                    'amount' => $income->amount,
                    'date' => $income->date,
                    'description' => ($income->category->name ?? 'Income') . ' - ' . ($income->description ?? '')
                ] : null;

            case 'expense':
                $expense = Expense::with('category')->find($id);
                return $expense ? [
                    'amount' => $expense->amount,
                    'date' => $expense->date,
                    'description' => ($expense->category->name ?? 'Expense') . ' - ' . ($expense->description ?? '')
                ] : null;

            case 'payroll':
                $payroll = Payroll::with('user')->find($id);
                if (!$payroll) return null;
                
                // Get staff name safely
                $staffName = 'Staff';
                if ($payroll->user) {
                    $staffName = trim($payroll->user->first_name . ' ' . $payroll->user->last_name);
                    if (empty($staffName)) {
                        $staffName = $payroll->user->name ?? 'Staff #' . $payroll->user_id;
                    }
                }
                
                return [
                    'amount' => $payroll->net_salary,
                    'date' => $payroll->pay_date,
                    'description' => 'Salary Payment - ' . $staffName . ' (' . date('F Y', strtotime($payroll->salary_month)) . ')'
                ];

            default:
                return null;
        }
    }

    /**
     * Create journal entry from mapping
     */
    private function createJournalEntry($mapping, $transaction)
    {
        // Get active fiscal year and period
        $fiscalYear = FiscalYear::where('is_active', true)->first();
        $accountingPeriod = AccountingPeriod::where('is_closed', false)
            ->where('fiscal_year_id', $fiscalYear->id ?? null)
            ->where('start_date', '<=', $mapping->transaction_date)
            ->where('end_date', '>=', $mapping->transaction_date)
            ->first();

        // Create journal entry
        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $mapping->transaction_date,
            'fiscal_year_id' => $fiscalYear->id ?? null,
            'accounting_period_id' => $accountingPeriod->id ?? null,
            'journal_type' => 'general',
            'description' => $mapping->description ?? $transaction['description'],
            'reference_type' => $mapping->transaction_type,
            'reference_id' => $mapping->transaction_id,
            'total_debit' => $mapping->amount,
            'total_credit' => $mapping->amount,
            'is_posted' => false,
            'is_system_generated' => true,
            'created_by' => Auth::id(),
        ]);

        // Create debit line
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'line_number' => 1,
            'account_id' => $mapping->debit_account_id,
            'description' => $mapping->description ?? $transaction['description'],
            'debit' => $mapping->amount,
            'credit' => 0,
        ]);

        // Create credit line
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'line_number' => 2,
            'account_id' => $mapping->credit_account_id,
            'description' => $mapping->description ?? $transaction['description'],
            'debit' => 0,
            'credit' => $mapping->amount,
        ]);

        // Post the journal entry
        $journalEntry->post(Auth::id());

        return $journalEntry;
    }

    /**
     * Auto-map a transaction using default mappings
     */
    public function autoMap(Request $request)
    {
        $request->validate([
            'transaction_type' => 'required|in:fee,income,expense,payroll',
            'transaction_id' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            // Get the transaction
            $transaction = $this->getTransaction($request->transaction_type, $request->transaction_id);
            
            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            // Get category ID based on transaction type
            $categoryId = $this->getTransactionCategoryId($request->transaction_type, $request->transaction_id);

            // Find default mapping
            $mappingType = $request->transaction_type === 'fee' ? 'fee_category' : (
                          $request->transaction_type === 'income' ? 'income_category' : (
                          $request->transaction_type === 'expense' ? 'expense_category' : 
                          'payroll'));

            // Build the query - for payroll, category_id is null
            $defaultMappingQuery = DefaultAccountMapping::where('mapping_type', $mappingType)
                ->where('status', 'active');
            
            if ($categoryId !== null) {
                $defaultMappingQuery->where('category_id', $categoryId);
            } else {
                $defaultMappingQuery->whereNull('category_id');
            }
            
            $defaultMapping = $defaultMappingQuery->first();

            if (!$defaultMapping) {
                throw new \Exception('No default mapping found for this ' . $request->transaction_type . '. Please configure mappings in Settings.');
            }

            // Create the mapping using default accounts
            $mapping = TransactionMapping::updateOrCreate(
                [
                    'transaction_type' => $request->transaction_type,
                    'transaction_id' => $request->transaction_id,
                ],
                [
                    'debit_account_id' => $defaultMapping->debit_account_id,
                    'credit_account_id' => $defaultMapping->credit_account_id,
                    'amount' => $transaction['amount'],
                    'transaction_date' => $transaction['date'],
                    'description' => $defaultMapping->description ?? $transaction['description'],
                    'mapped_at' => now(),
                    'mapped_by' => Auth::id(),
                    'status' => 'active',
                ]
            );

            // Create journal entry
            $journalEntry = $this->createJournalEntry($mapping, $transaction);
            
            // Update mapping with journal entry id
            $mapping->journal_entry_id = $journalEntry->id;
            $mapping->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction auto-mapped successfully using default mapping!',
                'journal_entry_id' => $journalEntry->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error auto-mapping transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category ID for a transaction
     */
    private function getTransactionCategoryId($type, $id)
    {
        switch ($type) {
            case 'fee':
                $fee = Fee::find($id);
                return $fee->fees_category_id ?? null;

            case 'income':
                $income = Income::find($id);
                return $income->income_category_id ?? null;

            case 'expense':
                $expense = Expense::find($id);
                return $expense->expense_category_id ?? null;

            case 'payroll':
                return null; // Payroll doesn't have a category

            default:
                return null;
        }
    }
}

