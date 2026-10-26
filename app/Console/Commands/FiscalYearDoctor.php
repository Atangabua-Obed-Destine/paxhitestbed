<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Is every journal entry filed under the fiscal year that actually contains it?
 *
 * It was not here. One fiscal year existed, shaped like the ACADEMIC year
 * (October 2026 to July 2027), while the ledger ran from October 2025 to
 * October 2026. Every posting path resolved the fiscal year by date and then
 * fell back to "whichever year is active" when no year matched — so 912 of 924
 * entries were stamped with a year that does not contain them, and carried no
 * accounting period at all, because no period could contain them either.
 *
 * Nothing about that is visible from the screens. The entries exist, the
 * reports run, and the figures are simply wrong: a year filtered by
 * fiscal_year_id returns two calendar years of transactions, while anything
 * reported by period returns almost nothing.
 *
 * Read-only unless --repair is given, and --repair only ever re-points an entry
 * at the year and period its OWN DATE falls in. It never moves a date, never
 * touches an amount, and never crosses a closed year without being told to.
 */
class FiscalYearDoctor extends Command
{
    protected $signature = 'accounting:fiscal-year-doctor
                            {--repair : Re-point misfiled entries at the fiscal year their date falls in}
                            {--allow-closed : Permit repairs that move entries into or out of a closed year}';

    protected $description = 'Report, and optionally repair, journal entries filed under the wrong fiscal year';

    public function handle(): int
    {
        $this->line('');
        $this->info('Fiscal years and the entries filed under them');
        $this->line(str_repeat('=', 90));
        $this->line('  database : ' . DB::getDatabaseName());
        $this->line('');

        $this->years();
        $this->coverage();

        $misfiled = $this->misfiled();
        $strayPeriods = $this->strayPeriods();
        $unmatched = $this->unmatched();

        if ($misfiled->isEmpty() && $strayPeriods->isEmpty() && $unmatched === 0) {
            $this->info('  Every entry is filed under the fiscal year its date falls in.');
            $this->line('');

            return self::SUCCESS;
        }

        if (!$this->option('repair')) {
            $this->line('');
            $this->line('  Nothing has been changed. To correct the filing:');
            $this->line('      php artisan accounting:fiscal-year-doctor --repair');
            $this->line('');

            return self::SUCCESS;
        }

        // One list: an entry can be in the wrong year, or in the right year
        // with no period, and both are repaired the same way — by its date.
        return $this->repair($misfiled->concat($strayPeriods)->unique('id'));
    }

    protected function years(): void
    {
        $years = FiscalYear::orderBy('start_date')->get();

        $this->info(sprintf('Fiscal years (%d)', $years->count()));
        $this->line(str_repeat('-', 90));

        if ($years->isEmpty()) {
            $this->warn('  None. Every journal entry is therefore unattached.');
            $this->line('');

            return;
        }

        $this->line(sprintf('  %-4s %-16s %-12s %-12s %-7s %-7s %-8s %s',
            'id', 'name', 'from', 'to', 'months', 'active', 'closed', 'entries'));

        foreach ($years as $year) {
            $this->line(sprintf('  %-4s %-16s %-12s %-12s %-7s %-7s %-8s %d',
                $year->id,
                mb_substr($year->name, 0, 16),
                $year->start_date->toDateString(),
                $year->end_date->toDateString(),
                $year->duration_in_months,
                $year->is_active ? 'yes' : '',
                $year->is_closed ? 'yes' : '',
                JournalEntry::where('fiscal_year_id', $year->id)->count()));

            if ($warning = $year->shapeWarning()) {
                $this->warn('       ' . $warning);
            }

            foreach ($year->overlapping() as $clash) {
                $this->warn(sprintf('       overlaps "%s" (%s to %s)', $clash->name,
                    $clash->start_date->toDateString(), $clash->end_date->toDateString()));
            }
        }

        $this->line('');
    }

    /** Which calendar years does the ledger actually cover, and are they catered for? */
    protected function coverage(): void
    {
        $this->info('What the ledger covers');
        $this->line(str_repeat('-', 90));

        $rows = JournalEntry::selectRaw('YEAR(entry_date) as calendar_year, COUNT(*) as entries,'
                . ' SUM(total_debit) as debits, MIN(entry_date) as first_entry, MAX(entry_date) as last_entry')
            ->groupBy('calendar_year')
            ->orderBy('calendar_year')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  no journal entries at all.');
            $this->line('');

            return;
        }

