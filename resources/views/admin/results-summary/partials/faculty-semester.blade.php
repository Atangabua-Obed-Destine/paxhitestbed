{{-- One semester's faculty summary: what the page shows for that semester alone. --}}
@if(!empty($show_heading))
            <div class="col-sm-12">
                <h4 class="mt-3 mb-2 semester-block-heading" data-semester-block="{{ $semester->id }}"><i class="fas fa-bookmark"></i> {{ $semester->title }}</h4>
            </div>
@endif
            <!-- Report Header -->
            <div class="col-sm-12" id="printable-report{{ $bk }}">
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
                            <table class="table table-bordered table-striped" id="faculty-results-table{{ $bk }}">
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
                        <canvas id="departmentPerformanceChart{{ $bk }}" height="300"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> {{ __('Scripts Distribution by Department') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="scriptsDistributionChart{{ $bk }}" height="300"></canvas>
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

@if(isset($department_summaries) && count($department_summaries) > 0)
@push('scripts')
<script type="text/javascript">
(function () {
"use strict";

// Department Performance Chart
var perfCtx = document.getElementById('departmentPerformanceChart{{ $bk }}').getContext('2d');
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
var distCtx = document.getElementById('scriptsDistributionChart{{ $bk }}').getContext('2d');
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
})();
</script>
@endpush
@endif
