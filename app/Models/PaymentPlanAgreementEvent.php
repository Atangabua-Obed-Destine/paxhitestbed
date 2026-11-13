<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing that happened to an agreement, with the schedule as it stood
 * afterwards. Written, never edited: it is the agreement's history.
 */
class PaymentPlanAgreementEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['agreement_id', 'version', 'event', 'user_id', 'reason', 'changes', 'snapshot', 'created_at'];

    protected $casts = [
        'changes' => 'array',
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function agreement()
    {
        return $this->belongsTo(PaymentPlanAgreement::class, 'agreement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getLabelAttribute(): string
    {
        return [
            'created' => __('Prepared'),
            'revised' => __('Revised'),
            'returned' => __('Sent back for approval'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'cancelled' => __('status_cancelled'),
        ][$this->event] ?? ucfirst((string) $this->event);
    }
}
