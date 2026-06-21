@extends('student.layouts.master')
@section('title', $title)

@section('page_css')
<style>
/* ========== LIVE ROOM STYLES ========== */
.live-room-container {
    display: flex;
    gap: 20px;
    min-height: calc(100vh - 200px);
}

/* Main Content Area */
.main-content-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 15px;
    min-height: 0;
}

/* Sidebar */
.sidebar-area {
    width: 350px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

/* Cards */
.live-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.live-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.live-card-header h5 {
    margin: 0;
    font-weight: 600;
}

.live-card-body {
    padding: 20px;
}

/* Session Info Header */
.session-info-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 15px;
}

.session-info-header.in-progress {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
}

.session-info-header h4 {
    margin: 0 0 10px 0;
    font-weight: 700;
}

.session-info-header .live-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.2);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
}

.session-info-header .live-badge .dot {
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

.session-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}

.meta-item i {
    opacity: 0.8;
}

/* Attendance Status Card */
.attendance-status-card {
    padding: 15px;
    border-radius: 10px;
    text-align: center;
}

.attendance-status-card.clocked-in {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border: 2px solid #28a745;
}

.attendance-status-card.not-clocked {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
    border: 2px solid #ffc107;
}

/* Logbook Delegate Card */
.logbook-delegate-card {
    background: linear-gradient(135deg, #e8f4fd 0%, #d6eaf8 100%);
    border: 2px solid #3498db;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
}

.logbook-delegate-card .delegate-badge {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.logbook-delegate-card h6 {
    color: #2c3e50;
    font-weight: 700;
}

.logbook-delegate-card .form-group label {
    font-weight: 600;
    color: #34495e;
    font-size: 0.9rem;
}

.logbook-delegate-card .form-control {
    border: 1px solid #bdc3c7;
    border-radius: 8px;
}

.logbook-delegate-card .form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
}

.logbook-delegate-card .btn-success {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    padding: 12px;
}

/* Attendance Delegate Card */
.attendance-delegate-card {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    color: white;
}

.attendance-delegate-card .delegate-badge {
    background: rgba(52, 152, 219, 0.3);
    border: 1px solid rgba(52, 152, 219, 0.5);
    border-radius: 20px;
    padding: 6px 15px;
    display: inline-flex;
    align-items: center;
    margin-bottom: 15px;
    font-size: 0.85rem;
}

.attendance-delegate-card h6 {
    color: #fff;
    font-weight: 600;
    margin-bottom: 10px;
}

.scanner-wrapper {
    background: #1a252f;
    border-radius: 12px;
    padding: 10px;
    min-height: 250px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.scanner-wrapper #delegateReader {
    max-width: 100%;
}

.scan-result-box {
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    animation: fadeIn 0.3s ease;
}

.scan-result-box.success {
    background: rgba(39, 174, 96, 0.3);
    border: 1px solid rgba(39, 174, 96, 0.5);
}

.scan-result-box.error {
    background: rgba(231, 76, 60, 0.3);
    border: 1px solid rgba(231, 76, 60, 0.5);
}

.scan-result-box.warning {
    background: rgba(241, 196, 15, 0.3);
    border: 1px solid rgba(241, 196, 15, 0.5);
}

.scan-result-box .student-name {
    font-size: 1.3rem;
    font-weight: 600;
}

.scan-result-box .matricule {
    font-size: 0.9rem;
    opacity: 0.8;
}

.recent-scans-list {
    max-height: 150px;
    overflow-y: auto;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 10px;
}

.recent-scan-item {
    padding: 8px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-scan-item:last-child {
    border-bottom: none;
}

.recent-scan-item .scan-name {
    font-weight: 500;
}

.recent-scan-item .scan-time {
    font-size: 0.8rem;
    opacity: 0.7;
}

.recent-scan-item .scan-status {
    font-size: 0.75rem;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Chat Container */
.chat-container {
    display: flex;
    flex-direction: column;
    height: 450px;
    max-height: 450px;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 15px;
    min-height: 0; /* Important for flex overflow to work */
}

.chat-message {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.chat-message.own {
    flex-direction: row-reverse;
}

.chat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.chat-bubble {
    max-width: 70%;
    background: white;
    padding: 10px 15px;
    border-radius: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.chat-message.own .chat-bubble {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.chat-sender {
    font-size: 0.75rem;
    font-weight: 600;
    color: #667eea;
    margin-bottom: 3px;
}

.chat-message.own .chat-sender {
    color: rgba(255,255,255,0.8);
    text-align: right;
}

.chat-text {
    font-size: 0.9rem;
    line-height: 1.4;
    word-wrap: break-word;
}

.chat-time {
    font-size: 0.7rem;
    color: #999;
    margin-top: 5px;
}

.chat-message.own .chat-time {
    color: rgba(255,255,255,0.7);
    text-align: right;
}

.chat-message.announcement {
    background: #fff3cd;
    padding: 10px 15px;
    border-radius: 10px;
    border-left: 4px solid #ffc107;
    margin-bottom: 15px;
}

.chat-message.announcement .chat-bubble {
    background: transparent;
    box-shadow: none;
    padding: 0;
    max-width: 100%;
}

/* Chat Input */
.chat-input-container {
    display: flex;
    gap: 10px;
    flex-shrink: 0; /* Prevent shrinking */
    padding-top: 10px;
    background: white;
    position: sticky;
    bottom: 0;
}

.chat-input-container input {
    flex: 1;
    padding: 12px 20px;
    border: 2px solid #e9ecef;
    border-radius: 25px;
    outline: none;
    transition: all 0.3s ease;
    min-width: 0; /* Allow input to shrink properly */
}

.chat-input-container input:focus {
    border-color: #667eea;
}

.chat-input-container button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
}

.chat-input-container button:hover {
    transform: scale(1.05);
}

.chat-disabled {
    text-align: center;
    padding: 30px;
    color: #999;
    background: #f8f9fa;
    border-radius: 10px;
}

/* Notes Area */
.notes-area {
    flex: 1;
}

.notes-area textarea {
    width: 100%;
    height: 200px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    resize: none;
    outline: none;
    transition: all 0.3s ease;
}

.notes-area textarea:focus {
    border-color: #667eea;
}

.notes-saved-indicator {
    font-size: 0.85rem;
    color: #28a745;
    margin-top: 10px;
    display: none;
}

.notes-saved-indicator.show {
    display: block;
    animation: fadeIn 0.3s ease;
}

/* Alerts Section */
.alerts-list {
    max-height: 250px;
    overflow-y: auto;
}

.alert-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 10px;
    border-left: 4px solid #667eea;
}

.alert-item.reminder { border-left-color: #17a2b8; }
.alert-item.assignment { border-left-color: #007bff; }
.alert-item.exam { border-left-color: #dc3545; }
.alert-item.important { border-left-color: #ffc107; }
.alert-item.deadline { border-left-color: #343a40; }

.alert-item-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 5px;
}

.alert-item-title {
    font-weight: 600;
    font-size: 0.9rem;
}

.alert-item-meta {
    font-size: 0.75rem;
    color: #666;
}

.alert-upvote-btn {
    background: none;
    border: 1px solid #ddd;
    border-radius: 15px;
    padding: 3px 10px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.alert-upvote-btn:hover, .alert-upvote-btn.upvoted {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.alert-verified {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #d4edda;
    color: #155724;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
}

/* Participants */
.participants-count {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    margin-bottom: 15px;
}

.participants-count .number {
    font-size: 2rem;
    font-weight: 700;
}

/* Topic Card */
.topic-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 15px;
}

.topic-card h6 {
    margin: 0 0 10px 0;
    opacity: 0.9;
}

.topic-card p {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Tabs */
.live-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 10px;
}

.live-tab {
    padding: 8px 15px;
    border: none;
    background: none;
    color: #666;
    font-weight: 500;
    cursor: pointer;
    border-radius: 20px;
    transition: all 0.3s ease;
}

.live-tab:hover {
    background: #f8f9fa;
}

.live-tab.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Responsive */
@media (max-width: 992px) {
    .live-room-container {
        flex-direction: column;
        height: auto;
    }
    
    .sidebar-area {
        width: 100%;
    }
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Session Info Header -->
        <div class="session-info-header {{ $session->status == 'in_progress' ? 'in-progress' : '' }}">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        @if($session->status == 'in_progress')
                        <span class="live-badge">
                            <span class="dot"></span> LIVE
                        </span>
                        @endif
                        <h4>{{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown Subject' }}</h4>
                    </div>
                    <div class="session-meta-row">
                        <span class="meta-item">
                            <i class="fas fa-user-tie"></i> {{ $session->teacher->name ?? 'Unknown' }}
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-clock"></i> 
                            {{ $session->actual_start_time ? $session->actual_start_time->format('H:i') : 'N/A' }} - 
                            {{ $session->scheduled_end_time ? $session->scheduled_end_time->format('H:i') : 'N/A' }}
                        </span>
                        @if($session->section)
                        <span class="meta-item">
                            <i class="fas fa-users"></i> {{ $session->section->title }}
                        </span>
                        @endif
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('student.class-hub.index') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Classes
                    </a>
                </div>
            </div>
        </div>

        <div class="live-room-container">
            <!-- Main Content -->
            <div class="main-content-area">
                <!-- Attendance Status -->
                <div class="attendance-status-card {{ $attendance && $attendance->clock_in_time ? 'clocked-in' : 'not-clocked' }}">
                    @if($attendance && $attendance->clock_in_time)
                        <h5 class="mb-1"><i class="fas fa-check-circle text-success mr-2"></i>You're Clocked In!</h5>
                        <p class="mb-0">Clocked in at {{ $attendance->clock_in_time->format('H:i') }}
                            @if($attendance->is_late) <span class="badge badge-warning">Late</span> @endif
                        </p>
                    @else
                        <h5 class="mb-1"><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Not Clocked In</h5>
                        <p class="mb-0">Please scan your QR code at the kiosk to register your attendance!</p>
                    @endif
                </div>

                @if($isLogbookDelegate ?? false)
                <!-- Logbook Delegate Section -->
                <div class="logbook-delegate-card">
                    <div class="delegate-badge">
                        <i class="fas fa-user-edit mr-2"></i>
                        <span>You are the Class Representative</span>
                    </div>
                    <h6 class="mb-3"><i class="fas fa-book mr-2"></i>Fill Class Logbook</h6>
                    <p class="text-muted small mb-3">The lecturer has delegated the logbook to you. Please fill in the class details.</p>
                    
                    <form id="studentLogbookForm">
                        <div class="form-group">
                            <label><i class="fas fa-bookmark mr-1"></i> Topic Covered <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="topic_covered" id="delegateTopic" 
                                   value="{{ $session->topic_covered ?? '' }}" placeholder="What topic was covered today?" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-list-ul mr-1"></i> Content Summary</label>
                            <textarea class="form-control" name="content_summary" id="delegateContentSummary" rows="3" 
                                      placeholder="Brief summary of what was taught...">{{ $session->learning_objectives ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tasks mr-1"></i> Assignments Given</label>
                            <input type="text" class="form-control" name="assignments_given" id="delegateAssignments" 
                                   value="{{ $session->assignments_given ?? '' }}" placeholder="Any homework or assignments?">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note mr-1"></i> Remarks</label>
                            <textarea class="form-control" name="remarks" id="delegateRemarks" rows="2" 
                                      placeholder="Any additional notes...">{{ $session->remarks ?? '' }}</textarea>
                        </div>
                        <button type="button" class="btn btn-success btn-block" id="saveStudentLogbookBtn">
                            <i class="fas fa-save mr-2"></i> Save Logbook
                        </button>
                    </form>
                </div>
                @endif

                @if($isAttendanceDelegate ?? false)
                <!-- Attendance Delegate Section - QR Scanner -->
                <div class="attendance-delegate-card">
                    <div class="delegate-badge">
                        <i class="fas fa-clipboard-check mr-2"></i>
                        <span>Attendance Delegated to You</span>
                    </div>
                    <h6 class="mb-3"><i class="fas fa-qrcode mr-2"></i>Scan Student QR Codes</h6>
                    <p class="text-muted small mb-3">Students should scan their ID cards here to clock in.</p>
                    
                    <div class="attendance-scanner-container">
                        <div class="attendance-summary mb-3 text-center">
                            <span class="badge badge-lg badge-success px-3 py-2" style="font-size: 1.1rem;">
                                <i class="fas fa-user-check mr-1"></i> <span id="delegatePresentCount">{{ $presentCount }}</span> / {{ $totalEnrolled }} Present
                            </span>
                        </div>
                        
                        <!-- Scanner Area -->
                        <div class="scanner-wrapper mb-3">
                            <div id="delegateReader" style="width: 100%; border-radius: 10px; overflow: hidden;"></div>
                        </div>
                        
                        <div class="scanner-controls text-center mb-3">
                            <button class="btn btn-primary btn-lg" id="startDelegateScannerBtn">
                                <i class="fas fa-camera mr-2"></i> Start Scanner
                            </button>
                            <button class="btn btn-secondary btn-lg" id="stopDelegateScannerBtn" style="display: none;">
                                <i class="fas fa-stop mr-2"></i> Stop Scanner
                            </button>
                        </div>
                        
                        <!-- Last Scan Result -->
                        <div id="delegateScanResult" class="scan-result-box" style="display: none;">
                            <!-- Will be populated by JS -->
                        </div>
                        
                        <!-- Recent Scans List -->
                        <div class="recent-scans mt-3">
                            <h6 class="mb-2"><i class="fas fa-history mr-1"></i> Recent Scans</h6>
                            <div id="recentScansList" class="recent-scans-list">
                                <p class="text-muted small text-center">No scans yet</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Topic Covered -->
                @if($session->topic_covered || $session->logbook)
                <div class="topic-card">
                    <h6><i class="fas fa-book-open mr-2"></i>Today's Topic</h6>
                    <p>{{ $session->topic_covered ?? $session->logbook->topic_covered ?? 'Topic not yet specified' }}</p>
                </div>
                @endif

                <!-- Tabs for Chat/Notes/Questions -->
                <div class="live-card" style="flex: 1; display: flex; flex-direction: column;">
                    <div class="live-card-body" style="flex: 1; display: flex; flex-direction: column;">
                        <div class="live-tabs">
                            <button class="live-tab active" data-tab="chat">
                                <i class="fas fa-comments mr-1"></i> Discussion
                            </button>
                            <button class="live-tab" data-tab="notes">
                                <i class="fas fa-sticky-note mr-1"></i> My Notes
                            </button>
                            <button class="live-tab" data-tab="questions">
                                <i class="fas fa-question-circle mr-1"></i> Questions
                            </button>
                        </div>

                        <!-- Chat Tab -->
                        <div class="tab-content active" id="tab-chat">
                            @if($session->chat_enabled ?? true)
                            <div class="chat-container">
                                <div class="chat-messages" id="chatMessages">
                                    @foreach($messages as $msg)
                                    <div class="chat-message {{ $msg->student_id == $student->id ? 'own' : '' }} {{ $msg->is_announcement ? 'announcement' : '' }}">
                                        <div class="chat-avatar">
                                            {{ strtoupper(substr($msg->sender_name, 0, 1)) }}
                                        </div>
                                        <div class="chat-bubble">
                                            <div class="chat-sender">
                                                {{ $msg->sender_name }}
                                                @if($msg->sender_type == 'lecturer')
                                                <span class="badge badge-primary badge-sm">Lecturer</span>
                                                @endif
                                            </div>
                                            <div class="chat-text">{{ $msg->message }}</div>
                                            <div class="chat-time">{{ $msg->created_at->format('H:i') }}</div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="chat-input-container">
                                    <input type="text" id="chatInput" placeholder="Type your message..." maxlength="1000">
                                    <button type="button" id="sendMessageBtn">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                            @else
                            <div class="chat-disabled">
                                <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                <p>Chat has been disabled for this class session by the lecturer.</p>
                            </div>
                            @endif
                        </div>

                        <!-- Notes Tab -->
                        <div class="tab-content" id="tab-notes">
                            <div class="notes-area">
                                <input type="text" class="form-control mb-3" id="noteTitle" 
                                       placeholder="Note title (optional)" value="{{ $note->title ?? '' }}">
                                <textarea id="noteContent" placeholder="Start taking notes... (auto-saves every 5 seconds)">{{ $note->content ?? '' }}</textarea>
                                <div class="notes-saved-indicator" id="noteSavedIndicator">
                                    <i class="fas fa-check-circle mr-1"></i> Notes saved
                                </div>
                            </div>
                        </div>

                        <!-- Questions Tab -->
                        <div class="tab-content" id="tab-questions">
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="questionInput" 
                                           placeholder="Ask a question to the lecturer...">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" id="submitQuestionBtn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="questionAnonymous">
                                    <label class="custom-control-label" for="questionAnonymous">Ask anonymously</label>
                                </div>
                            </div>
                            <div class="questions-list">
                                @forelse($session->questions as $q)
                                <div class="alert-item">
                                    <div class="alert-item-header">
                                        <span class="alert-item-title">{{ $q->question }}</span>
                                        <span class="badge {{ $q->status == 'answered' ? 'badge-success' : 'badge-secondary' }}">
                                            {{ ucfirst($q->status) }}
                                        </span>
                                    </div>
                                    <div class="alert-item-meta">
                                        Asked by {{ $q->display_name }} • {{ $q->created_at->diffForHumans() }}
                                    </div>
                                    @if($q->answer)
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <strong>Answer:</strong> {{ $q->answer }}
                                    </div>
                                    @endif
                                </div>
                                @empty
                                <p class="text-muted text-center">No questions yet. Be the first to ask!</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar-area">
                <!-- Participants -->
                <div class="participants-count">
                    <div class="number">{{ $presentCount }}</div>
                    <div>
                        <strong>Students Present</strong><br>
                        <small>out of {{ $totalEnrolled }} enrolled</small>
                    </div>
                </div>

                <!-- Alerts Section -->
                <div class="live-card" style="flex: 1;">
                    <div class="live-card-header">
                        <h5><i class="fas fa-bell mr-2"></i>Class Alerts</h5>
                        <button class="btn btn-sm btn-light" id="openAddAlertBtn" type="button">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="live-card-body">
                        <div class="alerts-list">
                            @forelse($session->alerts as $alert)
                            <div class="alert-item {{ $alert->alert_type }}">
                                <div class="alert-item-header">
                                    <div>
                                        <span class="alert-item-title">
                                            <i class="{{ $alert->type_info['icon'] }} mr-1"></i>
                                            {{ $alert->title }}
                                        </span>
                                        @if($alert->is_verified_by_lecturer)
                                        <span class="alert-verified">
                                            <i class="fas fa-check"></i> Verified
                                        </span>
                                        @endif
                                    </div>
                                    <button class="alert-upvote-btn {{ $alert->hasUpvotedBy($student->id) ? 'upvoted' : '' }}"
                                            data-alert-id="{{ $alert->id }}">
                                        <i class="fas fa-thumbs-up"></i> {{ $alert->upvotes }}
                                    </button>
                                </div>
                                @if($alert->content)
                                <p class="mb-1 small">{{ $alert->content }}</p>
                                @endif
                                <div class="alert-item-meta">
                                    @if($alert->due_date)
                                    <span class="text-danger">
                                        <i class="fas fa-calendar mr-1"></i>Due: {{ $alert->due_date->format('M d, Y') }}
                                    </span>
                                    @endif
                                    <span class="ml-2">by {{ $alert->student->first_name ?? 'Unknown' }}</span>
                                </div>
                            </div>
                            @empty
                            <p class="text-muted text-center small">No alerts yet. Add one if the lecturer mentions something important!</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Alert Modal -->
<div class="modal fade" id="addAlertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bell mr-2"></i>Add Alert/Reminder</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="alertForm">
                    <div class="form-group">
                        <label>Alert Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="alert_type" id="alertType" required>
                            @foreach($alertTypes as $key => $type)
                            <option value="{{ $key }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="alertTitle" 
                               placeholder="e.g., Assignment Due Friday" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label>Details</label>
                        <textarea class="form-control" name="content" id="alertContent" rows="3"
                                  placeholder="Additional details..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Due Date (optional)</label>
                                <input type="date" class="form-control" name="due_date" id="alertDueDate">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Due Time (optional)</label>
                                <input type="time" class="form-control" name="due_time" id="alertDueTime">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAlertBtn">
                    <i class="fas fa-plus mr-1"></i> Add Alert
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script src="{{ asset('dashboard/js/html5-qrcode.min.js') }}"></script>
<script>
const sessionId = {{ $session->id }};
const studentId = {{ $student->id }};
let lastMessageId = {{ $messages->last()?->id ?? 0 }};
let notesSaveTimer = null;

// Tab switching
document.querySelectorAll('.live-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.live-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// Open Add Alert Modal
document.getElementById('openAddAlertBtn')?.addEventListener('click', function() {
    $('#addAlertModal').modal('show');
});

// ============================================
// STUDENT LOGBOOK DELEGATION
// ============================================
document.getElementById('saveStudentLogbookBtn')?.addEventListener('click', function() {
    const form = document.getElementById('studentLogbookForm');
    const topic = document.getElementById('delegateTopic').value.trim();
    
    if (!topic) {
        Swal.fire('Required', 'Please enter the topic covered', 'warning');
        return;
    }
    
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    
    fetch('{{ route("student.class-hub.save-logbook", $session->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            topic_covered: topic,
            content_summary: document.getElementById('delegateContentSummary').value.trim(),
            assignments_given: document.getElementById('delegateAssignments').value.trim(),
            remarks: document.getElementById('delegateRemarks').value.trim()
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Logbook';
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Logbook Saved!',
                text: 'Thank you for filling the class logbook.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to save logbook', 'error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Logbook';
        console.error('Save logbook error:', error);
        Swal.fire('Error', 'Failed to save logbook', 'error');
    });
});

// ============================================
// STUDENT ATTENDANCE DELEGATION - QR SCANNER
// ============================================
(function() {
    const startBtn = document.getElementById('startDelegateScannerBtn');
    const stopBtn = document.getElementById('stopDelegateScannerBtn');
    const readerEl = document.getElementById('delegateReader');
    const resultBox = document.getElementById('delegateScanResult');
    const recentList = document.getElementById('recentScansList');
    const presentCountEl = document.getElementById('delegatePresentCount');
    
    if (!startBtn || !readerEl) return;
    
    let html5QrCode = null;
    let isScanning = false;
    let lastScannedId = null;
    let scanCooldown = false;
    
    function updatePresentCount(newCount) {
        if (presentCountEl && newCount !== undefined) {
            presentCountEl.textContent = newCount;
        }
    }
    
    function showResult(data) {
        if (!resultBox) return;
        
        let statusClass = 'success';
        let icon = 'check-circle';
        
        if (data.status === 'error') {
            statusClass = 'error';
            icon = 'times-circle';
        } else if (data.status === 'already_clocked_in' || data.status === 'warning') {
            statusClass = 'warning';
            icon = 'exclamation-circle';
        }
        
        resultBox.className = 'scan-result-box ' + statusClass;
        resultBox.innerHTML = `
            <i class="fas fa-${icon} fa-2x mb-2"></i>
            <div class="student-name">${data.student || 'Unknown'}</div>
            <div class="matricule">${data.matricule || ''} ${data.program ? '(' + data.program + ')' : ''}</div>
            <div class="mt-2">${data.message}</div>
            ${data.is_late ? '<span class="badge badge-warning mt-1">Late</span>' : ''}
        `;
        resultBox.style.display = 'block';
        
        // Auto hide after 4 seconds
        setTimeout(() => {
            resultBox.style.display = 'none';
        }, 4000);
    }
    
    function addToRecentScans(data) {
        if (!recentList) return;
        
        // Remove "no scans" message if exists
        const noScansMsg = recentList.querySelector('p');
        if (noScansMsg) noScansMsg.remove();
        
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        let statusBadge = '<span class="badge badge-success scan-status">OK</span>';
        if (data.status === 'error') {
            statusBadge = '<span class="badge badge-danger scan-status">Error</span>';
        } else if (data.status === 'already_clocked_in') {
            statusBadge = '<span class="badge badge-warning scan-status">Already In</span>';
        } else if (data.is_late) {
            statusBadge = '<span class="badge badge-warning scan-status">Late</span>';
        }
        
        const item = document.createElement('div');
        item.className = 'recent-scan-item';
        item.innerHTML = `
            <div>
                <span class="scan-name">${data.student || data.matricule || 'Unknown'}</span>
                <span class="scan-time ml-2">${timeStr}</span>
            </div>
            ${statusBadge}
        `;
        
        recentList.insertBefore(item, recentList.firstChild);
        
        // Keep only last 10 items
        while (recentList.children.length > 10) {
            recentList.removeChild(recentList.lastChild);
        }
    }
    
    function processScan(scannedData) {
        if (scanCooldown) return;
        
        // Prevent duplicate scans within 2 seconds
        if (scannedData === lastScannedId) return;
        lastScannedId = scannedData;
        scanCooldown = true;
        
        setTimeout(() => {
            scanCooldown = false;
            lastScannedId = null;
        }, 2000);
        
        // Play scan sound
        try {
            const audio = new Audio('/sounds/beep.mp3');
            audio.volume = 0.3;
            audio.play().catch(() => {});
        } catch(e) {}
        
        fetch('{{ route("student.class-hub.mark-attendance", $session->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                student_id: scannedData
            })
        })
        .then(response => response.json())
        .then(data => {
            showResult(data);
            addToRecentScans(data);
            
            // Update count on successful clock in
            if (data.status === 'success') {
                const currentCount = parseInt(presentCountEl?.textContent || '0');
                updatePresentCount(currentCount + 1);
            }
        })
        .catch(error => {
            console.error('Scan error:', error);
            showResult({
                status: 'error',
                message: 'Network error. Please try again.',
                student: ''
            });
        });
    }
    
    startBtn.addEventListener('click', function() {
        if (isScanning) return;
        
        // Check if Html5Qrcode library is loaded
        if (typeof Html5Qrcode === 'undefined') {
            console.error('Html5Qrcode library not loaded!');
            Swal.fire('Error', 'QR Scanner library not loaded. Please refresh the page.', 'error');
            return;
        }
        
        html5QrCode = new Html5Qrcode("delegateReader");
        
        html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            },
            (decodedText) => {
                processScan(decodedText);
            },
            (errorMessage) => {
                // Ignore scan errors
            }
        ).then(() => {
            isScanning = true;
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
        }).catch(err => {
            console.error('Camera error:', err);
            Swal.fire('Camera Error', 'Could not access camera. Please check permissions.', 'error');
        });
    });
    
    stopBtn.addEventListener('click', function() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                startBtn.style.display = 'inline-block';
                stopBtn.style.display = 'none';
                html5QrCode = null;
            });
        }
    });
})();

// Send message
document.getElementById('sendMessageBtn')?.addEventListener('click', sendMessage);
document.getElementById('chatInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendMessage();
});

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    fetch('{{ route("student.class-hub.send-message", $session->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            session_id: sessionId,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            input.value = '';
            appendMessage(data.message, true);
            scrollChatToBottom();
        }
    })
    .catch(error => console.error('Send message error:', error));
}

function appendMessage(msg, isOwn = false) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'chat-message' + (isOwn ? ' own' : '');
    div.innerHTML = `
        <div class="chat-avatar">${msg.sender_name.charAt(0).toUpperCase()}</div>
        <div class="chat-bubble">
            <div class="chat-sender">${msg.sender_name}</div>
            <div class="chat-text">${msg.message}</div>
            <div class="chat-time">${msg.formatted_time || new Date().toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit'})}</div>
        </div>
    `;
    container.appendChild(div);
    lastMessageId = msg.id;
}

