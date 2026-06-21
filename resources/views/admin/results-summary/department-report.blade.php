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
                        <h4 class="page-title"><i class="fas fa-building"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Results Summary') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Department Report') }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                            </a>
                            @if(isset($course_results) && count($course_results) > 0)
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('Print Report') }}
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
                        <h5><i class="fas fa-filter"></i> {{ __('Generate Department Report') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.department-report') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-2">
                                    <label for="faculty">{{ __('field_faculty') }} <span class="text-danger">*</span></label>
                                    <select class="form-control faculty" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($faculties))
                                        @foreach($faculties->sortBy('title') as $faculty)
                                        <option value="{{ $faculty->id }}" @if($selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="department">{{ __('field_department') }}</label>
                                    <select class="form-control department" name="department" id="department">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($departments))
                                        @foreach($departments->sortBy('title') as $dept)
                                        <option value="{{ $dept->id }}" @if($selected_department == $dept->id) selected @endif>{{ $dept->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control program" name="program" id="program">
                                        <option value="0">{{ __('all') }}</option>
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
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-block btn-filter">
                                        <i class="fas fa-file-alt"></i> {{ __('Generate Report') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($course_results) && count($course_results) > 0)
            <!-- Report Header -->
            <div class="col-sm-12" id="printable-report">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-1">{{ $report_meta['institution_name'] ?? config('app.name') }}</h3>
                        <hr>
                        <div class="row">
                            <div class="col-md-6 text-left">
                                <p class="mb-1"><strong>SCHOOL/FACULTY:</strong> {{ $report_meta['faculty_name'] }}</p>
                                <p class="mb-1"><strong>Degree Programme:</strong> {{ $report_meta['degree_type'] }} Programme</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <p class="mb-1"><strong>DEPARTMENT:</strong> {{ $report_meta['department_name'] }}</p>
                                <p class="mb-1"><strong>SEMESTER:</strong> {{ $report_meta['semester_title'] }}</p>
                                <p class="mb-1"><strong>SCHOOL YEAR:</strong> {{ $report_meta['session_title'] }}</p>
                            </div>
                        </div>
                        <hr>
                        <h5 class="text-primary">FREQUENCY DISTRIBUTION OF STUDENT PERFORMANCES</h5>
                    </div>
                </div>
            </div>

            <!-- Course Results Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm" id="results-table">
                                <thead class="thead-dark">
                                    <tr>
                                        <th rowspan="2" class="text-center align-middle">SN</th>
                                        <th rowspan="2" class="text-center align-middle">COURSE CODE</th>
                                        <th rowspan="2" class="align-middle">COURSE TITLE</th>
                                        <th rowspan="2" class="text-center align-middle">CV</th>
                                        <th rowspan="2" class="text-center align-middle">ST</th>
                                        <th rowspan="2" class="align-middle">LECTURER(S)</th>
                                        <th rowspan="2" class="text-center align-middle">% CC</th>
                                        <th colspan="2" class="text-center">NUMBER</th>
                                        <th colspan="2" class="text-center">RESULTS</th>
                                        <th colspan="2" class="text-center">PERCENTAGE</th>
                                        <th colspan="{{ count($grades) }}" class="text-center">GRADE DISTRIBUTION</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">CR</th>
                                        <th class="text-center">CE</th>
                                        <th class="text-center">PASS</th>
                                        <th class="text-center">FAIL</th>
                                        <th class="text-center">% PASS</th>
                                        <th class="text-center">% FAIL</th>
                                        @foreach($grades as $grade)
                                        <th class="text-center">{{ $grade->title }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalCR = 0;
                                        $totalCE = 0;
                                        $totalPass = 0;
                                        $totalFail = 0;
                                        $gradeColumnTotals = [];
                                        foreach($grades as $grade) {
                                            $gradeColumnTotals[$grade->title] = 0;
                                        }
                                    @endphp
                                    @foreach($course_results as $key => $course)
                                    @php
                                        $totalCR += $course['candidates_registered'];
                                        $totalCE += $course['candidates_examined'];
                                        $totalPass += $course['passed'];
                                        $totalFail += $course['failed'];
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td class="text-center"><strong>{{ $course['course_code'] }}</strong></td>
                                        <td>{{ $course['course_title'] }}</td>
                                        <td class="text-center">{{ $course['credit_value'] }}</td>
                                        <td class="text-center">{{ $course['status'] }}</td>
                                        <td>{{ Str::limit($course['lecturers'], 25) }}</td>
                                        <td class="text-center">{{ $course['course_coverage'] }}</td>
                                        <td class="text-center">{{ $course['candidates_registered'] }}</td>
                                        <td class="text-center">{{ $course['candidates_examined'] }}</td>
                                        <td class="text-center text-success"><strong>{{ $course['passed'] }}</strong></td>
                                        <td class="text-center text-danger"><strong>{{ $course['failed'] }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $course['pass_rate'] >= 70 ? 'success' : ($course['pass_rate'] >= 50 ? 'warning' : 'danger') }}">
                                                {{ number_format($course['pass_rate'], 2) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $course['fail_rate'] <= 30 ? 'success' : ($course['fail_rate'] <= 50 ? 'warning' : 'danger') }}">
                                                {{ number_format($course['fail_rate'], 2) }}
                                            </span>
                                        </td>
                                        @foreach($grades as $grade)
                                        @php
                                            $gradeCount = $course['grade_distribution'][$grade->title] ?? 0;
                                            $gradeColumnTotals[$grade->title] += $gradeCount;
                                        @endphp
                                        <td class="text-center">{{ $gradeCount }}</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="7" class="text-right"><strong>TOTAL/AVERAGE</strong></th>
                                        <th class="text-center">{{ $totalCR }}</th>
                                        <th class="text-center">{{ $totalCE }}</th>
                                        <th class="text-center text-success"><strong>{{ $totalPass }}</strong></th>
                                        <th class="text-center text-danger"><strong>{{ $totalFail }}</strong></th>
                                        <th class="text-center">
                                            <span class="badge badge-success">
                                                {{ $totalCE > 0 ? number_format(($totalPass / $totalCE) * 100, 2) : 0 }}
                                            </span>
                                        </th>
                                        <th class="text-center">
                                            <span class="badge badge-danger">
                                                {{ $totalCE > 0 ? number_format(($totalFail / $totalCE) * 100, 2) : 0 }}
                                            </span>
                                        </th>
                                        @foreach($grades as $grade)
                                        <th class="text-center">{{ $gradeColumnTotals[$grade->title] }}</th>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Legend -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted">
                                <strong>CV</strong> = Credit Value; 
                                <strong>ST</strong> = Status (C = Compulsory; E = Elective); 
                                <strong>CR</strong> = Candidates Registered; 
                                <strong>CE</strong> = Candidates Examined; 
                                <strong>% CC</strong> = Course Coverage; 
                                The % Pass is based on CE (Candidates who completed the course by writing the final examination).
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Number of Marked Scripts</h6>
                                    <h3 class="text-primary">{{ $totalCE }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Number of Passed Scripts</h6>
                                    <h3 class="text-success">{{ $totalPass }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Number of Failed Scripts</h6>
                                    <h3 class="text-danger">{{ $totalFail }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Success Rate</h6>
                                    <h3 class="text-info">{{ $totalCE > 0 ? number_format(($totalPass / $totalCE), 4) : 0 }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lecturers Section -->
            @if(isset($lecturers) && count($lecturers) > 0)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chalkboard-teacher"></i> {{ __('Lecturers') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($lecturers as $lecturer)
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-2 text-center">
                                    <i class="fas fa-user-tie fa-2x text-muted mb-2"></i>
                                    <p class="mb-0"><strong>{{ $lecturer }}</strong></p>
                                    <small class="text-muted">_______________________</small>
                                    <p class="mb-0 small">Signature</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Report Footer -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center">
                        <small class="text-muted">
                            Report generated on {{ $report_meta['generated_at'] }} by {{ $report_meta['generated_by'] }}
                        </small>
                    </div>
                </div>
            </div>
            @else
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ __('Please select filters to generate the department report') }}</h5>
                        <p class="text-muted">Select Faculty, Session, and Semester to view course-by-course results.</p>
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
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>

<script type="text/javascript">
"use strict";

// Faculty filter change
$(".faculty").on('change', function(e) {
    e.preventDefault();
    var faculty = $(this).val();
    var department = $(".department");
    var program = $(".program");
    
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Load departments
    $.ajax({
        type: 'POST',
        url: "{{ route('filter-academic-department') }}",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            faculty: faculty
        },
        success: function(response) {
            department.empty();
            department.append('<option value="0">{{ __("all") }}</option>');
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.title
                }).appendTo('.department');
            });
        }
    });
    
    // Load programs
    $.ajax({
        type: 'POST',
        url: "{{ route('filter-program') }}",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            faculty: faculty
        },
        success: function(response) {
            program.empty();
            program.append('<option value="0">{{ __("all") }}</option>');
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.title
                }).appendTo('.program');
            });
        }
    });
});

