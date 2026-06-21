@extends('student.layouts.master')
@section('title', $title)

@section('page_css')
<link rel="stylesheet" href="{{ asset('dashboard/plugins/fullcalendar/css/fullcalendar.min.css') }}">
<style>
    /* ========== ANIMATIONS ========== */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
        40% { transform: translateY(-10px); }
        60% { transform: translateY(-5px); }
    }

    /* ========== DASHBOARD STYLES ========== */
    .dashboard-card {
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        animation: fadeInUp 0.6s ease;
        border: none;
        overflow: hidden;
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-card {
        position: relative;
        padding: 25px;
        border-radius: 15px;
        color: #fff;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: rgba(255,255,255,0.1);
        transform: rotate(45deg);
        transition: all 0.5s ease;
    }

    .stat-card:hover::before {
        top: -60%;
        right: -60%;
    }

    .stat-card .icon-wrapper {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        font-size: 30px;
        margin-bottom: 15px;
        animation: pulse 2s infinite;
    }

    .stat-card h3 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-card p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }

    /* Gradient backgrounds for stat cards */
    .gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .gradient-success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .gradient-warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .gradient-info {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .gradient-danger {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .gradient-purple {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    }

    /* Quick action buttons */
    .quick-action-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px 20px;
        border-radius: 10px;
        background: #f8f9fa;
        border: 2px solid transparent;
        transition: all 0.3s ease;
        text-decoration: none;
        color: #333;
        margin-bottom: 10px;
    }

    .quick-action-btn:hover {
        border-color: #667eea;
        background: #fff;
        transform: translateX(5px);
        color: #667eea;
    }

    .quick-action-btn i {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-radius: 8px;
        font-size: 18px;
    }

    /* Progress bars */
    .custom-progress {
        height: 8px;
        border-radius: 10px;
        overflow: hidden;
        background: #e9ecef;
    }

    .custom-progress-bar {
        height: 100%;
        border-radius: 10px;
        transition: width 1s ease;
        position: relative;
        overflow: hidden;
    }

    .custom-progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        background-image: linear-gradient(
            -45deg,
            rgba(255, 255, 255, .2) 25%,
            transparent 25%,
            transparent 50%,
            rgba(255, 255, 255, .2) 50%,
            rgba(255, 255, 255, .2) 75%,
            transparent 75%,
            transparent
        );
        z-index: 1;
        background-size: 50px 50px;
        animation: move 2s linear infinite;
    }

    @keyframes move {
        0% { background-position: 0 0; }
        100% { background-position: 50px 50px; }
    }

    /* List items */
    .list-item {
        padding: 15px;
        border-radius: 10px;
        background: #f8f9fa;
        margin-bottom: 10px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .list-item:hover {
        background: #fff;
        border-left-color: #667eea;
        transform: translateX(5px);
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    }

    /* Section headers */
    .section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .section-header i {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-radius: 8px;
        font-size: 18px;
    }

    .section-header h5 {
        margin: 0;
        font-weight: 600;
    }

    /* Badge styles */
    .custom-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    /* Timeline */
    .timeline-item {
        position: relative;
        padding-left: 40px;
        margin-bottom: 20px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 25px;
        bottom: -20px;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item:last-child::before {
        display: none;
    }

    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #667eea;
        border: 3px solid #fff;
        box-shadow: 0 0 0 2px #667eea;
    }

    /* Animated loader */
    .metric-loader {
        display: inline-block;
        animation: bounce 1s ease-in-out;
    }

    /* Welcome banner */
    .welcome-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px;
        border-radius: 15px;
        color: #fff;
        position: relative;
        overflow: hidden;
        margin-bottom: 30px;
        animation: fadeIn 0.6s ease;
    }

    .welcome-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: rgba(255,255,255,0.05);
        transform: rotate(45deg);
    }

    .welcome-banner h2 {
        font-size: 2rem;
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
    }

    .welcome-banner p {
        margin: 0;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    /* Responsive animations */
    .fade-in-up { animation: fadeInUp 0.6s ease; }
    .fade-in-left { animation: slideInLeft 0.6s ease; }
    .fade-in-right { animation: slideInRight 0.6s ease; }
    
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
    .delay-5 { animation-delay: 0.5s; }

    /* Card icon backgrounds */
    .card-icon-bg {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 24px;
        margin-right: 15px;
    }

    .bg-primary-light {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
    }

    .bg-success-light {
        background: rgba(17, 153, 142, 0.1);
        color: #11998e;
    }

    .bg-warning-light {
        background: rgba(240, 147, 251, 0.1);
        color: #f093fb;
    }

    .bg-info-light {
        background: rgba(79, 172, 254, 0.1);
        color: #4facfe;
    }

    .bg-danger-light {
        background: rgba(250, 112, 154, 0.1);
        color: #fa709a;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Hover effects */
    .hover-scale {
        transition: transform 0.3s ease;
    }

    .hover-scale:hover {
        transform: scale(1.02);
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->

        <!-- Progression Status Alert -->
        @if(isset($progressionStatus) && $progressionStatus['status'] == 'waiting_for_session')
        <div class="alert alert-info alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-left: 5px solid #17a2b8;">
            <div class="d-flex align-items-center">
                <div class="mr-3">
                    <i class="fas fa-clock fa-2x text-info"></i>
                </div>
                <div>
                    <h5 class="alert-heading mb-1"><i class="fas fa-hourglass-half"></i> Semester Completed!</h5>
                    <p class="mb-0">{{ $progressionStatus['message'] }}</p>
                    <small class="text-muted">You have met all requirements for progression. Your dashboard will automatically update once the new academic session begins.</small>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        @endif

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-hand-sparkles"></i> Welcome back, {{ $student->first_name }}!</h2>
                    <p>{{ Carbon\Carbon::now()->format('l, F j, Y') }}</p>
                    @if(isset($currentEnroll))
                    <p class="mb-0">
                        <i class="fas fa-graduation-cap"></i> {{ $currentEnroll->program->title ?? 'N/A' }} | 
                        <i class="fas fa-calendar-alt"></i> {{ $currentEnroll->semester->title ?? 'N/A' }} | 
                        <i class="fas fa-users"></i> {{ $currentEnroll->section->title ?? 'N/A' }}
                    </p>
                    @endif
                </div>
                <div class="col-md-4 text-md-right">
                    <img src="{{ asset('dashboard/images/graduation-cap.png') }}" alt="Education" style="max-width: 150px; opacity: 0.2;" onerror="this.style.display='none'">
                </div>
            </div>
        </div>

        <!-- Statistics Cards Row -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 fade-in-up delay-1">
                <div class="stat-card gradient-primary">
                    <div class="icon-wrapper">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="metric-loader">{{ number_format($cgpa ?? 0, 2) }}</h3>
                    <p>Cumulative GPA</p>
                    <small style="opacity: 0.8;">{{ $totalCredits ?? 0 }} Credits Earned</small>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 fade-in-up delay-2">
                <div class="stat-card gradient-success">
                    <div class="icon-wrapper">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <h3 class="metric-loader">{{ $passedCourses ?? 0 }}/{{ $totalCourses ?? 0 }}</h3>
                    <p>Courses Passed</p>
                    <small style="opacity: 0.8;">
                        @php
                            $completion = $totalCourses > 0 ? ($passedCourses / $totalCourses) * 100 : 0;
                        @endphp
                        Of {{ $totalCourses ?? 0 }} Courses Attempted • {{ number_format($completion, 1) }}% Complete
                    </small>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 fade-in-up delay-3">
                <div class="stat-card gradient-info">
                    <div class="icon-wrapper">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="metric-loader">{{ number_format($attendancePercentage ?? 0, 1) }}%</h3>
                    <p>Attendance Rate</p>
                    <small style="opacity: 0.8;">{{ $presentAttendance ?? 0 }}/{{ $totalAttendance ?? 0 }} Classes</small>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 fade-in-up delay-4">
                <div class="stat-card gradient-warning">
                    <div class="icon-wrapper">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 class="metric-loader">{{ number_format($dueFees ?? 0, 0) }}</h3>
                    <p>Fees Balance (XAF)</p>
                    <small style="opacity: 0.8;">Paid: {{ number_format($paidFees ?? 0, 0) }} XAF</small>
                </div>
            </div>
        </div>

        <!-- Live Classes Widget - PROMINENT POSITION -->
        @php
            // Check for live class sessions
            $liveClasses = collect();
            $enrollmentId = session('selected_enrollment_id');
            if($enrollmentId) {
                $enrollment = \App\Models\StudentEnroll::find($enrollmentId);
                if($enrollment) {
                    $liveClasses = \App\Models\ClassSession::where('status', 'in_progress')
                        ->where('program_id', $enrollment->program_id)
                        ->where('semester_id', $enrollment->semester_id)
                        ->where('session_id', $enrollment->session_id)
                        ->where(function($q) use ($enrollment) {
                            $q->whereNull('section_id')
                              ->orWhere('section_id', $enrollment->section_id);
                        })
                        ->with(['subject', 'teacher'])
                        ->get();
                }
            }
        @endphp
        @if($liveClasses->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card" style="border: 3px solid #28a745; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); animation: pulse 2s infinite;">
                    <div class="card-body">
                        <div class="section-header d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-success mr-2 px-3 py-2" style="font-size: 14px; animation: pulse 1.5s infinite;">
                                    <i class="fas fa-broadcast-tower mr-1"></i> {{ $liveClasses->count() }} LIVE CLASS{{ $liveClasses->count() > 1 ? 'ES' : '' }}
                                </span>
                                <h5 class="mb-0" style="color: #28a745;"><i class="fas fa-video mr-2"></i>Classes In Progress</h5>
                            </div>
                            <a href="{{ route('student.class-hub.index') }}" class="btn btn-success btn-sm">
                                <i class="fas fa-broadcast-tower mr-1"></i> Go to Class Hub
                            </a>
                        </div>
                        @foreach($liveClasses as $liveClass)
                        <div class="list-item" style="background: white; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #28a745;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="card-icon-bg mr-3" style="background: rgba(40, 167, 69, 0.2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-broadcast-tower fa-lg" style="color: #28a745;"></i>
                                    </div>
                                    <div>
                                        <strong style="font-size: 16px;">{{ $liveClass->subject->title ?? 'Class in Session' }}</strong>
                                        <span class="badge badge-success ml-2" style="animation: pulse 1s infinite;">LIVE NOW</span>
                                        <br><small class="text-muted">
                                            <i class="fas fa-user-tie"></i> {{ $liveClass->teacher->first_name ?? '' }} {{ $liveClass->teacher->last_name ?? 'Lecturer' }}
                                            @if($liveClass->actual_start_time)
                                            | <i class="fas fa-clock"></i> Started {{ \Carbon\Carbon::parse($liveClass->actual_start_time)->diffForHumans() }}
                                            @endif
                                            @if($liveClass->topic_covered)
                                            <br><i class="fas fa-book"></i> Topic: {{ Str::limit($liveClass->topic_covered, 50) }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                                <a href="{{ route('student.class-hub.live-room', $liveClass->id) }}" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Join Now
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Course Breakdown -->
        @if(isset($courseBreakdown) && count($courseBreakdown) > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card fade-in-up">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="section-header mb-0 border-0 pb-0">
                                <i class="fas fa-book-open"></i>
                                <h5 class="mb-0">Complete Course Overview</h5>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="button" id="toggleCourseBreakdown">
                                <i class="fas fa-chevron-down"></i> Expand
                            </button>
                        </div>
                        <p class="text-muted small mb-3">View all courses organized by semester with detailed information including validation status, attempts, and schedules</p>
                        
                        <div class="collapse" id="courseBreakdownCollapse">
                            @foreach($courseBreakdown as $semesterData)
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="badge badge-{{ $semesterData['is_resit'] ? 'warning' : 'primary' }} mr-2">
                                        {{ $semesterData['is_resit'] ? 'RESIT' : 'REGULAR' }}
                                    </div>
                                    <h6 class="mb-0">
                                        <strong>{{ $semesterData['semester']->title }}</strong>
                                        @if($semesterData['session'])
                                        <span class="text-muted"> - {{ $semesterData['session']->title }}</span>
                                        @endif
                                    </h6>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Code</th>
                                                <th>Course Name</th>
                                                <th>Credits</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Grade</th>
                                                <th>Attempts</th>
                                                <th>Schedule</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($semesterData['courses'] as $course)
                                            <tr>
                                                <td><code>{{ $course['code'] }}</code></td>
                                                <td>
                                                    <strong>{{ $course['name'] }}</strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">{{ $course['credits'] }} CR</span>
                                                </td>
                                                <td>
                                                    @php
                                                        $typeMap = [
                                                            0 => ['label' => 'Optional', 'class' => 'secondary'],
                                                            1 => ['label' => 'Compulsory', 'class' => 'danger'],
                                                            2 => ['label' => 'University Req.', 'class' => 'warning']
                                                        ];
                                                        $typeInfo = $typeMap[$course['type']] ?? ['label' => 'Unknown', 'class' => 'secondary'];
                                                    @endphp
                                                    <small class="badge badge-{{ $typeInfo['class'] }}">{{ $typeInfo['label'] }}</small>
                                                </td>
                                                <td>
                                                    @if($course['validated'])
                                                        @if($course['marks']['passed'])
                                                            <span class="badge badge-success">
                                                                <i class="fas fa-check-circle"></i> Passed
                                                            </span>
                                                        @else
                                                            <span class="badge badge-danger">
                                                                <i class="fas fa-times-circle"></i> Failed
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="badge badge-secondary">
                                                            <i class="fas fa-hourglass-half"></i> Pending
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($course['validated'] && $course['marks'])
                                                        <span class="font-weight-bold">{{ $course['marks']['grade'] ?? 'N/A' }}</span>
                                                        <br><small class="text-muted">{{ $course['marks']['marks'] }}% ({{ $course['marks']['grade_point'] }} GP)</small>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $course['attempts'] > 1 ? 'warning' : 'light' }}">
                                                        {{ $course['attempts'] }}{{ $course['attempts'] == 1 ? 'st' : ($course['attempts'] == 2 ? 'nd' : 'th') }}
                                                    </span>
                                                    @if($course['resit_info'])
                                                        <br><small class="text-warning"><i class="fas fa-redo"></i> Resit</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(count($course['class_routines']) > 0)
                                                        <button class="btn btn-sm btn-outline-info" type="button" data-toggle="collapse" data-target="#schedule-{{ $course['id'] }}-{{ $semesterData['semester']->id }}">
                                                            <i class="fas fa-clock"></i> {{ count($course['class_routines']) }}
                                                        </button>
                                                        <div class="collapse mt-2" id="schedule-{{ $course['id'] }}-{{ $semesterData['semester']->id }}">
                                                            <div class="card card-body p-2" style="font-size: 0.85rem;">
                                                                @foreach($course['class_routines'] as $routine)
                                                                <div class="mb-1">
                                                                    <strong>{{ $routine['day'] }}</strong>: 
                                                                    {{ date('g:i A', strtotime($routine['start_time'])) }} - {{ date('g:i A', strtotime($routine['end_time'])) }}
                                                                    @if($routine['room'])
                                                                        <br><small class="text-muted"><i class="fas fa-door-open"></i> {{ $routine['room'] }}</small>
                                                                    @endif
                                                                    @if($routine['teacher'])
                                                                        <br><small class="text-muted"><i class="fas fa-chalkboard-teacher"></i> {{ $routine['teacher'] }}</small>
                                                                    @endif
                                                                </div>
                                                                @if(!$loop->last)<hr class="my-1">@endif
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="text-muted small">No schedule</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if(!$loop->last)
                            <hr class="my-4">
                            @endif
                            @endforeach
                            
                            <div class="text-center mt-4 pt-3 border-top">
                                <a href="{{ route('student.transcript.index') }}" class="btn btn-primary btn-sm mr-2">
                                    <i class="fas fa-file-alt"></i> View Full Transcript
                                </a>
                                <a href="{{ route('student.class-routine.index') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-alt"></i> View Class Routine
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Main Content Grid -->
        <div class="row">
            
            <!-- Left Column -->
            <div class="col-xl-8">
                
                <!-- Quick Actions -->
                <div class="card dashboard-card mb-4 fade-in-left">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-bolt"></i>
                            <h5>Quick Actions</h5>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <a href="{{ route('student.course-registration.index') }}" class="quick-action-btn">
                                    <i class="fas fa-clipboard-list"></i>
                                    <div>
                                        <strong>Course Registration</strong>
                                        <br><small>Manage courses</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('student.class-routine.index') }}" class="quick-action-btn">
                                    <i class="fas fa-calendar-day"></i>
                                    <div>
                                        <strong>Class Routine</strong>
                                        <br><small>View schedule</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('student.exam-results.index') }}" class="quick-action-btn">
                                    <i class="fas fa-award"></i>
                                    <div>
                                        <strong>Exam Results</strong>
                                        <br><small>Check grades</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('student.fees.index') }}" class="quick-action-btn">
                                    <i class="fas fa-wallet"></i>
                                    <div>
                                        <strong>Fees Payment</strong>
                                        <br><small>Pay fees</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('student.assignment.index') }}" class="quick-action-btn">
                                    <i class="fas fa-tasks"></i>
                                    <div>
                                        <strong>Assignments</strong>
                                        <br><small>{{ $pendingAssignments ?? 0 }} pending</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('student.e-library.index') }}" class="quick-action-btn">
                                    <i class="fas fa-book"></i>
                                    <div>
                                        <strong>E-Library</strong>
                                        <br><small>Browse books</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                @if(isset($todayClasses) && count($todayClasses) > 0)
                <div class="card dashboard-card mb-4 fade-in-left delay-1">
                    <div class="card-body">
                        <div class="section-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h5>Today's Classes</h5>
                            </div>
                            <a href="{{ route('student.class-hub.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-broadcast-tower mr-1"></i> Class Hub
                            </a>
                        </div>
                        @foreach($todayClasses as $class)
                        <div class="list-item">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="card-icon-bg bg-primary-light">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    <div>
                                        <strong>{{ $class->subject->title ?? 'N/A' }}</strong>
                                        <br><small class="text-muted">
                                            <i class="fas fa-clock"></i> {{ date('h:i A', strtotime($class->start_time)) }} - {{ date('h:i A', strtotime($class->end_time)) }}
                                            @if($class->room)
                                            | <i class="fas fa-door-open"></i> {{ $class->room }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                                <span class="custom-badge bg-primary text-white">
                                    {{ $class->subject->code ?? '' }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Recent Assignments -->
                @if(isset($assignments) && count($assignments) > 0)
                <div class="card dashboard-card mb-4 fade-in-left delay-2">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-clipboard-check"></i>
                            <h5>Recent Assignments</h5>
                        </div>
                        @foreach($assignments as $assignment)
                        @if($assignment->assignment && $assignment->assignment->status == 1)
                        <div class="list-item">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="d-flex align-items-start flex-grow-1">
                                    <div class="card-icon-bg {{ $assignment->attendance == 1 ? 'bg-success-light' : 'bg-warning-light' }}">
                                        <i class="fas {{ $assignment->attendance == 1 ? 'fa-check-circle' : 'fa-clock' }}"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <a href="{{ route('student.assignment.show', $assignment->id) }}" class="text-dark">
                                            <strong>{{ $assignment->assignment->title ?? 'N/A' }}</strong>
                                        </a>
                                        <br><small class="text-muted">
                                            <i class="fas fa-book"></i> {{ $assignment->assignment->subject->code ?? '' }}
                                            | <i class="fas fa-calendar"></i> Due: {{ date('M d, Y', strtotime($assignment->assignment->end_date)) }}
                                        </small>
                                    </div>
                                </div>
                                @if($assignment->attendance == 1)
                                <span class="custom-badge bg-success text-white">Submitted</span>
                                @else
                                <span class="custom-badge bg-warning text-white">Pending</span>
                                @endif
                            </div>
                        </div>
                        @endif
                        @endforeach
                        <div class="text-center mt-3">
                            <a href="{{ route('student.assignment.index') }}" class="btn btn-outline-primary btn-sm">
                                View All Assignments <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Recent Exam Results -->
                @if(isset($latestMarks) && count($latestMarks) > 0)
                <div class="card dashboard-card mb-4 fade-in-left delay-3">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-chart-bar"></i>
                            <h5>Recent Exam Results</h5>
                        </div>
                        @foreach($latestMarks as $mark)
                        <div class="list-item">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong>{{ $mark->subject->title ?? 'N/A' }}</strong>
                                <span class="custom-badge {{ $mark->total_marks >= 50 ? 'bg-success' : 'bg-danger' }} text-white">
                                    {{ number_format($mark->total_marks, 2) }}%
                                </span>
                            </div>
                            <div class="custom-progress">
                                <div class="custom-progress-bar {{ $mark->total_marks >= 50 ? 'bg-success' : 'bg-danger' }}" 
                                     style="width: {{ $mark->total_marks }}%"></div>
                            </div>
                        </div>
                        @endforeach
                        <div class="text-center mt-3">
                            <a href="{{ route('student.exam-results.index') }}" class="btn btn-outline-primary btn-sm">
                                View All Results <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @endif

            </div>

            <!-- Right Column -->
            <div class="col-xl-4">
                
                <!-- Academic Performance Summary -->
                @if(isset($graduationEligibility))
                <div class="card dashboard-card mb-4 fade-in-right">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-graduation-cap"></i>
                            <h5>Graduation Status</h5>
                        </div>
                        <div class="text-center py-3">
                            @if($graduationEligibility['is_eligible'])
                            <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                            <h6 class="mt-3 text-success">Eligible for Graduation!</h6>
                            @else
                            <i class="fas fa-hourglass-half text-warning" style="font-size: 48px;"></i>
                            <h6 class="mt-3 text-warning">Not Yet Eligible</h6>
                            @endif
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Compulsory Courses</span>
                                <strong>{{ $graduationEligibility['compulsory']['passed_subjects'] }}/{{ $graduationEligibility['compulsory']['total_subjects'] }}</strong>
                            </div>
                            <div class="custom-progress">
                                @php
                                    $compPer = $graduationEligibility['compulsory']['total_subjects'] > 0 
                                        ? ($graduationEligibility['compulsory']['passed_subjects'] / $graduationEligibility['compulsory']['total_subjects']) * 100 
                                        : 0;
                                @endphp
                                <div class="custom-progress-bar bg-primary" style="width: {{ $compPer }}%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>University Requirements</span>
                                <strong>{{ $graduationEligibility['university_requirement']['passed_subjects'] }}/{{ $graduationEligibility['university_requirement']['total_subjects'] }}</strong>
                            </div>
                            <div class="custom-progress">
                                @php
                                    $uniPer = $graduationEligibility['university_requirement']['total_subjects'] > 0 
                                        ? ($graduationEligibility['university_requirement']['passed_subjects'] / $graduationEligibility['university_requirement']['total_subjects']) * 100 
                                        : 0;
                                @endphp
                                <div class="custom-progress-bar bg-info" style="width: {{ $uniPer }}%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Credits</span>
                                <strong>{{ $graduationEligibility['completed_credits'] }}/{{ $graduationEligibility['total_credits'] }}</strong>
                            </div>
                            <div class="custom-progress">
                                @php
                                    $creditPer = $graduationEligibility['total_credits'] > 0 
                                        ? ($graduationEligibility['completed_credits'] / $graduationEligibility['total_credits']) * 100 
                                        : 0;
                                @endphp
                                <div class="custom-progress-bar bg-success" style="width: {{ $creditPer }}%"></div>
                            </div>
                        </div>
                        @if(!$graduationEligibility['is_eligible'] && isset($graduationEligibility['reasons']))
                        <div class="alert alert-warning mb-0" style="font-size: 0.9rem;">
                            <strong>Requirements:</strong>
                            <ul class="mb-0 pl-3 mt-2">
                                @foreach($graduationEligibility['reasons'] as $reason)
                                <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Upcoming Exams -->
                @if(isset($upcomingExams) && count($upcomingExams) > 0)
                <div class="card dashboard-card mb-4 fade-in-right delay-1">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-file-alt"></i>
                            <h5>Upcoming Exams</h5>
                        </div>
                        @foreach($upcomingExams as $exam)
                        <div class="timeline-item">
                            <div class="timeline-icon"></div>
                            <div class="list-item mb-0">
                                <strong>{{ $exam->subject->title ?? 'N/A' }}</strong>
                                <br><small class="text-muted">
                                    <i class="fas fa-calendar"></i> {{ date('M d, Y', strtotime($exam->exam_date)) }}
                                    <br><i class="fas fa-clock"></i> {{ date('h:i A', strtotime($exam->start_time)) }}
                                    @if($exam->room)
                                    <br><i class="fas fa-door-open"></i> {{ $exam->room }}
                                    @endif
                                </small>
                            </div>
                        </div>
                        @endforeach
                        <div class="text-center mt-3">
                            <a href="{{ route('student.exam-routine.index') }}" class="btn btn-outline-primary btn-sm">
                                View Full Schedule <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Latest Notices -->
                @if(isset($latestNotices) && count($latestNotices) > 0)
                <div class="card dashboard-card mb-4 fade-in-right delay-2">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-bullhorn"></i>
                            <h5>Latest Notices</h5>
                        </div>
                        @foreach($latestNotices->take(3) as $notice)
                        <div class="list-item">
                            <a href="{{ route('student.notice.index') }}" class="text-dark">
                                <strong>{{ Str::limit($notice->title, 50) }}</strong>
                            </a>
                            <br><small class="text-muted">
                                <i class="fas fa-calendar"></i> {{ date('M d, Y', strtotime($notice->created_at)) }}
                            </small>
                        </div>
                        @endforeach
                        <div class="text-center mt-3">
                            <a href="{{ route('student.notice.index') }}" class="btn btn-outline-primary btn-sm">
                                View All Notices <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Payment Reminders -->
                @if($dueFees > 0)
                <div class="card dashboard-card mb-4 fade-in-right delay-3" style="border-left: 4px solid #f5576c;">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5>Payment Reminder</h5>
                        </div>
                        <div class="alert alert-warning mb-3">
                            <strong>Outstanding Balance:</strong> {{ number_format($dueFees, 0) }} XAF
                        </div>
                        @if(isset($upcomingPayments) && count($upcomingPayments) > 0)
                        <p class="mb-2"><strong>Pending Payments:</strong></p>
                        @foreach($upcomingPayments as $payment)
                        <div class="list-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>{{ $payment->description ?? 'Payment Plan' }}</span>
                                <span class="custom-badge bg-warning text-white">Pending</span>
                            </div>
                        </div>
                        @endforeach
                        @endif
                        <div class="text-center mt-3">
                            <a href="{{ route('student.fees.index') }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-credit-card mr-2"></i> Make Payment
                            </a>
                        </div>
                    </div>
                </div>
                @endif

                <!-- E-Library Quick Access -->
                @if(isset($recentEBooks) && count($recentEBooks) > 0)
                <div class="card dashboard-card mb-4 fade-in-right delay-4">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-book-reader"></i>
                            <h5>E-Library</h5>
                        </div>
                        <div class="row">
                            @foreach($recentEBooks as $book)
                            <div class="col-6 mb-3">
                                <a href="{{ route('student.e-library.show', $book->id) }}" class="text-decoration-none">
                                    <div class="hover-scale" style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        @if($book->cover_image)
                                        <img src="{{ asset('uploads/e-library/'.$book->cover_image) }}" alt="{{ $book->title }}" style="width: 100%; height: 150px; object-fit: cover;">
                                        @else
                                        <div style="width: 100%; height: 150px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: #fff;">
                                            <i class="fas fa-book" style="font-size: 48px;"></i>
                                        </div>
                                        @endif
                                        <div class="p-2 bg-light">
                                            <small class="text-dark"><strong>{{ Str::limit($book->title, 20) }}</strong></small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            @endforeach
                        </div>
                        <div class="text-center mt-2">
                            <a href="{{ route('student.e-library.index') }}" class="btn btn-outline-primary btn-sm">
                                Browse Library <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Upcoming Events -->
                <div class="card dashboard-card mb-4 fade-in-right delay-5">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-calendar-star"></i>
                            <h5>Upcoming Events</h5>
                        </div>
                        @if(isset($latest_events) && count($latest_events) > 0)
                            @foreach($latest_events->take(3) as $event)
                            <div class="list-item">
                                <div class="d-flex align-items-start">
                                    <div style="width: 50px; height: 50px; background: {{ $event->color }}20; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 15px; border: 2px solid {{ $event->color }};">
                                        <i class="fas fa-calendar-check" style="color: {{ $event->color }}; font-size: 20px;"></i>
                                    </div>
                                    <div>
                                        <strong>{{ $event->title }}</strong>
                                        <br><small class="text-muted">
                                            {{ date('M d', strtotime($event->start_date)) }} 
                                            @if($event->start_date != $event->end_date)
                                            - {{ date('M d, Y', strtotime($event->end_date)) }}
                                            @else
                                            , {{ date('Y', strtotime($event->start_date)) }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @else
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <p>No upcoming events</p>
                        </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        <!-- Calendar Section -->
        <div class="row">
            <div class="col-12">
                <div class="card dashboard-card fade-in-up">
                    <div class="card-body">
                        <div class="section-header">
                            <i class="fas fa-calendar-alt"></i>
                            <h5>Academic Calendar</h5>
                        </div>
                        <div id='calendar' class='calendar'></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<!-- Full calendar js -->
<script src="{{ asset('dashboard/plugins/fullcalendar/js/lib/moment.min.js') }}"></script>
<script src="{{ asset('dashboard/plugins/fullcalendar/js/lib/jquery-ui.min.js') }}"></script>
<script src="{{ asset('dashboard/plugins/fullcalendar/js/fullcalendar.min.js') }}"></script>

<script type="text/javascript">
    // Full calendar
    $(window).on('load', function() {
        "use strict";
        $('#calendar').fullCalendar({
            header: {
                left: 'prev,next',
                center: 'title',
                right: 'today'
            },
            defaultDate: '@php echo date("Y-m-d"); @endphp',
            editable: false,
            droppable: false,
            events: [
                @php
                    foreach($events as $key => $row){
                        echo "{
                                title: '".$row->title."',
                                start: '".$row->start_date."',
                                end: '".$row->end_date."',
                                borderColor: '".$row->color."',
                                backgroundColor: '".$row->color."',
                                textColor: '#fff'
                            }, ";
                    }
                @endphp
            ],
        });
    });

    // Animate progress bars on load
    $(document).ready(function() {
        $('.custom-progress-bar').each(function() {
            const width = $(this).attr('style').match(/width:\s*(\d+(\.\d+)?%)/);
            if (width) {
                $(this).css('width', '0%');
                setTimeout(() => {
                    $(this).css('width', width[1]);
                }, 300);
            }
        });

        // Add fade-in animation to elements as they come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right').forEach(el => {
            el.style.opacity = '0';
            observer.observe(el);
        });

        // Course breakdown expand/collapse functionality
        setTimeout(function() {
            var $toggleBtn = $('#toggleCourseBreakdown');
            var $collapseDiv = $('#courseBreakdownCollapse');
            
            console.log('Toggle button found:', $toggleBtn.length);
            console.log('Collapse div found:', $collapseDiv.length);
            console.log('Bootstrap collapse is available');
            
            // Manual click handler to trigger collapse
            $toggleBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Button clicked!');
                $collapseDiv.collapse('toggle');
            });
            
            // Listen for collapse events
            $collapseDiv.on('show.bs.collapse', function () {
                console.log('Collapse showing');
                $toggleBtn.html('<i class="fas fa-chevron-up"></i> Collapse');
            });

            $collapseDiv.on('hide.bs.collapse', function () {
                console.log('Collapse hiding');
                $toggleBtn.html('<i class="fas fa-chevron-down"></i> Expand');
            });
            
            $collapseDiv.on('shown.bs.collapse', function () {
                console.log('Collapse shown (animation complete)');
            });
        }, 100);
    });
</script>
@endsection
