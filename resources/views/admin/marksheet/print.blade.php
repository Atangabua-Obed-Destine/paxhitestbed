<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width,maximum-scale=1.0">
    <title>{{ $title }}</title>

    <style type="text/css">
    /* ===============================================
       PAX HIGHER INSTITUTE — Official Transcript Print
       =============================================== */

    @import url('https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700;900&family=Source+Sans+Pro:wght@400;600;700&display=swap');

    @page {
      size: A4 portrait;
      margin: 0;
    }
    @page :footer { display: none; }
    @page :header { display: none; }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      margin: 0;
      padding: 0;
      background: #f5f5f5;
      font-family: 'Source Sans Pro', 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      color: #1a1a1a;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    /* --- Page container --- */
    .tp-page {
      position: relative;
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      background: #fff;
      overflow: hidden;
    }

    /* --- Letterhead background --- */
    .tp-letterhead {
      position: absolute;
      top: 0; left: 0;
      width: 100%;
      height: auto;
      z-index: 0;
      pointer-events: none;
    }

    /* --- Content overlay --- */
    .tp-content {
      position: relative;
      z-index: 1;
      padding: 190px 42px 40px 42px;
    }

    /* --- Document title --- */
    .tp-doc-title {
      text-align: center;
      margin-bottom: 16px;
      padding-bottom: 8px;
      border-bottom: 2.5px double #1a1a1a;
    }
    .tp-doc-title h2 {
      font-family: 'Merriweather', Georgia, serif;
      font-size: 16px;
      font-weight: 900;
      letter-spacing: 4px;
      text-transform: uppercase;
      color: #1a1a1a;
      margin: 0;
    }
    .tp-doc-title .tp-doc-subtitle {
      font-size: 9.5px;
      letter-spacing: 1.5px;
      color: #555;
      text-transform: uppercase;
      margin-top: 3px;
    }

    /* --- Student Information Grid --- */
    .tp-info-section {
      margin-bottom: 14px;
    }
    .tp-info-grid {
      display: table;
      width: 100%;
      border-collapse: collapse;
    }
    .tp-info-row {
      display: table-row;
    }
    .tp-info-cell {
      display: table-cell;
      padding: 3.5px 0;
      font-size: 11px;
      vertical-align: top;
    }
    .tp-info-label {
      width: 22%;
      font-weight: 700;
      color: #333;
      text-transform: uppercase;
      font-size: 9.5px;
      letter-spacing: 0.4px;
      padding-right: 4px;
    }
    .tp-info-value {
      width: 28%;
      font-weight: 600;
      color: #1a1a1a;
      padding-left: 2px;
    }
    .tp-info-value::before {
      content: ': ';
      color: #555;
    }
    .tp-info-divider {
      height: 1px;
      background: #ccc;
      margin: 6px 0;
    }

    /* --- Matricule highlight --- */
    .tp-matricule {
      font-family: 'Consolas', 'Courier New', monospace;
      font-weight: 700;
      letter-spacing: 1.2px;
      color: #1a1a1a;
    }

    /* --- Summary bar --- */
    .tp-summary-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 7px 14px;
      margin-bottom: 14px;
      border: 1.5px solid #1a1a1a;
      background: transparent;
    }
    .tp-summary-item {
      text-align: center;
      flex: 1;
    }
    .tp-summary-item + .tp-summary-item {
      border-left: 1px solid #999;
    }
    .tp-summary-label {
      display: block;
      font-size: 8px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #555;
      font-weight: 600;
    }
    .tp-summary-value {
      display: block;
      font-size: 14px;
      font-weight: 900;
      font-family: 'Merriweather', serif;
      color: #1a1a1a;
      line-height: 1.3;
    }
    .tp-summary-value.tp-gpa-big {
      font-size: 18px;
    }

    /* --- Academic Records Table --- */
    .tp-records-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 0;
      font-size: 10px;
    }
    .tp-records-table thead th {
      background: transparent;
      border-top: 2.5px solid #1a1a1a;
      border-bottom: 2.5px double #1a1a1a;
      padding: 5px 4px;
      font-size: 8.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #1a1a1a;
      text-align: center;
      vertical-align: bottom;
    }
    .tp-records-table thead th.tp-col-left {
      text-align: left;
    }

    /* Semester header row */
    .tp-sem-header td {
      padding: 8px 4px 4px;
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #1a1a1a;
      border-bottom: 1px solid #999;
      text-align: left;
      background: transparent;
    }

    /* Per-semester column headers */
    .tp-col-headers th {
      padding: 3px 4px;
      font-size: 8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #444;
      border-bottom: 1px solid #bbb;
      text-align: center;
      background: #f8f8f8;
    }
    .tp-col-headers th.tp-col-left {
      text-align: left;
    }

    /* Course rows */
    .tp-records-table tbody td {
      padding: 3px 4px;
      border-bottom: 0.5px solid #ddd;
      text-align: center;
      font-size: 10px;
      color: #1a1a1a;
      vertical-align: middle;
      background: transparent;
    }
    .tp-records-table tbody td.tp-td-left {
      text-align: left;
    }
    .tp-records-table tbody td.tp-td-code {
      text-align: left;
      font-family: 'Consolas', 'Courier New', monospace;
      font-weight: 600;
      letter-spacing: 0.3px;
    }
    .tp-records-table tbody td.tp-td-title {
      text-align: left;
      font-weight: 500;
    }
    .tp-records-table tbody td.tp-td-type {
      font-size: 9px;
      font-weight: 600;
    }
    .tp-records-table tbody td.tp-td-grade {
      font-weight: 800;
    }

    /* Semester subtotal rows */
    .tp-sem-subtotal td {
      padding: 4px 4px;
      border-top: 1px solid #999;
      border-bottom: none;
      font-weight: 700;
      font-size: 9.5px;
      color: #1a1a1a;
      background: transparent;
    }
    .tp-sem-gpa td {
      padding: 2px 4px 6px;
      border-bottom: 2px solid #1a1a1a;
      font-weight: 700;
      font-size: 9.5px;
      color: #1a1a1a;
      background: transparent;
    }
    .tp-sem-gpa-val {
      font-family: 'Merriweather', serif;
      font-weight: 900;
      font-size: 11px;
    }

    /* Column widths */
    .tp-col-code { width: 9%; }
    .tp-col-title { width: 26%; }
    .tp-col-type { width: 6%; }
    .tp-col-cv { width: 8%; }
    .tp-col-att { width: 9%; }
    .tp-col-ern { width: 9%; }
    .tp-col-gpt { width: 9%; }
    .tp-col-grd { width: 7%; }
    .tp-col-qpt { width: 9%; }

    /* --- Grading Scale --- */
    .tp-grade-scale {
      margin-top: 16px;
      page-break-inside: avoid;
    }
    .tp-grade-scale-title {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #1a1a1a;
      margin-bottom: 5px;
      border-bottom: 1px solid #999;
      padding-bottom: 3px;
    }
    .tp-grade-scale-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 0;
    }
    .tp-grade-scale-item {
      flex: 0 0 20%;
      display: flex;
      align-items: baseline;
      gap: 4px;
      padding: 2px 0;
      font-size: 9px;
    }
    .tp-grade-scale-item .tp-gs-grade {
      font-weight: 800;
      min-width: 14px;
    }
    .tp-grade-scale-item .tp-gs-range {
      color: #555;
    }
    .tp-grade-scale-item .tp-gs-point {
      font-weight: 700;
    }

    /* --- Footer / Signatures --- */
    .tp-footer-section {
      margin-top: 24px;
      page-break-inside: avoid;
    }
    .tp-signatures {
      display: flex;
      justify-content: space-between;
      margin-top: 50px;
    }
    .tp-sig-block {
      text-align: center;
      width: 30%;
    }
    .tp-sig-line {
      border-top: 1.5px solid #1a1a1a;
      margin-bottom: 4px;
      width: 100%;
    }
    .tp-sig-label {
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #333;
    }
    .tp-sig-sublabel {
      font-size: 8px;
      color: #666;
      font-style: italic;
    }

    /* --- End-of-document marker --- */
    .tp-end-marker {
      text-align: center;
      margin-top: 18px;
      font-size: 8px;
      color: #999;
      letter-spacing: 3px;
      text-transform: uppercase;
    }
    .tp-end-marker::before,
    .tp-end-marker::after {
      content: '———';
      margin: 0 6px;
    }

    /* --- Print overrides --- */
    @media print {
      body { background: #fff; margin: 0; padding: 0; }
      .tp-page {
        width: 100%;
        margin: 0;
        box-shadow: none;
        page-break-after: always;
      }
      .tp-content { padding-top: 190px; }
      .tp-letterhead { display: block; }
      .tp-summary-bar { border-color: #1a1a1a !important; }
      .tp-records-table thead th { border-color: #1a1a1a !important; }
      .tp-sem-header td { border-color: #999 !important; }
      .tp-col-headers th { border-color: #bbb !important; background: #f5f5f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .tp-sem-gpa td { border-color: #1a1a1a !important; }
      .tp-sig-line { border-color: #1a1a1a !important; }
    }
    @media screen {
      .tp-page { box-shadow: 0 2px 20px rgba(0,0,0,0.12); margin: 20px auto; }
    }
    </style>
</head>

@php
    $version = App\Models\Language::version();
@endphp
@if($version->direction == 1)
<style type="text/css">
.tp-page { direction: rtl; }
.tp-info-label { text-align: right; }
.tp-info-value { text-align: right; }
.tp-info-value::before { content: ' :'; }
.tp-records-table thead th.tp-col-left,
.tp-col-headers th.tp-col-left,
.tp-records-table tbody td.tp-td-left,
.tp-records-table tbody td.tp-td-code,
.tp-records-table tbody td.tp-td-title { text-align: right; }
.tp-sem-header td { text-align: right; }
.tp-col-headers th { text-align: right; }
</style>
@endif

<body>

@php
    // Resolve enrollment
    if (!isset($currentEnroll)) {
        $currentEnroll = \App\Models\StudentEnroll::where('student_id', $row->id)
            ->with('program')
            ->orderBy('id', 'desc')
            ->first();
    }
    if (!isset($selectedProgramId)) {
        $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : $row->program_id;
    }

    // Pre-compute CGPA
    $total_credits = 0;
    $total_cgpa = 0;
    $total_credits_earned = 0;
    $total_courses = 0;
    $starting_year = '';
    $ending_year = '';
    $unique_courses = [];

    foreach ($row->studentEnrolls as $item) {
        if ($item->program_id != $selectedProgramId || $item->matricule != $currentEnroll->matricule) continue;

        if (!$starting_year && $item->session) $starting_year = $item->session->title;
        if ($item->session) $ending_year = $item->session->title;

        if (isset($item->subjectMarks)) {
            foreach ($item->subjectMarks as $mark) {
                if ($mark->is_visible_to_student) {
                    $marks_per = round($mark->total_marks);
                    $credit = $mark->subject->credit_hour;

                    foreach ($grades as $grade) {
                        if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                            $total_cgpa += ($grade->point * $credit);
                            $total_credits += $credit;
                            if ($grade->point > 0) $total_credits_earned += $credit;
                            if (!isset($unique_courses[$mark->subject_id])) {
                                $unique_courses[$mark->subject_id] = true;
                                $total_courses++;
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    $safe_credits = $total_credits > 0 ? $total_credits : 1;
    $com_gpa = $total_cgpa / $safe_credits;

    // Academic standing
    if ($com_gpa >= 3.6) $standing = 'First Class (Distinction)';
    elseif ($com_gpa >= 3.0) $standing = 'Second Class Upper';
    elseif ($com_gpa >= 2.5) $standing = 'Second Class Lower';
    elseif ($com_gpa >= 2.0) $standing = 'Third Class';
    elseif ($com_gpa >= 1.0) $standing = 'Pass';
    elseif ($total_courses > 0) $standing = 'Fail';
    else $standing = 'N/A';

    // Semester items
    $semester_items = [];
    $semester_keys = [];
    foreach ($row->studentEnrolls as $enroll) {
        if (isset($enroll->session) && isset($enroll->semester) && isset($enroll->section) && $enroll->program_id == $selectedProgramId && $enroll->matricule == $currentEnroll->matricule) {
            $key = $enroll->session->title . '|' . $enroll->semester->title;
            if (!in_array($key, $semester_keys)) {
                $semester_items[] = [$enroll->session->title, $enroll->semester->title, $enroll->section->title];
                $semester_keys[] = $key;
            }
        }
    }
@endphp

<div class="tp-page printable">
    {{-- Letterhead background --}}
    <img src="{{ url('uploads/letterhead/paxletterhead.jpg') }}" class="tp-letterhead" alt="">

    <div class="tp-content">

        {{-- Document Title --}}
        <div class="tp-doc-title">
            <h2>Academic Transcript</h2>
            <div class="tp-doc-subtitle">Official Record of Academic Achievement</div>
        </div>

        {{-- Student Information --}}
        <div class="tp-info-section">
            <div class="tp-info-grid">
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_matricule') }}</div>
                    <div class="tp-info-cell tp-info-value"><span class="tp-matricule">{{ $currentEnroll->matricule ?? $row->student_id }}</span></div>
                    <div class="tp-info-cell tp-info-label">{{ __('field_program') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ $currentEnroll->program->title ?? $row->program->title ?? 'N/A' }}</div>
                </div>
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_name') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ strtoupper($row->first_name . ' ' . $row->last_name) }}</div>
                    <div class="tp-info-cell tp-info-label">{{ __('field_batch') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ $row->batch->title ?? 'N/A' }}</div>
                </div>
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_gender') }}</div>
                    <div class="tp-info-cell tp-info-value">
                        @if($row->gender == 1) {{ __('gender_male') }}
                        @elseif($row->gender == 2) {{ __('gender_female') }}
                        @elseif($row->gender == 3) {{ __('gender_other') }}
                        @endif
                    </div>
                    <div class="tp-info-cell tp-info-label">{{ __('field_starting_year') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ $starting_year }}</div>
                </div>
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_dob') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ date($setting->date_format ?? 'd-m-Y', strtotime($row->dob)) }}</div>
                    <div class="tp-info-cell tp-info-label">{{ __('field_ending_year') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ $ending_year }}</div>
                </div>
                @if($row->nationality)
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">Nationality</div>
                    <div class="tp-info-cell tp-info-value">{{ $row->nationality }}</div>
                    <div class="tp-info-cell tp-info-label">Date Issued</div>
                    <div class="tp-info-cell tp-info-value">{{ date('F d, Y') }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Summary bar --}}
        <div class="tp-summary-bar">
            <div class="tp-summary-item">
                <span class="tp-summary-label">Cumulative GPA</span>
                <span class="tp-summary-value tp-gpa-big">{{ number_format((float)$com_gpa, 2, '.', '') }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Total Credits</span>
                <span class="tp-summary-value">{{ number_format((float)$total_credits, 1) }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Credits Earned</span>
                <span class="tp-summary-value">{{ number_format((float)$total_credits_earned, 1) }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Courses</span>
                <span class="tp-summary-value">{{ $total_courses }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Standing</span>
                <span class="tp-summary-value" style="font-size:10px;">{{ $standing }}</span>
            </div>
        </div>

        {{-- Academic Records Table --}}
        <table class="tp-records-table">
            <thead>
                <tr>
                    <th class="tp-col-code tp-col-left">Code</th>
                    <th class="tp-col-title tp-col-left">Course Title</th>
                    <th class="tp-col-type">Type</th>
                    <th class="tp-col-cv">Credit</th>
                    <th class="tp-col-att">Attempted</th>
                    <th class="tp-col-ern">Earned</th>
                    <th class="tp-col-gpt">Grade Pt</th>
                    <th class="tp-col-grd">Grade</th>
                    <th class="tp-col-qpt">Quality Pts</th>
                </tr>
            </thead>

            @foreach($semester_items as $semIdx => $semester_item)
            <tbody>
                {{-- Semester Header --}}
                <tr class="tp-sem-header">
                    <td colspan="9">{{ $semester_item[1] }} &mdash; {{ $semester_item[0] }} (Section: {{ $semester_item[2] }})</td>
                </tr>
                {{-- Column Headers --}}
                <tr class="tp-col-headers">
                    <th class="tp-col-code tp-col-left">Code</th>
                    <th class="tp-col-title tp-col-left">Course Title</th>
                    <th class="tp-col-type">Type</th>
                    <th class="tp-col-cv">Credit</th>
                    <th class="tp-col-att">Attempted</th>
                    <th class="tp-col-ern">Earned</th>
                    <th class="tp-col-gpt">Grade Pt</th>
                    <th class="tp-col-grd">Grade</th>
                    <th class="tp-col-qpt">Quality Pts</th>
                </tr>

                @php
                    $sem_credits = 0;
                    $sem_qp = 0;
                    $sem_earned = 0;
                @endphp

                @foreach($row->studentEnrolls as $item)
                @if(isset($item->semester) && isset($item->session) && $semester_item[1] == $item->semester->title && $semester_item[0] == $item->session->title && $item->program_id == $selectedProgramId && $item->matricule == $currentEnroll->matricule)
                @foreach($item->subjects as $subject)
                @php
                    $creditsAttempted = (float) $subject->credit_hour;
                    $sem_credits += $creditsAttempted;
                    $subject_grade = null;
                    $subjectGradePoint = null;
                    $subjectQualityPoints = null;
                    $creditsEarned = 0;

                    $typeLabel = $subject->subject_type == 1 ? 'C' : ($subject->subject_type == 2 ? 'UR' : 'E');

                    if (isset($item->subjectMarks)) {
                        foreach ($item->subjectMarks as $mark) {
                            if ($mark->subject_id == $subject->id) {
                                if ($mark->is_visible_to_student) {
                                    $marks_per = round($mark->total_marks);
                                    foreach ($grades as $grade) {
                                        if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                            $subjectGradePoint = (float) $grade->point;
                                            $subjectQualityPoints = $subjectGradePoint * $creditsAttempted;
                                            $sem_qp += $subjectQualityPoints;
                                            if ($subjectGradePoint > 0) {
                                                $sem_earned += $creditsAttempted;
                                                $creditsEarned = $creditsAttempted;
                                            }
                                            $subject_grade = $grade->title;
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                @endphp
                <tr>
                    <td class="tp-td-code">{{ $subject->code }}</td>
                    <td class="tp-td-title">{{ $subject->title }}</td>
                    <td class="tp-td-type">{{ $typeLabel }}</td>
                    <td>{{ number_format($creditsAttempted, 1) }}</td>
                    <td>{{ number_format($creditsAttempted, 1) }}</td>
                    <td>{{ !is_null($subjectGradePoint) ? number_format($creditsEarned, 1) : '—' }}</td>
                    <td>{{ !is_null($subjectGradePoint) ? number_format($subjectGradePoint, 2) : '—' }}</td>
                    <td class="tp-td-grade">{{ $subject_grade ?? '—' }}</td>
                    <td>{{ !is_null($subjectQualityPoints) ? number_format($subjectQualityPoints, 2) : '—' }}</td>
                </tr>
                @endforeach
                @endif
                @endforeach

                @php
                    $semGpa = $sem_credits > 0 ? $sem_qp / $sem_credits : 0;
                @endphp

                {{-- Semester subtotal --}}
                <tr class="tp-sem-subtotal">
                    <td class="tp-td-left" colspan="3"><strong>Semester Totals</strong></td>
                    <td><strong>{{ number_format($sem_credits, 1) }}</strong></td>
                    <td><strong>{{ number_format($sem_credits, 1) }}</strong></td>
                    <td><strong>{{ number_format($sem_earned, 1) }}</strong></td>
                    <td colspan="2"></td>
                    <td><strong>{{ number_format($sem_qp, 2) }}</strong></td>
                </tr>
                <tr class="tp-sem-gpa">
                    <td class="tp-td-left" colspan="4">Semester GPA: <span class="tp-sem-gpa-val">{{ number_format($semGpa, 2) }}</span></td>
                    <td colspan="5" style="text-align:right; font-size:8.5px; color:#555;">Credits Earned: {{ number_format($sem_earned, 1) }} / {{ number_format($sem_credits, 1) }}</td>
                </tr>
            </tbody>
            @endforeach
        </table>

        {{-- Grading Scale --}}
        <div class="tp-grade-scale">
            <div class="tp-grade-scale-title">Grading Scale</div>
            <div class="tp-grade-scale-grid">
                @foreach($grades as $grade)
                <div class="tp-grade-scale-item">
                    <span class="tp-gs-grade">{{ $grade->title }}</span>
                    <span class="tp-gs-point">({{ number_format($grade->point, 1) }})</span>
                    <span class="tp-gs-range">{{ number_format($grade->min_mark, 0) }}-{{ number_format($grade->max_mark, 0) }}%</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Signatures --}}
        <div class="tp-footer-section">
            <div class="tp-signatures">
                <div class="tp-sig-block">
                    <div class="tp-sig-line"></div>
                    <div class="tp-sig-label">{!! $marksheet->footer_left !!}</div>
                </div>
                <div class="tp-sig-block">
                    <div class="tp-sig-line"></div>
                    <div class="tp-sig-label">{!! $marksheet->footer_center !!}</div>
                </div>
                <div class="tp-sig-block">
                    <div class="tp-sig-line"></div>
                    <div class="tp-sig-label">{!! $marksheet->footer_right !!}</div>
                </div>
            </div>
        </div>

        {{-- End marker --}}
        <div class="tp-end-marker">End of Transcript</div>

    </div>{{-- end .tp-content --}}
</div>{{-- end .tp-page --}}

<!-- Print Js -->
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>
<script src="{{ asset('dashboard/plugins/print/js/jQuery.print.min.js') }}"></script>

<script type="text/javascript">
$(document).ready(function() {
    "use strict";
    $.print(".printable");
});
</script>

</body>
</html>