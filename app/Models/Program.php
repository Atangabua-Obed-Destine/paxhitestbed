<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class Program extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'faculty_id', 'academic_department_id', 'degree_type_id', 'academic_level', 'title', 'slug', 'shortcode', 'registration', 'status',
        'description', 'excerpt', 'featured_image', 'banner_image', 
        'duration', 'credit', 'validity_years', 'requirements', 'career_prospects',
    ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    public function academicDepartment()
    {
        return $this->belongsTo(AcademicDepartment::class, 'academic_department_id');
    }

    public function degreeType()
    {
        return $this->belongsTo(DegreeType::class, 'degree_type_id');
    }

    public function batches()
    {
        return $this->belongsToMany(Batch::class, 'batch_program', 'program_id', 'batch_id');
    }

    public function semesters()
    {
        return $this->belongsToMany(Semester::class, 'program_semester', 'program_id', 'semester_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(Session::class, 'program_session', 'program_id', 'session_id');
    }

    public function semesterSections()
    {
        return $this->hasMany(ProgramSemesterSection::class, 'program_id', 'id');
    }

    public function studentEnrolls()
    {
        return $this->hasMany(StudentEnroll::class, 'program_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'program_subject', 'program_id', 'subject_id');
    }

    public function rooms()
    {
        return $this->belongsToMany(ClassRoom::class, 'program_class_room', 'program_id', 'class_room_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'program_id', 'id');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoutine::class, 'program_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_program', 'program_id', 'user_id');
    }

    public function contents()
    {
        return $this->hasMany(Content::class, 'program_id', 'id');
    }

    /**
     * Check if program is undergraduate level
     * 
     * @return bool
     */
    public function isUndergraduate()
    {
        return $this->academic_level === 'A';
    }

    /**
     * Check if program is masters level
     * 
     * @return bool
     */
    public function isMasters()
    {
        return $this->academic_level === 'M';
    }

    /**
     * Check if program is doctoral level
     * 
     * @return bool
     */
    public function isDoctoral()
    {
        return $this->academic_level === 'D';
    }

    /**
     * Get human-readable academic level name
     * 
     * @return string
     */
    public function getAcademicLevelNameAttribute()
    {
        return match($this->academic_level) {
            'A' => 'Undergraduate',
            'M' => 'Masters',
            'D' => 'Doctoral',
            default => 'Unknown',
        };
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
        
        // Get faculty name with fallback
        $facultyName = 'Unknown Faculty';
        if ($this->faculty) {
            $facultyName = $this->faculty->title ?? 'Faculty #' . $this->faculty_id;
        } elseif ($this->faculty_id) {
            $faculty = \App\Models\Faculty::find($this->faculty_id);
            $facultyName = $faculty ? ($faculty->title ?? 'Faculty #' . $this->faculty_id) : 'Faculty #' . $this->faculty_id;
        }
        
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Program {$event}: {$title} ({$shortcode}), Faculty: {$facultyName}, Status: {$status}";
    }
}
