<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cash flow statement, indirect method, read from the posted ledger.
 *
 * Every account other than cash is put in exactly one line, and each line is
 * the cash effect of that account's movement in the period: minus its change
 * in debit-minus-credit terms. An asset that grows used cash; a debt that
 * grows kept cash in. Because the ledger balances, the lines add up to the
 * change in cash exactly, so the statement cannot disagree with the balance
 * sheet.
 *
 * It used to pick accounts by pattern, one query each, and add the change in
 * what the school owes with the wrong sign: owing more CNPS or tax showed as
 * cash going out, and paying it as cash coming in.
 *
 * Periods are by entry date. Cash is class 5, less 59 (provisions on it).
 * The year-end closing is left out: it moves no cash, and counted it would
 * turn the year's result into a movement of equity.
 */
class CashFlowService
{
    const CATEGORY_OPERATING = 'operating';
    const CATEGORY_INVESTING = 'investing';
    const CATEGORY_FINANCING = 'financing';

    /**
     * Each line: [section, key, label]. The first prefix that matches an
     * account code wins, so longer prefixes come first.
     */
    private const LINES = [
        // The result of the period, and its later appropriation between
        // equity accounts (closing the year moves it into 13, then 11/12).
        ['6', 'operating', 'net_income', 'Result for the period'],
        ['7', 'operating', 'net_income', 'Result for the period'],
        ['8', 'operating', 'net_income', 'Result for the period'],
        ['11', 'operating', 'net_income', 'Result for the period'],
        ['12', 'operating', 'net_income', 'Result for the period'],
        ['13', 'operating', 'net_income', 'Result for the period'],
        // Charges that use no cash: depreciation and provisions.
        ['28', 'operating', 'depreciation', 'Depreciation and provisions (no cash)'],
        ['29', 'operating', 'depreciation', 'Depreciation and provisions (no cash)'],
        ['19', 'operating', 'depreciation', 'Depreciation and provisions (no cash)'],
        ['39', 'operating', 'depreciation', 'Depreciation and provisions (no cash)'],
        ['49', 'operating', 'depreciation', 'Depreciation and provisions (no cash)'],
        ['59', 'operating', 'depreciation', 'Depreciation and provisions (no cash)'],
        // Working capital.
        ['3', 'operating', 'inventory', 'Change in inventory'],
        ['40', 'operating', 'payables', 'Change in amounts owed to suppliers'],
        ['41', 'operating', 'receivables', 'Change in amounts owed by students and other debtors'],
        ['42', 'operating', 'staff', 'Change in amounts owed to or by staff'],
        ['43', 'operating', 'social', 'Change in amounts owed to CNPS and social bodies'],
        ['44', 'operating', 'state', 'Change in taxes owed to the State'],
        ['4', 'operating', 'other_working_capital', 'Change in other amounts owed'],
        // Investing.
        ['2', 'investing', 'fixed_assets', 'Equipment, buildings and other fixed assets'],
        // Financing.
        ['10', 'financing', 'capital', 'Capital contributed'],
        ['14', 'financing', 'grants', 'Investment grants received'],
        ['15', 'financing', 'equity_other', 'Other equity movements'],
        ['16', 'financing', 'borrowings', 'Borrowings received less repaid'],
        ['17', 'financing', 'borrowings', 'Borrowings received less repaid'],
        ['18', 'financing', 'borrowings', 'Borrowings received less repaid'],
        ['1', 'financing', 'equity_other', 'Other equity movements'],
    ];

    /**
     * @param  int|null  $fiscalYearId  the year whose dates are used when none are given
     * @return array<string, mixed>
     */
    public function generateCashFlowStatement($fiscalYearId, $startDate = null, $endDate = null)
    {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);

        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::parse($fiscalYear->start_date);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::parse($fiscalYear->end_date);

        $rows = DB::select("
            SELECT a.account_code AS code, a.normal_balance, a.opening_balance,
                   COALESCE(SUM(CASE WHEN je.entry_date < ? THEN l.debit - l.credit END), 0) AS before_start,
                   COALESCE(SUM(CASE WHEN je.entry_date >= ? THEN l.debit - l.credit END), 0) AS movement
            FROM chart_of_accounts a
            LEFT JOIN journal_entry_lines l ON l.account_id = a.id
            LEFT JOIN journal_entries je ON je.id = l.journal_entry_id
                 AND je.is_posted = 1 AND je.deleted_at IS NULL AND je.entry_date <= ?
                 AND je.journal_type <> 'closing'
            GROUP BY a.id, a.account_code, a.normal_balance, a.opening_balance",
            [$startDate->toDateString(), $startDate->toDateString(), $endDate->toDateString()]);

        $beginningCash = 0.0;
        $cashMovement = 0.0;
        $lines = [];

        foreach ($rows as $r) {
            $code = (string) $r->code;
            $movement = (float) $r->movement;

            if ($this->isCash($code)) {
                $stored = (float) $r->opening_balance * ($r->normal_balance === 'debit' ? 1 : -1);
                $beginningCash += $stored + (float) $r->before_start;
                $cashMovement += $movement;
                continue;
            }

            if (abs($movement) < 0.005) {
                continue;
            }

            [$section, $key, $label] = $this->lineFor($code);
            $lines[$section][$key] ??= ['description' => __($label), 'amount' => 0.0, 'type' => $key];
            // Cash effect: an asset that grows used cash, a debt that grows kept it.
            $lines[$section][$key]['amount'] -= $movement;
        }

