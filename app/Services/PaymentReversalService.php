<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Undoing a payment that should never have been recorded.
 *
 * Approving a receipt does five things at once — it settles the receipt, moves
 * the fee's paid amount and status, credits a payment account, posts a journal
 * entry, and (for an admission fee) submits the application. Undoing it by hand
 * in any one of those places leaves the other four disagreeing, which is how a
 * ledger stops balancing.
 *
 * So a reversal is the exact mirror of PaymentVerificationController::approve(),
 * performed in one transaction:
 *
 *   1. the receipt is marked reversed, with a reason and who did it;
 *   2. the fee is recomputed from whatever approved receipts remain;
 *   3. any payment-account credit is debited back;
 *   4. the ledger posting is reversed — by a new opposing entry, never by
 *      deleting the original, so the audit trail survives;
 *   5. an application submitted on the strength of that payment returns to
 *      draft, with the reason on its timeline.
 *
 * Nothing is deleted anywhere. A reversal is itself a record.
 */
class PaymentReversalService
{
    /** Receipts in this state have been undone and no longer count as money. */
    public const STATUS_REVERSED = 'reversed';

    public function __construct(
        protected TransactionAutoMapService $autoMap,
    ) {
    }

    /**
     * Why this receipt cannot be reversed — empty when it can.
     *
     * @return array<int, string>
     */
    public function blockers(PaymentReceipt $receipt): array
    {
        $blockers = [];

        if ($receipt->verification_status === self::STATUS_REVERSED) {
            $blockers[] = __('This payment has already been reversed.');
        } elseif ($receipt->verification_status !== 'approved') {
            $blockers[] = __('Only an approved payment can be reversed. This one is :status.', [
                'status' => $receipt->verification_status,
            ]);
        }

        if (!$receipt->fee) {
            $blockers[] = __('The fee this payment belongs to no longer exists.');
        }

        return $blockers;
    }

    /**
     * Reverse an approved payment, everywhere it reached.
     *
     * @param  string  $reason  Recorded on the receipt and the timeline.
     * @return array{reversed: bool, message: string, application: ?Application}
     */
    public function reverse(PaymentReceipt $receipt, string $reason): array
    {
        if ($blockers = $this->blockers($receipt)) {
            return ['reversed' => false, 'message' => implode(' ', $blockers), 'application' => null];
        }

        $application = null;

        DB::transaction(function () use ($receipt, $reason, &$application) {
            $fee = Fee::lockForUpdate()->find($receipt->fee_id);

            // 1. The receipt itself. Kept, not deleted — a reversal is a record.
            $receipt->verification_status = self::STATUS_REVERSED;
            $receipt->verification_note = trim(
                (string) $receipt->verification_note . "\n" .
                __('Reversed on :date by :user: :reason', [
                    'date' => now()->format('d M Y H:i'),
                    'user' => optional(Auth::user())->name ?? __('system'),
                    'reason' => $reason,
                ])
            );
            $receipt->save();

            // 2. The fee, recomputed from what is left rather than by
            //    subtracting — subtraction drifts once several receipts and a
            //    walk-in payment have touched the same fee.
            $this->recomputeFee($fee);

            // 3. The payment account, if the approval credited one.
            $this->reversePaymentAccount($receipt, $reason);

            // 4. The ledger. A new opposing entry, never a deletion.
            $this->autoMap->reverse('fee', $fee->id);

            // 5. The application, if this was its admission fee.
            $application = Application::where('admission_fee_id', $fee->id)->first();
            if ($application) {
                $this->returnApplicationToDraft($application, $reason);
            }
        });

        return [
            'reversed' => true,
            'message' => __('Payment reversed. The fee, the accounts and the ledger have all been put back.'),
            'application' => $application,
        ];
    }

    /**
     * Set a fee back to what its remaining approved receipts say it is.
     *
     * Recomputed from scratch on purpose. Subtracting the reversed amount looks
     * simpler but goes wrong the moment a fee has been paid in instalments or
     * had a walk-in payment recorded against it as well.
     */
    public function recomputeFee(Fee $fee): void
    {
        $paid = (float) PaymentReceipt::where('fee_id', $fee->id)
            ->where('verification_status', 'approved')
            ->sum('amount');

        $due = (float) $fee->fee_amount + (float) $fee->fine_amount - (float) $fee->discount_amount;

        $latest = PaymentReceipt::where('fee_id', $fee->id)
            ->where('verification_status', 'approved')
            ->orderByDesc('payment_date')
            ->first();

        $fee->paid_amount = $paid;
        $fee->status = $paid <= 0 ? 0 : ($paid >= $due ? 1 : 2);
        $fee->pay_date = $latest->payment_date ?? null;
        $fee->payment_method = $latest->payment_method ?? null;
        $fee->payment_account_id = $latest->payment_account_id ?? null;
        $fee->updated_by = Auth::id();
        $fee->save();
    }

    /** Debit back whatever the approval credited to a payment account. */
    protected function reversePaymentAccount(PaymentReceipt $receipt, string $reason): void
    {
        if (!$receipt->payment_account_id) {
            return;
        }

        $account = PaymentAccount::lockForUpdate()->find($receipt->payment_account_id);
        if (!$account) {
            return;
        }

        $balance = (float) $account->current_balance - (float) $receipt->amount;

        $entry = new PaymentAccountTransaction();
        $entry->payment_account_id = $account->id;
        $entry->transaction_type = 'debit';        // money going back out
        $entry->amount = $receipt->amount;
        $entry->transaction_date = now();
        $entry->title = __('Reversal of receipt #:id', ['id' => $receipt->id]);
        $entry->description = $reason;
        $entry->payment_method = $receipt->payment_method;
        $entry->reference_type = 'fees';
        $entry->reference_id = $receipt->fee_id;
        $entry->balance_after = $balance;
        $entry->created_by = Auth::id();
        $entry->save();

        $account->current_balance = $balance;
        $account->save();
    }

    /**
     * Put an application back to draft so it can be paid for again.
     *
     * Applied at whatever stage the application has reached, by decision: an
     * application that was only ever submitted because of a payment that did not
     * happen should not stay in the admissions queue. Where an officer had
     * already taken it under review or recorded a decision, the timeline entry
     * is what tells them why it left their queue.
     */
    protected function returnApplicationToDraft(Application $application, string $reason): void
    {
        $previousStage = $application->stage;

        if ($previousStage === 'draft') {
            return;
        }

        $meta = $application->portal_meta;
        $meta = is_array($meta) ? $meta : (array) json_decode((string) $meta, true);
        $meta['returned_to_draft_from'] = $previousStage;
        $meta['returned_to_draft_reason'] = $reason;
        $meta['returned_to_draft_at'] = now()->toDateTimeString();
        // The application is no longer submitted, so the date it was made no
        // longer applies. It is stamped again if and when it is resubmitted.
        $application->apply_date = null;

        $application->status = 0;
        $application->stage = 'draft';
        $application->progress = Application::stageProgressMap()['draft'];
        $application->portal_meta = $meta;
        $application->save();

        $application->recordStatus(
            'draft',
            __('The payment for this application was reversed (:reason), so it has been returned to draft. It was :stage. Pay the admission fee again and it will be submitted automatically.', [
                'reason' => $reason,
                'stage' => __('application_stage.' . $previousStage),
            ]),
            0,
            __('application_stage.draft'),
            Auth::id(),
            'admin'
        );
    }
}
