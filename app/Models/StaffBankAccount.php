<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class StaffBankAccount extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'bank_name', 'account_name', 'account_number', 'branch', 'ifsc_code', 'is_default', 'status',
    ];

    /**
     * Get the user (staff) that owns this bank account.
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }
}
