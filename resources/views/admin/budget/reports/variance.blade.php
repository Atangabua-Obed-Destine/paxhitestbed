@extends('admin.layouts.master')
@section('title', __('budget_variance_report'))

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title">{{ __('budget_variance_report') }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.budget.index') }}">{{ __('budgets') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('variance_report') }}</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">{{ __('total_allocations') }}</h6>
                        <h3 class="m-b-0">{{ $summary['total_allocations'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('favorable') }}</h6>
                        <h3 class="m-b-0 text-white">{{ $summary['favorable_count'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('unfavorable') }}</h6>
                        <h3 class="m-b-0 text-white">{{ $summary['unfavorable_count'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card {{ $summary['total_variance'] >= 0 ? 'bg-success' : 'bg-danger' }}">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('total_variance') }}</h6>
                        <h3 class="m-b-0 text-white">{{ number_format($summary['total_variance'], 2) }}</h3>
                    </div>
                </div>
            </div>

            <!-- Filters Card -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="ti-filter"></i> {{ __('filters') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.budget.reports.variance') }}" method="GET">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="budget_id">{{ __('budget') }}</label>
                                        <select name="budget_id" id="budget_id" class="form-control">
                                            <option value="">{{ __('all_budgets') }}</option>
                                            @foreach($allBudgets as $budget)
                                                <option value="{{ $budget->id }}" {{ request('budget_id') == $budget->id ? 'selected' : '' }}>
                                                    {{ $budget->code }} - {{ $budget->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="variance_type">{{ __('variance_type') }}</label>
                                        <select name="variance_type" id="variance_type" class="form-control">
                                            <option value="">{{ __('all_types') }}</option>
                                            <option value="favorable" {{ request('variance_type') == 'favorable' ? 'selected' : '' }}>{{ __('favorable') }}</option>
                                            <option value="unfavorable" {{ request('variance_type') == 'unfavorable' ? 'selected' : '' }}>{{ __('unfavorable') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti-search"></i> {{ __('apply_filters') }}
                                    </button>
                                    <a href="{{ route('admin.budget.reports.variance') }}" class="btn btn-secondary">
                                        <i class="ti-reload"></i> {{ __('reset') }}
                                    </a>
                                    <a href="{{ route('admin.budget.reports.variance', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
                                       class="btn btn-danger float-right">
                                        <i class="ti-download"></i> {{ __('export_pdf') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="ti-stats-up"></i> {{ __('variance_analysis') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('budget_code') }}</th>
                                        <th>{{ __('budget_title') }}</th>
                                        <th>{{ __('category') }}</th>
                                        <th class="text-right">{{ __('allocated') }}</th>
                                        <th class="text-right">{{ __('spent') }}</th>
                                        <th class="text-right">{{ __('variance') }}</th>
                                        <th class="text-right">{{ __('variance_percent') }}</th>
                                        <th class="text-center">{{ __('status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reportData as $row)
                                    <tr>
                                        <td><strong>{{ $row['budget_code'] }}</strong></td>
                                        <td>{{ $row['budget_title'] }}</td>
                                        <td>{{ $row['category'] }}</td>
                                        <td class="text-right">{{ number_format($row['allocated'], 2) }}</td>
                                        <td class="text-right">{{ number_format($row['spent'], 2) }}</td>
                                        <td class="text-right {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            <strong>{{ number_format($row['variance'], 2) }}</strong>
                                        </td>
                                        <td class="text-right {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            <strong>{{ number_format($row['variance_percent'], 1) }}%</strong>
                                        </td>
                                        <td class="text-center">
                                            @if($row['status'] == 'favorable')
                                                <span class="badge badge-success">
                                                    <i class="ti-check"></i> {{ __('favorable') }}
                                                </span>
                                            @else
                                                <span class="badge badge-danger">
                                                    <i class="ti-close"></i> {{ __('unfavorable') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            {{ __('no_data_available') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
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
