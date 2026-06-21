<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use Carbon\Carbon;

class DepreciationSchedule extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'fixed_asset_id',
        'depreciation_date',
        'depreciation_amount',
        'accumulated_depreciation_before',
        'accumulated_depreciation_after',
        'book_value_before',
        'book_value_after',
        'fiscal_year_id',
        'accounting_period_id',
        'journal_entry_id',
        'status',
        'posted_at',
        'posted_by',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'depreciation_amount' => 'decimal:2',
        'accumulated_depreciation_before' => 'decimal:2',
        'accumulated_depreciation_after' => 'decimal:2',
        'book_value_before' => 'decimal:2',
        'book_value_after' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_POSTED = 'posted';
    const STATUS_REVERSED = 'reversed';

    /**
     * Get the fixed asset
     */
    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class);
    }

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
     * Get the journal entry
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the user who posted
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
     * Scope for pending depreciation
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for posted depreciation
     */
    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    /**
     * Scope for a specific period
     */
    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('accounting_period_id', $periodId);
    }

    /**
     * Scope for a specific fiscal year
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
        return $query->whereBetween('depreciation_date', [$startDate, $endDate]);
    }

    /**
     * Post the depreciation entry
     */
    public function post($userId = null)
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \Exception(__('only_pending_depreciation_can_be_posted'));
        }

        $asset = $this->fixedAsset;
        if (!$asset) {
            throw new \Exception(__('asset_not_found'));
        }

        // Create journal entry for depreciation
        $journalEntry = $this->createDepreciationJournalEntry();

        // Update asset
        $asset->recordDepreciation(
            $this->depreciation_amount,
            $this->depreciation_date,
            $journalEntry->id
        );

        // Update this record
        $this->status = self::STATUS_POSTED;
        $this->journal_entry_id = $journalEntry->id;
        $this->posted_at = now();
        $this->posted_by = $userId ?? auth()->id();
        $this->save();

        return $journalEntry;
    }

    /**
     * Create journal entry for depreciation
     */
    protected function createDepreciationJournalEntry()
    {
        $asset = $this->fixedAsset;
        $category = $asset->category;

        if (!$category || !$category->depreciationExpenseAccount || !$category->accumulatedDepreciationAccount) {
            throw new \Exception(__('depreciation_accounts_not_configured'));
        }

        // Create journal entry
        $journalEntry = JournalEntry::create([
            'entry_date' => $this->depreciation_date,
            'reference_number' => 'DEP-' . $asset->asset_code . '-' . Carbon::parse($this->depreciation_date)->format('Ym'),
            'description' => __('depreciation_for') . ' ' . $asset->name . ' - ' . Carbon::parse($this->depreciation_date)->format('F Y'),
            'source_type' => 'depreciation',
            'source_id' => $this->id,
            'fiscal_year_id' => $this->fiscal_year_id,
            'accounting_period_id' => $this->accounting_period_id,
            'status' => 'posted',
            'created_by' => auth()->id(),
        ]);

        // Debit: Depreciation Expense
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $category->depreciation_expense_account_id,
            'debit' => $this->depreciation_amount,
            'credit' => 0,
            'description' => __('depreciation_expense') . ' - ' . $asset->name,
        ]);

        // Credit: Accumulated Depreciation
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $category->accumulated_depreciation_account_id,
            'debit' => 0,
            'credit' => $this->depreciation_amount,
            'description' => __('accumulated_depreciation') . ' - ' . $asset->name,
        ]);

        return $journalEntry;
    }

    /**
     * Reverse the depreciation entry
     */
    public function reverse($reason = null, $userId = null)
    {
        if ($this->status !== self::STATUS_POSTED) {
            throw new \Exception(__('only_posted_depreciation_can_be_reversed'));
        }

        $asset = $this->fixedAsset;
        
        // Reverse asset values
        $asset->accumulated_depreciation -= $this->depreciation_amount;
        $asset->book_value = $asset->acquisition_cost - $asset->accumulated_depreciation;
        
        // Update status if it was fully depreciated
        if ($asset->status === FixedAsset::STATUS_FULLY_DEPRECIATED) {
            $asset->status = FixedAsset::STATUS_ACTIVE;
        }
        
        $asset->save();

        // Reverse journal entry if exists
        if ($this->journalEntry) {
            $this->journalEntry->reverse($reason ?? __('depreciation_reversal'));
        }

        // Update this record
        $this->status = self::STATUS_REVERSED;
        $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                       __('reversed_on') . ': ' . now()->format('Y-m-d H:i:s') . 
                       ($reason ? ' - ' . $reason : '');
        $this->save();
    }

    /**
     * Get status label
     */
    public function getStatusLabel()
    {
        $labels = [
            self::STATUS_PENDING => __('pending'),
            self::STATUS_POSTED => __('posted'),
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
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_POSTED => 'badge-success',
            self::STATUS_REVERSED => 'badge-danger',
        ];

        return $classes[$this->status] ?? 'badge-secondary';
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $assetCode = $this->fixedAsset->asset_code ?? 'N/A';
        $date = $this->depreciation_date ? Carbon::parse($this->depreciation_date)->format('M Y') : 'N/A';
        $amount = number_format($this->depreciation_amount ?? 0, 2);
        $status = $this->status ?? 'N/A';
        
        return "Depreciation {$event}: Asset [{$assetCode}], Period: {$date}, Amount: {$amount}, Status: {$status}";
    }
}
