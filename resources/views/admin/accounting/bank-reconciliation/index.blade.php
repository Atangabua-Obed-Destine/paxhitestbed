@extends('admin.layouts.master')
@section('title', __('bank_reconciliation'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('bank_reconciliation') }} ({{ __('rapprochement_bancaire') }})</h3>
                        <div class="card-tools">
                            @can('bank-reconciliation-create')
                            <a href="{{ route('admin.bank-reconciliation.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('new_reconciliation') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Statistics -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['total'] ?? 0 }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('total_reconciliations') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['in_progress'] ?? 0 }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('in_progress') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-spinner"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['completed'] ?? 0 }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('completed') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ number_format($statistics['unreconciled_amount'] ?? 0, 0, ',', ' ') }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('unreconciled_amount') }} (FCFA)</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <form action="{{ route('admin.bank-reconciliation.index') }}" method="GET">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="bank_account_id">{{ __('bank_account') }}</label>
                                                <select name="bank_account_id" id="bank_account_id" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
                                                    @foreach($bankAccounts as $account)
                                                    <option value="{{ $account->id }}" {{ request('bank_account_id') == $account->id ? 'selected' : '' }}>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="status">{{ __('status_title') }}</label>
                                                <select name="status" id="status" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
                                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ __('draft') }}</option>
                                                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>{{ __('in_progress') }}</option>
                                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('completed') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="month">{{ __('month') }}</label>
                                                <input type="month" name="month" id="month" class="form-control form-control-sm" value="{{ request('month') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                    <i class="fas fa-filter"></i> {{ __('filter') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Reconciliations Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th>{{ __('bank_account') }}</th>
                                        <th width="10%">{{ __('statement_date') }}</th>
                                        <th width="12%" class="text-right">{{ __('statement_balance') }}</th>
                                        <th width="12%" class="text-right">{{ __('book_balance') }}</th>
                                        <th width="12%" class="text-right">{{ __('difference') }}</th>
                                        <th width="8%">{{ __('status_title') }}</th>
                                        <th width="12%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reconciliations as $key => $reconciliation)
                                    <tr>
                                        <td>{{ $reconciliations->firstItem() + $key }}</td>
                                        <td>
                                            <strong>{{ $reconciliation->bankAccount->account_code ?? 'N/A' }}</strong>
                                            <br><small>{{ $reconciliation->bankAccount->account_name ?? '' }}</small>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($reconciliation->statement_date)->format('d M Y') }}</td>
                                        <td class="text-right">{{ number_format($reconciliation->statement_ending_balance, 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($reconciliation->book_balance, 0, ',', ' ') }}</td>
                                        <td class="text-right">
                                            @php
                                                $diff = $reconciliation->statement_ending_balance - $reconciliation->adjusted_book_balance;
                                            @endphp
                                            <span class="{{ abs($diff) < 1 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($diff, 0, ',', ' ') }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($reconciliation->status == 'completed')
                                            <span class="badge badge-success">{{ __('completed') }}</span>
                                            @elseif($reconciliation->status == 'in_progress')
                                            <span class="badge badge-warning">{{ __('in_progress') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __('draft') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('bank-reconciliation-view')
                                            <a href="{{ route('admin.bank-reconciliation.show', $reconciliation->id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            
                                            @if($reconciliation->status != 'completed')
                                            @can('bank-reconciliation-edit')
                                            <a href="{{ route('admin.bank-reconciliation.edit', $reconciliation->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('bank-reconciliation-complete')
                                            <form action="{{ route('admin.bank-reconciliation.complete', $reconciliation->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('complete') }}" onclick="return confirm('{{ __('confirm_complete_reconciliation') }}')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            @endcan
                                            
                                            @can('bank-reconciliation-delete')
                                            <form action="{{ route('admin.bank-reconciliation.destroy', $reconciliation->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('{{ __('are_you_sure') }}')" title="{{ __('delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endcan
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $reconciliations->appends(request()->query())->links() }}
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection
