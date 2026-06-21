<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Faculty extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sector_id', 'title', 'slug', 'shortcode', 'matric_code', 'status',
        'description', 'excerpt', 'featured_image', 'banner_image',
        'dean_name', 'dean_photo', 'email', 'phone', 'website',
    ];

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id', 'id');
    }

    public function academicDepartments()
    {
        return $this->hasMany(AcademicDepartment::class, 'faculty_id', 'id');
    }

    public function programs()
    {
        return $this->hasMany(Program::class, 'faculty_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $shortcode = $this->shortcode ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Faculty {$event}: {$title} ({$shortcode}), Status: {$status}";
    }
}
