<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use Illuminate\Support\Str;

class DegreeType extends Model
{
    use Auditable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'shortcode', 'code_append_to_student_matricule', 'level', 'duration_years', 'min_credits', 
        'slug', 'description', 'requirements', 'sort_order', 'status',
        'is_hnd', 'is_postgraduate',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_hnd' => 'boolean',
        'is_postgraduate' => 'boolean',
    ];

    /**
     * Get the programs for this degree type.
     */
    public function programs()
    {
        return $this->hasMany(Program::class, 'degree_type_id');
    }

    /**
     * Per-degree-type application form configuration.
     */
    public function fieldSettings()
    {
        return $this->hasMany(DegreeTypeFieldSetting::class, 'degree_type_id');
    }

    public function applicationDocuments()
    {
        return $this->hasMany(DegreeTypeDocument::class, 'degree_type_id')->orderBy('sort_order');
    }

    public function applicationSetting()
    {
        return $this->hasOne(DegreeTypeApplicationSetting::class, 'degree_type_id');
    }

    /**
     * Boot method to auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($degreeType) {
            if (empty($degreeType->slug)) {
                $degreeType->slug = Str::slug($degreeType->title);
            }
        });

        static::updating(function ($degreeType) {
            if ($degreeType->isDirty('title') && empty($degreeType->slug)) {
                $degreeType->slug = Str::slug($degreeType->title);
            }
        });
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $shortcode = $this->shortcode ?? 'N/A';
        $level = $this->level ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Degree Type {$event}: {$title} ({$shortcode}), Level: {$level}, Status: {$status}";
    }
}
