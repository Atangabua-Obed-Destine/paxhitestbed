<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A qualification card that belongs to a specific degree type's application form.
 *
 * The upload slots a card shows are not stored here: they are the
 * DegreeTypeDocument rows whose qualification_group matches this qual_key.
 */
class DegreeTypeQualification extends Model
{
    protected $fillable = [
        'degree_type_id', 'qual_key', 'label', 'description',
        'required', 'sort_order', 'status',
    ];

    protected $casts = [
        'required' => 'boolean',
        'status' => 'boolean',
    ];

    public function degreeType()
    {
        return $this->belongsTo(DegreeType::class, 'degree_type_id');
    }

    /** The documents collected inside this card. */
    public function documents()
    {
        return $this->hasMany(DegreeTypeDocument::class, 'degree_type_id', 'degree_type_id')
            ->where('qualification_group', $this->qual_key);
    }
}
