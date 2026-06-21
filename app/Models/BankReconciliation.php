<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class BankReconciliation extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'payment_account_id',
        'chart_of_account_id',
        'statement_date',
        'reconciliation_date',
        'statement_reference',
        'statement_opening_balance',
        'statement_closing_balance',
        'book_balance',
        'adjusted_book_balance',
        'outstanding_deposits',
        'outstanding_checks',
        'bank_charges',
        'bank_interest',
        'other_adjustments',
        'difference',
        'status',
        'notes',
        'reconciled_by',
        'reconciled_at',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'reconciliation_date' => 'date',
        'statement_opening_balance' => 'decimal:2',
        'statement_closing_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'adjusted_book_balance' => 'decimal:2',
        'outstanding_deposits' => 'decimal:2',
        'outstanding_checks' => 'decimal:2',
        'bank_charges' => 'decimal:2',
        'bank_interest' => 'decimal:2',
        'other_adjustments' => 'decimal:2',
        'difference' => 'decimal:2',
        'reconciled_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_APPROVED = 'approved';

    /**
     * Get the payment account
     */
    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * Get the chart of account
     */
    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    /**
     * Get reconciliation items
     */
    public function items()
    {
        return $this->hasMany(BankReconciliationItem::class);
    }

    /**
     * Get outstanding checks
     */
    public function outstandingChecks()
    {
        return $this->items()->where('item_type', 'outstanding_check')->where('is_cleared', false);
    }

    /**
     * Get deposits in transit
     */
    public function depositsInTransit()
    {
        return $this->items()->where('item_type', 'deposit_in_transit')->where('is_cleared', false);
    }

    /**
     * Get the user who reconciled
     */
    public function reconciledBy()
    {
        return $this->belongsTo(\App\User::class, 'reconciled_by');
    }

    /**
     * Get the user who approved
     */
    public function approvedBy()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
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
     * Scope for specific status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for completed reconciliations
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_APPROVED]);
    }

    /**
     * Scope for pending reconciliations
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Check if reconciliation is balanced
     */
    public function isBalanced()
    {
        return abs($this->difference) < 0.01;
    }

    /**
     * Calculate the adjusted book balance
     */
    public function calculateAdjustedBookBalance()
    {
        $adjustedBalance = $this->book_balance;
        
        foreach ($this->items as $item) {
            if ($item->effect === 'add') {
                $adjustedBalance += $item->amount;
            } else {
                $adjustedBalance -= $item->amount;
            }
        }
        
        return $adjustedBalance;
    }

    /**
     * Calculate difference between bank and adjusted book balance
     */
    public function calculateDifference()
    {
        $adjustedBalance = $this->calculateAdjustedBookBalance();
        return $this->statement_closing_balance - $adjustedBalance;
    }

    /**
     * Recalculate all balances
     */
    public function recalculate()
    {
        $this->outstanding_deposits = $this->items()
            ->where('item_type', 'deposit_in_transit')
            ->where('is_cleared', false)
            ->sum('amount');

        $this->outstanding_checks = $this->items()
            ->where('item_type', 'outstanding_check')
            ->where('is_cleared', false)
            ->sum('amount');

        $this->bank_charges = $this->items()
            ->where('item_type', 'bank_charge')
            ->sum('amount');

        $this->bank_interest = $this->items()
            ->where('item_type', 'bank_interest')
            ->sum('amount');

        $this->adjusted_book_balance = $this->calculateAdjustedBookBalance();
        $this->difference = $this->calculateDifference();
        
        $this->save();
    }

    /**
     * Complete the reconciliation
     */
    public function complete($userId)
    {
        if (!$this->isBalanced()) {
            throw new \Exception(__('reconciliation_not_balanced'));
        }

        $this->status = self::STATUS_COMPLETED;
        $this->reconciled_by = $userId;
        $this->reconciled_at = now();
        $this->save();

        return true;
    }

    /**
     * Approve the reconciliation
     */
    public function approve($userId)
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new \Exception(__('reconciliation_must_be_completed_first'));
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();

        return true;
    }

    /**
     * Get previous reconciliation
     */
    public function getPreviousReconciliation()
    {
        return static::where('payment_account_id', $this->payment_account_id)
            ->where('statement_date', '<', $this->statement_date)
            ->where('status', self::STATUS_APPROVED)
            ->orderBy('statement_date', 'desc')
            ->first();
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $accountName = $this->paymentAccount->name ?? 'Unknown';
        $date = $this->statement_date ? $this->statement_date->format('Y-m-d') : 'N/A';
        $status = $this->status ?? 'N/A';
        
        return "Bank Reconciliation {$event}: {$accountName} for {$date}, Status: {$status}";
    }
}
