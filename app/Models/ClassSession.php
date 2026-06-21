<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;
use Carbon\Carbon;
use App\Traits\Auditable;

class ClassSession extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'class_routine_id',
        'teacher_id',
        'subject_id',
        'program_id',
        'session_id',
        'semester_id',
        'section_id',
        'date',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'actual_duration_minutes',
        'status',
        'is_scheduled',
        'is_extra_class',
        'topic_covered',
        'learning_objectives',
        'teaching_methods',
        'materials_used',
        'assignments_given',
        'remarks',
        'class_rep_enroll_id',
        'logbook_delegated',
        'logbook_filled_by',
        'attendance_delegated',
        'hod_user_id',
        'lecturer_attendance_status',
        'lecturer_attendance_synced',
    ];

    protected $casts = [
        'date' => 'date',
        'is_scheduled' => 'boolean',
        'is_extra_class' => 'boolean',
        'logbook_delegated' => 'boolean',
        'attendance_delegated' => 'boolean',
        'lecturer_attendance_synced' => 'boolean',
    ];

    /**
     * Accessor for actual_start_time - converts TIME string to Carbon
     */
    public function getActualStartTimeAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }
        // If value already contains a date (datetime format), parse directly
        if (strlen($value) > 8) {
            return Carbon::parse($value);
        }
        // Combine with date for full datetime, or create time-only Carbon
        if ($this->attributes['date'] ?? null) {
            $dateStr = $this->attributes['date'];
            if ($dateStr instanceof \DateTime || $dateStr instanceof Carbon) {
                $dateStr = $dateStr->format('Y-m-d');
            } elseif (is_string($dateStr) && strlen($dateStr) > 10) {
                // Extract just the date part if it includes time (e.g., "2025-12-08 00:00:00")
                $dateStr = substr($dateStr, 0, 10);
            }
            return Carbon::parse($dateStr . ' ' . $value);
        }
        return Carbon::createFromFormat('H:i:s', $value);
    }

    /**
     * Accessor for actual_end_time - converts TIME string to Carbon
     */
    public function getActualEndTimeAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }
        // If value already contains a date (datetime format), parse directly
        if (strlen($value) > 8) {
            return Carbon::parse($value);
        }
        // Combine with date for full datetime, or create time-only Carbon
        if ($this->attributes['date'] ?? null) {
            $dateStr = $this->attributes['date'];
            if ($dateStr instanceof \DateTime || $dateStr instanceof Carbon) {
                $dateStr = $dateStr->format('Y-m-d');
            } elseif (is_string($dateStr) && strlen($dateStr) > 10) {
                // Extract just the date part if it includes time (e.g., "2025-12-08 00:00:00")
                $dateStr = substr($dateStr, 0, 10);
            }
            return Carbon::parse($dateStr . ' ' . $value);
        }
        return Carbon::createFromFormat('H:i:s', $value);
    }

    /**
     * Accessor for scheduled_start_time - converts TIME string to Carbon
     */
    public function getScheduledStartTimeAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }
        // If value already contains a date (datetime format), parse directly
        if (strlen($value) > 8) {
            return Carbon::parse($value);
        }
        if ($this->attributes['date'] ?? null) {
            $dateStr = $this->attributes['date'];
            if ($dateStr instanceof \DateTime || $dateStr instanceof Carbon) {
                $dateStr = $dateStr->format('Y-m-d');
            } elseif (is_string($dateStr) && strlen($dateStr) > 10) {
                // Extract just the date part if it includes time (e.g., "2025-12-08 00:00:00")
                $dateStr = substr($dateStr, 0, 10);
            }
            return Carbon::parse($dateStr . ' ' . $value);
        }
        return Carbon::createFromFormat('H:i:s', $value);
    }

    /**
     * Accessor for scheduled_end_time - converts TIME string to Carbon
     */
    public function getScheduledEndTimeAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }
        // If value already contains a date (datetime format), parse directly
        if (strlen($value) > 8) {
            return Carbon::parse($value);
        }
        if ($this->attributes['date'] ?? null) {
            $dateStr = $this->attributes['date'];
            if ($dateStr instanceof \DateTime || $dateStr instanceof Carbon) {
                $dateStr = $dateStr->format('Y-m-d');
            } elseif (is_string($dateStr) && strlen($dateStr) > 10) {
                // Extract just the date part if it includes time (e.g., "2025-12-08 00:00:00")
                $dateStr = substr($dateStr, 0, 10);
            }
            return Carbon::parse($dateStr . ' ' . $value);
        }
        return Carbon::createFromFormat('H:i:s', $value);
    }

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Lecturer attendance status constants (matches StaffHourlyAttendance)
     */
    const ATTENDANCE_PRESENT = 1;
    const ATTENDANCE_ABSENT = 2;
    const ATTENDANCE_LATE = 3;
    const ATTENDANCE_HALF_DAY = 4;

    /**
     * Get the scheduled class routine
     */
    public function classRoutine()
    {
        return $this->belongsTo(ClassRoutine::class, 'class_routine_id');
    }

    /**
     * Get the teacher/lecturer
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the subject
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * Get the program
     */
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * Get the session (academic year)
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    /**
     * Get the semester
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Get the section
     */
    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * Get the class representative
     */
    public function classRep()
    {
        return $this->belongsTo(StudentEnroll::class, 'class_rep_enroll_id');
    }

    /**
     * Get the HOD user
     */
    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }

    /**
     * Get the logbook entry
     */
    public function logbook()
    {
        return $this->hasOne(ClassLogbook::class, 'class_session_id');
    }

    /**
     * Get student attendances for this session
     */
    public function studentAttendances()
    {
        return $this->hasMany(StudentClassAttendance::class, 'class_session_id');
    }

    /**
     * Get chat messages for this session
     */
    public function messages()
    {
        return $this->hasMany(ClassSessionMessage::class, 'class_session_id');
    }

    /**
     * Get student notes for this session
     */
    public function studentNotes()
    {
        return $this->hasMany(StudentClassNote::class, 'class_session_id');
    }

    /**
     * Get alerts for this session
     */
    public function alerts()
    {
        return $this->hasMany(ClassSessionAlert::class, 'class_session_id');
    }

    /**
     * Get questions for this session
     */
    public function questions()
    {
        return $this->hasMany(ClassSessionQuestion::class, 'class_session_id');
    }

    /**
     * Get student attendances for this session
     */
    public function attendances()
    {
        return $this->hasMany(StudentClassAttendance::class, 'class_session_id');
    }

    /**
     * Scope for filtering by date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope for filtering by teacher
     */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for scheduled classes only
     */
    public function scopeScheduledOnly($query)
    {
        return $query->where('is_scheduled', true);
    }

    /**
     * Scope for extra classes only
     */
    public function scopeExtraOnly($query)
    {
        return $query->where('is_extra_class', true);
    }

    /**
     * Get scheduled duration in minutes
     */
    public function getScheduledDurationMinutesAttribute()
    {
        if ($this->scheduled_start_time && $this->scheduled_end_time) {
            $start = \Carbon\Carbon::parse($this->scheduled_start_time);
            $end = \Carbon\Carbon::parse($this->scheduled_end_time);
            return $start->diffInMinutes($end);
        }
        return 0;
    }

    /**
     * Get the time when the first student clocked in (across all joint sessions)
     */
    public function getFirstClockInTimeAttribute()
    {
        // Get all joint session IDs (same teacher, date, subject, start time)
        $jointSessionIds = self::where('teacher_id', $this->teacher_id)
            ->where('date', $this->attributes['date'] ?? $this->date)
            ->where('subject_id', $this->subject_id)
            ->where('scheduled_start_time', $this->attributes['scheduled_start_time'] ?? null)
            ->pluck('id')
            ->toArray();
        
        if (empty($jointSessionIds)) {
            $jointSessionIds = [$this->id];
        }
        
        // Find first clock-in across all joint sessions
        $firstAttendance = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)
            ->whereNotNull('clock_in_time')
            ->orderBy('clock_in_time', 'asc')
            ->first();
        
        return $firstAttendance ? $firstAttendance->clock_in_time : null;
    }

    /**
     * Get the calculated end time based on first clock-in + scheduled duration
     * This is the expected end time based on when class actually started (first student clocked in)
     */
    public function getCalculatedEndTimeAttribute()
    {
        $firstClockIn = $this->first_clock_in_time;
        
        if ($firstClockIn && $this->scheduled_duration_minutes > 0) {
            return Carbon::parse($firstClockIn)->addMinutes($this->scheduled_duration_minutes);
        }
        
        // Fall back to scheduled end time if no students have clocked in yet
        return $this->scheduled_end_time;
    }

    /**
     * Get remaining minutes until calculated end time
     */
    public function getRemainingMinutesAttribute()
    {
        $calculatedEnd = $this->calculated_end_time;
        
        if ($calculatedEnd && $this->status === self::STATUS_IN_PROGRESS) {
            $now = Carbon::now();
            if ($now->lt($calculatedEnd)) {
                return $now->diffInMinutes($calculatedEnd);
            }
            return 0; // Class time has ended
        }
        
        return null;
    }

    /**
     * Check if class time has exceeded the calculated end time
     */
    public function getIsOvertimeAttribute()
    {
        $calculatedEnd = $this->calculated_end_time;
        
        if ($calculatedEnd && $this->status === self::STATUS_IN_PROGRESS) {
            return Carbon::now()->gt($calculatedEnd);
        }
        
        return false;
    }

    /**
     * Get overtime minutes (how long past the calculated end time)
     */
    public function getOvertimeMinutesAttribute()
    {
        $calculatedEnd = $this->calculated_end_time;
        
        if ($calculatedEnd && $this->status === self::STATUS_IN_PROGRESS && $this->is_overtime) {
            return Carbon::now()->diffInMinutes($calculatedEnd);
        }
        
        return 0;
    }

    /**
     * Get elapsed minutes since first student clocked in
     */
    public function getElapsedMinutesAttribute()
    {
        $firstClockIn = $this->first_clock_in_time;
        
        if ($firstClockIn && $this->status === self::STATUS_IN_PROGRESS) {
            return Carbon::parse($firstClockIn)->diffInMinutes(Carbon::now());
        }
        
        return 0;
    }

    /**
     * Get progress percentage based on elapsed time vs scheduled duration
     */
    public function getProgressPercentageAttribute()
    {
        $scheduledDuration = $this->scheduled_duration_minutes;
        $elapsed = $this->elapsed_minutes;
        
        if ($scheduledDuration > 0 && $elapsed > 0) {
            $percentage = ($elapsed / $scheduledDuration) * 100;
            return min(100, round($percentage, 1)); // Cap at 100%
        }
        
        return 0;
    }

    /**
     * Get duration percentage (actual vs scheduled)
     */
    public function getDurationPercentageAttribute()
    {
        $scheduled = $this->scheduled_duration_minutes;
        if ($scheduled > 0 && $this->actual_duration_minutes > 0) {
            return round(($this->actual_duration_minutes / $scheduled) * 100, 2);
        }
        return 0;
    }

    /**
     * Check if minimum duration is met
     */
    public function meetsMinimumDuration($minimumPercentage = 70)
    {
        return $this->duration_percentage >= $minimumPercentage;
    }

    /**
     * Get present students count
     */
    public function getPresentCountAttribute()
    {
        return $this->studentAttendances()->whereNotNull('clock_in_time')->count();
    }

    /**
     * Get clocked out students count
     */
    public function getClockedOutCountAttribute()
    {
        return $this->studentAttendances()->where('has_clocked_out', true)->count();
    }

    /**
     * Start the class session
     */
    public function start()
    {
        $this->update([
            'actual_start_time' => now(),
            'status' => self::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * End the class session (and all joint sessions if applicable)
     */
    public function end($endTime = null, $endJointSessions = true)
    {
        $endTime = $endTime ?? now();
        
        // End this session
        $this->endSingle($endTime);
        
        // End all joint sessions if applicable
        if ($endJointSessions) {
            $jointSessions = ClassSession::where('teacher_id', $this->teacher_id)
                ->where('date', $this->date)
                ->where('subject_id', $this->subject_id)
                ->where('scheduled_start_time', $this->scheduled_start_time)
                ->where('id', '!=', $this->id)
                ->where('status', self::STATUS_IN_PROGRESS)
                ->get();
            
            foreach ($jointSessions as $jointSession) {
                $jointSession->endSingle($endTime);
            }
        }
    }
    
    /**
     * End a single session (internal method - use end() to handle joint sessions)
     */
    protected function endSingle($endTime = null)
    {
        $endTime = $endTime ?? now();
        $startTime = \Carbon\Carbon::parse($this->actual_start_time);
        $duration = $startTime->diffInMinutes(\Carbon\Carbon::parse($endTime));
        
        $this->update([
            'actual_end_time' => $endTime,
            'actual_duration_minutes' => $duration,
            'status' => self::STATUS_COMPLETED,
        ]);
        
        // Mark students who clocked in but never clocked out as Incomplete/Absent
        $this->markIncompleteAttendances();
    }

    /**
     * Mark students who clocked in but never clocked out as Incomplete (Absent)
     * This is called when class ends to finalize attendance
     */
    public function markIncompleteAttendances()
    {
        // Get all students who clocked in but never clocked out
        $incompleteAttendances = $this->studentAttendances()
            ->whereNotNull('clock_in_time')
            ->where('has_clocked_out', false)
            ->get();
        
        foreach ($incompleteAttendances as $attendance) {
            // Update status to Incomplete
            $attendance->update([
                'status' => StudentClassAttendance::STATUS_INCOMPLETE,
                'has_clocked_out' => true, // Mark as processed
                'clock_out_time' => now(),
                'duration_minutes' => $attendance->clock_in_time 
                    ? $attendance->clock_in_time->diffInMinutes(now()) 
                    : 0,
            ]);
            
            // Sync to main attendance table as Absent
            $this->syncIncompleteToStudentAttendance($attendance);
        }
        
        // Also mark students who never clocked in at all as Absent
        $absentAttendances = $this->studentAttendances()
            ->whereNull('clock_in_time')
            ->get();
        
        foreach ($absentAttendances as $attendance) {
            $attendance->update([
                'status' => StudentClassAttendance::STATUS_ABSENT,
            ]);
            
            // Sync to main attendance table as Absent
            $this->syncIncompleteToStudentAttendance($attendance);
        }
    }

    /**
     * Sync incomplete/absent attendance to main StudentAttendance table
     */
    protected function syncIncompleteToStudentAttendance($attendance)
    {
        // Mark as Absent (2) in main attendance table
        StudentAttendance::updateOrCreate(
            [
                'student_enroll_id' => $attendance->student_enroll_id,
                'subject_id' => $this->subject_id,
                'date' => $this->date,
            ],
            [
                'time' => $attendance->clock_in_time 
                    ? $attendance->clock_in_time->format('H:i:s') 
                    : $this->scheduled_start_time,
                'attendance' => 2, // Absent
                'note' => $attendance->clock_in_time 
                    ? 'Incomplete: Clocked in but never clocked out (Class Session)' 
                    : 'Absent: Never attended class (Class Session)',
                'created_by' => $this->teacher_id,
                'updated_by' => $this->teacher_id,
            ]
        );
    }

    /**
     * Cancel the class session
     */
    public function cancel()
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Determine lecturer attendance status based on class completion
     */
    public function determineLecturerAttendanceStatus($minimumPercentage = 70)
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            return self::ATTENDANCE_ABSENT;
        }

        $percentage = $this->duration_percentage;
        
        if ($percentage >= $minimumPercentage) {
            return self::ATTENDANCE_PRESENT;
        } elseif ($percentage >= ($minimumPercentage / 2)) {
            return self::ATTENDANCE_HALF_DAY;
        } else {
            return self::ATTENDANCE_ABSENT;
        }
    }

    /**
     * Sync lecturer attendance to staff_hourly_attendances table
     */
    public function syncLecturerAttendance()
    {
        if ($this->lecturer_attendance_synced) {
            return false;
        }

        $minimumPercentage = AttendanceSetting::getValue('minimum_class_duration_percentage', 70);
        $status = $this->determineLecturerAttendanceStatus($minimumPercentage);
        
        // Create or update staff hourly attendance
        $hourlyAttendance = StaffHourlyAttendance::updateOrCreate(
            [
                'user_id' => $this->teacher_id,
                'subject_id' => $this->subject_id,
                'date' => $this->date,
                'start_time' => $this->scheduled_start_time,
            ],
            [
                'session_id' => $this->session_id,
                'program_id' => $this->program_id,
                'semester_id' => $this->semester_id,
                'section_id' => $this->section_id,
                'end_time' => $this->scheduled_end_time,
                'attendance' => $status,
                'note' => $this->topic_covered ? 'Topic: ' . $this->topic_covered : null,
            ]
        );

        // Update sync status
        $this->update([
            'lecturer_attendance_status' => $status,
            'lecturer_attendance_synced' => true,
        ]);

        return $hourlyAttendance;
    }
}
