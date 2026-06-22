<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A document requirement that belongs to a specific degree type's application form.
 */
class DegreeTypeDocument extends Model
{
    protected $fillable = [
        'degree_type_id', 'doc_key', 'label', 'description',
        'required', 'assign_to_column', 'sort_order', 'status',
    ];

    protected $casts = [
        'required' => 'boolean',
        'status' => 'boolean',
    ];

    public function degreeType()
    {
        return $this->belongsTo(DegreeType::class, 'degree_type_id');
    }
}
