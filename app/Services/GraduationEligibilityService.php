<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectMarking;
use Illuminate\Support\Collection;

class GraduationEligibilityService
{
    /**
     * Check if a student is eligible for graduation
     * 
     * @param Student $student
     * @param int|null $programId Optional program ID to check (defaults to student's main program)
     * @return array
     */
    public function checkEligibility(Student $student, ?int $programId = null): array
    {
        $programId = $programId ?? $student->program_id;
        
        // Get all program subjects
        $programSubjects = $this->getProgramSubjects($programId);
        
        // Get student's subject marks across all enrollments (filtered by program)
        $studentMarks = $this->getStudentMarks($student, $programId);
        
        // Analyze each subject category
        $compulsory = $this->analyzeSubjectCategory($programSubjects, $studentMarks, 1); // Compulsory
        $universityReq = $this->analyzeSubjectCategory($programSubjects, $studentMarks, 2); // University Requirement
        $optional = $this->analyzeSubjectCategory($programSubjects, $studentMarks, 0); // Optional
        
        // Overall eligibility
        $isEligible = $compulsory['all_passed'] && $universityReq['all_passed'];
        
        // Reasons for ineligibility
        $reasons = [];
        if (!$compulsory['all_passed']) {
            $reasons[] = "Failed {$compulsory['failed_count']} compulsory course(s)";
        }
        if (!$universityReq['all_passed']) {
            $reasons[] = "Failed {$universityReq['failed_count']} university requirement course(s)";
        }
        
        return [
            'is_eligible' => $isEligible,
            'compulsory' => $compulsory,
            'university_requirement' => $universityReq,
            'optional' => $optional,
            'reasons' => $reasons,
            'total_credits' => $compulsory['total_credits'] + $universityReq['total_credits'] + $optional['total_credits'],
            'completed_credits' => $compulsory['completed_credits'] + $universityReq['completed_credits'] + $optional['completed_credits'],
        ];
    }
    
    /**
     * Get detailed course breakdown for a student
     * 
     * @param Student $student
     * @param int|null $programId Optional program ID to check (defaults to student's main program)
     * @return array
     */
    public function getCourseBreakdown(Student $student, ?int $programId = null): array
    {
        $programId = $programId ?? $student->program_id;
        $programSubjects = $this->getProgramSubjects($programId);
        $studentMarks = $this->getStudentMarks($student, $programId);
        
        $breakdown = [
            'compulsory' => [],
            'university_requirement' => [],
            'optional' => [],
        ];
        
        foreach ($programSubjects as $subject) {
            $mark = $studentMarks->firstWhere('subject_id', $subject->id);
            
            $courseData = [
                'code' => $subject->code,
                'title' => $subject->title,
                'credit_hour' => $subject->credit_hour,
                'has_marks' => !is_null($mark),
                'total_marks' => $mark ? round($mark->total_marks, 2) : null,
                'percentage' => $mark ? round($mark->total_marks, 2) : null,
                'passed' => $mark ? ($mark->total_marks >= 50) : false,
                'status' => $this->getCourseStatus($mark),
                'grade' => $mark ? $this->getGrade($mark->total_marks) : null,
            ];
            
            if ($subject->subject_type == 1) {
                $breakdown['compulsory'][] = $courseData;
            } elseif ($subject->subject_type == 2) {
                $breakdown['university_requirement'][] = $courseData;
            } else {
                $breakdown['optional'][] = $courseData;
            }
        }
        
        return $breakdown;
    }
    
    /**
     * Get all subjects for a program
     * 
     * @param int $programId
     * @return Collection
     */
    protected function getProgramSubjects(int $programId): Collection
    {
        return Subject::whereHas('programs', function($query) use ($programId) {
            $query->where('program_id', $programId);
        })
        ->where('status', 1)
        ->orderBy('subject_type', 'desc') // Compulsory first
        ->orderBy('code', 'asc')
        ->get();
    }
    /**
     * Get student's marks (latest attempt for each subject)
     * 
     * @param Student $student
     * @param int|null $programId Optional program ID to filter by
     * @return Collection
     */
    protected function getStudentMarks(Student $student, ?int $programId = null): Collection
    {
        $marks = SubjectMarking::whereHas('studentEnroll', function($query) use ($student, $programId) {
            $query->where('student_id', $student->id);
            if ($programId) {
                $query->where('program_id', $programId);
            }
        })
        ->with(['subject', 'studentEnroll.session', 'studentEnroll.semester'])
        ->get();
        
        // CRITICAL: Filter out unpublished marks to match transcript behavior
        $publishedMarks = $marks->filter(function($mark) {
            return $this->isMarkPublished($mark);
        });
        
        // Group by subject_id and get the latest attempt (or highest mark)
        $latestMarks = $publishedMarks->groupBy('subject_id')->map(function($subjectMarks) {
            // Use the latest entry (by created_at or highest mark)
            return $subjectMarks->sortByDesc('created_at')->first();
        });
        
        return $latestMarks->values();
    }
    
