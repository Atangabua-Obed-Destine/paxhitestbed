<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

/**
 * The cash analysis book, itemised.
 *
 * The Bursar's daybook is a columnar cash book: every movement on one row —
 * date, reference, description, money in, money out — with the amount repeated
 * under the analysis column it belongs to. The Income & Expenditure sheet is
 * that same money summed down those columns.
 *
 * So this service itemises, and BudgetActualsService summarises the same
 * sources. The two must agree line for line; scripts/daybook_test.php asserts
 * it, which is what makes "the sheet ties to the book" a property of the code
 * rather than a hope.
 *
 * Money that cannot be placed on a line is emitted with a null line and
 * counted, never dropped — an unanalysed row is a question for the Bursar, not
 * something to hide.
 */
class DaybookService
{
    /** Where a row's money sits in the book. */
    public const IN = 'in';
    public const OUT = 'out';

    /**
     * Every cash movement in a period, in date order.
     *
     * @return array<int, array{
     *     date: string, ref: string, description: string, direction: string,
     *     amount: float, line_id: ?int, source: string, source_id: int
     * }>
     */
    public function rows(?string $from = null, ?string $to = null): array
    {
        $rows = array_merge(
            $this->feeRows($from, $to),
            $this->incomeRows($from, $to),
            $this->expenseRows($from, $to),
            $this->payrollRows($from, $to)
        );

        usort($rows, function ($a, $b) {
            return [$a['date'], $a['source'], $a['source_id']]
                <=> [$b['date'], $b['source'], $b['source_id']];
        });

        return $rows;
    }

    /**
     * The same money, summed by budget line.
     *
     * This exists to be compared against BudgetActualsService::forPeriod(). If
     * the two ever disagree, one of them is lying about the institution's
     * money, and the test says which line.
     *
     * @return array{lines: array<int,float>, unallocated: float}
     */
    public function totalsByLine(?string $from = null, ?string $to = null): array
    {
        $lines = [];
        $unallocated = 0.0;

        foreach ($this->rows($from, $to) as $row) {
            // Almost every row adds to its line: a payment is spending on an
            // expenditure line, a receipt is income on an income line. A
            // reversal is the exception — it belongs in the book on the day it
            // happened, but it takes money back off the line rather than
            // putting more on. Builders that produce such a row say so with
            // signed_amount; everything else contributes its amount as before.
            $contribution = $row['signed_amount'] ?? $row['amount'];

            if ($row['line_id'] === null) {
                $unallocated += $contribution;
                continue;
            }

            $lines[$row['line_id']] = ($lines[$row['line_id']] ?? 0) + $contribution;
        }

        return ['lines' => $lines, 'unallocated' => $unallocated];
    }


    /* ===================================================================
     |  The Monthly Summary
     |===================================================================*/

