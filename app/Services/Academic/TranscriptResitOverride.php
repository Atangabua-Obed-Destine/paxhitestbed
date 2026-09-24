<?php

namespace App\Services\Academic;

use App\Models\MarksheetSetting;
use App\Models\ResitRequest;
use App\Models\SubjectMarking;

/**
 * A transcript-only reading of resit results, for the one school that asked for
 * it.
 *
 * With `resit_replaces_original` turned on in the transcript settings, a resit
 * the student passed is shown against the semester the course was first taken,
 * in place of the fail, and is no longer listed under the resit semester. A
 * resit failed again is left exactly as it is: the fail in its own semester and
 * the failed resit in the resit semester.
 *
 * Nothing here writes. It decides what a transcript should show as it renders,
 * so mark sheets, exam results, progression and the student portal go on reading
 * the marks as they are stored. With the setting off, every method reports
 * nothing and the transcript renders as it always did.
 */
class TranscriptResitOverride
{
    /** enrollment id => [subject id => SubjectMarking to show instead] */
    protected array $replacements = [];

    /** enrollment id => [subject id => true] for resit rows that have moved */
    protected array $hidden = [];

    protected bool $enabled = false;

    /**
     * Work out the replacements for one student's transcript.
     *
     * @param \Illuminate\Support\Collection $enrollments the enrolments this transcript covers
     * @param \Illuminate\Support\Collection $grades      the school's grading scale
     */
    public function build($enrollments, $grades): self
    {
        $this->replacements = [];
        $this->hidden = [];
        $this->enabled = (bool) optional(MarksheetSetting::where('status', '1')->first())->resit_replaces_original;

        if (!$this->enabled || $enrollments->isEmpty()) {
            return $this;
        }

        [$resitEnrollments, $regularEnrollments] = $enrollments->partition(
            fn ($enroll) => (bool) optional($enroll->semester)->is_resit
        );

        if ($resitEnrollments->isEmpty() || $regularEnrollments->isEmpty()) {
            return $this;
        }

        $requests = ResitRequest::whereIn('student_enroll_id', $regularEnrollments->pluck('id'))->get();

        // Oldest first, so a course resat twice ends up showing the newest pass.
        foreach ($resitEnrollments->sortBy('id') as $resitEnroll) {
            foreach ($this->publishedMarks($resitEnroll) as $resitMark) {
                if (!$this->isPass($resitMark, $grades)) {
                    continue; // Failed again: the transcript says so, in both places.
                }

                $parent = $this->parentEnrollment($resitMark, $resitEnroll, $regularEnrollments, $requests);

                if (!$parent) {
                    continue; // Nothing to replace, so nothing to move.
                }

                $this->replacements[$parent->id][$resitMark->subject_id] = $resitMark;
                $this->hidden[$resitEnroll->id][$resitMark->subject_id] = true;
            }
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** The mark to show in place of the original, or null to show what is there. */
    public function replacementFor($enrollId, $subjectId): ?SubjectMarking
    {
        return $this->replacements[$enrollId][$subjectId] ?? null;
    }

    /** True for a resit row whose result now appears in the original semester. */
    public function isHidden($enrollId, $subjectId): bool
    {
        return isset($this->hidden[$enrollId][$subjectId]);
    }

    /**
     * Has this enrolment a row left to print?
     *
     * Counted over the courses registered, which is what the transcript lists —
     * not over published marks. A resit semester whose marks are still being
     * entered has rows to show (the course, with a dash for the mark), and
     * leaving it off because nothing is published yet would hide a semester the
     * student is actually sitting.
     */
    public function hasVisibleRows($enroll): bool
    {
        $subjects = collect($enroll->subjects ?? []);

        if ($subjects->isNotEmpty()) {
            return $subjects->contains(fn ($subject) => !$this->isHidden($enroll->id, $subject->id));
        }

        foreach ($this->publishedMarks($enroll) as $mark) {
            if (!$this->isHidden($enroll->id, $mark->subject_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Which enrolment this resit belongs to.
     *
     * The resit request is the proper answer, by its resit enrolment or by the
     * session and semester it was scheduled into. Where neither points anywhere
     * — resit_enroll_id is filled only by resits taken since
     * ResitEnrollmentService began setting it, so it is empty for earlier
     * students — the course itself is the link: the student's own regular
     * enrolment holding a mark for the same subject.
     */
    protected function parentEnrollment($resitMark, $resitEnroll, $regularEnrollments, $requests)
    {
        foreach ($requests as $request) {
            if ((int) $request->subject_id !== (int) $resitMark->subject_id) {
                continue;
            }

            $pointsHere = (int) $request->resit_enroll_id === (int) $resitEnroll->id
                || ((int) $request->resit_session_id === (int) $resitEnroll->session_id
                    && (int) $request->resit_semester_id === (int) $resitEnroll->semester_id);

            if ($pointsHere) {
                $parent = $regularEnrollments->firstWhere('id', $request->student_enroll_id);

                if ($parent) {
                    return $parent;
                }
            }
        }

        // Newest first: a course taken more than once is replaced where it was
        // last sat before the resit.
        foreach ($regularEnrollments->sortByDesc('id') as $enroll) {
            foreach ($this->publishedMarks($enroll) as $mark) {
                if ((int) $mark->subject_id === (int) $resitMark->subject_id) {
                    return $enroll;
                }
            }
        }

        return null;
    }

    /**
     * A mark counts only once it is published and visible to the student, which
     * is the same test the transcript itself applies: a resit still being marked
     * changes nothing.
     */
    protected function publishedMarks($enroll)
    {
        return collect($enroll->subjectMarks ?? [])
            ->filter(fn ($mark) => $mark->workflow_state === SubjectMarking::STATE_PUBLISHED && $mark->is_visible_to_student);
    }

    /**
     * Passed, by the same rule the rest of the system uses: below 50 is a fail,
     * or the subject's own passing mark where one is set.
     *
     * Not "the grade carries points": the scale gives D (40-44.99) and D+
     * (45-49.99) points, yet those are the very marks students are sent to
     * resit — SemesterProgressionService counts anything under 50 as failed
     * (app/Services/Academic/SemesterProgressionService.php:231). Reading a 42
     * as a pass here would move a course into its original semester that the
     * student has not actually passed.
     */
    protected function isPass($mark, $grades): bool
    {
        $passMark = (float) (optional($mark->subject)->passing_marks ?: 50);

        return round((float) $mark->total_marks) >= $passMark;
    }
}
