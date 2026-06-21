<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\Auditable;

class FiscalYear extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'is_closed',
        'closed_by',
        'closed_at',
        'closing_note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // When creating or updating, ensure only one active fiscal year
        static::saving(function ($fiscalYear) {
            if ($fiscalYear->is_active) {
                // Deactivate all other fiscal years
                static::where('id', '!=', $fiscalYear->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * Get accounting periods for this fiscal year
     */
    public function accountingPeriods()
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }

    /**
     * Get journal entries for this fiscal year
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Get the user who closed this year
     */
    public function closedBy()
    {
        return $this->belongsTo(\App\User::class, 'closed_by');
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
     * Scope for active fiscal year
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_closed', false);
    }

    /**
     * Scope for open fiscal years
     */
    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    /**
     * Check if a date falls within this fiscal year
     */
    public function containsDate($date)
    {
        $date = Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get the current active fiscal year
     */
    public static function getActiveFiscalYear()
    {
        return static::where('is_active', true)->where('is_closed', false)->first();
    }

    /**
     * Get duration in months
     */
    public function getDurationInMonthsAttribute()
    {
        return $this->start_date->diffInMonths($this->end_date) + 1;
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $name = $this->name ?? 'N/A';
        $startDate = $this->start_date ? $this->start_date->format('Y-m-d') : 'N/A';
        $endDate = $this->end_date ? $this->end_date->format('Y-m-d') : 'N/A';
        $isActive = $this->is_active ? 'Active' : 'Inactive';
        $isClosed = $this->is_closed ? 'Closed' : 'Open';
        
        return "Fiscal Year {$event}: {$name} ({$startDate} to {$endDate}), Status: {$isActive}, {$isClosed}";
    }
}
