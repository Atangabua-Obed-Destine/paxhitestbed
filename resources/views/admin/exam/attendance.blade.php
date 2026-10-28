@extends('admin.layouts.master')
@section('title', $title)

{{--
  Exam attendance.

  This screen and the marking screen are two halves of one job, done by the
  same person in the same sitting, so they now read as one place: the tokens
  and the components come from partials/theme, which the marking screen also
  includes, and what is specific to attendance is in partials/attendance_*.

  It was 1,816 lines. The parts that are a list, a band, a roll or a set of
  notices are partials now, the same way marking's are.

  Four things here were not design at all but broken wiring, which this
  install's Bootstrap version made silent. They are fixed, and each is noted
  where it sits:

    * Bootstrap here is 5.2.2, and the markup was written for 4.x. The
      cross-programme panel's "View Programmes" collapse used data-toggle /
      data-target, which 5.x does not read, so the button did nothing; the
      confirmation modal's Cancel and × used data-dismiss, so neither closed
      it. Both now use the data-bs-* attributes.

    * .custom-control.custom-switch does not exist in 5.x and is not
      backfilled by this theme, so the All Programmes switch had been
      rendering as a bare checkbox. It is a real switch now, same id, same
      handler.

    * mr-* and ml-* are not in 5.x either (they are me-* and ms-*) and are
      not backfilled, so around eighteen icons were sitting hard against
      their text. Spacing is done with gap now and depends on neither.

    * the course panel's view toggle was bound to .view-toggle-btn, a class
      no element carried. See partials/attendance_courses for what that cost.

  Nothing the form submits or the JavaScript binds was renamed.
--}}

@section('content')

