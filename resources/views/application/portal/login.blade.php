@extends('application.portal.layout')

@section('content')
<div class="portal-card card" style="max-width: 450px; margin: 0 auto;">
    <div class="card-header py-3 text-center">
        <div class="mb-3">
            <i class="fas fa-user-graduate" style="font-size: 48px; color: #667eea;"></i>
        </div>
        <h5 class="mb-1">{{ __('Application Portal Sign In') }}</h5>
        <p class="text-muted mb-0" style="font-size: 14px;">{{ __('Access your application dashboard') }}</p>
    </div>
    <div class="card-body p-4">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <form method="post" action="{{ route('application.authenticate') }}" class="needs-validation" novalidate>
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-1"></i> {{ __('field_email') }}
                </label>
                <input type="email" 
                       class="form-control @error('email') is-invalid @enderror" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       placeholder="Enter your email address"
                       required 
                       autofocus>
                @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-1"></i> {{ __('field_password') }}
                </label>
                <div class="input-group">
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password"
                           required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>
            
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                </div>
                <a href="{{ route('application.password.request') }}" class="text-primary text-decoration-none" style="font-size: 14px;">
                    <i class="fas fa-key me-1"></i>{{ __('Forgot Password?') }}
                </a>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>{{ __('Sign In') }}
                </button>
            </div>
        </form>
        
        <hr class="my-4">
        
        <div class="text-center">
            <p class="mb-2">{{ __("Don't have an application account?") }}</p>
            <a href="{{ route('application.register') }}" class="btn btn-outline-success">
                <i class="fas fa-plus-circle me-2"></i>{{ __('Start new application') }}
            </a>
        </div>
    </div>
</div>

@push('styles')
<style>
    .btn-lg {
        padding: 0.75rem 1.5rem;
    }
    .alert {
        border-radius: 0.5rem;
    }
</style>
@endpush

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
