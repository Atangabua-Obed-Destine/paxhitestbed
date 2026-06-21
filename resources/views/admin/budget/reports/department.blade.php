@extends('admin.layouts.master')
@section('title', __('department_expenditure_report'))

@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title">{{ __('department_expenditure_report') }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.budget.index') }}">{{ __('budgets') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('department_report') }}</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header"><h5><i class="ti-filter"></i> {{ __('filters') }}</h5></div>
                    <div class="card-body">
                        <form action="{{ route('admin.budget.reports.department') }}" method="GET">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fiscal_year">{{ __('fiscal_year') }}</label>
                                        <select name="fiscal_year" class="form-control">
                                            <option value="">{{ __('all_years') }}</option>
                                            @foreach($fiscalYears as $year)
                                                <option value="{{ $year }}" {{ request('fiscal_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status">{{ __('status') }}</label>
                                        <select name="status" class="form-control">
                                            <option value="">{{ __('all_statuses') }}</option>
                                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('active') }}</option>
                                            <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('closed') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="ti-search"></i> {{ __('apply_filters') }}</button>
                            <a href="{{ route('admin.budget.reports.department') }}" class="btn btn-secondary"><i class="ti-reload"></i> {{ __('reset') }}</a>
                            <a href="{{ route('admin.budget.reports.department', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-danger float-right"><i class="ti-download"></i> {{ __('export_pdf') }}</a>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header"><h5><i class="ti-bar-chart"></i> {{ __('department_comparison') }}</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('department') }}</th>
                                        <th class="text-center">{{ __('budgets') }}</th>
                                        <th class="text-right">{{ __('total_budget') }}</th>
                                        <th class="text-right">{{ __('total_spent') }}</th>
                                        <th class="text-right">{{ __('total_remaining') }}</th>
                                        <th class="text-right">{{ __('utilization') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reportData as $row)
                                    <tr>
                                        <td><strong>{{ $row['department'] }}</strong></td>
                                        <td class="text-center">{{ $row['budget_count'] }}</td>
                                        <td class="text-right">{{ number_format($row['total_budget'], 2) }}</td>
                                        <td class="text-right">{{ number_format($row['total_spent'], 2) }}</td>
                                        <td class="text-right">{{ number_format($row['total_remaining'], 2) }}</td>
                                        <td class="text-right">
                                            @php
                                                $progressClass = $row['utilization_rate'] > 90 ? 'danger' : ($row['utilization_rate'] > 75 ? 'warning' : 'success');
                                            @endphp
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-{{ $progressClass }}" style="width: {{ min($row['utilization_rate'], 100) }}%;">
                                                    {{ number_format($row['utilization_rate'], 1) }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ __('no_data_available') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
