<?php

namespace App\Services;

use App\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

/**
 * Works out what was actually spent and received against each budget line.
 *
 * This is the only place that decides where real money lands on the sheet, so
 * the figure a report shows and the figure a reconciliation check sums are the
 * same figure by construction.
 *
 * Actuals are read from expenses / incomes / fees directly, not from journal
 * entries. The sheet therefore works whether or not the double-entry ledger has
 * been switched on.
 *
 * Two rules matter more than the rest:
 *   - a transaction is counted once, against exactly one line;
 *   - money that cannot be placed is reported as unallocated, never dropped.
 */
class BudgetActualsService
{
    /**
     * Actual amounts keyed by budget line id, for a date range.
     *
     * @return array{lines: array<int,float>, unallocated: array<string,float>}
     */
    public function forPeriod(?string $from = null, ?string $to = null): array
    {
        $lines = [];
        $unallocated = [];

        $this->addExpenses($lines, $unallocated, $from, $to);
        $this->addIncomes($lines, $unallocated, $from, $to);
        $this->addFees($lines, $unallocated, $from, $to);

        return ['lines' => $lines, 'unallocated' => $unallocated];
    }

    /** Expenditure, routed by its category's mapping. */
    protected function addExpenses(array &$lines, array &$unallocated, ?string $from, ?string $to): void
    {
        $rows = DB::table('expenses as e')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'e.category_id')
                    ->where('m.mapping_type', '=', 'expense_category');
            })
            // A line set on the row itself wins, so a single miscategorised
            // transaction can be corrected without changing the rule for its
            // whole category.
            ->selectRaw('COALESCE(e.budget_line_id, m.budget_line_id) as line_id, SUM(e.amount) as total')
            ->when($from, fn ($q) => $q->whereDate('e.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.date', '<=', $to))
            ->groupBy('line_id')
            ->get();

        foreach ($rows as $row) {
            if ($row->line_id === null) {
                $unallocated['expenses'] = ($unallocated['expenses'] ?? 0) + (float) $row->total;
                continue;
            }
            $lines[(int) $row->line_id] = ($lines[(int) $row->line_id] ?? 0) + (float) $row->total;
        }
    }

    /** Recorded income other than student fees. */
    protected function addIncomes(array &$lines, array &$unallocated, ?string $from, ?string $to): void
    {
        $rows = DB::table('incomes as i')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'i.category_id')
                    ->where('m.mapping_type', '=', 'income_category');
            })
            ->selectRaw('COALESCE(i.budget_line_id, m.budget_line_id) as line_id, SUM(i.amount) as total')
            ->when($from, fn ($q) => $q->whereDate('i.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('i.date', '<=', $to))
            ->groupBy('line_id')
            ->get();

        foreach ($rows as $row) {
            if ($row->line_id === null) {
                $unallocated['incomes'] = ($unallocated['incomes'] ?? 0) + (float) $row->total;
                continue;
            }
            $lines[(int) $row->line_id] = ($lines[(int) $row->line_id] ?? 0) + (float) $row->total;
        }
    }

    /**
     * Student fees.
     *
     * Tuition is split by school, which the fee category alone cannot tell us —
     * the route is fee → enrolment → programme → faculty → the line tagged with
     * that faculty. Anything that will not resolve falls back to the category's
     * own mapping, and if that is missing too it is reported as unallocated
     * rather than being quietly attributed to the wrong school.
     */
    protected function addFees(array &$lines, array &$unallocated, ?string $from, ?string $to): void
    {
        $facultyLines = BudgetLine::whereNotNull('faculty_id')
            ->pluck('id', 'faculty_id');

        $rows = DB::table('fees as f')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'f.category_id')
                    ->where('m.mapping_type', '=', 'fee_category');
            })
            ->leftJoin('fees_categories as fc', 'fc.id', '=', 'f.category_id')
            ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
            ->leftJoin('programs as p', 'p.id', '=', 'se.program_id')
            ->selectRaw('m.budget_line_id as mapped_line, fc.is_admission, fc.is_resit,
                         p.faculty_id, SUM(f.paid_amount) as total')
            ->where('f.paid_amount', '>', 0)
            ->when($from, fn ($q) => $q->whereDate('f.pay_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('f.pay_date', '<=', $to))
            ->groupBy('m.budget_line_id', 'fc.is_admission', 'fc.is_resit', 'p.faculty_id')
            ->get();

        foreach ($rows as $row) {
            $amount = (float) $row->total;
            if ($amount == 0.0) {
                continue;
            }

            $lineId = null;

            // Only tuition is split by school. Admission and resit fees are
            // institution-wide and keep their category's own line.
            $isTuition = !$row->is_admission && !$row->is_resit;

            if ($isTuition && $row->faculty_id && isset($facultyLines[$row->faculty_id])) {
                $lineId = (int) $facultyLines[$row->faculty_id];
            } elseif ($row->mapped_line) {
                $lineId = (int) $row->mapped_line;
            }

            if ($lineId === null) {
                $unallocated['fees'] = ($unallocated['fees'] ?? 0) + $amount;
                continue;
            }

            $lines[$lineId] = ($lines[$lineId] ?? 0) + $amount;
        }
    }

    /**
     * Roll child figures up into their headers, so a group shows the sum of the
     * lines beneath it without those lines being counted twice.
     *
     * @param  array<int,float> $lineTotals keyed by budget line id
     * @return array<int,float> the same map, with header ids filled in
     */
    public function withHeaderTotals(array $lineTotals): array
    {
        $withHeaders = $lineTotals;

        foreach (BudgetLine::where('is_header', true)->get() as $header) {
            $childIds = BudgetLine::where('parent_id', $header->id)->pluck('id');
            $sum = 0.0;
            foreach ($childIds as $childId) {
                $sum += $lineTotals[$childId] ?? 0;
            }
            $withHeaders[$header->id] = $sum;
        }

        return $withHeaders;
    }

    /**
     * Section totals. Headers are excluded so their children are not counted a
     * second time.
     */
    public function sectionTotal(array $lineTotals, string $section): float
    {
        $ids = BudgetLine::where('section', $section)->where('is_header', false)->pluck('id');

        $total = 0.0;
        foreach ($ids as $id) {
            $total += $lineTotals[$id] ?? 0;
        }

        return $total;
    }
}
