@extends('admin.layouts.master')
@section('title', __('trial_balance'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('trial_balance') }}</h3>
                        <div class="card-tools">
                            @can('trial-balance-export')
                            <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> {{ __('export_to_excel') }}
                            </button>
                            @endcan
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('print') }}
                            </button>
                            <a href="{{ route('admin.general-ledger.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Report Header -->
                        <div class="text-center mb-4 print-header">
                            <h3><strong>{{ config('app.name') }}</strong></h3>
                            <h4>{{ __('trial_balance') }}</h4>
                            @if($startDate && $endDate)
                            <p>{{ __('period') }}: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
                            @else
                            <p>{{ __('as_of') }}: {{ \Carbon\Carbon::now()->format('d M Y') }}</p>
                            @endif
                        </div>

                        <!-- Date Range Filter -->
                        <div class="row mb-3 no-print">
                            <div class="col-md-12">
                                <form action="{{ route('admin.general-ledger.trial-balance') }}" method="GET">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="fiscal_year_id">{{ __('fiscal_year') }}</label>
                                                <select class="form-control form-control-sm" id="fiscal_year_id" name="fiscal_year_id">
                                                    <option value="">{{ __('all_years') }}</option>
                                                    @foreach($fiscalYears as $year)
                                                    <option value="{{ $year->id }}" {{ request('fiscal_year_id') == $year->id ? 'selected' : '' }}>
                                                        {{ $year->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="start_date">{{ __('start_date') }}</label>
                                                <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="{{ request('start_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="end_date">{{ __('end_date') }}</label>
                                                <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="{{ request('end_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                    <i class="fas fa-filter"></i> {{ __('filter') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Trial Balance Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm" id="trialBalanceTable">
                                <thead>
                                    <tr class="table-primary">
                                        <th width="10%">{{ __('account_code') }}</th>
                                        <th width="30%">{{ __('account_name') }}</th>
                                        <th width="12%" class="text-right">{{ __('opening_debit') }}</th>
                                        <th width="12%" class="text-right">{{ __('opening_credit') }}</th>
                                        <th width="12%" class="text-right">{{ __('period_debit') }}</th>
                                        <th width="12%" class="text-right">{{ __('period_credit') }}</th>
                                        <th width="12%" class="text-right">{{ __('closing_debit') }}</th>
                                        <th width="12%" class="text-right">{{ __('closing_credit') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalOpeningDebit = 0;
                                        $totalOpeningCredit = 0;
                                        $totalPeriodDebit = 0;
                                        $totalPeriodCredit = 0;
                                        $totalClosingDebit = 0;
                                        $totalClosingCredit = 0;
                                        $currentClass = null;
                                    @endphp

                                    @foreach($accounts as $account)
                                        @php
                                            // Detect class change (first digit of account code)
                                            $accountClass = substr($account->account_code, 0, 1);
                                            
                                            // Calculate opening balance (before period)
                                            $openingDebit = 0;
                                            $openingCredit = 0;
                                            if ($account->normal_balance == 'debit') {
                                                $openingBalance = $account->opening_balance;
                                                $openingDebit = $openingBalance > 0 ? $openingBalance : 0;
                                                $openingCredit = $openingBalance < 0 ? abs($openingBalance) : 0;
                                            } else {
                                                $openingBalance = $account->opening_balance;
                                                $openingCredit = $openingBalance > 0 ? $openingBalance : 0;
                                                $openingDebit = $openingBalance < 0 ? abs($openingBalance) : 0;
                                            }

                                            // Period movements
                                            $periodDebit = $account->period_debit;
                                            $periodCredit = $account->period_credit;

                                            // Calculate closing balance
                                            if ($account->normal_balance == 'debit') {
                                                $closingBalance = $openingBalance + $periodDebit - $periodCredit;
                                                $closingDebit = $closingBalance > 0 ? $closingBalance : 0;
                                                $closingCredit = $closingBalance < 0 ? abs($closingBalance) : 0;
                                            } else {
                                                $closingBalance = $openingBalance + $periodCredit - $periodDebit;
                                                $closingCredit = $closingBalance > 0 ? $closingBalance : 0;
                                                $closingDebit = $closingBalance < 0 ? abs($closingBalance) : 0;
                                            }

                                            $totalOpeningDebit += $openingDebit;
                                            $totalOpeningCredit += $openingCredit;
                                            $totalPeriodDebit += $periodDebit;
                                            $totalPeriodCredit += $periodCredit;
                                            $totalClosingDebit += $closingDebit;
                                            $totalClosingCredit += $closingCredit;
                                        @endphp

                                        <!-- Class Header -->
                                        @if($currentClass != $accountClass)
                                            @php $currentClass = $accountClass; @endphp
                                            <tr class="table-secondary">
                                                <td colspan="8">
                                                    <strong>{{ __('class') }} {{ $accountClass }}: {{ __('ohada_class_' . $accountClass . '_name') }}</strong>
                                                </td>
                                            </tr>
                                        @endif

                                        <!-- Account Row -->
                                        <tr>
                                            <td><strong>{{ $account->account_code }}</strong></td>
                                            <td>
                                                <a href="{{ route('admin.general-ledger.account', $account->id) }}">
                                                    {{ $account->account_name }}
                                                </a>
                                                @if($account->account_name_fr)
                                                <br><small class="text-muted">{{ $account->account_name_fr }}</small>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                {{ $openingDebit > 0 ? number_format($openingDebit, 0, ',', ' ') : '-' }}
                                            </td>
                                            <td class="text-right">
                                                {{ $openingCredit > 0 ? number_format($openingCredit, 0, ',', ' ') : '-' }}
                                            </td>
                                            <td class="text-right">
                                                {{ $periodDebit > 0 ? number_format($periodDebit, 0, ',', ' ') : '-' }}
                                            </td>
                                            <td class="text-right">
                                                {{ $periodCredit > 0 ? number_format($periodCredit, 0, ',', ' ') : '-' }}
                                            </td>
                                            <td class="text-right">
                                                {{ $closingDebit > 0 ? number_format($closingDebit, 0, ',', ' ') : '-' }}
                                            </td>
                                            <td class="text-right">
                                                {{ $closingCredit > 0 ? number_format($closingCredit, 0, ',', ' ') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach

                                    <!-- Grand Total Row -->
                                    <tr class="table-success">
                                        <td colspan="2" class="text-right"><strong>{{ __('total') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalOpeningDebit, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalOpeningCredit, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalPeriodDebit, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalPeriodCredit, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalClosingDebit, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalClosingCredit, 0, ',', ' ') }}</strong></td>
                                    </tr>

                                    <!-- Verification Row -->
                                    <tr class="table-info">
                                        <td colspan="2" class="text-right"><strong>{{ __('difference') }}</strong></td>
                                        <td class="text-right">
                                            <span class="badge badge-{{ abs($totalOpeningDebit - $totalOpeningCredit) < 0.01 ? 'success' : 'danger' }}">
                                                {{ number_format(abs($totalOpeningDebit - $totalOpeningCredit), 0, ',', ' ') }}
                                            </span>
                                        </td>
                                        <td></td>
                                        <td class="text-right">
                                            <span class="badge badge-{{ abs($totalPeriodDebit - $totalPeriodCredit) < 0.01 ? 'success' : 'danger' }}">
                                                {{ number_format(abs($totalPeriodDebit - $totalPeriodCredit), 0, ',', ' ') }}
                                            </span>
                                        </td>
                                        <td></td>
                                        <td class="text-right">
                                            <span class="badge badge-{{ abs($totalClosingDebit - $totalClosingCredit) < 0.01 ? 'success' : 'danger' }}">
                                                {{ number_format(abs($totalClosingDebit - $totalClosingCredit), 0, ',', ' ') }}
                                            </span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Verification Alert -->
                        @php
                            $isBalanced = abs($totalClosingDebit - $totalClosingCredit) < 0.01;
                        @endphp
                        <div class="row mt-3">
                            <div class="col-md-12">
                                @if($isBalanced)
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> <strong>{{ __('trial_balance_verified') }}:</strong> 
                                    {{ __('debits_equal_credits') }}
                                </div>
                                @else
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <strong>{{ __('trial_balance_error') }}:</strong> 
                                    {{ __('debits_do_not_equal_credits') }} 
                                    ({{ __('difference') }}: {{ number_format(abs($totalClosingDebit - $totalClosingCredit), 0, ',', ' ') }} FCFA)
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Summary Information -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <strong>{{ __('report_information') }}:</strong><br>
                                    {{ __('total_accounts_included') }}: {{ $accounts->count() }}<br>
                                    {{ __('report_generated_at') }}: {{ \Carbon\Carbon::now()->format('d M Y H:i:s') }}<br>
                                    @if($startDate && $endDate)
                                    {{ __('reporting_period') }}: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                                    @endif
                                </div>
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
        .card-header .card-tools {
            display: none !important;
        }
        body {
            font-size: 10px;
        }
        .table-sm td, .table-sm th {
            padding: 0.2rem !important;
            font-size: 10px;
        }
        .print-header {
            margin-bottom: 20px;
        }
        @page {
            size: landscape;
            margin: 1cm;
        }
    }
</style>
@endpush

@push('scripts')
<script>
function exportToExcel() {
    // Simple table to Excel export
    var table = document.getElementById('trialBalanceTable');
    var html = table.outerHTML;
    var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    var link = document.createElement("a");
    link.href = url;
    link.download = 'trial_balance_' + new Date().toISOString().slice(0,10) + '.xls';
    link.click();
}
</script>
@endpush
