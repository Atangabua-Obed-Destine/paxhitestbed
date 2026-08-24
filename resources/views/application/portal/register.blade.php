@extends('application.portal.layout')

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
                <h1 class="display-4 fw-bolder text-white mb-4">Start Your Journey.</h1>
                <p class="lead text-white-50">Create an account to begin your application process. Join a community of innovators and take the first step toward your academic future.</p>
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
            <div class="text-center mb-4 d-md-none">
                <i class="fas fa-graduation-cap fa-3x" style="color: #667eea;"></i>
                <h3 class="fw-bold mt-2">{{ institution_name() }}</h3>
            </div>

            <div class="mb-4">
                <h2 class="fw-bold text-dark mb-1">{{ __('Start your application') }}</h2>
                <p class="text-muted">{{ __('First, create the account you will use to complete and track your application.') }}</p>
            </div>

            <form method="post" action="{{ route('application.register.store') }}" class="needs-validation modern-form" novalidate>
                @csrf
                
                <div class="row gx-3">
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control @error('first_name') is-invalid @enderror" 
                                   id="first_name" 
                                   name="first_name" 
                                   value="{{ old('first_name') }}" 
                                   placeholder="First Name"
                                   required 
                                   autofocus>
                            <label for="first_name"><i class="fas fa-user text-muted me-2"></i>{{ __('First Name') }}</label>
                            @error('first_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control @error('last_name') is-invalid @enderror" 
                                   id="last_name" 
                                   name="last_name" 
                                   value="{{ old('last_name') }}" 
                                   placeholder="Last Name"
                                   required>
                            <label for="last_name">{{ __('Last Name') }}</label>
                            @error('last_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" 
                           class="form-control @error('email') is-invalid @enderror" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           placeholder="name@example.com"
                           required>
                    <label for="email"><i class="fas fa-envelope text-muted me-2"></i>{{ __('Email Address') }}</label>
                    <div class="form-text mt-1 text-muted small"><i class="fas fa-info-circle me-1"></i>{{ __('This will be used for all application communications.') }}</div>
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-floating mb-3">
                    <input type="email"
                           class="form-control @error('email_confirmation') is-invalid @enderror"
                           id="email_confirmation"
                           name="email_confirmation"
                           value="{{ old('email_confirmation') }}"
                           placeholder="name@example.com"
                           onpaste="return false;"
                           required>
                    <label for="email_confirmation"><i class="fas fa-envelope text-muted me-2"></i>{{ __('Confirm Email Address') }}</label>
                    @error('email_confirmation')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-floating mb-3 position-relative">
                    <input type="password" 
                           class="form-control @error('password') is-invalid @enderror" 
                           id="password" 
                           name="password" 
                           placeholder="Password"
                           required 
                           minlength="8">
                    <label for="password"><i class="fas fa-lock text-muted me-2"></i>{{ __('Password') }}</label>
                    <button class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted" type="button" onclick="togglePassword('password', 'toggleIcon1')" tabindex="-1">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </button>
                    @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-floating mb-4 position-relative">
                    <input type="password" 
                           class="form-control" 
                           id="password_confirmation" 
                           name="password_confirmation" 
                           placeholder="Confirm Password"
                           required>
                    <label for="password_confirmation"><i class="fas fa-lock text-muted me-2"></i>{{ __('Confirm Password') }}</label>
                    <button class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted" type="button" onclick="togglePassword('password_confirmation', 'toggleIcon2')" tabindex="-1">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </button>
                </div>
                
                <div class="form-check custom-checkbox mb-4">
                    <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required>
                    <label class="form-check-label text-muted" style="font-size: 14px;" for="agree_terms">
                        @isset($termsPage)
                            {{ __('I agree to the') }} <a href="{{ route('page.single', $termsPage->slug) }}" target="_blank" rel="noopener" class="text-primary text-decoration-none">{{ __('Terms and Conditions') }}</a>
                        @else
                            {{ __('I confirm the information I provide will be true and complete.') }}
                        @endisset
                    </label>
                    <div class="invalid-feedback">{{ __('You must agree before submitting.') }}</div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 modern-btn mb-4">
                    {{ __('Create Account') }} <i class="fas fa-user-plus ms-2"></i>
                </button>
                
                <div class="text-center">
                    <p class="text-muted mb-0">
                        {{ __('Already started an application?') }}
                        <a href="{{ route('application.login') }}" class="text-primary fw-bold text-decoration-none ms-1">{{ __('Sign in to continue') }}</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
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
