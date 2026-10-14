@extends('auth.layouts.student-master')
@section('title', __('auth_login'))
@section('content')

<!-- Student Login Section -->
<div class="student-login-wrapper">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left Side - Information Panel -->
            <div class="col-lg-6 login-info-panel d-none d-lg-flex" data-aos="fade-right">
                <div class="info-content">
                    <div class="university-logo mb-4">
                        @if(isset($setting))
                        <img src="{{ asset('/uploads/setting/'.$setting->logo_path) }}" alt="{{ $setting->title ?? 'PAX Higher Institute' }} Logo">
                        @endif
                    </div>
                    
                    <h1 class="welcome-title">Welcome to {{ $setting->title ?? 'PAX Higher Institute' }}</h1>
                    <p class="welcome-subtitle">Student Portal Access</p>
                    
                    <div class="info-features">
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="100">
                            <div class="feature-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Academic Excellence</h5>
                                <p>Access your courses, grades, and academic resources</p>
                            </div>
                        </div>
                        
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="200">
                            <div class="feature-icon">
                                <i class="fas fa-book-reader"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Digital Learning</h5>
                                <p>E-learning materials and online class resources</p>
                            </div>
                        </div>
                        
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="300">
                            <div class="feature-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Student Services</h5>
                                <p>Manage your profile, fees, and academic records</p>
                            </div>
                        </div>
                        
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="400">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Schedule & Events</h5>
                                <p>View timetables, exams, and campus events</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-row">
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="500">
                            <div class="stat-number">50+</div>
                            <div class="stat-label">Students</div>
                        </div>
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="600">
                            <div class="stat-number">150+</div>
                            <div class="stat-label">Faculty</div>
                        </div>
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="700">
                            <div class="stat-number">50+</div>
                            <div class="stat-label">Programs</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="col-lg-6 login-form-panel" data-aos="fade-left">
                <div class="form-container">
                    <!-- Mobile Logo -->
                    <div class="mobile-logo d-lg-none mb-4">
                        @if(isset($setting))
                        <img src="{{ asset('/uploads/setting/'.$setting->logo_path) }}" alt="{{ $setting->title ?? 'PAX Higher Institute' }} Logo">
                        @endif
                    </div>
                    
                    <div class="login-header">
                        <div class="icon-wrapper mb-4">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h2 class="login-title">Student Login</h2>
                        <p class="login-subtitle">Enter your credentials to access your student portal</p>
                    </div>

                    <!-- Form Start -->
                    <form method="POST" action="{{ route($loginRoute) }}" class="login-form">
                        @csrf
                        
                        @if(session('info'))
                        <div class="alert alert-info alert-custom" role="alert">
                            <i class="fas fa-info-circle"></i>
                            <strong>{{ session('info') }}</strong>
                        </div>
                        @endif
                        
                        <div class="form-group-custom">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input id="email" 
                                   type="email" 
                                   class="form-control-custom @error('email') is-invalid @enderror" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required 
                                   autocomplete="email" 
                                   placeholder="e.g., student@example.com" 
                                   autofocus>

                            @error('email')
                                <span class="invalid-feedback-custom" role="alert">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        
                        <div class="form-group-custom">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <div class="password-wrapper">
                                <input id="password" 
                                       type="password" 
                                       class="form-control-custom @error('password') is-invalid @enderror" 
                                       name="password" 
                                       required 
                                       autocomplete="current-password" 
                                       placeholder="Enter your password">
                                <span class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </span>
                            </div>

                            @error('password')
                                <span class="invalid-feedback-custom" role="alert">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        
                        <div class="form-options">
                            <div class="remember-me">
                                <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label for="remember">
                                    Remember me
                                </label>
                            </div>
                            
                            @if (Route::has('student.password.request'))
                            <div class="forgot-password">
                                <a href="{{ route($forgotPasswordRoute) }}">
                                    Forgot Password?
                                </a>
                            </div>
                            @endif
                        </div>
                        
                        <button type="submit" class="btn-login">
                            <span>Login to Portal</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                    <!-- Form End -->

                    {{-- Installing the portal.

                         Android can be asked directly. iPhone cannot: Apple
                         exposes no install API at all, so the most that is
                         possible there is showing where the button is. Saying
                         so plainly beats a button that does nothing. --}}
                    @if(config('pwa.enabled'))
                    <div id="pwa-install" class="pwa-install" hidden>
                        <div class="pwa-install-head">
                            <i class="fas fa-mobile-alt"></i>
                            <strong>{{ __('Put this portal on your phone') }}</strong>
                            <button type="button" id="pwa-dismiss" aria-label="{{ __('Close') }}">&times;</button>
                        </div>

                        <p class="pwa-install-text" id="pwa-text">
                            {{ __('Add it to your home screen and it opens like an app, without the browser.') }}
                        </p>

                        <button type="button" id="pwa-install-button" class="pwa-install-button" hidden>
                            <i class="fas fa-download"></i> {{ __('Install') }}
                        </button>

                        <ol class="pwa-steps" id="pwa-steps" hidden>
                            <li>{{ __('Tap the Share button at the bottom of Safari') }} <i class="fas fa-share-square"></i></li>
                            <li>{{ __('Scroll down and choose "Add to Home Screen"') }}</li>
                            <li>{{ __('Tap Add. The portal will appear with your other apps.') }}</li>
                        </ol>
                    </div>

                    <style>
                        .pwa-install {
                            margin: 18px 0 4px;
                            padding: 14px 16px;
                            border: 1px solid #e3e8ef;
                            border-radius: 12px;
                            background: #f8fafc;
                            text-align: left;
                        }
                        .pwa-install-head {
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            font-size: .95rem;
                            margin-bottom: 6px;
                        }
                        .pwa-install-head button {
                            margin-left: auto;
                            background: none;
                            border: 0;
                            font-size: 1.3rem;
                            line-height: 1;
                            color: #90a0b7;
                            cursor: pointer;
                        }
                        .pwa-install-text { font-size: .87rem; color: #6b7a8d; margin: 0 0 10px; }
                        .pwa-install-button {
                            width: 100%;
                            padding: 11px;
                            font-size: .95rem;
                            font-weight: 600;
                            color: #fff;
                            background: {{ config('pwa.theme_color') }};
                            border: 0;
                            border-radius: 9px;
                            cursor: pointer;
                        }
                        .pwa-steps { margin: 0; padding-left: 20px; font-size: .86rem; color: #6b7a8d; }
                        .pwa-steps li { margin-bottom: 4px; }
                    </style>

                    <script>
                    (function () {
                        var card = document.getElementById('pwa-install');
                        if (!card) { return; }

                        var button = document.getElementById('pwa-install-button');
                        var steps = document.getElementById('pwa-steps');
                        var text = document.getElementById('pwa-text');
                        var dismiss = document.getElementById('pwa-dismiss');
                        var KEY = 'pax-install-dismissed';

                        // Already installed: it opens in its own window, so there
                        // is nothing to offer.
                        var standalone = window.matchMedia('(display-mode: standalone)').matches
                            || window.navigator.standalone === true;

                        if (standalone) { return; }

                        try {
                            if (localStorage.getItem(KEY) === '1') { return; }
                        } catch (e) {
                            // Private browsing: show it rather than fail.
                        }

                        function show() { card.hidden = false; }

                        dismiss.addEventListener('click', function () {
                            card.hidden = true;
                            try { localStorage.setItem(KEY, '1'); } catch (e) {}
                        });

                        // --- Android and desktop Chrome --------------------
                        var deferred = null;

                        window.addEventListener('beforeinstallprompt', function (event) {
                            event.preventDefault();
                            deferred = event;
                            button.hidden = false;
                            steps.hidden = true;
                            show();
                        });

                        button.addEventListener('click', function () {
                            if (!deferred) { return; }
                            deferred.prompt();
                            deferred.userChoice.then(function () {
                                deferred = null;
                                card.hidden = true;
                                try { localStorage.setItem(KEY, '1'); } catch (e) {}
                            });
                        });

                        // --- iPhone and iPad -------------------------------
                        // There is no prompt to fire here. Apple provides no API,
                        // so the only honest thing is to show where the button is.
                        var ua = window.navigator.userAgent;
                        var isIos = /iPad|iPhone|iPod/.test(ua)
                            || (/Macintosh/.test(ua) && 'ontouchend' in document);

                        if (isIos) {
                            text.textContent = {!! json_encode(__('Add it to your home screen in three taps and it opens like an app.')) !!};
                            steps.hidden = false;
                            button.hidden = true;
                            show();
                        }
                    })();
                    </script>
                    @endif

                    @if (Route::has('student.register'))
                    <div class="register-link">
                        <p>Don't have an account? 
                            <a href="{{ route('student.register') }}">Register Now</a>
                        </p>
                    </div>
                    @endif
                    
                    <div class="login-footer">
                        <div class="help-links">
                            <a href="{{ route('home') }}">
                                <i class="fas fa-home"></i> Back to Website
                            </a>
                            <a href="#">
                                <i class="fas fa-question-circle"></i> Need Help?
                            </a>
                        </div>
                        
                        @isset($setting->copyright_text)
                        <p class="copyright-text">
                            &copy; {!! strip_tags($setting->copyright_text, '<a><b><br>') !!}
                        </p>
                        @endisset
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

@endsection