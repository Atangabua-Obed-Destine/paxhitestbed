{{--
  The marks table.

  Every column, every condition and every control is the one that was here
  before — the bulk-unlock checkbox, the cross-programme heading rows, the
  anonymous script code for finals, the attendance columns for CA, the
  per-row unlock button, the caption. What changes is that the figures can be
  read and the column can be typed.

  Three things are deliberate:

    * the mark box stays type="text" with inputmode="decimal". A number
      spinner changes the value on a scroll wheel over a field the marker has
      just tabbed into, which would be a new way to mis-record a mark. The
      inputmode gets a tablet a digit keypad without that risk.

    * it reads old('marks.<id>') first, so a refused save hands back what was
      typed rather than 88 empty boxes.

    * every state wears a word as well as a colour.

  Expects what the marking view already computed: $rows, $maxMarks,
  $isFinalExam, $cross_program.

  $colSpan is worked out here rather than passed in, because it has to equal
  the number of <th> this very partial emits. Computed anywhere else it is a
  second copy of the column list that goes stale the moment a column is added
  here -- which is how it came to be missing altogether, leaving the
  cross-programme heading row undefined on exactly the ?cross_program=1 URL.
--}}

@php
    /* Keep in step with the thead below: 7 unconditional columns (S/N,
       exam id or matricule, max marks, note, status, semester, section),
       the unlock checkbox, the four CA-only columns (name, absences,
       attendance score, actual mark), and the programme column. */
    $colSpan = 7
        + (auth()->user()->can('subject-marking-unlock') ? 1 : 0)
        + ($isFinalExam ? 0 : 4)
        + ($cross_program ? 1 : 0);
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
                <th>{{ $isFinalExam ? __('Student Exam ID') : __('field_matricule') }}</th>
                @if(!$isFinalExam)
                <th>{{ __('field_student_name') }}</th>
                @endif
                @if($cross_program)
                <th>{{ trans_choice('module_program', 1) }}</th>
                @endif
                <th class="n">
                    {{ __('field_max_marks') }}
                    @if(!is_null($maxMarks))
                        <span class="exm-num">/ {{ round($maxMarks, 2) + 0 }}</span>
                    @endif
                </th>
                <th>{{ __('field_note') }}</th>
                @if(!$isFinalExam)
                <th class="n">{{ __('Absences') }}</th>
                <th class="n">{{ __('Attendance Score') }}</th>
                <th class="n">{{ __('Actual Mark') }}</th>
                @endif
                <th>{{ __('Status') }}</th>
                <th>{{ __('field_semester') }}</th>
                <th>{{ __('field_section') }}</th>
            </tr>
        </thead>
        <tbody>
        @php $prevMarkingProgramId = null; @endphp
        @foreach($rows as $row)
            @php
                $rowProgram = optional($row->studentEnroll)->program;
                $anonymousCode = $row->scriptCode->anonymous_code ?? null;
                $canMark = !$isFinalExam || !empty($anonymousCode);
                $is_locked = $row->marks_locked;
                $markValue = old('marks.' . $row->id, $row->achieve_marks !== null && $row->achieve_marks !== ''
                    ? round($row->achieve_marks, 2) + 0 : '');
            @endphp

            @if($cross_program && $rowProgram && $rowProgram->id !== $prevMarkingProgramId)
                @php $prevMarkingProgramId = $rowProgram->id; @endphp
                <tr>
                    <td colspan="{{ $colSpan }}" style="background:var(--ground) !important">
                        <span class="exm-label">
                            <i class="fas fa-graduation-cap"></i>
                            {{ $rowProgram->title }}
                            @if($rowProgram->faculty)<span style="font-weight:600">· {{ $rowProgram->faculty->title }}</span>@endif
                        </span>
                    </td>
                </tr>
            @endif

            <tr>
                @can('subject-marking-unlock')
                <td class="text-center">
                    @if($is_locked)
                    <div class="checkbox checkbox-warning d-inline">
                        <input type="checkbox" class="bulk-unlock-checkbox" id="unlock-{{ $row->id }}" value="{{ $row->id }}">
                        <label for="unlock-{{ $row->id }}" class="cr"></label>
                    </div>
                    @endif
                </td>
                @endcan

                <td class="exm-meta">{{ $loop->iteration }}</td>

                <td class="exm-who">
                    @if($isFinalExam)
                        @if($anonymousCode)
                            <span class="exm-num">{{ $anonymousCode }}</span>
                        @else
                            <span class="exm-tag exm-tag-locked">{{ __('Not set') }}</span>
                        @endif
                    @else
                        <span class="exm-num">{{ optional($row->studentEnroll)->matricule ?? optional(optional($row->studentEnroll)->student)->student_id ?? '' }}</span>
                    @endif
                </td>

                @if(!$isFinalExam)
                <td>{{ trim(optional(optional($row->studentEnroll)->student)->first_name . ' ' . optional(optional($row->studentEnroll)->student)->last_name) }}</td>
                @endif

                @if($cross_program)
                <td><span class="exm-tag">{{ optional($rowProgram)->title ?? '—' }}</span></td>
                @endif

                <td class="n">
                    <input type="text" inputmode="decimal" autocomplete="off"
                           class="exm-mark" name="marks[{{ $row->id }}]"
                           value="{{ $markValue }}"
                           data-v-max="{{ $maxMarks }}" data-v-min="0"
                           aria-label="{{ __('field_max_marks') }}"
                           @unless($canMark && !$is_locked) disabled @endunless>
                    @if($isFinalExam && !$canMark)
                        <span class="exm-feedback">{{ __('Awaiting student exam ID') }}</span>
                    @endif
                </td>

                <td>
                    <input type="text" class="exm-note" name="notes[{{ $row->id }}]"
                           value="{{ old('notes.' . $row->id, $row->note) }}"
                           aria-label="{{ __('field_note') }}"
                           @unless($canMark && !$is_locked) disabled @endunless>
                </td>

                @if(!$isFinalExam)
                <td class="n">
                    <input type="number" min="0" class="exm-mark att-absence"
                           name="absences[{{ $row->student_enroll_id }}]" form="marksForm"
                           data-enroll="{{ $row->student_enroll_id }}" placeholder="0"
                           aria-label="{{ __('Absences') }}"
                           value="{{ $mig_absences[$row->student_enroll_id] ?? '' }}">
                </td>
                <td class="n">
                    <span class="att-score exm-tag exm-num" data-enroll="{{ $row->student_enroll_id }}">0</span>
                </td>
                <td class="n">
                    <span class="att-actual exm-tag exm-num" data-enroll="{{ $row->student_enroll_id }}"
                          title="{{ __('Actual graded attendance mark across all recorded dates') }}">{{ $mig_actual_marks[$row->student_enroll_id] ?? 0 }}</span>
                </td>
                @endif

                <td>
                    @if($is_locked)
                        <span class="exm-tag exm-tag-locked"><i class="fas fa-lock"></i> {{ __('exam_locked') }}</span>
                        @can('subject-marking-unlock')
                        <button type="button" class="btn btn-icon btn-light btn-sm unlock-marks"
                                data-exam-id="{{ $row->id }}" title="{{ __('Unlock') }}">
                            <i class="fas fa-unlock"></i>
                        </button>
                        @endcan
                    @else
                        <span class="exm-tag exm-tag-open"><i class="fas fa-lock-open"></i> {{ __('exam_unlocked') }}</span>
                    @endif
                </td>

                <td class="exm-meta">{{ optional(optional($row->studentEnroll)->semester)->title ?? '' }}</td>
                <td class="exm-meta">{{ optional(optional($row->studentEnroll)->section)->title ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>

        @if($firstRow)
        <caption>{{ $firstRow->subject->code ?? '' }} - {{ $firstRow->type->title ?? '' }} - {{ optional(optional($firstRow->studentEnroll)->session)->title ?? '' }}</caption>
        @endif
    </table>
</div>

@if($isFinalExam)
    @if($cross_program)
    <p class="mt-2 exm-meta px-3"><i class="fas fa-info-circle"></i> Cross-programme mode: Marks apply to students from all sharing programmes. Student exam IDs must be configured before marks can be entered.</p>
    @else
    <p class="mt-2 exm-meta px-3">{{ __('Student marks can only be entered once a student exam ID has been configured by the administrator.') }}</p>
    @endif
@elseif($cross_program)
<p class="mt-2 exm-meta px-3"><i class="fas fa-info-circle"></i> Cross-programme mode: You are entering marks for students from all programmes sharing this course.</p>
@endif
