<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One attempt to pay the platform access fee through Fapshi.
 *
 * See the create migration for why this is a table of its own rather than
 * columns on platform_fee_payments, and for what the two non-Fapshi statuses
 * mean.
 */
class FapshiTransaction extends Model
{
    use HasFactory, Auditable;

    /** Still waiting on the student — a link exists and may yet be paid. */
    public const OPEN_STATUSES = ['created', 'pending'];

    /** Nothing further will happen to these. */
    public const TERMINAL_STATUSES = ['successful', 'failed', 'expired', 'mismatched', 'refundable'];

    /** Money arrived but access was not granted. Somebody has to look at these. */
    public const NEEDS_ATTENTION_STATUSES = ['mismatched', 'refundable'];

    protected $fillable = [
        'environment',
        'trans_id',
        'external_id',
        'student_id',
        'student_enroll_id',
        'session_id',
        'fee_amount',
        'charged_amount',
        'amount_received',
        'revenue',
        'currency',
        'status',
        'financial_trans_id',
        'payment_link',
        'platform_fee_payment_id',
        'failure_reason',
        'raw_request',
        'raw_response',
        'initiated_at',
        'confirmed_at',
    ];

    protected $casts = [
        'fee_amount' => 'decimal:2',
        'charged_amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'revenue' => 'decimal:2',
        'raw_request' => 'array',
        'raw_response' => 'array',
        'initiated_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function platformFeePayment()
    {
        return $this->belongsTo(PlatformFeePayment::class, 'platform_fee_payment_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Has the link Fapshi gave us passed its 24-hour life?
     *
     * Measured from initiated_at rather than created_at: the row is written
     * before the API call, so created_at can precede the link's own clock.
     */
    public function linkHasExpired(): bool
    {
        $started = $this->initiated_at ?? $this->created_at;

        if (!$started) {
            return false;
        }

        return $started->addHours((int) config('fapshi.link_lifetime_hours', 24))->isPast();
    }

    /**
     * What the gateway actually took, where that is knowable.
     *
     * Fapshi reports both what the student paid and what reaches the school;
     * the difference is its commission. fapshi:reconcile compares this against
     * the surcharge the school configured, which is the only way to find out
     * whether that figure was right.
     */
    public function commissionTaken(): ?float
    {
        if ($this->amount_received === null || $this->revenue === null) {
            return null;
        }

        return round((float) $this->amount_received - (float) $this->revenue, 2);
    }

    /** Keeps the audit log readable — see App\Traits\Auditable. */
    public function getAuditDescription($event): string
    {
        return sprintf('Platform fee payment via Fapshi: %s %s, %s (ref %s)',
            number_format((float) $this->charged_amount, 2),
            $this->currency,
            $this->status,
            $this->external_id);
    }
}
