<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\ProgressionEligibilityService;
use App\Services\Academic\SemesterProgressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Flasher\Prime\FlasherInterface as Flasher;

class ProgressionController extends Controller
{
    protected $eligibilityService;
    protected $progressionService;

    public function __construct(
        ProgressionEligibilityService $eligibilityService,
        SemesterProgressionService $progressionService
    ) {
        $this->eligibilityService = $eligibilityService;
        $this->progressionService = $progressionService;
    }

    /**
     * Check if student is eligible for progression
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEligibility()
    {
        $student = Auth::guard('student')->user();
        
        // Get selected enrollment from session (for multi-program students)
        $selectedEnrollmentId = session('selected_enrollment_id');
        
        $eligibility = $this->eligibilityService->checkEligibility($student, $selectedEnrollmentId);

        // Add timestamp and debug info for troubleshooting
        $eligibility['checked_at'] = now()->toDateTimeString();
        $eligibility['student_id'] = $student->id ?? null;

        return response()->json($eligibility)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Process student-initiated progression
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function proceed(Request $request)
    {
        $student = Auth::guard('student')->user();
        
        // Get selected enrollment from session
        $selectedEnrollmentId = session('selected_enrollment_id');
        
        // Re-check eligibility with the selected enrollment
        $eligibility = $this->eligibilityService->checkEligibility($student, $selectedEnrollmentId);
        
        if (!$eligibility['eligible']) {
            return response()->json([
                'success' => false,
                'message' => __('You are not eligible for progression at this time.'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Use the enrollment from the eligibility check, not currentEnroll
            $currentEnrollment = \App\Models\StudentEnroll::find($eligibility['enrollment_id']);
            
            if (!$currentEnrollment) {
                throw new \Exception('No active enrollment found');
            }

            if ($eligibility['type'] === 'resit') {
                // Progress to resit semester
                $resitSemester = \App\Models\Semester::findOrFail($eligibility['target_semester_id']);
                
                $newEnrollment = $this->progressionService->progressToResitSemester(
                    $currentEnrollment,
                    $resitSemester,
                    $eligibility['target_session_id'],
                    $eligibility['summary']['scheduled_courses'] ?? []
                );
                
                $message = __('Successfully progressed to :semester', ['semester' => $resitSemester->title]);
                
            } elseif ($eligibility['type'] === 'regular') {
                // Progress to next regular semester
                $nextSemester = \App\Models\Semester::findOrFail($eligibility['target_semester_id']);
                
                $newEnrollment = $this->progressionService->progressToNextSemester(
                    $currentEnrollment,
                    $nextSemester
                );
                
                $message = __('Successfully progressed to :semester', ['semester' => $nextSemester->title]);
                
            } else {
                throw new \Exception('Invalid progression type');
            }

            DB::commit();
            
            // Update session to use the new enrollment
            session(['selected_enrollment_id' => $newEnrollment->id]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'new_enrollment_id' => $newEnrollment->id,
                'redirect_url' => route('student.dashboard.index'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => __('Progression failed: ') . $e->getMessage(),
            ], 500);
        }
    }
}
