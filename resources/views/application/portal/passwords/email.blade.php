@extends('application.portal.layout')

@section('content')
<div class="portal-card card" style="max-width: 500px; margin: 0 auto;">
    <div class="card-header py-3 text-center">
        <div class="mb-3">
            <i class="fas fa-envelope-open-text" style="font-size: 48px; color: #667eea;"></i>
        </div>
        <h5 class="mb-1">{{ __('Forgot Your Password?') }}</h5>
        <p class="text-muted mb-0" style="font-size: 14px;">{{ __("No worries! Enter your email and we'll send you a reset link.") }}</p>
    </div>
    <div class="card-body p-4">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <form method="POST" action="{{ route('application.password.email') }}" class="needs-validation" novalidate>
            @csrf
            
            <div class="mb-4">
                <label for="email" class="form-label">
                    <i class="fas fa-at me-1"></i> {{ __('Email Address') }}
                </label>
                <input type="email" 
                       class="form-control form-control-lg @error('email') is-invalid @enderror" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       placeholder="Enter your registered email"
                       required 
                       autofocus>
                @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ __('Enter the email address you used when creating your application account.') }}
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i>{{ __('Send Password Reset Link') }}
                </button>
            </div>
        </form>

        <hr class="my-4">

        <div class="text-center">
            <p class="mb-2">{{ __('Remember your password?') }}</p>
            <a href="{{ route('application.login') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>{{ __('Back to Sign In') }}
            </a>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1"></i>
                {{ __("Your security is important to us. Reset links expire in 60 minutes.") }}
            </small>
        </div>
    </div>
</div>

@push('styles')
<style>
    .form-control-lg {
        padding: 0.75rem 1rem;
        font-size: 1rem;
    }
    .btn-lg {
        padding: 0.75rem 1.5rem;
    }
    .alert {
        border-radius: 0.5rem;
    }
</style>
@endpush
@endsection
