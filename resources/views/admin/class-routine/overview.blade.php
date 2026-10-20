@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<style>
/* ---------------------------------------------------------------------------
   Timetable overview
   One line per class group, so the state of a whole session reads at a glance.
   Deliberately dense: a registrar is comparing thirty-nine rows, not reading
   one, and anything that adds height pushes the comparison off the screen.
   --------------------------------------------------------------------------- */
.tto-stat-row { display: flex; flex-wrap: wrap; gap: 12px; }
.tto-stat {
    flex: 1 1 140px; background: #fff; border: 1px solid #e3e8ee;
    border-left: 3px solid #2c3e50; border-radius: 4px; padding: 10px 14px;
}
.tto-stat .tto-figure { font-size: 20px; font-weight: 700; line-height: 1.1; color: #2c3e50; }
.tto-stat .tto-label {
    font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6c7a89;
}
.tto-stat.is-bad { border-left-color: #9c2b18; }
.tto-stat.is-bad .tto-figure { color: #9c2b18; }
.tto-stat.is-warn { border-left-color: #8d6e00; }
.tto-stat.is-warn .tto-figure { color: #8d6e00; }
.tto-stat.is-ok { border-left-color: #1b5e20; }

.tto-table { font-size: 13px; }
.tto-table thead th {
    background: #2c3e50; color: #fff; font-weight: 600; font-size: 11px;
    text-transform: uppercase; letter-spacing: .03em; vertical-align: middle;
    border-color: #2c3e50; white-space: nowrap;
}
.tto-table td { vertical-align: middle; }
.tto-table tbody tr:hover { background: #f7f9fb; }
.tto-prog { font-weight: 600; color: #2c3e50; }
.tto-faculty { font-size: 11px; color: #8a97a4; text-transform: uppercase; letter-spacing: .03em; }
.tto-num { text-align: center; font-variant-numeric: tabular-nums; }

/* A verdict is a word AND a colour, never a colour on its own. */
.tto-badge {
    display: inline-block; padding: 2px 8px; border-radius: 10px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}
.tto-badge.is-ok   { background: #e8f5e9; color: #1b5e20; }
.tto-badge.is-warn { background: #fff8e1; color: #8d6e00; }
.tto-badge.is-bad  { background: #fdecea; color: #9c2b18; }

/* The week as seven boxes, so a programme teaching only two days shows it. */
.tto-days { display: inline-flex; gap: 2px; }
.tto-days span {
    width: 18px; height: 18px; line-height: 18px; text-align: center;
    font-size: 9px; font-weight: 700; border-radius: 2px;
    background: #eef2f5; color: #b0bac5;
}
.tto-days span.on { background: #2c3e50; color: #fff; }

@media (max-width: 767px) {
    .tto-table { font-size: 12px; }
    .tto-stat { flex: 1 1 calc(50% - 12px); }
}
</style>
@endpush

@section('content')
<div class="main-body">
    <div class="page-wrapper">

        {{-- Why this session, and how to change it ----------------------- --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <span>
                            {{ __('Every class group in one session, and whether its timetable holds together.') }}
                        </span>
                    </div>

                    <div class="card-block">
                        <form method="get" action="{{ route($route.'.overview') }}">
                            <div class="row gx-2 align-items-end">
                                <div class="form-group col-md-4">
                                    <label for="ov-session">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session" id="ov-session">
                                        @foreach($sessions as $session)
                                        <option value="{{ $session->id }}"
                                            @if((string) $selected_session === (string) $session->id) selected @endif>
                                            {{ $session->title }}@if($session->current == '1') &nbsp;— {{ __('current') }}@endif
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="ov-faculty">{{ __('field_faculty') }}</label>
                                    <select class="form-control" name="faculty" id="ov-faculty">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}"
                                            @if((string) $selected_faculty === (string) $faculty->id) selected @endif>
                                            {{ $faculty->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-search"></i> {{ __('btn_filter') }}
                                    </button>

                                    @can($access.'-print')
                                    <a class="btn btn-success"
                                       href="{{ route($route.'.export', ['session' => $selected_session, 'faculty' => $selected_faculty]) }}">
                                        <i class="fas fa-file-excel"></i> {{ __('Export to Excel') }}
                                    </a>
                                    @endcan
                                </div>
                            </div>
                        </form>

                        @if($current_session && (string) $selected_session !== (string) $current_session->id)
                        <div class="alert alert-info mb-0 py-2">
                            <i class="fas fa-info-circle"></i>
                            {{ __('You are looking at :viewing. The session marked current is :current.', [
                                'viewing' => $sessions->firstWhere('id', (int) $selected_session)->title ?? '—',
                                'current' => $current_session->title,
                            ]) }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- The session in figures --------------------------------------- --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <div class="tto-stat-row">
                            <div class="tto-stat">
                                <div class="tto-figure">{{ $timetables->count() }}</div>
                                <div class="tto-label">{{ __('Timetables') }}</div>
                            </div>
                            <div class="tto-stat">
                                <div class="tto-figure">{{ $totals['classes'] }}</div>
                                <div class="tto-label">{{ __('Classes') }}</div>
                            </div>
                            <div class="tto-stat">
                                <div class="tto-figure">{{ $totals['courses'] }}</div>
                                <div class="tto-label">{{ __('Courses') }}</div>
                            </div>
                            <div class="tto-stat">
                                <div class="tto-figure">{{ $totals['lecturers'] }}</div>
                                <div class="tto-label">{{ __('Lecturers') }}</div>
                            </div>
                            <div class="tto-stat">
                                <div class="tto-figure">{{ $totals['contact_hours'] }}</div>
                                <div class="tto-label">{{ __('Contact hours') }}</div>
                            </div>
                            <div class="tto-stat {{ count($unscheduled) ? 'is-warn' : 'is-ok' }}">
                                <div class="tto-figure">{{ count($unscheduled) }}</div>
                                <div class="tto-label">{{ __('Not scheduled') }}</div>
                            </div>
                            <div class="tto-stat {{ $totals['flagged'] ? 'is-bad' : 'is-ok' }}">
                                <div class="tto-figure">{{ $totals['flagged'] }}</div>
                                <div class="tto-label">{{ __('Need attention') }}</div>
                            </div>
                        </div>

                        @if($totals['flagged'])
                        <div class="alert alert-danger mt-3 mb-0">
                            <strong><i class="fas fa-exclamation-triangle"></i>
                                {{ __(':count classes cannot be taught as scheduled.', ['count' => $totals['flagged']]) }}</strong>
                            <div class="mt-1" style="font-size:13px">
                                @php
                                    $pieces = [];
                                    if($totals['conflicts']['clash'])    $pieces[] = __(':n where the group itself is double-booked', ['n' => $totals['conflicts']['clash']]);
                                    if($totals['conflicts']['room'])     $pieces[] = __(':n where a room takes two different courses', ['n' => $totals['conflicts']['room']]);
                                    if($totals['conflicts']['lecturer']) $pieces[] = __(':n where a lecturer is in two places', ['n' => $totals['conflicts']['lecturer']]);
                                    if($totals['conflicts']['duration']) $pieces[] = __(':n with an impossible start or end time', ['n' => $totals['conflicts']['duration']]);
                                @endphp
                                {{ implode('; ', $pieces) }}.
                                {{ __('The Excel export lists every one with the two classes that cause it.') }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- One line per class group ------------------------------------- --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Timetables') }}</h5>
                    </div>
                    <div class="card-block">
                        @if($timetables->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                            {{ __('No classes are scheduled in this session yet.') }}
                        </div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-sm tto-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                        <th class="tto-num">{{ __('Classes') }}</th>
                                        <th class="tto-num">{{ __('Courses') }}</th>
                                        <th class="tto-num">{{ __('Lecturers') }}</th>
                                        <th class="tto-num">{{ __('Rooms') }}</th>
                                        <th>{{ __('Days taught') }}</th>
                                        <th class="tto-num">{{ __('Hours') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($timetables as $entry)
                                    @php
                                        $s = $entry['summary'];
                                        $severity = $s['total_conflicts'] === 0 ? 'ok'
                                            : (($s['conflicts']['clash'] || $s['conflicts']['duration']) ? 'bad' : 'warn');
                                        $verdict = $s['conflicts']['clash'] ? __('Group double-booked')
                                            : ($s['conflicts']['duration'] ? __('Check times')
                                            : ($s['total_conflicts'] ? __('Room or lecturer clash') : __('Clear')));
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="tto-prog">{{ $entry['program']->title ?? '—' }}</div>
                                            <div class="tto-faculty">{{ $entry['faculty'] }}</div>
                                        </td>
                                        <td>{{ $entry['semester']->title ?? '—' }}</td>
                                        <td>{{ $entry['section']->title ?? '—' }}</td>
                                        <td class="tto-num">{{ $s['classes'] }}</td>
                                        <td class="tto-num">{{ $s['courses'] }}</td>
                                        <td class="tto-num">{{ $s['lecturers'] }}</td>
                                        <td class="tto-num">{{ $s['rooms'] }}</td>
                                        <td>
                                            <span class="tto-days">
                                                @foreach($timetable::DAYS as $num => $key)
                                                <span class="{{ in_array($num, $entry['days'], true) ? 'on' : '' }}"
                                                      title="{{ __($key) }}">{{ mb_substr(__($key), 0, 1) }}</span>
                                                @endforeach
                                            </span>
                                        </td>
                                        <td class="tto-num">{{ $s['contact_hours'] }}</td>
                                        <td>
                                            <span class="tto-badge is-{{ $severity }}">{{ $verdict }}</span>
                                            @if($s['total_conflicts'])
                                            <div style="font-size:11px;color:#8a97a4">
                                                {{ trans_choice('{1}:count problem|[2,*]:count problems', $s['total_conflicts'], ['count' => $s['total_conflicts']]) }}
                                            </div>
                                            @endif
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="{{ route($route.'.index', [
                                                   'faculty'  => $entry['program']->faculty_id ?? '',
                                                   'program'  => $entry['program']->id ?? '',
                                                   'session'  => $selected_session,
                                                   'semester' => $entry['semester']->id ?? '',
                                                   'semester_year' => $entry['semester']->year ?? '',
                                                   'section'  => $entry['section']->id ?? '',
                                               ]) }}">
                                                <i class="fas fa-table"></i> {{ __('Open') }}
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- What is missing entirely ------------------------------------- --}}
        @if(count($unscheduled))
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('No timetable in this session') }}</h5>
                        <span>
                            {{ __(':count of :total active programmes have no class scheduled at all. They cannot appear in the table above, because that table is built from the classes that exist.', [
                                'count' => count($unscheduled), 'total' => $programme_count,
                            ]) }}
                        </span>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            @foreach($unscheduled as $programme)
                            <div class="col-md-4 mb-2">
                                <div style="border-left:3px solid #8d6e00;padding:4px 10px;background:#fffdf5">
                                    <div style="font-weight:600;font-size:13px">{{ $programme->title }}</div>
                                    <div class="tto-faculty">{{ $programme->faculty->title ?? '—' }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection
