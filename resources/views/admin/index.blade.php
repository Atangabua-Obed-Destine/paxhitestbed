@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    /* Modern Dashboard Styles */
    .welcome-banner {
        background: linear-gradient(135deg, #182b49 0%, #2c4168 100%);
        border-radius: 15px;
        padding: 2rem;
        color: white;
        box-shadow: 0 8px 20px rgba(24, 43, 73, 0.3);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .welcome-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }
    
    .welcome-banner h2 {
        font-size: 2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
        color: white !important;
    }
    
    .welcome-banner p {
        font-size: 1rem;
        opacity: 0.9;
        margin-bottom: 0;
        position: relative;
        z-index: 1;
        color: white !important;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        margin-bottom: 1.5rem;
        border: none;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #04a9f5, #1de9b6);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card:hover::before {
        transform: scaleX(1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
        color: #182b49;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .quick-actions-section {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }
    
    .section-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: #182b49;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .section-title i {
        margin-right: 0.8rem;
        color: #04a9f5;
    }
    
    .action-btn {
        display: block;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 1rem 1.2rem;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
        margin-bottom: 0.8rem;
        position: relative;
        overflow: hidden;
    }
    
    .action-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(4, 169, 245, 0.1), transparent);
        transition: left 0.5s ease;
    }
    
    .action-btn:hover::before {
        left: 100%;
    }
    
    .action-btn:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(4, 169, 245, 0.2);
        border-color: #04a9f5;
        color: #04a9f5;
    }
    
    .action-btn i {
        margin-right: 0.8rem;
        width: 25px;
        text-align: center;
    }
    
    .action-btn .badge {
        float: right;
        margin-top: 2px;
    }
    
    /* Color variations for stat icons */
    .stat-blue { background: linear-gradient(135deg, #04a9f5 0%, #0692d8 100%); color: white; }
    .stat-green { background: linear-gradient(135deg, #1de9b6 0%, #17c79a 100%); color: white; }
    .stat-orange { background: linear-gradient(135deg, #f4c22b 0%, #dba719 100%); color: white; }
    .stat-red { background: linear-gradient(135deg, #f44236 0%, #d9362b 100%); color: white; }
    .stat-purple { background: linear-gradient(135deg, #8a5fc6 0%, #7146b4 100%); color: white; }
    .stat-teal { background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%); color: white; }
    .stat-indigo { background: linear-gradient(135deg, #5f27cd 0%, #4b1eae 100%); color: white; }
    .stat-pink { background: linear-gradient(135deg, #ee5a6f 0%, #d9455a 100%); color: white; }
    
    /* Pulse animation */
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .pulse-animation {
        animation: pulse 2s infinite;
    }
    
    /* Section Grid */
    .sections-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    @media (max-width: 768px) {
        .welcome-banner h2 {
            font-size: 1.5rem;
        }
        .stat-value {
            font-size: 1.5rem;
        }
        .sections-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>👋 Welcome back, {{ Auth::user()->first_name }}!</h2>
            <p>Here's what's happening in your institution today - {{ \Carbon\Carbon::now()->format('l, F d, Y') }}</p>
        </div>
        
        <!-- Teacher Dashboard Section -->
        @if($is_teacher)
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 style="margin: 0; color: white;">
                            <i class="fas fa-chalkboard-teacher"></i> My Teaching Schedule
                        </h5>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            <!-- Summary Cards -->
                            <div class="col-md-4">
                                <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                                    <div class="card-block text-center">
                                        <h3 style="color: white; margin: 0;">{{ $teacher_today_classes->count() }}</h3>
                                        <p style="margin: 5px 0; color: white;">Classes Today</p>
                                        <small style="color: rgba(255,255,255,0.8);">{{ \Carbon\Carbon::now()->format('l') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
                                    <div class="card-block text-center">
                                        <h3 style="color: white; margin: 0;">{{ $teacher_subjects->count() }}</h3>
                                        <p style="margin: 5px 0; color: white;">Courses Teaching</p>
                                        <small style="color: rgba(255,255,255,0.8);">This Session</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
                                    <div class="card-block text-center">
                                        <h3 style="color: white; margin: 0;">{{ $teacher_total_classes }}</h3>
                                        <p style="margin: 5px 0; color: white;">Weekly Classes</p>
                                        <small style="color: rgba(255,255,255,0.8);">Total Schedule</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Live Class Kiosk Quick Access -->
                        @php
                            $today = date('Y-m-d');
                            $activeSession = \App\Models\ClassSession::where('teacher_id', Auth::id())
                                ->where('date', $today)
                                ->where('status', 'in_progress')
                                ->with(['subject', 'program'])
                                ->first();
                            
                            $pendingSessions = \App\Models\ClassSession::where('teacher_id', Auth::id())
                                ->where('date', $today)
                                ->where('status', 'pending')
                                ->count();
                            
                            $completedSessions = \App\Models\ClassSession::where('teacher_id', Auth::id())
                                ->where('date', $today)
                                ->where('status', 'completed')
                                ->count();
                        @endphp
                        
                        <div class="mt-3">
                            @if($activeSession)
                            <!-- Active Session Alert -->
                            <div class="card mb-3" style="border: 3px solid #28a745; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); animation: pulse 2s infinite;">
                                <div class="card-block">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 60px; height: 60px; background: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; animation: pulse 1.5s infinite;">
                                                <i class="fas fa-broadcast-tower fa-2x text-white"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1" style="color: #155724;">
                                                    <span class="badge badge-success mr-2" style="animation: pulse 1s infinite;">LIVE NOW</span>
                                                    Class In Progress
                                                </h5>
                                                <p class="mb-0" style="color: #155724;">
                                                    <strong>{{ $activeSession->subject->code ?? '' }} - {{ $activeSession->subject->title ?? 'Unknown Course' }}</strong>
                                                    <br>
                                                    <small>
                                                        <i class="fas fa-graduation-cap"></i> {{ $activeSession->program->short_form ?? $activeSession->program->title ?? 'N/A' }}
                                                        @if($activeSession->actual_start_time)
                                                        | <i class="fas fa-clock"></i> Started {{ \Carbon\Carbon::parse($activeSession->actual_start_time)->diffForHumans() }}
                                                        @endif
                                                    </small>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="{{ route('admin.class-session.kiosk') }}" class="btn btn-success btn-lg">
                                            <i class="fas fa-sign-in-alt mr-2"></i> Return to Kiosk
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @else
                            <!-- Quick Access Card -->
                            <div class="card mb-3" style="border: 2px solid #17a2b8; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                <div class="card-block">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                                <i class="fas fa-qrcode fa-2x text-white"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1" style="color: #0c5460;">
                                                    <i class="fas fa-chalkboard mr-2"></i> Live Class Kiosk
                                                </h5>
                                                <p class="mb-0" style="color: #0c5460;">
                                                    Start classes, take attendance with QR scanner, manage logbooks
                                                    <br>
                                                    <small>
                                                        @if($pendingSessions > 0)
                                                        <span class="badge badge-warning mr-2"><i class="fas fa-hourglass-half"></i> {{ $pendingSessions }} pending</span>
                                                        @endif
                                                        @if($completedSessions > 0)
                                                        <span class="badge badge-secondary"><i class="fas fa-check"></i> {{ $completedSessions }} completed today</span>
                                                        @endif
                                                        @if($pendingSessions == 0 && $completedSessions == 0)
                                                        <span class="text-muted">No sessions scheduled for today</span>
                                                        @endif
                                                    </small>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="{{ route('admin.class-session.kiosk') }}" class="btn btn-info btn-lg">
                                            <i class="fas fa-external-link-alt mr-2"></i> Open Kiosk
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Today's Classes -->
                        @if($teacher_today_classes->count() > 0)
                        <div class="mt-3">
                            <h6><i class="fas fa-clock"></i> Today's Classes</h6>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Course</th>
                                            <th>Program</th>
                                            <th>Semester</th>
                                            <th>Section</th>
                                            <th>Room</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($teacher_today_classes as $class)
                                        <tr>
                                            <td>
                                                <strong>
                                                    @if(isset($setting->time_format))
                                                    {{ date($setting->time_format, strtotime($class->start_time)) }}
                                                    @else
                                                    {{ date("h:i A", strtotime($class->start_time)) }}
                                                    @endif
                                                    -
                                                    @if(isset($setting->time_format))
                                                    {{ date($setting->time_format, strtotime($class->end_time)) }}
                                                    @else
                                                    {{ date("h:i A", strtotime($class->end_time)) }}
                                                    @endif
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary">{{ $class->subject->code ?? '' }}</span>
                                                {{ $class->subject->title ?? '' }}
                                            </td>
                                            <td>{{ $class->program->title ?? '' }}</td>
                                            <td>{{ $class->semester->title ?? '' }}</td>
                                            <td>{{ $class->section->title ?? '' }}</td>
                                            <td><i class="fas fa-door-open"></i> {{ $class->room->title ?? '' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @else
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> No classes scheduled for today.
                        </div>
                        @endif
                        
                        <!-- My Courses -->
                        @if($teacher_subjects->count() > 0)
                        <div class="mt-3">
                            <h6><i class="fas fa-book"></i> My Courses This Session</h6>
                            <div class="row">
                                @foreach($teacher_subjects as $subject)
                                <div class="col-md-3 mb-2">
                                    <div class="card" style="border-left: 4px solid #667eea;">
                                        <div class="card-block" style="padding: 10px;">
                                            <strong>{{ $subject->code }}</strong><br>
                                            <small>{{ $subject->title }}</small>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        
                        <!-- Full Weekly Schedule (Collapsible) -->
                        @if($teacher_weekly_schedule->count() > 0)
                        <div class="mt-3">
                            <button class="btn btn-outline-info btn-block" type="button" data-bs-toggle="collapse" data-bs-target="#fullScheduleCollapse" aria-expanded="false" aria-controls="fullScheduleCollapse">
                                <i class="fas fa-calendar-week"></i> Show Full Weekly Schedule
                                <i class="fas fa-chevron-down float-right mt-1"></i>
                            </button>
                            
                            <div class="collapse mt-2" id="fullScheduleCollapse">
                                <div class="card">
                                    <div class="card-block">
                                        <h6 class="mb-3"><i class="fas fa-calendar-alt"></i> Complete Weekly Schedule</h6>
                                        
                                        @php
                                        $days = [
                                            '1' => 'Saturday',
                                            '2' => 'Sunday',
                                            '3' => 'Monday',
                                            '4' => 'Tuesday',
                                            '5' => 'Wednesday',
                                            '6' => 'Thursday',
                                            '7' => 'Friday'
                                        ];
                                        @endphp
                                        
                                        @foreach($days as $dayNumber => $dayName)
                                            @if($teacher_weekly_schedule->has($dayNumber))
                                            <div class="mb-3">
                                                <h6 class="text-primary"><i class="fas fa-calendar-day"></i> {{ $dayName }}</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th width="15%">Time</th>
                                                                <th width="20%">Subject</th>
                                                                <th width="20%">Program</th>
                                                                <th width="15%">Semester</th>
                                                                <th width="15%">Section</th>
                                                                <th width="15%">Room</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($teacher_weekly_schedule[$dayNumber] as $class)
                                                            <tr>
                                                                <td>
                                                                    <small>
                                                                        @if(isset($setting->time_format))
                                                                        {{ date($setting->time_format, strtotime($class->start_time)) }}
                                                                        @else
                                                                        {{ date("h:i A", strtotime($class->start_time)) }}
                                                                        @endif
                                                                        -
                                                                        @if(isset($setting->time_format))
                                                                        {{ date($setting->time_format, strtotime($class->end_time)) }}
                                                                        @else
                                                                        {{ date("h:i A", strtotime($class->end_time)) }}
                                                                        @endif
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-info">{{ $class->subject->code ?? '' }}</span>
                                                                    <br><small>{{ $class->subject->title ?? '' }}</small>
                                                                </td>
                                                                <td><small>{{ $class->program->title ?? '' }}</small></td>
                                                                <td><small>{{ $class->semester->title ?? '' }}</small></td>
                                                                <td><small>{{ $class->section->title ?? '' }}</small></td>
                                                                <td><small><i class="fas fa-door-open"></i> {{ $class->room->title ?? '' }}</small></td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            @endif
                                        @endforeach
                                        
                                        @if($teacher_weekly_schedule->count() == 0)
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> No classes scheduled for this week.
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Quick Links -->
                        <div hidden class="mt-3">
                            <a href="{{ route('admin.class-routine.teacher') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-calendar-alt"></i> View Full Schedule
                            </a>
                            <a href="{{ route('admin.class-routine.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-list"></i> All Class Routines
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Quick Stats Overview -->
        <div class="row">
            <!-- Admission Stats -->
            @canany(['application-view', 'student-view'])
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value">{{ $pending_applications }}</div>
                    <div class="stat-label">Pending Applications</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-green">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-value">{{ $active_students }}</div>
                    <div class="stat-label">Active Students</div>
                </div>
            </div>
            @endcanany
            
            @canany(['student-enroll-single', 'student-enroll-group'])
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-purple">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <div class="stat-value">{{ $enrolled_students }}</div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>
            @endcanany
            
            @can('student-enroll-complete')
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-teal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value">{{ $course_completed }}</div>
                    <div class="stat-label">Graduated Students</div>
                </div>
            </div>
            @endcan
        </div>
        
        <!-- Attendance & Classes -->
        @canany(['student-attendance-action', 'class-routine-view'])
        <div class="row">
            @can('student-attendance-action')
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-green pulse-animation">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value">{{ $today_present }}</div>
                    <div class="stat-label">Present Today</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value">{{ $today_absent }}</div>
                    <div class="stat-label">Absent Today</div>
                </div>
            </div>
            @endcan
            
            @can('class-routine-view')
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-indigo">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value">{{ $today_classes }}</div>
                    <div class="stat-label">Classes Today</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-orange">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value">{{ $total_exam_routines }}</div>
                    <div class="stat-label">Exam Schedules</div>
                </div>
            </div>
            @endcan
        </div>
        @endcanany
        
        <!-- Fees & Payments -->
        @canany(['fees-student-report'])
        <div class="row">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-green">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">{!! $setting->currency_symbol ?? '$' !!}{{ number_format($total_fees_collected, 0) }}</div>
                    <div class="stat-label">Fees Collected</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-orange pulse-animation">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value">{!! $setting->currency_symbol ?? '$' !!}{{ number_format($total_fees_due, 0) }}</div>
                    <div class="stat-label">Fees Due</div>
                </div>
            </div>
            
            @can('payment-receipt-verify')
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-purple">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value">{{ $payment_verifications }}</div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
            </div>
            @endcan
            
            @can('payment-plan.index')
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-teal">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value">{{ $active_payment_plans }}</div>
                    <div class="stat-label">Active Payment Plans</div>
                </div>
            </div>
            @endcan
        </div>
        @endcanany
        
        <!-- Staff & HR -->
        @canany(['user-view', 'payroll-view'])
        <div class="row">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value">{{ $active_staffs }}</div>
                    <div class="stat-label">Active Staff</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-orange">
                        <i class="fas fa-umbrella-beach"></i>
                    </div>
                    <div class="stat-value">{{ $staff_on_leave }}</div>
                    <div class="stat-label">Staff on Leave</div>
                </div>
            </div>
            
            @can('payroll-view')
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-red pulse-animation">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-value">{{ $pending_payroll }}</div>
                    <div class="stat-label">Pending Payroll</div>
                </div>
            </div>
            @endcan
            
            @can('book-view')
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon stat-purple">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-value">{{ $library_books }}</div>
                    <div class="stat-label">Library Books</div>
                </div>
            </div>
            @endcan
        </div>
        @endcanany
        
        <!-- Active Students Breakdown (Collapsible) -->
        @can('student-view')
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white" id="headingOne">
                        <h5 class="mb-0">
                            <button class="btn btn-link btn-block text-left collapsed d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne" style="text-decoration: none; color: #333;">
                                <span><i class="fas fa-chart-pie text-primary mr-2"></i> Active Students Breakdown</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </h5>
                    </div>

                    <div id="collapseOne" class="collapse" aria-labelledby="headingOne">
                        <div class="card-body">
                            <ul class="nav nav-pills mb-3" id="breakdownTab" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" id="faculty-tab" data-bs-toggle="tab" data-bs-target="#faculty" type="button" role="tab" aria-controls="faculty" aria-selected="true">By Faculty</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" id="department-tab" data-bs-toggle="tab" data-bs-target="#department" type="button" role="tab" aria-controls="department" aria-selected="false">By Department</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" id="program-tab" data-bs-toggle="tab" data-bs-target="#program" type="button" role="tab" aria-controls="program" aria-selected="false">By Program</button>
                                </li>
                            </ul>
                            <div class="tab-content" id="breakdownTabContent">
                                <!-- Faculty Tab -->
                                <div class="tab-pane fade show active" id="faculty" role="tabpanel" aria-labelledby="faculty-tab">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped" id="facultyTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Faculty</th>
                                                    <th class="text-right">Active Students</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($students_by_faculty as $faculty)
                                                <tr>
                                                    <td>{{ $faculty->title }}</td>
                                                    <td class="text-right"><span class="badge badge-primary badge-pill">{{ $faculty->active_students_count }}</span></td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Department Tab -->
                                <div class="tab-pane fade" id="department" role="tabpanel" aria-labelledby="department-tab">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped" id="departmentTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Department</th>
                                                    <th class="text-right">Active Students</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($students_by_department as $dept)
                                                <tr>
                                                    <td>{{ $dept->title }}</td>
                                                    <td class="text-right"><span class="badge badge-info badge-pill">{{ $dept->active_students_count }}</span></td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Program Tab -->
                                <div class="tab-pane fade" id="program" role="tabpanel" aria-labelledby="program-tab">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped" id="programTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Program</th>
                                                    <th class="text-right">Active Students</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($students_by_program as $prog)
                                                <tr>
                                                    <td>{{ $prog->title }}</td>
                                                    <td class="text-right"><span class="badge badge-success badge-pill">{{ $prog->student_enrolls_count }}</span></td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endcan

        <!-- Quick Actions Grid -->
        <div class="sections-grid">
            
            <!-- Admission Section -->
            @canany(['application-view', 'student-view', 'student-transfer-in-view', 'student-card'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-university"></i>
                    {{ trans_choice('module_admission', 2) }}
                </div>
                @can('application-view')
                <a href="{{ route('admin.application.index') }}" class="action-btn">
                    <i class="fas fa-scroll"></i> {{ trans_choice('module_application', 2) }}
                    @if($pending_applications > 0)
                    <span class="badge badge-danger">{{ $pending_applications }}</span>
                    @endif
                </a>
                @endcan
                @can('student-view')
                <a href="{{ route('admin.student.index') }}" class="action-btn">
                    <i class="fas fa-users"></i> {{ trans_choice('module_student', 1) }} {{ __('list') }}
                </a>
                @endcan
                @can('student-transfer-in-view')
                <a href="{{ route('admin.student-transfer-in.index') }}" class="action-btn">
                    <i class="fas fa-exchange-alt"></i> {{ trans_choice('module_transfer_in', 1) }}
                </a>
                @endcan
                @can('student-card')
                <a href="{{ route('admin.id-card.index') }}" class="action-btn">
                    <i class="fas fa-id-card"></i> {{ trans_choice('module_id_card', 2) }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Student Management -->
            @canany(['student-attendance-action', 'student-enroll-single', 'student-enroll-complete', 'student-enroll-alumni'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-user-graduate"></i>
                    {{ trans_choice('module_student', 2) }}
                </div>
                @can('student-attendance-action')
                <a href="{{ route('admin.student-attendance.index') }}" class="action-btn">
                    <i class="fas fa-calendar-check"></i> {{ trans_choice('module_attendance', 2) }}
                </a>
                @endcan
                @can('student-enroll-single')
                <a href="{{ route('admin.single-enroll.index') }}" class="action-btn">
                    <i class="fas fa-user-plus"></i> {{ trans_choice('module_single_enroll', 1) }}
                </a>
                @endcan
                @can('student-enroll-complete')
                <a href="{{ route('admin.course-complete.index') }}" class="action-btn">
                    <i class="fas fa-check-circle"></i> {{ trans_choice('module_course_complete', 2) }}
                    @if($course_completed > 0)
                    <span class="badge badge-success">{{ $course_completed }}</span>
                    @endif
                </a>
                @endcan
                @can('student-enroll-alumni')
                <a href="{{ route('admin.student-alumni.index') }}" class="action-btn">
                    <i class="fas fa-user-tie"></i> {{ trans_choice('module_student_alumni', 1) }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Routines -->
            @canany(['class-routine-view', 'exam-routine-view', 'class-routine-teacher'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="far fa-calendar-alt"></i>
                    {{ trans_choice('module_routine', 2) }}
                </div>
                @can('class-routine-view')
                <a href="{{ route('admin.class-routine.index') }}" class="action-btn">
                    <i class="fas fa-chalkboard-teacher"></i> {{ trans_choice('module_class_routine', 2) }}
                    @if($today_classes > 0)
                    <span class="badge badge-info">{{ $today_classes }} today</span>
                    @endif
                </a>
                @endcan
                @can('exam-routine-view')
                <a href="{{ route('admin.exam-routine.index') }}" class="action-btn">
                    <i class="fas fa-file-alt"></i> {{ trans_choice('module_exam_routine', 2) }}
                </a>
                @endcan
                @can('class-routine-teacher')
                <a href="{{ route('admin.class-routine.teacher') }}" class="action-btn">
                    <i class="fas fa-user-clock"></i> {{ trans_choice('module_teacher_routine', 2) }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Examination -->
            @canany(['exam-attendance', 'exam-marking', 'subject-marking', 'resit-request-view', 'admit-card-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i>
                    {{ trans_choice('module_examination', 2) }}
                </div>
                @can('exam-attendance')
                <a href="{{ route('admin.exam-attendance.index') }}" class="action-btn">
                    <i class="fas fa-clipboard-check"></i> {{ trans_choice('module_exam_attendance', 2) }}
                </a>
                @endcan
                @can('exam-marking')
                <a href="{{ route('admin.exam-marking.index') }}" class="action-btn">
                    <i class="fas fa-edit"></i> {{ trans_choice('module_exam_marking', 2) }}
                </a>
                <a href="{{ route('admin.student-exam-config.index') }}" class="action-btn">
                    <i class="fas fa-cog"></i> {{ __('Student Exam Config') }}
                </a>
                @endcan
                @can('subject-marking')
                <a href="{{ route('admin.subject-marking.index') }}" class="action-btn">
                    <i class="fas fa-marker"></i> {{ trans_choice('module_subject_marking', 2) }}
                </a>
                @endcan
                @can('resit-request-view')
                <a href="{{ route('admin.resit-requests.index') }}" class="action-btn">
                    <i class="fas fa-redo"></i> {{ __('Resit Requests') }}
                    @if($resit_requests > 0)
                    <span class="badge badge-warning pulse-animation">{{ $resit_requests }}</span>
                    @endif
                </a>
                @endcan
                @can('admit-card-view')
                <a href="{{ route('admin.admit-card.index') }}" class="action-btn">
                    <i class="fas fa-id-badge"></i> {{ trans_choice('module_admit_card', 2) }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Study Material -->
            @canany(['assignment-view', 'content-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-newspaper"></i>
                    {{ trans_choice('module_study_material', 2) }}
                </div>
                @can('assignment-view')
                <a href="{{ route('admin.assignment.index') }}" class="action-btn">
                    <i class="fas fa-tasks"></i> {{ trans_choice('module_assignment', 2) }}
                    @if($active_assignments > 0)
                    <span class="badge badge-primary">{{ $active_assignments }}</span>
                    @endif
                </a>
                @endcan
                @can('content-view')
                <a href="{{ route('admin.content.index') }}" class="action-btn">
                    <i class="fas fa-file-download"></i> {{ trans_choice('module_content', 1) }} {{ __('list') }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Fees Collection -->
            @canany(['fees-student-due', 'fees-student-report', 'payment-plan.index', 'fees-master-view', 'payment-receipt-verify'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-money-bill-wave"></i>
                    {{ trans_choice('module_fees_collection', 1) }}
                </div>
                @can('fees-student-due')
                <a href="{{ route('admin.fees-student.index') }}" class="action-btn">
                    <i class="fas fa-file-invoice-dollar"></i> {{ trans_choice('module_student_fees', 2) }}
                    @if($pending_payments > 0)
                    <span class="badge badge-warning">{{ $pending_payments }}</span>
                    @endif
                </a>
                @endcan
                @can('fees-student-report')
                <a href="{{ route('admin.fees-student.report') }}" class="action-btn">
                    <i class="fas fa-chart-bar"></i> {{ trans_choice('module_fees_report', 2) }}
                </a>
                @endcan
                @can('payment-plan.index')
                <a href="{{ route('admin.payment-plan.index') }}" class="action-btn">
                    <i class="fas fa-calendar-alt"></i> Payment Plans
                    @if($active_payment_plans > 0)
                    <span class="badge badge-info">{{ $active_payment_plans }}</span>
                    @endif
                </a>
                @endcan
                @can('fees-master-create')
                <a href="{{ route('admin.fees-master.create') }}" class="action-btn">
                    <i class="fas fa-plus-circle"></i> {{ trans_choice('module_fees_master', 2) }}
                </a>
                @endcan
                @can('fees-master-view')
                <a href="{{ route('admin.fees-master.index') }}" class="action-btn">
                    <i class="fas fa-history"></i> {{ trans_choice('module_fees_master_history', 2) }}
                </a>
                @endcan
                @can('payment-receipt-verify')
                <a href="{{ route('admin.payment-verification.index') }}" class="action-btn">
                    <i class="fas fa-check-double"></i> {{ trans_choice('module_payment_verification', 2) }}
                    @if($payment_verifications > 0)
                    <span class="badge badge-danger pulse-animation">{{ $payment_verifications }}</span>
                    @endif
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Human Resource -->
            @canany(['user-view', 'staff-assignment-index', 'staff-view', 'payroll-view', 'payroll-report', 'tax-setting-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-users-cog"></i>
                    {{ trans_choice('module_human_resource', 2) }}
                </div>
                @can('user-view')
                <a href="{{ route('admin.user.index') }}" class="action-btn">
                    <i class="fas fa-users"></i> {{ trans_choice('module_staff', 1) }} {{ __('list') }}
                    <span class="badge badge-secondary">{{ $active_staffs }}</span>
                </a>
                @endcan
                @can('staff-assignment-index')
                <a href="{{ route('admin.staff-assignment.index') }}" class="action-btn">
                    <i class="fas fa-user-tag"></i> {{ __('Staff Assign') }}
                </a>
                @endcan
                @can('staff-view')
                <a href="{{ route('admin.staff-id-card.index') }}" class="action-btn">
                    <i class="fas fa-id-card"></i> {{ trans_choice('module_staff', 1) }} {{ __('field_id_card') }}
                </a>
                @endcan
                @can('payroll-view')
                <a href="{{ route('admin.payroll.index') }}" class="action-btn">
                    <i class="fas fa-money-check-alt"></i> {{ trans_choice('module_payroll', 2) }}
                    @if($pending_payroll > 0)
                    <span class="badge badge-danger">{{ $pending_payroll }}</span>
                    @endif
                </a>
                @endcan
                @can('payroll-report')
                <a href="{{ route('admin.payroll.report') }}" class="action-btn">
                    <i class="fas fa-file-invoice"></i> {{ trans_choice('module_payroll_report', 2) }}
                </a>
                @endcan
                @can('tax-setting-view')
                <a href="{{ route('admin.tax-setting.index') }}" class="action-btn">
                    <i class="fas fa-percent"></i> {{ trans_choice('module_tax_setting', 2) }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Staff Attendance -->
            @canany(['staff-daily-attendance-action', 'staff-hourly-attendance-action'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-calendar-check"></i>
                    {{ trans_choice('module_staff_attendance', 2) }}
                </div>
                @can('staff-daily-attendance-action')
                <a href="{{ route('admin.staff-daily-attendance.index') }}" class="action-btn">
                    <i class="fas fa-calendar-day"></i> {{ trans_choice('module_staff_daily_attendance', 2) }}
                </a>
                @endcan
                @can('staff-hourly-attendance-action')
                <a href="{{ route('admin.staff-hourly-attendance.index') }}" class="action-btn">
                    <i class="fas fa-clock"></i> {{ trans_choice('module_staff_hourly_attendance', 2) }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Accounting -->
            @canany(['income-view', 'expense-view', 'budget-view', 'chart-of-accounts-view', 'journal-entry-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-calculator"></i>
                    {{ __('Accounting') }}
                </div>
                @can('income-view')
                <a href="{{ route('admin.income.index') }}" class="action-btn">
                    <i class="fas fa-hand-holding-usd"></i> {{ trans_choice('module_income', 1) }} {{ __('list') }}
                </a>
                @endcan
                @can('expense-view')
                <a href="{{ route('admin.expense.index') }}" class="action-btn">
                    <i class="fas fa-credit-card"></i> {{ trans_choice('module_expense', 1) }} {{ __('list') }}
                </a>
                @endcan
                @can('budget-view')
                <a href="{{ route('admin.budget.dashboard') }}" class="action-btn">
                    <i class="fas fa-chart-pie"></i> {{ __('budget_dashboard') }}
                </a>
                <a href="{{ route('admin.budget.index') }}" class="action-btn">
                    <i class="fas fa-list"></i> {{ trans_choice('module_budget', 2) }}
                </a>
                @endcan
                @can('payment-account-view')
                <a href="{{ route('admin.payment-account.index') }}" class="action-btn">
                    <i class="fas fa-university"></i> {{ __('Payment Accounts') }}
                </a>
                <a href="{{ route('admin.payment-account-transfer.index') }}" class="action-btn">
                    <i class="fas fa-exchange-alt"></i> {{ __('Account Transfers') }}
                </a>
                @endcan
                @can('chart-of-accounts-view')
                <a href="{{ route('admin.chart-of-accounts.index') }}" class="action-btn">
                    <i class="fas fa-sitemap"></i> {{ __('Chart of Accounts') }}
                </a>
                @endcan
                @can('journal-entry-view')
                <a href="{{ route('admin.journal-entries.index') }}" class="action-btn">
                    <i class="fas fa-book"></i> {{ __('Journal Entries') }}
                    @if(isset($unposted_entries) && $unposted_entries > 0)
                    <span class="badge badge-warning">{{ $unposted_entries }}</span>
                    @endif
                </a>
                @endcan
                @can('general-ledger-view')
                <a href="{{ route('admin.general-ledger.index') }}" class="action-btn">
                    <i class="fas fa-file-invoice"></i> {{ __('General Ledger') }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Library -->
            @canany(['issue-return-create', 'issue-return-view', 'book-view', 'book-request-view', 'e-library-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-book-open"></i>
                    {{ trans_choice('module_library', 1) }}
                </div>
                @can('issue-return-create')
                <a href="{{ route('admin.issue-return.create') }}" class="action-btn">
                    <i class="fas fa-book-medical"></i> {{ trans_choice('module_issue_book', 1) }}
                </a>
                @endcan
                @can('issue-return-view')
                <a href="{{ route('admin.issue-return.index') }}" class="action-btn">
                    <i class="fas fa-list"></i> {{ trans_choice('module_issue_return', 2) }}
                    @if($books_issued > 0)
                    <span class="badge badge-info">{{ $books_issued }} issued</span>
                    @endif
                    @if($books_overdue > 0)
                    <span class="badge badge-danger pulse-animation">{{ $books_overdue }} overdue</span>
                    @endif
                </a>
                @endcan
                @can('book-view')
                <a href="{{ route('admin.book-list.index') }}" class="action-btn">
                    <i class="fas fa-books"></i> {{ trans_choice('module_book_list', 2) }}
                    <span class="badge badge-secondary">{{ $library_books }}</span>
                </a>
                @endcan
                @can('book-request-view')
                <a href="{{ route('admin.book-request.index') }}" class="action-btn">
                    <i class="fas fa-book-reader"></i> {{ trans_choice('module_book_request', 2) }}
                    @if($pending_book_requests > 0)
                    <span class="badge badge-warning">{{ $pending_book_requests }}</span>
                    @endif
                </a>
                @endcan
                @can('e-library-view')
                <a href="{{ route('admin.e-library.index') }}" class="action-btn">
                    <i class="fas fa-laptop"></i> {{ __('E-Library') }}
                    <span class="badge badge-info">{{ $e_library_resources }}</span>
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Inventory -->
            @canany(['item-issue-create', 'item-issue-view', 'item-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-boxes"></i>
                    {{ trans_choice('module_inventory', 2) }}
                </div>
                @can('item-issue-create')
                <a href="{{ route('admin.item-issue.create') }}" class="action-btn">
                    <i class="fas fa-plus-circle"></i> {{ trans_choice('module_issue_item', 1) }}
                </a>
                @endcan
                @can('item-issue-view')
                <a href="{{ route('admin.item-issue.index') }}" class="action-btn">
                    <i class="fas fa-list"></i> {{ trans_choice('module_item_issue', 2) }}
                </a>
                @endcan
                @can('item-view')
                <a href="{{ route('admin.item-list.index') }}" class="action-btn">
                    <i class="fas fa-box"></i> {{ trans_choice('module_item_list', 2) }}
                </a>
                <a href="{{ route('admin.item-stock.index') }}" class="action-btn">
                    <i class="fas fa-warehouse"></i> {{ trans_choice('module_item_stock', 2) }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Communication -->
            @canany(['email-notify-create', 'event-view', 'notice-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-bullhorn"></i>
                    {{ trans_choice('module_communicate', 2) }}
                </div>
                @can('email-notify-create')
                <a href="{{ route('admin.email-notify.index') }}" class="action-btn">
                    <i class="fas fa-envelope"></i> {{ trans_choice('module_email_notify', 2) }}
                </a>
                @endcan
                @can('event-view')
                <a href="{{ route('admin.event.index') }}" class="action-btn">
                    <i class="fas fa-calendar"></i> {{ trans_choice('module_event', 2) }}
                    @if($upcoming_events > 0)
                    <span class="badge badge-info">{{ $upcoming_events }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.event.calendar') }}" class="action-btn">
                    <i class="fas fa-calendar-alt"></i> {{ trans_choice('module_calendar', 2) }}
                </a>
                @endcan
                @can('notice-view')
                <a href="{{ route('admin.notice.index') }}" class="action-btn">
                    <i class="fas fa-sticky-note"></i> {{ trans_choice('module_notice', 2) }}
                    @if($active_notices > 0)
                    <span class="badge badge-primary">{{ $active_notices }}</span>
                    @endif
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- Reports & Transcripts -->
            @canany(['transcript-view', 'report-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    {{ __('Reports & Transcripts') }}
                </div>
                @can('transcript-view')
                <a href="{{ route('admin.marksheet.semester') }}" class="action-btn">
                    <i class="fas fa-file-alt"></i> {{ __('Semester Marksheet') }}
                </a>
                <a href="{{ route('admin.marksheet.index') }}" class="action-btn">
                    <i class="fas fa-certificate"></i> {{ trans_choice('module_transcript', 2) }}
                </a>
                @endcan
                @can('report-view')
                <a href="{{ route('admin.report.student') }}" class="action-btn">
                    <i class="fas fa-chart-bar"></i> {{ trans_choice('module_student', 1) }} {{ __('report') }}
                </a>
                <a href="{{ route('admin.report.student-fees') }}" class="action-btn">
                    <i class="fas fa-file-invoice-dollar"></i> {{ __('Student Fees Report') }}
                </a>
                @endcan
            </div>
            @endcanany
            
            <!-- System Management -->
            @canany(['role-view', 'audit-log-view'])
            <div class="quick-actions-section">
                <div class="section-title">
                    <i class="fas fa-cogs"></i>
                    {{ __('System Management') }}
                </div>
                @can('role-view')
                <a href="{{ route('admin.role.index') }}" class="action-btn">
                    <i class="fas fa-user-shield"></i> {{ trans_choice('module_role_permission', 2) }}
                </a>
                @endcan
                @can('audit-log-view')
                <a href="{{ route('admin.audit-log.index') }}" class="action-btn">
                    <i class="fas fa-history"></i> {{ __('Audit Log') }}
                </a>
                @endcan
            </div>
            @endcanany
            
        </div>
        
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script>
    // Add smooth entrance animations
    $(document).ready(function() {
        $('.stat-card, .quick-actions-section').hide().each(function(i) {
            $(this).delay(50 * i).fadeIn(400);
        });
        
        // Manual toggle for schedule collapse (compatibility fix)
        $('[data-bs-toggle="collapse"]').on('click', function(e) {
            // Toggle chevron icon
            var icon = $(this).find('.fa-chevron-down, .fa-chevron-up');
            if (icon.hasClass('fa-chevron-down')) {
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            } else {
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            }
        });

        // Initialize DataTables for the breakdown tables
        $('#facultyTable').DataTable({
            "pageLength": 20,
            "lengthMenu": [[10, 20, 50, -1], [10, 20, 50, "All"]],
            "order": [[ 1, "desc" ]]
        });
        $('#departmentTable').DataTable({
            "pageLength": 20,
            "lengthMenu": [[10, 20, 50, -1], [10, 20, 50, "All"]],
            "order": [[ 1, "desc" ]]
        });
        $('#programTable').DataTable({
            "pageLength": 20,
            "lengthMenu": [[10, 20, 50, -1], [10, 20, 50, "All"]],
            "order": [[ 1, "desc" ]]
        });
    });
</script>
@endsection
