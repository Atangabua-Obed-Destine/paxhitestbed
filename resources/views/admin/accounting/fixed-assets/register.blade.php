@extends('admin.layouts.master')
@section('title', __('asset_register'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('asset_register') }} ({{ __('registre_immobilisations') }})</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.fixed-assets.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                            <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> {{ __('export_excel') }}
                            </button>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Filters -->
                        <form action="{{ route('admin.fixed-assets.register') }}" method="GET" class="mb-4">
                            <div class="row">
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
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="department_id">{{ __('department') }}</label>
                                        <select name="department_id" id="department_id" class="form-control form-control-sm">
                                            <option value="">{{ __('all_departments') }}</option>
                                            @foreach($departments as $department)
                                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                                {{ $department->title }}
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
                                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('active') }}</option>
                                            <option value="disposed" {{ request('status') == 'disposed' ? 'selected' : '' }}>{{ __('disposed') }}</option>
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
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-building"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('total_acquisition_cost') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['acquisition_cost'] ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('accumulated_depreciation') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['accumulated_depreciation'] ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-balance-scale"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ __('net_book_value') }}</span>
                                        <span class="info-box-number">{{ number_format($totals['book_value'] ?? 0, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Asset Register Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover" id="assetRegisterTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('asset_code') }}</th>
                                        <th>{{ __('asset_name') }}</th>
                                        <th>{{ __('category') }}</th>
                                        <th>{{ __('acquisition_date') }}</th>
                                        <th class="text-right">{{ __('acquisition_cost') }}</th>
                                        <th class="text-right">{{ __('accumulated_depreciation') }}</th>
                                        <th class="text-right">{{ __('net_book_value') }}</th>
                                        <th>{{ __('status_title') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($assets as $asset)
                                    <tr>
                                        <td><strong>{{ $asset->asset_code }}</strong></td>
                                        <td>
                                            {{ $asset->name }}
                                            @if($asset->serial_number)
                                            <br><small class="text-muted">S/N: {{ $asset->serial_number }}</small>
                                            @endif
                                        </td>
                                        <td><span class="badge badge-info">{{ $asset->category->name ?? 'N/A' }}</span></td>
                                        <td>{{ \Carbon\Carbon::parse($asset->acquisition_date)->format('d/m/Y') }}</td>
                                        <td class="text-right">{{ number_format($asset->acquisition_cost, 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($asset->accumulated_depreciation, 0, ',', ' ') }}</td>
                                        <td class="text-right">
                                            <strong>{{ number_format($asset->book_value, 0, ',', ' ') }}</strong>
                                        </td>
                                        <td>
                                            @if($asset->status == 'active')
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @elseif($asset->status == 'disposed')
                                            <span class="badge badge-danger">{{ __('disposed') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __($asset->status) }}</span>
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
                                        <td class="text-right">{{ number_format($totals['acquisition_cost'] ?? 0, 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($totals['accumulated_depreciation'] ?? 0, 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($totals['book_value'] ?? 0, 0, ',', ' ') }}</td>
                                        <td></td>
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

@section('scripts')
<script>
function exportToExcel() {
    // Export functionality
    window.location.href = "{{ route('admin.fixed-assets.register') }}?export=excel&" + new URLSearchParams(window.location.search).toString();
}
</script>
@endsection
