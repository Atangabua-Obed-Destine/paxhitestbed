<?php

namespace App\Services;

use App\Models\Fee;
use Illuminate\Support\Collection;

/**
 * Spreading one handover of money across a student's instalments.
 *
 * Students here pay toward the year, not toward an instalment. Handing over
 * 400,000 against a 150,000 First Instalment used to book 250,000 as credit,
 * which someone then applied to the Second Instalment by hand. That hand-work is
 * not hypothetical — 91 credits were raised and all 87 applications were made by
 * hand — and it is what overstated the ledger, because credit applied to a fee
 * was posted as cash although the cash had already been posted on the fee it
 * came from.
 *
 * So the money goes to the right fee at the counter instead, and the credit is
 * never raised.
 *
 * Two things decide what is offered, and both are deliberate:
 *
 *  - **Instalments only.** Admission and resit charges are not swept up by a
 *    tuition payment; a bursar settles those on purpose. The discriminator is
 *    the category's own `is_first_installment` / `is_second_installment` flag,
 *    never the title.
 *  - **By student, not by enrolment.** For the 2026/2027 intake both
 *    instalments sit on one enrolment. For 2025/2026 the second was raised
 *    months later against the second-semester enrolment. Looking by enrolment
 *    finds nothing at all for that cohort.
 */
class FeePaymentDistribution
{
    protected FeePaymentRecorder $recorder;

    public function __construct(FeePaymentRecorder $recorder)
    {
        $this->recorder = $recorder;
    }

    /**
     * The fees this payment may be spread over, the one being paid first.
     *
     * @return Collection<int, Fee>
     */
    public function targets(Fee $fee): Collection
    {
        $studentId = optional($fee->studentEnroll)->student_id;

        if (!$studentId) {
            return collect([$fee]);
        }

        $others = Fee::with(['category', 'studentEnroll.student', 'paymentPlan', 'resitRequest'])
            ->whereHas('studentEnroll', fn ($query) => $query->where('student_id', $studentId))
            ->whereHas('category', function ($query) {
                $query->where('is_first_installment', 1)->orWhere('is_second_installment', 1);
            })
            ->where('id', '!=', $fee->id)
            // A cancelled fee is not a debt.
            ->where('status', '!=', '3')
            ->get()
            ->reject(function (Fee $candidate) {
                // A plan carries its own instalment dates and its own screen.
                // Direct payment to a plan-backed fee is refused everywhere
                // else here, and this is not the place to start allowing it.
                if ($candidate->hasActivePaymentPlan()) {
                    return true;
                }

                // Resit charges must be paid in full, deliberately, on their own.
                if ($candidate->resitRequest) {
                    return true;
                }

                return $this->recorder->outstanding($candidate) <= 0.005;
            })
            ->sortBy([
                // First instalment before second, then whichever falls due first.
                fn (Fee $a, Fee $b) => $this->rank($b) <=> $this->rank($a),
                fn (Fee $a, Fee $b) => strtotime((string) $a->due_date) <=> strtotime((string) $b->due_date),
                fn (Fee $a, Fee $b) => $a->id <=> $b->id,
            ])
            ->values();

        // The fee the bursar actually opened always comes first: that is the one
        // the student handed the money for.
        return collect([$fee])->concat($others);
    }

    /**
     * How an amount would be spread, filling each fee in turn.
     *
     * Nothing is written. `leftover` is whatever remains once every instalment
     * is settled — a genuine prepayment, which the caller must deal with.
     *
     * @return array{legs: array<int, array>, leftover: float, total: float}
     */
    public function propose(Fee $fee, float $amount): array
    {
        $remaining = round($amount, 2);
        $legs = [];

        foreach ($this->targets($fee) as $target) {
            if ($remaining <= 0.005) {
                break;
            }

            $outstanding = $this->recorder->outstanding($target);

            if ($outstanding <= 0.005) {
                continue;
            }

            $applied = min($remaining, $outstanding);
            $remaining = round($remaining - $applied, 2);

            $legs[] = [
                'fee_id' => $target->id,
                'category' => optional($target->category)->title,
                'due_date' => $target->due_date ? \Carbon\Carbon::parse($target->due_date)->format('Y-m-d') : null,
                'outstanding' => round($outstanding, 2),
                'applied' => round($applied, 2),
                'remaining_after' => round($outstanding - $applied, 2),
                'is_origin' => $target->id === $fee->id,
            ];
        }

        return [
            'legs' => $legs,
            'leftover' => round($remaining, 2),
            'total' => round($amount, 2),
        ];
    }

    /**
     * Why this spread cannot be recorded as it stands.
     *
     * `$legs` is what the bursar actually submitted — they may have moved money
     * between the instalments — so every one of them is checked again here
     * rather than trusted. An empty array means it can go ahead.
     *
     * @param array<int, array{fee_id: int|string, applied: float|string}> $legs
     * @return array<int, string>
     */
    public function blockers(Fee $fee, array $legs, float $amount): array
    {
        $reasons = [];

        if ($amount <= 0) {
            $reasons[] = __('The amount must be more than zero.');

            return $reasons;
        }

        if ($legs === []) {
            $reasons[] = __('Nothing was allocated.');

            return $reasons;
        }

        $allowed = $this->targets($fee)->keyBy('id');
        $sum = 0.0;
        $seen = [];

        foreach ($legs as $leg) {
            $id = (int) ($leg['fee_id'] ?? 0);
            $applied = round((float) ($leg['applied'] ?? 0), 2);
            $sum = round($sum + $applied, 2);

            if (isset($seen[$id])) {
                $reasons[] = __('Fee #:id appears twice in the split.', ['id' => $id]);
                continue;
            }

            $seen[$id] = true;

            if (!$allowed->has($id)) {
                // Covers another student's fee, a resit, a plan-backed fee and a
                // fee that is already settled, in one answer.
                $reasons[] = __('Fee #:id cannot take part of this payment.', ['id' => $id]);
                continue;
            }

            if ($applied < 0) {
                $reasons[] = __('A negative amount was allocated to fee #:id.', ['id' => $id]);
                continue;
            }

            $outstanding = $this->recorder->outstanding($allowed->get($id));

            if ($applied - $outstanding > 0.005) {
                $reasons[] = __(':amount was allocated to :category, which only needs :outstanding.', [
                    'amount' => number_format($applied, 2),
                    'category' => optional($allowed->get($id)->category)->title ?? ('fee #' . $id),
                    'outstanding' => number_format($outstanding, 2),
                ]);
            }
        }

        // The split must account for every franc handed over. Anything beyond
        // the instalments is leftover, and the caller decides what becomes of
        // it — but it must be stated, not lost.
        if ($sum - round($amount, 2) > 0.005) {
            $reasons[] = __('The split comes to :sum, which is more than the :amount received.', [
                'sum' => number_format($sum, 2),
                'amount' => number_format($amount, 2),
            ]);
        }

        return $reasons;
    }

    /**
     * First instalment outranks second; anything else sits below both.
     */
    protected function rank(Fee $fee): int
    {
        $category = $fee->category;

        if (!$category) {
            return 0;
        }

        if ($category->is_first_installment) {
            return 2;
        }

        return $category->is_second_installment ? 1 : 0;
    }
}
