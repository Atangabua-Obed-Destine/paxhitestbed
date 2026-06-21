<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountType;
use App\Models\PaymentAccountTransaction;
use App\Models\Department;
use App\Models\PaymentReceipt;
use App\Models\PaymentPlanPayment;
use App\Models\Expense;
use App\Models\Income;
use Toastr;
use DB;

class PaymentAccountController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:payment-account-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:payment-account-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:payment-account-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:payment-account-delete', ['only' => ['destroy']]);
        $this->middleware('permission:payment-account-book', ['only' => ['accountBook', 'getTransactions']]);
        $this->middleware('permission:payment-account-deposit', ['only' => ['deposit']]);
        $this->middleware('permission:payment-account-withdraw', ['only' => ['withdraw']]);
        $this->middleware('permission:payment-account-transaction-edit', ['only' => ['editTransaction', 'updateTransaction']]);
        $this->middleware('permission:payment-account-transaction-delete', ['only' => ['deleteTransaction']]);
    }

    /**
     * Display a listing of the payment accounts.
     */
    public function index()
    {
        $data['payment_accounts'] = PaymentAccount::with('accountType', 'creator', 'updater')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Count unlinked transactions
        $unlinkedCount = 0;
        
        // Count unlinked fee receipts (approved)
        $unlinkedCount += PaymentReceipt::whereNull('payment_account_id')
            ->where('verification_status', 'approved')
            ->count();
        
        // Count unlinked installment payments
        $unlinkedCount += PaymentPlanPayment::whereNull('payment_account_id')->count();
        
        // Count unlinked multi-payments (approved)
        $unlinkedCount += \App\Models\MultiPayment::whereNull('payment_account_id')
            ->where('status', 'approved')
            ->count();
        
        // Count unlinked expenses
        $unlinkedCount += Expense::whereNull('payment_account_id')
            ->where('status', 1)
            ->count();
        
        // Count unlinked incomes
        $unlinkedCount += Income::whereNull('payment_account_id')
            ->where('status', 1)
            ->count();
        
        $data['unlinked_count'] = $unlinkedCount;
        
        return view('admin.payment-account.index', $data);
    }

    /**
     * Show the form for creating a new payment account.
     */
    public function create()
    {
        $data['account_types'] = PaymentAccountType::where('status', 1)->get();
        
        return view('admin.payment-account.create', $data);
    }

    /**
     * Store a newly created payment account.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'account_type_id' => 'required|exists:payment_account_types,id',
            'account_number' => 'nullable|string|max:255',
            'opening_balance' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $opening_balance = $request->opening_balance;
            
            // Create payment account
            $payment_account = PaymentAccount::create([
                'title' => $request->title,
                'account_number' => $request->account_number,
                'account_type_id' => $request->account_type_id,
                'opening_balance' => $opening_balance,
                'current_balance' => $opening_balance,
                'description' => $request->description,
                'status' => $request->status ?? 1,
                'created_by' => auth()->user()->id,
            ]);

            // If opening balance > 0, create initial transaction
            if ($opening_balance > 0) {
                PaymentAccountTransaction::create([
                    'payment_account_id' => $payment_account->id,
                    'transaction_type' => 'credit',
                    'amount' => $opening_balance,
                    'transaction_date' => now(),
                    'title' => 'Opening Balance',
                    'description' => 'Initial opening balance for account',
                    'balance_after' => $opening_balance,
                    'created_by' => auth()->user()->id,
                ]);
            }

            DB::commit();

            Toastr::success(__('payment_account_created_successfully'), __('msg_success'));
            return redirect()->route('admin.payment-account.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('msg_created_error'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified payment account.
     */
    public function show($id)
    {
        $data['payment_account'] = PaymentAccount::with(['accountType', 'creator', 'updater'])
            ->findOrFail($id);
        
        return view('admin.payment-account.show', $data);
    }

    /**
     * Show the form for editing the specified payment account.
     */
    public function edit($id)
    {
        $data['payment_account'] = PaymentAccount::findOrFail($id);
        $data['account_types'] = PaymentAccountType::where('status', 1)->get();
        
        return view('admin.payment-account.edit', $data);
    }

    /**
     * Update the specified payment account.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'account_type_id' => 'required|exists:payment_account_types,id',
            'account_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $payment_account = PaymentAccount::findOrFail($id);

            $payment_account->update([
                'title' => $request->title,
                'account_number' => $request->account_number,
                'account_type_id' => $request->account_type_id,
                'description' => $request->description,
                'status' => $request->status ?? 1,
                'updated_by' => auth()->user()->id,
            ]);

            Toastr::success(__('payment_account_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.payment-account.index');

        } catch (\Exception $e) {
            Toastr::error(__('msg_update_error'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified payment account.
     */
    public function destroy($id)
    {
        try {
            $payment_account = PaymentAccount::findOrFail($id);

            // Check if account has transactions
            if ($payment_account->transactions()->count() > 0) {
                Toastr::error(__('cannot_delete_account_with_transactions'), __('msg_error'));
                return redirect()->back();
            }

            $payment_account->delete();

            Toastr::success(__('payment_account_deleted_successfully'), __('msg_success'));
            return redirect()->route('admin.payment-account.index');

        } catch (\Exception $e) {
            Toastr::error(__('msg_delete_error'), __('msg_error'));
            return redirect()->back();
        }
    }

    /**
     * Display account book (ledger) for a payment account.
     */
    public function accountBook($id)
    {
        $data['payment_account'] = PaymentAccount::with('accountType')->findOrFail($id);
        
        // Get transactions with filters
        $query = PaymentAccountTransaction::where('payment_account_id', $id)
            ->with('creator');

        // Apply date filters if provided
        if (request()->has('date_from') && request()->date_from != '') {
            $query->where('transaction_date', '>=', request()->date_from);
        }
        if (request()->has('date_to') && request()->date_to != '') {
            $query->where('transaction_date', '<=', request()->date_to);
        }

        // Apply transaction type filter
        if (request()->has('transaction_type') && request()->transaction_type != '') {
            $query->where('transaction_type', request()->transaction_type);
        }

        $data['transactions'] = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.payment-account.account-book', $data);
    }

    /**
     * Show deposit form and process deposit
     */
    public function deposit(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'transaction_date' => 'required|date',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'payment_method' => 'nullable|string|max:255',
                'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            DB::beginTransaction();
            try {
                $payment_account = PaymentAccount::findOrFail($id);
                $amount = $request->amount;

                // Handle file upload
                $attach = null;
                if ($request->hasFile('attach')) {
                    $file = $request->file('attach');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('uploads/payment-account/'), $filename);
                    $attach = 'uploads/payment-account/' . $filename;
                }

                // Update account balance
                $new_balance = $payment_account->current_balance + $amount;
                $payment_account->update(['current_balance' => $new_balance]);

                // Create transaction
                PaymentAccountTransaction::create([
                    'payment_account_id' => $payment_account->id,
                    'transaction_type' => 'credit',
                    'amount' => $amount,
                    'transaction_date' => $request->transaction_date,
                    'title' => $request->title,
                    'description' => $request->description,
                    'payment_method' => $request->payment_method,
                    'balance_after' => $new_balance,
                    'attach' => $attach,
                    'created_by' => auth()->user()->id,
                ]);

                DB::commit();

                Toastr::success(__('deposit_successful'), __('msg_success'));
                return redirect()->route('admin.payment-account.account-book', $id);

            } catch (\Exception $e) {
                DB::rollBack();
                Toastr::error(__('deposit_failed'), __('msg_error'));
                return redirect()->back()->withInput();
            }
        }

        // Show deposit form
        $data['payment_account'] = PaymentAccount::findOrFail($id);
        return view('admin.payment-account.deposit', $data);
    }

    /**
     * Show withdrawal form and process withdrawal
     */
    public function withdraw(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'transaction_date' => 'required|date',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'payment_method' => 'nullable|string|max:255',
                'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            DB::beginTransaction();
            try {
                $payment_account = PaymentAccount::findOrFail($id);
                $amount = $request->amount;

                // Check sufficient balance
                if ($payment_account->current_balance < $amount) {
                    Toastr::error(__('insufficient_balance'), __('msg_error'));
                    return redirect()->back()->withInput();
                }

                // Handle file upload
                $attach = null;
                if ($request->hasFile('attach')) {
                    $file = $request->file('attach');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('uploads/payment-account/'), $filename);
                    $attach = 'uploads/payment-account/' . $filename;
                }

                // Update account balance
                $new_balance = $payment_account->current_balance - $amount;
                $payment_account->update(['current_balance' => $new_balance]);

                // Create transaction
                PaymentAccountTransaction::create([
                    'payment_account_id' => $payment_account->id,
                    'transaction_type' => 'debit',
                    'amount' => $amount,
                    'transaction_date' => $request->transaction_date,
                    'title' => $request->title,
                    'description' => $request->description,
                    'payment_method' => $request->payment_method,
                    'balance_after' => $new_balance,
                    'attach' => $attach,
                    'created_by' => auth()->user()->id,
                ]);

                DB::commit();

                Toastr::success(__('withdrawal_successful'), __('msg_success'));
                return redirect()->route('admin.payment-account.account-book', $id);

            } catch (\Exception $e) {
                DB::rollBack();
                Toastr::error(__('withdrawal_failed'), __('msg_error'));
                return redirect()->back()->withInput();
            }
        }

        // Show withdrawal form
        $data['payment_account'] = PaymentAccount::findOrFail($id);
        return view('admin.payment-account.withdraw', $data);
    }

    /**
     * Get transactions data for AJAX (for DataTables or similar)
     */
    public function getTransactions($id)
    {
        $transactions = PaymentAccountTransaction::where('payment_account_id', $id)
            ->with('creator')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    /**
     * Show the form for editing a transaction
     */
    public function editTransaction($id)
    {
        $data['transaction'] = PaymentAccountTransaction::with('paymentAccount')->findOrFail($id);
        
        // Prevent editing of linked transactions (fees, expenses, etc.)
        if (in_array($data['transaction']->reference_type, ['fees', 'expense', 'income', 'payroll'])) {
            Toastr::error(__('cannot_edit_linked_transaction'), __('msg_error'));
            return redirect()->back();
        }

        return view('admin.payment-account.transaction-edit', $data);
    }

    /**
     * Update a transaction
     */
    public function updateTransaction(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'payment_method' => 'nullable|string|max:255',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $transaction = PaymentAccountTransaction::findOrFail($id);
            
            // Prevent editing of linked transactions
            if (in_array($transaction->reference_type, ['fees', 'expense', 'income', 'payroll', 'transfer'])) {
                Toastr::error(__('cannot_edit_linked_transaction'), __('msg_error'));
                return redirect()->back();
            }

            $payment_account = $transaction->paymentAccount;
            $old_amount = $transaction->amount;
            $new_amount = $request->amount;
            $transaction_type = $transaction->transaction_type;

            // Calculate balance adjustment
            if ($transaction_type == 'credit') {
                // For credit: subtract old amount, add new amount
                $balance_adjustment = $new_amount - $old_amount;
            } else {
                // For debit: subtract old amount (negative), add new amount (negative)
                $balance_adjustment = $old_amount - $new_amount;
            }

            // Update account balance
            $new_balance = $payment_account->current_balance + $balance_adjustment;
            $payment_account->update(['current_balance' => $new_balance]);

            // Handle file upload
            if ($request->hasFile('attach')) {
                // Delete old file if exists
                if ($transaction->attach && file_exists(public_path($transaction->attach))) {
                    unlink(public_path($transaction->attach));
                }
                
                $file = $request->file('attach');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/payment-account/'), $filename);
                $attach = 'uploads/payment-account/' . $filename;
            } else {
                $attach = $transaction->attach;
            }

            // Update transaction
            $transaction->update([
                'amount' => $new_amount,
                'transaction_date' => $request->transaction_date,
                'title' => $request->title,
                'description' => $request->description,
                'payment_method' => $request->payment_method,
                'balance_after' => $new_balance,
                'attach' => $attach,
            ]);

            DB::commit();

            Toastr::success(__('transaction_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.payment-account.account-book', $payment_account->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('transaction_update_failed') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Delete a transaction
     */
    public function deleteTransaction($id)
    {
        DB::beginTransaction();
        try {
            $transaction = PaymentAccountTransaction::findOrFail($id);
            
            // Prevent deletion of linked transactions
            if (in_array($transaction->reference_type, ['fees', 'expense', 'income', 'payroll'])) {
                Toastr::error(__('cannot_delete_linked_transaction'), __('msg_error'));
                return redirect()->back();
            }

            // For transfer transactions, redirect to transfer delete
            if ($transaction->reference_type == 'transfer') {
                Toastr::error(__('delete_transfer_from_transfer_page'), __('msg_error'));
                return redirect()->route('admin.payment-account-transfer.index');
            }

            $payment_account = $transaction->paymentAccount;
            
            // Reverse the transaction effect on balance
            if ($transaction->transaction_type == 'credit') {
                // Was a credit, so subtract the amount
                $new_balance = $payment_account->current_balance - $transaction->amount;
            } else {
                // Was a debit, so add back the amount
                $new_balance = $payment_account->current_balance + $transaction->amount;
            }

            // Update account balance
            $payment_account->update(['current_balance' => $new_balance]);

            // Delete attachment file if exists
            if ($transaction->attach && file_exists(public_path($transaction->attach))) {
                unlink(public_path($transaction->attach));
            }

            // Delete transaction
            $transaction->delete();

            DB::commit();

            Toastr::success(__('transaction_deleted_successfully'), __('msg_success'));
            return redirect()->route('admin.payment-account.account-book', $payment_account->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('transaction_delete_failed') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back();
        }
    }
}
