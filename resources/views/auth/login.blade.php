@extends('auth.layouts.staff-master')
@section('title', __('auth_login'))
@section('content')

<!-- Staff Login Section -->
<div class="staff-login-wrapper">
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
                    <p class="welcome-subtitle">Staff Portal Access</p>
                    
                    <div class="info-features">
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="100">
                            <div class="feature-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Teaching Management</h5>
                                <p>Manage courses, grades, attendance, and class schedules</p>
                            </div>
                        </div>
                        
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="200">
                            <div class="feature-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Administrative Tools</h5>
                                <p>Access administrative functions and reporting tools</p>
                            </div>
                        </div>
                        
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="300">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Analytics & Reports</h5>
                                <p>View performance metrics and generate reports</p>
                            </div>
                        </div>
                        
                        <div class="feature-item" data-aos="fade-up" data-aos-delay="400">
                            <div class="feature-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="feature-text">
                                <h5>Communication Hub</h5>
                                <p>Connect with students, staff, and departments</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-row">
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="500">
                            <div class="stat-number">150+</div>
                            <div class="stat-label">Faculty Members</div>
                        </div>
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="600">
                            <div class="stat-number">50+</div>
                            <div class="stat-label">Departments</div>
                        </div>
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="700">
                            <div class="stat-number">50+</div>
                            <div class="stat-label">Students</div>
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
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h2 class="login-title">{{ __('auth_login_title') }}</h2>
                        <p class="login-subtitle">Access your staff dashboard and management tools</p>
                    </div>

                    <!-- Session Expired Alert -->
                    @if(request()->get('session_expired'))
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Session Expired!</strong> Your session has timed out due to inactivity. Please login again to continue.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    <!-- Form Start -->
                    <form method="POST" action="{{ route('login') }}" class="login-form">
                        @csrf
                        
                        <div class="form-group-custom">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> {{ __('field_email') }}
                            </label>
                            <input id="email" 
                                   type="email" 
                                   class="form-control-custom @error('email') is-invalid @enderror" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required 
                                   autocomplete="email" 
                                   placeholder="staff@paxhi.edu" 
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
                                <i class="fas fa-lock"></i> {{ __('field_password') }}
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
                                    {{ __('field_remember') }}
                                </label>
                            </div>
                            
                            @if (Route::has('password.request'))
                            <div class="forgot-password">
                                <a href="{{ route('password.request') }}">
                                    {{ __('auth_forgot_password') }}
                                </a>
                            </div>
                            @endif
                        </div>
                        
                        <button type="submit" class="btn-login">
                            <span>{{ __('auth_login') }}</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                    <!-- Form End -->

                    @if (Route::has('register'))
                    <div class="register-link">
                        <p>{{ __("auth_dont_have_account") }} 
                            <a href="{{ route('register') }}">{{ __('auth_register') }}</a>
                        </p>
                    </div>
                    @endif
                    
                    <div class="login-footer">
                        <div class="help-links">
                            <a href="{{ route('home') }}">
                                <i class="fas fa-home"></i> Back to Website
                            </a>
                            <a href="#">
                                <i class="fas fa-headset"></i> IT Support
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
