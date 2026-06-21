@extends('admin.layouts.master')
@section('title', __('fiscal_years'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('fiscal_years') }} ({{ __('exercices_comptables') }})</h3>
                        <div class="card-tools">
                            @can('accounting-period-create')
                            <a href="{{ route('admin.fiscal-years.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('add_fiscal_year') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th>{{ __('fiscal_year_name') }}</th>
                                        <th>{{ __('start_date') }}</th>
                                        <th>{{ __('end_date') }}</th>
                                        <th>{{ __('duration') }}</th>
                                        <th width="10%">{{ __('periods') }}</th>
                                        <th width="10%">{{ __('status_title') }}</th>
                                        <th width="20%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($fiscalYears as $key => $year)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <strong>{{ $year->name }}</strong>
                                            @if($year->is_active)
                                            <span class="badge badge-success ml-2">{{ __('active') }}</span>
                                            @endif
                                            @if($year->is_closed)
                                            <span class="badge badge-danger ml-2">{{ __('closed') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($year->start_date)->format('d M Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($year->end_date)->format('d M Y') }}</td>
                                        <td>{{ $year->duration_in_months }} {{ __('months') }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-info">{{ $year->accountingPeriods->count() }}</span>
                                        </td>
                                        <td>
                                            @if($year->is_closed)
                                            <span class="badge badge-danger">{{ __('closed') }}</span>
                                            @elseif($year->is_active)
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __('inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('accounting-period-view')
                                            <a href="{{ route('admin.fiscal-years.show', $year->id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('accounting-period-edit')
                                            @if(!$year->is_closed)
                                            <a href="{{ route('admin.fiscal-years.edit', $year->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            
                                            @can('accounting-period-create')
                                            @if($year->accountingPeriods->count() == 0)
                                            <form action="{{ route('admin.fiscal-years.generate-periods', $year->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('generate_periods') }}" onclick="return confirm('{{ __('generate_12_monthly_periods') }}?')">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                            
                                            @if(!$year->is_active && !$year->is_closed)
                                            <form action="{{ route('admin.fiscal-years.set-active', $year->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-xs" title="{{ __('set_active') }}" onclick="return confirm('{{ __('set_as_active_year') }}?')">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                            @endif
                                            
                                            @if(!$year->is_closed && $year->accountingPeriods->count() > 0)
                                            <form action="{{ route('admin.fiscal-years.close', $year->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-danger btn-xs" title="{{ __('close_year') }}" onclick="return confirm('{{ __('confirm_close_fiscal_year') }}')">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            </form>
                                            @endif
                                            
                                            @if($year->is_closed)
                                            <form action="{{ route('admin.fiscal-years.reopen', $year->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('reopen_year') }}" onclick="return confirm('{{ __('confirm_reopen_fiscal_year') }}')">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            </form>
                                            @endif
                                            
                                            @if(!$year->is_closed && $year->journalEntries->count() == 0)
                                            <form action="{{ route('admin.fiscal-years.destroy', $year->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('{{ __('are_you_sure') }}')" title="{{ __('delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
