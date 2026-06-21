<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class StudentCredit extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_id',
        'original_amount',
        'remaining_amount',
        'source_fee_id',
        'source_type',
        'status',
        'note',
        'refund_requested',
        'refund_requested_at',
        'refund_requested_by',
        'refund_approved',
        'refund_approved_at',
        'refund_approved_by',
        'refund_processed_at',
        'refund_processed_by',
        'refund_method',
        'refund_reference',
        'refund_note',
        'refund_rejected_at',
        'refund_rejected_by',
        'refund_rejected_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'original_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'refund_requested' => 'boolean',
        'refund_approved' => 'boolean',
        'refund_requested_at' => 'datetime',
        'refund_approved_at' => 'datetime',
        'refund_processed_at' => 'datetime',
        'refund_rejected_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_AVAILABLE = 'available';
    const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    const STATUS_FULLY_APPLIED = 'fully_applied';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_EXPIRED = 'expired';

    /**
     * Source type constants
     */
    const SOURCE_OVERPAYMENT = 'overpayment';
    const SOURCE_REFUND_REVERSAL = 'refund_reversal';
    const SOURCE_ADMIN_ADJUSTMENT = 'admin_adjustment';
    const SOURCE_TRANSFER = 'transfer';

    /**
     * Refund method constants
     */
    const REFUND_CASH = 'cash';
    const REFUND_BANK_TRANSFER = 'bank_transfer';
    const REFUND_CHEQUE = 'cheque';
    const REFUND_MOBILE_MONEY = 'mobile_money';

    /**
     * Get the student that owns the credit.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the source fee that generated this credit (for overpayments).
     */
    public function sourceFee()
    {
        return $this->belongsTo(Fee::class, 'source_fee_id');
    }

    /**
     * Get the applications of this credit to fees.
     */
    public function applications()
    {
        return $this->hasMany(CreditApplication::class, 'student_credit_id');
    }

    /**
     * Get the user who created this credit.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this credit.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who requested the refund.
     */
    public function refundRequestedBy()
    {
        return $this->belongsTo(User::class, 'refund_requested_by');
    }

    /**
     * Get the user who approved the refund.
     */
    public function refundApprovedBy()
    {
        return $this->belongsTo(User::class, 'refund_approved_by');
    }

    /**
     * Get the user who processed the refund.
     */
    public function refundProcessedBy()
    {
        return $this->belongsTo(User::class, 'refund_processed_by');
    }

    /**
     * Get the user who rejected the refund.
     */
    public function refundRejectedBy()
    {
        return $this->belongsTo(User::class, 'refund_rejected_by');
    }

    /**
     * Scope: refund requests that were rejected.
     */
    public function scopeRejectedRefund($query)
    {
        return $query->whereNotNull('refund_rejected_at');
    }

    /**
     * Scope to get only available credits.
     */
    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_APPLIED])
                     ->where('remaining_amount', '>', 0);
    }

    /**
     * Scope to get credits pending refund.
     */
    public function scopePendingRefund($query)
    {
        return $query->where('refund_requested', true)
                     ->where('refund_approved', false)
                     ->whereNull('refund_processed_at');
    }

    /**
     * Scope to get approved but not processed refunds.
     */
    public function scopeApprovedRefund($query)
    {
        return $query->where('refund_approved', true)
                     ->whereNull('refund_processed_at');
    }

    /**
     * Check if credit has available balance.
     */
    public function hasAvailableBalance()
    {
        return $this->remaining_amount > 0 && 
               in_array($this->status, [self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_APPLIED]);
    }

    /**
     * Check if credit can be refunded.
     */
    public function canBeRefunded()
    {
        return $this->remaining_amount > 0 &&
               !$this->refund_requested &&
               !$this->refund_approved &&
               is_null($this->refund_processed_at) &&
               $this->status !== self::STATUS_REFUNDED;
    }

    /**
     * Single source of truth for the refund lifecycle, derived from boolean
     * + timestamp columns. The `status` enum only encodes the wallet state
     * (available/applied/refunded), so views must use this for refund UX.
     *
     * @return string one of: none|requested|approved|rejected|processed
     */
    public function getRefundStateAttribute(): string
    {
        if ($this->refund_processed_at) {
            return 'processed';
        }
        if ($this->refund_approved) {
            return 'approved';
        }
        if ($this->refund_requested) {
            return 'requested';
        }
        if ($this->refund_rejected_at) {
            return 'rejected';
        }
        return 'none';
    }

    /**
     * Convenience accessor: "First Last" without forcing the view to
     * remember the column shape (Student has no `name` accessor).
     */
    public function getStudentFullNameAttribute(): string
    {
        if (!$this->relationLoaded('student') && !$this->student) {
            return 'N/A';
        }
        $s = $this->student;
        if (!$s) {
            return 'N/A';
        }
        return trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ?: 'N/A';
    }

    /**
     * Get the applied amount.
     */
    public function getAppliedAmountAttribute()
    {
        return $this->original_amount - $this->remaining_amount;
    }

    /**
     * Get status badge HTML.
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            self::STATUS_AVAILABLE => '<span class="badge badge-success">Available</span>',
            self::STATUS_PARTIALLY_APPLIED => '<span class="badge badge-info">Partially Applied</span>',
            self::STATUS_FULLY_APPLIED => '<span class="badge badge-primary">Fully Applied</span>',
            self::STATUS_REFUNDED => '<span class="badge badge-warning">Refunded</span>',
            self::STATUS_EXPIRED => '<span class="badge badge-secondary">Expired</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }

    /**
     * Get refund status badge HTML.
     */
    public function getRefundStatusBadgeAttribute()
    {
        if ($this->refund_processed_at) {
            return '<span class="badge badge-success">Refund Completed</span>';
        }
        if ($this->refund_approved) {
            return '<span class="badge badge-info">Refund Approved - Pending Processing</span>';
        }
        if ($this->refund_requested) {
            return '<span class="badge badge-warning">Refund Pending Approval</span>';
        }
        return '';
    }

    /**
     * Get source type label.
     */
    public function getSourceTypeLabelAttribute()
    {
        $labels = [
            self::SOURCE_OVERPAYMENT => 'Overpayment',
            self::SOURCE_REFUND_REVERSAL => 'Refund Reversal',
            self::SOURCE_ADMIN_ADJUSTMENT => 'Admin Adjustment',
            self::SOURCE_TRANSFER => 'Transfer',
        ];

        return $labels[$this->source_type] ?? 'Unknown';
    }

    /**
     * Custom audit description.
     */
    public function getAuditDescription($event)
    {
        $studentName = 'Unknown Student';
        if ($this->student) {
            $studentName = trim($this->student->first_name . ' ' . $this->student->last_name);
        }

        $amount = number_format($this->original_amount, 2);

        if ($event === 'created') {
            return "Credit of {$amount} created for {$studentName} ({$this->source_type_label})";
        } elseif ($event === 'updated') {
            if ($this->refund_processed_at && $this->wasChanged('refund_processed_at')) {
                return "Refund of {$amount} processed for {$studentName}";
            }
            if ($this->refund_approved && $this->wasChanged('refund_approved')) {
                return "Refund request approved for {$studentName}";
            }
            if ($this->refund_requested && $this->wasChanged('refund_requested')) {
                return "Refund requested for {$studentName}";
            }
            return "Credit updated for {$studentName}";
        } elseif ($event === 'deleted') {
            return "Credit deleted for {$studentName}";
        }

        return "Credit was {$event}";
    }
}
