@extends('admin.accounting.reports.pdf.layout')

@section('title', __('account_ledger'))
@section('report_title', __('account_ledger') . ' — ' . $account->account_code . ' ' . $account->account_name)

@php
    $fmt = fn ($v) => format_amount((float) $v);
    $debitNormal = $account->normal_balance === 'debit';
    $running = (float) $openingBalance;
@endphp

@section('content')
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ __('Period') }}:</strong>
        {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : __('from the start of the books') }}
        &ndash; {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : now()->format('d/m/Y') }}
        &middot; {{ __('Normal balance') }}: {{ $debitNormal ? __('debit') : __('credit') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>{{ __('date') }}</th>
                <th>{{ __('entry_number') }}</th>
                <th>{{ __('description') }}</th>
                <th class="text-right">{{ __('debit') }}</th>
                <th class="text-right">{{ __('credit') }}</th>
                <th class="text-right">{{ __('running_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="font-bold">
                <td colspan="5">{{ __('opening_balance') }}</td>
                <td class="text-right">{{ $fmt($openingBalance) }}</td>
            </tr>
            @foreach($transactions as $t)
            @php $running += $debitNormal ? ($t->debit - $t->credit) : ($t->credit - $t->debit); @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($t->journalEntry->entry_date)->format('d/m/Y') }}</td>
                <td>{{ $t->journalEntry->entry_number }}</td>
                <td>{{ $t->description ?: $t->journalEntry->description }}</td>
                <td class="text-right">{{ $t->debit > 0 ? $fmt($t->debit) : '' }}</td>
                <td class="text-right">{{ $t->credit > 0 ? $fmt($t->credit) : '' }}</td>
                <td class="text-right">{{ $fmt($running) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td colspan="3">{{ __('closing_balance') }} ({{ $transactions->count() }} {{ __('entries') }})</td>
                <td class="text-right">{{ $fmt($transactions->sum('debit')) }}</td>
                <td class="text-right">{{ $fmt($transactions->sum('credit')) }}</td>
                <td class="text-right">{{ $fmt($closingBalance) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
