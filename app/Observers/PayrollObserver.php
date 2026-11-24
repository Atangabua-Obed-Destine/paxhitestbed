<?php

namespace App\Observers;

use App\Models\Payroll;

class PayrollObserver
{
    /**
     * Handle the Payroll "created" event.
     *
     * Deliberately does nothing. A payroll is created unpaid; it is the pay
     * action (PayrollAccountingService) that posts it to the ledger, once the
     * money has moved. This used to auto-map the payroll here as well, which
     * failed on every generate (an unpaid payroll has no pay date) and logged
     * an error, and would have posted the salary twice had a pay date ever
     * been present at creation.
     */
    public function created(Payroll $payroll): void
    {
        //
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

