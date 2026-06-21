<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenateSignature extends Model
{
    protected $table = 'senate_signatures';

    protected $fillable = [
        'senate_deliberation_id',
        'signatory_name',
        'signatory_position',
        'signed_at',
        'notes',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /* ─── Relationships ─── */

    public function deliberation(): BelongsTo
    {
        return $this->belongsTo(SenateDeliberation::class, 'senate_deliberation_id');
    }
}
