<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeeSetting;
use App\Models\PlatformFeePayment;
use App\Models\StudentEnroll;
use App\Models\Setting;
use App\Services\PlatformFeeAccess;
use App\Traits\FileUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformFeePaymentController extends Controller
{
    use FileUploader;

    public function __construct(protected PlatformFeeAccess $access)
    {
    }

    /**
     * Show payment requirement page.
     */
    public function index()
    {
        $platformSetting = PlatformFeeSetting::first();
        $systemSetting = Setting::where('status', '1')->first();
        $student = Auth::guard('student')->user();
        
        $currentEnrollment = $this->access->currentEnrollment($student);

        if (!$currentEnrollment) {
            abort(403, 'No active enrollment found. Please contact administration.');
        }

        $currentEnrollment->load('session', 'program', 'semester');

        // Any payment covering this academic session, whichever enrolment it was
        // raised from — a student who paid last semester has paid for the year.
        $payment = $this->access->paymentFor($student, $currentEnrollment);
        
        return view('student.platform-fee.payment', compact('platformSetting', 'systemSetting', 'currentEnrollment', 'payment', 'student'));
    }

    /**
     * Upload payment receipt.
     */
    public function uploadReceipt(Request $request)
    {
        $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'student_note' => 'nullable|string|max:500',
            'payment_date' => 'required|date',
        ]);

        $student = Auth::guard('student')->user();
        $currentEnrollment = $this->access->currentEnrollment($student);

        if (!$currentEnrollment) {
            abort(403, 'No active enrollment found. Please contact administration.');
        }

        $platformSetting = PlatformFeeSetting::first();

        // Upload file
        $file = $request->file('receipt');
        $fileName = time() . '_' . $student->id . '.' . $file->getClientOriginalExtension();
        
        // Create directory if not exists
        $uploadPath = public_path('uploads/platform-fees');
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        
        $file->move($uploadPath, $fileName);

        // Create or update the payment for this academic session. Looked up
        // across the student's enrolments in it, so re-uploading after
        // progressing updates the receipt they already have rather than
        // starting a second payment for the same year.
        $payment = $this->access->paymentFor($student, $currentEnrollment);

        if ($payment && $payment->status === 'approved') {
            return redirect()->back()->with('success',
                'Your platform access fee for this academic year has already been approved. There is nothing further to pay.');
        }

        if ($payment) {
            // Update existing payment
            $payment->update([
                'fee_amount' => $platformSetting->fee_amount,
                'paid_amount' => $platformSetting->fee_amount,
                'receipt_path' => $fileName,
                'student_note' => $request->student_note,
                'payment_date' => $request->payment_date,
                'status' => 'pending',
                'admin_note' => null, // Reset admin note on reupload
                'verified_by' => null,
                'verified_at' => null,
            ]);
        } else {
            // Create new payment
            PlatformFeePayment::create([
                'student_enroll_id' => $currentEnrollment->id,
                'session_id' => $currentEnrollment->session_id, // Can be null
                'fee_amount' => $platformSetting->fee_amount,
                'paid_amount' => $platformSetting->fee_amount,
                'receipt_path' => $fileName,
                'student_note' => $request->student_note,
                'payment_date' => $request->payment_date,
                'status' => 'pending',
            ]);
        }

        return redirect()->back()->with('success', 'Payment receipt uploaded successfully! Awaiting verification from administration.');
    }
}
