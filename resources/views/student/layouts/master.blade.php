<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    
     @include('student.layouts.common.header_script')

</head>

<body>

    @php
        // Load announcements for student portal
        if(!isset($announcements)){
            try{
                $today = now()->toDateString();
                $announcements = \App\Models\Web\Announcement::where('status', 1)
                    ->where(function($q) use ($today){
                        $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
                    })
                    ->where(function($q) use ($today){
                        $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
                    })
                    ->where(function($q){
                        $q->where('language_id', App\Models\Language::version()->id)->orWhereNull('language_id');
                    })
                    ->orderByDesc('start_date')
                    ->get();
            } catch (\Exception $e) {
                $announcements = collect();
            }
        }
    @endphp

    @if(isset($announcements) && $announcements->count())
    @php
        // Calculate dynamic animation duration based on number of announcements
        // Formula: 25 seconds base + 15 seconds per announcement (very slow, very readable)
        $announcementCount = $announcements->count();
        $animationDuration = 25 + ($announcementCount * 15);
        $mobileAnimationDuration = 22 + ($announcementCount * 12);
    @endphp
    <div class="site-announcement" style="position:fixed;top:0;left:0;right:0;background:#fff8e1;border-bottom:1px solid #eee;overflow:hidden;z-index:10000;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        <div class="container-fluid">
            <div class="announcement-ticker-wrapper" style="position:relative;padding:10px 0;">
                <div class="announcement-ticker" id="announcementTicker">
                    @foreach($announcements as $a)
                        <span class="announcement-item" style="display:inline-block;padding:0 40px;color:#333;font-weight:600;white-space:nowrap;">
                            {!! $a->message !!}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <style>
        .announcement-ticker-wrapper {
            overflow: hidden;
            width: 100%;
        }
        .announcement-ticker {
            display: inline-block;
            white-space: nowrap;
            animation: scroll-left {{ $animationDuration }}s linear infinite;
            animation-delay: 0s; /* Start immediately */
        }
        .announcement-ticker:hover {
            animation-play-state: paused;
        }
        @keyframes scroll-left {
            0% {
                transform: translateX(20%); /* Start even closer - minimal delay */
            }
            100% {
                transform: translateX(-100%);
            }
        }
        /* Adjust student portal header to start below announcement bar */
        .pcoded-header {
            top: 50px !important;
        }
        /* Adjust navbar to start below announcement */
        .pcoded-navbar {
            top: 50px !important;
        }
        /* Adjust main content area */
        .pcoded-main-container {
            margin-top: 106px !important; /* 56px header + 50px announcement */
        }
        @media (max-width: 768px) {
            .announcement-ticker {
                animation-duration: {{ $mobileAnimationDuration }}s;
            }
            .announcement-item {
                padding: 0 20px !important;
                font-size: 14px;
            }
            .pcoded-header {
                top: 45px !important;
            }
            .pcoded-navbar {
                top: 45px !important;
            }
            .pcoded-main-container {
                margin-top: 101px !important; /* 56px header + 45px announcement */
            }
        }
    </style>
    @endif

    @if(session()->has('impersonate_admin_id'))
    <div class="impersonate-banner" style="position:fixed;top:0;left:0;right:0;background:#343a40;color:#fff;text-align:center;padding:10px;z-index:99999;">
        You are currently impersonating a student.
        <a href="{{ route('student.leave-impersonation') }}" class="btn btn-sm btn-danger ml-3">Leave Impersonation Mode</a>
    </div>
    <style>
        .site-announcement { top: 50px !important; }
        /* Add some extra top padding when impersonating */
        .pcoded-header { top: 50px !important; }
        .pcoded-navbar { top: 50px !important; }
    </style>
    @endif

    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- [ navigation menu ] start -->
    <nav class="pcoded-navbar active-lightblue title-lightblue navbar-lightblue brand-lightblue navbar-image-4 menu-item-icon-style2 {{\Cookie::get('sidebar')}}">
        <div class="navbar-wrapper">
            <div class="navbar-brand header-logo">
                @if(isset($setting))
                @if(upload_exists('setting/'.$setting->logo_path))
                <a href="{{ route('student.dashboard.index') }}" class="b-brand">
                    <img src="{{ upload_asset('setting/'.$setting->logo_path) }}" alt="logo">
                </a>
                @endif
                @endif
                <a class="mobile-menu" id="mobile-collapse" href="#!"><span></span></a>
            </div>


            @if(Request::is('student*'))
            <!--- Sidemenu -->
            @include('student.layouts.inc.sidebar')
            <!-- End Sidebar -->
            @endif

        </div>
    </nav>
    <!-- [ navigation menu ] end -->

    <!-- [ Sidebar Overlay ] -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- [ Header ] start -->
    <header class="navbar pcoded-header navbar-expand-lg navbar-light headerpos-fixed header-lightblue">
        <div class="m-header">
            <a class="mobile-menu" id="mobile-collapse1" href="#!"><span></span></a>
            @if(isset($setting))
            @if(upload_exists('setting/'.$setting->logo_path))
            <a href="{{ route('student.dashboard.index') }}" class="b-brand">
                <div class="b-bg">
                    <img src="{{ upload_asset('setting/'.$setting->logo_path) }}" alt="logo" height="20">
                </div>
            </a>
            @endif
            @endif
        </div>
        <!-- mobile-header button hidden by CSS -->
        <a class="mobile-menu" id="mobile-header" href="#!">
            <i class="feather icon-more-horizontal"></i>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li><a href="#!" class="full-screen" onclick="javascript:toggleFullScreen()"><i class="feather icon-maximize"></i></a></li>
                <li>
                    <h4 class="topbar-title">{{ $setting->title ?? config('app.name') }}</h4>
                </li>
                @php
                    $currentSession = \App\Models\Session::where('current', 1)->where('status', 1)->first();
                    $currentSemesterTitle = null;
                    // If a student is logged in, try to get their current enroll -> semester
                    if(\Illuminate\Support\Facades\Auth::guard('student')->check()){
                        $student = \Illuminate\Support\Facades\Auth::guard('student')->user();
                        $currentEnroll = $student->currentEnroll ?? null;
                        if($currentEnroll && $currentEnroll->semester){
                            $currentSemesterTitle = $currentEnroll->semester->title ?? null;
                        }
                    }
                @endphp
                @if($currentSession)
                <li class="ms-3">
                    <div class="d-flex align-items-center">
                        <div class="d-flex align-items-center text-white">
                            <i class="fas fa-graduation-cap me-2"></i>
                            <div style="font-size: 14px; font-weight: 600;">
                                <span class="badge bg-success">{{ __('current_session') }}: {{ $currentSession->title }}</span>
                            </div>
                        </div>
                    </div>
                </li>
                @endif

                @if(!empty($currentSemesterTitle))
                <li class="ms-2">
                    <div class="d-flex align-items-center">
                        <div class="d-flex align-items-center text-white">
                            <i class="fas fa-layer-group me-2"></i>
                            <div style="font-size: 14px; font-weight: 600;">
                                <span class="badge bg-info">{{ __('current_semester') }}: {{ $currentSemesterTitle }}</span>
                            </div>
                        </div>
                    </div>
                </li>
                @endif
            </ul>

            <!-- [ Auth Nav ] start -->
            @auth
            <ul class="navbar-nav ms-auto">
                <!-- Program Switcher (Multi-Enrollment Students Only) -->
                @if(Auth::guard('student')->check())
                    @php
                        $currentStudent = Auth::guard('student')->user();
                        // Get ALL enrollments (active and inactive) so students can view past program data
                        $tempEnrollments = \App\Models\StudentEnroll::where('student_id', $currentStudent->id)
                            ->with(['program.degreeType', 'program.faculty', 'semester', 'session'])
                            ->orderBy('id', 'desc')
                            ->get();
                        
                        // Group by unique matricule and keep only the latest enrollment for each
                        $allEnrollments = $tempEnrollments->groupBy('matricule')->map(function($group) {
                            return $group->sortByDesc('id')->first(); // Latest enrollment for this matricule
                        })->values();
                        
                        $selectedEnrollmentId = session('selected_enrollment_id');
                        $currentEnrollment = $allEnrollments->firstWhere('id', $selectedEnrollmentId);
                        
                        // If selected enrollment is not in unique list, pick first unique
                        if (!$currentEnrollment && $allEnrollments->count() > 0) {
                            $currentEnrollment = $allEnrollments->first();
                            // Auto-set this as selected
                            session(['selected_enrollment_id' => $currentEnrollment->id]);
                            $selectedEnrollmentId = $currentEnrollment->id;
                        }
                    @endphp
                    
                    @if($allEnrollments->count() > 1 && $currentEnrollment)
                    <li>
                        <div class="dropdown program-switcher-dropdown">
                            <a href="#" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" 
                               style="padding: 6px 12px; background: rgba(255,255,255,0.2); border-radius: 20px; text-decoration: none; margin-right: 8px;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-graduation-cap text-white me-2"></i>
                                    <div style="text-align: left;">
                                        <div class="text-white" style="font-size: 11px; opacity: 0.9; line-height: 1.2;">Current Program</div>
                                        <div class="text-white" style="font-weight: 600; font-size: 13px; line-height: 1.2;">
                                            {{ $currentEnrollment->matricule }}
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down text-white ms-2" style="font-size: 10px;"></i>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right notification" style="min-width: 380px; max-width: 450px;">
                                <div class="noti-head" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px;">
                                    <h6 class="d-inline-block m-b-0 text-white">
                                        <i class="fas fa-exchange-alt me-2"></i>Switch Program
                                    </h6>
                                    <p class="mb-0" style="font-size: 12px; opacity: 0.9; margin-top: 5px;">
                                        You're enrolled in {{ $allEnrollments->count() }} programs
                                    </p>
                                </div>
                                <ul class="noti-body" style="max-height: 400px; overflow-y: auto;">
                                    @foreach($allEnrollments as $enrollment)
                                    <li class="notification program-switch-item @if($enrollment->id == $selectedEnrollmentId) active-program @endif" 
                                        style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: all 0.3s ease;"
                                        data-enrollment-id="{{ $enrollment->id }}">
                                        <div class="d-flex align-items-start">
                                            <div class="program-icon" style="width: 45px; height: 45px; border-radius: 10px; 
                                                background: linear-gradient(135deg, {{ $enrollment->program->academic_level == 'M' ? '#f093fb 0%, #f5576c 100%' : ($enrollment->program->academic_level == 'D' ? '#4facfe 0%, #00f2fe 100%' : '#43e97b 0%, #38f9d7 100%') }}); 
                                                display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0;">
                                                <i class="fas fa-graduation-cap text-white" style="font-size: 18px;"></i>
                                            </div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="badge" style="background: {{ $enrollment->program->academic_level == 'M' ? '#f5576c' : ($enrollment->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; 
                                                        color: white; font-size: 10px; padding: 3px 8px; border-radius: 10px; margin-right: 8px;">
                                                        {{ $enrollment->program->academic_level == 'A' ? 'Undergraduate' : ($enrollment->program->academic_level == 'M' ? 'Masters' : 'Doctoral') }}
                                                    </span>
                                                    @if($enrollment->status == '1')
                                                    <span class="badge bg-success" style="font-size: 9px; padding: 3px 6px; margin-right: 4px;">
                                                        <i class="fas fa-circle"></i> Active
                                                    </span>
                                                    @else
                                                    <span class="badge bg-secondary" style="font-size: 9px; padding: 3px 6px; margin-right: 4px;">
                                                        <i class="fas fa-archive"></i> Inactive
                                                    </span>
                                                    @endif
                                                    @if($enrollment->id == $selectedEnrollmentId)
                                                    <span class="badge bg-primary" style="font-size: 9px; padding: 3px 6px;">
                                                        <i class="fas fa-check"></i> Selected
                                                    </span>
                                                    @endif
                                                </div>
                                                <h6 class="mb-1" style="font-size: 13px; font-weight: 600; color: #333; line-height: 1.3;">
                                                    {{ Str::limit($enrollment->program->title, 40) }}
                                                </h6>
                                                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                                    <i class="fas fa-id-card" style="width: 16px;"></i>
                                                    <strong>{{ $enrollment->matricule }}</strong>
                                                </div>
                                                <div style="font-size: 11px; color: #888;">
                                                    <i class="fas fa-building" style="width: 16px;"></i>
                                                    {{ $enrollment->program->faculty->title ?? 'N/A' }}
                                                </div>
                                                <div style="font-size: 11px; color: #888;">
                                                    <i class="fas fa-calendar" style="width: 16px;"></i>
                                                    {{ $enrollment->session->title ?? 'N/A' }} • 
                                                    {{ $enrollment->semester->title ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </li>
                    
                    <style>
                        .program-switch-item:hover {
                            background-color: #f8f9fa !important;
                            transform: translateX(5px);
                        }
                        .program-switch-item.active-program {
                            background-color: #e8f5e9 !important;
                            border-left: 4px solid #4caf50 !important;
                        }
                        .program-switcher-dropdown .dropdown-menu {
                            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
                            border: none;
                            border-radius: 10px;
                            overflow: hidden;
                        }
                    </style>
                    
                    <script>
                        // Wait for jQuery to be loaded before initializing
                        (function initProgramSwitcher() {
                            if (typeof jQuery === 'undefined') {
                                console.log('jQuery not loaded yet, waiting...');
                                setTimeout(initProgramSwitcher, 100);
                                return;
                            }
                            
                            console.log('Program switcher script loaded - jQuery available');
                            
                            jQuery(document).ready(function($) {
                                console.log('Document ready');
                                console.log('Found program switch items:', $('.program-switch-item').length);
                                
                                // Use event delegation to ensure it works even if DOM changes
                                $(document).on('click', '.program-switch-item', function(e) {
                                    console.log('Click detected on program switch item');
                                    e.preventDefault();
                                    e.stopPropagation();
                                    
                                    var $item = $(this);
                                    var enrollmentId = $item.data('enrollment-id');
                                    var currentEnrollmentId = {{ $selectedEnrollmentId ?? 'null' }};
                                    
                                    console.log('=== Program Switch Debug ===');
                                    console.log('Switching to enrollment:', enrollmentId);
                                    console.log('Current enrollment:', currentEnrollmentId);
                                    console.log('URL:', '{{ route("student.switch-program") }}');
                                    
                                    if(enrollmentId == currentEnrollmentId) {
                                        console.log('Already selected - no switch needed');
                                        return;
                                    }
                                    
                                    // Show loading
                                    $item.css('opacity', '0.6');
                                    console.log('Starting AJAX request...');
                                    
                                    $.ajax({
                                        url: '{{ route("student.switch-program") }}',
                                        method: 'POST',
                                        data: {
                                            _token: '{{ csrf_token() }}',
                                            enrollment_id: enrollmentId
                                        },
                                        success: function(response) {
                                            console.log('Switch successful:', response);
                                            console.log('Redirecting to dashboard...');
                                            // Redirect to dashboard instead of reloading current page
                                            // This ensures proper middleware evaluation for the new program
                                            window.location.href = '{{ route("student.dashboard.index") }}';
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('=== Switch Error ===');
                                            console.error('Status:', status);
                                            console.error('Error:', error);
                                            console.error('Response:', xhr.responseText);
                                            console.error('Status Code:', xhr.status);
                                            
                                            var errorMsg = 'Error switching program. ';
                                            if(xhr.responseJSON && xhr.responseJSON.message) {
                                                errorMsg += xhr.responseJSON.message;
                                            } else {
                                                errorMsg += 'Status: ' + xhr.status;
                                            }
                                            
                                            alert(errorMsg);
                                            $item.css('opacity', '1');
                                        }
                                    });
                                });
                            });
                        })();
                    </script>
                    @endif
                @endif

                <!-- Language -->
                <li>
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                            @php 
                            $version = App\Models\Language::version(); 
                            @endphp
                            <i class="fas fa-language"></i> {{ $version->name }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-right notification">
                            <div class="noti-head">
                                <h6 class="d-inline-block m-b-0">{{ trans_choice('module_language', 2) }}</h6>
                            </div>
                            <ul class="noti-body">
                                @foreach($user_languages as $user_language)
                                <li class="notification @if(\Session()->get('locale') == $user_language->code) active @endif">
                                    <a class="language" href="{{ route('version', $user_language->code) }}">{{ $user_language->name }}</a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </li>

                <!-- Progression Button -->
                <li id="progression-button-container" style="display: none;">
                    <a href="#" id="progression-button" data-bs-toggle="modal" data-bs-target="#manualProgressionModal" class="btn btn-sm btn-outline-info position-relative">
                        <i class="fas fa-tasks"></i> {{ __('Progression Status') }}
                    </a>
                </li>

                <!-- Notification -->
                <li>
                    <div class="dropdown">
                        <a class="dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="icon feather icon-bell">
                            @php
                                $unread_count = Auth::guard('student')->user()->unreadNotifications->count();
                            @endphp
                            @if($unread_count > 0)
                            <span class="notification-active"></span>
                            <span class="badge bg-danger notification-badge">{{ $unread_count }}</span>
                            @endif
                            </i>
                        </a>
                        @php
                            $unread_notifications = Auth::guard('student')->user()->unreadNotifications;
                        @endphp
                        <div class="dropdown-menu dropdown-menu-right notification">
                            <div class="noti-head">
                                <h6 class="d-inline-block m-b-0">{{ trans_choice('module_notification', 2) }} @if($unread_count > 0)<span class="badge bg-danger">{{ $unread_count }}</span>@endif</h6>
                            </div>
                            <ul class="noti-body">
                                @forelse($unread_notifications as $key => $notification)
                                @if($key < 10)
                                @php
                                    $notification_link = 'student.dashboard.index';
                                    $notification_type = '';
                                    $notification_icon = 'fa-arrow-circle-right';
                                    $notification_has_id = true;
                                    
                                    if(isset($notification->data['type'])) {
                                        if($notification->data['type'] == 'content') {
                                            $notification_link = 'student.download.show';
                                            $notification_type = trans_choice('module_content', 1);
                                        }
                                        elseif($notification->data['type'] == 'notice') {
                                            $notification_link = 'student.notice.show';
                                            $notification_type = trans_choice('module_notice', 1);
                                        }
                                        elseif($notification->data['type'] == 'assignment'){
                                            $notification_link = 'student.assignment.index';
                                            $notification_type = trans_choice('module_assignment', 1);
                                            $notification_has_id = false;
                                        }
                                        elseif($notification->data['type'] == 'exam_results'){
                                            $notification_link = 'student.transcript.index';
                                            $notification_type = __('Exam Results');
                                            $notification_icon = 'fa-graduation-cap';
                                            $notification_has_id = false;
                                        }
                                    }
                                @endphp
                                <li class="notification">
                                    @if(!$notification_has_id)
                                    <a class="media" href="{{ route($notification_link) }}">
                                    @else
                                    <a class="media" href="{{ route($notification_link, $notification->data['id'] ?? 0) }}">
                                    @endif
                                        <div class="media-body">
                                            <p><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><span class="n-time text-muted"><i class="icon feather icon-clock m-r-10"></i>{{ $notification->created_at->diffForHumans() }}</span></p>
                                            <p><i class="fas {{ $notification_icon }}"></i> {{ $notification_type }}</p>
                                            @if(isset($notification->data['message']) && !empty($notification->data['message']))
                                            <p class="text-muted small mb-0" style="font-size: 11px; line-height: 1.3;">{{ Str::limit($notification->data['message'], 80) }}</p>
                                            @endif
                                        </div>
                                    </a>
                                </li>
                                @endif
                                @empty
                                <li class="notification">{{ __('status_no_notification') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </li>

                <!-- Profile -->
                <li>
                    <div class="dropdown drp-user">
                        <a href="#" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" style="padding: 2px 8px; background: rgba(255,255,255,0.15); border-radius: 18px; text-decoration: none;">
                            <span class="text-white me-2" style="font-weight: 500; font-size: 13px;">{{ Auth::guard('student')->user()->first_name }} {{ Auth::guard('student')->user()->last_name }}</span>
                            <div style="width: 26px; height: 26px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-circle" style="color: #182b49; font-size: 16px;"></i>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right profile-notification">
                            <div class="pro-head">
                                @if(Auth::guard('student')->user()->photo)
                                <img src="{{ asset('uploads/student/'.Auth::guard('student')->user()->photo) }}" class="img-radius" alt="User Profile" @if(Auth::guard('student')->user()->gender == 1) onerror="this.src='{{ asset('dashboard/images/user/avatar-2.jpg') }}';" @else  onerror="this.src='{{ asset('dashboard/images/user/avatar-1.jpg') }}';" @endif>
                                @else
                                <img src="@if(Auth::guard('student')->user()->gender == 1) {{ asset('dashboard/images/user/avatar-2.jpg') }} @else {{ asset('dashboard/images/user/avatar-1.jpg') }} @endif" class="img-radius" alt="User Profile">
                                @endif
                                <span>{{ Auth::guard('student')->user()->first_name }} {{ Auth::guard('student')->user()->last_name }}</span>

                                <a href="javascript:void(0);" class="dud-logout" href="{{ route('student.logout') }}"
                                   onclick="event.preventDefault();
                                                 document.getElementById('logout-form').submit();">
                                    
                                    <i class="feather icon-log-out"></i>
                                </a>

                                <form id="logout-form" action="{{ route('student.logout') }}" method="POST">
                                    @csrf
                                </form>

                            </div>
                            <ul class="pro-body">
                                <li><a href="{{ route('student.profile.index') }}" class="dropdown-item"><i class="feather icon-user"></i> {{ trans_choice('module_profile', 2) }}</a></li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
            @endauth
            <!-- [ Auth Nav ] end -->

        </div>
    </header>
    <!-- [ Header ] end -->


    <!-- [ chat user list ] start -->
    <section class="header-user-list">
        <div class="h-list-header">
            <div class="input-group">
                <input type="text" id="search-friends" class="form-control" placeholder="Search Friend . . .">
            </div>
        </div>
        <div class="h-list-body">
            <a href="#!" class="h-close-text"><i class="feather icon-chevrons-right"></i></a>
            <div class="main-friend-cont scroll-div">
                <div class="main-friend-list">

                </div>
            </div>
        </div>
    </section>
    <!-- [ chat user list ] end -->

    <!-- [ chat message ] start -->
    <section class="header-chat">
        <div class="h-list-header">
            <h6></h6>
            <a href="#!" class="h-back-user-list"><i class="feather icon-chevron-left"></i></a>
        </div>
        <div class="h-list-body">
            <div class="main-chat-cont scroll-div">
                <div class="main-friend-chat">
                    <div class="media chat-messages">
                        
                        <div class="media-body chat-menu-content">
                            
                        </div>
                    </div>
                    <div class="media chat-messages">
                        <div class="media-body chat-menu-reply">
                            
                        </div>
                    </div>
                    <div class="media chat-messages">
                        
                        <div class="media-body chat-menu-content">
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- [ chat message ] end -->


    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    
                    <!-- start page title -->
                    <!-- Include page breadcrumb -->
                    @include('student.layouts.inc.breadcrumb')
                    <!-- end page title -->
                    

                    <!-- Start Content-->
                    @yield('content')
                    <!-- End Content-->

                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Semester Progression Modal -->
    @if(session()->has('progression_modal'))
    @php
        $progressionData = session('progression_modal');
        $isResit = $progressionData['is_resit'] ?? false;
        session()->forget('progression_modal'); // Clear after reading
    @endphp
    <div class="modal fade" id="progressionModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content" style="border: none; border-radius: 15px; overflow: hidden;">
                <!-- Animated Header -->
                @if($isResit)
                <!-- RESIT SEMESTER HEADER -->
                <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; padding: 30px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); animation: rotate 20s linear infinite;"></div>
                    <div style="position: relative; z-index: 1; width: 100%; text-align: center;">
                        <div style="font-size: 60px; animation: bounce 1s ease-in-out 2;">📚</div>
                        <h3 class="modal-title text-white" style="font-weight: 700; margin-top: 15px; font-size: 28px;">
                            Resit Semester Enrollment
                        </h3>
                        <p class="text-white mb-0" style="opacity: 0.95; font-size: 16px; margin-top: 10px;">
                            You've Been Enrolled to Retake Your Courses
                        </p>
                    </div>
                </div>
                @else
                <!-- REGULAR SEMESTER HEADER -->
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 30px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); animation: rotate 20s linear infinite;"></div>
                    <div style="position: relative; z-index: 1; width: 100%; text-align: center;">
                        <div style="font-size: 60px; animation: bounce 1s ease-in-out 2;">🎉</div>
                        <h3 class="modal-title text-white" style="font-weight: 700; margin-top: 15px; font-size: 28px;">
                            Congratulations!
                        </h3>
                        <p class="text-white mb-0" style="opacity: 0.95; font-size: 16px; margin-top: 10px;">
                            You've Been Progressed to a New Semester
                        </p>
                    </div>
                </div>
                @endif
                
                <!-- Modal Body -->
                <div class="modal-body" style="padding: 40px;">
                    @if($isResit)
                    <!-- RESIT SEMESTER SPECIFIC CONTENT -->
                    <!-- Courses to Retake -->
                    <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 25px; margin-bottom: 25px;">
                        <h5 style="color: #856404; font-weight: 700; margin-bottom: 15px; display: flex; align-items-center;">
                            <i class="fas fa-redo-alt" style="margin-right: 10px;"></i>
                            Courses You'll Be Retaking
                        </h5>
                        <p style="color: #856404; margin-bottom: 15px;">
                            You have been automatically enrolled in the following courses for the resit semester:
                        </p>
                        @if(isset($progressionData['scheduled_courses']))
                        <ul style="margin: 0; padding-left: 25px; color: #856404; line-height: 1.8;">
                            @foreach($progressionData['scheduled_courses'] as $course)
                            <li><strong>{{ $course['subject_code'] }}</strong> - {{ $course['subject_title'] }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-3 p-3" style="background: rgba(255,255,255,0.7); border-radius: 8px;">
                            <strong><i class="fas fa-info-circle me-1"></i> Total Courses:</strong> {{ $progressionData['courses_count'] ?? count($progressionData['scheduled_courses']) }}
                        </div>
                        @endif
                    </div>
                    
                    <!-- Progress Bar Animation -->
                    <div style="margin-bottom: 30px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div style="text-align: center; flex: 1;">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: #e0e0e0; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                    ✓
                                </div>
                                <p style="font-size: 12px; font-weight: 600; color: #666; margin: 0;">{{ $progressionData['old_semester'] }}</p>
                                <p style="font-size: 11px; color: #999; margin: 0;">Previous</p>
                            </div>
                            <div style="flex: 1; height: 4px; background: linear-gradient(to right, #e0e0e0 0%, #f5576c 100%); position: relative; margin: 0 10px;">
                                <div style="position: absolute; right: -10px; top: -5px; width: 0; height: 0; border-left: 10px solid #f5576c; border-top: 7px solid transparent; border-bottom: 7px solid transparent;"></div>
                            </div>
                            <div style="text-align: center; flex: 1;">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #f093fb, #f5576c); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; animation: pulse 2s infinite; box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);">
                                    📚
                                </div>
                                <p style="font-size: 12px; font-weight: 600; color: #f5576c; margin: 0;">{{ $progressionData['new_semester'] }}</p>
                                <p style="font-size: 11px; color: #999; margin: 0;">Resit Semester</p>
                            </div>
                        </div>
                    </div>
                    @else
                    <!-- REGULAR SEMESTER CONTENT -->
                    <!-- Progress Bar Animation -->
                    <div style="margin-bottom: 30px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div style="text-align: center; flex: 1;">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: #e0e0e0; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                    ✓
                                </div>
                                <p style="font-size: 12px; font-weight: 600; color: #666; margin: 0;">{{ $progressionData['old_semester'] }}</p>
                                <p style="font-size: 11px; color: #999; margin: 0;">Completed</p>
                            </div>
                            <div style="flex: 1; height: 4px; background: linear-gradient(to right, #e0e0e0 0%, #667eea 100%); position: relative; margin: 0 10px;">
                                <div style="position: absolute; right: -10px; top: -5px; width: 0; height: 0; border-left: 10px solid #667eea; border-top: 7px solid transparent; border-bottom: 7px solid transparent;"></div>
                            </div>
                            <div style="text-align: center; flex: 1;">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; animation: pulse 2s infinite; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                                    🎯
                                </div>
                                <p style="font-size: 12px; font-weight: 600; color: #667eea; margin: 0;">{{ $progressionData['new_semester'] }}</p>
                                <p style="font-size: 11px; color: #999; margin: 0;">New Journey</p>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Semester Information Card -->
                    <div style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); border-radius: 12px; padding: 25px; margin-bottom: 25px;">
                        <h5 style="color: #333; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center;">
                            <i class="fas fa-info-circle" style="margin-right: 10px; color: #667eea;"></i>
                            Your New Semester Details
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                        <i class="fas fa-graduation-cap" style="color: #667eea;"></i>
                                    </div>
                                    <div>
                                        <p style="margin: 0; font-size: 11px; color: #666; font-weight: 600;">PROGRAM</p>
                                        <p style="margin: 0; font-size: 14px; color: #333; font-weight: 700;">{{ $progressionData['program'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                        <i class="fas fa-calendar-alt" style="color: #667eea;"></i>
                                    </div>
                                    <div>
                                        <p style="margin: 0; font-size: 11px; color: #666; font-weight: 600;">SESSION</p>
                                        <p style="margin: 0; font-size: 14px; color: #333; font-weight: 700;">{{ $progressionData['session'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                        <i class="fas fa-book-open" style="color: #667eea;"></i>
                                    </div>
                                    <div>
                                        <p style="margin: 0; font-size: 11px; color: #666; font-weight: 600;">SEMESTER</p>
                                        <p style="margin: 0; font-size: 14px; color: #333; font-weight: 700;">{{ $progressionData['new_semester'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                        <i class="fas fa-id-card" style="color: #667eea;"></i>
                                    </div>
                                    <div>
                                        <p style="margin: 0; font-size: 11px; color: #666; font-weight: 600;">MATRICULE</p>
                                        <p style="margin: 0; font-size: 14px; color: #333; font-weight: 700;">{{ $progressionData['matricule'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @if($isResit)
                    <!-- RESIT SEMESTER TIPS -->
                    <div style="background: #fff; border: 2px solid #f5576c; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                        <h5 style="color: #c3195d; font-weight: 700; margin-bottom: 15px; display: flex; align-items: center;">
                            <i class="fas fa-bullseye" style="margin-right: 10px; color: #f5576c;"></i>
                            Focus Areas for Resit Success
                        </h5>
                        <ul style="margin: 0; padding-left: 25px; line-height: 1.8; color: #555;">
                            <li><strong>Learn from Previous Attempts:</strong> Review where you struggled before and focus on those areas</li>
                            <li><strong>Attend ALL Classes:</strong> 100% attendance is crucial during resit semesters</li>
                            <li><strong>Study Strategically:</strong> Focus on understanding concepts rather than memorization</li>
                            <li><strong>Get Extra Help:</strong> Don't hesitate to approach lecturers during office hours</li>
                            <li><strong>Join Study Groups:</strong> Collaborative learning can help reinforce difficult concepts</li>
                            <li><strong>Practice Past Questions:</strong> Familiarize yourself with exam patterns and question types</li>
                        </ul>
                    </div>
                    
                    <!-- Resit Motivational Quote -->
                    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 20px;">
                        <p style="color: white; font-size: 18px; font-weight: 600; margin: 0; font-style: italic; line-height: 1.6;">
                            "A setback is a setup for a comeback. This is your opportunity to prove yourself!"
                        </p>
                        <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 10px; margin-bottom: 0;">
                            Give it your best shot! You can do this! 💪📖
                        </p>
                    </div>
                    @else
                    <!-- REGULAR SEMESTER TIPS -->
                    <div style="background: #fff; border: 2px solid #f0f0f0; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                        <h5 style="color: #333; font-weight: 700; margin-bottom: 15px; display: flex; align-items: center;">
                            <i class="fas fa-lightbulb" style="margin-right: 10px; color: #ffc107;"></i>
                            Tips for Success This Semester
                        </h5>
                        <ul style="margin: 0; padding-left: 25px; line-height: 1.8; color: #555;">
                            <li><strong>Register Your Courses:</strong> Visit the Course Registration page to select your courses for this semester</li>
                            <li><strong>Maintain Good Attendance:</strong> Aim for at least 75% attendance in all classes</li>
                            <li><strong>Stay Consistent:</strong> Regular study and timely completion of assignments lead to better results</li>
                            <li><strong>Seek Help Early:</strong> Don't hesitate to ask lecturers or classmates if you need clarification</li>
                            <li><strong>Track Your Progress:</strong> Monitor your CA scores and prepare well for final exams</li>
                        </ul>
                    </div>
                    
                    <!-- Motivational Quote -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 20px;">
                        <p style="color: white; font-size: 18px; font-weight: 600; margin: 0; font-style: italic; line-height: 1.6;">
                            "Success is the sum of small efforts repeated day in and day out."
                        </p>
                        <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 10px; margin-bottom: 0;">
                            You've got this! Make this semester your best one yet! 💪
                        </p>
                    </div>
                    @endif
                    
                    <!-- Academic Requirements Alert -->
                    <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 20px;">
                        <h6 style="color: #856404; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center;">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
                            Remember to Maintain:
                        </h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div style="text-align: center;">
                                    <div style="font-size: 32px; font-weight: 700; color: #856404;">≥ 2.0</div>
                                    <p style="margin: 0; font-size: 12px; color: #856404; font-weight: 600;">Minimum GPA</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="text-align: center;">
                                    <div style="font-size: 32px; font-weight: 700; color: #856404;">≥ 75%</div>
                                    <p style="margin: 0; font-size: 12px; color: #856404; font-weight: 600;">Attendance</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="text-align: center;">
                                    <div style="font-size: 32px; font-weight: 700; color: #856404;">≥ 50%</div>
                                    <p style="margin: 0; font-size: 12px; color: #856404; font-weight: 600;">Pass Mark</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="modal-footer" style="border-top: 2px solid #f0f0f0; padding: 20px 40px; background: #fafafa;">
                    <a href="{{ route('student.course-registration.index') }}" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; padding: 12px 30px; font-weight: 600; border-radius: 8px;">
                        <i class="fas fa-clipboard-list me-2"></i> Register Courses Now
                    </a>
                    <button type="button" class="btn btn-light btn-lg" data-dismiss="modal" style="padding: 12px 30px; font-weight: 600; border-radius: 8px; border: 2px solid #e0e0e0;">
                        <i class="fas fa-check me-2"></i> I'll Do It Later
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Styles and Animations -->
    <style>
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        #progressionModal .modal-dialog {
            animation: slideInDown 0.5s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
    
    <!-- Auto-show Modal Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('#progressionModal').modal('show');
            
            // Add confetti effect
            if (typeof confetti !== 'undefined') {
                setTimeout(function() {
                    confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                }, 300);
            }
        });
    </script>
    @endif
    <!-- End Semester Progression Modal -->

    @if(!session()->has('progression_modal'))
    <!-- Manual Progression Modal (only show if no session modal) -->
    <div class="modal fade" id="manualProgressionModal" tabindex="-1" aria-labelledby="manualProgressionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white" id="progressionModalHeader">
                    <h5 class="modal-title" id="manualProgressionModalLabel">
                        <i class="fas fa-graduation-cap"></i> {{ __('Semester Progression') }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="progressionModalBody">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('Loading...') }}</span>
                        </div>
                        <p class="mt-3">{{ __('Loading progression details...') }}</p>
                    </div>
                </div>
                <div class="modal-footer" id="progressionModalFooter" style="display: none;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                    </button>
                    <button type="button" class="btn btn-success" id="proceedProgressionBtn">
                        <i class="fas fa-arrow-circle-right"></i> {{ __('Proceed with Progression') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Progression JavaScript -->
    <style>
        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }
        }
        
        #progression-button-container {
            margin: 0 10px;
        }
        
        #progression-button {
            animation: gentle-bounce 3s ease-in-out infinite;
        }
        
        @keyframes gentle-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
    </style>

    @include('student.layouts.common.footer_script')

    <!-- Progression Eligibility Check (After jQuery loads) -->
    <script>
    $(document).ready(function() {
        let eligibilityData = null;

        // Check eligibility on page load (determines button styling)
        function checkProgressionEligibility() {
            console.log('[Progression] Checking eligibility...');
            
            $.ajax({
                url: '{{ route("student.progression.check") }}',
                method: 'GET',
                cache: false,
                headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' },
                success: function(response) {
                    console.log('[Progression] Response:', JSON.stringify(response));
                    eligibilityData = response;
                    
                    if (response.eligible === true) {
                        console.log('[Progression] Student IS eligible. Showing green button.');
                        $('#progression-button')
                            .removeClass('btn-outline-info')
                            .addClass('btn-success')
                            .html('<i class="fas fa-arrow-circle-up"></i> {{ __("Ready to Progress") }} <span class="position-absolute top-0 start-100 translate-middle p-2 bg-danger border border-light rounded-circle pulse-animation"><span class="visually-hidden">New</span></span>');
                    } else {
                        console.log('[Progression] Student NOT eligible. Reason: ' + (response.message || 'N/A'));
                        $('#progression-button')
                            .removeClass('btn-success')
                            .addClass('btn-outline-info')
                            .html('<i class="fas fa-tasks"></i> {{ __("Progression Status") }}');
                    }
                    
                    // Always show the button
                    $('#progression-button-container').fadeIn();
                },
                error: function(xhr) {
                    console.error('[Progression] AJAX failed:', xhr.status, xhr.responseText);
                    $('#progression-button')
                        .removeClass('btn-success')
                        .addClass('btn-outline-secondary')
                        .html('<i class="fas fa-tasks"></i> {{ __("Progression Status") }}');
                    $('#progression-button-container').fadeIn();
                }
            });
        }

        // Reset modal back to loading spinner
        function resetModalToLoading() {
            $('#progressionModalHeader').removeClass('bg-info bg-warning').addClass('bg-success');
            $('#progressionModalBody').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading...') }}</span>
                    </div>
                    <p class="mt-3">{{ __('Loading progression details...') }}</p>
                </div>
            `);
            $('#progressionModalFooter').hide();
        }

        // Show an error message inside the modal
        function showModalError(message) {
            $('#progressionModalHeader').removeClass('bg-success bg-info').addClass('bg-warning');
            $('#progressionModalBody').html(`
                <div class="alert alert-warning mb-0">
                    <h5><i class="fas fa-exclamation-triangle"></i> {{ __('Error') }}</h5>
                    <p class="mb-0">${message}</p>
                </div>
            `);
            $('#progressionModalFooter').hide();
        }

        // Build modal content for eligible student
        function buildEligibleModal(response) {
            const summary = response.summary;
            if (!summary) {
                showModalError('{{ __("Progression data is incomplete. Please try again.") }}');
                return;
            }

            $('#progressionModalHeader').removeClass('bg-info bg-warning').addClass('bg-success');

            let bodyHtml = `
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> ${summary.message || '{{ __("You are eligible for progression!") }}'}</h5>
                </div>

                ${response.program_name ? `
                <div class="alert alert-info mb-3">
                    <i class="fas fa-graduation-cap"></i> <strong>{{ __('Program') }}:</strong> ${response.program_name}
                </div>
                ` : ''}

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="card-title text-primary">{{ __('Current Semester') }}</h6>
                                <p class="mb-0"><strong>${summary.current_semester || 'N/A'}</strong></p>
                                <small class="text-muted">${summary.current_session || ''}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6 class="card-title text-success">{{ __('Progress To') }}</h6>
                                <p class="mb-0"><strong>${response.target_semester_title || 'N/A'}</strong></p>
                                <small class="text-muted">${summary.progression_type || ''}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Academic stats for regular progression
            if (response.type === 'regular') {
                bodyHtml += `
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-primary mb-0">${summary.semester_gpa || '--'}</h3>
                                    <small class="text-muted">{{ __('Semester GPA') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-success mb-0">${summary.credits_earned || '--'}</h3>
                                    <small class="text-muted">{{ __('Credits Earned') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-info mb-0">${summary.credits_attempted || '--'}</h3>
                                    <small class="text-muted">{{ __('Credits Attempted') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                if (summary.courses && summary.courses.length > 0) {
                    bodyHtml += `
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">{{ __('Semester Courses') }}</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Course') }}</th>
                                            <th class="text-center">{{ __('Grade') }}</th>
                                            <th class="text-center">{{ __('Credits') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;

                    summary.courses.forEach(function(course) {
                        const gradeClass = parseFloat(course.gpa) >= 2.0 ? 'text-success' : 'text-danger';
                        bodyHtml += `
                            <tr>
                                <td>${course.course_name}</td>
                                <td class="text-center ${gradeClass}"><strong>${course.grade || 'N/A'}</strong></td>
                                <td class="text-center">${course.credits}</td>
                            </tr>
                        `;
                    });

                    bodyHtml += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }
            }

            // Resit semester: show scheduled courses
            if (response.type === 'resit' && summary.scheduled_courses && summary.scheduled_courses.length > 0) {
                bodyHtml += `
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-redo-alt"></i> {{ __('Courses Scheduled for Resit') }}</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>{{ __('Course Code') }}</th><th>{{ __('Course Title') }}</th></tr></thead>
                                <tbody>
                `;
                summary.scheduled_courses.forEach(function(c) {
                    bodyHtml += `<tr><td><strong>${c.subject_code || ''}</strong></td><td>${c.subject_title || ''}</td></tr>`;
                });
                bodyHtml += `</tbody></table></div></div>`;
            }

            // Requirements list
            if (summary.requirements && summary.requirements.length > 0) {
                bodyHtml += `
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">{{ __('Requirements Met') }}</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                `;

                summary.requirements.forEach(function(req) {
                    bodyHtml += `<li><i class="fas fa-check-circle text-success"></i> ${req}</li>`;
                });

                bodyHtml += `
                            </ul>
                        </div>
                    </div>
                `;
            }

            // Carry-over courses notice for eligible students
            const carryOverCourses = response.carry_over_courses || (summary.carry_over_courses || []);
            if (carryOverCourses.length > 0) {
                // Collect unique semester type labels
                const semTypes = [...new Set(carryOverCourses.map(c => c.semester_type_label).filter(Boolean))];
                const semTypesText = semTypes.join(' {{ __("and") }} ');
                bodyHtml += `
                    <div class="card mt-3 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-history"></i> {{ __('Carry-Over Courses') }}
                                <span class="badge bg-danger ms-2">${carryOverCourses.length}</span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-2">
                                <i class="fas fa-info-circle"></i>
                                {{ __('These courses were failed in previous semesters and must be re-registered in the next') }}
                                <strong>${semTypesText}</strong>.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Course') }}</th>
                                            <th class="text-center">{{ __('Credits') }}</th>
                                            <th class="text-center">{{ __('Score') }}</th>
                                            <th>{{ __('From') }}</th>
                                            <th>{{ __('Re-register In') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                carryOverCourses.forEach(function(co) {
                    bodyHtml += `
                        <tr>
                            <td><strong>${co.subject_code || ''}</strong> - ${co.subject_title || ''}</td>
                            <td class="text-center">${co.credit_hours || '--'}</td>
                            <td class="text-center text-danger"><strong>${co.best_marks || 0}%</strong></td>
                            <td><small>${co.from_semester || ''}</small></td>
                            <td><span class="badge bg-info">${co.semester_type_label || ''}</span></td>
                        </tr>
                    `;
                });
                bodyHtml += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            }

            $('#progressionModalBody').html(bodyHtml);
            $('#progressionModalFooter').show();
            // Update stored data for the proceed button
            eligibilityData = response;
        }

        // Build modal content for NOT eligible student
        function buildNotEligibleModal(response) {
            $('#progressionModalHeader').removeClass('bg-success bg-warning').addClass('bg-info');

            const details = response.details || {};
            const message = response.message || '{{ __("You are not yet eligible for semester progression.") }}';
            const programName = details.program_name || null;
            const currentSemester = details.current_semester || null;
            const currentSession = details.current_session || null;
            const regularCheck = details.regular_check || {};
            const resitCheck = details.resit_check || {};
            const regularReason = regularCheck.reason || null;
            const unresolvedCourses = resitCheck.unresolved_courses || [];

            let bodyHtml = `
                <div class="alert alert-info">
                    <h5 class="mb-1"><i class="fas fa-info-circle"></i> {{ __('Progression Status') }}</h5>
                    <p class="mb-0">${message}</p>
                </div>
            `;

            // Program and semester context
            if (programName || currentSemester) {
                bodyHtml += `
                    <div class="card border-info mb-3">
                        <div class="card-body py-2">
                            <div class="row">
                                ${programName ? `
                                <div class="col-md-4">
                                    <small class="text-muted d-block">{{ __('Program') }}</small>
                                    <strong>${programName}</strong>
                                </div>` : ''}
                                ${currentSemester ? `
                                <div class="col-md-4">
                                    <small class="text-muted d-block">{{ __('Current Semester') }}</small>
                                    <strong>${currentSemester}</strong>
                                </div>` : ''}
                                ${currentSession ? `
                                <div class="col-md-4">
                                    <small class="text-muted d-block">{{ __('Session') }}</small>
                                    <strong>${currentSession}</strong>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            // Requirements checklist
            const marksPublished = !regularReason || !regularReason.includes('not been published');
            const allPassed = !regularReason || !regularReason.includes('failed');
            const noPendingResit = !regularReason || !regularReason.includes('pending resit');
            const nextSemesterAvailable = !regularReason || (!regularReason.includes('No next semester') && !regularReason.includes('not configured'));

            const requirements = [
                { met: marksPublished, text: '{{ __("All course marks have been published") }}' },
                { met: allPassed, text: '{{ __("All courses passed (or failed courses resolved via resit)") }}' },
                { met: noPendingResit, text: '{{ __("No pending/active resit requests") }}' },
                { met: nextSemesterAvailable, text: '{{ __("Next semester is available and configured") }}' },
            ];

            bodyHtml += `
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-clipboard-list"></i> {{ __('Progression Requirements') }}</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
            `;

            requirements.forEach(function(req) {
                const icon = req.met
                    ? '<i class="fas fa-check-circle text-success me-2"></i>'
                    : '<i class="fas fa-times-circle text-danger me-2"></i>';
                bodyHtml += `<li class="mb-2">${icon} ${req.text}</li>`;
            });

            bodyHtml += `</ul></div></div>`;

            // Courses requiring action (unresolved resit courses)
            if (unresolvedCourses.length > 0) {
                bodyHtml += `
                    <div class="card mb-3 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> {{ __('Courses Requiring Action') }}</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>{{ __('Course') }}</th><th class="text-center">{{ __('Status') }}</th></tr></thead>
                                <tbody>
                `;

                unresolvedCourses.forEach(function(course) {
                    let statusBadge = '';
                    if (course.status === 'no_request') {
                        statusBadge = '<span class="badge bg-danger">{{ __("No Resit Request") }}</span>';
                    } else if (course.status === 'pending_payment') {
                        statusBadge = '<span class="badge bg-warning text-dark">{{ __("Pending Payment") }}</span>';
                    } else {
                        statusBadge = '<span class="badge bg-secondary">' + (course.workflow_state || course.status || 'Unknown') + '</span>';
                    }
                    bodyHtml += `<tr><td><strong>${course.subject_code || ''}</strong> - ${course.subject_title || ''}</td><td class="text-center">${statusBadge}</td></tr>`;
                });

                bodyHtml += `</tbody></table></div></div>`;
            }

            // Action items / what you can do
            let actions = [];
            if (regularReason && regularReason.includes('not been published')) {
                actions.push('{{ __("Your exam results are still being processed. Please check back after marks have been published by the administration.") }}');
            }
            if (regularReason && regularReason.includes('failed')) {
                actions.push('<a href="{{ route("student.resit.index") }}" class="text-primary fw-bold">{{ __("Visit the Resit page to request resit exams or decline for your failed courses.") }}</a>');
            }
            if (regularReason && regularReason.includes('pending resit')) {
                actions.push('{{ __("Wait for your active resit requests to be processed and scheduled by the administration.") }}');
            }
            if (regularReason && (regularReason.includes('No next semester') || regularReason.includes('not configured'))) {
                actions.push('{{ __("Contact the academic office — the next semester may not yet be set up in the system.") }}');
            }
            if (actions.length === 0) {
                actions.push('{{ __("Please contact the academic office if you need assistance with your progression.") }}');
            }

            bodyHtml += `
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-hand-point-right"></i> {{ __('What You Can Do') }}</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
            `;
            actions.forEach(function(action) {
                bodyHtml += `<li class="mb-1">${action}</li>`;
            });
            bodyHtml += `</ul></div></div>`;

            // Carry-over courses for not-eligible students
            const carryOverCourses = (details.carry_over_courses || []);
            if (carryOverCourses.length > 0) {
                const semTypes = [...new Set(carryOverCourses.map(c => c.semester_type_label).filter(Boolean))];
                const semTypesText = semTypes.join(' {{ __("and") }} ');
                bodyHtml += `
                    <div class="card mt-3 border-secondary">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-history"></i> {{ __('Carry-Over Courses') }}
                                <span class="badge bg-danger ms-2">${carryOverCourses.length}</span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-2">
                                <i class="fas fa-info-circle"></i>
                                {{ __('These courses were failed previously and must be re-registered in the next') }}
                                <strong>${semTypesText}</strong>.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Course') }}</th>
                                            <th class="text-center">{{ __('Credits') }}</th>
                                            <th class="text-center">{{ __('Score') }}</th>
                                            <th>{{ __('From') }}</th>
                                            <th>{{ __('Re-register In') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                carryOverCourses.forEach(function(co) {
                    bodyHtml += `
                        <tr>
                            <td><strong>${co.subject_code || ''}</strong> - ${co.subject_title || ''}</td>
                            <td class="text-center">${co.credit_hours || '--'}</td>
                            <td class="text-center text-danger"><strong>${co.best_marks || 0}%</strong></td>
                            <td><small>${co.from_semester || ''}</small></td>
                            <td><span class="badge bg-info">${co.semester_type_label || ''}</span></td>
                        </tr>
                    `;
                });
                bodyHtml += `</tbody></table></div></div></div>`;
            }

            $('#progressionModalBody').html(bodyHtml);
            // No "Proceed" button for non-eligible students
            $('#progressionModalFooter').hide();
        }

        // Load eligibility details when modal is opened — always fetch fresh data
        $('#manualProgressionModal').on('show.bs.modal', function() {
            // Reset to loading state
            resetModalToLoading();

            // Fetch fresh eligibility data
            $.ajax({
                url: '{{ route("student.progression.check") }}',
                method: 'GET',
                cache: false,
                headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' },
                success: function(response) {
                    try {
                        eligibilityData = response;

                        if (response.eligible === true) {
                            buildEligibleModal(response);
                        } else {
                            buildNotEligibleModal(response);
                        }
                    } catch (error) {
                        console.error('[Progression] Error building modal:', error);
                        showModalError('{{ __("An unexpected error occurred while loading progression details. Please try again.") }}');
                    }
                },
                error: function(xhr) {
                    let errorMsg = '{{ __("Unable to check progression eligibility. Please try again later.") }}';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showModalError(errorMsg);
                }
            });
        });

        // Reset modal content when closed (so re-opening starts fresh)
        $('#manualProgressionModal').on('hidden.bs.modal', function() {
            resetModalToLoading();
        });

        // Handle progression button click
        $('#proceedProgressionBtn').click(function() {
            if (!eligibilityData || !eligibilityData.eligible) {
                return;
            }

            const btn = $(this);
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ __('Processing...') }}');

            $.ajax({
                url: '{{ route("student.progression.proceed") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    type: eligibilityData.type
                },
                success: function(response) {
                    if (response.success) {
                        $('#progressionModalBody').html(`
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h4>{{ __('Progression Successful!') }}</h4>
                                <p>${response.message}</p>
                                <p class="text-muted">{{ __('Redirecting...') }}</p>
                            </div>
                        `);
                        $('#progressionModalFooter').hide();

                        setTimeout(function() {
                            window.location.href = response.redirect || '{{ route("student.dashboard.index") }}';
                        }, 2000);
                    } else {
                        $('#progressionModalBody').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> ${response.message || '{{ __('Progression failed. Please try again.') }}'}
                            </div>
                        `);
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr) {
                    let errorMsg = '{{ __('An error occurred. Please try again.') }}';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    $('#progressionModalBody').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> ${errorMsg}
                        </div>
                    `);
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Check eligibility on page load
        checkProgressionEligibility();
    });
    </script>
    
    {{-- Dynamic Popup Component for Student Portal --}}
    @include('components.dynamic-popup', ['area' => 'student_portal'])
    
    <script defer src="https://ai.innovakickstarter.com/vendor/chatbot/js/external-chatbot.js" data-chatbot-uuid="1e603773-8c6f-4275-ac48-f63bf9a9844d" data-iframe-width="420" data-iframe-height="745" data-language="en" ></script>

</body>
</html>
