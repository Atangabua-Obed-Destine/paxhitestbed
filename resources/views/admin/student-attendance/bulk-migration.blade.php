@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<style>
    .migration-grid {
        overflow-x: auto;
        max-height: 70vh;
        position: relative;
    }
    
    .migration-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .migration-table thead th {
        position: sticky;
        top: 0;
        background-color: #343a40;
        color: #fff;
        z-index: 10;
        padding: 8px 4px;
        text-align: center;
        font-size: 11px;
        min-width: 70px;
    }
    
    .migration-table thead th:first-child,
    .migration-table thead th:nth-child(2) {
        position: sticky;
        left: 0;
        z-index: 20;
        min-width: 100px;
    }
    
    .migration-table thead th:nth-child(2) {
        left: 100px;
        min-width: 150px;
    }
    
    .migration-table.with-program thead th:nth-child(3) {
        position: sticky;
        left: 250px;
        z-index: 20;
        min-width: 100px;
    }
    
    .migration-table tbody td:first-child,
    .migration-table tbody td:nth-child(2) {
        position: sticky;
        background-color: #f8f9fa;
        z-index: 5;
        font-weight: 500;
    }
    
    .migration-table tbody td:first-child {
        left: 0;
        min-width: 100px;
    }
    
    .migration-table tbody td:nth-child(2) {
        left: 100px;
        min-width: 150px;
    }
    
    .migration-table.with-program tbody td:nth-child(2) {
        border-right: none;
    }
    
    .migration-table.with-program tbody td:nth-child(3) {
        position: sticky;
        left: 250px;
        background-color: #f8f9fa;
        z-index: 5;
        min-width: 100px;
        border-right: 2px solid #dee2e6;
    }
    
    .migration-table:not(.with-program) tbody td:nth-child(2) {
        border-right: 2px solid #dee2e6;
    }
    
    .attendance-cell {
        width: 70px;
        height: 40px;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
        font-weight: bold;
        font-size: 14px;
        border: 1px solid #dee2e6;
    }
    
    .attendance-cell:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .attendance-cell.present {
        background-color: #28a745 !important;
        color: white;
    }
    
    .attendance-cell.absent {
        background-color: #dc3545 !important;
        color: white;
    }
    
    .attendance-cell.leave {
        background-color: #ffc107 !important;
        color: #212529;
    }
    
    .attendance-cell.holiday {
        background-color: #6c757d !important;
        color: white;
    }
    
    .attendance-cell.empty {
        background-color: #e9ecef;
        color: #6c757d;
    }
    
    .date-header {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        white-space: nowrap;
        padding: 8px 4px !important;
        height: 80px;
    }
    
    .quick-fill-btn {
        margin: 2px;
        padding: 5px 10px;
        font-size: 12px;
    }
    
    .stats-card {
        text-align: center;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    
    .stats-card h3 {
        margin: 0;
        font-size: 24px;
        font-weight: bold;
    }
    
    .stats-card small {
        font-size: 12px;
        text-transform: uppercase;
    }
    
    .legend-item {
        display: inline-flex;
        align-items: center;
        margin-right: 15px;
        font-size: 13px;
    }
    
    .legend-box {
        width: 20px;
        height: 20px;
        margin-right: 5px;
        border-radius: 3px;
    }
    
    .column-header-actions {
        font-size: 10px;
        margin-top: 4px;
    }
    
    .column-header-actions a {
        color: #adb5bd;
        cursor: pointer;
        margin: 0 2px;
    }
    
    .column-header-actions a:hover {
        color: #fff;
    }
    
    .row-actions {
        display: flex;
        gap: 3px;
    }
    
    .row-actions .btn {
        padding: 2px 5px;
        font-size: 10px;
    }
    
    .keyboard-shortcuts {
        font-size: 12px;
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
    }
    
    .keyboard-shortcuts kbd {
        background: #333;
        color: #fff;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
    }
    
    .selected-cell {
        outline: 3px solid #007bff;
        outline-offset: -3px;
    }
    
    /* Totals Column */
    .totals-header {
        background: #495057 !important;
        min-width: 90px;
        text-align: center;
    }
    
    .totals-cell {
        background: #f8f9fa;
        text-align: center;
        font-size: 11px;
        white-space: nowrap;
    }
    
    .totals-cell span {
        display: inline-block;
        padding: 1px 4px;
        margin: 1px;
        border-radius: 3px;
        font-weight: 600;
    }
    
    .totals-cell .total-p { background: #d4edda; color: #155724; }
    .totals-cell .total-a { background: #f8d7da; color: #721c24; }
    .totals-cell .total-l { background: #fff3cd; color: #856404; }
    .totals-cell .total-h { background: #d1ecf1; color: #0c5460; }
</style>

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-alt"></i> {{ $title }}</h5>
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary btn-sm float-right">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                    </div>
                    <div class="card-block">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Migration Mode:</strong> Enter historical attendance data for multiple dates at once. 
                            Click cells to toggle attendance status. Use keyboard shortcuts for faster entry.
                        </div>
                        
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.bulk-migration') }}">
                            <div class="row gx-2">
                                <!-- Include All Programs Toggle -->
                                <div class="form-group col-md-12 mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="include_all_programs" name="include_all_programs" value="1" {{ ($include_all_programs ?? '0') === '1' ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="include_all_programs">
                                            <strong><i class="fas fa-globe"></i> Include All Programs (Joint Classes)</strong>
                                            <small class="text-muted d-block">Enable to show students from ALL programs enrolled in the selected course</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Standard Filters (hidden when Include All is ON) -->
                                <div id="standard-filters" class="{{ ($include_all_programs ?? '0') === '1' ? 'd-none' : '' }}" style="display: contents;">
                                    @include('common.inc.common_search_filter', [
                                      'faculties' => $faculties ?? collect(),
                                      'programs' => $programs ?? collect(),
                                      'sessions' => $sessions ?? collect(),
                                      'semesters' => $semesters ?? collect(),
                                      'sections' => $sections ?? collect(),
                                      'semesterOptions' => $semesterOptions ?? [],
                                      'selected_faculty' => $selected_faculty ?? '0',
                                      'selected_program' => $selected_program ?? '0',
                                      'selected_session' => $selected_session ?? '0',
                                      'selected_semester' => $selected_semester ?? '0',
                                      'selected_semester_year' => $selected_semester_year ?? '0',
                                      'selected_section' => $selected_section ?? '0',
                                      'include_semester_all' => true,
                                      'include_section_all' => true,
                                    ])
                                </div>
                                
                                <!-- Session filter for Include All mode -->
                                <div id="all-programs-session" class="form-group col-md-3 {{ ($include_all_programs ?? '0') !== '1' ? 'd-none' : '' }}">
                                    <label for="session_all">{{ __('field_session') }} <span>*</span></label>
                                    <select class="form-control" name="session_all" id="session_all" {{ ($include_all_programs ?? '0') !== '1' ? 'disabled' : '' }}>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($all_sessions ?? collect()) as $sess)
                                        <option value="{{ $sess->id }}" @if(($selected_session ?? '0') == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="subject">{{ __('field_subject') }} <span>*</span></label>
                                    <select class="form-control subject subject-filter" name="subject" id="subject" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($subjects ?? collect())->sortBy('code') as $subj)
                                        <option value="{{ $subj->id }}" @if(($selected_subject ?? '0') == $subj->id) selected @endif>{{ $subj->code }} - {{ $subj->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_subject') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="start_date">Start Date <span>*</span></label>
                                    <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $start_date }}" required>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} Start Date
                                    </div>
                                </div>
                                
                                <div class="form-group col-md-2">
                                    <label for="end_date">End Date <span>*</span></label>
                                    <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $end_date }}" required>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} End Date
                                    </div>
                                </div>

                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-table"></i> Load Grid</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($rows) && count($rows) > 0 && count($dates) > 0)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <i class="fas fa-users"></i> {{ count($rows) }} Students × {{ count($dates) }} Days
                                </h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <div class="legend-item">
                                    <div class="legend-box" style="background: #28a745;"></div> Present (P)
                                </div>
                                <div class="legend-item">
                                    <div class="legend-box" style="background: #dc3545;"></div> Absent (A)
                                </div>
                                <div class="legend-item">
                                    <div class="legend-box" style="background: #ffc107;"></div> Leave (L)
                                </div>
                                <div class="legend-item">
                                    <div class="legend-box" style="background: #6c757d;"></div> Holiday (H)
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-block">
                        <!-- Quick Actions -->
                        <div class="mb-3">
                            <strong>Quick Fill Entire Grid:</strong>
                            <button type="button" class="btn btn-success quick-fill-btn" onclick="fillAll(1)">
                                <i class="fas fa-check"></i> All Present
                            </button>
                            <button type="button" class="btn btn-danger quick-fill-btn" onclick="fillAll(2)">
                                <i class="fas fa-times"></i> All Absent
                            </button>
                            <button type="button" class="btn btn-warning quick-fill-btn" onclick="fillAll(3)">
                                <i class="fas fa-clock"></i> All Leave
                            </button>
                            <button type="button" class="btn btn-secondary quick-fill-btn" onclick="fillAll(4)">
                                <i class="fas fa-calendar-times"></i> All Holiday
                            </button>
                            <button type="button" class="btn btn-outline-dark quick-fill-btn" onclick="clearAll()">
                                <i class="fas fa-eraser"></i> Clear All
                            </button>
                            
                            <span class="float-right keyboard-shortcuts">
                                <strong>Keyboard:</strong> 
                                <kbd>P</kbd> Present 
                                <kbd>A</kbd> Absent 
                                <kbd>L</kbd> Leave 
                                <kbd>H</kbd> Holiday 
                                <kbd>X</kbd> Clear
                                <kbd>Arrow Keys</kbd> Navigate
                            </span>
                        </div>
                        
                        <!-- Stats Row -->
                        <div class="row mb-3" id="stats-row">
                            <div class="col-md-3">
                                <div class="stats-card bg-success text-white">
                                    <h3 id="stat-present">0</h3>
                                    <small>Present</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card bg-danger text-white">
                                    <h3 id="stat-absent">0</h3>
                                    <small>Absent</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card bg-warning">
                                    <h3 id="stat-leave">0</h3>
                                    <small>Leave</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card bg-secondary text-white">
                                    <h3 id="stat-holiday">0</h3>
                                    <small>Holiday</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Migration Grid -->
                        <form id="migration-form" action="{{ route($route.'.bulk-migration.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="subject" value="{{ $selected_subject }}">
                            
                            <div class="migration-grid">
                                <table class="table migration-table table-bordered {{ ($include_all_programs ?? '0') === '1' ? 'with-program' : '' }}" id="attendance-grid">
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Student Name</th>
                                            @if(($include_all_programs ?? '0') === '1')
                                            <th>Program</th>
                                            @endif
                                            @foreach($dates as $date)
                                            <th>
                                                <div class="date-header">{{ \Carbon\Carbon::parse($date)->format('D, M j') }}</div>
                                                <div class="column-header-actions">
                                                    <a onclick="fillColumn('{{ $date }}', 1)" title="All Present"><i class="fas fa-check"></i></a>
                                                    <a onclick="fillColumn('{{ $date }}', 2)" title="All Absent"><i class="fas fa-times"></i></a>
                                                </div>
                                            </th>
                                            @endforeach
                                            <th class="totals-header">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rows as $index => $row)
                                        <tr data-enroll-id="{{ $row->id }}">
                                            <td>
                                                {{ $row->matricule ?? $row->student->student_id }}
                                                <div class="row-actions">
                                                    <button type="button" class="btn btn-success btn-sm" onclick="fillRow({{ $row->id }}, 1)" title="All Present">P</button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="fillRow({{ $row->id }}, 2)" title="All Absent">A</button>
                                                </div>
                                            </td>
                                            <td>{{ $row->student->first_name }} {{ $row->student->last_name }}</td>
                                            @if(($include_all_programs ?? '0') === '1')
                                            <td><small class="badge badge-secondary">{{ $row->program->shortcode ?? $row->program->title ?? 'N/A' }}</small></td>
                                            @endif
                                            @php $rowTotals = ['P' => 0, 'A' => 0, 'L' => 0, 'H' => 0]; @endphp
                                            @foreach($dates as $date)
                                            @php
                                                $key = $row->id . '_' . $date;
                                                $existing = $existingAttendances[$key][0] ?? null;
                                                $attendanceValue = $existing ? $existing->attendance : '';
                                                $cellClass = '';
                                                $cellText = '-';
                                                if($attendanceValue == 1) { $cellClass = 'present'; $cellText = 'P'; $rowTotals['P']++; }
                                                elseif($attendanceValue == 2) { $cellClass = 'absent'; $cellText = 'A'; $rowTotals['A']++; }
                                                elseif($attendanceValue == 3) { $cellClass = 'leave'; $cellText = 'L'; $rowTotals['L']++; }
                                                elseif($attendanceValue == 4) { $cellClass = 'holiday'; $cellText = 'H'; $rowTotals['H']++; }
                                                else { $cellClass = 'empty'; }
                                            @endphp
                                            <td class="attendance-cell {{ $cellClass }}" 
                                                data-enroll="{{ $row->id }}" 
                                                data-date="{{ $date }}"
                                                data-value="{{ $attendanceValue }}"
                                                data-row="{{ $index }}"
                                                data-col="{{ $loop->index }}"
                                                tabindex="0">
                                                <span class="cell-text">{{ $cellText }}</span>
                                                <input type="hidden" name="attendances[{{ $row->id }}][{{ $date }}]" value="{{ $attendanceValue }}">
                                            </td>
                                            @endforeach
                                            <td class="totals-cell" data-enroll="{{ $row->id }}">
                                                <span class="total-p">P:{{ $rowTotals['P'] }}</span>
                                                <span class="total-a">A:{{ $rowTotals['A'] }}</span>
                                                @if($rowTotals['L'] > 0)<span class="total-l">L:{{ $rowTotals['L'] }}</span>@endif
                                                @if($rowTotals['H'] > 0)<span class="total-h">H:{{ $rowTotals['H'] }}</span>@endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <button type="submit" class="btn btn-primary btn-lg" id="save-btn">
                                    <i class="fas fa-save"></i> Save All Attendance Records
                                </button>
                                <p class="text-muted mt-2">
                                    <small>This will create/update <span id="total-records">0</span> attendance records</small>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @elseif(isset($selected_subject) && $selected_subject !== '0' && !empty($start_date) && !empty($end_date))
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <h5>No Students Found</h5>
                            <p>No students are enrolled in this subject for the selected criteria.</p>
                        </div>
                    </div>
                </div>
            </div>
            @else
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-hand-point-up fa-2x mb-2"></i>
                            <h5>Select Filters to Begin</h5>
                            <p>Choose Faculty, Program, Session, Subject, and Date Range, then click "Load Grid" to display the attendance matrix.</p>
                        </div>
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
<script type="text/javascript">
"use strict";

let selectedCell = null;
const totalStudents = {{ isset($rows) ? count($rows) : 0 }};
const totalDates = {{ isset($dates) ? count($dates) : 0 }};

// Attendance cycle: empty -> P -> A -> L -> H -> empty
const attendanceStates = {
    '': { next: '1', class: 'present', text: 'P' },
    '1': { next: '2', class: 'absent', text: 'A' },
    '2': { next: '3', class: 'leave', text: 'L' },
    '3': { next: '4', class: 'holiday', text: 'H' },
    '4': { next: '', class: 'empty', text: '-' }
};

const valueToClass = {
    '': 'empty',
    '1': 'present',
    '2': 'absent',
    '3': 'leave',
    '4': 'holiday'
};

const valueToText = {
    '': '-',
    '1': 'P',
    '2': 'A',
    '3': 'L',
    '4': 'H'
};

// Cell click handler
$(document).on('click', '.attendance-cell', function() {
    selectCell($(this));
    cycleAttendance($(this));
});

// Keyboard navigation
$(document).on('keydown', '.attendance-cell', function(e) {
    const cell = $(this);
    const row = parseInt(cell.data('row'));
    const col = parseInt(cell.data('col'));
    
    switch(e.key.toUpperCase()) {
        case 'P':
            setAttendance(cell, '1');
            e.preventDefault();
            break;
        case 'A':
            setAttendance(cell, '2');
            e.preventDefault();
            break;
        case 'L':
            setAttendance(cell, '3');
            e.preventDefault();
            break;
        case 'H':
            setAttendance(cell, '4');
            e.preventDefault();
            break;
        case 'X':
        case 'DELETE':
        case 'BACKSPACE':
            setAttendance(cell, '');
            e.preventDefault();
            break;
        case 'ARROWUP':
            navigateTo(row - 1, col);
            e.preventDefault();
            break;
        case 'ARROWDOWN':
            navigateTo(row + 1, col);
            e.preventDefault();
            break;
        case 'ARROWLEFT':
            navigateTo(row, col - 1);
            e.preventDefault();
            break;
        case 'ARROWRIGHT':
        case 'TAB':
            navigateTo(row, col + 1);
            e.preventDefault();
            break;
        case 'ENTER':
        case ' ':
            cycleAttendance(cell);
            e.preventDefault();
            break;
    }
});

function selectCell(cell) {
    $('.attendance-cell').removeClass('selected-cell');
    cell.addClass('selected-cell');
    cell.focus();
    selectedCell = cell;
}

function navigateTo(row, col) {
    const targetCell = $(`.attendance-cell[data-row="${row}"][data-col="${col}"]`);
    if (targetCell.length) {
        selectCell(targetCell);
    }
}

function cycleAttendance(cell) {
    const currentValue = cell.data('value').toString();
    const nextState = attendanceStates[currentValue];
    setAttendance(cell, nextState.next);
}

function setAttendance(cell, value) {
    value = value.toString();
    
    // Update cell
    cell.data('value', value);
    cell.removeClass('present absent leave holiday empty');
    cell.addClass(valueToClass[value]);
    
    // Update the text span (not the whole cell content)
    cell.find('.cell-text').text(valueToText[value]);
    
    // Update hidden input
    cell.find('input').val(value);
    
    updateStats();
    updateRowTotals(cell.data('enroll'));
}

function fillAll(value) {
    if (!confirm('This will set ALL cells to ' + valueToText[value] + '. Continue?')) return;
    
    $('.attendance-cell').each(function() {
        setAttendance($(this), value);
    });
}

function clearAll() {
    if (!confirm('This will clear ALL attendance data. Continue?')) return;
    
    $('.attendance-cell').each(function() {
        setAttendance($(this), '');
    });
}

function fillColumn(date, value) {
    $(`.attendance-cell[data-date="${date}"]`).each(function() {
        setAttendance($(this), value);
    });
}

function fillRow(enrollId, value) {
    $(`.attendance-cell[data-enroll="${enrollId}"]`).each(function() {
        setAttendance($(this), value);
    });
}

function updateStats() {
    let present = 0, absent = 0, leave = 0, holiday = 0, filled = 0;
    
    $('.attendance-cell').each(function() {
        const val = $(this).data('value').toString();
        if (val === '1') { present++; filled++; }
        else if (val === '2') { absent++; filled++; }
        else if (val === '3') { leave++; filled++; }
        else if (val === '4') { holiday++; filled++; }
    });
    
    $('#stat-present').text(present);
    $('#stat-absent').text(absent);
    $('#stat-leave').text(leave);
    $('#stat-holiday').text(holiday);
    $('#total-records').text(filled);
}

function updateRowTotals(enrollId) {
    let p = 0, a = 0, l = 0, h = 0;
    
    $(`.attendance-cell[data-enroll="${enrollId}"]`).each(function() {
        const val = $(this).data('value').toString();
        if (val === '1') p++;
        else if (val === '2') a++;
        else if (val === '3') l++;
        else if (val === '4') h++;
    });
    
    const totalsCell = $(`.totals-cell[data-enroll="${enrollId}"]`);
    let html = `<span class="total-p">P:${p}</span><span class="total-a">A:${a}</span>`;
    if (l > 0) html += `<span class="total-l">L:${l}</span>`;
    if (h > 0) html += `<span class="total-h">H:${h}</span>`;
    totalsCell.html(html);
}

// Form submission validation
$('#migration-form').on('submit', function(e) {
    const filledCells = $('.attendance-cell').filter(function() {
        return $(this).data('value').toString() !== '';
    }).length;
    
    if (filledCells === 0) {
        e.preventDefault();
        alert('Please mark at least one attendance cell before saving.');
        return false;
    }
    
    if (!confirm(`You are about to save ${filledCells} attendance records. Continue?`)) {
        e.preventDefault();
        return false;
    }
    
    // Show loading state
    $('#save-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
});

// Initialize stats on page load
$(document).ready(function() {
    updateStats();
    
    // Focus first cell if grid exists
    const firstCell = $('.attendance-cell').first();
    if (firstCell.length) {
        selectCell(firstCell);
    }
    
    // Include All Programs toggle handler
    const $includeAllToggle = $('#include_all_programs');
    const $standardFilters = $('#standard-filters');
    const $allProgramsSession = $('#all-programs-session');
    const $sessionAllSelect = $('#session_all');
    
    const updateFilterVisibility = () => {
        if ($includeAllToggle.is(':checked')) {
            $standardFilters.addClass('d-none');
            // Disable standard filter fields so they don't submit
            $standardFilters.find('select').prop('disabled', true);
            $allProgramsSession.removeClass('d-none');
            // Enable the session_all dropdown
            $sessionAllSelect.prop('disabled', false);
        } else {
            $standardFilters.removeClass('d-none');
            // Re-enable standard filter fields
            $standardFilters.find('select').prop('disabled', false);
            $allProgramsSession.addClass('d-none');
            // Disable the session_all dropdown so it doesn't conflict
            $sessionAllSelect.prop('disabled', true);
        }
    };
    
    // Initialize on page load
    updateFilterVisibility();
    
    $includeAllToggle.on('change', function() {
        updateFilterVisibility();
        resetSubjectOptions();
    });
    
    // Subject filter AJAX - fetch subjects when session changes
    const $subject = $('#subject');
    const $filterForm = $('form.needs-validation').first();
    
    const resetSubjectOptions = () => {
        $subject.html('<option value="">{{ __("select") }}</option>');
    };
    
    const fetchSubjects = () => {
        const isAllPrograms = $includeAllToggle.is(':checked');
        let sessionId;
        
        if (isAllPrograms) {
            sessionId = $('#session_all').val();
            if (!sessionId) {
                resetSubjectOptions();
                return;
            }
        } else {
            const programId = $filterForm.find('.common-program').val();
            sessionId = $filterForm.find('.common-session').val();
            if (!programId || !sessionId) {
                resetSubjectOptions();
                return;
            }
        }

        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });

        const postData = {
            session: sessionId
        };
        
        if (!isAllPrograms) {
            postData.program = $filterForm.find('.common-program').val();
            postData.semester = $filterForm.find('.common-semester').val();
            postData.section = $filterForm.find('.common-section').val();
        }

        $.post("{{ route('filter-techer-subject') }}", postData, function(response){
            resetSubjectOptions();
            if(Array.isArray(response)){
                response.forEach((item) => {
                    if(!item) return;
                    $subject.append($('<option/>', {
                        value: item.id,
                        text: (item.code ? item.code + ' - ' : '') + item.title
                    }));
                });
            }
        });
    };

    // Trigger fetch when session/semester/section changes (standard mode)
    $filterForm.on('change', '.common-faculty, .common-program', function(){
        if (!$includeAllToggle.is(':checked')) {
            resetSubjectOptions();
        }
    });

    $filterForm.on('change', '.common-session, .common-semester, .common-section', function(){
        if (!$includeAllToggle.is(':checked')) {
            fetchSubjects();
        }
    });
    
    // Trigger fetch when session changes (Include All mode)
    $('#session_all').on('change', function() {
        if ($includeAllToggle.is(':checked')) {
            fetchSubjects();
        }
    });

    // Initial fetch if program and session are already selected
    const isAllProgramsChecked = $includeAllToggle.is(':checked');
    if (isAllProgramsChecked) {
        const initialSessionAll = $('#session_all').val();
        if (initialSessionAll && !$subject.children('option').not('[value=""]').length) {
            fetchSubjects();
        }
    } else {
        const initialProgram = $filterForm.find('.common-program').val();
        const initialSession = $filterForm.find('.common-session').val();
        if (initialProgram && initialSession && !$subject.children('option').not('[value=""]').length) {
            fetchSubjects();
        }
    }
});
</script>
@endsection
