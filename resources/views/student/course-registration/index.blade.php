@extends('student.layouts.master')
@section('title', $title)
@section('page_css')
<link rel="stylesheet" href="{{ asset('dashboard/css/pages/course-registration.css') }}">
@endsection
@section('content')

@php
    $registrationDeadline = $registrationStatus['deadline'] ?? null;
    $registrationClosed = isset($registrationStatus) && $registrationStatus['state'] === 'closed';

    // One tone per performance band, used by the CGPA tile, its pill and the
    // feedback note, so the three always agree.
    $performanceTones = [
        'excellent'    => 'success',
        'very_good'    => 'info',
        'good'         => 'primary',
        'satisfactory' => 'warning',
    ];
    $performanceLabels = [
        'excellent'    => __('Excellent'),
        'very_good'    => __('Very Good'),
        'good'         => __('Good'),
        'satisfactory' => __('Satisfactory'),
    ];
    $performanceTone = isset($performanceSummary)
        ? ($performanceTones[$performanceSummary['performance_status']] ?? 'danger')
        : 'neutral';
    $performanceLabel = isset($performanceSummary)
        ? ($performanceLabels[$performanceSummary['performance_status']] ?? __('Needs Improvement'))
        : '';
@endphp

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper cr-page">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">

                        {{-- Where the student stands, before anything else: whether
                             there is still something to register and how long they
                             have. These stay visible; only the guide folds away. --}}
                        @if(isset($registrationStatus) && in_array($registrationStatus['state'], ['open', 'partly_done'], true))
                            <div class="cr-note tone-info">
                                <i class="fas fa-clipboard-list"></i>
                                <div class="cr-note-body">
                                    <strong>{{ $registrationStatus['headline'] }}</strong>
                                    {{ $registrationStatus['detail'] }}
                                </div>
                            </div>
                        @endif

                        @if($registrationDeadline && !$registrationClosed)
                            {{-- The deadline, in the colour of how little time is left. --}}
                            <div class="cr-note tone-{{ $registrationDeadline->colour() }}">
                                <i class="fas fa-hourglass-half"></i>
                                <div class="cr-note-body">
                                    <strong>{{ __('Register before :when', ['when' => $registrationDeadline->closesAtLong()]) }}</strong>
                                    <span class="cr-pill tone-{{ $registrationDeadline->colour() }} ms-1">{{ $registrationDeadline->countdown() }}</span>
                                    <div class="mt-1">{{ __('After that moment you can neither register nor drop courses here. Only your department can change your list.') }}</div>
                                </div>
                            </div>
                        @endif

                        {{-- How to do this.

                             Written for a student who has never registered
                             courses before and does not know what "assign" or
                             "carry-over" mean. The opening sentence says what
                             the page is for and what doing nothing costs; the
                             steps, the labels and the troubleshooting fold out
                             for whoever wants them. --}}
                        <div class="cr-help {{ (isset($registrationStatus) && in_array($registrationStatus['state'], ['open', 'partly_done'], true)) || ($registrationDeadline && !$registrationClosed) ? 'mt-3' : '' }}" role="note">
                            <h6 class="cr-help-title">
                                <i class="fas fa-question-circle"></i>
                                {{ __('How to register your courses') }}
                            </h6>
                            <p class="cr-help-lead">
                                {{ __('Registering tells the school which classes you are taking this semester. Until you do it, you will not appear on the class list or the exam list for those courses, and no marks can be recorded for you.') }}
                            </p>
                            <button class="cr-help-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#cr-help-more" aria-expanded="false" aria-controls="cr-help-more">
                                <span class="when-closed">{{ __('Read the full guide') }}</span>
                                <span class="when-open">{{ __('Show less') }}</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>

                            <div class="collapse" id="cr-help-more">
                                <div class="cr-help-more">
                                    <p class="cr-section-title">{{ __('Steps') }}</p>
                                    <ol class="cr-steps">
                                        <li>
                                            {{ __('Look at') }} <strong>{{ __('My Courses') }}</strong>
                                            {{ __('further down this page. Those are the courses you are already registered for. If a course is missing from it, you still need to register it.') }}
                                        </li>
                                        <li>
                                            {{ __('In') }} <strong>{{ __('Choose your courses') }}</strong>,
                                            {{ __('click the box and pick every course you want to take. You can pick several at once; click each one you want.') }}
                                        </li>
                                        <li>
                                            {{ __('Check the list you have picked, then click') }}
                                            <strong>{{ __('Register these courses') }}</strong>.
                                            {{ __('You will be asked to confirm before anything is saved.') }}
                                        </li>
                                        <li>
                                            {{ __('The courses then appear under') }} <strong>{{ __('My Courses') }}</strong>.
                                            {{ __('That is how you know it worked.') }}
                                        </li>
                                    </ol>

                                    <p class="cr-section-title">{{ __('What the labels mean') }}</p>
                                    <dl class="cr-terms">
                                        <div><dt>{{ __('Compulsory') }}</dt><dd>{{ __('you must take it to complete your programme.') }}</dd></div>
                                        <div><dt>{{ __('University Requirement') }}</dt><dd>{{ __('required of every student, whatever the programme.') }}</dd></div>
                                        <div><dt>{{ __('Optional') }}</dt><dd>{{ __('you choose whether to take it.') }}</dd></div>
                                        <div><dt>{{ __('Carry-over') }}</dt><dd>{{ __('a course from an earlier year that you have not passed yet. Register it again to retake it.') }}</dd></div>
                                    </dl>

                                    <p class="cr-section-title">{{ __('If something looks wrong') }}</p>
                                    <ul class="cr-bullets">
                                        <li>{{ __('Nothing in the list to choose from? Either you are already registered for everything open to you, or the school has not set the courses for your semester yet. Speak to your department.') }}</li>
                                        <li>{{ __('Registered the wrong course? Use the Drop button beside it in My Courses, as long as no marks have been recorded yet and the registration deadline has not passed.') }}</li>
                                        @if(!is_null($maxCreditLimit))
                                        <li>{{ __('You may register up to :limit credits this semester. Picking more than that will be refused.', ['limit' => $maxCreditLimit]) }}</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>

                        @if(!$currentEnroll)
                            <div class="cr-note tone-warning mt-3" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="cr-note-body">
                                    {{ __('You are not currently enrolled in a semester, so course registration is unavailable.') }}
                                </div>
                            </div>
                        @else
                            {{-- REMOVED: Old CGPA calculation that counted ALL programs
                                 Now using $performanceSummary from controller which filters by current program --}}

                            <div class="row g-3 mt-1">
                                <div class="col-lg-6">
                                    <div class="cr-facts">
                                        <p class="cr-section-title">{{ __('tab_basic_info') }}</p>
                                        <dl>
                                            <div>
                                                <dt>{{ __('field_matricule') }}</dt>
                                                <dd>
                                                    #{{ $currentEnroll->matricule ?? $student->student_id }}
                                                    @if($currentEnroll && $currentEnroll->program)
                                                        <span class="cr-pill tone-primary">
                                                            {{ $currentEnroll->program->academic_level == 'A' ? 'UG' : ($currentEnroll->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                        </span>
                                                    @endif
                                                </dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_name') }}</dt>
                                                <dd>{{ $student->first_name }} {{ $student->last_name }}</dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_gender') }}</dt>
                                                <dd>
                                                    @if($student->gender == 1)
                                                        {{ __('gender_male') }}
                                                    @elseif($student->gender == 2)
                                                        {{ __('gender_female') }}
                                                    @elseif($student->gender == 3)
                                                        {{ __('gender_other') }}
                                                    @endif
                                                </dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_total_credit_hour') }}</dt>
                                                <dd>{{ isset($performanceSummary) ? number_format($performanceSummary['total_credits_attempted'], 2) : '0.00' }}</dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_cumulative_gpa') }}</dt>
                                                <dd>{{ isset($performanceSummary) ? number_format($performanceSummary['cgpa'], 2) : '0.00' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="cr-facts">
                                        <p class="cr-section-title">{{ __('field_academic_information') }}</p>
                                        <dl>
                                            <div>
                                                <dt>{{ __('field_batch') }}</dt>
                                                <dd>{{ $student->batch->title ?? '' }}</dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_program') }}</dt>
                                                <dd>{{ $currentEnroll->program->title ?? '' }}</dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_session') }}</dt>
                                                <dd>{{ $currentEnroll->session->title ?? '' }}</dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_semester') }}</dt>
                                                <dd>{{ $currentEnroll->semester->title ?? '' }}</dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('field_section') }}</dt>
                                                <dd>{{ $currentEnroll->section->title ?? '' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
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
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line"></i> {{ __('Academic Performance Summary') }}</h5>
                        </div>
                        <div class="card-block">
                            <div class="row cr-stats">
                                <!-- CGPA -->
                                <div class="col-6 col-xl-3">
                                    <div class="cr-stat">
                                        <p class="cr-stat-title">{{ __('field_cumulative_gpa') }}</p>
                                        <p class="cr-stat-value tone-{{ $performanceTone }}">{{ number_format($performanceSummary['cgpa'], 2) }}</p>
                                        <div class="cr-stat-foot">
                                            <span class="cr-pill tone-{{ $performanceTone }}">{{ $performanceLabel }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Credits -->
                                <div class="col-6 col-xl-3">
                                    @php
                                        $creditsShare = $performanceSummary['total_credits_attempted'] > 0
                                            ? min(100, $performanceSummary['total_credits_earned'] / $performanceSummary['total_credits_attempted'] * 100)
                                            : 0;
                                    @endphp
                                    <div class="cr-stat">
                                        <p class="cr-stat-title">{{ __('Credits Progress') }}</p>
                                        <p class="cr-stat-value">{{ number_format($performanceSummary['total_credits_earned'], 1) }}</p>
                                        <div class="cr-stat-foot">
                                            {{ __('of') }} {{ number_format($performanceSummary['total_credits_attempted'], 1) }} {{ __('attempted') }}
                                            <div class="cr-progress"><span style="width: {{ round($creditsShare, 1) }}%"></span></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Course completion -->
                                <div class="col-6 col-xl-3">
                                    <div class="cr-stat">
                                        <p class="cr-stat-title">{{ __('Course Completion') }}</p>
                                        <p class="cr-stat-value">{{ number_format($performanceSummary['completion_percentage'], 1) }}%</p>
                                        <div class="cr-stat-foot">
                                            {{ $performanceSummary['passed_courses'] }}/{{ $performanceSummary['total_courses'] }} {{ __('courses') }}
                                            <div class="cr-progress"><span style="width: {{ min(100, max(0, round($performanceSummary['completion_percentage'], 1))) }}%"></span></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Graduation status -->
                                <div class="col-6 col-xl-3">
                                    <div class="cr-stat">
                                        <p class="cr-stat-title">{{ __('Graduation Status') }}</p>
                                        @if($performanceSummary['graduation_ready'])
                                            <p class="cr-stat-value tone-success"><i class="fas fa-check-circle"></i></p>
                                            <div class="cr-stat-foot"><span class="cr-pill tone-success">{{ __('Ready') }}</span></div>
                                        @else
                                            <p class="cr-stat-value tone-warning"><i class="fas fa-clock"></i></p>
                                            <div class="cr-stat-foot"><span class="cr-pill tone-warning">{{ __('In Progress') }}</span></div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Course type breakdown -->
                            <div class="cr-section">
                                <p class="cr-section-title">{{ __('Course Type Breakdown') }}</p>
                                <div class="cr-types">
                                    @foreach([
                                        ['label' => __('subject_type_compulsory'), 'key' => 'compulsory', 'strict' => true],
                                        ['label' => __('subject_type_university_requirement'), 'key' => 'university_req', 'strict' => true],
                                        ['label' => __('subject_type_optional'), 'key' => 'optional', 'strict' => false],
                                    ] as $type)
                                        @php
                                            $passed  = $performanceSummary[$type['key'] . '_passed'];
                                            $total   = $performanceSummary[$type['key'] . '_total'];
                                            $failed  = $performanceSummary[$type['key'] . '_failed'] ?? 0;
                                            $missing = $performanceSummary[$type['key'] . '_missing'] ?? 0;
                                            $typeTone = !$type['strict'] ? 'primary' : ($passed == $total ? 'success' : 'warning');
                                        @endphp
                                        <div class="cr-type">
                                            <div class="cr-type-head">
                                                <span>{{ $type['label'] }}</span>
                                                <span class="cr-pill tone-{{ $typeTone }}">{{ $passed }}/{{ $total }}</span>
                                            </div>
                                            <div class="cr-type-notes">
                                                @if($failed > 0)
                                                    <span class="cr-text-danger">{{ $failed }} {{ __('failed') }}</span>
                                                @endif
                                                @if($missing > 0)
                                                    <span class="cr-text-warning">{{ $missing }} {{ __('not attempted') }}</span>
                                                @endif
                                                @if($failed == 0 && $missing == 0)
                                                    <span>{{ __('validated') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Performance feedback -->
                            <div class="cr-section">
                                <p class="cr-section-title">{{ __('Performance Feedback') }}</p>
                                <div class="cr-note tone-{{ $performanceTone === 'info' ? 'success' : $performanceTone }}" role="alert">
                                    <i class="fas fa-lightbulb"></i>
                                    <div class="cr-note-body">
                                        <ul class="mb-0 mt-0">
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
                                                <li class="cr-text-danger">
                                                    <strong>{{ __('Important:') }}</strong> {{ __('You have') }} {{ $performanceSummary['failed_courses'] }} {{ __('unvalidated course(s) (below 50%). These courses must be retaken and validated for graduation.') }}
                                                </li>
                                            @endif

                                            @if(!$performanceSummary['graduation_ready'])
                                                <li>
                                                    <strong>{{ __('Graduation Status:') }}</strong> {{ __('Not eligible yet') }}
                                                    @if(isset($performanceSummary['graduation_reasons']) && !empty($performanceSummary['graduation_reasons']))
                                                        <ul>
                                                            @foreach($performanceSummary['graduation_reasons'] as $reason)
                                                                <li>{{ $reason }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </li>
                                            @else
                                                <li class="cr-text-success">
                                                    <strong>{{ __('Congratulations!') }}</strong> {{ __('You have validated all required courses and are eligible for graduation.') }}
                                                </li>

                                                {{-- While the next academic year is still being set up, "all
                                                     required courses" means every course that exists so far.
                                                     Saying so stops a student reading this as the end of their
                                                     programme when their remaining courses simply have not been
                                                     configured yet. --}}
                                                @if(!empty($progressionPaused))
                                                    <li class="cr-text-warning">
                                                        <i class="fas fa-info-circle"></i>
                                                        <strong>{{ __('Not final yet.') }}</strong>
                                                        {{ __('Courses for the next academic year are still being set up, so this is based only on the courses published so far. It may change once they are added.') }}
                                                    </li>
                                                @endif
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
                @php
                    $carryOver = collect($carryOverCourses);
                @endphp
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-redo-alt"></i> {{ __('Carry-Over Courses (Courses to Retake)') }}</h5>
                            <span class="cr-pill tone-warning">{{ count($carryOverCourses) }}</span>
                            <p class="cr-sub">{{ __('These courses from previous semesters with the same semester type need to be retaken and validated (≥50%) for graduation.') }}</p>
                        </div>
                        <div class="card-block">
                            <div class="cr-note tone-warning mb-3">
                                <i class="fas fa-info-circle"></i>
                                <div class="cr-note-body">
                                    <span class="cr-note-title">{{ __('What are Carry-Over Courses?') }}</span>
                                    {{ __('Carry-over courses are courses from the same semester type (First Semester or Second Semester) that you attempted in previous years but did not validate (scored less than 50%). You should register these courses again this semester to improve your grades and work towards graduation.') }}
                                </div>
                            </div>

                            <div class="cr-table-flush">
                                <div class="table-responsive">
                                    <table class="table cr-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('field_code') }}</th>
                                                <th>{{ __('field_subject') }}</th>
                                                <th>{{ __('field_subject_type') }}</th>
                                                <th class="cr-num">{{ __('field_credit_hour') }}</th>
                                                <th class="cr-num">{{ __('Best Score') }}</th>
                                                <th>{{ __('field_grade') }}</th>
                                                <th class="cr-num">{{ __('Attempts') }}</th>
                                                <th>{{ __('Last Taken') }}</th>
                                                <th>{{ __('field_status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($carryOverCourses as $course)
                                            <tr>
                                                <td class="cr-code">{{ $course['subject']->code }}</td>
                                                <td>{{ $course['subject']->title }}</td>
                                                <td>
                                                    @if($course['subject_type'] == 1)
                                                        <span class="cr-pill tone-danger">{{ __('subject_type_compulsory') }}</span>
                                                    @elseif($course['subject_type'] == 2)
                                                        <span class="cr-pill tone-warning">{{ __('subject_type_university_requirement') }}</span>
                                                    @else
                                                        <span class="cr-pill">{{ __('subject_type_optional') }}</span>
                                                    @endif
                                                </td>
                                                <td class="cr-num">{{ number_format($course['credit_hours'], 1) }}</td>
                                                <td class="cr-num cr-text-danger fw-semibold">{{ number_format($course['best_marks'], 1) }}%</td>
                                                <td>{{ $course['grade'] }}</td>
                                                <td class="cr-num">{{ $course['attempts'] }}x</td>
                                                <td>
                                                    {{ $course['session_title'] }}
                                                    <span class="cr-course-sub">{{ $course['semester_title'] }}</span>
                                                </td>
                                                <td>
                                                    <span class="cr-pill tone-danger">{{ __('Not Validated') }}</span>
                                                    @if($course['subject_type'] == 1 || $course['subject_type'] == 2)
                                                        <span class="cr-course-sub cr-text-danger">{{ __('Required for Graduation') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="cr-table-foot mb-3">
                                <strong>{{ __('Total Carry-Over Courses:') }} {{ count($carryOverCourses) }}</strong>
                                <span class="cr-pill tone-danger">{{ __('Compulsory:') }} {{ $carryOver->where('subject_type', 1)->count() }}</span>
                                <span class="cr-pill tone-warning">{{ __('University Req:') }} {{ $carryOver->where('subject_type', 2)->count() }}</span>
                                <span class="cr-pill">{{ __('Optional:') }} {{ $carryOver->where('subject_type', 0)->count() }}</span>
                            </div>

                            <div class="cr-note tone-info">
                                <i class="fas fa-graduation-cap"></i>
                                <div class="cr-note-body">
                                    <span class="cr-note-title">{{ __('Action Required') }}</span>
                                    <ul>
                                        <li>{{ __('Register these courses in the "Course Registration" section below to retake them this semester.') }}</li>
                                        <li>{{ __('Focus especially on compulsory and university requirement courses as they are mandatory for graduation.') }}</li>
                                        <li>{{ __('Aim to score at least 50% to validate each course.') }}</li>
                                        <li>{{ __('Courses marked in red (Compulsory) and orange (University Requirement) are critical priorities.') }}</li>
                                    </ul>
                                </div>
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
                            <h5><i class="fas fa-book-open"></i> {{ __('My Courses') }}</h5>
                            <p class="cr-sub">
                                {{ __('status_current') }} {{ __('field_session') }}:
                                {{ $currentEnroll->session->title ?? '' }} &middot; {{ $currentEnroll->semester->title ?? '' }} &middot; {{ $currentEnroll->section->title ?? '' }}
                            </p>
                        </div>
                        <div class="card-block">
                            <div class="cr-table-flush">
                                <div class="table-responsive">
                                    <table class="table cr-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('field_code') }}</th>
                                                <th>{{ __('field_subject') }}</th>
                                                <th class="cr-num">{{ __('field_credit_hour') }}</th>
                                                <th class="cr-num">{{ __('field_point') }}</th>
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
                                                    <td class="cr-code">{{ $subject->code }}</td>
                                                    <td>
                                                        {{ $subject->title }}
                                                        @if($subject->subject_type == 0)
                                                            <span class="cr-pill ms-1">{{ __('subject_type_optional') }}</span>
                                                        @elseif($subject->subject_type == 2)
                                                            <span class="cr-pill tone-warning ms-1">{{ __('subject_type_university_requirement') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="cr-num">{{ number_format($creditHour, 2) }}</td>
                                                    <td class="cr-num">
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
                                                        @elseif(isset($registrationStatus) && $registrationStatus['state'] === 'closed')
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="{{ __('Cannot drop: the course registration deadline has passed') }}">
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
                                                    <td colspan="6" class="text-center text-muted py-4">{{ __('no_result_found') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="2">{{ __('field_term_total') }}</th>
                                                <th class="cr-num">{{ number_format((float) $semesterCredits, 2, '.', '') }}</th>
                                                <th class="cr-num">{{ number_format((float) $semesterCgpa, 2, '.', '') }}</th>
                                                <th></th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-plus-circle"></i> {{ __('Choose your courses') }}</h5>
                        </div>
                        <div class="card-block">
                            @if($isResitSemester)
                                <div class="cr-note tone-warning" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div class="cr-note-body">
                                        <span class="cr-note-title">{{ __('Resit Semester') }}</span>
                                        {{ __('Course registration is not available during resit semesters. Students cannot register courses by themselves during this period.') }}
                                    </div>
                                </div>
                            @elseif(isset($registrationStatus) && $registrationStatus['state'] === 'closed')
                                {{-- The deadline has passed. Nothing to choose from, and the
                                     reason stated in the school's own words where it wrote any. --}}
                                <div class="cr-note tone-{{ $registrationStatus['registered'] > 0 ? 'neutral' : 'danger' }}" role="alert">
                                    <i class="fas fa-lock"></i>
                                    <div class="cr-note-body">
                                        <span class="cr-note-title">{{ $registrationStatus['headline'] }}</span>
                                        {{ $registrationStatus['detail'] }}
                                        @if($registrationStatus['deadline'])
                                            <hr>
                                            <p class="mb-0">{{ $registrationStatus['deadline']->noteFor() }}</p>
                                        @endif
                                    </div>
                                </div>
                            @else
                                @php
                                    $hasAssignableSubjects = isset($assignableSubjects) && $assignableSubjects->isNotEmpty();
                                    $prefilledSubjects = collect(old('subjects', []))->map(fn ($id) => (string) $id)->toArray();
                                @endphp
                                @if(!is_null($maxCreditLimit))
                                    <div class="cr-note tone-warning">
                                        <i class="fas fa-info-circle"></i>
                                        <div class="cr-note-body">
                                            {{ __('You may register up to :limit credits this semester.', ['limit' => $maxCreditLimit]) }}
                                        </div>
                                    </div>
                                @endif
                                @if(!$hasAssignableSubjects)
                                    <div class="cr-note tone-info" role="alert">
                                        <i class="fas fa-info-circle"></i>
                                        <div class="cr-note-body">
                                            {{ __('No subjects are currently available for registration. This may be because:') }}
                                            <ul>
                                                <li>{{ __('You have already validated all available courses (marks ≥ 50%)') }}</li>
                                                <li>{{ __('You are already registered for all available courses') }}</li>
                                                <li>{{ __('No courses have been assigned to your current semester and section') }}</li>
                                            </ul>
                                            <hr>
                                            <p class="mb-0">
                                                {{ __('If you believe courses should be listed here, speak to your department - only the school can set which courses your semester offers.') }}
                                            </p>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        // Two groups: what this semester offers, and what is
                                        // being retaken from earlier years. Inside each, the
                                        // courses a student cannot skip come first.
                                        $typeOrder = [1 => 0, 2 => 1, 0 => 2];
                                        $sortCourses = fn ($c) => $c->sortBy(fn ($s) => sprintf('%d-%s', $typeOrder[(int) $s->subject_type] ?? 3, $s->code))->values();
                                        $courseGroups = [
                                            'current' => [
                                                'title'   => __('This semester'),
                                                'courses' => $sortCourses($assignableSubjects->filter(fn ($s) => in_array($s->id, $currentSemesterEnrolledSubjectIds))),
                                            ],
                                            'carry' => [
                                                'title'   => __('Carry-over (retake)'),
                                                'courses' => $sortCourses($assignableSubjects->reject(fn ($s) => in_array($s->id, $currentSemesterEnrolledSubjectIds))),
                                            ],
                                        ];
                                        $registeredCredits = (float) ($currentEnroll->subjects ?? collect())->sum('credit_hour');
                                    @endphp
                                    <form class="needs-validation cr-form {{ !is_null($maxCreditLimit) ? 'mt-3' : '' }}" novalidate action="{{ route($route . '.update') }}" method="post" id="cr-register-form">
                                        @csrf
                                        <p class="cr-hint mt-0 mb-3">
                                            {{ __('Showing courses from your current semester and previous years of the same semester type that you have not yet validated (marks < 50%).') }}
                                            {{ __('Tick every course you want to take, then click') }} <strong>{{ __('Register these courses') }}</strong>.
                                        </p>

                                        <div class="cr-pick-tools">
                                            <div class="cr-pick-search">
                                                <i class="fas fa-search"></i>
                                                <input type="search" class="form-control" id="cr-pick-search" placeholder="{{ __('Search by code or title') }}" autocomplete="off" aria-label="{{ __('Search by code or title') }}">
                                            </div>
                                            <span class="cr-pick-count">{{ $assignableSubjects->count() }} {{ __('courses available') }}</span>
                                        </div>

                                        <div class="cr-pick" id="subjects" role="group" aria-label="{{ __('Choose your courses') }}">
                                            @foreach($courseGroups as $groupKey => $group)
                                                @continue($group['courses']->isEmpty())
                                                <div class="cr-pick-group" data-group="{{ $groupKey }}">
                                                    <div class="cr-pick-group-head">
                                                        <span class="cr-pick-group-title">{{ $group['title'] }} <span class="cr-pill">{{ $group['courses']->count() }}</span></span>
                                                        <button type="button" class="cr-pick-all" data-group="{{ $groupKey }}">{{ __('Select all') }}</button>
                                                    </div>
                                                    @foreach($group['courses'] as $subject)
                                                        @php
                                                            $searchText = strtolower($subject->code . ' ' . $subject->title);
                                                        @endphp
                                                        <label class="cr-pick-row" data-search="{{ $searchText }}">
                                                            <input type="checkbox" class="form-check-input" name="subjects[]" value="{{ $subject->id }}"
                                                                   data-code="{{ $subject->code }}" data-title="{{ $subject->title }}"
                                                                   data-credits="{{ (float) $subject->credit_hour }}"
                                                                   @checked(in_array((string) $subject->id, $prefilledSubjects, true))>
                                                            <span class="cr-pick-main">
                                                                <span class="cr-pick-title"><span class="cr-code">{{ $subject->code }}</span> {{ $subject->title }}</span>
                                                                <span class="cr-pick-meta">
                                                                    @if($subject->subject_type == 1)
                                                                        <span class="cr-pill tone-danger">{{ __('Compulsory') }}</span>
                                                                    @elseif($subject->subject_type == 2)
                                                                        <span class="cr-pill tone-warning">{{ __('University Requirement') }}</span>
                                                                    @else
                                                                        <span class="cr-pill">{{ __('Optional') }}</span>
                                                                    @endif
                                                                </span>
                                                            </span>
                                                            <span class="cr-pick-credits">{{ rtrim(rtrim(number_format((float) $subject->credit_hour, 1), '0'), '.') }} <small>{{ __('credits') }}</small></span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                            <p class="cr-pick-empty" id="cr-pick-empty" hidden>{{ __('No course matches your search.') }}</p>
                                        </div>

                                        @error('subjects')
                                            <div class="cr-note tone-danger mt-3">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <div class="cr-note-body">{{ $message }}</div>
                                            </div>
                                        @enderror

                                        {{-- Always in view while the list scrolls: what is picked,
                                             what it adds up to, and the limit it is measured against. --}}
                                        <div class="cr-pick-bar" id="cr-pick-bar"
                                             data-registered="{{ $registeredCredits }}"
                                             data-limit="{{ $maxCreditLimit ?? '' }}">
                                            <div class="cr-pick-summary">
                                                <strong id="cr-pick-selected">{{ __('No course selected') }}</strong>
                                                <span id="cr-pick-credits-line">
                                                    @if(!is_null($maxCreditLimit))
                                                        {{ __(':used of :limit credits used', ['used' => rtrim(rtrim(number_format($registeredCredits, 1), '0'), '.'), 'limit' => $maxCreditLimit]) }}
                                                    @endif
                                                </span>
                                            </div>
                                            <button type="button" class="btn btn-success" id="cr-register-btn" data-bs-toggle="modal" data-bs-target="#confirmModal" disabled>
                                                <i class="fas fa-check"></i> {{ __('Register these courses') }}
                                            </button>
                                        </div>
                                        @include('student.course-registration.confirm')
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
<script type="text/javascript">
"use strict";
(function($){
    if(typeof $ === 'undefined'){
        return;
    }

    $(function(){
        // ---- course picker -------------------------------------------------
        const $form = $('#cr-register-form');
        if($form.length){
            const t = {!! json_encode([
                'none'      => __('No course selected'),
                'one'       => __('1 course selected'),
                'many'      => __(':count courses selected'),
                'credits'   => __(':count credits'),
                'used'      => __(':used of :limit credits used'),
                'over'      => __('Over the limit by :over credits. Untick a course to continue.'),
                'selectAll' => __('Select all'),
                'clearAll'  => __('Clear'),
                'total'     => __('Total: :credits credits'),
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
            const fmt = (s, r) => Object.keys(r).reduce((a, k) => a.split(':' + k).join(r[k]), s);
            const num = n => (Math.round(n * 10) / 10).toString();

            const $boxes   = $form.find('input[name="subjects[]"]');
            const $bar     = $('#cr-pick-bar');
            const $btn     = $('#cr-register-btn');
            const registered = parseFloat($bar.data('registered')) || 0;
            const limit      = $bar.data('limit') === '' ? null : parseFloat($bar.data('limit'));

            function refresh(){
                const $on = $boxes.filter(':checked');
                const picked = $on.toArray().reduce((sum, el) => sum + (parseFloat($(el).data('credits')) || 0), 0);
                const count = $on.length;

                $('#cr-pick-selected').text(count === 0 ? t.none : (count === 1 ? t.one : fmt(t.many, {count: count})));

                let line = count ? fmt(t.credits, {count: num(picked)}) : '';
                let over = false;
                if(limit !== null){
                    const used = registered + picked;
                    over = used > limit;
                    line = (line ? line + ' · ' : '') + fmt(t.used, {used: num(used), limit: num(limit)});
                    if(over){ line = fmt(t.over, {over: num(used - limit)}); }
                }
                $('#cr-pick-credits-line').text(line);
                $bar.toggleClass('is-over', over).toggleClass('has-selection', count > 0);
                $btn.prop('disabled', count === 0 || over);

                $boxes.each(function(){ $(this).closest('.cr-pick-row').toggleClass('is-on', this.checked); });
                $form.find('.cr-pick-all').each(function(){
                    const $rows = $form.find('.cr-pick-group[data-group="' + $(this).data('group') + '"] .cr-pick-row:visible input');
                    const all = $rows.length && $rows.filter(':checked').length === $rows.length;
                    $(this).text(all ? t.clearAll : t.selectAll);
                });
            }

            $boxes.on('change', refresh);

            // Select all / Clear, for whatever the search is currently showing.
            $form.on('click', '.cr-pick-all', function(){
                const $rows = $form.find('.cr-pick-group[data-group="' + $(this).data('group') + '"] .cr-pick-row:visible input');
                const all = $rows.filter(':checked').length === $rows.length;
                $rows.prop('checked', !all);
                refresh();
            });

            // Search: hides rows, never unticks them.
            $('#cr-pick-search').on('input', function(){
                const q = this.value.trim().toLowerCase();
                let shown = 0;
                $form.find('.cr-pick-group').each(function(){
                    let inGroup = 0;
                    $(this).find('.cr-pick-row').each(function(){
                        const match = !q || String($(this).data('search')).indexOf(q) !== -1;
                        $(this).toggle(match);
                        if(match){ inGroup++; }
                    });
                    $(this).toggle(inGroup > 0);
                    shown += inGroup;
                });
                $('#cr-pick-empty').prop('hidden', shown > 0);
                refresh();
            });

            // The dialog names exactly what is about to be saved.
            $('#confirmModal').on('show.bs.modal', function(){
                const $list = $('#cr-confirm-list').empty();
                let total = 0;
                $boxes.filter(':checked').each(function(){
                    const credits = parseFloat($(this).data('credits')) || 0;
                    total += credits;
                    $('<li>')
                        .append($('<span class="cr-code">').text($(this).data('code')))
                        .append(document.createTextNode(' ' + $(this).data('title')))
                        .append($('<span class="cr-confirm-credits">').text(num(credits)))
                        .appendTo($list);
                });
                $('#cr-confirm-total').text(fmt(t.total, {credits: num(total)}));
            });

            refresh();
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
