<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomoTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'environment',
        'fee_id',
        'application_id',
        'payment_receipt_id',
        'initiated_by_type',
        'initiated_by_id',
        'reference_id',
        'external_id',
        'msisdn',
        'amount',
        'currency',
        'status',
        'financial_transaction_id',
        'failure_reason',
        'raw_request',
        'raw_response',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'raw_request' => 'array',
        'raw_response' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }
}
