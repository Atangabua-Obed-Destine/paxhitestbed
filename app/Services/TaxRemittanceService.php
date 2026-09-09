<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollTaxLine;
use App\Models\TaxRemittance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the school is holding on somebody else's behalf, and paying it over.
 *
 * Payroll withholds correctly and posts correctly, and then the cycle stopped:
 * 443 Etat - Retenue and 431 CNPS accumulated month after month with nothing
 * to clear them and nothing to say they needed clearing. This is the missing
 * half — what is owed, to whom, for which month, and the record of the payment
 * when it is made.
 *
 * The unit is the salary MONTH, not a running balance, because that is the
 * unit a declaration is made in and an inspection asks in. "August's PAYE was
 * declared on the 15th of September under receipt 4471" is a question this can
 * answer; "we have paid 40,000 of the 55,000 we owe" is not one anybody asks.
 */
class TaxRemittanceService
{
    /**
     * Every authority-and-month that has money owing or already paid.
     *
     * WHAT IS OWED COMES FROM THE LEDGER, not from the itemised tax lines,
     * and the distinction matters. A payroll rounds its total once — 21,281.90
     * of itemised tax is stored and posted as 21,282 — so the parts are ten
     * centimes short of what the liability account actually carries. Paying
     * the itemised figure would clear the month on this screen and leave the
     * account permanently non-zero, which is exactly the kind of residue
     * nobody ever goes back for.
     *
     * So the ledger says how much, and the tax lines say what it is made of.
     * Only the payroll entries count towards what was withheld: a remittance
     * also debits this account, and that is the settlement, not a reduction in
     * what was taken.
     *
     * @return array<int, array<string, mixed>>
     */
    public function outstanding(?string $upToMonth = null): array
    {
        $withheld = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('payrolls as p', 'p.id', '=', 'e.reference_id')
            // Both the original and its reversal, netted: unpaying a payroll
            // debits the same liability back out, and reading only 'payroll'
            // would leave a reversed month still looking owed.
            ->whereIn('e.reference_type', ['payroll', 'payroll_reversal'])
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->whereIn('l.account_id', function ($q) {
                $q->select('liability_account_id')->from('payroll_tax_lines')
                  ->whereNotNull('liability_account_id')
                  ->union(
                      DB::table('tax_settings')->select('liability_account_id')
                        ->whereNotNull('liability_account_id')
                  );
            })
            ->when($upToMonth, fn ($q) => $q->whereDate('p.salary_month', '<=', $upToMonth))
            ->groupBy('l.account_id', 'p.salary_month')
            ->selectRaw('l.account_id as account_id,
                         p.salary_month as month,
                         COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) as due')
            ->havingRaw('COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) <> 0')
            ->get();

        // The composition of each of those balances, for the declaration form
        // and the staff count. Keyed the same way so a month with a ledger
        // balance but no recorded breakdown still appears — it is owed either
        // way, and hiding it would be the worse failure.
        $composition = PayrollTaxLine::query()
            ->join('payrolls as p', 'p.id', '=', 'payroll_tax_lines.payroll_id')
            ->where('p.status', 1)
            ->whereNotNull('payroll_tax_lines.liability_account_id')
            ->groupBy('payroll_tax_lines.liability_account_id', 'payroll_tax_lines.salary_month')
            ->selectRaw('payroll_tax_lines.liability_account_id as account_id,
                         payroll_tax_lines.salary_month as month,
                         SUM(payroll_tax_lines.employee_amount) as employee,
                         SUM(payroll_tax_lines.employer_amount) as employer,
                         COUNT(DISTINCT payroll_tax_lines.payroll_id) as staff_count')
            ->get()
            ->keyBy(fn ($r) => $r->account_id . '|' . substr((string) $r->month, 0, 10));

        $paid = TaxRemittance::live()
            ->groupBy('liability_account_id', 'salary_month')
            ->selectRaw('liability_account_id as account_id, salary_month as month, SUM(amount) as paid')
            ->get()
            ->keyBy(fn ($r) => $r->account_id . '|' . substr((string) $r->month, 0, 10));

        $accounts = ChartOfAccount::whereIn('id', $withheld->pluck('account_id')->unique()->all())
            ->get()->keyBy('id');

        $rows = [];

        foreach ($withheld as $row) {
            $month = substr((string) $row->month, 0, 10);
            $key = $row->account_id . '|' . $month;

            $due = round((float) $row->due, 2);
            $settled = round((float) ($paid[$key]->paid ?? 0), 2);
            $account = $accounts->get($row->account_id);
            $parts = $composition->get($key);

            $rows[] = [
                'account_id' => (int) $row->account_id,
                'account_code' => $account->account_code ?? '?',
                'account_name' => $account->account_name ?? __('Unknown account'),
                'month' => $month,
                'month_label' => Carbon::parse($month)->format('F Y'),
                'employee' => round((float) ($parts->employee ?? 0), 2),
                'employer' => round((float) ($parts->employer ?? 0), 2),
                // True when the ledger carries a balance for a month whose
                // composition was never recorded — an older payroll. The
                // figure is still right; only the itemisation is missing, and
                // the screen says so rather than showing an empty breakdown as
                // though nothing were owed.
                'itemised' => $parts !== null,
                'due' => $due,
                'paid' => $settled,
                'outstanding' => round($due - $settled, 2),
                'staff_count' => (int) ($parts->staff_count ?? 0),
                'status' => $this->statusFor($due, $settled),
            ];
        }

        // Oldest debt first — that is the one at risk of a penalty.
        usort($rows, function ($a, $b) {
            return [$a['month'], $a['account_code']] <=> [$b['month'], $b['account_code']];
        });

        return $rows;
    }

    private function statusFor(float $due, float $paid): string
    {
        if ($paid <= 0) {
            return 'undeclared';
        }

        if (abs($due - $paid) < 0.01) {
            return 'settled';
        }

        return $paid > $due ? 'overpaid' : 'part_paid';
    }

    /** The individual taxes behind one authority-and-month, for the declaration form. */
    public function breakdownFor(int $accountId, string $month): array
    {
        return PayrollTaxLine::query()
            ->join('payrolls as p', 'p.id', '=', 'payroll_tax_lines.payroll_id')
            ->where('p.status', 1)
            ->where('payroll_tax_lines.liability_account_id', $accountId)
            ->whereDate('payroll_tax_lines.salary_month', $month)
            ->groupBy('payroll_tax_lines.label')
            ->selectRaw('payroll_tax_lines.label,
                         SUM(payroll_tax_lines.employee_amount) as employee,
                         SUM(payroll_tax_lines.employer_amount) as employer,
                         COUNT(DISTINCT payroll_tax_lines.user_id) as staff_count')
            ->orderByDesc(DB::raw('SUM(payroll_tax_lines.employee_amount) + SUM(payroll_tax_lines.employer_amount)'))
            ->get()
            ->map(fn ($r) => [
                'label' => $r->label,
                'employee' => round((float) $r->employee, 2),
                'employer' => round((float) $r->employer, 2),
                'total' => round((float) $r->employee + (float) $r->employer, 2),
                'staff_count' => (int) $r->staff_count,
            ])
            ->all();
    }

    /**
     * Does what the months say is owed agree with what the ledger holds?
     *
     * They are two independent records of the same thing — the itemised
     * payroll lines, and the posted journal entries — so a difference means
     * one of them is wrong, and the screen has to say so rather than show a
     * confident figure. A manual journal entry against 443, a payroll paid
     * before the breakdown existed, or an edit made directly in the database
     * all show up here.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reconciliation(): array
    {
        $expected = [];

        foreach ($this->outstanding() as $row) {
            $expected[$row['account_id']] = ($expected[$row['account_id']] ?? 0) + $row['outstanding'];
        }

        $rows = [];

        foreach ($expected as $accountId => $amount) {
            $account = ChartOfAccount::find($accountId);

            if (!$account) {
                continue;
            }

            $ledger = $this->ledgerBalance($accountId);
            $difference = round($ledger - $amount, 2);

            $rows[] = [
                'account_id' => (int) $accountId,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'per_month' => round($amount, 2),
                'ledger' => $ledger,
                'difference' => $difference,
                'agrees' => abs($difference) < 0.51,
            ];
        }

        return $rows;
    }

    /** What a liability account actually carries, from posted entries only. */
    public function ledgerBalance(int $accountId): float
    {
        $row = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->selectRaw('COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) as balance')
            ->first();

        return round((float) $row->balance, 2);
    }

    /**
     * Record a payment to an authority and post it to the ledger.
     *
     * DR the liability, CR the bank or cash it left. Neither side is an
     * expense: the salary became a cost when it was paid, and this is the
     * school settling a debt it has been holding since. That is also why the
     * daybook and the budget sheet do not move — both read only classes 2 and
     * 6, and a remittance touches neither.
     *
     * @throws \RuntimeException when the payment cannot stand as recorded
     */
    public function record(array $input, int $userId): TaxRemittance
    {
        $month = Carbon::parse($input['salary_month'])->startOfMonth()->toDateString();
        $accountId = (int) $input['liability_account_id'];
        $amount = round((float) $input['amount'], 2);

        if ($amount <= 0) {
            throw new \RuntimeException(__('A remittance has to be for more than zero.'));
        }

        $liability = ChartOfAccount::find($accountId);
        $source = ChartOfAccount::find($input['source_account_id']);

        if (!$liability || !$source) {
            throw new \RuntimeException(__('That account no longer exists.'));
        }

        // Paying tax out of an expense account would post a cost twice — once
        // when the salary was paid and again here.
        if ((int) $source->class_number !== 5) {
            throw new \RuntimeException(__('Tax has to be paid from a cash or bank account.'));
        }

        $already = TaxRemittance::live()
            ->where('liability_account_id', $accountId)
            ->whereDate('salary_month', $month)
            ->sum('amount');

        if ($already > 0 && empty($input['allow_additional'])) {
            throw new \RuntimeException(__('That month has already been paid to this authority. Void the existing payment first, or tick the box to record an additional one.'));
        }

        $due = 0.0;

        foreach ($this->outstanding() as $row) {
            if ($row['account_id'] === $accountId && $row['month'] === $month) {
                $due = $row['due'];
                break;
            }
        }

        // Paying more than was withheld is possible — a penalty, an
        // adjustment from a previous year — but it should be deliberate, not
        // a typo that quietly turns the liability negative.
        if ($amount > round($due - $already, 2) + 0.01 && empty($input['allow_overpayment'])) {
            throw new \RuntimeException(__('That is more than is outstanding for the month. Tick the overpayment box if it is intended.'));
        }

        return DB::transaction(function () use ($input, $userId, $month, $accountId, $amount, $liability, $source) {
            $remittance = TaxRemittance::create([
                'liability_account_id' => $accountId,
                'salary_month' => $month,
                'amount' => $amount,
                'payment_date' => $input['payment_date'],
                'source_account_id' => $source->id,
                'reference' => $input['reference'] ?? null,
                'note' => $input['note'] ?? null,
                'created_by' => $userId,
            ]);

            $description = sprintf(
                '%s - %s (%s)',
                __('Tax Remittance'),
                $liability->account_name,
                Carbon::parse($month)->format('F Y')
            );

            $entry = $this->postEntry($remittance, $description, $liability->id, $source->id, $amount, $userId);

            $remittance->journal_entry_id = $entry->id;
            $remittance->save();

            return $remittance;
        });
    }

    /**
     * Undo a remittance with a reversing entry.
     *
     * Never a delete, for the same reason unpaying a payroll is not one: the
     * money did leave the bank, somebody recorded it, and a correction that
     * removes all trace of both is indistinguishable from it never having
     * happened.
     */
    public function void(TaxRemittance $remittance, int $userId, ?string $reason = null): TaxRemittance
    {
        if ($remittance->isVoided()) {
            throw new \RuntimeException(__('That payment has already been voided.'));
        }

        return DB::transaction(function () use ($remittance, $userId, $reason) {
            $original = $remittance->journalEntry;

            if ($original) {
                $reversal = JournalEntry::create([
                    'entry_number' => JournalEntry::generateEntryNumber(),
                    'entry_date' => now()->toDateString(),
                    'fiscal_year_id' => $original->fiscal_year_id,
                    'accounting_period_id' => $original->accounting_period_id,
                    'journal_type' => 'remittance',
                    'description' => __('Reversal') . ': ' . $original->description,
                    'reference_type' => 'tax_remittance_reversal',
                    'reference_id' => $remittance->id,
                    'total_debit' => $original->total_credit,
                    'total_credit' => $original->total_debit,
                    'is_posted' => false,
                    'is_system_generated' => true,
                    'created_by' => $userId,
                ]);

                $lineNumber = 1;

                foreach ($original->lines as $line) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $reversal->id,
                        'line_number' => $lineNumber++,
                        'account_id' => $line->account_id,
                        'description' => __('Reversal') . ': ' . $line->description,
                        'debit' => $line->credit,
                        'credit' => $line->debit,
                    ]);
                }

                $reversal->post($userId);
                $remittance->void_journal_entry_id = $reversal->id;
            }

            $remittance->voided_at = now();
            $remittance->voided_by = $userId;
            $remittance->void_reason = $reason;
            $remittance->save();

            return $remittance;
        });
    }

    /** Build and post the two-line entry a remittance is. */
    private function postEntry(TaxRemittance $remittance, string $description, int $debitAccount, int $creditAccount, float $amount, int $userId): JournalEntry
    {
        $fiscalYear = FiscalYear::where('is_active', true)->first();

        if (!$fiscalYear) {
            throw new \RuntimeException(__('There is no active fiscal year to post this into.'));
        }

        $period = AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)
            ->where('is_closed', false)
            ->whereDate('start_date', '<=', $remittance->payment_date)
            ->whereDate('end_date', '>=', $remittance->payment_date)
            ->first();

        $entry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $remittance->payment_date,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id ?? null,
            'journal_type' => 'remittance',
            'description' => $description,
            'reference_number' => $remittance->reference,
            'reference_type' => 'tax_remittance',
            'reference_id' => $remittance->id,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'is_posted' => false,
            'is_system_generated' => true,
            'created_by' => $userId,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'line_number' => 1,
            'account_id' => $debitAccount,
            'description' => __('Tax paid over') . ' - ' . $description,
            'debit' => $amount,
            'credit' => 0,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'line_number' => 2,
            'account_id' => $creditAccount,
            'description' => __('Payment') . ' - ' . $description,
            'debit' => 0,
            'credit' => $amount,
        ]);

        $entry->post($userId);

        return $entry;
    }

    /** The cash and bank accounts a remittance may be paid from. */
    public function sourceAccounts()
    {
        return ChartOfAccount::where('class_number', 5)
            ->where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
    }
}
