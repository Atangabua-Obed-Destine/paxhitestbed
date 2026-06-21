@extends('admin.layouts.master')
@section('title', 'Platform Fee Statistics')

@section('content')
<style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }
    
    .stats-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        animation: fadeInUp 0.6s ease;
    }
    
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        animation: fadeInUp 0.8s ease;
        border-left: 5px solid;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card.total {
        border-left-color: #3b82f6;
    }
    
    .stat-card.pending {
        border-left-color: #f59e0b;
    }
    
    .stat-card.approved {
        border-left-color: #10b981;
    }
    
    .stat-card.rejected {
        border-left-color: #ef4444;
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 15px;
        animation: pulse 2s infinite;
    }
    
    .stat-icon.total {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .stat-icon.pending {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }
    
    .stat-icon.approved {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .stat-icon.rejected {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 14px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .session-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-top: 25px;
        animation: fadeInUp 1s ease;
    }
    
    .session-header {
        font-size: 20px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .session-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.3s ease;
    }
    
    .session-row:hover {
        background: #f8fafc;
        padding-left: 10px;
    }
    
    .session-name {
        font-weight: 600;
        color: #475569;
    }
    
    .session-stats {
        display: flex;
        gap: 30px;
    }
    
    .session-stat {
        text-align: center;
    }
    
    .session-stat-value {
        font-size: 20px;
        font-weight: 700;
    }
    
    .session-stat-label {
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
    }
    
    .revenue-card {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4);
        animation: fadeInUp 0.7s ease;
        text-align: center;
    }
    
    .revenue-amount {
        font-size: 48px;
        font-weight: 700;
        margin: 20px 0;
    }
    
    .revenue-label {
        font-size: 16px;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
</style>

<div class="content-header row">
</div>

<div class="content-body">
    <section id="platform-fee-statistics">
        <div class="stats-header">
            <h2 class="mb-0"><i class="fas fa-chart-bar"></i> Platform Fee Statistics</h2>
            <p class="mb-0 mt-2" style="opacity: 0.9;">Overview of platform fee payments and collections</p>
        </div>

        <!-- Stats Cards Row -->
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="stat-card total">
                    <div class="stat-icon total">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-number">{{ $statistics['total_payments'] }}</div>
                    <div class="stat-label">Total Payments</div>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="stat-card pending">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number">{{ $statistics['pending_payments'] }}</div>
                    <div class="stat-label">Pending Review</div>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="stat-card approved">
                    <div class="stat-icon approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number">{{ $statistics['approved_payments'] }}</div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="stat-card rejected">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-number">{{ $statistics['rejected_payments'] }}</div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Revenue Card -->
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="revenue-card">
                    <div class="revenue-label">
                        <i class="fas fa-money-bill-wave"></i> Total Revenue Collected
                    </div>
                    <div class="revenue-amount">
                        {!! $systemSetting->currency_symbol !!} {{ number_format($statistics['total_collected'], 2) }}
                    </div>
                    <p class="mb-0" style="opacity: 0.8;">From approved platform fee payments</p>
                </div>
            </div>
        </div>

        <!-- Session Breakdown -->
        <div class="session-card">
            <div class="session-header">
                <i class="fas fa-calendar-alt"></i> Session-wise Breakdown
            </div>

            @if($statistics['session_breakdown']->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-inbox text-muted" style="font-size: 48px;"></i>
                    <p class="text-muted mt-3">No payment data available yet</p>
                </div>
            @else
                @foreach($statistics['session_breakdown'] as $session)
                <div class="session-row">
                    <div class="session-name">
                        <i class="fas fa-graduation-cap text-primary"></i> {{ $session->session_name }}
                    </div>
                    <div class="session-stats">
                        <div class="session-stat">
                            <div class="session-stat-value text-primary">{{ $session->total }}</div>
                            <div class="session-stat-label">Total</div>
                        </div>
                        <div class="session-stat">
                            <div class="session-stat-value text-warning">{{ $session->pending }}</div>
                            <div class="session-stat-label">Pending</div>
                        </div>
                        <div class="session-stat">
                            <div class="session-stat-value text-success">{{ $session->approved }}</div>
                            <div class="session-stat-label">Approved</div>
                        </div>
                        <div class="session-stat">
                            <div class="session-stat-value text-danger">{{ $session->rejected }}</div>
                            <div class="session-stat-label">Rejected</div>
                        </div>
                        <div class="session-stat">
                            <div class="session-stat-value text-success">{!! $systemSetting->currency_symbol !!} {{ number_format($session->revenue, 2) }}</div>
                            <div class="session-stat-label">Revenue</div>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </section>
</div>
@endsection
