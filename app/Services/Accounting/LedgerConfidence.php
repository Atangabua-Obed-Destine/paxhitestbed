<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Services\DaybookService;
use Illuminate\Support\Facades\DB;

/**
 * Can these figures be taken into a meeting?
 *
 * Every report in this module is arithmetically correct and can still be
 * misleading, because correctness is about the money that reached the books and
 * says nothing about the money that did not. On this database, a quarter of all
 * income — 20,254,000 across four rows — sits on no budget line, so it is absent
 * from every analysed total while the daybook, which never drops money, counts
 * it as unallocated and says so to nobody.
 *
 * This collects the handful of facts that decide whether a number is safe to
 * present, so the accountant meets them before the meeting rather than a board
 * member meeting them during it.
 *
 * Read-only throughout. The queries for misfiled entries are the same ones
 * accounting:fiscal-year-doctor runs, kept here so a screen and a command can
 * never quietly disagree about whether the ledger is sound.
 */
class LedgerConfidence
{
    public const GOOD = 'good';
    public const WARNING = 'warning';
    public const CRITICAL = 'critical';

    public function __construct(protected DaybookService $daybook)
    {
    }

    /**
     * Every check, worst first.
     *
     * @return array<int, array{
     *     key: string, label: string, state: string, figure: string,
     *     detail: string, action: ?string
     * }>
     */
    public function checks(?Budget $budget = null, ?string $from = null, ?string $to = null): array
    {
        $checks = [
            $this->unallocated($from, $to),
            $this->unpostedPayroll(),
            $this->misfiledEntries(),
            $this->entriesWithoutPeriod(),
            $this->ledgerBalances(),
        ];

        if ($budget) {
            $checks[] = $this->budgetAlignment($budget);
        }

        // Worst first: a panel that opens with five ticks and buries the one
        // red line has told the reader nothing.
        $rank = [self::CRITICAL => 0, self::WARNING => 1, self::GOOD => 2];

        usort($checks, fn ($a, $b) => $rank[$a['state']] <=> $rank[$b['state']]);

        return $checks;
    }