function scrollChatToBottom() {
    const container = document.getElementById('chatMessages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Poll for new messages every 3 seconds
setInterval(function() {
    fetch(`{{ route('student.class-hub.get-messages', $session->id) }}?last_id=${lastMessageId}`)
    .then(response => response.json())
    .then(data => {
        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(msg => {
                if (msg.student_id != studentId) {
                    appendMessage(msg, false);
                }
            });
            scrollChatToBottom();
        }
    })
    .catch(error => console.error('Poll messages error:', error));
}, 3000);

// Auto-save notes
document.getElementById('noteContent')?.addEventListener('input', function() {
    clearTimeout(notesSaveTimer);
    notesSaveTimer = setTimeout(saveNotes, 5000);
});

document.getElementById('noteTitle')?.addEventListener('input', function() {
    clearTimeout(notesSaveTimer);
    notesSaveTimer = setTimeout(saveNotes, 5000);
});

function saveNotes() {
    const title = document.getElementById('noteTitle')?.value || '';
    const content = document.getElementById('noteContent')?.value || '';
    
    fetch('{{ route("student.class-hub.save-note", $session->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            session_id: sessionId,
            title: title,
            content: content
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const indicator = document.getElementById('noteSavedIndicator');
            indicator.classList.add('show');
            setTimeout(() => indicator.classList.remove('show'), 3000);
        }
    })
    .catch(error => console.error('Save notes error:', error));
}

