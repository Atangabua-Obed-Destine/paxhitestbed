@extends('admin.layouts.master')
@section('title', __('chart_of_accounts'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('chart_of_accounts') }} ({{ __('plan_comptable') }})</h3>
                        <div class="card-tools">
                            @can('chart-of-accounts-create')
                            <a href="{{ route('admin.chart-of-accounts.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('add_account') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Statistics Cards -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['total_accounts'] }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('total_accounts') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-list"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['active_accounts'] }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('active_accounts') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['detail_accounts'] }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('detail_accounts') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ number_format($statistics['total_balance'], 0, ',', ' ') }} FCFA</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('total_balance') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter by Class -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.chart-of-accounts.index') }}" class="btn btn-sm {{ !request('class') ? 'btn-primary' : 'btn-outline-primary' }}">
                                        {{ __('all_classes') }}
                                    </a>
                                    @for($i = 1; $i <= 9; $i++)
                                    <a href="{{ route('admin.chart-of-accounts.index', ['class' => $i]) }}" class="btn btn-sm {{ request('class') == $i ? 'btn-primary' : 'btn-outline-primary' }}">
                                        {{ __('classe') }} {{ $i }}
                                    </a>
                                    @endfor
                                </div>
                            </div>
                        </div>

                        <!-- Accounts Tree -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="10%">{{ __('account_code') }}</th>
                                        <th>{{ __('account_name') }}</th>
                                        <th width="12%">{{ __('classe') }}</th>
                                        <th width="12%">{{ __('account_type') }}</th>
                                        <th width="10%">{{ __('normal_balance') }}</th>
                                        <th width="12%">{{ __('current_balance') }}</th>
                                        <th width="8%">{{ __('status_title') }}</th>
                                        <th width="15%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($accounts as $account)
                                    <tr class="account-row" data-level="{{ $account->level ?? 0 }}" style="background-color: {{ $account->account_category == 'heading' ? '#f8f9fa' : 'white' }};">
                                        <td>
                                            <strong>{{ $account->account_code }}</strong>
                                        </td>
                                        <td style="padding-left: {{ ($account->level ?? 0) * 20 }}px;">
                                            @if($account->account_category == 'heading')
                                            <i class="fas fa-folder text-warning"></i>
                                            @else
                                            <i class="fas fa-file text-info"></i>
                                            @endif
                                            <strong>{{ $account->account_name }}</strong>
                                            @if($account->account_name_fr)
                                            <br><small class="text-muted" style="padding-left: {{ ($account->level ?? 0) * 20 + 20 }}px;">{{ $account->account_name_fr }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ __('classe') }} {{ $account->class_number }}</span>
                                        </td>
                                        <td>{{ __(strtolower($account->account_type)) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $account->normal_balance == 'debit' ? 'success' : 'warning' }}">
                                                {{ __($account->normal_balance) }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <strong>{{ number_format($account->current_balance, 0, ',', ' ') }}</strong> FCFA
                                        </td>
                                        <td>
                                            @if($account->is_active)
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('chart-of-accounts-view')
                                            <a href="{{ route('admin.chart-of-accounts.show', $account->id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            @can('chart-of-accounts-edit')
                                            <a href="{{ route('admin.chart-of-accounts.edit', $account->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('chart-of-accounts-delete')
                                            @if(!$account->is_system)
                                            <form action="{{ route('admin.chart-of-accounts.destroy', $account->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('{{ __('are_you_sure') }}')" title="{{ __('delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
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

@push('scripts')
<script>
    $(document).ready(function() {
        // Add hover effect for hierarchical view
        $('.account-row').hover(
            function() {
                $(this).css('background-color', '#e3f2fd');
            },
            function() {
                var bgColor = $(this).data('level') == 0 || $(this).find('.fa-folder').length ? '#f8f9fa' : 'white';
                $(this).css('background-color', bgColor);
            }
        );
    });
</script>
@endpush
