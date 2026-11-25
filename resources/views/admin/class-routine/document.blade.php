<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9ecef; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
    .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
           padding: 10px 16px; background: #1f3a5f; color: #fff; font-size: 13px; }
    .bar form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0; }
    .bar select, .bar button, .bar a { font: inherit; padding: 6px 10px; border-radius: 4px; border: 0; }
    .bar button, .bar a { background: #fff; color: #1f3a5f; font-weight: 700; cursor: pointer; text-decoration: none; }
    .bar .print { margin-left: auto; background: #ffd54f; }
    .bar .note { opacity: .85; }
    .sheet { width: 277mm; min-height: 180mm; margin: 14px auto; padding: 10mm 8mm; background: #fff;
             box-shadow: 0 1px 4px rgba(0,0,0,.25); page-break-after: always; break-after: page; }
    .sheet:last-child { page-break-after: auto; break-after: auto; }
    .head { text-align: center; line-height: 1.45; margin-bottom: 6px; }
    .head div { font-size: 12px; }
    .head .h1 { font-weight: 700; font-size: 13px; }
    .head .spec { font-weight: 700; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
    thead th { font-weight: 700; text-align: center; font-size: 11px; }
    th.day, td.day { width: 11%; font-weight: 700; text-align: left; vertical-align: top; }
    td.cls { font-size: 10.5px; line-height: 1.3; }
    td.cls .code { font-weight: 400; }
    td.cls .lect { display: block; margin-top: 2px; }
    td.cls + td.cls { }
    td.tall { text-align: center; vertical-align: middle; padding: 0; width: 6%; }
    td.tall span { display: inline-block; writing-mode: vertical-rl; transform: rotate(180deg);
                   font-size: 16px; letter-spacing: .06em; white-space: nowrap; }
    td.brk { text-align: center; vertical-align: middle; font-weight: 700; background: #fff59d; }
    .rooms { margin-top: 6px; font-size: 10px; color: #333; }
    .empty-state { max-width: 640px; margin: 40px auto; padding: 24px; background: #fff; text-align: center; font-size: 14px; }
    @media print {
        body { background: #fff; }
        .bar { display: none; }
        .sheet { margin: 0; padding: 0; width: auto; min-height: 0; box-shadow: none; }
    }
</style>
</head>
<body>
@if(!empty($controls))
<div class="bar">
    <form method="get" action="{{ $action }}">
        <select name="session" aria-label="{{ __('Session') }}">
            @foreach($sessions as $s)
            <option value="{{ $s->id }}" {{ (int) optional($session)->id === (int) $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
            @endforeach
        </select>
        <select name="semester" aria-label="{{ __('Semester') }}">
            @foreach($semesters as $s)
            <option value="{{ $s->id }}" {{ (int) optional($semester)->id === (int) $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
            @endforeach
        </select>
        <select name="program" aria-label="{{ __('Programme') }}">
            <option value="">{{ __('All specialties') }}</option>
            @foreach($programs as $p)
            <option value="{{ $p->id }}" {{ (int) request('program') === (int) $p->id ? 'selected' : '' }}>{{ $p->title }}</option>
            @endforeach
        </select>
        <button type="submit">{{ __('Show') }}</button>
    </form>
    @if($sheets)
    <span class="note">{{ trans_choice('{1}:count timetable, one per page|[2,*]:count timetables, one per page', count($sheets), ['count' => count($sheets)]) }}</span>
    @endif
    <a href="{{ $back }}">{{ __('Back') }}</a>
    @if($sheets)<button type="button" class="print" onclick="window.print()">{{ __('Print') }}</button>@endif
</div>
@elseif($sheets)
<div class="bar"><span>{{ $title }}</span><a href="{{ $back }}">{{ __('Back') }}</a><button type="button" class="print" onclick="window.print()">{{ __('Print') }}</button></div>
@endif

@forelse($sheets as $sheet)
<section class="sheet">
    <div class="head">
        <div class="h1">{{ $sheet['heading'] }}</div>
        @if($sheet['programme_line'])<div class="h1">{{ $sheet['programme_line'] }}</div>@endif
        <div class="h1">{{ $sheet['semester_line'] }}</div>
        <div class="spec">{{ __('SPECIALTY') }}: {{ $sheet['specialty'] }}</div>
    </div>
    <table>
        <thead>
            <tr>
                <th class="day">{{ __('DAY') }}</th>
                @foreach($sheet['columns'] as $c)
                <th>{{ \Carbon\Carbon::createFromFormat('H:i', $c['start'])->format('g:i') }} – {{ \Carbon\Carbon::createFromFormat('H:i', $c['end'])->format('g:ia') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($sheet['rows'] as $row)
            <tr>
                <td class="day">{{ strtoupper(__($row['day'])) }}</td>
                @foreach($row['cells'] as $cell)
                    @if($cell['type'] === 'tall-break')
                    <td class="tall" colspan="{{ $cell['colspan'] }}" rowspan="{{ $cell['rowspan'] }}"><span>{{ $cell['title'] }}</span></td>
                    @elseif($cell['type'] === 'break')
                    <td class="brk" colspan="{{ $cell['colspan'] }}">{{ $cell['title'] }}</td>
                    @elseif($cell['type'] === 'class')
                    <td class="cls" colspan="{{ $cell['colspan'] }}">
                        @foreach($cell['items'] as $l)
                        <div>
                            <span class="code">{{ implode(' / ', $l->codes) }}</span> {{ $l->title }}
                            @if($l->lecturer)<span class="lect">({{ $l->lecturer }})</span>@endif
                        </div>
                        @endforeach
                    </td>
                    @else
                    <td colspan="{{ $cell['colspan'] }}"></td>
                    @endif
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($sheet['rooms'])
    <div class="rooms">{{ __('Room') }}: {{ implode(', ', $sheet['rooms']) }}</div>
    @endif
</section>
@empty
<div class="empty-state">
    {{ $session && $semester ? __('No classes are scheduled for this semester yet.') : __('Choose a session and a semester.') }}
</div>
@endforelse
</body>
</html>
