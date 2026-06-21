<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationGuardian extends Model
{
    protected $fillable = [
        'application_id',
        'full_name',
        'relationship',
        'type',
        'occupation',
        'email',
        'phone_primary',
        'phone_secondary',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
