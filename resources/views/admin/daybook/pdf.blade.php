<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Daybook') }} — {{ $period->name }}</title>
    {!! $letterheadStyles !!}
    <style>
        @page { margin: 14mm 10mm 14mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }

        h1 { font-size: 13px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: .04em; }
        .period { font-size: 9px; color: #444; margin: 0 0 10px; }

        table.book { width: 100%; border-collapse: collapse; }
        table.book th, table.book td { border: .5px solid #999; padding: 2.5px 4px; }
        table.book th { background: #e8ebef; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
        .num { text-align: right; white-space: nowrap; }
        .ref { font-size: 8px; color: #555; }
        .line { font-size: 8px; color: #333; }
        .none { color: #8a5312; font-weight: bold; }
        .total td { border-top: 1.2px solid #333; font-weight: bold; background: #f2f4f7; }

        /* A month runs to several pages and a bare column of figures is
           unreadable without its headings. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .warn {
            border: .5px solid #c98b2e; background: #fdf6ea; color: #7a5312;
            padding: 5px 7px; margin-bottom: 8px; font-size: 8px;
        }
        .foot { margin-top: 12px; font-size: 8px; color: #444; }
        .sign { margin-top: 24px; width: 100%; }
        .sign td { padding-top: 18px; border-top: .5px solid #333; font-size: 8px; width: 33%; }
    </style>
</head>
<body>

{!! $letterhead !!}

<h1>{{ __('Daybook — Cash Analysis Book') }}</h1>
<p class="period">
    {{ $period->name }}
    &nbsp;|&nbsp; {{ $budget->title }}
    &nbsp;|&nbsp; {{ __('Generated') }} {{ now()->format('d M Y H:i') }}
    &nbsp;|&nbsp; {{ __('Amounts in FCFA') }}
</p>

@if(count($unanalysed))
    <div class="warn">
        <strong>{{ trans_choice(':count movement carries no analysis column|:count movements carry no analysis column', count($unanalysed), ['count' => count($unanalysed)]) }}.</strong>
        {{ __('They are marked below. Until a category points them at a budget line they cannot appear on the Income & Expenditure sheet.') }}
    </div>
@endif

<table class="book">
    <thead>
        <tr>
            <th style="width:44px">{{ __('Date') }}</th>
            <th style="width:70px">{{ __('Ref') }}</th>
            <th>{{ __('Description') }}</th>
            <th style="width:150px">{{ __('Analysis column') }}</th>
            <th style="width:74px" class="num">{{ __('Debit IN') }}</th>
            <th style="width:74px" class="num">{{ __('Credit OUT') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M') }}</td>
                <td class="ref">{{ $row['ref'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="line">
                    @php $line = $row['line_id'] ? ($lines[$row['line_id']] ?? null) : null; @endphp
                    @if($line)
                        {{ $line->code }} {{ $line->name }}
                    @else
                        <span class="none">{{ __('none') }}</span>
                    @endif
                </td>
                <td class="num">{{ $row['direction'] === 'in' ? number_format($row['amount']) : '' }}</td>
                <td class="num">{{ $row['direction'] === 'out' ? number_format($row['amount']) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6">{{ __('No cash moved in this period.') }}</td></tr>
        @endforelse
    </tbody>
    @if(count($rows))
        <tfoot>
            <tr class="total">
                <td colspan="4">{{ __('TOTAL FOR') }} {{ strtoupper($period->name) }}</td>
                <td class="num">{{ number_format($totalIn) }}</td>
                <td class="num">{{ number_format($totalOut) }}</td>
            </tr>
            <tr class="total">
                <td colspan="4">{{ __('NET MOVEMENT') }}</td>
                <td class="num" colspan="2">{{ number_format($totalIn - $totalOut) }}</td>
            </tr>
        </tfoot>
    @endif
</table>

<p class="foot">
    {{ __('Generated from fees, incomes, expenses and payroll already recorded in the system. The Income & Expenditure sheet is this same money summed down these columns.') }}
</p>

<table class="sign">
    <tr>
        <td>{{ __('Bursar') }}</td>
        <td>{{ __('Principal') }}</td>
        <td>{{ __('Date') }}</td>
    </tr>
</table>

</body>
</html>
