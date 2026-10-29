<?php

namespace App\Services\Resit;

use App\Models\ResitRequest;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Academic\OutstandingCourses;
use App\Services\Academic\SemesterProgressionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raise a resit-semester enrolment for courses a student carried over.
 *
 * WHY THIS EXISTS. A student who did not validate a course has to sit it again,
 * and the mark has to be recorded somewhere. It cannot go on the original
 * enrolment: that one carries the fail, and the transcript has to go on showing
 * the course was registered in the year it was first taken. So the sitting gets
 * an enrolment of its own, in the resit semester, holding only the carried-over
 * courses.
 *
 * The school has been doing exactly this -- 66 students already hold that shape
 * -- but only ever as the end of the student-initiated request workflow. A
 * student who declined a resit, or a cohort being settled retroactively after a
 * remedial assessment, had no route at all. This is the admin-initiated route.
 *
 * WHAT IT MUST NOT DO, and how each is prevented:
 *
 *  - Charge the student. No fee assigner is called from here. Independently,
 *    all three copies of autoAssignProgramSemesterFees() return early on a
 *    resit semester, and no fee structure exists for one.
 *  - Reissue the matricule. It is copied from the origin enrolment, so
 *    students.student_id stays the canonical id and no sequence number is
 *    consumed. generateEnrollmentMatricule() is never called.
 *  - Disturb the live enrolment. The origin row's status, programme, subjects
 *    and marks are never written. Nor is students.program_id.
 *  - Become the student's current enrolment. A back-dated row is the newest by
 *    id, which is why EnrolmentOrder now decides that question by academic
 *    position instead. Nothing here depends on id order.
 *
 * WHY status = 2. The marking screens admit `whereIn('status', [1, 2])` --
 * "active and completed" -- in 28 places, and 2 is what the 36 existing first-
 * resit enrolments carry. A resit sitting is a completed phase, not the
 * student's current one. Note ResitEnrollmentService writes 0 instead, which
 * every one of those screens excludes; that is a defect in the request path,
 * not a pattern to follow.
 *
 * Everything is a transaction: the enrolment, its registrations and the resit
 * requests are one fact. The requests are not optional bookkeeping --
 * OutstandingCourses::awaitingSitting() and TranscriptResitOverride both key
 * off them, so without them the course stays on the carry-over list for ever
 * and a passed resit can never be shown against the year it was owed from.
 */
class ResitEnrolmentBackfill
{
    /** A resit sitting is a completed phase, so the row is marked completed. */
    public const ENROLMENT_STATUS = 2;

    public function __construct(
        protected OutstandingCourses $outstanding,
        protected SemesterProgressionService $progression
    ) {
    }

