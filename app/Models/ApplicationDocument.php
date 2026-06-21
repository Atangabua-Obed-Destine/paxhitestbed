<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class ApplicationDocument extends Model
{
    use Auditable;
    protected $fillable = [
        'application_id',
        'document_type',
        'file_path',
        'is_received',
        'is_optional',
        'needs_resubmission',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'resubmitted_at',
        'notes',
    ];

    protected $casts = [
        'is_received' => 'boolean',
        'is_optional' => 'boolean',
        'needs_resubmission' => 'boolean',
        'rejected_at' => 'datetime',
        'resubmitted_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function rejectedByUser()
    {
        return $this->belongsTo(\App\User::class, 'rejected_by');
    }

    /**
     * Check if document needs attention from applicant
     */
    public function needsAttention(): bool
    {
        return $this->needs_resubmission && !$this->resubmitted_at;
    }
}
