<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanPayment;
use App\Models\Fee;
use App\Models\Student;
use App\Models\PrintSetting;
use Carbon\Carbon;

class PaymentPlanController extends Controller
{
    protected $title, $route, $view, $path, $access;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = 'Payment Plan';
        $this->route = 'admin.payment-plan';
        $this->view = 'admin.payment-plan';
        $this->path = 'payment-plan';
        $this->access = 'payment-plan';

        $this->middleware('permission:'.$this->access.'.index', ['only' => ['index']]);
        $this->middleware('permission:'.$this->access.'.create', ['only' => ['create']]);
        $this->middleware('permission:'.$this->access.'.store', ['only' => ['store']]);
        $this->middleware('permission:'.$this->access.'.show', ['only' => ['show']]);
        $this->middleware('permission:'.$this->access.'.edit', ['only' => ['edit']]);
        $this->middleware('permission:'.$this->access.'.update', ['only' => ['update']]);
        $this->middleware('permission:'.$this->access.'.destroy', ['only' => ['destroy']]);
        $this->middleware('permission:'.$this->access.'.pay', ['only' => ['processPayment', 'splitPreview']]);
        $this->middleware('permission:'.$this->access.'.cancel', ['only' => ['cancel']]);
        $this->middleware('permission:'.$this->access.'.reverse', ['only' => ['reverse']]);
    }

    /**
     * Display a listing of the resource.
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
        
        // Add setting for currency symbol
        $data['setting'] = PrintSetting::where('slug', 'fees-receipt')->first();

        // Filter by status
        $status = $request->get('status', '');
        $data['selected_status'] = $status;

        // Filter by student search
        $search = $request->get('student', '');
        $data['search_student'] = $search;

        // Build query
        $query = PaymentPlan::with(['agreement.plans', 'student', 'fee.category', 'fee.studentEnroll.program', 'creator', 'installments']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('student', function($q) use ($search) {
                $q->where('student_id', 'like', '%'.$search.'%')
                  ->orWhere('first_name', 'like', '%'.$search.'%')
                  ->orWhere('last_name', 'like', '%'.$search.'%');
            });
        }

        $data['rows'] = $query->orderBy('id', 'desc')->paginate(20);

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        
        // Add setting for currency symbol
        $data['setting'] = PrintSetting::where('slug', 'fees-receipt')->first();
        
        // Get all active students with current enrollment
        $data['students'] = Student::whereHas('currentEnroll')->where('status', '1')->orderBy('student_id', 'asc')->get();

        // Get student and fee from request
        $studentId = $request->get('student_id');
        $feeId = $request->get('fee_id');

        if ($studentId && $feeId) {
            $data['student'] = Student::findOrFail($studentId);
            $data['fee'] = Fee::findOrFail($feeId);
        }

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'grace_period_days' => 'nullable|integer|min:0|max:30',
            'notes' => 'nullable|string',
            // The older one-fee form sent a count; it is still honoured.
            'installments_count' => 'sometimes|integer|min:1|max:12',
            'installments.*.amount' => 'sometimes|required|numeric|min:0',
            'installments.*.due_date' => 'sometimes|required|date',
        ]);

        $student = Student::findOrFail($request->student_id);

        // An agreement is prepared, not put into force: it holds its fees and
        // takes no money until the Vice Chancellor approves it.
        try {
            $agreement = app(\App\Services\PaymentPlanAgreements::class)->create(
                $student,
                $this->scheduleFrom($request),
                $request->notes,
                Auth::id(),
                (int) ($request->grace_period_days ?? 7)
            );
        } catch (\DomainException $e) {
            Flasher::addError($e->getMessage());
            return redirect()->back()->withInput();
        }

        Flasher::addSuccess(__('Agreement :ref prepared. Print it, sign it with the student, and take it to the Vice Chancellor for approval.', [
            'ref' => $agreement->reference,
        ]));

        // Straight to the document: it is what happens next.
        return redirect()->route('admin.payment-plan.agreement.print', $agreement->id);
    }

    /**
     * The schedule as submitted: plans[fee_id][include, installments[...]],
     * or the older form's single fee_id with installments[...].
     */
    protected function scheduleFrom(Request $request): array
    {
        $spec = [];

        if ($request->has('plans')) {
            foreach ((array) $request->input('plans') as $feeId => $plan) {
                if (empty($plan['include'])) {
                    continue;
                }

                $spec[] = [
                    'fee_id' => (int) $feeId,
                    'installments' => array_values((array) ($plan['installments'] ?? [])),
                ];
            }

            return $spec;
        }

        if ($request->filled('fee_id')) {
            $spec[] = [
                'fee_id' => (int) $request->fee_id,
                'installments' => array_values((array) $request->input('installments', [])),
            ];
        }

        return $spec;
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;
        
        // Add setting for currency symbol
        $data['setting'] = PrintSetting::where('slug', 'fees-receipt')->first();

        $data['row'] = PaymentPlan::with(['agreement', 
            'student.currentEnroll',
            'fee',
            'creator',
            'approver',
            'installments.payments.paidBy'
        ])->findOrFail($id);

        // Offered on the payment form so the money lands in the right account
        // and the cash book as it is recorded, rather than waiting to be linked.
        $data['payment_accounts'] = \App\Models\PaymentAccount::where('status', 1)
            ->orderBy('title')->get();

        return view($this->view.'.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        
        // Add setting for currency symbol
        $data['setting'] = PrintSetting::where('slug', 'fees-receipt')->first();

        $data['row'] = PaymentPlan::with(['student', 'fee', 'installments'])->findOrFail($id);

        // Only allow editing if plan is active
        if (!$data['row']->isActive()) {
            Flasher::addWarning('Can only edit active payment plans');
            return redirect()->route($this->route.'.show', $id);
        }

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $plan = PaymentPlan::findOrFail($id);

        // Only allow updating active plans
        if (!$plan->isActive()) {
            Flasher::addWarning('Can only edit active payment plans');
            return redirect()->route($this->route.'.show', $id);
        }

        // Validate request
        $request->validate([
            'late_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'grace_period_days' => 'nullable|integer|min:0|max:30',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $update = [
                'grace_period_days' => $request->grace_period_days ?? 7,
                'notes' => $request->notes,
            ];

            // With late fees switched off the field is disabled, so nothing is
            // submitted for it. Leaving the stored percentage untouched keeps an
            // older plan's record honest; nothing can charge it either way.
            if (config('payment_plan.late_fees_enabled', false)) {
                $update['late_fee_percentage'] = $request->late_fee_percentage ?? 0;
            }

            $plan->update($update);

            // Update grace period ends for pending installments
            foreach ($plan->installments()->whereIn('status', ['pending', 'partial'])->get() as $installment) {
                $installment->update([
                    'grace_period_ends' => $installment->due_date->copy()->addDays($plan->grace_period_days),
                ]);
            }

            DB::commit();

            Flasher::addSuccess('Payment plan updated successfully!');
            return redirect()->route($this->route.'.show', $id);

        } catch (\Exception $e) {
            DB::rollback();
            Flasher::addError('Failed to update payment plan: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Cancel a payment plan.
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request, $id)
    {
        $plan = PaymentPlan::findOrFail($id);

        if (!$plan->isActive()) {
            Flasher::addWarning('Can only cancel active payment plans');
            return redirect()->route($this->route.'.show', $id);
        }

        $request->validate([
            'cancellation_reason' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Releases the fee for direct payment again. Money already taken
            // stays on the fee and stays in the ledger — it was really paid.
            $plan->cancel($request->cancellation_reason);

            DB::commit();

            Flasher::addSuccess('Payment plan cancelled successfully');
            return redirect()->route($this->route.'.index');

        } catch (\Exception $e) {
            DB::rollback();
            Flasher::addError('Failed to cancel payment plan: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Process payment for an installment.
     *
     * @return \Illuminate\Http\Response
     */
    public function processPayment(Request $request)
    {
        $id = $request->payment_plan_id;
        $installmentId = $request->installment_id;
        
        $plan = PaymentPlan::findOrFail($id);
        $installment = PaymentPlanInstallment::findOrFail($installmentId);

        // Both ids were checked to exist, never against each other — so an
        // instalment id from another plan would have credited that other plan's
        // fee while this plan's screen reported success.
        if ((int) $installment->payment_plan_id !== (int) $plan->id) {
            Flasher::addError('That instalment belongs to a different payment plan.');
            return redirect()->back();
        }

        // Validate
        $request->validate([
            'payment_plan_id' => 'required|exists:payment_plans,id',
            'installment_id' => 'required|exists:payment_plan_installments,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|integer|between:1,7',
            'payment_date' => 'required|date',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'reference_no' => 'nullable|string',
            'note' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Where the money goes.
        //
        // More than the instalment needs is the ordinary case, not a mistake:
        // a student pays toward what they owe. The rest is carried onto the
        // plan's later instalments and then the student's other instalment
        // fees, exactly as a payment on an ordinary fee is carried — and what
        // the bursar was shown is checked again here, never trusted.
        $distributor = app(\App\Services\PlanPaymentDistribution::class);
        $amount = round((float) $request->amount, 2);

        if ($request->filled('allocation')) {
            $legs = collect($request->input('allocation'))
                ->map(fn ($leg) => [
                    'type' => (string) ($leg['type'] ?? ''),
                    'id' => (int) ($leg['id'] ?? 0),
                    'applied' => round((float) ($leg['applied'] ?? 0), 2),
                ])
                ->filter(fn ($leg) => $leg['applied'] > 0.005)
                ->values()
                ->all();
        } else {
            $legs = $distributor->propose($installment, $amount)['legs'];
        }

        DB::beginTransaction();
        try {
            $refusals = $distributor->blockers($installment, $legs, $amount);

            if ($refusals !== []) {
                DB::rollBack();
                Flasher::addError(implode(' ', $refusals));
                return redirect()->back()->withInput();
            }

            // Handle receipt upload
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receipt = $request->file('receipt');
                $receiptName = time() . '_' . $receipt->getClientOriginalName();
                $receipt->move(public_path('uploads/payment-plan'), $receiptName);
                $receiptPath = 'payment-plan/' . $receiptName;
            }

            // Each leg is recorded as it would be on its own screen: an
            // instalment sees to everything its money touches — the
            // instalment, the fee, the ledger, the payment account and the
            // student's statement — and an ordinary fee is written by the same
            // recorder the fee screen uses.
            $applied = $distributor->record($installment, $legs, [
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'payment_account_id' => $request->payment_account_id,
                'reference_no' => $request->reference_no,
                'receipt_path' => $receiptPath,
                'note' => $request->note,
            ]);

            DB::commit();

            if (count($applied) > 1) {
                Flasher::addSuccess(__('Payment split across :count lines: :detail', [
                    'count' => count($applied),
                    'detail' => implode(', ', array_map(
                        fn ($leg) => $leg['label'] . ' ' . number_format($leg['applied'], 2),
                        $applied
                    )),
                ]));
            }

            Flasher::addSuccess('Payment recorded successfully!');

            // Paid from the fees report: straight to the fee's receipt, the
            // way a payment on an ordinary fee ends. Its Back link runs off
            // the previous URL, which is the filtered report, so the bursar's
            // place is kept.
            if ($request->input('return_to') === 'report' && $plan->fee_id) {
                return redirect()->route('admin.fees-student.print', ['id' => $plan->fee_id]);
            }

            return redirect()->route($this->route.'.show', $id);

        } catch (\DomainException $e) {
            // The money was refused for a reason worth reading — the balance, a
            // plan that is no longer active. Nothing has been written.
            DB::rollback();
            Flasher::addError($e->getMessage());
            return redirect()->back()->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            Flasher::addError('Failed to process payment: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Reverse one instalment payment, everywhere it reached.
     *
     * @return \Illuminate\Http\Response
     */
    public function reverse(Request $request, $id, $paymentId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $payment = PaymentPlanPayment::with('installment')->findOrFail($paymentId);

        // The payment has to belong to the plan whose screen asked for this.
        if (!$payment->installment || (int) $payment->installment->payment_plan_id !== (int) $id) {
            Flasher::addError('That payment belongs to a different payment plan.');
            return redirect()->back();
        }

        $result = app(\App\Services\InstallmentPaymentReversal::class)
            ->reverse($payment, $request->reason);

        if ($result['reversed']) {
            Flasher::addSuccess($result['message']);
        } else {
            Flasher::addError($result['message']);
        }

        return redirect()->route($this->route.'.show', $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $plan = PaymentPlan::findOrFail($id);

        // A plan that is part of an agreement ends through the agreement —
        // rejected or cancelled — so the signed document's history stays whole.
        if ($plan->agreement_id) {
            Flasher::addWarning(__('This plan is part of agreement :ref. Reject or cancel the agreement instead.', [
                'ref' => optional($plan->agreement)->reference,
            ]));
            return redirect()->back();
        }

        // Only allow deletion of cancelled or draft plans with no payments
        if ($plan->total_paid > 0) {
            Flasher::addWarning('Cannot delete payment plan with recorded payments');
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            // Delete installments (payments will cascade)
            $plan->installments()->delete();

            // Deleting the plan releases the fee: fees.payment_plan_id is a
            // foreign key with ON DELETE SET NULL.
            $plan->delete();

            DB::commit();

            Flasher::addSuccess('Payment plan deleted successfully');
            return redirect()->route($this->route.'.index');

        } catch (\Exception $e) {
            DB::rollback();
            Flasher::addError('Failed to delete payment plan: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * How an amount handed over on an instalment would be spread.
     *
     * Answered by the server rather than worked out in the browser, so the
     * figures the bursar is shown are the figures the money will actually move
     * against. Nothing is written.
     */
    public function splitPreview(Request $request, $id)
    {
        $installment = PaymentPlanInstallment::with('paymentPlan.fee.category')->findOrFail($id);
        $amount = round((float) $request->input('amount', 0), 2);
        $proposal = app(\App\Services\PlanPaymentDistribution::class)->propose($installment, $amount);

        return response()->json([
            'installment_id' => $installment->id,
            'amount' => $amount,
            'legs' => $proposal['legs'],
            'leftover' => $proposal['leftover'],
            // Everything the student could put money on from this screen, so
            // the modal can say how much is too much.
            'owed' => $proposal['owed'],
            'outstanding' => round((float) $installment->remaining_balance, 2),
        ]);
    }

    /**
     * Get student fees for AJAX call.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStudentFees(Request $request)
    {
        $studentId = (int) $request->student_id;

        if (!$studentId) {
            return response()->json(['fees' => []]);
        }

        $agreements = app(\App\Services\PaymentPlanAgreements::class);

        $fees = $agreements->eligibleFees($studentId)->map(fn ($fee) => [
            'id' => $fee->id,
            'category_title' => $fee->category->title ?? '',
            'session' => optional(optional($fee->studentEnroll)->session)->title,
            'due_date' => $fee->due_date ? \Carbon\Carbon::parse($fee->due_date)->format('Y-m-d') : null,
            'fee_amount' => (float) $fee->fee_amount,
            'discount_amount' => (float) ($fee->discount_amount ?? 0),
            'fine_amount' => (float) ($fee->fine_amount ?? 0),
            'paid_amount' => (float) ($fee->paid_amount ?? 0),
            'total_amount' => (float) $fee->total_amount,
            'remaining_balance' => $agreements->balance($fee),
            'status' => $fee->status,
            'status_label' => $fee->isPartiallyPaid() ? 'Partially Paid' : 'Unpaid',
        ])->values();

        return response()->json(['fees' => $fees]);
    }
}
