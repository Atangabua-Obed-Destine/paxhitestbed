@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-download text-primary"></i> {{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        @can($access.'-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('btn_add_new') }}</a>
                        @endcan

                        <a href="{{ route($route.'.index') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                        
                        <!-- Category Filter -->
                        <div class="dropdown d-inline-block ml-2">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="categoryFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-filter"></i> 
                                {{ $selected_category ? $categories[$selected_category] : 'All Categories' }}
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="categoryFilter">
                                <li><a class="dropdown-item {{ !$selected_category ? 'active' : '' }}" href="{{ route($route.'.index') }}">All Categories</a></li>
                                <li><hr class="dropdown-divider"></li>
                                @foreach($categories as $key => $label)
                                <li><a class="dropdown-item {{ $selected_category == $key ? 'active' : '' }}" href="{{ route($route.'.index', ['category' => $key]) }}">{{ $label }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="export-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>Category</th>
                                        <th>File</th>
                                        <th>Size</th>
                                        <th>Downloads</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <i class="{{ $row->icon ?? $row->file_icon }}"></i>
                                            {!! str_limit($row->title, 40, ' ...') !!}
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $row->category_label }}</span>
                                        </td>
                                        <td>
                                            <small class="text-muted" title="{{ $row->file_name }}">
                                                {{ str_limit($row->file_name, 25, '...') }}
                                            </small>
                                        </td>
                                        <td>{{ $row->formatted_file_size }}</td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-download"></i> {{ number_format($row->download_count) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if( $row->status == 1 )
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route($route.'.download', $row->id) }}" class="btn btn-icon btn-info btn-sm" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>

                                            <button type="button" class="btn btn-icon btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#showModal-{{ $row->id }}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <!-- Include Show modal -->
                                            @include($view.'.show')

                                            @can($access.'-edit')
                                            <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm" title="Edit">
                                                <i class="far fa-edit"></i>
                                            </a>
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <!-- Include Delete modal -->
                                            @include('admin.layouts.inc.delete')
                                            @endcan
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
