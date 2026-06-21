<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class StudentAttendance extends Model
{
    use Auditable;

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_enroll_id', 'subject_id', 'date', 'time', 'attendance', 'note', 'created_by', 'updated_by',
    ];

    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id', 'id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * Get custom audit description.
     *
     * @param string $event
     * @return string
     */
    public function getAuditDescription($event)
    {
        $student = $this->studentEnroll->student ?? null;
        $studentName = $student ? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) : 'Unknown Student';
        if (empty(trim($studentName))) $studentName = 'Unknown Student';
        $subjectName = $this->subject->title ?? 'Unknown Subject';
        $attendanceStatus = $this->attendance == 1 ? 'Present' : ($this->attendance == 2 ? 'Absent' : 'Leave');

        if ($event === 'created') {
            return "Attendance marked for {$studentName} in {$subjectName}: {$attendanceStatus} on {$this->date}";
        } elseif ($event === 'updated') {
            return "Attendance updated for {$studentName} in {$subjectName}: {$attendanceStatus} on {$this->date}";
        }

        return "Student attendance was {$event}";
    }
}

