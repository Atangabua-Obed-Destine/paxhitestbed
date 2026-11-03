{{-- The course class list as a document.

     Same figures as the screen and the workbook, from the same array
     (App\Services\Academic\CourseRoster) and the same scope
     (App\Support\CourseClassFilter), so the three cannot disagree.

     Laid out for dompdf: tables rather than flex or grid, no remote assets,
     and `thead { display: table-header-group }` so a long register repeats its
     headings on every page it runs onto.

     The screen's attendance strip becomes a DATED grid here. On paper there is
     no tooltip to say which session a mark belongs to, so the date has to be
     printed; and because a date column cannot shrink below legibility, the
     grid is chunked into blocks that fit the page rather than squeezed. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Class List') }}</title>
    {!! $letterheadStyles !!}
    <style>
        @page { margin: 12mm 10mm 14mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1d2530; }

        .masthead { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 6px; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 {
            font-size: 10.5px; margin: 14px 0 5px; padding-bottom: 2px;
            border-bottom: 1px solid #1d2530; text-transform: uppercase; letter-spacing: .05em;
        }
        .lede { color: #4a5563; margin: 0 0 8px; }
        .note { font-size: 7.5px; color: #4a5563; margin: 3px 0 0; }
        .muted { color: #6b7480; }

        table { border-collapse: collapse; width: 100%; }
        table.grid th, table.grid td { border: .5px solid #b9c0ca; padding: 2.5px 4px; vertical-align: top; }
        table.grid th {
            background: #e8ebef; font-size: 7px; text-transform: uppercase;
            letter-spacing: .03em; text-align: left;
        }
        table.grid tfoot td { background: #f2f4f7; font-weight: bold; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        table.filters td { border: .5px solid #b9c0ca; padding: 3px 6px; }
        table.filters td.k { background: #f2f4f7; width: 14%; font-weight: bold; }

        table.tiles td { border: .5px solid #b9c0ca; padding: 6px 8px; width: 20%; }
        .tile-n { font-size: 15px; font-weight: bold; }
        .tile-l { font-size: 7px; text-transform: uppercase; letter-spacing: .05em; color: #4a5563; }
        .tile-c { font-size: 8px; font-weight: normal; color: #4a5563; }

        /* Ordered severity: attended, excused, did not attend. Holiday is not a
           state of the student, so it takes a neutral. Text only, and the word
           always travels with the colour -- paper has no tooltip. */
        .s-present { color: #0f7a3d; font-weight: bold; }
        .s-leave { color: #b8860b; font-weight: bold; }
        .s-absent { color: #b3261e; font-weight: bold; }
        .s-holiday { color: #6b7480; font-weight: bold; }
        .zero { color: #c3c8cf; }

        table.reg th, table.reg td { border: .5px solid #b9c0ca; padding: 1.5px 0; text-align: center; font-size: 6.5px; }
        table.reg th.d { width: 14px; }
        table.reg td.nm { text-align: left; padding-left: 3px; font-size: 7px; white-space: nowrap; }
        table.reg th.nm { text-align: left; padding-left: 3px; }

        .sig-col { width: 90px; }
        .page-break { page-break-before: always; }

        table.sign { margin-top: 26px; page-break-inside: avoid; }
        table.sign td { padding-top: 3px; border-top: .5px solid #333; width: 30%; font-size: 8px; }
        table.sign td.gap { border-top: none; width: 5%; }
    </style>
</head>
<body>

@php
    $t = $report['totals'];
    $subject = $report['subject'];
    $held = $report['sessions_held'];

    $rateText = fn ($rate) => $rate === null ? '—' : $rate . '%';
    $rateClass = function ($rate) {
        if ($rate === null) return 'zero';
        return $rate >= 75 ? 's-present' : ($rate >= 50 ? 's-leave' : 's-absent');
    };

    $letters = ['present' => __('P'), 'absent' => __('A'), 'leave' => __('L'), 'holiday' => __('H'), 'unmarked' => ''];

    // A date column cannot shrink below legibility, so the register is broken
    // into blocks that fit the page instead of being squeezed off it.
    $perBlock = 40;
    $blocks = $held > 0 ? array_chunk($report['dates'], $perBlock, true) : [];
@endphp

{!! $letterhead !!}

<div class="masthead">{{ $report['institution'] }}</div>

<h1>{{ __('Class List and Attendance') }}</h1>
<p class="lede">
    <strong>{{ $subject->code ?? '' }}</strong> — {{ $subject->title ?? '' }}<br>
    {{ __('Generated') }} {{ $report['generated_at']->format('d/m/Y H:i') }}
</p>

<table class="filters">
    @foreach($report['filters'] as $label => $value)
    @if($value !== '' && $value !== null)
    <tr><td class="k">{{ $label }}</td><td>{{ $value }}</td></tr>
    @endif
    @endforeach
    <tr><td class="k">{{ __('Sessions held') }}</td><td>{{ $held }}</td></tr>
</table>

@if(!$report['has_data'])

<h2>{{ __('No students') }}</h2>
<p>{{ __('Nobody is enrolled on this course under these filters.') }}</p>

@else

<table class="tiles" style="margin-top:8px">
    <tr>
        <td>
            <div class="tile-n">{{ $t['students'] }}</div>
            <div class="tile-l">{{ __('Students') }}</div>
            <div class="tile-c">{{ count($report['by_class']) }} {{ __('group(s)') }}</div>
        </td>
        <td>
            <div class="tile-n">{{ $held }}</div>
            <div class="tile-l">{{ __('Sessions held') }}</div>
        </td>
        <td>
            <div class="tile-n">{{ number_format($t['present']) }}</div>
            <div class="tile-l">{{ __('Present') }}</div>
            <div class="tile-c">{{ number_format($t['absent']) }} {{ __('absent') }}</div>
        </td>
        <td>
            <div class="tile-n {{ $rateClass($t['rate']) }}">{{ $rateText($t['rate']) }}</div>
            <div class="tile-l">{{ __('Attendance') }}</div>
            <div class="tile-c">{{ __('holidays not counted') }}</div>
        </td>
        <td>
            <div class="tile-n {{ $t['unmarked'] ? '' : 'zero' }}">{{ number_format($t['unmarked']) }}</div>
            <div class="tile-l">{{ __('Not marked') }}</div>
            <div class="tile-c">{{ __('not the same as absent') }}</div>
        </td>
    </tr>
</table>

@if($report['outside']['records'] > 0)
<p class="note">
    <strong>{{ __('Narrowed:') }}</strong>
    {{ __('this document leaves out :n attendance records on :s other enrolments of this course, because a group was chosen.', [
        'n' => number_format($report['outside']['records']),
        's' => number_format($report['outside']['students']),
    ]) }}
</p>
@endif

@if(count($report['at_risk']) > 0)
<h2>{{ __('Needs attention') }}</h2>
<table class="grid">
    <thead>
        <tr>
            <th>{{ __('field_matricule') }}</th>
            <th>{{ __('field_name') }}</th>
            <th>{{ __('field_program') }}</th>
            <th class="num">{{ __('P') }}</th>
            <th class="num">{{ __('A') }}</th>
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
            <td>{{ $row['program'] }}</td>
            <td class="num">{{ $row['present'] }}</td>
            <td class="num">{{ $row['absent'] }}</td>
            <td class="num {{ $rateClass($row['rate']) }}">{{ $rateText($row['rate']) }}</td>
            <td>{{ implode(' · ', $row['reasons']) }}</td>
            <td>{{ $row['phone'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<h2>{{ __('Class list') }}</h2>
<table class="grid">
    <thead>
        <tr>
            <th style="width:22px">#</th>
            <th>{{ __('field_matricule') }}</th>
            <th>{{ __('field_name') }}</th>
            @if(count($report['by_class']) > 1)
            <th>{{ __('field_program') }}</th>
            @endif
            <th class="num">{{ __('P') }}</th>
            <th class="num">{{ __('A') }}</th>
            <th class="num">{{ __('L') }}</th>
            <th class="num">{{ __('Not marked') }}</th>
            <th class="num">{{ __('Attendance %') }}</th>
            <th>{{ __('Last present') }}</th>
            <th class="sig-col">{{ __('Signature') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['students'] as $i => $row)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row['matricule'] }}</td>
            <td>{{ $row['name'] }}</td>
            @if(count($report['by_class']) > 1)
            <td>{{ $row['program'] }}</td>
            @endif
            <td class="num">{{ $row['present'] }}</td>
            <td class="num {{ $row['absent'] ? '' : 'zero' }}">{{ $row['absent'] }}</td>
            <td class="num {{ $row['leave'] ? '' : 'zero' }}">{{ $row['leave'] }}</td>
            <td class="num {{ $row['unmarked'] ? '' : 'zero' }}">{{ $row['unmarked'] }}</td>
            <td class="num {{ $rateClass($row['rate']) }}">{{ $rateText($row['rate']) }}</td>
            <td>{{ $row['last_seen'] ? date('d/m/Y', strtotime($row['last_seen'])) : '—' }}</td>
            <td></td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="{{ count($report['by_class']) > 1 ? 4 : 3 }}">{{ __('field_total') }}</td>
            <td class="num">{{ number_format($t['present']) }}</td>
            <td class="num">{{ number_format($t['absent']) }}</td>
            <td class="num">{{ number_format($t['leave']) }}</td>
            <td class="num">{{ number_format($t['unmarked']) }}</td>
            <td class="num">{{ $rateText($t['rate']) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

@if(count($report['by_class']) > 1)
<h2>{{ __('By group') }}</h2>
<table class="grid">
    <thead>
        <tr>
            <th>{{ __('field_program') }}</th>
            <th>{{ __('field_semester') }}</th>
            <th>{{ __('field_section') }}</th>
            <th class="num">{{ __('field_students') }}</th>
            <th class="num">{{ __('P') }}</th>
            <th class="num">{{ __('A') }}</th>
            <th class="num">{{ __('L') }}</th>
            <th class="num">{{ __('Not marked') }}</th>
            <th class="num">{{ __('Attendance %') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['by_class'] as $row)
        <tr>
            <td>{{ $row['program'] }}</td>
            <td>{{ $row['semester'] }}</td>
            <td>{{ $row['section'] }}</td>
            <td class="num">{{ $row['students'] }}</td>
            <td class="num">{{ $row['present'] }}</td>
            <td class="num">{{ $row['absent'] }}</td>
            <td class="num">{{ $row['leave'] }}</td>
            <td class="num">{{ $row['unmarked'] }}</td>
            <td class="num {{ $rateClass($row['rate']) }}">{{ $rateText($row['rate']) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if($held > 0)

<div class="page-break"></div>
<h2>{{ __('Register') }}</h2>
<p class="note">
    {{ __('P present · A absent · L leave · H holiday · a blank cell means the register was taken that day but this student was not marked, which is not the same as absent.') }}
</p>

@foreach($blocks as $block)
<table class="reg" style="margin-bottom:10px">
    <thead>
        <tr>
            <th class="nm" style="width:70px">{{ __('field_matricule') }}</th>
            <th class="nm">{{ __('field_name') }}</th>
            @foreach($block as $date)
            <th class="d">{{ date('d/m', strtotime($date)) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($report['students'] as $row)
        <tr>
            <td class="nm">{{ $row['matricule'] }}</td>
            <td class="nm">{{ mb_strimwidth($row['name'], 0, 28, '…') }}</td>
            @foreach($block as $index => $date)
            @php $mark = $row['strip'][$index] ?? 'unmarked'; @endphp
            <td class="{{ $mark === 'unmarked' ? '' : 's-'.$mark }}">{{ $letters[$mark] }}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
@endforeach

<h2>{{ __('Session by session') }}</h2>
<table class="grid">
    <thead>
        <tr>
            <th>{{ __('field_date') }}</th>
            <th>{{ __('Day') }}</th>
            <th class="num">{{ __('Marked') }}</th>
            <th class="num">{{ __('P') }}</th>
            <th class="num">{{ __('A') }}</th>
            <th class="num">{{ __('L') }}</th>
            <th class="num">{{ __('H') }}</th>
            <th class="num">{{ __('Attendance %') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['by_date'] as $row)
        <tr>
            <td>{{ date('d/m/Y', strtotime($row['date'])) }}</td>
            <td>{{ date('D', strtotime($row['date'])) }}</td>
            <td class="num">{{ $row['marked'] }}</td>
            <td class="num">{{ $row['present'] }}</td>
            <td class="num {{ $row['absent'] ? '' : 'zero' }}">{{ $row['absent'] }}</td>
            <td class="num {{ $row['leave'] ? '' : 'zero' }}">{{ $row['leave'] }}</td>
            <td class="num {{ $row['holiday'] ? '' : 'zero' }}">{{ $row['holiday'] }}</td>
            <td class="num {{ $rateClass($row['rate']) }}">{{ $rateText($row['rate']) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@endif

<h2>{{ __('How this report counts') }}</h2>
<p class="note">
    {{ __('A student is counted once, however many enrolments they hold. A student gets a fresh enrolment record each semester and their attendance is split across those records, so counting enrolments would list the same person several times, each with part of their record.') }}
</p>
<p class="note">
    {{ __('The attendance rate is present ÷ (present + absent + leave). A holiday is not a working day and is left out of the rate. A session the register was taken but this student was not marked is counted as “not marked”, never as an absence.') }}
</p>
<p class="note">
    {{ __('Students are included on the strength of their enrolment for this session, whichever semester they have since moved on to — a course whose students have progressed still has the class that sat it.') }}
</p>

@endif

<table class="sign">
    <tr>
        <td>{{ __('Lecturer') }}</td>
        <td class="gap"></td>
        <td>{{ __('Head of Department') }}</td>
        <td class="gap"></td>
        <td>{{ __('Date') }}</td>
    </tr>
</table>

</body>
</html>
