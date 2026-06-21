<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\User;
use App\Traits\Auditable;

class TransactionMapping extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'transaction_type',
        'transaction_id',
        'debit_account_id',
        'credit_account_id',
        'amount',
        'transaction_date',
        'description',
        'journal_entry_id',
        'mapped_at',
        'mapped_by',
        'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'mapped_at' => 'datetime',
    ];

    /**
     * Get the debit account for the mapping
     */
    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    /**
     * Get the credit account for the mapping
     */
    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    /**
     * Get the journal entry created from this mapping
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the user who created the mapping
     */
    public function mapper()
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    /**
     * Get the transaction (polymorphic relationship)
     */
    public function transaction()
    {
        return $this->morphTo('transaction', 'transaction_type', 'transaction_id');
    }

    /**
     * Scope to filter by transaction type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope to filter by mapped status
     */
    public function scopeMapped($query)
    {
        return $query->whereNotNull('journal_entry_id');
    }

    /**
     * Scope to filter by unmapped status
     */
    public function scopeUnmapped($query)
    {
        return $query->whereNull('journal_entry_id');
    }

    /**
     * Scope to filter by active status
     */
    public function scopeActive($query)
    {
        return $this->where('status', 'active');
    }

    /**
     * Check if the mapping has a journal entry
     */
    public function isMapped()
    {
        return !is_null($this->journal_entry_id);
    }
}
