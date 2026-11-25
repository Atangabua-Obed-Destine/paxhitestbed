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

        // (There is no template_reference column: the reference is derived from
        // the id, see getTemplateReferenceAttribute. Setting it here made every
        // insert fail.)
        static::creating(function ($entry) {
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
        // Journal entries carry reference_type / reference_id (there are no
        // source_type / source_id columns).
        return $this->hasMany(JournalEntry::class, 'reference_id')
            ->where('reference_type', 'recurring_entry');
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
                return $this->monthsAhead($fromDate, 1);

            case self::FREQUENCY_QUARTERLY:
                return $this->monthsAhead($fromDate, 3);

            case self::FREQUENCY_SEMIANNUALLY:
                return $this->monthsAhead($fromDate, 6);

            case self::FREQUENCY_ANNUALLY:
                return $this->monthsAhead($fromDate, 12);

            default:
                return $this->monthsAhead($fromDate, 1);
        }
    }

    /**
     * $months later, on the entry's own day (its day of month, else the day it
     * started), or the month's last day when that month is shorter.
     *
     * addMonth() on 31 January lands on 3 March (February skipped), and once a
     * date was cut to the 28th every later month stayed on the 28th.
     */
    protected function monthsAhead(Carbon $from, int $months): Carbon
    {
        $day = (int) ($this->day_of_month ?: ($this->start_date ? Carbon::parse($this->start_date)->day : $from->day));
        $next = $from->copy()->startOfMonth()->addMonthsNoOverflow($months);

        return $next->day(min($day, $next->daysInMonth));
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
        if (!$this->shouldRun($entryDate ?? now())) {
            return null;
        }

        // The run's own date: the one it was due on.
        $entryDate = Carbon::parse($this->next_run_date ?? $entryDate ?? now())->toDateString();

        // Written against columns journal entries never had (source_type,
        // source_id, status) and with no number or totals, so no recurring
        // entry could ever be generated. Now: a numbered entry in the fiscal
        // year and month that contain its date, refused for a closed month,
        // posted when the template says auto-post, otherwise left for the
        // accountant to check and post.
        $year = FiscalYear::forDate($entryDate);
        if (!$year || $year->is_closed) {
            throw new \RuntimeException(__('No open fiscal year covers :date.', ['date' => Carbon::parse($entryDate)->format('j F Y')]));
        }
        $period = FiscalYear::periodForDate($entryDate);
        if ($period && $period->is_closed) {
            throw new \RuntimeException(__('The month that contains :date is closed.', ['date' => Carbon::parse($entryDate)->format('j F Y')]));
        }

        $debit = round((float) $this->lines->sum('debit'), 2);
        $credit = round((float) $this->lines->sum('credit'), 2);
        $types = ['general', 'sales', 'purchase', 'cash', 'bank', 'adjustment', 'opening', 'closing', 'payroll', 'remittance'];

        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber($entryDate),
            'entry_date' => $entryDate,
            'fiscal_year_id' => $year->id,
            'accounting_period_id' => $period->id ?? null,
            'journal_type' => in_array($this->journal_type, $types, true) ? $this->journal_type : 'general',
            'description' => $this->description ?: $this->name,
            'reference_number' => $this->template_reference . '-' . Carbon::parse($entryDate)->format('Ymd'),
            'reference_type' => 'recurring_entry',
            'reference_id' => $this->id,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'is_posted' => false,
            'is_system_generated' => false,
            'created_by' => auth()->id() ?? $this->created_by,
        ]);

        $n = 1;
        foreach ($this->lines->sortBy('line_number') as $line) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $n++,
                'account_id' => $line->account_id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ]);
        }

        if ($this->auto_post) {
            $journalEntry->load('lines');
            $journalEntry->post(auth()->id() ?? $this->created_by);
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

    /* ---- The names the screens use, answered from the real columns ---- */

    public function getTemplateReferenceAttribute(): string
    {
        return 'REC-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getTemplateNameAttribute(): ?string
    {
        return $this->name;
    }

    public function getTotalOccurrencesAttribute(): ?int
    {
        return $this->occurrences;
    }

    public function getOccurrencesCreatedAttribute(): int
    {
        return (int) $this->occurrences_completed;
    }

    /** active, paused (switched off) or expired (end date or occurrences reached). */
    public function getStatusAttribute(): string
    {
        if (($this->end_date && Carbon::parse($this->end_date)->lt(now()->startOfDay()))
            || ($this->occurrences && $this->occurrences_completed >= $this->occurrences)) {
            return 'expired';
        }

        return $this->is_active ? 'active' : 'paused';
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
