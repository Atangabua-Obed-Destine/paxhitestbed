@extends('student.layouts.master')
@section('title', $title)

{{--
    The student's day book.

    Laid out like the staff dashboard: today's date in the margin and a
    greeting, then what the day holds (the classes on the student's own
    timetable, with the one in progress and the next one marked), what is
    waiting on them, their messages, four figures with the context that makes
    them mean something, results and progress to graduation, and every screen
    of the portal grouped below. Figures come from Student\DashboardController.
--}}

@section('page_css')
@include('common.dashboard._daybook_style')
<style>
    .et-dash .et-live { background: var(--et-good-tint); border-color: #b9dcc7; }
    .et-dash .et-live .et-panel-title { color: var(--et-good); }
    .et-dash .et-live-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: center; padding: 12px 20px; border-top: 1px solid #cfe6d8; }
    .et-dash .et-class.is-break .et-class-title { font-weight: 500; color: var(--et-muted); font-style: italic; }
    .et-dash .et-class.is-break .et-class-node::after { border-style: dashed; }
    .et-dash .et-reasons { margin: 12px 0 0; padding: 10px 14px 10px 30px; border-radius: 8px; background: var(--et-warn-tint); color: var(--et-warn); font-size: 13px; }
    .et-dash .et-progress-row { padding: 10px 0; }
    .et-dash .et-progress-row + .et-progress-row { border-top: 1px solid var(--et-rule); }
    .et-dash .et-progress-head { display: flex; justify-content: space-between; font-size: 14px; color: var(--et-ink); }
    .et-dash .et-progress-head b { font-variant-numeric: tabular-nums; }
    .et-dash .et-verdict { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; font-weight: 700; }
    .et-dash .et-verdict.good { color: var(--et-good); }
    .et-dash .et-verdict.warn { color: var(--et-warn); }
    .et-dash .et-bar-fill.good { background: var(--et-good); }
    .et-dash .et-bar-fill.bad { background: var(--et-bad); }
</style>
@endsection

@section('content')
@php
    $now = now();
    $greeting = $now->hour < 12 ? __('Good morning') : ($now->hour < 17 ? __('Good afternoon') : __('Good evening'));
    $timeFormat = preg_match('/[aA]/', $setting->time_format ?? '') ? 'g:i A' : 'H:i';
    $at = fn ($time) => date($timeFormat, strtotime($time));
    $money = fn ($amount) => format_amount($amount) . ' ' . ($setting->currency_symbol ?? 'XAF');

    $needsRegistration = isset($registrationStatus) && in_array($registrationStatus['state'], ['open', 'partly_done'], true);
    $registrationDeadline = $registrationStatus['deadline'] ?? null;
    $missedDeadline = isset($registrationStatus) && $registrationStatus['state'] === 'closed' && $registrationStatus['registered'] === 0;

    $current = $nowNext['current'] ?? null;
    $next = $nowNext['next'] ?? null;
    $clock = $now->format('H:i:s');

    // Live classes in the student's class right now.
    $liveClasses = collect();
    if (isset($currentEnroll) && $currentEnroll) {
        $liveClasses = \App\Models\ClassSession::where('status', 'in_progress')
            ->where('program_id', $currentEnroll->program_id)->where('semester_id', $currentEnroll->semester_id)
            ->where('session_id', $currentEnroll->session_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $currentEnroll->section_id))
            ->with(['subject', 'teacher'])->get();
    }

    $unreadMessages = app(\App\Services\Mailbox\Mailbox::class)->unreadCount(['student', (int) $student->id]);
    $examsSoon = collect($upcomingExams ?? [])->filter(fn ($e) => $e->date && \Carbon\Carbon::parse($e->date)->lte($now->copy()->addDays(14)));

    // What is waiting on this student: only what needs doing, never a row of zeros.
    $waiting = array_values(array_filter([
        $needsRegistration ? ['count' => $registrationStatus['registered'] ?? 0, 'icon' => null, 'route' => route('student.course-registration.index'), 'label' => __('Register your courses'), 'note' => $registrationDeadline ? __('Before :when', ['when' => $registrationDeadline->closesAtLong()]) : __('Registration is open'), 'tone' => 'warn', 'chip' => '!'] : null,
        ($dueFees ?? 0) > 0 ? ['route' => route('student.fees.index'), 'label' => __('Fees still to pay'), 'note' => $money($dueFees) . ' · ' . __('paid so far: :p', ['p' => $money($paidFees ?? 0)]), 'tone' => 'bad', 'chip' => '<i class="fas fa-money-bill-wave"></i>'] : null,
        ($pendingAssignments ?? 0) > 0 ? ['route' => route('student.assignment.index'), 'label' => __('Assignments to hand in'), 'note' => __('Not yet submitted'), 'tone' => '', 'chip' => (string) $pendingAssignments] : null,
        $unreadMessages > 0 ? ['route' => route('student.mailbox.index'), 'label' => __('Unread messages'), 'note' => __('From your lecturers or the school offices'), 'tone' => '', 'chip' => (string) $unreadMessages] : null,
        $examsSoon->count() ? ['route' => route('student.exam-routine.index'), 'label' => __('Exams in the next two weeks'), 'note' => __('First: :course on :date', ['course' => $examsSoon->first()->subject->code ?? $examsSoon->first()->subject->title ?? '', 'date' => \Carbon\Carbon::parse($examsSoon->first()->date)->translatedFormat('D j M')]), 'tone' => 'warn', 'chip' => (string) $examsSoon->count()] : null,
    ]));

    $attended = $totalAttendance ?? 0;
    $passShare = ($totalCourses ?? 0) > 0 ? round($passedCourses / $totalCourses * 100) : 0;
    $paidShare = ($totalFees ?? 0) > 0 ? min(100, round(($paidFees ?? 0) / $totalFees * 100)) : 0;
    $attendShare = round($attendancePercentage ?? 0);

    $typeMap = [0 => [__('Optional'), 'muted'], 1 => [__('Compulsory'), 'bad'], 2 => [__('University req.'), 'warn']];
