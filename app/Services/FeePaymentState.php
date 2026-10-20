<?php

namespace App\Services;

use App\Models\Fee;

/**
 * Has anything at all been paid towards this fee?
 *
 * It sounds like `paid_amount > 0` and it is not. The live data shows why:
 *
 *   - one fee has a receipt still awaiting verification. Real money was handed
 *     over; `paid_amount` is still zero until somebody approves it. Treating
 *     that fee as untouched would let it be replaced out from under a payment
 *     that is already in the building.
 *   - twenty-two fees have `paid_amount > 0` with no approved receipt at all -
 *     every one of them was settled by credit. Looking only at receipts would
 *     call those untouched too.
 *
 * So the answer is the union of every kind of evidence, and it already existed
 * twice: once in FeeAssignmentsHistoryController::destroy() for "may I delete
 * this?", once in FeeAssignmentAmount::blockers() for "may I lower this?".
 * Two copies of a money rule is one more than is safe, and a programme change
 * needed it as a third. This is that rule, in one place.
 *
 * Note it deliberately reads RAW `paid_amount`, not `net_paid_amount`. A fee
 * whose overpayment was later moved out as credit has a net figure back near
 * zero, but it has certainly been paid against and must never be replaced.
 */
class FeePaymentState
{
    /**
     * Nothing has been paid, promised, or credited against this fee.
     *
     * The only state in which an assignment may be replaced outright rather
     * than corrected.
     */
    public static function isUntouched(Fee $fee): bool
    {
        return static::evidence($fee) === [];
    }

    /** The opposite, for callers that read better that way. */
    public static function hasBeenPaidAgainst(Fee $fee): bool
    {
        return !static::isUntouched($fee);
    }

    /**
     * Why this fee counts as touched, in words a registrar can act on.
     *
     * An empty array means nothing has touched it.
     *
     * @return array<int, string>
     */
    public static function evidence(Fee $fee): array
    {
        $reasons = [];

        $paid = (float) ($fee->paid_amount ?? 0);

        if ($paid > 0.005) {
            $reasons[] = __(':paid has been paid against it.', [
                'paid' => number_format($paid, 2),
            ]);
        }

        // 1 = fully paid, 2 = partly paid.
        if (in_array((int) $fee->status, [1, 2], true)) {
            $reasons[] = __('It is marked as paid.');
        }

        // Any receipt, of any status: a receipt awaiting verification is money
        // already handed over.
        if ($fee->paymentReceipts()->exists()) {
            $reasons[] = __('A payment receipt has been recorded against it.');
        }

        if ($fee->creditApplications()->exists()) {
            $reasons[] = __('Credit has been applied to it.');
        }

        if ($fee->generatedCredits()->exists()) {
            $reasons[] = __('Credit was raised from it.');
        }

        if (method_exists($fee, 'pendingMultiPaymentDistributions')
            && $fee->pendingMultiPaymentDistributions()->exists()) {
            $reasons[] = __('A split payment against it is still pending.');
        }

        if ($fee->payment_plan_id && $fee->paymentPlan
            && $fee->paymentPlan->status === 'active') {
            $reasons[] = __('It is on an active payment plan.');
        }

        return $reasons;
    }
}
