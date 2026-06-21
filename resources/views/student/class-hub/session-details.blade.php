@extends('student.layouts.master')
@section('title', $title)

@section('page_css')
<style>
.session-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    height: 100%;
}

.info-card h5 {
    color: #667eea;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed #eee;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: #666;
    font-weight: 500;
}

.info-value {
    font-weight: 600;
    color: #333;
}

.attendance-badge {
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: 600;
}

.attendance-badge.present {
    background: #d4edda;
    color: #155724;
}

.attendance-badge.late {
    background: #fff3cd;
    color: #856404;
}

.attendance-badge.absent {
    background: #f8d7da;
    color: #721c24;
}

.topic-box {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px;
    border-radius: 12px;
    margin-top: 15px;
}

.topic-box h6 {
    color: #667eea;
    margin-bottom: 10px;
}

.notes-preview {
    background: #fffef0;
    border: 1px solid #ffe066;
    border-radius: 12px;
    padding: 20px;
    margin-top: 15px;
}

.alerts-section .alert-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 10px;
    border-left: 4px solid #667eea;
}

.alerts-section .alert-item.important {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.alerts-section .alert-item.reminder {
    border-left-color: #ffc107;
    background: #fffef5;
}

.message-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #667eea;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 20px;
    border-left: 2px solid #667eea;
    margin-left: 10px;
}

.timeline-item:last-child {
    border-left-color: transparent;
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 0;
    width: 14px;
    height: 14px;
    background: #667eea;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.timeline-time {
    font-size: 0.85rem;
    color: #667eea;
    font-weight: 600;
}