    /** Is everything clean? Lets the panel collapse to one line. */
    public function allClear(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['state'] !== self::GOOD) {
                return false;
            }
        }

        return true;
    }

    /** How many checks are not good — for the heading. */
    public function concerns(array $checks): int
    {
        return count(array_filter($checks, fn ($c) => $c['state'] !== self::GOOD));
    }

    /* ===================================================================== */

    /**
     * Money the sheet cannot place on any line.
     *
     * The headline check, and the reason this class exists. The daybook emits
     * these rows with a null line and counts them; nothing until now showed
     * the total to anybody.
     */
    protected function unallocated(?string $from, ?string $to): array
    {
        $rows = $this->daybook->rows($from, $to);

        $amount = 0.0;
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row['line_id'])) {
                $amount += (float) $row['amount'];
                $count++;
            }
        }

        $total = array_sum(array_column($rows, 'amount'));
        $share = $total > 0 ? ($amount / $total) * 100 : 0.0;

        return [
            'key' => 'unallocated',
            'label' => __('Money on no budget line'),
            'state' => $amount > 0 ? ($share >= 5 ? self::CRITICAL : self::WARNING) : self::GOOD,
            'figure' => number_format($amount, 2),
            'detail' => $amount > 0
                ? sprintf(__('%d transaction(s), %s%% of everything that moved in this period. It is counted'
                    . ' in the book but appears on no line of the Income & Expenditure sheet.'),
                    $count, number_format($share, 1))
                : __('Every transaction in this period sits on a budget line.'),
            'action' => $amount > 0
                ? __('Give the categories behind these rows a budget line, then this figure returns to the sheet.')
                : null,
        ];
    }

    /**
     * Payroll runs that have not been posted.
     *
     * The daybook reads salaries from the ledger, so an unpaid run means the
     * book shows a school with no staff cost — correct, and badly misleading
     * if nobody says so.
     */
    protected function unpostedPayroll(): array
    {
        $unpaid = DB::table('payrolls')->where('status', '!=', 1)->count();
        $total = (float) DB::table('payrolls')->where('status', '!=', 1)->sum('net_salary');

        return [
            'key' => 'payroll',
            'label' => __('Payroll not yet posted'),
            'state' => $unpaid > 0 ? self::WARNING : self::GOOD,
            'figure' => (string) $unpaid,
            'detail' => $unpaid > 0
                ? sprintf(__('%d payroll run(s) worth %s have not been paid, so no salary appears anywhere'
                    . ' in this analysis.'), $unpaid, number_format($total, 2))
                : __('Every payroll run has been posted to the ledger.'),
            'action' => $unpaid > 0
                ? __('Say so when presenting, or pay and post the runs first — staff cost is missing until you do.')
                : null,
        ];
    }

    /**
     * Entries filed under a fiscal year that does not contain their own date.
     *
     * The same query accounting:fiscal-year-doctor runs. 912 of 924 entries
     * were in this state this morning.
     */
    protected function misfiledEntries(): array
    {
        $count = $this->misfiledQuery()->count();

        return [
            'key' => 'misfiled',
            'label' => __('Entries in the wrong exercice'),
            'state' => $count > 0 ? self::CRITICAL : self::GOOD,
            'figure' => (string) $count,
            'detail' => $count > 0
                ? __('These are dated outside the fiscal year they are filed under, so a report for'
                    . ' that year includes money from another one.')
                : __('Every entry is filed under the fiscal year its date falls in.'),
            'action' => $count > 0 ? __('Run: php artisan accounting:fiscal-year-doctor --repair') : null,
        ];
    }

    /** The query itself, so the doctor and this panel cannot drift apart. */
    public function misfiledQuery()
    {
        return DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->whereNotNull('fiscal_year_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('fiscal_years')
                    ->whereColumn('fiscal_years.id', 'journal_entries.fiscal_year_id')
                    ->whereColumn('fiscal_years.start_date', '<=', 'journal_entries.entry_date')
                    ->whereColumn('fiscal_years.end_date', '>=', 'journal_entries.entry_date');
            });
    }

    protected function entriesWithoutPeriod(): array
    {
        $count = DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->whereNull('accounting_period_id')
            ->count();

        return [
            'key' => 'no_period',
            'label' => __('Entries with no accounting period'),
            'state' => $count > 0 ? self::WARNING : self::GOOD,
            'figure' => (string) $count,
            'detail' => $count > 0
                ? __('Anything reported by month leaves these out.')
                : __('Every entry sits in a month of its own exercice.'),
            'action' => $count > 0
                ? __('Generate the monthly periods for the fiscal year, then run the doctor.')
                : null,
        ];
    }

    /**
     * The oldest check in bookkeeping.
     *
     * Compared as strings to the penny rather than as floats: two sums that
     * differ in the fifteenth decimal place are equal for this purpose, and
     * comparing floats with == would report a problem that does not exist.
     */
    protected function ledgerBalances(): array
    {
        $debits = (float) DB::table('journal_entries')->whereNull('deleted_at')->sum('total_debit');
        $credits = (float) DB::table('journal_entries')->whereNull('deleted_at')->sum('total_credit');

        $balanced = number_format($debits, 2, '.', '') === number_format($credits, 2, '.', '');

        return [
            'key' => 'balanced',
            'label' => __('Debits equal credits'),
            'state' => $balanced ? self::GOOD : self::CRITICAL,
            'figure' => number_format($debits, 2),
            'detail' => $balanced
                ? __('The ledger balances.')
                : sprintf(__('Debits %s against credits %s — a difference of %s.'),
                    number_format($debits, 2), number_format($credits, 2),
                    number_format(abs($debits - $credits), 2)),
            'action' => $balanced ? null : __('Do not present these figures until this is found.'),
        ];
    }

    /**
     * Does this budget cover the same days as an exercice?
     *
     * Straight from BudgetPeriod, so the warning here and the one on the budget
     * screen are the same sentence.
     */
    protected function budgetAlignment(Budget $budget): array
    {
        $warnings = BudgetPeriod::alignmentWarnings(
            optional($budget->start_date)->format('Y-m-d'),
            optional($budget->end_date)->format('Y-m-d')
        );

        return [
            'key' => 'alignment',
            'label' => __('Budget matches an exercice'),
            'state' => $warnings === [] ? self::GOOD : self::WARNING,
            'figure' => $warnings === [] ? __('aligned') : __('misaligned'),
            'detail' => $warnings === []
                ? __('This budget covers exactly one fiscal year, so it can be compared with the statutory accounts.')
                : implode(' ', $warnings),
            'action' => $warnings === []
                ? null
                : __('These figures cannot be compared line for line with an exercice\'s accounts.'),
        ];
    }
}
