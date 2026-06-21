@extends('admin.layouts.master')
@section('title', $title)
@section('page_css')
<link rel="stylesheet" href="{{ asset('dashboard/plugins/lightbox2-master/css/lightbox.min.css') }}">
<style>
/* =====================================================
   ADMIN TRANSCRIPT — Professional Styling (at- prefix)
   ===================================================== */

/* Summary Strip */
.at-summary-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0;
    padding: 16px 20px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
    border-radius: 8px;
    margin-bottom: 20px;
}
.at-summary-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 6px 22px;
}
.at-summary-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748b;
    margin-bottom: 2px;
}
.at-summary-value {
    font-size: 20px;
    font-weight: 800;
    color: #1e293b;
}
.at-summary-gpa {
    color: #2563eb;
    font-size: 26px;
}
.at-summary-divider {
    width: 1px;
    height: 34px;
    background: #94a3b8;
    opacity: 0.3;
    flex-shrink: 0;
}

/* Grading Scale Toggle */
.at-grade-toggle {
    margin-bottom: 16px;
}
.at-toggle-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    transition: all 0.2s;
}
.at-toggle-btn:hover { background: #f1f5f9; color: #2563eb; }
.at-toggle-chevron {
    margin-left: auto;
    transition: transform 0.3s;
    font-size: 11px;
}
.at-chevron-open { transform: rotate(180deg); }
.at-collapsed { display: none; }

.at-grade-panel {
    padding: 0 0 16px;
    overflow-x: auto;
}
.at-grade-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.at-grade-table th {
    background: #f1f5f9;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 10px;
    color: #475569;
    padding: 7px 10px;
    text-align: left;
    border-bottom: 2px solid #cbd5e1;
    white-space: nowrap;
}
.at-grade-table td {
    padding: 5px 10px;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
}
.at-class-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.2px;
}
.at-class-distinction { background: #dcfce7; color: #166534; }
.at-class-merit { background: #dbeafe; color: #1e40af; }
.at-class-pass { background: #fef9c3; color: #854d0e; }
.at-class-marginal { background: #ffedd5; color: #9a3412; }
.at-class-fail { background: #fee2e2; color: #991b1b; }

/* Semester Blocks */
.at-semester-block {
    margin-bottom: 20px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.at-semester-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    background: #1e3a5f;
    border-top: 3px solid #2563eb;
}
.at-semester-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.at-semester-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px; height: 30px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    font-size: 13px;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
}
.at-semester-title {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.at-semester-session {
    font-size: 11px;
    color: rgba(255,255,255,0.65);
    letter-spacing: 0.2px;
}

/* Academic Table */
.at-table-wrapper {
    overflow-x: auto;
}
.at-academic-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.at-academic-table thead th {
    background: #f1f5f9;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 10px;
    color: #475569;
    padding: 10px 12px;
    text-align: left;
    border-bottom: 2px solid #94a3b8;
    white-space: nowrap;
}
.at-col-code { width: 100px; }
.at-col-title { min-width: 160px; }
.at-col-type { width: 60px; text-align: center !important; }
.at-col-num { width: 78px; text-align: center !important; }
.at-col-grade { width: 78px; text-align: center !important; }

.at-academic-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}
.at-academic-table tbody tr:nth-child(even) td {
    background: rgba(248, 250, 252, 0.6);
}
.at-academic-table tbody tr:hover td {
    background: rgba(239, 246, 255, 0.7);
}
.at-cell-code {
    font-family: 'Consolas', 'Courier New', monospace;
    font-weight: 600;
    color: #334155;
    letter-spacing: 0.5px;
}
.at-cell-title { font-weight: 500; color: #1e293b; }
.at-cell-type { text-align: center; }
.at-cell-num { text-align: center; font-variant-numeric: tabular-nums; }
.at-cell-grade { text-align: center; }

/* Type badges */
.at-type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 800;
}
.at-type-core { background: #dbeafe; color: #1e40af; }
.at-type-ur { background: #f3e8ff; color: #6b21a8; }
.at-type-elective { background: #fef3c7; color: #92400e; }

/* Grade pill */
.at-grade-pill {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.4px;
}
.at-grade-pass { background: #dcfce7; color: #166534; }
.at-grade-fail { background: #fee2e2; color: #991b1b; }

.at-pending {
    color: #94a3b8;
    font-size: 14px;
}
.at-pending-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #f1f5f9;
    color: #64748b;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Failed rows */
.at-row-fail td {
    background: rgba(254, 226, 226, 0.25) !important;
}

/* Footer rows */
.at-footer-totals td {
    background: #f8fafc !important;
    border-top: 2px solid #94a3b8;
    border-bottom: 1px solid #cbd5e1;
    padding: 10px 12px;
    font-size: 12px;
}
.at-footer-gpa td {
    background: #f0f9ff !important;
    padding: 10px 12px;
    font-size: 13px;
}
.at-gpa-value {
    text-align: left;
    color: #2563eb;
    font-size: 16px !important;
}

/* Academic Standing */
.at-standing {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    vertical-align: middle;
}
.at-standing-distinction { background: #dcfce7; color: #166534; }
.at-standing-good { background: #dbeafe; color: #1e40af; }
.at-standing-satisfactory { background: #fef9c3; color: #854d0e; }
.at-standing-warning { background: #fee2e2; color: #991b1b; }

/* Chart Section */
.at-chart-section {
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    margin-top: 8px;
}
.at-section-heading {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.at-section-heading i { color: #2563eb; }
.at-chart-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 12px;
    color: #64748b;
}
.at-legend-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    vertical-align: middle;
    margin-right: 4px;
}

/* Stat cards */
.at-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 16px;
}
.at-stat-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.at-stat-icon {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    font-size: 16px;
    flex-shrink: 0;
}
.at-stat-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.at-stat-number {
    font-size: 16px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.2;
}
.at-stat-number small {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.at-stat-label {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 600;
}

/* Insights */
.at-insights {
    display: flex;
    gap: 10px;
    margin-top: 14px;
    padding: 14px 16px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    font-size: 12.5px;
    color: #78350f;
}
.at-insights-icon {
    font-size: 18px;
    color: #d97706;
    flex-shrink: 0;
    margin-top: 2px;
}
.at-insights-body strong { color: #92400e; }
.at-insights-body ul {
    margin: 4px 0 0;
    padding-left: 18px;
}
.at-insights-body li {
    margin-bottom: 3px;
    line-height: 1.5;
}

/* Responsive */
@media (max-width: 768px) {
    .at-summary-strip { flex-direction: column; gap: 4px; }
    .at-summary-divider { width: 50px; height: 1px; }
    .at-stats-row { grid-template-columns: 1fr 1fr; }
    .at-chart-controls { flex-direction: column; align-items: flex-start; }
    .at-insights { flex-direction: column; }
}
</style>
@endsection
@section('content')

@php
    // Get all enrollments for this student
    $tempEnrollments = \App\Models\StudentEnroll::where('student_id', $row->id)
        ->with(['program.degreeType', 'program.faculty', 'semester', 'session'])
        ->orderBy('id', 'desc')
        ->get();
    
    // Group by unique matricule and keep only the latest enrollment for each
    $allEnrollments = $tempEnrollments->groupBy('matricule')->map(function($group) {
        return $group->first(); // Latest enrollment for this matricule
    })->values();
    
    // Get selected enrollment from URL parameter or use latest
    $selectedEnrollmentId = request()->get('enrollment_id');
    if ($selectedEnrollmentId) {
        $currentEnroll = $allEnrollments->firstWhere('id', $selectedEnrollmentId);
    }
    if (!isset($currentEnroll) || !$currentEnroll) {
        $currentEnroll = $allEnrollments->first();
    }
    
    // Set program filter
    $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : $row->program_id;
@endphp

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        
        <!-- Program Switcher for Multi-Enrollment Students -->
        @if($allEnrollments->count() > 1)
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="alert alert-info d-flex align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white;">
                    <i class="fas fa-exchange-alt me-3" style="font-size: 24px;"></i>
                    <div style="flex: 1;">
                        <h6 class="mb-1 text-white"><strong>Multi-Enrollment Student</strong></h6>
                        <p class="mb-0" style="font-size: 13px; opacity: 0.95;">
                            This student has {{ $allEnrollments->count() }} enrollments. Select an enrollment to view all data for that specific program.
                        </p>
                    </div>
                    <div class="dropdown" style="margin-left: 15px;">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" style="min-width: 200px; font-weight: 500;">
                            <i class="fas fa-graduation-cap me-2"></i>{{ $currentEnroll->matricule }}
                        </button>
                        <ul class="dropdown-menu" style="min-width: 350px;">
                            @foreach($allEnrollments as $enrollment)
                            <li>
                                <a class="dropdown-item @if($enrollment->id == $currentEnroll->id) active @endif" 
                                   href="{{ route($route.'.show', $row->id) }}?enrollment_id={{ $enrollment->id }}"
                                   style="padding: 12px 15px;">
                                    <div class="d-flex align-items-start">
                                        <div style="width: 40px; height: 40px; border-radius: 8px; 
                                            background: linear-gradient(135deg, {{ $enrollment->program->academic_level == 'M' ? '#f093fb 0%, #f5576c 100%' : ($enrollment->program->academic_level == 'D' ? '#4facfe 0%, #00f2fe 100%' : '#43e97b 0%, #38f9d7 100%') }}); 
                                            display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="fas fa-graduation-cap text-white"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; font-size: 14px; color: #333;">
                                                {{ $enrollment->matricule }}
                                                @php
                                                    $isEligible = false;
                                                    if ($enrollment->status == '1') {
                                                        $graduationService = app(\App\Services\GraduationEligibilityService::class);
                                                        $eligibility = $graduationService->checkEligibility($row, $enrollment->program_id);
                                                        $isEligible = $eligibility['is_eligible'];
                                                    }
                                                @endphp
                                                @if($enrollment->status == '0')
                                                    @if($isEligible)
                                                        <span class="badge bg-primary" style="font-size: 9px; margin-left: 5px;">
                                                            <i class="fas fa-graduation-cap"></i> Graduated
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary" style="font-size: 9px; margin-left: 5px;">
                                                            <i class="fas fa-archive"></i> Inactive
                                                        </span>
                                                    @endif
                                                @elseif($isEligible)
                                                    <span class="badge bg-warning text-dark" style="font-size: 9px; margin-left: 5px;">
                                                        <i class="fas fa-check-double"></i> Eligible
                                                    </span>
                                                @else
                                                    <span class="badge bg-success" style="font-size: 9px; margin-left: 5px;">
                                                        <i class="fas fa-circle"></i> Active
                                                    </span>
                                                @endif
                                            </div>
                                            <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                                {{ $enrollment->program->title ?? 'N/A' }}
                                                <span class="badge" style="background: {{ $enrollment->program->academic_level == 'M' ? '#f5576c' : ($enrollment->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; font-size: 9px; margin-left: 5px;">
                                                    {{ $enrollment->program->academic_level == 'A' ? 'UG' : ($enrollment->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            </div>
                                            <div style="font-size: 11px; color: #999; margin-top: 3px;">
                                                {{ $enrollment->session->title ?? '' }} - {{ $enrollment->semester->title ?? '' }}
                                            </div>
                                        </div>
                                        @if($enrollment->id == $currentEnroll->id)
                                        <i class="fas fa-check-circle text-success" style="font-size: 18px; margin-left: 8px;"></i>
                                        @endif
                                    </div>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        @endif
        
        <div class="row">
            <div class="col-md-4">
                <div class="card user-card user-card-1">
                    <div class="card-body pb-0">
                        @php $student = $row; @endphp

                        <div class="media user-about-block align-items-center mt-0 mb-3">
                            <div class="position-relative d-inline-block">
                                @if(upload_exists($path.'/'.$row->photo))
                                <img src="{{ upload_asset($path.'/'.$row->photo) }}" class="img-radius img-fluid wid-80" alt="{{ __('field_photo') }}" onerror="this.src='{{ asset('dashboard/images/user/avatar-2.jpg') }}';">
                                @else
                                <img src="{{ asset('dashboard/images/user/avatar-2.jpg') }}" class="img-radius img-fluid wid-80" alt="{{ __('field_photo') }}">
                                @endif
                                <div class="certificated-badge">
                                    <i class="fas fa-certificate text-primary bg-icon"></i>
                                    <i class="fas fa-check front-icon text-white"></i>
                                </div>
                            </div>
                            <div class="media-body ms-3">
                                <h6 class="mb-1">{{ $row->first_name }} {{ $row->last_name }}</h6>
                                @if($currentEnroll)
                                <p class="mb-0">
                                    <strong style="font-size: 15px; color: #667eea;">#{{ $currentEnroll->matricule ?? $row->student_id }}</strong>
                                    @if($currentEnroll->program)
                                        <span class="badge" style="background: {{ $currentEnroll->program->academic_level == 'M' ? '#f5576c' : ($currentEnroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 10px; margin-left: 5px;">
                                            {{ $currentEnroll->program->academic_level == 'A' ? 'Undergraduate' : ($currentEnroll->program->academic_level == 'M' ? 'Masters' : 'Doctoral') }}
                                        </span>
                                    @endif
                                    @php
                                        $currentEligible = false;
                                        if ($currentEnroll->status == '1') {
                                            $graduationService = app(\App\Services\GraduationEligibilityService::class);
                                            $currentEligibility = $graduationService->checkEligibility($row, $currentEnroll->program_id);
                                            $currentEligible = $currentEligibility['is_eligible'];
                                        }
                                    @endphp
                                    @if($currentEnroll->status == '0')
                                        @if($currentEligible)
                                            <span class="badge bg-primary" style="font-size: 10px; margin-left: 5px;">
                                                <i class="fas fa-graduation-cap"></i> Graduated
                                            </span>
                                        @else
                                            <span class="badge bg-secondary" style="font-size: 10px; margin-left: 5px;">
                                                <i class="fas fa-archive"></i> Inactive
                                            </span>
                                        @endif
                                    @elseif($currentEligible)
                                        <span class="badge bg-warning text-dark" style="font-size: 10px; margin-left: 5px;">
                                            <i class="fas fa-check-double"></i> Eligible for Graduation
                                        </span>
                                    @else
                                        <span class="badge bg-success" style="font-size: 10px; margin-left: 5px;">
                                            <i class="fas fa-circle"></i> Active
                                        </span>
                                    @endif
                                </p>
                                <p class="mb-0 text-muted" style="font-size: 11px;">Internal ID: {{ $row->student_id }}</p>
                                @else
                                <p class="mb-0 text-muted">#{{ $row->student_id }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <span class="f-w-500"><i class="far fa-envelope m-r-10"></i>{{ __('field_email') }} : </span>
                            <span class="float-end">{{ $row->email }}</span>
                        </li>
                        <li class="list-group-item">
                            <span class="f-w-500"><i class="fas fa-phone-alt m-r-10"></i>{{ __('field_phone') }} : </span>
                            <span class="float-end">{{ $row->phone }}</span>
                        </li>
                        <li class="list-group-item">
                            <span class="f-w-500"><i class="fas fa-users m-r-10"></i>{{ __('field_batch') }} : </span>
                            <span class="float-end">{{ $row->batch->title ?? '' }}</span>
                        </li>
                        <li class="list-group-item">
                            <span class="f-w-500"><i class="fas fa-graduation-cap m-r-10"></i>{{ __('field_program') }} : </span>
                            <span class="float-end">{{ $currentEnroll->program->title ?? $row->program->title ?? '' }}</span>
                        </li>
                        <li class="list-group-item">
                            <span class="f-w-500"><i class="far fa-calendar-alt m-r-10"></i>{{ __('field_admission_date') }} : </span>
                            <span class="float-end">
                                @if(isset($setting->date_format))
                                {{ date($setting->date_format, strtotime($row->admission_date)) }}
                                @else
                                {{ date("Y-m-d", strtotime($row->admission_date)) }}
                                @endif
                            </span>
                        </li>
                        @if(isset($row->registration_no))
                        <li class="list-group-item border-bottom-0">
                            <span class="f-w-500"><i class="far fa-question-circle m-r-10"></i>{{ __('field_registration_no') }} : </span>
                            <span class="float-end">#{{ $row->registration_no }}</span>
                        </li>
                        @endif
                    </ul>

                    @php
                        $total_credits = 0; // Only passed courses
                        $total_cgpa = 0;
                        $total_credits_attempted = 0; // All courses (for CGPA calculation)
                    @endphp
                    @foreach( $row->studentEnrolls as $key => $item )
                        @if($item->program_id == $selectedProgramId && $item->matricule == $currentEnroll->matricule)
                        @if(isset($item->subjectMarks))
                        @foreach($item->subjectMarks as $mark)
                            @if($mark->is_visible_to_student)
                            @php
                            $marks_per = round($mark->total_marks);
                            @endphp

                            @foreach($grades as $grade)
                            @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                            @php
                            // Count all courses (including failed) for CGPA calculation
                            $total_cgpa = $total_cgpa + ($grade->point * $mark->subject->credit_hour);
                            $total_credits_attempted = $total_credits_attempted + $mark->subject->credit_hour;
                            
                            // Only count PASSED courses (>=50%) for total credits earned
                            if($marks_per >= 50) {
                                $total_credits = $total_credits + $mark->subject->credit_hour;
                            }
                            @endphp
                            @break
                            @endif
                            @endforeach
                            @endif
                        @endforeach
                        @endif
                        @endif

                    @endforeach
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col">
                                <h6 class="mb-1">{{ number_format((float)$total_credits, 2, '.', '') }}</h6>
                                <p class="mb-0">{{ __('field_total_credit_hour') }}</p>
                            </div>
                            <div class="col border-start">
                                <h6 class="mb-1">
                                    @php
                                    // Use attempted credits for CGPA calculation (includes failed courses)
                                    $creditBase = $total_credits_attempted > 0 ? $total_credits_attempted : 1;
                                    $com_gpa = $total_cgpa / $creditBase;
                                    echo number_format((float)$com_gpa, 2, '.', '');
                                    @endphp
                                </h6>
                                <p class="mb-0">{{ __('field_cumulative_gpa') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
                function field($slug){
                    return \App\Models\Field::field($slug);
                }
            @endphp
            <div class="col-md-8">
                <div class="card">
                    <div class="card-block">
                        <div class="">
                            <div class="row">
                                <div class="col-md-6">
                                    <fieldset class="row gx-2 scheduler-border">
                                    @if(field('student_father_name')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_father_name') }}:</mark> {{ $row->father_name }}</p><hr/>
                                    @endif
                                    @if(field('student_father_occupation')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_father_occupation') }}:</mark> {{ $row->father_occupation }}</p><hr/>
                                    @endif
                                    @if(field('student_mother_name')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_mother_name') }}:</mark> {{ $row->mother_name }}</p><hr/>
                                    @endif
                                    @if(field('student_mother_occupation')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_mother_occupation') }}:</mark> {{ $row->mother_occupation }}</p><hr/>
                                    @endif

                                    <p><mark class="text-primary">{{ __('field_gender') }}:</mark> 
                                        @if( $row->gender == 1 )
                                        {{ __('gender_male') }}
                                        @elseif( $row->gender == 2 )
                                        {{ __('gender_female') }}
                                        @elseif( $row->gender == 3 )
                                        {{ __('gender_other') }}
                                        @endif
                                    </p><hr/>

                                    <p><mark class="text-primary">{{ __('field_dob') }}:</mark> 
                                        @if(isset($setting->date_format))
                                        {{ date($setting->date_format, strtotime($row->dob)) }}
                                        @else
                                        {{ date("Y-m-d", strtotime($row->dob)) }}
                                        @endif
                                    </p><hr/>

                                    @if(field('student_emergency_phone')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_emergency_phone') }}:</mark> {{ $row->emergency_phone }}</p><hr/>
                                    @endif
                                    @if(field('student_religion')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_religion') }}:</mark> {{ $row->religion }}</p><hr/>
                                    @endif
                                    @if(field('student_caste')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_caste') }}:</mark> {{ $row->caste }}</p><hr/>
                                    @endif
                                    @if(field('student_mother_tongue')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_mother_tongue') }}:</mark> {{ $row->mother_tongue }}</p><hr/>
                                    @endif
                                    @if(field('student_nationality')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_nationality') }}:</mark> {{ $row->nationality }}</p><hr/>
                                    @endif

                                    @if(field('student_marital_status')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_marital_status') }}:</mark> 
                                        @if( $row->marital_status == 1 )
                                        {{ __('marital_status_single') }}
                                        @elseif( $row->marital_status == 2 )
                                        {{ __('marital_status_married') }}
                                        @elseif( $row->marital_status == 3 )
                                        {{ __('marital_status_widowed') }}
                                        @elseif( $row->marital_status == 4 )
                                        {{ __('marital_status_divorced') }}
                                        @elseif( $row->marital_status == 5 )
                                        {{ __('marital_status_other') }}
                                        @endif
                                    </p><hr/>
                                    @endif

                                    @if(field('student_blood_group')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_blood_group') }}:</mark> 
                                        @if( $row->blood_group == 1 )
                                        {{ __('A+') }}
                                        @elseif( $row->blood_group == 2 )
                                        {{ __('A-') }}
                                        @elseif( $row->blood_group == 3 )
                                        {{ __('B+') }}
                                        @elseif( $row->blood_group == 4 )
                                        {{ __('B-') }}
                                        @elseif( $row->blood_group == 5 )
                                        {{ __('AB+') }}
                                        @elseif( $row->blood_group == 6 )
                                        {{ __('AB-') }}
                                        @elseif( $row->blood_group == 7 )
                                        {{ __('O+') }}
                                        @elseif( $row->blood_group == 8 )
                                        {{ __('O-') }}
                                        @endif
                                    </p><hr/>
                                    @endif

                                    @if(field('student_national_id')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_national_id') }}:</mark> {{ $row->national_id }}</p><hr/>
                                    @endif
                                    @if(field('student_passport_no')->status == 1)
                                    <p><mark class="text-primary">{{ __('field_passport_no') }}:</mark> {{ $row->passport_no }}</p>
                                    @endif
                                    </fieldset>
                                </div>
                                <div class="col-md-6">
                                    @if(field('student_address')->status == 1)
                                    <fieldset class="row gx-2 scheduler-border">
                                    <legend>{{ __('field_present') }} {{ __('field_address') }}</legend>
                                    <p><mark class="text-primary">{{ __('field_province') }}:</mark> {{ $row->present_province ?? '' }}</p><hr/>
                                    <p><mark class="text-primary">{{ __('field_district') }}:</mark> {{ $row->present_district ?? '' }}</p><hr/>
                                    <p><mark class="text-primary">{{ __('field_address') }}:</mark> {{ $row->present_address }}</p>
                                    </fieldset>

                                    <fieldset class="row gx-2 scheduler-border">
                                    <legend>{{ __('field_permanent') }} {{ __('field_address') }}</legend>
                                    <p><mark class="text-primary">{{ __('field_province') }}:</mark> {{ $row->permanent_province ?? '' }}</p><hr/>
                                    <p><mark class="text-primary">{{ __('field_district') }}:</mark> {{ $row->permanent_district ?? '' }}</p><hr/>
                                    <p><mark class="text-primary">{{ __('field_address') }}:</mark> {{ $row->permanent_address }}</p>
                                    </fieldset>
                                    @endif

                                    <fieldset class="row gx-2 scheduler-border">
                                    <p><mark class="text-primary">{{ __('field_hostel') }}:</mark> {{ $row->hostelRoom->room->hostel->name ?? '' }}</p><hr/>
                                    <p><mark class="text-primary">{{ __('field_room') }}:</mark> {{ $row->hostelRoom->room->name ?? '' }}</p><hr/>
                                    </fieldset>
                                    <fieldset class="row gx-2 scheduler-border">
                                    <p><mark class="text-primary">{{ __('field_route') }}:</mark> {{ $row->transport->transportRoute->title ?? '' }}</p><hr/>
                                    <p><mark class="text-primary">{{ __('field_vehicle') }}:</mark> {{ $row->transport->transportVehicle->number ?? '' }}</p>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="pills-transcript-tab" data-bs-toggle="pill" href="#pills-transcript" role="tab" aria-controls="pills-transcript" aria-selected="true">{{ __('tab_transcript') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pills-educational-tab" data-bs-toggle="pill" href="#pills-educational" role="tab" aria-controls="pills-educational" aria-selected="false">{{ __('tab_educational_info') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pills-fees-tab" data-bs-toggle="pill" href="#pills-fees" role="tab" aria-controls="pills-fees" aria-selected="false">{{ __('tab_fees_assign') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pills-book-tab" data-bs-toggle="pill" href="#pills-book" role="tab" aria-controls="pills-book" aria-selected="false">{{ __('tab_book_issues') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pills-notes-tab" data-bs-toggle="pill" href="#pills-notes" role="tab" aria-controls="pills-notes" aria-selected="false">{{ __('tab_notes') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pills-leave-tab" data-bs-toggle="pill" href="#pills-leave" role="tab" aria-controls="pills-leave" aria-selected="false">{{ __('tab_leave') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pills-documents-tab" data-bs-toggle="pill" href="#pills-documents" role="tab" aria-controls="pills-documents" aria-selected="false">{{ __('tab_documents') }}</a>
                            </li>
                        </ul>
                        <div class="tab-content" id="pills-tabContent">
                            <div class="tab-pane fade show active" id="pills-transcript" role="tabpanel" aria-labelledby="pills-transcript-tab">

                                {{-- ===== CGPA / CREDITS SUMMARY STRIP ===== --}}
                                @php
                                    $at_total_cgpa = 0;
                                    $at_cgpa_credits = 0;
                                    $at_unique_courses = [];
                                @endphp
                                @foreach( $row->studentEnrolls as $key => $item )
                                    @if($item->program_id == $selectedProgramId && $item->matricule == $currentEnroll->matricule)
                                    @if(isset($item->subjectMarks))
                                    @foreach($item->subjectMarks as $mark)
                                        @if($mark->is_visible_to_student)
                                        @php
                                            $marks_per = round($mark->total_marks);
                                            $subject_id = $mark->subject_id;
                                            $credit_hour = $mark->subject->credit_hour;
                                            $is_passed = $marks_per >= 50;
                                        @endphp
                                        @foreach($grades as $grade)
                                            @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                                            @php
                                                $at_total_cgpa += ($grade->point * $credit_hour);
                                                $at_cgpa_credits += $credit_hour;
                                                if(!isset($at_unique_courses[$subject_id])) {
                                                    $at_unique_courses[$subject_id] = ['credits' => $credit_hour, 'passed' => $is_passed];
                                                } else {
                                                    if($is_passed && !$at_unique_courses[$subject_id]['passed']) {
                                                        $at_unique_courses[$subject_id]['passed'] = true;
                                                    }
                                                }
                                            @endphp
                                            @break
                                            @endif
                                        @endforeach
                                        @endif
                                    @endforeach
                                    @endif
                                    @endif
                                @endforeach
                                @php
                                    $at_credits_attempted = 0;
                                    $at_credits_earned = 0;
                                    $at_carry_over_count = 0;
                                    $at_carry_over_credits = 0;
                                    $at_active_resit_ids = isset($active_resit_subject_ids) ? array_flip($active_resit_subject_ids) : [];
                                    foreach($at_unique_courses as $sid => $course) {
                                        $at_credits_attempted += $course['credits'];
                                        if($course['passed']) {
                                            $at_credits_earned += $course['credits'];
                                        } elseif(!isset($at_active_resit_ids[$sid])) {
                                            // Failed and not currently in an active resit workflow -> true carry-over
                                            $at_carry_over_count++;
                                            $at_carry_over_credits += $course['credits'];
                                        }
                                    }
                                    $at_cgpa_base = $at_cgpa_credits > 0 ? $at_cgpa_credits : 1;
                                    $at_com_gpa = $at_total_cgpa / $at_cgpa_base;
                                @endphp

                                <div class="at-summary-strip">
                                    <div class="at-summary-item">
                                        <span class="at-summary-label">Cumulative GPA</span>
                                        <span class="at-summary-value at-summary-gpa">{{ number_format((float)$at_com_gpa, 2, '.', '') }}</span>
                                    </div>
                                    <div class="at-summary-divider"></div>
                                    <div class="at-summary-item">
                                        <span class="at-summary-label">{{ __('field_credits_attempted') }}</span>
                                        <span class="at-summary-value">{{ round($at_credits_attempted, 1) }}</span>
                                    </div>
                                    <div class="at-summary-divider"></div>
                                    <div class="at-summary-item">
                                        <span class="at-summary-label">{{ __('field_credits_earned') }}</span>
                                        <span class="at-summary-value">{{ round($at_credits_earned, 1) }}</span>
                                    </div>
                                    <div class="at-summary-divider"></div>
                                    <div class="at-summary-item">
                                        <span class="at-summary-label">Total Courses</span>
                                        <span class="at-summary-value">{{ count($at_unique_courses) }}</span>
                                    </div>
                                    <div class="at-summary-divider"></div>
                                    <div class="at-summary-item">
                                        <span class="at-summary-label">Carry-Over Courses</span>
                                        <span class="at-summary-value" @if($at_carry_over_count > 0) style="color:#fd7e14;" @endif>
                                            {{ $at_carry_over_count }}@if($at_carry_over_count > 0) <small style="font-weight:normal;color:#6c757d;">({{ rtrim(rtrim(number_format($at_carry_over_credits, 1), '0'), '.') }} cr.)</small>@endif
                                        </span>
                                    </div>
                                </div>

                                {{-- ===== GRADING SCALE (collapsible) ===== --}}
                                <div class="at-grade-toggle">
                                    <button class="at-toggle-btn" onclick="var p=document.getElementById('adminGradeScalePanel');p.classList.toggle('at-collapsed');this.querySelector('.at-toggle-chevron').classList.toggle('at-chevron-open')">
                                        <i class="fas fa-list-ol"></i> Grading Scale Reference
                                        <i class="fas fa-chevron-down at-toggle-chevron"></i>
                                    </button>
                                </div>
                                <div id="adminGradeScalePanel" class="at-grade-panel at-collapsed" style="margin-bottom:16px;">
                                    <table class="at-grade-table">
                                        <thead>
                                            <tr>
                                                <th>Grade</th>
                                                <th>Grade Point</th>
                                                <th>Mark Range</th>
                                                <th>Classification</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($grades as $grade)
                                            <tr>
                                                <td><strong>{{ $grade->title }}</strong></td>
                                                <td>{{ number_format((float)$grade->point, 2, '.', '') }}</td>
                                                <td>{{ number_format((float)$grade->min_mark, 0) }}% &ndash; {{ number_format((float)$grade->max_mark, 0) }}%</td>
                                                <td>
                                                    @if($grade->point >= 3.5)
                                                        <span class="at-class-badge at-class-distinction">Distinction</span>
                                                    @elseif($grade->point >= 3.0)
                                                        <span class="at-class-badge at-class-merit">Merit</span>
                                                    @elseif($grade->point >= 2.0)
                                                        <span class="at-class-badge at-class-pass">Pass</span>
                                                    @elseif($grade->point >= 1.0)
                                                        <span class="at-class-badge at-class-marginal">Marginal</span>
                                                    @else
                                                        <span class="at-class-badge at-class-fail">Fail</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- ===== GPA TREND CHART ===== --}}
                                @if(isset($gpa_trend) && count($gpa_trend) > 0)
                                <div class="at-chart-section">
                                    <div class="at-section-heading">
                                        <i class="fas fa-chart-line"></i> Academic Performance Trend
                                    </div>
                                    <div class="at-chart-controls">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-secondary active" onclick="updateChartType('line')" id="btn-line">
                                                <i class="fas fa-chart-line"></i> Line
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="updateChartType('bar')" id="btn-bar">
                                                <i class="fas fa-chart-bar"></i> Bar
                                            </button>
                                        </div>
                                        <div>
                                            <span class="at-legend-dot" style="background:#2563eb;"></span> Semester GPA
                                            <span class="at-legend-dot" style="background:#059669; margin-left:12px;"></span> Cumulative GPA
                                        </div>
                                    </div>
                                    <div style="position:relative; width:100%; max-height:280px;">
                                        <canvas id="gpaChart"></canvas>
                                    </div>

                                    {{-- Performance Stats Row --}}
                                    @php
                                        $total_semesters = count($gpa_trend);
                                        $highest_semester_gpa = $total_semesters > 0 ? max(array_column($gpa_trend, 'semester_gpa')) : 0;
                                        $current_cgpa_chart = $total_semesters > 0 ? end($gpa_trend)['cumulative_gpa'] : 0;
                                        $total_credits_earned_chart = $total_semesters > 0 ? end($gpa_trend)['cumulative_credits'] : 0;

                                        $trend_direction = 'Stable';
                                        $trend_icon = 'fa-minus';
                                        $trend_color = '#6b7280';
                                        if ($total_semesters >= 2) {
                                            $recent_gpa = $gpa_trend[$total_semesters - 1]['semester_gpa'];
                                            $previous_gpa = $gpa_trend[$total_semesters - 2]['semester_gpa'];
                                            if ($recent_gpa > $previous_gpa) {
                                                $trend_direction = 'Improving';
                                                $trend_icon = 'fa-arrow-up';
                                                $trend_color = '#059669';
                                            } elseif ($recent_gpa < $previous_gpa) {
                                                $trend_direction = 'Declining';
                                                $trend_icon = 'fa-arrow-down';
                                                $trend_color = '#dc2626';
                                            }
                                        }
                                    @endphp

                                    <div class="at-stats-row">
                                        <div class="at-stat-card">
                                            <div class="at-stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-graduation-cap"></i></div>
                                            <div class="at-stat-body">
                                                <span class="at-stat-number">{{ number_format($current_cgpa_chart, 2) }}</span>
                                                <span class="at-stat-label">Current CGPA</span>
                                            </div>
                                        </div>
                                        <div class="at-stat-card">
                                            <div class="at-stat-icon" style="background:#ecfdf5;color:#059669;"><i class="fas fa-trophy"></i></div>
                                            <div class="at-stat-body">
                                                <span class="at-stat-number">{{ number_format($highest_semester_gpa, 2) }}</span>
                                                <span class="at-stat-label">Best Semester</span>
                                            </div>
                                        </div>
                                        <div class="at-stat-card">
                                            <div class="at-stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas {{ $trend_icon }}"></i></div>
                                            <div class="at-stat-body">
                                                <span class="at-stat-number" style="color:{{ $trend_color }}">{{ $trend_direction }}</span>
                                                <span class="at-stat-label">Trend</span>
                                            </div>
                                        </div>
                                        <div class="at-stat-card">
                                            <div class="at-stat-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-book"></i></div>
                                            <div class="at-stat-body">
                                                <span class="at-stat-number">{{ number_format($total_credits_earned_chart, 1) }}</span>
                                                <span class="at-stat-label">Credits Earned</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Performance Insights --}}
                                    <div class="at-insights">
                                        <div class="at-insights-icon"><i class="fas fa-lightbulb"></i></div>
                                        <div class="at-insights-body">
                                            <strong>Performance Insights</strong>
                                            <ul>
                                                @if($current_cgpa_chart >= 3.5)
                                                    <li>Outstanding achievement &mdash; student is maintaining an excellent CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>.</li>
                                                @elseif($current_cgpa_chart >= 3.0)
                                                    <li>Strong performance with a CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>. On track for distinction.</li>
                                                @elseif($current_cgpa_chart >= 2.0)
                                                    <li>Satisfactory CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>. Room for improvement.</li>
                                                @else
                                                    <li>CGPA is <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>. Academic advising is recommended.</li>
                                                @endif
                                                <li>Completed <strong>{{ $total_semesters }}</strong> semester(s) with <strong>{{ number_format($total_credits_earned_chart, 1) }}</strong> credits earned.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                {{-- ===== SEMESTER ACADEMIC RECORDS ===== --}}
                                @php
                                    $semester_items = [];
                                    $semester_keys = [];
                                @endphp

                                @foreach( $row->studentEnrolls as $key => $enroll )
                                @if(isset($enroll->session) && isset($enroll->semester) && isset($enroll->section) && $enroll->program_id == $selectedProgramId && $enroll->matricule == $currentEnroll->matricule)
                                @php
                                    $semester_key = $enroll->session->title . '|' . $enroll->semester->title;
                                    if(!in_array($semester_key, $semester_keys)){
                                        array_push($semester_items, array($enroll->session->title, $enroll->semester->title, $enroll->section->title));
                                        array_push($semester_keys, $semester_key);
                                    }
                                @endphp
                                @endif
                                @endforeach

                                @foreach($semester_items as $semIdx => $semester_item)
                                <div class="at-semester-block">
                                    {{-- Semester Header --}}
                                    <div class="at-semester-header">
                                        <div class="at-semester-header-left">
                                            <span class="at-semester-number">{{ $semIdx + 1 }}</span>
                                            <div>
                                                <h3 class="at-semester-title">{{ $semester_item[1] }}</h3>
                                                <span class="at-semester-session">{{ $semester_item[0] }} &bull; {{ $semester_item[2] }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Semester Table --}}
                                    <div class="at-table-wrapper">
                                        <table class="at-academic-table">
                                            <thead>
                                                <tr>
                                                    <th class="at-col-code">{{ __('field_code') }}</th>
                                                    <th class="at-col-title">{{ __('field_subject') }}</th>
                                                    <th class="at-col-type">Type</th>
                                                    <th class="at-col-num">Credit Hrs</th>
                                                    <th class="at-col-num">Attempted</th>
                                                    <th class="at-col-num">Earned</th>
                                                    <th class="at-col-num">Grade Pt</th>
                                                    <th class="at-col-grade">{{ __('field_grade') }}</th>
                                                    <th class="at-col-num">Quality Pts</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $semester_credits = 0;
                                                    $semester_cgpa = 0;
                                                    $semester_credits_earned = 0;
                                                @endphp
                                                @foreach( $row->studentEnrolls as $key => $item )
                                                @if(isset($item->semester) && isset($item->session) && $semester_item[1] == $item->semester->title && $semester_item[0] == $item->session->title && $item->program_id == $selectedProgramId && $item->matricule == $currentEnroll->matricule)

                                                @foreach( $item->subjects as $subject )
                                                @php
                                                    $creditsAttempted = (float) $subject->credit_hour;
                                                    $semester_credits += $creditsAttempted;
                                                    $subject_grade = null;
                                                    $subjectGradePoint = null;
                                                    $subjectQualityPoints = null;
                                                    $creditsEarned = 0;
                                                    $isPassed = false;
                                                    if(isset($item->subjectMarks)){
                                                        foreach($item->subjectMarks as $mark){
                                                            if($mark->subject_id == $subject->id){
                                                                if($mark->is_visible_to_student){
                                                                    $marks_per = round($mark->total_marks);
                                                                    $isPassed = $marks_per >= 50;
                                                                    foreach($grades as $grade){
                                                                        if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark){
                                                                            $subjectGradePoint = (float) $grade->point;
                                                                            $subjectQualityPoints = $subjectGradePoint * $creditsAttempted;
                                                                            $semester_cgpa += $subjectQualityPoints;
                                                                            if($subjectGradePoint > 0){
                                                                                $semester_credits_earned += $creditsAttempted;
                                                                                $creditsEarned = $creditsAttempted;
                                                                            }
                                                                            $subject_grade = $grade->title;
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                                break;
                                                            }
                                                        }
                                                    }
                                                @endphp

                                                <tr class="{{ !$isPassed && !is_null($subjectGradePoint) ? 'at-row-fail' : '' }}">
                                                    <td class="at-cell-code">{{ $subject->code }}</td>
                                                    <td class="at-cell-title">{{ $subject->title }}</td>
                                                    <td class="at-cell-type">
                                                        @if($subject->subject_type == 1)
                                                            <span class="at-type-badge at-type-core">C</span>
                                                        @elseif($subject->subject_type == 2)
                                                            <span class="at-type-badge at-type-ur">UR</span>
                                                        @else
                                                            <span class="at-type-badge at-type-elective">E</span>
                                                        @endif
                                                    </td>
                                                    <td class="at-cell-num">{{ number_format($creditsAttempted, 1) }}</td>
                                                    <td class="at-cell-num">{{ number_format($creditsAttempted, 1) }}</td>
                                                    <td class="at-cell-num">
                                                        @if(!is_null($subjectGradePoint))
                                                            {{ number_format($creditsEarned, 1) }}
                                                        @else
                                                            <span class="at-pending">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td class="at-cell-num">
                                                        @if(!is_null($subjectGradePoint))
                                                            {{ number_format($subjectGradePoint, 2) }}
                                                        @else
                                                            <span class="at-pending">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td class="at-cell-grade">
                                                        @if($subject_grade)
                                                            <span class="at-grade-pill {{ $isPassed ? 'at-grade-pass' : 'at-grade-fail' }}">{{ $subject_grade }}</span>
                                                        @else
                                                            <span class="at-pending-badge">Pending</span>
                                                        @endif
                                                    </td>
                                                    <td class="at-cell-num">
                                                        @if(!is_null($subjectQualityPoints))
                                                            {{ number_format($subjectQualityPoints, 2) }}
                                                        @else
                                                            <span class="at-pending">&mdash;</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach

                                                @endif
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                @php
                                                    $semesterGpa = $semester_credits > 0 ? $semester_cgpa / $semester_credits : 0;
                                                @endphp
                                                <tr class="at-footer-totals">
                                                    <td colspan="3"><strong>{{ __('field_term_total') }}</strong></td>
                                                    <td class="at-cell-num"><strong>{{ number_format((float)$semester_credits, 1) }}</strong></td>
                                                    <td class="at-cell-num"><strong>{{ number_format((float)$semester_credits, 1) }}</strong></td>
                                                    <td class="at-cell-num"><strong>{{ number_format((float)$semester_credits_earned, 1) }}</strong></td>
                                                    <td colspan="2"></td>
                                                    <td class="at-cell-num"><strong>{{ number_format((float)$semester_cgpa, 2) }}</strong></td>
                                                </tr>
                                                <tr class="at-footer-gpa">
                                                    <td colspan="4"><strong>{{ __('field_semester_gpa') }}</strong></td>
                                                    <td colspan="5" class="at-gpa-value">
                                                        <strong>{{ number_format((float)$semesterGpa, 2) }}</strong>
                                                        @if($semesterGpa >= 3.5)
                                                            <span class="at-standing at-standing-distinction">Dean's List</span>
                                                        @elseif($semesterGpa >= 3.0)
                                                            <span class="at-standing at-standing-good">Good Standing</span>
                                                        @elseif($semesterGpa >= 2.0)
                                                            <span class="at-standing at-standing-satisfactory">Satisfactory</span>
                                                        @elseif($semesterGpa > 0)
                                                            <span class="at-standing at-standing-warning">Academic Warning</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                @endforeach

                            </div>
                            <div class="tab-pane fade" id="pills-educational" role="tabpanel" aria-labelledby="pills-educational-tab">
                                <div class="row">
                                    <div class="col-md-4">
                                        <fieldset class="row gx-2 scheduler-border">
                                        <p><mark class="text-primary">{{ __('field_batch') }}:</mark> {{ $row->batch->title ?? '' }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_program') }}:</mark> {{ $currentEnroll->program->title ?? $row->program->title ?? '' }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_session') }}:</mark> {{ $currentEnroll->session->title ?? '' }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_semester') }}:</mark> {{ $currentEnroll->semester->title ?? '' }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_section') }}:</mark> {{ $currentEnroll->section->title ?? '' }}</p><hr/>

                                        <p><mark class="text-primary">{{ __('field_status') }}:</mark> 
                                        @foreach($row->statuses as $key => $status)
                                            <span class="badge badge-primary">{{ $status->title }}</span>
                                        @endforeach
                                        </p><hr/>
                                        </fieldset>
                                    </div>
                                    <div class="col-md-4">
                                        @if(field('student_school_info')->status == 1)
                                        <fieldset class="row gx-2 scheduler-border">
                                        <legend>{{ __('field_school_information') }}</legend>
                                        <p><mark class="text-primary">{{ __('field_school_name') }}:</mark> {{ $row->school_name }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_exam_id') }}:</mark> {{ $row->school_exam_id }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_graduation_year') }}:</mark> {{ $row->school_graduation_year }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_graduation_point') }}:</mark> {{ $row->school_graduation_point }}</p><hr/>
                                        </fieldset>
                                        @endif
                                        
                                        @if(field('student_collage_info')->status == 1)
                                        <fieldset class="row gx-2 scheduler-border">
                                        <legend>{{ __('field_college_information') }}</legend>
                                        <p><mark class="text-primary">{{ __('field_collage_name') }}:</mark> {{ $row->collage_name }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_exam_id') }}:</mark> {{ $row->collage_exam_id }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_graduation_year') }}:</mark> {{ $row->collage_graduation_year }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_graduation_point') }}:</mark> {{ $row->collage_graduation_point }}</p><hr/>
                                        </fieldset>
                                        @endif
                                    </div>
                                    <div class="col-md-4">
                                        @if(field('student_relatives')->status == 1)
                                        @foreach($row->relatives as $key => $relative)
                                        <fieldset class="row gx-2 scheduler-border">
                                        <legend>{{ __('field_guardians_information') }}-{{ $key + 1 }}</legend>
                                        <p><mark class="text-primary">{{ __('field_relation') }}:</mark> {{ $relative->relation }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_name') }}:</mark> {{ $relative->name }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_occupation') }}:</mark> {{ $relative->occupation }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_phone') }}:</mark> {{ $relative->phone }}</p><hr/>
                                        <p><mark class="text-primary">{{ __('field_address') }}:</mark> {{ $relative->address }}</p><hr/>
                                        </fieldset>
                                        @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="pills-fees" role="tabpanel" aria-labelledby="pills-fees-tab">
                                <!-- [ Data table ] start -->
                                @isset($fees)
                                <div class="table-responsive">
                                    <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>{{ __('field_session') }}</th>
                                                <th>{{ __('field_semester') }}</th>
                                                <th>{{ __('field_fees_type') }}</th>
                                                <th>{{ __('field_fee') }}</th>
                                                <th>{{ __('field_discount') }}</th>
                                                <th>{{ __('field_fine_amount') }}</th>
                                                <th>{{ __('field_net_amount') }}</th>
                                                <th>{{ __('field_due_date') }}</th>
                                                <th>{{ __('field_status') }}</th>
                                                <th>{{ __('field_pay_date') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                          @foreach( $fees->sortByDesc('id') as $key => $row )
                                          @if($row->studentEnroll && $row->studentEnroll->matricule == $currentEnroll->matricule)
                                          @if($row->status == 0)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $row->studentEnroll->session->title ?? '' }}</td>
                                                <td>{{ $row->studentEnroll->semester->title ?? '' }}</td>
                                                <td>{{ $row->category->title ?? '' }}</td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$row->fee_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$row->fee_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @php 
                                                    $discount_amount = 0;
                                                    $today = date('Y-m-d');
                                                    @endphp

                                                    @isset($row->category)
                                                    @foreach($row->category->discounts->where('status', '1') as $discount)

                                                    @php
                                                    $availability = \App\Models\FeesDiscount::availability($discount->id, $row->studentEnroll->student_id);
                                                    @endphp

                                                    @if(isset($availability))
                                                    @if($discount->start_date <= $today && $discount->end_date >= $today)
                                                        @if($discount->type == '1')
                                                            @php
                                                            $discount_amount = $discount_amount + $discount->amount;
                                                            @endphp
                                                        @else
                                                            @php
                                                            $discount_amount = $discount_amount + ( ($row->fee_amount / 100) * $discount->amount);
                                                            @endphp
                                                        @endif
                                                    @endif
                                                    @endif
                                                    @endforeach
                                                    @endisset


                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$discount_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$discount_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @php
                                                        $fine_amount = 0;
                                                    @endphp
                                                    @if(empty($row->pay_date) || $row->due_date < $row->pay_date)
                                                        
                                                        @php
                                                        $due_date = strtotime($row->due_date);
                                                        $today = strtotime(date('Y-m-d')); 
                                                        $days = (int)(($today - $due_date)/86400);
                                                        @endphp

                                                        @if($row->due_date < date("Y-m-d"))
                                                        @isset($row->category)
                                                        @foreach($row->category->fines->where('status', '1') as $fine)
                                                        @if($fine->start_day <= $days && $fine->end_day >= $days)
                                                            @if($fine->type == '1')
                                                                @php
                                                                $fine_amount = $fine_amount + $fine->amount;
                                                                @endphp
                                                            @else
                                                                @php
                                                                $fine_amount = $fine_amount + ( ($row->fee_amount / 100) * $fine->amount);
                                                                @endphp
                                                            @endif
                                                        @endif
                                                        @endforeach
                                                        @endisset
                                                        @endif
                                                    @endif


                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$fine_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$fine_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @php
                                                    $net_amount = ($row->fee_amount - $discount_amount) + $fine_amount;
                                                    @endphp

                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$net_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$net_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                    {{ date($setting->date_format, strtotime($row->due_date)) }}
                                                    @else
                                                    {{ date("Y-m-d", strtotime($row->due_date)) }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($row->status == 1)
                                                    <span class="badge badge-pill badge-success">{{ __('status_paid') }}</span>
                                                    @elseif($row->status == 2)
                                                    <span class="badge badge-pill badge-danger">{{ __('status_canceled') }}</span>
                                                    @else
                                                    <span class="badge badge-pill badge-primary">{{ __('status_pending') }}</span>
                                                    @endif
                                                </td>
                                                <td></td>
                                            </tr>

                                          @elseif($row->status == 1)

                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $row->studentEnroll->session->title ?? '' }}</td>
                                                <td>{{ $row->studentEnroll->semester->title ?? '' }}</td>
                                                <td>{{ $row->category->title ?? '' }}</td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$row->fee_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$row->fee_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$row->discount_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$row->discount_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$row->fine_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$row->fine_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @php $display_paid = min((float) $row->paid_amount, (float) $row->total_amount); @endphp
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$display_paid, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$display_paid, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                    {{ date($setting->date_format, strtotime($row->due_date)) }}
                                                    @else
                                                    {{ date("Y-m-d", strtotime($row->due_date)) }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($row->status == 1)
                                                    <span class="badge badge-pill badge-success">{{ __('status_paid') }}</span>
                                                    @elseif($row->status == 2)
                                                    <span class="badge badge-pill badge-danger">{{ __('status_canceled') }}</span>
                                                    @else
                                                    <span class="badge badge-pill badge-primary">{{ __('status_pending') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                    {{ date($setting->date_format, strtotime($row->pay_date)) }}
                                                    @else
                                                    {{ date("Y-m-d", strtotime($row->pay_date)) }}
                                                    @endif
                                                </td>
                                            </tr>
                                          @endif
                                          @endif
                                          @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                                <!-- [ Data table ] end -->
                            </div>
                            <div class="tab-pane fade" id="pills-book" role="tabpanel" aria-labelledby="pills-book-tab">
                                <!-- [ Data table ] start -->
                                <div class="table-responsive">
                                    <table id="basic-table2" class="display table nowrap table-striped table-hover" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>{{ __('field_isbn') }}</th>
                                                <th>{{ __('field_book') }}</th>
                                                <th>{{ __('field_issue_date') }}</th>
                                                <th>{{ __('field_due_return_date') }}</th>
                                                <th>{{ __('field_return_date') }}</th>
                                                <th>{{ __('field_status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                          @isset($student->member)
                                          @foreach( $student->member->issuReturn->sortByDesc('id') as $key => $row )
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $row->book->isbn ?? '' }}</td>
                                                <td>{{ $row->book->title ?? '' }}</td>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                    {{ date($setting->date_format, strtotime($row->issue_date)) }}
                                                    @else
                                                    {{ date("Y-m-d", strtotime($row->issue_date)) }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                    {{ date($setting->date_format, strtotime($row->due_date)) }}
                                                    @else
                                                    {{ date("Y-m-d", strtotime($row->due_date)) }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(!empty($row->return_date))
                                                    @if(isset($setting->date_format))
                                                        {{ date($setting->date_format, strtotime($row->return_date)) }}
                                                    @else
                                                        {{ date("Y-m-d", strtotime($row->return_date)) }}
                                                    @endif
                                                    @endif
                                                </td>
                                                <td>
                                                    @if( $row->status == 0 )
                                                    <span class="badge badge-pill badge-danger">{{ __('status_lost') }}</span>

                                                    @elseif( $row->status == 1 )
                                                    @if($row->due_date < date("Y-m-d"))
                                                    <span class="badge badge-pill badge-danger">{{ __('status_delay') }}</span>
                                                    @else
                                                    <span class="badge badge-pill badge-primary">{{ __('status_issued') }}</span>
                                                    @endif

                                                    @elseif( $row->status == 2 )
                                                    <span class="badge badge-pill badge-success">{{ __('status_returned') }}</span>
                                                    @if($row->due_date < $row->return_date)
                                                    <span class="badge badge-pill badge-danger">{{ __('status_delayed') }}</span>
                                                    @endif
                                                    @endif
                                                </td>
                                            </tr>
                                          @endforeach
                                          @endisset
                                        </tbody>
                                    </table>
                                </div>
                                <!-- [ Data table ] end -->
                            </div>
                            <div class="tab-pane fade" id="pills-notes" role="tabpanel" aria-labelledby="pills-notes-tab">
                                <!-- [ Data table ] start -->
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>{{ __('field_date') }}</th>
                                                <th>{{ __('field_title') }}</th>
                                                <th>{{ __('field_note') }}</th>
                                                <th>{{ __('field_attach') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($student->notes->where('status', 1)->sortBy('id') as $note)
                                            <tr>
                                                <td>
                                                @if(isset($setting->date_format))
                                                {{ date($setting->date_format, strtotime($note->created_at)) }}
                                                @else
                                                {{ date("Y-m-d", strtotime($note->created_at)) }}
                                                @endif
                                                </td>
                                                <td>{{ $note->title }}</td>
                                                <td>{{ $note->description }}</td>
                                                <td>
                                                @if(is_file('uploads/note/'.$note->attach))
                                                <a href="{{ asset('uploads/note/'.$note->attach) }}" class="btn btn-sm btn-icon btn-dark" download><i class="fas fa-download"></i></a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- [ Data table ] end -->
                            </div>
                            <div class="tab-pane fade" id="pills-leave" role="tabpanel" aria-labelledby="pills-leave-tab">
                                <!-- [ Data table ] start -->
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>{{ __('field_leave_date') }}</th>
                                                <th>{{ __('field_days') }}</th>
                                                <th>{{ __('field_apply_date') }}</th>
                                                <th>{{ __('field_status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($student->leaves->sortByDesc('id') as $leave)
                                            <tr>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                        {{ date($setting->date_format, strtotime($leave->from_date)) }}
                                                    @else
                                                        {{ date("Y-m-d", strtotime($leave->from_date)) }}
                                                    @endif
                                                    -
                                                    @if(isset($setting->date_format))
                                                        {{ date($setting->date_format, strtotime($leave->to_date)) }}
                                                    @else
                                                        {{ date("Y-m-d", strtotime($leave->to_date)) }}
                                                    @endif
                                                </td>
                                                <td>{{ (int)((strtotime($leave->to_date) - strtotime($leave->from_date))/86400) + 1 }}</td>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                        {{ date($setting->date_format, strtotime($leave->apply_date)) }}
                                                    @else
                                                        {{ date("Y-m-d", strtotime($leave->apply_date)) }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if( $leave->status == 1 )
                                                    <span class="badge badge-pill badge-success">{{ __('status_approved') }}</span>
                                                    @elseif( $leave->status == 2 )
                                                    <span class="badge badge-pill badge-danger">{{ __('status_rejected') }}</span>
                                                    @else
                                                    <span class="badge badge-pill badge-primary">{{ __('status_pending') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- [ Data table ] end -->
                            </div>
                            <div class="tab-pane fade" id="pills-documents" role="tabpanel" aria-labelledby="pills-documents-tab">
                                <!-- [ Data table ] start -->
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>{{ __('field_title') }}</th>
                                                <th>{{ __('field_document') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(field('student_photo')->status == 1)
                                            <tr>
                                                <td>{{ __('field_photo') }}</td>
                                                <td>
                                                @if(is_file('uploads/'.$path.'/'.$student->photo))
                                                <a href="{{ asset('uploads/'.$path.'/'.$student->photo) }}" data-lightbox="gallery">
                                                    <img src="{{ asset('uploads/'.$path.'/'.$student->photo) }}" class="img-fluid field-image">
                                                </a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endif
                                            @if(field('student_signature')->status == 1)
                                            <tr>
                                                <td>{{ __('field_signature') }}</td>
                                                <td>
                                                @if(is_file('uploads/'.$path.'/'.$student->signature))
                                                <a href="{{ asset('uploads/'.$path.'/'.$student->signature) }}" data-lightbox="gallery">
                                                    <img src="{{ asset('uploads/'.$path.'/'.$student->signature) }}" class="img-fluid field-image">
                                                </a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endif
                                            @if(field('student_school_transcript')->status == 1)
                                            <tr>
                                                <td>{{ __('field_school_transcript') }}</td>
                                                <td>
                                                @if(is_file('uploads/'.$path.'/'.$student->school_transcript))
                                                <a href="{{ asset('uploads/'.$path.'/'.$student->school_transcript) }}" data-lightbox="gallery">
                                                    <img src="{{ asset('uploads/'.$path.'/'.$student->school_transcript) }}" class="img-fluid field-image">
                                                </a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endif
                                            @if(field('student_school_certificate')->status == 1)
                                            <tr>
                                                <td>{{ __('field_school_certificate') }}</td>
                                                <td>
                                                @if(is_file('uploads/'.$path.'/'.$student->school_certificate))
                                                <a href="{{ asset('uploads/'.$path.'/'.$student->school_certificate) }}" data-lightbox="gallery">
                                                    <img src="{{ asset('uploads/'.$path.'/'.$student->school_certificate) }}" class="img-fluid field-image">
                                                </a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endif
                                            @if(field('student_collage_transcript')->status == 1)
                                            <tr>
                                                <td>{{ __('field_collage_transcript') }}</td>
                                                <td>
                                                @if(is_file('uploads/'.$path.'/'.$student->collage_transcript))
                                                <a href="{{ asset('uploads/'.$path.'/'.$student->collage_transcript) }}" data-lightbox="gallery">
                                                    <img src="{{ asset('uploads/'.$path.'/'.$student->collage_transcript) }}" class="img-fluid field-image">
                                                </a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endif
                                            @if(field('student_collage_certificate')->status == 1)
                                            <tr>
                                                <td>{{ __('field_collage_certificate') }}</td>
                                                <td>
                                                @if(is_file('uploads/'.$path.'/'.$student->collage_certificate))
                                                <a href="{{ asset('uploads/'.$path.'/'.$student->collage_certificate) }}" data-lightbox="gallery">
                                                    <img src="{{ asset('uploads/'.$path.'/'.$student->collage_certificate) }}" class="img-fluid field-image">
                                                </a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endif
                                            @foreach($student->documents as $document)
                                            <tr>
                                                <td>{{ $document->title }}</td>
                                                <td>
                                                @if(is_file('uploads/'.$path.'/'.$document->attach))
                                                <a target="__blank" href="{{ asset('uploads/'.$path.'/'.$document->attach) }}" class="btn btn-sm btn-icon btn-dark" download><i class="fas fa-download"></i></a>
                                                @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- [ Data table ] end -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/lightbox2-master/js/lightbox.min.js') }}"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script type="text/javascript">
'use strict';

@if(isset($gpa_trend) && count($gpa_trend) > 0)
// Prepare data from backend
var gpaData = {!! json_encode($gpa_trend) !!};

// Extract data for charts
var labels = gpaData.map(function(item) { return item.label; });
var semesterGPA = gpaData.map(function(item) { return item.semester_gpa; });
var cumulativeGPA = gpaData.map(function(item) { return item.cumulative_gpa; });

// Chart configuration
var chartConfig = {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Semester GPA',
            data: semesterGPA,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: '#2563eb',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }, {
            label: 'Cumulative GPA',
            data: cumulativeGPA,
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.08)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: '#059669',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2.8,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                titleFont: { size: 13, weight: 'bold' },
                bodyFont: { size: 12 },
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2);
                    },
                    afterLabel: function(ctx) {
                        var d = gpaData[ctx.dataIndex];
                        return 'Credits: ' + (d.credits || d.cumulative_credits || '');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 4.0,
                ticks: {
                    stepSize: 0.5,
                    font: { size: 11 },
                    color: '#94a3b8',
                    callback: function(v) { return v.toFixed(1); }
                },
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                title: { display: true, text: 'GPA (0.0 - 4.0)', font: { size: 12, weight: 'bold' }, color: '#64748b' }
            },
            x: {
                ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 45, minRotation: 45 },
                grid: { display: false }
            }
        }
    }
};

// Initialize chart
var ctx = document.getElementById('gpaChart').getContext('2d');
var gpaChart = new Chart(ctx, chartConfig);

// Function to update chart type
window.updateChartType = function(type) {
    gpaChart.destroy();
    chartConfig.type = type;
    if (type === 'bar') {
        chartConfig.data.datasets[0].backgroundColor = 'rgba(37, 99, 235, 0.65)';
        chartConfig.data.datasets[1].backgroundColor = 'rgba(5, 150, 105, 0.65)';
        chartConfig.data.datasets[0].borderWidth = 2;
        chartConfig.data.datasets[1].borderWidth = 2;
    } else {
        chartConfig.data.datasets[0].backgroundColor = 'rgba(37, 99, 235, 0.08)';
        chartConfig.data.datasets[1].backgroundColor = 'rgba(5, 150, 105, 0.08)';
        chartConfig.data.datasets[0].borderWidth = 3;
        chartConfig.data.datasets[1].borderWidth = 3;
    }
    gpaChart = new Chart(ctx, chartConfig);
    document.getElementById('btn-line').classList.toggle('active', type === 'line');
    document.getElementById('btn-bar').classList.toggle('active', type === 'bar');
};
@endif
</script>
@endsection