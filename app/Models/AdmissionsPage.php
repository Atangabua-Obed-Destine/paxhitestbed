<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionsPage extends Model
{
    use HasFactory;

    protected $table = 'web_admissions_pages';

    protected $fillable = [
        'language_id',
        'banner_image',
        'title',
        'subtitle',
        'description',
        'requirements',
        'process_steps',
        'contact_info',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
    ];

    protected $casts = [
        'process_steps' => 'array',
        'status' => 'integer',
    ];

    /**
     * Get the language associated with the admissions page.
     */
    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get active admissions page by language.
     */
    public static function getByLanguage($language_id = null)
    {
        $language_id = $language_id ?? Language::version()->id;
        
        return self::where('language_id', $language_id)
                   ->where('status', 1)
                   ->first();
    }
}
