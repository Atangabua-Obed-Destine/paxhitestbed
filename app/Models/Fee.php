<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Fee extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_enroll_id', 'category_id', 'fee_amount', 'fine_amount', 'discount_amount', 'paid_amount', 'assign_date', 'due_date', 'pay_date', 'payment_method', 'payment_account_id', 'note', 'status', 'payment_plan_id', 'created_by', 'updated_by',
    ];
  
    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }
  
    public function category()
    {
        return $this->belongsTo(FeesCategory::class, 'category_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(\App\Models\PaymentAccount::class, 'payment_account_id');
    }

    public function resitRequest()
    {
        return $this->hasOne(ResitRequest::class, 'fee_id');
    }

    public function paymentReceipts()
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    public function pendingReceipt()
    {
        return $this->hasOne(PaymentReceipt::class)->where('verification_status', 'pending');
    }

    public function approvedReceipts()
    {
        return $this->hasMany(PaymentReceipt::class)->where('verification_status', 'approved');
    }

    /**
     * Get pending multi-payment distributions for this fee.
     */
    public function pendingMultiPaymentDistributions()
    {
        return $this->hasMany(\App\Models\MultiPaymentDistribution::class, 'fee_id')
            ->whereHas('multiPayment', function($query) {
                $query->where('status', 'pending');
            })
            ->where('amount_applied', '>', 0);
    }

    /**
     * Check if fee has pending multi-payment.
     */
    public function hasPendingMultiPayment()
    {
        return $this->pendingMultiPaymentDistributions()->exists();
    }

    /**
     * Get pending multi-payment total amount for this fee.
     */
    public function getPendingMultiPaymentAmount()
    {
        return $this->pendingMultiPaymentDistributions()->sum('amount_applied');
    }

    /**
     * Get the payment plan associated with this fee.
     */
    public function paymentPlan()
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    /**
     * Check if fee has an active payment plan.
     */
    public function hasActivePaymentPlan()
    {
        return $this->payment_plan_id && $this->paymentPlan && $this->paymentPlan->status === 'active';
    }

    /**
     * Get credits applied to this fee.
     */
    public function creditApplications()
    {
        return $this->hasMany(CreditApplication::class, 'fee_id');
    }

    /**
     * Get credits generated from this fee (overpayments).
     */
    public function generatedCredits()
    {
        return $this->hasMany(StudentCredit::class, 'source_fee_id');
    }

    /**
     * Calculate the total amount due (fee + fine - discount).
     *
     * @return float
     */
    public function getTotalAmountAttribute()
    {
        $fee = $this->attributes['fee_amount'] ?? 0;
        $fine = $this->attributes['fine_amount'] ?? 0;
        $discount = $this->attributes['discount_amount'] ?? 0;
        return $fee + $fine - $discount;
    }

    /**
     * Calculate the remaining balance.
     * Can be negative if overpaid (indicates credit generated).
     *
     * @return float
     */
    public function getRemainingBalanceAttribute()
    {
        $total = $this->total_amount ?? 0;
        $paid = $this->paid_amount ?? 0;
        return $total - $paid; // Allow negative for overpayments
    }

    /**
     * Get the display remaining balance (always >= 0 for UI).
     *
     * @return float
     */
    public function getDisplayRemainingBalanceAttribute()
    {
        return max(0, $this->remaining_balance);
    }

    /**
     * Calculate overpayment amount (if any).
     *
     * @return float
     */
    public function getOverpaymentAmountAttribute()
    {
        $balance = $this->remaining_balance;
        return $balance < 0 ? abs($balance) : 0;
    }

    /**
     * Check if fee was overpaid.
     *
     * @return bool
     */
    public function isOverpaid()
    {
        return $this->remaining_balance < 0;
    }

    /**
     * Get total credits applied to this fee.
     *
     * @return float
     */
    public function getTotalCreditsAppliedAttribute()
    {
        return $this->creditApplications()->sum('amount_applied');
    }

    /**
     * Check if the fee is fully paid.
     *
     * @return bool
     */
    public function isFullyPaid()
    {
        $total = $this->total_amount ?? 0;
        $paid = $this->paid_amount ?? 0;
        return $total > 0 && $paid >= $total;
    }

    /**
     * Check if the fee is partially paid.
     *
     * @return bool
     */
    public function isPartiallyPaid()
    {
        $total = $this->total_amount ?? 0;
        $paid = $this->paid_amount ?? 0;
        return $paid > 0 && $paid < $total;
    }

    /**
     * Check if the fee is unpaid.
     *
     * @return bool
     */
    public function isUnpaid()
    {
        $paid = $this->paid_amount ?? 0;
        return $paid == 0;
    }

    /**
     * Get the payment status badge HTML.
     *
     * @return string
     */
    public function getStatusBadgeAttribute()
    {
        // Check for payment plan first
        if ($this->hasActivePaymentPlan()) {
            return '<span class="badge badge-info">Payment Plan Active</span>';
        }
        
        // Check for overpayment
        if ($this->isOverpaid()) {
            return '<span class="badge badge-primary"><i class="fas fa-plus-circle"></i> Overpaid</span>';
        }
        
        // Check actual payment amounts first (more reliable than status field)
        if ($this->isFullyPaid()) {
            return '<span class="badge badge-success">Fully Paid</span>';
        } elseif ($this->isPartiallyPaid()) {
            return '<span class="badge badge-warning">Partially Paid</span>';
        } elseif ($this->status == 3) {
            return '<span class="badge badge-danger">Cancelled</span>';
        } else {
            return '<span class="badge badge-secondary">Unpaid</span>';
        }
    }

    /**
     * Get custom audit description.
     *
     * @param string $event
     * @return string
     */
    public function getAuditDescription($event)
    {
        // Get student name from first_name and last_name
        $studentName = 'Unknown Student';
        
        // Try to get student info - reload from database if needed
        try {
            if ($this->student_enroll_id) {
                // Force reload the relationship from database
                $studentEnroll = \App\Models\StudentEnroll::with('student')->find($this->student_enroll_id);
                
                if ($studentEnroll && $studentEnroll->student) {
                    $firstName = $studentEnroll->student->first_name ?? '';
                    $lastName = $studentEnroll->student->last_name ?? '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    
                    if (!empty($fullName)) {
                        $studentName = $fullName;
                    }
                }
            }
        } catch (\Exception $e) {
            // If there's any error, stick with 'Unknown Student'
        }

        // Get category name
        $categoryName = 'Unknown Category';
        try {
            if ($this->category_id) {
                $category = \App\Models\FeesCategory::find($this->category_id);
                if ($category) {
                    $categoryName = $category->title;
                }
            }
        } catch (\Exception $e) {
            // If there's any error, stick with 'Unknown Category'
        }

        $amount = $this->paid_amount ?? $this->fee_amount;

        if ($event === 'created') {
            return "Fee assigned to {$studentName} for {$categoryName} (Amount: {$amount})";
        } elseif ($event === 'updated') {
            if ($this->status == 1) {
                return "Fee payment received from {$studentName} for {$categoryName} (Paid: {$this->paid_amount})";
            }
            return "Fee updated for {$studentName} for {$categoryName}";
        } elseif ($event === 'deleted') {
            return "Fee deleted for {$studentName} for {$categoryName}";
        }

        return "Fee was {$event}";
    }
}

