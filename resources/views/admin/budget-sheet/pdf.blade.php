<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Income & Expenditure') }} — {{ $budget->title }}</title>
    {!! $letterheadStyles !!}
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }

        h1 { font-size: 13px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: .04em; }
        .period { font-size: 9px; color: #444; margin: 0 0 10px; }

        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th, table.sheet td { border: .5px solid #999; padding: 2.5px 4px; }
        table.sheet th { background: #e8ebef; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
        .num { text-align: right; white-space: nowrap; }
        .code { width: 34px; color: #555; }
        .section td { background: #34495e; color: #fff; font-weight: bold; text-transform: uppercase; font-size: 8px; }
        .header td { background: #eef1f6; font-weight: bold; }
        .child .name { padding-left: 10px; }
        .total td { border-top: 1.2px solid #333; font-weight: bold; }
        /* Lighter rule than a section total: a subtotal closes a group, not a
           half of the sheet. */
        .subtotal td { border-top: 0.5px solid #999; border-bottom: 0.5px solid #999; font-weight: bold; background: #f7f9fb; }
        .subtotal .name { padding-left: 10px; font-style: italic; }
        .prior { color: #555; }

        /* Repeat the column headings on every page — a budget runs to several
           pages and a bare column of figures is unreadable without them. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .foot { margin-top: 14px; font-size: 8px; color: #444; }
        .sign { margin-top: 26px; width: 100%; }
        .sign td { padding-top: 20px; font-size: 8.5px; }
        .sign .rule { border-bottom: .5px solid #333; width: 78%; }
    </style>
</head>
<body>

{!! $letterhead !!}

<h1>{{ __('Income and Expenditure Annual Budget Sheet') }}</h1>
<p class="period">
    {{ $budget->title }} &nbsp;·&nbsp;
    {{ optional($budget->start_date)->format('d M Y') }} &ndash; {{ optional($budget->end_date)->format('d M Y') }}
    &nbsp;·&nbsp; {{ __('Status') }}: {{ ucfirst(str_replace('_', ' ', $budget->status)) }}
</p>

@php
    $money = fn ($v) => $v ? number_format($v) : '—';
    $currentSection = null;
@endphp

<table class="sheet">
    <thead>
        <tr>
            <th class="code">{{ __('Code') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ $previous ? $previous->title : __('Last year') }}</th>
            <th class="num">{{ __('Budget') }}</th>
            <th class="num">{{ __('Actual') }}</th>
            <th class="num">{{ __('Variance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr class="total">
            <td></td>
            <td>{{ __('OPENING BALANCE') }}</td>
            <td class="num prior">{{ $previous ? $money($priorTotals['opening']) : '—' }}</td>
            <td class="num">{{ $money($budget->opening_balance) }}</td>
            <td class="num">{{ $money($totals['actual']['opening']) }}</td>
            <td class="num">—</td>
        </tr>

        @foreach($lines as $line)
            @if($line->section !== $currentSection)
                @php $currentSection = $line->section; @endphp
                <tr class="section"><td colspan="6">{{ __(ucfirst($currentSection)) }}</td></tr>
            @endif

            @php
                $b = $budgeted[$line->id] ?? 0;
                $a = $actual[$line->id] ?? 0;
                $v = $a - $b;
                $p = $priorActual[$line->id] ?? 0;
            @endphp

            <tr class="{{ $line->is_header ? 'header' : 'child' }}">
                <td class="code">{{ $line->code }}</td>
                <td class="name">{{ $line->name }}</td>
                <td class="num prior">{{ $money($p) }}</td>
                <td class="num">{{ $money($b) }}</td>
                <td class="num">{{ $money($a) }}</td>
                <td class="num">{{ $b ? number_format($v) : '—' }}</td>
            </tr>

            {{-- The group's own total, ruled off at its foot. On paper this is
                 where the reader's eye stops, and it is what the diocesan form
                 shows, so the signed document must carry it too. --}}
            @if(isset($groupEnds[$line->id]))
                @php
                    $gh = $groupEnds[$line->id];
                    $gb = $budgeted[$gh->id] ?? 0;
                    $ga = $actual[$gh->id] ?? 0;
                    $gp = $priorActual[$gh->id] ?? 0;
                    $gv = $ga - $gb;
                @endphp
                <tr class="subtotal">
                    <td></td>
                    <td class="name">{{ __('Total') }} {{ $gh->name }}</td>
                    <td class="num prior">{{ $money($gp) }}</td>
                    <td class="num">{{ $money($gb) }}</td>
                    <td class="num">{{ $money($ga) }}</td>
                    <td class="num">{{ $gb ? number_format($gv) : '—' }}</td>
                </tr>
            @endif
        @endforeach

        <tr class="total">
            <td></td>
            <td>{{ __('TOTAL INCOME') }}</td>
            <td class="num prior">{{ $previous ? $money($priorTotals['income']) : '—' }}</td>
            <td class="num">{{ $money($totals['budget']['income']) }}</td>
            <td class="num">{{ $money($totals['actual']['income']) }}</td>
            <td class="num">{{ number_format($totals['actual']['income'] - $totals['budget']['income']) }}</td>
        </tr>
        <tr class="total">
            <td></td>
            <td>{{ __('TOTAL EXPENDITURE') }}</td>
            <td class="num prior">{{ $previous ? $money($priorTotals['expenditure']) : '—' }}</td>
            <td class="num">{{ $money($totals['budget']['expenditure']) }}</td>
            <td class="num">{{ $money($totals['actual']['expenditure']) }}</td>
            <td class="num">{{ number_format($totals['actual']['expenditure'] - $totals['budget']['expenditure']) }}</td>
        </tr>
        <tr class="total">
            <td></td>
            <td>{{ __('CLOSING BALANCE') }}</td>
            <td class="num prior">{{ $previous ? $money($priorTotals['closing']) : '—' }}</td>
            <td class="num">{{ $money($totals['budget']['closing']) }}</td>
            <td class="num">{{ $money($totals['actual']['closing']) }}</td>
            <td class="num">—</td>
        </tr>
        <tr class="total">
            <td></td>
            <td>{{ __('CAPITAL EXPENSES') }}</td>
            <td class="num prior">{{ $previous ? $money($priorTotals['capital']) : '—' }}</td>
            <td class="num">{{ $money($totals['budget']['capital']) }}</td>
            <td class="num">{{ $money($totals['actual']['capital']) }}</td>
            <td class="num">{{ number_format($totals['actual']['capital'] - $totals['budget']['capital']) }}</td>
        </tr>
    </tbody>
</table>

{{-- The closing balance above follows the paper form, which does not deduct
     capital from it. Stating the cash position separately stops the closing
     balance being read as money in the bank. --}}
<div class="foot">
    <strong>{{ __('Cash position') }}:</strong>
    {{ __('Closing balance') }} {{ $money($totals['actual']['closing']) }}
    + {{ __('depreciation (does not leave the bank)') }} {{ $money($totals['actual']['depreciation']) }}
    − {{ __('capital spend') }} {{ $money($totals['actual']['capital']) }}
    = <strong>{{ $money($totals['actual']['cash']) }}</strong>

    @if(!empty($reconciliation['agrees']))
        &nbsp;·&nbsp; {{ __('Agreed with the general ledger.') }}
        @if(!empty($reconciliation['known_differences']))
            {{ __('Except a known difference awaiting a ledger correction:') }}
            @foreach($reconciliation['known_differences'] as $known)
                {{ $known['detail'] }}
            @endforeach
        @endif
    @else
        &nbsp;·&nbsp; <strong>{{ __('NOT yet agreed with the general ledger.') }}</strong>
    @endif
</div>

<table class="sign">
    <tr>
        <td width="50%"><div class="rule"></div>{{ __('Prepared by (Bursar)') }}</td>
        <td width="50%"><div class="rule"></div>{{ __('Approved by') }}</td>
    </tr>
</table>

</body>
</html>
