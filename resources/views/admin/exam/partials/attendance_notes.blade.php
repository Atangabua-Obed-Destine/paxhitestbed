{{--
  What the person needs to know before they start ticking.

  This replaces a stack of between four and seven full-width Bootstrap alerts
  in four different colours, which appeared above the table before a single
  name was visible and said, among other things, both "attendance taken" and
  "scheduled on such a date" and "students need 70%" at the same time. They
  are the same sentences, one line each, in the one place.

  Order is deliberate: what blocks you, then what you must know, then what
  merely confirms. Nothing here is new information and nothing was dropped.

  Expects $selected_exam_type, $can_take_attendance, $attendances,
  $exam_routine, $attendance_setting.
--}}

@php
    $notesFinal = isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1;
    $notesBlocked = $notesFinal && isset($can_take_attendance) && !$can_take_attendance;
@endphp

<div class="exm-notes">
    @if($notesBlocked)
    <div class="exm-note-row is-bad">
        <i class="fas fa-exclamation-triangle"></i>
        <span>
            <b>{{ __('Exam Not Scheduled') }}</b> &mdash;
            {{ __('This is a final exam and has not been scheduled in the exam routine. Schedule it before taking attendance.') }}
            <a href="{{ route('admin.exam-routine.create') }}">{{ __('Schedule it now') }}</a>
        </span>
    </div>
    @else
        @if(isset($attendances))
        <div class="exm-note-row {{ count($attendances) > 0 ? 'is-good' : '' }}">
            <i class="fas {{ count($attendances) > 0 ? 'fa-check-circle' : 'fa-circle' }}"></i>
            <span>{{ count($attendances) > 0 ? __('attendance_taken') : __('attendance_not_taken') }}</span>
        </div>
        @endif

        @if(isset($exam_routine) && $exam_routine)
        <div class="exm-note-row">
            <i class="fas fa-calendar-alt"></i>
            <span>
                <b>{{ __('Scheduled') }}</b> &mdash;
                {{ \Carbon\Carbon::parse($exam_routine->date)->format('d M Y') }},
                {{ \Carbon\Carbon::parse($exam_routine->start_time)->format('H:i') }}
                &ndash; {{ \Carbon\Carbon::parse($exam_routine->end_time)->format('H:i') }}
            </span>
        </div>
        @elseif(isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 0)
        <div class="exm-note-row">
            <i class="fas fa-info-circle"></i>
            <span>{{ __('This is continuous assessment (:type). It does not need to be scheduled.', ['type' => $selected_exam_type->title]) }}</span>
        </div>
        @endif

        @if(isset($attendance_setting) && $attendance_setting && $attendance_setting->is_enabled && $notesFinal)
        <div class="exm-note-row is-warn">
            <i class="fas fa-user-clock"></i>
            <span>
                <b>{{ __('Eligibility') }}</b> &mdash;
                {{ __('A student needs at least :pct% course attendance to sit a final exam. Anyone below that is marked absent and cannot write it unless bypassed.', ['pct' => $attendance_setting->minimum_attendance_percentage]) }}
            </span>
        </div>
        @endif
    @endif
</div>
