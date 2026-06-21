<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeeSetting;
use App\Models\PlatformFeePayment;
use App\Models\StudentEnroll;
use App\Models\Setting;
use App\Traits\FileUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformFeePaymentController extends Controller
{
    use FileUploader;

    /**
     * Show payment requirement page.
     */
    public function index()
    {
        $platformSetting = PlatformFeeSetting::first();
        $systemSetting = Setting::where('status', '1')->first();
        $student = Auth::guard('student')->user();
        
        // Get current session (latest enrollment)
        $currentEnrollment = StudentEnroll::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->with('session', 'program', 'semester')
            ->first();
        
        if (!$currentEnrollment) {
            abort(403, 'No active enrollment found. Please contact administration.');
        }
        
        // Check for existing payment
        $payment = PlatformFeePayment::where('student_enroll_id', $currentEnrollment->id)
            ->where('session_id', $currentEnrollment->session_id)
            ->first();
        
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
        $currentEnrollment = StudentEnroll::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
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

        // Create or update payment record
        $payment = PlatformFeePayment::where('student_enroll_id', $currentEnrollment->id)->first();
        
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
