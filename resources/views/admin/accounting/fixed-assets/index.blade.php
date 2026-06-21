@extends('admin.layouts.master')
@section('title', __('fixed_assets'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('fixed_assets') }} ({{ __('immobilisations') }})</h3>
                        <div class="card-tools">
                            @can('fixed-asset-list')
                            <a href="{{ route('admin.fixed-assets.depreciation-report') }}" class="btn btn-info btn-sm mr-2">
                                <i class="fas fa-chart-bar"></i> {{ __('depreciation_reports') }}
                            </a>
                            @endcan
                            @can('fixed-asset-create')
                            <a href="{{ route('admin.fixed-assets.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('add_fixed_asset') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Statistics Cards -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ $statistics['total_assets'] ?? 0 }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('total_assets') }}</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-building"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ number_format($statistics['total_cost'] ?? 0, 0, ',', ' ') }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('total_acquisition_cost') }} (FCFA)</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ number_format($statistics['total_depreciation'] ?? 0, 0, ',', ' ') }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('accumulated_depreciation') }} (FCFA)</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                                    <div class="inner">
                                        <h3 style="color: white;">{{ number_format($statistics['total_book_value'] ?? 0, 0, ',', ' ') }}</h3>
                                        <p style="color: rgba(255,255,255,0.9);">{{ __('net_book_value') }} (FCFA)</p>
                                    </div>
                                    <div class="icon" style="color: rgba(255,255,255,0.3);">
                                        <i class="fas fa-balance-scale"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <form action="{{ route('admin.fixed-assets.index') }}" method="GET">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="category_id">{{ __('category') }}</label>
                                                <select name="category_id" id="category_id" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
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
                                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('active') }}</option>
                                                    <option value="disposed" {{ request('status') == 'disposed' ? 'selected' : '' }}>{{ __('disposed') }}</option>
                                                    <option value="fully_depreciated" {{ request('status') == 'fully_depreciated' ? 'selected' : '' }}>{{ __('fully_depreciated') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="location">{{ __('location') }}</label>
                                                <input type="text" name="location" id="location" class="form-control form-control-sm" value="{{ request('location') }}" placeholder="{{ __('location') }}">
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
                            </div>
                        </div>

                        <!-- Depreciation Actions -->
                        @can('fixed-asset-depreciation')
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <strong><i class="fas fa-calculator"></i> {{ __('depreciation_actions') }}:</strong>
                                    <form action="{{ route('admin.fixed-assets.calculate-depreciation') }}" method="POST" class="d-inline ml-3">
                                        @csrf
                                        <input type="hidden" name="month" value="{{ date('Y-m') }}">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="fas fa-calculator"></i> {{ __('calculate_monthly_depreciation') }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.fixed-assets.post-depreciation') }}" method="POST" class="d-inline ml-2">
                                        @csrf
                                        <input type="hidden" name="month" value="{{ date('Y-m') }}">
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('{{ __('confirm_post_depreciation') }}')">
                                            <i class="fas fa-check"></i> {{ __('post_depreciation_entries') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endcan

                        <!-- Assets Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th width="10%">{{ __('asset_code') }}</th>
                                        <th>{{ __('asset_name') }}</th>
                                        <th width="10%">{{ __('category') }}</th>
                                        <th width="10%">{{ __('acquisition_date') }}</th>
                                        <th width="10%" class="text-right">{{ __('acquisition_cost') }}</th>
                                        <th width="10%" class="text-right">{{ __('net_book_value') }}</th>
                                        <th width="8%">{{ __('status_title') }}</th>
                                        <th width="12%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($assets as $key => $asset)
                                    <tr>
                                        <td>{{ $assets->firstItem() + $key }}</td>
                                        <td><strong>{{ $asset->asset_code }}</strong></td>
                                        <td>
                                            {{ $asset->name }}
                                            @if($asset->serial_number)
                                            <br><small class="text-muted">S/N: {{ $asset->serial_number }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $asset->category->name ?? 'N/A' }}</span>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') }}</td>
                                        <td class="text-right"><strong>{{ number_format($asset->acquisition_cost, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right">
                                            <strong>{{ number_format($asset->book_value ?? 0, 0, ',', ' ') }}</strong>
                                            @if(($asset->book_value ?? 0) <= 0)
                                            <br><small class="text-danger">{{ __('fully_depreciated') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($asset->status == 'active')
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @elseif($asset->status == 'disposed')
                                            <span class="badge badge-danger">{{ __('disposed') }}</span>
                                            @else
                                            <span class="badge badge-warning">{{ __($asset->status) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('fixed-asset-list')
                                            <a href="{{ route('admin.fixed-assets.show', $asset->id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('fixed-asset-edit')
                                            @if($asset->status == 'active')
                                            <a href="{{ route('admin.fixed-assets.edit', $asset->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            
                                            @can('fixed-asset-dispose')
                                            @if($asset->status == 'active')
                                            <button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-target="#disposeModal{{ $asset->id }}" title="{{ __('dispose') }}">
                                                <i class="fas fa-sign-out-alt"></i>
                                            </button>
                                            @endif
                                            @endcan
                                            
                                            @can('fixed-asset-delete')
                                            @if($asset->status == 'active' && $asset->accumulated_depreciation == 0)
                                            <form action="{{ route('admin.fixed-assets.destroy', $asset->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('{{ __('are_you_sure') }}')" title="{{ __('delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                        </td>
                                    </tr>
                                    
                                    <!-- Dispose Modal -->
                                    @can('fixed-asset-dispose')
                                    <div class="modal fade" id="disposeModal{{ $asset->id }}" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.fixed-assets.dispose', $asset->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ __('dispose_asset') }}: {{ $asset->name }}</h5>
                                                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="form-group">
                                                            <label>{{ __('disposal_date') }} <span class="text-danger">*</span></label>
                                                            <input type="date" name="disposal_date" class="form-control" required value="{{ date('Y-m-d') }}">
                                                        </div>
                                                        <div class="form-group">
                                                            <label>{{ __('disposal_method') }} <span class="text-danger">*</span></label>
                                                            <select name="disposal_method" class="form-control" required>
                                                                <option value="sale">{{ __('sale') }}</option>
                                                                <option value="scrap">{{ __('scrap') }}</option>
                                                                <option value="donation">{{ __('donation') }}</option>
                                                                <option value="theft">{{ __('theft') }}</option>
                                                                <option value="damage">{{ __('damage') }}</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>{{ __('disposal_proceeds') }}</label>
                                                            <input type="number" name="disposal_proceeds" class="form-control" value="0" min="0">
                                                            <small class="text-muted">{{ __('current_nbv') }}: {{ number_format($asset->book_value ?? 0, 0, ',', ' ') }} FCFA</small>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>{{ __('notes') }}</label>
                                                            <textarea name="notes" class="form-control" rows="2"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                                                        <button type="submit" class="btn btn-warning">{{ __('dispose_asset') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @endcan
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $assets->appends(request()->query())->links() }}
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
