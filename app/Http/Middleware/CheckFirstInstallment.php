<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\Fee;
use App\Models\FeesCategory;
use Illuminate\Support\Facades\Session;

class CheckFirstInstallment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('student')->user();

        if (!$user) {
            return $next($request);
        }

        // Get current enrollment
        // Logic similar to ProgramSelectorController to determine current enrollment
        $enrollmentId = Session::get('selected_enrollment_id');
        
        if ($enrollmentId) {
            $enrollment = StudentEnroll::find($enrollmentId);
        } else {
            // No enrollment selected - get the latest enrollment
            $enrollment = StudentEnroll::where('student_id', $user->id)
                        ->orderBy('id', 'desc')
                        ->first();
        }

        if (!$enrollment) {
            return $next($request);
        }

        // Check if it is First Semester (Type 1)
        $semester = $enrollment->semester;

        // Check if it is First Year, First Semester
        // Assuming 'year' is 1 for First Year and 'semester_type' is TYPE_FIRST (1)
        if ($semester && $semester->year == 1 && $semester->semester_type == Semester::TYPE_FIRST) {
            
            // Skip if it is a resit semester
            if ($semester->is_resit) {
                return $next($request);
            }

            // Check if bypass is enabled for this specific enrollment (program-specific)
            if ($enrollment->bypass_payment_restriction) {
                return $next($request);
            }

            // Check if First Installment is paid
            // Find Fee Category for First Installment
            $firstInstallmentCategory = FeesCategory::where('is_first_installment', 1)->first();

            if ($firstInstallmentCategory) {
                $isPaid = Fee::where('student_enroll_id', $enrollment->id)
                            ->where('category_id', $firstInstallmentCategory->id)
                            ->where('status', '1') // 1 = Paid
                            ->exists();

                if (!$isPaid) {
                    // Allow access to Fees and Payment related routes
                    if ($request->is('student/fees*') || 
                        $request->is('student/manual-payment*') || 
                        $request->is('student/multi-payment*') || 
                        $request->is('student/payment-plan*') ||
                        $request->is('student/platform-fee*') ||
                        $request->is('student/logout') ||
                        $request->is('student/select-program') || // Allow program selection
                        $request->is('student/switch-program')
                        ) {
                        return $next($request);
                    }

                    // Redirect to Fees page
                    return redirect()->route('student.fees.index')->with('error', 'You must pay your First Installment fees to access the portal.');
                }
            }
        }

        return $next($request);
    }
}
