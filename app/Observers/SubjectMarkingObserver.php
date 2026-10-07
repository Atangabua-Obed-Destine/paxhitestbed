<?php

namespace App\Observers;

use App\Models\ResitRequest;
use App\Models\SubjectMarking;
use Illuminate\Support\Facades\Log;

/**
 * Closes a resit request once its paper has been sat and marked.
 *
 * The resit workflow ended at 'scheduled': nothing moved a request on once the
 * student had actually sat the resit, so a spent request looked for ever like
 * one still to come. Every carry-over list reads that as "being handled" and
 * hides the course — which is how a student came to fail a resit and still be
 * offered progression with the course missing from his carry-overs.
 *
 * Hooked on the mark rather than on a screen, for the same reason FeeObserver
 * gives for doing this with fees: marks reach 'published' from exam publishing,
 * from senate deliberation and from the marking screens, and hooking the model
 * is what makes the behaviour whole rather than true of whichever paths someone
 * remembered to change.
 *
 * Nothing here decides whether a course is still owed — OutstandingCourses does
 * that from the marks, and deliberately does not depend on this observer having
 * run. This keeps the workflow data honest; it is not load-bearing.
 */
class SubjectMarkingObserver
{
    public function created(SubjectMarking $marking): void
    {
        $this->closeResitIfSat($marking);
    }

    public function updated(SubjectMarking $marking): void
    {
        if (!$marking->wasChanged(['workflow_state', 'total_marks'])) {
            return;
        }

        $this->closeResitIfSat($marking);
    }

    /**
     * A published mark on a resit enrolment settles whichever request sent the
     * student there.
     */
    protected function closeResitIfSat(SubjectMarking $marking): void
    {
        try {
            if ($marking->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
                return;
            }

            $enrollment = $marking->studentEnroll;

            if (!$enrollment) {
                return;
            }

            // Only a sitting settles a request, and a sitting happens in a resit
            // semester. A mark published for an ordinary semester is a first
            // attempt, not the answer to a request.
            $semester = $enrollment->semester;

            if (!$semester || !$semester->is_resit) {
                return;
            }

            $requests = ResitRequest::where('subject_id', $marking->subject_id)
                ->whereIn('workflow_state', [
                    ResitRequest::STATE_APPROVED,
                    ResitRequest::STATE_SCHEDULED,
                ])
                ->where(function ($query) use ($enrollment, $semester) {
                    $query->where('resit_enroll_id', $enrollment->id)
                        ->orWhere('resit_semester_id', $semester->id);
                })
                ->whereIn('student_enroll_id', $enrollment->student
                    ? $enrollment->student->enrolls()->pluck('id')
                    : [])
                ->get();

            if ($requests->isEmpty()) {
                return;
            }

            $passMark = (float) (optional($marking->subject)->passing_marks ?: 50);
            $passed = round((float) $marking->total_marks) >= $passMark;

            foreach ($requests as $request) {
                $request->workflow_state = ResitRequest::STATE_COMPLETED;
                $request->completed_at = now();
                $request->outcome = $passed
                    ? ResitRequest::OUTCOME_PASSED
                    : ResitRequest::OUTCOME_FAILED;

                // The enrolment the paper was actually sat under, which the
                // scheduling step never recorded.
                if (!$request->resit_enroll_id) {
                    $request->resit_enroll_id = $enrollment->id;
                }

                $request->save();
            }
        } catch (\Throwable $e) {
            // Closing the request must never stop a mark being published; the
            // carry-over rule reads the marks, so nothing is lost if this fails.
            Log::warning('Could not close the resit request for a published mark', [
                'subject_marking_id' => $marking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
