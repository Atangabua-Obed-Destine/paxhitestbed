<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentEnroll;
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

        // Group by unique matricule - keep only latest enrollment for each matricule
        $enrollments = $allEnrollments->groupBy('matricule')->map(function($group) {
            return $group->sortByDesc('id')->first();
        })->values();

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
