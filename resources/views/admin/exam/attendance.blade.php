@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    /* Attendance Status Indicator Styles */
    .attendance-status-indicator .status-content {
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        transition: all 0.3s ease;
    }
    .attendance-status-indicator .status-content.status-not_taken {
        background: #fff3cd;
        border-color: #ffc107;
    }
    .attendance-status-indicator .status-content.status-partial {
        background: #d1ecf1;
        border-color: #17a2b8;
    }
    .attendance-status-indicator .status-content.status-completed {
        background: #d4edda;
        border-color: #28a745;
    }
    .attendance-status-indicator .status-content.status-no_students {
        background: #e9ecef;
        border-color: #6c757d;
    }
    .attendance-status-indicator .status-icon {
        font-size: 14px;
    }
    .attendance-status-indicator .status-message {
        font-weight: 500;
    }
    .attendance-status-indicator .status-details span {
        font-size: 11px;
    }
    .attendance-status-indicator .status-details .present-count::before {
        content: '\f00c';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        margin-right: 2px;
    }
    .attendance-status-indicator .status-details .absent-count::before {
        content: '\f00d';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        margin-right: 2px;
    }
    .attendance-status-indicator .status-details .pending-count::before {
        content: '\f017';
        font-family: 'Font Awesome 5 Free';
        font-weight: 400;
        margin-right: 2px;
    }
    .status-loading {
        text-align: center;
        color: #6c757d;
    }
    .status-loading i {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    }
    .course-card {
        transition: all 0.3s ease;
    }
    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    .badge-outline-info {
        background-color: transparent;
        border: 1px solid #36b9cc;
        color: #36b9cc;
    }
    .opacity-75 {
        opacity: 0.75;
    }
    #quickProgramSelector {
        border-left: none;
        font-weight: 500;
    }
    .program-section {
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            
            {{-- My Assigned Courses Quick Access Section --}}
            @if(isset($myAssignedCourses) && $myAssignedCourses->count() > 0)
            <div class="col-sm-12">
                <div class="card border-primary shadow-sm">
                    <div class="card-header bg-gradient-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-chalkboard-teacher"></i> {{ __('My Assigned Courses') }}
                                </h5>
                                <small class="opacity-75">
                                    @if(isset($currentSession))
                                        <i class="fas fa-calendar-alt"></i> {{ $currentSession->title }}
                                    @endif
                                    • {{ $myAssignedCourses->sum(function($p) { return $p['subjects']->count(); }) }} {{ __('Total Courses') }}
                                </small>
                            </div>
                            <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#myCoursesCollapse" aria-expanded="false">
                                <i class="fas fa-chevron-down" id="collapseIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="collapse" id="myCoursesCollapse">
                        <div class="card-body">
                            {{-- Program Selector Dropdown --}}
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-primary text-white">
                                                <i class="fas fa-graduation-cap"></i>
                                            </span>
                                        </div>
                                        <select class="form-control" id="quickProgramSelector">
                                            <option value="all">{{ __('All Programs') }} ({{ $myAssignedCourses->count() }})</option>
                                            @foreach($myAssignedCourses as $idx => $programData)
                                            <option value="{{ $idx }}">{{ $programData['program_title'] }} ({{ $programData['subjects']->count() }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary active" id="viewCards">
                                            <i class="fas fa-th-large"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" id="viewTable">
                                            <i class="fas fa-list"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Courses by Program --}}
                            <div id="coursesContainer">
                                @foreach($myAssignedCourses as $programIndex => $programData)
                                <div class="program-section mb-4" data-program-index="{{ $programIndex }}">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-folder-open"></i> {{ $programData['program_title'] }}
                                        <span class="badge badge-secondary">{{ $programData['subjects']->count() }} {{ __('courses') }}</span>
                                    </h6>

                                    {{-- Card View --}}
                                    <div class="row course-cards-view">
                                        @foreach($programData['subjects'] as $subjectIndex => $subjectData)
                                        @php 
                                            $uniqueId = $programIndex . '_' . $subjectIndex;
                                        @endphp
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="card h-100 border-left-primary shadow-sm course-card" style="border-left: 4px solid #4e73df !important;">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <span class="badge badge-dark">{{ $subjectData['subject_code'] }}</span>
                                                        <span class="badge badge-outline-info">
                                                            <i class="fas fa-users"></i> {{ $subjectData['sections']->count() }} {{ __('Section(s)') }}
                                                        </span>
                                                    </div>
                                                    
                                                    <h6 class="font-weight-bold text-dark mb-2" title="{{ $subjectData['subject_title'] }}">
                                                        {{ Str::limit($subjectData['subject_title'], 35) }}
                                                    </h6>

                                                    <div class="mb-3">
                                                        <span class="badge badge-primary" title="{{ $programData['program_title'] }}">
                                                            <i class="fas fa-graduation-cap"></i> {{ Str::limit($programData['program_code'], 20) }}
                                                        </span>
                                                    </div>

                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <label class="small text-muted mb-1">{{ __('Year') }}</label>
                                                            <select class="form-control form-control-sm quick-year" 
                                                                    id="year_{{ $uniqueId }}"
                                                                    data-unique="{{ $uniqueId }}">
                                                                @foreach($subjectData['years'] as $yr)
                                                                <option value="{{ $yr }}" {{ $yr == $subjectData['first_year'] ? 'selected' : '' }}>
                                                                    {{ __('Year') }} {{ $yr }}
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="small text-muted mb-1">{{ __('Semester') }}</label>
                                                            <select class="form-control form-control-sm quick-semester" 
                                                                    id="semester_{{ $uniqueId }}"
                                                                    data-subject="{{ $subjectData['subject_id'] }}"
                                                                    data-semesters="{{ $subjectData['semesters']->toJson() }}">
                                                                @foreach($subjectData['semesters'] as $sem)
                                                                <option value="{{ $sem->id }}" 
                                                                        data-year="{{ $sem->year }}"
                                                                        data-is-resit="{{ $sem->is_resit ? '1' : '0' }}"
                                                                        {{ $sem->id == $subjectData['first_semester_id'] ? 'selected' : '' }}>
                                                                    {{ $sem->title }}@if($sem->is_resit) (Resit)@endif
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <label class="small text-muted mb-1">{{ __('Section') }}</label>
                                                            @if($subjectData['sections']->count() > 1)
                                                            <select class="form-control form-control-sm quick-section" id="section_{{ $uniqueId }}">
                                                                <option value="0">{{ __('All Sections') }}</option>
                                                                @foreach($subjectData['sections'] as $secIdx => $sec)
                                                                <option value="{{ $sec->id }}" {{ $secIdx === 0 ? 'selected' : '' }}>{{ $sec->title }}</option>
                                                                @endforeach
                                                            </select>
                                                            @else
                                                            <input type="text" class="form-control form-control-sm" value="{{ $subjectData['first_section_title'] }}" disabled>
                                                            <input type="hidden" id="section_{{ $uniqueId }}" value="{{ $subjectData['first_section_id'] ?? 0 }}">
                                                            @endif
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="small text-muted mb-1">{{ __('Exam Type') }} <span class="text-danger">*</span></label>
                                                            <select class="form-control form-control-sm quick-exam-type" id="examtype_{{ $uniqueId }}" required>
                                                                <option value="">-- {{ __('Select') }} --</option>
                                                                @foreach($examTypes as $et)
                                                                <option value="{{ $et->id }}">{{ $et->title }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <button type="button" 
                                                            class="btn btn-info btn-block quick-take-attendance"
                                                            data-faculty="{{ $programData['faculty_id'] }}"
                                                            data-program="{{ $programData['program_id'] }}"
                                                            data-session="{{ $currentSession->id ?? '' }}"
                                                            data-subject="{{ $subjectData['subject_id'] }}"
                                                            data-unique="{{ $uniqueId }}">
                                                        <i class="fas fa-user-check"></i> {{ __('Take Attendance') }}
                                                    </button>
                                                    
                                                    {{-- Status Indicator --}}
                                                    <div class="attendance-status-indicator mt-2" id="status_{{ $uniqueId }}" style="display: none;">
                                                        <div class="status-content d-flex align-items-center justify-content-between p-2 rounded">
                                                            <div class="status-info d-flex align-items-center">
                                                                <i class="status-icon mr-2"></i>
                                                                <span class="status-message small"></span>
                                                            </div>
                                                            <div class="status-details small">
                                                                <span class="present-count text-success" title="Present"></span>
                                                                <span class="absent-count text-danger ml-1" title="Absent"></span>
                                                                <span class="pending-count text-warning ml-1" title="Pending"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>

                                    {{-- Table View (Hidden by default) --}}
                                    <div class="course-table-view d-none">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover table-bordered mb-0">
                                                <thead class="thead-dark">
                                                    <tr>
                                                        <th>{{ __('Code') }}</th>
                                                        <th>{{ __('Course Title') }}</th>
                                                        <th>{{ __('Program') }}</th>
                                                        <th>{{ __('Year') }}</th>
                                                        <th>{{ __('Semester') }}</th>
                                                        <th>{{ __('Section') }}</th>
                                                        <th>{{ __('Exam Type') }}</th>
                                                        <th>{{ __('Action') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($programData['subjects'] as $subjectIndex => $subjectData)
                                                    @php 
                                                        $uniqueIdTable = 'tbl_' . $programIndex . '_' . $subjectIndex;
                                                    @endphp
                                                    <tr>
                                                        <td><span class="badge badge-secondary">{{ $subjectData['subject_code'] }}</span></td>
                                                        <td><strong>{{ Str::limit($subjectData['subject_title'], 25) }}</strong></td>
                                                        <td>
                                                            <span class="badge badge-primary" title="{{ $programData['program_title'] }}">
                                                                {{ Str::limit($programData['program_code'], 15) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <select class="form-control form-control-sm quick-year" 
                                                                    id="year_{{ $uniqueIdTable }}"
                                                                    data-unique="{{ $uniqueIdTable }}">
                                                                @foreach($subjectData['years'] as $yr)
                                                                <option value="{{ $yr }}" {{ $yr == $subjectData['first_year'] ? 'selected' : '' }}>
                                                                    {{ __('Year') }} {{ $yr }}
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select class="form-control form-control-sm quick-semester" 
                                                                    id="semester_{{ $uniqueIdTable }}"
                                                                    data-subject="{{ $subjectData['subject_id'] }}"
                                                                    data-semesters="{{ $subjectData['semesters']->toJson() }}">
                                                                @foreach($subjectData['semesters'] as $sem)
                                                                <option value="{{ $sem->id }}" 
                                                                        data-year="{{ $sem->year }}"
                                                                        data-is-resit="{{ $sem->is_resit ? '1' : '0' }}"
                                                                        {{ $sem->id == $subjectData['first_semester_id'] ? 'selected' : '' }}>
                                                                    {{ $sem->title }}@if($sem->is_resit) (Resit)@endif
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            @if($subjectData['sections']->count() > 1)
                                                            <select class="form-control form-control-sm quick-section" id="section_{{ $uniqueIdTable }}">
                                                                <option value="0">{{ __('All') }}</option>
                                                                @foreach($subjectData['sections'] as $secIdx => $sec)
                                                                <option value="{{ $sec->id }}" {{ $secIdx === 0 ? 'selected' : '' }}>{{ $sec->title }}</option>
                                                                @endforeach
                                                            </select>
                                                            @else
                                                            <span class="badge badge-light">{{ $subjectData['first_section_title'] }}</span>
                                                            <input type="hidden" id="section_{{ $uniqueIdTable }}" value="{{ $subjectData['first_section_id'] ?? 0 }}">
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <select class="form-control form-control-sm quick-exam-type" id="examtype_{{ $uniqueIdTable }}" required>
                                                                <option value="">{{ __('Select') }}</option>
                                                                @foreach($examTypes as $et)
                                                                <option value="{{ $et->id }}">{{ $et->title }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-info quick-take-attendance"
                                                                    data-faculty="{{ $programData['faculty_id'] }}"
                                                                    data-program="{{ $programData['program_id'] }}"
                                                                    data-session="{{ $currentSession->id ?? '' }}"
                                                                    data-subject="{{ $subjectData['subject_id'] }}"
                                                                    data-unique="{{ $uniqueIdTable }}">
                                                                <i class="fas fa-user-check"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Divider --}}
            @if(isset($myAssignedCourses) && $myAssignedCourses->count() > 0)
            <div class="col-sm-12 my-3">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 border-bottom"></div>
                    <span class="badge badge-secondary px-4 py-2 mx-3">
                        <i class="fas fa-filter"></i> {{ __('Advanced Filter') }}
                    </span>
                    <div class="flex-grow-1 border-bottom"></div>
                </div>
            </div>
            @endif

            <div class="col-sm-12">
                <div class="card" id="advancedFilterCard">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>

                        @can($access.'-import')
                        <a href="{{ route($route.'.import') }}" class="btn btn-dark btn-sm float-right"><i class="fas fa-upload"></i> {{ __('btn_import') }}</a>
                        @endcan
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}" id="advancedFilterForm">
                            {{-- Preserve cross-programme toggle state across filter submissions --}}
                            @if($cross_program)
                            <input type="hidden" name="cross_program" id="crossProgramHidden" value="1">
                            @endif
                            <div class="row gx-2">
                                @include('common.inc.subject_search_filter')

                                <div class="form-group col-md-3">
                                    <label for="type">{{ __('field_type') }} <span>*</span></label>
                                    <select class="form-control" name="type" id="type" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach( $types as $type )
                                        <option value="{{ $type->id }}" @if( $selected_type == $type->id) selected @endif>{{ $type->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_type') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 CROSS-PROGRAMME TOGGLE
                 Allows supervisors to see students from all programmes taking
                 the same course (useful for general/shared courses).
                 Only shows when a subject has been selected and results exist.
                 ══════════════════════════════════════════════════════════════════ --}}
            @if(isset($rows) && isset($sharing_programs) && $sharing_programs->count() > 1)
            <div class="col-sm-12">
                <div class="card mb-2" style="border-left: 4px solid {{ $cross_program ? '#17a2b8' : '#6c757d' }}; transition: border-color 0.3s;">
                    <div class="card-block py-3 px-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            {{-- Toggle switch --}}
                            <div class="d-flex align-items-center">
                                <div class="custom-control custom-switch mr-3">
                                    <input type="checkbox" class="custom-control-input" id="crossProgramToggle"
                                        {{ $cross_program ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="crossProgramToggle" style="font-size: 14px; cursor: pointer;">
                                        <i class="fas fa-globe mr-1 {{ $cross_program ? 'text-info' : 'text-muted' }}"></i>
                                        All Programmes Mode
                                    </label>
                                </div>

                                <span class="text-muted" style="font-size: 13px;">
                                    @if($cross_program)
                                        Showing students from <strong class="text-info">{{ $sharing_programs->count() }} programmes</strong> taking this course
                                    @else
                                        This course is shared by <strong>{{ $sharing_programs->count() }} programmes</strong> &mdash; toggle to see all
                                    @endif
                                </span>
                            </div>

                            {{-- Sharing programmes pills (collapsible) --}}
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#sharingProgramsList" aria-expanded="false">
                                    <i class="fas fa-sitemap mr-1"></i> View Programmes
                                </button>
                            </div>
                        </div>

                        {{-- Collapsible programmes list --}}
                        <div class="collapse {{ $cross_program ? 'show' : '' }} mt-3" id="sharingProgramsList">
                            <div class="border rounded p-3" style="background: #f8f9fa;">
                                <small class="text-muted d-block mb-2">
                                    <i class="fas fa-info-circle"></i> Programmes offering this course:
                                </small>
                                <div class="d-flex flex-wrap" style="gap: 6px;">
                                    @foreach($sharing_programs as $sp)
                                        @php
                                            $isCurrentProgram = ($sp->id == $selected_program);
                                            $badgeClass = $isCurrentProgram ? 'badge-primary' : 'badge-light border';
                                        @endphp
                                        <span class="badge {{ $badgeClass }} px-2 py-1" style="font-size: 12px;">
                                            @if($isCurrentProgram)
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                            @endif
                                            {{ $sp->title }}
                                            @if($sp->faculty)
                                                <small class="ml-1 opacity-75">({{ Str::limit($sp->faculty->title, 20) }})</small>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                                @if($cross_program)
                                <div class="mt-2">
                                    <small class="text-info">
                                        <i class="fas fa-check-circle"></i>
                                        Cross-programme mode is <strong>active</strong>. Students from all {{ $sharing_programs->count() }} programmes are shown below, grouped by programme.
                                    </small>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @elseif(isset($rows) && isset($sharing_programs) && $sharing_programs->count() == 1)
            {{-- Single programme - show a subtle note --}}
            <div class="col-sm-12">
                <div class="text-muted text-center mb-2" style="font-size: 12px;">
                    <i class="fas fa-lock"></i> This course is exclusive to <strong>{{ $sharing_programs->first()->title }}</strong>
                </div>
            </div>
            @endif

            <div class="col-sm-12">
                <div class="card">
                    @if(isset($rows))
                    <div class="card-block">
                        {{-- Check if exam scheduling is required but not done (only for final exams) --}}
                        @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1 && isset($can_take_attendance) && !$can_take_attendance)
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <strong>{{ __('Exam Not Scheduled') }}:</strong> 
                            This is a final exam and has not been scheduled in the <a href="{{ route('admin.exam-routine.create') }}">Exam Routine</a>. 
                            Please schedule the exam first before taking attendance.
                        </div>
                        @else
                            @if(isset($attendances))
                            @if(count($attendances) > 0)
                            <div class="alert alert-success" role="alert">
                                {{ __('attendance_taken') }}
                            </div>
                            @else
                            <div class="alert alert-danger" role="alert">
                                {{ __('attendance_not_taken') }}
                            </div>
                            @endif
                            @endif
                            
                            {{-- Show exam schedule info if available --}}
                            @if(isset($exam_routine) && $exam_routine)
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-calendar-alt"></i> <strong>{{ __('Scheduled') }}:</strong> 
                                {{ \Carbon\Carbon::parse($exam_routine->date)->format('d M, Y') }} 
                                ({{ \Carbon\Carbon::parse($exam_routine->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($exam_routine->end_time)->format('h:i A') }})
                            </div>
                            @elseif(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 0)
                            <div class="alert alert-warning" role="alert">
                                <i class="fas fa-info-circle"></i> <strong>{{ __('Note') }}:</strong> 
                                This is a non-final exam ({{ $selected_exam_type->title }}). Attendance can be taken without exam scheduling.
                            </div>
                            @endif
                        @endif
                    </div>
                    @endif
                    
                    @if(isset($rows) && isset($can_take_attendance) && $can_take_attendance)
                    <div class="card-header">
                        <div class="form-group d-inline">
                            <div class="radio radio-primary d-inline">
                                <input type="radio" name="all_check" id="attendance-p" class="all_present">
                                <label for="attendance-p" class="cr">{{ __('all') }} {{ __('attendance_present') }}</label>
                            </div>
                        </div>
                        <div class="form-group d-inline">
                            <div class="radio radio-danger d-inline">
                                <input type="radio" name="all_check" id="attendance-a" class="all_absent">
                                <label for="attendance-a" class="cr">{{ __('all') }} {{ __('attendance_absent') }}</label>
                            </div>
                        </div>

                        <a href="{{ route($route.'.index') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>

                        @if(isset($rows))
                        <button type="button" class="btn btn-dark btn-print">
                            <i class="fas fa-print"></i> {{ __('btn_print') }}
                        </button>
                        @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                        <a href="#" id="btn-print-sheet" class="btn btn-outline-dark"
                           data-url="{{ route('admin.exam-attendance.print', ['faculty'=>$selected_faculty,'program'=>$selected_program,'session'=>$selected_session,'semester'=>$selected_semester,'section'=>$selected_section,'subject'=>$selected_subject,'type'=>$selected_type]) }}">
                            <i class="fas fa-file-signature"></i> {{ __('Print Blank Sign-In/Out Sheet') }}
                        </a>
                        @endif
                        @endif
                        <div class="clearfix"></div>
                    </div>

                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf

                    @if(isset($attendances))
                    @foreach($attendances as $attendance)
                        @if($loop->first)
                        @php
                            $check_data = $attendance;
                        @endphp
                        @endif
                    @endforeach
                    @endif
                    
                    <div class="card-block">
                        <div class="row">
                            <div class="form-group col-sm-6 col-md-3">
                                <label for="date">{{ __('field_date') }} <span>*</span></label>
                                <input type="date" class="form-control date" name="date" id="date" value="{{ $check_data->date ?? '' }}" required>
                                    
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_date') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="subject" value="{{ $selected_subject }}">
                    <input type="hidden" name="type" value="{{ $selected_type }}">
                    <input type="hidden" name="attendances" class="attendances" value="">
                    <input type="hidden" name="bypasses" class="bypasses" value="">
                    @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                    <input type="hidden" name="signins" class="signins" value="">
                    <input type="hidden" name="signouts" class="signouts" value="">
                    @endif

                    <div class="card-block">
                        @if(isset($attendance_setting) && $attendance_setting && $attendance_setting->is_enabled && isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Final Exam Attendance Eligibility:</strong> Students require minimum <strong>{{ $attendance_setting->minimum_attendance_percentage }}%</strong> course attendance to be eligible for final exams.
                            Students below this threshold are automatically marked as "Absent" and cannot write the exam unless bypassed.
                        </div>
                        @endif
                        
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover printable">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_serial') }}</th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ trans_choice('module_program', 1) }}</th>
                                        @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                                        <th>{{ __('Attendance Mark') }}</th>
                                        <th>Eligibility</th>
                                        @foreach(($ca_types ?? collect()) as $ct)
                                        <th title="{{ __('CA') }} / {{ rtrim(rtrim(number_format($ct->marks,2),'0'),'.') }}">{{ $ct->title }}</th>
                                        @endforeach
                                        <th>{{ __('Sign In') }}</th>
                                        <th>{{ __('Sign Out') }}</th>
                                        @endif
                                        <th>{{ __('field_attendance') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                                        @can('exam-attendance-bypass')
                                        <th>Bypass</th>
                                        @endcan
                                        @endif
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach( $subjects as $subject )
                                    @if($subject->id == $selected_subject)
                                    @php
                                        $cur_subject = $subject->code;
                                    @endphp
                                    @endif
                                    @endforeach
                                    @foreach( $types as $type )
                                    @if($type->id == $selected_type)
                                    @php
                                        $cur_type = $type->title;
                                    @endphp
                                    @endif
                                    @endforeach
                                  @php $prevProgramId = null; @endphp
                                  @foreach( $rows as $key => $row )
                                    @php
                                        // Check if this is a final exam
                                        $is_final_exam = isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1;
                                        
                                        // Get attendance data for this student (only for final exams)
                                        $attendance_data = null;
                                        $attendance_percentage = null;   // null = no class attendance recorded (N/A)
                                        $attendance_mark = null;
                                        $attendance_contribution = 0;
                                        $no_attendance_records = false;

                                        if($is_final_exam){
                                            $attendance_data = $student_attendance_percentages[$row->id] ?? null;
                                            $attendance_percentage = $attendance_data['percentage'] ?? null;
                                            $attendance_mark = $attendance_data['attendance_mark'] ?? null;
                                            $attendance_contribution = $attendance_data['attendance_contribution'] ?? 0;
                                            $no_attendance_records = $is_final_exam && is_null($attendance_percentage);
                                        }

                                        // Check if student is eligible (only for final exams).
                                        // No class attendance recorded (null %) ⇒ eligible (do not block).
                                        $is_eligible = true;
                                        $minimum_percentage = $attendance_setting->minimum_attendance_percentage ?? 70;

                                        if($is_final_exam && $attendance_setting && $attendance_setting->is_enabled){
                                            $is_eligible = is_null($attendance_percentage) ? true : ($attendance_percentage >= $minimum_percentage);
                                        }
                                        
                                        // Check if bypass is already set for this student
                                        $is_bypassed = false;
                                        if($is_final_exam && isset($attendances)){
                                            foreach($attendances as $attendance){
                                                if($attendance->student_enroll_id == $row->id && $attendance->bypass_course_attendance){
                                                    $is_bypassed = true;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        // Student can attend if: not a final exam OR eligible OR bypassed
                                        $can_attend = !$is_final_exam || $is_eligible || $is_bypassed;
                                        
                                        // Show warning even when bypassed (for visibility)
                                        $show_warning = $is_final_exam && !$is_eligible;

                                        // Check lock
                                        $is_locked = false;
                                        if(isset($attendances)){
                                            foreach($attendances as $attendance){
                                                if($attendance->student_enroll_id == $row->id){
                                                    $is_locked = $attendance->attendance_locked;
                                                    break;
                                                }
                                            }
                                        }

                                        // Cross-programme group separator detection
                                        $currentProgramId = $row->program_id;
                                        $isNewProgramGroup = isset($cross_program) && $cross_program && ($currentProgramId !== $prevProgramId);
                                    @endphp

                                    {{-- Programme group separator (cross-programme mode only) --}}
                                    @if($isNewProgramGroup)
                                    @php
                                        // Count students in this programme group
                                        $programGroupCount = collect($rows)->where('program_id', $currentProgramId)->count();
                                        $colSpan = 8 + ($is_final_exam ? 2 : 0) + ($is_final_exam && auth()->user()->can('exam-attendance-bypass') ? 1 : 0);
                                    @endphp
                                    <tr class="cross-program-separator" style="background: linear-gradient(135deg, #e8f4fd 0%, #f0f7ff 100%); border-top: 2px solid #17a2b8;">
                                        <td colspan="{{ $colSpan }}" class="py-2 px-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <i class="fas fa-graduation-cap text-info mr-2"></i>
                                                    <strong style="font-size: 13px; color: #0c5460;">{{ $row->program->title ?? 'Unknown Programme' }}</strong>
                                                    @if($row->program && $row->program->faculty)
                                                        <small class="text-muted ml-2">({{ $row->program->faculty->title ?? '' }})</small>
                                                    @endif
                                                </div>
                                                <span class="badge badge-info">{{ $programGroupCount }} {{ Str::plural('student', $programGroupCount) }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @php $prevProgramId = $currentProgramId; @endphp

                                    <tr class="{{ $show_warning ? 'table-danger' : '' }}" style="{{ $is_bypassed ? 'background-color: #fff3cd !important;' : '' }}">
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            @isset($row->matricule)
                                            <a href="{{ route('admin.student.show', $row->student->id) }}">
                                            <strong style="color: #667eea;">#{{ $row->matricule ?? '' }}</strong>
                                            @if($row->program)
                                                <br>
                                                <span class="badge" style="background: {{ $row->program->academic_level == 'M' ? '#f5576c' : ($row->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px;">
                                                    {{ $row->program->academic_level == 'A' ? 'UG' : ($row->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            @endif
                                            </a>
                                            @endisset
                                        </td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td>
                                            @if($row->program)
                                                <span class="badge badge-light border" style="font-size: 11px; white-space: normal; text-align: left; max-width: 180px;">
                                                    {{ Str::limit($row->program->title ?? '', 30) }}
                                                </span>
                                                @if(isset($cross_program) && $cross_program && $row->program_id == $selected_program)
                                                    <br><small class="text-primary"><i class="fas fa-map-marker-alt"></i> primary</small>
                                                @endif
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        @if($is_final_exam)
                                        <td>
                                            @if($no_attendance_records)
                                                <span class="badge badge-secondary">N/A</span>
                                                <small class="d-block text-muted">{{ __('No class attendance recorded') }}</small>
                                            @elseif($attendance_data)
                                                <span class="badge badge-{{ $attendance_percentage >= $minimum_percentage ? 'success' : 'danger' }}">
                                                    {{ rtrim(rtrim(number_format($attendance_mark,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($attendance_contribution,2),'0'),'.') }}
                                                </span>
                                                <small class="d-block text-muted">
                                                    {{ $attendance_percentage }}% · P:{{ $attendance_data['present'] }} A:{{ $attendance_data['absent'] }} L:{{ $attendance_data['leave'] }}
                                                </small>
                                            @else
                                                <span class="badge badge-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($is_bypassed)
                                                <span class="badge badge-warning"><i class="fas fa-check-circle"></i> Bypassed</span>
                                            @elseif($is_eligible)
                                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Eligible</span>
                                            @else
                                                <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Not Eligible</span>
                                            @endif
                                        </td>
                                        @endif

                                        @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                                        @foreach(($ca_types ?? collect()) as $ct)
                                            @php $cm = $ca_marks[$row->id][$ct->id] ?? null; @endphp
                                            <td class="text-center">
                                                @if($cm && !is_null($cm['achieve']))
                                                    {{ rtrim(rtrim(number_format($cm['achieve'],2),'0'),'.') }} / {{ rtrim(rtrim(number_format($cm['marks'],2),'0'),'.') }}
                                                @else
                                                    <span class="text-muted">&mdash;</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        @endif

                                        @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                                        @php
                                            $rowSignIn = false; $rowSignOut = false;
                                            if(isset($attendances)){
                                                foreach($attendances as $att){
                                                    if($att->student_enroll_id == $row->id){
                                                        $rowSignIn = (bool) $att->sign_in;
                                                        $rowSignOut = (bool) $att->sign_out;
                                                    }
                                                }
                                            }
                                        @endphp
                                        <td>
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input class="c-signin" type="checkbox" data_signin_id="{{ $row->id }}" id="signin-{{ $key }}" value="1"
                                                    {{ (!$can_attend || $is_locked) ? 'disabled' : '' }} {{ $rowSignIn ? 'checked' : '' }}>
                                                <label for="signin-{{ $key }}" class="cr">{{ __('Sign In') }}</label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="checkbox checkbox-success d-inline">
                                                <input class="c-signout" type="checkbox" data_signout_id="{{ $row->id }}" id="signout-{{ $key }}" value="1"
                                                    {{ (!$can_attend || $is_locked || !$rowSignIn) ? 'disabled' : '' }} {{ $rowSignOut ? 'checked' : '' }}>
                                                <label for="signout-{{ $key }}" class="cr">{{ __('Sign Out') }}</label>
                                            </div>
                                        </td>
                                        @endif

                                        <td>
                                        <input type="text" name="students[]" value="{{ $row->id }}" hidden>

                                        <div class="form-group d-inline">
                                            <div class="radio radio-primary d-inline">
                                                <input class="c-present" type="radio" data_id="{{ $row->id }}" name="attendances-{{ $key }}" id="attendance-p-{{ $key }}" value="1" required 
                                                {{ (!$can_attend || $is_locked) ? 'disabled' : '' }}
                                                @if(isset($attendances))
                                                @foreach($attendances as $attendance)
                                                    @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 1)
                                                        checked
                                                    @endif
                                                @endforeach
                                                @endif
                                                >
                                                <label for="attendance-p-{{ $key }}" class="cr">{{ __('attendance_present') }}</label>
                                            </div>
                                        </div>
                                        <div class="form-group d-inline">
                                            <div class="radio radio-danger d-inline">
                                                <input class="c-absent" type="radio" data_id="{{ $row->id }}" name="attendances-{{ $key }}" id="attendance-a-{{ $key }}" value="2" required 
                                                {{ (!$can_attend) ? 'checked' : '' }} {{ (!$can_attend || $is_locked) ? 'disabled' : '' }}
                                                @if(isset($attendances))
                                                @foreach($attendances as $attendance)
                                                    @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 2)
                                                        checked
                                                    @endif
                                                @endforeach
                                                @endif
                                                >
                                                <label for="attendance-a-{{ $key }}" class="cr">{{ __('attendance_absent') }}</label>
                                            </div>
                                        </div>
                                        @if($show_warning)
                                            <small class="d-block" style="color: {{ $is_bypassed ? '#856404' : '#dc3545' }};">
                                                <i class="fas fa-exclamation-triangle"></i> Below minimum attendance threshold
                                                @if($is_bypassed)
                                                    <span class="badge badge-warning ml-1">Bypassed</span>
                                                @endif
                                            </small>
                                        @endif
                                        </td>
                                        <td>
                                            @if($is_locked)
                                                <span class="badge badge-danger"><i class="fas fa-lock"></i></span>
                                                @can('subject-marking-unlock')
                                                <button type="button" class="btn btn-icon btn-outline-warning btn-sm unlock-attendance" 
                                                    data-student="{{ $row->id }}" 
                                                    data-subject="{{ $selected_subject }}" 
                                                    data-type="{{ $selected_type }}"
                                                    title="Unlock Attendance">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                                @endcan
                                            @else
                                                <span class="badge badge-success"><i class="fas fa-lock-open"></i></span>
                                            @endif
                                        </td>
                                        @if($is_final_exam)
                                        @can('exam-attendance-bypass')
                                        <td>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input c-bypass" id="bypass-{{ $key }}" name="bypass-{{ $key }}" value="{{ $key }}" data_bypass_id="{{ $row->id }}"
                                                {{ $is_bypassed ? 'checked' : '' }}
                                                >
                                                <label class="custom-control-label" for="bypass-{{ $key }}">
                                                    @if($is_bypassed)
                                                        <span class="badge badge-warning">Active</span>
                                                    @else
                                                        Bypass
                                                    @endif
                                                </label>
                                            </div>
                                        </td>
                                        @endcan
                                        @endif
                                        <td>{{ $row->semester->title ?? '' }}</td>
                                        <td>{{ $row->section->title ?? '' }}</td>
                                    </tr>
                                  @endforeach
                                </tbody>

                                <caption>{{ $cur_subject ?? '' }} - {{ $cur_type ?? '' }} - {{ $row->session->title ?? '' }}</caption>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    
                    @if(count($rows) < 1)
                    <div class="card-block">
                        <h5>{{ __('no_result_found') }}</h5>
                    </div>
                    @endif

                    @if(count($rows) > 0)
                    <div class="card-footer">
                        <button type="button" class="btn btn-success" id="btnShowConfirmModal"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                    </div>
                    @endif
                    </form>
                    @else
                    {{-- Final exam not scheduled - show info only, no form --}}
                    @if(isset($rows) && count($rows) > 0)
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ trans_choice('module_program', 1) }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                    <tr>
                                        <td>{{ $row->matricule }}</td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td><span class="badge badge-light border" style="font-size:11px;">{{ $row->program->title ?? 'N/A' }}</span></td>
                                        <td>{{ $row->semester->title ?? '' }}</td>
                                        <td>{{ $row->section->title ?? 'N/A' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-info-circle"></i> The student list above is for reference only. 
                            Please <a href="{{ route('admin.exam-routine.create') }}">schedule the final exam</a> first to enable attendance taking.
                        </div>
                    </div>
                    @endif
                    @endif

                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Confirmation Modal -->
@if(isset($rows) && count($rows) > 0 && isset($can_take_attendance) && $can_take_attendance)
<div class="modal fade" id="confirmAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="confirmAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="confirmAttendanceModalLabel">
                    <i class="fas fa-clipboard-check"></i> Confirm Exam Attendance Submission
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Exam Details Section -->
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-info-circle text-primary"></i> Exam Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2"><strong><i class="fas fa-book"></i> Subject:</strong> 
                                    <span class="badge badge-info">
                                        @foreach($subjects as $subj)
                                            @if($subj->id == $selected_subject)
                                                {{ $subj->code }} - {{ $subj->title }}
                                            @endif
                                        @endforeach
                                    </span>
                                </p>
                                <p class="mb-2"><strong><i class="fas fa-file-alt"></i> Exam Type:</strong>
                                    <span class="badge {{ isset($selected_exam_type) && $selected_exam_type->is_final == 1 ? 'badge-danger' : 'badge-warning' }}">
                                        @foreach($types as $t)
                                            @if($t->id == $selected_type)
                                                {{ $t->title }}
                                                @if(isset($selected_exam_type) && $selected_exam_type->is_final == 1)
                                                    (Final Exam)
                                                @endif
                                            @endif
                                        @endforeach
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><strong><i class="fas fa-calendar-alt"></i> Session:</strong>
                                    @if(isset($rows) && count($rows) > 0)
                                        {{ $rows[array_key_first($rows)]->session->title ?? 'N/A' }}
                                    @endif
                                </p>
                                <p class="mb-2"><strong><i class="fas fa-clock"></i> Exam Date:</strong> 
                                    <span id="modalExamDate" class="text-primary font-weight-bold">--</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Summary Section -->
                <div class="card mb-3 border-success">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-users text-success"></i> Attendance Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-0 text-primary" id="totalStudents">0</h3>
                                    <small class="text-muted">Total Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-success text-white rounded">
                                    <h3 class="mb-0" id="presentCount">0</h3>
                                    <small>Present</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-danger text-white rounded">
                                    <h3 class="mb-0" id="absentCount">0</h3>
                                    <small>Absent</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-secondary text-white rounded">
                                    <h3 class="mb-0" id="unmarkedCount">0</h3>
                                    <small>Unmarked</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attendance Rate Progress Bar -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Attendance Rate</span>
                                <span id="attendanceRate">0%</span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" id="attendanceProgressBar" style="width: 0%"></div>
                                <div class="progress-bar bg-danger" role="progressbar" id="absentProgressBar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warnings Section (for Final Exams) -->
                @if(isset($selected_exam_type) && $selected_exam_type->is_final == 1)
                <div class="card mb-3 border-warning" id="warningsSection">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Final Exam Eligibility</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="p-2 border rounded">
                                    <h4 class="mb-0 text-success" id="eligibleCount">0</h4>
                                    <small class="text-muted">Eligible Students</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded border-danger">
                                    <h4 class="mb-0 text-danger" id="ineligibleCount">0</h4>
                                    <small class="text-muted">Not Eligible</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded border-warning">
                                    <h4 class="mb-0 text-warning" id="bypassedCount">0</h4>
                                    <small class="text-muted">Bypassed</small>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0" id="eligibilityNote">
                            <i class="fas fa-info-circle"></i> 
                            <span id="eligibilityMessage">Students require minimum {{ $attendance_setting->minimum_attendance_percentage ?? 70 }}% course attendance to be eligible.</span>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Confirmation Notice -->
                <div class="alert alert-warning mb-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x mr-3"></i>
                        <div>
                            <strong>Please Review Before Submitting!</strong>
                            <p class="mb-0 small">Once submitted, attendance records will be saved. Make sure all information is correct before proceeding.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" id="btnConfirmSubmit">
                    <i class="fas fa-check"></i> Confirm & Submit Attendance
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     SUCCESS MODAL – shown after attendance is saved via session flash
     ══════════════════════════════════════════════════════════════ --}}
@if(session('attendance_saved'))
@php $saved = session('attendance_saved'); @endphp
<div class="modal fade" id="attendanceSavedModal" tabindex="-1" role="dialog" aria-labelledby="attendanceSavedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-success">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="attendanceSavedModalLabel">
                    <i class="fas fa-check-circle"></i> Attendance Submitted Successfully
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center py-4">
                {{-- Animated checkmark --}}
                <div class="mb-3">
                    <div style="width:80px;height:80px;border-radius:50%;background:#28a745;display:inline-flex;align-items:center;justify-content:center;">
                        <i class="fas fa-check fa-3x text-white"></i>
                    </div>
                </div>

                <h4 class="text-success mb-3">All Done!</h4>

                <p class="mb-2" style="font-size:16px;">
                    <strong>{{ $saved['count'] }}</strong> student attendance record(s) saved and locked.
                </p>

                <div class="card bg-light border-0 mx-auto" style="max-width:380px;">
                    <div class="card-body py-2 px-3 text-left" style="font-size:14px;">
                        <p class="mb-1"><i class="fas fa-book text-primary mr-1"></i> <strong>Subject:</strong> {{ $saved['subject'] }}</p>
                        <p class="mb-1"><i class="fas fa-file-alt text-info mr-1"></i> <strong>Exam Type:</strong> {{ $saved['type'] }}</p>
                        <p class="mb-0"><i class="fas fa-calendar-alt text-secondary mr-1"></i> <strong>Date:</strong> {{ \Carbon\Carbon::parse($saved['date'])->format('d M Y') }}</p>
                    </div>
                </div>

                {{-- Present / Absent summary --}}
                <div class="row mt-3 justify-content-center">
                    <div class="col-auto">
                        <span class="badge badge-success px-3 py-2" style="font-size:14px;">
                            <i class="fas fa-user-check"></i> {{ $saved['present'] }} Present
                        </span>
                    </div>
                    <div class="col-auto">
                        <span class="badge badge-danger px-3 py-2" style="font-size:14px;">
                            <i class="fas fa-user-times"></i> {{ $saved['absent'] }} Absent
                        </span>
                    </div>
                </div>

                @if($saved['locked'] > 0)
                <div class="alert alert-warning mt-3 mb-0 py-2 small">
                    <i class="fas fa-lock"></i>
                    {{ $saved['locked'] }} student(s) were skipped because their attendance was already locked.
                </div>
                @endif
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                    <i class="fas fa-thumbs-up"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('page_js')
<script type="text/javascript">
    "use strict";
    $(document).ready(function() {

        // ========== SUCCESS MODAL (auto-show after attendance saved) ==========
        @if(session('attendance_saved'))
        $('#attendanceSavedModal').modal('show');
        @endif

        // ═══════════════════════════════════════
        // CROSS-PROGRAMME TOGGLE
        // ═══════════════════════════════════════
        $('#crossProgramToggle').on('change', function() {
            var url = new URL(window.location.href);
            if ($(this).is(':checked')) {
                url.searchParams.set('cross_program', '1');
            } else {
                url.searchParams.delete('cross_program');
            }
            // Also update the hidden input in filter form (for when form is next submitted)
            $('#crossProgramHidden').val($(this).is(':checked') ? '1' : '0');
            // Reload page with new toggle state
            window.location.href = url.toString();
        });

        // Print blank manual Sign-In/Out sheet (carries the live date + All-Programmes toggle).
        $('#btn-print-sheet').on('click', function(e){
            e.preventDefault();
            var url = new URL($(this).data('url'), window.location.origin);
            var d = $('#date').val();
            if(d){ url.searchParams.set('date', d); }
            if($('#crossProgramToggle').is(':checked')){ url.searchParams.set('cross_program', '1'); }
            window.open(url.toString(), '_blank');
        });

        // Handle bypass checkbox change
        $(".c-bypass").on('change', function(){
            var key = $(this).val();
            var row = $(this).closest('tr');
            var label = $(this).next('label').find('.badge');
            
            if($(this).is(":checked")){
                // Enable attendance radio buttons when bypassed
                $("input[name='attendances-"+key+"']").prop('disabled', false);
                // Uncheck absent if it was auto-selected
                $("input[name='attendances-"+key+"'][value='2']").prop('checked', false);
                // Final exam: attendance is derived from the signs, so the bypass must
                // also enable Sign In (Sign Out follows once Sign In is ticked).
                $('#signin-'+key).prop('disabled', false);
                // Update visual state
                row.css('background-color', '#fff3cd');
                label.text('Active');
                // Update warning badge
                var warning = row.find('small.d-block');
                if(warning.length){
                    warning.css('color', '#856404');
                    if(warning.find('.badge').length == 0){
                        warning.append(' <span class="badge badge-warning ml-1">Bypassed</span>');
                    }
                }
            } else {
                // Remove bypass visual state
                row.css('background-color', '');
                label.text('Bypass');
                // Check if student is eligible
                if(row.hasClass('table-danger')){
                    // Re-disable if not eligible
                    $("input[name='attendances-"+key+"']").prop('disabled', true);
                    $("input[name='attendances-"+key+"'][value='2']").prop('checked', true).prop('disabled', true);
                    // Clear + disable the signs so it reverts to Absent on a final exam.
                    $('#signin-'+key).prop('checked', false).prop('disabled', true);
                    $('#signout-'+key).prop('checked', false).prop('disabled', true);
                }
                // Update warning badge
                var warning = row.find('small.d-block');
                if(warning.length){
                    warning.css('color', '#dc3545');
                    warning.find('.badge').remove();
                }
            }
        });
        
        // Show confirmation modal when update button is clicked
        $("#btnShowConfirmModal").on('click', function(e){
            e.preventDefault();
            
            // Calculate attendance statistics
            var totalStudents = $("input[name^='attendances-']").length / 2; // Divide by 2 because each student has 2 radio buttons
            var presentCount = $(".c-present:checked").length;
            var absentCount = $(".c-absent:checked").length;
            var unmarkedCount = totalStudents - presentCount - absentCount;
            
            // Calculate attendance rate
            var attendanceRate = totalStudents > 0 ? Math.round((presentCount / totalStudents) * 100) : 0;
            var absentRate = totalStudents > 0 ? Math.round((absentCount / totalStudents) * 100) : 0;
            
            // Update modal with statistics
            $("#totalStudents").text(totalStudents);
            $("#presentCount").text(presentCount);
            $("#absentCount").text(absentCount);
            $("#unmarkedCount").text(unmarkedCount);
            $("#attendanceRate").text(attendanceRate + '%');
            
            // Update progress bars
            $("#attendanceProgressBar").css('width', attendanceRate + '%');
            $("#absentProgressBar").css('width', absentRate + '%');
            
            // Update exam date
            var examDate = $("#date").val();
            if(examDate){
                var dateObj = new Date(examDate);
                var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                $("#modalExamDate").text(dateObj.toLocaleDateString('en-US', options));
            } else {
                $("#modalExamDate").text('Not Set').addClass('text-danger');
            }
            
            // Final exam eligibility stats
            @if(isset($selected_exam_type) && $selected_exam_type->is_final == 1)
            var eligibleCount = $("tr:not(.table-danger)").length - 1; // Subtract header row
            var ineligibleCount = $("tr.table-danger").length;
            var bypassedCount = $(".c-bypass:checked").length;
            
            // Recalculate eligible (those who are eligible OR bypassed)
            var totalIneligible = ineligibleCount;
            var effectivelyEligible = eligibleCount + bypassedCount;
            
            $("#eligibleCount").text($("tbody tr").length - totalIneligible + bypassedCount);
            $("#ineligibleCount").text(Math.max(0, totalIneligible - bypassedCount));
            $("#bypassedCount").text(bypassedCount);
            
            // Update eligibility message based on situation
            if(bypassedCount > 0){
                $("#eligibilityNote").removeClass('alert-info').addClass('alert-warning');
                $("#eligibilityMessage").html('<strong>' + bypassedCount + '</strong> student(s) have been bypassed and will be allowed to take the exam despite not meeting attendance requirements.');
            } else if(totalIneligible > 0){
                $("#eligibilityNote").removeClass('alert-info').addClass('alert-danger');
                $("#eligibilityMessage").html('<strong>' + totalIneligible + '</strong> student(s) are not eligible due to low course attendance and will be marked absent.');
            } else {
                $("#eligibilityNote").removeClass('alert-warning alert-danger').addClass('alert-info');
                $("#eligibilityMessage").text('All students meet the minimum attendance requirement for this exam.');
            }
            @endif
            
            // Validate before showing modal
            if(unmarkedCount > 0){
                // Show warning about unmarked students
                if(!confirm('Warning: ' + unmarkedCount + ' student(s) have not been marked. Do you want to continue?')){
                    return;
                }
            }
            
            // Check if date is set
            if(!examDate){
                alert('Please set the exam date before submitting.');
                $("#date").focus();
                return;
            }
            
            // Show the modal
            $("#confirmAttendanceModal").modal('show');
        });
        
        // Handle confirm submit
        $("#btnConfirmSubmit").on('click', function(){
            // Prepare form data
            var attendances = [];
            var bypasses = [];
            
            $.each($("input[data_id]:checked"), function(){
                attendances.push($(this).val());
            });
            
            $.each($("input[data_bypass_id]:checked"), function(){
                bypasses.push($(this).attr('data_bypass_id'));
            });

            // Sign In / Sign Out (final exam) — aligned to students[] order.
            var signins = [], signouts = [];
            $("input[name='students[]']").each(function(){
                var sid = $(this).val();
                var $si = $(".c-signin[data_signin_id='" + sid + "']");
                var $so = $(".c-signout[data_signout_id='" + sid + "']");
                signins.push(($si.length && $si.is(':checked')) ? '1' : '0');
                signouts.push(($so.length && $so.is(':checked')) ? '1' : '0');
            });

            $(".attendances").val(attendances.join(','));
            $(".bypasses").val(bypasses.join(','));
            $(".signins").val(signins.join(','));
            $(".signouts").val(signouts.join(','));
            
            // Close modal and submit form
            $("#confirmAttendanceModal").modal('hide');
            
            // Add a small delay to ensure modal closes before form submit
            setTimeout(function(){
                $("form[action*='exam-attendance']").submit();
            }, 300);
        });
    });


    // ── Sign In / Sign Out interlink (final exam) ─────────────────────────────
    // Rule: present = sign_in AND sign_out; sign-out requires sign-in;
    // sign-in only (no sign-out) = absent; absent = neither verified.
    function syncSignRow(idx){
        var $si = $('#signin-'+idx), $so = $('#signout-'+idx);
        var $p = $('#attendance-p-'+idx), $a = $('#attendance-a-'+idx);
        if(!$si.length || !$p.length) return;            // not a final-exam row
        if($p.prop('disabled')) return;                  // locked record
        var si = $si.is(':checked');
        if(!si){ $so.prop('checked', false).prop('disabled', true); }
        else { $so.prop('disabled', false); }
        var present = si && $so.is(':checked');
        $p.prop('checked', present);
        $a.prop('checked', !present);
    }

    $(document).on('change', '.c-signin', function(){
        syncSignRow($(this).attr('id').replace('signin-',''));
    });
    $(document).on('change', '.c-signout', function(){
        syncSignRow($(this).attr('id').replace('signout-',''));
    });
    // Present/Absent radios drive the signs (bidirectional).
    $(document).on('change', '.c-present', function(){
        if(!$(this).is(':checked')) return;
        var idx = $(this).attr('id').replace('attendance-p-','');
        var $si = $('#signin-'+idx), $so = $('#signout-'+idx);
        if($si.length && !$si.prop('disabled')){
            $si.prop('checked', true);
            $so.prop('disabled', false).prop('checked', true);
        }
    });
    $(document).on('change', '.c-absent', function(){
        if(!$(this).is(':checked')) return;
        var idx = $(this).attr('id').replace('attendance-a-','');
        var $si = $('#signin-'+idx), $so = $('#signout-'+idx);
        if($si.length && !$si.prop('disabled')){
            $si.prop('checked', false);
            $so.prop('checked', false).prop('disabled', true);
        }
    });

    // checkbox all-check-button selector
    $(".all_present").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox that are not disabled
            $(".c-present:not(:disabled)").prop('checked', true);
            $(".c-signin:not(:disabled)").prop('checked', true).each(function(){
                var idx = $(this).attr('id').replace('signin-','');
                $('#signout-'+idx).prop('disabled', false).prop('checked', true);
            });
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-present").prop('checked', false);
        }
    });
    $(".all_absent").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox that are not disabled
            $(".c-absent:not(:disabled)").prop('checked', true);
            $(".c-signin:not(:disabled)").prop('checked', false).each(function(){
                var idx = $(this).attr('id').replace('signin-','');
                $('#signout-'+idx).prop('checked', false).prop('disabled', true);
            });
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-absent").prop('checked', false);
        }
    });

    // Unlock attendance
    $(".unlock-attendance").on('click', function(){
        var btn = $(this);
        var studentId = btn.data('student');
        var subjectId = btn.data('subject');
        var typeId = btn.data('type');
        
        if(confirm('Are you sure you want to unlock this attendance record?')){
            $.ajax({
                url: '{{ route("admin.exam-attendance.unlock") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    student_enroll_id: studentId,
                    subject_id: subjectId,
                    exam_type_id: typeId
                },
                success: function(response){
                    if(response.status == 'success'){
                        // alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function(xhr){
                    alert('Error: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error'));
                }
            });
        }
    });
    
    // ========== MY ASSIGNED COURSES SECTION ==========
    
    // Program filter for My Assigned Courses
    $('#quickProgramSelector').on('change', function() {
        var selectedProgram = $(this).val();
        
        if (selectedProgram === 'all') {
            // Show all program sections
            $('.program-section').show();
        } else {
            // Show only selected program section (by index)
            $('.program-section').each(function() {
                if ($(this).data('program-index') == selectedProgram) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });
    
    // View toggle for My Assigned Courses
    $('.view-toggle-btn').on('click', function() {
        $('.view-toggle-btn').removeClass('active');
        $(this).addClass('active');
        
        var view = $(this).data('view');
        if (view === 'card') {
            $('#card-view').show();
            $('#table-view').hide();
        } else {
            $('#card-view').hide();
            $('#table-view').show();
        }
    });
    
    // Year dropdown filter - update semesters when year changes
    $(document).on('change', '.course-year-select', function() {
        var card = $(this).closest('.course-card, tr');
        var semesterSelect = card.find('.course-semester-select');
        var selectedYear = $(this).val();
        
        // Show/hide semester options based on year
        semesterSelect.find('option').each(function() {
            if ($(this).val() === '' || $(this).data('year') == selectedYear) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Reset semester selection
        semesterSelect.val('');
    });
    
    // Take Attendance button click (quick-take-attendance)
    $(document).on('click', '.quick-take-attendance', function() {
        var btn = $(this);
        var uniqueId = btn.data('unique');
        
        // Get data attributes from button
        var facultyId = btn.data('faculty');
        var programId = btn.data('program');
        var sessionId = btn.data('session');
        var subjectId = btn.data('subject');
        
        // Get values from the dropdowns using the unique ID (with underscore format)
        var semesterId = $('#semester_' + uniqueId).val();
        var sectionId = $('#section_' + uniqueId).val();
        var examTypeId = $('#examtype_' + uniqueId).val();
        
        // Validate selections
        if (!semesterId) {
            alert('Please select a semester');
            return;
        }
        if (!sectionId) {
            alert('Please select a section');
            return;
        }
        if (!examTypeId) {
            alert('Please select an exam type');
            return;
        }
        
        // Navigate directly to the attendance page with correct parameter names
        var url = "{{ route('admin.exam-attendance.index') }}?" + 
                  "faculty=" + facultyId + 
                  "&program=" + programId + 
                  "&session=" + sessionId + 
                  "&semester=" + semesterId + 
                  "&section=" + sectionId + 
                  "&subject=" + subjectId + 
                  "&type=" + examTypeId;
        
        window.location.href = url;
    });
    
    // Function to check attendance status
    function checkAttendanceStatus(uniqueId) {
        var subjectId = $('.quick-take-attendance[data-unique="' + uniqueId + '"]').data('subject');
        var sessionId = $('.quick-take-attendance[data-unique="' + uniqueId + '"]').data('session');
        var semesterId = $('#semester_' + uniqueId).val();
        var sectionId = $('#section_' + uniqueId).val();
        var examTypeId = $('#examtype_' + uniqueId).val();
        
        var statusDiv = $('#status_' + uniqueId);
        
        // Only check if exam type is selected
        if (!examTypeId) {
            statusDiv.hide();
            return;
        }
        
        // Show loading state
        statusDiv.show();
        statusDiv.find('.status-content')
            .removeClass('status-not_taken status-partial status-completed status-no_students')
            .html('<div class="status-loading p-2"><i class="fas fa-spinner"></i> Checking status...</div>');
        
        $.ajax({
            url: "{{ route('admin.exam-attendance.check-status') }}",
            type: 'GET',
            data: {
                subject_id: subjectId,
                semester_id: semesterId,
                section_id: sectionId,
                exam_type_id: examTypeId,
                session_id: sessionId
            },
            success: function(response) {
                var statusContent = statusDiv.find('.status-content');
                statusContent.removeClass('status-not_taken status-partial status-completed status-no_students');
                
                if (response.status === 'incomplete') {
                    statusDiv.hide();
                    return;
                }
                
                statusContent.addClass('status-' + response.status);
                
                var html = '<div class="d-flex align-items-center justify-content-between">' +
                    '<div class="status-info d-flex align-items-center">' +
                    '<i class="' + response.icon + ' mr-2 text-' + response.color + '"></i>' +
                    '<span class="status-message text-' + response.color + '">' + response.message + '</span>' +
                    '</div>';
                
                if (response.total > 0 && response.status !== 'no_students') {
                    html += '<div class="status-details">' +
                        '<span class="present-count text-success" title="Present">' + response.present + '</span>' +
                        '<span class="absent-count text-danger ml-2" title="Absent">' + response.absent + '</span>' +
                        '<span class="pending-count text-warning ml-2" title="Pending">' + response.pending + '</span>' +
                        '</div>';
                }
                
                html += '</div>';
                statusContent.html(html);
            },
            error: function(xhr, status, error) {
                console.log('Status check error:', xhr.responseText);
                var statusContent = statusDiv.find('.status-content');
                statusContent.removeClass('status-not_taken status-partial status-completed status-no_students');
                statusContent.addClass('status-no_students');
                statusContent.html('<div class="d-flex align-items-center p-1"><i class="fas fa-exclamation-triangle mr-2 text-warning"></i><span class="small text-muted">Unable to check status</span></div>');
            }
        });
    }
    
    // Check status when exam type changes
    $(document).on('change', '.quick-exam-type', function() {
        var uniqueId = $(this).attr('id').replace('examtype_', '');
        checkAttendanceStatus(uniqueId);
    });
    
    // Also check when semester or section changes (if exam type is already selected)
    $(document).on('change', '.quick-semester, .quick-section', function() {
        var id = $(this).attr('id');
        var uniqueId = id.replace('semester_', '').replace('section_', '');
        var examTypeId = $('#examtype_' + uniqueId).val();
        if (examTypeId) {
            checkAttendanceStatus(uniqueId);
        }
    });
    
    // Collapse state management
    var myCoursesCollapse = document.getElementById('myAssignedCoursesCollapse');
    if (myCoursesCollapse) {
        // My Assigned Courses is collapsed by default (no sessionStorage needed)
        // Just update icon when shown/hidden
        myCoursesCollapse.addEventListener('shown.bs.collapse', function () {
            $('#collapseIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });
        
        myCoursesCollapse.addEventListener('hidden.bs.collapse', function () {
            $('#collapseIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
    }
</script>
@endsection