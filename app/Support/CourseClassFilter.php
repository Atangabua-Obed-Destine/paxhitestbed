<?php

namespace App\Support;

use App\Models\ClassRoutine;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\StaffAssignment;
use App\Models\Subject;
use App\Services\StaffAssignmentService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Which course is being looked at, and is this person allowed to look at it.
 *
 * One object so the screen, the PDF and the workbook cannot answer those two
 * questions differently. Three rules here are counter-intuitive enough that
 * changing them back would look like a tidy-up:
 *
 * 1. A COURSE IS NOT A PROGRAMME. student_attendances is keyed
 *    (student_enroll_id, subject_id, date) and carries no programme column.
 *    CPT 127 on this install is taught to twelve programmes, all Y1 second
 *    semester -- one room, one register, twelve rows in class_routines. So
 *    programme/semester/section are OPTIONAL narrowing here, never required.
 *    The older screens require a programme only because their filter cascade
 *    makes the dropdown mandatory, and that artefact splits one class into
 *    twelve lists.
 *
 * 2. ENROLMENT status = '1' MEANS "THE SEMESTER THE STUDENT IS IN NOW", NOT
 *    "a real enrolment". A student gets one student_enrolls row per semester
 *    they pass through; finishing a semester sets that row to '2'. On this
 *    install only 3 of 77 first-semester enrolments are still '1'. Filtering
 *    status = '1' therefore empties the class list of every course whose
 *    students have since moved on -- 42 subject+semester pairs here, holding
 *    real attendance. We scope by SESSION instead, which is what actually
 *    bounds an academic year.
 *
 * 3. THE SUBJECT IS CHECKED SERVER-SIDE, not just absent from the dropdown.
 *    A dropdown that omits a subject stops nobody editing the query string.
 */
class CourseClassFilter
{
    /** Relations the roster needs; loaded once, never per student. */
    public const RELATIONS = ['student', 'program', 'semester', 'section'];

    /** Selected values, keyed as the view expects them. */
    public array $selected = [];

    protected ?int $sessionId = null;
    protected ?int $subjectId = null;
    protected ?int $programId = null;
    protected ?int $semesterId = null;
    protected ?int $sectionId = null;
    protected ?string $from = null;
    protected ?string $to = null;

    /** True once the subject has been checked against what this user may see. */
    protected bool $authorised = false;

    /**
     * routines() answers "what do you teach", and the screen, the dropdown,
     * the authorisation check and the class picker all ask it. Resolving the
     * access rules each time cost 55 of the 72 queries a page took, none of
     * which can change inside one request.
     *
     * @var array<string, EloquentCollection>
     */
    protected array $routineCache = [];

    public static function fromRequest(Request $request): self
    {
        $filter = new self();

        $filter->sessionId = $filter->id($request->session) ?? optional(
            Session::where('status', '1')->where('current', '1')->first()
        )->id;

        $filter->subjectId = $filter->id($request->subject);
        $filter->programId = $filter->id($request->program);
        $filter->semesterId = $filter->id($request->semester);
        $filter->sectionId = $filter->id($request->section);
        $filter->from = $filter->date($request->from);
        $filter->to = $filter->date($request->to);

        $filter->selected = [
            'selected_session' => $filter->sessionId ?? '0',
            'selected_subject' => $filter->subjectId ?? '0',
            'selected_program' => $filter->programId ?? '0',
            'selected_semester' => $filter->semesterId ?? '0',
            'selected_section' => $filter->sectionId ?? '0',
            'selected_from' => $filter->from ?? '',
            'selected_to' => $filter->to ?? '',
        ];

        return $filter;
    }

    /**
     * '0' and '' both mean "not chosen". Casting them to int would make them 0,
     * which is a perfectly good id as far as a where clause is concerned.
     */
    protected function id($value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        return ctype_digit((string) $value) ? (int) $value : null;
    }

    protected function date($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $parsed = date_create((string) $value);

        return $parsed ? $parsed->format('Y-m-d') : null;
    }

    public function sessionId(): ?int
    {
        return $this->sessionId;
    }

    public function subjectId(): ?int
    {
        return $this->subjectId;
    }

    public function programId(): ?int
    {
        return $this->programId;
    }

    public function semesterId(): ?int
    {
        return $this->semesterId;
    }

    public function sectionId(): ?int
    {
        return $this->sectionId;
    }

    public function from(): ?string
    {
        return $this->from;
    }

    public function to(): ?string
    {
        return $this->to;
    }

    /** Enough has been chosen to draw a class list. */
    public function isSearching(): bool
    {
        return $this->sessionId !== null && $this->subjectId !== null;
    }

    /** True when the class has been narrowed below the whole course. */
    public function isNarrowed(): bool
    {
        return $this->programId !== null
            || $this->semesterId !== null
            || $this->sectionId !== null;
    }

