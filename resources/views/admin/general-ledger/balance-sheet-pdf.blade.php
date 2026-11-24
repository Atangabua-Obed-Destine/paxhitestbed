@extends('admin.accounting.reports.pdf.layout')

@section('title', __('balance_sheet'))
@section('report_title', __('balance_sheet') . ' (' . __('bilan') . ')')

@php
    $fmt = fn ($v) => format_amount($v, true);
    $cmp = $comparative ?? null;
    $cmpLine = fn (string $section, $account) => $cmp ? ($cmp[$section]->firstWhere('id', $account->id)->balance ?? 0) : null;
    $actif = [
        'fixedAssets' => __('Fixed assets') . ' (' . __('Actif immobilisé') . ')',
        'inventory' => __('Inventory') . ' (' . __('Stocks') . ')',
        'receivables' => __('Receivables') . ' (' . __('Créances') . ')',
        'cashAndBanks' => __('Cash and banks') . ' (' . __('Trésorerie-Actif') . ')',
    ];
    $passif = [
        'financialDebts' => __('Borrowings and provisions') . ' (' . __('Dettes financières') . ')',
        'payables' => __('Payables') . ' (' . __('Passif circulant') . ')',
        'treasuryLiabilities' => __('Bank overdrafts') . ' (' . __('Trésorerie-Passif') . ')',
    ];
    $cols = $cmp ? 3 : 2;
@endphp

@section('content')
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ __('as_of') }}:</strong> {{ \Carbon\Carbon::parse($asOfDate)->format('d/m/Y') }}
        @if($fiscalYear) &middot; {{ $fiscalYear->name }} @endif
    </p>

    <div class="section-title">{{ __('assets') }} ({{ __('actif') }})</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('account') }}</th>
                <th class="text-right">{{ \Carbon\Carbon::parse($asOfDate)->format('d/m/Y') }}</th>
                @if($cmp)<th class="text-right">{{ \Carbon\Carbon::parse($comparativeDate)->format('d/m/Y') }}</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($actif as $key => $label)
            <tr class="font-bold"><td>{{ $label }}</td><td class="text-right">{{ $fmt(${$key}->sum('balance')) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp[$key]->sum('balance')) }}</td>@endif</tr>
            @foreach(${$key} as $account)
            <tr><td style="padding-left: 14px;">{{ $account->account_code }} - {{ $account->account_name }}</td><td class="text-right">{{ $fmt($account->balance) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmpLine($key, $account)) }}</td>@endif</tr>
            @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals-row"><td>{{ __('total_assets') }}</td><td class="text-right">{{ $fmt($totalAssets) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['totalAssets']) }}</td>@endif</tr>
        </tfoot>
    </table>

    <div class="section-title">{{ __('liabilities_equity') }} ({{ __('passif') }})</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('account') }}</th>
                <th class="text-right">{{ \Carbon\Carbon::parse($asOfDate)->format('d/m/Y') }}</th>
                @if($cmp)<th class="text-right">{{ \Carbon\Carbon::parse($comparativeDate)->format('d/m/Y') }}</th>@endif
            </tr>
        </thead>
        <tbody>
            <tr class="font-bold"><td>{{ __('equity_capital') }} ({{ __('capitaux_propres') }})</td><td class="text-right">{{ $fmt($totalEquity) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['totalEquity']) }}</td>@endif</tr>
            @foreach($equity as $account)
            <tr><td style="padding-left: 14px;">{{ $account->account_code }} - {{ $account->account_name }}</td><td class="text-right">{{ $fmt($account->balance) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmpLine('equity', $account)) }}</td>@endif</tr>
            @endforeach
            @if(abs($priorResult) > 0.004 || ($cmp && abs($cmp['priorResult']) > 0.004))
            <tr><td style="padding-left: 14px;">{{ __('Report à nouveau') }}</td><td class="text-right">{{ $fmt($priorResult) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['priorResult']) }}</td>@endif</tr>
            @endif
            <tr><td style="padding-left: 14px;">{{ __('Résultat net de l\'exercice') }}</td><td class="text-right">{{ $fmt($yearResult) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['yearResult']) }}</td>@endif</tr>
            @foreach($passif as $key => $label)
            <tr class="font-bold"><td>{{ $label }}</td><td class="text-right">{{ $fmt(${$key}->sum('balance')) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp[$key]->sum('balance')) }}</td>@endif</tr>
            @foreach(${$key} as $account)
            <tr><td style="padding-left: 14px;">{{ $account->account_code }} - {{ $account->account_name }}</td><td class="text-right">{{ $fmt($account->balance) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmpLine($key, $account)) }}</td>@endif</tr>
            @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals-row"><td>{{ __('total_liabilities_equity') }}</td><td class="text-right">{{ $fmt($totalLiabilities) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['totalLiabilities']) }}</td>@endif</tr>
        </tfoot>
    </table>

    <p class="{{ abs($difference) < 0.01 ? 'text-success' : 'text-danger' }}" style="margin-top: 10px;">
        @if(abs($difference) < 0.01)
            {{ __('balance_sheet_is_balanced') }}: {{ __('assets') }} = {{ __('liabilities_equity') }}
        @else
            {{ __('balance_sheet_not_balanced') }}: {{ __('difference') }} {{ format_amount(abs($difference)) }} FCFA
        @endif
    </p>
@endsection
