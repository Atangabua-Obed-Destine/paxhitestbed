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
        // These used to name methods that do not exist — transactions,
        // saveDefault, saveMapping, deleteMapping, remapTransaction — so
        // Laravel matched nothing and applied nothing. Every route except the
        // settings page was open to any logged-in staff member, including
        // save-default, which decides which account every franc posts to.
        //
        // The names below are the real ones, and scripts/ledger_sync_test.php
        // checks each route is refused without its permission, so a rename
        // that drifts from them again fails a test instead of opening a door.
        $this->middleware('permission:transaction-mapping-view', ['only' => ['transactionsList']]);
        $this->middleware('permission:transaction-mapping-settings', ['only' => ['settings', 'saveDefaultMappings']]);
        $this->middleware('permission:transaction-mapping-manage', ['only' => ['mapTransaction', 'autoMap', 'bulkSync']]);
        $this->middleware('permission:transaction-mapping-remap', ['only' => ['updateMapping']]);
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

        // Where each category appears on the Income & Expenditure sheet.
        // Headers are excluded: they total their children and can never carry a
        // figure of their own.
        $budgetLines = \App\Models\BudgetLine::active()
            ->postable()
            ->orderByRaw("FIELD(section, 'income', 'expenditure', 'capital')")
            ->orderBy('code')
            ->get();

        return view('admin.accounting.mappings.settings', compact(
            'feeCategories',
            'incomeCategories',
            'expenseCategories',
            'accounts',
            'existingMappings',
            'budgetLines'
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
            'mappings.*.budget_line_id' => 'nullable|exists:budget_lines,id',
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
                        // Blank means the category has no home on the sheet
                        // yet; the report lists it as unallocated rather than
                        // dropping it.
                        'budget_line_id' => !empty($mappingData['budget_line_id'])
                            ? $mappingData['budget_line_id']
                            : null,
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
     * Display all transactions with mapping status.
     *
     * Each row is also classified by the ledger sync rule, so the page offers a
     * checkbox only where posting is actually possible and says why where it is
     * not. The rule is LedgerSyncService::decide() — the same one the sync
     * applies when it posts, not a second copy of it.
     */
    public function transactionsList(Request $request, \App\Services\LedgerSyncService $sync)
    {
        $type = $request->get('type', 'all');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $status = $request->get('status', 'all'); // all, mapped, unmapped

        // Fetched once, not once per row — the page lists every transaction
        // before paginating, so the per-row lookup ran hundreds of queries.
        //
        // Only an ACTIVE mapping means posted. A reversed one means the posting
        // was undone; counting it as mapped hid it from the one screen that
        // exists to post it again.
        $active = TransactionMapping::where('status', 'active')->get()
            ->keyBy(fn ($m) => $m->transaction_type . ':' . $m->transaction_id);

        $defaults = $sync->defaultIndex();
        $closed = $sync->closedPeriods();

        $transactions = [];

        $add = function (string $txType, $model, array $row) use (&$transactions, $active, $sync, $defaults, $closed, $status) {
            $mapping = $active->get($txType . ':' . $model->id);

            if ($status === 'mapped' && !$mapping) {
                return;
            }
            if ($status === 'unmapped' && $mapping) {
                return;
            }

            $transactions[] = $row + [
                'id' => $model->id,
                'type' => $txType,
                'is_mapped' => $mapping !== null,
                'mapping' => $mapping,
                'sync' => $sync->decide($txType, $model, $mapping !== null, $defaults, $closed),
            ];
        };

        // Fetch fees
        if ($type === 'all' || $type === 'fee') {
            $fees = Fee::with(['category', 'studentEnroll.student'])
                ->when($dateFrom, fn($q) => $q->where('pay_date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('pay_date', '<=', $dateTo))
                ->get();

            foreach ($fees as $fee) {
                $studentName = 'N/A';
                if ($fee->studentEnroll && $fee->studentEnroll->student) {
                    $studentName = trim($fee->studentEnroll->student->first_name . ' ' . $fee->studentEnroll->student->last_name);
                }

                $add('fee', $fee, [
                    'type_label' => 'Student Fee',
                    'date' => $fee->pay_date,
                    'description' => $fee->category->title ?? $fee->category->name ?? 'Fee Payment',
                    'reference' => 'Student: ' . $studentName,
                    'amount' => $fee->paid_amount,
                ]);
            }
        }

        // Fetch income
        if ($type === 'all' || $type === 'income') {
            $incomes = Income::with('category')
                ->when($dateFrom, fn($q) => $q->where('date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('date', '<=', $dateTo))
                ->get();

            foreach ($incomes as $income) {
                $add('income', $income, [
                    'type_label' => 'Income',
                    'date' => $income->date,
                    'description' => $income->category->title ?? $income->category->name ?? 'Income',
                    'reference' => $income->description ?? '',
                    'amount' => $income->amount,
                ]);
            }
        }

        // Fetch expenses
        if ($type === 'all' || $type === 'expense') {
            $expenses = Expense::with('category')
                ->when($dateFrom, fn($q) => $q->where('date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('date', '<=', $dateTo))
                ->get();

            foreach ($expenses as $expense) {
                $add('expense', $expense, [
                    'type_label' => 'Expense',
                    'date' => $expense->date,
                    'description' => $expense->category->title ?? $expense->category->name ?? 'Expense',
                    'reference' => $expense->description ?? '',
                    'amount' => $expense->amount,
                ]);
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
                $staffName = 'N/A';
                if ($payroll->user) {
                    $staffName = trim($payroll->user->name ?? ($payroll->user->first_name . ' ' . $payroll->user->last_name));
                }

                $salaryMonth = $payroll->salary_month ? date('F Y', strtotime($payroll->salary_month)) : 'N/A';

                $add('payroll', $payroll, [
                    'type_label' => 'Payroll',
                    'date' => $payroll->pay_date,
                    'description' => 'Salary Payment - ' . $salaryMonth,
                    'reference' => 'Staff: ' . $staffName . ' | Net: ' . number_format($payroll->net_salary, 0) . ' | Tax: ' . number_format($payroll->tax, 0),
                    'amount' => $payroll->net_salary,
                ]);
            }
        }

        // Fetch payment plan payments
        if ($type === 'all' || $type === 'payment_plan_payment') {
            $payments = \App\Models\PaymentPlanPayment::with(['installment.paymentPlan.fee.category', 'installment.paymentPlan.student'])
                ->when($dateFrom, fn($q) => $q->where('payment_date', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('payment_date', '<=', $dateTo))
                ->get();

            foreach ($payments as $payment) {
                $student = $payment->installment->paymentPlan->student ?? null;
                $fee = $payment->installment->paymentPlan->fee ?? null;
                $installmentNo = $payment->installment->installment_number ?? '?';

                $add('payment_plan_payment', $payment, [
                    'type_label' => 'Payment Plan',
                    'date' => $payment->payment_date,
                    'description' => sprintf(
                        'Installment #%s - %s',
                        $installmentNo,
                        $fee->category->title ?? $fee->category->name ?? 'Fee'
                    ),
                    'reference' => 'Student: ' . ($student ? ($student->first_name . ' ' . $student->last_name) : 'N/A'),
                    'amount' => $payment->amount,
                ]);
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
     * Post one transaction using its category's default mapping — the ✨ button.
     *
     * This used to build the posting itself, and read the category from
     * fees_category_id, income_category_id and expense_category_id — columns
     * that do not exist. Every fee, income and expense therefore looked
     * uncategorised, no uncategorised mapping exists, and the button failed on
     * every row it was shown on. It also refused payment-plan payments the
     * page offered it for, and would have posted payroll as a net-only entry
     * on top of the payroll screen's own.
     *
     * It now goes through the same rule and the same posting as the bulk sync
     * and the observers, so all three post a transaction identically.
     */
    public function autoMap(Request $request, \App\Services\LedgerSyncService $sync)
    {
        $request->validate([
            'transaction_type' => 'required|in:' . implode(',', \App\Services\LedgerSyncService::TYPES),
            'transaction_id' => 'required|integer|min:1',
        ]);

        $result = $sync->sync([[
            'type' => $request->transaction_type,
            'id' => (int) $request->transaction_id,
        ]]);

        if ($result['totals']['posted'] === 1) {
            return response()->json([
                'success' => true,
                'message' => __('Posted to the ledger using the default mapping.'),
                'journal_entry_id' => $result['posted'][0]['journal_entry_id'],
            ]);
        }

        // A refusal names what to fix. 422 rather than 500: nothing broke, the
        // transaction simply is not postable yet.
        return response()->json([
            'success' => false,
            'message' => $result['refused'][0]['reason']
                ?? $result['failed'][0]['reason']
                ?? __('This transaction could not be posted.'),
        ], 422);
    }

    /**
     * Post a selection of transactions using their default mappings.
     *
     * Each row is judged again at the moment of posting and stands alone, so
     * one refusal never blocks the rest. Nothing without a configured mapping
     * is posted, and nothing is posted to a guessed account.
     */
    public function bulkSync(Request $request, \App\Services\LedgerSyncService $sync)
    {
        $request->validate([
            'items' => 'required|array|min:1|max:500',
            'items.*.type' => 'required|string|in:' . implode(',', \App\Services\LedgerSyncService::TYPES),
            'items.*.id' => 'required|integer|min:1',
        ]);

        $result = $sync->sync($request->input('items'));
        $t = $result['totals'];

        $message = trans_choice(':count transaction posted|:count transactions posted', $t['posted'], ['count' => $t['posted']]);

        if ($t['refused']) {
            $message .= '. ' . trans_choice(':count refused|:count refused', $t['refused'], ['count' => $t['refused']]);
        }

        if ($t['failed']) {
            $message .= '. ' . trans_choice(':count failed|:count failed', $t['failed'], ['count' => $t['failed']]);
        }

        if ($result['truncated']) {
            $message .= '. ' . __('Only the first :max were processed; sync the rest separately.', ['max' => \App\Services\LedgerSyncService::MAX_PER_REQUEST]);
        }

        return response()->json([
            'success' => $t['failed'] === 0,
            'message' => $message . '.',
        ] + $result);
    }
}