    public function hasWindow(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    // ---------------------------------------------------------------- access

    /**
     * The class_routines rows this user may see for the chosen session.
     *
     * This is the single source of "what do you teach". Everything else --
     * the subject dropdown, the authorisation check, the dashboard tiles --
     * is derived from it, so a lecturer cannot reach through one door what
     * another door would refuse them.
     */
    public function routines(?int $userId = null): EloquentCollection
    {
        $userId = $userId ?? Auth::guard('web')->id();
        $key = (string) ($userId ?? 'guest');

        if (isset($this->routineCache[$key])) {
            return $this->routineCache[$key];
        }

        $query = ClassRoutine::with(['subject', 'program', 'semester', 'section'])
            ->where('status', '1');

        if ($this->sessionId !== null) {
            $query->where('session_id', $this->sessionId);
        }

        if (!$this->seesEverything($userId)) {
            if (StaffAssignmentService::hasAssignments($userId) || StaffAssignmentService::isHeadOfDepartment($userId)) {
                // A head of department or an assigned member of staff answers
                // for a whole area, not only the hours they personally teach.
                $query->whereIn('subject_id', StaffAssignmentService::getAccessibleCourseIds($userId));
            } else {
                $query->where('teacher_id', $userId);
            }
        }

        return $this->routineCache[$key] = $query->get();
    }

    protected function seesEverything(?int $userId): bool
    {
        return $userId !== null && StaffAssignmentService::isSuperAdmin($userId);
    }

    /** The subjects this user may draw a class list for, this session. */
    public function subjects(?int $userId = null): Collection
    {
        return $this->routines($userId)
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->sortBy('code')
            ->values();
    }

    /**
     * Refuse a subject this user does not teach.
     *
     * Returns the Subject on success. The dropdown already omits it; this is
     * what stops the query string.
     */
    public function authorise(?int $userId = null): ?Subject
    {
        if (!$this->isSearching()) {
            return null;
        }

        $subject = $this->subjects($userId)->firstWhere('id', $this->subjectId);

        if (!$subject) {
            abort(403, __('You do not teach this course this session.'));
        }

        $this->authorised = true;

        return $subject;
    }

    public function isAuthorised(): bool
    {
        return $this->authorised;
    }

    /**
     * The teaching groups of the chosen subject this user may see, as
     * program|semester|section keys -- or NULL meaning no constraint at all.
     *
     * This is what "the whole course" means, and it is NOT "everyone taking
     * this subject". A course can be split between lecturers: GECC101 here is
     * taught to eleven programmes by two of them, four and six. Taking the
     * subject whole would put one lecturer's class, and their attendance, in
     * front of the other.
     *
     * NULL is returned only for someone who sees everything, because 17
     * subjects on this install have students in a programme no routine covers.
     * Scoping those away from an administrator would hide real students;
     * showing them to a lecturer would hand them somebody else's class.
     */
    public function allowedGroups(?int $userId = null): ?array
    {
        $userId = $userId ?? Auth::guard('web')->id();

        if ($this->seesEverything($userId)) {
            return null;
        }

        return $this->routines($userId)
            ->where('subject_id', $this->subjectId)
            ->map(fn ($row) => $row->program_id . '|' . $row->semester_id . '|' . $row->section_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The distinct classes the chosen subject is taught to, for the "which
     * group?" control. Empty narrowing means all of them at once, which for a
     * service course taught by one person is the register they actually keep.
     */
    public function classesForSubject(?int $userId = null): array
    {
        if ($this->subjectId === null) {
            return [];
        }

        return $this->routines($userId)
            ->where('subject_id', $this->subjectId)
            ->unique(fn ($row) => $row->program_id . '|' . $row->semester_id . '|' . $row->section_id)
            ->map(fn ($row) => [
                'program_id' => $row->program_id,
                'semester_id' => $row->semester_id,
                'section_id' => $row->section_id,
                'program' => $row->program->title ?? '',
                'semester' => $row->semester->title ?? '',
                'section' => $row->section->title ?? '',
            ])
            ->sortBy(fn ($row) => $row['program'] . $row['semester'] . $row['section'])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------- describe

    /** Label => value, for the head of a document. */
    public function describe(?Subject $subject = null): array
    {
        $out = [];

        if ($subject) {
            $out[__('field_subject')] = trim(($subject->code ?? '') . ' — ' . ($subject->title ?? ''), ' —');
        }

        $out[__('field_session')] = $this->titleOf(Session::class, $this->sessionId);

        $out[__('field_program')] = $this->programId
            ? $this->titleOf(Program::class, $this->programId)
            : __('All programmes taking this course');

        $out[__('field_semester')] = $this->semesterId
            ? $this->titleOf(Semester::class, $this->semesterId)
            : __('All');

        $out[__('field_section')] = $this->sectionId
            ? $this->titleOf(Section::class, $this->sectionId)
            : __('All');

        $out[__('field_period')] = $this->windowLabel();

        return $out;
    }

    public function windowLabel(): string
    {
        if ($this->from && $this->to) {
            return date('d/m/Y', strtotime($this->from)) . ' – ' . date('d/m/Y', strtotime($this->to));
        }

        if ($this->from) {
            return __('From') . ' ' . date('d/m/Y', strtotime($this->from));
        }

        if ($this->to) {
            return __('Up to') . ' ' . date('d/m/Y', strtotime($this->to));
        }

        return __('Whole course to date');
    }

    protected function titleOf(string $model, ?int $id): string
    {
        if ($id === null) {
            return __('All');
        }

        return (string) ($model::find($id)->title ?? '');
    }

    /** The query string that reproduces this view, for the export buttons. */
    public function toQuery(): array
    {
        return array_filter([
            'session' => $this->sessionId,
            'subject' => $this->subjectId,
            'program' => $this->programId,
            'semester' => $this->semesterId,
            'section' => $this->sectionId,
            'from' => $this->from,
            'to' => $this->to,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
