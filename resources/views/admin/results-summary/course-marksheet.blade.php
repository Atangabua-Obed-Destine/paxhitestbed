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
                        <h4 class="page-title"><i class="fas fa-file-spreadsheet"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Results Summary') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Course Mark Sheet') }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                            </a>
                            <a href="{{ route($route.'.student-results-summary') }}" class="btn btn-info">
                                <i class="fas fa-th-list"></i> {{ __('Student Results') }}
                            </a>
                            @if(isset($student_records) && count($student_records) > 0)
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
                        <h5><i class="fas fa-filter"></i> {{ __('Select Course to Generate Mark Sheet') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.course-marksheet') }}">
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
                                    <label for="subject">{{ __('field_subject') }} <span class="text-danger">*</span></label>
                                    <select class="form-control subject" name="subject" id="subject" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($subjects))
                                        @foreach($subjects->sortBy('code') as $subj)
                                        <option value="{{ $subj->id }}" @if($selected_subject == $subj->id) selected @endif>{{ $subj->code }} - {{ $subj->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <button type="submit" class="btn btn-info btn-filter">
                                        <i class="fas fa-file-alt"></i> {{ __('Generate Mark Sheet') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($student_records) && count($student_records) > 0)
            <!-- Mark Sheet Header - Printable -->
            <div class="col-sm-12" id="printable-marksheet">
                <div class="card">
                    <div class="card-body">
                        <!-- Institution Header -->
                        <div class="text-center mb-3">
                            <h3 class="mb-1 font-weight-bold">{{ $course_meta['institution_name'] }}</h3>
                            <h5 class="text-primary">{{ $course_meta['faculty_name'] }}</h5>
                            <h6>{{ $course_meta['semester_title'] }} Exam {{ $course_meta['session_title'] }}</h6>
                        </div>
                        
                        <hr>
                        
                        <!-- Course Information Grid -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="140"><strong>Department:</strong></td>
                                        <td>{{ $course_meta['department_name'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Course Code:</strong></td>
                                        <td><span class="badge badge-dark">{{ $course_meta['course_code'] }}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Course Title:</strong></td>
                                        <td>{{ $course_meta['course_title'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Credit Value:</strong></td>
                                        <td><span class="badge badge-info">{{ $course_meta['credit_value'] }} Credits</span></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="140"><strong>Lecturer(s):</strong></td>
                                        <td>{{ $course_meta['lecturers'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Level/Semester:</strong></td>
                                        <td>{{ $course_meta['level'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Programme:</strong></td>
                                        <td>{{ $course_meta['program_name'] }} ({{ $course_meta['degree_type'] }})</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Course Type:</strong></td>
                                        <td><span class="badge badge-secondary">{{ $course_meta['course_type'] }}</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Mark Distribution Info -->
                        <div class="alert alert-info mb-3 no-print">
                            <i class="fas fa-info-circle"></i>
                            <strong>Mark Distribution:</strong>
                            Attendance ({{ $mark_distribution['attendance'] }}) + 
                            Assignments ({{ $mark_distribution['assignment'] }}) + 
                            Activities ({{ $mark_distribution['activity'] }}) +
                            CA Exams ({{ $mark_distribution['ca_exam'] ?? 0 }}) = 
                            <strong>CA {{ $mark_distribution['ca_total'] }}/100</strong> | 
                            Final Exam = <strong>{{ $mark_distribution['exam_total'] }}/100</strong> | 
                            <strong>Total = {{ $mark_distribution['grand_total'] }}/100</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Data Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header no-print">
                        <h5><i class="fas fa-users"></i> {{ __('Student Results') }} ({{ count($student_records) }} Students)</h5>
                        <div class="card-header-right">
                            <form method="post" action="{{ route($route.'.export-course-marksheet') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="faculty" value="{{ $selected_faculty }}">
                                <input type="hidden" name="program" value="{{ $selected_program }}">
                                <input type="hidden" name="session" value="{{ $selected_session }}">
                                <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                <input type="hidden" name="section" value="{{ $selected_section }}">
                                <input type="hidden" name="subject" value="{{ $selected_subject }}">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel"></i> {{ __('Export to Excel') }}
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm" id="marksheet-table">
                                <thead class="thead-dark">
                                    <tr>
                                        <th rowspan="2" class="text-center align-middle">S/N</th>
                                        <th rowspan="2" class="text-center align-middle">MAT. NO</th>
                                        <th rowspan="2" class="align-middle">NAME</th>
                                        <th rowspan="2" class="text-center align-middle">Exam Code</th>
                                        <th colspan="3" class="text-center bg-info">CONTINUOUS ASSESSMENT</th>
                                        <th colspan="2" class="text-center bg-secondary">EXAM SIGN</th>
                                        <th rowspan="2" class="text-center align-middle bg-warning">Exam<br>/{{ $mark_distribution['exam_total'] }}</th>
                                        <th rowspan="2" class="text-center align-middle bg-success text-white">Total<br>/100</th>
                                        <th rowspan="2" class="text-center align-middle">Grade</th>
                                        <th rowspan="2" class="text-center align-middle">Remarks</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center bg-info">Att/{{ $mark_distribution['attendance'] }}</th>
                                        <th class="text-center bg-info">CA/{{ $mark_distribution['ca_total'] - $mark_distribution['attendance'] }}</th>
                                        <th class="text-center bg-info">Tot/{{ $mark_distribution['ca_total'] }}</th>
                                        <th class="text-center bg-secondary">In</th>
                                        <th class="text-center bg-secondary">Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($student_records as $record)
                                    <tr class="{{ $record['remark'] == 'Failed' ? 'table-danger' : '' }}">
                                        <td class="text-center">{{ $record['sn'] }}</td>
                                        <td class="text-center">
                                            <strong>{{ $record['matricule'] }}</strong>
                                        </td>
                                        <td>{{ $record['name'] }}</td>
                                        <td class="text-center">
                                            <code>{{ $record['exam_code'] }}</code>
                                        </td>
                                        <td class="text-center">{{ number_format($record['attendance_marks'], 1) }}</td>
                                        <td class="text-center">{{ number_format($record['assignment_marks'] + $record['activity_marks'] + ($record['ca_exam_marks'] ?? 0), 1) }}</td>
                                        <td class="text-center"><strong>{{ number_format($record['total_ca'], 1) }}</strong></td>
                                        <td class="text-center">
                                            @if($record['exam_attendance'])
                                                <i class="fas fa-check text-success"></i>
                                            @else
                                                <i class="fas fa-times text-danger"></i>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($record['exam_attendance'])
                                                <i class="fas fa-check text-success"></i>
                                            @else
                                                <i class="fas fa-times text-danger"></i>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ number_format($record['exam_marks'], 1) }}</td>
                                        <td class="text-center"><strong>{{ number_format($record['total_marks'], 1) }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $record['grade'] == 'F' ? 'danger' : ($record['grade'] == 'A' ? 'success' : 'primary') }}">
                                                {{ $record['grade'] }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $record['remark'] == 'Passed' ? 'success' : 'danger' }}">
                                                {{ $record['remark'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> {{ __('Statistics') }}</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong>No. Registered</strong></td>
                                    <td class="text-right"><span class="badge badge-primary badge-lg">{{ $statistics['registered'] }}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>No. Examined</strong></td>
                                    <td class="text-right"><span class="badge badge-info badge-lg">{{ $statistics['examined'] }}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>No. Absent</strong></td>
                                    <td class="text-right"><span class="badge badge-secondary badge-lg">{{ $statistics['absent'] }}</span></td>
                                </tr>
                                <tr class="table-success">
                                    <td><strong>No. Passed</strong></td>
                                    <td class="text-right"><span class="badge badge-success badge-lg">{{ $statistics['passed'] }}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>% Pass</strong></td>
                                    <td class="text-right"><span class="badge badge-success badge-lg">{{ $statistics['pass_rate'] }}%</span></td>
                                </tr>
                                <tr class="table-danger">
                                    <td><strong>No. Failed</strong></td>
                                    <td class="text-right"><span class="badge badge-danger badge-lg">{{ $statistics['failed'] }}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>% Failed</strong></td>
                                    <td class="text-right"><span class="badge badge-danger badge-lg">{{ $statistics['fail_rate'] }}%</span></td>
                                </tr>
                                <tr class="table-info">
                                    <td><strong>Course Average</strong></td>
                                    <td class="text-right"><span class="badge badge-info badge-lg">{{ $statistics['course_average'] }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Grade Distribution -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-graduation-cap"></i> {{ __('Grade Distribution') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered text-center">
                                <thead class="thead-light">
                                    <tr>
                                        @foreach($grades as $grade)
                                        <th>{{ $grade->title }}</th>
                                        @endforeach
                                        <th class="bg-dark text-white">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @php $gradeTotal = 0; @endphp
                                        @foreach($grades as $grade)
                                        @php 
                                            $count = $statistics['grade_distribution'][$grade->title] ?? 0;
                                            $gradeTotal += $count;
                                        @endphp
                                        <td>
                                            <span class="badge badge-{{ $grade->remark == 'Fail' ? 'danger' : ($grade->title == 'A' ? 'success' : 'primary') }} badge-lg">
                                                {{ $count }}
                                            </span>
                                        </td>
                                        @endforeach
                                        <td><span class="badge badge-dark badge-lg">{{ $gradeTotal }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Visual Chart -->
                        <canvas id="gradeChart" height="200" class="mt-3 no-print"></canvas>
                    </div>
                </div>
            </div>

            <!-- Signature Section -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-signature"></i> {{ __('Verification & Signatures') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="border rounded p-3 text-center h-100">
                                    <p class="mb-5">&nbsp;</p>
                                    <hr>
                                    <p class="mb-0"><strong>Lecturer</strong></p>
                                    <small class="text-muted">{{ $course_meta['lecturers'] }}</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="border rounded p-3 text-center h-100">
                                    <p class="mb-5">&nbsp;</p>
                                    <hr>
                                    <p class="mb-0"><strong>Head of Department</strong></p>
                                    <small class="text-muted">{{ $course_meta['department_name'] }}</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="border rounded p-3 text-center h-100 bg-light">
                                    <p class="mb-5">&nbsp;</p>
                                    <hr>
                                    <p class="mb-0"><strong>Dean</strong></p>
                                    <small class="text-muted">{{ $course_meta['faculty_shortcode'] }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Footer -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center">
                        <small class="text-muted">
                            Report generated on {{ $course_meta['generated_at'] }} by {{ $course_meta['generated_by'] }}
                        </small>
                    </div>
                </div>
            </div>
            @else
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-spreadsheet fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ __('Select filters to generate the Individual Course Mark Sheet') }}</h5>
                        <p class="text-muted">This report generates a detailed mark sheet for a specific course, similar to the Excel format used for exam results.</p>
                        <ul class="list-unstyled text-muted">
                            <li><i class="fas fa-check text-success"></i> Student enrollment and attendance</li>
                            <li><i class="fas fa-check text-success"></i> Continuous Assessment marks</li>
                            <li><i class="fas fa-check text-success"></i> Final Examination marks</li>
                            <li><i class="fas fa-check text-success"></i> Grade calculation and statistics</li>
                            <li><i class="fas fa-check text-success"></i> Export to Excel functionality</li>
                        </ul>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@if(isset($statistics) && isset($grades))
<script type="text/javascript">
"use strict";

// Grade Distribution Chart
var ctx = document.getElementById('gradeChart').getContext('2d');
var gradeChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($grades->pluck('title')) !!},
        datasets: [{
            label: '{{ __("Number of Students") }}',
            data: [
                @foreach($grades as $grade)
                {{ $statistics['grade_distribution'][$grade->title] ?? 0 }},
                @endforeach
            ],
            backgroundColor: [
                @foreach($grades as $grade)
                '{{ $grade->remark == "Fail" ? "rgba(220, 53, 69, 0.8)" : ($grade->title == "A" ? "rgba(40, 167, 69, 0.8)" : "rgba(0, 123, 255, 0.8)") }}',
                @endforeach
            ],
            borderColor: [
                @foreach($grades as $grade)
                '{{ $grade->remark == "Fail" ? "rgb(220, 53, 69)" : ($grade->title == "A" ? "rgb(40, 167, 69)" : "rgb(0, 123, 255)") }}',
                @endforeach
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>
@endif

<script type="text/javascript">
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
    $(".subject").empty().append('<option value="">{{ __("select") }}</option>');
    
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
    $(".subject").empty().append('<option value="">{{ __("select") }}</option>');
    
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

// AJAX filter for subjects based on session
$(".session").on('change', function(e) {
    e.preventDefault();
    var program = $(".program").val();
    var session = $(this).val();
    var subject = $(".subject");
    
    subject.empty().append('<option value="">{{ __("select") }}</option>');
    
    if (!session || !program) return;
    
    $.ajax({
        type: 'POST',
        url: "{{ route('filter-subject') }}",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            program: program,
            session: session
        },
        success: function(response) {
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.code + ' - ' + this.title
                }).appendTo(subject);
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
    canvas,
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
        padding: 2px 4px !important;
        border: 1px solid #000 !important;
    }
    
    .table-responsive {
        overflow: visible !important;
    }
    
    /* Ensure colors print */
    .badge-success { background-color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-warning { background-color: #ffc107 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-danger { background-color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-info { background-color: #17a2b8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-primary { background-color: #007bff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-success { color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-danger { color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .thead-dark th { background-color: #343a40 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-info { background-color: #17a2b8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-secondary { background-color: #6c757d !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-warning { background-color: #ffc107 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-success { background-color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .table-danger { background-color: #f8d7da !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    /* Page setup */
    @page {
        size: A4 landscape;
        margin: 5mm;
    }
}

.badge-lg {
    font-size: 14px;
    padding: 6px 10px;
}
</style>
@endsection
