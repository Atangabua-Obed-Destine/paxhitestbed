<?php

namespace App\Services\Student;

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Models\ResitRequest;
use App\Services\Academic\OutstandingCourses;
use App\Services\Academic\SemesterProgressionService;

class ProgressionEligibilityService
{
    protected $progressionService;

    public function __construct(SemesterProgressionService $progressionService)
    {
        $this->progressionService = $progressionService;
    }

    /**
     * Check if student is eligible for progression (regular or resit)
     * Checks the currently selected enrollment (respects session selection)
     * 
     * @param Student $student
     * @param int|null $selectedEnrollmentId Optional specific enrollment to check
     * @return array
     */
    public function checkEligibility(Student $student, ?int $selectedEnrollmentId = null): array
    {
        // If a specific enrollment is selected (from session), use that
        if ($selectedEnrollmentId) {
            $enrollment = $student->enrolls()
                ->where('id', $selectedEnrollmentId)
                ->where('status', 1)
                ->with(['semester', 'session', 'program'])
                ->first();
            
            if (!$enrollment) {
                return [
                    'eligible' => false,
                    'type' => null,
                    'message' => 'Selected enrollment not found or inactive',
                ];
            }
            
            // Check this specific enrollment
            return $this->checkEnrollmentEligibility($enrollment);
        }
        
        // Fallback: Get ALL active enrollments (for multi-program students)
        // Order by ID descending so newest enrollments are checked first
        $activeEnrollments = $student->enrolls()
            ->where('status', 1)
            ->with(['semester', 'session', 'program'])
            ->orderBy('id', 'desc')
            ->get();
        
        if ($activeEnrollments->isEmpty()) {
            return [
                'eligible' => false,
                'type' => null,
                'message' => 'No active enrollment found',
                'details' => [],
            ];
        }

        // Check each active enrollment for eligibility (newest first)
        // Collect all reasons if none are eligible
        $allReasons = [];
        foreach ($activeEnrollments as $enrollment) {
            $result = $this->checkEnrollmentEligibility($enrollment);
            if ($result['eligible']) {
                return $result;
            }
            $allReasons[] = $result;
        }

        // A student held back only because the year ahead is not open yet is
        // told exactly that. This used to flatten every answer into a generic
        // "not yet eligible", which dropped the paused flag and the school's
        // note along with it — so the screen said one thing for a student with
        // a single enrolment and another for a student with two.
        foreach ($allReasons as $reason) {
            if (($reason['paused'] ?? false) === true) {
                return $reason;
            }
        }

        // Return the most relevant reason from the first enrollment
        $primaryReason = $allReasons[0] ?? [];
        return [
            'eligible' => false,
            'type' => null,
            'message' => $primaryReason['message'] ?? 'Not yet eligible for progression',
            'details' => $primaryReason,
        ];
    }
    
