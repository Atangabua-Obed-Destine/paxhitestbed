<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class PaymentPlan extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fee_id',
        'student_id',
        'total_amount',
        'installments_count',
        'late_fee_percentage',
        'grace_period_days',
        'created_by',
        'approved_by',
        'approved_at',
        'status',
        'notes',
        'cancellation_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'late_fee_percentage' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the fee that this payment plan belongs to.
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Get the student that this payment plan belongs to.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who created this payment plan.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this payment plan.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all installments for this payment plan.
     */
    public function installments()
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('installment_number');
    }

    /**
     * Get total amount paid across all installments.
     */
    public function getTotalPaidAttribute()
    {
        return $this->installments->sum('paid_amount') ?? 0;
    }

    /**
     * Get total remaining balance.
     */
    public function getRemainingBalanceAttribute()
    {
        return max(0, $this->total_amount - $this->total_paid);
    }

    /**
     * Get payment progress percentage.
     */
    public function getProgressPercentageAttribute()
    {
        if ($this->total_amount == 0) {
            return 0;
        }
        return round(($this->total_paid / $this->total_amount) * 100, 2);
    }

    /**
     * Check if payment plan is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if payment plan is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment plan is cancelled.
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if payment plan has defaulted.
     */
    public function isDefaulted()
    {
        return $this->status === 'defaulted';
    }

    /**
     * Get next upcoming installment.
     */
    public function getNextInstallmentAttribute()
    {
        return $this->installments()
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->first();
    }

    /**
     * Get overdue installments.
     */
    public function getOverdueInstallmentsAttribute()
    {
        return $this->installments()
            ->where('status', 'overdue')
            ->get();
    }

    /**
     * Get overdue count.
     */
    public function getOverdueCountAttribute()
    {
        return $this->overdue_installments->count();
    }

    /**
     * Mark payment plan as completed.
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
        ]);
    }

    /**
     * Cancel payment plan.
     */
    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        // Clear payment plan link from fee to allow direct payment again
        if ($this->fee) {
            $this->fee->update(['payment_plan_id' => null]);
        }
    }

    /**
     * Mark as defaulted.
     */
    public function markAsDefaulted()
    {
        $this->update([
            'status' => 'defaulted',
        ]);
    }

    /**
     * Get status badge HTML.
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'active' => '<span class="badge badge-success">Active</span>',
            'completed' => '<span class="badge badge-primary">Completed</span>',
            'cancelled' => '<span class="badge badge-danger">Cancelled</span>',
            'defaulted' => '<span class="badge badge-warning">Defaulted</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }
}
