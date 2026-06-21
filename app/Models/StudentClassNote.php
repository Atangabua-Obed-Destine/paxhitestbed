<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentClassNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'student_enroll_id',
        'content',
        'title',
        'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    /**
     * Get the class session
     */
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * Get the student
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the student enrollment
     */
    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    /**
     * Get or create note for a student in a class session
     */
    public static function getOrCreate($classSessionId, $studentId, $studentEnrollId = null)
    {
        return static::firstOrCreate(
            [
                'class_session_id' => $classSessionId,
                'student_id' => $studentId,
            ],
            [
                'student_enroll_id' => $studentEnrollId,
                'content' => '',
                'title' => '',
            ]
        );
    }
}
