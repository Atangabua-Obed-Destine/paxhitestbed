<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Session;
use App\Models\StudentEnroll;
use App\Models\StudentExamCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admission → HND Exam Codes.
 *
 * CNOENC sends the school a pre-registration code list each year: one national
 * code per student sitting the HND exam, grouped by programme and level. The
 * codes are recorded here, a whole class at a time, and the same document is
 * printed back out of the system.
 */
class StudentExamCodeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:student-exam-code-view', ['only' => ['index', 'pdf']]);
        $this->middleware('permission:student-exam-code-manage', ['only' => ['save']]);
    }

    public function index(Request $request)
    {
        [$sessionId, $level, $programId] = $this->filters($request);

        $students = $this->studentsForSitting($sessionId, $level, $programId);
        $codes = $this->codesForSitting($sessionId, $level);

        return view('admin.student-exam-codes.index', [
            'title' => __('HND Exam Codes'),
            'sessions' => Session::orderBy('title', 'desc')->get(),
            'programs' => Program::where('status', '1')->orderBy('title', 'asc')->get(),
            'selected_session' => $sessionId,
            'selected_level' => $level,
            'selected_program' => $programId,
            'groups' => $students,
            'codes' => $codes,
            'total_students' => $students->sum(fn ($group) => $group->count()),
            'total_coded' => $students->sum(fn ($group) => $group->filter(fn ($row) => isset($codes[$row->student_id]))->count()),
        ]);
    }

    /**
     * Record the whole column at once.
     *
     * A box left empty for a student who has a code removes that code — the
     * screen says so before saving. Everything happens in one transaction, so a
     * rejected code leaves the whole save untouched rather than half-applied.
     */
    public function save(Request $request)
    {
        [$sessionId, $level] = $this->filters($request);

        $request->validate([
            'session_id' => ['required', 'exists:sessions,id'],
            'level' => ['required', 'integer', 'min:1', 'max:2'],
            'codes' => ['array'],
        ]);

        $userId = Auth::guard('web')->id();
        $saved = $removed = 0;
        $rejected = [];
        $unusual = [];

        // A clash throws, which rolls the whole save back and sends the admin
        // back to the screen with the problem named. Anything else — a database
        // fault, say — is left to surface as itself rather than being dressed up
        // as a rejected code.
        DB::transaction(function () use ($request, $sessionId, $level, $userId, &$saved, &$removed, &$rejected, &$unusual) {
            foreach ((array) $request->input('codes', []) as $studentId => $rawCode) {
                $studentId = (int) $studentId;
                $code = StudentExamCode::normalise($rawCode);
                $existing = StudentExamCode::forSitting($sessionId, $level)->where('student_id', $studentId)->first();

                if ($code === '') {
                    if ($existing) {
                        $existing->delete();
                        $removed++;
                    }

                    continue;
                }

                if ($existing && $existing->code === $code) {
                    continue;
                }

                // The commission never issues one code twice, so a code that
                // already belongs to somebody else is a typing mistake.
                $owner = StudentExamCode::with('student')->where('code', $code)
                    ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                    ->first();

                if ($owner) {
                    $rejected[] = __(':code already belongs to :student', [
                        'code' => $code,
                        'student' => optional($owner->student)->student_id ?? __('another student'),
                    ]);

                    continue;
                }

                if (!StudentExamCode::looksLikeCommissionCode($code)) {
                    $unusual[] = $code;
                }

                StudentExamCode::updateOrCreate(
                    ['student_id' => $studentId, 'session_id' => $sessionId, 'level' => $level],
                    [
                        'code' => $code,
                        'program_id' => optional($this->enrollmentFor($studentId, $sessionId, $level))->program_id,
                        'created_by' => $existing ? $existing->created_by : $userId,
                        'updated_by' => $userId,
                    ]
                );

                $saved++;
            }

            if ($rejected !== []) {
                // Nothing is half-saved: the admin fixes the clash and saves again.
                throw ValidationException::withMessages(['codes' => $rejected]);
            }
        });

        if ($saved || $removed) {
            Flasher::addSuccess(__(':saved code(s) recorded, :removed removed.', ['saved' => $saved, 'removed' => $removed]), __('msg_success'));
        } else {
            Flasher::addInfo(__('Nothing changed.'), __('msg_info'));
        }

        if ($unusual !== []) {
            Flasher::addWarning(__('Saved, but these are not in the commission\'s usual format (HND and 10 characters): :codes', [
                'codes' => implode(', ', $unusual),
            ]), __('msg_warning'));
        }

        return redirect()->route('admin.student-exam-codes.index', $request->only('session_id', 'level', 'program_id'));
    }

    /** The commission's list, as a PDF. */
    public function pdf(Request $request)
    {
        [$sessionId, $level, $programId] = $this->filters($request);

        $codes = $this->codesForSitting($sessionId, $level);
        $groups = $this->codedStudentsForSitting($sessionId, $level, $programId);
        $session = Session::find($sessionId);

        $pdf = Pdf::loadView('admin.student-exam-codes.pdf', [
            'groups' => $groups,
            'codes' => $codes,
            'level' => $level,
            'session' => $session,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('HND-CODES-' . str_replace('/', '-', $session->title ?? 'list') . '-L' . $level . '.pdf');
    }

    /** @return array{0:int,1:int,2:int} session, level, programme (0 = all) */
    protected function filters(Request $request): array
    {
        $sessionId = (int) ($request->input('session_id')
            ?: optional(Session::where('status', '1')->orderBy('id', 'desc')->first())->id
            ?: optional(Session::orderBy('id', 'desc')->first())->id);

        $level = (int) $request->input('level', 1);
        $level = in_array($level, [1, 2], true) ? $level : 1;

        return [$sessionId, $level, (int) $request->input('program_id', 0)];
    }

    /**
     * The students sitting the exam that year at that level, grouped by
     * programme and ordered by name — the order the commission's list uses.
     *
     * Level comes from the enrolment's semester `year`, the same field Form A2
     * filters on. A student enrolled in both a regular and a resit semester is
     * one student, so only their newest enrolment counts.
     */
    protected function studentsForSitting(int $sessionId, int $level, int $programId = 0)
    {
        $enrollments = StudentEnroll::with(['student', 'program'])
            ->where('session_id', $sessionId)
            ->whereHas('semester', fn ($query) => $query->where('year', $level))
            ->whereHas('student', fn ($query) => $query->where('status', '!=', 0))
            ->when($programId, fn ($query) => $query->where('program_id', $programId))
            ->orderBy('id', 'desc')
            ->get()
            ->unique('student_id');

        return $enrollments
            ->sortBy(fn ($enroll) => strtoupper(trim(optional($enroll->student)->first_name . ' ' . optional($enroll->student)->last_name)))
            ->groupBy(fn ($enroll) => optional($enroll->program)->title ?? __('Unassigned'))
            ->sortKeys();
    }

    /**
     * What the printed list holds: only students who have a code, because the
     * commission's list is of students actually registered for the exam.
     * Programmes where nobody has a code yet are left off it entirely.
     */
    protected function codedStudentsForSitting(int $sessionId, int $level, int $programId = 0)
    {
        $codes = $this->codesForSitting($sessionId, $level);

        return $this->studentsForSitting($sessionId, $level, $programId)
            ->map(fn ($group) => $group->filter(fn ($row) => isset($codes[$row->student_id]))->values())
            ->filter(fn ($group) => $group->isNotEmpty());
    }

    /** The codes already recorded for that sitting, keyed by student. */
    protected function codesForSitting(int $sessionId, int $level)
    {
        return StudentExamCode::forSitting($sessionId, $level)->pluck('code', 'student_id')->all();
    }

    protected function enrollmentFor(int $studentId, int $sessionId, int $level)
    {
        return StudentEnroll::where('student_id', $studentId)
            ->where('session_id', $sessionId)
            ->whereHas('semester', fn ($query) => $query->where('year', $level))
            ->orderBy('id', 'desc')
            ->first();
    }
}
