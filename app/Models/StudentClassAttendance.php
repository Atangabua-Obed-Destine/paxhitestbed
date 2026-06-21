<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StudentClassAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_enroll_id',
        'matricule',
        'clock_in_time',
        'clock_out_time',
        'duration_minutes',
        'status',
        'is_late',
        'late_minutes',
        'has_clocked_out',
        'is_first_clock_out',
        'clock_in_device',
        'clock_out_device',
        'clock_in_ip',
        'clock_out_ip',
        'last_scan_time',
        'marked_by_class_rep',
    ];

    protected $casts = [
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
        'last_scan_time' => 'datetime',
        'is_late' => 'boolean',
        'has_clocked_out' => 'boolean',
        'is_first_clock_out' => 'boolean',
        'marked_by_class_rep' => 'boolean',
    ];

    /**
     * Status constants
     */
    const STATUS_PRESENT = 'P';
    const STATUS_ABSENT = 'A';
    const STATUS_LATE = 'L';
    const STATUS_EARLY_LEAVE = 'E';
    const STATUS_INCOMPLETE = 'I';

    /**
     * Get the class session
     */
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * Get the student enrollment
     */
    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    /**
     * Get the student through enrollment
     */
    public function student()
    {
        return $this->hasOneThrough(
            Student::class,
            StudentEnroll::class,
            'id', // Foreign key on student_enrolls
            'id', // Foreign key on students
            'student_enroll_id', // Local key on student_class_attendances
            'student_id' // Local key on student_enrolls
        );
    }

    /**
     * Scope for clocked in students
     */
    public function scopeClockedIn($query)
    {
        return $query->whereNotNull('clock_in_time');
    }

    /**
     * Scope for clocked out students
     */
    public function scopeClockedOut($query)
    {
        return $query->where('has_clocked_out', true);
    }

    /**
     * Scope for late students
     */
    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    /**
     * Scope for present students (not absent)
     */
    public function scopePresent($query)
    {
        return $query->where('status', '!=', self::STATUS_ABSENT);
    }

    /**
     * Check if student can scan (cooldown check)
     */
    public function canScan($cooldownSeconds = 60)
    {
        if (!$this->last_scan_time) {
            return true;
        }

        return $this->last_scan_time->diffInSeconds(now()) >= $cooldownSeconds;
    }

    /**
     * Clock in the student
     */
    public function clockIn($device = null, $ip = null)
    {
        $now = now();
        $session = $this->classSession;
        
        // Calculate if late
        $isLate = false;
        $lateMinutes = 0;
        
        if ($session->actual_start_time) {
            $startTime = Carbon::parse($session->actual_start_time);
            $lateThresholdPercentage = AttendanceSetting::getValue('late_threshold_percentage', 15);
            $scheduledDuration = $session->scheduled_duration_minutes;
            $lateThresholdMinutes = ($scheduledDuration * $lateThresholdPercentage) / 100;
            
            $minutesSinceStart = $startTime->diffInMinutes($now);
            
            if ($minutesSinceStart > $lateThresholdMinutes) {
                $isLate = true;
                $lateMinutes = $minutesSinceStart;
            }
        }
        
        $this->update([
            'clock_in_time' => $now,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
            'status' => $isLate ? self::STATUS_LATE : self::STATUS_PRESENT,
            'clock_in_device' => $device,
            'clock_in_ip' => $ip,
            'last_scan_time' => $now,
        ]);
        
        // Sync to main student attendance table (mark as Present on clock-in)
        $this->syncToStudentAttendance();
        
        return $this;
    }

    /**
     * Clock out the student
     */
    public function clockOut($device = null, $ip = null, $approvalType = null)
    {
        $now = now();
        $session = $this->classSession;
        
        // Calculate duration
        $duration = 0;
        if ($this->clock_in_time) {
            $duration = $this->clock_in_time->diffInMinutes($now);
        }
        
        // Get all joint session IDs (same teacher, date, subject, start time)
        $jointSessionIds = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->where('status', ClassSession::STATUS_IN_PROGRESS)
            ->pluck('id')
            ->toArray();
        
        // Check if this is the first clock out across ALL joint sessions
        $isFirstClockOut = !StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)
            ->where('id', '!=', $this->id)
            ->where('has_clocked_out', true)
            ->exists();
        
        // Determine if this is an early leave with permission
        $status = $this->status;
        if ($approvalType === 'leave_permission') {
            $status = self::STATUS_EARLY_LEAVE;
        }
        
        $this->update([
            'clock_out_time' => $now,
            'duration_minutes' => $duration,
            'has_clocked_out' => true,
            'is_first_clock_out' => $isFirstClockOut,
            'clock_out_device' => $device,
            'clock_out_ip' => $ip,
            'last_scan_time' => $now,
            'status' => $status,
        ]);
        
        // If approval type is 'end_class', end the session immediately
        if ($approvalType === 'end_class') {
            $session->end($now);
        }
        // If this is the first clock out and setting is enabled AND no specific approval type
        // (meaning session ended naturally), set class end time
        elseif ($isFirstClockOut && $approvalType !== 'leave_permission' && AttendanceSetting::getValue('class_end_on_first_clock_out', true)) {
            $session->end($now);
        }
        
        // Sync to main student attendance table
        $this->syncToStudentAttendance();
        
        return $this;
    }

    /**
     * Sync this class attendance record to the main StudentAttendance table
     */
    public function syncToStudentAttendance()
    {
        $session = $this->classSession;
        if (!$session) {
            return null;
        }
        
        // Map StudentClassAttendance status to StudentAttendance values
        // StudentAttendance: 1 = Present, 2 = Absent, 3 = Leave
        $attendanceValue = 1; // Default to Present
        
        if ($this->status === self::STATUS_EARLY_LEAVE) {
            $attendanceValue = 3; // Leave
        } elseif ($this->status === self::STATUS_ABSENT || !$this->clock_in_time) {
            $attendanceValue = 2; // Absent
        } elseif ($this->status === self::STATUS_PRESENT || $this->status === self::STATUS_LATE) {
            $attendanceValue = 1; // Present (late is still present)
        }
        
        // Create or update the main attendance record
        return StudentAttendance::updateOrCreate(
            [
                'student_enroll_id' => $this->student_enroll_id,
                'subject_id' => $session->subject_id,
                'date' => $session->date,
            ],
            [
                'time' => $this->clock_in_time ? $this->clock_in_time->format('H:i:s') : now()->format('H:i:s'),
                'attendance' => $attendanceValue,
                'note' => $this->status === self::STATUS_EARLY_LEAVE 
                    ? 'Early leave with permission (Class Session)' 
                    : 'Auto-synced from Class Session Tracking',
                'created_by' => $session->teacher_id,
                'updated_by' => $session->teacher_id,
            ]
        );
    }

    /**
     * Toggle clock in/out
     */
    public function toggleScan($device = null, $ip = null, $lecturerApproved = false, $approvalType = null)
    {
        // If already clocked out, no more scanning allowed
        if ($this->has_clocked_out) {
            return ['success' => false, 'message' => 'Student has already clocked out and cannot scan again.', 'action' => 'blocked'];
        }

        // Check cooldown
        $cooldownSeconds = AttendanceSetting::getValue('scan_cooldown_seconds', 60);
        if (!$this->canScan($cooldownSeconds)) {
            $remainingSeconds = $cooldownSeconds - $this->last_scan_time->diffInSeconds(now());
            return ['success' => false, 'message' => "Please wait {$remainingSeconds} seconds before scanning again.", 'action' => 'cooldown'];
        }

        // If not clocked in yet, clock in
        if (!$this->clock_in_time) {
            $this->clockIn($device, $ip);
            return ['success' => true, 'message' => 'Clocked in successfully', 'action' => 'clock_in', 'time' => $this->clock_in_time->format('H:i:s')];
        }

        // Check if scheduled duration is met before allowing clock-out
        $session = $this->classSession;
        if ($session && $session->status === ClassSession::STATUS_IN_PROGRESS) {
            $isScheduledDurationMet = $this->isScheduledDurationMet();
            
            // If duration not met and not approved by lecturer, require confirmation
            if (!$isScheduledDurationMet && !$lecturerApproved) {
                $scheduledEnd = $session->scheduled_end_time;
                $remainingMinutes = $this->getRemainingScheduledMinutes();
                
                return [
                    'success' => false, 
                    'action' => 'early_clock_out_pending',
                    'message' => 'Session has not reached scheduled end time. Lecturer approval required.',
                    'remaining_minutes' => $remainingMinutes,
                    'scheduled_end_time' => $scheduledEnd instanceof \Carbon\Carbon ? $scheduledEnd->format('H:i') : $scheduledEnd,
                    'student_id' => $this->student_enroll_id,
                    'attendance_id' => $this->id,
                    'matricule' => $this->matricule,
                ];
            }
        }

        // Otherwise, clock out (approved or duration met)
        $this->clockOut($device, $ip, $approvalType);
        return ['success' => true, 'message' => 'Clocked out successfully', 'action' => 'clock_out', 'time' => $this->clock_out_time->format('H:i:s')];
    }

    /**
     * Check if the scheduled session duration has been met
     */
    public function isScheduledDurationMet()
    {
        $session = $this->classSession;
        if (!$session || !$session->actual_start_time) {
            return false;
        }

        $now = now();
        $sessionDate = \Carbon\Carbon::parse($session->date)->format('Y-m-d');
        
        // Parse scheduled end time
        $scheduledEndTime = $session->scheduled_end_time;
        if ($scheduledEndTime instanceof \Carbon\Carbon) {
            $scheduledEnd = $scheduledEndTime;
        } else {
            $scheduledEnd = \Carbon\Carbon::parse($sessionDate . ' ' . $scheduledEndTime);
        }
        
        // Session is complete if current time >= scheduled end time
        return $now->gte($scheduledEnd);
    }

    /**
     * Get remaining minutes until scheduled end time
     */
    public function getRemainingScheduledMinutes()
    {
        $session = $this->classSession;
        if (!$session) {
            return 0;
        }

        $now = now();
        $sessionDate = \Carbon\Carbon::parse($session->date)->format('Y-m-d');
        
        // Parse scheduled end time
        $scheduledEndTime = $session->scheduled_end_time;
        if ($scheduledEndTime instanceof \Carbon\Carbon) {
            $scheduledEnd = $scheduledEndTime;
        } else {
            $scheduledEnd = \Carbon\Carbon::parse($sessionDate . ' ' . $scheduledEndTime);
        }
        
        if ($now->gte($scheduledEnd)) {
            return 0;
        }
        
        return $now->diffInMinutes($scheduledEnd);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_PRESENT => 'Present',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_LATE => 'Late',
            self::STATUS_EARLY_LEAVE => 'Early Leave',
            self::STATUS_INCOMPLETE => 'Incomplete',
        ];

        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        $classes = [
            self::STATUS_PRESENT => 'badge-success',
            self::STATUS_ABSENT => 'badge-danger',
            self::STATUS_LATE => 'badge-warning',
            self::STATUS_EARLY_LEAVE => 'badge-info',
            self::STATUS_INCOMPLETE => 'badge-secondary',
        ];

        return $classes[$this->status] ?? 'badge-secondary';
    }
}
