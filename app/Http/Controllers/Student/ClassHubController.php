<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ClassSession;
use App\Models\ClassSessionMessage;
use App\Models\ClassSessionAlert;
use App\Models\ClassSessionQuestion;
use App\Models\StudentClassNote;
use App\Models\StudentClassAttendance;
use App\Models\StudentEnroll;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassHubController extends Controller
{
    protected $title, $route, $view;

    public function __construct()
    {
        $this->title = 'My Classes';
        $this->route = 'student.class-hub';
        $this->view = 'student.class-hub';
    }

    /**
     * Get current student and enrollment
     */
    private function getStudentData()
    {
        $student = Auth::guard('student')->user();
        $enrollmentId = session('selected_enrollment_id');
        $enrollment = null;
        
        if ($enrollmentId) {
            $enrollment = StudentEnroll::with(['program', 'semester', 'section', 'session'])
                            ->where('id', $enrollmentId)
                            ->where('student_id', $student->id)
                            ->first();
        }
        
        return [
            'student' => $student,
            'enrollment' => $enrollment,
        ];
    }

    /**
     * Main class hub - Today's classes
     */
    public function index()
    {
        $data = $this->getStudentData();
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        
        $today = Carbon::today();
        $dayOfWeek = strtolower($today->format('l')); // monday, tuesday, etc.
        
        // Get classes for today where student is enrolled
        $enrollment = $data['enrollment'];
        
        if (!$enrollment) {
            $data['todaySessions'] = collect();
            $data['liveSessions'] = collect();
            return view($this->view . '.index', $data);
        }

        // Get today's class sessions for this student's program/semester/section
        $todaySessions = ClassSession::with(['subject', 'teacher', 'program', 'section'])
            ->whereDate('date', $today)
            ->where('program_id', $enrollment->program_id)
            ->where('semester_id', $enrollment->semester_id)
            ->where('session_id', $enrollment->session_id)
            ->where(function($q) use ($enrollment) {
                $q->whereNull('section_id')
                  ->orWhere('section_id', $enrollment->section_id);
            })
            ->orderBy('scheduled_start_time')
            ->get();

        // Attach attendance status for each session
        foreach ($todaySessions as $session) {
            $attendance = StudentClassAttendance::where('class_session_id', $session->id)
                ->where('student_enroll_id', $enrollment->id)
                ->first();
            $session->myAttendance = $attendance;
        }

        $data['todaySessions'] = $todaySessions;
        $data['liveSessions'] = $todaySessions->where('status', ClassSession::STATUS_IN_PROGRESS);
        $data['upcomingSessions'] = $todaySessions->where('status', ClassSession::STATUS_PENDING);
        $data['completedSessions'] = $todaySessions->where('status', ClassSession::STATUS_COMPLETED);

        return view($this->view . '.index', $data);
    }

    /**
     * View class history with search/filters
     */
    public function history(Request $request)
    {
        $data = $this->getStudentData();
        $data['title'] = 'Class History';
        $data['route'] = $this->route;
        
        $enrollment = $data['enrollment'];
        
        if (!$enrollment) {
            $data['sessions'] = collect();
            return view($this->view . '.history', $data);
        }

        $query = ClassSession::with(['subject', 'teacher', 'program', 'section', 'logbook'])
            ->where('program_id', $enrollment->program_id)
            ->where('semester_id', $enrollment->semester_id)
            ->where('session_id', $enrollment->session_id)
            ->where(function($q) use ($enrollment) {
                $q->whereNull('section_id')
                  ->orWhere('section_id', $enrollment->section_id);
            });

        // Filters
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('topic_covered', 'like', "%{$search}%")
                  ->orWhereHas('subject', function($sq) use ($search) {
                      $sq->where('title', 'like', "%{$search}%")
                         ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        $sessions = $query->orderBy('date', 'desc')
                          ->orderBy('scheduled_start_time', 'desc')
                          ->paginate(15);

        // Attach attendance for each
        foreach ($sessions as $session) {
            $attendance = StudentClassAttendance::where('class_session_id', $session->id)
                ->where('student_enroll_id', $enrollment->id)
                ->first();
            $session->myAttendance = $attendance;
        }

        // Get subjects for filter dropdown
        $data['subjects'] = \App\Models\Subject::whereHas('classSessions', function($q) use ($enrollment) {
            $q->where('program_id', $enrollment->program_id)
              ->where('semester_id', $enrollment->semester_id);
        })->orderBy('code')->get();

        $data['sessions'] = $sessions;
        $data['filters'] = $request->only(['subject_id', 'date_from', 'date_to', 'status', 'search']);

        return view($this->view . '.history', $data);
    }

    /**
     * Live class room - main interaction page
     */
    public function liveRoom($sessionId)
    {
        $data = $this->getStudentData();
        $enrollment = $data['enrollment'];
        
        if (!$enrollment) {
            return redirect()->route('student.class-hub.index')
                           ->with('error', 'No enrollment found');
        }

        $session = ClassSession::with([
            'subject', 'teacher', 'program', 'section', 'semester',
            'logbook', 'alerts' => function($q) {
                $q->orderBy('upvotes', 'desc')->orderBy('created_at', 'desc');
            },
            'questions' => function($q) {
                $q->orderBy('upvotes', 'desc')->orderBy('created_at', 'desc');
            }
        ])->findOrFail($sessionId);

        // Verify student has access to this session
        if ($session->program_id != $enrollment->program_id || 
            $session->semester_id != $enrollment->semester_id ||
            $session->session_id != $enrollment->session_id) {
            return redirect()->route('student.class-hub.index')
                           ->with('error', 'You do not have access to this class');
        }

        // Get student's attendance record
        $attendance = StudentClassAttendance::where('class_session_id', $session->id)
            ->where('student_enroll_id', $enrollment->id)
            ->first();

        // Get or create student's note for this session
        $note = StudentClassNote::getOrCreate($session->id, $data['student']->id, $enrollment->id);

        // For joint classes, get messages from all sessions with same subject/time
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

        // Get recent messages (last 50) from all joint sessions
        $messages = ClassSessionMessage::with(['student', 'user'])
            ->whereIn('class_session_id', $jointSessionIds)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        // Get pinned messages from all joint sessions
        $pinnedMessages = ClassSessionMessage::with(['student', 'user'])
            ->whereIn('class_session_id', $jointSessionIds)
            ->where('is_pinned', true)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get present students count
        $presentCount = StudentClassAttendance::where('class_session_id', $session->id)
            ->whereNotNull('clock_in_time')
            ->count();
        
        $totalEnrolled = StudentClassAttendance::where('class_session_id', $session->id)->count();

        // For joint classes, check delegation across ANY of the joint sessions
        // (delegation is synced across all sessions, but check just in case)
        $isLogbookDelegate = false;
        $isAttendanceDelegate = false;
        
        if (count($jointSessionIds) > 1) {
            // Check any joint session for delegation to this student
            $delegatedSession = ClassSession::whereIn('id', $jointSessionIds)
                ->where('class_rep_enroll_id', $enrollment->id)
                ->first();
            
            if ($delegatedSession) {
                $isLogbookDelegate = $delegatedSession->logbook_delegated;
                $isAttendanceDelegate = $delegatedSession->attendance_delegated;
            }
        } else {
            // Single session - check this session directly
            $isLogbookDelegate = $session->logbook_delegated && 
                                 $session->class_rep_enroll_id == $enrollment->id;
            $isAttendanceDelegate = $session->attendance_delegated && 
                                    $session->class_rep_enroll_id == $enrollment->id;
        }

        $data['title'] = ($session->subject->code ?? 'Class') . ' - Live';
        $data['route'] = $this->route;
        $data['session'] = $session;
        $data['attendance'] = $attendance;
        $data['note'] = $note;
        $data['messages'] = $messages;
        $data['pinnedMessages'] = $pinnedMessages;
        $data['presentCount'] = $presentCount;
        $data['totalEnrolled'] = $totalEnrolled;
        $data['alertTypes'] = ClassSessionAlert::getTypeOptions();
        $data['isLogbookDelegate'] = $isLogbookDelegate;
        $data['isAttendanceDelegate'] = $isAttendanceDelegate;
        $data['jointSessionIds'] = $jointSessionIds;

        return view($this->view . '.live-room', $data);
    }

    /**
     * View session details (for completed sessions)
     */
    public function sessionDetails($sessionId)
    {
        $data = $this->getStudentData();
        $enrollment = $data['enrollment'];
        
        if (!$enrollment) {
            return redirect()->route('student.class-hub.index')
                           ->with('error', 'No enrollment found');
        }

        $session = ClassSession::with([
            'subject', 'teacher', 'program', 'section', 'semester',
            'logbook', 'alerts.student', 'questions.student', 'attendances'
        ])->findOrFail($sessionId);

        // Get student's attendance and note
        $attendance = StudentClassAttendance::where('class_session_id', $session->id)
            ->where('student_enroll_id', $enrollment->id)
            ->first();

        $note = StudentClassNote::where('class_session_id', $session->id)
            ->where('student_id', $data['student']->id)
            ->first();

        // For joint classes, get messages from all sessions with same subject/time
        $jointSessionIds = [$session->id];
        $jointSessions = ClassSession::where('teacher_id', $session->teacher_id)
            ->where('date', $session->date)
            ->where('subject_id', $session->subject_id)
            ->where('scheduled_start_time', $session->scheduled_start_time)
            ->pluck('id')
            ->toArray();
        
        if (count($jointSessions) > 1) {
            $jointSessionIds = $jointSessions;
        }

        // Get all messages for history from all joint sessions
        $messages = ClassSessionMessage::with(['student', 'user'])
            ->whereIn('class_session_id', $jointSessionIds)
            ->orderBy('created_at')
            ->get();

        $data['title'] = ($session->subject->code ?? 'Class') . ' - Details';
        $data['session'] = $session;
        $data['attendance'] = $attendance;
        $data['myAttendance'] = $attendance;
        $data['note'] = $note;
        $data['myNote'] = $note;
        $data['messages'] = $messages;

        return view($this->view . '.session-details', $data);
    }

    /**
     * My Notes - all notes across classes
     */
    public function myNotes(Request $request)
    {
        $data = $this->getStudentData();
        $data['title'] = 'My Class Notes';
        
        $query = StudentClassNote::with(['classSession.subject', 'classSession.teacher'])
            ->where('student_id', $data['student']->id)
            ->whereNotNull('content')
            ->where('content', '!=', '');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('classSession.subject', function($sq) use ($search) {
                      $sq->where('title', 'like', "%{$search}%")
                         ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('classSession', function($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            });
        }

        $notes = $query->orderBy('updated_at', 'desc')->paginate(15);

        $data['notes'] = $notes;
        $data['route'] = $this->route;

        return view($this->view . '.my-notes', $data);
    }

    /**
     * My Attendance Stats
     */
    public function myAttendance()
    {
        $data = $this->getStudentData();
        $data['title'] = 'My Attendance';
        $enrollment = $data['enrollment'];
        
        if (!$enrollment) {
            $data['stats'] = [];
            return view($this->view . '.my-attendance', $data);
        }

        // Get attendance stats per subject
        $stats = DB::table('student_class_attendances as sca')
            ->join('class_sessions as cs', 'sca.class_session_id', '=', 'cs.id')
            ->join('subjects as s', 'cs.subject_id', '=', 's.id')
            ->where('sca.student_enroll_id', $enrollment->id)
            ->select(
                's.id as subject_id',
                's.code as subject_code',
                's.title as subject_title',
                DB::raw('COUNT(*) as total_classes'),
                DB::raw('SUM(CASE WHEN sca.clock_in_time IS NOT NULL THEN 1 ELSE 0 END) as attended'),
                DB::raw('SUM(CASE WHEN sca.is_late = 1 THEN 1 ELSE 0 END) as late_count'),
                DB::raw('SUM(CASE WHEN sca.clock_in_time IS NULL THEN 1 ELSE 0 END) as absent')
            )
            ->groupBy('s.id', 's.code', 's.title')
            ->orderBy('s.code')
            ->get();

        // Calculate percentages
        foreach ($stats as $stat) {
            $stat->attendance_percentage = $stat->total_classes > 0 
                ? round(($stat->attended / $stat->total_classes) * 100, 1)
                : 0;
        }

        $data['stats'] = $stats;
        $data['route'] = $this->route;

        return view($this->view . '.my-attendance', $data);
    }

    // ==================== API ENDPOINTS ====================

    /**
     * Send a message in the class chat
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'message' => 'required|string|max:1000',
            'reply_to_id' => 'nullable|exists:class_session_messages,id',
        ]);

        $data = $this->getStudentData();
        $session = ClassSession::findOrFail($request->session_id);

        // Check if chat is enabled
        if (!($session->chat_enabled ?? true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat is disabled for this class'
            ], 403);
        }

        $message = ClassSessionMessage::create([
            'class_session_id' => $request->session_id,
            'student_id' => $data['student']->id,
            'sender_type' => ClassSessionMessage::SENDER_STUDENT,
            'message' => $request->message,
            'reply_to_id' => $request->reply_to_id,
        ]);

        $message->load('student');

        // TODO: Broadcast event for real-time update
        // event(new ClassMessageSent($message));

        return response()->json([
            'status' => 'success',
            'message' => $message->toArray(),
        ]);
    }

    /**
     * Get messages for polling (fallback if WebSocket unavailable)
     */
    public function getMessages(Request $request, $sessionId)
    {
        $lastId = $request->input('last_id', 0);
        
        $session = ClassSession::find($sessionId);
        if (!$session) {
            return response()->json(['status' => 'error', 'message' => 'Session not found'], 404);
        }
        
        // For joint classes, get messages from all sessions with same subject/time
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
        
        $messages = ClassSessionMessage::with(['student', 'user'])
            ->whereIn('class_session_id', $jointSessionIds)
            ->where('id', '>', $lastId)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'messages' => $messages->map->toArray(),
        ]);
    }

    /**
     * Save/update student note
     */
    public function saveNote(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
        ]);

        $data = $this->getStudentData();
        
        $note = StudentClassNote::updateOrCreate(
            [
                'class_session_id' => $request->session_id,
                'student_id' => $data['student']->id,
            ],
            [
                'student_enroll_id' => $data['enrollment']->id ?? null,
                'title' => $request->title,
                'content' => $request->content,
            ]
        );

        return response()->json([
            'status' => 'success',
            'note' => $note,
        ]);
    }

    /**
     * Create an alert/reminder
     */
    public function createAlert(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'alert_type' => 'required|in:reminder,assignment,exam,important,deadline,other',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable',
        ]);

        $data = $this->getStudentData();
        $session = ClassSession::findOrFail($request->session_id);

        // Check if alerts are enabled
        if (!($session->allow_alerts ?? true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alerts are disabled for this class'
            ], 403);
        }

        $alert = ClassSessionAlert::create([
            'class_session_id' => $request->session_id,
            'student_id' => $data['student']->id,
            'title' => $request->title,
            'content' => $request->content,
            'alert_type' => $request->alert_type,
            'due_date' => $request->due_date,
            'due_time' => $request->due_time,
        ]);

        $alert->load('student');

        return response()->json([
            'status' => 'success',
            'alert' => $alert,
        ]);
    }

    /**
     * Upvote an alert
     */
    public function upvoteAlert(Request $request, $alertId)
    {
        $data = $this->getStudentData();
        $alert = ClassSessionAlert::findOrFail($alertId);
        
        $added = $alert->toggleUpvote($data['student']->id);
        
        return response()->json([
            'status' => 'success',
            'upvoted' => $added,
            'upvotes' => $alert->fresh()->upvotes,
        ]);
    }

    /**
     * Submit a question
     */
    public function submitQuestion(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'question' => 'required|string|max:1000',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $data = $this->getStudentData();
        $session = ClassSession::findOrFail($request->session_id);

        // Check if questions are enabled
        if (!($session->allow_questions ?? true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Questions are disabled for this class'
            ], 403);
        }

        $question = ClassSessionQuestion::create([
            'class_session_id' => $request->session_id,
            'student_id' => $data['student']->id,
            'question' => $request->question,
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        return response()->json([
            'status' => 'success',
            'question' => $question,
        ]);
    }

    /**
     * Get live session status (for dashboard widget)
     */
    public function getLiveStatus()
    {
        $data = $this->getStudentData();
        $enrollment = $data['enrollment'];
        
        if (!$enrollment) {
            return response()->json([
                'status' => 'success',
                'has_live' => false,
                'sessions' => [],
            ]);
        }

        $today = Carbon::today();
        
        // Get active sessions
        $liveSessions = ClassSession::with(['subject', 'teacher'])
            ->whereDate('date', $today)
            ->where('status', ClassSession::STATUS_IN_PROGRESS)
            ->where('program_id', $enrollment->program_id)
            ->where('semester_id', $enrollment->semester_id)
            ->where('session_id', $enrollment->session_id)
            ->where(function($q) use ($enrollment) {
                $q->whereNull('section_id')
                  ->orWhere('section_id', $enrollment->section_id);
            })
            ->get();

        $sessionsData = [];
        foreach ($liveSessions as $session) {
            $attendance = StudentClassAttendance::where('class_session_id', $session->id)
                ->where('student_enroll_id', $enrollment->id)
                ->first();

            $sessionsData[] = [
                'id' => $session->id,
                'subject_code' => $session->subject->code ?? 'N/A',
                'subject_title' => $session->subject->title ?? 'Unknown',
                'teacher_name' => $session->teacher->name ?? 'Unknown',
                'started_at' => $session->actual_start_time ? $session->actual_start_time->format('H:i') : null,
                'scheduled_end' => $session->scheduled_end_time ? $session->scheduled_end_time->format('H:i') : null,
                'clocked_in' => $attendance && $attendance->clock_in_time ? true : false,
                'clock_in_time' => $attendance && $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : null,
                'is_late' => $attendance ? $attendance->is_late : false,
            ];
        }

        return response()->json([
            'status' => 'success',
            'has_live' => count($sessionsData) > 0,
            'sessions' => $sessionsData,
        ]);
    }

    /**
     * Delete a student's note
     */
    public function deleteNote(StudentClassNote $note)
    {
        $data = $this->getStudentData();
        
        if (!$data['enrollment']) {
            return redirect()->back()->with('error', 'No active enrollment found.');
        }

        // Verify ownership
        if ($note->student_enroll_id !== $data['enrollment']->id) {
            return redirect()->back()->with('error', 'You can only delete your own notes.');
        }

        $note->delete();

        return redirect()->back()->with('success', 'Note deleted successfully.');
    }

    /**
     * Export all notes to text file
     */
    public function exportNotes()
    {
        $data = $this->getStudentData();
        
        if (!$data['enrollment']) {
            return redirect()->back()->with('error', 'No active enrollment found.');
        }

        $notes = StudentClassNote::with(['classSession.subject'])
            ->where('student_enroll_id', $data['enrollment']->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $content = "My Class Notes Export\n";
        $content .= "Exported: " . now()->format('F d, Y H:i') . "\n";
        $content .= "Student: " . $data['student']->first_name . " " . $data['student']->last_name . "\n";
        $content .= str_repeat("=", 60) . "\n\n";

        foreach ($notes as $note) {
            $content .= "Subject: " . ($note->classSession->subject->code ?? 'N/A') . " - " . ($note->classSession->subject->title ?? 'Unknown') . "\n";
            $content .= "Class Date: " . ($note->classSession->date ? $note->classSession->date->format('M d, Y') : 'N/A') . "\n";
            $content .= "Note Updated: " . $note->updated_at->format('M d, Y H:i') . "\n";
            $content .= str_repeat("-", 40) . "\n";
            $content .= $note->content . "\n";
            $content .= "\n" . str_repeat("=", 60) . "\n\n";
        }

        $filename = 'class-notes-export-' . now()->format('Y-m-d') . '.txt';

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Save logbook as delegated class representative.
     */
    public function saveLogbook(Request $request, $sessionId)
    {
        $data = $this->getStudentData();
        $enrollment = $data['enrollment'];

        if (!$enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active enrollment found.'
            ], 403);
        }

        $session = ClassSession::findOrFail($sessionId);

        // Verify this student is the delegated class rep
        if (!$session->logbook_delegated || $session->class_rep_enroll_id != $enrollment->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to fill this logbook.'
            ], 403);
        }

        $request->validate([
            'topic_covered' => 'required|string|max:255',
            'content_summary' => 'nullable|string|max:2000',
            'assignments_given' => 'nullable|string|max:500',
            'remarks' => 'nullable|string|max:1000',
        ]);

        // Update the session logbook fields
        $session->update([
            'topic_covered' => $request->topic_covered,
            'learning_objectives' => $request->content_summary,
            'assignments_given' => $request->assignments_given,
            'remarks' => $request->remarks,
            'logbook_filled_by' => 'class_rep',
        ]);

        // Also update or create the logbook record if exists
        if ($session->logbook) {
            $session->logbook->update([
                'topic_covered' => $request->topic_covered,
                'learning_objectives' => $request->content_summary,
                'assignments_given' => $request->assignments_given,
                'remarks' => $request->remarks,
                'class_rep_student_id' => $enrollment->id,
                'updated_by' => null, // Filled by student
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logbook saved successfully!'
        ]);
    }

    /**
     * Mark attendance as delegated class representative.
     */
    public function markAttendance(Request $request, $sessionId)
    {
        $data = $this->getStudentData();
        $enrollment = $data['enrollment'];

        if (!$enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active enrollment found.'
            ], 403);
        }

        $session = ClassSession::findOrFail($sessionId);

        // Verify this student is the delegated class rep for attendance
        if (!$session->attendance_delegated || $session->class_rep_enroll_id != $enrollment->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to mark attendance.'
            ], 403);
        }

        // Check if session is in progress
        if ($session->status !== ClassSession::STATUS_IN_PROGRESS) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class session is not active.'
            ], 400);
        }

        $request->validate([
            'student_id' => 'required|string',
        ]);

        // Extract student ID from scanned data (same logic as admin kiosk)
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

        // Get student name for response
        $studentName = 'Unknown Student';
        if ($attendance->studentEnroll && $attendance->studentEnroll->student) {
            $student = $attendance->studentEnroll->student;
            $studentName = $student->first_name . ' ' . $student->last_name;
        }

        // Get program info
        $programName = '';
        if ($attendance->classSession && $attendance->classSession->program) {
            $programName = $attendance->classSession->program->short_form ?? $attendance->classSession->program->title ?? '';
        }

        // Check if already clocked in
        if ($attendance->clock_in_time) {
            return response()->json([
                'status' => 'already_clocked_in',
                'message' => $studentName . ' has already clocked in at ' . $attendance->clock_in_time->format('H:i'),
                'student' => $studentName,
                'matricule' => $attendance->matricule,
                'program' => $programName,
                'clock_in_time' => $attendance->clock_in_time->format('H:i'),
            ]);
        }

        // Clock in the student
        $now = now();
        $isLate = false;
        $lateMinutes = 0;

        // Check if late (if class has started more than 15 minutes ago)
        if ($session->actual_start_time) {
            $startTime = \Carbon\Carbon::parse($session->actual_start_time);
            $gracePeriod = 15; // minutes
            $lateMinutes = $now->diffInMinutes($startTime);
            if ($lateMinutes > $gracePeriod) {
                $isLate = true;
            }
        }

        $attendance->update([
            'clock_in_time' => $now,
            'status' => $isLate ? StudentClassAttendance::STATUS_LATE : StudentClassAttendance::STATUS_PRESENT,
            'is_late' => $isLate,
            'late_minutes' => $isLate ? $lateMinutes : 0,
            'marked_by_class_rep' => true,
            'clock_in_device' => 'Class Rep Scanner',
            'clock_in_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $studentName . ' clocked in successfully!',
            'student' => $studentName,
            'matricule' => $attendance->matricule,
            'program' => $programName,
            'clock_in_time' => $now->format('H:i'),
            'is_late' => $isLate,
            'action' => 'clock_in',
        ]);
    }
}
