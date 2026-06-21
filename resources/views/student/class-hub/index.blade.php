@extends('student.layouts.master')
@section('title', $title)

@section('page_css')
<style>
/* ========== CLASS HUB STYLES ========== */
.class-hub-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.class-hub-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: rgba(255,255,255,0.1);
    transform: rotate(45deg);
}

.class-hub-header h2 {
    margin: 0;
    font-weight: 700;
    position: relative;
    z-index: 1;
}

.class-hub-header .date-display {
    opacity: 0.9;
    font-size: 1.1rem;
    position: relative;
    z-index: 1;
}

/* Session Cards */
.session-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border-left: 5px solid #ddd;
    position: relative;
}

.session-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.session-card.live {
    border-left-color: #e74c3c;
    animation: pulse-border 2s infinite;
}

.session-card.upcoming {
    border-left-color: #3498db;
}

.session-card.completed {
    border-left-color: #27ae60;
}

.session-card.cancelled {
    border-left-color: #95a5a6;
    opacity: 0.7;
}

@keyframes pulse-border {
    0%, 100% { box-shadow: 0 5px 15px rgba(231, 76, 60, 0.2); }
    50% { box-shadow: 0 5px 25px rgba(231, 76, 60, 0.4); }
}

/* Live Indicator */
.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e74c3c;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.live-indicator .dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge.clocked-in {
    background: #d4edda;
    color: #155724;
}

.status-badge.not-clocked {
    background: #fff3cd;
    color: #856404;
}

.status-badge.late {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.absent {
    background: #f5f5f5;
    color: #666;
}

/* Session Info */
.session-subject {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.session-meta {
    color: #666;
    font-size: 0.9rem;
}

.session-time {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #eee;
}

.time-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.time-item i {
    color: #667eea;
}

/* Join Button */
.btn-join-class {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 10px 25px;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-join-class:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #666;
}

.empty-state i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 20px;
}

/* Section Headers */
.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #667eea;
}

