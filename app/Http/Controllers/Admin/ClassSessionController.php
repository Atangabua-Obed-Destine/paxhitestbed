<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\ClassSession;
use App\Models\ClassLogbook;
use App\Models\StudentClassAttendance;
use App\Models\AttendanceSetting;
use App\Models\ClassRoutine;
use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Session;
use App\Models\Section;
use App\Models\Subject;
use App\User;
use App\Models\AcademicDepartment;
use Carbon\Carbon;

class ClassSessionController extends Controller
{
    protected $title, $route, $view, $path, $access;
    
    public function __construct()
    {
        $this->title = 'Class Session Tracking';
        $this->route = 'admin.class-session';
        $this->view = 'admin.class-session';
        $this->path = 'class-session';
        $this->access = 'class-session';

        $this->middleware('permission:'.$this->access.'-view', ['only' => ['index', 'show', 'getDetails', 'getStudents', 'getStats']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['kiosk', 'startSession', 'endSession', 'createExtraClass', 'scan', 'scanClockOut']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['updateLogbook', 'updateClassRep']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['cancelSession']]);
    }

    /**
     * Display a listing of class sessions (Admin Dashboard).
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        // Filters
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        $data['selected_session'] = $session = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_section'] = $section = $request->section ?? '0';
        $data['selected_teacher'] = $teacher = $request->teacher ?? '0';
        $data['selected_date_from'] = $dateFrom = $request->date_from ?? date('Y-m-01');
        $data['selected_date_to'] = $dateTo = $request->date_to ?? date('Y-m-d');
        $data['selected_status'] = $status = $request->status ?? '';

        // Filter options
        $data['faculties'] = Faculty::where('status', '1')->orderBy('title', 'asc')->get();
        $data['programs'] = Program::where('status', '1')->orderBy('title', 'asc')->get();
        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();
        $data['teachers'] = User::where('status', '1')
            ->where('salary_type', 2) // Hourly staff
            ->orderBy('first_name', 'asc')
            ->get();

        // Build query
        $query = ClassSession::with(['teacher', 'subject', 'program', 'semester', 'section', 'logbook']);

        if ($program !== '0') {
            $query->where('program_id', $program);
        }
        if ($session !== '0') {
            $query->where('session_id', $session);
        }
        if ($semester !== '0') {
            $query->where('semester_id', $semester);
        }
        if ($section !== '0') {
            $query->where('section_id', $section);
        }
        if ($teacher !== '0') {
            $query->where('teacher_id', $teacher);
        }
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $data['rows'] = $query->orderBy('date', 'desc')->orderBy('scheduled_start_time', 'desc')->paginate(20);

        return view($this->view.'.index', $data);
    }

    /**
     * Display details of a specific class session.
     */
    public function show($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['session'] = ClassSession::with([
            'teacher', 
            'subject', 
            'program', 
            'semester', 
            'section', 
            'logbook',
            'classRep.student',
            'hod',
            'studentAttendances.studentEnroll.student',
            'messages.student',
            'messages.user',
            'questions.student',
            'alerts.student'
        ])->findOrFail($id);

        // Get student notes count for this session
        $data['notesCount'] = \App\Models\StudentClassNote::where('class_session_id', $id)
            ->whereNotNull('content')
            ->count();

        return view($this->view.'.show', $data);
    }

