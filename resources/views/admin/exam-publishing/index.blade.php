@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    /* Accordion arrow rotation */
    .accordion-arrow { transition: transform 0.3s ease; }
    [aria-expanded="true"] .accordion-arrow,
    .collapsed .accordion-arrow { transform: rotate(0deg); }
    [aria-expanded="false"] .accordion-arrow { transform: rotate(-90deg); }
    
    /* Multi-mode courses table compact */
    .multi-courses-table td, .multi-courses-table th { 
        padding: 0.35rem 0.5rem; 
        font-size: 0.88em; 
        vertical-align: middle; 
    }

    /* Custom toggle switch */
    .custom-switch-track { cursor: pointer; }
    .custom-switch-track:hover { opacity: 0.85; }
    
    /* Faculty accordion styling */
    #facultyAccordion > .card { border-radius: 6px; overflow: hidden; }
    #facultyAccordion > .card > .card-header:hover { opacity: 0.92; }
    
    /* Progress bar segments */
    .progress-bar { font-size: 0.78em; font-weight: 600; }

    /* ── Fix: Theme forces h5 text to #000 inside card-headers ── */
    /* Override for colored card-header backgrounds so text stays white */
    .card-header.bg-dark h5,
    .card-header.bg-primary h5,
    .card-header.bg-info h5,
    .card-header.bg-success h5,
    .card-header.bg-secondary h5,
    .card-header.bg-danger h5 {
        color: #fff !important;
    }

    /* Remove the theme's decorative blue left-bar on colored headers */
    .card-header.bg-dark h5:after,
    .card-header.bg-primary h5:after,
    .card-header.bg-info h5:after,
    .card-header.bg-success h5:after,
    .card-header.bg-secondary h5:after,
    .card-header.bg-danger h5:after {
        display: none !important;
    }

    /* ── Fix: Badge text-color contrast ── */
    .badge-light  { color: #333 !important; }
    .badge-dark   { color: #fff !important; }

    /* Stat-card h3/small on colored backgrounds */
    .card.bg-dark .card-body,
    .card.bg-primary .card-body,
    .card.bg-info .card-body,
    .card.bg-secondary .card-body,
    .card.bg-success .card-body,
    .card.bg-warning .card-body {
        color: inherit;
    }
    .card.bg-dark h3, .card.bg-dark small,
    .card.bg-primary h3, .card.bg-primary small,
    .card.bg-info h3, .card.bg-info small,
    .card.bg-secondary h3, .card.bg-secondary small,
    .card.bg-success h3, .card.bg-success small {
        color: #fff !important;
    }
    .card.bg-warning h3, .card.bg-warning small {
        color: #333 !important;
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12 no-print">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title"><i class="fas fa-broadcast-tower"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        @if(isset($subjects) && count($subjects) > 0)
                        <div class="btn-group">
                            <a href="{{ route($route.'.download-pdf', [
                                'faculty' => $selected_faculty,
                                'program' => $selected_program,
                                'session' => $selected_session,
                                'semester' => $selected_semester,
                                'section' => $selected_section,
                                'type' => $selected_type
                            ]) }}" class="btn btn-danger" target="_blank">
                                <i class="fas fa-file-pdf"></i> {{ __('Download PDF') }}
                            </a>
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('Print') }}
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Select Program, Session, Semester & Exam Type') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-2">
                                    <label for="faculty">{{ __('field_faculty') }} <span class="text-danger">*</span></label>
                                    <select class="form-control faculty" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($faculties))
                                        @foreach($faculties->sortBy('title') as $fac)
                                        <option value="{{ $fac->id }}" @if($selected_faculty == $fac->id) selected @endif>{{ $fac->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="program">{{ __('field_program') }} <span class="text-danger">*</span></label>
                                    <select class="form-control program" name="program" id="program" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($programs))
                                        @foreach($programs->sortBy('title') as $prog)
                                        <option value="{{ $prog->id }}" @if($selected_program == $prog->id) selected @endif>{{ $prog->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="session">{{ __('field_session') }} <span class="text-danger">*</span></label>
                                    <select class="form-control session" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($sessions))
                                        @foreach($sessions->sortByDesc('id') as $sess)
                                        <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="semester">{{ __('field_semester') }} <span class="text-danger">*</span></label>
                                    <select class="form-control semester" name="semester" id="semester" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($semesters))
                                        @foreach($semesters->sortBy('id') as $sem)
                                        <option value="{{ $sem->id }}" @if($selected_semester == $sem->id) selected @endif>{{ $sem->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="section">{{ __('field_section') }}</label>
                                    <select class="form-control section" name="section" id="section">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($sections))
                                        @foreach($sections->sortBy('title') as $sec)
                                        <option value="{{ $sec->id }}" @if($selected_section == $sec->id) selected @endif>{{ $sec->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="type">{{ __('Exam Type') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="type" id="type" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($types))
                                        @foreach($types as $t)
                                        <option value="{{ $t->id }}" @if($selected_type == $t->id) selected @endif>
                                            {{ $t->title }} {{ $t->is_final ? '(Final)' : '(CA)' }}
                                        </option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-12">
                                    <button type="submit" class="btn btn-info btn-filter">
                                        <i class="fas fa-search"></i> {{ __('Load Courses') }}
                                    </button>

                                    {{-- Multi-Program Toggle --}}
                                    <input type="hidden" name="multi" id="multiInput" value="{{ $multi_mode ? '1' : '0' }}">
                                    <div class="form-check form-switch d-inline-block ml-4" style="vertical-align: middle;">
                                        <label class="form-check-label d-flex align-items-center gap-2" for="multiToggle" style="cursor: pointer; font-weight: 600; font-size: 0.95em;">
                                            <span class="custom-switch-track d-inline-block position-relative" style="width: 44px; height: 24px; background: {{ $multi_mode ? '#28a745' : '#ced4da' }}; border-radius: 12px; transition: background 0.3s; vertical-align: middle;">
                                                <span class="custom-switch-thumb position-absolute" style="width: 20px; height: 20px; background: #fff; border-radius: 50%; top: 2px; left: {{ $multi_mode ? '22px' : '2px' }}; transition: left 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"></span>
                                            </span>
                                            <input type="checkbox" id="multiToggle" style="display: none;" {{ $multi_mode ? 'checked' : '' }}>
                                            <span class="ml-1">
                                                <i class="fas fa-layer-group"></i> {{ __('Multi-Program Mode') }}
                                            </span>
                                        </label>
                                    </div>
                                    @if($multi_mode)
                                    <span class="badge badge-success ml-2" style="font-size: 0.85em;">
                                        <i class="fas fa-check-circle"></i> Active — All Programs Loaded
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if($multi_mode && !empty($faculty_groups))
            {{-- ============================================ --}}
            {{-- MULTI-PROGRAM MODE --}}
            {{-- ============================================ --}}

            <!-- Multi-Mode Info Banner -->
            <div class="col-sm-12 no-print">
                <div class="alert alert-{{ (isset($exam_type) && $exam_type && $exam_type->is_final) ? 'warning' : 'info' }} d-flex align-items-center">
                    <i class="fas fa-layer-group fa-2x mr-3"></i>
                    <div>
                        <strong><i class="fas fa-broadcast-tower"></i> Multi-Program Publishing Mode</strong> —
                        Loading <strong>all programs</strong> across <strong>{{ count($faculty_groups) }} faculties</strong>
                        for <strong>{{ isset($exam_type) ? $exam_type->title : '' }}</strong>
                        @if(isset($is_final_exam) && $is_final_exam)
                            <span class="badge badge-danger ml-1">Final Exam</span>
                        @else
                            <span class="badge badge-warning ml-1">CA</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Multi-Mode Global Stats -->
            <div class="col-sm-12 no-print">
                <div class="row">
                    <div class="col-md-2">
                        <div class="card bg-dark text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ count($faculty_groups) }}</h3>
                                <small>{{ __('Faculties') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['total_programs'] }}</h3>
                                <small>{{ __('Programs') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['total_courses'] }}</h3>
                                <small>{{ __('Total Courses') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ number_format($global_stats['total_students']) }}</h3>
                                <small>{{ __('Total Students') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['published'] }}/{{ $global_stats['total_courses'] }}</h3>
                                <small>{{ __('Published') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['draft'] + $global_stats['submitted'] + $global_stats['checked'] + $global_stats['approved'] }}</h3>
                                <small>{{ __('Pending') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Mode State Distribution -->
            <div class="col-sm-12 no-print">
                @php
                    $totalCourses = $global_stats['total_courses'] ?: 1;
                    $draftPct = round(($global_stats['draft'] / $totalCourses) * 100);
                    $submittedPct = round(($global_stats['submitted'] / $totalCourses) * 100);
                    $checkedPct = round(($global_stats['checked'] / $totalCourses) * 100);
                    $approvedPct = round(($global_stats['approved'] / $totalCourses) * 100);
                    $publishedPct = round(($global_stats['published'] / $totalCourses) * 100);
                @endphp
                <div class="progress mb-3" style="height: 28px; border-radius: 6px;">
                    <div class="progress-bar bg-secondary" style="width: {{ $draftPct }}%" title="Draft: {{ $global_stats['draft'] }}">
                        @if($draftPct > 5) Draft ({{ $global_stats['draft'] }}) @endif
                    </div>
                    <div class="progress-bar bg-info" style="width: {{ $submittedPct }}%" title="Submitted: {{ $global_stats['submitted'] }}">
                        @if($submittedPct > 5) Submitted ({{ $global_stats['submitted'] }}) @endif
                    </div>
                    <div class="progress-bar bg-primary" style="width: {{ $checkedPct }}%" title="Checked: {{ $global_stats['checked'] }}">
                        @if($checkedPct > 5) Checked ({{ $global_stats['checked'] }}) @endif
                    </div>
                    <div class="progress-bar bg-warning text-dark" style="width: {{ $approvedPct }}%" title="Approved: {{ $global_stats['approved'] }}">
                        @if($approvedPct > 5) Approved ({{ $global_stats['approved'] }}) @endif
                    </div>
                    <div class="progress-bar bg-success" style="width: {{ $publishedPct }}%" title="Published: {{ $global_stats['published'] }}">
                        @if($publishedPct > 5) Published ({{ $global_stats['published'] }}) @endif
                    </div>
                </div>
            </div>

            <!-- Multi-Mode Global Bulk Actions -->
            <div class="col-sm-12 no-print">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks"></i> {{ __('Global Bulk Workflow Actions') }}</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-light" id="multiSelectAllBtn">
                                <i class="fas fa-check-square"></i> Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light" id="multiDeselectAllBtn">
                                <i class="fas fa-square"></i> Deselect All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light" id="multiSelectSameStateBtn">
                                <i class="fas fa-filter"></i> Select Same State
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light" id="multiExpandAllBtn">
                                <i class="fas fa-expand-arrows-alt"></i> Expand All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light" id="multiCollapseAllBtn">
                                <i class="fas fa-compress-arrows-alt"></i> Collapse All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="multiBulkTransitionForm" method="POST" action="{{ route($route.'.bulk-transition-multi') }}">
                            @csrf
                            <input type="hidden" name="session_id" value="{{ $selected_session }}">
                            <input type="hidden" name="semester_id" value="{{ $selected_semester }}">
                            <input type="hidden" name="exam_type_id" value="{{ $selected_type }}">
                            <div id="multiSelectedStatesContainer"></div>

                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label>{{ __('Selected Courses') }}</label>
                                    <div id="multiSelectedCount" class="alert alert-secondary py-2 mb-0">
                                        <strong>0</strong> course(s) selected across <strong>0</strong> program(s)
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label for="multiBulkState">{{ __('Transition To') }}</label>
                                    <select class="form-control" name="state" id="multiBulkState" required>
                                        <option value="">{{ __('select') }}</option>
                                        @can('exam-publishing-submit')
                                        <option value="submitted">Submitted</option>
                                        @endcan
                                        @can('exam-publishing-check')
                                        <option value="checked">Checked</option>
                                        @endcan
                                        @can('exam-publishing-approve')
                                        <option value="approved">Approved</option>
                                        @endcan
                                        @can('exam-publishing-publish')
                                        <option value="published">Published</option>
                                        @endcan
                                    </select>
                                </div>
                                <div class="col-md-2 multi-publish-field" style="display: none;">
                                    <label for="multiPublishDate">{{ __('Publish Date') }}</label>
                                    <input type="date" class="form-control" name="publish_date" id="multiPublishDate" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-2 multi-publish-field" style="display: none;">
                                    <label for="multiPublishTime">{{ __('Publish Time') }}</label>
                                    <input type="time" class="form-control" name="publish_time" id="multiPublishTime" value="{{ date('H:i') }}">
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-success btn-block" id="multiBulkTransitionBtn" disabled>
                                        <i class="fas fa-arrow-right"></i> {{ __('Apply Transition to All Selected') }}
                                    </button>
                                </div>
                            </div>

                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <label for="multiBulkNotes">{{ __('Notes') }} ({{ __('optional') }})</label>
                                    <input type="text" class="form-control" name="notes" id="multiBulkNotes" placeholder="Add notes for this multi-program transition...">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Faculty → Program Accordion Groups -->
            <div class="col-sm-12">
                <div class="accordion" id="facultyAccordion">
                    @foreach($faculty_groups as $facultyId => $fg)
                    @php
                        $fac = $fg['faculty'];
                        $facPrograms = $fg['programs'];
                        $facCourseCount = 0;
                        $facStudentCount = 0;
                        $facPublished = 0;
                        $facTotal = 0;
                        foreach ($facPrograms as $pg) {
                            $facCourseCount += count($pg['subjects']);
                            $facStudentCount += $pg['total_students'];
                            foreach ($pg['subject_states'] as $ss) {
                                $facTotal++;
                                if (($ss->workflow_state ?? 'draft') === 'published') $facPublished++;
                            }
                        }
                    @endphp
                    <div class="card mb-2 border-dark">
                        <div class="card-header bg-dark text-white py-2" id="facultyHead{{ $facultyId }}" style="cursor: pointer;"
                             data-bs-toggle="collapse" data-bs-target="#facultyBody{{ $facultyId }}" aria-expanded="true">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-university mr-1"></i> {{ $fac->title }}
                                    <span class="badge badge-light ml-2">{{ count($facPrograms) }} {{ __('Programs') }}</span>
                                </h5>
                                <div>
                                    <span class="badge badge-info">{{ $facCourseCount }} Courses</span>
                                    <span class="badge badge-primary">{{ number_format($facStudentCount) }} Students</span>
                                    <span class="badge badge-success">{{ $facPublished }}/{{ $facTotal }} Published</span>
                                    <i class="fas fa-chevron-down ml-2 accordion-arrow"></i>
                                </div>
                            </div>
                        </div>
                        <div id="facultyBody{{ $facultyId }}" class="collapse show" data-bs-parent="#facultyAccordion">
                            <div class="card-body p-2">
                                {{-- Program Sub-Accordion --}}
                                <div class="accordion" id="programAccordion{{ $facultyId }}">
                                    @foreach($facPrograms as $programId => $pg)
                                    @php
                                        $prog = $pg['program'];
                                        $progSubjects = $pg['subjects'];
                                        $progStates = $pg['subject_states'];
                                        $progStats = $pg['subject_stats'];
                                        $progStudents = $pg['total_students'];
                                        $progPublished = collect($progStates)->where('workflow_state', 'published')->count();
                                        $progDraft = collect($progStates)->where('workflow_state', 'draft')->count();
                                        $isFinalForProg = $pg['is_final_exam'];
                                        $caPublishedForProg = $pg['ca_published'] ?? true;
                                        $caStatusForProg = $pg['ca_publishing_status'] ?? [];
                                    @endphp
                                    <div class="card mb-1 border-secondary">
                                        <div class="card-header bg-light py-2" id="programHead{{ $facultyId }}_{{ $programId }}" style="cursor: pointer;"
                                             data-bs-toggle="collapse" data-bs-target="#programBody{{ $facultyId }}_{{ $programId }}" aria-expanded="false">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-graduation-cap mr-1 text-primary"></i> 
                                                    {{ $prog->title }}
                                                    @if($prog->short_code)
                                                    <small class="text-muted">({{ $prog->short_code }})</small>
                                                    @endif
                                                </h6>
                                                <div>
                                                    <span class="badge badge-secondary">{{ count($progSubjects) }} Courses</span>
                                                    <span class="badge badge-primary">{{ $progStudents }} Students</span>
                                                    @if($progPublished === count($progSubjects) && count($progSubjects) > 0)
                                                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> All Published</span>
                                                    @elseif($progPublished > 0)
                                                        <span class="badge badge-warning text-dark">{{ $progPublished }}/{{ count($progSubjects) }} Published</span>
                                                    @else
                                                        <span class="badge badge-secondary">{{ $progDraft }} Draft</span>
                                                    @endif
                                                    <i class="fas fa-chevron-down ml-1 accordion-arrow text-muted"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="programBody{{ $facultyId }}_{{ $programId }}" class="collapse">
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover table-sm mb-0 multi-courses-table">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th class="text-center" style="width: 35px;">
                                                                    <input type="checkbox" class="form-check-input program-check-all" data-program="{{ $programId }}" data-faculty="{{ $facultyId }}">
                                                                </th>
                                                                <th style="width: 80px;">{{ __('Code') }}</th>
                                                                <th>{{ __('Course Title') }}</th>
                                                                <th class="text-center" style="width: 50px;">CV</th>
                                                                <th class="text-center" style="width: 80px;">{{ __('Students') }}</th>
                                                                <th class="text-center" style="width: 80px;">{{ __('With Marks') }}</th>
                                                                <th class="text-center" style="width: 60px;">{{ __('Pass') }}</th>
                                                                <th class="text-center" style="width: 60px;">{{ __('Fail') }}</th>
                                                                <th class="text-center" style="width: 70px;">{{ __('Avg') }}</th>
                                                                @if($isFinalForProg)
                                                                <th class="text-center" style="width: 80px;">{{ __('CA') }}</th>
                                                                @endif
                                                                <th class="text-center" style="width: 110px;">{{ __('State') }}</th>
                                                                <th class="text-center no-print" style="width: 160px;">{{ __('Actions') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($progSubjects as $subject)
                                                            @php
                                                                $state = $progStates[$subject->id] ?? null;
                                                                $stats = $progStats[$subject->id] ?? [];
                                                                $currentState = $state ? $state->workflow_state : 'draft';
                                                                $badge = $state ? $state->getStateBadge() : ['class' => 'badge-secondary', 'icon' => 'fa-pencil-alt', 'text' => 'Draft'];
                                                                $hasMarks = ($stats['with_marks'] ?? 0) > 0;
                                                                $caStatus = $isFinalForProg ? ($caStatusForProg[$subject->id] ?? false) : true;
                                                            @endphp
                                                            <tr class="{{ !$hasMarks ? 'table-light text-muted' : '' }}" 
                                                                data-state="{{ $currentState }}" 
                                                                data-subject-id="{{ $subject->id }}"
                                                                data-program-id="{{ $programId }}"
                                                                data-has-marks="{{ $hasMarks ? '1' : '0' }}">
                                                                <td class="text-center">
                                                                    <input type="checkbox" class="form-check-input multi-course-checkbox"
                                                                        value="{{ $state ? $state->id : '' }}"
                                                                        data-state="{{ $currentState }}"
                                                                        data-program-id="{{ $programId }}"
                                                                        data-faculty-id="{{ $facultyId }}"
                                                                        data-subject-code="{{ $subject->code }}"
                                                                        {{ !$hasMarks || !$state ? 'disabled' : '' }}>
                                                                </td>
                                                                <td><span class="badge badge-dark">{{ $subject->code }}</span></td>
                                                                <td>{{ $subject->title ?? $subject->subject_name }}</td>
                                                                <td class="text-center">{{ $subject->credit_hour }}</td>
                                                                <td class="text-center">{{ $stats['total'] ?? 0 }}</td>
                                                                <td class="text-center">
                                                                    @if($hasMarks)
                                                                    <span class="badge badge-info">{{ $stats['with_marks'] }}</span>
                                                                    @else
                                                                    <span class="badge badge-danger">0</span>
                                                                    @endif
                                                                </td>
                                                                <td class="text-center text-success">{{ $stats['passed'] ?? 0 }}</td>
                                                                <td class="text-center text-danger">{{ $stats['failed'] ?? 0 }}</td>
                                                                <td class="text-center">
                                                                    @if(isset($stats['average']) && $stats['average'] !== '-')
                                                                        {{ $stats['average'] }}/{{ $stats['contribution'] ?? 100 }}
                                                                    @else - @endif
                                                                </td>
                                                                @if($isFinalForProg)
                                                                <td class="text-center">
                                                                    @if($caStatus)
                                                                    <span class="badge badge-success"><i class="fas fa-check"></i></span>
                                                                    @else
                                                                    <span class="badge badge-danger"><i class="fas fa-times"></i></span>
                                                                    @endif
                                                                </td>
                                                                @endif
                                                                <td class="text-center">
                                                                    <span class="badge {{ $badge['class'] }}">
                                                                        <i class="fas {{ $badge['icon'] }}"></i> {{ $badge['text'] }}
                                                                    </span>
                                                                </td>
                                                                <td class="text-center no-print">
                                                                    @if($state && $hasMarks)
                                                                        @php $nextStates = $state->getNextStates(); $canPublish = !$isFinalForProg || $caStatus; @endphp
                                                                        @foreach($nextStates as $nextState)
                                                                            @if($nextState === 'published' && !$canPublish)
                                                                                <button type="button" class="btn btn-sm btn-secondary" disabled title="CA must be published first">
                                                                                    <i class="fas fa-lock"></i>
                                                                                </button>
                                                                            @else
                                                                                @can('exam-publishing-' . ($nextState === 'submitted' ? 'submit' : ($nextState === 'checked' ? 'check' : ($nextState === 'approved' ? 'approve' : 'publish'))))
                                                                                <button type="button" class="btn btn-sm btn-{{ $nextState === 'published' ? 'success' : 'primary' }} transition-btn"
                                                                                    data-state-id="{{ $state->id }}"
                                                                                    data-target-state="{{ $nextState }}"
                                                                                    data-subject-code="{{ $subject->code }}"
                                                                                    title="Transition to {{ ucfirst($nextState) }}">
                                                                                    <i class="fas fa-{{ $nextState === 'submitted' ? 'paper-plane' : ($nextState === 'checked' ? 'check' : ($nextState === 'approved' ? 'thumbs-up' : 'globe')) }}"></i>
                                                                                </button>
                                                                                @endcan
                                                                            @endif
                                                                        @endforeach
                                                                        <button type="button" class="btn btn-sm btn-outline-info history-btn"
                                                                            data-state-id="{{ $state->id }}"
                                                                            data-subject-code="{{ $subject->code }}"
                                                                            title="View History">
                                                                            <i class="fas fa-history"></i>
                                                                        </button>
                                                                    @elseif(!$hasMarks)
                                                                        <span class="text-muted small">No marks</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            @elseif(isset($subjects) && count($subjects) > 0)
                    <i class="fas fa-{{ $is_final_exam ? 'exclamation-triangle' : 'info-circle' }} fa-2x mr-3"></i>
                    <div>
                        @if($is_final_exam)
                            <strong>Final Exam Mode:</strong> 
                            You are publishing <strong>{{ $exam_type->title }}</strong> marks. 
                            CA must be published before Final Exam can be published.
                            @if(!$ca_published)
                                <span class="text-danger"><strong>Warning:</strong> Some courses have unpublished CA results.</span>
                            @endif
                        @else
                            <strong>Continuous Assessment (CA) Mode:</strong> 
                            You are publishing <strong>{{ $exam_type->title }}</strong> marks. 
                            Attendance will NOT be published with CA.
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="col-sm-12 no-print">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ count($subjects) }}</h3>
                                <small>Total Courses</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $total_students }}</h3>
                                <small>Total Students</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center py-3">
                                @php
                                    $draftCount = collect($subject_states)->where('workflow_state', 'draft')->count();
                                    $submittedCount = collect($subject_states)->where('workflow_state', 'submitted')->count();
                                    $checkedCount = collect($subject_states)->where('workflow_state', 'checked')->count();
                                    $approvedCount = collect($subject_states)->where('workflow_state', 'approved')->count();
                                    $publishedCount = collect($subject_states)->where('workflow_state', 'published')->count();
                                @endphp
                                <h3 class="mb-0">{{ $publishedCount }}/{{ count($subjects) }}</h3>
                                <small>Published Courses</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $exam_type->title }}</h3>
                                <small>{{ $is_final_exam ? 'Final Exam' : 'CA' }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Card -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-tasks"></i> {{ __('Bulk Workflow Actions') }}</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">
                                <i class="fas fa-check-square"></i> Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">
                                <i class="fas fa-square"></i> Deselect All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" id="selectSameStateBtn">
                                <i class="fas fa-filter"></i> Select Same State
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="bulkTransitionForm" method="POST" action="{{ route($route.'.bulk-transition') }}">
                            @csrf
                            <input type="hidden" name="program_id" value="{{ $selected_program }}">
                            <input type="hidden" name="session_id" value="{{ $selected_session }}">
                            <input type="hidden" name="semester_id" value="{{ $selected_semester }}">
                            <input type="hidden" name="section_id" value="{{ $selected_section ?: '' }}">
                            <input type="hidden" name="exam_type_id" value="{{ $selected_type }}">
                            <div id="selectedSubjectsContainer"></div>

                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label>{{ __('Selected Courses') }}</label>
                                    <div id="selectedCount" class="alert alert-secondary py-2 mb-0">
                                        <strong>0</strong> course(s) selected
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label for="bulkState">{{ __('Transition To') }}</label>
                                    <select class="form-control" name="state" id="bulkState" required>
                                        <option value="">{{ __('select') }}</option>
                                        @can('exam-publishing-submit')
                                        <option value="submitted">Submitted</option>
                                        @endcan
                                        @can('exam-publishing-check')
                                        <option value="checked">Checked</option>
                                        @endcan
                                        @can('exam-publishing-approve')
                                        <option value="approved">Approved</option>
                                        @endcan
                                        @can('exam-publishing-publish')
                                        <option value="published">Published</option>
                                        @endcan
                                    </select>
                                </div>
                                <div class="col-md-2 publish-datetime-field" id="publishDateField" style="display: none;">
                                    <label for="bulkPublishDate">{{ __('Publish Date') }}</label>
                                    <input type="date" class="form-control" name="publish_date" id="bulkPublishDate" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-2 publish-datetime-field" id="publishTimeField" style="display: none;">
                                    <label for="bulkPublishTime">{{ __('Publish Time') }}</label>
                                    <input type="time" class="form-control" name="publish_time" id="bulkPublishTime" value="{{ date('H:i') }}">
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary btn-block" id="bulkTransitionBtn" disabled>
                                        <i class="fas fa-arrow-right"></i> {{ __('Apply Transition') }}
                                    </button>
                                </div>
                            </div>

                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <label for="bulkNotes">{{ __('Notes') }} ({{ __('optional') }})</label>
                                    <input type="text" class="form-control" name="notes" id="bulkNotes" placeholder="Add notes for this transition...">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Courses Grid -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-table"></i> {{ __('Courses Publishing Status') }} ({{ count($subjects) }} Courses)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0" id="coursesTable">
                                <thead class="thead-dark">
                                    <tr>
                                        <th class="text-center" style="width: 40px;">
                                            <input type="checkbox" id="checkAll" class="form-check-input">
                                        </th>
                                        <th style="width: 80px;">{{ __('Code') }}</th>
                                        <th>{{ __('Course Title') }}</th>
                                        <th class="text-center" style="width: 60px;">{{ __('CV') }}</th>
                                        <th class="text-center" style="width: 100px;">{{ __('Students') }}</th>
                                        <th class="text-center" style="width: 100px;">{{ __('With Marks') }}</th>
                                        <th class="text-center" style="width: 80px;">{{ __('Pass') }}</th>
                                        <th class="text-center" style="width: 80px;">{{ __('Fail') }}</th>
                                        <th class="text-center" style="width: 80px;">{{ __('Avg') }}</th>
                                        @if($is_final_exam)
                                        <th class="text-center" style="width: 100px;">{{ __('CA Status') }}</th>
                                        @endif
                                        <th class="text-center" style="width: 120px;">{{ __('Workflow State') }}</th>
                                        <th class="text-center no-print" style="width: 200px;">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subjects as $subject)
                                    @php
                                        $state = $subject_states[$subject->id] ?? null;
                                        $stats = $subject_stats[$subject->id] ?? [];
                                        $currentState = $state ? $state->workflow_state : 'draft';
                                        $badge = $state ? $state->getStateBadge() : ['class' => 'badge-secondary', 'icon' => 'fa-pencil-alt', 'text' => 'Draft'];
                                        $hasMarks = ($stats['with_marks'] ?? 0) > 0;
                                        $caStatus = $is_final_exam ? ($ca_publishing_status[$subject->id] ?? false) : true;
                                    @endphp
                                    <tr class="{{ !$hasMarks ? 'table-light text-muted' : '' }}" data-state="{{ $currentState }}" data-subject-id="{{ $subject->id }}" data-has-marks="{{ $hasMarks ? '1' : '0' }}">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input course-checkbox" 
                                                value="{{ $subject->id }}" 
                                                data-state="{{ $currentState }}"
                                                {{ !$hasMarks ? 'disabled' : '' }}>
                                        </td>
                                        <td>
                                            <span class="badge badge-dark">{{ $subject->code }}</span>
                                        </td>
                                        <td>{{ $subject->title ?? $subject->subject_name }}</td>
                                        <td class="text-center">{{ $subject->credit_hour }}</td>
                                        <td class="text-center">{{ $stats['total'] ?? 0 }}</td>
                                        <td class="text-center">
                                            @if($hasMarks)
                                            <span class="badge badge-info">{{ $stats['with_marks'] }}</span>
                                            @else
                                            <span class="badge badge-danger">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-success">{{ $stats['passed'] ?? 0 }}</td>
                                        <td class="text-center text-danger">{{ $stats['failed'] ?? 0 }}</td>
                                        <td class="text-center">
                                            @if(isset($stats['average']) && $stats['average'] !== '-')
                                                {{ $stats['average'] }}/{{ $stats['contribution'] ?? 100 }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        @if($is_final_exam)
                                        <td class="text-center">
                                            @if($caStatus)
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Published</span>
                                            @else
                                            <span class="badge badge-danger"><i class="fas fa-times"></i> Not Published</span>
                                            @endif
                                        </td>
                                        @endif
                                        <td class="text-center">
                                            <span class="badge {{ $badge['class'] }}">
                                                <i class="fas {{ $badge['icon'] }}"></i> {{ $badge['text'] }}
                                            </span>
                                        </td>
                                        <td class="text-center no-print">
                                            @if($state && $hasMarks)
                                                @php
                                                    $nextStates = $state->getNextStates();
                                                    $canPublish = !$is_final_exam || $caStatus;
                                                @endphp
                                                
                                                @foreach($nextStates as $nextState)
                                                    @if($nextState === 'published' && !$canPublish)
                                                        <button type="button" class="btn btn-sm btn-secondary" disabled title="CA must be published first">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    @else
                                                        @can('exam-publishing-' . ($nextState === 'submitted' ? 'submit' : ($nextState === 'checked' ? 'check' : ($nextState === 'approved' ? 'approve' : 'publish'))))
                                                        <button type="button" class="btn btn-sm btn-{{ $nextState === 'published' ? 'success' : 'primary' }} transition-btn"
                                                            data-state-id="{{ $state->id }}"
                                                            data-target-state="{{ $nextState }}"
                                                            data-subject-code="{{ $subject->code }}"
                                                            title="Transition to {{ ucfirst($nextState) }}">
                                                            <i class="fas fa-{{ $nextState === 'submitted' ? 'paper-plane' : ($nextState === 'checked' ? 'check' : ($nextState === 'approved' ? 'thumbs-up' : 'globe')) }}"></i>
                                                            {{ ucfirst($nextState) }}
                                                        </button>
                                                        @endcan
                                                    @endif
                                                @endforeach

                                                <button type="button" class="btn btn-sm btn-outline-info history-btn" 
                                                    data-state-id="{{ $state->id }}"
                                                    data-subject-code="{{ $subject->code }}"
                                                    title="View History">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                            @elseif(!$hasMarks)
                                                <span class="text-muted small">No marks</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Preview (Expandable) -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-users"></i> {{ __('Student Marks Preview') }}
                            <button type="button" class="btn btn-sm btn-outline-primary ml-2" data-bs-toggle="collapse" data-bs-target="#studentsPreview">
                                <i class="fas fa-eye"></i> Toggle View
                            </button>
                            <a href="{{ route($route.'.download-marks-pdf', [
                                'faculty' => $selected_faculty,
                                'program' => $selected_program,
                                'session' => $selected_session,
                                'semester' => $selected_semester,
                                'section' => $selected_section,
                                'type' => $selected_type
                            ]) }}" class="btn btn-sm btn-danger ml-2" target="_blank">
                                <i class="fas fa-file-pdf"></i> Download Marks PDF
                            </a>
                            <span class="float-right">
                                <small class="text-muted">
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i></span> = Registered &amp; Passed |
                                    <span class="badge badge-danger"><i class="fas fa-times-circle"></i></span> = Registered &amp; Failed |
                                    <span class="badge badge-secondary"><i class="fas fa-minus-circle"></i></span> = Not Registered
                                </small>
                            </span>
                        </h5>
                    </div>
                    <div class="collapse" id="studentsPreview">
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px; overflow: auto;">
                                <table class="table table-bordered table-sm mb-0" style="font-size: 0.85em;">
                                    <thead class="thead-dark" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th class="text-center" style="min-width: 40px;">S/N</th>
                                            <th class="text-center" style="min-width: 100px;">Mat No.</th>
                                            <th style="min-width: 180px;">Name</th>
                                            @foreach($subjects as $subject)
                                            <th class="text-center" style="min-width: 90px; background: #495057;">
                                                <small>{{ $subject->code }}</small>
                                            </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($students as $index => $student)
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td class="text-center"><small>{{ $student['matricule'] }}</small></td>
                                            <td>{{ Str::limit($student['name'], 25) }}</td>
                                            @foreach($subjects as $subject)
                                                @php
                                                    $courseData = $student['courses'][$subject->id] ?? null;
                                                    $isRegistered = $courseData['registered'] ?? false;
                                                    $hasMarks = $courseData && $courseData['has_marks'];
                                                    $marks = $courseData['marks'] ?? null;
                                                    $contribution = $courseData['contribution'] ?? 0;
                                                    $passMark = $courseData['pass_mark'] ?? 0;
                                                    $total = $courseData['total'] ?? null;
                                                    $workflowState = $courseData['workflow_state'] ?? 'draft';
                                                    $examTypePassFail = $courseData['exam_type_pass_fail'] ?? null; // For this exam type
                                                    $overallPassFail = $courseData['overall_pass_fail'] ?? null; // For overall course
                                                    $grade = $courseData['grade'] ?? null;
                                                    
                                                    // Determine cell styling based on EXAM TYPE pass/fail (what we're viewing)
                                                    $cellClass = '';
                                                    $cellBg = '';
                                                    if (!$isRegistered) {
                                                        $cellBg = 'background: #f8f9fa;'; // Light gray for not registered
                                                        $cellClass = 'text-muted';
                                                    } elseif ($examTypePassFail === 'pass') {
                                                        $cellBg = 'background: #d4edda;'; // Light green for pass
                                                        $cellClass = 'text-success';
                                                    } elseif ($examTypePassFail === 'fail') {
                                                        $cellBg = 'background: #f8d7da;'; // Light red for fail
                                                        $cellClass = 'text-danger';
                                                    } elseif ($hasMarks) {
                                                        $cellBg = 'background: #fff3cd;'; // Light yellow for has marks but status unknown
                                                        $cellClass = 'text-dark';
                                                    }
                                                    
                                                    // Workflow state badge
                                                    $stateBadges = [
                                                        'draft' => ['class' => 'badge-secondary', 'icon' => 'edit', 'short' => 'D'],
                                                        'submitted' => ['class' => 'badge-info', 'icon' => 'paper-plane', 'short' => 'S'],
                                                        'checked' => ['class' => 'badge-primary', 'icon' => 'check', 'short' => 'C'],
                                                        'approved' => ['class' => 'badge-warning', 'icon' => 'thumbs-up', 'short' => 'A'],
                                                        'published' => ['class' => 'badge-success', 'icon' => 'globe', 'short' => 'P'],
                                                    ];
                                                    $stateBadge = $stateBadges[$workflowState] ?? $stateBadges['draft'];
                                                @endphp
                                                <td class="text-center {{ $cellClass }}" style="{{ $cellBg }} position: relative;">
                                                    @if(!$isRegistered)
                                                        <span class="text-muted" title="Not Registered">
                                                            <i class="fas fa-minus-circle"></i>
                                                        </span>
                                                    @else
                                                        {{-- Registration indicator --}}
                                                        <span class="position-absolute" style="top: 2px; left: 3px; font-size: 0.6em;">
                                                            <i class="fas fa-check text-success" title="Registered"></i>
                                                        </span>
                                                        
                                                        {{-- Main content: Marks --}}
                                                        @if($hasMarks)
                                                            <strong title="{{ $marks }}/{{ $contribution }} (Pass: {{ $passMark }}){{ $total ? ' | Total: ' . $total : '' }}{{ $grade ? ' | Grade: ' . $grade : '' }}">
                                                                {{ number_format($marks, 1) }}
                                                            </strong>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                        
                                                        {{-- Workflow state indicator (bottom-left) --}}
                                                        <span class="position-absolute" style="bottom: 1px; left: 3px;">
                                                            <span class="badge {{ $stateBadge['class'] }}" style="font-size: 0.55em; padding: 1px 3px;" title="{{ ucfirst($workflowState) }}">
                                                                {{ $stateBadge['short'] }}
                                                            </span>
                                                        </span>
                                                        
                                                        {{-- Exam Type Pass/Fail indicator (bottom-right) --}}
                                                        @if($examTypePassFail)
                                                        <span class="position-absolute" style="bottom: 1px; right: 3px;">
                                                            @if($examTypePassFail === 'pass')
                                                                <i class="fas fa-check-circle text-success" style="font-size: 0.7em;" title="Passed this exam type ({{ $marks }}/{{ $contribution }} >= {{ $passMark }})"></i>
                                                            @else
                                                                <i class="fas fa-times-circle text-danger" style="font-size: 0.7em;" title="Failed this exam type ({{ $marks }}/{{ $contribution }} < {{ $passMark }})"></i>
                                                            @endif
                                                        </span>
                                                        @endif
                                                        
                                                        {{-- Overall Course Grade indicator (top-right) --}}
                                                        @if($grade)
                                                        <span class="position-absolute" style="top: 1px; right: 3px;">
                                                            <span class="badge {{ $overallPassFail === 'pass' ? 'badge-success' : 'badge-danger' }}" style="font-size: 0.5em; padding: 1px 2px;" title="Overall Grade: {{ $grade }} (Total: {{ $total }})">
                                                                {{ $grade }}
                                                            </span>
                                                        </span>
                                                        @endif
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            {{-- Legend --}}
                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small><strong>Cell Colors (Exam Type Pass/Fail):</strong></small><br>
                                        <small>
                                            <span style="display: inline-block; width: 15px; height: 15px; background: #d4edda; border: 1px solid #c3e6cb;"></span> Passed (marks ≥ 50% of contribution) |
                                            <span style="display: inline-block; width: 15px; height: 15px; background: #f8d7da; border: 1px solid #f5c6cb;"></span> Failed |
                                            <span style="display: inline-block; width: 15px; height: 15px; background: #fff3cd; border: 1px solid #ffeeba;"></span> Has Marks |
                                            <span style="display: inline-block; width: 15px; height: 15px; background: #f8f9fa; border: 1px solid #e9ecef;"></span> Not Registered
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small><strong>Workflow States:</strong></small><br>
                                        <small>
                                            <span class="badge badge-secondary">D</span> Draft |
                                            <span class="badge badge-info">S</span> Submitted |
                                            <span class="badge badge-primary">C</span> Checked |
                                            <span class="badge badge-warning">A</span> Approved |
                                            <span class="badge badge-success">P</span> Published
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small><strong>Indicators:</strong></small><br>
                                        <small>
                                            <i class="fas fa-check text-success"></i> Registered |
                                            <i class="fas fa-check-circle text-success"></i> Exam Type Passed |
                                            <i class="fas fa-times-circle text-danger"></i> Exam Type Failed |
                                            <span class="badge badge-success" style="font-size: 0.6em;">A</span> Overall Grade
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Results Preview Draft -->
            @if(isset($draft_student_results) && count($draft_student_results) > 0)
            <div class="col-sm-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list"></i> {{ __('Students Results Preview') }}
                            <span class="badge badge-dark ml-1">DRAFT</span>
                            <button type="button" class="btn btn-sm btn-outline-dark ml-2" data-bs-toggle="collapse" data-bs-target="#draftResultsPreview">
                                <i class="fas fa-eye"></i> Toggle View
                            </button>
                            <button type="button" class="btn btn-sm btn-dark ml-1" onclick="printDraftResults()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <form method="post" action="{{ route($route.'.export-draft-results') }}" class="d-inline ml-1">
                                @csrf
                                <input type="hidden" name="program" value="{{ $selected_program }}">
                                <input type="hidden" name="session" value="{{ $selected_session }}">
                                <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                <input type="hidden" name="section" value="{{ $selected_section }}">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-file-excel"></i> Export to Excel
                                </button>
                            </form>
                            <span class="float-right">
                                <small>
                                    {{ count($draft_student_results) }} Students &times; {{ count($draft_subjects) }} Courses |
                                    <span class="text-success font-weight-bold">Pass: {{ $draft_overall_stats['total_passed'] }}</span> |
                                    <span class="text-danger font-weight-bold">Fail: {{ $draft_overall_stats['total_failed'] }}</span>
                                    @if($draft_overall_stats['total_pending'] > 0)
                                    | <span class="text-muted">Pending: {{ $draft_overall_stats['total_pending'] }}</span>
                                    @endif
                                </small>
                            </span>
                        </h5>
                    </div>
                    <div class="collapse" id="draftResultsPreview">
                        <div class="card-body p-0">
                            {{-- Draft watermark banner --}}
                            <div class="alert alert-warning text-center mb-0 py-1" style="border-radius: 0;">
                                <small><i class="fas fa-exclamation-triangle"></i> <strong>DRAFT PREVIEW</strong> &mdash; This shows all results regardless of publishing status. Marks may change before final publication.</small>
                            </div>
                            <div class="table-responsive" id="draftResultsTable" style="max-height: 600px; overflow: auto;">
                                <table class="table table-bordered table-sm mb-0" style="font-size: 0.8em;">
                                    <thead class="thead-dark" style="position: sticky; top: 0; z-index: 10;">
                                        {{-- Course Headers Row --}}
                                        <tr>
                                            <th rowspan="2" class="text-center align-middle" style="min-width: 35px; background: #343a40; position: sticky; left: 0; z-index: 11;">S/N</th>
                                            <th rowspan="2" class="text-center align-middle" style="min-width: 95px; background: #343a40; position: sticky; left: 35px; z-index: 11;">Mat No.</th>
                                            <th rowspan="2" class="align-middle" style="min-width: 160px; background: #343a40; position: sticky; left: 130px; z-index: 11;">Name</th>
                                            @foreach($draft_subjects as $dSubject)
                                            <th colspan="5" class="text-center" style="background: #495057; border-left: 2px solid #212529; padding: 3px;">
                                                <span class="badge badge-primary" style="font-size: 0.85em;">{{ $dSubject->code }}</span>
                                                <br><small class="text-warning" style="font-size: 0.7em;" title="{{ $dSubject->title }}">{{ Str::limit($dSubject->title, 18) }}</small>
                                                <br><small class="text-light">CV: {{ $dSubject->credit_hour }}</small>
                                            </th>
                                            @endforeach
                                            <th colspan="9" class="text-center" style="background: #17a2b8; border-left: 2px solid #212529;">
                                                <span class="text-white font-weight-bold">SUMMARY</span>
                                            </th>
                                        </tr>
                                        {{-- Sub-headers Row --}}
                                        <tr>
                                            @foreach($draft_subjects as $dSubject)
                                            <th class="text-center" style="background: #6c757d; border-left: 2px solid #212529; min-width: 30px;" title="Attendance">Att</th>
                                            <th class="text-center" style="background: #6c757d; min-width: 30px;" title="Continuous Assessment">CA</th>
                                            <th class="text-center" style="background: #6c757d; min-width: 30px;" title="Exam">EX</th>
                                            <th class="text-center" style="background: #6c757d; min-width: 35px;" title="Total">TOT</th>
                                            <th class="text-center" style="background: #6c757d; min-width: 30px;" title="Grade">Grd</th>
                                            @endforeach
                                            <th class="text-center" style="background: #138496; color: white; border-left: 2px solid #212529; min-width: 35px;" title="Total Credits Registered">TCR</th>
                                            <th class="text-center" style="background: #138496; color: white; min-width: 35px;" title="Total Credits Earned">TCE</th>
                                            <th class="text-center" style="background: #138496; color: white; min-width: 40px;" title="Grade Point Average">GPA</th>
                                            <th class="text-center" style="background: #138496; color: white; min-width: 35px;" title="Scheduled Resit Courses">R#</th>
                                            <th class="text-center" style="background: #138496; color: white; min-width: 40px;" title="Scheduled Resit Credits">RCR</th>
                                            <th class="text-center" style="background: #138496; color: white; min-width: 35px;" title="Carry Over Courses">CO#</th>
                                            <th class="text-center" style="background: #138496; color: white; min-width: 40px;" title="Carry Over Credits">COCR</th>
                                            <th class="text-center" style="background: #28a745; color: white; min-width: 30px;" title="Courses Passed">&#10003;</th>
                                            <th class="text-center" style="background: #dc3545; color: white; min-width: 30px;" title="Courses Failed">&#10007;</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($draft_student_results as $dStudent)
                                        @php
                                            $dHasFailure = $dStudent['summary']['courses_failed'] > 0;
                                            $dRowClass = $dHasFailure ? 'table-warning' : '';
                                        @endphp
                                        <tr class="{{ $dRowClass }}">
                                            <td class="text-center" style="position: sticky; left: 0; background: #fff; z-index: 1;">{{ $dStudent['sn'] }}</td>
                                            <td class="text-center" style="position: sticky; left: 35px; background: #fff; z-index: 1; font-size: 0.85em;">{{ $dStudent['matricule'] }}</td>
                                            <td style="position: sticky; left: 130px; background: #fff; z-index: 1; white-space: nowrap;">{{ Str::limit($dStudent['name'], 22) }}</td>
                                            @foreach($draft_subjects as $dSubject)
                                                @php
                                                    $dCourse = $dStudent['courses'][$dSubject->id] ?? null;
                                                    $dIsRegistered = $dCourse && $dCourse['registered'];
                                                    $dIsPassed = $dIsRegistered && ($dCourse['status'] ?? '') == 'P';
                                                    $dIsFailed = $dIsRegistered && ($dCourse['status'] ?? '') == 'F';
                                                    $dIsNotSubmitted = $dIsRegistered && ($dCourse['status'] ?? '') == 'N/S';
                                                    $dIsAbsent = $dIsRegistered && ($dCourse['status'] ?? '') == 'ABS';
                                                @endphp
                                                @if($dIsRegistered)
                                                    @php
                                                        $dHasZeroContribution = $dCourse['has_zero_contribution'] ?? false;
                                                        $dDecision = $dCourse['decision'] ?? null;
                                                    @endphp
                                                    @if($dIsNotSubmitted)
                                                        <td class="text-center text-warning" style="border-left: 2px solid #dee2e6; font-style: italic; font-size: 0.75em;">{{ $dCourse['attendance_marks'] }}</td>
                                                        <td class="text-center text-warning" style="font-style: italic; font-size: 0.75em;">{{ $dCourse['ca_marks'] }}</td>
                                                        <td class="text-center text-warning" style="font-style: italic; font-size: 0.75em;">{{ $dCourse['exam_marks'] }}</td>
                                                        <td class="text-center text-warning font-weight-bold" style="font-style: italic; font-size: 0.75em;">{{ $dCourse['total_marks'] }}</td>
                                                        <td class="text-center">
                                                            <span class="badge badge-warning" style="font-size: 0.7em;" title="Marks Not Submitted">N/S</span>
                                                            @if($dDecision)
                                                                <div class="mt-1"><span class="badge badge-{{ $dDecision['class'] }}" style="font-size: 0.62em;">{{ $dDecision['code'] }}</span></div>
                                                            @endif
                                                        </td>
                                                    @elseif($dIsAbsent)
                                                        <td class="text-center text-muted" style="border-left: 2px solid #dee2e6;">-</td>
                                                        <td class="text-center text-muted">-</td>
                                                        <td class="text-center text-muted">-</td>
                                                        <td class="text-center text-muted">-</td>
                                                        <td class="text-center">
                                                            <span class="badge badge-secondary" style="font-size: 0.7em;">ABS</span>
                                                            @if($dDecision)
                                                                <div class="mt-1"><span class="badge badge-{{ $dDecision['class'] }}" style="font-size: 0.62em;">{{ $dDecision['code'] }}</span></div>
                                                            @endif
                                                        </td>
                                                    @else
                                                        @php
                                                            $dHasPartialNS = ($dCourse['grade'] === 'N/S' || $dCourse['exam_marks'] === 'N/S' || $dCourse['ca_marks'] === 'N/S');
                                                            $dCaAbsent = $dCourse['ca_absent'] ?? false;
                                                            $dFinalAbsent = $dCourse['final_absent'] ?? false;
                                                        @endphp
                                                        <td class="text-center{{ $dCourse['attendance_marks'] === 'N/S' ? ' text-warning font-italic' : '' }}" style="border-left: 2px solid #dee2e6;">{{ $dCourse['attendance_marks'] }}</td>
                                                        <td class="text-center{{ $dCourse['ca_marks'] === 'N/S' ? ' text-warning font-italic' : '' }}{{ $dCourse['ca_marks'] === 'ABS' ? ' text-danger' : '' }}">
                                                            @if($dCourse['ca_marks'] === 'ABS')
                                                                <span class="badge badge-danger" style="font-size: 0.7em;" title="Absent for CA — 0 marks">ABS</span>
                                                            @else
                                                                {{ $dCourse['ca_marks'] }}
                                                            @endif
                                                            @if($dCourse['ca_marks'] !== 'ABS' && $dHasZeroContribution && $dCourse['ca_marks'] !== 'N/S')
                                                                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 0.6em;" title="Mark distribution weights not configured - showing raw marks"></i>
                                                            @endif
                                                        </td>
                                                        <td class="text-center{{ $dCourse['exam_marks'] === 'N/S' ? ' text-warning font-italic' : '' }}{{ $dCourse['exam_marks'] === 'ABS' ? ' text-danger' : '' }}">
                                                            @if($dCourse['exam_marks'] === 'ABS')
                                                                <span class="badge badge-danger" style="font-size: 0.7em;" title="Absent for Final Exam — 0 marks">ABS</span>
                                                            @else
                                                                {{ $dCourse['exam_marks'] }}
                                                            @endif
                                                        </td>
                                                        <td class="text-center font-weight-bold {{ $dIsFailed ? 'text-danger' : ($dIsPassed ? 'text-success' : '') }}">
                                                            {{ $dCourse['total_marks'] }}
                                                            @if($dHasZeroContribution && !$dHasPartialNS)
                                                                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 0.6em;" title="Mark distribution weights not configured - totals may be inaccurate"></i>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if($dCourse['grade'] === 'N/S')
                                                                <span class="badge badge-warning" style="font-size: 0.75em;" title="Marks incomplete - final grade pending">N/S</span>
                                                            @else
                                                                <span class="badge {{ $dIsPassed ? 'badge-success' : ($dIsFailed ? 'badge-danger' : 'badge-secondary') }}" style="font-size: 0.8em;"
                                                                    @if($dCaAbsent || $dFinalAbsent)
                                                                        title="Student was absent for {{ $dFinalAbsent ? 'Final Exam' : 'CA' }} — 0 marks awarded"
                                                                    @elseif($dHasZeroContribution)
                                                                        title="Warning: Mark distribution not set"
                                                                    @endif
                                                                >
                                                                    {{ $dCourse['grade'] }}
                                                                </span>
                                                                @if($dDecision)
                                                                    <div class="mt-1"><span class="badge badge-{{ $dDecision['class'] }}" style="font-size: 0.62em;">{{ $dDecision['code'] }}</span></div>
                                                                @endif
                                                            @endif
                                                        </td>
                                                    @endif
                                                @else
                                                    <td class="text-center text-muted" style="border-left: 2px solid #dee2e6;">-</td>
                                                    <td class="text-center text-muted">-</td>
                                                    <td class="text-center text-muted">-</td>
                                                    <td class="text-center text-muted">-</td>
                                                    <td class="text-center text-muted">-</td>
                                                @endif
                                            @endforeach
                                            {{-- Summary columns --}}
                                            <td class="text-center font-weight-bold" style="border-left: 2px solid #17a2b8; background: #e8f4f8;">{{ $dStudent['summary']['total_credits_registered'] }}</td>
                                            <td class="text-center font-weight-bold" style="background: #e8f4f8;">{{ $dStudent['summary']['total_credits_earned'] }}</td>
                                            <td class="text-center font-weight-bold" style="background: #e8f4f8;">
                                                <span class="badge {{ $dStudent['summary']['gpa'] >= 2.0 ? 'badge-success' : 'badge-warning' }}" style="font-size: 0.85em;">
                                                    {{ number_format($dStudent['summary']['gpa'], 2) }}
                                                </span>
                                            </td>
                                            <td class="text-center font-weight-bold" style="background: #e8f4f8;">{{ $dStudent['summary']['scheduled_resit_courses'] }}</td>
                                            <td class="text-center font-weight-bold" style="background: #e8f4f8;">{{ rtrim(rtrim(number_format($dStudent['summary']['scheduled_resit_credits'], 1), '0'), '.') }}</td>
                                            <td class="text-center font-weight-bold" style="background: #e8f4f8;">{{ $dStudent['summary']['carry_over_courses'] }}</td>
                                            <td class="text-center font-weight-bold" style="background: #e8f4f8;">{{ rtrim(rtrim(number_format($dStudent['summary']['carry_over_credits'], 1), '0'), '.') }}</td>
                                            <td class="text-center text-success font-weight-bold" style="background: #d4edda;">{{ $dStudent['summary']['courses_passed'] }}</td>
                                            <td class="text-center text-danger font-weight-bold" style="background: #f8d7da;">{{ $dStudent['summary']['courses_failed'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="thead-light">
                                        <tr>
                                            <th colspan="3" class="text-right" style="position: sticky; left: 0; background: #e9ecef; z-index: 1;">Course Summary:</th>
                                            @foreach($draft_subjects as $dSubject)
                                                @php
                                                    $dStats = $draft_course_stats[$dSubject->id] ?? null;
                                                @endphp
                                                <th colspan="5" class="text-center" style="font-size: 0.75em; border-left: 2px solid #dee2e6;">
                                                    @if($dStats)
                                                    <div>Reg: {{ $dStats['registered'] }} | Exam: {{ $dStats['examined'] }}</div>
                                                    <div class="text-success">P: {{ $dStats['passed'] }} ({{ $dStats['pass_rate'] }}%)</div>
                                                    <div class="text-danger">F: {{ $dStats['failed'] }}</div>
                                                    @endif
                                                </th>
                                            @endforeach
                                            <th colspan="9" class="text-center" style="border-left: 2px solid #17a2b8; background: #d1ecf1; font-size: 0.8em;">
                                                <div><strong>{{ $draft_overall_stats['total_students'] }}</strong> Students</div>
                                                <div class="text-success">Clear: {{ $draft_overall_stats['total_passed'] }}</div>
                                                <div class="text-danger">With Fails: {{ $draft_overall_stats['total_failed'] }}</div>
                                                @if($draft_overall_stats['total_pending'] > 0)
                                                <div class="text-muted">Pending: {{ $draft_overall_stats['total_pending'] }}</div>
                                                @endif
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            {{-- Legend --}}
                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small><strong>Column Key:</strong></small><br>
                                        <small>
                                            <strong>Att</strong> = Attendance |
                                            <strong>CA</strong> = Continuous Assessment |
                                            <strong>EX</strong> = Final Exam |
                                            <strong>TOT</strong> = Total |
                                            <strong>Grd</strong> = Grade
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small><strong>Summary Key:</strong></small><br>
                                        <small>
                                            <strong>TCR</strong> = Total Credits Registered |
                                            <strong>TCE</strong> = Total Credits Earned |
                                            <strong>GPA</strong> = Grade Point Average |
                                            <strong>R#</strong> = Scheduled Resit Courses |
                                            <strong>RCR</strong> = Scheduled Resit Credits |
                                            <strong>CO#</strong> = Carry Over Courses |
                                            <strong>COCR</strong> = Carry Over Credits
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small><strong>Status:</strong></small><br>
                                        <small>
                                            <span class="badge badge-success">A+</span> Pass (Total &ge; 50) |
                                            <span class="badge badge-danger">F</span> Fail (Total &lt; 50) |
                                            <span class="badge badge-warning text-dark">N/S</span> Marks Not Submitted |
                                            <span class="badge badge-secondary">ABS</span> Absent |
                                            <span class="text-muted">-</span> Not Registered |
                                            <span class="badge badge-success">VAL</span> Validated |
                                            <span class="badge badge-primary">RES</span> Scheduled Resit |
                                            <span class="badge badge-dark">CO</span> Carry Over |
                                            <span class="badge badge-warning text-dark">PND</span> Pending Resit Decision |
                                            <i class="fas fa-exclamation-triangle text-warning"></i> Mark Distribution Not Set
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @else
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-broadcast-tower fa-3x text-info mb-3"></i>
                        <h5>{{ __('Select filters to view Exam Publishing') }}</h5>
                        <p class="text-muted">Choose Faculty, Program, Session, Semester, and Exam Type to load courses for publishing.</p>
                    </div>
                </div>
            </div>
            @endif

        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Transition Modal -->
<div class="modal fade" id="transitionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="transitionForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> {{ __('Workflow Transition') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Transitioning <strong id="transitionSubjectCode"></strong> to <strong id="transitionTargetState"></strong></p>
                    
                    <input type="hidden" name="state" id="transitionStateInput">
                    
                    <div class="form-group">
                        <label for="transitionNotes">{{ __('Notes') }} ({{ __('optional') }})</label>
                        <textarea class="form-control" name="notes" id="transitionNotes" rows="2" placeholder="Add notes..."></textarea>
                    </div>
                    
                    <div class="form-group" id="publishDateGroup" style="display: none;">
                        <label for="transitionPublishDate">{{ __('Publish Date') }}</label>
                        <input type="date" class="form-control" name="publish_date" id="transitionPublishDate" value="{{ date('Y-m-d') }}">
                    </div>
                    
                    <div class="form-group" id="publishTimeGroup" style="display: none;">
                        <label for="transitionPublishTime">{{ __('Publish Time') }}</label>
                        <input type="time" class="form-control" name="publish_time" id="transitionPublishTime" value="{{ date('H:i') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('btn_cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> {{ __('Confirm Transition') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history"></i> {{ __('Workflow History') }} - <span id="historySubjectCode"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="historyLoading" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading history...</p>
                </div>
                <table class="table table-sm" id="historyTable" style="display: none;">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody"></tbody>
                </table>
                <div id="historyEmpty" class="text-center text-muted py-4" style="display: none;">
                    <i class="fas fa-info-circle"></i> No history available.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('btn_close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Transition Confirmation Modal -->
@if((isset($subjects) && count($subjects) > 0) || ($multi_mode && !empty($faculty_groups)))
<div class="modal fade" id="bulkTransitionConfirmModal" tabindex="-1" role="dialog" aria-labelledby="bulkTransitionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkTransitionConfirmModalLabel">
                    <i class="fas fa-tasks"></i> Confirm Bulk Workflow Transition
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Context Information -->
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0"><i class="fas fa-info-circle text-primary"></i> Publishing Context</h6>
                    </div>
                    <div class="card-body py-3">
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">Program</small>
                                <p class="mb-0 font-weight-bold">
                                    @if(isset($programs))
                                        @foreach($programs as $prog)
                                            @if($prog->id == $selected_program){{ $prog->title }}@endif
                                        @endforeach
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Session</small>
                                <p class="mb-0 font-weight-bold">
                                    @if(isset($sessions))
                                        @foreach($sessions as $sess)
                                            @if($sess->id == $selected_session){{ $sess->title }}@endif
                                        @endforeach
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Semester</small>
                                <p class="mb-0 font-weight-bold">
                                    @if(isset($semesters))
                                        @foreach($semesters as $sem)
                                            @if($sem->id == $selected_semester){{ $sem->title }}@endif
                                        @endforeach
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Exam Type</small>
                                <p class="mb-0">
                                    <span class="badge {{ $is_final_exam ? 'badge-danger' : 'badge-warning' }}">
                                        {{ $exam_type->title ?? 'N/A' }}
                                        {{ $is_final_exam ? '(Final)' : '(CA)' }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Actioned By</small>
                                <p class="mb-0 font-weight-bold">
                                    <i class="fas fa-user"></i> {{ Auth::user()->name ?? Auth::user()->email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transition Summary -->
                <div class="card mb-3 border-info">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0"><i class="fas fa-exchange-alt text-info"></i> Transition Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-0 text-primary" id="bulkSelectedCount">0</h3>
                                    <small class="text-muted">Courses Selected</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded" id="bulkCurrentStateBox" style="background: #e9ecef;">
                                    <h5 class="mb-0" id="bulkCurrentState">--</h5>
                                    <small class="text-muted">Current State</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded" id="bulkTargetStateBox" style="background: #d4edda;">
                                    <h5 class="mb-0 text-success" id="bulkTargetState">--</h5>
                                    <small class="text-muted">Target State</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Workflow Progress -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center" style="position: relative;">
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center workflow-step" id="stepDraft" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-pencil-alt"></i>
                                    </div>
                                    <small>Draft</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center workflow-step" id="stepSubmitted" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <small>Submitted</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center workflow-step" id="stepChecked" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <small>Checked</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center workflow-step" id="stepApproved" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-thumbs-up"></i>
                                    </div>
                                    <small>Approved</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center workflow-step" id="stepPublished" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <small>Published</small>
                                </div>
                                <!-- Progress line -->
                                <div style="position: absolute; top: 22px; left: 25px; right: 25px; height: 3px; background: #dee2e6;"></div>
                                <div id="workflowProgressLine" style="position: absolute; top: 22px; left: 25px; height: 3px; background: #28a745; width: 0%; transition: width 0.3s;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Courses Details -->
                <div class="card mb-3 border-secondary">
                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list text-secondary"></i> Selected Courses</h6>
                        <small class="text-muted" id="bulkCoursesListCount">0 course(s)</small>
                    </div>
                    <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light" style="position: sticky; top: 0;">
                                <tr>
                                    <th style="width: 100px;">Code</th>
                                    <th>Course Title</th>
                                    <th class="text-center" style="width: 80px;">Students</th>
                                    <th class="text-center" style="width: 80px;">With Marks</th>
                                    <th class="text-center" style="width: 100px;">Current State</th>
                                    @if($is_final_exam)
                                    <th class="text-center" style="width: 90px;">CA Status</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="bulkCoursesList">
                                <!-- Will be populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Impact Summary -->
                <div class="card mb-3 border-success">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0"><i class="fas fa-chart-pie text-success"></i> Impact Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="p-2 border rounded">
                                    <h4 class="mb-0 text-primary" id="bulkTotalStudents">0</h4>
                                    <small class="text-muted">Total Students Affected</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded">
                                    <h4 class="mb-0 text-info" id="bulkTotalMarks">0</h4>
                                    <small class="text-muted">Total Mark Records</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded border-success">
                                    <h4 class="mb-0 text-success" id="bulkTotalPass">0</h4>
                                    <small class="text-muted">Passing Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded border-danger">
                                    <h4 class="mb-0 text-danger" id="bulkTotalFail">0</h4>
                                    <small class="text-muted">Failing Students</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3" id="bulkPublishImpact" style="display: none;">
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-globe"></i> 
                                <strong>Publishing Impact:</strong> Once published, these marks will become visible to students based on the publish date and time set.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warnings Section -->
                <div id="bulkWarningsSection" style="display: none;">
                    <div class="alert alert-danger mb-3" id="bulkMixedStatesWarning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Mixed States Detected!</strong>
                        <p class="mb-0" id="bulkMixedStatesMessage">Selected courses are in different workflow states. Please select courses in the same state.</p>
                    </div>
                    
                    <div class="alert alert-warning mb-3" id="bulkCANotPublishedWarning" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>CA Not Published Warning!</strong>
                        <p class="mb-0" id="bulkCANotPublishedMessage">Some selected courses have unpublished CA results. Publishing Final Exam marks for these courses may not be allowed.</p>
                        <ul id="bulkCANotPublishedList" class="mb-0 mt-2" style="font-size: 0.9em;"></ul>
                    </div>
                    
                    <div class="alert alert-info mb-3" id="bulkNoMarksWarning" style="display: none;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong>
                        <span id="bulkNoMarksMessage">Some courses may have students without marks.</span>
                    </div>
                </div>

                <!-- Publish Date/Time (only for published state) -->
                <div id="bulkPublishDateTimeSection" style="display: none;">
                    <div class="card mb-3 border-warning">
                        <div class="card-header bg-warning text-dark py-2">
                            <h6 class="mb-0"><i class="fas fa-calendar-alt"></i> Publish Schedule</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="modalBulkPublishDate">{{ __('Publish Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="modalBulkPublishDate" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="modalBulkPublishTime">{{ __('Publish Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="modalBulkPublishTime" value="{{ date('H:i') }}">
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Results will be visible to students after this date and time.
                                    <br>
                                    <strong id="publishPreviewText">Scheduled: {{ date('l, F j, Y') }} at {{ date('g:i A') }}</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Final Confirmation -->
                <div class="alert alert-warning mb-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x mr-3"></i>
                        <div>
                            <strong>Please Review Before Proceeding!</strong>
                            <p class="mb-0 small">This action will transition all selected courses to the target workflow state. Please ensure all information is correct.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" id="btnConfirmBulkTransition" disabled>
                    <i class="fas fa-check"></i> Confirm Transition
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Multi-Mode Bulk Transition Confirmation Modal -->
<div class="modal fade" id="multiBulkTransitionConfirmModal" tabindex="-1" role="dialog" aria-labelledby="multiBulkTransitionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="multiBulkTransitionConfirmModalLabel">
                    <i class="fas fa-tasks"></i> Confirm Multi-Program Bulk Workflow Transition
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Context Information -->
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0"><i class="fas fa-info-circle text-primary"></i> Publishing Context</h6>
                    </div>
                    <div class="card-body py-3">
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">Programs</small>
                                <p class="mb-0 font-weight-bold" id="multiModalProgramInfo">--</p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Session</small>
                                <p class="mb-0 font-weight-bold">
                                    @if(isset($sessions))
                                        @foreach($sessions as $sess)
                                            @if($sess->id == $selected_session){{ $sess->title }}@endif
                                        @endforeach
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Semester</small>
                                <p class="mb-0 font-weight-bold">
                                    @if(isset($semesters))
                                        @foreach($semesters as $sem)
                                            @if($sem->id == $selected_semester){{ $sem->title }}@endif
                                        @endforeach
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Exam Type</small>
                                <p class="mb-0">
                                    <span class="badge {{ $is_final_exam ? 'badge-danger' : 'badge-warning' }}">
                                        {{ $exam_type->title ?? 'N/A' }}
                                        {{ $is_final_exam ? '(Final)' : '(CA)' }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Actioned By</small>
                                <p class="mb-0 font-weight-bold">
                                    <i class="fas fa-user"></i> {{ Auth::user()->name ?? Auth::user()->email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transition Summary -->
                <div class="card mb-3 border-info">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0"><i class="fas fa-exchange-alt text-info"></i> Transition Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-0 text-primary" id="multiModalSelectedCount">0</h3>
                                    <small class="text-muted">Courses Selected</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-0 text-secondary" id="multiModalProgramCount">0</h3>
                                    <small class="text-muted">Programs Affected</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 rounded" id="multiModalCurrentStateBox" style="background: #e9ecef;">
                                    <h5 class="mb-0" id="multiModalCurrentState">--</h5>
                                    <small class="text-muted">Current State</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 rounded" id="multiModalTargetStateBox" style="background: #d4edda;">
                                    <h5 class="mb-0 text-success" id="multiModalTargetState">--</h5>
                                    <small class="text-muted">Target State</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Workflow Progress -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center" style="position: relative;">
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center multi-workflow-step" id="multiStepDraft" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-pencil-alt"></i>
                                    </div>
                                    <small>Draft</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center multi-workflow-step" id="multiStepSubmitted" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <small>Submitted</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center multi-workflow-step" id="multiStepChecked" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <small>Checked</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center multi-workflow-step" id="multiStepApproved" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-thumbs-up"></i>
                                    </div>
                                    <small>Approved</small>
                                </div>
                                <div class="text-center" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center multi-workflow-step" id="multiStepPublished" style="width: 45px; height: 45px; background: #6c757d; color: white; margin: 0 auto;">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <small>Published</small>
                                </div>
                                <!-- Progress line -->
                                <div style="position: absolute; top: 22px; left: 25px; right: 25px; height: 3px; background: #dee2e6;"></div>
                                <div id="multiWorkflowProgressLine" style="position: absolute; top: 22px; left: 25px; height: 3px; background: #28a745; width: 0%; transition: width 0.3s;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Courses Grouped by Program -->
                <div class="card mb-3 border-secondary">
                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list text-secondary"></i> Selected Courses by Program</h6>
                        <small class="text-muted" id="multiModalCoursesListCount">0 course(s) across 0 program(s)</small>
                    </div>
                    <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                        <div id="multiModalCoursesList">
                            <!-- Populated dynamically: program headings + course tables -->
                        </div>
                    </div>
                </div>

                <!-- Impact Summary -->
                <div class="card mb-3 border-success">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0"><i class="fas fa-chart-pie text-success"></i> Impact Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="p-2 border rounded">
                                    <h4 class="mb-0 text-primary" id="multiModalTotalStudents">0</h4>
                                    <small class="text-muted">Total Students Affected</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded">
                                    <h4 class="mb-0 text-info" id="multiModalTotalMarks">0</h4>
                                    <small class="text-muted">Total Mark Records</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded border-success">
                                    <h4 class="mb-0 text-success" id="multiModalTotalPass">0</h4>
                                    <small class="text-muted">Passing Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 border rounded border-danger">
                                    <h4 class="mb-0 text-danger" id="multiModalTotalFail">0</h4>
                                    <small class="text-muted">Failing Students</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Per-Program Breakdown -->
                        <div class="mt-3" id="multiModalProgramBreakdown">
                            <h6 class="text-muted mb-2"><i class="fas fa-sitemap"></i> Per-Program Breakdown</h6>
                            <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light" style="position: sticky; top: 0;">
                                        <tr>
                                            <th>Program</th>
                                            <th class="text-center" style="width: 80px;">Courses</th>
                                            <th class="text-center" style="width: 80px;">Students</th>
                                            <th class="text-center" style="width: 80px;">Marks</th>
                                            <th class="text-center" style="width: 70px;">Pass</th>
                                            <th class="text-center" style="width: 70px;">Fail</th>
                                        </tr>
                                    </thead>
                                    <tbody id="multiModalProgramBreakdownBody"></tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-3" id="multiModalPublishImpact" style="display: none;">
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-globe"></i> 
                                <strong>Publishing Impact:</strong> Once published, these marks will become visible to students across <strong id="multiModalPublishProgramCount">0</strong> program(s) based on the publish date and time set.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warnings Section -->
                <div id="multiModalWarningsSection" style="display: none;">
                    <div class="alert alert-danger mb-3" id="multiModalMixedStatesWarning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Mixed States Detected!</strong>
                        <p class="mb-0" id="multiModalMixedStatesMessage">Selected courses are in different workflow states. Please select courses in the same state.</p>
                    </div>
                    
                    <div class="alert alert-warning mb-3" id="multiModalCANotPublishedWarning" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>CA Not Published Warning!</strong>
                        <p class="mb-0" id="multiModalCANotPublishedMessage">Some selected courses have unpublished CA results. Publishing Final Exam marks for these courses may not be allowed.</p>
                        <ul id="multiModalCANotPublishedList" class="mb-0 mt-2" style="font-size: 0.9em;"></ul>
                    </div>
                    
                    <div class="alert alert-info mb-3" id="multiModalNoMarksWarning" style="display: none;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong>
                        <span id="multiModalNoMarksMessage">Some courses may have students without marks.</span>
                    </div>

                    <div class="alert alert-warning mb-3" id="multiModalCrossProgramWarning" style="display: none;">
                        <i class="fas fa-project-diagram"></i>
                        <strong>Cross-Program Operation!</strong>
                        <p class="mb-0" id="multiModalCrossProgramMessage">You are transitioning courses across multiple programs simultaneously. Please ensure all selected courses are ready for this transition.</p>
                    </div>
                </div>

                <!-- Publish Date/Time (only for published state) -->
                <div id="multiModalPublishDateTimeSection" style="display: none;">
                    <div class="card mb-3 border-warning">
                        <div class="card-header bg-warning text-dark py-2">
                            <h6 class="mb-0"><i class="fas fa-calendar-alt"></i> Publish Schedule</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="multiModalPublishDate">{{ __('Publish Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="multiModalPublishDate" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="multiModalPublishTime">{{ __('Publish Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="multiModalPublishTime" value="{{ date('H:i') }}">
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Results will be visible to students across all affected programs after this date and time.
                                    <br>
                                    <strong id="multiPublishPreviewText">Scheduled: {{ date('l, F j, Y') }} at {{ date('g:i A') }}</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Final Confirmation -->
                <div class="alert alert-warning mb-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x mr-3"></i>
                        <div>
                            <strong>Please Review Before Proceeding!</strong>
                            <p class="mb-0 small">This action will transition all selected courses across multiple programs to the target workflow state. Please ensure all information is correct before confirming.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" id="btnConfirmMultiBulkTransition" disabled>
                    <i class="fas fa-check"></i> Confirm Multi-Program Transition
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('page_js')
<script>
    "use strict";

    // AJAX filter cascading
    $(".faculty").on('change', function(e) {
        e.preventDefault();
        var faculty = $(this).val();
        var program = $(".program");
        
        // Clear dependent dropdowns
        program.empty().append('<option value="">{{ __("select") }}</option>');
        $(".session").empty().append('<option value="">{{ __("select") }}</option>');
        $(".semester").empty().append('<option value="">{{ __("select") }}</option>');
        $(".section").empty().append('<option value="0">{{ __("all") }}</option>');

        if (!faculty) return;

        $.ajax({
            type: 'POST',
            url: "{{ route('filter-program') }}",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                faculty: faculty
            },
            success: function(response) {
                $.each(response, function() {
                    $('<option/>', {
                        'value': this.id,
                        'text': this.title
                    }).appendTo(program);
                });
            }
        });
    });

    $(".program").on('change', function(e) {
        e.preventDefault();
        var program = $(this).val();
        var session = $(".session");
        var semester = $(".semester");
        
        // Clear dependent dropdowns
        session.empty().append('<option value="">{{ __("select") }}</option>');
        semester.empty().append('<option value="">{{ __("select") }}</option>');
        $(".section").empty().append('<option value="0">{{ __("all") }}</option>');

        if (!program) return;

        // Load sessions
        $.ajax({
            type: 'POST',
            url: "{{ route('filter-session') }}",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                program: program
            },
            success: function(response) {
                $.each(response, function() {
                    $('<option/>', {
                        'value': this.id,
                        'text': this.title
                    }).appendTo(session);
                });
            }
        });

        // Load semesters
        $.ajax({
            type: 'POST',
            url: "{{ route('filter-semester') }}",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                program: program
            },
            success: function(response) {
                $.each(response, function() {
                    $('<option/>', {
                        'value': this.id,
                        'text': this.title
                    }).appendTo(semester);
                });
            }
        });
    });

    $(".semester").on('change', function(e) {
        e.preventDefault();
        var program = $(".program").val();
        var semester = $(this).val();
        var section = $(".section");
        
        section.empty().append('<option value="0">{{ __("all") }}</option>');

        if (!program || !semester) return;

        $.ajax({
            type: 'POST',
            url: "{{ route('filter-section') }}",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                program: program,
                semester: semester
            },
            success: function(response) {
                $.each(response, function() {
                    $('<option/>', {
                        'value': this.id,
                        'text': this.title
                    }).appendTo(section);
                });
            }
        });
    });

    // Course selection management
    function updateSelectedCount() {
        var selected = $('.course-checkbox:checked').length;
        $('#selectedCount strong').text(selected);
        $('#bulkTransitionBtn').prop('disabled', selected === 0 || !$('#bulkState').val());

        // Update hidden inputs
        $('#selectedSubjectsContainer').empty();
        $('.course-checkbox:checked').each(function() {
            $('#selectedSubjectsContainer').append(
                '<input type="hidden" name="subject_ids[]" value="' + $(this).val() + '">'
            );
        });

        // Check if all selected are same state
        var states = [];
        $('.course-checkbox:checked').each(function() {
            states.push($(this).data('state'));
        });
        var uniqueStates = [...new Set(states)];
        
        if (uniqueStates.length > 1) {
            $('#selectedCount').removeClass('alert-secondary alert-success').addClass('alert-warning');
            $('#selectedCount').html('<strong>' + selected + '</strong> course(s) selected <span class="text-danger">(Mixed states!)</span>');
        } else {
            $('#selectedCount').removeClass('alert-warning').addClass('alert-secondary');
            $('#selectedCount').html('<strong>' + selected + '</strong> course(s) selected');
        }
    }

    $('.course-checkbox').on('change', updateSelectedCount);
    $('#bulkState').on('change', function() {
        updateSelectedCount();
        // Show/hide publish date/time fields based on selected state
        if ($(this).val() === 'published') {
            $('.publish-datetime-field').show();
        } else {
            $('.publish-datetime-field').hide();
        }
    });

    $('#checkAll').on('change', function() {
        $('.course-checkbox:not(:disabled)').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });

    $('#selectAllBtn').on('click', function() {
        $('.course-checkbox:not(:disabled)').prop('checked', true);
        $('#checkAll').prop('checked', true);
        updateSelectedCount();
    });

    $('#deselectAllBtn').on('click', function() {
        $('.course-checkbox').prop('checked', false);
        $('#checkAll').prop('checked', false);
        updateSelectedCount();
    });

    $('#selectSameStateBtn').on('click', function() {
        // Find first checked state or prompt
        var firstChecked = $('.course-checkbox:checked').first();
        var targetState = firstChecked.length ? firstChecked.data('state') : null;

        if (!targetState) {
            // Prompt user to select state
            var states = ['draft', 'submitted', 'checked', 'approved'];
            var stateChoice = prompt('Enter state to select (draft, submitted, checked, approved):');
            if (stateChoice && states.includes(stateChoice.toLowerCase())) {
                targetState = stateChoice.toLowerCase();
            } else {
                return;
            }
        }

        $('.course-checkbox').prop('checked', false);
        $('.course-checkbox[data-state="' + targetState + '"]:not(:disabled)').prop('checked', true);
        updateSelectedCount();
    });

    // Individual transition buttons
    $('.transition-btn').on('click', function() {
        var stateId = $(this).data('state-id');
        var targetState = $(this).data('target-state');
        var subjectCode = $(this).data('subject-code');

        $('#transitionForm').attr('action', '{{ url("admin/exam/exam-publishing") }}/' + stateId + '/transition');
        $('#transitionSubjectCode').text(subjectCode);
        $('#transitionTargetState').text(targetState.charAt(0).toUpperCase() + targetState.slice(1));
        $('#transitionStateInput').val(targetState);

        // Show/hide publish date fields
        if (targetState === 'published') {
            $('#publishDateGroup, #publishTimeGroup').show();
        } else {
            $('#publishDateGroup, #publishTimeGroup').hide();
        }

        $('#transitionModal').modal('show');
    });

    // History button
    $('.history-btn').on('click', function() {
        var stateId = $(this).data('state-id');
        var subjectCode = $(this).data('subject-code');

        $('#historySubjectCode').text(subjectCode);
        $('#historyLoading').show();
        $('#historyTable, #historyEmpty').hide();
        $('#historyTableBody').empty();

        $('#historyModal').modal('show');

        $.ajax({
            url: '{{ url("admin/exam/exam-publishing") }}/' + stateId + '/history',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#historyLoading').hide();
                
                if (data.length === 0) {
                    $('#historyEmpty').show();
                } else {
                    $.each(data, function(index, log) {
                        $('#historyTableBody').append(
                            '<tr>' +
                            '<td>' + log.date + '</td>' +
                            '<td>' + log.user + '</td>' +
                            '<td>' + log.action + '</td>' +
                            '<td>' + (log.note || '-') + '</td>' +
                            '</tr>'
                        );
                    });
                    $('#historyTable').show();
                }
            },
            error: function() {
                $('#historyLoading').hide();
                $('#historyEmpty').show().html('<i class="fas fa-exclamation-triangle text-danger"></i> Failed to load history.');
            }
        });
    });

    // Form validation - Show confirmation modal instead of direct submit
    $('#bulkTransitionForm').on('submit', function(e) {
        e.preventDefault();
        
        var selected = $('.course-checkbox:checked').length;
        if (selected === 0) {
            alert('Please select at least one course.');
            return false;
        }

        var targetState = $('#bulkState').val();
        if (!targetState) {
            alert('Please select a target state.');
            return false;
        }

        // Check same state
        var states = [];
        var courseData = [];
        var totalStudents = 0;
        var totalMarks = 0;
        var totalPass = 0;
        var totalFail = 0;
        var caNotPublishedCourses = [];
        var isFinalExam = {{ $is_final_exam ? 'true' : 'false' }};
        
        $('.course-checkbox:checked').each(function() {
            var $row = $(this).closest('tr');
            var state = $(this).data('state');
            states.push(state);
            
            var subjectId = $(this).val();
            var code = $row.find('td:eq(1) .badge').text().trim();
            var title = $row.find('td:eq(2)').text().trim();
            var students = parseInt($row.find('td:eq(4)').text().trim()) || 0;
            var withMarks = parseInt($row.find('td:eq(5) .badge').text().trim()) || 0;
            var pass = parseInt($row.find('td:eq(6)').text().trim()) || 0;
            var fail = parseInt($row.find('td:eq(7)').text().trim()) || 0;
            
            // Check CA status for final exam
            var caPublished = true;
            if (isFinalExam) {
                var caCell = $row.find('td:eq(9)');
                caPublished = caCell.find('.badge-success').length > 0;
                if (!caPublished && targetState === 'published') {
                    caNotPublishedCourses.push(code + ' - ' + title);
                }
            }
            
            courseData.push({
                id: subjectId,
                code: code,
                title: title,
                students: students,
                withMarks: withMarks,
                pass: pass,
                fail: fail,
                state: state,
                caPublished: caPublished
            });
            
            totalStudents += students;
            totalMarks += withMarks;
            totalPass += pass;
            totalFail += fail;
        });

        var uniqueStates = [...new Set(states)];
        var hasMixedStates = uniqueStates.length > 1;

        // Update modal content
        $('#bulkSelectedCount').text(selected);
        $('#bulkCoursesListCount').text(selected + ' course(s)');
        
        // Current state
        var currentStateDisplay = hasMixedStates ? 'Mixed (' + uniqueStates.join(', ') + ')' : uniqueStates[0].charAt(0).toUpperCase() + uniqueStates[0].slice(1);
        $('#bulkCurrentState').text(currentStateDisplay);
        
        // Target state
        var targetStateDisplay = targetState.charAt(0).toUpperCase() + targetState.slice(1);
        $('#bulkTargetState').text(targetStateDisplay);
        
        // Update workflow progress visualization
        updateWorkflowProgress(hasMixedStates ? null : uniqueStates[0], targetState);
        
        // Populate courses list
        var coursesHtml = '';
        courseData.forEach(function(course) {
            var stateClass = {
                'draft': 'badge-secondary',
                'submitted': 'badge-info',
                'checked': 'badge-primary',
                'approved': 'badge-warning',
                'published': 'badge-success'
            }[course.state] || 'badge-secondary';
            
            coursesHtml += '<tr>' +
                '<td><span class="badge badge-dark">' + course.code + '</span></td>' +
                '<td>' + course.title + '</td>' +
                '<td class="text-center">' + course.students + '</td>' +
                '<td class="text-center"><span class="badge badge-info">' + course.withMarks + '</span></td>' +
                '<td class="text-center"><span class="badge ' + stateClass + '">' + course.state.charAt(0).toUpperCase() + course.state.slice(1) + '</span></td>';
            
            if (isFinalExam) {
                coursesHtml += '<td class="text-center">' +
                    (course.caPublished 
                        ? '<span class="badge badge-success"><i class="fas fa-check"></i></span>' 
                        : '<span class="badge badge-danger"><i class="fas fa-times"></i></span>') +
                    '</td>';
            }
            
            coursesHtml += '</tr>';
        });
        $('#bulkCoursesList').html(coursesHtml);
        
        // Update impact summary
        $('#bulkTotalStudents').text(totalStudents);
        $('#bulkTotalMarks').text(totalMarks);
        $('#bulkTotalPass').text(totalPass);
        $('#bulkTotalFail').text(totalFail);
        
        // Show/hide publish impact
        if (targetState === 'published') {
            $('#bulkPublishImpact').show();
            $('#bulkPublishDateTimeSection').show();
            
            // Copy date/time from the form fields to the modal fields
            var formDate = $('#bulkPublishDate').val();
            var formTime = $('#bulkPublishTime').val();
            
            if (formDate) {
                $('#modalBulkPublishDate').val(formDate);
            }
            if (formTime) {
                $('#modalBulkPublishTime').val(formTime);
            }
            
            // Update the preview text
            var dateVal = $('#modalBulkPublishDate').val();
            var timeVal = $('#modalBulkPublishTime').val();
            if (dateVal && timeVal) {
                var dateObj = new Date(dateVal + 'T' + timeVal);
                var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                var timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
                $('#publishPreviewText').html('<i class="fas fa-clock"></i> Scheduled: ' + dateObj.toLocaleDateString('en-US', options) + ' at ' + dateObj.toLocaleTimeString('en-US', timeOptions));
            }
        } else {
            $('#bulkPublishImpact').hide();
            $('#bulkPublishDateTimeSection').hide();
        }
        
        // Handle warnings
        $('#bulkWarningsSection').hide();
        $('#bulkMixedStatesWarning').hide();
        $('#bulkCANotPublishedWarning').hide();
        $('#bulkNoMarksWarning').hide();
        
        var hasBlockingError = false;
        
        if (hasMixedStates) {
            $('#bulkWarningsSection').show();
            $('#bulkMixedStatesWarning').show();
            $('#bulkMixedStatesMessage').text('Selected courses are in different workflow states (' + uniqueStates.join(', ') + '). Please select courses in the same state.');
            hasBlockingError = true;
        }
        
        if (isFinalExam && caNotPublishedCourses.length > 0 && targetState === 'published') {
            $('#bulkWarningsSection').show();
            $('#bulkCANotPublishedWarning').show();
            $('#bulkCANotPublishedMessage').text(caNotPublishedCourses.length + ' course(s) have unpublished CA results:');
            var listHtml = '';
            caNotPublishedCourses.forEach(function(course) {
                listHtml += '<li class="text-danger">' + course + '</li>';
            });
            $('#bulkCANotPublishedList').html(listHtml);
            hasBlockingError = true;
        }
        
        // Check if some courses have fewer marks than students
        var lowMarksCount = courseData.filter(function(c) { return c.withMarks < c.students && c.students > 0; }).length;
        if (lowMarksCount > 0) {
            $('#bulkWarningsSection').show();
            $('#bulkNoMarksWarning').show();
            $('#bulkNoMarksMessage').text(lowMarksCount + ' course(s) have students without marks entered.');
        }
        
        // Enable/disable confirm button
        $('#btnConfirmBulkTransition').prop('disabled', hasBlockingError);
        
        // Show the modal
        $('#bulkTransitionConfirmModal').modal('show');
        
        return false;
    });
    
    // Update workflow progress visualization
    function updateWorkflowProgress(currentState, targetState) {
        var stateOrder = ['draft', 'submitted', 'checked', 'approved', 'published'];
        var currentIndex = currentState ? stateOrder.indexOf(currentState) : -1;
        var targetIndex = stateOrder.indexOf(targetState);
        
        // Reset all steps
        $('.workflow-step').css('background', '#6c757d');
        
        // Highlight current and completed steps
        if (currentIndex >= 0) {
            for (var i = 0; i <= currentIndex; i++) {
                $('#step' + stateOrder[i].charAt(0).toUpperCase() + stateOrder[i].slice(1)).css('background', '#17a2b8');
            }
        }
        
        // Highlight target step
        $('#step' + targetState.charAt(0).toUpperCase() + targetState.slice(1)).css('background', '#28a745');
        
        // Progress line
        var progressPercent = (targetIndex / (stateOrder.length - 1)) * 100;
        $('#workflowProgressLine').css('width', progressPercent + '%');
        
        // Update state boxes colors
        var stateColors = {
            'draft': '#6c757d',
            'submitted': '#17a2b8',
            'checked': '#007bff',
            'approved': '#ffc107',
            'published': '#28a745'
        };
        
        if (currentState) {
            $('#bulkCurrentStateBox').css('background', stateColors[currentState] + '33');
            $('#bulkCurrentState').css('color', stateColors[currentState]);
        }
        
        $('#bulkTargetStateBox').css('background', stateColors[targetState] + '33');
        $('#bulkTargetState').css('color', stateColors[targetState]);
    }
    
    // Handle publish date/time preview
    $('#modalBulkPublishDate, #modalBulkPublishTime').on('change', function() {
        var date = $('#modalBulkPublishDate').val();
        var time = $('#modalBulkPublishTime').val();
        
        if (date && time) {
            var dateObj = new Date(date + 'T' + time);
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            var timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
            $('#publishPreviewText').html('<i class="fas fa-clock"></i> Scheduled: ' + dateObj.toLocaleDateString('en-US', options) + ' at ' + dateObj.toLocaleTimeString('en-US', timeOptions));
        }
    });
    
    // Confirm bulk transition
    $('#btnConfirmBulkTransition').on('click', function() {
        // Copy date/time values from modal to form if published
        if ($('#bulkState').val() === 'published') {
            $('#bulkPublishDate').val($('#modalBulkPublishDate').val());
            $('#bulkPublishTime').val($('#modalBulkPublishTime').val());
        }
        
        // Close modal
        $('#bulkTransitionConfirmModal').modal('hide');
        
        // Submit form directly (bypass the submit handler)
        setTimeout(function() {
            document.getElementById('bulkTransitionForm').submit();
        }, 300);
    });

    // Print Draft Results Preview
    function printDraftResults() {
        var printContents = document.getElementById('draftResultsTable');
        if (!printContents) return;
        
        var printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Students Results Preview (DRAFT)</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-size: 10px; } table { font-size: 9px; } .badge { font-size: 0.8em; padding: 2px 4px; }');
        printWindow.document.write('.text-success { color: #28a745 !important; } .text-danger { color: #dc3545 !important; }');
        printWindow.document.write('.font-weight-bold { font-weight: bold !important; }');
        printWindow.document.write('.table-warning { background-color: #fff3cd !important; }');
        printWindow.document.write('.badge-success { background-color: #28a745; color: #fff; } .badge-danger { background-color: #dc3545; color: #fff; }');
        printWindow.document.write('.badge-warning { background-color: #ffc107; color: #212529; } .badge-secondary { background-color: #6c757d; color: #fff; }');
        printWindow.document.write('.badge-primary { background-color: #007bff; color: #fff; }');
        printWindow.document.write('.draft-watermark { text-align: center; color: #ffc107; font-size: 14px; font-weight: bold; margin: 10px 0; }');
        printWindow.document.write('@page { size: landscape; margin: 5mm; }');
        printWindow.document.write('</' + 'style></' + 'head><body>');
        printWindow.document.write('<div class="draft-watermark">&#9888; DRAFT PREVIEW &mdash; Students Results Preview (Not Yet Published)</div>');
        printWindow.document.write(printContents.innerHTML);
        printWindow.document.write('</' + 'body></' + 'html>');
        printWindow.document.close();
        
        printWindow.onload = function() {
            printWindow.print();
        };
    }

    // ==========================================
    // MULTI-PROGRAM MODE TOGGLE
    // ==========================================
    $('#multiToggle').on('change', function() {
        var isMulti = $(this).is(':checked');
        $('#multiInput').val(isMulti ? '1' : '0');
        
        // Update toggle visual
        var $track = $(this).closest('label').find('.custom-switch-track');
        var $thumb = $track.find('.custom-switch-thumb');
        if (isMulti) {
            $track.css('background', '#28a745');
            $thumb.css('left', '22px');
        } else {
            $track.css('background', '#ced4da');
            $thumb.css('left', '2px');
        }

        // In multi mode, relax required constraints on faculty/program/section
        if (isMulti) {
            $('#faculty').removeAttr('required');
            $('#program').removeAttr('required');
        } else {
            $('#faculty').attr('required', 'required');
            $('#program').attr('required', 'required');
        }
    });

    // Make the toggle label clickable
    $(document).on('click', '.custom-switch-track', function(e) {
        e.preventDefault();
        var $checkbox = $(this).closest('label').find('#multiToggle');
        $checkbox.prop('checked', !$checkbox.is(':checked')).trigger('change');
    });

    @if($multi_mode && !empty($faculty_groups))
    // ==========================================
    // MULTI-PROGRAM MODE JS
    // ==========================================

    // Multi-mode selection management
    function updateMultiSelectedCount() {
        var selected = $('.multi-course-checkbox:checked').length;
        var programIds = [];
        $('.multi-course-checkbox:checked').each(function() {
            var pid = $(this).data('program-id');
            if (programIds.indexOf(pid) === -1) programIds.push(pid);
        });

        $('#multiSelectedCount').html(
            '<strong>' + selected + '</strong> course(s) selected across <strong>' + programIds.length + '</strong> program(s)'
        );
        $('#multiBulkTransitionBtn').prop('disabled', selected === 0 || !$('#multiBulkState').val());

        // Update hidden state_ids
        $('#multiSelectedStatesContainer').empty();
        $('.multi-course-checkbox:checked').each(function() {
            $('#multiSelectedStatesContainer').append(
                '<input type="hidden" name="state_ids[]" value="' + $(this).val() + '">'
            );
        });

        // Check if all selected are same state
        var states = [];
        $('.multi-course-checkbox:checked').each(function() {
            states.push($(this).data('state'));
        });
        var uniqueStates = [...new Set(states)];

        if (uniqueStates.length > 1) {
            $('#multiSelectedCount').removeClass('alert-secondary alert-success').addClass('alert-warning');
            $('#multiSelectedCount').append(' <span class="text-danger">(Mixed states!)</span>');
        } else if (selected > 0) {
            $('#multiSelectedCount').removeClass('alert-warning').addClass('alert-secondary');
        } else {
            $('#multiSelectedCount').removeClass('alert-warning').addClass('alert-secondary');
        }
    }

    // Course checkbox changes
    $(document).on('change', '.multi-course-checkbox', function() {
        updateMultiSelectedCount();
        // Update program-level "check all" checkbox
        var programId = $(this).data('program-id');
        var facultyId = $(this).data('faculty-id');
        var $programCheckAll = $('.program-check-all[data-program="' + programId + '"][data-faculty="' + facultyId + '"]');
        var totalInProgram = $('.multi-course-checkbox[data-program-id="' + programId + '"][data-faculty-id="' + facultyId + '"]:not(:disabled)').length;
        var checkedInProgram = $('.multi-course-checkbox[data-program-id="' + programId + '"][data-faculty-id="' + facultyId + '"]:checked').length;
        $programCheckAll.prop('checked', totalInProgram > 0 && totalInProgram === checkedInProgram);
        $programCheckAll.prop('indeterminate', checkedInProgram > 0 && checkedInProgram < totalInProgram);
    });

    // Program-level "check all"
    $(document).on('change', '.program-check-all', function() {
        var programId = $(this).data('program');
        var facultyId = $(this).data('faculty');
        var isChecked = $(this).is(':checked');
        $('.multi-course-checkbox[data-program-id="' + programId + '"][data-faculty-id="' + facultyId + '"]:not(:disabled)').prop('checked', isChecked);
        updateMultiSelectedCount();
    });

    // State selector change
    $('#multiBulkState').on('change', function() {
        updateMultiSelectedCount();
        if ($(this).val() === 'published') {
            $('.multi-publish-field').show();
        } else {
            $('.multi-publish-field').hide();
        }
    });

    // Global Select All
    $('#multiSelectAllBtn').on('click', function() {
        $('.multi-course-checkbox:not(:disabled)').prop('checked', true);
        $('.program-check-all').prop('checked', true).prop('indeterminate', false);
        updateMultiSelectedCount();
    });

    // Global Deselect All
    $('#multiDeselectAllBtn').on('click', function() {
        $('.multi-course-checkbox').prop('checked', false);
        $('.program-check-all').prop('checked', false).prop('indeterminate', false);
        updateMultiSelectedCount();
    });

    // Select Same State
    $('#multiSelectSameStateBtn').on('click', function() {
        var firstChecked = $('.multi-course-checkbox:checked').first();
        var targetState = firstChecked.length ? firstChecked.data('state') : null;

        if (!targetState) {
            var stateChoice = prompt('Enter state to select (draft, submitted, checked, approved):');
            if (stateChoice && ['draft', 'submitted', 'checked', 'approved'].includes(stateChoice.toLowerCase())) {
                targetState = stateChoice.toLowerCase();
            } else {
                return;
            }
        }

        $('.multi-course-checkbox').prop('checked', false);
        $('.multi-course-checkbox[data-state="' + targetState + '"]:not(:disabled)').prop('checked', true);
        // Update program check-alls
        $('.program-check-all').each(function() {
            var pid = $(this).data('program');
            var fid = $(this).data('faculty');
            var total = $('.multi-course-checkbox[data-program-id="' + pid + '"][data-faculty-id="' + fid + '"]:not(:disabled)').length;
            var checked = $('.multi-course-checkbox[data-program-id="' + pid + '"][data-faculty-id="' + fid + '"]:checked').length;
            $(this).prop('checked', total > 0 && total === checked);
            $(this).prop('indeterminate', checked > 0 && checked < total);
        });
        updateMultiSelectedCount();
    });

    // Expand All Accordions
    $('#multiExpandAllBtn').on('click', function() {
        $('#facultyAccordion .collapse').collapse('show');
        setTimeout(function() {
            $('#facultyAccordion .collapse .collapse').collapse('show');
        }, 300);
    });

    // Collapse All Accordions
    $('#multiCollapseAllBtn').on('click', function() {
        $('#facultyAccordion .collapse .collapse').collapse('hide');
        setTimeout(function() {
            $('#facultyAccordion > .card > .collapse').collapse('hide');
        }, 300);
    });

    // Multi-mode bulk form submit (with detailed confirmation modal)
    $('#multiBulkTransitionForm').on('submit', function(e) {
        e.preventDefault();

        var selected = $('.multi-course-checkbox:checked').length;
        if (selected === 0) {
            alert('Please select at least one course.');
            return false;
        }

        var targetState = $('#multiBulkState').val();
        if (!targetState) {
            alert('Please select a target state.');
            return false;
        }

        // Gather rich data from all selected courses
        var states = [];
        var programData = {}; // programId -> { name, courses[], totalStudents, totalMarks, totalPass, totalFail }
        var totalStudents = 0;
        var totalMarks = 0;
        var totalPass = 0;
        var totalFail = 0;
        var caNotPublishedCourses = [];
        var isFinalExam = {{ $is_final_exam ? 'true' : 'false' }};

        $('.multi-course-checkbox:checked').each(function() {
            var state = $(this).data('state');
            states.push(state);
            var $row = $(this).closest('tr');
            var programId = $(this).data('program-id');
            var subjectCode = $(this).data('subject-code') || $row.find('td:eq(1) .badge').text().trim();
            var title = $row.find('td:eq(2)').text().trim();
            var students = parseInt($row.find('td:eq(4)').text().trim()) || 0;
            var withMarks = parseInt($row.find('td:eq(5) .badge').text().trim()) || 0;
            var pass = parseInt($row.find('td:eq(6)').text().trim()) || 0;
            var fail = parseInt($row.find('td:eq(7)').text().trim()) || 0;

            // Check CA status for final exam — look for the CA column badge
            var caPublished = true;
            if (isFinalExam) {
                // The CA column has a badge-success (check) or badge-danger (times)
                var $caCell = $row.find('td .badge-success .fa-check, td .badge-danger .fa-times').closest('td');
                if ($caCell.length > 0) {
                    caPublished = $caCell.find('.badge-success').length > 0;
                    if (!caPublished && targetState === 'published') {
                        caNotPublishedCourses.push(subjectCode + ' - ' + title);
                    }
                }
            }

            totalStudents += students;
            totalMarks += withMarks;
            totalPass += pass;
            totalFail += fail;

            // Build program data
            if (!programData[programId]) {
                var $programHeader = $(this).closest('.card-body').closest('.collapse').prev('.card-header');
                programData[programId] = {
                    name: $programHeader.find('h6').text().trim(),
                    courses: [],
                    totalStudents: 0,
                    totalMarks: 0,
                    totalPass: 0,
                    totalFail: 0
                };
            }
            programData[programId].courses.push({
                code: subjectCode,
                title: title,
                students: students,
                withMarks: withMarks,
                pass: pass,
                fail: fail,
                state: state,
                caPublished: caPublished
            });
            programData[programId].totalStudents += students;
            programData[programId].totalMarks += withMarks;
            programData[programId].totalPass += pass;
            programData[programId].totalFail += fail;
        });

        var uniqueStates = [...new Set(states)];
        var hasMixedStates = uniqueStates.length > 1;
        var programCount = Object.keys(programData).length;

        // --- Populate Modal ---

        // Publishing Context: Programs
        var programNamesList = Object.values(programData).map(function(p) { return p.name; });
        if (programCount <= 3) {
            $('#multiModalProgramInfo').html('<i class="fas fa-graduation-cap text-primary"></i> ' + programNamesList.join('<br><i class="fas fa-graduation-cap text-primary"></i> '));
        } else {
            $('#multiModalProgramInfo').html('<span class="badge badge-primary">' + programCount + ' Programs</span><br><small class="text-muted">' + programNamesList.slice(0, 3).join(', ') + ' <em>+' + (programCount - 3) + ' more</em></small>');
        }

        // Transition Summary
        $('#multiModalSelectedCount').text(selected);
        $('#multiModalProgramCount').text(programCount);
        var currentStateDisplay = hasMixedStates ? 'Mixed (' + uniqueStates.join(', ') + ')' : uniqueStates[0].charAt(0).toUpperCase() + uniqueStates[0].slice(1);
        $('#multiModalCurrentState').text(currentStateDisplay);
        var targetStateDisplay = targetState.charAt(0).toUpperCase() + targetState.slice(1);
        $('#multiModalTargetState').text(targetStateDisplay);

        // Workflow Progress visualization
        updateMultiWorkflowProgress(hasMixedStates ? null : uniqueStates[0], targetState);

        // Courses list grouped by program
        var coursesHtml = '';
        var stateClassMap = {
            'draft': 'badge-secondary',
            'submitted': 'badge-info',
            'checked': 'badge-primary',
            'approved': 'badge-warning',
            'published': 'badge-success'
        };

        Object.keys(programData).forEach(function(pid) {
            var prog = programData[pid];
            coursesHtml += '<div class="border-bottom">' +
                '<div class="bg-light px-3 py-2 d-flex justify-content-between align-items-center">' +
                '<strong><i class="fas fa-graduation-cap text-primary mr-1"></i> ' + prog.name + '</strong>' +
                '<span class="text-muted small">' + prog.courses.length + ' course(s) &middot; ' + prog.totalStudents + ' students</span>' +
                '</div>' +
                '<table class="table table-sm table-hover mb-0">' +
                '<thead class="thead-light"><tr>' +
                '<th style="width:100px;">Code</th>' +
                '<th>Course Title</th>' +
                '<th class="text-center" style="width:80px;">Students</th>' +
                '<th class="text-center" style="width:80px;">With Marks</th>' +
                '<th class="text-center" style="width:70px;">Pass</th>' +
                '<th class="text-center" style="width:70px;">Fail</th>' +
                '<th class="text-center" style="width:100px;">State</th>';
            if (isFinalExam) {
                coursesHtml += '<th class="text-center" style="width:80px;">CA Status</th>';
            }
            coursesHtml += '</tr></thead><tbody>';

            prog.courses.forEach(function(course) {
                var stateClass = stateClassMap[course.state] || 'badge-secondary';
                coursesHtml += '<tr>' +
                    '<td><span class="badge badge-dark">' + course.code + '</span></td>' +
                    '<td>' + course.title + '</td>' +
                    '<td class="text-center">' + course.students + '</td>' +
                    '<td class="text-center"><span class="badge badge-info">' + course.withMarks + '</span></td>' +
                    '<td class="text-center text-success">' + course.pass + '</td>' +
                    '<td class="text-center text-danger">' + course.fail + '</td>' +
                    '<td class="text-center"><span class="badge ' + stateClass + '">' + course.state.charAt(0).toUpperCase() + course.state.slice(1) + '</span></td>';
                if (isFinalExam) {
                    coursesHtml += '<td class="text-center">' +
                        (course.caPublished
                            ? '<span class="badge badge-success"><i class="fas fa-check"></i></span>'
                            : '<span class="badge badge-danger"><i class="fas fa-times"></i></span>') +
                        '</td>';
                }
                coursesHtml += '</tr>';
            });

            coursesHtml += '</tbody></table></div>';
        });

        $('#multiModalCoursesList').html(coursesHtml);
        $('#multiModalCoursesListCount').text(selected + ' course(s) across ' + programCount + ' program(s)');

        // Impact Summary
        $('#multiModalTotalStudents').text(totalStudents);
        $('#multiModalTotalMarks').text(totalMarks);
        $('#multiModalTotalPass').text(totalPass);
        $('#multiModalTotalFail').text(totalFail);

        // Per-Program Breakdown table
        var breakdownHtml = '';
        Object.keys(programData).forEach(function(pid) {
            var prog = programData[pid];
            breakdownHtml += '<tr>' +
                '<td><small><i class="fas fa-graduation-cap text-primary mr-1"></i> ' + prog.name + '</small></td>' +
                '<td class="text-center"><span class="badge badge-secondary">' + prog.courses.length + '</span></td>' +
                '<td class="text-center">' + prog.totalStudents + '</td>' +
                '<td class="text-center"><span class="badge badge-info">' + prog.totalMarks + '</span></td>' +
                '<td class="text-center text-success">' + prog.totalPass + '</td>' +
                '<td class="text-center text-danger">' + prog.totalFail + '</td>' +
                '</tr>';
        });
        $('#multiModalProgramBreakdownBody').html(breakdownHtml);

        // Show/hide publish impact
        if (targetState === 'published') {
            $('#multiModalPublishImpact').show();
            $('#multiModalPublishProgramCount').text(programCount);
            $('#multiModalPublishDateTimeSection').show();

            // Copy date/time from form to modal
            var formDate = $('#multiPublishDate').val();
            var formTime = $('#multiPublishTime').val();
            if (formDate) $('#multiModalPublishDate').val(formDate);
            if (formTime) $('#multiModalPublishTime').val(formTime);

            // Update preview text
            var dateVal = $('#multiModalPublishDate').val();
            var timeVal = $('#multiModalPublishTime').val();
            if (dateVal && timeVal) {
                var dateObj = new Date(dateVal + 'T' + timeVal);
                var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                var timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
                $('#multiPublishPreviewText').html('<i class="fas fa-clock"></i> Scheduled: ' + dateObj.toLocaleDateString('en-US', options) + ' at ' + dateObj.toLocaleTimeString('en-US', timeOptions));
            }
        } else {
            $('#multiModalPublishImpact').hide();
            $('#multiModalPublishDateTimeSection').hide();
        }

        // Handle warnings
        $('#multiModalWarningsSection').hide();
        $('#multiModalMixedStatesWarning').hide();
        $('#multiModalCANotPublishedWarning').hide();
        $('#multiModalNoMarksWarning').hide();
        $('#multiModalCrossProgramWarning').hide();

        var hasBlockingError = false;

        if (hasMixedStates) {
            $('#multiModalWarningsSection').show();
            $('#multiModalMixedStatesWarning').show();
            $('#multiModalMixedStatesMessage').text('Selected courses are in different workflow states (' + uniqueStates.join(', ') + '). Please select courses in the same state for bulk transition.');
            hasBlockingError = true;
        }

        if (isFinalExam && caNotPublishedCourses.length > 0 && targetState === 'published') {
            $('#multiModalWarningsSection').show();
            $('#multiModalCANotPublishedWarning').show();
            $('#multiModalCANotPublishedMessage').text(caNotPublishedCourses.length + ' course(s) have unpublished CA results:');
            var caListHtml = '';
            caNotPublishedCourses.forEach(function(course) {
                caListHtml += '<li class="text-danger"><i class="fas fa-times-circle mr-1"></i> ' + course + '</li>';
            });
            $('#multiModalCANotPublishedList').html(caListHtml);
            hasBlockingError = true;
        }

        // Check for courses with fewer marks than students
        var lowMarksCount = 0;
        Object.values(programData).forEach(function(prog) {
            prog.courses.forEach(function(c) {
                if (c.withMarks < c.students && c.students > 0) lowMarksCount++;
            });
        });
        if (lowMarksCount > 0) {
            $('#multiModalWarningsSection').show();
            $('#multiModalNoMarksWarning').show();
            $('#multiModalNoMarksMessage').text(lowMarksCount + ' course(s) have students without marks entered. These students will not have results after publishing.');
        }

        // Cross-program warning (always shown when > 1 program)
        if (programCount > 1) {
            $('#multiModalWarningsSection').show();
            $('#multiModalCrossProgramWarning').show();
            $('#multiModalCrossProgramMessage').text('You are transitioning ' + selected + ' courses across ' + programCount + ' programs simultaneously. This is a high-impact operation affecting ' + totalStudents + ' students. Please verify all selections are correct.');
        }

        // Enable/disable confirm button
        $('#btnConfirmMultiBulkTransition').prop('disabled', hasBlockingError);

        // Show the modal
        $('#multiBulkTransitionConfirmModal').modal('show');

        return false;
    });

    // Multi-mode workflow progress visualization
    function updateMultiWorkflowProgress(currentState, targetState) {
        var stateOrder = ['draft', 'submitted', 'checked', 'approved', 'published'];
        var currentIndex = currentState ? stateOrder.indexOf(currentState) : -1;
        var targetIndex = stateOrder.indexOf(targetState);

        // Reset all steps
        $('.multi-workflow-step').css('background', '#6c757d');

        // Highlight current and completed steps
        if (currentIndex >= 0) {
            for (var i = 0; i <= currentIndex; i++) {
                $('#multiStep' + stateOrder[i].charAt(0).toUpperCase() + stateOrder[i].slice(1)).css('background', '#17a2b8');
            }
        }

        // Highlight target step
        $('#multiStep' + targetState.charAt(0).toUpperCase() + targetState.slice(1)).css('background', '#28a745');

        // Progress line
        var progressPercent = (targetIndex / (stateOrder.length - 1)) * 100;
        $('#multiWorkflowProgressLine').css('width', progressPercent + '%');

        // Update state boxes
        var stateColors = {
            'draft': '#6c757d',
            'submitted': '#17a2b8',
            'checked': '#007bff',
            'approved': '#ffc107',
            'published': '#28a745'
        };

        if (currentState) {
            $('#multiModalCurrentStateBox').css('background', stateColors[currentState] + '33');
            $('#multiModalCurrentState').css('color', stateColors[currentState]);
        } else {
            $('#multiModalCurrentStateBox').css('background', '#f8d7da');
            $('#multiModalCurrentState').css('color', '#dc3545');
        }

        $('#multiModalTargetStateBox').css('background', stateColors[targetState] + '33');
        $('#multiModalTargetState').css('color', stateColors[targetState]);
    }

    // Multi-mode publish date/time preview update
    $('#multiModalPublishDate, #multiModalPublishTime').on('change', function() {
        var date = $('#multiModalPublishDate').val();
        var time = $('#multiModalPublishTime').val();
        if (date && time) {
            var dateObj = new Date(date + 'T' + time);
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            var timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
            $('#multiPublishPreviewText').html('<i class="fas fa-clock"></i> Scheduled: ' + dateObj.toLocaleDateString('en-US', options) + ' at ' + dateObj.toLocaleTimeString('en-US', timeOptions));
        }
    });

    // Confirm Multi-Program Bulk Transition
    $('#btnConfirmMultiBulkTransition').on('click', function() {
        // Copy date/time values from modal to form if publishing
        if ($('#multiBulkState').val() === 'published') {
            $('#multiPublishDate').val($('#multiModalPublishDate').val());
            $('#multiPublishTime').val($('#multiModalPublishTime').val());
        }

        // Close modal
        $('#multiBulkTransitionConfirmModal').modal('hide');

        // Submit the multi-mode form directly (bypass the submit handler)
        setTimeout(function() {
            document.getElementById('multiBulkTransitionForm').submit();
        }, 300);
    });
    @endif
</script>
@endsection
