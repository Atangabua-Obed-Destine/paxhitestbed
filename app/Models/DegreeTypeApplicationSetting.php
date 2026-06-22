<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-degree-type application settings: intro / requirements text and the
 * admission-fee configuration for that degree type's form.
 */
class DegreeTypeApplicationSetting extends Model
{
    protected $fillable = [
        'degree_type_id', 'intro_html', 'requirements_html',
        'fee_enabled', 'fee_amount', 'fee_due_days', 'fee_instructions',
        'acceptance_letter_enabled', 'acceptance_letter_html',
    ];

    protected $casts = [
        'fee_enabled' => 'boolean',
        'fee_amount' => 'decimal:2',
        'fee_due_days' => 'integer',
        'acceptance_letter_enabled' => 'boolean',
    ];

    public function degreeType()
    {
        return $this->belongsTo(DegreeType::class, 'degree_type_id');
    }
}
