@extends('admin.layouts.master')

@section('title', __('Accounting Reports'))

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ __('Accounting Reports') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Accounting Reports') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Aging Reports -->
            <div class="row">
                <div class="col-12">
                    <h4 class="text-muted mb-3"><i class="fas fa-clock mr-2"></i>{{ __('Aging Reports') }}</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="card card-outline card-info">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-file-invoice-dollar text-info"></i>
                                {{ __('Receivables Aging') }}
                            </h5>
                            <p class="card-text">{{ __('Analyze accounts receivable aging by customer and date brackets') }}</p>
                            <a href="{{ route('admin.accounting-reports.receivables-aging') }}" class="btn btn-info">
                                <i class="fas fa-chart-bar"></i> {{ __('View Report') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-outline card-warning">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-file-invoice text-warning"></i>
                                {{ __('Payables Aging') }}
                            </h5>
                            <p class="card-text">{{ __('Analyze accounts payable aging by vendor and date brackets') }}</p>
                            <a href="{{ route('admin.accounting-reports.payables-aging') }}" class="btn btn-warning">
                                <i class="fas fa-chart-bar"></i> {{ __('View Report') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-outline card-danger">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-user-graduate text-danger"></i>
                                {{ __('Student Fee Aging') }}
                            </h5>
                            <p class="card-text">{{ __('Analyze student fee balances by program and aging brackets') }}</p>
                            <a href="{{ route('admin.accounting-reports.student-fee-aging') }}" class="btn btn-danger">
                                <i class="fas fa-chart-bar"></i> {{ __('View Report') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Flow Reports -->
            <div class="row mt-4">
                <div class="col-12">
                    <h4 class="text-muted mb-3"><i class="fas fa-money-bill-wave mr-2"></i>{{ __('Cash Flow Reports') }}</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="card card-outline card-success">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-stream text-success"></i>
                                {{ __('Cash Flow Statement') }}
                            </h5>
                            <p class="card-text">{{ __('Operating, investing, and financing cash flows per OHADA standards') }}</p>
                            <a href="{{ route('admin.accounting-reports.cash-flow-statement') }}" class="btn btn-success">
                                <i class="fas fa-chart-bar"></i> {{ __('View Report') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-outline card-primary">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-chart-line text-primary"></i>
                                {{ __('Comparative Cash Flow') }}
                            </h5>
                            <p class="card-text">{{ __('Compare cash flows across multiple fiscal years') }}</p>
                            <a href="{{ route('admin.accounting-reports.comparative-cash-flow') }}" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i> {{ __('View Report') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Reports -->
            <div class="row mt-4">
                <div class="col-12">
                    <h4 class="text-muted mb-3"><i class="fas fa-balance-scale mr-2"></i>{{ __('Budget Reports') }}</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="card card-outline card-purple">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-balance-scale-left text-purple"></i>
                                {{ __('Budget vs Actual') }}
                            </h5>
                            <p class="card-text">{{ __('Compare budgeted amounts to actual results with variance analysis') }}</p>
                            <a href="{{ route('admin.accounting-reports.budget-vs-actual') }}" class="btn btn-purple">
                                <i class="fas fa-chart-bar"></i> {{ __('View Report') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mt-4">
                <div class="col-12">
                    <h4 class="text-muted mb-3"><i class="fas fa-tachometer-alt mr-2"></i>{{ __('Quick Stats') }}</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ __('Current Fiscal Year') }}</span>
                            <span class="info-box-number">{{ $currentFiscalYear->name ?? __('Not Set') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ __('FY Status') }}</span>
                            <span class="info-box-number">{{ $currentFiscalYear->is_closed ? __('Closed') : __('Open') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
