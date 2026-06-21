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
                        <h4 class="page-title"><i class="fas fa-university"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Results Summary') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Faculty Report') }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                            </a>
                            @if(isset($department_summaries) && count($department_summaries) > 0)
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
                        <h5><i class="fas fa-filter"></i> {{ __('Generate Faculty Summary Report') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.faculty-report') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="faculty">{{ __('field_faculty') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($faculties))
                                        @foreach($faculties->sortBy('title') as $faculty)
                                        <option value="{{ $faculty->id }}" @if($selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="degree_type">{{ __('Degree Type') }}</label>
                                    <select class="form-control" name="degree_type" id="degree_type">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($degree_types))
                                        @foreach($degree_types as $dt)
                                        <option value="{{ $dt->id }}" @if($selected_degree_type == $dt->id) selected @endif>{{ $dt->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="session">{{ __('field_session') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="session" id="session" required>
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
                                    <select class="form-control" name="semester" id="semester" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($semesters))
                                        @foreach($semesters->sortBy('id') as $sem)
                                        <option value="{{ $sem->id }}" @if($selected_semester == $sem->id) selected @endif>{{ $sem->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
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

            @if(isset($department_summaries) && count($department_summaries) > 0)
            <!-- Report Header -->
            <div class="col-sm-12" id="printable-report">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-1">{{ $report_meta['institution_name'] ?? config('app.name') }}</h3>
                        <h4 class="text-primary mb-3">{{ $report_meta['faculty_name'] }} ({{ $report_meta['faculty_shortcode'] }})</h4>
                        <hr>
                        <h5>{{ $report_meta['degree_type'] }} - {{ strtoupper($report_meta['semester_title']) }} RESULTS {{ $report_meta['session_title'] }}</h5>
                    </div>
                </div>
            </div>

            <!-- Faculty Summary Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="faculty-results-table">
                                <thead class="thead-dark">
                                    <tr>
                                        <th class="text-center">S/N</th>
                                        <th>Department</th>
                                        <th class="text-center">No. of Courses Offered</th>
                                        <th class="text-center">No. of Scripts Written</th>
                                        <th class="text-center">Pass</th>
                                        <th class="text-center">Fail</th>
                                        <th class="text-center">% Pass</th>
                                        <th class="text-center">% Fail</th>
                                        <th class="text-center">Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($department_summaries as $key => $dept)
                                    <tr>
                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td>
                                            <strong>{{ $dept['name'] }}</strong>
                                            @if($dept['head_name'] && $dept['head_name'] != 'N/A')
                                            <br><small class="text-muted">HOD: {{ $dept['head_name'] }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $dept['courses_offered'] }}</td>
                                        <td class="text-center">{{ $dept['scripts_written'] }}</td>
                                        <td class="text-center text-success"><strong>{{ $dept['passed'] }}</strong></td>
                                        <td class="text-center text-danger"><strong>{{ $dept['failed'] }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $dept['pass_rate'] >= 70 ? 'success' : ($dept['pass_rate'] >= 50 ? 'warning' : 'danger') }}">
                                                {{ number_format($dept['pass_rate'], 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $dept['fail_rate'] <= 30 ? 'success' : ($dept['fail_rate'] <= 50 ? 'warning' : 'danger') }}">
                                                {{ number_format($dept['fail_rate'], 2) }}%
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $dept['pass_rate'] }}%" title="Pass: {{ $dept['pass_rate'] }}%">
                                                    {{ $dept['pass_rate'] >= 15 ? number_format($dept['pass_rate'], 1) . '%' : '' }}
                                                </div>
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $dept['fail_rate'] }}%" title="Fail: {{ $dept['fail_rate'] }}%">
                                                    {{ $dept['fail_rate'] >= 15 ? number_format($dept['fail_rate'], 1) . '%' : '' }}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="2" class="text-right"><strong>TOTAL</strong></th>
                                        <th class="text-center"><strong>{{ $faculty_totals['courses_offered'] }}</strong></th>
                                        <th class="text-center"><strong>{{ $faculty_totals['scripts_written'] }}</strong></th>
                                        <th class="text-center text-success"><strong>{{ $faculty_totals['passed'] }}</strong></th>
                                        <th class="text-center text-danger"><strong>{{ $faculty_totals['failed'] }}</strong></th>
                                        <th class="text-center">
                                            <span class="badge badge-success badge-lg">{{ number_format($faculty_totals['pass_rate'], 2) }}%</span>
                                        </th>
                                        <th class="text-center">
                                            <span class="badge badge-danger badge-lg">{{ number_format($faculty_totals['fail_rate'], 2) }}%</span>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="col-xl-3 col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-book fa-2x mb-2"></i>
                        <h2>{{ $faculty_totals['courses_offered'] }}</h2>
                        <p class="mb-0">Total Courses</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-file-alt fa-2x mb-2"></i>
                        <h2>{{ $faculty_totals['scripts_written'] }}</h2>
                        <p class="mb-0">Scripts Marked</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h2>{{ number_format($faculty_totals['pass_rate'], 1) }}%</h2>
                        <p class="mb-0">Overall Pass Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-2x mb-2"></i>
                        <h2>{{ count($department_summaries) }}</h2>
                        <p class="mb-0">Departments</p>
                    </div>
                </div>
            </div>

            <!-- Performance Chart -->
            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> {{ __('Department Performance Comparison') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentPerformanceChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> {{ __('Scripts Distribution by Department') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="scriptsDistributionChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Signature Section -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-signature"></i> {{ __('Signatures') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($department_summaries as $dept)
                            <div class="col-md-3 mb-4">
                                <div class="border rounded p-3 text-center h-100">
                                    <p class="mb-4">&nbsp;</p>
                                    <hr>
                                    <p class="mb-0"><strong>HOD {{ $dept['name'] }}</strong></p>
                                    @if($dept['head_name'] && $dept['head_name'] != 'N/A')
                                    <small class="text-muted">{{ $dept['head_name'] }}</small>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                            
                            <div class="col-md-3 mb-4">
                                <div class="border rounded p-3 text-center h-100 bg-light">
                                    <p class="mb-4">&nbsp;</p>
                                    <hr>
                                    <p class="mb-0"><strong>Dean {{ $report_meta['faculty_shortcode'] }}</strong></p>
                                    @if($report_meta['dean_name'] && $report_meta['dean_name'] != 'N/A')
                                    <small class="text-muted">{{ $report_meta['dean_name'] }}</small>
                                    @endif
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
                        <h5 class="text-muted">{{ __('Please select filters to generate the faculty summary report') }}</h5>
                        <p class="text-muted">Select Faculty, Session, and Semester to view the summary report.</p>
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

@if(isset($department_summaries) && count($department_summaries) > 0)
<script type="text/javascript">
"use strict";

// Department Performance Chart
var perfCtx = document.getElementById('departmentPerformanceChart').getContext('2d');
var perfChart = new Chart(perfCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode(collect($department_summaries)->pluck('name')) !!},
        datasets: [{
            label: '{{ __("Pass Rate %") }}',
            data: {!! json_encode(collect($department_summaries)->pluck('pass_rate')) !!},
            backgroundColor: 'rgba(40, 167, 69, 0.8)',
            borderColor: 'rgb(40, 167, 69)',
            borderWidth: 1
        }, {
            label: '{{ __("Fail Rate %") }}',
            data: {!! json_encode(collect($department_summaries)->pluck('fail_rate')) !!},
            backgroundColor: 'rgba(220, 53, 69, 0.8)',
            borderColor: 'rgb(220, 53, 69)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        },
        plugins: {
            legend: {
                position: 'top',
            }
        }
    }
});

// Scripts Distribution Chart
var distCtx = document.getElementById('scriptsDistributionChart').getContext('2d');
var distChart = new Chart(distCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(collect($department_summaries)->pluck('name')) !!},
        datasets: [{
            data: {!! json_encode(collect($department_summaries)->pluck('scripts_written')) !!},
            backgroundColor: [
                '#007bff',
                '#28a745',
                '#dc3545',
                '#ffc107',
                '#17a2b8',
                '#6610f2',
                '#fd7e14',
                '#20c997',
                '#e83e8c',
                '#6c757d'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            }
        }
    }
});
</script>
@endif

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
        font-size: 9px !important;
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
    
    /* Hide charts and KPI cards on print */
    .col-xl-3.col-md-6,
    .col-xl-6.col-md-12 {
        display: none !important;
    }
    
    /* Ensure colors print */
    .badge-success { background-color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-warning { background-color: #ffc107 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-danger { background-color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-success { color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-danger { color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .thead-dark th { background-color: #343a40 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .progress { display: none !important; }
    
    /* Page setup */
    @page {
        size: A4 landscape;
        margin: 5mm;
    }
}

.badge-lg {
    font-size: 14px;
    padding: 8px 12px;
}
</style>
@endsection
