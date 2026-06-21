@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            @can($access.'-create')
            <div class="col-md-4">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('btn_create') }} {{ $title }}</h5>
                        </div>
                        <div class="card-block">
                            <!-- Form Start -->
                            <div class="form-group">
                                <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>
                                <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                            </div>

                            <div class="form-group">
                                <label for="description" class="form-label">{{ __('field_description') }}</label>
                                <textarea class="form-control" name="description" id="description" rows="3">{{ old('description') }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="logo" class="form-label">Logo</label>
                                <input type="file" class="form-control" name="logo" id="logo">
                                <small class="form-text text-muted">Image/SVG (JPG, PNG, SVG) - Max 2MB</small>
                            </div>

                            <div class="form-group">
                                <label for="url" class="form-label">Website URL</label>
                                <input type="url" class="form-control" name="url" id="url" value="{{ old('url') }}" placeholder="https://example.com">
                            </div>

                            <div class="form-group">
                                <label for="sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0">
                                <small class="form-text text-muted">Lower numbers appear first</small>
                            </div>
                            <!-- Form End -->
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Logo</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_description') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $row->sort_order }}</td>
                                        <td>
                                            @if(is_file('uploads/'.$path.'/'.$row->logo))
                                            <img src="{{ asset('uploads/'.$path.'/'.$row->logo) }}" alt="{{ $row->title }}" style="max-width: 80px; max-height: 60px; object-fit: contain;">
                                            @else
                                            <div style="width: 80px; height: 60px; background: #f5f5f5; border: 1px dashed #ccc; border-radius: 4px;"></div>
                                            @endif
                                        </td>
                                        <td>{{ $row->title }}</td>
                                        <td>{{ Str::limit($row->description, 80) }}</td>
                                        <td>
                                            @if( $row->status == 1 )
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->url)
                                            <a href="{{ $row->url }}" target="_blank" class="btn btn-icon btn-sm btn-info" title="Visit Website">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            @endif

                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}">
                                                <i class="far fa-edit"></i>
                                            </button>
                                            @include($view.'.edit')
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
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
