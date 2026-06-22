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
        }
        .portal-nav a {
            color: #fff;
            margin-right: 1.5rem;
            font-weight: 600;
        }
        .portal-wrapper {
            max-width: 1100px;
            margin: 2.5rem auto;
            padding: 0 1.25rem;
        }
        .portal-card {
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 15px 35px rgba(50, 50, 93, .05);
            border: 1px solid rgba(50, 50, 93, .08);
        }
        .portal-card .card-header {
            border-bottom: 1px solid rgba(50, 50, 93, .1);
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
    </style>
    <!-- Institutional portal theme (loads last so it overrides defaults) -->
    <link rel="stylesheet" href="{{ asset('dashboard/css/application-portal.css') }}?v={{ filemtime(public_path('dashboard/css/application-portal.css')) }}">
    @stack('styles')
</head>
<body class="application-portal">
    <header class="portal-nav d-flex align-items-center justify-content-between">
        <div class="brand-mark">
            <i class="fas fa-graduation-cap fa-lg"></i>
            <span class="h6 mb-0 d-none d-sm-inline">{{ config('app.name') }}</span>
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

    <script src="{{ asset('dashboard/js/vendor-all.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
