@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    /* Filter Section */
    .filter-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        color: white;
        margin-bottom: 25px;
    }
    
    .filter-card .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .filter-card .card-header h5 {
        color: white;
        font-weight: 600;
    }
    
    .filter-card label {
        color: rgba(255,255,255,0.9);
        font-weight: 500;
        font-size: 13px;
    }
    
    .filter-card .form-control {
        background: rgba(255,255,255,0.95);
        border: none;
        border-radius: 8px;
    }
    
    .filter-card .btn-filter {
        background: white;
        color: #667eea;
        font-weight: 600;
        border-radius: 8px;
        padding: 10px 25px;
    }
    
    .filter-card .btn-filter:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    /* Primary vs Secondary Filters */
    .primary-filters {
        border-right: 1px solid rgba(255,255,255,0.2);
        padding-right: 20px;
    }
    
    .secondary-filters {
        padding-left: 20px;
        opacity: 0.9;
    }
    
    .secondary-filters label {
        font-size: 12px;
    }
    
    /* Statistics Cards */
    .stats-row {
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        border-left: 4px solid;
    }
    
    .stat-card.exams { border-color: #4CAF50; }
    .stat-card.programs { border-color: #2196F3; }
    .stat-card.subjects { border-color: #FF9800; }
    .stat-card.days { border-color: #9C27B0; }
    .stat-card.range { border-color: #00BCD4; }
    
    .stat-card .stat-number {
        font-size: 24px;
        font-weight: 700;
        color: #333;
    }
    
    .stat-card .stat-label {
        font-size: 11px;
        text-transform: uppercase;
        color: #888;
    }
    
    /* Table Styles */
    .schedule-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .schedule-table thead th {
        background: #343a40;
        color: white;
        padding: 12px 10px;
        font-weight: 600;
        text-align: left;
        border: none;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .schedule-table tbody td {
        padding: 10px 8px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
    }
    
    .schedule-table tbody tr:hover {
        background: #f1f5f9;
    }
    
    /* Row Number */
    .row-num {
        color: #6c757d;
        font-weight: 500;
        text-align: center;
    }
    
    /* Date Column Styling */
    .date-cell {
        background: #f8f9fa;
        border-left: 3px solid #667eea;
    }
    
    .date-cell .date-day {
        display: block;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        color: #667eea;
    }
    
    .date-cell .date-full {
        display: block;
        font-weight: 600;
        color: #333;
        font-size: 12px;
    }
    
    .date-cell.date-repeat {
        background: transparent;
        border-left-color: transparent;
    }
    
    .date-cell .date-continuation {
        color: #adb5bd;
        font-size: 16px;
    }
    
    .today-badge {
        display: inline-block;
        background: #28a745;
        color: white;
        font-size: 9px;
        padding: 2px 5px;
        border-radius: 3px;
        font-weight: 700;
        margin-top: 2px;
    }
    
    /* Date row grouping */
    .date-start {
        border-top: 2px solid #dee2e6;
    }
    
    .date-start:first-child {
        border-top: none;
    }
    
    /* Today/Past row highlighting */
    .today-row {
        background: rgba(40, 167, 69, 0.05);
    }
    
    .today-row:hover {
        background: rgba(40, 167, 69, 0.1);
    }
    
    .today-row .date-cell {
        background: rgba(40, 167, 69, 0.1);
        border-left-color: #28a745;
    }
    
    .past-row {
        opacity: 0.7;
    }
    
    .past-row .date-cell {
        border-left-color: #adb5bd;
    }
    
    /* Time Column */
    .time-cell {
        white-space: nowrap;
    }
    
    .time-range {
        font-weight: 600;
        color: #495057;
    }
    
    /* Course Column */
    .course-cell .course-code {
        display: block;
        color: #6c757d;
        font-size: 11px;
        font-weight: 500;
    }
    
    .course-cell .course-title {
        display: block;
        font-weight: 500;
        color: #333;
        line-height: 1.3;
    }
    
    /* Badge styles */
    .program-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .faculty-1 { background: rgba(76, 175, 80, 0.15); color: #2E7D32; }
    .faculty-2 { background: rgba(33, 150, 243, 0.15); color: #1565C0; }
    .faculty-3 { background: rgba(255, 152, 0, 0.15); color: #E65100; }
    .faculty-4 { background: rgba(156, 39, 176, 0.15); color: #7B1FA2; }
    .faculty-5 { background: rgba(0, 188, 212, 0.15); color: #00838F; }
    .faculty-6 { background: rgba(244, 67, 54, 0.15); color: #C62828; }
    .faculty-7 { background: rgba(139, 195, 74, 0.15); color: #558B2F; }
    .faculty-8 { background: rgba(103, 58, 183, 0.15); color: #4527A0; }
    
    /* Other cell styles */
    .semester-cell {
        font-size: 12px;
    }
    
    .room-cell, .supervisor-cell {
        font-size: 12px;
        color: #495057;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 15px;
    }
    
    .empty-state i {
        font-size: 60px;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    
    .empty-state h4 {
        color: #6c757d;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #adb5bd;
    }
    
    /* Print Header */
    .print-header {
        display: none;
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #333;
    }
    
    .print-header h2 {
        margin: 0;
        font-size: 20px;
    }
    
    .print-header .print-subtitle {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }
    
    .print-header .print-meta {
        font-size: 12px;
        color: #888;
        margin-top: 10px;
    }
    
    /* Print Styles */
    @media print {
        /* Hide layout elements */
        .pcoded-navbar,
        .pcoded-header,
        .announcement-bar,
        .navbar,
        nav,
        header,
        .breadcrumb,
        .pcoded-content > .row:first-child,
        #styleSelector,
        .modal,
        .modal-backdrop {
            display: none !important;
        }
        
        /* Reset layout containers */
        .pcoded-main-container {
            margin-left: 0 !important;
            margin-top: 0 !important;
            padding: 0 !important;
        }
        
        .pcoded-content {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        body {
            font-size: 10px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .filter-card, .btn-print, .no-print, .stats-row, .page-wrapper > .row:first-child {
            display: none !important;
        }
        
        .main-body, .page-wrapper {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
            margin: 0 !important;
        }
        
        .card-body {
            padding: 0 !important;
        }
        
        .print-header {
            display: block !important;
        }
        
        .schedule-table {
            font-size: 9px;
        }
        
        .schedule-table thead th {
            background: #333 !important;
            color: white !important;
            padding: 6px 4px;
            font-size: 9px;
        }
        
        .schedule-table tbody td {
            padding: 5px 4px;
        }
        
        .date-cell {
            background: #f0f0f0 !important;
            border-left: 2px solid #333 !important;
        }
        
        .date-cell .date-day {
            font-size: 8px;
        }
        
        .date-cell .date-full {
            font-size: 9px;
        }
        
        .date-cell.date-repeat {
            border-left-color: transparent !important;
        }
        
        .today-badge {
            font-size: 7px;
            padding: 1px 3px;
        }
        
        .today-row .date-cell {
            background: #e8f5e9 !important;
            border-left-color: #4CAF50 !important;
        }
        
        .date-start {
            border-top: 1px solid #999;
        }
        
        .program-badge {
            border: 1px solid #ccc;
            padding: 2px 4px;
            font-size: 8px;
            background: white !important;
        }
        
        .course-cell .course-code {
            font-size: 8px;
        }
        
        .course-cell .course-title {
            font-size: 9px;
        }
        
        .time-range {
            font-size: 9px;
        }
        
        .room-cell, .supervisor-cell, .semester-cell {
            font-size: 9px;
        }
        
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .primary-filters {
            border-right: none;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding-right: 0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .secondary-filters {
            padding-left: 0;
        }
        
        .schedule-table {
            font-size: 11px;
        }
        
        .schedule-table thead th,
        .schedule-table tbody td {
            padding: 8px 5px;
        }
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        
        <!-- Filter Card -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h5><i class="fas fa-calendar-check"></i> {{ $title }}</h5>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route($route.'.schedule-overview') }}" id="filter-form">
                    <div class="row">
                        <!-- Primary Filters -->
                        <div class="col-lg-8 primary-filters">
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label for="session"><i class="fas fa-graduation-cap"></i> {{ __('field_session') }} <span class="text-warning">*</span></label>
                                    <select class="form-control" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $sess)
                                        <option value="{{ $sess->id }}" {{ $selected_session == $sess->id ? 'selected' : '' }}>
                                            {{ $sess->title }}
                                            @if($sess->current == '1') (Current) @endif
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="form-group col-md-2">
                                    <label for="semester_year"><i class="fas fa-layer-group"></i> {{ __('field_year') }}</label>
                                    <select class="form-control" name="semester_year" id="semester_year">
                                        <option value="0">{{ __('All') }}</option>
                                        @foreach($semester_years as $year)
                                        <option value="{{ $year }}" {{ $selected_semester_year == $year ? 'selected' : '' }}>
                                            Year {{ $year }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="form-group col-md-3">
                                    <label for="semester"><i class="fas fa-bookmark"></i> {{ __('field_semester') }}</label>
                                    <select class="form-control" name="semester" id="semester">
                                        <option value="0">{{ __('All') }}</option>
                                        @foreach($semesters as $sem)
                                        <option value="{{ $sem->id }}" {{ $selected_semester == $sem->id ? 'selected' : '' }}>
                                            {{ $sem->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="form-group col-md-4">
                                    <label for="exam_type"><i class="fas fa-file-alt"></i> {{ __('field_type') }} <span class="text-warning">*</span></label>
                                    <select class="form-control" name="exam_type" id="exam_type" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($exam_types as $type)
                                        <option value="{{ $type->id }}" {{ $selected_exam_type == $type->id ? 'selected' : '' }}>
                                            {{ $type->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Secondary Filters (Optional) -->
                        <div class="col-lg-4 secondary-filters">
                            <small class="d-block mb-2" style="opacity: 0.7;"><i class="fas fa-filter"></i> Optional</small>
                            <div class="row">
                                <div class="form-group col-6">
                                    <label for="faculty">{{ __('field_faculty') }}</label>
                                    <select class="form-control form-control-sm" name="faculty" id="faculty">
                                        <option value="0">{{ __('All') }}</option>
                                        @foreach($faculties as $fac)
                                        <option value="{{ $fac->id }}" {{ $selected_faculty == $fac->id ? 'selected' : '' }}>
                                            {{ $fac->shortcode ?? $fac->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="form-group col-6">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control form-control-sm" name="program" id="program">
                                        <option value="0">{{ __('All') }}</option>
                                        @foreach($programs as $prog)
                                        <option value="{{ $prog->id }}" {{ $selected_program == $prog->id ? 'selected' : '' }}>
                                            {{ $prog->shortcode ?? $prog->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-filter">
                                <i class="fas fa-search"></i> Load Schedule
                            </button>
                            <a href="{{ route($route.'.schedule-overview') }}" class="btn btn-secondary ml-2" style="border-radius: 8px;">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                            @if($routines->count() > 0)
                            <button type="button" class="btn btn-dark ml-2 btn-print" style="border-radius: 8px;" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        @if($routines->count() > 0)
        <!-- Statistics Row -->
        <div class="row stats-row no-print">
            <div class="col-6 col-md mb-2">
                <div class="stat-card exams">
                    <div class="stat-number">{{ $statistics['total_exams'] }}</div>
                    <div class="stat-label">Exams</div>
                </div>
            </div>
            <div class="col-6 col-md mb-2">
                <div class="stat-card programs">
                    <div class="stat-number">{{ $statistics['total_programs'] }}</div>
                    <div class="stat-label">Programs</div>
                </div>
            </div>
            <div class="col-6 col-md mb-2">
                <div class="stat-card subjects">
                    <div class="stat-number">{{ $statistics['total_subjects'] }}</div>
                    <div class="stat-label">Courses</div>
                </div>
            </div>
            <div class="col-6 col-md mb-2">
                <div class="stat-card days">
                    <div class="stat-number">{{ $statistics['total_days'] }}</div>
                    <div class="stat-label">Days</div>
                </div>
            </div>
            <div class="col-12 col-md mb-2">
                <div class="stat-card range">
                    <div class="stat-number" style="font-size: 14px;">
                        {{ $statistics['earliest_date'] ? \Carbon\Carbon::parse($statistics['earliest_date'])->format('M j') : 'N/A' }} - {{ $statistics['latest_date'] ? \Carbon\Carbon::parse($statistics['latest_date'])->format('M j, Y') : 'N/A' }}
                    </div>
                    <div class="stat-label">Period</div>
                </div>
            </div>
        </div>
        
        <!-- Schedule Table -->
        <div class="card">
            <div class="card-body p-0">
                <!-- Print Header (Only visible in print) -->
                <div class="print-header">
                    <h2>EXAMINATION SCHEDULE</h2>
                    <div class="print-subtitle">
                        @php
                            $selectedSessionObj = $sessions->firstWhere('id', $selected_session);
                            $selectedTypeObj = $exam_types->firstWhere('id', $selected_exam_type);
                        @endphp
                        {{ $selectedSessionObj->title ?? 'All Sessions' }} | 
                        {{ $selectedTypeObj->title ?? 'All Types' }}
                        @if($selected_semester_year && $selected_semester_year != '0')
                            | Year {{ $selected_semester_year }}
                        @endif
                    </div>
                    <div class="print-meta">
                        Generated: {{ now()->format('F j, Y - g:i A') }} | 
                        Total: {{ $statistics['total_exams'] }} exams over {{ $statistics['total_days'] }} days
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 130px;">Date</th>
                                <th style="width: 110px;">Time</th>
                                <th>Course</th>
                                <th style="width: 110px;">Program</th>
                                <th style="width: 90px;">Semester</th>
                                <th style="width: 100px;">Room(s)</th>
                                <th>Supervisor(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                                $counter = 0; 
                                $lastDate = null;
                            @endphp
                            @foreach($routines_by_date as $date => $dayRoutines)
                            @php
                                $dateObj = \Carbon\Carbon::parse($date);
                                $isToday = $dateObj->isToday();
                                $isPast = $dateObj->isPast() && !$isToday;
                                $dateClass = $isToday ? 'today-row' : ($isPast ? 'past-row' : '');
                                $examsOnDay = $dayRoutines->count();
                            @endphp
                            
                            @foreach($dayRoutines as $index => $routine)
                            @php
                                $counter++;
                                $facultyIndex = (($routine->program->faculty->id ?? 0) % 8) + 1;
                                $startTime = \Carbon\Carbon::parse($routine->start_time);
                                $endTime = \Carbon\Carbon::parse($routine->end_time);
                                $isFirstOfDate = ($index === 0);
                                $isNewDate = ($lastDate !== $date);
                                
                                // Get teacher IDs who teach this course (to exclude them from supervisors)
                                $courseTeacherIds = \App\Models\ClassRoutine::where('subject_id', $routine->subject_id)
                                    ->where('session_id', $routine->session_id)
                                    ->where('program_id', $routine->program_id)
                                    ->where('semester_id', $routine->semester_id)
                                    ->pluck('teacher_id')
                                    ->unique()
                                    ->toArray();
                                
                                // Filter supervisors to exclude course teachers
                                $supervisorsOnly = $routine->users->filter(function($user) use ($courseTeacherIds) {
                                    return !in_array($user->id, $courseTeacherIds);
                                });
                            @endphp
                            <tr class="{{ $dateClass }} {{ $isNewDate ? 'date-start' : '' }}">
                                <td class="row-num">{{ $counter }}</td>
                                <td class="date-cell {{ $isFirstOfDate ? '' : 'date-repeat' }}">
                                    @if($isFirstOfDate)
                                        <span class="date-day">{{ $dateObj->format('D') }}</span>
                                        <span class="date-full">{{ $dateObj->format('M j, Y') }}</span>
                                        @if($isToday)
                                            <span class="today-badge">TODAY</span>
                                        @endif
                                    @else
                                        <span class="date-continuation">↳</span>
                                    @endif
                                </td>
                                <td class="time-cell">
                                    <span class="time-range">{{ $startTime->format('H:i') }} - {{ $endTime->format('H:i') }}</span>
                                </td>
                                <td class="course-cell">
                                    <span class="course-code">{{ $routine->subject->code ?? 'N/A' }}</span>
                                    <span class="course-title">{{ $routine->subject->title ?? 'Unknown' }}</span>
                                </td>
                                <td>
                                    <span class="program-badge faculty-{{ $facultyIndex }}">
                                        {{ $routine->program->shortcode ?? $routine->program->title ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="semester-cell">{{ $routine->semester->title ?? 'N/A' }}</td>
                                <td class="room-cell">
                                    @if($routine->rooms->count() > 0)
                                        {{ $routine->rooms->pluck('title')->implode(', ') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="supervisor-cell">
                                    @if($supervisorsOnly->count() > 0)
                                        @foreach($supervisorsOnly as $user)
                                            {{ $user->first_name }} {{ $user->last_name }}@if(!$loop->last), @endif
                                        @endforeach
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @php $lastDate = $date; @endphp
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        @else
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h4>No Exam Schedule Found</h4>
            <p>
                @if(empty($selected_session) || $selected_session == '0')
                    Please select a session to view exam schedules.
                @elseif(empty($selected_exam_type) || $selected_exam_type == '0')
                    Please select an exam type to view schedules.
                @else
                    No exams are scheduled for the selected filters.<br>
                    Try adjusting the filters or check if exams have been scheduled.
                @endif
            </p>
        </div>
        @endif
        
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
"use strict";

$(document).ready(function() {
    const $session = $('#session');
    const $semesterYear = $('#semester_year');
    const $semester = $('#semester');
    const $faculty = $('#faculty');
    const $program = $('#program');
    
    // When session changes, fetch semester years
    $session.on('change', function() {
        const sessionId = $(this).val();
        
        // Reset dependent dropdowns
        $semesterYear.html('<option value="0">{{ __("All") }}</option>');
        $semester.html('<option value="0">{{ __("All") }}</option>');
        
        if (!sessionId) return;
        
        // Fetch semester years for this session
        $.get('{{ url("admin/ajax/session-semester-years") }}/' + sessionId, function(data) {
            if (data.years && data.years.length > 0) {
                data.years.forEach(function(year) {
                    $semesterYear.append('<option value="' + year + '">Year ' + year + '</option>');
                });
            }
        });
    });
    
    // When semester year changes, fetch semesters
    $semesterYear.on('change', function() {
        const sessionId = $session.val();
        const year = $(this).val();
        
        $semester.html('<option value="0">{{ __("All") }}</option>');
        
        if (!sessionId || year == '0') return;
        
        // Fetch semesters for this session and year
        $.get('{{ url("admin/ajax/session-year-semesters") }}/' + sessionId + '/' + year, function(data) {
            if (data.semesters && data.semesters.length > 0) {
                data.semesters.forEach(function(sem) {
                    $semester.append('<option value="' + sem.id + '">' + sem.title + '</option>');
                });
            }
        });
    });
    
    // When faculty changes, fetch programs
    $faculty.on('change', function() {
        const facultyId = $(this).val();
        
        $program.html('<option value="0">{{ __("All") }}</option>');
        
        if (!facultyId || facultyId == '0') return;
        
        $.get('{{ url("admin/ajax/faculty-programs") }}/' + facultyId, function(data) {
            if (data.programs && data.programs.length > 0) {
                data.programs.forEach(function(prog) {
                    $program.append('<option value="' + prog.id + '">' + (prog.shortcode || prog.title) + '</option>');
                });
            }
        });
    });
});
</script>
@endsection
