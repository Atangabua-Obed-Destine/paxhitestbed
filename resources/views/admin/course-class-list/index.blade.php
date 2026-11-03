{{-- A lecturer's own course: who is in it and how they have been attending.

     Everything here reads App\Services\Academic\CourseRoster's one array, the
     same array the PDF and the workbook read, so the three cannot disagree.

     The group picker is deliberately optional. Attendance carries no programme
     (student_attendances is keyed student_enroll_id + subject_id + date), so a
     service course taught to twelve programmes is ONE register; forcing a
     programme the way the older screens do splits it into twelve lists. --}}
@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<style>
    /* One cell per session the register was taken, so every strip is the same
       width and can be read down the column as well as across. */
    .att-strip { display: flex; flex-wrap: nowrap; gap: 1px; min-width: 40px; }
    .att-strip i { display: block; width: 5px; height: 14px; border-radius: 1px; flex: 0 0 auto; }
    /* A class per state rather than an inline style: on a 47-session course
       this is several thousand cells, and the repeated style attribute was
       most of the page weight. */
    .m-present  { background: #0f7a3d; }
    .m-leave    { background: #b8860b; }
    .m-absent   { background: #b3261e; }
    .m-holiday  { background: #6b7480; }
    .m-unmarked { background: #d7dbe0; }
    .att-key { display: inline-flex; align-items: center; gap: 4px; margin-right: 12px; }
    .att-key b { display: inline-block; width: 10px; height: 10px; border-radius: 2px; }

    .tile { border: 1px solid #e2e5e9; border-radius: 4px; padding: 10px 12px; height: 100%; }
    .tile-n { font-size: 20px; font-weight: 600; line-height: 1.1; }
    .tile-l { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7480; }
    .tile-c { font-size: 11px; color: #6b7480; }

    .rate-good { color: #0f7a3d; font-weight: 600; }
    .rate-fair { color: #b8860b; font-weight: 600; }
    .rate-poor { color: #b3261e; font-weight: 600; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .zero { color: #c3c8cf; }
    .roster-table td, .roster-table th { vertical-align: middle; }
</style>

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <span>{{ __('Everyone taking one of your courses, with their attendance — whole course, or one group of it.') }}</span>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">

                                <div class="form-group col-md-3">
                                    <label for="session">{{ __('field_session') }} <span>*</span></label>
                                    <select class="form-control select2" name="session" id="session" required>
                                        @foreach($sessions as $row)
                                        <option value="{{ $row->id }}" @selected($selected_session == $row->id)>{{ $row->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="subject">{{ __('field_subject') }} <span>*</span></label>
                                    <select class="form-control select2" name="subject" id="subject" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($subjects as $row)
                                        <option value="{{ $row->id }}" @selected($selected_subject == $row->id)>{{ $row->code }} - {{ $row->title }}</option>
                                        @endforeach
                                    </select>
                                    @if($subjects->isEmpty())
                                    <small class="text-muted">{{ __('You have no courses timetabled for this session.') }}</small>
                                    @endif
                                </div>

                                {{-- One control for the three ids, because programme,
                                     semester and section are not independent here: they
                                     are the identity of a teaching group, and offering
                                     them separately lets someone build a combination
                                     that is not a class at all. --}}
                                <div class="form-group col-md-5">
                                    <label for="group">{{ __('Group') }}</label>
                                    <select class="form-control" name="group" id="group">
                                        <option value="">{{ __('Whole course — every group') }}</option>
                                        @foreach($classes as $row)
                                        @php $value = $row['program_id'].'|'.$row['semester_id'].'|'.$row['section_id']; @endphp
                                        <option value="{{ $value }}" @selected($value === $selected_program.'|'.$selected_semester.'|'.$selected_section)>
                                            {{ $row['program'] }} — {{ $row['semester'] }}@if($row['section']) · {{ $row['section'] }}@endif
                                        </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="program" id="group-program" value="{{ $selected_program !== '0' ? $selected_program : '' }}">
                                    <input type="hidden" name="semester" id="group-semester" value="{{ $selected_semester !== '0' ? $selected_semester : '' }}">
                                    <input type="hidden" name="section" id="group-section" value="{{ $selected_section !== '0' ? $selected_section : '' }}">
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="from">{{ __('From') }}</label>
                                    <input type="date" class="form-control" name="from" id="from" value="{{ $selected_from }}">
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="to">{{ __('Up to') }}</label>
                                    <input type="date" class="form-control" name="to" id="to" value="{{ $selected_to }}">
                                    <small class="text-muted">{{ __('Leave both blank for the whole course to date.') }}</small>
                                </div>

                                <div class="form-group col-md-6 align-self-end">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>

                                    {{-- formaction, not links: a link would carry the last
                                         SEARCHED filters, so a group or date just changed
                                         and not yet searched would silently not be in the
                                         file. These submit the form as it stands. --}}
                                    <button type="submit" class="btn btn-primary" formaction="{{ route($route.'.pdf') }}">
                                        <i class="fas fa-file-pdf"></i> {{ __('PDF') }}
                                    </button>
                                    <button type="submit" class="btn btn-success" formaction="{{ route($route.'.excel') }}">
                                        <i class="fas fa-file-excel"></i> {{ __('Excel') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @isset($report)
            @php
                $t = $report['totals'];
                // The hues themselves live in CSS classes above, keyed by the
                // same names CourseRoster::COLOURS uses, so the legend and the
                // strip cannot end up a different colour from each other.
                $markLabels = [
                    'present'  => __('Present'),
                    'absent'   => __('Absent'),
                    'leave'    => __('Leave'),
                    'holiday'  => __('Holiday'),
                    'unmarked' => __('Not marked'),
                ];
                $rateClass = function ($rate) {
                    if ($rate === null) return 'zero';
                    return $rate >= 75 ? 'rate-good' : ($rate >= 50 ? 'rate-fair' : 'rate-poor');
                };
            @endphp

            @if(!$report['has_data'])
            <div class="col-sm-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    {{ __('Nobody is enrolled on this course under the filters you chose.') }}
                </div>
            </div>
            @else

            {{-- The figures. Five tiles, because a lecturer opening this wants to
                 know the size of the class and whether the register is being kept
                 before they want anything else. --}}
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $report['subject']->code ?? '' }} — {{ $report['subject']->title ?? '' }}</h5>
                        <span>
                            @foreach($report['filters'] as $label => $value)
                                @if($value !== '' && $value !== null)
                                <strong>{{ $label }}:</strong> {{ $value }}@if(!$loop->last) &nbsp;·&nbsp; @endif
                                @endif
                            @endforeach
                        </span>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            <div class="col-6 col-md mb-2"><div class="tile">
                                <div class="tile-n">{{ $t['students'] }}</div>
                                <div class="tile-l">{{ __('Students') }}</div>
                                <div class="tile-c">{{ trans_choice('{1} in one group|[2,*] across :count groups', count($report['by_class']), ['count' => count($report['by_class'])]) }}</div>
                            </div></div>
                            <div class="col-6 col-md mb-2"><div class="tile">
                                <div class="tile-n">{{ $t['sessions_held'] }}</div>
                                <div class="tile-l">{{ __('Sessions held') }}</div>
                                <div class="tile-c">{{ $report['sessions_held'] ? __('register taken') : __('no register yet') }}</div>
                            </div></div>
                            <div class="col-6 col-md mb-2"><div class="tile">
                                <div class="tile-n">{{ number_format($t['present']) }}</div>
                                <div class="tile-l">{{ __('Present') }}</div>
                                <div class="tile-c">{{ number_format($t['absent']) }} {{ __('absent') }}</div>
                            </div></div>
                            <div class="col-6 col-md mb-2"><div class="tile">
                                <div class="tile-n {{ $rateClass($t['rate']) }}">{{ $t['rate'] === null ? '—' : $t['rate'].'%' }}</div>
                                <div class="tile-l">{{ __('Attendance') }}</div>
                                <div class="tile-c">{{ __('holidays not counted') }}</div>
                            </div></div>
                            <div class="col-6 col-md mb-2"><div class="tile">
                                <div class="tile-n {{ $t['unmarked'] ? '' : 'zero' }}">{{ number_format($t['unmarked']) }}</div>
                                <div class="tile-l">{{ __('Not marked') }}</div>
                                <div class="tile-c">{{ __('not the same as absent') }}</div>
                            </div></div>
                        </div>

                        @if($report['outside']['records'] > 0)
                        <div class="alert alert-warning mb-0 mt-2">
                            <i class="fas fa-filter"></i>
                            {{ __('Your group filter leaves out :n attendance records on :s other enrolments of this course. Choose “Whole course” to include them.', [
                                'n' => number_format($report['outside']['records']),
                                's' => number_format($report['outside']['students']),
                            ]) }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Only when there is someone to chase. A panel that is always there
                 is a panel people stop reading. --}}
            @if(count($report['at_risk']) > 0)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle text-warning"></i> {{ __('Needs attention') }}</h5>
                        <span>{{ __('Below 75% once there are four sessions to judge on, three or more absences running, or never marked at all.') }}</span>
                    </div>
                    <div class="card-block table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('field_matricule') }}</th>
                                    <th>{{ __('field_name') }}</th>
                                    <th>{{ __('field_program') }}</th>
                                    <th class="num">{{ __('Present') }}</th>
                                    <th class="num">{{ __('Absent') }}</th>
                                    <th class="num">{{ __('Attendance %') }}</th>
                                    <th>{{ __('Why') }}</th>
                                    <th>{{ __('field_phone') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['at_risk'] as $row)
                                <tr>
                                    <td>{{ $row['matricule'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td><small>{{ $row['program'] }}</small></td>
                                    <td class="num">{{ $row['present'] }}</td>
                                    <td class="num">{{ $row['absent'] }}</td>
                                    <td class="num {{ $rateClass($row['rate']) }}">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td>
                                    <td><small>{{ implode(' · ', $row['reasons']) }}</small></td>
                                    <td><small>{{ $row['phone'] }}</small></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- The class list itself. --}}
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Class list') }}</h5>
                        <span>
                            @foreach($markLabels as $key => $label)
                            <span class="att-key"><b class="m-{{ $key }}"></b> {{ $label }}</span>
                            @endforeach
                        </span>
                    </div>
                    <div class="card-block table-responsive">
                        <table class="table table-sm table-striped table-hover roster-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('field_matricule') }}</th>
                                    <th>{{ __('field_name') }}</th>
                                    @if(count($report['by_class']) > 1)
                                    <th>{{ __('field_program') }}</th>
                                    @endif
                                    @if($report['sessions_held'] > 0)
                                    <th>{{ __('Sessions') }} <small class="text-muted">({{ __('oldest → newest') }})</small></th>
                                    @endif
                                    <th class="num">{{ __('P') }}</th>
                                    <th class="num">{{ __('A') }}</th>
                                    <th class="num">{{ __('Not marked') }}</th>
                                    <th class="num">{{ __('Attendance %') }}</th>
                                    <th>{{ __('Last present') }}</th>
                                    <th>{{ __('field_phone') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['students'] as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <a href="{{ route('admin.student.show', $row['student_id']) }}">{{ $row['matricule'] }}</a>
                                    </td>
                                    <td>{{ $row['name'] }}</td>
                                    @if(count($report['by_class']) > 1)
                                    <td><small>{{ $row['program'] }}</small></td>
                                    @endif
                                    @if($report['sessions_held'] > 0)
                                    <td>
                                        {{-- Redundant encoding: the same record is in the
                                             figures to the right, so the strip never has to
                                             be read by colour alone. It is here because a
                                             percentage hides the thing that matters most —
                                             whether the absences are recent and running. --}}
                                        <div class="att-strip">
                                            @foreach($row['strip'] as $d => $mark)
                                            <i class="m-{{ $mark }}"
                                               title="{{ date('d/m', strtotime($report['dates'][$d])) }} {{ $markLabels[$mark] }}"></i>
                                            @endforeach
                                        </div>
                                    </td>
                                    @endif
                                    <td class="num">{{ $row['present'] }}</td>
                                    <td class="num {{ $row['absent'] ? '' : 'zero' }}">{{ $row['absent'] }}</td>
                                    <td class="num {{ $row['unmarked'] ? '' : 'zero' }}">{{ $row['unmarked'] }}</td>
                                    <td class="num {{ $rateClass($row['rate']) }}">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td>
                                    <td><small>{{ $row['last_seen'] ? date('d/m/Y', strtotime($row['last_seen'])) : '—' }}</small></td>
                                    <td><small>{{ $row['phone'] }}</small></td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="{{ 3 + (count($report['by_class']) > 1 ? 1 : 0) + ($report['sessions_held'] > 0 ? 1 : 0) }}">{{ __('field_total') }}</th>
                                    <th class="num">{{ number_format($t['present']) }}</th>
                                    <th class="num">{{ number_format($t['absent']) }}</th>
                                    <th class="num">{{ number_format($t['unmarked']) }}</th>
                                    <th class="num {{ $rateClass($t['rate']) }}">{{ $t['rate'] === null ? '—' : $t['rate'].'%' }}</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            @if(count($report['by_class']) > 1)
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5>{{ __('By group') }}</h5>
                        <span>{{ __('One programme drifting while the rest hold up is invisible in the overall figure.') }}</span>
                    </div>
                    <div class="card-block table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('field_program') }}</th>
                                    <th>{{ __('field_semester') }}</th>
                                    <th class="num">{{ __('field_students') }}</th>
                                    <th class="num">{{ __('P') }}</th>
                                    <th class="num">{{ __('A') }}</th>
                                    <th class="num">{{ __('Attendance %') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['by_class'] as $row)
                                <tr>
                                    <td><small>{{ $row['program'] }}</small></td>
                                    <td><small>{{ $row['semester'] }}@if($row['section']) · {{ $row['section'] }}@endif</small></td>
                                    <td class="num">{{ $row['students'] }}</td>
                                    <td class="num">{{ $row['present'] }}</td>
                                    <td class="num">{{ $row['absent'] }}</td>
                                    <td class="num {{ $rateClass($row['rate']) }}">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($report['sessions_held'] > 0)
            <div class="{{ count($report['by_class']) > 1 ? 'col-md-6' : 'col-sm-12' }}">
                <div class="card h-100">
                    <div class="card-header">
                        <h5>{{ __('Session by session') }}</h5>
                        <span>{{ __('Turnout for each date the register was taken.') }}</span>
                    </div>
                    <div class="card-block table-responsive" style="max-height: 420px; overflow-y: auto;">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('field_date') }}</th>
                                    <th class="num">{{ __('Marked') }}</th>
                                    <th class="num">{{ __('P') }}</th>
                                    <th class="num">{{ __('A') }}</th>
                                    <th class="num">{{ __('Attendance %') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['by_date'] as $row)
                                <tr>
                                    <td>{{ date('D d/m/Y', strtotime($row['date'])) }}</td>
                                    <td class="num">{{ $row['marked'] }}</td>
                                    <td class="num">{{ $row['present'] }}</td>
                                    <td class="num {{ $row['absent'] ? '' : 'zero' }}">{{ $row['absent'] }}</td>
                                    <td class="num {{ $rateClass($row['rate']) }}">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @endif
            @endisset

        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
    'use strict';
    $(document).ready(function () {

        // The group picker carries three ids in one value, so it is unpacked
        // into the hidden fields the controller actually reads. Done on change
        // AND on submit: a browser restoring a selection on back-navigation
        // fires no change event, and the hidden fields would then still hold
        // the previous group.
        function unpackGroup() {
            var parts = ($('#group').val() || '').split('|');
            $('#group-program').val(parts[0] || '');
            $('#group-semester').val(parts[1] || '');
            $('#group-section').val(parts[2] || '');
        }

        // Scoped to this screen's own form. $('form') would bind to every form
        // on the page -- the sidebar search among them -- and rewrite hidden
        // fields that belong to somebody else.
        $('#group').on('change', unpackGroup);
        $('#group').closest('form').on('submit', unpackGroup);

        // Changing the course changes which groups exist, so a stale group
        // would be carried onto a course it does not belong to.
        $('#subject').on('change', function () {
            $('#group').val('');
            unpackGroup();
        });
    });
</script>
@endsection
