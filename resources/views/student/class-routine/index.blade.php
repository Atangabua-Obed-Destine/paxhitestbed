@extends('student.layouts.master')
@section('title', $title)

@push('css')
@endpush

@section('content')
{{--
  The timetable, built for the phone first.

  It used to be a seven-column table with every day's classes stacked inside
  one cell, which on a phone meant scrolling sideways through seven columns to
  read five lines of small text per class. A student's real question is almost
  always "what do I have next, and where" - so that is answered at the top,
  before anything else, and the week is a day at a time underneath.

  The week grid is still there on a wide screen, for the student who wants to
  see the shape of the whole week or print it.
--}}
@php
    $timeFormat = $setting->time_format ?? 'h:i A';
    $fmt = fn ($t) => $t ? date($timeFormat, strtotime($t)) : '';

    $dayShort = [
        1 => __('day_saturday'), 2 => __('day_sunday'), 3 => __('day_monday'),
        4 => __('day_tuesday'), 5 => __('day_wednesday'), 6 => __('day_thursday'),
        7 => __('day_friday'),
    ];

    $totalClasses = $rows->count();
    $current = $nowNext['current'] ?? null;
    $next = $nowNext['next'] ?? null;

    // A stable colour per subject, so the same course looks the same all week.
    $palette = ['#4680ff', '#0e9e4a', '#e58a00', '#7759de', '#d6336c', '#0c8599', '#b2610a', '#5c7cfa'];
    $colourOf = function ($row) use ($palette) {
        $key = $row->subject_id ?? ($row->subject->code ?? 'x');

        return $palette[abs(crc32((string) $key)) % count($palette)];
    };

    $minutesOf = function ($row) {
        $start = strtotime($row->start_time);
        $end = strtotime($row->end_time);

        return $start && $end && $end > $start ? (int) round(($end - $start) / 60) : null;
    };
@endphp

