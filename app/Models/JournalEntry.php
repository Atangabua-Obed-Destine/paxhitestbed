<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TransactionMapping;
use App\Traits\Auditable;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'fiscal_year_id',
        'accounting_period_id',
        'journal_type',
        'description',
        'reference_number',
        'reference_type',
        'reference_id',
        'total_debit',
        'total_credit',
        'is_posted',
        'is_system_generated',
        'posted_by',
        'posted_at',
        'is_reversed',
        'reversed_entry_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_posted' => 'boolean',
        'is_system_generated' => 'boolean',
        'is_reversed' => 'boolean',
        'posted_at' => 'datetime',
    ];

    /**
     * Get the fiscal year
     */
    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the accounting period
     */
    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    /**
     * Get journal entry lines
     */
    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }

    /**
     * Get the transaction mapping that created this entry
     */
    public function transactionMapping()
    {
        return $this->hasOne(TransactionMapping::class, 'journal_entry_id');
    }

    /**
     * Get the user who posted this entry
     */
    public function postedBy()
    {
        return $this->belongsTo(\App\User::class, 'posted_by');
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
     * Get the reversing entry if this entry was reversed
     */
    public function reversingEntry()
    {
        return $this->hasOne(JournalEntry::class, 'reversed_entry_id');
    }

    /**
     * Get the original entry if this is a reversing entry
     */
    public function originalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_entry_id');
    }

    /**
     * Scope for posted entries
     */
    public function scopePosted($query)
    {
        return $query->where('is_posted', true);
    }

    /**
     * Scope for unposted entries
     */
    public function scopeUnposted($query)
    {
        return $query->where('is_posted', false);
    }

    /**
     * Scope for system generated entries
     */
    public function scopeSystemGenerated($query)
    {
        return $query->where('is_system_generated', true);
    }

    /**
     * Scope for manual entries
     */
    public function scopeManual($query)
    {
        return $query->where('is_system_generated', false);
    }

    /**
     * Scope for specific fiscal year
     */
    public function scopeForFiscalYear($query, $fiscalYearId)
    {
        return $query->where('fiscal_year_id', $fiscalYearId);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }

    /**
     * Check if entry is balanced (debits = credits)
     */
    public function isBalanced()
    {
        if (abs($this->total_debit - $this->total_credit) >= 0.01) {
            return false;
        }

        // The header can say anything; the lines are what post. Once there are
        // lines, they must balance and agree with the header.
        if ($this->exists) {
            $lines = $this->lines()->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c, COUNT(*) n')->first();
            if ((int) $lines->n > 0) {
                return abs($lines->d - $lines->c) < 0.01
                    && abs($lines->d - $this->total_debit) < 0.01
                    && abs($lines->c - $this->total_credit) < 0.01;
            }
        }

        return true;
    }

    /**
     * The next number in the one series every entry shares: JE-YYYY-NNNN, by
     * the year of the entry's own date.
     *
     * There used to be two series — JE-YYYY-NNNN here and JE-NNNNNN for
     * automatic postings — and the second had to ignore the first to avoid
     * reading a year as a sequence number. One series, read by numeric value,
     * removes that hazard: "JE-2026-9999" sorts above "JE-2026-10000" as text,
     * so the string maximum would repeat a number at entry ten thousand.
     *
     * Soft-deleted entries still hold their numbers in the unique index, so
     * they count. Read FOR UPDATE: inside a transaction two simultaneous
     * postings then take numbers one after the other.
     *
     * Existing JE-NNNNNN numbers are history and are left as they are.
     */
    public static function generateEntryNumber($date = null)
    {
        $year = $date ? \Carbon\Carbon::parse($date)->year : (int) date('Y');
        $prefix = 'JE-' . $year . '-';

        $max = static::withTrashed()
            ->where('entry_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('entry_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
    /**
     * Post the journal entry
     */
    public function post($userId)
    {
        if ($this->is_posted) {
            return false;
        }

        if (!$this->isBalanced()) {
            throw new \Exception('Journal entry is not balanced. Debits must equal credits.');
        }

        // Cannot post into a closed period or a closed fiscal year
        if ($this->accountingPeriod && $this->accountingPeriod->is_closed) {
            throw new \Exception('Cannot post into a closed accounting period.');
        }
        if ($this->fiscalYear && $this->fiscalYear->is_closed) {
            throw new \Exception('Cannot post into a closed fiscal year.');
        }

        // Atomic: flag posted + apply all balance updates together
        \Illuminate\Support\Facades\DB::transaction(function () use ($userId) {
            $this->is_posted = true;
            $this->posted_by = $userId;
            $this->posted_at = now();
            $this->save();

            foreach ($this->lines as $line) {
                $account = $line->account;
                if (!$account) {
                    continue;
                }
                if ($account->normal_balance === 'debit') {
                    $account->current_balance += ($line->debit - $line->credit);
                } else {
                    $account->current_balance += ($line->credit - $line->debit);
                }
                $account->save();
            }
        });

        return true;
    }

    /**
     * Unpost the journal entry
     */
    /**
     * Take a posted entry back to draft.
     *
     * Only for entries typed in by hand. An entry the system wrote for a fee,
     * an income, an expense, a payroll or a remittance is corrected through
     * that record, which writes a reversing entry; un-posting it here would
     * leave the record saying one thing and the ledger another. $bySystem is
     * for the code that owns such an entry.
     */
    public function unpost(bool $bySystem = false)
    {
        if (!$this->is_posted) {
            return false;
        }

        if ($this->is_system_generated && !$bySystem) {
            throw new \Exception(__('This entry was written by the system for a fee, income, expense, payroll or remittance. Correct it through that record; it cannot be un-posted by hand.'));
        }
        if ($this->accountingPeriod && $this->accountingPeriod->is_closed) {
            throw new \Exception(__('cannot_unpost_entry_in_closed_period'));
        }
        if ($this->fiscalYear && $this->fiscalYear->is_closed) {
            throw new \Exception(__('Cannot un-post an entry in a closed fiscal year.'));
        }

        // Atomic: reverse all balance updates + clear posted flag together
        \Illuminate\Support\Facades\DB::transaction(function () {
            foreach ($this->lines as $line) {
                $account = $line->account;
                if (!$account) {
                    continue;
                }
                if ($account->normal_balance === 'debit') {
                    $account->current_balance -= ($line->debit - $line->credit);
                } else {
                    $account->current_balance -= ($line->credit - $line->debit);
                }
                $account->save();
            }

            $this->is_posted = false;
            $this->posted_by = null;
            $this->posted_at = null;
            $this->save();
        });

        return true;
    }
    
    /**
     * Custom audit description for better readability
     */
    public function getAuditDescription($event)
    {
        $entryNumber = $this->entry_number ?? 'N/A';
        $amount = $this->total_debit ?? 0;
        $type = $this->journal_type ?? 'general';
        $posted = $this->is_posted ? 'Posted' : 'Draft';
        return "Journal Entry {$event}: #{$entryNumber} ({$type}) - {$posted}, Amount: {$amount}";
    }
}
