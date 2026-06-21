@extends('admin.layouts.master')
@section('title', __('budget_vs_actual'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('budget_vs_actual') }} ({{ __('budget_realise') }})</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> {{ __('export') }}
                            </button>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Filters -->
                        <form action="{{ route('admin.accounting-reports.budget-vs-actual') }}" method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fiscal_year_id">{{ __('fiscal_year') }}</label>
                                        <select name="fiscal_year_id" id="fiscal_year_id" class="form-control form-control-sm">
                                            @foreach($fiscalYears as $year)
                                            <option value="{{ $year->id }}" {{ request('fiscal_year_id', $activeFiscalYear->id ?? '') == $year->id ? 'selected' : '' }}>
                                                {{ $year->name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="period">{{ __('period') }}</label>
                                        <select name="period" id="period" class="form-control form-control-sm">
                                            <option value="ytd" {{ request('period', 'ytd') == 'ytd' ? 'selected' : '' }}>{{ __('year_to_date') }}</option>
                                            <option value="monthly" {{ request('period') == 'monthly' ? 'selected' : '' }}>{{ __('monthly') }}</option>
                                            <option value="quarterly" {{ request('period') == 'quarterly' ? 'selected' : '' }}>{{ __('quarterly') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="month">{{ __('month') }}</label>
                                        <input type="month" name="month" id="month" class="form-control form-control-sm" value="{{ request('month', date('Y-m')) }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                                            <i class="fas fa-sync"></i> {{ __('generate') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('total_budget') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['budget'] ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box bg-primary">
                                    <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('total_actual') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['actual'] ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box bg-{{ ($totals['variance'] ?? 0) >= 0 ? 'success' : 'danger' }}">
                                    <span class="info-box-icon"><i class="fas fa-balance-scale"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('variance') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['variance'] ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-percentage"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('variance_percentage') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['variance_pct'] ?? 0, 1) }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue Section -->
                        <div class="card">
                            <div class="card-header bg-success">
                                <h5 class="card-title mb-0"><i class="fas fa-arrow-up"></i> {{ __('revenue') }}</h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ __('account') }}</th>
                                            <th class="text-right">{{ __('budget') }}</th>
                                            <th class="text-right">{{ __('actual') }}</th>
                                            <th class="text-right">{{ __('variance') }}</th>
                                            <th class="text-right">{{ __('percentage') }}</th>
                                            <th width="15%">{{ __('progress') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($revenue ?? [] as $item)
                                        <tr>
                                            <td>
                                                <strong>{{ $item['account_code'] }}</strong> - {{ $item['account_name'] }}
                                            </td>
                                            <td class="text-right">{{ number_format($item['budget'], 0, ',', ' ') }}</td>
                                            <td class="text-right">{{ number_format($item['actual'], 0, ',', ' ') }}</td>
                                            <td class="text-right {{ $item['variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance'], 0, ',', ' ') }}
                                            </td>
                                            <td class="text-right">{{ number_format($item['variance_pct'], 1) }}%</td>
                                            <td>
                                                @php $pct = min(100, max(0, $item['achievement_pct'] ?? 0)); @endphp
                                                <div class="progress" style="height: 15px;">
                                                    <div class="progress-bar bg-{{ $pct >= 100 ? 'success' : ($pct >= 75 ? 'info' : ($pct >= 50 ? 'warning' : 'danger')) }}" 
                                                         style="width: {{ $pct }}%;">{{ number_format($pct, 0) }}%</div>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">{{ __('no_data_found') }}</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="table-success">
                                        <tr>
                                            <th>{{ __('total_revenue') }}</th>
                                            <th class="text-right">{{ number_format($revenueTotals['budget'] ?? 0, 0, ',', ' ') }}</th>
                                            <th class="text-right">{{ number_format($revenueTotals['actual'] ?? 0, 0, ',', ' ') }}</th>
                                            <th class="text-right">{{ number_format($revenueTotals['variance'] ?? 0, 0, ',', ' ') }}</th>
                                            <th class="text-right">{{ number_format($revenueTotals['variance_pct'] ?? 0, 1) }}%</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Expenses Section -->
                        <div class="card mt-3">
                            <div class="card-header bg-danger">
                                <h5 class="card-title mb-0"><i class="fas fa-arrow-down"></i> {{ __('expenses') }}</h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ __('account') }}</th>
                                            <th class="text-right">{{ __('budget') }}</th>
                                            <th class="text-right">{{ __('actual') }}</th>
                                            <th class="text-right">{{ __('variance') }}</th>
                                            <th class="text-right">{{ __('percentage') }}</th>
                                            <th width="15%">{{ __('progress') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($expenses ?? [] as $item)
                                        <tr class="{{ ($item['actual'] > $item['budget']) ? 'table-danger' : '' }}">
                                            <td>
                                                <strong>{{ $item['account_code'] }}</strong> - {{ $item['account_name'] }}
                                            </td>
                                            <td class="text-right">{{ number_format($item['budget'], 0, ',', ' ') }}</td>
                                            <td class="text-right">{{ number_format($item['actual'], 0, ',', ' ') }}</td>
                                            <td class="text-right {{ $item['variance'] <= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance'], 0, ',', ' ') }}
                                            </td>
                                            <td class="text-right">{{ number_format($item['variance_pct'], 1) }}%</td>
                                            <td>
                                                @php 
                                                    $pct = $item['budget'] > 0 ? ($item['actual'] / $item['budget']) * 100 : 0;
                                                    $pct = min(150, max(0, $pct));
                                                @endphp
                                                <div class="progress" style="height: 15px;">
                                                    <div class="progress-bar bg-{{ $pct > 100 ? 'danger' : ($pct > 90 ? 'warning' : 'success') }}" 
                                                         style="width: {{ min(100, $pct) }}%;">{{ number_format(min(100, $pct), 0) }}%</div>
                                                    @if($pct > 100)
                                                    <div class="progress-bar bg-danger" style="width: {{ min(50, $pct - 100) }}%;"></div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">{{ __('no_data_found') }}</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="table-danger">
                                        <tr>
                                            <th>{{ __('total_expenses') }}</th>
                                            <th class="text-right">{{ number_format($expenseTotals['budget'] ?? 0, 0, ',', ' ') }}</th>
                                            <th class="text-right">{{ number_format($expenseTotals['actual'] ?? 0, 0, ',', ' ') }}</th>
                                            <th class="text-right">{{ number_format($expenseTotals['variance'] ?? 0, 0, ',', ' ') }}</th>
                                            <th class="text-right">{{ number_format($expenseTotals['variance_pct'] ?? 0, 1) }}%</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Net Result -->
                        <div class="card mt-3">
                            <div class="card-header bg-primary">
                                <h5 class="card-title mb-0"><i class="fas fa-calculator"></i> {{ __('net_result') }}</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <td width="40%">{{ __('budgeted_net_income') }}</td>
                                        <td class="text-right">{{ number_format(($revenueTotals['budget'] ?? 0) - ($expenseTotals['budget'] ?? 0), 0, ',', ' ') }} FCFA</td>
                                    </tr>
                                    <tr>
                                        <td>{{ __('actual_net_income') }}</td>
                                        <td class="text-right">{{ number_format(($revenueTotals['actual'] ?? 0) - ($expenseTotals['actual'] ?? 0), 0, ',', ' ') }} FCFA</td>
                                    </tr>
                                    <tr class="table-primary font-weight-bold">
                                        <td>{{ __('variance') }}</td>
                                        @php
                                            $netVariance = (($revenueTotals['actual'] ?? 0) - ($expenseTotals['actual'] ?? 0)) - (($revenueTotals['budget'] ?? 0) - ($expenseTotals['budget'] ?? 0));
                                        @endphp
                                        <td class="text-right {{ $netVariance >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $netVariance >= 0 ? '+' : '' }}{{ number_format($netVariance, 0, ',', ' ') }} FCFA
                                        </td>
                                    </tr>
                                </table>
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

@section('scripts')
<script>
function exportToExcel() {
    window.location.href = "{{ route('admin.accounting-reports.export-excel') }}?report=budget-vs-actual&fiscal_year_id={{ request('fiscal_year_id') }}&period={{ request('period', 'ytd') }}&month={{ request('month', date('Y-m')) }}";
}
</script>
@endsection
