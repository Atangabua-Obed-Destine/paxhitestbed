<?php

namespace App\Services;

use App\Models\ResultContribution;
use App\Models\ExamTypeContribution;
use App\Models\ExamType;

class ResultContributionService
{
    /**
     * Get mark contributions for a specific subject/course
     * Returns an array with exam type contributions and fixed contributions
     * 
     * @param int $subject_id
     * @return array
     */
    public static function getSubjectContributions($subject_id)
    {
        // Get result contribution (attendance, assignments, activities) for this subject
        $resultContribution = ResultContribution::where('subject_id', $subject_id)
            ->where('status', 1)
            ->first();
        
        // Get exam type contributions for this subject
        $examContributions = ExamTypeContribution::where('subject_id', $subject_id)->get();
        
        // If no contributions found for this subject, return defaults (all zeros)
        if(!$resultContribution && $examContributions->isEmpty()){
            return [
                'attendance' => 0,
                'assignment' => 0,
                'activity' => 0,
                'exam_types' => [],
                'configured' => false
            ];
        }
        
        // Build exam types array - keyed by exam_type_id with full objects
        $examTypesArray = [];
        foreach($examContributions as $examContribution){
            $examTypesArray[$examContribution->exam_type_id] = $examContribution;
        }
        
        return [
            'attendance' => $resultContribution ? $resultContribution->attendances : 0,
            'assignment' => $resultContribution ? $resultContribution->assignments : 0,
            'activity' => $resultContribution ? $resultContribution->activities : 0,
            'exam_types' => $examTypesArray,
            'configured' => true
        ];
    }
    
    /**
     * Get contribution percentage for a specific exam type and subject
     * 
     * @param int $subject_id
     * @param int $exam_type_id
     * @return float
     */
    public static function getExamTypeContribution($subject_id, $exam_type_id)
    {
        $contribution = ExamTypeContribution::where('subject_id', $subject_id)
            ->where('exam_type_id', $exam_type_id)
            ->first();
        
        return $contribution ? $contribution->contribution : 0;
    }
    
    /**
     * Check if a subject has configured mark distribution
     * 
     * @param int $subject_id
     * @return bool
     */
    public static function isConfigured($subject_id)
    {
        $resultContribution = ResultContribution::where('subject_id', $subject_id)
            ->where('status', 1)
            ->exists();
        
        $examContributions = ExamTypeContribution::where('subject_id', $subject_id)->exists();
        
        return $resultContribution || $examContributions;
    }
}
