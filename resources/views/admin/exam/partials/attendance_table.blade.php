{{--
  The roll.

  Every control is the one that was here before, with the same name, id, class
  and data attribute, because the page's JavaScript and the store() action
  both address them directly:

    students[]                    the order signins/signouts are aligned to
    attendances-<key>             two radios per student, counted as length/2
    .c-present / .c-absent        data_id
    .c-signin / .c-signout        data_signin_id / data_signout_id,
                                  ids signin-<key> / signout-<key>
    .c-bypass                     data_bypass_id, id AND name bypass-<key>,
                                  immediately followed by its <label>, which
                                  must contain a .badge — the handler reads
                                  $(this).next('label').find('.badge')
    small.exm-why.d-block         the ineligibility note the handler recolours
                                  and appends a badge to
    tr.table-danger               how the modal counts who is not eligible
    .bulk-unlock-checkbox         id unlock-<key>, value is the enrolment id
    .unlock-attendance            data-student / data-subject / data-type

  What changed is presentation: state wears a word as well as a colour, the
  figures are tabular, and the programme separator spans the table properly.

  $colSpan is computed here rather than passed in, for the reason the marking
  table documents: it has to equal the number of <th> this file emits, and a
  copy kept anywhere else goes stale. The copy this replaced was

      8 + ($is_final_exam ? 2 : 0) + bypass + unlock

  which counted neither the continuous-assessment columns nor Sign In and
  Sign Out, so on a final exam the separator stopped short of the right-hand
  edge by two columns plus one per CA type.

  Expects $rows, $attendances, $selected_exam_type, $selected_subject,
  $selected_type, $cross_program, $ca_types, $ca_marks,
  $student_attendance_percentages, $attendance_setting, and $cur_subject /
  $cur_type / $bandSession for the caption.
--}}

@php
    $isFinalTable = isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1;
    $caTypes = $ca_types ?? collect();
    $canUnlock = auth()->user()->can('subject-marking-unlock');
    $canBypass = auth()->user()->can('exam-attendance-bypass');

    /* Keep in step with the thead below: eight unconditional columns (S/N,
       matricule, name, programme, attendance, status, semester, section), the
       unlock checkbox, and on a final exam the attendance mark, eligibility,
       one per CA type, Sign In and Sign Out, plus Bypass where permitted. */
    $colSpan = 8
        + ($canUnlock ? 1 : 0)
        + ($isFinalTable ? 4 + $caTypes->count() : 0)
        + ($isFinalTable && $canBypass ? 1 : 0);
@endphp

