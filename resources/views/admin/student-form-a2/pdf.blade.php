<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Confirmation (Form A2) - {{ $student->first_name }} {{ $student->last_name }}</title>
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
            margin-bottom: 20px;
            height: 120px;
        }
        .logo {
            width: 80px;
            height: auto;
            float: left;
        }
        .institution-info {
            text-align: center;
        }
        .title {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            font-size: 12pt;
        }
        .section-header {
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 10pt;
        }
        .row {
            display: flex;
            margin-bottom: 5px;
        }
        .col {
            flex: 1;
        }
        .field-label {
            display: inline-block;
        }
        .field-value {
            display: inline-block;
            border-bottom: 1px solid #000;
            padding-left: 5px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 10pt;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 8px;
        }
        th {
            background-color: #f0f0f0;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer-note {
            margin-top: 10px;
            font-style: italic;
            font-size: 10pt;
        }
        .signature-section {
            margin-top: 20px;
            text-align: right;
        }
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 250px;
        }
        .clearance-section {
            margin-top: 20px;
        }
        
        /* Admin toolbar */
        .admin-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #343a40;
            padding: 10px 20px;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-toolbar .title-text {
            color: white;
            font-weight: bold;
        }
        .admin-toolbar .btn {
            padding: 8px 16px;
            margin-left: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .admin-toolbar .btn:hover { opacity: 0.9; }
        
        /* Print specific styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .admin-toolbar {
                display: none !important;
            }
            .content-wrapper {
                margin-top: 0 !important;
            }
        }
        
        @media screen {
            .content-wrapper {
                margin-top: 60px;
            }
        }
    </style>
</head>
<body @if(!isset($is_preview) || !$is_preview) onload="window.print()" @endif>

    @if(isset($is_admin) && $is_admin && isset($is_preview) && $is_preview)
    <div class="admin-toolbar">
        <span class="title-text">Form A2 Preview - {{ $student->first_name }} {{ $student->last_name }}</span>
        <div>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="{{ route('admin.student-form-a2.download', $enrollment->id) }}" class="btn btn-success" target="_blank">
                <i class="fas fa-download"></i> Download/Print
            </a>
            <button class="btn btn-secondary" onclick="window.close()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
    @endif

    <div class="content-wrapper">
        {{-- The configured letterhead, exactly as authored, in place of a
             masthead each form used to assemble from Settings. --}}
        {{-- Despite the file name these are returned as views, not run through
             dompdf, so the letterhead must resolve its image to a URL. A
             filesystem path would simply not load in a browser. --}}
        @include('partials.document-header', ['forPdf' => false, 'rule' => true])


        <div class="title">
            ADMISSION CONFIRMATION (FORM A2) – {{ $enrollment->program->shortcode ?? 'PROGRAM' }}
        </div>

        <div class="section-header">PART 1: STUDENT INFORMATION</div>
        
        <div style="margin-bottom: 5px;">
            <span class="field-label">Name (as on Birth Certificate):</span>
            <span class="field-value" style="width: 75%;">{{ $student->first_name }} {{ $student->last_name }}</span>
        </div>

        <div style="margin-bottom: 5px;">
            <span class="field-label">Date of Birth:</span>
            <span class="field-value" style="width: 25%;">{{ $student->dob ? \Carbon\Carbon::parse($student->dob)->format('d/m/Y') : '' }}</span>
            
            <span class="field-label" style="margin-left: 20px;">Place of Birth:</span>
            <span class="field-value" style="width: 35%;">{{ $student->present_village ?? $student->present_district ?? '' }}</span>
        </div>

        <div style="margin-bottom: 5px;">
            <span class="field-label">Sex:</span>
            <span class="field-value" style="width: 25%;">{{ $student->gender == 1 ? 'Male' : ($student->gender == 2 ? 'Female' : 'Other') }}</span>
            
            <span class="field-label" style="margin-left: 20px;">Nationality:</span>
            <span class="field-value" style="width: 35%;">{{ $student->nationality ?? 'Cameroonian' }}</span>
        </div>

        <div style="margin-bottom: 5px;">
            <span class="field-label">Telephone:</span>
            <span class="field-value" style="width: 80%;">{{ $student->phone ?? '' }}</span>
        </div>

        <div style="margin-bottom: 5px;">
            <span class="field-label">Faculty/School:</span>
            <span class="field-value" style="width: 75%;">{{ $enrollment->program->faculty->title ?? '' }}</span>
        </div>

        <div style="margin-bottom: 5px;">
            <span class="field-label">Department & Level:</span>
            <span class="field-value" style="width: 70%;">{{ $enrollment->program->academicDepartment->title ?? '' }} - {{ $enrollment->semester->title ?? '' }}</span>
        </div>

        <div style="margin-bottom: 5px;">
            <span class="field-label">Number of O'Level Papers / Probatoire Series:</span>
            <span class="field-value" style="width: 30%;">{{ $student->school_graduation_point ?? '' }}</span>
            
            <span class="field-label" style="margin-left: 10px;">Year Obtained:</span>
            <span class="field-value" style="width: 15%;">{{ $student->school_graduation_year ?? '' }}</span>
        </div>

        <div style="margin-bottom: 10px;">
            <span class="field-label">Number of A'Level Papers / BAC Series:</span>
            <span class="field-value" style="width: 30%;">{{ $student->collage_graduation_point ?? '' }}</span>
            
            <span class="field-label" style="margin-left: 10px;">Year Obtained:</span>
            <span class="field-value" style="width: 15%;">{{ $student->collage_graduation_year ?? '' }}</span>
        </div>

        <div class="section-header">PART 2: FINANCE OFFICE DETAILS</div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">FEE BREAKDOWN</th>
                    <th style="width: 20%;">AMOUNT (FCFA)</th>
                    <th style="width: 40%;">Finance Department</th>
                </tr>
            </thead>
            <tbody>
                @foreach($breakdowns as $breakdown)
                <tr>
                    <td>{{ $breakdown->title }}</td>
                    <td class="text-right">{{ number_format($breakdown->amount, 0) }}</td>
                    <td></td>
                </tr>
                @endforeach
                
                @if(count($breakdowns) == 0)
                <tr>
                    <td>Tuition (First Installment)</td>
                    <td class="text-right">{{ number_format($totalAmount, 0) }}</td>
                    <td></td>
                </tr>
                @endif

                <tr style="font-weight: bold;">
                    <td>TOTAL</td>
                    <td class="text-right">{{ number_format($totalAmount, 0) }}</td>
                    <td class="text-center">{{ $totalAmountWords }} FCFA</td>
                </tr>
            </tbody>
        </table>

        <div class="footer-note">
            The Finance Department of {{ institution_code() }} confirms that this candidate has paid all the above fees and can therefore be registered in the University's Register and on the class list of the Faculty/School.
        </div>

        <div class="signature-section">
            <span style="font-weight: bold;">Director of Finance:</span>
            <span class="signature-line">{{ $settings->finance_director_name ?? '____________________' }}</span>
        </div>

        <div class="section-header">PART 3: REGISTRY CLEARANCE</div>
        
        <div class="footer-note">
            This is to certify that the above-named candidate has completed all required registration formalities at {{ institution_name() }} and has been assigned the following matriculation number:
        </div>

        <div style="margin-top: 15px; margin-bottom: 15px;">
            <span class="field-label" style="font-weight: bold;">Matriculation No.:</span>
            <span class="field-value" style="width: 50%;">{{ $enrollment->matricule ?? $student->student_id }}</span>
        </div>

        <div class="footer-note">
            The candidate is hereby authorized to attend lectures in the stated Faculty/School and Department.
        </div>

        <div class="signature-section" style="margin-top: 40px;">
            <span style="font-weight: bold;">Registrar:</span>
            <span class="signature-line">{{ $settings->registrar_name ?? '____________________' }}</span>
        </div>
    </div>

</body>
</html>
