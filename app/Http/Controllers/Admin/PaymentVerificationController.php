<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Flasher\Laravel\Facade\Flasher;
use App\Models\PaymentReceipt;
use App\Models\InstallmentPaymentReceipt;
use Illuminate\Http\Request;
use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\MultiPayment;

class PaymentVerificationController extends Controller
{
    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->title = trans_choice('module_payment_verification', 1);
        $this->route = 'admin.payment-verification';
        $this->view = 'admin.payment-verification';
        $this->path = 'payment-receipts';
        $this->access = 'payment-receipt';

        $this->middleware('permission:' . $this->access . '-verify', ['only' => ['index', 'show', 'approve', 'reject']]);
    }

    /**
     * Display a listing of payment receipts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get payment type filter (fee or installment)
        $paymentType = $request->input('payment_type', 'all');
        $data['selected_payment_type'] = $paymentType;

        // Build queries for both types
        $feeReceipts = collect();
        $installmentReceipts = collect();
        $multiPayments = collect();

        // Get Fee Payment Receipts
        if ($paymentType === 'all' || $paymentType === 'fee') {
            $feeQuery = PaymentReceipt::with([
                'student',
                'fee.category',
                'fee.studentEnroll.session',
                'fee.studentEnroll.semester',
                'verifier'
            ])->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $feeQuery->where('verification_status', $request->status);
            } else {
                $data['selected_status'] = 'pending';
                $feeQuery->where('verification_status', 'pending');
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $feeQuery->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $feeQuery->whereDate('created_at', '<=', $request->date_to);
            }

            // Search by student name or reference
            if ($request->filled('search')) {
                $search = $request->search;
                $feeQuery->where(function ($q) use ($search) {
                    $q->where('payment_reference', 'like', '%' . $search . '%')
                        ->orWhereHas('student', function ($sq) use ($search) {
                            $sq->where('first_name', 'like', '%' . $search . '%')
                                ->orWhere('last_name', 'like', '%' . $search . '%');
                        });
                });
            }

            $feeReceipts = $feeQuery->get()->map(function($receipt) {
                $receipt->payment_type = 'fee';
                return $receipt;
            });
        }

        // Get Installment Payment Receipts
        if ($paymentType === 'all' || $paymentType === 'installment') {
            $installmentQuery = InstallmentPaymentReceipt::with([
                'student',
                'installment.paymentPlan.fee.category',
                'installment.paymentPlan.fee.studentEnroll.session',
                'installment.paymentPlan.fee.studentEnroll.semester',
                'verifier'
            ])->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $installmentQuery->where('status', $request->status);
            } else {
                $installmentQuery->where('status', 'pending');
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $installmentQuery->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $installmentQuery->whereDate('created_at', '<=', $request->date_to);
            }

            // Search by student name
            if ($request->filled('search')) {
                $search = $request->search;
                $installmentQuery->whereHas('student', function ($sq) use ($search) {
                    $sq->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            }

            $installmentReceipts = $installmentQuery->get()->map(function($receipt) {
                $receipt->payment_type = 'installment';
                return $receipt;
            });
        }

        // Get Multi-Payment Receipts
        if ($paymentType === 'all' || $paymentType === 'multi') {
            $multiQuery = MultiPayment::with([
                'student',
                'distributions.fee.category',
                'distributions.fee.studentEnroll.session',
                'distributions.fee.studentEnroll.semester',
                'distributions.installment',
                'verifiedBy'
            ])->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $multiQuery->where('status', $request->status);
            } else {
                $multiQuery->where('status', 'pending');
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $multiQuery->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $multiQuery->whereDate('created_at', '<=', $request->date_to);
            }

            // Search by student name or transaction ID
            if ($request->filled('search')) {
                $search = $request->search;
                $multiQuery->where(function ($q) use ($search) {
                    $q->where('transaction_id', 'like', '%' . $search . '%')
                        ->orWhereHas('student', function ($sq) use ($search) {
                            $sq->where('first_name', 'like', '%' . $search . '%')
                                ->orWhere('last_name', 'like', '%' . $search . '%');
                        });
                });
            }

            $multiPayments = $multiQuery->get()->map(function($receipt) {
                $receipt->payment_type = 'multi';
                return $receipt;
            });
        }

        // Merge and sort by created_at
        $allReceipts = $feeReceipts->merge($installmentReceipts)->merge($multiPayments)
            ->sortByDesc('created_at')
            ->values();

        // Manually paginate
        $perPage = 25;
        $currentPage = $request->input('page', 1);
        $data['rows'] = new \Illuminate\Pagination\LengthAwarePaginator(
            $allReceipts->forPage($currentPage, $perPage),
            $allReceipts->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Get statistics
        $data['stats'] = [
            'pending_fee' => PaymentReceipt::where('verification_status', 'pending')->count(),
            'pending_installment' => InstallmentPaymentReceipt::where('status', 'pending')->count(),
            'pending_multi' => MultiPayment::where('status', 'pending')->count(),
            'approved_fee' => PaymentReceipt::where('verification_status', 'approved')->count(),
            'approved_installment' => InstallmentPaymentReceipt::where('status', 'approved')->count(),
            'approved_multi' => MultiPayment::where('status', 'approved')->count(),
            'rejected_fee' => PaymentReceipt::where('verification_status', 'rejected')->count(),
            'rejected_installment' => InstallmentPaymentReceipt::where('status', 'rejected')->count(),
            'rejected_multi' => MultiPayment::where('status', 'rejected')->count(),
        ];

        $data['stats']['pending'] = $data['stats']['pending_fee'] + $data['stats']['pending_installment'] + $data['stats']['pending_multi'];
        $data['stats']['approved'] = $data['stats']['approved_fee'] + $data['stats']['approved_installment'] + $data['stats']['approved_multi'];
        $data['stats']['rejected'] = $data['stats']['rejected_fee'] + $data['stats']['rejected_installment'] + $data['stats']['rejected_multi'];
        $data['stats']['total'] = $data['stats']['pending'] + $data['stats']['approved'] + $data['stats']['rejected'];

        $data['selected_status'] = $request->status ?? 'pending';

        return view($this->view . '.index', $data);
    }

    /**
     * Display the specified payment receipt.
     *
     * @param  string  $type
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($type, $id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        if ($type === 'fee') {
            $data['row'] = PaymentReceipt::with([
                'student',
                'fee.category',
                'fee.studentEnroll.session',
                'fee.studentEnroll.semester',
                'fee.studentEnroll.program',
                'fee.studentEnroll.section',
                'verifier'
            ])->findOrFail($id);
            $data['payment_type'] = 'fee';
            $data['path'] = 'payment-receipts';
        } elseif ($type === 'installment') {
            $data['row'] = InstallmentPaymentReceipt::with([
                'student',
                'installment.paymentPlan.fee.category',
                'installment.paymentPlan.fee.studentEnroll.session',
                'installment.paymentPlan.fee.studentEnroll.semester',
                'installment.paymentPlan.fee.studentEnroll.program',
                'installment.paymentPlan',
                'verifier'
            ])->findOrFail($id);
            $data['payment_type'] = 'installment';
            $data['path'] = 'installment-receipts';
        } else {
            $data['row'] = MultiPayment::with([
                'student',
                'distributions.fee.category',
                'distributions.fee.studentEnroll.session',
                'distributions.fee.studentEnroll.semester',
                'distributions.fee.studentEnroll.program',
                'distributions.installment',
                'verifiedBy'
            ])->findOrFail($id);
            $data['payment_type'] = 'multi';
            $data['path'] = 'multi-payments';
            
            // Use dedicated multi-payment view
            return view($this->view . '.show-multi', $data);
        }

        return view($this->view . '.show', $data);
    }

    /**
     * Approve a payment receipt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $type
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request, $type, $id)
    {
        $request->validate([
            'verification_note' => 'nullable|string|max:1000',
        ]);

        if ($type === 'fee') {
            return $this->approveFeeReceipt($request, $id);
        } elseif ($type === 'installment') {
            return $this->approveInstallmentReceipt($request, $id);
        } else {
            return $this->approveMultiPayment($request, $id);
        }
    }

    /**
     * Approve a fee payment receipt.
     */
    private function approveFeeReceipt(Request $request, $id)
    {
        $request->validate([
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);

        $receipt = PaymentReceipt::with('fee')->findOrFail($id);

        // Check if already processed
        if ($receipt->verification_status !== 'pending') {
            Flasher::addWarning(__('msg_payment_receipt_already_processed'));
            return redirect()->back();
        }

        try {
            DB::beginTransaction();
            
            // Check if this is a resit fee linked to a rejected/cancelled/declined request
            $fee = $receipt->fee;
            if ($fee->resitRequest) {
                $resitRequest = $fee->resitRequest;
                $invalidStates = ['rejected', 'cancelled', 'declined'];
                
                if (in_array($resitRequest->workflow_state, $invalidStates)) {
                    DB::rollBack();
                    Flasher::addError(
                        __('Cannot verify payment for a resit fee linked to a :state request. The student must create a new resit request.', 
                        ['state' => ucfirst($resitRequest->workflow_state)])
                    );
                    return redirect()->back();
                }
            }

            // Update receipt status
            $receipt->update([
                'verification_status' => 'approved',
                'verification_note' => $request->verification_note,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'payment_account_id' => $request->payment_account_id, // Link receipt to account
            ]);

            // Update the fee with cumulative payment
            
            // Calculate new paid amount (add to existing)
            $newPaidAmount = $fee->paid_amount + $receipt->amount;
            
            // Calculate total amount due (fee + fine - discount)
            $totalDue = $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
            
            // Determine payment status
            // 0 = Unpaid, 1 = Fully Paid, 2 = Partially Paid, 3 = Cancelled
            if ($newPaidAmount >= $totalDue) {
                $status = 1; // Fully Paid
            } elseif ($newPaidAmount > 0) {
                $status = 2; // Partially Paid
            } else {
                $status = 0; // Unpaid
            }
            
            // Get all approved receipts for note
            $approvedCount = $fee->paymentReceipts()->where('verification_status', 'approved')->count();
            $receiptIds = $fee->paymentReceipts()->where('verification_status', 'approved')->pluck('id')->toArray();
            
            $fee->update([
                'status' => $status,
                'paid_amount' => $newPaidAmount,
                'pay_date' => $receipt->payment_date,
                'payment_method' => $receipt->payment_method,
                'payment_account_id' => $request->payment_account_id,
                'note' => 'Payment received via manual receipt(s): #' . implode(', #', $receiptIds) . ' (Total: ' . $newPaidAmount . '/' . $totalDue . ')',
                'updated_by' => Auth::id(),
            ]);

            // Create Payment Account Transaction if account is selected
            if ($request->payment_account_id) {
                $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
                
                // Calculate new balance
                $new_balance = $payment_account->current_balance + $receipt->amount;
                
                // Create transaction record
                $account_transaction = new PaymentAccountTransaction;
                $account_transaction->payment_account_id = $request->payment_account_id;
                $account_transaction->transaction_type = 'credit'; // Money coming IN
                $account_transaction->amount = $receipt->amount;
                $account_transaction->transaction_date = $receipt->payment_date;
                $account_transaction->title = 'Fee Payment (Verified) - ' . ($fee->studentEnroll->student->first_name ?? '') . ' ' . ($fee->studentEnroll->student->last_name ?? '');
                $account_transaction->description = 'Verified fee payment via receipt #' . $receipt->id . ' - ' . ($fee->category->title ?? 'Fee');
                $account_transaction->payment_method = $receipt->payment_method;
                $account_transaction->reference_type = 'fees';
                $account_transaction->reference_id = $fee->id;
                $account_transaction->balance_after = $new_balance;
                $account_transaction->created_by = Auth::id();
                $account_transaction->save();
                
                // Update account balance
                $payment_account->current_balance = $new_balance;
                $payment_account->save();
            }

            DB::commit();

            if ($status == 1) {
                Flasher::addSuccess(__('msg_payment_receipt_approved_fully_paid'));
            } else {
                Flasher::addSuccess(__('msg_payment_receipt_approved_partially_paid'));
            }

            return redirect()->route($this->route . '.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_payment_verification_error') . ': ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Reject a payment receipt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $type
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject(Request $request, $type, $id)
    {
        $request->validate([
            'verification_note' => 'required|string|max:1000',
        ]);

        if ($type === 'fee') {
            return $this->rejectFeeReceipt($request, $id);
        } elseif ($type === 'installment') {
            return $this->rejectInstallmentReceipt($request, $id);
        } else {
            return $this->rejectMultiPayment($request, $id);
        }
    }

    /**
     * Reject a fee payment receipt.
     */
    private function rejectFeeReceipt(Request $request, $id)
    {
        $receipt = PaymentReceipt::findOrFail($id);

        // Check if already processed
        if ($receipt->verification_status !== 'pending') {
            Flasher::addWarning(__('msg_payment_receipt_already_processed'));
            return redirect()->back();
        }

        // Update receipt status
        $receipt->update([
            'verification_status' => 'rejected',
            'verification_note' => $request->verification_note,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        Flasher::addInfo(__('msg_payment_receipt_rejected_admin'));

        return redirect()->route($this->route . '.index');
    }

    /**
     * Approve an installment payment receipt.
     */
    private function approveInstallmentReceipt(Request $request, $id)
    {
        $request->validate([
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);

        $receipt = InstallmentPaymentReceipt::with('installment.paymentPlan.fee.studentEnroll.student')->findOrFail($id);

        // Check if already processed
        if ($receipt->status !== 'pending') {
            Flasher::addWarning('This receipt has already been processed.');
            return redirect()->back();
        }

        try {
            \DB::beginTransaction();

            // Record the payment using the installment's recordPayment method with payment account
            $receipt->installment->recordPayment($receipt->amount, [
                'payment_method' => $receipt->payment_method,
                'payment_date' => $receipt->payment_date,
                'note' => $receipt->note,
                'payment_account_id' => $request->payment_account_id,
            ]);

            // If payment account is selected, create payment account transaction
            if ($request->payment_account_id) {
                $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
                
                // Calculate new balance
                $new_balance = $payment_account->current_balance + $receipt->amount;
                
                // Get student info
                $fee = $receipt->installment->paymentPlan->fee;
                $student = $fee->studentEnroll->student ?? null;
                $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : 'Student';
                
                // Create transaction record
                $account_transaction = new PaymentAccountTransaction;
                $account_transaction->payment_account_id = $request->payment_account_id;
                $account_transaction->transaction_type = 'credit'; // Money coming IN
                $account_transaction->amount = $receipt->amount;
                $account_transaction->transaction_date = $receipt->payment_date;
                $account_transaction->title = 'Installment Payment (Verified) - ' . $studentName;
                $account_transaction->description = 'Verified installment payment via receipt #' . $receipt->id . ' - ' . ($fee->category->title ?? 'Fee');
                $account_transaction->payment_method = $receipt->payment_method;
                $account_transaction->reference_type = 'fees';
                $account_transaction->reference_id = $fee->id;
                $account_transaction->balance_after = $new_balance;
                $account_transaction->created_by = Auth::id();
                $account_transaction->save();
                
                // Update account balance
                $payment_account->current_balance = $new_balance;
                $payment_account->save();
            }

            // Update receipt status
            $receipt->status = 'approved';
            $receipt->verified_by = Auth::id();
            $receipt->verified_at = now();
            $receipt->verification_note = $request->verification_note;
            $receipt->save();

            \DB::commit();

            Flasher::addSuccess('Installment payment receipt approved successfully. Payment has been recorded.');
            return redirect()->route($this->route . '.index');

        } catch (\Exception $e) {
            \DB::rollBack();
            Flasher::addError('Failed to approve receipt: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Reject an installment payment receipt.
     */
    private function rejectInstallmentReceipt(Request $request, $id)
    {
        $receipt = InstallmentPaymentReceipt::findOrFail($id);

        // Check if already processed
        if ($receipt->status !== 'pending') {
            Flasher::addWarning('This receipt has already been processed.');
            return redirect()->back();
        }

        // Update receipt status
        $receipt->status = 'rejected';
        $receipt->verified_by = Auth::id();
        $receipt->verified_at = now();
        $receipt->verification_note = $request->verification_note;
        $receipt->save();

        Flasher::addWarning('Installment payment receipt rejected. Student has been notified.');
        return redirect()->route($this->route . '.index');
    }

    /**
     * Approve a multi-payment.
     */
    private function approveMultiPayment(Request $request, $id)
    {
        $request->validate([
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);

        $multiPayment = MultiPayment::with(['student', 'distributions.fee', 'distributions.installment'])->findOrFail($id);

        // Check if already processed
        if ($multiPayment->status !== 'pending') {
            Flasher::addWarning('This multi-payment has already been processed.');
            return redirect()->back();
        }

        try {
            DB::beginTransaction();

            // Apply payment to each distribution (only amounts > 0)
            foreach ($multiPayment->distributions as $distribution) {
                // Skip distributions with zero or negative amounts
                if ($distribution->amount_applied <= 0) {
                    continue;
                }

                $fee = $distribution->fee;
                
                // Check if this distribution is for a specific installment
                if ($distribution->installment_id && $distribution->installment) {
                    // Payment for a specific installment
                    $installment = $distribution->installment;
                    
                    // Update installment paid amount
                    $installmentPaidBefore = $installment->paid_amount ?? 0;
                    $installmentNewPaid = $installmentPaidBefore + $distribution->amount_applied;
                    
                    // Calculate installment total (including late fees)
                    $installmentTotal = $installment->amount + ($installment->late_fee ?? 0);
                    
                    // Determine installment status
                    if ($installmentNewPaid >= $installmentTotal) {
                        $installmentStatus = 'paid';
                        $installmentPaidAt = now();
                    } elseif ($installmentNewPaid > 0) {
                        $installmentStatus = 'partial';
                        $installmentPaidAt = $installment->paid_at;
                    } else {
                        $installmentStatus = $installment->status;
                        $installmentPaidAt = $installment->paid_at;
                    }
                    
                    // Update installment
                    $installment->update([
                        'paid_amount' => $installmentNewPaid,
                        'status' => $installmentStatus,
                        'paid_at' => $installmentPaidAt,
                    ]);
                    
                    // Also update the main fee's paid amount
                    $fee->paid_amount = ($fee->paid_amount ?? 0) + $distribution->amount_applied;
                    
                    // Calculate fee total and status
                    $feeTotalDue = $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
                    
                    if ($fee->paid_amount >= $feeTotalDue) {
                        $fee->status = 1; // Fully Paid
                    } elseif ($fee->paid_amount > 0) {
                        $fee->status = 2; // Partially Paid
                    } else {
                        $fee->status = 0; // Unpaid
                    }
                    
                    // Update fee
                    $fee->update([
                        'status' => $fee->status,
                        'paid_amount' => $fee->paid_amount,
                        'pay_date' => $multiPayment->payment_date,
                        'payment_method' => $multiPayment->payment_method,
                        'payment_account_id' => $request->payment_account_id,
                        'note' => 'Multi-payment #' . $multiPayment->id . ' - Installment ' . $installment->installment_number . ' - Applied: ' . $distribution->amount_applied,
                        'updated_by' => Auth::id(),
                    ]);
                    
                    // Check if all installments are paid and update payment plan status
                    if ($fee->paymentPlan) {
                        $allInstallmentsPaid = $fee->paymentPlan->installments()
                            ->where('status', '!=', 'paid')
                            ->count() === 0;
                        
                        if ($allInstallmentsPaid) {
                            $fee->paymentPlan->update(['status' => 'completed']);
                            // Clear payment plan link from fee if fully paid
                            if ($fee->status == 1) {
                                $fee->update(['payment_plan_id' => null]);
                            }
                        }
                    }
                    
                } else {
                    // Regular fee payment (no installment)
                    $newPaidAmount = ($fee->paid_amount ?? 0) + $distribution->amount_applied;
                    
                    // Calculate total amount due
                    $totalDue = $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
                    
                    // Determine payment status
                    if ($newPaidAmount >= $totalDue) {
                        $status = 1; // Fully Paid
                    } elseif ($newPaidAmount > 0) {
                        $status = 2; // Partially Paid
                    } else {
                        $status = 0; // Unpaid
                    }
                    
                    // Update the fee
                    $fee->update([
                        'status' => $status,
                        'paid_amount' => $newPaidAmount,
                        'pay_date' => $multiPayment->payment_date,
                        'payment_method' => $multiPayment->payment_method,
                        'payment_account_id' => $request->payment_account_id,
                        'note' => 'Multi-payment #' . $multiPayment->id . ' - Applied: ' . $distribution->amount_applied . ' (Balance after: ' . $distribution->balance_after . ')',
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            // Update multi-payment status
            $multiPayment->update([
                'status' => 'approved',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'admin_note' => $request->verification_note,
            ]);

            // Create Payment Account Transaction if account is selected
            if ($request->payment_account_id) {
                $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
                
                // Calculate new balance
                $new_balance = $payment_account->current_balance + $multiPayment->amount_paid;
                
                // Create transaction record
                $account_transaction = new PaymentAccountTransaction;
                $account_transaction->payment_account_id = $request->payment_account_id;
                $account_transaction->transaction_type = 'credit';
                $account_transaction->amount = $multiPayment->amount_paid;
                $account_transaction->transaction_date = $multiPayment->payment_date;
                $account_transaction->title = 'Multi-Fee Payment (Verified) - ' . trim($multiPayment->student->first_name . ' ' . $multiPayment->student->last_name);
                $account_transaction->description = 'Verified multi-fee payment #' . $multiPayment->id . ' - ' . $multiPayment->distributions->where('amount_applied', '>', 0)->count() . ' items paid';
                $account_transaction->payment_method = $multiPayment->payment_method;
                $account_transaction->reference_type = 'multi_payments';
                $account_transaction->reference_id = $multiPayment->id;
                $account_transaction->balance_after = $new_balance;
                $account_transaction->created_by = Auth::id();
                $account_transaction->save();
                
                // Update account balance
                $payment_account->current_balance = $new_balance;
                $payment_account->save();
            }

            DB::commit();

            Flasher::addSuccess('Multi-payment approved successfully. All fees and installments have been updated.');
            return redirect()->route($this->route . '.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError('Failed to approve multi-payment: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Reject a multi-payment.
     */
    private function rejectMultiPayment(Request $request, $id)
    {
        $multiPayment = MultiPayment::findOrFail($id);

        // Check if already processed
        if ($multiPayment->status !== 'pending') {
            Flasher::addWarning('This multi-payment has already been processed.');
            return redirect()->back();
        }

        // Update multi-payment status
        $multiPayment->update([
            'status' => 'rejected',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'admin_note' => $request->verification_note,
        ]);

        Flasher::addInfo('Multi-payment rejected. Student has been notified.');
        return redirect()->route($this->route . '.index');
    }
}
