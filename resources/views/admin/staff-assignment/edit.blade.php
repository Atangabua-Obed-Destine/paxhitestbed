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
                        <h5>{{ $title }}: {{ $user->first_name }} {{ $user->last_name }}</h5>
                    </div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                    </div>

                    @if($hasClassRoutine)
                    <div class="card-block">
                        <div class="alert alert-danger" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-circle"></i> {{ __('Class Routine Detected!') }}</h5>
                            <p>{{ __('This staff member has existing class routine assignments. Updating their assignments will restrict their access to ONLY the selected areas.') }}</p>
                            <hr>
                            <p class="mb-0">
                                <strong>{{ __('Please confirm:') }}</strong> 
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="confirmOverwrite" required>
                                    <label class="form-check-label" for="confirmOverwrite">
                                        {{ __('I understand that this will restrict access and may affect existing class routines') }}
                                    </label>
                                </div>
                            </p>
                        </div>
                    </div>
                    @endif

                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.update', $user->id) }}" method="post">
                        @csrf
                        @method('PUT')

                            @if($hasClassRoutine)
                            <input type="hidden" name="overwrite_routine" id="overwriteRoutineInput" value="0">
                            @endif

                            <!-- Current Staff Info -->
                            <fieldset class="mb-4 pb-3 border-bottom">
                                <legend class="text-primary"><i class="fas fa-user"></i> {{ __('Staff Information') }}</legend>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>{{ __('Name') }}:</strong> {{ $user->first_name }} {{ $user->last_name }}</p>
                                        <p><strong>{{ __('Staff ID') }}:</strong> {{ $user->staff_id ?? '-' }}</p>
                                        <p><strong>{{ __('Email') }}:</strong> {{ $user->email }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>{{ __('Role') }}:</strong> 
                                            @foreach($user->roles as $role)
                                                <span class="badge badge-primary">{{ $role->name }}</span>
                                            @endforeach
                                        </p>
                                        <p><strong>{{ __('Total Assignments') }}:</strong> {{ $user->staffAssignments->count() }}</p>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Assignment Sections Container -->
                            <div id="assignmentSections">
                                @php
                                    $facultyIds = $currentAssignments['faculties'];
                                    $sectionIndex = 0;
                                @endphp

                                @foreach($facultyIds as $index => $facultyId)
                                    @php
                                        $faculty = $faculties->find($facultyId);
                                        // Get programs and courses for this faculty
                                        $assignedPrograms = $user->staffAssignments
                                            ->where('assignable_type', 'App\Models\Program')
                                            ->filter(function($assignment) use ($facultyId) {
                                                return $assignment->assignable && $assignment->assignable->faculty_id == $facultyId;
                                            })
                                            ->pluck('assignable_id')
                                            ->toArray();
                                        
                                        $assignedCourses = $user->staffAssignments
                                            ->where('assignable_type', 'App\Models\Subject')
                                            ->filter(function($assignment) use ($assignedPrograms) {
                                                return $assignment->assignable && in_array($assignment->assignable->program_id, $assignedPrograms);
                                            })
                                            ->pluck('assignable_id')
                                            ->toArray();
                                    @endphp

                                    <div class="assignment-section" data-index="{{ $sectionIndex }}">
                                        <fieldset class="mb-4 pb-3 border-bottom">
                                            <legend class="text-success d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-university"></i> {{ __('Faculty Assignment') }} #<span class="section-number">{{ $sectionIndex + 1 }}</span></span>
                                                @if($loop->first && count($facultyIds) > 1)
                                                <button type="button" class="btn btn-sm btn-danger remove-section">
                                                    <i class="fas fa-times"></i> {{ __('Remove') }}
                                                </button>
                                                @elseif(!$loop->first)
                                                <button type="button" class="btn btn-sm btn-danger remove-section">
                                                    <i class="fas fa-times"></i> {{ __('Remove') }}
                                                </button>
                                                @else
                                                <button type="button" class="btn btn-sm btn-danger remove-section" style="display: none;">
                                                    <i class="fas fa-times"></i> {{ __('Remove') }}
                                                </button>
                                                @endif
                                            </legend>

                                            <div class="row">
                                                <!-- Faculty Select -->
                                                <div class="form-group col-md-12">
                                                    <label class="font-weight-bold">{{ __('field_faculty') }} <span>*</span></label>
                                                    <select class="form-control faculty-select" name="assignments[{{ $sectionIndex }}][faculty_id]" required>
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($faculties as $fac)
                                                            <option value="{{ $fac->id }}" {{ $fac->id == $facultyId ? 'selected' : '' }}>{{ $fac->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_faculty') }}</div>
                                                </div>

                                                <!-- Programs Select -->
                                                <div class="form-group col-md-6">
                                                    <label class="font-weight-bold">{{ __('Programs') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                                    <select class="form-control select2 program-select" name="assignments[{{ $sectionIndex }}][program_ids][]" multiple data-faculty="{{ $facultyId }}" data-selected="{{ json_encode($assignedPrograms) }}">
                                                        <!-- Will be populated via AJAX -->
                                                    </select>
                                                    <small class="text-muted">{{ __('Leave empty for all programs') }}</small>
                                                </div>

                                                <!-- Courses Select -->
                                                <div class="form-group col-md-6">
                                                    <label class="font-weight-bold">{{ __('Courses') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                                    <select class="form-control select2 course-select" name="assignments[{{ $sectionIndex }}][course_ids][]" multiple data-programs="{{ json_encode($assignedPrograms) }}" data-selected="{{ json_encode($assignedCourses) }}">
                                                        <!-- Will be populated via AJAX -->
                                                    </select>
                                                    <small class="text-muted">{{ __('Leave empty for all courses') }}</small>
                                                </div>
                                            </div>
                                        </fieldset>
                                    </div>
                                    @php $sectionIndex++; @endphp
                                @endforeach
                            </div>

                            <!-- Add Faculty Button -->
                            <div class="mb-4">
                                <button type="button" class="btn btn-outline-success" id="addFacultyBtn">
                                    <i class="fas fa-plus"></i> {{ __('Add Another Faculty') }}
                                </button>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-save"></i> {{ __('btn_update') }} {{ __('Assignments') }}
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

    let sectionIndex = {{ count($facultyIds) }};
    const hasClassRoutine = {{ $hasClassRoutine ? 'true' : 'false' }};

    // Confirm overwrite checkbox handler
    if (hasClassRoutine) {
        $('#confirmOverwrite').on('change', function() {
            $('#overwriteRoutineInput').val(this.checked ? '1' : '0');
            $('#submitBtn').prop('disabled', !this.checked);
        });

        // Disable submit by default if has class routine
        $('#submitBtn').prop('disabled', true);
    }

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
                        <div class="form-group col-md-12">
                            <label class="font-weight-bold">{{ __('field_faculty') }} <span>*</span></label>
                            <select class="form-control faculty-select" name="assignments[${index}][faculty_id]" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_faculty') }}</div>
                        </div>

                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">{{ __('Programs') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <select class="form-control select2 program-select" name="assignments[${index}][program_ids][]" multiple disabled>
                                <option value="">{{ __('Select faculty first') }}</option>
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">{{ __('Courses') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <select class="form-control select2 course-select" name="assignments[${index}][course_ids][]" multiple disabled>
                                <option value="">{{ __('Select programs first') }}</option>
                            </select>
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
        
        $newSection.find('.select2').select2({
            placeholder: '{{ __("select") }}',
            allowClear: true
        });

        sectionIndex++;
        updateSectionNumbers();
        updateRemoveButtons();
    });

    // Remove Faculty Assignment Section
    $(document).on('click', '.remove-section', function() {
        $(this).closest('.assignment-section').remove();
        updateSectionNumbers();
        updateRemoveButtons();
    });

    // Update Section Numbers
    function updateSectionNumbers() {
        $('.assignment-section').each(function(index) {
            $(this).find('.section-number').text(index + 1);
        });
    }

    // Update Remove Buttons Visibility
    function updateRemoveButtons() {
        const $sections = $('.assignment-section');
        if ($sections.length > 1) {
            $sections.find('.remove-section').show();
        } else {
            $sections.find('.remove-section').hide();
        }
    }

    // Faculty Change Handler
    $(document).on('change', '.faculty-select', function() {
        const $section = $(this).closest('.assignment-section');
        const $programSelect = $section.find('.program-select');
        const $courseSelect = $section.find('.course-select');
        const facultyId = $(this).val();

        $programSelect.html('<option value="">{{ __("Loading...") }}</option>').prop('disabled', true);
        $courseSelect.html('<option value="">{{ __("Select programs first") }}</option>').prop('disabled', true);

        if (!facultyId) {
            $programSelect.html('<option value="">{{ __("Select faculty first") }}</option>');
            return;
        }

        $.ajax({
            url: '{{ route("admin.staff-assignment.get-programs") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                faculty_id: facultyId
            },
            success: function(programs) {
                $programSelect.html('');
                programs.forEach(function(program) {
                    $programSelect.append(`<option value="${program.id}">${program.title}</option>`);
                });
                $programSelect.prop('disabled', false);
            }
        });
    });

    // Program Change Handler
    $(document).on('change', '.program-select', function() {
        const $section = $(this).closest('.assignment-section');
        const $courseSelect = $section.find('.course-select');
        const programIds = $(this).val();

        $courseSelect.html('<option value="">{{ __("Loading...") }}</option>').prop('disabled', true);

        if (!programIds || programIds.length === 0) {
            $courseSelect.html('<option value="">{{ __("Select programs first") }}</option>');
            return;
        }

        $.ajax({
            url: '{{ route("admin.staff-assignment.get-courses") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                program_ids: programIds
            },
            success: function(courses) {
                $courseSelect.html('');
                courses.forEach(function(course) {
                    $courseSelect.append(`<option value="${course.id}">${course.code} - ${course.title}</option>`);
                });
                $courseSelect.prop('disabled', false);
            }
        });
    });

    // Initialize existing assignments
    $(document).ready(function() {
        $('.faculty-select').each(function() {
            const $section = $(this).closest('.assignment-section');
            const $programSelect = $section.find('.program-select');
            const $courseSelect = $section.find('.course-select');
            const facultyId = $programSelect.data('faculty');
            const selectedPrograms = $programSelect.data('selected');
            const selectedCourses = $courseSelect.data('selected');

            if (facultyId) {
                // Load programs
                $.ajax({
                    url: '{{ route("admin.staff-assignment.get-programs") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        faculty_id: facultyId
                    },
                    success: function(programs) {
                        $programSelect.html('');
                        programs.forEach(function(program) {
                            const isSelected = selectedPrograms.includes(program.id);
                            $programSelect.append(`<option value="${program.id}" ${isSelected ? 'selected' : ''}>${program.title}</option>`);
                        });
                        $programSelect.prop('disabled', false);

                        // Load courses if programs selected
                        if (selectedPrograms.length > 0) {
                            $.ajax({
                                url: '{{ route("admin.staff-assignment.get-courses") }}',
                                method: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}',
                                    program_ids: selectedPrograms
                                },
                                success: function(courses) {
                                    $courseSelect.html('');
                                    courses.forEach(function(course) {
                                        const isSelected = selectedCourses.includes(course.id);
                                        $courseSelect.append(`<option value="${course.id}" ${isSelected ? 'selected' : ''}>${course.code} - ${course.title}</option>`);
                                    });
                                    $courseSelect.prop('disabled', false);
                                }
                            });
                        }
                    }
                });
            }
        });

        updateRemoveButtons();
    });

})(jQuery);
</script>
@endsection
