@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@include('admin.supplier-bill.partials.style')

@php
    $verdict = [
        'ok' => ['label' => __('Healthy'), 'chip' => 'paid'],
        'watch' => ['label' => __('Watch'), 'chip' => 'part_paid'],
        'act' => ['label' => __('Act now'), 'chip' => 'unpaid'],
    ];
    $show = function ($i) {
        if ($i['value'] === null) { return '—'; }
        $v = round((float) $i['value'], $i['unit'] === 'money' ? 0 : 1) + 0;
        return match ($i['unit']) {
            'percent' => number_format($v, 1, ',', ' ') . ' %',
            'months' => number_format($v, 1, ',', ' ') . ' ' . __('months'),
            default => format_amount($v),
        };
    };
    $peak = max(1, collect($h['months'])->flatMap(fn ($m) => [$m['income'], $m['expenses']])->max());
    $cashPeak = max(1, collect($h['months'])->map(fn ($m) => abs($m['cash']))->max());
@endphp

<style>
    .fh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }
    .fh-tile { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; background: #fff; }
    .fh-tile .fh-label { color: #475569; font-size: .8rem; font-weight: 600; }
    .fh-tile .fh-value { font-size: 1.35rem; font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; margin: 4px 0; }
    .fh-tile .fh-detail { color: #64748b; font-size: .78rem; line-height: 1.35; }
    .fh-bar { height: 8px; border-radius: 0 4px 4px 0; display: inline-block; vertical-align: middle; }
    .fh-bar.in { background: #1d4ed8; }
    .fh-bar.out { background: #b45309; }
    .fh-bar.cash { background: #0f766e; }
    .fh-bar.neg { background: #b91c1c; }
    .fh-key span { display: inline-flex; align-items: center; gap: 6px; margin-right: 14px; font-size: .8rem; color: #334155; }
    .fh-key i { width: 12px; height: 8px; display: inline-block; border-radius: 2px; }
</style>

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
                        <h5 class="mb-0">{{ __('Financial health') }}
                            <span class="ap-muted">{{ __('from the books, at :date', ['date' => format_date($h['as_of'])]) }}@if($h['year']) · {{ $h['year'] }}@endif</span></h5>
                        <form method="get" class="ap-filters">
                            <div class="form-group">
                                <label for="as_of">{{ __('As at') }}</label>
                                <input type="date" name="as_of" id="as_of" class="form-control" value="{{ $h['as_of'] }}">
                            </div>
                            <div class="form-group"><button type="submit" class="btn btn-info"><i class="fas fa-sync"></i> {{ __('Show') }}</button></div>
                        </form>
                    </div>
                    <div class="card-block">

                        @if($h['attention'])
                        <div class="ap-note is-warn mb-3">
                            <strong>{{ __('Needs attention') }}</strong>
                            <ul class="mb-0 pl-3">
                                @foreach($h['attention'] as $i)
                                <li><strong>{{ $i['label'] }}: {{ $show($i) }}</strong> — {{ $i['detail'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @else
                        <div class="ap-note mb-3">{{ __('Nothing needs attention on these measures.') }}</div>
                        @endif

                        <div class="fh-grid mb-4">
                            @foreach($h['indicators'] as $i)
                            <div class="fh-tile">
                                <div class="d-flex justify-content-between align-items-start" style="gap:8px">
                                    <span class="fh-label">{{ $i['label'] }}</span>
                                    <span class="ap-chip {{ $verdict[$i['state']]['chip'] }}">{{ $verdict[$i['state']]['label'] }}</span>
                                </div>
                                <div class="fh-value">{{ $show($i) }}</div>
                                <div class="fh-detail">{{ $i['detail'] }}</div>
                            </div>
                            @endforeach
                        </div>

                        <div class="row">
                            <div class="col-xl-8">
                                <h6 class="mb-1">{{ __('Twelve months from the books') }}</h6>
                                <div class="fh-key mb-2">
                                    <span><i class="fh-bar in"></i>{{ __('Income (class 7)') }}</span>
                                    <span><i class="fh-bar out"></i>{{ __('Expenses (class 6)') }}</span>
                                    <span><i class="fh-bar cash"></i>{{ __('Cash at the month end') }}</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm ap-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Month') }}</th>
                                                <th class="ap-num">{{ __('Income') }}</th>
                                                <th class="ap-num">{{ __('Expenses') }}</th>
                                                <th style="width:24%"></th>
                                                <th class="ap-num">{{ __('Surplus') }}</th>
                                                <th class="ap-num">{{ __('Cash at the month end') }}</th>
                                                <th style="width:14%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($h['months'] as $m)
                                            <tr>
                                                <td class="text-nowrap">{{ $m['label'] }}</td>
                                                <td class="ap-num">{{ format_amount($m['income']) }}</td>
                                                <td class="ap-num">{{ format_amount($m['expenses']) }}</td>
                                                <td>
                                                    <div><span class="fh-bar in" style="width: {{ max(0, round($m['income'] / $peak * 100, 1)) }}%" title="{{ __('Income') }} {{ format_amount($m['income']) }}"></span></div>
                                                    <div><span class="fh-bar out" style="width: {{ max(0, round($m['expenses'] / $peak * 100, 1)) }}%" title="{{ __('Expenses') }} {{ format_amount($m['expenses']) }}"></span></div>
                                                </td>
                                                <td class="ap-num">{{ format_amount($m['surplus']) }}@if($m['surplus'] < 0) <span class="ap-chip unpaid">{{ __('deficit') }}</span>@endif</td>
                                                <td class="ap-num">{{ format_amount($m['cash']) }}@if($m['cash'] < 0) <span class="ap-chip unpaid">{{ __('below zero') }}</span>@endif</td>
                                                <td><span class="fh-bar {{ $m['cash'] < 0 ? 'neg' : 'cash' }}" style="width: {{ round(abs($m['cash']) / $cashPeak * 100, 1) }}%" title="{{ format_amount($m['cash']) }}"></span></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if(collect($h['months'])->contains(fn ($m) => $m['cash'] < 0))
                                <div class="ap-note is-warn">{{ __('Cash below zero in the books usually means money was paid out before the receipts that covered it were recorded, or that an opening balance is missing. Check the opening balances of the cash and bank accounts.') }}</div>
                                @endif
                            </div>
                            <div class="col-xl-4">
                                <h6 class="mb-2">{{ __('This year against last year, to the same date') }}</h6>
                                @php $ly = $h['last_year']; @endphp
                                <table class="table table-sm ap-table">
                                    <thead>
                                        <tr><th></th><th class="ap-num">{{ __('This year') }}</th><th class="ap-num">{{ __('Last year') }}</th><th class="ap-num">{{ __('Change') }}</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach([['Income', $h['income'], $ly['income']], ['Expenses', $h['expenses'], $ly['expenses']], ['Surplus', $h['surplus'], $ly['surplus']]] as [$label, $now, $then])
                                        <tr>
                                            <td>{{ __($label) }}</td>
                                            <td class="ap-num">{{ format_amount($now) }}</td>
                                            <td class="ap-num">{{ format_amount($then) }}</td>
                                            <td class="ap-num">{{ abs($then) > 0.5 ? number_format(($now - $then) / abs($then) * 100, 1, ',', ' ') . ' %' : '—' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="ap-muted mb-3">{{ __(':a to :b, against :c to :d.', ['a' => format_date($h['from']), 'b' => format_date($h['as_of']), 'c' => format_date($ly['from']), 'd' => format_date($ly['to'])]) }}</div>

                                <h6 class="mb-2">{{ __('Where to look next') }}</h6>
                                <ul class="pl-3 small">
                                    <li><a href="{{ route('admin.general-ledger.income-statement') }}">{{ __('Income statement') }}</a> · <a href="{{ route('admin.general-ledger.balance-sheet') }}">{{ __('Balance sheet') }}</a> · <a href="{{ route('admin.general-ledger.trial-balance') }}">{{ __('Trial balance') }}</a></li>
                                    <li><a href="{{ route('admin.daybook.analysis') }}">{{ __('Budget analysis and fee collection by student') }}</a></li>
                                    <li><a href="{{ route('admin.supplier-bill.aging') }}">{{ __('Payables aging') }}</a> · <a href="{{ route('admin.accounting-reports.student-fee-aging') }}">{{ __('Student fee aging') }}</a></li>
                                    <li><a href="{{ route('admin.bank-reconciliation.index') }}">{{ __('Bank reconciliation') }}</a> · <a href="{{ route('admin.accounting.ledger-health') }}">{{ __('Ledger health') }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
