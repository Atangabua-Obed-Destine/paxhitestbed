<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class IpWhitelist extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'ip_address',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this whitelist entry
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if an IP is whitelisted and active
     */
    public static function isWhitelisted(string $ip): bool
    {
        return static::where('ip_address', $ip)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all active whitelisted IPs
     */
    public static function getActiveIps(): array
    {
        return static::where('is_active', true)
            ->pluck('ip_address')
            ->toArray();
    }
}
