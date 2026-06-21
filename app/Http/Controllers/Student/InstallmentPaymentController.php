<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\PaymentPlanInstallment;
use App\Models\InstallmentPaymentReceipt;
use App\Models\Student;

class InstallmentPaymentController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->title = 'Installment Payment';
        $this->route = 'student.installment-payment';
        $this->view = 'student.installment-payment';
        $this->path = 'installment-receipts';
    }

    /**
     * Show the form for uploading a payment receipt.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // Add setting for currency symbol
        $data['setting'] = \App\Models\PrintSetting::where('slug', 'fees-receipt')->first();

        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get installment (ensure it belongs to this student)
        $data['installment'] = PaymentPlanInstallment::with(['paymentPlan.fee.category', 'paymentPlan.fee.studentEnroll'])
            ->whereHas('paymentPlan', function($query) use ($user) {
                $query->where('student_id', $user->id);
            })
            ->findOrFail($id);

        // Check if installment has pending multi-payment
        if ($data['installment']->hasPendingMultiPayment()) {
            Flasher::addWarning('This installment has a pending multi-payment verification. Please wait for approval.');
            return redirect()->route('student.payment-plan.show', $data['installment']->payment_plan_id);
        }

        // Check if installment can be paid
        if (!in_array($data['installment']->status, ['pending', 'partial', 'overdue'])) {
            Flasher::addError('This installment has already been paid.');
            return redirect()->route('student.payment-plan.show', $data['installment']->payment_plan_id);
        }

        return view($this->view . '.create', $data);
    }

    /**
     * Store a newly created payment receipt in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|integer|min:1',
            'receipt_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'note' => 'nullable|string|max:500',
        ]);

        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get installment (ensure it belongs to this student)
        $installment = PaymentPlanInstallment::with('paymentPlan')
            ->whereHas('paymentPlan', function($query) use ($user) {
                $query->where('student_id', $user->id);
            })
            ->findOrFail($id);

        // Check if installment has pending multi-payment
        if ($installment->hasPendingMultiPayment()) {
            Flasher::addWarning('This installment has a pending multi-payment verification. Please wait for approval.');
            return redirect()->route('student.payment-plan.show', $installment->payment_plan_id);
        }

        // Check if installment can be paid
        if (!in_array($installment->status, ['pending', 'partial', 'overdue'])) {
            Flasher::addError('This installment has already been paid.');
            return redirect()->route('student.payment-plan.show', $installment->payment_plan_id);
        }

        // Validate amount doesn't exceed remaining balance
        if ($request->amount > $installment->remaining_balance) {
            Flasher::addError('Payment amount cannot exceed remaining balance of ' . number_format($installment->remaining_balance, 2));
            return redirect()->back()->withInput();
        }

        // Upload receipt file
        $receipt_file = $this->uploadMedia($request, 'receipt_file', $this->path, 600, 800);

        // Create payment receipt record
        $receipt = new InstallmentPaymentReceipt();
        $receipt->installment_id = $installment->id;
        $receipt->student_id = $user->id;
        $receipt->amount = $request->amount;
        $receipt->payment_date = $request->payment_date;
        $receipt->payment_method = $request->payment_method;
        $receipt->receipt_file = $receipt_file;
        $receipt->note = $request->note;
        $receipt->status = 'pending'; // Pending admin verification
        $receipt->save();

        Flasher::addSuccess('Payment receipt uploaded successfully. Waiting for admin verification.');
        return redirect()->route('student.payment-plan.show', $installment->payment_plan_id);
    }

    /**
     * Display the specified payment receipt.
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

        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get receipt (ensure it belongs to this student)
        $data['receipt'] = InstallmentPaymentReceipt::with([
                'installment.paymentPlan.fee.category',
                'verifier'
            ])
            ->where('student_id', $user->id)
            ->findOrFail($id);

        return view($this->view . '.show', $data);
    }
}
