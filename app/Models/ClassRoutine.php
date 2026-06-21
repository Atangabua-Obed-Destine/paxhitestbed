<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class ClassRoutine extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'teacher_id', 'subject_id', 'room_id', 'session_id', 'program_id', 'semester_id', 'section_id', 'start_time', 'end_time', 'day', 'status',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function room()
    {
        return $this->belongsTo(ClassRoom::class, 'room_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

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

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        // Load relationships if not loaded
        try {
            $this->loadMissing(['teacher', 'subject', 'room']);
        } catch (\Exception $e) {
            // Silently handle loading errors
        }
        
        // Get teacher name with fallback
        $teacherName = 'Unknown Teacher';
        if ($this->teacher) {
            $teacherName = $this->teacher->name ?? 'Teacher #' . $this->teacher_id;
        } elseif ($this->teacher_id) {
            $teacher = \App\User::find($this->teacher_id);
            $teacherName = $teacher ? ($teacher->name ?? 'Teacher #' . $this->teacher_id) : 'Teacher #' . $this->teacher_id;
        }
        
        // Get subject title with fallback
        $subjectTitle = 'Unknown Subject';
        if ($this->subject) {
            $subjectTitle = $this->subject->title ?? 'Subject #' . $this->subject_id;
        } elseif ($this->subject_id) {
            $subject = \App\Models\Subject::find($this->subject_id);
            $subjectTitle = $subject ? ($subject->title ?? 'Subject #' . $this->subject_id) : 'Subject #' . $this->subject_id;
        }
        
        // Get room title with fallback
        $roomTitle = 'Unknown Room';
        if ($this->room) {
            $roomTitle = $this->room->title ?? 'Room #' . $this->room_id;
        } elseif ($this->room_id) {
            $room = \App\Models\ClassRoom::find($this->room_id);
            $roomTitle = $room ? ($room->title ?? 'Room #' . $this->room_id) : 'Room #' . $this->room_id;
        }
        
        $day = $this->day ?? 'N/A';
        $startTime = $this->start_time ?? 'N/A';
        $endTime = $this->end_time ?? 'N/A';
        
        return "Class Routine {$event}: {$subjectTitle} by {$teacherName}, {$day} {$startTime}-{$endTime}, Room: {$roomTitle}";
    }
}
