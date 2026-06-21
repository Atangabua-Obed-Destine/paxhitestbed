<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StaffTaxExemption extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'tax_setting_id', 'reason', 'custom_percentage', 'custom_fixed_amount', 'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'expires_at' => 'date',
    ];

    /**
     * Get the user (staff) that is exempt from this tax.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tax setting that this exemption applies to.
     */
    public function taxSetting()
    {
        return $this->belongsTo(TaxSetting::class);
    }

    /**
     * Scope a query to only include non-expired exemptions.
     */
    public function scopeNotExpired($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        return $query->where(function($q) use ($date) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>=', $date);
        });
    }

    /**
     * Check if this exemption has expired.
     */
    public function isExpired($date = null)
    {
        if (is_null($this->expires_at)) {
            return false;
        }
        
        $date = $date ? Carbon::parse($date) : Carbon::today();
        return $this->expires_at < $date;
    }

    /**
     * Check if this exemption is currently active.
     */
    public function isActive($date = null)
    {
        return !$this->isExpired($date);
    }
}
