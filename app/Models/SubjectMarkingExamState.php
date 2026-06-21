<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectMarkingExamState extends Model
{
    use Auditable;

    protected $fillable = [
        'subject_marking_id',
        'exam_type_id',
        'workflow_state',
        'state_changed_at',
        'state_changed_by',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'publish_date',
        'publish_time',
    ];

    protected $casts = [
        'state_changed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'publish_date' => 'date',
        'publish_time' => 'datetime:H:i:s',
    ];

    public function subjectMarking(): BelongsTo
    {
        return $this->belongsTo(SubjectMarking::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }
}
