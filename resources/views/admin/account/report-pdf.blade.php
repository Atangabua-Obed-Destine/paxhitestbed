{{--
  The income / expenditure report, as a document.

  One file for both sides of the sheet, like the screen it prints from: the
  two were byte-identical once before and drifted, and a report that drifted
  would be a signed document disagreeing with its own screen.

  Expects: $report (from TransactionReport::build), $cover (from describe()),
  $filtered, $title, $path, $generatedAt, $generatedBy, $setting, and the
  letterhead pair.

  Read top to bottom it answers, in this order: who issued this and what does
  it cover, how much is it, where did it come from, when did it move, and
  then every record behind those figures - all of them, not a page of them.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $cover[0]['value'] }}</title>
    {!! $letterheadStyles !!}
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }

        /* body, not *. The universal selector applies a font DIRECTLY to every
           element including <strong>, and a direct rule beats an inherited
           one, so bold text comes out in a different typeface rather than a
           heavier one. */
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }

        h1 { font-size: 14px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: .04em; }
        h2 {
            font-size: 11px; margin: 14px 0 4px; color: {{ $accentDark }};
            border-bottom: .8px solid #c3c2b7; padding-bottom: 3px;
        }
        .period { font-size: 9px; color: #444; margin: 0 0 10px; }
        .sub { font-size: 8.5px; color: #444; margin: 0 0 6px; line-height: 1.5; }

        table.g { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        /* Top-aligned: dompdf centres by default, which leaves a one-word cell
           floating halfway down a taller row and makes the columns read as
           though they had come apart. */
        table.g th, table.g td { border: .5px solid #999; padding: 2.5px 4px; vertical-align: top; }
        table.g th { background: #e8ebef; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
        .num { text-align: right; white-space: nowrap; }
        .total td { border-top: 1.2px solid #333; font-weight: bold; background: #f2f4f7; }

        /* Repeat the header on every page of the itemised table, and never
           split a row across the fold. Without the first, page 4 of a long
           report is a wall of unlabelled numbers. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        /* The tiles, as a strip of cells - dompdf lays a table out reliably
           where it will not lay out flexbox at all. */
        table.tiles { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 6px; }
        table.tiles td { border: .5px solid #c9d2de; background: #fbfcfd; padding: 6px 7px; width: 25%; }
        table.tiles .lbl { font-size: 7px; text-transform: uppercase; letter-spacing: .06em; color: #70809a; }
        table.tiles .val { font-size: 13px; font-weight: bold; }
        table.tiles .note { font-size: 7.5px; color: #555; }

        /* What this covers. Boxed and placed before any figure, because a
           total under a letterhead is read as all the money unless the
           document says otherwise. */
        table.cover { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.cover td { border: .5px solid #c9d2de; padding: 3px 5px; font-size: 8.5px; }
        table.cover td.k {
            background: #f4f6f9; width: 22%; text-transform: uppercase;
            font-size: 7.5px; letter-spacing: .04em; color: #4a5a72;
        }

        .warn {
            border-left: 2.5px solid #7a5312; background: #fdf7ec;
            padding: 5px 7px; font-size: 8.5px; margin-bottom: 8px; line-height: 1.5;
        }

        /* The share bar. A table cell with a background, because dompdf will
           not render a div of a computed width reliably inside a table. */
        .bar { height: 5px; background: #e9edf2; }
        .bar div { height: 5px; background: {{ $accent }}; }

        .foot { margin-top: 12px; font-size: 7.5px; color: #444; line-height: 1.5; }
        .sign { margin-top: 18px; width: 100%; }
        .sign td { padding-top: 16px; border-top: .5px solid #333; font-size: 8px; width: 33%; }
        .brk { page-break-before: always; }

        /* dompdf resolves counter(page) only inside a fixed-position box. A
           long report is the one document where the reader needs to know a
           page is missing. */
        .pageno {
            position: fixed; bottom: -10mm; left: 0; right: 0;
            text-align: center; font-size: 7.5px; color: #666;
        }
        .pageno:after { content: counter(page); }
    </style>
</head>
<body>

<div class="pageno">{{ __('Page') }} </div>

{!! $letterhead !!}

@php
    $r = $report;
    $h = $r['headline'];
    $decimals = $setting->decimal_place ?? 2;
    $currency = $setting->currency_symbol ?? '';
    $dateFormat = $setting->date_format ?? 'Y-m-d';
    $money = fn ($value) => number_format((float) $value, $decimals);
    $day = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format($dateFormat) : '—';
@endphp

<h1>{{ $title }}</h1>
<p class="period">
    {{ $cover[0]['value'] }}
    @if($h['count'] > 0)
        · {{ __('entries dated :first to :last', ['first' => $day($h['first']), 'last' => $day($h['last'])]) }}
    @endif
</p>

{{-- What this covers, before any figure. --}}
<table class="cover">
    @foreach($cover as $line)
    <tr>
        <td class="k">{{ $line['label'] }}</td>
        <td>{{ $line['value'] }}</td>
    </tr>
    @endforeach
</table>

@if($filtered)
{{-- Stated, not implied. The single most likely way this document gets
     misread in a meeting is as a complete statement of the period's money,
     and the only defence is to say plainly that it is not. --}}
<div class="warn">
    <strong>{{ __('This is a filtered report.') }}</strong>
    {{ __('The figures below cover only the records described above. They are not the whole of this period\'s :what. Remove the filters to report on everything.', ['what' => mb_strtolower($title)]) }}
</div>
@endif

@if($h['count'] === 0)

    <p class="sub">{{ __('No records match this filter, so there is nothing to report. This is not an error: it means nothing was recorded under these terms in this period.') }}</p>

@else

<table class="tiles">
    <tr>
        <td>
            <div class="lbl">{{ __('Total') }}</div>
            <div class="val">{{ $money($h['total']) }}</div>
            <div class="note">{{ $currency }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('acc_records') }}</div>
            <div class="val">{{ number_format($h['count']) }}</div>
            <div class="note">{{ __('entries in this report') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('Average entry') }}</div>
            <div class="val">{{ $money($h['average']) }}</div>
            <div class="note">{{ __('total divided by the count') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('Largest single entry') }}</div>
            <div class="val">{{ $money($h['largest']) }}</div>
            <div class="note">{{ Str::limit($h['largest_title'] ?? '—', 28) }}</div>
        </td>
    </tr>
</table>

<p class="sub">
    {{ __('The average and the largest entry are given together on purpose: a total made of one large payment and nine small ones reads very differently from ten even ones, and the average alone cannot tell the two apart.') }}
</p>

{{-- ================= by category ================= --}}
<h2>{{ __('By category') }}</h2>
<p class="sub">{{ __('Largest first. Share is of the total above, so these add to 100% of what this report covers — not of all :what.', ['what' => mb_strtolower($title)]) }}</p>
<table class="g">
    <thead><tr>
        <th>{{ __('field_category') }}</th>
        <th class="num" style="width:60px">{{ __('acc_records') }}</th>
        <th class="num" style="width:95px">{{ __('field_amount') }}</th>
        <th class="num" style="width:50px">{{ __('Share') }}</th>
        <th style="width:110px"></th>
    </tr></thead>
    <tbody>
    @foreach($r['by_category'] as $row)
        <tr>
            <td>{{ $row['label'] }}</td>
            <td class="num">{{ number_format($row['count']) }}</td>
            <td class="num">{{ $money($row['total']) }}</td>
            <td class="num">{{ number_format($row['share'], 1) }}%</td>
            <td>
                <div class="bar"><div style="width: {{ max(1, $row['share']) }}%"></div></div>
            </td>
        </tr>
    @endforeach
        <tr class="total">
            <td>{{ __('Total') }}</td>
            <td class="num">{{ number_format($h['count']) }}</td>
            <td class="num">{{ $money($h['total']) }}</td>
            <td class="num">100.0%</td>
            <td></td>
        </tr>
    </tbody>
</table>

{{-- ================= by payment method ================= --}}
<h2>{{ __('field_payment_method') }}</h2>
<p class="sub">{{ __('How the money moved. An entry with no method recorded is shown as such rather than folded into cash, because the two are not the same thing to anyone reconciling a bank statement.') }}</p>
<table class="g">
    <thead><tr>
        <th>{{ __('field_payment_method') }}</th>
        <th class="num" style="width:60px">{{ __('acc_records') }}</th>
        <th class="num" style="width:95px">{{ __('field_amount') }}</th>
        <th class="num" style="width:50px">{{ __('Share') }}</th>
        <th style="width:110px"></th>
    </tr></thead>
    <tbody>
    @foreach($r['by_method'] as $row)
        <tr>
            <td>{{ $row['label'] }}</td>
            <td class="num">{{ number_format($row['count']) }}</td>
            <td class="num">{{ $money($row['total']) }}</td>
            <td class="num">{{ number_format($row['share'], 1) }}%</td>
            <td>
                <div class="bar"><div style="width: {{ max(1, $row['share']) }}%"></div></div>
            </td>
        </tr>
    @endforeach
        <tr class="total">
            <td>{{ __('Total') }}</td>
            <td class="num">{{ number_format($h['count']) }}</td>
            <td class="num">{{ $money($h['total']) }}</td>
            <td class="num">100.0%</td>
            <td></td>
        </tr>
    </tbody>
</table>

{{-- ================= month by month ================= --}}
@if(count($r['by_month']) > 1)
<h2>{{ __('Month by month') }}</h2>
<p class="sub">{{ __('Every month between the first and last entry, including the ones with nothing in them: a list that skipped those would draw a straight line through the gap and hide the month worth asking about.') }}</p>
<table class="g">
    <thead><tr>
        <th>{{ __('Month') }}</th>
        <th class="num" style="width:60px">{{ __('acc_records') }}</th>
        <th class="num" style="width:95px">{{ __('field_amount') }}</th>
        <th style="width:160px"></th>
    </tr></thead>
    <tbody>
    @php $peak = max(array_map(fn ($m) => $m['total'], $r['by_month'])) ?: 1; @endphp
    @foreach($r['by_month'] as $row)
        <tr>
            <td>{{ $row['label'] }}</td>
            <td class="num">{{ $row['count'] === 0 ? '—' : number_format($row['count']) }}</td>
            <td class="num">{{ $row['count'] === 0 ? '—' : $money($row['total']) }}</td>
            <td>
                @if($row['total'] > 0)
                <div class="bar"><div style="width: {{ max(1, round($row['total'] / $peak * 100)) }}%"></div></div>
                @endif
            </td>
        </tr>
    @endforeach
        <tr class="total">
            <td>{{ __('Total') }}</td>
            <td class="num">{{ number_format($h['count']) }}</td>
            <td class="num">{{ $money($h['total']) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
@endif

{{-- ================= every record ================= --}}
<h2 class="brk">{{ __('All records') }}</h2>
<p class="sub">
    {{ __('Every one of the :count records behind the figures above, newest first. Not a page of them: this document is built from the records themselves, so what is listed here is the whole of what the filter selected.', ['count' => number_format($h['count'])]) }}
</p>
<table class="g">
    <thead><tr>
        <th style="width:22px">#</th>
        <th style="width:58px">{{ __('field_date') }}</th>
        <th>{{ __('field_title') }}</th>
        <th style="width:95px">{{ __('field_category') }}</th>
        <th style="width:62px">{{ __('field_payment_method') }}</th>
        <th style="width:66px">{{ __('field_invoice_id') }}</th>
        <th class="num" style="width:82px">{{ __('field_amount') }}</th>
    </tr></thead>
    <tbody>
    @foreach($r['rows'] as $i => $row)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td>{{ $day($row->date) }}</td>
            <td>{{ $row->title }}</td>
            <td>{{ $row->category->title ?? '—' }}</td>
            <td>{{ \App\Support\PaymentMethod::label($row->payment_method) }}</td>
            <td>{{ $row->invoice_id }}</td>
            <td class="num">{{ $money($row->amount) }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="6">{{ __('Total') }} — {{ number_format($h['count']) }} {{ __('acc_records') }}</td>
            <td class="num">{{ $money($h['total']) }}</td>
        </tr>
    </tbody>
</table>

@endif

<p class="foot">
    {{ __('Generated :at by :who.', ['at' => $generatedAt->format($dateFormat . ' H:i'), 'who' => $generatedBy]) }}
    {{ __('Amounts are in :currency.', ['currency' => $currency ?: __('the system currency')]) }}
    {{ __('Figures are taken from the records as they stand at that moment; a record edited afterwards will not match this copy.') }}
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