<!-- Start Content-->
<div class="main-body exm">
    @include('admin.exam.partials.theme')
    @include('admin.exam.partials.attendance_theme')

    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">

            @if(isset($myAssignedCourses) && $myAssignedCourses->count() > 0)
                @include('admin.exam.partials.attendance_courses')
            @endif

            {{-- The filter. The markup inside is common/inc/subject_search_filter,
                 shared with six other views and untouched; the rules in theme
                 reach it only because it sits inside .exm here. --}}
            <div class="col-sm-12">
                <div class="exm-panel" id="advancedFilterCard">
                    <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}" id="advancedFilterForm">
                        {{-- Preserve cross-programme toggle state across filter submissions --}}
                        @if($cross_program)
                        <input type="hidden" name="cross_program" id="crossProgramHidden" value="1">
                        @endif

                        {{-- The page names itself. Dropping the old card
                             header took this with it, and before a course is
                             chosen there is no band to say where you are. --}}
                        <div class="exm-actions" style="border-top:0;border-bottom:1px solid var(--rule-soft)">
                            <div>
                                <span class="exm-label">{{ __('Advanced Filter') }}</span>
                                <h4 style="margin:.1rem 0 0;font-size:1rem;font-weight:700;color:var(--ink)">{{ $title }}</h4>
                            </div>
                        </div>

                        <div class="exm-filters">
                            <div class="row gx-2">
                                @include('common.inc.subject_search_filter')

                                <div class="form-group col-md-3">
                                    <label for="type">{{ __('field_type') }} <span>*</span></label>
                                    <select class="form-control" name="type" id="type" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach( $types as $type )
                                        <option value="{{ $type->id }}" @if( $selected_type == $type->id) selected @endif>{{ $type->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_type') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="exm-actions">
                            <button type="submit" class="exm-btn exm-btn-main btn-filter">
                                <i class="fas fa-search"></i> {{ __('btn_search') }}
                            </button>

                            @can($access.'-import')
                            <span class="exm-right">
                                <a href="{{ route($route.'.import') }}" class="exm-btn">
                                    <i class="fas fa-upload"></i> {{ __('btn_import') }}
                                </a>
                            </span>
                            @endcan
                        </div>
                    </form>
                </div>
            </div>

            {{-- Cross-programme mode. Only offered when the course is actually
                 shared, which is the only time it means anything. --}}
            @if(isset($rows) && isset($sharing_programs) && $sharing_programs->count() > 1)
            <div class="col-sm-12">
                <div class="exm-panel exm-cross {{ $cross_program ? 'is-on' : '' }}">
                    <div class="exm-actions" style="border-top:0">
                        <label class="exm-switch" for="crossProgramToggle">
                            <input type="checkbox" id="crossProgramToggle" {{ $cross_program ? 'checked' : '' }}>
                            <span class="track"></span>
                            <span class="t">{{ __('All programmes') }}</span>
                        </label>

                        <span style="font-size:.82rem;color:var(--body)">
                            @if($cross_program)
                                {{ __('Showing every student taking this course, from all :count programmes, grouped by programme.', ['count' => $sharing_programs->count()]) }}
                            @else
                                {{ __(':count programmes share this course. Turn this on to see them all at once.', ['count' => $sharing_programs->count()]) }}
                            @endif
                        </span>

                        <span class="exm-right">
                            {{-- data-bs-*: this install is Bootstrap 5.2, where
                                 data-toggle / data-target are not read. --}}
                            <button class="exm-btn" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#sharingProgramsList"
                                    aria-expanded="{{ $cross_program ? 'true' : 'false' }}"
                                    aria-controls="sharingProgramsList">
                                <i class="fas fa-sitemap"></i> {{ __('Which programmes') }}
                            </button>
                        </span>
                    </div>

                    <div class="collapse {{ $cross_program ? 'show' : '' }}" id="sharingProgramsList">
                        <div style="padding:0 1.1rem 1rem">
                            <div class="exm-pills">
                                @foreach($sharing_programs as $sp)
                                <span class="exm-pill {{ $sp->id == $selected_program ? 'is-here' : '' }}">
                                    {{ $sp->title }}
                                    @if($sp->faculty)<small>· {{ $sp->faculty->title }}</small>@endif
                                </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @elseif(isset($rows) && isset($sharing_programs) && $sharing_programs->count() == 1)
            <div class="col-sm-12">
                <p style="font-size:.78rem;color:var(--muted);margin:0 0 .75rem">
                    <i class="fas fa-lock"></i>
                    {{ __('Only :programme takes this course.', ['programme' => $sharing_programs->first()->title]) }}
                </p>
            </div>
            @endif

            <div class="col-sm-12">
                @php
                    // The caption and the band name what is being taken. Worked
                    // out once here rather than by looping the whole subject and
                    // type lists inside the table body, as this view used to.
                    //
                    // $subjects and $types are only set once a course has been
                    // chosen, so both are read defensively: with no filter there
                    // is nothing to name.
                    $cur_subject = null;
                    $cur_type = null;
                    $bandSubject = null;
                    $bandType = null;
                    $bandSession = null;

                    foreach (($subjects ?? []) as $subject) {
                        if ($subject->id == $selected_subject) {
                            $cur_subject = $subject->code;
                            $bandSubject = trim($subject->code . ' · ' . $subject->title, ' ·');
                        }
                    }

                    foreach (($types ?? []) as $type) {
                        if ($type->id == $selected_type) {
                            $cur_type = $bandType = $type->title;
                        }
                    }

                    if (isset($rows) && count($rows) > 0) {
                        $firstRow = $rows[array_key_first($rows)] ?? null;
                        $bandSession = optional(optional($firstRow)->session)->title;
                    }
                @endphp

                @if(isset($rows) && count($rows) > 0)
                    @include('admin.exam.partials.attendance_band')
                @endif

                <div class="exm-panel">
                    @if(isset($rows))
                        @include('admin.exam.partials.attendance_notes')
                    @endif

                    @if(isset($rows) && isset($can_take_attendance) && $can_take_attendance)

                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf

                    @if(isset($attendances))
                    @foreach($attendances as $attendance)
                        @if($loop->first)
                        @php
                            $check_data = $attendance;
                        @endphp
                        @endif
                    @endforeach
                    @endif

                    <input type="hidden" name="subject" value="{{ $selected_subject }}">
                    <input type="hidden" name="type" value="{{ $selected_type }}">
                    <input type="hidden" name="attendances" class="attendances" value="">
                    <input type="hidden" name="bypasses" class="bypasses" value="">
                    @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                    <input type="hidden" name="signins" class="signins" value="">
                    <input type="hidden" name="signouts" class="signouts" value="">
                    @endif

                    {{-- The date the roll is for, and the two whole-column
                         shortcuts, on one line above the names. --}}
                    <div class="exm-actions">
                        <span>
                            <label for="date" class="exm-label" style="display:block;margin-bottom:.2rem">
                                {{ __('field_date') }} *
                            </label>
                            <input type="date" class="form-control date" name="date" id="date"
                                   value="{{ $check_data->date ?? '' }}" required
                                   style="border:1px solid var(--rule);border-radius:6px;font-size:.86rem;width:auto">
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_date') }}
                            </div>
                        </span>

                        @if(count($rows) > 0)
                        <span style="margin-left:.5rem">
                            <span class="exm-label" style="display:block;margin-bottom:.2rem">{{ __('Mark everyone') }}</span>
                            <span class="exm-pa">
                                <span class="form-group d-inline">
                                    <span class="radio radio-primary d-inline">
                                        <input type="radio" name="all_check" id="attendance-p" class="all_present">
                                        <label for="attendance-p" class="cr">{{ __('attendance_present') }}</label>
                                    </span>
                                </span>
                                <span class="form-group d-inline">
                                    <span class="radio radio-danger d-inline">
                                        <input type="radio" name="all_check" id="attendance-a" class="all_absent">
                                        <label for="attendance-a" class="cr">{{ __('attendance_absent') }}</label>
                                    </span>
                                </span>
                            </span>
                        </span>
                        @endif

                        <span class="exm-right">
                            @can('subject-marking-unlock')
                            <button type="button" class="exm-btn" id="btn-bulk-unlock" style="display: none;">
                                <i class="fas fa-unlock"></i> {{ __('Unlock selected') }}
                            </button>
                            @endcan

                            @if(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1)
                            <a href="#" id="btn-print-sheet" class="exm-btn"
                               data-url="{{ route('admin.exam-attendance.print', ['faculty'=>$selected_faculty,'program'=>$selected_program,'session'=>$selected_session,'semester'=>$selected_semester,'section'=>$selected_section,'subject'=>$selected_subject,'type'=>$selected_type]) }}">
                                <i class="fas fa-file-signature"></i> {{ __('Blank sign-in sheet') }}
                            </a>
                            @endif

                            <button type="button" class="exm-btn btn-print">
                                <i class="fas fa-print"></i> {{ __('btn_print') }}
                            </button>

                            <a href="{{ route($route.'.index') }}" class="exm-btn">
                                <i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}
                            </a>
                        </span>
                    </div>

                    @if(count($rows) > 0)
                    <div style="padding:0 1.1rem">
                        @include('admin.exam.partials.attendance_table')
                    </div>

                    <div class="exm-actions">
                        <button type="button" class="exm-btn exm-btn-main" id="btnShowConfirmModal">
                            <i class="fas fa-check"></i> {{ __('btn_update') }}
                        </button>
                        <span style="font-size:.78rem;color:var(--muted)">
                            {{ __('You will be shown a summary before anything is saved.') }}
                        </span>
                    </div>
                    @else
                    <div class="exm-empty">
                        <i class="fas fa-user-check"></i>
                        <p>{{ __('no_result_found') }}</p>
                        <small>{{ __('No student is enrolled on this course for the filter above.') }}</small>
                    </div>
                    @endif
                    </form>

                    @else

                    {{-- Final exam not scheduled: the roll is readable, but there
                         is nothing to tick, so no form is rendered. --}}
                    @if(isset($rows) && count($rows) > 0)
                    <div style="padding:.9rem 1.1rem 1.1rem">
                        <div class="exm-scroll">
                            <table class="display table nowrap exm-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ trans_choice('module_program', 1) }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                    <tr>
                                        <td class="exm-who">{{ $row->matricule }}</td>
                                        <td class="exm-who">{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td class="exm-meta">{{ $row->program->title ?? '—' }}</td>
                                        <td class="exm-meta">{{ $row->semester->title ?? '' }}</td>
                                        <td class="exm-meta">{{ $row->section->title ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="exm-notes" style="padding:.9rem 0 0">
                            <div class="exm-note-row is-warn">
                                <i class="fas fa-info-circle"></i>
                                <span>
                                    {{ __('This list is for reference only.') }}
                                    <a href="{{ route('admin.exam-routine.create') }}">{{ __('Schedule the final exam') }}</a>
                                    {{ __('to start taking attendance.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endif

                    @endif
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

{{-- The summary shown before anything is written. Every id the handler fills
     in is unchanged. --}}
@if(isset($rows) && count($rows) > 0 && isset($can_take_attendance) && $can_take_attendance)
<div class="modal fade exm-modal" id="confirmAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="confirmAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmAttendanceModalLabel">
                    {{ __('Before this is saved') }}
                </h5>
                {{-- data-bs-dismiss: on 5.x, data-dismiss does not close it. --}}
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"
                        style="border:0;background:none;font-size:1.4rem;line-height:1;color:var(--muted)">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <dl class="exm-kv" style="margin-bottom:1rem">
                    <dt>{{ __('field_subject') }}</dt>
                    <dd>
                        @foreach(($subjects ?? []) as $subj)
                            @if($subj->id == $selected_subject){{ $subj->code }} · {{ $subj->title }}@endif
                        @endforeach
                    </dd>

                    <dt>{{ __('field_type') }}</dt>
                    <dd>
                        @foreach(($types ?? []) as $t)
                            @if($t->id == $selected_type){{ $t->title }}@endif
                        @endforeach
                        @if(isset($selected_exam_type) && $selected_exam_type->is_final == 1)
                            <span class="exm-tag exm-tag-bad">{{ __('Final exam') }}</span>
                        @endif
                    </dd>

                    <dt>{{ __('field_session') }}</dt>
                    <dd>{{ $bandSession ?? '—' }}</dd>

                    <dt>{{ __('field_date') }}</dt>
                    <dd><span id="modalExamDate">--</span></dd>
                </dl>

                <span class="exm-label">{{ __('What you are about to record') }}</span>
                <div class="exm-tiles" style="margin-top:.35rem">
                    <div class="exm-tile">
                        <span class="v" id="totalStudents">0</span>
                        <span class="k">{{ __('Students') }}</span>
                    </div>
                    <div class="exm-tile is-good">
                        <span class="v" id="presentCount">0</span>
                        <span class="k">{{ __('attendance_present') }}</span>
                    </div>
                    <div class="exm-tile is-bad">
                        <span class="v" id="absentCount">0</span>
                        <span class="k">{{ __('attendance_absent') }}</span>
                    </div>
                    <div class="exm-tile">
                        <span class="v" id="unmarkedCount">0</span>
                        <span class="k">{{ __('Unmarked') }}</span>
                    </div>
                </div>

                <div style="margin-top:.8rem">
                    <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--muted)">
                        <span class="exm-label">{{ __('Attendance rate') }}</span>
                        <span class="exm-num" id="attendanceRate" style="color:var(--ink);font-weight:700">0%</span>
                    </div>
                    <div class="exm-split" style="margin-top:.3rem">
                        <div class="progress-bar" id="attendanceProgressBar" style="width:0%;background:var(--good)"></div>
                        <div class="progress-bar" id="absentProgressBar" style="width:0%;background:var(--bad)"></div>
                    </div>
                </div>

                @if(isset($selected_exam_type) && $selected_exam_type->is_final == 1)
                <div id="warningsSection" style="margin-top:1rem">
                    <span class="exm-label">{{ __('Eligibility') }}</span>
                    <div class="exm-tiles" style="grid-template-columns:repeat(3,1fr);margin-top:.35rem">
                        <div class="exm-tile is-good">
                            <span class="v" id="eligibleCount">0</span>
                            <span class="k">{{ __('Eligible') }}</span>
                        </div>
                        <div class="exm-tile is-bad">
                            <span class="v" id="ineligibleCount">0</span>
                            <span class="k">{{ __('Not eligible') }}</span>
                        </div>
                        <div class="exm-tile is-warn">
                            <span class="v" id="bypassedCount">0</span>
                            <span class="k">{{ __('Bypassed') }}</span>
                        </div>
                    </div>

                    {{-- The handler swaps alert-info / alert-warning /
                         alert-danger on this element, so those three classes
                         stay the hook; is-warn is what it looks like here. --}}
                    <div class="exm-note-row alert-info" id="eligibilityNote" style="margin-top:.5rem">
                        <i class="fas fa-info-circle"></i>
                        <span id="eligibilityMessage">
                            {{ __('A student needs at least :pct% course attendance to be eligible.', ['pct' => $attendance_setting->minimum_attendance_percentage ?? 70]) }}
                        </span>
                    </div>
                </div>
                @endif

                <div class="exm-note-row is-warn" style="margin-top:1rem">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>
                        <b>{{ __('Read it over first.') }}</b>
                        {{ __('Once submitted, these records are saved and locked.') }}
                    </span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="exm-btn" data-bs-dismiss="modal">{{ __('btn_cancel') }}</button>
                <button type="button" class="exm-btn exm-btn-main" id="btnConfirmSubmit">
                    <i class="fas fa-check"></i> {{ __('Save attendance') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Shown once, after a save, from the session flash. --}}
@if(session('attendance_saved'))
@php $saved = session('attendance_saved'); @endphp
<div class="modal fade exm-modal" id="attendanceSavedModal" tabindex="-1" role="dialog" aria-labelledby="attendanceSavedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendanceSavedModalLabel">
                    <i class="fas fa-check-circle" style="color:var(--good)"></i> {{ __('Attendance saved') }}
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"
                        style="border:0;background:none;font-size:1.4rem;line-height:1;color:var(--muted)">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size:.9rem;color:var(--body);margin-bottom:.9rem">
                    {{ trans_choice(':count record saved and locked.|:count records saved and locked.', $saved['count'], ['count' => $saved['count']]) }}
                </p>

                <div class="exm-tiles" style="grid-template-columns:repeat(2,1fr)">
                    <div class="exm-tile is-good">
                        <span class="v">{{ $saved['present'] }}</span>
                        <span class="k">{{ __('attendance_present') }}</span>
                    </div>
                    <div class="exm-tile is-bad">
                        <span class="v">{{ $saved['absent'] }}</span>
                        <span class="k">{{ __('attendance_absent') }}</span>
                    </div>
                </div>

                <dl class="exm-kv" style="margin-top:1rem">
                    <dt>{{ __('field_subject') }}</dt>
                    <dd>{{ $saved['subject'] }}</dd>
                    <dt>{{ __('field_type') }}</dt>
                    <dd>{{ $saved['type'] }}</dd>
                    <dt>{{ __('field_date') }}</dt>
                    <dd>{{ \Carbon\Carbon::parse($saved['date'])->format('d M Y') }}</dd>
                </dl>

                @if($saved['locked'] > 0)
                <div class="exm-note-row is-warn" style="margin-top:.9rem">
                    <i class="fas fa-lock"></i>
                    <span>{{ trans_choice(':count student was skipped because their attendance was already locked.|:count students were skipped because their attendance was already locked.', $saved['locked'], ['count' => $saved['locked']]) }}</span>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="exm-btn exm-btn-main" data-bs-dismiss="modal">{{ __('OK') }}</button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('page_js')
<script type="text/javascript">
    "use strict";
    $(document).ready(function() {

        // ========== SUCCESS MODAL (auto-show after attendance saved) ==========
        @if(session('attendance_saved'))
        $('#attendanceSavedModal').modal('show');
        @endif

        // ═══════════════════════════════════════
        // CROSS-PROGRAMME TOGGLE
        // ═══════════════════════════════════════
        $('#crossProgramToggle').on('change', function() {
            var url = new URL(window.location.href);
            if ($(this).is(':checked')) {
                url.searchParams.set('cross_program', '1');
            } else {
                url.searchParams.delete('cross_program');
            }
            // Also update the hidden input in filter form (for when form is next submitted)
            $('#crossProgramHidden').val($(this).is(':checked') ? '1' : '0');
            // Reload page with new toggle state
            window.location.href = url.toString();
        });

        // Print blank manual Sign-In/Out sheet (carries the live date + All-Programmes toggle).
        $('#btn-print-sheet').on('click', function(e){
            e.preventDefault();
            var url = new URL($(this).data('url'), window.location.origin);
            var d = $('#date').val();
            if(d){ url.searchParams.set('date', d); }
            if($('#crossProgramToggle').is(':checked')){ url.searchParams.set('cross_program', '1'); }
            window.open(url.toString(), '_blank');
        });

        // Handle bypass checkbox change
        $(".c-bypass").on('change', function(){
            var key = $(this).val();
            var row = $(this).closest('tr');
            var label = $(this).next('label').find('.badge');
            
            if($(this).is(":checked")){
                // Enable attendance radio buttons when bypassed
                $("input[name='attendances-"+key+"']").prop('disabled', false);
                // Uncheck absent if it was auto-selected
                $("input[name='attendances-"+key+"'][value='2']").prop('checked', false);
                // Final exam: attendance is derived from the signs, so the bypass must
                // also enable Sign In (Sign Out follows once Sign In is ticked).
                $('#signin-'+key).prop('disabled', false);
                // Update visual state
                row.css('background-color', '#fff3cd');
                label.text('Active');
                // Update warning badge
                var warning = row.find('small.d-block');
                if(warning.length){
                    warning.css('color', '#856404');
                    if(warning.find('.badge').length == 0){
                        warning.append(' <span class="badge badge-warning ml-1">Bypassed</span>');
                    }
                }
            } else {
                // Remove bypass visual state
                row.css('background-color', '');
                label.text('Bypass');
                // Check if student is eligible
                if(row.hasClass('table-danger')){
                    // Re-disable if not eligible
                    $("input[name='attendances-"+key+"']").prop('disabled', true);
                    $("input[name='attendances-"+key+"'][value='2']").prop('checked', true).prop('disabled', true);
                    // Clear + disable the signs so it reverts to Absent on a final exam.
                    $('#signin-'+key).prop('checked', false).prop('disabled', true);
                    $('#signout-'+key).prop('checked', false).prop('disabled', true);
                }
                // Update warning badge
                var warning = row.find('small.d-block');
                if(warning.length){
                    warning.css('color', '#dc3545');
                    warning.find('.badge').remove();
                }
            }
        });
        
        // Show confirmation modal when update button is clicked
        $("#btnShowConfirmModal").on('click', function(e){
            e.preventDefault();
            
            // Calculate attendance statistics
            var totalStudents = $("input[name^='attendances-']").length / 2; // Divide by 2 because each student has 2 radio buttons
            var presentCount = $(".c-present:checked").length;
            var absentCount = $(".c-absent:checked").length;
            var unmarkedCount = totalStudents - presentCount - absentCount;
            
            // Calculate attendance rate
            var attendanceRate = totalStudents > 0 ? Math.round((presentCount / totalStudents) * 100) : 0;
            var absentRate = totalStudents > 0 ? Math.round((absentCount / totalStudents) * 100) : 0;
            
            // Update modal with statistics
            $("#totalStudents").text(totalStudents);
            $("#presentCount").text(presentCount);
            $("#absentCount").text(absentCount);
            $("#unmarkedCount").text(unmarkedCount);
            $("#attendanceRate").text(attendanceRate + '%');
            
            // Update progress bars
            $("#attendanceProgressBar").css('width', attendanceRate + '%');
            $("#absentProgressBar").css('width', absentRate + '%');
            
            // Update exam date
            var examDate = $("#date").val();
            if(examDate){
                var dateObj = new Date(examDate);
                var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                $("#modalExamDate").text(dateObj.toLocaleDateString('en-US', options));
            } else {
                $("#modalExamDate").text('Not Set').addClass('text-danger');
            }
            
            // Final exam eligibility stats
            @if(isset($selected_exam_type) && $selected_exam_type->is_final == 1)
            var eligibleCount = $("tr:not(.table-danger)").length - 1; // Subtract header row
            var ineligibleCount = $("tr.table-danger").length;
            var bypassedCount = $(".c-bypass:checked").length;
            
            // Recalculate eligible (those who are eligible OR bypassed)
            var totalIneligible = ineligibleCount;
            var effectivelyEligible = eligibleCount + bypassedCount;
            
            // tbody tr also matches the programme heading rows in
            // all-programmes mode, which inflated this figure by one per
            // programme. Those are the rows that carry no student.
            var rollRows = $("tbody tr").not('.cross-program-separator').length;
            $("#eligibleCount").text(rollRows - totalIneligible + bypassedCount);
            $("#ineligibleCount").text(Math.max(0, totalIneligible - bypassedCount));
            $("#bypassedCount").text(bypassedCount);
            
            // Update eligibility message based on situation
            if(bypassedCount > 0){
                $("#eligibilityNote").removeClass('alert-info').addClass('alert-warning');
                $("#eligibilityMessage").html('<strong>' + bypassedCount + '</strong> student(s) have been bypassed and will be allowed to take the exam despite not meeting attendance requirements.');
            } else if(totalIneligible > 0){
                $("#eligibilityNote").removeClass('alert-info').addClass('alert-danger');
                $("#eligibilityMessage").html('<strong>' + totalIneligible + '</strong> student(s) are not eligible due to low course attendance and will be marked absent.');
            } else {
                $("#eligibilityNote").removeClass('alert-warning alert-danger').addClass('alert-info');
                $("#eligibilityMessage").text('All students meet the minimum attendance requirement for this exam.');
            }
            @endif
            
            // Validate before showing modal
            if(unmarkedCount > 0){
                // Show warning about unmarked students
                if(!confirm('Warning: ' + unmarkedCount + ' student(s) have not been marked. Do you want to continue?')){
                    return;
                }
            }
            
            // Check if date is set
            if(!examDate){
                alert('Please set the exam date before submitting.');
                $("#date").focus();
                return;
            }
            
            // Show the modal
            $("#confirmAttendanceModal").modal('show');
        });
        
        // Handle confirm submit
        $("#btnConfirmSubmit").on('click', function(){
            // Prepare form data
            var attendances = [];
            var bypasses = [];
            
            $.each($("input[data_id]:checked"), function(){
                attendances.push($(this).val());
            });
            
            $.each($("input[data_bypass_id]:checked"), function(){
                bypasses.push($(this).attr('data_bypass_id'));
            });

            // Sign In / Sign Out (final exam) — aligned to students[] order.
            var signins = [], signouts = [];
            $("input[name='students[]']").each(function(){
                var sid = $(this).val();
                var $si = $(".c-signin[data_signin_id='" + sid + "']");
                var $so = $(".c-signout[data_signout_id='" + sid + "']");
                signins.push(($si.length && $si.is(':checked')) ? '1' : '0');
                signouts.push(($so.length && $so.is(':checked')) ? '1' : '0');
            });

            $(".attendances").val(attendances.join(','));
            $(".bypasses").val(bypasses.join(','));
            $(".signins").val(signins.join(','));
            $(".signouts").val(signouts.join(','));
            
            // Close modal and submit form
            $("#confirmAttendanceModal").modal('hide');
            
            // Add a small delay to ensure modal closes before form submit
            setTimeout(function(){
                $("form[action*='exam-attendance']").submit();
            }, 300);
        });
    });


    // ── Sign In / Sign Out interlink (final exam) ─────────────────────────────
    // Rule: present = sign_in AND sign_out; sign-out requires sign-in;
    // sign-in only (no sign-out) = absent; absent = neither verified.
    function syncSignRow(idx){
        var $si = $('#signin-'+idx), $so = $('#signout-'+idx);
        var $p = $('#attendance-p-'+idx), $a = $('#attendance-a-'+idx);
        if(!$si.length || !$p.length) return;            // not a final-exam row
        if($p.prop('disabled')) return;                  // locked record
        var si = $si.is(':checked');
        if(!si){ $so.prop('checked', false).prop('disabled', true); }
        else { $so.prop('disabled', false); }
        var present = si && $so.is(':checked');
        $p.prop('checked', present);
        $a.prop('checked', !present);
    }

    $(document).on('change', '.c-signin', function(){
        syncSignRow($(this).attr('id').replace('signin-',''));
    });
    $(document).on('change', '.c-signout', function(){
        syncSignRow($(this).attr('id').replace('signout-',''));
    });
    // Present/Absent radios drive the signs (bidirectional).
    $(document).on('change', '.c-present', function(){
        if(!$(this).is(':checked')) return;
        var idx = $(this).attr('id').replace('attendance-p-','');
        var $si = $('#signin-'+idx), $so = $('#signout-'+idx);
        if($si.length && !$si.prop('disabled')){
            $si.prop('checked', true);
            $so.prop('disabled', false).prop('checked', true);
        }
    });
    $(document).on('change', '.c-absent', function(){
        if(!$(this).is(':checked')) return;
        var idx = $(this).attr('id').replace('attendance-a-','');
        var $si = $('#signin-'+idx), $so = $('#signout-'+idx);
        if($si.length && !$si.prop('disabled')){
            $si.prop('checked', false);
            $so.prop('checked', false).prop('disabled', true);
        }
    });

    // checkbox all-check-button selector
    $(".all_present").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox that are not disabled
            $(".c-present:not(:disabled)").prop('checked', true);
            $(".c-signin:not(:disabled)").prop('checked', true).each(function(){
                var idx = $(this).attr('id').replace('signin-','');
                $('#signout-'+idx).prop('disabled', false).prop('checked', true);
            });
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-present").prop('checked', false);
        }
    });
    $(".all_absent").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox that are not disabled
            $(".c-absent:not(:disabled)").prop('checked', true);
            $(".c-signin:not(:disabled)").prop('checked', false).each(function(){
                var idx = $(this).attr('id').replace('signin-','');
                $('#signout-'+idx).prop('checked', false).prop('disabled', true);
            });
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-absent").prop('checked', false);
        }
    });

    // Unlock attendance
    $(".unlock-attendance").on('click', function(){
        var btn = $(this);
        var studentId = btn.data('student');
        var subjectId = btn.data('subject');
        var typeId = btn.data('type');
        
        if(confirm('Are you sure you want to unlock this attendance record?')){
            $.ajax({
                url: '{{ route("admin.exam-attendance.unlock") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    student_enroll_id: studentId,
                    subject_id: subjectId,
                    exam_type_id: typeId
                },
                success: function(response){
                    if(response.status == 'success'){
                        // alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function(xhr){
                    alert('Error: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error'));
                }
            });
        }
    });
    
    // Bulk Unlock Logic
    $('.check-all-unlock').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.bulk-unlock-checkbox').prop('checked', isChecked);
        toggleBulkUnlockBtn();
    });

    $('.bulk-unlock-checkbox').on('change', function() {
        toggleBulkUnlockBtn();
        var allChecked = $('.bulk-unlock-checkbox:not(:checked)').length === 0 && $('.bulk-unlock-checkbox').length > 0;
        $('.check-all-unlock').prop('checked', allChecked);
    });

    function toggleBulkUnlockBtn() {
        if ($('.bulk-unlock-checkbox:checked').length > 0) {
            $('#btn-bulk-unlock').show();
        } else {
            $('#btn-bulk-unlock').hide();
        }
    }

    $('#btn-bulk-unlock').on('click', function() {
        var selectedIds = [];
        $('.bulk-unlock-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) return;
        
        var subjectId = '{{ $selected_subject ?? '' }}';
        var typeId = '{{ $selected_type ?? '' }}';

        if(confirm('Are you sure you want to unlock attendance for ' + selectedIds.length + ' student(s)?')){
            $.ajax({
                url: '{{ route("admin.exam-attendance.bulk-unlock") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    student_enroll_ids: selectedIds,
                    subject_id: subjectId,
                    exam_type_id: typeId
                },
                success: function(response){
                    if(response.status == 'success'){
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function(xhr){
                    alert('Error: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error'));
                }
            });
        }
    });
    
    // ========== MY ASSIGNED COURSES SECTION ==========
    
    // Program filter for My Assigned Courses
    $('#quickProgramSelector').on('change', function() {
        var selectedProgram = $(this).val();
        
        if (selectedProgram === 'all') {
            // Show all program sections
            $('.program-section').show();
            // ... except the ones the search box has emptied.
            $('#courseSearch').trigger('input');
        } else {
            // Show only selected program section (by index)
            $('.program-section').each(function() {
                if ($(this).data('program-index') == selectedProgram) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });
    
    // View toggle for My Assigned Courses.
    //
    // This was bound to .view-toggle-btn, which no element on the page
    // carried, and it showed and hid #card-view and #table-view, which did
    // not exist either - the buttons were #viewCards and #viewTable and had
    // no handler at all. So the toggle had never worked, and the table half
    // of the panel (a second full copy of every course) could not be
    // reached. There is one copy now and the class says how to lay it out.
    $('.view-toggle-btn').on('click', function() {
        $('.view-toggle-btn').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');

        var cards = $(this).data('view') === 'card';
        $('#coursesContainer .exm-courses').toggleClass('is-cards', cards).toggleClass('is-rows', !cards);
    });

    // Find a course by code or title. At 283 courses this is the difference
    // between the panel being usable and being scrolled past.
    $('#courseSearch').on('input', function() {
        var q = $(this).val().toLowerCase().trim();
        var shown = 0;

        $('#coursesContainer .exm-course').each(function() {
            var hit = q === '' || String($(this).data('search') || '').indexOf(q) !== -1;
            $(this).toggle(hit);
            if (hit) { shown++; }
        });

        // A programme heading with nothing left under it is noise.
        var selected = $('#quickProgramSelector').val();
        $('.program-section').each(function() {
            var any = $(this).find('.exm-course:visible').length > 0;
            var inScope = selected === 'all' || $(this).data('program-index') == selected;
            $(this).toggle(any && inScope);
        });

        $('#courseSearchEmpty').toggle(shown === 0);
    });
    
    // Year narrows the semester list on each card.
    //
    // This used to be a local handler bound to a class that existed nowhere in
    // the project, so the Year dropdown on this screen did nothing whatsoever
    // while looking as though it worked. The markup it should have been
    // driving is .quick-year, which the shared module binds.
    SemesterYearSelect.bindCards();
    
    // Take Attendance button click (quick-take-attendance)
    $(document).on('click', '.quick-take-attendance', function() {
        var btn = $(this);
        var uniqueId = btn.data('unique');
        
        // Get data attributes from button
        var facultyId = btn.data('faculty');
        var programId = btn.data('program');
        var sessionId = btn.data('session');
        var subjectId = btn.data('subject');
        
        // Get values from the dropdowns using the unique ID (with underscore format)
        var semesterId = $('#semester_' + uniqueId).val();
        var sectionId = $('#section_' + uniqueId).val();
        var examTypeId = $('#examtype_' + uniqueId).val();
        
        // Validate selections
        if (!semesterId) {
            alert('Please select a semester');
            return;
        }
        if (!sectionId) {
            alert('Please select a section');
            return;
        }
        if (!examTypeId) {
            alert('Please select an exam type');
            return;
        }
        
        // Navigate directly to the attendance page with correct parameter names
        var url = "{{ route('admin.exam-attendance.index') }}?" + 
                  "faculty=" + facultyId + 
                  "&program=" + programId + 
                  "&session=" + sessionId + 
                  "&semester=" + semesterId + 
                  "&section=" + sectionId + 
                  "&subject=" + subjectId + 
                  "&type=" + examTypeId;
        
        window.location.href = url;
    });
    
    // Function to check attendance status
    function checkAttendanceStatus(uniqueId) {
        var subjectId = $('.quick-take-attendance[data-unique="' + uniqueId + '"]').data('subject');
        var sessionId = $('.quick-take-attendance[data-unique="' + uniqueId + '"]').data('session');
        var semesterId = $('#semester_' + uniqueId).val();
        var sectionId = $('#section_' + uniqueId).val();
        var examTypeId = $('#examtype_' + uniqueId).val();
        
        var statusDiv = $('#status_' + uniqueId);
        
        // Only check if exam type is selected
        if (!examTypeId) {
            statusDiv.hide();
            return;
        }
        
        // Show loading state
        statusDiv.show();
        statusDiv.find('.status-content')
            .removeClass('status-not_taken status-partial status-completed status-no_students')
            .html('<div class="status-loading p-2"><i class="fas fa-spinner"></i> Checking status...</div>');
        
        $.ajax({
            url: "{{ route('admin.exam-attendance.check-status') }}",
            type: 'GET',
            data: {
                subject_id: subjectId,
                semester_id: semesterId,
                section_id: sectionId,
                exam_type_id: examTypeId,
                session_id: sessionId
            },
            success: function(response) {
                var statusContent = statusDiv.find('.status-content');
                statusContent.removeClass('status-not_taken status-partial status-completed status-no_students');
                
                if (response.status === 'incomplete') {
                    statusDiv.hide();
                    return;
                }
                
                statusContent.addClass('status-' + response.status);
                
                var html = '<div class="d-flex align-items-center justify-content-between">' +
                    '<div class="status-info d-flex align-items-center">' +
                    '<i class="' + response.icon + ' mr-2 text-' + response.color + '"></i>' +
                    '<span class="status-message text-' + response.color + '">' + response.message + '</span>' +
                    '</div>';
                
                if (response.total > 0 && response.status !== 'no_students') {
                    html += '<div class="status-details">' +
                        '<span class="present-count text-success" title="Present">' + response.present + '</span>' +
                        '<span class="absent-count text-danger ml-2" title="Absent">' + response.absent + '</span>' +
                        '<span class="pending-count text-warning ml-2" title="Pending">' + response.pending + '</span>' +
                        '</div>';
                }
                
                html += '</div>';
                statusContent.html(html);
            },
            error: function(xhr, status, error) {
                console.log('Status check error:', xhr.responseText);
                var statusContent = statusDiv.find('.status-content');
                statusContent.removeClass('status-not_taken status-partial status-completed status-no_students');
                statusContent.addClass('status-no_students');
                statusContent.html('<div class="d-flex align-items-center p-1"><i class="fas fa-exclamation-triangle mr-2 text-warning"></i><span class="small text-muted">Unable to check status</span></div>');
            }
        });
    }
    
    // Check status when exam type changes
    $(document).on('change', '.quick-exam-type', function() {
        var uniqueId = $(this).attr('id').replace('examtype_', '');
        checkAttendanceStatus(uniqueId);
    });
    
    // Also check when semester or section changes (if exam type is already selected)
    $(document).on('change', '.quick-semester, .quick-section', function() {
        var id = $(this).attr('id');
        var uniqueId = id.replace('semester_', '').replace('section_', '');
        var examTypeId = $('#examtype_' + uniqueId).val();
        if (examTypeId) {
            checkAttendanceStatus(uniqueId);
        }
    });
    
    // Collapse state management
    // The element is #myCoursesCollapse. Listening on
    // #myAssignedCoursesCollapse, which does not exist, meant the chevron
    // never turned over when the panel opened.
    var myCoursesCollapse = document.getElementById('myCoursesCollapse');
    if (myCoursesCollapse) {
        // My Assigned Courses is collapsed by default (no sessionStorage needed)
        // Just update icon when shown/hidden
        myCoursesCollapse.addEventListener('shown.bs.collapse', function () {
            $('#collapseIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });
        
        myCoursesCollapse.addEventListener('hidden.bs.collapse', function () {
            $('#collapseIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
    }
</script>
@endsection
