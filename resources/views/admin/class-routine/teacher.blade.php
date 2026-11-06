@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<link rel="stylesheet" href="{{ asset('dashboard/css/pages/admin-ui.css') }}?v={{ filemtime(public_path('dashboard/css/pages/admin-ui.css')) }}">
<link rel="stylesheet" href="{{ asset('dashboard/css/pages/teacher-routine.css') }}?v={{ filemtime(public_path('dashboard/css/pages/teacher-routine.css')) }}">
@endpush

@section('content')

@php
    $dayNames = [
        1 => __('day_saturday'), 2 => __('day_sunday'), 3 => __('day_monday'), 4 => __('day_tuesday'),
        5 => __('day_wednesday'), 6 => __('day_thursday'), 7 => __('day_friday'),
    ];
    $timeFormat = \App\Support\TeacherTimetable::timeFormat($setting->time_format ?? null);
    $is12h = (bool) preg_match('/[aA]/', $timeFormat);
    $fmtTime = fn (int $minutes) => gmdate($timeFormat, $minutes * 60);
    $fmtHour = fn (int $minutes) => gmdate($is12h ? 'g A' : 'H:i', $minutes * 60);
    $today = \App\Support\TeacherTimetable::todayNumber();
    $hourPx = 64;
    $tt = $timetable ?? null;
@endphp

