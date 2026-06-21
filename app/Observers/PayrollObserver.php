<?php

namespace App\Observers;

use App\Models\Payroll;
use App\Services\TransactionAutoMapService;

class PayrollObserver
{
    protected $autoMapService;

    public function __construct(TransactionAutoMapService $autoMapService)
    {
        $this->autoMapService = $autoMapService;
    }

    /**
     * Handle the Payroll "created" event.
     */
    public function created(Payroll $payroll): void
    {
        $this->autoMapService->autoMap(
            'payroll',
            $payroll->id,
            null, // Payroll doesn't have a category
            [
                'amount' => $payroll->net_salary,
                'date' => $payroll->pay_date,
                'description' => 'Salary Payment - ' . ($payroll->staff->name ?? 'Staff')
            ]
        );
    }

    /**
     * Handle the Payroll "updated" event.
     */
    public function updated(Payroll $payroll): void
    {
        //
    }

    /**
     * Handle the Payroll "deleted" event.
     */
    public function deleted(Payroll $payroll): void
    {
        //
    }

    /**
     * Handle the Payroll "restored" event.
     */
    public function restored(Payroll $payroll): void
    {
        //
    }

    /**
     * Handle the Payroll "force deleted" event.
     */
    public function forceDeleted(Payroll $payroll): void
    {
        //
    }
}

