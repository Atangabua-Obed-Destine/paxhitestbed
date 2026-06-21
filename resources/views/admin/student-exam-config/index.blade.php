@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">

            {{-- ══════════════════════════════════════════════════════════════
                 SYSTEM-WIDE OVERVIEW: Pending Exam ID Configurations
                 Shows BEFORE any filters are applied so the admin immediately
                 knows which subjects / programs still need attention.
                 ══════════════════════════════════════════════════════════════ --}}
            @if(!empty($overview_pending) && $overview_pending->count())
            <div class="col-sm-12 mb-3">
                <div class="card border-left border-warning" style="border-left: 4px solid #f0ad4e !important;">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center" style="background:#fff8e1; cursor:pointer;" onclick="document.getElementById('overviewBody').classList.toggle('d-none');">
                        <h5 class="mb-0" style="font-size:15px;">
                            <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
                            System Overview &mdash;
                            <span class="text-danger font-weight-bold">{{ number_format($overview_total_pending) }}</span>
                            student exam {{ Str::plural('ID', $overview_total_pending) }} still pending
                            across <strong>{{ $overview_pending->count() }}</strong> subject {{ Str::plural('group', $overview_pending->count()) }}
                        </h5>
                        <span>
                            <span class="badge badge-success mr-1"><i class="fas fa-check"></i> {{ number_format($overview_total_configured) }} configured</span>
                            <span class="badge badge-danger mr-1"><i class="fas fa-times"></i> {{ number_format($overview_total_pending) }} pending</span>
                            <i class="fas fa-chevron-down text-muted ml-2"></i>
                        </span>
                    </div>
                    <div id="overviewBody" class="card-block p-0">
                        {{-- Progress bar --}}
                        @php
                            $overviewGrandTotal = $overview_grand_total ?? 1;
                            $overviewPercent = $overviewGrandTotal > 0 ? round(($overview_total_configured / $overviewGrandTotal) * 100) : 0;
                        @endphp
                        <div class="px-3 pt-3 pb-1">
                            <div class="d-flex justify-content-between mb-1" style="font-size:12px;">
                                <span>Overall Configuration Progress</span>
                                <strong>{{ $overviewPercent }}%</strong>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar {{ $overviewPercent == 100 ? 'bg-success' : ($overviewPercent >= 50 ? 'bg-info' : 'bg-warning') }}"
                                     role="progressbar" style="width:{{ $overviewPercent }}%"
                                     aria-valuenow="{{ $overviewPercent }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </div>

                        {{-- Overview table --}}
                        <div style="max-height: 380px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0" style="font-size:13px;">
                                <thead style="position:sticky; top:0; background:#f8f9fa; z-index:1;">
                                    <tr>
                                        <th style="width:5%">#</th>
                                        <th>Faculty</th>
                                        <th>Programme</th>
                                        <th>Subject</th>
                                        <th>Exam Type</th>
                                        <th>Session</th>
                                        <th class="text-center" style="width:8%">Total</th>
                                        <th class="text-center" style="width:8%"><i class="fas fa-check text-success"></i></th>
                                        <th class="text-center" style="width:8%"><i class="fas fa-times text-danger"></i></th>
                                        <th class="text-center" style="width:8%">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $prevFaculty = null; @endphp
                                    @foreach($overview_pending as $idx => $row)
                                        @php
                                            $rowPercent = $row->total_students > 0 ? round(($row->configured_count / $row->total_students) * 100) : 0;
                                            $showFaculty = ($row->faculty_name !== $prevFaculty);
                                            $prevFaculty = $row->faculty_name;
                                        @endphp
                                        <tr style="{{ $row->unconfigured_count > 0 ? 'background:#fff3cd;' : '' }}">
                                            <td class="text-muted">{{ $idx + 1 }}</td>
                                            <td>
                                                @if($showFaculty)
                                                    <strong>{{ $row->faculty_name }}</strong>
                                                @else
                                                    <span class="text-muted">&rdquo;</span>
                                                @endif
                                            </td>
                                            <td>{{ $row->program_name }}</td>
                                            <td>
                                                <strong>{{ $row->subject_name }}</strong>
                                                @if($row->subject_code)
                                                    <br><small class="text-muted">{{ $row->subject_code }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $row->exam_type_name }}</td>
                                            <td>{{ $row->session_name }}</td>
                                            <td class="text-center">{{ $row->total_students }}</td>
                                            <td class="text-center text-success">{{ $row->configured_count }}</td>
                                            <td class="text-center text-danger font-weight-bold">{{ $row->unconfigured_count }}</td>
                                            <td class="text-center">
                                                <div class="progress" style="height:6px; min-width:50px;">
                                                    <div class="progress-bar {{ $rowPercent >= 100 ? 'bg-success' : ($rowPercent >= 50 ? 'bg-info' : 'bg-danger') }}"
                                                         style="width:{{ $rowPercent }}%"></div>
                                                </div>
                                                <small class="text-muted">{{ $rowPercent }}%</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot style="background:#f8f9fa; font-weight:bold;">
                                    <tr>
                                        <td colspan="6" class="text-right">Totals:</td>
                                        <td class="text-center">{{ number_format($overview_grand_total) }}</td>
                                        <td class="text-center text-success">{{ number_format($overview_total_configured) }}</td>
                                        <td class="text-center text-danger">{{ number_format($overview_total_pending) }}</td>
                                        <td class="text-center">{{ $overviewPercent }}%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="px-3 py-2" style="font-size:12px; background:#f8f9fa;">
                            <i class="fas fa-info-circle text-muted mr-1"></i>
                            Use the filters below to select a specific subject and configure Student Exam IDs. Only subjects with pending IDs are shown above.
                        </div>
                    </div>
                </div>
            </div>
            @elseif(isset($overview_pending) && $overview_pending->count() === 0)
            <div class="col-sm-12 mb-3">
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong>All configured!</strong> Every student with attendance across all final exam types has been assigned a Student Exam ID.
                </div>
            </div>
            @endif

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            @if($cross_program)
                            <input type="hidden" name="cross_program" value="1">
                            @endif
                            <div class="row gx-2">
                                @include('common.inc.subject_search_filter')

                                <div class="form-group col-md-3">
                                    <label for="type">{{ __('field_type') }} <span>*</span></label>
                                    <select class="form-control" name="type" id="type" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($types as $type)
                                            <option value="{{ $type->id }}" @if($selected_type == $type->id) selected @endif>{{ $type->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_type') }}
                                    </div>
                                    <small class="d-block text-muted mt-1">{{ __('Only final exam types are available for anonymisation.') }}</small>
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
                 Allows supervisors to configure Exam IDs for students from ALL
                 programmes taking the same course (useful for general/shared courses).
                 Only shows when a subject has been selected and results exist.
                 ══════════════════════════════════════════════════════════════════ --}}
            @if(!empty($rows) && isset($sharing_programs) && $sharing_programs->count() > 1)
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
                                        Configuring Exam IDs for students from <strong class="text-info">{{ $sharing_programs->count() }} programmes</strong>
                                    @else
                                        This course is shared by <strong>{{ $sharing_programs->count() }} programmes</strong> &mdash; toggle to configure all at once
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
                                        Cross-programme mode is <strong>active</strong>. Students from all {{ $sharing_programs->count() }} programmes are shown below, grouped by programme. Exam IDs must still be unique across all programmes.
                                    </small>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @elseif(!empty($rows) && isset($sharing_programs) && $sharing_programs->count() == 1)
            {{-- Single programme - show a subtle note --}}
            <div class="col-sm-12">
                <div class="text-muted text-center mb-2" style="font-size: 12px;">
                    <i class="fas fa-lock"></i> This course is exclusive to <strong>{{ $sharing_programs->first()->title }}</strong>
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════════════════════
                 CONFIGURATION STATUS INDICATOR
                 Shows at-a-glance how many students still need exam IDs
                 ══════════════════════════════════════════════════════════ --}}
            @if(!empty($rows))
            <div class="col-sm-12">
                @php
                    $totalStudents     = $total_students ?? 0;
                    $configuredCount   = $configured_count ?? 0;
                    $unconfiguredCount = $unconfigured_count ?? 0;
                    $unconfiguredList  = $unconfigured_students ?? [];
                    $progressPercent   = $totalStudents > 0 ? round(($configuredCount / $totalStudents) * 100) : 0;
                    $subjectName       = optional($context_subject ?? null)->title ?? optional($context_subject ?? null)->code ?? 'N/A';
                    $programName       = optional($context_program ?? null)->title ?? 'N/A';
                    $sessionName       = optional($context_session ?? null)->title ?? 'N/A';
                    $examTypeName      = optional($selected_exam_type ?? null)->title ?? 'N/A';
                @endphp

                {{-- ── Summary Stats Card ── --}}
                <div class="card mb-0">
                    <div class="card-block py-3">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <span class="badge bg-secondary">{{ $programName }}</span>
                                    <span class="badge bg-info text-dark">{{ $subjectName }}</span>
                                    <span class="badge bg-dark">{{ $sessionName }}</span>
                                    <span class="badge bg-warning text-dark">{{ $examTypeName }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="fas fa-users text-primary"></i>
                                        <strong>{{ $totalStudents }}</strong> <small class="text-muted">Total</small>
                                    </span>
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <strong>{{ $configuredCount }}</strong> <small class="text-muted">Configured</small>
                                    </span>
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="fas fa-exclamation-circle text-danger"></i>
                                        <strong>{{ $unconfiguredCount }}</strong> <small class="text-muted">Pending</small>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 10px;">
                                        <div class="progress-bar {{ $progressPercent === 100 ? 'bg-success' : ($progressPercent >= 50 ? 'bg-info' : 'bg-danger') }}"
                                             role="progressbar"
                                             style="width: {{ $progressPercent }}%;"
                                             aria-valuenow="{{ $progressPercent }}"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    <strong class="{{ $progressPercent === 100 ? 'text-success' : 'text-muted' }}">{{ $progressPercent }}%</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Unconfigured Students Detail Alert ── --}}
                @if($unconfiguredCount > 0)
                    <div class="alert alert-danger mb-3 mt-0" style="border-left: 4px solid #dc3545 !important; border-radius: 0 0 4px 4px;">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-exclamation-triangle mt-1" style="font-size: 1.2rem;"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold text-danger">
                                    <i class="fas fa-id-card-alt me-1"></i>
                                    {{ $unconfiguredCount }} Student{{ $unconfiguredCount > 1 ? 's' : '' }} Missing Exam ID Configuration
                                </h6>
                                <p class="mb-2 small">
                                    The following student{{ $unconfiguredCount > 1 ? 's' : '' }} attended the <strong>{{ $examTypeName }}</strong> exam for
                                    <strong>{{ $subjectName }}</strong> but {{ $unconfiguredCount > 1 ? 'have' : 'has' }} not been assigned an anonymous Student Exam ID yet.
                                    <strong>Without an Exam ID, {{ $unconfiguredCount > 1 ? 'their scripts' : 'this student\'s script' }} cannot be anonymously marked.</strong>
                                </p>

                                {{-- Scrollable Detail Table --}}
                                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
                                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                            <tr>
                                                <th style="width: 40px;">#</th>
                                                <th>{{ __('field_matricule') }}</th>
                                                <th>{{ __('field_name') }}</th>
                                                <th>{{ trans_choice('module_program', 1) }}</th>
                                                <th>{{ __('field_semester') }}</th>
                                                <th>{{ __('field_section') }}</th>
                                                <th style="width: 120px;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($unconfiguredList as $index => $exam)
                                                @php $stu = optional($exam->studentEnroll->student); @endphp
                                                <tr>
                                                    <td class="text-muted">{{ $index + 1 }}</td>
                                                    <td>
                                                        <a href="{{ route('admin.student.show', $stu->id ?? 0) }}" target="_blank" class="fw-bold">
                                                            {{ $exam->studentEnroll->matricule ?? $stu->student_id ?? '—' }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $stu->first_name ?? '' }} {{ $stu->last_name ?? '' }}</td>
                                                    <td>
                                                        <span class="badge badge-light border" style="font-size: 10px; white-space: normal;">
                                                            {{ Str::limit($exam->studentEnroll->program->title ?? 'N/A', 25) }}
                                                        </span>
                                                    </td>
                                                    <td><small>{{ $exam->studentEnroll->semester->title ?? '—' }}</small></td>
                                                    <td><small>{{ $exam->studentEnroll->section->title ?? '—' }}</small></td>
                                                    <td>
                                                        <span class="badge bg-danger"><i class="fas fa-times-circle"></i> No Exam ID</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-2 small">
                                    <i class="fas fa-arrow-down text-primary"></i>
                                    <strong>What to do:</strong> Scroll down to the main table, enter a unique Student Exam ID in the input field for each flagged student, then click <em>"{{ __('btn_update') }}"</em> to save.
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-success mb-3 mt-0" style="border-left: 4px solid #28a745 !important; border-radius: 0 0 4px 4px;">
                        <i class="fas fa-check-circle me-1"></i>
                        <strong>All {{ $totalStudents }} student{{ $totalStudents > 1 ? 's' : '' }} have been assigned an Exam ID.</strong>
                        Configuration is complete for <strong>{{ $subjectName }}</strong> ({{ $examTypeName }}).
                    </div>
                @endif
            </div>
            @endif

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('Configure Student Exam IDs') }}</h5>
                        @if(!empty($rows))
                            <span class="badge bg-primary">{{ count($rows) }}</span>
                        @endif
                    </div>
                    <div class="card-block">
                        @error('student_exam_ids')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror

                        @if(empty($rows))
                            <div class="alert alert-warning mb-0" role="alert">
                                {{ __('No matching exam records found. Please adjust your filters and ensure attendance has been captured for the selected exam.') }}
                            </div>
                        @else
                            <form method="post" action="{{ route($route.'.store') }}" class="needs-validation" novalidate>
                                @csrf

                                <input type="hidden" name="faculty" value="{{ $selected_faculty }}">
                                <input type="hidden" name="program" value="{{ $selected_program }}">
                                <input type="hidden" name="session" value="{{ $selected_session }}">
                                <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                <input type="hidden" name="section" value="{{ $selected_section }}">
                                <input type="hidden" name="subject" value="{{ $selected_subject }}">
                                <input type="hidden" name="type" value="{{ $selected_type }}">
                                @if($cross_program)
                                    <input type="hidden" name="cross_program" value="1">
                                @endif

                                <div class="table-responsive">
                                    <table class="display table nowrap table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>{{ __('field_matricule') }}</th>
                                                <th>{{ __('field_name') }}</th>
                                                @if($cross_program)
                                                    <th>{{ trans_choice('module_program', 1) }}</th>
                                                @endif
                                                <th>{{ __('Student Exam ID') }}</th>
                                                <th>{{ __('field_semester') }}</th>
                                                <th>{{ __('field_section') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $prevProgramId = null; @endphp
                                            @foreach($rows as $exam)
                                                @php
                                                    $student = $exam->studentEnroll->student;
                                                    $currentCode = old('student_exam_ids.'.$exam->id, $exam->scriptCode->anonymous_code ?? '');
                                                    $isUnconfigured = empty($currentCode);
                                                    $examProgram = $exam->studentEnroll->program ?? null;
                                                @endphp

                                                {{-- Programme group separator --}}
                                                @if($cross_program && $examProgram && $examProgram->id !== $prevProgramId)
                                                    @php $prevProgramId = $examProgram->id; @endphp
                                                    <tr class="bg-light">
                                                        <td colspan="{{ $cross_program ? 6 : 5 }}" class="py-2 px-3">
                                                            <strong>
                                                                <i class="fas fa-graduation-cap text-primary me-1"></i>
                                                                {{ $examProgram->title }}
                                                            </strong>
                                                            @if($examProgram->faculty)
                                                                <small class="text-muted ms-2">({{ $examProgram->faculty->title }})</small>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif

                                                <tr class="{{ $isUnconfigured ? 'table-warning' : '' }}">
                                                    <td>
                                                        @if($isUnconfigured)
                                                            <i class="fas fa-exclamation-circle text-danger me-1" title="Missing Exam ID"></i>
                                                        @else
                                                            <i class="fas fa-check-circle text-success me-1" title="Configured"></i>
                                                        @endif
                                                        @if($student)
                                                            <a href="{{ route('admin.student.show', $student->id) }}">#{{ $exam->studentEnroll->matricule ?? $student->student_id }}</a>
                                                        @else
                                                            {{ __('Unknown Student') }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }}</td>
                                                    @if($cross_program)
                                                        <td>
                                                            <span class="badge badge-light border" style="font-size: 10px; white-space: normal;">
                                                                {{ Str::limit($examProgram->title ?? 'N/A', 25) }}
                                                            </span>
                                                        </td>
                                                    @endif
                                                    <td style="min-width: 180px;">
                                                        <input type="text" class="form-control {{ $isUnconfigured ? 'border-danger' : 'border-success' }}" name="student_exam_ids[{{ $exam->id }}]" value="{{ $currentCode }}" maxlength="50" placeholder="{{ $isUnconfigured ? '⚠ Enter Exam ID' : __('Enter code') }}">
                                                    </td>
                                                    <td>{{ $exam->studentEnroll->semester->title ?? '' }}</td>
                                                    <td>{{ $exam->studentEnroll->section->title ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if($cross_program)
                                    <p class="text-muted small mt-2"><i class="fas fa-info-circle"></i> {{ __('Cross-programme mode: Student Exam IDs must be unique across ALL programmes sharing this subject within this session. This ensures no two students from any programme have the same anonymous code.') }}</p>
                                @else
                                    <p class="text-muted small mt-2">{{ __('Student exam IDs must be unique within this selection (same program, session, semester, subject, and exam type). The same code can be reused in different selections. Leave a field blank to clear an existing assignment.') }}</p>
                                @endif

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> {{ __('btn_update') }}</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script>
$(document).ready(function() {
    // Cross-programme toggle handler
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
});
</script>
@endsection
