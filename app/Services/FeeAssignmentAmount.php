<?php

namespace App\Services;

use App\Models\Fee;
use Illuminate\Support\Facades\DB;

/**
 * Correcting the amount on a fee that has already been assigned.
 *
 * A fee's amount is copied onto the student's row when they enrol, from the
 * programme's fee structure. Nothing reads that structure again afterwards, so
 * a figure configured wrongly stays wrong on every student raised from it, and
 * a fee with payments against it cannot be deleted and re-raised.
 *
 * The rule the school asked for is deliberately one-directional: the charge may
 * go **up**, because a student who has paid is simply asked for the difference,
 * but it may not come **down** once money has been taken against the old figure.
 * Lowering a charge someone has already paid would turn their payment into an
 * overpayment and issue credit for money the school never agreed to return.
 *
 * One class, used by both the single-row correction and the cohort one, so the
 * rule cannot be enforced in one place and forgotten in the other.
 */
class FeeAssignmentAmount
{
    /** Written into the fee's note, so the correction is legible on the record. */
    public const MARKER = 'Amount corrected';

    protected StudentCreditService $credits;
    protected FeeLedgerPosting $ledger;

    public function __construct(StudentCreditService $credits, FeeLedgerPosting $ledger)
    {
        $this->credits = $credits;
        $this->ledger = $ledger;
    }

    /**
     * Why this fee's amount cannot be changed to that one.
     *
     * An empty array means it can. Each reason is written to be shown to a
     * bursar as it is: it names the obstacle and what to do about it, because
     * "cannot edit" on its own sends someone looking in the wrong place.
     *
     * @return array<int, string>
     */
    public function blockers(Fee $fee, float $newAmount): array
    {
        $current = round((float) ($fee->fee_amount ?? 0), 2);
        $newAmount = round($newAmount, 2);
        $paid = (float) ($fee->paid_amount ?? 0);

        $reasons = [];

        if ($newAmount <= 0) {
            $reasons[] = __('The amount must be more than zero.');
        }

        if (abs($newAmount - $current) < 0.005) {
            $reasons[] = __('That is already the amount on this fee.');
        }

        if ($newAmount < $current) {
            // The whole point of the rule. Lowering is allowed only while the
            // fee is untouched, which is what makes a plain typo correctable
            // before anyone has paid against it.
            if ($paid > 0.005) {
                $reasons[] = __('This fee cannot be lowered: :paid has already been paid against it. Raise it, or reverse the payment first.', [
                    'paid' => number_format($paid, 2),
                ]);
            }

            if ($fee->paymentReceipts()->exists()) {
                $reasons[] = __('This fee cannot be lowered: a payment receipt has been recorded against it.');
            }

            if ($fee->creditApplications()->exists() || $fee->generatedCredits()->exists()) {
                $reasons[] = __('This fee cannot be lowered: credit has been applied to it or generated from it.');
            }
        }

        if ($fee->hasActivePaymentPlan()) {
            // A plan's total is a snapshot taken when it was agreed, and it is
            // what the student was given in writing. Changing the fee under it
            // would leave the plan completing while money is still owed.
            $reasons[] = __('This fee is on active payment plan #:plan. Cancel or re-issue the plan first.', [
                'plan' => $fee->payment_plan_id,
            ]);
        }

        if ($fee->resitRequest) {
            $reasons[] = __('A resit fee is set by the resit configuration, not here.');
        }

        if ($fee->pendingReceipt()->exists()) {
            $reasons[] = __('A payment receipt on this fee is waiting to be verified. Settle that first.');
        }

        if (method_exists($fee, 'pendingMultiPaymentDistributions')
            && $fee->pendingMultiPaymentDistributions()->exists()) {
            $reasons[] = __('A payment covering several fees is still being processed against this one.');
        }

        return $reasons;
    }

    public function canChange(Fee $fee, float $newAmount): bool
    {
        return $this->blockers($fee, $newAmount) === [];
    }

    /**
     * Change it, and leave everything that depends on it consistent.
     *
     * @return array{changed: bool, reasons: array, old: float, new: float, status: string, credit_applied: float, balance: float}
     */
    public function apply(Fee $fee, float $newAmount, ?string $reason = null, ?int $userId = null): array
    {
        $reasons = $this->blockers($fee, $newAmount);

        if ($reasons !== []) {
            return [
                'changed' => false,
                'reasons' => $reasons,
                'old' => round((float) $fee->fee_amount, 2),
                'new' => round($newAmount, 2),
                'status' => (string) $fee->status,
                'credit_applied' => 0.0,
                'balance' => round((float) $fee->remaining_balance, 2),
            ];
        }

        $old = round((float) $fee->fee_amount, 2);
        $new = round($newAmount, 2);

        $creditApplied = DB::transaction(function () use ($fee, $old, $new, $reason, $userId) {
            $fee->fee_amount = $new;
            $fee->status = $this->statusFor($fee, $new);
            $fee->updated_by = $userId;
            $fee->note = trim((string) $fee->note . "\n" . $this->noteFor($old, $new, $reason));
            $fee->save();

            // The student's own unspent credit settles the increase before they
            // are asked for it. Applying credit is not cash, so this adds
            // nothing to the ledger — it only moves money the school is already
            // holding for this student onto the charge it now belongs to.
            $applied = 0.0;

            if ($fee->fresh()->remaining_balance > 0.005) {
                $result = $this->credits->applyCreditsToFee($fee->fresh(), null, 'auto', $userId);
                $applied = (float) ($result['total_applied'] ?? 0);

                if ($applied > 0) {
                    // applyCreditsToFee moves paid_amount, so the status has to
                    // be read again from where the fee actually stands.
                    $fee->refresh();
                    $fee->status = $this->statusFor($fee, $new);
                    $fee->save();
                }
            }

            // Expected to change nothing: the ledger posts cash received, and
            // no cash moved here. Called so it can never be left behind if that
            // ever stops being true.
            $this->ledger->resync($fee->fresh(), $userId);

            return $applied;
        });

        $fee->refresh();

        return [
            'changed' => true,
            'reasons' => [],
            'old' => $old,
            'new' => $new,
            'status' => (string) $fee->status,
            'credit_applied' => round($creditApplied, 2),
            'balance' => round((float) $fee->remaining_balance, 2),
        ];
    }

    /**
     * Paid or partly paid, decided the way a payment decides it.
     *
     * The same comparison FeesStudentController makes when a payment is taken,
     * so there is one answer to what "paid" means. Without this a fee raised
     * above what was paid keeps the status it had and goes on reading as
     * settled while money is owed — the worst way this could fail.
     */
    protected function statusFor(Fee $fee, float $newAmount): string
    {
        $paid = (float) ($fee->paid_amount ?? 0);

        if ($paid <= 0.005) {
            return '0';
        }

        $total = $newAmount + (float) ($fee->fine_amount ?? 0) - (float) ($fee->discount_amount ?? 0);

        return $paid >= $total ? '1' : '2';
    }

    protected function noteFor(float $old, float $new, ?string $reason): string
    {
        $line = self::MARKER . ' on ' . now()->format('d M Y') . ': '
            . number_format($old, 2) . ' to ' . number_format($new, 2) . '.';

        $reason = trim((string) $reason);

        return $reason === '' ? $line : $line . ' ' . $reason;
    }
}
