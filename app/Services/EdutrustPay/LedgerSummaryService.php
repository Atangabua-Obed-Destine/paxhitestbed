<?php

namespace App\Services\EdutrustPay;

use App\Models\JournalEntry;
use Carbon\Carbon;
use EdutrustPay\Contract\Money;
use Illuminate\Support\Facades\DB;

/**
 * Summarises one calendar month of the general ledger.
 *
 * The existing reports — trial balance, balance sheet, income statement — all
 * live inside GeneralLedgerController and are shaped for a screen: a fiscal
 * period, a hierarchy of accounts, running balances. None of them produces what
 * a monthly consolidation needs, which is a flat set of totals per OHADA GROUP
 * for one calendar month. So this is a new capability rather than a duplicate of
 * an existing one, and nothing in the controller has been changed.
 *
 * THE CALENDAR MONTH IS THE UNIT, NOT THE ACCOUNTING PERIOD. Two reasons:
 * journal_entries.accounting_period_id is nullable, so a period-keyed query
 * silently drops entries; and the body consolidates across institutions whose
 * fiscal years differ — this one runs January-December while its schools run
 * July-June. Months are the only thing that can be added up across both.
 *
 * EVERY FIGURE IS RECOMPUTED FROM journal_entry_lines. chart_of_accounts
 * .current_balance exists and would be far cheaper to read, but post() and
 * unpost() mutate it in place, so it is a second source of truth that drifts.
 * A consolidated position built on it would disagree with this institution's own
 * printed statements with nothing to show why.
 */
class LedgerSummaryService
{
    public const CLASS_TREASURY = 5;

    public const CLASS_EXPENSE = 6;

    public const CLASS_REVENUE = 7;

    private string $start;

    private string $end;

    public function __construct(string $period)
    {
        $month = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();

        $this->start = $month->toDateString();
        $this->end = $month->copy()->endOfMonth()->toDateString();
    }

    /**
     * Income by OHADA group, netted in the credit direction.
     *
     * @return array<string, Money>
     */
    public function incomeByGroup(): array
    {
        return $this->groupTotals(self::CLASS_REVENUE, 'credit');
    }

    /**
     * Expenditure by OHADA group, netted in the debit direction.
     *
     * @return array<string, Money>
     */
    public function expenditureByGroup(): array
    {
        return $this->groupTotals(self::CLASS_EXPENSE, 'debit');
    }

    /**
     * @return array{opening: Money, movement: Money, closing: Money}
     */
    public function cash(): array
    {
        $opening = Money::fromNumeric($this->treasuryNet(null, Carbon::parse($this->start)->subDay()->toDateString()));
        $movement = Money::fromNumeric($this->treasuryNet($this->start, $this->end));

        return [
            'opening' => $opening,
            'movement' => $movement,
            // Derived, never queried separately, so opening + movement always
            // equals closing and the console's cash-discontinuity warning can
            // only fire on a genuine error rather than on rounding.
            'closing' => $opening->add($movement),
        ];
    }

    /**
     * Control totals, so the console can verify the ledger balances itself
     * rather than trusting the summary above it.
     *
     * @return array{total_debits: Money, total_credits: Money, journal_entry_count: int, unposted_count: int}
     */
    public function control(): array
    {
        $totals = $this->lineQuery(true)->selectRaw('SUM(l.debit) as dr, SUM(l.credit) as cr')->first();

        return [
            'total_debits' => Money::fromNumeric((string) ($totals->dr ?? 0)),
            'total_credits' => Money::fromNumeric((string) ($totals->cr ?? 0)),
            'journal_entry_count' => $this->entryCount(true),

            /*
             * Worth reporting honestly even though it is unflattering: unpost()
             * in this system adjusts account balances backwards instead of
             * posting a contra entry, so figures can leave the ledger without a
             * reversing trail. This count is the only external sign of it.
             */
            'unposted_count' => $this->entryCount(false),
        ];
    }

