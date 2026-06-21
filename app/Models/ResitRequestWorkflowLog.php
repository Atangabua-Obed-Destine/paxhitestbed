<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResitRequestWorkflowLog extends Model
{
    use Auditable;

    protected $fillable = [
        'resit_request_id',
        'from_state',
        'to_state',
        'changed_by',
        'notes',
        'changed_at',
        'meta',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function resitRequest(): BelongsTo
    {
        return $this->belongsTo(ResitRequest::class, 'resit_request_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
