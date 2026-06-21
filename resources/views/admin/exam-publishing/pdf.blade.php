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
            font-size: 9pt;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 15px;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4e73df;
        }
        .header .logo {
            width: 60px;
            height: auto;
            margin-bottom: 5px;
        }
        .header .institution-name {
            font-size: 16pt;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .header .institution-address {
            font-size: 9pt;
            color: #666;
            margin-bottom: 10px;
        }
        .header .report-title {
            font-size: 14pt;
            font-weight: bold;
            color: #4e73df;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .header .exam-type-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 10pt;
            font-weight: bold;
            margin-top: 8px;
        }
        .header .exam-type-final {
            background-color: #e74c3c;
            color: white;
        }
        .header .exam-type-ca {
            background-color: #27ae60;
            color: white;
        }
        
        /* Info Section */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            background: #f8f9fc;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e3e6f0;
        }
        .info-row {
            display: table-row;
        }
        .info-cell {
            display: table-cell;
            padding: 3px 10px;
            width: 50%;
        }
        .info-label {
            font-weight: bold;
            color: #4e73df;
            display: inline-block;
            width: 100px;
        }
        .info-value {
            color: #333;
        }
        
        /* Summary Stats */
        .summary-section {
            margin-bottom: 15px;
        }
        .summary-title {
            font-size: 11pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .stats-row {
            display: table-row;
        }
        .stat-box {
            display: table-cell;
            text-align: center;
            padding: 8px;
            border: 1px solid #e3e6f0;
            background: #fff;
        }
        .stat-box:first-child {
            border-radius: 5px 0 0 5px;
        }
        .stat-box:last-child {
            border-radius: 0 5px 5px 0;
        }
        .stat-value {
            font-size: 16pt;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 8pt;
            color: #666;
            text-transform: uppercase;
        }
        .stat-primary { color: #4e73df; }
        .stat-success { color: #27ae60; }
        .stat-danger { color: #e74c3c; }
        .stat-warning { color: #f39c12; }
        .stat-info { color: #17a2b8; }
        .stat-secondary { color: #6c757d; }
        
        /* Workflow Progress */
        .workflow-progress {
            margin-bottom: 15px;
            background: #f8f9fc;
            padding: 10px;
            border-radius: 5px;
        }
        .workflow-title {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .workflow-stages {
            display: table;
            width: 100%;
        }
        .workflow-stage {
            display: table-cell;
            text-align: center;
            padding: 5px;
            position: relative;
        }
        .stage-count {
            font-size: 14pt;
            font-weight: bold;
        }
        .stage-name {
            font-size: 8pt;
            text-transform: uppercase;
        }
        .stage-draft { color: #6c757d; }
        .stage-submitted { color: #17a2b8; }
        .stage-checked { color: #f39c12; }
        .stage-approved { color: #9b59b6; }
        .stage-published { color: #27ae60; }
        
        /* Main Table */
        .table-section {
            margin-bottom: 15px;
        }
        .table-title {
            font-size: 11pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        table.data-table th {
            background: #4e73df;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7pt;
        }
        table.data-table th.text-center {
            text-align: center;
        }
        table.data-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #e3e6f0;
            vertical-align: middle;
        }
        table.data-table tr:nth-child(even) {
            background: #f8f9fc;
        }
        table.data-table tr:hover {
            background: #eef2f7;
        }
        table.data-table .text-center {
            text-align: center;
        }
        table.data-table .text-right {
            text-align: right;
        }
        
        /* Course Code Badge */
        .course-code {
            display: inline-block;
            background: #2c3e50;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }
        
        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }
        .badge-draft { background: #6c757d; color: white; }
        .badge-submitted { background: #17a2b8; color: white; }
        .badge-checked { background: #f39c12; color: white; }
        .badge-approved { background: #9b59b6; color: white; }
        .badge-published { background: #27ae60; color: white; }
        .badge-success { background: #27ae60; color: white; }
        .badge-danger { background: #e74c3c; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        
        /* Pass/Fail Indicators */
        .pass-indicator { color: #27ae60; font-weight: bold; }
        .fail-indicator { color: #e74c3c; font-weight: bold; }
        
        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
        }
        .footer-row {
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            text-align: left;
            width: 50%;
        }
        .footer-right {
            display: table-cell;
            text-align: right;
            width: 50%;
        }
        
        /* Legend */
        .legend {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fc;
            border-radius: 5px;
            font-size: 8pt;
        }
        .legend-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .legend-item {
            display: inline-block;
            margin-right: 15px;
        }
        
        /* No Marks Row */
        .no-marks-row {
            background: #fff3cd !important;
            color: #856404;
        }
        
        /* Page Break */
        .page-break {
            page-break-after: always;
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
                @if($setting && $setting->phone), Tel: {{ $setting->phone }}@endif
                @if($setting && $setting->email), Email: {{ $setting->email }}@endif
            </div>
            <div class="report-title">{{ $title }}</div>
            <div class="exam-type-badge {{ $is_final_exam ? 'exam-type-final' : 'exam-type-ca' }}">
                {{ $exam_type->title }} {{ $is_final_exam ? '(Final Examination)' : '(Continuous Assessment)' }}
            </div>
        </div>

        <!-- Info Section -->
        <div class="info-section">
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Faculty:</span>
                    <span class="info-value">{{ $faculty->title }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Program:</span>
                    <span class="info-value">{{ $program->title }} ({{ $program->code }})</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Session:</span>
                    <span class="info-value">{{ $session->title }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Semester:</span>
                    <span class="info-value">{{ $semester->title }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Section:</span>
                    <span class="info-value">{{ $section ? $section->title : 'All Sections' }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Exam Type:</span>
                    <span class="info-value">{{ $exam_type->title }} ({{ $is_final_exam ? 'Final' : 'CA' }})</span>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="summary-section">
            <div class="summary-title">📊 Summary Statistics</div>
            <div class="stats-grid">
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-value stat-info">{{ $total_courses }}</span>
                        <span class="stat-label">Total Courses</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value stat-primary">{{ $total_students }}</span>
                        <span class="stat-label">Total Students</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value stat-secondary">{{ $overall_with_marks }}</span>
                        <span class="stat-label">With Marks</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value stat-success">{{ $overall_passed }}</span>
                        <span class="stat-label">Passed</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value stat-danger">{{ $overall_failed }}</span>
                        <span class="stat-label">Failed</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value stat-success">{{ $published_count }}/{{ $total_courses }}</span>
                        <span class="stat-label">Published</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow Progress -->
        <div class="workflow-progress">
            <div class="workflow-title">📋 Workflow Progress</div>
            <div class="workflow-stages">
                <div class="workflow-stage">
                    <div class="stage-count stage-draft">{{ $draft_count }}</div>
                    <div class="stage-name stage-draft">Draft</div>
                </div>
                <div class="workflow-stage">
                    <div class="stage-count stage-submitted">{{ $submitted_count }}</div>
                    <div class="stage-name stage-submitted">Submitted</div>
                </div>
                <div class="workflow-stage">
                    <div class="stage-count stage-checked">{{ $checked_count }}</div>
                    <div class="stage-name stage-checked">Checked</div>
                </div>
                <div class="workflow-stage">
                    <div class="stage-count stage-approved">{{ $approved_count }}</div>
                    <div class="stage-name stage-approved">Approved</div>
                </div>
                <div class="workflow-stage">
                    <div class="stage-count stage-published">{{ $published_count }}</div>
                    <div class="stage-name stage-published">Published</div>
                </div>
            </div>
        </div>

        <!-- Courses Table -->
        <div class="table-section">
            <div class="table-title">📚 Courses Publishing Status Details</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 30px;" class="text-center">#</th>
                        <th style="width: 70px;">Code</th>
                        <th>Course Title</th>
                        <th style="width: 40px;" class="text-center">CV</th>
                        <th style="width: 60px;" class="text-center">Students</th>
                        <th style="width: 70px;" class="text-center">With Marks</th>
                        <th style="width: 50px;" class="text-center">Pass</th>
                        <th style="width: 50px;" class="text-center">Fail</th>
                        <th style="width: 70px;" class="text-center">Average</th>
                        @if($is_final_exam)
                        <th style="width: 70px;" class="text-center">CA Status</th>
                        @endif
                        <th style="width: 80px;" class="text-center">State</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjects as $index => $subject)
                    @php
                        $state = $subject_states[$subject->id] ?? null;
                        $stats = $subject_stats[$subject->id] ?? [];
                        $currentState = $state ? $state->workflow_state : 'draft';
                        $hasMarks = ($stats['with_marks'] ?? 0) > 0;
                        $caStatus = $is_final_exam ? ($ca_publishing_status[$subject->id] ?? false) : true;
                        
                        // Calculate pass rate
                        $passRate = ($stats['with_marks'] ?? 0) > 0 
                            ? round(($stats['passed'] ?? 0) / ($stats['with_marks']) * 100, 2) 
                            : 0;
                    @endphp
                    <tr class="{{ !$hasMarks ? 'no-marks-row' : '' }}">
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td><span class="course-code">{{ $subject->code }}</span></td>
                        <td>{{ $subject->title ?? $subject->subject_name }}</td>
                        <td class="text-center">{{ $subject->credit_hour }}</td>
                        <td class="text-center">{{ $stats['total'] ?? 0 }}</td>
                        <td class="text-center">
                            @if($hasMarks)
                            <span class="badge badge-info">{{ $stats['with_marks'] }}</span>
                            @else
                            <span class="badge badge-danger">0</span>
                            @endif
                        </td>
                        <td class="text-center pass-indicator">{{ $stats['passed'] ?? 0 }}</td>
                        <td class="text-center fail-indicator">{{ $stats['failed'] ?? 0 }}</td>
                        <td class="text-center">
                            @if(isset($stats['average']) && $stats['average'] > 0)
                                {{ $stats['average'] }}/{{ $stats['contribution'] ?? 100 }}
                                <br><small style="color: #666;">({{ $passRate }}% pass)</small>
                            @else
                                -
                            @endif
                        </td>
                        @if($is_final_exam)
                        <td class="text-center">
                            @if($caStatus)
                            <span class="badge badge-success">✓ Published</span>
                            @else
                            <span class="badge badge-danger">✗ Pending</span>
                            @endif
                        </td>
                        @endif
                        <td class="text-center">
                            <span class="badge badge-{{ $currentState }}">{{ ucfirst($currentState) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div class="legend">
            <div class="legend-title">Legend:</div>
            <span class="legend-item"><span class="badge badge-draft">Draft</span> Not yet submitted</span>
            <span class="legend-item"><span class="badge badge-submitted">Submitted</span> Submitted for review</span>
            <span class="legend-item"><span class="badge badge-checked">Checked</span> Verified by checker</span>
            <span class="legend-item"><span class="badge badge-approved">Approved</span> Approved by authority</span>
            <span class="legend-item"><span class="badge badge-published">Published</span> Results published</span>
            <br><br>
            <span class="legend-item"><strong>CV:</strong> Credit Value</span>
            <span class="legend-item"><strong>Pass Mark:</strong> 50% of contribution</span>
            @if($is_final_exam)
            <span class="legend-item"><strong>CA Status:</strong> Continuous Assessment must be published before Final Exam</span>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-row">
                <div class="footer-left">
                    <strong>Generated on:</strong> {{ $generated_at->format('F d, Y \a\t h:i A') }}<br>
                    <strong>Generated by:</strong> {{ $generated_by->name ?? 'System' }}
                </div>
                <div class="footer-right">
                    <em>This is an official document from {{ $setting->title ?? 'the Institution' }}</em><br>
                    <em>Page 1 of 1</em>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
