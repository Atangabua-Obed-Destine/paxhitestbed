@extends('admin.layouts.master')
@section('title', __('fiscal_year_details'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('fiscal_year_details') }}</h3>
                        <div class="card-tools">
                            @can('accounting-period-edit')
                            @if(!$fiscalYear->is_closed)
                            <a href="{{ route('admin.fiscal-years.edit', $fiscalYear->id) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> {{ __('edit') }}
                            </a>
                            @endif
                            @endcan
                            <a href="{{ route('admin.fiscal-years.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <!-- Fiscal Year Information -->
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">{{ __('fiscal_year_name') }}</th>
                                        <td><strong>{{ $fiscalYear->name }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('start_date') }}</th>
                                        <td>{{ \Carbon\Carbon::parse($fiscalYear->start_date)->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('end_date') }}</th>
                                        <td>{{ \Carbon\Carbon::parse($fiscalYear->end_date)->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('duration') }}</th>
                                        <td>{{ $fiscalYear->duration_in_months }} {{ __('months') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('status_title') }}</th>
                                        <td>
                                            @if($fiscalYear->is_closed)
                                            <span class="badge badge-danger">{{ __('closed') }}</span>
                                            @elseif($fiscalYear->is_active)
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __('inactive') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($fiscalYear->is_closed)
                                    <tr>
                                        <th>{{ __('closed_by') }}</th>
                                        <td>{{ $fiscalYear->closedBy->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('closed_at') }}</th>
                                        <td>{{ $fiscalYear->closed_at ? \Carbon\Carbon::parse($fiscalYear->closed_at)->format('d M Y H:i') : '-' }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>

                            <!-- Statistics -->
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">{{ __('total_periods') }}</th>
                                        <td><strong>{{ $fiscalYear->accountingPeriods->count() }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('closed_periods') }}</th>
                                        <td>{{ $fiscalYear->accountingPeriods->where('is_closed', true)->count() }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('total_journal_entries') }}</th>
                                        <td>{{ $fiscalYear->journalEntries->count() }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('posted_entries') }}</th>
                                        <td>{{ $fiscalYear->journalEntries->where('is_posted', true)->count() }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('created_at') }}</th>
                                        <td>{{ $fiscalYear->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('updated_at') }}</th>
                                        <td>{{ $fiscalYear->updated_at->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Accounting Periods -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>{{ __('accounting_periods') }} ({{ $fiscalYear->accountingPeriods->count() }})</h5>
                                    @can('accounting-period-create')
                                    @if($fiscalYear->accountingPeriods->count() == 0 && !$fiscalYear->is_closed)
                                    <form action="{{ route('admin.fiscal-years.generate-periods', $fiscalYear->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('{{ __('generate_12_monthly_periods') }}?')">
                                            <i class="fas fa-calendar-plus"></i> {{ __('generate_periods') }}
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                </div>
                                
                                @if($fiscalYear->accountingPeriods->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th width="8%">{{ __('period_number') }}</th>
                                                <th>{{ __('period_name') }}</th>
                                                <th>{{ __('start_date') }}</th>
                                                <th>{{ __('end_date') }}</th>
                                                <th width="10%">{{ __('entries') }}</th>
                                                <th width="10%">{{ __('status_title') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($fiscalYear->accountingPeriods->sortBy('period_number') as $period)
                                            <tr>
                                                <td class="text-center"><strong>{{ $period->period_number }}</strong></td>
                                                <td>
                                                    <strong>{{ $period->name }}</strong>
                                                    @if($period->french_name && $period->french_name != $period->name)
                                                    <br><small class="text-muted">{{ $period->french_name }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($period->start_date)->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($period->end_date)->format('d M Y') }}</td>
                                                <td class="text-center">
                                                    <span class="badge badge-info">
                                                        {{ $period->journalEntries->count() }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($period->is_closed)
                                                    <span class="badge badge-danger">{{ __('closed') }}</span>
                                                    @else
                                                    <span class="badge badge-success">{{ __('open') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @else
                                <p class="text-muted">{{ __('no_periods_generated') }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>{{ __('quick_actions') }}</h5>
                                <div class="btn-group" role="group">
                                    @can('accounting-period-edit')
                                    @if(!$fiscalYear->is_active && !$fiscalYear->is_closed)
                                    <form action="{{ route('admin.fiscal-years.set-active', $fiscalYear->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('set_as_active_year') }}?')">
                                            <i class="fas fa-check-circle"></i> {{ __('set_active') }}
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                    
                                    @can('accounting-period-close')
                                    @if(!$fiscalYear->is_closed && $fiscalYear->accountingPeriods->count() > 0)
                                    <form action="{{ route('admin.fiscal-years.close', $fiscalYear->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('{{ __('confirm_close_fiscal_year') }}')">
                                            <i class="fas fa-lock"></i> {{ __('close_year') }}
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                    
                                    @can('accounting-period-reopen')
                                    @if($fiscalYear->is_closed)
                                    <form action="{{ route('admin.fiscal-years.reopen', $fiscalYear->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        <button type="submit" class="btn btn-success" onclick="return confirm('{{ __('confirm_reopen_fiscal_year') }}')">
                                            <i class="fas fa-unlock"></i> {{ __('reopen_year') }}
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                </div>
                            </div>
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
