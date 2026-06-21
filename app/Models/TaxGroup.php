<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TaxGroup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'code', 'description', 'is_progressive', 'status', 
        'effective_from', 'effective_to', 'display_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_progressive' => 'boolean',
        'status' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * Get the tax brackets for this group.
     */
    public function brackets()
    {
        return $this->hasMany(TaxSetting::class, 'tax_group_id')
                    ->orderBy('bracket_order', 'asc')
                    ->orderBy('min_amount', 'asc');
    }

    /**
     * Get active brackets for this group.
     */
    public function activeBrackets()
    {
        return $this->brackets()->where('status', 1);
    }

    /**
     * Scope a query to only include active tax groups.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include groups effective on a given date.
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
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc')->orderBy('title', 'asc');
    }

    /**
     * Get active and effective tax groups with their brackets.
     */
    public static function getEffectiveGroups($date = null)
    {
        return static::active()
            ->effectiveOn($date)
            ->ordered()
            ->with(['brackets' => function($query) use ($date) {
                $query->where('status', 1)
                      ->where(function($q) use ($date) {
                          $date = $date ? Carbon::parse($date) : Carbon::today();
                          $q->whereNull('effective_from')
                            ->orWhere('effective_from', '<=', $date);
                      })
                      ->where(function($q) use ($date) {
                          $date = $date ? Carbon::parse($date) : Carbon::today();
                          $q->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $date);
                      })
                      ->orderBy('bracket_order', 'asc')
                      ->orderBy('min_amount', 'asc');
            }])
            ->get();
    }

    /**
     * Calculate progressive tax for a given salary.
     * 
     * @param float $salary The salary to calculate tax on
     * @param \Illuminate\Support\Collection|null $exemptions Staff exemptions keyed by tax_setting_id
     * @return array ['amount' => float, 'breakdown' => array]
     */
    public function calculateTax($salary, $exemptions = null)
    {
        $breakdown = [];

        $brackets = $this->brackets()->where('status', 1)->get();

        // Step lookup: a salary is taxed by the SINGLE applicable bracket — the
        // active bracket with the highest min_amount that does not exceed the
        // salary. Tax is applied once (never summed across bands). This matches
        // the institution's tax-table design (each band = the tax for that level).
        $bracket = null;
        foreach ($brackets as $candidate) {
            if ($salary >= $candidate->min_amount) {
                if ($bracket === null || $candidate->min_amount >= $bracket->min_amount) {
                    $bracket = $candidate;
                }
            }
        }

        if ($bracket === null) {
            // Salary is below every band — no tax in this group.
            return [
                'group_id' => $this->id,
                'group_title' => $this->title,
                'is_progressive' => $this->is_progressive,
                'amount' => 0,
                'breakdown' => [],
            ];
        }

        // Check for exemption (expired exemptions do not apply)
        $isExempt = $exemptions && $exemptions->has($bracket->id);
        $exemption = $isExempt ? $exemptions->get($bracket->id) : null;
        if ($exemption && $exemption->isExpired()) {
            $isExempt = false;
            $exemption = null;
        }

        // Tax-free allowance applies to percentage taxes
        $taxableAmount = max(0, $salary - $bracket->max_no_taxable_amount);

        $bracketTax = 0;
        $taxInfo = [
            'bracket_id' => $bracket->id,
            'title' => $bracket->title,
            'range' => $bracket->min_amount . ' - ' . $bracket->max_amount,
            'taxable_amount' => $taxableAmount,
            'rate' => null,
            'tax_amount' => 0,
            'is_exempt' => $isExempt,
            'custom_rate' => false,
        ];

        if ($isExempt) {
            // Handle exemption with possible custom rate
            if ($exemption->custom_percentage && $bracket->tax_type == 1) {
                $bracketTax = ($taxableAmount / 100) * $exemption->custom_percentage;
                $taxInfo['rate'] = $exemption->custom_percentage . '% (custom)';
                $taxInfo['custom_rate'] = true;
            } elseif ($exemption->custom_fixed_amount && $bracket->tax_type == 2) {
                $bracketTax = $exemption->custom_fixed_amount;
                $taxInfo['rate'] = $exemption->custom_fixed_amount . ' (custom fixed)';
                $taxInfo['custom_rate'] = true;
            }
            // Else fully exempt, tax remains 0
        } else {
            // Normal tax calculation
            if ($bracket->tax_type == 2) {
                // Fixed amount
                $bracketTax = $bracket->fixed_amount;
                $taxInfo['rate'] = $bracket->fixed_amount . ' (fixed)';
            } else {
                // Percentage
                $bracketTax = ($taxableAmount / 100) * $bracket->percentange;
                $taxInfo['rate'] = $bracket->percentange . '%';
            }
        }

        $taxInfo['tax_amount'] = $bracketTax;
        $breakdown[] = $taxInfo;

        return [
            'group_id' => $this->id,
            'group_title' => $this->title,
            'is_progressive' => $this->is_progressive,
            'amount' => $bracketTax,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Get the single applicable bracket for a salary using step-lookup
     * (highest active min_amount ≤ salary), or null if salary is below all bands.
     * Shared so employer-side calculation selects the same bracket as calculateTax().
     */
    public function applicableBracket($salary)
    {
        $bracket = null;
        foreach ($this->brackets()->where('status', 1)->get() as $candidate) {
            if ($salary >= $candidate->min_amount) {
                if ($bracket === null || $candidate->min_amount >= $bracket->min_amount) {
                    $bracket = $candidate;
                }
            }
        }
        return $bracket;
    }
}
