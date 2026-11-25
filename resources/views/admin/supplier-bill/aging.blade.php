@extends($print ? 'admin.supplier-bill.print' : 'admin.layouts.master')
@section('title', $title)
@section('content')

@unless($print)
@include('admin.supplier-bill.partials.style')
@endunless

@php
    $bucketLabels = [
        'not_due' => __('Not yet due'),
        'd1_30' => __('1–30 days'),
        'd31_60' => __('31–60 days'),
        'd61_90' => __('61–90 days'),
        'over_90' => __('Over 90 days'),
    ];
    $t = $report['totals'];
@endphp

@if($print)
    <h1 class="doc-title">{{ __('Payables Aging') }}</h1>
    <div class="doc-sub">{{ __('What the school owes its suppliers on') }} {{ format_date($report['as_of']) }}, {{ __('by how long past the due date') }}</div>
@else
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header"><h5>{{ $title }}</h5></div>
                    <div class="card-block">
                        @include('admin.supplier-bill.partials.nav')
                        <form method="get" class="ap-filters mt-3">
                            <div class="form-group">
                                <label for="as_of">{{ __('As of') }}</label>
                                <input type="date" name="as_of" id="as_of" class="form-control" value="{{ $report['as_of'] }}">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('Show') }}</button>
                                <a href="{{ route($route.'.aging', ['as_of' => $report['as_of'], 'print' => 1]) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print"></i> {{ __('btn_print') }}</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <p class="ap-muted">{{ __('Counted from each bill\'s due date. A bill entered without payment terms is due on its own date.') }}</p>
@endif

                        <div class="table-responsive">
                            <table class="table ap-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Supplier') }}</th>
                                        @foreach($bucketLabels as $label)
                                        <th class="ap-num">{{ $label }}</th>
                                        @endforeach
                                        <th class="ap-num">{{ __('Total owed') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($report['suppliers'] as $s)
                                    <tr>
                                        <td>
                                            @if($print){{ $s['name'] }}@else
                                            <a href="{{ route($route.'.statement', ['supplier_id' => $s['supplier_id'], 'to' => $report['as_of']]) }}">{{ $s['name'] }}</a>
                                            <div class="ap-muted">{{ count($s['bills']) }} {{ count($s['bills']) === 1 ? __('bill') : __('bills') }}</div>
                                            @endif
                                        </td>
                                        @foreach(array_keys($bucketLabels) as $b)
                                        <td class="ap-num">{{ $s[$b] > 0 ? format_amount($s[$b]) : '—' }}</td>
                                        @endforeach
                                        <td class="ap-num"><strong>{{ format_amount($s['total']) }}</strong></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="text-center ap-muted">{{ __('Nothing is owed to any supplier on this date.') }}</td></tr>
                                    @endforelse
                                    <tr class="ap-total-row">
                                        <td>{{ __('Total') }}</td>
                                        @foreach(array_keys($bucketLabels) as $b)
                                        <td class="ap-num">{{ format_amount($t[$b]) }}</td>
                                        @endforeach
                                        <td class="ap-num">{{ format_amount($t['total']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- The tie-out an auditor asks for first. --}}
                        <table class="table ap-table mt-3" style="max-width:560px">
                            <tbody>
                                <tr><td>{{ __('Owed on the bills above') }}</td><td class="ap-num">{{ format_amount($t['total']) }}</td></tr>
                                <tr><td>{{ __('Balance of the supplier account in the ledger') }}</td><td class="ap-num">{{ format_amount($report['ledger_balance']) }}</td></tr>
                                <tr class="ap-total-row"><td>{{ __('Difference') }}</td><td class="ap-num">{{ format_amount($report['difference']) }}</td></tr>
                            </tbody>
                        </table>
                        @if(abs($report['difference']) > 0.005)
                        <div class="{{ $print ? 'ap-muted' : 'ap-note is-warn' }}">{{ __('The ledger balance and the bills differ. That is money posted to the supplier account other than through a bill or a supplier payment — for example a journal entry typed by hand. Open the account in the general ledger to see which entries.') }}</div>
                        @else
                        <div class="{{ $print ? 'ap-muted' : 'ap-note' }}">{{ __('The bills agree with the ledger.') }}</div>
                        @endif

                        @unless($print)
                        <h6 class="mt-4">{{ __('Bills behind these figures') }}</h6>
                        <div class="table-responsive">
                            <table id="export-table" class="display table table-striped ap-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('Supplier') }}</th>
                                        <th>{{ __('Bill') }}</th>
                                        <th>{{ __('Their invoice') }}</th>
                                        <th>{{ __('Due') }}</th>
                                        <th class="ap-num">{{ __('Days overdue') }}</th>
                                        <th class="ap-num">{{ __('Owed') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report['suppliers'] as $s)
                                    @foreach($s['bills'] as $bill)
                                    <tr>
                                        <td>{{ $s['name'] }}</td>
                                        <td><a href="{{ route($route.'.show', $bill->id) }}">{{ $bill->number }}</a></td>
                                        <td>{{ $bill->supplier_reference ?? '—' }}</td>
                                        <td data-order="{{ $bill->due_date }}">{{ format_date($bill->due_date) }}</td>
                                        <td class="ap-num">{{ $bill->days_overdue }}</td>
                                        <td class="ap-num" data-order="{{ $bill->owed }}">{{ format_amount($bill->owed) }}</td>
                                    </tr>
                                    @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endunless

@unless($print)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endunless

@endsection
