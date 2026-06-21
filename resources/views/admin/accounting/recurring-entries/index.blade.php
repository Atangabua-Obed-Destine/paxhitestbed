@extends('admin.layouts.master')
@section('title', __('recurring_entries'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('recurring_entries') }} ({{ __('ecritures_recurrentes') }})</h3>
                        <div class="card-tools">
                            @can('recurring-entries-process')
                            <form action="{{ route('admin.recurring-entries.process-all') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm mr-2" onclick="return confirm('{{ __('confirm_process_all') }}')">
                                    <i class="fas fa-play"></i> {{ __('process_all_due') }}
                                </button>
                            </form>
                            @endcan
                            @can('recurring-entries-create')
                            <a href="{{ route('admin.recurring-entries.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('add_recurring_entry') }}
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
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('total_templates') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-redo"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['active'] ?? 0 }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('active_templates') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['due_today'] ?? 0 }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('due_today') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ number_format($statistics['monthly_total'] ?? 0, 0, ',', ' ') }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('monthly_total') }} (FCFA)</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <form action="{{ route('admin.recurring-entries.index') }}" method="GET">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="frequency">{{ __('frequency') }}</label>
                                                <select name="frequency" id="frequency" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
                                                    <option value="daily" {{ request('frequency') == 'daily' ? 'selected' : '' }}>{{ __('daily') }}</option>
                                                    <option value="weekly" {{ request('frequency') == 'weekly' ? 'selected' : '' }}>{{ __('weekly') }}</option>
                                                    <option value="monthly" {{ request('frequency') == 'monthly' ? 'selected' : '' }}>{{ __('monthly') }}</option>
                                                    <option value="quarterly" {{ request('frequency') == 'quarterly' ? 'selected' : '' }}>{{ __('quarterly') }}</option>
                                                    <option value="yearly" {{ request('frequency') == 'yearly' ? 'selected' : '' }}>{{ __('yearly') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="status">{{ __('status_title') }}</label>
                                                <select name="status" id="status" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
                                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('active') }}</option>
                                                    <option value="paused" {{ request('status') == 'paused' ? 'selected' : '' }}>{{ __('paused') }}</option>
                                                    <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>{{ __('expired') }}</option>
                                                </select>
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

                        <!-- Templates Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th>{{ __('template_name') }}</th>
                                        <th width="10%">{{ __('frequency') }}</th>
                                        <th width="10%">{{ __('next_run') }}</th>
                                        <th width="10%" class="text-right">{{ __('amount') }}</th>
                                        <th width="8%">{{ __('occurrences') }}</th>
                                        <th width="8%">{{ __('status_title') }}</th>
                                        <th width="15%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($entries as $key => $entry)
                                    <tr>
                                        <td>{{ $entries->firstItem() + $key }}</td>
                                        <td>
                                            <strong>{{ $entry->template_name }}</strong>
                                            <br><small class="text-muted">{{ Str::limit($entry->description, 50) }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ __($entry->frequency) }}</span>
                                        </td>
                                        <td>
                                            @if($entry->next_run_date)
                                            {{ \Carbon\Carbon::parse($entry->next_run_date)->format('d M Y') }}
                                            @if($entry->next_run_date <= now())
                                            <br><span class="badge badge-danger">{{ __('overdue') }}</span>
                                            @endif
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <strong>{{ number_format($entry->lines->sum('debit_amount'), 0, ',', ' ') }}</strong>
                                        </td>
                                        <td class="text-center">
                                            {{ $entry->occurrences_created ?? 0 }}
                                            @if($entry->total_occurrences)
                                            / {{ $entry->total_occurrences }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($entry->status == 'active')
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @elseif($entry->status == 'paused')
                                            <span class="badge badge-warning">{{ __('paused') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __($entry->status) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('recurring-entries-view')
                                            <a href="{{ route('admin.recurring-entries.show', $entry->id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('recurring-entries-edit')
                                            <a href="{{ route('admin.recurring-entries.edit', $entry->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('recurring-entries-process')
                                            @if($entry->status == 'active')
                                            <form action="{{ route('admin.recurring-entries.process', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('process_now') }}" onclick="return confirm('{{ __('confirm_process') }}')">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                            
                                            @can('recurring-entries-edit')
                                            @if($entry->status == 'active')
                                            <form action="{{ route('admin.recurring-entries.pause', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-xs" title="{{ __('pause') }}">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </form>
                                            @elseif($entry->status == 'paused')
                                            <form action="{{ route('admin.recurring-entries.resume', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('resume') }}">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                            
                                            @can('recurring-entries-create')
                                            <form action="{{ route('admin.recurring-entries.duplicate', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary btn-xs" title="{{ __('duplicate') }}">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </form>
                                            @endcan
                                            
                                            @can('recurring-entries-delete')
                                            <form action="{{ route('admin.recurring-entries.destroy', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('{{ __('are_you_sure') }}')" title="{{ __('delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $entries->appends(request()->query())->links() }}
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
