@extends('admin.layouts.master')
@section('title', __('fund_transfers'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('fund_transfers') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.payment-account-transfer.create') }}" class="btn btn-info btn-sm">
                                <i class="fas fa-exchange-alt"></i> {{ __('new_transfer') }}
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
                                        <th width="10%">{{ __('date') }}</th>
                                        <th>{{ __('from_account') }}</th>
                                        <th>{{ __('to_account') }}</th>
                                        <th width="12%">{{ __('amount') }}</th>
                                        <th>{{ __('note') }}</th>
                                        <th width="10%">{{ __('created_by') }}</th>
                                        <th width="10%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transfers as $key => $transfer)
                                    <tr>
                                        <td>{{ $transfers->firstItem() + $key }}</td>
                                        <td>{{ date('d M Y', strtotime($transfer->transfer_date)) }}</td>
                                        <td>
                                            <strong>{{ $transfer->fromAccount->title }}</strong><br>
                                            <small class="text-muted">{{ $transfer->fromAccount->accountType->title }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $transfer->toAccount->title }}</strong><br>
                                            <small class="text-muted">{{ $transfer->toAccount->accountType->title }}</small>
                                        </td>
                                        <td class="text-right">
                                            <strong class="text-primary">{{ number_format($transfer->amount, 2) }}</strong>
                                        </td>
                                        <td>{{ Str::limit($transfer->note, 50) }}</td>
                                        <td>
                                            @if($transfer->creator)
                                            {{ $transfer->creator->name }}
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                @if($transfer->attach)
                                                <a href="{{ asset($transfer->attach) }}" target="_blank" 
                                                   class="btn btn-sm btn-info" title="{{ __('view_attachment') }}">
                                                    <i class="fas fa-paperclip"></i>
                                                </a>
                                                @endif
                                                @can('budget-view')
                                                <a href="{{ route('admin.payment-account-transfer.edit', $transfer->id) }}" 
                                                   class="btn btn-sm btn-primary" title="{{ __('edit') }}">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.payment-account-transfer.destroy', $transfer->id) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('{{ __('are_you_sure_delete_transfer') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="{{ __('delete') }}">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('no_transfers_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- /.card-body -->
                    <div class="card-footer">
                        {{ $transfers->links() }}
                    </div>
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
@endsection
