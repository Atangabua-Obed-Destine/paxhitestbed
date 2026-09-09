<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One queued or delivered push to EdutrustPay.
 */
class EdutrustPayOutbox extends Model
{
    protected $table = 'edutrustpay_outbox';

    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    protected $fillable = [
        'kind', 'period', 'sequence', 'payload', 'payload_hash',
        'status', 'attempts', 'next_attempt_at', 'delivered_at',
        'last_status_code', 'last_response',
    ];

    protected $casts = [
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'attempts' => 'integer',
        'sequence' => 'integer',
    ];

    /**
     * Items due for a delivery attempt now.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::PENDING)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id');
    }

    public function isDelivered(): bool
    {
        return $this->status === self::DELIVERED;
    }
}
