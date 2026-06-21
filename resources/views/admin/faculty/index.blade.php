@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            @can($access.'-create')
            <div class="col-md-12">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('btn_create') }} {{ $title }}</h5>
                        </div>
                        <div class="card-block">
                            <!-- Nav tabs -->
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#basic-info" role="tab">
                                        <i class="fas fa-info-circle"></i> Basic Information
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#content" role="tab">
                                        <i class="fas fa-file-alt"></i> Content
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#dean-info" role="tab">
                                        <i class="fas fa-user-tie"></i> Dean Information
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#images" role="tab">
                                        <i class="fas fa-images"></i> Images
                                    </a>
                                </li>
                            </ul>

                            <!-- Tab panes -->
                            <div class="tab-content mt-3">
                                <!-- Basic Info Tab -->
                                <div class="tab-pane active" id="basic-info" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="sector_id" class="form-label">{{ __('Sector') }}</label>
                                                <select class="form-control" name="sector_id" id="sector_id">
                                                    <option value="">{{ __('Select Sector') }}</option>
                                                    @foreach($sectors as $sector)
                                                    <option value="{{ $sector->id }}" @if(old('sector_id') == $sector->id) selected @endif>{{ $sector->title }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>

                                                <div class="invalid-feedback">
                                                  {{ __('required_field') }} {{ __('field_title') }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="shortcode" class="form-label">{{ __('field_shortcode') }}</label>
                                                <input type="text" class="form-control" name="shortcode" id="shortcode" value="{{ old('shortcode') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="matric_code" class="form-label">{{ __('Faculty Matricule Code') }}</label>
                                                <input type="text" class="form-control" name="matric_code" id="matric_code" value="{{ old('matric_code') }}" placeholder="e.g. 01">
                                                <small class="form-text text-muted">Used for generating student matricule numbers.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Content Tab -->
                                <div class="tab-pane" id="content" role="tabpanel">
                                    <div class="form-group">
                                        <label for="excerpt">Excerpt / Short Description</label>
                                        <textarea class="form-control" name="excerpt" id="excerpt" rows="3" placeholder="Brief overview of the faculty">{{ old('excerpt') }}</textarea>
                                        <small class="form-text text-muted">A short summary that appears in faculty cards and overview sections.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Full Description</label>
                                        <textarea class="form-control texteditor" name="description" id="description">{{ old('description') }}</textarea>
                                        <small class="form-text text-muted">Detailed information about the faculty.</small>
                                    </div>
                                </div>

                                <!-- Dean Info Tab -->
                                <div class="tab-pane" id="dean-info" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="dean_name">Dean Name</label>
                                                <input type="text" class="form-control" name="dean_name" id="dean_name" value="{{ old('dean_name') }}" placeholder="e.g., Prof. John Doe">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email</label>
                                                <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}" placeholder="dean@example.com">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">Phone</label>
                                                <input type="text" class="form-control" name="phone" id="phone" value="{{ old('phone') }}" placeholder="+237 XXX XXX XXX">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="website">Website URL</label>
                                                <input type="url" class="form-control" name="website" id="website" value="{{ old('website') }}" placeholder="https://example.com">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Images Tab -->
                                <div class="tab-pane" id="images" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="featured_image">Featured Image</label>
                                                <input type="file" class="form-control" name="featured_image" id="featured_image" accept="image/*">
                                                <small class="form-text text-muted">Recommended size: 800x600px. Appears on faculty cards.</small>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="banner_image">Banner Image</label>
                                                <input type="file" class="form-control" name="banner_image" id="banner_image" accept="image/*">
                                                <small class="form-text text-muted">Recommended size: 1920x500px. Header banner on detail page.</small>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="dean_photo">Dean Photo</label>
                                                <input type="file" class="form-control" name="dean_photo" id="dean_photo" accept="image/*">
                                                <small class="form-text text-muted">Recommended size: 400x400px. Dean's profile photo.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Form End -->
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary"><i class="fas fa-times"></i> {{ __('btn_cancel') }}</a>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            <div class="col-md-12 mt-4">
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
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_shortcode') }}</th>
                                        <th>{{ __('Matric Code') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->title }}</td>
                                        <td>{{ $row->shortcode }}</td>
                                        <td>{{ $row->matric_code }}</td>
                                        <td>
                                            @if( $row->status == 1 )
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}">
                                                <i class="far fa-edit"></i>
                                            </button>
                                            <!-- Include Edit modal -->
                                            @include($view.'.edit')
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}">
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