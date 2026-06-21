<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class PaymentAccountTransaction extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_account_id',
        'transaction_type',
        'amount',
        'transaction_date',
        'title',
        'description',
        'reference_type',
        'reference_id',
        'payment_method',
        'payment_reference',
        'balance_after',
        'attach',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    /**
     * Get the payment account
     */
    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    /**
     * Get the creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the referenced model (polymorphic)
     */
    public function reference()
    {
        // This would return the related model based on reference_type and reference_id
        // Can be expanded when integrating with fees, expenses, etc.
        if ($this->reference_type && $this->reference_id) {
            $modelMap = [
                'fees' => \App\Models\Fee::class,
                'expense' => \App\Models\Expense::class,
                'income' => \App\Models\Income::class,
                'transfer' => \App\Models\PaymentAccountTransfer::class,
            ];
            
            if (isset($modelMap[$this->reference_type])) {
                return $modelMap[$this->reference_type]::find($this->reference_id);
            }
        }
        
        return null;
    }
}
