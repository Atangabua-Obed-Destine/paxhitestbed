<?php

namespace App\Services\Accounting;

use App\Models\RecurringJournalEntry;
use App\Models\JournalEntry;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class RecurringEntryService
{
    /**
     * Process all due recurring entries
     *
     * @param Carbon|string|null $date
     * @return array
     */
    public function processDueEntries($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();
        
        $results = [
            'processed' => 0,
            'created' => 0,
            'errors' => [],
            'entries' => [],
        ];

        $dueEntries = RecurringJournalEntry::dueToRun($date)->get();

        foreach ($dueEntries as $recurringEntry) {
            try {
                // Validate before generating
                $recurringEntry->validateLines();

                $journalEntry = $recurringEntry->generateJournalEntry($date);
                
                if ($journalEntry) {
                    $results['entries'][] = $journalEntry;
                    $results['created']++;
                }

                $results['processed']++;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'recurring_entry_id' => $recurringEntry->id,
                    'name' => $recurringEntry->name,
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Recurring entry processing error', [
                    'recurring_entry_id' => $recurringEntry->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Process a specific recurring entry
     *
     * @param RecurringJournalEntry $recurringEntry
     * @param Carbon|string|null $date
     * @return JournalEntry|null
     */
    public function processEntry(RecurringJournalEntry $recurringEntry, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();

        if (!$recurringEntry->shouldRun($date)) {
            return null;
        }

        $recurringEntry->validateLines();
        
        return $recurringEntry->generateJournalEntry($date);
    }

    /**
     * Create a recurring entry template
     *
     * @param array $data
     * @return RecurringJournalEntry
     */
    public function createTemplate(array $data)
    {
        DB::beginTransaction();
        try {
            // Calculate total amounts
            $totalDebit = 0;
            $totalCredit = 0;
            if (isset($data['lines'])) {
                foreach ($data['lines'] as $line) {
                    $totalDebit += $line['debit'] ?? 0;
                    $totalCredit += $line['credit'] ?? 0;
                }
            }

            // Create the recurring entry
            $recurringEntry = RecurringJournalEntry::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'journal_type' => $data['journal_type'] ?? 'general',
                'frequency' => $data['frequency'],
                'day_of_month' => $data['day_of_month'] ?? null,
                'day_of_week' => $data['day_of_week'] ?? null,
                'month_of_year' => $data['month_of_year'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'next_run_date' => $data['start_date'],
                'occurrences' => $data['occurrences'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'auto_post' => $data['auto_post'] ?? false,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => auth()->id(),
            ]);

            // Create lines
            if (isset($data['lines'])) {
                foreach ($data['lines'] as $index => $line) {
                    $recurringEntry->lines()->create([
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'description' => $line['description'] ?? null,
                        'order' => $index + 1,
                    ]);
                }
            }

            // Validate the template
            $recurringEntry->validateLines();

            DB::commit();
            return $recurringEntry;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a recurring entry template
     *
     * @param RecurringJournalEntry $recurringEntry
     * @param array $data
     * @return RecurringJournalEntry
     */
    public function updateTemplate(RecurringJournalEntry $recurringEntry, array $data)
    {
        DB::beginTransaction();
        try {
            // Calculate total amounts
            $totalDebit = 0;
            $totalCredit = 0;
            if (isset($data['lines'])) {
                foreach ($data['lines'] as $line) {
                    $totalDebit += $line['debit'] ?? 0;
                    $totalCredit += $line['credit'] ?? 0;
                }
            }

            // Update the recurring entry
            $recurringEntry->update([
                'name' => $data['name'] ?? $recurringEntry->name,
                'description' => $data['description'] ?? $recurringEntry->description,
                'journal_type' => $data['journal_type'] ?? $recurringEntry->journal_type,
                'frequency' => $data['frequency'] ?? $recurringEntry->frequency,
                'day_of_month' => $data['day_of_month'] ?? $recurringEntry->day_of_month,
                'day_of_week' => $data['day_of_week'] ?? $recurringEntry->day_of_week,
                'month_of_year' => $data['month_of_year'] ?? $recurringEntry->month_of_year,
                'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $recurringEntry->end_date,
                'occurrences' => array_key_exists('occurrences', $data) ? $data['occurrences'] : $recurringEntry->occurrences,
                'is_active' => $data['is_active'] ?? $recurringEntry->is_active,
                'auto_post' => $data['auto_post'] ?? $recurringEntry->auto_post,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'updated_by' => auth()->id(),
            ]);

            // Update lines if provided
            if (isset($data['lines'])) {
                // Delete existing lines
                $recurringEntry->lines()->delete();

                // Create new lines
                foreach ($data['lines'] as $index => $line) {
                    $recurringEntry->lines()->create([
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'description' => $line['description'] ?? null,
                        'order' => $index + 1,
                    ]);
                }
            }

            // Validate the template
            $recurringEntry->validateLines();

            DB::commit();
            return $recurringEntry->fresh(['lines']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Pause a recurring entry
     *
     * @param RecurringJournalEntry $recurringEntry
     * @return RecurringJournalEntry
     */
    public function pause(RecurringJournalEntry $recurringEntry)
    {
        $recurringEntry->update([
            'is_active' => false,
            'notes' => ($recurringEntry->notes ? $recurringEntry->notes . "\n" : '') . 
                       'Paused on: ' . now()->format('Y-m-d H:i:s'),
        ]);

        return $recurringEntry;
    }

    /**
     * Resume a recurring entry
     *
     * @param RecurringJournalEntry $recurringEntry
     * @param Carbon|string|null $newNextRunDate
     * @return RecurringJournalEntry
     */
    public function resume(RecurringJournalEntry $recurringEntry, $newNextRunDate = null)
    {
        $updateData = [
            'is_active' => true,
            'notes' => ($recurringEntry->notes ? $recurringEntry->notes . "\n" : '') . 
                       'Resumed on: ' . now()->format('Y-m-d H:i:s'),
        ];

        if ($newNextRunDate) {
            $updateData['next_run_date'] = Carbon::parse($newNextRunDate);
        }

        $recurringEntry->update($updateData);

        return $recurringEntry;
    }

    /**
     * Skip next occurrence
     *
     * @param RecurringJournalEntry $recurringEntry
     * @return RecurringJournalEntry
     */
    public function skipNext(RecurringJournalEntry $recurringEntry)
    {
        $newNextRunDate = $recurringEntry->calculateNextRunDate();
        
        $recurringEntry->update([
            'next_run_date' => $newNextRunDate,
            'notes' => ($recurringEntry->notes ? $recurringEntry->notes . "\n" : '') . 
                       'Skipped occurrence on: ' . now()->format('Y-m-d'),
        ]);

        return $recurringEntry;
    }

    /**
     * Get upcoming recurring entries
     *
     * @param int $days Number of days to look ahead
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUpcoming(int $days = 30)
    {
        $endDate = Carbon::now()->addDays($days);

        return RecurringJournalEntry::active()
            ->where('next_run_date', '<=', $endDate)
            ->orderBy('next_run_date')
            ->get();
    }

    /**
     * Get recurring entries summary
     *
     * @return array
     */
    public function getSummary()
    {
        return [
            'total' => RecurringJournalEntry::count(),
            'active' => RecurringJournalEntry::active()->count(),
            'inactive' => RecurringJournalEntry::where('is_active', false)->count(),
            'due_today' => RecurringJournalEntry::dueToRun(Carbon::today())->count(),
            'due_this_week' => RecurringJournalEntry::dueToRun(Carbon::now()->endOfWeek())->count(),
            'by_frequency' => RecurringJournalEntry::active()
                ->select('frequency', DB::raw('COUNT(*) as count'))
                ->groupBy('frequency')
                ->pluck('count', 'frequency')
                ->toArray(),
            'total_monthly_amount' => $this->calculateMonthlyTotal(),
        ];
    }

    /**
     * Calculate total monthly recurring amount
     *
     * @return float
     */
    protected function calculateMonthlyTotal()
    {
        $total = 0;
        $activeEntries = RecurringJournalEntry::active()->get();

        foreach ($activeEntries as $entry) {
            $monthlyMultiplier = $this->getMonthlyMultiplier($entry->frequency);
            $total += $entry->total_debit * $monthlyMultiplier;
        }

        return $total;
    }

    /**
     * Get monthly multiplier for frequency
     *
     * @param string $frequency
     * @return float
     */
    protected function getMonthlyMultiplier(string $frequency)
    {
        $multipliers = [
            RecurringJournalEntry::FREQUENCY_DAILY => 30,
            RecurringJournalEntry::FREQUENCY_WEEKLY => 4.33,
            RecurringJournalEntry::FREQUENCY_BIWEEKLY => 2.17,
            RecurringJournalEntry::FREQUENCY_MONTHLY => 1,
            RecurringJournalEntry::FREQUENCY_QUARTERLY => 0.33,
            RecurringJournalEntry::FREQUENCY_SEMIANNUALLY => 0.17,
            RecurringJournalEntry::FREQUENCY_ANNUALLY => 0.083,
        ];

        return $multipliers[$frequency] ?? 1;
    }

    /**
     * Send notification email
     *
     * @param RecurringJournalEntry $recurringEntry
     * @param JournalEntry $journalEntry
     */
    protected function sendNotification(RecurringJournalEntry $recurringEntry, JournalEntry $journalEntry)
    {
        // Notification disabled - columns don't exist in table
    }

    /**
     * Duplicate a recurring entry template
     *
     * @param RecurringJournalEntry $recurringEntry
     * @param array $overrides
     * @return RecurringJournalEntry
     */
    public function duplicate(RecurringJournalEntry $recurringEntry, array $overrides = [])
    {
        $data = $recurringEntry->toArray();
        
        // Remove id and timestamps
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
        
        // Reset counters
        $data['occurrences_completed'] = 0;
        $data['last_run_date'] = null;
        $data['next_run_date'] = $overrides['start_date'] ?? $data['start_date'];
        
        // Apply overrides
        $data = array_merge($data, $overrides);
        
        // Get lines
        $data['lines'] = $recurringEntry->lines->map(function ($line) {
            return [
                'account_id' => $line->account_id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ];
        })->toArray();

        return $this->createTemplate($data);
    }
}
