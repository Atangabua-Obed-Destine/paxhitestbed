<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20mm 18mm 18mm 18mm; }
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { color: #1a1a1a; font-size: 10.5px; line-height: 1.5; margin: 0; padding: 0; }

        /* ── Header ── */
        .header { text-align: center; margin-bottom: 8px; }
        .header .institution-name { font-size: 15px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .header .institution-details { font-size: 9px; line-height: 1.4; color: #333; }
        .header .logo { width: 65px; height: auto; margin-bottom: 4px; }



        /* ── Form title ── */
        .form-title { font-size: 13px; font-weight: bold; text-transform: uppercase; margin: 10px 0 4px; }
        .academic-year-box {
            display: inline-block; border: 1px solid #333; padding: 3px 8px;
            font-size: 10px; margin-left: 12px; vertical-align: middle;
        }
        .academic-year-box .label { font-style: italic; }

        /* ── Section numbering ── */
        .section { margin-top: 10px; margin-bottom: 6px; page-break-inside: avoid; }
        .section-title {
            font-size: 11px; font-weight: bold; text-transform: uppercase;
            border-bottom: 1.5px solid #333; padding-bottom: 2px; margin-bottom: 6px;
        }
        .section-subtitle { font-size: 9.5px; font-style: italic; color: #444; margin-bottom: 6px; }

        /* ── Blank line ── */
        .field-row { margin-bottom: 5px; }
        .field-label { font-weight: bold; font-size: 10px; display: inline; }
        .blank {
            display: inline-block; border-bottom: 1px solid #333;
            min-width: 180px; height: 14px; vertical-align: bottom;
        }
        .blank-short { min-width: 100px; }
        .blank-long { min-width: 300px; }
        .blank-full { width: 100%; }
        .blank-half { min-width: 48%; width: 48%; }

        /* ── Checkbox ── */
        .checkbox {
            display: inline-block; width: 12px; height: 12px;
            border: 1px solid #333; vertical-align: middle; margin: 0 2px;
        }

        /* ── Tables ── */
        table.form-table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 10px; }
        table.form-table th, table.form-table td {
            border: 1px solid #333; padding: 4px 6px; text-align: left; vertical-align: top;
        }
        table.form-table th { background: #eee; font-weight: bold; font-size: 9.5px; }
        table.form-table td { min-height: 20px; height: 20px; }
        table.form-table td.tall { height: 28px; }

        /* ── Two-column layout ── */
        .two-col { width: 100%; }
        .two-col td { width: 50%; border: none; padding: 0 8px 0 0; vertical-align: top; }

        /* ── Page break ── */
        .page-break { page-break-before: always; }

        /* ── Official use header ── */
        .official-header {
            font-size: 12px; font-weight: bold; text-transform: uppercase;
            text-align: center; margin-bottom: 4px;
        }
        .official-sub { text-align: center; font-style: italic; font-size: 9.5px; margin-bottom: 10px; }

        /* ── Submission address ── */
        .address-block { margin-left: 20px; font-size: 10px; line-height: 1.6; }

        /* ── Footer page number ── */
        .page-number { text-align: center; font-size: 9px; color: #888; margin-top: 10px; }

        /* ── Inline fields ── */
        .inline-fields { display: block; margin-bottom: 4px; }

        /* ── Note box ── */
        .note-box {
            border: 1px solid #333; padding: 6px 10px; margin: 8px 0;
            font-size: 9.5px;
        }
    </style>
</head>
<body>
@php
    $fe = $fieldEnabled;
    $institutionName = optional($setting)->title ?? config('app.name');
    $address = optional($setting)->address ?? '';
    $phone = optional($setting)->phone ?? '';
    $email = optional($setting)->email ?? '';
    $website = optional($setting)->website ?? '';
    $currency = optional($setting)->currency_symbol ?? 'FCFA';
    $sectionNum = 0;
@endphp

{{-- ═══════════════════════ PAGE 1 ═══════════════════════ --}}


{{-- Institution Header --}}
<div class="header">
    @php
        $logoPath = null;
        if ($appSetting && $appSetting->logo_left && is_file(public_path('uploads/application-setting/'.$appSetting->logo_left))) {
            $logoPath = public_path('uploads/application-setting/'.$appSetting->logo_left);
        }
    @endphp
    @if($logoPath)
        <img src="{{ $logoPath }}" class="logo" alt="Logo"><br>
    @endif
    <div class="institution-name">{{ $institutionName }}</div>
    <div class="institution-details">
        @if($address){{ $address }}<br>@endif
        @if($phone)Tel: {{ $phone }}@endif
        @if($email) &nbsp; E-mail: {{ $email }}@endif
        @if($website)<br>Website: {{ $website }}@endif
    </div>
</div>

{{-- Form Title --}}
<div style="text-align: center;">
    <span class="form-title">{{ $degreeType->title }} Application Form</span>
    <span class="academic-year-box">
        <span class="label">Academic Year:</span>
        <span class="blank blank-short"></span>
    </span>
</div>

{{-- ── SECTION: PERSONAL DETAILS ── --}}
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Personal Details</div>
    <div class="section-subtitle">Please enter all information exactly as it appears in your Birth Certificate.</div>

    <div class="field-row">
        <span class="field-label">Name (Surname first):</span>
        <span class="blank blank-long"></span>
    </div>

    <div class="field-row">
        <span class="field-label">Gender:</span>
        Male <span class="checkbox"></span> &nbsp;&nbsp;
        Female <span class="checkbox"></span>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <span class="field-label">Date of Birth:</span>
        Day: <span class="blank blank-short" style="min-width:60px;"></span>
        Month: <span class="blank blank-short" style="min-width:80px;"></span>
        Year: <span class="blank blank-short" style="min-width:60px;"></span>
    </div>

    @if($fe('application_birth_city') || $fe('application_birth_division') || $fe('application_birth_region') || $fe('application_birth_country'))
    <div class="field-row">
        <span class="field-label">Place of Birth:</span>
    </div>
    @if($fe('application_birth_city'))
    <div class="field-row" style="padding-left: 80px;">
        City/Town: <span class="blank" style="min-width:320px;"></span>
    </div>
    @endif
    @if($fe('application_birth_division'))
    <div class="field-row" style="padding-left: 80px;">
        Division: <span class="blank" style="min-width:330px;"></span>
    </div>
    @endif
    @if($fe('application_birth_region'))
    <div class="field-row" style="padding-left: 80px;">
        Region: <span class="blank" style="min-width:340px;"></span>
    </div>
    @endif
    @if($fe('application_birth_country'))
    <div class="field-row" style="padding-left: 80px;">
        Country: <span class="blank" style="min-width:335px;"></span>
    </div>
    @endif
    @endif

    @if($fe('application_religion'))
    <div class="field-row">
        <span class="field-label">Religion:</span> <span class="blank" style="min-width:250px;"></span>
    </div>
    @endif

    @if($fe('application_catholic_baptised'))
    <div class="field-row" style="padding-left: 20px;">
        If Catholic: &nbsp; Baptised <span class="checkbox"></span> &nbsp;&nbsp;
        Not Baptised <span class="checkbox"></span> &nbsp;&nbsp;
        Confirmed <span class="checkbox"></span> &nbsp;&nbsp;
        First Communion <span class="checkbox"></span>
    </div>
    @endif

    @if($fe('application_mother_tongue'))
    <div class="field-row">
        <span class="field-label">Mother Tongue:</span> <span class="blank" style="min-width:250px;"></span>
    </div>
    @endif

    <div class="field-row">
        <span class="field-label">Nationality:</span> <span class="blank" style="min-width:280px;"></span>
    </div>

    <div class="field-row">
        <span class="field-label">National ID Card Number:</span> <span class="blank" style="min-width:160px;"></span>
        @if($fe('application_national_id_issue_date'))
        &nbsp; Issued on: <span class="blank blank-short" style="min-width:100px;"></span>
        @endif
        @if($fe('application_national_id_issue_place'))
        &nbsp; At: <span class="blank blank-short" style="min-width:100px;"></span>
        @endif
    </div>

    <div class="field-row">
        <span class="field-label">Passport Number:</span> <span class="blank" style="min-width:160px;"></span>
        @if($fe('application_passport_issue_date'))
        &nbsp; Issued on: <span class="blank blank-short" style="min-width:100px;"></span>
        @endif
        @if($fe('application_passport_issue_country'))
        &nbsp; Country of Issue: <span class="blank blank-short" style="min-width:100px;"></span>
        @endif
    </div>
</div>

{{-- ── SECTION: ADDRESSES ── --}}
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Addresses</div>

    @if($fe('application_postal_address'))
    <div class="section-subtitle">(Please, include Post Box number)</div>
    @endif

    <div class="field-row">
        <span class="field-label">Current Residence:</span>
    </div>
    <div class="field-row" style="padding-left: 20px;">
        Country: <span class="blank" style="min-width:340px;"></span>
    </div>
    <div class="field-row" style="padding-left: 20px;">
        Province/Region: <span class="blank" style="min-width:260px;"></span>
        &nbsp; District: <span class="blank" style="min-width:150px;"></span>
    </div>
    <div class="field-row" style="padding-left: 20px;">
        Village/Quarter: <span class="blank" style="min-width:180px;"></span>
        &nbsp; House/Street Address: <span class="blank" style="min-width:180px;"></span>
    </div>

    <div class="field-row" style="margin-top:6px;">
        <span class="field-label">Permanent Address:</span>
    </div>
    <div class="field-row" style="padding-left: 20px;">
        Province/Region: <span class="blank" style="min-width:200px;"></span>
        &nbsp; District: <span class="blank" style="min-width:120px;"></span>
        &nbsp; Village: <span class="blank" style="min-width:120px;"></span>
    </div>
    <div class="field-row" style="padding-left: 20px;">
        House/Street Address: <span class="blank" style="min-width:340px;"></span>
    </div>
    @if($fe('application_postal_address'))
    <div class="field-row" style="padding-left: 20px;">
        Postal Address / P.O. Box: <span class="blank" style="min-width:310px;"></span>
    </div>
    @endif

    <div class="field-row" style="margin-top:6px;">
        <span class="field-label">Student's Phone Number(s) (Include Country Code):</span> <span class="blank" style="min-width:230px;"></span>
    </div>
    @if($fe('application_alternate_phone'))
    <div class="field-row">
        <span class="field-label">Alternate Phone:</span> <span class="blank" style="min-width:300px;"></span>
    </div>
    @endif
    <div class="field-row">
        <span class="field-label">Student's Email Address:</span> <span class="blank" style="min-width:320px;"></span>
    </div>
</div>

{{-- ── SECTION: GUARDIANS ── --}}
@if($fe('application_guardians'))
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Parent / Guardian / Sponsor</div>

    <table class="form-table">
        <thead>
            <tr>
                <th style="width:12%;">Type</th>
                <th style="width:22%;">Full Name</th>
                <th style="width:16%;">Occupation</th>
                <th style="width:28%;">Address</th>
                <th style="width:22%;">Phone Number</th>
            </tr>
        </thead>
        <tbody>
            <tr><td class="tall">Parent</td><td></td><td></td><td></td><td></td></tr>
            <tr><td class="tall">Sponsor</td><td></td><td></td><td></td><td></td></tr>
            <tr><td class="tall">Guardian</td><td></td><td></td><td></td><td></td></tr>
        </tbody>
    </table>
</div>
@endif

{{-- ── SECTION: ACADEMIC QUALIFICATION ── --}}
@if($fe('application_academic_history'))
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Academic Qualification</div>
    <div class="section-subtitle">
        List in chronological order (from Secondary school), all colleges/universities you have attended.
        Please, provide clean photocopies of any certificates obtained from these institutions.
        Students with foreign certificates in languages other than English or French will provide a translation by a certified translator alongside the original document.
    </div>
    <div class="section-subtitle">
        <em>For holders of the GCE Ordinary Level and Advanced Level, indicate the number of papers obtained and the year.</em><br>
        <em>For holders of Probatoire and Baccalaureate, indicate the series obtained and the year.</em>
    </div>

    @php
        // Same cards the online form prescribes, so paper and web agree.
        $blankCards = \App\Services\DegreeTypeFormConfig::qualifications($degreeType);
        $blankExtras = max(0, 5 - count($blankCards));
    @endphp
    <table class="form-table">
        <thead>
            <tr>
                <th style="width:16%;" rowspan="2">Qualification</th>
                <th style="width:14%;" rowspan="2">Awarding Body</th>
                <th style="width:16%;" rowspan="2">Name of Institution</th>
                <th style="width:12%;" rowspan="2">City (Country)</th>
                <th style="width:10%;" rowspan="2">Language of Instruction</th>
                <th colspan="2" style="text-align:center;">Years</th>
                <th colspan="2" style="text-align:center;">Certificates Obtained</th>
            </tr>
            <tr>
                <th style="width:7%;">From</th>
                <th style="width:7%;">To</th>
                <th style="width:9%;">GCE O/L or PROB</th>
                <th style="width:9%;">GCE A/L or BAC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($blankCards as $blankCard)
            <tr>
                <td class="tall"><strong>{{ $blankCard['label'] }}</strong></td>
                <td></td><td></td><td></td><td></td>
                <td></td><td></td><td></td><td></td>
            </tr>
            @endforeach
            @for($i = 0; $i < $blankExtras; $i++)
            <tr>
                <td class="tall"></td><td></td><td></td><td></td><td></td>
                <td></td><td></td><td></td><td></td>
            </tr>
            @endfor
        </tbody>
    </table>
</div>
@endif

{{-- ── SECTION: ACADEMIC OBJECTIVES (Programme Choices) ── --}}
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Academic Objectives</div>
    <div class="section-subtitle">
        Please indicate, in order of preference, the {{ $degreeType->title }} programme you would like to study at the {{ $institutionName }}.
    </div>

    <table class="form-table">
        <thead>
            <tr>
                <th style="width:25%;">Choices</th>
                <th>{{ $degreeType->title }} Programme</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>First Choice</td><td class="tall"></td></tr>
            @if($fe('application_program_choice_second'))
            <tr><td>Second Choice</td><td class="tall"></td></tr>
            @endif
            @if($fe('application_program_choice_third'))
            <tr><td>Third Choice</td><td class="tall"></td></tr>
            @endif
        </tbody>
    </table>

    @if($programs->count())
    <div class="note-box">
        <strong>Available {{ $degreeType->title }} Programmes:</strong><br>
        @foreach($programs as $i => $program)
            {{ $i + 1 }}. {{ $program->title }}@if(!$loop->last), @endif
        @endforeach
    </div>
    @endif

    <div class="note-box">
        <strong>(N.B)</strong> The applicant's first choice will be respected as much as possible provided he/she has the prerequisite subjects for the programme chosen. Otherwise, they will be offered the next best choice.
    </div>
</div>

{{-- ── SECTION: LANGUAGES ── --}}
@if($fe('application_language_proficiency'))
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Languages</div>

    @if($fe('application_studied_in_english'))
    <div class="field-row">
        Was English the language of instruction at the secondary/high school(s) you attended?
        &nbsp; Yes <span class="checkbox"></span> &nbsp; No <span class="checkbox"></span>
    </div>
    @endif

    @if($fe('application_instruction_language_secondary'))
    <div class="field-row">
        If NO, specify the language of instruction at these schools: <span class="blank" style="min-width:200px;"></span>
    </div>
    @endif

    <div class="field-row" style="margin-top:6px;">
        Indicate any other language(s) you understand or speak:
    </div>

    <table class="form-table">
        <thead>
            <tr>
                <th style="width:22%;" rowspan="2">Language</th>
                <th style="width:14%;" rowspan="2">Years of Study</th>
                <th colspan="4" style="text-align:center;">Fluency</th>
            </tr>
            <tr>
                <th style="width:16%;">Excellent</th>
                <th style="width:16%;">Good</th>
                <th style="width:16%;">Fair</th>
                <th style="width:16%;">Minimal</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < 4; $i++)
            <tr>
                <td class="tall"></td><td></td>
                <td style="text-align:center;"></td>
                <td style="text-align:center;"></td>
                <td style="text-align:center;"></td>
                <td style="text-align:center;"></td>
            </tr>
            @endfor
        </tbody>
    </table>

    <div class="note-box">
        <strong>English Proficiency</strong><br>
        Applicants from a non-English speaking background who have passed "A" Level Examinations or equivalent will be required to demonstrate proficiency in the English Language, by sitting and passing an English Language Test.
    </div>
</div>
@endif

{{-- ── SECTION: SUPPORTING DOCUMENTS ── --}}
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Supporting Documents (Admission Requirements)</div>

    <ol style="font-size:10px; padding-left: 20px; margin-top: 4px;">
        @if($formSettings['fee_enabled'] ?? false)
        <li>A Receipt of Registration Fee of {{ number_format($formSettings['fee_amount']) }} {{ $currency }} paid into the {{ $institutionName }} or any other evidence of payment.</li>
        @endif
        @foreach($documents as $key => $doc)
        <li>{{ $doc['label'] }}@if(!empty($doc['description'])) — <em>{{ $doc['description'] }}</em>@endif</li>
        @endforeach
    </ol>
</div>

{{-- ── SECTION: DECLARATION ── --}}
@if($fe('application_declaration'))
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Declaration</div>

    <p style="font-size:10px;">
        I certify that all the information I have given on this application form is correct and complete.
        I understand that withholding or providing false information or fake documents in support of this application
        may disqualify me from admission or later be used as grounds for my dismissal from the {{ $institutionName }}.
    </p>

    <div class="field-row">
        <span class="field-label">Applicant's Name:</span> <span class="blank" style="min-width:240px;"></span>
        &nbsp;&nbsp;&nbsp;
        <span class="field-label">Signature:</span> <span class="blank" style="min-width:130px;"></span>
    </div>
    <div class="field-row" style="text-align:right;">
        <span class="field-label">Date:</span> <span class="blank" style="min-width:130px;"></span>
    </div>
</div>
@endif

{{-- ── SECTION: SUBMISSION ADDRESS ── --}}
@php $sectionNum++; @endphp
<div class="section">
    <div class="section-title">{{ $sectionNum }}. Submit your complete Application File to:</div>

    <div class="address-block">
        The Registrar<br>
        {{ $institutionName }}<br>
        @if($address){{ $address }}<br>@endif
        @if($phone)Contact Number: {{ $phone }}<br>@endif
        @if($website)Website: {{ $website }}@endif
        @if($email) &nbsp;&nbsp; Email: {{ $email }}@endif
    </div>

    <p style="font-size:9px; margin-top:8px; text-align:center; color:#555;">
        This Application Form could equally be downloaded and filled online from the website above.
    </p>
</div>

{{-- ═══════════════════════ PAGE: ADMISSION BOARD RESULTS ═══════════════════════ --}}
@if($fe('application_board_review'))
<div class="page-break"></div>

<div class="official-header">{{ $degreeType->title }} Admission Board Results</div>
<div class="official-sub">(For Official Use ONLY)</div>

{{-- Requirements met --}}
<table class="form-table" style="width:70%; margin: 0 auto 12px;">
    <thead>
        <tr><th colspan="3" style="text-align:center;">Does the Candidate meet the basic requirements for admission?</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>University Admission Requirements</td>
            <td style="width:40px; text-align:center;">YES</td>
            <td style="width:40px; text-align:center;">NO</td>
        </tr>
        <tr>
            <td>{{ $degreeType->title }} Programme Requirements</td>
            <td style="width:40px; text-align:center;">YES</td>
            <td style="width:40px; text-align:center;">NO</td>
        </tr>
    </tbody>
</table>

{{-- Admitted --}}
<table class="form-table" style="margin-bottom:12px;">
    <thead>
        <tr>
            <th style="width:25%;">Admitted</th>
            <th>Observation/Comment</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>First Choice:</td><td class="tall"></td></tr>
        <tr><td>Second Choice:</td><td class="tall"></td></tr>
        <tr><td>Third Choice:</td><td class="tall"></td></tr>
    </tbody>
</table>

{{-- Rejected --}}
<table class="form-table" style="margin-bottom:20px;">
    <thead>
        <tr>
            <th style="width:25%;">Rejected</th>
            <th>Reasons for Rejection</th>
        </tr>
    </thead>
    <tbody>
        <tr><td class="tall" style="height:50px;"></td><td></td></tr>
    </tbody>
</table>

{{-- Signatories --}}
<div style="text-align:center; font-weight:bold; font-size:11px; margin-bottom:6px;">Admission Board Signatories</div>
<table class="form-table">
    <thead>
        <tr>
            <th style="width:35%;">Name / Function</th>
            <th style="width:35%;">Signature</th>
            <th style="width:30%;">Date</th>
        </tr>
    </thead>
    <tbody>
        <tr><td class="tall"></td><td></td><td></td></tr>
        <tr><td class="tall"></td><td></td><td></td></tr>
        <tr><td class="tall"></td><td></td><td></td></tr>
    </tbody>
</table>
@endif

</body>
</html>
