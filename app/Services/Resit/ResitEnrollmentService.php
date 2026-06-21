<?php

namespace App\Services\Resit;

use App\Models\ResitRequest;
use App\Models\StudentEnroll;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ResitEnrollmentService
{
    /**
     * Ensure the student has an enrollment in the selected resit semester and subject.
     */
    public function ensureEnrollment(ResitRequest $resitRequest): StudentEnroll
    {
        if (!$resitRequest->resit_session_id || !$resitRequest->resit_semester_id) {
            throw ValidationException::withMessages([
                'resit_session_id' => __('Resit session and semester must be selected before scheduling.'),
            ]);
        }

        $originalEnroll = $resitRequest->studentEnroll;
        if (!$originalEnroll) {
            throw ValidationException::withMessages([
                'student_enroll_id' => __('Unable to locate the original enrollment for this resit request.'),
            ]);
        }

        $subjectId = $resitRequest->subject_id;
        if (!$subjectId) {
            throw ValidationException::withMessages([
                'subject_id' => __('Resit request is missing the associated subject.'),
            ]);
        }

        $studentId = $originalEnroll->student_id;
        $programId = $originalEnroll->program_id;
        $sectionId = $originalEnroll->section_id;
        $sessionId = $resitRequest->resit_session_id;
        $semesterId = $resitRequest->resit_semester_id;
        $actorId = Auth::guard('web')->id();

        $enroll = $resitRequest->resitEnroll;
        if (!$enroll) {
            $enroll = StudentEnroll::where('student_id', $studentId)
                ->where('program_id', $programId)
                ->where('session_id', $sessionId)
                ->where('semester_id', $semesterId)
                ->where('section_id', $sectionId)
                ->first();

            if (!$enroll) {
                $enroll = new StudentEnroll();
                $enroll->student_id = $studentId;
                $enroll->program_id = $programId;
                $enroll->session_id = $sessionId;
                $enroll->semester_id = $semesterId;
                $enroll->section_id = $sectionId;
                $enroll->status = 0; // keep primary enrollment unchanged
                
                // Copy matricule from original enrollment
                $enroll->matricule = $originalEnroll->matricule;
                
                $enroll->created_by = $actorId;
                $enroll->updated_by = $actorId;
                $enroll->save();
            }

            $resitRequest->resit_enroll_id = $enroll->id;
            $resitRequest->save();
        }

        $enroll->subjects()->syncWithoutDetaching([$subjectId]);

        return $enroll;
    }
}
