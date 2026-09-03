<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\TranscriptRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Captures a transcript exactly as it is about to be printed.
 *
 * Verification is only worth anything if it checks the marks, not merely that
 * the student exists — otherwise an altered PDF still scans as genuine. So the
 * figures are recorded at the moment of issue and the QR code points at them.
 *
 * The rules here mirror the transcript view's own: a mark counts only when it
 * is visible to the student, its grade is the band whose range contains it, and
 * credits are earned only when that grade carries points. They are asserted
 * equal in scripts/transcript_verification_test.php, so the stored snapshot and
 * the printed page cannot drift apart unnoticed.
 */
class TranscriptSnapshotService
{
    /**
     * The figures the transcript shows, for one student on one programme.
     *
     * @return array{courses: array, cumulative_gpa: float, total_credits: float,
     *               credits_earned: float, total_courses: int, standing: string}
     */
    public function build(Student $student, ?StudentEnroll $currentEnroll): array
    {
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        $selectedProgramId = $currentEnroll?->program_id ?? $student->program_id;
        $matricule = $currentEnroll?->matricule;

        $courses = [];
        $uniqueCourses = [];
        $totalCredits = 0.0;
        $totalPoints = 0.0;
        $creditsEarned = 0.0;
        $totalCourses = 0;

        foreach ($student->studentEnrolls as $enroll) {
            // The same enrolment filter the view applies: this programme, and
            // this matricule. A student who transferred carries more than one.
            if ($enroll->program_id != $selectedProgramId || $enroll->matricule != $matricule) {
                continue;
            }

            foreach ($enroll->subjectMarks ?? [] as $mark) {
                if (!$mark->is_visible_to_student) {
                    continue;
                }

                $scored = round($mark->total_marks);
                $credit = (float) ($mark->subject->credit_hour ?? 0);
                $grade = $this->gradeFor($grades, $scored);

                if (!$grade) {
                    continue;
                }

                $totalPoints += $grade->point * $credit;
                $totalCredits += $credit;

                if ($grade->point > 0) {
                    $creditsEarned += $credit;
                }

                if (!isset($uniqueCourses[$mark->subject_id])) {
                    $uniqueCourses[$mark->subject_id] = true;
                    $totalCourses++;
                }

                $courses[] = [
                    'session' => $enroll->session->title ?? null,
                    'semester' => $enroll->semester->title ?? null,
                    'code' => $mark->subject->code ?? null,
                    'title' => $mark->subject->title ?? null,
                    'credit' => $credit,
                    'grade' => $grade->title ?? null,
                    'point' => (float) $grade->point,
                    'quality_points' => $grade->point * $credit,
                ];
            }
        }

        $gpa = $totalCredits > 0 ? $totalPoints / $totalCredits : 0.0;

        return [
            'courses' => $courses,
            'cumulative_gpa' => round($gpa, 2),
            'total_credits' => $totalCredits,
            'credits_earned' => $creditsEarned,
            'total_courses' => $totalCourses,
            'standing' => $this->standing($gpa, $totalCourses),
        ];
    }

    /**
     * Record this transcript as issued, and hand back the record the QR points
     * at. Re-opening the same transcript reuses its record rather than minting
     * a second code for a document that already has one.
     */
    public function record(Student $student, ?StudentEnroll $currentEnroll): ?TranscriptRecord
    {
        if (!$currentEnroll) {
            return null;
        }

        $snapshot = $this->build($student, $currentEnroll);

        return TranscriptRecord::updateOrCreate(
            [
                'student_id' => $student->id,
                'student_enroll_id' => $currentEnroll->id,
            ],
            [
                'program_id' => $currentEnroll->program_id,
                'courses_snapshot' => $snapshot['courses'],
                'matricule' => $currentEnroll->matricule ?? $student->student_id,
                'student_name' => trim($student->first_name . ' ' . $student->last_name),
                'programme_name' => $currentEnroll->program->title ?? null,
                'cumulative_gpa' => $snapshot['cumulative_gpa'],
                'total_credits' => $snapshot['total_credits'],
                'credits_earned' => $snapshot['credits_earned'],
                'total_courses' => $snapshot['total_courses'],
                'standing' => $snapshot['standing'],
                'issued_at' => now(),
                'issued_by' => Auth::id(),
            ]
        );
    }

    /** The band whose range contains the mark, highest first. */
    protected function gradeFor($grades, $scored)
    {
        foreach ($grades as $grade) {
            if ($scored >= $grade->min_mark && $scored <= $grade->max_mark) {
                return $grade;
            }
        }

        return null;
    }

    /** The wording printed in the Standing box. */
    protected function standing(float $gpa, int $courses): string
    {
        if ($gpa >= 3.6) return 'First Class (Distinction)';
        if ($gpa >= 3.0) return 'Second Class Upper';
        if ($gpa >= 2.5) return 'Second Class Lower';
        if ($gpa >= 2.0) return 'Third Class';
        if ($gpa >= 1.0) return 'Pass';

        return $courses > 0 ? 'Fail' : 'N/A';
    }
}
