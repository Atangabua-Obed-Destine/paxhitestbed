{{--
  What is being taken, and where it stands.

  The marking screen opens with this and the attendance screen did not: it
  opened with between four and seven stacked Bootstrap alerts and no count of
  anything. A supervisor looking at 88 names wants to know how many are still
  unmarked before they start scrolling, and whether anyone is sitting the exam
  who should not be.

  Every figure here is counted from the rows and records already loaded for
  the table — no extra query. Present and absent come from the SAVED records,
  so on a fresh sheet the bar is empty and says so; it is the state of the
  record, not of the checkboxes, which is what you want before you submit.

  Expects $rows, and optionally $attendances, $exam_routine,
  $selected_exam_type, $attendance_setting, $student_attendance_percentages.
--}}

@php
    $total = count($rows);

    $isFinal = isset($selected_exam_type) && $selected_exam_type && $selected_exam_type->is_final == 1;

    $present = 0;
    $absent = 0;
    $lockedCount = 0;

    if (isset($attendances)) {
        foreach ($attendances as $att) {
            if ((int) $att->attendance === 1) { $present++; }
            if ((int) $att->attendance === 2) { $absent++; }
            if ($att->attendance_locked) { $lockedCount++; }
        }
    }

    // A record can exist for somebody no longer in this list, so the marked
    // count is clamped rather than trusted to be <= the roll.
    $marked = min($present + $absent, $total);
    $unmarked = max(0, $total - $marked);

    $pct = $total > 0 ? round($marked / $total * 100) : 0;
    $presentPct = $total > 0 ? $present / $total * 100 : 0;
    $absentPct = $total > 0 ? $absent / $total * 100 : 0;

    // Who cannot sit it. Counted the same way the roll counts it, so the band
    // and the rows cannot disagree.
    $minimum = $attendance_setting->minimum_attendance_percentage ?? 70;
    $eligibilityOn = $isFinal && isset($attendance_setting) && $attendance_setting
        && $attendance_setting->is_enabled;

    $ineligible = 0;
    $bypassed = 0;

    if ($eligibilityOn) {
        foreach ($rows as $row) {
            $percentage = $student_attendance_percentages[$row->id]['percentage'] ?? null;

            if (!is_null($percentage) && $percentage < $minimum) {
                $ineligible++;
            }
        }
    }

    if ($isFinal && isset($attendances)) {
        foreach ($attendances as $att) {
            if ($att->bypass_course_attendance) { $bypassed++; }
        }
    }
@endphp

<div class="exm-band">
    <div class="exm-band-top">
        <div>
            <span class="exm-label">{{ __('field_attendance') }}</span>
            <h4>
                {{ $bandSubject ?? __('This course') }}
                @if($bandType)<span style="color:var(--muted);font-weight:600"> · {{ $bandType }}</span>@endif
            </h4>
            <div class="exm-band-sub">
                @if($bandSession){{ $bandSession }} · @endif
                {{ trans_choice(':count student|:count students', $total, ['count' => number_format($total)]) }}
                @if($isFinal)
                    · {{ __('Final exam') }}
                @elseif(isset($selected_exam_type) && $selected_exam_type)
                    · {{ __('Continuous assessment') }}
                @endif
                @if(isset($exam_routine) && $exam_routine)
                    · {{ \Carbon\Carbon::parse($exam_routine->date)->format('d M Y') }},
                    {{ \Carbon\Carbon::parse($exam_routine->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($exam_routine->end_time)->format('H:i') }}
                @endif
            </div>
        </div>

        <div class="exm-progress">
            <span class="exm-label">{{ __('Recorded') }}</span>
            <div>
                <span class="v exm-num">{{ number_format($marked) }}</span>
                <span class="c exm-num">/ {{ number_format($total) }} · {{ $pct }}%</span>
            </div>
        </div>
    </div>

    {{-- Present, absent, and what is still blank — in that order, left to
         right, so the bar reads the way the roll does. --}}
    <div class="exm-split" title="{{ $present }} {{ __('attendance_present') }} · {{ $absent }} {{ __('attendance_absent') }} · {{ $unmarked }} {{ __('not recorded') }}">
        <div style="width:{{ $presentPct }}%;background:var(--good)"></div>
        <div style="width:{{ $absentPct }}%;background:var(--bad)"></div>
    </div>

    <div class="exm-stats">
        <span class="exm-stat"><b class="exm-num">{{ number_format($present) }}</b>{{ __('attendance_present') }}</span>
        <span class="exm-stat"><b class="exm-num">{{ number_format($absent) }}</b>{{ __('attendance_absent') }}</span>

        <span class="exm-stat {{ $unmarked > 0 ? 'is-bad' : '' }}">
            <b class="exm-num">{{ number_format($unmarked) }}</b>{{ __('not recorded') }}
        </span>

        @if($lockedCount > 0)
        <span class="exm-stat"><b class="exm-num">{{ number_format($lockedCount) }}</b>{{ __('Locked') }}</span>
        @endif

        @if($eligibilityOn)
        <span class="exm-stat {{ $ineligible > 0 ? 'is-bad' : '' }}">
            <b class="exm-num">{{ number_format($ineligible) }}</b>{{ __('below :pct% attendance', ['pct' => $minimum]) }}
        </span>
        @endif

        @if($bypassed > 0)
        <span class="exm-stat"><b class="exm-num">{{ number_format($bypassed) }}</b>{{ __('Bypassed') }}</span>
        @endif
    </div>
</div>
