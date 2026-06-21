<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampusLifeSection extends Model
{
    use HasFactory;

    protected $table = 'web_campus_life_sections';

    protected $fillable = [
        'language_id',
        'section_type',
        'title',
        'description',
        'image',
        'icon',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the language associated with the campus life section.
     */
    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get active sections by language and type.
     */
    public static function getByLanguage($language_id = null, $section_type = null)
    {
        $language_id = $language_id ?? Language::version()->id;
        
        $query = self::where('language_id', $language_id)
                     ->where('status', 1)
                     ->orderBy('sort_order');
        
        if ($section_type) {
            $query->where('section_type', $section_type);
        }
        
        return $query->get();
    }

    /**
     * Scope to filter by section type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('section_type', $type);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
