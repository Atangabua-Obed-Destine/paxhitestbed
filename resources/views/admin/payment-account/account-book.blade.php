@extends('admin.layouts.master')
@section('title', __('account_book'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <!-- Account Info Card -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('account_book') }}: {{ $payment_account->title }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.payment-account.index') }}" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <p><strong>{{ __('account_type') }}:</strong> {{ $payment_account->accountType->title }}</p>
                                <p><strong>{{ __('account_number') }}:</strong> {{ $payment_account->account_number ?? '-' }}</p>
                            </div>
                            <div class="col-md-3">
                                <p><strong>{{ __('opening_balance') }}:</strong> {{ number_format($payment_account->opening_balance, 2) }}</p>
                                <p><strong>{{ __('current_balance') }}:</strong> 
                                    <span class="badge {{ $payment_account->current_balance < 0 ? 'badge-danger' : 'badge-success' }} font-size-16">
                                        {{ number_format($payment_account->current_balance, 2) }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="{{ route('admin.payment-account.deposit', $payment_account->id) }}" class="btn btn-success">
                                    <i class="fas fa-plus-circle"></i> {{ __('deposit') }}
                                </a>
                                <a href="{{ route('admin.payment-account.withdraw', $payment_account->id) }}" class="btn btn-warning">
                                    <i class="fas fa-minus-circle"></i> {{ __('withdraw') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('filter_transactions') }}</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.payment-account.account-book', $payment_account->id) }}" method="GET">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date_from">{{ __('date_from') }}</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from" 
                                               value="{{ request('date_from') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date_to">{{ __('date_to') }}</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to" 
                                               value="{{ request('date_to') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="transaction_type">{{ __('transaction_type') }}</label>
                                        <select class="form-control" id="transaction_type" name="transaction_type">
                                            <option value="">{{ __('all') }}</option>
                                            <option value="credit" {{ request('transaction_type') == 'credit' ? 'selected' : '' }}>
                                                {{ __('credit') }}
                                            </option>
                                            <option value="debit" {{ request('transaction_type') == 'debit' ? 'selected' : '' }}>
                                                {{ __('debit') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label><br>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter"></i> {{ __('filter') }}
                                        </button>
                                        <a href="{{ route('admin.payment-account.account-book', $payment_account->id) }}" 
                                           class="btn btn-secondary">
                                            <i class="fas fa-redo"></i> {{ __('reset') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('transactions') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th width="10%">{{ __('date') }}</th>
                                        <th>{{ __('title') }}</th>
                                        <th>{{ __('description') }}</th>
                                        <th width="8%">{{ __('type') }}</th>
                                        <th width="10%" class="text-right">{{ __('debit') }}</th>
                                        <th width="10%" class="text-right">{{ __('credit') }}</th>
                                        <th width="12%" class="text-right">{{ __('balance') }}</th>
                                        <th width="10%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $key => $transaction)
                                    <tr>
                                        <td>{{ $transactions->firstItem() + $key }}</td>
                                        <td>{{ date('d M Y', strtotime($transaction->transaction_date)) }}</td>
                                        <td>{{ $transaction->title }}</td>
                                        <td>
                                            {{ Str::limit($transaction->description, 50) }}
                                            @if($transaction->reference_type)
                                            <br><small class="badge badge-secondary">{{ ucfirst($transaction->reference_type) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($transaction->transaction_type == 'debit')
                                            <span class="badge badge-danger">{{ __('debit') }}</span>
                                            @else
                                            <span class="badge badge-success">{{ __('credit') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($transaction->transaction_type == 'debit')
                                            <span class="text-danger">{{ number_format($transaction->amount, 2) }}</span>
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($transaction->transaction_type == 'credit')
                                            <span class="text-success">{{ number_format($transaction->amount, 2) }}</span>
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <strong>{{ number_format($transaction->balance_after, 2) }}</strong>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                @if($transaction->attach)
                                                <a href="{{ asset($transaction->attach) }}" target="_blank" 
                                                   class="btn btn-sm btn-info" title="{{ __('view_attachment') }}">
                                                    <i class="fas fa-paperclip"></i>
                                                </a>
                                                @endif
                                                
                                                @if($transaction->reference_type == 'transfer')
                                                    {{-- For transfers, redirect to transfer list --}}
                                                    <a href="{{ route('admin.payment-account-transfer.index') }}" 
                                                       class="btn btn-sm btn-secondary" title="{{ __('view_transfer') }}">
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </a>
                                                @else
                                                    {{-- For manual transactions (deposit/withdraw), allow edit/delete --}}
                                                    @can('budget-view')
                                                    <a href="{{ route('admin.payment-account.transaction.edit', $transaction->id) }}" 
                                                       class="btn btn-sm btn-primary" title="{{ __('edit') }}">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('admin.payment-account.transaction.delete', $transaction->id) }}" 
                                                          method="POST" class="d-inline" 
                                                          onsubmit="return confirm('{{ __('confirm_delete_transaction') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger" title="{{ __('delete') }}">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">{{ __('no_transactions_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if($transactions->count() > 0)
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="5" class="text-right">{{ __('total') }}:</th>
                                        <th class="text-right text-danger">
                                            {{ number_format($transactions->where('transaction_type', 'debit')->sum('amount'), 2) }}
                                        </th>
                                        <th class="text-right text-success">
                                            {{ number_format($transactions->where('transaction_type', 'credit')->sum('amount'), 2) }}
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                    <!-- /.card-body -->
                    <div class="card-footer">
                        {{ $transactions->appends(request()->query())->links() }}
                    </div>
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
@endsection
