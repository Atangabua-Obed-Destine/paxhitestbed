@extends('admin.layouts.master')
@section('title', __('income_statement') . ' - ' . __('accounting_view'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="card-title mb-0">
                                    {{ __('income_statement') }} ({{ __('compte_de_resultat') }})
                                    <span class="badge badge-info ml-2">{{ __('accounting_view') }}</span>
                                </h3>
                                <small class="text-muted">
                                    @if($startDate)
                                        {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                                    @else
                                        {{ __('up_to') }} {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                                    @endif
                                </small>
                            </div>
                            <div>
                                <a href="{{ route('admin.general-ledger.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> {{ __('back') }}
                                </a>
                                <button type="button" class="btn btn-info btn-sm" onclick="window.print()">
                                    <i class="fas fa-print"></i> {{ __('print') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-4 no-print">
                            <div class="col-md-12">
                                <form action="{{ route('admin.general-ledger.income-statement') }}" method="GET" class="form-inline">
                                    <div class="form-group mr-3">
                                        <label for="start_date" class="mr-2">{{ __('start_date') }}:</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="{{ $startDate ?? '' }}">
                                    </div>
                                    <div class="form-group mr-3">
                                        <label for="end_date" class="mr-2">{{ __('end_date') }}:</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="{{ $endDate }}" required>
                                    </div>
                                    <div class="form-group mr-3">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="use_journal_entries" name="use_journal_entries" value="1" checked>
                                            <label class="custom-control-label" for="use_journal_entries">
                                                <i class="fas fa-book"></i> {{ __('use_accounting_view') }}
                                            </label>
                                        </div>
                                        <small class="text-muted d-block">{{ __('pull_from_journal_entries') }}</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> {{ __('apply_filter') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Alert -->
                        <div class="alert alert-info no-print">
                            <i class="fas fa-info-circle"></i>
                            <strong>{{ __('accounting_view') }}:</strong> {{ __('this_report_uses_posted_journal_entries') }}
                        </div>

                        <!-- Income Statement Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="60%"><strong>{{ __('account') }}</strong></th>
                                        <th width="40%" class="text-right"><strong>{{ __('amount') }} (FCFA)</strong></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- REVENUE SECTION (Class 7) -->
                                    <tr class="table-success">
                                        <td colspan="2">
                                            <h5 class="mb-0">
                                                <i class="fas fa-arrow-circle-up"></i> 
                                                <strong>{{ __('revenue') }} ({{ __('class') }} 7)</strong>
                                            </h5>
                                        </td>
                                    </tr>
                                    
                                    @forelse($revenueByAccount as $account)
                                    <tr>
                                        <td class="pl-4">
                                            <i class="fas fa-angle-right text-muted"></i> 
                                            {{ $account['account_code'] }} - {{ $account['account_name'] }}
                                        </td>
                                        <td class="text-right">{{ number_format($account['amount'], 0, ',', ' ') }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">{{ __('no_revenue_recorded') }}</td>
                                    </tr>
                                    @endforelse
                                    
                                    <tr class="table-success font-weight-bold">
                                        <td><strong>{{ __('total_revenue') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalRevenue, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    <!-- EXPENSES SECTION (Class 6) -->
                                    <tr class="table-danger">
                                        <td colspan="2">
                                            <h5 class="mb-0">
                                                <i class="fas fa-arrow-circle-down"></i> 
                                                <strong>{{ __('expenses') }} ({{ __('class') }} 6)</strong>
                                            </h5>
                                        </td>
                                    </tr>
                                    
                                    @forelse($expensesByAccount as $account)
                                    <tr>
                                        <td class="pl-4">
                                            <i class="fas fa-angle-right text-muted"></i> 
                                            {{ $account['account_code'] }} - {{ $account['account_name'] }}
                                        </td>
                                        <td class="text-right">{{ number_format($account['amount'], 0, ',', ' ') }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">{{ __('no_expenses_recorded') }}</td>
                                    </tr>
                                    @endforelse
                                    
                                    <tr class="table-danger font-weight-bold">
                                        <td><strong>{{ __('total_expenses') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalExpenses, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    <!-- OPERATING PROFIT/LOSS -->
                                    <tr class="table-{{ $operatingProfit >= 0 ? 'primary' : 'warning' }} font-weight-bold">
                                        <td><strong>{{ __('operating_result') }} ({{ __('resultat_exploitation') }})</strong></td>
                                        <td class="text-right"><strong>{{ number_format($operatingProfit, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    <!-- OTHER RESULTS SECTION (Class 8) -->
                                    @if($otherResultsByAccount->isNotEmpty())
                                    <tr class="table-info">
                                        <td colspan="2">
                                            <h5 class="mb-0">
                                                <i class="fas fa-plus-minus"></i> 
                                                <strong>{{ __('other_results') }} ({{ __('class') }} 8)</strong>
                                            </h5>
                                        </td>
                                    </tr>
                                    
                                    @foreach($otherResultsByAccount as $account)
                                    <tr>
                                        <td class="pl-4">
                                            <i class="fas fa-angle-right text-muted"></i> 
                                            {{ $account['account_code'] }} - {{ $account['account_name'] }}
                                        </td>
                                        <td class="text-right">{{ number_format($account['amount'], 0, ',', ' ') }}</td>
                                    </tr>
                                    @endforeach
                                    
                                    <tr class="table-info font-weight-bold">
                                        <td><strong>{{ __('total_other_results') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalOtherResults, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    @endif
                                    
                                    <!-- NET PROFIT/LOSS -->
                                    <tr class="table-{{ $netProfit >= 0 ? 'success' : 'danger' }}" style="font-size: 16px;">
                                        <td>
                                            <strong>
                                                @if($netProfit >= 0)
                                                    <i class="fas fa-check-circle text-success"></i> {{ __('net_profit') }} ({{ __('benefice_net') }})
                                                @else
                                                    <i class="fas fa-times-circle text-danger"></i> {{ __('net_loss') }} ({{ __('perte_nette') }})
                                                @endif
                                            </strong>
                                        </td>
                                        <td class="text-right"><strong>{{ number_format(abs($netProfit), 0, ',', ' ') }}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Financial Metrics -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ __('financial_metrics') }}</h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <td>{{ __('operating_margin') }}:</td>
                                                <td class="text-right">
                                                    <strong>{{ number_format($operatingMargin, 2) }}%</strong>
                                                </td>
                                            </tr>
                                        </table>
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

<style media="print">
    .no-print {
        display: none !important;
    }
</style>

@endsection
