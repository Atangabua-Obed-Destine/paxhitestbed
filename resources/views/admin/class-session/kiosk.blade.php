@extends('admin.layouts.master')

@section('title', $title)

@push('styles')
<style>
    .kiosk-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
    }
    .kiosk-header {
        background: rgba(255,255,255,0.95);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .session-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    .session-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .session-card.active {
        border: 3px solid #28a745;
        background: linear-gradient(145deg, #f0fff4, #ffffff);
    }
    .session-card.pending {
        border-left: 5px solid #ffc107;
    }
    .session-card.completed {
        border-left: 5px solid #28a745;
        opacity: 0.8;
    }
    .session-card.cancelled {
        border-left: 5px solid #dc3545;
        opacity: 0.6;
    }
    .session-card.joint-class {
        border-left: 5px solid #17a2b8;
        background: linear-gradient(145deg, #f0f9ff, #ffffff);
    }
    .session-card.joint-class .joint-class-badge {
        border-bottom: 1px dashed #dee2e6;
        padding-bottom: 8px;
    }
    .session-card.joint-class .joint-sessions-list {
        background: #f8f9fa;
        margin: 0 -20px -20px -20px;
        padding: 10px 20px;
        border-radius: 0 0 15px 15px;
    }
    .scanner-section {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    .stats-card .count {
        font-size: 2.5rem;
        font-weight: bold;
        color: #333;
    }
    .stats-card.present .count { color: #28a745; }
    .stats-card.absent .count { color: #dc3545; }
    .stats-card.late .count { color: #ffc107; }
    .stats-card.total .count { color: #6c757d; }
    #reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        min-height: 300px;
        background: #f8f9fa;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #reader video {
        border-radius: 10px;
    }
    .student-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .student-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
    }
    .student-item:last-child {
        border-bottom: none;
    }
    .student-item.clocked-in {
        background: #d4edda;
    }
    .student-item.clocked-out {
        background: #f8f9fa;
    }
    .student-item.late {
        background: #fff3cd;
    }
    .program-header {
        background: #e9ecef;
        padding: 8px 15px;
        border-left: 4px solid #17a2b8;
        font-size: 0.9rem;
    }
    .joint-badge {
        font-size: 0.7rem;
        vertical-align: middle;
    }
    .logbook-form {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .time-display {
        font-size: 1.5rem;
        font-weight: bold;
        color: #333;
    }
    .recent-scan {
        animation: highlight 2s ease-out;
    }
    @keyframes highlight {
        0% { background: #28a74533; }
        100% { background: transparent; }
    }
    .btn-start-class {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
    }
    .btn-end-class {
        background: linear-gradient(135deg, #dc3545, #c82333);
        border: none;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
    }
    /* Student Activity Section */
    .activity-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .activity-tabs {
        display: flex;
        border-bottom: 2px solid #eee;
        margin-bottom: 15px;
    }
    .activity-tab {
        padding: 10px 20px;
        border: none;
        background: none;
        cursor: pointer;
        font-weight: 500;
        color: #666;
        position: relative;
    }
    .activity-tab.active {
        color: #667eea;
    }
    .activity-tab.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 2px;
    }
    .activity-tab .badge {
        margin-left: 5px;
    }
    .activity-content {
        max-height: 300px;
        overflow-y: auto;
    }
    .chat-message-lecturer {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 10px;
    }
    .chat-message-lecturer.from-lecturer {
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        border-left: 3px solid #2196f3;
    }
    .chat-avatar-sm {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .chat-avatar-sm.lecturer {
        background: linear-gradient(135deg, #2196f3, #1976d2);
    }
    .lecturer-chat-input {
        border-top: 1px solid #eee;
        padding-top: 15px;
    }
    .question-item {
        cursor: pointer;
        transition: all 0.2s;
    }
    .question-item:hover {
        transform: translateX(5px);
    }
    .chat-content {
        flex: 1;
    }
    .chat-sender-name {
        font-weight: 600;
        font-size: 0.85rem;
        color: #333;
    }
    .chat-text {
        font-size: 0.9rem;
        color: #555;
        margin-top: 3px;
    }
    .chat-time {
        font-size: 0.75rem;
        color: #999;
        margin-top: 3px;
    }
    .alert-item {
        display: flex;
        gap: 12px;
        padding: 12px;
        background: #fff3cd;
        border-radius: 10px;
        margin-bottom: 10px;
        border-left: 4px solid #ffc107;
    }
    .alert-item.exam { border-left-color: #dc3545; background: #f8d7da; }
    .alert-item.assignment { border-left-color: #17a2b8; background: #d1ecf1; }
    .alert-item.deadline { border-left-color: #6f42c1; background: #e2d5f1; }
    .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        color: #ffc107;
        font-size: 1.2rem;
    }
    .alert-item.exam .alert-icon { color: #dc3545; }
    .alert-item.assignment .alert-icon { color: #17a2b8; }
    .alert-item.deadline .alert-icon { color: #6f42c1; }
    .question-item {
        padding: 12px;
        background: #e3f2fd;
        border-radius: 10px;
        margin-bottom: 10px;
        border-left: 4px solid #2196f3;
    }
    .question-item.answered {
        background: #e8f5e9;
        border-left-color: #4caf50;
    }
    .question-badge {
        font-size: 0.7rem;
        padding: 3px 8px;
    }
    .no-activity {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }
    .no-activity i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
</style>
@endpush

@section('content')
<div class="kiosk-container">
    <!-- Header -->
    <div class="kiosk-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2 class="mb-1">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                    Class Session Kiosk
                </h2>
                <p class="text-muted mb-0">
                    {{ $teacher->first_name ?? '' }} {{ $teacher->last_name ?? '' }} | 
                    {{ date('l, d F Y', strtotime($today)) }}
                </p>
            </div>
            <div class="col-md-6 text-right">
                <div class="time-display" id="currentTime">{{ date('H:i:s') }}</div>
                <a href="{{ route('admin.dashboard.index') }}" class="btn btn-outline-secondary btn-sm mt-2">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Sessions List -->
        <div class="col-lg-4">
            <div class="mb-3">
                <h5 class="text-white"><i class="fas fa-calendar-day mr-2"></i>Today's Classes</h5>
            </div>

            <!-- Scheduled Sessions -->
            @php
                $displayedJointKeys = [];
            @endphp
            @forelse($scheduled_sessions as $session)
            @php
                $isJoint = $session->is_joint_class ?? false;
                $jointKey = $session->joint_key ?? null;
                $alreadyDisplayed = $jointKey && in_array($jointKey, $displayedJointKeys);
                if ($jointKey && !$alreadyDisplayed) {
                    $displayedJointKeys[] = $jointKey;
                }
            @endphp
            
            @if(!$alreadyDisplayed)
            <div class="session-card {{ $session->status }} {{ $isJoint ? 'joint-class' : '' }}" data-session-id="{{ $session->id }}" @if($isJoint) data-joint-key="{{ $jointKey }}" @endif>
                @if($isJoint)
                <div class="joint-class-badge mb-2">
                    <span class="badge badge-info"><i class="fas fa-users mr-1"></i> Joint Class</span>
                    <small class="text-muted ml-2">
                        {{ count($session->joint_programs) }} programs
                    </small>
                </div>
                @endif
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">{{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown' }}</h6>
                        @if($isJoint)
                        <small class="text-muted d-block mb-1">
                            @foreach($session->joint_programs as $idx => $prog)
                                <span class="badge badge-light mr-1">{{ $prog->short_form ?? $prog->title }}</span>
                            @endforeach
                        </small>
                        @else
                        <small class="text-muted">
                            {{ $session->program->short_form ?? $session->program->title ?? 'N/A' }} | 
                            {{ $session->semester->title ?? 'N/A' }}
                            @if($session->section)
                             | {{ $session->section->title }}
                            @endif
                        </small>
                        @endif
                        <div class="mt-2">
                            <span class="badge badge-info">
                                {{ \Carbon\Carbon::parse($session->scheduled_start_time)->format('H:i') }} - 
                                {{ \Carbon\Carbon::parse($session->scheduled_end_time)->format('H:i') }}
                            </span>
                            @if($session->status == 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($session->status == 'in_progress')
                                <span class="badge badge-success">In Progress</span>
                            @elseif($session->status == 'completed')
                                <span class="badge badge-secondary">Completed</span>
                            @elseif($session->status == 'cancelled')
                                <span class="badge badge-danger">Cancelled</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        @if($session->status == 'pending')
                            @if($isJoint)
                            <button class="btn btn-success btn-sm start-joint-session-btn" 
                                    data-joint-key="{{ $jointKey }}"
                                    data-session-ids="{{ collect($joint_sessions[$jointKey]['sessions'] ?? [])->pluck('id')->implode(',') }}">
                                <i class="fas fa-play mr-1"></i> Start All
                            </button>
                            @else
                            <button class="btn btn-success btn-sm start-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-play mr-1"></i> Start
                            </button>
                            @endif
                        @elseif($session->status == 'in_progress')
                            <button class="btn btn-primary btn-sm select-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-qrcode mr-1"></i> Scan
                            </button>
                        @elseif($session->status == 'completed')
                            <button class="btn btn-secondary btn-sm manage-completed-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-edit mr-1"></i> Manage
                            </button>
                        @endif
                    </div>
                </div>
                
                @if($isJoint && $session->status == 'in_progress')
                {{-- Show all joint session scan buttons when in progress --}}
                <div class="joint-sessions-list mt-2 pt-2 border-top">
                    <small class="text-muted d-block mb-1">Scan attendance for each program:</small>
                    @foreach($joint_sessions[$jointKey]['sessions'] ?? [] as $jointSession)
                    <button class="btn btn-outline-primary btn-sm mr-1 mb-1 select-session-btn" data-id="{{ $jointSession->id }}">
                        <i class="fas fa-qrcode"></i> {{ $jointSession->program->short_form ?? $jointSession->program->title }}
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
            @empty
            <div class="session-card text-center">
                <p class="text-muted mb-0">No scheduled classes for today</p>
            </div>
            @endforelse

            <!-- Extra Sessions -->
            @if($extra_sessions->count() > 0)
            <div class="mt-4">
                <h6 class="text-white"><i class="fas fa-plus-circle mr-2"></i>Extra Classes</h6>
            </div>
            @php
                $displayedExtraJointKeys = [];
            @endphp
            @foreach($extra_sessions as $session)
            @php
                $isJoint = $session->is_joint_class ?? false;
                $jointKey = $session->joint_key ?? null;
                $alreadyDisplayed = $jointKey && in_array($jointKey, $displayedExtraJointKeys);
                if ($jointKey && !$alreadyDisplayed) {
                    $displayedExtraJointKeys[] = $jointKey;
                }
            @endphp
            
            @if(!$alreadyDisplayed)
            <div class="session-card {{ $session->status }} {{ $isJoint ? 'joint-class' : '' }}" data-session-id="{{ $session->id }}" @if($isJoint) data-joint-key="{{ $jointKey }}" @endif>
                @if($isJoint)
                <div class="joint-class-badge mb-2">
                    <span class="badge badge-info"><i class="fas fa-users mr-1"></i> Joint Class</span>
                    <span class="badge badge-primary ml-1">Extra</span>
                    <small class="text-muted ml-2">
                        {{ count($session->joint_programs) }} programs
                    </small>
                </div>
                @else
                <span class="badge badge-primary mb-2">Extra Class</span>
                @endif
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">{{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown' }}</h6>
                        @if($isJoint)
                        <small class="text-muted d-block mb-1">
                            @foreach($session->joint_programs as $idx => $prog)
                                <span class="badge badge-light mr-1">{{ $prog->short_form ?? $prog->title }}</span>
                            @endforeach
                        </small>
                        @else
                        <small class="text-muted">
                            {{ $session->program->short_form ?? $session->program->title ?? 'N/A' }} | 
                            {{ $session->semester->title ?? 'N/A' }}
                            @if($session->section)
                             | {{ $session->section->title }}
                            @endif
                        </small>
                        @endif
                        <div class="mt-2">
                            <span class="badge badge-info">
                                {{ \Carbon\Carbon::parse($session->scheduled_start_time)->format('H:i') }} - 
                                {{ \Carbon\Carbon::parse($session->scheduled_end_time)->format('H:i') }}
                            </span>
                            @if($session->status == 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($session->status == 'in_progress')
                                <span class="badge badge-success">In Progress</span>
                            @elseif($session->status == 'completed')
                                <span class="badge badge-secondary">Completed</span>
                            @elseif($session->status == 'cancelled')
                                <span class="badge badge-danger">Cancelled</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        @if($session->status == 'pending')
                            @if($isJoint)
                            <button class="btn btn-success btn-sm start-joint-session-btn" 
                                    data-joint-key="{{ $jointKey }}"
                                    data-session-ids="{{ collect($extra_joint_sessions[$jointKey]['sessions'] ?? [])->pluck('id')->implode(',') }}">
                                <i class="fas fa-play mr-1"></i> Start All
                            </button>
                            @else
                            <button class="btn btn-success btn-sm start-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-play mr-1"></i> Start
                            </button>
                            @endif
                        @elseif($session->status == 'in_progress')
                            <button class="btn btn-primary btn-sm select-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-qrcode mr-1"></i> Scan
                            </button>
                        @elseif($session->status == 'completed')
                            <button class="btn btn-secondary btn-sm manage-completed-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-edit mr-1"></i> Manage
                            </button>
                        @endif
                    </div>
                </div>
                
                @if($isJoint && $session->status == 'in_progress')
                {{-- Show all joint session scan buttons when in progress --}}
                <div class="joint-sessions-list mt-2 pt-2 border-top">
                    <small class="text-muted d-block mb-1">Scan attendance for each program:</small>
                    @foreach($extra_joint_sessions[$jointKey]['sessions'] ?? [] as $jointSession)
                    <button class="btn btn-outline-primary btn-sm mr-1 mb-1 select-session-btn" data-id="{{ $jointSession->id }}">
                        <i class="fas fa-qrcode"></i> {{ $jointSession->program->short_form ?? $jointSession->program->title }}
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
            @endforeach
            @endif

            <!-- Add Extra Class Button -->
            @if($allow_extra_classes)
            <button class="btn btn-outline-light btn-block mt-3" id="addExtraClassBtn">
                <i class="fas fa-plus mr-2"></i> Add Extra Class
            </button>
            @endif
        </div>

        <!-- Middle Column: Scanner & Stats -->
        <div class="col-lg-5">
            <!-- Active Session Scanner -->
            <div class="scanner-section" id="scannerSection" style="{{ $active_session ? '' : 'display:none;' }}">
                <div class="text-center mb-3">
                    <h5 id="activeSessionTitle">
                        @if($active_session)
                        {{ $active_session->subject->code ?? '' }} - {{ $active_session->subject->title ?? 'Active Class' }}
                        @else
                        Select a Class to Start
                        @endif
                    </h5>
                    
                    <!-- Session Timing Info -->
                    <div id="activeSessionTime" class="mb-2">
                        @if($active_session)
                        <div class="d-flex justify-content-center align-items-center flex-wrap">
                            <span class="badge badge-info mr-2 mb-1">
                                <i class="fas fa-play-circle"></i> Session: {{ \Carbon\Carbon::parse($active_session->actual_start_time)->format('H:i') }}
                            </span>
                            @if($active_session->first_clock_in_time)
                            <span class="badge badge-success mr-2 mb-1">
                                <i class="fas fa-user-check"></i> First Scan: {{ \Carbon\Carbon::parse($active_session->first_clock_in_time)->format('H:i') }}
                            </span>
                            <span class="badge badge-warning mr-2 mb-1" id="calculatedEndBadge">
                                <i class="fas fa-hourglass-end"></i> Ends: {{ $active_session->calculated_end_time->format('H:i') }}
                            </span>
                            @else
                            <span class="badge badge-secondary mr-2 mb-1">
                                <i class="fas fa-clock"></i> Scheduled: {{ \Carbon\Carbon::parse($active_session->scheduled_start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($active_session->scheduled_end_time)->format('H:i') }}
                            </span>
                            <span class="text-muted small ml-2 mb-1">
                                <i class="fas fa-info-circle"></i> Duration starts when first student scans
                            </span>
                            @endif
                        </div>
                        
                        <!-- Progress Bar -->
                        @if($active_session->first_clock_in_time)
                        <div class="mt-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Progress: <strong id="elapsedMinutes">{{ $active_session->elapsed_minutes }}</strong> / {{ $active_session->scheduled_duration_minutes }} min</span>
                                <span id="remainingTime">
                                    @if($active_session->is_overtime)
                                    <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Overtime: {{ $active_session->overtime_minutes }} min</span>
                                    @else
                                    <span class="text-success"><i class="fas fa-hourglass-half"></i> {{ $active_session->remaining_minutes }} min remaining</span>
                                    @endif
                                </span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar {{ $active_session->is_overtime ? 'bg-danger' : 'bg-success' }}" 
                                     role="progressbar" 
                                     id="sessionProgressBar"
                                     style="width: {{ min(100, $active_session->progress_percentage) }}%"
                                     aria-valuenow="{{ $active_session->progress_percentage }}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>

                <!-- QR Scanner -->
                <div id="reader" class="mb-4"></div>
                
                <!-- Scanner Controls -->
                <div class="text-center mb-3" id="scannerControls" style="display: none;">
                    <button type="button" class="btn btn-info btn-sm" id="startScannerBtn">
                        <i class="fa fa-camera"></i> Start Scanner
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="stopScannerBtn" style="display: none;">
                        <i class="fa fa-stop"></i> Stop Scanner
                    </button>
                    <div id="scannerStatus" class="text-muted mt-2 small"></div>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-4">
                        <div class="stats-card present">
                            <div class="count" id="presentCount">0</div>
                            <small>Present</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stats-card late">
                            <div class="count" id="lateCount">0</div>
                            <small>Late</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stats-card total">
                            <div class="count" id="totalCount">0</div>
                            <small>Total</small>
                        </div>
                    </div>
                </div>

                <!-- End Session Button -->
                <div class="text-center">
                    <button class="btn btn-end-class text-white" id="endSessionBtn" style="{{ $active_session ? '' : 'display:none;' }}">
                        <i class="fas fa-stop-circle mr-2"></i> End Class Session
                    </button>
                </div>

                <!-- Recent Scans -->
                <div class="mt-4">
                    <h6><i class="fas fa-history mr-2"></i>Recent Scans</h6>
                    <div id="recentScans" class="student-list" style="max-height: 200px;">
                        <!-- Populated via JS -->
                    </div>
                </div>
            </div>

            <!-- Placeholder when no session active -->
            <div class="scanner-section text-center" id="noSessionPlaceholder" style="{{ $active_session ? 'display:none;' : '' }}">
                <i class="fas fa-chalkboard fa-5x text-muted mb-4"></i>
                <h4 class="text-muted">No Active Class</h4>
                <p class="text-muted">Start a class from the left panel to begin attendance tracking</p>
            </div>
        </div>

        <!-- Right Column: Student List & Logbook -->
        <div class="col-lg-3">
            <!-- Student List -->
            <div class="logbook-form mb-4" id="studentListSection" style="{{ $active_session ? '' : 'display:none;' }}">
                <h6><i class="fas fa-users mr-2"></i>Students</h6>
                <div class="student-list" id="studentList">
                    <!-- Populated via JS -->
                </div>
            </div>

            <!-- Logbook Form -->
            <div class="logbook-form" id="logbookSection" style="{{ $active_session ? '' : 'display:none;' }}">
                <h6><i class="fas fa-book mr-2"></i>Class Logbook</h6>
                <form id="logbookForm">
                    <input type="hidden" id="logbookSessionId" value="{{ $active_session ? $active_session->id : '' }}">
                    <div class="form-group">
                        <label>Topic Covered</label>
                        <input type="text" class="form-control" name="topic_covered" id="topicCovered" placeholder="Enter topic..." value="{{ $active_session->topic_covered ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>Content Summary</label>
                        <textarea class="form-control" name="content_summary" id="contentSummary" rows="2" placeholder="Brief summary...">{{ $active_session->learning_objectives ?? '' }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Assignments Given</label>
                        <input type="text" class="form-control" name="assignments_given" id="assignmentsGiven" placeholder="Any assignments?" value="{{ $active_session->assignments_given ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Additional remarks...">{{ $active_session->remarks ?? '' }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Class Representative</label>
                        <select class="form-control" id="classRepSelect" data-initial-value="{{ $active_session->class_rep_enroll_id ?? '' }}">
                            <option value="">-- Select (Optional) --</option>
                            <!-- Populated via JS -->
                        </select>
                        <div id="delegateCheckboxContainer" style="{{ ($active_session && $active_session->class_rep_enroll_id) ? '' : 'display: none;' }}">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="delegateLogbook" {{ ($active_session && $active_session->logbook_delegated) ? 'checked' : '' }}>
                                <label class="form-check-label" for="delegateLogbook">
                                    <i class="fas fa-book mr-1"></i> Delegate logbook filling
                                </label>
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="delegateAttendance" {{ ($active_session && $active_session->attendance_delegated) ? 'checked' : '' }}>
                                <label class="form-check-label" for="delegateAttendance">
                                    <i class="fas fa-clipboard-check mr-1"></i> Delegate attendance marking
                                </label>
                            </div>
                            <small class="form-text text-muted mt-1">The class rep can perform these tasks from their student portal</small>
                        </div>
                        <div class="alert alert-info mt-2 py-2" id="delegationStatus" style="{{ ($active_session && ($active_session->logbook_delegated || $active_session->attendance_delegated)) ? '' : 'display: none;' }}">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <span id="delegationStatusText">
                                @if($active_session && $active_session->logbook_delegated && $active_session->attendance_delegated)
                                    Logbook & Attendance delegated to class rep
                                @elseif($active_session && $active_session->logbook_delegated)
                                    Logbook delegated to class rep
                                @elseif($active_session && $active_session->attendance_delegated)
                                    Attendance delegated to class rep
                                @endif
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-block" id="saveLogbookBtn">
                        <i class="fas fa-save mr-2"></i> Save Logbook
                    </button>
                </form>
            </div>

            <!-- Student Activity Section -->
            <div class="activity-section" id="activitySection" style="{{ $active_session ? '' : 'display:none;' }}">
                <h6 class="mb-3"><i class="fas fa-comments mr-2"></i>Student Activity</h6>
                
                <div class="activity-tabs">
                    <button class="activity-tab active" data-tab="chat" onclick="switchActivityTab('chat')">
                        <i class="fas fa-comment-dots"></i> Chat
                        <span class="badge badge-primary" id="chatCount">0</span>
                    </button>
                    <button class="activity-tab" data-tab="questions" onclick="switchActivityTab('questions')">
                        <i class="fas fa-question-circle"></i> Questions
                        <span class="badge badge-info" id="questionsCount">0</span>
                    </button>
                    <button class="activity-tab" data-tab="alerts" onclick="switchActivityTab('alerts')">
                        <i class="fas fa-bell"></i> Alerts
                        <span class="badge badge-warning" id="alertsCount">0</span>
                    </button>
                </div>

                <!-- Chat Tab -->
                <div class="activity-content" id="chatTab">
                    <div id="chatMessagesList">
                        <div class="no-activity">
                            <i class="fas fa-comments"></i>
                            <p>No messages yet</p>
                        </div>
                    </div>
                    <!-- Lecturer Chat Input -->
                    <div class="lecturer-chat-input mt-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="lecturerChatInput" placeholder="Send a message to students..." maxlength="500">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button" id="lecturerSendBtn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Questions Tab -->
                <div class="activity-content" id="questionsTab" style="display: none;">
                    <div id="questionsList">
                        <div class="no-activity">
                            <i class="fas fa-question-circle"></i>
                            <p>No questions yet</p>
                        </div>
                    </div>
                    <!-- Quick Answer Input -->
                    <div class="lecturer-answer-section mt-3" id="answerSection" style="display: none;">
                        <div class="alert alert-info mb-2" id="answeringQuestion"></div>
                        <div class="input-group">
                            <input type="text" class="form-control" id="lecturerAnswerInput" placeholder="Type your answer...">
                            <div class="input-group-append">
                                <button class="btn btn-success" type="button" id="submitAnswerBtn">
                                    <i class="fas fa-check"></i> Answer
                                </button>
                                <button class="btn btn-secondary" type="button" onclick="cancelAnswer()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts Tab -->
                <div class="activity-content" id="alertsTab" style="display: none;">
                    <div id="alertsList">
                        <div class="no-activity">
                            <i class="fas fa-bell"></i>
                            <p>No alerts yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Extra Class Modal -->
<div class="modal fade" id="extraClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Extra Class</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="extraClassForm">
                    <div class="form-group">
                        <label>Course <span class="text-danger">*</span></label>
                        <select class="form-control" name="subject_id" id="extraSubject" required>
                            <option value="">-- Select Course --</option>
                            @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" 
                                    data-programs="{{ json_encode($subject->classes->pluck('program_id')->unique()->values()) }}"
                                    data-sessions="{{ json_encode($subject->classes->pluck('session_id')->unique()->values()) }}"
                                    data-semesters="{{ json_encode($subject->classes->pluck('semester_id')->unique()->values()) }}"
                                    data-sections="{{ json_encode($subject->classes->pluck('section_id')->unique()->values()) }}">
                                {{ $subject->code }} - {{ $subject->title }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Program(s) <span class="text-danger">*</span></label>
                        <small class="text-muted d-block mb-2">Select multiple programs to create a joint class</small>
                        <div id="extraProgramCheckboxes" class="program-checkbox-list border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                            @foreach($programs as $program)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input extra-program-checkbox" 
                                       id="extraProgram_{{ $program->id }}" value="{{ $program->id }}">
                                <label class="custom-control-label" for="extraProgram_{{ $program->id }}">
                                    {{ $program->short_form ?? $program->title }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <small class="text-info mt-1 d-block" id="selectedProgramCount">0 program(s) selected</small>
                    </div>
                    <div class="form-group">
                        <label>Academic Year <span class="text-danger">*</span></label>
                        <select class="form-control" name="session_id" id="extraSession" required>
                            <option value="">-- Select Academic Year --</option>
                            @foreach($academic_sessions as $academic_session)
                            <option value="{{ $academic_session->id }}">{{ $academic_session->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Semester <span class="text-danger">*</span></label>
                        <select class="form-control" name="semester_id" id="extraSemester" required>
                            <option value="">-- Select Semester --</option>
                            @foreach($semesters as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select class="form-control" name="section_id" id="extraSection">
                            <option value="">-- All Sections --</option>
                            @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" id="extraStartTime" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" id="extraEndTime" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="createExtraClassBtn">Create Class</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Completed Session Modal -->
<div class="modal fade" id="manageCompletedModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Manage Completed Session</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="completedSessionInfo" class="mb-3">
                    <h6 id="completedSessionTitle">Loading...</h6>
                    <small class="text-muted" id="completedSessionTime"></small>
                </div>
                
                <!-- QR Scanner for Clock Out -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-qrcode mr-2"></i>Student Clock Out Scanner</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Students can scan their ID card QR code to clock out.</p>
                        <div id="completedSessionReader" style="width: 100%; max-width: 400px; margin: 0 auto;"></div>
                        <div class="text-center mt-2">
                            <button type="button" class="btn btn-success btn-sm" id="startClockOutScannerBtn">
                                <i class="fas fa-play mr-1"></i> Start Scanner
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="stopClockOutScannerBtn" style="display:none;">
                                <i class="fas fa-stop mr-1"></i> Stop Scanner
                            </button>
                        </div>
                        <div id="clockOutScanResult" class="text-center mt-2"></div>
                    </div>
                </div>

                <!-- Student Attendance List -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-users mr-2"></i>Student Attendance Status</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Matricule</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="completedStudentList">
                                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Students must scan their ID card QR code to clock out.
                        </small>
                    </div>
                </div>

                <!-- Logbook Section -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-book mr-2"></i>Class Logbook</h6>
                    </div>
                    <div class="card-body">
                        <form id="completedLogbookForm">
                            <input type="hidden" name="session_id" id="completedSessionId">
                            <div class="form-group">
                                <label>Topic Covered</label>
                                <input type="text" class="form-control" name="topic_covered" id="completedTopicCovered">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Learning Objectives</label>
                                        <textarea class="form-control" name="learning_objectives" id="completedLearningObjectives" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Teaching Methods</label>
                                        <textarea class="form-control" name="teaching_methods" id="completedTeachingMethods" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Remarks</label>
                                <textarea class="form-control" name="remarks" id="completedRemarks" rows="2"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-primary btn-sm" id="saveCompletedLogbookBtn">
                            <i class="fas fa-save mr-1"></i> Save Logbook
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('dashboard/js/html5-qrcode.min.js') }}"></script>
<script>
(function() {
    'use strict';
    
    // State
    let activeSessionId = {{ $active_session ? $active_session->id : 'null' }};
    let html5QrCode = null;
    let isScanning = false;
    let lastScannedCode = '';
    let lastScanTime = 0;
    const SCAN_COOLDOWN = {{ $settings['scan_cooldown_seconds'] ?? 60 }} * 1000;
    
    // Update clock
    function updateClock() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-GB');
    }
    setInterval(updateClock, 1000);
    
    // Initialize QR Scanner
    function initScanner() {
        if (html5QrCode) {
            return;
        }
        
        // Show scanner controls
        document.getElementById('scannerControls').style.display = 'block';
        document.getElementById('scannerStatus').textContent = 'Initializing scanner...';
        
        try {
            html5QrCode = new Html5Qrcode("reader");
            
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                isScanning = true;
                document.getElementById('startScannerBtn').style.display = 'none';
                document.getElementById('stopScannerBtn').style.display = 'inline-block';
                document.getElementById('scannerStatus').textContent = 'Scanner active - point at student QR code';
            }).catch(err => {
                console.error("Camera error:", err);
                document.getElementById('scannerStatus').textContent = 'Camera failed - click Start Scanner to retry';
                document.getElementById('startScannerBtn').style.display = 'inline-block';
                Swal.fire('Camera Error', 'Unable to access camera. Please check permissions. Error: ' + err, 'error');
            });
        } catch (e) {
            console.error("Scanner init error:", e);
            document.getElementById('scannerStatus').textContent = 'Scanner library error - refresh page';
            Swal.fire('Scanner Error', 'Failed to initialize scanner: ' + e.message, 'error');
        }
    }
    
    // Start Scanner (manual button)
    function startScanner() {
        if (!activeSessionId) {
            Swal.fire('Error', 'Please start a session first', 'warning');
            return;
        }
        
        if (html5QrCode && isScanning) {
            return;
        }
        
        // Reset and reinitialize
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                html5QrCode = null;
                initScanner();
            }).catch(() => {
                html5QrCode = null;
                initScanner();
            });
        } else {
            initScanner();
        }
    }
    
    // Stop Scanner
    function stopScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                document.getElementById('startScannerBtn').style.display = 'inline-block';
                document.getElementById('stopScannerBtn').style.display = 'none';
                document.getElementById('scannerStatus').textContent = 'Scanner stopped';
            }).catch(err => console.error("Stop error:", err));
        }
    }
    
    // Attach scanner button event listeners
    document.getElementById('startScannerBtn')?.addEventListener('click', startScanner);
    document.getElementById('stopScannerBtn')?.addEventListener('click', stopScanner);
    
    // Handle successful scan
    function onScanSuccess(decodedText) {
        const now = Date.now();
        
        // Debounce
        if (decodedText === lastScannedCode && (now - lastScanTime) < SCAN_COOLDOWN) {
            return;
        }
        
        lastScannedCode = decodedText;
        lastScanTime = now;
        
        // Play beep
        playBeep();
        
        // Send to server
        processScan(decodedText);
    }
    
    function onScanError(errorMessage) {
        // Ignore scan errors (no QR found)
    }
    
    // Process scan via AJAX
    function processScan(studentId) {
        if (!activeSessionId) {
            Swal.fire('Error', 'No active session', 'error');
            return;
        }
        
        fetch('{{ route("admin.class-session.scan") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                student_id: studentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const programInfo = data.program ? `<br><small class="text-info">${data.program}</small>` : '';
                Swal.fire({
                    icon: 'success',
                    title: data.action === 'clock_in' ? 'Clock In' : 'Clock Out',
                    html: `<strong>${data.student}</strong><br>${data.matricule}${programInfo}<br>${data.time}`,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Add to recent scans
                addRecentScan(data);
                
                // Update stats
                if (data.stats) {
                    document.getElementById('presentCount').textContent = data.stats.present;
                    if (data.stats.total) {
                        document.getElementById('totalCount').textContent = data.stats.total;
                    }
                }
                
                // Initialize session timer if this was the first clock-in
                if (data.action === 'clock_in' && data.is_first_clock_in && data.first_clock_in_time) {
                    initializeSessionTimer(data.first_clock_in_time, data.calculated_end_time, data.scheduled_duration_minutes);
                }
                
                // Refresh student list
                loadStudentList();
            } else if (data.status === 'pending_approval') {
                // Early clock-out - needs lecturer approval
                showEarlyClockOutModal(data);
            } else if (data.status === 'warning') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Notice',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Scan error:', error);
            Swal.fire('Error', 'Failed to process scan', 'error');
        });
    }
    
    // Show early clock-out confirmation modal
    function showEarlyClockOutModal(data) {
        Swal.fire({
            title: 'Early Clock-Out Request',
            html: `
                <div class="text-left">
                    <p><strong>${data.student}</strong> (${data.matricule}) is trying to clock out.</p>
                    <p class="text-warning"><i class="fa fa-exclamation-triangle"></i> Session has not reached scheduled end time.</p>
                    <p><strong>Remaining time:</strong> ${data.remaining_minutes} minutes<br>
                    <strong>Scheduled end:</strong> ${data.scheduled_end_time}</p>
                    <hr>
                    <p class="mb-0"><strong>Choose an action:</strong></p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fa fa-sign-out-alt"></i> Give Leave Permission',
            denyButtonText: '<i class="fa fa-stop-circle"></i> End Class Now',
            cancelButtonText: '<i class="fa fa-times"></i> Deny Request',
            confirmButtonColor: '#17a2b8',
            denyButtonColor: '#dc3545',
            reverseButtons: false,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Give leave permission - student leaves, class continues
                approveEarlyClockOut(data.attendance_id, 'leave_permission');
            } else if (result.isDenied) {
                // End class - ends for everyone
                Swal.fire({
                    title: 'End Class for Everyone?',
                    text: 'This will end the class session for all students. Are you sure?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, End Class',
                    confirmButtonColor: '#dc3545'
                }).then((confirmResult) => {
                    if (confirmResult.isConfirmed) {
                        approveEarlyClockOut(data.attendance_id, 'end_class');
                    }
                });
            } else {
                // Cancelled - denied, show notification
                Swal.fire({
                    icon: 'info',
                    title: 'Request Denied',
                    text: `${data.student} must remain until ${data.scheduled_end_time}`,
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });
    }
    
    // Approve early clock-out
    function approveEarlyClockOut(attendanceId, approvalType) {
        fetch('{{ route("admin.class-session.approve-early-clockout") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                attendance_id: attendanceId,
                approval_type: approvalType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: approvalType === 'end_class' ? 'Class Ended' : 'Leave Granted',
                    html: data.message,
                    timer: 3000,
                    showConfirmButton: false
                });
                
                // Add to recent scans
                addRecentScan(data);
                
                // Update stats
                if (data.stats) {
                    document.getElementById('presentCount').textContent = data.stats.present;
                }
                
                // Refresh student list
                loadStudentList();
                
                // If session ended, refresh page after delay
                if (data.session_ended) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Approve error:', error);
            Swal.fire('Error', 'Failed to process approval', 'error');
        });
    }
    
    // Add to recent scans display
    function addRecentScan(data) {
        const container = document.getElementById('recentScans');
        const item = document.createElement('div');
        item.className = 'student-item recent-scan ' + (data.action === 'clock_in' ? 'clocked-in' : 'clocked-out');
        item.innerHTML = `
            <div>
                <strong>${data.student}</strong><br>
                <small>${data.matricule}</small>
            </div>
            <div class="text-right">
                <span class="badge ${data.action === 'clock_in' ? 'badge-success' : 'badge-secondary'}">
                    ${data.action === 'clock_in' ? 'IN' : 'OUT'}
                </span><br>
                <small>${data.time}</small>
            </div>
        `;
        container.insertBefore(item, container.firstChild);
        
        // Keep only last 10
        while (container.children.length > 10) {
            container.removeChild(container.lastChild);
        }
    }
    
    // Play beep sound
    function playBeep() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.3;
            oscillator.start();
            setTimeout(() => oscillator.stop(), 150);
        } catch(e) {
            console.log('Audio not available');
        }
    }
    
    // Start session
    document.querySelectorAll('.start-session-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sessionId = this.dataset.id;
            
            Swal.fire({
                title: 'Start Class?',
                text: 'This will begin attendance tracking for this class.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Start',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    startSession(sessionId);
                }
            });
        });
    });
    
    function startSession(sessionId) {
        fetch('{{ route("admin.class-session.start") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ session_id: sessionId })
        })
        .then(response => {
            console.log('Start session response status:', response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    try {
                        const json = JSON.parse(text);
                        throw new Error(json.message || 'Server error: ' + response.status);
                    } catch(e) {
                        throw new Error('Server error: ' + response.status + ' - ' + text.substring(0, 200));
                    }
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Class Started!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Unknown error occurred', 'error');
            }
        })
        .catch(error => {
            console.error('Start error:', error);
            Swal.fire('Error', error.message || 'Failed to start session', 'error');
        });
    }
    
    // Start all joint sessions at once
    document.querySelectorAll('.start-joint-session-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sessionIds = this.dataset.sessionIds.split(',');
            const jointKey = this.dataset.jointKey;
            const programCount = sessionIds.length;
            
            Swal.fire({
                title: 'Start Joint Class?',
                html: `This will begin attendance tracking for <strong>${programCount} programs</strong> at once.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Yes, Start All ${programCount}`,
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    startJointSessions(sessionIds);
                }
            });
        });
    });
    
    async function startJointSessions(sessionIds) {
        Swal.fire({
            title: 'Starting Joint Class...',
            html: 'Starting sessions for all programs...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        let successCount = 0;
        let errorCount = 0;
        
        for (const sessionId of sessionIds) {
            try {
                const response = await fetch('{{ route("admin.class-session.start") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ session_id: sessionId })
                });
                
                const data = await response.json();
                if (data.status === 'success') {
                    successCount++;
                } else {
                    errorCount++;
                }
            } catch (error) {
                console.error('Error starting session ' + sessionId, error);
                errorCount++;
            }
        }
        
        if (errorCount === 0) {
            Swal.fire({
                icon: 'success',
                title: 'Joint Class Started!',
                text: `All ${successCount} program sessions started successfully.`,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Partially Started',
                text: `${successCount} started, ${errorCount} failed.`,
            }).then(() => {
                window.location.reload();
            });
        }
    }

    // End session
    document.getElementById('endSessionBtn')?.addEventListener('click', function() {
        Swal.fire({
            title: 'End Class?',
            text: 'This will complete the class session and sync attendance.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, End Class',
            cancelButtonText: 'Cancel'
        }).then(result => {
            if (result.isConfirmed) {
                endSession();
            }
        });
    });
    
    function endSession() {
        fetch('{{ route("admin.class-session.end") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ session_id: activeSessionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Class Ended!',
                    html: `Duration: ${data.session.duration_minutes} minutes<br>Present: ${data.session.present_count} students`,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('End error:', error);
            Swal.fire('Error', 'Failed to end session', 'error');
        });
    }
    
    // Load student list
    function loadStudentList() {
        if (!activeSessionId) return;
        
        fetch(`{{ url('admin/class-session') }}/${activeSessionId}/students`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const container = document.getElementById('studentList');
                const classRepSelect = document.getElementById('classRepSelect');
                const savedClassRepId = classRepSelect.value; // Save current selection before clearing
                container.innerHTML = '';
                classRepSelect.innerHTML = '<option value="">-- Select (Optional) --</option>';
                
                const isJointClass = data.is_joint_class || false;
                
                // Group by program if joint class
                let studentsByProgram = {};
                if (isJointClass) {
                    data.students.forEach(student => {
                        const prog = student.program || 'Unknown';
                        if (!studentsByProgram[prog]) {
                            studentsByProgram[prog] = [];
                        }
                        studentsByProgram[prog].push(student);
                    });
                }
                
                if (isJointClass) {
                    // Show grouped by program
                    Object.keys(studentsByProgram).sort().forEach(program => {
                        const programHeader = document.createElement('div');
                        programHeader.className = 'program-header mt-2 mb-1';
                        programHeader.innerHTML = `<strong class="text-info"><i class="fas fa-users"></i> ${program}</strong> <span class="badge badge-light">${studentsByProgram[program].length}</span>`;
                        container.appendChild(programHeader);
                        
                        studentsByProgram[program].forEach(student => {
                            container.appendChild(createStudentItem(student, false));
                            addClassRepOption(classRepSelect, student);
                        });
                    });
                } else {
                    // Single program
                    data.students.forEach(student => {
                        container.appendChild(createStudentItem(student, false));
                        addClassRepOption(classRepSelect, student);
                    });
                }
                
                // Restore class rep selection if previously selected or from session data
                // Restore class rep selection - prioritize: current selection > API data > initial Blade value
                const initialClassRepId = classRepSelect.getAttribute('data-initial-value');
                const classRepToSelect = savedClassRepId || data.class_rep_enroll_id || initialClassRepId;
                if (classRepToSelect) {
                    classRepSelect.value = classRepToSelect;
                    // Show delegate checkbox if class rep is selected
                    document.getElementById('delegateCheckboxContainer').style.display = 'block';
                    
                    // Restore delegation checkbox states from API
                    if (data.logbook_delegated) {
                        document.getElementById('delegateLogbook').checked = true;
                    }
                    if (data.attendance_delegated) {
                        document.getElementById('delegateAttendance').checked = true;
                    }
                    updateDelegationStatus();
                }
                
                // Update counts
                document.getElementById('totalCount').textContent = data.students.length;
                document.getElementById('presentCount').textContent = data.students.filter(s => s.clock_in_time).length;
                document.getElementById('lateCount').textContent = data.students.filter(s => s.is_late).length;
                
                // Show joint class indicator if applicable
                const titleEl = document.getElementById('activeSessionTitle');
                if (isJointClass && titleEl && data.program_count) {
                    const jointBadge = titleEl.querySelector('.joint-badge');
                    if (!jointBadge) {
                        titleEl.innerHTML += ` <span class="joint-badge badge badge-info"><i class="fas fa-users"></i> ${data.program_count} Programs</span>`;
                    }
                }
            }
        })
        .catch(error => console.error('Error loading students:', error));
    }
    
    function createStudentItem(student, showProgram = true) {
        const item = document.createElement('div');
        let statusClass = '';
        if (student.has_clocked_out) statusClass = 'clocked-out';
        else if (student.clock_in_time) statusClass = 'clocked-in';
        if (student.is_late) statusClass += ' late';
        
        item.className = 'student-item ' + statusClass;
        item.innerHTML = `
            <div>
                <strong>${student.name}</strong><br>
                <small>${student.matricule}</small>
                ${showProgram && student.program ? `<br><small class="text-info">${student.program}</small>` : ''}
            </div>
            <div class="text-right">
                <span class="badge ${student.status_label === 'Present' ? 'badge-success' : 
                                  student.status_label === 'Late' ? 'badge-warning' : 'badge-secondary'}">
                    ${student.status_label}
                </span>
                ${student.clock_in_time ? `<br><small>In: ${student.clock_in_time}</small>` : ''}
                ${student.clock_out_time ? `<br><small>Out: ${student.clock_out_time}</small>` : ''}
            </div>
        `;
        return item;
    }
    
    function addClassRepOption(select, student) {
        const option = document.createElement('option');
        option.value = student.enroll_id;
        option.textContent = `${student.matricule} - ${student.name}${student.program ? ' (' + student.program + ')' : ''}`;
        select.appendChild(option);
    }
    
    // Save logbook
    document.getElementById('saveLogbookBtn')?.addEventListener('click', function() {
        const formData = {
            session_id: activeSessionId,
            topic_covered: document.getElementById('topicCovered').value,
            content_summary: document.getElementById('contentSummary').value,
            assignments_given: document.getElementById('assignmentsGiven').value,
            remarks: document.getElementById('remarks').value
        };
        
        fetch('{{ route("admin.class-session.update-logbook") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: 'Logbook updated successfully',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Save logbook error:', error);
            Swal.fire('Error', 'Failed to save logbook', 'error');
        });
    });
    
    // Update class rep
    document.getElementById('classRepSelect')?.addEventListener('change', function() {
        if (!activeSessionId) return;
        
        const hasClassRep = this.value ? true : false;
        const delegateCheckbox = document.getElementById('delegateLogbook');
        const delegateContainer = document.getElementById('delegateCheckboxContainer');
        
        // Show/hide delegate checkbox based on class rep selection
        if (hasClassRep) {
            delegateContainer.style.display = 'block';
        } else {
            delegateContainer.style.display = 'none';
            delegateCheckbox.checked = false;
            // Also clear delegation if class rep is removed
            toggleLogbookDelegation();
        }
        
        fetch('{{ route("admin.class-session.update-class-rep") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                class_rep_enroll_id: this.value || null
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Silently updated
            }
        })
        .catch(error => console.error('Update class rep error:', error));
    });
    
    // Toggle logbook delegation to class rep - attach event listener
    document.getElementById('delegateLogbook')?.addEventListener('change', function() {
        if (!activeSessionId) return;
        
        const delegateCheckbox = document.getElementById('delegateLogbook');
        const delegationStatus = document.getElementById('delegationStatus');
        const delegationStatusText = document.getElementById('delegationStatusText');
        const classRepSelect = document.getElementById('classRepSelect');
        const isDelegated = delegateCheckbox.checked;
        
        fetch('{{ route("admin.class-session.toggle-delegation") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                delegated: isDelegated
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const selectedOption = classRepSelect.options[classRepSelect.selectedIndex];
                if (isDelegated) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Logbook Delegated',
                        html: `<strong>${selectedOption.text}</strong> can now fill the logbook from their student portal.`,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
                updateDelegationStatus();
            } else {
                Swal.fire('Error', data.message, 'error');
                delegateCheckbox.checked = !isDelegated;
            }
        })
        .catch(error => {
            console.error('Toggle delegation error:', error);
            delegateCheckbox.checked = !isDelegated;
            Swal.fire('Error', 'Failed to update delegation', 'error');
        });
    });
    
    // Toggle attendance delegation to class rep
    document.getElementById('delegateAttendance')?.addEventListener('change', function() {
        if (!activeSessionId) return;
        
        const delegateCheckbox = document.getElementById('delegateAttendance');
        const classRepSelect = document.getElementById('classRepSelect');
        const isDelegated = delegateCheckbox.checked;
        
        fetch('{{ route("admin.class-session.toggle-delegation") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                type: 'attendance',
                delegated: isDelegated
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                updateDelegationStatus();
                
                if (isDelegated) {
                    const selectedOption = classRepSelect.options[classRepSelect.selectedIndex];
                    Swal.fire({
                        icon: 'success',
                        title: 'Attendance Delegated',
                        html: `<strong>${selectedOption.text}</strong> can now mark attendance from their student portal.`,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            } else {
                Swal.fire('Error', data.message, 'error');
                delegateCheckbox.checked = !isDelegated;
            }
        })
        .catch(error => {
            console.error('Toggle attendance delegation error:', error);
            delegateCheckbox.checked = !isDelegated;
            Swal.fire('Error', 'Failed to update delegation', 'error');
        });
    });
    
    // Update delegation status text
    function updateDelegationStatus() {
        const logbookDelegated = document.getElementById('delegateLogbook')?.checked;
        const attendanceDelegated = document.getElementById('delegateAttendance')?.checked;
        const delegationStatus = document.getElementById('delegationStatus');
        const delegationStatusText = document.getElementById('delegationStatusText');
        const classRepSelect = document.getElementById('classRepSelect');
        const selectedOption = classRepSelect.options[classRepSelect.selectedIndex];
        const repName = selectedOption?.text || 'class rep';
        
        if (logbookDelegated && attendanceDelegated) {
            delegationStatusText.textContent = `Logbook & Attendance delegated to ${repName}`;
            delegationStatus.style.display = 'block';
        } else if (logbookDelegated) {
            delegationStatusText.textContent = `Logbook delegated to ${repName}`;
            delegationStatus.style.display = 'block';
        } else if (attendanceDelegated) {
            delegationStatusText.textContent = `Attendance delegated to ${repName}`;
            delegationStatus.style.display = 'block';
        } else {
            delegationStatus.style.display = 'none';
        }
    }
    
    // Add extra class modal
    document.getElementById('addExtraClassBtn')?.addEventListener('click', function() {
        // Reset form and checkboxes
        document.getElementById('extraClassForm').reset();
        document.querySelectorAll('.extra-program-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.custom-control').style.display = 'block';
        });
        updateSelectedProgramCount();
        $('#extraClassModal').modal('show');
    });
    
    // Update selected program count
    function updateSelectedProgramCount() {
        const count = document.querySelectorAll('.extra-program-checkbox:checked').length;
        const countEl = document.getElementById('selectedProgramCount');
        if (countEl) {
            countEl.textContent = count + ' program(s) selected';
            countEl.className = count > 0 ? 'text-success mt-1 d-block' : 'text-info mt-1 d-block';
        }
    }
    
    // Handle program checkbox changes
    document.querySelectorAll('.extra-program-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedProgramCount);
    });
    
    // Filter programs when course is selected
    document.getElementById('extraSubject')?.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const validProgramIds = selected.dataset.programs ? JSON.parse(selected.dataset.programs) : [];
        
        // Show/hide program checkboxes based on course assignment
        document.querySelectorAll('.extra-program-checkbox').forEach(cb => {
            const programId = parseInt(cb.value);
            const wrapper = cb.closest('.custom-control');
            if (validProgramIds.length === 0 || validProgramIds.includes(programId)) {
                wrapper.style.display = 'block';
            } else {
                wrapper.style.display = 'none';
                cb.checked = false;
            }
        });
        updateSelectedProgramCount();
    });
    
    document.getElementById('createExtraClassBtn')?.addEventListener('click', function() {
        const form = document.getElementById('extraClassForm');
        
        // Get selected program IDs
        const selectedPrograms = Array.from(document.querySelectorAll('.extra-program-checkbox:checked'))
            .map(cb => cb.value);
        
        if (selectedPrograms.length === 0) {
            Swal.fire('Error', 'Please select at least one program', 'error');
            return;
        }
        
        const formData = {
            subject_id: document.getElementById('extraSubject').value,
            program_ids: selectedPrograms,
            session_id: document.getElementById('extraSession').value,
            semester_id: document.getElementById('extraSemester').value,
            section_id: document.getElementById('extraSection').value || null,
            start_time: document.getElementById('extraStartTime').value,
            end_time: document.getElementById('extraEndTime').value
        };
        
        // Validate required fields
        if (!formData.subject_id || !formData.session_id || !formData.semester_id || !formData.start_time || !formData.end_time) {
            Swal.fire('Error', 'Please fill in all required fields', 'error');
            return;
        }
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
        
        fetch('{{ route("admin.class-session.create-extra") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat().join('\n');
                        throw new Error(errorMessages);
                    }
                    throw new Error(data.message || 'Failed to create extra class');
                }
                return data;
            });
        })
        .then(data => {
            if (data.status === 'success') {
                const sessionCount = data.sessions_created || selectedPrograms.length;
                Swal.fire({
                    icon: 'success',
                    title: sessionCount > 1 ? `${sessionCount} Joint Classes Created!` : 'Extra Class Created!',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                btn.disabled = false;
                btn.innerHTML = 'Create Class';
                Swal.fire('Error', data.message || 'Failed to create extra class', 'error');
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = 'Create Class';
            console.error('Create extra class error:', error);
            Swal.fire('Error', error.message || 'Failed to create extra class', 'error');
        });
    });
    
    // Auto-refresh stats every 30 seconds
    function refreshStats() {
        if (!activeSessionId) return;
        
        fetch(`{{ url('admin/class-session') }}/${activeSessionId}/stats`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('presentCount').textContent = data.stats.present;
                document.getElementById('lateCount').textContent = data.stats.late;
                document.getElementById('totalCount').textContent = data.stats.total;
            }
        })
        .catch(error => console.error('Refresh stats error:', error));
    }
    setInterval(refreshStats, 30000);
    
    // Session timer/progress tracking
    let sessionTimerData = {
        firstClockInTime: null,
        scheduledDurationMinutes: {{ $active_session ? $active_session->scheduled_duration_minutes : 0 }},
        calculatedEndTime: null,
    };
    
    @if($active_session && $active_session->first_clock_in_time)
    sessionTimerData.firstClockInTime = new Date('{{ $active_session->first_clock_in_time->toISOString() }}');
    sessionTimerData.calculatedEndTime = new Date('{{ $active_session->calculated_end_time->toISOString() }}');
    @endif
    
    function updateSessionProgress() {
        if (!sessionTimerData.firstClockInTime || !activeSessionId) return;
        
        const now = new Date();
        const elapsed = Math.floor((now - sessionTimerData.firstClockInTime) / 60000); // minutes
        const scheduledDuration = sessionTimerData.scheduledDurationMinutes;
        const remaining = scheduledDuration - elapsed;
        const progressPercent = Math.min(100, (elapsed / scheduledDuration) * 100);
        const isOvertime = remaining < 0;
        
        // Update elapsed minutes display
        const elapsedEl = document.getElementById('elapsedMinutes');
        if (elapsedEl) elapsedEl.textContent = elapsed;
        
        // Update progress bar
        const progressBar = document.getElementById('sessionProgressBar');
        if (progressBar) {
            progressBar.style.width = `${Math.min(100, progressPercent)}%`;
            progressBar.className = `progress-bar ${isOvertime ? 'bg-danger' : 'bg-success'}`;
        }
        
        // Update remaining time display
        const remainingEl = document.getElementById('remainingTime');
        if (remainingEl) {
            if (isOvertime) {
                remainingEl.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Overtime: ${Math.abs(remaining)} min</span>`;
            } else {
                remainingEl.innerHTML = `<span class="text-success"><i class="fas fa-hourglass-half"></i> ${remaining} min remaining</span>`;
            }
        }
        
        // Update calculated end badge color if overtime
        const endBadge = document.getElementById('calculatedEndBadge');
        if (endBadge && isOvertime) {
            endBadge.className = 'badge badge-danger mr-2 mb-1';
        }
    }
    
    // Initialize session timer when first student clocks in
    function initializeSessionTimer(firstClockInTime, calculatedEndTime, scheduledDurationMinutes) {
        sessionTimerData.firstClockInTime = new Date(firstClockInTime);
        sessionTimerData.calculatedEndTime = new Date(calculatedEndTime);
        sessionTimerData.scheduledDurationMinutes = scheduledDurationMinutes;
        
        // Update the UI to show the timer info
        const activeSessionTimeEl = document.getElementById('activeSessionTime');
        if (activeSessionTimeEl) {
            const firstClockInFormatted = sessionTimerData.firstClockInTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
            const calculatedEndFormatted = sessionTimerData.calculatedEndTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
            
            activeSessionTimeEl.innerHTML = `
                <div class="d-flex justify-content-center align-items-center flex-wrap">
                    <span class="badge badge-info mr-2 mb-1">
                        <i class="fas fa-play-circle"></i> Session Started
                    </span>
                    <span class="badge badge-success mr-2 mb-1">
                        <i class="fas fa-user-check"></i> First Scan: ${firstClockInFormatted}
                    </span>
                    <span class="badge badge-warning mr-2 mb-1" id="calculatedEndBadge">
                        <i class="fas fa-hourglass-end"></i> Ends: ${calculatedEndFormatted}
                    </span>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Progress: <strong id="elapsedMinutes">0</strong> / ${scheduledDurationMinutes} min</span>
                        <span id="remainingTime">
                            <span class="text-success"><i class="fas fa-hourglass-half"></i> ${scheduledDurationMinutes} min remaining</span>
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" id="sessionProgressBar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            `;
        }
        
        // Start the progress updater
        updateSessionProgress();
        setInterval(updateSessionProgress, 60000);
    }
    
    // Update session progress every minute
    if (sessionTimerData.firstClockInTime) {
        updateSessionProgress(); // Initial update
        setInterval(updateSessionProgress, 60000); // Update every minute
    }
    
    // Store all dropdown options for filtering
    const allPrograms = @json($programs);
    const allSessions = @json($academic_sessions);
    const allSemesters = @json($semesters);
    const allSections = @json($sections);
    
    // Filter dropdowns when subject changes
    document.getElementById('extraSubject')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            // Reset all dropdowns
            resetDropdown('extraProgram', allPrograms, 'id', 'title');
            resetDropdown('extraSession', allSessions, 'id', 'title');
            resetDropdown('extraSemester', allSemesters, 'id', 'title');
            resetDropdown('extraSection', allSections, 'id', 'title');
            return;
        }
        
        // Get allowed IDs from data attributes
        const allowedPrograms = JSON.parse(selectedOption.dataset.programs || '[]');
        const allowedSessions = JSON.parse(selectedOption.dataset.sessions || '[]');
        const allowedSemesters = JSON.parse(selectedOption.dataset.semesters || '[]');
        const allowedSections = JSON.parse(selectedOption.dataset.sections || '[]');
        
        // Filter and repopulate dropdowns
        filterDropdown('extraProgram', allPrograms, allowedPrograms, 'id', 'title', 'short_form');
        filterDropdown('extraSession', allSessions, allowedSessions, 'id', 'title');
        filterDropdown('extraSemester', allSemesters, allowedSemesters, 'id', 'title');
        filterDropdown('extraSection', allSections, allowedSections, 'id', 'title');
    });
    
    function filterDropdown(selectId, allItems, allowedIds, valueKey, textKey, altTextKey) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        const currentValue = select.value;
        select.innerHTML = '<option value="">-- Select --</option>';
        
        allItems.forEach(item => {
            if (allowedIds.includes(item[valueKey])) {
                const option = document.createElement('option');
                option.value = item[valueKey];
                option.textContent = (altTextKey && item[altTextKey]) ? item[altTextKey] : item[textKey];
                if (item[valueKey] == currentValue) option.selected = true;
                select.appendChild(option);
            }
        });
        
        // Auto-select if only one option
        if (select.options.length === 2) {
            select.selectedIndex = 1;
        }
    }
    
    function resetDropdown(selectId, allItems, valueKey, textKey, altTextKey) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        select.innerHTML = '<option value="">-- Select --</option>';
        allItems.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = (altTextKey && item[altTextKey]) ? item[altTextKey] : item[textKey];
            select.appendChild(option);
        });
    }
    
    // ========== MANAGE COMPLETED SESSION HANDLERS ==========
    let managingSessionId = null;
    
    // Handle Manage Completed Session button click
    document.addEventListener('click', function(e) {
        if (e.target.closest('.manage-completed-btn')) {
            const btn = e.target.closest('.manage-completed-btn');
            managingSessionId = btn.dataset.id;
            loadCompletedSessionData(managingSessionId);
            $('#manageCompletedModal').modal('show');
        }
    });
    
    // Load completed session data
    function loadCompletedSessionData(sessionId) {
        fetch(`{{ url('admin/class-session') }}/${sessionId}/details`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const session = data.session;
                document.getElementById('completedSessionId').value = session.id;
                
                // Build title with joint class indicator
                let titleHtml = (session.subject?.code || '') + ' - ' + (session.subject?.title || 'Class Session');
                if (data.is_joint_class && data.program_count) {
                    titleHtml += ` <span class="badge badge-info ml-2"><i class="fas fa-users"></i> ${data.program_count} Programs</span>`;
                }
                document.getElementById('completedSessionTitle').innerHTML = titleHtml;
                document.getElementById('completedSessionTime').textContent = 
                    'Duration: ' + (session.actual_start_time || 'N/A') + ' - ' + (session.actual_end_time || 'N/A');
                
                // Populate logbook fields
                document.getElementById('completedTopicCovered').value = session.topic_covered || '';
                document.getElementById('completedLearningObjectives').value = session.learning_objectives || '';
                document.getElementById('completedTeachingMethods').value = session.teaching_methods || '';
                document.getElementById('completedRemarks').value = session.remarks || '';
                
                // Populate student list
                renderCompletedStudentList(data.students);
            }
        })
        .catch(err => {
            console.error('Error loading session data:', err);
            Swal.fire('Error', 'Failed to load session data', 'error');
        });
    }
    
    // Render student list in modal
    function renderCompletedStudentList(students) {
        const tbody = document.getElementById('completedStudentList');
        if (!students || students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No students found</td></tr>';
            return;
        }
        
        tbody.innerHTML = students.map(student => `
            <tr data-attendance-id="${student.id}" data-matricule="${student.matricule}">
                <td>
                    ${student.student_name || 'Unknown'}
                    ${student.program ? `<br><small class="badge badge-info">${student.program}</small>` : ''}
                </td>
                <td><code>${student.matricule || 'N/A'}</code></td>
                <td>${student.clock_in_time || '<span class="text-danger">-</span>'}</td>
                <td>${student.clock_out_time || '<span class="text-warning">Pending</span>'}</td>
                <td>
                    ${student.clock_out_time ? 
                        '<span class="badge badge-success"><i class="fas fa-check"></i> Complete</span>' : 
                        (student.clock_in_time ? 
                            '<span class="badge badge-warning"><i class="fas fa-clock"></i> Awaiting Scan</span>' : 
                            '<span class="badge badge-secondary">-</span>')
                    }
                </td>
            </tr>
        `).join('');
    }
    
    // ========== COMPLETED SESSION QR SCANNER ==========
    let completedSessionScanner = null;
    let isCompletedScannerRunning = false;
    
    // Start clock-out scanner for completed session
    document.getElementById('startClockOutScannerBtn')?.addEventListener('click', function() {
        if (!managingSessionId) {
            Swal.fire('Error', 'No session selected', 'error');
            return;
        }
        
        startCompletedSessionScanner();
    });
    
    // Stop clock-out scanner
    document.getElementById('stopClockOutScannerBtn')?.addEventListener('click', function() {
        stopCompletedSessionScanner();
    });
    
    function startCompletedSessionScanner() {
        if (completedSessionScanner) {
            return;
        }
        
        completedSessionScanner = new Html5Qrcode("completedSessionReader");
        
        const config = {
            fps: 10,
            qrbox: { width: 200, height: 200 },
            aspectRatio: 1.0
        };
        
        completedSessionScanner.start(
            { facingMode: "environment" },
            config,
            onCompletedSessionScanSuccess,
            (errorMessage) => { /* Ignore scan errors */ }
        ).then(() => {
            isCompletedScannerRunning = true;
            document.getElementById('startClockOutScannerBtn').style.display = 'none';
            document.getElementById('stopClockOutScannerBtn').style.display = 'inline-block';
        }).catch(err => {
            console.error("Camera error:", err);
            Swal.fire('Camera Error', 'Unable to access camera. Please check permissions.', 'error');
        });
    }
    
    function stopCompletedSessionScanner() {
        if (completedSessionScanner && isCompletedScannerRunning) {
            completedSessionScanner.stop().then(() => {
                completedSessionScanner.clear();
                completedSessionScanner = null;
                isCompletedScannerRunning = false;
                document.getElementById('startClockOutScannerBtn').style.display = 'inline-block';
                document.getElementById('stopClockOutScannerBtn').style.display = 'none';
            }).catch(err => {
                console.error("Error stopping scanner:", err);
            });
        }
    }
    
    let lastCompletedScanCode = '';
    let lastCompletedScanTime = 0;
    
    function onCompletedSessionScanSuccess(decodedText) {
        const now = Date.now();
        
        // Prevent duplicate scans within cooldown
        if (decodedText === lastCompletedScanCode && (now - lastCompletedScanTime) < SCAN_COOLDOWN) {
            return;
        }
        
        lastCompletedScanCode = decodedText;
        lastCompletedScanTime = now;
        
        // Show scanning indicator
        document.getElementById('clockOutScanResult').innerHTML = 
            '<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Processing...</span>';
        
        // Send clock-out scan request
        fetch(`{{ route('admin.class-session.scan-clock-out') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                session_id: managingSessionId,
                student_id: decodedText
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('clockOutScanResult').innerHTML = 
                    `<span class="text-success"><i class="fas fa-check-circle"></i> ${data.student} clocked out at ${data.time}</span>`;
                
                // Refresh the student list
                loadCompletedSessionData(managingSessionId);
                
                // Play success sound or beep (optional)
                playBeep('success');
            } else {
                document.getElementById('clockOutScanResult').innerHTML = 
                    `<span class="text-danger"><i class="fas fa-times-circle"></i> ${data.message}</span>`;
                playBeep('error');
            }
            
            // Clear message after 3 seconds
            setTimeout(() => {
                document.getElementById('clockOutScanResult').innerHTML = '';
            }, 3000);
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('clockOutScanResult').innerHTML = 
                '<span class="text-danger"><i class="fas fa-times-circle"></i> Scan failed. Please try again.</span>';
        });
    }
    
    // Stop scanner when modal closes
    $('#manageCompletedModal').on('hidden.bs.modal', function() {
        stopCompletedSessionScanner();
    });
    
    // Simple beep function
    function playBeep(type) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = type === 'success' ? 800 : 300;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.3;
            
            oscillator.start();
            setTimeout(() => oscillator.stop(), 150);
        } catch (e) {
            // Audio not supported, ignore
        }
    }
    
    // Save completed session logbook
    document.getElementById('saveCompletedLogbookBtn')?.addEventListener('click', function() {
        const form = document.getElementById('completedLogbookForm');
        const formData = new FormData(form);
        formData.append('session_id', managingSessionId);
        
        // Debug: Log what we're sending
        console.log('Saving logbook for session:', managingSessionId);
        
        fetch(`{{ route('admin.class-session.update-logbook') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Server error: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Logbook saved successfully',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to save logbook', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            Swal.fire('Error', err.message || 'Failed to save logbook', 'error');
        });
    });

    // ============================================
    // STUDENT ACTIVITY SECTION
    // ============================================
    
    // Switch activity tabs
    window.switchActivityTab = function(tab) {
        // Update tab buttons
        document.querySelectorAll('.activity-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`.activity-tab[data-tab="${tab}"]`).classList.add('active');
        
        // Show/hide content
        document.getElementById('chatTab').style.display = tab === 'chat' ? 'block' : 'none';
        document.getElementById('questionsTab').style.display = tab === 'questions' ? 'block' : 'none';
        document.getElementById('alertsTab').style.display = tab === 'alerts' ? 'block' : 'none';
    };
    
    // Load student activity
    function loadStudentActivity() {
        if (!activeSessionId) return;
        
        fetch(`{{ url('admin/class-session') }}/${activeSessionId}/activity`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update counts
                document.getElementById('chatCount').textContent = data.messages?.length || 0;
                document.getElementById('questionsCount').textContent = data.questions?.length || 0;
                document.getElementById('alertsCount').textContent = data.alerts?.length || 0;
                
                // Render messages
                renderChatMessages(data.messages || []);
                renderQuestions(data.questions || []);
                renderAlerts(data.alerts || []);
            }
        })
        .catch(error => console.error('Load activity error:', error));
    }
    
    function renderChatMessages(messages) {
        const container = document.getElementById('chatMessagesList');
        if (messages.length === 0) {
            container.innerHTML = `<div class="no-activity"><i class="fas fa-comments"></i><p>No messages yet. Start the conversation!</p></div>`;
            return;
        }
        
        container.innerHTML = messages.map(msg => `
            <div class="chat-message-lecturer ${msg.is_lecturer ? 'from-lecturer' : ''}">
                <div class="chat-avatar-sm ${msg.is_lecturer ? 'lecturer' : ''}">${msg.sender_name?.charAt(0).toUpperCase() || 'S'}</div>
                <div class="chat-content">
                    <div class="chat-sender-name">
                        ${msg.sender_name || 'Student'}
                        ${msg.is_lecturer ? '<span class="badge badge-primary ml-1">Lecturer</span>' : ''}
                        ${msg.program && !msg.is_lecturer ? `<span class="badge badge-info ml-1">${msg.program}</span>` : ''}
                    </div>
                    <div class="chat-text">${msg.message}</div>
                    <div class="chat-time">${msg.formatted_time}</div>
                </div>
            </div>
        `).join('');
    }
    
    function renderQuestions(questions) {
        const container = document.getElementById('questionsList');
        if (questions.length === 0) {
            container.innerHTML = `<div class="no-activity"><i class="fas fa-question-circle"></i><p>No questions yet</p></div>`;
            return;
        }
        
        container.innerHTML = questions.map(q => `
            <div class="question-item ${q.is_answered ? 'answered' : ''}" onclick="${!q.is_answered ? `selectQuestionToAnswer(${q.id}, '${q.question.replace(/'/g, "\\'")}')` : ''}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${q.is_anonymous ? 'Anonymous' : q.student_name}</strong>
                        ${q.program ? `<span class="badge badge-info ml-1">${q.program}</span>` : ''}
                        <span class="badge question-badge ${q.is_answered ? 'badge-success' : 'badge-warning'} ml-2">
                            ${q.is_answered ? 'Answered' : 'Click to Answer'}
                        </span>
                    </div>
                    <small class="text-muted">${q.formatted_time}</small>
                </div>
                <p class="mb-1 mt-2">${q.question}</p>
                ${q.is_answered && q.answer ? `<div class="alert alert-success py-1 px-2 mt-2 mb-0"><small><strong>Answer:</strong> ${q.answer}</small></div>` : ''}
            </div>
        `).join('');
    }
    
    function renderAlerts(alerts) {
        const container = document.getElementById('alertsList');
        if (alerts.length === 0) {
            container.innerHTML = `<div class="no-activity"><i class="fas fa-bell"></i><p>No alerts yet</p></div>`;
            return;
        }
        
        const icons = {
            'exam': 'fa-clipboard-list',
            'assignment': 'fa-tasks',
            'deadline': 'fa-calendar-times',
            'reminder': 'fa-bell',
            'general': 'fa-info-circle'
        };
        
        container.innerHTML = alerts.map(a => `
            <div class="alert-item ${a.alert_type}">
                <div class="alert-icon">
                    <i class="fas ${icons[a.alert_type] || 'fa-bell'}"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <strong>${a.title}</strong>
                        <span class="badge badge-secondary">${a.upvotes || 0} upvotes</span>
                    </div>
                    <p class="mb-1 small">${a.content || ''}</p>
                    ${a.due_date ? `<small class="text-muted"><i class="fas fa-clock mr-1"></i>Due: ${a.due_date} ${a.due_time || ''}</small>` : ''}
                    <div class="small text-muted mt-1">By: ${a.student_name}${a.program ? ` <span class="badge badge-info">${a.program}</span>` : ''}</div>
                </div>
            </div>
        `).join('');
    }
    
    // Poll for new activity every 5 seconds
    setInterval(loadStudentActivity, 5000);
    
    // ============================================
    // LECTURER COMMUNICATION
    // ============================================
    
    let selectedQuestionId = null;
    
    // Lecturer send message
    document.getElementById('lecturerSendBtn')?.addEventListener('click', sendLecturerMessage);
    document.getElementById('lecturerChatInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendLecturerMessage();
    });
    
    function sendLecturerMessage() {
        const input = document.getElementById('lecturerChatInput');
        const message = input.value.trim();
        
        if (!message || !activeSessionId) return;
        
        fetch('{{ route("admin.class-session.send-message") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                input.value = '';
                loadStudentActivity(); // Refresh to show new message
            } else {
                alert(data.message || 'Failed to send message');
            }
        })
        .catch(error => {
            console.error('Send message error:', error);
            alert('Failed to send message');
        });
    }
    
    // Select question to answer
    window.selectQuestionToAnswer = function(questionId, questionText) {
        selectedQuestionId = questionId;
        document.getElementById('answerSection').style.display = 'block';
        document.getElementById('answeringQuestion').innerHTML = `<strong>Answering:</strong> ${questionText}`;
        document.getElementById('lecturerAnswerInput').focus();
    };
    
    window.cancelAnswer = function() {
        selectedQuestionId = null;
        document.getElementById('answerSection').style.display = 'none';
        document.getElementById('answeringQuestion').innerHTML = '';
        document.getElementById('lecturerAnswerInput').value = '';
    };
    
    // Submit answer
    document.getElementById('submitAnswerBtn')?.addEventListener('click', submitAnswer);
    document.getElementById('lecturerAnswerInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') submitAnswer();
    });
    
    function submitAnswer() {
        const answer = document.getElementById('lecturerAnswerInput').value.trim();
        
        if (!answer || !selectedQuestionId || !activeSessionId) return;
        
        fetch('{{ route("admin.class-session.answer-question") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                question_id: selectedQuestionId,
                answer: answer
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                cancelAnswer();
                loadStudentActivity(); // Refresh to show answered
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Answer submitted!',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                alert(data.message || 'Failed to submit answer');
            }
        })
        .catch(error => {
            console.error('Submit answer error:', error);
            alert('Failed to submit answer');
        });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, activeSessionId:', activeSessionId);
        console.log('Html5Qrcode available:', typeof Html5Qrcode !== 'undefined');
        
        if (activeSessionId) {
            // Show scanner controls
            document.getElementById('scannerControls').style.display = 'block';
            
            // Small delay to ensure library is fully loaded
            setTimeout(function() {
                if (typeof Html5Qrcode === 'undefined') {
                    console.error('Html5Qrcode library not loaded!');
                    document.getElementById('scannerStatus').textContent = 'QR library not loaded - refresh page';
                    return;
                }
                initScanner();
            }, 500);
            
            loadStudentList();
            loadStudentActivity(); // Load activity on page load
        }
    });
})();
</script>
@endpush
