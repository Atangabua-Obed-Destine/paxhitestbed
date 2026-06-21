<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\Auditable;
use App\User;

class AcademicDepartment extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'faculty_id', 'title', 'shortcode', 'slug', 'description', 
        'head_of_department_id', 'email', 'phone', 'sort_order', 'status'
    ];

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($department) {
            if (empty($department->slug)) {
                $department->slug = Str::slug($department->title);
            }
        });

        static::updating(function ($department) {
            if ($department->isDirty('title')) {
                $department->slug = Str::slug($department->title);
            }
        });
    }

    /**
     * Relationship to Faculty
     */
    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    /**
     * Relationship to Head of Department (User)
     */
    public function headOfDepartment()
    {
        return $this->belongsTo(User::class, 'head_of_department_id');
    }

    /**
     * Relationship to Programs
     */
    public function programs()
    {
        return $this->hasMany(Program::class, 'academic_department_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        // Load faculty relationship if not loaded
        try {
            if (!$this->relationLoaded('faculty') && $this->faculty_id) {
                $this->load('faculty');
            }
        } catch (\Exception $e) {
            // Silently handle loading errors
        }

        $title = $this->title ?? 'N/A';
        $shortcode = $this->shortcode ?? 'N/A';
        $facultyName = $this->faculty ? ($this->faculty->title ?? 'Unknown Faculty') : 'Unknown Faculty';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Academic Department {$event}: {$title} ({$shortcode}), Faculty: {$facultyName}, Status: {$status}";
    }
}
