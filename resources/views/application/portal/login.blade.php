@extends('application.portal.layout')

@push('styles')
<style>
    .new-applicant-callout {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        border: 1px solid #cddcf7;
        border-left: 3px solid #3b6fd4;
        border-radius: 0 10px 10px 0;
        background: #f4f8ff;
    }
    .new-applicant-callout strong { color: #182b49; font-size: .95rem; }
</style>
@endpush

@section('content')
<div class="auth-split-layout">
    <!-- Left Panel: Brand / Image -->
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="brand-header">
                <i class="fas fa-graduation-cap fa-2x mb-3 text-white"></i>
                <h2 class="fw-bold text-white mb-2">{{ institution_name() }}</h2>
            </div>
            
            <div class="auth-hero-text">
                <h1 class="display-4 fw-bolder text-white mb-4">Shape Your Future.</h1>
                <p class="lead text-white-50">Log in to your application portal to continue your journey, track your admission status, and connect with our academic community.</p>
            </div>
            
            <div class="auth-footer text-white-50 small">
                &copy; {{ date('Y') }} {{ institution_name() }}. All rights reserved.
            </div>
        </div>
        <div class="auth-bg-overlay"></div>
    </div>

    <!-- Right Panel: Form -->
    <div class="auth-right">
        <div class="auth-form-container">
            <div class="text-center mb-5 d-md-none">
                <i class="fas fa-graduation-cap fa-3x" style="color: #667eea;"></i>
                <h3 class="fw-bold mt-2">{{ institution_name() }}</h3>
            </div>

            <div class="mb-5">
                <h2 class="fw-bold text-dark mb-1">{{ __('Continue your application') }}</h2>
                <p class="text-muted">{{ __('Sign in to pick up where you left off.') }}</p>
            </div>

            <div class="new-applicant-callout mb-4">
                <div>
                    <strong class="d-block">{{ __('Applying for the first time?') }}</strong>
                    <span class="text-muted small">{{ __('You do not need an account yet.') }}</span>
                </div>
                <a href="{{ route('application.start') }}" class="btn btn-sm btn-outline-primary flex-shrink-0">
                    {{ __('Start your application') }} <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show modern-alert" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <form method="post" action="{{ route('application.authenticate') }}" class="needs-validation modern-form" novalidate>
                @csrf
                
                <div class="form-floating mb-4">
                    <input type="email" 
                           class="form-control @error('email') is-invalid @enderror" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           placeholder="name@example.com"
                           required 
                           autofocus>
                    <label for="email"><i class="fas fa-envelope text-muted me-2"></i>{{ __('Email Address') }}</label>
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-floating mb-4 position-relative">
                    <input type="password" 
                           class="form-control @error('password') is-invalid @enderror" 
                           id="password" 
                           name="password" 
                           placeholder="Password"
                           required>
                    <label for="password"><i class="fas fa-lock text-muted me-2"></i>{{ __('Password') }}</label>
                    <button class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted" type="button" onclick="togglePassword()" tabindex="-1">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                    @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check custom-checkbox">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label text-muted" for="remember">{{ __('Remember me') }}</label>
                    </div>
                    <a href="{{ route('application.password.request') }}" class="text-primary text-decoration-none fw-semibold">
                        {{ __('Forgot Password?') }}
                    </a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100 modern-btn mb-4">
                    {{ __('Sign In') }} <i class="fas fa-arrow-right ms-2"></i>
                </button>
                
                <div class="text-center">
                    <p class="text-muted mb-0">
                        {{ __('New to') }} {{ institution_name() }}?
                        <a href="{{ route('application.start') }}" class="text-primary fw-bold text-decoration-none ms-1">{{ __('Start your application') }}</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>



@push('scripts')
<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
@endpush
@endsection
