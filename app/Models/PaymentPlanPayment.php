<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PaymentPlanPayment extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'installment_id',
        'amount',
        'payment_method',
        'payment_date',
        'reference_no',
        'receipt_path',
        'paid_by_type',
        'paid_by_id',
        'note',
        'payment_account_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    /**
     * Get the installment this payment belongs to.
     */
    public function installment()
    {
        return $this->belongsTo(PaymentPlanInstallment::class, 'installment_id');
    }

    /**
     * Get the entity that made the payment (polymorphic).
     */
    public function paidBy()
    {
        return $this->morphTo();
    }

    /**
     * Get payment method label.
     */
    public function getPaymentMethodLabelAttribute()
    {
        $methods = [
            1 => __('payment_method_cash'),
            2 => __('payment_method_cheque'),
            3 => __('payment_method_bank'),
            4 => __('payment_method_online'),
            5 => __('payment_method_card'),
            6 => __('payment_method_mobile_money'),
            7 => __('payment_method_other'),
        ];

        return $methods[$this->payment_method] ?? 'Unknown';
    }

    /**
     * Get the receipt URL.
     */
    public function getReceiptUrlAttribute()
    {
        if (!$this->receipt_path) {
            return null;
        }

        return asset('uploads/' . $this->receipt_path);
    }

    /**
     * Check if receipt exists.
     */
    public function hasReceipt()
    {
        return !empty($this->receipt_path);
    }

    /**
     * Get payment plan through installment.
     */
    public function getPaymentPlanAttribute()
    {
        return $this->installment ? $this->installment->paymentPlan : null;
    }

    /**
     * Get student through payment plan.
     */
    public function getStudentAttribute()
    {
        return $this->payment_plan ? $this->payment_plan->student : null;
    }
}
