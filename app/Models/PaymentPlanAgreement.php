<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment plan agreement: the document the bursar prepares, the student
 * signs and the Vice Chancellor approves.
 *
 * It covers one or more fees. Each fee keeps its own PaymentPlan underneath —
 * that is what money is paid against — and this ties them together under one
 * reference, one approval and one printed document.
 *
 * See App\Services\PaymentPlanAgreements for everything that changes one.
 */
class PaymentPlanAgreement extends Model
{
    use Auditable;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'reference', 'student_id', 'status', 'version', 'notes',
        'created_by', 'approved_by', 'approved_at', 'approved_version',
        'rejected_by', 'rejected_at', 'rejection_reason', 'review_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'version' => 'integer',
        'approved_version' => 'integer',
    ];

    public function plans()
    {
        return $this->hasMany(PaymentPlan::class, 'agreement_id')->orderBy('id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function events()
    {
        return $this->hasMany(PaymentPlanAgreementEvent::class, 'agreement_id')->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::REJECTED, self::CANCELLED], true);
    }

    /** Has it ever been approved? A pending one that has, is back for re-approval. */
    public function wasApproved(): bool
    {
        return $this->approved_version !== null;
    }

    /** Every instalment of every plan, in the order they fall due. */
    public function timeline()
    {
        return $this->plans->flatMap(function (PaymentPlan $plan) {
            return $plan->installments->map(fn ($installment) => [
                'plan' => $plan,
                'installment' => $installment,
                'fee' => $plan->fee,
            ]);
        })->sortBy(fn ($row) => [
            optional($row['installment']->due_date)->format('Y-m-d'),
            $row['plan']->id,
            $row['installment']->installment_number,
        ])->values();
    }

    public function getTotalAttribute(): float
    {
        return round((float) $this->plans->sum(fn ($plan) => (float) $plan->total_amount), 2);
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->isPending()) {
            return $this->wasApproved() ? __('Awaiting re-approval') : __('Awaiting approval');
        }

        if ($this->isApproved() && $this->plans->isNotEmpty()
            && $this->plans->every(fn ($plan) => $plan->status === 'completed')) {
            return __('status_completed');
        }

        return [
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
            self::CANCELLED => __('status_cancelled'),
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusClassAttribute(): string
    {
        return [
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'secondary',
        ][$this->status] ?? 'secondary';
    }
}