    /**
     * Check eligibility for a specific enrollment
     */
    protected function checkEnrollmentEligibility(StudentEnroll $enrollment): array
    {
        // Detect carry-over courses for context
        $carryOverCourses = $this->getCarryOverCourses($enrollment);

        // Check for resit semester progression first
        $resitEligibility = $this->checkResitSemesterEligibility($enrollment);

        // Check for regular semester progression
        $regularEligibility = $this->checkRegularSemesterEligibility($enrollment);

        // Would they otherwise be able to move? Then the only thing stopping
        // them is the year ahead not being ready, and that is what they are
        // told — rather than "not yet eligible", which would be untrue.
        //
        // Checked here because this is the one method both the student's modal
        // and ProgressionController@proceed go through, so the screen and the
        // server cannot disagree about it.
        //
        // A student who could not progress anyway keeps their own reason: it is
        // true, it is more use to them, and the paused panel's reassurance that
        // their results are fine would not be.
        if ($resitEligibility['eligible'] || $regularEligibility['eligible']) {
            if ($paused = $this->progressionPaused($enrollment)) {
                $paused['carry_over_courses'] = $carryOverCourses;

                return $paused;
            }
        }

        if ($resitEligibility['eligible']) {
            $resitEligibility['carry_over_courses'] = $carryOverCourses;
            return $resitEligibility;
        }

        if ($regularEligibility['eligible']) {
            $regularEligibility['carry_over_courses'] = $carryOverCourses;
            if (!empty($carryOverCourses)) {
                $regularEligibility['summary']['carry_over_courses'] = $carryOverCourses;
            }
            return $regularEligibility;
        }

        // Determine the most useful reason to show the student
        $reason = $regularEligibility['reason'] ?? $resitEligibility['reason'] ?? 'Not yet eligible for progression';

        // What is actually standing in their way, and whose move it is.
        //
        // Taken from both checks: a student in a regular semester is held up by
        // the resit check, one already in a resit semester by the regular
        // check's look back at the semester it belongs to. Reading only the
        // first missed the second entirely.
        $blockers = $this->blockersFrom($resitEligibility, $regularEligibility);

        return [
            'eligible' => false,
            'type' => null,
            'message' => $reason,
            'enrollment_id' => $enrollment->id,
            'program_name' => $enrollment->program->title ?? 'N/A',
            'current_semester' => $enrollment->semester->title ?? 'N/A',
            'current_session' => $enrollment->session->title ?? 'N/A',
            'resit_check' => $resitEligibility,
            'regular_check' => $regularEligibility,
            'carry_over_courses' => $carryOverCourses,

            // Named at the top level so the portal does not have to go digging
            // through resit_check to find out whether the student can do
            // something about this.
            'blockers' => $blockers,
            'blocked_by_resits' => !empty($blockers),
            'action_required' => collect($blockers)->contains(fn ($b) => $b['student_can_act']),
        ];
    }

    /**
     * The failed courses standing between this student and progression.
     *
     * A failed course stops progression until it is settled one way or the
     * other — resit requested and paid for, or declined. Until then the portal
     * said only "not yet eligible", which tells a student nothing about what to
     * do, and the thing they had to do lived on a different page they had no
     * reason to visit.
     *
     * Each blocker says whose move it is, because the two cases need different
     * things from the student: one is a decision they have to make, the other
     * is a wait they should not be chasing.
     *
     * @param  array<string, mixed> ...$checks the eligibility checks to read
     * @return array<int, array<string, mixed>>
     */
    protected function blockersFrom(array ...$checks): array
    {
        $blockers = [];
        $seen = [];

        $courses = [];
        foreach ($checks as $check) {
            foreach ($check['unresolved_courses'] ?? [] as $course) {
                // The same course can be reported by both checks; it is one
                // blocker, not two.
                $key = $course['subject_id'] ?? ($course['subject_code'] ?? null);

                if ($key !== null && isset($seen[$key])) {
                    continue;
                }

                if ($key !== null) {
                    $seen[$key] = true;
                }

                $courses[] = $course;
            }
        }

        foreach ($courses as $course) {
            $status = $course['status'] ?? 'no_request';
            $state = $course['workflow_state'] ?? null;

            if ($status === 'no_request') {
                $blockers[] = [
                    'subject_code' => $course['subject_code'] ?? '',
                    'subject_title' => $course['subject_title'] ?? '',
                    'status' => $status,
                    'label' => __('Awaiting your decision'),
                    'what_to_do' => __('Ask to resit this course, or decline it.'),
                    'student_can_act' => true,
                ];
                continue;
            }

            // Raised, but not yet settled. Whether that is on the student or on
            // the school decides what they are told to do about it.
            $awaitingPayment = $state === \App\Models\ResitRequest::STATE_AWAITING_PAYMENT;

            $blockers[] = [
                'subject_code' => $course['subject_code'] ?? '',
                'subject_title' => $course['subject_title'] ?? '',
                'status' => $status,
                'label' => $awaitingPayment ? __('Awaiting your payment') : __('With the school'),
                'what_to_do' => $awaitingPayment
                    ? __('Pay the resit fee, then upload or present your receipt.')
                    : __('Your request is being reviewed. There is nothing for you to do.'),
                'student_can_act' => $awaitingPayment,
            ];
        }

        return $blockers;
    }

