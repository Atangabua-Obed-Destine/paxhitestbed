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
                        <h5 class="mb-0">{{ __('Bank reconciliation') }} <span class="ap-muted">({{ __('rapprochement bancaire') }})</span></h5>
                        @can('bank-reconciliation-create')
                        <a href="{{ route('admin.bank-reconciliation.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('New reconciliation') }}</a>
                        @endcan
                    </div>
                    <div class="card-block">
                        <p class="ap-muted mb-3">{{ __('Each month, match every bank, cash and mobile money account against its statement. A completed reconciliation is where the next one starts.') }}</p>

                        <h6 class="mb-2">{{ __('Accounts') }}</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm ap-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Account') }}</th>
                                        <th class="ap-num">{{ __('Balance in the books today') }}</th>
                                        <th>{{ __('Reconciled to') }}</th>
                                        <th class="ap-num">{{ __('Statement balance then') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($accounts as $a)
                                    @php $l = $last[$a->id]; @endphp
                                    <tr>
                                        <td>{{ $a->account_code }} {{ $a->account_name }}</td>
                                        <td class="ap-num">{{ format_amount($l['book']) }}</td>
                                        <td>@if($l['last']){{ format_date($l['last']->statement_date) }}@else<span class="ap-chip unpaid">{{ __('Never reconciled') }}</span>@endif</td>
                                        <td class="ap-num">{{ $l['last'] ? format_amount($l['last']->statement_closing_balance) : '—' }}</td>
                                        <td class="text-right">
                                            @can('bank-reconciliation-create')
                                            <a href="{{ route('admin.bank-reconciliation.create', ['account_id' => $a->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('Reconcile') }}</a>
                                            @endcan
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <h6 class="mb-2">{{ __('Reconciliations') }}</h6>
                        <form method="get" class="ap-filters mb-3">
                            <div class="form-group">
                                <label for="account_id">{{ __('Account') }}</label>
                                <select name="account_id" id="account_id" class="form-control">
                                    <option value="">{{ __('All accounts') }}</option>
                                    @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->account_code }} {{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">{{ __('Status') }}</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">{{ __('All') }}</option>
                                    <option value="in_progress" @selected(request('status') === 'in_progress')>{{ __('In progress') }}</option>
                                    <option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped ap-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Account') }}</th>
                                        <th>{{ __('Statement date') }}</th>
                                        <th>{{ __('Reference') }}</th>
                                        <th class="ap-num">{{ __('Statement balance') }}</th>
                                        <th class="ap-num">{{ __('Balance in the books') }}</th>
                                        <th class="ap-num">{{ __('Difference') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $r)
                                    <tr>
                                        <td>{{ $r->chartOfAccount->account_code ?? '' }} {{ $r->chartOfAccount->account_name ?? '' }}</td>
                                        <td>{{ format_date($r->statement_date) }}</td>
                                        <td>{{ $r->statement_reference ?: '—' }}</td>
                                        <td class="ap-num">{{ format_amount($r->statement_closing_balance) }}</td>
                                        <td class="ap-num">{{ format_amount($r->book_balance) }}</td>
                                        <td class="ap-num">{{ format_amount($r->difference) }}</td>
                                        <td>
                                            @if(in_array($r->status, ['completed', 'approved']))
                                                <span class="ap-chip paid">{{ __('Completed') }}</span>
                                            @else
                                                <span class="ap-chip part_paid">{{ __('In progress') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right"><a href="{{ route('admin.bank-reconciliation.show', $r->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="8" class="ap-muted">{{ __('No reconciliation yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $rows->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
