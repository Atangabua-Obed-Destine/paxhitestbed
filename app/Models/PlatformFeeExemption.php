<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PlatformFeeExemption extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'exemption_type',
        'student_enroll_id',
        'student_id',
        'session_id',
        'reason',
        'created_by',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * A student exemption means a student for a session. One granted against
     * an enrolment, as older code and older rows do, is read as that
     * enrolment's student and session, so the two can never disagree.
     */
    protected static function booted(): void
    {
        static::saving(function (PlatformFeeExemption $exemption) {
            if ($exemption->exemption_type !== 'student' || !$exemption->student_enroll_id) {
                return;
            }

            $enrollment = StudentEnroll::find($exemption->student_enroll_id);
            if (!$enrollment) {
                return;
            }

            $exemption->student_id = $exemption->student_id ?: $enrollment->student_id;
            $exemption->session_id = $exemption->session_id ?: $enrollment->session_id;
        });
    }

    /**
     * Active exemptions that excuse this student in this session: a session
     * exemption covering everybody, or one granted to the student.
     */
    public function scopeCovering($query, int $studentId, ?int $sessionId)
    {
        return $query->where('status', 1)->where(function ($q) use ($studentId, $sessionId) {
            $q->where(function ($session) use ($sessionId) {
                $session->where('exemption_type', 'session')->where('session_id', $sessionId);
            })->orWhere(function ($student) use ($studentId, $sessionId) {
                $student->where('exemption_type', 'student')->where('student_id', $studentId)
                    ->where('session_id', $sessionId);
            });
        });
    }

    /**
     * The enrolment an older exemption was granted against. History only:
     * the exemption is read from student_id and session_id.
     */
    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    /** The student excused (if exemption_type = student). */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The academic session: everyone in it for a session exemption, the one
     * the student is excused for otherwise.
     */
    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the user who created the exemption.
     */
    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Scope to get active exemptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get student exemptions.
     */
    public function scopeForStudent($query)
    {
        return $query->where('exemption_type', 'student');
    }

    /**
     * Scope to get session exemptions.
     */
    public function scopeForSession($query)
    {
        return $query->where('exemption_type', 'session');
    }
}
