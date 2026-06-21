<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Semester extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'year',
        'semester_type',
        'status',
        'is_resit',
        'parent_semester_id',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_resit' => 'boolean',
    ];

    // Semester type constants
    public const TYPE_FIRST = 1;
    public const TYPE_SECOND = 2;

    /**
     * Get semester type name
     */
    public function getSemesterTypeName(): string
    {
        return match($this->semester_type) {
            self::TYPE_FIRST => 'First Semester',
            self::TYPE_SECOND => 'Second Semester',
            default => 'Unknown',
        };
    }

    public function scopeResit($query)
    {
        return $query->where('is_resit', true);
    }

    public function scopeRegular($query)
    {
        return $query->where('is_resit', false);
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_semester', 'semester_id', 'program_id');
    }

    public function programSections()
    {
        return $this->hasMany(ProgramSemesterSection::class, 'semester_id', 'id');
    }

    public function studentEnrolls()
    {
        return $this->hasMany(StudentEnroll::class, 'semester_id');
    }

    public function enrollSubjects()
    {
        return $this->hasMany(EnrollSubject::class, 'semester_id');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoutine::class, 'semester_id', 'id');
    }

    public function contents()
    {
        return $this->hasMany(Content::class, 'semester_id', 'id');
    }

    /**
     * Get the parent semester (for resit semesters)
     */
    public function parentSemester()
    {
        return $this->belongsTo(Semester::class, 'parent_semester_id');
    }

    /**
     * Get child resit semesters
     */
    public function resitSemesters()
    {
        return $this->hasMany(Semester::class, 'parent_semester_id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $year = $this->year ?? 'N/A';
        $type = $this->getSemesterTypeName();
        $isResit = $this->is_resit ? 'Yes' : 'No';
        $status = $this->status ? 'Active' : 'Inactive';
        
        return "Semester {$event}: {$title} (Year: {$year}, {$type}), Resit: {$isResit}, Status: {$status}";
    }
}
