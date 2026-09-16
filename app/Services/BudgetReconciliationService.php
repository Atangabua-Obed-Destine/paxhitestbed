<?php

namespace App\Services;

use App\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

/**
 * Does the budget sheet agree with the ledger?
 *
 * The two are computed by different routes from the same underlying money:
 *
 *   sheet   expense/income/fee -> category -> default_account_mappings.budget_line_id
 *   ledger  expense/income/fee -> category -> default_account_mappings.debit/credit_account_id
 *
 * They share the bridge table, so they agree by construction — but only for as
 * long as every category actually carries both halves of that mapping. Add an
 * expense category and forget its mapping and the sheet drifts from the accounts
 * silently, which is the failure this class exists to make loud.
 *
 * Nothing here recomputes either side: the sheet total comes from
 * BudgetActualsService (the one place that decides where money lands on the
 * sheet) and the ledger total from the posted journal lines.
 */
class BudgetReconciliationService
{
    /**
     * Which ledger class each section of the sheet should land in, and which way
     * round its balance runs.
     *
     * Capital is the interesting one: buying a vehicle is not an expense, so it
     * is capitalised to a class 2 asset rather than charged to class 6. The
     * sheet still shows it, because the Bursar budgets for it.
     */
    protected const SECTION_CLASSES = [
        'income' => ['class' => 7, 'normal' => 'credit', 'label' => 'Income'],
        'expenditure' => ['class' => 6, 'normal' => 'debit', 'label' => 'Expenditure'],
        'capital' => ['class' => 2, 'normal' => 'debit', 'label' => 'Capital'],
    ];

    public function __construct(protected BudgetActualsService $actuals)
    {
    }

