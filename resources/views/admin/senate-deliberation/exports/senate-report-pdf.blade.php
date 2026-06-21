<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Senate Report — {{ $deliberation->meeting_number ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }

        .header { text-align: center; border-bottom: 3px double #333; padding-bottom: 12px; margin-bottom: 15px; }
        .header h1 { font-size: 18px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 2px; }
        .header h2 { font-size: 14px; color: #555; margin-bottom: 2px; }
        .header .meeting-ref { font-size: 12px; color: #777; }

        .section { margin-bottom: 18px; }
        .section-title {
            font-size: 13px; font-weight: bold; text-transform: uppercase;
            border-bottom: 1px solid #999; padding-bottom: 3px; margin-bottom: 8px;
            color: #222;
        }

        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-grid td { padding: 4px 8px; vertical-align: top; }
        .info-grid .label { font-weight: bold; width: 120px; color: #555; }

        table.data-table {
            width: 100%; border-collapse: collapse; margin-bottom: 10px;
            font-size: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #aaa; padding: 4px 6px; text-align: left;
        }
        table.data-table th {
            background-color: #2c3e50; color: #fff; font-weight: bold;
            text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px;
        }
        table.data-table tbody tr:nth-child(even) { background-color: #f8f9fa; }

        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 3px;
            font-size: 9px; font-weight: bold; color: #fff; text-transform: uppercase;
        }
        .badge-success { background-color: #28a745; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-danger  { background-color: #dc3545; }
        .badge-info    { background-color: #17a2b8; }
        .badge-primary { background-color: #007bff; }
        .badge-secondary { background-color: #6c757d; }

        .standing-summary { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .standing-summary td { text-align: center; padding: 8px; font-size: 11px; }
        .standing-summary .count { font-size: 18px; font-weight: bold; display: block; }

        .signature-block { margin-top: 30px; page-break-inside: avoid; }
        .signature-line { display: inline-block; width: 45%; margin: 20px 2%; vertical-align: top; }
        .signature-line .line { border-top: 1px solid #333; padding-top: 3px; margin-top: 30px; }

        .page-break { page-break-before: always; }

        .footer { text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ccc; padding-top: 5px; margin-top: 20px; }
    </style>
</head>
<body>

    {{-- ═══ HEADER ═══ --}}
    <div class="header">
        <h1>Senate Deliberation Report</h1>
        <h2>Academic Results Review</h2>
        <div class="meeting-ref">{{ $deliberation->meeting_number }}</div>
    </div>

    {{-- ═══ MEETING DETAILS ═══ --}}
    <div class="section">
        <div class="section-title">1. Meeting Details</div>
        <table class="info-grid">
            <tr>
                <td class="label">Session:</td>
                <td>{{ $deliberation->session->title ?? '-' }}</td>
                <td class="label">Semester:</td>
                <td>{{ $deliberation->semester->title ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Date:</td>
                <td>{{ $deliberation->meeting_date ? $deliberation->meeting_date->format('d F Y') : 'Not set' }}</td>
                <td class="label">Venue:</td>
                <td>{{ $deliberation->venue ?? 'Not set' }}</td>
            </tr>
            <tr>
                <td class="label">Chairperson:</td>
                <td>{{ $deliberation->chairperson ?? 'Not set' }}</td>
                <td class="label">Registrar:</td>
                <td>{{ $deliberation->registrar ?? 'Not set' }}</td>
            </tr>
            <tr>
                <td class="label">Status:</td>
                <td>
                    @php
                        $sBadge = match($deliberation->status) {
                            'completed' => 'badge-success',
                            'in_progress' => 'badge-info',
                            'deferred' => 'badge-warning',
                            default => 'badge-secondary',
                        };
                    @endphp
                    <span class="badge {{ $sBadge }}">{{ $statusLabels[$deliberation->status] ?? ucfirst($deliberation->status) }}</span>
                </td>
                <td class="label">Decision:</td>
                <td>
                    @php
                        $dBadge = match($deliberation->overall_decision) {
                            'approved' => 'badge-success',
                            'approved_with_conditions' => 'badge-warning',
                            'rejected' => 'badge-danger',
                            'deferred' => 'badge-info',
                            default => 'badge-secondary',
                        };
                    @endphp
                    <span class="badge {{ $dBadge }}">{{ $decisionLabels[$deliberation->overall_decision] ?? 'Pending' }}</span>
                </td>
            </tr>
        </table>
        @if($deliberation->remarks)
        <p><strong>Remarks:</strong> {{ $deliberation->remarks }}</p>
        @endif
        @if($deliberation->conditions)
        <p><strong>Conditions:</strong> {{ $deliberation->conditions }}</p>
        @endif
    </div>

    {{-- ═══ OVERALL STATISTICS ═══ --}}
    <div class="section">
        <div class="section-title">2. Overall Statistics</div>
        <table class="info-grid">
            <tr>
                <td class="label">Total Students:</td>
                <td>{{ number_format($overallStats['total_students']) }}</td>
                <td class="label">Average GPA:</td>
                <td>{{ number_format($overallStats['avg_gpa'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Highest GPA:</td>
                <td>{{ number_format($overallStats['highest_gpa'], 2) }}</td>
                <td class="label">Lowest GPA:</td>
                <td>{{ number_format($overallStats['lowest_gpa'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- ═══ ACADEMIC STANDING DISTRIBUTION ═══ --}}
    <div class="section">
        <div class="section-title">3. Academic Standing Distribution</div>
        <table class="standing-summary">
            <tr>
                <td style="background:#e3f2fd;"><span class="count" style="color:#1565c0;">{{ $standingCounts['deans_list'] ?? 0 }}</span>Dean's List<br><small>(GPA ≥ 3.50)</small></td>
                <td style="background:#e8f5e9;"><span class="count" style="color:#2e7d32;">{{ $standingCounts['good_standing'] ?? 0 }}</span>Good Standing<br><small>(GPA ≥ 2.00)</small></td>
                <td style="background:#fff8e1;"><span class="count" style="color:#f57f17;">{{ $standingCounts['academic_warning'] ?? 0 }}</span>Warning<br><small>(GPA 1.50–1.99)</small></td>
                <td style="background:#fbe9e7;"><span class="count" style="color:#e65100;">{{ $standingCounts['academic_probation'] ?? 0 }}</span>Probation<br><small>(GPA 1.00–1.49)</small></td>
                <td style="background:#ffebee;"><span class="count" style="color:#c62828;">{{ $standingCounts['recommended_dismissal'] ?? 0 }}</span>Dismissal<br><small>(GPA < 1.00)</small></td>
            </tr>
        </table>
    </div>

    {{-- ═══ PROGRAM-LEVEL DECISIONS ═══ --}}
    <div class="section">
        <div class="section-title">4. Program-Level Decisions</div>
        @foreach($programsByFaculty as $facTitle => $facPrograms)
        <p style="font-weight:bold; margin-top:8px; color:#2c3e50;">{{ $facTitle }}</p>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th style="text-align:center">Students</th>
                    <th style="text-align:center">Passed</th>
                    <th style="text-align:center">Failed</th>
                    <th style="text-align:center">Avg GPA</th>
                    <th style="text-align:center">Pass %</th>
                    <th>Decision</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($facPrograms as $pd)
                <tr>
                    <td>{{ $pd->program->title ?? 'Unknown' }}</td>
                    <td style="text-align:center">{{ $pd->total_students }}</td>
                    <td style="text-align:center">{{ $pd->total_passed }}</td>
                    <td style="text-align:center">{{ $pd->total_failed }}</td>
                    <td style="text-align:center">{{ number_format($pd->average_gpa, 2) }}</td>
                    <td style="text-align:center">{{ $pd->pass_rate }}%</td>
                    <td>
                        @php
                            $pdBadge = match($pd->decision) {
                                'approved' => 'badge-success',
                                'approved_with_conditions' => 'badge-warning',
                                'rejected' => 'badge-danger',
                                'deferred' => 'badge-info',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $pdBadge }}">{{ $decisionLabels[$pd->decision] ?? 'Pending' }}</span>
                    </td>
                    <td style="font-size:9px;">{{ $pd->remarks ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach
    </div>

    {{-- ═══ FLAGGED STUDENTS ═══ --}}
    @if($flaggedStudents->count() > 0)
    <div class="page-break"></div>
    <div class="section">
        <div class="section-title">5. Students Requiring Senate Attention</div>
        <p style="font-size:10px; color:#666; margin-bottom:5px;">
            Students classified under Academic Warning, Academic Probation, or Recommended Dismissal.
        </p>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:30px">S/N</th>
                    <th>Matricule</th>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th style="text-align:center">GPA</th>
                    <th>Standing</th>
                </tr>
            </thead>
            <tbody>
                @foreach($flaggedStudents as $idx => $fs)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td><strong>{{ $fs->enrollment->matricule ?? $fs->student->student_id ?? 'N/A' }}</strong></td>
                    <td>{{ trim(($fs->student->first_name ?? '') . ' ' . ($fs->student->last_name ?? '')) }}</td>
                    <td style="font-size:9px;">{{ $fs->program->title ?? '' }}</td>
                    <td style="text-align:center; font-weight:bold; color:{{ $fs->gpa < 1.0 ? '#c62828' : '#e65100' }}">
                        {{ number_format($fs->gpa, 2) }}
                    </td>
                    <td>
                        @php
                            $fsBadge = match($fs->standing) {
                                'academic_warning' => 'badge-warning',
                                'academic_probation' => 'badge-danger',
                                'recommended_dismissal' => 'badge-danger',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $fsBadge }}">{{ $standingLabels[$fs->standing] ?? ucfirst($fs->standing) }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ═══ SIGNATURES ═══ --}}
    <div class="signature-block">
        <div class="section-title">{{ $flaggedStudents->count() > 0 ? '6' : '5' }}. Signatories</div>
        @if($deliberation->signatures->count() > 0)
            @foreach($deliberation->signatures as $sig)
            <div class="signature-line">
                <div class="line">
                    <strong>{{ $sig->signatory_name }}</strong><br>
                    <span style="color:#555;">{{ $sig->signatory_position }}</span><br>
                    <small style="color:#999;">{{ $sig->signed_at ? $sig->signed_at->format('d F Y, H:i') : '' }}</small>
                </div>
            </div>
            @endforeach
        @else
            <p style="color:#999;">No signatures recorded.</p>
        @endif
    </div>

    {{-- ═══ FOOTER ═══ --}}
    <div class="footer">
        Generated on {{ now()->format('d F Y \a\t H:i') }} | Senate Deliberation System
    </div>

</body>
</html>
