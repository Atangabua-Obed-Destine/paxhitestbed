<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Admissions Demand Report') }}</title>
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
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        table.filters td { border: .5px solid #b9c0ca; padding: 3px 6px; }

        table.tiles td { border: .5px solid #b9c0ca; padding: 6px 8px; width: 20%; }
        .tile-n { font-size: 18px; font-weight: bold; }
        .tile-l { font-size: 7px; text-transform: uppercase; letter-spacing: .05em; color: #4a5563; }

        .bar { background: #eef0f3; height: 5px; width: 100%; margin-top: 2px; }
        .bar span { display: block; height: 5px; background: #3a6ea5; }

        .sig { font-weight: bold; white-space: nowrap; }
        .sig-strong { color: #1e7b4f; }
        .sig-reachable { color: #2d5f91; }
        .sig-below { color: #9a5b00; }
        .sig-none { color: #b0302a; }

        table.groups td { width: 25%; vertical-align: top; border: .5px solid #b9c0ca; padding: 5px 7px; }
        table.groups ul { margin: 4px 0 0; padding-left: 11px; }
        table.groups li { margin-bottom: 1px; }

        .matrix td, .matrix th { text-align: center; }
        .matrix .label { text-align: left; }
        .zero { color: #c3c8cf; }
        .yes { color: #1e7b4f; font-weight: bold; }
        .no { color: #b0302a; font-weight: bold; }

        .page-break { page-break-before: always; }
        /* Room to sign above each rule, and the label directly beneath it. With
           the padding inside the cell instead, the rule sat hard under the
           paragraph above and read as underlining. */
        table.sign { margin-top: 40px; page-break-inside: avoid; }
        table.sign td { padding-top: 3px; border-top: .5px solid #333; width: 30%; font-size: 8px; }
        table.sign td.gap { border-top: none; width: 5%; }
    </style>
</head>
<body>

@php
    $t = $report['totals'];
    $min = $report['min_class'];
    $showDrafts = $report['drafts_counted'];
@endphp

@if(trim($letterhead) !== '')
    {!! $letterhead !!}
@else
    <div class="masthead">{{ $report['institution'] }}</div>
@endif

<h1>{{ __('Admissions Demand Report') }}</h1>
<p class="lede">
    {{ __('Prepared for the Admissions Board, to decide which programmes to open this academic year.') }}
    &nbsp;|&nbsp; {{ __('Generated') }} {{ $report['generated_at']->format('d M Y H:i') }}
</p>

<table class="filters">
    <tr>
        @foreach($report['filters'] as $label => $value)
            <td><span class="muted">{{ $label }}</span><br><strong>{{ $value }}</strong></td>
        @endforeach
    </tr>
</table>

{{-- ============================ At a glance ============================ --}}
<h2>{{ __('At a glance') }}</h2>

<table class="tiles">
    <tr>
        <td><div class="tile-n">{{ $t['applications'] }}</div><div class="tile-l">{{ __('Applications') }}</div></td>
        <td><div class="tile-n">{{ $t['approved'] }}</div><div class="tile-l">{{ __('Approved') }}</div></td>
        <td><div class="tile-n">{{ $t['in_progress'] }}</div><div class="tile-l">{{ __('Awaiting approval') }}</div></td>
        <td><div class="tile-n">{{ $t['refused'] }}</div><div class="tile-l">{{ __('Refused') }}</div></td>
        @if($showDrafts)
            <td><div class="tile-n">{{ $t['drafts'] }}</div><div class="tile-l">{{ __('Unfinished drafts (not counted)') }}</div></td>
        @else
            <td><div class="tile-n">{{ $t['fee_paid'] }}</div><div class="tile-l">{{ __('Admission fee paid') }}</div></td>
        @endif
    </tr>
</table>
<p class="note">
    {{ __('Female') }} {{ $t['female'] }} &middot; {{ __('Male') }} {{ $t['male'] }} &middot; {{ __('Other or not recorded') }} {{ $t['gender_other'] }}
    &nbsp;|&nbsp; {{ __('Admission fee paid') }} {{ $t['fee_paid'] }}
    &nbsp;|&nbsp; {{ __('Gave a 2nd choice') }} {{ $t['with_second'] }} &middot; {{ __('a 3rd choice') }} {{ $t['with_third'] }}
    &nbsp;|&nbsp; {{ __(':with of :offered programmes have first-choice applicants', ['with' => $report['programme_counts']['with_first'], 'offered' => $report['programme_counts']['offered']]) }}
</p>

{{-- ======================= Which programmes have them ======================= --}}
<h2>{{ __('Which programmes have the applicants') }}</h2>
<p class="note">
    {{ __('Each programme is measured by its first choices — the applicants who would enrol — against a minimum class of :min. These are signals for the Board, not decisions.', ['min' => $min]) }}
</p>

<table class="groups" style="margin-top:4px">
    <tr>
        @foreach(\App\Services\ApplicationDemandReport::SIGNALS as $signal)
            <td>
                <div class="sig sig-{{ $signal }}">{{ $report['signal_labels'][$signal] }} ({{ count($report['signals'][$signal]) }})</div>
                <div class="note">{{ $report['signal_explanations'][$signal] }}</div>
                @if(empty($report['signals'][$signal]))
                    <div class="muted" style="margin-top:4px">{{ __('None') }}</div>
                @else
                    <ul>
                        @foreach($report['signals'][$signal] as $row)
                            @php
                                // Joined in PHP: whitespace between Blade conditionals
                                // printed as a stray space before each comma.
                                $counts = array_filter([
                                    $row['first'] . ' ' . __('1st'),
                                    $row['second'] ? $row['second'] . ' ' . __('2nd') : null,
                                    $row['third'] ? $row['third'] . ' ' . __('3rd') : null,
                                ]);
                            @endphp
                            <li>
                                {{ $row['title'] }}
                                @if($signal !== \App\Services\ApplicationDemandReport::NONE)
                                    <span class="muted">— {{ implode(', ', $counts) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </td>
        @endforeach
    </tr>
</table>

{{-- ============================== Faculties ============================== --}}
<h2>{{ __('Applicants by faculty') }}</h2>

<table class="grid">
    <thead>
        <tr>
            <th>{{ __('Faculty') }}</th>
            <th class="num">{{ __('Programmes') }}</th>
            <th class="num">{{ __('With 1st-choice applicants') }}</th>
            <th class="num">{{ __('1st choice') }}</th>
            <th class="num">{{ __('Share') }}</th>
            <th class="num">{{ __('Any choice') }}</th>
            <th class="num">{{ __('2nd') }}</th>
            <th class="num">{{ __('3rd') }}</th>
            <th class="num">{{ __('Approved') }}</th>
            <th class="num">{{ __('Awaiting') }}</th>
            <th class="num">{{ __('Refused') }}</th>
            <th class="num">{{ __('Fee paid') }}</th>
            <th>{{ __('Programme signals') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['faculties'] as $faculty)
            <tr>
                <td><strong>{{ $faculty['title'] }}</strong></td>
                <td class="num">{{ $faculty['programmes'] }}</td>
                <td class="num">{{ $faculty['programmes_with_first'] }}</td>
                <td class="num"><strong>{{ $faculty['first'] }}</strong></td>
                <td class="num">{{ $faculty['share'] }}%</td>
                <td class="num">{{ $faculty['any'] }}</td>
                <td class="num">{{ $faculty['second'] }}</td>
                <td class="num">{{ $faculty['third'] }}</td>
                <td class="num">{{ $faculty['approved'] }}</td>
                <td class="num">{{ $faculty['in_progress'] }}</td>
                <td class="num">{{ $faculty['refused'] }}</td>
                <td class="num">{{ $faculty['fee_paid'] }}</td>
                <td>
                    {{-- One per line. Side by side, and unable to wrap, they forced
                         the table wider than the page. --}}
                    @foreach(\App\Services\ApplicationDemandReport::SIGNALS as $signal)
                        @if($faculty['signals'][$signal])
                            <div class="sig sig-{{ $signal }}">{{ $faculty['signals'][$signal] }} {{ $report['signal_labels'][$signal] }}</div>
                        @endif
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="13" class="muted">{{ __('No programmes to report on.') }}</td></tr>
        @endforelse
    </tbody>
</table>
<p class="note">{{ __('"Any choice" counts each applicant once, however many of the faculty\'s programmes they chose.') }}</p>

{{-- ============================== Programmes ============================== --}}
<h2>{{ __('Demand for each programme') }}</h2>

@php
    $maxFirst = max(1, collect($report['programmes'])->max('first') ?: 1);
@endphp

<table class="grid">
    <thead>
        <tr>
            <th class="num">#</th>
            <th>{{ __('Programme') }}</th>
            <th>{{ __('Faculty') }}</th>
            <th style="width:70px">{{ __('1st choice') }}</th>
            <th class="num">{{ __('2nd') }}</th>
            <th class="num">{{ __('3rd') }}</th>
            <th class="num">{{ __('Any') }}</th>
            <th class="num">{{ __('Weighted') }}</th>
            <th class="num">{{ __('Approved') }}</th>
            <th class="num">{{ __('Awaiting') }}</th>
            <th class="num">{{ __('Refused') }}</th>
            <th class="num">{{ __('Fee paid') }}</th>
            <th class="num">{{ __('F / M') }}</th>
            @if($showDrafts)
                <th class="num">{{ __('Drafts') }}</th>
            @endif
            <th>{{ __('Most chosen 2nd') }}</th>
            <th>{{ __('Signal') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['programmes'] as $row)
            <tr>
                <td class="num">{{ $loop->iteration }}</td>
                <td><strong>{{ $row['title'] }}</strong></td>
                <td class="muted">{{ $row['faculty_code'] ?: $row['faculty'] }}</td>
                <td>
                    <strong>{{ $row['first'] }}</strong> <span class="muted">{{ $row['share'] }}%</span>
                    <div class="bar"><span style="width: {{ round($row['first'] / $maxFirst * 100) }}%"></span></div>
                </td>
                <td class="num">{{ $row['second'] }}</td>
                <td class="num">{{ $row['third'] }}</td>
                <td class="num">{{ $row['mentions'] }}</td>
                <td class="num">{{ $row['weighted'] }}</td>
                <td class="num">{{ $row['approved'] }}</td>
                <td class="num">{{ $row['in_progress'] }}</td>
                <td class="num">{{ $row['refused'] }}</td>
                <td class="num">{{ $row['fee_paid'] }}</td>
                <td class="num">{{ $row['female'] }} / {{ $row['male'] }}</td>
                @if($showDrafts)
                    <td class="num">{{ $row['drafts'] }}</td>
                @endif
                <td>
                    @if($row['top_second'])
                        {{ $row['top_second']['code'] }} <span class="muted">({{ $row['top_second']['count'] }})</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td><span class="sig sig-{{ $row['signal'] }}">{{ $report['signal_labels'][$row['signal']] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="16" class="muted">{{ __('No programmes to report on.') }}</td></tr>
        @endforelse
    </tbody>
</table>
<p class="note">
    {{ __('Ranked by first choices. Approved, Awaiting, Refused, Fee paid and F / M count first-choice applicants only. Weighted demand scores 3 per first choice, 2 per second and 1 per third, as a tie-breaker.') }}
    @if($showDrafts)
        {{ __('Drafts are forms begun but never submitted that name the programme first; they are not counted as applications.') }}
    @endif
</p>

{{-- =========================== If not opened =========================== --}}
@if(count($report['redirects']))
    <div class="page-break"></div>
    <h2>{{ __('If a programme is not opened') }}</h2>
    <p class="note">
        {{ __('For each programme without enough first-choice applicants: where the people who chose it first would go next, and whether that fallback has enough applicants itself.') }}
    </p>

    @foreach($report['redirects'] as $redirect)
        @php $p = $redirect['programme']; @endphp
        <table class="grid" style="margin-top:6px">
            <thead>
                <tr>
                    <th colspan="4" style="font-size:8px; text-transform:none; letter-spacing:0">
                        {{ $p['title'] }}
                        &nbsp;·&nbsp; {{ trans_choice(':count first-choice applicant|:count first-choice applicants', $p['first'], ['count' => $p['first']]) }}
                        &nbsp;·&nbsp; <span class="sig sig-{{ $p['signal'] }}">{{ $report['signal_labels'][$p['signal']] }}</span>
                        &nbsp;·&nbsp;
                        @if($redirect['stranded'])
                            <span class="no">{{ trans_choice(':count has no fallback with enough applicants|:count have no fallback with enough applicants', $redirect['stranded'], ['count' => $redirect['stranded']]) }}</span>
                        @else
                            <span class="yes">{{ __('Every applicant has a fallback with enough applicants') }}</span>
                        @endif
                    </th>
                </tr>
                <tr>
                    <th style="width:22%">{{ __('Applicant') }}</th>
                    <th style="width:32%">{{ __('2nd choice') }}</th>
                    <th style="width:32%">{{ __('3rd choice') }}</th>
                    <th style="width:14%">{{ __('Fallback with enough') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($redirect['applicants'] as $applicant)
                    <tr>
                        <td>{{ $applicant['name'] }}<br><span class="muted">{{ $applicant['registration_no'] }}</span></td>
                        <td>
                            @if($applicant['second'])
                                {{ $applicant['second'] }}
                                <span class="sig sig-{{ $applicant['second_signal'] }}">· {{ $report['signal_labels'][$applicant['second_signal']] ?? '' }}</span>
                            @else
                                <span class="muted">{{ __('None given') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($applicant['third'])
                                {{ $applicant['third'] }}
                                <span class="sig sig-{{ $applicant['third_signal'] }}">· {{ $report['signal_labels'][$applicant['third_signal']] ?? '' }}</span>
                            @else
                                <span class="muted">{{ __('None given') }}</span>
                            @endif
                        </td>
                        <td class="center">
                            @if($applicant['has_viable_fallback'])
                                <span class="yes">{{ __('Yes') }}</span>
                            @else
                                <span class="no">{{ __('No') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endif

{{-- ============================ Choice flow ============================ --}}
@if(count($report['matrix']['rows']) && count($report['matrix']['columns']) && count($report['matrix']['columns']) <= 16)
    <h2>{{ __('First choice against second choice') }}</h2>
    <p class="note">{{ __('Read across: of the applicants who chose a programme first, how many chose each other programme second.') }}</p>

    <table class="grid matrix" style="margin-top:4px">
        <thead>
            <tr>
                <th class="label">{{ __('1st choice ↓   2nd choice →') }}</th>
                @foreach($report['matrix']['columns'] as $column)
                    <th>{{ $column['code'] }}</th>
                @endforeach
                <th>{{ __('None') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['matrix']['rows'] as $matrixRow)
                <tr>
                    <td class="label"><strong>{{ $matrixRow['code'] }}</strong> <span class="muted">({{ $matrixRow['first'] }})</span></td>
                    @foreach($report['matrix']['columns'] as $column)
                        @php $n = $report['matrix']['cells'][$matrixRow['id']][$column['id']] ?? 0; @endphp
                        <td class="{{ $n ? '' : 'zero' }}">{{ $n ?: '·' }}</td>
                    @endforeach
                    @php $none = $report['matrix']['cells'][$matrixRow['id']]['none'] ?? 0; @endphp
                    <td class="{{ $none ? '' : 'zero' }}">{{ $none ?: '·' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ======================= Programme codes & trend ======================= --}}
<table style="margin-top:12px">
    <tr>
        <td style="width:62%; vertical-align:top; padding-right:10px">
            <h2 style="margin-top:0">{{ __('Programme codes') }}</h2>
            <table class="grid">
                @php $legend = collect($report['legend']); $half = (int) ceil($legend->count() / 2); $left = $legend->slice(0, $half); $right = $legend->slice($half); @endphp
                @foreach($left as $legendCode => $legendTitle)
                    @php $rightCode = $right->keys()->get($loop->index); @endphp
                    <tr>
                        <td style="width:14%"><strong>{{ $legendCode }}</strong></td>
                        <td style="width:36%">{{ $legendTitle }}</td>
                        <td style="width:14%"><strong>{{ $rightCode }}</strong></td>
                        <td style="width:36%">{{ $rightCode ? $right[$rightCode] : '' }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
        <td style="width:38%; vertical-align:top">
            <h2 style="margin-top:0">{{ __('Applications by month') }}</h2>
            @php $maxMonth = max(1, collect($report['months'])->max('count') ?: 1); @endphp
            <table class="grid">
                @forelse($report['months'] as $month)
                    <tr>
                        <td style="width:28%">{{ $month['month'] }}</td>
                        <td style="width:12%" class="num">{{ $month['count'] }}</td>
                        <td><div class="bar" style="margin-top:3px"><span style="width: {{ round($month['count'] / $maxMonth * 100) }}%"></span></div></td>
                    </tr>
                @empty
                    <tr><td class="muted">{{ __('No applications.') }}</td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

{{-- ============================== Register ============================== --}}
<div class="page-break"></div>
<h2>{{ __('Register of applications') }}</h2>
<p class="note">{{ __(':count applications, grouped by first choice. Programmes are shown by code; the codes are listed above.', ['count' => count($report['register'])]) }}</p>

<table class="grid" style="margin-top:4px">
    <thead>
        <tr>
            <th>{{ __('Reg. no') }}</th>
            <th>{{ __('Applicant') }}</th>
            <th>{{ __('Gender') }}</th>
            <th>{{ __('1st choice') }}</th>
            <th>{{ __('2nd choice') }}</th>
            <th>{{ __('3rd choice') }}</th>
            <th>{{ __('Approval') }}</th>
            <th>{{ __('Admission fee') }}</th>
            <th>{{ __('Applied') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['register'] as $entry)
            <tr>
                <td>{{ $entry['registration_no'] }}</td>
                <td>{{ $entry['name'] }}</td>
                <td>{{ $entry['gender'] }}</td>
                <td><strong>{{ $entry['first_code'] ?? '—' }}</strong></td>
                <td>{{ $entry['second_code'] ?? '—' }}</td>
                <td>{{ $entry['third_code'] ?? '—' }}</td>
                <td>{{ $entry['approval'] }}</td>
                <td>{{ $entry['fee'] }}</td>
                <td class="num">{{ $entry['applied'] }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">{{ __('No applications match these filters.') }}</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ============================== Method ============================== --}}
<h2>{{ __('How this report counts') }}</h2>
<p class="note">
    {{ __('Applications are those matching the filters shown at the top, exactly as the applications list shows them.') }}
    {{ __('A programme\'s demand is its first choices, because those are the applicants who would enrol; second and third choices show where applicants would go if their first choice is not opened.') }}
    {{ __('"Reachable with 2nd choices" counts every second-choice applicant as though they would come, so it is the most a programme could reach, not what it will.') }}
    {{ __('Approval figures follow each application through the approval chain as it stands at the time this report was generated.') }}
</p>

<table class="sign">
    <tr>
        <td>{{ __('Chair, Admissions Board') }}</td>
        <td class="gap"></td>
        <td>{{ __('Registrar') }}</td>
        <td class="gap"></td>
        <td>{{ __('Date') }}</td>
    </tr>
</table>

</body>
</html>
