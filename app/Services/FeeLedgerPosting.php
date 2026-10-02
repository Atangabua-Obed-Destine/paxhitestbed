<?php

namespace App\Services;

use App\Models\DefaultAccountMapping;
use App\Models\Fee;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a fee's ledger posting equal to the cash the fee actually received.
 *
 * Fees used to be posted at paid_amount. When an overpaid fee's excess was
 * applied to another fee as student credit, that second fee's paid_amount grew
 * and it was posted again as cash received — Dr cash, Cr tuition income — for
 * money that had already been posted on the first fee. Cash and income were
 * both overstated by every franc of credit moved.
 *
 * A fee is now posted at its cash received (Fee::cash_received_amount). Credit
 * brought in from another fee is not cash; money transferred out of a fee was.
 *
 * Every change goes through TransactionAutoMapService, so the ledger keeps its
 * usual audit trail: the old entry is reversed, never deleted, and a reversal
 * lands in the original month while that period is open, or today once it is
 * closed.
 */
class FeeLedgerPosting
{
    public const UNCHANGED = 'unchanged';
    public const POSTED = 'posted';
    public const REPOSTED = 'reposted';
    public const REVERSED = 'reversed';
    public const NO_MAPPING = 'no_mapping';
    public const SKIPPED = 'skipped';

    public function __construct(protected TransactionAutoMapService $autoMap)
    {
    }

    /**
     * What this fee's payment plans have taken from the student.
     *
     * That money belongs to the ledger as instalments — PaymentPlanPaymentObserver
     * posts each one as its own transaction — so it must come off whatever the
     * fee itself posts, or the same cash is counted twice.
     *
     * Recorded, not posted: an instalment payment that failed to post (no
     * account mapping for the category, or a date in no accounting period) is
     * still the plan's money. Letting it slip onto the fee would hide the gap
     * the mappings screen exists to show, and would post it twice as soon as the
     * instalment was posted properly.
     */
    public function paidThroughPlans(Fee $fee): float
    {
        $total = DB::table('payment_plan_payments as ppp')
            ->join('payment_plan_installments as ppi', 'ppi.id', '=', 'ppp.installment_id')
            ->join('payment_plans as pp', 'pp.id', '=', 'ppi.payment_plan_id')
            ->where('pp.fee_id', $fee->id)
            ->where('ppp.status', '!=', 'reversed')
            ->sum('ppp.amount');

        return round((float) $total, 2);
    }

    /** What the fee's active posting carries, or null when it has none. */
    public function postedAmount(Fee $fee): ?float
    {
        $total = DB::table('transaction_mappings as tm')
            ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
            ->where('tm.transaction_type', 'fee')
            ->where('tm.transaction_id', $fee->id)
            ->where('tm.status', 'active')
            ->value('je.total_debit');

        return $total === null ? null : round((float) $total, 2);
    }

    /**
     * Bring the fee's posting into line with its cash received.
     *
     * @param bool $force repost even when the amount already matches — for a
     *                    changed category or date, which move the entry
     *
     * @return string one of the class constants
     * @throws \RuntimeException when a repost fails; the reversal is undone with it
     */
    public function resync(Fee $fee, ?int $userId = null, bool $force = false): string
    {
        // Money taken through a payment plan is posted instalment by instalment,
        // so only what the fee took directly is left for the fee to post.
        //
        // This used to be a flat skip whenever the fee carried a plan link, which
        // was wrong at both ends: a fee whose plan was cancelled and which was
        // then paid directly stayed unposted for good, and a fee whose plan had
        // completed lost the protection entirely — the link is cleared on
        // completion, so its whole amount became postable on top of the
        // instalments. Subtracting is true in every one of those states, and
        // needs no link to work.
        $cash = round($fee->cash_received_amount - $this->paidThroughPlans($fee), 2);
        $posted = $this->postedAmount($fee);
        $shouldPost = $cash > 0.009 && !empty($fee->pay_date);

        // Settled wholly by credit, or unpaid: nothing belongs in the ledger.
        if (!$shouldPost) {
            if ($posted === null) {
                return self::UNCHANGED;
            }

            $this->autoMap->reverse('fee', $fee->id, $userId);

            return self::REVERSED;
        }

        if ($posted !== null && !$force && abs($posted - $cash) < 0.01) {
            return self::UNCHANGED;
        }

        $payload = [
            'amount' => $cash,
            'date' => $fee->pay_date,
            'description' => 'Fee Payment - ' . ($fee->category->name ?? 'Student Fee'),
        ];

        // A first posting goes straight through: if the category has no
        // mapping, autoMap says so and nothing has been touched.
        if ($posted === null) {
            return $this->autoMap->autoMap('fee', $fee->id, $fee->category_id, $payload)
                ? self::POSTED
                : self::NO_MAPPING;
        }

        // A repost reverses first. Without a mapping to repost against, that
        // would leave the fee unposted, so an existing posting is left alone.
        if (!$this->hasMapping($fee)) {
            return self::NO_MAPPING;
        }

        return DB::transaction(function () use ($fee, $payload, $userId) {
            if (!$this->autoMap->remap('fee', $fee->id, $fee->category_id, $payload, $userId)) {
                throw new \RuntimeException("Fee #{$fee->id} could not be reposted; its posting was left as it was.");
            }

            return self::REPOSTED;
        });
    }

    protected function hasMapping(Fee $fee): bool
    {
        return DefaultAccountMapping::where('mapping_type', 'fee_category')
            ->where(function ($query) use ($fee) {
                $fee->category_id
                    ? $query->where('category_id', $fee->category_id)
                    : $query->whereNull('category_id');
            })
            ->where('status', 'active')
            ->exists();
    }
}
