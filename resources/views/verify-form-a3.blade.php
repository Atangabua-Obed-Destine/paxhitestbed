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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 850px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header .logo {
            width: 60px;
            height: auto;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 40px;
        }
        .not-found {
            text-align: center;
            padding: 40px 20px;
        }
        .not-found .icon {
            font-size: 80px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .not-found h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .not-found p {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .verified {
            text-align: center;
            margin-bottom: 30px;
        }
        .verified .icon {
            font-size: 80px;
            color: #27ae60;
            margin-bottom: 20px;
        }
        .verified h2 {
            color: #27ae60;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .verified p {
            color: #7f8c8d;
        }
        .details-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .details-section h3 {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-item .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .detail-item .value {
            font-size: 15px;
            color: #2c3e50;
            font-weight: 600;
        }
        .courses-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .courses-table th {
            background: #3498db;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .courses-table th:first-child {
            border-radius: 6px 0 0 0;
        }
        .courses-table th:last-child {
            border-radius: 0 6px 0 0;
        }
        .courses-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ecf0f1;
            color: #2c3e50;
        }
        .courses-table tr:hover {
            background: #f1f8ff;
        }
        .courses-table .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        .courses-table .total-row td {
            border-top: 2px solid #3498db;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-core {
            background: #d4edda;
            color: #155724;
        }
        .badge-university {
            background: #cce5ff;
            color: #004085;
        }
        .badge-elective {
            background: #fff3cd;
            color: #856404;
        }
        .verification-meta {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }
        .verification-meta p {
            font-size: 12px;
            color: #95a5a6;
            margin-bottom: 5px;
        }
        .verification-meta .code {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #7f8c8d;
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 4px;
            display: inline-block;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 40px;
            text-align: center;
            border-top: 1px solid #ecf0f1;
        }
        .footer p {
            color: #95a5a6;
            font-size: 12px;
        }
        @media (max-width: 600px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            .content {
                padding: 20px;
            }
            .header {
                padding: 20px;
            }
            .header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        @if(isset($generalSetting->logo_path))
            <img src="{{ asset('uploads/setting/'.$generalSetting->logo_path) }}" class="logo" alt="Logo">
        @endif
        <h1>{{ institution_name() }} ({{ institution_code() }})</h1>
        <p>Form A3 &mdash; Course Registration Verification</p>
    </div>

    <div class="content">
        @if(!$record_found)
            {{-- Record NOT found --}}
            <div class="not-found">
                <div class="icon">&#10060;</div>
                <h2>Form A3 Not Found</h2>
                <p>No course registration record could be found for the verification code provided.<br>
                   Please check the code and try again.</p>
                <p style="font-size: 13px; color: #bdc3c7;">Code: <code>{{ $verification_code }}</code></p>
            </div>
        @else
            {{-- Record FOUND --}}
            <div class="verified">
                <div class="icon">&#9989;</div>
                <h2>Verified Course Registration</h2>
                <p>This Form A3 document has been verified as authentic.</p>
            </div>

            {{-- Student Information --}}
            <div class="details-section">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#3498db" viewBox="0 0 16 16"><path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3Zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                    Student Information
                </h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="label">Student Name</span>
                        <span class="value">{{ $student->first_name }} {{ $student->last_name }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Matricule</span>
                        <span class="value">{{ $enrollment->matricule ?? $student->student_id }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Field / Faculty</span>
                        <span class="value">{{ $enrollment->program->faculty->title ?? '—' }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Specialty / Program</span>
                        <span class="value">{{ $enrollment->program->title ?? '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Academic Information --}}
            <div class="details-section">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#3498db" viewBox="0 0 16 16"><path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/></svg>
                    Academic Details
                </h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="label">Academic Year</span>
                        <span class="value">{{ $record->session->title ?? '—' }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Semester</span>
                        <span class="value">{{ $record->semester->title ?? '—' }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Total Credits</span>
                        <span class="value">{{ $record->total_credits ?? 0 }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Registration Date</span>
                        <span class="value">{{ $record->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- Registered Courses --}}
            <div class="details-section">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#3498db" viewBox="0 0 16 16"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102z"/></svg>
                    Registered Courses
                </h3>
                <table class="courses-table">
                    <thead>
                        <tr>
                            <th style="width: 6%;">S/N</th>
                            <th style="width: 16%;">Code</th>
                            <th style="width: 48%;">Course Title</th>
                            <th style="width: 14%; text-align: center;">Credits</th>
                            <th style="width: 16%; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalCredits = 0; @endphp
                        @foreach($subjects as $key => $subject)
                            @php $totalCredits += (int)($subject['credits'] ?? 0); @endphp
                            <tr>
                                <td>{{ $key + 1 }}</td>
                                <td><strong>{{ $subject['code'] ?? '' }}</strong></td>
                                <td>{{ $subject['title'] ?? '' }}</td>
                                <td style="text-align: center;">{{ $subject['credits'] ?? 0 }}</td>
                                <td style="text-align: center;">
                                    @php $subjectType = $subject['type'] ?? 0; @endphp
                                    @if($subjectType == 1)
                                        <span class="status-badge badge-core">Compulsory</span>
                                    @elseif($subjectType == 2)
                                        <span class="status-badge badge-university">University Req.</span>
                                    @else
                                        <span class="status-badge badge-elective">Elective</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;">TOTAL CREDITS</td>
                            <td style="text-align: center;">{{ $totalCredits }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Verification Metadata --}}
            <div class="verification-meta">
                <p>Verification Code</p>
                <span class="code">{{ $record->verification_code }}</span>
                <p style="margin-top: 10px;">Verified on {{ now()->format('d/m/Y \a\t H:i:s') }}</p>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ institution_name() }}. All rights reserved.</p>
    </div>
</div>

</body>
</html>
