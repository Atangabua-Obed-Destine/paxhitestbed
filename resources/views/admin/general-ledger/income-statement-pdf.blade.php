@extends('admin.accounting.reports.pdf.layout')

@section('title', __('income_statement'))
@section('report_title', __('income_statement') . ' (' . __('compte_de_resultat') . ')')

@php
    $fmt = fn ($v) => format_amount($v, true);
@endphp

@section('content')
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ __('Period') }}:</strong>
        {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : __('from the start of the books') }}
        &ndash; {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
        &middot; {{ __('From posted journal entries') }}
    </p>

    <div class="section-title">{{ __('revenue') }} ({{ __('Produits') }} &ndash; {{ __('class') }} 7)</div>
    <table>
        <tbody>
            @forelse($revenue as $account)
            <tr><td>{{ $account->account_code }} - {{ $account->account_name }}</td><td class="text-right">{{ $fmt($account->balance) }}</td></tr>
            @empty
            <tr><td colspan="2" class="text-center">{{ __('no_revenue_recorded') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr class="totals-row"><td>{{ __('total_revenue') }}</td><td class="text-right">{{ $fmt($totalRevenue) }}</td></tr></tfoot>
    </table>

    <div class="section-title">{{ __('expenses') }} ({{ __('Charges') }} &ndash; {{ __('class') }} 6)</div>
    <table>
        <tbody>
            @forelse($expenses as $account)
            <tr><td>{{ $account->account_code }} - {{ $account->account_name }}</td><td class="text-right">{{ $fmt($account->balance) }}</td></tr>
            @empty
            <tr><td colspan="2" class="text-center">{{ __('no_expenses_recorded') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr class="totals-row"><td>{{ __('total_expenses') }}</td><td class="text-right">{{ $fmt($totalExpenses) }}</td></tr></tfoot>
    </table>

    @if($otherResults->isNotEmpty())
    <div class="section-title">{{ __('other_results') }} (HAO &ndash; {{ __('class') }} 8)</div>
    <table>
        <tbody>
            @foreach($otherResults as $account)
            <tr><td>{{ $account->account_code }} - {{ $account->account_name }}</td><td class="text-right">{{ $fmt($account->normal_balance === 'debit' ? -$account->balance : $account->balance) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr class="totals-row"><td>{{ __('total_other_results') }}</td><td class="text-right">{{ $fmt($totalOtherResults) }}</td></tr></tfoot>
    </table>
    @endif

    <table style="margin-top: 12px;">
        <tbody>
            <tr class="totals-row">
                <td>{{ $netProfit >= 0 ? __('net_profit') : __('net_loss') }} ({{ $netProfit >= 0 ? __('benefice_net') : __('perte_nette') }})</td>
                <td class="text-right">{{ $fmt($netProfit) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