    /**
     * Compare every section of the sheet with the ledger for a date range.
     *
     * @return array{
     *     sections: array<int, array{section:string,label:string,class:int,sheet:float,ledger:float,difference:float,agrees:bool,accounts:array}>,
     *     issues: array<int, array{type:string,detail:string}>,
     *     unallocated: array<string,float>,
     *     agrees: bool,
     *     from: ?string, to: ?string
     * }
     */
    public function reconcile(?string $from = null, ?string $to = null): array
    {
        $actuals = $this->actuals->forPeriod($from, $to);

        // A known, quantified difference, kept apart from real drift.
        //
        // When an overpaid fee's excess was applied to another fee as student
        // credit, the ledger posted it as cash received on that second fee too —
        // income, and cash, counted twice. The sheet counts the cash once, when
        // it arrived. So the sheet's income is lower than the ledger's by exactly
        // that credit. It is a pending ledger correction, reported by
        // `php artisan fees:credit-audit`; until it is made, it is named here
        // rather than turning the whole verdict red, and anything beyond it
        // still does.
        $creditPostedAsCash = $this->feeCreditPostedAsCash($from, $to);

        $sections = [];
        foreach (self::SECTION_CLASSES as $section => $meta) {
            $sheet = $this->actuals->sectionTotal($actuals['lines'], $section);
            $accounts = $this->ledgerAccounts($meta['class'], $meta['normal'], $from, $to);
            $ledger = array_sum(array_column($accounts, 'amount'));

            $difference = round($sheet - $ledger, 2);
            $explained = $section === 'income' ? round(-$creditPostedAsCash, 2) : 0.0;

            $sections[] = [
                'section' => $section,
                'label' => $meta['label'],
                'class' => $meta['class'],
                'sheet' => $sheet,
                'ledger' => $ledger,
                'difference' => $difference,
                'explained' => $explained,
                'unexplained' => round($difference - $explained, 2),
                'agrees' => abs($difference - $explained) < 0.01,
                'accounts' => $accounts,
            ];
        }

        $knownDifferences = [];

        if (abs($creditPostedAsCash) > 0.009) {
            $knownDifferences[] = [
                'section' => 'income',
                'amount' => $creditPostedAsCash,
                'detail' => sprintf(
                    'The ledger posted %s FCFA of student credit as cash received: overpayment moved from one fee to another was booked as income on both fees. The sheet counts that cash once. This needs a ledger correction; run php artisan fees:credit-audit for the detail.',
                    number_format($creditPostedAsCash)
                ),
            ];
        }

        $issues = array_merge(
            $this->unmappedCategories(),
            $this->unreachableLines(),
        );

        // Money the sheet could not place is reported by BudgetActualsService
        // rather than dropped; surface it here as an issue in its own right.
        foreach ($actuals['unallocated'] as $source => $amount) {
            if ($amount > 0) {
                $issues[] = [
                    'type' => 'unallocated',
                    'detail' => sprintf('%s: %s FCFA could not be placed on any budget line', $source, number_format($amount)),
                ];
            }
        }

        $agrees = true;
        foreach ($sections as $s) {
            $agrees = $agrees && $s['agrees'];
        }

        return [
            'sections' => $sections,
            'issues' => $issues,
            'known_differences' => $knownDifferences,
            'unallocated' => $actuals['unallocated'],
            'agrees' => $agrees && $issues === [],
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Fee credit the ledger posted as cash a second time, for fees paid in the
     * window.
     *
     * Read from what is actually posted, not from paid_amount: once the ledger
     * has been corrected (Fees → Credit audit), each posting carries the cash
     * received and this is zero. Worked out from paid_amount instead, it would
     * go on "explaining" a gap that no longer exists and turn the sheet red.
     */
    protected function feeCreditPostedAsCash(?string $from, ?string $to): float
    {
        $posted = DB::table('transaction_mappings as tm')
            ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
            ->where('tm.transaction_type', 'fee')
            ->where('tm.status', 'active')
            ->select('tm.transaction_id', 'je.total_debit');

        return round((float) DB::table('fees as f')
            ->joinSub($posted, 'p', 'p.transaction_id', '=', 'f.id')
            ->when($from, fn ($q) => $q->whereDate('f.pay_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('f.pay_date', '<=', $to))
            ->sum(DB::raw('p.total_debit - ' . \App\Models\Fee::cashReceivedSql('f'))), 2);
    }

    /**
     * Net movement per account in one ledger class, in its normal direction.
     *
     * @return array<int, array{code:string,name:string,amount:float}>
     */
    protected function ledgerAccounts(int $class, string $normal, ?string $from, ?string $to): array
    {
        $signed = $normal === 'credit'
            ? 'SUM(l.credit) - SUM(l.debit)'
            : 'SUM(l.debit) - SUM(l.credit)';

        $rows = DB::table('journal_entry_lines as l')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('a.class_number', $class)
            ->when($from, fn ($q) => $q->whereDate('j.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('j.entry_date', '<=', $to))
            ->groupBy('a.account_code', 'a.account_name')
            ->havingRaw($signed . ' <> 0')
            ->orderBy('a.account_code')
            ->selectRaw('a.account_code, a.account_name, ' . $signed . ' as amount')
            ->get();

        return $rows->map(fn ($r) => [
            'code' => $r->account_code,
            'name' => $r->account_name,
            'amount' => (float) $r->amount,
        ])->all();
    }

    /**
     * Categories that reach the ledger but not the sheet.
     *
     * Only one direction can break. `debit_account_id` and `credit_account_id`
     * are NOT NULL, so the database itself guarantees every category reaches the
     * chart of accounts; `budget_line_id` is nullable, so the sheet is the half
     * that can silently lose a category. That asymmetry is the whole risk.
     *
     * @return array<int, array{type:string,detail:string}>
     */
    protected function unmappedCategories(): array
    {
        return DB::table('default_account_mappings')
            ->whereNull('budget_line_id')
            ->get()
            ->map(fn ($row) => [
                'type' => 'unmapped_category',
                'detail' => sprintf(
                    '%s #%s posts to the ledger but reaches no budget line, so its money is missing from the sheet',
                    $row->mapping_type,
                    $row->category_id
                ),
            ])
            ->all();
    }

    /**
     * Budget lines that no category maps to.
     *
     * Not an error on its own — a line budgeted for but not yet spent against is
     * perfectly normal — so these are reported only where money has already been
     * posted with nowhere on the sheet to land. Lines with a faculty are excluded
     * because tuition reaches them through the enrolment, not a category.
     *
     * @return array<int, array{type:string,detail:string}>
     */
    protected function unreachableLines(): array
    {
        $reached = DB::table('default_account_mappings')
            ->whereNotNull('budget_line_id')
            ->pluck('budget_line_id')
            ->all();

        $orphans = BudgetLine::where('is_header', false)
            ->whereNull('faculty_id')
            ->whereNotIn('id', $reached ?: [0])
            ->whereIn('id', function ($q) {
                // Only lines that money has actually been pinned to by hand.
                $q->select('budget_line_id')->from('expenses')->whereNotNull('budget_line_id');
            })
            ->get();

        return $orphans->map(fn ($l) => [
            'type' => 'unreachable_line',
            'detail' => sprintf('budget line %s "%s" is used by transactions but no category maps to it', $l->code, $l->name),
        ])->all();
    }

    /**
     * The chart account(s) each budget line posts to, keyed by budget line id.
     *
     * Read from the existing bridge rather than a table of its own — there is
     * one mapping in this system and this is a view of it. A line reached by
     * several categories shows the distinct set.
     *
     * @return array<int, array<int, string>>  e.g. [12 => ['606 Carburants et lubrifiants']]
     */
    public function accountsByLine(): array
    {
        $rows = DB::table('default_account_mappings as m')
            ->whereNotNull('m.budget_line_id')
            ->leftJoin('chart_of_accounts as d', 'd.id', '=', 'm.debit_account_id')
            ->leftJoin('chart_of_accounts as c', 'c.id', '=', 'm.credit_account_id')
            ->selectRaw('m.budget_line_id, m.mapping_type, d.account_code as dc, d.account_name as dn, c.account_code as cc, c.account_name as cn')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            // Expenditure is what the debit side names; income what the credit
            // side names. Showing both would list the bank account on every line.
            $isIncome = $row->mapping_type !== 'expense_category';
            $code = $isIncome ? $row->cc : $row->dc;
            $name = $isIncome ? $row->cn : $row->dn;

            if (!$code) {
                continue;
            }

            $label = $code . ' ' . $name;
            $id = (int) $row->budget_line_id;
            if (!isset($map[$id]) || !in_array($label, $map[$id], true)) {
                $map[$id][] = $label;
            }
        }

        return $map;
    }

    /**
     * The categories that reach each budget line, keyed by budget line id.
     *
     * The screen already shows which ACCOUNT a line posts to, which answers
     * "where does this sit in the ledger" but not "what actually feeds this
     * line" — and the second is the question someone looking at an empty line
     * is asking. Read from the same bridge, so the two views cannot disagree.
     *
     * `seeded` distinguishes a mapping that shipped with the system from one
     * somebody here decided on. DefaultBudgetMappingSeeder runs unauthenticated
     * so leaves created_by null, while Mapping Settings and the budget line
     * screen both write Auth::id(). Editing a seeded mapping through Mapping
     * Settings therefore flips it to configured — which is the honest reading:
     * the flag records "still as shipped", not original authorship.
     *
     * @return array<int, array<int, array{type: string, name: string, seeded: bool}>>
     */
    public function categoriesByLine(): array
    {
        $tables = [
            'fee_category' => 'fees_categories',
            'income_category' => 'income_categories',
            'expense_category' => 'expense_categories',
        ];

        $map = [];

        foreach ($tables as $type => $table) {
            $rows = DB::table('default_account_mappings as m')
                ->where('m.mapping_type', $type)
                ->whereNotNull('m.budget_line_id')
                ->join($table . ' as c', 'c.id', '=', 'm.category_id')
                ->selectRaw('m.budget_line_id, c.id as category_id, c.title, m.created_by')
                ->get();

            foreach ($rows as $row) {
                $map[(int) $row->budget_line_id][] = [
                    'type' => $type,
                    'id' => (int) $row->category_id,
                    'name' => $row->title,
                    'seeded' => $row->created_by === null,
                ];
            }
        }

        return $map;
    }

    /**
     * Which budget line each category currently feeds, keyed by
     * "type:category_id".
     *
     * Pointing a category at a new line silently takes its money off whatever
     * line it fed before. Every category in this installation is already
     * mapped, so that is the ordinary case rather than an edge one, and the
     * screen has to be able to name the line it is about to empty.
     *
     * @return array<string, array{line_id: int, code: string, name: string}>
     */
    public function lineByCategory(): array
    {
        $rows = DB::table('default_account_mappings as m')
            ->whereNotNull('m.budget_line_id')
            ->whereNotNull('m.category_id')
            ->join('budget_lines as b', 'b.id', '=', 'm.budget_line_id')
            ->selectRaw('m.mapping_type, m.category_id, b.id as line_id, b.code, b.name')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->mapping_type . ':' . $row->category_id] = [
                'line_id' => (int) $row->line_id,
                'code' => $row->code,
                'name' => $row->name,
            ];
        }

        return $map;
    }
}
