@extends('admin.layouts.master')
@section('title', __('cashflow_projection'))

@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title">{{ __('cashflow_projection') }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.budget.index') }}">{{ __('budgets') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('cashflow_projection') }}</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Budget Selection -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header"><h5><i class="ti-filter"></i> {{ __('select_budget') }}</h5></div>
                    <div class="card-body">
                        <form action="{{ route('admin.budget.reports.cashflow') }}" method="GET">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                        <label for="budget_id">{{ __('budget') }}</label>
                                        <select name="budget_id" class="form-control" required>
                                            <option value="">{{ __('select_budget') }}</option>
                                            @foreach($allBudgets as $bdg)
                                                <option value="{{ $bdg->id }}" {{ isset($budget) && $budget->id == $bdg->id ? 'selected' : '' }}>
                                                    {{ $bdg->code }} - {{ $bdg->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block"><i class="ti-search"></i> {{ __('generate') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($budget) && isset($projections))
            <!-- Projection Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="ti-line-chart"></i> {{ $budget->code }} - {{ $budget->title }}</h5>
                        <a href="{{ route('admin.budget.reports.cashflow', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-sm btn-danger float-right">
                            <i class="ti-download"></i> {{ __('export_pdf') }}
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('month') }}</th>
                                        <th class="text-right">{{ __('planned_spending') }}</th>
                                        <th class="text-right">{{ __('actual_spending') }}</th>
                                        <th class="text-right">{{ __('variance') }}</th>
                                        <th class="text-right">{{ __('cumulative_planned') }}</th>
                                        <th class="text-right">{{ __('cumulative_actual') }}</th>
                                        <th class="text-right">{{ __('cumulative_variance') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($projections as $projection)
                                    @php
                                        $variance = $projection['planned'] - $projection['actual'];
                                        $cumulativeVariance = $projection['cumulative_planned'] - $projection['cumulative_actual'];
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $projection['month'] }}</strong></td>
                                        <td class="text-right">{{ number_format($projection['planned'], 2) }}</td>
                                        <td class="text-right">{{ number_format($projection['actual'], 2) }}</td>
                                        <td class="text-right {{ $variance >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($variance, 2) }}
                                        </td>
                                        <td class="text-right">{{ number_format($projection['cumulative_planned'], 2) }}</td>
                                        <td class="text-right">{{ number_format($projection['cumulative_actual'], 2) }}</td>
                                        <td class="text-right {{ $cumulativeVariance >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($cumulativeVariance, 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Chart -->
                        <div class="mt-4">
                            <canvas id="cashflowChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

@if(isset($projections))
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    const ctx = document.getElementById('cashflowChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode(array_column($projections, 'month')) !!},
            datasets: [
                {
                    label: 'Planned',
                    data: {!! json_encode(array_column($projections, 'planned')) !!},
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    fill: false,
                    tension: 0.4
                },
                {
                    label: 'Actual',
                    data: {!! json_encode(array_column($projections, 'actual')) !!},
                    borderColor: '#FF6384',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    fill: false,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>
@endsection
@endif
