<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\User;

class AuditLogController extends Controller
{
    protected $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->access = 'audit-log';

        $this->middleware('permission:'.$this->access.'-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:'.$this->access.'-export', ['only' => ['export']]);
    }

    /**
     * Display a listing of audit logs.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data['title'] = trans_choice('module_audit_trail', 2);

        // Build the query
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by event type
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        // Filter by model type
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%' . $request->auditable_type . '%');
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in description
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'like', '%' . $request->search . '%')
                  ->orWhere('url', 'like', '%' . $request->search . '%')
                  ->orWhere('ip_address', 'like', '%' . $request->search . '%');
            });
        }

        // Paginate results
        $data['rows'] = $query->paginate(25)->appends($request->except('page'));

        // Get filter data
        $data['users'] = User::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']);
        $data['events'] = AuditLog::select('event')->distinct()->pluck('event');
        $data['models'] = AuditLog::select('auditable_type')->distinct()->get()->map(function($item) {
            $parts = explode('\\', $item->auditable_type);
            return [
                'full' => $item->auditable_type,
                'short' => end($parts)
            ];
        })->unique('short');

        return view('admin.audit-log.index', $data);
    }

    /**
     * Display the specified audit log.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data['title'] = trans_choice('module_audit_trail', 1);
        $data['row'] = AuditLog::with('user', 'auditable')->findOrFail($id);

        return view('admin.audit-log.show', $data);
    }

    /**
     * Export audit logs to CSV.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        // Build the query with same filters as index
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%' . $request->auditable_type . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'like', '%' . $request->search . '%')
                  ->orWhere('url', 'like', '%' . $request->search . '%')
                  ->orWhere('ip_address', 'like', '%' . $request->search . '%');
            });
        }

        $logs = $query->get();

        $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'ID',
                'Date & Time',
                'User',
                'Event',
                'Model',
                'Model ID',
                'Description',
                'IP Address',
                'URL'
            ]);

            // Data rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user ? $log->user->name : 'System',
                    $log->event_name,
                    $log->model_name,
                    $log->auditable_id,
                    $log->description,
                    $log->ip_address,
                    $log->url
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get statistics for dashboard widget.
     *
     * @return \Illuminate\Http\Response
     */
    public function stats()
    {
        $stats = [
            'today' => AuditLog::whereDate('created_at', today())->count(),
            'this_week' => AuditLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => AuditLog::whereMonth('created_at', now()->month)->count(),
            'total' => AuditLog::count(),
            'by_event' => AuditLog::selectRaw('event, count(*) as count')
                ->groupBy('event')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'recent' => AuditLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }
}
