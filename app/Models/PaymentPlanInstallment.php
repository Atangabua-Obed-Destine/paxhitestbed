<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use Carbon\Carbon;

class PaymentPlanInstallment extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_plan_id',
        'installment_number',
        'amount',
        'due_date',
        'paid_amount',
        'late_fee',
        'status',
        'grace_period_ends',
        'paid_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'due_date' => 'date',
        'grace_period_ends' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the payment plan this installment belongs to.
     */
    public function paymentPlan()
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    /**
     * Get all payments for this installment.
     */
    public function payments()
    {
        return $this->hasMany(PaymentPlanPayment::class, 'installment_id');
    }

    /**
     * Get all payment receipts submitted by students for this installment.
     */
    public function paymentReceipts()
    {
        return $this->hasMany(InstallmentPaymentReceipt::class, 'installment_id');
    }

    /**
     * Get pending payment receipts for this installment.
     */
    public function pendingReceipts()
    {
        return $this->hasMany(InstallmentPaymentReceipt::class, 'installment_id')->where('status', 'pending');
    }

    /**
     * Get the most recent pending receipt for this installment.
     */
    public function getPendingReceiptAttribute()
    {
        return $this->paymentReceipts()->where('status', 'pending')->latest()->first();
    }

    /**
     * Get pending multi-payment distributions for this installment.
     */
    public function pendingMultiPaymentDistributions()
    {
        return $this->hasMany(\App\Models\MultiPaymentDistribution::class, 'installment_id')
            ->whereHas('multiPayment', function($query) {
                $query->where('status', 'pending');
            })
            ->where('amount_applied', '>', 0);
    }

    /**
     * Check if installment has pending multi-payment.
     */
    public function hasPendingMultiPayment()
    {
        return $this->pendingMultiPaymentDistributions()->exists();
    }

    /**
     * Get pending multi-payment total amount for this installment.
     */
    public function getPendingMultiPaymentAmount()
    {
        return $this->pendingMultiPaymentDistributions()->sum('amount_applied');
    }

    /**
     * Get remaining balance for this installment.
     */
    public function getRemainingBalanceAttribute()
    {
        $total = $this->amount + ($this->late_fee ?? 0);
        return max(0, $total - ($this->paid_amount ?? 0));
    }

    /**
     * Check if installment is overdue.
     */
    public function getIsOverdueAttribute()
    {
        if ($this->status === 'paid') {
            return false;
        }

        $checkDate = $this->grace_period_ends ?? $this->due_date;
        return Carbon::parse($checkDate)->isPast();
    }

    /**
     * Check if grace period is active.
     */
    public function getIsGracePeriodActiveAttribute()
    {
        if (!$this->grace_period_ends || $this->status === 'paid') {
            return false;
        }

        return Carbon::now()->between($this->due_date, $this->grace_period_ends);
    }

    /**
     * Check if installment is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if installment is partially paid.
     */
    public function isPartial()
    {
        return $this->status === 'partial';
    }

    /**
     * Check if installment is fully paid.
     */
    public function isPaid()
    {
        return $this->status === 'paid';
    }

    /**
     * Check if installment is overdue.
     */
    public function isOverdue()
    {
        return $this->status === 'overdue';
    }

    /**
     * Record a payment for this installment.
     */
    public function recordPayment($amount, $paymentData = [])
    {
        // Update paid amount
        $newPaidAmount = ($this->paid_amount ?? 0) + $amount;
        $this->paid_amount = $newPaidAmount;

        // Determine new status
        $totalDue = $this->amount + ($this->late_fee ?? 0);
        
        if ($newPaidAmount >= $totalDue) {
            $this->status = 'paid';
            $this->paid_at = now();
        } else {
            $this->status = 'partial';
        }

        $this->save();

        // Update the original fee's paid amount and payment account
        $plan = $this->paymentPlan;
        if ($plan && $plan->fee) {
            $currentFeePaid = $plan->fee->paid_amount ?? 0;
            $newFeePaid = $currentFeePaid + $amount;
            
            // Calculate if fee is now fully paid
            $feeTotal = $plan->fee->total_amount;
            $feeStatus = ($newFeePaid >= $feeTotal) ? 1 : 2; // 1=Fully Paid, 2=Partially Paid
            
            // Build update array
            $feeUpdateData = [
                'paid_amount' => $newFeePaid,
                'status' => $feeStatus,
            ];
            
            // If payment account is provided and fee doesn't have one yet, assign it
            if (isset($paymentData['payment_account_id']) && $paymentData['payment_account_id'] && !$plan->fee->payment_account_id) {
                $feeUpdateData['payment_account_id'] = $paymentData['payment_account_id'];
            }
            
            $plan->fee->update($feeUpdateData);
        }

        // Create payment record
        $payment = $this->payments()->create(array_merge($paymentData, [
            'amount' => $amount,
            'payment_date' => $paymentData['payment_date'] ?? now(),
        ]));

        // Check if payment plan is now complete
        $this->checkPaymentPlanCompletion();

        return $payment;
    }

    /**
     * Apply late fee to this installment.
     */
    public function applyLateFee()
    {
        if ($this->status === 'paid' || $this->late_fee > 0) {
            return false;
        }

        $plan = $this->paymentPlan;
        if (!$plan || $plan->late_fee_percentage <= 0) {
            return false;
        }

        // Calculate late fee
        $lateFee = ($this->amount * $plan->late_fee_percentage) / 100;
        
        $this->late_fee = $lateFee;
        $this->save();

        return true;
    }

    /**
     * Update status based on due date and grace period.
     */
    public function updateStatus()
    {
        if ($this->status === 'paid') {
            return;
        }

        if ($this->is_overdue) {
            $this->status = 'overdue';
            $this->save();

            // Apply late fee if not already applied
            $this->applyLateFee();
        }
    }

    /**
     * Check if entire payment plan is completed.
     */
    protected function checkPaymentPlanCompletion()
    {
        $plan = $this->paymentPlan;
        
        if (!$plan) {
            return;
        }

        // Check if all installments are paid
        $allPaid = $plan->installments()
            ->where('status', '!=', 'paid')
            ->count() === 0;

        if ($allPaid && $plan->isActive()) {
            $plan->markAsCompleted();
            
            // Update fee status to fully paid and clear payment plan link
            if ($plan->fee) {
                $plan->fee->update([
                    'status' => 1, // 1 = Fully paid
                    'payment_plan_id' => null, // Clear payment plan link
                ]);
            }
        }
    }

    /**
     * Get status badge HTML.
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">Pending</span>',
            'partial' => '<span class="badge badge-info">Partial</span>',
            'paid' => '<span class="badge badge-success">Paid</span>',
            'overdue' => '<span class="badge badge-danger">Overdue</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }
}
