@extends('admin.layouts.master')
@section('title', __('year_end_closing'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('year_end_closing') }} ({{ __('cloture_exercice') }})</h3>
                        <div class="card-tools">
                            @can('year-end-closing-create')
                            <a href="{{ route('admin.year-end-closing.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('new_year_end_closing') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Alert for Active Fiscal Year -->
                        @if($activeFiscalYear)
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>{{ __('active_fiscal_year') }}:</strong> {{ $activeFiscalYear->name }}
                            ({{ \Carbon\Carbon::parse($activeFiscalYear->start_date)->format('d M Y') }} - {{ \Carbon\Carbon::parse($activeFiscalYear->end_date)->format('d M Y') }})
                        </div>
                        @endif

                        <!-- Closings Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th>{{ __('fiscal_year') }}</th>
                                        <th width="12%">{{ __('closing_date') }}</th>
                                        <th width="10%">{{ __('checklist_progress') }}</th>
                                        <th width="12%" class="text-right">{{ __('net_income') }}</th>
                                        <th width="10%">{{ __('status_title') }}</th>
                                        <th width="15%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($closings as $key => $closing)
                                    <tr>
                                        <td>{{ $closings->firstItem() + $key }}</td>
                                        <td>
                                            <strong>{{ $closing->fiscalYear->name ?? 'N/A' }}</strong>
                                            <br>
                                            <small class="text-muted">
                                                {{ $closing->fiscalYear ? \Carbon\Carbon::parse($closing->fiscalYear->start_date)->format('d/m/Y') : '' }} - 
                                                {{ $closing->fiscalYear ? \Carbon\Carbon::parse($closing->fiscalYear->end_date)->format('d/m/Y') : '' }}
                                            </small>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($closing->closing_date)->format('d M Y') }}</td>
                                        <td>
                                            @php
                                                $checklist = $closing->checklist ?? [];
                                                $completed = collect($checklist)->filter(fn($item) => $item['completed'] ?? false)->count();
                                                $total = count($checklist);
                                                $percentage = $total > 0 ? ($completed / $total) * 100 : 0;
                                            @endphp
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-{{ $percentage == 100 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger') }}" 
                                                     role="progressbar" style="width: {{ $percentage }}%;">
                                                    {{ $completed }}/{{ $total }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <strong class="{{ ($closing->net_income ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($closing->net_income ?? 0, 0, ',', ' ') }} FCFA
                                            </strong>
                                        </td>
                                        <td>
                                            @if($closing->status == 'approved')
                                            <span class="badge badge-success">{{ __('approved') }}</span>
                                            @elseif($closing->status == 'pending_approval')
                                            <span class="badge badge-warning">{{ __('pending_approval') }}</span>
                                            @elseif($closing->status == 'in_progress')
                                            <span class="badge badge-info">{{ __('in_progress') }}</span>
                                            @elseif($closing->status == 'reversed')
                                            <span class="badge badge-danger">{{ __('reversed') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __('draft') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('year-end-closing-view')
                                            <a href="{{ route('admin.year-end-closing.show', $closing->id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            
                                            @if($closing->status == 'draft' || $closing->status == 'in_progress')
                                            @can('year-end-closing-edit')
                                            <a href="{{ route('admin.year-end-closing.edit', $closing->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('year-end-closing-process')
                                            @if($closing->status == 'draft')
                                            <form action="{{ route('admin.year-end-closing.start', $closing->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-xs" title="{{ __('start_closing') }}">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                            @endif
                                            
                                            @if($closing->status == 'in_progress' && $percentage == 100)
                                            <form action="{{ route('admin.year-end-closing.generate-entries', $closing->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('generate_closing_entries') }}" onclick="return confirm('{{ __('confirm_generate_entries') }}')">
                                                    <i class="fas fa-cogs"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                            @endif
                                            
                                            @if($closing->status == 'pending_approval')
                                            @can('year-end-closing-approve')
                                            <form action="{{ route('admin.year-end-closing.approve', $closing->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('approve') }}" onclick="return confirm('{{ __('confirm_approve_closing') }}')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            @endcan
                                            @endif
                                            
                                            @if($closing->status == 'approved')
                                            @can('year-end-closing-reverse')
                                            <form action="{{ route('admin.year-end-closing.reverse', $closing->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-danger btn-xs" title="{{ __('reverse') }}" onclick="return confirm('{{ __('confirm_reverse_closing') }}')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                            @endcan
                                            @endif
                                            
                                            @if($closing->status == 'draft')
                                            @can('year-end-closing-delete')
                                            <form action="{{ route('admin.year-end-closing.destroy', $closing->id) }}" method="POST" style="display: inline-block;">
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
                                        <td colspan="7" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $closings->links() }}
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