    /**
     * What raising the enrolment would do, without doing any of it.
     *
     * The admin confirms this, not a hope: the courses, the semester, the
     * matricule that will be reused, and anything that refuses the whole thing.
     *
     * @param  array<int>|null  $subjectIds  limit to these courses; null means all carried over
     * @return array{
     *     ok: bool,
     *     blockers: list<string>,
     *     notes: list<string>,
     *     origin: StudentEnroll,
     *     resit_semester: ?Semester,
     *     existing_enrolment: ?StudentEnroll,
     *     matricule: ?string,
     *     courses: list<array>,
     *     already_registered: list<array>,
     * }
     */
    public function preview(StudentEnroll $origin, ?array $subjectIds = null): array
    {
        $blockers = [];
        $notes = [];

        $originSemester = $origin->semester;
        $resitSemester = $originSemester ? $this->resitSemesterFor($originSemester) : null;

        if (!$originSemester) {
            $blockers[] = __('This enrolment has no semester, so there is nothing to raise a resit against.');
        } elseif ($originSemester->is_resit) {
            $blockers[] = __('This is already a resit enrolment. A resit of a resit is not something this screen can raise.');
        } elseif ((string) $origin->status === '0') {
            // Closed: the row the enrolment screens leave behind when a student
            // moves programme. Resitting a course on a programme they have left
            // would put marks against a programme nobody is on.
            $blockers[] = __('This enrolment is closed, which means the student has moved off it. Raise the resit against their current enrolment instead.');
        } elseif (!$resitSemester) {
            $blockers[] = __('There is no active resit semester for :semester (year :year, semester :type). Create one, with its parent semester set, before raising resit enrolments.', [
                'semester' => $originSemester->title,
                'year' => $originSemester->year,
                'type' => $originSemester->semester_type,
            ]);
        } elseif (!$resitSemester->parent_semester_id) {
            // Without a parent, attendance and continuous-assessment inheritance
            // silently does nothing, and TranscriptResitOverride loses the link
            // it prefers for deciding which semester a passed resit belongs to.
            $blockers[] = __('The resit semester ":semester" has no parent semester set, so it cannot inherit from the semester it shadows. Set it before raising resit enrolments.', [
                'semester' => $resitSemester->title,
            ]);
        } elseif ($resitSemester->id === $originSemester->id) {
            $blockers[] = __('The resit semester cannot be the same semester as the original.');
        }

        $courses = collect();
        $alreadyRegistered = collect();
        $existing = null;
        $complete = false;

        if ($blockers === [] && $resitSemester) {
            $existing = $this->existingResitEnrolment($origin, $resitSemester);

            $carried = $this->outstandingFrom($origin, $originSemester);

            if ($subjectIds !== null) {
                $carried = $carried->filter(fn ($row, $id) => in_array((int) $id, $subjectIds, true));
            }

            if ($carried->isEmpty()) {
                $blockers[] = $subjectIds === null
                    ? __('This student has no outstanding course from :semester.', ['semester' => $originSemester->title])
                    : __('None of the selected courses is outstanding from :semester.', ['semester' => $originSemester->title]);
            }

            $registered = $existing
                ? $existing->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->all()
                : [];

            foreach ($carried as $subjectId => $row) {
                if (in_array((int) $subjectId, $registered, true)) {
                    $alreadyRegistered->push($row);

                    continue;
                }

                $courses->push($row);
            }

            if ($existing) {
                $notes[] = __('A resit enrolment already exists for :semester (#:id). The courses below will be added to it rather than a second enrolment being created.', [
                    'semester' => $resitSemester->title,
                    'id' => $existing->id,
                ]);
            }

            if ($alreadyRegistered->isNotEmpty()) {
                $notes[] = __(':count course(s) are already registered on that resit enrolment and will be left alone.', [
                    'count' => $alreadyRegistered->count(),
                ]);
            }

            // Already done is not an error. The admin screens this drives get
            // refreshed and double-submitted, and raising the enrolment is
            // itself what makes these courses look provided for, so a repeat
            // must be a quiet no-op rather than a failure.
            if ($courses->isEmpty() && $alreadyRegistered->isNotEmpty()) {
                $complete = true;
                $notes[] = __('Every outstanding course from :semester is already registered on the resit enrolment. Nothing further is needed.', [
                    'semester' => $originSemester->title,
                ]);
            }

            // Overriding a decision the student already recorded is a real act,
            // so it is named rather than done quietly. On this installation
            // every one of the seven students needing a resit enrolment had
            // declined it, so this is the normal case, not the edge one.
            $overrides = $this->existingRequestStates($origin, $courses->pluck('subject_id')->all());

            foreach ($overrides as $state => $count) {
                $notes[] = __(':count course(s) already have a resit request marked ":state". Raising this will change them to "scheduled".', [
                    'count' => $count,
                    'state' => $state,
                ]);
            }

            $notes[] = __('No fee will be raised: a resit semester is excluded from fee assignment.');
            $notes[] = __('The matricule :matricule will be reused, not reissued.', [
                'matricule' => $origin->matricule ?? optional($origin->student)->student_id,
            ]);
        }

        return [
            'ok' => $blockers === [],
            // Nothing left to do, as opposed to nothing it may do.
            'complete' => $complete,
            'blockers' => $blockers,
            'notes' => $notes,
            'origin' => $origin,
            'origin_semester' => $originSemester,
            'resit_semester' => $resitSemester,
            'existing_enrolment' => $existing,
            'matricule' => $origin->matricule ?? optional($origin->student)->student_id,
            'courses' => $courses->values()->all(),
            'already_registered' => $alreadyRegistered->values()->all(),
        ];
    }

