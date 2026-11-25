<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Financial health: the handful of figures an accountant and a board look at
 * first, read from the books (posted journal lines), each with what it means
 * and a plain verdict.
 *
 *   cash and months of cover   can the school pay its way?
 *   surplus and margin         is it living within its income this year?
 *   payroll share of income    the cost a school can least easily cut
 *   fees outstanding           how much of what was billed is still owed
 *   supplier bills overdue     is the school paying its suppliers on time?
 *   student credit held (419)  money held for students, not the school's yet
 *   budget execution           spending against the year's budget, and pace
 *
 * plus twelve months of income, expenses, surplus and closing cash, and this
 * year to date against the same months last year.
 *
 * Nothing here is stored: every call reads the ledger as it stands.
 */
class FinancialHealth
{
    public const OK = 'ok';
    public const WATCH = 'watch';
    public const ACT = 'act';

    public function summary(?string $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?: now())->toDateString();
        $year = FiscalYear::forDate($asOf);
        $from = $year ? $year->start_date->toDateString() : Carbon::parse($asOf)->startOfYear()->toDateString();

        $cash = $this->balance('5', $asOf, ['59']);
        $income = $this->movement('7', $from, $asOf, 'credit');
        $expenses = $this->movement('6', $from, $asOf, 'debit');
        $payroll = $this->movement('66', $from, $asOf, 'debit');
        $surplus = round($income - $expenses, 2);

        // Average cash spending per month over the last three complete months:
        // class 6 without depreciation (68), which spends no cash.
        $end = Carbon::parse($asOf)->startOfMonth()->subDay();
        $start = (clone $end)->subMonthsNoOverflow(2)->startOfMonth();
        $monthlySpend = round(($this->movement('6', $start->toDateString(), $end->toDateString(), 'debit')
            - $this->movement('68', $start->toDateString(), $end->toDateString(), 'debit')) / 3, 2);
        $cover = $monthlySpend > 0 ? round($cash / $monthlySpend, 1) : null;

        $fees = $this->feesOutstanding($asOf);
        $payables = $this->payables($asOf);
        $credit = -$this->balance('419', $asOf);
        $budget = $this->budgetExecution($asOf);
        $lastYear = $this->sameTimeLastYear($from, $asOf);

        $indicators = [
            $this->indicator('cash', __('Cash and bank'), $cash, 'money',
                $cash < 0 ? self::ACT : self::OK,
                __('Everything in the cash boxes, bank and mobile money accounts (class 5) at :date.', ['date' => Carbon::parse($asOf)->format('j M Y')])),
            $this->indicator('cover', __('Months of cash cover'), $cover, 'months',
                $cover === null ? self::WATCH : ($cover < 1 ? self::ACT : ($cover < 3 ? self::WATCH : self::OK)),
                __('Cash divided by average monthly spending (:amount over the last three months). Under one month is urgent; three or more is comfortable.', ['amount' => format_amount($monthlySpend)])),
            $this->indicator('surplus', __('Surplus for the year to date'), $surplus, 'money',
                $surplus < 0 ? self::WATCH : self::OK,
                __('Income (class 7) less expenses (class 6) since :date.', ['date' => Carbon::parse($from)->format('j M Y')])),
            $this->indicator('margin', __('Surplus as a share of income'), $income > 0 ? round($surplus / $income * 100, 1) : null, 'percent',
                $income <= 0 ? self::WATCH : ($surplus / $income < 0 ? self::ACT : ($surplus / $income < 0.05 ? self::WATCH : self::OK)),
                __('Below 0% the school spends more than it earns; a small positive margin builds reserves.')),
            $this->indicator('payroll', __('Payroll as a share of income'), $income > 0 ? round($payroll / $income * 100, 1) : null, 'percent',
                $income <= 0 ? self::WATCH : ($payroll / $income > 0.8 ? self::ACT : ($payroll / $income > 0.65 ? self::WATCH : self::OK)),
                __('Salaries and social charges (account 66) against income. Above about 65% leaves little for everything else.')),
            $this->indicator('collection', __('Fees collected'), $fees['rate'], 'percent',
                $fees['rate'] === null ? self::WATCH : ($fees['rate'] < 60 ? self::ACT : ($fees['rate'] < 80 ? self::WATCH : self::OK)),
                __('This school year (from :start): :owed still owed of :billed due by :date. Owed from earlier years: :arrears.', ['start' => Carbon::parse($fees['year_start'])->format('j M Y'), 'owed' => format_amount($fees['overdue']), 'date' => Carbon::parse($asOf)->format('j M Y'), 'billed' => format_amount($fees['billed_due']), 'arrears' => format_amount($fees['arrears'])])),
            $this->indicator('payables', __('Supplier bills overdue'), $payables['overdue'], 'money',
                $payables['overdue'] > 0.005 ? self::WATCH : self::OK,
                __(':owed owed to suppliers in all; :overdue of it past its due date.', ['owed' => format_amount($payables['owed']), 'overdue' => format_amount($payables['overdue'])])),
            $this->indicator('credit', __('Held for students (419)'), $credit, 'money', self::OK,
                __('Overpayments not yet used on another fee or refunded. It is owed to students, not the school\'s income.')),
        ];

