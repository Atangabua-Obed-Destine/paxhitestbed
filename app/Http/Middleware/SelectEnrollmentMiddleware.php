<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentEnroll;
use App\Support\EnrolmentOrder;
use Symfony\Component\HttpFoundation\Response;

class SelectEnrollmentMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * This middleware manages which program enrollment is currently active for the student.
     * Students with multiple program enrollments (e.g., Bachelor + Masters) need to select
     * which program's data they want to view.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to authenticated students
        if (!Auth::guard('student')->check()) {
            return $next($request);
        }

        $student = Auth::guard('student')->user();

        // Get ALL enrollments (active and inactive) for this student
        // This allows students to view data from past/inactive programs
        $allEnrollments = StudentEnroll::where('student_id', $student->id)
                                    ->with(['program.degreeType', 'program.faculty', 'semester', 'session'])
                                    ->orderBy('id', 'desc')
                                    ->get();

        // One enrollment per matricule: the one the student has reached.
        //
        // This used to be `sortByDesc('id')->first()` — the most recently
        // created row. That is a proxy for academic position and a wrong one,
        // because it assumes rows are always created in academic order. A
        // back-dated enrollment breaks it: a resit sitting raised for Year 1
        // Semester 1, for a student already in Semester 2, is the newest row by
        // id and the earliest by academic position, so it was picked and the
        // student's whole portal moved back to the resit semester.
        //
        // EnrolmentOrder sorts by where the enrollment actually sits — session,
        // then semester year, then semester type, then regular before resit —
        // so creation order no longer decides it. Checked against the live data
        // when it was introduced: for all 86 matricule groups it picks the same
        // enrollment this line used to.
        $enrollments = EnrolmentOrder::furthestPerMatricule($allEnrollments);

        // If student has no active enrollments, continue (edge case)
        if ($enrollments->isEmpty()) {
            return $next($request);
        }

        // If student has only one unique enrollment, auto-select it
        if ($enrollments->count() === 1) {
            session(['selected_enrollment_id' => $enrollments->first()->id]);
            return $next($request);
        }

        // Multiple unique enrollments exist - check if one is selected
        $selectedEnrollmentId = session('selected_enrollment_id');

        // If no enrollment selected OR selected enrollment doesn't belong to this student's active enrollments
        if (!$selectedEnrollmentId || !$allEnrollments->contains('id', $selectedEnrollmentId)) {
            
            // Skip enrollment selection for these routes
            $skipRoutes = [
                'student.select-program',
                'student.switch-program',
                'student.logout',
            ];

            // Check if current route should skip selection
            $currentRoute = $request->route()->getName();
            if (in_array($currentRoute, $skipRoutes)) {
                return $next($request);
            }

            // Redirect to program selection page
            return redirect()->route('student.select-program');
        }

        // Valid enrollment selected - attach to request for easy access
        $selectedEnrollment = $allEnrollments->firstWhere('id', $selectedEnrollmentId);
        $request->merge(['selected_enrollment' => $selectedEnrollment]);

        return $next($request);
    }
}
