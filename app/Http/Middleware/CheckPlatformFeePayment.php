<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PlatformFeeSetting;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeExemption;
use App\Models\StudentEnroll;
use Illuminate\Support\Facades\Auth;

class CheckPlatformFeePayment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if student is authenticated
        if (!Auth::guard('student')->check()) {
            return $next($request);
        }

        $student = Auth::guard('student')->user();
        
        // Get platform fee settings
        $setting = PlatformFeeSetting::first();
        
        // If platform fee is disabled globally, allow access
        if (!$setting || !$setting->is_enabled) {
            return $next($request);
        }

        // Get current student enrollment (latest)
        $currentEnrollment = StudentEnroll::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$currentEnrollment) {
            return $next($request); // No enrollment, allow access
        }

        // Check if session is exempted
        $sessionExempted = PlatformFeeExemption::where('status', 1)
            ->where('exemption_type', 'session')
            ->where('session_id', $currentEnrollment->session_id)
            ->exists();
        
        if ($sessionExempted) {
            return $next($request);
        }

        // Check if student is exempted
        $studentExempted = PlatformFeeExemption::where('status', 1)
            ->where('exemption_type', 'student')
            ->where('student_enroll_id', $currentEnrollment->id)
            ->exists();
        
        if ($studentExempted) {
            return $next($request);
        }

        // Check payment status
        $payment = PlatformFeePayment::where('student_enroll_id', $currentEnrollment->id)
            ->where('session_id', $currentEnrollment->session_id)
            ->first();
        
        // If payment approved, allow access
        if ($payment && $payment->status == 'approved') {
            return $next($request);
        }

        // Redirect to payment page
        return redirect()->route('student.platform-fee.payment')
            ->with('warning', 'Please complete your platform access fee payment to access the portal.');
    }
}

