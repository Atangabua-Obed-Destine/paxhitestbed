<?php

namespace App\Services\Accounting;

use App\Models\TransactionMapping;
use Illuminate\Support\Facades\DB;

/**
 * Does every transaction still line up with its posting?
 *
 * Three records describe the same money and are written at different moments:
 * the source row (an income, an expense, a fee), the transaction_mappings row
 * that says which accounts it posts to, and the journal entry itself. An edit
 * touches the first and is expected to carry the other two with it.
 *
 * When that chain breaks it breaks silently. Every screen goes on rendering,
 * every total goes on adding up, and the two sides of the reconciliation
 * quietly stop describing the same thing. Two faults found on this system in
 * one afternoon illustrate the shape of it: a date-only edit moved the money
 * on the sheet and left the journal entry in the old month and the old
 * exercice; and a re-posting that failed halfway reversed the original entry
 * and never wrote its replacement, leaving a transaction on the sheet with no
 * ledger entry at all.
 *
 * Both are now fixed, and both would have been invisible without something
 * like this. So this asks the questions an auditor would ask, in order, and
 * reports what it finds.
 *
 * Read-only throughout. Repairs live in the commands that own them —
 * accounting:fiscal-year-doctor, fees:credit-audit, ledger:sync-preview —
 * because each needs a decision this cannot make.
 */
