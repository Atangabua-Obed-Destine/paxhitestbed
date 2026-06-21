<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;
use Carbon\Carbon;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'asset_code',
        'name',
        'name_fr',
        'description',
        'category_id',
        'serial_number',
        'model',
        'manufacturer',
        'location',
        'department_id',
        'custodian_id',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'declining_balance_rate',
        'depreciation_start_date',
        'accumulated_depreciation',
        'book_value',
        'last_depreciation_date',
        'status',
        'disposal_date',
        'disposal_value',
        'disposal_reason',
        'disposal_journal_entry_id',
        'warranty_expiry',
        'notes',
        'image_path',
        'purchase_journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'depreciation_start_date' => 'date',
        'last_depreciation_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'declining_balance_rate' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
        'disposal_value' => 'decimal:2',
        'useful_life_months' => 'integer',
    ];

    /**
     * Status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';
    const STATUS_DISPOSED = 'disposed';
    const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    const STATUS_LOST = 'lost';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($asset) {
            if (empty($asset->asset_code)) {
                $asset->asset_code = static::generateAssetCode();
            }
            if (empty($asset->book_value)) {
                $asset->book_value = $asset->acquisition_cost - ($asset->accumulated_depreciation ?? 0);
            }
        });
    }

    /**
     * Generate unique asset code
     */
    public static function generateAssetCode()
    {
        $year = date('Y');
        $prefix = 'FA-' . $year . '-';
        
        $lastAsset = static::withTrashed()
            ->where('asset_code', 'like', $prefix . '%')
            ->orderBy('asset_code', 'desc')
            ->first();
        
        if ($lastAsset) {
            $lastNumber = (int) substr($lastAsset->asset_code, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the category
     */
    public function category()
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id');
    }

    /**
     * Get depreciation schedules
     */
    public function depreciationSchedules()
    {
        return $this->hasMany(DepreciationSchedule::class)->orderBy('depreciation_date');
    }

    /**
     * Get the department
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the custodian
     */
    public function custodian()
    {
        return $this->belongsTo(\App\User::class, 'custodian_id');
    }

    /**
     * Get disposal journal entry
     */
    public function disposalJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'disposal_journal_entry_id');
    }

    /**
     * Get purchase journal entry
     */
    public function purchaseJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'purchase_journal_entry_id');
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
     * Scope for active assets
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for depreciable assets
     */
    public function scopeDepreciable($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE])
            ->where('depreciation_method', '!=', 'none')
            ->where('book_value', '>', 0);
    }

    /**
     * Calculate monthly depreciation amount
     */
    public function calculateMonthlyDepreciation()
    {
        if ($this->depreciation_method === 'none' || $this->book_value <= $this->salvage_value) {
            return 0;
        }

        switch ($this->depreciation_method) {
            case 'straight_line':
                return $this->calculateStraightLineDepreciation();
            
            case 'declining_balance':
                return $this->calculateDecliningBalanceDepreciation();
            
            case 'units_of_production':
                // This requires units produced - not applicable for monthly calculation
                return 0;
            
            default:
                return 0;
        }
    }

    /**
     * Calculate straight-line monthly depreciation
     */
    protected function calculateStraightLineDepreciation()
    {
        $depreciableAmount = $this->acquisition_cost - $this->salvage_value;
        $monthlyDepreciation = $depreciableAmount / $this->useful_life_months;
        
        // Don't depreciate below salvage value
        $maxDepreciation = $this->book_value - $this->salvage_value;
        
        return min($monthlyDepreciation, $maxDepreciation);
    }

    /**
     * Calculate declining balance monthly depreciation
     */
    protected function calculateDecliningBalanceDepreciation()
    {
        $rate = $this->declining_balance_rate ?? (2 / ($this->useful_life_months / 12));
        $monthlyRate = $rate / 12;
        $monthlyDepreciation = $this->book_value * $monthlyRate;
        
        // Don't depreciate below salvage value
        $maxDepreciation = $this->book_value - $this->salvage_value;
        
        return min($monthlyDepreciation, $maxDepreciation);
    }

    /**
     * Get remaining useful life in months
     */
    public function getRemainingLifeMonths()
    {
        $startDate = Carbon::parse($this->depreciation_start_date);
        $monthsDepreciated = $startDate->diffInMonths(now());
        
        return max(0, $this->useful_life_months - $monthsDepreciated);
    }

    /**
     * Check if asset is fully depreciated
     */
    public function isFullyDepreciated()
    {
        return $this->book_value <= $this->salvage_value || $this->status === self::STATUS_FULLY_DEPRECIATED;
    }

    /**
     * Check if depreciation is due for a given date
     */
    public function isDepreciationDue($date = null)
    {
        $date = $date ?? now();
        
        if ($this->isFullyDepreciated() || $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->depreciation_method === 'none') {
            return false;
        }

        if ($this->last_depreciation_date === null) {
            return Carbon::parse($this->depreciation_start_date)->lte($date);
        }

        $lastDepreciation = Carbon::parse($this->last_depreciation_date);
        $nextDepreciationDue = $lastDepreciation->copy()->addMonth();
        
        return $nextDepreciationDue->lte($date);
    }

    /**
     * Record depreciation
     */
    public function recordDepreciation($amount, $depreciationDate, $journalEntryId = null)
    {
        $this->accumulated_depreciation += $amount;
        $this->book_value = $this->acquisition_cost - $this->accumulated_depreciation;
        $this->last_depreciation_date = $depreciationDate;

        // Check if fully depreciated
        if ($this->book_value <= $this->salvage_value) {
            $this->status = self::STATUS_FULLY_DEPRECIATED;
            $this->book_value = $this->salvage_value;
        }

        $this->save();
    }

    /**
     * Dispose asset
     */
    public function dispose($disposalDate, $disposalValue, $reason = null, $journalEntryId = null)
    {
        $this->status = self::STATUS_DISPOSED;
        $this->disposal_date = $disposalDate;
        $this->disposal_value = $disposalValue;
        $this->disposal_reason = $reason;
        $this->disposal_journal_entry_id = $journalEntryId;
        $this->save();
    }

    /**
     * Get status label
     */
    public function getStatusLabel()
    {
        $labels = [
            self::STATUS_ACTIVE => __('active'),
            self::STATUS_FULLY_DEPRECIATED => __('fully_depreciated'),
            self::STATUS_DISPOSED => __('disposed'),
            self::STATUS_UNDER_MAINTENANCE => __('under_maintenance'),
            self::STATUS_LOST => __('lost'),
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $code = $this->asset_code ?? 'N/A';
        $name = $this->name ?? 'N/A';
        $status = $this->status ?? 'N/A';
        $bookValue = number_format($this->book_value ?? 0, 2);
        
        return "Fixed Asset {$event}: [{$code}] {$name}, Status: {$status}, Book Value: {$bookValue}";
    }
}
