@extends('admin.layouts.master')

@section('title', __('Cash Flow Statement'))

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ __('Cash Flow Statement') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.accounting-reports.index') }}">{{ __('Reports') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Cash Flow Statement') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filters -->
            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> {{ __('Report Filters') }}</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.accounting-reports.cash-flow-statement') }}">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="fiscal_year_id">{{ __('Fiscal Year') }}</label>
                                    <select class="form-control" id="fiscal_year_id" name="fiscal_year_id">
                                        @foreach($fiscalYears ?? [] as $fy)
                                            <option value="{{ $fy->id }}" {{ ($fiscalYear->id ?? 0) == $fy->id ? 'selected' : '' }}>
                                                {{ $fy->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date">{{ __('Start Date') }}</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="{{ $startDate ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end_date">{{ __('End Date') }}</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="{{ $endDate ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fas fa-search"></i> {{ __('Generate Report') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cash Flow Statement -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ __('Cash Flow Statement') }}
                        @if(isset($fiscalYear))
                        - {{ $fiscalYear->name }}
                        @endif
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> {{ __('Export Excel') }}
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="exportToPdf()">
                            <i class="fas fa-file-pdf"></i> {{ __('Export PDF') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @if(isset($statement))
                    <div class="row">
                        <div class="col-md-8 offset-md-2">
                            <div class="text-center mb-4">
                                <h4>{{ config('app.name') }}</h4>
                                <h5>{{ __('Cash Flow Statement') }}</h5>
                                <p class="text-muted">
                                    {{ __('For the Period') }} {{ $startDate ?? '' }} {{ __('to') }} {{ $endDate ?? '' }}
                                </p>
                            </div>

                            <!-- Operating Activities -->
                            <div class="card card-outline card-primary mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">{{ __('Cash Flows from Operating Activities') }}</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tbody>
                                            @foreach($statement['operating']['items'] ?? [] as $item)
                                            <tr>
                                                <td style="padding-left: 20px;">{{ $item['name'] }}</td>
                                                <td class="text-right {{ $item['amount'] < 0 ? 'text-danger' : '' }}">
                                                    {{ number_format($item['amount']) }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="font-weight-bold">
                                            <tr>
                                                <td>{{ __('Net Cash from Operating Activities') }}</td>
                                                <td class="text-right {{ ($statement['operating']['total'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($statement['operating']['total'] ?? 0) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Investing Activities -->
                            <div class="card card-outline card-info mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">{{ __('Cash Flows from Investing Activities') }}</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tbody>
                                            @foreach($statement['investing']['items'] ?? [] as $item)
                                            <tr>
                                                <td style="padding-left: 20px;">{{ $item['name'] }}</td>
                                                <td class="text-right {{ $item['amount'] < 0 ? 'text-danger' : '' }}">
                                                    {{ number_format($item['amount']) }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="font-weight-bold">
                                            <tr>
                                                <td>{{ __('Net Cash from Investing Activities') }}</td>
                                                <td class="text-right {{ ($statement['investing']['total'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($statement['investing']['total'] ?? 0) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Financing Activities -->
                            <div class="card card-outline card-warning mb-4">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0">{{ __('Cash Flows from Financing Activities') }}</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tbody>
                                            @foreach($statement['financing']['items'] ?? [] as $item)
                                            <tr>
                                                <td style="padding-left: 20px;">{{ $item['name'] }}</td>
                                                <td class="text-right {{ $item['amount'] < 0 ? 'text-danger' : '' }}">
                                                    {{ number_format($item['amount']) }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="font-weight-bold">
                                            <tr>
                                                <td>{{ __('Net Cash from Financing Activities') }}</td>
                                                <td class="text-right {{ ($statement['financing']['total'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ number_format($statement['financing']['total'] ?? 0) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="card bg-light">
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr class="font-weight-bold">
                                            <td>{{ __('Net Increase (Decrease) in Cash') }}</td>
                                            <td class="text-right {{ ($statement['net_change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($statement['net_change'] ?? 0) }} {{ __('XAF') }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Cash at Beginning of Period') }}</td>
                                            <td class="text-right">
                                                {{ number_format($statement['opening_cash'] ?? 0) }} {{ __('XAF') }}
                                            </td>
                                        </tr>
                                        <tr class="border-top font-weight-bold" style="font-size: 1.1em;">
                                            <td>{{ __('Cash at End of Period') }}</td>
                                            <td class="text-right text-primary">
                                                {{ number_format($statement['closing_cash'] ?? 0) }} {{ __('XAF') }}
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>{{ __('Select a fiscal year and date range to generate the cash flow statement') }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
function exportToExcel() {
    window.location.href = "{{ route('admin.accounting-reports.export-excel') }}?report=cash-flow&fiscal_year_id={{ $fiscalYear->id ?? '' }}&start_date={{ $startDate ?? '' }}&end_date={{ $endDate ?? '' }}";
}

function exportToPdf() {
    window.location.href = "{{ route('admin.accounting-reports.export-pdf') }}?report=cash-flow&fiscal_year_id={{ $fiscalYear->id ?? '' }}&start_date={{ $startDate ?? '' }}&end_date={{ $endDate ?? '' }}";
}
</script>
@endsection
