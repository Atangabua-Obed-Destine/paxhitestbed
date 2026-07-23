<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicDepartment;
use App\Models\AcademicStanding;
use App\Models\ClassRoutine;
use App\Models\ClassSession;
use App\Models\DegreeType;
use App\Models\Exam;
use App\Models\ExamPublishingState;
use App\Models\ExamType;
use App\Models\EnrollSubject;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\Program;
use App\Models\ResitRequest;
use App\Models\Section;
use App\Models\Semester;
use App\Models\SenateDeliberation;
use App\Models\SenateDeliberationLog;
use App\Models\SenateDeliberationProgram;
use App\Models\SenateSignature;
use App\Models\Session;
use App\Models\StudentAttendance;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Models\SubjectMarking;
use App\Exports\SenateResultsPreviewExport;
use App\Services\ResultContributionService;
use App\Services\StaffAssignmentService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SenateDeliberationController extends Controller
{
    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title  = __('Senate Deliberation');
        $this->route  = 'admin.senate-deliberation';
        $this->view   = 'admin.senate-deliberation';
        $this->path   = 'senate-deliberation';
        $this->access = 'senate-deliberation';

        $this->middleware('permission:' . $this->access . '-view', ['only' => [
            'index', 'academicStandings', 'deliberations', 'showDeliberation',
            'resultsPreview', 'studentMatrix',
        ]]);
        $this->middleware('permission:' . $this->access . '-create', ['only' => [
            'storeDeliberation', 'classify', 'programDecision', 'addSignature', 'removeSignature', 'updateDeliberation',
        ]]);
        $this->middleware('permission:' . $this->access . '-export', ['only' => [
            'exportPdf', 'exportExcel', 'exportResultsPreview',
        ]]);
    }

    /* =========================================================================
     *  DASHBOARD  —  /admin/exam/senate-deliberation
     * ========================================================================= */

    public function index(Request $request)
    {
        $data = [
            'title'  => $this->title . ' — Dashboard',
            'route'  => $this->route,
            'view'   => $this->view,
            'path'   => $this->path,
            'access' => $this->access,
        ];

        // Filters
        $data['selected_session']  = $session  = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';

        // Sessions & semesters (all active, no staff assignment filter — senate sees everything)
        $data['sessions']  = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', 1)->where('is_resit', 0)->orderBy('id', 'asc')->get();

        // If no session selected, try to default to current session
        if ($session === '0') {
            $currentSession = Session::where('current', 1)->first();
            if ($currentSession) {
                $data['selected_session'] = $session = (string) $currentSession->id;
            }
        }

        // ── Build dashboard data when both filters are set ──────────────────
        if ($session !== '0' && $semester !== '0') {
            $data = array_merge($data, $this->buildDashboardData((int)$session, (int)$semester));
        }

        return view($this->view . '.index', $data);
    }

    /**
     * Build all dashboard KPI data for a given session + semester.
     */
    private function buildDashboardData(int $sessionId, int $semesterId): array
    {
        $result = [];

        // ── 1. Enrollment stats ─────────────────────────────────────────────
        $enrollments = StudentEnroll::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where('status', 1)
            ->with(['program.faculty'])
            ->get();

        $result['total_students']  = $enrollments->count();
        $result['total_programs']  = $enrollments->pluck('program_id')->unique()->count();
        $result['total_faculties'] = $enrollments->pluck('program.faculty_id')->unique()->filter()->count();

        // ── 2. Publishing readiness ─────────────────────────────────────────
        $publishingStates = ExamPublishingState::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->select('workflow_state', DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_state')
            ->pluck('count', 'workflow_state')
            ->toArray();

        $totalCourseEntries = array_sum($publishingStates);
        $publishedCount     = $publishingStates[ExamPublishingState::STATE_PUBLISHED] ?? 0;
        $approvedCount      = $publishingStates[ExamPublishingState::STATE_APPROVED] ?? 0;
        $checkedCount       = $publishingStates[ExamPublishingState::STATE_CHECKED] ?? 0;
        $submittedCount     = $publishingStates[ExamPublishingState::STATE_SUBMITTED] ?? 0;
        $draftCount         = $publishingStates[ExamPublishingState::STATE_DRAFT] ?? 0;

        $result['publishing'] = [
            'total'     => $totalCourseEntries,
            'published' => $publishedCount,
            'approved'  => $approvedCount,
            'checked'   => $checkedCount,
            'submitted' => $submittedCount,
            'draft'     => $draftCount,
            'readiness' => $totalCourseEntries > 0
                ? round(($publishedCount / $totalCourseEntries) * 100, 1)
                : 0,
        ];

        // ── 3. Academic standings (from stored data if available) ───────────
        $standingsExist = AcademicStanding::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->exists();

        $result['standings_computed'] = $standingsExist;

        if ($standingsExist) {
            $standingCounts = AcademicStanding::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->select('standing', DB::raw('COUNT(*) as count'))
                ->groupBy('standing')
                ->pluck('count', 'standing')
                ->toArray();

            $result['standing_distribution'] = [
                'deans_list'            => $standingCounts[AcademicStanding::STANDING_DEANS_LIST] ?? 0,
                'good_standing'         => $standingCounts[AcademicStanding::STANDING_GOOD] ?? 0,
                'academic_warning'      => $standingCounts[AcademicStanding::STANDING_WARNING] ?? 0,
                'academic_probation'    => $standingCounts[AcademicStanding::STANDING_PROBATION] ?? 0,
                'recommended_dismissal' => $standingCounts[AcademicStanding::STANDING_RECOMMENDED_DISMISSAL] ?? 0,
            ];

            $gpas = AcademicStanding::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->pluck('gpa');

            $result['avg_gpa'] = $gpas->count() > 0 ? round($gpas->avg(), 2) : 0;
            $result['highest_gpa'] = $gpas->max() ?? 0;
            $result['lowest_gpa']  = $gpas->min() ?? 0;

            $totalWithStandings = $gpas->count();
            $passedCount = AcademicStanding::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->where('gpa', '>=', AcademicStanding::GPA_GOOD)
                ->count();

            $result['overall_pass_rate'] = $totalWithStandings > 0
                ? round(($passedCount / $totalWithStandings) * 100, 1)
                : 0;

            // Flagged students count (warning + probation + dismissal)
            $result['flagged_count'] = ($standingCounts[AcademicStanding::STANDING_WARNING] ?? 0)
                + ($standingCounts[AcademicStanding::STANDING_PROBATION] ?? 0)
                + ($standingCounts[AcademicStanding::STANDING_RECOMMENDED_DISMISSAL] ?? 0);

            // Top flagged students for alert panel
            $result['flagged_students'] = AcademicStanding::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->whereIn('standing', [
                    AcademicStanding::STANDING_WARNING,
                    AcademicStanding::STANDING_PROBATION,
                    AcademicStanding::STANDING_RECOMMENDED_DISMISSAL,
                ])
                ->with(['student', 'program'])
                ->orderBy('gpa', 'asc')
                ->limit(20)
                ->get();
        } else {
            $result['standing_distribution'] = [
                'deans_list' => 0, 'good_standing' => 0,
                'academic_warning' => 0, 'academic_probation' => 0,
                'recommended_dismissal' => 0,
            ];
            $result['avg_gpa'] = 0;
            $result['highest_gpa'] = 0;
            $result['lowest_gpa']  = 0;
            $result['overall_pass_rate'] = 0;
            $result['flagged_count'] = 0;
            $result['flagged_students'] = collect();
        }

        // ── 4. Faculty-level summary ────────────────────────────────────────
        $facultyStats = [];
        $programsByFaculty = $enrollments->groupBy('program.faculty_id');

        foreach ($programsByFaculty as $facultyId => $facultyEnrolls) {
            if (!$facultyId) continue;

            $faculty = $facultyEnrolls->first()->program->faculty ?? null;
            if (!$faculty) continue;

            $programIds = $facultyEnrolls->pluck('program_id')->unique()->toArray();

            $fs = [
                'id'            => $facultyId,
                'title'         => $faculty->title,
                'shortcode'     => $faculty->shortcode ?? '',
                'program_count' => count($programIds),
                'student_count' => $facultyEnrolls->count(),
                'avg_gpa'       => 0,
                'pass_rate'     => 0,
                'published'     => 0,
                'total_courses' => 0,
            ];

            // Faculty publishing readiness
            $facPublishing = ExamPublishingState::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->whereIn('program_id', $programIds)
                ->select('workflow_state', DB::raw('COUNT(*) as count'))
                ->groupBy('workflow_state')
                ->pluck('count', 'workflow_state')
                ->toArray();

            $fs['total_courses'] = array_sum($facPublishing);
            $fs['published']     = $facPublishing[ExamPublishingState::STATE_PUBLISHED] ?? 0;

            // Faculty GPA stats from standings
            if ($standingsExist) {
                $facGpas = AcademicStanding::where('session_id', $sessionId)
                    ->where('semester_id', $semesterId)
                    ->where('faculty_id', $facultyId)
                    ->pluck('gpa');

                $fs['avg_gpa'] = $facGpas->count() > 0 ? round($facGpas->avg(), 2) : 0;

                $facPassed = AcademicStanding::where('session_id', $sessionId)
                    ->where('semester_id', $semesterId)
                    ->where('faculty_id', $facultyId)
                    ->where('gpa', '>=', AcademicStanding::GPA_GOOD)
                    ->count();

                $fs['pass_rate'] = $facGpas->count() > 0
                    ? round(($facPassed / $facGpas->count()) * 100, 1)
                    : 0;
            }

            $facultyStats[$facultyId] = $fs;
        }

        $result['faculty_stats'] = collect($facultyStats)->sortBy('title');

        // ── 5. Existing deliberation for this session+semester ──────────────
        $result['deliberation'] = SenateDeliberation::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->with(['programs', 'signatures', 'creator'])
            ->latest()
            ->first();

        return $result;
    }

    /* =========================================================================
     *  ACADEMIC STANDINGS  —  /admin/exam/senate-deliberation/academic-standings
     * ========================================================================= */

    /**
     * Attach `carry_over_courses` and `carry_over_credits` to each
     * AcademicStanding row. Mutates standings in place.
     *
     * Carry-over definition (academic-standings report):
     *   - Published failing marks (<50) anywhere in the student's
     *     (student_id, program_id) history — INCLUDING the current enrollment.
     *   - Subject NOT passed in any other enrollment under the same program.
     *   - Subject NOT currently in an active resit workflow.
     */
    protected function attachCarryOverMetrics($standings): void
    {
        $standingEnrollments = $standings->pluck('enrollment')->filter()->values();
        if ($standingEnrollments->isEmpty()) {
            foreach ($standings as $s) {
                $s->carry_over_courses = 0;
                $s->carry_over_credits = 0;
            }
            return;
        }

        $studentIds = $standingEnrollments->pluck('student_id')->filter()->unique()->values();
        $programIds = $standingEnrollments->pluck('program_id')->filter()->unique()->values();

        $relatedEnrollments = StudentEnroll::whereIn('student_id', $studentIds)
            ->whereIn('program_id', $programIds)
            ->with(['subjectMarks.subject'])
            ->get()
            ->groupBy(fn($e) => $e->student_id . ':' . $e->program_id);

        $activeResitStates = [
            ResitRequest::STATE_REQUESTED,
            ResitRequest::STATE_AWAITING_PAYMENT,
            ResitRequest::STATE_FINANCE_REVIEW,
            ResitRequest::STATE_APPROVED,
            ResitRequest::STATE_SCHEDULED,
        ];

        $allEnrollIds = $relatedEnrollments->flatten(1)->pluck('id')->values();
        $resitsByPair = ResitRequest::whereIn('student_enroll_id', $allEnrollIds)
            ->whereIn('workflow_state', $activeResitStates)
            ->get()
            ->groupBy(function ($r) use ($relatedEnrollments) {
                foreach ($relatedEnrollments as $key => $group) {
                    if ($group->firstWhere('id', $r->student_enroll_id)) {
                        return $key;
                    }
                }
                return 'unknown';
            });

        foreach ($standings as $standing) {
            $enroll = $standing->enrollment;
            if (!$enroll) {
                $standing->carry_over_courses = 0;
                $standing->carry_over_credits = 0;
                continue;
            }
            $pairKey = $enroll->student_id . ':' . $enroll->program_id;
            $programEnrollments = $relatedEnrollments->get($pairKey, collect());
            $activeResitSubjectIds = $resitsByPair->get($pairKey, collect())
                ->pluck('subject_id')->flip()->all();

            $passedSubjectIds = [];
            foreach ($programEnrollments as $e) {
                foreach ($e->subjectMarks ?? [] as $mark) {
                    if (!$mark->subject) continue;
                    if ($mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) continue;
                    if (round($mark->total_marks) >= 50) {
                        $passedSubjectIds[$mark->subject_id] = true;
                    }
                }
            }

            $carryOvers = [];
            foreach ($programEnrollments as $e) {
                foreach ($e->subjectMarks ?? [] as $mark) {
                    if (!$mark->subject) continue;
                    if ($mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) continue;
                    $subjectId = $mark->subject_id;
                    $marksPer = round($mark->total_marks);
                    if ($marksPer >= 50) continue;
                    if (isset($passedSubjectIds[$subjectId])) continue;
                    if (isset($activeResitSubjectIds[$subjectId])) continue;

                    if (isset($carryOvers[$subjectId])) {
                        if ($marksPer > $carryOvers[$subjectId]['best_marks']) {
                            $carryOvers[$subjectId]['best_marks'] = $marksPer;
                        }
                        continue;
                    }
                    $carryOvers[$subjectId] = [
                        'best_marks'   => $marksPer,
                        'credit_hours' => (float) ($mark->subject->credit_hour ?? 0),
                    ];
                }
            }

            $standing->carry_over_courses = count($carryOvers);
            $standing->carry_over_credits = round(collect($carryOvers)->sum('credit_hours'), 1);
        }
    }

    public function academicStandings(Request $request)
    {
        $data = [
            'title'  => $this->title . ' — Academic Standings',
            'route'  => $this->route,
            'view'   => $this->view,
            'path'   => $this->path,
            'access' => $this->access,
        ];

        $data['selected_session']  = $session  = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_faculty']  = $faculty  = $request->faculty ?? '0';
        $data['selected_program']  = $program  = $request->program ?? '0';
        $data['selected_standing'] = $standing = $request->standing ?? 'all';

        $data['sessions']  = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', 1)->orderBy('is_resit', 'asc')->orderBy('id', 'asc')->get();

        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        if ($faculty !== '0') {
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }

        $data['standing_options'] = AcademicStanding::standingLabels();

        // ── Fetch standings ─────────────────────────────────────────────────
        if ($session !== '0' && $semester !== '0') {
            $query = AcademicStanding::where('session_id', $session)
                ->where('semester_id', $semester)
                ->with(['student', 'program.faculty', 'enrollment']);

            if ($faculty !== '0') {
                $query->where('faculty_id', $faculty);
            }
            if ($program !== '0') {
                $query->where('program_id', $program);
            }
            if ($standing !== 'all') {
                $query->where('standing', $standing);
            }

            $data['standings'] = $query->orderBy('gpa', 'desc')->paginate(50);

            // ── Attach carry-over metrics (#CO and CO Cr.) to each standing ──
            // Definition for this report: courses the student has FAILED
            // (published marks < 50) anywhere in their (student, program)
            // history — including the current enrollment — that they have
            // not since passed and that are not in an active resit workflow.
            $this->attachCarryOverMetrics(collect($data['standings']->items()));

            // Summary counts
            $countQuery = AcademicStanding::where('session_id', $session)
                ->where('semester_id', $semester);
            if ($faculty !== '0') $countQuery->where('faculty_id', $faculty);
            if ($program !== '0') $countQuery->where('program_id', $program);

            $data['standing_counts'] = $countQuery
                ->select('standing', DB::raw('COUNT(*) as count'))
                ->groupBy('standing')
                ->pluck('count', 'standing')
                ->toArray();

            $data['standings_exist'] = true;
        } else {
            $data['standings'] = null;
            $data['standing_counts'] = [];
            $data['standings_exist'] = false;
        }

        return view($this->view . '.academic-standings', $data);
    }

    /* =========================================================================
     *  CLASSIFY  —  Generate / Refresh Academic Standings
     * ========================================================================= */

    public function classify(Request $request)
    {
        $request->validate([
            'session'  => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
        ]);

        $sessionId  = (int) $request->session;
        $semesterId = (int) $request->semester;

        $grades     = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $passingMark = 50;

        // Get all enrollments for this session + semester (exclude only
        // status=0 which means inactive/withdrawn; active=1 and completed=2
        // are both valid for standings classification).
        $enrollments = StudentEnroll::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where('status', '!=', 0)
            ->with([
                'student',
                'program.faculty',
                'section',
                'exams' => function ($q) {
                    $q->with(['type', 'subject']);
                },
                'subjectMarks' => function ($q) {
                    $q->with('subject');
                },
                'subjects',
            ])
            ->get();

        // Group by unique student (keep latest enrollment per matricule within this scope)
        $uniqueEnrolls = $enrollments->groupBy('matricule')->map(function ($group) {
            return $group->sortByDesc('id')->first();
        })->values();

        $classified = 0;
        $userId = Auth::id();

        DB::beginTransaction();
        try {
            // Remove old standings for this session+semester (regenerate)
            AcademicStanding::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->delete();

            foreach ($uniqueEnrolls as $enroll) {
                $student = $enroll->student;
                if (!$student) continue;

                $gpaData = $this->computeEnrollmentGpa($enroll, $grades, $passingMark);

                // Only classify students who have at least one examined course
                if ($gpaData['has_results'] === false) continue;

                $standingValue = AcademicStanding::classifyGpa($gpaData['gpa']);

                // Check for previous standing
                $previousStanding = AcademicStanding::where('student_id', $enroll->student_id)
                    ->where('id', '!=', 0)
                    ->whereNotNull('standing')
                    ->latest()
                    ->value('standing');

                AcademicStanding::create([
                    'student_id'              => $enroll->student_id,
                    'student_enroll_id'       => $enroll->id,
                    'session_id'              => $sessionId,
                    'semester_id'             => $semesterId,
                    'program_id'              => $enroll->program_id,
                    'faculty_id'              => $enroll->program->faculty_id ?? null,
                    'gpa'                     => $gpaData['gpa'],
                    'cgpa'                    => null, // CGPA requires cross-semester computation
                    'total_credits_registered' => $gpaData['credits_registered'],
                    'total_credits_earned'    => $gpaData['credits_earned'],
                    'courses_registered'      => $gpaData['courses_registered'],
                    'courses_passed'          => $gpaData['courses_passed'],
                    'courses_failed'          => $gpaData['courses_failed'],
                    'standing'                => $standingValue,
                    'previous_standing'       => $previousStanding,
                    'classified_by'           => $userId,
                    'classified_at'           => now(),
                ]);

                $classified++;
            }

            DB::commit();

            return redirect()->back()->with('success', "Academic standings classified successfully for {$classified} students.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to classify standings: ' . $e->getMessage());
        }
    }

    /**
     * Compute GPA for a single enrollment — mirrors ResultsSummaryController logic.
     */
    private function computeEnrollmentGpa($enroll, $grades, float $passingMark = 50): array
    {
        $totalCreditsRegistered = 0;
        $totalCreditsEarned     = 0;
        $totalQualityPoints     = 0;
        $coursesPassed           = 0;
        $coursesFailed           = 0;
        $coursesRegistered      = 0;
        $hasResults             = false;

        $subjects = $enroll->subjects ?? collect();

        foreach ($subjects as $subject) {
            $creditHour = (float) ($subject->credit_hour ?? 0);

            // Check if student is registered for this subject via exams
            $subjectExams = $enroll->exams->where('subject_id', $subject->id);
            if ($subjectExams->isEmpty()) continue;

            $coursesRegistered++;
            $totalCreditsRegistered += $creditHour;

            // Get marks from SubjectMarking
            $subjectMark = $enroll->subjectMarks->where('subject_id', $subject->id)->first();

            // Only count if there's a mark record (regardless of publishing state for senate overview)
            if (!$subjectMark) continue;

            // Get contribution settings
            $subjectContributions = \App\Services\ResultContributionService::getSubjectContributions($subject->id);
            $attendanceContribution = $subjectContributions['attendance'] ?? 0;

            // Attendance marks
            $studentAttendance = \App\Models\StudentAttendance::where('student_enroll_id', $enroll->id)
                ->where('subject_id', $subject->id)
                ->get();

            $present = $studentAttendance->where('attendance', 1)->count();
            $leave   = $studentAttendance->where('attendance', 3)->count();
            $absent  = $studentAttendance->where('attendance', 2)->count();
            $totalPresent = $present + $leave;
            $totalAttCount = $totalPresent + $absent;

            $attendanceMarks = 0;
            if ($totalAttCount > 0 && $attendanceContribution > 0) {
                $attendanceMarks = ($attendanceContribution / $totalAttCount) * $totalPresent;
            }

            $storedAttendance = (float) ($subjectMark->attendances ?? 0);
            $storedAssignment = (float) ($subjectMark->assignments ?? 0);
            $storedActivity   = (float) ($subjectMark->activities ?? 0);

            $attendanceMarks = round($attendanceMarks, 2) ?: round($storedAttendance, 2);

            // Calculate CA + exam marks
            $caExamMarks    = 0;
            $finalExamMarks = 0;
            $wasExamined    = false;

            foreach ($subjectExams as $exam) {
                if ($exam->attendance == 1 && $exam->contribution > 0) {
                    $wasExamined = true;
                    if ($exam->marks > 0) {
                        $pct = ($exam->achieve_marks / $exam->marks) * 100;
                        $contributed = ($pct / 100) * $exam->contribution;

                        if ($exam->type && $exam->type->is_final) {
                            $finalExamMarks += $contributed;
                        } else {
                            $caExamMarks += $contributed;
                        }
                    }
                }
            }

            if (!$wasExamined) continue;

            $hasResults = true;

            $totalCA    = round($attendanceMarks + $storedAssignment + $storedActivity + $caExamMarks, 2);
            $examMarks  = round($finalExamMarks, 2);
            $totalMarks = round($totalCA + $examMarks, 2);

            // Grade lookup
            $gradePoint = 0;
            foreach ($grades as $grade) {
                if ($totalMarks >= $grade->min_mark && $totalMarks <= $grade->max_mark) {
                    $gradePoint = (float) $grade->point;
                    break;
                }
            }

            $passed = $totalMarks >= $passingMark;

            if ($passed) {
                $coursesPassed++;
                $totalCreditsEarned += $creditHour;
            } else {
                $coursesFailed++;
            }

            $totalQualityPoints += ($gradePoint * $creditHour);
        }

        $gpa = $totalCreditsRegistered > 0
            ? round($totalQualityPoints / $totalCreditsRegistered, 2)
            : 0;

        return [
            'gpa'                => $gpa,
            'credits_registered' => $totalCreditsRegistered,
            'credits_earned'     => $totalCreditsEarned,
            'courses_registered' => $coursesRegistered,
            'courses_passed'     => $coursesPassed,
            'courses_failed'     => $coursesFailed,
            'quality_points'     => $totalQualityPoints,
            'has_results'        => $hasResults,
        ];
    }

    /* =========================================================================
     *  DELIBERATIONS  —  /admin/exam/senate-deliberation/deliberations
     * ========================================================================= */

    public function deliberations(Request $request)
    {
        $data = [
            'title'  => $this->title . ' — Deliberation Sessions',
            'route'  => $this->route,
            'view'   => $this->view,
            'path'   => $this->path,
            'access' => $this->access,
        ];

        $data['selected_session']  = $session  = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';

        $data['sessions']  = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', 1)->where('is_resit', 0)->orderBy('id', 'asc')->get();

        $query = SenateDeliberation::with(['session', 'semester', 'creator', 'programs', 'signatures'])
            ->orderBy('created_at', 'desc');

        if ($session !== '0') {
            $query->where('session_id', $session);
        }
        if ($semester !== '0') {
            $query->where('semester_id', $semester);
        }

        $data['deliberations'] = $query->paginate(15);

        return view($this->view . '.deliberations', $data);
    }

    public function storeDeliberation(Request $request)
    {
        $request->validate([
            'session_id'  => 'required|exists:sessions,id',
            'semester_id' => 'required|exists:semesters,id',
            'meeting_date' => 'nullable|date',
            'venue'       => 'nullable|string|max:191',
            'chairperson' => 'nullable|string|max:191',
            'registrar'   => 'nullable|string|max:191',
        ]);

        $sessionId  = (int) $request->session_id;
        $semesterId = (int) $request->semester_id;

        $meetingNumber = SenateDeliberation::generateMeetingNumber($sessionId, $semesterId);

        DB::beginTransaction();
        try {
            $deliberation = SenateDeliberation::create([
                'session_id'     => $sessionId,
                'semester_id'    => $semesterId,
                'meeting_number' => $meetingNumber,
                'meeting_date'   => $request->meeting_date,
                'venue'          => $request->venue,
                'chairperson'    => $request->chairperson,
                'registrar'      => $request->registrar,
                'status'         => SenateDeliberation::STATUS_PENDING,
                'overall_decision' => SenateDeliberation::DECISION_PENDING,
                'created_by'     => Auth::id(),
            ]);

            // Auto-populate programs from enrollments
            $programIds = StudentEnroll::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->where('status', 1)
                ->distinct()
                ->pluck('program_id');

            foreach ($programIds as $programId) {
                $program = Program::find($programId);
                if (!$program) continue;

                // Get stats from academic standings if computed
                $standingStats = AcademicStanding::where('session_id', $sessionId)
                    ->where('semester_id', $semesterId)
                    ->where('program_id', $programId)
                    ->select(
                        DB::raw('COUNT(*) as total'),
                        DB::raw('SUM(CASE WHEN gpa >= 2.0 THEN 1 ELSE 0 END) as passed'),
                        DB::raw('SUM(CASE WHEN gpa < 2.0 THEN 1 ELSE 0 END) as failed'),
                        DB::raw('ROUND(AVG(gpa), 2) as avg_gpa')
                    )
                    ->first();

                $total  = $standingStats->total ?? 0;
                $passed = $standingStats->passed ?? 0;

                SenateDeliberationProgram::create([
                    'senate_deliberation_id' => $deliberation->id,
                    'program_id'             => $programId,
                    'faculty_id'             => $program->faculty_id,
                    'total_students'         => $total,
                    'total_passed'           => $passed,
                    'total_failed'           => $standingStats->failed ?? 0,
                    'average_gpa'            => $standingStats->avg_gpa ?? 0,
                    'pass_rate'              => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
                ]);
            }

            // Log the creation
            SenateDeliberationLog::create([
                'senate_deliberation_id' => $deliberation->id,
                'action'       => SenateDeliberationLog::ACTION_CREATED,
                'description'  => "Senate deliberation {$meetingNumber} created",
                'performed_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()
                ->route('admin.senate-deliberation.show', $deliberation->id)
                ->with('success', "Deliberation session {$meetingNumber} created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create deliberation: ' . $e->getMessage());
        }
    }

    /* =========================================================================
     *  SHOW DELIBERATION  —  /admin/exam/senate-deliberation/{id}
     * ========================================================================= */

    public function showDeliberation($id)
    {
        $deliberation = SenateDeliberation::with([
            'session', 'semester', 'creator', 'updater',
            'programs.program.faculty',
            'programs.reviewer',
            'signatures',
            'logs' => function ($q) {
                $q->with('performer')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // Group programs by faculty
        $programsByFaculty = $deliberation->programs
            ->sortBy('program.title')
            ->groupBy(function ($p) {
                return $p->program->faculty->title ?? 'Unknown Faculty';
            });

        // Standing summary for this session+semester
        $standingCounts = AcademicStanding::where('session_id', $deliberation->session_id)
            ->where('semester_id', $deliberation->semester_id)
            ->select('standing', DB::raw('COUNT(*) as count'))
            ->groupBy('standing')
            ->pluck('count', 'standing')
            ->toArray();

        $data = [
            'title'             => $this->title . ' — ' . $deliberation->meeting_number,
            'route'             => $this->route,
            'view'              => $this->view,
            'path'              => $this->path,
            'access'            => $this->access,
            'deliberation'      => $deliberation,
            'programsByFaculty' => $programsByFaculty,
            'standingCounts'    => $standingCounts,
            'standingLabels'    => AcademicStanding::standingLabels(),
            'decisionLabels'    => SenateDeliberation::decisionLabels(),
            'statusLabels'      => SenateDeliberation::statusLabels(),
        ];

        return view($this->view . '.show', $data);
    }

    /* =========================================================================
     *  UPDATE DELIBERATION  —  AJAX / POST
     * ========================================================================= */

    public function updateDeliberation(Request $request, $id)
    {
        $deliberation = SenateDeliberation::findOrFail($id);

        $request->validate([
            'status'           => 'nullable|in:pending,in_progress,completed,deferred',
            'overall_decision' => 'nullable|in:pending,approved,approved_with_conditions,deferred,rejected',
            'remarks'          => 'nullable|string',
            'conditions'       => 'nullable|string',
            'action_items'     => 'nullable|string',
            'meeting_date'     => 'nullable|date',
            'venue'            => 'nullable|string|max:191',
            'chairperson'      => 'nullable|string|max:191',
            'registrar'        => 'nullable|string|max:191',
        ]);

        $changes = [];

        if ($request->has('status') && $request->status !== $deliberation->status) {
            $changes[] = "Status: {$deliberation->status} → {$request->status}";
            $deliberation->status = $request->status;
        }
        if ($request->has('overall_decision') && $request->overall_decision !== $deliberation->overall_decision) {
            $changes[] = "Decision: {$deliberation->overall_decision} → {$request->overall_decision}";
            $deliberation->overall_decision = $request->overall_decision;
        }

        $deliberation->fill($request->only([
            'remarks', 'conditions', 'action_items',
            'meeting_date', 'venue', 'chairperson', 'registrar',
        ]));
        $deliberation->updated_by = Auth::id();
        $deliberation->save();

        if (!empty($changes)) {
            SenateDeliberationLog::create([
                'senate_deliberation_id' => $deliberation->id,
                'action'       => SenateDeliberationLog::ACTION_STATUS_CHANGED,
                'description'  => implode('; ', $changes),
                'performed_by' => Auth::id(),
            ]);
        }

        return redirect()->back()->with('success', 'Deliberation updated successfully.');
    }

    /* =========================================================================
     *  PROGRAM DECISION  —  Per-program review
     * ========================================================================= */

    public function programDecision(Request $request, $deliberationId)
    {
        $request->validate([
            'program_deliberation_id' => 'required|exists:senate_deliberation_programs,id',
            'decision'   => 'required|in:pending,approved,approved_with_conditions,deferred,rejected',
            'remarks'    => 'nullable|string',
            'conditions' => 'nullable|string',
            'action_items' => 'nullable|string',
        ]);

        $progDelib = SenateDeliberationProgram::where('id', $request->program_deliberation_id)
            ->where('senate_deliberation_id', $deliberationId)
            ->firstOrFail();

        $oldDecision = $progDelib->decision;

        $progDelib->update([
            'decision'     => $request->decision,
            'remarks'      => $request->remarks,
            'conditions'   => $request->conditions,
            'action_items' => $request->action_items,
            'reviewed_by'  => Auth::id(),
            'reviewed_at'  => now(),
        ]);

        SenateDeliberationLog::create([
            'senate_deliberation_id' => $deliberationId,
            'action'       => SenateDeliberationLog::ACTION_PROGRAM_REVIEWED,
            'target_type'  => 'program',
            'target_id'    => $progDelib->program_id,
            'description'  => "Program '{$progDelib->program->title}' decision: {$oldDecision} → {$request->decision}",
            'performed_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', "Decision updated for {$progDelib->program->title}.");
    }

    /* =========================================================================
     *  SIGNATURES
     * ========================================================================= */

    public function addSignature(Request $request, $deliberationId)
    {
        $request->validate([
            'signatory_name'     => 'required|string|max:191',
            'signatory_position' => 'required|string|max:191',
        ]);

        $deliberation = SenateDeliberation::findOrFail($deliberationId);

        $sig = SenateSignature::create([
            'senate_deliberation_id' => $deliberation->id,
            'signatory_name'         => $request->signatory_name,
            'signatory_position'     => $request->signatory_position,
            'signed_at'              => now(),
        ]);

        SenateDeliberationLog::create([
            'senate_deliberation_id' => $deliberation->id,
            'action'       => SenateDeliberationLog::ACTION_SIGNATURE_ADDED,
            'target_type'  => 'signature',
            'target_id'    => $sig->id,
            'description'  => "Signature added: {$request->signatory_name} ({$request->signatory_position})",
            'performed_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Signature added successfully.');
    }

    public function removeSignature($signatureId)
    {
        $sig = SenateSignature::findOrFail($signatureId);
        $deliberationId = $sig->senate_deliberation_id;

        SenateDeliberationLog::create([
            'senate_deliberation_id' => $deliberationId,
            'action'       => SenateDeliberationLog::ACTION_SIGNATURE_ADDED,
            'description'  => "Signature removed: {$sig->signatory_name} ({$sig->signatory_position})",
            'performed_by' => Auth::id(),
        ]);

        $sig->delete();

        return redirect()->back()->with('success', 'Signature removed.');
    }

    /* =========================================================================
     *  RESULTS PREVIEW  —  Comprehensive Senate Results Review
     *  /admin/exam/senate-deliberation/results-preview
     * ========================================================================= */

    public function resultsPreview(Request $request)
    {
        $data = [
            'title'  => $this->title . ' — Comprehensive Results Preview',
            'route'  => $this->route,
            'view'   => $this->view,
            'path'   => $this->path,
            'access' => $this->access,
        ];

        // Filters
        $data['selected_session']  = $session  = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_faculty']  = $selFaculty = $request->faculty ?? '0';

        $data['sessions']  = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', 1)->orderBy('is_resit', 'asc')->orderBy('id', 'asc')->get();
        $data['faculties'] = Faculty::where('status', '1')->orderBy('title', 'asc')->get();
        $data['grades']    = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Default to current session
        if ($session === '0') {
            $currentSession = Session::where('current', 1)->first();
            if ($currentSession) {
                $data['selected_session'] = $session = (string) $currentSession->id;
            }
        }

        // Build data when both session + semester set
        if ($session !== '0' && $semester !== '0') {
            $sessionId  = (int) $session;
            $semesterId = (int) $semester;
            $facultyId  = $selFaculty !== '0' ? (int) $selFaculty : null;

            // ── 1. Institution KPIs ─────────────────────────────────────────
            $data['kpis'] = $this->buildResultsKPIs($sessionId, $semesterId, $facultyId);

            // ── 2. Faculty Performance Summary ──────────────────────────────
            $data['faculty_summaries'] = $this->buildFacultySummaries($sessionId, $semesterId, $facultyId);

            // ── 3. Department + Course Results (grouped by faculty) ─────────
            $data['faculty_course_data'] = $this->buildCourseResultsByFaculty($sessionId, $semesterId, $facultyId);

            // ── 4. Lecturer Performance Index ───────────────────────────────
            $data['lecturer_performance'] = $this->buildLecturerPerformance($sessionId, $semesterId, $facultyId);

            // ── 5. Publishing Readiness Summary ─────────────────────────────
            $data['publishing_summary'] = $this->buildPublishingSummary($sessionId, $semesterId, $facultyId);

            // ── 6. Academic Standing Distribution (if computed) ─────────────
            $data['standings_computed'] = AcademicStanding::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->exists();

            if ($data['standings_computed']) {
                $stQuery = AcademicStanding::where('session_id', $sessionId)
                    ->where('semester_id', $semesterId);
                if ($facultyId) $stQuery->where('faculty_id', $facultyId);

                $data['standing_distribution'] = $stQuery
                    ->select('standing', DB::raw('COUNT(*) as count'))
                    ->groupBy('standing')
                    ->pluck('count', 'standing')
                    ->toArray();
            } else {
                $data['standing_distribution'] = [];
            }

            // ── 7. Top/Bottom performing courses ────────────────────────────
            $data['top_courses']    = $this->getPerformingCourses($sessionId, $semesterId, $facultyId, 'best', 10);
            $data['bottom_courses'] = $this->getPerformingCourses($sessionId, $semesterId, $facultyId, 'worst', 10);

            // ── 8. Programs list for student matrix loading ─────────────────
            $progQuery = Program::where('status', '1')
                ->whereHas('studentEnrolls', function ($q) use ($sessionId, $semesterId) {
                    $q->where('session_id', $sessionId)
                      ->where('semester_id', $semesterId)
                                            ->whereIn('status', [1, 2]);
                })
                ->with(['faculty', 'academicDepartment', 'degreeType']);

            if ($facultyId) {
                $progQuery->where('faculty_id', $facultyId);
            }

            $data['programs_list'] = $progQuery->orderBy('faculty_id')->orderBy('title')->get();

            // ── 9. Build full student result matrices grouped by faculty ────
            $data['faculty_student_matrices'] = $this->buildStudentMatrixByFaculty(
                $sessionId, $semesterId, $facultyId, $data['grades']
            );

            // ── 10. Build student performance summary for screenshot table ──
            $data['student_performance_summaries'] = $this->buildStudentPerformanceSummaries(
                $data['faculty_student_matrices'],
                $data['faculty_summaries']
            );

            // Context labels
            $data['session_label']  = Session::find($sessionId)->title ?? '';
            $data['semester_label'] = Semester::find($semesterId)->title ?? '';
            $data['institution_name'] = config('app.name', 'Institution');
        }

        $data['standingLabels'] = AcademicStanding::standingLabels();

        return view($this->view . '.results-preview', $data);
    }

    /**
     * AJAX: Load student results matrix for a specific program.
     */
    public function studentMatrix(Request $request)
    {
        $request->validate([
            'session'  => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
            'program'  => 'required|exists:programs,id',
        ]);

        $sessionId  = (int) $request->session;
        $semesterId = (int) $request->semester;
        $programId  = (int) $request->program;

        $grades     = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $passingMark = 50;

        // Get enrollments
        $enrollments = StudentEnroll::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where('program_id', $programId)
            ->whereIn('status', [1, 2])
            ->with(['student', 'exams.type', 'exams.subject', 'subjectMarks.subject', 'subjects', 'section'])
            ->get();

        // Show semester-configured subjects first, then only extra subjects actually registered by this cohort.
        $subjects = $this->getMatrixSubjectsForProgram($programId, $semesterId, $enrollments);

        // Deduplicate by matricule (keep latest)
        $uniqueEnrolls = $enrollments->groupBy('matricule')->map(fn($g) => $g->sortByDesc('id')->first())->values();
        $progressionMetrics = $this->buildStudentProgressionSummaryMetrics($uniqueEnrolls);
        $courseDecisionMetrics = $this->buildCourseDecisionMetrics($uniqueEnrolls);

        $studentResults = [];

        foreach ($uniqueEnrolls as $enroll) {
            $student = $enroll->student;
            if (!$student) continue;

            $row = [
                'sn'        => count($studentResults) + 1,
                'matricule' => $enroll->matricule ?? $student->student_id ?? 'N/A',
                'name'      => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'section'   => $enroll->section->title ?? '-',
                'courses'   => [],
                'summary'   => [
                    'total_credits_registered' => 0,
                    'total_credits_earned'     => 0,
                    'total_quality_points'     => 0,
                    'courses_passed'           => 0,
                    'courses_failed'           => 0,
                    'courses_registered'       => 0,
                    'scheduled_resit_courses'  => 0,
                    'scheduled_resit_credits'  => 0,
                    'carry_over_courses'       => 0,
                    'carry_over_credits'       => 0,
                    'gpa'                      => 0,
                ],
            ];

            $enrollSubjects = $enroll->subjects ?? collect();

            foreach ($subjects as $subject) {
                $isRegistered = $enrollSubjects->contains('id', $subject->id);

                if (!$isRegistered) {
                    $row['courses'][$subject->id] = [
                        'registered' => false,
                        'ca_marks'   => '-', 'exam_marks' => '-', 'total_marks' => '-',
                        'grade'      => '-', 'status' => 'NR', 'grade_point' => 0, 'credit_value' => 0,
                        'decision_label' => null, 'decision_code' => null, 'decision_class' => null,
                    ];
                    continue;
                }

                $row['summary']['courses_registered']++;
                $creditHour = (float) ($subject->credit_hour ?? 0);
                $row['summary']['total_credits_registered'] += $creditHour;

                $subjectExams = $enroll->exams->where('subject_id', $subject->id);
                $subjectMark  = $enroll->subjectMarks->where('subject_id', $subject->id)->first();

                if ($subjectExams->isEmpty() || !$subjectMark) {
                    $row['courses'][$subject->id] = [
                        'registered' => true,
                        'ca_marks'   => '-', 'exam_marks' => '-', 'total_marks' => '-',
                        'grade'      => '-', 'status' => 'NP', 'grade_point' => 0, 'credit_value' => $creditHour,
                        'decision_label' => null, 'decision_code' => null, 'decision_class' => null,
                    ];
                    continue;
                }

                // Compute marks using same logic as computeEnrollmentGpa
                $subjectContributions = ResultContributionService::getSubjectContributions($subject->id);
                $attendanceContribution = $subjectContributions['attendance'] ?? 0;

                $studentAttendance = StudentAttendance::where('student_enroll_id', $enroll->id)
                    ->where('subject_id', $subject->id)->get();

                $present = $studentAttendance->where('attendance', 1)->count();
                $leave   = $studentAttendance->where('attendance', 3)->count();
                $absent  = $studentAttendance->where('attendance', 2)->count();
                $totalPresent  = $present + $leave;
                $totalAttCount = $totalPresent + $absent;

                $attendanceMarks = 0;
                if ($totalAttCount > 0 && $attendanceContribution > 0) {
                    $attendanceMarks = ($attendanceContribution / $totalAttCount) * $totalPresent;
                }

                $storedAttendance = (float) ($subjectMark->attendances ?? 0);
                $storedAssignment = (float) ($subjectMark->assignments ?? 0);
                $storedActivity   = (float) ($subjectMark->activities ?? 0);
                $attendanceMarks = round($attendanceMarks, 2) ?: round($storedAttendance, 2);

                $caExamMarks = 0;
                $finalExamMarks = 0;
                $wasExamined = false;
                $hasMarksSubmitted = false;
                $hasCaMarks = false;
                $hasFinalMarks = false;
                $wasConfirmedAbsent = false;
                $caConfirmedAbsent = false;
                $finalConfirmedAbsent = false;

                foreach ($subjectExams as $exam) {
                    $isFinal = $exam->type && $exam->type->is_final;

                    if ($exam->attendance == 1) {
                        $wasExamined = true;

                        if ($exam->achieve_marks !== null) {
                            $hasMarksSubmitted = true;
                            if ($isFinal) $hasFinalMarks = true;
                            else $hasCaMarks = true;
                        }

                        if ($exam->contribution > 0 && $exam->marks > 0) {
                            $pct = ($exam->achieve_marks / $exam->marks) * 100;
                            $contributed = ($pct / 100) * $exam->contribution;
                            if ($isFinal) {
                                $finalExamMarks += $contributed;
                            } else {
                                $caExamMarks += $contributed;
                            }
                        }
                    } elseif ($exam->attendance == 2) {
                        if ($exam->marks_locked == 1 || $exam->attendance_locked == 1) {
                            $wasConfirmedAbsent = true;
                            if ($isFinal) $finalConfirmedAbsent = true;
                            else $caConfirmedAbsent = true;
                        }
                    }
                }

                $hasStoredCaData = ($storedAssignment > 0 || $storedActivity > 0 || $storedAttendance > 0);
                if ($hasStoredCaData) {
                    $hasMarksSubmitted = true;
                    $hasCaMarks = true;
                    if (!$wasExamined) $wasExamined = true;
                }

                if (!$wasExamined) {
                    $isAbsent = $wasConfirmedAbsent
                        || ($subjectMark->marks_locked ?? false)
                        || ($subjectMark->attendance_locked ?? false);
                    $status = $isAbsent ? 'ABS' : 'N/S';
                    $row['courses'][$subject->id] = [
                        'registered' => true,
                        'ca_marks'   => $status === 'ABS' ? '-' : 'N/S',
                        'exam_marks' => $status === 'ABS' ? '-' : 'N/S',
                        'total_marks' => $status === 'ABS' ? '-' : 'N/S',
                        'grade'      => $status, 'status' => $status,
                        'grade_point' => 0, 'credit_value' => $creditHour,
                        'decision_label' => null, 'decision_code' => null, 'decision_class' => null,
                    ];
                    continue;
                }

                if (!$hasMarksSubmitted) {
                    $isAbsent = $wasConfirmedAbsent
                        || ($subjectMark->marks_locked ?? false)
                        || ($subjectMark->attendance_locked ?? false);
                    $status = $isAbsent ? 'ABS' : 'N/S';
                    $row['courses'][$subject->id] = [
                        'registered' => true,
                        'ca_marks'   => $status === 'ABS' ? '-' : 'N/S',
                        'exam_marks' => $status === 'ABS' ? '-' : 'N/S',
                        'total_marks' => $status === 'ABS' ? '-' : 'N/S',
                        'grade'      => $status, 'status' => $status,
                        'grade_point' => 0, 'credit_value' => $creditHour,
                        'decision_label' => null, 'decision_code' => null, 'decision_class' => null,
                    ];
                    continue;
                }

                $totalCA    = round($attendanceMarks + $storedAssignment + $storedActivity + $caExamMarks, 2);
                $examMarks  = round($finalExamMarks, 2);
                $totalMarks = round($totalCA + $examMarks, 2);

                // Grade lookup
                $gradeTitle = '-';
                $gradePoint = 0;
                foreach ($grades as $grade) {
                    if ($totalMarks >= $grade->min_mark && $totalMarks <= $grade->max_mark) {
                        $gradeTitle = $grade->title;
                        $gradePoint = (float) $grade->point;
                        break;
                    }
                }

                $passed = $totalMarks >= $passingMark;
                $status = $passed ? 'P' : 'F';

                if ($passed) {
                    $row['summary']['courses_passed']++;
                    $row['summary']['total_credits_earned'] += $creditHour;
                } else {
                    $row['summary']['courses_failed']++;
                }

                $row['summary']['total_quality_points'] += ($gradePoint * $creditHour);

                // Build display values with partial absence handling
                $caDisplay = round($totalCA - $attendanceMarks, 2);
                $examDisplay = $examMarks;
                if ($caConfirmedAbsent && !$hasCaMarks) {
                    $caDisplay = 'ABS';
                }
                if ($finalConfirmedAbsent && !$hasFinalMarks) {
                    $examDisplay = 'ABS';
                }

                $row['courses'][$subject->id] = [
                    'registered'  => true,
                    'ca_marks'    => $caDisplay,
                    'exam_marks'  => $examDisplay,
                    'total_marks' => $totalMarks,
                    'grade'       => $gradeTitle,
                    'status'      => $status,
                    'grade_point' => $gradePoint,
                    'credit_value' => $creditHour,
                    'decision_label' => $courseDecisionMetrics[$enroll->id][$subject->id]['label'] ?? null,
                    'decision_code' => $courseDecisionMetrics[$enroll->id][$subject->id]['code'] ?? null,
                    'decision_class' => $courseDecisionMetrics[$enroll->id][$subject->id]['class'] ?? null,
                ];
            }

            // Compute GPA
            $row['summary']['gpa'] = $row['summary']['total_credits_registered'] > 0
                ? round($row['summary']['total_quality_points'] / $row['summary']['total_credits_registered'], 2)
                : 0;

            $row['summary'] = array_merge(
                $row['summary'],
                $progressionMetrics[$enroll->id] ?? []
            );

            $courseDecisions = $courseDecisionMetrics[$enroll->id] ?? [];
            $row['summary']['carry_over_courses'] = collect($courseDecisions)
                ->where('code', 'CO')
                ->count();
            $row['summary']['carry_over_credits'] = round(
                collect($enrollSubjects)->filter(function ($subject) use ($courseDecisions) {
                    return ($courseDecisions[$subject->id]['code'] ?? null) === 'CO';
                })->sum(fn($subject) => (float) ($subject->credit_hour ?? 0)),
                1
            );

            $studentResults[] = $row;
        }

        // Course stats
        $courseStats = [];
        foreach ($subjects as $subject) {
            $cs = ['code' => $subject->code, 'title' => $subject->title, 'credit' => $subject->credit_hour,
                    'registered' => 0, 'examined' => 0, 'passed' => 0, 'failed' => 0,
                    'total_marks' => 0, 'grade_distribution' => []];
            foreach ($studentResults as $sr) {
                $c = $sr['courses'][$subject->id] ?? null;
                if (!$c || !$c['registered']) continue;
                $cs['registered']++;
                if (in_array($c['status'], ['P', 'F'])) {
                    $cs['examined']++;
                    $cs['total_marks'] += $c['total_marks'];
                    if ($c['status'] === 'P') $cs['passed']++;
                    if ($c['status'] === 'F') $cs['failed']++;
                    $g = $c['grade'];
                    $cs['grade_distribution'][$g] = ($cs['grade_distribution'][$g] ?? 0) + 1;
                }
            }
            $cs['average']   = $cs['examined'] > 0 ? round($cs['total_marks'] / $cs['examined'], 2) : 0;
            $cs['pass_rate'] = $cs['examined'] > 0 ? round(($cs['passed'] / $cs['examined']) * 100, 1) : 0;
            $courseStats[$subject->id] = $cs;
        }

        // Overall stats
        $totalStudents = count($studentResults);
        $totalPassed   = collect($studentResults)->filter(fn($r) => $r['summary']['courses_failed'] == 0 && $r['summary']['courses_registered'] > 0 && $r['summary']['courses_passed'] > 0)->count();
        $totalFailed   = collect($studentResults)->filter(fn($r) => $r['summary']['courses_failed'] > 0)->count();

        $program  = Program::with(['faculty', 'academicDepartment', 'degreeType'])->find($programId);
        $lectInfo = $this->getSubjectLecturers($subjects->pluck('id')->toArray(), $sessionId, $semesterId, $programId);

        return response()->json([
            'success'         => true,
            'student_results' => $studentResults,
            'subjects'        => $subjects->map(fn($s) => [
                'id' => $s->id, 'code' => $s->code, 'title' => $s->title,
                'credit_hour' => $s->credit_hour, 'lecturer' => $lectInfo[$s->id] ?? '-',
            ]),
            'course_stats'    => $courseStats,
            'overall_stats'   => [
                'total_students' => $totalStudents,
                'total_passed'   => $totalPassed,
                'total_failed'   => $totalFailed,
                'total_pending'  => $totalStudents - $totalPassed - $totalFailed,
            ],
            'program' => [
                'title'      => $program->title ?? '',
                'shortcode'  => $program->shortcode ?? '',
                'faculty'    => $program->faculty->title ?? '',
                'department' => $program->academicDepartment->title ?? '',
                'degree'     => $program->degreeType->title ?? '',
            ],
            'grades' => Grade::where('status', '1')->orderBy('min_mark', 'desc')
                ->get()->map(fn($g) => ['title' => $g->title, 'point' => $g->point]),
        ]);
    }

    /**
     * Build per-course progression labels for each enrollment in the matrix scope.
     */
    private function buildCourseDecisionMetrics(Collection $enrollments): array
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
                    if (!$mark->subject || $mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
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
                if (!$mark->subject || $mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
                    continue;
                }

                $subjectId = $mark->subject_id;
                $marksPer = round($mark->total_marks);
                $request = $enrollmentRequests->get($subjectId);

                if ($marksPer >= 50 || isset($laterPassedSubjects[$subjectId])) {
                    $subjectMetrics[$subjectId] = [
                        'label' => 'Validated',
                        'code' => 'VAL',
                        'class' => 'success',
                    ];
                    continue;
                }

                if ($request && $request->workflow_state === ResitRequest::STATE_SCHEDULED) {
                    $subjectMetrics[$subjectId] = [
                        'label' => 'Scheduled Resit',
                        'code' => 'RES',
                        'class' => 'primary',
                    ];
                    continue;
                }

                if ($request && in_array($request->workflow_state, [
                    ResitRequest::STATE_REQUESTED,
                    ResitRequest::STATE_AWAITING_PAYMENT,
                    ResitRequest::STATE_FINANCE_REVIEW,
                    ResitRequest::STATE_APPROVED,
                ], true)) {
                    $subjectMetrics[$subjectId] = [
                        'label' => 'Pending Resit Decision',
                        'code' => 'PND',
                        'class' => 'warning',
                    ];
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
                    $subjectMetrics[$subjectId] = [
                        'label' => 'Carry Over',
                        'code' => 'CO',
                        'class' => 'dark',
                    ];
                    continue;
                }

                $subjectMetrics[$subjectId] = [
                    'label' => 'Pending Resit Decision',
                    'code' => 'PND',
                    'class' => 'warning',
                ];
            }

            $metrics[$currentEnrollment->id] = $subjectMetrics;
        }

        return $metrics;
    }

    /**
     * Get matrix subjects for a program/semester using semester offerings plus actual registered extras.
     */
    private function getMatrixSubjectsForProgram(int $programId, int $semesterId, Collection $enrollments): Collection
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
     * Build per-enrollment summary metrics for scheduled resits and carry-over courses.
     */
    private function buildStudentProgressionSummaryMetrics(Collection $enrollments): array
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
            ->filter(function ($enroll) use ($pairKeys) {
                return $pairKeys->has($enroll->student_id . ':' . $enroll->program_id);
            })
            ->values();

        if ($relatedEnrollments->isEmpty()) {
            return [];
        }

        $enrollmentMap = $relatedEnrollments->keyBy('id');
        $enrollmentsByPair = $relatedEnrollments->groupBy(fn($enroll) => $enroll->student_id . ':' . $enroll->program_id);

        $resitRequestsByPair = ResitRequest::whereIn('student_enroll_id', $relatedEnrollments->pluck('id')->values())
            ->with(['subject:id,credit_hour'])
            ->get()
            ->groupBy(function ($request) use ($enrollmentMap) {
                $sourceEnrollment = $enrollmentMap->get($request->student_enroll_id);

                return $sourceEnrollment
                    ? $sourceEnrollment->student_id . ':' . $sourceEnrollment->program_id
                    : 'unknown';
            });

        $activeResitStates = [
            ResitRequest::STATE_REQUESTED,
            ResitRequest::STATE_AWAITING_PAYMENT,
            ResitRequest::STATE_FINANCE_REVIEW,
            ResitRequest::STATE_APPROVED,
            ResitRequest::STATE_SCHEDULED,
        ];

        $metrics = [];

        foreach ($enrollments as $currentEnrollment) {
            $pairKey = $currentEnrollment->student_id . ':' . $currentEnrollment->program_id;
            $programEnrollments = $enrollmentsByPair->get($pairKey, collect());
            $pairResitRequests = $resitRequestsByPair->get($pairKey, collect());

            $passedSubjectIds = [];
            foreach ($programEnrollments as $programEnrollment) {
                foreach ($programEnrollment->subjectMarks ?? [] as $mark) {
                    if (!$mark->subject || $mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
                        continue;
                    }

                    if (round($mark->total_marks) >= 50) {
                        $passedSubjectIds[$mark->subject_id] = true;
                    }
                }
            }

            $activeResitSubjectIds = $pairResitRequests
                ->whereIn('workflow_state', $activeResitStates)
                ->pluck('subject_id')
                ->flip()
                ->all();

            $carryOvers = [];
            foreach ($programEnrollments as $programEnrollment) {
                if ($programEnrollment->id === $currentEnrollment->id) {
                    continue;
                }

                foreach ($programEnrollment->subjectMarks ?? [] as $mark) {
                    if (!$mark->subject || $mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
                        continue;
                    }

                    $subjectId = $mark->subject_id;
                    $marksPer = round($mark->total_marks);

                    if ($marksPer >= 50 || isset($passedSubjectIds[$subjectId]) || isset($activeResitSubjectIds[$subjectId])) {
                        continue;
                    }

                    if (isset($carryOvers[$subjectId])) {
                        if ($marksPer > $carryOvers[$subjectId]['best_marks']) {
                            $carryOvers[$subjectId]['best_marks'] = $marksPer;
                        }
                        continue;
                    }

                    $carryOvers[$subjectId] = [
                        'best_marks' => $marksPer,
                        'credit_hours' => (float) ($mark->subject->credit_hour ?? 0),
                    ];
                }
            }

            $scheduledResits = $pairResitRequests
                ->where('student_enroll_id', $currentEnrollment->id)
                ->where('workflow_state', ResitRequest::STATE_SCHEDULED)
                ->unique('subject_id')
                ->values();

            $metrics[$currentEnrollment->id] = [
                'scheduled_resit_courses' => $scheduledResits->count(),
                'scheduled_resit_credits' => round($scheduledResits->sum(fn($request) => (float) ($request->subject->credit_hour ?? 0)), 1),
                'carry_over_courses' => count($carryOvers),
                'carry_over_credits' => round(collect($carryOvers)->sum('credit_hours'), 1),
            ];
        }

        return $metrics;
    }
    /* ─────────────────────────────────────────────────────────────────────────
     *  PRIVATE HELPERS — Results Preview Data Builders
     * ───────────────────────────────────────────────────────────────────────── */

    /**
     * Build complete student result matrices grouped by Faculty → Program.
     * Mirrors the exam-publishing draft preview format with Att, CA, EX, TOT, Grd per course.
     */
    private function buildStudentMatrixByFaculty(int $sessionId, int $semesterId, ?int $facultyId, $grades): array
    {
        $passingMark = 50;

        // Get all active programs that have enrolments this period
        $programs = Program::where('status', '1')
            ->when($facultyId, fn($q) => $q->where('faculty_id', $facultyId))
            ->whereHas('studentEnrolls', function ($q) use ($sessionId, $semesterId) {
                $q->where('session_id', $sessionId)->where('semester_id', $semesterId)->whereIn('status', [1, 2]);
            })
            ->with(['faculty', 'academicDepartment', 'degreeType'])
            ->orderBy('faculty_id')->orderBy('title')
            ->get();

        // Group by faculty
        $faculties = Faculty::where('status', '1')
            ->when($facultyId, fn($q) => $q->where('id', $facultyId))
            ->orderBy('title')->get();

        $result = [];

        foreach ($faculties as $fac) {
            $facPrograms = $programs->where('faculty_id', $fac->id);
            if ($facPrograms->isEmpty()) continue;

            $facData = [
                'id'        => $fac->id,
                'name'      => $fac->title,
                'shortcode' => $fac->shortcode ?? '',
                'programs'  => [],
            ];

            foreach ($facPrograms as $program) {
                $programId = $program->id;

                // Get enrollments
                $enrollments = StudentEnroll::where('session_id', $sessionId)
                    ->where('semester_id', $semesterId)
                    ->where('program_id', $programId)
                    ->whereIn('status', [1, 2])
                    ->with(['student', 'exams.type', 'exams.subject', 'subjectMarks.subject', 'subjects', 'section'])
                    ->get();

                $subjects = $this->getMatrixSubjectsForProgram($programId, $semesterId, $enrollments);

                if ($subjects->isEmpty()) continue;

                // Deduplicate by matricule
                $uniqueEnrolls = $enrollments->groupBy('matricule')
                    ->map(fn($g) => $g->sortByDesc('id')->first())->values();
                $progressionMetrics = $this->buildStudentProgressionSummaryMetrics($uniqueEnrolls);
                $courseDecisionMetrics = $this->buildCourseDecisionMetrics($uniqueEnrolls);

                if ($uniqueEnrolls->isEmpty()) continue;

                // Get lecturers for all subjects
                $lecturerMap = $this->getSubjectLecturers(
                    $subjects->pluck('id')->toArray(), $sessionId, $semesterId, $programId
                );

                $studentResults = [];

                foreach ($uniqueEnrolls as $enroll) {
                    $student = $enroll->student;
                    if (!$student) continue;

                    $row = [
                        'sn'        => count($studentResults) + 1,
                        'matricule' => $enroll->matricule ?? $student->student_id ?? 'N/A',
                        'name'      => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                        'section'   => $enroll->section->title ?? '-',
                        'courses'   => [],
                        'summary'   => [
                            'total_credits_registered' => 0,
                            'total_credits_earned'     => 0,
                            'total_quality_points'     => 0,
                            'courses_passed'           => 0,
                            'courses_failed'           => 0,
                            'courses_registered'       => 0,
                            'scheduled_resit_courses'  => 0,
                            'scheduled_resit_credits'  => 0,
                            'carry_over_courses'       => 0,
                            'carry_over_credits'       => 0,
                            'gpa'                      => 0,
                        ],
                    ];

                    $enrollSubjects = $enroll->subjects ?? collect();

                    foreach ($subjects as $subject) {
                        $isRegistered = $enrollSubjects->contains('id', $subject->id);

                        if (!$isRegistered) {
                            $row['courses'][$subject->id] = [
                                'registered' => false, 'attendance_marks' => '-',
                                'ca_marks' => '-', 'exam_marks' => '-', 'total_marks' => '-',
                                'grade' => '-', 'status' => 'NR', 'grade_point' => 0, 'credit_value' => 0,
                                'is_absent' => false, 'ca_absent' => false, 'final_absent' => false,
                                'is_not_submitted' => false, 'has_zero_contribution' => false,
                                'decision_label' => null, 'decision_code' => null, 'decision_class' => null,
                            ];
                            continue;
                        }

                        $row['summary']['courses_registered']++;
                        $creditHour = (float) ($subject->credit_hour ?? 0);
                        $row['summary']['total_credits_registered'] += $creditHour;

                        $subjectExams = $enroll->exams->where('subject_id', $subject->id);
                        $subjectMark  = $enroll->subjectMarks->where('subject_id', $subject->id)->first();

                        // Check for absent / not submitted
                        if ($subjectExams->isEmpty() || !$subjectMark) {
                            // Determine if absent vs not submitted
                            $isAbsent = false;
                            if ($subjectMark && ($subjectMark->marks_locked ?? false)) {
                                $isAbsent = true;
                            }
                            $status = $isAbsent ? 'ABS' : 'N/S';
                            $row['courses'][$subject->id] = [
                                'registered' => true, 'attendance_marks' => $status === 'ABS' ? '-' : 'N/S',
                                'ca_marks' => $status === 'ABS' ? '-' : 'N/S',
                                'exam_marks' => $status === 'ABS' ? '-' : 'N/S',
                                'total_marks' => $status === 'ABS' ? '-' : 'N/S',
                                'grade' => $status, 'status' => $status,
                                'grade_point' => 0, 'credit_value' => $creditHour,
                                'is_absent' => $isAbsent, 'ca_absent' => false, 'final_absent' => false,
                                'is_not_submitted' => ($status === 'N/S'),
                                'has_zero_contribution' => false,
                                'decision_label' => null, 'decision_code' => null, 'decision_class' => null,
                            ];
                            continue;
                        }

                        // Compute marks
                        $subjectContributions = ResultContributionService::getSubjectContributions($subject->id);
                        $attendanceContribution = $subjectContributions['attendance'] ?? 0;
                        $hasZeroContribution = !$subjectContributions['configured'];

                        $studentAttendanceRecords = StudentAttendance::where('student_enroll_id', $enroll->id)
                            ->where('subject_id', $subject->id)->get();

                        $present = $studentAttendanceRecords->where('attendance', 1)->count();
                        $leave   = $studentAttendanceRecords->where('attendance', 3)->count();
                        $absent  = $studentAttendanceRecords->where('attendance', 2)->count();
                        $totalPresent  = $present + $leave;
                        $totalAttCount = $totalPresent + $absent;

                        $attendanceMarks = 0;
                        if ($totalAttCount > 0 && $attendanceContribution > 0) {
                            $attendanceMarks = ($attendanceContribution / $totalAttCount) * $totalPresent;
                        }
                        $storedAttendance = (float) ($subjectMark->attendances ?? 0);
                        $storedAssignment = (float) ($subjectMark->assignments ?? 0);
                        $storedActivity   = (float) ($subjectMark->activities ?? 0);
                        $attendanceMarks  = round($attendanceMarks, 2) ?: round($storedAttendance, 2);

                        $caExamMarks = 0;
                        $finalExamMarks = 0;
                        $wasExamined = false;
                        $hasMarksSubmitted = false;
                        $hasCaMarks = false;
                        $hasFinalMarks = false;
                        $caAbsent = true;
                        $finalAbsent = true;
                        $wasConfirmedAbsent = false;
                        $caConfirmedAbsent = false;
                        $finalConfirmedAbsent = false;

                        foreach ($subjectExams as $exam) {
                            $isFinal = $exam->type && $exam->type->is_final;

                            if ($exam->attendance == 1) {
                                if ($isFinal) $finalAbsent = false;
                                else $caAbsent = false;

                                $wasExamined = true;

                                if ($exam->achieve_marks !== null) {
                                    $hasMarksSubmitted = true;
                                    if ($isFinal) $hasFinalMarks = true;
                                    else $hasCaMarks = true;
                                }

                                if ($exam->contribution > 0 && $exam->marks > 0) {
                                    $pct = ($exam->achieve_marks / $exam->marks) * 100;
                                    $contributed = ($pct / 100) * $exam->contribution;
                                    if ($isFinal) {
                                        $finalExamMarks += $contributed;
                                    } else {
                                        $caExamMarks += $contributed;
                                    }
                                }
                            } elseif ($exam->attendance == 2) {
                                // attendance defaults to 2, so only trust it as "genuinely absent"
                                // when the lecturer has finalized (marks_locked=1 or attendance_locked=1)
                                if ($exam->marks_locked == 1 || $exam->attendance_locked == 1) {
                                    $wasConfirmedAbsent = true;
                                    if ($isFinal) {
                                        $finalConfirmedAbsent = true;
                                    } else {
                                        $caConfirmedAbsent = true;
                                    }
                                }
                            }
                        }

                        // If has no final exam types at all, finalAbsent stays false
                        $hasFinalExamType = $subjectExams->contains(fn($e) => $e->type && $e->type->is_final);
                        if (!$hasFinalExamType) { $finalAbsent = false; $finalConfirmedAbsent = false; }
                        $hasCaExamType = $subjectExams->contains(fn($e) => $e->type && !($e->type->is_final ?? false));
                        if (!$hasCaExamType) { $caAbsent = false; $caConfirmedAbsent = false; }

                        // Also check subject_markings for stored CA data
                        $hasStoredCaData = ($storedAssignment > 0 || $storedActivity > 0 || $storedAttendance > 0);
                        if ($hasStoredCaData) {
                            $hasMarksSubmitted = true;
                            $hasCaMarks = true;
                            if (!$wasExamined) $wasExamined = true;
                        }

                        if (!$wasExamined) {
                            // Not examined: check if confirmed absent via exam-level locks
                            $isAbsent = $wasConfirmedAbsent
                                || ($subjectMark->marks_locked ?? false)
                                || ($subjectMark->attendance_locked ?? false);
                            $status = $isAbsent ? 'ABS' : 'N/S';
                            $row['courses'][$subject->id] = [
                                'registered' => true, 'attendance_marks' => $status === 'ABS' ? '-' : round($attendanceMarks, 1),
                                'ca_marks' => $status === 'ABS' ? '-' : 'N/S',
                                'exam_marks' => $status === 'ABS' ? '-' : 'N/S',
                                'total_marks' => $status === 'ABS' ? '-' : 'N/S',
                                'grade' => $status, 'status' => $status,
                                'grade_point' => 0, 'credit_value' => $creditHour,
                                'is_absent' => ($status === 'ABS'),
                                'ca_absent' => $caConfirmedAbsent || $caAbsent,
                                'final_absent' => $finalConfirmedAbsent || $finalAbsent,
                                'is_not_submitted' => ($status === 'N/S'),
                                'has_zero_contribution' => $hasZeroContribution,
                                'decision_label' => null, 'decision_code' => null, 'decision_class' => null,
                            ];
                            continue;
                        }

                        $totalCA    = round($attendanceMarks + $storedAssignment + $storedActivity + $caExamMarks, 2);
                        $examMarks  = round($finalExamMarks, 2);
                        $totalMarks = round($totalCA + $examMarks, 2);

                        $gradeTitle = '-';
                        $gradePoint = 0;
                        foreach ($grades as $grade) {
                            if ($totalMarks >= $grade->min_mark && $totalMarks <= $grade->max_mark) {
                                $gradeTitle = $grade->title;
                                $gradePoint = (float) $grade->point;
                                break;
                            }
                        }

                        $passed = $totalMarks >= $passingMark;

                        // Determine status matching exam-publishing logic:
                        // ABS = confirmed absent (exam-level locks), N/S = not submitted, P/F = normal
                        if (!$hasMarksSubmitted && $wasConfirmedAbsent) {
                            $status = 'ABS';
                        } elseif (!$hasMarksSubmitted) {
                            $status = 'N/S';
                        } else {
                            $status = $passed ? 'P' : 'F';
                        }

                        if ($status === 'P') {
                            $row['summary']['courses_passed']++;
                            $row['summary']['total_credits_earned'] += $creditHour;
                        } elseif ($status === 'F') {
                            $row['summary']['courses_failed']++;
                        }

                        if ($status === 'P' || $status === 'F') {
                            $row['summary']['total_quality_points'] += ($gradePoint * $creditHour);
                        }

                        // Determine display values based on status (matching exam-publishing)
                        $displayAttendance = round($attendanceMarks, 1);
                        $displayCA = round($totalCA - $attendanceMarks, 2);
                        $displayExam = $examMarks;
                        $displayTotal = $totalMarks;
                        $displayGrade = $gradeTitle;
                        $isAbsentStatus = false;
                        $isNotSubmitted = false;

                        if ($status === 'ABS') {
                            $displayAttendance = '-';
                            $displayCA = '-';
                            $displayExam = '-';
                            $displayTotal = '-';
                            $displayGrade = 'ABS';
                            $isAbsentStatus = true;
                        } elseif ($status === 'N/S') {
                            $displayAttendance = round($attendanceMarks, 1);
                            $displayCA = 'N/S';
                            $displayExam = 'N/S';
                            $displayTotal = 'N/S';
                            $displayGrade = 'N/S';
                            $isNotSubmitted = true;
                        } else {
                            // P or F: check per-type absence for partial scenarios
                            if ($finalConfirmedAbsent && !$hasFinalMarks) {
                                $displayExam = 'ABS';
                            } elseif (!$hasFinalMarks) {
                                $displayExam = 'N/S';
                            }
                            if ($caConfirmedAbsent && !$hasCaMarks) {
                                $displayCA = 'ABS';
                            } elseif (!$hasCaMarks && !$hasStoredCaData) {
                                $displayCA = 'N/S';
                            }
                        }

                        $row['courses'][$subject->id] = [
                            'registered'          => true,
                            'attendance_marks'    => $displayAttendance,
                            'ca_marks'            => $displayCA,
                            'exam_marks'          => $displayExam,
                            'total_marks'         => $displayTotal,
                            'grade'               => $displayGrade,
                            'status'              => $status,
                            'grade_point'         => $gradePoint,
                            'credit_value'        => $creditHour,
                            'is_absent'           => $isAbsentStatus,
                            'ca_absent'           => $caConfirmedAbsent || $caAbsent,
                            'final_absent'        => $finalConfirmedAbsent || $finalAbsent,
                            'is_not_submitted'    => $isNotSubmitted,
                            'has_zero_contribution' => $hasZeroContribution,
                            'decision_label'      => $courseDecisionMetrics[$enroll->id][$subject->id]['label'] ?? null,
                            'decision_code'       => $courseDecisionMetrics[$enroll->id][$subject->id]['code'] ?? null,
                            'decision_class'      => $courseDecisionMetrics[$enroll->id][$subject->id]['class'] ?? null,
                        ];
                    }

                    // Compute GPA
                    $row['summary']['gpa'] = $row['summary']['total_credits_registered'] > 0
                        ? round($row['summary']['total_quality_points'] / $row['summary']['total_credits_registered'], 2)
                        : 0;

                    $row['summary'] = array_merge(
                        $row['summary'],
                        $progressionMetrics[$enroll->id] ?? []
                    );

                    $courseDecisions = $courseDecisionMetrics[$enroll->id] ?? [];
                    $row['summary']['carry_over_courses'] = collect($courseDecisions)
                        ->where('code', 'CO')
                        ->count();
                    $row['summary']['carry_over_credits'] = round(
                        collect($enrollSubjects)->filter(function ($subject) use ($courseDecisions) {
                            return ($courseDecisions[$subject->id]['code'] ?? null) === 'CO';
                        })->sum(fn($subject) => (float) ($subject->credit_hour ?? 0)),
                        1
                    );

                    $studentResults[] = $row;
                }

                if (empty($studentResults)) continue;

                // Course stats
                $courseStats = [];
                foreach ($subjects as $subject) {
                    $cs = ['registered' => 0, 'examined' => 0, 'passed' => 0, 'failed' => 0, 'total_marks_sum' => 0, 'average' => 0, 'pass_rate' => 0];
                    foreach ($studentResults as $sr) {
                        $c = $sr['courses'][$subject->id] ?? null;
                        if (!$c || !$c['registered']) continue;
                        $cs['registered']++;
                        if (in_array($c['status'], ['P', 'F'])) {
                            $cs['examined']++;
                            $cs['total_marks_sum'] += $c['total_marks'];
                            if ($c['status'] === 'P') $cs['passed']++;
                            if ($c['status'] === 'F') $cs['failed']++;
                        }
                    }
                    $cs['average']   = $cs['examined'] > 0 ? round($cs['total_marks_sum'] / $cs['examined'], 1) : 0;
                    $cs['pass_rate'] = $cs['examined'] > 0 ? round(($cs['passed'] / $cs['examined']) * 100, 1) : 0;
                    $courseStats[$subject->id] = $cs;
                }

                // Overall stats
                $totalStudents = count($studentResults);
                $totalPassed = collect($studentResults)->filter(fn($r) => $r['summary']['courses_failed'] == 0 && $r['summary']['courses_registered'] > 0 && $r['summary']['courses_passed'] > 0)->count();
                $totalFailed = collect($studentResults)->filter(fn($r) => $r['summary']['courses_failed'] > 0)->count();

                $facData['programs'][] = [
                    'id'         => $program->id,
                    'title'      => $program->title,
                    'shortcode'  => $program->shortcode ?? '',
                    'degree'     => $program->degreeType->shortcode ?? $program->degreeType->title ?? '',
                    'department' => $program->academicDepartment->title ?? '',
                    'subjects'   => $subjects->map(fn($s) => [
                        'id' => $s->id, 'code' => $s->code, 'title' => $s->title,
                        'credit_hour' => $s->credit_hour,
                        'lecturer' => $lecturerMap[$s->id] ?? '-',
                    ])->toArray(),
                    'students'     => $studentResults,
                    'course_stats' => $courseStats,
                    'overall'      => [
                        'total_students' => $totalStudents,
                        'total_passed'   => $totalPassed,
                        'total_failed'   => $totalFailed,
                        'total_pending'  => $totalStudents - $totalPassed - $totalFailed,
                    ],
                ];
            }

            if (!empty($facData['programs'])) {
                $result[] = $facData;
            }
        }

        return $result;
    }

    /**
     * Build institution-wide KPIs for the results preview.
     */
    private function buildResultsKPIs(int $sessionId, int $semesterId, ?int $facultyId): array
    {
        $query = SubjectMarking::whereHas('studentEnroll', function ($q) use ($sessionId, $semesterId, $facultyId) {
            $q->where('session_id', $sessionId)
              ->where('semester_id', $semesterId)
              ->whereIn('status', [1, 2]);
            if ($facultyId) {
                $q->whereHas('program', fn($pq) => $pq->where('faculty_id', $facultyId));
            }
        })->whereNotNull('total_marks');

        $total   = (clone $query)->count();
        $passed  = (clone $query)->where('total_marks', '>=', 50)->count();
        $failed  = $total - $passed;
        $average = (clone $query)->avg('total_marks');

        $uniqueStudents = (clone $query)->distinct('student_enroll_id')->count('student_enroll_id');
        $uniqueCourses  = (clone $query)->distinct('subject_id')->count('subject_id');

        return [
            'total_scripts'   => $total,
            'passed_scripts'  => $passed,
            'failed_scripts'  => $failed,
            'pass_rate'       => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            'fail_rate'       => $total > 0 ? round(($failed / $total) * 100, 1) : 0,
            'average_mark'    => round($average ?? 0, 2),
            'unique_students' => $uniqueStudents,
            'unique_courses'  => $uniqueCourses,
        ];
    }

    /**
     * Build student performance summary mapping to the requested format.
     */
    private function buildStudentPerformanceSummaries(array $matrices, array $facultySummaries): array
    {
        $summaries = [];
        $scriptsMap = [];
        
        foreach ($facultySummaries as $fs) {
            $scriptsMap[$fs['id']] = $fs['scripts_written'] ?? 0;
        }

        foreach ($matrices as $fac) {
            $facRegistered = 0;
            $facPassed = 0;
            $facFailed = 0;
            $subjects = [];

            foreach ($fac['programs'] as $prog) {
                $facRegistered += $prog['overall']['total_students'] ?? 0;
                $facPassed += $prog['overall']['total_passed'] ?? 0;
                $facFailed += $prog['overall']['total_failed'] ?? 0;
                foreach ($prog['subjects'] as $subj) {
                    $subjects[$subj['id']] = true;
                }
            }

            if ($facRegistered > 0 || !empty($subjects)) {
                $summaries[] = [
                    'id'               => $fac['id'] ?? 0,
                    'name'             => $fac['name'] ?? '',
                    'shortcode'        => $fac['shortcode'] ?? '',
                    'courses_examined' => count($subjects),
                    'scripts_marked'   => $scriptsMap[$fac['id']] ?? 0,
                    'registered'       => $facRegistered,
                    'examined'         => $facRegistered,
                    'passed'           => $facPassed,
                    'failed'           => $facFailed,
                    'pass_rate'        => $facRegistered > 0 ? round(($facPassed / $facRegistered) * 100, 1) : 0,
                ];
            }
        }
        return $summaries;
    }

    /**
     * Build per-faculty summary with department breakdown.
     */
    private function buildFacultySummaries(int $sessionId, int $semesterId, ?int $facultyId): array
    {
        $faculties = Faculty::where('status', '1')
            ->when($facultyId, fn($q) => $q->where('id', $facultyId))
            ->with('academicDepartments')
            ->orderBy('title')
            ->get();

        $summaries = [];

        foreach ($faculties as $fac) {
            $facProgramIds = Program::where('faculty_id', $fac->id)
                ->where('status', '1')
                ->pluck('id')
                ->toArray();

            if (empty($facProgramIds)) continue;

            // Check if any enrollments exist
            $enrollCount = StudentEnroll::where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->whereIn('status', [1, 2])
                ->whereIn('program_id', $facProgramIds)
                ->count();

            if ($enrollCount === 0) continue;

            $markQuery = SubjectMarking::whereHas('studentEnroll', function ($q) use ($sessionId, $semesterId, $facProgramIds) {
                $q->where('session_id', $sessionId)
                  ->where('semester_id', $semesterId)
                  ->whereIn('status', [1, 2])
                  ->whereIn('program_id', $facProgramIds);
            })->whereNotNull('total_marks');

            $totalScripts = (clone $markQuery)->count();
            $passedScripts = (clone $markQuery)->where('total_marks', '>=', 50)->count();

            // Department breakdown
            $departments = [];
            foreach ($fac->academicDepartments as $dept) {
                $deptProgramIds = Program::where('academic_department_id', $dept->id)
                    ->where('faculty_id', $fac->id)
                    ->where('status', '1')
                    ->pluck('id')->toArray();

                if (empty($deptProgramIds)) continue;

                $deptMarkQuery = SubjectMarking::whereHas('studentEnroll', function ($q) use ($sessionId, $semesterId, $deptProgramIds) {
                    $q->where('session_id', $sessionId)
                      ->where('semester_id', $semesterId)
                      ->whereIn('status', [1, 2])
                      ->whereIn('program_id', $deptProgramIds);
                })->whereNotNull('total_marks');

                $deptTotal  = (clone $deptMarkQuery)->count();
                if ($deptTotal === 0) continue;
                $deptPassed = (clone $deptMarkQuery)->where('total_marks', '>=', 50)->count();

                $deptCourses = (clone $deptMarkQuery)->distinct('subject_id')->count('subject_id');

                $departments[] = [
                    'id'              => $dept->id,
                    'name'            => $dept->title,
                    'shortcode'       => $dept->shortcode ?? '',
                    'head_name'       => $dept->headOfDepartment->name ?? '-',
                    'courses_offered' => $deptCourses,
                    'scripts_written' => $deptTotal,
                    'passed'          => $deptPassed,
                    'failed'          => $deptTotal - $deptPassed,
                    'pass_rate'       => round(($deptPassed / $deptTotal) * 100, 1),
                    'fail_rate'       => round((($deptTotal - $deptPassed) / $deptTotal) * 100, 1),
                ];
            }

            $summaries[] = [
                'id'              => $fac->id,
                'name'            => $fac->title,
                'shortcode'       => $fac->shortcode ?? '',
                'program_count'   => count($facProgramIds),
                'student_count'   => $enrollCount,
                'scripts_written' => $totalScripts,
                'passed'          => $passedScripts,
                'failed'          => $totalScripts - $passedScripts,
                'pass_rate'       => $totalScripts > 0 ? round(($passedScripts / $totalScripts) * 100, 1) : 0,
                'fail_rate'       => $totalScripts > 0 ? round((($totalScripts - $passedScripts) / $totalScripts) * 100, 1) : 0,
                'courses_offered' => (clone $markQuery)->distinct('subject_id')->count('subject_id'),
                'departments'     => $departments,
            ];
        }

        return $summaries;
    }

    /**
     * Build detailed course results grouped by Faculty → Department → Course.
     */
    private function buildCourseResultsByFaculty(int $sessionId, int $semesterId, ?int $facultyId): array
    {
        $faculties = Faculty::where('status', '1')
            ->when($facultyId, fn($q) => $q->where('id', $facultyId))
            ->with(['academicDepartments.programs' => fn($q) => $q->where('status', '1')])
            ->orderBy('title')
            ->get();

        $gradesRef = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $gradeColTitles = $gradesRef->pluck('title')->toArray();
        $result = [];

        foreach ($faculties as $fac) {
            $facData = [
                'id' => $fac->id, 'name' => $fac->title, 'shortcode' => $fac->shortcode ?? '',
                'departments' => [],
            ];

            foreach ($fac->academicDepartments as $dept) {
                $deptData = [
                    'id' => $dept->id, 'name' => $dept->title, 'shortcode' => $dept->shortcode ?? '',
                    'head_name' => $dept->headOfDepartment->name ?? '-',
                    'courses' => [],
                ];

                $deptProgramIds = $dept->programs->pluck('id')->toArray();
                if (empty($deptProgramIds)) continue;

                // Find subjects taught in these programs this semester
                $subjectIds = SubjectMarking::whereHas('studentEnroll', function ($q) use ($sessionId, $semesterId, $deptProgramIds) {
                    $q->where('session_id', $sessionId)
                      ->where('semester_id', $semesterId)
                      ->whereIn('status', [1, 2])
                      ->whereIn('program_id', $deptProgramIds);
                })->whereNotNull('total_marks')
                  ->distinct('subject_id')
                  ->pluck('subject_id')
                  ->toArray();

                if (empty($subjectIds)) continue;

                $subjects = Subject::whereIn('id', $subjectIds)->orderBy('code')->get();

                foreach ($subjects as $subj) {
                    $smQuery = SubjectMarking::where('subject_id', $subj->id)
                        ->whereHas('studentEnroll', function ($q) use ($sessionId, $semesterId, $deptProgramIds) {
                            $q->where('session_id', $sessionId)
                              ->where('semester_id', $semesterId)
                              ->whereIn('status', [1, 2])
                              ->whereIn('program_id', $deptProgramIds);
                        })->whereNotNull('total_marks');

                    $markings = (clone $smQuery)->get();
                    $examined = $markings->count();
                    if ($examined === 0) continue;

                    $passed = $markings->where('total_marks', '>=', 50)->count();

                    // Registered count (students with exams for this subject)
                    $registered = StudentEnroll::where('session_id', $sessionId)
                        ->where('semester_id', $semesterId)
                        ->whereIn('status', [1, 2])
                        ->whereIn('program_id', $deptProgramIds)
                        ->whereHas('exams', fn($q) => $q->where('subject_id', $subj->id))
                        ->count();

                    // Grade distribution
                    $gradeDist = [];
                    foreach ($gradeColTitles as $gt) { $gradeDist[$gt] = 0; }
                    foreach ($markings as $m) {
                        foreach ($gradesRef as $g) {
                            if ($m->total_marks >= $g->min_mark && $m->total_marks <= $g->max_mark) {
                                $gradeDist[$g->title] = ($gradeDist[$g->title] ?? 0) + 1;
                                break;
                            }
                        }
                    }

                    // Lecturers — try dept programs first, fall back to any program in this session/semester
                    $lecturers = ClassRoutine::where('subject_id', $subj->id)
                        ->where('session_id', $sessionId)
                        ->where('semester_id', $semesterId)
                        ->whereIn('program_id', $deptProgramIds)
                        ->with('teacher')
                        ->get()
                        ->pluck('teacher.name')
                        ->filter()
                        ->unique()
                        ->implode(', ');

                    if (empty($lecturers)) {
                        $lecturers = ClassRoutine::where('subject_id', $subj->id)
                            ->where('session_id', $sessionId)
                            ->where('semester_id', $semesterId)
                            ->with('teacher')
                            ->get()
                            ->pluck('teacher.name')
                            ->filter()
                            ->unique()
                            ->implode(', ');
                    }

                    // Course coverage
                    $coverage = $this->calculateCourseCoverage($subj->id, $sessionId, $semesterId, $deptProgramIds);

                    // Subject type: DB values 0=Optional, 1=Compulsory, 2=University Requirement
                    $typeLabel = match((int)($subj->subject_type ?? 0)) {
                        1 => 'C', 2 => 'UR', 0 => 'O', default => '-',
                    };

                    $deptData['courses'][] = [
                        'id'                     => $subj->id,
                        'code'                   => $subj->code,
                        'title'                  => $subj->title,
                        'credit_value'           => $subj->credit_hour,
                        'type'                   => $typeLabel,
                        'lecturers'              => $lecturers ?: '-',
                        'coverage'               => $coverage,
                        'candidates_registered'  => $registered,
                        'candidates_examined'    => $examined,
                        'passed'                 => $passed,
                        'failed'                 => $examined - $passed,
                        'pass_rate'              => round(($passed / $examined) * 100, 1),
                        'fail_rate'              => round((($examined - $passed) / $examined) * 100, 1),
                        'average_marks'          => round($markings->avg('total_marks'), 2),
                        'grade_distribution'     => $gradeDist,
                    ];
                }

                if (!empty($deptData['courses'])) {
                    $facData['departments'][] = $deptData;
                }
            }

            if (!empty($facData['departments'])) {
                $result[] = $facData;
            }
        }

        return $result;
    }

    /**
     * Build lecturer performance index — aggregated across all courses.
     */
    private function buildLecturerPerformance(int $sessionId, int $semesterId, ?int $facultyId): array
    {
        $routineQuery = ClassRoutine::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where('status', '1');

        if ($facultyId) {
            $routineQuery->whereHas('program', fn($q) => $q->where('faculty_id', $facultyId));
        }

        $routines = $routineQuery->with(['teacher', 'subject', 'program.faculty', 'program.academicDepartment'])
            ->get();

        // Group by teacher
        $lecturerMap = [];

        foreach ($routines as $routine) {
            if (!$routine->teacher || !$routine->subject) continue;

            $teacherId = $routine->teacher_id;
            $subjectId = $routine->subject_id;
            $key = $teacherId . '_' . $subjectId;

            if (!isset($lecturerMap[$teacherId])) {
                $lecturerMap[$teacherId] = [
                    'id'         => $teacherId,
                    'name'       => $routine->teacher->name,
                    'faculty'    => $routine->program->faculty->title ?? '-',
                    'department' => $routine->program->academicDepartment->title ?? '-',
                    'courses'    => [],
                    'aggregate'  => [
                        'total_scripts' => 0, 'passed' => 0, 'failed' => 0,
                        'total_marks' => 0, 'course_count' => 0,
                    ],
                ];
            }

            // Avoid counting duplicate subject entries for same lecturer
            if (isset($lecturerMap[$teacherId]['courses'][$subjectId])) continue;

            // Get marks for this subject in this session/semester
            $programIds = $routines->where('teacher_id', $teacherId)
                ->where('subject_id', $subjectId)
                ->pluck('program_id')
                ->unique()
                ->toArray();

            $markings = SubjectMarking::where('subject_id', $subjectId)
                ->whereHas('studentEnroll', function ($q) use ($sessionId, $semesterId, $programIds) {
                    $q->where('session_id', $sessionId)
                      ->where('semester_id', $semesterId)
                      ->whereIn('status', [1, 2])
                      ->whereIn('program_id', $programIds);
                })->whereNotNull('total_marks')
                ->get();

            $examined = $markings->count();
            if ($examined === 0) continue;

            $passed  = $markings->where('total_marks', '>=', 50)->count();
            $avgMark = round($markings->avg('total_marks'), 2);
            $passRate = round(($passed / $examined) * 100, 1);

            // Coverage
            $coverage = $this->calculateCourseCoverage($subjectId, $sessionId, $semesterId, $programIds);

            $lecturerMap[$teacherId]['courses'][$subjectId] = [
                'code'      => $routine->subject->code,
                'title'     => $routine->subject->title,
                'credit'    => $routine->subject->credit_hour,
                'examined'  => $examined,
                'passed'    => $passed,
                'failed'    => $examined - $passed,
                'pass_rate' => $passRate,
                'average'   => $avgMark,
                'coverage'  => $coverage,
            ];

            $lecturerMap[$teacherId]['aggregate']['total_scripts'] += $examined;
            $lecturerMap[$teacherId]['aggregate']['passed'] += $passed;
            $lecturerMap[$teacherId]['aggregate']['failed'] += ($examined - $passed);
            $lecturerMap[$teacherId]['aggregate']['total_marks'] += ($avgMark * $examined);
            $lecturerMap[$teacherId]['aggregate']['course_count']++;
        }

        // Compute aggregate pass rate & averages, assign performance rating
        $lecturers = [];
        foreach ($lecturerMap as $lec) {
            $agg = &$lec['aggregate'];
            $agg['pass_rate']    = $agg['total_scripts'] > 0 ? round(($agg['passed'] / $agg['total_scripts']) * 100, 1) : 0;
            $agg['average_mark'] = $agg['total_scripts'] > 0 ? round($agg['total_marks'] / $agg['total_scripts'], 2) : 0;

            // Performance rating: Outstanding / Good / Satisfactory / Needs Improvement / Critical
            $pr = $agg['pass_rate'];
            $agg['rating'] = match(true) {
                $pr >= 90 => 'Outstanding',
                $pr >= 75 => 'Good',
                $pr >= 60 => 'Satisfactory',
                $pr >= 45 => 'Needs Improvement',
                default   => 'Critical',
            };
            $agg['rating_class'] = match($agg['rating']) {
                'Outstanding'       => 'success',
                'Good'              => 'info',
                'Satisfactory'      => 'primary',
                'Needs Improvement' => 'warning',
                'Critical'          => 'danger',
                default             => 'secondary',
            };

            $lec['courses'] = array_values($lec['courses']);
            $lecturers[] = $lec;
        }

        // Sort by pass rate descending
        usort($lecturers, fn($a, $b) => $b['aggregate']['pass_rate'] <=> $a['aggregate']['pass_rate']);

        // Add rank
        foreach ($lecturers as $i => &$l) { $l['rank'] = $i + 1; }

        return $lecturers;
    }

    /**
     * Build publishing readiness summary.
     */
    private function buildPublishingSummary(int $sessionId, int $semesterId, ?int $facultyId): array
    {
        $query = ExamPublishingState::where('session_id', $sessionId)
            ->where('semester_id', $semesterId);

        if ($facultyId) {
            $query->whereHas('program', fn($q) => $q->where('faculty_id', $facultyId));
        }

        $states = (clone $query)->select('workflow_state', DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_state')
            ->pluck('count', 'workflow_state')
            ->toArray();

        $total = array_sum($states);

        return [
            'total'     => $total,
            'published' => $states[ExamPublishingState::STATE_PUBLISHED] ?? 0,
            'approved'  => $states[ExamPublishingState::STATE_APPROVED] ?? 0,
            'checked'   => $states[ExamPublishingState::STATE_CHECKED] ?? 0,
            'submitted' => $states[ExamPublishingState::STATE_SUBMITTED] ?? 0,
            'draft'     => $states[ExamPublishingState::STATE_DRAFT] ?? 0,
            'readiness' => $total > 0
                ? round((($states[ExamPublishingState::STATE_PUBLISHED] ?? 0) / $total) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get top/bottom performing courses.
     */
    private function getPerformingCourses(int $sessionId, int $semesterId, ?int $facultyId, string $type = 'best', int $limit = 10): array
    {
        $query = DB::table('subject_markings')
            ->join('student_enrolls', 'subject_markings.student_enroll_id', '=', 'student_enrolls.id')
            ->join('subjects', 'subject_markings.subject_id', '=', 'subjects.id')
            ->where('student_enrolls.session_id', $sessionId)
            ->where('student_enrolls.semester_id', $semesterId)
            ->whereIn('student_enrolls.status', [1, 2])
            ->whereNotNull('subject_markings.total_marks');

        if ($facultyId) {
            $query->join('programs', 'student_enrolls.program_id', '=', 'programs.id')
                  ->where('programs.faculty_id', $facultyId);
        }

        $results = $query->select(
                'subject_markings.subject_id',
                'subjects.code as course_code',
                'subjects.title as course_title',
                'subjects.credit_hour',
                DB::raw('COUNT(*) as total_scripts'),
                DB::raw('SUM(CASE WHEN subject_markings.total_marks >= 50 THEN 1 ELSE 0 END) as passed_scripts'),
                DB::raw('SUM(CASE WHEN subject_markings.total_marks < 50 THEN 1 ELSE 0 END) as failed_scripts'),
                DB::raw('ROUND(AVG(subject_markings.total_marks), 2) as average_marks'),
                DB::raw('ROUND((SUM(CASE WHEN subject_markings.total_marks >= 50 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as pass_rate')
            )
            ->groupBy('subject_markings.subject_id', 'subjects.code', 'subjects.title', 'subjects.credit_hour')
            ->having('total_scripts', '>=', 3) // At least 3 scripts for meaningful data
            ->orderBy('pass_rate', $type === 'best' ? 'desc' : 'asc')
            ->limit($limit)
            ->get()
            ->toArray();

        // Get lecturers for these courses
        $subjectIds = array_column($results, 'subject_id');
        $lecturers = $this->getSubjectLecturers($subjectIds, $sessionId, $semesterId);

        foreach ($results as &$r) {
            $r = (array) $r;
            $r['lecturers'] = $lecturers[$r['subject_id']] ?? '-';
            $r['fail_rate'] = 100 - (float) $r['pass_rate'];
        }

        return $results;
    }

    /**
     * Get lecturers for a list of subject IDs.
     */
    private function getSubjectLecturers(array $subjectIds, int $sessionId, int $semesterId, ?int $programId = null): array
    {
        if (empty($subjectIds)) return [];

        $query = ClassRoutine::whereIn('subject_id', $subjectIds)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->with('teacher');

        if ($programId) {
            $query->where('program_id', $programId);
        }

        $routines = $query->get();
        $map = [];

        foreach ($routines as $r) {
            if (!$r->teacher) continue;
            $sid = $r->subject_id;
            if (!isset($map[$sid])) $map[$sid] = [];
            $map[$sid][$r->teacher->name] = true;
        }

        return array_map(fn($names) => implode(', ', array_keys($names)), $map);
    }

    /**
     * Calculate course coverage percentage.
     */
    private function calculateCourseCoverage(int $subjectId, int $sessionId, int $semesterId, array $programIds): int
    {
        $planned = ClassSession::where('subject_id', $subjectId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->count();

        if ($planned === 0) return 100; // Default if no class sessions tracked

        $completed = ClassSession::where('subject_id', $subjectId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->where(function ($q) {
                $q->whereNotNull('actual_start_time')
                  ->orWhereIn('status', ['completed', 'held']);
            })
            ->count();

        return min(100, (int) round(($completed / $planned) * 100));
    }

    /* =========================================================================
     *  EXPORT — PDF  (Senate Report)
     * ========================================================================= */

    public function exportPdf(Request $request)
    {
        $request->validate([
            'deliberation_id' => 'required|exists:senate_deliberations,id',
        ]);

        $deliberation = SenateDeliberation::with([
            'session', 'semester', 'creator',
            'programs.program.faculty',
            'signatures',
        ])->findOrFail($request->deliberation_id);

        // Academic standing summary
        $standingCounts = AcademicStanding::where('session_id', $deliberation->session_id)
            ->where('semester_id', $deliberation->semester_id)
            ->select('standing', DB::raw('COUNT(*) as count'))
            ->groupBy('standing')
            ->pluck('count', 'standing')
            ->toArray();

        // Programs grouped by faculty
        $programsByFaculty = $deliberation->programs
            ->sortBy('program.title')
            ->groupBy(fn($p) => $p->program->faculty->title ?? 'Unknown');

        // Flagged students
        $flaggedStudents = AcademicStanding::where('session_id', $deliberation->session_id)
            ->where('semester_id', $deliberation->semester_id)
            ->whereIn('standing', [
                AcademicStanding::STANDING_WARNING,
                AcademicStanding::STANDING_PROBATION,
                AcademicStanding::STANDING_RECOMMENDED_DISMISSAL,
            ])
            ->with(['student', 'program'])
            ->orderBy('standing')
            ->orderBy('gpa', 'asc')
            ->get();

        // Overall stats
        $allGpas = AcademicStanding::where('session_id', $deliberation->session_id)
            ->where('semester_id', $deliberation->semester_id)
            ->pluck('gpa');

        $overallStats = [
            'total_students' => $allGpas->count(),
            'avg_gpa'       => $allGpas->count() > 0 ? round($allGpas->avg(), 2) : 0,
            'highest_gpa'   => $allGpas->max() ?? 0,
            'lowest_gpa'    => $allGpas->min() ?? 0,
        ];

        $data = compact(
            'deliberation', 'standingCounts', 'programsByFaculty',
            'flaggedStudents', 'overallStats'
        );
        $data['standingLabels'] = AcademicStanding::standingLabels();
        $data['decisionLabels'] = SenateDeliberation::decisionLabels();

        // Log the export
        SenateDeliberationLog::create([
            'senate_deliberation_id' => $deliberation->id,
            'action'       => SenateDeliberationLog::ACTION_EXPORTED,
            'description'  => 'Senate report exported as PDF',
            'performed_by' => Auth::id(),
        ]);

        $pdf = \PDF::loadView($this->view . '.exports.senate-report-pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Senate_Report_' . str_replace(['/', ' '], '_', $deliberation->meeting_number) . '.pdf';

        return $pdf->download($filename);
    }

    /* =========================================================================
     *  EXPORT — Excel  (Comprehensive Results Preview)
     * ========================================================================= */

    public function exportResultsPreview(Request $request)
    {
        $request->validate([
            'session'  => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
        ]);

        $sessionId  = (int) $request->session;
        $semesterId = (int) $request->semester;
        $facultyId  = ($request->faculty && $request->faculty !== '0') ? (int) $request->faculty : null;

        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Build all data sections (same as resultsPreview)
        $exportData = [
            'kpis'                    => $this->buildResultsKPIs($sessionId, $semesterId, $facultyId),
            'faculty_summaries'       => $this->buildFacultySummaries($sessionId, $semesterId, $facultyId),
            'faculty_course_data'     => $this->buildCourseResultsByFaculty($sessionId, $semesterId, $facultyId),
            'lecturer_performance'    => $this->buildLecturerPerformance($sessionId, $semesterId, $facultyId),
            'publishing_summary'      => $this->buildPublishingSummary($sessionId, $semesterId, $facultyId),
            'top_courses'             => $this->getPerformingCourses($sessionId, $semesterId, $facultyId, 'best', 10),
            'bottom_courses'          => $this->getPerformingCourses($sessionId, $semesterId, $facultyId, 'worst', 10),
            'faculty_student_matrices'=> $this->buildStudentMatrixByFaculty($sessionId, $semesterId, $facultyId, $grades),
            'grades'                  => $grades,
            'standingLabels'          => AcademicStanding::standingLabels(),
        ];
        
        $exportData['student_performance_summaries'] = $this->buildStudentPerformanceSummaries(
            $exportData['faculty_student_matrices'],
            $exportData['faculty_summaries']
        );

        // Academic standing distribution
        $exportData['standings_computed'] = AcademicStanding::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)->exists();

        if ($exportData['standings_computed']) {
            $stQuery = AcademicStanding::where('session_id', $sessionId)
                ->where('semester_id', $semesterId);
            if ($facultyId) $stQuery->where('faculty_id', $facultyId);

            $exportData['standing_distribution'] = $stQuery
                ->select('standing', DB::raw('COUNT(*) as count'))
                ->groupBy('standing')
                ->pluck('count', 'standing')
                ->toArray();
        } else {
            $exportData['standing_distribution'] = [];
        }

        // Context labels
        $sessionModel  = Session::find($sessionId);
        $semesterModel = Semester::find($semesterId);
        $exportData['session_label']    = $sessionModel->title ?? '';
        $exportData['semester_label']   = $semesterModel->title ?? '';
        $exportData['institution_name'] = config('app.name', 'Institution');

        // Build filename
        $sessionSlug  = str_replace(['/', ' ', '\\'], '_', $exportData['session_label']);
        $semesterSlug = str_replace(['/', ' ', '\\'], '_', $exportData['semester_label']);
        $filename = "Senate_Results_Preview_{$sessionSlug}_{$semesterSlug}.xlsx";

        // Log this export
        $deliberation = SenateDeliberation::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)->first();
        if ($deliberation) {
            SenateDeliberationLog::create([
                'senate_deliberation_id' => $deliberation->id,
                'performed_by'           => Auth::id(),
                'action'                 => 'exported_results_preview_excel',
                'description'            => 'Results preview exported as Excel',
                'meta'                   => [
                    'faculty_filter' => $facultyId,
                    'filename'       => $filename,
                ],
            ]);
        }

        return Excel::download(new SenateResultsPreviewExport($exportData), $filename);
    }

    /* =========================================================================
     *  EXPORT — Excel  (Academic Standings)
     * ========================================================================= */

    public function exportExcel(Request $request)
    {
        $request->validate([
            'session'  => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
        ]);

        $sessionId  = (int) $request->session;
        $semesterId = (int) $request->semester;
        $facultyId  = $request->faculty !== null && $request->faculty !== '0' ? (int) $request->faculty : null;
        $programId  = $request->program !== null && $request->program !== '0' ? (int) $request->program : null;
        $standingFilter = $request->standing ?: 'all';

        $session  = Session::find($sessionId);
        $semester = Semester::find($semesterId);

        $query = AcademicStanding::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->with(['student', 'program.faculty', 'enrollment']);

        if ($facultyId)               $query->where('faculty_id', $facultyId);
        if ($programId)               $query->where('program_id', $programId);
        if ($standingFilter !== 'all') $query->where('standing', $standingFilter);

        $standings = $query
            ->orderBy('faculty_id')
            ->orderBy('program_id')
            ->orderBy('gpa', 'desc')
            ->get();

        if ($standings->isEmpty()) {
            return redirect()->back()->with('error', 'No standings data to export. Please classify standings first.');
        }

        $this->attachCarryOverMetrics($standings);

        $standingLabels = AcademicStanding::standingLabels();

        // Build structured data array for the export class
        $exportData = [
            'institution_name' => config('app.name', 'Institution'),
            'session_label'    => $session->title ?? '-',
            'semester_label'   => $semester->title ?? '-',
            'faculty_label'    => $facultyId ? (Faculty::find($facultyId)->title ?? '-') : 'All Faculties',
            'program_label'    => $programId ? (Program::find($programId)->title ?? '-') : 'All Programs',
            'standing_label'   => $standingFilter === 'all' ? 'All Standings' : ($standingLabels[$standingFilter] ?? $standingFilter),
            'generated_by'     => Auth::user()->name ?? 'System',
            'standing_labels'  => $standingLabels,
            'standing_counts'  => $standings->groupBy('standing')->map->count()->toArray(),
            'summary' => [
                'total_students'   => $standings->count(),
                'avg_gpa'          => round($standings->avg('gpa') ?? 0, 2),
                'highest_gpa'      => round($standings->max('gpa') ?? 0, 2),
                'lowest_gpa'       => round($standings->min('gpa') ?? 0, 2),
                'total_co_courses' => (int) $standings->sum('carry_over_courses'),
                'total_co_credits' => round($standings->sum('carry_over_credits'), 1),
            ],
            'standings' => $standings->map(function ($s) use ($standingLabels) {
                return [
                    'matricule'               => $s->enrollment->matricule ?? $s->student->student_id ?? 'N/A',
                    'student_name'            => trim(($s->student->first_name ?? '') . ' ' . ($s->student->last_name ?? '')),
                    'faculty'                 => $s->program->faculty->title ?? '',
                    'program'                 => $s->program->title ?? '',
                    'credits_registered'      => $s->total_credits_registered,
                    'credits_earned'          => $s->total_credits_earned,
                    'courses_registered'      => $s->courses_registered,
                    'courses_passed'          => $s->courses_passed,
                    'courses_failed'          => $s->courses_failed,
                    'carry_over_courses'      => $s->carry_over_courses ?? 0,
                    'carry_over_credits'      => rtrim(rtrim(number_format($s->carry_over_credits ?? 0, 1), '0'), '.'),
                    'gpa'                     => (float) $s->gpa,
                    'standing'                => $s->standing,
                    'standing_label'          => $standingLabels[$s->standing] ?? ucfirst($s->standing),
                    'previous_standing_label' => $s->previous_standing ? ($standingLabels[$s->previous_standing] ?? ucfirst($s->previous_standing)) : '-',
                ];
            })->toArray(),
        ];

        // Filename
        $filename = 'Academic_Standings_' . ($session->title ?? '') . '_' . ($semester->title ?? '');
        if ($facultyId && isset($exportData['faculty_label'])) $filename .= '_' . $exportData['faculty_label'];
        if ($programId && isset($exportData['program_label'])) $filename .= '_' . $exportData['program_label'];
        if ($standingFilter !== 'all') $filename .= '_' . ($standingLabels[$standingFilter] ?? $standingFilter);
        $filename = str_replace(['/', ' ', '\\'], '_', $filename) . '.xlsx';

        // Log if there's a deliberation
        $deliberation = SenateDeliberation::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->latest()
            ->first();

        if ($deliberation) {
            SenateDeliberationLog::create([
                'senate_deliberation_id' => $deliberation->id,
                'action'       => SenateDeliberationLog::ACTION_EXPORTED,
                'description'  => 'Academic standings exported as Excel',
                'performed_by' => Auth::id(),
            ]);
        }

        return Excel::download(new \App\Exports\AcademicStandingsExport($exportData), $filename);
    }
}
