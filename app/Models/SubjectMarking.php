<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectMarking extends Model
{
    use Auditable;

    public const STATE_DRAFT = 'draft';
    public const STATE_SUBMITTED = 'submitted';
    public const STATE_CHECKED = 'checked';
    public const STATE_APPROVED = 'approved';
    public const STATE_PUBLISHED = 'published';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_enroll_id',
        'subject_id',
        'exam_marks',
        'attendances',
        'assignments',
        'activities',
        'total_marks',
        'publish_date',
        'publish_time',
        'status',
        'workflow_state',
        'state_changed_at',
        'state_changed_by',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'resolved_exam_weight',
        'resolved_ca_weight',
        'resolved_attendance_weight',
        'validated',
        'created_by',
        'updated_by',
        'is_published_override',
        'unpublish_reason',
        'unpublished_by',
        'unpublished_at',
        'republished_by',
        'republished_at',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'publish_time' => 'datetime:H:i:s',
        'state_changed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'resolved_exam_weight' => 'float',
        'resolved_ca_weight' => 'float',
        'resolved_attendance_weight' => 'float',
        'validated' => 'boolean',
        'is_published_override' => 'boolean',
        'unpublished_at' => 'datetime',
        'republished_at' => 'datetime',
    ];

    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function workflowLogs()
    {
        return $this->hasMany(SubjectMarkingWorkflowLog::class, 'subject_marking_id');
    }

    public function examStates(): HasMany
    {
        return $this->hasMany(SubjectMarkingExamState::class);
    }

    public function stateChanger()
    {
        return $this->belongsTo(User::class, 'state_changed_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function unpublisher()
    {
        return $this->belongsTo(User::class, 'unpublished_by');
    }

    public function republisher()
    {
        return $this->belongsTo(User::class, 'republished_by');
    }

    public function publishLogs()
    {
        return $this->hasMany(SubjectMarkingPublishLog::class, 'subject_marking_id');
    }

    /**
     * Check if result is visible to student.
     * Logic: 
     * - If override is TRUE, force visible regardless of workflow
     * - If override is FALSE, force hidden regardless of workflow
     * - If override is NULL, follow workflow_state (must be 'published')
     *   AND publish_date/publish_time must be in the past
     *
     * @return bool
     */
    public function getIsVisibleToStudentAttribute()
    {
        // If override exists, use it directly
        if ($this->is_published_override === true) {
            return true;
        }
        if ($this->is_published_override === false) {
            return false;
        }
        
        // No override - follow workflow
        if ($this->workflow_state !== self::STATE_PUBLISHED) {
            return false;
        }
        
        // Workflow is published - now check if publish date/time has passed
        if ($this->publish_date && $this->publish_time) {
            $publishDateTime = strtotime($this->publish_date->format('Y-m-d') . ' ' . $this->publish_time->format('H:i:s'));
            return time() >= $publishDateTime;
        }
        
        // If publish_date is set but no publish_time, check date only
        if ($this->publish_date) {
            return strtotime($this->publish_date->format('Y-m-d')) <= strtotime(date('Y-m-d'));
        }
        
        // No publish date set - if workflow is published, show immediately
        return true;
    }

    /**
     * Get the publish status badge for UI display.
     *
     * @return array
     */
    public function getPublishStatusBadge()
    {
        if ($this->is_published_override === false) {
            return [
                'text' => 'Unpublished',
                'class' => 'badge-danger',
                'icon' => 'fa-lock',
            ];
        }
        
        if ($this->is_published_override === true) {
            return [
                'text' => 'Force Published',
                'class' => 'badge-success',
                'icon' => 'fa-unlock',
            ];
        }
        
        // Follow workflow
        if ($this->workflow_state === self::STATE_PUBLISHED) {
            return [
                'text' => 'Published',
                'class' => 'badge-success',
                'icon' => 'fa-check',
            ];
        }
        
        return [
            'text' => ucfirst($this->workflow_state),
            'class' => 'badge-secondary',
            'icon' => 'fa-clock',
        ];
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

        if ($event === 'created') {
            return "Marks submitted for {$studentName} in {$subjectName} (Total: {$this->total_marks})";
        } elseif ($event === 'updated') {
            return "Marks updated for {$studentName} in {$subjectName} (Total: {$this->total_marks})";
        } elseif ($event === 'deleted') {
            return "Marks deleted for {$studentName} in {$subjectName}";
        }

        return "Subject marking was {$event}";
    }
}

