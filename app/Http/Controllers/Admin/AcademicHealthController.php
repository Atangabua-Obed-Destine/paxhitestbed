<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sector;
use App\Models\Faculty;
use App\Models\AcademicDepartment;
use App\Models\DegreeType;
use App\Models\Program;
use App\Models\Batch;
use App\Models\Session;
use App\Models\Semester;
use App\Models\Section;
use App\Models\Subject;
use App\Models\EnrollSubject;
use App\Models\ClassRoom;
use App\Models\ClassRoutine;
use App\Models\ExamRoutine;
use App\Models\FeesCategory;
use App\Models\ProgramSemesterFee;
use App\Models\FeesMaster;
use App\Models\StaffAssignment;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Department;
use App\Models\Grade;
use App\Models\ExamType;
use App\User;

class AcademicHealthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web');
    }

    public function index()
    {
        $data['title'] = 'Academic Configuration Health Report';

        // ═══════════════════════════════════════════════════
        // CURRENT SESSION
        // ═══════════════════════════════════════════════════
        $currentSession = Session::where('current', '1')->first();
        $data['current_session'] = $currentSession;
        $data['all_sessions'] = Session::orderBy('id', 'desc')->get();

        // ═══════════════════════════════════════════════════
        // SEMESTERS
        // ═══════════════════════════════════════════════════
        $activeSemesters = Semester::where('status', '1')->where('is_resit', 0)->orderBy('year')->orderBy('semester_type')->get();
        $data['active_semesters'] = $activeSemesters;
        $data['resit_semesters'] = Semester::where('status', '1')->where('is_resit', 1)->get();

        // ═══════════════════════════════════════════════════
        // KPI METRICS
        // ═══════════════════════════════════════════════════
        $totalFaculties = Faculty::where('status', '1')->count();
        $totalDepartments = AcademicDepartment::count();
        $totalPrograms = Program::where('status', '1')->count();
        $totalSubjects = Subject::where('status', '1')->count();
        $totalStudents = Student::where('status', '1')->count();
        
        // Staff who are teachers (have class routines assigned)
        $teacherIds = ClassRoutine::distinct('teacher_id')->pluck('teacher_id');
        $totalTeachers = User::whereIn('id', $teacherIds)->where('status', '1')->count();
        $totalStaff = User::where('status', '1')->count();
        
        // Fee coverage
        $programsWithFees = ProgramSemesterFee::distinct('program_id')->pluck('program_id')->count();
        $feeCoverage = $totalPrograms > 0 ? round(($programsWithFees / $totalPrograms) * 100) : 0;
        
        // Overall readiness score (weighted)
        $checks = [];
        $checks[] = $currentSession ? 1 : 0;  // Has current session
        $checks[] = $activeSemesters->count() > 0 ? 1 : 0;  // Has semesters
        $checks[] = $totalFaculties > 0 ? 1 : 0;
        $checks[] = $totalPrograms > 0 ? 1 : 0;
        $checks[] = $totalSubjects > 0 ? 1 : 0;
        $checks[] = EnrollSubject::count() > 0 ? 1 : 0;
        $checks[] = $feeCoverage >= 80 ? 1 : ($feeCoverage >= 50 ? 0.5 : 0);
        $checks[] = Grade::where('status', '1')->count() > 0 ? 1 : 0;
        $checks[] = ExamType::where('status', '1')->sum('contribution') == 100 ? 1 : 0;
        $overallScore = count($checks) > 0 ? round((array_sum($checks) / count($checks)) * 100) : 0;

        $data['kpis'] = [
            'total_faculties' => $totalFaculties,
            'total_departments' => $totalDepartments,
            'total_programs' => $totalPrograms,
            'total_subjects' => $totalSubjects,
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'total_staff' => $totalStaff,
            'fee_coverage' => $feeCoverage,
            'overall_score' => $overallScore,
        ];

        // ═══════════════════════════════════════════════════
        // CONFIGURATION PIPELINE
        // ═══════════════════════════════════════════════════
        $data['pipeline'] = [
            ['label' => 'Sectors', 'count' => Sector::count(), 'ok' => Sector::count() > 0, 'route' => route('admin.sector.index')],
            ['label' => 'Faculties', 'count' => $totalFaculties, 'ok' => $totalFaculties > 0, 'route' => route('admin.faculty.index')],
            ['label' => 'Departments', 'count' => $totalDepartments, 'ok' => $totalDepartments > 0, 'route' => route('admin.academic-department.index')],
            ['label' => 'Programs', 'count' => $totalPrograms, 'ok' => $totalPrograms > 0, 'route' => route('admin.program.index')],
            ['label' => 'Subjects', 'count' => $totalSubjects, 'ok' => $totalSubjects > 0, 'route' => route('admin.subject.index')],
            ['label' => 'Course Offerings', 'count' => EnrollSubject::count(), 'ok' => EnrollSubject::count() > 0, 'route' => route('admin.enroll-subject.index')],
            ['label' => 'Fee Configs', 'count' => ProgramSemesterFee::count(), 'ok' => ProgramSemesterFee::count() > 0, 'route' => route('admin.program-semester-fee.index')],
            ['label' => 'Grades', 'count' => Grade::where('status', '1')->count(), 'ok' => Grade::where('status', '1')->count() > 0, 'route' => route('admin.grade.index')],
        ];

        // ═══════════════════════════════════════════════════
        // TAB 2: FACULTY HIERARCHY REPORT
        // ═══════════════════════════════════════════════════
        $faculties = Faculty::where('status', '1')
            ->with(['academicDepartments', 'programs.degreeType'])
            ->orderBy('title')
            ->get();

        $facultyReport = [];
        foreach ($faculties as $faculty) {
            $fData = [
                'id' => $faculty->id,
                'title' => $faculty->title,
                'shortcode' => $faculty->shortcode ?? '—',
                'dean' => $faculty->dean_name ?? 'Not Assigned',
                'departments' => [],
                'total_programs' => 0,
                'total_subjects' => 0,
                'total_offerings' => 0,
                'total_students' => 0,
                'fee_configs' => 0,
                'routines' => 0,
            ];

            $departments = AcademicDepartment::where('faculty_id', $faculty->id)->with('headOfDepartment')->get();
            
            foreach ($departments as $dept) {
                $deptPrograms = Program::where('faculty_id', $faculty->id)
                    ->where('academic_department_id', $dept->id)
                    ->where('status', '1')
                    ->with('degreeType')
                    ->get();

                $deptData = [
                    'id' => $dept->id,
                    'title' => $dept->title,
                    'shortcode' => $dept->shortcode ?? '—',
                    'hod' => $dept->headOfDepartment ? ($dept->headOfDepartment->first_name . ' ' . $dept->headOfDepartment->last_name) : 'Not Assigned',
                    'programs' => [],
                ];

                foreach ($deptPrograms as $program) {
                    // Subjects for this program
                    $subjectCount = $program->subjects()->where('status', '1')->count();
                    
                    // Enroll subjects (course offerings)
                    $offeringsCount = EnrollSubject::where('program_id', $program->id)->count();
                    
                    // Enrolled subjects details per semester
                    $enrollSubjects = EnrollSubject::where('program_id', $program->id)
                        ->with(['semester', 'subjects'])
                        ->get();

                    $semesterOfferings = [];
                    foreach ($enrollSubjects as $es) {
                        $semLabel = $es->semester ? $es->semester->title : 'Unknown';
                        $semesterOfferings[] = [
                            'semester' => $semLabel,
                            'courses' => $es->subjects->map(function($s) {
                                return ['code' => $s->code, 'title' => $s->title, 'credits' => $s->credit_hour];
                            })->toArray(),
                            'course_count' => $es->subjects->count(),
                        ];
                    }

                    // Students enrolled in this program
                    $studentCount = Student::where('program_id', $program->id)->where('status', '1')->count();
                    
                    // Fee configurations
                    $feeCount = ProgramSemesterFee::where('program_id', $program->id)->count();
                    $feeSemesters = ProgramSemesterFee::where('program_id', $program->id)
                        ->with(['semester', 'feesCategory'])
                        ->get()
                        ->groupBy('semester_id');

                    // Class routines
                    $routineCount = 0;
                    if ($currentSession) {
                        $routineCount = ClassRoutine::where('program_id', $program->id)
                            ->where('session_id', $currentSession->id)
                            ->count();
                    }

                    // Teachers assigned to this program's routines
                    $programTeacherIds = [];
                    if ($currentSession) {
                        $programTeacherIds = ClassRoutine::where('program_id', $program->id)
                            ->where('session_id', $currentSession->id)
                            ->distinct('teacher_id')
                            ->pluck('teacher_id')
                            ->toArray();
                    }
                    $programTeachers = User::whereIn('id', $programTeacherIds)
                        ->select('id', 'first_name', 'last_name', 'department_id')
                        ->with('department')
                        ->get();

                    $deptData['programs'][] = [
                        'id' => $program->id,
                        'title' => $program->title,
                        'shortcode' => $program->shortcode ?? '—',
                        'degree' => $program->degreeType ? $program->degreeType->shortcode : '—',
                        'subjects' => $subjectCount,
                        'offerings' => $offeringsCount,
                        'semester_offerings' => $semesterOfferings,
                        'students' => $studentCount,
                        'fees' => $feeCount,
                        'fee_semesters' => $feeSemesters,
                        'routines' => $routineCount,
                        'teachers' => $programTeachers,
                        'has_fees' => $feeCount > 0,
                        'has_offerings' => $offeringsCount > 0,
                        'has_routines' => $routineCount > 0,
                    ];

                    $fData['total_programs']++;
                    $fData['total_subjects'] += $subjectCount;
                    $fData['total_offerings'] += $offeringsCount;
                    $fData['total_students'] += $studentCount;
                    $fData['fee_configs'] += $feeCount;
                    $fData['routines'] += $routineCount;
                }

                // Programs not assigned to any department
                $fData['departments'][] = $deptData;
            }

            // Programs in this faculty without a department
            $unassignedPrograms = Program::where('faculty_id', $faculty->id)
                ->where('status', '1')
                ->where(function($q) use ($departments) {
                    $q->whereNull('academic_department_id')
                      ->orWhereNotIn('academic_department_id', $departments->pluck('id'));
                })
                ->with('degreeType')
                ->get();
            
            if ($unassignedPrograms->count() > 0) {
                $unassignedDept = [
                    'id' => 0,
                    'title' => 'Unassigned (No Department)',
                    'shortcode' => '—',
                    'hod' => '—',
                    'programs' => [],
                ];
                foreach ($unassignedPrograms as $program) {
                    $subjectCount = $program->subjects()->where('status', '1')->count();
                    $offeringsCount = EnrollSubject::where('program_id', $program->id)->count();
                    $studentCount = Student::where('program_id', $program->id)->where('status', '1')->count();
                    $feeCount = ProgramSemesterFee::where('program_id', $program->id)->count();

                    $unassignedDept['programs'][] = [
                        'id' => $program->id,
                        'title' => $program->title,
                        'shortcode' => $program->shortcode ?? '—',
                        'degree' => $program->degreeType ? $program->degreeType->shortcode : '—',
                        'subjects' => $subjectCount,
                        'offerings' => $offeringsCount,
                        'semester_offerings' => [],
                        'students' => $studentCount,
                        'fees' => $feeCount,
                        'fee_semesters' => collect(),
                        'routines' => 0,
                        'teachers' => collect(),
                        'has_fees' => $feeCount > 0,
                        'has_offerings' => $offeringsCount > 0,
                        'has_routines' => false,
                    ];
                    $fData['total_programs']++;
                    $fData['total_subjects'] += $subjectCount;
                    $fData['total_offerings'] += $offeringsCount;
                    $fData['total_students'] += $studentCount;
                    $fData['fee_configs'] += $feeCount;
                }
                $fData['departments'][] = $unassignedDept;
            }

            $facultyReport[] = $fData;
        }
        $data['faculty_report'] = $facultyReport;

        // ═══════════════════════════════════════════════════
        // TAB 3: SEMESTER CONFIGURATION MATRIX
        // ═══════════════════════════════════════════════════
        $allPrograms = Program::where('status', '1')->with(['faculty', 'degreeType'])->orderBy('title')->get();
        $semesterMatrix = [];
        foreach ($allPrograms as $prog) {
            $row = [
                'program' => $prog->title,
                'shortcode' => $prog->shortcode ?? '—',
                'faculty' => $prog->faculty ? $prog->faculty->shortcode : '—',
                'degree' => $prog->degreeType ? $prog->degreeType->shortcode : '—',
                'semesters' => [],
            ];
            foreach ($activeSemesters as $sem) {
                $es = EnrollSubject::where('program_id', $prog->id)
                    ->where('semester_id', $sem->id)
                    ->with('subjects')
                    ->first();
                $hasFee = ProgramSemesterFee::where('program_id', $prog->id)
                    ->where('semester_id', $sem->id)
                    ->exists();
                $hasRoutine = false;
                if ($currentSession) {
                    $hasRoutine = ClassRoutine::where('program_id', $prog->id)
                        ->where('semester_id', $sem->id)
                        ->where('session_id', $currentSession->id)
                        ->exists();
                }
                $row['semesters'][$sem->id] = [
                    'courses' => $es ? $es->subjects->count() : 0,
                    'has_fee' => $hasFee,
                    'has_routine' => $hasRoutine,
                ];
            }
            $semesterMatrix[] = $row;
        }
        $data['semester_matrix'] = $semesterMatrix;

        // ═══════════════════════════════════════════════════
        // TAB 4: STAFF & TEACHING
        // ═══════════════════════════════════════════════════
        $staffDepartments = Department::where('status', '1')->with('users')->get();
        $data['staff_departments'] = $staffDepartments;
        
        // Teachers with their assignments
        $teachers = User::whereIn('id', $teacherIds)
            ->where('status', '1')
            ->with(['department', 'designation'])
            ->get();
        
        $teacherReport = [];
        foreach ($teachers as $teacher) {
            $routines = ClassRoutine::where('teacher_id', $teacher->id);
            if ($currentSession) {
                $routines = $routines->where('session_id', $currentSession->id);
            }
            $routines = $routines->with(['subject', 'program', 'semester'])->get();
            
            $teacherReport[] = [
                'id' => $teacher->id,
                'name' => $teacher->first_name . ' ' . $teacher->last_name,
                'department' => $teacher->department ? $teacher->department->title : 'Not Assigned',
                'designation' => $teacher->designation ? $teacher->designation->title : '—',
                'courses_taught' => $routines->pluck('subject.title')->unique()->filter()->count(),
                'programs' => $routines->pluck('program.shortcode')->unique()->filter()->implode(', '),
                'total_slots' => $routines->count(),
            ];
        }
        usort($teacherReport, function($a, $b) { return $b['courses_taught'] - $a['courses_taught']; });
        $data['teacher_report'] = $teacherReport;

        // Staff with no teaching assignments
        $staffWithoutTeaching = User::where('status', '1')
            ->whereNotIn('id', $teacherIds)
            ->whereHas('roles', function($q) {
                $q->where('name', 'like', '%teacher%')
                  ->orWhere('name', 'like', '%lecturer%');
            })
            ->with(['department', 'designation'])
            ->get();
        $data['staff_without_teaching'] = $staffWithoutTeaching;

        // ═══════════════════════════════════════════════════
        // TAB 5: FINANCIAL
        // ═══════════════════════════════════════════════════
        $allFees = ProgramSemesterFee::with(['program.faculty', 'semester', 'feesCategory'])
            ->orderBy('program_id')
            ->orderBy('semester_id')
            ->get();
        $data['all_fees'] = $allFees;

        // Programs without ANY fee configuration
        $programIdsWithFees = ProgramSemesterFee::distinct('program_id')->pluck('program_id');
        $programsWithoutFees = Program::where('status', '1')
            ->whereNotIn('id', $programIdsWithFees)
            ->with(['faculty', 'degreeType'])
            ->get();
        $data['programs_without_fees'] = $programsWithoutFees;

        // Students enrolled in programs without fees
        $studentsWithoutFeeProgram = 0;
        if ($programsWithoutFees->count() > 0) {
            $studentsWithoutFeeProgram = Student::where('status', '1')
                ->whereIn('program_id', $programsWithoutFees->pluck('id'))
                ->count();
        }
        $data['students_without_fee_program'] = $studentsWithoutFeeProgram;

        // ═══════════════════════════════════════════════════
        // TAB 6: DIAGNOSTICS
        // ═══════════════════════════════════════════════════
        $diagnostic_errors = [];
        $warnings = [];
        $healthy = [];

        // Session
        $currentSessions = Session::where('current', '1')->get();
        if ($currentSessions->count() == 0) {
            $diagnostic_errors[] = ['category' => 'Session', 'title' => 'No Current Session', 'message' => 'No session is marked as Current.', 'action_link' => route('admin.session.index'), 'action_text' => 'Manage Sessions'];
        } elseif ($currentSessions->count() > 1) {
            $diagnostic_errors[] = ['category' => 'Session', 'title' => 'Multiple Current Sessions', 'message' => 'Multiple sessions are marked as Current.', 'action_link' => route('admin.session.index'), 'action_text' => 'Fix Sessions'];
        } else {
            $healthy[] = ['category' => 'Session', 'title' => 'Current Session Valid', 'message' => "'{$currentSession->title}' is set as current."];
        }

        // Semesters
        if ($activeSemesters->count() == 0) {
            $diagnostic_errors[] = ['category' => 'Semester', 'title' => 'No Active Semesters', 'message' => 'No active regular semesters.', 'action_link' => route('admin.semester.index'), 'action_text' => 'Manage Semesters'];
        } else {
            $healthy[] = ['category' => 'Semester', 'title' => 'Semesters Configured', 'message' => $activeSemesters->count() . ' active semester(s).'];
        }

        // Faculties
        if ($totalFaculties == 0) {
            $diagnostic_errors[] = ['category' => 'Structure', 'title' => 'No Faculties', 'message' => 'No active faculties.', 'action_link' => route('admin.faculty.index'), 'action_text' => 'Create Faculty'];
        } else {
            $healthy[] = ['category' => 'Structure', 'title' => 'Faculties OK', 'message' => "{$totalFaculties} active faculty/faculties."];
        }

        // Programs without offerings
        $progsNoOfferings = Program::where('status', '1')
            ->whereDoesntHave('subjects')
            ->count();
        if ($progsNoOfferings > 0) {
            $warnings[] = ['category' => 'Programs', 'title' => "{$progsNoOfferings} Programs Without Subjects", 'message' => 'These programs have no subjects assigned.', 'action_link' => route('admin.subject.index'), 'action_text' => 'Manage Subjects'];
        }

        // Fee coverage
        if ($programsWithoutFees->count() > 0) {
            $diagnostic_errors[] = ['category' => 'Financial', 'title' => $programsWithoutFees->count() . ' Programs Without Fees', 'message' => "These programs have no fee configuration. {$studentsWithoutFeeProgram} students affected.", 'action_link' => route('admin.program-semester-fee.index'), 'action_text' => 'Configure Fees'];
        } else {
            $healthy[] = ['category' => 'Financial', 'title' => 'All Programs Have Fees', 'message' => 'Every active program has at least one fee config.'];
        }

        // Exam types
        $examContribution = ExamType::where('status', '1')->sum('contribution');
        if ($examContribution != 100) {
            $diagnostic_errors[] = ['category' => 'Grading', 'title' => 'Invalid Exam Contributions', 'message' => "Sum is {$examContribution}%, must be 100%.", 'action_link' => route('admin.exam-type.index'), 'action_text' => 'Fix Exam Types'];
        } else {
            $healthy[] = ['category' => 'Grading', 'title' => 'Exam Contributions Valid', 'message' => 'Sum is exactly 100%.'];
        }

        // Grades
        $gradeCount = Grade::where('status', '1')->count();
        if ($gradeCount == 0) {
            $diagnostic_errors[] = ['category' => 'Grading', 'title' => 'No Grading Scale', 'message' => 'No grades configured.', 'action_link' => route('admin.grade.index'), 'action_text' => 'Setup Grades'];
        } else {
            $healthy[] = ['category' => 'Grading', 'title' => 'Grades Configured', 'message' => "{$gradeCount} grades."];
        }

        // Class routines for current session
        if ($currentSession) {
            $routineCount = ClassRoutine::where('session_id', $currentSession->id)->count();
            if ($routineCount == 0) {
                $warnings[] = ['category' => 'Timetable', 'title' => 'No Class Routines', 'message' => 'No class timetable entries for the current session.', 'action_link' => route('admin.class-routine.index'), 'action_text' => 'Create Routines'];
            } else {
                $healthy[] = ['category' => 'Timetable', 'title' => 'Routines Configured', 'message' => "{$routineCount} class routine entries."];
            }

            $examRoutineCount = ExamRoutine::where('session_id', $currentSession->id)->count();
            if ($examRoutineCount == 0) {
                $warnings[] = ['category' => 'Timetable', 'title' => 'No Exam Routines', 'message' => 'No exam timetable entries for the current session.', 'action_link' => route('admin.exam-routine.index'), 'action_text' => 'Create Exam Routines'];
            } else {
                $healthy[] = ['category' => 'Timetable', 'title' => 'Exam Routines OK', 'message' => "{$examRoutineCount} exam routine entries."];
            }
        }

        // Classrooms
        $classrooms = ClassRoom::where('status', '1')->count();
        if ($classrooms == 0) {
            $warnings[] = ['category' => 'Infrastructure', 'title' => 'No Classrooms', 'message' => 'No active classrooms configured.', 'action_link' => route('admin.room.index'), 'action_text' => 'Add Rooms'];
        } else {
            $healthy[] = ['category' => 'Infrastructure', 'title' => 'Classrooms OK', 'message' => "{$classrooms} active classrooms."];
        }

        $data['diagnostic_errors'] = $diagnostic_errors;
        $data['warnings'] = $warnings;
        $data['healthy'] = $healthy;

        return view('admin.academic-health.index', $data);
    }
}
