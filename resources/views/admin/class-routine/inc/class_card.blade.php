{{--
  One class in one grid cell.

  Shared by the screen and the printed sheet so the two can never show a
  different set of facts about the same class. Needs: $row, $flags,
  $timetable, and $showTime (whether to repeat the exact hours inside the
  card, which the grid wants and the day list does not).

  The lecturer is on the card deliberately: a timetable that says where to be
  but not who is teaching sends people to the right room to find nobody there.
--}}
@php
    $types = array_column($flags, 'type');
    $severity = $flags
        ? (array_intersect($types, ['clash', 'duration']) ? 'has-bad' : 'has-warn')
        : '';
@endphp

<div class="ctt-class {{ $severity }}">
    <div class="ctt-code">{{ $row->subject->code ?? '—' }}</div>
    <span class="ctt-title">{{ $row->subject->title ?? '' }}</span>

    <div class="ctt-meta">
        @if(!empty($showTime))
        <i class="far fa-clock"></i>
        {{ $timetable->clock($row->start_time) }} – {{ $timetable->clock($row->end_time) }}<br>
        @endif
        <i class="fas fa-door-open"></i> {{ $row->room->title ?? '—' }}<br>
        <i class="fas fa-user-tie"></i> {{ $timetable->lecturer($row) }}
    </div>

    @foreach($flags as $flag)
    <span class="ctt-flag {{ in_array($flag['type'], ['clash', 'duration'], true) ? 'is-bad' : 'is-warn' }}">
        {{ $flag['note'] }}
    </span>
    @endforeach
</div>
