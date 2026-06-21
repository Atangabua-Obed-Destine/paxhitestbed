<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\EnrollSubject;
use App\Models\ExamPublishingState;
use App\Models\ExamPublishingWorkflowLog;
use App\Models\ExamType;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\Notice;
use App\Models\NoticeCategory;
use App\Notifications\NoticeNotification;
use App\Models\Program;
use App\Models\ResitRequest;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Models\SubjectMarking;
use App\Models\SubjectMarkingExamState;
use App\Notifications\ExamPublishingNotification;
use App\Services\ResultContributionService;
use App\Services\StaffAssignmentService;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ExamPublishingController extends Controller
{
    protected $title, $route, $view, $path, $access;

    /**
     * Permission requirements for each workflow state.
     */
    protected array $statePermissions = [
        ExamPublishingState::STATE_SUBMITTED => 'exam-publishing-submit',
        ExamPublishingState::STATE_CHECKED => 'exam-publishing-check',
        ExamPublishingState::STATE_APPROVED => 'exam-publishing-approve',
        ExamPublishingState::STATE_PUBLISHED => 'exam-publishing-publish',
    ];

    /**
     * Workflow transitions map.
     */
    protected array $transitions = [
        ExamPublishingState::STATE_DRAFT => [ExamPublishingState::STATE_SUBMITTED],
        ExamPublishingState::STATE_SUBMITTED => [ExamPublishingState::STATE_CHECKED],
        ExamPublishingState::STATE_CHECKED => [ExamPublishingState::STATE_APPROVED],
        ExamPublishingState::STATE_APPROVED => [ExamPublishingState::STATE_PUBLISHED],
        ExamPublishingState::STATE_PUBLISHED => [],
    ];

    public function __construct()
    {
        $this->title = __('Exam Publishing');
        $this->route = 'admin.exam-publishing';
        $this->view = 'admin.exam-publishing';
        $this->path = 'exam-publishing';
        $this->access = 'exam-publishing';

        $this->middleware('permission:' . $this->access . '-view', ['only' => ['index']]);
        $this->middleware('permission:' . $this->access . '-submit', ['only' => ['transition', 'bulkTransition']]);
    }

    /**
     * Display the exam publishing page.
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Selection parameters
        $data['selected_faculty'] = $faculty = $request->faculty ?? null;
        $data['selected_program'] = $program = $request->program ?? null;
        $data['selected_session'] = $session = $request->session ?? null;
        $data['selected_semester'] = $semester = $request->semester ?? null;
        $data['selected_section'] = $section = $request->section ?? null;
        $data['selected_type'] = $type = $request->type ?? null;
        $data['multi_mode'] = $multiMode = (bool) ($request->multi ?? false);

        // Get filter options
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        
        // Exam types - separate CA and Final
        $data['types'] = ExamType::where('status', '1')->orderBy('is_final', 'asc')->orderBy('title', 'asc')->get();
        $data['selected_exam_type'] = null;
        
        if (!empty($type) && $type != '0') {
            $data['selected_exam_type'] = ExamType::where('status', '1')->find($type);
        }

        // Cascading filters
        if (!empty($faculty) && $faculty != '0') {
            $programQuery = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc');
            $data['programs'] = StaffAssignmentService::filterPrograms($programQuery)->get();
        }

        if (!empty($program) && $program != '0') {
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['semesters'] = $semesters->orderBy('id', 'asc')->get();
        }

        if (!empty($program) && $program != '0' && !empty($semester) && $semester != '0') {
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester) {
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        // Grades for reference
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Default values for variables that may not be set if filters aren't selected
        $data['is_final_exam'] = false;
        $data['ca_published'] = true;
        $data['ca_publishing_status'] = [];
        $data['faculty_groups'] = [];

        // ==========================================
        // MULTI-PROGRAM MODE: Load ALL programs
        // ==========================================
        if ($multiMode && !empty($session) && !empty($semester) && !empty($type)) {
            $data['faculty_groups'] = $this->loadMultiProgramData(
                (int) $session,
                (int) $semester,
                (int) $type
            );

            // Compute global stats for multi-mode
            $globalStats = [
                'total_programs' => 0,
                'total_courses' => 0,
                'total_students' => 0,
                'draft' => 0,
                'submitted' => 0,
                'checked' => 0,
                'approved' => 0,
                'published' => 0,
            ];
            foreach ($data['faculty_groups'] as $fg) {
                foreach ($fg['programs'] as $pg) {
                    $globalStats['total_programs']++;
                    $globalStats['total_courses'] += count($pg['subjects']);
                    $globalStats['total_students'] += $pg['total_students'];
                    foreach ($pg['subject_states'] as $state) {
                        $ws = $state->workflow_state ?? 'draft';
                        if (isset($globalStats[$ws])) {
                            $globalStats[$ws]++;
                        }
                    }
                }
            }
            $data['global_stats'] = $globalStats;

            // Set exam type info for multi-mode
            $examType = ExamType::find($type);
            $data['exam_type'] = $examType;
            $data['is_final_exam'] = $examType && $examType->is_final;

            return view($this->view . '.index', $data);
        }

        // ==========================================
        // SINGLE PROGRAM MODE (existing behavior)
        // ==========================================
        if (!empty($program) && !empty($session) && !empty($semester) && !empty($type)) {
            $data = array_merge($data, $this->loadPublishingData(
                (int) $program,
                (int) $session,
                (int) $semester,
                $section ? (int) $section : null,
                (int) $type
            ));

            // Generate draft results preview (full results matrix without publication check)
            $draftPreview = $this->generateDraftResultsPreview(
                (int) $program,
                (int) $session,
                (int) $semester,
                $section ? (int) $section : null
            );
            if ($draftPreview) {
                $data = array_merge($data, $draftPreview);
            }
        }

        return view($this->view . '.index', $data);
    }

    /**
     * Load publishing data for ALL programs across ALL faculties.
     * Groups data by Faculty → Program.
     */
    protected function loadMultiProgramData(int $sessionId, int $semesterId, int $examTypeId): array
    {
        $facultyGroups = [];

        // Get all faculties the user has access to
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $faculties = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        foreach ($faculties as $faculty) {
            $programQuery = Program::where('faculty_id', $faculty->id)
                ->where('status', '1')
                ->orderBy('title', 'asc');
            $programs = StaffAssignmentService::filterPrograms($programQuery)->get();

            $programDataCollection = [];

            foreach ($programs as $program) {
                // Check if this program has enrollments for this session/semester
                $hasEnrollments = StudentEnroll::where('program_id', $program->id)
                    ->where('session_id', $sessionId)
                    ->where('semester_id', $semesterId)
                    ->whereIn('status', [1, 2])
                    ->exists();

                if (!$hasEnrollments) {
                    continue; // Skip programs with no enrollments
                }

                try {
                    $publishingData = $this->loadPublishingData(
                        $program->id,
                        $sessionId,
                        $semesterId,
                        null, // No section filter in multi-mode
                        $examTypeId
                    );

                    // Only include programs that have subjects
                    if (!empty($publishingData['subjects']) && count($publishingData['subjects']) > 0) {
                        $programDataCollection[$program->id] = [
                            'program' => $program,
                            'subjects' => $publishingData['subjects'],
                            'subject_states' => $publishingData['subject_states'],
                            'subject_stats' => $publishingData['subject_stats'],
                            'total_students' => $publishingData['total_students'],
                            'is_final_exam' => $publishingData['is_final_exam'],
                            'ca_published' => $publishingData['ca_published'] ?? true,
                            'ca_publishing_status' => $publishingData['ca_publishing_status'] ?? [],
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Multi-program load skipped for program ' . $program->id . ': ' . $e->getMessage());
                    continue;
                }
            }

            if (!empty($programDataCollection)) {
                $facultyGroups[$faculty->id] = [
                    'faculty' => $faculty,
                    'programs' => $programDataCollection,
                ];
            }
        }

        return $facultyGroups;
    }

    /**
     * Download Courses Publishing Status as PDF.
     */
    public function downloadPdf(Request $request)
    {
        // Validate required parameters
        $request->validate([
            'faculty' => 'required|exists:faculties,id',
            'program' => 'required|exists:programs,id',
            'session' => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
            'type' => 'required|exists:exam_types,id',
        ]);

        $faculty = Faculty::find($request->faculty);
        $program = Program::find($request->program);
        $session = Session::find($request->session);
        $semester = Semester::find($request->semester);
        $section = $request->section ? Section::find($request->section) : null;
        $examType = ExamType::find($request->type);

        // Load publishing data
        $publishingData = $this->loadPublishingData(
            (int) $request->program,
            (int) $request->session,
            (int) $request->semester,
            $request->section ? (int) $request->section : null,
            (int) $request->type
        );

        // Get setting for institution info
        $setting = \App\Models\Setting::where('status', '1')->first();

        // Calculate summary statistics
        $totalCourses = count($publishingData['subjects']);
        $draftCount = collect($publishingData['subject_states'])->where('workflow_state', 'draft')->count();
        $submittedCount = collect($publishingData['subject_states'])->where('workflow_state', 'submitted')->count();
        $checkedCount = collect($publishingData['subject_states'])->where('workflow_state', 'checked')->count();
        $approvedCount = collect($publishingData['subject_states'])->where('workflow_state', 'approved')->count();
        $publishedCount = collect($publishingData['subject_states'])->where('workflow_state', 'published')->count();

        // Calculate overall pass/fail stats
        $overallPassed = 0;
        $overallFailed = 0;
        $overallWithMarks = 0;
        foreach ($publishingData['subject_stats'] as $stats) {
            $overallPassed += $stats['passed'];
            $overallFailed += $stats['failed'];
            $overallWithMarks += $stats['with_marks'];
        }

        $data = [
            'title' => __('Courses Publishing Status Report'),
            'setting' => $setting,
            'faculty' => $faculty,
            'program' => $program,
            'session' => $session,
            'semester' => $semester,
            'section' => $section,
            'exam_type' => $examType,
            'subjects' => $publishingData['subjects'],
            'subject_states' => $publishingData['subject_states'],
            'subject_stats' => $publishingData['subject_stats'],
            'is_final_exam' => $publishingData['is_final_exam'],
            'ca_publishing_status' => $publishingData['ca_publishing_status'] ?? [],
            'total_students' => $publishingData['total_students'],
            'total_courses' => $totalCourses,
            'draft_count' => $draftCount,
            'submitted_count' => $submittedCount,
            'checked_count' => $checkedCount,
            'approved_count' => $approvedCount,
            'published_count' => $publishedCount,
            'overall_passed' => $overallPassed,
            'overall_failed' => $overallFailed,
            'overall_with_marks' => $overallWithMarks,
            'generated_at' => now(),
            'generated_by' => Auth::guard('web')->user(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.exam-publishing.pdf', $data);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'courses_publishing_status_' . $program->code . '_' . $semester->title . '_' . $examType->title . '_' . date('Y-m-d_His') . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Download Student Marks Preview as detailed PDF.
     */
    public function downloadMarksPdf(Request $request)
    {
        // Validate required parameters
        $request->validate([
            'faculty' => 'required|exists:faculties,id',
            'program' => 'required|exists:programs,id',
            'session' => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
            'type' => 'required|exists:exam_types,id',
        ]);

        $faculty = Faculty::find($request->faculty);
        $program = Program::find($request->program);
        $session = Session::find($request->session);
        $semester = Semester::find($request->semester);
        $section = $request->section ? Section::find($request->section) : null;
        $examType = ExamType::find($request->type);

        // Load publishing data
        $publishingData = $this->loadPublishingData(
            (int) $request->program,
            (int) $request->session,
            (int) $request->semester,
            $request->section ? (int) $request->section : null,
            (int) $request->type
        );

        // Get setting for institution info
        $setting = \App\Models\Setting::where('status', '1')->first();

        // Get grades for legend
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Calculate detailed statistics per student
        $studentStats = [];
        foreach ($publishingData['students'] as $student) {
            $totalCourses = 0;
            $coursesWithMarks = 0;
            $coursesPassed = 0;
            $coursesFailed = 0;
            $totalMarks = 0;
            $totalContribution = 0;

            foreach ($publishingData['subjects'] as $subject) {
                $courseData = $student['courses'][$subject->id] ?? null;
                if ($courseData && $courseData['registered']) {
                    $totalCourses++;
                    if ($courseData['has_marks']) {
                        $coursesWithMarks++;
                        $totalMarks += $courseData['marks'];
                        $totalContribution += $courseData['contribution'];
                        
                        if ($courseData['exam_type_pass_fail'] === 'pass') {
                            $coursesPassed++;
                        } elseif ($courseData['exam_type_pass_fail'] === 'fail') {
                            $coursesFailed++;
                        }
                    }
                }
            }

            $studentStats[$student['enroll_id']] = [
                'total_courses' => $totalCourses,
                'courses_with_marks' => $coursesWithMarks,
                'courses_passed' => $coursesPassed,
                'courses_failed' => $coursesFailed,
                'total_marks' => $totalMarks,
                'total_contribution' => $totalContribution,
                'average' => $totalContribution > 0 ? round(($totalMarks / $totalContribution) * 100, 2) : 0,
                'pass_rate' => $coursesWithMarks > 0 ? round(($coursesPassed / $coursesWithMarks) * 100, 2) : 0,
            ];
        }

        // Class statistics
        $classStats = [
            'total_students' => count($publishingData['students']),
            'students_all_passed' => 0,
            'students_some_failed' => 0,
            'students_no_marks' => 0,
            'highest_average' => 0,
            'lowest_average' => 100,
            'class_average' => 0,
        ];

        $totalAverage = 0;
        $studentsWithAverage = 0;

        foreach ($studentStats as $stats) {
            if ($stats['courses_with_marks'] == 0) {
                $classStats['students_no_marks']++;
            } elseif ($stats['courses_failed'] == 0) {
                $classStats['students_all_passed']++;
            } else {
                $classStats['students_some_failed']++;
            }

            if ($stats['average'] > 0) {
                $totalAverage += $stats['average'];
                $studentsWithAverage++;
                $classStats['highest_average'] = max($classStats['highest_average'], $stats['average']);
                $classStats['lowest_average'] = min($classStats['lowest_average'], $stats['average']);
            }
        }

        $classStats['class_average'] = $studentsWithAverage > 0 ? round($totalAverage / $studentsWithAverage, 2) : 0;
        if ($classStats['lowest_average'] == 100) $classStats['lowest_average'] = 0;

        $data = [
            'title' => __('Student Marks Report'),
            'setting' => $setting,
            'faculty' => $faculty,
            'program' => $program,
            'session' => $session,
            'semester' => $semester,
            'section' => $section,
            'exam_type' => $examType,
            'subjects' => $publishingData['subjects'],
            'students' => $publishingData['students'],
            'subject_states' => $publishingData['subject_states'],
            'subject_stats' => $publishingData['subject_stats'],
            'student_stats' => $studentStats,
            'class_stats' => $classStats,
            'is_final_exam' => $publishingData['is_final_exam'],
            'grades' => $grades,
            'generated_at' => now(),
            'generated_by' => Auth::guard('web')->user(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.exam-publishing.marks-pdf', $data);
        $pdf->setPaper('A4', 'landscape');

        // Use shortcode if available, otherwise fall back to code
        $programCode = $program->shortcode ?? $program->code ?? 'PROG';
        // Sanitize for filename (remove special characters)
        $programCode = preg_replace('/[^A-Za-z0-9\-_]/', '_', $programCode);
        $semesterTitle = preg_replace('/[^A-Za-z0-9\-_]/', '_', $semester->title);
        $examTypeTitle = preg_replace('/[^A-Za-z0-9\-_]/', '_', $examType->title);
        
        $filename = 'student_marks_report_' . $programCode . '_' . $semesterTitle . '_' . $examTypeTitle . '_' . date('Y-m-d_His') . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Load the main publishing data - all courses and student marks.
     */
    protected function loadPublishingData(int $programId, int $sessionId, int $semesterId, ?int $sectionId, int $examTypeId): array
    {
        $examType = ExamType::find($examTypeId);
        $isFinalExam = $examType && $examType->is_final;

        // Get all subjects for this program/semester
        // First try EnrollSubject (specific course offerings), then fall back to program subjects
        $enrollSubjectQuery = \App\Models\EnrollSubject::where('program_id', $programId)
            ->where('semester_id', $semesterId)
            ->where('status', 1);
        
        if ($sectionId) {
            $enrollSubjectQuery->where('section_id', $sectionId);
        }
        
        $enrollSubjects = $enrollSubjectQuery->with('subjects')->get();
        
        // Collect all unique subjects from the EnrollSubject records
        $subjects = collect();
        foreach ($enrollSubjects as $enrollSubject) {
            foreach ($enrollSubject->subjects as $subject) {
                if ($subject->status == '1' && !$subjects->contains('id', $subject->id)) {
                    $subjects->push($subject);
                }
            }
        }
        
        // If no EnrollSubject records, fall back to getting subjects from the program
        // This matches how SubjectMarkingController works
        if ($subjects->isEmpty()) {
            $subjectQuery = Subject::where('status', '1')
                ->whereHas('programs', function ($query) use ($programId) {
                    $query->where('program_id', $programId);
                });
            
            // Apply staff assignment filter if needed
            $subjectQuery = StaffAssignmentService::filterCourses($subjectQuery);
            
            $subjects = $subjectQuery->orderBy('code', 'asc')->get();
        }
        
        $subjects = $subjects->sortBy('code')->values();

        // Get all student enrollments for this selection
        $enrollQuery = StudentEnroll::where('program_id', $programId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->whereIn('status', [1, 2]);

        if ($sectionId) {
            $enrollQuery->where('section_id', $sectionId);
        }

        $enrollments = $enrollQuery->with(['student', 'subjects'])->get()->sortBy(function ($enroll) {
            return $enroll->matricule ?? $enroll->student->student_id ?? '';
        });

        // Get grades for pass/fail determination
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $failGrades = $grades->filter(fn($g) => strtoupper($g->remark) === 'FAIL' || in_array(strtoupper($g->title), ['F', 'FF', 'IC']));
        $failMinMark = $failGrades->min('min_mark') ?? 0;
        $failMaxMark = $failGrades->max('max_mark') ?? 49;

        // Get CA exam types (is_final = 0) for the preview when viewing final exam
        $caExamTypes = [];
        if ($isFinalExam) {
            $caExamTypes = ExamType::where('status', '1')
                ->where('is_final', false)
                ->orderBy('title', 'asc')
                ->get();
        }

        // Build the data matrix
        $studentData = [];
        $subjectStates = [];
        $subjectStats = [];

        foreach ($subjects as $subject) {
            // Get all subject_markings for this subject/program/session/semester
            $markings = SubjectMarking::whereHas('studentEnroll', function ($q) use ($programId, $sessionId, $semesterId, $sectionId) {
                $q->where('program_id', $programId)
                  ->where('session_id', $sessionId)
                  ->where('semester_id', $semesterId);
                if ($sectionId) {
                    $q->where('section_id', $sectionId);
                }
            })
            ->where('subject_id', $subject->id)
            ->with(['examStates' => function ($q) use ($examTypeId) {
                $q->where('exam_type_id', $examTypeId);
            }])
            ->get();
            
            $totalMarkings = $markings->count();
            
            // Derive workflow state from subject_marking_exam_states for THIS SPECIFIC EXAM TYPE
            // NOT from the overall subject_markings.workflow_state
            $stateOrder = [
                ExamPublishingState::STATE_DRAFT => 0,
                ExamPublishingState::STATE_SUBMITTED => 1,
                ExamPublishingState::STATE_CHECKED => 2,
                ExamPublishingState::STATE_APPROVED => 3,
                ExamPublishingState::STATE_PUBLISHED => 4,
            ];
            
            $derivedState = ExamPublishingState::STATE_DRAFT;
            
            if ($totalMarkings > 0) {
                // Get the per-exam-type states for this exam type
                $examTypeStates = [];
                foreach ($markings as $marking) {
                    $examState = $marking->examStates->first(); // Already filtered by exam_type_id
                    if ($examState) {
                        $examTypeStates[] = $examState->workflow_state ?? ExamPublishingState::STATE_DRAFT;
                    } else {
                        // No exam state record exists for this exam type - treat as draft
                        $examTypeStates[] = ExamPublishingState::STATE_DRAFT;
                    }
                }
                
                // Find the lowest/most restrictive state among all students for THIS exam type
                $minOrder = 4; // Start with highest (published)
                $derivedState = ExamPublishingState::STATE_PUBLISHED;
                
                foreach ($examTypeStates as $state) {
                    $order = $stateOrder[$state] ?? 0;
                    if ($order < $minOrder) {
                        $minOrder = $order;
                        $derivedState = $state;
                    }
                }
                
                // If no exam states exist at all, check if there are marks in the exams table
                if (empty($examTypeStates) || count(array_filter($examTypeStates, fn($s) => $s !== ExamPublishingState::STATE_DRAFT)) === 0) {
                    // Check if there are actual exam marks for this exam type
                    $hasExamMarks = \App\Models\Exam::where('subject_id', $subject->id)
                        ->where('exam_type_id', $examTypeId)
                        ->whereIn('student_enroll_id', $markings->pluck('student_enroll_id'))
                        ->whereNotNull('achieve_marks')
                        ->exists();
                    
                    if ($hasExamMarks) {
                        $derivedState = ExamPublishingState::STATE_DRAFT; // Marks exist but not submitted yet
                    } else {
                        $derivedState = ExamPublishingState::STATE_DRAFT;
                    }
                }
            }
            
            // Get or create publishing state for this subject and SYNC with derived state
            $publishingState = ExamPublishingState::firstOrCreate([
                'program_id' => $programId,
                'session_id' => $sessionId,
                'semester_id' => $semesterId,
                'section_id' => $sectionId,
                'subject_id' => $subject->id,
                'exam_type_id' => $examTypeId,
            ], [
                'workflow_state' => $derivedState,
                'state_changed_at' => now(),
                'state_changed_by' => Auth::guard('web')->id(),
                'created_by' => Auth::guard('web')->id(),
            ]);
            
            // Always sync the publishing state with derived state from subject_marking_exam_states
            if ($publishingState->workflow_state !== $derivedState) {
                $publishingState->workflow_state = $derivedState;
                $publishingState->state_changed_at = now();
                $publishingState->save();
            }

            $subjectStates[$subject->id] = $publishingState;
            
            // Get contribution percentage for this subject/exam type
            // First try from ExamTypeContribution table
            $contribution = ResultContributionService::getExamTypeContribution($subject->id, $examTypeId);
            
            // If no contribution configured, try to get it from the exam records themselves
            if (!$contribution || $contribution == 0) {
                $sampleExam = Exam::where('subject_id', $subject->id)
                    ->where('exam_type_id', $examTypeId)
                    ->whereNotNull('contribution')
                    ->where('contribution', '>', 0)
                    ->first();
                $contribution = $sampleExam ? $sampleExam->contribution : ($examType->contribution ?? 100);
            }
            
            // Initialize stats
            $subjectStats[$subject->id] = [
                'total' => 0,
                'with_marks' => 0,
                'passed' => 0,
                'failed' => 0,
                'average' => 0,
                'sum' => 0,
                'contribution' => $contribution, // e.g., 30 for 30%
                'pass_mark' => $contribution * 0.5, // 50% of contribution as pass mark
            ];
        }

        // Build student data with marks per subject
        foreach ($enrollments as $enroll) {
            // Get registered subjects for this enrollment
            $registeredSubjectIds = $enroll->subjects->pluck('id')->toArray();
            
            $studentRow = [
                'enroll_id' => $enroll->id,
                'matricule' => $enroll->matricule ?? $enroll->student->student_id ?? 'N/A',
                'name' => $enroll->student->first_name . ' ' . $enroll->student->last_name,
                'courses' => [],
            ];

            foreach ($subjects as $subject) {
                // Check if student registered for this course
                $isRegistered = in_array($subject->id, $registeredSubjectIds);
                
                // Only count registered students in stats
                if ($isRegistered) {
                    $subjectStats[$subject->id]['total']++;
                }

                // Get exam marks for this student/subject/exam_type
                $examMark = Exam::where('student_enroll_id', $enroll->id)
                    ->where('subject_id', $subject->id)
                    ->where('exam_type_id', $examTypeId)
                    ->where('attendance', 1)
                    ->first();

                // Get subject marking for attendance/CA totals and workflow state
                $subjectMarking = SubjectMarking::where('student_enroll_id', $enroll->id)
                    ->where('subject_id', $subject->id)
                    ->with(['examStates' => function($q) use ($examTypeId) {
                        $q->where('exam_type_id', $examTypeId);
                    }])
                    ->first();

                // Get per-exam-type workflow state for this student
                $studentWorkflowState = 'draft';
                if ($subjectMarking) {
                    $examState = $subjectMarking->examStates->first();
                    $studentWorkflowState = $examState ? $examState->workflow_state : ($subjectMarking->workflow_state ?? 'draft');
                }

                // Get CA marks if viewing final exam
                $caMarks = [];
                if ($isFinalExam) {
                    foreach ($caExamTypes as $caType) {
                        $caExam = Exam::where('student_enroll_id', $enroll->id)
                            ->where('subject_id', $subject->id)
                            ->where('exam_type_id', $caType->id)
                            ->where('attendance', 1)
                            ->first();
                        
                        $caMarks[$caType->id] = [
                            'type' => $caType->title,
                            'marks' => $caExam ? $caExam->achieve_marks : null,
                            'contribution' => $caExam ? $caExam->contribution : $caType->contribution,
                        ];
                    }
                }

                // Determine pass/fail status
                // 1. For the SPECIFIC EXAM TYPE: marks >= 50% of contribution
                // 2. For OVERALL course: total_marks and grade configuration
                
                $examTypePassFail = null; // Pass/fail for this specific exam type (CA or Final)
                $overallPassFail = null; // Pass/fail for the overall course
                $studentGrade = null;
                
                // Check exam type specific pass/fail (using same logic as Courses Publishing Status)
                if ($examMark && $examMark->achieve_marks !== null) {
                    $contribution = $subjectStats[$subject->id]['contribution'];
                    $passMark = $subjectStats[$subject->id]['pass_mark']; // 50% of contribution
                    $examTypePassFail = $examMark->achieve_marks >= $passMark ? 'pass' : 'fail';
                }
                
                // Check overall course pass/fail (based on total marks and grade)
                $totalMarks = $subjectMarking ? $subjectMarking->total_marks : null;
                if ($totalMarks !== null && $totalMarks > 0) {
                    // Find the grade for this total
                    $studentGrade = $grades->first(function($g) use ($totalMarks) {
                        return $totalMarks >= $g->min_mark && $totalMarks <= $g->max_mark;
                    });
                    
                    if ($studentGrade) {
                        // Check if it's a failing grade (F, or grade point = 0, or remark = FAIL)
                        $isFailing = strtoupper($studentGrade->remark ?? '') === 'FAIL' 
                            || in_array(strtoupper($studentGrade->title), ['F', 'FF', 'IC'])
                            || $studentGrade->point == 0;
                        $overallPassFail = $isFailing ? 'fail' : 'pass';
                    } else {
                        // Fallback: if total marks < 50, consider failed
                        $overallPassFail = $totalMarks >= 50 ? 'pass' : 'fail';
                    }
                }

                // Prepare course data
                $courseData = [
                    'exam_id' => $examMark ? $examMark->id : null,
                    'has_marks' => $examMark && $examMark->achieve_marks !== null,
                    'marks' => $examMark ? $examMark->achieve_marks : null,
                    'contribution' => $examMark ? $examMark->contribution : ($examType->contribution ?? 0),
                    'pass_mark' => $subjectStats[$subject->id]['pass_mark'] ?? 0,
                    'attendance' => $subjectMarking ? $subjectMarking->attendances : null,
                    'ca_total' => $isFinalExam ? ($subjectMarking ? $subjectMarking->exam_marks : null) : null,
                    'ca_marks' => $caMarks,
                    'total' => $totalMarks,
                    'locked' => $examMark ? $examMark->marks_locked : false,
                    'registered' => $isRegistered,
                    'workflow_state' => $studentWorkflowState,
                    'exam_type_pass_fail' => $examTypePassFail, // Pass/fail for this specific exam type
                    'overall_pass_fail' => $overallPassFail, // Pass/fail for overall course
                    'grade' => $studentGrade ? $studentGrade->title : null,
                ];

                // Update stats - only for REGISTERED students
                // Marks are already in the contribution scale (e.g., 21.67 out of 30)
                if ($isRegistered && $courseData['has_marks']) {
                    $subjectStats[$subject->id]['with_marks']++;
                    
                    // Marks are already weighted/scaled to contribution, use them directly
                    $marks = $courseData['marks'];
                    $subjectStats[$subject->id]['sum'] += $marks;
                    
                    // Pass/fail based on 50% of the contribution
                    $passMark = $subjectStats[$subject->id]['pass_mark'];
                    if ($marks >= $passMark) {
                        $subjectStats[$subject->id]['passed']++;
                    } else {
                        $subjectStats[$subject->id]['failed']++;
                    }
                }

                $studentRow['courses'][$subject->id] = $courseData;
            }

            $studentData[] = $studentRow;
        }

        // Calculate averages
        foreach ($subjects as $subject) {
            $stats = &$subjectStats[$subject->id];
            if ($stats['with_marks'] > 0) {
                $stats['average'] = round($stats['sum'] / $stats['with_marks'], 2);
            }
        }

        // Check CA publishing status for final exams (validation)
        $caPublished = true;
        $caPublishingStatus = [];
        
        if ($isFinalExam) {
            foreach ($subjects as $subject) {
                // Check if any CA exam type is published for this subject
                $anyCAPublished = ExamPublishingState::where('program_id', $programId)
                    ->where('session_id', $sessionId)
                    ->where('semester_id', $semesterId)
                    ->where('section_id', $sectionId)
                    ->where('subject_id', $subject->id)
                    ->whereHas('examType', function ($q) {
                        $q->where('is_final', false);
                    })
                    ->where('workflow_state', ExamPublishingState::STATE_PUBLISHED)
                    ->exists();
                
                $caPublishingStatus[$subject->id] = $anyCAPublished;
                
                if (!$anyCAPublished) {
                    $caPublished = false;
                }
            }
        }

        return [
            'subjects' => $subjects,
            'students' => $studentData,
            'subject_states' => $subjectStates,
            'subject_stats' => $subjectStats,
            'exam_type' => $examType,
            'is_final_exam' => $isFinalExam,
            'ca_exam_types' => $caExamTypes ?? [],
            'ca_published' => $caPublished,
            'ca_publishing_status' => $caPublishingStatus ?? [],
            'total_students' => count($studentData),
        ];
    }

    /**
     * Transition a single subject's publishing state.
     */
    public function transition(Request $request, ExamPublishingState $examPublishingState)
    {
        $request->validate([
            'state' => 'required|string|in:submitted,checked,approved,published',
            'notes' => 'nullable|string|max:500',
            'publish_date' => 'nullable|date',
            'publish_time' => 'nullable|date_format:H:i',
        ]);

        $targetState = $request->input('state');

        // Check permission
        $this->authorizeTransition($targetState);

        // Validate transition
        if (!$this->canTransition($examPublishingState->workflow_state, $targetState)) {
            Flasher::addError(__('Invalid workflow transition.'), __('Error'));
            return redirect()->back();
        }

        // For final exam publishing, check if CA is published
        if ($targetState === ExamPublishingState::STATE_PUBLISHED && $examPublishingState->isFinalExam()) {
            $caPublished = ExamPublishingState::where('program_id', $examPublishingState->program_id)
                ->where('session_id', $examPublishingState->session_id)
                ->where('semester_id', $examPublishingState->semester_id)
                ->where('section_id', $examPublishingState->section_id)
                ->where('subject_id', $examPublishingState->subject_id)
                ->whereHas('examType', function ($q) {
                    $q->where('is_final', false);
                })
                ->where('workflow_state', ExamPublishingState::STATE_PUBLISHED)
                ->exists();

            if (!$caPublished) {
                Flasher::addError(__('Cannot publish Final Exam results. CA must be published first.'), __('Error'));
                return redirect()->back();
            }
        }

        // Check if there are marks to publish
        $hasMarks = $this->subjectHasMarks($examPublishingState);
        if (!$hasMarks && in_array($targetState, [ExamPublishingState::STATE_SUBMITTED, ExamPublishingState::STATE_PUBLISHED])) {
            Flasher::addError(__('Cannot proceed. No marks have been entered for this subject.'), __('Error'));
            return redirect()->back();
        }

        try {
            DB::transaction(function () use ($examPublishingState, $targetState, $request) {
                $fromState = $examPublishingState->workflow_state;
                $userId = Auth::guard('web')->id();

                $examPublishingState->workflow_state = $targetState;
                $examPublishingState->state_changed_at = now();
                $examPublishingState->state_changed_by = $userId;

                if ($targetState === ExamPublishingState::STATE_APPROVED) {
                    $examPublishingState->reviewed_at = now();
                    $examPublishingState->reviewed_by = $userId;
                    $examPublishingState->review_notes = $request->input('notes');
                }

                if ($targetState === ExamPublishingState::STATE_PUBLISHED) {
                    $examPublishingState->publish_date = $request->input('publish_date') ?? now()->toDateString();
                    $examPublishingState->publish_time = $request->input('publish_time') ?? now()->format('H:i:s');
                    $examPublishingState->published_by = $userId;
                    $examPublishingState->published_at = now();

                    // Update statistics
                    $this->updatePublishingStats($examPublishingState);
                }

                $examPublishingState->updated_by = $userId;
                $examPublishingState->save();
                
                // Sync to SubjectMarking records for ALL transitions
                // This keeps Subject Marking page in sync with Exam Publishing
                $this->syncToSubjectMarkings($examPublishingState);

                // Create workflow log
                ExamPublishingWorkflowLog::create([
                    'exam_publishing_state_id' => $examPublishingState->id,
                    'from_state' => $fromState,
                    'to_state' => $targetState,
                    'action' => 'transition',
                    'changed_by' => $userId,
                    'notes' => $request->input('notes'),
                    'changed_at' => now(),
                ]);
            });

            Flasher::addSuccess(
                sprintf('%s transitioned to %s successfully.', 
                    $examPublishingState->subject->code ?? 'Subject',
                    ucfirst($targetState)
                ),
                __('msg_success')
            );

        } catch (\Exception $e) {
            \Log::error('Exam Publishing Transition Error: ' . $e->getMessage(), [
                'state_id' => $examPublishingState->id,
                'target_state' => $targetState,
                'trace' => $e->getTraceAsString(),
            ]);
            Flasher::addError('Transition failed: ' . $e->getMessage(), __('Error'));
        }

        return redirect()->back();
    }

    /**
     * Bulk transition multiple subjects at once.
     */
    public function bulkTransition(Request $request)
    {
        $request->validate([
            'state' => 'required|string|in:submitted,checked,approved,published',
            'notes' => 'nullable|string|max:500',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'integer|exists:subjects,id',
            'program_id' => 'required|integer|exists:programs,id',
            'session_id' => 'required|integer|exists:sessions,id',
            'semester_id' => 'required|integer|exists:semesters,id',
            'section_id' => 'nullable|integer|exists:sections,id',
            'exam_type_id' => 'required|integer|exists:exam_types,id',
            'publish_date' => 'nullable|date',
            'publish_time' => 'nullable|date_format:H:i',
        ]);

        $targetState = $request->input('state');
        $subjectIds = $request->input('subject_ids');
        $sectionId = $request->input('section_id') ?: null;

        // Check permission
        $this->authorizeTransition($targetState);

        // Get publishing states for selected subjects
        $states = ExamPublishingState::where('program_id', $request->program_id)
            ->where('session_id', $request->session_id)
            ->where('semester_id', $request->semester_id)
            ->where('section_id', $sectionId)
            ->where('exam_type_id', $request->exam_type_id)
            ->whereIn('subject_id', $subjectIds)
            ->get();

        // Validate all selected courses are in the same workflow state
        $currentStates = $states->pluck('workflow_state')->unique();
        if ($currentStates->count() > 1) {
            Flasher::addError(
                __('Selected courses must all be in the same workflow state. Found: :states', 
                    ['states' => $currentStates->map(fn($s) => ucfirst($s))->implode(', ')]
                ),
                __('Error')
            );
            return redirect()->back();
        }

        $currentState = $currentStates->first() ?? ExamPublishingState::STATE_DRAFT;

        // Validate transition is valid
        if (!$this->canTransition($currentState, $targetState)) {
            Flasher::addError(
                __('Cannot transition from :from to :to.', 
                    ['from' => ucfirst($currentState), 'to' => ucfirst($targetState)]
                ),
                __('Error')
            );
            return redirect()->back();
        }

        // Check for Final Exam CA prerequisite
        $examType = ExamType::find($request->exam_type_id);
        if ($targetState === ExamPublishingState::STATE_PUBLISHED && $examType && $examType->is_final) {
            $unpublishedCA = [];
            foreach ($subjectIds as $subjectId) {
                $caPublished = ExamPublishingState::where('program_id', $request->program_id)
                    ->where('session_id', $request->session_id)
                    ->where('semester_id', $request->semester_id)
                    ->where('section_id', $sectionId)
                    ->where('subject_id', $subjectId)
                    ->whereHas('examType', function ($q) {
                        $q->where('is_final', false);
                    })
                    ->where('workflow_state', ExamPublishingState::STATE_PUBLISHED)
                    ->exists();

                if (!$caPublished) {
                    $subject = Subject::find($subjectId);
                    $unpublishedCA[] = $subject ? $subject->code : $subjectId;
                }
            }

            if (!empty($unpublishedCA)) {
                Flasher::addError(
                    __('Cannot publish Final Exam. CA not published for: :subjects', 
                        ['subjects' => implode(', ', $unpublishedCA)]
                    ),
                    __('Error')
                );
                return redirect()->back();
            }
        }

        $successCount = 0;
        $failCount = 0;
        $skippedNoMarks = [];

        try {
            DB::transaction(function () use ($states, $subjectIds, $targetState, $request, &$successCount, &$failCount, &$skippedNoMarks, $sectionId) {
                $userId = Auth::guard('web')->id();

                foreach ($states as $state) {
                    // Check if has marks
                    if (!$this->subjectHasMarks($state)) {
                        $skippedNoMarks[] = $state->subject->code ?? $state->subject_id;
                        continue;
                    }

                    $fromState = $state->workflow_state;

                    $state->workflow_state = $targetState;
                    $state->state_changed_at = now();
                    $state->state_changed_by = $userId;

                    if ($targetState === ExamPublishingState::STATE_APPROVED) {
                        $state->reviewed_at = now();
                        $state->reviewed_by = $userId;
                        $state->review_notes = $request->input('notes');
                    }

                    if ($targetState === ExamPublishingState::STATE_PUBLISHED) {
                        $state->publish_date = $request->input('publish_date') ?? now()->toDateString();
                        $state->publish_time = $request->input('publish_time') ?? now()->format('H:i:s');
                        $state->published_by = $userId;
                        $state->published_at = now();

                        $this->updatePublishingStats($state);
                    }

                    $state->updated_by = $userId;
                    $state->save();
                    
                    // Sync to SubjectMarking records for ALL transitions
                    $this->syncToSubjectMarkings($state);

                    ExamPublishingWorkflowLog::create([
                        'exam_publishing_state_id' => $state->id,
                        'from_state' => $fromState,
                        'to_state' => $targetState,
                        'action' => 'bulk_transition',
                        'changed_by' => $userId,
                        'notes' => $request->input('notes') ?? 'Bulk transition',
                        'changed_at' => now(),
                    ]);

                    $successCount++;
                }
            });

            if ($successCount > 0) {
                Flasher::addSuccess(
                    sprintf('Successfully transitioned %d course(s) to %s.', $successCount, ucfirst($targetState)),
                    __('msg_success')
                );

                // Send notifications to students when publishing results
                if ($targetState === ExamPublishingState::STATE_PUBLISHED) {
                    $this->sendPublishingNotifications(
                        $subjectIds,
                        $request->program_id,
                        $request->session_id,
                        $request->semester_id,
                        $sectionId,
                        $request->exam_type_id,
                        $request->input('publish_date'),
                        $request->input('publish_time')
                    );
                }
            }

            if (!empty($skippedNoMarks)) {
                Flasher::addWarning(
                    sprintf('Skipped %d course(s) without marks: %s', count($skippedNoMarks), implode(', ', $skippedNoMarks)),
                    __('Warning')
                );
            }

        } catch (\Exception $e) {
            \Log::error('Bulk Exam Publishing Transition Error: ' . $e->getMessage(), [
                'target_state' => $targetState,
                'trace' => $e->getTraceAsString(),
            ]);
            Flasher::addError('Bulk transition failed: ' . $e->getMessage(), __('Error'));
        }

        return redirect()->back();
    }

    /**
     * Bulk transition multiple subjects across multiple programs at once (multi-program mode).
     * Uses ExamPublishingState IDs directly instead of subject_ids + program_id.
     */
    public function bulkTransitionMulti(Request $request)
    {
        $request->validate([
            'state' => 'required|string|in:submitted,checked,approved,published',
            'notes' => 'nullable|string|max:500',
            'state_ids' => 'required|array|min:1',
            'state_ids.*' => 'integer|exists:exam_publishing_states,id',
            'session_id' => 'required|integer|exists:sessions,id',
            'semester_id' => 'required|integer|exists:semesters,id',
            'exam_type_id' => 'required|integer|exists:exam_types,id',
            'publish_date' => 'nullable|date',
            'publish_time' => 'nullable|date_format:H:i',
        ]);

        $targetState = $request->input('state');
        $stateIds = $request->input('state_ids');

        // Check permission
        $this->authorizeTransition($targetState);

        // Get all selected publishing states
        $states = ExamPublishingState::whereIn('id', $stateIds)->get();

        if ($states->isEmpty()) {
            Flasher::addError(__('No valid publishing states found.'), __('Error'));
            return redirect()->back();
        }

        // Validate all selected courses are in the same workflow state
        $currentStates = $states->pluck('workflow_state')->unique();
        if ($currentStates->count() > 1) {
            Flasher::addError(
                __('Selected courses must all be in the same workflow state. Found: :states',
                    ['states' => $currentStates->map(fn($s) => ucfirst($s))->implode(', ')]
                ),
                __('Error')
            );
            return redirect()->back();
        }

        $currentState = $currentStates->first() ?? ExamPublishingState::STATE_DRAFT;

        // Validate transition is valid
        if (!$this->canTransition($currentState, $targetState)) {
            Flasher::addError(
                __('Cannot transition from :from to :to.',
                    ['from' => ucfirst($currentState), 'to' => ucfirst($targetState)]
                ),
                __('Error')
            );
            return redirect()->back();
        }

        // Check for Final Exam CA prerequisite (per program)
        $examType = ExamType::find($request->exam_type_id);
        if ($targetState === ExamPublishingState::STATE_PUBLISHED && $examType && $examType->is_final) {
            $unpublishedCA = [];
            foreach ($states as $state) {
                $caPublished = ExamPublishingState::where('program_id', $state->program_id)
                    ->where('session_id', $state->session_id)
                    ->where('semester_id', $state->semester_id)
                    ->where('section_id', $state->section_id)
                    ->where('subject_id', $state->subject_id)
                    ->whereHas('examType', function ($q) {
                        $q->where('is_final', false);
                    })
                    ->where('workflow_state', ExamPublishingState::STATE_PUBLISHED)
                    ->exists();

                if (!$caPublished) {
                    $subject = Subject::find($state->subject_id);
                    $program = Program::find($state->program_id);
                    $unpublishedCA[] = ($subject ? $subject->code : $state->subject_id) .
                        ' (' . ($program ? $program->short_code : $state->program_id) . ')';
                }
            }

            if (!empty($unpublishedCA)) {
                Flasher::addError(
                    __('Cannot publish Final Exam. CA not published for: :subjects',
                        ['subjects' => implode(', ', array_slice($unpublishedCA, 0, 10))]
                    ),
                    __('Error')
                );
                return redirect()->back();
            }
        }

        $successCount = 0;
        $skippedNoMarks = [];

        try {
            DB::transaction(function () use ($states, $targetState, $request, &$successCount, &$skippedNoMarks) {
                $userId = Auth::guard('web')->id();

                foreach ($states as $state) {
                    if (!$this->subjectHasMarks($state)) {
                        $subject = $state->subject;
                        $program = Program::find($state->program_id);
                        $skippedNoMarks[] = ($subject->code ?? $state->subject_id) .
                            ' (' . ($program->short_code ?? $state->program_id) . ')';
                        continue;
                    }

                    $fromState = $state->workflow_state;

                    $state->workflow_state = $targetState;
                    $state->state_changed_at = now();
                    $state->state_changed_by = $userId;

                    if ($targetState === ExamPublishingState::STATE_APPROVED) {
                        $state->reviewed_at = now();
                        $state->reviewed_by = $userId;
                        $state->review_notes = $request->input('notes');
                    }

                    if ($targetState === ExamPublishingState::STATE_PUBLISHED) {
                        $state->publish_date = $request->input('publish_date') ?? now()->toDateString();
                        $state->publish_time = $request->input('publish_time') ?? now()->format('H:i:s');
                        $state->published_by = $userId;
                        $state->published_at = now();
                        $this->updatePublishingStats($state);
                    }

                    $state->updated_by = $userId;
                    $state->save();

                    $this->syncToSubjectMarkings($state);

                    ExamPublishingWorkflowLog::create([
                        'exam_publishing_state_id' => $state->id,
                        'from_state' => $fromState,
                        'to_state' => $targetState,
                        'action' => 'bulk_transition_multi',
                        'changed_by' => $userId,
                        'notes' => $request->input('notes') ?? 'Multi-program bulk transition',
                        'changed_at' => now(),
                    ]);

                    $successCount++;
                }
            });

            if ($successCount > 0) {
                Flasher::addSuccess(
                    sprintf('Successfully transitioned %d course(s) across multiple programs to %s.', $successCount, ucfirst($targetState)),
                    __('msg_success')
                );

                // Send notifications when publishing
                if ($targetState === ExamPublishingState::STATE_PUBLISHED) {
                    // Group by program for notifications
                    $statesByProgram = $states->groupBy('program_id');
                    foreach ($statesByProgram as $programId => $programStates) {
                        $subjectIds = $programStates->pluck('subject_id')->toArray();
                        $sectionId = $programStates->first()->section_id;
                        $this->sendPublishingNotifications(
                            $subjectIds,
                            $programId,
                            $request->session_id,
                            $request->semester_id,
                            $sectionId,
                            $request->exam_type_id,
                            $request->input('publish_date'),
                            $request->input('publish_time')
                        );
                    }
                }
            }

            if (!empty($skippedNoMarks)) {
                Flasher::addWarning(
                    sprintf('Skipped %d course(s) without marks: %s', count($skippedNoMarks), implode(', ', array_slice($skippedNoMarks, 0, 10))),
                    __('Warning')
                );
            }

        } catch (\Exception $e) {
            \Log::error('Multi-Program Bulk Transition Error: ' . $e->getMessage(), [
                'target_state' => $targetState,
                'state_ids' => $stateIds,
                'trace' => $e->getTraceAsString(),
            ]);
            Flasher::addError('Bulk transition failed: ' . $e->getMessage(), __('Error'));
        }

        return redirect()->back();
    }

    /**
     * Get workflow history for a publishing state.
     */
    public function history(ExamPublishingState $examPublishingState)
    {
        $logs = $examPublishingState->workflowLogs()
            ->with('changer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $action = $log->from_state
                    ? "Changed from " . ucfirst($log->from_state) . " to " . ucfirst($log->to_state)
                    : "Set to " . ucfirst($log->to_state);

                return [
                    'date' => $log->created_at->format('d M Y H:i'),
                    'user' => $log->changer ? ($log->changer->first_name . ' ' . $log->changer->last_name) : 'System',
                    'action' => $action,
                    'note' => $log->notes,
                ];
            });

        return response()->json($logs);
    }

    /**
     * Check if a subject has marks entered.
     */
    protected function subjectHasMarks(ExamPublishingState $state): bool
    {
        return Exam::where('subject_id', $state->subject_id)
            ->where('exam_type_id', $state->exam_type_id)
            ->whereHas('studentEnroll', function ($q) use ($state) {
                $q->where('program_id', $state->program_id)
                    ->where('session_id', $state->session_id)
                    ->where('semester_id', $state->semester_id);
                
                if ($state->section_id) {
                    $q->where('section_id', $state->section_id);
                }
            })
            ->where('attendance', 1)
            ->whereNotNull('achieve_marks')
            ->exists();
    }

    /**
     * Send notifications to students when exam results are published.
     * 
     * @param array $subjectIds The subject IDs being published
     * @param int $programId Program ID
     * @param int $sessionId Session ID
     * @param int $semesterId Semester ID
     * @param int|null $sectionId Section ID (optional)
     * @param int $examTypeId Exam type ID
     * @param string|null $publishDate Scheduled publish date
     * @param string|null $publishTime Scheduled publish time
     */
    protected function sendPublishingNotifications(
        array $subjectIds,
        int $programId,
        int $sessionId,
        int $semesterId,
        ?int $sectionId,
        int $examTypeId,
        ?string $publishDate,
        ?string $publishTime
    ): void {
        try {
            // Get exam type info
            $examType = ExamType::find($examTypeId);
            $examTypeName = $examType ? $examType->title : 'Exam';

            // Get program, session, semester info for the notification message
            $program = Program::find($programId);
            $session = Session::find($sessionId);
            $semester = Semester::find($semesterId);

            // Format the publish date/time for display
            $publishDateFormatted = $publishDate ? \Carbon\Carbon::parse($publishDate)->format('d M Y') : now()->format('d M Y');
            $publishTimeFormatted = $publishTime ? \Carbon\Carbon::parse($publishTime)->format('h:i A') : now()->format('h:i A');

            // Get unique student IDs who have marks for these subjects
            $studentIds = [];
            
            foreach ($subjectIds as $subjectId) {
                // Find all enrolled students who have marks for this subject and exam type
                $enrollIds = StudentEnroll::where('program_id', $programId)
                    ->where('session_id', $sessionId)
                    ->where('semester_id', $semesterId)
                    ->when($sectionId, function ($q) use ($sectionId) {
                        $q->where('section_id', $sectionId);
                    })
                    ->whereIn('status', [1, 2])
                    ->pluck('id');

                // Get student IDs from exams with marks
                $examStudentEnrollIds = Exam::where('subject_id', $subjectId)
                    ->where('exam_type_id', $examTypeId)
                    ->whereIn('student_enroll_id', $enrollIds)
                    ->where('attendance', 1)
                    ->whereNotNull('achieve_marks')
                    ->pluck('student_enroll_id');

                // Map enroll IDs to student IDs
                $subjectStudentIds = StudentEnroll::whereIn('id', $examStudentEnrollIds)
                    ->pluck('student_id')
                    ->toArray();

                $studentIds = array_merge($studentIds, $subjectStudentIds);
            }

            // Get unique students
            $studentIds = array_unique($studentIds);

            if (empty($studentIds)) {
                \Log::info('No students to notify for exam publishing', [
                    'subject_ids' => $subjectIds,
                    'program_id' => $programId,
                    'exam_type_id' => $examTypeId,
                ]);
                return;
            }

            // Get the students
            $students = Student::whereIn('id', $studentIds)->get();

            // Get subject info for notification
            $subjects = Subject::whereIn('id', $subjectIds)->get();
            $subjectCodes = $subjects->pluck('code')->implode(', ');
            $subjectCount = $subjects->count();

            // Build notification title and message
            $title = sprintf('%s Results Published', $examTypeName);
            
            if ($subjectCount === 1) {
                $subject = $subjects->first();
                $message = sprintf(
                    'Your %s results for %s (%s) have been published and are now available. Results will be visible from %s at %s.',
                    $examTypeName,
                    $subject->title,
                    $subject->code,
                    $publishDateFormatted,
                    $publishTimeFormatted
                );
            } else {
                $message = sprintf(
                    'Your %s results for %d course(s) (%s) have been published and are now available. Results will be visible from %s at %s.',
                    $examTypeName,
                    $subjectCount,
                    $subjectCodes,
                    $publishDateFormatted,
                    $publishTimeFormatted
                );
            }

            // Build detailed description for the Notice
            $subjectList = $subjects->map(function($s) {
                return '<li><strong>' . $s->code . '</strong> - ' . $s->title . '</li>';
            })->implode('');
            
            $description = '<div class="exam-results-notice">';
            $description .= '<p>' . $message . '</p>';
            $description .= '<h6><i class="fas fa-list"></i> Published Courses:</h6>';
            $description .= '<ul>' . $subjectList . '</ul>';
            $description .= '<div class="alert alert-info mt-3">';
            $description .= '<i class="fas fa-calendar-check"></i> <strong>Scheduled Publication:</strong> ' . $publishDateFormatted . ' at ' . $publishTimeFormatted;
            $description .= '</div>';
            $description .= '<p class="mt-3"><a href="' . route('student.transcript.index') . '" class="btn btn-primary btn-sm"><i class="fas fa-file-alt"></i> View Your Transcript</a></p>';
            $description .= '</div>';

            // Generate unique notice number
            $lastNotice = Notice::orderBy('notice_no', 'desc')->first();
            $noticeNo = $lastNotice ? ($lastNotice->notice_no + 1) : 1;

            // Find or create an "Exam Results" category
            $category = NoticeCategory::firstOrCreate(
                ['slug' => 'exam-results'],
                ['title' => 'Exam Results', 'status' => 1]
            );

            // Create the Notice record
            $notice = new Notice();
            $notice->faculty_id = $program ? $program->faculty_id : 0;
            $notice->program_id = $programId;
            $notice->session_id = $sessionId;
            $notice->semester_id = $semesterId;
            $notice->section_id = $sectionId ?? 0;
            $notice->category_id = $category->id;
            $notice->notice_no = $noticeNo;
            $notice->title = $title;
            $notice->description = $description;
            $notice->date = $publishDate ?? now()->toDateString();
            $notice->status = 1;
            $notice->created_by = Auth::guard('web')->id();
            $notice->save();

            // Attach the notice to all affected students
            $notice->students()->attach($students);

            // Prepare notification data for bell icon (uses existing NoticeNotification)
            $notificationData = [
                'id' => $notice->id,
                'title' => $title,
                'type' => 'notice'
            ];

            // Send notification to all affected students (shows in bell icon)
            // Only send immediate notification if publish date is today
            $today_date = \Carbon\Carbon::parse(\Carbon\Carbon::today())->format('Y-m-d');
            $noticeDate = $publishDate ?? now()->toDateString();
            
            if ($noticeDate == $today_date) {
                Notification::send($students, new NoticeNotification($notificationData));
            }

            \Log::info('Exam publishing notice created and notifications sent', [
                'notice_id' => $notice->id,
                'student_count' => $students->count(),
                'subject_count' => $subjectCount,
                'exam_type' => $examTypeName,
                'publish_date' => $publishDate,
                'publish_time' => $publishTime,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send exam publishing notifications: ' . $e->getMessage(), [
                'subject_ids' => $subjectIds,
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - notifications failing shouldn't break the publishing flow
        }
    }

    /**
     * Update publishing statistics.
     */
    protected function updatePublishingStats(ExamPublishingState $state): void
    {
        $enrollQuery = StudentEnroll::where('program_id', $state->program_id)
            ->where('session_id', $state->session_id)
            ->where('semester_id', $state->semester_id)
            ->whereIn('status', [1, 2]);

        if ($state->section_id) {
            $enrollQuery->where('section_id', $state->section_id);
        }

        $enrollIds = $enrollQuery->pluck('id');

        $exams = Exam::where('subject_id', $state->subject_id)
            ->where('exam_type_id', $state->exam_type_id)
            ->whereIn('student_enroll_id', $enrollIds)
            ->where('attendance', 1)
            ->get();

        $state->total_students = $enrollIds->count();
        $state->students_with_marks = $exams->whereNotNull('achieve_marks')->count();
        $state->students_passed = $exams->where('achieve_marks', '>=', 50)->count();
        $state->students_failed = $exams->where('achieve_marks', '<', 50)->whereNotNull('achieve_marks')->count();
    }

    /**
     * Sync publishing state to SubjectMarking records.
     */
    protected function syncToSubjectMarkings(ExamPublishingState $state): void
    {
        // This syncs the exam-level publishing to the SubjectMarking table
        // when the Exam Publishing module publishes marks
        // The student portal reads publish_date/publish_time and workflow_state from subject_markings

        $enrollQuery = StudentEnroll::where('program_id', $state->program_id)
            ->where('session_id', $state->session_id)
            ->where('semester_id', $state->semester_id)
            ->whereIn('status', [1, 2]);

        if ($state->section_id) {
            $enrollQuery->where('section_id', $state->section_id);
        }

        $enrollIds = $enrollQuery->pluck('id');
        $userId = Auth::guard('web')->id();

        foreach ($enrollIds as $enrollId) {
            // Update SubjectMarking with publish_date, publish_time, and workflow_state
            $subjectMarking = SubjectMarking::where('student_enroll_id', $enrollId)
                ->where('subject_id', $state->subject_id)
                ->first();

            if ($subjectMarking) {
                // Update the per-exam-type workflow state in subject_marking_exam_states
                // This is what Subject Marking page reads for each exam type
                $examState = SubjectMarkingExamState::firstOrCreate(
                    [
                        'subject_marking_id' => $subjectMarking->id,
                        'exam_type_id' => $state->exam_type_id,
                    ],
                    [
                        'workflow_state' => $state->workflow_state,
                        'state_changed_at' => now(),
                        'state_changed_by' => $userId,
                    ]
                );
                
                // Update the exam state workflow
                $examState->workflow_state = $state->workflow_state;
                $examState->state_changed_at = now();
                $examState->state_changed_by = $userId;
                
                if ($state->workflow_state === ExamPublishingState::STATE_PUBLISHED) {
                    $examState->publish_date = $state->publish_date;
                    $examState->publish_time = $state->publish_time;
                }
                
                if ($state->workflow_state === ExamPublishingState::STATE_APPROVED) {
                    $examState->reviewed_at = $state->reviewed_at;
                    $examState->reviewed_by = $state->reviewed_by;
                    $examState->review_notes = $state->review_notes;
                }
                
                $examState->save();
                
                // Get ALL active exam types that are configured in the system
                // The overall result can only be published when ALL exam types are published
                $allExamTypes = ExamType::where('status', 1)->pluck('id')->toArray();
                
                // Get existing exam states for this marking
                $existingExamStates = SubjectMarkingExamState::where('subject_marking_id', $subjectMarking->id)->get();
                $existingExamTypeIds = $existingExamStates->pluck('exam_type_id')->toArray();
                
                // Check if ALL exam types have been published
                $allPublished = true;
                foreach ($allExamTypes as $examTypeId) {
                    $existingState = $existingExamStates->firstWhere('exam_type_id', $examTypeId);
                    if (!$existingState || $existingState->workflow_state !== ExamPublishingState::STATE_PUBLISHED) {
                        $allPublished = false;
                        break;
                    }
                }
                
                if ($allPublished && count($allExamTypes) > 0) {
                    // All exam types published - update overall state to published
                    $subjectMarking->workflow_state = ExamPublishingState::STATE_PUBLISHED;
                    $subjectMarking->publish_date = $state->publish_date;
                    $subjectMarking->publish_time = $state->publish_time;
                } else {
                    // Not all published - set overall state to the minimum state among existing states
                    // If some exam types don't have states yet, consider them as 'draft'
                    $stateOrder = [
                        ExamPublishingState::STATE_DRAFT => 0,
                        ExamPublishingState::STATE_SUBMITTED => 1,
                        ExamPublishingState::STATE_CHECKED => 2,
                        ExamPublishingState::STATE_APPROVED => 3,
                        ExamPublishingState::STATE_PUBLISHED => 4,
                    ];
                    
                    // Start with the highest possible state, then find the minimum
                    $minState = ExamPublishingState::STATE_PUBLISHED;
                    $minOrder = 4;
                    
                    // Check all exam types - find the MINIMUM state
                    foreach ($allExamTypes as $examTypeId) {
                        $existingState = $existingExamStates->firstWhere('exam_type_id', $examTypeId);
                        $currentState = $existingState ? $existingState->workflow_state : ExamPublishingState::STATE_DRAFT;
                        $order = $stateOrder[$currentState] ?? 0;
                        
                        // Keep track of minimum state
                        if ($order < $minOrder) {
                            $minOrder = $order;
                            $minState = $currentState;
                        }
                    }
                    
                    $subjectMarking->workflow_state = $minState;
                    
                    // Don't set publish_date if not all are published
                    // (preserve existing if any, don't overwrite)
                }
                
                $subjectMarking->state_changed_at = now();
                $subjectMarking->state_changed_by = $userId;
                $subjectMarking->updated_by = $userId;
                $subjectMarking->save();
            }
        }
    }

    /**
     * Check if transition is valid.
     */
    protected function canTransition(string $fromState, string $toState): bool
    {
        return in_array($toState, $this->transitions[$fromState] ?? []);
    }

    /**
     * Authorize the current user for the transition.
     */
    protected function authorizeTransition(string $targetState): void
    {
        $permission = $this->statePermissions[$targetState] ?? null;

        if ($permission) {
            $user = Auth::guard('web')->user();
            
            if (!$user || !$user->can($permission)) {
                abort(403, __('You do not have permission to perform this transition.'));
            }
        }
    }

    /**
     * Export Draft Results Preview to Excel.
     */
    public function exportDraftResultsSummary(Request $request)
    {
        $program = $request->program;
        $session = $request->session;
        $semester = $request->semester;
        $section = $request->section ?? '0';

        if (!$program || !$session || !$semester) {
            return back()->with('error', 'Please select all required filters.');
        }

        $draftData = $this->generateDraftResultsPreview(
            (int) $program,
            (int) $session,
            (int) $semester,
            $section ? (int) $section : null
        );

        if (!$draftData) {
            return back()->with('error', 'No data available for the selected filters.');
        }

        $subjects = $draftData['draft_subjects'];
        $studentResults = $draftData['draft_student_results'];
        $grades = $draftData['draft_grades'];
        $courseStats = $draftData['draft_course_stats'];
        $overallStats = $draftData['draft_overall_stats'];

        // Get metadata
        $programModel = Program::with('academicDepartment', 'degreeType', 'faculty')->find($program);
        $sessionModel = Session::find($session);
        $semesterModel = Semester::find($semester);
        $sectionModel = ($section && $section != '0') ? Section::find($section) : null;

        $meta = [
            'institution_name' => config('app.name', 'Catholic University of Cameroon, CATUC'),
            'faculty_name' => $programModel->faculty->title ?? 'N/A',
            'faculty_shortcode' => $programModel->faculty->shortcode ?? 'N/A',
            'department_name' => $programModel->academicDepartment->title ?? 'N/A',
            'program_name' => $programModel->title ?? 'N/A',
            'program_shortcode' => $programModel->shortcode ?? 'N/A',
            'degree_type' => $programModel->degreeType->title ?? 'N/A',
            'session_title' => $sessionModel->title ?? 'N/A',
            'semester_title' => $semesterModel->title ?? 'N/A',
            'section_title' => $sectionModel->title ?? 'All Sections',
            'level' => $semesterModel->title ?? 'N/A',
            'generated_at' => now()->format('F d, Y H:i:s'),
            'generated_by' => Auth::user()->name ?? 'System',
        ];

        // ===== Build Excel Spreadsheet =====
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DRAFT RESULTS');

        // Header section
        $sheet->setCellValue('A1', $meta['institution_name']);
        $sheet->mergeCells('A1:Z1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', $meta['faculty_name']);
        $sheet->mergeCells('A2:Z2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Department: ' . $meta['department_name'] . ' / ' . $meta['program_name']);
        $sheet->mergeCells('A3:Z3');

        $sheet->setCellValue('A4', $meta['semester_title'] . ' ' . $meta['session_title']);
        $sheet->mergeCells('A4:Z4');

        $sheet->setCellValue('A5', 'DRAFT PREVIEW - Results may change before final publication');
        $sheet->mergeCells('A5:Z5');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setItalic(true)->setSize(10);
        $sheet->getStyle('A5')->getFont()->getColor()->setARGB('FFFF0000');
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFF2CC');

        // Column headers - Row 7: Course codes, Row 8: Course names
        $sheet->setCellValue('A7', 'S/N');
        $sheet->setCellValue('B7', 'Mat No.');
        $sheet->setCellValue('C7', 'Name of Student');

        $colIndex = 4; // Start from column D (courses)
        foreach ($subjects as $subject) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4);

            // Row 7: Course code and credit value
            $sheet->setCellValue($col . '7', $subject->code . ' (CV:' . $subject->credit_hour . ')');
            $sheet->mergeCells($col . '7:' . $endCol . '7');
            $sheet->getStyle($col . '7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . '7')->getFont()->setBold(true);

            // Row 8: Course title
            $sheet->setCellValue($col . '8', $subject->title);
            $sheet->mergeCells($col . '8:' . $endCol . '8');
            $sheet->getStyle($col . '8')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . '8')->getFont()->setItalic(true)->setSize(9);
            $sheet->getStyle($col . '8')->getAlignment()->setWrapText(true);

            $colIndex += 5;
        }

        // Row 9: Sub-headers (Att, CA, EX, TOT, Grad) - only for course columns
        // Columns A-C are merged across rows 7-9
        $sheet->mergeCells('A7:A9');
        $sheet->mergeCells('B7:B9');
        $sheet->mergeCells('C7:C9');
        $sheet->getStyle('A7:C9')->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $colIndex = 4; // Courses start from column D
        foreach ($subjects as $subject) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($col . '9', 'Att');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . '9', 'CA');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 2) . '9', 'EX');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 3) . '9', 'TOT');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4) . '9', 'Grad');
            $colIndex += 5;
        }

        // Summary columns after courses
        $summaryStartCol = $colIndex;
        $summaryEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 8);
        $summaryFirstCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol);

        // Summary header spans rows 7-8
        $sheet->setCellValue($summaryFirstCol . '7', 'SUMMARY');
        $sheet->mergeCells($summaryFirstCol . '7:' . $summaryEndCol . '8');
        $sheet->getStyle($summaryFirstCol . '7')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle($summaryFirstCol . '7')->getFont()->setBold(true)->setSize(11);

        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol) . '9', 'TCR');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 1) . '9', 'TCE');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 2) . '9', 'GPA');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 3) . '9', 'R#');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4) . '9', 'RCR');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 5) . '9', 'CO#');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 6) . '9', 'COCR');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 7) . '9', 'Pass');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 8) . '9', 'Fail');

        // Style header row
        $headerRange = 'A9:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 8) . '9';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        // Data rows
        $row = 10;
        foreach ($studentResults as $student) {
            $sheet->setCellValue('A' . $row, $student['sn']);
            $sheet->setCellValue('B' . $row, $student['matricule']);
            $sheet->setCellValue('C' . $row, $student['name']);

            $colIndex = 4; // Courses start from column D
            foreach ($subjects as $subject) {
                $courseData = $student['courses'][$subject->id] ?? null;
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                if ($courseData && $courseData['registered']) {
                    $sheet->setCellValue($col . $row, $courseData['attendance_marks']);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . $row, $courseData['ca_marks']);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 2) . $row, $courseData['exam_marks']);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 3) . $row, $courseData['total_marks']);
                    $gradeValue = $courseData['grade'];
                    if (!empty($courseData['decision']['code']) && !in_array($gradeValue, ['-', 'N/S', 'ABS'], true)) {
                        $gradeValue .= ' [' . $courseData['decision']['code'] . ']';
                    }

                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4) . $row, $gradeValue);

                    // Color cells based on status
                    $gradeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4);
                    if ($courseData['status'] == 'N/S') {
                        // Yellow/warning fill for Not Submitted across all 5 columns
                        $nsWarningColor = 'FFFFF2CC'; // Light yellow
                        for ($ci = 0; $ci < 5; $ci++) {
                            $nsCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + $ci);
                            $sheet->getStyle($nsCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($nsWarningColor);
                            $sheet->getStyle($nsCol . $row)->getFont()->setItalic(true);
                        }
                    } elseif ($courseData['status'] == 'ABS') {
                        // Gray fill for Absent across all 5 columns
                        $absColor = 'FFE0E0E0'; // Light gray
                        for ($ci = 0; $ci < 5; $ci++) {
                            $absCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + $ci);
                            $sheet->getStyle($absCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($absColor);
                        }
                    } elseif ($courseData['status'] == 'P') {
                        $sheet->getStyle($gradeCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FF90EE90');
                    } elseif ($courseData['status'] == 'F') {
                        $sheet->getStyle($gradeCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFFFCCCB');
                    }

                    // Also handle partial N/S (e.g., CA submitted but Final not)
                    if ($courseData['status'] != 'N/S' && $courseData['status'] != 'ABS') {
                        $partialNsColor = 'FFFFF2CC';
                        if ($courseData['ca_marks'] === 'N/S') {
                            $caCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                            $sheet->getStyle($caCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($partialNsColor);
                            $sheet->getStyle($caCol . $row)->getFont()->setItalic(true);
                        }
                        if ($courseData['exam_marks'] === 'N/S') {
                            $exCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 2);
                            $sheet->getStyle($exCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($partialNsColor);
                            $sheet->getStyle($exCol . $row)->getFont()->setItalic(true);
                        }
                    }
                } else {
                    for ($i = 0; $i < 5; $i++) {
                        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + $i) . $row, '-');
                    }
                }
                $colIndex += 5;
            }

            // Summary columns
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol) . $row, $student['summary']['total_credits_registered']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 1) . $row, $student['summary']['total_credits_earned']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 2) . $row, $student['summary']['gpa']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 3) . $row, $student['summary']['scheduled_resit_courses']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4) . $row, $student['summary']['scheduled_resit_credits']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 5) . $row, $student['summary']['carry_over_courses']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 6) . $row, $student['summary']['carry_over_credits']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 7) . $row, $student['summary']['courses_passed']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 8) . $row, $student['summary']['courses_failed']);

            $row++;
        }

        // Auto-size columns
        for ($i = 1; $i <= $summaryStartCol + 8; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // Set thin borders for all cells
        $lastRow = $row - 1;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 8);
        $sheet->getStyle('A7:' . $lastCol . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Add thick borders between courses (every 5 columns starting from E)
        $courseColIndex = 5; // Start from column E
        foreach ($subjects as $index => $subject) {
            $firstCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($courseColIndex);

            // Left border of each course block (thick)
            $sheet->getStyle($firstCol . '7:' . $firstCol . $lastRow)->getBorders()->getLeft()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

            // Background color for course header (alternating)
            $headerColor = ($index % 2 == 0) ? 'FFD9E8FB' : 'FFFFF2CC'; // Light blue / Light yellow
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($courseColIndex + 4);
            $sheet->getStyle($firstCol . '7:' . $endCol . '9')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($headerColor);

            $courseColIndex += 5;
        }

        // Summary section styling - left border and background
        $summaryFirstCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol);
        $summaryLastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 8);

        // Thick left border for summary section
        $sheet->getStyle($summaryFirstCol . '7:' . $summaryFirstCol . $lastRow)->getBorders()->getLeft()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Summary header background (light cyan)
        $sheet->getStyle($summaryFirstCol . '7:' . $summaryLastCol . '9')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0F7FA');

        // Right border of the entire table
        $sheet->getStyle($lastCol . '7:' . $lastCol . $lastRow)->getBorders()->getRight()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Top border of header row
        $sheet->getStyle('A7:' . $lastCol . '7')->getBorders()->getTop()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Bottom border of sub-header row (row 9)
        $sheet->getStyle('A9:' . $lastCol . '9')->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Bottom border of last data row
        $sheet->getStyle('A' . $lastRow . ':' . $lastCol . $lastRow)->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Left border of student info section (columns A-C)
        $sheet->getStyle('A7:A' . $lastRow)->getBorders()->getLeft()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Right border after student name column (column C)
        $sheet->getStyle('C7:C' . $lastRow)->getBorders()->getRight()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Style the student info header (A7:C9)
        $sheet->getStyle('A7:C9')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        // Bold headers
        $sheet->getStyle('A7:' . $lastCol . '9')->getFont()->setBold(true);

        // Center align all data except name column
        $sheet->getStyle('A10:B' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D10:' . $lastCol . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // ===== COURSE SUMMARY SECTION =====
        $gradesList = $grades->pluck('title')->toArray();
        $gradeCount = count($gradesList);

        // Calculate last column for Course Summary
        $csLastColIndex = 6 + $gradeCount + 4;
        $csLastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($csLastColIndex);

        $courseSummaryRow = $lastRow + 3;

        // Course Summary Header
        $sheet->setCellValue('A' . $courseSummaryRow, 'COURSE SUMMARY WITH GRADE DISTRIBUTION');
        $sheet->mergeCells('A' . $courseSummaryRow . ':' . $csLastColLetter . $courseSummaryRow);
        $sheet->getStyle('A' . $courseSummaryRow)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $courseSummaryRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF4472C4');
        $sheet->getStyle('A' . $courseSummaryRow)->getFont()->getColor()->setARGB('FFFFFFFF');

        // Course Summary Column Headers
        $csHeaderRow = $courseSummaryRow + 1;
        $sheet->setCellValue('A' . $csHeaderRow, 'S/N');
        $sheet->setCellValue('B' . $csHeaderRow, 'Course Code');
        $sheet->setCellValue('C' . $csHeaderRow, 'Course Title');
        $sheet->setCellValue('D' . $csHeaderRow, 'CV');
        $sheet->setCellValue('E' . $csHeaderRow, 'Reg');
        $sheet->setCellValue('F' . $csHeaderRow, 'Exam');

        // Grade columns
        $gradeColIndex = 7;
        foreach ($gradesList as $gradeTitle) {
            $gradeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gradeColIndex);
            $sheet->setCellValue($gradeCol . $csHeaderRow, $gradeTitle);

            $gradeObj = $grades->firstWhere('title', $gradeTitle);
            $isPassingGrade = $gradeObj && $gradeObj->min_mark >= 50;
            $gradeHeaderColor = $isPassingGrade ? 'FF90EE90' : 'FFFFCCCB';
            $sheet->getStyle($gradeCol . $csHeaderRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($gradeHeaderColor);

            $gradeColIndex++;
        }

        // Summary columns after grades
        $passCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gradeColIndex);
        $failCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gradeColIndex + 1);
        $rateCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gradeColIndex + 2);
        $avgCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gradeColIndex + 3);

        $sheet->setCellValue($passCol . $csHeaderRow, 'Pass');
        $sheet->setCellValue($failCol . $csHeaderRow, 'Fail');
        $sheet->setCellValue($rateCol . $csHeaderRow, 'Rate');
        $sheet->setCellValue($avgCol . $csHeaderRow, 'Avg');

        // Style pass/fail header columns
        $sheet->getStyle($passCol . $csHeaderRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF90EE90');
        $sheet->getStyle($failCol . $csHeaderRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFCCCB');

        $sheet->getStyle('A' . $csHeaderRow . ':F' . $csHeaderRow)->getFont()->setBold(true);
        $sheet->getStyle($passCol . $csHeaderRow . ':' . $avgCol . $csHeaderRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $csHeaderRow . ':F' . $csHeaderRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E2F3');

        // Course Summary Data
        $csDataRow = $csHeaderRow + 1;
        $csIndex = 1;
        foreach ($subjects as $subject) {
            $stats = $courseStats[$subject->id] ?? [
                'registered' => 0, 'examined' => 0, 'passed' => 0, 'failed' => 0, 'pass_rate' => 0, 'average' => 0, 'grade_distribution' => []
            ];

            $sheet->setCellValue('A' . $csDataRow, $csIndex);
            $sheet->setCellValue('B' . $csDataRow, $subject->code);
            $sheet->setCellValue('C' . $csDataRow, $subject->title);
            $sheet->setCellValue('D' . $csDataRow, $subject->credit_hour);
            $sheet->setCellValue('E' . $csDataRow, $stats['registered']);
            $sheet->setCellValue('F' . $csDataRow, $stats['examined']);

            // Grade distribution columns
            $gradeColIndex = 7;
            foreach ($gradesList as $gradeTitle) {
                $gradeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gradeColIndex);
                $gradeCount = $stats['grade_distribution'][$gradeTitle] ?? 0;
                $sheet->setCellValue($gradeCol . $csDataRow, $gradeCount);

                if ($gradeCount > 0) {
                    $gradeObj = $grades->firstWhere('title', $gradeTitle);
                    $isPassingGrade = $gradeObj && $gradeObj->min_mark >= 50;
                    $cellColor = $isPassingGrade ? 'FFE2EFDA' : 'FFFCE4D6';
                    $sheet->getStyle($gradeCol . $csDataRow)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($cellColor);
                }

                $gradeColIndex++;
            }

            // Summary columns
            $sheet->setCellValue($passCol . $csDataRow, $stats['passed']);
            $sheet->setCellValue($failCol . $csDataRow, $stats['failed']);
            $sheet->setCellValue($rateCol . $csDataRow, $stats['pass_rate'] . '%');
            $sheet->setCellValue($avgCol . $csDataRow, $stats['average']);

            // Color pass rate based on value
            $passRateColor = $stats['pass_rate'] >= 70 ? 'FF90EE90' : ($stats['pass_rate'] >= 50 ? 'FFFFF2CC' : 'FFFFCCCB');
            $sheet->getStyle($rateCol . $csDataRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($passRateColor);

            $csDataRow++;
            $csIndex++;
        }

        // Course Summary borders
        $csLastRow = $csDataRow - 1;
        $sheet->getStyle('A' . $csHeaderRow . ':' . $avgCol . $csLastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A' . $csHeaderRow . ':' . $avgCol . $csHeaderRow)->getBorders()->getTop()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle('A' . $csLastRow . ':' . $avgCol . $csLastRow)->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Thick border before grade columns and after grade columns
        $firstGradeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(7);
        $sheet->getStyle($firstGradeCol . $csHeaderRow . ':' . $firstGradeCol . $csLastRow)->getBorders()->getLeft()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle($passCol . $csHeaderRow . ':' . $passCol . $csLastRow)->getBorders()->getLeft()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Center align course summary columns (except title)
        $sheet->getStyle('A' . $csHeaderRow . ':B' . $csLastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $csHeaderRow . ':' . $avgCol . $csLastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Auto-size course summary columns
        for ($i = 1; $i <= $gradeColIndex + 3; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // ===== GRADE LEGEND SECTION =====
        $gradeLegendRow = $csLastRow + 3;

        $sheet->setCellValue('A' . $gradeLegendRow, 'GRADE LEGEND');
        $sheet->mergeCells('A' . $gradeLegendRow . ':F' . $gradeLegendRow);
        $sheet->getStyle('A' . $gradeLegendRow)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $gradeLegendRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF70AD47');
        $sheet->getStyle('A' . $gradeLegendRow)->getFont()->getColor()->setARGB('FFFFFFFF');

        $glHeaderRow = $gradeLegendRow + 1;
        $sheet->setCellValue('A' . $glHeaderRow, 'Grade');
        $sheet->setCellValue('B' . $glHeaderRow, 'Mark Range');
        $sheet->setCellValue('C' . $glHeaderRow, 'Grade Point');
        $sheet->setCellValue('D' . $glHeaderRow, 'Interpretation');
        $sheet->setCellValue('E' . $glHeaderRow, 'Status');

        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glHeaderRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glHeaderRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2EFDA');

        $glDataRow = $glHeaderRow + 1;
        foreach ($grades as $grade) {
            $sheet->setCellValue('A' . $glDataRow, $grade->title);
            $sheet->setCellValue('B' . $glDataRow, $grade->min_mark . ' - ' . $grade->max_mark);
            $sheet->setCellValue('C' . $glDataRow, number_format((float)$grade->point, 2));
            $sheet->setCellValue('D' . $glDataRow, $grade->interpretation ?? '-');
            $sheet->setCellValue('E' . $glDataRow, $grade->remark ?? '-');

            $isPassing = $grade->min_mark >= 50;
            $statusColor = $isPassing ? 'FF90EE90' : 'FFFFCCCB';
            $sheet->getStyle('E' . $glDataRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($statusColor);

            if (($glDataRow - $glHeaderRow) % 2 == 0) {
                $sheet->getStyle('A' . $glDataRow . ':D' . $glDataRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF2F2F2');
            }

            $glDataRow++;
        }

        $glLastRow = $glDataRow - 1;
        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glLastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glHeaderRow)->getBorders()->getTop()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle('A' . $glLastRow . ':E' . $glLastRow)->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glLastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // ===== OVERALL STATISTICS SECTION =====
        $overallRow = $glLastRow + 3;

        $sheet->setCellValue('A' . $overallRow, 'OVERALL STATISTICS');
        $sheet->mergeCells('A' . $overallRow . ':D' . $overallRow);
        $sheet->getStyle('A' . $overallRow)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $overallRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF5B9BD5');
        $sheet->getStyle('A' . $overallRow)->getFont()->getColor()->setARGB('FFFFFFFF');

        $osDataRow = $overallRow + 1;
        $sheet->setCellValue('A' . $osDataRow, 'Total Students:');
        $sheet->setCellValue('B' . $osDataRow, $overallStats['total_students']);
        $sheet->getStyle('A' . $osDataRow)->getFont()->setBold(true);

        $osDataRow++;
        $sheet->setCellValue('A' . $osDataRow, 'Students Passed (All Courses):');
        $sheet->setCellValue('B' . $osDataRow, $overallStats['total_passed']);
        $sheet->getStyle('A' . $osDataRow)->getFont()->setBold(true);
        $sheet->getStyle('B' . $osDataRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF90EE90');

        $osDataRow++;
        $sheet->setCellValue('A' . $osDataRow, 'Students Failed (Any Course):');
        $sheet->setCellValue('B' . $osDataRow, $overallStats['total_failed']);
        $sheet->getStyle('A' . $osDataRow)->getFont()->setBold(true);
        $sheet->getStyle('B' . $osDataRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFCCCB');

        $osDataRow++;
        $overallPassRate = $overallStats['total_students'] > 0
            ? round(($overallStats['total_passed'] / $overallStats['total_students']) * 100, 2)
            : 0;
        $sheet->setCellValue('A' . $osDataRow, 'Overall Pass Rate:');
        $sheet->setCellValue('B' . $osDataRow, $overallPassRate . '%');
        $sheet->getStyle('A' . $osDataRow)->getFont()->setBold(true);
        $passRateColor = $overallPassRate >= 70 ? 'FF90EE90' : ($overallPassRate >= 50 ? 'FFFFF2CC' : 'FFFFCCCB');
        $sheet->getStyle('B' . $osDataRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB($passRateColor);

        $sheet->getStyle('A' . ($overallRow + 1) . ':B' . $osDataRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Generated info
        $genRow = $osDataRow + 2;
        $sheet->setCellValue('A' . $genRow, 'DRAFT - Generated: ' . $meta['generated_at'] . ' by ' . $meta['generated_by']);
        $sheet->getStyle('A' . $genRow)->getFont()->setItalic(true)->setSize(9);

        // Create the file
        $filename = 'DRAFT_Student_Results_' . str_replace(['/', ' '], '_', $meta['program_shortcode']) . '_' .
                   str_replace(['/', ' '], '_', $meta['session_title']) . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Get preview subjects for a program/semester using semester offerings plus actual registered extras.
     */
    private function getDraftPreviewSubjectsForProgram(int $programId, int $semesterId, Collection $enrollments): Collection
    {
        $configuredSubjects = collect();

        $enrollSubjectRows = EnrollSubject::where('program_id', $programId)
            ->where('semester_id', $semesterId)
            ->where('status', 1)
            ->with('subjects')
            ->get();

        foreach ($enrollSubjectRows as $enrollSubject) {
            foreach ($enrollSubject->subjects as $subject) {
                if ($subject->status == '1' && !$configuredSubjects->contains('id', $subject->id)) {
                    $configuredSubjects->push($subject);
                }
            }
        }

        $registeredSubjects = $enrollments
            ->flatMap(function ($enroll) {
                return $enroll->subjects ?? collect();
            })
            ->filter(fn($subject) => $subject && $subject->status == '1')
            ->unique('id')
            ->values();

        if ($configuredSubjects->isEmpty()) {
            return $registeredSubjects->sortBy('code')->values();
        }

        $extraRegisteredSubjects = $registeredSubjects
            ->reject(fn($subject) => $configuredSubjects->contains('id', $subject->id))
            ->values();

        return $configuredSubjects
            ->concat($extraRegisteredSubjects)
            ->sortBy('code')
            ->values();
    }

    /**
     * Build per-course progression labels for each enrollment in the draft preview scope.
     */
    private function buildDraftCourseDecisionMetrics(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $pairKeys = $enrollments->map(fn($enroll) => $enroll->student_id . ':' . $enroll->program_id)
            ->unique()
            ->flip();

        $relatedEnrollments = StudentEnroll::whereIn('student_id', $enrollments->pluck('student_id')->filter()->unique()->values())
            ->whereIn('program_id', $enrollments->pluck('program_id')->filter()->unique()->values())
            ->with(['subjectMarks.subject'])
            ->orderBy('id', 'asc')
            ->get()
            ->filter(fn($enroll) => $pairKeys->has($enroll->student_id . ':' . $enroll->program_id))
            ->values();

        if ($relatedEnrollments->isEmpty()) {
            return [];
        }

        $enrollmentsByPair = $relatedEnrollments->groupBy(fn($enroll) => $enroll->student_id . ':' . $enroll->program_id);
        $requestsByEnrollment = ResitRequest::whereIn('student_enroll_id', $relatedEnrollments->pluck('id')->values())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_enroll_id');

        $metrics = [];

        foreach ($enrollments as $currentEnrollment) {
            $pairKey = $currentEnrollment->student_id . ':' . $currentEnrollment->program_id;
            $programEnrollments = $enrollmentsByPair->get($pairKey, collect());
            $laterEnrollments = $programEnrollments->filter(fn($enroll) => $enroll->id > $currentEnrollment->id)->values();

            $laterPassedSubjects = [];
            foreach ($laterEnrollments as $laterEnrollment) {
                foreach ($laterEnrollment->subjectMarks ?? [] as $mark) {
                    if (!$mark->subject || $mark->total_marks === null) {
                        continue;
                    }

                    if (round($mark->total_marks) >= 50) {
                        $laterPassedSubjects[$mark->subject_id] = true;
                    }
                }
            }

            $enrollmentRequests = $requestsByEnrollment->get($currentEnrollment->id, collect())
                ->groupBy('subject_id')
                ->map(fn($group) => $group->first());

            $subjectMetrics = [];
            foreach (($currentEnrollment->subjectMarks ?? collect()) as $mark) {
                if (!$mark->subject || $mark->total_marks === null) {
                    continue;
                }

                $subjectId = $mark->subject_id;
                $marksPer = round($mark->total_marks);
                $request = $enrollmentRequests->get($subjectId);

                if ($marksPer >= 50 || isset($laterPassedSubjects[$subjectId])) {
                    $subjectMetrics[$subjectId] = ['label' => 'Validated', 'code' => 'VAL', 'class' => 'success'];
                    continue;
                }

                if ($request && $request->workflow_state === ResitRequest::STATE_SCHEDULED) {
                    $subjectMetrics[$subjectId] = ['label' => 'Scheduled Resit', 'code' => 'RES', 'class' => 'primary'];
                    continue;
                }

                if ($request && in_array($request->workflow_state, [
                    ResitRequest::STATE_REQUESTED,
                    ResitRequest::STATE_AWAITING_PAYMENT,
                    ResitRequest::STATE_FINANCE_REVIEW,
                    ResitRequest::STATE_APPROVED,
                ], true)) {
                    $subjectMetrics[$subjectId] = ['label' => 'Pending Resit Decision', 'code' => 'PND', 'class' => 'warning'];
                    continue;
                }

                $hasLaterAttempt = $laterEnrollments->contains(function ($laterEnrollment) use ($subjectId) {
                    return collect($laterEnrollment->subjectMarks ?? [])->contains(function ($laterMark) use ($subjectId) {
                        return $laterMark->subject_id == $subjectId;
                    });
                });

                if ($hasLaterAttempt || ($request && in_array($request->workflow_state, [
                    ResitRequest::STATE_DECLINED,
                    ResitRequest::STATE_REJECTED,
                    ResitRequest::STATE_CANCELLED,
                ], true))) {
                    $subjectMetrics[$subjectId] = ['label' => 'Carry Over', 'code' => 'CO', 'class' => 'dark'];
                    continue;
                }

                $subjectMetrics[$subjectId] = ['label' => 'Pending Resit Decision', 'code' => 'PND', 'class' => 'warning'];
            }

            $metrics[$currentEnrollment->id] = $subjectMetrics;
        }

        return $metrics;
    }

    /**
     * Build per-enrollment summary metrics for scheduled resits and carry-over courses.
     */
    private function buildDraftProgressionSummaryMetrics(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $pairKeys = $enrollments->map(fn($enroll) => $enroll->student_id . ':' . $enroll->program_id)
            ->unique()
            ->flip();

        $relatedEnrollments = StudentEnroll::whereIn('student_id', $enrollments->pluck('student_id')->filter()->unique()->values())
            ->whereIn('program_id', $enrollments->pluck('program_id')->filter()->unique()->values())
            ->with(['subjectMarks.subject'])
            ->orderBy('id', 'asc')
            ->get()
            ->filter(fn($enroll) => $pairKeys->has($enroll->student_id . ':' . $enroll->program_id))
            ->values();

        if ($relatedEnrollments->isEmpty()) {
            return [];
        }

        $enrollmentMap = $relatedEnrollments->keyBy('id');
        $resitRequestsByPair = ResitRequest::whereIn('student_enroll_id', $relatedEnrollments->pluck('id')->values())
            ->with(['subject:id,credit_hour'])
            ->get()
            ->groupBy(function ($request) use ($enrollmentMap) {
                $sourceEnrollment = $enrollmentMap->get($request->student_enroll_id);

                return $sourceEnrollment
                    ? $sourceEnrollment->student_id . ':' . $sourceEnrollment->program_id
                    : 'unknown';
            });

        $metrics = [];

        foreach ($enrollments as $currentEnrollment) {
            $pairKey = $currentEnrollment->student_id . ':' . $currentEnrollment->program_id;
            $pairResitRequests = $resitRequestsByPair->get($pairKey, collect());
            $scheduledResits = $pairResitRequests
                ->where('student_enroll_id', $currentEnrollment->id)
                ->where('workflow_state', ResitRequest::STATE_SCHEDULED)
                ->unique('subject_id')
                ->values();

            $metrics[$currentEnrollment->id] = [
                'scheduled_resit_courses' => $scheduledResits->count(),
                'scheduled_resit_credits' => round($scheduledResits->sum(fn($request) => (float) ($request->subject->credit_hour ?? 0)), 1),
                'carry_over_courses' => 0,
                'carry_over_credits' => 0,
            ];
        }

        return $metrics;
    }

    /**
     * Generate draft results preview data (like Student Results Matrix but without publication check).
     * Shows all results regardless of publishing status - used as a draft preview on the publishing page.
     */
    private function generateDraftResultsPreview($programId, $sessionId, $semesterId, $sectionId)
    {
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $passingMark = 50;

        // Get all enrolled students
        $enrollQuery = StudentEnroll::query();
        $enrollQuery->where('session_id', $sessionId);
        $enrollQuery->where('program_id', $programId);
        $enrollQuery->where('semester_id', $semesterId);
        $enrollQuery->whereIn('status', [1, 2]);

        if ($sectionId && $sectionId != '0') {
            $enrollQuery->where('section_id', $sectionId);
        }

        $enrollQuery->with([
            'student',
            'program',
            'semester',
            'section',
            'subjects',
            'exams' => function ($q) {
                $q->with(['type', 'subject']);
            },
            'subjectMarks' => function ($q) {
                $q->with('subject');
            },
        ]);

        $enrollments = $enrollQuery->get()->sortBy('matricule');

        // Group by unique matricule (keep only latest enrollment per student)
        $uniqueEnrollments = $enrollments->groupBy('matricule')->map(function($group) {
            return $group->sortByDesc('id')->first();
        })->values()->sortBy('matricule');

        if ($uniqueEnrollments->isEmpty()) {
            return null;
        }

        $subjects = $this->getDraftPreviewSubjectsForProgram($programId, $semesterId, $uniqueEnrollments);

        if ($subjects->isEmpty()) {
            return null;
        }

        $courseDecisionMetrics = $this->buildDraftCourseDecisionMetrics($uniqueEnrollments);
        $progressionSummaryMetrics = $this->buildDraftProgressionSummaryMetrics($uniqueEnrollments);

        // Build student results matrix
        $studentResults = [];
        $courseStats = [];
        $overallStats = [
            'total_students' => 0,
            'total_passed' => 0,
            'total_failed' => 0,
            'total_pending' => 0,
            'courses_count' => count($subjects),
        ];

        // Initialize course stats
        $gradeDistribution = [];
        foreach ($grades as $grade) {
            $gradeDistribution[$grade->title] = 0;
        }

        foreach ($subjects as $subject) {
            $courseStats[$subject->id] = [
                'code' => $subject->code,
                'title' => $subject->title,
                'credit_value' => $subject->credit_hour,
                'registered' => 0,
                'examined' => 0,
                'passed' => 0,
                'failed' => 0,
                'total_marks' => 0,
                'grade_distribution' => $gradeDistribution,
            ];
        }

        $serialNumber = 1;
        foreach ($uniqueEnrollments as $enroll) {
            $student = $enroll->student;
            if (!$student) continue;

            $overallStats['total_students']++;

            $studentData = [
                'sn' => $serialNumber++,
                'matricule' => $enroll->matricule ?? $student->student_id ?? 'N/A',
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'section' => $enroll->section->title ?? 'N/A',
                'courses' => [],
                'summary' => [
                    'total_credits_registered' => 0,
                    'total_credits_earned' => 0,
                    'total_quality_points' => 0,
                    'courses_passed' => 0,
                    'courses_failed' => 0,
                    'scheduled_resit_courses' => 0,
                    'scheduled_resit_credits' => 0,
                    'carry_over_courses' => 0,
                    'carry_over_credits' => 0,
                    'gpa' => 0,
                ],
            ];

            $allCoursesPass = true;
            $hasAnyExaminedCourse = false;
            $enrollmentDecisionMetrics = $courseDecisionMetrics[$enroll->id] ?? [];
            $enrollmentProgressionMetrics = $progressionSummaryMetrics[$enroll->id] ?? [
                'scheduled_resit_courses' => 0,
                'scheduled_resit_credits' => 0,
                'carry_over_courses' => 0,
                'carry_over_credits' => 0,
            ];

            foreach ($subjects as $subject) {
                $subjectId = $subject->id;

                // Check if student is registered for this course
                $isRegistered = collect($enroll->subjects ?? [])->contains('id', $subjectId)
                    || $enroll->exams->where('subject_id', $subjectId)->isNotEmpty();

                if (!$isRegistered) {
                    $studentData['courses'][$subjectId] = [
                        'registered' => false,
                        'attendance_marks' => '-',
                        'ca_marks' => '-',
                        'exam_marks' => '-',
                        'total_marks' => '-',
                        'grade' => '-',
                        'status' => 'NR',
                        'decision' => null,
                    ];
                    continue;
                }

                $courseStats[$subjectId]['registered']++;
                $studentData['summary']['total_credits_registered'] += (float)$subject->credit_hour;

                // Get contribution settings for this subject
                $subjectContributions = ResultContributionService::getSubjectContributions($subjectId);
                $attendanceContribution = $subjectContributions['attendance'] ?? 0;

                // Get attendance marks
                $studentAttendance = \App\Models\StudentAttendance::where('student_enroll_id', $enroll->id)
                    ->where('subject_id', $subjectId)
                    ->get();

                $present = $studentAttendance->where('attendance', 1)->count();
                $absent = $studentAttendance->where('attendance', 2)->count();
                $leave = $studentAttendance->where('attendance', 3)->count();
                $totalPresent = $present + $leave;
                $totalAttendanceCount = $totalPresent + $absent;

                $attendanceMarks = 0;
                if (!empty($totalAttendanceCount) && $attendanceContribution > 0) {
                    $attendanceMarks = ($attendanceContribution / $totalAttendanceCount) * $totalPresent;
                }

                // Get stored marks from SubjectMarking (NO publication check - this is draft preview)
                $subjectMark = $enroll->subjectMarks->where('subject_id', $subjectId)->first();

                $storedAttendance = $subjectMark ? (float)($subjectMark->attendances ?? 0) : 0;
                $storedAssignment = $subjectMark ? (float)($subjectMark->assignments ?? 0) : 0;
                $storedActivity = $subjectMark ? (float)($subjectMark->activities ?? 0) : 0;

                // Use calculated or stored attendance
                $attendanceMarks = round($attendanceMarks, 2) ?: round($storedAttendance, 2);

                // Calculate CA and Exam marks
                $caExamMarks = 0;
                $finalExamMarks = 0;
                $rawCaMarks = 0;       // Raw achieve_marks total (fallback when contribution=0)
                $rawFinalMarks = 0;    // Raw final achieve_marks (fallback when contribution=0)
                $hasZeroContribution = false; // Track if contribution weights are missing
                $wasExamined = false;
                $hasMarksSubmitted = false;
                $hasCaMarks = false;
                $hasFinalMarks = false;
                $wasConfirmedAbsent = false; // Only true when marks_locked=1 or attendance_locked=1 AND attendance=2
                $caConfirmedAbsent = false;  // Track per-type absence for mixed attendance scenarios
                $finalConfirmedAbsent = false;

                foreach ($enroll->exams->where('subject_id', $subjectId) as $exam) {
                    $examContribution = (float)$exam->contribution;
                    $isFinalType = $exam->type && $exam->type->is_final;

                    if ($exam->attendance == 1) {
                        $wasExamined = true;

                        // Check if marks have actually been submitted (achieve_marks is NOT null)
                        if ($exam->achieve_marks !== null) {
                            $hasMarksSubmitted = true;
                            if ($isFinalType) {
                                $hasFinalMarks = true;
                                $rawFinalMarks += (float)$exam->achieve_marks;
                            } else {
                                $hasCaMarks = true;
                                $rawCaMarks += (float)$exam->achieve_marks;
                            }
                        }

                        // Track if contribution weights are missing
                        if ($examContribution <= 0) {
                            $hasZeroContribution = true;
                        }

                        // Only calculate contributed marks if contribution weight is set
                        if ($examContribution > 0 && $exam->marks > 0 && $exam->achieve_marks !== null) {
                            $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                            $contributedMarks = (($percentOfMarks / 100) * $examContribution);

                            if ($isFinalType) {
                                $finalExamMarks += $contributedMarks;
                            } else {
                                $caExamMarks += $contributedMarks;
                            }
                        }
                    } elseif ($exam->attendance == 2) {
                        // attendance defaults to 2, so only trust it as "genuinely absent"
                        // when the lecturer has finalized (marks_locked=1 or attendance_locked=1)
                        if ($exam->marks_locked == 1 || $exam->attendance_locked == 1) {
                            $wasConfirmedAbsent = true;
                            // Track which exam type has the confirmed absence
                            if ($isFinalType) {
                                $finalConfirmedAbsent = true;
                            } else {
                                $caConfirmedAbsent = true;
                            }
                        }
                        // If neither locked, this is an unprocessed default state → not reliable
                    }
                }

                // When contribution weights are missing, use raw achieve_marks as fallback
                // so the preview shows actual entered values instead of 0
                $effectiveCaMarks = ($caExamMarks == 0 && $rawCaMarks > 0 && $hasZeroContribution)
                    ? $rawCaMarks : $caExamMarks;
                $effectiveFinalMarks = ($finalExamMarks == 0 && $rawFinalMarks > 0 && $hasZeroContribution)
                    ? $rawFinalMarks : $finalExamMarks;

                $totalCA = round($attendanceMarks + $storedAssignment + $storedActivity + $effectiveCaMarks, 2);
                $examMarks = round($effectiveFinalMarks, 2);

                // Also check subject_markings for submitted CA marks
                // Lecturers can enter CA marks via SubjectMarkingController which writes to
                // subject_markings (assignments, activities, attendances) WITHOUT updating
                // the exams table. So exams may still have attendance=2 (default) while
                // subject_markings already has CA data entered.
                $hasStoredCaData = ($storedAssignment > 0 || $storedActivity > 0 || $storedAttendance > 0);

                // NOTE: subject_markings.exam_marks is the COMBINED total of ALL exam type
                // contributions (CA + Final), NOT just the final exam marks.
                // Do NOT use it as a fallback for the "Final Exam" column — it would
                // double-count CA marks that are already included in $totalCA.

                $totalMarks = round($totalCA + $examMarks, 2);

                if ($hasStoredCaData) {
                    $hasMarksSubmitted = true;
                    $hasCaMarks = true;
                    // If CA marks exist in subject_markings, student clearly participated
                    if (!$wasExamined) {
                        $wasExamined = true;
                    }
                }

                // Get grade
                $letterGrade = 'F';
                $gradePoint = 0;
                foreach ($grades as $grade) {
                    if ($totalMarks >= $grade->min_mark && $totalMarks <= $grade->max_mark) {
                        $letterGrade = $grade->title;
                        $gradePoint = (float)$grade->point;
                        break;
                    }
                }

                $passed = $totalMarks >= $passingMark;

                // Determine status:
                // - ABS: Only when absent is CONFIRMED (marks_locked=1 or attendance_locked=1)
                //   NOTE: attendance defaults to 2, so we can't trust attendance=2 alone
                // - N/S: Marks not submitted yet (unprocessed records, or present but no marks)
                // - P: Examined, marks submitted, passed
                // - F: Examined, marks submitted, failed
                if (!$wasExamined) {
                    $status = $wasConfirmedAbsent ? 'ABS' : 'N/S';
                } elseif (!$hasMarksSubmitted) {
                    $status = 'N/S';
                } else {
                    $status = $passed ? 'P' : 'F';
                }

                // Update stats - only count students with submitted marks as examined
                if ($wasExamined && $hasMarksSubmitted) {
                    $hasAnyExaminedCourse = true;
                    $courseStats[$subjectId]['examined']++;
                    $courseStats[$subjectId]['total_marks'] += $totalMarks;

                    if (isset($courseStats[$subjectId]['grade_distribution'][$letterGrade])) {
                        $courseStats[$subjectId]['grade_distribution'][$letterGrade]++;
                    }

                    if ($passed) {
                        $courseStats[$subjectId]['passed']++;
                        $studentData['summary']['courses_passed']++;
                        $studentData['summary']['total_credits_earned'] += (float)$subject->credit_hour;
                    } else {
                        $courseStats[$subjectId]['failed']++;
                        $studentData['summary']['courses_failed']++;
                        $allCoursesPass = false;
                    }

                    $studentData['summary']['total_quality_points'] += ($gradePoint * (float)$subject->credit_hour);
                } else {
                    $allCoursesPass = false;
                }

                // Set display values based on status
                $displayAttendance = round($attendanceMarks, 2);
                $displayCA = round($totalCA - $attendanceMarks, 2);
                $displayExam = round($examMarks, 2);
                $displayTotal = round($totalMarks, 2);
                $displayGrade = $letterGrade;
                $decision = $enrollmentDecisionMetrics[$subjectId] ?? null;

                if ($status == 'ABS') {
                    // Confirmed absent - show dashes (same display for blade and Excel)
                    $displayAttendance = '-';
                    $displayCA = '-';
                    $displayExam = '-';
                    $displayTotal = '-';
                    $displayGrade = 'ABS';
                } elseif ($status == 'N/S') {
                    // Not submitted - show N/S markers
                    $displayAttendance = 'N/S';
                    $displayCA = 'N/S';
                    $displayExam = 'N/S';
                    $displayTotal = 'N/S';
                    $displayGrade = 'N/S';
                } else {
                    // P or F: Check for partial absence or missing marks
                    // Absent = 0 marks for that component; grade is computed from the total normally
                    if ($finalConfirmedAbsent && !$hasFinalMarks) {
                        // Present for CA but confirmed absent for Final Exam → Final = 0
                        $displayExam = 'ABS';
                    } elseif (!$hasFinalMarks) {
                        // Final marks not submitted and not confirmed absent → N/S
                        $displayExam = 'N/S';
                        if ($hasCaMarks) {
                            $displayTotal = round($totalCA, 2);
                            $displayGrade = 'N/S'; // Can't determine final grade without all marks
                        }
                    }

                    if ($caConfirmedAbsent && !$hasCaMarks) {
                        // Present for Final but confirmed absent for CA → CA = 0
                        $displayCA = 'ABS';
                    } elseif (!$hasCaMarks) {
                        $displayCA = ($storedAssignment > 0 || $storedActivity > 0 || $caExamMarks > 0) ? $displayCA : 'N/S';
                    }
                }

                $studentData['courses'][$subjectId] = [
                    'registered' => true,
                    'attendance_marks' => $displayAttendance,
                    'ca_marks' => $displayCA,
                    'exam_marks' => $displayExam,
                    'total_marks' => $displayTotal,
                    'grade' => $displayGrade,
                    'status' => $status,
                    'grade_point' => $gradePoint,
                    'credit_value' => (float)$subject->credit_hour,
                    'has_zero_contribution' => $hasZeroContribution,
                    'is_not_submitted' => ($status == 'N/S' || $displayGrade == 'N/S'),
                    'is_absent' => ($status == 'ABS'),
                    'ca_absent' => $caConfirmedAbsent,
                    'final_absent' => $finalConfirmedAbsent,
                    'decision' => $decision,
                ];
            }

            $studentData['summary']['scheduled_resit_courses'] = $enrollmentProgressionMetrics['scheduled_resit_courses'] ?? 0;
            $studentData['summary']['scheduled_resit_credits'] = $enrollmentProgressionMetrics['scheduled_resit_credits'] ?? 0;
            $studentData['summary']['carry_over_courses'] = collect($studentData['courses'])
                ->filter(fn($course) => ($course['decision']['code'] ?? null) === 'CO')
                ->count();
            $studentData['summary']['carry_over_credits'] = round(collect($studentData['courses'])
                ->filter(fn($course) => ($course['decision']['code'] ?? null) === 'CO')
                ->sum(fn($course) => (float) ($course['credit_value'] ?? 0)), 1);

            // Calculate GPA
            if ($studentData['summary']['total_credits_registered'] > 0) {
                $studentData['summary']['gpa'] = round(
                    $studentData['summary']['total_quality_points'] / $studentData['summary']['total_credits_registered'],
                    2
                );
            }

            if ($hasAnyExaminedCourse) {
                if ($allCoursesPass && $studentData['summary']['courses_failed'] == 0) {
                    $overallStats['total_passed']++;
                } else {
                    $overallStats['total_failed']++;
                }
            } else {
                $overallStats['total_pending']++;
            }

            $studentResults[] = $studentData;
        }

        // Calculate course averages
        foreach ($courseStats as $subjectId => &$stats) {
            $stats['average'] = $stats['examined'] > 0
                ? round($stats['total_marks'] / $stats['examined'], 2)
                : 0;
            $stats['pass_rate'] = $stats['examined'] > 0
                ? round(($stats['passed'] / $stats['examined']) * 100, 2)
                : 0;
        }

        return [
            'draft_student_results' => $studentResults,
            'draft_subjects' => $subjects,
            'draft_course_stats' => $courseStats,
            'draft_overall_stats' => $overallStats,
            'draft_grades' => $grades,
        ];
    }
}
