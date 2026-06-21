@extends('admin.layouts.master')
@section('title', __('depreciation_report'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('depreciation_report') }} ({{ __('rapport_amortissement') }})</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.fixed-assets.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                            <a href="{{ route('admin.fixed-assets.depreciation-summary') }}" class="btn btn-info btn-sm">
                                <i class="fas fa-chart-pie"></i> {{ __('summary_by_category') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Filters -->
                        <form action="{{ route('admin.fixed-assets.depreciation-report') }}" method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fiscal_year_id">{{ __('fiscal_year') }}</label>
                                        <select name="fiscal_year_id" id="fiscal_year_id" class="form-control form-control-sm">
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
                                        <label for="category_id">{{ __('category') }}</label>
                                        <select name="category_id" id="category_id" class="form-control form-control-sm">
                                            <option value="">{{ __('all_categories') }}</option>
                                            @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">{{ __('status_title') }}</label>
                                        <select name="status" id="status" class="form-control form-control-sm">
                                            <option value="">{{ __('all') }}</option>
                                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ __('pending') }}</option>
                                            <option value="posted" {{ request('status') == 'posted' ? 'selected' : '' }}>{{ __('posted') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                                            <i class="fas fa-filter"></i> {{ __('filter') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box bg-primary">
                                    <span class="info-box-icon"><i class="fas fa-calculator"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('total_depreciation') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['total_depreciation'] ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('pending_entries') }}</span>
                                        <span class="info-box-number">{{ $totals['pending_count'] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-check"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('posted_entries') }}</span>
                                        <span class="info-box-number">{{ $totals['posted_count'] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Depreciation Schedule Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('asset_code') }}</th>
                                        <th>{{ __('asset_name') }}</th>
                                        <th>{{ __('category') }}</th>
                                        <th>{{ __('period') }}</th>
                                        <th class="text-right">{{ __('depreciation_amount') }}</th>
                                        <th class="text-right">{{ __('accumulated') }}</th>
                                        <th class="text-right">{{ __('remaining_value') }}</th>
                                        <th>{{ __('status_title') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($schedules as $schedule)
                                    <tr>
                                        <td><strong>{{ $schedule->asset->asset_code ?? 'N/A' }}</strong></td>
                                        <td>{{ $schedule->asset->name ?? 'N/A' }}</td>
                                        <td><span class="badge badge-info">{{ $schedule->asset->category->name ?? 'N/A' }}</span></td>
                                        <td>{{ \Carbon\Carbon::parse($schedule->depreciation_date)->format('M Y') }}</td>
                                        <td class="text-right">{{ number_format($schedule->depreciation_amount, 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($schedule->accumulated_depreciation, 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($schedule->remaining_value, 0, ',', ' ') }}</td>
                                        <td>
                                            @if($schedule->status == 'pending')
                                            <span class="badge badge-warning">{{ __('pending') }}</span>
                                            @elseif($schedule->status == 'posted')
                                            <span class="badge badge-success">{{ __('posted') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __($schedule->status) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="thead-light font-weight-bold">
                                    <tr>
                                        <td colspan="4">{{ __('total') }}</td>
                                        <td class="text-right">{{ number_format($totals['total_depreciation'] ?? 0, 0, ',', ' ') }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
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
