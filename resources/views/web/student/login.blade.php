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
                        <img src="{{ asset('/uploads/setting/'.$setting->logo_path) }}" alt="PAX Higher Institute Logo">
                        @endif
                    </div>
                    
                    <h1 class="welcome-title">Welcome to PAX Higher Institute</h1>
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
                        <img src="{{ asset('/uploads/setting/'.$setting->logo_path) }}" alt="PAX Higher Institute Logo">
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