    /**
     * Check if student is eligible for resit semester progression
     */
    protected function checkResitSemesterEligibility(StudentEnroll $enrollment): array
    {
        $result = $this->progressionService->checkResitSemesterProgression($enrollment);
        
        if (!$result['can_progress']) {
            return [
                'eligible' => false,
                'type' => null,
                'reason' => $result['reason'] ?? null,
                'unresolved_courses' => $result['unresolved_courses'] ?? [],
            ];
        }

        // Get summary data
        $summary = $this->buildResitProgressionSummary($enrollment, $result);

        return [
            'eligible' => true,
            'type' => 'resit',
            'enrollment_id' => $enrollment->id,
            'program_id' => $enrollment->program_id,
            'program_name' => $enrollment->program->title ?? 'N/A',
            'target_session_id' => $result['resit_session_id'],
            'target_semester_id' => $result['resit_semester']->id,
            'target_semester_title' => $result['resit_semester']->title,
            'summary' => $summary,
        ];
    }

    /**
     * The year this student would be entering is not open for progression.
     *
     * Returns the answer to give them, or null when the way is clear.
     *
     * A student is told which year is closed and what the school has said about
     * it, rather than being given the ordinary "not yet eligible" — they may
     * have met every requirement, and being told they have not would be untrue.
     *
     * @return array<string, mixed>|null
     */
    protected function progressionPaused(StudentEnroll $enrollment): ?array
    {
        $target = $this->progressionService->targetSessionFor($enrollment);

        if (!$target || $target->allowsProgression()) {
            return null;
        }

        return [
            'eligible' => false,
            'paused' => true,
            'type' => null,
            'enrollment_id' => $enrollment->id,
            'program_name' => $enrollment->program->title ?? 'N/A',
            'target_session_title' => $target->title,
            'message' => __('Progression into :session is paused for now.', ['session' => $target->title]),
            'paused_note' => $target->progressionNote(),
            'summary' => [
                'program_name' => $enrollment->program->title ?? 'N/A',
                'current_semester' => $enrollment->semester->title ?? 'N/A',
                'current_session' => $enrollment->session->title ?? 'N/A',
            ],
        ];
    }

    /**
     * Check if student is eligible for regular semester progression
     */
    protected function checkRegularSemesterEligibility(StudentEnroll $enrollment): array
    {
        $result = $this->progressionService->checkProgressionEligibility($enrollment);
        
        if (!$result['eligible'] || !$result['next_semester']) {
            return [
                'eligible' => false,
                'type' => null,
                'reason' => $result['reason'] ?? null,
                // A student already sitting in a resit semester can still be
                // held back by a failed course from the semester it belongs to.
                // That blocker is reported here rather than by the resit check,
                // and without carrying it out the portal could say something was
                // wrong but never which course.
                'unresolved_courses' => $result['unresolved_courses'] ?? [],
            ];
        }

        // Get summary data
        $summary = $this->buildRegularProgressionSummary($enrollment, $result);

        // Where they actually land. This used to report the session they were
        // already in, which was wrong whenever the institution had opened a new
        // academic year — the student was moved into it without being told.
        $targetSession = $this->progressionService->targetSessionFor($enrollment);
        $newSession = $this->progressionService->entersNewSession($enrollment);

        return [
            'eligible' => true,
            'type' => 'regular',
            'enrollment_id' => $enrollment->id,
            'program_id' => $enrollment->program_id,
            'program_name' => $enrollment->program->title ?? 'N/A',
            'target_session_id' => $targetSession->id ?? $enrollment->session_id,
            'target_session_title' => $targetSession->title ?? null,
            'enters_new_session' => $newSession,
            'target_semester_id' => $result['next_semester']->id,
            'target_semester_title' => $result['next_semester']->title,
            'summary' => $summary,
        ];
    }

