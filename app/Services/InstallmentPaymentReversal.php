<?php

namespace App\Services;

use App\Models\CreditApplication;
use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanPayment;
use App\Models\StudentCredit;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Undo one payment plan instalment payment, everywhere it reached.
 *
 * Until now nothing could: a payment recorded against the wrong instalment, or
 * twice, stayed on the instalment and on the fee for good, and the only way out
 * was to edit the database by hand.
 *
 * Recording a payment is additive — PaymentPlanInstallment::recordPayment adds
 * to the instalment, adds to the fee, posts a journal entry, credits a payment
 * account and writes a line on the student's statement — so undoing it is
 * subtraction, in the same seven places:
 *
 *   1. the payment, marked reversed and kept, with who did it and why;
 *   2. the instalment: this payment off it, and its status re-derived;
 *   3. the fee: the same amount off it, and its status re-derived;
 *   4. the payment account, debited back, if one was credited;
 *   5. the student's statement, an opposing entry rather than a deletion;
 *   6. the ledger, the instalment's posting reversed;
 *   7. the plan itself, re-opened if this payment was what completed it.
 *
 * It refuses, writing nothing, when the payment is already reversed or when the
 * fee has since passed money on as credit that has been spent elsewhere —
 * unpicking that silently is exactly what PaymentReversalService refuses to do
 * for fee payments, and for the same reason.
 */
class InstallmentPaymentReversal
{
    public function __construct(
        protected TransactionAutoMapService $autoMap,
        protected FeeCreditReconciliation $credit,
    ) {
    }

    /**
     * Why this payment cannot be reversed — empty when it can.
     *
     * @return array<string>
     */
    public function blockers(PaymentPlanPayment $payment): array
    {
        $blockers = [];

        if ($payment->isReversed()) {
            $blockers[] = __('This payment has already been reversed.');
        }

        $installment = $payment->installment;
        $plan = $installment?->paymentPlan;

        if (!$installment || !$plan) {
            $blockers[] = __('This payment no longer belongs to an instalment on a payment plan.');

            return $blockers;
        }

        $fee = $plan->fee;

        if (!$fee) {
            $blockers[] = __('The fee this plan was built from no longer exists.');

            return $blockers;
        }

        // Taking this money off the fee may leave credit the fee raised with
        // nothing behind it. Where that credit has already settled another fee,
        // reversing would leave that fee paid with money that no longer exists.
        foreach ($this->spentCreditFrom($fee, (float) $payment->amount) as $spent) {
            $blockers[] = $spent;
        }

        return $blockers;
    }

    /**
     * Credit raised by this fee that this reversal would strand, and which has
     * already been applied to another fee. Wording matches the fee reversal, so
     * the two screens read the same.
     *
     * @return array<string>
     */
    protected function spentCreditFrom(Fee $fee, float $amount): array
    {
        $wouldBePaid = max(0, round((float) $fee->paid_amount - $amount, 2));

        $position = $this->credit->creditPosition($fee, null, $wouldBePaid);

        if (!$position || ($position['already_spent'] ?? 0) <= 0.009) {
            return [];
        }

        // Name where it went, so the message is something a person can act on.
        $names = CreditApplication::whereIn('student_credit_id',
                StudentCredit::where('source_fee_id', $fee->id)
                    ->where('source_type', StudentCredit::SOURCE_OVERPAYMENT)->pluck('id'))
            ->with('fee.category')
            ->get()
            ->map(fn ($application) => optional(optional($application->fee)->category)->title
                ? __('fee #:id :category', ['id' => $application->fee_id, 'category' => $application->fee->category->title])
                : __('fee #:id', ['id' => $application->fee_id]))
            ->unique()
            ->implode(', ');

        return [__('Reversing this payment would leave :amount of credit on this fee with nothing behind it, and that credit has already been applied to :fee. Undo that first, or reverse a different payment.', [
            'amount' => number_format($position['already_spent'], 2),
            'fee' => $names !== '' ? $names : __('another fee'),
        ])];
    }