    /**
     * Display the kiosk interface for lecturer.
     */
    public function kiosk(Request $request)
    {
        $data['title'] = 'Class Session Kiosk';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $teacherId = Auth::id();
        $data['today'] = $today = date('Y-m-d');
        $data['current_time'] = date('H:i:s');

        // Get teacher info
        $data['teacher'] = User::find($teacherId);

        // Get today's scheduled classes from class_routines
        // Day mapping: 1=Saturday, 2=Sunday, 3=Monday, 4=Tuesday, 5=Wednesday, 6=Thursday, 7=Friday
        $dayMapping = [
            'saturday' => 1,
            'sunday' => 2,
            'monday' => 3,
            'tuesday' => 4,
            'wednesday' => 5,
            'thursday' => 6,
            'friday' => 7,
        ];
        $dayName = strtolower(date('l', strtotime($today)));
        $dayOfWeek = $dayMapping[$dayName] ?? null;
        
        $scheduledRoutines = collect();
        if ($dayOfWeek) {
            $scheduledRoutines = ClassRoutine::where('teacher_id', $teacherId)
                ->where('day', $dayOfWeek)
                ->where('status', 1)
                ->with(['subject', 'program', 'semester', 'section', 'session'])
                ->orderBy('start_time', 'asc')
                ->get();
        }

        // Get or create class sessions for today's routines
        $data['scheduled_sessions'] = [];
        $data['joint_sessions'] = []; // Group joint classes by time+subject
        
        foreach ($scheduledRoutines as $routine) {
            $session = ClassSession::firstOrCreate(
                [
                    'class_routine_id' => $routine->id,
                    'date' => $today,
                ],
                [
                    'teacher_id' => $teacherId,
                    'subject_id' => $routine->subject_id,
                    'program_id' => $routine->program_id,
                    'session_id' => $routine->session_id,
                    'semester_id' => $routine->semester_id,
                    'section_id' => $routine->section_id,
                    'scheduled_start_time' => $routine->start_time,
                    'scheduled_end_time' => $routine->end_time,
                    'is_scheduled' => true,
                    'is_extra_class' => false,
                    'status' => ClassSession::STATUS_PENDING,
                ]
            );
            
            $session->load(['subject', 'program', 'semester', 'section', 'studentAttendances']);
            $data['scheduled_sessions'][] = $session;
            
            // Group by time slot + subject for joint class detection
            $key = $routine->start_time . '_' . $routine->end_time . '_' . $routine->subject_id;
            if (!isset($data['joint_sessions'][$key])) {
                $data['joint_sessions'][$key] = [
                    'subject' => $routine->subject,
                    'start_time' => $routine->start_time,
                    'end_time' => $routine->end_time,
                    'sessions' => [],
                    'programs' => [],
                ];
            }
            $data['joint_sessions'][$key]['sessions'][] = $session;
            $data['joint_sessions'][$key]['programs'][] = $routine->program;
        }
        
        // Mark sessions that are part of a joint class (more than one program)
        foreach ($data['joint_sessions'] as $key => $jointGroup) {
            $isJoint = count($jointGroup['sessions']) > 1;
            foreach ($jointGroup['sessions'] as $session) {
                $session->is_joint_class = $isJoint;
                $session->joint_programs = $isJoint ? $jointGroup['programs'] : [];
                $session->joint_key = $key;
            }
        }

        // Get any extra classes added for today
        $data['extra_sessions'] = ClassSession::where('teacher_id', $teacherId)
            ->where('date', $today)
            ->where('is_extra_class', true)
            ->with(['subject', 'program', 'semester', 'section', 'studentAttendances'])
            ->orderBy('scheduled_start_time', 'asc')
            ->get();

        // Group extra sessions for joint class detection (same subject + start time)
        $data['extra_joint_sessions'] = [];
        foreach ($data['extra_sessions'] as $extraSession) {
            $key = $extraSession->subject_id . '_' . $extraSession->scheduled_start_time;
            if (!isset($data['extra_joint_sessions'][$key])) {
                $data['extra_joint_sessions'][$key] = [
                    'subject' => $extraSession->subject,
                    'start_time' => $extraSession->scheduled_start_time,
                    'end_time' => $extraSession->scheduled_end_time,
                    'sessions' => [],
                    'programs' => [],
                ];
            }
            $data['extra_joint_sessions'][$key]['sessions'][] = $extraSession;
            $data['extra_joint_sessions'][$key]['programs'][] = $extraSession->program;
        }

        // Mark extra sessions that are part of a joint class (more than one program)
        foreach ($data['extra_joint_sessions'] as $key => $jointGroup) {
            $isJoint = count($jointGroup['sessions']) > 1;
            foreach ($jointGroup['sessions'] as $extraSession) {
                $extraSession->is_joint_class = $isJoint;
                $extraSession->joint_programs = $isJoint ? $jointGroup['programs'] : [];
                $extraSession->joint_key = $key;
            }
        }

        // Get current active session if any
        $data['active_session'] = ClassSession::where('teacher_id', $teacherId)
            ->where('date', $today)
            ->where('status', ClassSession::STATUS_IN_PROGRESS)
            ->with(['subject', 'program', 'semester', 'section', 'studentAttendances.studentEnroll.student'])
            ->first();

        // For joint classes, get ALL active sessions and ALL students across programs
        $data['joint_active_sessions'] = [];
        $data['all_joint_students'] = collect();
        $data['joint_session_ids'] = [];
        
        if ($data['active_session']) {
            // Find the joint key for this active session
            $activeJointKey = null;
            foreach ($data['joint_sessions'] as $key => $jointGroup) {
                foreach ($jointGroup['sessions'] as $jSession) {
                    if ($jSession->id === $data['active_session']->id) {
                        $activeJointKey = $key;
                        break 2;
                    }
                }
            }
            
            if ($activeJointKey && count($data['joint_sessions'][$activeJointKey]['sessions']) > 1) {
                // This is a joint class - get all active sessions in this joint group
                $data['joint_active_sessions'] = ClassSession::where('teacher_id', $teacherId)
                    ->where('date', $today)
                    ->where('status', ClassSession::STATUS_IN_PROGRESS)
                    ->where('subject_id', $data['active_session']->subject_id)
                    ->where('scheduled_start_time', $data['active_session']->scheduled_start_time)
                    ->with(['subject', 'program', 'semester', 'section', 'studentAttendances.studentEnroll.student'])
                    ->get();
                
                $data['joint_session_ids'] = $data['joint_active_sessions']->pluck('id')->toArray();
                
                // Collect all students from all joint sessions
                foreach ($data['joint_active_sessions'] as $jSession) {
                    foreach ($jSession->studentAttendances as $attendance) {
                        $attendance->session_program = $jSession->program;
                        $data['all_joint_students']->push($attendance);
                    }
                }
            }
        }

        // Settings
        $data['settings'] = AttendanceSetting::getAllAsArray();
        $data['allow_extra_classes'] = AttendanceSetting::getValue('allow_extra_classes', true);

        // Available subjects and programs for extra class
        $data['subjects'] = Subject::where('status', 1)
            ->with(['classes', 'programs'])
            ->whereHas('classes', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->orderBy('code', 'asc')
            ->get();

        // Get all programs, sessions, semesters the teacher has classes for
        $teacherClasses = ClassRoutine::where('teacher_id', $teacherId)
            ->where('status', 1)
            ->with(['program', 'session', 'semester', 'section'])
            ->get();
        
        $data['programs'] = $teacherClasses->pluck('program')->filter()->unique('id')->values();
        $data['academic_sessions'] = $teacherClasses->pluck('session')->filter()->unique('id')->values();
        $data['semesters'] = $teacherClasses->pluck('semester')->filter()->unique('id')->values();
        $data['sections'] = $teacherClasses->pluck('section')->filter()->unique('id')->values();

        return view($this->view.'.kiosk', $data);
    }

    /**
     * Start a class session.
     */
    public function startSession(Request $request)
    {
        try {
            $request->validate([
                'session_id' => 'required|exists:class_sessions,id',
            ]);

            $session = ClassSession::findOrFail($request->session_id);

            // Verify teacher owns this session
            if ($session->teacher_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized to start this session.'
                ], 403);
            }

            // Check if already started
            if ($session->status !== ClassSession::STATUS_PENDING) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session already started or completed.'
            ], 400);
        }

        // Start the session
        $session->start();

        // Create logbook entry
        ClassLogbook::create([
            'class_session_id' => $session->id,
            'created_by' => Auth::id(),
        ]);

        // Get enrolled students for this class
        $enrolledStudents = StudentEnroll::where('status', '1')
            ->where('program_id', $session->program_id)
            ->where('session_id', $session->session_id)
            ->where('semester_id', $session->semester_id)
            ->when($session->section_id, function($query) use ($session) {
                $query->where('section_id', $session->section_id);
            })
            ->with('subjects')
            ->whereHas('subjects', function($query) use ($session) {
                $query->where('subject_id', $session->subject_id);
            })
            ->with('student')
            ->whereHas('student', function($query) {
                $query->where('status', '1');
            })
            ->get();

        // Group by unique matricule
        $uniqueStudents = $enrolledStudents->groupBy('matricule')->map(function($group) {
            return $group->sortByDesc('id')->first();
        });

        // Pre-create attendance records for all enrolled students
        foreach ($uniqueStudents as $enroll) {
            StudentClassAttendance::firstOrCreate(
                [
                    'class_session_id' => $session->id,
                    'student_enroll_id' => $enroll->id,
                ],
                [
                    'matricule' => $enroll->matricule,
                    'status' => StudentClassAttendance::STATUS_ABSENT,
                ]
            );
        }

        // Set HOD based on program's academic department
        $hodUserId = null;
        if ($session->program) {
            $department = AcademicDepartment::where('status', 1)
                ->whereHas('programs', function($query) use ($session) {
                    $query->where('id', $session->program_id);
                })
                ->first();
            
            if ($department && $department->head_of_department_id) {
                $hodUserId = $department->head_of_department_id;
                $session->update(['hod_user_id' => $hodUserId]);
            }
        }

        $session->load(['subject', 'program', 'studentAttendances.studentEnroll.student']);

            return response()->json([
                'status' => 'success',
                'message' => 'Class session started successfully!',
                'session' => [
                    'id' => $session->id,
                    'subject' => $session->subject->title ?? 'N/A',
                    'start_time' => $session->actual_start_time->format('H:i:s'),
                    'total_students' => $uniqueStudents->count(),
                    'present_count' => 0,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Start session error: ' . $e->getMessage(), [
                'session_id' => $request->session_id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to start session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * End a class session (and all joint sessions if applicable).
     */
    public function endSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
        ]);

        $session = ClassSession::findOrFail($request->session_id);

        // Verify teacher owns this session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to end this session.'
            ], 403);
        }

        // Check if in progress
        if ($session->status !== ClassSession::STATUS_IN_PROGRESS) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is not in progress.'
            ], 400);
        }

        // Find all joint sessions (same teacher, date, subject, start time) that are in progress
        $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->where('status', ClassSession::STATUS_IN_PROGRESS)
            ->get();

        $endedCount = 0;
        $totalPresent = 0;
        $totalClockedOut = 0;

        foreach ($jointSessions as $jointSession) {
            // End the session
            $jointSession->end();
            $endedCount++;

            // Auto clock out remaining students if enabled
            if (AttendanceSetting::getValue('auto_clock_out_enabled', true)) {
                $jointSession->studentAttendances()
                    ->whereNotNull('clock_in_time')
                    ->where('has_clocked_out', false)
                    ->update([
                        'clock_out_time' => now(),
                        'has_clocked_out' => true,
                    ]);
            }

            // Mark students who never clocked in as absent
            $jointSession->studentAttendances()
                ->whereNull('clock_in_time')
                ->update(['status' => StudentClassAttendance::STATUS_ABSENT]);

            // Sync lecturer attendance if enabled and logbook is complete (or not required)
            $requireLogbook = AttendanceSetting::getValue('require_logbook_completion', true);
            $autoSync = AttendanceSetting::getValue('lecturer_attendance_auto_sync', true);
            
            if ($autoSync) {
                $logbook = $jointSession->logbook;
                if (!$requireLogbook || ($logbook && $logbook->is_completed)) {
                    $jointSession->syncLecturerAttendance();
                }
            }

            $totalPresent += $jointSession->present_count;
            $totalClockedOut += $jointSession->clocked_out_count;
        }

        // Refresh the original session for response data
        $session->refresh();

        $message = $endedCount > 1 
            ? "Joint class ended successfully! ({$endedCount} sessions)" 
            : 'Class session ended successfully!';

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'session' => [
                'id' => $session->id,
                'end_time' => $session->actual_end_time->format('H:i:s'),
                'duration_minutes' => $session->actual_duration_minutes,
                'duration_percentage' => $session->duration_percentage,
                'present_count' => $endedCount > 1 ? $totalPresent : $session->present_count,
                'clocked_out_count' => $endedCount > 1 ? $totalClockedOut : $session->clocked_out_count,
                'ended_sessions_count' => $endedCount,
            ]
        ]);
    }

    /**
     * Cancel a class session.
     */
    public function cancelSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $session = ClassSession::findOrFail($request->session_id);

        // Verify teacher owns this session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to cancel this session.'
            ], 403);
        }

        // Cannot cancel completed sessions
        if ($session->status === ClassSession::STATUS_COMPLETED) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot cancel a completed session.'
            ], 400);
        }

        $session->update([
            'status' => ClassSession::STATUS_CANCELLED,
            'remarks' => $request->reason ?? 'Cancelled by lecturer',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Class session cancelled.'
        ]);
    }

    /**
     * Create an extra/unscheduled class (supports multiple programs for joint classes).
     */
    public function createExtraClass(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'program_id' => 'required_without:program_ids|exists:programs,id',
            'program_ids' => 'required_without:program_id|array|min:1',
            'program_ids.*' => 'exists:programs,id',
            'session_id' => 'required|exists:sessions,id',
            'semester_id' => 'required|exists:semesters,id',
            'section_id' => 'nullable|exists:sections,id',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $today = date('Y-m-d');
        $teacherId = Auth::id();

        // Check if extra classes are allowed
        if (!AttendanceSetting::getValue('allow_extra_classes', true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Extra classes are not allowed.'
            ], 403);
        }

        // Determine program IDs - support both single and multiple
        $programIds = $request->program_ids ?? [$request->program_id];
        $createdSessions = [];

        // Create a session for each program (joint class support)
        foreach ($programIds as $programId) {
            $session = ClassSession::create([
                'teacher_id' => $teacherId,
                'subject_id' => $request->subject_id,
                'program_id' => $programId,
                'session_id' => $request->session_id,
                'semester_id' => $request->semester_id,
                'section_id' => $request->section_id,
                'date' => $today,
                'scheduled_start_time' => $request->start_time,
                'scheduled_end_time' => $request->end_time,
                'is_scheduled' => false,
                'is_extra_class' => true,
                'status' => ClassSession::STATUS_PENDING,
            ]);

            $session->load(['subject', 'program', 'semester', 'section']);
            $createdSessions[] = $session;
        }

        $sessionCount = count($createdSessions);
        $message = $sessionCount > 1 
            ? "Joint class created for {$sessionCount} programs!" 
            : 'Extra class created successfully!';

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'sessions_created' => $sessionCount,
            'session' => $createdSessions[0], // Return first session for compatibility
            'sessions' => $createdSessions
        ]);
    }

    /**
     * Process student scan (clock in/out).
     */
    public function scan(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'student_id' => 'required|string',
        ]);

        $session = ClassSession::findOrFail($request->session_id);

        // Check if session is in progress
        if ($session->status !== ClassSession::STATUS_IN_PROGRESS) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class session is not active.'
            ], 400);
        }

        // Extract student ID from scanned data
        // ID cards contain verification URLs like: http://localhost/paxhitest/student/verify/PAX25ABC001
        // We need to extract just the matricule from it
        $scannedData = trim($request->student_id);
        $studentIdentifier = $scannedData;
        
        // Check if scanned data is a URL containing student/verify/
        if (preg_match('/student\/verify\/([^\/\?\s]+)/i', $scannedData, $matches)) {
            $studentIdentifier = $matches[1];
        }
        // Also handle URL-encoded data
        elseif (preg_match('/student%2Fverify%2F([^%\/\?\s]+)/i', $scannedData, $matches)) {
            $studentIdentifier = urldecode($matches[1]);
        }
        // Check if it's just a full URL with the matricule at the end
        elseif (filter_var($scannedData, FILTER_VALIDATE_URL)) {
            $urlPath = parse_url($scannedData, PHP_URL_PATH);
            if ($urlPath) {
                $segments = explode('/', trim($urlPath, '/'));
                $studentIdentifier = end($segments);
            }
        }

        // For joint classes, find all sessions with same subject/time that are in progress
        $jointSessionIds = [$session->id];
        $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->where('status', ClassSession::STATUS_IN_PROGRESS)
            ->pluck('id')
            ->toArray();
        
        if (count($jointSessions) > 1) {
            $jointSessionIds = $jointSessions;
        }

        // Find student attendance record - look across all joint sessions
        $attendance = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)
            ->where(function($query) use ($studentIdentifier) {
                $query->where('matricule', $studentIdentifier)
                    ->orWhereHas('studentEnroll.student', function($q) use ($studentIdentifier) {
                        $q->where('student_id', $studentIdentifier);
                    });
            })
            ->with(['studentEnroll.student', 'classSession.program'])
            ->first();

        if (!$attendance) {
            $programInfo = count($jointSessionIds) > 1 ? ' (checked ' . count($jointSessionIds) . ' programs)' : '';
            return response()->json([
                'status' => 'error',
                'message' => 'Student not found in this class. Scanned: ' . $studentIdentifier . $programInfo
            ], 404);
        }
        
        // Use the session from the found attendance (important for joint classes)
        $attendanceSession = $attendance->classSession ?? $session;

        // Get device info
        $device = $request->header('User-Agent') ?? 'Unknown';
        $ip = $request->ip();

        // Toggle scan
        $result = $attendance->toggleScan($device, $ip);

        // Handle early clock-out pending (needs lecturer approval)
        if (!$result['success'] && $result['action'] === 'early_clock_out_pending') {
            // Get student info for the confirmation dialog
            $studentName = 'Unknown';
            if ($attendance->studentEnroll && $attendance->studentEnroll->student) {
                $student = $attendance->studentEnroll->student;
                $studentName = $student->first_name . ' ' . $student->last_name;
            }
            
            return response()->json([
                'status' => 'pending_approval',
                'message' => $result['message'],
                'action' => $result['action'],
                'student' => $studentName,
                'matricule' => $result['matricule'],
                'attendance_id' => $result['attendance_id'],
                'remaining_minutes' => $result['remaining_minutes'],
                'scheduled_end_time' => $result['scheduled_end_time'],
                'session_id' => $session->id,
            ]);
        }

        if (!$result['success']) {
            return response()->json([
                'status' => 'warning',
                'message' => $result['message'],
                'action' => $result['action'],
            ]);
        }

        // Get student info
        $studentName = 'Unknown';
        $programName = '';
        if ($attendance->studentEnroll && $attendance->studentEnroll->student) {
            $student = $attendance->studentEnroll->student;
            $studentName = $student->first_name . ' ' . $student->last_name;
        }
        if ($attendanceSession && $attendanceSession->program) {
            $programName = $attendanceSession->program->short_form ?? $attendanceSession->program->title ?? '';
        }

        // Get updated stats - count across all joint sessions
        $presentCount = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)
            ->whereNotNull('clock_in_time')->count();
        $clockedOutCount = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)
            ->where('has_clocked_out', true)->count();
        $totalCount = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)->count();

        // Check if this was the first clock-in (to initialize the session timer)
        $isFirstClockIn = $result['action'] === 'clock_in' && $presentCount === 1;
        $firstClockInTime = null;
        $calculatedEndTime = null;
        $scheduledDurationMinutes = null;
        
        if ($isFirstClockIn) {
            // Refresh the session to get the updated first_clock_in_time
            $session->refresh();
            $firstClockInTime = $session->first_clock_in_time ? $session->first_clock_in_time->toISOString() : null;
            $calculatedEndTime = $session->calculated_end_time ? $session->calculated_end_time->toISOString() : null;
            $scheduledDurationMinutes = $session->scheduled_duration_minutes;
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'action' => $result['action'],
            'time' => $result['time'],
            'student' => $studentName,
            'matricule' => $attendance->matricule,
            'program' => $programName,
            'is_late' => $attendance->is_late,
            'is_joint_class' => count($jointSessionIds) > 1,
            'is_first_clock_in' => $isFirstClockIn,
            'first_clock_in_time' => $firstClockInTime,
            'calculated_end_time' => $calculatedEndTime,
            'scheduled_duration_minutes' => $scheduledDurationMinutes,
            'stats' => [
                'present' => $presentCount,
                'clocked_out' => $clockedOutCount,
                'total' => $totalCount,
            ]
        ]);
    }

    /**
     * Approve early clock-out with lecturer decision.
     */
    public function approveEarlyClockOut(Request $request)
    {
        $request->validate([
            'attendance_id' => 'required|exists:student_class_attendances,id',
            'approval_type' => 'required|in:leave_permission,end_class',
        ]);

        $attendance = StudentClassAttendance::with(['classSession', 'studentEnroll.student'])->findOrFail($request->attendance_id);
        $session = $attendance->classSession;
        
        // Ensure session is still in progress
        if ($session->status !== ClassSession::STATUS_IN_PROGRESS) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is no longer in progress.'
            ], 400);
        }

        // Get device info
        $device = $request->header('User-Agent') ?? 'Unknown';
        $ip = $request->ip();
        
        // Perform the approved clock-out
        $result = $attendance->toggleScan($device, $ip, true, $request->approval_type);

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ], 400);
        }

        // Get student info
        $studentName = 'Unknown';
        if ($attendance->studentEnroll && $attendance->studentEnroll->student) {
            $student = $attendance->studentEnroll->student;
            $studentName = $student->first_name . ' ' . $student->last_name;
        }

        // Refresh session to get updated status
        $session->refresh();

        // Get updated stats
        $presentCount = $session->studentAttendances()->whereNotNull('clock_in_time')->count();
        $clockedOutCount = $session->studentAttendances()->where('has_clocked_out', true)->count();

        $message = $request->approval_type === 'end_class' 
            ? "Class ended. {$studentName} clocked out."
            : "{$studentName} left with permission. Class continues.";

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'action' => $result['action'],
            'time' => $result['time'],
            'student' => $studentName,
            'matricule' => $attendance->matricule,
            'approval_type' => $request->approval_type,
            'session_ended' => $session->status === ClassSession::STATUS_COMPLETED,
            'stats' => [
                'present' => $presentCount,
                'clocked_out' => $clockedOutCount,
            ]
        ]);
    }

    /**
     * Update logbook entry (and all joint sessions if applicable).
     */
    public function updateLogbook(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'topic_covered' => 'nullable|string|max:500',
            'content_summary' => 'nullable|string',
            'learning_objectives' => 'nullable|string',
            'teaching_methods' => 'nullable|string',
            'materials_used' => 'nullable|string',
            'assignments_given' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $session = ClassSession::findOrFail($request->session_id);

        // Verify teacher owns this session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update this logbook.'
            ], 403);
        }

        // Find all joint sessions (same teacher, date, subject, start time)
        $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->get();

        $updatedCount = 0;
        $logbook = null;

        foreach ($jointSessions as $jointSession) {
            $logbook = ClassLogbook::updateOrCreate(
                ['class_session_id' => $jointSession->id],
                [
                    'topic_covered' => $request->topic_covered,
                    'content_summary' => $request->content_summary,
                    'learning_objectives' => $request->learning_objectives,
                    'teaching_methods' => $request->teaching_methods,
                    'materials_used' => $request->materials_used,
                    'assignments_given' => $request->assignments_given,
                    'remarks' => $request->remarks,
                    'updated_by' => Auth::id(),
                ]
            );

            // Also update all logbook fields in session for quick reference
            $jointSession->update([
                'topic_covered' => $request->topic_covered,
                'learning_objectives' => $request->learning_objectives,
                'teaching_methods' => $request->teaching_methods,
                'materials_used' => $request->materials_used,
                'assignments_given' => $request->assignments_given,
                'remarks' => $request->remarks,
            ]);

            // Check if should mark as complete
            if ($request->mark_complete && $logbook->hasMinimumContent()) {
                $logbook->markAsCompleted();
                
                // Sync lecturer attendance if session is completed
                if ($jointSession->status === ClassSession::STATUS_COMPLETED) {
                    $jointSession->syncLecturerAttendance();
                }
            }

            $updatedCount++;
        }

        $message = $updatedCount > 1 
            ? "Logbook updated for all {$updatedCount} joint sessions!" 
            : 'Logbook updated successfully!';

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'is_completed' => $logbook ? $logbook->is_completed : false,
            'updated_sessions_count' => $updatedCount,
        ]);
    }

    /**
     * Update class representative (and sync to all joint sessions).
     */
    public function updateClassRep(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'class_rep_enroll_id' => 'nullable|exists:student_enrolls,id',
        ]);

        $session = ClassSession::findOrFail($request->session_id);

        // Verify teacher owns this session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update class representative.'
            ], 403);
        }

        // Find all joint sessions (same teacher, date, subject, start time)
        $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->get();
        
        // Update all joint sessions
        foreach ($jointSessions as $jointSession) {
            $jointSession->update(['class_rep_enroll_id' => $request->class_rep_enroll_id]);
            
            // If class rep is removed, also remove delegation
            if (!$request->class_rep_enroll_id) {
                $jointSession->update([
                    'logbook_delegated' => false,
                    'attendance_delegated' => false,
                ]);
            }

            // Also update in logbook if exists
            if ($jointSession->logbook) {
                $jointSession->logbook->update([
                    'class_rep_student_id' => $request->class_rep_enroll_id,
                    'updated_by' => Auth::id(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Class representative updated!' . (count($jointSessions) > 1 ? ' (Applied to ' . count($jointSessions) . ' programs)' : '')
        ]);
    }

    /**
     * Toggle delegation (logbook or attendance) to class rep (and sync to all joint sessions).
     */
    public function toggleDelegation(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'delegated' => 'required|boolean',
            'type' => 'sometimes|string|in:logbook,attendance',
        ]);

        $session = ClassSession::findOrFail($request->session_id);
        $type = $request->type ?? 'logbook';

        // Verify teacher owns this session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update delegation.'
            ], 403);
        }

        // Must have a class rep to delegate
        if ($request->delegated && !$session->class_rep_enroll_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select a class representative first.'
            ], 400);
        }

        // Find all joint sessions (same teacher, date, subject, start time)
        $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->get();

        $field = $type === 'attendance' ? 'attendance_delegated' : 'logbook_delegated';
        
        // Update all joint sessions
        foreach ($jointSessions as $jointSession) {
            $jointSession->update([
                $field => $request->delegated,
                // Also copy the class_rep_enroll_id if enabling delegation
                'class_rep_enroll_id' => $request->delegated ? $session->class_rep_enroll_id : $jointSession->class_rep_enroll_id,
            ]);
        }

        $typeLabel = ucfirst($type);
        $programCount = count($jointSessions);
        $message = $request->delegated ? "{$typeLabel} delegated to class rep!" : 'Delegation removed.';
        if ($programCount > 1) {
            $message .= " (Applied to {$programCount} programs)";
        }
        
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'delegated' => $request->delegated,
            'type' => $type
        ]);
    }

    /**
     * Get students list for a session (AJAX).
     */
    public function getStudents($sessionId)
    {
        $session = ClassSession::with(['studentAttendances.studentEnroll.student', 'program'])
            ->findOrFail($sessionId);

        // For joint classes, get all sessions with same subject/time that are in progress
        $jointSessionIds = [$session->id];
        if ($session->status === ClassSession::STATUS_IN_PROGRESS) {
            $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
                ->where('date', $session->date)
                ->where('subject_id', $session->subject_id)
                ->where('scheduled_start_time', $session->scheduled_start_time)
                ->where('status', ClassSession::STATUS_IN_PROGRESS)
                ->with(['studentAttendances.studentEnroll.student', 'program'])
                ->get();
            
            if ($jointSessions->count() > 1) {
                $jointSessionIds = $jointSessions->pluck('id')->toArray();
                
                // Collect students from all joint sessions
                $allStudents = collect();
                foreach ($jointSessions as $jSession) {
                    foreach ($jSession->studentAttendances as $attendance) {
                        $allStudents->push([
                            'id' => $attendance->id,
                            'enroll_id' => $attendance->student_enroll_id,
                            'matricule' => $attendance->matricule,
                            'name' => $attendance->studentEnroll && $attendance->studentEnroll->student 
                                ? $attendance->studentEnroll->student->first_name . ' ' . $attendance->studentEnroll->student->last_name 
                                : 'Unknown',
                            'program' => $jSession->program->short_form ?? $jSession->program->title ?? '',
                            'program_id' => $jSession->program_id,
                            'clock_in_time' => $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i:s') : null,
                            'clock_out_time' => $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i:s') : null,
                            'status' => $attendance->status,
                            'status_label' => $attendance->status_label,
                            'is_late' => $attendance->is_late,
                            'has_clocked_out' => $attendance->has_clocked_out,
                        ]);
                    }
                }
                
                return response()->json([
                    'status' => 'success',
                    'students' => $allStudents,
                    'is_joint_class' => true,
                    'program_count' => $jointSessions->count(),
                    'class_rep_enroll_id' => $session->class_rep_enroll_id,
                    'logbook_delegated' => $session->logbook_delegated,
                    'attendance_delegated' => $session->attendance_delegated,
                ]);
            }
        }

        // Single session - original logic
        $students = $session->studentAttendances->map(function($attendance) use ($session) {
            return [
                'id' => $attendance->id,
                'enroll_id' => $attendance->student_enroll_id,
                'matricule' => $attendance->matricule,
                'name' => $attendance->studentEnroll && $attendance->studentEnroll->student 
                    ? $attendance->studentEnroll->student->first_name . ' ' . $attendance->studentEnroll->student->last_name 
                    : 'Unknown',
                'program' => $session->program->short_form ?? $session->program->title ?? '',
                'program_id' => $session->program_id,
                'clock_in_time' => $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i:s') : null,
                'clock_out_time' => $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i:s') : null,
                'status' => $attendance->status,
                'status_label' => $attendance->status_label,
                'is_late' => $attendance->is_late,
                'has_clocked_out' => $attendance->has_clocked_out,
            ];
        });

        return response()->json([
            'status' => 'success',
            'students' => $students,
            'is_joint_class' => false,
            'class_rep_enroll_id' => $session->class_rep_enroll_id,
            'logbook_delegated' => $session->logbook_delegated,
            'attendance_delegated' => $session->attendance_delegated,
        ]);
    }

    /**
     * Get session statistics (AJAX).
     */
    public function getStats($sessionId)
    {
        $session = ClassSession::with('studentAttendances')->findOrFail($sessionId);

        // For joint classes, get combined stats from all sessions
        $jointSessionIds = [$session->id];
        $isJointClass = false;
        
        if ($session->status === ClassSession::STATUS_IN_PROGRESS) {
            $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
                ->where('date', $session->date)
                ->where('subject_id', $session->subject_id)
                ->where('scheduled_start_time', $session->scheduled_start_time)
                ->where('status', ClassSession::STATUS_IN_PROGRESS)
                ->pluck('id')
                ->toArray();
            
            if (count($jointSessions) > 1) {
                $jointSessionIds = $jointSessions;
                $isJointClass = true;
            }
        }

        // Get stats from all joint sessions
        $total = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)->count();
        $present = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)->whereNotNull('clock_in_time')->count();
        $clockedOut = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)->where('has_clocked_out', true)->count();
        $late = StudentClassAttendance::whereIn('class_session_id', $jointSessionIds)->where('is_late', true)->count();
        $absent = $total - $present;

        return response()->json([
            'status' => 'success',
            'stats' => [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'clocked_out' => $clockedOut,
                'percentage' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ],
            'session_status' => $session->status,
            'is_joint_class' => $isJointClass,
            'program_count' => count($jointSessionIds),
            'actual_start_time' => $session->actual_start_time ? $session->actual_start_time->format('H:i:s') : null,
            'actual_end_time' => $session->actual_end_time ? $session->actual_end_time->format('H:i:s') : null,
            'duration_minutes' => $session->actual_duration_minutes,
        ]);
    }

    /**
     * Get session details for managing completed session (AJAX).
     */
    public function getDetails($sessionId)
    {
        $session = ClassSession::with(['subject', 'program', 'semester', 'section'])
            ->findOrFail($sessionId);
        
        // Check if this is the lecturer's session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to manage this session.'
            ], 403);
        }
        
        // Check if session is from today (only allow editing on same day)
        $today = date('Y-m-d');
        if ($session->date->format('Y-m-d') !== $today) {
            return response()->json([
                'status' => 'error',
                'message' => 'This session is locked. You can only edit sessions from today.'
            ], 403);
        }

        // For joint classes, find all sessions with same subject/time
        $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->with(['program', 'studentAttendances.studentEnroll.student'])
            ->get();
        
        $isJointClass = $jointSessions->count() > 1;
        $jointSessionIds = $jointSessions->pluck('id')->toArray();

        // Collect students from all joint sessions
        $allStudents = collect();
        foreach ($jointSessions as $jSession) {
            foreach ($jSession->studentAttendances as $attendance) {
                $studentName = 'Unknown';
                if ($attendance->studentEnroll && $attendance->studentEnroll->student) {
                    $student = $attendance->studentEnroll->student;
                    $studentName = $student->first_name . ' ' . $student->last_name;
                }
                
                $allStudents->push([
                    'id' => $attendance->id,
                    'student_name' => $studentName,
                    'matricule' => $attendance->matricule,
                    'program' => $jSession->program->short_form ?? $jSession->program->title ?? '',
                    'program_id' => $jSession->program_id,
                    'clock_in_time' => $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i:s') : null,
                    'clock_out_time' => $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i:s') : null,
                    'has_clocked_out' => $attendance->has_clocked_out,
                ]);
            }
        }
        
        // Get logbook data if exists (prefer logbook data over session data)
        $logbook = $session->logbook;
        
        return response()->json([
            'status' => 'success',
            'session' => [
                'id' => $session->id,
                'subject' => $session->subject,
                'program' => $session->program,
                'actual_start_time' => $session->actual_start_time ? $session->actual_start_time->format('H:i:s') : null,
                'actual_end_time' => $session->actual_end_time ? $session->actual_end_time->format('H:i:s') : null,
                'topic_covered' => $logbook->topic_covered ?? $session->topic_covered,
                'learning_objectives' => $logbook->learning_objectives ?? $session->learning_objectives,
                'teaching_methods' => $logbook->teaching_methods ?? $session->teaching_methods,
                'remarks' => $logbook->remarks ?? $session->remarks,
                'status' => $session->status,
            ],
            'students' => $allStudents,
            'is_joint_class' => $isJointClass,
            'program_count' => $jointSessions->count(),
        ]);
    }

    /**
     * Process student QR scan for clock-out on completed session (same day).
     */
    public function scanClockOut(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'student_id' => 'required|string',
        ]);
        
        $session = ClassSession::findOrFail($request->session_id);
        
        // Check authorization
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to manage this session.'
            ], 403);
        }
        
        // Check if session is from today
        $today = date('Y-m-d');
        if ($session->date->format('Y-m-d') !== $today) {
            return response()->json([
                'status' => 'error',
                'message' => 'This session is locked. You can only clock out on the same day.'
            ], 403);
        }
        
        // Extract student ID from scanned data (same logic as scan method)
        $scannedData = trim($request->student_id);
        $studentIdentifier = $scannedData;
        
        // Check if scanned data is a URL containing student/verify/
        if (preg_match('/student\/verify\/([^\/\?\s]+)/i', $scannedData, $matches)) {
            $studentIdentifier = $matches[1];
        }
        // Also handle URL-encoded data
        elseif (preg_match('/student%2Fverify%2F([^%\/\?\s]+)/i', $scannedData, $matches)) {
            $studentIdentifier = urldecode($matches[1]);
        }
        // Check if it's just a full URL with the matricule at the end
        elseif (filter_var($scannedData, FILTER_VALIDATE_URL)) {
            $urlPath = parse_url($scannedData, PHP_URL_PATH);
            if ($urlPath) {
                $segments = explode('/', trim($urlPath, '/'));
                $studentIdentifier = end($segments);
            }
        }
        
        // Find student attendance record
        $attendance = StudentClassAttendance::where('class_session_id', $session->id)
            ->where(function($query) use ($studentIdentifier) {
                $query->where('matricule', $studentIdentifier)
                    ->orWhereHas('studentEnroll.student', function($q) use ($studentIdentifier) {
                        $q->where('student_id', $studentIdentifier);
                    });
            })
            ->with('studentEnroll.student')
            ->first();
        
        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student not found in this class. Scanned: ' . $studentIdentifier
            ], 404);
        }
        
        // Get student name
        $studentName = 'Unknown';
        if ($attendance->studentEnroll && $attendance->studentEnroll->student) {
            $student = $attendance->studentEnroll->student;
            $studentName = $student->first_name . ' ' . $student->last_name;
        }
        
        // Check if student has clocked in
        if (!$attendance->clock_in_time) {
            return response()->json([
                'status' => 'error',
                'message' => $studentName . ' did not clock in for this class.'
            ], 400);
        }
        
        // Check if already clocked out
        if ($attendance->has_clocked_out) {
            return response()->json([
                'status' => 'warning',
                'message' => $studentName . ' has already clocked out at ' . $attendance->clock_out_time->format('H:i:s'),
                'student' => $studentName,
                'time' => $attendance->clock_out_time->format('H:i:s'),
            ]);
        }
        
        // Clock out via QR scan
        $clockOutTime = now()->format('H:i:s');
        $attendance->update([
            'clock_out_time' => $clockOutTime,
            'has_clocked_out' => true,
            'clock_out_device' => 'QR Scan - ' . ($request->header('User-Agent') ?? 'Unknown'),
            'clock_out_ip' => $request->ip(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => $studentName . ' clocked out successfully.',
            'student' => $studentName,
            'time' => $clockOutTime,
        ]);
    }

    /**
     * Get student activity for a session (messages, questions, alerts).
     */
    public function getActivity($sessionId)
    {
        $session = ClassSession::findOrFail($sessionId);
        
        // For joint classes, get activity from all sessions with same subject/time
        $jointSessionIds = [$session->id];
        if ($session->status === ClassSession::STATUS_IN_PROGRESS) {
            $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
                ->where('date', $session->date)
                ->where('subject_id', $session->subject_id)
                ->where('scheduled_start_time', $session->scheduled_start_time)
                ->where('status', ClassSession::STATUS_IN_PROGRESS)
                ->pluck('id')
                ->toArray();
            
            if (count($jointSessions) > 1) {
                $jointSessionIds = $jointSessions;
            }
        }
        
        $isJointClass = count($jointSessionIds) > 1;
        
        // Get messages from all joint sessions (including lecturer messages)
        $messages = \App\Models\ClassSessionMessage::whereIn('class_session_id', $jointSessionIds)
            ->with(['student', 'user', 'classSession.program'])
            ->orderBy('created_at', 'desc')
            ->take(100)
            ->get()
            ->map(function($msg) use ($isJointClass) {
                $isLecturer = $msg->sender_type === \App\Models\ClassSessionMessage::SENDER_LECTURER;
                $senderName = 'Unknown';
                $program = '';
                
                if ($isLecturer && $msg->user) {
                    $senderName = $msg->user->first_name . ' ' . $msg->user->last_name;
                } elseif ($msg->student) {
                    $senderName = $msg->student->first_name . ' ' . $msg->student->last_name;
                }
                
                if ($isJointClass && $msg->classSession && $msg->classSession->program) {
                    $program = $msg->classSession->program->short_form ?? $msg->classSession->program->title ?? '';
                }
                
                return [
                    'id' => $msg->id,
                    'message' => $msg->message,
                    'sender_name' => $senderName,
                    'program' => $program,
                    'is_lecturer' => $isLecturer,
                    'formatted_time' => $msg->created_at->format('H:i'),
                    'created_at' => $msg->created_at->toISOString(),
                ];
            });
        
        // Get questions from all joint sessions
        $questions = \App\Models\ClassSessionQuestion::whereIn('class_session_id', $jointSessionIds)
            ->with(['student', 'classSession.program'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($q) use ($isJointClass) {
                $program = '';
                if ($isJointClass && $q->classSession && $q->classSession->program) {
                    $program = $q->classSession->program->short_form ?? $q->classSession->program->title ?? '';
                }
                
                return [
                    'id' => $q->id,
                    'question' => $q->question,
                    'student_name' => $q->student ? $q->student->first_name . ' ' . $q->student->last_name : 'Unknown',
                    'program' => $program,
                    'is_anonymous' => $q->is_anonymous,
                    'is_answered' => $q->is_answered,
                    'answer' => $q->answer,
                    'formatted_time' => $q->created_at->format('H:i'),
                ];
            });
        
        // Get alerts from all joint sessions
        $alerts = \App\Models\ClassSessionAlert::whereIn('class_session_id', $jointSessionIds)
            ->with(['student', 'classSession.program'])
            ->orderBy('upvotes', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($a) use ($isJointClass) {
                $program = '';
                if ($isJointClass && $a->classSession && $a->classSession->program) {
                    $program = $a->classSession->program->short_form ?? $a->classSession->program->title ?? '';
                }
                
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'content' => $a->content,
                    'alert_type' => $a->alert_type,
                    'program' => $program,
                    'due_date' => $a->due_date ? $a->due_date->format('Y-m-d') : null,
                    'due_time' => $a->due_time,
                    'upvotes' => $a->upvotes,
                    'student_name' => $a->student ? $a->student->first_name . ' ' . $a->student->last_name : 'Unknown',
                    'formatted_time' => $a->created_at->format('H:i'),
                ];
            });
        
        return response()->json([
            'status' => 'success',
            'messages' => $messages,
            'questions' => $questions,
            'alerts' => $alerts,
            'is_joint_class' => $isJointClass,
            'program_count' => count($jointSessionIds),
        ]);
    }

    /**
     * Send message to class as lecturer.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'message' => 'required|string|max:1000',
        ]);

        $session = ClassSession::findOrFail($request->session_id);
        
        // Verify lecturer owns this session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        // Create a single message - it will be visible to all joint sessions
        // since getActivity fetches from all joint sessions
        $message = \App\Models\ClassSessionMessage::create([
            'class_session_id' => $session->id,
            'student_id' => null, // null indicates lecturer message
            'user_id' => Auth::id(),
            'sender_type' => \App\Models\ClassSessionMessage::SENDER_LECTURER,
            'message' => $request->message,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'data' => [
                'id' => $message->id,
                'message' => $message->message,
                'formatted_time' => $message->created_at->format('H:i'),
            ]
        ]);
    }

    /**
     * Answer a student question.
     */
    public function answerQuestion(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'question_id' => 'required|exists:class_session_questions,id',
            'answer' => 'required|string|max:2000',
        ]);

        $session = ClassSession::findOrFail($request->session_id);
        
        // Verify lecturer owns this session
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        // For joint classes, check if question belongs to any joint session
        $jointSessionIds = [$session->id];
        if ($session->status === ClassSession::STATUS_IN_PROGRESS) {
            $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
                ->where('date', $session->date)
                ->where('subject_id', $session->subject_id)
                ->where('scheduled_start_time', $session->scheduled_start_time)
                ->where('status', ClassSession::STATUS_IN_PROGRESS)
                ->pluck('id')
                ->toArray();
            
            if (count($jointSessions) > 1) {
                $jointSessionIds = $jointSessions;
            }
        }

        $question = \App\Models\ClassSessionQuestion::where('id', $request->question_id)
            ->whereIn('class_session_id', $jointSessionIds)
            ->firstOrFail();

        $question->update([
            'answer' => $request->answer,
            'answered_by' => Auth::id(),
            'answered_at' => now(),
            'is_answered' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Answer submitted successfully',
        ]);
    }
}
