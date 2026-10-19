<?php

namespace App\Services\Assessment;

use App\Models\ResitRequest;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Services\Academic\OutstandingCourses;

/**
 * Whether a mark may be changed to a given figure.
 *
 * There is one thing a mark change can silently break, and it is resits.
 * SubjectMarkingObserver settles a resit only while the request is approved or
 * scheduled; once it reaches `completed` nothing in this system ever revisits
 * its outcome, and `resits:close-sat` scans the same two states, so it cannot
 * repair one either. Raise a mark from 49 to 60 after the resit was closed and
 * the request keeps saying "failed" for ever, against a mark that says the
 * opposite.
 *
 * So a change that would contradict a resit already decided is refused, and the
 * refusal names the request so somebody can settle it properly on the resit
 * screen.
 *
 * Two things this must NOT do, or it breaks the resit process it is protecting:
 *
 *   - it must not refuse while the resit attempt itself is being marked. That
 *     is how a resit gets decided, and the mark arrives on a resit-semester
 *     enrolment.
 *   - it must not refuse on a request still open (requested, approved,
 *     scheduled). The observer settles those in the ordinary way.
 *
 * Used by the admin's direct edit and by the lecturer's exam-marking screen, so
 * the two cannot drift apart.
 */
class MarkChangeGuard
{
    protected OutstandingCourses $outstanding;

    public function __construct(OutstandingCourses $outstanding)
    {
        $this->outstanding = $outstanding;
    }

    /**
     * Why this mark may not be changed to that figure.
     *
     * An empty array means it may.
     *
     * @return array<int, string>
     */
    public function blockers(StudentEnroll $enrolment, ?Subject $subject, float $newTotal): array
    {
        if (!$subject) {
            return [];
        }

        // Marking the resit attempt is exactly how a resit is decided.
        if (optional($enrolment->semester)->is_resit) {
            return [];
        }

        $studentId = $enrolment->student_id;

        if (!$studentId) {
            return [];
        }

        $decided = ResitRequest::with('studentEnroll')
            ->where('subject_id', $subject->id)
            ->where('workflow_state', ResitRequest::STATE_COMPLETED)
            ->whereHas('studentEnroll', fn ($query) => $query->where('student_id', $studentId))
            ->get();

        if ($decided->isEmpty()) {
            return [];
        }

        $passes = round($newTotal) >= $this->outstanding->passMark($subject);
        $reasons = [];

        foreach ($decided as $request) {
            // Only a contradiction blocks. A resit that already agrees with the
            // new mark is no reason to refuse a correction.
            $agrees = $request->outcome === ($passes
                ? ResitRequest::OUTCOME_PASSED
                : ResitRequest::OUTCOME_FAILED);

            if ($agrees || $request->outcome === null) {
                continue;
            }

            $reasons[] = __('A resit for :subject was already completed on :date and recorded as :outcome. This mark would make it say the opposite. Settle resit #:id on the resit screen first.', [
                'subject' => $subject->title ?? ('#' . $subject->id),
                'date' => optional($request->completed_at)->format('d M Y') ?? __('an earlier date'),
                'outcome' => $request->outcome === ResitRequest::OUTCOME_PASSED ? __('passed') : __('failed'),
                'id' => $request->id,
            ]);
        }

        return $reasons;
    }

    public function allows(StudentEnroll $enrolment, ?Subject $subject, float $newTotal): bool
    {
        return $this->blockers($enrolment, $subject, $newTotal) === [];
    }

    /**
     * The verdict a figure produces, by the same rule the carry-over list uses.
     */
    public function passes(?Subject $subject, float $total): bool
    {
        return round($total) >= $this->outstanding->passMark($subject);
    }
}
