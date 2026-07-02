<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use App\Models\StudentEnroll;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use App\Imports\MarksImport;
use App\Models\ExamType;
use App\Models\ExamRoutine;
use App\Models\ClassRoutine;
use App\Models\Semester;
use App\Models\Program;
use App\Models\Session;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Exam;
use App\Models\ExamAttendanceSetting;
use App\User;

class ExamAttendanceController extends Controller
{
    protected $title, $route, $view, $path, $access;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_exam_attendance', 1);
        $this->route = 'admin.exam-attendance';
        $this->view = 'admin.exam';
        $this->path = 'exam';
        $this->access = 'exam';


        $this->middleware('permission:'.$this->access.'-attendance', ['only' => ['index','store','printSheet']]);
        $this->middleware('permission:'.$this->access.'-import', ['only' => ['index','import','importStore']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get current session and teacher's assigned courses for quick access
        $data['myAssignedCourses'] = $this->getMyAssignedCourses();
        $data['examTypes'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['currentSession'] = Session::where('status', '1')->where('current', '1')->first();

        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = null;
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = null;
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = null;
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = null;
        }

        if(!empty($request->subject) || $request->subject != null){
            $data['selected_subject'] = $subject = $request->subject;
        }
        else{
            $data['selected_subject'] = null;
        }

        if(!empty($request->type) || $request->type != null){
            $data['selected_type'] = $type = $request->type;
        }
        else{
            $data['selected_type'] = '0';
        }

        // Cross-programme toggle: include students from ALL programmes taking this course
        $crossProgram = $request->boolean('cross_program', false);
        $data['cross_program'] = $crossProgram;


        // Search Filter
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['types'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();

        // Access Data - define early for use in multiple blocks
        $authUser = Auth::guard('web')->user();
        $teacher_id = $authUser->id;
        $superAdmin = $authUser->hasRole('Super Admin');

        if(!empty($request->faculty) && $request->faculty != '0'){
        $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $semesters = Semester::where('status', 1);
        $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['semesters'] = $semesters->orderBy('id', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0'){
        $sections = Section::where('status', 1);
        $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
            $query->where('program_id', $program);
            $query->where('semester_id', $semester);
        });
        $data['sections'] = $sections->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0' && !empty($request->session) && $request->session != '0'){
            // Get subject IDs where user is assigned as exam routine supervisor
            $examRoutineSupervisorSubjectIds = [];
            if(!$superAdmin){
                $examRoutineSupervisorSubjectIds = ExamRoutine::where('session_id', $session)
                    ->where('program_id', $program)
                    ->where('status', 1)
                    ->whereHas('users', function($q) use ($teacher_id) {
                        $q->where('user_id', $teacher_id);
                    })
                    ->pluck('subject_id')
                    ->unique()
                    ->toArray();
            }

            // Filter Subject - include both class teachers and exam routine supervisors
            $subjects = Subject::where('status', '1');
            $subjects->where(function($query) use ($teacher_id, $session, $superAdmin, $examRoutineSupervisorSubjectIds) {
                // Include subjects where user is class teacher
                $query->whereHas('classes', function ($q) use ($teacher_id, $session, $superAdmin){
                    if(isset($session)){
                        $q->where('session_id', $session);
                    }
                    if(!$superAdmin){
                        $q->where('teacher_id', $teacher_id);
                    }
                });
                
                // Also include subjects where user is exam routine supervisor
                if(!empty($examRoutineSupervisorSubjectIds)){
                    $query->orWhereIn('id', $examRoutineSupervisorSubjectIds);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }


        // Filter Student
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject)){

            // Check Subject Access - allow if user is class teacher OR exam routine supervisor
            $hasAccess = false;
            
            // Check if super admin
            if(isset($superAdmin)){
                $hasAccess = true;
            }
            
            // Check if class teacher
            if(!$hasAccess){
                $classTeacherAccess = Subject::where('id', $subject)
                    ->whereHas('classes', function ($query) use ($teacher_id, $session){
                        if(isset($session)){
                            $query->where('session_id', $session);
                        }
                        $query->where('teacher_id', $teacher_id);
                    })->exists();
                if($classTeacherAccess) $hasAccess = true;
            }
            
            // Check if exam routine supervisor for this subject
            if(!$hasAccess){
                $examRoutineAccess = ExamRoutine::where('session_id', $session)
                    ->where('program_id', $program)
                    ->where('subject_id', $subject)
                    ->where('status', 1)
                    ->whereHas('users', function($q) use ($teacher_id) {
                        $q->where('user_id', $teacher_id);
                    })->exists();
                if($examRoutineAccess) $hasAccess = true;
            }
            
            if(!$hasAccess){
                abort(403, 'You are not authorized to access this subject. You must be either the class teacher or an exam supervisor.');
            }

            // ── Find all programmes that share this subject ──
            $subjectModel = Subject::find($subject);
            $data['sharing_programs'] = $subjectModel
                ? $subjectModel->programs()->with('faculty')->where('programs.status', '1')->orderBy('programs.title')->get()
                : collect();


            // Enrolls
            $enrolls = StudentEnroll::where('id', '!=', null);

            if ($crossProgram) {
                // Cross-programme mode: skip program/section filters but keep session and semester
                if(!empty($request->session) && $request->session != '0'){
                    $enrolls->where('session_id', $session);
                }
                if(!empty($request->semester) && $request->semester != '0'){
                    $enrolls->where('semester_id', $semester);
                }
                // Restrict to programmes that actually share this subject
                $sharingProgramIds = $data['sharing_programs']->pluck('id')->toArray();
                if (!empty($sharingProgramIds)) {
                    $enrolls->whereIn('program_id', $sharingProgramIds);
                }
            } else {
                // Normal mode: filter by all criteria
                if(!empty($request->program) && $request->program != '0'){
                    $enrolls->where('program_id', $program);
                }
                if(!empty($request->session) && $request->session != '0'){
                    $enrolls->where('session_id', $session);
                }
                if(!empty($request->semester) && $request->semester != '0'){
                    $enrolls->where('semester_id', $semester);
                }
                if(!empty($request->section) && $request->section != '0'){
                    $enrolls->where('section_id', $section);
                }
            }

            if(!empty($request->subject) && $request->subject != '0'){
                $enrolls->with('subjects')->whereHas('subjects', function ($query) use ($subject){
                    $query->where('subject_id', $subject);
                });
            }
            $enrolls->with(['student', 'program.faculty', 'semester', 'section'])->whereHas('student', function ($query){
                $query->where('status', '1');
                $query->orderBy('matricule', 'asc');
            });
            $rows = $enrolls->get();

            if ($crossProgram) {
                // Cross-programme: deduplicate per matricule PER PROGRAM (same student in different programmes = valid)
                $uniqueMatricules = $rows->groupBy(function($row) {
                    return $row->program_id . '-' . $row->matricule;
                })->map(function($group) {
                    return $group->sortByDesc('id')->first();
                })->values();

                // Sort by programme name, then matricule
                $data['rows'] = $uniqueMatricules->sortBy(function($row){
                    return ($row->program->title ?? '') . '_' . $row->matricule;
                })->all();
            } else {
                // Normal: one enrollment per matricule
                $uniqueMatricules = $rows->groupBy('matricule')->map(function($group) {
                    return $group->sortByDesc('id')->first();
                })->values();

                // Array Sorting
                $data['rows'] = $uniqueMatricules->sortBy(function($query){
                    return $query->matricule;
                })->all();
            }
        }


        // Exam attendances
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject) && !empty($request->type)){

            $attendances = Exam::where('id', '!=', null);

            if ($crossProgram) {
                // Cross-programme: filter by session and semester (not program/section)
                $attendances->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($session, $semester){
                    if($session != '0'){
                        $query->where('session_id', $session);
                    }
                    if($semester != '0'){
                        $query->where('semester_id', $semester);
                    }
                });
            } else {
                if(!empty($request->program) && !empty($request->session)){
                    $attendances->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                        if($program != '0'){
                            $query->where('program_id', $program);
                        }
                        if($session != '0'){
                            $query->where('session_id', $session);
                        }
                        if($semester != '0'){
                            $query->where('semester_id', $semester);
                        }
                        if($section != '0'){
                            $query->where('section_id', $section);
                        }
                    });
                }
            }
            if(!empty($request->subject) && $request->subject != '0'){
                $attendances->where('subject_id', $subject);
            }
            if(!empty($request->type) && $request->type != '0'){
                $attendances->where('exam_type_id', $type);
            }

            $data['attendances'] = $attendances->orderBy('id', 'desc')->get();
        }

        // Get exam attendance eligibility settings
        $data['attendance_setting'] = ExamAttendanceSetting::first();
        
        // Get selected exam type to check if it's a final exam
        $data['selected_exam_type'] = null;
        if(!empty($request->type) && $request->type != '0'){
            $data['selected_exam_type'] = ExamType::find($request->type);
        }
        
        // Check if exam is scheduled in exam routine (only required for final exams)
        $data['exam_scheduled'] = false;
        $data['exam_routine'] = null;
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject) && !empty($request->type)){
            $examRoutineQuery = ExamRoutine::where('exam_type_id', $request->type)
                ->where('session_id', $request->session)
                ->where('subject_id', $request->subject)
                ->where('status', 1);

            if ($crossProgram) {
                // Cross-programme: check if exam is scheduled in ANY sharing programme
            } else {
                $examRoutineQuery->where('program_id', $request->program);
            }
            
            // Optionally filter by semester and section if provided (only in normal mode)
            if(!$crossProgram){
                if(!empty($request->semester) && $request->semester != '0'){
                    $examRoutineQuery->where('semester_id', $request->semester);
                }
                if(!empty($request->section) && $request->section != '0'){
                    $examRoutineQuery->where('section_id', $request->section);
                }
            }
            
            $data['exam_routine'] = $examRoutineQuery->first();
            $data['exam_scheduled'] = $data['exam_routine'] !== null;
        }
        
        // Determine if attendance can be taken
        // For non-final exams (is_final = 0): Always allow attendance
        // For final exams (is_final = 1): Only allow if exam is scheduled
        $data['can_take_attendance'] = true;
        if(isset($data['selected_exam_type']) && $data['selected_exam_type'] && $data['selected_exam_type']->is_final == 1){
            // Final exam - require scheduling
            $data['can_take_attendance'] = $data['exam_scheduled'];
        }
        
        // Calculate course attendance percentage for each student (only for final exams)
        if(!empty($data['rows']) && !empty($request->subject) && !empty($data['selected_exam_type']) && $data['selected_exam_type']->is_final == 1){
            $data['student_attendance_percentages'] = [];

            foreach($data['rows'] as $row){
                $data['student_attendance_percentages'][$row->id] = $this->courseAttendanceStats($row->id, $request->subject);
            }
        }

        // CA marks reference (final-exam attendance only): one read-only column per
        // non-final exam type configured for this subject, showing the achieved marks.
        $data['ca_types'] = collect();
        $data['ca_marks'] = [];
        if(!empty($data['rows']) && !empty($request->subject) && !empty($data['selected_exam_type']) && $data['selected_exam_type']->is_final == 1){
            $data['ca_types'] = ExamType::where('is_final', 0)->where('status', 1)
                ->whereHas('exams', function($q) use ($subject){ $q->where('subject_id', $subject); })
                ->orderBy('id')->get();

            $enrollIds = collect($data['rows'])->pluck('id')->all();
            if($data['ca_types']->isNotEmpty() && !empty($enrollIds)){
                $caRows = Exam::where('subject_id', $subject)
                    ->whereIn('exam_type_id', $data['ca_types']->pluck('id')->all())
                    ->whereIn('student_enroll_id', $enrollIds)
                    ->get();
                foreach($caRows as $ex){
                    $data['ca_marks'][$ex->student_enroll_id][$ex->exam_type_id] = [
                        'achieve' => $ex->achieve_marks,
                        'marks'   => $ex->marks,
                    ];
                }
            }
        }


        return view($this->view.'.attendance', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'subject' => 'required',
            'type' => 'required',
            'date' => 'required|date|before_or_equal:today',
            'attendances' => 'required',
            'students' => 'required',
        ]);

        // Block submission if mark distribution is not configured for this course
        if (!\App\Services\ResultContributionService::isConfigured($request->subject)) {
            Flasher::addError('Mark distribution has not been configured for this course. Please configure it before taking exam attendance.', 'Error');
            return redirect()->back();
        }

        $attendances = explode(",",$request->attendances);
        $bypasses = $request->bypasses ? explode(",",$request->bypasses) : [];

        // ── Hoist repeated queries out of the loop ──
        $examType = ExamType::where('id', $request->type)->firstOrFail();

        // Final-exam invigilation: Sign In / Sign Out drive the attendance.
        // present (attendance = 1) is derived as sign_in AND sign_out.
        $isFinal = (bool) ($examType->is_final ?? false);
        $signins  = $isFinal && $request->filled('signins')  ? explode(",", $request->signins)  : [];
        $signouts = $isFinal && $request->filled('signouts') ? explode(",", $request->signouts) : [];

        $contribution = \App\Services\ResultContributionService::getExamTypeContribution(
            $request->subject,
            $request->type
        );

        $currentUserId = Auth::guard('web')->id();

        // ── Pre-load existing exam records for all students in one query ──
        $existingExams = Exam::where('subject_id', $request->subject)
            ->where('exam_type_id', $request->type)
            ->whereIn('student_enroll_id', $request->students)
            ->get()
            ->keyBy('student_enroll_id');

        $savedCount  = 0;
        $lockedCount = 0;

        // Eligibility enforcement (final exam): a bypass is only honoured if the
        // current user actually holds the bypass permission.
        $attendanceSetting = ExamAttendanceSetting::first();
        $canBypass = Auth::user() ? Auth::user()->can('exam-attendance-bypass') : false;

        // ── Wrap in a DB transaction for atomicity ──
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Insert Data
            foreach($request->students as $key => $student_id){

                // Bypass only takes effect when the user is permitted to bypass.
                $bypass_enabled = $canBypass && in_array($student_id, $bypasses);

                // Check lock status using pre-loaded records
                $existingExam = $existingExams->get($student_id);

                if ($existingExam && $existingExam->attendance_locked) {
                    $lockedCount++;
                    continue; // Skip locked records
                }

                // For the final exam, attendance is DERIVED from the two sign verifications:
                // present (1) only when signed in AND signed out; sign-out requires sign-in.
                $attendanceValue = $attendances[$key] ?? 2;
                $values = [
                    'student_enroll_id' => $student_id,
                    'subject_id' => $request->subject,
                    'exam_type_id' => $request->type,
                    'date' => $request->date,
                    'marks' => $examType->marks,
                    'contribution' => $contribution,
                    'attendance_locked' => 1, // Lock upon saving
                    'bypass_course_attendance' => $bypass_enabled,
                    'bypassed_by' => $bypass_enabled ? $currentUserId : null,
                    'bypassed_at' => $bypass_enabled ? now() : null,
                    'created_by' => $currentUserId
                ];

                if ($isFinal) {
                    $si = !empty($signins[$key]);
                    $so = $si && !empty($signouts[$key]); // sign-out only counts if signed in

                    // Enforce eligibility server-side: an ineligible, non-bypassed student
                    // is forced Absent regardless of what was submitted.
                    $eligible = $this->isCourseAttendanceEligible($student_id, $request->subject, $attendanceSetting);
                    if (!$eligible && !$bypass_enabled) {
                        $si = false;
                        $so = false;
                    }

                    $attendanceValue = ($si && $so) ? 1 : 2;
                    $values['sign_in'] = $si;
                    $values['sign_out'] = $so;
                }

                $values['attendance'] = $attendanceValue;
                $attendances[$key] = $attendanceValue; // keep the success-modal counts accurate

                // Insert Or Update Data
                $exam = Exam::updateOrCreate(
                [
                    'student_enroll_id' => $student_id,
                    'subject_id' => $request->subject,
                    'exam_type_id' => $request->type
                ], $values);

                $savedCount++;
            }

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            Flasher::addError('An error occurred while saving attendance. Please try again. (' . class_basename($e) . ')', 'Error');
            return redirect()->back();
        }

        // Sync exam data to subject_markings so Subject Result page reflects updated data
        $syncedStudents = collect($request->students)->unique();
        foreach ($syncedStudents as $studentEnrollId) {
            \App\Services\ExamMarksSyncService::sync((int)$studentEnrollId, (int)$request->subject);
        }

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        // Flash detailed data so the view can show a prominent success modal
        $subjectModel = \App\Models\Subject::find($request->subject);
        session()->flash('attendance_saved', [
            'count'   => $savedCount,
            'locked'  => $lockedCount,
            'subject' => ($subjectModel->code ?? '') . ' - ' . ($subjectModel->title ?? ''),
            'type'    => $examType->title ?? '',
            'date'    => $request->date,
            'present' => collect($attendances)->filter(fn($v) => $v == '1')->count(),
            'absent'  => collect($attendances)->filter(fn($v) => $v == '0')->count(),
        ]);

        return redirect()->back();
    }

    /**
     * Print a blank manual invigilation sheet (Sign In / Sign Out columns left
     * blank for handwriting). Mirrors the screen's student gathering + eligibility
     * (respecting All Programmes Mode), and pre-fills Course Attendance + Eligibility.
     */
    public function printSheet(Request $request)
    {
        $subject = $request->subject;
        $type    = $request->type;
        $program = $request->program;
        $session = $request->session;
        $semester = $request->semester;
        $section = $request->section;
        $crossProgram = $request->boolean('cross_program', false);

        if (empty($subject) || empty($type)) {
            Flasher::addError('Select a subject and exam type before printing the sheet.', 'Error');
            return redirect()->back();
        }

        $subjectModel = Subject::find($subject);
        $sharingPrograms = $subjectModel
            ? $subjectModel->programs()->where('programs.status', '1')->orderBy('programs.title')->get()
            : collect();

        // Gather enrolls (same rules as index()).
        $enrolls = StudentEnroll::query();
        if ($crossProgram) {
            if (!empty($session) && $session != '0') { $enrolls->where('session_id', $session); }
            if (!empty($semester) && $semester != '0') { $enrolls->where('semester_id', $semester); }
            $ids = $sharingPrograms->pluck('id')->toArray();
            if (!empty($ids)) { $enrolls->whereIn('program_id', $ids); }
        } else {
            if (!empty($program) && $program != '0') { $enrolls->where('program_id', $program); }
            if (!empty($session) && $session != '0') { $enrolls->where('session_id', $session); }
            if (!empty($semester) && $semester != '0') { $enrolls->where('semester_id', $semester); }
            if (!empty($section) && $section != '0') { $enrolls->where('section_id', $section); }
        }
        $enrolls->whereHas('subjects', function ($q) use ($subject) { $q->where('subject_id', $subject); })
            ->with(['student', 'program'])
            ->whereHas('student', function ($q) { $q->where('status', '1'); });
        $rows = $enrolls->get();

        if ($crossProgram) {
            $rows = $rows->groupBy(function ($r) { return $r->program_id . '-' . $r->matricule; })
                ->map(function ($g) { return $g->sortByDesc('id')->first(); })
                ->sortBy(function ($r) { return (optional($r->program)->title ?? '') . '_' . $r->matricule; })
                ->values();
        } else {
            $rows = $rows->groupBy('matricule')
                ->map(function ($g) { return $g->sortByDesc('id')->first(); })
                ->sortBy(function ($r) { return $r->matricule; })
                ->values();
        }

        $attendanceSetting = ExamAttendanceSetting::first();

        // Saved bypass state, to flag bypassed students on the sheet.
        $bypassMap = Exam::where('subject_id', $subject)->where('exam_type_id', $type)
            ->whereIn('student_enroll_id', $rows->pluck('id')->all())
            ->pluck('bypass_course_attendance', 'student_enroll_id');

        $examTypeModel = ExamType::find($type);
        $ca_types = collect();
        $ca_marks = [];
        if($examTypeModel && $examTypeModel->is_final == 1){
            $ca_types = ExamType::where('is_final', 0)->where('status', 1)
                ->whereHas('exams', function($q) use ($subject){ $q->where('subject_id', $subject); })
                ->orderBy('id')->get();

            $enrollIds = $rows->pluck('id')->all();
            if($ca_types->isNotEmpty() && !empty($enrollIds)){
                $caRows = Exam::where('subject_id', $subject)
                    ->whereIn('exam_type_id', $ca_types->pluck('id')->all())
                    ->whereIn('student_enroll_id', $enrollIds)
                    ->get();
                foreach($caRows as $ex){
                    $ca_marks[$ex->student_enroll_id][$ex->exam_type_id] = [
                        'achieve' => $ex->achieve_marks,
                        'marks'   => $ex->marks,
                    ];
                }
            }
        }

        $sheet = [];
        foreach ($rows as $r) {
            $stats = $this->courseAttendanceStats($r->id, $subject);
            $sheet[] = [
                'id'         => $r->id,
                'matricule'  => $r->matricule,
                'name'       => trim((optional($r->student)->first_name ?? '') . ' ' . (optional($r->student)->last_name ?? '')),
                'program'    => optional($r->program)->shortcode ?: optional($r->program)->title,
                'percentage' => $stats['percentage'],
                'attendance_mark' => $stats['attendance_mark'],
                'attendance_contribution' => $stats['attendance_contribution'],
                'eligible'   => $this->isCourseAttendanceEligible($r->id, $subject, $attendanceSetting),
                'bypassed'   => (bool) ($bypassMap[$r->id] ?? false),
            ];
        }

        $data = [
            'title' => 'Examination Sign-In / Sign-Out Sheet',
            'setting' => \App\Models\Setting::first(),
            'sheet' => $sheet,
            'examType' => $examTypeModel,
            'ca_types' => $ca_types,
            'ca_marks' => $ca_marks,
            'attendanceSetting' => $attendanceSetting,
            'crossProgram' => $crossProgram,
            'sharingPrograms' => $sharingPrograms,
            'date' => $request->date,
            'facultyName' => optional(\App\Models\Faculty::find($request->faculty))->title,
            'programName' => $crossProgram ? __('All sharing programmes') : optional(\App\Models\Program::find($program))->title,
            'sessionName' => optional(\App\Models\Session::find($session))->title,
            'semesterName' => optional(\App\Models\Semester::find($semester))->title,
            'sectionName' => optional(\App\Models\Section::find($section))->title,
            'subjectModel' => $subjectModel,
        ];

        return view('admin.exam.attendance-sheet', $data);
    }

    /**
     * Course-attendance stats for a student in a subject.
     * - 'percentage' (used for eligibility) = present / (present + absent) * 100,
     *   EXCLUDING leave/holiday; null when there are no present/absent records.
     * - 'attendance_mark' = the contributed attendance score out of
     *   'attendance_contribution' (the mark distribution from result-contribution),
     *   computed the same way as ExamMarksSyncService (leave counts as present).
     */
    private function courseAttendanceStats($studentEnrollId, $subjectId, $attendanceContribution = null): array
    {
        static $contribCache = [];

        if ($attendanceContribution === null) {
            if (!array_key_exists($subjectId, $contribCache)) {
                $contribCache[$subjectId] = (float) (\App\Services\ResultContributionService::getSubjectContributions($subjectId)['attendance'] ?? 0);
            }
            $attendanceContribution = $contribCache[$subjectId];
        }

        $rows = StudentAttendance::where('student_enroll_id', $studentEnrollId)
            ->where('subject_id', $subjectId)
            ->get();

        $present = $absent = $leave = $holiday = 0;
        foreach ($rows as $r) {
            if ($r->attendance == 1) { $present++; }
            elseif ($r->attendance == 2) { $absent++; }
            elseif ($r->attendance == 3) { $leave++; }
            elseif ($r->attendance == 4) { $holiday++; }
        }

        $working = $present + $absent; // leave + holiday excluded (eligibility %)
        $percentage = $working > 0 ? round(($present / $working) * 100, 2) : null;

        // Attendance MARK — matches ExamMarksSyncService (leave counts as present).
        $markPresent = $present + $leave;
        $markSessions = $markPresent + $absent;
        $attendanceMark = ($markSessions > 0 && $attendanceContribution > 0)
            ? round(($attendanceContribution / $markSessions) * $markPresent, 2)
            : 0;

        return [
            'percentage' => $percentage,        // null = no class attendance recorded
            'present' => $present,
            'absent' => $absent,
            'leave' => $leave,
            'holiday' => $holiday,
            'total_working_days' => $working,
            'attendance_mark' => $attendanceMark,
            'attendance_contribution' => $attendanceContribution,
        ];
    }

    /**
     * Whether a student is eligible to sit the final exam for a subject, given the
     * global ExamAttendanceSetting. No records (null %) ⇒ eligible.
     */
    private function isCourseAttendanceEligible($studentEnrollId, $subjectId, $setting): bool
    {
        if (!$setting || !$setting->is_enabled) {
            return true;
        }
        $pct = $this->courseAttendanceStats($studentEnrollId, $subjectId)['percentage'];
        if ($pct === null) {
            return true; // no class attendance recorded → do not block
        }
        return $pct >= ($setting->minimum_attendance_percentage ?? 70);
    }

    /**
     * Unlock attendance for a student.
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'student_enroll_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'exam_type_id' => 'required|integer',
        ]);

        if (!Auth::user()->can('subject-marking-unlock')) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        $exam = Exam::where('student_enroll_id', $request->student_enroll_id)
            ->where('subject_id', $request->subject_id)
            ->where('exam_type_id', $request->exam_type_id)
            ->first();

        if ($exam) {
            $exam->attendance_locked = 0;
            $exam->save();
            return response()->json(['message' => 'Unlocked successfully', 'status' => 'success']);
        }

        return response()->json(['message' => 'Record not found'], 404);
    }

    /**
     * Bulk unlock attendance for students.
     */
    public function bulkUnlock(Request $request)
    {
        $request->validate([
            'student_enroll_ids' => 'required|array',
            'subject_id' => 'required|integer',
            'exam_type_id' => 'required|integer',
        ]);

        if (!Auth::user()->can('subject-marking-unlock')) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        $count = Exam::whereIn('student_enroll_id', $request->student_enroll_ids)
            ->where('subject_id', $request->subject_id)
            ->where('exam_type_id', $request->exam_type_id)
            ->update(['attendance_locked' => 0]);

        if ($count > 0) {
            return response()->json(['message' => "$count records unlocked successfully", 'status' => 'success']);
        }

        return response()->json(['message' => 'Records not found or already unlocked'], 404);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['access']    = $this->access;

        //
        $data['sessions'] = Session::where('status', '1')
                        ->orderBy('id', 'desc')->get();
        $data['types'] = ExamType::where('status', '1')
                        ->orderBy('title', 'asc')->get();

        return view($this->view.'.import', $data);
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function importStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'session' => 'required',
            'subject' => 'required',
            'type' => 'required',
            'date' => 'required|date|before_or_equal:today',
            'import' => 'required|file|mimes:xlsx',
        ]);


        // Passing Data
        $data['session'] = $request->session;
        $data['subject'] = $request->subject;
        $data['type'] = $request->type;
        $data['date'] = $request->date;

        Excel::import(new MarksImport($data), $request->file('import'));


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Get courses assigned to the current lecturer via ClassRoutine for the current session.
     * Groups courses by program and year for easy navigation.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getMyAssignedCourses()
    {
        $teacherId = Auth::guard('web')->id();
        $currentSession = Session::where('status', '1')->where('current', '1')->first();
        
        if (!$currentSession) {
            return collect([]);
        }

        // Check if user is super admin or has staff assignments
        $authUser = User::find($teacherId);
        $isSuperAdmin = $authUser ? $authUser->hasRole('Super Admin') : false;
        $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacherId)->exists();

        // Build query for class routines
        $routinesQuery = ClassRoutine::where('session_id', $currentSession->id)
            ->where('status', '1')
            ->with([
                'subject',
                'program.faculty',
                'semester',
                'section',
            ]);

        // If not super admin and doesn't have staff assignments, filter by teacher
        if (!$isSuperAdmin && !$hasAssignments) {
            $routinesQuery->where('teacher_id', $teacherId);
        }

        // Apply staff assignment filter if user has assignments
        if ($hasAssignments) {
            $assignments = \App\Models\StaffAssignment::where('user_id', $teacherId)->get();
            $allowedFacultyIds = $assignments->pluck('faculty_id')->filter()->unique();
            $allowedProgramIds = $assignments->pluck('program_id')->filter()->unique();
            $allowedSubjectIds = $assignments->pluck('subject_id')->filter()->unique();

            $routinesQuery->where(function($query) use ($allowedFacultyIds, $allowedProgramIds, $allowedSubjectIds) {
                if ($allowedFacultyIds->isNotEmpty()) {
                    $query->whereHas('program', function($q) use ($allowedFacultyIds) {
                        $q->whereIn('faculty_id', $allowedFacultyIds);
                    });
                }
                if ($allowedProgramIds->isNotEmpty()) {
                    $query->orWhereIn('program_id', $allowedProgramIds);
                }
                if ($allowedSubjectIds->isNotEmpty()) {
                    $query->orWhereIn('subject_id', $allowedSubjectIds);
                }
            });
        }

        $routines = $routinesQuery->get();

        // Group by program, then flatten subjects
        $grouped = $routines->groupBy(function($routine) {
            return $routine->program_id;
        })->map(function($programRoutines) {
            $program = $programRoutines->first()->program;
            
            $subjects = $programRoutines->groupBy('subject_id')->map(function($subjectRoutines) {
                $first = $subjectRoutines->first();
                $subject = $first->subject;
                
                $regularSemesters = $subjectRoutines->pluck('semester')->filter()->unique('id')->sortBy(['year', 'semester_type'])->values();
                
                $regularSemesterIds = $regularSemesters->pluck('id')->toArray();
                $resitSemesters = Semester::where('is_resit', true)
                    ->whereIn('parent_semester_id', $regularSemesterIds)
                    ->where('status', 1)
                    ->orderBy('year')
                    ->orderBy('semester_type')
                    ->get();
                
                $allSemesters = $regularSemesters->concat($resitSemesters)->sortBy(['year', 'semester_type', 'is_resit'])->values();
                $years = $allSemesters->pluck('year')->unique()->sort()->values();
                
                $sections = $subjectRoutines->pluck('section')->filter()->unique('id')->values();
                $firstSection = $sections->first();
                $firstSemester = $regularSemesters->first() ?? $allSemesters->first();
                
                return [
                    'subject_id' => $subject->id,
                    'subject_code' => $subject->code,
                    'subject_title' => $subject->title,
                    'semesters' => $allSemesters,
                    'years' => $years,
                    'first_year' => $years->first() ?? 1,
                    'first_semester_id' => $firstSemester->id ?? null,
                    'first_section_id' => $firstSection->id ?? null,
                    'first_section_title' => $firstSection->title ?? 'All',
                    'sections' => $sections,
                ];
            })->sortBy('subject_code')->values();

            return [
                'program_id' => $program->id,
                'program_title' => $program->title,
                'program_code' => $program->short_code ?? $program->title,
                'faculty_id' => $program->faculty_id,
                'faculty_title' => $program->faculty->title ?? 'Unknown Faculty',
                'subjects' => $subjects,
            ];
        })->sortBy('program_title')->values();

        return $grouped;
    }
    
    /**
     * Check attendance status for a specific subject/semester/section/exam type combination
     */
    public function checkStatus(Request $request)
    {
        $subjectId = $request->subject_id;
        $semesterId = $request->semester_id;
        $sectionId = $request->section_id;
        $examTypeId = $request->exam_type_id;
        $sessionId = $request->session_id;
        
        if (!$subjectId || !$semesterId || !$examTypeId) {
            return response()->json([
                'status' => 'incomplete',
                'message' => 'Please complete all selections'
            ]);
        }
        
        // Get enrolled students for this combination
        $enrollQuery = StudentEnroll::whereIn('status', [1, 2])
            ->whereHas('program', function($q) use ($semesterId) {
                $q->whereHas('semesters', function($sq) use ($semesterId) {
                    $sq->where('semester_id', $semesterId);
                });
            });
        
        if ($sessionId) {
            $enrollQuery->where('session_id', $sessionId);
        }
        
        if ($sectionId && $sectionId != '0') {
            $enrollQuery->where('section_id', $sectionId);
        }
        
        // Get student enrolls that have this subject
        $enrollQuery->whereHas('subjects', function($q) use ($subjectId) {
            $q->where('subject_id', $subjectId);
        });
        
        $totalStudents = $enrollQuery->count();
        
        if ($totalStudents == 0) {
            return response()->json([
                'status' => 'no_students',
                'message' => 'No students enrolled',
                'icon' => 'fas fa-user-slash',
                'color' => 'secondary',
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'pending' => 0
            ]);
        }
        
        // Get exam records for this combination
        $studentEnrollIds = $enrollQuery->pluck('id');
        
        $examRecords = Exam::where('subject_id', $subjectId)
            ->where('exam_type_id', $examTypeId)
            ->whereIn('student_enroll_id', $studentEnrollIds)
            ->get();
        
        $attendanceMarked = $examRecords->whereNotNull('attendance')->count();
        $presentCount = $examRecords->where('attendance', 1)->count();
        $absentCount = $examRecords->where('attendance', 2)->count();
        $pendingCount = $totalStudents - $attendanceMarked;
        $lockedCount = $examRecords->where('attendance_locked', 1)->count();
        
        // Determine status
        if ($attendanceMarked == 0) {
            return response()->json([
                'status' => 'not_taken',
                'message' => 'Attendance not taken yet',
                'icon' => 'fas fa-clock',
                'color' => 'warning',
                'total' => $totalStudents,
                'present' => 0,
                'absent' => 0,
                'pending' => $totalStudents,
                'locked' => 0
            ]);
        } elseif ($pendingCount > 0) {
            return response()->json([
                'status' => 'partial',
                'message' => $attendanceMarked . '/' . $totalStudents . ' marked',
                'icon' => 'fas fa-user-clock',
                'color' => 'info',
                'total' => $totalStudents,
                'present' => $presentCount,
                'absent' => $absentCount,
                'pending' => $pendingCount,
                'locked' => $lockedCount
            ]);
        } else {
            $isLocked = $lockedCount == $totalStudents;
            return response()->json([
                'status' => 'completed',
                'message' => $isLocked ? 'Completed & Locked' : 'All marked',
                'icon' => $isLocked ? 'fas fa-lock' : 'fas fa-check-circle',
                'color' => 'success',
                'total' => $totalStudents,
                'present' => $presentCount,
                'absent' => $absentCount,
                'pending' => 0,
                'locked' => $lockedCount
            ]);
        }
    }
}
