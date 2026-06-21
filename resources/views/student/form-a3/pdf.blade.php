<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Course Registration - Form A3</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
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
        
        /* Print specific styles */
        @media print {
            body {
                padding: 10px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body @if(!isset($is_preview) || !$is_preview) onload="window.print()" @endif>

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
            <p style="font-size: 10pt;">{{ $hnd_coordinator_name ?? '' }}</p>
        </div>
        <div class="signature-block">
            <p class="signature-line"></p>
            <p class="signature-label">Director of Academics</p>
            <p style="font-size: 10pt;">{{ $dir_acad_name ?? '' }}</p>
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
            @if(isset($attendance_enabled) && $attendance_enabled)
            <li>Failure to attend {{ $attendance_percentage ?? 75 }}% of lectures may result in disqualification from exams.</li>
            @endif
            <li>Keep this form as proof of registration for your records.</li>
        </ul>
    </div>

    <div style="text-align: center; margin-top: 20px; font-size: 9pt; color: #666;">
        Generated on: {{ now()->format('d/m/Y H:i:s') }}
    </div>

</body>
</html>
