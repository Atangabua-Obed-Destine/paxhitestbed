<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamPublishingWorkflowLog extends Model
{
    protected $table = 'exam_publishing_workflow_logs';

    protected $fillable = [
        'exam_publishing_state_id',
        'from_state',
        'to_state',
        'action',
        'changed_by',
        'notes',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function examPublishingState(): BelongsTo
    {
        return $this->belongsTo(ExamPublishingState::class, 'exam_publishing_state_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
