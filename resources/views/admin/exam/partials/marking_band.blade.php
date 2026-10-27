{{--
  What is being marked, and how far along.

  The count already existed, but only inside the confirmation dialog — which a
  marker reaches after the work is done. On a screen that can carry 88
  students it belongs at the top, where it answers "how much is left" while
  there is still something to do about it.

  Everything here is counted from $rows, which the page already has, so the
  band cannot disagree with the table under it. The distribution is drawn from
  what is SAVED; the page script updates it live as marks are typed.

  Expects $rows, $maxMarks, $selected_exam_type, and optionally $subjectTitle.
--}}

@php
    $marked = collect($rows)->filter(fn ($r) => $r->achieve_marks !== null && $r->achieve_marks !== '');
    $total = count($rows);
    $done = $marked->count();

    $values = $marked->map(fn ($r) => (float) $r->achieve_marks)->values();

    $rate = $total > 0 ? round($done / $total * 100, 1) : 0.0;
@endphp

<div class="exm-band"
     data-exm-total="{{ $total }}"
     data-exm-max="{{ $maxMarks !== null ? round($maxMarks, 2) : '' }}">
    <div class="exm-band-top">
        <div>
            <div class="exm-label">{{ optional($selected_exam_type)->title ?? __('field_type') }}</div>
            <h4>{{ $subjectTitle ?? __('field_subject') }}</h4>
            @if(!is_null($maxMarks))
            <div class="exm-band-sub">
                {{ __('exam_out_of') }} {{ round($maxMarks, 2) + 0 }}
            </div>
            @endif
        </div>

        <div class="exm-progress">
            <div class="exm-label">{{ __('exam_marked_progress') }}</div>
            <div class="v exm-num">
                <span id="exmDone">{{ $done }}</span><span class="c">{{ __('exam_of') }} {{ $total }}</span>
            </div>
        </div>
    </div>

    {{-- role="img" with the figure in the label, because the bar is the only
         place the proportion is drawn. --}}
    <div class="exm-bar" role="img" aria-label="{{ $rate }}% {{ __('exam_marked_progress') }}">
        <span id="exmBar" style="width: {{ $rate }}%;"></span>
    </div>

    {{-- So far. A column typed one row out of step shows up here — an average
         that has moved, or a sudden run at zero — before it is published. --}}
    <div class="exm-stats" id="exmStats" @if($done === 0) style="display:none" @endif>
        <span class="exm-label" style="align-self:center">{{ __('exam_distribution') }}</span>
        <span class="exm-stat"><b id="exmAvg">{{ $values->isNotEmpty() ? round($values->avg(), 1) : 0 }}</b>{{ __('exam_average') }}</span>
        <span class="exm-stat"><b id="exmMin">{{ $values->isNotEmpty() ? round($values->min(), 1) + 0 : 0 }}</b>{{ __('exam_lowest') }}</span>
        <span class="exm-stat"><b id="exmMax">{{ $values->isNotEmpty() ? round($values->max(), 1) + 0 : 0 }}</b>{{ __('exam_highest') }}</span>
        <span class="exm-stat"><b id="exmZero">{{ $values->filter(fn ($v) => $v == 0.0)->count() }}</b>{{ __('exam_at_zero') }}</span>
    </div>
</div>
