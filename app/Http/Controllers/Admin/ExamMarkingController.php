<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\ExamType;
use App\Models\Semester;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Program;
use App\Models\Session;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\Exam;
use App\Models\ClassRoutine;
use App\Models\StudentEnroll;
use App\Models\StudentAttendance;
use App\User;

class ExamMarkingController extends Controller
{
    protected $title, $route, $view, $path, $access;

    /** Count of assigned courses before the published-filter is applied (for the "Show all" toggle). */
    protected $myAssignedUnfilteredCount = 0;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_exam_marking', 1);
        $this->route = 'admin.exam-marking';
        $this->view = 'admin.exam';
        $this->path = 'exam';
        $this->access = 'exam';

        $this->middleware('permission:'.$this->access.'-marking', ['only' => ['index','store']]);
        $this->middleware('permission:'.$this->access.'-result', ['only' => ['result']]);
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

        // Get current session and teacher's assigned courses for quick access.
        // "Show all" toggle reveals published (otherwise-hidden) courses too.
        $showAssignedAll = $request->boolean('show_assigned_all');
        $data['show_assigned_all'] = $showAssignedAll;
        $data['myAssignedCourses'] = $this->getMyAssignedCourses($showAssignedAll);
        $data['assignedUnfilteredCount'] = $this->myAssignedUnfilteredCount;
        $data['examTypes'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['currentSession'] = Session::where('status', '1')->where('current', '1')->first();

        // Cross-programme toggle
        $data['cross_program'] = $request->boolean('cross_program', false);

        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = null;
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
            $data['selected_type'] = null;
        }


        // Filter Search
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['types'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['selected_exam_type'] = null;
        if (!empty($request->type) && $request->type != '0') {
            $data['selected_exam_type'] = ExamType::where('status', '1')->find($request->type);
        }

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
            // Access Data
            $authUser = Auth::guard('web')->user();
            $teacher_id = $authUser->id;
            $superAdmin = $authUser->hasRole('Super Admin');
            
            // Check if user has staff assignments
            $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacher_id)->exists();

            // Filter Subject
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            
            // Apply staff assignment filter
            $subjects = StaffAssignmentService::filterCourses($subjects);
            
            $subjectsList = $subjects->orderBy('code', 'asc')->get();
            
            // Ensure selected subject is included in the list (for quick access feature)
            if (!empty($request->subject) && $request->subject != '0') {
                $selectedSubjectExists = $subjectsList->contains('id', (int)$request->subject);
                if (!$selectedSubjectExists) {
                    $selectedSubjectObj = Subject::where('id', $request->subject)->where('status', '1')->first();
                    if ($selectedSubjectObj) {
                        $subjectsList->push($selectedSubjectObj);
                        $subjectsList = $subjectsList->sortBy('code')->values();
                    }
                }
            }
            
