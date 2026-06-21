<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class MultiPaymentDistribution extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'multi_payment_id',
        'fee_id',
        'installment_id',
        'fee_amount',
        'amount_applied',
        'balance_before',
        'balance_after',
        'fee_status_after'
    ];

    protected $casts = [
        'fee_amount' => 'decimal:2',
        'amount_applied' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    // Relationships
    public function multiPayment()
    {
        return $this->belongsTo(MultiPayment::class);
    }

    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    public function installment()
    {
        return $this->belongsTo(\App\Models\PaymentPlanInstallment::class, 'installment_id');
    }
    
    /**
     * Custom audit description for better readability
     */
    public function getAuditDescription($event)
    {
        // Load relationships if not loaded
        if (!$this->relationLoaded('fee')) {
            try {
                $this->load('fee');
            } catch (\Exception $e) {
                // Relationship loading failed
            }
        }
        
        $amountApplied = $this->amount_applied ?? 0;
        
        // Get fee category name
        $feeName = 'Unknown Fee';
        if ($this->fee) {
            try {
                if (!$this->fee->relationLoaded('category')) {
                    $this->fee->load('category');
                }
                $feeName = $this->fee->category->title ?? 'Fee #' . $this->fee_id;
            } catch (\Exception $e) {
                $feeName = 'Fee #' . $this->fee_id;
            }
        } elseif ($this->fee_id) {
            // Fallback: try direct query
            $fee = \App\Models\Fee::with('category')->find($this->fee_id);
            if ($fee && $fee->category) {
                $feeName = $fee->category->title ?? 'Fee #' . $this->fee_id;
            } else {
                $feeName = 'Fee #' . $this->fee_id;
            }
        }
        
        return "Payment distribution {$event}: {$feeName}, Amount Applied: " . number_format($amountApplied, 2);
    }
}
