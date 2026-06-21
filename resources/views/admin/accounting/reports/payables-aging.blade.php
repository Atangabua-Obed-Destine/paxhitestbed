@extends('admin.layouts.master')

@section('title', __('Payables Aging Report'))

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ __('Payables Aging Report') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.accounting-reports.index') }}">{{ __('Reports') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Payables Aging') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filters -->
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> {{ __('Report Filters') }}</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.accounting-reports.payables-aging') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="as_of_date">{{ __('As of Date') }}</label>
                                    <input type="date" class="form-control" id="as_of_date" name="as_of_date" 
                                           value="{{ $asOfDate }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-warning btn-block">
                                        <i class="fas fa-search"></i> {{ __('Generate Report') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary -->
            @if(isset($report['summary']))
            <div class="row">
                <div class="col-lg-2 col-md-4">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h4>{{ number_format($report['summary']['current'] ?? 0) }} {{ __('XAF') }}</h4>
                            <p>{{ __('Current (0-30 days)') }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h4>{{ number_format($report['summary']['days_31_60'] ?? 0) }} {{ __('XAF') }}</h4>
                            <p>{{ __('31-60 Days') }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h4>{{ number_format($report['summary']['days_61_90'] ?? 0) }} {{ __('XAF') }}</h4>
                            <p>{{ __('61-90 Days') }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="small-box bg-orange">
                        <div class="inner">
                            <h4>{{ number_format($report['summary']['days_91_120'] ?? 0) }} {{ __('XAF') }}</h4>
                            <p>{{ __('91-120 Days') }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h4>{{ number_format($report['summary']['over_120'] ?? 0) }} {{ __('XAF') }}</h4>
                            <p>{{ __('Over 120 Days') }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h4>{{ number_format($report['summary']['total'] ?? 0) }} {{ __('XAF') }}</h4>
                            <p>{{ __('Total Outstanding') }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-calculator"></i></div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Detailed Report -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Aging Details by Vendor') }}</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> {{ __('Export Excel') }}
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="exportToPdf()">
                            <i class="fas fa-file-pdf"></i> {{ __('Export PDF') }}
                        </button>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Vendor') }}</th>
                                <th class="text-right">{{ __('Current') }}</th>
                                <th class="text-right">{{ __('31-60 Days') }}</th>
                                <th class="text-right">{{ __('61-90 Days') }}</th>
                                <th class="text-right">{{ __('91-120 Days') }}</th>
                                <th class="text-right">{{ __('Over 120 Days') }}</th>
                                <th class="text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['details'] ?? [] as $item)
                            <tr>
                                <td>{{ $item['vendor_name'] }}</td>
                                <td class="text-right">{{ number_format($item['current'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($item['days_31_60'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($item['days_61_90'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($item['days_91_120'] ?? 0) }}</td>
                                <td class="text-right text-danger">{{ number_format($item['over_120'] ?? 0) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($item['total'] ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">{{ __('No payables data found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if(isset($report['summary']))
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td>{{ __('Grand Total') }}</td>
                                <td class="text-right">{{ number_format($report['summary']['current'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($report['summary']['days_31_60'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($report['summary']['days_61_90'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($report['summary']['days_91_120'] ?? 0) }}</td>
                                <td class="text-right text-danger">{{ number_format($report['summary']['over_120'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($report['summary']['total'] ?? 0) }}</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
function exportToExcel() {
    window.location.href = "{{ route('admin.accounting-reports.export-excel') }}?report=payables-aging&as_of_date={{ $asOfDate }}";
}

function exportToPdf() {
    window.location.href = "{{ route('admin.accounting-reports.export-pdf') }}?report=payables-aging&as_of_date={{ $asOfDate }}";
}
</script>
@endsection
