<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultAccessBlock extends Model
{
    protected $fillable = [
        'student_id',
        'program_id',
        'session_id',
        'semester_id',
        'is_active',
        'reason',
        'blocked_by',
        'blocked_at',
        'unblocked_by',
        'unblocked_at',
        'unblock_reason',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'blocked_at'   => 'datetime',
        'unblocked_at' => 'datetime',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function blockedBy()
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function unblockedBy()
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    /**
     * Quick check whether a student's results for a given enrollment context
     * are currently blocked from being viewed in the student portal.
     */
    public static function isBlocked($studentId, $programId, $sessionId, $semesterId): bool
    {
        if (!$studentId || !$programId || !$sessionId || !$semesterId) {
            return false;
        }
        return static::query()
            ->where('student_id', $studentId)
            ->where('program_id', $programId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Return the active block row (with reason, blocker etc.) if any.
     */
    public static function activeBlockFor($studentId, $programId, $sessionId, $semesterId): ?self
    {
        if (!$studentId || !$programId || !$sessionId || !$semesterId) {
            return null;
        }
        return static::query()
            ->where('student_id', $studentId)
            ->where('program_id', $programId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }
}
