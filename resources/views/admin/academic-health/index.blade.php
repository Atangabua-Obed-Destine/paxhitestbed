@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    /* ═══ KPI Cards ═══ */
    .kpi-card { border-radius: 10px; transition: transform 0.2s; border: 0; box-shadow: 0 2px 8px rgba(0,0,0,.08); overflow: hidden; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.15); }
    .kpi-card .kpi-value { font-size: 26px; font-weight: 700; margin-bottom: 2px; }
    .kpi-card .kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; opacity: 0.85; }

    /* ═══ Tabs ═══ */
    .health-tabs .nav-link { font-weight: 600; font-size: 13px; padding: 12px 20px; color: #555; border-bottom: 3px solid transparent; }
    .health-tabs .nav-link.active { background: #f8f9fa; color: #2c3e50; border-bottom-color: #3498db; }
    .health-tabs .nav-link i { margin-right: 5px; }

    /* ═══ Pipeline ═══ */
    .pipeline { display: flex; align-items: center; flex-wrap: wrap; gap: 0; }
    .pipeline-step { flex: 1; min-width: 100px; text-align: center; padding: 12px 6px; border-radius: 6px; margin: 4px; transition: transform 0.2s; position: relative; }
    .pipeline-step:hover { transform: translateY(-2px); }
    .pipeline-step .step-count { font-size: 22px; font-weight: 700; }
    .pipeline-step .step-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; }
    .pipeline-step.ok { background: linear-gradient(135deg, #d4edda, #c3e6cb); color: #155724; }
    .pipeline-step.fail { background: linear-gradient(135deg, #f8d7da, #f5c6cb); color: #721c24; }
    .pipeline-arrow { font-size: 18px; color: #adb5bd; margin: 0 -2px; }

    /* ═══ Faculty accordion ═══ */
    .faculty-accordion .card { border-radius: 8px; margin-bottom: 10px; border: 1px solid #dee2e6; overflow: hidden; }
    .faculty-accordion .card-header { cursor: pointer; background: linear-gradient(135deg, #f8f9fa, #e9ecef); transition: background 0.2s; padding: 14px 18px; }
    .faculty-accordion .card-header:hover { background: linear-gradient(135deg, #e9ecef, #dee2e6); }
    .faculty-accordion .card-header h6 { margin: 0; font-weight: 700; font-size: 14px; }
    .faculty-stat { display: inline-block; padding: 3px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; margin-left: 6px; }

    /* ═══ Department section ═══ */
    .dept-section { background: #fafbfc; border-radius: 8px; padding: 16px; margin-bottom: 14px; border-left: 4px solid #3498db; }
    .dept-section h6 { color: #2c3e50; font-weight: 700; font-size: 13px; }

    /* ═══ Program table ═══ */
    .program-table { font-size: 12px; }
    .program-table th { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; background: #34495e; color: #fff; }
    .program-table td { vertical-align: middle; }

    /* ═══ Status badges ═══ */
    .status-ok { color: #28a745; font-weight: 700; }
    .status-warn { color: #ffc107; font-weight: 700; }
    .status-fail { color: #dc3545; font-weight: 700; }

    /* ═══ Performance bar ═══ */
    .readiness-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; min-width: 60px; }
    .readiness-bar .fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }

    /* ═══ Summary pills ═══ */
    .summary-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; margin: 2px; }

    /* ═══ Semester matrix ═══ */
    .semester-matrix { font-size: 11px; }
    .semester-matrix th { font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; position: sticky; top: 0; z-index: 2; }
    .semester-matrix td { text-align: center; padding: 6px 8px !important; }
    .semester-matrix .prog-name { text-align: left; white-space: nowrap; min-width: 150px; font-weight: 600; }
    .cell-ok { background: #d4edda !important; }
    .cell-partial { background: #fff3cd !important; }
    .cell-empty { background: #f8d7da !important; }

    /* ═══ Course detail modal ═══ */
    .course-list { max-height: 300px; overflow-y: auto; }
    .course-item { padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 12px; }
    .course-item:last-child { border-bottom: 0; }

    /* ═══ Teacher table ═══ */
    .teacher-table { font-size: 12px; }
    .teacher-table th { font-size: 10.5px; text-transform: uppercase; background: #2c3e50; color: #fff; }

    /* ═══ Audit items ═══ */
    .audit-item { border-radius: 8px; border-left: 4px solid #ccc; margin-bottom: 12px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.04); transition: transform 0.15s; }
    .audit-item:hover { transform: translateX(4px); }
    .audit-error { border-left-color: #dc3545; }
    .audit-warning { border-left-color: #ffc107; }
    .audit-healthy { border-left-color: #28a745; }
    .audit-body { padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
    .audit-icon { font-size: 1.4rem; margin-right: 14px; opacity: 0.85; }
    .audit-content { flex-grow: 1; }
    .audit-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 2px; color: #333; }
    .audit-title small { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.4px; background: #eee; padding: 2px 6px; border-radius: 4px; margin-left: 8px; color: #666; }
    .audit-message { color: #666; margin: 0; font-size: 0.85rem; }

    /* ═══ Print ═══ */
    @media print {
        .no-print, .page-header, .pcoded-header, .pcoded-sidebar { display: none !important; }
        .tab-content > .tab-pane { display: block !important; opacity: 1 !important; page-break-before: always; }
        .faculty-accordion .collapse { display: block !important; }
        body { font-size: 10px; }
    }
</style>
@endsection

@section('content')
<div class="pcoded-content">

    {{-- ═══ PAGE HEADER ═══ --}}
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="page-header-title">
                    <i class="fas fa-stethoscope bg-c-blue"></i>
                    <div class="d-inline">
                        <h5>{{ $title }}</h5>
                        <span>Comprehensive diagnostic report &bull; Generated {{ now()->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-right no-print">
                <button class="btn btn-outline-dark btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <div class="pcoded-inner-content">
        <div class="main-body">
            <div class="page-wrapper">
                <div class="page-body">

    {{-- ═══ KPI CARDS ═══ --}}
    <div class="row mb-3">
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;">
                <div class="kpi-value">{{ $kpis['total_faculties'] }}</div>
                <div class="kpi-label">Faculties</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;">
                <div class="kpi-value">{{ $kpis['total_programs'] }}</div>
                <div class="kpi-label">Programs</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#36d1dc,#5b86e5);color:#fff;">
                <div class="kpi-value">{{ number_format($kpis['total_students']) }}</div>
                <div class="kpi-label">Students</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff;">
                <div class="kpi-value">{{ $kpis['total_teachers'] }}</div>
                <div class="kpi-label">Teachers</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,#4facfe,#00f2fe);color:#fff;">
                <div class="kpi-value">{{ $kpis['fee_coverage'] }}%</div>
                <div class="kpi-label">Fee Coverage</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="card kpi-card text-center p-3" style="background:linear-gradient(135deg,{{ $kpis['overall_score'] >= 80 ? '#11998e,#38ef7d' : ($kpis['overall_score'] >= 50 ? '#f7971e,#ffd200' : '#eb3349,#f45c43') }});color:#fff;">
                <div class="kpi-value">{{ $kpis['overall_score'] }}%</div>
                <div class="kpi-label">Readiness</div>
            </div>
        </div>
    </div>

    {{-- ═══ TABBED NAVIGATION ═══ --}}
    <ul class="nav nav-tabs health-tabs mb-0 no-print" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-overview" role="tab"><i class="fas fa-chart-pie"></i> Overview</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-faculty" role="tab"><i class="fas fa-university"></i> Faculty Report</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-semester" role="tab"><i class="fas fa-th"></i> Semester Matrix</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-staff" role="tab"><i class="fas fa-chalkboard-teacher"></i> Staff & Teaching</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-financial" role="tab"><i class="fas fa-money-bill-wave"></i> Financial</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-diagnostics" role="tab">
                <i class="fas fa-heartbeat"></i> Diagnostics
                @if(count($diagnostic_errors) > 0)<span class="badge badge-danger ml-1">{{ count($diagnostic_errors) }}</span>@endif
            </a>
        </li>
    </ul>

    <div class="tab-content">

    {{-- ═══════════════════════════════════════════════════════
         TAB 1: OVERVIEW
       ═══════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
        <div class="card" style="border-top:0;border-top-left-radius:0;border-top-right-radius:0;">
            <div class="card-block">

                {{-- Session & Semester Status --}}
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <div class="card border h-100">
                            <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-calendar-alt text-primary"></i> Session & Semester Status</h6></div>
                            <div class="card-body">
                                @if($current_session)
                                <div class="mb-3">
                                    <span class="summary-pill" style="background:#d4edda;color:#155724;font-size:13px;">
                                        <i class="fas fa-check-circle"></i> Current Session: <strong>{{ $current_session->title }}</strong>
                                    </span>
                                    @if($current_session->applications_open)
                                    <span class="summary-pill" style="background:#d1ecf1;color:#0c5460;">Applications: Open</span>
                                    @else
                                    <span class="summary-pill" style="background:#fff3cd;color:#856404;">Applications: Closed</span>
                                    @endif
                                </div>
                                @else
                                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> No current session is set!</div>
                                @endif

                                <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                    <thead class="thead-light"><tr><th>Semester</th><th>Year</th><th>Type</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @foreach($active_semesters as $sem)
                                        <tr>
                                            <td class="font-weight-bold">{{ $sem->title }}</td>
                                            <td>Year {{ $sem->year }}</td>
                                            <td>{{ $sem->getSemesterTypeName() }}</td>
                                            <td><span class="badge badge-success">Active</span></td>
                                        </tr>
                                        @endforeach
                                        @foreach($resit_semesters as $sem)
                                        <tr class="table-warning">
                                            <td class="font-weight-bold">{{ $sem->title }}</td>
                                            <td>Year {{ $sem->year }}</td>
                                            <td><span class="badge badge-warning">Resit</span></td>
                                            <td><span class="badge badge-success">Active</span></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7 mb-3">
                        <div class="card border h-100">
                            <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-project-diagram text-success"></i> Configuration Pipeline</h6></div>
                            <div class="card-body">
                                <p class="text-muted mb-3" style="font-size:12px;">Each step must be configured for the system to function. Green means data exists, red means empty.</p>
                                <div class="pipeline">
                                    @foreach($pipeline as $i => $step)
                                    <a href="{{ $step['route'] }}" class="pipeline-step {{ $step['ok'] ? 'ok' : 'fail' }}" title="Click to manage {{ $step['label'] }}">
                                        <div class="step-count">{{ $step['count'] }}</div>
                                        <div class="step-label">{{ $step['label'] }}</div>
                                    </a>
                                    @if($i < count($pipeline) - 1)
                                    <span class="pipeline-arrow"><i class="fas fa-chevron-right"></i></span>
                                    @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Quick diagnostic summary --}}
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="card border-left border-danger" style="border-left-width: 4px !important;">
                            <div class="card-body text-center py-3">
                                <h3 class="text-danger mb-0">{{ count($diagnostic_errors) }}</h3>
                                <small class="text-uppercase text-muted font-weight-bold">Critical Blockers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="card border-left border-warning" style="border-left-width: 4px !important;">
                            <div class="card-body text-center py-3">
                                <h3 class="text-warning mb-0">{{ count($warnings) }}</h3>
                                <small class="text-uppercase text-muted font-weight-bold">Warnings</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="card border-left border-success" style="border-left-width: 4px !important;">
                            <div class="card-body text-center py-3">
                                <h3 class="text-success mb-0">{{ count($healthy) }}</h3>
                                <small class="text-uppercase text-muted font-weight-bold">Passed Checks</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TAB 2: FACULTY REPORT
       ═══════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-faculty" role="tabpanel">
        <div class="card" style="border-top:0;border-top-left-radius:0;border-top-right-radius:0;">
            <div class="card-block">

                {{-- Faculty Summary Table --}}
                <h6 class="mb-3"><i class="fas fa-university"></i> Faculty Overview</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped table-sm program-table">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Faculty</th>
                                <th>Code</th>
                                <th>Dean</th>
                                <th class="text-center">Depts</th>
                                <th class="text-center">Programs</th>
                                <th class="text-center">Subjects</th>
                                <th class="text-center">Students</th>
                                <th class="text-center">Fee Configs</th>
                                <th class="text-center">Routines</th>
                                <th style="width:100px;">Readiness</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($faculty_report as $fi => $fac)
                            @php
                                $fReadiness = 0;
                                $fChecks = 0;
                                if ($fac['total_programs'] > 0) $fChecks++;
                                if ($fac['total_subjects'] > 0) $fChecks++;
                                if ($fac['total_offerings'] > 0) $fChecks++;
                                if ($fac['fee_configs'] > 0) $fChecks++;
                                if ($fac['routines'] > 0) $fChecks++;
                                $fReadiness = $fChecks > 0 ? round(($fChecks / 5) * 100) : 0;
                            @endphp
                            <tr>
                                <td>{{ $fi + 1 }}</td>
                                <td class="font-weight-bold">{{ $fac['title'] }}</td>
                                <td>{{ $fac['shortcode'] }}</td>
                                <td style="font-size:11px;">{{ $fac['dean'] }}</td>
                                <td class="text-center">{{ count($fac['departments']) }}</td>
                                <td class="text-center">{{ $fac['total_programs'] }}</td>
                                <td class="text-center">{{ $fac['total_subjects'] }}</td>
                                <td class="text-center">{{ number_format($fac['total_students']) }}</td>
                                <td class="text-center">{{ $fac['fee_configs'] }}</td>
                                <td class="text-center">{{ $fac['routines'] }}</td>
                                <td>
                                    <div class="readiness-bar">
                                        <div class="fill" style="width:{{ $fReadiness }}%;background:{{ $fReadiness >= 80 ? '#28a745' : ($fReadiness >= 40 ? '#ffc107' : '#dc3545') }};"></div>
                                    </div>
                                    <small class="font-weight-bold {{ $fReadiness >= 80 ? 'text-success' : ($fReadiness >= 40 ? 'text-warning' : 'text-danger') }}">{{ $fReadiness }}%</small>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Detailed Faculty Accordion --}}
                <h6 class="mb-3"><i class="fas fa-sitemap"></i> Detailed Breakdown</h6>
                <div class="accordion faculty-accordion" id="facultyAccordion">
                    @foreach($faculty_report as $fi => $fac)
                    <div class="card">
                        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#fac{{ $fac['id'] }}">
                            <h6>
                                <i class="fas fa-chevron-down mr-2"></i>
                                {{ $fac['title'] }} ({{ $fac['shortcode'] }})
                                <span class="faculty-stat" style="background:#e3f2fd;color:#1565c0;">{{ $fac['total_programs'] }} Programs</span>
                                <span class="faculty-stat" style="background:#e8f5e9;color:#2e7d32;">{{ number_format($fac['total_students']) }} Students</span>
                                @if($fac['fee_configs'] == 0)
                                <span class="faculty-stat" style="background:#ffebee;color:#c62828;">⚠ No Fees</span>
                                @endif
                            </h6>
                        </div>
                        <div id="fac{{ $fac['id'] }}" class="collapse {{ $fi === 0 ? 'show' : '' }}" data-parent="#facultyAccordion">
                            <div class="card-body p-3">

                                @foreach($fac['departments'] as $dept)
                                <div class="dept-section">
                                    <h6 class="mb-1">
                                        <i class="fas fa-building"></i> {{ $dept['title'] }} ({{ $dept['shortcode'] }})
                                        <small class="text-muted float-right">HOD: {{ $dept['hod'] }}</small>
                                    </h6>

                                    @if(count($dept['programs']) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm program-table mb-1">
                                            <thead>
                                                <tr>
                                                    <th>Program</th>
                                                    <th>Degree</th>
                                                    <th class="text-center">Students</th>
                                                    <th class="text-center">Subjects</th>
                                                    <th class="text-center">Offerings</th>
                                                    <th class="text-center">Fees</th>
                                                    <th class="text-center">Routines</th>
                                                    <th class="text-center">Teachers</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($dept['programs'] as $prog)
                                                @php
                                                    $pStatus = ($prog['has_offerings'] && $prog['has_fees']) ? 'ready' : (($prog['has_offerings'] || $prog['has_fees']) ? 'partial' : 'missing');
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <strong>{{ $prog['title'] }}</strong>
                                                        <br><small class="text-muted">{{ $prog['shortcode'] }}</small>
                                                    </td>
                                                    <td class="text-center">{{ $prog['degree'] }}</td>
                                                    <td class="text-center">{{ $prog['students'] }}</td>
                                                    <td class="text-center">{{ $prog['subjects'] }}</td>
                                                    <td class="text-center">
                                                        @if($prog['has_offerings'])
                                                        <span class="status-ok">✓ {{ $prog['offerings'] }}</span>
                                                        @else
                                                        <span class="status-fail">✗ 0</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if($prog['has_fees'])
                                                        <span class="status-ok">✓ {{ $prog['fees'] }}</span>
                                                        @else
                                                        <span class="status-fail">✗ None</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if($prog['has_routines'])
                                                        <span class="status-ok">✓ {{ $prog['routines'] }}</span>
                                                        @else
                                                        <span class="status-warn">— 0</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">{{ $prog['teachers']->count() }}</td>
                                                    <td>
                                                        @if($pStatus == 'ready')
                                                        <span class="badge badge-success">🟢 Ready</span>
                                                        @elseif($pStatus == 'partial')
                                                        <span class="badge badge-warning">🟡 Partial</span>
                                                        @else
                                                        <span class="badge badge-danger">🔴 Missing</span>
                                                        @endif
                                                    </td>
                                                    <td style="white-space: nowrap;">
                                                        @if(!$prog['has_offerings'])
                                                        <a href="{{ route('admin.enroll-subject.index') }}" class="btn btn-outline-primary btn-sm" title="Add Course Offerings"><i class="fas fa-plus-circle"></i></a>
                                                        @endif
                                                        @if(!$prog['has_fees'])
                                                        <a href="{{ route('admin.program-semester-fee.index') }}" class="btn btn-outline-danger btn-sm" title="Configure Fees"><i class="fas fa-dollar-sign"></i></a>
                                                        @endif
                                                    </td>
                                                </tr>

                                                {{-- Semester course offerings detail --}}
                                                @if(count($prog['semester_offerings']) > 0)
                                                <tr>
                                                    <td colspan="10" style="background: #f0f4f8; padding: 8px 16px;">
                                                        <strong style="font-size:11px;"><i class="fas fa-list"></i> Course Offerings by Semester:</strong>
                                                        <div class="row mt-1">
                                                            @foreach($prog['semester_offerings'] as $semOff)
                                                            <div class="col-md-4 mb-2">
                                                                <div class="card border mb-0">
                                                                    <div class="card-header py-1 px-2 bg-light" style="font-size:11px;">
                                                                        <strong>{{ $semOff['semester'] }}</strong>
                                                                        <span class="badge badge-primary float-right">{{ $semOff['course_count'] }} courses</span>
                                                                    </div>
                                                                    <div class="card-body p-0 course-list">
                                                                        @foreach($semOff['courses'] as $course)
                                                                        <div class="course-item">
                                                                            <strong>{{ $course['code'] }}</strong> — {{ $course['title'] }}
                                                                            <span class="text-muted float-right">{{ $course['credits'] }} cr</span>
                                                                        </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endif

                                                {{-- Teachers --}}
                                                @if($prog['teachers']->count() > 0)
                                                <tr>
                                                    <td colspan="10" style="background: #fdf6ec; padding: 8px 16px;">
                                                        <strong style="font-size:11px;"><i class="fas fa-chalkboard-teacher"></i> Assigned Teachers:</strong>
                                                        @foreach($prog['teachers'] as $t)
                                                        <span class="summary-pill" style="background:#fff;border:1px solid #ddd;">
                                                            {{ $t->first_name }} {{ $t->last_name }}
                                                            @if($t->department) <small class="text-muted">({{ $t->department->title }})</small> @endif
                                                        </span>
                                                        @endforeach
                                                    </td>
                                                </tr>
                                                @endif

                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @else
                                    <p class="text-muted mb-0" style="font-size:12px;"><i class="fas fa-info-circle"></i> No programs assigned to this department.</p>
                                    @endif
                                </div>
                                @endforeach

                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TAB 3: SEMESTER CONFIGURATION MATRIX
       ═══════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-semester" role="tabpanel">
        <div class="card" style="border-top:0;border-top-left-radius:0;border-top-right-radius:0;">
            <div class="card-block">
                <h6 class="mb-2"><i class="fas fa-th"></i> Program × Semester Configuration Matrix</h6>
                <p class="text-muted mb-3" style="font-size:12px;">This matrix shows what is configured for each program in each semester. <span class="badge badge-success">Green</span> = courses + fees. <span class="badge badge-warning">Yellow</span> = partial. <span class="badge badge-danger">Red</span> = nothing.</p>

                <div class="table-responsive" style="max-height: 600px; overflow: auto;">
                    <table class="table table-bordered semester-matrix">
                        <thead class="thead-dark">
                            <tr>
                                <th style="min-width:40px;">S/N</th>
                                <th style="min-width:180px;">Program</th>
                                <th>Faculty</th>
                                <th>Degree</th>
                                @foreach($active_semesters as $sem)
                                <th class="text-center" style="min-width:120px;">{{ $sem->title }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($semester_matrix as $mi => $row)
                            <tr>
                                <td>{{ $mi + 1 }}</td>
                                <td class="prog-name">{{ $row['program'] }}</td>
                                <td>{{ $row['faculty'] }}</td>
                                <td>{{ $row['degree'] }}</td>
                                @foreach($active_semesters as $sem)
                                @php
                                    $cell = $row['semesters'][$sem->id] ?? ['courses' => 0, 'has_fee' => false, 'has_routine' => false];
                                    $cellClass = ($cell['courses'] > 0 && $cell['has_fee']) ? 'cell-ok' : (($cell['courses'] > 0 || $cell['has_fee']) ? 'cell-partial' : 'cell-empty');
                                @endphp
                                <td class="{{ $cellClass }}">
                                    @if($cell['courses'] > 0)
                                    <strong>{{ $cell['courses'] }}</strong> courses
                                    @else
                                    <span class="text-muted">—</span>
                                    @endif
                                    <br>
                                    @if($cell['has_fee'])
                                    <small class="text-success"><i class="fas fa-check"></i> Fee</small>
                                    @else
                                    <small class="text-danger"><i class="fas fa-times"></i> Fee</small>
                                    @endif
                                    @if($cell['has_routine'])
                                    <small class="text-success ml-1"><i class="fas fa-check"></i> TT</small>
                                    @endif
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TAB 4: STAFF & TEACHING
       ═══════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-staff" role="tabpanel">
        <div class="card" style="border-top:0;border-top-left-radius:0;border-top-right-radius:0;">
            <div class="card-block">

                <div class="row mb-4">
                    <div class="col-md-3 mb-2">
                        <div class="card border text-center p-3">
                            <h3 class="mb-0 text-primary">{{ $kpis['total_staff'] }}</h3>
                            <small class="text-muted text-uppercase font-weight-bold">Total Staff</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="card border text-center p-3">
                            <h3 class="mb-0 text-success">{{ $kpis['total_teachers'] }}</h3>
                            <small class="text-muted text-uppercase font-weight-bold">Active Teachers</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="card border text-center p-3">
                            <h3 class="mb-0 text-info">{{ count($staff_departments) }}</h3>
                            <small class="text-muted text-uppercase font-weight-bold">Staff Departments</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="card border text-center p-3">
                            <h3 class="mb-0 text-warning">{{ count($staff_without_teaching) }}</h3>
                            <small class="text-muted text-uppercase font-weight-bold">Unassigned Teachers</small>
                        </div>
                    </div>
                </div>

                {{-- Teacher assignment table --}}
                <h6 class="mb-3"><i class="fas fa-chalkboard-teacher"></i> Teacher Assignments (Current Session)</h6>
                @if(count($teacher_report) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm teacher-table">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Teacher Name</th>
                                <th>Staff Department</th>
                                <th>Designation</th>
                                <th class="text-center">Courses Taught</th>
                                <th>Programs</th>
                                <th class="text-center">Timetable Slots</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($teacher_report as $ti => $t)
                            <tr>
                                <td>{{ $ti + 1 }}</td>
                                <td class="font-weight-bold">{{ $t['name'] }}</td>
                                <td>{{ $t['department'] }}</td>
                                <td>{{ $t['designation'] }}</td>
                                <td class="text-center"><strong>{{ $t['courses_taught'] }}</strong></td>
                                <td><small>{{ $t['programs'] }}</small></td>
                                <td class="text-center">{{ $t['total_slots'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <p>No teacher assignments found for the current session.</p>
                </div>
                @endif

                {{-- Staff with teacher role but no classes --}}
                @if($staff_without_teaching->count() > 0)
                <hr>
                <h6 class="mb-3 text-warning"><i class="fas fa-exclamation-triangle"></i> Staff with Teacher/Lecturer Role but No Classes Assigned</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" style="font-size:12px;">
                        <thead class="thead-light">
                            <tr><th>Name</th><th>Department</th><th>Designation</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach($staff_without_teaching as $sw)
                            <tr>
                                <td>{{ $sw->first_name }} {{ $sw->last_name }}</td>
                                <td>{{ $sw->department ? $sw->department->title : '—' }}</td>
                                <td>{{ $sw->designation ? $sw->designation->title : '—' }}</td>
                                <td><a href="{{ route('admin.class-routine.index') }}" class="btn btn-outline-primary btn-sm">Assign Classes</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TAB 5: FINANCIAL
       ═══════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-financial" role="tabpanel">
        <div class="card" style="border-top:0;border-top-left-radius:0;border-top-right-radius:0;">
            <div class="card-block">

                <div class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <div class="card border text-center p-3">
                            <h3 class="mb-0 text-success">{{ $all_fees->count() }}</h3>
                            <small class="text-muted text-uppercase font-weight-bold">Total Fee Configs</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="card border text-center p-3">
                            <h3 class="mb-0 text-danger">{{ $programs_without_fees->count() }}</h3>
                            <small class="text-muted text-uppercase font-weight-bold">Programs Without Fees</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="card border text-center p-3">
                            <h3 class="mb-0 text-warning">{{ $students_without_fee_program }}</h3>
                            <small class="text-muted text-uppercase font-weight-bold">Students Affected</small>
                        </div>
                    </div>
                </div>

                {{-- Programs without fees --}}
                @if($programs_without_fees->count() > 0)
                <div class="alert alert-danger mb-4">
                    <strong><i class="fas fa-exclamation-circle"></i> Programs Without Any Fee Configuration</strong>
                    <p class="mb-2">These programs have no fee structure defined. Students enrolled in these programs cannot be billed.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm bg-white mb-0" style="font-size:12px;">
                            <thead class="thead-light"><tr><th>Program</th><th>Faculty</th><th>Degree</th><th>Action</th></tr></thead>
                            <tbody>
                                @foreach($programs_without_fees as $pwf)
                                <tr>
                                    <td class="font-weight-bold">{{ $pwf->title }}</td>
                                    <td>{{ $pwf->faculty ? $pwf->faculty->title : '—' }}</td>
                                    <td>{{ $pwf->degreeType ? $pwf->degreeType->shortcode : '—' }}</td>
                                    <td><a href="{{ route('admin.program-semester-fee.create') }}" class="btn btn-danger btn-sm">Configure Fee <i class="fas fa-arrow-right"></i></a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Existing fee configs --}}
                <h6 class="mb-3"><i class="fas fa-money-bill-wave"></i> All Fee Configurations</h6>
                @if($all_fees->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm program-table">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Program</th>
                                <th>Faculty</th>
                                <th>Semester</th>
                                <th>Fee Category</th>
                                <th class="text-right">Amount</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($all_fees as $fi => $fee)
                            <tr>
                                <td>{{ $fi + 1 }}</td>
                                <td class="font-weight-bold">{{ $fee->program ? $fee->program->title : '—' }}</td>
                                <td>{{ ($fee->program && $fee->program->faculty) ? $fee->program->faculty->shortcode : '—' }}</td>
                                <td>{{ $fee->semester ? $fee->semester->title : '—' }}</td>
                                <td>{{ $fee->feesCategory ? $fee->feesCategory->title : '—' }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($fee->amount, 0) }}</td>
                                <td class="text-center">
                                    @if($fee->status)
                                    <span class="badge badge-success">Active</span>
                                    @else
                                    <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                    <p>No fee configurations exist. <a href="{{ route('admin.program-semester-fee.create') }}">Create one</a>.</p>
                </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TAB 6: DIAGNOSTICS
       ═══════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-diagnostics" role="tabpanel">
        <div class="card" style="border-top:0;border-top-left-radius:0;border-top-right-radius:0;">
            <div class="card-block">

                @if(count($diagnostic_errors) > 0)
                <h6 class="text-danger mb-3"><i class="fas fa-exclamation-circle"></i> Critical Blockers ({{ count($diagnostic_errors) }})</h6>
                @foreach($diagnostic_errors as $item)
                <div class="audit-item audit-error">
                    <div class="audit-body">
                        <div class="audit-icon text-danger"><i class="fas fa-times-circle"></i></div>
                        <div class="audit-content">
                            <h5 class="audit-title">{{ $item['title'] }} <small>{{ $item['category'] }}</small></h5>
                            <p class="audit-message">{{ $item['message'] }}</p>
                        </div>
                        @if(isset($item['action_link']))
                        <a href="{{ $item['action_link'] }}" class="btn btn-danger btn-sm ml-3">{{ $item['action_text'] ?? 'Fix' }} <i class="fas fa-arrow-right"></i></a>
                        @endif
                    </div>
                </div>
                @endforeach
                @endif

                @if(count($warnings) > 0)
                <h6 class="text-warning mb-3 mt-4"><i class="fas fa-exclamation-triangle"></i> Warnings ({{ count($warnings) }})</h6>
                @foreach($warnings as $item)
                <div class="audit-item audit-warning">
                    <div class="audit-body">
                        <div class="audit-icon text-warning"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="audit-content">
                            <h5 class="audit-title">{{ $item['title'] }} <small>{{ $item['category'] }}</small></h5>
                            <p class="audit-message">{{ $item['message'] }}</p>
                        </div>
                        @if(isset($item['action_link']))
                        <a href="{{ $item['action_link'] }}" class="btn btn-warning btn-sm ml-3">{{ $item['action_text'] ?? 'Review' }} <i class="fas fa-arrow-right"></i></a>
                        @endif
                    </div>
                </div>
                @endforeach
                @endif

                @if(count($healthy) > 0)
                <h6 class="text-success mb-3 mt-4"><i class="fas fa-check-circle"></i> Passed Checks ({{ count($healthy) }})</h6>
                @foreach($healthy as $item)
                <div class="audit-item audit-healthy">
                    <div class="audit-body">
                        <div class="audit-icon text-success"><i class="fas fa-check-circle"></i></div>
                        <div class="audit-content">
                            <h5 class="audit-title">{{ $item['title'] }} <small>{{ $item['category'] }}</small></h5>
                            <p class="audit-message">{{ $item['message'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
                @endif

                @if(count($diagnostic_errors) == 0 && count($warnings) == 0)
                <div class="text-center py-5">
                    <i class="fas fa-glass-cheers text-success fa-3x mb-3"></i>
                    <h4 class="text-success">All Systems Healthy!</h4>
                    <p class="text-muted">Your academic configuration is fully set up and operational.</p>
                </div>
                @endif

            </div>
        </div>
    </div>

    </div>{{-- /tab-content --}}

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
