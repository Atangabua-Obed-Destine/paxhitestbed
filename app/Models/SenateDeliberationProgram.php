<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenateDeliberationProgram extends Model
{
    use Auditable;

    protected $table = 'senate_deliberation_programs';

    protected $fillable = [
        'senate_deliberation_id',
        'program_id',
        'faculty_id',
        'decision',
        'remarks',
        'conditions',
        'action_items',
        'total_students',
        'total_passed',
        'total_failed',
        'average_gpa',
        'pass_rate',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'average_gpa' => 'decimal:2',
        'pass_rate'   => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    /* ─── Relationships ─── */

    public function deliberation(): BelongsTo
    {
        return $this->belongsTo(SenateDeliberation::class, 'senate_deliberation_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ─── Helpers ─── */

    public function getFailRateAttribute(): float
    {
        return $this->total_students > 0
            ? round(($this->total_failed / $this->total_students) * 100, 2)
            : 0;
    }

    public function getDecisionLabelAttribute(): string
    {
        return SenateDeliberation::decisionLabels()[$this->decision] ?? ucfirst($this->decision);
    }

    public function getDecisionBadgeAttribute(): string
    {
        return SenateDeliberation::decisionBadgeClass($this->decision);
    }
}
