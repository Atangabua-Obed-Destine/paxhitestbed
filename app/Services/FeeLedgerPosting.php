<?php

namespace App\Services;

use App\Models\DefaultAccountMapping;
use App\Models\Fee;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\CashAccounts;
use App\Services\Accounting\FeeAccrual;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a fee's ledger postings equal to the cash the fee actually received,
 * each part on the day it came in.
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
 * And by date. A fee has one pay_date, which every payment overwrites, so one
 * entry for all its cash put October's money in December. Now the cash its
 * approved receipts show arriving before pay_date is posted on those days,
 * into the accounts those receipts name, as "parts" (fee_cash_parts); the
 * fee's own entry carries the rest on pay_date. Receipts on the pay_date
 * itself stay in the fee's own entry, so an ordinary payment makes no extra
 * entries, and the order in which a payment saves the fee and its receipt
 * changes nothing.
 *
 * Every change goes through TransactionAutoMapService or a reversal, so the
 * ledger keeps its usual audit trail: the old entry is reversed, never
 * deleted, and a reversal lands in the original month while that period is
 * open, or today once it is closed.
 */
class FeeLedgerPosting
{
    use Accounting\Concerns\PostsJournalEntries;

    public const UNCHANGED = 'unchanged';
    public const POSTED = 'posted';
    public const REPOSTED = 'reposted';
    public const REVERSED = 'reversed';
    public const NO_MAPPING = 'no_mapping';
    public const SKIPPED = 'skipped';

    /** Journal reference types of the earlier-dated parts. */
    public const PART_TYPES = ['fee_receipt', 'fee_receipt_reversal'];

    public function __construct(protected TransactionAutoMapService $autoMap)
    {
    }

    /* ===================================================================
     |  The rule, as SQL, for every report that needs it
     |===================================================================*/

    /** SQL: what a fee's postings carry in all — its cash received less plan instalments. */
    public static function postableSql(string $alias = 'fees'): string
    {
        return '(' . Fee::cashReceivedSql($alias)
            . ' - (SELECT COALESCE(SUM(ppp.amount), 0) FROM payment_plan_payments ppp'
            . ' JOIN payment_plan_installments ppi ON ppi.id = ppp.installment_id'
            . ' JOIN payment_plans pp ON pp.id = ppi.payment_plan_id'
            . ' WHERE pp.fee_id = ' . $alias . ".id AND ppp.status != 'reversed'))";
    }

    /**
     * SQL condition: receipt `pr` of fee `$alias` is posted on its own day.
     * Approved, dated before the fee's pay_date, and inside a fiscal year: a
     * receipt from before the books began has no month to post into, so it
     * stays with the fee's own entry.
     */
    public static function splitReceiptCondition(string $alias = 'fees'): string
    {
        return "pr.verification_status = 'approved' AND {$alias}.pay_date IS NOT NULL AND pr.payment_date < {$alias}.pay_date"
            . ' AND EXISTS (SELECT 1 FROM fiscal_years fy WHERE pr.payment_date BETWEEN fy.start_date AND fy.end_date)';
    }

    /** SQL: cash its approved receipts show arriving before its pay_date (inside the books). */
    public static function priorSql(string $alias = 'fees'): string
    {
        return "(SELECT COALESCE(SUM(pr.amount), 0) FROM payment_receipts pr WHERE pr.fee_id = {$alias}.id AND "
            . self::splitReceiptCondition($alias) . ')';
    }

    /**
     * SQL: what the fee's own entry carries, on its pay_date: the postable
     * amount less the earlier-dated parts. When receipts claim more than the
     * fee received (a transfer out after the fact), nothing is split off and
     * the fee's entry carries it all, as before.
     */
    public static function remainderSql(string $alias = 'fees'): string
    {
        $postable = self::postableSql($alias);
        $prior = self::priorSql($alias);

        return "({$postable} - CASE WHEN {$prior} <= {$postable} + 0.005 THEN {$prior} ELSE 0 END)";
    }

