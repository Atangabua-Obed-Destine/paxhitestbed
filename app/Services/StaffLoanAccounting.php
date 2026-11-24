<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollSetting;
use App\Models\StaffLoan;
use Illuminate\Support\Facades\DB;

/**
 * The ledger side of a staff loan: the money going out when it is given.
 *
 * Only when the school has set both accounts on the Payroll Settings page
 * (the staff-advances account and the account the money is paid from). With
 * either missing nothing is posted, and the screens say so.
 */
class StaffLoanAccounting
{
    public function configured(): bool
    {
        $s = PayrollSetting::current();

        return $s->advance_account_id && $s->advance_payment_account_id
            && ChartOfAccount::whereKey($s->advance_account_id)->exists()
            && ChartOfAccount::whereKey($s->advance_payment_account_id)->exists();
    }

    /**
     * Debit staff advances, credit the paying account, on the day it was given.
     *
     * @throws \RuntimeException when the day has no open fiscal year
     */
    public function recordGiven(StaffLoan $loan, int $byUserId): ?JournalEntry
    {
        if (!$this->configured()) {
            return null;
        }

        $s = PayrollSetting::current();
        $date = $loan->given_on->format('Y-m-d');
        $year = FiscalYear::forDate($date);

        if (!$year) {
            throw new \RuntimeException(__('No fiscal year covers :date, so the loan cannot be recorded in the accounts. Open the fiscal year first.', ['date' => $loan->given_on->format('j M Y')]));
        }

        $period = FiscalYear::periodForDate($date);
        $who = trim(($loan->user->first_name ?? '') . ' ' . ($loan->user->last_name ?? ''));
        $amount = round((float) $loan->amount, 2);

        return DB::transaction(function () use ($loan, $s, $date, $year, $period, $who, $amount, $byUserId) {
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber($date),
                'entry_date' => $date,
                'fiscal_year_id' => $year->id,
                'accounting_period_id' => $period->id ?? null,
                'journal_type' => 'payroll',
                'description' => "{$loan->title} given - {$who}",
                'reference_type' => 'staff_loan',
                'reference_id' => $loan->id,
                'total_debit' => $amount,
                'total_credit' => $amount,
                'is_posted' => false,
                'is_system_generated' => true,
                'created_by' => $byUserId,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id, 'line_number' => 1, 'account_id' => $s->advance_account_id,
                'description' => "{$loan->title} - {$who}", 'debit' => $amount, 'credit' => 0,
            ]);
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id, 'line_number' => 2, 'account_id' => $s->advance_payment_account_id,
                'description' => "{$loan->title} paid out - {$who}", 'debit' => 0, 'credit' => $amount,
            ]);

            $entry->post($byUserId);

            $loan->forceFill(['journal_entry_id' => $entry->id])->save();

            return $entry;
        });
    }

    /** Undo the entry made when the loan was given, dated today. */
    public function reverseGiven(StaffLoan $loan, int $byUserId): ?JournalEntry
    {
        if (!$loan->journal_entry_id || $loan->reversal_entry_id) {
            return null;
        }

        $original = JournalEntry::with('lines')->find($loan->journal_entry_id);
        if (!$original) {
            return null;
        }

        $date = now()->format('Y-m-d');
        $year = FiscalYear::forDate($date);
        if (!$year) {
            throw new \RuntimeException(__('No fiscal year covers today, so the loan cannot be cancelled in the accounts.'));
        }
        $period = FiscalYear::periodForDate($date);

        return DB::transaction(function () use ($loan, $original, $date, $year, $period, $byUserId) {
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber($date),
                'entry_date' => $date,
                'fiscal_year_id' => $year->id,
                'accounting_period_id' => $period->id ?? null,
                'journal_type' => 'payroll',
                'description' => 'Reversal: ' . $original->description,
                'reference_type' => 'staff_loan_reversal',
                'reference_id' => $loan->id,
                'reversed_entry_id' => $original->id,
                'total_debit' => $original->total_credit,
                'total_credit' => $original->total_debit,
                'is_posted' => false,
                'is_system_generated' => true,
                'created_by' => $byUserId,
            ]);

            $n = 1;
            foreach ($original->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id, 'line_number' => $n++, 'account_id' => $line->account_id,
                    'description' => 'Reversal: ' . $line->description, 'debit' => $line->credit, 'credit' => $line->debit,
                ]);
            }

            $entry->post($byUserId);
            $original->forceFill(['is_reversed' => true])->save();
            $loan->forceFill(['reversal_entry_id' => $entry->id])->save();

            return $entry;
        });
    }
}
