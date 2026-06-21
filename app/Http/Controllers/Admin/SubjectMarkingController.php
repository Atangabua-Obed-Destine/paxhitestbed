<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Grade;
use App\Models\Program;
use App\Models\ResultContribution;
use App\Models\Section;
use App\Models\Session;
use App\Models\Semester;
use App\Models\StudentAttendance;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Models\SubjectMarking;
use App\Models\SubjectMarkingExamState;
use App\Services\Assessment\AssessmentWeightService;
use App\Services\Assessment\SubjectMarkingWorkflowService;
use App\User;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubjectMarkingController extends Controller
{
    protected $title, $route, $view, $path, $access;
    protected AssessmentWeightService $weightService;
    protected SubjectMarkingWorkflowService $workflowService;
    
    /**
     * Create a new controller instance.
     */
    public function __construct(
        AssessmentWeightService $weightService,
        SubjectMarkingWorkflowService $workflowService
    ) {
        // Module Data
        $this->title = trans_choice('module_subject_marking', 1);
        $this->route = 'admin.subject-marking';
        $this->view = 'admin.subject-marking';
        $this->path = 'subject-marking';
        $this->access = 'subject';

        $this->weightService = $weightService;
        $this->workflowService = $workflowService;

        $this->middleware('permission:'.$this->access.'-marking', ['only' => ['index', 'store']]);
        $this->middleware('permission:'.$this->access.'-result', ['only' => ['result']]);
        $this->middleware('permission:subject-marking-submit|subject-marking-check|subject-marking-approve|subject-marking-publish', ['only' => ['transition']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $faculty = $program = $session = $semester = $section = $type = $subject = '0';

        $data = [
            'title' => $this->title,
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'access' => $this->access,
            'workflowMeta' => [],
        ];

        if (!empty($request->faculty)) {
            $faculty = $request->faculty;
        }
        if (!empty($request->program)) {
            $program = $request->program;
        }
        if (!empty($request->session)) {
            $session = $request->session;
        }
        if (!empty($request->semester)) {
            $semester = $request->semester;
        }
        if (!empty($request->section)) {
            $section = $request->section;
        }
        if (!empty($request->type)) {
            $type = $request->type;
        }
        if (!empty($request->subject)) {
            $subject = $request->subject;
        }

        $data['selected_faculty'] = $faculty;
        $data['selected_program'] = $program;
        $data['selected_session'] = $session;
        $data['selected_semester'] = $semester;
        $data['selected_section'] = $section;
        $data['selected_type'] = $type;
        $data['selected_subject'] = $subject;

        // Filter Search
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['examTypes'] = ExamType::where('status', '1')->orderBy('contribution', 'desc')->get();
        
        // Get course-specific result contributions if subject is selected
        if (!empty($request->subject) && $request->subject != '0') {
            $contributionsData = \App\Services\ResultContributionService::getSubjectContributions($subject);
            if ($contributionsData['configured']) {
                $data['resultContributions'] = (object)[
                    'attendances' => $contributionsData['attendance'],
                    'assignments' => $contributionsData['assignment'],
                    'activities' => $contributionsData['activity'],
                ];
                $data['examTypeContributions'] = $contributionsData['exam_types'];
            } else {
                $data['resultContributions'] = null;
                $data['examTypeContributions'] = [];
            }
        } else {
            $data['resultContributions'] = null;
            $data['examTypeContributions'] = [];
        }

        if (!empty($request->faculty) && $request->faculty != '0') {
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }

        if (!empty($request->program) && $request->program != '0') {
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();
        }

        if (!empty($request->program) && $request->program != '0') {
            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['semesters'] = $semesters->orderBy('id', 'asc')->get();
        }

        if (!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0') {
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester) {
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        if (!empty($request->program) && $request->program != '0' && !empty($request->session) && $request->session != '0') {
            $authUser = Auth::guard('web')->user();
            $teacherId = $authUser->id;
            $superAdmin = $authUser->hasRole('Super Admin');
            
            // Check if user has staff assignments
            $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacherId)->exists();

            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($teacherId, $session, $superAdmin, $hasAssignments) {
                if (isset($session)) {
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if (!$superAdmin && !$hasAssignments) {
                    $query->where('teacher_id', $teacherId);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            
            // Apply staff assignment filter
            $subjects = StaffAssignmentService::filterCourses($subjects);
            
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }


        if (!empty($request->program) && !empty($request->session) && !empty($request->subject)) {
            $authUser = Auth::guard('web')->user();
            $teacherId = $authUser->id;
            $superAdmin = $authUser->hasRole('Super Admin');
            
            // Check if user has staff assignments
            $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacherId)->exists();

            $subjectCheck = Subject::where('id', $subject);
            $subjectCheck->with('classes')->whereHas('classes', function ($query) use ($teacherId, $session, $superAdmin, $hasAssignments) {
                if (isset($session)) {
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if (!$superAdmin && !$hasAssignments) {
                    $query->where('teacher_id', $teacherId);
                }
            })->firstOrFail();

            $enrolls = StudentEnroll::query();
            $enrolls->whereIn('status', [1, 2]); // Active and Completed enrollments
            if (!empty($request->session) && $request->session != '0') {
                $enrolls->where('session_id', $session);
            }
            if (!empty($request->program) && $request->program != '0') {
                $enrolls->where('program_id', $program);
            }
            if (!empty($request->semester) && $request->semester != '0') {
                $enrolls->where('semester_id', $semester);
            }
            if (!empty($request->section) && $request->section != '0') {
                $enrolls->where('section_id', $section);
            }
            if (!empty($request->subject) && $request->subject != '0') {
                $enrolls->with('exams')->whereHas('exams', function ($query) use ($subject) {
                    $query->where('subject_id', $subject);
                });
            }
            $enrolls->with('student')->whereHas('student', function ($query) {
                $query->orderBy('matricule', 'asc');
            });

            $rows = $enrolls->get();

            // Group by unique matricule - keep only latest enrollment per matricule
            $uniqueMatricules = $rows->groupBy('matricule')->map(function($group) {
                return $group->sortByDesc('id')->first();
            })->values();

            $data['rows'] = $uniqueMatricules->sortBy(function ($query) {
                return $query->matricule;
            })->all();
        }


        if (!empty($request->program) && !empty($request->session) && !empty($request->subject)) {
            $attendances = StudentAttendance::query();
            $attendances->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section) {
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

            if (!empty($request->subject) && $request->subject != '0') {
                $attendances->where('subject_id', $subject);
            }

            $data['studentAttendance'] = $attendances->orderBy('id', 'desc')->get();
        }


        if (!empty($request->subject)) {
            $data['subject'] = Subject::where('id', $request->subject)->first();
        }


        if (!empty($request->program) && !empty($request->session) && !empty($request->subject)) {
            $selectedSubjectId = (int) $subject;

            $markings = SubjectMarking::where('subject_id', $selectedSubjectId);

            $markings->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section) {
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

            $markings->with([
                'studentEnroll',
                'studentEnroll.student',
                'studentEnroll.exams' => function ($query) use ($selectedSubjectId) {
                    $query->where('subject_id', $selectedSubjectId)->with('type');
                },
                'examStates.examType',
            ]);

            $markingsCollection = $markings->get();

            foreach ($markingsCollection as $marking) {
                $examTypeIds = $marking->studentEnroll?->exams
                    ->where('subject_id', $selectedSubjectId)
                    ->pluck('exam_type_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($examTypeIds)) {
                    $this->workflowService->ensureExamStates($marking, $examTypeIds);
                }
            }

            $markingsCollection->load('examStates.examType');

            $data['markings'] = $markingsCollection;
            $data['workflowMeta'] = $markingsCollection->mapWithKeys(function (SubjectMarking $marking) {
                $overallState = $marking->workflow_state ?: SubjectMarking::STATE_DRAFT;
                $changedAt = $marking->state_changed_at;

                $examMeta = $marking->examStates->mapWithKeys(function (SubjectMarkingExamState $examState) {
                    $state = $examState->workflow_state ?: SubjectMarking::STATE_DRAFT;
                    $changedAt = $examState->state_changed_at;

                    return [
                        $examState->exam_type_id => [
                            'title' => $examState->examType->title ?? __('field_exam'),
                            'state' => $state,
                            'label' => $this->formatWorkflowState($state),
                            'badge' => $this->resolveWorkflowBadge($state),
                            'changed_at' => $examState->state_changed_at,
                            'changed_display' => $changedAt ? $changedAt->format('d M Y H:i') : null,
                            'transitions' => $this->workflowService->getPermittedNextStatesForExamState($examState),
                        ],
                    ];
                })->all();

                return [
                    $marking->id => [
                        'overall' => [
                            'state' => $overallState,
                            'label' => $this->formatWorkflowState($overallState),
                            'badge' => $this->resolveWorkflowBadge($overallState),
                            'changed_at' => $marking->state_changed_at,
                            'changed_display' => $changedAt ? $changedAt->format('d M Y H:i') : null,
                        ],
                        'overall_transitions' => $this->workflowService->getPermittedNextStatesFor($marking),
                        'exam_types' => $examMeta,
                    ],
                ];
            })->all();
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
        $request->validate([
            'students' => 'required',
            'subjects' => 'required',
            'exam_marks' => 'required',
            'total_marks' => 'required',
            'publish_date' => 'required|date',
        ]);

        $studentIds = collect($request->students ?? [])->map(fn ($id) => (int) $id)->filter()->all();
        $subjectIds = collect($request->subjects ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->all();

        // Validate configuration for all subjects
        foreach ($subjectIds as $subId) {
            $contributionsData = \App\Services\ResultContributionService::getSubjectContributions($subId);
            if (!$contributionsData['configured']) {
                Flasher::addError(__('msg_mark_distribution_not_configured'), __('Error'));
                return redirect()->back();
            }
        }

        $enrollments = StudentEnroll::whereIn('id', $request->students ?? [])
            ->select(['id', 'program_id', 'semester_id'])
            ->get()
            ->keyBy('id');

        $studentIds = collect($request->students ?? [])->map(fn ($id) => (int) $id)->filter()->all();
        $subjectIds = collect($request->subjects ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->all();

        $examTypeLookup = Exam::whereIn('student_enroll_id', $studentIds)
            ->whereIn('subject_id', $subjectIds)
            ->select(['student_enroll_id', 'subject_id', 'exam_type_id'])
            ->get()
            ->groupBy(fn ($exam) => $exam->student_enroll_id.'-'.$exam->subject_id);

        $resolvedWeights = [];
        $now = Carbon::now();
        $currentUserId = Auth::guard('web')->id();

        foreach ($request->students as $key => $studentId) {
            $subjectId = (int) ($request->subjects[$key] ?? 0);
            $enroll = $enrollments->get((int) $studentId);

            if (!$enroll || !$subjectId) {
                continue;
            }

            $cacheKey = implode('-', [
                $enroll->program_id,
                $enroll->semester_id,
                $subjectId,
            ]);

            if (!isset($resolvedWeights[$cacheKey])) {
                $resolvedWeights[$cacheKey] = $this->weightService->resolve(
                    (int) $enroll->program_id,
                    $enroll->semester_id ? (int) $enroll->semester_id : null,
                    $subjectId,
                    $request->publish_date
                );
            }

            $weights = $resolvedWeights[$cacheKey];
            $totalMarks = (float) ($request->total_marks[$key] ?? 0);
            $validated = $totalMarks >= 50;

            $subjectMarking = SubjectMarking::firstOrNew([
                'student_enroll_id' => $studentId,
                'subject_id' => $subjectId,
            ]);

            $subjectMarking->exam_marks = $request->exam_marks[$key];
            $subjectMarking->attendances = $request->attendances[$key] ?? 0;
            $subjectMarking->assignments = $request->assignments[$key] ?? 0;
            $subjectMarking->activities = $request->activities[$key] ?? 0;
            $subjectMarking->total_marks = $totalMarks;
            $subjectMarking->publish_date = $request->publish_date;
            $subjectMarking->publish_time = $request->publish_time;
            $subjectMarking->resolved_exam_weight = $weights->get('exam_weight');
            $subjectMarking->resolved_ca_weight = $weights->get('ca_weight');
            $subjectMarking->resolved_attendance_weight = $weights->get('attendance_weight');
            $subjectMarking->validated = $validated;

            if (!$subjectMarking->exists) {
                $subjectMarking->workflow_state = SubjectMarking::STATE_DRAFT;
                $subjectMarking->state_changed_at = $now;
                $subjectMarking->state_changed_by = $currentUserId;
            }

            $subjectMarking->save();

            if ($subjectMarking->wasRecentlyCreated) {
                $subjectMarking->workflowLogs()->create([
                    'from_state' => null,
                    'to_state' => $subjectMarking->workflow_state,
                    'changed_by' => $currentUserId,
                    'changed_at' => $now,
                ]);
            }

            $lookupKey = $studentId.'-'.$subjectId;
            $examTypesForMarking = $examTypeLookup->get($lookupKey, collect())
                ->pluck('exam_type_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($examTypesForMarking)) {
                $this->workflowService->ensureExamStates($subjectMarking, $examTypesForMarking);
            }
        }

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    protected function formatWorkflowState(string $state): string
    {
        return Str::of($state)->replace('_', ' ')->title();
    }

    protected function resolveWorkflowBadge(string $state): string
    {
        return [
            SubjectMarking::STATE_DRAFT => 'secondary',
            SubjectMarking::STATE_SUBMITTED => 'info',
            SubjectMarking::STATE_CHECKED => 'warning',
            SubjectMarking::STATE_APPROVED => 'primary',
            SubjectMarking::STATE_PUBLISHED => 'success',
        ][$state] ?? 'secondary';
    }

    public function transition(Request $request, SubjectMarking $subjectMarking)
    {
        $request->validate([
            'state' => 'required|string',
            'notes' => 'nullable|string|max:500',
            'exam_type_id' => 'nullable|integer|exists:exam_types,id',
            'publish_date' => 'nullable|date',
            'publish_time' => 'nullable|date_format:H:i',
        ]);

        $examTypeId = $request->input('exam_type_id');
        $publishDate = $request->input('publish_date');
        $publishTime = $request->input('publish_time');

        try {
            if ($examTypeId) {
                $examState = $subjectMarking->examStates()->firstOrCreate([
                    'exam_type_id' => (int) $examTypeId,
                ], [
                    'workflow_state' => SubjectMarking::STATE_DRAFT,
                    'state_changed_at' => now(),
                    'state_changed_by' => Auth::guard('web')->id(),
                ]);

                $oldState = $examState->workflow_state ?: SubjectMarking::STATE_DRAFT;

                // Set publish date/time if provided
                if ($publishDate) {
                    $examState->publish_date = $publishDate;
                }
                if ($publishTime) {
                    $examState->publish_time = $publishTime;
                }
                if ($publishDate || $publishTime) {
                    $examState->save();
                }

                $this->workflowService->transitionExamState($examState, $request->input('state'), $request->input('notes'));
                
                // Reload to see final state after sync
                $examState->refresh();
                $subjectMarking->refresh();
                
                Flasher::addSuccess(
                    sprintf('Exam state: %s → %s. Overall marking: %s', 
                        ucfirst($oldState), 
                        ucfirst($examState->workflow_state), 
                        ucfirst($subjectMarking->workflow_state)
                    ), 
                    __('msg_success')
                );
            } else {
                // Set publish date/time for overall marking if provided
                if ($publishDate) {
                    $subjectMarking->publish_date = $publishDate;
                }
                if ($publishTime) {
                    $subjectMarking->publish_time = $publishTime;
                }
                if ($publishDate || $publishTime) {
                    $subjectMarking->save();
                }

                $this->workflowService->transition($subjectMarking, $request->input('state'), $request->input('notes'));
                
                Flasher::addSuccess(__('Overall marking updated successfully'), __('msg_success'));
            }

            // Auto-progression disabled - students now progress manually via header button
            // if ($request->input('state') === SubjectMarking::STATE_PUBLISHED) {
            //     $this->checkAutomaticProgression($subjectMarking);
            // }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Flasher::addError($e->getMessage(), __('Validation Error'));
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Flasher::addError($e->getMessage(), __('Authorization Error'));
            return redirect()->back();
        } catch (\Exception $e) {
            Flasher::addError('Transition failed: ' . $e->getMessage(), __('Error'));
            \Log::error('Subject Marking Transition Error: ' . $e->getMessage(), [
                'subject_marking_id' => $subjectMarking->id,
                'exam_type_id' => $examTypeId,
                'state' => $request->input('state'),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back();
        }

        return redirect()->back();
    }

    /**
     * Check if student should be automatically progressed to next semester
     * Called after marks are published
     */
    protected function checkAutomaticProgression(SubjectMarking $subjectMarking)
    {
        try {
            $enrollment = $subjectMarking->studentEnroll;
            
            if (!$enrollment) {
                return;
            }

            $progressionService = app(\App\Services\Academic\SemesterProgressionService::class);
            
            // Check eligibility
            $eligibility = $progressionService->checkProgressionEligibility($enrollment);
            
            if ($eligibility['eligible']) {
                // Attempt automatic progression
                $result = $progressionService->attemptAutomaticProgression($enrollment);
                
                if ($result['progressed']) {
                    \Log::info("Student automatically progressed", [
                        'student_id' => $enrollment->student_id,
                        'from_semester' => $enrollment->semester_id,
                        'to_semester' => $eligibility['next_semester']->id,
                        'new_enrollment_id' => $result['new_enrollment']->id ?? null,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail the main transaction
            \Log::error('Automatic progression check failed: ' . $e->getMessage(), [
                'subject_marking_id' => $subjectMarking->id,
            ]);
        }
    }

    /**
     * Autosave a single student's marks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function autosave(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'exam_marks' => 'required|numeric|min:0',
            'attendances' => 'nullable|numeric|min:0',
            'assignments' => 'nullable|numeric|min:0',
            'activities' => 'nullable|numeric|min:0',
            'publish_date' => 'nullable|date',
            'publish_time' => 'nullable',
        ]);

        try {
            $studentId = (int) $request->student_id;
            $subjectId = (int) $request->subject_id;

            // Validate configuration
            $contributionsData = \App\Services\ResultContributionService::getSubjectContributions($subjectId);
            if (!$contributionsData['configured']) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('msg_mark_distribution_not_configured')
                ], 422);
            }

            $enroll = StudentEnroll::where('id', $studentId)->firstOrFail();

            // Resolve weights
            $weights = $this->weightService->resolve(
                (int) $enroll->program_id,
                $enroll->semester_id ? (int) $enroll->semester_id : null,
                $subjectId,
                $request->publish_date
            );

            // Calculate total
            $examMarks = (float) $request->exam_marks;
            $attendances = (float) ($request->attendances ?? 0);
            $assignments = (float) ($request->assignments ?? 0);
            $activities = (float) ($request->activities ?? 0);
            
            $totalMarks = $examMarks + $attendances + $assignments + $activities;
            $validated = $totalMarks >= 50;

            $subjectMarking = SubjectMarking::firstOrNew([
                'student_enroll_id' => $studentId,
                'subject_id' => $subjectId,
            ]);

            // Check if anything actually changed to avoid unnecessary writes/logs
            if ($subjectMarking->exists && 
                (float)$subjectMarking->exam_marks == $examMarks &&
                (float)$subjectMarking->attendances == $attendances &&
                (float)$subjectMarking->assignments == $assignments &&
                (float)$subjectMarking->activities == $activities &&
                (float)$subjectMarking->total_marks == $totalMarks) {
                return response()->json(['message' => 'No changes detected', 'status' => 'success']);
            }

            $subjectMarking->exam_marks = $examMarks;
            $subjectMarking->attendances = $attendances;
            $subjectMarking->assignments = $assignments;
            $subjectMarking->activities = $activities;
            $subjectMarking->total_marks = $totalMarks;
            
            if ($request->publish_date) {
                $subjectMarking->publish_date = $request->publish_date;
            }
            if ($request->publish_time) {
                $subjectMarking->publish_time = $request->publish_time;
            }

            $subjectMarking->resolved_exam_weight = $weights->get('exam_weight');
            $subjectMarking->resolved_ca_weight = $weights->get('ca_weight');
            $subjectMarking->resolved_attendance_weight = $weights->get('attendance_weight');
            $subjectMarking->validated = $validated;

            if (!$subjectMarking->exists) {
                $subjectMarking->workflow_state = SubjectMarking::STATE_DRAFT;
                $subjectMarking->state_changed_at = now();
                $subjectMarking->state_changed_by = Auth::guard('web')->id();
            }

            $subjectMarking->save();

            if ($subjectMarking->wasRecentlyCreated) {
                $subjectMarking->workflowLogs()->create([
                    'from_state' => null,
                    'to_state' => $subjectMarking->workflow_state,
                    'changed_by' => Auth::guard('web')->id(),
                    'changed_at' => now(),
                ]);
            }

            // Ensure exam states exist
            $examTypeIds = Exam::where('student_enroll_id', $studentId)
                ->where('subject_id', $subjectId)
                ->pluck('exam_type_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($examTypeIds)) {
                $this->workflowService->ensureExamStates($subjectMarking, $examTypeIds);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Saved',
                'total_marks' => $totalMarks,
                'last_updated' => now()->format('H:i:s')
            ]);

        } catch (\Exception $e) {
            \Log::error('Autosave failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Save failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get history logs for a subject marking.
     *
     * @param  \App\Models\SubjectMarking  $subjectMarking
     * @return \Illuminate\Http\Response
     */
    public function history(SubjectMarking $subjectMarking)
    {
        $logs = $subjectMarking->workflowLogs()
            ->with(['changer', 'examType'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $action = $log->from_state ? 
                    "Changed status from " . ucfirst($log->from_state) . " to " . ucfirst($log->to_state) :
                    "Set status to " . ucfirst($log->to_state);
                
                if ($log->examType) {
                    $action .= " (" . $log->examType->title . ")";
                } else {
                    $action .= " (Overall)";
                }

                return [
                    'date' => $log->created_at->format('d M Y H:i'),
                    'user' => $log->changer->first_name . ' ' . $log->changer->last_name ?? 'System',
                    'action' => $action,
                    'note' => $log->notes
                ];
            });

        return response()->json($logs);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function result(Request $request)
    {
        //
        $data['title'] = trans_choice('module_subject_result', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


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


        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['examTypes'] = ExamType::where('status', '1')->orderBy('contribution', 'desc')->get();
        
        // Get course-specific result contributions if subject is selected
        if (!empty($request->subject) && $request->subject != '0') {
            $contributionsData = \App\Services\ResultContributionService::getSubjectContributions($subject);
            if ($contributionsData['configured']) {
                $data['resultContributions'] = (object)[
                    'attendances' => $contributionsData['attendance'],
                    'assignments' => $contributionsData['assignment'],
                    'activities' => $contributionsData['activity'],
                ];
                $data['examTypeContributions'] = $contributionsData['exam_types'];
            } else {
                $data['resultContributions'] = null;
                $data['examTypeContributions'] = [];
            }
        } else {
            $data['resultContributions'] = null;
            $data['examTypeContributions'] = [];
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


        // Filter Marks
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject)){

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


            // Marks
            $markings = SubjectMarking::where('subject_id', $subject);

            $markings->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
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

            $markings->with('studentEnroll.student')->whereHas('studentEnroll.student', function ($query){
                $query->orderBy('matricule', 'asc');
            });

            $rows = $markings->get();

            // Group by unique matricule - keep only latest marking per matricule
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
     * Bulk transition for multiple subject markings by exam type.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkTransition(Request $request)
    {
        // Filter out zero values before validation
        $data = $request->all();
        if (isset($data['section_id']) && $data['section_id'] == '0') {
            unset($data['section_id']);
        }
        if (isset($data['semester_id']) && $data['semester_id'] == '0') {
            unset($data['semester_id']);
        }
        
        $request->merge($data);
        
        $validated = $request->validate([
            'state' => 'required|string',
            'notes' => 'nullable|string|max:500',
            'exam_type_id' => 'required|integer|exists:exam_types,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'program_id' => 'required|integer|exists:programs,id',
            'session_id' => 'required|integer|exists:sessions,id',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'section_id' => 'nullable|integer|exists:sections,id',
            'publish_date' => 'nullable|date',
            'publish_time' => 'nullable|date_format:H:i',
        ]);

        try {
            // Build query for student enrollments
            $query = \App\Models\StudentEnroll::where('program_id', $request->program_id)
                ->where('session_id', $request->session_id)
                ->whereIn('status', [1, 2]); // Active and Completed enrollments
            
            // Add optional filters
            if ($request->filled('semester_id') && $request->semester_id != '0') {
                $query->where('semester_id', $request->semester_id);
            }
            if ($request->filled('section_id') && $request->section_id != '0') {
                $query->where('section_id', $request->section_id);
            }
            
            $enrollments = $query->get();

            $successCount = 0;
            $failCount = 0;
            $errors = [];

            foreach ($enrollments as $enrollment) {
                try {
                    // Find or create subject marking record
                    $subjectMarking = SubjectMarking::firstOrCreate([
                        'student_enroll_id' => $enrollment->id,
                        'subject_id' => $request->subject_id,
                    ], [
                        'total_marks' => 0,
                        'workflow_state' => SubjectMarking::STATE_DRAFT,
                    ]);

                    // Find or create exam state
                    $examState = $subjectMarking->examStates()->firstOrCreate([
                        'exam_type_id' => (int) $request->exam_type_id,
                    ], [
                        'workflow_state' => SubjectMarking::STATE_DRAFT,
                        'state_changed_at' => now(),
                        'state_changed_by' => Auth::guard('web')->id(),
                    ]);

                    // Set publish date/time if provided
                    if ($request->publish_date) {
                        $examState->publish_date = $request->publish_date;
                    }
                    if ($request->publish_time) {
                        $examState->publish_time = $request->publish_time;
                    }
                    if ($request->publish_date || $request->publish_time) {
                        $examState->save();
                    }

                    // Check if transition is possible
                    $currentState = $examState->workflow_state ?: SubjectMarking::STATE_DRAFT;
                    
                    // If already in target state, skip
                    if ($currentState === $request->state) {
                        // Already in state
                        continue;
                    }

                    // Check if transition is valid
                    if ($this->workflowService->canTransition($currentState, $request->state)) {
                        $this->workflowService->transitionExamState($examState, $request->state, $request->notes);
                        $successCount++;
                    } else {
                        // Cannot transition (e.g. trying to Submit but already Approved, or skipping steps)
                        // We count this as a "skip" rather than a failure to avoid alarming the user
                        // for mixed-state batches.
                    }
                    
                } catch (\Exception $e) {
                    $failCount++;
                    $identifier = $enrollment->matricule ?? $enrollment->student->student_id;
                    $errors[] = "Student #{$identifier}: " . $e->getMessage();
                    \Log::error('Bulk transition failed for student: ' . $enrollment->id, [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if ($successCount > 0) {
                Flasher::addSuccess(
                    sprintf('Successfully transitioned %d student(s) to %s', $successCount, ucfirst($request->state)),
                    __('msg_success')
                );
            } else if ($failCount == 0) {
                // If no successes and no failures, it means everyone was skipped (already done or invalid state)
                Flasher::addInfo(
                    sprintf('No students were transitioned. They may already be in %s state or not ready for this transition.', ucfirst($request->state)),
                    __('Information')
                );
            }

            if ($failCount > 0) {
                Flasher::addWarning(
                    sprintf('Failed to transition %d student(s). Check logs for details.', $failCount),
                    __('Partial Success')
                );
            }

        } catch (\Exception $e) {
            Flasher::addError('Bulk transition failed: ' . $e->getMessage(), __('Error'));
            \Log::error('Bulk Transition Error: ' . $e->getMessage(), [
                'exam_type_id' => $request->exam_type_id,
                'subject_id' => $request->subject_id,
                'state' => $request->state,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return redirect()->back();
    }

    /**
     * Unpublish an individual student's result.
     */
    public function unpublishStudent(Request $request, SubjectMarking $subjectMarking)
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        // Permission check - only super admin, HOD, or exam officers
        if (!$this->canManagePublishOverride()) {
            Flasher::addError('You do not have permission to unpublish individual results.', __('Permission Denied'));
            return redirect()->back();
        }

        // Check if section is published
        if ($subjectMarking->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
            Flasher::addWarning('This section is not yet published. Cannot unpublish individual student.', __('Invalid State'));
            return redirect()->back();
        }

        // Check if already unpublished
        if ($subjectMarking->is_published_override === false) {
            Flasher::addWarning('This student result is already unpublished.', __('Already Unpublished'));
            return redirect()->back();
        }

        try {
            $previousState = $subjectMarking->is_published_override === true ? 'force_published' : 'following_workflow';

            // Update subject marking
            $subjectMarking->update([
                'is_published_override' => false,
                'unpublish_reason' => $request->reason,
                'unpublished_by' => Auth::id(),
                'unpublished_at' => now(),
            ]);

            // Log the action
            \App\Models\SubjectMarkingPublishLog::create([
                'subject_marking_id' => $subjectMarking->id,
                'action' => 'unpublish',
                'reason' => $request->reason,
                'performed_by' => Auth::id(),
                'previous_state' => $previousState,
                'new_state' => 'unpublished',
            ]);

            $studentName = $subjectMarking->studentEnroll->student->name ?? 'Student';
            Flasher::addSuccess(
                "Result for {$studentName} has been unpublished successfully.",
                __('Result Unpublished')
            );

        } catch (\Exception $e) {
            Flasher::addError('Failed to unpublish result: ' . $e->getMessage(), __('Error'));
            \Log::error('Unpublish Student Error: ' . $e->getMessage(), [
                'subject_marking_id' => $subjectMarking->id,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return redirect()->back();
    }

    /**
     * Republish an individual student's result.
     */
    public function republishStudent(Request $request, SubjectMarking $subjectMarking)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Permission check
        if (!$this->canManagePublishOverride()) {
            Flasher::addError('You do not have permission to republish individual results.', __('Permission Denied'));
            return redirect()->back();
        }

        // Check if currently unpublished
        if ($subjectMarking->is_published_override !== false) {
            Flasher::addWarning('This student result is not unpublished.', __('Invalid State'));
            return redirect()->back();
        }

        try {
            // Update subject marking - set to NULL to follow workflow
            $subjectMarking->update([
                'is_published_override' => null,
                'republished_by' => Auth::id(),
                'republished_at' => now(),
            ]);

            // Log the action
            \App\Models\SubjectMarkingPublishLog::create([
                'subject_marking_id' => $subjectMarking->id,
                'action' => 'republish',
                'reason' => $request->reason ?? 'Result issue resolved',
                'performed_by' => Auth::id(),
                'previous_state' => 'unpublished',
                'new_state' => 'following_workflow',
            ]);

            $studentName = $subjectMarking->studentEnroll->student->name ?? 'Student';
            Flasher::addSuccess(
                "Result for {$studentName} has been republished successfully.",
                __('Result Republished')
            );

        } catch (\Exception $e) {
            Flasher::addError('Failed to republish result: ' . $e->getMessage(), __('Error'));
            \Log::error('Republish Student Error: ' . $e->getMessage(), [
                'subject_marking_id' => $subjectMarking->id,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return redirect()->back();
    }

    /**
     * Check if current user can manage publish overrides.
     */
    private function canManagePublishOverride()
    {
        $user = Auth::user();

        // Super admin always allowed
        if ($user->is_superadmin) {
            return true;
        }

        // Check for specific permissions
        return $user->hasPermissionTo('subject-marking-unpublish') || 
               $user->hasRole(['HOD', 'Exam Officer', 'Academic Dean']);
    }
}
