<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankReconciliationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_reconciliation_id',
        'item_type',
        'reference_number',
        'transaction_date',
        'description',
        'amount',
        'effect',
        'is_cleared',
        'cleared_date',
        'journal_entry_id',
        'payment_account_transaction_id',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'cleared_date' => 'date',
        'amount' => 'decimal:2',
        'is_cleared' => 'boolean',
    ];

    /**
     * Item type constants
     */
    const TYPE_OUTSTANDING_CHECK = 'outstanding_check';
    const TYPE_DEPOSIT_IN_TRANSIT = 'deposit_in_transit';
    const TYPE_BANK_CHARGE = 'bank_charge';
    const TYPE_BANK_INTEREST = 'bank_interest';
    const TYPE_NSF_CHECK = 'nsf_check';
    const TYPE_DIRECT_DEPOSIT = 'direct_deposit';
    const TYPE_DIRECT_DEBIT = 'direct_debit';
    const TYPE_ERROR_CORRECTION = 'error_correction';
    const TYPE_OTHER_ADJUSTMENT = 'other_adjustment';

    /**
     * Effect constants
     */
    const EFFECT_ADD = 'add';
    const EFFECT_SUBTRACT = 'subtract';

    /**
     * Get the bank reconciliation
     */
    public function bankReconciliation()
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    /**
     * Get the journal entry (if adjustment was recorded)
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the original payment account transaction
     */
    public function paymentAccountTransaction()
    {
        return $this->belongsTo(PaymentAccountTransaction::class);
    }

    /**
     * Scope for uncleared items
     */
    public function scopeUncleared($query)
    {
        return $query->where('is_cleared', false);
    }

    /**
     * Scope for cleared items
     */
    public function scopeCleared($query)
    {
        return $query->where('is_cleared', true);
    }

    /**
     * Scope by item type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Mark item as cleared
     */
    public function markAsCleared($clearedDate = null)
    {
        $this->is_cleared = true;
        $this->cleared_date = $clearedDate ?? now();
        $this->save();
    }

    /**
     * Get item type label
     */
    public function getTypeLabel()
    {
        $labels = [
            self::TYPE_OUTSTANDING_CHECK => __('outstanding_check'),
            self::TYPE_DEPOSIT_IN_TRANSIT => __('deposit_in_transit'),
            self::TYPE_BANK_CHARGE => __('bank_charge'),
            self::TYPE_BANK_INTEREST => __('bank_interest'),
            self::TYPE_NSF_CHECK => __('nsf_check'),
            self::TYPE_DIRECT_DEPOSIT => __('direct_deposit'),
            self::TYPE_DIRECT_DEBIT => __('direct_debit'),
            self::TYPE_ERROR_CORRECTION => __('error_correction'),
            self::TYPE_OTHER_ADJUSTMENT => __('other_adjustment'),
        ];

        return $labels[$this->item_type] ?? $this->item_type;
    }

    /**
     * Determine if this type affects book balance positively
     */
    public function isAddition()
    {
        return $this->effect === self::EFFECT_ADD;
    }

    /**
     * Determine if this type affects book balance negatively
     */
    public function isSubtraction()
    {
        return $this->effect === self::EFFECT_SUBTRACT;
    }
}
