{{-- The fees report as a document.

     Same figures as the workbook, from the same array (App\Services\FeeReport)
     and the same filters (App\Support\FeeReportFilter) as the screen, so the
     three cannot disagree.

     Laid out for dompdf, which means tables rather than flex or grid, no
     remote assets, and `thead { display: table-header-group }` so the register
     repeats its headings on every page it runs onto.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Fees Report') }}</title>
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
        table.filters td.k { background: #f2f4f7; width: 15%; font-weight: bold; }

        table.tiles td { border: .5px solid #b9c0ca; padding: 6px 8px; width: 20%; }
        .tile-n { font-size: 15px; font-weight: bold; }
        .tile-l { font-size: 7px; text-transform: uppercase; letter-spacing: .05em; color: #4a5563; }
        .tile-c { font-size: 8px; font-weight: normal; color: #4a5563; }

        .bar { background: #eef0f3; height: 5px; width: 100%; margin-top: 4px; }
        .bar span { display: block; height: 5px; background: #3a6ea5; }

        /* The states, in the order they stack: settled, short, nothing paid.
           Ordered severity, so they take the status roles rather than a new
           categorical set. Text only — a printed sheet has no tooltip, so the
           word always travels with the colour. */
        .s-paid { color: #0f7a3d; font-weight: bold; }
        .s-partial { color: #b8860b; font-weight: bold; }
        .s-unpaid { color: #b3261e; font-weight: bold; }
        .s-plan { color: #2d5f91; font-weight: bold; }
        .s-cancelled { color: #6b7480; font-weight: bold; }
        .is-due { color: #b3261e; font-weight: bold; }
        .zero { color: #c3c8cf; }

        .page-break { page-break-before: always; }

        /* Room to sign above each rule, and the label directly beneath it. */
        table.sign { margin-top: 30px; page-break-inside: avoid; }
        table.sign td { padding-top: 3px; border-top: .5px solid #333; width: 30%; font-size: 8px; }
        table.sign td.gap { border-top: none; width: 5%; }
    </style>
</head>
<body>

@php
    $t = $report['totals'];
    $outside = $report['outside'];
    $decimals = $report['decimals'];
    $currency = $report['currency'];

    $money = fn ($value) => number_format((float) $value, $decimals);

    // A nil figure in a money column is noise; shown grey so the eye skips it.
    $cell = fn ($value) => (float) $value == 0.0
        ? '<span class="zero">' . number_format(0, $decimals) . '</span>'
        : number_format((float) $value, $decimals);

    $stateClass = [
        'paid' => 's-paid', 'partial' => 's-partial', 'unpaid' => 's-unpaid',
        'plan' => 's-plan', 'cancelled' => 's-cancelled', 'overpaid' => 's-plan',
    ];

    // Every breakdown prints the same seven money columns.
    $moneyHead = [
        __('Fees'), __('Students'), __('Billed'), __('Collected'),
        __('Outstanding'), __('Held'), __('%'),
    ];
@endphp

@if(trim($letterhead) !== '')
    {!! $letterhead !!}
@else
    <div class="masthead">{{ $report['institution'] }}</div>
@endif

<h1>{{ __('Fees Report') }}</h1>
<p class="lede">
    {{ __('Generated') }} {{ $report['generated_at']->format('d M Y H:i') }}
    &nbsp;·&nbsp;
    {{ trans_choice(':count fee|:count fees', $t['fees'], ['count' => number_format($t['fees'])]) }},
    {{ trans_choice(':count student|:count students', $t['students'], ['count' => number_format($t['students'])]) }}
    @if($currency) &nbsp;·&nbsp; {{ __('Amounts in') }} {!! $currency !!} @endif
</p>

{{-- ============================== What it covers ============================== --}}
<table class="filters">
    @foreach(array_chunk($report['filters'], 2, true) as $pair)
    <tr>
        @foreach($pair as $label => $value)
        <td class="k">{{ $label }}</td>
        <td>{{ $value }}</td>
        @endforeach
        @if(count($pair) === 1)
        <td class="k"></td><td></td>
        @endif
    </tr>
    @endforeach
</table>

@if(!$report['has_data'])
    <h2>{{ __('Nothing matched') }}</h2>
    <p class="note">{{ __('No fee matches these filters. Widen them and run the report again.') }}</p>
@else

{{-- ============================== The money ============================== --}}
<h2>{{ __('The money') }}</h2>

<table class="tiles">
    <tr>
        <td>
            <div class="tile-l">{{ __('fees_billed') }}</div>
            <div class="tile-n">{{ $money($t['billed']) }}</div>
        </td>
        <td>
            <div class="tile-l">{{ __('fees_collected') }}</div>
            <div class="tile-n s-paid">{{ $money($t['collected']) }}</div>
        </td>
        <td>
            <div class="tile-l">{{ __('fees_outstanding') }}</div>
            <div class="tile-n is-due">{{ $money($t['outstanding']) }}</div>
        </td>
        <td>
            <div class="tile-l">{{ __('fees_overpaid_held') }}</div>
            <div class="tile-n {{ $t['overpaid'] > 0 ? 's-plan' : 'zero' }}">{{ $money($t['overpaid']) }}</div>
        </td>
        <td>
            <div class="tile-l">{{ __('fees_of_billed_collected') }}</div>
            <div class="tile-n">{{ $t['rate'] }}%</div>
            {{-- The bar carries no figure the number above it does not; it is
                 there so the proportion can be seen at a glance. --}}
            <div class="bar"><span style="width: {{ $t['rate'] }}%;"></span></div>
        </td>
    </tr>
</table>

@if($outside['fees'] > 0)
<p class="note">
    <b>{{ __('Not counted above:') }}</b>
    {{ trans_choice(':count fee|:count fees', $outside['fees'], ['count' => number_format($outside['fees'])]) }}
    {{ __('worth') }} {{ $money($outside['billed']) }}
    {{ __('that these filters match but which have no student record behind them — admission fees raised against applicants, and any fee whose enrolment no longer exists.') }}
</p>
@endif

{{-- ============================== Needs checking ==============================
     Only when there is something wrong. A standing "nothing to report" panel
     teaches the reader to skip the one place they must not. --}}
@if($report['anomalies']['fees'] > 0)
<h2 class="s-unpaid">{{ __('Needs checking') }}</h2>
<p class="note">
    {{ __('A fee can only raise overpayment credit out of money paid on it, so the credit moved off a fee can never exceed what was paid on it. On these fees it does, which understates Collected above by') }}
    <b>{{ $money($report['anomalies']['amount']) }}</b>.
    {{ __('The figures are wrong in the records, not in this report, so it does not silently correct them.') }}
</p>
<table class="grid">
    <thead>
        <tr>
            <th>{{ __('field_receipt') }}</th>
            <th>{{ __('field_matricule') }}</th>
            <th>{{ __('Student') }}</th>
            <th>{{ __('field_fees_type') }}</th>
            <th class="num">{{ __('field_net_amount') }}</th>
            <th class="num">{{ __('Recorded as paid') }}</th>
            <th class="num">{{ __('Credit moved to another fee') }}</th>
            <th class="num">{{ __('Understates collected by') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['anomalies']['rows'] as $line)
        <tr>
            <td>{{ $line['receipt'] }}</td>
            <td>{{ $line['matricule'] }}</td>
            <td>{{ $line['name'] }}</td>
            <td>{{ $line['category'] }}</td>
            <td class="num">{{ $money($line['due']) }}</td>
            <td class="num">{{ $money($line['paid_raw']) }}</td>
            <td class="num">{{ $money($line['moved_out']) }}</td>
            <td class="num is-due">{{ $money($line['shortfall']) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ============================== By status ============================== --}}
<h2>{{ __('Fees by status') }}</h2>
<table class="grid">
    <thead>
        <tr>
            <th>{{ __('field_status') }}</th>
            @foreach($moneyHead as $h)<th class="num">{{ $h }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($report['by_status'] as $line)
        <tr>
            <td class="{{ $stateClass[$line['state']] ?? '' }}">{{ $line['title'] }}</td>
            <td class="num">{{ number_format($line['fees']) }}</td>
            <td class="num">{{ number_format($line['students']) }}</td>
            <td class="num">{{ $money($line['billed']) }}</td>
            <td class="num">{{ $money($line['collected']) }}</td>
            <td class="num {{ $line['outstanding'] > 0 ? 'is-due' : '' }}">{{ $money($line['outstanding']) }}</td>
            <td class="num">{!! $cell($line['overpaid']) !!}</td>
            <td class="num">{{ $line['rate'] }}%</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ============================== By fee type ============================== --}}
@php
    /* Every breakdown below is the same table with a different first column,
       so it is written once here rather than five times. $lines are rows from
       FeeReport's breakdowns; $head/$pick say what goes in front of the money. */
    $breakdown = function (array $lines, array $head, callable $pick) use ($money, $cell, $moneyHead) {
        $out = '<table class="grid"><thead><tr>';
        foreach ($head as $h) { $out .= '<th>' . e($h) . '</th>'; }
        foreach ($moneyHead as $h) { $out .= '<th class="num">' . e($h) . '</th>'; }
        $out .= '</tr></thead><tbody>';

        $sum = ['fees' => 0, 'billed' => 0, 'collected' => 0, 'outstanding' => 0, 'overpaid' => 0];

        foreach ($lines as $line) {
            $out .= '<tr>';
            foreach ($pick($line) as $value) { $out .= '<td>' . e($value) . '</td>'; }
            $out .= '<td class="num">' . number_format($line['fees']) . '</td>';
            $out .= '<td class="num">' . number_format($line['students']) . '</td>';
            $out .= '<td class="num">' . $money($line['billed']) . '</td>';
            $out .= '<td class="num">' . $money($line['collected']) . '</td>';
            $out .= '<td class="num' . ($line['outstanding'] > 0 ? ' is-due' : '') . '">' . $money($line['outstanding']) . '</td>';
            $out .= '<td class="num">' . $cell($line['overpaid']) . '</td>';
            $out .= '<td class="num">' . $line['rate'] . '%</td>';
            $out .= '</tr>';

            foreach (array_keys($sum) as $k) { $sum[$k] += $line[$k]; }
        }

        /* A total row, because the first thing anybody does to a column of
           money is add it up, and getting a different answer from the headline
           is how a report loses its reader's trust. Students are deliberately
           NOT totalled: one student can appear on several lines, so a column
           sum would overcount people. */
        $out .= '</tbody><tfoot><tr>';
        $out .= '<td colspan="' . count($head) . '">' . e(__('Total')) . '</td>';
        $out .= '<td class="num">' . number_format($sum['fees']) . '</td>';
        $out .= '<td class="num muted">—</td>';
        $out .= '<td class="num">' . $money($sum['billed']) . '</td>';
        $out .= '<td class="num">' . $money($sum['collected']) . '</td>';
        $out .= '<td class="num">' . $money($sum['outstanding']) . '</td>';
        $out .= '<td class="num">' . $money($sum['overpaid']) . '</td>';
        $out .= '<td class="num">' . ($sum['billed'] > 0 ? min(100, round($sum['collected'] / $sum['billed'] * 100, 1)) : 0) . '%</td>';
        $out .= '</tr></tfoot></table>';

        return $out;
    };
@endphp

<h2>{{ __('By fee type') }}</h2>
{!! $breakdown($report['by_category'], [__('field_fees_type')], fn ($l) => [$l['title']]) !!}

<h2>{{ __('By faculty') }}</h2>
{!! $breakdown($report['by_faculty'], [__('Faculty')], fn ($l) => [$l['title']]) !!}

<h2>{{ __('By programme') }}</h2>
{!! $breakdown($report['by_programme'], [__('Faculty'), __('Programme')], fn ($l) => [$l['faculty'], $l['title']]) !!}

<h2>{{ __('By session and semester') }}</h2>
{!! $breakdown($report['by_term'], [__('Session'), __('Semester')], fn ($l) => [$l['session'], $l['semester']]) !!}

{{-- ============================== How it was collected ============================== --}}
<h2>{{ __('How it was collected') }}</h2>
<table>
    <tr>
        <td style="width: 48%; vertical-align: top; padding-right: 10px;">
            <table class="grid">
                <thead><tr><th>{{ __('field_payment_method') }}</th><th class="num">{{ __('Fees') }}</th><th class="num">{{ __('fees_collected') }}</th></tr></thead>
                <tbody>
                @forelse($report['by_method'] as $line)
                    <tr>
                        <td>{{ $line['title'] }}</td>
                        <td class="num">{{ number_format($line['fees']) }}</td>
                        <td class="num">{{ $money($line['collected']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">{{ __('Nothing has been collected on these fees.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </td>
        <td style="width: 52%; vertical-align: top;">
            <table class="grid">
                <thead><tr><th>{{ __('Month') }}</th><th class="num">{{ __('Fees') }}</th><th class="num">{{ __('fees_collected') }}</th></tr></thead>
                <tbody>
                @forelse($report['by_month'] as $line)
                    <tr>
                        <td>{{ $line['month'] }}</td>
                        <td class="num">{{ number_format($line['fees']) }}</td>
                        <td class="num">{{ $money($line['collected']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">{{ __('No fee here carries a payment date.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>

{{-- ============================== Who owes ============================== --}}
<h2>{{ __('Every student in scope, most owed first') }}</h2>
<table class="grid">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('field_matricule') }}</th>
            <th>{{ __('Student') }}</th>
            <th>{{ __('Programme') }}</th>
            <th class="num">{{ __('Fees') }}</th>
            <th class="num">{{ __('fees_billed') }}</th>
            <th class="num">{{ __('fees_collected') }}</th>
            <th class="num">{{ __('fees_outstanding') }}</th>
            <th class="num">{{ __('%') }}</th>
            <th>{{ __('field_phone') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['students'] as $i => $line)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td>{{ $line['matricule'] }}</td>
            <td>{{ $line['name'] }}</td>
            <td>{{ $line['programme'] }}</td>
            <td class="num">{{ number_format($line['fees']) }}</td>
            <td class="num">{{ $money($line['billed']) }}</td>
            <td class="num">{{ $money($line['collected']) }}</td>
            <td class="num {{ $line['outstanding'] > 0 ? 'is-due' : '' }}">{{ $money($line['outstanding']) }}</td>
            <td class="num">{{ $line['rate'] }}%</td>
            <td>{{ $line['phone'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ============================== The register ============================== --}}
<div class="page-break"></div>
<h2>{{ __('Register of fees') }}</h2>
<p class="note">{{ __('Every fee these filters matched, in the order the report screen lists them.') }}</p>

<table class="grid">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('field_receipt') }}</th>
            <th>{{ __('field_matricule') }}</th>
            <th>{{ __('Student') }}</th>
            <th>{{ __('Programme') }}</th>
            <th>{{ __('field_fees_type') }}</th>
            <th class="num">{{ __('field_net_amount') }}</th>
            <th class="num">{{ __('field_paid') }}</th>
            <th class="num">{{ __('field_remaining_balance') }}</th>
            <th>{{ __('field_status') }}</th>
            <th>{{ __('field_payment_method') }}</th>
            <th>{{ __('field_pay_date') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['register'] as $line)
        <tr>
            <td class="num">{{ $line['no'] }}</td>
            <td>{{ $line['receipt'] }}</td>
            <td>{{ $line['matricule'] }}</td>
            <td>{{ $line['name'] }}</td>
            <td>{{ $line['programme'] }}</td>
            <td>
                {{ $line['category'] }}
                @if($line['resit'])<div class="muted">{{ $line['resit'] }}</div>@endif
            </td>
            <td class="num">{{ $money($line['due']) }}</td>
            <td class="num">
                {{ $money($line['paid']) }}
                @if($line['moved_out'] > 0)<div class="muted">{{ __('moved to another fee') }}: {{ $money($line['moved_out']) }}</div>@endif
            </td>
            <td class="num {{ $line['balance'] > 0 ? 'is-due' : '' }}">
                {{ $money($line['balance']) }}
                @if($line['held'] > 0)<div class="s-plan">+{{ $money($line['held']) }} {{ __('held') }}</div>@endif
            </td>
            <td>{{ $line['status'] }}</td>
            <td>{{ $line['method'] }}</td>
            <td>{{ $line['pay_date'] ? $line['pay_date']->format($report['date_format']) : '' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6">{{ __('Total') }}</td>
            <td class="num">{{ $money($t['billed']) }}</td>
            <td class="num">{{ $money($t['collected']) }}</td>
            <td class="num">{{ $money($t['outstanding']) }}</td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
</table>

@endif

{{-- ============================== Method ============================== --}}
<h2>{{ __('How this report counts') }}</h2>
@foreach($report['notes'] as $note)
<p class="note">{{ $note }}</p>
@endforeach

<table class="sign">
    <tr>
        <td>{{ __('Bursar') }}</td>
        <td class="gap"></td>
        <td>{{ __('Registrar') }}</td>
        <td class="gap"></td>
        <td>{{ __('field_date') }}</td>
    </tr>
</table>

</body>
</html>