.timeline-text {
    color: #666;
    margin-top: 5px;
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Header -->
        <div class="session-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent p-0 mb-2" style="font-size: 0.9rem;">
                            <li class="breadcrumb-item"><a href="{{ route('student.class-hub.index') }}" class="text-white-50">Class Hub</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('student.class-hub.history') }}" class="text-white-50">History</a></li>
                            <li class="breadcrumb-item active text-white">Session Details</li>
                        </ol>
                    </nav>
                    <h2 class="mb-2">
                        {{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown Subject' }}
                    </h2>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-calendar mr-2"></i> {{ $session->date ? $session->date->format('l, F d, Y') : 'N/A' }}
                        @if($session->is_extra_class)
                        <span class="badge badge-light ml-2">Extra Class</span>
                        @endif
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    @if($session->status == 'in_progress')
                    <a href="{{ route('student.class-hub.live-room', $session->id) }}" class="btn btn-light">
                        <i class="fas fa-broadcast-tower mr-1"></i> Join Live Room
                    </a>
                    @else
                    <a href="{{ route('student.class-hub.history') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Back to History
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Session Information -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5><i class="fas fa-info-circle mr-2"></i> Session Information</h5>
                    
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            @switch($session->status)
                                @case('scheduled')
                                    <span class="badge badge-secondary">Scheduled</span>
                                    @break
                                @case('in_progress')
                                    <span class="badge badge-success">In Progress</span>
                                    @break
                                @case('completed')
                                    <span class="badge badge-primary">Completed</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge badge-danger">Cancelled</span>
                                    @break
                                @default
                                    <span class="badge badge-secondary">{{ $session->status }}</span>
                            @endswitch
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Lecturer</span>
                        <span class="info-value">{{ $session->teacher->name ?? 'Unknown' }}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Scheduled Time</span>
                        <span class="info-value">
                            {{ $session->start_time ? \Carbon\Carbon::parse($session->start_time)->format('H:i') : 'N/A' }} - 
                            {{ $session->end_time ? \Carbon\Carbon::parse($session->end_time)->format('H:i') : 'N/A' }}
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Actual Time</span>
                        <span class="info-value">
                            {{ $session->actual_start_time ? $session->actual_start_time->format('H:i') : 'Not started' }} - 
                            {{ $session->actual_end_time ? $session->actual_end_time->format('H:i') : 'Not ended' }}
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Duration</span>
                        <span class="info-value">
                            {{ $session->actual_duration_minutes ?? $session->planned_duration_minutes ?? 0 }} minutes
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Venue</span>
                        <span class="info-value">{{ $session->venue ?? 'Not specified' }}</span>
                    </div>

                    @if($session->topic_covered)
                    <div class="topic-box">
                        <h6><i class="fas fa-book-open mr-2"></i> Topic Covered</h6>
                        <p class="mb-0">{{ $session->topic_covered }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Attendance Information -->
            <div class="col-md-6">
                <div class="info-card">
                    <h5><i class="fas fa-user-check mr-2"></i> Your Attendance</h5>
                    
                    @if($myAttendance)
                    <div class="text-center mb-4">
                        @if($myAttendance->clock_in_time)
                            @if($myAttendance->is_late)
                            <span class="attendance-badge late">
                                <i class="fas fa-clock mr-1"></i> Attended (Late)
                            </span>
                            @else
                            <span class="attendance-badge present">
                                <i class="fas fa-check-circle mr-1"></i> Present
                            </span>
                            @endif
                        @else
                            <span class="attendance-badge absent">
                                <i class="fas fa-times-circle mr-1"></i> Absent
                            </span>
                        @endif
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Clock In</span>
                        <span class="info-value">{{ $myAttendance->clock_in_time ? $myAttendance->clock_in_time->format('H:i:s') : 'N/A' }}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Clock Out</span>
                        <span class="info-value">{{ $myAttendance->clock_out_time ? $myAttendance->clock_out_time->format('H:i:s') : 'N/A' }}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Method</span>
                        <span class="info-value">
                            @if($myAttendance->clock_in_method)
                            <span class="badge badge-info">{{ ucfirst($myAttendance->clock_in_method) }}</span>
                            @else
                            N/A
                            @endif
                        </span>
                    </div>
                    
                    @if($myAttendance->is_late)
                    <div class="info-row">
                        <span class="info-label">Late Duration</span>
                        <span class="info-value text-warning">{{ $myAttendance->late_duration_minutes ?? 0 }} minutes</span>
                    </div>
                    @endif
                    
                    @else
                    <div class="text-center py-4">
                        <span class="attendance-badge absent">
                            <i class="fas fa-times-circle mr-1"></i> Absent
                        </span>
                        <p class="text-muted mt-3">You did not attend this class session</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- My Notes -->
        @if($myNote)
        <div class="info-card">
            <h5><i class="fas fa-sticky-note mr-2"></i> Your Notes</h5>
            <div class="notes-preview">
                <pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">{{ $myNote->content }}</pre>
            </div>
            <div class="mt-3 text-right">
                <small class="text-muted">Last updated: {{ $myNote->updated_at->format('M d, Y H:i') }}</small>
            </div>
        </div>
        @endif

        <!-- Session Statistics -->
        <div class="info-card">
            <h5><i class="fas fa-chart-bar mr-2"></i> Session Statistics</h5>
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="message-count">
                        <i class="fas fa-comments"></i>
                        {{ $session->message_count ?? 0 }}
                    </div>
                    <p class="text-muted mt-2 mb-0">Chat Messages</p>
                </div>
                <div class="col-md-3">
                    <div class="message-count" style="background: #28a745;">
                        <i class="fas fa-users"></i>
                        {{ $session->attendances->where('clock_in_time', '!=', null)->count() }}
                    </div>
                    <p class="text-muted mt-2 mb-0">Students Attended</p>
                </div>
                <div class="col-md-3">
                    <div class="message-count" style="background: #ffc107;">
                        <i class="fas fa-exclamation-triangle"></i>
                        {{ $session->alerts->count() }}
                    </div>
                    <p class="text-muted mt-2 mb-0">Alerts Posted</p>
                </div>
                <div class="col-md-3">
                    <div class="message-count" style="background: #17a2b8;">
                        <i class="fas fa-question-circle"></i>
                        {{ $session->questions->count() }}
                    </div>
                    <p class="text-muted mt-2 mb-0">Questions Asked</p>
                </div>
            </div>
        </div>

        <!-- Important Alerts from this Session -->
        @if($session->alerts->count() > 0)
        <div class="info-card alerts-section">
            <h5><i class="fas fa-bell mr-2"></i> Class Alerts & Reminders</h5>
            @foreach($session->alerts->take(5) as $alert)
            <div class="alert-item {{ $alert->alert_type }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>{{ $alert->title }}</strong>
                        <p class="mb-0 mt-1">{{ $alert->description }}</p>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-{{ $alert->alert_type == 'important' ? 'danger' : ($alert->alert_type == 'reminder' ? 'warning' : 'info') }}">
                            {{ ucfirst($alert->alert_type) }}
                        </span>
                        <br>
                        <small class="text-muted">{{ $alert->upvote_count }} upvotes</small>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