// Program filter change
$(".program").on('change', function(e) {
    e.preventDefault();
    var program = $(this).val();
    var session = $(".session");
    var semester = $(".semester");
    
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Load sessions
    $.ajax({
        type: 'POST',
        url: "{{ route('filter-session') }}",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            program: program
        },
        success: function(response) {
            session.empty();
            session.append('<option value="">{{ __("select") }}</option>');
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.title
                }).appendTo('.session');
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
            semester.empty();
            semester.append('<option value="">{{ __("select") }}</option>');
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.title
                }).appendTo('.semester');
            });
        }
    });
});
</script>

<style>
/* Print styles */
@media print {
    /* Hide all navigation and non-essential elements */
    .pcoded-header,
    .pcoded-navbar,
    .page-header,
    .breadcrumb,
    nav.navbar,
    .sidebar,
    footer,
    .btn,
    .btn-group,
    form,
    .card-header,
    .no-print {
        display: none !important;
        visibility: hidden !important;
    }
    
    /* Reset page layout for print */
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    
    .pcoded-main-container {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    .pcoded-wrapper,
    .pcoded-content,
    .pcoded-inner-content,
    .main-body,
    .page-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
        margin: 0 0 10px 0 !important;
    }
    
    .card-body {
        padding: 5px !important;
    }
    
    /* Table styling for print */
    table {
        font-size: 8px !important;
        width: 100% !important;
        border-collapse: collapse !important;
    }
    
    table th, table td {
        padding: 3px 4px !important;
        border: 1px solid #000 !important;
    }
    
    .table-responsive {
        overflow: visible !important;
    }
    
    /* Ensure colors print */
    .badge-success { background-color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-warning { background-color: #ffc107 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-danger { background-color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-success { color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-danger { color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .thead-dark th { background-color: #343a40 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    /* Page setup */
    @page {
        size: A4 landscape;
        margin: 5mm;
    }
}
</style>
@endsection
