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
     * The submission is read as the state the column should end up in, not as a
     * list of changes to apply box by box. That matters when a code moves from
     * one student to another: worked through in page order, giving the code to
     * the right student is rejected because the wrong student still holds it,
     * even though the same save clears them — so the save could never go
     * through, and the clearing was rolled back with it. Deciding the whole
     * column first, then writing it, makes the result the same whichever way
     * round the two students appear on the page, and lets two students swap
     * codes in one save.
     *
     * A clash that a save does not itself resolve is still refused outright:
     * nothing is half-written.
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
        $submitted = collect((array) $request->input('codes', []))
            ->mapWithKeys(fn ($code, $studentId) => [(int) $studentId => StudentExamCode::normalise($code)]);

        $current = StudentExamCode::forSitting($sessionId, $level)->get()->keyBy('student_id');

        // What each student should end up with.
        $removals = $submitted->filter(fn ($code) => $code === '')->keys()
            ->filter(fn ($studentId) => $current->has($studentId));
        $wanted = $submitted->filter(fn ($code) => $code !== '')
            ->filter(fn ($code, $studentId) => optional($current->get($studentId))->code !== $code);

        $rejected = [];

        // Two students given the same code in one save.
        foreach ($wanted->groupBy(fn ($code) => $code) as $code => $students) {
            if ($students->count() > 1) {
                $rejected[] = __(':code was typed for :count students in this save.', ['code' => $code, 'count' => $students->count()]);
            }
        }

        // A code already held by someone this save does not clear. Codes held
        // elsewhere — another year or level — count too: the commission never
        // issues one twice.
        //
        // Filtered by hand rather than with only(): on an Eloquent collection
        // only() picks by model id, not by the key the collection is built on,
        // and would quietly free nothing.
        $touched = $removals->merge($wanted->keys())->all();
        $freed = $current->filter(fn ($row) => in_array((int) $row->student_id, $touched, true))
            ->pluck('code')->all();

        foreach ($wanted as $studentId => $code) {
            $owner = StudentExamCode::with('student')->where('code', $code)
                ->where('student_id', '!=', $studentId)
                ->first();

            if ($owner && !in_array($code, $freed, true)) {
                $rejected[] = __(':code already belongs to :student', [
                    'code' => $code,
                    'student' => optional($owner->student)->student_id ?? __('another student'),
                ]);
            }
        }

        if ($rejected !== []) {
            // Refused before anything is written, so nothing is half-saved.
            throw ValidationException::withMessages(['codes' => array_unique($rejected)]);
        }

        $unusual = $wanted->reject(fn ($code) => StudentExamCode::looksLikeCommissionCode($code))->values()->all();

        // Written in one go: every code leaving first, so a code moving between
        // two students never meets itself in the unique index.
        DB::transaction(function () use ($removals, $wanted, $current, $sessionId, $level, $userId) {
            StudentExamCode::forSitting($sessionId, $level)
                ->whereIn('student_id', $removals->merge($wanted->keys())->all())
                ->delete();

            foreach ($wanted as $studentId => $code) {
                StudentExamCode::create([
                    'student_id' => $studentId,
                    'session_id' => $sessionId,
                    'level' => $level,
                    'code' => $code,
                    'program_id' => optional($this->enrollmentFor($studentId, $sessionId, $level))->program_id,
                    // Whoever first recorded a code for this student keeps that
                    // credit; only the change is this user's.
                    'created_by' => optional($current->get($studentId))->created_by ?? $userId,
                    'updated_by' => $userId,
                ]);
            }
        });

        if ($wanted->isNotEmpty() || $removals->isNotEmpty()) {
            Flasher::addSuccess(__(':saved code(s) recorded, :removed removed.', [
                'saved' => $wanted->count(), 'removed' => $removals->count(),
            ]), __('msg_success'));
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