@endphp

<div class="main-body">
    <div class="page-wrapper">
    <div class="et-dash">

        {{-- Both notices below carry the deadline themselves; tell the layout not to add its strip. --}}
        @if(($needsRegistration && $registrationDeadline) || $missedDeadline)
            @section('registration_deadline_shown', '1')
        @endif

        @if($missedDeadline)
        <div class="et-alert bad" role="alert">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <div>
                <h3>{{ $registrationStatus['headline'] }}</h3>
                <p>{{ $registrationStatus['detail'] }}</p>
                @if($registrationDeadline && trim((string) $registrationDeadline->note) !== '')<p class="et-when">{{ $registrationDeadline->note }}</p>@endif
            </div>
        </div>
        @endif

        @if($needsRegistration)
        <div class="et-alert {{ $registrationDeadline && $registrationDeadline->urgency() === 'imminent' ? 'bad' : 'warn' }}" role="alert">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            <div>
                <h3>{{ $registrationStatus['headline'] }}</h3>
                <p>{{ $registrationStatus['detail'] }}</p>
                @if($registrationDeadline)
                    <p class="et-when"><i class="fas fa-hourglass-half" aria-hidden="true"></i> {{ __('Register before :when', ['when' => $registrationDeadline->closesAtLong()]) }} · {{ $registrationDeadline->countdown() }}</p>
                @endif
            </div>
            <a href="{{ route('student.course-registration.index') }}" class="et-btn"><i class="fas fa-arrow-right" aria-hidden="true"></i> {{ __('Register my courses') }}</a>
        </div>

        <div class="modal fade" id="courseRegistrationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="fas fa-clipboard-list"></i> {{ __('Course registration is open') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <h6 class="font-weight-bold">{{ $registrationStatus['headline'] }}</h6>
                        <p>{{ $registrationStatus['detail'] }}</p>
                        @if($registrationDeadline)
                        <div class="alert alert-{{ $registrationDeadline->colour() }} py-2 mb-2">
                            <i class="fas fa-hourglass-half"></i> <strong>{{ __('Deadline') }}:</strong> {{ $registrationDeadline->closesAtLong() }}
                            &mdash; <strong>{{ $registrationDeadline->countdown() }}</strong>. {{ __('After that, you will not be able to register on the portal.') }}
                        </div>
                        @endif
                        @if($registrationStatus['enrollment'] && $registrationStatus['enrollment']->semester)
                        <p class="mb-2 text-muted small">{{ __('field_semester') }}: <strong>{{ $registrationStatus['enrollment']->semester->title }}</strong>
                            @if($registrationStatus['enrollment']->session) &mdash; {{ $registrationStatus['enrollment']->session->title }}@endif</p>
                        @endif
                        <div class="alert alert-light border mb-0 small"><i class="fas fa-info-circle"></i> {{ __('Until you register, you will not appear on the class list or the exam list for these courses.') }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('I will do it later') }}</button>
                        <a href="{{ route('student.course-registration.index') }}" class="btn btn-warning"><i class="fas fa-arrow-right"></i> {{ __('Take me there') }}</a>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('courseRegistrationModal');
            if (!modalEl || typeof bootstrap === 'undefined') { return; }
            // Once a visit; the notice above stays put.
            var seen = null;
            try { seen = sessionStorage.getItem('courseRegistrationPrompt'); } catch (e) {}
            if (seen === '{{ $registrationStatus['available'] }}') { return; }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            try { sessionStorage.setItem('courseRegistrationPrompt', '{{ $registrationStatus['available'] }}'); } catch (e) {}
        });
        </script>
        @endpush
        @endif

        @if(isset($progressionStatus) && $progressionStatus['status'] == 'waiting_for_session')
        <div class="et-alert info" role="status">
            <i class="fas fa-hourglass-half" aria-hidden="true"></i>
            <div>
                <h3>{{ __('Semester completed') }}</h3>
                <p>{{ $progressionStatus['message'] }} {{ __('You have met the requirements to move on; this page updates once the new academic session begins.') }}</p>
            </div>
        </div>
        @endif

        {{-- ══ Masthead ══ --}}
        <header class="et-mast">
            <div class="et-date" aria-hidden="true">
                <span class="et-date-day">{{ $now->format('j') }}</span>
                <span class="et-date-week">{{ $now->translatedFormat('l') }}</span>
                <span class="et-date-month">{{ $now->translatedFormat('M Y') }}</span>
            </div>
            <div class="et-mast-main">
                <p class="et-eyebrow">{{ collect([optional(optional($currentEnroll)->program)->title, optional(optional($currentEnroll)->semester)->title])->filter()->implode(' · ') ?: institution_name() }}</p>
                <h1 class="et-greet">{{ $greeting }}, {{ $student->first_name }}.</h1>
                <p class="et-lede">
                    @if($current)
                        {!! __('You are in :course right now, until :end.', ['course' => '<strong>' . e(trim(($current->subject->code ?? '') . ' ' . ($current->subject->title ?? ''))) . '</strong>', 'end' => e($at($current->end_time))]) !!}
                        @if($next) {!! __('Next is :course at :time.', ['course' => '<strong>' . e($next->subject->code ?? $next->subject->title ?? '') . '</strong>', 'time' => e($at($next->start_time))]) !!} @endif
                    @elseif($next)
                        {{ trans_choice('{1} You have one class today.|[2,*] You have :n classes today.', $todayClasses->count(), ['n' => $todayClasses->count()]) }}
                        {!! __('The next is :course at :time:room.', [
                            'course' => '<strong>' . e(trim(($next->subject->code ?? '') . ' ' . ($next->subject->title ?? ''))) . '</strong>',
                            'time' => e($at($next->start_time)),
                            'room' => $next->room ? ', ' . e(__('in :room', ['room' => $next->room->title])) : '',
                        ]) !!}
                    @elseif($todayClasses->count())
                        {{ __('Your classes are over for today.') }}
                    @else
                        {{ __('No classes on your timetable today.') }}
                    @endif
                    @if(count($waiting))
                        {{ trans_choice('{1} One thing is waiting on you below.|[2,*] :n things are waiting on you below.', count($waiting), ['n' => count($waiting)]) }}
                    @endif
                </p>
            </div>
            <div class="et-mast-side">
                @if(isset($currentEnroll) && $currentEnroll)
                    <span class="et-chip"><i class="fas fa-id-card" aria-hidden="true"></i> {{ $currentEnroll->matricule ?? $student->student_id }}</span>
                    @if($currentEnroll->session)<span class="et-chip"><i class="fas fa-graduation-cap" aria-hidden="true"></i> {{ __('Academic year :year', ['year' => $currentEnroll->session->title]) }}</span>@endif
                @endif
                <span class="et-clock">{{ $now->translatedFormat('l, j F Y') }}</span>
            </div>
        </header>

        {{-- ══ Live classes ══ --}}
        @if($liveClasses->count())
        <section class="et-section" aria-label="{{ __('Classes in progress') }}">
            <div class="et-panel et-live">
                <div class="et-panel-head">
                    <h2 class="et-panel-title"><span class="et-live-dot" aria-hidden="true"></span> {{ trans_choice('{1} A class is live now|[2,*] :n classes are live now', $liveClasses->count(), ['n' => $liveClasses->count()]) }}</h2>
                    <a class="et-panel-link" href="{{ route('student.class-hub.index') }}">{{ __('Class Hub') }} →</a>
                </div>
                @foreach($liveClasses as $live)
                <div class="et-live-row">
                    <div>
                        <strong>{{ trim(($live->subject->code ?? '') . ' ' . ($live->subject->title ?? __('Class in session'))) }}</strong>
                        <div class="et-figure-sub">{{ trim(($live->teacher->first_name ?? '') . ' ' . ($live->teacher->last_name ?? '')) }}@if($live->actual_start_time) · {{ __('started :when', ['when' => \Carbon\Carbon::parse($live->actual_start_time)->diffForHumans()]) }}@endif</div>
                    </div>
                    <a href="{{ route('student.class-hub.live-room', $live->id) }}" class="et-btn good"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> {{ __('Join now') }}</a>
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ══ Today ══ --}}
        <section class="et-section" aria-labelledby="et-today-title">
            <div class="et-row">
                <div class="et-panel et-wide">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title" id="et-today-title">{{ __('Your classes today') }}</h2>
                        <a class="et-panel-link" href="{{ route('student.class-routine.index') }}">{{ __('Full timetable') }} →</a>
                    </div>
                    @if($todayAgenda->count())
                        <ol class="et-timeline">
                            @foreach($todayAgenda as $row)
                                @php
                                    // Breaks arrive as plain rows marked is_break (TimetableBreak::forClasses).
                                    $isBreak = !empty($row->is_break);
                                    $start = date('H:i:s', strtotime($row->start_time));
                                    $end = date('H:i:s', strtotime($row->end_time));
                                    $state = $isBreak ? 'break' : ($end <= $clock ? 'done' : ($start <= $clock ? 'now' : ($next && $row->id === $next->id ? 'next' : 'later')));
                                @endphp
                                <li class="et-class is-{{ $state }}">
                                    <div class="et-class-time"><b>{{ $at($row->start_time) }}</b><span>{{ __('to :time', ['time' => $at($row->end_time)]) }}</span></div>
                                    <div class="et-class-node" aria-hidden="true"></div>
                                    <div>
                                        @if($isBreak)
                                            <p class="et-class-title">{{ $row->title }}</p>
                                        @else
                                            <p class="et-class-title">
                                                @if($row->subject->code ?? false)<span class="et-code">{{ $row->subject->code }}</span>@endif{{ $row->subject->title ?? '' }}
                                                @if($state === 'now')<span class="et-state now">{{ __('In progress') }}</span>
                                                @elseif($state === 'next')<span class="et-state next">{{ __('Next') }}</span>
                                                @elseif($state === 'done')<span class="et-state done">{{ __('Finished') }}</span>@endif
                                            </p>
                                            <p class="et-class-meta">
                                                {{ collect([$row->room->title ?? null, $row->teacher ? trim($row->teacher->first_name . ' ' . $row->teacher->last_name) : null])->filter()->implode(' · ') }}
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="et-empty"><i class="fas fa-mug-hot" aria-hidden="true"></i>
                            {{ ($weekClassCount ?? 0) ? __('No classes on your timetable today.') : __('Your class timetable has not been published yet.') }}
                        </div>
                    @endif
                </div>

                <div class="et-panel">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title">{{ __('Waiting on you') }}</h2>
                        @if(count($waiting))<span class="et-section-note">{{ trans_choice('{1} One item|[2,*] :n items', count($waiting), ['n' => count($waiting)]) }}</span>@endif
                    </div>
                    @if(count($waiting))
                        <ul class="et-todo">
                            @foreach($waiting as $item)
                            <li>
                                <a href="{{ $item['route'] }}">
                                    <span class="et-count {{ $item['tone'] }}">{!! $item['chip'] !!}</span>
                                    <span><span class="et-todo-label">{{ $item['label'] }}</span><span class="et-todo-note">{{ $item['note'] }}</span></span>
                                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="et-empty"><i class="fas fa-check-circle" aria-hidden="true"></i> {{ __('Nothing is waiting on you. You are up to date.') }}</div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ══ Messages and what is coming up ══ --}}
        <section class="et-section" aria-label="{{ __('Messages and what is coming up') }}">
            <div class="et-row">
                @include('common.dashboard._messages_panel', ['mbRoute' => 'student.mailbox', 'mbMember' => ['student', (int) $student->id]])

                <div class="et-panel">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title">{{ __('Coming up') }}</h2>
                        <a class="et-panel-link" href="{{ route('student.exam-routine.index') }}">{{ __('Exam timetable') }} →</a>
                    </div>
                    @php
                        $coming = collect($upcomingExams ?? [])->map(fn ($e) => ['when' => \Carbon\Carbon::parse($e->date), 'icon' => 'fa-file-alt', 'label' => trim(($e->subject->code ?? '') . ' ' . ($e->subject->title ?? __('Exam'))), 'note' => __('Exam') . ($e->start_time ? ' · ' . $at($e->start_time) : '')])
                            ->concat(collect($latest_events ?? [])->map(fn ($ev) => ['when' => \Carbon\Carbon::parse($ev->start_date), 'icon' => 'fa-calendar-check', 'label' => $ev->title, 'note' => $ev->start_date != $ev->end_date ? __('until :d', ['d' => \Carbon\Carbon::parse($ev->end_date)->translatedFormat('j M')]) : __('Event')]))
                            ->sortBy('when')->take(6)->values();
                    @endphp
                    @if($coming->count())
                        <ul class="et-daybook">
                            @foreach($coming as $c)
                            <li><div class="et-line">
                                <i class="fas {{ $c['icon'] }}" aria-hidden="true"></i>
                                <span class="et-day-label"><b style="color:var(--et-ink)">{{ $c['label'] }}</b><br><span class="et-todo-note">{{ $c['note'] }}</span></span>
                                <span class="et-day-count" style="font-size:13px">{{ $c['when']->isToday() ? __('Today') : $c['when']->translatedFormat('D j M') }}</span>
                            </div></li>
                            @endforeach
                        </ul>
                    @else
                        <div class="et-empty"><i class="fas fa-calendar" aria-hidden="true" style="color: var(--et-faint)"></i> {{ __('No exams or events coming up.') }}</div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ══ Figures ══ --}}
        <section class="et-section" aria-labelledby="et-figures-title">
            <div class="et-section-head">
                <h2 class="et-section-title" id="et-figures-title">{{ __('Your studies at a glance') }}</h2>
            </div>
            <div class="et-figures">
                <a class="et-figure" href="{{ route('student.transcript.index') }}">
                    <p class="et-figure-label"><i class="fas fa-chart-line" aria-hidden="true"></i> {{ __('Grade point average') }}</p>
                    <p class="et-figure-value">{{ number_format($cgpa ?? 0, 2) }} <small>/ 4.00</small></p>
                    <div class="et-meter" aria-hidden="true"><span class="accent" style="width: {{ min(100, round(($cgpa ?? 0) / 4 * 100)) }}%"></span></div>
                    <p class="et-figure-sub"><b>{{ $totalCredits ?? 0 }}</b> {{ __('credits earned') }}</p>
                </a>
                <a class="et-figure" href="{{ route('student.exam-results.index') }}">
                    <p class="et-figure-label"><i class="fas fa-book-reader" aria-hidden="true"></i> {{ __('Courses passed') }}</p>
                    <p class="et-figure-value">{{ $passedCourses ?? 0 }} <small>{{ __('of :n', ['n' => $totalCourses ?? 0]) }}</small></p>
                    <div class="et-meter" role="img" aria-label="{{ __(':p% of courses taken have been passed', ['p' => $passShare]) }}"><span class="good" style="width: {{ $passShare }}%"></span>@if(($failedCourses ?? 0) > 0)<span class="bad" style="width: {{ 100 - $passShare }}%"></span>@endif</div>
                    <p class="et-figure-sub">
                        @if(($failedCourses ?? 0) > 0)<b>{{ $failedCourses }}</b> {{ __('still to pass') }}@else{{ __('Every course taken is passed.') }}@endif
                    </p>
                </a>
                <a class="et-figure" href="{{ route('student.attendance.index') }}">
                    <p class="et-figure-label"><i class="fas fa-clipboard-check" aria-hidden="true"></i> {{ __('Attendance this semester') }}</p>
                    @if($attended)
                        <p class="et-figure-value">{{ $attendShare }}% <small>{{ __('present') }}</small></p>
                        <div class="et-meter" aria-hidden="true"><span class="{{ $attendShare >= 75 ? 'good' : 'bad' }}" style="width: {{ $attendShare }}%"></span></div>
                        <p class="et-figure-sub"><b>{{ $presentAttendance }}</b> {{ __('of :n classes', ['n' => $totalAttendance]) }}</p>
                    @else
                        <p class="et-figure-value">— <small>{{ __('no register yet') }}</small></p>
                        <p class="et-figure-sub">{{ __('Shown once your lecturers take attendance.') }}</p>
                    @endif
                </a>
                <a class="et-figure" href="{{ route('student.fees.index') }}">
                    <p class="et-figure-label"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> {{ __('Fees') }}</p>
                    <p class="et-figure-value">{{ format_amount($dueFees ?? 0) }} <small>{{ __('to pay') }}</small></p>
                    <div class="et-meter" role="img" aria-label="{{ __(':p% of your fees are paid', ['p' => $paidShare]) }}"><span class="good" style="width: {{ $paidShare }}%"></span></div>
                    <p class="et-figure-sub"><b>{{ $paidShare }}%</b> {{ __('paid') }} · {{ $money($paidFees ?? 0) }}</p>
                </a>
            </div>
        </section>

        {{-- ══ Results and progress ══ --}}
        <section class="et-section" aria-label="{{ __('Results and progress') }}">
            <div class="et-row">
                <div class="et-panel et-wide">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title">{{ __('Latest results') }}</h2>
                        <a class="et-panel-link" href="{{ route('student.exam-results.index') }}">{{ __('All results') }} →</a>
                    </div>
                    <div class="et-panel-body">
                        @if(isset($latestMarks) && $latestMarks->count())
                            <ul class="et-bars">
                                @foreach($latestMarks as $mark)
                                <li>
                                    <span class="et-bar-name">@if($mark->subject->code ?? false)<span class="et-code">{{ $mark->subject->code }}</span>@endif{{ $mark->subject->title ?? '' }}</span>
                                    <span class="et-bar-count">{{ rtrim(rtrim(number_format($mark->total_marks, 2), '0'), '.') }}<small style="font-weight:600;color:var(--et-muted)">/100</small></span>
                                    <span class="et-bar-track"><span class="et-bar-fill {{ $mark->total_marks >= 50 ? 'good' : 'bad' }}" style="width: {{ min(100, max(0, $mark->total_marks)) }}%"></span></span>
                                </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="et-empty" style="padding:0"><i class="fas fa-hourglass-half" aria-hidden="true" style="color: var(--et-faint)"></i> {{ __('No results published yet this semester.') }}</div>
                        @endif
                    </div>
                </div>

                @if(isset($graduationEligibility))
                @php $g = $graduationEligibility; $pct = fn ($a, $b) => $b > 0 ? min(100, round($a / $b * 100)) : 0; @endphp
                <div class="et-panel">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title">{{ __('Towards graduation') }}</h2>
                        <a class="et-panel-link" href="{{ route('student.transcript.index') }}">{{ __('Transcript') }} →</a>
                    </div>
                    <div class="et-panel-body">
                        <p class="et-verdict {{ $g['is_eligible'] ? 'good' : 'warn' }}">
                            <i class="fas {{ $g['is_eligible'] ? 'fa-check-circle' : 'fa-hourglass-half' }}" aria-hidden="true"></i>
                            {{ $g['is_eligible'] ? __('You meet the requirements to graduate.') : __('Not yet eligible to graduate.') }}
                        </p>
                        @foreach([
                            [__('Compulsory courses'), $g['compulsory']['passed_subjects'], $g['compulsory']['total_subjects']],
                            [__('University requirements'), $g['university_requirement']['passed_subjects'], $g['university_requirement']['total_subjects']],
                            [__('Credits'), $g['completed_credits'], $g['total_credits']],
                        ] as [$label, $have, $need])
                        <div class="et-progress-row">
                            <div class="et-progress-head"><span>{{ $label }}</span><b>{{ $have }} / {{ $need }}</b></div>
                            <div class="et-bar-track" style="margin-top:6px"><span class="et-bar-fill" style="width: {{ $pct($have, $need) }}%"></span></div>
                        </div>
                        @endforeach
                        @if(!$g['is_eligible'] && !empty($g['reasons']))
                        <ul class="et-reasons">@foreach($g['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </section>

        {{-- ══ Notices and assignments ══ --}}
        @if((isset($latestNotices) && $latestNotices->count()) || (isset($assignments) && $assignments->count()))
        <section class="et-section" aria-label="{{ __('Notices and assignments') }}">
            <div class="et-row">
                @if(isset($latestNotices) && $latestNotices->count())
                <div class="et-panel">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title">{{ __('Notices') }}</h2>
                        <a class="et-panel-link" href="{{ route('student.notice.index') }}">{{ __('All notices') }} →</a>
                    </div>
                    <ul class="et-daybook">
                        @foreach($latestNotices->take(4) as $notice)
                        <li><a href="{{ route('student.notice.index') }}">
                            <i class="fas fa-bullhorn" aria-hidden="true"></i>
                            <span class="et-day-label">{{ \Illuminate\Support\Str::limit($notice->title, 70) }}</span>
                            <span class="et-todo-note">{{ \Carbon\Carbon::parse($notice->date ?? $notice->created_at)->translatedFormat('j M') }}</span>
                        </a></li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if(isset($assignments) && $assignments->count())
                <div class="et-panel">
                    <div class="et-panel-head">
                        <h2 class="et-panel-title">{{ __('Assignments') }}</h2>
                        <a class="et-panel-link" href="{{ route('student.assignment.index') }}">{{ __('All assignments') }} →</a>
                    </div>
                    <ul class="et-daybook">
                        @foreach($assignments as $a)
                            @continue(!$a->assignment || $a->assignment->status != 1)
                            <li><a href="{{ route('student.assignment.show', $a->id) }}">
                                <i class="fas {{ $a->attendance == 1 ? 'fa-check-circle' : 'fa-clock' }}" aria-hidden="true" style="color: {{ $a->attendance == 1 ? 'var(--et-good)' : 'var(--et-warn)' }}"></i>
                                <span class="et-day-label">{{ \Illuminate\Support\Str::limit($a->assignment->title, 60) }}<br><span class="et-todo-note">{{ $a->assignment->subject->code ?? '' }} · {{ __('due :d', ['d' => \Carbon\Carbon::parse($a->assignment->end_date)->translatedFormat('D j M')]) }}</span></span>
                                <span class="et-pill {{ $a->attendance == 1 ? 'good' : 'warn' }}">{{ $a->attendance == 1 ? __('Submitted') : __('To do') }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </section>
        @endif

        {{-- ══ Course overview ══ --}}
        @if(isset($courseBreakdown) && count($courseBreakdown))
        <section class="et-section" aria-labelledby="et-courses-title">
            <div class="et-section-head">
                <h2 class="et-section-title" id="et-courses-title">{{ __('Your courses, semester by semester') }}</h2>
                <span class="et-section-note">{{ __('Open a semester to see each course, its grade and attempts.') }}</span>
            </div>
            <div class="et-panel">
                @foreach($courseBreakdown as $sem)
                    @php
                        $passedHere = collect($sem['courses'])->filter(fn ($c) => $c['validated'] && ($c['marks']['passed'] ?? false))->count();
                    @endphp
                    <details class="et-semester" @if($loop->last) open @endif>
                        <summary>
                            {{ $sem['semester']->title }}@if($sem['is_resit']) · {{ __('Resit') }}@endif
                            <span>{{ optional($sem['session'])->title }} · {{ trans_choice('{1} one course|[2,*] :n courses', count($sem['courses']), ['n' => count($sem['courses'])]) }} · {{ __(':n passed', ['n' => $passedHere]) }}</span>
                        </summary>
                        <div class="et-table-wrap">
                            <table class="et-table">
                                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Course') }}</th><th class="num">{{ __('Credits') }}</th><th>{{ __('Type') }}</th><th>{{ __('Result') }}</th><th class="num">{{ __('Mark') }}</th><th class="num">{{ __('Attempt') }}</th></tr></thead>
                                <tbody>
                                    @foreach($sem['courses'] as $course)
                                    @php $type = $typeMap[$course['type']] ?? [__('Other'), 'muted']; @endphp
                                    <tr>
                                        <td><span class="et-code">{{ $course['code'] }}</span></td>
                                        <td>{{ $course['name'] }}</td>
                                        <td class="num">{{ $course['credits'] }}</td>
                                        <td><span class="et-pill {{ $type[1] }}">{{ $type[0] }}</span></td>
                                        <td>
                                            @if($course['validated'])
                                                @if($course['marks']['passed'] ?? false)<span class="et-pill good">{{ __('Passed') }}</span>@else<span class="et-pill bad">{{ __('Failed') }}</span>@endif
                                            @else
                                                <span class="et-pill muted">{{ __('Pending') }}</span>
                                            @endif
                                        </td>
                                        <td class="num">@if($course['validated'] && $course['marks']){{ $course['marks']['grade'] ?? '' }} · {{ $course['marks']['marks'] }}@else — @endif</td>
                                        <td class="num">{{ $course['attempts'] }}@if($course['resit_info']) <span class="et-pill warn">{{ __('Resit') }}</span>@endif</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ══ Everything in the portal ══ --}}
        <section class="et-section" aria-labelledby="et-dir-title">
            <div class="et-section-head">
                <h2 class="et-section-title" id="et-dir-title">{{ __('Everything in your portal') }}</h2>
            </div>
            @php
                $directory = [
                    [__('Studies'), 'fa-book', [
                        ['student.course-registration.index', __('Course registration')],
                        ['student.class-routine.index', __('Class timetable')],
                        ['student.exam-routine.index', __('Exam timetable')],
                        ['student.exam-results.index', __('Exam results')],
                        ['student.transcript.index', __('Transcript')],
                        ['student.resit.index', __('Resits')],
                    ]],
                    [__('Learning'), 'fa-chalkboard', [
                        ['student.class-hub.index', __('Class Hub')],
                        ['student.assignment.index', __('Assignments')],
                        ['student.download.index', __('Downloads')],
                        ['student.e-library.index', __('E-Library')],
                        ['student.library.index', __('Library')],
                    ]],
                    [__('Fees'), 'fa-money-bill-wave', [
                        ['student.fees.index', __('Fees and receipts')],
                        ['student.manual-payment.index', __('Upload a payment')],
                        ['student.multi-payment.index', __('Pay several fees')],
                        ['student.payment-plan.index', __('Payment plans')],
                    ]],
                    [__('Me'), 'fa-user', [
                        ['student.mailbox.index', __('Messages')],
                        ['student.notice.index', __('Notices')],
                        ['student.attendance.index', __('Attendance')],
                        ['student.leave.index', __('Leave')],
                        ['student.form-a2.index', __('Form A2')],
                        ['student.profile.index', __('My profile')],
                    ]],
                ];
            @endphp
            <div class="et-directory">
                @foreach($directory as [$groupTitle, $icon, $links])
                <div class="et-dir-group">
                    <h3 class="et-dir-title"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $groupTitle }}</h3>
                    <ul class="et-dir-links">
                        @foreach($links as [$name, $label])
                            @if(\Illuminate\Support\Facades\Route::has($name))
                            <li><a href="{{ route($name) }}">{{ $label }}</a></li>
                            @endif
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </section>

    </div>
    </div>
</div>
@endsection
