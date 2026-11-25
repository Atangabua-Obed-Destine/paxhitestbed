@extends('admin.layouts.master')
@section('title', $title)

{{--
    The EduTrust dashboard.

    It is laid out like a day book, the register a school office keeps: today's
    date in the margin, a greeting, then what the day holds. It reads two ways.

    A lecturer opens their own desk first: a welcome, their classes today in
    order with the one in progress and the next one marked, the live class
    kiosk, their courses and their week.

    Staff who run the institution then see what is waiting on them (only what
    actually needs doing, never a row of zeros), four figures with the context
    that makes them mean something, where the students are, the day at a
    glance, and a directory of the screens they can open.

    Every panel and link is behind the same permission it had before; no figure
    is calculated differently. The figures come from DashboardController.
--}}

@section('page_css')
@include('common.dashboard._daybook_style')
@endsection

@section('content')
@php
    $me = Auth::user();
    $now = now();
    $greeting = $now->hour < 12 ? __('Good morning') : ($now->hour < 17 ? __('Good afternoon') : __('Good evening'));
    // Timetable times, without seconds. The setting offers "HH:MM:SS" (stored as
    // h:i:s, which PHP reads as a 12-hour clock with no AM/PM, so 17:08 would
    // print as 05:08) and "HH:MM:SS XM". Read them as the labels promise.
    $timeFormat = preg_match('/[aA]/', $setting->time_format ?? '') ? 'g:i A' : 'H:i';
    $at = fn ($time) => date($timeFormat, strtotime($time));
    $money = fn ($amount) => ($setting->currency_symbol ?? '$') . format_amount($amount);

    // The system numbers days 1 = Saturday … 7 = Friday.
    $todayNumber = [1 => 3, 2 => 4, 3 => 5, 4 => 6, 5 => 7, 6 => 1, 7 => 2][(int) $now->format('N')];

    // ── The lecturer's day ──
    $classesToday = collect();
    $nextClass = null;
    $classNow = null;
    if ($is_teacher) {
        $clock = $now->format('H:i:s');
        // A service course is often one class in one room shared by many groups,
        // and the timetable holds a row per group. The lecturer teaches it once,
        // so it is shown once, with its groups counted.
        $classesToday = $teacher_today_classes
            ->groupBy(fn ($c) => implode('|', [$c->start_time, $c->end_time, $c->subject_id, $c->room_id]))
            ->map(function ($rows) { $class = $rows->first(); $class->dash_groups = $rows; return $class; })
            ->values()
            ->map(function ($class) use ($clock) {
            $start = date('H:i:s', strtotime($class->start_time));
            $end = date('H:i:s', strtotime($class->end_time));
            $class->dash_state = $end <= $clock ? 'done' : ($start <= $clock ? 'now' : 'later');
            return $class;
        });
        $classNow = $classesToday->firstWhere('dash_state', 'now');
        // Counted the same way: a class shared by several groups is one class.
        $weeklyTaught = $teacher_weekly_schedule->flatten()
            ->unique(fn ($c) => implode('|', [$c->day, $c->start_time, $c->end_time, $c->subject_id, $c->room_id]))
            ->count();
        $nextClass = $classesToday->firstWhere('dash_state', 'later');
        if ($nextClass) {
            $nextClass->dash_state = 'next';
        }

        $activeSession = \App\Models\ClassSession::where('teacher_id', Auth::id())
            ->where('date', $now->toDateString())->where('status', 'in_progress')
            ->with(['subject', 'program'])->first();
        $pendingSessions = \App\Models\ClassSession::where('teacher_id', Auth::id())
            ->where('date', $now->toDateString())->where('status', 'pending')->count();
        $completedSessions = \App\Models\ClassSession::where('teacher_id', Auth::id())
            ->where('date', $now->toDateString())->where('status', 'completed')->count();
    }

    // ── What is waiting on this person: only what they may act on, only above zero ──
    $waitingItems = [
        ['application-view', $pending_applications, 'admin.application.index', __('Applications to review'), __('New applicants waiting for a decision'), ''],
        ['payment-receipt-verify', $payment_verifications, 'admin.payment-verification.index', __('Payment receipts to verify'), __('Students are waiting for their payments to be confirmed'), 'warn'],
        ['resit-request-view', $resit_requests, 'admin.resit-requests.index', __('Resit requests'), __('Requested or waiting for payment'), ''],
        ['payroll-view', $pending_payroll, 'admin.payroll.index', __('Payroll to complete'), __('Salaries generated but not yet paid'), 'warn'],
        ['journal-entry-view', $unposted_entries ?? 0, 'admin.journal-entries.index', __('Journal entries to post'), __('Recorded but not yet in the ledger'), ''],
        ['issue-return-view', $books_overdue, 'admin.issue-return.index', __('Library books overdue'), __('Past their return date'), 'bad'],
        ['book-request-view', $pending_book_requests, 'admin.book-request.index', __('Book requests'), __('Waiting for the library to answer'), ''],
    ];
    $canSeeWaiting = false;
    $waiting = [];
    foreach ($waitingItems as [$permission, $count, $route, $label, $note, $tone]) {
        if (!$me->can($permission)) continue;
        $canSeeWaiting = true;
        if ($count > 0) $waiting[] = compact('count', 'route', 'label', 'note', 'tone');
    }
    $waitingTotal = count($waiting);

    $canStudents = $me->canany(['application-view', 'student-view']);
    $canFees = $me->can('fees-student-report');
    $canAttendance = $me->can('student-attendance-action');
    $canStaff = $me->canany(['user-view', 'payroll-view']);
    $canFigures = $canStudents || $canFees || $canAttendance || $canStaff;

    // ── The day at a glance ──
    $dayItems = array_values(array_filter([
        ['class-routine-view', 'fa-chalkboard-teacher', __('Classes on the timetable today'), $today_classes, 'admin.class-routine.index'],
        ['event-view', 'fa-calendar', __('Upcoming events'), $upcoming_events, 'admin.event.index'],
        ['notice-view', 'fa-sticky-note', __('Notices published'), $active_notices, 'admin.notice.index'],
        ['assignment-view', 'fa-tasks', __('Assignments open'), $active_assignments, 'admin.assignment.index'],
        ['payment-plan.index', 'fa-calendar-check', __('Active payment plans'), $active_payment_plans, 'admin.payment-plan.index'],
        ['issue-return-view', 'fa-book', __('Books out on loan'), $books_issued, 'admin.issue-return.index'],
    ], fn ($item) => $me->can($item[0])));
    $frontItems = array_values(array_filter([
        [['visitor-view', 'visitor-create'], 'fa-user-friends', __('Visitors'), $daily_visitors->count(), 'admin.visitor.index'],
        [['phone-log-view', 'phone-log-create'], 'fa-phone-alt', __('Phone calls'), $daily_phone_logs->count(), 'admin.phone-log.index'],
        [['enquiry-view', 'enquiry-create'], 'fa-question-circle', __('Enquiries'), $daily_enqueries->count(), 'admin.enquiry.index'],
        [['postal-exchange-view', 'postal-exchange-create'], 'fa-envelope', __('Post in and out'), $daily_postals->count(), 'admin.postal-exchange.index'],
    ], fn ($item) => $me->canany($item[0])));

    // ── Directory of screens, grouped as in the menu ──
    $directory = [
        [trans_choice('module_admission', 2), 'fa-university', [
            ['application-view', 'admin.application.index', trans_choice('module_application', 2)],
            ['student-view', 'admin.student.index', trans_choice('module_student', 1) . ' ' . __('list')],
            ['student-transfer-in-view', 'admin.student-transfer-in.index', trans_choice('module_transfer_in', 1)],
            ['student-card', 'admin.id-card.index', trans_choice('module_id_card', 2)],
        ]],
        [trans_choice('module_student', 2), 'fa-user-graduate', [
            ['student-attendance-action', 'admin.student-attendance.index', trans_choice('module_attendance', 2)],
            ['student-enroll-single', 'admin.single-enroll.index', trans_choice('module_single_enroll', 1)],
            ['student-enroll-complete', 'admin.course-complete.index', trans_choice('module_course_complete', 2)],
            ['student-enroll-alumni', 'admin.student-alumni.index', trans_choice('module_student_alumni', 1)],
        ]],
        [trans_choice('module_routine', 2), 'fa-calendar-alt', [
            ['class-routine-view', 'admin.class-routine.index', trans_choice('module_class_routine', 2)],
            ['exam-routine-view', 'admin.exam-routine.index', trans_choice('module_exam_routine', 2)],
            ['class-routine-teacher', 'admin.class-routine.teacher', trans_choice('module_teacher_routine', 2)],
        ]],
        [trans_choice('module_examination', 2), 'fa-file-alt', [
            ['exam-attendance', 'admin.exam-attendance.index', trans_choice('module_exam_attendance', 2)],
            ['exam-marking', 'admin.exam-marking.index', trans_choice('module_exam_marking', 2)],
            ['exam-marking', 'admin.student-exam-config.index', __('Student Exam Config')],
            ['subject-marking', 'admin.subject-marking.index', trans_choice('module_subject_marking', 2)],
            ['resit-request-view', 'admin.resit-requests.index', __('Resit Requests')],
            ['admit-card-view', 'admin.admit-card.index', trans_choice('module_admit_card', 2)],
        ]],
        [trans_choice('module_study_material', 2), 'fa-newspaper', [
            ['assignment-view', 'admin.assignment.index', trans_choice('module_assignment', 2)],
            ['content-view', 'admin.content.index', trans_choice('module_content', 1) . ' ' . __('list')],
        ]],
        [trans_choice('module_fees_collection', 1), 'fa-money-bill-wave', [
            ['fees-student-due', 'admin.fees-student.index', trans_choice('module_student_fees', 2)],
            ['fees-student-report', 'admin.fees-student.report', trans_choice('module_fees_report', 2)],
            ['payment-plan.index', 'admin.payment-plan.index', __('Payment Plans')],
            ['fees-master-create', 'admin.fees-master.create', trans_choice('module_fees_master', 2)],
            ['fees-master-view', 'admin.fees-master.index', trans_choice('module_fees_master_history', 2)],
            ['payment-receipt-verify', 'admin.payment-verification.index', trans_choice('module_payment_verification', 2)],
        ]],
        [trans_choice('module_human_resource', 2), 'fa-users-cog', [
            ['user-view', 'admin.user.index', trans_choice('module_staff', 1) . ' ' . __('list')],
            ['staff-assignment-index', 'admin.staff-assignment.index', __('Staff Assign')],
            ['staff-view', 'admin.staff-id-card.index', trans_choice('module_staff', 1) . ' ' . __('field_id_card')],
            ['payroll-view', 'admin.payroll.index', trans_choice('module_payroll', 2)],
            ['payroll-report', 'admin.payroll.report', trans_choice('module_payroll_report', 2)],
            ['tax-setting-view', 'admin.tax-setting.index', trans_choice('module_tax_setting', 2)],
        ]],
        [trans_choice('module_staff_attendance', 2), 'fa-calendar-check', [
            ['staff-daily-attendance-action', 'admin.staff-daily-attendance.index', trans_choice('module_staff_daily_attendance', 2)],
            ['staff-hourly-attendance-action', 'admin.staff-hourly-attendance.index', trans_choice('module_staff_hourly_attendance', 2)],
        ]],
        [__('Accounting'), 'fa-calculator', [
            ['income-view', 'admin.income.index', trans_choice('module_income', 1) . ' ' . __('list')],
            ['expense-view', 'admin.expense.index', trans_choice('module_expense', 1) . ' ' . __('list')],
            ['budget-view', 'admin.budget.dashboard', __('budget_dashboard')],
            ['budget-view', 'admin.budget.index', trans_choice('module_budget', 2)],
            ['payment-account-view', 'admin.payment-account.index', __('Payment Accounts')],
            ['payment-account-view', 'admin.payment-account-transfer.index', __('Account Transfers')],
            ['chart-of-accounts-view', 'admin.chart-of-accounts.index', __('Chart of Accounts')],
            ['journal-entry-view', 'admin.journal-entries.index', __('Journal Entries')],
            ['general-ledger-view', 'admin.general-ledger.index', __('General Ledger')],
        ]],
        [trans_choice('module_library', 1), 'fa-book-open', [
            ['issue-return-create', 'admin.issue-return.create', trans_choice('module_issue_book', 1)],
            ['issue-return-view', 'admin.issue-return.index', trans_choice('module_issue_return', 2)],
            ['book-view', 'admin.book-list.index', trans_choice('module_book_list', 2)],
            ['book-request-view', 'admin.book-request.index', trans_choice('module_book_request', 2)],
            ['e-library-view', 'admin.e-library.index', __('E-Library')],
        ]],
        [trans_choice('module_inventory', 2), 'fa-boxes', [
            ['item-issue-create', 'admin.item-issue.create', trans_choice('module_issue_item', 1)],
            ['item-issue-view', 'admin.item-issue.index', trans_choice('module_item_issue', 2)],
            ['item-view', 'admin.item-list.index', trans_choice('module_item_list', 2)],
            ['item-view', 'admin.item-stock.index', trans_choice('module_item_stock', 2)],
        ]],
        [trans_choice('module_communicate', 2), 'fa-bullhorn', [
            ['email-notify-create', 'admin.email-notify.index', trans_choice('module_email_notify', 2)],
            ['event-view', 'admin.event.index', trans_choice('module_event', 2)],
            ['event-view', 'admin.event.calendar', trans_choice('module_calendar', 2)],
            ['notice-view', 'admin.notice.index', trans_choice('module_notice', 2)],
        ]],
        [__('Reports & Transcripts'), 'fa-chart-line', [
            ['transcript-view', 'admin.marksheet.semester', __('Semester Marksheet')],
            ['transcript-view', 'admin.marksheet.index', trans_choice('module_transcript', 2)],
            ['report-view', 'admin.report.student', trans_choice('module_student', 1) . ' ' . __('report')],
            ['report-view', 'admin.report.student-fees', __('Student Fees Report')],
        ]],
        [__('System Management'), 'fa-cogs', [
            ['role-view', 'admin.role.index', trans_choice('module_role_permission', 2)],
            ['audit-log-view', 'admin.audit-log.index', __('Audit Log')],
        ]],
    ];
    $directory = array_values(array_filter(array_map(function ($group) use ($me) {
        $group[2] = array_values(array_filter($group[2], fn ($link) => $me->can($link[0])));
        return $group;
    }, $directory), fn ($group) => count($group[2]) > 0));

    // ── Where the students are ──
    $breakdowns = [
        'faculty' => [__('Faculty'), $students_by_faculty->map(fn ($f) => [$f->title, (int) $f->active_students_count])->values()],
        'department' => [__('Department'), $students_by_department->map(fn ($d) => [$d->title, (int) $d->active_students_count])->values()],
        'program' => [__('Programme'), $students_by_program->map(fn ($p) => [$p->title, (int) $p->student_enrolls_count])->filter(fn ($row) => $row[1] > 0)->values()],
    ];
    $breakdowns = array_filter($breakdowns, fn ($b) => $b[1]->count() > 0);