<style>
    .tt-wrap { --tt-ink:#1f2733; --tt-muted:#6b7684; --tt-line:#e6e9ef; --tt-accent:#4680ff; }
    .tt-head { background:linear-gradient(135deg,#4680ff,#1b55e2); color:#fff; border-radius:14px; padding:18px; }
    .tt-head h4 { color:#fff; font-weight:700; margin:0 0 2px; font-size:1.15rem; }
    .tt-head .tt-sub { opacity:.9; font-size:.85rem; }

    /* What's happening now - the answer most students open this page for. */
    .tt-now { background:#fff; border:1px solid var(--tt-line); border-radius:14px; padding:16px; }
    .tt-now.is-live { border-color:#0e9e4a; box-shadow:0 0 0 3px rgba(14,158,74,.1); }
    .tt-now .tt-eyebrow { font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; color:var(--tt-muted); font-weight:700; }
    .tt-now .tt-title { font-size:1.05rem; font-weight:700; color:var(--tt-ink); margin:4px 0 2px; line-height:1.3; }
    .tt-live-dot { width:8px; height:8px; border-radius:50%; background:#0e9e4a; display:inline-block; margin-right:6px;
                   animation:tt-pulse 1.6s ease-in-out infinite; }
    @keyframes tt-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

    /* Day picker: one row, swipeable, never wraps. */
    .tt-days { display:flex; gap:8px; overflow-x:auto; padding:4px 2px 10px; -webkit-overflow-scrolling:touch;
               scrollbar-width:none; }
    .tt-days::-webkit-scrollbar { display:none; }
    .tt-day { flex:0 0 auto; min-width:62px; min-height:62px; border:1px solid var(--tt-line); background:#fff;
              border-radius:12px; padding:8px 10px; text-align:center; cursor:pointer; transition:.15s;
              color:var(--tt-ink); }
    .tt-day:hover { border-color:var(--tt-accent); }
    .tt-day .tt-dname { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--tt-muted); }
    .tt-day .tt-dcount { font-size:1.15rem; font-weight:700; line-height:1.2; }
    .tt-day .tt-dunit { font-size:.66rem; color:var(--tt-muted); }
    .tt-day.active { background:var(--tt-accent); border-color:var(--tt-accent); color:#fff; }
    .tt-day.active .tt-dname, .tt-day.active .tt-dunit { color:rgba(255,255,255,.85); }
    .tt-day.is-today { position:relative; }
    .tt-day.is-today::after { content:''; position:absolute; left:50%; bottom:5px; transform:translateX(-50%);
                              width:5px; height:5px; border-radius:50%; background:var(--tt-accent); }
    .tt-day.active.is-today::after { background:#fff; }
    .tt-day.is-empty .tt-dcount { color:var(--tt-muted); opacity:.5; }

    /* The agenda: a time rail, then the class. */
    .tt-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--tt-line); }
    .tt-item:last-child { border-bottom:0; }
    .tt-rail { flex:0 0 76px; text-align:right; }
    .tt-rail .tt-from { font-weight:700; color:var(--tt-ink); font-size:.92rem; white-space:nowrap; }
    .tt-rail .tt-to, .tt-rail .tt-dur { font-size:.74rem; color:var(--tt-muted); white-space:nowrap; }
    .tt-body { flex:1 1 auto; min-width:0; border-left:3px solid var(--tt-accent); padding-left:12px; }
    .tt-subject { font-weight:700; color:var(--tt-ink); line-height:1.3; }
    .tt-code { font-size:.72rem; font-weight:700; color:var(--tt-muted); }
    .tt-meta { font-size:.8rem; color:var(--tt-muted); margin-top:3px; }
    .tt-meta i { width:14px; }

    .tt-empty { text-align:center; padding:34px 16px; color:var(--tt-muted); }
    .tt-empty i { font-size:2rem; opacity:.3; display:block; margin-bottom:10px; }

    .tt-panel { display:none; }
    .tt-panel.active { display:block; }

    /* The whole week, for a wide screen. Hidden on phones, where it is the
       very thing that made this page unusable. */
    .tt-week { display:none; }
    .tt-week table { width:100%; table-layout:fixed; }
    .tt-week th { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--tt-muted);
                  border-bottom:2px solid var(--tt-line); padding:8px 6px; text-align:left; }
    .tt-week th.is-today { color:var(--tt-accent); }
    .tt-week td { vertical-align:top; padding:6px; border-bottom:1px solid var(--tt-line); }
    .tt-chip { border-radius:8px; padding:7px 8px; margin-bottom:6px; background:#f6f8fc;
               border-left:3px solid var(--tt-accent); }
    .tt-chip .c { font-weight:700; font-size:.76rem; color:var(--tt-ink); }
    .tt-chip .t { font-size:.7rem; color:var(--tt-muted); }

    @media (min-width:992px) {
        .tt-week { display:block; }
    }

    @media print {
        .tt-days, .tt-now, .tt-head .btn, .tt-agenda { display:none !important; }
        .tt-week { display:block !important; }
    }
</style>

<div class="main-body">
    <div class="page-wrapper tt-wrap">

        {{-- Context: which timetable this is. A student on two programmes needs
             to know which one they are looking at. --}}
        <div class="tt-head mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:10px">
                <div>
                    <h4><i class="fas fa-calendar-alt mr-1"></i> {{ $title }}</h4>
                    <div class="tt-sub">
                        @if($enroll)
                            {{ optional($enroll->program)->title }}
                            @if(optional($enroll->semester)->title) &bull; {{ $enroll->semester->title }} @endif
                            @if(optional($enroll->section)->title) &bull; {{ __('field_section') }} {{ $enroll->section->title }} @endif
                        @else
                            {{ __('No enrollment selected') }}
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <div style="font-size:1.4rem;font-weight:700;line-height:1">{{ $totalClasses }}</div>
                    <div class="tt-sub">{{ __('classes this week') }}</div>
                </div>
            </div>
        </div>

        @if($totalClasses === 0)
            <div class="card">
                <div class="card-block tt-empty">
                    <i class="fas fa-calendar-times"></i>
                    <h6 class="mb-2">{{ __('No timetable yet') }}</h6>
                    <p class="mb-0">
                        {{ __('Your class timetable has not been published for this semester. It will appear here as soon as the department sets it.') }}
                    </p>
                </div>
            </div>
        @else

        {{-- Now / next. The reason the page gets opened. --}}
        <div class="tt-now mb-3 {{ $current ? 'is-live' : '' }}">
            @if($current)
                <div class="tt-eyebrow"><span class="tt-live-dot"></span>{{ __('In class now') }}</div>
                <div class="tt-title">{{ optional($current->subject)->title ?? optional($current->subject)->code }}</div>
                <div class="tt-meta">
                    <i class="far fa-clock"></i> {{ $fmt($current->start_time) }} &ndash; {{ $fmt($current->end_time) }}
                    @if(optional($current->room)->title)
                        &nbsp;<i class="fas fa-door-open"></i> {{ $current->room->title }}
                    @endif
                </div>
                @if($next)
                    <div class="tt-meta mt-2 pt-2" style="border-top:1px dashed var(--tt-line)">
                        <strong>{{ __('Then') }}:</strong>
                        {{ optional($next->subject)->code }} &bull; {{ $fmt($next->start_time) }}
                    </div>
                @endif
            @elseif($next)
                <div class="tt-eyebrow">{{ __('Up next') }}</div>
                <div class="tt-title">{{ optional($next->subject)->title ?? optional($next->subject)->code }}</div>
                <div class="tt-meta">
                    <i class="far fa-clock"></i> {{ $fmt($next->start_time) }} &ndash; {{ $fmt($next->end_time) }}
                    @if(optional($next->room)->title)
                        &nbsp;<i class="fas fa-door-open"></i> {{ $next->room->title }}
                    @endif
                </div>
                @if(optional($next->teacher)->first_name)
                    <div class="tt-meta">
                        <i class="fas fa-user"></i>
                        {{ trim(($next->teacher->first_name ?? '').' '.($next->teacher->last_name ?? '')) }}
                    </div>
                @endif
            @elseif($nowNext['done'] ?? false)
                <div class="tt-eyebrow">{{ __('Today') }}</div>
                <div class="tt-title">{{ __('That is all for today') }}</div>
                <div class="tt-meta">{{ __('Your remaining classes are on the other days below.') }}</div>
            @else
                <div class="tt-eyebrow">{{ __('Today') }}</div>
                <div class="tt-title">{{ __('No classes today') }}</div>
                <div class="tt-meta">{{ __('Pick another day below to see what is coming.') }}</div>
            @endif
        </div>

        {{-- The week, one tappable day at a time. --}}
        <div class="tt-days" role="tablist" aria-label="{{ __('Days of the week') }}">
            @foreach($days as $number => $label)
                @php $count = count($byDay[$number] ?? []); @endphp
                <button type="button"
                        class="tt-day {{ $number === $today ? 'active is-today' : '' }} {{ $count === 0 ? 'is-empty' : '' }}"
                        data-day="{{ $number }}"
                        role="tab"
                        aria-selected="{{ $number === $today ? 'true' : 'false' }}"
                        aria-controls="tt-panel-{{ $number }}">
                    <div class="tt-dname">{{ Str::limit(__($label), 3, '') }}</div>
                    <div class="tt-dcount">{{ $count }}</div>
                    <div class="tt-dunit">{{ $count === 1 ? __('class') : __('classes') }}</div>
                </button>
            @endforeach
        </div>

        <div class="card tt-agenda">
            <div class="card-block">
                @foreach($days as $number => $label)
                    <div class="tt-panel {{ $number === $today ? 'active' : '' }}"
                         id="tt-panel-{{ $number }}" role="tabpanel">

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0" style="font-weight:700">
                                {{ __($label) }}
                                @if($number === $today)
                                    <span class="badge badge-primary ml-1">{{ __('Today') }}</span>
                                @endif
                            </h6>
                            @if(count($byDay[$number] ?? []) > 0)
                                <small class="text-muted">
                                    {{ $fmt(collect($byDay[$number])->min('start_time')) }}
                                    &ndash;
                                    {{ $fmt(collect($byDay[$number])->max('end_time')) }}
                                </small>
                            @endif
                        </div>

                        @forelse($byDay[$number] ?? [] as $row)
                            @php
                                $minutes = $minutesOf($row);
                                $colour = $colourOf($row);
                                $isNow = $current && $current->id === $row->id;
                            @endphp
                            <div class="tt-item">
                                <div class="tt-rail">
                                    <div class="tt-from">{{ $fmt($row->start_time) }}</div>
                                    <div class="tt-to">{{ $fmt($row->end_time) }}</div>
                                    @if($minutes)
                                        <div class="tt-dur">
                                            {{ $minutes >= 60 ? round($minutes / 60, 1) . 'h' : $minutes . 'm' }}
                                        </div>
                                    @endif
                                </div>
                                <div class="tt-body" style="border-left-color:{{ $colour }}">
                                    <div class="tt-subject">
                                        {{ optional($row->subject)->title ?? optional($row->subject)->code ?? __('Subject') }}
                                        @if($isNow)
                                            <span class="badge badge-success ml-1">{{ __('Now') }}</span>
                                        @endif
                                    </div>
                                    <div class="tt-code">{{ optional($row->subject)->code }}</div>
                                    <div class="tt-meta">
                                        @if(optional($row->room)->title)
                                            <i class="fas fa-door-open"></i> {{ $row->room->title }}
                                        @endif
                                        @if(optional($row->teacher)->first_name)
                                            <br><i class="fas fa-user"></i>
                                            {{ trim(($row->teacher->first_name ?? '').' '.($row->teacher->last_name ?? '')) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="tt-empty">
                                <i class="fas fa-mug-hot"></i>
                                <div>{{ __('No classes on this day') }}</div>
                            </div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>

        {{-- The full week, for a wide screen and for printing. --}}
        <div class="card tt-week mt-3">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Full week') }}</h5>
            </div>
            <div class="card-block">
                <table>
                    <thead>
                        <tr>
                            @foreach($days as $number => $label)
                                <th class="{{ $number === $today ? 'is-today' : '' }}">{{ __($label) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach($days as $number => $label)
                                <td>
                                    @forelse($byDay[$number] ?? [] as $row)
                                        <div class="tt-chip" style="border-left-color:{{ $colourOf($row) }}">
                                            <div class="c">{{ optional($row->subject)->code }}</div>
                                            <div class="t">{{ $fmt($row->start_time) }} &ndash; {{ $fmt($row->end_time) }}</div>
                                            @if(optional($row->room)->title)
                                                <div class="t">{{ $row->room->title }}</div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="t text-muted" style="font-size:.72rem">&mdash;</div>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    "use strict";

    // Switching days is the whole navigation model on a phone, so it is plain
    // DOM work with no dependency on a plugin being present.
    var days = document.querySelectorAll('.tt-day');
    var panels = document.querySelectorAll('.tt-panel');

    function show(dayNumber) {
        days.forEach(function (button) {
            var mine = button.getAttribute('data-day') === dayNumber;
            button.classList.toggle('active', mine);
            button.setAttribute('aria-selected', mine ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('active', panel.id === 'tt-panel-' + dayNumber);
        });
    }

    days.forEach(function (button) {
        button.addEventListener('click', function () {
            show(button.getAttribute('data-day'));
        });
    });

    // Bring today into view on a narrow screen, where it may start off-screen.
    var today = document.querySelector('.tt-day.is-today');

    if (today && today.scrollIntoView) {
        today.scrollIntoView({ block: 'nearest', inline: 'center' });
    }
})();
</script>
@endpush
