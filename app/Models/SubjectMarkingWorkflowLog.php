<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectMarkingWorkflowLog extends Model
{
    use Auditable;

    protected $fillable = [
    'subject_marking_id',
    'subject_marking_exam_state_id',
    'exam_type_id',
        'from_state',
        'to_state',
        'changed_by',
        'notes',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function subjectMarking(): BelongsTo
    {
        return $this->belongsTo(SubjectMarking::class, 'subject_marking_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function examState(): BelongsTo
    {
        return $this->belongsTo(SubjectMarkingExamState::class, 'subject_marking_exam_state_id');
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }
}
