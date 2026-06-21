<?php

namespace App\Services\Accounting;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class BankReconciliationService
{
    /**
     * Create a new bank reconciliation
     *
     * @param array $data
     * @return BankReconciliation
     */
    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            // Get previous reconciliation to calculate beginning balance
            $previousReconciliation = BankReconciliation::where('bank_account_id', $data['bank_account_id'])
                ->where('status', BankReconciliation::STATUS_COMPLETED)
                ->orderBy('statement_date', 'desc')
                ->first();

            $beginningBalance = $previousReconciliation 
                ? $previousReconciliation->statement_ending_balance 
                : $this->calculateAccountBalance($data['bank_account_id'], Carbon::parse($data['statement_date'])->startOfMonth()->subDay());

            $reconciliation = BankReconciliation::create([
                'bank_account_id' => $data['bank_account_id'],
                'statement_date' => $data['statement_date'],
                'statement_beginning_balance' => $data['statement_beginning_balance'] ?? $beginningBalance,
                'statement_ending_balance' => $data['statement_ending_balance'],
                'fiscal_year_id' => $data['fiscal_year_id'] ?? FiscalYear::getActiveFiscalYear()?->id,
                'accounting_period_id' => $data['accounting_period_id'] ?? $this->getAccountingPeriod($data['statement_date']),
                'status' => BankReconciliation::STATUS_IN_PROGRESS,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Load outstanding items from previous reconciliation
            $this->loadOutstandingItems($reconciliation, $previousReconciliation);

            // Load new transactions
            $this->loadNewTransactions($reconciliation);

            // Calculate initial balances
            $reconciliation->calculateBalances();

            DB::commit();
            return $reconciliation->fresh(['items', 'bankAccount']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Load outstanding items from previous reconciliation
     *
     * @param BankReconciliation $reconciliation
     * @param BankReconciliation|null $previousReconciliation
     */
    protected function loadOutstandingItems(BankReconciliation $reconciliation, ?BankReconciliation $previousReconciliation)
    {
        if (!$previousReconciliation) {
            return;
        }

        // Get items that were not cleared in previous reconciliation
        $outstandingItems = $previousReconciliation->items()
            ->where('is_cleared', false)
            ->get();

        foreach ($outstandingItems as $item) {
            BankReconciliationItem::create([
                'bank_reconciliation_id' => $reconciliation->id,
                'item_type' => $item->item_type,
                'reference_number' => $item->reference_number,
                'description' => $item->description,
                'amount' => $item->amount,
                'transaction_date' => $item->transaction_date,
                'journal_entry_id' => $item->journal_entry_id,
                'effect' => $item->effect,
                'is_cleared' => false,
            ]);
        }
    }

    /**
     * Load new transactions from the books
     *
     * @param BankReconciliation $reconciliation
     */
    protected function loadNewTransactions(BankReconciliation $reconciliation)
    {
        // Get the statement period
        $startDate = Carbon::parse($reconciliation->statement_date)->startOfMonth();
        $endDate = Carbon::parse($reconciliation->statement_date);

        // Get all journal entry lines for this bank account in the period
        $transactions = JournalEntryLine::where('account_id', $reconciliation->bank_account_id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->where('is_posted', true)
                  ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->with('journalEntry')
            ->get();

        foreach ($transactions as $transaction) {
            // Check if this transaction already exists as an item
            $exists = $reconciliation->items()
                ->where('journal_entry_id', $transaction->journal_entry_id)
                ->exists();

            if ($exists) {
                continue;
            }

            // Determine item type and effect
            $amount = $transaction->debit > 0 ? $transaction->debit : $transaction->credit;
            $effect = $transaction->debit > 0 
                ? BankReconciliationItem::EFFECT_ADD 
                : BankReconciliationItem::EFFECT_SUBTRACT;

            // Determine item type based on description or reference
            $itemType = $this->determineItemType($transaction);

            BankReconciliationItem::create([
                'bank_reconciliation_id' => $reconciliation->id,
                'item_type' => $itemType,
                'reference_number' => $transaction->journalEntry->reference_number,
                'description' => $transaction->description ?? $transaction->journalEntry->description,
                'amount' => $amount,
                'transaction_date' => $transaction->journalEntry->entry_date,
                'journal_entry_id' => $transaction->journal_entry_id,
                'effect' => $effect,
                'is_cleared' => false,
            ]);
        }
    }

    /**
     * Determine item type from transaction
     *
     * @param JournalEntryLine $transaction
     * @return string
     */
    protected function determineItemType(JournalEntryLine $transaction)
    {
        $description = strtolower($transaction->description ?? '');
        $reference = strtolower($transaction->journalEntry->reference_number ?? '');

        if (str_contains($description, 'check') || str_contains($reference, 'chk')) {
            return BankReconciliationItem::TYPE_OUTSTANDING_CHECK;
        }

        if (str_contains($description, 'deposit')) {
            return BankReconciliationItem::TYPE_DEPOSIT_IN_TRANSIT;
        }

        if (str_contains($description, 'fee') || str_contains($description, 'charge')) {
            return BankReconciliationItem::TYPE_BANK_CHARGE;
        }

        if (str_contains($description, 'interest')) {
            return BankReconciliationItem::TYPE_INTEREST_EARNED;
        }

        // Default based on debit/credit
        return $transaction->credit > 0 
            ? BankReconciliationItem::TYPE_OUTSTANDING_CHECK 
            : BankReconciliationItem::TYPE_DEPOSIT_IN_TRANSIT;
    }

    /**
     * Clear an item
     *
     * @param BankReconciliationItem $item
     * @param Carbon|string|null $clearedDate
     * @return BankReconciliationItem
     */
    public function clearItem(BankReconciliationItem $item, $clearedDate = null)
    {
        $item->clear($clearedDate);
        
        // Recalculate reconciliation balances
        $item->bankReconciliation->calculateBalances();

        return $item;
    }

    /**
     * Unclear an item
     *
     * @param BankReconciliationItem $item
     * @return BankReconciliationItem
     */
    public function unclearItem(BankReconciliationItem $item)
    {
        $item->update([
            'is_cleared' => false,
            'cleared_date' => null,
        ]);

        // Recalculate reconciliation balances
        $item->bankReconciliation->calculateBalances();

        return $item;
    }

    /**
     * Add a new adjustment item
     *
     * @param BankReconciliation $reconciliation
     * @param array $data
     * @return BankReconciliationItem
     */
    public function addAdjustment(BankReconciliation $reconciliation, array $data)
    {
        $item = BankReconciliationItem::create([
            'bank_reconciliation_id' => $reconciliation->id,
            'item_type' => $data['item_type'],
            'reference_number' => $data['reference_number'] ?? null,
            'description' => $data['description'],
            'amount' => abs($data['amount']),
            'transaction_date' => $data['transaction_date'] ?? now(),
            'effect' => $data['effect'],
            'is_cleared' => $data['is_cleared'] ?? true,
            'cleared_date' => $data['is_cleared'] ?? true ? now() : null,
        ]);

        // Recalculate balances
        $reconciliation->calculateBalances();

        return $item;
    }

    /**
     * Complete the reconciliation
     *
     * @param BankReconciliation $reconciliation
     * @param bool $createAdjustingEntries
     * @return BankReconciliation
     */
    public function complete(BankReconciliation $reconciliation, bool $createAdjustingEntries = true)
    {
        // Verify the reconciliation balances
        $reconciliation->calculateBalances();

        if (!$reconciliation->isReconciled()) {
            throw new Exception(__('reconciliation_does_not_balance'));
        }

        DB::beginTransaction();
        try {
            // Create adjusting entries for bank charges, interest, etc.
            if ($createAdjustingEntries) {
                $this->createAdjustingEntries($reconciliation);
            }

            // Complete the reconciliation
            $reconciliation->complete(auth()->id());

            DB::commit();
            return $reconciliation->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create adjusting entries for bank items
     *
     * @param BankReconciliation $reconciliation
     */
    protected function createAdjustingEntries(BankReconciliation $reconciliation)
    {
        // Get items that need journal entries (bank charges, interest, etc.)
        $adjustmentItems = $reconciliation->items()
            ->whereNull('journal_entry_id')
            ->whereIn('item_type', [
                BankReconciliationItem::TYPE_BANK_CHARGE,
                BankReconciliationItem::TYPE_INTEREST_EARNED,
                BankReconciliationItem::TYPE_NSF_CHECK,
                BankReconciliationItem::TYPE_OTHER,
            ])
            ->get();

        if ($adjustmentItems->isEmpty()) {
            return;
        }

        // Group by type
        $groupedItems = $adjustmentItems->groupBy('item_type');

        foreach ($groupedItems as $type => $items) {
            $journalEntry = $this->createJournalEntryForItems($reconciliation, $type, $items);
            
            // Update items with journal entry reference
            foreach ($items as $item) {
                $item->update(['journal_entry_id' => $journalEntry->id]);
            }
        }
    }

    /**
     * Create journal entry for adjustment items
     *
     * @param BankReconciliation $reconciliation
     * @param string $type
     * @param \Illuminate\Support\Collection $items
     * @return JournalEntry
     */
    protected function createJournalEntryForItems(BankReconciliation $reconciliation, string $type, $items)
    {
        $totalAmount = $items->sum('amount');
        $description = $this->getAdjustmentDescription($type);

        // Get expense/income account based on type
        $expenseAccountId = $this->getAdjustmentAccountId($type);

        $journalEntry = JournalEntry::create([
            'entry_date' => $reconciliation->statement_date,
            'reference_number' => 'BR-' . $reconciliation->id . '-' . strtoupper(substr($type, 0, 3)),
            'description' => $description . ' - ' . Carbon::parse($reconciliation->statement_date)->format('F Y'),
            'source_type' => 'bank_reconciliation',
            'source_id' => $reconciliation->id,
            'fiscal_year_id' => $reconciliation->fiscal_year_id,
            'accounting_period_id' => $reconciliation->accounting_period_id,
            'status' => 'posted',
            'created_by' => auth()->id(),
        ]);

        // Create lines based on type
        if (in_array($type, [BankReconciliationItem::TYPE_BANK_CHARGE, BankReconciliationItem::TYPE_NSF_CHECK])) {
            // Debit expense, Credit bank
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccountId,
                'debit' => $totalAmount,
                'credit' => 0,
                'description' => $description,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $reconciliation->bank_account_id,
                'debit' => 0,
                'credit' => $totalAmount,
                'description' => $description,
            ]);
        } elseif ($type === BankReconciliationItem::TYPE_INTEREST_EARNED) {
            // Debit bank, Credit income
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $reconciliation->bank_account_id,
                'debit' => $totalAmount,
                'credit' => 0,
                'description' => $description,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccountId,
                'debit' => 0,
                'credit' => $totalAmount,
                'description' => $description,
            ]);
        }

        return $journalEntry;
    }

    /**
     * Get adjustment description
     *
     * @param string $type
     * @return string
     */
    protected function getAdjustmentDescription(string $type)
    {
        $descriptions = [
            BankReconciliationItem::TYPE_BANK_CHARGE => __('bank_service_charges'),
            BankReconciliationItem::TYPE_INTEREST_EARNED => __('interest_income'),
            BankReconciliationItem::TYPE_NSF_CHECK => __('nsf_checks'),
            BankReconciliationItem::TYPE_OTHER => __('bank_adjustment'),
        ];

        return $descriptions[$type] ?? __('bank_adjustment');
    }

    /**
     * Get adjustment account ID
     *
     * @param string $type
     * @return int|null
     */
    protected function getAdjustmentAccountId(string $type)
    {
        switch ($type) {
            case BankReconciliationItem::TYPE_BANK_CHARGE:
                // Bank charges expense (OHADA Class 631)
                return ChartOfAccount::where('code', 'like', '631%')->first()?->id
                    ?? ChartOfAccount::where('code', 'like', '63%')->first()?->id;

            case BankReconciliationItem::TYPE_INTEREST_EARNED:
                // Interest income (OHADA Class 77)
                return ChartOfAccount::where('code', 'like', '77%')->first()?->id;

            case BankReconciliationItem::TYPE_NSF_CHECK:
                // Bad debt expense or bank charges
                return ChartOfAccount::where('code', 'like', '659%')->first()?->id
                    ?? ChartOfAccount::where('code', 'like', '631%')->first()?->id;

            default:
                // Use suspense account
                return \App\Models\AccountingSetting::getValue('suspense_account_id');
        }
    }

    /**
     * Calculate account balance as of date
     *
     * @param int $accountId
     * @param Carbon $asOfDate
     * @return float
     */
    protected function calculateAccountBalance($accountId, Carbon $asOfDate)
    {
        $debits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->sum('debit');

        $credits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->sum('credit');

        return $debits - $credits;
    }

    /**
     * Get accounting period for date
     *
     * @param string|Carbon $date
     * @return int|null
     */
    protected function getAccountingPeriod($date)
    {
        $date = Carbon::parse($date);
        
        return AccountingPeriod::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first()?->id;
    }

    /**
     * Get reconciliation summary
     *
     * @param int $bankAccountId
     * @return array
     */
    public function getSummary(int $bankAccountId)
    {
        $lastReconciliation = BankReconciliation::where('bank_account_id', $bankAccountId)
            ->where('status', BankReconciliation::STATUS_COMPLETED)
            ->orderBy('statement_date', 'desc')
            ->first();

        $inProgressReconciliation = BankReconciliation::where('bank_account_id', $bankAccountId)
            ->where('status', BankReconciliation::STATUS_IN_PROGRESS)
            ->first();

        $currentBalance = $this->calculateAccountBalance($bankAccountId, Carbon::now());

        $totalOutstanding = 0;
        if ($inProgressReconciliation) {
            $totalOutstanding = $inProgressReconciliation->items()
                ->where('is_cleared', false)
                ->sum('amount');
        }

        return [
            'current_book_balance' => $currentBalance,
            'last_reconciliation_date' => $lastReconciliation?->statement_date,
            'last_statement_balance' => $lastReconciliation?->statement_ending_balance,
            'in_progress' => $inProgressReconciliation !== null,
            'in_progress_id' => $inProgressReconciliation?->id,
            'total_outstanding_items' => $totalOutstanding,
        ];
    }

    /**
     * Delete a reconciliation
     *
     * @param BankReconciliation $reconciliation
     * @return bool
     */
    public function delete(BankReconciliation $reconciliation)
    {
        if ($reconciliation->status === BankReconciliation::STATUS_COMPLETED) {
            throw new Exception(__('cannot_delete_completed_reconciliation'));
        }

        DB::beginTransaction();
        try {
            // Delete items
            $reconciliation->items()->delete();
            
            // Delete reconciliation
            $reconciliation->delete();

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
