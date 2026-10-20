@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<style>
/* ---------------------------------------------------------------------------
   Class timetable — admin
   A time-by-day grid. The old screen put a whole day into one table cell, so
   nothing lined up across the week and "what is happening at ten o'clock" had
   no answer. Time runs down the side, days across the top.
   --------------------------------------------------------------------------- */
.ctt-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

.ctt-ident {
    border-left: 3px solid #2c3e50; background: #f7f9fb;
    padding: 10px 14px; border-radius: 4px; margin-bottom: 14px;
}
.ctt-ident h6 { margin: 0; font-weight: 700; color: #2c3e50; font-size: 15px; }
.ctt-ident .ctt-sub { font-size: 12px; color: #6c7a89; margin-top: 2px; }

.ctt-figures { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.ctt-figure {
    background: #fff; border: 1px solid #e3e8ee; border-radius: 4px;
    padding: 8px 14px; min-width: 104px;
}
.ctt-figure b { display: block; font-size: 18px; color: #2c3e50; line-height: 1.1; }
.ctt-figure span {
    font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #8a97a4;
}

/* The grid ---------------------------------------------------------------- */
.ctt-grid { width: 100%; border-collapse: collapse; font-size: 13px; }
.ctt-grid th, .ctt-grid td { border: 1px solid #dfe5ec; }
.ctt-grid thead th {
    background: #2c3e50; color: #fff; font-weight: 600; font-size: 11px;
    text-transform: uppercase; letter-spacing: .04em; padding: 9px 6px;
    text-align: center; white-space: nowrap;
}
.ctt-grid thead th.is-today { background: #1b5e20; }
.ctt-grid tbody td { vertical-align: top; padding: 4px; width: 13.2%; }
.ctt-time {
    background: #f7f9fb; width: 7.6%; text-align: center; white-space: nowrap;
    font-weight: 600; color: #2c3e50; font-variant-numeric: tabular-nums;
    padding: 8px 4px !important; font-size: 12px;
}
.ctt-time small { display: block; font-weight: 400; color: #8a97a4; }
.ctt-empty { background: #fcfdfe; }

/* A class ----------------------------------------------------------------- */
.ctt-class {
    border: 1px solid #d5dbe1; border-left: 3px solid #2c3e50;
    border-radius: 3px; padding: 6px 8px; background: #fff;
}
.ctt-class + .ctt-class { margin-top: 5px; }
.ctt-class .ctt-code { font-weight: 700; color: #2c3e50; font-size: 13px; }
.ctt-class .ctt-title {
    font-size: 11px; color: #6c7a89; line-height: 1.3;
    display: block; margin-bottom: 3px;
}
.ctt-class .ctt-meta { font-size: 11px; color: #55606b; line-height: 1.45; }
.ctt-class .ctt-meta i { width: 11px; color: #a5b0bb; }
.ctt-class.has-warn { border-left-color: #8d6e00; background: #fffdf5; }
.ctt-class.has-bad  { border-left-color: #9c2b18; background: #fff8f7; }

.ctt-flag {
    display: block; margin-top: 4px; font-size: 10px; font-weight: 600;
    padding: 2px 5px; border-radius: 2px;
}
.ctt-flag.is-warn { background: #fff8e1; color: #8d6e00; }
.ctt-flag.is-bad  { background: #fdecea; color: #9c2b18; }

.ctt-legend { font-size: 11px; color: #8a97a4; margin-top: 10px; }
.ctt-legend span { margin-right: 14px; white-space: nowrap; }
.ctt-swatch {
    display: inline-block; width: 9px; height: 9px; border-radius: 2px;
    margin-right: 3px; vertical-align: baseline;
}

/* Phones get the week as a list: a seven-column grid cannot be read at
   360px, and squeezing it only makes it unreadable in a smaller way. */
.ctt-day-list { display: none; }
@media (max-width: 991px) {
    .ctt-grid-wrap { display: none; }
    .ctt-day-list { display: block; }
    .ctt-day-head {
        background: #2c3e50; color: #fff; padding: 7px 12px; border-radius: 4px 4px 0 0;
        font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em;
    }
    .ctt-day-head.is-today { background: #1b5e20; }
    .ctt-day-body {
        border: 1px solid #dfe5ec; border-top: 0;
        border-radius: 0 0 4px 4px; padding: 8px; margin-bottom: 12px;
    }
    .ctt-day-body .ctt-class { display: flex; gap: 10px; align-items: flex-start; }
    .ctt-when {
        min-width: 74px; font-size: 11px; font-weight: 700; color: #2c3e50;
        font-variant-numeric: tabular-nums; white-space: nowrap;
    }
}
</style>
@endpush

@section('content')
<div class="main-body">
    <div class="page-wrapper">

        {{-- Actions and filter ------------------------------------------- --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>

                    <div class="card-block">
                        <div class="ctt-toolbar">
                            @can($access.'-create')
                            <a href="{{ route($route.'.create') }}" class="btn btn-primary">
                                <i class="far fa-edit"></i> {{ __('modal_add') }} / {{ __('modal_edit') }}
                            </a>
                            @endcan

                            @canany([$access.'-view', $access.'-print'])
                            <a href="{{ route($route.'.overview') }}" class="btn btn-secondary">
                                <i class="fas fa-th-list"></i> {{ __('Timetable Overview') }}
                            </a>
                            @endcanany

                            @can($access.'-print')
                            {{-- Both only make sense once a timetable is on screen. --}}
                            @if(isset($rows) && $rows->isNotEmpty())
                            <button type="button" class="btn btn-dark"
                                    onclick="document.getElementById('print-routine').submit()">
                                <i class="fas fa-print"></i> {{ __('btn_print') }}
                            </button>

                            <form id="print-routine" target="_blank" method="post"
                                  action="{{ route($route.'.print') }}" hidden>
                                @csrf
                                <input type="hidden" name="program" value="{{ $selected_program }}">
                                <input type="hidden" name="session" value="{{ $selected_session }}">
                                <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                <input type="hidden" name="section" value="{{ $selected_section }}">
                            </form>
                            @endif

                            <a class="btn btn-success"
                               href="{{ route($route.'.export', ['session' => $selected_session]) }}">
                                <i class="fas fa-file-excel"></i> {{ __('Export Session to Excel') }}
                            </a>
                            @endcan

                            <a href="{{ route($route.'.index') }}" class="btn btn-info">
                                <i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}
                            </a>
                        </div>
                    </div>

                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route .'.index') }}">
                            <div class="row gx-2">
                                @include('common.inc.common_search_filter')

                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter">
                                        <i class="fas fa-search"></i> {{ __('btn_filter') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- The timetable ------------------------------------------------ --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">

                        @if(!isset($rows))
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-filter fa-2x mb-2 d-block"></i>
                            <div>{{ __('Choose a faculty, programme, year, semester and section to see its timetable.') }}</div>
                            <div class="mt-2" style="font-size:13px">
                                {{ __('To see every timetable in the session at once, open the') }}
                                <a href="{{ route($route.'.overview') }}">{{ __('Timetable Overview') }}</a>.
                            </div>
                        </div>

                        @elseif($rows->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                            <div>{{ __('No classes are scheduled for this selection.') }}</div>
                            @can($access.'-create')
                            <a href="{{ route($route.'.create') }}" class="btn btn-sm btn-primary mt-2">
                                <i class="far fa-edit"></i> {{ __('Schedule a class') }}
                            </a>
                            @endcan
                        </div>

                        @else
                        @php
                            $first = $rows->first();
                            $todayNumber = (int) date('w') === 6 ? 1 : (int) date('w') + 2;
                        @endphp

                        {{-- What this timetable is, said once, because the
                             filter above scrolls out of view. --}}
                        <div class="ctt-ident">
                            <h6>{{ $first->program->title ?? '—' }}</h6>
                            <div class="ctt-sub">
                                {{ $first->session->title ?? '—' }}
                                &nbsp;·&nbsp; {{ $first->semester->title ?? '—' }}
                                &nbsp;·&nbsp; {{ __('field_section') }} {{ $first->section->title ?? '—' }}
                            </div>
                        </div>

                        <div class="ctt-figures">
                            <div class="ctt-figure">
                                <b>{{ $summary['classes'] }}</b><span>{{ __('Classes') }}</span>
                            </div>
                            <div class="ctt-figure">
                                <b>{{ $summary['courses'] }}</b><span>{{ __('Courses') }}</span>
                            </div>
                            <div class="ctt-figure">
                                <b>{{ $summary['lecturers'] }}</b><span>{{ __('Lecturers') }}</span>
                            </div>
                            <div class="ctt-figure">
                                <b>{{ $summary['rooms'] }}</b><span>{{ __('Rooms') }}</span>
                            </div>
                            <div class="ctt-figure">
                                <b>{{ $summary['contact_hours'] }}</b><span>{{ __('Contact hours') }}</span>
                            </div>
                            <div class="ctt-figure">
                                <b>{{ $summary['days'] }}</b><span>{{ __('Days taught') }}</span>
                            </div>
                        </div>

                        {{-- Problems before the grid, because they decide
                             whether the grid can be trusted. --}}
                        @if($summary['flagged'])
                        <div class="alert alert-danger">
                            <strong><i class="fas fa-exclamation-triangle"></i>
                                {{ trans_choice(
                                    '{1}:count class on this timetable cannot be taught as scheduled|[2,*]:count classes on this timetable cannot be taught as scheduled',
                                    $summary['flagged'], ['count' => $summary['flagged']]
                                ) }}</strong>
                            <ul class="mb-0 mt-2" style="font-size:13px">
                                @if($summary['conflicts']['clash'])
                                <li>{{ __('The group is booked into two classes at once. One of them has to move — no room change fixes this.') }}</li>
                                @endif
                                @if($summary['conflicts']['duration'])
                                <li>{{ __('A class has a start or end time that cannot be right.') }}</li>
                                @endif
                                @if($summary['conflicts']['room'])
                                <li>{{ __('A room is taking a different course at the same hour, for another programme.') }}</li>
                                @endif
                                @if($summary['conflicts']['lecturer'])
                                <li>{{ __('A lecturer is scheduled to teach two different courses at the same hour.') }}</li>
                                @endif
                            </ul>
                            <div class="mt-2" style="font-size:12px">
                                {{ __('Each affected class is marked in the timetable below. The Excel export lists every conflict with the class that causes it.') }}
                            </div>
                        </div>
                        @endif

                        {{-- The week, as a grid ------------------------------ --}}
                        <div class="ctt-grid-wrap table-responsive">
                            <table class="ctt-grid">
                                <thead>
                                    <tr>
                                        <th>{{ __('Time') }}</th>
                                        @foreach($grid['days'] as $num => $key)
                                        <th class="{{ $num === $todayNumber ? 'is-today' : '' }}">
                                            {{ __($key) }}
                                        </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($grid['bands'] as $band)
                                    <tr>
                                        <td class="ctt-time">
                                            {{ $timetable->clock($band['start']) }}
                                            <small>{{ $timetable->clock($band['end']) }}</small>
                                        </td>
                                        @foreach(array_keys($grid['days']) as $num)
                                        @php $cell = $band['cells'][$num]; @endphp
                                        <td class="{{ $cell->isEmpty() ? 'ctt-empty' : '' }}">
                                            @foreach($cell as $row)
                                                @include('admin.class-routine.inc.class_card', [
                                                    'row' => $row,
                                                    'flags' => $conflicts[$row->id] ?? [],
                                                    'timetable' => $timetable,
                                                    'showTime' => true,
                                                ])
                                            @endforeach
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- The same week as a list, for phones ------------- --}}
                        <div class="ctt-day-list">
                            @foreach($grid['days'] as $num => $key)
                            @php
                                $ofDay = $rows->where('day', (string) $num)->sortBy('start_time');
                            @endphp
                            @if($ofDay->isNotEmpty())
                            <div class="ctt-day-head {{ $num === $todayNumber ? 'is-today' : '' }}">
                                {{ __($key) }}
                                @if($num === $todayNumber) &nbsp;· {{ __('today') }} @endif
                                <span style="float:right;font-weight:400;text-transform:none">
                                    {{ trans_choice('{1}:count class|[2,*]:count classes', $ofDay->count(), ['count' => $ofDay->count()]) }}
                                </span>
                            </div>
                            <div class="ctt-day-body">
                                @foreach($ofDay as $row)
                                @php
                                    $flags = $conflicts[$row->id] ?? [];
                                    $types = array_column($flags, 'type');
                                    $severity = $flags
                                        ? (array_intersect($types, ['clash', 'duration']) ? 'has-bad' : 'has-warn')
                                        : '';
                                @endphp
                                <div class="ctt-class {{ $severity }}">
                                    <div class="ctt-when">
                                        {{ $timetable->clock($row->start_time) }}<br>
                                        {{ $timetable->clock($row->end_time) }}
                                    </div>
                                    <div style="flex:1">
                                        <div class="ctt-code">{{ $row->subject->code ?? '—' }}</div>
                                        <span class="ctt-title">{{ $row->subject->title ?? '' }}</span>
                                        <div class="ctt-meta">
                                            <i class="fas fa-door-open"></i> {{ $row->room->title ?? '—' }}<br>
                                            <i class="fas fa-user-tie"></i> {{ $timetable->lecturer($row) }}
                                        </div>
                                        @foreach($flags as $flag)
                                        <span class="ctt-flag {{ in_array($flag['type'], ['clash','duration'], true) ? 'is-bad' : 'is-warn' }}">
                                            {{ $flag['note'] }}
                                        </span>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                            @endforeach
                        </div>

                        <div class="ctt-legend">
                            <span><span class="ctt-swatch" style="background:#2c3e50"></span>{{ __('Scheduled') }}</span>
                            <span><span class="ctt-swatch" style="background:#8d6e00"></span>{{ __('Room or lecturer clash') }}</span>
                            <span><span class="ctt-swatch" style="background:#9c2b18"></span>{{ __('Group double-booked, or an impossible time') }}</span>
                            <span><i class="fas fa-info-circle"></i>
                                {{ __('Contact hours count each class at its scheduled length, capped at five hours.') }}</span>
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
