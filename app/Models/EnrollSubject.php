<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class EnrollSubject extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'program_id', 'semester_id', 'section_id', 'status',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'enroll_subject_subject', 'enroll_subject_id', 'subject_id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        // Load relationships if not loaded
        try {
            $this->loadMissing(['program', 'semester', 'section']);
        } catch (\Exception $e) {
            // Silently handle loading errors
        }
        
        // Get program title with fallback
        $programTitle = 'Unknown Program';
        if ($this->program) {
            $programTitle = $this->program->title ?? 'Program #' . $this->program_id;
        } elseif ($this->program_id) {
            $program = \App\Models\Program::find($this->program_id);
            $programTitle = $program ? ($program->title ?? 'Program #' . $this->program_id) : 'Program #' . $this->program_id;
        }
        
        // Get semester title with fallback
        $semesterTitle = 'Unknown Semester';
        if ($this->semester) {
            $semesterTitle = $this->semester->title ?? 'Semester #' . $this->semester_id;
        } elseif ($this->semester_id) {
            $semester = \App\Models\Semester::find($this->semester_id);
            $semesterTitle = $semester ? ($semester->title ?? 'Semester #' . $this->semester_id) : 'Semester #' . $this->semester_id;
        }
        
        // Get section title with fallback
        $sectionTitle = 'Unknown Section';
        if ($this->section) {
            $sectionTitle = $this->section->title ?? 'Section #' . $this->section_id;
        } elseif ($this->section_id) {
            $section = \App\Models\Section::find($this->section_id);
            $sectionTitle = $section ? ($section->title ?? 'Section #' . $this->section_id) : 'Section #' . $this->section_id;
        }
        
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        $subjectCount = 0;
        
        try {
            $subjectCount = $this->subjects()->count();
        } catch (\Exception $e) {
            // If subjects relationship fails, count will remain 0
        }
        
        return "Enroll Subject {$event}: {$programTitle} - {$semesterTitle} - {$sectionTitle}, Subjects: {$subjectCount}, Status: {$status}";
    }
}
