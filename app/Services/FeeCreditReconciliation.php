<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\StudentCredit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the First-to-Second Instalment credit transfers did to the fee figures,
 * and the one correction that is safe to make to the data.
 *
 * Last academic year only First Instalments were configured, so students
 * overpaid them; when Second Instalments were added the excess was raised as
 * student credit and applied to them. That left three problems, reported here:
 *
 *   1. The applied credit sits in two fees' paid_amount — the source it was
 *      never taken off, and the target it was added to — so totals built from
 *      paid_amount count it twice. Fixed in the reporting (Fee::netPaidSql);
 *      the stored data is not changed.
 *   2. On a few fees an overpayment was credited twice: an early credit for a
 *      partial excess, then a new credit for the whole excess. The duplicated
 *      part is credit no payment backs, and it can still be spent. That is the
 *      correction voidDuplicateCredits() makes.
 *   3. The ledger was posted from paid_amount too, so the applied credit is also
 *      in cash and tuition income twice. That needs an accounting decision and
 *      is reported, not corrected.
 *
 * Everything is computed from the data, so it gives the right answer on any
 * installation, not just the one it was written against.
 */
class FeeCreditReconciliation
{
    /** Totals paid, credit moved between fees, and what was actually collected. */
    public function collected(): array
    {
        $net = Fee::netPaidSql('f');

        $byCategory = DB::table('fees as f')
            ->leftJoin('fees_categories as fc', 'fc.id', '=', 'f.category_id')
            ->selectRaw("fc.title as category, COUNT(*) as fees,
                         SUM(f.paid_amount) as paid,
                         SUM(f.paid_amount - {$net}) as moved_out,
                         SUM({$net}) as net_paid")
            ->groupBy('fc.title')
            ->orderBy('fc.title')
            ->get();

        return [
            'paid' => (float) $byCategory->sum('paid'),
            'moved_out' => (float) $byCategory->sum('moved_out'),
            'net_paid' => (float) $byCategory->sum('net_paid'),
            'by_category' => $byCategory,
        ];
    }

    /**
     * Fees on which more overpayment credit was raised than was ever overpaid.
     *
     * The genuine overpayment is what the fee is overpaid by now, plus anything
     * already moved out of it by a manual transfer (which reduces paid_amount).
     * Credit beyond that is not backed by any payment. As much of it as is still
     * unspent can be voided; any part already spent is reported for review,
     * because that money has reached another fee and cannot simply be withdrawn.
     */
    public function duplicateCredits(): Collection
    {
        $credits = StudentCredit::whereNotNull('source_fee_id')
            ->whereIn('source_type', [StudentCredit::SOURCE_OVERPAYMENT, StudentCredit::SOURCE_TRANSFER])
            ->orderBy('id')
            ->get()
            ->groupBy('source_fee_id');

        $fees = Fee::with('category')->whereIn('id', $credits->keys())->get()->keyBy('id');
        $findings = collect();

        foreach ($credits as $feeId => $feeCredits) {
            $fee = $fees->get($feeId);

            if (!$fee) {
                continue;
            }

            $overpayment = $feeCredits->where('source_type', StudentCredit::SOURCE_OVERPAYMENT);
            $transferredOut = (float) $feeCredits->where('source_type', StudentCredit::SOURCE_TRANSFER)->sum('original_amount');

            $overpaidNow = max(0, (float) $fee->paid_amount - (float) $fee->total_amount);
            $genuine = round($overpaidNow + $transferredOut, 2);
            $credited = round((float) $overpayment->sum('original_amount'), 2);
            $excess = round($credited - $genuine, 2);

            if ($excess <= 0.009) {
                continue;
            }

            // Take it from the most recent credit first: the later credit is the
            // one that re-counted an excess already credited.
            $toVoid = $excess;
            $plan = [];

            foreach ($overpayment->sortByDesc('id') as $credit) {
                if ($toVoid <= 0.009) {
                    break;
                }

                $void = round(min((float) $credit->remaining_amount, $toVoid), 2);

                if ($void <= 0.009) {
                    continue;
                }

                $plan[] = [
                    'credit_id' => $credit->id,
                    'remaining' => (float) $credit->remaining_amount,
                    'void' => $void,
                ];
                $toVoid = round($toVoid - $void, 2);
            }

            $findings->push([
                'fee_id' => $fee->id,
                'student_id' => $overpayment->first()->student_id ?? null,
                'category' => optional($fee->category)->title,
                'due' => (float) $fee->total_amount,
                'paid' => (float) $fee->paid_amount,
                'genuine_overpayment' => $genuine,
                'credited' => $credited,
                'excess' => $excess,
                'voidable' => round($excess - $toVoid, 2),
                'already_spent' => $toVoid,
                'plan' => $plan,
            ]);
        }

        return $findings;
    }

    /**
     * Cancel the unspent credit that no payment backs.
     *
     * Only remaining_amount is reduced; nothing already applied is touched, no
     * credit is deleted, and each change is noted on the credit itself.
     *
     * @return array{credits: int, amount: float, needs_review: float}
     */
    public function voidDuplicateCredits(?int $userId = null, string $by = 'fees:credit-audit'): array
    {
        return DB::transaction(function () use ($userId, $by) {
            $voidedCredits = 0;
            $voidedAmount = 0.0;
            $review = 0.0;
            $stamp = now()->format('Y-m-d H:i');

            // Worked out again inside the transaction, so it acts on the data as
            // it is at this moment, not as it was when the preview was shown.
            foreach ($this->duplicateCredits() as $finding) {
                $review += $finding['already_spent'];

                foreach ($finding['plan'] as $step) {
                    $credit = StudentCredit::lockForUpdate()->find($step['credit_id']);

                    if (!$credit) {
                        continue;
                    }

                    $void = round(min((float) $credit->remaining_amount, $step['void']), 2);

                    if ($void <= 0.009) {
                        continue;
                    }

                    $credit->remaining_amount = round((float) $credit->remaining_amount - $void, 2);

                    if ($credit->remaining_amount <= 0.009) {
                        $credit->remaining_amount = 0;
                        $credit->status = $credit->applications()->exists()
                            ? StudentCredit::STATUS_FULLY_APPLIED
                            : StudentCredit::STATUS_EXPIRED;
                    }

                    $credit->note = trim(($credit->note ? $credit->note . ' | ' : '')
                        . 'Voided ' . number_format($void, 2) . " on {$stamp} by {$by}: duplicate credit for an overpayment on fee #{$finding['fee_id']} already credited in full; no payment backs it.");
                    $credit->updated_by = $userId;
                    $credit->save();

                    $voidedCredits++;
                    $voidedAmount += $void;
                }
            }

            return [
                'credits' => $voidedCredits,
                'amount' => round($voidedAmount, 2),
                'needs_review' => round($review, 2),
            ];
        });
    }

    /**
     * Fees whose ledger posting differs from the cash they actually received —
     * what correctLedger() would repost, and where each correction would land.
     */
    public function ledgerCorrections(): Collection
    {
        $postings = DB::table('transaction_mappings as tm')
            ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
            ->leftJoin('accounting_periods as ap', 'ap.id', '=', 'je.accounting_period_id')
            ->where('tm.transaction_type', 'fee')
            ->where('tm.status', 'active')
            ->select('tm.transaction_id as fee_id', 'je.total_debit as posted', 'je.entry_date', 'ap.name as period', 'ap.is_closed')
            ->get()
            ->keyBy('fee_id');

        if ($postings->isEmpty()) {
            return collect();
        }

        $cash = DB::table('fees')
            ->whereIn('id', $postings->keys())
            ->whereNull('payment_plan_id')
            ->selectRaw('id, ' . Fee::cashReceivedSql('fees') . ' as cash')
            ->pluck('cash', 'id');

        $fees = Fee::with(['category', 'studentEnroll.student'])->whereIn('id', $cash->keys())->get()->keyBy('id');

        return $cash->map(function ($amount, $feeId) use ($postings, $fees) {
            $posting = $postings[$feeId];
            $fee = $fees->get($feeId);
            $difference = round((float) $posting->posted - (float) $amount, 2);

            if (abs($difference) < 0.01) {
                return null;
            }

            return [
                'fee_id' => (int) $feeId,
                'category' => optional(optional($fee)->category)->title,
                'student' => optional(optional(optional($fee)->studentEnroll)->student)->student_id,
                'posted' => (float) $posting->posted,
                'cash' => round((float) $amount, 2),
                'difference' => $difference,
                'entry_date' => substr((string) $posting->entry_date, 0, 10),
                'period' => $posting->period,
                // A closed period's figures have been reported, so its
                // correction is a new event dated today instead.
                'lands_today' => (bool) $posting->is_closed,
            ];
        })->filter()->sortBy('fee_id')->values();
    }

    /**
     * Repost every fee whose posting differs from its cash received.
     *
     * One fee at a time, each in its own transaction: a fee that cannot be
     * reposted is left exactly as it was and reported, and the rest go ahead.
     * Nothing is deleted — every original entry is reversed.
     *
     * @return array{corrected: int, amount: float, landed_today: int, failed: array}
     */
    public function correctLedger(?int $userId = null): array
    {
        $posting = app(FeeLedgerPosting::class);
        $corrected = 0;
        $amount = 0.0;
        $landedToday = 0;
        $failed = [];

        foreach ($this->ledgerCorrections() as $row) {
            try {
                $result = $posting->resync(Fee::findOrFail($row['fee_id']), $userId);

                if (in_array($result, [FeeLedgerPosting::REPOSTED, FeeLedgerPosting::REVERSED, FeeLedgerPosting::POSTED], true)) {
                    $corrected++;
                    $amount += $row['difference'];
                    $landedToday += $row['lands_today'] ? 1 : 0;
                } elseif ($result === FeeLedgerPosting::NO_MAPPING) {
                    $failed[] = ['fee_id' => $row['fee_id'], 'reason' => 'No account mapping for this fee category; its posting was left as it was.'];
                }
            } catch (\Throwable $e) {
                $failed[] = ['fee_id' => $row['fee_id'], 'reason' => $e->getMessage()];
            }
        }

        return [
            'corrected' => $corrected,
            'amount' => round($amount, 2),
            'landed_today' => $landedToday,
            'failed' => $failed,
        ];
    }

    /**
     * How far the ledger's fee postings exceed what was collected. The ledger is
     * posted from paid_amount, so it carries the moved credit twice as well.
     */
    public function ledgerExposure(): array
    {
        $postedIds = DB::table('transaction_mappings')
            ->where('transaction_type', 'fee')
            ->where('status', 'active')
            ->pluck('journal_entry_id');

        $posted = (float) DB::table('journal_entries')->whereIn('id', $postedIds)->sum('total_debit');
        $netPaid = (float) DB::table('fees')->sum(DB::raw(Fee::netPaidSql('fees')));

        $byMonth = DB::table('credit_applications as ca')
            ->join('student_credits as sc', 'sc.id', '=', 'ca.student_credit_id')
            ->where('sc.source_type', StudentCredit::SOURCE_OVERPAYMENT)
            ->selectRaw("DATE_FORMAT(ca.created_at, '%Y-%m') as month, COUNT(*) as applications, SUM(ca.amount_applied) as amount")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'posted' => $posted,
            'net_paid' => $netPaid,
            'overstated' => round($posted - $netPaid, 2),
            'by_month' => $byMonth,
        ];
    }