// Submit question
document.getElementById('submitQuestionBtn')?.addEventListener('click', function() {
    const question = document.getElementById('questionInput').value.trim();
    const isAnonymous = document.getElementById('questionAnonymous').checked;
    
    if (!question) return;
    
    fetch('{{ route("student.class-hub.submit-question", $session->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            session_id: sessionId,
            question: question,
            is_anonymous: isAnonymous
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('questionInput').value = '';
            location.reload(); // Simple refresh for now
        }
    })
    .catch(error => console.error('Submit question error:', error));
});

// Add alert
document.getElementById('saveAlertBtn')?.addEventListener('click', function() {
    const formData = {
        session_id: sessionId,
        alert_type: document.getElementById('alertType').value,
        title: document.getElementById('alertTitle').value,
        content: document.getElementById('alertContent').value,
        due_date: document.getElementById('alertDueDate').value || null,
        due_time: document.getElementById('alertDueTime').value || null
    };
    
    if (!formData.title) {
        alert('Please enter a title');
        return;
    }
    
    fetch('{{ route("student.class-hub.create-alert", $session->id) }}', {
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
            $('#addAlertModal').modal('hide');
            location.reload();
        } else {
            alert(data.message || 'Failed to add alert');
        }
    })
    .catch(error => console.error('Create alert error:', error));
});

// Upvote alert
document.querySelectorAll('.alert-upvote-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const alertId = this.dataset.alertId;
        
        fetch(`{{ url('student/class-hub/alert') }}/${alertId}/upvote`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                this.classList.toggle('upvoted', data.upvoted);
                this.innerHTML = `<i class="fas fa-thumbs-up"></i> ${data.upvotes}`;
            }
        })
        .catch(error => console.error('Upvote error:', error));
    });
});

// Scroll chat to bottom on load
document.addEventListener('DOMContentLoaded', scrollChatToBottom);
</script>
@endsection
