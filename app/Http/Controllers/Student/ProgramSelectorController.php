<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentEnroll;
use Flasher\Laravel\Facade\Flasher;

class ProgramSelectorController extends Controller
{
    /**
     * Show the program selection page
     * 
     * This page allows students with multiple program enrollments
     * to choose which program they want to view
     */
    public function showSelectProgram()
    {
        $student = Auth::guard('student')->user();

        // Get ALL enrollments (active and inactive) with related data
        // This allows students to view data from past/inactive programs
        $allEnrollments = StudentEnroll::where('student_id', $student->id)
                                    ->with(['program.degreeType', 'program.faculty', 'semester', 'session', 'section'])
                                    ->orderBy('id', 'desc')
                                    ->get();

        // Group by unique matricule - keep only the latest enrollment for each matricule
        $enrollments = $allEnrollments->groupBy('matricule')->map(function($group) {
            return $group->sortByDesc('id')->first();
        })->values();

        // If only one unique enrollment, auto-select and redirect to dashboard
        if ($enrollments->count() === 1) {
            session(['selected_enrollment_id' => $enrollments->first()->id]);
            return redirect()->route('student.dashboard.index');
        }

        // Get currently selected enrollment if any
        $selectedEnrollmentId = session('selected_enrollment_id');

        return view('student.select-program', [
            'title' => 'Select Program',
            'student' => $student,
            'enrollments' => $enrollments,
            'selected_enrollment_id' => $selectedEnrollmentId,
        ]);
    }

    /**
     * Switch to a different program enrollment
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function switchProgram(Request $request)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:student_enrolls,id',
        ]);

        $student = Auth::guard('student')->user();
        $enrollmentId = $request->enrollment_id;

        // Verify the enrollment belongs to this student (allow both active and inactive)
        $enrollment = StudentEnroll::where('id', $enrollmentId)
                                   ->where('student_id', $student->id)
                                   ->with(['program.degreeType', 'program.faculty'])
                                   ->first();

        if (!$enrollment) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid enrollment selection'
                ], 400);
            }
            Flasher::addError('Invalid enrollment selection', 'Error');
            return redirect()->back();
        }

        // Update session
        session(['selected_enrollment_id' => $enrollmentId]);
        session()->save(); // Force session save

        // Success message with program details
        $programName = $enrollment->program->title ?? 'Unknown Program';
        $levelName = $enrollment->program->academic_level_name ?? '';
        $message = "Switched to {$programName}";
        if ($levelName) {
            $message .= " ({$levelName})";
        }

        Flasher::addSuccess($message, 'Program Switched');

        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'matricule' => $enrollment->matricule,
                'program_name' => $programName,
            ]);
        }

        // Redirect back or to dashboard
        return redirect()->back()->with('success', $message);
    }

    /**
     * Get current enrollment data (for AJAX calls)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentEnrollment()
    {
        $student = Auth::guard('student')->user();
        $enrollmentId = session('selected_enrollment_id');

        if (!$enrollmentId) {
            return response()->json(['error' => 'No enrollment selected'], 400);
        }

        $enrollment = StudentEnroll::where('id', $enrollmentId)
                                   ->where('student_id', $student->id)
                                   ->with(['program.degreeType', 'program.faculty', 'semester', 'session'])
                                   ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'Enrollment not found'], 404);
        }

        return response()->json([
            'enrollment_id' => $enrollment->id,
            'matricule' => $enrollment->matricule,
            'program_id' => $enrollment->program_id,
            'program_name' => $enrollment->program->title ?? '',
            'program_level' => $enrollment->program->academic_level ?? 'A',
            'program_level_name' => $enrollment->program->academic_level_name ?? 'Undergraduate',
            'faculty_name' => $enrollment->program->faculty->title ?? '',
            'semester_name' => $enrollment->semester->title ?? '',
            'session_name' => $enrollment->session->title ?? '',
        ]);
    }
}
