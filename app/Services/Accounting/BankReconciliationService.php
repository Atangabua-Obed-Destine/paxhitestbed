<?php

namespace App\Services\Accounting;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bank reconciliation: does the bank statement agree with the books?
 *
 * The accountant picks a bank or cash account (class 5) and enters the
 * statement's date and closing balance. Every posted movement on that account
 * up to the statement date that an earlier reconciliation has not already
 * matched is listed. The accountant ticks the ones that appear on the
 * statement. What is left unticked is timing:
 *
 *   deposits in transit   money in the books, not yet on the statement
 *   outstanding payments  money out in the books, not yet on the statement
 *
 *   statement balance + deposits in transit − outstanding payments
 *     = the balance the books should show            (adjusted bank balance)
 *
 * When that equals the ledger balance at the statement date, the difference is
 * nil and the reconciliation can be completed. Bank charges or interest on the
 * statement that the books do not have yet are posted from here (Dr charges /
 * Cr bank, or Dr bank / Cr interest), through the review dialog, and appear
 * ticked straight away.
 *
 * A completed reconciliation is the starting point of the next one: its ticked
 * movements are never offered again, and its unticked ones are.
 *
 * This replaces an earlier service written against columns the table never
 * had (bank_account_id, statement_ending_balance, fiscal_year_id), which made
 * every reconciliation fail on save.
 */
class BankReconciliationService
{
    use Concerns\PostsJournalEntries;

    /** Bank, cash and mobile-money accounts that can be reconciled. */
    public function accounts(): Collection
    {
        return ChartOfAccount::where('account_code', 'like', '5%')
            ->where('account_code', 'not like', '59%')
            ->where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
    }

