@extends('admin.layouts.master')
@section('title', $title)
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
                        <h4 class="page-title"><i class="fas fa-th-list"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Results Summary') }}</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                            </a>
                            <a href="{{ route($route.'.course-marksheet') }}" class="btn btn-primary">
                                <i class="fas fa-file-spreadsheet"></i> {{ __('Course Marksheet') }}
                            </a>
                            @if(isset($student_results) && count($student_results) > 0)
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('Print') }}
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Select Program & Session to Generate Student Results Summary') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.student-results-summary') }}">
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
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-block btn-filter">
                                        <i class="fas fa-th-list"></i> {{ __('Generate Summary') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($student_results) && count($student_results) > 0)
            <!-- Results Summary Header - Printable -->
            <div class="col-sm-12" id="printable-summary">
                <div class="card">
                    <div class="card-body">
                        <!-- Institution Header -->
                        <div class="text-center mb-3">
                            <h3 class="mb-1 font-weight-bold">{{ $meta['institution_name'] }}</h3>
                            <h5 class="text-primary">{{ $meta['faculty_name'] }}</h5>
                            <h6>{{ $meta['semester_title'] }} Results {{ $meta['session_title'] }}</h6>
                        </div>
                        
                        <hr>
                        
                        <!-- Program Information Grid -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="140"><strong>Department:</strong></td>
                                        <td>{{ $meta['department_name'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Programme:</strong></td>
                                        <td>{{ $meta['program_name'] }} ({{ $meta['degree_type'] }})</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="140"><strong>Level/Semester:</strong></td>
                                        <td>{{ $meta['level'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Section:</strong></td>
                                        <td>{{ $meta['section_title'] }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row mb-3 no-print">
                            <div class="col-md-3">
                                <div class="alert alert-info text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['total_students'] }}</h4>
                                    <small>Total Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-primary text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['courses_count'] }}</h4>
                                    <small>Courses</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-success text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['total_passed'] }}</h4>
                                    <small>Clear Pass (All Courses)</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-danger text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['total_failed'] }}</h4>
                                    <small>Failed (≥1 Course)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Results Matrix Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header no-print">
                        <h5><i class="fas fa-table"></i> {{ __('Student Results Matrix') }} ({{ count($student_results) }} Students × {{ count($subjects) }} Courses)</h5>
                        <div class="card-header-right">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleView('compact')">
                                    <i class="fas fa-compress-alt"></i> Compact
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleView('full')">
                                    <i class="fas fa-expand-alt"></i> Full
                                </button>
                                <form method="post" action="{{ route($route.'.export-student-results-summary') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="faculty" value="{{ $selected_faculty }}">
                                    <input type="hidden" name="program" value="{{ $selected_program }}">
                                    <input type="hidden" name="session" value="{{ $selected_session }}">
                                    <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                    <input type="hidden" name="section" value="{{ $selected_section }}">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-excel"></i> {{ __('Export to Excel') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                            <table class="table table-bordered table-sm mb-0" id="results-matrix" style="font-size: 0.85em;">
                                <thead class="thead-dark" style="position: sticky; top: 0; z-index: 10;">
                                    <!-- Course Headers Row -->
                                    <tr>
                                        <th rowspan="2" class="text-center align-middle sticky-col" style="min-width: 40px; left: 0; background: #343a40; z-index: 11;">S/N</th>
                                        <th rowspan="2" class="text-center align-middle sticky-col" style="min-width: 100px; left: 40px; background: #343a40; z-index: 11;">Mat No.</th>
                                        <th rowspan="2" class="align-middle sticky-col" style="min-width: 180px; left: 140px; background: #343a40; z-index: 11;">Name</th>
                                        @foreach($subjects as $subject)
                                        <th colspan="5" class="text-center course-header" style="background: #495057; border-left: 2px solid #212529; padding: 4px;">
                                            <span class="badge badge-primary" style="font-size: 0.9em;">{{ $subject->code }}</span>
                                            <br><small class="text-warning" style="font-size: 0.75em;" title="{{ $subject->title }}">{{ Str::limit($subject->title, 20) }}</small>
                                            <br><small class="text-light">CV: {{ $subject->credit_hour }}</small>
                                        </th>
                                        @endforeach
                                        <th colspan="5" class="text-center" style="background: #17a2b8; border-left: 2px solid #212529;">
                                            <span class="text-white font-weight-bold">SUMMARY</span>
                                        </th>
                                    </tr>
                                    <!-- Sub-headers Row -->
                                    <tr>
                                        @foreach($subjects as $subject)
                                        <th class="text-center sub-header att-col" style="background: #6c757d; border-left: 2px solid #212529; min-width: 35px;" title="Attendance">Att</th>
                                        <th class="text-center sub-header ca-col" style="background: #6c757d; min-width: 35px;" title="Continuous Assessment">CA</th>
                                        <th class="text-center sub-header ex-col" style="background: #6c757d; min-width: 35px;" title="Exam">EX</th>
                                        <th class="text-center sub-header tot-col" style="background: #6c757d; min-width: 40px;" title="Total">TOT</th>
                                        <th class="text-center sub-header grd-col" style="background: #6c757d; min-width: 35px;" title="Grade">Grd</th>
                                        @endforeach
                                        <th class="text-center" style="background: #138496; color: white; border-left: 2px solid #212529; min-width: 40px;" title="Total Credits Registered">TCR</th>
                                        <th class="text-center" style="background: #138496; color: white; min-width: 40px;" title="Total Credits Earned">TCE</th>
                                        <th class="text-center" style="background: #138496; color: white; min-width: 45px;" title="Grade Point Average">GPA</th>
                                        <th class="text-center" style="background: #28a745; color: white; min-width: 35px;" title="Courses Passed">✓</th>
                                        <th class="text-center" style="background: #dc3545; color: white; min-width: 35px;" title="Courses Failed">✗</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($student_results as $student)
                                    @php
                                        $hasFailure = $student['summary']['courses_failed'] > 0;
                                        $rowClass = $hasFailure ? 'table-warning' : '';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="text-center sticky-col" style="left: 0; background: #fff;">{{ $student['sn'] }}</td>
                                        <td class="text-center sticky-col" style="left: 40px; background: #fff; font-size: 0.8em;">{{ $student['matricule'] }}</td>
                                        <td class="sticky-col" style="left: 140px; background: #fff; white-space: nowrap;">{{ Str::limit($student['name'], 25) }}</td>
                                        @foreach($subjects as $subject)
                                            @php
                                                $course = $student['courses'][$subject->id] ?? null;
                                                $isRegistered = $course && $course['registered'];
                                                $isPassed = $isRegistered && ($course['status'] ?? '') == 'P';
                                                $isFailed = $isRegistered && ($course['status'] ?? '') == 'F';
                                                $isAbsent = $isRegistered && ($course['status'] ?? '') == 'ABS';
                                            @endphp
                                            @if($isRegistered)
                                                <td class="text-center att-col" style="border-left: 2px solid #dee2e6;">{{ $course['attendance_marks'] }}</td>
                                                <td class="text-center ca-col">{{ $course['ca_marks'] }}</td>
                                                <td class="text-center ex-col">{{ $course['exam_marks'] }}</td>
                                                <td class="text-center tot-col font-weight-bold {{ $isFailed ? 'text-danger' : ($isPassed ? 'text-success' : '') }}">{{ $course['total_marks'] }}</td>
                                                <td class="text-center grd-col">
                                                    <span class="badge {{ $isPassed ? 'badge-success' : ($isFailed ? 'badge-danger' : 'badge-secondary') }}">
                                                        {{ $course['grade'] }}
                                                    </span>
                                                </td>
                                            @else
                                                <td class="text-center text-muted" style="border-left: 2px solid #dee2e6;">-</td>
                                                <td class="text-center text-muted">-</td>
                                                <td class="text-center text-muted">-</td>
                                                <td class="text-center text-muted">-</td>
                                                <td class="text-center text-muted">-</td>
                                            @endif
                                        @endforeach
                                        <!-- Summary columns -->
                                        <td class="text-center font-weight-bold" style="border-left: 2px solid #17a2b8; background: #e8f4f8;">{{ $student['summary']['total_credits_registered'] }}</td>
                                        <td class="text-center font-weight-bold" style="background: #e8f4f8;">{{ $student['summary']['total_credits_earned'] }}</td>
                                        <td class="text-center font-weight-bold" style="background: #e8f4f8;">
                                            <span class="badge {{ $student['summary']['gpa'] >= 2.0 ? 'badge-success' : 'badge-warning' }}">
                                                {{ number_format($student['summary']['gpa'], 2) }}
                                            </span>
                                        </td>
                                        <td class="text-center text-success font-weight-bold" style="background: #d4edda;">{{ $student['summary']['courses_passed'] }}</td>
                                        <td class="text-center text-danger font-weight-bold" style="background: #f8d7da;">{{ $student['summary']['courses_failed'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="3" class="text-right sticky-col" style="left: 0; background: #e9ecef; z-index: 11;">Course Summary:</th>
                                        @foreach($subjects as $subject)
                                            @php
                                                $stats = $course_stats[$subject->id] ?? null;
                                            @endphp
                                            <th colspan="5" class="text-center" style="font-size: 0.75em; border-left: 2px solid #dee2e6;">
                                                @if($stats)
                                                <div>Reg: {{ $stats['registered'] }} | Exam: {{ $stats['examined'] }}</div>
                                                <div class="text-success">P: {{ $stats['passed'] }} ({{ $stats['pass_rate'] }}%)</div>
                                                <div class="text-danger">F: {{ $stats['failed'] }}</div>
                                                @endif
                                            </th>
                                        @endforeach
                                        <th colspan="5" class="text-center" style="border-left: 2px solid #17a2b8; background: #d1ecf1; font-size: 0.8em;">
                                            <div><strong>{{ $overall_stats['total_students'] }}</strong> Students</div>
                                            <div class="text-success">Clear: {{ $overall_stats['total_passed'] }}</div>
                                            <div class="text-danger">With Fails: {{ $overall_stats['total_failed'] }}</div>
                                            @if(($overall_stats['total_pending'] ?? 0) > 0)
                                            <div class="text-muted">Pending: {{ $overall_stats['total_pending'] }}</div>
                                            @endif
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Performance Summary -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> {{ __('Course Performance Overview') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($subjects as $subject)
                            @php
                                $stats = $course_stats[$subject->id] ?? null;
                            @endphp
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="card border {{ $stats && $stats['pass_rate'] >= 50 ? 'border-success' : 'border-danger' }}">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1">
                                            <span class="badge badge-dark">{{ $subject->code }}</span>
                                            <small class="text-muted">(CV: {{ $subject->credit_hour }})</small>
                                        </h6>
                                        <p class="card-text small text-truncate mb-2" title="{{ $subject->title }}">{{ $subject->title }}</p>
                                        @if($stats)
                                        <div class="d-flex justify-content-between small">
                                            <span>Examined: {{ $stats['examined'] }}</span>
                                            <span class="text-success">Pass: {{ $stats['passed'] }}</span>
                                            <span class="text-danger">Fail: {{ $stats['failed'] }}</span>
                                        </div>
                                        <div class="progress mt-2" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: {{ $stats['pass_rate'] }}%"></div>
                                            <div class="progress-bar bg-danger" style="width: {{ 100 - $stats['pass_rate'] }}%"></div>
                                        </div>
                                        <div class="text-center mt-1">
                                            <span class="badge {{ $stats['pass_rate'] >= 50 ? 'badge-success' : 'badge-danger' }}">
                                                {{ $stats['pass_rate'] }}% Pass
                                            </span>
                                            <span class="badge badge-info">Avg: {{ $stats['average'] }}%</span>
                                        </div>
                                        @else
                                        <div class="text-muted small">No data</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grade Distribution Legend -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> {{ __('Grade Legend') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap">
                            @foreach($grades as $grade)
                            <div class="mr-4 mb-2">
                                <span class="badge {{ $grade->min_mark >= 50 ? 'badge-success' : 'badge-danger' }}" style="font-size: 1em;">
                                    {{ $grade->title }}
                                </span>
                                <span class="small text-muted">{{ $grade->min_mark }}-{{ $grade->max_mark }}% ({{ $grade->point }} pts)</span>
                            </div>
                            @endforeach
                        </div>
                        <hr>
                        <div class="small text-muted">
                            <strong>Legend:</strong>
                            <span class="mr-3"><span class="badge badge-success">P</span> = Passed (≥50%)</span>
                            <span class="mr-3"><span class="badge badge-danger">F</span> = Failed (<50%)</span>
                            <span class="mr-3"><span class="badge badge-secondary">ABS</span> = Absent</span>
                            <span class="mr-3"><span class="badge badge-light">NR</span> = Not Registered</span>
                            <span class="mr-3"><strong>TCR</strong> = Total Credits Registered</span>
                            <span class="mr-3"><strong>TCE</strong> = Total Credits Earned</span>
                            <span class="mr-3"><strong>GPA</strong> = Grade Point Average</span>
                        </div>
                    </div>
                </div>
            </div>

            @else
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                        <h5>{{ __('Select filters to generate Student Results Summary') }}</h5>
                        <p class="text-muted">Choose Faculty, Program, Session, and Semester to view the student-by-course results matrix.</p>
                    </div>
                </div>
            </div>
            @endif

        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script>
    "use strict";

    // AJAX filter for programs based on faculty
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

    // AJAX filter for sessions and semesters based on program
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

    // AJAX filter for sections based on semester
    $(".semester").on('change', function(e) {
        e.preventDefault();
        var program = $(".program").val();
        var semester = $(this).val();
        var section = $(".section");
        
        section.empty().append('<option value="0">{{ __("all") }}</option>');
        
        if (!semester || !program) return;
        
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

    // Toggle view mode
    function toggleView(mode) {
        var table = document.getElementById('results-matrix');
        if (mode === 'compact') {
            table.classList.add('table-compact');
            document.querySelectorAll('.att-col, .ca-col, .ex-col').forEach(function(el) {
                el.style.display = 'none';
            });
        } else {
            table.classList.remove('table-compact');
            document.querySelectorAll('.att-col, .ca-col, .ex-col').forEach(function(el) {
                el.style.display = '';
            });
        }
    }
</script>
@endsection

@section('page_css')
<style>
    /* Sticky columns for horizontal scroll */
    .sticky-col {
        position: sticky;
        background: #fff;
        z-index: 5;
    }
    
    /* Ensure table headers are sticky on vertical scroll */
    #results-matrix thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    /* Compact mode styles */
    .table-compact td, .table-compact th {
        padding: 0.2rem !important;
        font-size: 0.75em !important;
    }
    
    /* Print styles */
    @media print {
        .no-print {
            display: none !important;
        }
        
        body {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
        
        .sticky-col {
            position: static !important;
        }
        
        #results-matrix {
            font-size: 0.65em !important;
        }
        
        #results-matrix thead th {
            position: static !important;
            background: #343a40 !important;
            color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        #results-matrix .course-header {
            background: #495057 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        #results-matrix .sub-header {
            background: #6c757d !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .table-responsive {
            max-height: none !important;
            overflow: visible !important;
        }
        
        .page-wrapper {
            padding: 0 !important;
        }
        
        .main-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .badge {
            border: 1px solid #000 !important;
            padding: 2px 4px !important;
        }
        
        .badge-success {
            background: #28a745 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .badge-danger {
            background: #dc3545 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .badge-warning {
            background: #ffc107 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .text-success {
            color: #28a745 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .text-danger {
            color: #dc3545 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        /* Ensure table fits on page */
        table {
            width: 100% !important;
            table-layout: auto !important;
        }
        
        td, th {
            padding: 2px 3px !important;
        }
        
        /* Page break settings */
        #printable-summary {
            page-break-after: avoid;
        }
        
        @page {
            size: landscape;
            margin: 0.5cm;
        }
    }
    
    /* Course header styling */
    .course-header {
        white-space: nowrap;
    }
    
    /* Badge adjustments */
    .badge {
        font-size: 0.75em;
    }
    
    /* Table row hover */
    #results-matrix tbody tr:hover {
        background-color: #f5f5f5 !important;
    }
    
    /* Scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        height: 10px;
        width: 10px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 5px;
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
</style>
@endsection
