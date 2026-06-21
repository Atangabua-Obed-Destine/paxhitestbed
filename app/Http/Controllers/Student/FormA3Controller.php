<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session as LaravelSession;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\FormA3Record;
use App\Models\FormA3Setting;
use App\Models\ExamAttendanceSetting;
use App\Models\Setting;
use App\Models\Session;
use App\Models\Semester;

class FormA3Controller extends Controller
{
    /**
     * Display list of Form A3 records
     */
    public function index(Request $request)
    {
        $user = Auth::guard('student')->user();
        
        // Get selected enrollment from session
        $selectedEnrollmentId = LaravelSession::get('selected_enrollment_id');
        $selectedEnrollment = null;
        
        if ($selectedEnrollmentId) {
            $selectedEnrollment = StudentEnroll::with('program')->find($selectedEnrollmentId);
        }
        
        // If no selection, get the latest enrollment
        if (!$selectedEnrollment) {
            $selectedEnrollment = StudentEnroll::where('student_id', $user->id)
                ->with('program')
                ->orderBy('id', 'desc')
                ->first();
        }

        // Get enrollments for the SELECTED program only (same matricule = same program track)
        if ($selectedEnrollment) {
            $enrollments = StudentEnroll::where('student_id', $user->id)
                            ->where('matricule', $selectedEnrollment->matricule)
                            ->where('status', '1')
                            ->with('subjects')
                            ->get();
            
            $programEnrollIds = $enrollments->pluck('id')->toArray();
        } else {
            // Fallback to all enrollments
            $enrollments = StudentEnroll::where('student_id', $user->id)
                            ->where('status', '1')
                            ->with('subjects')
                            ->get();
            $programEnrollIds = $enrollments->pluck('id')->toArray();
        }

        // Check for missing Form A3 records and generate them (for selected program only)
        foreach ($enrollments as $enrollment) {
            if ($enrollment->subjects->count() > 0) {
                // Check if Form A3 exists
                $exists = FormA3Record::where('student_id', $user->id)
                            ->where('student_enroll_id', $enrollment->id)
                            ->exists();
                
                if (!$exists) {
                    // Generate it
                    self::generateFormA3($user, $enrollment, $enrollment->subjects);
                }
            }
        }

        $data['title'] = 'Form A3 Documents';
        $data['selected_enrollment'] = $selectedEnrollment;
        
        // Get sessions and semesters for the SELECTED program only
        if ($selectedEnrollment) {
            $data['sessions'] = StudentEnroll::where('student_id', $user->id)
                ->where('matricule', $selectedEnrollment->matricule)
                ->with('session')
                ->get()
                ->pluck('session')
                ->filter()
                ->unique('id')
                ->sortByDesc('id')
                ->values();
            
            $data['semesters'] = StudentEnroll::where('student_id', $user->id)
                ->where('matricule', $selectedEnrollment->matricule)
                ->with('semester')
                ->get()
                ->pluck('semester')
                ->filter()
                ->unique('id')
                ->sortBy('id')
                ->values();
        } else {
            $data['sessions'] = Session::orderBy('id', 'desc')->get();
            $data['semesters'] = Semester::orderBy('id', 'asc')->get();
        }
        
        // Get Form A3 records for the SELECTED program only
        $query = FormA3Record::where('student_id', $user->id)
                    ->whereIn('student_enroll_id', $programEnrollIds)
                    ->with(['session', 'semester', 'enrollment.program'])
                    ->orderBy('created_at', 'desc');
        
        // Filter by session if provided
        if ($request->has('session_id') && $request->session_id) {
            $query->where('session_id', $request->session_id);
        }
        
        // Filter by semester if provided
        if ($request->has('semester_id') && $request->semester_id) {
            $query->where('semester_id', $request->semester_id);
        }
        
        $data['records'] = $query->get();
        $data['selected_session'] = $request->session_id;
        $data['selected_semester'] = $request->semester_id;
        
        return view('student.form-a3.index', $data);
    }

    /**
     * Download/View Form A3 PDF
     */
    public function download(Request $request, $id)
    {
        $user = Auth::guard('student')->user();
        
        // Find the Form A3 record
        $record = FormA3Record::where('id', $id)
                    ->where('student_id', $user->id)
                    ->with(['session', 'semester', 'enrollment.program.faculty'])
                    ->first();
        
        if (!$record) {
            return redirect()->route('student.form-a3.index')
                        ->with('error', 'Form A3 record not found.');
        }

        $data['student'] = $user;
        $data['record'] = $record;
        $data['enrollment'] = $record->enrollment;
        $data['subjects'] = $record->subjects_snapshot ?? [];
        $data['totalCredits'] = $record->total_credits;
        $data['settings'] = FormA3Setting::first();
        $data['generalSetting'] = Setting::first();
        
        // Override with stored names if available
        if ($record->hnd_coordinator_name) {
            $data['hnd_coordinator_name'] = $record->hnd_coordinator_name;
        } else {
            $data['hnd_coordinator_name'] = $data['settings']->hnd_coordinator_name ?? 'HND Coordinator';
        }
        
        if ($record->dir_acad_name) {
            $data['dir_acad_name'] = $record->dir_acad_name;
        } else {
            $data['dir_acad_name'] = $data['settings']->dir_acad_name ?? 'Director of Academics';
        }
        
        // Check if preview mode
        $data['is_preview'] = $request->has('preview');
        
        // Get attendance eligibility setting
        $attendanceSetting = ExamAttendanceSetting::first();
        $data['attendance_percentage'] = $attendanceSetting->minimum_attendance_percentage ?? 75;
        $data['attendance_enabled'] = $attendanceSetting->is_enabled ?? true;

        return view('student.form-a3.pdf', $data);
    }

    /**
     * Generate Form A3 record when student registers courses
     * This is called from CourseRegistrationController
     */
    public static function generateFormA3($student, $enrollment, $subjects)
    {
        // Get current settings
        $settings = FormA3Setting::first();
        
        // Prepare subjects snapshot
        $subjectsSnapshot = [];
        $totalCredits = 0;
        
        foreach ($subjects as $subject) {
            $subjectsSnapshot[] = [
                'code' => $subject->code ?? '',
                'title' => $subject->title ?? '',
                'credits' => $subject->credit_hour ?? 0,
                'type' => $subject->subject_type ?? 0,
            ];
            $totalCredits += (int)($subject->credit_hour ?? 0);
        }
        
        // Check if Form A3 already exists for this enrollment and semester
        $existingRecord = FormA3Record::where('student_id', $student->id)
                            ->where('student_enroll_id', $enrollment->id)
                            ->where('session_id', $enrollment->session_id)
                            ->where('semester_id', $enrollment->semester_id)
                            ->first();
        
        if ($existingRecord) {
            // Update existing record
            $existingRecord->update([
                'subjects_snapshot' => $subjectsSnapshot,
                'total_credits' => $totalCredits,
                'hnd_coordinator_name' => $settings->hnd_coordinator_name ?? null,
                'dir_acad_name' => $settings->dir_acad_name ?? null,
            ]);
            return $existingRecord;
        }
        
        // Create new Form A3 record
        return FormA3Record::create([
            'student_id' => $student->id,
            'student_enroll_id' => $enrollment->id,
            'session_id' => $enrollment->session_id,
            'semester_id' => $enrollment->semester_id,
            'subjects_snapshot' => $subjectsSnapshot,
            'total_credits' => $totalCredits,
            'hnd_coordinator_name' => $settings->hnd_coordinator_name ?? null,
            'dir_acad_name' => $settings->dir_acad_name ?? null,
        ]);
    }
}
