<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Form A3 - {{ count($forms) }} Students</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .page {
            padding: 20px;
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .logo {
            width: 70px;
            height: auto;
        }
        .institution-info {
            text-align: center;
            margin-top: 5px;
        }
        .institution-info h2 {
            margin: 5px 0;
            font-size: 14pt;
        }
        .institution-info p {
            margin: 2px 0;
            font-size: 10pt;
        }
        .title {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            margin: 15px 0;
            font-size: 13pt;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        .sub-title {
            text-align: center;
            font-size: 11pt;
            margin-bottom: 15px;
        }
        .info-section {
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        .info-col {
            flex: 1;
        }
        .field-label {
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
        }
        .field-value {
            display: inline-block;
            border-bottom: 1px dotted #000;
            padding: 0 10px;
        }
        table.courses {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10pt;
        }
        table.courses th,
        table.courses td {
            border: 1px solid #000;
            padding: 5px 8px;
        }
        table.courses th {
            background-color: #e0e0e0;
            text-align: center;
            font-weight: bold;
        }
        table.courses td.center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .signature-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            text-align: center;
            width: 45%;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 200px;
            display: inline-block;
            margin-bottom: 5px;
        }
        .signature-label {
            font-weight: bold;
            font-size: 10pt;
        }
        .notes {
            margin-top: 20px;
            font-size: 9pt;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        .notes h4 {
            margin: 0 0 5px 0;
            font-size: 10pt;
        }
        .notes ul {
            margin: 0;
            padding-left: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }
        .status-core {
            background-color: #4CAF50;
            color: white;
        }
        .status-elective {
            background-color: #2196F3;
            color: white;
        }
        .status-resit {
            background-color: #FF9800;
            color: white;
        }
        
        /* Admin toolbar */
        .admin-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 12px 20px;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .admin-toolbar .toolbar-title {
            color: white;
            font-size: 14pt;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }
        .admin-toolbar .toolbar-actions {
            display: flex;
            gap: 10px;
        }
        .admin-toolbar .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-size: 11pt;
            font-family: Arial, sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .admin-toolbar .btn-print {
            background-color: #4CAF50;
            color: white;
        }
        .admin-toolbar .btn-print:hover {
            background-color: #45a049;
        }
        .admin-toolbar .btn-close {
            background-color: #f44336;
            color: white;
        }
        .admin-toolbar .btn-close:hover {
            background-color: #d32f2f;
        }
        
        /* Print specific styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .admin-toolbar {
                display: none !important;
            }
            .page {
                margin-top: 0 !important;
            }
            .page:first-child {
                margin-top: 0 !important;
            }
        }
        
        @media screen {
            .page:first-child {
                margin-top: 60px;
            }
        }
    </style>
</head>
<body @if(!isset($is_preview) || !$is_preview) onload="window.print()" @endif>

    @if(isset($is_preview) && $is_preview)
    <div class="admin-toolbar">
        <span class="toolbar-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 5px;">
                <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                <path d="M4.5 12.5A.5.5 0 0 1 5 12h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zm0-2A.5.5 0 0 1 5 10h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zm1.639-3.708 1.33.886 1.854-1.855a.25.25 0 0 1 .289-.047l1.888.974V7.5a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V7s1.54-1.274 1.639-1.208zM6.25 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z"/>
            </svg>
            Bulk Form A3 Preview - {{ count($forms) }} Students
        </span>
        <div class="toolbar-actions">
            <button class="btn btn-print" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                </svg>
                Print All
            </button>
            <a href="{{ route('admin.student-form-a3.index') }}" class="btn btn-close">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
                </svg>
                Close
            </a>
        </div>
    </div>
    @endif

    @foreach($forms as $index => $form)
    @php
        $record = $form['record'];
        $student = $form['student'];
        $enrollment = $form['enrollment'];
        $subjects = $form['subjects'];
    @endphp
    
    <div class="page">
        <div class="header">
            @if(isset($generalSetting->logo_path))
            <img src="{{ asset('uploads/setting/'.$generalSetting->logo_path) }}" class="logo" alt="Logo">
            @endif
            <div class="institution-info">
                <p style="font-weight: bold;">REPUBLIQUE DU CAMEROUN / REPUBLIC OF CAMEROON</p>
                <h2>{{ $generalSetting->title ?? 'PAX HIGHER INSTITUTE (PAXHI)' }}</h2>
                <p>{{ $generalSetting->address ?? 'Bamunka-Ndop, North West Region, Cameroon' }}</p>
                <p>Tel: {{ $generalSetting->phone ?? '' }} | Email: {{ $generalSetting->email ?? '' }}</p>
            </div>
        </div>

        <div class="title">
            STUDENT COURSE REGISTRATION FORM (FORM A3)
        </div>

        <div class="sub-title">
            Academic Year: <strong>{{ $record->session->title ?? '' }}</strong> | 
            Semester: <strong>{{ $record->semester->title ?? '' }}</strong>
        </div>

        <div class="info-section">
            <div class="info-row">
                <div class="info-col">
                    <span class="field-label">Student Name:</span>
                    <span class="field-value">{{ $student->first_name }} {{ $student->last_name }}</span>
                </div>
                <div class="info-col">
                    <span class="field-label">Matricule:</span>
                    <span class="field-value">{{ $enrollment->matricule ?? $student->student_id }}</span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-col">
                    <span class="field-label">Field:</span>
                    <span class="field-value">{{ $enrollment->program->faculty->title ?? '' }}</span>
                </div>
                <div class="info-col">
                    <span class="field-label">Specialty:</span>
                    <span class="field-value">{{ $enrollment->program->title ?? '' }}</span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-col">
                    <span class="field-label">Level:</span>
                    <span class="field-value">Level {{ $enrollment->semester->year ?? '1' }}</span>
                </div>
                <div class="info-col">
                    <span class="field-label">Registration Date:</span>
                    <span class="field-value">{{ $record->created_at->format('d/m/Y') }}</span>
                </div>
            </div>
        </div>

        <table class="courses">
            <thead>
                <tr>
                    <th style="width: 5%;">S/N</th>
                    <th style="width: 15%;">Course Code</th>
                    <th style="width: 50%;">Course Title</th>
                    <th style="width: 15%;">Credit Value</th>
                    <th style="width: 15%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @php $totalCredits = 0; @endphp
                @foreach($subjects as $key => $subject)
                @php $totalCredits += (int)($subject['credits'] ?? 0); @endphp
                <tr>
                    <td class="center">{{ $key + 1 }}</td>
                    <td class="center">{{ $subject['code'] ?? '' }}</td>
                    <td>{{ $subject['title'] ?? '' }}</td>
                    <td class="center">{{ $subject['credits'] ?? 0 }}</td>
                    <td class="center">
                        @php
                            $subjectType = $subject['type'] ?? 0;
                        @endphp
                        @if($subjectType == 1)
                            <span class="status-badge status-core">{{ __('subject_type_compulsory') }}</span>
                        @elseif($subjectType == 2)
                            <span class="status-badge status-elective">{{ __('subject_type_university_requirement') }}</span>
                        @else
                            <span class="status-badge status-resit">{{ __('subject_type_optional') }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">TOTAL CREDIT VALUE:</td>
                    <td class="center">{{ $totalCredits }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="signature-section">
            <div class="signature-block">
                <p class="signature-line"></p>
                <p class="signature-label">HND Coordinator</p>
                <p style="font-size: 10pt;">{{ $settings->hnd_coordinator_name ?? '' }}</p>
            </div>
            <div class="signature-block">
                <p class="signature-line"></p>
                <p class="signature-label">Director of Academics</p>
                <p style="font-size: 10pt;">{{ $settings->director_academics_name ?? '' }}</p>
            </div>
        </div>

        @if($record->verification_code)
        <div style="margin-top: 25px; display: flex; align-items: center; gap: 15px; border: 1px solid #ccc; padding: 12px; border-radius: 5px;">
            <div style="flex-shrink: 0;">
                {!! QrCode::size(90)->margin(1)->generate($record->verification_url) !!}
            </div>
            <div style="font-size: 9pt; color: #333;">
                <p style="font-weight: bold; margin-bottom: 4px;">Scan to Verify This Document</p>
                <p style="margin: 0; word-break: break-all;">{{ $record->verification_url }}</p>
                <p style="margin-top: 4px; color: #666;">Code: {{ $record->verification_code }}</p>
            </div>
        </div>
        @endif

        <div class="notes">
            <h4>Important Notes:</h4>
            <ul>
                <li>This form confirms your course registration for the stated semester.</li>
                <li>Any changes to course registration must be approved by the HND Coordinator.</li>
                <li>Students must attend all registered courses and complete all assessments.</li>
                <li>Keep this form as proof of registration for your records.</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 20px; font-size: 9pt; color: #666;">
            Generated on: {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
    @endforeach

</body>
</html>