    /** SQL: the earlier-dated parts that are split off (0 when they would exceed the fee's cash). */
    public static function splitPriorSql(string $alias = 'fees'): string
    {
        $postable = self::postableSql($alias);
        $prior = self::priorSql($alias);

        return "(CASE WHEN {$prior} <= {$postable} + 0.005 THEN {$prior} ELSE 0 END)";
    }

    /**
     * SQL for a derived table of fee cash events (fee_id, event_date, amount):
     * each fee's earlier receipts on their own days, and the rest of its cash
     * received on its pay_date. The ledger posts exactly this split, so the
     * budget sheet, the daybook and the fee collection chart read it too and
     * agree with the ledger month by month.
     */
    public static function cashEventsSql(): string
    {
        $cash = Fee::cashReceivedSql('f');
        $split = self::splitPriorSql('f');

        return "SELECT f.id AS fee_id, f.pay_date AS event_date, ({$cash} - {$split}) AS amount"
            . " FROM fees f WHERE f.paid_amount > 0 AND f.pay_date IS NOT NULL"
            . " UNION ALL"
            . " SELECT pr.fee_id, pr.payment_date AS event_date, pr.amount FROM payment_receipts pr"
            . " JOIN fees f ON f.id = pr.fee_id"
            . " WHERE f.paid_amount > 0 AND " . self::splitReceiptCondition('f') . " AND {$split} > 0";
    }

    /* ===================================================================
     |  Posting
     |===================================================================*/

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

    /** What the fee's own (pay_date) posting carries, or null when it has none. */
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
     * The earlier-dated parts the fee's receipts call for, one per day and
     * account. Empty when nothing came in before pay_date, when the receipts
     * claim more than the fee received, or when the category has no mapping.
     *
     * @return array<string, array{date:string, debit:int, credit:int, amount:float}>
     */
    public function desiredParts(Fee $fee, float $postable): array
    {
        if (empty($fee->pay_date) || $postable <= 0.009) {
            return [];
        }

        $receipts = DB::table('payment_receipts as pr')
            ->join('fees', 'fees.id', '=', 'pr.fee_id')
            ->where('pr.fee_id', $fee->id)
            ->whereRaw(self::splitReceiptCondition('fees'))
            ->orderBy('pr.payment_date')->orderBy('pr.id')
            ->get(['pr.payment_date', 'pr.amount', 'pr.payment_method', 'pr.payment_account_id']);

        if ($receipts->isEmpty() || (float) $receipts->sum('amount') > $postable + 0.005) {
            return [];
        }

        $mapping = DefaultAccountMapping::where('mapping_type', 'fee_category')
            ->where('category_id', $fee->category_id)->where('status', 'active')->first();
        if (!$mapping) {
            return [];
        }

        $credit = FeeAccrual::settlementAccountFor('fee', (int) $fee->id) ?: (int) $mapping->credit_account_id;
        $cash = app(CashAccounts::class);
        $parts = [];

        foreach ($receipts as $r) {
            $debit = ($r->payment_account_id ? $cash->forPaymentAccount($r->payment_account_id) : null)
                ?: ($r->payment_method ? $cash->forCurrentMethod((int) $r->payment_method) : null)
                ?: (int) $mapping->debit_account_id;
            $date = substr((string) $r->payment_date, 0, 10);
            $key = $date . '|' . $debit . '|' . $credit;
            $parts[$key] = [
                'date' => $date,
                'debit' => (int) $debit,
                'credit' => (int) $credit,
                'amount' => round(($parts[$key]['amount'] ?? 0) + (float) $r->amount, 2),
            ];
        }

        return $parts;
    }

    /**
     * Bring the fee's postings into line with its cash received, part by part.
     *
     * @param bool $force repost the pay_date entry even when the amount already
     *                    matches — for a changed category, date or account
     *
     * @return string one of the class constants
     * @throws \RuntimeException when a repost fails; the reversal is undone with it
     */
    public function resync(Fee $fee, ?int $userId = null, bool $force = false): string
    {
        // Money taken through a payment plan is posted instalment by instalment,
        // so only what the fee took directly is left for the fee to post.
        $postable = round($fee->cash_received_amount - $this->paidThroughPlans($fee), 2);

        return DB::transaction(function () use ($fee, $postable, $userId, $force) {
            $parts = $this->desiredParts($fee, $postable);
            $partsChanged = $this->syncParts($fee, $parts, $userId ?? Auth::id());
            $cash = round($postable - array_sum(array_column($parts, 'amount')), 2);

            $result = $this->resyncOwnEntry($fee, $cash, $userId, $force);

            return ($result === self::UNCHANGED && $partsChanged) ? self::REPOSTED : $result;
        });
    }

