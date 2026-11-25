<?php

namespace App\Observers;

use App\Models\CreditApplication;
use App\Models\StudentCredit;
use App\Services\Accounting\StudentCreditLedger;

/**
 * Keeps account 419 in step with student credit, whichever screen or service
 * changed it: raising a credit, applying it, transferring between fees,
 * voiding a duplicate, refunding. Watching the models rather than each caller
 * means a new route to a credit cannot forget the ledger.
 *
 * Registered for both StudentCredit and CreditApplication.
 */
class StudentCreditObserver
{
    public function __construct(protected StudentCreditLedger $ledger)
    {
    }

    public function saved($model): void
    {
        $this->sync($model);
    }

    public function deleted($model): void
    {
        $this->sync($model);
    }

    protected function sync($model): void
    {
        $studentId = $model instanceof CreditApplication
            ? StudentCredit::whereKey($model->student_credit_id)->value('student_id')
            : $model->student_id;

        $this->ledger->syncQuietly($studentId ? (int) $studentId : null);
    }
}
