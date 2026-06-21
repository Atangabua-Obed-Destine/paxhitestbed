<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class StudentEnroll extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_id', 'program_id', 'previous_program_id', 'program_change_reason', 'is_program_change',
        'session_id', 'semester_id', 'section_id', 'status', 'matricule',
        'religion', 'is_catholic_baptised', 'is_confirmed', 'has_first_communion',
        'created_by', 'updated_by', 'bypass_payment_restriction',
    ];

    protected $casts = [
        'is_catholic_baptised' => 'boolean',
        'is_confirmed' => 'boolean',
        'has_first_communion' => 'boolean',
        'bypass_payment_restriction' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function previousProgram()
    {
        return $this->belongsTo(Program::class, 'previous_program_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
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
        return $this->belongsToMany(Subject::class, 'student_enroll_subject', 'student_enroll_id', 'subject_id');
    }

    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class, 'student_enroll_id', 'id');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class, 'student_enroll_id', 'id');
    }

    public function subjectMarks()
    {
        return $this->hasMany(SubjectMarking::class, 'student_enroll_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(StudentAssignment::class, 'student_enroll_id', 'id');
    }

    public function fees()
    {
        return $this->hasMany(Fee::class, 'student_enroll_id', 'id');
    }

    public function resitRequests()
    {
        return $this->hasMany(ResitRequest::class, 'student_enroll_id');
    }

    public function religionDetail()
    {
        return $this->belongsTo(Religion::class, 'religion');
    }

    /**
     * Get the matricule for this enrollment.
     * Returns enrollment-specific matricule if set, otherwise falls back to student's student_id
     * This ensures backward compatibility while supporting multiple matricules per student
     * 
     * @return string
     */
    public function getMatriculeAttribute($value)
    {
        // If enrollment has its own matricule, use it
        if ($value) {
            return $value;
        }

        // Otherwise, fall back to the student's student_id for backward compatibility
        if ($this->relationLoaded('student') && $this->student) {
            return $this->student->student_id;
        }

        // If student not loaded, try to load it
        try {
            $student = $this->student;
            return $student ? $student->student_id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get custom audit description.
     *
     * @param string $event
     * @return string
     */
    public function getAuditDescription($event)
    {
        // Force load relationships - use load() instead of checking if loaded
        try {
            $this->load(['student', 'program', 'semester', 'session']);
        } catch (\Exception $e) {
            // Silently handle any loading errors
        }

        // Get student name safely
        $studentName = 'Unknown Student';
        if ($this->student) {
            $firstName = $this->student->first_name ?? '';
            $lastName = $this->student->last_name ?? '';
            $studentName = trim($firstName . ' ' . $lastName);
            if (empty($studentName) && $this->student->student_id) {
                $studentName = 'Student #' . $this->student->student_id;
            }
        }
        // Fallback to foreign key if relationship fails
        if ($studentName === 'Unknown Student' && $this->student_id) {
            // Try to fetch student directly
            $student = \App\Models\Student::find($this->student_id);
            if ($student) {
                $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
            }
            if (empty($studentName)) {
                $studentName = 'Student #' . $this->student_id;
            }
        }

        // Get program name safely
        $programName = 'Unknown Program';
        if ($this->program) {
            $programName = $this->program->title ?? 'Program #' . $this->program_id;
        } elseif ($this->program_id) {
            // Try to fetch program directly
            $program = \App\Models\Program::find($this->program_id);
            if ($program && $program->title) {
                $programName = $program->title;
            } else {
                $programName = 'Program #' . $this->program_id;
            }
        }

        // Get semester name safely
        $semesterName = 'Unknown Semester';
        if ($this->semester) {
            $semesterName = $this->semester->title ?? 'Semester #' . $this->semester_id;
        } elseif ($this->semester_id) {
            // Try to fetch semester directly
            $semester = \App\Models\Semester::find($this->semester_id);
            if ($semester && $semester->title) {
                $semesterName = $semester->title;
            } else {
                $semesterName = 'Semester #' . $this->semester_id;
            }
        }

        // Get session name if available
        $sessionInfo = '';
        if ($this->session_id) {
            if ($this->session) {
                $sessionInfo = ', Session: ' . ($this->session->title ?? '');
            } else {
                $session = \App\Models\Session::find($this->session_id);
                if ($session && $session->title) {
                    $sessionInfo = ', Session: ' . $session->title;
                }
            }
        }

        // Generate description based on event
        if ($event === 'created') {
            return "Student enrolled: {$studentName} to {$programName} - {$semesterName}{$sessionInfo}";
        } elseif ($event === 'updated') {
            return "Student enrollment updated: {$studentName} in {$programName} - {$semesterName}{$sessionInfo}";
        } elseif ($event === 'deleted') {
            return "Student enrollment deleted: {$studentName} from {$programName} - {$semesterName}";
        }

        return "Student enrollment {$event}: {$studentName} - {$programName}";
    }
}