    /**
     * One month of the book, summarised the way the Bursar's workbook does it:
     * Budget · Monthly · BB Forward · Cumulative · Balance.
     *
     * BB Forward is the cumulative to the END of the previous period within the
     * same budget, so the first month of a budget opens at zero rather than
     * inheriting last year's total. Balance is what is left of the budget, which
     * for expenditure is headroom and for income is the shortfall still to earn.
     *
     * @return array{
     *   period: \App\Models\AccountingPeriod, lines: array<int, array>,
     *   totals: array<string, array<string, float>>, profit_centres: array
     * }
     */
    public function monthlySummary(Budget $budget, AccountingPeriod $period): array
    {
        $budgetStart = optional($budget->start_date)->format('Y-m-d');
        $periodStart = $period->start_date instanceof \DateTimeInterface
            ? $period->start_date->format('Y-m-d')
            : substr((string) $period->start_date, 0, 10);
        $periodEnd = $period->end_date instanceof \DateTimeInterface
            ? $period->end_date->format('Y-m-d')
            : substr((string) $period->end_date, 0, 10);

        $monthly = $this->totalsByLine($periodStart, $periodEnd)['lines'];

        // Everything since the budget opened, up to the day before this period.
        $priorEnd = date('Y-m-d', strtotime($periodStart . ' -1 day'));
        $brought = ($budgetStart && $priorEnd >= $budgetStart)
            ? $this->totalsByLine($budgetStart, $priorEnd)['lines']
            : [];

        $budgeted = DB::table('budget_allocations')
            ->where('budget_id', $budget->id)
            ->whereNotNull('budget_line_id')
            ->pluck('allocated_amount', 'budget_line_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // Headings are part of the sheet, not decoration: the Income &
        // Expenditure sheet prints them with the sum of the lines filed under
        // them, and a summary that dropped them made the same money look like a
        // flat list of seventy rows.
        $sheetLines = BudgetLine::sheet();

        $monthly = $this->rollUpToHeadings($sheetLines, $monthly);
        $brought = $this->rollUpToHeadings($sheetLines, $brought);
        $budgeted = $this->rollUpToHeadings($sheetLines, $budgeted);

        $lines = [];
        $totals = [
            'income' => ['budget' => 0.0, 'monthly' => 0.0, 'brought' => 0.0, 'cumulative' => 0.0],
            'expenditure' => ['budget' => 0.0, 'monthly' => 0.0, 'brought' => 0.0, 'cumulative' => 0.0],
            'capital' => ['budget' => 0.0, 'monthly' => 0.0, 'brought' => 0.0, 'cumulative' => 0.0],
        ];

        foreach ($sheetLines as $line) {
            $m = (float) ($monthly[$line->id] ?? 0);
            $b = (float) ($brought[$line->id] ?? 0);
            $budgetFigure = (float) ($budgeted[$line->id] ?? 0);
            $cumulative = $m + $b;

            $lines[$line->id] = [
                'line' => $line,
                'is_header' => (bool) $line->is_header,
                'budget' => $budgetFigure,
                'monthly' => $m,
                'brought' => $b,
                'cumulative' => $cumulative,
                'balance' => $budgetFigure - $cumulative,
            ];

            // A heading already contains its children, so adding it to the
            // section total would count that money twice.
            if (!$line->is_header && isset($totals[$line->section])) {
                $totals[$line->section]['budget'] += $budgetFigure;
                $totals[$line->section]['monthly'] += $m;
                $totals[$line->section]['brought'] += $b;
                $totals[$line->section]['cumulative'] += $cumulative;
            }
        }

        return [
            'period' => $period,
            'lines' => $lines,
            'totals' => $totals,
            // Where to rule a subtotal, decided by the same helper the sheet
            // uses so the two cannot disagree about where a group ends.
            'group_ends' => BudgetLine::groupEnds($sheetLines),
            'profit_centres' => $this->profitCentres($lines),
        ];
    }

    /**
     * Give every heading the sum of the lines filed under it.
     *
     * Computed from the lines already in hand rather than by re-querying, and
     * kept local so this service never has to call back into the one that
     * summarises it.
     *
     * @param  \Illuminate\Support\Collection<int, BudgetLine> $sheetLines
     * @param  array<int, float> $figures
     * @return array<int, float>
     */
    protected function rollUpToHeadings($sheetLines, array $figures): array
    {
        foreach ($sheetLines as $line) {
            if (!$line->is_header) {
                continue;
            }

            $sum = 0.0;
            foreach ($sheetLines as $child) {
                if ($child->parent_id === $line->id) {
                    $sum += (float) ($figures[$child->id] ?? 0);
                }
            }

            $figures[$line->id] = $sum;
        }

        return $figures;
    }

    /**
     * Trading activities, paired.
     *
     * A profit centre is not a third section of the sheet — its lines already
     * belong to income or expenditure and are already counted there. This block
     * only re-presents them side by side so the margin per activity is visible,
     * which is the one question the annual sheet cannot answer. Nothing here is
     * added to any total.
     *
     * @param  array<int, array> $summaryLines rows from monthlySummary()
     * @return array<string, array{income: float, expenditure: float, margin: float, lines: array}>
     */
    public function profitCentres(array $summaryLines): array
    {
        $centres = [];

        foreach ($summaryLines as $row) {
            // A heading carries the sum of its children; counting it as an
            // activity too would double whatever those children earned.
            if (!empty($row['is_header'])) {
                continue;
            }

            $name = trim((string) $row['line']->profit_centre);
            if ($name === '') {
                continue;
            }

            if (!isset($centres[$name])) {
                $centres[$name] = ['income' => 0.0, 'expenditure' => 0.0, 'margin' => 0.0, 'lines' => []];
            }

            $side = $row['line']->section === BudgetLine::SECTION_INCOME ? 'income' : 'expenditure';
            $centres[$name][$side] += $row['cumulative'];
            $centres[$name]['lines'][] = $row;
        }

        foreach ($centres as $name => $centre) {
            $centres[$name]['margin'] = $centre['income'] - $centre['expenditure'];
        }

        ksort($centres);

        return $centres;
    }

    /**
     * The accounting periods a budget spans, in order.
     *
     * Driven off the budget rather than the fiscal year: a July-to-June book is
     * ordinary in a school, and the ledger's fiscal years are calendar.
     *
     * @return \Illuminate\Support\Collection<int, AccountingPeriod>
     */
    public function periodsFor(Budget $budget)
    {
        return AccountingPeriod::query()
            ->when($budget->start_date, fn ($q) => $q->whereDate('end_date', '>=', $budget->start_date))
            ->when($budget->end_date, fn ($q) => $q->whereDate('start_date', '<=', $budget->end_date))
            ->orderBy('start_date')
            ->get();
    }


    /* ===================================================================
     |  Analysis
     |===================================================================*/

    /**
     * The whole budget year read as a shape rather than a list.
     *
     * A month of the book answers "what happened"; these answer the questions a
     * Bursar actually has to act on — is collection seasonal, are we spending
     * faster than the year is passing, and where is the money going.
     *
     * Every row is fetched once and bucketed by month rather than re-querying
     * per period: twelve calls to rows() would run the same four joins twelve
     * times over.
     *
     * @return array{
     *   months: array<int, array>, pacing: array, top_expenditure: array,
     *   budget: array<string, float>, has_data: bool
     * }
     */
    public function analysis(Budget $budget): array
    {
        $periods = $this->periodsFor($budget);

        $from = optional($budget->start_date)->format('Y-m-d');
        $to = optional($budget->end_date)->format('Y-m-d');

        $rows = $this->rows($from, $to);

        $sections = BudgetLine::whereIn('id', array_filter(array_column($rows, 'line_id')))
            ->pluck('section', 'id');

        $months = [];
        foreach ($periods as $period) {
            $start = substr((string) $period->start_date, 0, 10);
            $end = substr((string) $period->end_date, 0, 10);

            $months[] = [
                'period' => $period,
                'label' => $this->shortMonth($period->name),
                'start' => $start,
                'end' => $end,
                'in' => 0.0,
                'out' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            foreach ($months as $i => $month) {
                if ($row['date'] >= $month['start'] && $row['date'] <= $month['end']) {
                    $months[$i][$row['direction'] === self::IN ? 'in' : 'out'] += $row['amount'];
                    break;
                }
            }
        }

        // Running totals, so the year can be read as a curve as well as bars.
        $cumIn = 0.0;
        $cumOut = 0.0;
        foreach ($months as $i => $month) {
            $cumIn += $month['in'];
            $cumOut += $month['out'];
            $months[$i]['net'] = $month['in'] - $month['out'];
            $months[$i]['cum_in'] = $cumIn;
            $months[$i]['cum_out'] = $cumOut;
        }

        $budgeted = DB::table('budget_allocations')
            ->where('budget_id', $budget->id)
            ->whereNotNull('budget_line_id')
            ->join('budget_lines as bl', 'bl.id', '=', 'budget_allocations.budget_line_id')
            ->selectRaw('bl.section, SUM(budget_allocations.allocated_amount) as total')
            ->groupBy('bl.section')
            ->pluck('total', 'section')
            ->map(fn ($v) => (float) $v)
            ->all();

        $budgetOut = (float) ($budgeted['expenditure'] ?? 0) + (float) ($budgeted['capital'] ?? 0);
        $budgetIn = (float) ($budgeted['income'] ?? 0);

        return [
            'months' => $months,
            'pacing' => $this->pacing($budget, $cumOut, $budgetOut, $rows, $sections),
            'top_expenditure' => $this->topLines($rows, $sections, 10),
            'budget' => ['income' => $budgetIn, 'expenditure' => $budgetOut],
            'totals' => ['in' => $cumIn, 'out' => $cumOut, 'net' => $cumIn - $cumOut],
            'has_data' => $rows !== [],
        ];
    }

    /**
     * Are we spending faster than the year is passing?
     *
     * The single number that turns a budget from a record into a control. Two
     * percentages — how much of the year has gone, how much of the budget has —
     * and the gap between them. Ahead of the year is an overspend forming, and
     * it is worth saying long before the money runs out.
     *
     * A budget can also simply be unfinished, and the difference matters: a
     * tool that shouts "385% overspent" at a budget nobody filled in is a tool
     * people stop reading. Coverage — how many of the lines that actually spent
     * money were given a figure — is what tells the two apart.
     *
     * @return array{elapsed: float, consumed: float, gap: float, status: string, projected: float}
     */
    protected function pacing(Budget $budget, float $spent, float $budgeted, array $rows = [], $sections = null): array
    {
        $start = $budget->start_date ? strtotime($budget->start_date) : null;
        $end = $budget->end_date ? strtotime($budget->end_date) : null;

        $elapsed = 0.0;
        if ($start && $end && $end > $start) {
            $elapsed = max(0.0, min(1.0, (time() - $start) / ($end - $start))) * 100;
        }

        $consumed = $budgeted > 0 ? ($spent / $budgeted) * 100 : 0.0;
        $gap = $consumed - $elapsed;

        // Projected outturn if the current rate simply continues.
        $projected = $elapsed > 0 ? $spent / ($elapsed / 100) : 0.0;

        // Which lines actually spent, and how many of those carry a figure.
        $spendingLines = [];
        foreach ($rows as $row) {
            if ($row['direction'] === self::OUT && $row['line_id'] !== null) {
                $spendingLines[$row['line_id']] = true;
            }
        }

        $budgetedLines = DB::table('budget_allocations')
            ->where('budget_id', $budget->id)
            ->whereNotNull('budget_line_id')
            ->where('allocated_amount', '>', 0)
            ->pluck('budget_line_id')
            ->flip();

        $covered = 0;
        foreach (array_keys($spendingLines) as $id) {
            if ($budgetedLines->has($id)) {
                $covered++;
            }
        }

        $coverage = $spendingLines === [] ? 100.0 : ($covered / count($spendingLines)) * 100;

        // Spending several times a budget, on lines mostly left blank, is an
        // unfinished budget rather than a runaway one. Say which.
        $likelyIncomplete = $budgeted > 0 && ($consumed > 150 || $coverage < 60);

        if ($budgeted <= 0) {
            $status = 'none';
        } elseif ($likelyIncomplete) {
            $status = 'incomplete';
        } elseif ($gap > 15) {
            $status = 'critical';
        } elseif ($gap > 5) {
            $status = 'warning';
        } else {
            $status = 'good';
        }

        return [
            'elapsed' => $elapsed,
            'consumed' => $consumed,
            'gap' => $gap,
            'status' => $status,
            'projected' => $projected,
            'budgeted' => $budgeted,
            'spent' => $spent,
            'coverage' => $coverage,
            'lines_spending' => count($spendingLines),
            'lines_budgeted' => $covered,
        ];
    }

    /** Where the money actually went, largest first. */
    protected function topLines(array $rows, $sections, int $limit): array
    {
        $totals = [];

        foreach ($rows as $row) {
            if ($row['direction'] !== self::OUT || $row['line_id'] === null) {
                continue;
            }
            $totals[$row['line_id']] = ($totals[$row['line_id']] ?? 0) + $row['amount'];
        }

        arsort($totals);
        $totals = array_slice($totals, 0, $limit, true);

        $lines = BudgetLine::whereIn('id', array_keys($totals))->get()->keyBy('id');

        $out = [];
        foreach ($totals as $id => $amount) {
            $line = $lines->get($id);
            $out[] = [
                'code' => $line->code ?? '',
                'name' => $line->name ?? '',
                'amount' => (float) $amount,
            ];
        }

        return $out;
    }

    /** "January 2026" -> "Jan", which is all a chart axis has room for. */
    protected function shortMonth(string $name): string
    {
        return substr(trim(explode(' ', $name)[0]), 0, 3);
    }

    /* ===================================================================
     |  Sources
     |
     |  Each mirrors the corresponding method in BudgetActualsService — same
     |  joins, same routing rules — but emits rows instead of totals. Where
     |  that service groups, this one does not.
     |===================================================================*/

    /**
     * Student fees.
     *
     * Tuition is split by school through enrolment → programme → faculty, so a
     * fee's line cannot be read from its category alone. The rule is the one
     * BudgetActualsService::addFees() applies; keeping them identical is what
     * lets the totals reconcile.
     */
    protected function feeRows(?string $from, ?string $to): array
    {
        $facultyLines = BudgetLine::whereNotNull('faculty_id')->pluck('id', 'faculty_id');

        $records = DB::table('fees as f')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'f.category_id')
                    ->where('m.mapping_type', '=', 'fee_category');
            })
            ->leftJoin('fees_categories as fc', 'fc.id', '=', 'f.category_id')
            ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
            ->leftJoin('programs as p', 'p.id', '=', 'se.program_id')
            ->leftJoin('students as s', 's.id', '=', 'se.student_id')
            ->leftJoin('applications as ap', 'ap.id', '=', 'f.applicant_id')
            ->where('f.paid_amount', '>', 0)
            ->when($from, fn ($q) => $q->whereDate('f.pay_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('f.pay_date', '<=', $to))
            // The cash that arrived on this fee. Credit applied to it from
            // another fee brought no cash and is not a movement in a cash book;
            // it was already recorded when it was paid. See Fee::cashReceivedSql().
            ->selectRaw("f.id, f.pay_date, f.paid_amount, f.note, " . \App\Models\Fee::cashReceivedSql('f') . " as cash_received,
                         m.budget_line_id as mapped_line, fc.title as category,
                         fc.is_admission, fc.is_resit, p.faculty_id,
                         TRIM(CONCAT(COALESCE(s.first_name, ap.first_name, ''), ' ',
                                     COALESCE(s.last_name,  ap.last_name,  ''))) as payer")
            ->get();

        $rows = [];

        foreach ($records as $r) {
            // A fee settled entirely by credit from another fee received no
            // cash, so it has no place in the book.
            if ((float) $r->cash_received <= 0.009) {
                continue;
            }

            $isTuition = !$r->is_admission && !$r->is_resit;

            $lineId = null;
            if ($isTuition && $r->faculty_id && isset($facultyLines[$r->faculty_id])) {
                $lineId = (int) $facultyLines[$r->faculty_id];
            } elseif ($r->mapped_line) {
                $lineId = (int) $r->mapped_line;
            }

            $rows[] = [
                'date' => (string) $r->pay_date,
                'ref' => 'FEE-' . $r->id,
                'description' => trim(($r->payer ?: __('Student')) . ' — ' . ($r->category ?: __('Fee'))),
                'direction' => self::IN,
                'amount' => (float) $r->cash_received,
                'line_id' => $lineId,
                'source' => 'fee',
                'source_id' => (int) $r->id,
            ];
        }

        return $rows;
    }

    /** Recorded income other than student fees. */
    protected function incomeRows(?string $from, ?string $to): array
    {
        $records = DB::table('incomes as i')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'i.category_id')
                    ->where('m.mapping_type', '=', 'income_category');
            })
            ->leftJoin('income_categories as ic', 'ic.id', '=', 'i.category_id')
            ->when($from, fn ($q) => $q->whereDate('i.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('i.date', '<=', $to))
            ->selectRaw('i.id, i.date, i.amount, i.title, i.reference,
                         COALESCE(i.budget_line_id, m.budget_line_id) as line_id,
                         ic.title as category')
            ->get();

        return $this->simpleRows($records, self::IN, 'income', 'INC-');
    }

    /** Expenditure, routed by its category's mapping. */
    protected function expenseRows(?string $from, ?string $to): array
    {
        $records = DB::table('expenses as e')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'e.category_id')
                    ->where('m.mapping_type', '=', 'expense_category');
            })
            ->leftJoin('expense_categories as ec', 'ec.id', '=', 'e.category_id')
            ->when($from, fn ($q) => $q->whereDate('e.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.date', '<=', $to))
            ->selectRaw('e.id, e.date, e.amount, e.title, e.reference,
                         COALESCE(e.budget_line_id, m.budget_line_id) as line_id,
                         ec.title as category')
            ->get();

        return $this->simpleRows($records, self::OUT, 'expense', 'EXP-');
    }

