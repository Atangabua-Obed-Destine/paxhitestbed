<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Month-end controls an accountant needs every month:
 *
 *   closeMonth / reopenMonth   lock a month once its books are checked, so
 *                              nothing more can be posted into it
 *   reverseEntry               undo a posted manual entry with a mirror entry
 *                              on a chosen date (an accrual reversed on the
 *                              first of the next month, a mistake corrected)
 *
 * Each has a preview that runs the real action inside a transaction and rolls
 * it back, the same way as supplier bills (SupplierPayablesPreview), so what
 * the review dialog shows cannot drift from what confirming does.
 */
class PeriodControls
{
    use Concerns\PostsJournalEntries;

    /* ===================================================================
     |  Closing and reopening a month
     |===================================================================*/

    /** @throws \RuntimeException when the month cannot be closed */
    public function closeMonth(AccountingPeriod $period, ?int $userId, ?string $note = null): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $userId, $note) {
            $period = AccountingPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $year = FiscalYear::find($period->fiscal_year_id);

            if ($period->is_closed) {
                throw new \RuntimeException(__(':month is already closed.', ['month' => $period->name]));
            }
            if ($year && $year->is_closed) {
                throw new \RuntimeException(__('The fiscal year is closed.'));
            }

            $earlierOpen = AccountingPeriod::where('fiscal_year_id', $period->fiscal_year_id)
                ->whereDate('start_date', '<', $period->start_date)
                ->where('is_closed', false)
                ->orderBy('start_date')
                ->pluck('name');
            if ($earlierOpen->isNotEmpty()) {
                throw new \RuntimeException(__('Months close in order. Close :months first.', ['months' => $earlierOpen->implode(', ')]));
            }

            $unposted = $this->entriesIn($period)->where('is_posted', false)->count();
            if ($unposted > 0) {
                throw new \RuntimeException(trans_choice('{1} One journal entry in this month is not posted. Post or delete it first.|[2,*] :count journal entries in this month are not posted. Post or delete them first.', $unposted, ['count' => $unposted]));
            }

            $period->forceFill([
                'is_closed' => true,
                'closed_by' => $userId,
                'closed_at' => now(),
                'closing_note' => $note,
            ])->save();

            return $period;
        });
    }

    /** @throws \RuntimeException when the month cannot be reopened */
    public function reopenMonth(AccountingPeriod $period, ?int $userId, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $userId, $reason) {
            $period = AccountingPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $year = FiscalYear::find($period->fiscal_year_id);

            if (!$period->is_closed) {
                throw new \RuntimeException(__(':month is not closed.', ['month' => $period->name]));
            }
            if ($year && $year->is_closed) {
                throw new \RuntimeException(__('The fiscal year is closed. Reopen the year first.'));
            }
            if (trim($reason) === '') {
                throw new \RuntimeException(__('Give a reason for reopening the month.'));
            }

            $laterClosed = AccountingPeriod::where('fiscal_year_id', $period->fiscal_year_id)
                ->whereDate('start_date', '>', $period->start_date)
                ->where('is_closed', true)
                ->orderBy('start_date')
                ->pluck('name');
            if ($laterClosed->isNotEmpty()) {
                throw new \RuntimeException(__('Reopen the later months first: :months.', ['months' => $laterClosed->implode(', ')]));
            }

            $period->forceFill([
                'is_closed' => false,
                'closing_note' => trim(($period->closing_note ? $period->closing_note . "\n" : '')
                    . sprintf('Reopened %s by user #%s: %s', now()->format('Y-m-d H:i'), $userId ?? '-', trim($reason))),
                'closed_by' => null,
                'closed_at' => null,
            ])->save();

            return $period;
        });
    }

    /** What closing the month would do, and anything that stops it. */
    public function previewClose(AccountingPeriod $period, ?int $userId): array
    {
        $p = $this->blank();
        $p['summary'] = $this->monthSummary($period);

        $this->dryRun($p, fn () => $this->closeMonth($period, $userId));

        foreach ($this->monthWarnings($period) as $warning) {
            $p['warnings'][] = $warning;
        }

        $p['notes'][] = __('Once closed, nothing can be posted into :month. Anything recorded later for these dates is posted on the day it is recorded instead. A month can be reopened, with a reason, while the year is open.', ['month' => $period->name]);

        return $p;
    }

    public function previewReopen(AccountingPeriod $period, ?int $userId): array
    {
        $p = $this->blank();
        $p['summary'] = $this->monthSummary($period);
        $this->dryRun($p, fn () => $this->reopenMonth($period, $userId, 'preview'));
        $p['notes'][] = __('Reopening lets entries be posted into :month again, and its reported figures may then change. The reason is kept on the month.', ['month' => $period->name]);

        return $p;
    }

    /* ===================================================================
     |  Reversing a manual entry
     |===================================================================*/

    /**
     * Post the mirror image of a manual entry on $date.
     *
     * $bySystem is for the code that owns a system entry (depreciation
     * reversing its own posting); the screen never passes it.
     *
     * @throws \RuntimeException
     */
    public function reverseEntry(JournalEntry $entry, string $date, ?int $userId, ?string $reason = null, bool $bySystem = false): JournalEntry
    {
        return DB::transaction(function () use ($entry, $date, $userId, $reason, $bySystem) {
            $entry = JournalEntry::with('lines')->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (!$entry->is_posted) {
                throw new \RuntimeException(__('Only a posted entry can be reversed. An unposted one can simply be edited or deleted.'));
            }
            if ($entry->is_reversed) {
                throw new \RuntimeException(__('This entry has already been reversed.'));
            }
            if ($entry->reversed_entry_id) {
                throw new \RuntimeException(__('This entry is itself a reversal. Post a new entry instead.'));
            }
            if ($entry->is_system_generated && !$bySystem) {
                throw new \RuntimeException(__('This entry was posted from another screen (:source). Correct it there, so the record and the books stay together.', ['source' => $entry->reference_type ?: __('a module')]));
            }

            $date = Carbon::parse($date)->toDateString();
            if ($date < $entry->entry_date->toDateString()) {
                throw new \RuntimeException(__('A reversal cannot be dated before the entry it reverses (:date).', ['date' => $entry->entry_date->format('j F Y')]));
            }

            $description = __('Reversal') . ': ' . $entry->description . ($reason ? ' — ' . $reason : '');
            $reversal = $this->newEntry($date, $entry->journal_type, $description, 'journal_entry_reversal', $entry->id,
                (float) $entry->total_debit, $userId, $entry->entry_number);
            $reversal->reversed_entry_id = $entry->id;
            $reversal->is_system_generated = $bySystem;
            $reversal->save();

            $n = 1;
            foreach ($entry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversal->id,
                    'line_number' => $n++,
                    'account_id' => $line->account_id,
                    'description' => __('Reversal') . ': ' . $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                ]);
            }

            $reversal->load('lines');
            $reversal->post($userId);
            $entry->forceFill(['is_reversed' => true])->save();

            return $reversal;
        });
    }

    public function previewReverse(JournalEntry $entry, string $date, ?int $userId): array
    {
        $p = $this->blank();
        $p['summary'] = [
            [__('Entry'), $entry->entry_number . ' · ' . $entry->entry_date->format('j F Y')],
            [__('Description'), $entry->description],
            [__('Amount'), format_amount($entry->total_debit)],
            [__('Reversal dated'), Carbon::parse($date)->format('j F Y')],
        ];

        $this->dryRun($p, function () use ($entry, $date, $userId, &$p) {
            $reversal = $this->reverseEntry($entry, $date, $userId);
            $p['entries'][] = $this->describe($reversal->fresh('lines'), __('Reversing entry to be posted'));
        });

        $p['notes'][] = __('The original entry stays in the books, marked as reversed; the reversing entry cancels it from its own date. Nothing is deleted.');

        return $p;
    }

    /* ===================================================================
     |  Helpers
     |===================================================================*/

    protected function entriesIn(AccountingPeriod $period)
    {
        return JournalEntry::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($period) {
                $q->where('accounting_period_id', $period->id)
                    ->orWhere(fn ($q) => $q->whereDate('entry_date', '>=', $period->start_date)
                        ->whereDate('entry_date', '<=', $period->end_date));
            });
    }

    protected function monthSummary(AccountingPeriod $period): array
    {
        $ids = $this->entriesIn($period)->where('is_posted', true)->pluck('id');
        $byClass = DB::table('journal_entry_lines as l')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('l.journal_entry_id', $ids)
            ->selectRaw('LEFT(a.account_code, 1) as cls, SUM(l.debit) as d, SUM(l.credit) as c')
            ->groupBy('cls')->get()->keyBy('cls');

        $income = (float) (($byClass['7']->c ?? 0) - ($byClass['7']->d ?? 0));
        $expenses = (float) (($byClass['6']->d ?? 0) - ($byClass['6']->c ?? 0));
        $cash = (float) (($byClass['5']->d ?? 0) - ($byClass['5']->c ?? 0));

        return [
            [__('Month'), $period->name . ' (' . Carbon::parse($period->start_date)->format('j M') . ' – ' . Carbon::parse($period->end_date)->format('j M Y') . ')'],
            [__('Posted entries'), number_format($ids->count())],
            [__('Income (class 7)'), format_amount($income)],
            [__('Expenses (class 6)'), format_amount($expenses)],
            [__('Result for the month'), format_amount($income - $expenses)],
            [__('Net cash and bank movement (class 5)'), format_amount($cash)],
        ];
    }

    /** Things worth checking before a month is locked; none of them blocks it. */
    protected function monthWarnings(AccountingPeriod $period): array
    {
        $from = Carbon::parse($period->start_date)->toDateString();
        $to = Carbon::parse($period->end_date)->toDateString();
        $warnings = [];

        $unmapped = function (string $table, string $type, string $dateColumn, array $where = []) use ($from, $to) {
            return DB::table($table . ' as t')
                ->whereDate('t.' . $dateColumn, '>=', $from)->whereDate('t.' . $dateColumn, '<=', $to)
                ->where($where)
                ->whereNotExists(fn ($q) => $q->from('transaction_mappings as tm')
                    ->whereColumn('tm.transaction_id', 't.id')->where('tm.transaction_type', $type)->where('tm.status', 'active'))
                ->count();
        };

        $expenses = $unmapped('expenses', 'expense', 'date', ['t.status' => 1]);
        $incomes = $unmapped('incomes', 'income', 'date', ['t.status' => 1]);
        if ($expenses + $incomes > 0) {
            $warnings[] = __(':expenses expense(s) and :incomes income(s) dated in this month are not in the ledger. See Ledger health before closing.', ['expenses' => $expenses, 'incomes' => $incomes]);
        }

        $drafts = DB::table('supplier_bills')->where('status', 'draft')
            ->whereDate('bill_date', '>=', $from)->whereDate('bill_date', '<=', $to)->count();
        if ($drafts > 0) {
            $warnings[] = __(':count supplier bill(s) dated in this month are still drafts and not in the books.', ['count' => $drafts]);
        }

        $bankAccounts = ChartOfAccount::where('account_code', 'like', '52%')->where('account_category', 'detail')->pluck('id');
        $used = DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->whereIn('l.account_id', $bankAccounts)->where('e.is_posted', 1)
            ->whereDate('e.entry_date', '>=', $from)->whereDate('e.entry_date', '<=', $to)->distinct()->pluck('l.account_id');
        $reconciled = DB::table('bank_reconciliations')->whereNull('deleted_at')->whereIn('chart_of_account_id', $used)
            ->whereIn('status', ['completed', 'approved'])->whereDate('statement_date', '>=', $to)->distinct()->pluck('chart_of_account_id');
        $missing = $used->diff($reconciled);
        if ($missing->isNotEmpty()) {
            $warnings[] = __('No completed bank reconciliation to the end of the month for: :accounts.', [
                'accounts' => ChartOfAccount::whereIn('id', $missing)->get()->map(fn ($a) => $a->account_code . ' ' . $a->account_name)->implode(', '),
            ]);
        }

        return $warnings;
    }

    protected function blank(): array
    {
        return ['ok' => true, 'problems' => [], 'warnings' => [], 'summary' => [], 'entries' => [], 'effects' => [], 'notes' => []];
    }

    /** Run $action for real and roll it back; a refusal becomes a problem. */
    protected function dryRun(array &$p, callable $action): void
    {
        DB::beginTransaction();
        try {
            $action();
        } catch (\RuntimeException $e) {
            $p['ok'] = false;
            $p['problems'][] = $e->getMessage();
            $p['entries'] = [];
        } catch (\Throwable $e) {
            $p['ok'] = false;
            $p['problems'][] = $e->getMessage();
            $p['entries'] = [];
        } finally {
            DB::rollBack();
        }
    }

    protected function describe(JournalEntry $entry, string $title): array
    {
        $accounts = ChartOfAccount::whereIn('id', $entry->lines->pluck('account_id'))->get()->keyBy('id');
        $period = $entry->accounting_period_id ? AccountingPeriod::find($entry->accounting_period_id) : null;

        return [
            'title' => $title,
            'date' => $entry->entry_date->format('j F Y'),
            'period' => $period->name ?? null,
            'description' => $entry->description,
            'lines' => $entry->lines->map(fn ($l) => [
                'account' => optional($accounts->get($l->account_id))->account_code . ' ' . optional($accounts->get($l->account_id))->account_name,
                'description' => $l->description,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
            'debit' => (float) $entry->lines->sum('debit'),
            'credit' => (float) $entry->lines->sum('credit'),
        ];
    }
}
