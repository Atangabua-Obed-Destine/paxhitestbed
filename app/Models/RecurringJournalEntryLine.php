<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class RecurringJournalEntryLine extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'recurring_journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'description',
        'order',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Get the recurring journal entry
     */
    public function recurringJournalEntry()
    {
        return $this->belongsTo(RecurringJournalEntry::class);
    }

    /**
     * Get the account
     */
    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Scope ordered by order column
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Check if this is a debit line
     */
    public function isDebit()
    {
        return $this->debit > 0;
    }

    /**
     * Check if this is a credit line
     */
    public function isCredit()
    {
        return $this->credit > 0;
    }

    /**
     * Get the amount (either debit or credit)
     */
    public function getAmount()
    {
        return $this->debit > 0 ? $this->debit : $this->credit;
    }

    /**
     * Get formatted amount with type
     */
    public function getFormattedAmount()
    {
        if ($this->debit > 0) {
            return number_format($this->debit, 2) . ' (Dr)';
        }
        return number_format($this->credit, 2) . ' (Cr)';
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $accountCode = $this->account->code ?? 'N/A';
        $debit = number_format($this->debit ?? 0, 2);
        $credit = number_format($this->credit ?? 0, 2);
        
        return "Recurring Entry Line {$event}: Account [{$accountCode}], Debit: {$debit}, Credit: {$credit}";
    }
}
