<?php

namespace App\Services;

use App\Models\Application;
use App\Models\CreditApplication;
use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentReceipt;
use App\Models\StudentCredit;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
 *   2. this payment comes off the fee — only this one;
 *   3. credit the payment raised, which nothing now backs, is cancelled;
 *   4. any payment-account credit is debited back;
 *   5. the student's statement gets an opposing entry;
 *   6. the fee's ledger posting is put back to the cash it actually received —
 *      by new entries, never by deleting the originals;
 *   7. an application submitted on the strength of that payment returns to
 *      draft, with the reason on its timeline.
 *
 * It refuses outright, changing nothing, when the credit that payment raised has
 * already settled another fee: taking the payment back would leave that fee paid
 * with money that no longer exists, and that is a decision for a person.
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

            return $blockers;
        }

        // Taking this payment off the fee leaves less overpayment behind it, so
        // some of the credit raised from this fee would no longer be backed by
        // anything. Unspent credit is simply cancelled (step 3 below); credit
        // already applied to another fee cannot be, because that fee would then
        // be settled with money that no longer exists. So this stops and lets a
        // person decide.
        foreach ($this->spentCreditFrom($receipt) as $spent) {
            $blockers[] = __('Reversing this payment would leave :amount of credit on this fee with nothing behind it, and that credit has already been applied to :fee. Undo that first, or reverse a different payment.', [
                'amount' => number_format($spent['amount'], 2),
                'fee' => $spent['fee'],
            ]);
        }

        return $blockers;
    }

    /**
     * Credit this fee would be left carrying unbacked, that has already been spent.
     *
     * Worked out by taking the payment off the fee on paper and asking the fee
     * credit audit what credit the fee would then be carrying without backing —
     * the same rule the Credit audit page uses, rather than a second one that
     * could disagree with it (FeeCreditReconciliation::duplicateCredits).
     *
     * @return array<int, array{amount: float, fee: string}>
     */
    protected function spentCreditFrom(PaymentReceipt $receipt): array
    {
        $fee = $receipt->fee;

        if (!$fee) {
            return [];
        }

        $wouldBePaid = max(0, round((float) $fee->paid_amount - (float) $receipt->amount, 2));
        $finding = $this->creditAudit($fee, $wouldBePaid);

        if (!$finding || $finding['already_spent'] <= 0.009) {
            return [];
        }

        // Name where it went, so the message is something a person can act on.
        $spentOn = CreditApplication::whereIn('student_credit_id',
                StudentCredit::where('source_fee_id', $fee->id)
                    ->where('source_type', StudentCredit::SOURCE_OVERPAYMENT)->pluck('id'))
            ->with('fee.category')
            ->get()
            ->map(fn ($application) => optional(optional($application->fee)->category)->title
                ? __('fee #:id :category', ['id' => $application->fee_id, 'category' => $application->fee->category->title])
                : __('fee #:id', ['id' => $application->fee_id]))
            ->unique()
            ->implode(', ');

        return [[
            'amount' => $finding['already_spent'],
            'fee' => $spentOn !== '' ? $spentOn : __('another fee'),
        ]];
    }

    /**
     * What the credit audit makes of this fee at a given paid amount.
     *
     * The audit answers the hypothetical directly, so nothing has to be written
     * and rolled back to find out (FeeCreditReconciliation::creditPosition).
     */
    protected function creditAudit(Fee $fee, float $paidAmount): ?array
    {
        return app(FeeCreditReconciliation::class)->creditPosition($fee, null, $paidAmount);
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

        $outcome = [];
        $ledgerIssue = null;

        DB::transaction(function () use ($receipt, $reason, &$application, &$outcome, &$ledgerIssue) {
            $fee = Fee::lockForUpdate()->find($receipt->fee_id);

            // 1. The receipt itself. Kept, not deleted — a reversal is a record.
            $receipt->verification_status = self::STATUS_REVERSED;
            $receipt->verification_note = trim(
                (string) $receipt->verification_note . "\n" .
                __('Reversed on :date by :user: :reason', [
                    'date' => now()->format('d M Y H:i'),
                    'user' => optional(Auth::guard('web')->user())->name ?? __('system'),
                    'reason' => $reason,
                ])
            );
            $receipt->save();

            // 2. The fee: this payment taken off it, and nothing else.
            $this->undoReceiptOnFee($fee, $receipt);

            // 3. The credit this payment raised, which nothing now backs.
            $voided = $this->cancelCreditRaisedBy($fee, $reason);

            // 4. The payment account, if the approval credited one.
            $this->reversePaymentAccount($receipt, $reason);

            // 5. The student's own statement: an opposing entry, not a deletion.
            $this->recordStatementReversal($fee, $receipt);

            // 6. The ledger, back to what the fee actually received. Reversing
            //    the posting outright would leave a fee that still holds money
            //    unposted; resync reverses and reposts at its cash received, the
            //    same rule the fee observer and the Credit audit correction use.
            //
            //    It can refuse — a fee whose pay date falls in no accounting
            //    period cannot be posted at all. That must not stop the money
            //    being put right: the posting is left as it stands and the
            //    message says so. Fees → Credit audit lists any fee whose
            //    posting differs from its cash and reposts them in one click.
            try {
                app(FeeLedgerPosting::class)->resync($fee->fresh(), Auth::guard('web')->id());
            } catch (\Throwable $e) {
                $ledgerIssue = $e->getMessage();
                Log::warning('Payment reversed but the ledger could not be updated', [
                    'receipt_id' => $receipt->id,
                    'fee_id' => $fee->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 7. The application, if this was its admission fee.
            $application = Application::where('admission_fee_id', $fee->id)->first();
            if ($application) {
                $this->returnApplicationToDraft($application, $reason);
            }

            $outcome = [
                'removed' => (float) $receipt->amount,
                'fee_now' => (float) $fee->fresh()->paid_amount,
                'credit_cancelled' => $voided['amount'] ?? 0.0,
            ];
        });

        return [
            'reversed' => true,
            'message' => $this->describe($outcome ?? [], $ledgerIssue),
            'application' => $application,
        ];
    }

    /**
     * Take this one payment off the fee, and nothing else.
     *
     * The exact inverse of approving it, which ADDS the receipt to the fee's
     * paid amount (PaymentVerificationController::approveFeeReceipt). Rebuilding
     * the fee from its approved receipts instead — which is what this used to do
     * — silently discards every payment that has no receipt row behind it, and
     * plenty do: the Received modal only began writing receipts later, so a fee
     * paid at the counter in December can lose that payment when an unrelated
     * one from April is reversed.
     */
    public function undoReceiptOnFee(Fee $fee, PaymentReceipt $receipt): void
    {
        $paid = max(0, round((float) $fee->paid_amount - (float) $receipt->amount, 2));
        $due = (float) $fee->fee_amount + (float) $fee->fine_amount - (float) $fee->discount_amount;

        // The newest payment still standing tells the fee when it was last paid.
        $latest = PaymentReceipt::where('fee_id', $fee->id)
            ->where('verification_status', 'approved')
            ->where('id', '!=', $receipt->id)
            ->orderByDesc('payment_date')
            ->first();

        $fee->paid_amount = $paid;

        // A cancelled fee stays cancelled; reversing a payment is not a decision
        // to bring it back.
        if ((int) $fee->status !== 3) {
            $fee->status = $paid <= 0.009 ? 0 : ($paid >= $due - 0.009 ? 1 : 2);
        }

        if ($latest) {
            $fee->pay_date = $latest->payment_date;
            $fee->payment_method = $latest->payment_method;
            $fee->payment_account_id = $latest->payment_account_id;
        } elseif ($paid <= 0.009) {
            $fee->pay_date = null;
            $fee->payment_method = null;
            $fee->payment_account_id = null;
        }
        // Money left but no receipt behind it: the fee keeps the date it has,
        // because a payment is still there and the ledger needs a date for it.

        $fee->updated_by = Auth::guard('web')->id();
        $fee->save();
    }

    /**
     * Cancel the credit this payment raised, now that the payment has gone.
     *
     * Uses the Credit audit's own rule, so the two can never disagree about
     * what counts as credit with nothing behind it. Credit already spent
     * elsewhere is never touched here — blockers() refuses the reversal before
     * it reaches this point.
     *
     * @return array{credits: int, amount: float, needs_review: float}
     */
    protected function cancelCreditRaisedBy(Fee $fee, string $reason): array
    {
        return app(FeeCreditReconciliation::class)->voidDuplicateCredits(
            Auth::guard('web')->id(),
            __('payment reversal: :reason', ['reason' => $reason]),
            $fee->id
        );
    }

    /** The student's statement: an opposing entry, never a deletion. */
    protected function recordStatementReversal(Fee $fee, PaymentReceipt $receipt): void
    {
        $student = optional($fee->studentEnroll)->student;

        if (!$student) {
            return;
        }

        $entry = new Transaction();
        $entry->transaction_id = Str::random(16);
        $entry->amount = $receipt->amount;
        $entry->type = '2';     // money going back out
        $entry->created_by = Auth::guard('web')->id();
        $student->transactions()->save($entry);
    }

    /** Say what actually happened, in the numbers the person will check. */
    protected function describe(array $outcome, ?string $ledgerIssue = null): string
    {
        $message = __('Payment reversed. :amount taken off the fee, which now stands at :now.', [
            'amount' => number_format($outcome['removed'] ?? 0, 2),
            'now' => number_format($outcome['fee_now'] ?? 0, 2),
        ]);

        if (($outcome['credit_cancelled'] ?? 0) > 0.009) {
            $message .= ' ' . __('The :amount of credit it had created has been cancelled.', [
                'amount' => number_format($outcome['credit_cancelled'], 2),
            ]);
        }

        if ($ledgerIssue) {
            return $message . ' ' . __('The accounts have been put back, but its ledger entry could not be: :why Post it from Fees → Credit audit once that is sorted.', [
                'why' => $ledgerIssue,
            ]);
        }

        return $message . ' ' . __('The accounts and the ledger have been put back with it.');
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
        $entry->created_by = Auth::guard('web')->id();
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
            Auth::guard('web')->id(),
            'admin'
        );
    }
}
