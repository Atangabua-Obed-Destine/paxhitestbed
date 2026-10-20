<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} — {{ $title }}</title>

    {{--
      A printed timetable. Goes on a noticeboard and into a file, so it is set
      up for paper first: A4 landscape, the letterhead and the class group at
      the top of every page, and the week as a time-by-day grid.

      The letterhead falls back to the institution's own name and logo. This
      used to require a print_settings row with slug 'class-routine', and
      there is none, so the Print button answered 404 rather than a timetable.
    --}}
    <style>
    @page { size: A4 landscape; margin: 10mm 8mm 12mm 8mm; }

    * { box-sizing: border-box; }

    body {
        margin: 0; padding: 0; background: #fff; color: #1a1a1a;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        font-size: 10pt; line-height: 1.35;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }

    .sheet { padding: 6mm 4mm; }

    /* Letterhead ---------------------------------------------------------- */
    .head {
        display: flex; align-items: center; gap: 12px;
        border-bottom: 2px solid #1a1a1a; padding-bottom: 6px; margin-bottom: 8px;
    }
    .head img { height: 58px; width: auto; }
    .head .mid { flex: 1; text-align: center; }
    .head .inst {
        font-size: 15pt; font-weight: 700; letter-spacing: .02em; text-transform: uppercase;
    }
    .head .sub { font-size: 8.5pt; color: #444; margin-top: 1px; }
    .head .doc {
        font-size: 11pt; font-weight: 700; text-transform: uppercase;
        letter-spacing: .09em; margin-top: 4px;
    }

    /* Which class group this is ------------------------------------------- */
    .ident { margin-bottom: 7px; }
    .ident table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    .ident td {
        border: 1px solid #1a1a1a; padding: 3px 7px; vertical-align: middle;
    }
    .ident td .k {
        display: block; font-size: 7pt; text-transform: uppercase;
        letter-spacing: .06em; color: #555;
    }
    .ident td .v { font-weight: 700; }

    /* The week ------------------------------------------------------------ */
    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.grid th, table.grid td { border: 1px solid #1a1a1a; }
    table.grid thead th {
        background: #1a1a1a; color: #fff; font-size: 8.5pt; font-weight: 700;
        text-transform: uppercase; letter-spacing: .05em; padding: 5px 2px;
        text-align: center;
    }
    table.grid tbody td { vertical-align: top; padding: 2px; height: 1px; }
    td.time {
        width: 8%; background: #f0f0f0; text-align: center; font-weight: 700;
        font-size: 8.5pt; padding: 5px 2px !important; white-space: nowrap;
    }
    td.time small { display: block; font-weight: 400; color: #555; }

    .cls { border: 1px solid #999; border-left: 3px solid #1a1a1a; padding: 3px 4px; }
    .cls + .cls { margin-top: 3px; }
    .cls .code { font-weight: 700; font-size: 9pt; }
    .cls .name {
        font-size: 7.5pt; color: #444; display: block;
        overflow-wrap: break-word; margin-bottom: 1px;
    }
    .cls .meta { font-size: 7.5pt; color: #222; }
    .cls.bad  { border-left-color: #000; background: #f4f4f4; }
    .cls .flag {
        display: block; font-size: 6.5pt; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em; margin-top: 2px; padding: 1px 3px;
        border: 1px solid #1a1a1a; background: #fff;
    }

    /* The list, the conflicts, the footer --------------------------------- */
    h2.sec {
        font-size: 9.5pt; text-transform: uppercase; letter-spacing: .07em;
        border-bottom: 1px solid #1a1a1a; padding-bottom: 2px;
        margin: 10px 0 5px;
    }
    table.list { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.list th, table.list td { border: 1px solid #1a1a1a; padding: 3px 5px; }
    table.list thead th {
        background: #e8e8e8; font-weight: 700; text-transform: uppercase;
        font-size: 7.5pt; letter-spacing: .05em; text-align: left;
    }
    table.list td.c { text-align: center; }

    .note { font-size: 7.5pt; color: #444; margin-top: 4px; }

    .sign { margin-top: 14px; display: flex; gap: 40px; }
    .sign div { flex: 1; }
    .sign .line {
        border-top: 1px solid #1a1a1a; margin-top: 30px; padding-top: 2px;
        font-size: 8pt; text-transform: uppercase; letter-spacing: .05em;
    }

    .foot {
        margin-top: 10px; border-top: 1px solid #999; padding-top: 3px;
        font-size: 7.5pt; color: #555; display: flex; justify-content: space-between;
    }

    /* Keep a class, a row, a table from being cut in half by a page break. */
    tr, .cls, table.list { break-inside: avoid; page-break-inside: avoid; }
    thead { display: table-header-group; }

    .toolbar { text-align: center; padding: 8px; background: #f4f4f4; }
    .toolbar button {
        font: inherit; padding: 6px 16px; cursor: pointer;
        border: 1px solid #1a1a1a; background: #fff;
    }
    @media print { .toolbar { display: none; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">{{ __('btn_print') }}</button>
</div>

<div class="sheet">

    {{-- Letterhead: a configured print_settings row wins, otherwise the
         institution's own identity, which is always there. --}}
    @php
        $logo = optional($setting)->logo_path;
        $logoPath = $logo && is_file(public_path('uploads/setting/'.$logo))
            ? asset('uploads/setting/'.$logo) : null;
        $leftLogo = $print && $print->logo_left && is_file('uploads/'.$path.'/'.$print->logo_left)
            ? asset('uploads/'.$path.'/'.$print->logo_left) : $logoPath;
        $rightLogo = $print && $print->logo_right && is_file('uploads/'.$path.'/'.$print->logo_right)
            ? asset('uploads/'.$path.'/'.$print->logo_right) : null;
    @endphp

    <div class="head">
        <div style="width:62px">
            @if($leftLogo)<img src="{{ $leftLogo }}" alt="">@endif
        </div>
        <div class="mid">
            <div class="inst">{{ $print && $print->title ? strip_tags($print->title) : institution_name() }}</div>
            @php
                // Built here rather than inline: two adjacent @if/@endif pairs
                // with nothing between them do not both compile, and the
                // address line only exists at all if something is configured.
                $contact = array_filter([
                    optional($setting)->address,
                    optional($setting)->phone,
                    optional($setting)->email,
                ]);
            @endphp
            @if($contact)
            <div class="sub">{{ implode(' · ', $contact) }}</div>
            @endif
            <div class="doc">{{ __('Class Timetable') }}</div>
        </div>
        <div style="width:62px;text-align:right">
            @if($rightLogo)<img src="{{ $rightLogo }}" alt="">@endif
        </div>
    </div>

    @if($rows->isEmpty())
        <p style="text-align:center;padding:30px 0">
            {{ __('No classes are scheduled for this selection.') }}
        </p>
    @else
    @php $first = $rows->first(); @endphp

    {{-- What this sheet is, so a printed page is never ambiguous. --}}
    <div class="ident">
        <table>
            <tr>
                <td style="width:34%">
                    <span class="k">{{ __('field_program') }}</span>
                    <span class="v">{{ $first->program->title ?? '—' }}</span>
                </td>
                <td style="width:16%">
                    <span class="k">{{ __('field_session') }}</span>
                    <span class="v">{{ $session_title }}</span>
                </td>
                <td style="width:20%">
                    <span class="k">{{ __('field_semester') }}</span>
                    <span class="v">{{ $first->semester->title ?? '—' }}</span>
                </td>
                <td style="width:12%">
                    <span class="k">{{ __('field_section') }}</span>
                    <span class="v">{{ $first->section->title ?? '—' }}</span>
                </td>
                <td style="width:18%">
                    <span class="k">{{ __('Classes') }} / {{ __('Contact hours') }}</span>
                    <span class="v">{{ $summary['classes'] }} / {{ $summary['contact_hours'] }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- The week ------------------------------------------------------- --}}
    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('Time') }}</th>
                @foreach($grid['days'] as $key)
                <th>{{ __($key) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($grid['bands'] as $band)
            <tr>
                <td class="time">
                    {{ $timetable->clock($band['start']) }}
                    <small>{{ $timetable->clock($band['end']) }}</small>
                </td>
                @foreach(array_keys($grid['days']) as $num)
                <td>
                    @foreach($band['cells'][$num] as $row)
                    @php $flags = $conflicts[$row->id] ?? []; @endphp
                    <div class="cls {{ $flags ? 'bad' : '' }}">
                        <div class="code">{{ $row->subject->code ?? '—' }}</div>
                        <span class="name">{{ $row->subject->title ?? '' }}</span>
                        <div class="meta">
                            {{ $timetable->clock($row->start_time) }}–{{ $timetable->clock($row->end_time) }}<br>
                            {{ $row->room->title ?? '—' }}<br>
                            {{ $timetable->lecturer($row) }}
                        </div>
                        @if($flags)
                        <span class="flag">
                            {{ __('Conflict') }}
                        </span>
                        @endif
                    </div>
                    @endforeach
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- The same classes as a list, because a grid cell cannot hold a full
         course title and the registry works from titles. --}}
    <h2 class="sec">{{ __('Classes in full') }}</h2>
    <table class="list">
        <thead>
            <tr>
                <th>{{ __('field_day') }}</th>
                <th>{{ __('Time') }}</th>
                <th>{{ __('Code') }}</th>
                <th>{{ __('field_subject') }}</th>
                <th>{{ __('field_room') }}</th>
                <th>{{ __('Lecturer') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows->sortBy(fn ($row) => sprintf('%02d-%s', (int) $row->day, $row->start_time)) as $row)
            <tr>
                <td>{{ __($timetable::DAYS[(int) $row->day] ?? '—') }}</td>
                <td style="white-space:nowrap">
                    {{ $timetable->clock($row->start_time) }}–{{ $timetable->clock($row->end_time) }}
                </td>
                <td>{{ $row->subject->code ?? '—' }}</td>
                <td>{{ $row->subject->title ?? '—' }}</td>
                <td>{{ $row->room->title ?? '—' }}</td>
                <td>{{ $timetable->lecturer($row) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Conflicts are printed as well as shown on screen: this sheet is the
         working document the registry fixes the timetable from, and a
         problem left off it is a problem nobody acts on. --}}
    @if($summary['flagged'])
    <h2 class="sec">
        {{ __('Scheduling problems') }}
        ({{ trans_choice('{1}:count class|[2,*]:count classes', $summary['flagged'], ['count' => $summary['flagged']]) }})
    </h2>
    <table class="list">
        <thead>
            <tr>
                <th style="width:14%">{{ __('field_day') }} / {{ __('Time') }}</th>
                <th style="width:12%">{{ __('Code') }}</th>
                <th>{{ __('What is wrong') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows->sortBy(fn ($row) => sprintf('%02d-%s', (int) $row->day, $row->start_time)) as $row)
            @php $flags = $conflicts[$row->id] ?? []; @endphp
            @if($flags)
            <tr>
                <td style="white-space:nowrap">
                    {{ __($timetable::DAYS[(int) $row->day] ?? '—') }}<br>
                    {{ $timetable->clock($row->start_time) }}–{{ $timetable->clock($row->end_time) }}
                </td>
                <td>{{ $row->subject->code ?? '—' }}</td>
                <td>
                    @foreach($flags as $flag)
                    &bull; {{ $flag['note'] }}<br>
                    @endforeach
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>
    <div class="note">
        {{ __('A course taught to several programmes at the same hour in the same room is a joint lecture and is not reported as a conflict.') }}
    </div>
    @endif

    <div class="sign">
        <div><div class="line">{{ __('Prepared by') }}</div></div>
        <div><div class="line">{{ __('Head of Department') }}</div></div>
        <div><div class="line">{{ __('Academic Registrar') }}</div></div>
    </div>
    @endif

    <div class="foot">
        <span>{{ institution_name() }}</span>
        <span>
            {{ __('Produced') }} {{ now()->format('d/m/Y H:i') }}
            @if($rows->isNotEmpty())
            &middot; {{ trans_choice('{1}:count class|[2,*]:count classes', $rows->count(), ['count' => $rows->count()]) }}
            @endif
        </span>
    </div>
</div>

<script>
// Open the print dialog once the page and its logo have actually loaded:
// firing it earlier prints a sheet with the letterhead missing.
window.addEventListener('load', function () {
    window.setTimeout(function () { window.print(); }, 250);
});
</script>

</body>
</html>
