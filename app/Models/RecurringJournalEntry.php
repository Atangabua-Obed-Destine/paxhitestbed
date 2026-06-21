<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;
use Carbon\Carbon;

class RecurringJournalEntry extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'description',
        'journal_type',
        'frequency',
        'day_of_month',
        'day_of_week',
        'month_of_year',
        'start_date',
        'end_date',
        'next_run_date',
        'last_run_date',
        'occurrences',
        'occurrences_completed',
        'is_active',
        'auto_post',
        'total_debit',
        'total_credit',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_date' => 'date',
        'last_run_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_active' => 'boolean',
        'auto_post' => 'boolean',
        'occurrences' => 'integer',
        'occurrences_completed' => 'integer',
        'day_of_month' => 'integer',
        'day_of_week' => 'integer',
        'month_of_year' => 'integer',
    ];

    /**
     * Frequency constants
     */
    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_BIWEEKLY = 'biweekly';
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_QUARTERLY = 'quarterly';
    const FREQUENCY_SEMIANNUALLY = 'semiannually';
    const FREQUENCY_ANNUALLY = 'annually';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entry) {
            if (empty($entry->template_reference)) {
                $entry->template_reference = 'REC-' . strtoupper(uniqid());
            }
            if (empty($entry->next_run_date)) {
                $entry->next_run_date = $entry->start_date;
            }
        });
    }

    /**
     * Get the lines
     */
    public function lines()
    {
        return $this->hasMany(RecurringJournalEntryLine::class);
    }

    /**
     * Get generated journal entries
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'source_id')
            ->where('source_type', 'recurring');
    }

    /**
     * Get the fiscal year
     */
    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
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
     * Scope for active entries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for entries due to run
     */
    public function scopeDueToRun($query, $date = null)
    {
        $date = $date ?? now();
        
        return $query->where('is_active', true)
            ->where('next_run_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $date);
            })
            ->where(function ($q) {
                $q->whereNull('occurrences')
                  ->orWhereRaw('occurrences_completed < occurrences');
            });
    }

    /**
     * Get all frequencies
     */
    public static function getFrequencies()
    {
        return [
            self::FREQUENCY_DAILY => __('daily'),
            self::FREQUENCY_WEEKLY => __('weekly'),
            self::FREQUENCY_BIWEEKLY => __('biweekly'),
            self::FREQUENCY_MONTHLY => __('monthly'),
            self::FREQUENCY_QUARTERLY => __('quarterly'),
            self::FREQUENCY_SEMIANNUALLY => __('semiannually'),
            self::FREQUENCY_ANNUALLY => __('annually'),
        ];
    }

    /**
     * Get frequency label
     */
    public function getFrequencyLabel()
    {
        return self::getFrequencies()[$this->frequency] ?? $this->frequency;
    }

    /**
     * Calculate next run date
     */
    public function calculateNextRunDate($fromDate = null)
    {
        $fromDate = $fromDate ?? $this->next_run_date ?? now();
        $fromDate = Carbon::parse($fromDate);

        switch ($this->frequency) {
            case self::FREQUENCY_DAILY:
                return $fromDate->addDay();

            case self::FREQUENCY_WEEKLY:
                return $fromDate->addWeek();

            case self::FREQUENCY_BIWEEKLY:
                return $fromDate->addWeeks(2);

            case self::FREQUENCY_MONTHLY:
                $nextDate = $fromDate->copy()->addMonth();
                if ($this->day_of_month) {
                    $nextDate->day = min($this->day_of_month, $nextDate->daysInMonth);
                }
                return $nextDate;

            case self::FREQUENCY_QUARTERLY:
                return $fromDate->addMonths(3);

            case self::FREQUENCY_SEMIANNUALLY:
                return $fromDate->addMonths(6);

            case self::FREQUENCY_ANNUALLY:
                return $fromDate->addYear();

            default:
                return $fromDate->addMonth();
        }
    }

    /**
     * Check if entry should run
     */
    public function shouldRun($date = null)
    {
        $date = $date ?? now();

        if (!$this->is_active) {
            return false;
        }

        if ($this->end_date && Carbon::parse($this->end_date)->lt($date)) {
            return false;
        }

        if ($this->occurrences && $this->occurrences_completed >= $this->occurrences) {
            return false;
        }

        return Carbon::parse($this->next_run_date)->lte($date);
    }

    /**
     * Generate journal entry from template
     */
    public function generateJournalEntry($entryDate = null)
    {
        if (!$this->shouldRun()) {
            return null;
        }

        $entryDate = $entryDate ?? $this->next_run_date ?? now();

        // Create journal entry
        $journalEntry = JournalEntry::create([
            'entry_date' => $entryDate,
            'reference_number' => $this->template_reference . '-' . Carbon::parse($entryDate)->format('Ymd'),
            'description' => $this->description ?? $this->name,
            'source_type' => 'recurring',
            'source_id' => $this->id,
            'fiscal_year_id' => $this->fiscal_year_id ?? FiscalYear::getActiveFiscalYear()?->id,
            'status' => $this->auto_post ? 'posted' : 'draft',
            'created_by' => $this->created_by,
        ]);

        // Create journal entry lines
        foreach ($this->lines as $line) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $line->account_id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ]);
        }

        // Update recurring entry
        $this->last_run_date = $entryDate;
        $this->next_run_date = $this->calculateNextRunDate($entryDate);
        $this->occurrences_completed = ($this->occurrences_completed ?? 0) + 1;

        // Deactivate if max occurrences reached or end date passed
        if ($this->occurrences && $this->occurrences_completed >= $this->occurrences) {
            $this->is_active = false;
        }
        if ($this->end_date && Carbon::parse($this->end_date)->lt($this->next_run_date)) {
            $this->is_active = false;
        }

        $this->save();

        return $journalEntry;
    }

    /**
     * Validate the template lines
     */
    public function validateLines()
    {
        $totalDebit = $this->lines->sum('debit');
        $totalCredit = $this->lines->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception(__('journal_entry_must_balance'));
        }

        if ($this->lines->isEmpty()) {
            throw new \Exception(__('at_least_two_lines_required'));
        }

        return true;
    }

    /**
     * Get total debits
     */
    public function getTotalDebits()
    {
        return $this->lines->sum('debit');
    }

    /**
     * Get total credits
     */
    public function getTotalCredits()
    {
        return $this->lines->sum('credit');
    }

    /**
     * Check if balanced
     */
    public function isBalanced()
    {
        return abs($this->getTotalDebits() - $this->getTotalCredits()) < 0.01;
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $ref = $this->template_reference ?? 'N/A';
        $name = $this->name ?? 'N/A';
        $frequency = $this->frequency ?? 'N/A';
        $active = $this->is_active ? 'Active' : 'Inactive';
        
        return "Recurring Entry {$event}: [{$ref}] {$name}, Frequency: {$frequency}, Status: {$active}";
    }
}