    /**
     * @return array{reversed: bool, message: string}
     */
    public function reverse(PaymentPlanPayment $payment, string $reason): array
    {
        if ($blockers = $this->blockers($payment)) {
            return ['reversed' => false, 'message' => implode(' ', $blockers)];
        }

        $outcome = [];
        $ledgerIssue = null;

        DB::transaction(function () use ($payment, $reason, &$outcome, &$ledgerIssue) {
            $installment = PaymentPlanInstallment::lockForUpdate()->find($payment->installment_id);
            $plan = PaymentPlan::lockForUpdate()->find($installment->payment_plan_id);
            $fee = $plan->fee_id ? Fee::lockForUpdate()->find($plan->fee_id) : null;
            $amount = (float) $payment->amount;

            // 1. The payment itself. Kept — a reversal is a record, not an erasure.
            $payment->status = 'reversed';
            $payment->reversed_at = now();
            $payment->reversed_by = Auth::guard('web')->id();
            $payment->reversal_reason = $reason;
            $payment->save();

            // 2. The instalment: this payment off it, status re-derived.
            $this->undoOnInstallment($installment, $amount);

            // 3. The fee: the same amount off it.
            $this->undoOnFee($fee, $amount);

            // 4. The payment account, if this payment credited one.
            $this->reversePaymentAccount($payment, $reason);

            // 5. The student's statement: an opposing entry.
            $this->recordStatementReversal($plan, $amount);

            // 6. The ledger. The instalment posted on its own, so its posting is
            //    reversed on its own — the fee's posting is worked out from what
            //    the plans have taken, and this payment no longer counts.
            //
            //    It can refuse: a payment dated outside every accounting period
            //    cannot be posted or reversed. That must never stop the money
            //    being put right, so the failure is reported instead.
            try {
                $this->autoMap->reverse('payment_plan_payment', $payment->id, Auth::guard('web')->id());

                if ($fee) {
                    app(FeeLedgerPosting::class)->resync($fee->fresh(), Auth::guard('web')->id());
                }
            } catch (\Throwable $e) {
                $ledgerIssue = $e->getMessage();
                Log::warning('Instalment payment reversed but the ledger could not be updated', [
                    'payment_id' => $payment->id,
                    'installment_id' => $installment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 7. The plan: a completed plan is no longer complete.
            //
            // Read before, not after: reopenPlan updates this same instance, so
            // comparing it with itself afterwards always says nothing changed.
            $statusBefore = $plan->status;
            $this->reopenPlan($plan);

            $outcome = [
                'removed' => $amount,
                'installment_now' => (float) $installment->fresh()->paid_amount,
                'installment_number' => $installment->installment_number,
                'fee_now' => $fee ? (float) $fee->fresh()->paid_amount : 0.0,
                'plan_reopened' => $statusBefore !== 'active' && $plan->fresh()->status === 'active',
            ];
        });

        return ['reversed' => true, 'message' => $this->describe($outcome, $ledgerIssue)];
    }

    /** Subtract this payment from the instalment and work its status out again. */
    protected function undoOnInstallment(PaymentPlanInstallment $installment, float $amount): void
    {
        $paid = max(0, round((float) $installment->paid_amount - $amount, 2));
        $due = (float) $installment->amount + (float) ($installment->late_fee ?? 0);

        $installment->paid_amount = $paid;

        if ($paid >= $due - 0.009 && $due > 0) {
            $installment->status = 'paid';
        } elseif ($paid > 0.009) {
            $installment->status = 'partial';
        } else {
            $installment->status = $installment->is_overdue ? 'overdue' : 'pending';
            $installment->paid_at = null;
        }

        $installment->save();
    }

    /**
     * Subtract this payment from the fee.
     *
     * The pay date is left as it is when money remains: a fee that still holds
     * cash needs a date for the ledger to file it under, and the date of an
     * earlier payment is the honest one. It is cleared only when nothing is left.
     */
    protected function undoOnFee(?Fee $fee, float $amount): void
    {
        if (!$fee) {
            return;
        }

        $paid = max(0, round((float) $fee->paid_amount - $amount, 2));
        $due = (float) $fee->total_amount;

        $update = ['paid_amount' => $paid];

        // A cancelled fee stays cancelled.
        if ((int) $fee->status !== 3) {
            $update['status'] = $paid >= $due - 0.009 ? 1 : ($paid > 0.009 ? 2 : 0);
        }

        if ($paid <= 0.009) {
            $update['pay_date'] = null;
        }

        $fee->update($update);
    }

    /** Debit back whatever this payment credited to a payment account. */
    protected function reversePaymentAccount(PaymentPlanPayment $payment, string $reason): void
    {
        if (!$payment->payment_account_id) {
            return;
        }

        $account = PaymentAccount::lockForUpdate()->find($payment->payment_account_id);

        if (!$account) {
            return;
        }

        $balance = round((float) $account->current_balance - (float) $payment->amount, 2);

        $entry = new PaymentAccountTransaction();
        $entry->payment_account_id = $account->id;
        $entry->transaction_type = 'debit';        // money going back out
        $entry->amount = $payment->amount;
        $entry->transaction_date = now();
        $entry->title = __('Reversal of instalment payment #:id', ['id' => $payment->id]);
        $entry->description = $reason;
        $entry->payment_method = $payment->payment_method;
        $entry->reference_type = 'payment_plan_payments';
        $entry->reference_id = $payment->id;
        $entry->balance_after = $balance;
        $entry->created_by = Auth::guard('web')->id();
        $entry->save();

        $account->current_balance = $balance;
        $account->save();
    }

    /** The student's statement: an opposing entry, never a deletion. */
    protected function recordStatementReversal(PaymentPlan $plan, float $amount): void
    {
        $student = $plan->student;

        if (!$student) {
            return;
        }

        $entry = new Transaction();
        $entry->transaction_id = Str::random(16);
        $entry->amount = $amount;
        $entry->type = '2';     // money going back out
        $entry->created_by = Auth::guard('web')->id();
        $student->transactions()->save($entry);
    }

    /**
     * A plan marked completed is not complete once one of its payments is gone.
     *
     * The fee's own link back to the plan was cleared on completion, so it is
     * put back too — otherwise the fee could be paid directly at the same time
     * as the plan is still collecting for it.
     */
    protected function reopenPlan(PaymentPlan $plan): void
    {
        if ($plan->status !== 'completed') {
            return;
        }

        $outstanding = $plan->installments()->where('status', '!=', 'paid')->exists();

        if (!$outstanding) {
            return;
        }

        $plan->update(['status' => 'active']);

        if ($plan->fee && !$plan->fee->payment_plan_id) {
            $plan->fee->update(['payment_plan_id' => $plan->id]);
        }
    }

    /** Say what actually happened, in the numbers the person will check. */
    protected function describe(array $outcome, ?string $ledgerIssue = null): string
    {
        $message = __('Payment reversed. :amount taken off instalment :number, which now stands at :now.', [
            'amount' => number_format($outcome['removed'] ?? 0, 2),
            'number' => $outcome['installment_number'] ?? '?',
            'now' => number_format($outcome['installment_now'] ?? 0, 2),
        ]);

        $message .= ' ' . __('The fee now stands at :fee.', [
            'fee' => number_format($outcome['fee_now'] ?? 0, 2),
        ]);

        if (!empty($outcome['plan_reopened'])) {
            $message .= ' ' . __('The plan has been re-opened, as it is no longer fully paid.');
        }

        if ($ledgerIssue) {
            return $message . ' ' . __('The accounts have been put back, but its ledger entry could not be: :why', [
                'why' => $ledgerIssue,
            ]);
        }

        return $message . ' ' . __('The accounts and the ledger have been put back with it.');
    }
}
