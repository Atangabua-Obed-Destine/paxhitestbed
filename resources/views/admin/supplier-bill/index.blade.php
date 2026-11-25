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
                        <div class="ap-toolbar">
                            @can('supplier-bill-create')
                            <a href="{{ route($route.'.create') }}" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> {{ __('Enter a bill') }}</a>
                            @endcan
                            @can('supplier-payment-create')
                            <a href="{{ route('admin.supplier-payment.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-money-check-alt"></i> {{ __('Pay a supplier') }}</a>
                            @endcan
                        </div>
                    </div>
                    <div class="card-block">
                        @include('admin.supplier-bill.partials.nav')
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card stat-tile stat-primary">
                    <div class="card-body">
                        <div class="stat-figure">{{ format_amount($total_owed) }}</div>
                        <div class="stat-label">{{ __('Owed to suppliers') }}</div>
                        <div class="stat-note">{{ __('Approved bills less payments') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-tile {{ $overdue_owed > 0 ? 'stat-danger' : 'stat-success' }}">
                    <div class="card-body">
                        <div class="stat-figure">{{ format_amount($overdue_owed) }}</div>
                        <div class="stat-label">{{ __('Overdue') }}</div>
                        <div class="stat-note">{{ __('Past the due date and not yet paid') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-tile {{ $drafts > 0 ? 'stat-warning' : 'stat-info' }}">
                    <div class="card-body">
                        <div class="stat-figure">{{ $drafts }}</div>
                        <div class="stat-label">{{ __('Drafts awaiting approval') }}</div>
                        <div class="stat-note">{{ __('A draft posts nothing to the ledger') }}</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <form method="get" action="{{ route($route.'.index') }}" class="ap-filters mb-3">
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
                                <label for="status">{{ __('Status') }}</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach(['draft' => __('Draft'), 'unpaid' => __('Unpaid'), 'part_paid' => __('Part paid'), 'paid' => __('Paid'), 'overdue' => __('Overdue'), 'void' => __('Void')] as $k => $v)
                                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="from">{{ __('Bill date from') }}</label>
                                <input type="date" name="from" id="from" class="form-control" value="{{ request('from') }}">
                            </div>
                            <div class="form-group">
                                <label for="to">{{ __('to') }}</label>
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
                                        <th>{{ __('Supplier') }}</th>
                                        <th>{{ __('Their invoice') }}</th>
                                        <th>{{ __('Bill date') }}</th>
                                        <th>{{ __('Due') }}</th>
                                        <th class="ap-num">{{ __('Total') }}</th>
                                        <th class="ap-num">{{ __('Owed') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $bill)
                                    <tr>
                                        <td><a href="{{ route($route.'.show', $bill->id) }}">{{ $bill->number }}</a>
                                            @if($bill->isOpeningBalance())<div class="ap-muted">{{ __('Opening balance') }}</div>@endif
                                        </td>
                                        <td>{{ $bill->supplier->title ?? '—' }}</td>
                                        <td>{{ $bill->supplier_reference ?? '—' }}</td>
                                        <td data-order="{{ $bill->bill_date->format('Y-m-d') }}">{{ format_date($bill->bill_date) }}</td>
                                        <td data-order="{{ $bill->due_date->format('Y-m-d') }}">{{ format_date($bill->due_date) }}</td>
                                        <td class="ap-num" data-order="{{ $bill->total }}">{{ format_amount($bill->total) }}</td>
                                        <td class="ap-num" data-order="{{ $bill->owed }}">{{ $bill->isApproved() ? format_amount($bill->owed) : '—' }}</td>
                                        <td>@include('admin.supplier-bill.partials.chip', ['state' => $bill->state, 'overdue' => $bill->overdue])</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route($route.'.show', $bill->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Open') }}"><i class="fas fa-eye"></i></a>
                                            @if($bill->isApproved() && $bill->owed > 0.005)
                                            @can('supplier-payment-create')
                                            <a href="{{ route('admin.supplier-payment.create', ['supplier_id' => $bill->supplier_id, 'bill_id' => $bill->id]) }}" class="btn btn-sm btn-outline-success" title="{{ __('Pay') }}"><i class="fas fa-money-bill-wave"></i></a>
                                            @endcan
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @can('supplier-bill-approve')
                <div class="card">
                    <div class="card-block">
                        <form method="post" action="{{ route($route.'.settings') }}" class="ap-filters">
                            @csrf
                            <div class="form-group" style="min-width:320px">
                                <label for="payable_account_id">{{ __('Supplier debts are owed on') }}</label>
                                <select name="payable_account_id" id="payable_account_id" class="form-control">
                                    @foreach($payable_accounts as $acc)
                                    <option value="{{ $acc->id }}" @selected($payable_account_id == $acc->id)>{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-outline-primary">{{ __('btn_save') }}</button>
                            </div>
                            <div class="ap-muted" style="max-width:520px">{{ __('Applies to bills approved from now on. Bills already approved keep the account they were posted to.') }}</div>
                        </form>
                    </div>
                </div>
                @endcan
            </div>
        </div>
    </div>
</div>

@endsection
