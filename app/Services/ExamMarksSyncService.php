<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\SubjectMarking;
use App\Models\StudentAttendance;
use App\Services\ResultContributionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

/**
 * Syncs calculated marks from the exams table + student_attendances table
 * into the subject_markings table.
 *
 * This ensures that marks entered via the Exam Attendance + Exam Marking
 * workflow are reflected in the Subject Result view (which reads from
 * subject_markings).
 */
class ExamMarksSyncService
{
    /**
     * Sync subject_markings for a specific student enrollment + subject.
     *
     * Calculates contributed exam marks from the exams table and attendance
     * marks from student_attendances, then updates/creates the subject_markings record.
     *
     * @param int $studentEnrollId
     * @param int $subjectId
     * @return SubjectMarking|null
     */
    public static function sync(int $studentEnrollId, int $subjectId): ?SubjectMarking
    {
        // Get mark distribution configuration
        $contributions = ResultContributionService::getSubjectContributions($subjectId);
        if (!$contributions['configured']) {
            return null;
        }

        $attendanceContribution = (float)($contributions['attendance'] ?? 0);

        // --- Calculate exam marks (CA + Final contributed) ---
        $exams = Exam::where('student_enroll_id', $studentEnrollId)
            ->where('subject_id', $subjectId)
            ->with('type')
            ->get();

        $totalExamMarks = 0;
        foreach ($exams as $exam) {
            if ($exam->attendance != 1) continue; // Only count present students
            if ($exam->achieve_marks === null) continue; // No marks submitted
            if ($exam->contribution <= 0 || $exam->marks <= 0) continue; // No weight configured

            $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
            $contributedMarks = ($percentOfMarks / 100) * $exam->contribution;
            $totalExamMarks += $contributedMarks;
        }

        $totalExamMarks = round($totalExamMarks, 2);

        // --- Calculate attendance marks ---
        $attendanceRecords = StudentAttendance::where('student_enroll_id', $studentEnrollId)
            ->where('subject_id', $subjectId)
            ->get();

        $present = $attendanceRecords->where('attendance', 1)->count();
        $absent = $attendanceRecords->where('attendance', 2)->count();
        $leave = $attendanceRecords->where('attendance', 3)->count();
        $totalPresent = $present + $leave;
        $totalSessions = $totalPresent + $absent;

        $attendanceMarks = 0;
        if ($totalSessions > 0 && $attendanceContribution > 0) {
            $attendanceMarks = ($attendanceContribution / $totalSessions) * $totalPresent;
        }
        $attendanceMarks = round($attendanceMarks, 2);

        // --- Get or create subject_markings record ---
        $subjectMarking = SubjectMarking::firstOrNew([
            'student_enroll_id' => $studentEnrollId,
            'subject_id' => $subjectId,
        ]);

        // Preserve manually entered assignments/activities (from Subject Marking policy adjustments)
        $existingAssignments = (float)($subjectMarking->assignments ?? 0);
        $existingActivities = (float)($subjectMarking->activities ?? 0);

        // Update calculated fields
        $subjectMarking->exam_marks = $totalExamMarks;
        $subjectMarking->attendances = $attendanceMarks;

        // Total = exam + attendance + assignments + activities
        $totalMarks = round($totalExamMarks + $attendanceMarks + $existingAssignments + $existingActivities, 2);
        $subjectMarking->total_marks = $totalMarks;
        $subjectMarking->validated = ($totalMarks >= 50);

        // Set workflow state for new records
        if (!$subjectMarking->exists) {
            $subjectMarking->workflow_state = SubjectMarking::STATE_DRAFT;
            $subjectMarking->state_changed_at = Carbon::now();
            $subjectMarking->state_changed_by = Auth::guard('web')->id();
            $subjectMarking->publish_date = now()->toDateString();
        }

        $subjectMarking->updated_by = Auth::guard('web')->id();
        $subjectMarking->save();

        return $subjectMarking;
    }

    /**
     * Sync subject_markings for ALL students enrolled in a subject within a session.
     *
     * Useful for batch operations like backfilling after mark distribution is configured.
     *
     * @param int $subjectId
     * @param int|null $sessionId  If null, syncs all sessions
     * @return int Number of records synced
     */
    public static function syncAllForSubject(int $subjectId, ?int $sessionId = null): int
    {
        $query = Exam::where('subject_id', $subjectId)
            ->select('student_enroll_id')
            ->distinct();

        if ($sessionId) {
            $query->whereHas('studentEnroll', function ($q) use ($sessionId) {
                $q->where('session_id', $sessionId);
            });
        }

        $enrollIds = $query->pluck('student_enroll_id')->unique();
        $count = 0;

        foreach ($enrollIds as $enrollId) {
            $result = self::sync($enrollId, $subjectId);
            if ($result) {
                $count++;
            }
        }

        return $count;
    }
}
