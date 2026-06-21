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
use App\Models\Transaction;
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
        $this->middleware('permission:'.$this->access.'.pay', ['only' => ['pay', 'processPayment']]);
        $this->middleware('permission:'.$this->access.'.approve', ['only' => ['approve']]);
        $this->middleware('permission:'.$this->access.'.cancel', ['only' => ['cancel']]);
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
        $query = PaymentPlan::with(['student', 'fee.category', 'fee.studentEnroll.program', 'creator', 'installments']);

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
        // Validate request
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'fee_id' => 'required|exists:fees,id',
            'total_amount' => 'required|numeric|min:0',
            'installments_count' => 'required|integer|min:2|max:12',
            'late_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'grace_period_days' => 'nullable|integer|min:0|max:30',
            'notes' => 'nullable|string',
            'installments.*.amount' => 'required|numeric|min:0',
            'installments.*.due_date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            // Create payment plan
            $plan = PaymentPlan::create([
                'student_id' => $request->student_id,
                'fee_id' => $request->fee_id,
                'total_amount' => $request->total_amount,
                'installments_count' => $request->installments_count,
                'late_fee_percentage' => $request->late_fee_percentage ?? 0,
                'grace_period_days' => $request->grace_period_days ?? 7,
                'created_by' => Auth::user()->id,
                'approved_by' => Auth::user()->id,
                'approved_at' => now(),
                'status' => 'active',
                'notes' => $request->notes,
            ]);

            // Create installments from the installments array
            $installments = $request->installments;
            $installmentNumber = 1;

            foreach ($installments as $installmentData) {
                $dueDate = Carbon::parse($installmentData['due_date']);
                $gracePeriodEnds = $dueDate->copy()->addDays($plan->grace_period_days);

                PaymentPlanInstallment::create([
                    'payment_plan_id' => $plan->id,
                    'installment_number' => $installmentNumber,
                    'amount' => $installmentData['amount'],
                    'due_date' => $dueDate,
                    'grace_period_ends' => $gracePeriodEnds,
                    'status' => 'pending',
                ]);

                $installmentNumber++;
            }

            // Update fee to link it to this payment plan
            // This prevents direct payment on this fee while payment plan is active
            $plan->fee->update([
                'payment_plan_id' => $plan->id,
            ]);

            DB::commit();

            Flasher::addSuccess('Payment plan created successfully!');
            return redirect()->route($this->route.'.show', $plan->id);

        } catch (\Exception $e) {
            DB::rollback();
            Flasher::addError('Failed to create payment plan: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
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

        $data['row'] = PaymentPlan::with([
            'student.currentEnroll', 
            'fee', 
            'creator', 
            'approver',
            'installments.payments.paidBy'
        ])->findOrFail($id);

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
            $plan->update([
                'late_fee_percentage' => $request->late_fee_percentage ?? 0,
                'grace_period_days' => $request->grace_period_days ?? 7,
                'notes' => $request->notes,
            ]);

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
            $plan->cancel($request->cancellation_reason);

            // Revert fee status
            $plan->fee->update([
                'paid_status' => 0, // Unpaid
            ]);

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
     * Show payment form for an installment.
     *
     * @return \Illuminate\Http\Response
     */
    public function pay($id, $installmentId)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;

        $data['plan'] = PaymentPlan::with(['student', 'fee'])->findOrFail($id);
        $data['installment'] = PaymentPlanInstallment::findOrFail($installmentId);

        return view($this->view.'.pay', $data);
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

        // Validate
        $request->validate([
            'payment_plan_id' => 'required|exists:payment_plans,id',
            'installment_id' => 'required|exists:payment_plan_installments,id',
            'amount' => 'required|numeric|min:0|max:' . $installment->remaining_balance,
            'payment_method' => 'required|integer|between:1,7',
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string',
            'note' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Handle receipt upload
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receipt = $request->file('receipt');
                $receiptName = time() . '_' . $receipt->getClientOriginalName();
                $receipt->move(public_path('uploads/payment-plan'), $receiptName);
                $receiptPath = 'payment-plan/' . $receiptName;
            }

            // Record payment
            $payment = $installment->recordPayment($request->amount, [
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'reference_no' => $request->reference_no,
                'receipt_path' => $receiptPath,
                'paid_by_type' => 'App\User',
                'paid_by_id' => Auth::user()->id,
                'note' => $request->note,
            ]);

            // Create transaction record for accounting (using polymorphic relationship)
            $transactionId = 'TXN-PP-' . time() . '-' . $installment->id;
            
            Transaction::create([
                'transactionable_id' => $payment->id,
                'transactionable_type' => 'App\Models\PaymentPlanPayment',
                'transaction_id' => $transactionId,
                'amount' => $request->amount,
                'type' => 1, // 1 = Credit (income)
                'created_by' => Auth::user()->id,
            ]);

            DB::commit();

            Flasher::addSuccess('Payment recorded successfully!');
            return redirect()->route($this->route.'.show', $id);

        } catch (\Exception $e) {
            DB::rollback();
            Flasher::addError('Failed to process payment: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $plan = PaymentPlan::findOrFail($id);

        // Only allow deletion of cancelled or draft plans with no payments
        if ($plan->total_paid > 0) {
            Flasher::addWarning('Cannot delete payment plan with recorded payments');
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            // Delete installments (payments will cascade)
            $plan->installments()->delete();
            
            // Delete plan
            $plan->delete();

            // Revert fee status if needed
            if ($plan->fee) {
                $plan->fee->update(['paid_status' => 0]);
            }

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
     * Get student fees for AJAX call.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStudentFees(Request $request)
    {
        $studentId = $request->student_id;
        
        if (!$studentId) {
            return response()->json(['fees' => []]);
        }

        // Get student's unpaid or partially paid fees that don't have an active payment plan
        $fees = Fee::with(['category', 'studentEnroll.program', 'studentEnroll.session', 'studentEnroll.semester'])
            ->whereHas('studentEnroll', function($query) use ($studentId) {
                $query->where('student_id', $studentId);
            })
            ->whereIn('status', [0, 2]) // Unpaid (0) or Partially Paid (2)
            ->whereNull('payment_plan_id') // Exclude fees already linked to a payment plan
            ->get()
            ->map(function($fee) {
                return [
                    'id' => $fee->id,
                    'category_title' => $fee->category->title ?? '',
                    'fee_amount' => $fee->fee_amount,
                    'discount_amount' => $fee->discount_amount ?? 0,
                    'fine_amount' => $fee->fine_amount ?? 0,
                    'paid_amount' => $fee->paid_amount ?? 0,
                    'total_amount' => $fee->total_amount,
                    'remaining_balance' => $fee->remaining_balance,
                    'status' => $fee->status,
                    'status_label' => $fee->isPartiallyPaid() ? 'Partially Paid' : 'Unpaid',
                ];
            });

        return response()->json(['fees' => $fees]);
    }
}