    /**
     * Raise it.
     *
     * Idempotent by design: run twice and the second run finds the enrolment
     * and the requests already there and adds only what is missing. The admin
     * screens this drives are the sort people refresh.
     *
     * @param  array<int>|null  $subjectIds
     * @return array{enrolment: StudentEnroll, registered: list<int>, requests: int, preview: array}
     */
    public function apply(StudentEnroll $origin, ?array $subjectIds, ?int $actorId): array
    {
        $preview = $this->preview($origin, $subjectIds);

        if (!$preview['ok']) {
            throw new \RuntimeException(implode(' ', $preview['blockers']));
        }

        /** @var Semester $resitSemester */
        $resitSemester = $preview['resit_semester'];
        $courses = collect($preview['courses']);

        // Already complete: there is nothing left to register, but the sitting
        // may still be missing what it inherits -- every enrolment raised here
        // before the inheritance was wired up is in exactly that state. The
        // inheritance checks before it writes, so running it on a sitting that
        // already has its CA and attendance changes nothing, which is what
        // makes running the screen again the repair.
        if ($preview['complete']) {
            $existing = $preview['existing_enrolment'];

            if ($existing) {
                DB::transaction(function () use ($existing, $resitSemester, $preview) {
                    $this->inheritFromParent($existing, $resitSemester, $preview['already_registered']);
                });
            }

            return [
                'enrolment' => $existing,
                'registered' => [],
                'requests' => 0,
                'preview' => $preview,
            ];
        }

        return DB::transaction(function () use ($origin, $resitSemester, $courses, $actorId, $preview) {
            $enrolment = $this->existingResitEnrolment($origin, $resitSemester);

            if (!$enrolment) {
                $enrolment = new StudentEnroll();
                $enrolment->student_id = $origin->student_id;
                $enrolment->program_id = $origin->program_id;
                $enrolment->session_id = $origin->session_id;
                $enrolment->semester_id = $resitSemester->id;
                $enrolment->section_id = $origin->section_id;

                // Copied, never generated. See the class docblock.
                $enrolment->matricule = $origin->matricule;

                $enrolment->status = self::ENROLMENT_STATUS;
                $enrolment->created_by = $actorId;
                $enrolment->updated_by = $actorId;
                $enrolment->save();
            }

            $subjectIdsToAdd = $courses->pluck('subject_id')->map(fn ($id) => (int) $id)->all();

            if ($subjectIdsToAdd !== []) {
                // syncWithoutDetaching, because student_enroll_subject has no
                // unique index: it is the only thing stopping a duplicate pair.
                $enrolment->subjects()->syncWithoutDetaching($subjectIdsToAdd);
            }

            $this->inheritFromParent(
                $enrolment,
                $resitSemester,
                array_merge($preview['already_registered'], $courses->all())
            );

            $requests = 0;

            foreach ($courses as $row) {
                $requests += $this->recordRequest($origin, $enrolment, $resitSemester, (int) $row['subject_id'], $actorId) ? 1 : 0;
            }

            // Courses already registered may still be missing their request --
            // the 170 scheduled requests on this installation all have a null
            // resit_enroll_id, so the link is worth repairing whenever we pass.
            foreach (collect($preview['already_registered']) as $row) {
                $this->recordRequest($origin, $enrolment, $resitSemester, (int) $row['subject_id'], $actorId);
            }

            return [
                'enrolment' => $enrolment->refresh(),
                'registered' => $subjectIdsToAdd,
                'requests' => $requests,
                'preview' => $preview,
            ];
        });
    }

