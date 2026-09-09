<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment of withheld tax to the body it was withheld for.
 *
 * @see database/migrations/2026_09_07_090200_create_tax_remittances_table.php
 */
class TaxRemittance extends Model
{
    protected $fillable = [
        'liability_account_id', 'salary_month', 'amount', 'payment_date',
        'source_account_id', 'reference', 'note', 'journal_entry_id',
        'voided_at', 'void_journal_entry_id', 'void_reason', 'voided_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'salary_month' => 'date',
        'payment_date' => 'date',
        'voided_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function liabilityAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id');
    }

    public function sourceAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'source_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function voider()
    {
        return $this->belongsTo(\App\User::class, 'voided_by');
    }

    /**
     * Only the remittances that still count.
     *
     * A voided one keeps its row — that is the audit trail — so every sum of
     * what has been paid has to exclude it explicitly. Forgetting this scope
     * is the way a voided payment silently keeps a month looking settled.
     */
    public function scopeLive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
