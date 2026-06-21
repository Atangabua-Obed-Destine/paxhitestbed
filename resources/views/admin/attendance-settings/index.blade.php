@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-cogs mr-2"></i>{{ $title }}</h5>
                            <span class="text-muted">Configure settings for the Class Session Kiosk, QR scanning, and digital logbooks</span>
                        </div>
                        @can($access.'-edit')
                        <form action="{{ route($route.'.reset') }}" method="POST" class="d-inline" 
                              onsubmit="return confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-undo mr-1"></i> Reset to Defaults
                            </button>
                        </form>
                        @endcan
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.update') }}" method="post">
                            @csrf

                            {{-- Class Session Settings --}}
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="{{ $categories['class_session']['icon'] }} mr-2"></i>{{ $categories['class_session']['title'] }}</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">{{ $categories['class_session']['description'] }}</p>
                                    
                                    <div class="row">
                                        {{-- Minimum Class Duration Percentage --}}
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="minimum_class_duration_percentage">
                                                    <i class="fas fa-clock mr-1 text-primary"></i>
                                                    Minimum Class Duration (%)
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" 
                                                           name="minimum_class_duration_percentage" 
                                                           id="minimum_class_duration_percentage" 
                                                           value="{{ old('minimum_class_duration_percentage', $settings['minimum_class_duration_percentage']['value'] ?? 70) }}" 
                                                           min="0" max="100" step="1" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['minimum_class_duration_percentage']['description'] ?? 'Minimum percentage of class time for lecturer to be marked present' }}
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Late Threshold Percentage --}}
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="late_threshold_percentage">
                                                    <i class="fas fa-user-clock mr-1 text-warning"></i>
                                                    Student Late Threshold (%)
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" 
                                                           name="late_threshold_percentage" 
                                                           id="late_threshold_percentage" 
                                                           value="{{ old('late_threshold_percentage', $settings['late_threshold_percentage']['value'] ?? 15) }}" 
                                                           min="0" max="100" step="1" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['late_threshold_percentage']['description'] ?? 'Students arriving after this % of class time will be marked Late' }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        {{-- Auto Clock Out Enabled --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" 
                                                           id="auto_clock_out_enabled" 
                                                           name="auto_clock_out_enabled" 
                                                           value="1" 
                                                           {{ ($settings['auto_clock_out_enabled']['value'] ?? true) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="auto_clock_out_enabled">
                                                        <i class="fas fa-sign-out-alt mr-1 text-info"></i>
                                                        Auto Clock Out on End
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['auto_clock_out_enabled']['description'] ?? 'Automatically clock out students when class ends' }}
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Class End on First Clock Out --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" 
                                                           id="class_end_on_first_clock_out" 
                                                           name="class_end_on_first_clock_out" 
                                                           value="1" 
                                                           {{ ($settings['class_end_on_first_clock_out']['value'] ?? false) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="class_end_on_first_clock_out">
                                                        <i class="fas fa-stop-circle mr-1 text-danger"></i>
                                                        End on First Clock Out
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['class_end_on_first_clock_out']['description'] ?? 'Record end time when first student clocks out' }}
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Allow Extra Classes --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" 
                                                           id="allow_extra_classes" 
                                                           name="allow_extra_classes" 
                                                           value="1" 
                                                           {{ ($settings['allow_extra_classes']['value'] ?? true) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="allow_extra_classes">
                                                        <i class="fas fa-plus-circle mr-1 text-success"></i>
                                                        Allow Extra Classes
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['allow_extra_classes']['description'] ?? 'Allow lecturers to create unscheduled classes' }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        {{-- Lecturer Attendance Auto Sync --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" 
                                                           id="lecturer_attendance_auto_sync" 
                                                           name="lecturer_attendance_auto_sync" 
                                                           value="1" 
                                                           {{ ($settings['lecturer_attendance_auto_sync']['value'] ?? true) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="lecturer_attendance_auto_sync">
                                                        <i class="fas fa-sync-alt mr-1 text-primary"></i>
                                                        Sync Lecturer Attendance
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['lecturer_attendance_auto_sync']['description'] ?? 'Auto-sync to staff hourly attendance table' }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- QR Scanning Settings --}}
                            <div class="card mb-4 border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="{{ $categories['scanning']['icon'] }} mr-2"></i>{{ $categories['scanning']['title'] }}</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">{{ $categories['scanning']['description'] }}</p>
                                    
                                    <div class="row">
                                        {{-- Scan Cooldown Seconds --}}
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="scan_cooldown_seconds">
                                                    <i class="fas fa-hourglass-half mr-1 text-info"></i>
                                                    Scan Cooldown Period
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" 
                                                           name="scan_cooldown_seconds" 
                                                           id="scan_cooldown_seconds" 
                                                           value="{{ old('scan_cooldown_seconds', $settings['scan_cooldown_seconds']['value'] ?? 60) }}" 
                                                           min="5" max="600" step="5" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">seconds</span>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['scan_cooldown_seconds']['description'] ?? 'Minimum seconds between scans for the same student' }}
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Student Multi Scan Mode --}}
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="student_multi_scan_mode">
                                                    <i class="fas fa-exchange-alt mr-1 text-success"></i>
                                                    Scan Mode
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-control" name="student_multi_scan_mode" id="student_multi_scan_mode" required>
                                                    <option value="toggle" {{ ($settings['student_multi_scan_mode']['value'] ?? 'toggle') == 'toggle' ? 'selected' : '' }}>
                                                        Toggle Mode (In/Out alternates)
                                                    </option>
                                                    <option value="first_in" {{ ($settings['student_multi_scan_mode']['value'] ?? 'toggle') == 'first_in' ? 'selected' : '' }}>
                                                        First In Only (subsequent scans ignored)
                                                    </option>
                                                </select>
                                                <small class="form-text text-muted">
                                                    {{ $settings['student_multi_scan_mode']['description'] ?? 'How multiple scans from the same student are handled' }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Logbook Settings --}}
                            <div class="card mb-4 border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="{{ $categories['logbook']['icon'] }} mr-2"></i>{{ $categories['logbook']['title'] }}</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">{{ $categories['logbook']['description'] }}</p>
                                    
                                    <div class="row">
                                        {{-- Require Logbook Completion --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" 
                                                           id="require_logbook_completion" 
                                                           name="require_logbook_completion" 
                                                           value="1" 
                                                           {{ ($settings['require_logbook_completion']['value'] ?? false) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="require_logbook_completion">
                                                        <i class="fas fa-check-double mr-1 text-success"></i>
                                                        Require Logbook Completion
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['require_logbook_completion']['description'] ?? 'Lecturer must fill logbook before attendance is synced' }}
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Allow Class Rep Selection --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" 
                                                           id="allow_class_rep_selection" 
                                                           name="allow_class_rep_selection" 
                                                           value="1" 
                                                           {{ ($settings['allow_class_rep_selection']['value'] ?? true) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="allow_class_rep_selection">
                                                        <i class="fas fa-user-tie mr-1 text-primary"></i>
                                                        Allow Class Rep Selection
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['allow_class_rep_selection']['description'] ?? 'Allow selecting a class representative' }}
                                                </small>
                                            </div>
                                        </div>

                                        {{-- Show HOD on Logbook --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" 
                                                           id="show_hod_on_logbook" 
                                                           name="show_hod_on_logbook" 
                                                           value="1" 
                                                           {{ ($settings['show_hod_on_logbook']['value'] ?? true) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="show_hod_on_logbook">
                                                        <i class="fas fa-user-shield mr-1 text-warning"></i>
                                                        Show HOD on Logbook
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['show_hod_on_logbook']['description'] ?? 'Display Head of Department on logbook' }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Kiosk Display Settings --}}
                            <div class="card mb-4 border-secondary">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0"><i class="{{ $categories['kiosk']['icon'] }} mr-2"></i>{{ $categories['kiosk']['title'] }}</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">{{ $categories['kiosk']['description'] }}</p>
                                    
                                    <div class="row">
                                        {{-- Kiosk Auto Refresh Seconds --}}
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="kiosk_auto_refresh_seconds">
                                                    <i class="fas fa-sync mr-1 text-secondary"></i>
                                                    Stats Auto-Refresh Interval
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" 
                                                           name="kiosk_auto_refresh_seconds" 
                                                           id="kiosk_auto_refresh_seconds" 
                                                           value="{{ old('kiosk_auto_refresh_seconds', $settings['kiosk_auto_refresh_seconds']['value'] ?? 30) }}" 
                                                           min="10" max="300" step="5" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">seconds</span>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">
                                                    {{ $settings['kiosk_auto_refresh_seconds']['description'] ?? 'How often to refresh attendance statistics on kiosk' }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- How it Works Info Box --}}
                            <div class="card mb-4 border-info">
                                <div class="card-body">
                                    <h6><i class="fas fa-info-circle text-info mr-2"></i>How These Settings Work</h6>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <ul class="list-unstyled">
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    <strong>Late Threshold:</strong> If class is 2 hours and threshold is 15%, students arriving after 18 minutes are "Late"
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    <strong>Minimum Duration:</strong> Lecturer must teach 70% of scheduled time to be marked "Present" in staff attendance
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    <strong>Scan Cooldown:</strong> Prevents accidental double-scans within the specified time window
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="list-unstyled">
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    <strong>Toggle Mode:</strong> Each scan alternates between clock-in and clock-out
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    <strong>First In Mode:</strong> Only the first scan counts, useful for simple attendance tracking
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success mr-2"></i>
                                                    <strong>Auto Clock Out:</strong> When class ends, all remaining students are automatically clocked out
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Submit Button --}}
                            <div class="row">
                                <div class="col-md-12">
                                    @can($access.'-edit')
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save mr-2"></i> {{ __('btn_update') }} Settings
                                    </button>
                                    @else
                                    <div class="alert alert-warning">
                                        <i class="fas fa-lock mr-2"></i> You don't have permission to edit these settings.
                                    </div>
                                    @endcan
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
"use strict";
// Form Validation
(function() {
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Visual feedback for switches
document.querySelectorAll('.custom-switch input[type="checkbox"]').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        var label = this.nextElementSibling;
        if (this.checked) {
            label.classList.add('text-success');
            label.classList.remove('text-muted');
        } else {
            label.classList.remove('text-success');
            label.classList.add('text-muted');
        }
    });
    
    // Trigger on load
    if (!checkbox.checked) {
        var label = checkbox.nextElementSibling;
        label.classList.add('text-muted');
    }
});
</script>
@endsection