    /**
     * Salaries and employer social charges, read from the ledger.
     *
     * Payroll has no expense row to itemise, so its rows come from the journal
     * entry it posts — which is also why an unposted run appears nowhere: it is
     * not a cost yet.
     */
    protected function payrollRows(?string $from, ?string $to): array
    {
        $payrollTypes = [
            'payroll', 'payroll_tax', 'payroll_allowance',
            'payroll_deduction', 'payroll_staff_payable',
        ];

        $records = DB::table('journal_entries as je')
            ->join('journal_entry_lines as jl', 'jl.journal_entry_id', '=', 'je.id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jl.account_id')
            ->leftJoin('default_account_mappings as m', function ($join) use ($payrollTypes) {
                $join->on('m.debit_account_id', '=', 'jl.account_id')
                    ->whereIn('m.mapping_type', $payrollTypes);
            })
            // Reversals belong in the book. Unpaying a payroll credits the same
            // expense accounts, and reading only 'payroll' left the original
            // standing — so a payroll reversed and re-posted was counted twice
            // here while the ledger held it once. A reversal is also a real
            // event on the day it happened, so it earns its own row rather than
            // being netted away silently.
            ->whereIn('je.reference_type', ['payroll', 'payroll_reversal'])
            ->where('je.is_posted', 1)
            ->whereIn('coa.class_number', [2, 6])
            ->when($from, fn ($q) => $q->whereDate('je.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('je.entry_date', '<=', $to))
            ->selectRaw('jl.id, je.entry_date, je.entry_number, je.description,
                         jl.debit, jl.credit, m.budget_line_id as line_id,
                         coa.account_code, coa.account_name')
            ->get();

        $rows = [];

        foreach ($records as $r) {
            // A debit on an expense account is money spent; the credit that
            // reverses it is money coming back. Both are real days in the book.
            $isReversal = (float) $r->credit > 0;
            $amount = $isReversal ? (float) $r->credit : (float) $r->debit;

            if ($amount == 0.0) {
                continue;
            }

            $rows[] = [
                'date' => substr((string) $r->entry_date, 0, 10),
                'ref' => (string) ($r->entry_number ?: 'JE-' . $r->id),
                'description' => trim(($r->description ?: __('Payroll')) . ' — ' . $r->account_code . ' ' . $r->account_name),
                'direction' => $isReversal ? self::IN : self::OUT,
                'amount' => $amount,
                // Shown as its own inbound row, but it removes spending from
                // the line rather than adding to it.
                'signed_amount' => $isReversal ? -$amount : $amount,
                'line_id' => $r->line_id ? (int) $r->line_id : null,
                'source' => 'payroll',
                'source_id' => (int) $r->id,
            ];
        }

        return $rows;
    }

    /** Incomes and expenses share a shape, so they share the mapping to rows. */
    protected function simpleRows($records, string $direction, string $source, string $prefix): array
    {
        $rows = [];

        foreach ($records as $r) {
            $amount = (float) $r->amount;
            if ($amount == 0.0) {
                continue;
            }

            $description = $r->title ?: ($r->category ?: ucfirst($source));
            if ($r->category && $r->title && $r->category !== $r->title) {
                $description .= ' — ' . $r->category;
            }

            $rows[] = [
                'date' => substr((string) $r->date, 0, 10),
                'ref' => (string) ($r->reference ?: $prefix . $r->id),
                'description' => $description,
                'direction' => $direction,
                'amount' => $amount,
                'line_id' => $r->line_id ? (int) $r->line_id : null,
                'source' => $source,
                'source_id' => (int) $r->id,
            ];
        }

        return $rows;
    }
}
