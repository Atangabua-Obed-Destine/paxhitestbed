<?php

namespace App\Services;

use App\Models\DefaultAccountMapping;
use App\Models\TransactionMapping;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionAutoMapService
{
    /**
     * Auto-map a transaction using default mappings
     *
     * @param string $transactionType (fee, income, expense, payroll)
     * @param int $transactionId
     * @param int|null $categoryId
     * @param array $transactionData ['amount', 'date', 'description']
     * @return bool|TransactionMapping
     */
    public function autoMap($transactionType, $transactionId, $categoryId, $transactionData)
    {
        try {
            DB::beginTransaction();

            // Check if already mapped (only an ACTIVE mapping blocks re-mapping;
            // a reversed one means the transaction can be mapped afresh)
            $existingMapping = TransactionMapping::where('transaction_type', $transactionType)
                ->where('transaction_id', $transactionId)
                ->where('status', 'active')
                ->first();

            if ($existingMapping) {
                Log::info("Transaction already mapped: {$transactionType}:{$transactionId}");
                DB::rollBack();
                return false;
            }

            // Find default mapping
            $mappingType = $this->getMappingType($transactionType);
            $defaultMapping = DefaultAccountMapping::where('mapping_type', $mappingType)
                ->where(function($query) use ($categoryId) {
                    if ($categoryId) {
                        $query->where('category_id', $categoryId);
                    } else {
                        $query->whereNull('category_id');
                    }
                })
                ->where('status', 'active')
                ->first();

            if (!$defaultMapping) {
                Log::warning("No default mapping found for {$transactionType} with category {$categoryId}");
                DB::rollBack();
                // Surface the gap (don't silently swallow). Safe in any context.
                if (!app()->runningInConsole()) {
                    try {
                        \Flasher\Laravel\Facade\Flasher::addWarning(
                            'Recorded, but not posted to the ledger: no account mapping is configured for this category. Set it under Accounting → Transaction Mappings.'
                        );
                    } catch (\Throwable $e) {
                        // no-op: flashing is best-effort
                    }
                }
                return false;
            }

            // Create (or re-activate, after a prior reversal) the transaction mapping.
            // The (transaction_type, transaction_id) pair is unique, so re-mapping must
            // reuse the same row rather than insert a second one.
            $mapping = TransactionMapping::updateOrCreate(
                [
                    'transaction_type' => $transactionType,
                    'transaction_id' => $transactionId,
                ],
                [
                    'debit_account_id' => $defaultMapping->debit_account_id,
                    'credit_account_id' => $defaultMapping->credit_account_id,
                    'amount' => $transactionData['amount'],
                    'transaction_date' => $transactionData['date'],
                    'description' => $defaultMapping->description ?? $transactionData['description'],
                    'mapped_at' => now(),
                    'mapped_by' => Auth::id() ?? 1, // Use system user if no auth
                    'status' => 'active',
                ]
            );

            // Create journal entry
            $journalEntry = $this->createJournalEntry($mapping, $transactionData);

            // Update mapping with journal entry id
            $mapping->journal_entry_id = $journalEntry->id;
            $mapping->save();

            DB::commit();

            Log::info("Transaction auto-mapped successfully: {$transactionType}:{$transactionId} -> JE:{$journalEntry->id}");

            return $mapping;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Auto-mapping failed for {$transactionType}:{$transactionId} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reverse a previously auto-mapped transaction: post a reversal journal entry
     * (debit/credit swapped) and mark the mapping 'reversed'. Used when the source
     * record is unpaid/edited/deleted so the GL stays in sync. Idempotent: a
     * transaction with no active mapping is a no-op.
     */
    public function reverse($transactionType, $transactionId, $userId = null)
    {
        $userId = $userId ?? (Auth::id() ?? 1);

        $mapping = TransactionMapping::where('transaction_type', $transactionType)
            ->where('transaction_id', $transactionId)
            ->where('status', 'active')
            ->first();

        if (!$mapping) {
            return false;
        }

        try {
            return DB::transaction(function () use ($mapping, $transactionType, $transactionId, $userId) {
                $original = $mapping->journal_entry_id
                    ? JournalEntry::with('lines')->find($mapping->journal_entry_id)
                    : null;

                if ($original) {
                    $reversal = JournalEntry::create([
                        'entry_number'        => $this->generateEntryNumber(),
                        'entry_date'          => now()->toDateString(),
                        'fiscal_year_id'      => $original->fiscal_year_id,
                        'accounting_period_id'=> $original->accounting_period_id,
                        'journal_type'        => 'general',
                        'description'         => 'Reversal - ' . ($original->description ?? ($transactionType . ' #' . $transactionId)),
                        'reference_type'      => $transactionType . '_reversal',
                        'reference_id'        => $transactionId,
                        'total_debit'         => $original->total_credit,
                        'total_credit'        => $original->total_debit,
                        'is_posted'           => true,
                        'is_system_generated' => true,
                        'reversed_entry_id'   => $original->id,
                        'posted_by'           => $userId,
                        'posted_at'           => now(),
                        'created_by'          => $userId,
                    ]);

                    $ln = 0;
                    foreach ($original->lines as $line) {
                        JournalEntryLine::create([
                            'journal_entry_id' => $reversal->id,
                            'line_number'      => ++$ln,
                            'account_id'       => $line->account_id,
                            'description'      => 'Reversal - ' . ($line->description ?? ''),
                            'debit'            => $line->credit, // swapped
                            'credit'           => $line->debit,  // swapped
                        ]);
                    }

                    $original->is_reversed = true;
                    $original->save();
                }

                $mapping->status = 'reversed';
                $mapping->save();

                Log::info("Transaction reversed: {$transactionType}:{$transactionId}");
                return true;
            });
        } catch (\Exception $e) {
            Log::error("Reversal failed for {$transactionType}:{$transactionId} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Re-map a transaction after its amount/category changed: reverse the existing
     * posting then create a fresh one reflecting the new values.
     */
    public function remap($transactionType, $transactionId, $categoryId, $transactionData, $userId = null)
    {
        $this->reverse($transactionType, $transactionId, $userId);
        return $this->autoMap($transactionType, $transactionId, $categoryId, $transactionData);
    }

    /**
     * Create journal entry from mapping
     */
    private function createJournalEntry($mapping, $transactionData)
    {
        // Get active fiscal year and period
        $fiscalYear = FiscalYear::where('is_active', true)->first();
        $accountingPeriod = AccountingPeriod::where('is_closed', false)
            ->where('fiscal_year_id', $fiscalYear->id ?? null)
            ->where('start_date', '<=', $mapping->transaction_date)
            ->where('end_date', '>=', $mapping->transaction_date)
            ->first();

        // Generate entry number
        $entryNumber = $this->generateEntryNumber();

        // Create journal entry
        $journalEntry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $mapping->transaction_date,
            'fiscal_year_id' => $fiscalYear->id ?? null,
            'accounting_period_id' => $accountingPeriod->id ?? null,
            'journal_type' => 'general',
            'description' => $mapping->description ?? $transactionData['description'],
            'reference_type' => $mapping->transaction_type,
            'reference_id' => $mapping->transaction_id,
            'total_debit' => $mapping->amount,
            'total_credit' => $mapping->amount,
            'is_posted' => false,
            'is_system_generated' => true,
            'created_by' => Auth::id() ?? 1,
        ]);

        // Create debit line
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'line_number' => 1,
            'account_id' => $mapping->debit_account_id,
            'description' => $mapping->description ?? $transactionData['description'],
            'debit' => $mapping->amount,
            'credit' => 0,
        ]);

        // Create credit line
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'line_number' => 2,
            'account_id' => $mapping->credit_account_id,
            'description' => $mapping->description ?? $transactionData['description'],
            'debit' => 0,
            'credit' => $mapping->amount,
        ]);

        // Post the journal entry
        $journalEntry->is_posted = true;
        $journalEntry->posted_by = Auth::id() ?? 1;
        $journalEntry->posted_at = now();
        $journalEntry->save();

        return $journalEntry;
    }

    /**
     * Generate entry number
     */
    private function generateEntryNumber()
    {
        $lastEntry = JournalEntry::orderBy('id', 'desc')->first();
        $lastNumber = $lastEntry ? intval(substr($lastEntry->entry_number, 3)) : 0;
        return 'JE-' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get mapping type from transaction type
     */
    private function getMappingType($transactionType)
    {
        switch ($transactionType) {
            case 'fee':
            case 'payment_plan_payment': // Payment plan payments use same mapping as fees
                return 'fee_category';
            case 'income':
                return 'income_category';
            case 'expense':
                return 'expense_category';
            case 'payroll':
                return 'payroll';
            default:
                return null;
        }
    }
}
