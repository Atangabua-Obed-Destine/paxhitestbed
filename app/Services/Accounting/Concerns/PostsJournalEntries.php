<?php

namespace App\Services\Accounting\Concerns;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;

/**
 * Writing and reversing system journal entries, with the date rules every
 * posting here follows: a date must fall in an open fiscal year and an open
 * month, and a reversal lands in its original's month while that month is
 * open, otherwise today.
 *
 * Shared by supplier payables and the student credit ledger, so the two can
 * never disagree about where an entry or its reversal belongs.
 */
trait PostsJournalEntries
{
    /** A new, unposted system entry dated $date, in the fiscal year and month that contain it. */
    protected function newEntry(string $date, string $journalType, string $description, string $refType, int $refId,
                              float $amount, ?int $userId, ?string $referenceNumber = null): JournalEntry
    {
        $year = FiscalYear::forDate($date);
        if (!$year) {
            throw new \RuntimeException(__('No fiscal year covers :date. Create it before posting.',
                ['date' => Carbon::parse($date)->format('j F Y')]));
        }
        if ($year->is_closed) {
            throw new \RuntimeException(__('The fiscal year that contains :date is closed.',
                ['date' => Carbon::parse($date)->format('j F Y')]));
        }

        $period = FiscalYear::periodForDate($date);
        if ($period && $period->is_closed) {
            throw new \RuntimeException(__('The accounting period that contains :date is closed.',
                ['date' => Carbon::parse($date)->format('j F Y')]));
        }

        return JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber($date),
            'entry_date' => $date,
            'fiscal_year_id' => $year->id,
            'accounting_period_id' => $period->id ?? null,
            'journal_type' => $journalType,
            'description' => mb_substr($description, 0, 250),
            'reference_number' => $referenceNumber,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'total_debit' => round($amount, 2),
            'total_credit' => round($amount, 2),
            'is_posted' => false,
            'is_system_generated' => true,
            'created_by' => $userId,
        ]);
    }

    /**
     * Post the mirror image of an entry.
     *
     * Dated like every other reversal here (TransactionAutoMapService): in the
     * original's own month while that month is open, so the month reads
     * correctly; today once it is closed, because its figures have been
     * reported and the correction is a new event.
     *
     * @return array{0: JournalEntry, 1: array<int,int>} the reversal, and original line id => reversal line id
     */
    protected function reverseEntry(JournalEntry $original, string $refType, int $refId, ?int $userId): array
    {
        $originalDate = $original->entry_date->toDateString();
        $period = $original->accounting_period_id ? AccountingPeriod::find($original->accounting_period_id) : null;
        $year = $original->fiscal_year_id ? FiscalYear::find($original->fiscal_year_id) : null;

        $date = ($period && !$period->is_closed && $year && !$year->is_closed) ? $originalDate : now()->toDateString();

        $reversal = $this->newEntry($date, $original->journal_type, __('Reversal') . ': ' . $original->description,
            $refType, $refId, (float) $original->total_debit, $userId, $original->reference_number);
        $reversal->reversed_entry_id = $original->id;
        $reversal->save();

        $map = [];
        $n = 1;
        foreach ($original->lines as $line) {
            $new = JournalEntryLine::create([
                'journal_entry_id' => $reversal->id,
                'line_number' => $n++,
                'account_id' => $line->account_id,
                'description' => __('Reversal') . ': ' . $line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ]);
            $map[$line->id] = $new->id;
        }

        $reversal->post($userId);
        $original->forceFill(['is_reversed' => true])->save();

        return [$reversal, $map];
    }
}
