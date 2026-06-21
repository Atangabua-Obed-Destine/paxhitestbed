<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class ClassLogbook extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'class_session_id',
        'topic_covered',
        'content_summary',
        'learning_objectives',
        'teaching_methods',
        'materials_used',
        'assignments_given',
        'remarks',
        'class_rep_student_id',
        'hod_user_id',
        'is_completed',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the class session
     */
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * Get the class representative student
     */
    public function classRep()
    {
        return $this->belongsTo(StudentEnroll::class, 'class_rep_student_id');
    }

    /**
     * Get the HOD user
     */
    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }

    /**
     * Get the user who created this entry
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this entry
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for completed logbooks
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope for incomplete logbooks
     */
    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Mark the logbook as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Check if logbook has minimum required content
     */
    public function hasMinimumContent()
    {
        return !empty($this->topic_covered);
    }
}
