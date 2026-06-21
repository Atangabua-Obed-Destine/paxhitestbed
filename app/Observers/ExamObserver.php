<?php

namespace App\Observers;

use App\Models\Exam;
use App\Models\SubjectMarking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExamObserver
{
    /**
     * Handle the Exam "saved" event (fires after both create and update).
     * Automatically recalculate and update subject_markings.exam_marks
     * when exam marks are changed.
     *
     * @param  \App\Models\Exam  $exam
     * @return void
     */
    public function saved(Exam $exam)
    {
        // Check if relevant fields were changed
        if ($exam->wasChanged(['achieve_marks', 'marks', 'contribution', 'attendance']) || $exam->wasRecentlyCreated) {
            $this->recalculateSubjectMarking($exam->student_enroll_id, $exam->subject_id);
        }
    }

    /**
     * Handle the Exam "deleted" event.
     * Recalculate subject marking when an exam is deleted.
     *
     * @param  \App\Models\Exam  $exam
     * @return void
     */
    public function deleted(Exam $exam)
    {
        $this->recalculateSubjectMarking($exam->student_enroll_id, $exam->subject_id);
    }

    /**
     * Recalculate the exam_marks for a student's subject marking.
     * This replicates the calculation logic from marking.blade.php
     *
     * @param  int  $studentEnrollId
     * @param  int  $subjectId
     * @return void
     */
    protected function recalculateSubjectMarking($studentEnrollId, $subjectId)
    {
        try {
            // Get all exams for this student and subject
            $exams = Exam::where('student_enroll_id', $studentEnrollId)
                ->where('subject_id', $subjectId)
                ->where('attendance', 1) // Only attended exams
                ->where('contribution', '>', 0) // Only exams with contribution
                ->get();

            Log::info("ExamObserver: Found exams", [
                'student_enroll_id' => $studentEnrollId,
                'subject_id' => $subjectId,
                'exam_count' => $exams->count(),
                'exams' => $exams->map(function($e) {
                    return [
                        'id' => $e->id,
                        'achieve_marks' => $e->achieve_marks,
                        'marks' => $e->marks,
                        'contribution' => $e->contribution,
                        'attendance' => $e->attendance,
                    ];
                })->toArray(),
            ]);

            // Calculate aggregate exam marks using the same formula as the view
            $contributeOfMarks = 0;
            
            foreach ($exams as $exam) {
                if ($exam->achieve_marks !== null && $exam->marks > 0) {
                    $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                    $contributeOfMarks += (($percentOfMarks / 100) * $exam->contribution);
                }
            }

            $exam_marks = round($contributeOfMarks);

            // Find or create the subject marking record
            $subjectMarking = SubjectMarking::firstOrNew([
                'student_enroll_id' => $studentEnrollId,
                'subject_id' => $subjectId,
            ]);

            // Update exam_marks
            $subjectMarking->exam_marks = $exam_marks;

            // If it's a new record, set default values
            if (!$subjectMarking->exists) {
                $subjectMarking->attendances = 0;
                $subjectMarking->assignments = 0;
                $subjectMarking->activities = 0;
                $subjectMarking->workflow_state = SubjectMarking::STATE_DRAFT;
                $subjectMarking->created_by = auth()->id();
            }

            // Recalculate total marks if other components exist
            $subjectMarking->total_marks = 
                ($subjectMarking->exam_marks ?? 0) + 
                ($subjectMarking->attendances ?? 0) + 
                ($subjectMarking->assignments ?? 0) + 
                ($subjectMarking->activities ?? 0);

            $subjectMarking->updated_by = auth()->id();
            $subjectMarking->save();

            Log::info("ExamObserver: Recalculated subject marking", [
                'student_enroll_id' => $studentEnrollId,
                'subject_id' => $subjectId,
                'exam_marks' => $exam_marks,
                'total_marks' => $subjectMarking->total_marks,
            ]);

        } catch (\Exception $e) {
            Log::error("ExamObserver: Failed to recalculate subject marking", [
                'student_enroll_id' => $studentEnrollId,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
