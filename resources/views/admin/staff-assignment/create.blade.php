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
                    </div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                    </div>

                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                        @csrf

                            <!-- Staff Selection -->
                            <fieldset class="mb-4 pb-3 border-bottom">
                                <legend class="text-primary"><i class="fas fa-user"></i> {{ __('Select Staff Member') }}</legend>
                                
                                <div class="row">
                                    <div class="form-group col-md-12">
                                        <label for="user_id">{{ __('Staff') }} <span>*</span></label>
                                        <select class="form-control select2" name="user_id" id="user_id" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($staff as $member)
                                                <option value="{{ $member->id }}" {{ old('user_id') == $member->id ? 'selected' : '' }}>
                                                    {{ $member->first_name }} {{ $member->last_name }} 
                                                    ({{ $member->staff_id ?? $member->email }})
                                                    @if($member->roles->count() > 0) - {{ $member->roles->first()->name }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('Staff') }}</div>
                                    </div>
                                </div>

                                <div class="alert alert-warning" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>{{ __('Warning') }}:</strong> {{ __('Once assignments are made, this staff will ONLY have access to the assigned faculties, programs, and courses. If they have existing class routines in other areas, they will lose access to them.') }}
                                </div>
                            </fieldset>

                            <!-- Assignment Sections Container -->
                            <div id="assignmentSections">
                                <!-- First Faculty Assignment (default) -->
                                <div class="assignment-section" data-index="0">
                                    <fieldset class="mb-4 pb-3 border-bottom">
                                        <legend class="text-success d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-university"></i> {{ __('Faculty Assignment') }} #<span class="section-number">1</span></span>
                                            <button type="button" class="btn btn-sm btn-danger remove-section" style="display: none;">
                                                <i class="fas fa-times"></i> {{ __('Remove') }}
                                            </button>
                                        </legend>

                                        <div class="row">
                                            <!-- Faculty Select -->
                                            <div class="form-group col-md-12">
                                                <label class="font-weight-bold">{{ __('field_faculty') }} <span>*</span></label>
                                                <select class="form-control faculty-select" name="assignments[0][faculty_id]" required>
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach($faculties as $faculty)
                                                        <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">{{ __('Staff will have access to this faculty') }}</small>
                                                <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_faculty') }}</div>
                                            </div>

                                            <!-- Programs Select (populated dynamically) -->
                                            <div class="form-group col-md-6">
                                                <label class="font-weight-bold">{{ __('Programs') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                                <select class="form-control select2 program-select" name="assignments[0][program_ids][]" multiple disabled>
                                                    <option value="">{{ __('Select faculty first') }}</option>
                                                </select>
                                                <small class="text-muted">{{ __('Leave empty for all programs, or select specific programs') }}</small>
                                            </div>

                                            <!-- Courses Select (populated dynamically) -->
                                            <div class="form-group col-md-6">
                                                <label class="font-weight-bold">{{ __('Courses') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                                <select class="form-control select2 course-select" name="assignments[0][course_ids][]" multiple disabled>
                                                    <option value="">{{ __('Select programs first') }}</option>
                                                </select>
                                                <small class="text-muted">{{ __('Leave empty for all courses, or select specific courses') }}</small>
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>

                            <!-- Add Faculty Button -->
                            <div class="mb-4">
                                <button type="button" class="btn btn-outline-success" id="addFacultyBtn">
                                    <i class="fas fa-plus"></i> {{ __('Add Another Faculty') }}
                                </button>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> {{ __('btn_save') }} {{ __('Assignments') }}
                                </button>
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
<script>
(function($) {
    'use strict';

    let sectionIndex = 1;

    // Faculty Assignment Template
    function getFacultyAssignmentTemplate(index) {
        return `
            <div class="assignment-section" data-index="${index}">
                <fieldset class="mb-4 pb-3 border-bottom">
                    <legend class="text-success d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-university"></i> {{ __('Faculty Assignment') }} #<span class="section-number">${index + 1}</span></span>
                        <button type="button" class="btn btn-sm btn-danger remove-section">
                            <i class="fas fa-times"></i> {{ __('Remove') }}
                        </button>
                    </legend>

                    <div class="row">
                        <!-- Faculty Select -->
                        <div class="form-group col-md-12">
                            <label class="font-weight-bold">{{ __('field_faculty') }} <span>*</span></label>
                            <select class="form-control faculty-select" name="assignments[${index}][faculty_id]" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('Staff will have access to this faculty') }}</small>
                            <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_faculty') }}</div>
                        </div>

                        <!-- Programs Select -->
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">{{ __('Programs') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <select class="form-control select2 program-select" name="assignments[${index}][program_ids][]" multiple disabled>
                                <option value="">{{ __('Select faculty first') }}</option>
                            </select>
                            <small class="text-muted">{{ __('Leave empty for all programs, or select specific programs') }}</small>
                        </div>

                        <!-- Courses Select -->
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">{{ __('Courses') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <select class="form-control select2 course-select" name="assignments[${index}][course_ids][]" multiple disabled>
                                <option value="">{{ __('Select programs first') }}</option>
                            </select>
                            <small class="text-muted">{{ __('Leave empty for all courses, or select specific courses') }}</small>
                        </div>
                    </div>
                </fieldset>
            </div>
        `;
    }

    // Add Faculty Assignment Section
    $('#addFacultyBtn').on('click', function() {
        const $newSection = $(getFacultyAssignmentTemplate(sectionIndex));
        $('#assignmentSections').append($newSection);
        
        // Initialize select2 for new section
        $newSection.find('.select2').select2({
            placeholder: '{{ __("select") }}',
            allowClear: true
        });

        sectionIndex++;
        updateSectionNumbers();
    });

    // Remove Faculty Assignment Section
    $(document).on('click', '.remove-section', function() {
        $(this).closest('.assignment-section').remove();
        updateSectionNumbers();
        
        // Show remove button on first section if more than one exists
        if ($('.assignment-section').length > 1) {
            $('.assignment-section').first().find('.remove-section').show();
        } else {
            $('.assignment-section').first().find('.remove-section').hide();
        }
    });

    // Update Section Numbers
    function updateSectionNumbers() {
        $('.assignment-section').each(function(index) {
            $(this).find('.section-number').text(index + 1);
        });
    }

    // Faculty Change Handler - Load Programs
    $(document).on('change', '.faculty-select', function() {
        const $section = $(this).closest('.assignment-section');
        const $programSelect = $section.find('.program-select');
        const $courseSelect = $section.find('.course-select');
        const facultyId = $(this).val();

        // Reset dependent selects
        $programSelect.html('<option value="">{{ __("Loading...") }}</option>').prop('disabled', true);
        $courseSelect.html('<option value="">{{ __("Select programs first") }}</option>').prop('disabled', true);

        if (!facultyId) {
            $programSelect.html('<option value="">{{ __("Select faculty first") }}</option>');
            return;
        }

        // Fetch programs via AJAX
        $.ajax({
            url: '{{ route("admin.staff-assignment.get-programs") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                faculty_id: facultyId
            },
            success: function(programs) {
                $programSelect.html('<option value="">{{ __("Select programs (optional)") }}</option>');
                
                if (programs.length > 0) {
                    programs.forEach(function(program) {
                        $programSelect.append(`<option value="${program.id}">${program.title}</option>`);
                    });
                    $programSelect.prop('disabled', false);
                } else {
                    $programSelect.html('<option value="">{{ __("No programs available") }}</option>');
                }
            },
            error: function() {
                $programSelect.html('<option value="">{{ __("Error loading programs") }}</option>');
            }
        });
    });

    // Program Change Handler - Load Courses
    $(document).on('change', '.program-select', function() {
        const $section = $(this).closest('.assignment-section');
        const $courseSelect = $section.find('.course-select');
        const programIds = $(this).val();

        // Reset courses
        $courseSelect.html('<option value="">{{ __("Loading...") }}</option>').prop('disabled', true);

        if (!programIds || programIds.length === 0) {
            $courseSelect.html('<option value="">{{ __("Select programs first") }}</option>');
            return;
        }

        // Fetch courses via AJAX
        $.ajax({
            url: '{{ route("admin.staff-assignment.get-courses") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                program_ids: programIds
            },
            success: function(courses) {
                $courseSelect.html('<option value="">{{ __("Select courses (optional)") }}</option>');
                
                if (courses.length > 0) {
                    courses.forEach(function(course) {
                        $courseSelect.append(`<option value="${course.id}">${course.code} - ${course.title}</option>`);
                    });
                    $courseSelect.prop('disabled', false);
                } else {
                    $courseSelect.html('<option value="">{{ __("No courses available") }}</option>');
                }
            },
            error: function() {
                $courseSelect.html('<option value="">{{ __("Error loading courses") }}</option>');
            }
        });
    });

    // Initialize
    $(document).ready(function() {
        // Show/hide first section remove button based on count
        if ($('.assignment-section').length > 1) {
            $('.assignment-section').first().find('.remove-section').show();
        }
    });

})(jQuery);
</script>
@endsection