class LedgerHealth
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const CRITICAL = 'critical';

    /** @var array<int, object>|null  memoised by orphanMappings() */
    protected ?array $orphanCache = null;

    /**
     * The transaction kinds that post to the ledger.
     *
     * `should_post` is what makes a missing entry a fault rather than a fact:
     * an unpaid payroll has no posting because nothing has been paid, and a
     * fee with nothing paid against it has nothing to post.
     */
    protected const SOURCES = [
        'income' => [
            'table' => 'incomes', 'amount' => 'amount', 'date' => 'date',
            'label' => 'Income', 'should_post' => null,
        ],
        'expense' => [
            'table' => 'expenses', 'amount' => 'amount', 'date' => 'date',
            'label' => 'Expense', 'should_post' => null,
        ],
        'fee' => [
            'table' => 'fees', 'amount' => 'paid_amount', 'date' => 'pay_date',
            'label' => 'Fee receipt', 'should_post' => 'paid_amount > 0',
        ],
        'payroll' => [
            'table' => 'payrolls', 'amount' => 'net_salary', 'date' => 'pay_date',
            'label' => 'Payroll', 'should_post' => 'status = 1',
        ],
    ];

    /**
     * Every check, worst first.
     *
     * @return array<int, array{
     *     key: string, group: string, label: string, state: string,
     *     count: int, value: float, detail: string, action: ?string,
     *     rows: array
     * }>
     */
    public function checks(): array
    {
        $checks = array_merge(
            $this->mappingChecks(),
            $this->entryChecks(),
            $this->agreementChecks(),
            $this->placementChecks(),
        );

        $rank = [self::CRITICAL => 0, self::WARNING => 1, self::OK => 2];

        usort($checks, fn ($a, $b) => [$rank[$a['state']], $a['group']] <=> [$rank[$b['state']], $b['group']]);

        return $checks;
    }

    public function worstState(array $checks): string
    {
        foreach ([self::CRITICAL, self::WARNING] as $state) {
            foreach ($checks as $check) {
                if ($check['state'] === $state) {
                    return $state;
                }
            }
        }

        return self::OK;
    }

    /**
     * Drop anything memoised, so the next checks() re-reads the database.
     *
     * Needed by a caller that repairs between two reads — otherwise the
     * second read reports the fault the first one just fixed.
     */
    public function forget(): void
    {
        $this->orphanCache = null;
    }

    public function concerns(array $checks): int
    {
        return count(array_filter($checks, fn ($c) => $c['state'] !== self::OK));
    }

    /* ===================================================================== */
    /*  A. Is every mapping still attached to something?                     */
    /* ===================================================================== */

    protected function mappingChecks(): array
    {
        $out = [];

        $orphanIds = $this->orphanMappingIds();

        /*
         * Orphans are excluded here because the orphan checks below already
         * own them, and the two readings are different: this one says a live
         * transaction is marked posted but is not in the ledger, which is
         * normal for an unpaid payroll. Reported against a deleted
         * transaction it only repeats the orphan row under a heading that
         * suggests something else is wrong.
         */
        $dangling = DB::table('transaction_mappings as m')
            ->where('m.status', 'active')
            ->whereNotIn('m.id', $orphanIds ?: [0])
            ->leftJoin('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')
            ->whereNull('j.id')
            ->select('m.id', 'm.transaction_type', 'm.transaction_id', 'm.amount', 'm.transaction_date')
            ->get();

        $out[] = $this->check('mapping_no_entry', 'Mappings', 'Mapping with no journal entry',
            $dangling->count(), (float) $dangling->sum('amount'),
            'The transaction is marked as posted but no entry exists for it, so the money is on the sheet and not in the ledger.',
            'Check whether the transaction was ever meant to post. An unpaid payroll has no entry because nothing has been paid yet.',
            $dangling->all(), self::WARNING);

        $atReversed = DB::table('transaction_mappings as m')
            ->where('m.status', 'active')
            ->join('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')
            ->where('j.is_reversed', 1)
            ->select('m.id', 'm.transaction_type', 'm.transaction_id', 'm.amount')
            ->get();

        $out[] = $this->check('mapping_at_reversed', 'Mappings', 'Active mapping pointing at a reversed entry',
            $atReversed->count(), (float) $atReversed->sum('amount'),
            'The mapping names an entry that has been reversed, so it points at a posting that no longer counts.',
            'Re-save the transaction to re-post it.',
            $atReversed->all(), self::CRITICAL);

        $dupes = DB::table('transaction_mappings')
            ->where('status', 'active')
            ->select('transaction_type', 'transaction_id', DB::raw('count(*) as n'))
            ->groupBy('transaction_type', 'transaction_id')
            ->having('n', '>', 1)
            ->get();

        /*
         * Kept although the database already forbids it.
         *
         * unique_transaction_mapping on (transaction_type, transaction_id)
         * makes a second mapping row impossible, so this cannot fire today —
         * which is the best reason to leave it in. If that index is ever
         * dropped or widened in a migration, this is what notices, and the
         * fault it would otherwise hide is money counted twice.
         */
        $out[] = $this->check('mapping_duplicate', 'Mappings', 'More than one active mapping for a transaction',
            $dupes->count(), 0.0,
            'One transaction claims two postings, so its money can be counted twice.',
            'Deactivate the older mapping, and check why the unique index did not prevent this.',
            $dupes->all(), self::CRITICAL);

        $orphans = $this->orphanMappings();
        /*
             * Whether the mapping actually carries a posting decides how bad
             * this is, so it is selected rather than assumed.
             *
             * An orphan that points at a live entry means the ledger holds
             * money for a transaction that no longer exists: the reports are
             * wrong until it is reversed. An orphan with no entry — the usual
             * leftover from a transaction created, mapped, and deleted before
             * it ever posted — is stale bookkeeping and nothing more. Marking
             * both critical taught the reader to discount the colour, which
         * costs more than the untidy row does.
         */
        $posted = array_values(array_filter($orphans, fn ($o) => (int) $o->posted === 1));
        $unposted = array_values(array_filter($orphans, fn ($o) => (int) $o->posted !== 1));

        $out[] = $this->check('mapping_orphan', 'Mappings',
            'Mapping whose transaction has been deleted, with money still in the ledger',
            count($posted), (float) array_sum(array_column($posted, 'amount')),
            'The transaction is gone but its entry is still live, so the ledger counts money nothing stands behind.',
            'Reverse the entry, then deactivate the mapping.',
            $posted, self::CRITICAL);

        $out[] = $this->check('mapping_orphan_unposted', 'Mappings',
            'Mapping whose transaction has been deleted, never posted',
            count($unposted), (float) array_sum(array_column($unposted, 'amount')),
            'The transaction is gone and nothing was ever posted for it, so no total is wrong — the mapping row is simply stale.',
            'Deactivate the mapping. Nothing in the ledger needs changing.',
            $unposted, self::WARNING);

        return $out;
    }

    /**
     * Active mappings whose transaction row no longer exists.
     *
     * Memoised because two checks and the exclusion above all need the same
     * answer, and it is one query per source table.
     *
     * @return array<int, object>  each carrying a `posted` flag: 1 when a live,
     *                             unreversed entry still stands behind it
     */
    protected function orphanMappings(): array
    {
        if ($this->orphanCache !== null) {
            return $this->orphanCache;
        }

        $orphans = [];

        foreach (self::SOURCES as $type => $meta) {
            $gone = DB::table('transaction_mappings as m')
                ->where('m.transaction_type', $type)
                ->where('m.status', 'active')
                ->whereNotIn('m.transaction_id', function ($q) use ($meta) {
                    $q->select('id')->from($meta['table']);
                })
                ->leftJoin('journal_entries as j', function ($join) {
                    $join->on('j.id', '=', 'm.journal_entry_id')
                        ->whereNull('j.deleted_at')
                        ->where('j.is_reversed', 0);
                })
                ->select('m.id', 'm.transaction_type', 'm.transaction_id', 'm.amount',
                    DB::raw('CASE WHEN j.id IS NULL THEN 0 ELSE 1 END as posted'))
                ->get();

            $orphans = array_merge($orphans, $gone->all());
        }

        return $this->orphanCache = $orphans;
    }

    /** @return array<int, int> */
    protected function orphanMappingIds(): array
    {
        return array_column($this->orphanMappings(), 'id');
    }

    /**
     * Retire the mappings whose transaction is gone and which never posted.
     *
     * The only repair in this class, and it is here because it is the only one
     * that needs no decision: with no live entry behind it there is nothing in
     * the ledger to reverse, no total to restate, and no reading of the row
     * other than "stale". Every other finding names a command that owns it,
     * because every other finding turns on a judgement this cannot make.
     *
     * Orphans that DO carry a live entry are deliberately left alone. Clearing
     * those would strand the entry — money in the ledger with nothing left
     * pointing at it, which is worse than the fault it replaced.
     *
     * The status is 'reversed' rather than a truer word like 'void' because
     * that is the one token this table already uses for "no longer claims a
     * posting", and every reader filters on `status = active`. A second token
     * meaning the same thing is how a later negative filter comes to treat a
     * retired row as live. What actually happened is in the audit trail.
     *
     * Who retired the row is not written to `mapped_by` — that field records
     * who created the mapping, and overwriting it would destroy the only
     * record of where the row came from in order to store something the audit
     * trail already holds.
     *
     * @return array<int, object>  the rows retired, as they were before
     */
    public function retireUnpostedOrphans(): array
    {
        $retired = array_values(array_filter($this->orphanMappings(),
            fn ($o) => (int) $o->posted !== 1));

        if ($retired === []) {
            return [];
        }

        DB::transaction(function () use ($retired) {
            // Through the model, so Auditable records who retired what.
            TransactionMapping::whereIn('id', array_column($retired, 'id'))
                ->where('status', 'active')
                ->get()
                ->each(function ($mapping) {
                    $mapping->status = 'reversed';
                    $mapping->save();
                });
        });

        $this->orphanCache = null;

        return $retired;
    }

    /* ===================================================================== */
    /*  B. Is each entry internally sound?                                   */
    /* ===================================================================== */

    protected function entryChecks(): array
    {
        $out = [];

        $noLines = DB::table('journal_entries as j')
            ->whereNull('j.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('journal_entry_lines as l')
                  ->whereColumn('l.journal_entry_id', 'j.id');
            })
            ->select('j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit')
            ->get();

        $out[] = $this->check('entry_no_lines', 'Entries', 'Entry with no lines',
            $noLines->count(), (float) $noLines->sum('total_debit'),
            'A header with nothing under it. It carries a total that no account receives.',
            'The entry has to be rebuilt or removed.',
            $noLines->all(), self::CRITICAL);

        $unbalanced = DB::table('journal_entries as j')
            ->leftJoin('journal_entry_lines as l', 'l.journal_entry_id', '=', 'j.id')
            ->whereNull('j.deleted_at')
            ->groupBy('j.id', 'j.entry_number', 'j.entry_date')
            ->havingRaw('ABS(COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0)) > 0.009')
            ->select('j.id', 'j.entry_number', 'j.entry_date',
                DB::raw('COALESCE(SUM(l.debit),0) as debit'), DB::raw('COALESCE(SUM(l.credit),0) as credit'))
            ->get();

        $out[] = $this->check('entry_unbalanced', 'Entries', 'Entry whose lines do not balance',
            $unbalanced->count(), 0.0,
            'Debits and credits differ within one entry. Double entry stops being double entry.',
            'Open the entry and correct the lines.',
            $unbalanced->all(), self::CRITICAL);

        $headerDrift = DB::table('journal_entries as j')
            ->leftJoin('journal_entry_lines as l', 'l.journal_entry_id', '=', 'j.id')
            ->whereNull('j.deleted_at')
            ->groupBy('j.id', 'j.entry_number', 'j.total_debit')
            ->havingRaw('ABS(COALESCE(SUM(l.debit),0) - j.total_debit) > 0.009')
            ->select('j.id', 'j.entry_number', 'j.total_debit',
                DB::raw('COALESCE(SUM(l.debit),0) as line_total'))
            ->get();

        $out[] = $this->check('entry_header_drift', 'Entries', 'Entry total disagreeing with its own lines',
            $headerDrift->count(), 0.0,
            'Reports read the header; the ledger reads the lines. They are showing different figures.',
            'Recompute the entry total from its lines.',
            $headerDrift->all(), self::CRITICAL);

        $danglingReversal = DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->whereNotNull('reversed_entry_id')
            ->whereNotIn('reversed_entry_id', function ($q) {
                $q->select('id')->from('journal_entries');
            })
            ->select('id', 'entry_number', 'reversed_entry_id')
            ->get();

        $out[] = $this->check('reversal_orphan', 'Entries', 'Reversal naming an entry that is gone',
            $danglingReversal->count(), 0.0,
            'A correction that cannot be traced to what it corrected.',
            null, $danglingReversal->all(), self::WARNING);

        $reversedNoReversal = DB::table('journal_entries as j')
            ->where('j.is_reversed', 1)
            ->whereNull('j.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('journal_entries as r')
                  ->whereColumn('r.reversed_entry_id', 'j.id');
            })
            ->select('j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit')
            ->get();

        $out[] = $this->check('reversed_without_reversal', 'Entries', 'Marked reversed with no reversing entry',
            $reversedNoReversal->count(), (float) $reversedNoReversal->sum('total_debit'),
            'The entry was taken out of the accounts without a counter-entry, so the books lost the amount rather than cancelling it.',
            'The reversing entry has to be written.',
            $reversedNoReversal->all(), self::CRITICAL);

        // Reversal reference types (fee_reversal, expense_reversal) legitimately
        // carry several entries against one source id — each reverses a
        // different original — so they are excluded from this one.
        $multiLive = DB::table('journal_entries')
            ->whereNotNull('reference_type')
            ->where('reference_type', 'not like', '%\_reversal')
            ->where('is_reversed', 0)
            ->whereNull('deleted_at')
            ->select('reference_type', 'reference_id', DB::raw('count(*) as n'),
                DB::raw('SUM(total_debit) as total'))
            ->groupBy('reference_type', 'reference_id')
            ->having('n', '>', 1)
            ->get();

        $out[] = $this->check('entry_duplicate', 'Entries', 'Transaction with more than one live entry',
            $multiLive->count(), (float) $multiLive->sum('total'),
            'The same money is posted twice, so every total that reads the ledger is overstated by it.',
            'Reverse the duplicate.',
            $multiLive->all(), self::CRITICAL);

        return $out;
    }

    /* ===================================================================== */
    /*  C. Does the ledger still say what the transaction says?              */
    /* ===================================================================== */

    protected function agreementChecks(): array
    {
        $out = [];
        $missing = [];
        $dateDrift = [];
        $amountDrift = [];

        foreach (self::SOURCES as $type => $meta) {
            $posted = DB::table('journal_entries')
                ->where('reference_type', $type)
                ->where('is_reversed', 0)
                ->whereNull('deleted_at')
                ->pluck('reference_id')
                ->all();

            $unposted = DB::table($meta['table'])
                ->whereNotIn('id', $posted ?: [0])
                ->when($meta['should_post'], fn ($q) => $q->whereRaw($meta['should_post']))
                ->select('id', DB::raw("{$meta['amount']} as amount"), DB::raw("{$meta['date']} as date"),
                    DB::raw("'{$type}' as kind"))
                ->get();

            $missing = array_merge($missing, $unposted->all());

            $drift = DB::table('journal_entries as j')
                ->join($meta['table'] . ' as s', 's.id', '=', 'j.reference_id')
                ->where('j.reference_type', $type)
                ->where('j.is_reversed', 0)
                ->whereNull('j.deleted_at')
                ->whereRaw("DATE(j.entry_date) <> DATE(s.{$meta['date']})")
                ->select('j.id', 'j.reference_id', 'j.entry_date',
                    DB::raw("s.{$meta['date']} as source_date"), DB::raw("'{$type}' as kind"))
                ->get();

            $dateDrift = array_merge($dateDrift, $drift->all());

            $amounts = DB::table('journal_entries as j')
                ->join($meta['table'] . ' as s', 's.id', '=', 'j.reference_id')
                ->where('j.reference_type', $type)
                ->where('j.is_reversed', 0)
                ->whereNull('j.deleted_at')
                ->whereRaw("ABS(j.total_debit - s.{$meta['amount']}) > 0.009")
                ->select('j.id', 'j.reference_id', 'j.total_debit',
                    DB::raw("s.{$meta['amount']} as source_amount"), DB::raw("'{$type}' as kind"))
                ->get();

            $amountDrift = array_merge($amountDrift, $amounts->all());
        }

        $out[] = $this->check('source_unposted', 'Agreement', 'Transaction with no entry in the ledger',
            count($missing), (float) array_sum(array_column($missing, 'amount')),
            'These are on the Income & Expenditure sheet and absent from the ledger, so the two cannot reconcile.',
            'Usually a category with no account mapping. Map it, then re-post.',
            $missing, self::CRITICAL);

        $out[] = $this->check('date_drift', 'Agreement', 'Entry dated differently from its transaction',
            count($dateDrift), 0.0,
            'The sheet counts it in one month and the ledger in another — and if they fall either side of 31 December, in different exercices.',
            'Run php artisan accounting:repost. Re-saving the transaction will not do it: nothing has changed on the transaction, so nothing fires.',
            $dateDrift, self::CRITICAL);

        $out[] = $this->check('amount_drift', 'Agreement', 'Entry posted for a different amount',
            count($amountDrift),
            (float) array_sum(array_map(fn ($r) => abs(($r->source_amount ?? 0) - ($r->total_debit ?? 0)), $amountDrift)),
            'The ledger and the transaction disagree about how much money moved.',
            'For fees this is usually credit applied from another fee rather than cash: run php artisan fees:credit-audit.',
            $amountDrift, self::WARNING);

        return $out;
    }

    /* ===================================================================== */
    /*  D. Is each entry filed where its date says it belongs?               */
    /* ===================================================================== */

    protected function placementChecks(): array
    {
        $out = [];

        $wrongYear = DB::table('journal_entries as j')
            ->whereNull('j.deleted_at')
            ->whereNotNull('j.fiscal_year_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('fiscal_years as f')
                  ->whereColumn('f.id', 'j.fiscal_year_id')
                  ->whereColumn('f.start_date', '<=', 'j.entry_date')
                  ->whereColumn('f.end_date', '>=', 'j.entry_date');
            })
            ->select('j.id', 'j.entry_number', 'j.entry_date', 'j.fiscal_year_id', 'j.total_debit')
            ->get();

        $out[] = $this->check('wrong_exercice', 'Placement', 'Entry filed outside its own exercice',
            $wrongYear->count(), (float) $wrongYear->sum('total_debit'),
            'A report for that year includes money from another one.',
            'Run php artisan accounting:fiscal-year-doctor --repair',
            $wrongYear->all(), self::CRITICAL);

        $wrongPeriod = DB::table('journal_entries as j')
            ->whereNull('j.deleted_at')
            ->whereNotNull('j.accounting_period_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('accounting_periods as p')
                  ->whereColumn('p.id', 'j.accounting_period_id')
                  ->whereColumn('p.start_date', '<=', 'j.entry_date')
                  ->whereColumn('p.end_date', '>=', 'j.entry_date');
            })
            ->select('j.id', 'j.entry_number', 'j.entry_date', 'j.accounting_period_id')
            ->get();

        $out[] = $this->check('wrong_period', 'Placement', 'Entry filed outside its own month',
            $wrongPeriod->count(), 0.0,
            'Anything reported by month puts it in the wrong one.',
            'Run php artisan accounting:fiscal-year-doctor --repair',
            $wrongPeriod->all(), self::CRITICAL);

        $noYear = DB::table('journal_entries')
            ->whereNull('deleted_at')->whereNull('fiscal_year_id')
            ->select('id', 'entry_number', 'entry_date', 'total_debit')->get();

        $out[] = $this->check('no_exercice', 'Placement', 'Entry attached to no exercice',
            $noYear->count(), (float) $noYear->sum('total_debit'),
            'No fiscal year covers its date, so every report filtered by year leaves it out.',
            'Create the fiscal year that covers these dates, then run the fiscal-year doctor.',
            $noYear->all(), self::WARNING);

        $noPeriod = DB::table('journal_entries')
            ->whereNull('deleted_at')->whereNull('accounting_period_id')
            ->select('id', 'entry_number', 'entry_date')->get();

        $out[] = $this->check('no_period', 'Placement', 'Entry attached to no month',
            $noPeriod->count(), 0.0,
            'Anything reported by month leaves it out.',
            'Generate the monthly periods for its fiscal year, then run the fiscal-year doctor.',
            $noPeriod->all(), self::WARNING);

        return $out;
    }

    /* ===================================================================== */

    /**
     * A check is OK when its count is zero; otherwise it carries the severity
     * it was declared with. Severity is a property of the FAULT, not of how
     * many there are — one entry posted twice is as wrong as ten.
     */
    protected function check(string $key, string $group, string $label, int $count, float $value,
        string $detail, ?string $action, array $rows, string $severity): array
    {
        return [
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'state' => $count === 0 ? self::OK : $severity,
            'count' => $count,
            'value' => $value,
            'detail' => $count === 0 ? 'None.' : $detail,
            'action' => $count === 0 ? null : $action,
            'rows' => $rows,
        ];
    }
}