    /**
     * A hash over the month's posted journal lines.
     *
     * Lets the console notice that a period's underlying detail changed even
     * when its totals did not — which is what a reclassification looks like from
     * the outside. Ordered explicitly so the hash does not depend on how the
     * database happens to return rows.
     */
    public function ledgerHash(): string
    {
        $rows = $this->lineQuery(true)
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.line_number')->orderBy('l.id')
            ->select('e.entry_date', 'e.entry_number', 'a.account_code', 'l.debit', 'l.credit')
            ->get();

        $material = $rows->map(fn ($r) => implode('|', [
            $r->entry_date,
            $r->entry_number,
            $r->account_code,
            number_format((float) $r->debit, 2, '.', ''),
            number_format((float) $r->credit, 2, '.', ''),
        ]))->implode("\n");

        return 'sha256:'.hash('sha256', $material);
    }

    public function reversals(): int
    {
        return (int) JournalEntry::query()
            ->whereBetween('entry_date', [$this->start, $this->end])
            ->where(fn ($q) => $q->where('is_reversed', 1)->orWhere('reference_type', 'like', '%reversal%'))
            ->count();
    }

    /**
     * Entries added to this month AFTER the month had ended.
     *
     * NOT "entry_date earlier than created_at". That reading flags almost every
     * entry in a real ledger — every bursary enters last month's transactions
     * this month — and a control that fires constantly teaches everyone to
     * ignore it. The narrower question is how much of what is being reported for
     * this month was assembled after the month closed.
     *
     * Reversals are excluded because TransactionAutoMapService::reversalPlacement()
     * deliberately dates a reversal to the original entry while its period is
     * still open, so counting them would be counting correct behaviour.
     */
    public function lateEntries(): int
    {
        return (int) JournalEntry::query()
            ->whereBetween('entry_date', [$this->start, $this->end])
            ->where('created_at', '>', Carbon::parse($this->end)->endOfDay())
            ->where('is_reversed', 0)
            ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', 'not like', '%reversal%'))
            ->count();
    }

    public function auditEvents(): int
    {
        try {
            return (int) DB::table('audit_logs')
                ->whereBetween('created_at', [$this->start.' 00:00:00', $this->end.' 23:59:59'])
                ->where(fn ($q) => $q
                    ->where('auditable_type', 'like', '%JournalEntry%')
                    ->orWhere('auditable_type', 'like', '%ChartOfAccount%')
                    ->orWhere('auditable_type', 'like', '%AccountingPeriod%'))
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function startDate(): string
    {
        return $this->start;
    }

    public function endDate(): string
    {
        return $this->end;
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    /**
     * @return array<string, Money>
     */
    private function groupTotals(int $class, string $normal): array
    {
        $rows = $this->lineQuery(true)
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.class_number', $class)
            ->selectRaw('LEFT(a.account_code, 2) as grp, SUM(l.debit) as dr, SUM(l.credit) as cr')
            ->groupBy('grp')
            ->orderBy('grp')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            // Revenue is credit-natured and expenses debit-natured, so each nets
            // in its own direction: a refund against revenue reduces revenue
            // rather than appearing as an expense.
            $net = $normal === 'credit'
                ? bcsub((string) $row->cr, (string) $row->dr, 2)
                : bcsub((string) $row->dr, (string) $row->cr, 2);

            $money = Money::fromNumeric($net);

            if (! $money->isZero()) {
                $totals[(string) $row->grp] = $money;
            }
        }

        return $totals;
    }

    private function treasuryNet(?string $start, string $end): string
    {
        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->where('a.class_number', self::CLASS_TREASURY)
            ->where('e.entry_date', '<=', $end);

        if ($start !== null) {
            $query->where('e.entry_date', '>=', $start);
        }

        $row = $query->selectRaw('SUM(l.debit) as dr, SUM(l.credit) as cr')->first();

        return bcsub((string) ($row->dr ?? 0), (string) ($row->cr ?? 0), 2);
    }

    private function lineQuery(bool $posted): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', $posted ? 1 : 0)
            ->whereNull('e.deleted_at')
            ->whereBetween('e.entry_date', [$this->start, $this->end]);
    }

    private function entryCount(bool $posted): int
    {
        return (int) JournalEntry::query()
            ->where('is_posted', $posted ? 1 : 0)
            ->whereBetween('entry_date', [$this->start, $this->end])
            ->count();
    }
}
