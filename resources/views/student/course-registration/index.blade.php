@extends('student.layouts.master')
@section('title', $title)
@section('page_css')
<link rel="stylesheet" href="{{ asset('dashboard/plugins/select2/css/select2.min.css') }}">
@endsection
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        @if(!$currentEnroll)
                            <div class="alert alert-warning mb-0" role="alert">
                                {{ __('You are not currently enrolled in a semester, so course registration is unavailable.') }}
                            </div>
                        @else
                            {{-- REMOVED: Old CGPA calculation that counted ALL programs
                                 Now using $performanceSummary from controller which filters by current program --}}

                            <div class="row">
                                <div class="col-md-6">
                                    <fieldset class="row gx-2 scheduler-border">
                                        <legend>{{ __('tab_basic_info') }}</legend>
                                        <p><mark class="text-primary">{{ __('field_matricule') }}:</mark> 
                                            <strong style="font-size: 15px; color: #667eea;">#{{ $currentEnroll->matricule ?? $student->student_id }}</strong>
                                            @if($currentEnroll && $currentEnroll->program)
                                                <span class="badge" style="background: {{ $currentEnroll->program->academic_level == 'M' ? '#f5576c' : ($currentEnroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 10px;">
                                                    {{ $currentEnroll->program->academic_level == 'A' ? 'UG' : ($currentEnroll->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            @endif
                                        </p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_name') }}:</mark> {{ $student->first_name }} {{ $student->last_name }}</p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_gender') }}:</mark>
                                            @if($student->gender == 1)
                                                {{ __('gender_male') }}
                                            @elseif($student->gender == 2)
                                                {{ __('gender_female') }}
                                            @elseif($student->gender == 3)
                                                {{ __('gender_other') }}
                                            @endif
                                        </p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_total_credit_hour') }}:</mark> 
                                            {{ isset($performanceSummary) ? number_format($performanceSummary['total_credits_attempted'], 2) : '0.00' }}
                                        </p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_cumulative_gpa') }}:</mark>
                                            {{ isset($performanceSummary) ? number_format($performanceSummary['cgpa'], 2) : '0.00' }}
                                        </p>
                                        <hr/>
                                    </fieldset>
                                </div>
                                <div class="col-md-6">
                                    <fieldset class="row gx-2 scheduler-border">
                                        <legend>{{ __('field_academic_information') }}</legend>
                                        <p><mark class="text-primary">{{ __('field_batch') }}:</mark> {{ $student->batch->title ?? '' }}</p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_program') }}:</mark> {{ $currentEnroll->program->title ?? '' }}</p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_session') }}:</mark> {{ $currentEnroll->session->title ?? '' }}</p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_semester') }}:</mark> {{ $currentEnroll->semester->title ?? '' }}</p>
                                        <hr/>

                                        <p><mark class="text-primary">{{ __('field_section') }}:</mark> {{ $currentEnroll->section->title ?? '' }}</p>
                                        <hr/>
                                    </fieldset>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($currentEnroll && isset($performanceSummary))
                <!-- Performance Summary Section -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> {{ __('Academic Performance Summary') }}</h5>
                        </div>
                        <div class="card-block">
                            <div class="row">
                                <!-- CGPA Card -->
                                <div class="col-md-3">
                                    <div class="card bg-light border-0">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">{{ __('field_cumulative_gpa') }}</h6>
                                            <h2 class="mb-2 
                                                @if($performanceSummary['performance_status'] == 'excellent') text-success
                                                @elseif($performanceSummary['performance_status'] == 'very_good') text-info
                                                @elseif($performanceSummary['performance_status'] == 'good') text-primary
                                                @elseif($performanceSummary['performance_status'] == 'satisfactory') text-warning
                                                @else text-danger
                                                @endif">
                                                {{ number_format($performanceSummary['cgpa'], 2) }}
                                            </h2>
                                            <span class="badge 
                                                @if($performanceSummary['performance_status'] == 'excellent') bg-success
                                                @elseif($performanceSummary['performance_status'] == 'very_good') bg-info
                                                @elseif($performanceSummary['performance_status'] == 'good') bg-primary
                                                @elseif($performanceSummary['performance_status'] == 'satisfactory') bg-warning
                                                @else bg-danger
                                                @endif">
                                                @if($performanceSummary['performance_status'] == 'excellent') {{ __('Excellent') }}
                                                @elseif($performanceSummary['performance_status'] == 'very_good') {{ __('Very Good') }}
                                                @elseif($performanceSummary['performance_status'] == 'good') {{ __('Good') }}
                                                @elseif($performanceSummary['performance_status'] == 'satisfactory') {{ __('Satisfactory') }}
                                                @else {{ __('Needs Improvement') }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Credits Card -->
                                <div class="col-md-3">
                                    <div class="card bg-light border-0">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">{{ __('Credits Progress') }}</h6>
                                            <h3 class="mb-2 text-info">{{ number_format($performanceSummary['total_credits_earned'], 1) }}</h3>
                                            <small class="text-muted">{{ __('of') }} {{ number_format($performanceSummary['total_credits_attempted'], 1) }} {{ __('attempted') }}</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Course Completion Card -->
                                <div class="col-md-3">
                                    <div class="card bg-light border-0">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">{{ __('Course Completion') }}</h6>
                                            <h3 class="mb-2 text-success">{{ number_format($performanceSummary['completion_percentage'], 1) }}%</h3>
                                            <small class="text-muted">{{ $performanceSummary['passed_courses'] }}/{{ $performanceSummary['total_courses'] }} {{ __('courses') }}</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Graduation Status Card -->
                                <div class="col-md-3">
                                    <div class="card bg-light border-0">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">{{ __('Graduation Status') }}</h6>
                                            @if($performanceSummary['graduation_ready'])
                                                <h3 class="mb-2 text-success"><i class="fas fa-check-circle"></i></h3>
                                                <span class="badge bg-success">{{ __('Ready') }}</span>
                                            @else
                                                <h3 class="mb-2 text-warning"><i class="fas fa-clock"></i></h3>
                                                <span class="badge bg-warning">{{ __('In Progress') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detailed Breakdown -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="alert alert-info" role="alert">
                                        <h6 class="alert-heading"><i class="fas fa-info-circle"></i> {{ __('Course Type Breakdown') }}</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>{{ __('subject_type_compulsory') }}:</strong>
                                                <span class="badge bg-{{ $performanceSummary['compulsory_passed'] == $performanceSummary['compulsory_total'] ? 'success' : 'warning' }}">
                                                    {{ $performanceSummary['compulsory_passed'] }}/{{ $performanceSummary['compulsory_total'] }}
                                                </span>
                                                @if(($performanceSummary['compulsory_failed'] ?? 0) > 0)
                                                    <small class="text-danger">({{ $performanceSummary['compulsory_failed'] }} {{ __('failed') }})</small>
                                                @endif
                                                @if(($performanceSummary['compulsory_missing'] ?? 0) > 0)
                                                    <small class="text-warning">({{ $performanceSummary['compulsory_missing'] }} {{ __('not attempted') }})</small>
                                                @endif
                                            </div>
                                            <div class="col-md-4">
                                                <strong>{{ __('subject_type_university_requirement') }}:</strong>
                                                <span class="badge bg-{{ $performanceSummary['university_req_passed'] == $performanceSummary['university_req_total'] ? 'success' : 'warning' }}">
                                                    {{ $performanceSummary['university_req_passed'] }}/{{ $performanceSummary['university_req_total'] }}
                                                </span>
                                                @if(($performanceSummary['university_req_failed'] ?? 0) > 0)
                                                    <small class="text-danger">({{ $performanceSummary['university_req_failed'] }} {{ __('failed') }})</small>
                                                @endif
                                                @if(($performanceSummary['university_req_missing'] ?? 0) > 0)
                                                    <small class="text-warning">({{ $performanceSummary['university_req_missing'] }} {{ __('not attempted') }})</small>
                                                @endif
                                            </div>
                                            <div class="col-md-4">
                                                <strong>{{ __('subject_type_optional') }}:</strong>
                                                <span class="badge bg-primary">
                                                    {{ $performanceSummary['optional_passed'] }}/{{ $performanceSummary['optional_total'] }}
                                                </span>
                                                @if(($performanceSummary['optional_failed'] ?? 0) > 0)
                                                    <small class="text-danger">({{ $performanceSummary['optional_failed'] }} {{ __('failed') }})</small>
                                                @endif
                                                @if(($performanceSummary['optional_missing'] ?? 0) > 0)
                                                    <small class="text-warning">({{ $performanceSummary['optional_missing'] }} {{ __('not attempted') }})</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Performance Feedback -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert 
                                        @if($performanceSummary['performance_status'] == 'excellent' || $performanceSummary['performance_status'] == 'very_good') alert-success
                                        @elseif($performanceSummary['performance_status'] == 'good') alert-primary
                                        @elseif($performanceSummary['performance_status'] == 'satisfactory') alert-warning
                                        @else alert-danger
                                        @endif" role="alert">
                                        <h6 class="alert-heading"><i class="fas fa-lightbulb"></i> {{ __('Performance Feedback') }}</h6>
                                        <ul class="mb-0">
                                            @if($performanceSummary['performance_status'] == 'excellent')
                                                <li>{{ __('Outstanding performance! You are maintaining an excellent CGPA. Keep up the excellent work!') }}</li>
                                            @elseif($performanceSummary['performance_status'] == 'very_good')
                                                <li>{{ __('Very good performance! You are doing well academically. Continue your efforts!') }}</li>
                                            @elseif($performanceSummary['performance_status'] == 'good')
                                                <li>{{ __('Good performance. You are on the right track. Consider ways to improve further.') }}</li>
                                            @elseif($performanceSummary['performance_status'] == 'satisfactory')
                                                <li>{{ __('Satisfactory performance. Focus on improving your grades in upcoming semesters.') }}</li>
                                            @else
                                                <li>{{ __('Your performance needs improvement. Please seek academic advising and support to enhance your grades.') }}</li>
                                            @endif
                                            
                                            @if($performanceSummary['failed_courses'] > 0)
                                                <li class="text-danger">
                                                    <strong>{{ __('Important:') }}</strong> {{ __('You have') }} {{ $performanceSummary['failed_courses'] }} {{ __('unvalidated course(s) (below 50%). These courses must be retaken and validated for graduation.') }}
                                                </li>
                                            @endif

                                            @if(!$performanceSummary['graduation_ready'])
                                                <li>
                                                    <strong>{{ __('Graduation Status:') }}</strong> {{ __('Not eligible yet') }}
                                                    @if(isset($performanceSummary['graduation_reasons']) && !empty($performanceSummary['graduation_reasons']))
                                                        <ul class="mt-2">
                                                            @foreach($performanceSummary['graduation_reasons'] as $reason)
                                                                <li>{{ $reason }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </li>
                                            @else
                                                <li class="text-success">
                                                    <strong>{{ __('Congratulations!') }}</strong> {{ __('You have validated all required courses and are eligible for graduation.') }}
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carry-Over Courses Section -->
                @if(isset($carryOverCourses) && count($carryOverCourses) > 0)
                <div class="col-sm-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> {{ __('Carry-Over Courses (Courses to Retake)') }}</h5>
                            <small>{{ __('These courses from previous semesters with the same semester type need to be retaken and validated (≥50%) for graduation.') }}</small>
                        </div>
                        <div class="card-block">
                            <div class="alert alert-warning" role="alert">
                                <strong><i class="fas fa-info-circle"></i> {{ __('What are Carry-Over Courses?') }}</strong>
                                <p class="mb-0">{{ __('Carry-over courses are courses from the same semester type (First Semester or Second Semester) that you attempted in previous years but did not validate (scored less than 50%). You should register these courses again this semester to improve your grades and work towards graduation.') }}</p>
                            </div>

                            <div class="table-responsive">
                                <table class="display table table-striped table-bordered">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>{{ __('field_code') }}</th>
                                            <th>{{ __('field_subject') }}</th>
                                            <th>{{ __('field_subject_type') }}</th>
                                            <th>{{ __('field_credit_hour') }}</th>
                                            <th>{{ __('Best Score') }}</th>
                                            <th>{{ __('field_grade') }}</th>
                                            <th>{{ __('Attempts') }}</th>
                                            <th>{{ __('Last Taken') }}</th>
                                            <th>{{ __('field_status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($carryOverCourses as $course)
                                        <tr class="
                                            @if($course['subject_type'] == 1) table-danger
                                            @elseif($course['subject_type'] == 2) table-warning
                                            @endif">
                                            <td><strong>{{ $course['subject']->code }}</strong></td>
                                            <td>{{ $course['subject']->title }}</td>
                                            <td>
                                                @if($course['subject_type'] == 1)
                                                    <span class="badge bg-danger">{{ __('subject_type_compulsory') }}</span>
                                                @elseif($course['subject_type'] == 2)
                                                    <span class="badge bg-warning">{{ __('subject_type_university_requirement') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ __('subject_type_optional') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ number_format($course['credit_hours'], 1) }}</td>
                                            <td>
                                                <span class="badge bg-danger">{{ number_format($course['best_marks'], 1) }}%</span>
                                            </td>
                                            <td>{{ $course['grade'] }}</td>
                                            <td>
                                                <span class="badge bg-info">{{ $course['attempts'] }}x</span>
                                            </td>
                                            <td>
                                                <small>{{ $course['session_title'] }}<br/>{{ $course['semester_title'] }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">{{ __('Not Validated') }}</span>
                                                @if($course['subject_type'] == 1 || $course['subject_type'] == 2)
                                                    <br/><small class="text-danger"><strong>{{ __('Required for Graduation') }}</strong></small>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-light">
                                            <td colspan="9">
                                                <strong>{{ __('Total Carry-Over Courses:') }}</strong> {{ count($carryOverCourses) }}
                                                <span class="ms-3">
                                                    <span class="badge bg-danger">{{ __('Compulsory:') }} {{ collect($carryOverCourses)->where('subject_type', 1)->count() }}</span>
                                                    <span class="badge bg-warning">{{ __('University Req:') }} {{ collect($carryOverCourses)->where('subject_type', 2)->count() }}</span>
                                                    <span class="badge bg-secondary">{{ __('Optional:') }} {{ collect($carryOverCourses)->where('subject_type', 0)->count() }}</span>
                                                </span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="alert alert-info mt-3" role="alert">
                                <h6 class="alert-heading"><i class="fas fa-graduation-cap"></i> {{ __('Action Required') }}</h6>
                                <ul class="mb-0">
                                    <li>{{ __('Register these courses in the "Course Registration" section below to retake them this semester.') }}</li>
                                    <li>{{ __('Focus especially on compulsory and university requirement courses as they are mandatory for graduation.') }}</li>
                                    <li>{{ __('Aim to score at least 50% to validate each course.') }}</li>
                                    <li>{{ __('Courses marked in red (Compulsory) and orange (University Requirement) are critical priorities.') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            @endif

            @if($currentEnroll)
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('status_current') }} {{ __('field_session') }}: {{ $currentEnroll->session->title ?? '' }} | {{ $currentEnroll->semester->title ?? '' }} | {{ $currentEnroll->section->title ?? '' }}</h5>
                        </div>
                        <div class="card-block">
                            <div class="table-responsive">
                                <table class="display table table-striped">
                                    <thead>
                                        <tr>
                                            <th>{{ __('field_code') }}</th>
                                            <th>{{ __('field_subject') }}</th>
                                            <th>{{ __('field_credit_hour') }}</th>
                                            <th>{{ __('field_point') }}</th>
                                            <th>{{ __('field_grade') }}</th>
                                            <th>{{ __('field_action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $semesterCredits = 0;
                                            $semesterCgpa = 0;
                                        @endphp
                                        @forelse($currentEnroll->subjects ?? collect() as $subject)
                                            @php
                                                $creditHour = (float) ($subject->credit_hour ?? 0);
                                                $semesterCredits += $creditHour;
                                                $subjectGrade = null;
                                                $subjectPoint = null;
                                                $hasMarks = false;
                                            @endphp
                                            <tr>
                                                <td>{{ $subject->code }}</td>
                                                <td>
                                                    {{ $subject->title }}
                                                    @if($subject->subject_type == 0)
                                                        ({{ __('subject_type_optional') }})
                                                    @elseif($subject->subject_type == 2)
                                                        ({{ __('subject_type_university_requirement') }})
                                                    @endif
                                                </td>
                                                <td>{{ number_format($creditHour, 2) }}</td>
                                                <td>
                                                    @if(isset($currentEnroll->subjectMarks))
                                                        @foreach($currentEnroll->subjectMarks as $mark)
                                                            @if($mark->subject_id == $subject->id)
                                                                @php
                                                                    // Check if mark is published (visible to student + publish date/time reached)
                                                                    $publishDate = date('Y-m-d', strtotime($mark->publish_date));
                                                                    $publishTime = date('H:i:s', strtotime($mark->publish_time));
                                                                    $currentDate = date('Y-m-d');
                                                                    $currentTime = date('H:i:s');
                                                                    $isPublished = $mark->is_visible_to_student && 
                                                                        (($publishDate == $currentDate && $publishTime <= $currentTime) || $publishDate < $currentDate);
                                                                    
                                                                    // Only set hasMarks if the mark is published
                                                                    if($isPublished) {
                                                                        $hasMarks = true;
                                                                        $marksPer = round($mark->total_marks);
                                                                    }
                                                                @endphp
                                                                @if($isPublished)
                                                                    @foreach($grades as $grade)
                                                                        @if($marksPer >= $grade->min_mark && $marksPer <= $grade->max_mark)
                                                                            @php
                                                                                $subjectPoint = $grade->point * $creditHour;
                                                                                $semesterCgpa += $subjectPoint;
                                                                                $subjectGrade = $grade->title;
                                                                            @endphp
                                                                            {{ number_format((float) $subjectPoint, 2, '.', '') }}
                                                                            @break
                                                                        @endif
                                                                    @endforeach
                                                                @endif
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </td>
                                                <td>{{ $subjectGrade ?? '' }}</td>
                                                <td>
                                                    @if($hasMarks)
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="{{ __('Cannot drop: Marks have been submitted') }}">
                                                            <i class="fas fa-lock"></i> {{ __('Locked') }}
                                                        </button>
                                                    @elseif($isResitSemester)
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="{{ __('Cannot drop courses during a resit semester') }}">
                                                            <i class="fas fa-lock"></i> {{ __('Locked') }}
                                                        </button>
                                                    @elseif(($subject->subject_type == 1 || $subject->subject_type == 2) && in_array($subject->id, $currentSemesterEnrolledSubjectIds))
                                                        @php
                                                            $subjectTypeName = $subject->subject_type == 1 ? __('subject_type_compulsory') : __('subject_type_university_requirement');
                                                        @endphp
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="{{ __('Cannot drop:') }} {{ $subjectTypeName }} {{ __('for this semester') }}">
                                                            <i class="fas fa-ban"></i> {{ __('Required') }}
                                                        </button>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-outline-danger btn-drop-subject" data-subject="{{ $subject->id }}" data-title="{{ $subject->code }} - {{ $subject->title }}">
                                                            <i class="fas fa-minus-circle"></i> {{ __('Drop') }}
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center">{{ __('no_result_found') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2">{{ __('field_term_total') }}</th>
                                            <th>{{ number_format((float) $semesterCredits, 2, '.', '') }}</th>
                                            <th>{{ number_format((float) $semesterCgpa, 2, '.', '') }}</th>
                                            <th></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('field_assign') }} {{ __('field_subject') }}</h5>
                        </div>
                        <div class="card-block">
                            @if($isResitSemester)
                                <div class="alert alert-warning mb-0" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>{{ __('Resit Semester') }}</strong><br>
                                    {{ __('Course registration is not available during resit semesters. Students cannot register courses by themselves during this period.') }}
                                </div>
                            @else
                                @php
                                    $hasAssignableSubjects = isset($assignableSubjects) && $assignableSubjects->isNotEmpty();
                                    $prefilledSubjects = collect(old('subjects', []))->map(fn ($id) => (string) $id)->toArray();
                                @endphp
                                @if(!is_null($maxCreditLimit))
                                    <div class="alert alert-warning">
                                        <i class="fas fa-info-circle"></i> {{ __('You may register up to :limit credits this semester.', ['limit' => $maxCreditLimit]) }}
                                    </div>
                                @endif
                                @if(!$hasAssignableSubjects)
                                    <div class="alert alert-info mb-0" role="alert">
                                        <i class="fas fa-info-circle"></i> 
                                        {{ __('No subjects are currently available for registration. This may be because:') }}
                                        <ul class="mb-0 mt-2">
                                            <li>{{ __('You have already validated all available courses (marks ≥ 50%)') }}</li>
                                            <li>{{ __('You are already registered for all available courses') }}</li>
                                            <li>{{ __('No courses have been assigned to your current semester and section') }}</li>
                                        </ul>
                                    </div>
                                @else
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>{{ __('Available Courses') }}:</strong><br>
                                        {{ __('Showing courses from your current semester and previous years of the same semester type that you have not yet validated (marks < 50%).') }}
                                    </div>
                                    <form class="needs-validation" novalidate action="{{ route($route . '.update') }}" method="post">
                                        @csrf
                                        <div class="row">
                                            <div class="form-group col-md-12">
                                                <label for="subjects">{{ __('field_subject') }} <span>* ({{ __('select_multiple') }})</span></label>
                                                <select class="form-control select2-multiple" name="subjects[]" id="subjects" multiple required>
                                                    @foreach($assignableSubjects as $subject)
                                                        <option value="{{ $subject->id }}" @if(in_array((string) $subject->id, $prefilledSubjects, true)) selected @endif>
                                                            {{ $subject->code }} - {{ $subject->title }}
                                                            @if($subject->subject_type == 0)
                                                                ({{ __('Optional') }})
                                                            @elseif($subject->subject_type == 1)
                                                                ({{ __('Compulsory') }})
                                                            @elseif($subject->subject_type == 2)
                                                                ({{ __('University Requirement') }})
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <div class="invalid-feedback">
                                                    {{ __('required_field') }} {{ __('field_subject') }}
                                                </div>
                                                @error('subjects')
                                                    <small class="text-danger d-block">{{ $message }}</small>
                                                @enderror
                                            </div>

                                            <div class="form-group col-md-12">
                                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmModal">
                                                    <i class="fas fa-check"></i> {{ __('Assign') }}
                                                </button>
                                                @include('student.course-registration.confirm')
                                            </div>
                                        </div>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                <form method="post" action="{{ route($route . '.drop') }}" id="drop-subject-form">
                    @csrf
                    <input type="hidden" name="subject_id" id="drop-subject-id" value="">
                </form>
                @include('student.course-registration.drop-confirm')
            @endif
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/select2/js/select2.full.min.js') }}"></script>
<script type="text/javascript">
"use strict";
(function($){
    if(typeof $ === 'undefined'){
        return;
    }

    $(function(){
        const $subjectSelect = $('#subjects');
        if($subjectSelect.length && typeof $.fn.select2 !== 'undefined'){
            $subjectSelect.select2({
                width: '100%',
                placeholder: "{{ __('Select subjects to assign') }}",
                closeOnSelect: false,
                allowClear: true
            });
        }

        const $modal = $('#dropConfirmModal');
        const $subjectInput = $('#drop-subject-id');
        const $subjectLabel = $('#drop-subject-label');

        $('.btn-drop-subject').on('click', function(e){
            e.preventDefault();
            const subjectId = $(this).data('subject');
            const subjectTitle = $(this).data('title');
            $subjectInput.val(subjectId);
            $subjectLabel.text(subjectTitle);
            $modal.modal('show');
        });

        $modal.on('hidden.bs.modal', function(){
            $subjectInput.val('');
            $subjectLabel.text('');
        });

        $('#confirm-drop-button').on('click', function(){
            if(!$subjectInput.val()){
                return;
            }
            $('#drop-subject-form').trigger('submit');
        });
    });
})(window.jQuery);
</script>
@endsection
