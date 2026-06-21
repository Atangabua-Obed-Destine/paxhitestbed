<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransfer;
use App\Models\PaymentAccountTransaction;
use Toastr;
use DB;

class PaymentAccountTransferController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:payment-account-transfer-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:payment-account-transfer-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:payment-account-transfer-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:payment-account-transfer-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of transfers.
     */
    public function index()
    {
        $data['transfers'] = PaymentAccountTransfer::with(['fromAccount', 'toAccount', 'creator'])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        
        return view('admin.payment-account.transfers.index', $data);
    }

    /**
     * Show the form for creating a new transfer.
     */
    public function create()
    {
        $data['payment_accounts'] = PaymentAccount::where('status', 1)->get();
        $data['from_account_id'] = request()->query('from'); // Pre-fill if coming from account list
        $data['to_account_id'] = request()->query('to'); // Pre-fill if needed
        
        return view('admin.payment-account.transfers.create', $data);
    }

    /**
     * Store a newly created transfer.
     */
    public function store(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|exists:payment_accounts,id',
            'to_account_id' => 'required|exists:payment_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'note' => 'nullable|string',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $from_account = PaymentAccount::findOrFail($request->from_account_id);
            $to_account = PaymentAccount::findOrFail($request->to_account_id);
            $amount = $request->amount;

            // Check sufficient balance in from account
            if ($from_account->current_balance < $amount) {
                Toastr::error(__('insufficient_balance_in_from_account'), __('msg_error'));
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

            // Create transfer record
            $transfer = PaymentAccountTransfer::create([
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $amount,
                'transfer_date' => $request->transfer_date,
                'note' => $request->note,
                'attach' => $attach,
                'created_by' => auth()->user()->id,
            ]);

            // Update from account (debit)
            $from_new_balance = $from_account->current_balance - $amount;
            $from_account->update(['current_balance' => $from_new_balance]);

            // Create debit transaction for from account
            PaymentAccountTransaction::create([
                'payment_account_id' => $from_account->id,
                'transaction_type' => 'debit',
                'amount' => $amount,
                'transaction_date' => $request->transfer_date,
                'title' => 'Transfer to ' . $to_account->title,
                'description' => $request->note,
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
                'balance_after' => $from_new_balance,
                'attach' => $attach,
                'created_by' => auth()->user()->id,
            ]);

            // Update to account (credit)
            $to_new_balance = $to_account->current_balance + $amount;
            $to_account->update(['current_balance' => $to_new_balance]);

            // Create credit transaction for to account
            PaymentAccountTransaction::create([
                'payment_account_id' => $to_account->id,
                'transaction_type' => 'credit',
                'amount' => $amount,
                'transaction_date' => $request->transfer_date,
                'title' => 'Transfer from ' . $from_account->title,
                'description' => $request->note,
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
                'balance_after' => $to_new_balance,
                'attach' => $attach,
                'created_by' => auth()->user()->id,
            ]);

            DB::commit();

            Toastr::success(__('transfer_successful'), __('msg_success'));
            return redirect()->route('admin.payment-account-transfer.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('transfer_failed') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified transfer.
     */
    public function show($id)
    {
        $data['transfer'] = PaymentAccountTransfer::with(['fromAccount', 'toAccount', 'creator'])
            ->findOrFail($id);
        
        return view('admin.payment-account.transfers.show', $data);
    }

    /**
     * Show the form for editing the specified transfer.
     */
    public function edit($id)
    {
        $data['transfer'] = PaymentAccountTransfer::with(['fromAccount', 'toAccount'])->findOrFail($id);
        $data['payment_accounts'] = PaymentAccount::where('status', 1)->get();
        
        return view('admin.payment-account.transfers.edit', $data);
    }

    /**
     * Update the specified transfer.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'from_account_id' => 'required|exists:payment_accounts,id',
            'to_account_id' => 'required|exists:payment_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'note' => 'nullable|string',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $transfer = PaymentAccountTransfer::with(['fromAccount', 'toAccount'])->findOrFail($id);
            
            // Get old values
            $old_from_account = $transfer->fromAccount;
            $old_to_account = $transfer->toAccount;
            $old_amount = $transfer->amount;

            // Get new values
            $new_from_account = PaymentAccount::findOrFail($request->from_account_id);
            $new_to_account = PaymentAccount::findOrFail($request->to_account_id);
            $new_amount = $request->amount;

            // Reverse old transfer effects
            $old_from_account->update([
                'current_balance' => $old_from_account->current_balance + $old_amount
            ]);
            $old_to_account->update([
                'current_balance' => $old_to_account->current_balance - $old_amount
            ]);

            // Check sufficient balance in new from account
            if ($new_from_account->current_balance < $new_amount) {
                DB::rollBack();
                Toastr::error(__('insufficient_balance_in_from_account'), __('msg_error'));
                return redirect()->back()->withInput();
            }

            // Handle file upload
            if ($request->hasFile('attach')) {
                // Delete old file if exists
                if ($transfer->attach && file_exists(public_path($transfer->attach))) {
                    unlink(public_path($transfer->attach));
                }
                
                $file = $request->file('attach');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/payment-account/'), $filename);
                $attach = 'uploads/payment-account/' . $filename;
            } else {
                $attach = $transfer->attach;
            }

            // Update transfer record
            $transfer->update([
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $new_amount,
                'transfer_date' => $request->transfer_date,
                'note' => $request->note,
                'attach' => $attach,
            ]);

            // Apply new transfer effects
            $from_new_balance = $new_from_account->current_balance - $new_amount;
            $new_from_account->update(['current_balance' => $from_new_balance]);

            $to_new_balance = $new_to_account->current_balance + $new_amount;
            $new_to_account->update(['current_balance' => $to_new_balance]);

            // Update related transactions
            $transactions = PaymentAccountTransaction::where('reference_type', 'transfer')
                ->where('reference_id', $transfer->id)
                ->get();

            foreach ($transactions as $transaction) {
                if ($transaction->payment_account_id == $old_from_account->id) {
                    // Update debit transaction
                    $transaction->update([
                        'payment_account_id' => $new_from_account->id,
                        'amount' => $new_amount,
                        'transaction_date' => $request->transfer_date,
                        'title' => 'Transfer to ' . $new_to_account->title,
                        'description' => $request->note,
                        'balance_after' => $from_new_balance,
                        'attach' => $attach,
                    ]);
                } else {
                    // Update credit transaction
                    $transaction->update([
                        'payment_account_id' => $new_to_account->id,
                        'amount' => $new_amount,
                        'transaction_date' => $request->transfer_date,
                        'title' => 'Transfer from ' . $new_from_account->title,
                        'description' => $request->note,
                        'balance_after' => $to_new_balance,
                        'attach' => $attach,
                    ]);
                }
            }

            DB::commit();

            Toastr::success(__('transfer_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.payment-account-transfer.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('transfer_update_failed') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified transfer.
     * Note: This is a sensitive operation that reverses a transfer
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $transfer = PaymentAccountTransfer::with(['fromAccount', 'toAccount'])->findOrFail($id);

            // Find related transactions
            $transactions = PaymentAccountTransaction::where('reference_type', 'transfer')
                ->where('reference_id', $transfer->id)
                ->get();

            if ($transactions->count() != 2) {
                Toastr::error(__('transfer_data_inconsistency'), __('msg_error'));
                return redirect()->back();
            }

            // Reverse the transfer amounts
            $amount = $transfer->amount;

            // Restore from account balance (add back)
            $from_account = $transfer->fromAccount;
            $from_account->update([
                'current_balance' => $from_account->current_balance + $amount
            ]);

            // Restore to account balance (subtract back)
            $to_account = $transfer->toAccount;
            $to_account->update([
                'current_balance' => $to_account->current_balance - $amount
            ]);

            // Delete transactions
            foreach ($transactions as $transaction) {
                $transaction->delete();
            }

            // Delete transfer
            $transfer->delete();

            DB::commit();

            Toastr::success(__('transfer_deleted_successfully'), __('msg_success'));
            return redirect()->route('admin.payment-account-transfer.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('msg_delete_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back();
        }
    }
}
