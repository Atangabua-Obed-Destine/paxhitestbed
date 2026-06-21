<?php

namespace App\Services\Student;

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Models\ResitRequest;
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
        if ($resitEligibility['eligible']) {
            $resitEligibility['carry_over_courses'] = $carryOverCourses;
            return $resitEligibility;
        }

        // Check for regular semester progression
        $regularEligibility = $this->checkRegularSemesterEligibility($enrollment);
        if ($regularEligibility['eligible']) {
            $regularEligibility['carry_over_courses'] = $carryOverCourses;
            if (!empty($carryOverCourses)) {
                $regularEligibility['summary']['carry_over_courses'] = $carryOverCourses;
            }
            return $regularEligibility;
        }

        // Determine the most useful reason to show the student
        $reason = $regularEligibility['reason'] ?? $resitEligibility['reason'] ?? 'Not yet eligible for progression';

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
        ];
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
            ];
        }

        // Get summary data
        $summary = $this->buildRegularProgressionSummary($enrollment, $result);

        return [
            'eligible' => true,
            'type' => 'regular',
            'enrollment_id' => $enrollment->id,
            'program_id' => $enrollment->program_id,
            'program_name' => $enrollment->program->title ?? 'N/A',
            'target_session_id' => $enrollment->session_id, // Use current session for now
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

        return [
            'program_name' => $enrollment->program->title ?? 'N/A',
            'current_semester' => $enrollment->semester->title ?? 'N/A',
            'current_session' => $enrollment->session->title ?? 'N/A',
            'target_semester' => $result['next_semester']->title ?? 'N/A',
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

        $student = $enrollment->student;
        if (!$student) {
            return [];
        }

        // Get ALL enrollments for this student & program, ordered by ID so regular semesters come before resits
        $allEnrollments = $student->enrolls()
            ->where('program_id', $enrollment->program_id)
            ->with(['semester', 'subjectMarks.subject'])
            ->orderBy('id', 'asc')
            ->get();

        // Build set of subjects passed (≥50%) in ANY published enrollment
        $passedSubjectIds = [];
        foreach ($allEnrollments as $enroll) {
            foreach ($enroll->subjectMarks ?? [] as $mark) {
                if (!$mark->subject) continue;
                if ($mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) continue;
                if (round($mark->total_marks) >= 50) {
                    $passedSubjectIds[$mark->subject_id] = true;
                }
            }
        }

        // Find subjects with active/pending resit requests (being handled through the resit process)
        $activeResitSubjectIds = ResitRequest::whereIn('student_enroll_id',
                $allEnrollments->pluck('id')->toArray()
            )
            ->whereIn('workflow_state', ['requested', 'awaiting_payment', 'finance_review', 'approved', 'scheduled'])
            ->pluck('subject_id')
            ->flip()
            ->toArray();

        // Collect carry-over courses from all previous enrollments
        $carryOvers = [];
        foreach ($allEnrollments as $enroll) {
            // Skip the current enrollment
            if ($enroll->id === $enrollment->id) continue;

            foreach ($enroll->subjectMarks ?? [] as $mark) {
                if (!$mark->subject) continue;
                if ($mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) continue;

                $subjectId = $mark->subject_id;
                $marksPer = round($mark->total_marks);

                if ($marksPer >= 50) continue;
                if (isset($passedSubjectIds[$subjectId])) continue;
                if (isset($activeResitSubjectIds[$subjectId])) continue;

                // Update best mark if already tracked from an earlier enrollment
                if (isset($carryOvers[$subjectId])) {
                    if ($marksPer > $carryOvers[$subjectId]['best_marks']) {
                        $carryOvers[$subjectId]['best_marks'] = $marksPer;
                    }
                    continue;
                }

                $fromSemester = $enroll->semester;
                $semType = $fromSemester ? ($fromSemester->semester_type ?? 1) : 1;

                $carryOvers[$subjectId] = [
                    'subject_id' => $subjectId,
                    'subject_code' => $mark->subject->code ?? '',
                    'subject_title' => $mark->subject->title ?? '',
                    'credit_hours' => $mark->subject->credit_hour ?? 0,
                    'best_marks' => $marksPer,
                    'from_semester' => $fromSemester->title ?? '',
                    'from_year' => $fromSemester->year ?? 0,
                    'semester_type' => $semType,
                    'semester_type_label' => $semType == 1 ? 'First Semester' : 'Second Semester',
                ];
            }
        }

        return array_values($carryOvers);
    }
}