        if ($budget) {
            $indicators[] = $this->indicator('budget', __('Budget spent'), $budget['spent_pct'], 'percent',
                ($budget['spent_pct'] > $budget['time_pct'] + 10 || ($budget['time_pct'] > 15 && $budget['spent_pct'] < $budget['time_pct'] / 3)) ? self::WATCH : self::OK,
                __(':spent of :budget spent (:pct% of the budget) with :time% of the budget year gone. Far ahead of the year means overspending; far behind usually means spending is not being filed against this budget.', [
                    'spent' => format_amount($budget['spent']), 'budget' => format_amount($budget['budgeted']),
                    'pct' => $budget['spent_pct'], 'time' => $budget['time_pct'],
                ]));
        }

        return [
            'as_of' => $asOf,
            'from' => $from,
            'year' => $year->name ?? null,
            'income' => $income,
            'expenses' => $expenses,
            'surplus' => $surplus,
            'cash' => $cash,
            'indicators' => $indicators,
            'attention' => array_values(array_filter($indicators, fn ($i) => $i['state'] !== self::OK)),
            'months' => $this->months($asOf),
            'last_year' => $lastYear,
            'fees' => $fees,
            'payables' => $payables,
            'budget' => $budget,
        ];
    }

    /** Twelve months to $asOf: income, expenses, surplus and closing cash, from the books. */
    public function months(string $asOf): array
    {
        $end = Carbon::parse($asOf)->endOfMonth();
        $start = (clone $end)->subMonthsNoOverflow(11)->startOfMonth();

        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.is_posted', 1)->whereNull('e.deleted_at')->where('e.journal_type', '!=', 'closing')
            ->whereDate('e.entry_date', '>=', $start->toDateString())
            ->whereDate('e.entry_date', '<=', min($end->toDateString(), $asOf))
            ->selectRaw("DATE_FORMAT(e.entry_date, '%Y-%m') as ym,
                SUM(CASE WHEN a.account_code LIKE '7%' THEN l.credit - l.debit ELSE 0 END) as income,
                SUM(CASE WHEN a.account_code LIKE '6%' THEN l.debit - l.credit ELSE 0 END) as expenses,
                SUM(CASE WHEN a.account_code LIKE '5%' AND a.account_code NOT LIKE '59%' THEN l.debit - l.credit ELSE 0 END) as cash_move")
            ->groupBy('ym')->get()->keyBy('ym');

        $cash = $this->balance('5', (clone $start)->subDay()->toDateString(), ['59']);
        $out = [];

        for ($m = clone $start; $m <= $end; $m->addMonthNoOverflow()) {
            $key = $m->format('Y-m');
            $r = $rows->get($key);
            $income = round((float) ($r->income ?? 0), 2);
            $expenses = round((float) ($r->expenses ?? 0), 2);
            $cash = round($cash + (float) ($r->cash_move ?? 0), 2);
            $out[] = [
                'month' => $key,
                'label' => $m->translatedFormat('M Y'),
                'income' => $income,
                'expenses' => $expenses,
                'surplus' => round($income - $expenses, 2),
                'cash' => $cash,
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */

    /** Balance (debit − credit) of every detail account under $prefix at the end of $asOf. */
    public function balance(string $prefix, string $asOf, array $except = []): float
    {
        $q = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.is_posted', 1)->whereNull('e.deleted_at')
            ->where('a.account_code', 'like', $prefix . '%')
            ->whereDate('e.entry_date', '<=', $asOf);

        foreach ($except as $x) {
            $q->where('a.account_code', 'not like', $x . '%');
        }

        $openingQuery = DB::table('chart_of_accounts')->whereNull('deleted_at')->where('account_code', 'like', $prefix . '%');
        foreach ($except as $x) {
            $openingQuery->where('account_code', 'not like', $x . '%');
        }
        $opening = (float) $openingQuery->sum(DB::raw("CASE WHEN normal_balance = 'credit' THEN -opening_balance ELSE opening_balance END"));

        return round($opening + (float) $q->sum(DB::raw('l.debit - l.credit')), 2);
    }

    /** Movement on accounts under $prefix between two dates, in their normal direction; closing entries left out. */
    public function movement(string $prefix, string $from, string $to, string $normal): float
    {
        $signed = $normal === 'credit' ? 'l.credit - l.debit' : 'l.debit - l.credit';

        return round((float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.is_posted', 1)->whereNull('e.deleted_at')->where('e.journal_type', '!=', 'closing')
            ->where('a.account_code', 'like', $prefix . '%')
            ->whereDate('e.entry_date', '>=', $from)->whereDate('e.entry_date', '<=', $to)
            ->sum(DB::raw($signed)), 2);
    }

    /**
     * The share of this school year's fees, due by $asOf, that has been
     * settled; and, apart, what is still owed from earlier years. Mixing old
     * arrears into the rate would hide how this year's collection is going.
     * The school year is the institutional budget covering $asOf, else the
     * fiscal year.
     */
    protected function feesOutstanding(string $asOf): array
    {
        $budget = Budget::where('is_institutional', 1)->whereDate('start_date', '<=', $asOf)->whereDate('end_date', '>=', $asOf)->orderByDesc('id')->first();
        $yearStart = $budget ? $budget->start_date->toDateString()
            : (optional(FiscalYear::forDate($asOf))->start_date?->toDateString() ?? Carbon::parse($asOf)->startOfYear()->toDateString());

        $year = $this->feesDue($yearStart, $asOf);
        $earlier = $this->feesDue(null, Carbon::parse($yearStart)->subDay()->toDateString());

        return $year + [
            'year_start' => $yearStart,
            'arrears' => $earlier['overdue'],
        ];
    }

    protected function feesDue(?string $from, string $to): array
    {
        $net = \App\Models\Fee::netPaidSql('f');
        $row = DB::table('fees as f')
            ->where('f.status', '!=', 3)
            ->when($from, fn ($q) => $q->whereDate('f.due_date', '>=', $from))
            ->whereDate('f.due_date', '<=', $to)
            ->selectRaw("SUM(f.fee_amount - f.discount_amount + f.fine_amount) as billed,
                SUM(LEAST(GREATEST({$net}, 0), f.fee_amount - f.discount_amount + f.fine_amount)) as collected")
            ->first();

        $billed = round((float) ($row->billed ?? 0), 2);
        $collected = round((float) ($row->collected ?? 0), 2);

        return [
            'billed_due' => $billed,
            'collected' => $collected,
            'overdue' => round(max(0, $billed - $collected), 2),
            'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : null,
        ];
    }

    protected function payables(string $asOf): array
    {
        $aging = app(SupplierPayables::class)->aging($asOf);
        $buckets = $aging['totals'] ?? [];
        $owed = (float) ($buckets['total'] ?? 0);
        $notDue = (float) ($buckets['not_due'] ?? 0);

        return ['owed' => round($owed, 2), 'overdue' => round(max(0, $owed - $notDue), 2), 'ledger' => (float) ($aging['ledger_balance'] ?? 0)];
    }

    protected function budgetExecution(string $asOf): ?array
    {
        $budget = Budget::query()
            ->where('is_institutional', 1)
            ->whereDate('start_date', '<=', $asOf)->whereDate('end_date', '>=', $asOf)
            ->orderByDesc('id')->first();

        if (!$budget) {
            return null;
        }

        $actuals = app(\App\Services\BudgetActualsService::class);
        $lines = $actuals->forPeriod($budget->start_date->toDateString(), $asOf)['lines'];
        $spent = (float) $actuals->sectionTotal($lines, 'expenditure');
        $allocations = DB::table('budget_allocations')->where('budget_id', $budget->id)
            ->selectRaw('budget_line_id, SUM(allocated_amount) as amount')->groupBy('budget_line_id')
            ->pluck('amount', 'budget_line_id')->map(fn ($v) => (float) $v)->all();
        $budgeted = (float) $actuals->sectionTotal($allocations, 'expenditure');

        $days = max(1, $budget->start_date->diffInDays($budget->end_date) + 1);
        $gone = min($days, $budget->start_date->diffInDays(Carbon::parse($asOf)) + 1);

        return [
            'name' => $budget->title ?: ('#' . $budget->id),
            'spent' => round($spent, 2),
            'budgeted' => round($budgeted, 2),
            'spent_pct' => $budgeted > 0 ? round($spent / $budgeted * 100, 1) : 0.0,
            'time_pct' => round($gone / $days * 100, 1),
        ];
    }

    protected function sameTimeLastYear(string $from, string $to): array
    {
        $lyFrom = Carbon::parse($from)->subYear()->toDateString();
        $lyTo = Carbon::parse($to)->subYear()->toDateString();
        $income = $this->movement('7', $lyFrom, $lyTo, 'credit');
        $expenses = $this->movement('6', $lyFrom, $lyTo, 'debit');

        return ['from' => $lyFrom, 'to' => $lyTo, 'income' => $income, 'expenses' => $expenses, 'surplus' => round($income - $expenses, 2)];
    }

    protected function indicator(string $key, string $label, $value, string $unit, string $state, string $detail): array
    {
        return compact('key', 'label', 'value', 'unit', 'state', 'detail');
    }
}
