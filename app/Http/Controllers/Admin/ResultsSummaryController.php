<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicDepartment;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Subject;
use App\Models\SubjectMarking;
use App\Models\StudentEnroll;
use App\Services\StaffAssignmentService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResultsSummaryController extends Controller
{
    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->title = __('Results Summary');
        $this->route = 'admin.results-summary';
        $this->view = 'admin.results-summary';
        $this->path = 'results-summary';
        $this->access = 'results-summary';

        $this->middleware('permission:' . $this->access . '-view', ['only' => ['index', 'departmentReport', 'facultyReport', 'studentResultsSummary']]);
        $this->middleware('permission:' . $this->access . '-export', ['only' => ['exportDepartment', 'exportFaculty', 'exportStudentResultsSummary']]);
    }

    /**
     * Display the results summary dashboard with KPIs
     */
    public function index(Request $request)
    {
        $data = [
            'title' => $this->title,
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'access' => $this->access,
        ];

        // Initialize selected values
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        $data['selected_session'] = $session = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_department'] = $department = $request->department ?? '0';

        // Get filter options
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Load dependent filters
        if ($faculty != '0') {
            $data['departments'] = AcademicDepartment::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
                
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }

        if ($program != '0') {
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();
        }

        if ($program != '0') {
            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['semesters'] = $semesters->orderBy('id', 'asc')->get();
        }

        // Calculate KPIs if filters are applied
        if ($faculty != '0' && $session != '0' && $semester != '0') {
            $data['kpis'] = $this->calculateKPIs($faculty, $program, $session, $semester, $department);
            $data['faculty_summary'] = $this->getFacultySummary($faculty, $session, $semester);
            $data['department_summaries'] = $this->getDepartmentSummaries($faculty, $session, $semester, $department);
            $data['grade_distribution'] = $this->getGradeDistribution($faculty, $program, $session, $semester, $department);
            $data['top_performing_courses'] = $this->getTopPerformingCourses($faculty, $program, $session, $semester, $department, 'best');
            $data['low_performing_courses'] = $this->getTopPerformingCourses($faculty, $program, $session, $semester, $department, 'worst');
        }

        return view($this->view . '.index', $data);
    }

    /**
     * Generate department-level detailed report
     */
    public function departmentReport(Request $request)
    {
        $data = [
            'title' => __('Department Results Report'),
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'access' => $this->access,
        ];

        // Initialize selected values
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_department'] = $department = $request->department ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        $data['selected_session'] = $session = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';

        // Get filter options
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Load dependent filters
        if ($faculty != '0') {
            $data['departments'] = AcademicDepartment::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
                
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }

        if ($program != '0') {
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();
        }

        if ($program != '0') {
            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['semesters'] = $semesters->orderBy('id', 'asc')->get();
        }

        // Generate course-by-course report if all required filters are selected
        if ($faculty != '0' && $session != '0' && $semester != '0') {
            $data['course_results'] = $this->getCourseResults($faculty, $department, $program, $session, $semester);
            $data['report_meta'] = $this->getReportMetadata($faculty, $department, $program, $session, $semester);
            $data['lecturers'] = $this->getCourseLecturers($faculty, $department, $program, $session, $semester);
        }

        return view($this->view . '.department-report', $data);
    }

    /**
     * Generate faculty-level summary report
     */
    public function facultyReport(Request $request)
    {
        $data = [
            'title' => __('Faculty Results Summary'),
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'access' => $this->access,
        ];

        // Initialize selected values
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_session'] = $session = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_degree_type'] = $degreeType = $request->degree_type ?? '0';

        // Get filter options
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', 1)->orderBy('id', 'asc')->get();
        $data['degree_types'] = \App\Models\DegreeType::where('status', '1')->orderBy('title', 'asc')->get();

        // Generate faculty summary if filters are selected
        if ($faculty != '0' && $session != '0' && $semester != '0') {
            $data['department_summaries'] = $this->getDepartmentSummariesForFaculty($faculty, $session, $semester, $degreeType);
            $data['faculty_totals'] = $this->getFacultyTotals($faculty, $session, $semester, $degreeType);
            $data['report_meta'] = $this->getFacultyReportMetadata($faculty, $session, $semester, $degreeType);
        }

        return view($this->view . '.faculty-report', $data);
    }

    /**
     * Calculate KPIs for the dashboard
     */
    private function calculateKPIs($faculty, $program, $session, $semester, $department)
    {
        $query = SubjectMarking::query()
            ->whereHas('studentEnroll', function ($q) use ($faculty, $program, $session, $semester) {
                $q->where('session_id', $session)
                  ->where('semester_id', $semester);
                  
                if ($program != '0') {
                    $q->where('program_id', $program);
                }
                
                $q->whereHas('program', function ($pq) use ($faculty) {
                    $pq->where('faculty_id', $faculty);
                });
            })
            ->where('workflow_state', SubjectMarking::STATE_PUBLISHED);

        // Filter by department if selected
        if ($department != '0') {
            $query->whereHas('subject', function ($sq) use ($department) {
                $sq->whereHas('programs', function ($pq) use ($department) {
                    $pq->where('academic_department_id', $department);
                });
            });
        }

        $markings = $query->get();
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Use 50 as standard passing threshold (C grade minimum)
        $passingMark = 50;

        $totalScripts = $markings->count();
        $passedScripts = $markings->where('total_marks', '>=', $passingMark)->count();
        $failedScripts = $totalScripts - $passedScripts;
        $passRate = $totalScripts > 0 ? round(($passedScripts / $totalScripts) * 100, 2) : 0;
        $averageMark = $totalScripts > 0 ? round($markings->avg('total_marks'), 2) : 0;

        // Get unique courses and students
        $uniqueCourses = $markings->pluck('subject_id')->unique()->count();
        $uniqueStudents = $markings->pluck('student_enroll_id')->unique()->count();

        // Calculate grade distribution counts
        $gradeDistribution = [];
        foreach ($grades as $grade) {
            $count = $markings->filter(function ($marking) use ($grade) {
                return $marking->total_marks >= $grade->min_mark && $marking->total_marks <= $grade->max_mark;
            })->count();
            $gradeDistribution[$grade->title] = $count;
        }

        return [
            'total_scripts' => $totalScripts,
            'passed_scripts' => $passedScripts,
            'failed_scripts' => $failedScripts,
            'pass_rate' => $passRate,
            'fail_rate' => $totalScripts > 0 ? round(100 - $passRate, 2) : 0,
            'average_mark' => $averageMark,
            'unique_courses' => $uniqueCourses,
            'unique_students' => $uniqueStudents,
            'grade_distribution' => $gradeDistribution,
        ];
    }

    /**
     * Get faculty-level summary
     */
    private function getFacultySummary($faculty, $session, $semester)
    {
        $facultyModel = Faculty::find($faculty);
        if (!$facultyModel) {
            return null;
        }

        $programs = Program::where('faculty_id', $faculty)->where('status', '1')->pluck('id');
        
        $markings = SubjectMarking::whereHas('studentEnroll', function ($q) use ($programs, $session, $semester) {
            $q->whereIn('program_id', $programs)
              ->where('session_id', $session)
              ->where('semester_id', $semester);
        })->where('workflow_state', SubjectMarking::STATE_PUBLISHED)->get();

        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Use 50 as standard passing threshold (C grade minimum)
        $passingMark = 50;

        $totalScripts = $markings->count();
        $passedScripts = $markings->where('total_marks', '>=', $passingMark)->count();

        return [
            'faculty_name' => $facultyModel->title,
            'faculty_shortcode' => $facultyModel->shortcode,
            'dean_name' => $facultyModel->dean_name,
            'total_scripts' => $totalScripts,
            'passed_scripts' => $passedScripts,
            'failed_scripts' => $totalScripts - $passedScripts,
            'pass_rate' => $totalScripts > 0 ? round(($passedScripts / $totalScripts) * 100, 2) : 0,
        ];
    }

    /**
     * Get department-level summaries
     */
    private function getDepartmentSummaries($faculty, $session, $semester, $selectedDepartment = '0')
    {
        $departments = AcademicDepartment::where('faculty_id', $faculty)
            ->where('status', '1')
            ->orderBy('title', 'asc');
            
        if ($selectedDepartment != '0') {
            $departments->where('id', $selectedDepartment);
        }
        
        $departments = $departments->get();
        
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Use 50 as standard passing threshold (C grade minimum)
        $passingMark = 50;

        $summaries = [];
        foreach ($departments as $dept) {
            $programs = Program::where('academic_department_id', $dept->id)->pluck('id');
            
            if ($programs->isEmpty()) {
                continue;
            }

            $markings = SubjectMarking::whereHas('studentEnroll', function ($q) use ($programs, $session, $semester) {
                $q->whereIn('program_id', $programs)
                  ->where('session_id', $session)
                  ->where('semester_id', $semester);
            })->where('workflow_state', SubjectMarking::STATE_PUBLISHED)->get();

            $uniqueCourses = $markings->pluck('subject_id')->unique()->count();
            $totalScripts = $markings->count();
            $passedScripts = $markings->where('total_marks', '>=', $passingMark)->count();

            $summaries[] = [
                'id' => $dept->id,
                'name' => $dept->title,
                'shortcode' => $dept->shortcode,
                'head_name' => $dept->head_name ?? 'N/A',
                'courses_offered' => $uniqueCourses,
                'scripts_written' => $totalScripts,
                'passed' => $passedScripts,
                'failed' => $totalScripts - $passedScripts,
                'pass_rate' => $totalScripts > 0 ? round(($passedScripts / $totalScripts) * 100, 2) : 0,
                'fail_rate' => $totalScripts > 0 ? round((($totalScripts - $passedScripts) / $totalScripts) * 100, 2) : 0,
            ];
        }

        return $summaries;
    }

    /**
     * Get grade distribution for charts
     */
    private function getGradeDistribution($faculty, $program, $session, $semester, $department)
    {
        $query = SubjectMarking::query()
            ->whereHas('studentEnroll', function ($q) use ($faculty, $program, $session, $semester) {
                $q->where('session_id', $session)
                  ->where('semester_id', $semester);
                  
                if ($program != '0') {
                    $q->where('program_id', $program);
                }
                
                $q->whereHas('program', function ($pq) use ($faculty) {
                    $pq->where('faculty_id', $faculty);
                });
            })
            ->where('workflow_state', SubjectMarking::STATE_PUBLISHED);

        if ($department != '0') {
            $query->whereHas('subject', function ($sq) use ($department) {
                $sq->whereHas('programs', function ($pq) use ($department) {
                    $pq->where('academic_department_id', $department);
                });
            });
        }

        $markings = $query->get();
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        $distribution = [];
        foreach ($grades as $grade) {
            $count = $markings->filter(function ($marking) use ($grade) {
                return $marking->total_marks >= $grade->min_mark && $marking->total_marks <= $grade->max_mark;
            })->count();
            
            $distribution[] = [
                'grade' => $grade->title,
                'count' => $count,
                'percentage' => $markings->count() > 0 ? round(($count / $markings->count()) * 100, 2) : 0,
                'color' => $this->getGradeColor($grade->title),
            ];
        }

        return $distribution;
    }

    /**
     * Get top/bottom performing courses
     */
    private function getTopPerformingCourses($faculty, $program, $session, $semester, $department, $type = 'best', $limit = 5)
    {
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Use 50 as standard passing threshold (C grade minimum)
        $passingMark = 50;

        $query = SubjectMarking::query()
            ->select('subject_id', 
                DB::raw('COUNT(*) as total_scripts'),
                DB::raw("SUM(CASE WHEN total_marks >= {$passingMark} THEN 1 ELSE 0 END) as passed_scripts"),
                DB::raw('AVG(total_marks) as average_marks'))
            ->whereHas('studentEnroll', function ($q) use ($faculty, $program, $session, $semester) {
                $q->where('session_id', $session)
                  ->where('semester_id', $semester);
                  
                if ($program != '0') {
                    $q->where('program_id', $program);
                }
                
                $q->whereHas('program', function ($pq) use ($faculty) {
                    $pq->where('faculty_id', $faculty);
                });
            })
            ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
            ->groupBy('subject_id')
            ->having('total_scripts', '>', 0);

        if ($department != '0') {
            $query->whereHas('subject', function ($sq) use ($department) {
                $sq->whereHas('programs', function ($pq) use ($department) {
                    $pq->where('academic_department_id', $department);
                });
            });
        }

        $results = $query->get()->map(function ($item) use ($passingMark) {
            $subject = Subject::find($item->subject_id);
            return [
                'subject_id' => $item->subject_id,
                'course_code' => $subject->code ?? 'N/A',
                'course_title' => $subject->title ?? 'N/A',
                'credit_hours' => $subject->credit_hour ?? 0,
                'total_scripts' => $item->total_scripts,
                'passed_scripts' => $item->passed_scripts,
                'failed_scripts' => $item->total_scripts - $item->passed_scripts,
                'pass_rate' => round(($item->passed_scripts / $item->total_scripts) * 100, 2),
                'average_marks' => round($item->average_marks, 2),
            ];
        });

        // Sort based on type
        if ($type === 'best') {
            $results = $results->sortByDesc('pass_rate')->take($limit);
        } else {
            $results = $results->sortBy('pass_rate')->take($limit);
        }

        return $results->values()->all();
    }

    /**
     * Get course-by-course results for department report
     */
    private function getCourseResults($faculty, $department, $program, $session, $semester)
    {
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Determine passing mark: Use 50 as standard passing threshold
        // This aligns with the C grade (min_mark: 50) which typically represents the minimum passing grade
        $passingMark = 50;

        // Get subjects for the selected filters
        $subjectsQuery = Subject::where('status', '1');
        
        if ($program != '0') {
            $subjectsQuery->whereHas('programs', function ($q) use ($program) {
                $q->where('program_id', $program);
            });
        } else {
            $subjectsQuery->whereHas('programs', function ($q) use ($faculty, $department) {
                $q->where('faculty_id', $faculty);
                if ($department != '0') {
                    $q->where('academic_department_id', $department);
                }
            });
        }

        $subjects = $subjectsQuery->orderBy('code', 'asc')->get();
        $results = [];

        foreach ($subjects as $subject) {
            $markings = SubjectMarking::where('subject_id', $subject->id)
                ->whereHas('studentEnroll', function ($q) use ($session, $semester, $program, $faculty) {
                    $q->where('session_id', $session)
                      ->where('semester_id', $semester);
                    
                    if ($program != '0') {
                        $q->where('program_id', $program);
                    }
                    
                    $q->whereHas('program', function ($pq) use ($faculty) {
                        $pq->where('faculty_id', $faculty);
                    });
                })
                ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
                ->get();

            if ($markings->isEmpty()) {
                continue;
            }

            // Get candidates registered (enrolled)
            $enrolledQuery = StudentEnroll::where('session_id', $session)
                ->where('semester_id', $semester)
                ->whereIn('status', [1, 2])
                ->whereHas('exams', function ($q) use ($subject) {
                    $q->where('subject_id', $subject->id);
                });
                
            if ($program != '0') {
                $enrolledQuery->where('program_id', $program);
            }
            
            $candidatesRegistered = $enrolledQuery->count();

            // Get lecturer(s) for this course
            $classRoutines = \App\Models\ClassRoutine::where('subject_id', $subject->id)
                ->where('session_id', $session)
                ->with('teacher')
                ->get();
            
            $lecturers = $classRoutines->pluck('teacher.name')->unique()->filter()->implode(', ') ?: 'N/A';

            // Calculate Course Coverage (% CC)
            // Based on ClassSession: completed sessions / planned sessions * 100
            $courseCoverage = $this->calculateCourseCoverage($subject->id, $session, $semester, $program);

            $candidatesExamined = $markings->count();
            $passed = $markings->where('total_marks', '>=', $passingMark)->count();
            $failed = $candidatesExamined - $passed;

            // Calculate grade distribution
            $gradeDistribution = [];
            foreach ($grades as $grade) {
                $count = $markings->filter(function ($marking) use ($grade) {
                    return $marking->total_marks >= $grade->min_mark && $marking->total_marks <= $grade->max_mark;
                })->count();
                $gradeDistribution[$grade->title] = $count;
            }

            // Map subject_type integer to display code
            // DB values: 0 = Optional (O), 1 = Compulsory (C), 2 = University Requirement (UR)
            $subjectTypeMap = [
                0 => 'O',   // Optional
                1 => 'C',   // Compulsory
                2 => 'UR',  // University Requirement
            ];
            $subjectTypeCode = $subjectTypeMap[$subject->subject_type] ?? 'N/A';

            $results[] = [
                'course_code' => $subject->code,
                'course_title' => $subject->title,
                'credit_value' => $subject->credit_hour,
                'status' => $subjectTypeCode,
                'lecturers' => $lecturers,
                'course_coverage' => $courseCoverage,
                'candidates_registered' => $candidatesRegistered,
                'candidates_examined' => $candidatesExamined,
                'passed' => $passed,
                'failed' => $failed,
                'pass_rate' => $candidatesExamined > 0 ? round(($passed / $candidatesExamined) * 100, 2) : 0,
                'fail_rate' => $candidatesExamined > 0 ? round(($failed / $candidatesExamined) * 100, 2) : 0,
                'grade_distribution' => $gradeDistribution,
                'average_marks' => round($markings->avg('total_marks'), 2),
            ];
        }

        return $results;
    }

    /**
     * Calculate course coverage percentage
     * Based on ClassSession completed sessions vs planned sessions
     * 
     * @param int $subjectId
     * @param int $sessionId
     * @param int $semesterId
     * @param mixed $programId
     * @return int Coverage percentage (0-100)
     */
    private function calculateCourseCoverage($subjectId, $sessionId, $semesterId, $programId)
    {
        // Get planned sessions count from ClassSession (scheduled classes)
        $classSessionQuery = \App\Models\ClassSession::where('subject_id', $subjectId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId);
        
        if ($programId != '0') {
            $classSessionQuery->where('program_id', $programId);
        }
        
        $totalPlanned = $classSessionQuery->count();
        
        // Get completed sessions (classes that were actually held)
        $completedSessionsQuery = \App\Models\ClassSession::where('subject_id', $subjectId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where(function ($q) {
                // Consider completed if has actual_start_time or status is completed
                $q->whereNotNull('actual_start_time')
                  ->orWhere('status', 'completed')
                  ->orWhere('status', 'held');
            });
        
        if ($programId != '0') {
            $completedSessionsQuery->where('program_id', $programId);
        }
        
        $completedSessions = $completedSessionsQuery->count();
        
        // If no class sessions data exists, check ClassRoutine for weekly schedule estimation
        if ($totalPlanned == 0) {
            // Try to estimate from ClassRoutine (weekly schedule)
            $routineQuery = \App\Models\ClassRoutine::where('subject_id', $subjectId)
                ->where('session_id', $sessionId)
                ->where('status', '1');
            
            if ($programId != '0') {
                $routineQuery->where('program_id', $programId);
            }
            
            $weeklySlots = $routineQuery->count();
            
            // If there are weekly slots but no class sessions recorded, 
            // we can't determine coverage, so default to 100%
            // This means the institution hasn't started using ClassSession tracking
            if ($weeklySlots > 0) {
                return 100; // Default when no session tracking data
            }
            
            return 100; // No routine data either, assume complete
        }
        
        // Calculate percentage
        $coverage = $totalPlanned > 0 ? round(($completedSessions / $totalPlanned) * 100) : 100;
        
        // Cap at 100% (in case of extra classes)
        return min($coverage, 100);
    }

    /**
     * Get report metadata
     */
    private function getReportMetadata($faculty, $department, $program, $session, $semester)
    {
        $facultyModel = Faculty::find($faculty);
        $departmentModel = $department != '0' ? AcademicDepartment::find($department) : null;
        $programModel = $program != '0' ? Program::find($program) : null;
        $sessionModel = Session::find($session);
        $semesterModel = Semester::find($semester);

        return [
            'institution_name' => config('app.name', 'University'),
            'faculty_name' => $facultyModel->title ?? 'N/A',
            'faculty_shortcode' => $facultyModel->shortcode ?? 'N/A',
            'department_name' => $departmentModel->title ?? 'All Departments',
            'program_name' => $programModel->title ?? 'All Programs',
            'degree_type' => $programModel ? ($programModel->degreeType->title ?? 'B.Sc.') : 'B.Sc.',
            'session_title' => $sessionModel->title ?? 'N/A',
            'semester_title' => $semesterModel->title ?? 'N/A',
            'generated_at' => now()->format('F d, Y H:i:s'),
            'generated_by' => Auth::user()->name ?? 'System',
        ];
    }

    /**
     * Get course lecturers
     */
    private function getCourseLecturers($faculty, $department, $program, $session, $semester)
    {
        $query = \App\Models\ClassRoutine::where('session_id', $session)
            ->whereHas('subject', function ($q) use ($faculty, $department, $program) {
                if ($program != '0') {
                    $q->whereHas('programs', function ($pq) use ($program) {
                        $pq->where('program_id', $program);
                    });
                } else {
                    $q->whereHas('programs', function ($pq) use ($faculty, $department) {
                        $pq->where('faculty_id', $faculty);
                        if ($department != '0') {
                            $pq->where('academic_department_id', $department);
                        }
                    });
                }
            })
            ->with('teacher')
            ->get();

        return $query->pluck('teacher.name')->unique()->filter()->values()->all();
    }

    /**
     * Get department summaries for faculty report
     */
    private function getDepartmentSummariesForFaculty($faculty, $session, $semester, $degreeType)
    {
        $departments = AcademicDepartment::where('faculty_id', $faculty)
            ->where('status', '1')
            ->orderBy('title', 'asc')
            ->get();

        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Use 50 as standard passing threshold (C grade minimum)
        $passingMark = 50;

        $summaries = [];
        foreach ($departments as $dept) {
            $programsQuery = Program::where('academic_department_id', $dept->id);
            
            if ($degreeType != '0') {
                $programsQuery->where('degree_type_id', $degreeType);
            }
            
            $programs = $programsQuery->pluck('id');

            if ($programs->isEmpty()) {
                continue;
            }

            $markings = SubjectMarking::whereHas('studentEnroll', function ($q) use ($programs, $session, $semester) {
                $q->whereIn('program_id', $programs)
                  ->where('session_id', $session)
                  ->where('semester_id', $semester);
            })->where('workflow_state', SubjectMarking::STATE_PUBLISHED)->get();

            $uniqueCourses = $markings->pluck('subject_id')->unique()->count();
            $totalScripts = $markings->count();
            $passedScripts = $markings->where('total_marks', '>=', $passingMark)->count();

            if ($totalScripts == 0) {
                continue;
            }

            $summaries[] = [
                'id' => $dept->id,
                'name' => $dept->title,
                'shortcode' => $dept->shortcode,
                'head_name' => $dept->head_name ?? 'N/A',
                'courses_offered' => $uniqueCourses,
                'scripts_written' => $totalScripts,
                'passed' => $passedScripts,
                'failed' => $totalScripts - $passedScripts,
                'pass_rate' => round(($passedScripts / $totalScripts) * 100, 2),
                'fail_rate' => round((($totalScripts - $passedScripts) / $totalScripts) * 100, 2),
            ];
        }

        return $summaries;
    }

    /**
     * Get faculty totals
     */
    private function getFacultyTotals($faculty, $session, $semester, $degreeType)
    {
        $programsQuery = Program::where('faculty_id', $faculty);
        
        if ($degreeType != '0') {
            $programsQuery->where('degree_type_id', $degreeType);
        }
        
        $programs = $programsQuery->pluck('id');

        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Use 50 as standard passing threshold (C grade minimum)
        $passingMark = 50;

        $markings = SubjectMarking::whereHas('studentEnroll', function ($q) use ($programs, $session, $semester) {
            $q->whereIn('program_id', $programs)
              ->where('session_id', $session)
              ->where('semester_id', $semester);
        })->where('workflow_state', SubjectMarking::STATE_PUBLISHED)->get();

        $uniqueCourses = $markings->pluck('subject_id')->unique()->count();
        $totalScripts = $markings->count();
        $passedScripts = $markings->where('total_marks', '>=', $passingMark)->count();

        return [
            'courses_offered' => $uniqueCourses,
            'scripts_written' => $totalScripts,
            'passed' => $passedScripts,
            'failed' => $totalScripts - $passedScripts,
            'pass_rate' => $totalScripts > 0 ? round(($passedScripts / $totalScripts) * 100, 2) : 0,
            'fail_rate' => $totalScripts > 0 ? round((($totalScripts - $passedScripts) / $totalScripts) * 100, 2) : 0,
        ];
    }

    /**
     * Get faculty report metadata
     */
    private function getFacultyReportMetadata($faculty, $session, $semester, $degreeType)
    {
        $facultyModel = Faculty::find($faculty);
        $sessionModel = Session::find($session);
        $semesterModel = Semester::find($semester);
        $degreeTypeModel = $degreeType != '0' ? \App\Models\DegreeType::find($degreeType) : null;

        return [
            'institution_name' => config('app.name', 'University'),
            'faculty_name' => $facultyModel->title ?? 'N/A',
            'faculty_shortcode' => $facultyModel->shortcode ?? 'N/A',
            'dean_name' => $facultyModel->dean_name ?? 'N/A',
            'degree_type' => $degreeTypeModel->title ?? 'All Programmes',
            'session_title' => $sessionModel->title ?? 'N/A',
            'semester_title' => $semesterModel->title ?? 'N/A',
            'generated_at' => now()->format('F d, Y H:i:s'),
            'generated_by' => Auth::user()->name ?? 'System',
        ];
    }

    /**
     * Display the Individual Course Mark Sheet (Excel-style format)
     * This generates the detailed mark sheet for a specific course
     */
    public function courseMarkSheet(Request $request)
    {
        $data = [
            'title' => __('Individual Course Mark Sheet'),
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'access' => $this->access,
        ];

        // Initialize selected values
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        $data['selected_session'] = $session = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_section'] = $section = $request->section ?? '0';
        $data['selected_subject'] = $subject = $request->subject ?? '0';

        // Get filter options
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Load dependent filters
        if ($faculty != '0') {
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }

        if ($program != '0') {
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

        if ($program != '0' && $semester != '0') {
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester) {
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        if ($program != '0' && $session != '0') {
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($session) {
                $query->where('session_id', $session);
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program) {
                $query->where('program_id', $program);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }

        // Generate mark sheet if all required filters are selected
        if ($faculty != '0' && $program != '0' && $session != '0' && $semester != '0' && $subject != '0') {
            $markSheetData = $this->generateCourseMarkSheet($faculty, $program, $session, $semester, $section, $subject);
            $data = array_merge($data, $markSheetData);
        }

        return view($this->view . '.course-marksheet', $data);
    }

    /**
     * Generate the course mark sheet data - mirrors Subject Marking page exactly
     */
    private function generateCourseMarkSheet($faculty, $program, $session, $semester, $section, $subjectId)
    {
        $subjectModel = Subject::find($subjectId);
        if (!$subjectModel) {
            return ['error' => 'Subject not found'];
        }

        // Get exam types with contributions for this subject
        $examTypes = ExamType::where('status', '1')->orderBy('is_final', 'asc')->orderBy('contribution', 'desc')->get();
        
        // Get result contribution settings for this subject (same as Subject Marking page)
        $subjectContributions = \App\Services\ResultContributionService::getSubjectContributions($subjectId);
        $attendanceContribution = $subjectContributions['attendance'] ?? 0;
        $assignmentContribution = $subjectContributions['assignment'] ?? 0;
        $activityContribution = $subjectContributions['activity'] ?? 0;
        $examTypeContributions = $subjectContributions['exam_types'] ?? [];
        
        // Calculate CA and Final Exam contributions separately
        $caExamContribution = 0;
        $finalExamContribution = 0;
        foreach ($examTypes as $examType) {
            $typeContribution = isset($examTypeContributions[$examType->id]) 
                ? $examTypeContributions[$examType->id]->contribution 
                : 0;
            if ($examType->is_final) {
                $finalExamContribution += $typeContribution;
            } else {
                $caExamContribution += $typeContribution;
            }
        }
        
        // CA Total = Attendance + Assignments + Activities + CA Exams (non-final)
        $caTotal = $attendanceContribution + $assignmentContribution + $activityContribution + $caExamContribution;
        // Exam Total = Final Exam only
        $examTotal = $finalExamContribution;

        // Get lecturer(s) for this course
        $classRoutines = \App\Models\ClassRoutine::where('subject_id', $subjectId)
            ->where('session_id', $session)
            ->with('teacher')
            ->get();
        $lecturers = $classRoutines->pluck('teacher.name')->unique()->filter()->implode(', ') ?: 'N/A';

        // Get student attendance for the subject (same as Subject Marking)
        $studentAttendance = \App\Models\StudentAttendance::query()
            ->with('studentEnroll')
            ->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section) {
                $query->where('program_id', $program);
                $query->where('session_id', $session);
                if ($semester != '0') {
                    $query->where('semester_id', $semester);
                }
                if ($section != '0') {
                    $query->where('section_id', $section);
                }
            })
            ->where('subject_id', $subjectId)
            ->get();

        // Get enrolled students - like Subject Marking controller
        $enrollQuery = StudentEnroll::query();
        $enrollQuery->where('session_id', $session);
        $enrollQuery->where('program_id', $program);
        $enrollQuery->whereIn('status', [1, 2]);
        
        if ($semester != '0') {
            $enrollQuery->where('semester_id', $semester);
        }
        if ($section != '0') {
            $enrollQuery->where('section_id', $section);
        }
        
        // Only get students registered for this subject
        $enrollQuery->whereHas('exams', function ($q) use ($subjectId) {
            $q->where('subject_id', $subjectId);
        });

        $enrollQuery->with([
            'student',
            'program',
            'exams' => function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId)->with(['type', 'scriptCode']);
            },
            'subjectMarks' => function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            },
            'section',
            'semester'
        ]);

        $rows = $enrollQuery->get();
        
        // Group by unique matricule - keep only latest enrollment per matricule (same as Subject Marking)
        $uniqueMatricules = $rows->groupBy('matricule')->map(function($group) {
            return $group->sortByDesc('id')->first();
        })->values();

        $enrollments = $uniqueMatricules->sortBy('matricule');

        // Get grades for calculating letter grades
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        
        // Use 50 as standard passing threshold (C grade minimum)
        $passingMark = 50;

        // Process each student's data - mirroring Subject Marking blade logic
        $studentRecords = [];
        $statistics = [
            'registered' => 0,
            'examined' => 0,
            'absent' => 0,
            'passed' => 0,
            'failed' => 0,
            'total_marks' => 0,
            'grade_distribution' => [],
        ];

        // Initialize grade distribution
        foreach ($grades as $grade) {
            $statistics['grade_distribution'][$grade->title] = 0;
        }

        $serialNumber = 1;
        foreach ($enrollments as $enroll) {
            $student = $enroll->student;
            if (!$student) continue;

            $statistics['registered']++;

            // Get exam code (anonymous code for final exam if exists)
            $finalExam = $enroll->exams->first(function ($exam) {
                return $exam->type && $exam->type->is_final;
            });
            $examCode = $finalExam && $finalExam->scriptCode 
                ? $finalExam->scriptCode->anonymous_code 
                : $subjectModel->code . '/' . $serialNumber;

            // Separate CA exams from Final exam
            $caExamMarks = 0;
            $finalExamMarks = 0;
            
            foreach ($enroll->exams->where('subject_id', $subjectId) as $exam) {
                if ($exam->attendance == 1 && $exam->student_enroll_id == $enroll->id && $exam->contribution > 0) {
                    if ($exam->marks > 0) {
                        $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                        $contributedMarks = (($percentOfMarks / 100) * $exam->contribution);
                        
                        // Check if this is a final exam or CA exam
                        if ($exam->type && $exam->type->is_final) {
                            $finalExamMarks += $contributedMarks;
                        } else {
                            $caExamMarks += $contributedMarks;
                        }
                    }
                }
            }
            $caExamMarks = round($caExamMarks, 2);
            $finalExamMarks = round($finalExamMarks, 2);

            // Get stored marks from SubjectMarking (if saved)
            $subjectMark = $enroll->subjectMarks->first(function($mark) use ($enroll) {
                return $mark->student_enroll_id == $enroll->id;
            });
            
            // Marks from SubjectMarking table (or defaults)
            $storedAttendance = $subjectMark ? (float)($subjectMark->attendances ?? 0) : 0;
            $storedAssignment = $subjectMark ? (float)($subjectMark->assignments ?? 0) : 0;
            $storedActivity = $subjectMark ? (float)($subjectMark->activities ?? 0) : 0;

            // Calculate attendance marks (EXACTLY like Subject Marking blade)
            $present = $studentAttendance->where('student_enroll_id', $enroll->id)->where('attendance', 1)->count();
            $absent = $studentAttendance->where('student_enroll_id', $enroll->id)->where('attendance', 2)->count();
            $leave = $studentAttendance->where('student_enroll_id', $enroll->id)->where('attendance', 3)->count();
            $totalPresent = $present + $leave;
            $totalAttendanceCount = $totalPresent + $absent;
            
            $attendanceMarksCalculated = 0;
            if (!empty($totalAttendanceCount) && $attendanceContribution > 0) {
                $attendanceMarksCalculated = ($attendanceContribution / $totalAttendanceCount) * $totalPresent;
            }

            // Use calculated values or stored values
            $attendanceMarks = round($attendanceMarksCalculated, 2) ?: round($storedAttendance, 2);
            $assignmentMarks = round($storedAssignment, 2);
            $activityMarks = round($storedActivity, 2);

            // Total CA = Attendance + Assignments + Activities + CA Exams (non-final)
            $totalCA = round($attendanceMarks + $assignmentMarks + $activityMarks + $caExamMarks, 2);
            
            // Final Exam marks
            $examMarks = $finalExamMarks;

            // Calculate total marks
            $totalMarks = round($totalCA + $examMarks, 2);

            // Determine if student was examined
            $wasExamined = $enroll->exams->where('subject_id', $subjectId)->where('attendance', 1)->count() > 0;
            $examAttendance = $finalExam ? ($finalExam->attendance == 1) : $wasExamined;
            
            if ($wasExamined) {
                $statistics['examined']++;
            } else {
                $statistics['absent']++;
            }

            // Get grade and remark
            $letterGrade = 'F';
            foreach ($grades as $grade) {
                if ($totalMarks >= $grade->min_mark && $totalMarks <= $grade->max_mark) {
                    $letterGrade = $grade->title;
                    break;
                }
            }
            
            // Remark is simply based on total marks: 50%+ = Passed, below 50% = Failed
            $remark = $totalMarks >= 50 ? 'Passed' : 'Failed';

            // Update statistics
            if ($wasExamined) {
                if ($totalMarks >= $passingMark) {
                    $statistics['passed']++;
                } else {
                    $statistics['failed']++;
                }
                $statistics['total_marks'] += $totalMarks;
                if (isset($statistics['grade_distribution'][$letterGrade])) {
                    $statistics['grade_distribution'][$letterGrade]++;
                }
            }

            $studentRecords[] = [
                'sn' => $serialNumber,
                'matricule' => $enroll->matricule ?? $student->student_id ?? 'N/A',
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'exam_code' => $examCode,
                'attendance_marks' => $attendanceMarks,
                'assignment_marks' => $assignmentMarks,
                'activity_marks' => $activityMarks,
                'ca_exam_marks' => $caExamMarks,
                'total_ca' => $totalCA,
                'exam_attendance' => $examAttendance,
                'sign_in' => $finalExam ? (bool) $finalExam->sign_in : false,
                'sign_out' => $finalExam ? (bool) $finalExam->sign_out : false,
                'exam_marks' => $examMarks,
                'total_marks' => $totalMarks,
                'grade' => $letterGrade,
                'remark' => $remark,
                'section' => $enroll->section->title ?? 'N/A',
                'semester' => $enroll->semester->title ?? 'N/A',
            ];

            $serialNumber++;
        }

        // Calculate statistics percentages
        $statistics['pass_rate'] = $statistics['examined'] > 0 
            ? round(($statistics['passed'] / $statistics['examined']) * 100, 2) 
            : 0;
        $statistics['fail_rate'] = $statistics['examined'] > 0 
            ? round(($statistics['failed'] / $statistics['examined']) * 100, 2) 
            : 0;
        $statistics['course_average'] = $statistics['examined'] > 0 
            ? round($statistics['total_marks'] / $statistics['examined'], 2) 
            : 0;

        // Get metadata
        $facultyModel = Faculty::find($faculty);
        $programModel = Program::find($program);
        $sessionModel = Session::find($session);
        $semesterModel = Semester::find($semester);
        $sectionModel = $section != '0' ? Section::find($section) : null;

        return [
            'student_records' => $studentRecords,
            'statistics' => $statistics,
            'mark_distribution' => [
                'attendance' => $attendanceContribution,
                'assignment' => $assignmentContribution,
                'activity' => $activityContribution,
                'ca_exam' => $caExamContribution,
                'ca_total' => $caTotal,
                'exam_total' => $examTotal,
                'grand_total' => $caTotal + $examTotal,
            ],
            'course_meta' => [
                'institution_name' => config('app.name', 'University'),
                'faculty_name' => $facultyModel->title ?? 'N/A',
                'faculty_shortcode' => $facultyModel->shortcode ?? 'N/A',
                'department_name' => $programModel->academicDepartment->title ?? 'N/A',
                'program_name' => $programModel->title ?? 'N/A',
                'program_shortcode' => $programModel->shortcode ?? 'N/A',
                'degree_type' => $programModel->degreeType->title ?? 'N/A',
                'session_title' => $sessionModel->title ?? 'N/A',
                'semester_title' => $semesterModel->title ?? 'N/A',
                'section_title' => $sectionModel->title ?? 'All Sections',
                'course_code' => $subjectModel->code,
                'course_title' => $subjectModel->title,
                'credit_value' => $subjectModel->credit_hour,
                'course_type' => $this->getSubjectTypeLabel($subjectModel->subject_type),
                'lecturers' => $lecturers,
                'level' => $semesterModel->title ?? 'N/A',
                'generated_at' => now()->format('F d, Y H:i:s'),
                'generated_by' => Auth::user()->name ?? 'System',
            ],
            'exam_types' => $examTypes,
            'exam_type_contributions' => $examTypeContributions,
        ];
    }

    /**
     * Get subject type label
     */
    private function getSubjectTypeLabel($type)
    {
        $types = [
            1 => 'Compulsory (C)',
            2 => 'Elective (E)',
            3 => 'University Requirement (UR)',
        ];
        return $types[$type] ?? 'N/A';
    }

    /**
     * Export Individual Course Mark Sheet to Excel
     */
    public function exportCourseMarkSheet(Request $request)
    {
        // Validate required parameters
        $request->validate([
            'faculty' => 'required',
            'program' => 'required',
            'session' => 'required',
            'semester' => 'required',
            'subject' => 'required',
        ]);

        $faculty = $request->faculty;
        $program = $request->program;
        $session = $request->session;
        $semester = $request->semester;
        $section = $request->section ?? '0';
        $subject = $request->subject;

        $markSheetData = $this->generateCourseMarkSheet($faculty, $program, $session, $semester, $section, $subject);
        
        if (isset($markSheetData['error'])) {
            return back()->with('error', $markSheetData['error']);
        }

        // Generate Excel file
        return $this->generateCourseMarkSheetExcel($markSheetData);
    }

    /**
     * Generate Excel file for course mark sheet
     */
    private function generateCourseMarkSheetExcel($data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Course Mark Sheet');

        $meta = $data['course_meta'];
        $distribution = $data['mark_distribution'];
        $records = $data['student_records'];
        $stats = $data['statistics'];
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(10);
        $sheet->getColumnDimension('L')->setWidth(10);
        $sheet->getColumnDimension('M')->setWidth(12);

        // Header section
        $sheet->setCellValue('A1', $meta['institution_name']);
        $sheet->mergeCells('A1:M1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', $meta['faculty_name']);
        $sheet->mergeCells('A2:M2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', $meta['semester_title'] . ' Exam ' . $meta['session_title']);
        $sheet->mergeCells('A3:M3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Course details
        $sheet->setCellValue('A4', 'Department:');
        $sheet->setCellValue('B4', $meta['department_name']);
        $sheet->setCellValue('A5', 'Course Code:');
        $sheet->setCellValue('B5', $meta['course_code']);
        $sheet->setCellValue('A6', 'Course Title:');
        $sheet->setCellValue('B6', $meta['course_title']);
        $sheet->setCellValue('A7', 'Credit Value:');
        $sheet->setCellValue('B7', $meta['credit_value']);
        $sheet->setCellValue('A8', 'Lecturer:');
        $sheet->setCellValue('B8', $meta['lecturers']);
        $sheet->setCellValue('A9', 'Level:');
        $sheet->setCellValue('B9', $meta['level']);

        // Table headers
        $headerRow = 11;
        $headers = ['S/N', 'MAT. NO', 'NAME', 'Exam Code', 'Att/' . $distribution['attendance'], 'CA/' . ($distribution['ca_total'] - $distribution['attendance']), 'Tot/' . $distribution['ca_total'], 'Sign In', 'Sign Out', 'Exam/' . $distribution['exam_total'], 'Total/100', 'Grade', 'Remarks'];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $headerRow, $header);
            $sheet->getStyle($col . $headerRow)->getFont()->setBold(true);
            $sheet->getStyle($col . $headerRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $headerRow)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Student data
        $row = $headerRow + 1;
        foreach ($records as $record) {
            $sheet->setCellValue('A' . $row, $record['sn']);
            $sheet->setCellValue('B' . $row, $record['matricule']);
            $sheet->setCellValue('C' . $row, $record['name']);
            $sheet->setCellValue('D' . $row, $record['exam_code']);
            $sheet->setCellValue('E' . $row, $record['attendance_marks']);
            $sheet->setCellValue('F' . $row, $record['ca_marks'] + $record['assignment_marks'] + $record['activity_marks']);
            $sheet->setCellValue('G' . $row, $record['total_ca']);
            $sheet->setCellValue('H' . $row, ($record['sign_in'] ?? $record['exam_attendance']) ? '✓' : '');
            $sheet->setCellValue('I' . $row, ($record['sign_out'] ?? $record['exam_attendance']) ? '✓' : '');
            $sheet->setCellValue('J' . $row, $record['exam_marks']);
            $sheet->setCellValue('K' . $row, $record['total_marks']);
            $sheet->setCellValue('L' . $row, $record['grade']);
            $sheet->setCellValue('M' . $row, $record['remark']);
            
            // Color coding for pass/fail
            if ($record['remark'] == 'Passed') {
                $sheet->getStyle('M' . $row)->getFont()->getColor()->setRGB('28A745');
            } else {
                $sheet->getStyle('M' . $row)->getFont()->getColor()->setRGB('DC3545');
            }
            
            $row++;
        }

        // Statistics section
        $statsRow = $row + 2;
        $sheet->setCellValue('J' . $statsRow, 'STATISTICS');
        $sheet->getStyle('J' . $statsRow)->getFont()->setBold(true);
        
        $sheet->setCellValue('J' . ($statsRow + 1), 'No. Registered:');
        $sheet->setCellValue('K' . ($statsRow + 1), $stats['registered']);
        
        $sheet->setCellValue('J' . ($statsRow + 2), 'No. Examined:');
        $sheet->setCellValue('K' . ($statsRow + 2), $stats['examined']);
        
        $sheet->setCellValue('J' . ($statsRow + 3), 'No. ABS:');
        $sheet->setCellValue('K' . ($statsRow + 3), $stats['absent']);
        
        $sheet->setCellValue('J' . ($statsRow + 4), 'No. Passed:');
        $sheet->setCellValue('K' . ($statsRow + 4), $stats['passed']);
        
        $sheet->setCellValue('J' . ($statsRow + 5), '% Pass:');
        $sheet->setCellValue('K' . ($statsRow + 5), $stats['pass_rate'] . '%');
        
        $sheet->setCellValue('J' . ($statsRow + 6), 'No. Failed:');
        $sheet->setCellValue('K' . ($statsRow + 6), $stats['failed']);
        
        $sheet->setCellValue('J' . ($statsRow + 7), '% Failed:');
        $sheet->setCellValue('K' . ($statsRow + 7), $stats['fail_rate'] . '%');
        
        $sheet->setCellValue('J' . ($statsRow + 8), 'Course Average:');
        $sheet->setCellValue('K' . ($statsRow + 8), $stats['course_average']);

        // Grade distribution
        $gradeRow = $statsRow + 10;
        $sheet->setCellValue('J' . $gradeRow, 'Grades');
        $sheet->getStyle('J' . $gradeRow)->getFont()->setBold(true);
        
        $gradeCol = 'K';
        foreach ($grades as $grade) {
            $sheet->setCellValue($gradeCol . $gradeRow, $grade->title);
            $sheet->setCellValue($gradeCol . ($gradeRow + 1), $stats['grade_distribution'][$grade->title] ?? 0);
            $gradeCol++;
        }

        // Set borders
        $lastDataRow = $row - 1;
        $sheet->getStyle('A' . $headerRow . ':M' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Create the file
        $filename = $meta['course_code'] . '_' . str_replace(['/', ' '], '_', $meta['session_title']) . '_MarkSheet.xlsx';
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    /**
     * Export department report to Excel
     */
    public function exportDepartment(Request $request)
    {
        // Implementation for Excel export
        // This would use Laravel Excel or PhpSpreadsheet
        return back()->with('success', 'Export functionality coming soon.');
    }

    /**
     * Export faculty report to Excel
     */
    public function exportFaculty(Request $request)
    {
        // Implementation for Excel export
        return back()->with('success', 'Export functionality coming soon.');
    }

    /**
     * Get color for grade (for charts)
     */
    private function getGradeColor($grade)
    {
        $colors = [
            'A+' => '#28a745',
            'A' => '#28a745',
            'B+' => '#5cb85c',
            'B' => '#7dc67d',
            'C+' => '#f0ad4e',
            'C' => '#ffc107',
            'D+' => '#fd7e14',
            'D' => '#dc3545',
            'F' => '#c82333',
        ];

        return $colors[$grade] ?? '#6c757d';
    }

    /**
     * Display the Student Results Summary (All courses per student - Excel RESULTS sheet format)
     * This shows a matrix where each row is a student, and columns show all their courses
     */
    public function studentResultsSummary(Request $request)
    {
        $data = [
            'title' => __('Student Results Summary'),
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'access' => $this->access,
        ];

        // Initialize selected values
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        $data['selected_session'] = $session = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_section'] = $section = $request->section ?? '0';

        // Get filter options
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Load dependent filters
        if ($faculty != '0') {
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }

        if ($program != '0') {
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

        if ($program != '0' && $semester != '0') {
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester) {
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        // Generate student results summary if all required filters are selected
        if ($faculty != '0' && $program != '0' && $session != '0' && $semester != '0') {
            $summaryData = $this->generateStudentResultsSummary($faculty, $program, $session, $semester, $section);
            $data = array_merge($data, $summaryData);
        }

        return view($this->view . '.student-results-summary', $data);
    }

    /**
     * Generate the Student Results Summary data
     * Creates a matrix showing all courses for each student
     */
    private function generateStudentResultsSummary($faculty, $program, $session, $semester, $section)
    {
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $passingMark = 50;

        // Get all subjects for this program/session
        $subjectsQuery = Subject::where('status', '1');
        $subjectsQuery->whereHas('programs', function ($q) use ($program) {
            $q->where('program_id', $program);
        });
        $subjectsQuery->whereHas('classes', function ($q) use ($session) {
            $q->where('session_id', $session);
        });
        $subjects = $subjectsQuery->orderBy('code', 'asc')->get();

        // Get all enrolled students
        $enrollQuery = StudentEnroll::query();
        $enrollQuery->where('session_id', $session);
        $enrollQuery->where('program_id', $program);
        $enrollQuery->where('semester_id', $semester);
        $enrollQuery->whereIn('status', [1, 2]);
        
        if ($section != '0') {
            $enrollQuery->where('section_id', $section);
        }

        $enrollQuery->with([
            'student',
            'program',
            'semester',
            'section',
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

        // Build student results matrix
        $studentResults = [];
        $courseStats = [];
        $overallStats = [
            'total_students' => 0,
            'total_passed' => 0,  // Students who passed ALL courses
            'total_failed' => 0,  // Students who failed at least one course
            'total_pending' => 0, // Students with no results published yet
            'courses_count' => count($subjects),
        ];

        // Initialize course stats with grade distribution
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
                'grade_distribution' => $gradeDistribution, // Track count per grade
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
                    'gpa' => 0,
                ],
            ];

            $allCoursesPass = true;
            $hasAnyExaminedCourse = false; // Track if student has any results

            // Process each subject for this student
            foreach ($subjects as $subject) {
                $subjectId = $subject->id;
                
                // Check if student is registered for this course
                $isRegistered = $enroll->exams->where('subject_id', $subjectId)->isNotEmpty();
                
                if (!$isRegistered) {
                    $studentData['courses'][$subjectId] = [
                        'registered' => false,
                        'attendance_marks' => '-',
                        'ca_marks' => '-',
                        'exam_marks' => '-',
                        'total_marks' => '-',
                        'grade' => '-',
                        'status' => 'NR', // Not Registered
                    ];
                    continue;
                }

                $courseStats[$subjectId]['registered']++;
                $studentData['summary']['total_credits_registered'] += (float)$subject->credit_hour;

                // Get contribution settings for this subject
                $subjectContributions = \App\Services\ResultContributionService::getSubjectContributions($subjectId);
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

                // Get stored marks from SubjectMarking
                $subjectMark = $enroll->subjectMarks->where('subject_id', $subjectId)->first();
                
                // Check if results are published for this subject
                $isPublished = $subjectMark && $subjectMark->workflow_state === 'published';
                
                // If not published, show as not available
                if (!$isPublished) {
                    $studentData['courses'][$subjectId] = [
                        'registered' => true,
                        'attendance_marks' => '-',
                        'ca_marks' => '-',
                        'exam_marks' => '-',
                        'total_marks' => '-',
                        'grade' => '-',
                        'status' => 'NP', // Not Published
                        'grade_point' => 0,
                        'credit_value' => (float)$subject->credit_hour,
                    ];
                    continue;
                }
                
                $storedAttendance = $subjectMark ? (float)($subjectMark->attendances ?? 0) : 0;
                $storedAssignment = $subjectMark ? (float)($subjectMark->assignments ?? 0) : 0;
                $storedActivity = $subjectMark ? (float)($subjectMark->activities ?? 0) : 0;

                // Use calculated or stored attendance
                $attendanceMarks = round($attendanceMarks, 2) ?: round($storedAttendance, 2);

                // Calculate CA and Exam marks
                $caExamMarks = 0;
                $finalExamMarks = 0;
                $wasExamined = false;

                foreach ($enroll->exams->where('subject_id', $subjectId) as $exam) {
                    if ($exam->attendance == 1 && $exam->contribution > 0) {
                        $wasExamined = true;
                        if ($exam->marks > 0) {
                            $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                            $contributedMarks = (($percentOfMarks / 100) * $exam->contribution);
                            
                            if ($exam->type && $exam->type->is_final) {
                                $finalExamMarks += $contributedMarks;
                            } else {
                                $caExamMarks += $contributedMarks;
                            }
                        }
                    }
                }

                $totalCA = round($attendanceMarks + $storedAssignment + $storedActivity + $caExamMarks, 2);
                $examMarks = round($finalExamMarks, 2);
                $totalMarks = round($totalCA + $examMarks, 2);

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
                $status = $wasExamined ? ($passed ? 'P' : 'F') : 'ABS';

                // Update stats
                if ($wasExamined) {
                    $hasAnyExaminedCourse = true; // Student has at least one result
                    $courseStats[$subjectId]['examined']++;
                    $courseStats[$subjectId]['total_marks'] += $totalMarks;
                    
                    // Track grade distribution
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

                $studentData['courses'][$subjectId] = [
                    'registered' => true,
                    'attendance_marks' => round($attendanceMarks, 2),
                    'ca_marks' => round($totalCA - $attendanceMarks, 2),
                    'exam_marks' => round($examMarks, 2),
                    'total_marks' => round($totalMarks, 2),
                    'grade' => $letterGrade,
                    'status' => $status,
                    'grade_point' => $gradePoint,
                    'credit_value' => (float)$subject->credit_hour,
                ];
            }

            // Calculate GPA for student
            if ($studentData['summary']['total_credits_registered'] > 0) {
                $studentData['summary']['gpa'] = round(
                    $studentData['summary']['total_quality_points'] / $studentData['summary']['total_credits_registered'], 
                    2
                );
            }

            // Only count students who have at least one examined course
            if ($hasAnyExaminedCourse) {
                if ($allCoursesPass && $studentData['summary']['courses_failed'] == 0) {
                    $overallStats['total_passed']++;
                } else {
                    $overallStats['total_failed']++;
                }
            } else {
                // No results published yet for this student
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

        // Get metadata
        $facultyModel = Faculty::find($faculty);
        $programModel = Program::with('academicDepartment', 'degreeType')->find($program);
        $sessionModel = Session::find($session);
        $semesterModel = Semester::find($semester);
        $sectionModel = $section != '0' ? Section::find($section) : null;

        return [
            'student_results' => $studentResults,
            'subjects' => $subjects,
            'course_stats' => $courseStats,
            'overall_stats' => $overallStats,
            'grades' => $grades,
            'meta' => [
                'institution_name' => config('app.name', 'Catholic University of Cameroon, CATUC'),
                'faculty_name' => $facultyModel->title ?? 'N/A',
                'faculty_shortcode' => $facultyModel->shortcode ?? 'N/A',
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
            ],
        ];
    }

    /**
     * Export Student Results Summary to Excel (matching Excel RESULTS sheet format)
     */
    public function exportStudentResultsSummary(Request $request)
    {
        $faculty = $request->faculty;
        $program = $request->program;
        $session = $request->session;
        $semester = $request->semester;
        $section = $request->section ?? '0';

        if (!$faculty || !$program || !$session || !$semester) {
            return back()->with('error', 'Please select all required filters.');
        }

        $summaryData = $this->generateStudentResultsSummary($faculty, $program, $session, $semester, $section);
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RESULTS');

        $meta = $summaryData['meta'];
        $subjects = $summaryData['subjects'];
        $studentResults = $summaryData['student_results'];
        $grades = $summaryData['grades'];

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

        // Column headers - Row 6: Course codes, Row 7: Course names
        $sheet->setCellValue('A6', 'S/N');
        $sheet->setCellValue('B6', 'Mat No.');
        $sheet->setCellValue('C6', 'Name of Student');
        
        // Note: A6:A8, B6:B8, C6:C8 will be merged later after row 8 sub-headers are set

        $colIndex = 4; // Start from column D (courses)
        foreach ($subjects as $subject) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4);
            
            // Row 6: Course code and credit value
            $sheet->setCellValue($col . '6', $subject->code . ' (CV:' . $subject->credit_hour . ')');
            $sheet->mergeCells($col . '6:' . $endCol . '6');
            $sheet->getStyle($col . '6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . '6')->getFont()->setBold(true);
            
            // Row 7: Course title
            $sheet->setCellValue($col . '7', $subject->title);
            $sheet->mergeCells($col . '7:' . $endCol . '7');
            $sheet->getStyle($col . '7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . '7')->getFont()->setItalic(true)->setSize(9);
            $sheet->getStyle($col . '7')->getAlignment()->setWrapText(true);
            
            $colIndex += 5;
        }

        // Row 8: Sub-headers (Att, CA, EX, TOT, Grad) - only for course columns
        // Columns A-C are merged across rows 6-8
        $sheet->mergeCells('A6:A8');
        $sheet->mergeCells('B6:B8');
        $sheet->mergeCells('C6:C8');
        $sheet->getStyle('A6:C8')->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $colIndex = 4; // Courses start from column D
        foreach ($subjects as $subject) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($col . '8', 'Att');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . '8', 'CA');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 2) . '8', 'EX');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 3) . '8', 'TOT');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4) . '8', 'Grad');
            $colIndex += 5;
        }

        // Summary columns after courses
        $summaryStartCol = $colIndex;
        $summaryEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4);
        $summaryFirstCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol);
        
        // Summary header spans rows 6-7
        $sheet->setCellValue($summaryFirstCol . '6', 'SUMMARY');
        $sheet->mergeCells($summaryFirstCol . '6:' . $summaryEndCol . '7');
        $sheet->getStyle($summaryFirstCol . '6')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle($summaryFirstCol . '6')->getFont()->setBold(true)->setSize(11);
        
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol) . '8', 'TCR');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 1) . '8', 'TCE');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 2) . '8', 'GPA');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 3) . '8', 'Pass');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4) . '8', 'Fail');

        // Style header row
        $headerRange = 'A8:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4) . '8';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        // Data rows
        $row = 9;
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
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4) . $row, $courseData['grade']);
                    
                    // Color grade cell
                    $gradeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 4);
                    if ($courseData['status'] == 'P') {
                        $sheet->getStyle($gradeCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FF90EE90');
                    } elseif ($courseData['status'] == 'F') {
                        $sheet->getStyle($gradeCol . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFFFCCCB');
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
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 3) . $row, $student['summary']['courses_passed']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4) . $row, $student['summary']['courses_failed']);

            $row++;
        }

        // Auto-size columns
        for ($i = 1; $i <= $summaryStartCol + 4; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // Set thin borders for all cells
        $lastRow = $row - 1;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4);
        $sheet->getStyle('A6:' . $lastCol . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Add thick borders between courses (every 5 columns starting from E)
        $courseColIndex = 5; // Start from column E
        foreach ($subjects as $index => $subject) {
            $firstCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($courseColIndex);
            
            // Left border of each course block (thick)
            $sheet->getStyle($firstCol . '6:' . $firstCol . $lastRow)->getBorders()->getLeft()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
            
            // Background color for course header (alternating)
            $headerColor = ($index % 2 == 0) ? 'FFD9E8FB' : 'FFFFF2CC'; // Light blue / Light yellow
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($courseColIndex + 4);
            $sheet->getStyle($firstCol . '6:' . $endCol . '8')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($headerColor);
            
            $courseColIndex += 5;
        }

        // Summary section styling - left border and background
        $summaryFirstCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol);
        $summaryLastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartCol + 4);
        
        // Thick left border for summary section
        $sheet->getStyle($summaryFirstCol . '6:' . $summaryFirstCol . $lastRow)->getBorders()->getLeft()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        
        // Summary header background (light cyan)
        $sheet->getStyle($summaryFirstCol . '6:' . $summaryLastCol . '8')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0F7FA');

        // Right border of the entire table
        $sheet->getStyle($lastCol . '6:' . $lastCol . $lastRow)->getBorders()->getRight()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Top border of header row
        $sheet->getStyle('A6:' . $lastCol . '6')->getBorders()->getTop()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Bottom border of sub-header row (row 8)
        $sheet->getStyle('A8:' . $lastCol . '8')->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Bottom border of last data row
        $sheet->getStyle('A' . $lastRow . ':' . $lastCol . $lastRow)->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Left border of student info section (columns A-C)
        $sheet->getStyle('A6:A' . $lastRow)->getBorders()->getLeft()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Right border after student name column (column C)
        $sheet->getStyle('C6:C' . $lastRow)->getBorders()->getRight()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Style the student info header (A6:C8)
        $sheet->getStyle('A6:C8')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        // Bold headers
        $sheet->getStyle('A6:' . $lastCol . '8')->getFont()->setBold(true);

        // Center align all data except name column
        $sheet->getStyle('A9:B' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D9:' . $lastCol . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // ===== COURSE SUMMARY SECTION =====
        $courseStats = $summaryData['course_stats'];
        $courseSummaryRow = $lastRow + 3; // Leave 2 blank rows
        
        // Get grade titles for columns
        $gradesList = $grades->pluck('title')->toArray();
        $gradeCount = count($gradesList);
        
        // Calculate last column for Course Summary
        // Fixed columns: S/N, Code, Title, CV, Reg, Exam, then grades, then Pass, Fail, Rate, Avg
        $csLastColIndex = 6 + $gradeCount + 4; // 6 fixed + grades + 4 summary
        $csLastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($csLastColIndex);
        
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
            
            // Color grade headers - green for passing (A, B+, B, C+, C, D), red for failing (F)
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
                
                // Highlight cells with counts > 0
                if ($gradeCount > 0) {
                    $gradeObj = $grades->firstWhere('title', $gradeTitle);
                    $isPassingGrade = $gradeObj && $gradeObj->min_mark >= 50;
                    $cellColor = $isPassingGrade ? 'FFE2EFDA' : 'FFFCE4D6'; // Light green / Light red
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
        $gradeLegendRow = $csLastRow + 3; // Leave 2 blank rows
        
        // Grade Legend Header
        $sheet->setCellValue('A' . $gradeLegendRow, 'GRADE LEGEND');
        $sheet->mergeCells('A' . $gradeLegendRow . ':F' . $gradeLegendRow);
        $sheet->getStyle('A' . $gradeLegendRow)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $gradeLegendRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF70AD47');
        $sheet->getStyle('A' . $gradeLegendRow)->getFont()->getColor()->setARGB('FFFFFFFF');
        
        // Grade Legend Column Headers
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
        
        // Grade Legend Data
        $glDataRow = $glHeaderRow + 1;
        foreach ($grades as $grade) {
            $sheet->setCellValue('A' . $glDataRow, $grade->title);
            $sheet->setCellValue('B' . $glDataRow, $grade->min_mark . ' - ' . $grade->max_mark);
            $sheet->setCellValue('C' . $glDataRow, number_format((float)$grade->point, 2));
            $sheet->setCellValue('D' . $glDataRow, $grade->interpretation ?? '-');
            $sheet->setCellValue('E' . $glDataRow, $grade->remark ?? '-');
            
            // Color based on pass/fail
            $isPassing = $grade->min_mark >= 50;
            $statusColor = $isPassing ? 'FF90EE90' : 'FFFFCCCB';
            $sheet->getStyle('E' . $glDataRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($statusColor);
            
            // Alternating row colors
            if (($glDataRow - $glHeaderRow) % 2 == 0) {
                $sheet->getStyle('A' . $glDataRow . ':D' . $glDataRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF2F2F2');
            }
            
            $glDataRow++;
        }
        
        // Grade Legend borders
        $glLastRow = $glDataRow - 1;
        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glLastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glHeaderRow)->getBorders()->getTop()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle('A' . $glLastRow . ':E' . $glLastRow)->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        
        // Center align grade legend
        $sheet->getStyle('A' . $glHeaderRow . ':E' . $glLastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // ===== OVERALL STATISTICS SECTION =====
        $overallStats = $summaryData['overall_stats'];
        $overallRow = $glLastRow + 3;
        
        // Overall Statistics Header
        $sheet->setCellValue('A' . $overallRow, 'OVERALL STATISTICS');
        $sheet->mergeCells('A' . $overallRow . ':D' . $overallRow);
        $sheet->getStyle('A' . $overallRow)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $overallRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF5B9BD5');
        $sheet->getStyle('A' . $overallRow)->getFont()->getColor()->setARGB('FFFFFFFF');
        
        // Overall Stats Data
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
        
        // Overall stats borders
        $sheet->getStyle('A' . ($overallRow + 1) . ':B' . $osDataRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Generated info
        $genRow = $osDataRow + 2;
        $sheet->setCellValue('A' . $genRow, 'Generated: ' . $meta['generated_at'] . ' by ' . $meta['generated_by']);
        $sheet->getStyle('A' . $genRow)->getFont()->setItalic(true)->setSize(9);

        // Create the file
        $filename = 'Student_Results_Summary_' . str_replace(['/', ' '], '_', $meta['program_shortcode']) . '_' . 
                   str_replace(['/', ' '], '_', $meta['session_title']) . '.xlsx';
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
}
