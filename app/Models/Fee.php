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
        'student_enroll_id', 'applicant_id', 'category_id', 'fee_amount', 'fine_amount', 'discount_amount', 'paid_amount', 'assign_date', 'due_date', 'pay_date', 'payment_method', 'payment_account_id', 'note', 'status', 'payment_plan_id', 'created_by', 'updated_by',
    ];
  
    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    // Set when the fee originates from an applicant (admission fee) rather than
    // an enrolled student. Kept even after the applicant becomes a student
    // (provenance), so we can always tell where the fee came from.
    public function applicant()
    {
        return $this->belongsTo(Application::class, 'applicant_id');
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

    /*
    |--------------------------------------------------------------------------
    | Paid, net of credit moved to other fees
    |--------------------------------------------------------------------------
    |
    | When a fee is overpaid, the excess is raised as a student credit and later
    | applied to another fee — typically a First Instalment overpaid before the
    | Second Instalment was configured. Applying the credit adds it to the target
    | fee's paid_amount, but nothing ever took it off the source fee's. So the
    | same money sat in two fees' paid_amount, and every total built by summing
    | paid_amount counted it twice: Total Collected, the dashboard, the budget
    | sheet's fee actuals, the General Ledger's fee summary.
    |
    | "Net paid" is paid_amount less the overpayment credit that has since been
    | applied elsewhere. The money counts on the fee it went to, and only there —
    | which is exactly what a manual transfer between fees already does. Summed
    | across fees, net paid is the cash actually received.
    |
    | The stored paid_amount is left as it is: it is what the receipts and the
    | ledger were written from.
    */

    /** @var float|null */
    protected $creditMovedOutCache = null;

    /**
     * SQL for the overpayment credit raised on a fee and applied to other fees.
     *
     * @param string $feeIdColumn the column holding the fee's id, e.g. "fees.id" or "f.id"
     */
    public static function creditMovedOutSql(string $feeIdColumn = 'fees.id'): string
    {
        return '(SELECT COALESCE(SUM(ca.amount_applied), 0)'
            . ' FROM credit_applications ca'
            . ' JOIN student_credits sc ON sc.id = ca.student_credit_id'
            . ' WHERE sc.source_fee_id = ' . $feeIdColumn
            . " AND sc.source_type = '" . StudentCredit::SOURCE_OVERPAYMENT . "')";
    }

    /**
     * SQL for a fee's net paid amount.
     *
     * @param string $alias the fees table alias in the surrounding query
     */
    public static function netPaidSql(string $alias = 'fees'): string
    {
        return '(' . $alias . '.paid_amount - ' . static::creditMovedOutSql($alias . '.id') . ')';
    }

    /**
     * SQL for the cash actually received on a fee — for anything dated.
     *
     * Net paid (above) puts moved credit on the fee it went to, which is right
     * for whether a fee is settled. But the cash arrived with the fee it was
     * paid on, in that fee's month. A budget sheet, daybook or monthly chart
     * built from net paid would move an overpayment out of the month it was
     * received and show the later credit as cash. So dated views use this:
     * paid_amount, less any credit applied to the fee (it brought no cash),
     * plus anything transferred out of it (that cash did arrive here).
     *
     * Summed across all fees it equals net paid exactly; only the dating differs.
     *
     * @param string $alias the fees table alias in the surrounding query
     */
    public static function cashReceivedSql(string $alias = 'fees'): string
    {
        return '(' . $alias . '.paid_amount'
            . ' - (SELECT COALESCE(SUM(ca2.amount_applied), 0) FROM credit_applications ca2'
            . ' WHERE ca2.fee_id = ' . $alias . '.id)'
            . ' + (SELECT COALESCE(SUM(sc2.original_amount), 0) FROM student_credits sc2'
            . ' WHERE sc2.source_fee_id = ' . $alias . '.id'
            . " AND sc2.source_type = '" . StudentCredit::SOURCE_TRANSFER . "'))";
    }

    /**
     * The cash this fee actually received — the per-fee form of
     * cashReceivedSql(), and the amount its ledger posting must carry.
     *
     * Read fresh every time: it is used straight after credit is applied or a
     * transfer is made, and a cached value would post the old figure.
     */
    public function getCashReceivedAmountAttribute(): float
    {
        $creditIn = (float) CreditApplication::where('fee_id', $this->id)->sum('amount_applied');

        $transferredOut = (float) StudentCredit::where('source_fee_id', $this->id)
            ->where('source_type', StudentCredit::SOURCE_TRANSFER)
            ->sum('original_amount');

        return round((float) ($this->paid_amount ?? 0) - $creditIn + $transferredOut, 2);
    }

    /** Load credit_moved_out with the fees, so a list does not query once per row. */
    public function scopeWithCreditMovedOut($query)
    {
        if (is_null($query->getQuery()->columns)) {
            $query->select('fees.*');
        }

        return $query->selectRaw(static::creditMovedOutSql('fees.id') . ' as credit_moved_out');
    }

    public function getCreditMovedOutAttribute(): float
    {
        if (array_key_exists('credit_moved_out', $this->attributes)) {
            return (float) $this->attributes['credit_moved_out'];
        }

        if ($this->creditMovedOutCache === null) {
            $this->creditMovedOutCache = (float) CreditApplication::query()
                ->join('student_credits', 'student_credits.id', '=', 'credit_applications.student_credit_id')
                ->where('student_credits.source_fee_id', $this->id)
                ->where('student_credits.source_type', StudentCredit::SOURCE_OVERPAYMENT)
                ->sum('credit_applications.amount_applied');
        }

        return $this->creditMovedOutCache;
    }

    /** What was paid on this fee and still belongs to it. */
    public function getNetPaidAmountAttribute(): float
    {
        return round((float) ($this->paid_amount ?? 0) - $this->credit_moved_out, 2);
    }

    public function getNetRemainingBalanceAttribute(): float
    {
        return round((float) ($this->total_amount ?? 0) - $this->net_paid_amount, 2);
    }

    /** Overpayment still held on this fee — not yet applied anywhere else. */
    public function getNetOverpaymentAmountAttribute(): float
    {
        return max(0, -$this->net_remaining_balance);
    }

    public function isNetOverpaid(): bool
    {
        return $this->net_remaining_balance < -0.005;
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

