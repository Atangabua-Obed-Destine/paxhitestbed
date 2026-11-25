<?php

namespace App\Console\Commands;

use App\Models\Fee;
use App\Services\Accounting\FeeAccrual;
use App\Services\Accounting\StudentCreditLedger;
use App\Services\FeeLedgerPosting;
use App\Services\TransactionAutoMapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Switch fees to accrual: what students owe goes on 411.
 *
 *   php artisan fees:accrual            what would change (everything rolled back)
 *   php artisan fees:accrual --enable   do it (asks first; take a backup)
 *
 * In one go, so the ledger is never half-switched:
 *   1. every billed fee posts Dr 411 / Cr fee income on its assign date;
 *   2. every fee payment and payment-plan instalment already posted is
 *      reposted to settle 411 instead of crediting income (the original
 *      entries are reversed, never deleted);
 *   3. student credit moves between 411 and 419, and credit with no cash
 *      behind it that settled a billed fee is honoured as a discount;
 *   4. 411 and 419 are checked against the fee and credit records.
 *
 * Total income over the life of the books changes only by what is still owed
 * (now income, and on 411) and by discounts honoured; when income is
 * recognised moves from the day of payment to the day of billing.
 */
class FeeAccrualCommand extends Command
{
    protected $signature = 'fees:accrual {--enable : Switch accrual on and bring every fee into line} {--force : Do not ask}';

    protected $description = 'Put fees on accrual: bill fees to 411 when assigned and settle 411 when paid.';

    public function handle(): int
    {
        $enable = (bool) $this->option('enable');

        if ($enable && !$this->option('force') && !$this->confirm('Switch fees to accrual and repost every fee payment? Take a database backup first.')) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        DB::beginTransaction();

        try {
            $result = $this->switchOver();
        } catch (\Throwable $e) {
            DB::rollBack();
            FeeAccrual::flush();
            $this->error('Stopped, nothing was changed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $enable ? DB::commit() : DB::rollBack();
        FeeAccrual::flush();

        $this->table(['Step', 'Result'], [
            ['Fees billed to 411', sprintf('%d posted, %d unchanged, %s billed in all', $result['billing']['posted'] + $result['billing']['reposted'], $result['billing']['unchanged'], number_format($result['billed'], 0, ',', ' '))],
            ['Fee payments reposted to settle 411', $result['fees']],
            ['Plan instalments reposted to settle 411', $result['plans']],
            ['Student credit postings', sprintf('%d posted, %d reversed', $result['credit']['posted'], $result['credit']['reversed'])],
            ['411 against what students owe', sprintf('%s in the books, %s owed: %s', number_format($result['tie']['ledger'], 0, ',', ' '), number_format($result['tie']['expected'], 0, ',', ' '), $result['tie']['agrees'] ? 'agree' : 'DIFFER by ' . number_format($result['tie']['difference'], 0, ',', ' '))],
            ['419 against unused credit', $result['credit_tie']['agrees'] ? 'agree' : 'DIFFER by ' . $result['credit_tie']['difference']],
            ['Income recognised (class 7), change', number_format($result['income_change'], 0, ',', ' ')],
        ]);

        foreach (array_merge($result['billing']['problems'], $result['credit']['problems'], $result['problems']) as $p) {
            $this->warn('  ' . $p);
        }

        $this->info($enable ? 'Fees are now on accrual.' : 'Dry run: nothing was changed. Re-run with --enable to do it.');

        return self::SUCCESS;
    }

    protected function switchOver(): array
    {
        $incomeBefore = $this->income();

        DB::table('accounting_settings')->where('setting_key', FeeAccrual::SETTING_ENABLED)->update(['setting_value' => '1', 'updated_at' => now()]);
        FeeAccrual::flush();

        $billing = app(FeeAccrual::class)->syncAll();
        $billed = (float) DB::table('fee_billing_postings')->where('status', 'active')->sum('amount');

        $receivable = FeeAccrual::receivableAccountId();
        $posting = app(FeeLedgerPosting::class);
        $autoMap = app(TransactionAutoMapService::class);
        $problems = [];

        // Fee payments posted to income: repost to settle 411.
        $fees = 0;
        $mapped = DB::table('transaction_mappings')->where('transaction_type', 'fee')->where('status', 'active')
            ->where('credit_account_id', '!=', $receivable)->pluck('transaction_id');
        foreach (Fee::with('category')->whereIn('id', $mapped)->get() as $fee) {
            if (!FeeAccrual::bills($fee)) {
                continue;
            }
            try {
                $outcome = $posting->resync($fee, null, true);
                $fees += $outcome === FeeLedgerPosting::REPOSTED ? 1 : 0;
            } catch (\Throwable $e) {
                $problems[] = "Fee #{$fee->id}: " . $e->getMessage();
            }
        }

        // Payment-plan instalments likewise.
        $plans = 0;
        $planMappings = DB::table('transaction_mappings')->where('transaction_type', 'payment_plan_payment')->where('status', 'active')
            ->where('credit_account_id', '!=', $receivable)->get();
        foreach ($planMappings as $m) {
            if (!FeeAccrual::settlementAccountFor('payment_plan_payment', (int) $m->transaction_id)) {
                continue;
            }
            $categoryId = DB::table('payment_plan_payments as ppp')
                ->join('payment_plan_installments as ppi', 'ppi.id', '=', 'ppp.installment_id')
                ->join('payment_plans as pp', 'pp.id', '=', 'ppi.payment_plan_id')
                ->join('fees as f', 'f.id', '=', 'pp.fee_id')
                ->where('ppp.id', $m->transaction_id)->value('f.category_id');
            $ok = $autoMap->remap('payment_plan_payment', (int) $m->transaction_id, $categoryId, [
                'amount' => (float) $m->amount, 'date' => $m->transaction_date, 'description' => $m->description ?? 'Payment plan instalment',
            ]);
            $ok ? $plans++ : $problems[] = "Plan payment #{$m->transaction_id} could not be reposted.";
        }

        $credit = app(StudentCreditLedger::class)->sync();

        return [
            'billing' => $billing,
            'billed' => $billed,
            'fees' => $fees,
            'plans' => $plans,
            'credit' => $credit,
            'tie' => app(FeeAccrual::class)->tieOut(),
            'credit_tie' => app(StudentCreditLedger::class)->tieOut(),
            'income_change' => round($this->income() - $incomeBefore, 2),
            'problems' => $problems,
        ];
    }

    protected function income(): float
    {
        return (float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.is_posted', 1)->whereNull('e.deleted_at')->where('a.account_code', 'like', '7%')
            ->sum(DB::raw('l.credit - l.debit'));
    }
}
