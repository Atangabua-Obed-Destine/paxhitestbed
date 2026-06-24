<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Exam extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_enroll_id', 'subject_id', 'exam_type_id', 'date', 'time', 'attendance', 'sign_in', 'sign_out', 'attendance_locked', 'bypass_course_attendance', 'bypassed_by', 'bypassed_at', 'marks', 'achieve_marks', 'marks_locked', 'contribution', 'note', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'sign_in' => 'boolean',
        'sign_out' => 'boolean',
    ];

    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id', 'id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function type()
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    public function scriptCode()
    {
        return $this->hasOne(ExamScriptCode::class, 'exam_id');
    }

    public function bypassedBy()
    {
        return $this->belongsTo(\App\User::class, 'bypassed_by');
    }
}
