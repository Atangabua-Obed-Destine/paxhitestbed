<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSessionQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'question',
        'answer',
        'answered_by_user_id',
        'answered_at',
        'status',
        'is_anonymous',
        'upvotes',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'is_anonymous' => 'boolean',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ANSWERED = 'answered';
    const STATUS_DISMISSED = 'dismissed';

    /**
     * Get the class session
     */
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * Get the student who asked
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the user who answered
     */
    public function answeredBy()
    {
        return $this->belongsTo(User::class, 'answered_by_user_id');
    }

    /**
     * Mark as answered
     */
    public function markAnswered($answer, $userId)
    {
        $this->update([
            'answer' => $answer,
            'answered_by_user_id' => $userId,
            'answered_at' => now(),
            'status' => self::STATUS_ANSWERED,
        ]);
    }

    /**
     * Mark as dismissed
     */
    public function dismiss()
    {
        $this->update(['status' => self::STATUS_DISMISSED]);
    }

    /**
     * Scope for pending questions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for answered questions
     */
    public function scopeAnswered($query)
    {
        return $query->where('status', self::STATUS_ANSWERED);
    }

    /**
     * Get display name (respects anonymity)
     */
    public function getDisplayNameAttribute()
    {
        if ($this->is_anonymous) {
            return 'Anonymous Student';
        }
        
        if ($this->student) {
            return $this->student->first_name . ' ' . $this->student->last_name;
        }
        
        return 'Unknown';
    }
}
