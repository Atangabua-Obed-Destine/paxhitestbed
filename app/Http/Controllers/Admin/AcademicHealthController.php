<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\AcademicHealthExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
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
        // The report exposes staffing gaps, fee coverage and enrolment
        // figures for the whole school. auth:web alone let any account read
        // it, which no other admin module allows.
        $this->middleware('permission:academic-health-view');
    }

    public function index()
    {
        return view('admin.academic-health.index', $this->report());
    }

    /**
     * The same report as a workbook.
     *
     * Built from report() rather than re-querying, so what is sent to the
     * school cannot disagree with what the screen showed.
     */
    public function export()
    {
        $data = $this->report();

        $filename = 'academic-health-'
            . str_replace(['/', ' '], '-', strtolower($data['current_session']->title ?? 'no-session'))
            . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new AcademicHealthExport($data), $filename);
    }

    /** Everything both the screen and the workbook are built from. */
    protected function report(): array
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
        
        // Overall readiness score.
        //
        // Weighted by consequence, not counted equally. Every check used to be
        // worth the same ninth of the score, so a school that could not compute
        // a single mark still scored 89% — a number that cannot tell "cosmetic"
        // from "results cannot be published" is not a health score.
        //
        // The weights say what breaks if the check fails:
        //   4  nothing can run at all — no session, no semester, no grading
        //   2  a whole area is unusable — no programmes, no subjects, no fees
        //   1  incomplete, but the year still runs
        $activeSubjectCount = Subject::where('status', '1')->count();
        $subjectsWithDistribution = DB::table('result_contributions')
            ->where('status', 1)->distinct()->count('subject_id');

        $weightedChecks = [
            ['weight' => 4, 'score' => $currentSession ? 1 : 0],
            ['weight' => 4, 'score' => $activeSemesters->count() > 0 ? 1 : 0],
            // Marks cannot be computed without a distribution, so a partial
            // configuration scores partially rather than passing outright.
            ['weight' => 4, 'score' => $activeSubjectCount > 0
                ? min(1, $subjectsWithDistribution / $activeSubjectCount) : 0],
            ['weight' => 4, 'score' => Grade::where('status', '1')->count() > 0 ? 1 : 0],
            ['weight' => 2, 'score' => $totalFaculties > 0 ? 1 : 0],
            ['weight' => 2, 'score' => $totalPrograms > 0 ? 1 : 0],
            ['weight' => 2, 'score' => $totalSubjects > 0 ? 1 : 0],
            ['weight' => 2, 'score' => EnrollSubject::count() > 0 ? 1 : 0],
            ['weight' => 2, 'score' => $feeCoverage >= 80 ? 1 : ($feeCoverage >= 50 ? 0.5 : 0)],
            ['weight' => 1, 'score' => ClassRoom::where('status', '1')->count() > 0 ? 1 : 0],
        ];

        $weightTotal = array_sum(array_column($weightedChecks, 'weight'));
        $weightEarned = array_sum(array_map(
            fn ($c) => $c['weight'] * $c['score'],
            $weightedChecks
        ));

        $overallScore = $weightTotal > 0 ? round(($weightEarned / $weightTotal) * 100) : 0;

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
        // Counted once each rather than twice per row: every entry below used to
        // run its count() a second time to decide 'ok'.
        $sectorCount = Sector::count();
        $offeringCount = EnrollSubject::count();
        $feeConfigCount = ProgramSemesterFee::count();
        $gradeCountPipeline = Grade::where('status', '1')->count();

        $data['pipeline'] = [
            // Optional, not failed. Sectors sit above faculties for
            // institutions that group them; this one does not use them, and
            // marking that "NOT OK" put a permanent red step in the pipeline
            // that raised no warning and cost no score — a signal that means
            // nothing teaches people to skip the ones that do.
            ['label' => 'Sectors', 'count' => $sectorCount, 'ok' => true, 'optional' => true, 'route' => route('admin.sector.index')],
            ['label' => 'Faculties', 'count' => $totalFaculties, 'ok' => $totalFaculties > 0, 'route' => route('admin.faculty.index')],
            ['label' => 'Departments', 'count' => $totalDepartments, 'ok' => $totalDepartments > 0, 'route' => route('admin.academic-department.index')],
            ['label' => 'Programs', 'count' => $totalPrograms, 'ok' => $totalPrograms > 0, 'route' => route('admin.program.index')],
            ['label' => trans_choice('module_subject', 2), 'count' => $totalSubjects, 'ok' => $totalSubjects > 0, 'route' => route('admin.subject.index')],
            ['label' => 'Course Offerings', 'count' => $offeringCount, 'ok' => $offeringCount > 0, 'route' => route('admin.enroll-subject.index')],
            ['label' => 'Fee Configs', 'count' => $feeConfigCount, 'ok' => $feeConfigCount > 0, 'route' => route('admin.program-semester-fee.index')],
            ['label' => 'Grades', 'count' => $gradeCountPipeline, 'ok' => $gradeCountPipeline > 0, 'route' => route('admin.grade.index')],
        ];

        // ═══════════════════════════════════════════════════
        // TAB 2: FACULTY HIERARCHY REPORT
        // ═══════════════════════════════════════════════════
        $faculties = Faculty::where('status', '1')
            ->with(['academicDepartments', 'programs.degreeType'])
            ->orderBy('title')
            ->get();

        $facultyReport = [];
        // Every offering, with its semester and subjects, fetched once. The
        // loop below asked per programme, and each ask re-ran the eager load —
        // the same "select * from semesters" ran twenty-two times drawing one
        // report.
        $offeringsByProgram = EnrollSubject::with(['semester', 'subjects'])
            ->get()->groupBy('program_id');

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
                    $enrollSubjects = $offeringsByProgram->get($program->id) ?? collect();
                    $offeringsCount = $enrollSubjects->count();

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

        // Three lookups for every programme-and-semester cell ran a query each,
        // so a twelve-programme school with two semesters spent 72 queries
        // drawing one table. Fetched once and indexed by programme:semester
        // instead, which is three queries however large the school grows.
        $offeringCourseCounts = DB::table('enroll_subjects as es')
            ->leftJoin('enroll_subject_subject as ess', 'ess.enroll_subject_id', '=', 'es.id')
            ->selectRaw('es.program_id, es.semester_id, COUNT(ess.subject_id) as course_count')
            ->groupBy('es.program_id', 'es.semester_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->program_id . ':' . $r->semester_id => (int) $r->course_count])
            ->all();

        $feeConfigured = ProgramSemesterFee::select('program_id', 'semester_id')->distinct()->get()
            ->mapWithKeys(fn ($r) => [$r->program_id . ':' . $r->semester_id => true])
            ->all();

        $routineConfigured = $currentSession
            ? ClassRoutine::where('session_id', $currentSession->id)
                ->select('program_id', 'semester_id')->distinct()->get()
                ->mapWithKeys(fn ($r) => [$r->program_id . ':' . $r->semester_id => true])
                ->all()
            : [];

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
                $key = $prog->id . ':' . $sem->id;

                $row['semesters'][$sem->id] = [
                    'courses' => $offeringCourseCounts[$key] ?? 0,
                    'has_fee' => isset($feeConfigured[$key]),
                    'has_routine' => isset($routineConfigured[$key]),
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
        
        // One query for every teacher's routines, grouped in memory, rather than
        // one query per teacher plus its eager loads. Each teacher's slice is
        // then just an array lookup.
        $routineQuery = ClassRoutine::whereIn('teacher_id', $teachers->pluck('id'))
            ->with(['subject', 'program']);

        if ($currentSession) {
            $routineQuery->where('session_id', $currentSession->id);
        }

        $routinesByTeacher = $routineQuery->get()->groupBy('teacher_id');

        $teacherReport = [];
        foreach ($teachers as $teacher) {
            $routines = $routinesByTeacher->get($teacher->id) ?? collect();

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
            $warnings[] = ['category' => 'Programs', 'title' => "{$progsNoOfferings} Programs Without " . trans_choice('module_subject', 2), 'message' => 'These programs have no courses assigned.', 'action_link' => route('admin.subject.index'), 'action_text' => __('Manage') . ' ' . trans_choice('module_subject', 2)];
        }

        // Fee coverage
        if ($programsWithoutFees->count() > 0) {
            $diagnostic_errors[] = ['category' => 'Financial', 'title' => $programsWithoutFees->count() . ' Programs Without Fees', 'message' => "These programs have no fee configuration. {$studentsWithoutFeeProgram} students affected.", 'action_link' => route('admin.program-semester-fee.index'), 'action_text' => 'Configure Fees'];
        } else {
            $healthy[] = ['category' => 'Financial', 'title' => 'All Programs Have Fees', 'message' => 'Every active program has at least one fee config.'];
        }

        // Grading weights
        //
        // Checked per subject, because that is what actually grades. The global
        // ExamType.contribution column is not used to compute a mark — marks
        // resolve through AssessmentWeightService and the per-subject rows in
        // exam_type_contributions — so testing that column reported a permanent
        // error against a correctly configured school. A red flag that is always
        // red teaches everyone to ignore it, and the day grading really breaks
        // it looks exactly the same.
        $activeSubjectIds = Subject::where('status', '1')->pluck('id');
        $configuredSubjectIds = DB::table('result_contributions')
            ->where('status', 1)->whereIn('subject_id', $activeSubjectIds)
            ->distinct()->pluck('subject_id');

        $unconfiguredSubjects = $activeSubjectIds->diff($configuredSubjectIds);

        if ($activeSubjectIds->isEmpty()) {
            // Nothing to grade yet; the structural checks above already say so.
        } elseif ($unconfiguredSubjects->isNotEmpty()) {
            $diagnostic_errors[] = [
                'category' => 'Grading',
                'title' => $unconfiguredSubjects->count() . ' ' . trans_choice('module_subject', 2) . ' Without Mark Distribution',
                'message' => 'Marks for these subjects cannot be computed until their distribution is set.',
                'action_link' => route('admin.subject.index'),
                'action_text' => 'Configure Distribution',
            ];
        } else {
            $healthy[] = [
                'category' => 'Grading',
                'title' => 'Mark Distribution Configured',
                'message' => 'All ' . $activeSubjectIds->count() . ' active courses have a mark distribution.',
            ];
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

        // Programme-and-semester combinations with no courses.
        //
        // The counts elsewhere hide this: a programme showing "31 courses" can
        // still have a semester with none, and nobody can be taught in it. Only
        // combinations that actually have students are raised, because an
        // unused semester on a programme nobody is enrolled in is not urgent.
        $enrolledCombinations = StudentEnroll::whereNotNull('semester_id')
            ->select('program_id', 'semester_id')->distinct()->get()
            ->map(fn ($e) => $e->program_id . ':' . $e->semester_id)
            ->flip();

        $semestersWithoutCourses = [];

        foreach ($semesterMatrix as $matrixRow) {
            foreach ($matrixRow['semesters'] as $semesterId => $cell) {
                if ($cell['courses'] > 0) {
                    continue;
                }

                $programId = collect($allPrograms)->firstWhere('title', $matrixRow['program'])->id ?? null;

                if ($programId && $enrolledCombinations->has($programId . ':' . $semesterId)) {
                    $semestersWithoutCourses[] = $matrixRow['program'] . ' — '
                        . ($activeSemesters->firstWhere('id', $semesterId)->title ?? 'semester ' . $semesterId);
                }
            }
        }

        if ($semestersWithoutCourses !== []) {
            $diagnostic_errors[] = [
                'category' => 'Courses',
                'title' => count($semestersWithoutCourses) . ' Semester(s) With No Courses',
                'message' => 'Students are enrolled in these but no course is assigned, so they cannot be taught or graded: '
                    . implode('; ', $semestersWithoutCourses) . '.',
                'action_link' => route('admin.enroll-subject.index'),
                'action_text' => 'Assign Courses',
            ];
        }

        // ═══════════════════════════════════════════════════
        // RESULTS READINESS
        // ═══════════════════════════════════════════════════
        //
        // Configuration being right does not mean the semester can close. These
        // are the things that actually hold a session open, and none of them
        // were reported: an administrator could read a clean health page while
        // sixteen enrolments had no marks at all.
        $draftMarks = DB::table('subject_markings')->where('workflow_state', 'draft')->count();
        $publishedMarks = DB::table('subject_markings')->where('workflow_state', 'published')->count();
        $enrolmentsWithoutMarks = StudentEnroll::whereDoesntHave('subjectMarks')->count();
        $totalEnrolments = StudentEnroll::count();

        $data['results_readiness'] = [
            'draft_marks' => $draftMarks,
            'published_marks' => $publishedMarks,
            'enrolments_without_marks' => $enrolmentsWithoutMarks,
            'total_enrolments' => $totalEnrolments,
            'published_percent' => ($draftMarks + $publishedMarks) > 0
                ? round(($publishedMarks / ($draftMarks + $publishedMarks)) * 100)
                : 0,
        ];

        if ($draftMarks > 0) {
            $warnings[] = [
                'category' => 'Results',
                'title' => "{$draftMarks} Marks Still Unpublished",
                'message' => 'These marks are entered but not published, so they do not appear on any transcript or result sheet.',
                'action_link' => route('admin.subject-marking.index'),
                'action_text' => 'Review Marks',
            ];
        }

        if ($enrolmentsWithoutMarks > 0) {
            $warnings[] = [
                'category' => 'Results',
                'title' => "{$enrolmentsWithoutMarks} Enrolments With No Marks",
                'message' => 'No mark has been recorded against these enrolments at all. The semester cannot be closed until they are entered or the enrolments withdrawn.',
                'action_link' => route('admin.subject-marking.index'),
                'action_text' => 'Enter Marks',
            ];
        }

        if ($draftMarks === 0 && $enrolmentsWithoutMarks === 0 && $publishedMarks > 0) {
            $healthy[] = [
                'category' => 'Results',
                'title' => 'All Marks Published',
                'message' => "{$publishedMarks} marks published, none outstanding.",
            ];
        }

        // ═══════════════════════════════════════════════════
        // FINANCIAL POSITION
        // ═══════════════════════════════════════════════════
        //
        // The report is read by administrators asking whether the school is in
        // good order, and money is half that answer. It was absent entirely.
        $feeTotals = DB::table('fees')
            // Paid net of overpayment credit applied to another fee, which a
            // plain SUM(paid_amount) counts twice. See Fee::netPaidSql().
            ->selectRaw('SUM(fee_amount + fine_amount - discount_amount) raised, SUM(' . \App\Models\Fee::netPaidSql('fees') . ') paid')
            ->first();

        $raised = (float) ($feeTotals->raised ?? 0);
        $paid = (float) ($feeTotals->paid ?? 0);

        $studentsOwing = DB::table('fees')
            ->whereRaw('paid_amount < (fee_amount + fine_amount - discount_amount)')
            ->distinct()->count('student_enroll_id');

        $activeBudget = DB::table('budgets')->where('is_institutional', 1)->where('status', 'active')->count();
        $unpostedPayroll = DB::table('payrolls')->where('status', 0)->count();

        $data['financial'] = [
            'fees_raised' => $raised,
            'fees_paid' => $paid,
            'outstanding' => $raised - $paid,
            'collection_percent' => $raised > 0 ? round(($paid / $raised) * 100) : 0,
            'students_owing' => $studentsOwing,
            'active_budget' => $activeBudget,
            'unposted_payroll' => $unpostedPayroll,
        ];

        if ($studentsOwing > 0) {
            $warnings[] = [
                'category' => 'Financial',
                'title' => "{$studentsOwing} Students With Outstanding Fees",
                'message' => 'Fees raised against these students are not fully paid.',
                'action_link' => route('admin.fees-student.index'),
                'action_text' => 'View Fees',
            ];
        }

        // More money received than was ever billed means instalments are missing
        // from the fee assignment, not that students overpaid. It hides real
        // arrears, because a student can be short on one instalment while the
        // total still looks settled.
        if ($paid > $raised) {
            $diagnostic_errors[] = [
                'category' => 'Financial',
                'title' => 'More Collected Than Billed',
                'message' => number_format($paid - $raised) . ' more has been received than was ever raised. Fee instalments are missing from the assignment, so arrears cannot be trusted.',
                'action_link' => route('admin.fees-master.index'),
                'action_text' => 'Check Fee Assignment',
            ];
        }

        if ($activeBudget === 0) {
            $warnings[] = [
                'category' => 'Financial',
                'title' => 'No Active Budget',
                'message' => 'No institutional budget sheet is active, so nothing is being measured against a plan.',
                'action_link' => route('admin.budget-sheet.index'),
                'action_text' => 'Open Budget Sheets',
            ];
        }

        $data['generated_at'] = now();

        // The headline number has to account for what was actually found.
        // Configuration alone scored 100% while a financial integrity error and
        // four warnings sat directly beneath it — a score that ignores the
        // findings on its own page tells the reader to ignore the findings.
        //
        // Kept separate so the view can explain the number rather than just
        // showing it: configuration is what is set up, the deductions are what
        // is currently wrong.
        $errorPenalty = count($diagnostic_errors) * 10;
        $warningPenalty = count($warnings) * 3;

        $data['score_breakdown'] = [
            'configuration' => $overallScore,
            'error_penalty' => $errorPenalty,
            'warning_penalty' => $warningPenalty,
            'errors' => count($diagnostic_errors),
            'warnings' => count($warnings),
        ];

        $data['kpis']['configuration_score'] = $overallScore;
        $data['kpis']['overall_score'] = max(0, $overallScore - $errorPenalty - $warningPenalty);

        $data['diagnostic_errors'] = $diagnostic_errors;
        $data['warnings'] = $warnings;
        $data['healthy'] = $healthy;

        return $data;
    }
}
