<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.3;
            color: #333;
        }
        .container {
            padding: 10px 15px;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #4e73df;
        }
        .header .logo {
            width: 50px;
            height: auto;
            margin-bottom: 5px;
        }
        .header .institution-name {
            font-size: 14pt;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .header .institution-address {
            font-size: 8pt;
            color: #666;
        }
        .header .report-title {
            font-size: 12pt;
            font-weight: bold;
            color: #4e73df;
            text-transform: uppercase;
            margin-top: 8px;
            letter-spacing: 1px;
        }
        .header .exam-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 9pt;
            font-weight: bold;
            margin-top: 5px;
        }
        .exam-type-final {
            background: #e74c3c;
            color: white;
        }
        .exam-type-ca {
            background: #27ae60;
            color: white;
        }
        
        /* Info Grid */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 12px;
            background: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
        }
        .info-row {
            display: table-row;
        }
        .info-cell {
            display: table-cell;
            padding: 4px 10px;
            width: 25%;
            border-right: 1px solid #e3e6f0;
        }
        .info-cell:last-child {
            border-right: none;
        }
        .info-label {
            font-size: 7pt;
            color: #4e73df;
            font-weight: bold;
            text-transform: uppercase;
            display: block;
        }
        .info-value {
            font-size: 9pt;
            color: #2c3e50;
            font-weight: bold;
        }
        
        /* Class Statistics Cards */
        .stats-section {
            margin-bottom: 12px;
        }
        .stats-title {
            font-size: 10pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 2px solid #4e73df;
        }
        .stats-cards {
            display: table;
            width: 100%;
        }
        .stats-row {
            display: table-row;
        }
        .stat-card {
            display: table-cell;
            text-align: center;
            padding: 8px 5px;
            border: 1px solid #e3e6f0;
            background: white;
        }
        .stat-card:first-child {
            border-radius: 5px 0 0 5px;
        }
        .stat-card:last-child {
            border-radius: 0 5px 5px 0;
        }
        .stat-icon {
            font-size: 12pt;
            margin-bottom: 3px;
        }
        .stat-number {
            font-size: 14pt;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 6pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-primary { color: #4e73df; }
        .stat-success { color: #27ae60; }
        .stat-danger { color: #e74c3c; }
        .stat-warning { color: #f39c12; }
        .stat-info { color: #17a2b8; }
        .stat-purple { color: #9b59b6; }
        
        /* Course Headers Summary */
        .course-summary {
            margin-bottom: 12px;
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            overflow: hidden;
        }
        .course-summary-title {
            background: #2c3e50;
            color: white;
            padding: 5px 10px;
            font-size: 9pt;
            font-weight: bold;
        }
        .course-summary-grid {
            display: table;
            width: 100%;
            font-size: 7pt;
        }
        .course-summary-row {
            display: table-row;
        }
        .course-summary-row:nth-child(even) {
            background: #f8f9fc;
        }
        .course-summary-cell {
            display: table-cell;
            padding: 4px 6px;
            border-bottom: 1px solid #e3e6f0;
            vertical-align: middle;
        }
        .course-code-badge {
            background: #4e73df;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 6pt;
            font-weight: bold;
        }
        
        /* Main Marks Table */
        .marks-section {
            margin-bottom: 12px;
        }
        .marks-title {
            font-size: 10pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 2px solid #4e73df;
        }
        table.marks-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
            table-layout: fixed;
        }
        table.marks-table th {
            background: #4e73df;
            color: white;
            padding: 3px 1px;
            text-align: center;
            font-weight: bold;
            font-size: 5pt;
            text-transform: uppercase;
            border: 1px solid #3a5fc8;
            overflow: hidden;
            word-wrap: break-word;
        }
        table.marks-table th.student-col {
            text-align: left;
            width: 80px;
        }
        table.marks-table th.course-col {
            padding: 3px 1px;
            width: 28px;
            background: #2c3e50;
            font-size: 5pt;
        }
        table.marks-table td {
            padding: 2px 1px;
            border: 1px solid #e3e6f0;
            text-align: center;
            vertical-align: middle;
            font-size: 6pt;
            overflow: hidden;
        }
        table.marks-table tr:nth-child(even) {
            background: #f8f9fc;
        }
        table.marks-table tr:hover {
            background: #eef2f7;
        }
        table.marks-table .student-name {
            text-align: left;
            font-weight: 500;
            font-size: 5pt;
        }
        table.marks-table .matricule {
            font-family: monospace;
            font-size: 5pt;
            color: #4e73df;
        }
        
        /* Mark Cells */
        .mark-cell {
            font-weight: bold;
            font-size: 6pt;
        }
        .mark-pass {
            background: #d4edda !important;
            color: #155724;
        }
        .mark-fail {
            background: #f8d7da !important;
            color: #721c24;
        }
        .mark-pending {
            background: #fff3cd !important;
            color: #856404;
        }
        .mark-not-registered {
            background: #e9ecef !important;
            color: #6c757d;
        }
        .mark-no-marks {
            color: #adb5bd;
        }
        
        /* Student Stats Columns */
        .stats-col {
            background: #f0f3f8 !important;
            font-weight: bold;
        }
        .stats-col-header {
            background: #6c757d !important;
            color: white;
        }
        
        /* Grade Scale */
        .grade-scale {
            margin-bottom: 12px;
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            overflow: hidden;
        }
        .grade-scale-title {
            background: #9b59b6;
            color: white;
            padding: 5px 10px;
            font-size: 9pt;
            font-weight: bold;
        }
        .grade-scale-grid {
            display: table;
            width: 100%;
            font-size: 7pt;
        }
        .grade-scale-row {
            display: table-row;
        }
        .grade-scale-cell {
            display: table-cell;
            padding: 3px 5px;
            text-align: center;
            border-right: 1px solid #e3e6f0;
        }
        .grade-scale-cell:last-child {
            border-right: none;
        }
        .grade-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 8pt;
        }
        .grade-pass { background: #27ae60; color: white; }
        .grade-fail { background: #e74c3c; color: white; }
        
        /* Legend */
        .legend {
            margin-top: 10px;
            padding: 8px;
            background: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            font-size: 7pt;
        }
        .legend-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 8pt;
        }
        .legend-row {
            display: table;
            width: 100%;
        }
        .legend-item {
            display: table-cell;
            padding: 2px 8px;
        }
        .legend-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            vertical-align: middle;
            margin-right: 3px;
        }
        .legend-pass { background: #d4edda; border: 1px solid #27ae60; }
        .legend-fail { background: #f8d7da; border: 1px solid #e74c3c; }
        .legend-pending { background: #fff3cd; border: 1px solid #f39c12; }
        .legend-nr { background: #e9ecef; border: 1px solid #6c757d; }
        
        /* Footer */
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 2px solid #e3e6f0;
            font-size: 7pt;
            color: #666;
        }
        .footer-row {
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            text-align: left;
            width: 60%;
        }
        .footer-right {
            display: table-cell;
            text-align: right;
            width: 40%;
        }
        
        /* Page Break */
        .page-break {
            page-break-after: always;
        }
        
        /* Ranking badge */
        .rank-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 6pt;
            font-weight: bold;
        }
        .rank-1 { background: #ffd700; color: #333; }
        .rank-2 { background: #c0c0c0; color: #333; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-other { background: #e9ecef; color: #666; }
        
        /* Workflow State Badges */
        .state-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .state-draft { background: #6c757d; color: white; }
        .state-submitted { background: #17a2b8; color: white; }
        .state-checked { background: #ffc107; color: #333; }
        .state-approved { background: #fd7e14; color: white; }
        .state-published { background: #28a745; color: white; }
        
        /* Published indicator in marks cell */
        .published-mark {
            position: relative;
        }
        .published-dot {
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #28a745;
            margin-left: 1px;
            vertical-align: super;
        }
        
        /* Course header in marks table */
        .course-header-cell {
            padding: 2px 1px !important;
            font-size: 5pt;
            line-height: 1.1;
        }
        .course-title-text {
            font-size: 4pt;
            color: #ccc;
            display: block;
            overflow: hidden;
            max-height: 16px;
        }
        .course-code-text {
            font-size: 5pt;
            font-weight: bold;
            display: block;
        }
        .course-contrib-text {
            font-size: 4pt;
            color: #aaa;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            @if($setting && $setting->logo)
            <img src="{{ public_path('uploads/setting/'.$setting->logo) }}" alt="Logo" class="logo">
            @endif
            <div class="institution-name">{{ $setting->title ?? 'Institution Name' }}</div>
            <div class="institution-address">
                {{ $setting->address ?? '' }}
                @if($setting && $setting->phone) | Tel: {{ $setting->phone }}@endif
            </div>
            <div class="report-title">📊 {{ $title }}</div>
            <div class="exam-type-badge {{ $is_final_exam ? 'exam-type-final' : 'exam-type-ca' }}">
                {{ $exam_type->title }} {{ $is_final_exam ? '(Final Examination)' : '(Continuous Assessment)' }}
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Faculty</span>
                    <span class="info-value">{{ $faculty->title }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Program</span>
                    <span class="info-value">{{ $program->code }} - {{ Str::limit($program->title, 25) }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Session</span>
                    <span class="info-value">{{ $session->title }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Semester</span>
                    <span class="info-value">{{ $semester->title }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Section</span>
                    <span class="info-value">{{ $section ? $section->title : 'All Sections' }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Exam Type</span>
                    <span class="info-value">{{ $exam_type->title }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Total Courses</span>
                    <span class="info-value">{{ count($subjects) }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Total Students</span>
                    <span class="info-value">{{ count($students) }}</span>
                </div>
            </div>
        </div>

        <!-- Class Statistics -->
        <div class="stats-section">
            <div class="stats-title">📈 Class Performance Overview</div>
            <div class="stats-cards">
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon stat-primary">👥</div>
                        <span class="stat-number stat-primary">{{ $class_stats['total_students'] }}</span>
                        <span class="stat-label">Total Students</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-success">🏆</div>
                        <span class="stat-number stat-success">{{ $class_stats['students_all_passed'] }}</span>
                        <span class="stat-label">All Courses Passed</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-danger">⚠️</div>
                        <span class="stat-number stat-danger">{{ $class_stats['students_some_failed'] }}</span>
                        <span class="stat-label">Some Failed</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-warning">📝</div>
                        <span class="stat-number stat-warning">{{ $class_stats['students_no_marks'] }}</span>
                        <span class="stat-label">No Marks Yet</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-info">📊</div>
                        <span class="stat-number stat-info">{{ $class_stats['class_average'] }}%</span>
                        <span class="stat-label">Class Average</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-success">⬆️</div>
                        <span class="stat-number stat-success">{{ $class_stats['highest_average'] }}%</span>
                        <span class="stat-label">Highest Avg</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-danger">⬇️</div>
                        <span class="stat-number stat-danger">{{ $class_stats['lowest_average'] }}%</span>
                        <span class="stat-label">Lowest Avg</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Summary -->
        <div class="course-summary">
            <div class="course-summary-title">📚 Course Statistics Summary</div>
            <div class="course-summary-grid">
                <div class="course-summary-row" style="background: #e9ecef; font-weight: bold;">
                    <div class="course-summary-cell" style="width: 8%;">Code</div>
                    <div class="course-summary-cell" style="width: 24%;">Course Title</div>
                    <div class="course-summary-cell" style="width: 5%;">CV</div>
                    <div class="course-summary-cell" style="width: 8%;">Students</div>
                    <div class="course-summary-cell" style="width: 8%;">With Marks</div>
                    <div class="course-summary-cell" style="width: 6%;">Pass</div>
                    <div class="course-summary-cell" style="width: 6%;">Fail</div>
                    <div class="course-summary-cell" style="width: 8%;">Average</div>
                    <div class="course-summary-cell" style="width: 8%;">Pass Rate</div>
                    <div class="course-summary-cell" style="width: 10%;">Status</div>
                </div>
                @foreach($subjects as $subject)
                @php
                    $stats = $subject_stats[$subject->id] ?? [];
                    $state = $subject_states[$subject->id] ?? null;
                    $workflowState = $state ? $state->workflow_state : 'draft';
                    $passRate = ($stats['with_marks'] ?? 0) > 0 
                        ? round(($stats['passed'] ?? 0) / ($stats['with_marks']) * 100, 2) 
                        : 0;
                @endphp
                <div class="course-summary-row">
                    <div class="course-summary-cell"><span class="course-code-badge">{{ $subject->code }}</span></div>
                    <div class="course-summary-cell" style="text-align: left;">{{ Str::limit($subject->title ?? $subject->subject_name, 30) }}</div>
                    <div class="course-summary-cell">{{ $subject->credit_hour }}</div>
                    <div class="course-summary-cell">{{ $stats['total'] ?? 0 }}</div>
                    <div class="course-summary-cell">{{ $stats['with_marks'] ?? 0 }}</div>
                    <div class="course-summary-cell" style="color: #27ae60; font-weight: bold;">{{ $stats['passed'] ?? 0 }}</div>
                    <div class="course-summary-cell" style="color: #e74c3c; font-weight: bold;">{{ $stats['failed'] ?? 0 }}</div>
                    <div class="course-summary-cell">{{ $stats['average'] ?? '-' }}/{{ $stats['contribution'] ?? 100 }}</div>
                    <div class="course-summary-cell" style="font-weight: bold; color: {{ $passRate >= 70 ? '#27ae60' : ($passRate >= 50 ? '#f39c12' : '#e74c3c') }};">{{ $passRate }}%</div>
                    <div class="course-summary-cell">
                        <span class="state-badge state-{{ $workflowState }}">{{ ucfirst($workflowState) }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Student Marks Table -->
        <div class="marks-section">
            <div class="marks-title">📋 Detailed Student Marks <small style="font-size: 7pt; color: #666;">(● = Published)</small></div>
            <table class="marks-table">
                <thead>
                    <tr>
                        <th style="width: 15px;">#</th>
                        <th class="student-col">Matricule</th>
                        <th class="student-col">Student Name</th>
                        @foreach($subjects as $subject)
                        @php
                            $subjectContrib = $subject_stats[$subject->id]['contribution'] ?? 100;
                        @endphp
                        <th class="course-col course-header-cell" title="{{ $subject->title }}">
                            <span class="course-title-text">{{ Str::limit($subject->title ?? $subject->subject_name, 12) }}</span>
                            <span class="course-code-text">{{ $subject->code }}</span>
                            <span class="course-contrib-text">/{{ $subjectContrib }}</span>
                        </th>
                        @endforeach
                        <th class="stats-col-header" style="width: 22px;">Reg</th>
                        <th class="stats-col-header" style="width: 22px;">Mrks</th>
                        <th class="stats-col-header" style="width: 22px;">Pass</th>
                        <th class="stats-col-header" style="width: 22px;">Fail</th>
                        <th class="stats-col-header" style="width: 28px;">Avg%</th>
                        <th class="stats-col-header" style="width: 22px;">Rank</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Calculate rankings based on average
                        $rankings = collect($student_stats)->sortByDesc('average')->values();
                        $rankMap = [];
                        $currentRank = 1;
                        $previousAvg = null;
                        foreach ($rankings as $idx => $stat) {
                            if ($stat['average'] > 0) {
                                if ($previousAvg !== $stat['average']) {
                                    $currentRank = $idx + 1;
                                }
                                $previousAvg = $stat['average'];
                            } else {
                                $currentRank = '-';
                            }
                            $rankMap[array_keys($student_stats)[$rankings->search($stat)]] = $currentRank;
                        }
                    @endphp
                    @foreach($students as $index => $student)
                    @php
                        $stats = $student_stats[$student['enroll_id']] ?? [];
                        $rank = $rankMap[$student['enroll_id']] ?? '-';
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="matricule">{{ $student['matricule'] }}</td>
                        <td class="student-name">{{ Str::limit($student['name'], 15) }}</td>
                        @foreach($subjects as $subject)
                            @php
                                $courseData = $student['courses'][$subject->id] ?? null;
                                $isRegistered = $courseData['registered'] ?? false;
                                $hasMarks = $courseData && $courseData['has_marks'];
                                $marks = $courseData['marks'] ?? null;
                                $examTypePassFail = $courseData['exam_type_pass_fail'] ?? null;
                                $studentWorkflowState = $courseData['workflow_state'] ?? 'draft';
                                $isPublished = $studentWorkflowState === 'published';
                                
                                $cellClass = 'mark-cell ';
                                if (!$isRegistered) {
                                    $cellClass .= 'mark-not-registered';
                                } elseif ($examTypePassFail === 'pass') {
                                    $cellClass .= 'mark-pass';
                                } elseif ($examTypePassFail === 'fail') {
                                    $cellClass .= 'mark-fail';
                                } elseif ($hasMarks) {
                                    $cellClass .= 'mark-pending';
                                } else {
                                    $cellClass .= 'mark-no-marks';
                                }
                            @endphp
                            <td class="{{ $cellClass }}">
                                @if(!$isRegistered)
                                    NR
                                @elseif($hasMarks)
                                    {{ number_format($marks, 1) }}@if($isPublished)<span class="published-dot" title="Published"></span>@endif
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        <td class="stats-col">{{ $stats['total_courses'] ?? 0 }}</td>
                        <td class="stats-col">{{ $stats['courses_with_marks'] ?? 0 }}</td>
                        <td class="stats-col" style="color: #27ae60;">{{ $stats['courses_passed'] ?? 0 }}</td>
                        <td class="stats-col" style="color: #e74c3c;">{{ $stats['courses_failed'] ?? 0 }}</td>
                        <td class="stats-col" style="font-weight: bold; color: {{ ($stats['average'] ?? 0) >= 50 ? '#27ae60' : '#e74c3c' }};">{{ $stats['average'] ?? 0 }}%</td>
                        <td class="stats-col">
                            @if($rank !== '-' && $rank <= 3)
                                <span class="rank-badge rank-{{ $rank }}">{{ $rank }}</span>
                            @elseif($rank !== '-')
                                <span class="rank-badge rank-other">{{ $rank }}</span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Grade Scale Reference -->
        @if($grades->count() > 0)
        <div class="grade-scale">
            <div class="grade-scale-title">🎓 Grade Scale Reference (Pass Mark: 50% of Contribution)</div>
            <div class="grade-scale-grid">
                <div class="grade-scale-row">
                    @foreach($grades as $grade)
                    <div class="grade-scale-cell">
                        <span class="grade-badge {{ strtoupper($grade->remark) == 'FAIL' || $grade->title == 'F' ? 'grade-fail' : 'grade-pass' }}">{{ $grade->title }}</span>
                        <br><small>{{ $grade->min_mark }}-{{ $grade->max_mark }}</small>
                        <br><small style="color: #666;">{{ $grade->point }} pts</small>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Legend -->
        <div class="legend">
            <div class="legend-title">📌 Legend</div>
            <div class="legend-row">
                <div class="legend-item">
                    <span class="legend-box legend-pass"></span> Passed (≥50% of contribution)
                </div>
                <div class="legend-item">
                    <span class="legend-box legend-fail"></span> Failed (<50% of contribution)
                </div>
                <div class="legend-item">
                    <span class="legend-box legend-pending"></span> Marks entered (status pending)
                </div>
                <div class="legend-item">
                    <span class="legend-box legend-nr"></span> NR = Not Registered
                </div>
            </div>
            <div class="legend-row" style="margin-top: 5px;">
                <div class="legend-item"><strong>Reg:</strong> Registered Courses</div>
                <div class="legend-item"><strong>Mrks:</strong> Courses with Marks</div>
                <div class="legend-item"><strong>Avg%:</strong> Weighted Average (Marks/Contribution × 100)</div>
                <div class="legend-item"><strong>Rank:</strong> Class Ranking by Average</div>
            </div>
            <div class="legend-row" style="margin-top: 5px;">
                <div class="legend-item"><span class="published-dot"></span> = Mark has been published</div>
                <div class="legend-item">
                    <span class="state-badge state-draft">Draft</span>
                    <span class="state-badge state-submitted">Submitted</span>
                    <span class="state-badge state-checked">Checked</span>
                    <span class="state-badge state-approved">Approved</span>
                    <span class="state-badge state-published">Published</span>
                    = Workflow States
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-row">
                <div class="footer-left">
                    <strong>Generated:</strong> {{ $generated_at->format('F d, Y \a\t h:i:s A') }}<br>
                    <strong>Generated by:</strong> {{ $generated_by->name ?? 'System' }} ({{ $generated_by->email ?? '' }})
                </div>
                <div class="footer-right">
                    <em>Official Document - {{ $setting->title ?? 'Institution' }}</em><br>
                    <em>{{ $exam_type->title }} | {{ $program->code }} | {{ $semester->title }}</em>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