        foreach ($rows as $row) {
            $covered = FiscalYear::forDate($row->first_entry) && FiscalYear::forDate($row->last_entry);

            $this->line(sprintf('  %s : %5d entries, debits %18s, %s to %s   %s',
                $row->calendar_year,
                $row->entries,
                number_format((float) $row->debits, 2),
                $row->first_entry,
                $row->last_entry,
                $covered ? '' : '<- no fiscal year covers part of this'));
        }

        $this->line('');
    }

    /**
     * Entries whose fiscal year does not contain their own date.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function misfiled()
    {
        $entries = JournalEntry::whereNotNull('fiscal_year_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('fiscal_years')
                    ->whereColumn('fiscal_years.id', 'journal_entries.fiscal_year_id')
                    ->whereColumn('fiscal_years.start_date', '<=', 'journal_entries.entry_date')
                    ->whereColumn('fiscal_years.end_date', '>=', 'journal_entries.entry_date');
            })
            ->orderBy('entry_date')
            ->get();

        $this->info(sprintf('Entries filed under a fiscal year that does not contain them: %d', $entries->count()));
        $this->line(str_repeat('-', 90));

        if ($entries->isEmpty()) {
            $this->line('  none');
            $this->line('');

            return $entries;
        }

        // Grouped, because listing 912 lines helps nobody.
        $grouped = $entries->groupBy(fn ($entry) => $entry->fiscal_year_id . '|'
            . \Carbon\Carbon::parse($entry->entry_date)->format('Y'));

        foreach ($grouped as $key => $group) {
            [$yearId, $calendar] = explode('|', $key);
            $target = FiscalYear::forDate($group->first()->entry_date);

            $this->line(sprintf('  %5d entries dated %s, filed under fiscal year #%s  ->  %s',
                $group->count(),
                $calendar,
                $yearId,
                $target ? 'would move to #' . $target->id . ' (' . $target->name . ')'
                    : 'NO fiscal year covers these dates — create one first'));
        }

        $noPeriod = JournalEntry::whereNull('accounting_period_id')->count();
        $this->line('');
        $this->line(sprintf('  entries with no accounting period at all: %d of %d',
            $noPeriod, JournalEntry::count()));
        $this->line('');

        return $entries;
    }

    /**
     * Entries in the right fiscal year, but with no period or the wrong one.
     *
     * A separate fault from being in the wrong year, and easy to miss because
     * the year looks right. It arises the same way: the period was resolved at
     * posting time and nothing contained the date, so the entry was written
     * with none — and every report that groups by period has been quietly
     * leaving it out ever since.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function strayPeriods()
    {
        $entries = JournalEntry::whereNotNull('fiscal_year_id')
            // In the right year: anything else is the misfiled() list's job.
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('fiscal_years')
                    ->whereColumn('fiscal_years.id', 'journal_entries.fiscal_year_id')
                    ->whereColumn('fiscal_years.start_date', '<=', 'journal_entries.entry_date')
                    ->whereColumn('fiscal_years.end_date', '>=', 'journal_entries.entry_date');
            })
            ->where(function ($query) {
                $query->whereNull('accounting_period_id')
                    ->orWhereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('accounting_periods')
                            ->whereColumn('accounting_periods.id', 'journal_entries.accounting_period_id')
                            ->whereColumn('accounting_periods.start_date', '<=', 'journal_entries.entry_date')
                            ->whereColumn('accounting_periods.end_date', '>=', 'journal_entries.entry_date');
                    });
            })
            ->orderBy('entry_date')
            ->get();

        // Only the ones a period actually exists for are repairable; the rest
        // need their periods generating first, which is the year's own screen.
        $fixable = $entries->filter(fn ($entry) => FiscalYear::periodForDate($entry->entry_date) !== null);

        $this->info(sprintf('Entries in the right year but with no matching period: %d', $entries->count()));
        $this->line(str_repeat('-', 90));

        if ($entries->isEmpty()) {
            $this->line('  none');
        } else {
            $this->line(sprintf('  %d can be matched to an existing period', $fixable->count()));

            if ($entries->count() > $fixable->count()) {
                $this->warn(sprintf('  %d have no period to go to — generate the monthly periods for'
                    . ' their fiscal year first.', $entries->count() - $fixable->count()));
            }
        }

        $this->line('');

        return $fixable;
    }

    /** Entries with no fiscal year at all. */
    protected function unmatched(): int
    {
        $count = JournalEntry::whereNull('fiscal_year_id')->count();

        if ($count > 0) {
            $this->warn(sprintf('  %d entr%s carry no fiscal year at all.', $count, $count === 1 ? 'y' : 'ies'));
            $this->line('');
        }

        return $count;
    }

    /**
     * What to do about an entry no fiscal year covers.
     *
     * The old advice was always "create the missing fiscal year", which
     * assumes the date is right. Far from the books in use that assumption is
     * usually wrong: an entry dated 2005 on a school whose ledger starts in
     * 2025 is a mistyped year, not a lost exercice, and creating a 2005
     * exercice to receive it files a typo as twenty years of history.
     *
     * So the distance decides which advice is given. Nothing is repaired
     * either way — both roads out of this need a decision about the school's
     * accounts that a repair command does not get to make.
     *
     * @param  array<int, string>  $dates  entry_date of each entry left alone
     */
    protected function reportStrays(array $dates): void
    {
        $years = FiscalYear::orderBy('start_date')->get();

        if ($years->isEmpty()) {
            $this->line('      There are no fiscal years at all. Create the first one.');

            return;
        }

        $first = Carbon::parse($years->first()->start_date);
        $last = Carbon::parse($years->last()->end_date);

        // A year, which is wide enough that an adjacent exercice the school
        // genuinely has not created yet still reads as near.
        $near = [];
        $far = [];

        foreach ($dates as $date) {
            $d = Carbon::parse($date);
            $gap = $d->lt($first) ? $d->diffInDays($first) : $d->diffInDays($last);

            if ($gap > 365) { $far[] = $d->toDateString(); } else { $near[] = $d->toDateString(); }
        }

        if ($near !== []) {
            $this->line(sprintf('      %d near the years you have (%s).',
                count($near), implode(', ', array_slice(array_unique($near), 0, 5))));
            $this->line('      Create the missing fiscal year, then run this again.');
        }

        if ($far !== []) {
            $this->line(sprintf('      %d far outside them (%s), against books running %s to %s.',
                count($far), implode(', ', array_slice(array_unique($far), 0, 5)),
                $first->toDateString(), $last->toDateString()));
            $this->line('      Check the transaction date before creating anything. A date this far');
            $this->line('      out is usually mistyped, and an exercice created to receive it files');
            $this->line('      the typo as history. Correct the date on the transaction and re-save it;');
            $this->line('      the posting follows the date.');
        }
    }

    /**
     * Re-point each misfiled entry at the year and period its date falls in.
     *
     * Nothing is invented: an entry whose date no fiscal year covers is left
     * exactly as it is and reported, because the fix for that is to create the
     * year, which is a decision about the school's accounts and not one a
     * repair command gets to make.
     */
    protected function repair($misfiled): int
    {
        $this->info('Repairing');
        $this->line(str_repeat('-', 90));

        $moved = 0;
        $skippedNoYear = 0;
        $skippedClosed = 0;
        $periodsSet = 0;
        $strayDates = [];

        DB::beginTransaction();

        try {
            foreach ($misfiled as $entry) {
                $target = FiscalYear::forDate($entry->entry_date);

                if (!$target) {
                    $skippedNoYear++;
                    $strayDates[] = $entry->entry_date;
                    continue;
                }

                $current = FiscalYear::find($entry->fiscal_year_id);

                // A closed year is a signed-off set of accounts. Moving an
                // entry into or out of one changes a figure somebody has
                // already declared, so it takes an explicit instruction.
                if (!$this->option('allow-closed')
                    && (($current && $current->is_closed) || $target->is_closed)) {
                    $skippedClosed++;
                    continue;
                }

                $period = FiscalYear::periodForDate($entry->entry_date);

                $entry->fiscal_year_id = $target->id;

                if ($period) {
                    $entry->accounting_period_id = $period->id;
                    $periodsSet++;
                } else {
                    // Better null than pointing at a period in another year.
                    $entry->accounting_period_id = null;
                }

                $entry->save();
                $moved++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('  Repair failed, nothing was changed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('  entries re-pointed at the right fiscal year : %d', $moved));
        $this->line(sprintf('  of those, given an accounting period        : %d', $periodsSet));

        if ($skippedNoYear > 0) {
            $this->warn(sprintf('  left alone, no fiscal year covers their date : %d', $skippedNoYear));
            $this->reportStrays($strayDates);
        }

        if ($skippedClosed > 0) {
            $this->warn(sprintf('  left alone, a closed year is involved        : %d', $skippedClosed));
            $this->line('      Those figures have been signed off. Re-run with --allow-closed only if');
            $this->line('      the accounts they belong to have not been declared.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
