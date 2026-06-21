@extends('admin.layouts.master')
@section('title', __('year_end_closing_details'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            {{ __('year_end_closing') }}: {{ $closing->fiscalYear->name ?? 'N/A' }}
                            <span class="badge badge-{{ $closing->status == 'approved' ? 'success' : ($closing->status == 'pending_approval' ? 'warning' : ($closing->status == 'in_progress' ? 'info' : 'secondary')) }}">
                                {{ __($closing->status) }}
                            </span>
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.year-end-closing.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Summary -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('fiscal_year') }}</span>
                                        <span class="info-box-number">{{ $closing->fiscalYear->name ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box bg-primary">
                                    <span class="info-box-icon"><i class="fas fa-calendar-check"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('closing_date') }}</span>
                                        <span class="info-box-number">{{ \Carbon\Carbon::parse($closing->closing_date)->format('d M Y') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box {{ ($closing->net_income ?? 0) >= 0 ? 'bg-success' : 'bg-danger' }}">
                                    <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('net_income') }}</span>
                                        <span class="info-box-number">{{ number_format($closing->net_income ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-user"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('prepared_by') }}</span>
                                        <span class="info-box-number">{{ $closing->preparedBy->name ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Checklist -->
                        <div class="card mt-3">
                            <div class="card-header bg-secondary">
                                <h5 class="card-title mb-0"><i class="fas fa-tasks"></i> {{ __('closing_checklist') }}</h5>
                            </div>
                            <div class="card-body">
                                @php
                                    $checklist = $closing->checklist ?? [];
                                @endphp
                                
                                @if(count($checklist) > 0)
                                <form action="{{ route('admin.year-end-closing.update-checklist', $closing->id) }}" method="POST">
                                    @csrf
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th width="5%">{{ __('done') }}</th>
                                                <th>{{ __('task') }}</th>
                                                <th width="15%">{{ __('completed_by') }}</th>
                                                <th width="15%">{{ __('completed_at') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($checklist as $key => $item)
                                            <tr class="{{ ($item['completed'] ?? false) ? 'table-success' : '' }}">
                                                <td class="text-center">
                                                    @if($closing->status == 'in_progress')
                                                    <input type="checkbox" name="checklist[{{ $key }}][completed]" value="1" 
                                                           {{ ($item['completed'] ?? false) ? 'checked' : '' }}
                                                           class="form-check-input">
                                                    <input type="hidden" name="checklist[{{ $key }}][task]" value="{{ $item['task'] }}">
                                                    @else
                                                    <i class="fas fa-{{ ($item['completed'] ?? false) ? 'check-circle text-success' : 'circle text-muted' }}"></i>
                                                    @endif
                                                </td>
                                                <td>{{ $item['task'] }}</td>
                                                <td>{{ $item['completed_by'] ?? '-' }}</td>
                                                <td>{{ isset($item['completed_at']) ? \Carbon\Carbon::parse($item['completed_at'])->format('d/m/Y H:i') : '-' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    
                                    @if($closing->status == 'in_progress')
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> {{ __('save_checklist') }}
                                    </button>
                                    @endif
                                </form>
                                @else
                                <p class="text-muted">{{ __('no_checklist_items') }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Trial Balance Summary -->
                        @if($closing->trial_balance_summary)
                        <div class="card mt-3">
                            <div class="card-header bg-info">
                                <h5 class="card-title mb-0"><i class="fas fa-balance-scale"></i> {{ __('trial_balance_summary') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>{{ __('total_assets') }}</th>
                                                <td class="text-right">{{ number_format($closing->trial_balance_summary['total_assets'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('total_liabilities') }}</th>
                                                <td class="text-right">{{ number_format($closing->trial_balance_summary['total_liabilities'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('total_equity') }}</th>
                                                <td class="text-right">{{ number_format($closing->trial_balance_summary['total_equity'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>{{ __('total_revenue') }}</th>
                                                <td class="text-right">{{ number_format($closing->trial_balance_summary['total_revenue'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('total_expenses') }}</th>
                                                <td class="text-right">{{ number_format($closing->trial_balance_summary['total_expenses'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <th>{{ __('net_income') }}</th>
                                                <td class="text-right"><strong>{{ number_format($closing->net_income ?? 0, 0, ',', ' ') }} FCFA</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Closing Entries -->
                        @if($closing->closing_journal_entry_id || $closing->opening_journal_entry_id)
                        <div class="card mt-3">
                            <div class="card-header bg-success">
                                <h5 class="card-title mb-0"><i class="fas fa-file-invoice"></i> {{ __('closing_entries') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @if($closing->closing_journal_entry_id)
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">{{ __('closing_entry') }}</div>
                                            <div class="card-body">
                                                <p><strong>{{ __('entry_id') }}:</strong> {{ $closing->closing_journal_entry_id }}</p>
                                                <a href="{{ route('admin.journal-entries.show', $closing->closing_journal_entry_id) }}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> {{ __('view_entry') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    
                                    @if($closing->opening_journal_entry_id)
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">{{ __('opening_entry') }}</div>
                                            <div class="card-body">
                                                <p><strong>{{ __('entry_id') }}:</strong> {{ $closing->opening_journal_entry_id }}</p>
                                                <a href="{{ route('admin.journal-entries.show', $closing->opening_journal_entry_id) }}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> {{ __('view_entry') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Notes -->
                        @if($closing->notes)
                        <div class="card mt-3">
                            <div class="card-header bg-secondary">
                                <h5 class="card-title mb-0"><i class="fas fa-sticky-note"></i> {{ __('notes') }}</h5>
                            </div>
                            <div class="card-body">
                                {{ $closing->notes }}
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="mt-4">
                            @if($closing->status == 'in_progress')
                            @php
                                $allCompleted = collect($closing->checklist ?? [])->every(fn($item) => $item['completed'] ?? false);
                            @endphp
                            @if($allCompleted)
                            @can('year-end-closing-process')
                            <form action="{{ route('admin.year-end-closing.generate-entries', $closing->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success" onclick="return confirm('{{ __('confirm_generate_entries') }}')">
                                    <i class="fas fa-cogs"></i> {{ __('generate_closing_entries') }}
                                </button>
                            </form>
                            @endcan
                            @else
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> {{ __('complete_checklist_first') }}
                            </div>
                            @endif
                            @endif
                            
                            @if($closing->status == 'pending_approval')
                            @can('year-end-closing-approve')
                            <form action="{{ route('admin.year-end-closing.approve', $closing->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success" onclick="return confirm('{{ __('confirm_approve_closing') }}')">
                                    <i class="fas fa-check"></i> {{ __('approve_closing') }}
                                </button>
                            </form>
                            @endcan
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
