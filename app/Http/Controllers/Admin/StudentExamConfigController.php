<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamScriptCode;
use App\Models\ExamType;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Subject;
use App\Models\StudentEnroll;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentExamConfigController extends Controller
{
    protected string $title;
    protected string $route;
    protected string $view;
    protected string $path;
    protected string $access;

    public function __construct()
    {
        $this->title = __('Student Exam Config');
        $this->route = 'admin.student-exam-config';
        $this->view = 'admin.student-exam-config';
        $this->path = 'exam';
        $this->access = 'exam';

        $this->middleware('permission:' . $this->access . '-marking');
    }

    public function index(Request $request)
    {
        $data = [
            'title' => $this->title,
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'access' => $this->access,
            'selected_faculty' => $request->input('faculty'),
            'selected_program' => $request->input('program'),
            'selected_session' => $request->input('session'),
            'selected_semester' => $request->input('semester'),
            'selected_section' => $request->input('section'),
            'selected_subject' => $request->input('subject'),
            'selected_type' => $request->input('type'),
            'selected_exam_type' => null,
            'cross_program' => $request->boolean('cross_program', false),
            'rows' => [],
        ];

        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['types'] = ExamType::where('status', '1')->where('is_final', true)->orderBy('title', 'asc')->get();

        // ── Overall Indicator: unconfigured exam IDs across ALL subjects ──
        // Only computed on initial page load (no filters applied) for quick overview
        if (!$this->canSearch($data)) {
            $finalTypeIds = $data['types']->pluck('id')->toArray();

            if (!empty($finalTypeIds)) {
                // Get accessible faculty IDs for this staff member
                $accessibleFacultyIds = $data['faculties']->pluck('id')->toArray();

                $overviewRows = DB::table('exams as e')
                    ->join('student_enrolls as se', 'e.student_enroll_id', '=', 'se.id')
                    ->join('programs as p', 'se.program_id', '=', 'p.id')
                    ->join('faculties as f', 'p.faculty_id', '=', 'f.id')
                    ->join('subjects as sub', 'e.subject_id', '=', 'sub.id')
                    ->join('exam_types as et', 'e.exam_type_id', '=', 'et.id')
                    ->join('sessions as sess', 'se.session_id', '=', 'sess.id')
                    ->leftJoin('exam_script_codes as esc', 'esc.exam_id', '=', 'e.id')
                    ->where('e.attendance', '1')
                    ->whereIn('e.exam_type_id', $finalTypeIds)
                    ->whereIn('p.faculty_id', $accessibleFacultyIds)
                    ->select(
                        'f.id as faculty_id',
                        'f.title as faculty_name',
                        'p.id as program_id',
                        'p.title as program_name',
                        'sub.id as subject_id',
                        DB::raw("COALESCE(sub.title, sub.code) as subject_name"),
                        'sub.code as subject_code',
                        'et.id as exam_type_id',
                        'et.title as exam_type_name',
                        'sess.id as session_id',
                        'sess.title as session_name',
                        DB::raw('COUNT(e.id) as total_students'),
                        DB::raw('COUNT(esc.id) as configured_count'),
                        DB::raw('COUNT(e.id) - COUNT(esc.id) as unconfigured_count')
                    )
                    ->groupBy(
                        'f.id', 'f.title',
                        'p.id', 'p.title',
                        'sub.id', 'sub.title', 'sub.code',
                        'et.id', 'et.title',
                        'sess.id', 'sess.title'
                    )
                    ->having(DB::raw('COUNT(e.id) - COUNT(esc.id)'), '>', 0)
                    ->orderBy('f.title')
                    ->orderBy('p.title')
                    ->orderBy('sub.code')
                    ->get();

                $data['overview_pending'] = $overviewRows;
                $data['overview_total_pending'] = $overviewRows->sum('unconfigured_count');
                $data['overview_total_configured'] = $overviewRows->sum('configured_count');
                $data['overview_grand_total'] = $overviewRows->sum('total_students');
            }
        }

        if (!empty($data['selected_type'])) {
            $data['selected_exam_type'] = $data['types']->firstWhere('id', (int) $data['selected_type']);
        }

        if (!empty($data['selected_faculty'])) {
            $data['programs'] = Program::where('faculty_id', $data['selected_faculty'])
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }

        if (!empty($data['selected_program'])) {
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($data) {
                $query->where('program_id', $data['selected_program']);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($data) {
                $query->where('program_id', $data['selected_program']);
            });
            $data['semesters'] = $semesters->orderBy('id', 'asc')->get();
        }

        if (!empty($data['selected_program']) && !empty($data['selected_semester']) && $data['selected_semester'] != '0') {
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($data) {
                $query->where('program_id', $data['selected_program']);
                $query->where('semester_id', $data['selected_semester']);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        if (!empty($data['selected_program']) && !empty($data['selected_session'])) {
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($data) {
                if (!empty($data['selected_session'])) {
                    $query->where('session_id', $data['selected_session']);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($data) {
                $query->where('program_id', $data['selected_program']);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }

        if ($this->canSearch($data)) {
            // ── Find all programmes that share this subject ──
            $subjectModel = Subject::find($data['selected_subject']);
            $data['sharing_programs'] = $subjectModel
                ? $subjectModel->programs()->with('faculty')->where('programs.status', '1')->orderBy('programs.title')->get()
                : collect();

            $crossProgram = $data['cross_program'];

            $exams = Exam::with(['studentEnroll.student', 'studentEnroll.program.faculty', 'studentEnroll.semester', 'studentEnroll.section', 'scriptCode', 'type'])
                ->where('attendance', '1')
                ->where('subject_id', $data['selected_subject'])
                ->where('exam_type_id', $data['selected_type']);

            if ($crossProgram) {
                // Cross-programme mode: only filter by session (skip program/semester/section)
                $sharingProgramIds = $data['sharing_programs']->pluck('id')->toArray();
                $exams->whereHas('studentEnroll', function ($query) use ($data, $sharingProgramIds) {
                    $query->where('session_id', $data['selected_session']);
                    if (!empty($sharingProgramIds)) {
                        $query->whereIn('program_id', $sharingProgramIds);
                    }
                });
            } else {
                // Normal mode: filter by all criteria
                $exams->whereHas('studentEnroll', function ($query) use ($data) {
                    $query->where('program_id', $data['selected_program']);
                    $query->where('session_id', $data['selected_session']);

                    if (!empty($data['selected_semester']) && $data['selected_semester'] != '0') {
                        $query->where('semester_id', $data['selected_semester']);
                    }

                    if (!empty($data['selected_section']) && $data['selected_section'] != '0') {
                        $query->where('section_id', $data['selected_section']);
                    }
                });
            }

            $examRows = $exams->get();

            if ($crossProgram) {
                // Cross-programme: sort by programme name, then matricule
                $data['rows'] = $examRows->sortBy(function ($exam) {
                    $enroll = $exam->studentEnroll;
                    return ($enroll->program->title ?? '') . '_' . ($enroll->matricule ?? optional($enroll->student)->student_id ?? '');
                })->values()->all();
            } else {
                $data['rows'] = $examRows->sortBy(function ($exam) {
                    $enroll = $exam->studentEnroll;
                    $student = optional($enroll->student);
                    return $enroll->matricule ?? $student->student_id ?? $student->first_name ?? $student->last_name ?? $exam->id;
                })->values()->all();
            }

            // ── Unconfigured Students Indicator ──
            // Students who attended but have no anonymous exam ID assigned
            $unconfigured = [];
            $configured = [];
            foreach ($data['rows'] as $exam) {
                if (empty($exam->scriptCode) || empty($exam->scriptCode->anonymous_code)) {
                    $unconfigured[] = $exam;
                } else {
                    $configured[] = $exam;
                }
            }
            $data['total_students']       = count($data['rows']);
            $data['configured_count']     = count($configured);
            $data['unconfigured_count']   = count($unconfigured);
            $data['unconfigured_students'] = $unconfigured;

            // Context labels for the banner
            $data['context_subject'] = Subject::find($data['selected_subject']);
            $data['context_program'] = Program::find($data['selected_program']);
            $data['context_session'] = Session::find($data['selected_session']);
        }

        return view($this->view . '.index', $data);
    }

    public function store(Request $request)
    {
        $studentExamIds = $request->input('student_exam_ids', []);
        $filters = Arr::only($request->all(), ['faculty', 'program', 'session', 'semester', 'section', 'subject', 'type', 'cross_program']);

        if (!is_array($studentExamIds)) {
            $studentExamIds = [];
        }

        $normalized = [];
        foreach ($studentExamIds as $examId => $value) {
            $normalized[(int) $examId] = trim((string) $value);
        }

        $nonEmptyCodes = array_filter($normalized, function ($value) {
            return $value !== '';
        });

        if (count($nonEmptyCodes) !== count(array_unique($nonEmptyCodes))) {
            throw ValidationException::withMessages([
                'student_exam_ids' => __('Student exam IDs must be unique.'),
            ]);
        }

        // Check for conflicts within the appropriate scope (cross-programme or single programme)
        if (!empty($nonEmptyCodes)) {
            $programId = $filters['program'] ?? null;
            $sessionId = $filters['session'] ?? null;
            $semesterId = $filters['semester'] ?? null;
            $subjectId = $filters['subject'] ?? null;
            $examTypeId = $filters['type'] ?? null;
            $crossProgram = ($filters['cross_program'] ?? '0') === '1';

            $conflicts = ExamScriptCode::whereIn('anonymous_code', array_values($nonEmptyCodes))
                ->whereNotIn('exam_id', array_keys($nonEmptyCodes))
                ->whereHas('exam', function ($query) use ($programId, $sessionId, $semesterId, $subjectId, $examTypeId, $crossProgram) {
                    $query->where('subject_id', $subjectId)
                          ->where('exam_type_id', $examTypeId)
                          ->whereHas('studentEnroll', function ($q) use ($programId, $sessionId, $semesterId, $crossProgram) {
                              $q->where('session_id', $sessionId);
                              if (!$crossProgram) {
                                  $q->where('program_id', $programId)
                                    ->where('semester_id', $semesterId);
                              }
                          });
                })
                ->pluck('anonymous_code')
                ->all();

            if (!empty($conflicts)) {
                throw ValidationException::withMessages([
                    'student_exam_ids' => __('The following student exam IDs are already in use in this selection: :codes', ['codes' => implode(', ', $conflicts)]),
                ]);
            }
        }

        DB::transaction(function () use ($normalized) {
            foreach ($normalized as $examId => $code) {
                $exam = Exam::with(['type', 'scriptCode'])->find($examId);

                if (!$exam || !($exam->type->is_final ?? false)) {
                    continue;
                }

                if ($code === '') {
                    if ($exam->scriptCode) {
                        $exam->scriptCode->delete();
                    }
                    continue;
                }

                if ($exam->scriptCode) {
                    $exam->scriptCode->update([
                        'anonymous_code' => $code,
                    ]);
                    continue;
                }

                ExamScriptCode::create([
                    'exam_id' => $exam->id,
                    'anonymous_code' => $code,
                    'generated_by' => Auth::guard('web')->id(),
                    'generated_at' => Carbon::now(),
                ]);
            }
        });

        Flasher::addSuccess(__('Student exam IDs updated successfully.'), __('msg_success'));

        return redirect()->route('admin.student-exam-config.index', array_filter($filters));
    }

    protected function canSearch(array $data): bool
    {
        return !empty($data['selected_faculty'])
            && !empty($data['selected_program'])
            && !empty($data['selected_session'])
            && !empty($data['selected_subject'])
            && !empty($data['selected_type']);
    }
}
