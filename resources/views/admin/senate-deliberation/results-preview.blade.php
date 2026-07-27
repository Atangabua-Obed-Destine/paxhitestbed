@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    .senate-tabs .nav-link { font-weight: 600; font-size: 13px; padding: 10px 18px; }
    .senate-tabs .nav-link.active { background: #2c3e50; color: #fff !important; border-color: #2c3e50; }
    .senate-tabs .nav-link i { margin-right: 5px; }
    .kpi-card { border-radius: 8px; transition: transform 0.2s; border: 0; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.12); }
    .kpi-card .kpi-value { font-size: 28px; font-weight: 700; margin-bottom: 2px; }
    .kpi-card .kpi-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
    .faculty-accordion .card { border-radius: 6px; margin-bottom: 8px; border: 1px solid #dee2e6; }
    .faculty-accordion .card-header { cursor: pointer; background: #f8f9fa; transition: background 0.2s; }
    .faculty-accordion .card-header:hover { background: #e9ecef; }
    .faculty-accordion .card-header h6 { margin: 0; font-weight: 600; }
    .dept-section { background: #fafbfc; border-radius: 6px; padding: 16px; margin-bottom: 14px; border-left: 4px solid #3498db; }
    .dept-section h6 { color: #2c3e50; font-weight: 700; }
    .course-table { font-size: 11.5px; }
    .course-table th { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; }
    .course-table td { vertical-align: middle; }
    .grade-cell { text-align: center; min-width: 28px; font-size: 10.5px; padding: 2px 3px !important; }
    .course-table .pass-rate-high { color: #28a745; font-weight: 700; }
    .course-table .pass-rate-mid { color: #ffc107; font-weight: 700; }
    .course-table .pass-rate-low { color: #dc3545; font-weight: 700; }
    .lecturer-card { border-radius: 8px; border: 1px solid #e9ecef; transition: all 0.2s; }
    .lecturer-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,.1); }
    .lecturer-rank { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
    .performance-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
    .performance-bar .fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
    .top-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; }
    .student-matrix-container { max-height: 600px; overflow: auto; }
    .student-matrix { font-size: 10.5px; }
    .student-matrix th { font-size: 10px; position: sticky; top: 0; z-index: 2; }
    .student-matrix td { padding: 3px 5px !important; white-space: nowrap; text-align: center; }
    .student-matrix .student-name { text-align: left; white-space: nowrap; min-width: 150px; }
    .student-matrix .gpa-cell { font-weight: 700; }
    .status-P { color: #28a745; font-weight: 600; }
    .status-F { color: #dc3545; font-weight: 600; }
    .status-NR { color: #adb5bd; }
    .status-NP, .status-NS { color: #6c757d; font-style: italic; }
    .readiness-ring { width: 100px; height: 100px; position: relative; display: inline-block; }
    .summary-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 2px; }
    .coverage-bar { width: 60px; height: 6px; background: #e9ecef; border-radius: 3px; display: inline-block; vertical-align: middle; }
    .coverage-bar .fill { height: 100%; border-radius: 3px; }
    @media print {
        .no-print, .page-header, .pcoded-header, .pcoded-sidebar, .card-header form { display: none !important; }
        .tab-content > .tab-pane { display: block !important; opacity: 1 !important; page-break-before: always; }
        .faculty-accordion .collapse { display: block !important; }
        .course-table { font-size: 9px; }
        body { font-size: 10px; }
    }
</style>
@endsection

@section('content')

{{-- ═══ PAGE HEADER ═══ --}}
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <div class="page-header-title">
                <i class="fas fa-university bg-c-blue"></i>
                <div class="d-inline">
                    <h5>{{ $title }}</h5>
                    <span>Comprehensive results preview for senate review</span>
                </div>
            </div>
        </div>
        <div class="col-md-6 text-right no-print">
            @if(isset($kpis))
            <a href="{{ route('admin.senate-deliberation.export-results-preview', ['session' => $selected_session, 'semester' => $selected_semester, 'faculty' => $selected_faculty ?? '0']) }}" class="btn btn-success btn-sm mr-2">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <button class="btn btn-outline-dark btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            @endif
        </div>
    </div>
</div>

{{-- ═══ FILTER CARD ═══ --}}
<div class="card no-print">
    <div class="card-header">
        <h5><i class="fas fa-filter"></i> {{ __('Select Academic Period') }}</h5>
    </div>
    <div class="card-block">
        <form method="GET" action="{{ route('admin.senate-deliberation.results-preview') }}">
            <div class="row gx-2">
                <div class="col-md-3">
                    <label class="form-label fw-bold">{{ __('Session') }} <span class="text-danger">*</span></label>
                    <select name="session" class="form-control" required>
                        <option value="0">{{ __('Select Session') }}</option>
                        @foreach($sessions as $s)
                        <option value="{{ $s->id }}" {{ $selected_session == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">{{ __('Semester') }} <span class="text-danger">*</span></label>
                    <select name="semester" class="form-control" required>
                        <option value="0">{{ __('Select Semester') }}</option>
                        @foreach($semesters as $sem)
                        <option value="{{ $sem->id }}" {{ $selected_semester == $sem->id ? 'selected' : '' }}>{{ $sem->title }}{{ !empty($sem->is_resit) ? ' (Resit)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">{{ __('Faculty') }} <small class="text-muted">(optional)</small></label>
                    <select name="faculty" class="form-control">
                        <option value="0">{{ __('All Faculties') }}</option>
                        @foreach($faculties as $fac)
                        <option value="{{ $fac->id }}" {{ $selected_faculty == $fac->id ? 'selected' : '' }}>{{ $fac->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> {{ __('Generate Preview') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@if(isset($kpis))

{{-- ═══ REPORT HEADER (printable) ═══ --}}
<div class="text-center mb-3 d-none d-print-block">
    <h4 class="mb-1"><strong>{{ $institution_name ?? config('app.name') }}</strong></h4>
    <h5>SENATE RESULTS REVIEW — COMPREHENSIVE PREVIEW</h5>
    <p class="mb-0">{{ $session_label }} &bull; {{ $semester_label }}
        @if($selected_faculty !== '0') &bull; {{ $faculties->firstWhere('id', $selected_faculty)->title ?? '' }} @endif
    </p>
    <small class="text-muted">Generated: {{ now()->format('d F Y, H:i') }}</small>
</div>

{{-- ═══ KPI CARDS ═══ --}}
<div class="row mb-3">
    <div class="col-md-2 col-sm-4 col-6 mb-2">
        <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;">
            <div class="kpi-value">{{ number_format($kpis['total_scripts']) }}</div>
            <div class="kpi-label">Total Scripts</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6 mb-2">
        <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;">
            <div class="kpi-value">{{ number_format($kpis['passed_scripts']) }}</div>
            <div class="kpi-label">Passed</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6 mb-2">
        <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#eb3349,#f45c43);color:#fff;">
            <div class="kpi-value">{{ number_format($kpis['failed_scripts']) }}</div>
            <div class="kpi-label">Failed</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6 mb-2">
        <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#36d1dc,#5b86e5);color:#fff;">
            <div class="kpi-value">{{ $kpis['pass_rate'] }}%</div>
            <div class="kpi-label">Pass Rate</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6 mb-2">
        <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff;">
            <div class="kpi-value">{{ number_format($kpis['average_mark'], 1) }}</div>
            <div class="kpi-label">Avg Mark</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6 mb-2">
        <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#4facfe,#00f2fe);color:#fff;">
            <div class="kpi-value">{{ number_format($kpis['unique_courses']) }}</div>
            <div class="kpi-label">Courses</div>
        </div>
    </div>
</div>

{{-- ═══ TABBED NAVIGATION ═══ --}}
<ul class="nav nav-tabs senate-tabs mb-0 no-print" id="senateResultsTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-overview" data-bs-toggle="tab" href="#pane-overview" role="tab">
            <i class="fas fa-chart-pie"></i> Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-faculty" data-bs-toggle="tab" href="#pane-faculty" role="tab">
            <i class="fas fa-university"></i> Faculty Report
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-courses" data-bs-toggle="tab" href="#pane-courses" role="tab">
            <i class="fas fa-book"></i> Course Results
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-lecturers" data-bs-toggle="tab" href="#pane-lecturers" role="tab">
            <i class="fas fa-chalkboard-teacher"></i> Lecturer Performance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-students" data-bs-toggle="tab" href="#pane-students" role="tab">
            <i class="fas fa-users"></i> Student Matrix
        </a>
    </li>
</ul>

<div class="tab-content" id="senateResultsContent">

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB 1: OVERVIEW
   ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
    <div class="card" style="border-top:0; border-top-left-radius:0; border-top-right-radius:0;">
        <div class="card-block">

            <div class="row">
                {{-- Publishing Readiness --}}
                <div class="col-md-4 mb-3">
                    <div class="card border h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center pb-0 border-bottom-0">
                            <h6 class="mb-0"><i class="fas fa-broadcast-tower text-primary"></i> Publishing Readiness</h6>
                        </div>
                        <div class="card-header bg-white pt-2 pb-0">
                            <ul class="nav nav-tabs card-header-tabs" id="pubReadinessTypeTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="tab-pub-ca" data-bs-toggle="tab" data-toggle="tab" href="#pane-pub-ca" role="tab">CA</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="tab-pub-final" data-bs-toggle="tab" data-toggle="tab" href="#pane-pub-final" role="tab">Final Exam</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body text-center tab-content p-2">
                            <!-- CA Pane -->
                            <div class="tab-pane fade show active" id="pane-pub-ca" role="tabpanel">
                                <div style="position:relative; height:160px;">
                                    <canvas id="caPublishingDonutChart"></canvas>
                                </div>
                                <div class="mt-2">
                                    <span class="summary-pill" style="background:#d4edda;color:#155724;">Published: {{ $publishing_summary_ca['published'] }}</span>
                                    <span class="summary-pill" style="background:#d1ecf1;color:#0c5460;">Approved: {{ $publishing_summary_ca['approved'] }}</span>
                                    <span class="summary-pill" style="background:#fff3cd;color:#856404;">Checked: {{ $publishing_summary_ca['checked'] }}</span>
                                    <span class="summary-pill" style="background:#f8d7da;color:#721c24;">Draft: {{ $publishing_summary_ca['draft'] }}</span>
                                </div>
                                <h4 class="mt-2 mb-0">{{ $publishing_summary_ca['readiness'] }}% Ready</h4>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#caPublishingDetailsModal">
                                    View CA Details
                                </button>
                            </div>

                            <!-- Final Pane -->
                            <div class="tab-pane fade" id="pane-pub-final" role="tabpanel">
                                <div style="position:relative; height:160px;">
                                    <canvas id="finalPublishingDonutChart"></canvas>
                                </div>
                                <div class="mt-2">
                                    <span class="summary-pill" style="background:#d4edda;color:#155724;">Published: {{ $publishing_summary_final['published'] }}</span>
                                    <span class="summary-pill" style="background:#d1ecf1;color:#0c5460;">Approved: {{ $publishing_summary_final['approved'] }}</span>
                                    <span class="summary-pill" style="background:#fff3cd;color:#856404;">Checked: {{ $publishing_summary_final['checked'] }}</span>
                                    <span class="summary-pill" style="background:#f8d7da;color:#721c24;">Draft: {{ $publishing_summary_final['draft'] }}</span>
                                </div>
                                <h4 class="mt-2 mb-0">{{ $publishing_summary_final['readiness'] }}% Ready</h4>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#finalPublishingDetailsModal">
                                    View Final Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Academic Standing Distribution --}}
                <div class="col-md-4 mb-3">
                    <div class="card border h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-graduation-cap text-success"></i> Academic Standing</h6>
                        </div>
                        <div class="card-body">
                            @if($standings_computed)
                            <div style="position:relative; height:180px;">
                                <canvas id="standingBarChart"></canvas>
                            </div>
                            <div class="mt-2 text-center" style="font-size:11px;">
                                @php $totalSt = array_sum($standing_distribution); @endphp
                                <span class="summary-pill" style="background:#e3f2fd;color:#1565c0;">
                                    <i class="fas fa-star"></i> Dean's: {{ $standing_distribution['deans_list'] ?? 0 }}
                                </span>
                                <span class="summary-pill" style="background:#e8f5e9;color:#2e7d32;">
                                    Good: {{ $standing_distribution['good_standing'] ?? 0 }}
                                </span>
                                <span class="summary-pill" style="background:#fff8e1;color:#f57f17;">
                                    Warn: {{ $standing_distribution['academic_warning'] ?? 0 }}
                                </span>
                                <span class="summary-pill" style="background:#fbe9e7;color:#e65100;">
                                    Prob: {{ $standing_distribution['academic_probation'] ?? 0 }}
                                </span>
                                <span class="summary-pill" style="background:#ffebee;color:#c62828;">
                                    Dismiss: {{ $standing_distribution['recommended_dismissal'] ?? 0 }}
                                </span>
                            </div>
                            @else
                            <div class="text-center py-4">
                                <i class="fas fa-exclamation-triangle text-warning fa-3x mb-2"></i>
                                <p class="text-muted">Standings not yet computed.</p>
                                <a href="{{ route('admin.senate-deliberation.academic-standings', ['session' => $selected_session, 'semester' => $selected_semester]) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calculator"></i> Generate Standings
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Top & Bottom Courses --}}
                <div class="col-md-4 mb-3">
                    <div class="card border h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-trophy text-warning"></i> Course Performance Highlights</h6>
                        </div>
                        <div class="card-body p-2" style="font-size:11.5px; max-height:360px; overflow-y:auto;">
                            @if(count($top_courses) > 0)
                            <p class="mb-1 px-2"><strong class="text-success"><i class="fas fa-arrow-up"></i> Top Courses</strong></p>
                            <table class="table table-sm mb-2">
                                @foreach(array_slice($top_courses, 0, 5) as $tc)
                                <tr>
                                    <td><strong>{{ $tc['course_code'] }}</strong></td>
                                    <td class="text-success font-weight-bold">{{ $tc['pass_rate'] }}%</td>
                                    <td class="text-muted">{{ $tc['lecturers'] }}</td>
                                </tr>
                                @endforeach
                            </table>
                            @endif
                            @if(count($bottom_courses) > 0)
                            <p class="mb-1 px-2"><strong class="text-danger"><i class="fas fa-arrow-down"></i> Courses Needing Attention</strong></p>
                            <table class="table table-sm mb-0">
                                @foreach(array_slice($bottom_courses, 0, 5) as $bc)
                                <tr>
                                    <td><strong>{{ $bc['course_code'] }}</strong></td>
                                    <td class="text-danger font-weight-bold">{{ $bc['pass_rate'] }}%</td>
                                    <td class="text-muted">{{ $bc['lecturers'] }}</td>
                                </tr>
                                @endforeach
                            </table>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB 2: FACULTY REPORT
   ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade" id="pane-faculty" role="tabpanel">
    <div class="card" style="border-top:0; border-top-left-radius:0; border-top-right-radius:0;">
        <div class="card-block">

            @if(isset($student_performance_summaries) && count($student_performance_summaries) > 0)
            @php
                $totCourses = 0; $totReg = 0; $totExam = 0; $totPass = 0; $totFail = 0; $totScripts = 0;
                foreach($student_performance_summaries as $ss) {
                    $totCourses += $ss['courses_examined'];
                    $totReg += $ss['registered'];
                    $totExam += $ss['examined'];
                    $totPass += $ss['passed'];
                    $totFail += $ss['failed'];
                    $totScripts += $ss['scripts_marked'];
                }
                $totPassRate = $totExam > 0 ? round(($totPass / $totExam) * 100, 1) : 0;
                $totFailRate = $totExam > 0 ? round(($totFail / $totExam) * 100, 1) : 0;
                $uniqueCourses = collect($faculty_student_matrices ?? [])->flatMap(fn($f) => collect($f['programs'] ?? [])->flatMap(fn($p) => $p['subjects'] ?? []))->unique('id')->count();
            @endphp
            
            <div class="mb-4">
                <h5 class="text-center font-weight-bold text-uppercase mb-3" style="font-size: 1.1rem; line-height: 1.5; color: #333;">
                    SUMMARY OF RESULTS FOR THE {{ strtoupper($semester_label ?? 'First Semester') }} EXAMINATION {{ $session_label ?? '2025/2026' }}
                </h5>
                <p class="text-justify mb-3" style="font-size: 1.05rem; color: #444;">
                    For the four schools, a total of <strong>{{ $uniqueCourses }}</strong> courses were examined, with <strong>{{ number_format($totScripts) }}</strong> scripts marked, <strong>{{ number_format($totPass) }}</strong> passed and <strong>{{ number_format($totFail) }}</strong> failed giving a percentage of passed of <strong>{{ $totPassRate }}</strong> and percentage failed of <strong>{{ $totFailRate }}</strong>.
                </p>
                
                <h6 class="mb-2 text-dark">Table 1: {{ $semester_label ?? 'First Semester' }} Statistics {{ $session_label ?? '2025/2026' }}</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm course-table" style="border: 1px solid #dee2e6;">
                        <thead class="bg-light text-dark">
                            <tr>
                                <th>Faculties/Schools</th>
                                <th class="text-center">No of courses examined</th>
                                <th class="text-center">No Registered</th>
                                <th class="text-center">No Examined</th>
                                <th class="text-center">No Passed</th>
                                <th class="text-center">No Failed</th>
                                <th class="text-center">% Passed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($student_performance_summaries as $ss)
                            <tr>
                                <td class="font-weight-bold">{{ $ss['shortcode'] ?: $ss['name'] }}</td>
                                <td class="text-center">{{ $ss['courses_examined'] }}</td>
                                <td class="text-center">{{ $ss['registered'] }}</td>
                                <td class="text-center">{{ $ss['examined'] }}</td>
                                <td class="text-center">{{ $ss['passed'] }}</td>
                                <td class="text-center">{{ $ss['failed'] }}</td>
                                <td class="text-center">{{ $ss['pass_rate'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td>Total</td>
                                <td class="text-center">{{ $totCourses }}</td>
                                <td class="text-center">{{ $totReg }}</td>
                                <td class="text-center">{{ $totExam }}</td>
                                <td class="text-center">{{ $totPass }}</td>
                                <td class="text-center">{{ $totFail }}</td>
                                <td class="text-center"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <hr class="my-4" style="border-top: 2px dashed #ddd;">
            @endif

            @if(count($faculty_summaries) > 0)
            {{-- Faculty Summary Table (Script-based) --}}
            <h6 class="mb-3"><i class="fas fa-university"></i> Faculty Performance Summary</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm course-table">
                    <thead class="thead-dark">
                        <tr>
                            <th>S/N</th>
                            <th>Faculty</th>
                            <th class="text-center">Programmes</th>
                            <th class="text-center">Students</th>
                            <th class="text-center">Courses</th>
                            <th class="text-center">Scripts</th>
                            <th class="text-center">Passed</th>
                            <th class="text-center">Failed</th>
                            <th class="text-center">% Pass</th>
                            <th class="text-center">% Fail</th>
                            <th class="text-center" style="width:120px;">Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $fsn = 1; $ftScripts=0; $ftPassed=0; $ftFailed=0; @endphp
                        @foreach($faculty_summaries as $fs)
                        @php $ftScripts+=$fs['scripts_written']; $ftPassed+=$fs['passed']; $ftFailed+=$fs['failed']; @endphp
                        <tr>
                            <td>{{ $fsn++ }}</td>
                            <td><strong>{{ $fs['name'] }}</strong> <small class="text-muted">({{ $fs['shortcode'] }})</small></td>
                            <td class="text-center">{{ $fs['program_count'] }}</td>
                            <td class="text-center">{{ number_format($fs['student_count']) }}</td>
                            <td class="text-center">{{ $fs['courses_offered'] }}</td>
                            <td class="text-center">{{ number_format($fs['scripts_written']) }}</td>
                            <td class="text-center text-success">{{ number_format($fs['passed']) }}</td>
                            <td class="text-center text-danger">{{ number_format($fs['failed']) }}</td>
                            <td class="text-center {{ $fs['pass_rate'] >= 70 ? 'pass-rate-high' : ($fs['pass_rate'] >= 50 ? 'pass-rate-mid' : 'pass-rate-low') }}">{{ $fs['pass_rate'] }}%</td>
                            <td class="text-center">{{ $fs['fail_rate'] }}%</td>
                            <td>
                                <div class="performance-bar">
                                    <div class="fill" style="width:{{ $fs['pass_rate'] }}%;background:{{ $fs['pass_rate'] >= 70 ? '#28a745' : ($fs['pass_rate'] >= 50 ? '#ffc107' : '#dc3545') }};"></div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="thead-light">
                        <tr class="font-weight-bold">
                            <td colspan="5" class="text-right">TOTAL</td>
                            <td class="text-center">{{ number_format($ftScripts) }}</td>
                            <td class="text-center text-success">{{ number_format($ftPassed) }}</td>
                            <td class="text-center text-danger">{{ number_format($ftFailed) }}</td>
                            <td class="text-center">{{ $ftScripts > 0 ? round(($ftPassed/$ftScripts)*100,1) : 0 }}%</td>
                            <td class="text-center">{{ $ftScripts > 0 ? round(($ftFailed/$ftScripts)*100,1) : 0 }}%</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Department Breakdown per Faculty --}}
            <h6 class="mt-4 mb-3"><i class="fas fa-sitemap"></i> Department Breakdown</h6>
            @foreach($faculty_summaries as $fs)
                @if(count($fs['departments']) > 0)
                <div class="dept-section mb-3">
                    <h6><i class="fas fa-university"></i> {{ $fs['name'] }} ({{ $fs['shortcode'] }})</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm course-table mb-0">
                            <thead style="background:#34495e; color:#fff;">
                                <tr>
                                    <th>S/N</th>
                                    <th>Department</th>
                                    <th class="text-center">HOD</th>
                                    <th class="text-center">Courses</th>
                                    <th class="text-center">Scripts</th>
                                    <th class="text-center">Pass</th>
                                    <th class="text-center">Fail</th>
                                    <th class="text-center">% Pass</th>
                                    <th class="text-center">% Fail</th>
                                    <th style="width:100px;">Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($fs['departments'] as $di => $dept)
                                <tr>
                                    <td>{{ $di + 1 }}</td>
                                    <td><strong>{{ $dept['name'] }}</strong></td>
                                    <td class="text-center" style="font-size:10.5px;">{{ $dept['head_name'] }}</td>
                                    <td class="text-center">{{ $dept['courses_offered'] }}</td>
                                    <td class="text-center">{{ number_format($dept['scripts_written']) }}</td>
                                    <td class="text-center text-success">{{ $dept['passed'] }}</td>
                                    <td class="text-center text-danger">{{ $dept['failed'] }}</td>
                                    <td class="text-center {{ $dept['pass_rate'] >= 70 ? 'pass-rate-high' : ($dept['pass_rate'] >= 50 ? 'pass-rate-mid' : 'pass-rate-low') }}">{{ $dept['pass_rate'] }}%</td>
                                    <td class="text-center">{{ $dept['fail_rate'] }}%</td>
                                    <td>
                                        <div class="performance-bar">
                                            <div class="fill" style="width:{{ $dept['pass_rate'] }}%;background:{{ $dept['pass_rate'] >= 70 ? '#28a745' : ($dept['pass_rate'] >= 50 ? '#ffc107' : '#dc3545') }};"></div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            @endforeach

            {{-- Faculty Comparison Chart --}}
            <div class="row mt-3">
                <div class="col-md-6">
                    <div style="position:relative; height:250px;">
                        <canvas id="facultyComparisonChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="position:relative; height:250px;">
                        <canvas id="facultyScriptsChart"></canvas>
                    </div>
                </div>
            </div>

            @else
            <div class="text-center py-5">
                <i class="fas fa-info-circle text-muted fa-3x mb-2"></i>
                <p class="text-muted">No faculty data available for the selected period.</p>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB 3: COURSE RESULTS (Department Report style — all faculties)
   ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade" id="pane-courses" role="tabpanel">
    <div class="card" style="border-top:0; border-top-left-radius:0; border-top-right-radius:0;">
        <div class="card-block">

            @if(count($faculty_course_data) > 0)
                @php $gradeColTitles = $grades->pluck('title')->toArray(); @endphp

                <div class="accordion faculty-accordion" id="courseAccordion">
                @foreach($faculty_course_data as $fi => $facCD)
                    <div class="card">
                        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#coursesFac{{ $facCD['id'] }}">
                            <h6>
                                <i class="fas fa-chevron-down mr-2"></i>
                                {{ $facCD['name'] }} ({{ $facCD['shortcode'] }})
                                <span class="badge badge-primary float-right">{{ count($facCD['departments']) }} Dept(s)</span>
                            </h6>
                        </div>
                        <div id="coursesFac{{ $facCD['id'] }}" class="collapse {{ $fi === 0 ? 'show' : '' }}" data-parent="#courseAccordion">
                            <div class="card-body p-3">

                                @foreach($facCD['departments'] as $deptCD)
                                <div class="dept-section">
                                    <h6 class="mb-1">
                                        <i class="fas fa-building"></i> {{ $deptCD['name'] }} ({{ $deptCD['shortcode'] }})
                                        <small class="text-muted float-right">HOD: {{ $deptCD['head_name'] }}</small>
                                    </h6>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped table-sm course-table mb-1">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th rowspan="2">S/N</th>
                                                    <th rowspan="2">Code</th>
                                                    <th rowspan="2">Course Title</th>
                                                    <th rowspan="2" class="text-center">CV</th>
                                                    <th rowspan="2" class="text-center">ST</th>
                                                    <th rowspan="2">Lecturer(s)</th>
                                                    <th rowspan="2" class="text-center">%CC</th>
                                                    <th colspan="2" class="text-center">Number</th>
                                                    <th colspan="2" class="text-center">Results</th>
                                                    <th colspan="4" class="text-center">Percentage</th>
                                                    <th colspan="{{ count($gradeColTitles) }}" class="text-center">Grade Distribution</th>
                                                    <th rowspan="2" class="text-center">Avg</th>
                                                </tr>
                                                <tr>
                                                    <th class="text-center">CR</th>
                                                    <th class="text-center">CE</th>
                                                    <th class="text-center">Pass</th>
                                                    <th class="text-center">Fail</th>
                                                    <th class="text-center" title="CA Pass Rate">%CA</th>
                                                    <th class="text-center" title="Exam Pass Rate">%Exam</th>
                                                    <th class="text-center" title="Overall Pass Rate">%Tot</th>
                                                    <th class="text-center" title="Fail Rate">%F</th>
                                                    @foreach($gradeColTitles as $gt)
                                                    <th class="grade-cell">{{ $gt }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $csn = 1; $dtReg=0; $dtExam=0; $dtPass=0; $dtFail=0; @endphp
                                                @foreach($deptCD['courses'] as $crs)
                                                @php $dtReg+=$crs['candidates_registered']; $dtExam+=$crs['candidates_examined']; $dtPass+=$crs['passed']; $dtFail+=$crs['failed']; @endphp
                                                <tr>
                                                    <td>{{ $csn++ }}</td>
                                                    <td><strong>{{ $crs['code'] }}</strong></td>
                                                    <td title="{{ $crs['title'] }}" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;">{{ $crs['title'] }}</td>
                                                    <td class="text-center">{{ $crs['credit_value'] }}</td>
                                                    <td class="text-center">{{ $crs['type'] }}</td>
                                                    <td style="font-size:10px;max-width:140px;overflow:hidden;text-overflow:ellipsis;" title="{{ $crs['lecturers'] }}">{{ $crs['lecturers'] }}</td>
                                                    <td class="text-center">
                                                        <div class="coverage-bar">
                                                            <div class="fill" style="width:{{ $crs['coverage'] }}%;background:{{ $crs['coverage'] >= 80 ? '#28a745' : ($crs['coverage'] >= 60 ? '#ffc107' : '#dc3545') }}"></div>
                                                        </div>
                                                        <small>{{ $crs['coverage'] }}%</small>
                                                    </td>
                                                    <td class="text-center">{{ $crs['candidates_registered'] }}</td>
                                                    <td class="text-center">{{ $crs['candidates_examined'] }}</td>
                                                    <td class="text-center text-success font-weight-bold">{{ $crs['passed'] }}</td>
                                                    <td class="text-center text-danger font-weight-bold">{{ $crs['failed'] }}</td>
                                                    <td class="text-center text-muted">{{ $crs['ca_pass_rate'] ?? 0 }}%</td>
                                                    <td class="text-center text-muted">{{ $crs['exam_pass_rate'] ?? 0 }}%</td>
                                                    <td class="text-center {{ $crs['pass_rate'] >= 70 ? 'pass-rate-high' : ($crs['pass_rate'] >= 50 ? 'pass-rate-mid' : 'pass-rate-low') }}">{{ $crs['pass_rate'] }}%</td>
                                                    <td class="text-center">{{ $crs['fail_rate'] }}%</td>
                                                    @foreach($gradeColTitles as $gt)
                                                    <td class="grade-cell">{{ $crs['grade_distribution'][$gt] ?? 0 }}</td>
                                                    @endforeach
                                                    <td class="text-center font-weight-bold">{{ $crs['average_marks'] }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="thead-light">
                                                <tr class="font-weight-bold">
                                                    <td colspan="7" class="text-right">TOTAL / AVERAGE</td>
                                                    <td class="text-center">{{ $dtReg }}</td>
                                                    <td class="text-center">{{ $dtExam }}</td>
                                                    <td class="text-center text-success">{{ $dtPass }}</td>
                                                    <td class="text-center text-danger">{{ $dtFail }}</td>
                                                    <td class="text-center">-</td>
                                                    <td class="text-center">-</td>
                                                    <td class="text-center">{{ $dtExam > 0 ? round(($dtPass/$dtExam)*100,1) : 0 }}%</td>
                                                    <td class="text-center">{{ $dtExam > 0 ? round(($dtFail/$dtExam)*100,1) : 0 }}%</td>
                                                    <td colspan="{{ count($gradeColTitles) + 1 }}"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                @endforeach

                            </div>
                        </div>
                    </div>
                @endforeach
                </div>

                {{-- Legend --}}
                <div class="mt-3 p-3" style="background:#f8f9fa; border-radius:6px; font-size:11px;">
                    <strong>Legend:</strong>
                    CV = Credit Value &bull; ST = Status (C=Compulsory, E=Elective, UR=University Requirement) &bull;
                    %CC = Course Coverage &bull; CR = Candidates Registered &bull; CE = Candidates Examined &bull;
                    %P = Percentage Passed &bull; %F = Percentage Failed &bull; Avg = Average Marks
                </div>

            @else
            <div class="text-center py-5">
                <i class="fas fa-info-circle text-muted fa-3x mb-2"></i>
                <p class="text-muted">No course results data available for the selected period.</p>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB 4: LECTURER PERFORMANCE
   ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade" id="pane-lecturers" role="tabpanel">
    <div class="card" style="border-top:0; border-top-left-radius:0; border-top-right-radius:0;">
        <div class="card-block">

            @if(count($lecturer_performance) > 0)

            {{-- Performance Summary Stats --}}
            @php
                $lpTotal = count($lecturer_performance);
                $lpOutstanding = collect($lecturer_performance)->where('aggregate.rating', 'Outstanding')->count();
                $lpGood = collect($lecturer_performance)->where('aggregate.rating', 'Good')->count();
                $lpSatisfactory = collect($lecturer_performance)->where('aggregate.rating', 'Satisfactory')->count();
                $lpNeedsImprovement = collect($lecturer_performance)->where('aggregate.rating', 'Needs Improvement')->count();
                $lpCritical = collect($lecturer_performance)->where('aggregate.rating', 'Critical')->count();
            @endphp
            <div class="row mb-3">
                <div class="col text-center">
                    <span class="summary-pill" style="background:#d4edda;color:#155724;">
                        <i class="fas fa-star"></i> Outstanding: {{ $lpOutstanding }}
                    </span>
                    <span class="summary-pill" style="background:#d1ecf1;color:#0c5460;">
                        <i class="fas fa-thumbs-up"></i> Good: {{ $lpGood }}
                    </span>
                    <span class="summary-pill" style="background:#cce5ff;color:#004085;">
                        <i class="fas fa-check"></i> Satisfactory: {{ $lpSatisfactory }}
                    </span>
                    <span class="summary-pill" style="background:#fff3cd;color:#856404;">
                        <i class="fas fa-exclamation-triangle"></i> Needs Improvement: {{ $lpNeedsImprovement }}
                    </span>
                    <span class="summary-pill" style="background:#f8d7da;color:#721c24;">
                        <i class="fas fa-times-circle"></i> Critical: {{ $lpCritical }}
                    </span>
                </div>
            </div>

            {{-- Lecturer Rankings Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-sm course-table" id="lecturerTable">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width:45px;">Rank</th>
                            <th>Lecturer</th>
                            <th>Faculty / Department</th>
                            <th class="text-center">Courses</th>
                            <th class="text-center">Scripts</th>
                            <th class="text-center">Passed</th>
                            <th class="text-center">Failed</th>
                            <th class="text-center">Pass Rate</th>
                            <th class="text-center">Avg Mark</th>
                            <th class="text-center" style="width:100px;">Performance</th>
                            <th class="text-center">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lecturer_performance as $lp)
                        <tr class="lecturer-row" data-lecturer-id="{{ $lp['id'] }}">
                            <td class="text-center">
                                @if($lp['rank'] <= 3)
                                <span class="lecturer-rank" style="background:{{ $lp['rank']==1 ? '#ffd700' : ($lp['rank']==2 ? '#c0c0c0' : '#cd7f32') }}; color:#fff;">
                                    {{ $lp['rank'] }}
                                </span>
                                @else
                                <span class="text-muted">{{ $lp['rank'] }}</span>
                                @endif
                            </td>
                            <td><strong>{{ $lp['name'] }}</strong></td>
                            <td style="font-size:10.5px;">{{ $lp['faculty'] }}<br><small class="text-muted">{{ $lp['department'] }}</small></td>
                            <td class="text-center">{{ $lp['aggregate']['course_count'] }}</td>
                            <td class="text-center">{{ number_format($lp['aggregate']['total_scripts']) }}</td>
                            <td class="text-center text-success">{{ number_format($lp['aggregate']['passed']) }}</td>
                            <td class="text-center text-danger">{{ number_format($lp['aggregate']['failed']) }}</td>
                            <td class="text-center {{ $lp['aggregate']['pass_rate'] >= 70 ? 'pass-rate-high' : ($lp['aggregate']['pass_rate'] >= 50 ? 'pass-rate-mid' : 'pass-rate-low') }}">
                                {{ $lp['aggregate']['pass_rate'] }}%
                            </td>
                            <td class="text-center font-weight-bold">{{ $lp['aggregate']['average_mark'] }}</td>
                            <td>
                                <div class="performance-bar">
                                    <div class="fill" style="width:{{ $lp['aggregate']['pass_rate'] }}%;background:var(--{{ $lp['aggregate']['rating_class'] == 'success' ? 'green' : ($lp['aggregate']['rating_class'] == 'danger' ? 'red' : 'blue') }}, {{ $lp['aggregate']['pass_rate'] >= 70 ? '#28a745' : ($lp['aggregate']['pass_rate'] >= 50 ? '#ffc107' : '#dc3545') }});"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-{{ $lp['aggregate']['rating_class'] }}">{{ $lp['aggregate']['rating'] }}</span>
                            </td>
                        </tr>
                        {{-- Expandable course detail row --}}
                        <tr class="lecturer-detail" id="lecDetail{{ $lp['id'] }}" style="display:none;">
                            <td></td>
                            <td colspan="10">
                                <table class="table table-sm table-bordered mb-0" style="font-size:10.5px;background:#f8f9fa;">
                                    <thead style="background:#e9ecef;">
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Title</th>
                                            <th class="text-center">Credits</th>
                                            <th class="text-center">Examined</th>
                                            <th class="text-center">Passed</th>
                                            <th class="text-center">Failed</th>
                                            <th class="text-center">Pass Rate</th>
                                            <th class="text-center">Average</th>
                                            <th class="text-center">Coverage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($lp['courses'] as $lc)
                                        <tr>
                                            <td><strong>{{ $lc['code'] }}</strong></td>
                                            <td>{{ $lc['title'] }}</td>
                                            <td class="text-center">{{ $lc['credit'] }}</td>
                                            <td class="text-center">{{ $lc['examined'] }}</td>
                                            <td class="text-center text-success">{{ $lc['passed'] }}</td>
                                            <td class="text-center text-danger">{{ $lc['failed'] }}</td>
                                            <td class="text-center {{ $lc['pass_rate'] >= 70 ? 'pass-rate-high' : ($lc['pass_rate'] >= 50 ? 'pass-rate-mid' : 'pass-rate-low') }}">{{ $lc['pass_rate'] }}%</td>
                                            <td class="text-center font-weight-bold">{{ $lc['average'] }}</td>
                                            <td class="text-center">
                                                <div class="coverage-bar">
                                                    <div class="fill" style="width:{{ $lc['coverage'] }}%;background:{{ $lc['coverage'] >= 80 ? '#28a745' : '#ffc107' }}"></div>
                                                </div>
                                                {{ $lc['coverage'] }}%
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Lecturer Performance Chart --}}
            <div class="row mt-3">
                <div class="col-md-6">
                    <div style="position:relative; height:220px;">
                        <canvas id="lecturerRatingChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="position:relative; height:220px;">
                        <canvas id="lecturerPassRateChart"></canvas>
                    </div>
                </div>
            </div>

            @else
            <div class="text-center py-5">
                <i class="fas fa-chalkboard-teacher text-muted fa-3x mb-2"></i>
                <p class="text-muted">No lecturer performance data available. Ensure class routines are configured.</p>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB 5: STUDENT RESULTS MATRIX (All Programmes — by Faculty)
   ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade" id="pane-students" role="tabpanel">
    <div class="card" style="border-top:0; border-top-left-radius:0; border-top-right-radius:0;">
        <div class="card-block">

            @if(isset($faculty_student_matrices) && count($faculty_student_matrices) > 0)

            {{-- Expand/Collapse All --}}
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h6 class="mb-0"><i class="fas fa-users"></i> Student Results Preview — All Programmes</h6>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="$('.matrix-faculty-collapse').collapse('show');">
                        <i class="fas fa-expand-arrows-alt"></i> Expand All
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="$('.matrix-faculty-collapse').collapse('hide');">
                        <i class="fas fa-compress-arrows-alt"></i> Collapse All
                    </button>
                    <button class="btn btn-sm btn-outline-dark" onclick="window.print();">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            @php $gradesList = $grades; @endphp

            <div class="accordion" id="studentMatrixAccordion">
            @foreach($faculty_student_matrices as $fmi => $facMatrix)
                {{-- ── FACULTY ACCORDION ── --}}
                <div class="card mb-2" style="border: 1px solid #343a40; border-radius: 6px; overflow: hidden;">
                    <div class="card-header py-2 px-3" style="background: linear-gradient(135deg, #2c3e50, #34495e); cursor: pointer;"
                         data-bs-toggle="collapse" data-bs-target="#matrixFac{{ $facMatrix['id'] }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-white">
                                <i class="fas fa-chevron-down mr-2"></i>
                                <i class="fas fa-university mr-1"></i>
                                {{ $facMatrix['name'] }}
                                <small class="text-light ml-1">({{ $facMatrix['shortcode'] }})</small>
                            </h6>
                            <div>
                                <span class="badge badge-primary">{{ count($facMatrix['programs']) }} Programme(s)</span>
                                @php
                                    $facTotalStudents = collect($facMatrix['programs'])->sum('overall.total_students');
                                    $facTotalPassed   = collect($facMatrix['programs'])->sum('overall.total_passed');
                                    $facTotalFailed   = collect($facMatrix['programs'])->sum('overall.total_failed');
                                @endphp
                                <span class="badge badge-light">{{ $facTotalStudents }} Students</span>
                                <span class="badge badge-success">{{ $facTotalPassed }} Pass</span>
                                <span class="badge badge-danger">{{ $facTotalFailed }} Fail</span>
                            </div>
                        </div>
                    </div>

                    <div id="matrixFac{{ $facMatrix['id'] }}" class="collapse matrix-faculty-collapse {{ $fmi === 0 ? 'show' : '' }}"
                         data-parent="#studentMatrixAccordion">
                        <div class="card-body p-2">

                            @foreach($facMatrix['programs'] as $pi => $progMatrix)
                            @php
                                $subjects   = $progMatrix['subjects'];
                                $students   = $progMatrix['students'];
                                $cStats     = $progMatrix['course_stats'];
                                $overall    = $progMatrix['overall'];
                                $subCount   = count($subjects);
                            @endphp

                            {{-- ── PROGRAMME CARD ── --}}
                            <div class="card border-warning mb-3" style="border-radius: 6px;">
                                {{-- Programme Header --}}
                                <div class="card-header bg-warning text-dark py-2 px-3" style="cursor:pointer;"
                                     data-bs-toggle="collapse" data-bs-target="#matrixProg{{ $progMatrix['id'] }}">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <div>
                                            <i class="fas fa-chevron-down mr-1"></i>
                                            <i class="fas fa-clipboard-list mr-1"></i>
                                            <strong>{{ $progMatrix['title'] }}</strong>
                                            <span class="badge badge-dark ml-1">{{ $progMatrix['degree'] }}</span>
                                            <small class="ml-2 text-muted">Dept: {{ $progMatrix['department'] }}</small>
                                        </div>
                                        <div style="font-size: 0.8em;">
                                            <small>
                                                <strong>{{ $overall['total_students'] }}</strong> Students
                                                &times; <strong>{{ $subCount }}</strong> Courses
                                                &nbsp;|&nbsp;
                                                <span class="text-success font-weight-bold">Pass: {{ $overall['total_passed'] }}</span>
                                                &nbsp;|&nbsp;
                                                <span class="text-danger font-weight-bold">Fail: {{ $overall['total_failed'] }}</span>
                                                @if($overall['total_pending'] > 0)
                                                &nbsp;|&nbsp;
                                                <span class="text-muted">Pending: {{ $overall['total_pending'] }}</span>
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                {{-- Draft Watermark --}}
                                <div class="alert alert-warning text-center mb-0 py-1" style="border-radius:0; font-size:0.75em;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>SENATE PREVIEW</strong> — This data is for senate review purposes.
                                    Not all results may be fully published.
                                </div>

                                <div id="matrixProg{{ $progMatrix['id'] }}" class="collapse {{ $pi === 0 ? 'show' : '' }}">
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height: 650px; overflow: auto;">
                                            <table class="table table-bordered table-sm mb-0" style="font-size: 0.8em;">

                                                {{-- ═══ TABLE HEADER — 2 ROWS ═══ --}}
                                                <thead>
                                                    {{-- Row 1: Subject groups + Summary --}}
                                                    <tr>
                                                        <th rowspan="2" class="text-center" style="min-width:35px; background:#343a40; color:#fff; position:sticky; left:0; z-index:11;">S/N</th>
                                                        <th rowspan="2" class="text-center" style="min-width:95px; background:#343a40; color:#fff; position:sticky; left:35px; z-index:11;">Mat No.</th>
                                                        <th rowspan="2" style="min-width:160px; background:#343a40; color:#fff; position:sticky; left:130px; z-index:11;">Name</th>

                                                        @foreach($subjects as $subj)
                                                        <th colspan="5" class="text-center" style="background:#495057; color:#fff; border-left:2px solid #212529; padding:3px;">
                                                            <span class="badge badge-primary" style="font-size:0.85em;">{{ $subj['code'] }}</span>
                                                            <br><small class="text-warning" style="font-size:0.7em;" title="{{ $subj['title'] }}">{{ Str::limit($subj['title'], 18) }}</small>
                                                            <br><small class="text-light">CV: {{ $subj['credit_hour'] }}</small>
                                                            @if($subj['lecturer'] !== '-')
                                                            <br><small class="text-info" style="font-size:0.6em;" title="{{ $subj['lecturer'] }}"><i class="fas fa-user-tie"></i> {{ Str::limit($subj['lecturer'], 16) }}</small>
                                                            @endif
                                                        </th>
                                                        @endforeach

                                                        <th colspan="9" class="text-center" style="background:#17a2b8; color:#fff; border-left:2px solid #212529;">
                                                            <strong>SUMMARY</strong>
                                                        </th>
                                                    </tr>

                                                    {{-- Row 2: Sub-column headers --}}
                                                    <tr>
                                                        @foreach($subjects as $subj)
                                                        <th class="text-center" style="background:#6c757d; color:#fff; border-left:2px solid #212529; min-width:30px; font-size:0.75em;" title="Attendance">Att</th>
                                                        <th class="text-center" style="background:#6c757d; color:#fff; min-width:30px; font-size:0.75em;" title="Continuous Assessment">CA</th>
                                                        <th class="text-center" style="background:#6c757d; color:#fff; min-width:30px; font-size:0.75em;" title="Final Exam">EX</th>
                                                        <th class="text-center" style="background:#6c757d; color:#fff; min-width:35px; font-size:0.75em;" title="Total Marks">TOT</th>
                                                        <th class="text-center" style="background:#6c757d; color:#fff; min-width:30px; font-size:0.75em;" title="Grade">Grd</th>
                                                        @endforeach

                                                        <th class="text-center" style="background:#138496; color:#fff; border-left:2px solid #212529; min-width:35px; font-size:0.75em;" title="Total Credits Registered">TCR</th>
                                                        <th class="text-center" style="background:#138496; color:#fff; min-width:35px; font-size:0.75em;" title="Total Credits Earned">TCE</th>
                                                        <th class="text-center" style="background:#138496; color:#fff; min-width:40px; font-size:0.75em;" title="Grade Point Average">GPA</th>
                                                        <th class="text-center" style="background:#28a745; color:#fff; min-width:30px; font-size:0.75em;" title="Courses Passed">&#10003;</th>
                                                        <th class="text-center" style="background:#dc3545; color:#fff; min-width:30px; font-size:0.75em;" title="Courses Failed">&#10007;</th>
                                                        <th class="text-center" style="background:#0d6efd; color:#fff; min-width:34px; font-size:0.75em;" title="Scheduled Resit Courses">R#</th>
                                                        <th class="text-center" style="background:#0d6efd; color:#fff; min-width:42px; font-size:0.75em;" title="Scheduled Resit Credits">RCR</th>
                                                        <th class="text-center" style="background:#fd7e14; color:#fff; min-width:38px; font-size:0.75em;" title="Carry-Over Courses">CO#</th>
                                                        <th class="text-center" style="background:#fd7e14; color:#fff; min-width:48px; font-size:0.75em;" title="Carry-Over Credits">COCR</th>
                                                    </tr>
                                                </thead>

                                                {{-- ═══ TABLE BODY — PER STUDENT ═══ --}}
                                                <tbody>
                                                @foreach($students as $stu)
                                                    @php $hasFail = $stu['summary']['courses_failed'] > 0; @endphp
                                                    <tr class="{{ $hasFail ? 'table-warning' : '' }}">
                                                        {{-- Sticky columns --}}
                                                        <td class="text-center" style="position:sticky; left:0; background:{{ $hasFail ? '#fff3cd' : '#fff' }}; z-index:1;">{{ $stu['sn'] }}</td>
                                                        <td class="text-center" style="position:sticky; left:35px; background:{{ $hasFail ? '#fff3cd' : '#fff' }}; z-index:1; font-size:0.85em;">
                                                            <strong>{{ $stu['matricule'] }}</strong>
                                                        </td>
                                                        <td style="position:sticky; left:130px; background:{{ $hasFail ? '#fff3cd' : '#fff' }}; z-index:1; white-space:nowrap;" title="{{ $stu['name'] }}">
                                                            {{ Str::limit($stu['name'], 22) }}
                                                        </td>

                                                        {{-- Per-subject columns --}}
                                                        @foreach($subjects as $subj)
                                                            @php $c = $stu['courses'][$subj['id']] ?? null; @endphp

                                                            @if(!$c || !$c['registered'])
                                                                {{-- NOT REGISTERED --}}
                                                                <td class="text-center text-muted" style="border-left:2px solid #dee2e6;">-</td>
                                                                <td class="text-center text-muted">-</td>
                                                                <td class="text-center text-muted">-</td>
                                                                <td class="text-center text-muted">-</td>
                                                                <td class="text-center text-muted">-</td>

                                                            @elseif($c['status'] === 'N/S' || $c['is_not_submitted'])
                                                                {{-- NOT SUBMITTED --}}
                                                                <td class="text-center text-warning" style="border-left:2px solid #dee2e6; font-style:italic; font-size:0.75em;">
                                                                    {{ is_numeric($c['attendance_marks']) ? number_format($c['attendance_marks'], 1) : $c['attendance_marks'] }}
                                                                </td>
                                                                <td class="text-center text-warning" style="font-style:italic; font-size:0.75em;">N/S</td>
                                                                <td class="text-center text-warning" style="font-style:italic; font-size:0.75em;">N/S</td>
                                                                <td class="text-center text-warning" style="font-style:italic; font-size:0.75em;">N/S</td>
                                                                <td class="text-center">
                                                                    <span class="badge badge-warning" style="font-size:0.7em;">N/S</span>
                                                                    @if(!empty($c['decision_label']))
                                                                        <div class="mt-1">
                                                                            <span class="badge badge-{{ $c['decision_class'] ?? 'secondary' }}" style="font-size:0.6em; white-space:normal;">{{ $c['decision_label'] }}</span>
                                                                        </div>
                                                                    @endif
                                                                </td>

                                                            @elseif($c['status'] === 'ABS' || $c['is_absent'])
                                                                {{-- ABSENT --}}
                                                                <td class="text-center text-muted" style="border-left:2px solid #dee2e6;">-</td>
                                                                <td class="text-center text-muted">-</td>
                                                                <td class="text-center text-muted">-</td>
                                                                <td class="text-center text-muted">-</td>
                                                                <td class="text-center">
                                                                    <span class="badge badge-secondary" style="font-size:0.7em;">ABS</span>
                                                                    @if(!empty($c['decision_label']))
                                                                        <div class="mt-1">
                                                                            <span class="badge badge-{{ $c['decision_class'] ?? 'secondary' }}" style="font-size:0.6em; white-space:normal;">{{ $c['decision_label'] }}</span>
                                                                        </div>
                                                                    @endif
                                                                </td>

                                                            @else
                                                                {{-- NORMAL (P or F) --}}
                                                                {{-- Attendance --}}
                                                                <td class="text-center{{ $c['attendance_marks'] === 'N/S' ? ' text-warning font-italic' : '' }}" style="border-left:2px solid #dee2e6;">
                                                                    {{ is_numeric($c['attendance_marks']) ? number_format($c['attendance_marks'], 1) : $c['attendance_marks'] }}
                                                                </td>

                                                                {{-- CA --}}
                                                                <td class="text-center{{ $c['ca_absent'] ? ' text-danger' : ($c['ca_marks'] === 'N/S' ? ' text-warning font-italic' : '') }}">
                                                                    @if($c['ca_absent'])
                                                                        <span class="badge badge-danger" style="font-size:0.7em;">ABS</span>
                                                                    @else
                                                                        {{ is_numeric($c['ca_marks']) ? number_format($c['ca_marks'], 1) : $c['ca_marks'] }}
                                                                        @if($c['has_zero_contribution'] && is_numeric($c['ca_marks']))
                                                                            <i class="fas fa-exclamation-triangle text-warning" style="font-size:0.6em;"></i>
                                                                        @endif
                                                                    @endif
                                                                </td>

                                                                {{-- Exam --}}
                                                                <td class="text-center{{ $c['final_absent'] ? ' text-danger' : ($c['exam_marks'] === 'N/S' ? ' text-warning font-italic' : '') }}">
                                                                    @if($c['final_absent'])
                                                                        <span class="badge badge-danger" style="font-size:0.7em;">ABS</span>
                                                                    @else
                                                                        {{ is_numeric($c['exam_marks']) ? number_format($c['exam_marks'], 1) : $c['exam_marks'] }}
                                                                    @endif
                                                                </td>

                                                                {{-- Total --}}
                                                                <td class="text-center font-weight-bold {{ $c['status'] === 'P' ? 'text-success' : 'text-danger' }}">
                                                                    {{ is_numeric($c['total_marks']) ? number_format($c['total_marks'], 1) : $c['total_marks'] }}
                                                                    @if($c['has_zero_contribution'] && is_numeric($c['total_marks']))
                                                                        <i class="fas fa-exclamation-triangle text-warning" style="font-size:0.6em;"></i>
                                                                    @endif
                                                                </td>

                                                                {{-- Grade --}}
                                                                <td class="text-center">
                                                                    @if($c['grade'] === 'N/S')
                                                                        <span class="badge badge-warning" style="font-size:0.75em;">N/S</span>
                                                                    @else
                                                                        <span class="badge {{ $c['status'] === 'P' ? 'badge-success' : ($c['status'] === 'F' ? 'badge-danger' : 'badge-secondary') }}" style="font-size:0.8em;"
                                                                              title="GP: {{ $c['grade_point'] }} | CV: {{ $c['credit_value'] }}{{ $c['ca_absent'] ? ' | CA: Absent' : '' }}{{ $c['final_absent'] ? ' | Exam: Absent' : '' }}{{ $c['has_zero_contribution'] ? ' | ⚠ Dist. not set' : '' }}">
                                                                            {{ $c['grade'] }}
                                                                        </span>
                                                                    @endif
                                                                    @if(!empty($c['decision_label']))
                                                                        <div class="mt-1">
                                                                            <span class="badge badge-{{ $c['decision_class'] ?? 'secondary' }}" style="font-size:0.6em; white-space:normal;">{{ $c['decision_label'] }}</span>
                                                                        </div>
                                                                    @endif
                                                                </td>
                                                            @endif
                                                        @endforeach

                                                        {{-- Summary columns --}}
                                                        <td class="text-center font-weight-bold" style="border-left:2px solid #17a2b8; background:#e8f4f8;">
                                                            {{ $stu['summary']['total_credits_registered'] }}
                                                        </td>
                                                        <td class="text-center font-weight-bold" style="background:#e8f4f8;">
                                                            {{ $stu['summary']['total_credits_earned'] }}
                                                        </td>
                                                        <td class="text-center font-weight-bold" style="background:#e8f4f8;">
                                                            <span class="badge {{ $stu['summary']['gpa'] >= 2.0 ? 'badge-success' : 'badge-warning' }}" style="font-size:0.85em;">
                                                                {{ number_format($stu['summary']['gpa'], 2) }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center text-success font-weight-bold" style="background:#d4edda;">
                                                            {{ $stu['summary']['courses_passed'] }}
                                                        </td>
                                                        <td class="text-center text-danger font-weight-bold" style="background:#f8d7da;">
                                                            {{ $stu['summary']['courses_failed'] }}
                                                        </td>
                                                        <td class="text-center font-weight-bold" style="background:#e7f1ff; color:#0d6efd;">
                                                            {{ $stu['summary']['scheduled_resit_courses'] }}
                                                        </td>
                                                        <td class="text-center font-weight-bold" style="background:#e7f1ff; color:#0d6efd;">
                                                            {{ number_format($stu['summary']['scheduled_resit_credits'], 1) }}
                                                        </td>
                                                        <td class="text-center font-weight-bold" style="background:#fff0e1; color:#c05600;">
                                                            {{ $stu['summary']['carry_over_courses'] }}
                                                        </td>
                                                        <td class="text-center font-weight-bold" style="background:#fff0e1; color:#c05600;">
                                                            {{ number_format($stu['summary']['carry_over_credits'], 1) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>

                                                {{-- ═══ TABLE FOOTER — COURSE STATS ═══ --}}
                                                <tfoot class="thead-light">
                                                    <tr>
                                                        <td colspan="3" class="text-right font-weight-bold" style="position:sticky; left:0; background:#e9ecef; z-index:1;">
                                                            Course Summary:
                                                        </td>
                                                        @foreach($subjects as $subj)
                                                            @php $cs = $cStats[$subj['id']] ?? null; @endphp
                                                            <td colspan="5" class="text-center" style="border-left:2px solid #dee2e6; font-size:0.75em;">
                                                                @if($cs)
                                                                    Reg: {{ $cs['registered'] }} | Exam: {{ $cs['examined'] }}
                                                                    <br><span class="text-success font-weight-bold">P: {{ $cs['passed'] }} ({{ $cs['pass_rate'] }}%)</span>
                                                                    <br><span class="text-danger font-weight-bold">F: {{ $cs['failed'] }}</span>
                                                                    <br><small class="text-muted">Avg: {{ $cs['average'] }}</small>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                        <td colspan="9" class="text-center" style="border-left:2px solid #17a2b8; background:#d1ecf1; font-size:0.8em;">
                                                            <strong>{{ $overall['total_students'] }} Students</strong>
                                                            <br><span class="text-success font-weight-bold">Clear: {{ $overall['total_passed'] }}</span>
                                                            <br><span class="text-danger font-weight-bold">With Fails: {{ $overall['total_failed'] }}</span>
                                                            @if($overall['total_pending'] > 0)
                                                            <br><span class="text-muted">Pending: {{ $overall['total_pending'] }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        {{-- Programme Footer Legend --}}
                                        <div class="card-footer bg-light py-2" style="font-size: 0.75em;">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <strong>Column Key:</strong>
                                                    Att = Attendance | CA = Continuous Assessment | EX = Final Exam | TOT = Total | Grd = Grade
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Summary Key:</strong>
                                                    TCR = Total Credits Registered | TCE = Total Credits Earned | GPA = Grade Point Average | R# = Scheduled Resit Courses | RCR = Scheduled Resit Credits | CO# = Carry-Over Courses | COCR = Carry-Over Credits
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Status / Decision Key:</strong>
                                                    <span class="badge badge-success" style="font-size:0.8em;">Pass</span> (≥50)
                                                    <span class="badge badge-danger" style="font-size:0.8em;">Fail</span> (<50)
                                                    <span class="badge badge-warning" style="font-size:0.8em;">N/S</span>
                                                    <span class="badge badge-secondary" style="font-size:0.8em;">ABS</span>
                                                    - = NR
                                                    <i class="fas fa-exclamation-triangle text-warning"></i> = Dist. not set
                                                    <br>
                                                    VAL = Validated | RES = Scheduled Resit | CO = Carry Over | PND = Pending Resit Decision
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <strong>Grade Scale:</strong>
                                                @foreach($gradesList as $g)
                                                    {{ $g->title }}={{ $g->point }}@if(!$loop->last), @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach

                        </div>
                    </div>
                </div>
            @endforeach
            </div>

            @else
            <div class="text-center py-5">
                <i class="fas fa-info-circle text-muted fa-3x mb-2"></i>
                <p class="text-muted">No enrolled programmes with student data found for the selected period.</p>
            </div>
            @endif

        </div>
    </div>
</div>

</div>{{-- /tab-content --}}

@else
{{-- No filters selected --}}
<div class="card">
    <div class="card-block text-center py-5">
        <i class="fas fa-search text-muted fa-4x mb-3"></i>
        <h5 class="text-muted">Select Session and Semester</h5>
        <p class="text-muted">Choose an academic session and semester above to generate the comprehensive results preview for senate review.</p>
    </div>
</div>
@endif

<!-- CA Publishing Readiness Details Modal -->
<div class="modal fade" id="caPublishingDetailsModal" tabindex="-1" role="dialog" aria-labelledby="caPublishingDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title" id="caPublishingDetailsModalLabel">CA Publishing Readiness Details</h5>
        <button type="button" class="close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
        <ul class="nav nav-tabs mb-3" id="caPubReadinessTabs" role="tablist">
            @php 
                $statesList = [
                    'draft' => 'Draft', 
                    'submitted' => 'Submitted', 
                    'checked' => 'Checked', 
                    'approved' => 'Approved', 
                    'published' => 'Published'
                ]; 
            @endphp
            @foreach($statesList as $stateKey => $stateLabel)
            <li class="nav-item">
                <a class="nav-link {{ $loop->first ? 'active' : '' }}" id="tab-pub-ca-{{ $stateKey }}" data-bs-toggle="tab" data-toggle="tab" href="#pane-pub-ca-{{ $stateKey }}" role="tab">
                    {{ $stateLabel }} <span class="badge badge-secondary">{{ count($publishing_summary_ca['details'][$stateKey] ?? []) }}</span>
                </a>
            </li>
            @endforeach
        </ul>

        <div class="tab-content" id="caPubReadinessTabsContent">
            @foreach($statesList as $stateKey => $stateLabel)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="pane-pub-ca-{{ $stateKey }}" role="tabpanel">
                @if(count($publishing_summary_ca['details'][$stateKey] ?? []) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped pub-details-table" style="width: 100%;">
                        <thead class="thead-dark">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Program</th>
                                <th>Section</th>
                                <th>Exam Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($publishing_summary_ca['details'][$stateKey] as $item)
                            <tr>
                                <td>{{ $item->subject->code ?? 'N/A' }}</td>
                                <td>{{ $item->subject->title ?? 'N/A' }}</td>
                                <td>{{ $item->program->title ?? 'N/A' }}</td>
                                <td>{{ $item->section->title ?? 'All' }}</td>
                                <td>{{ $item->examType->title ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info text-center mt-3">No items currently in {{ $stateLabel }} state.</div>
                @endif
            </div>
            @endforeach
        </div>

      </div>
      <div class="modal-footer border-top-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Final Exam Publishing Readiness Details Modal -->
<div class="modal fade" id="finalPublishingDetailsModal" tabindex="-1" role="dialog" aria-labelledby="finalPublishingDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title" id="finalPublishingDetailsModalLabel">Final Exam Publishing Readiness Details</h5>
        <button type="button" class="close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
        <ul class="nav nav-tabs mb-3" id="finalPubReadinessTabs" role="tablist">
            @foreach($statesList as $stateKey => $stateLabel)
            <li class="nav-item">
                <a class="nav-link {{ $loop->first ? 'active' : '' }}" id="tab-pub-final-{{ $stateKey }}" data-bs-toggle="tab" data-toggle="tab" href="#pane-pub-final-{{ $stateKey }}" role="tab">
                    {{ $stateLabel }} <span class="badge badge-secondary">{{ count($publishing_summary_final['details'][$stateKey] ?? []) }}</span>
                </a>
            </li>
            @endforeach
        </ul>

        <div class="tab-content" id="finalPubReadinessTabsContent">
            @foreach($statesList as $stateKey => $stateLabel)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="pane-pub-final-{{ $stateKey }}" role="tabpanel">
                @if(count($publishing_summary_final['details'][$stateKey] ?? []) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped pub-details-table" style="width: 100%;">
                        <thead class="thead-dark">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Program</th>
                                <th>Section</th>
                                <th>Exam Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($publishing_summary_final['details'][$stateKey] as $item)
                            <tr>
                                <td>{{ $item->subject->code ?? 'N/A' }}</td>
                                <td>{{ $item->subject->title ?? 'N/A' }}</td>
                                <td>{{ $item->program->title ?? 'N/A' }}</td>
                                <td>{{ $item->section->title ?? 'All' }}</td>
                                <td>{{ $item->examType->title ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info text-center mt-3">No items currently in {{ $stateLabel }} state.</div>
                @endif
            </div>
            @endforeach
        </div>

      </div>
      <div class="modal-footer border-top-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page_js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {

    // Initialize datatable for publishing readiness details
    if ($.fn.DataTable) {
        var pubTables = $('.pub-details-table').DataTable({
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Fix column widths when a tab is shown
        $('a[data-bs-toggle="tab"], a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });
    }

    // ── Lecturer row expand/collapse ────────────────────────────────────────
    $('.lecturer-row').css('cursor', 'pointer').on('click', function() {
        var id = $(this).data('lecturer-id');
        $('#lecDetail' + id).toggle();
    });

    @if(isset($kpis))
    // ── CHARTS ──────────────────────────────────────────────────────────────

    // CA Publishing Donut
    var pubCaCtx = document.getElementById('caPublishingDonutChart');
    if (pubCaCtx) {
        new Chart(pubCaCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Published', 'Approved', 'Checked', 'Submitted', 'Draft'],
                datasets: [{
                    data: [
                        {{ $publishing_summary_ca['published'] }},
                        {{ $publishing_summary_ca['approved'] }},
                        {{ $publishing_summary_ca['checked'] }},
                        {{ $publishing_summary_ca['submitted'] }},
                        {{ $publishing_summary_ca['draft'] }}
                    ],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#6c757d', '#dc3545'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Final Exam Publishing Donut
    var pubFinalCtx = document.getElementById('finalPublishingDonutChart');
    if (pubFinalCtx) {
        new Chart(pubFinalCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Published', 'Approved', 'Checked', 'Submitted', 'Draft'],
                datasets: [{
                    data: [
                        {{ $publishing_summary_final['published'] }},
                        {{ $publishing_summary_final['approved'] }},
                        {{ $publishing_summary_final['checked'] }},
                        {{ $publishing_summary_final['submitted'] }},
                        {{ $publishing_summary_final['draft'] }}
                    ],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#6c757d', '#dc3545'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Standing Bar Chart
    @if($standings_computed)
    var stCtx = document.getElementById('standingBarChart');
    if (stCtx) {
        new Chart(stCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ["Dean's List", "Good", "Warning", "Probation", "Dismissal"],
                datasets: [{
                    data: [
                        {{ $standing_distribution['deans_list'] ?? 0 }},
                        {{ $standing_distribution['good_standing'] ?? 0 }},
                        {{ $standing_distribution['academic_warning'] ?? 0 }},
                        {{ $standing_distribution['academic_probation'] ?? 0 }},
                        {{ $standing_distribution['recommended_dismissal'] ?? 0 }}
                    ],
                    backgroundColor: ['#1565c0', '#2e7d32', '#f57f17', '#e65100', '#c62828'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
    @endif

    // Faculty Comparison Chart
    @if(isset($faculty_summaries) && count($faculty_summaries) > 0)
    var fcCtx = document.getElementById('facultyComparisonChart');
    if (fcCtx) {
        new Chart(fcCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_column($faculty_summaries, 'shortcode')) !!},
                datasets: [{
                    label: 'Pass Rate %',
                    data: {!! json_encode(array_column($faculty_summaries, 'pass_rate')) !!},
                    backgroundColor: {!! json_encode(array_map(function($fs) { return $fs['pass_rate'] >= 70 ? '#28a745' : ($fs['pass_rate'] >= 50 ? '#ffc107' : '#dc3545'); }, $faculty_summaries)) !!},
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Faculty Pass Rates', font: { size: 13 } },
                    legend: { display: false }
                },
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    }

    var fsCtx = document.getElementById('facultyScriptsChart');
    if (fsCtx) {
        new Chart(fsCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_column($faculty_summaries, 'shortcode')) !!},
                datasets: [{
                    data: {!! json_encode(array_column($faculty_summaries, 'scripts_written')) !!},
                    backgroundColor: ['#667eea', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6c757d', '#e83e8c', '#fd7e14'],
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Scripts Distribution by Faculty', font: { size: 13 } },
                    legend: { position: 'bottom', labels: { font: { size: 10 } } }
                }
            }
        });
    }
    @endif

    // Lecturer Rating Pie
    @if(isset($lecturer_performance) && count($lecturer_performance) > 0)
    var lrCtx = document.getElementById('lecturerRatingChart');
    if (lrCtx) {
        new Chart(lrCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Outstanding', 'Good', 'Satisfactory', 'Needs Improvement', 'Critical'],
                datasets: [{
                    data: [{{ $lpOutstanding }}, {{ $lpGood }}, {{ $lpSatisfactory }}, {{ $lpNeedsImprovement }}, {{ $lpCritical }}],
                    backgroundColor: ['#28a745', '#17a2b8', '#007bff', '#ffc107', '#dc3545'],
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Lecturer Performance Distribution', font: { size: 13 } },
                    legend: { position: 'bottom', labels: { font: { size: 10 } } }
                }
            }
        });
    }

    // Top 15 Lecturers Pass Rate Chart
    var topLecturers = {!! json_encode(array_slice($lecturer_performance, 0, 15)) !!};
    var lpCtx = document.getElementById('lecturerPassRateChart');
    if (lpCtx && topLecturers.length > 0) {
        new Chart(lpCtx.getContext('2d'), {
            type: 'bar',
            indexAxis: 'y',
            data: {
                labels: topLecturers.map(function(l) { return l.name.substring(0, 20); }),
                datasets: [{
                    label: 'Pass Rate %',
                    data: topLecturers.map(function(l) { return l.aggregate.pass_rate; }),
                    backgroundColor: topLecturers.map(function(l) {
                        return l.aggregate.pass_rate >= 70 ? '#28a745' : (l.aggregate.pass_rate >= 50 ? '#ffc107' : '#dc3545');
                    }),
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    title: { display: true, text: 'Top Lecturers by Pass Rate', font: { size: 13 } },
                    legend: { display: false }
                },
                scales: { x: { beginAtZero: true, max: 100 } }
            }
        });
    }
    @endif

    @endif

});
</script>
@endsection