<div class="exm-scroll">
    <table class="display table nowrap exm-table printable" style="width:100%">
        <thead>
            <tr>
                @can('subject-marking-unlock')
                <th style="width:40px" class="text-center">
                    <div class="checkbox checkbox-warning d-inline">
                        <input type="checkbox" id="checkAllUnlock" class="check-all-unlock">
                        <label for="checkAllUnlock" class="cr"></label>
                    </div>
                </th>
                @endcan
                <th>S/N</th>
                <th>{{ __('field_matricule') }}</th>
                <th>{{ __('field_name') }}</th>
                <th>{{ trans_choice('module_program', 1) }}</th>
                @if($isFinalTable)
                <th class="n">{{ __('Attendance Mark') }}</th>
                <th>{{ __('Eligibility') }}</th>
                @foreach($caTypes as $ct)
                <th class="n" title="{{ __('CA') }} / {{ rtrim(rtrim(number_format($ct->marks, 2), '0'), '.') }}">{{ $ct->title }}</th>
                @endforeach
                <th>{{ __('Sign In') }}</th>
                <th>{{ __('Sign Out') }}</th>
                @endif
                <th>{{ __('field_attendance') }}</th>
                <th>{{ __('Status') }}</th>
                @if($isFinalTable)
                @can('exam-attendance-bypass')
                <th>{{ __('Bypass') }}</th>
                @endcan
                @endif
                <th>{{ __('field_semester') }}</th>
                <th>{{ __('field_section') }}</th>
            </tr>
        </thead>
        <tbody>
        @php $prevProgramId = null; @endphp
        @foreach( $rows as $key => $row )
            @php
                // Unchanged from the view this came out of.
                $is_final_exam = $isFinalTable;

                $attendance_data = null;
                $attendance_percentage = null;   // null = no class attendance recorded (N/A)
                $attendance_mark = null;
                $attendance_contribution = 0;
                $no_attendance_records = false;

                if($is_final_exam){
                    $attendance_data = $student_attendance_percentages[$row->id] ?? null;
                    $attendance_percentage = $attendance_data['percentage'] ?? null;
                    $attendance_mark = $attendance_data['attendance_mark'] ?? null;
                    $attendance_contribution = $attendance_data['attendance_contribution'] ?? 0;
                    $no_attendance_records = $is_final_exam && is_null($attendance_percentage);
                }

                // No class attendance recorded (null %) ⇒ eligible (do not block).
                $is_eligible = true;
                $minimum_percentage = $attendance_setting->minimum_attendance_percentage ?? 70;

                if($is_final_exam && $attendance_setting && $attendance_setting->is_enabled){
                    $is_eligible = is_null($attendance_percentage) ? true : ($attendance_percentage >= $minimum_percentage);
                }

                $is_bypassed = false;
                if($is_final_exam && isset($attendances)){
                    foreach($attendances as $attendance){
                        if($attendance->student_enroll_id == $row->id && $attendance->bypass_course_attendance){
                            $is_bypassed = true;
                            break;
                        }
                    }
                }

                $can_attend = !$is_final_exam || $is_eligible || $is_bypassed;
                $show_warning = $is_final_exam && !$is_eligible;

                $is_locked = false;
                if(isset($attendances)){
                    foreach($attendances as $attendance){
                        if($attendance->student_enroll_id == $row->id){
                            $is_locked = $attendance->attendance_locked;
                            break;
                        }
                    }
                }

                $currentProgramId = $row->program_id;
                $isNewProgramGroup = isset($cross_program) && $cross_program && ($currentProgramId !== $prevProgramId);
            @endphp

            @if($isNewProgramGroup)
            @php $programGroupCount = collect($rows)->where('program_id', $currentProgramId)->count(); @endphp
            <tr class="cross-program-separator">
                <td colspan="{{ $colSpan }}">
                    <div class="exm-sep">
                        <span class="exm-label">
                            <i class="fas fa-graduation-cap"></i>
                            {{ $row->program->title ?? __('No programme recorded') }}
                            @if($row->program && $row->program->faculty)
                                <span style="font-weight:600">· {{ $row->program->faculty->title }}</span>
                            @endif
                        </span>
                        <span class="exm-tag exm-tag-flat exm-num">
                            {{ trans_choice(':count student|:count students', $programGroupCount, ['count' => $programGroupCount]) }}
                        </span>
                    </div>
                </td>
            </tr>
            @endif
            @php $prevProgramId = $currentProgramId; @endphp

            {{-- table-danger is load-bearing: the confirmation modal counts
                 it. is-bypassed is the server's half of the highlight the
                 bypass handler sets on the row at run time. --}}
            <tr class="{{ $show_warning ? 'table-danger' : '' }} {{ $is_bypassed ? 'is-bypassed' : '' }}">
                @can('subject-marking-unlock')
                <td class="text-center">
                    @if($is_locked)
                    <div class="checkbox checkbox-warning d-inline">
                        <input type="checkbox" class="bulk-unlock-checkbox" id="unlock-{{ $key }}" value="{{ $row->id }}">
                        <label for="unlock-{{ $key }}" class="cr"></label>
                    </div>
                    @endif
                </td>
                @endcan

                <td class="exm-meta">{{ $loop->iteration }}</td>

                <td class="exm-who">
                    @isset($row->matricule)
                    <a href="{{ route('admin.student.show', $row->student->id) }}" style="color:var(--ink)">
                        {{ $row->matricule }}
                    </a>
                    @if($row->program)
                        <span class="exm-sub">
                            {{ $row->program->academic_level == 'A' ? __('Undergraduate') : ($row->program->academic_level == 'M' ? __('Masters') : __('Doctorate')) }}
                        </span>
                    @endif
                    @endisset
                </td>

                <td class="exm-who">{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>

                <td class="exm-meta">
                    @if($row->program)
                        {{ $row->program->title }}
                        @if(isset($cross_program) && $cross_program && $row->program_id == $selected_program)
                            <span class="exm-sub">{{ __('the programme filtered on') }}</span>
                        @endif
                    @else
                        <span class="exm-dash">&mdash;</span>
                    @endif
                </td>

                @if($isFinalTable)
                <td class="n exm-fig">
                    @if($no_attendance_records)
                        <span class="exm-dash">&mdash;</span>
                        <span class="exm-sub">{{ __('No class attendance recorded') }}</span>
                    @elseif($attendance_data)
                        {{ rtrim(rtrim(number_format($attendance_mark, 2), '0'), '.') }}
                        <span style="color:var(--muted);font-weight:400">/ {{ rtrim(rtrim(number_format($attendance_contribution, 2), '0'), '.') }}</span>
                        <span class="exm-sub">
                            {{ $attendance_percentage }}% ·
                            {{ __('attendance_present') }} {{ $attendance_data['present'] }} ·
                            {{ __('attendance_absent') }} {{ $attendance_data['absent'] }} ·
                            {{ __('attendance_leave') }} {{ $attendance_data['leave'] }}
                        </span>
                    @else
                        <span class="exm-dash">&mdash;</span>
                    @endif
                </td>
                <td>
                    @if($is_bypassed)
                        <span class="exm-tag exm-tag-locked">{{ __('Bypassed') }}</span>
                    @elseif($is_eligible)
                        <span class="exm-tag exm-tag-open">{{ __('Eligible') }}</span>
                    @else
                        <span class="exm-tag exm-tag-bad">{{ __('Not eligible') }}</span>
                    @endif
                </td>

                @foreach($caTypes as $ct)
                    @php $cm = $ca_marks[$row->id][$ct->id] ?? null; @endphp
                    <td class="n exm-fig">
                        @if($cm && !is_null($cm['achieve']))
                            {{ rtrim(rtrim(number_format($cm['achieve'], 2), '0'), '.') }}
                            <span style="color:var(--muted);font-weight:400">/ {{ rtrim(rtrim(number_format($cm['marks'], 2), '0'), '.') }}</span>
                        @else
                            <span class="exm-dash">&mdash;</span>
                        @endif
                    </td>
                @endforeach

                @php
                    $rowSignIn = false; $rowSignOut = false;
                    if(isset($attendances)){
                        foreach($attendances as $att){
                            if($att->student_enroll_id == $row->id){
                                $rowSignIn = (bool) $att->sign_in;
                                $rowSignOut = (bool) $att->sign_out;
                            }
                        }
                    }
                @endphp
                <td>
                    <div class="checkbox checkbox-primary d-inline">
                        <input class="c-signin" type="checkbox" data_signin_id="{{ $row->id }}" id="signin-{{ $key }}" value="1"
                            {{ (!$can_attend || $is_locked) ? 'disabled' : '' }} {{ $rowSignIn ? 'checked' : '' }}>
                        <label for="signin-{{ $key }}" class="cr">{{ __('In') }}</label>
                    </div>
                </td>
                <td>
                    <div class="checkbox checkbox-success d-inline">
                        <input class="c-signout" type="checkbox" data_signout_id="{{ $row->id }}" id="signout-{{ $key }}" value="1"
                            {{ (!$can_attend || $is_locked || !$rowSignIn) ? 'disabled' : '' }} {{ $rowSignOut ? 'checked' : '' }}>
                        <label for="signout-{{ $key }}" class="cr">{{ __('Out') }}</label>
                    </div>
                </td>
                @endif

                <td>
                    <input type="text" name="students[]" value="{{ $row->id }}" hidden>

                    <span class="exm-pa">
                        <span class="form-group d-inline">
                            <span class="radio radio-primary d-inline">
                                <input class="c-present" type="radio" data_id="{{ $row->id }}" name="attendances-{{ $key }}" id="attendance-p-{{ $key }}" value="1" required
                                {{ (!$can_attend || $is_locked) ? 'disabled' : '' }}
                                @if(isset($attendances))
                                @foreach($attendances as $attendance)
                                    @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 1)
                                        checked
                                    @endif
                                @endforeach
                                @endif
                                >
                                <label for="attendance-p-{{ $key }}" class="cr">{{ __('attendance_present') }}</label>
                            </span>
                        </span>
                        <span class="form-group d-inline">
                            <span class="radio radio-danger d-inline">
                                <input class="c-absent" type="radio" data_id="{{ $row->id }}" name="attendances-{{ $key }}" id="attendance-a-{{ $key }}" value="2" required
                                {{ (!$can_attend) ? 'checked' : '' }} {{ (!$can_attend || $is_locked) ? 'disabled' : '' }}
                                @if(isset($attendances))
                                @foreach($attendances as $attendance)
                                    @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 2)
                                        checked
                                    @endif
                                @endforeach
                                @endif
                                >
                                <label for="attendance-a-{{ $key }}" class="cr">{{ __('attendance_absent') }}</label>
                            </span>
                        </span>
                    </span>

                    @if($show_warning)
                        {{-- d-block and the <small> are what the bypass handler
                             looks for; it recolours this and appends to it. --}}
                        <small class="d-block exm-why" style="color: {{ $is_bypassed ? '#7a5406' : '#b3261e' }};">
                            {{ __('Below the minimum course attendance') }}
                            @if($is_bypassed)
                                <span class="badge badge-warning">{{ __('Bypassed') }}</span>
                            @endif
                        </small>
                    @endif
                </td>

                <td>
                    @if($is_locked)
                        <span class="exm-tag exm-tag-locked">{{ __('Locked') }}</span>
                        @can('subject-marking-unlock')
                        <button type="button" class="exm-btn unlock-attendance"
                            style="padding:.15rem .4rem;margin-left:.25rem"
                            data-student="{{ $row->id }}"
                            data-subject="{{ $selected_subject }}"
                            data-type="{{ $selected_type }}"
                            title="{{ __('Unlock') }}">
                            <i class="fas fa-unlock"></i>
                        </button>
                        @endcan
                    @else
                        <span class="exm-tag exm-tag-open">{{ __('Open') }}</span>
                    @endif
                </td>

                @if($isFinalTable)
                @can('exam-attendance-bypass')
                <td>
                    {{-- The input must stay immediately before its label, and
                         the badge must stay inside it. --}}
                    <div class="checkbox checkbox-warning d-inline">
                        <input type="checkbox" class="c-bypass" id="bypass-{{ $key }}" name="bypass-{{ $key }}" value="{{ $key }}" data_bypass_id="{{ $row->id }}"
                        {{ $is_bypassed ? 'checked' : '' }}
                        >
                        <label for="bypass-{{ $key }}" class="cr">
                            <span class="badge badge-warning">{{ $is_bypassed ? __('Active') : __('Bypass') }}</span>
                        </label>
                    </div>
                </td>
                @endcan
                @endif

                <td class="exm-meta">{{ $row->semester->title ?? '' }}</td>
                <td class="exm-meta">{{ $row->section->title ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>

        <caption>{{ $cur_subject ?? '' }} · {{ $cur_type ?? '' }}@if(!empty($bandSession)) · {{ $bandSession }}@endif</caption>
    </table>
</div>
