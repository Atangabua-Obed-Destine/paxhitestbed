<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TaxSetting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tax_group_id', 'is_dependent', 'depends_on_type', 'depends_on_id',
        'title', 'bracket_order', 'tax_type', 'is_shared', 'paid_by',
        'min_amount', 'max_amount', 'percentange', 'employer_percentage', 
        'fixed_amount', 'employer_fixed_amount', 'max_no_taxable_amount', 
        'status', 'effective_from', 'effective_to',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_shared' => 'boolean',
        'is_dependent' => 'boolean',
    ];

    /**
     * Get the source tax group this dependent tax calculates from.
     */
    public function dependsOnGroup()
    {
        return $this->belongsTo(TaxGroup::class, 'depends_on_id')
                    ->where(function ($q) {
                        // Only valid when depends_on_type is 'tax_group'
                    });
    }

    /**
     * Get the source standalone tax setting this dependent tax calculates from.
     */
    public function dependsOnTax()
    {
        return $this->belongsTo(TaxSetting::class, 'depends_on_id')
                    ->where(function ($q) {
                        // Only valid when depends_on_type is 'tax_setting'
                    });
    }

    /**
     * Get the source model (TaxGroup or TaxSetting) this tax depends on.
     */
    public function getDependencySourceAttribute()
    {
        if (!$this->is_dependent || !$this->depends_on_type || !$this->depends_on_id) {
            return null;
        }

        if ($this->depends_on_type === 'tax_group') {
            return TaxGroup::find($this->depends_on_id);
        }

        if ($this->depends_on_type === 'tax_setting') {
            return TaxSetting::find($this->depends_on_id);
        }

        return null;
    }

    /**
     * Get a human-readable label for the dependency source.
     */
    public function getDependencyLabelAttribute()
    {
        $source = $this->dependency_source;
        if (!$source) return null;

        if ($this->depends_on_type === 'tax_group') {
            return $source->title . ' (' . __('tax_group') . ')';
        }

        return $source->title . ' (' . __('standalone') . ')';
    }

    /**
     * Calculate employee contribution for a given base amount.
     * For dependent taxes, $baseAmount should be the source tax's output.
     * For normal taxes, $baseAmount is the salary.
     */
    public function calculateEmployeeContribution($salary)
    {
        if ($this->paid_by === 'employer') {
            return 0;
        }

        if ($this->tax_type == 1) {
            // Percentage applies after the tax-free allowance (matches payroll)
            $taxable = max(0, $salary - ($this->max_no_taxable_amount ?? 0));
            return ($taxable * $this->percentange) / 100;
        }
        return $this->fixed_amount;
    }

    /**
     * Calculate employer contribution for a given salary.
     */
    public function calculateEmployerContribution($salary)
    {
        if ($this->paid_by === 'employee') {
            return 0;
        }

        if ($this->tax_type == 1) {
            // Percentage applies after the tax-free allowance (matches payroll)
            $taxable = max(0, $salary - ($this->max_no_taxable_amount ?? 0));
            return ($taxable * $this->employer_percentage) / 100;
        }
        return $this->employer_fixed_amount;
    }

    /**
     * Get the tax group this bracket belongs to.
     */
    public function taxGroup()
    {
        return $this->belongsTo(TaxGroup::class, 'tax_group_id');
    }

    /**
     * Get the staff members exempt from this tax.
     */
    public function exemptStaff()
    {
        return $this->belongsToMany('App\User', 'staff_tax_exemptions', 'tax_setting_id', 'user_id')
                    ->withPivot('reason', 'custom_percentage', 'custom_fixed_amount', 'expires_at')
                    ->withTimestamps();
    }

    /**
     * Get the exemptions for this tax setting.
     */
    public function exemptions()
    {
        return $this->hasMany('App\Models\StaffTaxExemption', 'tax_setting_id');
    }

    /**
     * Scope a query to only include active tax brackets.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include standalone brackets (no group).
     */
    public function scopeStandalone($query)
    {
        return $query->whereNull('tax_group_id');
    }

    /**
     * Scope a query to only include base (non-dependent) taxes.
     */
    public function scopeBase($query)
    {
        return $query->where(function ($q) {
            $q->where('is_dependent', false)->orWhereNull('is_dependent');
        });
    }

    /**
     * Scope a query to only include dependent taxes.
     */
    public function scopeDependent($query)
    {
        return $query->where('is_dependent', true);
    }

    /**
     * Scope a query to only include brackets effective on a given date.
     */
    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        return $query->where(function($q) use ($date) {
            $q->whereNull('effective_from')
              ->orWhere('effective_from', '<=', $date);
        })->where(function($q) use ($date) {
            $q->whereNull('effective_to')
              ->orWhere('effective_to', '>=', $date);
        });
    }

    /**
     * Scope a query to order by bracket order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('bracket_order', 'asc')->orderBy('min_amount', 'asc');
    }

    /**
     * Get active and effective standalone tax brackets for a given date.
     * By default returns only base (non-dependent) taxes.
     */
    public static function getEffectiveBrackets($date = null)
    {
        return static::active()->standalone()->base()->effectiveOn($date)->ordered()->get();
    }

    /**
     * Get active and effective standalone DEPENDENT tax brackets for a given date.
     */
    public static function getEffectiveDependentBrackets($date = null)
    {
        return static::active()->standalone()->dependent()->effectiveOn($date)->ordered()->get();
    }

    /**
     * Get active and effective DEPENDENT brackets that belong to a tax group.
     */
    public static function getEffectiveDependentGroupBrackets($date = null)
    {
        return static::active()->whereNotNull('tax_group_id')->dependent()->effectiveOn($date)->ordered()->get();
    }

    /**
     * Check if this bracket is currently effective.
     */
    public function isEffective($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        $fromOk = is_null($this->effective_from) || $this->effective_from <= $date;
        $toOk = is_null($this->effective_to) || $this->effective_to >= $date;
        
        return $fromOk && $toOk;
    }
}
