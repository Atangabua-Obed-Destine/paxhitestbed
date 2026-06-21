<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use App\Models\PaymentReceipt;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Student;
use App\Models\Fee;
use App\Models\SubjectMarking;

class ManualPaymentController extends Controller
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
        $this->title = trans_choice('module_manual_payment', 1);
        $this->route = 'student.manual-payment';
        $this->view = 'student.manual-payment';
        $this->path = 'payment-receipts';
    }

    /**
     * Display a listing of unpaid fees.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();
        $data['user'] = $user;

        // Get unpaid and partially paid fees for this student (including fees with payment plans)
        $data['fees'] = Fee::with(['studentEnroll.session', 'studentEnroll.semester', 'category', 'paymentReceipts', 'approvedReceipts', 'paymentPlan'])
            ->whereHas('studentEnroll', function ($query) use ($user) {
                $query->where('student_id', $user->id);
            })
            ->whereIn('status', [0, 2]) // Unpaid (0) or Partially Paid (2)
            ->orderBy('due_date', 'asc')
            ->paginate(15);

        // Get submitted receipts history
        $data['receipts'] = PaymentReceipt::with(['fee.category', 'verifier'])
            ->where('student_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'receipts');

        return view($this->view . '.index', $data);
    }

    /**
     * Show the form for uploading a receipt.
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

        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get the fee and verify it belongs to this student
        $data['fee'] = Fee::with(['studentEnroll.session', 'studentEnroll.semester', 'studentEnroll.program', 'category', 'resitRequest.subject', 'resitRequest.session', 'resitRequest.resitSession', 'resitRequest.resitSemester'])
            ->whereHas('studentEnroll', function ($query) use ($user) {
                $query->where('student_id', $user->id);
            })
            ->where('id', $id)
            ->whereIn('status', [0, 2]) // Allow unpaid (0) or partially paid (2)
            ->firstOrFail();

        // If this is a resit fee, load the student's marks for the failed course
        $data['resitMarking'] = null;
        if ($data['fee']->resitRequest) {
            $rr = $data['fee']->resitRequest;
            $data['resitMarking'] = SubjectMarking::where('student_enroll_id', $rr->student_enroll_id)
                ->where('subject_id', $rr->subject_id)
                ->first();
        }

        // Check if there's already a pending multi-payment for this fee
        if ($data['fee']->hasPendingMultiPayment()) {
            Flasher::addWarning('This fee has a pending multi-payment verification. Please wait for admin approval before making another payment.');
            return redirect()->route($this->route . '.index');
        }

        // Check if there's already a pending receipt for this fee
        $pendingReceipt = PaymentReceipt::where('fee_id', $id)
            ->where('student_id', $user->id)
            ->where('verification_status', 'pending')
            ->first();

        if ($pendingReceipt) {
            Flasher::addWarning(__('msg_payment_receipt_already_submitted'));
            return redirect()->route($this->route . '.show', $pendingReceipt->id);
        }

        return view($this->view . '.create', $data);
    }

    /**
     * Store a newly uploaded receipt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'fee_id' => 'required|exists:fees,id',
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120', // 5MB max
            'payment_reference' => 'nullable|string|max:255',
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|integer|between:1,6',
            'student_note' => 'nullable|string|max:1000',
        ]);

        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Verify the fee belongs to this student  
        $fee = Fee::with('resitRequest')->whereHas('studentEnroll', function ($query) use ($user) {
            $query->where('student_id', $user->id);
        })
        ->where('id', $request->fee_id)
        ->whereIn('status', [0, 2]) // Allow unpaid (0) or partially paid (2)
        ->firstOrFail();

        // Check if there's a pending multi-payment for this fee
        if ($fee->hasPendingMultiPayment()) {
            Flasher::addError('This fee has a pending multi-payment verification. Cannot submit another payment.');
            return redirect()->route($this->route . '.index');
        }

        // Check if payment amount exceeds remaining balance
        $remainingBalance = $fee->remaining_balance;
        if ($request->amount > $remainingBalance) {
            Flasher::addError(__('msg_payment_exceeds_balance') . ' Remaining: ' . $remainingBalance);
            return redirect()->back()->withInput();
        }

        // Resit fees require full payment — no partial payments allowed
        if ($fee->resitRequest && $request->amount < $remainingBalance) {
            Flasher::addError(__('Partial payment is not allowed for resit fees. You must pay the full remaining balance.'));
            return redirect()->back()->withInput();
        }

        // Check for existing pending receipt
        $existingReceipt = PaymentReceipt::where('fee_id', $request->fee_id)
            ->where('student_id', $user->id)
            ->where('verification_status', 'pending')
            ->first();

        if ($existingReceipt) {
            Flasher::addWarning(__('msg_payment_receipt_already_submitted'));
            return redirect()->route($this->route . '.show', $existingReceipt->id);
        }

        // Upload receipt file
        $receiptFile = $this->uploadMedia($request, 'receipt_file', $this->path);

        // Create payment receipt record
        $receipt = PaymentReceipt::create([
            'fee_id' => $request->fee_id,
            'student_id' => $user->id,
            'receipt_file' => $receiptFile,
            'payment_reference' => $request->payment_reference,
            'payment_date' => $request->payment_date,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'student_note' => $request->student_note,
            'verification_status' => 'pending',
        ]);

        Flasher::addSuccess(__('msg_payment_receipt_submitted_successfully'));
        
        return redirect()->route($this->route . '.show', $receipt->id);
    }

    /**
     * Display the specified receipt.
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

        // Get the receipt and verify it belongs to this student
        $data['row'] = PaymentReceipt::with(['fee.category', 'fee.studentEnroll.session', 'fee.studentEnroll.semester', 'verifier'])
            ->where('id', $id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        return view($this->view . '.show', $data);
    }
}
