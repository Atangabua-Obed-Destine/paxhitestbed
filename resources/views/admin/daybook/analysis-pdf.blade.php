<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Financial analysis') }} — {{ $budget->title }}</title>
    {!! $letterheadStyles !!}
    <style>
        @page { margin: 14mm 12mm 14mm 12mm; }

        /*
            body, not *.
            The universal selector applies a font DIRECTLY to every element,
            including <strong>, and a direct rule beats an inherited one — so
            bold text came out in a different typeface rather than a heavier
            one. Set here, it is inherited, which is what was wanted.
        */
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }

        h1 { font-size: 14px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: .04em; }
        h2 {
            font-size: 11px; margin: 14px 0 4px; color: #182b49;
            border-bottom: .8px solid #c3c2b7; padding-bottom: 3px;
        }
        .period { font-size: 9px; color: #444; margin: 0 0 10px; }
        .sub { font-size: 8.5px; color: #444; margin: 0 0 6px; line-height: 1.5; }

        table.g { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        /* Top-aligned, because row heights vary wildly once a cell carries a
           sentence: dompdf centres by default, which leaves a one-word cell
           floating halfway down a four-line row and makes the table read as
           though the columns had come apart. */
        table.g th, table.g td { border: .5px solid #999; padding: 2.5px 4px; vertical-align: top; }
        table.g th { background: #e8ebef; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
        .num { text-align: right; white-space: nowrap; }
        .total td { border-top: 1.2px solid #333; font-weight: bold; background: #f2f4f7; }

        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        /* The tiles, as a strip of cells — dompdf lays a table out reliably
           where it will not lay out flexbox at all. */
        table.tiles { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 6px; }
        table.tiles td { border: .5px solid #c9d2de; background: #fbfcfd; padding: 6px 7px; width: 25%; }
        table.tiles .lbl { font-size: 7px; text-transform: uppercase; letter-spacing: .06em; color: #70809a; }
        table.tiles .val { font-size: 13px; font-weight: bold; }
        table.tiles .note { font-size: 7.5px; color: #555; }

        .chart { width: 100%; margin: 4px 0 8px; }
        .chart img { width: 100%; }

        /* Can these figures be presented? Kept at the front of the pack for
           the same reason it is at the top of the screen. */
        table.conf { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.conf td { border: .5px solid #999; padding: 3px 5px; font-size: 8.5px; vertical-align: top; }
        .st { font-weight: bold; white-space: nowrap; }
        .st-good { color: #056b05; }
        .st-warning { color: #7a5312; }
        .st-critical { color: #8a1f1f; }

        .up { color: #1c5fa8; font-weight: bold; }
        .down { color: #b4491f; font-weight: bold; }
        .none { color: #8a1f1f; font-weight: bold; }

        .foot { margin-top: 12px; font-size: 7.5px; color: #444; }
        .sign { margin-top: 20px; width: 100%; }
        .sign td { padding-top: 16px; border-top: .5px solid #333; font-size: 8px; width: 33%; }
        .brk { page-break-before: always; }
    </style>
</head>
<body>

{!! $letterhead !!}

@php
    $a = $analysis;
    $cash = $a['cash_position'];
    $closing = count($cash) ? end($cash)['balance'] : 0;
    $lowest = count($cash) ? min(array_column($cash, 'balance')) : 0;
    // Charts arrive in the order the page registered them: cash, in/out, net,
    // pacing, fee collection. Missing ones are simply absent — the tables
    // carry the figures.
    //
    // These are POSITIONS, not names, so the order is a contract between this
    // file and the registration order in analysis.blade.php. A chart inserted
    // ahead of the others there would print the wrong picture under four
    // headings here, silently. New charts go on the end;
    // scripts/fee_collection_test.php asserts that 0-3 are still the original
    // four.
    $chart = fn ($i) => $charts[$i] ?? null;
@endphp

<h1>{{ __('Financial analysis') }}</h1>
<p class="period">
    <strong>{{ $budget->title }}</strong>
    &nbsp;·&nbsp; {{ optional($budget->start_date)->format('j M Y') }}
    {{ __('to') }} {{ optional($budget->end_date)->format('j M Y') }}
    &nbsp;·&nbsp; {{ __('prepared') }} {{ $generatedAt->format('j M Y, H:i') }}
</p>

<h2>{{ __('Before these figures are presented') }}</h2>
<table class="conf">
    @foreach($checks as $c)
        <tr>
            <td class="st st-{{ $c['state'] }}" style="width:14%">
                {{ $c['state'] === 'good' ? __('OK') : ($c['state'] === 'warning' ? __('Check') : __('Action')) }}
            </td>
            <td style="width:24%"><strong>{{ $c['label'] }}</strong></td>
            <td class="num" style="width:16%">{{ $c['figure'] }}</td>
            <td>
                {{ $c['detail'] }}
                @if(!empty($c['action'])) <em>{{ $c['action'] }}</em> @endif
            </td>
        </tr>
    @endforeach
</table>

@if($a['has_data'])

<h2>{{ __('The year so far') }}</h2>
<table class="tiles">
    <tr>
        <td>
            <div class="lbl">{{ __('Money in') }}</div>
            <div class="val">{{ number_format($a['totals']['in']) }}</div>
            <div class="note">{{ __('Received') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('Money out') }}</div>
            <div class="val">{{ number_format($a['totals']['out']) }}</div>
            <div class="note">{{ __('Spent') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('Closing cash position') }}</div>
            <div class="val">{{ number_format($closing) }}</div>
            <div class="note">
                @if($lowest < 0)
                    {{ __('Lowest point :amount', ['amount' => number_format($lowest)]) }}
                @else
                    {{ __('Never went below zero') }}
                @endif
            </div>
        </td>
        <td>
            <div class="lbl">{{ __('Spending pace') }}</div>
            <div class="val">
                {{ in_array($a['pacing']['status'], ['none', 'incomplete'], true)
                    ? '—'
                    : number_format($a['pacing']['consumed'], 0) . '%' }}
            </div>
            <div class="note">
                @if($a['pacing']['status'] === 'none')
                    {{ __('No budget set') }}
                @elseif($a['pacing']['status'] === 'incomplete')
                    {{ __('Budget unfinished') }}
                @else
                    {{ __(':pct% of the year gone', ['pct' => number_format($a['pacing']['elapsed'], 0)]) }}
                @endif
            </div>
        </td>
    </tr>
</table>

<h2>{{ __('Cash position, month by month') }}</h2>
@if($chart(0))
    <div class="chart"><img src="{{ $chart(0) }}" alt="{{ __('Cash position') }}"></div>
@endif
<table class="g">
    <thead><tr>
        <th>{{ __('Month') }}</th><th class="num">{{ __('In') }}</th>
        <th class="num">{{ __('Out') }}</th><th class="num">{{ __('Net') }}</th>
        <th class="num">{{ __('Balance at month end') }}</th>
    </tr></thead>
    <tbody>
    @foreach($cash as $i => $p)
        <tr>
            <td>{{ $a['months'][$i]['period']->name ?? $p['label'] }}</td>
            <td class="num">{{ number_format($a['months'][$i]['in'] ?? 0) }}</td>
            <td class="num">{{ number_format($a['months'][$i]['out'] ?? 0) }}</td>
            <td class="num {{ $p['net'] >= 0 ? 'up' : 'down' }}">{{ number_format($p['net']) }}</td>
            <td class="num">{{ number_format($p['balance']) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td>{{ __('Total') }}</td>
        <td class="num">{{ number_format($a['totals']['in']) }}</td>
        <td class="num">{{ number_format($a['totals']['out']) }}</td>
        <td class="num">{{ number_format($a['totals']['net']) }}</td>
        <td class="num">{{ number_format($closing) }}</td>
    </tr>
    </tbody>
</table>

<div class="brk"></div>

<h2>{{ __('Money in and money out') }}</h2>
@if($chart(1))
    <div class="chart"><img src="{{ $chart(1) }}" alt="{{ __('Money in and out by month') }}"></div>
@endif

<h2>{{ __('Surplus and deficit by month') }}</h2>
@if($chart(2))
    <div class="chart"><img src="{{ $chart(2) }}" alt="{{ __('Surplus and deficit') }}"></div>
@endif

<h2>{{ __('Spending against the year') }}</h2>
@if($chart(3))
    <div class="chart"><img src="{{ $chart(3) }}" alt="{{ __('Pacing') }}"></div>
@endif

<div class="brk"></div>

@if(count($a['top_income']))
    <h2>{{ __('Where the money comes from') }}</h2>
    <p class="sub">
        {{ __('The top :n income lines account for :pct% of everything received.', [
            'n' => $a['concentration']['income']['count'],
            'pct' => number_format($a['concentration']['income']['share'], 0),
        ]) }}
    </p>
    <table class="g">
        <thead><tr>
            <th style="width:60px">{{ __('Code') }}</th><th>{{ __('Line') }}</th>
            <th class="num" style="width:60px">{{ __('Share') }}</th>
            <th class="num" style="width:110px">{{ __('Amount') }}</th>
        </tr></thead>
        <tbody>
        @foreach($a['top_income'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ number_format($row['share'], 0) }}%</td>
                <td class="num">{{ number_format($row['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(count($a['top_expenditure']))
    <h2>{{ __('Where the money goes') }}</h2>
    <p class="sub">
        {{ __('The top :n expenditure lines account for :pct% of everything spent.', [
            'n' => $a['concentration']['expenditure']['count'],
            'pct' => number_format($a['concentration']['expenditure']['share'], 0),
        ]) }}
    </p>
    <table class="g">
        <thead><tr>
            <th style="width:60px">{{ __('Code') }}</th><th>{{ __('Line') }}</th>
            <th class="num" style="width:60px">{{ __('Share') }}</th>
            <th class="num" style="width:110px">{{ __('Amount') }}</th>
        </tr></thead>
        <tbody>
        @foreach($a['top_expenditure'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ number_format($row['share'], 0) }}%</td>
                <td class="num">{{ number_format($row['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($a['movers']['rows']))
    <h2>{{ __('What changed, :from to :to', ['from' => $a['movers']['from'], 'to' => $a['movers']['to']]) }}</h2>
    <table class="g">
        <thead><tr>
            <th>{{ __('Line') }}</th>
            <th class="num" style="width:95px">{{ $a['movers']['from'] }}</th>
            <th class="num" style="width:95px">{{ $a['movers']['to'] }}</th>
            <th class="num" style="width:95px">{{ __('Change') }}</th>
        </tr></thead>
        <tbody>
        @foreach($a['movers']['rows'] as $row)
            <tr>
                <td>{{ $row['line'] }}</td>
                <td class="num">{{ number_format($row['previous']) }}</td>
                <td class="num">{{ number_format($row['current']) }}</td>
                <td class="num {{ $row['direction'] === 'up' ? 'up' : 'down' }}">
                    {{ $row['direction'] === 'up' ? '+' : '−' }}{{ number_format(abs($row['change'])) }}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(count($a['largest_movements']))
    <h2>{{ __('The largest single transactions') }}</h2>
    <table class="g">
        <thead><tr>
            <th style="width:62px">{{ __('Date') }}</th>
            <th style="width:62px">{{ __('Ref') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Line') }}</th>
            <th class="num" style="width:95px">{{ __('Amount') }}</th>
        </tr></thead>
        <tbody>
        @foreach($a['largest_movements'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['ref'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="{{ $row['line'] ? '' : 'none' }}">{{ $row['line'] ?: __('no budget line') }}</td>
                <td class="num {{ $row['direction'] === 'in' ? 'up' : 'down' }}">{{ number_format($row['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($a['unallocated']['count'] > 0)
    <h2>{{ __('Money on no budget line') }}</h2>
    <p class="sub">
        <strong>{{ number_format($a['unallocated']['amount']) }}</strong>
        {{ __('across :n transaction(s), :pct% of everything that moved. Counted in the book, absent from every line of the Income & Expenditure sheet.', [
            'n' => $a['unallocated']['count'],
            'pct' => number_format($a['unallocated']['share'], 1),
        ]) }}
    </p>
    <table class="g">
        <thead><tr>
            <th style="width:62px">{{ __('Date') }}</th>
            <th style="width:62px">{{ __('Ref') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num" style="width:95px">{{ __('Amount') }}</th>
        </tr></thead>
        <tbody>
        @foreach($a['unallocated']['rows'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['ref'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="num">{{ number_format($row['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@else
    <p class="sub">{{ __('Nothing has been recorded against this budget, so there is nothing to analyse.') }}</p>
@endif

{{-- Who has paid -------------------------------------------------------------
     Outside the has_data branch, as on the screen: a year whose fees are
     assigned and unpaid produces no cash rows at all, and is exactly the year
     a meeting needs this page for.
--}}
@php $cl = $collection; $cst = $cl['students']; $cap = $cl['applicants']; @endphp

@if($cst['total'] > 0 || $cap['total'] > 0)
<h2 class="brk">{{ __('Who has paid, and who has not') }}</h2>
<p class="sub">
    {{ __('Students with fees assigned in this budget year, counted as people rather than as fees: a student with three instalments and two paid is one student who is partly paid. Applicants are counted apart because they are not yet students, and pooling the two would overstate collection.') }}
</p>

<table class="tiles">
    <tr>
        <td>
            <div class="lbl">{{ __('Paid in full') }}</div>
            <div class="val st-good">{{ number_format($cst['full']) }}</div>
            <div class="note">{{ __('of :total students', ['total' => number_format($cst['total'])]) }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('Paid in part') }}</div>
            <div class="val st-warning">{{ number_format($cst['part']) }}</div>
            <div class="note">{{ __('a balance left') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('Paid nothing') }}</div>
            <div class="val st-critical">{{ number_format($cst['none']) }}</div>
            <div class="note">{{ __('nothing received') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('Collection rate') }}</div>
            <div class="val">{{ number_format($cst['rate'], 1) }}%</div>
            <div class="note">{{ __('of what students were billed') }}</div>
        </td>
    </tr>
</table>

<table class="g">
    <thead><tr>
        <th>{{ __('Cohort') }}</th>
        <th class="num" style="width:50px">{{ __('People') }}</th>
        <th class="num" style="width:42px">{{ __('Full') }}</th>
        <th class="num" style="width:42px">{{ __('Part') }}</th>
        <th class="num" style="width:48px">{{ __('Nothing') }}</th>
        <th class="num" style="width:85px">{{ __('Billed') }}</th>
        <th class="num" style="width:85px">{{ __('Collected') }}</th>
        <th class="num" style="width:85px">{{ __('Outstanding') }}</th>
    </tr></thead>
    <tbody>
        @foreach([__('Students') => $cst, __('Applicants') => $cap] as $label => $row)
        <tr>
            <td>{{ $label }}</td>
            <td class="num">{{ number_format($row['total']) }}</td>
            <td class="num">{{ number_format($row['full']) }}</td>
            <td class="num">{{ number_format($row['part']) }}</td>
            <td class="num">{{ number_format($row['none']) }}</td>
            <td class="num">{{ number_format($row['billed']) }}</td>
            <td class="num">{{ number_format($row['collected']) }}</td>
            <td class="num">{{ number_format($row['outstanding']) }}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>{{ __('Together') }}</td>
            <td class="num">{{ number_format($cl['combined']['total']) }}</td>
            <td class="num">{{ number_format($cl['combined']['full']) }}</td>
            <td class="num">{{ number_format($cl['combined']['part']) }}</td>
            <td class="num">{{ number_format($cl['combined']['none']) }}</td>
            <td class="num">{{ number_format($cl['combined']['billed']) }}</td>
            <td class="num">{{ number_format($cl['combined']['collected']) }}</td>
            <td class="num">{{ number_format($cl['combined']['outstanding']) }}</td>
        </tr>
    </tbody>
</table>

@if($cl['arrears']['people'] > 0)
<p class="sub">
    <strong class="st-warning">{{ __('Owed from earlier years: :amount', ['amount' => number_format($cl['arrears']['amount'])]) }}</strong>
    {{ __('across :n people, oldest assigned :date. Not in the table above, which covers this year\'s billing only.', [
        'n' => $cl['arrears']['people'],
        'date' => $cl['arrears']['oldest'] ? \Carbon\Carbon::parse($cl['arrears']['oldest'])->format('j M Y') : '—',
    ]) }}
</p>
@endif

@if(count($cl['by_month']))
<h2>{{ __('Fee collection, month by month') }}</h2>
<p class="sub">{{ __('All fee cash received in this budget year — a wider figure than Collected above, because it includes payments against earlier years\' fees. It is what ties to the money-in figures earlier in this pack.') }}</p>
{{-- Index 4: this chart is registered on the screen AFTER the original four,
     so the indices above keep their pictures. --}}
@if($chart(4))
    <div class="chart"><img src="{{ $chart(4) }}" alt=""></div>
@endif
<table class="g">
    <thead><tr>
        <th>{{ __('Month') }}</th>
        <th class="num" style="width:110px">{{ __('Received') }}</th>
        <th class="num" style="width:110px">{{ __('Running total') }}</th>
    </tr></thead>
    <tbody>
    @foreach($cl['by_month'] as $m)
        <tr>
            <td>{{ $m['label'] }}</td>
            <td class="num">{{ number_format($m['cash']) }}</td>
            <td class="num">{{ number_format($m['cumulative']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if(count($cl['by_programme']))
<h2>{{ __('Collection by programme') }}</h2>
<p class="sub">{{ __('Worst collection rate first. Rate is money collected against money billed, so a small programme can show a poor rate over very little money — read it with the outstanding column.') }}</p>
<table class="g">
    <thead><tr>
        <th>{{ trans_choice('module_program', 1) }}</th>
        <th class="num" style="width:45px">{{ __('People') }}</th>
        <th class="num" style="width:85px">{{ __('Billed') }}</th>
        <th class="num" style="width:85px">{{ __('Collected') }}</th>
        <th class="num" style="width:85px">{{ __('Outstanding') }}</th>
        <th class="num" style="width:45px">{{ __('Rate') }}</th>
    </tr></thead>
    <tbody>
    @foreach($cl['by_programme'] as $row)
        <tr>
            <td>{{ $row['programme'] }}@if($row['faculty'])<br><span style="font-size:7.5px;color:#666">{{ $row['faculty'] }}</span>@endif</td>
            <td class="num">{{ number_format($row['people']) }}</td>
            <td class="num">{{ number_format($row['due']) }}</td>
            <td class="num">{{ number_format($row['collected']) }}</td>
            <td class="num">{{ number_format($row['owed']) }}</td>
            <td class="num">{{ number_format($row['rate'], 1) }}%</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if(count($cl['by_category']))
<h2>{{ __('Collection by fee') }}</h2>
<p class="sub">{{ __('Counted in fees, not people. Compare each rate against how far through the year that fee falls due.') }}</p>
<table class="g">
    <thead><tr>
        <th>{{ __('field_fees_category') }}</th>
        <th class="num" style="width:45px">{{ __('Fees') }}</th>
        <th class="num" style="width:90px">{{ __('Billed') }}</th>
        <th class="num" style="width:90px">{{ __('Collected') }}</th>
        <th class="num" style="width:90px">{{ __('Outstanding') }}</th>
        <th class="num" style="width:45px">{{ __('Rate') }}</th>
    </tr></thead>
    <tbody>
    @foreach($cl['by_category'] as $row)
        <tr>
            <td>{{ $row['category'] }}</td>
            <td class="num">{{ number_format($row['fees']) }}</td>
            <td class="num">{{ number_format($row['due']) }}</td>
            <td class="num">{{ number_format($row['collected']) }}</td>
            <td class="num">{{ number_format($row['owed']) }}</td>
            <td class="num">{{ number_format($row['rate'], 1) }}%</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if(count($cl['top_debtors']))
<h2>{{ __('The largest outstanding balances') }}</h2>
<p class="sub">{{ __('Every fee assigned to this person this year, totalled. An applicant is marked as one: an unpaid admission fee there is an admission that has not completed, not a student in arrears.') }}</p>
<table class="g">
    <thead><tr>
        <th>{{ __('field_student_name') }}</th>
        <th style="width:70px">{{ __('Reference') }}</th>
        <th>{{ trans_choice('module_program', 1) }}</th>
        <th class="num" style="width:80px">{{ __('Billed') }}</th>
        <th class="num" style="width:80px">{{ __('Paid') }}</th>
        <th class="num" style="width:80px">{{ __('Outstanding') }}</th>
    </tr></thead>
    <tbody>
    @foreach($cl['top_debtors'] as $row)
        <tr>
            <td>{{ $row['name'] }}@if($row['kind'] === 'applicant') <span style="font-size:7.5px;color:#666">({{ __('Applicant') }})</span>@endif</td>
            <td>{{ $row['reference'] }}</td>
            <td>{{ $row['programme'] ?: '—' }}</td>
            <td class="num">{{ number_format($row['due']) }}</td>
            <td class="num">{{ number_format($row['paid']) }}</td>
            <td class="num">{{ number_format($row['owed']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if($cl['unattributed']['count'] > 0)
<h2>{{ __('Fees that belong to nobody') }}</h2>
<p class="sub">
    {{ __('These cannot be attributed to a student or an applicant, so they are in none of the figures above — :amount of it still outstanding. Shown here rather than dropped: a fee with no person cannot be chased, receipted, or put on anybody\'s statement.', [
        'amount' => number_format($cl['unattributed']['owed']),
    ]) }}
</p>
<table class="g">
    <thead><tr>
        <th style="width:45px">{{ __('Fee') }}</th>
        <th>{{ __('field_fees_category') }}</th>
        <th style="width:62px">{{ __('field_assign_date') }}</th>
        <th class="num" style="width:80px">{{ __('Billed') }}</th>
        <th class="num" style="width:80px">{{ __('Outstanding') }}</th>
        <th>{{ __('Why') }}</th>
    </tr></thead>
    <tbody>
    @foreach($cl['unattributed']['rows'] as $row)
        <tr>
            <td>#{{ $row['id'] }}</td>
            <td>{{ $row['category'] }}</td>
            <td>{{ $row['assign_date'] ? \Carbon\Carbon::parse($row['assign_date'])->format('j M Y') : '—' }}</td>
            <td class="num">{{ number_format($row['due']) }}</td>
            <td class="num">{{ number_format($row['owed']) }}</td>
            <td>
                @if($row['reason'] === 'missing_enrolment')
                    {{ __('Its enrolment (:id) no longer exists.', ['id' => $row['enroll_id']]) }}
                @else
                    {{ __('Its enrolment carries neither a student nor an applicant.') }}
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
@endif

<p class="foot">
    {{ __('Prepared from the Daybook. Every figure here is the same money as the book and the Income & Expenditure sheet, read a different way.') }}
    @if(!count($charts))
        <br>{{ __('Charts could not be captured for this copy; the tables carry the same figures.') }}
    @endif
</p>

<table class="sign">
    <tr>
        <td>{{ __('Prepared by') }}</td>
        <td>{{ __('Checked by') }}</td>
        <td>{{ __('Approved by') }}</td>
    </tr>
</table>

</body>
</html>