    /** The fee's own entry, on its pay_date, for what is left after the parts. */
    protected function resyncOwnEntry(Fee $fee, float $cash, ?int $userId, bool $force): string
    {
        $posted = $this->postedAmount($fee);
        $shouldPost = $cash > 0.009 && !empty($fee->pay_date);

        // Settled wholly by credit, unpaid, or all in earlier parts: nothing here.
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

        if (!$this->autoMap->remap('fee', $fee->id, $fee->category_id, $payload, $userId)) {
            throw new \RuntimeException("Fee #{$fee->id} could not be reposted; its posting was left as it was.");
        }

        return self::REPOSTED;
    }

    /** Make the fee's earlier-dated parts match $desired. @return bool whether anything changed */
    protected function syncParts(Fee $fee, array $desired, ?int $userId): bool
    {
        $changed = false;
        $active = DB::table('fee_cash_parts')->where('fee_id', $fee->id)->where('status', 'active')->lockForUpdate()->get()
            ->keyBy(fn ($p) => substr((string) $p->entry_date, 0, 10) . '|' . $p->debit_account_id . '|' . $p->credit_account_id);

        foreach ($active as $key => $part) {
            // Landed on another day because its month was closed: matched by account and amount.
            $want = $desired[$key] ?? null;
            if ($want && abs((float) $part->amount - $want['amount']) < 0.01) {
                unset($desired[$key]);
                continue;
            }

            $this->reversePart($part, $userId);
            $changed = true;
        }

        foreach ($desired as $want) {
            $this->postPart($fee, $want, $userId);
            $changed = true;
        }

        return $changed;
    }

    protected function postPart(Fee $fee, array $want, ?int $userId): void
    {
        $date = $this->openDate($want['date']);
        $description = 'Fee Payment - ' . ($fee->category->title ?? $fee->category->name ?? 'Student Fee') . ' (received ' . $want['date'] . ', fee #' . $fee->id . ')';

        $partId = DB::table('fee_cash_parts')->insertGetId([
            'fee_id' => $fee->id,
            'entry_date' => $want['date'],
            'debit_account_id' => $want['debit'],
            'credit_account_id' => $want['credit'],
            'amount' => $want['amount'],
            'status' => 'active',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entry = $this->newEntry($date, 'cash', $description, 'fee_receipt', $partId, $want['amount'], $userId, 'FEE-' . $fee->id);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 1, 'account_id' => $want['debit'],
            'description' => $description, 'debit' => $want['amount'], 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 2, 'account_id' => $want['credit'],
            'description' => $description, 'debit' => 0, 'credit' => $want['amount']]);
        $entry->load('lines');
        $entry->post($userId);

        DB::table('fee_cash_parts')->where('id', $partId)->update(['journal_entry_id' => $entry->id]);
    }

    protected function reversePart(object $part, ?int $userId): void
    {
        $original = $part->journal_entry_id ? JournalEntry::with('lines')->find($part->journal_entry_id) : null;
        $reversalId = null;

        if ($original && !$original->is_reversed) {
            [$reversal] = $this->reverseEntry($original, 'fee_receipt_reversal', (int) $part->id, $userId);
            $reversalId = $reversal->id;
        }

        DB::table('fee_cash_parts')->where('id', $part->id)
            ->update(['status' => 'reversed', 'reversal_entry_id' => $reversalId, 'updated_at' => now()]);
    }

    protected function openDate(string $date): string
    {
        $year = FiscalYear::forDate($date);
        $period = $year ? FiscalYear::periodForDate($date) : null;

        return (($year && $year->is_closed) || ($period && $period->is_closed)) ? now()->toDateString() : $date;
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
