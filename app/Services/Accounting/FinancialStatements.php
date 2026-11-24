<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The balances behind the balance sheet and the trial balance, read from the
 * posted ledger in one pass.
 *
 * Three rules the screens used to break, each of which put the balance sheet
 * out of balance on this system:
 *
 *  1. Every account that carries money is reported. The screens read only
 *     active detail accounts, so 110,700 posted to the heading "24" vanished
 *     from both the balance sheet and the trial balance.
 *  2. Each line is signed by what it is, not by the account's normal balance.
 *     Accumulated depreciation (28x) has a credit balance; it reduces fixed
 *     assets. Reading it in its "normal" direction added it to them instead.
 *  3. The year's result belongs on the passif. Until the year is closed the
 *     profit sits in classes 6 and 7, and a balance sheet that leaves it out
 *     can only balance when the school made exactly nothing.
 *
 * Because every account lands in exactly one section, the sheet balances by
 * construction whenever the ledger itself does.
 */
class FinancialStatements
{
    /** Contra-asset accounts: depreciation and provisions on assets. */
    private const CONTRA_ASSET = ['28', '29', '39', '49', '59'];

    /**
     * Every account with its balance as debit minus credit, from posted,
     * non-deleted entries, including the opening balance kept on the account.
     *
     * With $from: `opening` is the balance before $from and `debit`/`credit`
     * the movements from $from to $to. Without it, everything up to $to is
     * movement and `opening` is the stored opening balance only.
     *
     * Accounts are kept when they are active detail accounts, or when they
     * carry anything at all — a heading or a retired account with money on it
     * is still money.
     */
    public function accounts(?string $from, ?string $to): Collection
    {
        $to = $to ?: '9999-12-31';
        $fromSql = $from ?: '0001-01-01';

        $rows = DB::select("
            SELECT a.id, a.account_code, a.account_name, a.account_name_fr, a.normal_balance,
                   a.account_category, a.is_active, a.opening_balance, a.parent_id,
                   COALESCE(SUM(CASE WHEN je.entry_date < ? THEN l.debit END), 0)  AS pre_debit,
                   COALESCE(SUM(CASE WHEN je.entry_date < ? THEN l.credit END), 0) AS pre_credit,
                   COALESCE(SUM(CASE WHEN je.entry_date >= ? THEN l.debit END), 0)  AS debit,
                   COALESCE(SUM(CASE WHEN je.entry_date >= ? THEN l.credit END), 0) AS credit
            FROM chart_of_accounts a
            LEFT JOIN journal_entry_lines l ON l.account_id = a.id
            LEFT JOIN journal_entries je ON je.id = l.journal_entry_id
                 AND je.is_posted = 1 AND je.deleted_at IS NULL AND je.entry_date <= ?
            GROUP BY a.id, a.account_code, a.account_name, a.account_name_fr, a.normal_balance,
                     a.account_category, a.is_active, a.opening_balance, a.parent_id
            ORDER BY a.account_code", [$fromSql, $fromSql, $fromSql, $fromSql, $to]);

        return collect($rows)->map(function ($r) {
            $stored = (float) $r->opening_balance * ($r->normal_balance === 'debit' ? 1 : -1);
            $r->opening = round($stored + (float) $r->pre_debit - (float) $r->pre_credit, 2);
            $r->debit = round((float) $r->debit, 2);
            $r->credit = round((float) $r->credit, 2);
            $r->closing = round($r->opening + $r->debit - $r->credit, 2);
            $r->class = substr((string) $r->account_code, 0, 1);
            $r->is_postable = $r->account_category === 'detail' && (bool) $r->is_active;

            return $r;
        })->filter(fn ($r) => $r->is_postable
            || abs($r->opening) > 0.004 || $r->debit > 0.004 || $r->credit > 0.004)->values();
    }

    /**
     * The balance sheet as of a date, OHADA order.
     *
     * Every amount is positive when it is what its section says (an asset on
     * the actif, a debt on the passif); contra accounts come out negative under
     * the assets they reduce.
     *
     * @return array<string, mixed>
     */
    public function balanceSheet(string $asOf): array
    {
        $accounts = $this->accounts(null, $asOf)->filter(fn ($a) => abs($a->closing) > 0.004);

        $year = FiscalYear::forDate($asOf);
        $yearStart = $year ? $year->start_date->format('Y-m-d') : null;

        $line = function ($a, float $amount) {
            return (object) [
                'id' => $a->id,
                'account_code' => $a->account_code,
                'account_name' => $a->account_name,
                'account_name_fr' => $a->account_name_fr,
                'is_postable' => $a->is_postable,
                'balance' => round($amount, 2),
            ];
        };

        $s = [
            'fixedAssets' => collect(), 'inventory' => collect(), 'receivables' => collect(),
            'cashAndBanks' => collect(), 'equity' => collect(), 'financialDebts' => collect(),
            'payables' => collect(), 'treasuryLiabilities' => collect(), 'unclassified' => collect(),
        ];
        $resultTotal = 0.0;

        foreach ($accounts as $a) {
            $code = (string) $a->account_code;
            $two = substr($code, 0, 2);
            $contra = in_array($two, self::CONTRA_ASSET, true);

            switch ($a->class) {
                case '1':
                    // 16-19: borrowings and provisions for risks are debts, not equity.
                    $in = in_array($two, ['16', '17', '18', '19'], true) ? 'financialDebts' : 'equity';
                    $s[$in]->push($line($a, -$a->closing));
                    break;
                case '2':
                    $s['fixedAssets']->push($line($a, $a->closing));
                    break;
                case '3':
                    $s['inventory']->push($line($a, $a->closing));
                    break;
                case '4':
                    // Placed by the side the balance actually falls on: a
                    // supplier the school has overpaid is a receivable.
                    if ($contra || $a->closing > 0) {
                        $s['receivables']->push($line($a, $a->closing));
                    } else {
                        $s['payables']->push($line($a, -$a->closing));
                    }
                    break;
                case '5':
                    if ($contra || $a->closing > 0) {
                        $s['cashAndBanks']->push($line($a, $a->closing));
                    } else {
                        $s['treasuryLiabilities']->push($line($a, -$a->closing));
                    }
                    break;
                case '6':
                case '7':
                case '8':
                    $resultTotal -= $a->closing;
                    break;
                default:
                    $s['unclassified']->push($line($a, $a->closing));
            }
        }

        // The result not yet closed into class 1: this exercice's, and any
        // earlier exercice's that was never closed (shown separately, as the
        // report à nouveau it will become).
        $yearResult = $resultTotal;
        $priorResult = 0.0;
        if ($yearStart) {
            $priorResult = -$this->accounts(null, date('Y-m-d', strtotime($yearStart . ' -1 day')))
                ->filter(fn ($a) => in_array($a->class, ['6', '7', '8'], true))
                ->sum('closing');
            $yearResult = $resultTotal - $priorResult;
        }

        $sum = fn (string ...$keys) => round(array_sum(array_map(fn ($k) => $s[$k]->sum('balance'), $keys)), 2);

        $out = $s;
        $out['yearResult'] = round($yearResult, 2);
        $out['priorResult'] = round($priorResult, 2);
        $out['fiscalYear'] = $year;
        $out['totalAssets'] = $sum('fixedAssets', 'inventory', 'receivables', 'cashAndBanks');
        $out['totalEquity'] = round($sum('equity') + $yearResult + $priorResult, 2);
        $out['totalLiabilities'] = round($out['totalEquity'] + $sum('financialDebts', 'payables', 'treasuryLiabilities'), 2);
        $out['difference'] = round($out['totalAssets'] - $out['totalLiabilities'], 2);
        $out['unclassifiedTotal'] = $sum('unclassified');

        return $out;
    }
}