    /** The account's ledger balance (debit − credit) at the end of $asOf. */
    public function bookBalance(int $accountId, string $asOf): float
    {
        $account = ChartOfAccount::find($accountId);
        $opening = $account ? (float) $account->opening_balance * ($account->normal_balance === 'credit' ? -1 : 1) : 0.0;

        return round($opening + (float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->whereDate('e.entry_date', '<=', $asOf)
            ->sum(DB::raw('l.debit - l.credit')), 2);
    }

    /** The last completed reconciliation of an account. */
    public function lastCompleted(int $accountId): ?BankReconciliation
    {
        return BankReconciliation::where('chart_of_account_id', $accountId)
            ->whereIn('status', [BankReconciliation::STATUS_COMPLETED, BankReconciliation::STATUS_APPROVED])
            ->orderByDesc('statement_date')->orderByDesc('id')
            ->first();
    }

    /**
     * Start a reconciliation.
     *
     * @param array{chart_of_account_id:int, statement_date:string, statement_closing_balance:float,
     *              statement_opening_balance?:?float, statement_reference?:?string, notes?:?string} $data
     * @throws \RuntimeException
     */
    public function create(array $data, ?int $userId): BankReconciliation
    {
        return DB::transaction(function () use ($data, $userId) {
            $accountId = (int) $data['chart_of_account_id'];
            $date = Carbon::parse($data['statement_date'])->toDateString();

            if (!$this->accounts()->contains('id', $accountId)) {
                throw new \RuntimeException(__('Choose a bank, cash or mobile money account (class 5).'));
            }

            $open = BankReconciliation::where('chart_of_account_id', $accountId)
                ->whereIn('status', [BankReconciliation::STATUS_DRAFT, BankReconciliation::STATUS_IN_PROGRESS])
                ->lockForUpdate()->first();
            if ($open) {
                throw new \RuntimeException(__('A reconciliation of this account is already in progress (statement of :date). Finish or delete it first.',
                    ['date' => $open->statement_date->format('j F Y')]));
            }

            $last = $this->lastCompleted($accountId);
            if ($last && $date <= $last->statement_date->toDateString()) {
                throw new \RuntimeException(__('This account is already reconciled to :date. Use a later statement.',
                    ['date' => $last->statement_date->format('j F Y')]));
            }

            $rec = BankReconciliation::create([
                'payment_account_id' => null,
                'chart_of_account_id' => $accountId,
                'statement_date' => $date,
                'reconciliation_date' => now()->toDateString(),
                'statement_reference' => $data['statement_reference'] ?? null,
                'statement_opening_balance' => $data['statement_opening_balance'] ?? ($last ? (float) $last->statement_closing_balance : 0),
                'statement_closing_balance' => round((float) $data['statement_closing_balance'], 2),
                'book_balance' => $this->bookBalance($accountId, $date),
                'status' => BankReconciliation::STATUS_IN_PROGRESS,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($this->unmatchedLines($accountId, $date) as $line) {
                $in = (float) $line->debit > 0;
                BankReconciliationItem::create([
                    'bank_reconciliation_id' => $rec->id,
                    'item_type' => $in ? 'deposit_in_transit' : 'outstanding_check',
                    'reference_number' => $line->entry_number,
                    'transaction_date' => $line->entry_date,
                    'description' => mb_substr((string) ($line->description ?: $line->entry_description), 0, 190),
                    'amount' => round($in ? (float) $line->debit : (float) $line->credit, 2),
                    'effect' => $in ? 'add' : 'subtract',
                    'is_cleared' => false,
                    'journal_entry_id' => $line->journal_entry_id,
                ]);
            }

            return $this->recalculate($rec);
        });
    }

    /**
     * Posted lines on the account up to $date not yet matched by a completed
     * reconciliation. Matched means ticked on one: the same entry, the same
     * direction and amount.
     */
    public function unmatchedLines(int $accountId, string $date): Collection
    {
        $matched = DB::table('bank_reconciliation_items as i')
            ->join('bank_reconciliations as r', 'r.id', '=', 'i.bank_reconciliation_id')
            ->where('r.chart_of_account_id', $accountId)
            ->whereIn('r.status', [BankReconciliation::STATUS_COMPLETED, BankReconciliation::STATUS_APPROVED])
            ->whereNull('r.deleted_at')
            ->where('i.is_cleared', 1)
            ->whereNotNull('i.journal_entry_id')
            ->get(['i.journal_entry_id', 'i.effect', 'i.amount'])
            ->map(fn ($i) => $i->journal_entry_id . '|' . $i->effect . '|' . number_format((float) $i->amount, 2, '.', ''))
            ->countBy()->all();

        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->whereDate('e.entry_date', '<=', $date)
            ->orderBy('e.entry_date')->orderBy('l.id')
            ->get(['l.id', 'l.journal_entry_id', 'l.debit', 'l.credit', 'l.description',
                'e.entry_number', 'e.entry_date', 'e.description as entry_description']);

        return $lines->filter(function ($line) use (&$matched) {
            $in = (float) $line->debit > 0;
            $key = $line->journal_entry_id . '|' . ($in ? 'add' : 'subtract') . '|'
                . number_format($in ? (float) $line->debit : (float) $line->credit, 2, '.', '');

            if (($matched[$key] ?? 0) > 0) {
                $matched[$key]--;

                return false;
            }

            return ((float) $line->debit + (float) $line->credit) > 0;
        })->values();
    }

    /** Tick or untick movements that appear on the statement. */
    public function setCleared(BankReconciliation $rec, array $itemIds, bool $cleared): BankReconciliation
    {
        $this->assertEditable($rec);

        $rec->items()->whereIn('id', $itemIds)->update([
            'is_cleared' => $cleared,
            'cleared_date' => $cleared ? $rec->statement_date->toDateString() : null,
        ]);

        return $this->recalculate($rec);
    }

    /** Set exactly which movements are ticked. */
    public function saveTicks(BankReconciliation $rec, array $clearedIds): BankReconciliation
    {
        $this->assertEditable($rec);

        $rec->items()->update(['is_cleared' => false, 'cleared_date' => null]);
        $rec->items()->whereIn('id', $clearedIds)->update(['is_cleared' => true, 'cleared_date' => $rec->statement_date->toDateString()]);

        return $this->recalculate($rec);
    }

    public function updateStatement(BankReconciliation $rec, float $closing, ?string $reference = null): BankReconciliation
    {
        $this->assertEditable($rec);
        $rec->statement_closing_balance = round($closing, 2);
        if ($reference !== null) {
            $rec->statement_reference = $reference;
        }
        $rec->save();

        return $this->recalculate($rec);
    }

    /** The reconciliation's figures. */
    public function position(BankReconciliation $rec): array
    {
        $items = $rec->items()->get();
        $inTransit = round((float) $items->where('is_cleared', false)->where('effect', 'add')->sum('amount'), 2);
        $outstanding = round((float) $items->where('is_cleared', false)->where('effect', 'subtract')->sum('amount'), 2);
        $book = (float) $rec->book_balance;
        $statement = (float) $rec->statement_closing_balance;
        $adjusted = round($statement + $inTransit - $outstanding, 2);

        return [
            'book' => $book,
            'statement' => $statement,
            'in_transit' => $inTransit,
            'outstanding' => $outstanding,
            'adjusted' => $adjusted,
            'difference' => round($book - $adjusted, 2),
            'ticked' => $items->where('is_cleared', true)->count(),
            'unticked' => $items->where('is_cleared', false)->count(),
        ];
    }

    public function recalculate(BankReconciliation $rec): BankReconciliation
    {
        $rec->book_balance = $this->bookBalance((int) $rec->chart_of_account_id, $rec->statement_date->toDateString());
        $rec->save();

        $p = $this->position($rec);
        $rec->forceFill([
            'outstanding_deposits' => $p['in_transit'],
            'outstanding_checks' => $p['outstanding'],
            'bank_charges' => round((float) $rec->items()->where('item_type', 'bank_charge')->sum('amount'), 2),
            'bank_interest' => round((float) $rec->items()->where('item_type', 'bank_interest')->sum('amount'), 2),
            'adjusted_book_balance' => $p['adjusted'],
            'difference' => $p['difference'],
        ])->save();

        return $rec->fresh();
    }

    /**
     * Post a bank charge or interest shown on the statement but not in the books.
     *
     * Filed under an expense category (charges) or income category (interest),
     * like every other expense and income: the category's mapping gives the
     * ledger account and the budget line, so the budget sheet counts it too.
     *
     * @param array{kind:string, amount:float, date:string, category_id:int, description?:?string} $data
     * @throws \RuntimeException
     */
    public function addAdjustment(BankReconciliation $rec, array $data, ?int $userId): BankReconciliationItem
    {
        return DB::transaction(function () use ($rec, $data, $userId) {
            $this->assertEditable($rec);

            $kind = $data['kind'];
            $amount = round((float) $data['amount'], 2);
            $date = Carbon::parse($data['date'] ?? $rec->statement_date)->toDateString();
            $categoryId = (int) ($data['category_id'] ?? 0);
            $mapping = DB::table('default_account_mappings')
                ->where('mapping_type', $kind === 'bank_charge' ? 'expense_category' : 'income_category')
                ->where('category_id', $categoryId)->where('status', 'active')->first();
            $other = $mapping ? ChartOfAccount::find((int) ($kind === 'bank_charge' ? $mapping->debit_account_id : $mapping->credit_account_id)) : null;

            if (!in_array($kind, ['bank_charge', 'bank_interest'], true)) {
                throw new \RuntimeException(__('Choose bank charges or bank interest.'));
            }
            if ($amount <= 0) {
                throw new \RuntimeException(__('The amount must be more than zero.'));
            }
            if ($date > $rec->statement_date->toDateString()) {
                throw new \RuntimeException(__('The date must be on or before the statement date.'));
            }
            if (!$other || $other->account_category !== 'detail') {
                throw new \RuntimeException(__('Choose a category that is mapped to a ledger account (Accounting → Transaction mappings).'));
            }
            if ($kind === 'bank_charge' && !str_starts_with($other->account_code, '6')) {
                throw new \RuntimeException(__('Bank charges go to an expense account (class 6).'));
            }
            if ($kind === 'bank_interest' && !str_starts_with($other->account_code, '7')) {
                throw new \RuntimeException(__('Bank interest goes to an income account (class 7).'));
            }

            $bank = (int) $rec->chart_of_account_id;
            $description = trim(($kind === 'bank_charge' ? __('Bank charges') : __('Bank interest'))
                . ' — ' . __('statement of :date', ['date' => $rec->statement_date->format('j F Y')])
                . (!empty($data['description']) ? ' — ' . $data['description'] : ''));

            $entry = $this->newEntry($date, 'bank', $description, 'bank_reconciliation', $rec->id, $amount, $userId, $rec->statement_reference);
            [$debit, $credit] = $kind === 'bank_charge' ? [$other->id, $bank] : [$bank, $other->id];
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 1, 'account_id' => $debit,
                'description' => $description, 'debit' => $amount, 'credit' => 0]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 2, 'account_id' => $credit,
                'description' => $description, 'debit' => 0, 'credit' => $amount]);
            $entry->load('lines');
            $entry->post($userId);

            // It is on the statement, so it is matched from the start.
            $item = BankReconciliationItem::create([
                'bank_reconciliation_id' => $rec->id,
                'item_type' => $kind,
                'category_id' => $categoryId,
                'reference_number' => $entry->entry_number,
                'transaction_date' => $date,
                'description' => mb_substr($description, 0, 190),
                'amount' => $amount,
                'effect' => $kind === 'bank_charge' ? 'subtract' : 'add',
                'is_cleared' => true,
                'cleared_date' => $rec->statement_date->toDateString(),
                'journal_entry_id' => $entry->id,
            ]);

            $this->recalculate($rec);

            return $item;
        });
    }

