<?php

namespace App\Services\Academic;

use App\Models\ResitRequest;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use Illuminate\Support\Collection;

/**
 * What a student still owes, and why.
 *
 * This is the only place that decides whether a course is still outstanding, so
 * the figure the progression modal offers, the list course registration builds,
 * the count on the admin student page and the letter on a Senate sheet are the
 * same answer rather than four answers that happen to agree.
 *
 * They did not agree. Asked about one student who had failed four courses, resat
 * two and passed one, the modal said two, the admin page said two, course
 * registration said none, and the marks said three.
 *
 * Two rules decide everything here:
 *
 *   1. A course is VALIDATED when any published attempt reaches its pass mark.
 *      Otherwise it is OUTSTANDING. The mark is the record of what happened; no
 *      workflow state can make a failed course passed or a passed course failed.
 *
 *   2. An outstanding course is kept off the carry-over list only while a resit
 *      is still AWAITING ITS SITTING. A resit request is a request to sit a
 *      paper: once that paper has been sat and marked the request is spent,
 *      whatever the outcome, and the course goes back on the list if it failed.
 *
 * Rule 2 is what was missing. ResitRequest had no terminal state, so a request
 * stayed 'scheduled' for ever — including for the 168 resits that had been sat
 * and published months earlier — and every screen read that as "being handled"
 * and hid the course. A student could carry a failed course invisibly and
 * progress past it.
 *
 * Deliberately, this works out "awaiting its sitting" from the marks rather than
 * from the request's state alone, so it is right even where nobody has tidied
 * the workflow data up.
 */
class OutstandingCourses
{
    /** States in which a resit request has not yet been settled one way or another. */
    public const OPEN_STATES = [
        ResitRequest::STATE_REQUESTED,
        ResitRequest::STATE_AWAITING_PAYMENT,
        ResitRequest::STATE_FINANCE_REVIEW,
        ResitRequest::STATE_APPROVED,
        ResitRequest::STATE_SCHEDULED,
    ];

    /** The mark a subject must reach, honouring its own pass mark where it sets one. */
    public function passMark($subject): float
    {
        return (float) (optional($subject)->passing_marks ?: 50);
    }

    /**
     * Everything this student has not validated on this programme, whether or
     * not a resit is pending on it.
     *
     * Each row carries enough for every caller to render it its own way:
     *
     *   subject, subject_id, subject_code, subject_title, subject_type,
     *   credit_hours, pass_mark, best_marks, attempts, from_semester,
     *   from_semester_id, from_year, from_session, semester_type,
     *   semester_type_label, awaiting_resit, resit_request
     *
     * @return Collection<int, array> keyed by subject id
     */
    public function forEnrollment(StudentEnroll $enrollment): Collection
    {
        $student = $enrollment->student;

        if (!$student || !$enrollment->program_id) {
            return collect();
        }

        return $this->forStudent($student, (int) $enrollment->program_id, $enrollment->matricule);
    }

