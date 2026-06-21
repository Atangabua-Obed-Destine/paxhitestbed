@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content - Admin Transcript View -->
<div class="main-body">
    <div class="page-wrapper">

        @php
            // Get all enrollments for this student
            $tempEnrollments = \App\Models\StudentEnroll::where('student_id', $row->id)
                ->with(['program.degreeType', 'program.faculty', 'semester', 'session'])
                ->orderBy('id', 'desc')
                ->get();
            
            // Group by unique matricule and keep only the latest enrollment for each
            $allEnrollments = $tempEnrollments->groupBy('matricule')->map(function($group) {
                return $group->first();
            })->values();
            
            // Get selected enrollment from URL parameter or use latest
            $selectedEnrollmentId = request()->get('enrollment_id');
            if ($selectedEnrollmentId) {
                $currentEnroll = $allEnrollments->firstWhere('id', $selectedEnrollmentId);
            }
            if (!isset($currentEnroll) || !$currentEnroll) {
                $currentEnroll = $allEnrollments->first();
            }
            $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : $row->program_id;
        @endphp

        {{-- ===== ADMIN TOOLBAR ===== --}}
        <div class="ams-toolbar">
            <div class="ams-toolbar-left">
                <a href="{{ route($route.'.index') }}" class="ams-btn ams-btn-ghost">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="ams-toolbar-right">
                @can($access.'-print')
                <a href="{{ route('admin.marksheet.print', $row->id) }}?enrollment_id={{ $currentEnroll->id ?? '' }}" 
                   class="ams-btn ams-btn-primary" target="_blank">
                    <i class="fas fa-print"></i> Print Transcript
                </a>
                @endcan
                @can($access.'-download')
                <a href="{{ route('admin.marksheet.download', $row->id) }}?enrollment_id={{ $currentEnroll->id ?? '' }}" 
                   class="ams-btn ams-btn-secondary">
                    <i class="fas fa-download"></i> Download PDF
                </a>
                @endcan
                <button onclick="window.print()" class="ams-btn ams-btn-outline">
                    <i class="fas fa-file-alt"></i> Print Page
                </button>
            </div>
        </div>

        {{-- ===== MULTI-ENROLLMENT SWITCHER ===== --}}
        @if($allEnrollments->count() > 1)
        <div class="ams-enrollment-switcher">
            <div class="ams-switcher-icon">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="ams-switcher-info">
                <h6>Multi-Enrollment Student</h6>
                <p>This student has {{ $allEnrollments->count() }} program enrollments. Select one to view its transcript.</p>
            </div>
            <div class="dropdown">
                <button class="ams-btn ams-btn-switcher dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-graduation-cap"></i> {{ $currentEnroll->matricule ?? 'Select' }}
                </button>
                <ul class="dropdown-menu ams-dropdown-programs">
                    @foreach($allEnrollments as $enrollment)
                    <li>
                        <a class="dropdown-item @if($enrollment->id == ($currentEnroll->id ?? 0)) active @endif" 
                           href="{{ route($route.'.show', $row->id) }}?enrollment_id={{ $enrollment->id }}">
                            <div class="ams-program-item">
                                <div class="ams-program-badge" style="background: {{ $enrollment->program->academic_level == 'M' ? '#f5576c' : ($enrollment->program->academic_level == 'D' ? '#4facfe' : '#38f9d7') }}">
                                    {{ $enrollment->program->academic_level == 'A' ? 'UG' : ($enrollment->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                </div>
                                <div class="ams-program-details">
                                    <strong>{{ $enrollment->matricule }}</strong>
                                    <span>{{ $enrollment->program->title ?? 'N/A' }}</span>
                                    <small>{{ $enrollment->session->title ?? '' }} &bull; {{ $enrollment->semester->title ?? '' }}</small>
                                </div>
                                @if($enrollment->id == ($currentEnroll->id ?? 0))
                                <i class="fas fa-check-circle text-success"></i>
                                @endif
                            </div>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- ===== TRANSCRIPT DOCUMENT ===== --}}
        <div class="ams-document" id="transcriptDocument">

            {{-- INSTITUTION HEADER --}}
            <div class="ams-header">
                <div class="ams-header-inner">
                    <div class="ams-logo-area">
                        @if(isset($setting) && isset($setting->logo_path) && function_exists('upload_exists') && upload_exists('setting/'.$setting->logo_path))
                            <img src="{{ upload_asset('setting/'.$setting->logo_path) }}" alt="Logo" class="ams-logo">
                        @else
                            <div class="ams-logo-placeholder">
                                <i class="fas fa-university"></i>
                            </div>
                        @endif
                    </div>
                    <div class="ams-institution-info">
                        <h1 class="ams-institution-name">{{ $setting->title ?? 'INSTITUTION NAME' }}</h1>
                        @if(isset($setting->address) && $setting->address)
                        <p class="ams-institution-detail">{{ $setting->address }}</p>
                        @endif
                        @if((isset($setting->phone) && $setting->phone) || (isset($setting->email) && $setting->email))
                        <p class="ams-institution-detail">
                            @if(isset($setting->phone) && $setting->phone)Tel: {{ $setting->phone }}@endif
                            @if(isset($setting->phone) && $setting->phone && isset($setting->email) && $setting->email) &bull; @endif
                            @if(isset($setting->email) && $setting->email)Email: {{ $setting->email }}@endif
                        </p>
                        @endif
                    </div>
                </div>
                <div class="ams-title-bar">
                    <h2 class="ams-document-title">ACADEMIC TRANSCRIPT</h2>
                    <div class="ams-admin-badge">
                        <i class="fas fa-shield-alt"></i> ADMINISTRATIVE COPY
                    </div>
                </div>
            </div>

            {{-- STUDENT INFORMATION PANEL --}}
            <div class="ams-student-panel">
                <div class="ams-panel-label">
                    <i class="fas fa-user-graduate"></i> Student Information
                </div>
                <div class="ams-student-grid">
                    <div class="ams-field">
                        <span class="ams-field-label">Full Name</span>
                        <span class="ams-field-value">{{ strtoupper($row->first_name . ' ' . $row->last_name) }}</span>
                    </div>
                    @if($currentEnroll)
                    <div class="ams-field">
                        <span class="ams-field-label">{{ __('field_matricule') }}</span>
                        <span class="ams-field-value ams-field-mono">{{ $currentEnroll->matricule }}</span>
                    </div>
                    @endif
                    <div class="ams-field">
                        <span class="ams-field-label">Student ID</span>
                        <span class="ams-field-value ams-field-mono">{{ $row->student_id }}</span>
                    </div>
                    <div class="ams-field">
                        <span class="ams-field-label">{{ __('field_program') }}</span>
                        <span class="ams-field-value">{{ $currentEnroll->program->title ?? $row->program->title ?? 'N/A' }}
                            @if($currentEnroll && $currentEnroll->program)
                                <span class="ams-level-tag">
                                    {{ $currentEnroll->program->academic_level == 'A' ? 'Undergraduate' : ($currentEnroll->program->academic_level == 'M' ? 'Masters' : 'Doctoral') }}
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="ams-field">
                        <span class="ams-field-label">{{ __('field_batch') }}</span>
                        <span class="ams-field-value">{{ $row->batch->title ?? 'N/A' }}</span>
                    </div>
                    @if($row->dob)
                    <div class="ams-field">
                        <span class="ams-field-label">Date of Birth</span>
                        <span class="ams-field-value">{{ date($setting->date_format ?? 'd-m-Y', strtotime($row->dob)) }}</span>
                    </div>
                    @endif
                    @if($row->gender)
                    <div class="ams-field">
                        <span class="ams-field-label">Gender</span>
                        <span class="ams-field-value">{{ ucfirst($row->gender) }}</span>
                    </div>
                    @endif
                    @if($row->admission_date)
                    <div class="ams-field">
                        <span class="ams-field-label">Admission Date</span>
                        <span class="ams-field-value">{{ date($setting->date_format ?? 'd-m-Y', strtotime($row->admission_date)) }}</span>
                    </div>
                    @endif
                    @if($row->nationality)
                    <div class="ams-field">
                        <span class="ams-field-label">Nationality</span>
                        <span class="ams-field-value">{{ $row->nationality }}</span>
                    </div>
                    @endif
                    <div class="ams-field">
                        <span class="ams-field-label">Status</span>
                        <span class="ams-field-value">
                            @if($row->status == 1)
                                <span class="ams-status-active"><i class="fas fa-check-circle"></i> Active</span>
                            @else
                                <span class="ams-status-inactive"><i class="fas fa-times-circle"></i> Inactive</span>
                            @endif
                        </span>
                    </div>
                    <div class="ams-field">
                        <span class="ams-field-label">Date Printed</span>
                        <span class="ams-field-value">{{ date('F d, Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- CGPA / CREDITS SUMMARY STRIP --}}
            @php
                $total_cgpa = 0;
                $cgpa_credits = 0;
                $unique_courses = [];
            @endphp
            @foreach($row->studentEnrolls as $item)
                @if($item->program_id == $selectedProgramId && $item->matricule == $currentEnroll->matricule)
                @if(isset($item->subjectMarks))
                @foreach($item->subjectMarks as $mark)
                @if($mark->is_visible_to_student)
                    @php
                    $marks_per = round($mark->total_marks);
                    $subject_id = $mark->subject_id;
                    $credit_hour = $mark->subject->credit_hour;
                    $is_passed = $marks_per >= 50;
                    @endphp
                    @foreach($grades as $grade)
                    @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                    @php
                    $total_cgpa += ($grade->point * $credit_hour);
                    $cgpa_credits += $credit_hour;
                    if(!isset($unique_courses[$subject_id])) {
                        $unique_courses[$subject_id] = ['credits' => $credit_hour, 'passed' => $is_passed];
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
                    if($course['passed']) $total_credits_earned += $course['credits'];
                }
                if($cgpa_credits <= 0) $cgpa_credits = 1;
                $com_gpa = $total_cgpa / $cgpa_credits;
                
                // Determine academic standing
                $standing_label = 'N/A';
                $standing_class = 'neutral';
                if($com_gpa >= 3.6) { $standing_label = 'First Class (Distinction)'; $standing_class = 'distinction'; }
                elseif($com_gpa >= 3.0) { $standing_label = 'Second Class (Upper Division)'; $standing_class = 'merit'; }
                elseif($com_gpa >= 2.5) { $standing_label = 'Second Class (Lower Division)'; $standing_class = 'good'; }
                elseif($com_gpa >= 2.0) { $standing_label = 'Third Class'; $standing_class = 'satisfactory'; }
                elseif($com_gpa >= 1.0) { $standing_label = 'Pass'; $standing_class = 'warning'; }
                elseif(count($unique_courses) > 0) { $standing_label = 'Fail'; $standing_class = 'fail'; }
            @endphp

            <div class="ams-summary-strip">
                <div class="ams-summary-item ams-summary-gpa-item">
                    <span class="ams-summary-label">Cumulative GPA</span>
                    <span class="ams-summary-value ams-gpa-highlight">{{ number_format((float)$com_gpa, 2, '.', '') }}</span>
                </div>
                <div class="ams-summary-divider"></div>
                <div class="ams-summary-item">
                    <span class="ams-summary-label">Credits Attempted</span>
                    <span class="ams-summary-value">{{ round($total_credits_attempted, 1) }}</span>
                </div>
                <div class="ams-summary-divider"></div>
                <div class="ams-summary-item">
                    <span class="ams-summary-label">Credits Earned</span>
                    <span class="ams-summary-value">{{ round($total_credits_earned, 1) }}</span>
                </div>
                <div class="ams-summary-divider"></div>
                <div class="ams-summary-item">
                    <span class="ams-summary-label">Total Courses</span>
                    <span class="ams-summary-value">{{ count($unique_courses) }}</span>
                </div>
                <div class="ams-summary-divider"></div>
                <div class="ams-summary-item">
                    <span class="ams-summary-label">Academic Standing</span>
                    <span class="ams-standing-badge ams-standing-{{ $standing_class }}">{{ $standing_label }}</span>
                </div>
            </div>

            {{-- GRADING SCALE (collapsible) --}}
            <div class="ams-grade-toggle-area">
                <button class="ams-toggle-btn" onclick="var p=document.getElementById('amsGradePanel');p.classList.toggle('ams-collapsed');this.querySelector('.ams-chevron').classList.toggle('ams-chevron-open')">
                    <i class="fas fa-list-ol"></i> Grading Scale Reference
                    <i class="fas fa-chevron-down ams-chevron"></i>
                </button>
            </div>
            <div id="amsGradePanel" class="ams-grade-panel ams-collapsed">
                <table class="ams-grade-table">
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
                                    <span class="ams-class-badge ams-class-distinction">Distinction</span>
                                @elseif($grade->point >= 3.0)
                                    <span class="ams-class-badge ams-class-merit">Merit</span>
                                @elseif($grade->point >= 2.0)
                                    <span class="ams-class-badge ams-class-pass">Pass</span>
                                @elseif($grade->point >= 1.0)
                                    <span class="ams-class-badge ams-class-marginal">Marginal</span>
                                @else
                                    <span class="ams-class-badge ams-class-fail">Fail</span>
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
            @endphp

            @foreach($row->studentEnrolls as $enroll)
            @if(isset($enroll->session) && isset($enroll->semester) && isset($enroll->section) && $enroll->program_id == $selectedProgramId && $enroll->matricule == $currentEnroll->matricule)
            @php
                $semester_key = $enroll->session->title . '|' . $enroll->semester->title;
                if(!in_array($semester_key, $semester_keys)){
                    array_push($semester_items, [$enroll->session->title, $enroll->semester->title, $enroll->section->title]);
                    array_push($semester_keys, $semester_key);
                }
            @endphp
            @endif
            @endforeach

            @foreach($semester_items as $semIdx => $semester_item)
            <div class="ams-semester-block">
                {{-- Semester Header --}}
                <div class="ams-semester-header">
                    <div class="ams-semester-header-left">
                        <span class="ams-semester-number">{{ $semIdx + 1 }}</span>
                        <div>
                            <h3 class="ams-semester-title">{{ $semester_item[1] }}</h3>
                            <span class="ams-semester-session">{{ $semester_item[0] }} &bull; Section: {{ $semester_item[2] }}</span>
                        </div>
                    </div>
                </div>

                {{-- Semester Academic Table --}}
                <div class="ams-table-wrapper">
                    <table class="ams-academic-table">
                        <thead>
                            <tr>
                                <th class="ams-col-code">{{ __('field_code') }}</th>
                                <th class="ams-col-title">{{ __('field_subject') }}</th>
                                <th class="ams-col-type">Type</th>
                                <th class="ams-col-num">Credit Value</th>
                                <th class="ams-col-num">Attempted</th>
                                <th class="ams-col-num">Earned</th>
                                <th class="ams-col-num">Grade Pt</th>
                                <th class="ams-col-grade">{{ __('field_grade') }}</th>
                                <th class="ams-col-num">Quality Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $semester_credits = 0;
                                $semester_cgpa = 0;
                                $semester_credits_earned = 0;
                                $semester_course_count = 0;
                                $semester_pass_count = 0;
                                $semester_fail_count = 0;
                            @endphp
                            @foreach($row->studentEnrolls as $item)
                            @if(isset($item->semester) && isset($item->session) && $semester_item[1] == $item->semester->title && $semester_item[0] == $item->session->title && $item->program_id == $selectedProgramId && $item->matricule == $currentEnroll->matricule)

                            @foreach($item->subjects as $subject)
                            @php
                                $creditsAttempted = (float) $subject->credit_hour;
                                $semester_credits += $creditsAttempted;
                                $subject_grade = null;
                                $subjectGradePoint = null;
                                $subjectQualityPoints = null;
                                $creditsEarned = 0;
                                $isPassed = false;
                                $subjectTypeKey = $subject->subject_type == 0 ? 'subject_type_optional' : ($subject->subject_type == 2 ? 'subject_type_university_requirement' : 'subject_type_compulsory');
                            @endphp
                            
                            @php
                                if(isset($item->subjectMarks)){
                                    foreach($item->subjectMarks as $mark){
                                        if($mark->subject_id == $subject->id){
                                            if($mark->is_visible_to_student){
                                                $marks_per = round($mark->total_marks);
                                                foreach($grades as $grade){
                                                    if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark){
                                                        $subjectGradePoint = (float) $grade->point;
                                                        $subjectQualityPoints = $subjectGradePoint * $creditsAttempted;
                                                        $isPassed = $marks_per >= 50;
                                                        $semester_cgpa += $subjectQualityPoints;
                                                        if($subjectGradePoint > 0){
                                                            $semester_credits_earned += $creditsAttempted;
                                                            $creditsEarned = $creditsAttempted;
                                                        }
                                                        $subject_grade = $grade->title;
                                                        $semester_course_count++;
                                                        if($isPassed) $semester_pass_count++;
                                                        else $semester_fail_count++;
                                                        break;
                                                    }
                                                }
                                            }
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            <tr class="{{ !$isPassed && !is_null($subjectGradePoint) ? 'ams-row-fail' : '' }}">
                                <td class="ams-cell-code">{{ $subject->code }}</td>
                                <td class="ams-cell-title">{{ $subject->title }}</td>
                                <td class="ams-cell-type">
                                    @if($subject->subject_type == 1)
                                        <span class="ams-type-badge ams-type-core">C</span>
                                    @elseif($subject->subject_type == 2)
                                        <span class="ams-type-badge ams-type-ur">UR</span>
                                    @else
                                        <span class="ams-type-badge ams-type-elective">E</span>
                                    @endif
                                </td>
                                <td class="ams-cell-num">{{ number_format($creditsAttempted, 1) }}</td>
                                <td class="ams-cell-num">{{ number_format($creditsAttempted, 1) }}</td>
                                <td class="ams-cell-num">
                                    @if(!is_null($subjectGradePoint))
                                        {{ number_format($creditsEarned, 1) }}
                                    @else
                                        <span class="ams-pending">&mdash;</span>
                                    @endif
                                </td>
                                <td class="ams-cell-num">
                                    @if(!is_null($subjectGradePoint))
                                        {{ number_format($subjectGradePoint, 2) }}
                                    @else
                                        <span class="ams-pending">&mdash;</span>
                                    @endif
                                </td>
                                <td class="ams-cell-grade">
                                    @if($subject_grade)
                                        <span class="ams-grade-pill {{ $isPassed ? 'ams-grade-pass' : 'ams-grade-fail' }}">{{ $subject_grade }}</span>
                                    @else
                                        <span class="ams-pending-badge">Pending</span>
                                    @endif
                                </td>
                                <td class="ams-cell-num">
                                    @if(!is_null($subjectQualityPoints))
                                        {{ number_format($subjectQualityPoints, 2) }}
                                    @else
                                        <span class="ams-pending">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php
                                $semesterGpa = $semester_credits > 0 ? $semester_cgpa / $semester_credits : 0;
                            @endphp
                            <tr class="ams-footer-totals">
                                <td colspan="3"><strong>Semester Totals</strong></td>
                                <td class="ams-cell-num"><strong>{{ number_format((float)$semester_credits, 1) }}</strong></td>
                                <td class="ams-cell-num"><strong>{{ number_format((float)$semester_credits, 1) }}</strong></td>
                                <td class="ams-cell-num"><strong>{{ number_format((float)$semester_credits_earned, 1) }}</strong></td>
                                <td colspan="2"></td>
                                <td class="ams-cell-num"><strong>{{ number_format((float)$semester_cgpa, 2) }}</strong></td>
                            </tr>
                            <tr class="ams-footer-gpa">
                                <td colspan="3">
                                    <strong>Semester GPA</strong>
                                </td>
                                <td colspan="2" class="ams-gpa-value">
                                    <strong>{{ number_format((float)$semesterGpa, 2) }}</strong>
                                    @if($semesterGpa >= 3.5)
                                        <span class="ams-standing ams-standing-distinction">Dean's List</span>
                                    @elseif($semesterGpa >= 3.0)
                                        <span class="ams-standing ams-standing-good">Good Standing</span>
                                    @elseif($semesterGpa >= 2.0)
                                        <span class="ams-standing ams-standing-satisfactory">Satisfactory</span>
                                    @elseif($semesterGpa > 0)
                                        <span class="ams-standing ams-standing-warning">Academic Warning</span>
                                    @endif
                                </td>
                                <td colspan="4" style="text-align:right;">
                                    <span style="font-size:12px; color:#666;">Credits Earned: {{ number_format($semester_credits_earned, 1) }} / {{ number_format($semester_credits, 1) }}</span>
                                </td>
                            </tr>
                            @if($semester_course_count > 0)
                            <tr class="ams-footer-stats">
                                <td colspan="9">
                                    <div class="ams-semester-stats">
                                        <span><i class="fas fa-book"></i> {{ $semester_course_count }} course(s)</span>
                                        <span class="ams-stat-pass"><i class="fas fa-check"></i> {{ $semester_pass_count }} passed</span>
                                        @if($semester_fail_count > 0)
                                        <span class="ams-stat-fail"><i class="fas fa-times"></i> {{ $semester_fail_count }} failed</span>
                                        @endif
                                        <span><i class="fas fa-award"></i> {{ number_format($semester_credits_earned, 1) }}/{{ number_format($semester_credits, 1) }} credits earned</span>
                                    </div>
                                </td>
                            </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </div>
            @endforeach

            {{-- GPA TREND CHART --}}
            @if(isset($gpa_trend) && count($gpa_trend) > 0)
            <div class="ams-chart-section">
                <div class="ams-section-heading">
                    <i class="fas fa-chart-line"></i> Academic Performance Trend
                </div>
                <div class="ams-chart-controls">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" onclick="updateChartType('line')" id="btn-line">
                            <i class="fas fa-chart-line"></i> Line
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="updateChartType('bar')" id="btn-bar">
                            <i class="fas fa-chart-bar"></i> Bar
                        </button>
                    </div>
                    <div>
                        <span class="ams-legend-dot" style="background:#2563eb;"></span> Semester GPA
                        <span class="ams-legend-dot" style="background:#059669; margin-left:12px;"></span> Cumulative GPA
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
                    $total_credits_chart = $total_semesters > 0 ? end($gpa_trend)['cumulative_credits'] : 0;
                    
                    $trend_direction = 'Stable';
                    $trend_icon = 'fa-minus';
                    $trend_color = '#6b7280';
                    if ($total_semesters >= 2) {
                        $recent_gpa = $gpa_trend[$total_semesters - 1]['semester_gpa'];
                        $previous_gpa = $gpa_trend[$total_semesters - 2]['semester_gpa'];
                        if ($recent_gpa > $previous_gpa) {
                            $trend_direction = 'Improving';
                            $trend_icon = 'fa-arrow-up';
                            $trend_color = '#059669';
                        } elseif ($recent_gpa < $previous_gpa) {
                            $trend_direction = 'Declining';
                            $trend_icon = 'fa-arrow-down';
                            $trend_color = '#dc2626';
                        }
                    }
                @endphp

                <div class="ams-stats-row">
                    <div class="ams-stat-card">
                        <div class="ams-stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-graduation-cap"></i></div>
                        <div class="ams-stat-body">
                            <span class="ams-stat-number">{{ number_format($current_cgpa_chart, 2) }}</span>
                            <span class="ams-stat-label">Current CGPA</span>
                        </div>
                    </div>
                    <div class="ams-stat-card">
                        <div class="ams-stat-icon" style="background:#ecfdf5;color:#059669;"><i class="fas fa-trophy"></i></div>
                        <div class="ams-stat-body">
                            <span class="ams-stat-number">{{ number_format($highest_semester_gpa, 2) }}</span>
                            <span class="ams-stat-label">Best Semester</span>
                        </div>
                    </div>
                    <div class="ams-stat-card">
                        <div class="ams-stat-icon" style="background:#fef2f2;color:#dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="ams-stat-body">
                            <span class="ams-stat-number">{{ number_format($lowest_semester_gpa, 2) }}</span>
                            <span class="ams-stat-label">Lowest Semester</span>
                        </div>
                    </div>
                    <div class="ams-stat-card">
                        <div class="ams-stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas {{ $trend_icon }}"></i></div>
                        <div class="ams-stat-body">
                            <span class="ams-stat-number" style="color:{{ $trend_color }}">{{ $trend_direction }}</span>
                            <span class="ams-stat-label">Performance Trend</span>
                        </div>
                    </div>
                </div>

                {{-- Admin Insights --}}
                <div class="ams-insights">
                    <div class="ams-insights-icon"><i class="fas fa-lightbulb"></i></div>
                    <div class="ams-insights-body">
                        <strong>Administrative Insights</strong>
                        <ul>
                            @if($trend_direction == 'Improving')
                                <li>Student's academic performance is improving. Recent semester shows upward trajectory.</li>
                            @elseif($trend_direction == 'Declining')
                                <li class="text-danger"><strong>Attention Required:</strong> Recent semester performance has declined. Consider scheduling an academic advising session.</li>
                            @else
                                <li>Performance has been consistent across recent semesters.</li>
                            @endif
                            
                            @if($current_cgpa_chart >= 3.5)
                                <li>Outstanding achievement &mdash; student maintains an excellent CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>. Eligible for Dean's List.</li>
                            @elseif($current_cgpa_chart >= 3.0)
                                <li>Good academic standing with CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong>.</li>
                            @elseif($current_cgpa_chart >= 2.0)
                                <li>CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong> is satisfactory. Additional academic support may help improve performance.</li>
                            @else
                                <li class="text-danger"><strong>Academic Probation Alert:</strong> CGPA of <strong>{{ number_format($current_cgpa_chart, 2) }}</strong> is below the minimum threshold. Immediate academic intervention recommended.</li>
                            @endif
                            
                            @if($total_credits_attempted > 0)
                                @php $completion_rate = ($total_credits_earned / $total_credits_attempted) * 100; @endphp
                                <li>Course completion rate: <strong>{{ number_format($completion_rate, 1) }}%</strong> ({{ number_format($total_credits_earned, 1) }}/{{ number_format($total_credits_attempted, 1) }} credits earned).</li>
                            @endif
                            <li>Student has completed <strong>{{ $total_semesters }}</strong> semester(s) with <strong>{{ number_format($total_credits_chart, 1) }}</strong> total credits.</li>
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            {{-- DOCUMENT FOOTER --}}
            <div class="ams-document-footer">
                <div class="ams-footer-left">
                    <p><strong>ADMINISTRATIVE USE ONLY:</strong> This transcript is generated from the administrative portal for internal academic review purposes. 
                    It reflects published grades and official enrollment records as of the date printed.</p>
                </div>
                <div class="ams-footer-right">
                    <p class="ams-footer-generated">Generated: {{ date('F d, Y \a\t h:i A') }}</p>
                    <p class="ams-footer-system">{{ $setting->title ?? 'Institution' }} &mdash; Admin Portal</p>
                </div>
            </div>

        </div>{{-- end .ams-document --}}

    </div>
</div>
<!-- End Content-->

@endsection

@section('page_css')
<style>
/* =====================================================
   ADMIN MARKSHEET - Professional Transcript Styling
   ===================================================== */

/* ---- ADMIN TOOLBAR ---- */
.ams-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
    padding: 12px 16px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.ams-toolbar-left, .ams-toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }
.ams-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.ams-btn-primary { background: #2563eb; color: #fff; }
.ams-btn-primary:hover { background: #1d4ed8; color: #fff; }
.ams-btn-secondary { background: #059669; color: #fff; }
.ams-btn-secondary:hover { background: #047857; color: #fff; }
.ams-btn-outline { background: transparent; color: #475569; border: 1px solid #cbd5e1; }
.ams-btn-outline:hover { background: #f1f5f9; color: #1e293b; }
.ams-btn-ghost { background: transparent; color: #64748b; }
.ams-btn-ghost:hover { background: #f1f5f9; color: #1e293b; }
.ams-btn-switcher { background: #fff; color: #1e293b; border: 2px solid #e2e8f0; min-width: 200px; font-weight: 600; }
.ams-btn-switcher:hover { border-color: #2563eb; }

/* ---- ENROLLMENT SWITCHER ---- */
.ams-enrollment-switcher {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    margin-bottom: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    color: #fff;
}
.ams-switcher-icon { font-size: 24px; opacity: 0.9; }
.ams-switcher-info { flex: 1; }
.ams-switcher-info h6 { margin: 0; font-weight: 700; font-size: 14px; color: #fff; }
.ams-switcher-info p { margin: 2px 0 0; font-size: 12px; opacity: 0.85; }
.ams-dropdown-programs { min-width: 360px; padding: 6px; }
.ams-dropdown-programs .dropdown-item { padding: 10px 12px; border-radius: 6px; }
.ams-dropdown-programs .dropdown-item.active { background: #eff6ff; }
.ams-program-item { display: flex; align-items: center; gap: 10px; }
.ams-program-badge {
    width: 34px; height: 34px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 800; color: #fff; flex-shrink: 0;
}
.ams-program-details { flex: 1; display: flex; flex-direction: column; }
.ams-program-details strong { font-size: 13px; color: #1e293b; }
.ams-program-details span { font-size: 11.5px; color: #64748b; }
.ams-program-details small { font-size: 10.5px; color: #94a3b8; }

/* ---- DOCUMENT CONTAINER ---- */
.ams-document {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 20px rgba(0,0,0,0.07), 0 0 0 1px rgba(0,0,0,0.03);
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    color: #1e293b;
}

/* ---- INSTITUTION HEADER ---- */
.ams-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2439 100%);
    color: #fff;
}
.ams-header-inner {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px 36px 16px;
}
.ams-logo { height: 68px; width: auto; border-radius: 4px; background: rgba(255,255,255,0.12); padding: 4px; }
.ams-logo-placeholder {
    width: 68px; height: 68px;
    background: rgba(255,255,255,0.12);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: rgba(255,255,255,0.7);
}
.ams-institution-info { flex: 1; }
.ams-institution-name {
    font-size: 20px; font-weight: 800;
    letter-spacing: 1.5px; margin: 0 0 4px;
    text-transform: uppercase; line-height: 1.3;
}
.ams-institution-detail {
    margin: 0; font-size: 12px; opacity: 0.8; letter-spacing: 0.3px;
}
.ams-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 36px;
    background: rgba(255,255,255,0.08);
    border-top: 1px solid rgba(255,255,255,0.12);
}
.ams-document-title {
    font-size: 14px; font-weight: 700;
    letter-spacing: 2.5px; margin: 0;
    text-transform: uppercase;
}
.ams-admin-badge {
    background: #f59e0b; color: #1e293b;
    font-size: 10px; font-weight: 800;
    letter-spacing: 1px; padding: 5px 14px;
    border-radius: 4px; text-transform: uppercase;
}

/* ---- STUDENT PANEL ---- */
.ams-student-panel {
    padding: 24px 36px;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
.ams-panel-label {
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px;
    color: #2563eb; margin-bottom: 14px;
    display: flex; align-items: center; gap: 6px;
}
.ams-student-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px 28px;
}
.ams-field { display: flex; flex-direction: column; gap: 2px; }
.ams-field-label {
    font-size: 10px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.8px; color: #64748b;
}
.ams-field-value { font-size: 14px; font-weight: 600; color: #1e293b; }
.ams-field-mono { font-family: 'Consolas', 'Courier New', monospace; letter-spacing: 1px; color: #2563eb; }
.ams-level-tag {
    display: inline-block; font-size: 9.5px; font-weight: 700;
    padding: 2px 8px; border-radius: 10px; margin-left: 6px;
    background: #dbeafe; color: #1e40af; text-transform: uppercase; letter-spacing: 0.3px;
}
.ams-status-active { color: #059669; font-weight: 700; font-size: 13px; }
.ams-status-inactive { color: #dc2626; font-weight: 700; font-size: 13px; }

/* ---- SUMMARY STRIP ---- */
.ams-summary-strip {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0;
    padding: 18px 36px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-bottom: 2px solid #e2e8f0;
}
.ams-summary-item {
    display: flex; flex-direction: column; align-items: center;
    padding: 6px 24px;
}
.ams-summary-label {
    font-size: 9.5px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.7px; color: #64748b;
}
.ams-summary-value {
    font-size: 20px; font-weight: 800; color: #1e293b;
}
.ams-gpa-highlight { color: #2563eb; font-size: 28px; }
.ams-summary-divider { width: 1px; height: 38px; background: #cbd5e1; }
.ams-standing-badge {
    display: inline-block; padding: 3px 10px; border-radius: 12px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.3px; margin-top: 2px;
}
.ams-standing-distinction { background: #dcfce7; color: #166534; }
.ams-standing-merit { background: #dbeafe; color: #1e40af; }
.ams-standing-good { background: #e0f2fe; color: #0c4a6e; }
.ams-standing-satisfactory { background: #fef9c3; color: #854d0e; }
.ams-standing-warning { background: #ffedd5; color: #9a3412; }
.ams-standing-fail { background: #fee2e2; color: #991b1b; }
.ams-standing-neutral { background: #f1f5f9; color: #475569; }

/* ---- GRADE SCALE ---- */
.ams-grade-toggle-area { padding: 0 36px; background: #fff; }
.ams-toggle-btn {
    display: flex; align-items: center; gap: 8px; width: 100%;
    padding: 14px 0; background: none; border: none;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12.5px; font-weight: 600; color: #475569;
    cursor: pointer; text-transform: uppercase; letter-spacing: 0.7px;
    transition: color 0.2s;
}
.ams-toggle-btn:hover { color: #2563eb; }
.ams-chevron { margin-left: auto; transition: transform 0.3s; font-size: 11px; }
.ams-chevron-open { transform: rotate(180deg); }
.ams-collapsed { display: none; }
.ams-grade-panel { padding: 0 36px 16px; background: #fff; overflow-x: auto; }
.ams-grade-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.ams-grade-table th {
    background: #f1f5f9 !important; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.4px; font-size: 10px; color: #475569 !important;
    padding: 8px 12px; text-align: left; border-bottom: 2px solid #cbd5e1;
}
.ams-grade-table td { padding: 6px 12px; border-bottom: 1px solid #e2e8f0; color: #334155; }
.ams-class-badge {
    display: inline-block; padding: 2px 8px; border-radius: 10px;
    font-size: 10.5px; font-weight: 600; letter-spacing: 0.2px;
}
.ams-class-distinction { background: #dcfce7; color: #166534; }
.ams-class-merit { background: #dbeafe; color: #1e40af; }
.ams-class-pass { background: #fef9c3; color: #854d0e; }
.ams-class-marginal { background: #ffedd5; color: #9a3412; }
.ams-class-fail { background: #fee2e2; color: #991b1b; }

/* ---- SEMESTER BLOCKS ---- */
.ams-semester-block { margin: 0; padding: 0; }
.ams-semester-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 36px;
    background: #1e3a5f;
    border-top: 3px solid #2563eb;
}
.ams-semester-header-left { display: flex; align-items: center; gap: 12px; }
.ams-semester-number {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px;
    background: rgba(255,255,255,0.15); border-radius: 50%;
    font-size: 14px; font-weight: 800; color: #fff;
}
.ams-semester-title {
    font-size: 14px; font-weight: 700; color: #fff;
    margin: 0; text-transform: uppercase; letter-spacing: 0.8px;
}
.ams-semester-session { font-size: 11.5px; color: rgba(255,255,255,0.65); letter-spacing: 0.2px; }

/* ---- ACADEMIC TABLE ---- */
.ams-table-wrapper { position: relative; overflow-x: auto; }
.ams-academic-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 680px; }
.ams-academic-table thead th {
    background: #f1f5f9 !important; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.4px; font-size: 10px; color: #475569 !important;
    padding: 10px 10px; text-align: left;
    border-bottom: 2px solid #94a3b8; white-space: nowrap;
}
.ams-col-code { width: 100px; }
.ams-col-title { min-width: 180px; }
.ams-col-type { width: 55px; text-align: center !important; }
.ams-col-num { width: 78px; text-align: center !important; }
.ams-col-grade { width: 78px; text-align: center !important; }

.ams-academic-table tbody td {
    padding: 10px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle;
}
.ams-academic-table tbody tr:nth-child(even) td { background: rgba(248,250,252,0.6); }
.ams-academic-table tbody tr:hover td { background: rgba(239,246,255,0.7); }

.ams-cell-code { font-family: 'Consolas','Courier New',monospace; font-weight: 600; color: #334155; letter-spacing: 0.5px; }
.ams-cell-title { font-weight: 500; color: #1e293b; }
.ams-cell-type { text-align: center; }
.ams-cell-num { text-align: center; font-variant-numeric: tabular-nums; }
.ams-cell-grade { text-align: center; }

.ams-type-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 50%;
    font-size: 10px; font-weight: 800;
}
.ams-type-core { background: #dbeafe; color: #1e40af; }
.ams-type-ur { background: #f3e8ff; color: #6b21a8; }
.ams-type-elective { background: #fef3c7; color: #92400e; }

.ams-grade-pill {
    display: inline-block; padding: 3px 12px; border-radius: 12px;
    font-size: 12px; font-weight: 700; letter-spacing: 0.4px;
}
.ams-grade-pass { background: #dcfce7; color: #166534; }
.ams-grade-fail { background: #fee2e2; color: #991b1b; }

.ams-pending { color: #94a3b8; font-size: 14px; }
.ams-pending-badge {
    display: inline-block; padding: 2px 8px; background: #f1f5f9;
    color: #64748b; border-radius: 8px; font-size: 10px;
    font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;
}
.ams-row-fail td { background: rgba(254,226,226,0.25) !important; }

/* ---- FOOTER ROWS ---- */
.ams-footer-totals td {
    background: #f8fafc !important; border-top: 2px solid #94a3b8;
    border-bottom: 1px solid #cbd5e1; padding: 10px 10px; font-size: 12.5px;
}
.ams-footer-gpa td {
    background: #f0f9ff !important; padding: 10px 10px; font-size: 13px;
}
.ams-gpa-value { color: #2563eb; font-size: 15px !important; }
.ams-standing {
    display: inline-block; margin-left: 8px; padding: 2px 8px; border-radius: 10px;
    font-size: 10px; font-weight: 700; letter-spacing: 0.3px;
    text-transform: uppercase; vertical-align: middle;
}
.ams-standing-distinction { background: #dcfce7; color: #166534; }
.ams-standing-good { background: #dbeafe; color: #1e40af; }
.ams-standing-satisfactory { background: #fef9c3; color: #854d0e; }
.ams-standing-warning { background: #fee2e2; color: #991b1b; }

.ams-footer-stats td {
    background: #fafbfc !important; padding: 8px 10px;
    border-bottom: 2px solid #e2e8f0;
}
.ams-semester-stats {
    display: flex; gap: 16px; flex-wrap: wrap;
    font-size: 11.5px; color: #64748b; font-weight: 500;
}
.ams-semester-stats i { margin-right: 3px; }
.ams-stat-pass { color: #059669; }
.ams-stat-fail { color: #dc2626; font-weight: 700; }

/* ---- CHART SECTION ---- */
.ams-chart-section {
    padding: 28px 36px; border-top: 2px solid #e2e8f0; background: #fff;
}
.ams-section-heading {
    font-size: 15px; font-weight: 700; color: #1e293b;
    text-transform: uppercase; letter-spacing: 0.8px;
    margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.ams-section-heading i { color: #2563eb; }
.ams-chart-controls {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px; margin-bottom: 12px;
    font-size: 12px; color: #64748b;
}
.ams-legend-dot {
    display: inline-block; width: 10px; height: 10px;
    border-radius: 50%; vertical-align: middle; margin-right: 4px;
}

/* Stat Cards */
.ams-stats-row {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-top: 18px;
}
.ams-stat-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; background: #f8fafc;
    border: 1px solid #e2e8f0; border-radius: 10px;
}
.ams-stat-icon {
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; font-size: 18px; flex-shrink: 0;
}
.ams-stat-body { display: flex; flex-direction: column; }
.ams-stat-number { font-size: 18px; font-weight: 800; color: #1e293b; line-height: 1.2; }
.ams-stat-label {
    font-size: 10.5px; color: #64748b; text-transform: uppercase;
    letter-spacing: 0.4px; font-weight: 600;
}

/* Insights */
.ams-insights {
    display: flex; gap: 12px; margin-top: 16px; padding: 16px 18px;
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
    font-size: 13px; color: #78350f;
}
.ams-insights-icon { font-size: 20px; color: #d97706; flex-shrink: 0; }
.ams-insights-body strong { color: #92400e; }
.ams-insights-body ul { margin: 6px 0 0; padding-left: 18px; }
.ams-insights-body li { margin-bottom: 4px; line-height: 1.5; }

/* ---- DOCUMENT FOOTER ---- */
.ams-document-footer {
    display: flex; justify-content: space-between; gap: 24px;
    padding: 20px 36px; background: #f8fafc;
    border-top: 2px solid #e2e8f0; font-size: 11px; color: #64748b;
}
.ams-footer-left { flex: 1; }
.ams-footer-left p {
    margin: 0; padding: 10px 12px;
    background: #eff6ff; border-left: 3px solid #2563eb;
    border-radius: 0 6px 6px 0; color: #1e40af; font-size: 10.5px; line-height: 1.6;
}
.ams-footer-right { text-align: right; flex-shrink: 0; }
.ams-footer-generated { margin: 0 0 2px; font-weight: 600; }
.ams-footer-system { margin: 0; font-size: 10px; opacity: 0.7; }

/* ---- RESPONSIVE ---- */
@media (max-width: 768px) {
    .ams-header-inner { flex-direction: column; text-align: center; padding: 18px 16px; gap: 12px; }
    .ams-title-bar { flex-direction: column; text-align: center; gap: 8px; padding: 10px 16px; }
    .ams-student-panel { padding: 16px; }
    .ams-student-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    .ams-summary-strip { flex-direction: column; padding: 14px 16px; }
    .ams-summary-divider { width: 50px; height: 1px; }
    .ams-semester-header { padding: 12px 16px; }
    .ams-grade-toggle-area { padding: 0 16px; }
    .ams-grade-panel { padding: 0 16px 12px; }
    .ams-chart-section { padding: 18px 16px; }
    .ams-document-footer { flex-direction: column; padding: 16px; gap: 12px; }
    .ams-footer-right { text-align: left; }
    .ams-stats-row { grid-template-columns: 1fr 1fr; }
    .ams-enrollment-switcher { flex-direction: column; text-align: center; }
    .ams-toolbar { flex-direction: column; }
    .ams-toolbar-left, .ams-toolbar-right { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
    .ams-student-grid { grid-template-columns: 1fr; }
    .ams-institution-name { font-size: 15px; }
    .ams-stats-row { grid-template-columns: 1fr; }
}

/* ---- PRINT STYLES ---- */
@media print {
    .ams-toolbar { display: none !important; }
    .ams-enrollment-switcher { display: none !important; }
    .ams-document { box-shadow: none; border-radius: 0; }
    .ams-chart-section { display: none; }
    .ams-header, .ams-semester-header, .ams-admin-badge {
        -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
    }
    .ams-grade-pill, .ams-type-badge, .ams-class-badge, .ams-standing, .ams-standing-badge,
    .ams-footer-totals td, .ams-footer-gpa td, .ams-row-fail td, .ams-summary-strip,
    .ams-footer-stats td, .ams-stat-pass, .ams-stat-fail {
        -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
    }
    .ams-grade-panel { display: block !important; }
    .ams-grade-toggle-area { display: none; }
}
</style>
@endsection

@section('page_js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script type="text/javascript">
'use strict';

@if(isset($gpa_trend) && count($gpa_trend) > 0)
var gpaData = {!! json_encode($gpa_trend) !!};

var labels = gpaData.map(function(item) { return item.label; });
var semesterGPA = gpaData.map(function(item) { return item.semester_gpa; });
var cumulativeGPA = gpaData.map(function(item) { return item.cumulative_gpa; });

var chartConfig = {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Semester GPA',
            data: semesterGPA,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: '#2563eb',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }, {
            label: 'Cumulative GPA',
            data: cumulativeGPA,
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.08)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: '#059669',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2.8,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
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
                        return 'Credits: ' + d.credits;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 4.0,
                ticks: {
                    stepSize: 0.5,
                    font: { size: 11 },
                    color: '#94a3b8',
                    callback: function(v) { return v.toFixed(1); }
                },
                grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                title: { display: true, text: 'GPA (0.0 - 4.0)', font: { size: 12, weight: 'bold' }, color: '#64748b' }
            },
            x: {
                ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 45, minRotation: 45 },
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