.section-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.section-header .count-badge {
    background: #667eea;
    color: white;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Quick Stats */
.quick-stats {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.stat-item {
    background: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    text-align: center;
    min-width: 120px;
}

.stat-item .number {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
}

.stat-item .label {
    font-size: 0.85rem;
    color: #666;
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Header -->
        <div class="class-hub-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-chalkboard-teacher mr-3"></i>{{ $title }}</h2>
                    <p class="date-display mb-0">
                        <i class="fas fa-calendar-day mr-2"></i>
                        {{ \Carbon\Carbon::today()->format('l, F d, Y') }}
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('student.class-hub.history') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-history mr-1"></i> View History
                    </a>
                    <a href="{{ route('student.class-hub.my-notes') }}" class="btn btn-light btn-sm ml-2">
                        <i class="fas fa-sticky-note mr-1"></i> My Notes
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-item">
                <div class="number">{{ $todaySessions->count() }}</div>
                <div class="label">Today's Classes</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #e74c3c;">{{ $liveSessions->count() }}</div>
                <div class="label">Live Now</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #3498db;">{{ $upcomingSessions->count() }}</div>
                <div class="label">Upcoming</div>
            </div>
            <div class="stat-item">
                <div class="number" style="color: #27ae60;">{{ $completedSessions->count() }}</div>
                <div class="label">Completed</div>
            </div>
        </div>

        @if($todaySessions->isEmpty())
        <!-- No Classes -->
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h4>No Classes Today</h4>
                    <p>You don't have any scheduled classes for today.</p>
                    <a href="{{ route('student.class-routine.index') }}" class="btn btn-primary">
                        <i class="fas fa-calendar mr-1"></i> View Class Schedule
                    </a>
                </div>
            </div>
        </div>
        @else

        <!-- Live Sessions -->
        @if($liveSessions->count() > 0)
        <div class="section-header">
            <i class="fas fa-broadcast-tower text-danger"></i>
            <h5>Live Now</h5>
            <span class="count-badge bg-danger">{{ $liveSessions->count() }}</span>
        </div>
        
        @foreach($liveSessions as $session)
        <div class="session-card live">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <span class="live-indicator">
                                <span class="dot"></span> LIVE
                            </span>
                            <h5 class="session-subject mt-2">
                                {{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown Subject' }}
                            </h5>
                            <p class="session-meta mb-0">
                                <i class="fas fa-user-tie mr-1"></i> {{ $session->teacher->name ?? 'Unknown Lecturer' }}
                                @if($session->section)
                                <span class="ml-3"><i class="fas fa-users mr-1"></i> {{ $session->section->title }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="session-time">
                        <span class="time-item">
                            <i class="fas fa-play-circle"></i>
                            Started: {{ $session->actual_start_time ? $session->actual_start_time->format('H:i') : 'N/A' }}
                        </span>
                        <span class="time-item">
                            <i class="fas fa-clock"></i>
                            Ends: {{ $session->scheduled_end_time ? $session->scheduled_end_time->format('H:i') : 'N/A' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    @if($session->myAttendance && $session->myAttendance->clock_in_time)
                        <span class="status-badge {{ $session->myAttendance->is_late ? 'late' : 'clocked-in' }}">
                            <i class="fas fa-check-circle mr-1"></i>
                            Clocked In {{ $session->myAttendance->is_late ? '(Late)' : '' }}
                        </span>
                        <small class="d-block mt-1 text-muted">
                            at {{ $session->myAttendance->clock_in_time->format('H:i') }}
                        </small>
                    @else
                        <span class="status-badge not-clocked">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Not Clocked In
                        </span>
                        <small class="d-block mt-1 text-warning">
                            <i class="fas fa-qrcode"></i> Scan your QR code!
                        </small>
                    @endif
                </div>
                <div class="col-md-2 text-right">
                    <a href="{{ route('student.class-hub.live-room', $session->id) }}" class="btn btn-join-class">
                        <i class="fas fa-sign-in-alt mr-1"></i> Join
                    </a>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        <!-- Upcoming Sessions -->
        @if($upcomingSessions->count() > 0)
        <div class="section-header mt-4">
            <i class="fas fa-clock text-primary"></i>
            <h5>Upcoming</h5>
            <span class="count-badge">{{ $upcomingSessions->count() }}</span>
        </div>
        
        @foreach($upcomingSessions as $session)
        <div class="session-card upcoming">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="session-subject">
                        {{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown Subject' }}
                    </h5>
                    <p class="session-meta mb-0">
                        <i class="fas fa-user-tie mr-1"></i> {{ $session->teacher->name ?? 'Unknown Lecturer' }}
                        @if($session->is_extra_class)
                        <span class="badge badge-info ml-2">Extra Class</span>
                        @endif
                    </p>
                    <div class="session-time">
                        <span class="time-item">
                            <i class="fas fa-hourglass-start"></i>
                            Starts: {{ $session->scheduled_start_time ? $session->scheduled_start_time->format('H:i') : 'N/A' }}
                        </span>
                        <span class="time-item">
                            <i class="fas fa-hourglass-end"></i>
                            Ends: {{ $session->scheduled_end_time ? $session->scheduled_end_time->format('H:i') : 'N/A' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <span class="badge badge-secondary">
                        <i class="fas fa-clock mr-1"></i> Not Started
                    </span>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        <!-- Completed Sessions -->
        @if($completedSessions->count() > 0)
        <div class="section-header mt-4">
            <i class="fas fa-check-circle text-success"></i>
            <h5>Completed Today</h5>
            <span class="count-badge bg-success">{{ $completedSessions->count() }}</span>
        </div>
        
        @foreach($completedSessions as $session)
        <div class="session-card completed">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h5 class="session-subject">
                        {{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown Subject' }}
                    </h5>
                    <p class="session-meta mb-0">
                        <i class="fas fa-user-tie mr-1"></i> {{ $session->teacher->name ?? 'Unknown Lecturer' }}
                    </p>
                    <div class="session-time">
                        <span class="time-item">
                            <i class="fas fa-play-circle"></i>
                            {{ $session->actual_start_time ? $session->actual_start_time->format('H:i') : 'N/A' }}
                        </span>
                        <span class="time-item">
                            <i class="fas fa-stop-circle"></i>
                            {{ $session->actual_end_time ? $session->actual_end_time->format('H:i') : 'N/A' }}
                        </span>
                        @if($session->actual_duration_minutes)
                        <span class="time-item">
                            <i class="fas fa-hourglass-half"></i>
                            {{ $session->actual_duration_minutes }} min
                        </span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    @if($session->myAttendance && $session->myAttendance->clock_in_time)
                        <span class="status-badge clocked-in">
                            <i class="fas fa-check mr-1"></i> Attended
                        </span>
                    @else
                        <span class="status-badge absent">
                            <i class="fas fa-times mr-1"></i> Absent
                        </span>
                    @endif
                </div>
                <div class="col-md-2 text-right">
                    <a href="{{ route('student.class-hub.session-details', $session->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye mr-1"></i> View
                    </a>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        @endif
    </div>
</div>
@endsection

@section('page_js')
<script>
// Auto-refresh live status every 30 seconds
setInterval(function() {
    // Could add AJAX refresh here
}, 30000);
</script>
@endsection
