<?php

namespace Database\Seeders;

use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use App\Models\ExpenseCategory;
use App\Models\FeesCategory;
use App\Models\IncomeCategory;
use Illuminate\Database\Seeder;

/**
 * Connects every existing category to a sheet line and a pair of ledger accounts.
 *
 * The mappings below were derived by reading what the transactions in each
 * category actually say, not by matching category names to line names. Two of
 * them would have been wrong the other way round: "Utilities (Internet, water,
 * electricity)" is 99% Starlink subscriptions and belongs on Internet, and
 * "Other Expenses" is mostly untraced cash advances rather than any of the nine
 * specific lines the sheet offers.
 *
 * Where the honest answer is "we cannot know", the mapping points at a line
 * that says so rather than inventing a split.
 *
 * Idempotent: safe to re-run.
 */
class DefaultBudgetMappingSeeder extends Seeder
{
    /** Money leaves here unless a payment account says otherwise. */
    protected const CASH_ACCOUNT = '571';

    public function run(): void
    {
        $lines = BudgetLine::pluck('id', 'code');
        $accounts = ChartOfAccount::pluck('id', 'account_code');
        $cash = $accounts[self::CASH_ACCOUNT] ?? null;

        if (!$cash) {
            $this->command?->error('Cash account 571 missing — run the chart seeders first.');
            return;
        }

        $mapped = 0;
        $skipped = [];

        // --- expenses: debit the expense account, credit cash -------------
        foreach ($this->expenseMap() as $category => [$code, $account]) {
            $model = ExpenseCategory::where('title', $category)->first();
            if (!$model || !isset($lines[$code], $accounts[$account])) {
                $skipped[] = "expense/{$category}";
                continue;
            }
            DefaultAccountMapping::updateOrCreate(
                ['mapping_type' => 'expense_category', 'category_id' => $model->id],
                [
                    'budget_line_id' => $lines[$code],
                    'debit_account_id' => $accounts[$account],
                    'credit_account_id' => $cash,
                    'description' => $category . ' → ' . $code,
                    'status' => 'active',
                ]
            );
            $mapped++;
        }

        // --- income: debit cash, credit the revenue account ---------------
        foreach ($this->incomeMap() as $category => [$code, $account]) {
            $model = IncomeCategory::where('title', $category)->first();
            if (!$model || !isset($lines[$code], $accounts[$account])) {
                $skipped[] = "income/{$category}";
                continue;
            }
            DefaultAccountMapping::updateOrCreate(
                ['mapping_type' => 'income_category', 'category_id' => $model->id],
                [
                    'budget_line_id' => $lines[$code],
                    'debit_account_id' => $cash,
                    'credit_account_id' => $accounts[$account],
                    'description' => $category . ' → ' . $code,
                    'status' => 'active',
                ]
            );
            $mapped++;
        }

        // --- student fees -------------------------------------------------
        foreach ($this->feeMap() as $category => [$code, $account]) {
            $model = FeesCategory::where('title', $category)->first();
            if (!$model || !isset($lines[$code], $accounts[$account])) {
                $skipped[] = "fee/{$category}";
                continue;
            }
            DefaultAccountMapping::updateOrCreate(
                ['mapping_type' => 'fee_category', 'category_id' => $model->id],
                [
                    'budget_line_id' => $lines[$code],
                    'debit_account_id' => $cash,
                    'credit_account_id' => $accounts[$account],
                    'description' => $category . ' → ' . $code,
                    'status' => 'active',
                ]
            );
            $mapped++;
        }

        $this->command?->info("Default mappings written: {$mapped}.");
        if ($skipped) {
            $this->command?->warn('Not mapped: ' . implode(', ', $skipped));
        }
    }

    /**
     * category title => [budget line code, OHADA account]
     */
    protected function expenseMap(): array
    {
        return [
            // 29.3M in one category with no admin/lecturer split recorded.
            'PERSONNEL EXPENSES' => ['444', '661'],
            'TAXES AND SOCIAL INSURANCE' => ['445', '664'],

            // 75% of this is "Cash to ..." with no supporting classification.
            'OTHER EXPENSES' => ['592', '628'],

            // Architecture design, borehole, painting, generator repair.
            'EXTERNAL SERVICES' => ['511', '624'],
            // Catering and drinks at matriculation and examinations.
            'OTHER EXTERNAL SERVICES' => ['471', '634'],
            // Exam booklet printing, campus water/electricity fitting, BEPHA.
            'CONSUMABLES' => ['411', '602'],
            'STATIONERY' => ['404', '601'],
            // Staff transport allowances, not vehicle running costs.
            'TRANSPORT' => ['463', '633'],
            'FUEL' => ['461', '606'],
            // Almost entirely Starlink and internet subscriptions.
            'Utilities (Internet, water, electricity)' => ['441', '626'],
            'COMMUNICATION' => ['403', '626'],
            'FINANCIAL EXPENSES' => ['590', '67'],
            'CAPITAL EXPENDITURE' => ['542', '24'],

            'PROJECT A (CHICKENS)' => ['481', '608'],
            'PROJECT B (SCHOOL FARM/GARDEN)' => ['482', '608'],
            'PROJECT C (MIDEVIV)' => ['483', '608'],

            // Currently disabled, mapped so re-enabling one does not orphan it.
            'EXAMINATION' => ['412', '602'],
            'MATRICULATION' => ['471', '634'],
            'Students Affair' => ['478', '628'],
            'Works carried out by the VC' => ['511', '624'],
        ];
    }

    protected function incomeMap(): array
    {
        return [
            // 30M, 92% of recorded income. Its own line so it can be moved in
            // one step if Finance confirms it is capital rather than income.
            'Capital contribution' => ['623', '73'],
            'Registration' => ['600', '712'],
            'Chaplaincy' => ['625', '758'],
            'Other Income' => ['624', '758'],
        ];
    }

    /**
     * Tuition maps to 610 as a fallback only. The sheet splits tuition by
     * school, which cannot be decided from the fee category alone — the report
     * resolves it through student → programme → faculty.
     */
    protected function feeMap(): array
    {
        return [
            'Admission Fees' => ['600', '712'],
            'First Instalment' => ['610', '711'],
            'Second Installment' => ['610', '711'],
            'Resit Fee' => ['614', '713'],
        ];
    }
}
