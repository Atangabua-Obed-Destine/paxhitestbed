<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSessionAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'title',
        'content',
        'alert_type',
        'due_date',
        'due_time',
        'is_verified_by_lecturer',
        'verified_by_user_id',
        'upvotes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_verified_by_lecturer' => 'boolean',
    ];

    const TYPE_REMINDER = 'reminder';
    const TYPE_ASSIGNMENT = 'assignment';
    const TYPE_EXAM = 'exam';
    const TYPE_IMPORTANT = 'important';
    const TYPE_DEADLINE = 'deadline';
    const TYPE_OTHER = 'other';

    /**
     * Get alert type options
     */
    public static function getTypeOptions()
    {
        return [
            self::TYPE_REMINDER => ['label' => 'Reminder', 'icon' => 'fas fa-bell', 'color' => 'info'],
            self::TYPE_ASSIGNMENT => ['label' => 'Assignment', 'icon' => 'fas fa-tasks', 'color' => 'primary'],
            self::TYPE_EXAM => ['label' => 'Exam/Test', 'icon' => 'fas fa-clipboard-list', 'color' => 'danger'],
            self::TYPE_IMPORTANT => ['label' => 'Important', 'icon' => 'fas fa-exclamation-triangle', 'color' => 'warning'],
            self::TYPE_DEADLINE => ['label' => 'Deadline', 'icon' => 'fas fa-clock', 'color' => 'dark'],
            self::TYPE_OTHER => ['label' => 'Other', 'icon' => 'fas fa-info-circle', 'color' => 'secondary'],
        ];
    }

    /**
     * Get the class session
     */
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * Get the student who created the alert
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the user who verified the alert
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Get upvotes for this alert
     */
    public function upvoteRecords()
    {
        return $this->hasMany(ClassSessionAlertUpvote::class, 'alert_id');
    }

    /**
     * Check if a student has upvoted this alert
     */
    public function hasUpvotedBy($studentId)
    {
        return $this->upvoteRecords()->where('student_id', $studentId)->exists();
    }

    /**
     * Toggle upvote for a student
     */
    public function toggleUpvote($studentId)
    {
        $existing = $this->upvoteRecords()->where('student_id', $studentId)->first();
        
        if ($existing) {
            $existing->delete();
            $this->decrement('upvotes');
            return false; // Removed upvote
        } else {
            $this->upvoteRecords()->create(['student_id' => $studentId]);
            $this->increment('upvotes');
            return true; // Added upvote
        }
    }

    /**
     * Get type info
     */
    public function getTypeInfoAttribute()
    {
        $types = self::getTypeOptions();
        return $types[$this->alert_type] ?? $types[self::TYPE_OTHER];
    }

    /**
     * Scope for verified alerts
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified_by_lecturer', true);
    }

    /**
     * Scope for unverified alerts
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified_by_lecturer', false);
    }

    /**
     * Scope for upcoming alerts with due dates
     */
    public function scopeUpcoming($query)
    {
        return $query->whereNotNull('due_date')
                    ->where('due_date', '>=', now()->toDateString())
                    ->orderBy('due_date');
    }
}
