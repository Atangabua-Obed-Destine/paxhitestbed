<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class FixedAssetCategory extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'name_fr',
        'description',
        'depreciation_method',
        'useful_life_years',
        'salvage_value_percent',
        'asset_account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'useful_life_years' => 'integer',
        'salvage_value_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Depreciation method constants
     */
    const METHOD_STRAIGHT_LINE = 'straight_line';
    const METHOD_DECLINING_BALANCE = 'declining_balance';
    const METHOD_UNITS_OF_PRODUCTION = 'units_of_production';
    const METHOD_NONE = 'none';

    /**
     * Get assets in this category
     */
    public function assets()
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    /**
     * Get the asset account
     */
    public function assetAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    /**
     * Get the depreciation expense account
     */
    public function depreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_account_id');
    }

    /**
     * Get the accumulated depreciation account
     */
    public function accumulatedDepreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
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
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get depreciation method label
     */
    public function getMethodLabel()
    {
        $labels = [
            self::METHOD_STRAIGHT_LINE => __('straight_line'),
            self::METHOD_DECLINING_BALANCE => __('declining_balance'),
            self::METHOD_UNITS_OF_PRODUCTION => __('units_of_production'),
            self::METHOD_NONE => __('no_depreciation'),
        ];

        return $labels[$this->depreciation_method] ?? $this->depreciation_method;
    }

    /**
     * Calculate useful life in months
     */
    public function getUsefulLifeMonthsAttribute()
    {
        return $this->useful_life_years * 12;
    }

    /**
     * Check if category has proper account mappings
     */
    public function hasValidAccountMappings()
    {
        if ($this->depreciation_method === self::METHOD_NONE) {
            return $this->asset_account_id !== null;
        }

        return $this->asset_account_id !== null 
            && $this->depreciation_account_id !== null 
            && $this->accumulated_depreciation_account_id !== null;
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $name = $this->name ?? 'N/A';
        $method = $this->depreciation_method ?? 'N/A';
        
        return "Fixed Asset Category {$event}: {$name}, Method: {$method}";
    }
}