@endphp

<div class="main-body">
    <div class="page-wrapper">
    <div class="et-dash">

        {{-- ══ Masthead ══ --}}
        <header class="et-mast">
            <div class="et-date" aria-hidden="true">
                <span class="et-date-day">{{ $now->format('j') }}</span>
                <span class="et-date-week">{{ $now->translatedFormat('l') }}</span>
                <span class="et-date-month">{{ $now->translatedFormat('M Y') }}</span>
            </div>

            <div class="et-mast-main">
                <p class="et-eyebrow">{{ $is_teacher ? __('Your lecturer\'s desk') : institution_name() }}</p>
                <h1 class="et-greet">{{ $greeting }}, {{ $me->first_name }}.</h1>
                <p class="et-lede">
                    @if($is_teacher)
                        @if($classNow)
                            {!! __('You are teaching :course right now, until :end.', ['course' => '<strong>' . e(trim(($classNow->subject->code ?? '') . ' ' . ($classNow->subject->title ?? ''))) . '</strong>', 'end' => e($at($classNow->end_time))]) !!}
                            @if($nextClass) {!! __('Next is :course at :time.', ['course' => '<strong>' . e($nextClass->subject->code ?? $nextClass->subject->title ?? '') . '</strong>', 'time' => e($at($nextClass->start_time))]) !!} @endif
                        @elseif($nextClass)
                            {{ trans_choice('{1} You have one class today.|[2,*] You have :n classes today.', $classesToday->count(), ['n' => $classesToday->count()]) }}
                            {!! __('The next is :course at :time:room.', [
                                'course' => '<strong>' . e(trim(($nextClass->subject->code ?? '') . ' ' . ($nextClass->subject->title ?? ''))) . '</strong>',
                                'time' => e($at($nextClass->start_time)),
                                'room' => $nextClass->room ? ', ' . e(__('in :room', ['room' => $nextClass->room->title])) : '',
                            ]) !!}
                        @elseif($classesToday->count())
                            {{ __('That is all your classes for today. Thank you for your work.') }}
                        @else
                            {{ __('There are no classes on your timetable today. Welcome — everything you teach is below.') }}
                        @endif
                        @if($waitingTotal)
                            {{ trans_choice('{1} One thing is also waiting on you below.|[2,*] :n things are also waiting on you below.', $waitingTotal, ['n' => $waitingTotal]) }}
                        @endif
                    @elseif($canSeeWaiting)
                        @if($waitingTotal)
                            {{ trans_choice('{1} One thing is waiting on you today.|[2,*] :n things are waiting on you today.', $waitingTotal, ['n' => $waitingTotal]) }}
                            {{ __('Everything else is in order.') }}
                        @else
                            {{ __('Nothing is waiting on you today. Everything is up to date.') }}
                        @endif
                    @else
                        {{ __('Welcome back. Here is the institution today.') }}
                    @endif
                </p>
            </div>

            <div class="et-mast-side">
                @if($current_session)
                    <span class="et-chip"><i class="fas fa-graduation-cap" aria-hidden="true"></i> {{ __('Academic year :year', ['year' => $current_session->title]) }}</span>
                @endif
                <span class="et-clock">{{ $now->translatedFormat('l, j F Y') }}</span>
            </div>
        </header>

        {{-- ══ The lecturer's desk ══ --}}
        @if($is_teacher)
        <section class="et-section" aria-labelledby="et-today-title">
            <div class="et-row">
                <div class="et-panel et-wide">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title" id="et-today-title">{{ __('Your classes today') }}</h2>
                        @can('class-routine-teacher')
                            <a class="et-panel-link" href="{{ route('admin.class-routine.teacher') }}">{{ __('Full timetable') }} →</a>
                        @endcan
                    </div>
                    @if($classesToday->count())
                        <ol class="et-timeline">
                            @foreach($classesToday as $class)
                            <li class="et-class is-{{ $class->dash_state }}">
                                <div class="et-class-time">
                                    <b>{{ $at($class->start_time) }}</b>
                                    <span>{{ __('to :time', ['time' => $at($class->end_time)]) }}</span>
                                </div>
                                <div class="et-class-node" aria-hidden="true"></div>
                                <div>
                                    <p class="et-class-title">
                                        @if($class->subject->code ?? false)<span class="et-code">{{ $class->subject->code }}</span>@endif{{ $class->subject->title ?? '' }}
                                        @if($class->dash_state === 'now')<span class="et-state now">{{ __('In progress') }}</span>
                                        @elseif($class->dash_state === 'next')<span class="et-state next">{{ __('Next') }}</span>
                                        @elseif($class->dash_state === 'done')<span class="et-state done">{{ __('Finished') }}</span>@endif
                                    </p>
                                    <p class="et-class-meta">
                                        @if($class->dash_groups->count() > 1)
                                            {{ __(':n groups together', ['n' => $class->dash_groups->count()]) }}
                                        @else
                                            {{ collect([$class->program->title ?? null, $class->semester->title ?? null, $class->section->title ?? null])->filter()->implode(' · ') }}
                                        @endif
                                        @if($class->room) · <i class="fas fa-door-open" aria-hidden="true"></i> {{ $class->room->title }}@endif
                                    </p>
                                </div>
                            </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="et-empty"><i class="fas fa-mug-hot" aria-hidden="true"></i> {{ __('No classes on your timetable today.') }}</div>
                    @endif
                </div>

                <div class="et-panel et-kiosk-panel {{ $activeSession ? 'is-live' : '' }}">
                    <div class="et-kiosk">
                        @if($activeSession)
                            <h2 class="et-kiosk-title"><span class="et-live-dot" aria-hidden="true"></span> {{ __('Class in progress') }}</h2>
                            <p>
                                <strong>{{ trim(($activeSession->subject->code ?? '') . ' ' . ($activeSession->subject->title ?? __('Unknown course'))) }}</strong>
                                · {{ $activeSession->program->short_form ?? $activeSession->program->title ?? '' }}
                                @if($activeSession->actual_start_time)
                                    <br>{{ __('Started :when', ['when' => \Carbon\Carbon::parse($activeSession->actual_start_time)->diffForHumans()]) }}
                                @endif
                            </p>
                            <a href="{{ route('admin.class-session.kiosk') }}" class="et-btn good"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> {{ __('Return to the kiosk') }}</a>
                        @else
                            <h2 class="et-kiosk-title"><i class="fas fa-qrcode" aria-hidden="true"></i> {{ __('Live class kiosk') }}</h2>
                            <p>
                                {{ __('Start a class, take attendance with the QR scanner and keep the logbook.') }}
                                @if($pendingSessions || $completedSessions)
                                    <br>
                                    @if($pendingSessions){{ trans_choice('{1} One session to start|[2,*] :n sessions to start', $pendingSessions, ['n' => $pendingSessions]) }}@endif
                                    @if($pendingSessions && $completedSessions) · @endif
                                    @if($completedSessions){{ trans_choice('{1} one finished today|[2,*] :n finished today', $completedSessions, ['n' => $completedSessions]) }}@endif
                                @endif
                            </p>
                            <a href="{{ route('admin.class-session.kiosk') }}" class="et-btn"><i class="fas fa-chalkboard" aria-hidden="true"></i> {{ __('Open the kiosk') }}</a>
                        @endif
                    </div>
                    <div class="et-trio">
                        <div><b>{{ $classesToday->count() }}</b><span>{{ __('today') }}</span></div>
                        <div><b>{{ $teacher_subjects->count() }}</b><span>{{ __('courses') }}</span></div>
                        <div><b>{{ $weeklyTaught }}</b><span>{{ __('classes a week') }}</span></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- A tile is a SUBJECT; the classes it is taught to sit inside it.
             Collapsing them hides most of a lecturer's classes behind one tile
             and leaves a single link no way to say which group it means.

             The primary action is therefore the WHOLE course: attendance is
             kept per (student, subject, date) with no programme on it, so for
             a service course taught to twelve programmes the whole course IS
             the register the lecturer keeps. The per-group links are there for
             when they want one programme's slice. --}}
        @if($teacher_courses->count())
        <section class="et-section" aria-labelledby="et-courses-title">
            <div class="et-section-head">
                <h2 class="et-section-title" id="et-courses-title">{{ __('Your courses this session') }}</h2>
                <span class="et-section-note">{{ trans_choice('{1} One course|[2,*] :n courses', $teacher_courses->count(), ['n' => $teacher_courses->count()]) }}</span>
            </div>
            <div class="et-courses">
                @foreach($teacher_courses as $course)
                @php
                    $subject = $course['subject'];
                    $groups  = $course['classes'];
                    $base    = ['session' => $course['session_id'], 'subject' => $subject->id];
                @endphp
                <article class="et-course">
                    <div class="et-course-head">
                        <span class="et-code">{{ $subject->code }}</span>
                        <span class="et-course-students" title="{{ __('Students taking this course') }}"><i class="fas fa-user-graduate" aria-hidden="true"></i> {{ $course['students'] }}</span>
                    </div>
                    <p class="et-course-title">{{ $subject->title }}</p>

                    <div class="et-course-groups">
                        @if($groups->count() > 1)
                            <a class="et-toggle" data-bs-toggle="collapse" data-toggle="collapse" href="#course-groups-{{ $subject->id }}"
                               role="button" aria-expanded="false" aria-controls="course-groups-{{ $subject->id }}">
                                {{ __('Taught to :n groups — pick one', ['n' => $groups->count()]) }} <i class="fas fa-chevron-down" aria-hidden="true"></i>
                            </a>
                            <div class="collapse" id="course-groups-{{ $subject->id }}">
                                <ul>
                                    @foreach($groups as $group)
                                    <li>
                                        {{-- array_merge, not +: the + operator keeps the
                                             left-hand value on a key collision, which is a
                                             silent no-op the day $base grows one of these keys. --}}
                                        <a href="{{ route('admin.course-class-list.index', array_merge($base, [
                                                'program'  => $group['program_id'],
                                                'semester' => $group['semester_id'],
                                                'section'  => $group['section_id'],
                                           ])) }}">{{ $group['program'] }}</a>
                                        — {{ $group['semester'] }}@if($group['section']) · {{ $group['section'] }}@endif · {{ $group['students'] }}
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif($groups->count() === 1)
                            {{ $groups[0]['program'] }} — {{ $groups[0]['semester'] }}@if($groups[0]['section']) · {{ $groups[0]['section'] }}@endif
                        @endif
                    </div>

                    <a href="{{ route('admin.course-class-list.index', $base) }}" class="et-btn">
                        <i class="fas fa-clipboard-list" aria-hidden="true"></i> {{ __('Class list & attendance') }}
                    </a>
                </article>
                @endforeach
            </div>
        </section>
        @endif

        @if($teacher_weekly_schedule->count())
        <section class="et-section" aria-labelledby="et-week-title">
            <div class="et-section-head">
                <h2 class="et-section-title" id="et-week-title">{{ __('Your week') }}</h2>
                <span class="et-section-note">{{ trans_choice('{1} One class a week|[2,*] :n classes a week', $weeklyTaught, ['n' => $weeklyTaught]) }}</span>
            </div>
            @php
                // Monday first; the system's own numbers are 1 = Saturday … 7 = Friday.
                $weekDays = [3 => __('Monday'), 4 => __('Tuesday'), 5 => __('Wednesday'), 6 => __('Thursday'), 7 => __('Friday'), 1 => __('Saturday'), 2 => __('Sunday')];
            @endphp
            <div class="et-week">
                @foreach($weekDays as $dayNumber => $dayName)
                    @php
                        // One entry per class taught, not per group sharing it.
                        $slots = $teacher_weekly_schedule->get($dayNumber, collect())
                            ->groupBy(fn ($c) => implode('|', [$c->start_time, $c->end_time, $c->subject_id, $c->room_id]))
                            ->map(function ($rows) { $slot = $rows->first(); $slot->dash_groups = $rows->count(); return $slot; })
                            ->values();
                    @endphp
                    <div class="et-weekday {{ $dayNumber === $todayNumber ? 'is-today' : '' }} {{ $slots->isEmpty() ? 'is-empty' : '' }}">
                        <div class="et-weekday-name">{{ $dayName }}@if($dayNumber === $todayNumber) · {{ __('today') }}@endif</div>
                        @forelse($slots as $slot)
                            <div class="et-slot" title="{{ trim(($slot->subject->title ?? '') . ' — ' . ($slot->program->title ?? '')) }}">
                                <b>{{ $at($slot->start_time) }} · {{ $slot->subject->code ?? $slot->subject->title ?? '' }}</b>
                                <span>{{ collect([$slot->room->title ?? null, $slot->dash_groups > 1 ? __(':n groups', ['n' => $slot->dash_groups]) : null])->filter()->implode(' · ') }}</span>
                            </div>
                        @empty
                            <div class="et-slot-none">—</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>
        @endif
        @endif

        {{-- ══ Messages: everyone has a mailbox ══ --}}
        <section class="et-section" aria-label="{{ __('Messages') }}">
            <div class="et-row">
                @include('common.dashboard._messages_panel', ['mbRoute' => 'admin.mailbox', 'mbMember' => ['staff', (int) $me->id], 'mbGroups' => true])
            </div>
        </section>

        {{-- ══ The institution ══ --}}
        @if($canSeeWaiting || $canFigures)
        <section class="et-section" aria-labelledby="et-inst-title">
            <div class="et-section-head">
                <h2 class="et-section-title" id="et-inst-title">{{ $is_teacher ? __('The institution today') : __('Today at a glance') }}</h2>
            </div>
            <div class="et-row">
                @if($canSeeWaiting)
                <div class="et-panel">
                    <div class="et-panel-head">
                        <h3 class="et-panel-title">{{ __('Waiting on you') }}</h3>
                        @if($waitingTotal)<span class="et-section-note">{{ trans_choice('{1} One item|[2,*] :n items', $waitingTotal, ['n' => $waitingTotal]) }}</span>@endif
                    </div>
                    @if($waitingTotal)
                        <ul class="et-todo">
                            @foreach($waiting as $item)
                            <li>
                                <a href="{{ route($item['route']) }}">
                                    <span class="et-count {{ $item['tone'] }}">{{ number_format($item['count']) }}</span>
                                    <span>
                                        <span class="et-todo-label">{{ $item['label'] }}</span>
                                        <span class="et-todo-note">{{ $item['note'] }}</span>
                                    </span>
                                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="et-empty"><i class="fas fa-check-circle" aria-hidden="true"></i> {{ __('Nothing is waiting on you. Everything is up to date.') }}</div>
                    @endif
                </div>
                @endif

                @if($canFigures)
                <div class="et-figures et-wide">
                    @if($canStudents)
                    @php $studentsTag = $me->can('student-view') ? 'a' : 'div'; @endphp
                    <{{ $studentsTag }} class="et-figure" @if($studentsTag === 'a') href="{{ route('admin.student.index') }}" @endif>
                        <p class="et-figure-label"><i class="fas fa-user-graduate" aria-hidden="true"></i> {{ __('Students') }}</p>
                        <p class="et-figure-value">{{ number_format($active_students) }} <small>{{ __('on roll') }}</small></p>
                        @php $enrolShare = $active_students > 0 ? min(100, round($enrolled_students / $active_students * 100)) : 0; @endphp
                        <div class="et-meter" aria-hidden="true"><span class="accent" style="width: {{ $enrolShare }}%"></span></div>
                        <p class="et-figure-sub">
                            <b>{{ number_format($enrolled_students) }}</b> {{ __('enrolled this term') }}
                            @can('student-enroll-complete') · <b>{{ number_format($course_completed) }}</b> {{ __('completed their course') }}@endcan
                        </p>
                    </{{ $studentsTag }}>
                    @endif

                    @if($canFees)
                    @php
                        $billed = $total_fees_collected + $total_fees_due;
                        $collectedShare = $billed > 0 ? round($total_fees_collected / $billed * 100) : 0;
                    @endphp
                    <a class="et-figure" href="{{ route('admin.fees-student.report') }}">
                        <p class="et-figure-label"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> {{ __('Fees') }}</p>
                        <p class="et-figure-value">{!! $money($total_fees_collected) !!} <small>{{ __('collected') }}</small></p>
                        <div class="et-meter" role="img" aria-label="{{ __(':p% of fees billed have been collected', ['p' => $collectedShare]) }}"><span class="good" style="width: {{ $collectedShare }}%"></span></div>
                        <p class="et-figure-sub"><b>{{ $collectedShare }}%</b> {{ __('of what was billed') }} · <b>{!! $money($total_fees_due) !!}</b> {{ __('still owed') }}</p>
                    </a>
                    @endif

                    @if($canAttendance)
                    @php
                        $marked = $today_present + $today_absent;
                        $presentShare = $marked > 0 ? round($today_present / $marked * 100) : 0;
                    @endphp
                    <a class="et-figure" href="{{ route('admin.student-attendance.index') }}">
                        <p class="et-figure-label"><i class="fas fa-clipboard-check" aria-hidden="true"></i> {{ __('Attendance today') }}</p>
                        @if($marked)
                            <p class="et-figure-value">{{ $presentShare }}% <small>{{ __('present') }}</small></p>
                            <div class="et-meter" aria-hidden="true"><span class="good" style="width: {{ $presentShare }}%"></span><span class="bad" style="width: {{ 100 - $presentShare }}%"></span></div>
                            <div class="et-key"><span class="good">{{ __(':n present', ['n' => number_format($today_present)]) }}</span><span class="bad">{{ __(':n absent', ['n' => number_format($today_absent)]) }}</span></div>
                        @else
                            <p class="et-figure-value">— <small>{{ __('not taken yet') }}</small></p>
                            <p class="et-figure-sub">{{ __('No attendance has been recorded today.') }}</p>
                        @endif
                    </a>
                    @endif

                    @if($canStaff)
                    @php $staffTag = $me->can('user-view') ? 'a' : 'div'; @endphp
                    <{{ $staffTag }} class="et-figure" @if($staffTag === 'a') href="{{ route('admin.user.index') }}" @endif>
                        <p class="et-figure-label"><i class="fas fa-users" aria-hidden="true"></i> {{ __('Staff') }}</p>
                        <p class="et-figure-value">{{ number_format($active_staffs) }} <small>{{ __('active') }}</small></p>
                        <p class="et-figure-sub">
                            @if($staff_on_leave)
                                <b>{{ number_format($staff_on_leave) }}</b> {{ __('on leave today') }}
                            @else
                                {{ __('No one is on leave today.') }}
                            @endif
                        </p>
                    </{{ $staffTag }}>
                    @endif
                </div>
                @endif
            </div>
        </section>
        @endif

        @if(($me->can('student-view') && count($breakdowns)) || count($dayItems) || count($frontItems))
        <section class="et-section">
            <div class="et-row">
                @if($me->can('student-view') && count($breakdowns))
                <div class="et-panel et-wide">
                    <div class="et-panel-head">
                        <h3 class="et-panel-title">{{ __('Where our students are') }}</h3>
                        @if(count($breakdowns) > 1)
                        <div class="et-tabs" role="tablist" aria-label="{{ __('Group students by') }}">
                            @foreach($breakdowns as $key => [$label, $rows])
                                <button type="button" class="et-tab" role="tab" id="et-tab-{{ $key }}" aria-controls="et-pane-{{ $key }}"
                                        aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div class="et-panel-body">
                        @foreach($breakdowns as $key => [$label, $rows])
                            @php $max = max(1, $rows->max(fn ($r) => $r[1])); @endphp
                            <div role="tabpanel" id="et-pane-{{ $key }}" aria-labelledby="et-tab-{{ $key }}" @unless($loop->first) hidden @endunless>
                                <ul class="et-bars">
                                    @foreach($rows->take(8) as [$name, $count])
                                        <li>
                                            <span class="et-bar-name" title="{{ $name }}">{{ $name }}</span>
                                            <span class="et-bar-count">{{ number_format($count) }}</span>
                                            <span class="et-bar-track" aria-hidden="true"><span class="et-bar-fill" style="width: {{ round($count / $max * 100, 1) }}%"></span></span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if($rows->count() > 8)
                                <details class="et-more">
                                    <summary>{{ __('Show all :n', ['n' => $rows->count()]) }}</summary>
                                    <ul class="et-bars">
                                        @foreach($rows->slice(8) as [$name, $count])
                                            <li>
                                                <span class="et-bar-name" title="{{ $name }}">{{ $name }}</span>
                                                <span class="et-bar-count">{{ number_format($count) }}</span>
                                                <span class="et-bar-track" aria-hidden="true"><span class="et-bar-fill" style="width: {{ round($count / $max * 100, 1) }}%"></span></span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(count($dayItems) || count($frontItems))
                <div class="et-panel">
                    <div class="et-panel-head">
                        <h3 class="et-panel-title">{{ __('The day book') }}</h3>
                        <span class="et-section-note">{{ $now->translatedFormat('j F') }}</span>
                    </div>
                    <ul class="et-daybook">
                        @foreach($dayItems as [$perm, $icon, $label, $count, $route])
                            <li class="{{ $count ? '' : 'is-zero' }}">
                                <a href="{{ route($route) }}"><i class="fas {{ $icon }}" aria-hidden="true"></i><span class="et-day-label">{{ $label }}</span><span class="et-day-count">{{ number_format($count) }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                    @if(count($frontItems))
                        <div class="et-subhead">{{ __('At the front desk today') }}</div>
                        <ul class="et-daybook">
                            @foreach($frontItems as [$perm, $icon, $label, $count, $route])
                                <li class="{{ $count ? '' : 'is-zero' }}">
                                    <a href="{{ route($route) }}"><i class="fas {{ $icon }}" aria-hidden="true"></i><span class="et-day-label">{{ $label }}</span><span class="et-day-count">{{ number_format($count) }}</span></a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @endif
            </div>
        </section>
        @endif

        {{-- ══ Every screen this person can open, grouped as in the menu ══ --}}
        @if(count($directory))
        <section class="et-section" aria-labelledby="et-dir-title">
            <div class="et-section-head">
                <h2 class="et-section-title" id="et-dir-title">{{ __('Go to') }}</h2>
                <span class="et-section-note">{{ __('Everything you can open, grouped as in the menu') }}</span>
            </div>
            <div class="et-directory">
                @foreach($directory as [$groupTitle, $groupIcon, $links])
                <nav class="et-dir-group" aria-label="{{ $groupTitle }}">
                    <p class="et-dir-title"><i class="fas {{ $groupIcon }}" aria-hidden="true"></i> {{ $groupTitle }}</p>
                    <ul class="et-dir-links">
                        @foreach($links as [$perm, $route, $label])
                            <li><a href="{{ route($route) }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
                @endforeach
            </div>
        </section>
        @endif

    </div>
    </div>
</div>

@endsection

@section('page_js')
<script>
    // "Where our students are": tabs, with the arrow keys moving between them.
    (function () {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('.et-tab'));
        if (!tabs.length) return;
        var select = function (tab) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
            });
        };
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { select(tab); });
            tab.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                select(next); next.focus(); e.preventDefault();
            });
        });
    })();
</script>
@endsection
