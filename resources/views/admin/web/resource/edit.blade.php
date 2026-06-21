@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Card ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-edit text-primary"></i> {{ __('modal_edit') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>

                        <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                        
                        <a href="{{ route($route.'.download', $row->id) }}" class="btn btn-success"><i class="fas fa-download"></i> Download File</a>
                    </div>

                    <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-block">
                      <div class="row">
                        <!-- Form Start -->
                        <div class="form-group col-md-8">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ $row->title }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="category">Category <span>*</span></label>
                            <select class="form-control" name="category" id="category" required>
                                @foreach($categories as $key => $label)
                                <option value="{{ $key }}" {{ $row->category == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} Category
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="description">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="3">{{ $row->description }}</textarea>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} Description
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="file">File <small class="text-muted">(Leave empty to keep current file)</small></label>
                            <input type="file" class="form-control" name="file" id="file">
                            
                            <div class="mt-2 p-2 bg-light rounded">
                                <i class="{{ $row->icon ?? $row->file_icon }} fa-lg"></i>
                                <strong>Current File:</strong> {{ $row->file_name }}<br>
                                <small class="text-muted">Size: {{ $row->formatted_file_size }} | Type: {{ $row->file_type }}</small>
                            </div>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="icon">Custom Icon Class</label>
                            <input type="text" class="form-control" name="icon" id="icon" value="{{ $row->icon }}" placeholder="e.g., fas fa-book">
                            <small class="text-muted">Leave empty to auto-detect</small>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="sort_order">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="sort_order" value="{{ $row->sort_order }}" min="0">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="status" class="form-label">{{ __('select_status') }}</label>
                            <select class="form-control" name="status" id="status">
                                <option value="1" @if( $row->status == 1 ) selected @endif>{{ __('status_active') }}</option>
                                <option value="0" @if( $row->status == 0 ) selected @endif>{{ __('status_inactive') }}</option>
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label>Statistics</label>
                            <div class="p-3 bg-light rounded">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-primary mb-0">{{ number_format($row->download_count) }}</h4>
                                        <small class="text-muted">Downloads</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-info mb-0">{{ $row->created_at->format('M d, Y') }}</h4>
                                        <small class="text-muted">Created</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Form End -->
                      </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                    </div>
                    </form>
                </div>
            </div>
            <!-- [ Card ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
