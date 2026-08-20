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

        $sections = [];
        foreach (self::SECTION_CLASSES as $section => $meta) {
            $sheet = $this->actuals->sectionTotal($actuals['lines'], $section);
            $accounts = $this->ledgerAccounts($meta['class'], $meta['normal'], $from, $to);
            $ledger = array_sum(array_column($accounts, 'amount'));

            $difference = round($sheet - $ledger, 2);

            $sections[] = [
                'section' => $section,
                'label' => $meta['label'],
                'class' => $meta['class'],
                'sheet' => $sheet,
                'ledger' => $ledger,
                'difference' => $difference,
                'agrees' => abs($difference) < 0.01,
                'accounts' => $accounts,
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
            'unallocated' => $actuals['unallocated'],
            'agrees' => $agrees && $issues === [],
            'from' => $from,
            'to' => $to,
        ];
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
}
