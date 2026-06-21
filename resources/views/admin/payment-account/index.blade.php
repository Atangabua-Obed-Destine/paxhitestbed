@extends('admin.layouts.master')
@section('title', __('payment_accounts'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                @if($unlinked_count > 0)
                <div class="alert alert-danger alert-dismissible fade show" role="alert" style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center;">
                        <i class="feather icon-alert-circle" style="font-size: 1.5rem; margin-right: 10px;"></i>
                        <span>
                            <strong>{{ __('total') }} {{ $unlinked_count }} {{ __('payments_not_linked_with_any_account') }}.</strong>
                        </span>
                    </div>
                    <a href="{{ route('admin.payment-account-report.unlinked') }}" class="btn btn-light btn-sm" style="white-space: nowrap;">
                        {{ __('view_details') }}
                    </a>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="margin-left: 10px;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('payment_accounts') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.payment-account.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('add_payment_account') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th>{{ __('account_title') }}</th>
                                        <th>{{ __('account_number') }}</th>
                                        <th>{{ __('account_type') }}</th>
                                        <th width="12%">{{ __('opening_balance') }}</th>
                                        <th width="12%">{{ __('current_balance') }}</th>
                                        <th width="8%">{{ __('status_title') }}</th>
                                        <th width="15%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payment_accounts as $key => $account)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <strong>{{ $account->title }}</strong>
                                            @if($account->description)
                                            <br><small class="text-muted">{{ Str::limit($account->description, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $account->account_number ?? '-' }}</td>
                                        <td>
                                            <span class="badge badge-info">{{ $account->accountType->title }}</span>
                                        </td>
                                        <td class="text-right">{{ number_format($account->opening_balance, 2) }}</td>
                                        <td class="text-right">
                                            <strong class="{{ $account->current_balance < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($account->current_balance, 2) }}
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            @if($account->status == 1)
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ route('admin.payment-account.account-book', $account->id) }}" 
                                                   class="btn btn-sm btn-info" title="{{ __('account_book') }}">
                                                    <i class="fas fa-book"></i>
                                                </a>
                                                <a href="{{ route('admin.payment-account.deposit', $account->id) }}" 
                                                   class="btn btn-sm btn-success" title="{{ __('deposit_external_money') }}">
                                                    <i class="fas fa-plus-circle"></i>
                                                </a>
                                                <a href="{{ route('admin.payment-account.withdraw', $account->id) }}" 
                                                   class="btn btn-sm btn-warning" title="{{ __('withdraw_to_external') }}">
                                                    <i class="fas fa-minus-circle"></i>
                                                </a>
                                                <a href="{{ route('admin.payment-account-transfer.create') }}?from={{ $account->id }}" 
                                                   class="btn btn-sm btn-secondary" title="{{ __('transfer_to_another_account') }}">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </a>
                                                <a href="{{ route('admin.payment-account.edit', $account->id) }}" 
                                                   class="btn btn-sm btn-primary" title="{{ __('edit') }}">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.payment-account.destroy', $account->id) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('{{ __('are_you_sure') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="{{ __('delete') }}">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <th colspan="5" class="text-right">{{ __('total_balance') }}:</th>
                                        <th class="text-right">
                                            <strong class="text-primary">
                                                {{ number_format($payment_accounts->sum('current_balance'), 2) }}
                                            </strong>
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
@endsection
