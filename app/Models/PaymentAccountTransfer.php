<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class PaymentAccountTransfer extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'transfer_date',
        'note',
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
        'transfer_date' => 'date',
    ];

    /**
     * Get the from account
     */
    public function fromAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'from_account_id');
    }

    /**
     * Get the to account
     */
    public function toAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'to_account_id');
    }

    /**
     * Get the creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
