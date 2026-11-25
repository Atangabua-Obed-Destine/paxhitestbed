@extends($print ? 'admin.supplier-bill.print' : 'admin.layouts.master')
@section('title', $title)
@section('content')

@unless($print)
@include('admin.supplier-bill.partials.style')
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header"><h5>{{ $title }}</h5></div>
                    <div class="card-block">
                        @include('admin.supplier-bill.partials.nav')
                        <form method="get" class="ap-filters mt-3">
                            <div class="form-group" style="min-width:260px">
                                <label for="supplier_id">{{ __('Supplier') }} <span>*</span></label>
                                <select name="supplier_id" id="supplier_id" class="form-control select2" required>
                                    <option value="">{{ __('select') }}</option>
                                    @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(optional($supplier)->id == $s->id)>{{ $s->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="from">{{ __('From') }}</label>
                                <input type="date" name="from" id="from" class="form-control" value="{{ request('from') }}">
                            </div>
                            <div class="form-group">
                                <label for="to">{{ __('To') }}</label>
                                <input type="date" name="to" id="to" class="form-control" value="{{ request('to', now()->toDateString()) }}">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('Show') }}</button>
                                @if($statement)
                                <a href="{{ route($route.'.statement', array_merge(request()->only(['supplier_id', 'from', 'to']), ['print' => 1])) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print"></i> {{ __('btn_print') }}</a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
@endunless

@if(!$statement)
    <p class="ap-muted">{{ __('Choose a supplier to see their account: every bill, payment and correction, with a running balance.') }}</p>
@else
    @if($print)
    <h1 class="doc-title">{{ __('Supplier Statement') }}</h1>
    @endif
    <div class="{{ $print ? 'doc-sub' : 'mb-3' }}">
        <strong>{{ $supplier->title }}</strong>
        @if($supplier->tax_number) · {{ __('NIU') }} {{ $supplier->tax_number }} @endif
        <br>{{ $statement['from'] ? format_date($statement['from']) : __('From the first entry') }} — {{ format_date($statement['to']) }}
    </div>

    <div class="table-responsive">
        <table class="table ap-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Document') }}</th>
                    <th>{{ __('Their reference') }}</th>
                    <th>{{ __('Details') }}</th>
                    <th class="ap-num">{{ __('Billed') }}</th>
                    <th class="ap-num">{{ __('Paid') }}</th>
                    <th class="ap-num">{{ __('Balance owed') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td></td><td></td><td></td>
                    <td><em>{{ __('Balance brought forward') }}</em></td>
                    <td></td><td></td>
                    <td class="ap-num">{{ format_amount($statement['opening']) }}</td>
                </tr>
                @foreach($statement['rows'] as $r)
                <tr>
                    <td>{{ format_date($r['date']) }}</td>
                    <td>
                        @if($print){{ $r['number'] }}@else
                        <a href="{{ in_array($r['type'], ['bill', 'bill_void']) ? route($route.'.show', $r['id']) : route('admin.supplier-payment.show', $r['id']) }}">{{ $r['number'] }}</a>
                        @endif
                    </td>
                    <td>{{ $r['reference'] ?: '—' }}</td>
                    <td>{{ $r['description'] }}@if($r['due_date'])<div class="ap-muted">{{ __('Due') }} {{ format_date($r['due_date']) }}</div>@endif</td>
                    <td class="ap-num">{{ $r['billed'] != 0 ? format_amount($r['billed']) : '' }}</td>
                    <td class="ap-num">{{ $r['paid'] != 0 ? format_amount($r['paid']) : '' }}</td>
                    <td class="ap-num">{{ format_amount($r['balance']) }}</td>
                </tr>
                @endforeach
                <tr class="ap-total-row">
                    <td colspan="4">{{ __('Totals for the period, and balance owed') }}</td>
                    <td class="ap-num">{{ format_amount($statement['billed']) }}</td>
                    <td class="ap-num">{{ format_amount($statement['paid']) }}</td>
                    <td class="ap-num">{{ format_amount($statement['closing']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif

@unless($print)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endunless

@endsection
