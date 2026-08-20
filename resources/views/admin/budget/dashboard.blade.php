@extends('admin.layouts.master')
@section('title', __('budget_dashboard'))

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
                        <h4 class="page-title">{{ __('budget_dashboard') }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.budget.index') }}">{{ __('budgets') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('dashboard') }}</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-primary">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('total_budget') }}</h6>
                                <h3 class="m-b-0 text-white">{{ number_format($kpis['total_budget'], 2) }}</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-wallet text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white"><span class="label label-primary m-r-10">{{ $kpis['active_budgets_count'] }}</span>{{ __('active_budgets') }}</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-info">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('allocated') }}</h6>
                                <h3 class="m-b-0 text-white">{{ number_format($kpis['total_allocated'], 2) }}</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clipboard-list text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white"><span class="label label-info m-r-10">{{ number_format($kpis['allocation_rate'], 1) }}%</span>{{ __('allocation_rate') }}</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-warning">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('spent') }}</h6>
                                <h3 class="m-b-0 text-white">{{ number_format($kpis['total_spent'], 2) }}</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white"><span class="label label-warning m-r-10">{{ number_format($kpis['utilization_rate'], 1) }}%</span>{{ __('utilization_rate') }}</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card prod-p-card bg-success">
                    <div class="card-body">
                        <div class="row align-items-center m-b-25">
                            <div class="col">
                                <h6 class="m-b-5 text-white">{{ __('remaining') }}</h6>
                                <h3 class="m-b-0 text-white">{{ number_format($kpis['total_remaining'], 2) }}</h3>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-piggy-bank text-white f-28"></i>
                            </div>
                        </div>
                        <p class="m-b-0 text-white"><span class="label label-success m-r-10">{{ number_format(100 - $kpis['utilization_rate'], 1) }}%</span>{{ __('available') }}</p>
                    </div>
                </div>
            </div>

            <!-- Budget Alerts -->
            {{-- The annual sheet reported on its own terms. Folding it into the
                 figures above would let a 48M institutional budget swamp every
                 departmental average. --}}
            @isset($annualBudget)
            @php
                $annualUtil = $annualBudget->total_amount > 0
                    ? round($annualBudget->spent_amount / $annualBudget->total_amount * 100, 1)
                    : 0;
                $annualTone = $annualUtil > 100 ? 'danger' : ($annualUtil > 90 ? 'warning' : 'success');
            @endphp
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Annual budget') }} — {{ $annualBudget->title }}</h5>
                        <div class="card-header-right">
                            <a href="{{ route('admin.budget-sheet.show', $annualBudget->id) }}" class="btn btn-sm btn-primary">
                                {{ __('Open the sheet') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-block">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h6 class="text-muted">{{ __('Status') }}</h6>
                                <h4>{{ ucfirst(str_replace('_', ' ', $annualBudget->status)) }}</h4>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">{{ __('Budgeted spending') }}</h6>
                                <h4>{{ number_format($annualBudget->total_amount) }}</h4>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">{{ __('Actual spending') }}</h6>
                                <h4>{{ number_format($annualBudget->spent_amount) }}</h4>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">{{ __('field_utilization') }}</h6>
                                <h4 class="text-{{ $annualTone }}">{{ $annualUtil }}%</h4>
                            </div>
                        </div>
                        @if($annualBudget->total_amount == 0)
                            <p class="text-muted small mb-0 mt-3">
                                {{ __('No figures have been entered on this sheet yet, so there is nothing to measure against.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            @endisset

            @if(count($alerts) > 0)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="ti-bell"></i> {{ __('budget_alerts') }}</h5>
                    </div>
                    <div class="card-body">
                        @foreach($alerts as $alert)
                        <div class="alert alert-{{ $alert['type'] }} alert-dismissible fade show" role="alert">
                            <i class="{{ $alert['icon'] }}"></i>
                            <strong>{{ $alert['title'] }}:</strong> {{ $alert['message'] }}
                            <a href="{{ route('admin.budget.show', $alert['budget_id']) }}" class="btn btn-sm btn-{{ $alert['type'] }} float-right">
                                {{ __('view_budget') }}
                            </a>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Charts Row 1 -->
            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> {{ __('budget_utilization') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="utilizationChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> {{ __('department_spending') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> {{ __('monthly_spending_trend') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> {{ __('top_spending_categories') }}</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Active Budgets Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="ti-list"></i> {{ __('active_budgets') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('code') }}</th>
                                        <th>{{ __('title') }}</th>
                                        <th>{{ __('type') }}</th>
                                        <th>{{ __('total_amount') }}</th>
                                        <th>{{ __('spent') }}</th>
                                        <th>{{ __('remaining') }}</th>
                                        <th>{{ __('utilization') }}</th>
                                        <th>{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($activeBudgets as $budget)
                                    <tr>
                                        <td><strong>{{ $budget->code }}</strong></td>
                                        <td>{{ $budget->title }}</td>
                                        <td>
                                            <span class="badge badge-info">
                                                {{ ucfirst(str_replace('_', ' ', $budget->type)) }}
                                            </span>
                                        </td>
                                        <td>{{ number_format($budget->total_amount, 2) }}</td>
                                        <td>{{ number_format($budget->spent_amount, 2) }}</td>
                                        <td>{{ number_format($budget->remaining_amount, 2) }}</td>
                                        <td>
                                            @php
                                                $utilization = $budget->total_amount > 0 ? ($budget->spent_amount / $budget->total_amount) * 100 : 0;
                                                $progressClass = $utilization > 90 ? 'danger' : ($utilization > 75 ? 'warning' : 'success');
                                            @endphp
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-{{ $progressClass }}" 
                                                     role="progressbar" 
                                                     style="width: {{ min($utilization, 100) }}%;" 
                                                     aria-valuenow="{{ $utilization }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    {{ number_format($utilization, 1) }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.budget.show', $budget->id) }}" 
                                               class="btn btn-sm btn-primary" 
                                               title="{{ __('view') }}">
                                                <i class="ti-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            {{ __('no_active_budgets') }}
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

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Utilization Doughnut Chart
    const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
    new Chart(utilizationCtx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($utilizationData['labels']) !!},
            datasets: [{
                data: {!! json_encode($utilizationData['values']) !!},
                backgroundColor: {!! json_encode($utilizationData['colors']) !!},
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD'
                            }).format(context.parsed);
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Department Spending Pie Chart
    const departmentCtx = document.getElementById('departmentChart').getContext('2d');
    new Chart(departmentCtx, {
        type: 'pie',
        data: {
            labels: {!! json_encode($departmentData['labels']) !!},
            datasets: [{
                data: {!! json_encode($departmentData['values']) !!},
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF9F40'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD'
                            }).format(context.parsed);
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Monthly Trend Line Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($monthlyTrendData['labels']) !!},
            datasets: [{
                label: 'Monthly Spending',
                data: {!! json_encode($monthlyTrendData['values']) !!},
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Spent: ' + new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD'
                            }).format(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('en-US', {
                                notation: 'compact',
                                compactDisplay: 'short'
                            }).format(value);
                        }
                    }
                }
            }
        }
    });

    // Category Spending Bar Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($categoryData['labels']) !!},
            datasets: [
                {
                    label: 'Allocated',
                    data: {!! json_encode($categoryData['allocated']) !!},
                    backgroundColor: '#36A2EB',
                    borderWidth: 0
                },
                {
                    label: 'Spent',
                    data: {!! json_encode($categoryData['spent']) !!},
                    backgroundColor: '#FF6384',
                    borderWidth: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD'
                            }).format(context.parsed.y);
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('en-US', {
                                notation: 'compact',
                                compactDisplay: 'short'
                            }).format(value);
                        }
                    }
                }
            }
        }
    });
</script>
@endsection
