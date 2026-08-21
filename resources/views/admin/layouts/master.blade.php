<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>

     @include('admin.layouts.common.header_script')

     {{-- Page-specific styles. Four views already pushed to this stack — the
          budget sheet, bank reconciliation and the two journal-entry screens —
          but nothing rendered it, so their styling was silently dropped and
          those tables drew unformatted. Placed last so a page can override the
          theme it inherits. --}}
     @stack('css')

</head>

<body>

    @php
        // Load announcements for admin portal
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
        /* Adjust admin portal header to start below announcement bar */
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
                <a href="{{ route('admin.dashboard.index') }}" class="b-brand">
                    <img src="{{ upload_asset('setting/'.$setting->logo_path) }}" alt="logo">
                </a>
                @endif
                @endif
                <a class="mobile-menu" id="mobile-collapse" href="#!"><span></span></a>
            </div>


            @if(Request::is('admin*'))
            <!--- Sidemenu -->
            @include('admin.layouts.inc.sidebar')
            <!-- End Sidebar -->
            @endif

        </div>
    </nav>
    <!-- [ navigation menu ] end -->

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- [ Header ] start -->
    <header class="navbar pcoded-header navbar-expand-lg navbar-light headerpos-fixed header-lightblue">
        <div class="m-header">
            <a class="mobile-menu" id="mobile-collapse1" href="#!"><span></span></a>
            @if(isset($setting))
            @if(upload_exists('setting/'.$setting->logo_path))
            <a href="{{ route('admin.dashboard.index') }}" class="b-brand">
                <div class="b-bg">
                    <img src="{{ upload_asset('setting/'.$setting->logo_path) }}" alt="logo" height="20">
                </div>
            </a>
            @endif
            @endif
        </div>

        {{-- Desktop left section --}}
        <div class="header-left d-none d-lg-flex align-items-center">
            <a href="#!" class="full-screen" onclick="javascript:toggleFullScreen()" title="Toggle Fullscreen"><i class="feather icon-maximize"></i></a>
            <h4 class="topbar-title">{{ $setting->title }}</h4>
            <div class="header-clock d-none d-xl-flex align-items-center">
                <i class="fas fa-calendar-alt me-2"></i>
                <div id="real-time-clock">
                    <span id="current-date"></span> | <span id="current-time"></span>
                </div>
            </div>
            @php
                $currentSession = \App\Models\Session::where('current', 1)->where('status', 1)->first();
            @endphp
            @if($currentSession)
            <a href="{{ route('admin.session.index') }}" class="header-session-badge text-decoration-none" title="{{ __('manage_sessions') }}">
                <i class="fas fa-graduation-cap me-1"></i>{{ $currentSession->title }}
            </a>
            @endif
        </div>

        {{-- Right section - always visible --}}
        @auth
        <ul class="header-right ml-auto">
            {{-- Attendance Status --}}
            @php
                $today_attendance = \App\Models\StaffAttendance::where('user_id', Auth::id())
                                    ->where('date', date('Y-m-d'))
                                    ->first();
            @endphp
            <li class="header-attendance d-none d-md-flex">
                @if($today_attendance && $today_attendance->start_time && !$today_attendance->end_time)
                    <a href="{{ route('admin.staff-daily-attendance.my-attendance') }}" class="att-badge att-in" title="Clocked In at {{ date('h:i A', strtotime($today_attendance->start_time)) }}">
                        <i class="fas fa-clock"></i><span>Clocked In</span>
                    </a>
                @elseif($today_attendance && $today_attendance->end_time)
                    <a href="{{ route('admin.staff-daily-attendance.my-attendance') }}" class="att-badge att-done" title="Completed: {{ date('h:i A', strtotime($today_attendance->end_time)) }}">
                        <i class="fas fa-check-circle"></i><span>Done</span>
                    </a>
                @else
                    <a href="{{ route('admin.staff-daily-attendance.my-attendance') }}" class="att-badge att-out" title="Not Clocked In">
                        <i class="fas fa-exclamation-circle"></i><span>Not Clocked In</span>
                    </a>
                @endif
            </li>

            {{-- Connection Status --}}
            <li class="d-none d-sm-flex">
                <span class="connection-status" id="connection-status" title="Internet Connection">
                    <i class="fas fa-wifi" id="connection-icon"></i>
                </span>
            </li>

            {{-- Language --}}
            <li class="header-lang dropdown">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    @php $version = App\Models\Language::version(); @endphp
                    <i class="fas fa-language"></i><span class="d-none d-xl-inline ms-1">{{ $version->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header">{{ trans_choice('module_language', 2) }}</li>
                    @foreach($user_languages as $user_language)
                    <li>
                        <a class="dropdown-item @if(\Session()->get('locale') == $user_language->code) active @endif" href="{{ route('version', $user_language->code) }}">
                            {{ $user_language->name }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </li>

            {{-- Notifications --}}
            <li class="header-notif dropdown">
                @php
                    $unread_count = Auth::guard('web')->user()->unreadNotifications->count();
                    $unread_notifications = Auth::guard('web')->user()->unreadNotifications;
                @endphp
                <a class="dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                    <i class="icon feather icon-bell">
                    @if($unread_count > 0)
                    <span class="badge bg-danger rounded-pill notification-badge">{{ $unread_count }}</span>
                    @endif
                    </i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <li class="dropdown-header">
                        {{ trans_choice('module_notification', 2) }}
                        @if($unread_count > 0)<span class="badge bg-danger ms-1">{{ $unread_count }}</span>@endif
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <div class="notification-body">
                        @forelse($unread_notifications as $key => $notification)
                        @if($key < 10)
                        @php
                            $notification_link = 'admin.dashboard.index';
                            $notification_type = '';
                            if(isset($notification->data['type'])) {
                                if($notification->data['type'] == 'content') {
                                    $notification_link = 'admin.content.index';
                                    $notification_type = trans_choice('module_content', 1);
                                }
                                elseif($notification->data['type'] == 'notice') {
                                    $notification_link = 'admin.notice.index';
                                    $notification_type = trans_choice('module_notice', 1);
                                }
                            }
                        @endphp
                        <li>
                            <a class="dropdown-item" href="{{ route($notification_link) }}">
                                <strong>{{ $notification->data['title'] ?? 'Notification' }}</strong>
                                <small class="text-muted d-block">
                                    <i class="feather icon-clock"></i> {{ $notification->created_at->diffForHumans() }}
                                </small>
                                @if($notification_type)
                                <small class="text-primary"><i class="fas fa-arrow-circle-right"></i> {{ $notification_type }}</small>
                                @endif
                            </a>
                        </li>
                        @endif
                        @empty
                        <li><span class="dropdown-item-text">{{ __('status_no_notification') }}</span></li>
                        @endforelse
                    </div>
                </ul>
            </li>

            {{-- Profile --}}
            <li class="header-profile dropdown">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                    <div class="profile-pic-wrapper">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <span class="d-none d-lg-inline ms-2">{{ Auth::user()->first_name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                    <li class="dropdown-header text-center py-3">
                        {{-- Resolved before the browser asks for it: an absent photo used to be
                             requested anyway, answering 403 for the bare directory or 404 for
                             a file no longer on disk, on every page load. --}}
                        <img src="{{ avatar_url(Auth::user()->photo, 'user', Auth::user()->gender == 1 ? 'dashboard/images/user/avatar-2.jpg' : 'dashboard/images/user/avatar-1.jpg') }}"
                             class="rounded-circle mb-2" width="60" height="60" alt="{{ __('User') }}">
                        <div class="fw-bold">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</div>
                        <small class="text-muted">{{ Auth::user()->email }}</small>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    @can('profile-view')
                    <li>
                        <a href="{{ route('admin.profile.index') }}" class="dropdown-item">
                            <i class="feather icon-user me-2"></i>{{ trans_choice('module_profile', 2) }}
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    @endcan
                    <li>
                        <a href="{{ route('logout') }}" class="dropdown-item text-danger"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="feather icon-log-out me-2"></i>Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
        @endauth
    </header>
    <!-- [ Header ] end -->


    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">

                    <!-- start page title -->
                    <!-- Include page breadcrumb -->
                    @include('admin.layouts.inc.breadcrumb')
                    <!-- end page title -->


                    <!-- Start Content-->
                    @yield('content')
                    <!-- End Content-->

                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Session Timeout Warning Modal -->
    <div class="modal fade" id="session-timeout-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="sessionTimeoutLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="sessionTimeoutLabel">
                        <i class="fas fa-clock me-2"></i>Session Timeout Warning
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                    </div>
                    <h5 class="mb-3">Your session is about to expire!</h5>
                    <p class="text-muted">
                        Your session will expire in <strong id="session-minutes-left" class="text-danger">5</strong> minutes due to inactivity.
                    </p>
                    <p class="text-muted mb-0">
                        Click "Stay Logged In" to continue working or you will be automatically logged out.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <a href="{{ route('logout') }}" class="btn btn-secondary" 
                       onclick="event.preventDefault(); document.getElementById('logout-form-session').submit();">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout Now
                    </a>
                    <form id="logout-form-session" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                    <button type="button" class="btn btn-primary" id="extend-session-btn">
                        <i class="fas fa-check-circle me-1"></i>Stay Logged In
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- The theme's pcoded.min.js initialises PerfectScrollbar on .main-friend-cont
         and .main-chat-cont — panels from its chat demo that this application does
         not render. With nothing to attach to it throws "no element is specified",
         an uncaught error on every admin page load. These stubs give it the
         elements it insists on; the application wizard already does the same. --}}
    <div class="d-none" id="ps-placeholder" aria-hidden="true">
        <div class="main-friend-cont"></div>
        <div class="main-chat-cont"></div>
    </div>

    @include('admin.layouts.common.footer_script')

    @yield('scripts')
    @stack('scripts')

    {{-- Dynamic Popup Component for Admin Portal --}}
    @include('components.dynamic-popup', ['area' => 'admin_portal'])

    {{-- Third-party support chatbot, loaded only when one is configured. The
         host it used to point at unconditionally no longer resolves, so every
         page load spent a failed DNS lookup on it. Set CHATBOT_SCRIPT_URL and
         CHATBOT_UUID to switch it back on. --}}
    @if(config('services.chatbot.script_url') && config('services.chatbot.uuid'))
        <script defer src="{{ config('services.chatbot.script_url') }}"
                data-chatbot-uuid="{{ config('services.chatbot.uuid') }}"
                data-iframe-width="420" data-iframe-height="745" data-language="{{ app()->getLocale() }}"></script>
    @endif


    @include('components.chat-widget')
</body>
</html>