    /**
     * Check if subject marking is published and visible to students
     * Uses same logic as transcript to ensure consistency
     */
    protected function isMarkPublished($mark): bool
    {
        // Use the model's is_visible_to_student accessor which handles:
        // - workflow_state check
        // - is_published_override check (null = follow workflow, true = force publish, false = force unpublish)
        if (!$mark->is_visible_to_student) {
            return false;
        }
        
        // Check if publish date/time has passed (same logic as transcript)
        $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? 
                      $mark->publish_date->format('Y-m-d') : 
                      date('Y-m-d', strtotime($mark->publish_date));
        $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? 
                      $mark->publish_time->format('H:i:s') : 
                      date('H:i:s', strtotime($mark->publish_time));
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');

        $isVisible = ($publishDate == $currentDate && $publishTime <= $currentTime) || 
                     $publishDate < $currentDate;
        
        return $isVisible;
    }
    
    /**
     * Analyze subject category (Compulsory, University Requirement, Optional)
     * 
     * @param Collection $programSubjects
     * @param Collection $studentMarks
     * @param int $subjectType (0=Optional, 1=Compulsory, 2=University Requirement)
     * @return array
     */
    protected function analyzeSubjectCategory(Collection $programSubjects, Collection $studentMarks, int $subjectType): array
    {
        $categorySubjects = $programSubjects->where('subject_type', $subjectType);
        $totalSubjects = $categorySubjects->count();
        $totalCredits = $categorySubjects->sum('credit_hour');
        
        $completedSubjects = 0;
        $passedSubjects = 0;
        $failedSubjects = 0;
        $completedCredits = 0;
        $failedList = [];
        $missingList = [];
        
        foreach ($categorySubjects as $subject) {
            $mark = $studentMarks->firstWhere('subject_id', $subject->id);
            
            if ($mark) {
                $completedSubjects++;
                $totalMarks = round($mark->total_marks, 2);
                
                if ($totalMarks >= 50) {
                    $passedSubjects++;
                    $completedCredits += $subject->credit_hour;
                } else {
                    $failedSubjects++;
                    $failedList[] = [
                        'code' => $subject->code,
                        'title' => $subject->title,
                        'marks' => $totalMarks,
                        'credit_hour' => $subject->credit_hour,
                    ];
                }
            } else {
                $missingList[] = [
                    'code' => $subject->code,
                    'title' => $subject->title,
                    'credit_hour' => $subject->credit_hour,
                ];
            }
        }
        
        // If there are no subjects in this category, consider it as "all passed"
        // Otherwise, check if all subjects are passed
        $allPassed = ($totalSubjects == 0) || ($totalSubjects == $passedSubjects);
        
        return [
            'total_subjects' => $totalSubjects,
            'total_credits' => $totalCredits,
            'completed_subjects' => $completedSubjects,
            'completed_credits' => $completedCredits,
            'passed_subjects' => $passedSubjects,
            'failed_subjects' => $failedSubjects,
            'missing_subjects' => count($missingList),
            'all_passed' => $allPassed,
            'failed_count' => $failedSubjects,
            'failed_list' => $failedList,
            'missing_list' => $missingList,
            'completion_rate' => $totalSubjects > 0 ? round(($completedSubjects / $totalSubjects) * 100, 2) : 0,
            'pass_rate' => $completedSubjects > 0 ? round(($passedSubjects / $completedSubjects) * 100, 2) : 0,
        ];
    }
    
    /**
     * Get course status label
     * 
     * @param SubjectMark|null $mark
     * @return string
     */
    protected function getCourseStatus($mark): string
    {
        if (!$mark) {
            return 'Not Taken';
        }
        
        $totalMarks = round($mark->total_marks, 2);
        
        if ($totalMarks >= 50) {
            return 'Passed';
        } else {
            return 'Failed';
        }
    }
    
    /**
     * Get grade based on marks
     * 
     * @param float $marks
     * @return string
     */
    protected function getGrade(float $marks): string
    {
        if ($marks >= 80) return 'A';
        if ($marks >= 70) return 'B';
        if ($marks >= 60) return 'C';
        if ($marks >= 50) return 'D';
        return 'F';
    }
    
    /**
     * Get summary statistics for multiple students
     * 
     * @param Collection $students
     * @return array
     */
    public function getBatchStatistics(Collection $students): array
    {
        $eligible = 0;
        $ineligible = 0;
        $totalCredits = 0;
        $reasons = [];
        
        foreach ($students as $student) {
            $eligibility = $this->checkEligibility($student);
            
            if ($eligibility['is_eligible']) {
                $eligible++;
            } else {
                $ineligible++;
                foreach ($eligibility['reasons'] as $reason) {
                    if (!isset($reasons[$reason])) {
                        $reasons[$reason] = 0;
                    }
                    $reasons[$reason]++;
                }
            }
            
            $totalCredits += $eligibility['completed_credits'];
        }
        
        return [
            'total_students' => $students->count(),
            'eligible' => $eligible,
            'ineligible' => $ineligible,
            'average_credits' => $students->count() > 0 ? round($totalCredits / $students->count(), 2) : 0,
            'ineligibility_reasons' => $reasons,
        ];
    }
}
