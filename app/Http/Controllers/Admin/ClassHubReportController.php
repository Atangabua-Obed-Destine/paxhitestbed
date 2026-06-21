<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\ClassSessionMessage;
use App\Models\ClassSessionAlert;
use App\Models\StudentClassNote;
use App\Models\Subject;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClassHubReportController extends Controller
{
    /**
     * Display the class hub reports dashboard
     */
    public function index(Request $request)
    {
        // Check permission
        if (!auth()->user()->can('class-hub-reports-view')) {
            return redirect()->back()->with('error', 'You do not have permission to access class hub reports.');
        }

        $title = 'Class Hub Reports';

        // Get filter options
        $sessions = Session::where('status', 1)->orderBy('title', 'desc')->get();
        $semesters = Semester::where('status', 1)->orderBy('id', 'desc')->get();
        $programs = Program::where('status', 1)->orderBy('title')->get();
        $subjects = Subject::where('status', 1)->orderBy('title')->get();

        // Apply filters
        $sessionId = $request->get('session_id');
        $semesterId = $request->get('semester_id');
        $programId = $request->get('program_id');
        $subjectId = $request->get('subject_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Base query for class sessions
        $query = ClassSession::query();

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }
        if ($semesterId) {
            $query->whereHas('programSemester', function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            });
        }
        if ($programId) {
            $query->whereHas('programSemester', function($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }
        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        // Get session IDs for subqueries
        $sessionIds = (clone $query)->pluck('id');

        // Overview statistics
        $totalSessions = (clone $query)->count();
        $completedSessions = (clone $query)->where('status', 'completed')->count();
        $totalMessages = ClassSessionMessage::whereIn('class_session_id', $sessionIds)->count();
        $totalAlerts = ClassSessionAlert::whereIn('class_session_id', $sessionIds)->count();
        $totalNotes = StudentClassNote::whereIn('class_session_id', $sessionIds)->count();
        
        // Count sessions that have at least one message
        $activeChatSessions = (clone $query)->whereHas('messages')->count();

        // Top subjects by chat activity - use subquery for message count
        $topSubjectsByChat = ClassSession::select('subject_id')
            ->selectRaw('(SELECT COUNT(*) FROM class_session_messages WHERE class_session_messages.class_session_id = class_sessions.id) as total_messages')
            ->whereIn('id', $sessionIds)
            ->groupBy('subject_id')
            ->with('subject:id,title,code')
            ->orderByDesc('total_messages')
            ->limit(10)
            ->get();

        // Top sessions by engagement - use withCount
        $topSessions = (clone $query)
            ->with(['subject:id,title,code', 'teacher:id,first_name,last_name'])
            ->withCount(['messages', 'alerts', 'studentNotes as notes_count'])
            ->orderByDesc('messages_count')
            ->limit(10)
            ->get();

        // Chat activity by date (last 30 days)
        $chatActivityByDate = ClassSessionMessage::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as message_count')
            )
            ->whereIn('class_session_id', $sessionIds)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Alert types distribution
        $alertTypes = ClassSessionAlert::select('alert_type', DB::raw('COUNT(*) as count'))
            ->whereIn('class_session_id', $sessionIds)
            ->groupBy('alert_type')
            ->get();

        // Recent high-engagement sessions - sessions with messages
        $recentEngagement = (clone $query)
            ->whereHas('messages')
            ->withCount('messages')
            ->with(['subject:id,title,code', 'teacher:id,first_name,last_name'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        return view('admin.class-hub-reports.index', compact(
            'title',
            'sessions',
            'semesters',
            'programs',
            'subjects',
            'totalSessions',
            'completedSessions',
            'totalMessages',
            'totalAlerts',
            'totalNotes',
            'activeChatSessions',
            'topSubjectsByChat',
            'topSessions',
            'chatActivityByDate',
            'alertTypes',
            'recentEngagement'
        ));
    }

    /**
     * View detailed session engagement report
     */
    public function sessionDetail(Request $request, ClassSession $classSession)
    {
        if (!auth()->user()->can('class-hub-reports-view')) {
            return redirect()->back()->with('error', 'You do not have permission to access this report.');
        }

        $title = 'Session Engagement Detail';

        // Load all related data
        $classSession->load([
            'subject:id,title,code',
            'teacher:id,first_name,last_name',
            'messages.studentEnroll.student:id,first_name,last_name,photo',
            'alerts.student:id,first_name,last_name',
            'studentNotes.student:id,first_name,last_name',
            'questions.student:id,first_name,last_name',
            'attendances.studentEnroll.student:id,first_name,last_name'
        ]);

        // Calculate engagement metrics
        $metrics = [
            'total_messages' => $classSession->messages->count(),
            'unique_participants' => $classSession->messages->pluck('student_enroll_id')->unique()->count(),
            'total_alerts' => $classSession->alerts->count(),
            'total_notes' => $classSession->studentNotes->count(),
            'total_questions' => $classSession->questions->count(),
            'answered_questions' => $classSession->questions->where('is_answered', true)->count(),
            'attendance_rate' => $classSession->attendances->count() > 0 
                ? ($classSession->attendances->whereNotNull('clock_in_time')->count() / $classSession->attendances->count()) * 100 
                : 0,
        ];

        // Message timeline
        $messageTimeline = $classSession->messages()
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get();

        // Top contributors
        $topContributors = $classSession->messages()
            ->select('student_enroll_id', DB::raw('COUNT(*) as message_count'))
            ->with('studentEnroll.student:id,first_name,last_name,photo')
            ->groupBy('student_enroll_id')
            ->orderByDesc('message_count')
            ->limit(10)
            ->get();

        // Pinned messages
        $pinnedMessages = $classSession->messages()
            ->where('is_pinned', true)
            ->with('studentEnroll.student:id,first_name,last_name')
            ->orderBy('created_at')
            ->get();

        return view('admin.class-hub-reports.session-detail', compact(
            'title',
            'classSession',
            'metrics',
            'messageTimeline',
            'topContributors',
            'pinnedMessages'
        ));
    }

    /**
     * Export class hub engagement data
     */
    public function export(Request $request)
    {
        if (!auth()->user()->can('class-hub-reports-export')) {
            return redirect()->back()->with('error', 'You do not have permission to export reports.');
        }

        $dateFrom = $request->get('date_from', Carbon::now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        $sessions = ClassSession::with(['subject:id,title,code', 'teacher:id,first_name,last_name'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->withCount(['messages', 'alerts', 'studentNotes as notes_count', 'questions'])
            ->orderByDesc('date')
            ->get();

        // Generate CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="class-hub-report-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($sessions) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'Date',
                'Subject Code',
                'Subject Title',
                'Teacher',
                'Status',
                'Duration (min)',
                'Messages',
                'Alerts',
                'Notes',
                'Questions',
                'Chat Enabled'
            ]);

            foreach ($sessions as $session) {
                fputcsv($file, [
                    $session->date ? $session->date->format('Y-m-d') : 'N/A',
                    $session->subject->code ?? 'N/A',
                    $session->subject->title ?? 'N/A',
                    $session->teacher ? ($session->teacher->first_name . ' ' . $session->teacher->last_name) : 'N/A',
                    $session->status,
                    $session->actual_duration_minutes ?? $session->planned_duration_minutes ?? 0,
                    $session->messages_count,
                    $session->alerts_count,
                    $session->notes_count,
                    $session->questions_count,
                    $session->is_chat_enabled ? 'Yes' : 'No'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