        // Lines in statement order (the result first, then what it is
        // adjusted by), not in the order the accounts happen to be numbered.
        $order = array_flip(array_unique(array_column(self::LINES, 2)));
        foreach ($lines as &$sectionLines) {
            uksort($sectionLines, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
        }
        unset($sectionLines);

        $section = function (string $name, string $title) use ($lines) {
            $items = array_values(array_filter(
                array_map(function ($i) {
                    $i['amount'] = round($i['amount'], 2);
                    return $i;
                }, $lines[$name] ?? []),
                fn ($i) => abs($i['amount']) >= 0.005
            ));

            return ['title' => __($title), 'items' => $items, 'total' => round(array_sum(array_column($items, 'amount')), 2)];
        };

        $statement = [
            'fiscal_year' => $fiscalYear->name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'beginning_cash_balance' => round($beginningCash, 2),
            'ending_cash_balance' => round($beginningCash + $cashMovement, 2),
            'operating_activities' => $section(self::CATEGORY_OPERATING, 'Cash flows from operating activities'),
            'investing_activities' => $section(self::CATEGORY_INVESTING, 'Cash flows from investing activities'),
            'financing_activities' => $section(self::CATEGORY_FINANCING, 'Cash flows from financing activities'),
        ];

        $statement['net_operating'] = $statement['operating_activities']['total'];
        $statement['net_investing'] = $statement['investing_activities']['total'];
        $statement['net_financing'] = $statement['financing_activities']['total'];
        $statement['net_change_in_cash'] = round($statement['net_operating'] + $statement['net_investing'] + $statement['net_financing'], 2);

        $statement['calculated_ending_balance'] = round($statement['beginning_cash_balance'] + $statement['net_change_in_cash'], 2);
        $statement['difference'] = round($statement['ending_cash_balance'] - $statement['calculated_ending_balance'], 2);
        $statement['is_balanced'] = abs($statement['difference']) < 0.01;

        return $statement;
    }

    /**
     * This year against the one before it — by date, not by id: ids follow
     * the order years were created in, which is not the order they run in.
     */
    public function generateComparativeCashFlowStatement($fiscalYearId, $previousFiscalYearId = null)
    {
        $current = FiscalYear::findOrFail($fiscalYearId);
        $currentStatement = $this->generateCashFlowStatement($fiscalYearId);

        if (!$previousFiscalYearId) {
            $previousFiscalYearId = FiscalYear::whereDate('end_date', '<', $current->start_date)
                ->orderByDesc('end_date')
                ->value('id');
        }

        $previousStatement = $previousFiscalYearId ? $this->generateCashFlowStatement($previousFiscalYearId) : null;

        $out = [
            'current' => $currentStatement,
            'previous' => $previousStatement,
            'variance' => $previousStatement ? $this->calculateVariance($currentStatement, $previousStatement) : null,
            'periods' => [
                'current' => ['name' => $currentStatement['fiscal_year']],
                'previous' => ['name' => $previousStatement['fiscal_year'] ?? __('No earlier year')],
            ],
        ];

        // Side by side, line by line, for the screen and the PDF.
        foreach (['operating', 'investing', 'financing'] as $name) {
            $now = collect($currentStatement[$name . '_activities']['items'])->keyBy('type');
            $before = collect($previousStatement[$name . '_activities']['items'] ?? [])->keyBy('type');
            $items = [];
            foreach ($now->keys()->merge($before->keys())->unique() as $type) {
                $c = (float) ($now[$type]['amount'] ?? 0);
                $p = (float) ($before[$type]['amount'] ?? 0);
                $items[] = [
                    'description' => $now[$type]['description'] ?? $before[$type]['description'],
                    'current' => $c, 'previous' => $p, 'variance' => round($c - $p, 2),
                    'change_percent' => $p != 0 ? round(($c - $p) / abs($p) * 100, 1) : 0,
                ];
            }
            $c = (float) $currentStatement['net_' . $name];
            $p = (float) ($previousStatement['net_' . $name] ?? 0);
            $out[$name] = [
                'items' => $items, 'current_total' => $c, 'previous_total' => $p, 'variance' => round($c - $p, 2),
                'change_percent' => $p != 0 ? round(($c - $p) / abs($p) * 100, 1) : 0,
            ];
        }
        $c = (float) $currentStatement['net_change_in_cash'];
        $p = (float) ($previousStatement['net_change_in_cash'] ?? 0);
        $out['net_change'] = [
            'current' => $c, 'previous' => $p, 'variance' => round($c - $p, 2),
            'change_percent' => $p != 0 ? round(($c - $p) / abs($p) * 100, 1) : 0,
        ];

        return $out;
    }

    private function isCash(string $code): bool
    {
        return $code !== '' && $code[0] === '5' && !str_starts_with($code, '59');
    }

    /** @return array{0:string,1:string,2:string} */
    private function lineFor(string $code): array
    {
        foreach (self::LINES as [$prefix, $section, $key, $label]) {
            if (str_starts_with($code, $prefix)) {
                return [$section, $key, $label];
            }
        }

        // Anything else (an analytical class 9 account, say) still moved
        // money between accounts, so it is shown rather than dropped.
        return ['operating', 'other', 'Other movements'];
    }

    protected function calculateVariance(array $current, array $previous)
    {
        $out = [];
        foreach (['net_operating', 'net_investing', 'net_financing', 'net_change_in_cash'] as $key) {
            $out[$key] = [
                'amount' => $current[$key] - $previous[$key],
                'percentage' => $previous[$key] != 0
                    ? (($current[$key] - $previous[$key]) / abs($previous[$key])) * 100
                    : 0,
            ];
        }

        return $out;
    }
}
