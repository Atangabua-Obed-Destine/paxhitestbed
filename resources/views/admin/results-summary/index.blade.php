@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title"><i class="fas fa-chart-bar"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.course-marksheet') }}" class="btn btn-secondary">
                                <i class="fas fa-file-spreadsheet"></i> {{ __('Course Marksheet') }}
                            </a>
                            <a href="{{ route($route.'.student-results-summary') }}" class="btn btn-success">
                                <i class="fas fa-th-list"></i> {{ __('Student Results') }}
                            </a>
                            <a href="{{ route($route.'.department-report') }}" class="btn btn-primary">
                                <i class="fas fa-building"></i> {{ __('Department Report') }}
                            </a>
                            <a href="{{ route($route.'.faculty-report') }}" class="btn btn-info">
                                <i class="fas fa-university"></i> {{ __('Faculty Report') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Filter Results') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
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
                                        <i class="fas fa-search"></i> {{ __('btn_search') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($kpis))
            <!-- KPI Cards -->
            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-primary">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('Total Scripts') }}</h6>
                                <h3 class="m-b-0 text-white">{{ number_format($kpis['total_scripts']) }}</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-alt text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white">
                            <span class="label label-primary m-r-10">{{ $kpis['unique_courses'] }}</span>{{ __('Courses') }} |
                            <span class="label label-primary m-r-10">{{ $kpis['unique_students'] }}</span>{{ __('Students') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-success">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('Passed Scripts') }}</h6>
                                <h3 class="m-b-0 text-white">{{ number_format($kpis['passed_scripts']) }}</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white">
                            <span class="label label-success m-r-10">{{ $kpis['pass_rate'] }}%</span>{{ __('Pass Rate') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-danger">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('Failed Scripts') }}</h6>
                                <h3 class="m-b-0 text-white">{{ number_format($kpis['failed_scripts']) }}</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white">
                            <span class="label label-danger m-r-10">{{ $kpis['fail_rate'] }}%</span>{{ __('Fail Rate') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-info">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('Average Mark') }}</h6>
                                <h3 class="m-b-0 text-white">{{ $kpis['average_mark'] }}%</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white">
                            <span class="label label-info m-r-10">{{ __('Overall Performance') }}</span>
                        </p>
                    </div>
                </div>
            </div>

            @if(isset($faculty_summary) && $faculty_summary)
            <!-- Faculty Summary -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-university"></i> {{ $faculty_summary['faculty_name'] }} ({{ $faculty_summary['faculty_shortcode'] }})</h5>
                        @if($faculty_summary['dean_name'])
                        <span class="badge badge-secondary float-right">Dean: {{ $faculty_summary['dean_name'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- Charts Row -->
            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> {{ __('Grade Distribution') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="gradeDistributionChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> {{ __('Pass/Fail Overview') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="passFailChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            @if(isset($department_summaries) && count($department_summaries) > 0)
            <!-- Department Summaries Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-building"></i> {{ __('Department Performance Summary') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Department') }}</th>
                                        <th class="text-center">{{ __('Courses') }}</th>
                                        <th class="text-center">{{ __('Scripts Written') }}</th>
                                        <th class="text-center">{{ __('Pass') }}</th>
                                        <th class="text-center">{{ __('Fail') }}</th>
                                        <th class="text-center">{{ __('% Pass') }}</th>
                                        <th class="text-center">{{ __('% Fail') }}</th>
                                        <th class="text-center">{{ __('Performance') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($department_summaries as $key => $dept)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <strong>{{ $dept['name'] }}</strong>
                                            @if($dept['shortcode'])
                                            <br><small class="text-muted">{{ $dept['shortcode'] }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $dept['courses_offered'] }}</td>
                                        <td class="text-center">{{ $dept['scripts_written'] }}</td>
                                        <td class="text-center text-success"><strong>{{ $dept['passed'] }}</strong></td>
                                        <td class="text-center text-danger"><strong>{{ $dept['failed'] }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-success">{{ $dept['pass_rate'] }}%</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-danger">{{ $dept['fail_rate'] }}%</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $dept['pass_rate'] }}%">
                                                    {{ $dept['pass_rate'] }}%
                                                </div>
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $dept['fail_rate'] }}%">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="2"><strong>{{ __('TOTAL') }}</strong></th>
                                        <th class="text-center">{{ collect($department_summaries)->sum('courses_offered') }}</th>
                                        <th class="text-center">{{ collect($department_summaries)->sum('scripts_written') }}</th>
                                        <th class="text-center text-success"><strong>{{ collect($department_summaries)->sum('passed') }}</strong></th>
                                        <th class="text-center text-danger"><strong>{{ collect($department_summaries)->sum('failed') }}</strong></th>
                                        <th class="text-center">
                                            @php
                                                $totalScripts = collect($department_summaries)->sum('scripts_written');
                                                $totalPassed = collect($department_summaries)->sum('passed');
                                                $overallPassRate = $totalScripts > 0 ? round(($totalPassed / $totalScripts) * 100, 2) : 0;
                                            @endphp
                                            <span class="badge badge-success">{{ $overallPassRate }}%</span>
                                        </th>
                                        <th class="text-center">
                                            <span class="badge badge-danger">{{ round(100 - $overallPassRate, 2) }}%</span>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Top/Low Performing Courses -->
            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-trophy"></i> {{ __('Top Performing Courses') }}</h5>
                    </div>
                    <div class="card-body">
                        @if(isset($top_performing_courses) && count($top_performing_courses) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Course') }}</th>
                                        <th class="text-center">{{ __('Scripts') }}</th>
                                        <th class="text-center">{{ __('Pass Rate') }}</th>
                                        <th class="text-center">{{ __('Avg.') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($top_performing_courses as $course)
                                    <tr>
                                        <td>
                                            <strong>{{ $course['course_code'] }}</strong>
                                            <br><small>{{ Str::limit($course['course_title'], 30) }}</small>
                                        </td>
                                        <td class="text-center">{{ $course['total_scripts'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-success">{{ $course['pass_rate'] }}%</span>
                                        </td>
                                        <td class="text-center">{{ $course['average_marks'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <p class="text-muted text-center">{{ __('No data available') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-exclamation-triangle"></i> {{ __('Courses Needing Attention') }}</h5>
                    </div>
                    <div class="card-body">
                        @if(isset($low_performing_courses) && count($low_performing_courses) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Course') }}</th>
                                        <th class="text-center">{{ __('Scripts') }}</th>
                                        <th class="text-center">{{ __('Pass Rate') }}</th>
                                        <th class="text-center">{{ __('Avg.') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($low_performing_courses as $course)
                                    <tr>
                                        <td>
                                            <strong>{{ $course['course_code'] }}</strong>
                                            <br><small>{{ Str::limit($course['course_title'], 30) }}</small>
                                        </td>
                                        <td class="text-center">{{ $course['total_scripts'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-danger">{{ $course['pass_rate'] }}%</span>
                                        </td>
                                        <td class="text-center">{{ $course['average_marks'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <p class="text-muted text-center">{{ __('No data available') }}</p>
                        @endif
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

@if(isset($grade_distribution) && count($grade_distribution) > 0)
// Grade Distribution Chart
var gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
var gradeChart = new Chart(gradeCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(collect($grade_distribution)->pluck('grade')) !!},
        datasets: [{
            data: {!! json_encode(collect($grade_distribution)->pluck('count')) !!},
            backgroundColor: {!! json_encode(collect($grade_distribution)->pluck('color')) !!},
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            },
            title: {
                display: true,
                text: '{{ __("Grade Distribution") }}'
            }
        }
    }
});

// Pass/Fail Chart
var passFailCtx = document.getElementById('passFailChart').getContext('2d');
var passFailChart = new Chart(passFailCtx, {
    type: 'bar',
    data: {
        labels: ['{{ __("Passed") }}', '{{ __("Failed") }}'],
        datasets: [{
            label: '{{ __("Scripts") }}',
            data: [{{ $kpis['passed_scripts'] }}, {{ $kpis['failed_scripts'] }}],
            backgroundColor: ['#28a745', '#dc3545'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
@endif
</script>
@endsection
