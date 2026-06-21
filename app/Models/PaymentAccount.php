<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class PaymentAccount extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'account_number',
        'account_type_id',
        'opening_balance',
        'current_balance',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'status' => 'boolean',
    ];

    /**
     * Get the account type
     */
    public function accountType()
    {
        return $this->belongsTo(PaymentAccountType::class, 'account_type_id');
    }

    /**
     * Get all transactions for this account
     */
    public function transactions()
    {
        return $this->hasMany(PaymentAccountTransaction::class, 'payment_account_id')->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Get transfers from this account
     */
    public function transfersFrom()
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'from_account_id');
    }

    /**
     * Get transfers to this account
     */
    public function transfersTo()
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'to_account_id');
    }

    /**
     * Get the user who created this account
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this account
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
