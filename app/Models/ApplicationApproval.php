<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One decision taken on an application, by one person, at one step.
 *
 * Rows are appended and never changed or deleted. An approval that is later
 * returned stays on file alongside the return, so the history reads like the
 * paper file it replaces: who signed, when, and what happened afterwards.
 *
 * signed_name and signed_position are copied in at the moment of signing rather
 * than read back through the user. A registrar who later changes post, or
 * leaves the school, must not silently rewrite the record of an admission they
 * approved two years ago.
 */
class ApplicationApproval extends Model
{
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const RETURNED = 'returned';

    protected $fillable = [
        'application_id',
        'step',
        'decision',
        'decided_by',
        'signed_name',
        'signed_position',
        'returned_to_step',
        'note',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(\App\User::class, 'decided_by');
    }

    public function stepTitle(): string
    {
        return Application::approvalStepTitle($this->step);
    }

    /**
     * Who to name on the record. The snapshot first, because it is what was
     * true at the time; the user only as a fallback for rows written before
     * there was a snapshot to take.
     */
    public function signatory(): string
    {
        if (filled($this->signed_name)) {
            return $this->signed_name;
        }

        return (string) (optional($this->decidedBy)->name ?: __('Unknown'));
    }
}
