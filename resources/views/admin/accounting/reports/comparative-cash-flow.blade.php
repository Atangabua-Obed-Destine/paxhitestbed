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
                    @php
                        $pct = fn ($v) => number_format($v, 1) . '%';
                        $money = fn ($v) => format_amount($v, true);
                    @endphp
                    <table class="table table-bordered table-hover">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>{{ __('Cash Flow Category') }}</th>
                                <th class="text-right">{{ $statement['periods']['current']['name'] }}</th>
                                <th class="text-right">{{ $statement['periods']['previous']['name'] }}</th>
                                <th class="text-right">{{ __('Change') }}</th>
                                <th class="text-right">{{ __('% Change') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['operating' => __('Operating Activities'), 'investing' => __('Investing Activities'), 'financing' => __('Financing Activities')] as $key => $label)
                            <tr class="bg-light font-weight-bold">
                                <td colspan="5">{{ $label }}</td>
                            </tr>
                            @forelse($statement[$key]['items'] as $item)
                            <tr>
                                <td style="padding-left: 30px;">{{ $item['description'] }}</td>
                                <td class="text-right">{{ $money($item['current']) }}</td>
                                <td class="text-right">{{ $money($item['previous']) }}</td>
                                <td class="text-right {{ $item['variance'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($item['variance']) }}</td>
                                <td class="text-right">{{ $pct($item['change_percent']) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-muted" style="padding-left: 30px;">{{ __('No movement') }}</td></tr>
                            @endforelse
                            <tr class="font-weight-bold">
                                <td>{{ __('Net cash from') }} {{ strtolower($label) }}</td>
                                <td class="text-right">{{ $money($statement[$key]['current_total']) }}</td>
                                <td class="text-right">{{ $money($statement[$key]['previous_total']) }}</td>
                                <td class="text-right {{ $statement[$key]['variance'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($statement[$key]['variance']) }}</td>
                                <td class="text-right">{{ $pct($statement[$key]['change_percent']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-secondary text-white font-weight-bold">
                            <tr>
                                <td>{{ __('Net Change in Cash') }}</td>
                                <td class="text-right">{{ $money($statement['net_change']['current']) }}</td>
                                <td class="text-right">{{ $money($statement['net_change']['previous']) }}</td>
                                <td class="text-right">{{ $money($statement['net_change']['variance']) }}</td>
                                <td class="text-right">{{ $pct($statement['net_change']['change_percent']) }}</td>
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
