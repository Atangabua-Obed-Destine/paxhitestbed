@extends('admin.layouts.master')
@section('title', __('Security Settings'))
@section('content')

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="fas fa-cogs text-primary"></i> {{ __('Security Settings') }}</h3>
                        <p class="text-muted mb-0">{{ __('Configure system security parameters') }}</p>
                    </div>
                    <div>
                        <a href="{{ route('admin.security.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Form -->
        <form id="settingsForm">
            <div class="row">
                <!-- Login Security Settings -->
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-shield-alt"></i> {{ __('Login Security') }}
                            </h3>
                        </div>
                        <div class="card-body">
                            @foreach($settings as $setting)
                                @if(in_array($setting->key, ['max_login_attempts', 'lockout_duration', 'auto_block_threshold', 'session_timeout']))
                                <div class="form-group">
                                    <label for="setting_{{ $setting->key }}">
                                        {{ __(Str::title(str_replace('_', ' ', $setting->key))) }}
                                        <i class="fas fa-info-circle text-info" 
                                           data-toggle="tooltip" 
                                           title="{{ $setting->description }}"></i>
                                    </label>
                                    
                                    @if($setting->type === 'boolean')
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" 
                                               class="custom-control-input" 
                                               id="setting_{{ $setting->key }}" 
                                               name="settings[{{ $setting->key }}]" 
                                               value="1"
                                               {{ $setting->value ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="setting_{{ $setting->key }}">
                                            {{ __('Enable') }}
                                        </label>
                                    </div>
                                    @else
                                    <input type="number" 
                                           class="form-control" 
                                           id="setting_{{ $setting->key }}" 
                                           name="settings[{{ $setting->key }}]" 
                                           value="{{ $setting->value }}"
                                           min="1">
                                    @endif
                                    
                                    <small class="form-text text-muted">{{ $setting->description }}</small>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Password Policy Settings -->
                <div class="col-md-6">
                    <div class="card card-success card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-key"></i> {{ __('Password Policy') }}
                            </h3>
                        </div>
                        <div class="card-body">
                            @foreach($settings as $setting)
                                @if(in_array($setting->key, ['min_password_length', 'password_require_uppercase', 'password_require_lowercase', 'password_require_numbers', 'password_require_symbols', 'password_expiry_days', 'password_history_count']))
                                <div class="form-group">
                                    <label for="setting_{{ $setting->key }}">
                                        {{ __(Str::title(str_replace('_', ' ', $setting->key))) }}
                                        <i class="fas fa-info-circle text-info" 
                                           data-toggle="tooltip" 
                                           title="{{ $setting->description }}"></i>
                                    </label>
                                    
                                    @if($setting->type === 'boolean')
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" 
                                               class="custom-control-input" 
                                               id="setting_{{ $setting->key }}" 
                                               name="settings[{{ $setting->key }}]" 
                                               value="1"
                                               {{ $setting->value ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="setting_{{ $setting->key }}">
                                            {{ __('Enable') }}
                                        </label>
                                    </div>
                                    @else
                                    <input type="number" 
                                           class="form-control" 
                                           id="setting_{{ $setting->key }}" 
                                           name="settings[{{ $setting->key }}]" 
                                           value="{{ $setting->value }}"
                                           min="{{ in_array($setting->key, ['min_password_length']) ? '8' : '0' }}">
                                    @endif
                                    
                                    <small class="form-text text-muted">{{ $setting->description }}</small>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Upload Security & Access Control -->
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="card card-warning card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-upload"></i> {{ __('File Upload Security') }}
                            </h3>
                        </div>
                        <div class="card-body">
                            @foreach($settings as $setting)
                                @if(in_array($setting->key, ['max_file_upload_size', 'enable_file_upload_logging', 'enable_virus_scanning']))
                                <div class="form-group">
                                    <label for="setting_{{ $setting->key }}">
                                        {{ __(Str::title(str_replace('_', ' ', $setting->key))) }}
                                        <i class="fas fa-info-circle text-info" 
                                           data-toggle="tooltip" 
                                           title="{{ $setting->description }}"></i>
                                    </label>
                                    
                                    @if($setting->type === 'boolean')
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" 
                                               class="custom-control-input" 
                                               id="setting_{{ $setting->key }}" 
                                               name="settings[{{ $setting->key }}]" 
                                               value="1"
                                               {{ $setting->value ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="setting_{{ $setting->key }}">
                                            {{ __('Enable') }}
                                        </label>
                                    </div>
                                    @else
                                    <div class="input-group">
                                        <input type="number" 
                                               class="form-control" 
                                               id="setting_{{ $setting->key }}" 
                                               name="settings[{{ $setting->key }}]" 
                                               value="{{ $setting->value }}"
                                               min="1"
                                               max="10240">
                                        <div class="input-group-append">
                                            <span class="input-group-text">KB</span>
                                        </div>
                                    </div>
                                    @endif
                                    
                                    <small class="form-text text-muted">{{ $setting->description }}</small>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-danger card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-shield-alt"></i> {{ __('Access Control') }}
                            </h3>
                        </div>
                        <div class="card-body">
                            @foreach($settings as $setting)
                                @if(in_array($setting->key, ['enable_ip_whitelist', 'enable_security_alerts', 'security_alert_threshold']))
                                <div class="form-group">
                                    <label for="setting_{{ $setting->key }}">
                                        {{ __(Str::title(str_replace('_', ' ', $setting->key))) }}
                                        <i class="fas fa-info-circle text-info" 
                                           data-toggle="tooltip" 
                                           title="{{ $setting->description }}"></i>
                                    </label>
                                    
                                    @if($setting->type === 'boolean')
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" 
                                               class="custom-control-input" 
                                               id="setting_{{ $setting->key }}" 
                                               name="settings[{{ $setting->key }}]" 
                                               value="1"
                                               {{ $setting->value ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="setting_{{ $setting->key }}">
                                            {{ __('Enable') }}
                                        </label>
                                    </div>
                                    @else
                                    <input type="number" 
                                           class="form-control" 
                                           id="setting_{{ $setting->key }}" 
                                           name="settings[{{ $setting->key }}]" 
                                           value="{{ $setting->value }}"
                                           min="1">
                                    @endif
                                    
                                    <small class="form-text text-muted">{{ $setting->description }}</small>
                                </div>
                                @endif
                            @endforeach

                            <!-- IP Whitelist Quick Link -->
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-shield-alt"></i>
                                <strong>{{ __('IP Whitelist Management') }}</strong><br>
                                <small>{{ __('Manage trusted IPs that bypass all security checks') }}</small><br>
                                <a href="{{ route('admin.security.whitelist') }}" class="btn btn-sm btn-info mt-2">
                                    <i class="fas fa-cog"></i> {{ __('Manage IP Whitelist') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Factor Authentication Settings -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-mobile-alt"></i> {{ __('Two-Factor Authentication (2FA)') }}
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    @foreach($settings as $setting)
                                        @if(in_array($setting->key, ['enable_2fa_admin', '2fa_mandatory_admin']))
                                        <div class="form-group">
                                            <label for="setting_{{ $setting->key }}">
                                                {{ __(Str::title(str_replace('_', ' ', $setting->key))) }}
                                                <i class="fas fa-info-circle text-info" 
                                                   data-toggle="tooltip" 
                                                   title="{{ $setting->description }}"></i>
                                            </label>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" 
                                                       class="custom-control-input" 
                                                       id="setting_{{ $setting->key }}" 
                                                       name="settings[{{ $setting->key }}]" 
                                                       value="1"
                                                       {{ $setting->value ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="setting_{{ $setting->key }}">
                                                    {{ __('Enable') }}
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">{{ $setting->description }}</small>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="col-md-4">
                                    @foreach($settings as $setting)
                                        @if(in_array($setting->key, ['enable_2fa_student', '2fa_mandatory_student']))
                                        <div class="form-group">
                                            <label for="setting_{{ $setting->key }}">
                                                {{ __(Str::title(str_replace('_', ' ', $setting->key))) }}
                                                <i class="fas fa-info-circle text-info" 
                                                   data-toggle="tooltip" 
                                                   title="{{ $setting->description }}"></i>
                                            </label>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" 
                                                       class="custom-control-input" 
                                                       id="setting_{{ $setting->key }}" 
                                                       name="settings[{{ $setting->key }}]" 
                                                       value="1"
                                                       {{ $setting->value ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="setting_{{ $setting->key }}">
                                                    {{ __('Enable') }}
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">{{ $setting->description }}</small>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="col-md-4">
                                    @foreach($settings as $setting)
                                        @if($setting->key === '2fa_code_expiry')
                                        <div class="form-group">
                                            <label for="setting_{{ $setting->key }}">
                                                {{ __(Str::title(str_replace('_', ' ', $setting->key))) }}
                                                <i class="fas fa-info-circle text-info" 
                                                   data-toggle="tooltip" 
                                                   title="{{ $setting->description }}"></i>
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="setting_{{ $setting->key }}" 
                                                   name="settings[{{ $setting->key }}]" 
                                                   value="{{ $setting->value }}"
                                                   min="1"
                                                   max="60">
                                            <small class="form-text text-muted">{{ $setting->description }}</small>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>{{ __('Important:') }}</strong><br>
                                <ul class="mb-0 pl-3">
                                    <li><strong>{{ __('When you enable 2FA here, it will automatically enable 2FA for ALL existing users of that type') }}</strong></li>
                                    <li>{{ __('When mandatory is enabled, all users MUST use 2FA to login (cannot be disabled per user)') }}</li>
                                    <li>{{ __('Codes are sent via email and expire after the specified time') }}</li>
                                    <li>{{ __('Make sure your email system is properly configured before enabling') }}</li>
                                    <li>{{ __('You can individually manage user 2FA status in the User Management page') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Settings Summary -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-bar"></i> {{ __('Current Security Configuration') }}
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="info-box bg-gradient-danger">
                                        <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ __('Max Failed Attempts') }}</span>
                                            <span class="info-box-number" id="summary_max_attempts">
                                                {{ $settings->firstWhere('key', 'max_login_attempts')->value }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-box bg-gradient-warning">
                                        <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ __('Lockout Duration') }}</span>
                                            <span class="info-box-number" id="summary_lockout">
                                                {{ $settings->firstWhere('key', 'lockout_duration')->value }} min
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-box bg-gradient-info">
                                        <span class="info-box-icon"><i class="fas fa-key"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ __('Min Password Length') }}</span>
                                            <span class="info-box-number" id="summary_password">
                                                {{ $settings->firstWhere('key', 'min_password_length')->value }} chars
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> {{ __('Save Security Settings') }}
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg" onclick="location.reload()">
                                <i class="fas fa-undo"></i> {{ __('Reset Changes') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Warning Alert -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning">
                    <h5><i class="icon fas fa-exclamation-triangle"></i> {{ __('Important Notes') }}</h5>
                    <ul class="mb-0">
                        <li>{{ __('Changes to these settings take effect immediately') }}</li>
                        <li>{{ __('Setting values too restrictive may lock out legitimate users') }}</li>
                        <li>{{ __('Enabling IP whitelist without adding your IP may lock you out') }}</li>
                        <li>{{ __('Always test security settings in a non-production environment first') }}</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</section>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    console.log('Security settings page loaded');
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Update summary on input change
    $('#setting_max_login_attempts').on('input', function() {
        $('#summary_max_attempts').text($(this).val());
    });

    $('#setting_lockout_duration').on('input', function() {
        $('#summary_lockout').text($(this).val() + ' min');
    });

    $('#setting_min_password_length').on('input', function() {
        $('#summary_password').text($(this).val() + ' chars');
    });

    // Settings Form Submit
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        // Collect all settings
        var formData = {};
        
        // Get all input fields
        $('input[name^="settings"]').each(function() {
            var name = $(this).attr('name');
            var key = name.match(/settings\[([^\]]+)\]/)[1];
            
            if ($(this).attr('type') === 'checkbox') {
                formData[key] = $(this).is(':checked') ? 1 : 0;
            } else {
                formData[key] = $(this).val();
            }
        });

        // Validate IP whitelist warning
        if (formData['enable_ip_whitelist'] == 1) {
            if (!confirm('{{ __("Enable IP Whitelist?") }}\n\n{{ __("Enabling IP whitelist will restrict admin access to whitelisted IPs only.") }}\n{{ __("Make sure you have added your current IP to the whitelist!") }}\n\n{{ __("Continue?") }}')) {
                return false;
            }
        }
        
        submitSettings(formData);
    });

    function submitSettings(formData) {
        // Disable submit button to prevent double submission
        var submitBtn = $('#settingsForm button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ __("Saving...") }}');

        $.ajax({
            url: '{{ route("admin.security.settings.update") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                settings: formData
            },
            success: function(response) {
                alert(response.message);
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || '{{ __("Failed to update settings") }}');
                submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> {{ __("Save Security Settings") }}');
            }
        });
    }

    // Warn about sensitive toggles
    $('#setting_enable_ip_whitelist').on('change', function() {
        if ($(this).is(':checked')) {
            alert('{{ __("Warning: You are about to enable IP whitelisting. Make sure you have whitelisted your IP first!") }}');
        }
    });
});
</script>
@endsection

@section('styles')
<style>
    .info-box {
        min-height: 90px;
        transition: all 0.3s ease;
    }
    .info-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    .custom-control-label {
        cursor: pointer;
    }
</style>
@endsection
