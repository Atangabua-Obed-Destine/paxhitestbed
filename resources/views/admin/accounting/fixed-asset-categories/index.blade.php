@extends('admin.layouts.master')
@section('title', __('fixed_asset_categories'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('fixed_asset_categories') }}</h3>
                        <div class="card-tools">
                            @can('fixed-assets-create')
                            <a href="{{ route('admin.fixed-asset-categories.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('add_category') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th width="10%">{{ __('code') }}</th>
                                        <th>{{ __('category_name') }}</th>
                                        <th width="12%">{{ __('depreciation_method') }}</th>
                                        <th width="10%">{{ __('useful_life') }}</th>
                                        <th width="12%">{{ __('asset_account') }}</th>
                                        <th width="8%">{{ __('assets_count') }}</th>
                                        <th width="12%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($categories as $key => $category)
                                    <tr>
                                        <td>{{ ($categories->currentPage() - 1) * $categories->perPage() + $key + 1 }}</td>
                                        <td><strong>{{ $category->code }}</strong></td>
                                        <td>
                                            {{ $category->name }}
                                            @if($category->name_fr)
                                            <br><small class="text-muted">{{ $category->name_fr }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ __(str_replace('_', ' ', $category->depreciation_method)) }}</span>
                                        </td>
                                        <td>{{ $category->default_useful_life }} {{ __('years') }}</td>
                                        <td>
                                            @if($category->assetAccount)
                                            {{ $category->assetAccount->account_code }}
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary">{{ $category->assets_count ?? 0 }}</span>
                                        </td>
                                        <td>
                                            @can('fixed-assets-edit')
                                            <a href="{{ route('admin.fixed-asset-categories.edit', $category->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('fixed-assets-delete')
                                            @if(($category->assets_count ?? 0) == 0)
                                            <form action="{{ route('admin.fixed-asset-categories.destroy', $category->id) }}" method="POST" style="display: inline-block;">
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
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($categories->hasPages())
                        <div class="mt-3">
                            {{ $categories->links() }}
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
