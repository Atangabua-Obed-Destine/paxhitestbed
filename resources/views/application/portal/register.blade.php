@extends('application.portal.layout')

@section('content')
<div class="portal-card card">
    <div class="card-header py-3">
        <h5 class="mb-0">{{ __('Create Applicant Account') }}</h5>
    </div>
    <div class="card-body p-4">
        <form method="post" action="{{ route('application.register.store') }}" class="needs-validation" novalidate>
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label">{{ __('field_first_name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" required autofocus>
                    @error('first_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label">{{ __('field_last_name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('field_email') }} <span class="text-danger">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                <div class="form-text">{{ __('This email will be used for all communication regarding your application.') }}</div>
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">{{ __('field_password') }} <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required minlength="8">
                    <div class="form-text">{{ __('Minimum 8 characters.') }}</div>
                    @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }} <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required>
                <label class="form-check-label" for="agree_terms">{{ __('I agree to the terms and conditions') }}</label>
                <div class="invalid-feedback">{{ __('You must agree before submitting.') }}</div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">{{ __('Create Account') }}</button>
            </div>
            
            <div class="text-center mt-3">
                <p class="mb-0">{{ __('Already have an account?') }} <a href="{{ route('application.login') }}">{{ __('Sign In') }}</a></p>
            </div>
        </form>
    </div>
</div>
@endsection
