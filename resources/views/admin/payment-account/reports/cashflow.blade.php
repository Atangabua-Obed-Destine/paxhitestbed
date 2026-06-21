@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        
                        <!-- Filters -->
                        <form method="GET" action="{{ route($route.'.cashflow') }}">
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label for="account_id">{{ __('field_payment_account') }}</label>
                                    <select class="form-control" name="account_id" id="account_id">
                                        <option value="">{{ __('all_accounts') }}</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" {{ (request('account_id') == $account->id) ? 'selected' : '' }}>
                                                {{ $account->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="date_from">{{ __('field_date_from') }}</label>
                                    <input type="date" class="form-control" name="date_from" id="date_from" value="{{ request('date_from') }}">
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="date_to">{{ __('field_date_to') }}</label>
                                    <input type="date" class="form-control" name="date_to" id="date_to" value="{{ request('date_to') }}">
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="transaction_type">{{ __('field_transaction_type') }}</label>
                                    <select class="form-control" name="transaction_type" id="transaction_type">
                                        <option value="">{{ __('all_types') }}</option>
                                        <option value="credit" {{ request('transaction_type') == 'credit' ? 'selected' : '' }}>{{ __('credit') }}</option>
                                        <option value="debit" {{ request('transaction_type') == 'debit' ? 'selected' : '' }}>{{ __('debit') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="payment_method">{{ __('field_payment_method') }}</label>
                                    <select class="form-control" name="payment_method" id="payment_method">
                                        <option value="">{{ __('select') }}</option>
                                        <option value="1" {{ request('payment_method') == '1' ? 'selected' : '' }}>{{ __('payment_method_card') }}</option>
                                        <option value="2" {{ request('payment_method') == '2' ? 'selected' : '' }}>{{ __('payment_method_cash') }}</option>
                                        <option value="3" {{ request('payment_method') == '3' ? 'selected' : '' }}>{{ __('payment_method_cheque') }}</option>
                                        <option value="4" {{ request('payment_method') == '4' ? 'selected' : '' }}>{{ __('payment_method_bank') }}</option>
                                        <option value="5" {{ request('payment_method') == '5' ? 'selected' : '' }}>{{ __('payment_method_e_wallet') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-filter"></i> {{ __('btn_filter') }}
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Summary Cards -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ __('total_credit') }}</h6>
                                        <h4>{!! $setting->currency_symbol !!}{{ number_format($summary['total_credit'], 2) }}</h4>
                                        <small>{{ __('money_in') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ __('total_debit') }}</h6>
                                        <h4>{!! $setting->currency_symbol !!}{{ number_format($summary['total_debit'], 2) }}</h4>
                                        <small>{{ __('money_out') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ __('net_flow') }}</h6>
                                        <h4>{!! $setting->currency_symbol !!}{{ number_format($summary['net_flow'], 2) }}</h4>
                                        <small>{{ __('credit_minus_debit') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ __('total_balance') }}</h6>
                                        <h4>{!! $setting->currency_symbol !!}{{ number_format($total_balance, 2) }}</h4>
                                        <small>{{ __('all_accounts') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transactions Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_date') }}</th>
                                        <th>{{ __('field_account') }}</th>
                                        <th>{{ __('field_description') }}</th>
                                        <th>{{ __('field_payment_method') }}</th>
                                        <th>{{ __('field_reference') }}</th>
                                        <th class="text-right">{{ __('field_debit') }}</th>
                                        <th class="text-right">{{ __('field_credit') }}</th>
                                        <th class="text-right">{{ __('field_account_balance') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $transaction)
                                    <tr>
                                        <td>{{ date('d-m-Y H:i', strtotime($transaction->transaction_date)) }}</td>
                                        <td>
                                            <span class="badge badge-info">{{ $transaction->paymentAccount->title }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $transaction->title }}</strong>
                                            @if($transaction->description)
                                            <br><small class="text-muted">{{ $transaction->description }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($transaction->payment_method == 1)
                                                {{ __('payment_method_card') }}
                                            @elseif($transaction->payment_method == 2)
                                                {{ __('payment_method_cash') }}
                                            @elseif($transaction->payment_method == 3)
                                                {{ __('payment_method_cheque') }}
                                            @elseif($transaction->payment_method == 4)
                                                {{ __('payment_method_bank') }}
                                            @elseif($transaction->payment_method == 5)
                                                {{ __('payment_method_e_wallet') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($transaction->reference_type)
                                                <span class="badge badge-secondary">{{ ucfirst($transaction->reference_type) }}</span>
                                                @if($transaction->reference_id)
                                                    <br><small>#{{ $transaction->reference_id }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">{{ __('manual') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($transaction->transaction_type == 'debit')
                                                <span class="text-danger">{!! $setting->currency_symbol !!}{{ number_format($transaction->amount, 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($transaction->transaction_type == 'credit')
                                                <span class="text-success">{!! $setting->currency_symbol !!}{{ number_format($transaction->amount, 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <strong>{!! $setting->currency_symbol !!}{{ number_format($transaction->balance_after, 2) }}</strong>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('no_data_available') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($transactions->hasPages())
                        <div class="mt-3">
                            {{ $transactions->appends(request()->query())->links() }}
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
