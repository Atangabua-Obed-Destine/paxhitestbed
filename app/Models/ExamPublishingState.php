<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamPublishingState extends Model
{
    use Auditable;

    // Workflow states - reusing same constants as SubjectMarking
    public const STATE_DRAFT = 'draft';
    public const STATE_SUBMITTED = 'submitted';
    public const STATE_CHECKED = 'checked';
    public const STATE_APPROVED = 'approved';
    public const STATE_PUBLISHED = 'published';

    protected $table = 'exam_publishing_states';

    protected $fillable = [
        'program_id',
        'session_id',
        'semester_id',
        'section_id',
        'subject_id',
        'exam_type_id',
        'workflow_state',
        'state_changed_at',
        'state_changed_by',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'publish_date',
        'publish_time',
        'published_by',
        'published_at',
        'total_students',
        'students_with_marks',
        'students_passed',
        'students_failed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'state_changed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'publish_date' => 'date',
        'publish_time' => 'datetime:H:i:s',
        'total_students' => 'integer',
        'students_with_marks' => 'integer',
        'students_passed' => 'integer',
        'students_failed' => 'integer',
    ];

    // Relationships
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function stateChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'state_changed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function workflowLogs(): HasMany
    {
        return $this->hasMany(ExamPublishingWorkflowLog::class, 'exam_publishing_state_id');
    }

    /**
     * Check if the exam type is a final exam.
     */
    public function isFinalExam(): bool
    {
        return $this->examType && $this->examType->is_final;
    }

    /**
     * Get the workflow state badge for UI display.
     */
    public function getStateBadge(): array
    {
        $badges = [
            self::STATE_DRAFT => ['class' => 'badge-secondary', 'icon' => 'fa-pencil-alt', 'text' => 'Draft'],
            self::STATE_SUBMITTED => ['class' => 'badge-info', 'icon' => 'fa-paper-plane', 'text' => 'Submitted'],
            self::STATE_CHECKED => ['class' => 'badge-warning', 'icon' => 'fa-check-circle', 'text' => 'Checked'],
            self::STATE_APPROVED => ['class' => 'badge-primary', 'icon' => 'fa-thumbs-up', 'text' => 'Approved'],
            self::STATE_PUBLISHED => ['class' => 'badge-success', 'icon' => 'fa-globe', 'text' => 'Published'],
        ];

        return $badges[$this->workflow_state] ?? $badges[self::STATE_DRAFT];
    }

    /**
     * Get the next possible states for this record.
     */
    public function getNextStates(): array
    {
        $transitions = [
            self::STATE_DRAFT => [self::STATE_SUBMITTED],
            self::STATE_SUBMITTED => [self::STATE_CHECKED],
            self::STATE_CHECKED => [self::STATE_APPROVED],
            self::STATE_APPROVED => [self::STATE_PUBLISHED],
            self::STATE_PUBLISHED => [],
        ];

        return $transitions[$this->workflow_state] ?? [];
    }

    /**
     * Check if transition to given state is valid.
     */
    public function canTransitionTo(string $toState): bool
    {
        return in_array($toState, $this->getNextStates());
    }

    /**
     * Get custom audit description.
     */
    public function getAuditDescription($event): string
    {
        $subjectName = $this->subject->title ?? 'Unknown Subject';
        $examTypeName = $this->examType->title ?? 'Unknown Exam Type';

        if ($event === 'created') {
            return "Publishing state created for {$subjectName} ({$examTypeName})";
        } elseif ($event === 'updated') {
            return "Publishing state updated for {$subjectName} ({$examTypeName}) - State: {$this->workflow_state}";
        }

        return "Publishing state was {$event}";
    }

    /**
     * Scope for filtering by selection criteria.
     */
    public function scopeForSelection($query, int $programId, int $sessionId, int $semesterId, ?int $sectionId, int $examTypeId)
    {
        return $query->where('program_id', $programId)
            ->where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->where('section_id', $sectionId)
            ->where('exam_type_id', $examTypeId);
    }

    /**
     * Scope for filtering by workflow state.
     */
    public function scopeInState($query, string $state)
    {
        return $query->where('workflow_state', $state);
    }
}
