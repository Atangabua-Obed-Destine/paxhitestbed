@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    /* Marking Status Indicator Styles */
    .marking-status-indicator .status-content {
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        transition: all 0.3s ease;
    }
    .marking-status-indicator .status-content.status-not_taken {
        background: #fff3cd;
        border-color: #ffc107;
    }
    .marking-status-indicator .status-content.status-partial {
        background: #d1ecf1;
        border-color: #17a2b8;
    }
    .marking-status-indicator .status-content.status-completed {
        background: #d4edda;
        border-color: #28a745;
    }
    .marking-status-indicator .status-content.status-no_students {
        background: #e9ecef;
        border-color: #6c757d;
    }
    .marking-status-indicator .status-icon {
        font-size: 14px;
    }
    .marking-status-indicator .status-message {
        font-weight: 500;
    }
    .marking-status-indicator .status-details span {
        font-size: 11px;
    }
    .marking-status-indicator .status-details .marked-count::before {
        content: '\f00c';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        margin-right: 2px;
    }
    .marking-status-indicator .status-details .pending-count::before {
        content: '\f017';
        font-family: 'Font Awesome 5 Free';
        font-weight: 400;
        margin-right: 2px;
    }
    .marking-status-indicator .status-details .avg-marks {
        font-weight: 600;
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
    .year-section {
        background: #f8f9fc;
        border-radius: 8px;
        padding: 15px;
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
                                @php
                                    $shownCount = $myAssignedCourses->sum(function($p) { return $p['subjects']->count(); });
                                    $hiddenCount = (($assignedUnfilteredCount ?? $shownCount) - $shownCount);
                                @endphp
                                <small class="opacity-75">
                                    @if(isset($currentSession))
                                        <i class="fas fa-calendar-alt"></i> {{ $currentSession->title }}
                                    @endif
                                    • {{ $shownCount }} {{ __('Total Courses') }}
                                    @if($show_assigned_all ?? false)
                                        <span class="badge badge-light text-dark ml-1">{{ __('showing all') }}</span>
                                    @elseif($hiddenCount > 0)
                                        <span class="badge badge-light text-dark ml-1">{{ $hiddenCount }} {{ __('published hidden') }}</span>
                                    @endif
                                </small>
                            </div>
                            <div class="d-flex align-items-center">
                                @if($show_assigned_all ?? false)
                                    <a href="{{ route('admin.exam-marking.index') }}" class="btn btn-sm btn-light mr-2" title="{{ __('Hide published courses') }}">
                                        <i class="fas fa-eye-slash"></i> {{ __('Hide published') }}
                                    </a>
                                @else
                                    <a href="{{ route('admin.exam-marking.index', ['show_assigned_all' => 1]) }}" class="btn btn-sm btn-light mr-2" title="{{ __('Show all assigned courses, including published') }}">
                                        <i class="fas fa-eye"></i> {{ __('Show all') }}
                                    </a>
                                @endif
                                <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#myCoursesCollapse" aria-expanded="false">
                                    <i class="fas fa-chevron-down" id="collapseIcon"></i>
                                </button>
                            </div>
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
                                        <select class="form-control form-control-lg" id="quickProgramSelector">
                                            <option value="all">{{ __('All Programs') }} ({{ $myAssignedCourses->count() }})</option>
                                            @foreach($myAssignedCourses as $idx => $programData)
                                            <option value="{{ $idx }}" data-faculty="{{ $programData['faculty_title'] }}">
                                                {{ $programData['program_title'] }} 
                                                ({{ $programData['subjects']->count() }} {{ __('courses') }})
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> {{ __('Select a program to filter your courses') }}
                                    </small>
                                </div>
                                <div class="col-md-6 text-right">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary active" id="viewCards" title="Card View">
                                            <i class="fas fa-th-large"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" id="viewTable" title="Table View">
                                            <i class="fas fa-list"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Course Cards Container --}}
                            <div id="coursesContainer">
                                @foreach($myAssignedCourses as $programIndex => $programData)
                                <div class="program-section mb-4" data-program-index="{{ $programIndex }}">
                                    {{-- Program Header --}}
                                    <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3" style="width: 45px; height: 45px;">
                                            <i class="fas fa-book-open"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0 text-primary">{{ $programData['program_title'] }}</h5>
                                            <small class="text-muted">
                                                <i class="fas fa-university"></i> {{ $programData['faculty_title'] }}
                                            </small>
                                        </div>
                                        <span class="ml-auto badge badge-primary badge-pill px-3 py-2">
                                            {{ $programData['subjects']->count() }} {{ __('Courses') }}
                                        </span>
                                    </div>

                                    {{-- Card View --}}
                                    <div class="row course-cards-view">
                                        @foreach($programData['subjects'] as $subjectIndex => $subjectData)
                                        @php 
                                            $uniqueId = $programIndex . '_' . $subjectIndex;
                                        @endphp
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="card h-100 border-left-primary shadow-sm course-card" style="border-left: 4px solid #4e73df !important;">
                                                <div class="card-body p-3">
                                                    {{-- Course Header --}}
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <span class="badge badge-dark">{{ $subjectData['subject_code'] }}</span>
                                                        <span class="badge badge-outline-info">
                                                            <i class="fas fa-users"></i> {{ $subjectData['sections']->count() }} {{ __('Section(s)') }}
                                                        </span>
                                                    </div>
                                                    
                                                    {{-- Course Title --}}
                                                    <h6 class="font-weight-bold text-dark mb-2" title="{{ $subjectData['subject_title'] }}">
                                                        {{ Str::limit($subjectData['subject_title'], 35) }}
                                                    </h6>

                                                    {{-- Program Tag --}}
                                                    <div class="mb-3">
                                                        <span class="badge badge-primary" title="{{ $programData['program_title'] }}">
                                                            <i class="fas fa-graduation-cap"></i> {{ Str::limit($programData['program_code'], 20) }}
                                                        </span>
                                                    </div>

                                                    {{-- Year & Semester Selectors --}}
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

                                                    {{-- Section Selector --}}
                                                    <div class="row mb-2">
                                                        <div class="col-12">
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
                                                    </div>

                                                    {{-- Exam Type Selector --}}
                                                    <div class="mb-3">
                                                        <label class="small text-muted mb-1">{{ __('Exam Type') }} <span class="text-danger">*</span></label>
                                                        <select class="form-control form-control-sm quick-exam-type" id="examtype_{{ $uniqueId }}" required>
                                                            <option value="">-- {{ __('Select Exam Type') }} --</option>
                                                            @foreach($examTypes as $et)
                                                            <option value="{{ $et->id }}" data-is-final="{{ $et->is_final }}">
                                                                {{ $et->title }} 
                                                                @if($et->is_final) (Final) @endif
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Action Button --}}
                                                    <button type="button" 
                                                            class="btn btn-success btn-block quick-enter-marks"
                                                            data-faculty="{{ $programData['faculty_id'] }}"
                                                            data-program="{{ $programData['program_id'] }}"
                                                            data-session="{{ $currentSession->id ?? '' }}"
                                                            data-subject="{{ $subjectData['subject_id'] }}"
                                                            data-unique="{{ $uniqueId }}">
                                                        <i class="fas fa-edit"></i> {{ __('Enter Marks') }}
                                                    </button>
                                                    
                                                    {{-- Status Indicator --}}
                                                    <div class="marking-status-indicator mt-2" id="status_{{ $uniqueId }}" style="display: none;">
                                                        <div class="status-content d-flex align-items-center justify-content-between p-2 rounded">
                                                            <div class="status-info d-flex align-items-center">
                                                                <i class="status-icon mr-2"></i>
                                                                <span class="status-message small"></span>
                                                            </div>
                                                            <div class="status-details small">
                                                                <span class="marked-count text-success" title="Marked"></span>
                                                                <span class="pending-count text-warning ml-1" title="Pending"></span>
                                                                <span class="avg-marks text-primary ml-1" title="Average"></span>
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
                                                        <th style="width: 8%;">{{ __('Code') }}</th>
                                                        <th style="width: 18%;">{{ __('Course Title') }}</th>
                                                        <th style="width: 14%;">{{ __('Program') }}</th>
                                                        <th style="width: 10%;">{{ __('Year') }}</th>
                                                        <th style="width: 14%;">{{ __('Semester') }}</th>
                                                        <th style="width: 12%;">{{ __('Section') }}</th>
                                                        <th style="width: 18%;">{{ __('Exam Type') }}</th>
                                                        <th style="width: 12%;">{{ __('Action') }}</th>
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
                                                                <i class="fas fa-graduation-cap"></i> {{ Str::limit($programData['program_code'], 15) }}
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
                                                                    class="btn btn-sm btn-success quick-enter-marks"
                                                                    data-faculty="{{ $programData['faculty_id'] }}"
                                                                    data-program="{{ $programData['program_id'] }}"
                                                                    data-session="{{ $currentSession->id ?? '' }}"
                                                                    data-subject="{{ $subjectData['subject_id'] }}"
                                                                    data-unique="{{ $uniqueIdTable }}">
                                                                <i class="fas fa-edit"></i> {{ __('Go') }}
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
            @elseif(($assignedUnfilteredCount ?? 0) > 0 && !($show_assigned_all ?? false) && !isset($selected_subject))
            {{-- Lecturer HAS assigned courses, but all are published (hidden). Offer the toggle. --}}
            <div class="col-sm-12">
                <div class="card border-success">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h5 class="text-muted">{{ __('All your assigned courses are published') }}</h5>
                        <p class="text-muted mb-3">
                            {{ __('Nothing pending for the current session.') }}
                        </p>
                        <a href="{{ route('admin.exam-marking.index', ['show_assigned_all' => 1]) }}" class="btn btn-primary">
                            <i class="fas fa-eye"></i> {{ __('Show all courses') }}
                        </a>
                    </div>
                </div>
            </div>
            @elseif(isset($myAssignedCourses) && $myAssignedCourses->count() === 0 && !isset($selected_subject))
            <div class="col-sm-12">
                <div class="card border-warning">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chalkboard fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ __('No Courses Assigned') }}</h5>
                        <p class="text-muted mb-0">
                            {{ __('No courses assigned to you for the current session.') }}<br>
                            {{ __('Use the filter below to search for courses.') }}
                        </p>
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
                        <a href="{{ route('admin.exam-attendance.import') }}" class="btn btn-dark btn-sm float-right"><i class="fas fa-upload"></i> {{ __('btn_import') }}</a>
                        @endcan
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}" id="advancedFilterForm">
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
                            {{-- Preserve cross-programme toggle state across filter submissions --}}
                            @if($cross_program)
                                <input type="hidden" name="cross_program" value="1">
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            {{-- ================================================================
                 CROSS-PROGRAMME TOGGLE
                 ================================================================ --}}
            @if(isset($rows) && isset($sharing_programs) && $sharing_programs->count() > 1)
            <div class="col-sm-12">
                <div class="card border-info shadow-sm mb-3">
                    <div class="card-block py-3 px-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="custom-control custom-switch mr-3">
                                    <input type="checkbox" class="custom-control-input" id="crossProgramToggle"
                                           {{ $cross_program ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="crossProgramToggle" style="font-size: 14px; cursor: pointer;">
                                        <i class="fas fa-project-diagram text-info"></i>
                                        {{ __('Cross-Programme Marking') }}
                                    </label>
                                </div>
                                <span class="text-muted small">
                                    @if($cross_program)
                                        Marking students from <strong class="text-info">{{ $sharing_programs->count() }} programmes</strong> taking this course
                                    @else
                                        This course is shared by <strong>{{ $sharing_programs->count() }} programmes</strong> &mdash; toggle to mark all
                                    @endif
                                </span>
                            </div>

                            {{-- Sharing Programmes List --}}
                            <div>
                                <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#sharingProgramsListMarking">
                                    <i class="fas fa-eye"></i> {{ __('View Programmes') }}
                                </button>
                            </div>
                        </div>

                        <div class="collapse {{ $cross_program ? 'show' : '' }} mt-2" id="sharingProgramsListMarking">
                            <div class="border rounded p-2 bg-light">
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($sharing_programs as $sp)
                                        <span class="badge {{ $sp->id == $selected_program ? 'badge-primary' : 'badge-light border' }} px-2 py-1" style="font-size: 11px;">
                                            <i class="fas fa-graduation-cap mr-1"></i>
                                            {{ $sp->title }}
                                            @if($sp->faculty)
                                                <small class="text-muted">({{ Str::limit($sp->faculty->title, 15) }})</small>
                                            @endif
                                            @if($sp->id == $selected_program)
                                                <i class="fas fa-check-circle ml-1"></i>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            @if($cross_program)
                                <div class="mt-2 small">
                                    <i class="fas fa-info-circle text-info"></i>
                                    Cross-programme mode is <strong>active</strong>. Students from all {{ $sharing_programs->count() }} programmes are shown below, grouped by programme.
                                    Marks entered here apply across all sharing programmes.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @elseif(isset($rows) && isset($sharing_programs) && $sharing_programs->count() == 1)
            <div class="col-sm-12">
                <div class="alert alert-light border mb-3 py-2 px-3" style="font-size: 13px;">
                    <i class="fas fa-lock"></i> This course is exclusive to <strong>{{ $sharing_programs->first()->title }}</strong>
                    &mdash; cross-programme toggle is not available.
                </div>
            </div>
            @endif

            @php $isFinalExamPanel = (bool) optional($selected_exam_type)->is_final; @endphp
            @if(isset($rows) && count($rows) > 0 && !$isFinalExamPanel)
            <div class="col-sm-12">
                <div class="card border-info">
                    <div class="card-header bg-light py-2">
                        <i class="fas fa-user-clock text-info"></i> <strong>{{ __('Attendance Migration') }}</strong>
                        <small class="text-muted ml-2">{{ __('Bulk-record class attendance for CA: set the date range and total classes held, then enter each student\'s absences in the table below.') }}</small>
                    </div>
                    <div class="card-block p-3">
                        {{-- These inputs belong to the marks form (#marksForm) so the single
                             Update/Save button saves marks AND attendance together. --}}
                        <div class="row align-items-end">
                            <div class="col-md-3 form-group mb-2">
                                <label class="mb-1">{{ __('Start Date') }}</label>
                                <input type="date" name="start_date" id="att_start_date" form="marksForm" class="form-control" value="{{ $mig_start_date ?? '' }}">
                            </div>
                            <div class="col-md-3 form-group mb-2">
                                <label class="mb-1">{{ __('End Date') }}</label>
                                <input type="date" name="end_date" id="att_end_date" form="marksForm" class="form-control" value="{{ $mig_end_date ?? '' }}">
                            </div>
                            <div class="col-md-3 form-group mb-2">
                                <label class="mb-1">{{ __('Total Classes') }}</label>
                                <input type="number" name="total_classes" id="att_total_classes" form="marksForm" class="form-control" min="1" value="{{ $mig_total_classes ?? '' }}">
                            </div>
                            <div class="col-md-3 form-group mb-2">
                                <label class="mb-1">{{ __('Attendance Weight') }}</label>
                                <input type="text" class="form-control" value="{{ $attendance_weight ?? 0 }}" readonly title="{{ __('From mark distribution (result contribution)') }}">
                            </div>
                        </div>
                        <small class="text-muted d-block">
                            <i class="fas fa-info-circle"></i>
                            {{ __('Optional: to also record attendance, set the dates + total classes and enter each student\'s absences below, then click Update — it saves marks and attendance together. Presents = Total Classes − Absences; records are placed on real class days from the timetable when available. Saving REPLACES the student\'s entire attendance for this subject; students left blank are untouched.') }}
                        </small>
                    </div>
                </div>
            </div>
            @endif

            <div class="col-sm-12">
                <div class="card">
                    <form id="marksForm" class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @if($cross_program)
                        <input type="hidden" name="cross_program" value="1">
                    @endif
                    {{-- Filters carried so the save can return to this exact screen and bundle attendance --}}
                    <input type="hidden" name="faculty" value="{{ $selected_faculty }}">
                    <input type="hidden" name="program" value="{{ $selected_program }}">
                    <input type="hidden" name="session" value="{{ $selected_session }}">
                    <input type="hidden" name="semester" value="{{ $selected_semester }}">
                    <input type="hidden" name="section" value="{{ $selected_section }}">
                    <input type="hidden" name="subject" value="{{ $selected_subject }}">
                    <input type="hidden" name="type" value="{{ $selected_type }}">
                    <input type="hidden" name="exam_type" value="{{ $selected_type }}">

                    @if(isset($rows))
                    @php
                        $isFinalExam = (bool) optional($selected_exam_type)->is_final;
                        $firstRow = head($rows);
                        $maxMarks = $firstRow ? $firstRow->type->marks : null;
                        $hasRows = count($rows) > 0;
                    @endphp

                    @if($hasRows)
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover printable">
                                <thead>
                                    <tr>
                                        <th>S/N</th>
                                        <th>{{ $isFinalExam ? __('Student Exam ID') : __('field_matricule') }}</th>
                                        @if(!$isFinalExam)
                                        <th>{{ __('field_student_name') }}</th>
                                        @endif
                                        @if($cross_program)
                                        <th>{{ trans_choice('module_program', 1) }}</th>
                                        @endif
                                        <th>
                                            {{ __('field_max_marks') }}
                                            @if(!is_null($maxMarks))
                                                ({{ round($maxMarks, 2) }})
                                            @endif
                                        </th>
                                        <th>{{ __('field_note') }}</th>
                                        @if(!$isFinalExam)
                                        <th>{{ __('Absences') }}</th>
                                        <th>{{ __('Attendance Score') }}</th>
                                        <th>{{ __('Actual Mark') }}</th>
                                        @endif
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @php $prevMarkingProgramId = null; @endphp
                                  @foreach($rows as $row)
                                    @php
                                        $anonymousCode = $row->scriptCode->anonymous_code ?? null;
                                        $canMark = !$isFinalExam || !empty($anonymousCode);
                                        $is_locked = $row->marks_locked;
                                        $rowProgram = $row->studentEnroll->program ?? null;
                                        $colSpan = 6 + (!$isFinalExam ? 4 : 0) + ($cross_program ? 1 : 0);
                                    @endphp

                                    {{-- Programme group separator --}}
                                    @if($cross_program && $rowProgram && $rowProgram->id !== $prevMarkingProgramId)
                                        @php $prevMarkingProgramId = $rowProgram->id; @endphp
                                        <tr class="bg-light">
                                            <td colspan="{{ $colSpan }}" class="py-2 px-3">
                                                <strong>
                                                    <i class="fas fa-graduation-cap text-primary me-1"></i>
                                                    {{ $rowProgram->title }}
                                                </strong>
                                                @if($rowProgram->faculty)
                                                    <small class="text-muted ms-2">({{ $rowProgram->faculty->title }})</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif

                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            @if($isFinalExam)
                                                @if($anonymousCode)
                                                    {{ $anonymousCode }}
                                                @else
                                                    <span class="badge bg-warning text-dark">{{ __('Not set') }}</span>
                                                @endif
                                            @else
                                                @isset($row->studentEnroll->matricule)
                                                <a href="{{ route('admin.student.show', $row->studentEnroll->student->id) }}">
                                                #{{ $row->studentEnroll->matricule ?? '' }}
                                                </a>
                                                @endisset
                                            @endif
                                        </td>
                                        @if(!$isFinalExam)
                                        <td>
                                            {{ $row->studentEnroll->student->first_name ?? '' }} {{ $row->studentEnroll->student->last_name ?? '' }}
                                        </td>
                                        @endif
                                        @if($cross_program)
                                        <td>
                                            <span class="badge badge-light border" style="font-size: 10px; white-space: normal;">
                                                {{ Str::limit($rowProgram->title ?? 'N/A', 25) }}
                                            </span>
                                        </td>
                                        @endif
                                        <td>
                                            <input type="text" class="form-control" name="marks[{{ $row->id }}]" value="{{ $row->achieve_marks ? round($row->achieve_marks, 2) : '' }}" style="width: 110px;" data-v-max="{{ $maxMarks }}" data-v-min="0" @unless($canMark && !$is_locked) disabled @endunless>
                                            @if($isFinalExam && !$canMark)
                                                <small class="text-danger d-block mt-1">{{ __('Awaiting student exam ID') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" name="notes[{{ $row->id }}]" value="{{ $row->note }}" style="width: 160px;" @unless($canMark && !$is_locked) disabled @endunless>
                                        </td>
                                        @if(!$isFinalExam)
                                        <td>
                                            <input type="number" min="0" class="form-control att-absence" name="absences[{{ $row->student_enroll_id }}]" form="marksForm" data-enroll="{{ $row->student_enroll_id }}" style="width: 90px;" placeholder="0" value="{{ $mig_absences[$row->student_enroll_id] ?? '' }}">
                                        </td>
                                        <td>
                                            <span class="att-score badge badge-light border" data-enroll="{{ $row->student_enroll_id }}" style="font-size: 13px;">0</span>
                                        </td>
                                        <td>
                                            <span class="att-actual badge badge-info" data-enroll="{{ $row->student_enroll_id }}" style="font-size: 13px;" title="{{ __('Actual graded attendance mark across all recorded dates') }}">{{ $mig_actual_marks[$row->student_enroll_id] ?? 0 }}</span>
                                        </td>
                                        @endif
                                        <td>
                                            @if($is_locked)
                                                <span class="badge badge-danger"><i class="fas fa-lock"></i></span>
                                                @can('subject-marking-unlock')
                                                <button type="button" class="btn btn-icon btn-outline-warning btn-sm unlock-marks" 
                                                    data-exam="{{ $row->id }}"
                                                    title="Unlock Marks">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                                @endcan
                                            @else
                                                <span class="badge badge-success"><i class="fas fa-lock-open"></i></span>
                                            @endif
                                        </td>
                                        <td>{{ $row->studentEnroll->semester->title ?? '' }}</td>
                                        <td>{{ $row->studentEnroll->section->title ?? '' }}</td>
                                    </tr>
                                  @endforeach
                                </tbody>
                                @if($firstRow)
                                <caption>{{ $firstRow->subject->code ?? '' }} - {{ $firstRow->type->title ?? '' }} - {{ $firstRow->studentEnroll->session->title ?? '' }}</caption>
                                @endif
                            </table>
                            @if($isFinalExam)
                                @if($cross_program)
                                    <p class="mt-2 text-muted small"><i class="fas fa-info-circle"></i> Cross-programme mode: Marks apply to students from all sharing programmes. Student exam IDs must be configured before marks can be entered.</p>
                                @else
                                    <p class="mt-2 text-muted small">{{ __('Student marks can only be entered once a student exam ID has been configured by the administrator.') }}</p>
                                @endif
                            @elseif($cross_program)
                                <p class="mt-2 text-muted small"><i class="fas fa-info-circle"></i> Cross-programme mode: You are entering marks for students from all programmes sharing this course.</p>
                            @endif
                        </div>
                        <!-- [ Data table ] end -->
                    </div>

                    <div class="card-footer">
                        <button type="button" class="btn btn-success" id="btnShowConfirmModal"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                    </div>
                    @else
                    <div class="card-block">
                        <h5>{{ __('no_result_found') }}</h5>
                    </div>
                    @endif
                    @endif
                    </form>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@if(isset($rows) && count($rows) > 0 && !((bool) optional($selected_exam_type)->is_final))
<script>
(function(){
    var attWeight = {{ $attendance_weight ?? 0 }};
    function recalcAttScores(){
        var totalEl = document.getElementById('att_total_classes');
        var T = totalEl ? parseFloat(totalEl.value) : NaN;
        document.querySelectorAll('.att-absence').forEach(function(inp){
            var enroll = inp.getAttribute('data-enroll');
            var span = document.querySelector('.att-score[data-enroll="' + enroll + '"]');
            if(!span){ return; }
            if(isNaN(T) || T <= 0){ span.textContent = '0'; return; }
            var A = parseFloat(inp.value);
            if(isNaN(A) || A < 0){ A = 0; }
            if(A > T){ A = T; }
            var score = attWeight * (T - A) / T;
            span.textContent = (Math.round(score * 100) / 100);
        });
    }
    document.addEventListener('input', function(e){
        if(e.target && (e.target.classList.contains('att-absence') || e.target.id === 'att_total_classes')){
            recalcAttScores();
        }
    });
    recalcAttScores();
})();
</script>
@endif

<!-- Confirmation Modal -->
@if(isset($rows) && count($rows) > 0)
<div class="modal fade" id="confirmMarksModal" tabindex="-1" role="dialog" aria-labelledby="confirmMarksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="confirmMarksModalLabel">
                    <i class="fas fa-edit"></i> Confirm Marks Submission
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
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
                                        {{ $firstRow->subject->code ?? '' }} - {{ $firstRow->subject->title ?? '' }}
                                    </span>
                                </p>
                                <p class="mb-2"><strong><i class="fas fa-file-alt"></i> Exam Type:</strong>
                                    <span class="badge {{ $isFinalExam ? 'badge-danger' : 'badge-warning' }}">
                                        {{ $firstRow->type->title ?? '' }}
                                        @if($isFinalExam) (Final Exam) @endif
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><strong><i class="fas fa-calendar-alt"></i> Session:</strong>
                                    {{ $firstRow->studentEnroll->session->title ?? 'N/A' }}
                                </p>
                                <p class="mb-2"><strong><i class="fas fa-star"></i> Maximum Marks:</strong> 
                                    <span class="badge badge-dark">{{ round($maxMarks, 2) }}</span>
                                </p>
                                @if($cross_program)
                                <p class="mb-2">
                                    <span class="badge badge-info"><i class="fas fa-project-diagram"></i> Cross-Programme Mode</span>
                                    <small class="text-muted">({{ $sharing_programs->count() }} programmes)</small>
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Marks Summary Section -->
                <div class="card mb-3 border-success">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-bar text-success"></i> Marks Summary</h6>
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
                                    <h3 class="mb-0" id="markedCount">0</h3>
                                    <small>Marks Entered</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-secondary text-white rounded">
                                    <h3 class="mb-0" id="unmarkedCount">0</h3>
                                    <small>Not Entered</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-info text-white rounded">
                                    <h3 class="mb-0" id="averageMarks">0</h3>
                                    <small>Average</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Completion Progress Bar -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Completion Rate</span>
                                <span id="completionRate">0%</span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" id="completionProgressBar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Distribution Section -->
                <div class="card mb-3 border-info">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-pie text-info"></i> Performance Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="p-2 border rounded border-success">
                                    <h4 class="mb-0 text-success" id="passCount">0</h4>
                                    <small class="text-muted">Pass (≥50%)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded border-danger">
                                    <h4 class="mb-0 text-danger" id="failCount">0</h4>
                                    <small class="text-muted">Fail (&lt;50%)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded">
                                    <h4 class="mb-0 text-primary" id="passRate">0%</h4>
                                    <small class="text-muted">Pass Rate</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Marks Range Distribution -->
                        <div class="mt-3">
                            <small class="text-muted d-block mb-2">Marks Range Distribution:</small>
                            <div class="d-flex justify-content-between" style="font-size: 12px;">
                                <div class="text-center px-1">
                                    <div class="badge badge-success" id="rangeExcellent">0</div>
                                    <div>80-100%</div>
                                </div>
                                <div class="text-center px-1">
                                    <div class="badge badge-primary" id="rangeGood">0</div>
                                    <div>60-79%</div>
                                </div>
                                <div class="text-center px-1">
                                    <div class="badge badge-info" id="rangeAverage">0</div>
                                    <div>50-59%</div>
                                </div>
                                <div class="text-center px-1">
                                    <div class="badge badge-warning" id="rangePoor">0</div>
                                    <div>40-49%</div>
                                </div>
                                <div class="text-center px-1">
                                    <div class="badge badge-danger" id="rangeFail">0</div>
                                    <div>0-39%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Highest & Lowest Marks -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card border-success h-100">
                            <div class="card-body text-center py-2">
                                <i class="fas fa-trophy text-warning fa-2x mb-2"></i>
                                <h4 class="mb-0 text-success" id="highestMarks">--</h4>
                                <small class="text-muted">Highest Marks</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-danger h-100">
                            <div class="card-body text-center py-2">
                                <i class="fas fa-arrow-down text-danger fa-2x mb-2"></i>
                                <h4 class="mb-0 text-danger" id="lowestMarks">--</h4>
                                <small class="text-muted">Lowest Marks</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance summary (only when an attendance migration is bundled) -->
                <div class="card border-info mb-3" id="confirmAttendanceBlock" style="display:none;">
                    <div class="card-header bg-light py-2">
                        <i class="fas fa-user-clock text-info"></i> <strong>{{ __('Attendance to be saved') }}</strong>
                    </div>
                    <div class="card-body py-2">
                        <div class="row text-center">
                            <div class="col-4"><small class="text-muted d-block">{{ __('Date Range') }}</small><span id="confirmAttRange">--</span></div>
                            <div class="col-4"><small class="text-muted d-block">{{ __('Total Classes') }}</small><span id="confirmAttTotal">--</span></div>
                            <div class="col-4"><small class="text-muted d-block">{{ __('Students') }}</small><span id="confirmAttStudents">--</span></div>
                        </div>
                        <p class="mb-0 small text-danger mt-2"><i class="fas fa-exclamation-triangle"></i> {{ __('This replaces each listed student\'s entire attendance for this subject.') }}</p>
                    </div>
                </div>

                <!-- Confirmation Notice -->
                <div class="alert alert-warning mb-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x mr-3"></i>
                        <div>
                            <strong>Please Review Before Submitting!</strong>
                            <p class="mb-0 small">Once submitted, marks will be saved. Ensure all entries are correct before proceeding.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" id="btnConfirmSubmit">
                    <i class="fas fa-check"></i> Confirm & Submit Marks
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     SUCCESS MODAL – shown after marks are saved via session flash
     ══════════════════════════════════════════════════════════════ --}}
@if(session('marks_saved'))
@php $saved = session('marks_saved'); @endphp
<div class="modal fade" id="marksSavedModal" tabindex="-1" role="dialog" aria-labelledby="marksSavedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-success">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="marksSavedModalLabel">
                    <i class="fas fa-check-circle"></i> Marks Submitted Successfully
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
                    <strong>{{ $saved['count'] }}</strong> mark(s) have been saved and locked.
                </p>

                <div class="card bg-light border-0 mx-auto" style="max-width:360px;">
                    <div class="card-body py-2 px-3 text-left" style="font-size:14px;">
                        <p class="mb-1"><i class="fas fa-book text-primary mr-1"></i> <strong>Subject:</strong> {{ $saved['subject'] }}</p>
                        <p class="mb-0"><i class="fas fa-file-alt text-info mr-1"></i> <strong>Exam Type:</strong> {{ $saved['type'] }}</p>
                    </div>
                </div>

                @if($saved['blocked'] > 0)
                <div class="alert alert-warning mt-3 mb-0 py-2 small">
                    <i class="fas fa-exclamation-triangle"></i>
                    {{ $saved['blocked'] }} student(s) were skipped because their exam IDs are missing.
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
    (function ($) {
        "use strict";

        // ========== SUCCESS MODAL (auto-show after marks saved) ==========
        @if(session('marks_saved'))
        $(function() {
            $('#marksSavedModal').modal('show');
        });
        @endif

        // ========== CROSS-PROGRAMME TOGGLE ==========
        $('#crossProgramToggle').on('change', function() {
            var url = new URL(window.location.href);
            if ($(this).is(':checked')) {
                url.searchParams.set('cross_program', '1');
            } else {
                url.searchParams.delete('cross_program');
            }
            // Show loading indicator
            $(this).closest('.card').find('.card-block, .card-body').css('opacity', '0.5');
            window.location.href = url.toString();
        });
        
        // Toggle collapse icon for My Courses section
        $('#myCoursesCollapse').on('shown.bs.collapse', function () {
            $('#collapseIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        }).on('hidden.bs.collapse', function () {
            $('#collapseIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });

        // Program dropdown filter
        $('#quickProgramSelector').on('change', function() {
            var selectedValue = $(this).val();
            
            if (selectedValue === 'all') {
                // Show all programs
                $('.program-section').show();
            } else {
                // Hide all, then show only selected
                $('.program-section').hide();
                $('.program-section[data-program-index="' + selectedValue + '"]').show();
            }
        });

        // View toggle (Cards vs Table)
        $('#viewCards').on('click', function() {
            $(this).addClass('active');
            $('#viewTable').removeClass('active');
            $('.course-cards-view').removeClass('d-none');
            $('.course-table-view').addClass('d-none');
        });

        $('#viewTable').on('click', function() {
            $(this).addClass('active');
            $('#viewCards').removeClass('active');
            $('.course-table-view').removeClass('d-none');
            $('.course-cards-view').addClass('d-none');
        });

        // Year dropdown change handler - Filter semester options by selected year
        $(document).on('change', '.quick-year', function() {
            var $yearSelect = $(this);
            var uniqueId = $yearSelect.data('unique');
            var selectedYear = parseInt($yearSelect.val());
            var $semesterSelect = $('#semester_' + uniqueId);
            
            // Get all semesters data from the semester dropdown
            var allSemesters = $semesterSelect.data('semesters');
            
            if (allSemesters && Array.isArray(allSemesters)) {
                // Show only semesters matching the selected year
                $semesterSelect.find('option').each(function() {
                    var $option = $(this);
                    var optionYear = parseInt($option.data('year'));
                    
                    if (optionYear === selectedYear) {
                        $option.show();
                    } else {
                        $option.hide();
                    }
                });
                
                // Check if current selected option is still visible
                var $currentOption = $semesterSelect.find('option:selected');
                if ($currentOption.css('display') === 'none' || parseInt($currentOption.data('year')) !== selectedYear) {
                    // Select first visible option
                    var $firstVisible = $semesterSelect.find('option:visible:first');
                    if ($firstVisible.length) {
                        $semesterSelect.val($firstVisible.val());
                    }
                }
            }
        });

        // Initialize: Filter semesters on page load to match the default year selection
        $(document).ready(function() {
            $('.quick-year').each(function() {
                $(this).trigger('change');
            });
        });

        // Quick Enter Marks button handler - Auto-populate filter and submit
        $(document).on('click', '.quick-enter-marks', function(e) {
            e.preventDefault();
            
            var btn = $(this);
            var uniqueId = btn.data('unique');
            var faculty = btn.data('faculty');
            var program = btn.data('program');
            var session = btn.data('session');
            var subject = btn.data('subject');
            
            // Get selected values from dropdowns
            var semester = $('#semester_' + uniqueId).val();
            var section = $('#section_' + uniqueId).val() || $('#section_' + uniqueId).attr('value') || 0;
            var examType = $('#examtype_' + uniqueId).val();
            
            // Validate exam type selection
            if (!examType || examType === '') {
                // Highlight the exam type dropdown
                $('#examtype_' + uniqueId).addClass('is-invalid').focus();
                
                // Show a tooltip/popover message
                var $examSelect = $('#examtype_' + uniqueId);
                $examSelect.tooltip({
                    title: '{{ __("Please select an Exam Type first") }}',
                    placement: 'top',
                    trigger: 'manual'
                }).tooltip('show');
                
                setTimeout(function() {
                    $examSelect.tooltip('hide');
                }, 3000);
                
                return false;
            }
            
            // Show loading indicator on button
            var originalHtml = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i> {{ __("Loading...") }}').prop('disabled', true);
            
            // Build URL directly and navigate (more reliable than AJAX + form submit)
            var baseUrl = "{{ route('admin.exam-marking.index') }}";
            var params = new URLSearchParams({
                faculty: faculty,
                program: program,
                session: session,
                semester: semester,
                section: section,
                subject: subject,
                type: examType
            });
            
            // Navigate directly to the URL
            window.location.href = baseUrl + '?' + params.toString();
        });

        // Function to populate advanced filter dropdowns
        function populateAdvancedFilter(faculty, program, session, semester, section, subject, examType, callback) {
            // Set faculty and trigger change to load programs
            $('#faculty').val(faculty);
            
            // Load programs for selected faculty
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-program') }}",
                data: {
                    _token: '{{ csrf_token() }}',
                    faculty: faculty
                },
                success: function(response) {
                    $('option', '#program').remove();
                    $('#program').append('<option value="">{{ __("select") }}</option>');
                    $.each(response, function() {
                        var selected = (this.id == program) ? 'selected' : '';
                        $('#program').append('<option value="' + this.id + '" ' + selected + '>' + this.title + '</option>');
                    });
                    
                    // Load sessions for selected program
                    loadSessions(program, session, semester, section, subject, examType, callback);
                }
            });
        }

        function loadSessions(program, sessionId, semester, section, subject, examType, callback) {
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-session') }}",
                data: {
                    _token: '{{ csrf_token() }}',
                    program: program
                },
                success: function(response) {
                    $('option', '#session').remove();
                    $('#session').append('<option value="">{{ __("select") }}</option>');
                    $.each(response, function() {
                        var selected = (this.id == sessionId) ? 'selected' : '';
                        $('#session').append('<option value="' + this.id + '" ' + selected + '>' + this.title + '</option>');
                    });
                    
                    // Load semesters
                    loadSemesters(program, semester, section, subject, examType, callback);
                }
            });
        }

        function loadSemesters(program, semesterId, section, subject, examType, callback) {
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-semester') }}",
                data: {
                    _token: '{{ csrf_token() }}',
                    program: program
                },
                success: function(response) {
                    $('option', '#semester').remove();
                    $('#semester').append('<option value="0">{{ __("all") }}</option>');
                    $.each(response, function() {
                        var selected = (this.id == semesterId) ? 'selected' : '';
                        $('#semester').append('<option value="' + this.id + '" ' + selected + '>' + this.title + '</option>');
                    });
                    
                    // Load sections
                    loadSections(program, semesterId, section, subject, examType, callback);
                }
            });
        }

        function loadSections(program, semester, sectionId, subject, examType, callback) {
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-section') }}",
                data: {
                    _token: '{{ csrf_token() }}',
                    program: program,
                    semester: semester
                },
                success: function(response) {
                    $('option', '#section').remove();
                    $('#section').append('<option value="0">{{ __("all") }}</option>');
                    $.each(response, function() {
                        var selected = (this.id == sectionId) ? 'selected' : '';
                        $('#section').append('<option value="' + this.id + '" ' + selected + '>' + this.title + '</option>');
                    });
                    
                    // Load subjects
                    loadSubjects(program, $('#session').val(), subject, examType, callback);
                }
            });
        }

        function loadSubjects(program, session, subjectId, examType, callback) {
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-techer-subject') }}",
                data: {
                    _token: '{{ csrf_token() }}',
                    program: program,
                    session: session
                },
                success: function(response) {
                    $('option', '#subject').remove();
                    $('#subject').append('<option value="">{{ __("select") }}</option>');
                    
                    var foundSubject = false;
                    $.each(response, function() {
                        var isSelected = (parseInt(this.id) === parseInt(subjectId));
                        if (isSelected) foundSubject = true;
                        var selected = isSelected ? 'selected' : '';
                        var text = this.code ? (this.code + ' - ' + this.title) : this.title;
                        $('#subject').append('<option value="' + this.id + '" ' + selected + '>' + text + '</option>');
                    });
                    
                    // Explicitly set the subject value and trigger change
                    if (subjectId && foundSubject) {
                        $('#subject').val(subjectId).trigger('change');
                        console.log('Subject set to:', subjectId, 'Found:', foundSubject);
                    } else {
                        console.log('Subject NOT found in dropdown. ID:', subjectId, 'Response count:', response.length);
                    }
                    
                    // Set exam type
                    $('#type').val(examType);
                    
                    // Small delay to ensure DOM is updated before form submission
                    setTimeout(function() {
                        // Execute callback
                        if (typeof callback === 'function') {
                            callback();
                        }
                    }, 100);
                }
            });
        }

        // Remove invalid class when exam type is selected
        $(document).on('change', '.quick-exam-type', function() {
            $(this).removeClass('is-invalid').tooltip('hide');
        });

        // Maximum marks value
        var maxMarksValue = {{ $maxMarks ?? 100 }};
        
        // Real-time validation for marks input
        $(document).on('input change', 'input[name^="marks["]', function() {
            var $input = $(this);
            var value = parseFloat($input.val());
            var maxMarks = parseFloat($input.attr('data-v-max'));
            var minMarks = parseFloat($input.attr('data-v-min'));
            
            // Remove any previous error styling
            $input.removeClass('is-invalid');
            $input.siblings('.invalid-feedback').remove();
            
            if ($input.val() !== '') {
                if (isNaN(value)) {
                    $input.addClass('is-invalid');
                    $input.after('<div class="invalid-feedback" style="display: block;">Please enter a valid number</div>');
                } else if (value < minMarks) {
                    $input.addClass('is-invalid');
                    $input.after('<div class="invalid-feedback" style="display: block;">Marks cannot be less than ' + minMarks + '</div>');
                } else if (value > maxMarks) {
                    $input.addClass('is-invalid');
                    $input.after('<div class="invalid-feedback" style="display: block;">Marks cannot be greater than ' + maxMarks + '</div>');
                }
            }
        });
        
        // Show confirmation modal
        $("#btnShowConfirmModal").on('click', function(e) {
            e.preventDefault();
            
            // First validate all inputs
            var isValid = true;
            var errorMessages = [];
            
            
            $('input[name^="marks["]').each(function() {
                var $input = $(this);
                
                if ($input.is(':disabled')) {
                    return true;
                }
                
                var value = parseFloat($input.val());
                var maxMarks = parseFloat($input.attr('data-v-max'));
                var minMarks = parseFloat($input.attr('data-v-min'));
                
                if ($input.val() !== '') {
                    if (isNaN(value)) {
                        isValid = false;
                        errorMessages.push('Please enter valid numbers for all marks');
                        $input.addClass('is-invalid');
                    } else if (value < minMarks) {
                        isValid = false;
                        errorMessages.push('Marks cannot be less than ' + minMarks);
                        $input.addClass('is-invalid');
                    } else if (value > maxMarks) {
                        isValid = false;
                        errorMessages.push('Marks cannot be greater than ' + maxMarks);
                        $input.addClass('is-invalid');
                    }
                }
            });
            
            if (!isValid) {
                var uniqueErrors = [...new Set(errorMessages)];
                alert('Please fix the following errors:\n\n• ' + uniqueErrors.join('\n• '));
                var firstError = $('.is-invalid').first();
                if (firstError.length) {
                    $('html, body').animate({
                        scrollTop: firstError.offset().top - 100
                    }, 500);
                }
                return false;
            }
            
            // Calculate statistics
            var totalStudents = 0;
            var markedCount = 0;
            var unmarkedCount = 0;
            var totalMarks = 0;
            var passCount = 0;
            var failCount = 0;
            var highestMarks = null;
            var lowestMarks = null;
            var marksArray = [];
            
            // Range counters
            var rangeExcellent = 0; // 80-100%
            var rangeGood = 0;      // 60-79%
            var rangeAverage = 0;   // 50-59%
            var rangePoor = 0;      // 40-49%
            var rangeFail = 0;      // 0-39%
            
            $('input[name^="marks["]').each(function() {
                var $input = $(this);
                totalStudents++;
                
                if ($input.is(':disabled')) {
                    unmarkedCount++;
                    return true;
                }
                
                var value = $input.val().trim();
                
                if (value !== '' && !isNaN(parseFloat(value))) {
                    var marks = parseFloat(value);
                    markedCount++;
                    totalMarks += marks;
                    marksArray.push(marks);
                    
                    // Calculate percentage
                    var percentage = (marks / maxMarksValue) * 100;
                    
                    // Pass/Fail (50% threshold)
                    if (percentage >= 50) {
                        passCount++;
                    } else {
                        failCount++;
                    }
                    
                    // Range distribution
                    if (percentage >= 80) {
                        rangeExcellent++;
                    } else if (percentage >= 60) {
                        rangeGood++;
                    } else if (percentage >= 50) {
                        rangeAverage++;
                    } else if (percentage >= 40) {
                        rangePoor++;
                    } else {
                        rangeFail++;
                    }
                    
                    // Highest/Lowest
                    if (highestMarks === null || marks > highestMarks) {
                        highestMarks = marks;
                    }
                    if (lowestMarks === null || marks < lowestMarks) {
                        lowestMarks = marks;
                    }
                } else {
                    unmarkedCount++;
                }
            });
            
            // Calculate averages and rates
            var averageMarks = markedCount > 0 ? (totalMarks / markedCount).toFixed(2) : 0;
            var completionRate = totalStudents > 0 ? Math.round((markedCount / totalStudents) * 100) : 0;
            var passRate = markedCount > 0 ? Math.round((passCount / markedCount) * 100) : 0;
            
            // Update modal with statistics
            $("#totalStudents").text(totalStudents);
            $("#markedCount").text(markedCount);
            $("#unmarkedCount").text(unmarkedCount);
            $("#averageMarks").text(averageMarks + '/' + maxMarksValue);
            $("#completionRate").text(completionRate + '%');
            $("#completionProgressBar").css('width', completionRate + '%');
            
            // Performance distribution
            $("#passCount").text(passCount);
            $("#failCount").text(failCount);
            $("#passRate").text(passRate + '%');
            
            // Range distribution
            $("#rangeExcellent").text(rangeExcellent);
            $("#rangeGood").text(rangeGood);
            $("#rangeAverage").text(rangeAverage);
            $("#rangePoor").text(rangePoor);
            $("#rangeFail").text(rangeFail);
            
            // Highest/Lowest
            if (highestMarks !== null) {
                var highestPct = ((highestMarks / maxMarksValue) * 100).toFixed(1);
                $("#highestMarks").text(highestMarks + ' (' + highestPct + '%)');
            } else {
                $("#highestMarks").text('--');
            }
            
            if (lowestMarks !== null) {
                var lowestPct = ((lowestMarks / maxMarksValue) * 100).toFixed(1);
                $("#lowestMarks").text(lowestMarks + ' (' + lowestPct + '%)');
            } else {
                $("#lowestMarks").text('--');
            }
            
            // Warn about unmarked students
            if (unmarkedCount > 0 && markedCount > 0) {
                if (!confirm('Note: ' + unmarkedCount + ' student(s) have no marks entered. Do you want to continue?')) {
                    return;
                }
            }
            
            // Attendance summary: only when a migration is bundled (dates + total + ≥1 absence entered)
            var attStart = $('#att_start_date').val();
            var attEnd   = $('#att_end_date').val();
            var attTotal = $('#att_total_classes').val();
            var attStudents = 0;
            $('.att-absence').each(function(){ if ($(this).val() !== '') { attStudents++; } });
            if (attStart && attEnd && attTotal && attStudents > 0) {
                $('#confirmAttRange').text(attStart + ' → ' + attEnd);
                $('#confirmAttTotal').text(attTotal);
                $('#confirmAttStudents').text(attStudents);
                $('#confirmAttendanceBlock').show();
            } else {
                $('#confirmAttendanceBlock').hide();
            }

            // Show the modal
            $("#confirmMarksModal").modal('show');
        });
        
        // Handle confirm submit
        $("#btnConfirmSubmit").on('click', function() {
            // Close modal and submit form
            $("#confirmMarksModal").modal('hide');
            
            setTimeout(function() {
                $('#marksForm').submit();
            }, 300);
        });
        
        // Prevent typing invalid characters
        $(document).on('keypress', 'input[name^="marks["]', function(e) {
            // Allow: backspace, delete, tab, escape, enter, decimal point
            if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Allow: home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            
            // Ensure that it is a number or decimal point
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        // Unlock marks
        $(".unlock-marks").on('click', function(){
            var btn = $(this);
            var examId = btn.data('exam');
            
            if(confirm('Are you sure you want to unlock this mark record?')){
                $.ajax({
                    url: '{{ route("admin.exam-marking.unlock") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        exam_id: examId
                    },
                    success: function(response){
                        if(response.status == 'success'){
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
        
        // ========== MARKING STATUS INDICATOR ==========
        
        // Function to check marking status
        function checkMarkingStatus(uniqueId) {
            var subjectId = $('.quick-enter-marks[data-unique="' + uniqueId + '"]').data('subject');
            var sessionId = $('.quick-enter-marks[data-unique="' + uniqueId + '"]').data('session');
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
                url: "{{ route('admin.exam-marking.check-status') }}",
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
                            '<span class="marked-count text-success" title="Marked">' + response.marked + '</span>' +
                            '<span class="pending-count text-warning ml-2" title="Pending">' + response.pending + '</span>';
                        
                        // Show average marks if available
                        if (response.avg !== null) {
                            html += '<span class="avg-marks text-primary ml-2" title="Average">Avg: ' + response.avg + '</span>';
                        }
                        
                        html += '</div>';
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
            checkMarkingStatus(uniqueId);
        });
        
        // Also check when semester or section changes (if exam type is already selected)
        $(document).on('change', '.quick-semester, .quick-section', function() {
            var id = $(this).attr('id');
            var uniqueId = id.replace('semester_', '').replace('section_', '');
            var examTypeId = $('#examtype_' + uniqueId).val();
            if (examTypeId) {
                checkMarkingStatus(uniqueId);
            }
        });
        
    }(jQuery));
</script>
@endsection