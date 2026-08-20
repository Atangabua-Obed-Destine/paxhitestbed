@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Status Alert -->
            <div class="col-md-12">
                @if($row->status == 'draft')
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong><i class="feather icon-info"></i> Draft Budget</strong><br>
                    This budget is in draft mode. You can edit it and add allocations. When ready, submit it for approval.
                </div>
                @elseif($row->status == 'pending_approval')
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong><i class="feather icon-clock"></i> Pending Approval</strong><br>
                    This budget is waiting for approval. Once approved, it can be activated to start tracking expenses.
                </div>
                @elseif($row->status == 'approved')
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong><i class="feather icon-check-circle"></i> Approved</strong><br>
                    This budget has been approved. Click "Activate" below to start tracking expenses against this budget.
                </div>
                @elseif($row->status == 'active')
                <div class="alert alert-primary alert-dismissible fade show" role="alert">
                    <strong><i class="feather icon-activity"></i> Active Budget</strong><br>
                    This budget is currently active and tracking expenses. You can close it at the end of the fiscal year.
                </div>
                @elseif($row->status == 'closed')
                <div class="alert alert-secondary alert-dismissible fade show" role="alert">
                    <strong><i class="feather icon-lock"></i> Closed</strong><br>
                    This budget has been closed. No further changes can be made.
                </div>
                @elseif($row->status == 'cancelled')
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong><i class="feather icon-x-circle"></i> Cancelled</strong><br>
                    This budget has been cancelled and is no longer in use.
                </div>
                @endif
            </div>

            <!-- Budget Details -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('text_budget_details') }}</h5>
                        <div class="card-header-right">
                            @can('budget-edit')
                            @if($row->is_institutional)
                            {{-- One editor. The departmental form has no opening
                                 balance and no budget lines, so it cannot edit a sheet. --}}
                            <a href="{{ route('admin.budget-sheet.show', $row->id) }}" class="btn btn-primary btn-sm">
                                <i class="feather icon-file-text"></i> {{ __('Open the sheet') }}
                            </a>
                            @elseif(in_array($row->status, ['draft', 'pending_approval']))
                            <a href="{{ route('admin.budget.edit', $row->id) }}" class="btn btn-primary btn-sm">
                                <i class="feather icon-edit"></i> {{ __('btn_edit') }}
                            </a>
                            @elseif(in_array($row->status, ['approved', 'active']))
                            <button class="btn btn-secondary btn-sm" disabled title="Cannot edit an active or approved budget">
                                <i class="feather icon-lock"></i> {{ __('btn_edit') }} (Locked)
                            </button>
                            @endif
                            @endcan
                            
                            <a href="{{ route('admin.budget.index') }}" class="btn btn-secondary btn-sm">
                                <i class="feather icon-arrow-left"></i> {{ __('btn_back') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-block">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>{{ __('Belongs to') }}</strong></td>
                                <td>
                                    @if($row->parent)
                                        <a href="{{ route('admin.budget-sheet.show', $row->parent->id) }}">{{ $row->parent->title }}</a>
                                    @else
                                        <span class="text-muted">{{ __('Not part of an annual budget') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th style="width: 200px;">{{ __('field_budget_code') }}:</th>
                                <td><strong>{{ $row->budget_code }}</strong></td>
                            </tr>
                            <tr>
                                <th>{{ __('field_title') }}:</th>
                                <td>{{ $row->title }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('field_type') }}:</th>
                                <td>
                                    @if($row->type == 'annual')
                                    <span class="badge badge-primary">{{ __('text_annual') }}</span>
                                    @elseif($row->type == 'departmental')
                                    <span class="badge badge-info">{{ __('text_departmental') }}</span>
                                    @else
                                    <span class="badge badge-secondary">{{ __('text_project') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('field_fiscal_year') }}:</th>
                                <td>{{ $row->fiscal_year }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('field_department') }}:</th>
                                <td>{{ $row->department->title ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('field_period') }}:</th>
                                <td>{{ date('M d, Y', strtotime($row->start_date)) }} - {{ date('M d, Y', strtotime($row->end_date)) }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('field_status') }}:</th>
                                <td>{!! $row->status_badge !!}</td>
                            </tr>
                            @if($row->description)
                            <tr>
                                <th>{{ __('field_description') }}:</th>
                                <td>{{ $row->description }}</td>
                            </tr>
                            @endif
                            @if($row->note)
                            <tr>
                                <th>{{ __('field_note') }}:</th>
                                <td>{{ $row->note }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>


                {{-- Sub-budgets: the part of this annual budget that has been
                     handed to a department or project to spend. Shown only when
                     some has, so the page stays quiet until it is relevant. --}}
                @if($row->children->count())
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Sub-budgets delegated from this one') }}</h5>
                    </div>
                    <div class="card-block table-border-style">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_department') }}</th>
                                        <th class="text-right">{{ __('field_total_amount') }}</th>
                                        <th class="text-right">{{ __('Spent') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row->children as $child)
                                    <tr>
                                        <td>{{ $child->title }}</td>
                                        <td>{{ optional($child->department)->title ?? '—' }}</td>
                                        <td class="text-right">{{ number_format($child->total_amount) }}</td>
                                        <td class="text-right">{{ number_format($child->spent_amount) }}</td>
                                        <td><span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $child->status)) }}</span></td>
                                        <td class="text-right">
                                            <a href="{{ route('admin.budget.show', $child->id) }}" class="btn btn-sm btn-outline-primary">{{ __('Open') }}</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="2"><strong>{{ __('Total delegated') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($row->children->sum('total_amount')) }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($row->children->sum('spent_amount')) }}</strong></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Budget Allocations -->
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('text_budget_allocations') }}</h5>
                        <div class="card-header-right">
                            @can('budget-allocation-create')
                            @if($row->is_institutional)
                            {{-- An allocation made on the departmental screen carries no
                                 budget line, so its figure would sit on no line of the sheet. --}}
                            <a href="{{ route('admin.budget-sheet.show', $row->id) }}" class="btn btn-primary btn-sm">
                                <i class="feather icon-edit"></i> {{ __('Enter figures on the sheet') }}
                            </a>
                            @elseif(in_array($row->status, ['draft', 'pending_approval', 'approved']))
                            <a href="{{ route('admin.budget.allocations', $row->id) }}" class="btn btn-primary btn-sm">
                                <i class="feather icon-plus"></i> {{ __('text_manage_allocations') }}
                            </a>
                            @endif
                            @endcan
                        </div>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_category') }}</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_period') }}</th>
                                        <th>{{ __('field_allocated') }}</th>
                                        <th>{{ __('field_spent') }}</th>
                                        <th>{{ __('field_remaining') }}</th>
                                        <th>{{ __('field_utilization') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($row->allocations as $allocation)
                                    <tr>
                                        <td>{{ $allocation->expenseCategory->title ?? '-' }}</td>
                                        <td>{{ $allocation->title }}</td>
                                        <td>
                                            @if($allocation->period == 'yearly')
                                            <span class="badge badge-secondary">{{ __('text_yearly') }}</span>
                                            @elseif($allocation->period == 'q1')
                                            <span class="badge badge-primary">Q1</span>
                                            @elseif($allocation->period == 'q2')
                                            <span class="badge badge-info">Q2</span>
                                            @elseif($allocation->period == 'q3')
                                            <span class="badge badge-warning">Q3</span>
                                            @elseif($allocation->period == 'q4')
                                            <span class="badge badge-danger">Q4</span>
                                            @elseif($allocation->period == 'semester1')
                                            <span class="badge badge-success">Sem 1</span>
                                            @else
                                            <span class="badge badge-dark">Sem 2</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($allocation->allocated_amount, 2) }}</td>
                                        <td>{{ number_format($allocation->spent_amount, 2) }}</td>
                                        <td>{{ number_format($allocation->remaining_amount, 2) }}</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-{{ $allocation->status_color }}" 
                                                     role="progressbar" 
                                                     style="width: {{ $allocation->utilization_percentage }}%;">
                                                    {{ $allocation->utilization_percentage }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('msg_no_allocations') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                                @if($row->allocations->count() > 0)
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="3">{{ __('text_total') }}</th>
                                        <th>{{ number_format($row->allocated_amount, 2) }}</th>
                                        <th>{{ number_format($row->spent_amount, 2) }}</th>
                                        <th>{{ number_format($row->remaining_amount, 2) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Summary & Actions -->
            <div class="col-md-4">
                <!-- Summary Card -->
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('text_budget_summary') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="mb-3">
                            <h6 class="text-muted">{{ __('field_total_amount') }}</h6>
                            <h4 class="text-primary">{{ number_format($row->total_amount, 2) }}</h4>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted">{{ __('field_allocated_amount') }}</h6>
                            <h4 class="text-info">{{ number_format($row->allocated_amount, 2) }}</h4>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted">{{ __('field_spent_amount') }}</h6>
                            <h4 class="text-warning">{{ number_format($row->spent_amount, 2) }}</h4>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted">{{ __('field_remaining_amount') }}</h6>
                            <h4 class="text-success">{{ number_format($row->remaining_amount, 2) }}</h4>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted">{{ __('field_utilization') }}</h6>
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-{{ $row->utilization_color }}" 
                                     role="progressbar" 
                                     style="width: {{ $row->utilization_percentage }}%;">
                                    <strong>{{ $row->utilization_percentage }}%</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('text_actions') }}</h5>
                    </div>
                    <div class="card-block">
                        @can('budget-edit')
                        @if($row->status == 'draft')
                        <form action="{{ route('admin.budget.submit', $row->id) }}" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="feather icon-send"></i> {{ __('btn_submit_for_approval') }}
                            </button>
                        </form>
                        @endif
                        @endcan

                        @can('budget-approve')
                        @if($row->status == 'pending_approval')
                        <form action="{{ route('admin.budget.approve', $row->id) }}" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="feather icon-check-circle"></i> {{ __('btn_approve') }}
                            </button>
                        </form>
                        @endif
                        @endcan

                        @can('budget-activate')
                        @if($row->status == 'approved')
                        <form action="{{ route('admin.budget.activate', $row->id) }}" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-info btn-block">
                                <i class="feather icon-play"></i> {{ __('btn_activate') }}
                            </button>
                        </form>
                        @endif
                        @endcan

                        @can('budget-close')
                        @if($row->status == 'active')
                        <form action="{{ route('admin.budget.close', $row->id) }}" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-dark btn-block" onclick="return confirm('{{ __('msg_confirm_close_budget') }}')">
                                <i class="feather icon-lock"></i> {{ __('btn_close') }}
                            </button>
                        </form>
                        @endif
                        @endcan

                        @can('budget-cancel')
                        @if(in_array($row->status, ['draft', 'pending_approval', 'approved']))
                        <form action="{{ route('admin.budget.cancel', $row->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('{{ __('msg_confirm_cancel_budget') }}')">
                                <i class="feather icon-x-circle"></i> {{ __('btn_cancel') }}
                            </button>
                        </form>
                        @endif
                        @endcan

                        {{-- Revise the total of an otherwise-locked (approved/active) budget; logs a revision --}}
                        @can('budget-edit')
                        @if(in_array($row->status, ['approved', 'active']))
                        <hr>
                        <form action="{{ route('admin.budget.revise', $row->id) }}" method="POST">
                            @csrf
                            <label class="small font-weight-bold mb-1">{{ __('Revise Total Amount') }}</label>
                            <input type="number" step="0.01" min="0" name="new_amount" class="form-control form-control-sm mb-1" value="{{ old('new_amount', $row->total_amount) }}" required>
                            <textarea name="reason" class="form-control form-control-sm mb-1" rows="2" placeholder="{{ __('Reason for revision') }}" required>{{ old('reason') }}</textarea>
                            <button type="submit" class="btn btn-warning btn-block btn-sm" onclick="return confirm('{{ __('Apply this budget revision?') }}')">
                                <i class="feather icon-edit-2"></i> {{ __('Apply Revision') }}
                            </button>
                        </form>
                        @endif
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
