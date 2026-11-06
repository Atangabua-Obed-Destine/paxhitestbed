@extends('admin.layouts.master')
@section('title', __('Security Dashboard'))

@push('css')
<link rel="stylesheet" href="{{ asset('dashboard/css/pages/admin-ui.css') }}?v={{ filemtime(public_path('dashboard/css/pages/admin-ui.css')) }}">
<style>
    /* Security dashboard — particular to this screen. Spacing scale: 8 · 16 · 24 px. */
    .sec-page .sec-header-title { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .sec-page .sec-header-title h5 { margin: 0; }

    .sec-page .sec-total .card-body {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 16px; padding: 24px;
    }
    .sec-page .sec-total h6 { margin: 0 0 8px; color: #6c757d; }
    .sec-page .sec-total h2 { margin: 0 0 8px; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .sec-page .sec-total p { margin: 0; font-size: 12px; }
    .sec-page .sec-total .btn { margin: 0; }

    .sec-page .sec-chart { padding: 24px; }

    .sec-page .sec-settings { padding: 24px; }
    .sec-page .sec-setting {
        display: flex; align-items: center; gap: 16px;
        height: 100%; padding: 16px;
        border: 1px solid #e9ecef; border-radius: 6px; background: #f8f9fa;
    }
    .sec-page .sec-setting-icon {
        display: flex; align-items: center; justify-content: center; flex: none;
        width: 40px; height: 40px; border-radius: 6px; color: #fff; font-size: 16px;
    }
    .sec-page .sec-setting-label { display: block; margin-bottom: 4px; font-size: 12px; color: #6c757d; }
    .sec-page .sec-setting-value { display: block; font-size: 16px; font-weight: 600; color: #343a40; }
    .sec-page .sec-settings .row > [class*="col-"] { margin-bottom: 16px; }
    .sec-page .sec-settings .row > [class*="col-"]:last-child { margin-bottom: 0; }
    @media (min-width: 768px) {
        .sec-page .sec-settings .row > [class*="col-"] { margin-bottom: 0; }
    }

    .sec-page .sec-empty { padding: 24px 16px !important; color: #6c757d; }
    .sec-page .sec-empty i { display: block; margin-bottom: 8px; font-size: 24px; opacity: .6; }
    .sec-page .sec-empty p { margin: 0; }
</style>
@endpush

@section('content')

<div class="main-body">
    <div class="page-wrapper sec-page">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title sec-header-title">
                            <h5>{{ __('Security Dashboard') }}</h5>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle"></i> {{ __('System Protected') }}
                            </span>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ __('Security') }}</a></li>
                            <li class="breadcrumb-item"><a href="#!">{{ __('Dashboard') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- Statistics Cards -->
        <div class="row stat-cards">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle stat-card-icon"></i>
                        <h6 class="text-white">{{ __('Failed Attempts Today') }}</h6>
                        <h2 class="text-white" id="failed-attempts-today">{{ number_format($stats['failed_attempts_today']) }}</h2>
                        <a href="{{ route('admin.security.logs') }}" class="stat-card-foot"><i class="fas fa-eye"></i>{{ __('View Logs') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <i class="fas fa-user-lock stat-card-icon"></i>
                        <h6 class="text-white">{{ __('Blocked Users') }}</h6>
                        <h2 class="text-white" id="blocked-users">{{ number_format($stats['blocked_users']) }}</h2>
                        <a href="{{ route('admin.security.users') }}" class="stat-card-foot"><i class="fas fa-users-cog"></i>{{ __('Manage Users') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <i class="fas fa-network-wired stat-card-icon"></i>
                        <h6 class="text-white">{{ __('Active IP Whitelists') }}</h6>
                        <h2 class="text-white" id="active-whitelists">{{ number_format($stats['active_whitelists']) }}</h2>
                        <a href="{{ route('admin.security.whitelist') }}" class="stat-card-foot"><i class="fas fa-shield-alt"></i>{{ __('Manage IPs') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <i class="fas fa-ban stat-card-icon"></i>
                        <h6 class="text-white">{{ __('Blocked IPs') }}</h6>
                        <h2 class="text-white" id="blocked-ips">{{ number_format($stats['blocked_ips'] ?? 0) }}</h2>
                        <a href="{{ route('admin.security.blocked-ips') }}" class="stat-card-foot"><i class="fas fa-ban"></i>{{ __('Manage Blocks') }}</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Failed Login Attempts -->
        <div class="row">
            <div class="col-12">
                <div class="card sec-total">
                    <div class="card-body">
                        <div>
                            <h6>{{ __('Total Failed Login Attempts') }}</h6>
                            <h2 id="total-attempts">{{ number_format($stats['total_failed_attempts']) }}</h2>
                            <p class="text-muted"><i class="fas fa-info-circle"></i> {{ __('All time monitoring since system deployment') }}</p>
                        </div>
                        <a href="{{ route('admin.security.logs') }}" class="btn btn-secondary">
                            <i class="fas fa-list"></i> {{ __('View Details') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header ui-card-header">
                        <h5>{{ __('Failed Login Attempts - Last 30 Days') }}</h5>
                    </div>
                    <div class="card-body sec-chart">
                        <canvas id="trendChart" height="80"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header ui-card-header">
                        <h5>{{ __('Top Attacking IPs') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm ui-table-flush">
                            <thead>
                                <tr>
                                    <th>{{ __('IP Address') }}</th>
                                    <th class="text-end">{{ __('Attempts') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topAttackingIps as $ip)
                                <tr>
                                    <td><code>{{ $ip->ip_address }}</code></td>
                                    <td class="text-end"><span class="badge badge-danger">{{ $ip->total }}</span></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center sec-empty">
                                        <i class="fas fa-shield-alt"></i>
                                        <p>{{ __('No attacks detected') }}</p>
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
                <div class="card">
                    <div class="card-header ui-card-header">
                        <h5>{{ __('Recent Failed Login Attempts') }}</h5>
                        <a href="{{ route('admin.security.logs') }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-list"></i> {{ __('View All Logs') }}
                        </a>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-striped ui-table-flush">
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
                                    <td><span class="badge badge-danger">{{ $attempt->attempts }}</span></td>
                                    <td><small>{{ $attempt->last_attempt_at->diffForHumans() }}</small></td>
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
                                    <td colspan="6" class="text-center sec-empty">
                                        <i class="fas fa-check-circle"></i>
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
                <div class="card">
                    <div class="card-header ui-card-header">
                        <h5>{{ __('Security Settings Summary') }}</h5>
                        <a href="{{ route('admin.security.settings') }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> {{ __('Manage Settings') }}
                        </a>
                    </div>
                    <div class="card-body sec-settings">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="sec-setting">
                                    <span class="sec-setting-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                                    <div>
                                        <span class="sec-setting-label">{{ __('Max Login Attempts') }}</span>
                                        <span class="sec-setting-value">{{ $settings['max_login_attempts'] ?? 5 }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="sec-setting">
                                    <span class="sec-setting-icon bg-warning"><i class="fas fa-clock"></i></span>
                                    <div>
                                        <span class="sec-setting-label">{{ __('Lockout Duration') }}</span>
                                        <span class="sec-setting-value">{{ $settings['lockout_duration'] ?? 5 }} {{ __('minutes') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="sec-setting">
                                    <span class="sec-setting-icon bg-info"><i class="fas fa-key"></i></span>
                                    <div>
                                        <span class="sec-setting-label">{{ __('Min Password Length') }}</span>
                                        <span class="sec-setting-value">{{ $settings['min_password_length'] ?? 10 }} {{ __('characters') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

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
