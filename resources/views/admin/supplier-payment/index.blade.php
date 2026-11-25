@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@include('admin.supplier-bill.partials.style')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
                        <h5 class="mb-0">{{ $title }}</h5>
                        @can('supplier-payment-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-money-check-alt"></i> {{ __('Pay a supplier') }}</a>
                        @endcan
                    </div>
                    <div class="card-block">
                        @include('admin.supplier-bill.partials.nav')

                        <form method="get" class="ap-filters mt-3 mb-3">
                            <div class="form-group">
                                <label for="supplier_id">{{ __('Supplier') }}</label>
                                <select name="supplier_id" id="supplier_id" class="form-control select2">
                                    <option value="">{{ __('All suppliers') }}</option>
                                    @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="from">{{ __('From') }}</label>
                                <input type="date" name="from" id="from" class="form-control" value="{{ request('from') }}">
                            </div>
                            <div class="form-group">
                                <label for="to">{{ __('To') }}</label>
                                <input type="date" name="to" id="to" class="form-control" value="{{ request('to') }}">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
                                <a href="{{ route($route.'.index') }}" class="btn btn-light">{{ __('Clear') }}</a>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table id="export-table" class="display table table-striped table-hover ap-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('Number') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Supplier') }}</th>
                                        <th>{{ __('Bills paid') }}</th>
                                        <th>{{ __('Paid from') }}</th>
                                        <th>{{ __('Reference') }}</th>
                                        <th class="ap-num">{{ __('Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $p)
                                    <tr>
                                        <td><a href="{{ route($route.'.show', $p->id) }}">{{ $p->number }}</a></td>
                                        <td data-order="{{ $p->payment_date->format('Y-m-d') }}">{{ format_date($p->payment_date) }}</td>
                                        <td>{{ $p->supplier->title ?? '—' }}</td>
                                        <td>{{ $p->allocations->map(fn ($a) => $a->bill->number ?? '')->filter()->implode(', ') }}</td>
                                        <td>{{ $p->cashAccount ? $p->cashAccount->account_code.' '.$p->cashAccount->account_name : '—' }}</td>
                                        <td>{{ $p->reference ?: '—' }}</td>
                                        <td class="ap-num" data-order="{{ $p->amount }}">{{ format_amount($p->amount) }}</td>
                                        <td>@if($p->isVoid())<span class="ap-chip void">{{ __('Void') }}</span>@else<span class="ap-chip posted">{{ __('Posted') }}</span>@endif</td>
                                        <td><a href="{{ route($route.'.show', $p->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Open') }}"><i class="fas fa-eye"></i></a></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