<div class="main-body">
    <div class="page-wrapper tr-page">
        <!-- [ breadcrumb ] start -->
        <div class="page-header tr-no-print">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5>{{ $title }}</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_routine', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="#!">{{ $title }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- Filter -->
        <div class="card tr-filter tr-no-print">
            <div class="card-header">
                <h5>{{ __('Filter') }}</h5>
            </div>
            <div class="card-body">
                <form class="needs-validation" novalidate method="get" action="{{ route($route.'.teacher') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-5 col-md-6">
                            <label for="teacher">{{ __('Teacher') }} <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="teacher" id="teacher" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @if($selected_staff == $teacher->id) selected @endif>{{ $teacher->staff_id }} - {{ $teacher->first_name }} {{ $teacher->last_name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">{{ __('required_field') }} {{ __('Teacher') }}</div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="session">{{ __('field_session') }}</label>
                            <select class="form-control" name="session" id="session">
                                @foreach($sessions as $sess)
                                <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>
                                    {{ $sess->title }}@if($sess->current == '1') ({{ __('Current') }})@endif
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-12">
                            <div class="tr-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                @if($tt && $tt->slots->isNotEmpty())
                                <button type="button" class="btn btn-dark" onclick="window.print()"><i class="fas fa-print"></i> {{ __('Print') }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if(!$teacher_obj)
            {{-- Nothing chosen yet --}}
            <div class="card">
                <div class="tr-empty">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <strong>{{ __('Choose a teacher to see their week') }}</strong>
                    <span>{{ __('Pick a teacher and a session above, then press Search.') }}</span>
                </div>
            </div>
        @else
            {{-- Who, and how much they teach --}}
            <div class="card tr-summary">
                <div class="card-body">
                    <div class="tr-who">
                        <span class="tr-avatar">{{ strtoupper(mb_substr($teacher_obj->first_name ?? '', 0, 1).mb_substr($teacher_obj->last_name ?? '', 0, 1)) }}</span>
                        <div>
                            <h5 class="tr-name">{{ $teacher_obj->first_name }} {{ $teacher_obj->last_name }}</h5>
                            <span class="tr-sub">{{ $teacher_obj->staff_id }} · {{ $session_obj->title ?? '' }}</span>
                        </div>
                    </div>
                    @if($tt && $tt->slots->isNotEmpty())
                    <dl class="tr-figures">
                        <div><dt>{{ __('Periods / week') }}</dt><dd>{{ $tt->stats['periods'] }}</dd></div>
                        <div><dt>{{ __('Hours / week') }}</dt><dd>{{ \App\Support\TeacherTimetable::duration($tt->stats['minutes']) }}</dd></div>
                        <div><dt>{{ __('Courses') }}</dt><dd>{{ $tt->stats['courses'] }}</dd></div>
                        <div><dt>{{ __('Programs') }}</dt><dd>{{ $tt->stats['programs'] }}</dd></div>
                    </dl>
                    @endif
                </div>

                @if($tt && $tt->slots->isNotEmpty())
                <div class="tr-legend">
                    <span class="tr-legend-label">{{ __('Courses') }}</span>
                    <div class="tr-legend-items">
                        @foreach($tt->courses as $course)
                        @php [$tint, $accent] = \App\Support\TeacherTimetable::PALETTE[$course->colour]; @endphp
                        <button type="button" class="tr-chip" data-code="{{ $course->code }}" style="--tint: {{ $tint }}; --accent: {{ $accent }};"
                                title="{{ __('Highlight this course') }}">
                            <span class="tr-chip-code">{{ $course->code }}</span>
                            <span class="tr-chip-title">{{ $course->title }}</span>
                            <span class="tr-chip-count">{{ $course->periods }} {{ $course->periods == 1 ? __('period') : __('periods') }} · {{ \App\Support\TeacherTimetable::duration($course->minutes) }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            @if(!$tt || $tt->slots->isEmpty())
                <div class="card">
                    <div class="tr-empty">
                        <i class="far fa-calendar"></i>
                        <strong>{{ __('No classes in this session') }}</strong>
                        <span>{{ $teacher_obj->first_name }} {{ $teacher_obj->last_name }} {{ __('has no timetable for') }} {{ $session_obj->title ?? __('this session') }}. {{ __('Try another session above.') }}</span>
                    </div>
                </div>
            @else
                {{-- Things worth a second look --}}
                @if($tt->clashCount || $tt->longCount)
                <div class="tr-notices tr-no-print">
                    @if($tt->clashCount)
                    <div class="tr-notice is-clash">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><strong>{{ $tt->clashCount }} {{ __('periods overlap') }}</strong> — {{ __('the teacher is booked in two places at once. They are outlined in red.') }}</span>
                    </div>
                    @endif
                    @if($tt->longCount)
                    <div class="tr-notice is-long">
                        <i class="fas fa-hourglass-half"></i>
                        <span><strong>{{ $tt->longCount }} {{ $tt->longCount == 1 ? __('period is') : __('periods are') }} {{ __('longer than 6 hours') }}</strong> — {{ __('the end time was probably mistyped. They have a dashed amber border.') }}</span>
                    </div>
                    @endif
                </div>
                @endif

                <div class="card tr-board" id="tr-board">
                    <div class="card-header ui-card-header">
                        <h5>{{ __('Weekly timetable') }}</h5>
                        <div class="btn-group btn-group-sm tr-toggle tr-no-print" role="group" aria-label="{{ __('View') }}">
                            <button type="button" class="btn btn-outline-secondary" data-view="week"><i class="fas fa-th"></i> {{ __('Week') }}</button>
                            <button type="button" class="btn btn-outline-secondary" data-view="list"><i class="fas fa-list"></i> {{ __('List') }}</button>
                        </div>
                    </div>

                    @php
                        $hours = intdiv($tt->gridEnd - $tt->gridStart, 60);
                        $cols = '64px';
                        foreach ($dayNames as $d => $n) {
                            $cols .= $tt->days[$d]->isEmpty() ? ' 56px' : ' minmax(128px, 1fr)';
                        }

                        // Everything the detail panel shows, per slot.
                        $slotData = function ($slot) use ($dayNames, $fmtTime) {
                            return [
                                'code' => $slot->code,
                                'title' => $slot->title,
                                'day' => $dayNames[$slot->day] ?? '',
                                'time' => $fmtTime($slot->start).' – '.$fmtTime($slot->end),
                                'duration' => \App\Support\TeacherTimetable::duration($slot->duration),
                                'room' => $slot->rooms ? implode(', ', $slot->rooms) : '—',
                                'run' => $slot->run_index ? $slot->run_index.' / '.$slot->run_length : null,
                                'groups' => $slot->groups,
                                'clash' => $slot->clash,
                                'long' => $slot->long,
                            ];
                        };
                        $groupsLabel = fn ($slot) => count($slot->groups) > 1
                            ? count($slot->groups).' '.__('programs')
                            : ($slot->groups[0]['program'] ?? '');
                    @endphp

                    {{-- Week view --}}
                    <div class="tr-week-wrap" data-panel="week">
                        <div class="tr-week" style="--tr-cols: {{ $cols }}; --tr-hour: {{ $hourPx }}px;">
                            <div class="tr-week-head">
                                <div class="tr-corner"></div>
                                @foreach($dayNames as $d => $name)
                                    @php $empty = $tt->days[$d]->isEmpty(); @endphp
                                    <div class="tr-dayhead {{ $empty ? 'is-empty' : '' }} {{ $d == $today ? 'is-today' : '' }}" title="{{ $empty ? $name.' — '.__('No classes') : $name }}">
                                        <span class="tr-dayname-full">{{ $name }}</span>
                                        <span class="tr-dayname-short">{{ mb_substr($name, 0, 3) }}</span>
                                        @if(!$empty)<span class="tr-daycount">{{ $tt->days[$d]->count() }}</span>@endif
                                    </div>
                                @endforeach
                            </div>
                            <div class="tr-week-body" style="height: {{ $hours * $hourPx }}px;">
                                <div class="tr-times">
                                    @for($h = 0; $h <= $hours; $h++)
                                        <span style="top: {{ $h * $hourPx }}px;">{{ $fmtHour($tt->gridStart + $h * 60) }}</span>
                                    @endfor
                                </div>
                                @foreach($dayNames as $d => $name)
                                <div class="tr-day {{ $tt->days[$d]->isEmpty() ? 'is-empty' : '' }} {{ $d == $today ? 'is-today' : '' }}">
                                    @foreach($tt->days[$d] as $slot)
                                        @php
                                            [$tint, $accent] = \App\Support\TeacherTimetable::PALETTE[$slot->colour];
                                            $top = ($slot->start - $tt->gridStart) / 60 * $hourPx;
                                            $height = max($slot->duration / 60 * $hourPx, 24);
                                            // Periods in a run touch; everything else keeps a small gap.
                                            $runClass = '';
                                            if ($slot->run_index) {
                                                $runClass = $slot->run_index == 1 ? 'run-first' : ($slot->run_index == $slot->run_length ? 'run-last' : 'run-mid');
                                            }
                                            $gapTop = in_array($runClass, ['run-mid', 'run-last']) ? 0 : 2;
                                            $gapBottom = in_array($runClass, ['run-first', 'run-mid']) ? 0 : 2;
                                            $size = $slot->duration < 75 ? 'is-short' : '';
                                            $left = $slot->lane / $slot->lanes * 100;
                                            $width = 100 / $slot->lanes;
                                        @endphp
                                        <button type="button"
                                            class="tr-slot {{ $runClass }} {{ $size }} {{ $slot->clash ? 'is-clash' : '' }} {{ $slot->long ? 'is-long' : '' }}"
                                            data-code="{{ $slot->code }}"
                                            data-slot="{{ json_encode($slotData($slot)) }}"
                                            style="--tint: {{ $tint }}; --accent: {{ $accent }}; top: {{ $top + $gapTop }}px; height: {{ $height - $gapTop - $gapBottom }}px; left: calc({{ $left }}% + 4px); width: calc({{ $width }}% - 8px);">
                                            <span class="tr-slot-top">
                                                <strong>{{ $slot->code }}</strong>
                                                @if($slot->run_index)<span class="tr-run" title="{{ __('Period') }} {{ $slot->run_index }} {{ __('of') }} {{ $slot->run_length }}">{{ $slot->run_index }}/{{ $slot->run_length }}</span>@endif
                                                @if($slot->clash)<i class="fas fa-exclamation-circle tr-flag-clash" title="{{ __('Overlaps another period') }}"></i>@endif
                                                @if($slot->long)<i class="fas fa-hourglass-half tr-flag-long" title="{{ __('Longer than 6 hours — check the end time') }}"></i>@endif
                                            </span>
                                            <span class="tr-slot-title">{{ $slot->title }}</span>
                                            <span class="tr-slot-meta">{{ $fmtTime($slot->start) }} – {{ $fmtTime($slot->end) }}@if($slot->room) · {{ $slot->room }}@endif</span>
                                            <span class="tr-slot-tag">{{ $groupsLabel($slot) }}</span>
                                        </button>
                                    @endforeach
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- List view --}}
                    <div class="tr-list" data-panel="list" hidden>
                        @foreach($dayNames as $d => $name)
                            @continue($tt->days[$d]->isEmpty())
                            <div class="tr-list-day {{ $d == $today ? 'is-today' : '' }}">
                                <div class="tr-list-dayname">
                                    {{ $name }}
                                    @if($d == $today)<span class="tr-today-pill">{{ __('Today') }}</span>@endif
                                    <span class="tr-list-count">{{ $tt->days[$d]->count() }} {{ $tt->days[$d]->count() == 1 ? __('period') : __('periods') }}</span>
                                </div>
                                @php $prevRow = null; @endphp
                                @foreach($tt->days[$d] as $slot)
                                    @php [$tint, $accent] = \App\Support\TeacherTimetable::PALETTE[$slot->colour]; @endphp
                                    <button type="button"
                                        class="tr-list-row {{ $prevRow && $slot->run_index > 1 && $prevRow->subject_id === $slot->subject_id && $prevRow->end === $slot->start ? 'is-continued' : '' }} {{ $slot->clash ? 'is-clash' : '' }} {{ $slot->long ? 'is-long' : '' }}"
                                        data-code="{{ $slot->code }}"
                                        data-slot="{{ json_encode($slotData($slot)) }}"
                                        style="--tint: {{ $tint }}; --accent: {{ $accent }};">
                                        <span class="tr-list-time">{{ $fmtTime($slot->start) }} – {{ $fmtTime($slot->end) }}</span>
                                        <span class="tr-list-course">
                                            <strong>{{ $slot->code }}</strong> {{ $slot->title }}
                                        </span>
                                        <span class="tr-list-room">{{ $slot->room ?? '—' }}</span>
                                        <span class="tr-list-groups">{{ $groupsLabel($slot) }}</span>
                                        <span class="tr-list-flags">
                                            @if($slot->run_index)<span class="tr-run">{{ __('Period') }} {{ $slot->run_index }}/{{ $slot->run_length }}</span>@endif
                                            @if($slot->clash)<i class="fas fa-exclamation-circle tr-flag-clash" title="{{ __('Overlaps another period') }}"></i>@endif
                                            @if($slot->long)<i class="fas fa-hourglass-half tr-flag-long" title="{{ __('Longer than 6 hours — check the end time') }}"></i>@endif
                                        </span>
                                    </button>
                                    @php $prevRow = $slot; @endphp
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="tr-hint tr-no-print">
                        <i class="fas fa-info-circle"></i>
                        {{ __('Click a period for its details. Click a course above to highlight it. Periods of the same course that follow each other are joined and numbered (1/2, 2/2).') }}
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

{{-- Period details --}}
<div class="modal fade" id="tr-slot-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span data-f="code"></span> <span class="text-muted fw-normal" data-f="title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('btn_close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="tr-modal-flags"></div>
                <dl class="tr-modal-fields">
                    <div><dt>{{ __('Day') }}</dt><dd data-f="day"></dd></div>
                    <div><dt>{{ __('Time') }}</dt><dd data-f="time"></dd></div>
                    <div><dt>{{ __('Duration') }}</dt><dd data-f="duration"></dd></div>
                    <div><dt>{{ __('Room') }}</dt><dd data-f="room"></dd></div>
                    <div data-row="run"><dt>{{ __('Period') }}</dt><dd data-f="run"></dd></div>
                </dl>
                <span class="tr-modal-label">{{ __('Taught to') }}</span>
                <table class="table table-sm mb-0 tr-modal-groups">
                    <thead><tr><th>{{ __('field_program') }}</th><th>{{ __('field_semester') }}</th><th>{{ __('field_section') }}</th><th>{{ __('Room') }}</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script>
(function () {
    'use strict';

    var board = document.getElementById('tr-board');
    if (!board) { return; }

    // ---- Week / List toggle (remembered per browser) ----
    var KEY = 'teacherRoutine.view';
    function show(view) {
        board.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== view; });
        board.querySelectorAll('[data-view]').forEach(function (b) {
            var on = b.dataset.view === view;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    show(saved === 'week' || saved === 'list' ? saved : (window.innerWidth < 992 ? 'list' : 'week'));
    board.querySelectorAll('[data-view]').forEach(function (b) {
        b.addEventListener('click', function () {
            show(b.dataset.view);
            try { localStorage.setItem(KEY, b.dataset.view); } catch (e) {}
        });
    });

    // ---- Highlight a course from the legend ----
    var chips = document.querySelectorAll('.tr-chip');
    var focused = null;
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            focused = focused === chip.dataset.code ? null : chip.dataset.code;
            chips.forEach(function (c) { c.classList.toggle('is-active', c.dataset.code === focused); });
            board.classList.toggle('has-focus', !!focused);
            board.querySelectorAll('[data-code]').forEach(function (el) {
                el.classList.toggle('is-dim', !!focused && el.dataset.code !== focused);
            });
        });
    });

    // ---- Period details ----
    var modal = document.getElementById('tr-slot-modal');
    function openSlot(data) {
        modal.querySelectorAll('[data-f]').forEach(function (el) { el.textContent = data[el.dataset.f] || ''; });
        modal.querySelector('[data-row="run"]').hidden = !data.run;

        var flags = modal.querySelector('.tr-modal-flags');
        flags.textContent = '';
        function flag(cls, text) {
            var d = document.createElement('div');
            d.className = 'tr-notice ' + cls;
            d.textContent = text;
            flags.appendChild(d);
        }
        if (data.clash) { flag('is-clash', @json(__('This period overlaps another one the same teacher has.'))); }
        if (data.long) { flag('is-long', @json(__('This period is longer than 6 hours — the end time was probably mistyped.'))); }

        var body = modal.querySelector('.tr-modal-groups tbody');
        body.textContent = '';
        (data.groups || []).forEach(function (g) {
            var tr = document.createElement('tr');
            [g.program, g.semester, g.section, g.room].forEach(function (v) {
                var td = document.createElement('td');
                td.textContent = v || '—';
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        } else if (window.jQuery) {
            jQuery(modal).modal('show');
        }
    }
    board.addEventListener('click', function (e) {
        var el = e.target.closest('[data-slot]');
        if (!el) { return; }
        try { openSlot(JSON.parse(el.dataset.slot)); } catch (err) {}
    });
})();
</script>
@endsection
