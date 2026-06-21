@extends('admin.layouts.master')

@section('title', 'Class Session Details')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h1><i class="fas fa-eye mr-2"></i>Class Session Details</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ $title }}</a></li>
                    <li class="breadcrumb-item active">Details</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <!-- Print Header - Only visible when printing -->
        <div class="print-header">
            <h2>CLASS SESSION LOGBOOK</h2>
            <p><strong>{{ $session->subject->code ?? '' }} - {{ $session->subject->title ?? 'Class Session' }}</strong></p>
            <p>Date: {{ $session->date->format('l, d F Y') }} | Teacher: {{ $session->teacher->first_name ?? '' }} {{ $session->teacher->last_name ?? '' }}</p>
        </div>
        
        <div class="row">
            <!-- Session Info Card -->
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Session Information</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>Date</strong></td>
                                <td>{{ $session->date->format('l, d F Y') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Subject</strong></td>
                                <td>
                                    <strong>{{ $session->subject->code ?? 'N/A' }}</strong> - 
                                    {{ $session->subject->title ?? '' }}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Teacher</strong></td>
                                <td>{{ $session->teacher->first_name ?? '' }} {{ $session->teacher->last_name ?? '' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Program</strong></td>
                                <td>{{ $session->program->title ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Semester</strong></td>
                                <td>{{ $session->semester->title ?? 'N/A' }}</td>
                            </tr>
                            @if($session->section)
                            <tr>
                                <td><strong>Section</strong></td>
                                <td>{{ $session->section->title }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td><strong>Type</strong></td>
                                <td>
                                    @if($session->is_extra_class)
                                        <span class="badge badge-info">Extra Class</span>
                                    @else
                                        <span class="badge badge-primary">Scheduled</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Time Card -->
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-clock mr-2"></i>Timing</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6 class="text-muted">Scheduled</h6>
                                <p class="mb-0">
                                    <strong>{{ \Carbon\Carbon::parse($session->scheduled_start_time)->format('H:i') }}</strong>
                                    - 
                                    <strong>{{ \Carbon\Carbon::parse($session->scheduled_end_time)->format('H:i') }}</strong>
                                </p>
                                <small class="text-muted">{{ $session->scheduled_duration_minutes }} minutes</small>
                            </div>
                            <div class="col-6">
                                <h6 class="text-muted">Actual</h6>
                                @if($session->actual_start_time)
                                <p class="mb-0">
                                    <strong>{{ $session->actual_start_time->format('H:i:s') }}</strong>
                                    @if($session->actual_end_time)
                                    - <strong>{{ $session->actual_end_time->format('H:i:s') }}</strong>
                                    @endif
                                </p>
                                @if($session->actual_duration_minutes)
                                <small class="text-{{ $session->duration_percentage >= 70 ? 'success' : 'warning' }}">
                                    {{ $session->actual_duration_minutes }} minutes ({{ $session->duration_percentage }}%)
                                </small>
                                @endif
                                @else
                                <p class="text-muted mb-0">Not started</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Card -->
                <div class="card card-{{ $session->status == 'completed' ? 'success' : ($session->status == 'in_progress' ? 'info' : ($session->status == 'cancelled' ? 'danger' : 'warning')) }} card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-flag mr-2"></i>Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6 class="text-muted">Session Status</h6>
                                @if($session->status == 'pending')
                                    <span class="badge badge-warning badge-lg p-2">Pending</span>
                                @elseif($session->status == 'in_progress')
                                    <span class="badge badge-success badge-lg p-2">In Progress</span>
                                @elseif($session->status == 'completed')
                                    <span class="badge badge-primary badge-lg p-2">Completed</span>
                                @elseif($session->status == 'cancelled')
                                    <span class="badge badge-danger badge-lg p-2">Cancelled</span>
                                @endif
                            </div>
                            <div class="col-6">
                                <h6 class="text-muted">Lecturer Attendance</h6>
                                @if($session->lecturer_attendance_status == 1)
                                    <span class="badge badge-success p-2">Present</span>
                                @elseif($session->lecturer_attendance_status == 2)
                                    <span class="badge badge-danger p-2">Absent</span>
                                @elseif($session->lecturer_attendance_status == 3)
                                    <span class="badge badge-warning p-2">Late</span>
                                @elseif($session->lecturer_attendance_status == 4)
                                    <span class="badge badge-info p-2">Half Day</span>
                                @else
                                    <span class="badge badge-secondary p-2">Not Synced</span>
                                @endif
                                @if($session->lecturer_attendance_synced)
                                    <br><small class="text-success"><i class="fas fa-check"></i> Synced</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logbook Card -->
            <div class="col-md-6">
                <div class="card card-success card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-book mr-2"></i>Class Logbook</h3>
                        @if($session->logbook && $session->logbook->is_completed)
                            <span class="badge badge-success float-right">Completed</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($session->logbook || $session->topic_covered)
                        <table class="table table-borderless">
                            <tr>
                                <td width="35%"><strong>Topic Covered</strong></td>
                                <td>{{ $session->topic_covered ?: ($session->logbook->topic_covered ?? '-') }}</td>
                            </tr>
                            @if($session->logbook)
                            <tr>
                                <td><strong>Content Summary</strong></td>
                                <td>{{ $session->logbook->content_summary ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Learning Objectives</strong></td>
                                <td>{{ $session->logbook->learning_objectives ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Teaching Methods</strong></td>
                                <td>{{ $session->logbook->teaching_methods ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Materials Used</strong></td>
                                <td>{{ $session->logbook->materials_used ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Assignments Given</strong></td>
                                <td>{{ $session->logbook->assignments_given ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Remarks</strong></td>
                                <td>{{ $session->logbook->remarks ?? '-' }}</td>
                            </tr>
                            @endif
                        </table>
                        @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-book-open fa-3x mb-3"></i>
                            <p>No logbook entry yet</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Class Rep & HOD -->
                <div class="card card-secondary card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-users mr-2"></i>Officials</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6 class="text-muted">Class Representative</h6>
                                @if($session->classRep && $session->classRep->student)
                                <p class="mb-0">
                                    <strong>{{ $session->classRep->student->first_name }} {{ $session->classRep->student->last_name }}</strong>
                                    <br><small>{{ $session->classRep->matricule }}</small>
                                </p>
                                @else
                                <p class="text-muted mb-0">Not assigned</p>
                                @endif
                            </div>
                            <div class="col-6">
                                <h6 class="text-muted">Head of Department</h6>
                                @if($session->hod)
                                <p class="mb-0">
                                    <strong>{{ $session->hod->first_name }} {{ $session->hod->last_name }}</strong>
                                </p>
                                @else
                                <p class="text-muted mb-0">Not assigned</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Attendance Table -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-check mr-2"></i>Student Attendance</h3>
                <div class="card-tools">
                    <span class="badge badge-success">Present: {{ $session->studentAttendances->whereNotNull('clock_in_time')->count() }}</span>
                    <span class="badge badge-warning">Late: {{ $session->studentAttendances->where('is_late', true)->count() }}</span>
                    <span class="badge badge-danger">Absent: {{ $session->studentAttendances->whereNull('clock_in_time')->count() }}</span>
                    <span class="badge badge-primary">Total: {{ $session->studentAttendances->count() }}</span>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Matricule</th>
                            <th>Student Name</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($session->studentAttendances as $index => $attendance)
                        <tr class="{{ $attendance->is_late ? 'table-warning' : ($attendance->clock_in_time ? 'table-success' : '') }}">
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $attendance->matricule }}</td>
                            <td>
                                @if($attendance->studentEnroll && $attendance->studentEnroll->student)
                                {{ $attendance->studentEnroll->student->first_name }} 
                                {{ $attendance->studentEnroll->student->last_name }}
                                @else
                                --
                                @endif
                            </td>
                            <td>
                                @if($attendance->clock_in_time)
                                    {{ $attendance->clock_in_time->format('H:i:s') }}
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </td>
                            <td>
                                @if($attendance->clock_out_time)
                                    {{ $attendance->clock_out_time->format('H:i:s') }}
                                    @if($attendance->is_first_clock_out)
                                        <span class="badge badge-info" title="First to clock out">1st</span>
                                    @endif
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </td>
                            <td>
                                @if($attendance->duration_minutes)
                                    {{ $attendance->duration_minutes }} mins
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $attendance->status_badge_class }}">
                                    {{ $attendance->status_label }}
                                </span>
                                @if($attendance->is_late)
                                    <br><small class="text-warning">{{ $attendance->late_minutes }} mins late</small>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No attendance records</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </a>
            <a href="{{ route($route.'.kiosk') }}?session_id={{ $session->id }}" class="btn btn-info">
                <i class="fas fa-desktop mr-2"></i>Open in Kiosk
            </a>
            @if($session->status == 'completed' && $session->logbook)
            <button class="btn btn-primary float-right" onclick="window.print();">
                <i class="fas fa-print mr-2"></i>Print Logbook
            </button>
            @endif
        </div>

        <!-- Student Activity Section -->
        <div class="row">
            <!-- Quick Stats -->
            <div class="col-12 mb-3">
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-info">
                            <span class="info-box-icon"><i class="fas fa-comments"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Chat Messages</span>
                                <span class="info-box-number">{{ $session->messages->count() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-warning">
                            <span class="info-box-icon"><i class="fas fa-question-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Questions</span>
                                <span class="info-box-number">
                                    {{ $session->questions->count() }}
                                    @if($session->questions->whereNull('answer')->count() > 0)
                                    <small>({{ $session->questions->whereNull('answer')->count() }} unanswered)</small>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-danger">
                            <span class="info-box-icon"><i class="fas fa-bell"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Alerts</span>
                                <span class="info-box-number">{{ $session->alerts->count() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-success">
                            <span class="info-box-icon"><i class="fas fa-sticky-note"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Student Notes</span>
                                <span class="info-box-number">{{ $notesCount ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="col-md-6">
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-comments mr-2"></i>Chat Messages</h3>
                        <span class="badge badge-info float-right">{{ $session->messages->count() }}</span>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        @forelse($session->messages as $message)
                        <div class="direct-chat-msg {{ $message->user_id ? 'right' : '' }}">
                            <div class="direct-chat-infos clearfix">
                                <span class="direct-chat-name {{ $message->user_id ? 'float-right' : 'float-left' }}">
                                    @if($message->user_id)
                                        <i class="fas fa-chalkboard-teacher text-primary"></i>
                                        {{ $message->user->first_name ?? 'Lecturer' }}
                                    @else
                                        {{ $message->student->first_name ?? 'Student' }} {{ $message->student->last_name ?? '' }}
                                    @endif
                                </span>
                                <span class="direct-chat-timestamp {{ $message->user_id ? 'float-left' : 'float-right' }}">
                                    {{ $message->created_at->format('H:i') }}
                                </span>
                            </div>
                            <div class="direct-chat-text {{ $message->user_id ? 'bg-primary' : '' }}">
                                {{ $message->message }}
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>No chat messages</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Questions -->
            <div class="col-md-6">
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-question-circle mr-2"></i>Student Questions</h3>
                        <span class="badge badge-warning float-right">{{ $session->questions->count() }}</span>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        @forelse($session->questions as $question)
                        <div class="callout callout-{{ $question->answer ? 'success' : 'warning' }} py-2">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">
                                    {{ $question->student->first_name ?? 'Student' }} - {{ $question->created_at->format('H:i') }}
                                </small>
                                @if($question->is_anonymous)
                                <span class="badge badge-secondary">Anonymous</span>
                                @endif
                            </div>
                            <p class="mb-1"><strong>Q:</strong> {{ $question->question }}</p>
                            @if($question->answer)
                            <p class="mb-0 text-success">
                                <strong>A:</strong> {{ $question->answer }}
                                <br><small class="text-muted">Answered {{ $question->answered_at ? $question->answered_at->format('H:i') : '' }}</small>
                            </p>
                            @else
                            <small class="text-warning"><i class="fas fa-clock"></i> Awaiting answer</small>
                            @endif
                        </div>
                        @empty
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-question-circle fa-3x mb-3"></i>
                            <p>No questions asked</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <div class="col-12">
                <div class="card card-danger card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bell mr-2"></i>Class Alerts & Reminders</h3>
                        <span class="badge badge-danger float-right">{{ $session->alerts->count() }}</span>
                    </div>
                    <div class="card-body">
                        @if($session->alerts->count() > 0)
                        <div class="row">
                            @foreach($session->alerts as $alert)
                            <div class="col-md-4">
                                <div class="card card-{{ $alert->type == 'important' ? 'danger' : ($alert->type == 'reminder' ? 'warning' : 'info') }} card-outline">
                                    <div class="card-header py-2">
                                        <h5 class="card-title mb-0">
                                            @if($alert->type == 'important')
                                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                            @elseif($alert->type == 'reminder')
                                                <i class="fas fa-clock text-warning"></i>
                                            @else
                                                <i class="fas fa-info-circle text-info"></i>
                                            @endif
                                            {{ $alert->title }}
                                        </h5>
                                    </div>
                                    <div class="card-body py-2">
                                        <p class="mb-1">{{ $alert->message }}</p>
                                        <small class="text-muted">
                                            By {{ $alert->student->first_name ?? 'Student' }} - 
                                            {{ $alert->created_at->format('H:i') }}
                                        </small>
                                        @if($alert->upvotes > 0)
                                        <span class="badge badge-light float-right">
                                            <i class="fas fa-thumbs-up"></i> {{ $alert->upvotes }}
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-bell fa-3x mb-3"></i>
                            <p>No alerts for this session</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Final Back Button -->
        <div class="mb-4">
            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </a>
        </div>
    </div>
</section>
@endsection

@push('styles')
<style>
@media print {
    /* Hide non-essential elements */
    .content-header, 
    .card-tools, 
    .btn, 
    .no-print,
    .main-sidebar,
    .main-header,
    .main-footer,
    .info-box,
    .direct-chat-msg,
    .callout,
    nav,
    footer,
    .breadcrumb {
        display: none !important;
    }
    
    /* Reset layout */
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .content-wrapper {
        margin-left: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    
    .container-fluid {
        padding: 0 !important;
    }
    
    /* Show only logbook-related cards */
    .card {
        border: 1px solid #333 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
        margin-bottom: 15px !important;
    }
    
    .card-header {
        background: #f4f4f4 !important;
        border-bottom: 1px solid #333 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .card-primary.card-outline .card-header,
    .card-info.card-outline .card-header,
    .card-success.card-outline .card-header {
        border-top: 3px solid #333 !important;
    }
    
    /* Table styles */
    .table {
        border-collapse: collapse !important;
    }
    
    .table td, .table th {
        border: 1px solid #ddd !important;
        padding: 8px !important;
    }
    
    /* Hide student activity section for logbook print */
    .info-box,
    .direct-chat-msg,
    .callout {
        display: none !important;
    }
    
    /* Add print header */
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #333;
        padding-bottom: 10px;
    }
    
    /* Page settings */
    @page {
        margin: 1cm;
        size: A4;
    }
    
    /* Ensure colors print */
    .badge {
        border: 1px solid #333 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

/* Hidden by default, shown only in print */
.print-header {
    display: none;
}
</style>
@endpush
