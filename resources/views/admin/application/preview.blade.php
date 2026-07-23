<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Application') }} — {{ $row->registration_no }}</title>
    <style>
        /* ============================================================
           Official Application Form — print-oriented stylesheet.
           Self-contained (no framework classes) so it renders identically
           on screen and when exported to PDF via the browser print dialog.
           ============================================================ */
        :root {
            --ink: #111;
            --muted: #555;
            --line: #222;
            --rule: #bfbfbf;
            --band: #eef2f7;
            --band-strong: #d9e2ec;
            --accent: #0b3d91;
            --pad: 6mm;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #f0f0f0; color: var(--ink);
            font-family: 'Georgia', 'Times New Roman', Times, serif; font-size: 10.5pt; line-height: 1.35; }

        /* Toolbar (screen only) */
        .toolbar {
            position: sticky; top: 0; z-index: 1000; background: #1f2937; color: #fff;
            padding: 10px 16px; display: flex; gap: 10px; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,.15);
        }
        .toolbar .meta { flex: 1; text-align: left; font-family: system-ui, sans-serif; font-size: 12px; color: #cbd5e1; }
        .toolbar button {
            padding: 8px 18px; border-radius: 4px; border: 0; cursor: pointer; font-weight: 600;
            font-family: system-ui, sans-serif; font-size: 13px;
        }
        .btn-print { background: #10b981; color: #fff; }
        .btn-print:hover { background: #059669; }
        .btn-close { background: #ef4444; color: #fff; }
        .btn-close:hover { background: #dc2626; }

        /* A4 page frame */
        .page {
            width: 210mm; min-height: 297mm; margin: 12px auto; padding: 15mm 15mm 18mm 15mm;
            background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.15); position: relative;
        }

        /* Header */
        .doc-header { display: grid; grid-template-columns: 90px 1fr 35mm; gap: 8mm; align-items: center;
            border-bottom: 2px solid var(--line); padding-bottom: 4mm; }
        .doc-header .brand-logo img { max-height: 78px; max-width: 90px; object-fit: contain; }
        .doc-header .brand-body { text-align: center; }
        .brand-body .school-name { font-size: 16pt; font-weight: 700; letter-spacing: .5px;
            text-transform: uppercase; margin: 0; color: var(--accent); }
        .brand-body .school-tagline { font-size: 9pt; color: var(--muted); margin: 1mm 0 0; font-style: italic; }
        .brand-body .school-contact { font-size: 8.5pt; color: var(--muted); margin: 2mm 0 0;
            font-family: system-ui, sans-serif; }
        .brand-body .doc-title { display: inline-block; margin-top: 3mm; padding: 2mm 6mm;
            border: 1.5px solid var(--line); font-weight: 700; text-transform: uppercase;
            letter-spacing: 1.5px; font-size: 11.5pt; }
        .photo-box { width: 35mm; height: 45mm; border: 1px solid var(--line);
            display: flex; align-items: center; justify-content: center; overflow: hidden;
            font-size: 8pt; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }

        /* Reference bar */
        .ref-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm;
            margin: 5mm 0 3mm; border: 1px solid var(--line); background: var(--band); }
        .ref-bar .cell { padding: 2mm 3mm; border-right: 1px solid var(--rule); }
        .ref-bar .cell:last-child { border-right: 0; }
        .ref-bar .k { display: block; font-size: 7.5pt; text-transform: uppercase;
            letter-spacing: .5px; color: var(--muted); font-family: system-ui, sans-serif; }
        .ref-bar .v { display: block; font-weight: 700; font-size: 10pt; }

        /* Section */
        .section { margin-top: 6mm; page-break-inside: avoid; }
        .section-title { background: var(--band-strong); border: 1px solid var(--line);
            padding: 2mm 3mm; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
            font-size: 10pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .section-title .num { display: inline-block; width: 14px; text-align: right; margin-right: 4mm; color: var(--accent); }

        /* Info grid: two-column labeled rows */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 8mm;
            border: 1px solid var(--rule); border-top: 0; }
        .info-grid.single { grid-template-columns: 1fr; }
        .info-grid .cell { padding: 1.6mm 3mm; border-bottom: 1px solid var(--rule);
            display: grid; grid-template-columns: 46% 54%; align-items: baseline; gap: 3mm; }
        .info-grid .cell:nth-last-child(-n+2) { border-bottom: 0; }
        .info-grid.single .cell:last-child { border-bottom: 0; }
        .info-grid .k { font-weight: 700; color: var(--ink); font-size: 9.5pt; }
        .info-grid .v { color: var(--ink); }
        .info-grid .v.empty { color: var(--muted); font-style: italic; }
        .info-grid .cell.wide { grid-column: 1 / -1; grid-template-columns: 23% 77%; }

        /* Tables (repeaters: guardians, academic, languages, documents) */
        table.data { width: 100%; border-collapse: collapse; font-size: 9.5pt;
            border: 1px solid var(--line); }
        table.data th, table.data td { border: 1px solid var(--rule); padding: 2mm 3mm; vertical-align: top; text-align: left; }
        table.data thead th { background: var(--band); font-size: 8.5pt; text-transform: uppercase;
            letter-spacing: .5px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.data tbody tr:nth-child(even) td { background: #fafbfc; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* Sacraments inline chips */
        .chips { display: flex; gap: 3mm; padding: 2mm 3mm; border: 1px solid var(--rule); border-top: 0; }
        .chip { padding: 1mm 3mm; border: 1px solid var(--rule); font-size: 9pt; }
        .chip.yes { background: #e8f5e9; border-color: #a5d6a7; }
        .chip.no  { background: #fdecea; border-color: #f5b7b1; }

        /* Declaration & signatures */
        .declaration { margin-top: 6mm; padding: 4mm; border: 1px solid var(--rule);
            font-size: 9.5pt; text-align: justify; line-height: 1.55; }
        .signature-grid { margin-top: 6mm; display: grid; grid-template-columns: 1fr 1fr; gap: 12mm; }
        .signature-block { text-align: center; }
        .signature-block .sig-line {
            height: 18mm; border-bottom: 1px solid var(--line); display: flex; align-items: flex-end; justify-content: center;
        }
        .signature-block .sig-line img { max-height: 16mm; max-width: 60%; object-fit: contain; margin-bottom: 1mm; }
        .signature-block .caption { margin-top: 1.5mm; font-size: 9pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px; }
        .signature-block .sub { font-size: 8pt; color: var(--muted); }

        /* Official use only block */
        .official-use { margin-top: 6mm; border: 1.5px solid var(--line); }
        .official-use .oh { background: var(--band-strong); padding: 2mm 3mm; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px; font-size: 9.5pt;
            -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .official-use .ob { padding: 4mm 3mm; display: grid; grid-template-columns: 1fr 1fr; gap: 6mm 8mm; }
        .official-use .field { display: grid; grid-template-columns: 40% 60%; gap: 3mm; align-items: end; }
        .official-use .field .lbl { font-size: 9pt; font-weight: 700; }
        .official-use .field .box { border-bottom: 1px solid var(--line); min-height: 6mm; }

        /* Documents section */
        .doc-item { border: 1px solid var(--rule); margin-top: 4mm; page-break-inside: avoid; }
        .doc-item .doc-head { background: var(--band); padding: 1.5mm 3mm; font-weight: 700;
            display: flex; justify-content: space-between; align-items: center; font-size: 9.5pt;
            -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .doc-item .doc-head .badge { background: #10b981; color: #fff; padding: 0 2mm; border-radius: 3px;
            font-size: 8pt; text-transform: uppercase; letter-spacing: .5px; }
        .doc-item .doc-head .badge.missing { background: #ef4444; }
        .doc-item .doc-body { text-align: center; padding: 3mm; }
        .doc-item .doc-body img { max-width: 100%; max-height: 220mm; object-fit: contain; }
        .doc-item .doc-body .placeholder { padding: 12mm; color: var(--muted); font-style: italic; }

        /* Watermark for draft */
        .watermark {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 96pt; color: rgba(200, 0, 0, 0.08); font-weight: 900; letter-spacing: 8px;
            pointer-events: none; z-index: 0; text-transform: uppercase;
        }

        /* Footer (repeating on print) */
        .print-footer { margin-top: 8mm; padding-top: 3mm; border-top: 1px solid var(--rule);
            display: flex; justify-content: space-between; font-size: 8pt; color: var(--muted);
            font-family: system-ui, sans-serif; }

        /* Print rules */
        @media print {
            html, body { background: #fff; }
            .toolbar, .no-print { display: none !important; }
            .page { margin: 0; box-shadow: none; padding: 12mm 12mm 15mm 12mm; }
            .section { page-break-inside: avoid; }
            .doc-item { page-break-inside: avoid; }
            @page { size: A4; margin: 12mm; }
        }

        @media screen and (max-width: 240mm) {
            .page { width: 100%; padding: 6mm; }
        }
    </style>
</head>
<body>

@php
    // ------------------------------------------------------------------
    // Formatting helpers scoped to this view. Keeps the markup readable
    // and gives consistent "not provided" placeholders.
    // ------------------------------------------------------------------
    $emptyMark = '<span class="empty">' . e(__('— not provided —')) . '</span>';

    $fmtValue = function ($value) use ($emptyMark) {
        if ($value === null || $value === '' || $value === false) {
            return $emptyMark;
        }
        return e((string) $value);
    };

    $fmtDate = function ($value, $format = 'd M Y') use ($emptyMark) {
        if (!$value) return $emptyMark;
        try {
            // Accept Carbon instances, strings, or SQL zero-dates.
            $ts = $value instanceof \DateTimeInterface
                ? $value->getTimestamp()
                : strtotime((string) $value);
            if (!$ts || $ts < 0) return $emptyMark;
            return e(date($format, $ts));
        } catch (\Throwable $e) {
            return $emptyMark;
        }
    };

    $genderMap = [
        1 => __('gender_male'),
        2 => __('gender_female'),
        3 => __('gender_other'),
    ];

    $schoolName    = $setting->title    ?? config('app.name');
    $schoolAddress = $setting->address  ?? null;
    $schoolPhone   = $setting->phone    ?? null;
    $schoolEmail   = $setting->email    ?? null;
    $schoolCode    = $setting->academy_code ?? null;

    $stageLabel = ucwords(str_replace('_', ' ', (string) ($row->stage ?? 'draft')));
@endphp

<div class="toolbar no-print">
    <div class="meta">
        {{ __('Application') }} <strong>{{ $row->registration_no }}</strong>
        · {{ $row->first_name }} {{ $row->last_name }}
        · {{ $stageLabel }}
    </div>
    <button class="btn-print" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    <button class="btn-close" onclick="window.close()">{{ __('Close') }}</button>
</div>

<div class="page">

    @if(($row->stage ?? null) === 'draft')
        <div class="watermark">{{ __('DRAFT') }}</div>
    @endif

    {{-- =========================================================
         Header (logo · school block · applicant photo)
         ========================================================= --}}
    <div class="doc-header">
        <div class="brand-logo">
            @if(!empty($setting->logo_path) && is_file(public_path('uploads/setting/'.$setting->logo_path)))
                <img src="{{ asset('uploads/setting/'.$setting->logo_path) }}" alt="Logo">
            @endif
        </div>
        <div class="brand-body">
            <h1 class="school-name">{{ $schoolName }}</h1>
            @if($schoolAddress)
                <div class="school-tagline">{!! nl2br(e($schoolAddress)) !!}</div>
            @endif
            @if($schoolPhone || $schoolEmail)
                <div class="school-contact">
                    @if($schoolPhone) <span>{{ __('Tel') }}: {{ $schoolPhone }}</span> @endif
                    @if($schoolPhone && $schoolEmail) &nbsp;·&nbsp; @endif
                    @if($schoolEmail) <span>{{ __('Email') }}: {{ $schoolEmail }}</span> @endif
                </div>
            @endif
            <div class="doc-title">{{ __('Application for Admission') }}</div>
        </div>
        <div class="photo-box">
            @if($row->photo && is_file(public_path('uploads/'.$path.'/'.$row->photo)))
                <img src="{{ asset('uploads/'.$path.'/'.$row->photo) }}" alt="{{ __('Applicant Photo') }}">
            @else
                <span>{{ __('Photo') }}</span>
            @endif
        </div>
    </div>

    {{-- Reference bar --}}
    <div class="ref-bar">
        <div class="cell">
            <span class="k">{{ __('Application No.') }}</span>
            <span class="v">{{ $row->registration_no ?? '—' }}</span>
        </div>
        <div class="cell">
            <span class="k">{{ __('Academic Session') }}</span>
            <span class="v">{{ optional($row->session)->title ?? ($row->academic_year ?? '—') }}</span>
        </div>
        <div class="cell">
            <span class="k">{{ __('Programme Level') }}</span>
            <span class="v">{{ optional($row->degreeType)->title ?? '—' }}</span>
        </div>
        <div class="cell">
            <span class="k">{{ __('Submitted On') }}</span>
            <span class="v">{!! $fmtDate($row->apply_date) !!}</span>
        </div>
    </div>

    {{-- =========================================================
         1. Programme Selection
         ========================================================= --}}
    <div class="section">
        <div class="section-title"><span class="num">1</span>{{ __('Programme Selection') }}</div>
        <div class="info-grid">
            <div class="cell">
                <span class="k">{{ __('First Choice Programme') }}</span>
                <span class="v">{!! $fmtValue(optional($row->program)->title) !!}</span>
            </div>
            <div class="cell">
                <span class="k">{{ __('Academic Year') }}</span>
                <span class="v">{!! $fmtValue($row->academic_year) !!}</span>
            </div>
            @if($fieldEnabled('application_program_choice_second'))
                <div class="cell">
                    <span class="k">{{ __('Second Choice Programme') }}</span>
                    <span class="v">{!! $fmtValue(optional($row->preferredProgramSecond)->title) !!}</span>
                </div>
            @endif
            @if($fieldEnabled('application_program_choice_third'))
                <div class="cell">
                    <span class="k">{{ __('Third Choice Programme') }}</span>
                    <span class="v">{!! $fmtValue(optional($row->preferredProgramThird)->title) !!}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- =========================================================
         2. Personal Information
         ========================================================= --}}
    <div class="section">
        <div class="section-title"><span class="num">2</span>{{ __('Personal Information') }}</div>
        <div class="info-grid">
            <div class="cell">
                <span class="k">{{ __('field_first_name') }}</span>
                <span class="v">{!! $fmtValue($row->first_name) !!}</span>
            </div>
            <div class="cell">
                <span class="k">{{ __('field_last_name') }}</span>
                <span class="v">{!! $fmtValue($row->last_name) !!}</span>
            </div>
            <div class="cell">
                <span class="k">{{ __('field_gender') }}</span>
                <span class="v">{!! $fmtValue($genderMap[(int) $row->gender] ?? null) !!}</span>
            </div>
            <div class="cell">
                <span class="k">{{ __('field_dob') }}</span>
                <span class="v">{!! $fmtDate($row->dob) !!}</span>
            </div>
            <div class="cell">
                <span class="k">{{ __('field_nationality') }}</span>
                <span class="v">{!! $fmtValue($row->nationality) !!}</span>
            </div>
            @if($fieldEnabled('application_religion'))
                <div class="cell">
                    <span class="k">{{ __('field_religion') }}</span>
                    <span class="v">{!! $fmtValue(optional($row->religionDetail)->title ?? $row->religion) !!}</span>
                </div>
            @endif
            @if($fieldEnabled('application_mother_tongue'))
                <div class="cell">
                    <span class="k">{{ __('field_mother_tongue') }}</span>
                    <span class="v">{!! $fmtValue($row->mother_tongue) !!}</span>
                </div>
            @endif
            @if($fieldEnabled('application_studied_in_english'))
                <div class="cell">
                    <span class="k">{{ __('Language of Prior Instruction') }}</span>
                    <span class="v">
                        @if($row->studied_in_english === null || $row->studied_in_english === '')
                            {!! $emptyMark !!}
                        @elseif((int) $row->studied_in_english === 1)
                            {{ __('English') }}
                        @else
                            {{ $row->instruction_language_secondary ?: __('Other') }}
                        @endif
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- =========================================================
         Birth details (only if any of the sub-fields are enabled)
         ========================================================= --}}
    @if($fieldEnabled('application_birth_city') || $fieldEnabled('application_birth_division') || $fieldEnabled('application_birth_region') || $fieldEnabled('application_birth_country'))
        <div class="section">
            <div class="section-title"><span class="num">3</span>{{ __('Place of Birth') }}</div>
            <div class="info-grid">
                @if($fieldEnabled('application_birth_city'))
                    <div class="cell">
                        <span class="k">{{ __('City / Town') }}</span>
                        <span class="v">{!! $fmtValue($row->birth_city) !!}</span>
                    </div>
                @endif
                @if($fieldEnabled('application_birth_division'))
                    <div class="cell">
                        <span class="k">{{ __('Division') }}</span>
                        <span class="v">{!! $fmtValue($row->birth_division) !!}</span>
                    </div>
                @endif
                @if($fieldEnabled('application_birth_region'))
                    <div class="cell">
                        <span class="k">{{ __('Region') }}</span>
                        <span class="v">{!! $fmtValue($row->birth_region) !!}</span>
                    </div>
                @endif
                @if($fieldEnabled('application_birth_country'))
                    <div class="cell">
                        <span class="k">{{ __('Country') }}</span>
                        <span class="v">{!! $fmtValue($row->birth_country) !!}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- =========================================================
         Catholic sacraments (only if declared)
         ========================================================= --}}
    @if($fieldEnabled('application_catholic_baptised'))
        <div class="section">
            <div class="section-title"><span class="num">4</span>{{ __('Catholic Sacraments Received') }}</div>
            <div class="chips">
                <div class="chip {{ $row->is_catholic_baptised ? 'yes' : 'no' }}">
                    {{ __('Baptism') }}: <strong>{{ $row->is_catholic_baptised ? __('Yes') : __('No') }}</strong>
                </div>
                <div class="chip {{ $row->is_confirmed ? 'yes' : 'no' }}">
                    {{ __('Confirmation') }}: <strong>{{ $row->is_confirmed ? __('Yes') : __('No') }}</strong>
                </div>
                <div class="chip {{ $row->has_first_communion ? 'yes' : 'no' }}">
                    {{ __('First Communion') }}: <strong>{{ $row->has_first_communion ? __('Yes') : __('No') }}</strong>
                </div>
            </div>
        </div>
    @endif

    {{-- =========================================================
         Official identification
         ========================================================= --}}
    <div class="section">
        <div class="section-title"><span class="num">5</span>{{ __('Official Identification') }}</div>
        <div class="info-grid">
            <div class="cell">
                <span class="k">{{ __('National ID Number') }}</span>
                <span class="v">{!! $fmtValue($row->national_id) !!}</span>
            </div>
            <div class="cell">
                <span class="k">{{ __('Passport Number') }}</span>
                <span class="v">{!! $fmtValue($row->passport_no) !!}</span>
            </div>
            @if($fieldEnabled('application_national_id_issue_date'))
                <div class="cell">
                    <span class="k">{{ __('National ID Issue Date') }}</span>
                    <span class="v">{!! $fmtDate($row->national_id_issue_date) !!}</span>
                </div>
            @endif
            @if($fieldEnabled('application_national_id_issue_place'))
                <div class="cell">
                    <span class="k">{{ __('National ID Issue Place') }}</span>
                    <span class="v">{!! $fmtValue($row->national_id_issue_place) !!}</span>
                </div>
            @endif
            @if($fieldEnabled('application_passport_issue_date'))
                <div class="cell">
                    <span class="k">{{ __('Passport Issue Date') }}</span>
                    <span class="v">{!! $fmtDate($row->passport_issue_date) !!}</span>
                </div>
            @endif
            @if($fieldEnabled('application_passport_issue_country'))
                <div class="cell">
                    <span class="k">{{ __('Passport Issue Country') }}</span>
                    <span class="v">{!! $fmtValue($row->passport_issue_country) !!}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- =========================================================
         Contact channels
         ========================================================= --}}
    <div class="section">
        <div class="section-title"><span class="num">6</span>{{ __('Contact Information') }}</div>
        <div class="info-grid">
            <div class="cell">
                <span class="k">{{ __('field_email') }}</span>
                <span class="v">{!! $fmtValue($row->email) !!}</span>
            </div>
            <div class="cell">
                <span class="k">{{ __('Primary Phone') }}</span>
                <span class="v">{!! $fmtValue($row->phone) !!}</span>
            </div>
            @if($fieldEnabled('application_alternate_phone'))
                <div class="cell">
                    <span class="k">{{ __('Alternate Phone') }}</span>
                    <span class="v">{!! $fmtValue($row->alternate_phone) !!}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- =========================================================
         Current residence + permanent + postal
         ========================================================= --}}
    <div class="section">
        <div class="section-title"><span class="num">7</span>{{ __('Address') }}</div>
        <div class="info-grid single">
            <div class="cell wide">
                <span class="k">{{ __('Current Residence') }}</span>
                <span class="v">
                    @php
                        $residenceParts = array_filter([
                            $row->present_address, $row->present_village, $row->present_district,
                            $row->present_province, $row->country,
                        ]);
                    @endphp
                    {!! $residenceParts ? e(implode(', ', $residenceParts)) : $emptyMark !!}
                </span>
            </div>
            <div class="cell wide">
                <span class="k">{{ __('Permanent Address') }}</span>
                <span class="v">
                    @php
                        $permanentParts = array_filter([
                            $row->permanent_address, $row->permanent_village, $row->permanent_district,
                            $row->permanent_province,
                        ]);
                    @endphp
                    {!! $permanentParts ? e(implode(', ', $permanentParts)) : $emptyMark !!}
                </span>
            </div>
            @if($fieldEnabled('application_postal_address'))
                <div class="cell wide">
                    <span class="k">{{ __('Postal Address') }}</span>
                    <span class="v">
                        @php
                            $postalParts = array_filter([$row->postal_address_line1, $row->postal_address_line2]);
                        @endphp
                        {!! $postalParts ? e(implode(', ', $postalParts)) : $emptyMark !!}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- =========================================================
         Guardians
         ========================================================= --}}
    @if($fieldEnabled('application_guardians') && $row->guardians->count() > 0)
        <div class="section">
            <div class="section-title"><span class="num">8</span>{{ __('Parent / Guardian Information') }}</div>
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 25%">{{ __('Full Name') }}</th>
                        <th style="width: 15%">{{ __('Type / Relationship') }}</th>
                        <th style="width: 15%">{{ __('Occupation') }}</th>
                        <th style="width: 20%">{{ __('Phone') }}</th>
                        <th style="width: 25%">{{ __('Email') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($row->guardians as $g)
                    <tr>
                        <td>{{ $g->full_name }}{!! $g->is_primary ? ' <em style="font-size:8pt;color:#0b3d91">('.__('primary').')</em>' : '' !!}</td>
                        <td>{{ trim(($g->type ?? '') . (($g->relationship && $g->relationship !== $g->type) ? ' — '.$g->relationship : '')) ?: '—' }}</td>
                        <td>{{ $g->occupation ?: '—' }}</td>
                        <td>
                            {{ $g->phone_primary ?: '—' }}
                            @if($g->phone_secondary)<br><small>{{ $g->phone_secondary }}</small>@endif
                        </td>
                        <td>{{ $g->email ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- =========================================================
         Academic history
         ========================================================= --}}
    @if($fieldEnabled('application_academic_history') && $row->academicHistories->count() > 0)
        <div class="section">
            <div class="section-title"><span class="num">9</span>{{ __('Academic History') }}</div>
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 26%">{{ __('Institution') }}</th>
                        <th style="width: 14%">{{ __('City / Country') }}</th>
                        <th style="width: 20%">{{ __('Period') }}</th>
                        <th style="width: 20%">{{ __('Certificate') }}</th>
                        <th style="width: 20%">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($row->academicHistories as $h)
                    <tr>
                        <td>{{ $h->institution_name }}</td>
                        <td>{{ trim(implode(', ', array_filter([$h->city, $h->country]))) ?: '—' }}</td>
                        <td>
                            @php
                                $from = $h->date_from && strtotime((string)$h->date_from) > 0 ? date('M Y', strtotime((string)$h->date_from)) : null;
                                $to   = $h->date_to   && strtotime((string)$h->date_to)   > 0 ? date('M Y', strtotime((string)$h->date_to))   : null;
                            @endphp
                            {{ $from && $to ? "$from — $to" : ($from ?: ($to ?: '—')) }}
                        </td>
                        <td>{{ $h->certificate_obtained ?: '—' }}</td>
                        <td>
                            @php
                                $details = array_filter([
                                    $h->gce_ol_detail ? 'GCE O/L: '.$h->gce_ol_detail : null,
                                    $h->gce_al_detail ? 'GCE A/L: '.$h->gce_al_detail : null,
                                    $h->probatoire_detail ? 'Probatoire: '.$h->probatoire_detail : null,
                                    $h->baccalaureate_detail ? 'Bacc.: '.$h->baccalaureate_detail : null,
                                    $h->notes,
                                ]);
                            @endphp
                            {!! $details ? nl2br(e(implode("\n", $details))) : '—' !!}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- =========================================================
         Language proficiency
         ========================================================= --}}
    @if($fieldEnabled('application_language_proficiency') && $row->languages->count() > 0)
        <div class="section">
            <div class="section-title"><span class="num">10</span>{{ __('Language Proficiency') }}</div>
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 40%">{{ __('Language') }}</th>
                        <th style="width: 30%">{{ __('Years of Study') }}</th>
                        <th style="width: 30%">{{ __('Fluency Level') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($row->languages as $l)
                    <tr>
                        <td>{{ $l->language }}</td>
                        <td>{{ $l->years_of_study !== null ? $l->years_of_study : '—' }}</td>
                        <td>{{ $l->fluency_level ? ucfirst($l->fluency_level) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- =========================================================
         Declaration + signature
         ========================================================= --}}
    <div class="section">
        <div class="section-title"><span class="num">11</span>{{ __('Applicant Declaration') }}</div>
        <div class="declaration">
            {{ __('I hereby declare that the information provided in this application is true, complete and accurate to the best of my knowledge. I understand that any false, incomplete or misleading statement may result in the rejection of this application or, if already admitted, the withdrawal of my admission and dismissal from the institution without refund of any fees paid. I authorise the institution to verify all information supplied and to contact the institutions and referees listed herein.') }}
        </div>
        <div class="signature-grid">
            <div class="signature-block">
                <div class="sig-line">
                    @if($row->signature && is_file(public_path('uploads/'.$path.'/'.$row->signature)))
                        <img src="{{ asset('uploads/'.$path.'/'.$row->signature) }}" alt="{{ __('Signature') }}">
                    @endif
                </div>
                <div class="caption">{{ $row->declaration_name ?: trim($row->first_name.' '.$row->last_name) }}</div>
                <div class="sub">{{ __('Applicant\'s Signature') }}</div>
            </div>
            <div class="signature-block">
                <div class="sig-line">&nbsp;</div>
                <div class="caption">{!! $fmtDate($row->declaration_signed_date ?? $row->apply_date) !!}</div>
                <div class="sub">{{ __('Date') }}</div>
            </div>
        </div>
    </div>

    {{-- =========================================================
         For Official Use Only
         ========================================================= --}}
    <div class="section">
        <div class="official-use">
            <div class="oh">{{ __('For Official Use Only') }}</div>
            <div class="ob">
                <div class="field"><span class="lbl">{{ __('Application received by') }}</span><span class="box"></span></div>
                <div class="field"><span class="lbl">{{ __('Date received') }}</span><span class="box"></span></div>
                <div class="field"><span class="lbl">{{ __('Documents verified by') }}</span><span class="box"></span></div>
                <div class="field"><span class="lbl">{{ __('Verification date') }}</span><span class="box"></span></div>
                <div class="field"><span class="lbl">{{ __('Admission fee status') }}</span>
                    <span class="box">
                        @if($row->admissionFee)
                            {{ (int) $row->admissionFee->status === 1 ? __('Paid') : ((int) $row->admissionFee->status === 2 ? __('Partially Paid') : __('Unpaid')) }}
                            &nbsp;·&nbsp; {{ number_format((float) $row->admissionFee->paid_amount, 0) }} / {{ number_format((float) $row->admissionFee->fee_amount, 0) }}
                            {{ $setting->currency_symbol ?? '' }}
                        @endif
                    </span>
                </div>
                <div class="field"><span class="lbl">{{ __('Decision') }}</span><span class="box"></span></div>
                <div class="field" style="grid-column: 1 / -1;">
                    <span class="lbl">{{ __('Registrar / Admissions Officer signature') }}</span>
                    <span class="box" style="min-height: 14mm;"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================
         Submitted Documents (new page)
         ========================================================= --}}
    @if($row->documents->count() > 0)
        <div class="section" style="page-break-before: always;">
            <div class="section-title"><span class="num">12</span>{{ __('Submitted Documents') }}</div>
            @foreach($row->documents as $document)
                @php
                    $docKey = $document->document_type;
                    $docTitle = $documentRequirements[$docKey]['label'] ?? ucwords(str_replace('_', ' ', (string) $docKey));
                    $fileName = $document->file_path;
                    $extension = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $isPdf = $extension === 'pdf';
                    $filePath = 'uploads/'.$path.'/'.$fileName;
                    $absPath = public_path($filePath);
                    $exists = $fileName && is_file($absPath);
                @endphp
                <div class="doc-item">
                    <div class="doc-head">
                        <span>{{ $docTitle }}</span>
                        <span class="badge {{ $exists ? '' : 'missing' }}">
                            {{ $exists ? __('Received') : __('Missing') }}
                        </span>
                    </div>
                    <div class="doc-body">
                        @if($exists && $isImage)
                            <img src="{{ asset($filePath) }}" alt="{{ $docTitle }}">
                        @elseif($exists && $isPdf)
                            <div class="placeholder">
                                <strong>{{ strtoupper($extension) }}</strong> — {{ e($fileName) }}<br>
                                <small>{{ __('Open the digital record to view the full PDF.') }}</small>
                            </div>
                        @elseif($exists)
                            <div class="placeholder">
                                <strong>{{ strtoupper($extension) }}</strong> — {{ e($fileName) }}
                            </div>
                        @else
                            <div class="placeholder">{{ __('File not attached.') }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Footer --}}
    <div class="print-footer">
        <div>
            {{ __('Application No.') }}: <strong>{{ $row->registration_no }}</strong>
            @if($schoolCode) · {{ __('Institution Code') }}: {{ $schoolCode }} @endif
        </div>
        <div>{{ __('Generated') }} {{ date('d M Y H:i') }}</div>
    </div>
</div>

</body>
</html>