    /** Fees showing more paid than their receipts and credit account for. */
    public function unevidencedPayments(): Collection
    {
        $receipts = DB::table('payment_receipts')->where('verification_status', 'approved')
            ->selectRaw('fee_id, SUM(amount) as amount')->groupBy('fee_id')->pluck('amount', 'fee_id');
        $creditIn = DB::table('credit_applications')
            ->selectRaw('fee_id, SUM(amount_applied) as amount')->groupBy('fee_id')->pluck('amount', 'fee_id');
        $transferredOut = DB::table('student_credits')->where('source_type', StudentCredit::SOURCE_TRANSFER)
            ->whereNotNull('source_fee_id')
            ->selectRaw('source_fee_id, SUM(original_amount) as amount')->groupBy('source_fee_id')->pluck('amount', 'source_fee_id');

        return Fee::with(['category', 'studentEnroll.student'])->where('paid_amount', '>', 0)->get()
            ->map(function (Fee $fee) use ($receipts, $creditIn, $transferredOut) {
                $evidenced = (float) ($receipts[$fee->id] ?? 0)
                    + (float) ($creditIn[$fee->id] ?? 0)
                    - (float) ($transferredOut[$fee->id] ?? 0);

                return [
                    'fee_id' => $fee->id,
                    'category' => optional($fee->category)->title,
                    'student' => optional(optional($fee->studentEnroll)->student)->student_id,
                    'paid' => (float) $fee->paid_amount,
                    'evidenced' => round($evidenced, 2),
                    'unevidenced' => round((float) $fee->paid_amount - $evidenced, 2),
                ];
            })
            ->filter(fn ($row) => $row['unevidenced'] > 0.009)
            ->values();
    }

    /** Credit applications whose fee has since been deleted. */
    public function orphanApplications(): Collection
    {
        return DB::table('credit_applications as ca')
            ->leftJoin('fees as f', 'f.id', '=', 'ca.fee_id')
            ->join('student_credits as sc', 'sc.id', '=', 'ca.student_credit_id')
            ->whereNull('f.id')
            ->select('ca.id', 'ca.amount_applied', 'ca.created_at', 'sc.id as credit_id', 'sc.student_id')
            ->orderBy('ca.id')
            ->get();
    }
}