    /**
     * Build summary for resit semester progression
     */
    protected function buildResitProgressionSummary(StudentEnroll $enrollment, array $result): array
    {
        $scheduledCourses = $result['scheduled_courses'] ?? [];
        $failedCourses = $result['failed_courses'] ?? [];
        
        return [
            'program_name' => $enrollment->program->title ?? 'N/A',
            'current_semester' => $enrollment->semester->title ?? 'N/A',
            'current_session' => $enrollment->session->title ?? 'N/A',
            'target_semester' => $result['resit_semester']->title ?? 'N/A',
            'progression_type' => 'Resit Semester',
            'failed_courses_count' => count($failedCourses),
            'scheduled_courses_count' => count($scheduledCourses),
            'scheduled_courses' => $scheduledCourses,
            'message' => 'You have completed all decisions for failed courses and are ready to progress to the resit semester.',
            'requirements' => [
                'All failed courses must be scheduled for resit or declined',
                'All resit requests must be approved or scheduled',
            ],
        ];
    }

    /**
     * Build summary for regular semester progression
     */
    protected function buildRegularProgressionSummary(StudentEnroll $enrollment, array $result): array
    {
        // Calculate GPA and credits
        $grades = \App\Models\Grade::where('status', 1)->orderBy('point', 'desc')->get();
        $totalCredits = 0;
        $totalGradePoints = 0;
        $completedCredits = 0;

        if ($enrollment->subjectMarks) {
            foreach ($enrollment->subjectMarks as $mark) {
                if (!$mark->subject) continue;
                
                $creditHour = (float) $mark->subject->credit_hour;
                $totalCredits += $creditHour;
                
                $marksPer = round($mark->total_marks);
                
                foreach ($grades as $grade) {
                    if ($marksPer >= $grade->min_mark && $marksPer <= $grade->max_mark) {
                        $gradePoint = (float) $grade->point;
                        $totalGradePoints += ($gradePoint * $creditHour);
                        
                        if ($gradePoint > 0) {
                            $completedCredits += $creditHour;
                        }
                        break;
                    }
                }
            }
        }

        $gpa = $totalCredits > 0 ? $totalGradePoints / $totalCredits : 0;

        $targetSession = $this->progressionService->targetSessionFor($enrollment);

        return [
            'program_name' => $enrollment->program->title ?? 'N/A',
            'current_semester' => $enrollment->semester->title ?? 'N/A',
            'current_session' => $enrollment->session->title ?? 'N/A',
            'target_semester' => $result['next_semester']->title ?? 'N/A',
            // Named, and flagged when it differs: moving up can also mean moving
            // into a new academic year, and that is not something to discover
            // after the fact.
            'target_session' => $targetSession->title ?? ($enrollment->session->title ?? 'N/A'),
            'enters_new_session' => $this->progressionService->entersNewSession($enrollment),
            'progression_type' => 'Regular Semester',
            'semester_gpa' => number_format($gpa, 2),
            'credits_attempted' => number_format($totalCredits, 1),
            'credits_earned' => number_format($completedCredits, 1),
            'total_courses' => $enrollment->subjects()->count(),
            'message' => 'Congratulations! You have met the requirements to progress to the next semester.',
            'requirements' => [
                'Completed all courses in current semester',
                'Marks have been published',
                'No pending failed courses (all scheduled for resit or declined)',
            ],
        ];
    }

    /**
     * Get carry-over courses for a student's enrollment
     * Finds ALL failed courses across any previous enrollment for the same program
     * that haven't been passed in a later attempt and don't have an active resit request
     */
    protected function getCarryOverCourses(StudentEnroll $enrollment): array
    {
        if (!$enrollment->semester) {
            return [];
        }

        // One rule, shared with course registration, the admin student page and
        // the Senate sheets — see OutstandingCourses. This method used to carry
        // its own copy of it, and the copies had drifted: a resit that had been
        // sat and failed was still being treated as "in hand" and dropped from
        // the list, so a student who failed a resit was offered progression with
        // the course missing from his carry-overs.
        return app(OutstandingCourses::class)
            ->carryOvers($enrollment)
            ->map(fn (array $row) => [
                'subject_id' => $row['subject_id'],
                'subject_code' => $row['subject_code'],
                'subject_title' => $row['subject_title'],
                'credit_hours' => $row['credit_hours'],
                'best_marks' => $row['best_marks'],
                'attempts' => $row['attempts'],
                'from_semester' => $row['from_semester'],
                'from_year' => $row['from_year'],
                'semester_type' => $row['semester_type'],
                'semester_type_label' => $row['semester_type_label'],
            ])
            ->values()
            ->all();
    }
}
