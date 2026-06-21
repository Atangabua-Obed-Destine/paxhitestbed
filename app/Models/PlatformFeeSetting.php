<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PlatformFeeSetting extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'welcome_message',
        'fee_amount',
        'is_enabled',
        'payment_instructions',
        'currency',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'fee_amount' => 'decimal:2',
        'is_enabled' => 'boolean',
        'status' => 'boolean',
    ];
}
