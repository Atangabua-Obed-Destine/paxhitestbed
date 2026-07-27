<?php

namespace App\Services\Academic;

use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\SubjectMarking;
use App\Models\SubjectMarkingExamState;
use App\Models\ResitRequest;
use App\Models\EnrollSubject;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentAttendance;
use App\Models\ProgramSemesterFee;
use App\Models\Fee;
use App\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SemesterProgressionService
{
    /**
     * Check if student is eligible for automatic semester progression
     * 
     * @param StudentEnroll $enrollment
     * @return array ['eligible' => bool, 'reason' => string, 'next_semester' => Semester|null]
     */
    public function checkProgressionEligibility(StudentEnroll $enrollment): array
    {
        $currentSemester = $enrollment->semester;
        
        // SPECIAL HANDLING FOR RESIT SEMESTERS
        // Students in resit semesters automatically progress to next regular semester once marks are published
        // They don't need to pass - they just progress (graduation will be blocked if they failed)
        if ($currentSemester && $currentSemester->is_resit) {
            // 1. Check if all resit course marks have been published
            if (!$this->allCoursesHavePublishedMarks($enrollment)) {
                return [
                    'eligible' => false,
                    'reason' => 'Not all resit course marks have been published yet.',
                    'next_semester' => null,
                ];
            }

            // 1.5. Ensure they have no unresolved failed courses from their parent regular semester
            if ($this->hasUnresolvedFailedCoursesFromParent($enrollment)) {
                return [
                    'eligible' => false,
                    'reason' => 'You still have unresolved failed courses from your regular semester. You must request a resit or decline them before progressing to the next regular semester.',
                    'next_semester' => null,
                ];
            }
            
            // 2. Find the next regular semester based on the resit semester's year and type
            $nextSemester = $this->findNextSemesterFromResit($enrollment);
            
            if (!$nextSemester) {
                return [
                    'eligible' => false,
                    'reason' => 'No next regular semester found after resit semester.',
                    'next_semester' => null,
                ];
            }

            // 3. Verify next semester has courses in enrollment structure
            if (!$this->semesterExistsInProgram($enrollment->program_id, $nextSemester->id)) {
                return [
                    'eligible' => false,
                    'reason' => 'Next semester not configured for this program in enrollment structure.',
                    'next_semester' => null,
                ];
            }

            // Validate session transition
            $sessionValidation = $this->validateSessionTransition($enrollment, $nextSemester);
            if (!$sessionValidation['valid']) {
                return [
                    'eligible' => false,
                    'reason' => $sessionValidation['reason'],
                    'next_semester' => null,
                ];
            }

            // Check if target semester has courses the student hasn't validated yet
            if (!$this->hasUnvalidatedCourses($enrollment, $nextSemester)) {
                return [
                    'eligible' => false,
                    'reason' => 'No new courses to register for in the next semester. All courses have already been validated.',
                    'next_semester' => null,
                ];
            }
            
            return [
                'eligible' => true,
                'reason' => 'Resit semester completed. Ready to progress to next regular semester.',
                'next_semester' => $nextSemester,
            ];
        }
        
        // REGULAR SEMESTER HANDLING
        // 1. Check if all courses have published marks
        if (!$this->allCoursesHavePublishedMarks($enrollment)) {
            return [
                'eligible' => false,
                'reason' => 'Not all course marks have been published yet.',
                'next_semester' => null,
            ];
        }

        // 2. Check if student passed all courses (≥50%)
        if (!$this->passedAllCourses($enrollment)) {
            return [
                'eligible' => false,
                'reason' => 'Student has failed one or more courses. Must complete resit process first.',
                'next_semester' => null,
            ];
        }

        // 3. Check for active resit requests
        if ($this->hasActiveResitRequests($enrollment)) {
            return [
                'eligible' => false,
                'reason' => 'Student has pending resit requests. Must cancel all resit requests to progress.',
                'next_semester' => null,
            ];
        }

        // 4. Find next available semester
        $nextSemester = $this->findNextSemester($enrollment);
        
        if (!$nextSemester) {
            return [
                'eligible' => false,
                'reason' => 'No next semester available for this program.',
                'next_semester' => null,
            ];
        }

        // 5. Verify semester exists in program enrollment structure
        if (!$this->semesterExistsInProgram($enrollment->program_id, $nextSemester->id)) {
            return [
                'eligible' => false,
                'reason' => 'Next semester not configured for this program in enrollment structure.',
                'next_semester' => null,
            ];
        }

        // Validate session transition
        $sessionValidation = $this->validateSessionTransition($enrollment, $nextSemester);
        if (!$sessionValidation['valid']) {
            return [
                'eligible' => false,
                'reason' => $sessionValidation['reason'],
                'next_semester' => null,
            ];
        }

        // 6. Check if target semester has courses the student hasn't validated yet
        if (!$this->hasUnvalidatedCourses($enrollment, $nextSemester)) {
            return [
                'eligible' => false,
                'reason' => 'No new courses to register for in the next semester. All courses have already been validated.',
                'next_semester' => null,
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Student is eligible for automatic progression.',
            'next_semester' => $nextSemester,
        ];
    }

    /**
     * Check if all courses have published marks
     */
    protected function allCoursesHavePublishedMarks(StudentEnroll $enrollment): bool
    {
        $subjects = $enrollment->subjects;
        
        if ($subjects->isEmpty()) {
            return false;
        }

        foreach ($subjects as $subject) {
            $marking = SubjectMarking::where('student_enroll_id', $enrollment->id)
                ->where('subject_id', $subject->id)
                ->first();

            // Mark must exist and be published
            if (!$marking || $marking->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
                return false;
            }

            // Check if publish date/time has passed
            if ($marking->publish_date && $marking->publish_time) {
                $publishDateTime = strtotime($marking->publish_date . ' ' . $marking->publish_time);
                if ($publishDateTime > time()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if student passed all courses (total marks ≥ 50%)
     * OR has declined to resit failed courses
     * OR has scheduled resits for failed courses
     */
    protected function passedAllCourses(StudentEnroll $enrollment): bool
    {
        $subjects = $enrollment->subjects;
        
        if ($subjects->isEmpty()) {
            return false;
        }

        foreach ($subjects as $subject) {
            $marking = SubjectMarking::where('student_enroll_id', $enrollment->id)
                ->where('subject_id', $subject->id)
                ->first();

            if (!$marking) {
                return false;
            }

            // Check if marks < 50% (failed)
            if (round($marking->total_marks) < 50) {
                // Student failed - check if they've declined OR scheduled resit for this course
                $hasDeclinedOrScheduled = ResitRequest::where('student_enroll_id', $enrollment->id)
                    ->where('subject_id', $subject->id)
                    ->whereIn('workflow_state', [ResitRequest::STATE_DECLINED, ResitRequest::STATE_SCHEDULED])
                    ->exists();
                
                // If they haven't declined or scheduled, they can't progress yet
                if (!$hasDeclinedOrScheduled) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if student has active resit requests
     */
    protected function hasActiveResitRequests(StudentEnroll $enrollment): bool
    {
        $activeStates = [
            ResitRequest::STATE_REQUESTED,
            ResitRequest::STATE_AWAITING_PAYMENT,
            ResitRequest::STATE_FINANCE_REVIEW,
            ResitRequest::STATE_APPROVED,
            ResitRequest::STATE_SCHEDULED,
        ];

        $activeRequests = ResitRequest::where('student_enroll_id', $enrollment->id)
            ->whereIn('workflow_state', $activeStates)
            ->count();

        return $activeRequests > 0;
    }

    /**
     * Find the next non-resit semester for progression
     */
    protected function findNextSemester(StudentEnroll $enrollment): ?Semester
    {
        $currentSemester = $enrollment->semester;
        
        if (!$currentSemester) {
            return null;
        }

        $currentYear = $currentSemester->year;
        $currentType = $currentSemester->semester_type;

        // Logic: Find next semester based on year and semester_type
        // - If current is Year 1, First Semester (type=1) → Next is Year 1, Second Semester (type=2)
        // - If current is Year 1, Second Semester (type=2) → Next is Year 2, First Semester (type=1)
        // - And so on...

        $nextYear = $currentYear;
        $nextType = $currentType;

        if ($currentType == 1) {
            // First Semester → Go to Second Semester same year
            $nextType = 2;
        } else {
            // Second Semester → Go to First Semester next year
            $nextYear = $currentYear + 1;
            $nextType = 1;
        }

        // Find the next semester (non-resit) for this program
        $nextSemester = Semester::where('year', $nextYear)
            ->where('semester_type', $nextType)
            ->where('is_resit', 0)
            ->where('status', 1)
            ->whereHas('programs', function($query) use ($enrollment) {
                $query->where('program_id', $enrollment->program_id);
            })
            ->first();

        return $nextSemester;
    }
    
    /**
     * Find the next regular semester after completing a resit semester
     * For resit semesters, we progress to the semester that comes AFTER the semester they failed in
     * 
     * Example: 
     * - Failed in "SECOND SEMESTER Y1" (Year 1, Type 2)
     * - Did resit in "2nd RESIT SEMESTER Y1" (Year 1, Type 2, is_resit=1)
     * - Should progress to "FIRST SEMESTER Y2" (Year 2, Type 1) - the NEXT regular semester
     */
    protected function findNextSemesterFromResit(StudentEnroll $enrollment): ?Semester
    {
        $resitSemester = $enrollment->semester;
        
        if (!$resitSemester || !$resitSemester->is_resit) {
            return null;
        }

        // The resit semester's year and type match the semester the student failed in
        // So we calculate the NEXT semester from there
        $currentYear = $resitSemester->year;
        $currentType = $resitSemester->semester_type;

        $nextYear = $currentYear;
        $nextType = $currentType;

        if ($currentType == 1) {
            // Was First Semester → Go to Second Semester same year
            $nextType = 2;
        } else {
            // Was Second Semester → Go to First Semester next year
            $nextYear = $currentYear + 1;
            $nextType = 1;
        }

        // Find the next regular (non-resit) semester for this program
        $nextSemester = Semester::where('year', $nextYear)
            ->where('semester_type', $nextType)
            ->where('is_resit', 0)
            ->where('status', 1)
            ->whereHas('programs', function($query) use ($enrollment) {
                $query->where('program_id', $enrollment->program_id);
            })
            ->first();

        return $nextSemester;
    }

    /**
     * Check if semester exists in program's enrollment structure
     */
    protected function semesterExistsInProgram(int $programId, int $semesterId): bool
    {
        return EnrollSubject::where('program_id', $programId)
            ->where('semester_id', $semesterId)
            ->exists();
    }

    /**
     * Check if the target semester has courses that the student hasn't already validated (passed).
     * Also checks for carry-over courses from previous semesters of the same type.
     * Prevents progression when there are no new courses to register for.
     */
    protected function hasUnvalidatedCourses(StudentEnroll $enrollment, Semester $targetSemester): bool
    {
        $student = $enrollment->student;
        if (!$student) {
            return false;
        }

        // Get all subject IDs the student has already passed (≥50%) across all enrollments in this program
        $passedSubjectIds = SubjectMarking::whereHas('studentEnroll', function ($q) use ($student, $enrollment) {
            $q->where('student_id', $student->id)
              ->where('program_id', $enrollment->program_id);
        })
        ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
        ->get()
        ->filter(function ($mark) {
            return round($mark->total_marks) >= 50;
        })
        ->pluck('subject_id')
        ->unique();

        // 1. Check courses assigned to the target semester in enrollment structure
        $targetEnrollSubjects = EnrollSubject::where('program_id', $enrollment->program_id)
            ->where('semester_id', $targetSemester->id)
            ->with('subjects')
            ->get();

        $targetSubjectIds = $targetEnrollSubjects->flatMap(function ($es) {
            return $es->subjects->pluck('id');
        })->unique()->values();

        if ($targetSubjectIds->diff($passedSubjectIds)->count() > 0) {
            return true;
        }

        // 2. Check for carry-over courses: failed courses from previous semesters
        //    of the SAME semester_type as the target, not passed and no active resit
        $targetSemesterType = $targetSemester->semester_type;

        // Get all enrollments for this student/program in non-resit semesters of the same type
        $priorEnrollments = $student->enrolls()
            ->where('program_id', $enrollment->program_id)
            ->whereHas('semester', function ($q) use ($targetSemesterType) {
                $q->where('semester_type', $targetSemesterType)
                  ->where('is_resit', 0);
            })
            ->with(['subjectMarks.subject'])
            ->get();

        // Find subjects with active resit requests (exclude these — they're being handled)
        $activeResitSubjectIds = ResitRequest::whereIn('student_enroll_id',
                $priorEnrollments->pluck('id')->toArray()
            )
            ->whereIn('workflow_state', [
                ResitRequest::STATE_REQUESTED,
                ResitRequest::STATE_AWAITING_PAYMENT,
                ResitRequest::STATE_FINANCE_REVIEW,
                ResitRequest::STATE_APPROVED,
                ResitRequest::STATE_SCHEDULED,
            ])
            ->pluck('subject_id')
            ->unique();

        foreach ($priorEnrollments as $priorEnroll) {
            foreach ($priorEnroll->subjectMarks ?? [] as $mark) {
                if (!$mark->subject) continue;
                if ($mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) continue;
                if (round($mark->total_marks) >= 50) continue;

                $subjectId = $mark->subject_id;

                // Skip if already passed in a later enrollment
                if ($passedSubjectIds->contains($subjectId)) continue;

                // Skip if has an active resit request
                if ($activeResitSubjectIds->contains($subjectId)) continue;

                // This is a carry-over course — student has something to register for
                return true;
            }
        }

        return false;
    }

    /**
     * Progress student to next semester
     * Creates a new StudentEnroll record but does NOT register courses
     * 
     * @param StudentEnroll $currentEnrollment
     * @param Semester $nextSemester
     * @return StudentEnroll|null
     */
    public function progressToNextSemester(StudentEnroll $currentEnrollment, Semester $nextSemester): ?StudentEnroll
    {
        try {
            DB::beginTransaction();

            // Get the student
            $student = $currentEnrollment->student;

            // Determine the correct session for the new enrollment
            // If the next semester is in a new academic year (e.g., Year 1 -> Year 2),
            // we should check if there's a new active session in the system.
            // Otherwise, we default to the current active session of the system.
            
            $targetSessionId = $currentEnrollment->session_id;
            
            // Get the system's current active session
            $systemCurrentSession = Session::where('current', 1)->where('status', 1)->first();
            
            if ($systemCurrentSession) {
                // If the system has moved to a new session (e.g. 2024-2025), use that.
                // This handles the case where a student progresses into a new academic year.
                $targetSessionId = $systemCurrentSession->id;
            }

            // Check if enrollment already exists for next semester
            $existingEnrollment = StudentEnroll::where('student_id', $student->id)
                ->where('program_id', $currentEnrollment->program_id)
                ->where('semester_id', $nextSemester->id)
                ->where('session_id', $targetSessionId) 
                ->first();

            if ($existingEnrollment) {
                Log::info("Student {$student->id} already has enrollment for semester {$nextSemester->id}");
                DB::rollBack();
                return $existingEnrollment;
            }

            // Validate session transition
            $sessionValidation = $this->validateSessionTransition($currentEnrollment, $nextSemester);
            if (!$sessionValidation['valid']) {
                return [
                    'eligible' => false,
                    'reason' => $sessionValidation['reason'],
                    'next_semester' => null,
                ];
            }

            // Mark the current enrollment as completed
            $currentEnrollment->update(['status' => '2']);

            // Create new enrollment for next semester
            // Note: We use the same matricule as current enrollment
            $newEnrollment = StudentEnroll::create([
                'student_id' => $student->id,
                'matricule' => $currentEnrollment->matricule,
                'program_id' => $currentEnrollment->program_id,
                'session_id' => $targetSessionId,
                'semester_id' => $nextSemester->id,
                'section_id' => $currentEnrollment->section_id,
                'status' => 1, // Active
            ]);

            Log::info("Student {$student->id} automatically progressed to semester {$nextSemester->id}", [
                'old_enrollment' => $currentEnrollment->id,
                'new_enrollment' => $newEnrollment->id,
            ]);

            // Auto-assign program semester fees if conditions are met
            $this->autoAssignProgramSemesterFees($newEnrollment);

            DB::commit();

            return $newEnrollment;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to progress student: " . $e->getMessage(), [
                'enrollment_id' => $currentEnrollment->id,
                'next_semester_id' => $nextSemester->id,
            ]);
            return null;
        }
    }

    /**
     * Attempt automatic progression after marks are published
     * This is called after all marks for a semester are published
     * 
     * @param StudentEnroll $enrollment
     * @return array ['progressed' => bool, 'message' => string, 'new_enrollment' => StudentEnroll|null]
     */
    public function attemptAutomaticProgression(StudentEnroll $enrollment): array
    {
        $eligibility = $this->checkProgressionEligibility($enrollment);

        if (!$eligibility['eligible']) {
            return [
                'progressed' => false,
                'message' => $eligibility['reason'],
                'new_enrollment' => null,
            ];
        }

        $newEnrollment = $this->progressToNextSemester($enrollment, $eligibility['next_semester']);

        if ($newEnrollment) {
            return [
                'progressed' => true,
                'message' => "Student automatically progressed to {$eligibility['next_semester']->title}.",
                'new_enrollment' => $newEnrollment,
            ];
        }

        return [
            'progressed' => false,
            'message' => 'Failed to create new enrollment. Please contact administration.',
            'new_enrollment' => null,
        ];
    }
    
    /**
     * Check if student can progress to resit semester
     * This happens when ALL failed courses are either declined OR scheduled for resit
     * 
     * @param StudentEnroll $enrollment
     * @return array ['can_progress' => bool, 'resit_semester' => Semester|null, 'scheduled_courses' => array, 'reason' => string]
     */
    public function checkResitSemesterProgression(StudentEnroll $enrollment): array
    {
        // Don't allow progression from resit semesters to another resit semester
        if ($enrollment->semester && $enrollment->semester->is_resit) {
            return [
                'can_progress' => false,
                'resit_semester' => null,
                'scheduled_courses' => [],
                'reason' => 'Already in a resit semester. Will progress to regular semester after completion.',
            ];
        }
        
        // Get all failed courses
        $failedCourses = $this->getFailedCourses($enrollment);
        
        if ($failedCourses->isEmpty()) {
            return [
                'can_progress' => false,
                'resit_semester' => null,
                'scheduled_courses' => [],
                'reason' => 'No failed courses found.',
            ];
        }
        
        // Check status of all failed courses
        $unresolvedCourses = [];
        $scheduledCourses = [];
        
        foreach ($failedCourses as $course) {
            $resitRequest = ResitRequest::where('student_enroll_id', $enrollment->id)
                ->where('subject_id', $course->id)
                ->whereIn('workflow_state', [
                    ResitRequest::STATE_REQUESTED,
                    ResitRequest::STATE_AWAITING_PAYMENT,
                    ResitRequest::STATE_FINANCE_REVIEW,
                    ResitRequest::STATE_APPROVED,
                    ResitRequest::STATE_SCHEDULED,
                    ResitRequest::STATE_DECLINED,
                ])
                ->orderByDesc('created_at')
                ->first();
            
            if (!$resitRequest) {
                // No request made yet
                $unresolvedCourses[] = [
                    'subject_id' => $course->id,
                    'subject_code' => $course->code,
                    'subject_title' => $course->title,
                    'status' => 'no_request',
                ];
            } elseif (in_array($resitRequest->workflow_state, [ResitRequest::STATE_REQUESTED, ResitRequest::STATE_AWAITING_PAYMENT, ResitRequest::STATE_FINANCE_REVIEW])) {
                // Pending payment or review
                $unresolvedCourses[] = [
                    'subject_id' => $course->id,
                    'subject_code' => $course->code,
                    'subject_title' => $course->title,
                    'status' => 'pending_payment',
                    'workflow_state' => $resitRequest->workflow_state,
                ];
            } elseif ($resitRequest->workflow_state === ResitRequest::STATE_SCHEDULED) {
                // Scheduled for resit
                $scheduledCourses[] = [
                    'subject_id' => $course->id,
                    'subject_code' => $course->code,
                    'subject_title' => $course->title,
                    'resit_session_id' => $resitRequest->resit_session_id,
                    'resit_semester_id' => $resitRequest->resit_semester_id,
                ];
            }
            // If DECLINED or APPROVED, course is resolved (declined means won't resit, approved will auto-schedule)
        }
        
        // If there are unresolved courses, cannot progress
        if (!empty($unresolvedCourses)) {
            return [
                'can_progress' => false,
                'resit_semester' => null,
                'scheduled_courses' => [],
                'reason' => 'Some failed courses have unresolved resit requests.',
                'unresolved_courses' => $unresolvedCourses,
            ];
        }
        
        // If there are no scheduled courses, student has declined all resits
        // They should progress to next regular semester (not resit semester)
        if (empty($scheduledCourses)) {
            return [
                'can_progress' => false,
                'resit_semester' => null,
                'scheduled_courses' => [],
                'reason' => 'All failed courses declined. Will progress to regular semester.',
            ];
        }
        
        // Find the resit semester (should be same for all scheduled courses)
        $resitSemesterId = $scheduledCourses[0]['resit_semester_id'];
        $resitSessionId = $scheduledCourses[0]['resit_session_id'];
        $resitSemester = Semester::find($resitSemesterId);
        
        if (!$resitSemester || !$resitSemester->is_resit) {
            return [
                'can_progress' => false,
                'resit_semester' => null,
                'scheduled_courses' => [],
                'reason' => 'Resit semester not found or invalid.',
            ];
        }
        
        return [
            'can_progress' => true,
            'resit_semester' => $resitSemester,
            'resit_session_id' => $resitSessionId,
            'scheduled_courses' => $scheduledCourses,
            'reason' => 'Student has scheduled resits and can progress to resit semester.',
        ];
    }
    
    /**
     * Get all failed courses for an enrollment
     */
    protected function getFailedCourses(StudentEnroll $enrollment)
    {
        $subjects = $enrollment->subjects;
        $failedCourses = collect();
        
        foreach ($subjects as $subject) {
            $marking = SubjectMarking::where('student_enroll_id', $enrollment->id)
                ->where('subject_id', $subject->id)
                ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
                ->first();
            
            if ($marking && round($marking->total_marks) < 50) {
                $failedCourses->push($subject);
            }
        }
        
        return $failedCourses;
    }
    
    /**
     * Check if student has unresolved failed courses from the parent regular semester
     * 
     * @param StudentEnroll $resitEnrollment
     * @return bool
     */
    protected function hasUnresolvedFailedCoursesFromParent(StudentEnroll $resitEnrollment): bool
    {
        $resitSemester = $resitEnrollment->semester;
        if (!$resitSemester || !$resitSemester->parent_semester_id) {
            return false;
        }

        $parentEnrollment = StudentEnroll::where('student_id', $resitEnrollment->student_id)
            ->where('program_id', $resitEnrollment->program_id)
            ->where('semester_id', $resitSemester->parent_semester_id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$parentEnrollment) {
            return false;
        }

        $failedCourses = $this->getFailedCourses($parentEnrollment);

        foreach ($failedCourses as $course) {
            $isRegisteredInResit = $resitEnrollment->subjects()->where('subjects.id', $course->id)->exists();
            if ($isRegisteredInResit) {
                continue;
            }

            $hasDeclined = \App\Models\ResitRequest::where('student_enroll_id', $parentEnrollment->id)
                ->where('subject_id', $course->id)
                ->where('workflow_state', \App\Models\ResitRequest::STATE_DECLINED)
                ->exists();

            if ($hasDeclined) {
                continue;
            }
            
            // Neither registered for resit nor declined -> Unresolved!
            return true;
        }

        return false;
    }

    /**
     * Progress student to resit semester and register scheduled resit courses
     * 
     * @param StudentEnroll $currentEnrollment
     * @param Semester $resitSemester
     * @param int $resitSessionId
     * @param array $scheduledCourses
     * @return StudentEnroll|null
     */
    public function progressToResitSemester(
        StudentEnroll $currentEnrollment,
        Semester $resitSemester,
        int $resitSessionId,
        array $scheduledCourses
    ): ?StudentEnroll {
        try {
            DB::beginTransaction();
            
            $student = $currentEnrollment->student;
            
            // Use the provided resitSessionId, but verify it matches system current if needed
            // For resits, we usually trust the scheduled session ID as it comes from the ResitRequest
            // However, if the resit is happening NOW, it should likely be in the current session
            
            // Check if enrollment already exists for resit semester
            $existingEnrollment = StudentEnroll::where('student_id', $student->id)
                ->where('program_id', $currentEnrollment->program_id)
                ->where('semester_id', $resitSemester->id)
                ->where('session_id', $resitSessionId)
                ->first();
            
            if ($existingEnrollment) {
                Log::info("Student {$student->id} already has resit enrollment for semester {$resitSemester->id}");
                
                // Ensure all scheduled courses are registered
                $courseIds = array_column($scheduledCourses, 'subject_id');
                $existingEnrollment->subjects()->syncWithoutDetaching($courseIds);
                
                DB::commit();
                return $existingEnrollment;
            }
            
            // Mark the current enrollment as completed
            $currentEnrollment->update(['status' => '2']);

            // Create new enrollment for resit semester
            $resitEnrollment = StudentEnroll::create([
                'student_id' => $student->id,
                'matricule' => $currentEnrollment->matricule,
                'program_id' => $currentEnrollment->program_id,
                'session_id' => $resitSessionId,
                'semester_id' => $resitSemester->id,
                'section_id' => $currentEnrollment->section_id,
                'status' => 1, // Active
            ]);
            
            // Register ONLY the scheduled resit courses
            $courseIds = array_column($scheduledCourses, 'subject_id');
            $resitEnrollment->subjects()->attach($courseIds);
            
            // Inherit attendance and CA marks from parent semester
            $this->inheritParentSemesterData($resitEnrollment, $resitSemester, $courseIds);
            
            Log::info("Student {$student->id} progressed to resit semester {$resitSemester->id}", [
                'old_enrollment' => $currentEnrollment->id,
                'resit_enrollment' => $resitEnrollment->id,
                'courses_registered' => count($courseIds),
            ]);
            
            DB::commit();
            
            return $resitEnrollment;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to progress student to resit semester: " . $e->getMessage(), [
                'enrollment_id' => $currentEnrollment->id,
                'resit_semester_id' => $resitSemester->id,
            ]);
            return null;
        }
    }

    /**
     * Inherit attendance and CA marks from parent semester to resit semester
     * 
     * @param StudentEnroll $resitEnrollment The new resit enrollment
     * @param Semester $resitSemester The resit semester
     * @param array $courseIds Array of subject IDs being retaken
     * @return void
     */
    public function inheritParentSemesterData(
        StudentEnroll $resitEnrollment,
        Semester $resitSemester,
        array $courseIds
    ): void {
        try {
            // Get parent semester - skip if not found
            if (!$resitSemester->parent_semester_id) {
                Log::warning("Resit semester {$resitSemester->id} has no parent_semester_id set");
                return;
            }

            $parentSemester = $resitSemester->parentSemester;
            if (!$parentSemester) {
                Log::warning("Parent semester not found for resit semester {$resitSemester->id}");
                return;
            }

            // Find parent enrollment for same student, program, and parent semester
            $parentEnrollment = StudentEnroll::where('student_id', $resitEnrollment->student_id)
                ->where('program_id', $resitEnrollment->program_id)
                ->where('semester_id', $parentSemester->id)
                ->first();

            if (!$parentEnrollment) {
                Log::warning("No parent enrollment found for student {$resitEnrollment->student_id} in semester {$parentSemester->id}");
                return;
            }

            $inheritanceSummary = [
                'parent_enrollment_id' => $parentEnrollment->id,
                'resit_enrollment_id' => $resitEnrollment->id,
                'courses_processed' => 0,
                'attendance_records_copied' => 0,
                'ca_marks_copied' => 0,
                'subject_markings_created' => 0,
            ];

            // Process each resit course
            foreach ($courseIds as $subjectId) {
                $inheritanceSummary['courses_processed']++;

                // 1. Inherit StudentAttendance records
                $attendanceCopied = $this->inheritAttendance(
                    $parentEnrollment->id,
                    $resitEnrollment->id,
                    $subjectId
                );
                $inheritanceSummary['attendance_records_copied'] += $attendanceCopied;

                // 2. Inherit CA marks (non-final exam marks)
                $caMarksCopied = $this->inheritCAMarks(
                    $parentEnrollment->id,
                    $resitEnrollment->id,
                    $subjectId
                );
                $inheritanceSummary['ca_marks_copied'] += $caMarksCopied;

                // 3. Create SubjectMarking with inherited data
                $subjectMarkingCreated = $this->createInheritedSubjectMarking(
                    $parentEnrollment->id,
                    $resitEnrollment->id,
                    $subjectId
                );
                if ($subjectMarkingCreated) {
                    $inheritanceSummary['subject_markings_created']++;
                }
            }

            Log::info("Successfully inherited parent semester data", $inheritanceSummary);

        } catch (\Exception $e) {
            // Don't throw - log error and continue
            Log::error("Error inheriting parent semester data: " . $e->getMessage(), [
                'resit_enrollment_id' => $resitEnrollment->id,
                'exception' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Inherit attendance records from parent enrollment to resit enrollment
     * 
     * @param int $parentEnrollmentId
     * @param int $resitEnrollmentId
     * @param int $subjectId
     * @return int Number of records copied
     */
    protected function inheritAttendance(
        int $parentEnrollmentId,
        int $resitEnrollmentId,
        int $subjectId
    ): int {
        try {
            $attendanceRecords = StudentAttendance::where('student_enroll_id', $parentEnrollmentId)
                ->where('subject_id', $subjectId)
                ->get();

            $copiedCount = 0;
            foreach ($attendanceRecords as $record) {
                // Check if attendance already exists (prevent duplicates)
                $exists = StudentAttendance::where('student_enroll_id', $resitEnrollmentId)
                    ->where('subject_id', $subjectId)
                    ->where('date', $record->date)
                    ->where('time', $record->time)
                    ->exists();

                if (!$exists) {
                    StudentAttendance::create([
                        'student_enroll_id' => $resitEnrollmentId,
                        'subject_id' => $subjectId,
                        'date' => $record->date,
                        'time' => $record->time,
                        'attendance' => $record->attendance,
                        'note' => $record->note ? "Inherited from parent semester: " . $record->note : "Inherited from parent semester",
                        'status' => $record->status,
                    ]);
                    $copiedCount++;
                }
            }

            return $copiedCount;

        } catch (\Exception $e) {
            Log::error("Error inheriting attendance: " . $e->getMessage(), [
                'parent_enrollment_id' => $parentEnrollmentId,
                'resit_enrollment_id' => $resitEnrollmentId,
                'subject_id' => $subjectId,
            ]);
            return 0;
        }
    }

    /**
     * Inherit CA marks (non-final exam marks) from parent to resit enrollment
     * 
     * @param int $parentEnrollmentId
     * @param int $resitEnrollmentId
     * @param int $subjectId
     * @return int Number of exam records copied
     */
    protected function inheritCAMarks(
        int $parentEnrollmentId,
        int $resitEnrollmentId,
        int $subjectId
    ): int {
        try {
            // Get all exams for non-final exam types
            $caExams = Exam::where('student_enroll_id', $parentEnrollmentId)
                ->where('subject_id', $subjectId)
                ->whereHas('type', function ($query) {
                    $query->where('is_final', false);
                })
                ->get();

            $copiedCount = 0;
            foreach ($caExams as $exam) {
                // Check if exam already exists (prevent duplicates)
                $exists = Exam::where('student_enroll_id', $resitEnrollmentId)
                    ->where('subject_id', $subjectId)
                    ->where('exam_type_id', $exam->exam_type_id)
                    ->exists();

                if (!$exists) {
                    Exam::create([
                        'student_enroll_id' => $resitEnrollmentId,
                        'subject_id' => $subjectId,
                        'exam_type_id' => $exam->exam_type_id,
                        'date' => $exam->date,
                        'time' => $exam->time,
                        'attendance' => $exam->attendance,
                        'marks' => $exam->marks,
                        'achieve_marks' => $exam->achieve_marks,
                        'contribution' => $exam->contribution,
                        'note' => $exam->note ? "Inherited from parent semester: " . $exam->note : "Inherited from parent semester",
                        'status' => $exam->status,
                    ]);
                    $copiedCount++;
                }
            }

            return $copiedCount;

        } catch (\Exception $e) {
            Log::error("Error inheriting CA marks: " . $e->getMessage(), [
                'parent_enrollment_id' => $parentEnrollmentId,
                'resit_enrollment_id' => $resitEnrollmentId,
                'subject_id' => $subjectId,
            ]);
            return 0;
        }
    }

    /**
     * Create SubjectMarking record with inherited CA components
     * 
     * @param int $parentEnrollmentId
     * @param int $resitEnrollmentId
     * @param int $subjectId
     * @return bool Success status
     */
    protected function createInheritedSubjectMarking(
        int $parentEnrollmentId,
        int $resitEnrollmentId,
        int $subjectId
    ): bool {
        try {
            // Check if SubjectMarking already exists
            $existingMarking = SubjectMarking::where('student_enroll_id', $resitEnrollmentId)
                ->where('subject_id', $subjectId)
                ->first();

            if ($existingMarking) {
                Log::info("SubjectMarking already exists for resit enrollment", [
                    'resit_enrollment_id' => $resitEnrollmentId,
                    'subject_id' => $subjectId,
                ]);
                return false;
            }

            // Get parent SubjectMarking
            $parentMarking = SubjectMarking::where('student_enroll_id', $parentEnrollmentId)
                ->where('subject_id', $subjectId)
                ->first();

            if (!$parentMarking) {
                Log::info("No parent SubjectMarking found to inherit from", [
                    'parent_enrollment_id' => $parentEnrollmentId,
                    'subject_id' => $subjectId,
                ]);
                return false;
            }

            // Create new SubjectMarking in draft state with inherited CA components
            $newMarking = SubjectMarking::create([
                'student_enroll_id' => $resitEnrollmentId,
                'subject_id' => $subjectId,
                'exam_marks' => 0, // Final exam not yet taken
                'attendances' => $parentMarking->attendances, // Inherit attendance score
                'assignments' => $parentMarking->assignments, // Inherit assignments
                'activities' => $parentMarking->activities, // Inherit activities
                'total_marks' => $parentMarking->attendances + $parentMarking->assignments + $parentMarking->activities, // Partial total (without exam)
                'workflow_state' => SubjectMarking::STATE_DRAFT, // Start in draft
                'state_changed_at' => Carbon::now(),
                'resolved_exam_weight' => $parentMarking->resolved_exam_weight,
                'resolved_ca_weight' => $parentMarking->resolved_ca_weight,
                'resolved_attendance_weight' => $parentMarking->resolved_attendance_weight,
                'validated' => false,
                'status' => 1,
            ]);

            // Inherit SubjectMarkingExamStates for CA exam types
            $parentExamStates = SubjectMarkingExamState::where('subject_marking_id', $parentMarking->id)
                ->whereHas('examType', function ($query) {
                    $query->where('is_final', false);
                })
                ->get();

            foreach ($parentExamStates as $parentState) {
                SubjectMarkingExamState::create([
                    'subject_marking_id' => $newMarking->id,
                    'exam_type_id' => $parentState->exam_type_id,
                    'marks' => $parentState->marks,
                    'workflow_state' => SubjectMarking::STATE_PUBLISHED, // CA marks already published in parent
                    'state_changed_at' => Carbon::now(),
                    'publish_date' => $parentState->publish_date,
                    'publish_time' => $parentState->publish_time,
                ]);
            }

            Log::info("Created inherited SubjectMarking", [
                'parent_marking_id' => $parentMarking->id,
                'new_marking_id' => $newMarking->id,
                'inherited_ca_states' => $parentExamStates->count(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Error creating inherited SubjectMarking: " . $e->getMessage(), [
                'parent_enrollment_id' => $parentEnrollmentId,
                'resit_enrollment_id' => $resitEnrollmentId,
                'subject_id' => $subjectId,
            ]);
            return false;
        }
    }

    /**
     * Auto-assign program semester fees to student enrollment
     * Assigns fees for ALL semesters of the current academic year
     * Conditions:
     * - Only for regular (non-resit) semesters
     * - Prevents duplicate fee assignments
     * 
     * @param StudentEnroll $enrollment
     * @return void
     */
    protected function autoAssignProgramSemesterFees(StudentEnroll $enrollment): void
    {
        try {
            // Only for regular semesters
            if ($enrollment->semester && $enrollment->semester->is_resit) {
                Log::info("Skipping auto-fee assignment for resit semester", [
                    'enrollment_id' => $enrollment->id,
                    'semester_id' => $enrollment->semester_id,
                ]);
                return;
            }

            $semester = $enrollment->semester;
            if (!$semester) {
                Log::warning("Semester not found", [
                    'enrollment_id' => $enrollment->id,
                    'semester_id' => $enrollment->semester_id,
                ]);
                return;
            }

            // Get the academic year from current semester
            $academicYear = $semester->year;

            Log::info("Starting per-semester fee assignment during progression", [
                'enrollment_id' => $enrollment->id,
                'semester_id' => $semester->id,
                'semester_title' => $semester->title,
                'academic_year' => $academicYear,
            ]);

            // Only assign fees configured for the specific semester the student
            // is progressing into (per program-semester-fee configuration).
            // Year-wide assignment is handled at initial enrollment, not on progression.
            $yearSemesters = collect([$semester]);

            $feesCreated = 0;
            $feesSkipped = 0;
            $semestersProcessed = [];

            // Get all student's enrollments for this program to check existing fees
            $studentEnrollments = \App\Models\StudentEnroll::where('student_id', $enrollment->student_id)
                ->where('program_id', $enrollment->program_id)
                ->pluck('id')
                ->toArray();

            // Process fees for each semester of the year
            foreach ($yearSemesters as $yearSemester) {
                // Get configured fees for this semester
                $configuredFees = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                    ->where('semester_id', $yearSemester->id)
                    ->where('status', 1)
                    ->with('feesCategory')
                    ->get();

                if ($configuredFees->isEmpty()) {
                    Log::info("No fees configured for semester", [
                        'semester_id' => $yearSemester->id,
                        'semester_title' => $yearSemester->title,
                    ]);
                    continue;
                }

                $semesterFeesCreated = 0;

                foreach ($configuredFees as $feeConfig) {
                    // Check if this fee category was already assigned for THIS specific
                    // semester (across any of the student's enrollments for this program).
                    // Per-semester check matches the program-semester-fee configuration.
                    $existingFee = Fee::whereIn('student_enroll_id', $studentEnrollments)
                        ->where('category_id', $feeConfig->fees_category_id)
                        ->whereHas('studentEnroll', function($query) use ($yearSemester) {
                            $query->where('semester_id', $yearSemester->id);
                        })
                        ->first();

                    if ($existingFee) {
                        Log::info("Fee already exists for this semester - skipping", [
                            'student_id' => $enrollment->student_id,
                            'category_id' => $feeConfig->fees_category_id,
                            'category_title' => $feeConfig->feesCategory->title ?? 'Unknown',
                            'semester_id' => $yearSemester->id,
                            'existing_enrollment_id' => $existingFee->student_enroll_id,
                        ]);
                        $feesSkipped++;
                        continue;
                    }

                    // Calculate due date based on configured due_days or default based on semester type
                    if ($feeConfig->due_days) {
                        $daysToAdd = $feeConfig->due_days;
                    } else {
                        $daysToAdd = ($yearSemester->semester_type == 1) ? 30 : 60;
                    }
                    $dueDate = Carbon::now()->addDays($daysToAdd)->format('Y-m-d');

                    // Calculate fine amount if configured
                    $fineAmount = 0;
                    if ($feeConfig->fine_amount && $feeConfig->fine_type) {
                        if ($feeConfig->fine_type == 'fixed') {
                            $fineAmount = $feeConfig->fine_amount;
                        } else if ($feeConfig->fine_type == 'percentage') {
                            $fineAmount = ($feeConfig->amount * $feeConfig->fine_amount) / 100;
                        }
                    }

                    // Create Fee record
                    $fee = Fee::create([
                        'student_enroll_id' => $enrollment->id,
                        'category_id' => $feeConfig->fees_category_id,
                        'fee_amount' => $feeConfig->amount,
                        'fine_amount' => $fineAmount,
                        'assign_date' => Carbon::now()->format('Y-m-d'),
                        'due_date' => $dueDate,
                        'note' => "Auto-assigned for {$yearSemester->title} (Year {$academicYear})",
                        'status' => 0, // Unpaid
                    ]);
                    
                    // Auto-apply student credits if eligible
                    try {
                        $creditService = new \App\Services\StudentCreditService();
                        $creditResult = $creditService->autoApplyCreditsToNewFee($fee);
                        if ($creditResult['total_applied'] > 0) {
                            Log::info("Credits auto-applied to fee during progression", [
                                'fee_id' => $fee->id,
                                'amount_applied' => $creditResult['total_applied'],
                            ]);
                        }
                    } catch (\Exception $creditException) {
                        Log::warning("Failed to auto-apply credits: " . $creditException->getMessage());
                    }
                    
                    $feesCreated++;
                    $semesterFeesCreated++;
                    
                    Log::info("Fee created for year semester during progression", [
                        'enrollment_id' => $enrollment->id,
                        'semester' => $yearSemester->title,
                        'category' => $feeConfig->feesCategory->title ?? 'Unknown',
                        'amount' => $feeConfig->amount,
                        'due_date' => $dueDate,
                    ]);
                }

                if ($semesterFeesCreated > 0 || $configuredFees->isNotEmpty()) {
                    $semestersProcessed[] = [
                        'semester' => $yearSemester->title,
                        'fees_created' => $semesterFeesCreated,
                        'fees_configured' => $configuredFees->count(),
                    ];
                }
            }

            if ($feesCreated > 0 || $feesSkipped > 0) {
                Log::info("Year-based fee assignment completed during progression", [
                    'enrollment_id' => $enrollment->id,
                    'academic_year' => $academicYear,
                    'fees_created' => $feesCreated,
                    'fees_skipped' => $feesSkipped,
                    'semesters_processed' => $semestersProcessed,
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Failed to auto-assign year fees during progression: " . $e->getMessage(), [
                'enrollment_id' => $enrollment->id,
                'exception' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Validate if the session transition is allowed
     * Prevents progressing to a new academic year within the same session
     * 
     * @param StudentEnroll $enrollment
     * @param Semester $nextSemester
     * @return array ['valid' => bool, 'reason' => string|null]
     */
    protected function validateSessionTransition(StudentEnroll $enrollment, Semester $nextSemester): array
    {
        // If current semester is missing, we can't validate
        if (!$enrollment->semester) {
            return ['valid' => true, 'reason' => null];
        }

        // Check if we are moving to a higher academic year (e.g. Year 1 -> Year 2)
        // Note: We compare with the enrollment's semester year
        if ($nextSemester->year > $enrollment->semester->year) {
            // Get the system's current active session
            $currentActiveSession = \App\Models\Session::where('current', 1)->where('status', 1)->first();
            
            // If the system's active session is the SAME as the student's current enrollment session,
            // it means the new academic year hasn't started yet.
            if ($currentActiveSession && $currentActiveSession->id == $enrollment->session_id) {
                return [
                    'valid' => false,
                    'reason' => 'Cannot progress to next Academic Year yet. Waiting for new Academic Session to be activated.'
                ];
            }
        }
        
        return ['valid' => true, 'reason' => null];
    }
}
