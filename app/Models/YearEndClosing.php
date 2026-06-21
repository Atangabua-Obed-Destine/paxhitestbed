<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use Carbon\Carbon;

class YearEndClosing extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'fiscal_year_id',
        'closing_date',
        'closing_reference',
        'total_revenue',
        'total_expenses',
        'net_income',
        'retained_earnings_account_id',
        'income_summary_account_id',
        'closing_journal_entry_id',
        'status',
        'checklist',
        'started_at',
        'completed_at',
        'notes',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'net_income' => 'decimal:2',
        'checklist' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REVERSED = 'reversed';

    /**
     * Default checklist items
     */
    const DEFAULT_CHECKLIST = [
        'all_transactions_posted' => false,
        'bank_reconciliations_complete' => false,
        'depreciation_posted' => false,
        'accruals_posted' => false,
        'adjusting_entries_complete' => false,
        'trial_balance_reviewed' => false,
        'financial_statements_prepared' => false,
        'audit_adjustments_entered' => false,
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($closing) {
            if (empty($closing->closing_reference)) {
                $closing->closing_reference = 'YEC-' . date('Y') . '-' . strtoupper(uniqid());
            }
            if (empty($closing->checklist)) {
                $closing->checklist = self::DEFAULT_CHECKLIST;
            }
        });
    }

    /**
     * Get the fiscal year
     */
    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get retained earnings account
     */
    public function retainedEarningsAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'retained_earnings_account_id');
    }

    /**
     * Get income summary account
     */
    public function incomeSummaryAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'income_summary_account_id');
    }

    /**
     * Get closing journal entry
     */
    public function closingJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'closing_journal_entry_id');
    }

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Get updater
     */
    public function updater()
    {
        return $this->belongsTo(\App\User::class, 'updated_by');
    }

    /**
     * Get approver
     */
    public function approver()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
    }

    /**
     * Scope for completed closings
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending closings
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS, self::STATUS_PENDING_APPROVAL]);
    }

    /**
     * Get status label
     */
    public function getStatusLabel()
    {
        $labels = [
            self::STATUS_DRAFT => __('draft'),
            self::STATUS_IN_PROGRESS => __('in_progress'),
            self::STATUS_PENDING_APPROVAL => __('pending_approval'),
            self::STATUS_COMPLETED => __('completed'),
            self::STATUS_REVERSED => __('reversed'),
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass()
    {
        $classes = [
            self::STATUS_DRAFT => 'badge-secondary',
            self::STATUS_IN_PROGRESS => 'badge-info',
            self::STATUS_PENDING_APPROVAL => 'badge-warning',
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_REVERSED => 'badge-danger',
        ];

        return $classes[$this->status] ?? 'badge-secondary';
    }

    /**
     * Update checklist item
     */
    public function updateChecklistItem($item, $value)
    {
        $checklist = $this->checklist ?? [];
        $checklist[$item] = $value;
        $this->checklist = $checklist;
        $this->save();
    }

    /**
     * Get checklist completion percentage
     */
    public function getChecklistCompletionPercentage()
    {
        $checklist = $this->checklist ?? [];
        if (empty($checklist)) {
            return 0;
        }

        $completed = count(array_filter($checklist));
        return round(($completed / count($checklist)) * 100);
    }

    /**
     * Check if all checklist items are complete
     */
    public function isChecklistComplete()
    {
        $checklist = $this->checklist ?? [];
        return count(array_filter($checklist)) === count($checklist);
    }

    /**
     * Start the closing process
     */
    public function start()
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \Exception(__('only_draft_closings_can_be_started'));
        }

        $this->status = self::STATUS_IN_PROGRESS;
        $this->started_at = now();
        $this->save();
    }

    /**
     * Calculate closing amounts
     */
    public function calculateClosingAmounts()
    {
        $fiscalYear = $this->fiscalYear;
        if (!$fiscalYear) {
            throw new \Exception(__('fiscal_year_not_found'));
        }

        // Get all revenue accounts (Class 7 in OHADA)
        $revenueAccounts = ChartOfAccount::where('account_code', 'like', '7%')->pluck('id');

        // Get all expense accounts (Class 6 in OHADA)
        $expenseAccounts = ChartOfAccount::where('account_code', 'like', '6%')->pluck('id');

        // Calculate total revenue (credits minus debits)
        $revenueCredits = JournalEntryLine::whereIn('account_id', $revenueAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYear) {
                $q->where('fiscal_year_id', $fiscalYear->id)
                  ->where('is_posted', true);
            })
            ->sum('credit');

        $revenueDebits = JournalEntryLine::whereIn('account_id', $revenueAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYear) {
                $q->where('fiscal_year_id', $fiscalYear->id)
                  ->where('is_posted', true);
            })
            ->sum('debit');

        $this->total_revenue = $revenueCredits - $revenueDebits;

        // Calculate total expenses (debits minus credits)
        $expenseDebits = JournalEntryLine::whereIn('account_id', $expenseAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYear) {
                $q->where('fiscal_year_id', $fiscalYear->id)
                  ->where('is_posted', true);
            })
            ->sum('debit');

        $expenseCredits = JournalEntryLine::whereIn('account_id', $expenseAccounts)
            ->whereHas('journalEntry', function ($q) use ($fiscalYear) {
                $q->where('fiscal_year_id', $fiscalYear->id)
                  ->where('is_posted', true);
            })
            ->sum('credit');

        $this->total_expenses = $expenseDebits - $expenseCredits;

        // Calculate net income
        $this->net_income = $this->total_revenue - $this->total_expenses;

        $this->save();
    }

    /**
     * Generate closing entries
     */
    public function generateClosingEntries()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            throw new \Exception(__('closing_must_be_in_progress'));
        }

        if (!$this->isChecklistComplete()) {
            throw new \Exception(__('complete_checklist_before_closing'));
        }

        $this->calculateClosingAmounts();

        return \Illuminate\Support\Facades\DB::transaction(function () {
            $userId = auth()->id() ?? 1;

            // Create the closing journal entry as a draft, then post it via the model's
            // single posting path (validates balance + updates balances atomically).
            $journalEntry = JournalEntry::create([
                'entry_number'        => JournalEntry::generateEntryNumber(),
                'entry_date'          => $this->closing_date,
                'reference_number'    => $this->closing_reference,
                'description'         => __('year_end_closing_entry') . ' - ' . ($this->fiscalYear->name ?? ''),
                'journal_type'        => 'closing',
                'reference_type'      => 'year_end_closing',
                'reference_id'        => $this->id,
                'fiscal_year_id'      => $this->fiscal_year_id,
                'total_debit'         => 0,
                'total_credit'        => 0,
                'is_posted'           => false,
                'is_system_generated' => true,
                'created_by'          => $userId,
            ]);

            $lineNo = 0;

            // Close revenue accounts (Class 7, credit balances) → debit them to zero
            if ($this->total_revenue != 0) {
                foreach (ChartOfAccount::where('account_code', 'like', '7%')->get() as $account) {
                    $balance = $this->getAccountBalance($account->id); // credits - debits
                    if ($balance != 0) {
                        JournalEntryLine::create([
                            'journal_entry_id' => $journalEntry->id,
                            'line_number'      => ++$lineNo,
                            'account_id'       => $account->id,
                            'debit'            => $balance > 0 ? $balance : 0,
                            'credit'           => $balance < 0 ? abs($balance) : 0,
                            'description'      => __('close_revenue_account') . ' ' . $account->account_code,
                        ]);
                    }
                }
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number'      => ++$lineNo,
                    'account_id'       => $this->income_summary_account_id,
                    'debit'            => 0,
                    'credit'           => $this->total_revenue,
                    'description'      => __('revenue_to_income_summary'),
                ]);
            }

            // Close expense accounts (Class 6, debit balances) → CREDIT them to zero
            if ($this->total_expenses != 0) {
                foreach (ChartOfAccount::where('account_code', 'like', '6%')->get() as $account) {
                    $balance = $this->getAccountBalance($account->id); // credits - debits (negative for expenses)
                    if ($balance != 0) {
                        JournalEntryLine::create([
                            'journal_entry_id' => $journalEntry->id,
                            'line_number'      => ++$lineNo,
                            'account_id'       => $account->id,
                            'debit'            => $balance > 0 ? $balance : 0,
                            'credit'           => $balance < 0 ? abs($balance) : 0,
                            'description'      => __('close_expense_account') . ' ' . $account->account_code,
                        ]);
                    }
                }
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number'      => ++$lineNo,
                    'account_id'       => $this->income_summary_account_id,
                    'debit'            => $this->total_expenses,
                    'credit'           => 0,
                    'description'      => __('expenses_to_income_summary'),
                ]);
            }

            // Close income summary → retained earnings (net result)
            if ($this->net_income != 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number'      => ++$lineNo,
                    'account_id'       => $this->income_summary_account_id,
                    'debit'            => $this->net_income > 0 ? $this->net_income : 0,
                    'credit'           => $this->net_income < 0 ? abs($this->net_income) : 0,
                    'description'      => __('income_summary_to_retained_earnings'),
                ]);
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number'      => ++$lineNo,
                    'account_id'       => $this->retained_earnings_account_id,
                    'debit'            => $this->net_income < 0 ? abs($this->net_income) : 0,
                    'credit'           => $this->net_income > 0 ? $this->net_income : 0,
                    'description'      => __('net_income_to_retained_earnings'),
                ]);
            }

            // Set totals from the actual lines and post through the model
            $journalEntry->total_debit  = $journalEntry->lines()->sum('debit');
            $journalEntry->total_credit = $journalEntry->lines()->sum('credit');
            $journalEntry->save();
            $journalEntry->post($userId);

            $this->closing_journal_entry_id = $journalEntry->id;
            $this->status = self::STATUS_PENDING_APPROVAL;
            $this->save();

            return $journalEntry;
        });
    }

    /**
     * Get account balance for fiscal year
     */
    protected function getAccountBalance($accountId)
    {
        $credits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where('fiscal_year_id', $this->fiscal_year_id)
                  ->where('is_posted', true);
            })
            ->sum('credit');

        $debits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where('fiscal_year_id', $this->fiscal_year_id)
                  ->where('is_posted', true);
            })
            ->sum('debit');

        return $credits - $debits;
    }

    /**
     * Approve the closing
     */
    public function approve($userId = null)
    {
        if ($this->status !== self::STATUS_PENDING_APPROVAL) {
            throw new \Exception(__('only_pending_closings_can_be_approved'));
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->approved_by = $userId ?? auth()->id();
        $this->approved_at = now();
        $this->save();

        // Close the fiscal year
        $this->fiscalYear->update(['is_closed' => true]);
    }

    /**
     * Reverse the closing
     */
    public function reverse($reason = null)
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new \Exception(__('only_completed_closings_can_be_reversed'));
        }

        // Reverse the closing journal entry
        if ($this->closingJournalEntry) {
            $this->closingJournalEntry->reverse($reason ?? __('year_end_closing_reversal'));
        }

        // Reopen the fiscal year
        $this->fiscalYear->update(['is_closed' => false]);

        $this->status = self::STATUS_REVERSED;
        $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                       __('reversed_on') . ': ' . now()->format('Y-m-d H:i:s') . 
                       ($reason ? ' - ' . $reason : '');
        $this->save();
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $ref = $this->closing_reference ?? 'N/A';
        $fyName = $this->fiscalYear->name ?? 'N/A';
        $status = $this->status ?? 'N/A';
        $netIncome = number_format($this->net_income ?? 0, 2);
        
        return "Year-End Closing {$event}: [{$ref}] FY: {$fyName}, Net Income: {$netIncome}, Status: {$status}";
    }
}
