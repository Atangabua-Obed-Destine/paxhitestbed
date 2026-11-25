<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a supplier invoiced the school.
 *
 * Draft bills post nothing and can be edited. Approving one posts the cost and
 * the debt; after that it is corrected only by voiding it, which posts a
 * reversing entry and keeps the row.
 *
 * What is still owed is never stored here: it is the total less the live
 * allocations, read through App\Services\Accounting\SupplierPayables.
 *
 * @see database/migrations/2026_11_24_100000_create_supplier_payables_tables.php
 */
class SupplierBill extends Model
{
    use Auditable;

    public const DRAFT = 'draft';
    public const APPROVED = 'approved';
    public const VOID = 'void';

    public const KIND_BILL = 'bill';
    public const KIND_OPENING = 'opening_balance';

    protected $fillable = [
        'supplier_id', 'kind', 'number', 'supplier_reference', 'bill_date', 'due_date',
        'description', 'total', 'status', 'payable_account_id', 'attach',
        'journal_entry_id', 'approved_by', 'approved_at',
        'void_journal_entry_id', 'voided_by', 'voided_at', 'void_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(ItemSupplier::class, 'supplier_id');
    }

    public function lines()
    {
        return $this->hasMany(SupplierBillLine::class, 'supplier_bill_id')->orderBy('line_number');
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_bill_id');
    }

    public function payableAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function voidJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'void_journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
    }

    public function voider()
    {
        return $this->belongsTo(\App\User::class, 'voided_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }

    public function isOpeningBalance(): bool
    {
        return $this->kind === self::KIND_OPENING;
    }
}
