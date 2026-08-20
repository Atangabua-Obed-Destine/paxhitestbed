@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <div class="card-header-right">
                            @can('budget-create')
                            <a href="{{ route('admin.budget.create') }}" class="btn btn-primary btn-sm">
                                <i class="feather icon-plus"></i> {{ __('text_create_budget') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <div class="card-block">
                        <!-- Filter Form -->
                        <form method="get" action="{{ route('admin.budget.index') }}">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="type">{{ __('field_type') }}</label>
                                    <select class="form-control" name="type" id="type">
                                        <option value="">{{ __('text_all') }}</option>
                                        <option value="annual" {{ request('type') == 'annual' ? 'selected' : '' }}>{{ __('text_annual') }}</option>
                                        <option value="departmental" {{ request('type') == 'departmental' ? 'selected' : '' }}>{{ __('text_departmental') }}</option>
                                        <option value="project" {{ request('type') == 'project' ? 'selected' : '' }}>{{ __('text_project') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="status">{{ __('field_status') }}</label>
                                    <select class="form-control" name="status" id="status">
                                        <option value="">{{ __('text_all') }}</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ __('text_draft') }}</option>
                                        <option value="pending_approval" {{ request('status') == 'pending_approval' ? 'selected' : '' }}>{{ __('text_pending_approval') }}</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ __('text_approved') }}</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('text_active') }}</option>
                                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('text_closed') }}</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ __('text_cancelled') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="fiscal_year">{{ __('field_fiscal_year') }}</label>
                                    <select class="form-control" name="fiscal_year" id="fiscal_year">
                                        <option value="">{{ __('text_all') }}</option>
                                        @foreach($fiscal_years as $year)
                                        <option value="{{ $year }}" {{ request('fiscal_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="department_id">{{ __('field_department') }}</label>
                                    <select class="form-control" name="department_id" id="department_id">
                                        <option value="">{{ __('text_all') }}</option>
                                        @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>{{ $department->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12 mt-2">
                                    <button type="submit" class="btn btn-info btn-sm">
                                        <i class="feather icon-filter"></i> {{ __('btn_filter') }}
                                    </button>
                                    <a href="{{ route('admin.budget.index') }}" class="btn btn-secondary btn-sm">
                                        <i class="feather icon-refresh-cw"></i> {{ __('btn_reset') }}
                                    </a>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_id') }}</th>
                                        <th>{{ __('field_budget_code') }}</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_type') }}</th>
                                        <th>{{ __('field_fiscal_year') }}</th>
                                        <th>{{ __('field_department') }}</th>
                                        <th>{{ __('field_total_amount') }}</th>
                                        <th>{{ __('field_utilization') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($rows as $row)
                                    <tr>
                                        <td>{{ $row->id }}</td>
                                        <td><strong>{{ $row->budget_code }}</strong></td>
                                        <td>
                                            @if($row->is_institutional)
                                                <a href="{{ route('admin.budget-sheet.show', $row->id) }}">{{ $row->title }}</a>
                                            @else
                                                {{ $row->title }}
                                            @endif
                                        </td>
                                        <td>
                                            {{-- The annual sheet is a different instrument from a
                                                 departmental pot, so it says so rather than sharing
                                                 the plain "Annual" label with a project budget. --}}
                                            @if($row->is_institutional)
                                            <span class="badge badge-dark">{{ __('Annual (Institutional)') }}</span>
                                            @elseif($row->type == 'annual')
                                            <span class="badge badge-primary">{{ __('text_annual') }}</span>
                                            @elseif($row->type == 'departmental')
                                            <span class="badge badge-info">{{ __('text_departmental') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __('text_project') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->fiscal_year }}</td>
                                        <td>{{ $row->department->title ?? '-' }}</td>
                                        <td>{{ number_format($row->total_amount, 2) }}</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-{{ $row->utilization_color }}" 
                                                     role="progressbar" 
                                                     style="width: {{ $row->utilization_percentage }}%;" 
                                                     aria-valuenow="{{ $row->utilization_percentage }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    {{ $row->utilization_percentage }}%
                                                </div>
                                            </div>
                                            <small>
                                                Spent: {{ number_format($row->spent_amount, 2) }} / 
                                                Remaining: {{ number_format($row->remaining_amount, 2) }}
                                            </small>
                                        </td>
                                        <td>{!! $row->status_badge !!}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                @can('budget-view')
                                                <a href="{{ route('admin.budget.show', $row->id) }}" class="btn btn-icon btn-info btn-sm" title="{{ __('btn_view') }}">
                                                    <i class="feather icon-eye"></i>
                                                </a>
                                                @endcan
                                                
                                                @can('budget-edit')
                                                @if(in_array($row->status, ['draft', 'pending_approval']))
                                                <a href="{{ route('admin.budget.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm" title="{{ __('btn_edit') }}">
                                                    <i class="feather icon-edit"></i>
                                                </a>
                                                @endif
                                                @endcan
                                                
                                                @can('budget-delete')
                                                @if($row->status == 'draft')
                                                <button type="button" class="btn btn-icon btn-danger btn-sm" title="{{ __('btn_delete') }}" onclick="if(confirm('{{ __('msg_confirm_delete') }}')) { document.getElementById('delete-form-{{ $row->id }}').submit(); }">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                                <form id="delete-form-{{ $row->id }}" action="{{ route('admin.budget.delete', $row->id) }}" method="POST" style="display: none;">
                                                    @csrf
                                                </form>
                                                @endif
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center">{{ __('msg_no_record_found') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>

                            <div class="mt-3">
                                {{ $rows->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