    /** @throws \RuntimeException unless the difference is nil */
    public function complete(BankReconciliation $rec, ?int $userId): BankReconciliation
    {
        return DB::transaction(function () use ($rec, $userId) {
            $this->assertEditable($rec);
            $rec = $this->recalculate($rec);

            if (abs((float) $rec->difference) >= 0.01) {
                throw new \RuntimeException(__('The books and the statement differ by :amount. Tick the movements on the statement, post any charges or interest, and check the statement balance.',
                    ['amount' => format_amount($rec->difference)]));
            }

            $rec->forceFill([
                'status' => BankReconciliation::STATUS_COMPLETED,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ])->save();

            return $rec;
        });
    }

    /** Only one still in progress can be deleted; a completed one is history. */
    public function delete(BankReconciliation $rec): void
    {
        $this->assertEditable($rec);

        DB::transaction(function () use ($rec) {
            $rec->items()->delete();
            $rec->delete();
        });
    }

    /**
     * Posted lines of bank charges and interest, on their expense or income
     * account (not the bank side), with the category's budget line: what the
     * budget sheet and the daybook count. Built from the ledger, so the sheet
     * and the ledger agree by construction.
     */
    public static function budgetLedgerLines(?string $from = null, ?string $to = null): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_entry_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jl.account_id')
            ->join('bank_reconciliation_items as i', 'i.journal_entry_id', '=', 'je.id')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'i.category_id')
                    ->whereRaw("m.mapping_type = IF(i.item_type = 'bank_charge', 'expense_category', 'income_category')");
            })
            ->where('je.reference_type', 'bank_reconciliation')
            ->whereIn('i.item_type', ['bank_charge', 'bank_interest'])
            ->where('je.is_posted', 1)
            ->whereNull('je.deleted_at')
            ->where(fn ($q) => $q->where('a.account_code', 'like', '6%')->orWhere('a.account_code', 'like', '7%'))
            ->when($from, fn ($q) => $q->whereDate('je.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('je.entry_date', '<=', $to));
    }

    /* ---- previews --------------------------------------------------- */

    public function previewAdjustment(BankReconciliation $rec, array $data, ?int $userId): array
    {
        $p = ['ok' => true, 'problems' => [], 'warnings' => [], 'summary' => [], 'entries' => [], 'effects' => [], 'notes' => []];
        $before = $this->position($rec);

        DB::beginTransaction();
        try {
            $item = $this->addAdjustment($rec, $data, $userId);
            $entry = JournalEntry::with('lines')->find($item->journal_entry_id);
            $after = $this->position($rec->fresh());
            $p['entries'][] = $this->describe($entry, __('Ledger entry to be posted'));
            $p['effects'][] = [
                'title' => __('Effect on the reconciliation'),
                'columns' => ['', __('Before'), __('After')],
                'numeric' => [1, 2],
                'rows' => [
                    [__('Balance in the books'), format_amount($before['book']), format_amount($after['book'])],
                    [__('Adjusted bank balance'), format_amount($before['adjusted']), format_amount($after['adjusted'])],
                    [__('Difference'), format_amount($before['difference']), format_amount($after['difference'])],
                ],
            ];
        } catch (\Throwable $e) {
            $p['ok'] = false;
            $p['problems'][] = $e->getMessage();
        } finally {
            DB::rollBack();
        }

        return $p;
    }

    public function previewComplete(BankReconciliation $rec, ?int $userId): array
    {
        $p = ['ok' => true, 'problems' => [], 'warnings' => [], 'summary' => [], 'entries' => [], 'effects' => [], 'notes' => []];
        $pos = $this->position($rec);
        $account = ChartOfAccount::find($rec->chart_of_account_id);

        $p['summary'] = [
            [__('Account'), ($account->account_code ?? '') . ' ' . ($account->account_name ?? '')],
            [__('Statement date'), $rec->statement_date->format('j F Y')],
            [__('Statement balance'), format_amount($pos['statement'])],
            [__('+ Deposits in transit'), format_amount($pos['in_transit'])],
            [__('− Outstanding payments'), format_amount($pos['outstanding'])],
            [__('= Adjusted bank balance'), format_amount($pos['adjusted'])],
            [__('Balance in the books'), format_amount($pos['book'])],
            [__('Difference'), format_amount($pos['difference'])],
        ];

        DB::beginTransaction();
        try {
            $this->complete($rec, $userId);
        } catch (\Throwable $e) {
            $p['ok'] = false;
            $p['problems'][] = $e->getMessage();
        } finally {
            DB::rollBack();
        }

        if ($pos['unticked'] > 0) {
            $p['notes'][] = trans_choice('{1} One movement is not on this statement; it is carried to the next reconciliation.|[2,*] :count movements are not on this statement; they are carried to the next reconciliation.', $pos['unticked'], ['count' => $pos['unticked']]);
        }
        $p['notes'][] = __('Completing locks this reconciliation. The ticked movements will not be offered again.');

        return $p;
    }

    /* ---- helpers ------------------------------------------------------ */

    protected function assertEditable(BankReconciliation $rec): void
    {
        if (!in_array($rec->status, [BankReconciliation::STATUS_DRAFT, BankReconciliation::STATUS_IN_PROGRESS], true)) {
            throw new \RuntimeException(__('This reconciliation is completed and can no longer be changed.'));
        }
    }

    protected function describe(JournalEntry $entry, string $title): array
    {
        $accounts = ChartOfAccount::whereIn('id', $entry->lines->pluck('account_id'))->get()->keyBy('id');

        return [
            'title' => $title,
            'date' => $entry->entry_date->format('j F Y'),
            'period' => optional($entry->accountingPeriod)->name,
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
