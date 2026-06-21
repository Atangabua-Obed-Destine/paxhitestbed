@extends('admin.layouts.master')

@section('title', __('Comparative Cash Flow'))

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ __('Comparative Cash Flow') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.accounting-reports.index') }}">{{ __('Reports') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Comparative Cash Flow') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filters -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> {{ __('Report Filters') }}</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.accounting-reports.comparative-cash-flow') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="fiscal_year_id">{{ __('Current Fiscal Year') }}</label>
                                    <select class="form-control" id="fiscal_year_id" name="fiscal_year_id">
                                        @foreach($fiscalYears ?? [] as $fy)
                                            <option value="{{ $fy->id }}" {{ ($currentFiscalYear->id ?? 0) == $fy->id ? 'selected' : '' }}>
                                                {{ $fy->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-search"></i> {{ __('Generate Report') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Comparative Statement -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Comparative Cash Flow Statement') }}</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> {{ __('Export Excel') }}
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="exportToPdf()">
                            <i class="fas fa-file-pdf"></i> {{ __('Export PDF') }}
                        </button>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    @if(isset($statement))
                    <table class="table table-bordered table-hover">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>{{ __('Cash Flow Category') }}</th>
                                @foreach($statement['periods'] ?? [] as $period)
                                <th class="text-right">{{ $period }}</th>
                                @endforeach
                                <th class="text-right">{{ __('Change') }}</th>
                                <th class="text-right">{{ __('% Change') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Operating Activities -->
                            <tr class="bg-light font-weight-bold">
                                <td colspan="{{ count($statement['periods'] ?? []) + 3 }}">
                                    {{ __('Operating Activities') }}
                                </td>
                            </tr>
                            @foreach($statement['operating']['items'] ?? [] as $item)
                            <tr>
                                <td style="padding-left: 30px;">{{ $item['name'] }}</td>
                                @foreach($item['values'] ?? [] as $value)
                                <td class="text-right">{{ number_format($value) }}</td>
                                @endforeach
                                <td class="text-right {{ ($item['change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($item['change'] ?? 0) }}
                                </td>
                                <td class="text-right {{ ($item['change_percent'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($item['change_percent'] ?? 0, 1) }}%
                                </td>
                            </tr>
                            @endforeach
                            <tr class="font-weight-bold">
                                <td>{{ __('Net Cash from Operating') }}</td>
                                @foreach($statement['operating']['totals'] ?? [] as $total)
                                <td class="text-right">{{ number_format($total) }}</td>
                                @endforeach
                                <td class="text-right {{ ($statement['operating']['change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($statement['operating']['change'] ?? 0) }}
                                </td>
                                <td class="text-right {{ ($statement['operating']['change_percent'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($statement['operating']['change_percent'] ?? 0, 1) }}%
                                </td>
                            </tr>

                            <!-- Investing Activities -->
                            <tr class="bg-light font-weight-bold">
                                <td colspan="{{ count($statement['periods'] ?? []) + 3 }}">
                                    {{ __('Investing Activities') }}
                                </td>
                            </tr>
                            @foreach($statement['investing']['items'] ?? [] as $item)
                            <tr>
                                <td style="padding-left: 30px;">{{ $item['name'] }}</td>
                                @foreach($item['values'] ?? [] as $value)
                                <td class="text-right">{{ number_format($value) }}</td>
                                @endforeach
                                <td class="text-right {{ ($item['change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($item['change'] ?? 0) }}
                                </td>
                                <td class="text-right {{ ($item['change_percent'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($item['change_percent'] ?? 0, 1) }}%
                                </td>
                            </tr>
                            @endforeach
                            <tr class="font-weight-bold">
                                <td>{{ __('Net Cash from Investing') }}</td>
                                @foreach($statement['investing']['totals'] ?? [] as $total)
                                <td class="text-right">{{ number_format($total) }}</td>
                                @endforeach
                                <td class="text-right {{ ($statement['investing']['change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($statement['investing']['change'] ?? 0) }}
                                </td>
                                <td class="text-right {{ ($statement['investing']['change_percent'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($statement['investing']['change_percent'] ?? 0, 1) }}%
                                </td>
                            </tr>

                            <!-- Financing Activities -->
                            <tr class="bg-light font-weight-bold">
                                <td colspan="{{ count($statement['periods'] ?? []) + 3 }}">
                                    {{ __('Financing Activities') }}
                                </td>
                            </tr>
                            @foreach($statement['financing']['items'] ?? [] as $item)
                            <tr>
                                <td style="padding-left: 30px;">{{ $item['name'] }}</td>
                                @foreach($item['values'] ?? [] as $value)
                                <td class="text-right">{{ number_format($value) }}</td>
                                @endforeach
                                <td class="text-right {{ ($item['change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($item['change'] ?? 0) }}
                                </td>
                                <td class="text-right {{ ($item['change_percent'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($item['change_percent'] ?? 0, 1) }}%
                                </td>
                            </tr>
                            @endforeach
                            <tr class="font-weight-bold">
                                <td>{{ __('Net Cash from Financing') }}</td>
                                @foreach($statement['financing']['totals'] ?? [] as $total)
                                <td class="text-right">{{ number_format($total) }}</td>
                                @endforeach
                                <td class="text-right {{ ($statement['financing']['change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($statement['financing']['change'] ?? 0) }}
                                </td>
                                <td class="text-right {{ ($statement['financing']['change_percent'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($statement['financing']['change_percent'] ?? 0, 1) }}%
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-secondary text-white font-weight-bold">
                            <tr>
                                <td>{{ __('Net Change in Cash') }}</td>
                                @foreach($statement['net_changes'] ?? [] as $change)
                                <td class="text-right">{{ number_format($change) }}</td>
                                @endforeach
                                <td class="text-right">{{ number_format($statement['total_change'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($statement['total_change_percent'] ?? 0, 1) }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                    @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>{{ __('Select a fiscal year to generate the comparative cash flow statement') }}</p>
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
    window.location.href = "{{ route('admin.accounting-reports.export-excel') }}?report=comparative-cash-flow&fiscal_year_id={{ $currentFiscalYear->id ?? '' }}";
}

function exportToPdf() {
    window.location.href = "{{ route('admin.accounting-reports.export-pdf') }}?report=comparative-cash-flow&fiscal_year_id={{ $currentFiscalYear->id ?? '' }}";
}
</script>
@endsection
