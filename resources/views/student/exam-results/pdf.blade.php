<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Exam Results') }} — {{ $student->first_name }} {{ $student->last_name }}</title>
    <style>
        /* ─── Base Reset & Typography ─── */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1a202c;
            line-height: 1.5;
            background: #fff;
        }

        /* ─── Page Layout ─── */
        .er-page {
            width: 100%;
            padding: 15px 30px 20px;
        }

        /* ─── Institution Header ─── */
        .er-header {
            text-align: center;
            border-bottom: 3px double #4a5568;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .er-header .er-logo {
            width: 60px;
            height: 60px;
            margin-bottom: 5px;
        }
        .er-header h1 {
            font-size: 16px;
            color: #2d3748;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 2px;
        }
        .er-header .er-address {
            font-size: 9px;
            color: #718096;
            margin-bottom: 2px;
        }
        .er-header .er-doc-title {
            font-size: 13px;
            font-weight: bold;
            color: #fff;
            background: linear-gradient(90deg, #667eea, #764ba2);
            display: inline-block;
            padding: 4px 25px;
            border-radius: 3px;
            margin-top: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ─── Student Info Grid ─── */
        .er-student-info {
            width: 100%;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .er-student-info td {
            padding: 5px 10px;
            font-size: 9.5px;
            border-bottom: 1px solid #edf2f7;
        }
        .er-student-info .er-label {
            font-weight: bold;
            color: #4a5568;
            width: 18%;
            background: #f7fafc;
        }
        .er-student-info .er-value {
            color: #1a202c;
            width: 32%;
        }

        /* ─── Exam Info Banner ─── */
        .er-exam-banner {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .er-exam-banner td {
            background: #f0f4ff;
            border: 1px solid #d5daff;
            padding: 6px 10px;
            text-align: center;
            font-size: 9.5px;
        }
        .er-exam-banner .er-banner-label {
            font-weight: bold;
            color: #4a5568;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .er-exam-banner .er-banner-value {
            font-weight: bold;
            color: #2d3748;
            font-size: 11px;
            display: block;
            margin-top: 1px;
        }

        /* ─── Results Table ─── */
        .er-results {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9.5px;
        }
        .er-results thead th {
            background: #2d3748;
            color: #fff;
            padding: 7px 5px;
            text-align: center;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #2d3748;
        }
        .er-results thead th:nth-child(2),
        .er-results thead th:nth-child(3) {
            text-align: left;
        }
        .er-results tbody td {
            padding: 6px 5px;
            border: 1px solid #e2e8f0;
            text-align: center;
            vertical-align: middle;
        }
        .er-results tbody td:nth-child(2),
        .er-results tbody td:nth-child(3) {
            text-align: left;
        }
        .er-results tbody tr:nth-child(even) {
            background: #f7fafc;
        }
        .er-results tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        /* Mark columns background tinting */
        .er-att { background-color: #eef6ff !important; }
        .er-ca { background-color: #fffcf5 !important; }
        .er-ex { background-color: #f0f0ff !important; }
        .er-tot-pass { font-weight: bold; color: #27ae60 !important; background-color: #f0fff4 !important; }
        .er-tot-fail { font-weight: bold; color: #e53e3e !important; background-color: #fff5f5 !important; }
        .er-pass-badge { color: #27ae60; font-weight: bold; font-size: 9px; }
        .er-fail-badge { color: #e53e3e; font-weight: bold; font-size: 9px; }

        /* Summary footer row */
        .er-results tfoot td {
            padding: 7px 5px;
            border: 1px solid #d5daff;
            background: #f0f4ff;
            font-weight: bold;
            font-size: 9.5px;
            text-align: center;
        }

        /* ─── Summary Cards ─── */
        .er-summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .er-summary td {
            width: 25%;
            text-align: center;
            padding: 8px 5px;
            border: 1px solid #e2e8f0;
        }
        .er-summary .er-sum-label {
            font-size: 8px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .er-summary .er-sum-value {
            font-size: 15px;
            font-weight: bold;
            display: block;
            margin-top: 2px;
        }
        .er-sum-courses .er-sum-value { color: #667eea; }
        .er-sum-credits .er-sum-value { color: #138496; }
        .er-sum-earned .er-sum-value { color: #27ae60; }
        .er-sum-gpa .er-sum-value { color: #764ba2; }

        /* ─── Grading Scale Table ─── */
        .er-grading {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 10px;
        }
        .er-grading caption {
            font-weight: bold;
            font-size: 9px;
            text-align: left;
            padding-bottom: 4px;
            color: #4a5568;
        }
        .er-grading th {
            background: #edf2f7;
            color: #4a5568;
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        .er-grading td {
            padding: 3px 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        /* ─── Legend & Abbreviations ─── */
        .er-legend {
            font-size: 8px;
            color: #718096;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            margin-bottom: 8px;
        }
        .er-legend strong { color: #4a5568; }

        /* ─── Transcript Notice ─── */
        .er-transcript-notice {
            border: 1.5px solid #d5daff;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            padding: 10px 14px;
            background: #f8f9ff;
            margin-bottom: 12px;
        }
        .er-transcript-notice .er-notice-title {
            font-weight: bold;
            font-size: 9.5px;
            color: #4a5568;
            margin-bottom: 3px;
        }
        .er-transcript-notice .er-notice-text {
            font-size: 8.5px;
            color: #718096;
            line-height: 1.6;
        }
        .er-transcript-notice .er-notice-url {
            color: #667eea;
            font-weight: bold;
        }

        /* ─── Footer ─── */
        .er-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 7.5px;
            color: #a0aec0;
            text-align: center;
        }
        .er-footer .er-disclaimer {
            font-size: 7.5px;
            color: #a0aec0;
            font-style: italic;
            margin-bottom: 4px;
        }

        /* ─── Print optimisation ─── */
        @page { margin: 15mm 10mm; }
    </style>
</head>
<body>
    <div class="er-page">
        {{-- The configured letterhead, exactly as authored, in place of a
             masthead this document used to assemble from Settings. --}}
        @include('partials.document-header', ['forPdf' => true, 'rule' => true])

        <div class="er-header">
            <div class="er-doc-title">{{ __('Semester Examination Results') }}</div>
        </div>

        {{-- ══════════════════ STUDENT INFORMATION ══════════════════ --}}
        <table class="er-student-info" cellspacing="0" cellpadding="0">
            <tr>
                <td class="er-label">{{ __('Student Name') }}</td>
                <td class="er-value">{{ $student->first_name }} {{ $student->last_name }}</td>
                <td class="er-label">{{ __('Matricule') }}</td>
                <td class="er-value">{{ optional($enrollment)->matricule ?? $student->student_id ?? '—' }}</td>
            </tr>
            <tr>
                <td class="er-label">{{ __('Programme') }}</td>
                <td class="er-value">{{ optional(optional($enrollment)->program)->title ?? '—' }}</td>
                <td class="er-label">{{ __('Registration No.') }}</td>
                <td class="er-value">{{ $student->registration_no ?? '—' }}</td>
            </tr>
            <tr>
                <td class="er-label">{{ __('Gender') }}</td>
                <td class="er-value">{{ $student->gender ? ucfirst($student->gender) : '—' }}</td>
                <td class="er-label">{{ __('Date of Birth') }}</td>
                <td class="er-value">{{ $student->dob ? \Carbon\Carbon::parse($student->dob)->format('d M Y') : '—' }}</td>
            </tr>
        </table>

        {{-- ══════════════════ EXAM INFO BANNER ══════════════════ --}}
        <table class="er-exam-banner" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <span class="er-banner-label">{{ __('Academic Session') }}</span>
                    <span class="er-banner-value">{{ optional($session)->title ?? '—' }}</span>
                </td>
                <td>
                    <span class="er-banner-label">{{ __('Semester') }}</span>
                    <span class="er-banner-value">{{ optional($semester)->title ?? '—' }}</span>
                </td>
                <td>
                    <span class="er-banner-label">{{ __('Exam Type') }}</span>
                    <span class="er-banner-value">{{ optional($examType)->title ?? '—' }}</span>
                </td>
                <td>
                    <span class="er-banner-label">{{ __('Date Generated') }}</span>
                    <span class="er-banner-value">{{ $generatedAt->format('d M Y, H:i') }}</span>
                </td>
            </tr>
        </table>

        {{-- ══════════════════ SUMMARY CARDS ══════════════════ --}}
        <table class="er-summary" cellspacing="0" cellpadding="0">
            <tr>
                <td class="er-sum-courses">
                    <span class="er-sum-label">{{ __('Courses Taken') }}</span>
                    <span class="er-sum-value">{{ count($coursesData) }}</span>
                </td>
                <td class="er-sum-credits">
                    <span class="er-sum-label">{{ __('Credits Registered') }}</span>
                    <span class="er-sum-value">{{ number_format($totalCreditsRegistered, 0) }}</span>
                </td>
                <td class="er-sum-earned">
                    <span class="er-sum-label">{{ __('Credits Earned') }}</span>
                    <span class="er-sum-value">{{ number_format($totalCreditsEarned, 0) }}</span>
                </td>
                <td class="er-sum-gpa">
                    <span class="er-sum-label">{{ __('Semester GPA') }}</span>
                    <span class="er-sum-value">{{ number_format($gpa, 2) }}</span>
                </td>
            </tr>
        </table>

        {{-- ══════════════════ RESULTS TABLE ══════════════════ --}}
        @if(count($coursesData) > 0)
            <table class="er-results" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th style="width: 4%;">#</th>
                        <th style="width: 11%;">{{ __('Code') }}</th>
                        <th style="width: 26%;">{{ __('Course Title') }}</th>
                        <th style="width: 5%;">CV</th>
                        <th style="width: 7%;">ATT</th>
                        <th style="width: 8%;">CA</th>
                        <th style="width: 8%;">EX</th>
                        <th style="width: 8%;">TOT</th>
                        <th style="width: 6%;">GD</th>
                        <th style="width: 6%;">GP</th>
                        <th style="width: 11%;">{{ __('Remark') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($coursesData as $index => $course)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td style="text-align: left; font-weight: bold;">{{ $course['subject']->code ?? '—' }}</td>
                            <td style="text-align: left;">{{ $course['subject']->title ?? '—' }}</td>
                            <td>{{ number_format($course['credit_hour'], 0) }}</td>
                            <td class="er-att">
                                @if($course['ca_published'] ?? true)
                                    {{ number_format($course['attendance_mark'], 1) }}
                                @else
                                    <span style="color: #999;">N/P</span>
                                @endif
                            </td>
                            <td class="er-ca">
                                @if($course['ca_published'] ?? true)
                                    {{ number_format($course['ca_marks'], 1) }}
                                @else
                                    <span style="color: #999;">N/P</span>
                                @endif
                            </td>
                            <td class="er-ex">
                                @if($course['final_published'] ?? true)
                                    {{ number_format($course['exam_marks'], 1) }}
                                @else
                                    <span style="color: #999;">N/P</span>
                                @endif
                            </td>
                            <td class="{{ ($course['all_published'] ?? true) ? ($course['is_passed'] ? 'er-tot-pass' : 'er-tot-fail') : '' }}">
                                {{ number_format($course['total_marks'], 1) }}
                            </td>
                            <td style="font-weight: bold;">{{ $course['letter_grade'] }}</td>
                            <td>
                                @if($course['all_published'] ?? true)
                                    {{ number_format($course['grade_point'], 2) }}
                                @else
                                    <span style="color: #999;">--</span>
                                @endif
                            </td>
                            <td>
                                @if($course['all_published'] ?? true)
                                    @if($course['is_passed'])
                                        <span class="er-pass-badge">&#10003; Pass</span>
                                    @else
                                        <span class="er-fail-badge">&#10007; Fail</span>
                                    @endif
                                @else
                                    <span style="color: #999; font-size: 9px;">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;">{{ __('SEMESTER TOTALS') }}</td>
                        <td>{{ number_format($totalCreditsRegistered, 0) }}</td>
                        <td colspan="3"></td>
                        <td style="font-size: 10px;">
                            {{ number_format(count($coursesData) > 0 ? collect($coursesData)->avg('total_marks') : 0, 1) }}
                        </td>
                        <td></td>
                        <td style="font-size: 10px;">{{ number_format($gpa, 2) }}</td>
                        <td>
                            @php
                                $passed = collect($coursesData)->where('is_passed', true)->count();
                                $total = count($coursesData);
                            @endphp
                            {{ $passed }}/{{ $total }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            {{-- ══════════════════ ABBREVIATION LEGEND ══════════════════ --}}
            <div class="er-legend">
                <strong>CV</strong> = Credit Value &bull;
                <strong>ATT</strong> = Attendance &bull;
                <strong>CA</strong> = Continuous Assessment (Assignments + Activities + CA Exams) &bull;
                <strong>EX</strong> = Final Exam &bull;
                <strong>TOT</strong> = ATT + CA + EX &bull;
                <strong>GD</strong> = Grade &bull;
                <strong>GP</strong> = Grade Point &bull;
                Pass mark: <strong>50/100</strong>
            </div>

            {{-- ══════════════════ GRADING SCALE ══════════════════ --}}
            @if($grades->count())
                <table class="er-grading" cellspacing="0" cellpadding="0">
                    <caption>{{ __('Grading Scale') }}</caption>
                    <thead>
                        <tr>
                            <th>{{ __('Grade') }}</th>
                            @foreach($grades as $grade)
                                <td style="font-weight: bold; background: #edf2f7;">{{ $grade->title }}</td>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>{{ __('Mark Range') }}</th>
                            @foreach($grades as $grade)
                                <td>{{ number_format($grade->min_mark, 0) }}–{{ number_format($grade->max_mark, 0) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th>{{ __('Grade Point') }}</th>
                            @foreach($grades as $grade)
                                <td>{{ number_format($grade->point, 2) }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            @endif
        @else
            <div style="text-align: center; padding: 30px; color: #a0aec0;">
                <p style="font-size: 12px;">{{ __('No results available for the selected exam type.') }}</p>
            </div>
        @endif

        {{-- ══════════════════ TRANSCRIPT NOTICE ══════════════════ --}}
        <div class="er-transcript-notice">
            <div class="er-notice-title">&#128218; {{ __('Need your full academic record?') }}</div>
            <div class="er-notice-text">
                {{ __('This document shows results for a single semester and exam type only. For your complete academic transcript — including cumulative GPA, all semesters, overall academic standing, and official grade records — please visit your') }}
                <span class="er-notice-url">{{ __('Student Portal') }} &raquo; {{ __('Transcript') }}</span>
                {{ __('page. The transcript provides the comprehensive academic record recognized by the institution.') }}
            </div>
        </div>

        {{-- ══════════════════ FOOTER ══════════════════ --}}
        <div class="er-footer">
            <div class="er-disclaimer">
                {{ __('This is a computer-generated document downloaded from the student portal. It is not an official certified result slip. For official documents, please contact the Registrar\'s Office.') }}
            </div>
            <div>
                {{ institution_name() }} &bull;
                {{ __('Generated on') }}: {{ $generatedAt->format('d M Y \a\t H:i') }} &bull;
                {{ __('Student Portal') }}
            </div>
        </div>
    </div>
</body>
</html>
