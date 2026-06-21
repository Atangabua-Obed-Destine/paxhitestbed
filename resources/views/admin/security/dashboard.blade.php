@extends('admin.layouts.master')
@section('title', __('Security Dashboard'))
@section('content')

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="fas fa-shield-alt text-primary"></i> {{ __('Security Dashboard') }}</h3>
                        <p class="text-muted mb-0">{{ __('Monitor and manage system security') }}</p>
                    </div>
                    <div>
                        <span class="badge badge-success badge-lg">
                            <i class="fas fa-check-circle"></i> {{ __('System Protected') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <!-- Failed Attempts Today -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                <div class="info-box bg-danger elevation-2">
                    <span class="info-box-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text text-white font-weight-bold">{{ __('Failed Attempts Today') }}</span>
                        <span class="info-box-number text-white" id="failed-attempts-today">
                            {{ number_format($stats['failed_attempts_today']) }}
                        </span>
                        <a href="{{ route('admin.security.logs') }}" class="text-white small">
                            <i class="fas fa-eye"></i> {{ __('View Logs') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Blocked Users -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                <div class="info-box bg-warning elevation-2">
                    <span class="info-box-icon">
                        <i class="fas fa-user-lock"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text text-white font-weight-bold">{{ __('Blocked Users') }}</span>
                        <span class="info-box-number text-white" id="blocked-users">
                            {{ number_format($stats['blocked_users']) }}
                        </span>
                        <a href="{{ route('admin.security.users') }}" class="text-white small">
                            <i class="fas fa-users-cog"></i> {{ __('Manage Users') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Active Whitelists -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                <div class="info-box bg-info elevation-2">
                    <span class="info-box-icon">
                        <i class="fas fa-network-wired"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text text-white font-weight-bold">{{ __('Active IP Whitelists') }}</span>
                        <span class="info-box-number text-white" id="active-whitelists">
                            {{ number_format($stats['active_whitelists']) }}
                        </span>
                        <a href="{{ route('admin.security.whitelist') }}" class="text-white small">
                            <i class="fas fa-shield-alt"></i> {{ __('Manage IPs') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Blocked IPs -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                <div class="info-box bg-danger elevation-2">
                    <span class="info-box-icon">
                        <i class="fas fa-ban"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text text-white font-weight-bold">{{ __('Blocked IPs') }}</span>
                        <span class="info-box-number text-white" id="blocked-ips">
                            {{ number_format($stats['blocked_ips'] ?? 0) }}
                        </span>
                        <a href="{{ route('admin.security.blocked-ips') }}" class="text-white small">
                            <i class="fas fa-ban"></i> {{ __('Manage Blocks') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row - Total Statistics -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card card-outline card-secondary elevation-2">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <i class="fas fa-chart-line fa-4x text-secondary"></i>
                            </div>
                            <div class="col-md-8">
                                <h4 class="mb-1">{{ __('Total Failed Login Attempts') }}</h4>
                                <h2 class="mb-0 text-secondary font-weight-bold" id="total-attempts">
                                    {{ number_format($stats['total_failed_attempts']) }}
                                </h2>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-info-circle"></i> {{ __('All time monitoring since system deployment') }}
                                </p>
                            </div>
                            <div class="col-md-2 text-center">
                                <a href="{{ route('admin.security.logs') }}" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-list"></i> {{ __('View Details') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- Failed Login Trend Chart -->
            <div class="col-lg-8">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-area"></i> {{ __('Failed Login Attempts - Last 30 Days') }}
                        </h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="80"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Attacking IPs -->
            <div class="col-lg-4">
                <div class="card card-danger card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-fire"></i> {{ __('Top Attacking IPs') }}
                        </h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('IP Address') }}</th>
                                    <th class="text-right">{{ __('Attempts') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topAttackingIps as $ip)
                                <tr>
                                    <td>
                                        <code>{{ $ip->ip_address }}</code>
                                    </td>
                                    <td class="text-right">
                                        <span class="badge badge-danger">{{ $ip->total }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted">
                                        <i class="fas fa-shield-alt"></i> {{ __('No attacks detected') }}
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Failed Login Attempts -->
        <div class="row">
            <div class="col-12">
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clock"></i> {{ __('Recent Failed Login Attempts') }}
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.security.logs') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-list"></i> {{ __('View All Logs') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('IP Address') }}</th>
                                    <th>{{ __('User Type') }}</th>
                                    <th>{{ __('Attempts') }}</th>
                                    <th>{{ __('Last Attempt') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentAttempts as $attempt)
                                <tr>
                                    <td>{{ $attempt->email }}</td>
                                    <td><code>{{ $attempt->ip_address }}</code></td>
                                    <td>
                                        <span class="badge badge-{{ $attempt->user_type === 'admin' ? 'primary' : 'info' }}">
                                            {{ ucfirst($attempt->user_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-danger">{{ $attempt->attempts }}</span>
                                    </td>
                                    <td>
                                        <small>{{ $attempt->last_attempt_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        @if($attempt->isBlocked())
                                        <span class="badge badge-danger">
                                            <i class="fas fa-lock"></i> {{ __('Blocked') }}
                                            ({{ $attempt->getRemainingBlockTime() }} min)
                                        </span>
                                        @else
                                        <span class="badge badge-warning">
                                            <i class="fas fa-unlock"></i> {{ __('Active') }}
                                        </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-3x mb-2"></i>
                                        <p>{{ __('No recent failed login attempts') }}</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Settings Summary -->
        <div class="row">
            <div class="col-12">
                <div class="card card-success card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-cogs"></i> {{ __('Security Settings Summary') }}
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.security.settings') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> {{ __('Manage Settings') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <span class="info-box-icon bg-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('Max Login Attempts') }}</span>
                                        <span class="info-box-number">{{ $settings['max_login_attempts'] ?? 5 }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <span class="info-box-icon bg-warning">
                                        <i class="fas fa-clock"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('Lockout Duration') }}</span>
                                        <span class="info-box-number">{{ $settings['lockout_duration'] ?? 5 }} {{ __('minutes') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <span class="info-box-icon bg-info">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('Min Password Length') }}</span>
                                        <span class="info-box-number">{{ $settings['min_password_length'] ?? 10 }} {{ __('characters') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
<!-- /.content -->

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    // Prepare trend data
    var trendLabels = [];
    var trendData = [];
    
    @foreach($trendData as $data)
        trendLabels.push('{{ \Carbon\Carbon::parse($data->date)->format("M d") }}');
        trendData.push({{ $data->total_attempts }});
    @endforeach

    // Create trend chart
    var ctx = document.getElementById('trendChart').getContext('2d');
    var trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Failed Login Attempts',
                data: trendData,
                backgroundColor: 'rgba(220, 53, 69, 0.2)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Auto-refresh stats every 30 seconds
    setInterval(function() {
        refreshStats();
    }, 30000);

    function refreshStats() {
        $.ajax({
            url: '{{ route("admin.security.dashboard") }}',
            method: 'GET',
            success: function(response) {
                // Update would require returning JSON, for now just reload
                // location.reload();
            }
        });
    }

    // Animate number counters on page load
    $('.small-box h3').each(function() {
        var $this = $(this);
        var countTo = parseInt($this.text());
        
        $({ countNum: 0 }).animate({
            countNum: countTo
        }, {
            duration: 1000,
            easing: 'swing',
            step: function() {
                $this.text(Math.floor(this.countNum));
            },
            complete: function() {
                $this.text(this.countNum);
            }
        });
    });
});
</script>
@endsection

@section('styles')
<style>
    .small-box {
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .small-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    .card {
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .info-box {
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    .info-box:hover {
        transform: scale(1.02);
    }
    .badge-lg {
        padding: 8px 15px;
        font-size: 14px;
    }
</style>
@endsection
