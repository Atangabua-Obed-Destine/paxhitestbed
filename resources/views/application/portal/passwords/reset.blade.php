@extends('application.portal.layout')

@section('content')
<div class="portal-card card" style="max-width: 500px; margin: 0 auto;">
    <div class="card-header py-3 text-center">
        <div class="mb-3">
            <i class="fas fa-lock-open" style="font-size: 48px; color: #28a745;"></i>
        </div>
        <h5 class="mb-1">{{ __('Reset Your Password') }}</h5>
        <p class="text-muted mb-0" style="font-size: 14px;">{{ __('Create a new secure password for your account.') }}</p>
    </div>
    <div class="card-body p-4">
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <form method="POST" action="{{ route('application.password.update') }}" class="needs-validation" novalidate>
            @csrf
            
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-user me-1"></i> {{ __('Account Email') }}
                </label>
                <input type="text" class="form-control" value="{{ $email }}" disabled readonly>
                <div class="form-text text-success">
                    <i class="fas fa-check-circle me-1"></i>
                    {{ __('Reset link verified for this email.') }}
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-key me-1"></i> {{ __('New Password') }}
                </label>
                <div class="input-group">
                    <input type="password" 
                           class="form-control @error('password') is-invalid @enderror" 
                           id="password" 
                           name="password" 
                           placeholder="Enter new password"
                           minlength="8"
                           required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', 'toggleIcon1')">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </button>
                </div>
                @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                
                <!-- Password Strength Indicator -->
                <div class="password-strength mt-2" id="passwordStrength" style="display: none;">
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar" id="strengthBar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="text-muted" id="strengthText">Password strength</small>
                </div>
                
                <div class="form-text">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ __('Minimum 8 characters. Use a mix of letters, numbers, and symbols.') }}
                </div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">
                    <i class="fas fa-check-double me-1"></i> {{ __('Confirm New Password') }}
                </label>
                <div class="input-group">
                    <input type="password" 
                           class="form-control" 
                           id="password_confirmation" 
                           name="password_confirmation" 
                           placeholder="Confirm new password"
                           minlength="8"
                           required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirmation', 'toggleIcon2')">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </button>
                </div>
                <div id="passwordMatch" class="form-text" style="display: none;">
                    <i class="fas fa-times-circle text-danger me-1"></i>
                    <span class="text-danger">{{ __('Passwords do not match') }}</span>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                    <i class="fas fa-save me-2"></i>{{ __('Reset Password') }}
                </button>
            </div>
        </form>

        <hr class="my-4">

        <div class="text-center">
            <a href="{{ route('application.login') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>{{ __('Back to Sign In') }}
            </a>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted">
                <i class="fas fa-clock me-1"></i>
                {{ __('This reset link will expire in 60 minutes.') }}
            </small>
        </div>
    </div>
</div>

@push('styles')
<style>
    .password-strength .progress {
        border-radius: 3px;
    }
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

// Password strength checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('passwordStrength');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    if (password.length > 0) {
        strengthDiv.style.display = 'block';
        
        let strength = 0;
        let color = 'bg-danger';
        let text = 'Very weak';
        
        // Length check
        if (password.length >= 8) strength += 25;
        if (password.length >= 12) strength += 10;
        
        // Contains lowercase
        if (/[a-z]/.test(password)) strength += 15;
        
        // Contains uppercase
        if (/[A-Z]/.test(password)) strength += 15;
        
        // Contains numbers
        if (/[0-9]/.test(password)) strength += 15;
        
        // Contains special characters
        if (/[^a-zA-Z0-9]/.test(password)) strength += 20;
        
        if (strength < 30) {
            color = 'bg-danger';
            text = 'Very weak';
        } else if (strength < 50) {
            color = 'bg-warning';
            text = 'Weak';
        } else if (strength < 70) {
            color = 'bg-info';
            text = 'Fair';
        } else if (strength < 90) {
            color = 'bg-primary';
            text = 'Strong';
        } else {
            color = 'bg-success';
            text = 'Very strong';
        }
        
        strengthBar.style.width = strength + '%';
        strengthBar.className = 'progress-bar ' + color;
        strengthText.textContent = text;
    } else {
        strengthDiv.style.display = 'none';
    }
    
    checkPasswordMatch();
});

// Password match checker
document.getElementById('password_confirmation').addEventListener('input', checkPasswordMatch);

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirmation').value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirm.length > 0) {
        if (password === confirm) {
            matchDiv.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i><span class="text-success">{{ __("Passwords match") }}</span>';
            matchDiv.style.display = 'block';
        } else {
            matchDiv.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i><span class="text-danger">{{ __("Passwords do not match") }}</span>';
            matchDiv.style.display = 'block';
        }
    } else {
        matchDiv.style.display = 'none';
    }
}
</script>
@endpush
@endsection
