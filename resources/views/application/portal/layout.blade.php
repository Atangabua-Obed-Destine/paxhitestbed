<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('Application Portal') }}</title>

    <link rel="stylesheet" href="{{ asset('dashboard/css/style.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('dashboard/css/pages/wizard.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('dashboard/plugins/fontawesome/css/all.min.css') }}?v={{ time() }}">
    <style>
        body.application-portal {
            background: #f5f7fb;
            min-height: 100vh;
        }
        .portal-nav {
            background: #182b49;
            color: #fff;
            padding: 1.25rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 10;
        }
        .portal-nav a {
            color: rgba(255, 255, 255, 0.85);
            margin-right: 1.5rem;
            font-weight: 500;
            transition: color 0.3s ease;
            text-decoration: none;
        }
        .portal-nav a:hover {
            color: #fff;
        }
        .portal-wrapper {
            max-width: 1100px;
            margin: 2.5rem auto;
            padding: 0 1.25rem;
        }
        .portal-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: none;
            overflow: hidden;
        }
        .portal-card .card-header {
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
        }
        .progress {
            height: 12px;
            border-radius: 999px;
        }
        .progress-bar {
            width: calc(var(--progress-width, 0) * 1%);
            transition: width .4s ease;
        }
        .timeline-item {
            position: relative;
            padding-left: 2.5rem;
            margin-bottom: 1.75rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            top: .25rem;
            left: .65rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #2196f3;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            top: 1.5rem;
            left: 1.2rem;
            width: 2px;
            height: calc(100% - 1.5rem);
            background: rgba(24, 43, 73, .15);
        }
        .timeline-item:last-child::after {
            display: none;
        }
        
        /* Auth Split Layout Styles */
        body.application-portal { background-color: #f5f7fb; }
        .portal-auth-fullscreen { background-color: #fff; }
        .auth-split-layout { display: flex; min-height: 100vh; width: 100%; margin: 0; padding: 0; }
        /* The hero image is the institution's own, never an external one: a
           remote URL leaves this panel blank on a slow link or an offline
           server, and a stock photograph of somebody else's campus is the
           wrong first impression for an admissions page. Drop a photograph at
           public/uploads/setting/admissions-hero.jpg to use one; without it
           the brand colour alone is the deliberate fallback. */
        .auth-left { flex: 1; position: relative; background-color: #182b49;@if(file_exists(public_path('uploads/setting/admissions-hero.jpg'))) background-image: url('{{ asset('uploads/setting/admissions-hero.jpg') }}');@endif background-size: cover; background-position: center; display: flex; flex-direction: column; justify-content: center; padding: 4rem; overflow: hidden; }
        .auth-bg-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(24, 43, 73, 0.9) 0%, rgba(102, 126, 234, 0.8) 100%); z-index: 1; }
        .auth-left-content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; }
        .brand-header { margin-bottom: auto; }
        .auth-hero-text { margin-bottom: auto; max-width: 600px; }
        .auth-footer { margin-top: auto; }
        .auth-right { flex: 0 0 550px; background-color: #ffffff; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 3rem 2rem; box-shadow: -10px 0 30px rgba(0,0,0,0.05); z-index: 5; overflow-y: auto; overflow-x: hidden; }
        .auth-form-container { width: 100%; max-width: 420px; margin: auto; }
        .form-floating > .form-control { border-radius: 0.5rem; border: 1px solid #e2e8f0; box-shadow: none; padding-left: 1.25rem; }
        .form-floating > .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15); }
        .form-floating > label { padding-left: 1.25rem; }
        .custom-checkbox .form-check-input { border-radius: 0.25rem; border-color: #cbd5e1; }
        .custom-checkbox .form-check-input:checked { background-color: #667eea; border-color: #667eea; }
        .modern-btn { background-color: #182b49; border: none; border-radius: 0.5rem; padding: 0.8rem; font-weight: 600; transition: all 0.3s ease; }
        .modern-btn:hover { background-color: #2c4a7c; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(24, 43, 73, 0.2); }
        .modern-alert { border-radius: 0.5rem; border: none; background-color: #d1e7dd; color: #0f5132; }
        
        @media (max-width: 991px) {
            .auth-right { flex: 1; padding: 2rem 1.5rem; justify-content: flex-start; }
            .auth-left { display: none; }
            .auth-form-container { margin-top: 2rem; }
        }
    </style>
    <!-- Institutional portal theme (loads last so it overrides defaults) -->
    <link rel="stylesheet" href="{{ asset('dashboard/css/application-portal.css') }}?v={{ filemtime(public_path('dashboard/css/application-portal.css')) }}">
    @stack('styles')
</head>
<body class="application-portal">
    {{--
        A member of staff signed in as this applicant. Shown on every portal
        page so the session can never be mistaken for the applicant's own, and
        so the way out is always one click away — including when the account is
        disabled while they are inside it.
    --}}
    @php
        $impersonatedApplicant = session()->has('impersonate_applicant_admin_id') ? auth('applicant')->user() : null;
    @endphp

    @if($impersonatedApplicant)
        <div style="position:sticky;top:0;z-index:99999;background:#8a1c1c;color:#fff;padding:10px 16px;text-align:center;font-size:.9rem;">
            {{ __('You are signed in as :name (:email). Anything you do here is recorded as theirs.', [
                'name' => $impersonatedApplicant->full_name ?: __('this applicant'),
                'email' => $impersonatedApplicant->email,
            ]) }}
            <a href="{{ route('application.leave-impersonation') }}"
               style="margin-left:12px;background:#fff;color:#8a1c1c;padding:4px 10px;border-radius:4px;text-decoration:none;font-weight:600;">
                {{ __('Leave') }}
            </a>
        </div>
    @endif

    @php
        $isAuthPage = in_array(Route::currentRouteName(), ['application.start', 'application.login', 'application.register', 'application.password.request', 'application.password.reset']);
    @endphp

    @if($isAuthPage)
        <main class="portal-auth-fullscreen m-0 p-0" style="min-height: 100vh; display: flex; flex-direction: column;">
            @yield('content')
        </main>
    @else
        <header class="portal-nav d-flex align-items-center justify-content-between">
            <div class="brand-mark">
                <i class="fas fa-graduation-cap fa-lg"></i>
                <span class="h6 mb-0 d-none d-sm-inline">{{ institution_name() }}</span>
                <span class="h6 mb-0 d-sm-none">{{ __('Admissions') }}</span>
            </div>
            <nav class="d-flex align-items-center">
                @auth('applicant')
                    <a href="{{ route('application.dashboard') }}">{{ __('My Account') }}</a>
                    <a href="{{ route('application.create') }}">{{ __('Apply Online') }}</a>
                    <span class="me-3 d-none d-md-inline"><i class="far fa-user-circle me-1"></i>{{ strtoupper(trim(auth('applicant')->user()->first_name.' '.auth('applicant')->user()->last_name)) }}</span>
                    <form action="{{ route('application.logout') }}" method="post" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light text-primary">{{ __('Logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('application.login') }}">{{ __('Sign In') }}</a>
                    <a href="{{ route('application.register') }}">{{ __('Create Account') }}</a>
                @endauth
            </nav>
        </header>

        <main class="portal-wrapper">
            @yield('content')
        </main>
    @endif

    <script src="{{ asset('dashboard/js/vendor-all.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
    @stack('scripts')

    @include('components.chat-widget')
</body>
</html>
