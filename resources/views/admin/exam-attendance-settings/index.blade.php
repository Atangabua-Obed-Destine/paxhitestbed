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
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <span>Configure minimum course attendance percentage required for students to be eligible to write <strong>final exams</strong></span>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.update') }}" method="post" enctype="multipart/form-data">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="minimum_attendance_percentage">{{ __('field_minimum_attendance_percentage') }} <span>*</span></label>
                                        <input type="number" class="form-control" name="minimum_attendance_percentage" id="minimum_attendance_percentage" value="{{ old('minimum_attendance_percentage', $setting->minimum_attendance_percentage) }}" min="0" max="100" step="0.01" required>

                                        <div class="invalid-feedback">
                                            {{ __('required_field') }} {{ __('field_minimum_attendance_percentage') }}
                                        </div>

                                        <small class="form-text text-muted">
                                            Students with course attendance below this percentage will not be eligible to write <strong>final exams</strong> (default: 70%). This does not apply to continuous assessments or other non-final exam types.
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="is_enabled">{{ __('field_status') }}</label>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="is_enabled" name="is_enabled" value="1" {{ $setting->is_enabled ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_enabled">{{ __('Enable Course Attendance Check') }}</label>
                                        </div>

                                        <small class="form-text text-muted">
                                            When disabled, students can write final exams regardless of their course attendance
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> How it works:</h6>
                                        <ul class="mb-0">
                                            <li><strong>Only applies to Final Exams</strong> (exam types with is_final = 1). Continuous assessments and other non-final exams are not affected.</li>
                                            <li>When taking final exam attendance, the system calculates each student's course attendance percentage</li>
                                            <li>Students below the minimum percentage are automatically marked as "Absent" and cannot be changed</li>
                                            <li>Users with "Exam Attendance Bypass" permission can override this using the bypass checkbox</li>
                                            <li>Course attendance is calculated from the Student Attendance module (Present days / Working days × 100)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
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
</script>
@endsection
