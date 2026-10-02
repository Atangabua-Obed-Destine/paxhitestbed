<?php

namespace App\Console\Commands;

use App\Models\PaymentPlanPayment;
use App\Models\TransactionMapping;
use App\Services\TransactionAutoMapService;
use Illuminate\Console\Command;

/**
 * Post payment plan instalment payments the ledger never received.
 *
 * transaction_mappings.transaction_type was an ENUM that did not list
 * 'payment_plan_payment', so every instalment payment either failed to post
 * outright (on a strict-mode server) or posted under a blank type that nothing
 * could find. The column has been widened; this walks the payments that were
 * taken in the meantime and posts them properly.
 *
 * Safe to run more than once: a payment that already has an active mapping is
 * left alone.
 */
class PostPaymentPlanLedgerCommand extends Command
{
    protected $signature = 'payment-plan:post-ledger
                            {--dry-run : List what would be posted and change nothing}';

    protected $description = 'Post payment plan instalment payments that never reached the ledger';

    public function handle(TransactionAutoMapService $autoMap): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $posted = TransactionMapping::where('transaction_type', 'payment_plan_payment')
            ->where('status', 'active')
            ->pluck('transaction_id')
            ->flip();

        $payments = PaymentPlanPayment::with('installment.paymentPlan.fee.category')
            ->where('status', '!=', 'reversed')
            ->orderBy('payment_date')
            ->get()
            ->reject(fn ($payment) => isset($posted[$payment->id]));

        if ($payments->isEmpty()) {
            $this->info('Every instalment payment is already posted. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info($payments->count() . ' instalment payment(s) are not posted'
            . ($dryRun ? ' — dry run, nothing will be written:' : ':'));

        $done = 0;
        $skipped = 0;
        $rows = [];

        foreach ($payments as $payment) {
            $installment = $payment->installment;
            $fee = $installment?->paymentPlan?->fee;

            if (!$installment || !$fee) {
                $rows[] = [$payment->id, $payment->amount, $payment->payment_date, 'its instalment or fee is gone'];
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $rows[] = [$payment->id, $payment->amount, $payment->payment_date, 'would post'];
                continue;
            }

            $result = $autoMap->autoMap('payment_plan_payment', $payment->id, $fee->category_id, [
                'amount' => $payment->amount,
                'date' => $payment->payment_date,
                'description' => sprintf(
                    'Payment Plan - Installment #%d - %s',
                    $installment->installment_number,
                    $fee->category->name ?? $fee->category->title ?? 'Student Fee'
                ),
            ]);

            if ($result) {
                $done++;
                $rows[] = [$payment->id, $payment->amount, $payment->payment_date, 'posted'];
            } else {
                $skipped++;
                $rows[] = [$payment->id, $payment->amount, $payment->payment_date, 'could not post — see the log'];
            }
        }

        $this->table(['payment', 'amount', 'date', 'outcome'], $rows);

        if ($dryRun) {
            $this->comment('Dry run. Run again without --dry-run to post these.');

            return self::SUCCESS;
        }

        $this->info("Posted {$done}." . ($skipped ? " Left alone: {$skipped}." : ''));

        return self::SUCCESS;
    }
}
