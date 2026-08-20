<?php

namespace App\Observers;

use App\Models\Application;
use App\Models\Fee;
use App\Services\ApplicationSubmissionService;
use App\Services\Resit\ResitFeeService;
use App\Services\TransactionAutoMapService;

class FeeObserver
{
    protected $autoMapService;
    protected $resitFeeService;
    protected $submissionService;

    public function __construct(
        TransactionAutoMapService $autoMapService,
        ResitFeeService $resitFeeService,
        ApplicationSubmissionService $submissionService
    ) {
        $this->autoMapService = $autoMapService;
        $this->resitFeeService = $resitFeeService;
        $this->submissionService = $submissionService;
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

        $this->submitApplicationIfFeeSettled($fee);
    }

    /**
     * An admission fee that has just been settled submits its application.
     *
     * Every route to a paid fee ends here — mobile money, an admin verifying an
     * uploaded receipt, and a walk-in payment taken at the counter all save the
     * Fee — so hooking the model is what makes the behaviour whole rather than
     * true of whichever paths someone remembered to change.
     *
     * The applicant cannot pay until the form is complete, so by this point
     * there is genuinely nothing left for them to do; leaving the application
     * sitting in draft would only wait on a button press that adds nothing.
     */
    protected function submitApplicationIfFeeSettled(Fee $fee): void
    {
        if (!$fee->wasChanged('status') || (int) $fee->status !== 1) {
            return;
        }

        $application = Application::where('admission_fee_id', $fee->id)->first();
        if (!$application) {
            // Ordinary student fee, not an application fee.
            return;
        }

        $this->submissionService->autoSubmit($application, __('admission fee was approved'));
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

