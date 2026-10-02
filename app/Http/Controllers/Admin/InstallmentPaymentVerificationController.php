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

            // Read the instalment again under a lock. The balance the verifier
            // saw was read before this form was submitted, and another receipt
            // for the same instalment may have been approved since. recordPayment
            // refuses anything the instalment cannot take; the lock makes sure it
            // is refusing against the current figure.
            $installment = PaymentPlanInstallment::whereKey($receipt->installment_id)
                ->lockForUpdate()->firstOrFail();

            // recordPayment sees to the instalment, the fee, the ledger, the
            // payment account and the student's statement. This screen used to
            // credit the payment account itself, which would now be done twice.
            $installment->recordPayment(
                $receipt->amount,
                [
                    'payment_method' => $receipt->payment_method,
                    'payment_date' => $receipt->payment_date,
                    'note' => $receipt->note,
                    'payment_account_id' => $request->payment_account_id,
                    'paid_by_type' => 'App\Models\Student',
                    'paid_by_id' => $receipt->student_id,
                ]
            );

            // Update receipt status
            $receipt->status = 'approved';
            $receipt->verified_by = Auth::guard('web')->user()->id;
            $receipt->verified_at = now();
            $receipt->verification_note = $request->verification_note;
            $receipt->save();

            \DB::commit();

            Flasher::addSuccess('Payment receipt approved successfully. Payment has been recorded.');
            return redirect()->route($this->route . '.index');

        } catch (\DomainException $e) {
            // The instalment cannot take this money — most often because another
            // receipt for it was approved first. The receipt stays pending so it
            // can be rejected, or approved against a different instalment.
            \DB::rollBack();
            Flasher::addError($e->getMessage() . ' The receipt has been left pending.');
            return redirect()->back();
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
