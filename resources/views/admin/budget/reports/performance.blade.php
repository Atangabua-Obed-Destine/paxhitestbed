@extends('admin.layouts.master')
@section('title', __('budget_performance_report'))

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
                        <h4 class="page-title">{{ __('budget_performance_report') }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.budget.index') }}">{{ __('budgets') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('performance_report') }}</li>
                        </ol>
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
                        <form action="{{ route('admin.budget.reports.performance') }}" method="GET">
                            <div class="row">
                                <div class="col-md-3">
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
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">{{ __('status') }}</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="">{{ __('all_statuses') }}</option>
                                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('active') }}</option>
                                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ __('approved') }}</option>
                                            <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('closed') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="budget_type">{{ __('budget_type') }}</label>
                                        <select name="budget_type" id="budget_type" class="form-control">
                                            <option value="">{{ __('all_types') }}</option>
                                            <option value="annual" {{ request('budget_type') == 'annual' ? 'selected' : '' }}>{{ __('annual') }}</option>
                                            <option value="departmental" {{ request('budget_type') == 'departmental' ? 'selected' : '' }}>{{ __('departmental') }}</option>
                                            <option value="project" {{ request('budget_type') == 'project' ? 'selected' : '' }}>{{ __('project') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fiscal_year">{{ __('fiscal_year') }}</label>
                                        <select name="fiscal_year" id="fiscal_year" class="form-control">
                                            <option value="">{{ __('all_years') }}</option>
                                            @foreach($fiscalYears as $year)
                                                <option value="{{ $year }}" {{ request('fiscal_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti-search"></i> {{ __('apply_filters') }}
                                    </button>
                                    <a href="{{ route('admin.budget.reports.performance') }}" class="btn btn-secondary">
                                        <i class="ti-reload"></i> {{ __('reset') }}
                                    </a>
                                    <a href="{{ route('admin.budget.reports.performance', array_merge(request()->all(), ['export' => 'pdf'])) }}" 
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
                        <h5><i class="ti-bar-chart"></i> {{ __('performance_analysis') }}</h5>
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
                                        <th class="text-right">{{ __('committed') }}</th>
                                        <th class="text-right">{{ __('remaining') }}</th>
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
                                        <td class="text-right">{{ number_format($row['committed'], 2) }}</td>
                                        <td class="text-right">{{ number_format($row['remaining'], 2) }}</td>
                                        <td class="text-right {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($row['variance'], 2) }}
                                        </td>
                                        <td class="text-right {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($row['variance_percent'], 1) }}%
                                        </td>
                                        <td class="text-center">
                                            @if($row['status'] == 'favorable')
                                                <span class="badge badge-success">{{ __('favorable') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ __('unfavorable') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">
                                            {{ __('no_data_available') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if(count($reportData) > 0)
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="3" class="text-right">{{ __('total') }}</th>
                                        <th class="text-right">{{ number_format(collect($reportData)->sum('allocated'), 2) }}</th>
                                        <th class="text-right">{{ number_format(collect($reportData)->sum('spent'), 2) }}</th>
                                        <th class="text-right">{{ number_format(collect($reportData)->sum('committed'), 2) }}</th>
                                        <th class="text-right">{{ number_format(collect($reportData)->sum('remaining'), 2) }}</th>
                                        <th class="text-right">{{ number_format(collect($reportData)->sum('variance'), 2) }}</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                                @endif
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