            $data['subjects'] = $subjectsList;
        }


        // Exam Marking
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject) && !empty($request->type)){

            // Check Subject Access
            $subject_check = Subject::where('id', $subject);
            $subject_check->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            })->firstOrFail();

            // ── Find all programmes that share this subject ──
            $subjectModel = Subject::find($subject);
            $data['sharing_programs'] = $subjectModel
                ? $subjectModel->programs()->with('faculty')->where('programs.status', '1')->orderBy('programs.title')->get()
                : collect();

            $crossProgram = $data['cross_program'];

            // Is this a final exam type? (CA / non-final does not require exam-attendance)
            $isFinal = (bool) optional($data['selected_exam_type'])->is_final;

            // For CA (non-final): exam-attendance is NOT a prerequisite. Ensure every enrolled
            // student has a present exam row so they appear and their marks count in the grade.
            if (!$isFinal) {
                $sharingProgramIds = $crossProgram ? $data['sharing_programs']->pluck('id')->toArray() : [];
                $this->ensureCaExamRows($subject, $type, $data['selected_exam_type'], [
                    'cross_program'      => $crossProgram,
                    'program'            => $program,
                    'session'            => $session,
                    'semester'           => $semester,
                    'section'            => $section,
                    'sharingProgramIds'  => $sharingProgramIds,
                ], $teacher_id);
            }

            // Exams — CA shows all rows (attendance not required); finals still require present
            $exams = Exam::where('subject_id', $subject)
                ->where('exam_type_id', $type);
            if ($isFinal) {
                $exams->where('attendance', '1');
            }

            if ($crossProgram) {
                // Cross-programme mode: only filter by session (skip program/semester/section)
                $sharingProgramIds = $data['sharing_programs']->pluck('id')->toArray();
                $exams->whereHas('studentEnroll', function ($query) use ($session, $sharingProgramIds) {
                    $query->where('session_id', $session);
                    if (!empty($sharingProgramIds)) {
                        $query->whereIn('program_id', $sharingProgramIds);
                    }
                });
            } else {
                // Normal mode: filter by all criteria
                $exams->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section) {
                    if ($program != '0') {
                        $query->where('program_id', $program);
                    }
                    if ($session != '0') {
                        $query->where('session_id', $session);
                    }
                    if ($semester != '0') {
                        $query->where('semester_id', $semester);
                    }
                    if ($section != '0') {
                        $query->where('section_id', $section);
                    }
                });
            }

            $exams->with([
                'studentEnroll.student',
                'studentEnroll.program.faculty',
                'studentEnroll.semester',
                'studentEnroll.section',
                'scriptCode',
                'type',
                'subject',
            ])->whereHas('studentEnroll.student');

                $rows = $exams->get();

                // Group by unique matricule - keep only latest exam per matricule
                $uniqueMatricules = $rows->groupBy(function($item) {
                    return $item->studentEnroll->matricule;
                })->map(function($group) {
                    return $group->sortByDesc('id')->first();
                })->values();

                if ($crossProgram) {
                    // Cross-programme: sort by programme name, then matricule
                    $data['rows'] = $uniqueMatricules->sortBy(function ($exam) {
                        $enroll = $exam->studentEnroll;
                        return ($enroll->program->title ?? '') . '_' . ($enroll->matricule ?? optional($enroll->student)->student_id ?? '');
                    })->values()->all();
                } else {
                    // Normal: sort by matricule
                    $data['rows'] = $uniqueMatricules->sortBy(function ($query) {
                        return $query->studentEnroll->matricule;
                    })->values()->all();
                }
                
                // Fetch Submission Logs for the selected exams
                $examIdsForLog = $uniqueMatricules->pluck('id')->toArray();
                if (!empty($examIdsForLog)) {
                    $logs = \App\Models\AuditLog::with(['user', 'auditable.studentEnroll.student'])
                        ->where('auditable_type', 'App\Models\Exam')
                        ->whereIn('auditable_id', $examIdsForLog)
                        ->where('event', 'updated')
                        ->orderBy('created_at', 'desc')
                        ->get();

                    // Group logs by user and time (minute) to form "Submission Sessions"
                    $data['submissionLogs'] = $logs->groupBy(function($log) {
                        return $log->user_id . '_' . $log->created_at->format('Y-m-d H:i');
                    });
                } else {
                    $data['submissionLogs'] = collect();
                }
        }

        // Attendance weight (from result-contribution) for the inline attendance-migration panel.
        $data['attendance_weight'] = 0;
        if (!empty($request->subject) && $request->subject != '0') {
            $contrib = \App\Services\ResultContributionService::getSubjectContributions($request->subject);
            $data['attendance_weight'] = (float) ($contrib['attendance'] ?? 0);
        }

        // Pre-fill the attendance-migration panel after a save (keep dates/total and show saved absences)
        $data['mig_start_date']    = $request->start_date;
        $data['mig_end_date']      = $request->end_date;
        $data['mig_total_classes'] = $request->total_classes;
        $data['mig_absences']      = [];
        $data['mig_actual_marks']  = []; // true attendance mark across ALL dates for the subject
        $migRows = $data['rows'] ?? [];
        if (!empty($migRows)) {
            $enrollIds = collect($migRows)->map(fn($r) => $r->student_enroll_id)->filter()->unique()->all();

            // In-range absences (to pre-fill the input after a save)
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $data['mig_absences'] = StudentAttendance::where('subject_id', $request->subject)
                    ->whereIn('student_enroll_id', $enrollIds)
                    ->whereBetween('date', [$request->start_date, $request->end_date])
                    ->where('attendance', 2) // 2 = absent
                    ->selectRaw('student_enroll_id, COUNT(*) as cnt')
                    ->groupBy('student_enroll_id')
                    ->pluck('cnt', 'student_enroll_id')
                    ->toArray();
            }

            // Actual attendance mark = weight x (present+leave)/(present+leave+absent) over ALL dates
            // (mirrors ExamMarksSyncService so it equals the graded attendance component)
            $weight = (float) $data['attendance_weight'];
            $grouped = StudentAttendance::where('subject_id', $request->subject)
                ->whereIn('student_enroll_id', $enrollIds)
                ->get()
                ->groupBy('student_enroll_id');
            foreach ($enrollIds as $eid) {
                $recs    = $grouped->get($eid) ?? collect();
                $present = $recs->whereIn('attendance', [1, 3])->count(); // present + leave
                $absent  = $recs->where('attendance', 2)->count();
                $total   = $present + $absent;
                $data['mig_actual_marks'][$eid] = $total > 0 ? round($weight * $present / $total, 2) : 0;
            }
        }

        return view($this->view.'.marking', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'marks'         => 'nullable|array',
            'notes'         => 'nullable|array',
            'subject'       => 'nullable|integer',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'total_classes' => 'nullable|integer|min:1',
            'absences'      => 'nullable|array',
            'absences.*'    => 'nullable|integer|min:0',
        ]);

        $marks = $validated['marks'] ?? [];
        $notes = $validated['notes'] ?? [];

        $examIds = collect([$marks, $notes])->flatMap(function ($items) {
            return is_array($items) ? array_keys($items) : [];
        })->unique()->filter()->values();

        // Is an attendance migration bundled into this same Save?
        $migReady = $request->filled('start_date') && $request->filled('end_date')
            && $request->filled('total_classes')
            && is_array($request->absences)
            && collect($request->absences)->contains(fn($v) => $v !== null && $v !== '');

        if ($examIds->isEmpty() && !$migReady) {
            return redirect()->back();
        }

        $migSubjectId = $request->filled('subject') ? (int) $request->subject : null;

        // Block submission if mark distribution is not configured for this course
        $firstExam = $examIds->isNotEmpty() ? Exam::find($examIds->first()) : null;
        $checkSubjectId = $firstExam ? $firstExam->subject_id : $migSubjectId;
        if ($checkSubjectId && !\App\Services\ResultContributionService::isConfigured($checkSubjectId)) {
            Flasher::addError('Mark distribution has not been configured for this course. Please configure it before submitting.', 'Error');
            return redirect()->back();
        }

        // Pre-validate the attendance migration BEFORE saving marks, so a bad date
        // range never leaves marks saved with the attendance half failing.
        $classDates = null;
        $T = 0;
        if ($migReady) {
            $startC = \Carbon\Carbon::parse($request->start_date);
            $endC   = \Carbon\Carbon::parse($request->end_date);
            $T      = (int) $request->total_classes;
            if ($startC->diffInDays($endC) > 31) {
                Flasher::addError(__('Date range cannot exceed 31 days.'), __('msg_error'));
                return redirect()->back();
            }
            $classDates = $this->resolveClassDates($migSubjectId, $request->session, $request->program, $request->semester, $request->section, $startC, $endC, $T);
            if (count($classDates) < $T) {
                Flasher::addError(__('The selected date range only provides :n class day(s) but :t were entered. Widen the date range or reduce total classes.', ['n' => count($classDates), 't' => $T]), __('msg_error'));
                return redirect()->back();
            }
            $classDates = array_slice($classDates, 0, $T);
        }

        // ── Eager-load ALL exams in one query to eliminate N+1 ──
        $allExams = Exam::with(['type', 'scriptCode', 'studentEnroll', 'studentEnroll.student'])
            ->whereIn('id', $examIds)
            ->get()
            ->keyBy('id');

        $blocked = [];
        $updatedCount = 0;
        $currentUserId = Auth::guard('web')->id();
        $syncPairs = collect();

        // ── Wrap in a DB transaction for atomicity ──
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($examIds as $examId) {
                $markValue = $marks[$examId] ?? null;
                $noteValue = $notes[$examId] ?? null;

                $exam = $allExams->get($examId);

                if (!$exam) {
                    continue;
                }

                // Check lock status
                if ($exam->marks_locked) {
                    continue;
                }

                if (($exam->type->is_final ?? false) && !$exam->scriptCode) {
                    $blocked[] = $exam->studentEnroll?->matricule ?? $exam->studentEnroll?->student?->student_id;
                    continue;
                }

                $exam->achieve_marks = ($markValue === null || $markValue === '') ? null : $markValue;
                $exam->marks_locked = 1; // Lock upon saving
                $exam->note = $noteValue;
                $exam->updated_by = $currentUserId;

                // Auto-set contribution if missing or zero
                if (empty($exam->contribution) || $exam->contribution == 0) {
                    $contribution = \App\Services\ResultContributionService::getExamTypeContribution(
                        $exam->subject_id,
                        $exam->exam_type_id
                    );
                    if ($contribution > 0) {
                        $exam->contribution = $contribution;
                    }
                }

                $changed = $exam->isDirty(['achieve_marks', 'note']);

                if ($changed) {
                    $exam->updated_by = $currentUserId;
                }

                $exam->save();

                if ($changed) {
                    $updatedCount++;
                }

                // Collect sync pairs from the already-loaded exam (no extra query)
                $key = $exam->student_enroll_id . '-' . $exam->subject_id;
                if (!$syncPairs->has($key)) {
                    $syncPairs->put($key, ['enroll' => $exam->student_enroll_id, 'subject' => $exam->subject_id]);
                }
            }

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            Flasher::addError('An error occurred while saving marks. Please try again. (' . class_basename($e) . ')', 'Error');
            return redirect()->back();
        }

        if (!empty($blocked)) {
            $blockedList = collect($blocked)->filter()->unique()->implode(', ');
            Flasher::addWarning(__('Student exam IDs are missing for some records. Please contact the administrator to configure them before submitting marks.'), __('msg_warning'));

            if (!empty($blockedList)) {
                Flasher::addWarning(__('Affected student IDs: :list', ['list' => $blockedList]), __('msg_warning'));
            }
        }

        if ($updatedCount > 0) {
            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            // Flash detailed success data so the view can show a prominent success modal
            $firstExamModel = $allExams->first();
            session()->flash('marks_saved', [
                'count'   => $updatedCount,
                'subject' => ($firstExamModel->subject->code ?? '') . ' - ' . ($firstExamModel->subject->title ?? ''),
                'type'    => $firstExamModel->type->title ?? '',
                'blocked' => count($blocked),
            ]);
        }

        // ── Apply the bundled attendance migration (same Save) ──
        $attendanceEnrolls = [];
        if ($migReady && $classDates) {
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $attendanceEnrolls = $this->applyAttendanceMigration($migSubjectId, $classDates, $request->absences, $T, $currentUserId);
                \Illuminate\Support\Facades\DB::commit();
                if (!empty($attendanceEnrolls)) {
                    Flasher::addSuccess(__(':n student attendance record-set(s) saved, :t class(es) each.', ['n' => count($attendanceEnrolls), 't' => $T]), __('msg_success'));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                Flasher::addError(__('An error occurred while saving attendance.') . ' (' . class_basename($e) . ')', __('msg_error'));
            }
        }

        // Sync marks- and attendance-affected students once each
        $syncPairs = $syncPairs->keyBy(fn($p) => $p['enroll'] . '-' . $p['subject']);
        foreach ($attendanceEnrolls as $eid) {
            $syncPairs->put($eid . '-' . $migSubjectId, ['enroll' => $eid, 'subject' => $migSubjectId]);
        }
        foreach ($syncPairs as $pair) {
            \App\Services\ExamMarksSyncService::sync((int) $pair['enroll'], (int) $pair['subject']);
        }

        // Keep the same filtered screen and (if used) the attendance panel populated
        if ($request->filled('subject')) {
            $params = [
                'faculty'  => $request->faculty,  'program'  => $request->program,
                'session'  => $request->session,  'semester' => $request->semester,
                'section'  => $request->section,  'subject'  => $request->subject,
                'type'     => $request->type ?? $request->exam_type,
            ];
            if ($request->boolean('cross_program')) {
                $params['cross_program'] = 1;
            }
            if ($migReady) {
                $params['start_date']    = $request->start_date;
                $params['end_date']      = $request->end_date;
                $params['total_classes'] = $T;
            }
            return redirect()->route('admin.exam-marking.index', $params);
        }

        return redirect()->back();
    }

    /**
     * Unlock marks for a student exam.
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|integer',
        ]);

        if (!Auth::user()->can('subject-marking-unlock')) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        $exam = Exam::find($request->exam_id);

        if ($exam) {
            // Check if the marking is published and not unpublished
            $subjectMarking = \App\Models\SubjectMarking::where('student_enroll_id', $exam->student_enroll_id)
                ->where('subject_id', $exam->subject_id)
                ->first();

            if ($subjectMarking && 
                $subjectMarking->workflow_state === \App\Models\SubjectMarking::STATE_PUBLISHED && 
                $subjectMarking->is_published_override !== false) {
                return response()->json(['message' => 'Cannot unlock marks for a published result. Please unpublish first.'], 403);
            }

            $exam->marks_locked = 0;
            $exam->save();
            return response()->json(['message' => 'Unlocked successfully', 'status' => 'success']);
        }

        return response()->json(['message' => 'Record not found'], 404);
    }

    /**
     * Bulk unlock marks for student exams.
     */
    public function bulkUnlock(Request $request)
    {
        $request->validate([
            'exam_ids' => 'required|array',
        ]);

        if (!Auth::user()->can('subject-marking-unlock')) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        $exams = Exam::whereIn('id', $request->exam_ids)->get();
        $unlockedCount = 0;
        $skippedCount = 0;

        foreach ($exams as $exam) {
            $subjectMarking = \App\Models\SubjectMarking::where('student_enroll_id', $exam->student_enroll_id)
                ->where('subject_id', $exam->subject_id)
                ->first();

            if ($subjectMarking && 
                $subjectMarking->workflow_state === \App\Models\SubjectMarking::STATE_PUBLISHED && 
                $subjectMarking->is_published_override !== false) {
                $skippedCount++;
                continue;
            }

            $exam->marks_locked = 0;
            $exam->save();
            $unlockedCount++;
        }

        if ($unlockedCount > 0) {
            $message = "$unlockedCount records unlocked successfully.";
            if ($skippedCount > 0) {
                $message .= " $skippedCount records were skipped because their results are published.";
            }
            return response()->json(['message' => $message, 'status' => 'success']);
        }

        if ($skippedCount > 0) {
            return response()->json(['message' => 'Cannot unlock marks for published results. Please unpublish first.'], 403);
        }

        return response()->json(['message' => 'Records not found or already unlocked'], 404);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function result(Request $request)
    {
        //
        $data['title'] = trans_choice('module_exam_result', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;


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
            $data['selected_program'] = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = '0';
        }

        if(!empty($request->subject) || $request->subject != null){
            $data['selected_subject'] = $subject = $request->subject;
        }
        else{
            $data['selected_subject'] = '0';
        }

        if(!empty($request->type) || $request->type != null){
            $data['selected_type'] = $type = $request->type;
        }
        else{
            $data['selected_type'] = '0';
        }


        // Filter Search
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['types'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

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
            // Access Data
            $authUser = Auth::guard('web')->user();
            $teacher_id = $authUser->id;
            $superAdmin = $authUser->hasRole('Super Admin');

            // Filter Subject
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                if(!$superAdmin){
                    $query->where('teacher_id', $teacher_id);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }


        // Exam Result
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject) && !empty($request->type)){

            // Check Subject Access
            $subject_check = Subject::where('id', $subject);
            $subject_check->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                if(!$superAdmin){
                    $query->where('teacher_id', $teacher_id);
                }
            })->firstOrFail();


            // Exams
            $exams = Exam::where('id', '!=', null);

            if(!empty($request->program) && !empty($request->session)){

                $exams->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
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
            if(!empty($request->subject) && $request->subject != '0'){
                $exams->where('subject_id', $subject);
            }
            if(!empty($request->type) && $request->type != '0'){
                $exams->where('exam_type_id', $type);
            }
            $exams->with('studentEnroll.student')->whereHas('studentEnroll.student', function ($query){
                $query->orderBy('matricule', 'asc');
            });

            $rows = $exams->get();

            // Group by unique matricule - keep only latest exam per matricule
            $uniqueMatricules = $rows->groupBy(function($item) {
                return $item->studentEnroll->matricule;
            })->map(function($group) {
                return $group->sortByDesc('id')->first();
            })->values();

            // Array Sorting
            $data['rows'] = $uniqueMatricules->sortBy(function($query){

               return $query->studentEnroll->matricule ?? $query->studentEnroll->student->student_id;

            })->all();
        }


        return view($this->view.'.result', $data);
    }

    /**
     * Get courses assigned to the current lecturer via ClassRoutine for the current session.
     * Groups courses by program and year for easy navigation.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getMyAssignedCourses($showAll = false)
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
            // Get staff assignment restrictions
            $assignments = \App\Models\StaffAssignment::where('user_id', $teacherId)->get();
            $allowedFacultyIds = $assignments->pluck('faculty_id')->filter()->unique();
            $allowedProgramIds = $assignments->pluck('program_id')->filter()->unique();
            $allowedSubjectIds = $assignments->pluck('subject_id')->filter()->unique();

            $routinesQuery->where(function($query) use ($allowedFacultyIds, $allowedProgramIds, $allowedSubjectIds) {
                // Filter by faculty if specified
                if ($allowedFacultyIds->isNotEmpty()) {
                    $query->whereHas('program', function($q) use ($allowedFacultyIds) {
                        $q->whereIn('faculty_id', $allowedFacultyIds);
                    });
                }
                // Filter by program if specified
                if ($allowedProgramIds->isNotEmpty()) {
                    $query->orWhereIn('program_id', $allowedProgramIds);
                }
                // Filter by subject if specified
                if ($allowedSubjectIds->isNotEmpty()) {
                    $query->orWhereIn('subject_id', $allowedSubjectIds);
                }
            });
        }

        $routines = $routinesQuery->get();

        // Group by program, then flatten subjects (each subject gets its own entry with year dropdown options)
        $grouped = $routines->groupBy(function($routine) {
            return $routine->program_id;
        })->map(function($programRoutines) {
            $program = $programRoutines->first()->program;
            
            // Group by subject to get unique subjects with all their year/semester options
            $subjects = $programRoutines->groupBy('subject_id')->map(function($subjectRoutines) {
                $first = $subjectRoutines->first();
                $subject = $first->subject;
                
                // Get all unique regular semesters for this subject (contains year info)
                $regularSemesters = $subjectRoutines->pluck('semester')->filter()->unique('id')->sortBy(['year', 'semester_type'])->values();
                
                // Get resit semesters for each regular semester
                $regularSemesterIds = $regularSemesters->pluck('id')->toArray();
                $resitSemesters = Semester::where('is_resit', true)
                    ->whereIn('parent_semester_id', $regularSemesterIds)
                    ->where('status', 1)
                    ->orderBy('year')
                    ->orderBy('semester_type')
                    ->get();
                
                // Combine regular and resit semesters
                $allSemesters = $regularSemesters->concat($resitSemesters)->sortBy(['year', 'semester_type', 'is_resit'])->values();
                
                // Get unique years from all semesters
                $years = $allSemesters->pluck('year')->unique()->sort()->values();
                
                // Get first available section
                $sections = $subjectRoutines->pluck('section')->filter()->unique('id')->values();
                $firstSection = $sections->first();
                
                // Default to first semester (prefer regular over resit)
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

        // Remember how many courses exist before filtering (drives the "Show all" toggle/empty state)
        $this->myAssignedUnfilteredCount = $grouped->sum(fn($p) => $p['subjects']->count());

        // Declutter: drop courses already fully published (every configured exam type published
        // across all regular semesters/sections taught this session), then drop empty programs.
        // Hidden courses remain reachable via the manual filters — this only trims the quick list.
        // Skipped entirely when the lecturer toggles "Show all".
        if (!$showAll) {
            $sessionId = $currentSession->id;
            $grouped = $grouped->map(function ($p) use ($sessionId) {
                $p['subjects'] = $p['subjects']
                    ->reject(fn($s) => $this->isCourseFullyPublished($p['program_id'], $sessionId, $s))
                    ->values();
                return $p;
            })->filter(fn($p) => $p['subjects']->isNotEmpty())->values();
        }

        return $grouped;
    }

    /**
     * Whether a "My Assigned Courses" subject entry is fully published — i.e. every configured
     * exam type is published (ExamPublishingState) for every regular semester + section the
     * course is taught in this session. Unconfigured courses are never "fully published".
     */
    private function isCourseFullyPublished($programId, $sessionId, array $subjectData): bool
    {
        $examTypeIds = array_keys(\App\Services\ResultContributionService::getSubjectContributions($subjectData['subject_id'])['exam_types']);
        if (empty($examTypeIds)) {
            return false; // not configured -> keep visible
        }

        $semesterIds = collect($subjectData['semesters'] ?? [])
            ->reject(fn($s) => (bool) ($s->is_resit ?? false))
            ->pluck('id')->filter()->unique()->values()->all();
        if (empty($semesterIds)) {
            return false;
        }

        $sectionIds = collect($subjectData['sections'] ?? [])->pluck('id')->filter()->unique()->values()->all();
        if (empty($sectionIds)) {
            $sectionIds = [null]; // course has no section -> single bucket
        }

        $published = \App\Models\ExamPublishingState::where('program_id', $programId)
            ->where('session_id', $sessionId)
            ->where('subject_id', $subjectData['subject_id'])
            ->where('workflow_state', \App\Models\ExamPublishingState::STATE_PUBLISHED)
            ->get(['semester_id', 'section_id', 'exam_type_id']);
        if ($published->isEmpty()) {
            return false;
        }

        // Every (semester × section × exam type) triple must have a matching published row.
        // A published row with section_id null/0 counts as "all sections".
        foreach ($semesterIds as $sem) {
            foreach ($sectionIds as $sec) {
                foreach ($examTypeIds as $et) {
                    $covered = $published->first(function ($row) use ($sem, $sec, $et) {
                        return (int) $row->semester_id === (int) $sem
                            && (int) $row->exam_type_id === (int) $et
                            && ($row->section_id === null || (int) $row->section_id === 0 || (int) $row->section_id === (int) $sec);
                    });
                    if (!$covered) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check marking status for a specific subject/semester/section/exam type combination
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
                'marked' => 0,
                'pending' => 0
            ]);
        }
        
        // Get exam records for this combination
        $studentEnrollIds = $enrollQuery->pluck('id');
        
        $examRecords = Exam::where('subject_id', $subjectId)
            ->where('exam_type_id', $examTypeId)
            ->whereIn('student_enroll_id', $studentEnrollIds)
            ->get();
        
        $marksEntered = $examRecords->whereNotNull('achieve_marks')->count();
        $lockedCount = $examRecords->where('marks_locked', 1)->count();
        $pendingCount = $totalStudents - $marksEntered;
        
        // Calculate average if marks exist
        $avgMarks = null;
        if ($marksEntered > 0) {
            $totalMarks = $examRecords->whereNotNull('achieve_marks')->sum('achieve_marks');
            $avgMarks = round($totalMarks / $marksEntered, 1);
        }
        
        // Determine status
        if ($marksEntered == 0) {
            return response()->json([
                'status' => 'not_taken',
                'message' => 'Marks not entered yet',
                'icon' => 'fas fa-clock',
                'color' => 'warning',
                'total' => $totalStudents,
                'marked' => 0,
                'pending' => $totalStudents,
                'locked' => 0,
                'avg' => null
            ]);
        } elseif ($pendingCount > 0) {
            return response()->json([
                'status' => 'partial',
                'message' => $marksEntered . '/' . $totalStudents . ' entered',
                'icon' => 'fas fa-user-clock',
                'color' => 'info',
                'total' => $totalStudents,
                'marked' => $marksEntered,
                'pending' => $pendingCount,
                'locked' => $lockedCount,
                'avg' => $avgMarks
            ]);
        } else {
            $isLocked = $lockedCount == $totalStudents;
            return response()->json([
                'status' => 'completed',
                'message' => $isLocked ? 'Completed & Locked' : 'All entered',
                'icon' => $isLocked ? 'fas fa-lock' : 'fas fa-check-circle',
                'color' => 'success',
                'total' => $totalStudents,
                'marked' => $marksEntered,
                'pending' => 0,
                'locked' => $lockedCount,
                'avg' => $avgMarks
            ]);
        }
    }

    /**
     * Inline attendance migration (CA / non-final only).
     *
     * The lecturer supplies a date range, the total number of classes held (T),
     * and per-student absence counts (A). The system places T real
     * StudentAttendance rows per student on actual class days (from the
     * lecturer's ClassRoutine timetable when available, else weekdays in the
     * range): A absent (2) + (T-A) present (1). Re-running clears the subject's
     * rows in the range first (idempotent). Each student is then re-synced so
     * the attendance component of the grade reflects the records.
     */
    public function attendanceMigration(Request $request)
    {
        $request->validate([
            'subject'       => 'required|exists:subjects,id',
            'exam_type'     => 'required|exists:exam_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'total_classes' => 'required|integer|min:1',
            'absences'      => 'required|array',
            'absences.*'    => 'nullable|integer|min:0',
        ]);

        $subjectId = (int) $request->subject;
        $T         = (int) $request->total_classes;
        $start     = $request->start_date;
        $end       = $request->end_date;

        // Attendance only applies to continuous assessment (non-final) exam types
        $examType = ExamType::find($request->exam_type);
        if ($examType && $examType->is_final) {
            Flasher::addError(__('Attendance migration is only available for continuous assessment (non-final) exam types.'), __('msg_error'));
            return redirect()->back();
        }

        // Require mark distribution configured (consistent with marking/attendance guards)
        if (!\App\Services\ResultContributionService::isConfigured($subjectId)) {
            Flasher::addError('Mark distribution has not been configured for this course. Please configure it before migrating attendance.', 'Error');
            return redirect()->back();
        }

        $startCarbon = \Carbon\Carbon::parse($start);
        $endCarbon   = \Carbon\Carbon::parse($end);

        // 31-day guard (same as bulk migration)
        if ($startCarbon->diffInDays($endCarbon) > 31) {
            Flasher::addError(__('Date range cannot exceed 31 days.'), __('msg_error'));
            return redirect()->back();
        }

        // Resolve the class dates (shared by all students)
        $classDates = $this->resolveClassDates(
            $subjectId,
            $request->session,
            $request->program,
            $request->semester,
            $request->section,
            $startCarbon,
            $endCarbon,
            $T
        );

        if (count($classDates) < $T) {
            Flasher::addError(
                __('The selected date range only provides :n class day(s) but :t were entered. Widen the date range or reduce total classes.', ['n' => count($classDates), 't' => $T]),
                __('msg_error')
            );
            return redirect()->back();
        }

        $classDates = array_slice($classDates, 0, $T);

        $created_by = Auth::guard('web')->id();

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $processedEnrolls = $this->applyAttendanceMigration($subjectId, $classDates, $request->absences, $T, $created_by);
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            Flasher::addError(__('An error occurred while migrating attendance.') . ' (' . class_basename($e) . ')', __('msg_error'));
            return redirect()->back();
        }

        // Sync each processed student's attendance component into subject_markings
        foreach ($processedEnrolls as $enrollId) {
            \App\Services\ExamMarksSyncService::sync((int) $enrollId, $subjectId);
        }

        Flasher::addSuccess(
            __(':n student record-set(s) migrated, :t class(es) each.', ['n' => count($processedEnrolls), 't' => $T]),
            __('msg_success')
        );

        // Redirect back to the marking screen keeping all filters + the migration
        // inputs, so the panel stays populated and the saved absences/scores show.
        return redirect()->route('admin.exam-marking.index', [
            'faculty'       => $request->faculty,
            'program'       => $request->program,
            'session'       => $request->session,
            'semester'      => $request->semester,
            'section'       => $request->section,
            'subject'       => $request->subject,
            'type'          => $request->exam_type,
            'start_date'    => $start,
            'end_date'      => $end,
            'total_classes' => $T,
        ]);
    }

    /**
     * For CA (non-final) exam types, ensure every enrolled student has a "present"
     * exam row for the subject + type, so the marking screen can list them and
     * their marks count toward the grade WITHOUT requiring the exam-attendance
     * step first. Missing rows are created present; existing rows that were marked
     * absent are flipped to present. Idempotent; failures are logged, not fatal.
     */
    private function ensureCaExamRows($subjectId, $type, $examType, array $scope, $teacherId): void
    {
        try {
            $maxMarks     = (float) ($examType->marks ?? 0);
            $contribution = \App\Services\ResultContributionService::getExamTypeContribution($subjectId, $type);

            $enrolls = StudentEnroll::where('status', '1')
                ->whereHas('subjects', fn($q) => $q->where('subject_id', $subjectId))
                ->whereHas('student', fn($q) => $q->where('status', '1'));

            if (!empty($scope['cross_program'])) {
                $enrolls->where('session_id', $scope['session']);
                if (!empty($scope['sharingProgramIds'])) {
                    $enrolls->whereIn('program_id', $scope['sharingProgramIds']);
                }
            } else {
                if (!empty($scope['program'])  && $scope['program']  != '0') { $enrolls->where('program_id', $scope['program']); }
                if (!empty($scope['session'])  && $scope['session']  != '0') { $enrolls->where('session_id', $scope['session']); }
                if (!empty($scope['semester']) && $scope['semester'] != '0') { $enrolls->where('semester_id', $scope['semester']); }
                if (!empty($scope['section'])  && $scope['section']  != '0') { $enrolls->where('section_id', $scope['section']); }
            }

            foreach ($enrolls->pluck('id') as $eid) {
                $exam = Exam::firstOrCreate(
                    ['student_enroll_id' => $eid, 'subject_id' => $subjectId, 'exam_type_id' => $type],
                    ['marks' => $maxMarks, 'contribution' => $contribution, 'attendance' => 1, 'status' => 1, 'created_by' => $teacherId]
                );
                if (!$exam->wasRecentlyCreated && (int) $exam->attendance !== 1) {
                    $exam->attendance = 1;
                    $exam->save();
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ensureCaExamRows failed: ' . $e->getMessage());
        }
    }

    /**
     * Apply the attendance migration for a set of students: for each student with
     * an entered absences value, clear ALL of their attendance for the subject
     * (so the panel is authoritative), then create T records — A absent (2) and
     * the rest present (1) — randomly distributed across the given class dates.
     * Students with a blank value are skipped. Returns the processed enroll ids.
     * Caller is responsible for the surrounding transaction and the sync.
     */
    private function applyAttendanceMigration(int $subjectId, array $classDates, array $absences, int $T, $userId): array
    {
        $processed = [];
        foreach ($absences as $enrollId => $absenceCount) {
            if ($absenceCount === null || $absenceCount === '') { continue; } // untouched

            $enrollId = (int) $enrollId;
            $A = (int) $absenceCount;
            if ($A > $T) { $A = $T; } // clamp defensively

            StudentAttendance::where('subject_id', $subjectId)
                ->where('student_enroll_id', $enrollId)
                ->delete();

            $shuffled = $classDates;
            shuffle($shuffled);
            $absentDates = array_slice($shuffled, 0, $A);

            foreach ($classDates as $d) {
                StudentAttendance::updateOrCreate(
                    ['student_enroll_id' => $enrollId, 'subject_id' => $subjectId, 'date' => $d],
                    ['attendance' => in_array($d, $absentDates, true) ? 2 : 1, 'created_by' => $userId]
                );
            }
            $processed[] = $enrollId;
        }
        return $processed;
    }

    /**
     * Resolve actual class dates within a range. Prefers the lecturer's
     * timetable (ClassRoutine.day, stored as a Saturday-based number:
     * 1=Sat,2=Sun,3=Mon,4=Tue,5=Wed,6=Thu,7=Fri). Falls back to non-Sunday
     * days, then tops up with any remaining days, so at least T distinct real
     * dates can be returned when the range allows. Returns chronological Y-m-d.
     */
    private function resolveClassDates($subjectId, $session, $program, $semester, $section, $startCarbon, $endCarbon, $T)
    {
        // Map Carbon weekday name -> this app's day number scheme
        $dayMap = [
            'Saturday' => 1, 'Sunday' => 2, 'Monday' => 3, 'Tuesday' => 4,
            'Wednesday' => 5, 'Thursday' => 6, 'Friday' => 7,
        ];

        $routineQuery = ClassRoutine::where('subject_id', $subjectId);
        if (!empty($session)  && $session  != '0') { $routineQuery->where('session_id', $session); }
        if (!empty($program)  && $program  != '0') { $routineQuery->where('program_id', $program); }
        if (!empty($semester) && $semester != '0') { $routineQuery->where('semester_id', $semester); }
        if (!empty($section)  && $section  != '0') { $routineQuery->where('section_id', $section); }

        $routineDays = $routineQuery->pluck('day')
            ->filter(fn($d) => $d !== null && $d !== '')
            ->map(fn($d) => (int) $d)
            ->unique()
            ->values()
            ->all();

        $candidates = [];

        // Pass 1: timetable days (or non-Sunday fallback when no timetable)
        $cursor = $startCarbon->copy();
        while ($cursor->lte($endCarbon)) {
            $appDay = $dayMap[$cursor->format('l')] ?? null;
            $include = !empty($routineDays)
                ? in_array($appDay, $routineDays, true)
                : ($appDay !== 2); // fallback: everything except Sunday
            if ($include) {
                $candidates[] = $cursor->format('Y-m-d');
            }
            $cursor->addDay();
        }

        // Pass 2: top up with any remaining days (incl. Sunday) until we have T
        if (count($candidates) < $T) {
            $cursor = $startCarbon->copy();
            while ($cursor->lte($endCarbon) && count($candidates) < $T) {
                $dateStr = $cursor->format('Y-m-d');
                if (!in_array($dateStr, $candidates, true)) {
                    $candidates[] = $dateStr;
                }
                $cursor->addDay();
            }
            sort($candidates);
        }

        return $candidates;
    }
}
