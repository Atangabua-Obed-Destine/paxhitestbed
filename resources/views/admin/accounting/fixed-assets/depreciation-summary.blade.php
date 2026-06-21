@extends('admin.layouts.master')
@section('title', __('depreciation_summary'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('depreciation_summary') }} ({{ __('resume_amortissement') }})</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.fixed-assets.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                            <a href="{{ route('admin.fixed-assets.depreciation-report') }}" class="btn btn-info btn-sm">
                                <i class="fas fa-list"></i> {{ __('detailed_report') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Fiscal Year Filter -->
                        <form action="{{ route('admin.fixed-assets.depreciation-summary') }}" method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fiscal_year_id">{{ __('fiscal_year') }}</label>
                                        <select name="fiscal_year_id" id="fiscal_year_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                            @foreach($fiscalYears as $year)
                                            <option value="{{ $year->id }}" {{ ($currentFiscalYear && $currentFiscalYear->id == $year->id) ? 'selected' : '' }}>
                                                {{ $year->name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>

                        @if($currentFiscalYear)
                        <div class="alert alert-info">
                            <i class="fas fa-calendar"></i> 
                            {{ __('showing_data_for') }}: <strong>{{ $currentFiscalYear->name }}</strong>
                            ({{ \Carbon\Carbon::parse($currentFiscalYear->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($currentFiscalYear->end_date)->format('d/m/Y') }})
                        </div>
                        @endif

                        <!-- Summary by Category -->
                        <div class="row">
                            @forelse($summary as $item)
                            <div class="col-md-6 col-lg-4">
                                <div class="card card-outline card-primary">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-folder"></i> {{ $item['category_name'] }}
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <td>{{ __('number_of_assets') }}:</td>
                                                <td class="text-right"><strong>{{ $item['asset_count'] ?? 0 }}</strong></td>
                                            </tr>
                                            <tr>
                                                <td>{{ __('total_acquisition_cost') }}:</td>
                                                <td class="text-right">{{ number_format($item['total_acquisition_cost'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr>
                                                <td>{{ __('depreciation_this_year') }}:</td>
                                                <td class="text-right text-warning">{{ number_format($item['depreciation_this_year'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr>
                                                <td>{{ __('accumulated_depreciation') }}:</td>
                                                <td class="text-right text-danger">{{ number_format($item['accumulated_depreciation'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr class="border-top">
                                                <td><strong>{{ __('net_book_value') }}:</strong></td>
                                                <td class="text-right"><strong class="text-success">{{ number_format($item['net_book_value'] ?? 0, 0, ',', ' ') }} FCFA</strong></td>
                                            </tr>
                                        </table>
                                        
                                        @php
                                            $pct = $item['total_acquisition_cost'] > 0 
                                                ? (($item['accumulated_depreciation'] ?? 0) / $item['total_acquisition_cost']) * 100 
                                                : 0;
                                        @endphp
                                        <div class="progress mt-2" style="height: 10px;">
                                            <div class="progress-bar bg-warning" style="width: {{ min(100, $pct) }}%;" 
                                                 title="{{ number_format($pct, 1) }}% {{ __('depreciated') }}"></div>
                                        </div>
                                        <small class="text-muted">{{ number_format($pct, 1) }}% {{ __('depreciated') }}</small>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> {{ __('no_data_found') }}
                                </div>
                            </div>
                            @endforelse
                        </div>

                        <!-- Grand Totals -->
                        @if(count($summary) > 0)
                        <div class="card card-outline card-success mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="fas fa-calculator"></i> {{ __('grand_total') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-primary">{{ collect($summary)->sum('asset_count') }}</h4>
                                            <small class="text-muted">{{ __('total_assets') }}</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-info">{{ number_format(collect($summary)->sum('total_acquisition_cost'), 0, ',', ' ') }}</h4>
                                            <small class="text-muted">{{ __('total_acquisition_cost') }} (FCFA)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-warning">{{ number_format(collect($summary)->sum('depreciation_this_year'), 0, ',', ' ') }}</h4>
                                            <small class="text-muted">{{ __('depreciation_this_year') }} (FCFA)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-success">{{ number_format(collect($summary)->sum('net_book_value'), 0, ',', ' ') }}</h4>
                                            <small class="text-muted">{{ __('net_book_value') }} (FCFA)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
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
