<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\InstallmentPaymentReceipt;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Traits\FileUploader;

class InstallmentPaymentVerificationController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = 'Installment Payment Verification';
        $this->route = 'admin.installment-payment-verification';
        $this->view = 'admin.installment-payment-verification';
        $this->path = 'installment-receipts';
        $this->access = 'payment-plan';
    }

    /**
     * Display a listing of pending receipts.
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

        // Filter by status
        $status = $request->input('status', 'pending');
        $data['selected_status'] = $status;

        // Get receipts
        $receipts = InstallmentPaymentReceipt::with([
            'installment.paymentPlan.fee.category',
            'installment.paymentPlan.fee.studentEnroll.student',
            'student',
            'verifier'
        ]);

        if ($status !== 'all') {
            $receipts->where('status', $status);
        }

        $data['rows'] = $receipts->orderBy('created_at', 'desc')->paginate(20);

        // Statistics
        $data['pending_count'] = InstallmentPaymentReceipt::where('status', 'pending')->count();
        $data['approved_count'] = InstallmentPaymentReceipt::where('status', 'approved')->count();
        $data['rejected_count'] = InstallmentPaymentReceipt::where('status', 'rejected')->count();

        return view($this->view . '.index', $data);
    }

    /**
     * Display the specified receipt for verification.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get receipt with all relationships
        $data['row'] = InstallmentPaymentReceipt::with([
            'installment.paymentPlan.fee.category',
            'installment.paymentPlan.fee.studentEnroll.student',
            'installment.paymentPlan.fee.studentEnroll.program',
            'installment.paymentPlan.fee.studentEnroll.session',
            'installment.paymentPlan.fee.studentEnroll.semester',
            'student',
            'verifier'
        ])->findOrFail($id);

        // Get active payment accounts for linking
        $data['payment_accounts'] = PaymentAccount::where('status', 1)
            ->orderBy('title', 'asc')
            ->get();

        return view($this->view . '.show', $data);
    }

    /**
     * Approve a payment receipt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'verification_note' => 'nullable|string|max:500',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);

        $receipt = InstallmentPaymentReceipt::with('installment.paymentPlan.fee.studentEnroll.student')->findOrFail($id);

        // Check if already processed
        if ($receipt->status !== 'pending') {
            Flasher::addError('This receipt has already been processed.');
            return redirect()->back();
        }

        try {
            \DB::beginTransaction();

            // Record the payment using the installment's recordPayment method with payment account
            $receipt->installment->recordPayment(
                $receipt->amount,
                [
                    'payment_method' => $receipt->payment_method,
                    'payment_date' => $receipt->payment_date,
                    'note' => $receipt->note,
                    'payment_account_id' => $request->payment_account_id
                ]
            );

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
            $receipt->verified_by = Auth::guard('web')->user()->id;
            $receipt->verified_at = now();
            $receipt->verification_note = $request->verification_note;
            $receipt->save();

            \DB::commit();

            Flasher::addSuccess('Payment receipt approved successfully. Payment has been recorded.');
            return redirect()->route($this->route . '.index');

        } catch (\Exception $e) {
            \DB::rollBack();
            Flasher::addError('Failed to approve receipt: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Reject a payment receipt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'verification_note' => 'required|string|max:500',
        ]);

        $receipt = InstallmentPaymentReceipt::findOrFail($id);

        // Check if already processed
        if ($receipt->status !== 'pending') {
            Flasher::addError('This receipt has already been processed.');
            return redirect()->back();
        }

        // Update receipt status
        $receipt->status = 'rejected';
        $receipt->verified_by = Auth::guard('web')->user()->id;
        $receipt->verified_at = now();
        $receipt->verification_note = $request->verification_note;
        $receipt->save();

        Flasher::addWarning('Payment receipt rejected. Student has been notified.');
        return redirect()->route($this->route . '.index');
    }
}
