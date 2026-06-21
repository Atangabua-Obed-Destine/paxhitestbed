@extends('admin.layouts.master')
@section('title', __('income_statement'))

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
                                <h3 class="card-title mb-0">{{ __('income_statement') }} ({{ __('compte_de_resultat') }})</h3>
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
                                @can('income-statement-export')
                                <a href="{{ route('admin.general-ledger.income-statement-pdf', request()->all()) }}" class="btn btn-danger btn-sm" target="_blank">
                                    <i class="fas fa-file-pdf"></i> {{ __('export_pdf') }}
                                </a>
                                <a href="{{ route('admin.general-ledger.income-statement-excel', request()->all()) }}" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel"></i> {{ __('export_excel') }}
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
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
                                            <input type="checkbox" class="custom-control-input" id="use_journal_entries" name="use_journal_entries" value="1" {{ $useJournalEntries ? 'checked' : '' }}>
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

                        <!-- Comprehensive Income Statement Report -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" style="font-size: 14px;">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="65%"><strong>{{ __('description') }}</strong></th>
                                        <th width="35%" class="text-right"><strong>{{ __('amount') }} (FCFA)</strong></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- ============================================ -->
                                    <!-- REVENUE SECTION -->
                                    <!-- ============================================ -->
                                    <tr class="table-success">
                                        <td colspan="2">
                                            <h5 class="mb-0">
                                                <i class="fas fa-arrow-circle-up"></i> 
                                                <strong>{{ __('revenue') }} / {{ __('income') }}</strong>
                                            </h5>
                                        </td>
                                    </tr>
                                    
                                    <!-- A. STUDENT FEES REVENUE -->
                                    <tr class="bg-light">
                                        <td><strong>A. {{ __('student_fees_revenue') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($studentFeesRevenue, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    @if($feesByCategory->isNotEmpty())
                                        @foreach($feesByCategory as $feeCategory)
                                        <tr>
                                            <td class="pl-4">
                                                <i class="fas fa-angle-right text-muted"></i> 
                                                {{ $feeCategory->category_name }}
                                            </td>
                                            <td class="text-right">{{ number_format($feeCategory->total, 0, ',', ' ') }}</td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="2" class="text-center text-muted pl-4">
                                                <small><em>{{ __('no_student_fee_payments') }}</em></small>
                                            </td>
                                        </tr>
                                    @endif
                                    
                                    <tr><td colspan="2" class="p-1"></td></tr> <!-- Spacer -->
                                    
                                    <!-- B. OTHER INCOME -->
                                    <tr class="bg-light">
                                        <td><strong>B. {{ __('other_income') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($otherIncomeTotal, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    @if($incomeByCategory->isNotEmpty())
                                        @foreach($incomeByCategory as $income)
                                        <tr>
                                            <td class="pl-4">
                                                <i class="fas fa-angle-right text-muted"></i> 
                                                {{ $income->category_name }}
                                            </td>
                                            <td class="text-right">{{ number_format($income->total, 0, ',', ' ') }}</td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="2" class="text-center text-muted pl-4">
                                                <small><em>{{ __('no_other_income_recorded') }}</em></small>
                                            </td>
                                        </tr>
                                    @endif
                                    
                                    <!-- TOTAL REVENUE -->
                                    <tr class="table-success" style="font-size: 15px;">
                                        <td><strong><i class="fas fa-equals"></i> {{ __('total_revenue') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalRevenue, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    <tr><td colspan="2" class="p-2 bg-white"></td></tr> <!-- Spacer -->
                                    
                                    <!-- ============================================ -->
                                    <!-- EXPENSES SECTION -->
                                    <!-- ============================================ -->
                                    <tr class="table-danger">
                                        <td colspan="2">
                                            <h5 class="mb-0">
                                                <i class="fas fa-arrow-circle-down"></i> 
                                                <strong>{{ __('expenses') }}</strong>
                                            </h5>
                                        </td>
                                    </tr>
                                    
                                    <!-- C. OPERATING EXPENSES -->
                                    <tr class="bg-light">
                                        <td><strong>C. {{ __('operating_expenses') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($operatingExpensesTotal, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    @if($expensesByCategory->isNotEmpty())
                                        @foreach($expensesByCategory as $expense)
                                        <tr>
                                            <td class="pl-4">
                                                <i class="fas fa-angle-right text-muted"></i> 
                                                {{ $expense->category_name }}
                                            </td>
                                            <td class="text-right">{{ number_format($expense->total, 0, ',', ' ') }}</td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="2" class="text-center text-muted pl-4">
                                                <small><em>{{ __('no_operating_expenses') }}</em></small>
                                            </td>
                                        </tr>
                                    @endif
                                    
                                    <tr><td colspan="2" class="p-1"></td></tr> <!-- Spacer -->
                                    
                                    <!-- D. PAYROLL & SALARIES -->
                                    <tr class="bg-light">
                                        <td><strong>D. {{ __('staff_salaries_payroll') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($payrollTotal, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    @if($payrollBreakdown && $payrollBreakdown->staff_count > 0)
                                        <tr>
                                            <td class="pl-4">
                                                <i class="fas fa-angle-right text-muted"></i> 
                                                {{ __('basic_salaries') }} 
                                                <small class="text-muted">({{ $payrollBreakdown->staff_count }} {{ __('staff') }})</small>
                                            </td>
                                            <td class="text-right">{{ number_format($payrollBreakdown->total_basic_salary, 0, ',', ' ') }}</td>
                                        </tr>
                                        @if($payrollBreakdown->total_allowances > 0)
                                        <tr>
                                            <td class="pl-4">
                                                <i class="fas fa-angle-right text-muted"></i> 
                                                {{ __('allowances') }}
                                            </td>
                                            <td class="text-right">{{ number_format($payrollBreakdown->total_allowances, 0, ',', ' ') }}</td>
                                        </tr>
                                        @endif
                                        @if($payrollBreakdown->total_bonuses > 0)
                                        <tr>
                                            <td class="pl-4">
                                                <i class="fas fa-angle-right text-muted"></i> 
                                                {{ __('bonuses') }}
                                            </td>
                                            <td class="text-right">{{ number_format($payrollBreakdown->total_bonuses, 0, ',', ' ') }}</td>
                                        </tr>
                                        @endif
                                        @if($payrollBreakdown->total_deductions > 0)
                                        <tr>
                                            <td class="pl-4 text-success">
                                                <i class="fas fa-minus-circle"></i> 
                                                {{ __('deductions') }}
                                            </td>
                                            <td class="text-right text-success">({{ number_format($payrollBreakdown->total_deductions, 0, ',', ' ') }})</td>
                                        </tr>
                                        @endif
                                        @if($payrollBreakdown->total_tax > 0)
                                        <tr>
                                            <td class="pl-4 text-success">
                                                <i class="fas fa-minus-circle"></i> 
                                                {{ __('taxes') }}
                                            </td>
                                            <td class="text-right text-success">({{ number_format($payrollBreakdown->total_tax, 0, ',', ' ') }})</td>
                                        </tr>
                                        @endif
                                    @else
                                        <tr>
                                            <td colspan="2" class="text-center text-muted pl-4">
                                                <small><em>{{ __('no_payroll_records') }}</em></small>
                                            </td>
                                        </tr>
                                    @endif
                                    
                                    <!-- TOTAL EXPENSES -->
                                    <tr class="table-danger" style="font-size: 15px;">
                                        <td><strong><i class="fas fa-equals"></i> {{ __('total_expenses') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalExpenses, 0, ',', ' ') }}</strong></td>
                                    </tr>
                                    
                                    <tr><td colspan="2" class="p-2 bg-white"></td></tr> <!-- Spacer -->
                                    
                                    <!-- ============================================ -->
                                    <!-- FINANCIAL RESULTS -->
                                    <!-- ============================================ -->
                                    <tr class="table-info">
                                        <td colspan="2">
                                            <h5 class="mb-0">
                                                <i class="fas fa-chart-line"></i> 
                                                <strong>{{ __('financial_results') }}</strong>
                                            </h5>
                                        </td>
                                    </tr>
                                    
                                    <!-- Gross Profit (before payroll) -->
                                    <tr class="bg-light">
                                        <td>
                                            <strong>{{ __('gross_profit') }}</strong>
                                            <small class="text-muted d-block">{{ __('revenue_minus_operating_expenses') }}</small>
                                        </td>
                                        <td class="text-right">
                                            <strong class="{{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($grossProfit, 0, ',', ' ') }}
                                            </strong>
                                        </td>
                                    </tr>
                                    
                                    <!-- Operating Profit -->
                                    <tr class="table-warning" style="font-size: 16px;">
                                        <td>
                                            <strong>
                                                @if($operatingProfit >= 0)
                                                    <i class="fas fa-arrow-up text-success"></i>
                                                @else
                                                    <i class="fas fa-arrow-down text-danger"></i>
                                                @endif
                                                {{ __('operating_profit') }} / {{ __('loss') }}
                                            </strong>
                                            <small class="text-muted d-block">{{ __('revenue_minus_all_expenses') }}</small>
                                        </td>
                                        <td class="text-right">
                                            <strong class="{{ $operatingProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($operatingProfit, 0, ',', ' ') }}
                                            </strong>
                                        </td>
                                    </tr>
                                    
                                    <!-- Net Profit/Loss -->
                                    <tr class="table-{{ $netProfit >= 0 ? 'success' : 'danger' }}" style="font-size: 17px;">
                                        <td>
                                            <strong>
                                                @if($netProfit >= 0)
                                                    <i class="fas fa-check-circle"></i> {{ __('net_profit') }}
                                                @else
                                                    <i class="fas fa-times-circle"></i> {{ __('net_loss') }}
                                                @endif
                                            </strong>
                                            <small class="d-block" style="font-weight: normal;">
                                                {{ __('final_profit_or_loss') }}
                                            </small>
                                        </td>
                                        <td class="text-right">
                                            <strong style="font-size: 1.3em;">
                                                {{ number_format(abs($netProfit), 0, ',', ' ') }}
                                            </strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Financial Summary Cards -->
                        <div class="row mt-4">
                            <!-- Total Revenue Card -->
                            <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                                <div class="card" style="border-left: 4px solid #28a745;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-muted mb-1" style="font-size: 13px; font-weight: 500;">
                                                    {{ __('total_revenue') }}
                                                </h6>
                                                <h3 class="mb-0" style="font-weight: 700; color: #28a745;">
                                                    {{ number_format($totalRevenue, 0, ',', ' ') }}
                                                    <small class="text-muted" style="font-size: 14px;">FCFA</small>
                                                </h3>
                                                <small class="text-muted">
                                                    <i class="fas fa-graduation-cap"></i> 
                                                    {{ __('fees') }}: {{ number_format($studentFeesRevenue, 0, ',', ' ') }}
                                                </small>
                                            </div>
                                            <div class="text-success" style="font-size: 40px; opacity: 0.3;">
                                                <i class="fas fa-arrow-circle-up"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Total Expenses Card -->
                            <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                                <div class="card" style="border-left: 4px solid #dc3545;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-muted mb-1" style="font-size: 13px; font-weight: 500;">
                                                    {{ __('total_expenses') }}
                                                </h6>
                                                <h3 class="mb-0" style="font-weight: 700; color: #dc3545;">
                                                    {{ number_format($totalExpenses, 0, ',', ' ') }}
                                                    <small class="text-muted" style="font-size: 14px;">FCFA</small>
                                                </h3>
                                                <small class="text-muted">
                                                    <i class="fas fa-users"></i> 
                                                    {{ __('payroll') }}: {{ number_format($payrollTotal, 0, ',', ' ') }}
                                                </small>
                                            </div>
                                            <div class="text-danger" style="font-size: 40px; opacity: 0.3;">
                                                <i class="fas fa-arrow-circle-down"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Net Profit/Loss Card -->
                            <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                                <div class="card" style="border-left: 4px solid {{ $netProfit >= 0 ? '#007bff' : '#ffc107' }};">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-muted mb-1" style="font-size: 13px; font-weight: 500;">
                                                    @if($netProfit >= 0)
                                                        {{ __('net_profit') }}
                                                    @else
                                                        {{ __('net_loss') }}
                                                    @endif
                                                </h6>
                                                <h3 class="mb-0" style="font-weight: 700; color: {{ $netProfit >= 0 ? '#007bff' : '#ffc107' }};">
                                                    {{ number_format(abs($netProfit), 0, ',', ' ') }}
                                                    <small class="text-muted" style="font-size: 14px;">FCFA</small>
                                                </h3>
                                                <small class="text-muted">
                                                    @if($netProfit >= 0)
                                                        <i class="fas fa-thumbs-up text-success"></i> {{ __('profitable') }}
                                                    @else
                                                        <i class="fas fa-exclamation-triangle text-warning"></i> {{ __('deficit') }}
                                                    @endif
                                                </small>
                                            </div>
                                            <div style="font-size: 40px; opacity: 0.3; color: {{ $netProfit >= 0 ? '#007bff' : '#ffc107' }};">
                                                <i class="fas fa-{{ $netProfit >= 0 ? 'trophy' : 'chart-line' }}"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Profit Margin Card -->
                            <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                                <div class="card" style="border-left: 4px solid #17a2b8;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-muted mb-1" style="font-size: 13px; font-weight: 500;">
                                                    {{ __('profit_margin') }}
                                                </h6>
                                                <h3 class="mb-0" style="font-weight: 700; color: #17a2b8;">
                                                    {{ number_format($operatingMargin, 1) }}%
                                                </h3>
                                                <small class="text-muted">
                                                    @if($operatingMargin >= 20)
                                                        <i class="fas fa-star text-success"></i> {{ __('excellent') }}
                                                    @elseif($operatingMargin >= 10)
                                                        <i class="fas fa-check text-info"></i> {{ __('good') }}
                                                    @elseif($operatingMargin >= 0)
                                                        <i class="fas fa-minus text-warning"></i> {{ __('fair') }}
                                                    @else
                                                        <i class="fas fa-times text-danger"></i> {{ __('negative') }}
                                                    @endif
                                                </small>
                                            </div>
                                            <div class="text-info" style="font-size: 40px; opacity: 0.3;">
                                                <i class="fas fa-percentage"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expense Breakdown Charts -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-chart-pie"></i> {{ __('expense_breakdown') }}</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td>{{ __('operating_expenses') }}</td>
                                                <td class="text-right">{{ number_format($operatingExpensesTotal, 0, ',', ' ') }}</td>
                                                <td class="text-right">
                                                    <span class="badge badge-info">
                                                        {{ $totalExpenses > 0 ? number_format(($operatingExpensesTotal / $totalExpenses) * 100, 1) : 0 }}%
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>{{ __('payroll_expenses') }}</td>
                                                <td class="text-right">{{ number_format($payrollTotal, 0, ',', ' ') }}</td>
                                                <td class="text-right">
                                                    <span class="badge badge-warning">
                                                        {{ $totalExpenses > 0 ? number_format(($payrollTotal / $totalExpenses) * 100, 1) : 0 }}%
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-calculator"></i> {{ __('key_metrics') }}</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td>{{ __('payroll_as_percent_of_revenue') }}</td>
                                                <td class="text-right">
                                                    <span class="badge badge-{{ $payrollAsPercentOfRevenue < 50 ? 'success' : 'warning' }}">
                                                        {{ number_format($payrollAsPercentOfRevenue, 1) }}%
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>{{ __('operating_expenses_as_percent_of_revenue') }}</td>
                                                <td class="text-right">
                                                    <span class="badge badge-info">
                                                        {{ number_format($operatingExpensesAsPercentOfRevenue, 1) }}%
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>{{ __('total_expense_ratio') }}</strong></td>
                                                <td class="text-right">
                                                    <strong>
                                                        <span class="badge badge-{{ ($payrollAsPercentOfRevenue + $operatingExpensesAsPercentOfRevenue) < 80 ? 'success' : 'danger' }}">
                                                            {{ number_format($payrollAsPercentOfRevenue + $operatingExpensesAsPercentOfRevenue, 1) }}%
                                                        </span>
                                                    </strong>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Report Footer -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    {{ __('report_generated_at') }}: {{ now()->format('d/m/Y H:i') }}
                                </small>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection

@push('styles')
<style>
    @media print {
        .no-print {
            display: none !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
        }
        .info-box {
            display: none !important;
        }
    }
</style>
@endpush
