@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content - Professional Draft Transcript -->
<div class="main-body">
    <div class="page-wrapper">

        {{-- ===== DRAFT TRANSCRIPT DOCUMENT ===== --}}
        <div class="transcript-document" id="transcriptDocument">

            {{-- DIAGONAL WATERMARK (covers entire transcript) --}}
            <div class="transcript-watermark-overlay" aria-hidden="true">
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
                <span>DRAFT</span><span>DRAFT</span><span>DRAFT</span>
            </div>

            {{-- INSTITUTION HEADER --}}
            <div class="transcript-header">
                <div class="transcript-header-inner">
                    <div class="transcript-logo-area">
                        @if(isset($setting) && upload_exists('setting/'.$setting->logo_path))
                            <img src="{{ upload_asset('setting/'.$setting->logo_path) }}" alt="Institution Logo" class="transcript-logo">
                        @else
                            <div class="transcript-logo-placeholder">
                                <i class="fas fa-university"></i>
                            </div>
                        @endif
                    </div>
                    <div class="transcript-institution-info">
                        <h1 class="transcript-institution-name">{{ $setting->title ?? 'PAXHI UNIVERSITY INSTITUTE' }}</h1>
                        @if(isset($setting->address))
                        <p class="transcript-institution-address">{{ $setting->address }}</p>
                        @endif
                        @if(isset($setting->phone) || isset($setting->email))
                        <p class="transcript-institution-contact">
                            @if(isset($setting->phone))Tel: {{ $setting->phone }}@endif
                            @if(isset($setting->phone) && isset($setting->email)) &bull; @endif
                            @if(isset($setting->email))Email: {{ $setting->email }}@endif
                        </p>
                        @endif
                    </div>
                </div>
                <div class="transcript-title-bar">
                    <div class="transcript-title-bar-inner">
                        <h2 class="transcript-document-title">UNOFFICIAL ACADEMIC TRANSCRIPT</h2>
                        <div class="transcript-draft-badge">
                            <i class="fas fa-exclamation-triangle"></i> DRAFT &mdash; NOT FOR OFFICIAL USE
                        </div>
                    </div>
                </div>
            </div>

            {{-- STUDENT INFORMATION PANEL --}}
            @php
                $selectedEnrollmentId = session('selected_enrollment_id');
                $currentEnroll = \App\Models\StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $row->id)
                            ->with('program')
                            ->first();
                $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : $row->program_id;
            @endphp

            <div class="transcript-student-panel">
                <div class="transcript-student-grid">
                    <div class="transcript-student-field">
                        <span class="transcript-field-label">Student Name</span>
                        <span class="transcript-field-value">{{ strtoupper($row->first_name . ' ' . $row->last_name) }}</span>
                    </div>
                    @if($currentEnroll)
                    <div class="transcript-student-field">
                        <span class="transcript-field-label">{{ __('field_matricule') }}</span>
                        <span class="transcript-field-value transcript-field-mono">{{ $currentEnroll->matricule }}</span>
                    </div>
                    @endif
                    <div class="transcript-student-field">
                        <span class="transcript-field-label">{{ __('field_program') }}</span>
                        <span class="transcript-field-value">{{ $currentEnroll->program->title ?? $row->program->title ?? '' }}</span>
                    </div>
                    <div class="transcript-student-field">
                        <span class="transcript-field-label">{{ __('field_batch') }}</span>
                        <span class="transcript-field-value">{{ $row->batch->title ?? '' }}</span>
                    </div>
                    @if($currentEnroll && $currentEnroll->program)
                    <div class="transcript-student-field">
                        <span class="transcript-field-label">Academic Level</span>
                        <span class="transcript-field-value">
                            @php
                                $levels = ['A' => 'Undergraduate (HND)', 'M' => 'Masters', 'D' => 'Doctoral'];
                            @endphp
                            {{ $levels[$currentEnroll->program->academic_level] ?? 'N/A' }}
                        </span>
                    </div>
                    @endif
                    <div class="transcript-student-field">
                        <span class="transcript-field-label">Date Printed</span>
                        <span class="transcript-field-value">{{ date('F d, Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- CGPA / CREDITS SUMMARY STRIP --}}
            @php
                $total_cgpa = 0;
                $cgpa_credits = 0;
                $unique_courses = [];
            @endphp
            @foreach( $row->studentEnrolls as $key => $item )
                @if($item->program_id == $selectedProgramId)
                @if(isset($blockedSemesters) && isset($blockedSemesters[$item->session_id . '|' . $item->semester_id]))
                    @continue
                @endif
                @if(isset($item->subjectMarks))
                @foreach($item->subjectMarks as $mark)
                @if($mark->is_visible_to_student && ((date('Y-m-d', strtotime($mark->publish_date)) == date('Y-m-d') && date('H:i:s', strtotime($mark->publish_time)) <= date('H:i:s')) || date('Y-m-d', strtotime($mark->publish_date)) < date('Y-m-d')))
                    @php
                    $marks_per = round($mark->total_marks);
                    $subject_id = $mark->subject_id;
                    $credit_hour = $mark->subject->credit_hour;
                    $is_passed = $marks_per >= 50;
                    @endphp
                    @foreach($grades as $grade)
                    @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                    @php
                    $total_cgpa = $total_cgpa + ($grade->point * $credit_hour);
                    $cgpa_credits = $cgpa_credits + $credit_hour;
                    if(!isset($unique_courses[$subject_id])) {
                        $unique_courses[$subject_id] = [
                            'credits' => $credit_hour,
                            'passed' => $is_passed
                        ];
                    } else {
                        if($is_passed && !$unique_courses[$subject_id]['passed']) {
                            $unique_courses[$subject_id]['passed'] = true;
                        }
                    }
                    @endphp
                    @break
                    @endif
                    @endforeach
                @endif
                @endforeach
                @endif
                @endif
            @endforeach
            @php
                $total_credits_attempted = 0;
                $total_credits_earned = 0;
                foreach($unique_courses as $course) {
                    $total_credits_attempted += $course['credits'];
                    if($course['passed']) {
                        $total_credits_earned += $course['credits'];
                    }
                }
                if($cgpa_credits <= 0) { $cgpa_credits = 1; }
                $com_gpa = $total_cgpa / $cgpa_credits;
            @endphp

            <div class="transcript-summary-strip">
                <div class="transcript-summary-item">
                    <span class="transcript-summary-label">Cumulative GPA</span>
                    <span class="transcript-summary-value transcript-summary-gpa">{{ number_format((float)$com_gpa, 2, '.', '') }}</span>
                </div>
                <div class="transcript-summary-divider"></div>
                <div class="transcript-summary-item">
                    <span class="transcript-summary-label">{{ __('field_credits_attempted') }}</span>
                    <span class="transcript-summary-value">{{ round($total_credits_attempted, 2) }}</span>
                </div>
                <div class="transcript-summary-divider"></div>
                <div class="transcript-summary-item">
                    <span class="transcript-summary-label">{{ __('field_credits_earned') }}</span>
                    <span class="transcript-summary-value">{{ round($total_credits_earned, 2) }}</span>
                </div>
                <div class="transcript-summary-divider"></div>
                <div class="transcript-summary-item">
                    <span class="transcript-summary-label">Total Courses</span>
                    <span class="transcript-summary-value">{{ count($unique_courses) }}</span>
                </div>
            </div>

            {{-- GRADING SCALE (collapsible) --}}
            <div class="transcript-grade-scale-toggle">
                <button class="transcript-toggle-btn" onclick="var p=document.getElementById('gradeScalePanel');p.classList.toggle('transcript-collapsed');this.querySelector('.transcript-toggle-chevron').classList.toggle('transcript-chevron-open')">
                    <i class="fas fa-list-ol"></i> Grading Scale Reference
                    <i class="fas fa-chevron-down transcript-toggle-chevron"></i>
                </button>
            </div>
            <div id="gradeScalePanel" class="transcript-grade-scale-panel transcript-collapsed">
                <table class="transcript-grade-table">
                    <thead>
                        <tr>
                            <th>Grade</th>
                            <th>Grade Point</th>
                            <th>Mark Range</th>
                            <th>Classification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grades as $grade)
                        <tr>
                            <td><strong>{{ $grade->title }}</strong></td>
                            <td>{{ number_format((float)$grade->point, 2, '.', '') }}</td>
                            <td>{{ number_format((float)$grade->min_mark, 0) }}% &ndash; {{ number_format((float)$grade->max_mark, 0) }}%</td>
                            <td>
                                @if($grade->point >= 3.5)
                                    <span class="transcript-class-badge transcript-class-distinction">Distinction</span>
                                @elseif($grade->point >= 3.0)
                                    <span class="transcript-class-badge transcript-class-merit">Merit</span>
                                @elseif($grade->point >= 2.0)
                                    <span class="transcript-class-badge transcript-class-pass">Pass</span>
                                @elseif($grade->point >= 1.0)
                                    <span class="transcript-class-badge transcript-class-marginal">Marginal</span>
                                @else
                                    <span class="transcript-class-badge transcript-class-fail">Fail</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- SEMESTER ACADEMIC RECORDS --}}
            @php
                $semester_items = [];
                $semester_keys = [];
                // $blockedSemesters is provided by TranscriptController and keyed by "{session_id}|{semester_id}"
                if(!isset($blockedSemesters)) { $blockedSemesters = []; }
            @endphp

            @foreach( $row->studentEnrolls as $key => $enroll )
            @if(isset($enroll->session) && isset($enroll->semester) && isset($enroll->section) && $enroll->program_id == $selectedProgramId)
            @php
                $semester_key = $enroll->session->title . '|' . $enroll->semester->title;
                if(!in_array($semester_key, $semester_keys)){
                    array_push($semester_items, array(
                        $enroll->session->title,
                        $enroll->semester->title,
                        $enroll->section->title,
                        $enroll->session_id,
                        $enroll->semester_id,
                    ));
                    array_push($semester_keys, $semester_key);
                }
            @endphp
            @endif
            @endforeach

            @foreach($semester_items as $semIdx => $semester_item)
            @php
                $semBlockKey = ($semester_item[3] ?? '') . '|' . ($semester_item[4] ?? '');
                $semIsBlocked = isset($blockedSemesters[$semBlockKey]);
            @endphp
            <div class="transcript-semester-block">
                {{-- Semester Header --}}
                <div class="transcript-semester-header">
                    <div class="transcript-semester-header-left">
                        <span class="transcript-semester-number">{{ $semIdx + 1 }}</span>
                        <div>
                            <h3 class="transcript-semester-title">{{ $semester_item[1] }}</h3>
                            <span class="transcript-semester-session">{{ $semester_item[0] }} &bull; {{ $semester_item[2] }}</span>
                        </div>
                    </div>
                </div>

                @if($semIsBlocked)
                <div class="alert alert-danger" style="margin: 12px 0; border-left: 4px solid #c62828;">
                    <h6 class="mb-1"><i class="fas fa-lock mr-1"></i> {{ __('Results Withheld') }}</h6>
                    <small>{{ __('Your results for this semester have been withheld by the administration. Please contact the administration office for assistance.') }}</small>
                </div>
                @else
                <div class="transcript-table-wrapper">
                    <div class="transcript-table-watermark" aria-hidden="true">DRAFT</div>

                    <table class="transcript-academic-table">
                        <thead>
                            <tr>
                                <th class="transcript-col-code">{{ __('field_code') }}</th>
                                <th class="transcript-col-title">{{ __('field_subject') }}</th>
                                <th class="transcript-col-type">{{ __('field_subject_type') }}</th>
                                <th class="transcript-col-num">Credit Value</th>
                                <th class="transcript-col-num">Attempted</th>
                                <th class="transcript-col-num">Earned</th>
                                <th class="transcript-col-num">Grade Pt</th>
                                <th class="transcript-col-grade">{{ __('field_grade') }}</th>
                                <th class="transcript-col-num">Quality Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $semester_cgpa_credits = 0;
                                $semester_cgpa = 0;
                                $semester_unique_courses = [];
                            @endphp
                            @foreach( $row->studentEnrolls as $key => $item )
                            @if(isset($item->semester) && isset($item->session) && $semester_item[1] == $item->semester->title && $semester_item[0] == $item->session->title && $item->program_id == $selectedProgramId)

                            @foreach( $item->subjects as $subject )
                            @php
                                $creditsAttempted = (float) $subject->credit_hour;
                                $subject_id = $subject->id;
                                $subject_grade = null;
                                $subjectGradePoint = null;
                                $subjectQualityPoints = null;
                                $creditsEarned = 0;
                                $subjectTypeKey = $subject->subject_type == 0 ? 'subject_type_optional' : ($subject->subject_type == 2 ? 'subject_type_university_requirement' : 'subject_type_compulsory');
                                $isPassed = false;
                            @endphp
                            
                            @php
                                if(isset($item->subjectMarks)){
                                    foreach($item->subjectMarks as $mark){
                                        if($mark->subject_id == $subject->id){
                                            $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? $mark->publish_date->format('Y-m-d') : date('Y-m-d', strtotime($mark->publish_date));
                                            $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? $mark->publish_time->format('H:i:s') : date('H:i:s', strtotime($mark->publish_time));
                                            $currentDate = date('Y-m-d');
                                            $currentTime = date('H:i:s');
                                            
                                            $isVisible = $mark->is_visible_to_student && (($publishDate == $currentDate && $publishTime <= $currentTime) || $publishDate < $currentDate);
                                            
                                            if($isVisible){
                                                $marks_per = round($mark->total_marks);
                                                foreach($grades as $grade){
                                                    if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark){
                                                        $subjectGradePoint = (float) $grade->point;
                                                        $subjectQualityPoints = $subjectGradePoint * $creditsAttempted;
                                                        $isPassed = $marks_per >= 50;
                                                        
                                                        $semester_cgpa += $subjectQualityPoints;
                                                        $semester_cgpa_credits += $creditsAttempted;
                                                        
                                                        if(!isset($semester_unique_courses[$subject_id])) {
                                                            $semester_unique_courses[$subject_id] = [
                                                                'credits' => $creditsAttempted,
                                                                'passed' => $isPassed
                                                            ];
                                                        } else {
                                                            if($isPassed && !$semester_unique_courses[$subject_id]['passed']) {
                                                                $semester_unique_courses[$subject_id]['passed'] = true;
                                                            }
                                                        }
                                                        
                                                        if($subjectGradePoint > 0){
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
                            <tr class="{{ $isPassed ? '' : (!is_null($subjectGradePoint) ? 'transcript-row-fail' : '') }}">
                                <td class="transcript-cell-code" data-label="Code">{{ $subject->code }}</td>
                                <td class="transcript-cell-title" data-label="Subject">{{ $subject->title }}</td>
                                <td class="transcript-cell-type" data-label="Type">
                                    @if($subject->subject_type == 1)
                                        <span class="transcript-type-badge transcript-type-core">C</span>
                                    @elseif($subject->subject_type == 2)
                                        <span class="transcript-type-badge transcript-type-ur">UR</span>
                                    @else
                                        <span class="transcript-type-badge transcript-type-elective">E</span>
                                    @endif
                                </td>
                                <td class="transcript-cell-num" data-label="Credit Value">{{ number_format($creditsAttempted, 1) }}</td>
                                <td class="transcript-cell-num" data-label="Attempted">{{ number_format($creditsAttempted, 1) }}</td>
                                <td class="transcript-cell-num" data-label="Earned">
                                    @if(!is_null($subjectGradePoint))
                                        {{ number_format($creditsEarned, 1) }}
                                    @else
                                        <span class="transcript-pending">&mdash;</span>
                                    @endif
                                </td>
                                <td class="transcript-cell-num" data-label="Grade Pt">
                                    @if(!is_null($subjectGradePoint))
                                        {{ number_format($subjectGradePoint, 2) }}
                                    @else
                                        <span class="transcript-pending">&mdash;</span>
                                    @endif
                                </td>
                                <td class="transcript-cell-grade" data-label="Grade">
                                    @if($subject_grade)
                                        <span class="transcript-grade-pill {{ $isPassed ? 'transcript-grade-pass' : 'transcript-grade-fail' }}">{{ $subject_grade }}</span>
                                    @else
                                        <span class="transcript-pending-badge">Pending</span>
                                    @endif
                                </td>
                                <td class="transcript-cell-num" data-label="Quality Pts">
                                    @if(!is_null($subjectQualityPoints))
                                        {{ number_format($subjectQualityPoints, 2) }}
                                    @else
                                        <span class="transcript-pending">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach

                            @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php
                                $semester_credits_attempted = 0;
                                $semester_credits_earned = 0;
                                foreach($semester_unique_courses as $course) {
                                    $semester_credits_attempted += $course['credits'];
                                    if($course['passed']) {
                                        $semester_credits_earned += $course['credits'];
                                    }
                                }
                                $semesterGpa = $semester_cgpa_credits > 0 ? $semester_cgpa / $semester_cgpa_credits : 0;
                            @endphp
                            <tr class="transcript-footer-totals">
                                <td colspan="3"><strong>Term Totals</strong></td>
                                <td class="transcript-cell-num"><strong>{{ number_format((float)$semester_credits_attempted, 1) }}</strong></td>
                                <td class="transcript-cell-num"><strong>{{ number_format((float)$semester_credits_attempted, 1) }}</strong></td>
                                <td class="transcript-cell-num"><strong>{{ number_format((float)$semester_credits_earned, 1) }}</strong></td>
                                <td colspan="2"></td>
                                <td class="transcript-cell-num"><strong>{{ number_format((float)$semester_cgpa, 2) }}</strong></td>
                            </tr>
                            <tr class="transcript-footer-gpa">
                                <td colspan="4">
                                    <strong>Semester GPA</strong>
                                </td>
                                <td colspan="5" class="transcript-gpa-value">
                                    <strong>{{ number_format((float)$semesterGpa, 2) }}</strong>
                                    @if($semesterGpa >= 3.5)
                                        <span class="transcript-standing transcript-standing-distinction">Dean's List</span>
                                    @elseif($semesterGpa >= 3.0)
                                        <span class="transcript-standing transcript-standing-good">Good Standing</span>
                                    @elseif($semesterGpa >= 2.0)
                                        <span class="transcript-standing transcript-standing-satisfactory">Satisfactory</span>
                                    @elseif($semesterGpa > 0)
                                        <span class="transcript-standing transcript-standing-warning">Academic Warning</span>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>
            @endforeach

            {{-- GPA TREND CHART --}}
            @if(isset($gpa_trend) && count($gpa_trend) > 0)
            <div class="transcript-chart-section">
                <div class="transcript-section-heading">
                    <i class="fas fa-chart-line"></i> Academic Performance Trend
                </div>
                <div class="transcript-chart-controls">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" onclick="updateChartType('line')" id="btn-line">
                            <i class="fas fa-chart-line"></i> Line
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="updateChartType('bar')" id="btn-bar">
                            <i class="fas fa-chart-bar"></i> Bar
                        </button>
                    </div>
                    <div>
                        <span class="transcript-legend-dot" style="background:#2563eb;"></span> Semester GPA
                        <span class="transcript-legend-dot" style="background:#059669; margin-left:12px;"></span> Cumulative GPA
                    </div>
                </div>
                <div style="position:relative; width:100%; max-height:280px;">
                    <canvas id="gpaChart"></canvas>
                </div>

                {{-- Performance Stats Row --}}
                @php
                    $total_semesters = count($gpa_trend);
                    $highest_semester_gpa = $total_semesters > 0 ? max(array_column($gpa_trend, 'semester_gpa')) : 0;
                    $lowest_semester_gpa = $total_semesters > 0 ? min(array_column($gpa_trend, 'semester_gpa')) : 0;
                    $current_cgpa_chart = $total_semesters > 0 ? end($gpa_trend)['cumulative_gpa'] : 0;
                    $total_ca = $total_semesters > 0 ? end($gpa_trend)['cumulative_credits_attempted'] : 0;
                    $total_ce = $total_semesters > 0 ? end($gpa_trend)['cumulative_credits_earned'] : 0;
                    
                    $trend_direction = 'stable';
                    $trend_icon = 'fa-minus';
                    $trend_color = '#6b7280';
                    if ($total_semesters >= 2) {
                        $recent_gpa = $gpa_trend[$total_semesters - 1]['semester_gpa'];
                        $previous_gpa = $gpa_trend[$total_semesters - 2]['semester_gpa'];
                        if ($recent_gpa > $previous_gpa) {
                            $trend_direction = 'Improving';
                            $trend_icon = 'fa-arrow-trend-up';
                            $trend_color = '#059669';
                        } elseif ($recent_gpa < $previous_gpa) {
                            $trend_direction = 'Declining';
                            $trend_icon = 'fa-arrow-trend-down';
                            $trend_color = '#dc2626';
                        }
                    }
                @endphp

                <div class="transcript-stats-row">
                    <div class="transcript-stat-card">
                        <div class="transcript-stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-graduation-cap"></i></div>
                        <div class="transcript-stat-body">
                            <span class="transcript-stat-number">{{ number_format($current_cgpa_chart, 2) }}</span>
                            <span class="transcript-stat-label">Current CGPA</span>
                        </div>
                    </div>
                    <div class="transcript-stat-card">
                        <div class="transcript-stat-icon" style="background:#ecfdf5;color:#059669;"><i class="fas fa-trophy"></i></div>
                        <div class="transcript-stat-body">
                            <span class="transcript-stat-number">{{ number_format($highest_semester_gpa, 2) }}</span>
                            <span class="transcript-stat-label">Best Semester</span>
                        </div>
                    </div>
                    <div class="transcript-stat-card">
                        <div class="transcript-stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas {{ $trend_icon }}"></i></div>
                        <div class="transcript-stat-body">
                            <span class="transcript-stat-number" style="color:{{ $trend_color }}">{{ $trend_direction }}</span>
                            <span class="transcript-stat-label">Trend</span>
                        </div>
                    </div>
                    <div class="transcript-stat-card">
                        <div class="transcript-stat-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-book"></i></div>
                        <div class="transcript-stat-body">
                            <span class="transcript-stat-number">{{ number_format($total_ce, 1) }}<small>/{{ number_format($total_ca, 1) }}</small></span>
                            <span class="transcript-stat-label">Credits Earned / Attempted</span>
                        </div>
                    </div>
                </div>

                {{-- Insights --}}
                <div class="transcript-insights">
                    <div class="transcript-insights-icon"><i class="fas fa-lightbulb"></i></div>
                    <div class="transcript-insights-body">
                        <strong>Performance Insights</strong>
                        <ul>
                            @if($current_cgpa_chart >= 3.5)
                                <li>Outstanding achievement &mdash; you are maintaining an excellent CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>.</li>
                            @elseif($current_cgpa_chart >= 3.0)
                                <li>Strong performance with a CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>. Keep pushing toward distinction!</li>
                            @elseif($current_cgpa_chart >= 2.0)
                                <li>Satisfactory CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>. There is room for improvement.</li>
                            @else
                                <li>Your CGPA is <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>. Please seek academic advising.</li>
                            @endif
                            @if($total_ca > 0)
                                @php $completion = ($total_ce / $total_ca) * 100; @endphp
                                <li>Course completion rate: <strong>{{ number_format($completion, 1) }}%</strong> ({{ number_format($total_ce, 1) }} / {{ number_format($total_ca, 1) }} credits).</li>
                            @endif
                            <li>Completed <strong>{{ $total_semesters }}</strong> semester(s) to date.</li>
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            {{-- DOCUMENT FOOTER --}}
            <div class="transcript-document-footer">
                <div class="transcript-footer-left">
                    <p><strong>IMPORTANT NOTICE:</strong> This is an unofficial draft transcript generated from the student portal. 
                    It does not bear the institution's seal or authorized signature and is <strong>not valid</strong> for 
                    official purposes. For an official transcript, please contact the Academic Registry.</p>
                </div>
                <div class="transcript-footer-right">
                    <p class="transcript-footer-generated">Generated: {{ date('F d, Y \a\t h:i A') }}</p>
                    <p class="transcript-footer-system">{{ $setting->title ?? 'PAXHI' }} Student Portal</p>
                </div>
            </div>

        </div>{{-- end .transcript-document --}}

    </div>
</div>
<!-- End Content-->

@endsection

@section('page_css')
<style>
/* =====================================================
   DRAFT TRANSCRIPT - Mobile-First Professional Styling
   ===================================================== */

/* ---- BASE STYLES (Mobile-first: phones < 480px) ---- */

/* Document Container */
.transcript-document {
    position: relative;
    margin: 0;
    background: #fff;
    overflow: hidden;
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    color: #1e293b;
    -webkit-text-size-adjust: 100%;
}

/* GLOBAL DIAGONAL WATERMARK */
.transcript-watermark-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 1;
    pointer-events: none;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 30px;
    transform: rotate(-30deg);
    transform-origin: center center;
    padding: 100px;
    margin: -100px;
}
.transcript-watermark-overlay span {
    font-size: 44px;
    font-weight: 900;
    color: rgba(220, 38, 38, 0.045);
    text-transform: uppercase;
    letter-spacing: 14px;
    white-space: nowrap;
    user-select: none;
}

/* Z-index for all content sections above watermark */
.transcript-header,
.transcript-student-panel,
.transcript-summary-strip,
.transcript-grade-scale-toggle,
.transcript-grade-scale-panel,
.transcript-semester-block,
.transcript-chart-section,
.transcript-document-footer {
    position: relative;
    z-index: 2;
}

/* INSTITUTION HEADER */
.transcript-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2439 100%);
    color: #fff;
    padding: 0;
}
.transcript-header-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 12px;
    padding: 20px 16px 14px;
}
.transcript-logo-area {
    flex-shrink: 0;
}
.transcript-logo {
    height: 52px;
    width: auto;
    border-radius: 4px;
    background: rgba(255,255,255,0.12);
    padding: 3px;
}
.transcript-logo-placeholder {
    width: 52px; height: 52px;
    background: rgba(255,255,255,0.12);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: rgba(255,255,255,0.7);
}
.transcript-institution-info {
    flex: 1;
}
.transcript-institution-name {
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 0.8px;
    margin: 0 0 3px;
    text-transform: uppercase;
    line-height: 1.35;
}
.transcript-institution-address,
.transcript-institution-contact {
    margin: 0;
    font-size: 11px;
    opacity: 0.82;
    letter-spacing: 0.2px;
}

/* Title bar */
.transcript-title-bar {
    background: rgba(255,255,255,0.08);
    border-top: 1px solid rgba(255,255,255,0.12);
}
.transcript-title-bar-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 10px 16px;
    gap: 8px;
}
.transcript-document-title {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    margin: 0;
    text-transform: uppercase;
}
.transcript-draft-badge {
    background: #dc2626;
    color: #fff;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 4px 10px;
    border-radius: 3px;
    text-transform: uppercase;
    animation: transcript-pulse 2.5s ease-in-out infinite;
}
@keyframes transcript-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* STUDENT INFO PANEL */
.transcript-student-panel {
    padding: 16px;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
.transcript-student-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}
.transcript-student-field {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.transcript-field-label {
    font-size: 9.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #64748b;
}
.transcript-field-value {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
}
.transcript-field-mono {
    font-family: 'Consolas', 'Courier New', monospace;
    letter-spacing: 1px;
    color: #2563eb;
}

/* SUMMARY STRIP */
.transcript-summary-strip {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    padding: 14px 16px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-bottom: 2px solid #e2e8f0;
}
.transcript-summary-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 16px;
    width: 100%;
}
.transcript-summary-label {
    font-size: 9.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #64748b;
    margin-bottom: 1px;
}
.transcript-summary-value {
    font-size: 20px;
    font-weight: 800;
    color: #1e293b;
}
.transcript-summary-gpa {
    color: #2563eb;
    font-size: 26px;
}
.transcript-summary-divider {
    width: 50px;
    height: 1px;
    background: #cbd5e1;
    flex-shrink: 0;
}

/* GRADE SCALE PANEL (collapsible) */
.transcript-grade-scale-toggle {
    padding: 0 16px;
    background: #fff;
}
.transcript-toggle-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 12px 0;
    background: none;
    border: none;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    transition: color 0.2s;
}
.transcript-toggle-btn:hover { color: #2563eb; }
.transcript-toggle-chevron {
    margin-left: auto;
    transition: transform 0.3s;
    font-size: 11px;
}
.transcript-chevron-open { transform: rotate(180deg); }
.transcript-collapsed { display: none; }

.transcript-grade-scale-panel {
    padding: 0 16px 12px;
    background: #fff;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.transcript-grade-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5px;
    min-width: 340px;
}
.transcript-grade-table th {
    background: #f1f5f9;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 9.5px;
    color: #475569;
    padding: 6px 7px;
    text-align: left;
    border-bottom: 2px solid #cbd5e1;
    white-space: nowrap;
}
.transcript-grade-table td {
    padding: 5px 7px;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
}
.transcript-class-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.2px;
}
.transcript-class-distinction { background: #dcfce7; color: #166534; }
.transcript-class-merit { background: #dbeafe; color: #1e40af; }
.transcript-class-pass { background: #fef9c3; color: #854d0e; }
.transcript-class-marginal { background: #ffedd5; color: #9a3412; }
.transcript-class-fail { background: #fee2e2; color: #991b1b; }

/* SEMESTER BLOCKS */
.transcript-semester-block {
    margin: 0;
    padding: 0;
}
.transcript-semester-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #1e3a5f;
    border-top: 3px solid #2563eb;
}
.transcript-semester-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.transcript-semester-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    font-size: 11px;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
}
.transcript-semester-title {
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.transcript-semester-session {
    font-size: 10.5px;
    color: rgba(255,255,255,0.65);
    letter-spacing: 0.2px;
}

/* TABLE WRAPPER — horizontally scrollable on all sizes */
.transcript-table-wrapper {
    position: relative;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.transcript-table-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    font-size: 44px;
    font-weight: 900;
    color: rgba(220, 38, 38, 0.055);
    letter-spacing: 10px;
    text-transform: uppercase;
    pointer-events: none;
    white-space: nowrap;
    user-select: none;
    z-index: 1;
}

/* ===== ACADEMIC TABLE (always tabular, scrollable on small screens) ===== */
.transcript-academic-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    position: relative;
    z-index: 2;
    min-width: 620px;
}
.transcript-academic-table thead th {
    background: #f1f5f9;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 8.5px;
    color: #475569;
    padding: 7px 5px;
    text-align: left;
    border-bottom: 2px solid #94a3b8;
    white-space: nowrap;
}
.transcript-col-code { width: 80px; }
.transcript-col-title { min-width: 140px; }
.transcript-col-type { width: 50px; text-align: center !important; }
.transcript-col-num { width: 62px; text-align: center !important; }
.transcript-col-grade { width: 62px; text-align: center !important; }

.transcript-academic-table tbody td {
    padding: 7px 5px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}
.transcript-academic-table tbody tr:nth-child(even) td {
    background: rgba(248, 250, 252, 0.6);
}
.transcript-academic-table tbody tr:hover td {
    background: rgba(239, 246, 255, 0.7);
}

/* Hide data-label pseudo-elements — not used in table mode */
.transcript-academic-table tbody td[data-label]::before {
    display: none;
}

.transcript-cell-code {
    font-family: 'Consolas', 'Courier New', monospace;
    font-weight: 600;
    color: #334155;
    letter-spacing: 0.5px;
}
.transcript-cell-title {
    font-weight: 500;
    color: #1e293b;
}
.transcript-cell-type { text-align: center; }
.transcript-cell-num { text-align: center; font-variant-numeric: tabular-nums; }
.transcript-cell-grade { text-align: center; }

/* Subject type badges */
.transcript-type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px; height: 24px;
    border-radius: 50%;
    font-size: 9px;
    font-weight: 800;
}
.transcript-type-core { background: #dbeafe; color: #1e40af; }
.transcript-type-ur { background: #f3e8ff; color: #6b21a8; }
.transcript-type-elective { background: #fef3c7; color: #92400e; }

/* Grade pill */
.transcript-grade-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.4px;
}
.transcript-grade-pass { background: #dcfce7; color: #166534; }
.transcript-grade-fail { background: #fee2e2; color: #991b1b; }

.transcript-pending {
    color: #94a3b8;
    font-size: 14px;
}
.transcript-pending-badge {
    display: inline-block;
    padding: 2px 7px;
    background: #f1f5f9;
    color: #64748b;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Failed rows */
.transcript-row-fail td {
    background: rgba(254, 226, 226, 0.25) !important;
}

/* Footer rows */
.transcript-footer-totals td {
    background: #f8fafc !important;
    border-top: 2px solid #94a3b8;
    border-bottom: 1px solid #cbd5e1;
    padding: 8px 5px;
    font-size: 11px;
}
.transcript-footer-gpa td {
    background: #f0f9ff !important;
    padding: 10px 5px;
    font-size: 12px;
}
.transcript-gpa-value {
    text-align: left;
    color: #2563eb;
    font-size: 14px !important;
}

/* Academic standing */
.transcript-standing {
    display: inline-block;
    margin-left: 6px;
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    vertical-align: middle;
}
.transcript-standing-distinction { background: #dcfce7; color: #166534; }
.transcript-standing-good { background: #dbeafe; color: #1e40af; }
.transcript-standing-satisfactory { background: #fef9c3; color: #854d0e; }
.transcript-standing-warning { background: #fee2e2; color: #991b1b; }

/* CHART SECTION */
.transcript-chart-section {
    padding: 16px;
    border-top: 2px solid #e2e8f0;
    background: #fff;
}
.transcript-section-heading {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.transcript-section-heading i { color: #2563eb; }
.transcript-chart-controls {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 11px;
    color: #64748b;
}
.transcript-legend-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    vertical-align: middle;
    margin-right: 4px;
}

/* Stat cards - 2 col grid on mobile */
.transcript-stats-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 14px;
}
.transcript-stat-card {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.transcript-stat-icon {
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    font-size: 14px;
    flex-shrink: 0;
}
.transcript-stat-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.transcript-stat-number {
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.transcript-stat-number small {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
}
.transcript-stat-label {
    font-size: 9px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Insights */
.transcript-insights {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 12px;
    padding: 12px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    font-size: 12px;
    color: #78350f;
}
.transcript-insights-icon {
    font-size: 16px;
    color: #d97706;
    flex-shrink: 0;
}
.transcript-insights-body strong { color: #92400e; }
.transcript-insights-body ul {
    margin: 4px 0 0;
    padding-left: 16px;
}
.transcript-insights-body li {
    margin-bottom: 3px;
    line-height: 1.45;
}

/* DOCUMENT FOOTER */
.transcript-document-footer {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px 16px;
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
    font-size: 10.5px;
    color: #64748b;
    line-height: 1.6;
}
.transcript-footer-left { flex: 1; }
.transcript-footer-left p {
    margin: 0;
    padding: 8px 10px;
    background: #fef2f2;
    border-left: 3px solid #dc2626;
    border-radius: 0 4px 4px 0;
    color: #991b1b;
    font-size: 10px;
}
.transcript-footer-right {
    text-align: left;
    flex-shrink: 0;
}
.transcript-footer-generated {
    margin: 0 0 2px;
    font-weight: 600;
}
.transcript-footer-system {
    margin: 0;
    font-size: 10px;
    opacity: 0.7;
}


/* ================================================================
   BREAKPOINT: 480px+ (Larger phones)
   ================================================================ */
@media (min-width: 480px) {
    .transcript-student-grid {
        grid-template-columns: 1fr 1fr;
        gap: 14px 20px;
    }
    .transcript-field-value { font-size: 13.5px; }

    .transcript-summary-strip {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: center;
    }
    .transcript-summary-item { width: auto; padding: 6px 18px; }
    .transcript-summary-divider { width: 1px; height: 30px; }
    .transcript-summary-value { font-size: 20px; }

    .transcript-insights {
        flex-direction: row;
        gap: 10px;
    }

    .transcript-watermark-overlay span {
        font-size: 52px;
        letter-spacing: 16px;
    }
    .transcript-watermark-overlay { gap: 36px; }

    .transcript-table-watermark { font-size: 52px; letter-spacing: 14px; }
}


/* ================================================================
   BREAKPOINT: 576px+ (Phablets / small tablets — table layout)
   ================================================================ */
@media (min-width: 576px) {
    .transcript-document {
        border-radius: 4px;
        box-shadow: 0 1px 12px rgba(0,0,0,0.06);
    }

    /* Increase section padding */
    .transcript-header-inner { padding: 22px 20px 14px; }
    .transcript-title-bar-inner { padding: 10px 20px; }
    .transcript-student-panel { padding: 18px 20px; }
    .transcript-summary-strip { padding: 16px 20px; }
    .transcript-grade-scale-toggle { padding: 0 20px; }
    .transcript-grade-scale-panel { padding: 0 20px 14px; }
    .transcript-semester-header { padding: 12px 20px; }
    .transcript-chart-section { padding: 18px 20px; }
    .transcript-document-footer { padding: 16px 20px; }

    .transcript-institution-name { font-size: 16px; letter-spacing: 1px; }
    .transcript-draft-badge { font-size: 10px; padding: 4px 12px; }

    /* Table scales up */
    .transcript-academic-table { font-size: 12px; }
    .transcript-academic-table thead th { font-size: 9px; padding: 8px 6px; }
    .transcript-academic-table tbody td { padding: 8px 6px; }
    .transcript-footer-totals td { padding: 10px 6px; font-size: 12px; }
    .transcript-footer-gpa td { padding: 10px 6px; font-size: 13px; }

    .transcript-chart-controls {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}


/* ================================================================
   BREAKPOINT: 768px+ (Tablets)
   ================================================================ */
@media (min-width: 768px) {
    .transcript-document {
        margin: 0 auto;
        box-shadow: 0 2px 20px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.03);
    }

    /* Header: side-by-side */
    .transcript-header-inner {
        flex-direction: row;
        text-align: left;
        gap: 16px;
        padding: 24px 28px 16px;
    }
    .transcript-logo { height: 62px; padding: 4px; }
    .transcript-logo-placeholder { width: 62px; height: 62px; font-size: 28px; }
    .transcript-institution-name { font-size: 19px; letter-spacing: 1.2px; }
    .transcript-institution-address,
    .transcript-institution-contact { font-size: 12px; }

    .transcript-title-bar-inner {
        flex-direction: row;
        justify-content: space-between;
        padding: 12px 28px;
    }
    .transcript-document-title { font-size: 13px; letter-spacing: 2.2px; }
    .transcript-draft-badge { font-size: 10.5px; padding: 5px 12px; letter-spacing: 1.2px; }

    .transcript-student-panel { padding: 20px 28px; }
    .transcript-student-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 16px 24px;
    }
    .transcript-field-label { font-size: 10px; letter-spacing: 0.9px; }
    .transcript-field-value { font-size: 14px; }

    .transcript-summary-strip { padding: 18px 28px; }
    .transcript-summary-item { padding: 4px 22px; }
    .transcript-summary-divider { height: 36px; }
    .transcript-summary-gpa { font-size: 26px; }

    .transcript-grade-scale-toggle { padding: 0 28px; }
    .transcript-grade-scale-panel { padding: 0 28px 14px; }
    .transcript-grade-table { font-size: 12.5px; }
    .transcript-grade-table th { padding: 7px 10px; font-size: 10px; }
    .transcript-grade-table td { padding: 6px 10px; }
    .transcript-class-badge { font-size: 11px; padding: 2px 10px; }

    .transcript-semester-header { padding: 14px 28px; }
    .transcript-semester-number { width: 30px; height: 30px; font-size: 13px; }
    .transcript-semester-title { font-size: 14px; letter-spacing: 0.8px; }
    .transcript-semester-session { font-size: 11.5px; }

    .transcript-academic-table { font-size: 12.5px; min-width: 680px; }
    .transcript-academic-table thead th { font-size: 9.5px; padding: 9px 8px; }
    .transcript-academic-table tbody td { padding: 9px 8px; }

    .transcript-table-watermark { font-size: 66px; letter-spacing: 16px; }
    .transcript-watermark-overlay span { font-size: 66px; letter-spacing: 20px; }
    .transcript-watermark-overlay { gap: 44px; }

    .transcript-chart-section { padding: 22px 28px; }
    .transcript-section-heading { font-size: 14px; }
    .transcript-stats-row { grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .transcript-stat-card { padding: 12px 14px; gap: 10px; }
    .transcript-stat-icon { width: 38px; height: 38px; font-size: 16px; }
    .transcript-stat-number { font-size: 16px; }
    .transcript-stat-number small { font-size: 12px; }
    .transcript-stat-label { font-size: 10px; }

    .transcript-insights { padding: 14px 16px; font-size: 12.5px; }
    .transcript-insights-icon { font-size: 18px; }

    .transcript-document-footer {
        flex-direction: row;
        gap: 20px;
        padding: 18px 28px;
        font-size: 11px;
    }
    .transcript-footer-right { text-align: right; }
    .transcript-footer-left p { font-size: 10.5px; }
}


/* ================================================================
   BREAKPOINT: 1024px+ (Desktops — full width)
   ================================================================ */
@media (min-width: 1024px) {
    .transcript-document {
        max-width: 1100px;
        box-shadow: 0 2px 24px rgba(0,0,0,0.08), 0 0 0 1px rgba(0,0,0,0.04);
    }

    .transcript-header-inner { gap: 20px; padding: 28px 36px 18px; }
    .transcript-logo { height: 72px; }
    .transcript-logo-placeholder { width: 72px; height: 72px; font-size: 32px; }
    .transcript-institution-name { font-size: 22px; letter-spacing: 1.5px; }
    .transcript-institution-address,
    .transcript-institution-contact { font-size: 12.5px; }

    .transcript-title-bar-inner { padding: 12px 36px; }
    .transcript-document-title { font-size: 15px; letter-spacing: 3px; }
    .transcript-draft-badge { font-size: 11px; padding: 5px 14px; letter-spacing: 1.5px; }

    .transcript-student-panel { padding: 24px 36px; }
    .transcript-student-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px 32px;
    }
    .transcript-field-label { font-size: 10.5px; letter-spacing: 1px; }
    .transcript-field-value { font-size: 14.5px; }

    .transcript-summary-strip { padding: 18px 36px; }
    .transcript-summary-item { padding: 4px 28px; min-width: 120px; }
    .transcript-summary-value { font-size: 22px; }
    .transcript-summary-gpa { font-size: 28px; }
    .transcript-summary-divider { height: 40px; }

    .transcript-toggle-btn { font-size: 13px; letter-spacing: 0.8px; }
    .transcript-grade-scale-toggle { padding: 0 36px; }
    .transcript-grade-scale-panel { padding: 0 36px 16px; }
    .transcript-grade-table { font-size: 13px; }
    .transcript-grade-table th { padding: 8px 12px; font-size: 10.5px; }
    .transcript-grade-table td { padding: 7px 12px; }

    .transcript-semester-header { padding: 16px 36px; }
    .transcript-semester-number { width: 32px; height: 32px; font-size: 14px; }
    .transcript-semester-title { font-size: 15px; letter-spacing: 1px; }
    .transcript-semester-session { font-size: 12px; }

    .transcript-academic-table { min-width: auto; font-size: 13px; }
    .transcript-col-code { width: 100px; }
    .transcript-col-title { min-width: 180px; }
    .transcript-col-type { width: 60px; }
    .transcript-col-num { width: 80px; }
    .transcript-col-grade { width: 80px; }
    .transcript-academic-table thead th { padding: 10px 12px; font-size: 10px; }
    .transcript-academic-table tbody td { padding: 10px 12px; }
    .transcript-footer-totals td { padding: 10px 12px; }
    .transcript-footer-gpa td { padding: 12px 12px; font-size: 14px; }
    .transcript-gpa-value { font-size: 16px !important; }
    .transcript-standing { margin-left: 12px; font-size: 10.5px; padding: 2px 10px; }
    .transcript-type-badge { width: 26px; height: 26px; font-size: 10px; }
    .transcript-grade-pill { padding: 3px 12px; font-size: 12px; }

    .transcript-table-watermark { font-size: 80px; letter-spacing: 20px; }
    .transcript-watermark-overlay span { font-size: 90px; letter-spacing: 30px; }
    .transcript-watermark-overlay { gap: 60px; padding: 200px; margin: -200px; }

    .transcript-chart-section { padding: 28px 36px; }
    .transcript-section-heading { font-size: 15px; letter-spacing: 1px; }
    .transcript-chart-controls { font-size: 12.5px; }
    .transcript-stats-row { gap: 14px; }
    .transcript-stat-card { padding: 14px 16px; gap: 12px; }
    .transcript-stat-icon { width: 42px; height: 42px; font-size: 18px; border-radius: 10px; }
    .transcript-stat-number { font-size: 18px; }
    .transcript-stat-number small { font-size: 13px; }
    .transcript-stat-label { font-size: 11px; letter-spacing: 0.5px; }

    .transcript-insights { padding: 16px 18px; font-size: 13px; }
    .transcript-insights-icon { font-size: 20px; }

    .transcript-document-footer { gap: 24px; padding: 20px 36px; font-size: 11.5px; }
}


/* ================================================================
   PRINT STYLES
   ================================================================ */
@media print {
    .transcript-document {
        box-shadow: none;
        border-radius: 0;
        max-width: none;
    }
    .transcript-table-wrapper { overflow: visible; }
    .transcript-academic-table { min-width: auto; }

    /* Preserve watermark colors in print */
    .transcript-watermark-overlay span {
        color: rgba(220, 38, 38, 0.06) !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .transcript-table-watermark {
        color: rgba(220, 38, 38, 0.06) !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .transcript-header,
    .transcript-semester-header,
    .transcript-draft-badge {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .transcript-grade-pill,
    .transcript-type-badge,
    .transcript-class-badge,
    .transcript-standing,
    .transcript-row-fail td,
    .transcript-footer-totals td,
    .transcript-footer-gpa td {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .transcript-chart-section { display: none; }
    .transcript-grade-scale-toggle { display: none; }
    .transcript-grade-scale-panel { display: block !important; padding: 10px 36px 16px; }
    .transcript-toggle-btn { display: none; }
}

</style>
@endsection

@section('page_js')
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script type="text/javascript">
'use strict';

@if(isset($gpa_trend) && count($gpa_trend) > 0)
var gpaData = {!! json_encode($gpa_trend) !!};

var labels = gpaData.map(function(item) { return item.label; });
var semesterGPA = gpaData.map(function(item) { return item.semester_gpa; });
var cumulativeGPA = gpaData.map(function(item) { return item.cumulative_gpa; });

var isMobile = window.innerWidth < 576;
var isTablet = window.innerWidth >= 576 && window.innerWidth < 768;

var chartConfig = {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Semester GPA',
            data: semesterGPA,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            borderWidth: isMobile ? 2 : 3,
            fill: true,
            tension: 0.4,
            pointRadius: isMobile ? 4 : 6,
            pointHoverRadius: isMobile ? 6 : 9,
            pointBackgroundColor: '#2563eb',
            pointBorderColor: '#fff',
            pointBorderWidth: isMobile ? 1.5 : 2
        }, {
            label: 'Cumulative GPA',
            data: cumulativeGPA,
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.08)',
            borderWidth: isMobile ? 2 : 3,
            fill: true,
            tension: 0.4,
            pointRadius: isMobile ? 4 : 6,
            pointHoverRadius: isMobile ? 6 : 9,
            pointBackgroundColor: '#059669',
            pointBorderColor: '#fff',
            pointBorderWidth: isMobile ? 1.5 : 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: window.innerWidth > 768 ? 2.8 : (window.innerWidth > 576 ? 2.2 : 2),
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: '#1e293b',
                titleFont: { size: 13, weight: 'bold' },
                bodyFont: { size: 12 },
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2);
                    },
                    afterLabel: function(ctx) {
                        var d = gpaData[ctx.dataIndex];
                        return ['Credits: ' + d.credits_attempted + ' attempted, ' + d.credits_earned + ' earned'];
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 4.0,
                ticks: {
                    stepSize: isMobile ? 1.0 : 0.5,
                    font: { size: isMobile ? 9 : 11 },
                    color: '#94a3b8',
                    callback: function(v) { return v.toFixed(1); }
                },
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                title: { display: !isMobile, text: 'GPA (0.0 - 4.0)', font: { size: 12, weight: 'bold' }, color: '#64748b' }
            },
            x: {
                ticks: {
                    font: { size: isMobile ? 8 : 10 },
                    color: '#94a3b8',
                    maxRotation: isMobile ? 65 : 45,
                    minRotation: isMobile ? 45 : 45
                },
                grid: { display: false }
            }
        }
    }
};

var ctx = document.getElementById('gpaChart').getContext('2d');
var gpaChart = new Chart(ctx, chartConfig);

window.updateChartType = function(type) {
    gpaChart.destroy();
    chartConfig.type = type;
    if (type === 'bar') {
        chartConfig.data.datasets[0].backgroundColor = 'rgba(37, 99, 235, 0.65)';
        chartConfig.data.datasets[1].backgroundColor = 'rgba(5, 150, 105, 0.65)';
        chartConfig.data.datasets[0].borderWidth = 2;
        chartConfig.data.datasets[1].borderWidth = 2;
    } else {
        chartConfig.data.datasets[0].backgroundColor = 'rgba(37, 99, 235, 0.08)';
        chartConfig.data.datasets[1].backgroundColor = 'rgba(5, 150, 105, 0.08)';
        chartConfig.data.datasets[0].borderWidth = 3;
        chartConfig.data.datasets[1].borderWidth = 3;
    }
    gpaChart = new Chart(ctx, chartConfig);
    document.getElementById('btn-line').classList.toggle('active', type === 'line');
    document.getElementById('btn-bar').classList.toggle('active', type === 'bar');
};
@endif
</script>
@endsection