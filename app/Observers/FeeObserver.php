<?php

namespace App\Observers;

use App\Models\Fee;
use App\Services\Resit\ResitFeeService;
use App\Services\TransactionAutoMapService;

class FeeObserver
{
    protected $autoMapService;
    protected $resitFeeService;

    public function __construct(TransactionAutoMapService $autoMapService, ResitFeeService $resitFeeService)
    {
        $this->autoMapService = $autoMapService;
        $this->resitFeeService = $resitFeeService;
    }

    /**
     * Handle the Fee "created" event.
     */
    public function created(Fee $fee): void
    {
        // Skip fees under active payment plan - those are handled by PaymentPlanPayment observer
        // Skip payment-plan linked fees for auto mapping only; other sync happens below.
        if (!$fee->payment_plan_id && $fee->paid_amount > 0 && $fee->pay_date) {
            $this->autoMapService->autoMap(
                'fee',
                $fee->id,
                $fee->category_id,
                [
                    'amount' => $fee->paid_amount,
                    'date' => $fee->pay_date,
                    'description' => 'Fee Payment - ' . ($fee->category->name ?? 'Student Fee')
                ]
            );
        }

        $this->resitFeeService->syncFromFee($fee);
    }

    /**
     * Handle the Fee "updated" event.
     */
    public function updated(Fee $fee): void
    {
        // Skip fees under active payment plan - those are handled by PaymentPlanPayment observer
        if (!$fee->payment_plan_id) {
            $paidNow = ($fee->paid_amount > 0 && $fee->pay_date);
            $payload = [
                'amount' => $fee->paid_amount,
                'date' => $fee->pay_date,
                'description' => 'Fee Payment - ' . ($fee->category->name ?? 'Student Fee'),
            ];

            if (!$paidNow) {
                // Fee was un-paid (amount cleared / pay_date removed) → reverse any posting
                $this->autoMapService->reverse('fee', $fee->id);
            } elseif ($fee->wasChanged(['paid_amount', 'category_id', 'pay_date'])) {
                // Newly paid or amount/category changed → (re)post the correct entry
                $this->autoMapService->remap('fee', $fee->id, $fee->category_id, $payload);
            }
        }

        $this->resitFeeService->syncFromFee($fee);
    }

    /**
     * Handle the Fee "deleted" event — reverse any ledger posting.
     */
    public function deleted(Fee $fee): void
    {
        $this->autoMapService->reverse('fee', $fee->id);
    }

    /**
     * Handle the Fee "restored" event.
     */
    public function restored(Fee $fee): void
    {
        //
    }

    /**
     * Handle the Fee "force deleted" event.
     */
    public function forceDeleted(Fee $fee): void
    {
        //
    }
}

