<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Transaction extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transactionable_id', 'transactionable_type', 'transaction_id', 'amount', 'type', 'created_by', 'updated_by',
    ];

    // Polymorphic relations
    public function transactionable()
    {
        return $this->morphTo();
    }
    
    /**
     * Custom audit description for better readability
     */
    public function getAuditDescription($event)
    {
        $amount = $this->amount ?? 0;
        $type = $this->type ?? 'unknown';
        return "Transaction {$event}: {$type} of amount {$amount}";
    }
}
