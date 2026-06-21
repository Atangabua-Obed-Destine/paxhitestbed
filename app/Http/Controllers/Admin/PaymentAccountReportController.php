<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\Fee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payroll;
use App\Models\PaymentReceipt;
use App\Models\PaymentPlanPayment;
use App\Models\MultiPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentAccountReportController extends Controller
{
    protected $title, $route, $view, $access;

    public function __construct()
    {
        $this->title = 'Payment Account Reports';
        $this->route = 'admin.payment-account-report';
        $this->view = 'admin.payment-account.reports';
        $this->access = 'payment-account-report';

        // Apply permissions middleware
        $this->middleware('permission:payment-account-report-view', ['only' => ['index', 'show', 'accountReport', 'transactionReport', 'balanceReport']]);
        $this->middleware('permission:payment-account-report-export', ['only' => ['exportPdf', 'exportExcel']]);
    }

    /**
     * Cash Flow Report - Shows all transactions with running balances
     */
    public function cashflow(Request $request)
    {
        $data['title'] = 'Cash Flow Report';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        // Get all active payment accounts
        $data['accounts'] = PaymentAccount::where('status', 1)->orderBy('title')->get();

        // Filters
        $query = PaymentAccountTransaction::with(['paymentAccount', 'creator']);

        // Account filter
        if ($request->filled('account_id')) {
            $query->where('payment_account_id', $request->account_id);
            $data['selected_account'] = $request->account_id;
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
            $data['date_from'] = $request->date_from;
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
            $data['date_to'] = $request->date_to;
        }

        // Transaction type filter
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
            $data['selected_type'] = $request->transaction_type;
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
            $data['selected_payment_method'] = $request->payment_method;
        }

        // Reference type filter (fees, expense, income, etc.)
        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
            $data['selected_reference_type'] = $request->reference_type;
        }

        // Order by date and time (newest first by default)
        $sortOrder = $request->get('sort', 'desc');
        $query->orderBy('transaction_date', $sortOrder)->orderBy('id', $sortOrder);
        $data['sort_order'] = $sortOrder;

        // Calculate summary statistics BEFORE pagination
        // We need to rebuild the base query for each summary to avoid conflicts
        $baseFilters = [];
        if ($request->filled('account_id')) {
            $baseFilters['payment_account_id'] = $request->account_id;
        }
        if ($request->filled('date_from')) {
            $baseFilters['date_from'] = $request->date_from;
        }
        if ($request->filled('date_to')) {
            $baseFilters['date_to'] = $request->date_to;
        }
        if ($request->filled('payment_method')) {
            $baseFilters['payment_method'] = $request->payment_method;
        }
        if ($request->filled('reference_type')) {
            $baseFilters['reference_type'] = $request->reference_type;
        }

        // Calculate total credit
        $creditQuery = PaymentAccountTransaction::query();
        if (isset($baseFilters['payment_account_id'])) {
            $creditQuery->where('payment_account_id', $baseFilters['payment_account_id']);
        }
        if (isset($baseFilters['date_from'])) {
            $creditQuery->whereDate('transaction_date', '>=', $baseFilters['date_from']);
        }
        if (isset($baseFilters['date_to'])) {
            $creditQuery->whereDate('transaction_date', '<=', $baseFilters['date_to']);
        }
        if (isset($baseFilters['payment_method'])) {
            $creditQuery->where('payment_method', $baseFilters['payment_method']);
        }
        if (isset($baseFilters['reference_type'])) {
            $creditQuery->where('reference_type', $baseFilters['reference_type']);
        }
        $total_credit = $creditQuery->where('transaction_type', 'credit')->sum('amount');

        // Calculate total debit
        $debitQuery = PaymentAccountTransaction::query();
        if (isset($baseFilters['payment_account_id'])) {
            $debitQuery->where('payment_account_id', $baseFilters['payment_account_id']);
        }
        if (isset($baseFilters['date_from'])) {
            $debitQuery->whereDate('transaction_date', '>=', $baseFilters['date_from']);
        }
        if (isset($baseFilters['date_to'])) {
            $debitQuery->whereDate('transaction_date', '<=', $baseFilters['date_to']);
        }
        if (isset($baseFilters['payment_method'])) {
            $debitQuery->where('payment_method', $baseFilters['payment_method']);
        }
        if (isset($baseFilters['reference_type'])) {
            $debitQuery->where('reference_type', $baseFilters['reference_type']);
        }
        $total_debit = $debitQuery->where('transaction_type', 'debit')->sum('amount');

        // Count total transactions
        $countQuery = PaymentAccountTransaction::query();
        if (isset($baseFilters['payment_account_id'])) {
            $countQuery->where('payment_account_id', $baseFilters['payment_account_id']);
        }
        if (isset($baseFilters['date_from'])) {
            $countQuery->whereDate('transaction_date', '>=', $baseFilters['date_from']);
        }
        if (isset($baseFilters['date_to'])) {
            $countQuery->whereDate('transaction_date', '<=', $baseFilters['date_to']);
        }
        if (isset($baseFilters['payment_method'])) {
            $countQuery->where('payment_method', $baseFilters['payment_method']);
        }
        if (isset($baseFilters['reference_type'])) {
            $countQuery->where('reference_type', $baseFilters['reference_type']);
        }
        $transaction_count = $countQuery->count();

        $data['summary'] = [
            'total_credit' => $total_credit,
            'total_debit' => $total_debit,
            'transaction_count' => $transaction_count,
        ];
        $data['summary']['net_flow'] = $total_credit - $total_debit;

        // Get transactions with pagination
        $data['transactions'] = $query->paginate(25);

        // Get current balances of all accounts
        $data['account_balances'] = PaymentAccount::where('status', 1)
            ->select('id', 'title', 'current_balance')
            ->get();
        $data['total_balance'] = PaymentAccount::where('status', 1)->sum('current_balance');

        return view($this->view . '.cashflow', $data);
    }

    /**
     * Account Statement - Detailed statement for a single account
     */
    public function accountStatement(Request $request, $id)
    {
        $data['title'] = 'Account Statement';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['account'] = PaymentAccount::findOrFail($id);

        // Date range filter (default to current month)
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $data['date_from'] = $dateFrom;
        $data['date_to'] = $dateTo;

        // Get opening balance (balance before date_from)
        $openingBalance = PaymentAccountTransaction::where('payment_account_id', $id)
            ->whereDate('transaction_date', '<', $dateFrom)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->value('balance_after') ?? $data['account']->opening_balance;

        $data['opening_balance'] = $openingBalance;

        // Get transactions for the period
        $data['transactions'] = PaymentAccountTransaction::where('payment_account_id', $id)
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(50);

        // Calculate period summary
        $data['period_credit'] = PaymentAccountTransaction::where('payment_account_id', $id)
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->where('transaction_type', 'credit')
            ->sum('amount');

        $data['period_debit'] = PaymentAccountTransaction::where('payment_account_id', $id)
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->where('transaction_type', 'debit')
            ->sum('amount');

        $data['closing_balance'] = $data['account']->current_balance;

        return view($this->view . '.statement', $data);
    }

    /**
     * Summary Report - Overview of all accounts
     */
    public function summary(Request $request)
    {
        $data['title'] = 'Account Summary Report';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        // Date range for activity summary
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $data['date_from'] = $dateFrom;
        $data['date_to'] = $dateTo;

        // Get all accounts with their activity
        $accounts = PaymentAccount::where('status', 1)->get();

        $data['accounts_summary'] = $accounts->map(function ($account) use ($dateFrom, $dateTo) {
            $credit = PaymentAccountTransaction::where('payment_account_id', $account->id)
                ->whereDate('transaction_date', '>=', $dateFrom)
                ->whereDate('transaction_date', '<=', $dateTo)
                ->where('transaction_type', 'credit')
                ->sum('amount');

            $debit = PaymentAccountTransaction::where('payment_account_id', $account->id)
                ->whereDate('transaction_date', '>=', $dateFrom)
                ->whereDate('transaction_date', '<=', $dateTo)
                ->where('transaction_type', 'debit')
                ->sum('amount');

            $transactionCount = PaymentAccountTransaction::where('payment_account_id', $account->id)
                ->whereDate('transaction_date', '>=', $dateFrom)
                ->whereDate('transaction_date', '<=', $dateTo)
                ->count();

            return [
                'account' => $account,
                'credit' => $credit,
                'debit' => $debit,
                'net_flow' => $credit - $debit,
                'transaction_count' => $transactionCount,
                'current_balance' => $account->current_balance,
            ];
        });

        // Grand totals
        $data['grand_total'] = [
            'credit' => $data['accounts_summary']->sum('credit'),
            'debit' => $data['accounts_summary']->sum('debit'),
            'net_flow' => $data['accounts_summary']->sum('net_flow'),
            'transaction_count' => $data['accounts_summary']->sum('transaction_count'),
            'current_balance' => $data['accounts_summary']->sum('current_balance'),
        ];

        return view($this->view . '.summary', $data);
    }

    /**
     * Unlinked Transactions Report - Shows all individual payment transactions not linked to payment accounts
     */
    public function unlinkedTransactions(Request $request)
    {
        $data = [];
        $data['title'] = 'Payment Account Report';
        $data['activeMenu'] = 'payment-account';
        $data['route'] = $this->route;

        // Get all active payment accounts for linking
        $data['payment_accounts'] = PaymentAccount::where('status', 1)
            ->orderBy('title', 'asc')
            ->get();

        // Initialize collection for all transactions
        $allTransactions = collect();

        // Filter parameters
        $type = $request->get('type', 'all'); // all, fee, installment, expense, income, payroll
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $search = $request->get('search');

        $data['selected_type'] = $type;
        $data['date_from'] = $dateFrom;
        $data['date_to'] = $dateTo;
        $data['search'] = $search;

        // Get Regular Fee Payment Receipts (Individual Transactions)
        if ($type == 'all' || $type == 'fee') {
            $receiptQuery = PaymentReceipt::whereNull('payment_account_id')
                ->where('verification_status', 'approved') // Only approved payments
                ->with(['fee.category', 'fee.studentEnroll.student', 'fee.studentEnroll.program', 'student']);

            if ($dateFrom) {
                $receiptQuery->whereDate('payment_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $receiptQuery->whereDate('payment_date', '<=', $dateTo);
            }
            if ($search) {
                $receiptQuery->whereHas('student', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_id', 'like', "%{$search}%");
                });
            }

            $receipts = $receiptQuery->get()->map(function($receipt) {
                $student = $receipt->student ?? null;
                $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : 'Unknown Student';
                $studentId = $student->student_id ?? '';
                $program = $receipt->fee->studentEnroll->program->title ?? '';
                
                return [
                    'id' => $receipt->id,
                    'type' => 'fee_receipt',
                    'date' => $receipt->created_at, // Use created_at to show time
                    'payment_date' => $receipt->payment_date, // Keep payment date for reference
                    'payment_ref_no' => 'FEE' . date('Y', strtotime($receipt->payment_date)) . '/' . str_pad($receipt->id, 4, '0', STR_PAD_LEFT),
                    'invoice_no' => $receipt->fee->category->title ?? 'Fee #' . $receipt->fee_id,
                    'amount' => $receipt->amount,
                    'payment_type' => 'Fee Payment',
                    'account' => $receipt->payment_method ? $this->getPaymentMethodName($receipt->payment_method) : '-',
                    'description' => $studentName . ($studentId ? ' (' . $studentId . ')' : '') . ($program ? ' - ' . $program : ''),
                    'category' => $receipt->fee->category->title ?? '-',
                    'original' => $receipt
                ];
            });

            $allTransactions = $allTransactions->merge($receipts);
        }

        // Get Installment Payments (Individual Transactions)
        if ($type == 'all' || $type == 'installment') {
            $installmentQuery = PaymentPlanPayment::whereNull('payment_account_id')
                ->with([
                    'installment.paymentPlan.fee.category',
                    'installment.paymentPlan.fee.studentEnroll.student',
                    'installment.paymentPlan.fee.studentEnroll.program'
                ]);

            if ($dateFrom) {
                $installmentQuery->whereDate('payment_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $installmentQuery->whereDate('payment_date', '<=', $dateTo);
            }
            if ($search) {
                $installmentQuery->whereHas('installment.paymentPlan.fee.studentEnroll.student', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_id', 'like', "%{$search}%");
                });
            }

            $installmentPayments = $installmentQuery->get()->map(function($payment) {
                $fee = $payment->installment->paymentPlan->fee ?? null;
                $student = $fee->studentEnroll->student ?? null;
                $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : 'Unknown Student';
                $studentId = $student->student_id ?? '';
                $program = $fee->studentEnroll->program->title ?? '';
                $installmentNo = $payment->installment->installment_number ?? '';
                
                return [
                    'id' => $payment->id,
                    'type' => 'installment_payment',
                    'date' => $payment->created_at, // Use created_at to show time
                    'payment_date' => $payment->payment_date, // Keep payment date for reference
                    'payment_ref_no' => 'INS' . date('Y', strtotime($payment->payment_date)) . '/' . str_pad($payment->id, 4, '0', STR_PAD_LEFT),
                    'invoice_no' => ($installmentNo ? 'Installment #' . $installmentNo : 'Installment') . ' - ' . ($fee->category->title ?? 'Fee'),
                    'amount' => $payment->amount,
                    'payment_type' => 'Fee Payment',
                    'account' => $payment->payment_method ? $this->getPaymentMethodName($payment->payment_method) : '-',
                    'description' => $studentName . ($studentId ? ' (' . $studentId . ')' : '') . ($program ? ' - ' . $program : ''),
                    'category' => $fee->category->title ?? '-',
                    'original' => $payment
                ];
            });

            $allTransactions = $allTransactions->merge($installmentPayments);
        }

        // Get Multi-Payments (Multi-Fee Payments)
        if ($type == 'all' || $type == 'multi_payment') {
            $multiPaymentQuery = \App\Models\MultiPayment::whereNull('payment_account_id')
                ->where('status', 'approved') // Only approved multi-payments
                ->with(['student', 'distributions']);

            if ($dateFrom) {
                $multiPaymentQuery->whereDate('payment_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $multiPaymentQuery->whereDate('payment_date', '<=', $dateTo);
            }
            if ($search) {
                $multiPaymentQuery->whereHas('student', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_id', 'like', "%{$search}%");
                });
            }

            $multiPayments = $multiPaymentQuery->get()->map(function($multiPayment) {
                $student = $multiPayment->student ?? null;
                $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : 'Unknown Student';
                $studentId = $student->student_id ?? '';
                $itemsCount = $multiPayment->distributions->where('amount_applied', '>', 0)->count();
                
                return [
                    'id' => $multiPayment->id,
                    'type' => 'multi_payment',
                    'date' => $multiPayment->created_at,
                    'payment_date' => $multiPayment->payment_date,
                    'payment_ref_no' => 'MULTI' . date('Y', strtotime($multiPayment->payment_date)) . '/' . str_pad($multiPayment->id, 4, '0', STR_PAD_LEFT),
                    'invoice_no' => 'Multi-Payment #' . $multiPayment->id,
                    'amount' => $multiPayment->amount_paid,
                    'payment_type' => 'Multi-Fee Payment',
                    'account' => $multiPayment->payment_method ? $this->getPaymentMethodName($multiPayment->payment_method) : '-',
                    'description' => $studentName . ($studentId ? ' (' . $studentId . ')' : '') . ' - ' . $itemsCount . ' items',
                    'category' => 'Multi-Fee Payment',
                    'original' => $multiPayment
                ];
            });

            $allTransactions = $allTransactions->merge($multiPayments);
        }

        // Get Expenses
        if ($type == 'all' || $type == 'expense') {
            $expensesQuery = Expense::whereNull('payment_account_id')
                ->where('status', 1)
                ->with(['category']);

            if ($dateFrom) {
                $expensesQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $expensesQuery->whereDate('date', '<=', $dateTo);
            }
            if ($search) {
                $expensesQuery->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $expenses = $expensesQuery->get()->map(function($expense) {
                return [
                    'id' => $expense->id,
                    'type' => 'expense',
                    'date' => $expense->created_at, // Use created_at for accurate timestamp
                    'expense_date' => $expense->date, // Keep original expense date
                    'payment_ref_no' => 'EXP' . date('Y') . '/' . str_pad($expense->id, 4, '0', STR_PAD_LEFT),
                    'invoice_no' => $expense->category->title ?? 'Expense #' . $expense->id,
                    'amount' => $expense->amount,
                    'payment_type' => 'Expense',
                    'account' => $this->getPaymentMethodName($expense->payment_method), // Use helper method
                    'description' => $expense->title ?? 'Expense',
                    'category' => $expense->category->title ?? '-',
                    'original' => $expense
                ];
            });

            $allTransactions = $allTransactions->merge($expenses);
        }

        // Get Income transactions
        if ($type == 'all' || $type == 'income') {
            $incomesQuery = Income::whereNull('payment_account_id')
                ->where('status', 1)
                ->with(['category']);

            if ($dateFrom) {
                $incomesQuery->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $incomesQuery->whereDate('date', '<=', $dateTo);
            }
            if ($search) {
                $incomesQuery->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $incomes = $incomesQuery->get()->map(function($income) {
                return [
                    'id' => $income->id,
                    'type' => 'income',
                    'date' => $income->created_at, // Use created_at for accurate timestamp
                    'income_date' => $income->date, // Keep original income date
                    'payment_ref_no' => 'INC' . date('Y') . '/' . str_pad($income->id, 4, '0', STR_PAD_LEFT),
                    'invoice_no' => $income->category->title ?? 'Income #' . $income->id,
                    'amount' => $income->amount,
                    'payment_type' => 'Income',
                    'account' => $this->getPaymentMethodName($income->payment_method), // Use helper method
                    'description' => $income->title ?? 'Income',
                    'category' => $income->category->title ?? '-',
                    'original' => $income
                ];
            });

            $allTransactions = $allTransactions->merge($incomes);
        }

        // Get Payroll transactions
        if ($type == 'all' || $type == 'payroll') {
            $payrollsQuery = Payroll::whereNull('payment_account_id')
                ->where('status', 1)
                ->with(['user']);

            if ($dateFrom) {
                $payrollsQuery->whereDate('pay_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $payrollsQuery->whereDate('pay_date', '<=', $dateTo);
            }
            if ($search) {
                $payrollsQuery->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $payrolls = $payrollsQuery->get()->map(function($payroll) {
                $user = $payroll->user ?? null;
                $userName = $user ? $user->name : 'Unknown Staff';
                
                return [
                    'id' => $payroll->id,
                    'type' => 'payroll',
                    'date' => $payroll->pay_date,
                    'payment_ref_no' => 'PAY' . date('Y') . '/' . str_pad($payroll->id, 4, '0', STR_PAD_LEFT),
                    'invoice_no' => 'Payroll #' . $payroll->id,
                    'amount' => $payroll->net_salary,
                    'payment_type' => 'Payroll',
                    'account' => $payroll->payment_method ? ucfirst(str_replace('_', ' ', $payroll->payment_method)) : '-',
                    'description' => 'Salary Payment - ' . $userName . ' - ' . date('F Y', strtotime($payroll->salary_month)),
                    'category' => 'Payroll',
                    'original' => $payroll
                ];
            });

            $allTransactions = $allTransactions->merge($payrolls);
        }

        // Sort by date descending (latest first)
        $allTransactions = $allTransactions->sortByDesc('date')->values(); // values() resets keys to 0,1,2,3...

        // Paginate manually
        $perPage = 25;
        $currentPage = $request->get('page', 1);
        $data['transactions'] = new \Illuminate\Pagination\LengthAwarePaginator(
            $allTransactions->forPage($currentPage, $perPage),
            $allTransactions->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view($this->view . '.unlinked', $data);
    }

    /**
     * Link a transaction to a payment account
     */
    public function linkTransaction(Request $request)
    {
        $request->validate([
            'type' => 'required|in:fee_receipt,installment_payment,expense,income,payroll',
            'id' => 'required|integer',
            'payment_account_id' => 'required|exists:payment_accounts,id'
        ]);

        try {
            DB::beginTransaction();

            $paymentAccount = PaymentAccount::findOrFail($request->payment_account_id);
            $transactionTitle = '';
            $amount = 0;
            $transactionType = 'credit'; // Default
            $referenceType = '';
            $referenceId = $request->id;

            // Update the appropriate model
            switch ($request->type) {
                case 'fee_receipt':
                    $receipt = PaymentReceipt::with('fee.studentEnroll.student')->findOrFail($request->id);
                    $receipt->payment_account_id = $request->payment_account_id;
                    $receipt->save();
                    
                    $amount = $receipt->amount;
                    $transactionType = 'credit'; // Money IN
                    $referenceType = 'payment_receipts';
                    $student = $receipt->fee->studentEnroll->student ?? null;
                    $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : 'Student';
                    $transactionTitle = 'Fee Payment Receipt - ' . $studentName . ' - ' . ($receipt->fee->category->title ?? 'Fee');
                    break;

                case 'installment_payment':
                    $payment = PaymentPlanPayment::with('installment.paymentPlan.fee.studentEnroll.student')->findOrFail($request->id);
                    $payment->payment_account_id = $request->payment_account_id;
                    $payment->save();
                    
                    $amount = $payment->amount;
                    $transactionType = 'credit'; // Money IN
                    $referenceType = 'payment_plan_payments';
                    $fee = $payment->installment->paymentPlan->fee ?? null;
                    $student = $fee->studentEnroll->student ?? null;
                    $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : 'Student';
                    $installmentNo = $payment->installment->installment_number ?? '';
                    $transactionTitle = 'Installment Payment #' . $installmentNo . ' - ' . $studentName . ' - ' . ($fee->category->title ?? 'Fee');
                    break;

                case 'expense':
                    $expense = Expense::findOrFail($request->id);
                    
                    // Check if account has sufficient balance for expenses
                    if ($paymentAccount->current_balance < $expense->amount) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient balance in selected account. Current balance: ' . number_format($paymentAccount->current_balance, 2)
                        ]);
                    }
                    
                    $expense->payment_account_id = $request->payment_account_id;
                    $expense->save();
                    
                    $amount = $expense->amount;
                    $transactionType = 'debit'; // Money OUT
                    $referenceType = 'expenses';
                    $transactionTitle = 'Expense - ' . ($expense->title ?? 'Expense');
                    break;

                case 'income':
                    $income = Income::findOrFail($request->id);
                    $income->payment_account_id = $request->payment_account_id;
                    $income->save();
                    
                    $amount = $income->amount;
                    $transactionType = 'credit'; // Money IN
                    $referenceType = 'incomes';
                    $transactionTitle = 'Income - ' . ($income->title ?? 'Income') . ' - ' . ($income->category->title ?? '');
                    break;

                case 'payroll':
                    $payroll = Payroll::findOrFail($request->id);
                    
                    // Check if account has sufficient balance for payroll
                    if ($paymentAccount->current_balance < $payroll->net_salary) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient balance in selected account. Current balance: ' . number_format($paymentAccount->current_balance, 2)
                        ]);
                    }
                    
                    $payroll->payment_account_id = $request->payment_account_id;
                    $payroll->save();
                    
                    $amount = $payroll->net_salary;
                    $transactionType = 'debit'; // Money OUT
                    $referenceType = 'payrolls';
                    $user = $payroll->user ?? null;
                    $userName = $user ? $user->name : 'Staff';
                    $transactionTitle = 'Payroll - ' . $userName . ' - ' . date('F Y', strtotime($payroll->salary_month));
                    break;
            }

            // Create payment account transaction
            $transaction = new PaymentAccountTransaction();
            $transaction->payment_account_id = $request->payment_account_id;
            $transaction->transaction_type = $transactionType;
            $transaction->transaction_date = now();
            $transaction->amount = $amount;
            $transaction->title = $transactionTitle;
            $transaction->description = 'Linked from ' . ucfirst($request->type) . ' #' . $request->id;
            $transaction->payment_method = 'bank_transfer'; // Default
            $transaction->reference_type = $referenceType;
            $transaction->reference_id = $referenceId;
            $transaction->created_by = auth()->user()->id;
            $transaction->save();

            // Update account balance
            if ($transactionType == 'credit') {
                $paymentAccount->current_balance += $amount;
            } else {
                $paymentAccount->current_balance -= $amount;
            }
            $paymentAccount->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction linked successfully to ' . $paymentAccount->title
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error linking transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get payment method name
     */
    private function getPaymentMethodName($method)
    {
        $methods = [
            1 => 'Cash',
            2 => 'Bank Transfer',
            3 => 'MTN Mobile Money',
            4 => 'Orange Money',
            5 => 'Other'
        ];
        
        return $methods[$method] ?? 'Other';
    }
}