    /**
     * @return Collection<int, array> keyed by subject id
     */
    public function forStudent(Student $student, int $programId, ?string $matricule = null): Collection
    {
        $enrollments = $student->enrolls()
            ->where('program_id', $programId)
            ->when($matricule, fn ($q) => $q->where('matricule', $matricule))
            ->with(['semester', 'session', 'subjectMarks.subject'])
            ->orderBy('id')
            ->get();

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $validated = [];
        $failures = [];

        foreach ($enrollments as $enrollment) {
            foreach ($enrollment->subjectMarks ?? [] as $mark) {
                if (!$mark->subject || $mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
                    continue;
                }

                $marks = round((float) $mark->total_marks);

                if ($marks >= $this->passMark($mark->subject)) {
                    $validated[$mark->subject_id] = true;
                    continue;
                }

                // Every failed attempt is kept: the best of them is what the
                // student has to beat, and the count of them is what tells a
                // Senate how much trouble a course is giving them.
                $failures[$mark->subject_id][] = [
                    'mark' => $mark,
                    'marks' => $marks,
                    'enrollment' => $enrollment,
                ];
            }
        }

        $pending = $this->pendingResitSubjects($student, $enrollments);
        $rows = collect();

        foreach ($failures as $subjectId => $attempts) {
            if (isset($validated[$subjectId])) {
                continue;
            }

            // The first failure is where the course is owed from; a resit is a
            // second attempt at that same debt, not a new one.
            $first = $attempts[0];
            $semester = $first['enrollment']->semester;
            $subject = $first['mark']->subject;
            $type = $semester->semester_type ?? 1;

            $rows->put($subjectId, [
                'subject' => $subject,
                'subject_id' => $subjectId,
                'subject_code' => $subject->code ?? '',
                'subject_title' => $subject->title ?? '',
                'subject_type' => $subject->subject_type ?? null,
                'credit_hours' => $subject->credit_hour ?? 0,
                'pass_mark' => $this->passMark($subject),
                'best_marks' => max(array_column($attempts, 'marks')),
                'attempts' => count($attempts),
                'from_semester' => $semester->title ?? '',
                'from_semester_id' => $semester->id ?? null,
                'from_year' => $semester->year ?? 0,
                'from_session' => $first['enrollment']->session->title ?? '',
                'semester_type' => $type,
                'semester_type_label' => $type == 1 ? 'First Semester' : 'Second Semester',
                'awaiting_resit' => isset($pending[$subjectId]),
                'resit_request' => $pending[$subjectId] ?? null,
            ]);
        }

        return $rows;
    }

    /**
     * What the student must re-register: outstanding, and not waiting on a resit
     * they have yet to sit.
     *
     * @return Collection<int, array> keyed by subject id
     */
    public function carryOvers(StudentEnroll $enrollment): Collection
    {
        return $this->forEnrollment($enrollment)->reject(fn (array $row) => $row['awaiting_resit']);
    }

    /** Courses the student is still waiting to sit a resit for. */
    public function awaitingResit(StudentEnroll $enrollment): Collection
    {
        return $this->forEnrollment($enrollment)->filter(fn (array $row) => $row['awaiting_resit']);
    }

    /**
     * Subject ids with a resit the student has genuinely yet to sit.
     *
     * An open request whose paper has already been marked is spent, however its
     * workflow state was left. That is worked out here rather than trusted from
     * the state, because nothing ever moved a request out of 'scheduled'.
     *
     * @return array<int, ResitRequest>
     */
    protected function pendingResitSubjects(Student $student, Collection $enrollments): array
    {
        $requests = ResitRequest::whereIn('student_enroll_id', $enrollments->pluck('id'))
            ->whereIn('workflow_state', self::OPEN_STATES)
            ->orderBy('id')
            ->get();

        if ($requests->isEmpty()) {
            return [];
        }

        // Enrolments by semester, so "was the paper sat?" can be answered for
        // the semester the resit was scheduled into.
        $enrollmentsBySemester = $enrollments->keyBy('semester_id');

        $pending = [];

        foreach ($requests as $request) {
            if ($this->awaitingSitting($request, $enrollmentsBySemester)) {
                $pending[$request->subject_id] = $request;
            }
        }

        return $pending;
    }

    /**
     * Has this request's paper still to be sat?
     *
     * Scheduled into a semester the student has a published mark for in that
     * subject means it has been sat, and the request is spent. Scheduled into a
     * semester with no mark yet — or not scheduled anywhere yet — means it is
     * still to come.
     *
     * @param  Collection<int, StudentEnroll> $enrollmentsBySemester
     */
    public function awaitingSitting(ResitRequest $request, Collection $enrollmentsBySemester): bool
    {
        if (!in_array($request->workflow_state, self::OPEN_STATES, true)) {
            return false;
        }

        $sitting = $request->resit_enroll_id
            ? $enrollmentsBySemester->firstWhere('id', $request->resit_enroll_id)
            : ($request->resit_semester_id ? $enrollmentsBySemester->get($request->resit_semester_id) : null);

        if (!$sitting) {
            // Nowhere to sit it yet, so it is still to come.
            return true;
        }

        $sat = SubjectMarking::where('student_enroll_id', $sitting->id)
            ->where('subject_id', $request->subject_id)
            ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
            ->exists();

        return !$sat;
    }
}
