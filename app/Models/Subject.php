<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Subject extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'code', 'credit_hour', 'subject_type', 'class_type', 'total_marks', 'passing_marks', 'description', 'status',
    ];

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_subject', 'subject_id', 'program_id');
    }

    public function subjectEnrolls()
    {
        return $this->belongsToMany(EnrollSubject::class, 'enroll_subject_subject', 'subject_id', 'enroll_subject_id');
    }

    public function studentEnrolls()
    {
        return $this->belongsToMany(StudentEnroll::class, 'student_enroll_subject', 'student_enroll_id', 'subject_id');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoutine::class, 'subject_id', 'id');
    }

    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class, 'subject_id', 'id');
    }

    public function examRoutines()
    {
        return $this->hasMany(ExamRoutine::class, 'subject_id', 'id');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class, 'subject_id', 'id');
    }

    public function subjectMarks()
    {
        return $this->hasMany(SubjectMarking::class, 'subject_id', 'id');
    }

    /**
     * Get class sessions for this subject
     */
    public function classSessions()
    {
        return $this->hasMany(ClassSession::class, 'subject_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $code = $this->code ?? 'N/A';
        $creditHour = $this->credit_hour ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Subject {$event}: {$title} ({$code}), Credit Hours: {$creditHour}, Status: {$status}";
    }
}
