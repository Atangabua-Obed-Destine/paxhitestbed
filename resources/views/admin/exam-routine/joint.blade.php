@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Card ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-file-alt"></i> {{ $title }}</h5>
                        <small class="text-muted">{{ __('Schedule the same exam for multiple programs/faculties at once') }}</small>
                    </div>
                    <div class="card-block">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>{{ __('Joint Exam Scheduling') }}:</strong> 
                            {{ __('Select multiple programs to schedule the same exam across different faculties/programs. This is useful for joint exams, shared courses, or cross-faculty examinations.') }}
                        </div>

                        @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong><i class="fas fa-exclamation-triangle"></i> {{ __('Please fix the following errors') }}:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <form class="needs-validation" novalidate action="{{ route($route.'.joint.store') }}" method="post">
                            @csrf
                            
                            <div class="row">
                                <!-- Programs Selection -->
                                <div class="col-md-12 mb-4">
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-graduation-cap"></i> {{ __('Select Programs') }} <span class="text-danger">*</span></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="selectAllPrograms">
                                                        <label class="form-check-label fw-bold" for="selectAllPrograms">
                                                            {{ __('Select All Programs') }}
                                                        </label>
                                                    </div>
                                                    <hr>
                                                </div>
                                            </div>
                                            
                                            @php
                                                $groupedPrograms = $all_programs->groupBy('faculty_id');
                                            @endphp
                                            
                                            <div class="row">
                                                @foreach($faculties as $faculty)
                                                    @if(isset($groupedPrograms[$faculty->id]))
                                                    <div class="col-md-4 mb-3">
                                                        <div class="card h-100">
                                                            <div class="card-header py-2">
                                                                <div class="form-check">
                                                                    <input class="form-check-input faculty-checkbox" type="checkbox" id="faculty_{{ $faculty->id }}" data-faculty="{{ $faculty->id }}">
                                                                    <label class="form-check-label fw-bold" for="faculty_{{ $faculty->id }}">
                                                                        {{ $faculty->title }}
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="card-body py-2" style="max-height: 200px; overflow-y: auto;">
                                                                @foreach($groupedPrograms[$faculty->id] as $program)
                                                                <div class="form-check">
                                                                    <input class="form-check-input program-checkbox" type="checkbox" name="programs[]" value="{{ $program->id }}" id="program_{{ $program->id }}" data-faculty="{{ $faculty->id }}">
                                                                    <label class="form-check-label" for="program_{{ $program->id }}">
                                                                        {{ $program->title }}
                                                                    </label>
                                                                </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                            
                                            @error('programs')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                            @enderror
                                            @error('programs.*')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Exam Details -->
                                <div class="col-md-12">
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-calendar-alt"></i> {{ __('Exam Details') }}</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="form-group col-md-4">
                                                    <label for="session">{{ __('field_session') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control @error('session') is-invalid @enderror" name="session" id="session" required>
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($sessions as $session)
                                                        <option value="{{ $session->id }}" {{ old('session', $session->current ? $session->id : '') == $session->id ? 'selected' : '' }}>{{ $session->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('session')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="semester">{{ __('field_semester') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control @error('semester') is-invalid @enderror" name="semester" id="semester" required>
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($semesters as $semester)
                                                        <option value="{{ $semester->id }}" {{ old('semester') == $semester->id ? 'selected' : '' }}>{{ $semester->title }} @if($semester->year)(Year {{ $semester->year }})@endif</option>
                                                        @endforeach
                                                    </select>
                                                    @error('semester')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="section">{{ __('field_section') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control @error('section') is-invalid @enderror" name="section" id="section" required>
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($sections as $section)
                                                        <option value="{{ $section->id }}" {{ old('section') == $section->id ? 'selected' : '' }}>{{ $section->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('section')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="subject">{{ __('field_subject') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control select2 @error('subject') is-invalid @enderror" name="subject" id="subject" required>
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($subjects as $subject)
                                                        <option value="{{ $subject->id }}" {{ old('subject') == $subject->id ? 'selected' : '' }}>{{ $subject->code }} - {{ $subject->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('subject')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="type">{{ __('field_exam_type') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control @error('type') is-invalid @enderror" name="type" id="type" required>
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($types as $type)
                                                        <option value="{{ $type->id }}" {{ old('type') == $type->id ? 'selected' : '' }}>{{ $type->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('type')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="date">{{ __('field_date') }} <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control date @error('date') is-invalid @enderror" name="date" id="date" value="{{ old('date') }}" required>
                                                    @error('date')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label for="teachers">{{ __('Supervisor(s)') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control select2 @error('teachers') is-invalid @enderror" name="teachers[]" id="teachers" multiple required>
                                                        @foreach($teachers as $teacher)
                                                        <option value="{{ $teacher->id }}" {{ (is_array(old('teachers')) && in_array($teacher->id, old('teachers'))) ? 'selected' : '' }}>{{ $teacher->staff_id }} - {{ $teacher->first_name }} {{ $teacher->last_name }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('teachers')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                    @error('teachers.*')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label for="rooms">{{ __('field_room') }} {{ __('field_no') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control select2 @error('rooms') is-invalid @enderror" name="rooms[]" id="rooms" multiple required>
                                                        @foreach($rooms as $room)
                                                        <option value="{{ $room->id }}" {{ (is_array(old('rooms')) && in_array($room->id, old('rooms'))) ? 'selected' : '' }}>{{ $room->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('rooms')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                    @error('rooms.*')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label for="start_time">{{ __('field_time') }} {{ __('field_from') }} <span class="text-danger">*</span></label>
                                                    <input type="time" class="form-control time @error('start_time') is-invalid @enderror" name="start_time" id="start_time" value="{{ old('start_time') }}" required>
                                                    @error('start_time')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label for="end_time">{{ __('field_time') }} {{ __('field_to') }} <span class="text-danger">*</span></label>
                                                    <input type="time" class="form-control time @error('end_time') is-invalid @enderror" name="end_time" id="end_time" value="{{ old('end_time') }}" required>
                                                    @error('end_time')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer text-end mt-3">
                                <a href="{{ route($route.'.create') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> {{ __('Schedule Joint Exam') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- [ Card ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
(function ($) {
    "use strict";
    
    // Restore selected programs from old input
    @if(old('programs'))
    var oldPrograms = @json(old('programs'));
    oldPrograms.forEach(function(programId) {
        $('#program_' + programId).prop('checked', true);
    });
    // Update faculty checkboxes state
    $('.faculty-checkbox').each(function() {
        var facultyId = $(this).data('faculty');
        var allInFaculty = $('.program-checkbox[data-faculty="' + facultyId + '"]');
        var checkedInFaculty = allInFaculty.filter(':checked');
        $(this).prop('checked', allInFaculty.length === checkedInFaculty.length && allInFaculty.length > 0);
    });
    updateSelectAllState();
    @endif

    // Select All Programs
    $('#selectAllPrograms').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.program-checkbox').prop('checked', isChecked);
        $('.faculty-checkbox').prop('checked', isChecked);
    });

    // Faculty checkbox - select all programs in that faculty
    $('.faculty-checkbox').on('change', function() {
        var facultyId = $(this).data('faculty');
        var isChecked = $(this).prop('checked');
        $('.program-checkbox[data-faculty="' + facultyId + '"]').prop('checked', isChecked);
        updateSelectAllState();
    });

    // Program checkbox - update faculty and select all state
    $('.program-checkbox').on('change', function() {
        var facultyId = $(this).data('faculty');
        var allInFaculty = $('.program-checkbox[data-faculty="' + facultyId + '"]');
        var checkedInFaculty = allInFaculty.filter(':checked');
        
        // Update faculty checkbox
        $('#faculty_' + facultyId).prop('checked', allInFaculty.length === checkedInFaculty.length);
        
        updateSelectAllState();
    });

    // Update select all checkbox state
    function updateSelectAllState() {
        var allPrograms = $('.program-checkbox');
        var checkedPrograms = allPrograms.filter(':checked');
        $('#selectAllPrograms').prop('checked', allPrograms.length === checkedPrograms.length);
    }

    // Date Picker
    $('.date').bootstrapMaterialDatePicker({
        time: false,
        format: 'YYYY-MM-DD',
        minDate: new Date()
    });

    // Time Picker
    $('.time').bootstrapMaterialDatePicker({
        date: false,
        shortTime: true,
        format: 'HH:mm'
    });

    // Validate at least one program is selected
    $('form').on('submit', function(e) {
        var selectedPrograms = $('.program-checkbox:checked').length;
        if (selectedPrograms === 0) {
            e.preventDefault();
            alert('{{ __("Please select at least one program") }}');
            return false;
        }

        // Validate time
        var startTime = $('#start_time').val();
        var endTime = $('#end_time').val();
        if (startTime && endTime && startTime >= endTime) {
            e.preventDefault();
            alert('{{ __("End time must be after start time") }}');
            return false;
        }

        // Validate at least one teacher is selected
        var selectedTeachers = $('#teachers').val();
        if (!selectedTeachers || selectedTeachers.length === 0) {
            e.preventDefault();
            alert('{{ __("Please select at least one teacher") }}');
            return false;
        }

        // Validate at least one room is selected
        var selectedRooms = $('#rooms').val();
        if (!selectedRooms || selectedRooms.length === 0) {
            e.preventDefault();
            alert('{{ __("Please select at least one room") }}');
            return false;
        }
    });

})(jQuery);
</script>
@endsection
