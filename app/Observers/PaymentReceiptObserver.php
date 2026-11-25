<?php

namespace App\Observers;

use App\Models\Fee;
use App\Models\PaymentReceipt;
use App\Services\FeeLedgerPosting;
use Illuminate\Support\Facades\Log;

/**
 * A receipt dates the cash it records: approving, reversing or deleting one
 * re-dates its fee's postings (FeeLedgerPosting splits earlier receipts into
 * entries on their own days). Never fails the receipt: a posting problem is
 * logged, and Ledger health shows the fee.
 */
class PaymentReceiptObserver
{
    public function saved(PaymentReceipt $receipt): void
    {
        if ($receipt->wasChanged(['verification_status', 'amount', 'payment_date', 'payment_method', 'payment_account_id', 'fee_id'])
            || $receipt->wasRecentlyCreated) {
            $this->resync($receipt->fee_id);

            if ($receipt->wasChanged('fee_id') && $receipt->getOriginal('fee_id')) {
                $this->resync((int) $receipt->getOriginal('fee_id'));
            }
        }
    }

    public function deleted(PaymentReceipt $receipt): void
    {
        $this->resync($receipt->fee_id);
    }

    protected function resync(?int $feeId): void
    {
        $fee = $feeId ? Fee::with('category')->find($feeId) : null;

        if (!$fee || empty($fee->pay_date)) {
            return;
        }

        try {
            app(FeeLedgerPosting::class)->resync($fee);
        } catch (\Throwable $e) {
            Log::warning("Fee #{$feeId}: could not re-date its postings after a receipt changed: " . $e->getMessage());
        }
    }
}