    /**
     * Carry attendance and continuous assessment over from the semester the
     * resit semester shadows.
     *
     * This is the same call the request workflow and progressToResitSemester()
     * make, and it is what the rest of the system assumes has happened. Left
     * out, the sitting holds no exam row at all for the course -- so the
     * student is absent from the CA marking screen entirely, and the only
     * thing that brings a row into existence is saving exam attendance, which
     * continuous assessment otherwise never requires.
     *
     * Every step of the inheritance checks before it writes, so calling this
     * for a course that already carries its data does nothing.
     *
     * @param  list<array>  $courses  rows carrying a subject_id
     */
    protected function inheritFromParent(StudentEnroll $enrolment, Semester $resitSemester, array $courses): void
    {
        $courseIds = collect($courses)
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($courseIds === []) {
            return;
        }

        $this->progression->inheritParentSemesterData($enrolment, $resitSemester, $courseIds);
    }

    /**
     * The states of any resit requests already on these courses.
     *
     * @param  array<int>  $subjectIds
     * @return array<string, int>  state => how many
     */
    protected function existingRequestStates(StudentEnroll $origin, array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        return ResitRequest::where('student_enroll_id', $origin->id)
            ->whereIn('subject_id', $subjectIds)
            ->where('workflow_state', '!=', ResitRequest::STATE_SCHEDULED)
            ->get()
            ->groupBy('workflow_state')
            ->map(fn ($group) => $group->count())
            ->all();
    }

    /**
     * Can a resit be owed from this enrolment at all?
     *
     * A resit semester cannot itself owe one, and neither can a CLOSED
     * enrolment: `status = 0` is what the enrolment screens write to the old
     * row when a student moves programme, so it is a programme the student has
     * left. Two students here show exactly that -- a Year 1 Semester 1 row on
     * programme 2 with its own matricule, closed, beside the live one on
     * programme 12 -- and without this the same courses were offered twice, once
     * against a programme nobody is on any more.
     */
    protected function canOwe(StudentEnroll $enrolment): bool
    {
        return $enrolment->semester
            && !$enrolment->semester->is_resit
            && (string) $enrolment->status !== '0';
    }

    /**
     * The resit semester that shadows a regular one.
     *
     * Matched on year and semester type, which is the same match
     * ResitRequestWorkflowService makes, with the parent link preferred when
     * the resit semester declares one.
     */
    public function resitSemesterFor(Semester $semester): ?Semester
    {
        $byParent = Semester::where('is_resit', 1)
            ->where('status', 1)
            ->where('parent_semester_id', $semester->id)
            ->orderBy('id')
            ->first();

        if ($byParent) {
            return $byParent;
        }

        return Semester::where('is_resit', 1)
            ->where('status', 1)
            ->where('year', $semester->year)
            ->where('semester_type', $semester->semester_type)
            ->orderBy('id')
            ->first();
    }

    /**
     * The courses still owed from one semester.
     *
     * Read from OutstandingCourses, which is the single source of truth for
     * "what does this student still owe" and already reconciles attempts across
     * enrolments. Its docblock exists because four screens once disagreed on
     * the answer; re-deriving it here would make five.
     *
     * Note this is forEnrollment(), NOT carryOvers(). carryOvers() drops a
     * course whose resit is awaiting its sitting, which is right for "what must
     * the student re-register" and wrong for this screen in two ways:
     *
     *  - raising the enrolment is itself what makes a course awaiting, so a
     *    second run saw nothing owed and failed instead of doing nothing;
     *  - a course can have a scheduled request and no enrolment to sit it in.
     *    All 170 scheduled requests on this installation have a null
     *    resit_enroll_id, so that is the common case, not a corner.
     *
     * Whether a course is already provided for is decided here by what is
     * registered on the resit enrolment, which is the thing that actually
     * carries the sitting.
     *
     * @return Collection<int, array>
     */
    public function outstandingFrom(StudentEnroll $origin, Semester $originSemester): Collection
    {
        return $this->outstanding->forEnrollment($origin)
            ->filter(fn (array $row) => (int) ($row['from_semester_id'] ?? 0) === (int) $originSemester->id);
    }

