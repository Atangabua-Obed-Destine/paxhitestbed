<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedLoginAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'user_type',
        'attempts',
        'last_attempt_at',
        'blocked_until',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'blocked_until' => 'datetime',
    ];

    /**
     * Check if this IP/email combo is currently blocked
     */
    public function isBlocked(): bool
    {
        if (!$this->blocked_until) {
            return false;
        }

        return now()->lessThan($this->blocked_until);
    }

    /**
     * Get remaining block time in minutes
     */
    public function getRemainingBlockTime(): ?int
    {
        if (!$this->isBlocked()) {
            return null;
        }

        return now()->diffInMinutes($this->blocked_until);
    }
}
