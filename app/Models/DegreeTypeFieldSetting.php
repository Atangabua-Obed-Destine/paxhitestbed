<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-degree-type override of a global application form field/section toggle.
 */
class DegreeTypeFieldSetting extends Model
{
    protected $fillable = [
        'degree_type_id', 'slug', 'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function degreeType()
    {
        return $this->belongsTo(DegreeType::class, 'degree_type_id');
    }
}