    /** Every regular enrolment of this student that still owes a course. */
    public function worklistFor(Student $student): Collection
    {
        $rows = collect();

        $enrolments = $student->enrolls()
            ->with(['semester', 'session', 'program'])
            ->get()
            ->filter(fn (StudentEnroll $e) => $this->canOwe($e));

        foreach ($enrolments as $origin) {
            $resitSemester = $this->resitSemesterFor($origin->semester);
            $carried = $this->outstandingFrom($origin, $origin->semester);

            if ($carried->isEmpty()) {
                continue;
            }

            $existing = $resitSemester ? $this->existingResitEnrolment($origin, $resitSemester) : null;
            $registered = $existing
                ? $existing->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->all()
                : [];

            $missing = $carried->reject(fn ($row, $id) => in_array((int) $id, $registered, true));

            if ($missing->isEmpty()) {
                continue;
            }

            $rows->push([
                'origin' => $origin,
                'resit_semester' => $resitSemester,
                'existing_enrolment' => $existing,
                'courses' => $missing->values()->all(),
                'course_count' => $missing->count(),
            ]);
        }

        return $rows;
    }

    /**
     * The resit enrolment for this student in this semester, if there is one.
     *
     * Matched on the same coordinates both existing creators use, and on the
     * matricule as well, so a student enrolled on two programmes cannot have
     * one programme's resit row picked up for the other's.
     */
    protected function existingResitEnrolment(StudentEnroll $origin, Semester $resitSemester): ?StudentEnroll
    {
        return StudentEnroll::where('student_id', $origin->student_id)
            ->where('program_id', $origin->program_id)
            ->where('semester_id', $resitSemester->id)
            ->when($origin->matricule, fn ($q) => $q->where('matricule', $origin->matricule))
            ->orderBy('id')
            ->first();
    }

    /**
     * Record that this course is being sat in the resit semester.
     *
     * Keyed on the ORIGINAL enrolment and the subject, because that is where
     * the debt is owed from and how every reader looks it up. An existing
     * request is updated rather than duplicated, which is also how the missing
     * resit_enroll_id on older requests gets repaired.
     *
     * @return bool whether a row was created
     */
    protected function recordRequest(
        StudentEnroll $origin,
        StudentEnroll $resitEnrolment,
        Semester $resitSemester,
        int $subjectId,
        ?int $actorId
    ): bool {
        $request = ResitRequest::where('student_enroll_id', $origin->id)
            ->where('subject_id', $subjectId)
            ->orderByDesc('id')
            ->first();

        $created = false;
        $previousState = null;

        if (!$request) {
            $request = new ResitRequest();
            $request->student_enroll_id = $origin->id;
            $request->subject_id = $subjectId;
            $request->session_id = $origin->session_id;
            $created = true;
        } elseif ($request->workflow_state !== ResitRequest::STATE_SCHEDULED) {
            $previousState = $request->workflow_state;
        }

        $request->resit_session_id = $resitEnrolment->session_id;
        $request->resit_semester_id = $resitSemester->id;
        $request->resit_enroll_id = $resitEnrolment->id;
        $request->workflow_state = ResitRequest::STATE_SCHEDULED;
        $request->state_changed_at = now();
        $request->state_changed_by = $actorId;

        // Raised by the school after the fact, so there is nothing to pay. A
        // fee is never created here; the amount is recorded as waived so the
        // finance side of the workflow has an answer rather than a null.
        if ($created || (float) $request->fee_amount == 0.0) {
            $request->fee_amount = 0;
            $request->payment_status = ResitRequest::PAYMENT_WAIVED;
        }

        $request->approved_by = $request->approved_by ?: $actorId;
        $request->approved_at = $request->approved_at ?: now();

        // The note is appended, never replaced. Where the student had declined
        // the resit, "Student declined to resit this course" is the record of a
        // decision they made, and the school overriding it afterwards is a
        // second fact rather than a correction of the first. Losing the
        // original would leave no trace of why the request existed.
        $note = $previousState === null
            ? __('Resit enrolment raised by the school for a carried-over course.')
            : __('Resit enrolment raised by the school for a carried-over course (was ":state").', [
                'state' => $previousState,
            ]);

        $request->notes = trim(($request->notes ? $request->notes . "\n" : '') . $note);

        $request->save();

        return $created;
    }
}
