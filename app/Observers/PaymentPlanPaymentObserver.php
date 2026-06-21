<?php

namespace App\Observers;

use App\Models\PaymentPlanPayment;
use App\Services\TransactionAutoMapService;

class PaymentPlanPaymentObserver
{
    protected $autoMapService;

    public function __construct(TransactionAutoMapService $autoMapService)
    {
        $this->autoMapService = $autoMapService;
    }

    /**
     * Handle the PaymentPlanPayment "created" event.
     */
    public function created(PaymentPlanPayment $payment): void
    {
        // Auto-map each payment plan installment payment as a separate transaction
        $installment = $payment->installment;
        $plan = $installment?->paymentPlan;
        $fee = $plan?->fee;

        if (!$installment || !$plan || !$fee) {
            return;
        }

        // Use the fee's category for mapping
        $categoryId = $fee->category_id;

        // Create a unique description for this installment payment
        $description = sprintf(
            'Payment Plan - Installment #%d - %s',
            $installment->installment_number,
            $fee->category->name ?? 'Student Fee'
        );

        // Auto-map this payment using the PaymentPlanPayment ID, not the Fee ID
        $this->autoMapService->autoMap(
            'payment_plan_payment',
            $payment->id,
            $categoryId,
            [
                'amount' => $payment->amount,
                'date' => $payment->payment_date,
                'description' => $description
            ]
        );
    }

    /**
     * Handle the PaymentPlanPayment "updated" event.
     */
    public function updated(PaymentPlanPayment $payment): void
    {
        //
    }

    /**
     * Handle the PaymentPlanPayment "deleted" event.
     */
    public function deleted(PaymentPlanPayment $payment): void
    {
        //
    }

    /**
     * Handle the PaymentPlanPayment "restored" event.
     */
    public function restored(PaymentPlanPayment $paymentPlanPayment): void
    {
        //
    }

    /**
     * Handle the PaymentPlanPayment "force deleted" event.
     */
    public function forceDeleted(PaymentPlanPayment $paymentPlanPayment): void
    {
        //
    }
}
