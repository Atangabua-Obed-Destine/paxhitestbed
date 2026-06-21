<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Session;
use App\Models\Semester;
use App\Models\FormA3Record;
use App\Models\FormA3Setting;
use App\Models\ExamAttendanceSetting;
use App\Models\Setting;
use App\Models\StudentEnroll;
use App\Services\StaffAssignmentService;
use App\Http\Controllers\Student\FormA3Controller as StudentSideFormA3Controller;
use Illuminate\Support\Facades\Log;

class StudentFormA3Controller extends Controller
{
    /**
     * Display listing of Form A3 records
     */
    public function index(Request $request)
    {
        $data['title'] = 'Student Form A3';
        
        // Get faculties (filtered by staff assignment if applicable)
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        
        // Get all sessions and semesters
        $data['sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', 1)->orderBy('id', 'asc')->get();
        
        // Initialize selected values
        $data['selected_faculty'] = $selectedFaculty = $request->faculty ?? '';
        $data['selected_program'] = $selectedProgram = $request->program ?? '';
        $data['selected_session'] = $selectedSession = $request->session ?? '';
        $data['selected_semester'] = $selectedSemester = $request->semester ?? '';
        
        // Load programs when faculty is selected
        $data['programs'] = collect();
        if (!empty($selectedFaculty)) {
            $data['programs'] = Program::where('faculty_id', $selectedFaculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }
        
        // Backfill: auto-generate Form A3 records for any enrollment that has
        // registered subjects but no FormA3Record yet (mirrors the lazy-create
        // logic on the student-side page so admins see all eligible students).
        $this->backfillMissingFormA3Records($selectedFaculty, $selectedProgram, $selectedSession, $selectedSemester);

        // Build Form A3 records query - show all by default
        $recordsQuery = FormA3Record::with([
                'student', 
                'enrollment.program.faculty', 
                'session', 
                'semester'
            ])
            ->whereHas('student', function($query) {
                $query->where('status', '!=', 0); // Exclude disabled students
            })
            ->whereHas('enrollment', function($query) {
                $query->whereNotNull('matricule') // Only enrollments with matricule
                      ->where('status', 1); // Only active enrollments
            });
        
        // Apply faculty filter
        if (!empty($selectedFaculty)) {
            $recordsQuery->whereHas('enrollment.program', function($query) use ($selectedFaculty) {
                $query->where('faculty_id', $selectedFaculty);
            });
        }
        
        // Apply program filter
        if (!empty($selectedProgram)) {
            $recordsQuery->whereHas('enrollment', function($query) use ($selectedProgram) {
                $query->where('program_id', $selectedProgram);
            });
        }
        
        // Apply session filter
        if (!empty($selectedSession)) {
            $recordsQuery->where('session_id', $selectedSession);
        }
        
        // Apply semester filter
        if (!empty($selectedSemester)) {
            $recordsQuery->where('semester_id', $selectedSemester);
        }
        
        $data['records'] = $recordsQuery->orderBy('created_at', 'desc')->get();

        // Build a lookup map of active result-access blocks for the displayed
        // records, keyed by "studentId|programId|sessionId|semesterId" so the
        // view can render the correct toggle state per row in O(1).
        $blockMap = [];
        if ($data['records']->isNotEmpty()) {
            $studentIds = $data['records']->pluck('student_id')->unique()->filter()->values();
            $blocks = \App\Models\ResultAccessBlock::query()
                ->whereIn('student_id', $studentIds)
                ->where('is_active', true)
                ->get();
            foreach ($blocks as $b) {
                $blockMap[$b->student_id . '|' . $b->program_id . '|' . $b->session_id . '|' . $b->semester_id] = $b;
            }
        }
        $data['blockMap'] = $blockMap;

        return view('admin.student-form-a3.index', $data);
    }

    /**
     * Auto-generate FormA3Record rows for any active enrollment that has
     * registered subjects but no record yet. Scoped by the same filters used
     * on the page so we don't scan the entire DB needlessly.
     */
    protected function backfillMissingFormA3Records($faculty, $program, $session, $semester)
    {
        $query = StudentEnroll::with(['student', 'subjects'])
            ->whereNotNull('matricule')
            ->where('status', 1)
            ->whereHas('student', function ($q) {
                $q->where('status', '!=', 0);
            });
            // NOTE: we intentionally do NOT filter by whereHas('subjects') so that
            // a Form A3 record is created even for students who have not yet
            // registered any courses — the form will then surface in the admin
            // list with zero subjects, indicating non-registration.

        if (!empty($faculty)) {
            $query->whereHas('program', function ($q) use ($faculty) {
                $q->where('faculty_id', $faculty);
            });
        }
        if (!empty($program)) {
            $query->where('program_id', $program);
        }
        if (!empty($session)) {
            $query->where('session_id', $session);
        }
        if (!empty($semester)) {
            $query->where('semester_id', $semester);
        }

        // Pull existing record keys to skip in one query
        $existing = FormA3Record::query()
            ->select('student_enroll_id')
            ->pluck('student_enroll_id')
            ->flip();

        $query->chunk(200, function ($enrollments) use ($existing) {
            foreach ($enrollments as $enrollment) {
                if (isset($existing[$enrollment->id])) {
                    continue;
                }
                if (!$enrollment->student) {
                    continue;
                }
                try {
                    StudentSideFormA3Controller::generateFormA3(
                        $enrollment->student,
                        $enrollment,
                        $enrollment->subjects // may be empty — record will show 0 credits
                    );
                } catch (\Throwable $e) {
                    Log::warning('Form A3 backfill failed for enrollment ' . $enrollment->id . ': ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * Preview Form A3 for a specific record
     */
    public function preview(Request $request, $recordId)
    {
        $record = FormA3Record::with([
            'student', 
            'enrollment.program.faculty', 
            'session', 
            'semester'
        ])->findOrFail($recordId);
        
        $data['student'] = $record->student;
        $data['record'] = $record;
        $data['enrollment'] = $record->enrollment;
        $data['subjects'] = $record->subjects_snapshot ?? [];
        $data['totalCredits'] = $record->total_credits;
        $data['settings'] = FormA3Setting::first();
        $data['generalSetting'] = Setting::first();
        
        // Override with stored names if available
        $data['hnd_coordinator_name'] = $record->hnd_coordinator_name 
            ?? ($data['settings']->hnd_coordinator_name ?? 'HND Coordinator');
        $data['dir_acad_name'] = $record->dir_acad_name 
            ?? ($data['settings']->dir_acad_name ?? 'Director of Academics');
        
        // Get attendance eligibility setting
        $attendanceSetting = ExamAttendanceSetting::first();
        $data['attendance_percentage'] = $attendanceSetting->minimum_attendance_percentage ?? 75;
        $data['attendance_enabled'] = $attendanceSetting->is_enabled ?? true;
        
        $data['is_preview'] = true;
        $data['is_admin'] = true;
        
        return view('admin.student-form-a3.pdf', $data);
    }
    
    /**
     * Download/Print Form A3 for a specific record
     */
    public function download(Request $request, $recordId)
    {
        $record = FormA3Record::with([
            'student', 
            'enrollment.program.faculty', 
            'session', 
            'semester'
        ])->findOrFail($recordId);
        
        $data['student'] = $record->student;
        $data['record'] = $record;
        $data['enrollment'] = $record->enrollment;
        $data['subjects'] = $record->subjects_snapshot ?? [];
        $data['totalCredits'] = $record->total_credits;
        $data['settings'] = FormA3Setting::first();
        $data['generalSetting'] = Setting::first();
        
        $data['hnd_coordinator_name'] = $record->hnd_coordinator_name 
            ?? ($data['settings']->hnd_coordinator_name ?? 'HND Coordinator');
        $data['dir_acad_name'] = $record->dir_acad_name 
            ?? ($data['settings']->dir_acad_name ?? 'Director of Academics');
        
        $attendanceSetting = ExamAttendanceSetting::first();
        $data['attendance_percentage'] = $attendanceSetting->minimum_attendance_percentage ?? 75;
        $data['attendance_enabled'] = $attendanceSetting->is_enabled ?? true;
        
        $data['is_preview'] = false;
        $data['is_admin'] = true;
        
        return view('admin.student-form-a3.pdf', $data);
    }
    
    /**
     * Bulk preview/download multiple Form A3s
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'record_ids' => 'required|array',
            'record_ids.*' => 'exists:form_a3_records,id',
        ]);
        
        $records = FormA3Record::with([
            'student', 
            'enrollment.program.faculty', 
            'session', 
            'semester'
        ])->whereIn('id', $request->record_ids)->get();
        
        $data['forms'] = [];
        $settings = FormA3Setting::first();
        $attendanceSetting = ExamAttendanceSetting::first();
        
        foreach ($records as $record) {
            $data['forms'][] = [
                'student' => $record->student,
                'record' => $record,
                'enrollment' => $record->enrollment,
                'subjects' => $record->subjects_snapshot ?? [],
                'totalCredits' => $record->total_credits,
                'hnd_coordinator_name' => $record->hnd_coordinator_name 
                    ?? ($settings->hnd_coordinator_name ?? 'HND Coordinator'),
                'dir_acad_name' => $record->dir_acad_name 
                    ?? ($settings->dir_acad_name ?? 'Director of Academics'),
            ];
        }
        
        $data['settings'] = $settings;
        $data['generalSetting'] = Setting::first();
        $data['attendance_percentage'] = $attendanceSetting->minimum_attendance_percentage ?? 75;
        $data['attendance_enabled'] = $attendanceSetting->is_enabled ?? true;
        $data['is_preview'] = $request->has('preview');
        $data['is_admin'] = true;
        
        return view('admin.student-form-a3.bulk-pdf', $data);
    }

    /**
     * Public verification of Form A3 via QR code / URL
     * No authentication required
     */
    public function verify($code)
    {
        $data['title'] = 'Verify Form A3';

        $record = FormA3Record::where('verification_code', $code)
            ->with([
                'student',
                'enrollment.program.faculty',
                'session',
                'semester',
            ])
            ->first();

        if (!$record) {
            $data['record_found'] = false;
            $data['verification_code'] = $code;
        } else {
            $data['record_found'] = true;
            $data['record'] = $record;
            $data['student'] = $record->student;
            $data['enrollment'] = $record->enrollment;
            $data['subjects'] = $record->subjects_snapshot ?? [];
            $data['totalCredits'] = $record->total_credits;
            $data['generalSetting'] = Setting::first();
        }

        return view('verify-form-a3', $data);
    }

    /**
     * Toggle a student's ability to view results for a specific
     * (program + session + semester) combination, scoped by the Form A3
     * record selected in the admin list. When blocked, the student-side
     * exam results page and the matching semester block on the transcript
     * will display a withheld-results notice instead of the actual marks.
     */
    public function toggleResultAccess(Request $request, $recordId)
    {
        $record = FormA3Record::with('enrollment')->findOrFail($recordId);
        $enrollment = $record->enrollment;

        if (!$enrollment || !$enrollment->program_id) {
            return back()->with('error', 'Cannot toggle access: enrollment program is missing for this record.');
        }

        $studentId  = $record->student_id;
        $programId  = $enrollment->program_id;
        $sessionId  = $record->session_id;
        $semesterId = $record->semester_id;

        $existing = \App\Models\ResultAccessBlock::activeBlockFor($studentId, $programId, $sessionId, $semesterId);
        $adminId  = optional(\Illuminate\Support\Facades\Auth::user())->id;

        if ($existing) {
            // Currently blocked → unblock
            $existing->update([
                'is_active'      => false,
                'unblocked_by'   => $adminId,
                'unblocked_at'   => now(),
                'unblock_reason' => trim((string)$request->input('reason')) ?: null,
            ]);
            return back()->with('success', 'Result access restored for this student / semester.');
        }

        // Not blocked → create a new block
        $reason = trim((string)$request->input('reason'));
        if ($reason === '') {
            return back()->with('error', 'A reason is required when blocking result access.');
        }

        \App\Models\ResultAccessBlock::create([
            'student_id'  => $studentId,
            'program_id'  => $programId,
            'session_id'  => $sessionId,
            'semester_id' => $semesterId,
            'is_active'   => true,
            'reason'      => $reason,
            'blocked_by'  => $adminId,
            'blocked_at'  => now(),
        ]);

        return back()->with('success', 'Result access has been blocked for this student / semester.');
    }
}
