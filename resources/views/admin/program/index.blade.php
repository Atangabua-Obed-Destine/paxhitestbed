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
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="faculty">{{ __('field_faculty') }} <span>*</span></label>
                                                <select class="form-control" name="faculty" id="faculty" required>
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach( $faculties as $faculty )
                                                    <option value="{{ $faculty->id }}" @if(old('faculty') == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                                    @endforeach
                                                </select>

                                                <div class="invalid-feedback">
                                                  {{ __('required_field') }} {{ __('field_faculty') }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="academic_department">Academic Department</label>
                                                <select class="form-control" name="academic_department" id="academic_department">
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach( $academicDepartments as $dept )
                                                    <option value="{{ $dept->id }}" data-faculty-id="{{ $dept->faculty_id }}" @if(old('academic_department') == $dept->id) selected @endif>{{ $dept->title }} ({{ $dept->faculty->title }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="degree_type">Degree Type</label>
                                                <select class="form-control" name="degree_type" id="degree_type">
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach( $degreeTypes as $degreeType )
                                                    <option value="{{ $degreeType->id }}" @if(old('degree_type') == $degreeType->id) selected @endif>{{ $degreeType->title }} ({{ $degreeType->shortcode }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>

                                                <div class="invalid-feedback">
                                                  {{ __('required_field') }} {{ __('field_title') }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="shortcode" class="form-label">{{ __('field_shortcode') }} <span>*</span></label>
                                                <input type="text" class="form-control" name="shortcode" id="shortcode" value="{{ old('shortcode') }}" required>

                                                <div class="invalid-feedback">
                                                  {{ __('required_field') }} {{ __('field_shortcode') }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="duration">Duration</label>
                                                <input type="text" class="form-control" name="duration" id="duration" value="{{ old('duration') }}" placeholder="e.g., 3 Years, 4 Semesters">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="credit">Credits</label>
                                                <input type="text" class="form-control" name="credit" id="credit" value="{{ old('credit') }}" placeholder="e.g., 120 Credits">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="validity_years">ID Card Validity (Years)</label>
                                                <input type="number" class="form-control" name="validity_years" id="validity_years" value="{{ old('validity_years', 4) }}" min="1" max="10" placeholder="e.g., 4">
                                                <small class="form-text text-muted">Number of years the ID card is valid from date of issue</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Content Tab -->
                                <div class="tab-pane" id="content" role="tabpanel">
                                    <div class="form-group">
                                        <label for="excerpt">Excerpt / Short Description</label>
                                        <textarea class="form-control" name="excerpt" id="excerpt" rows="3" placeholder="Brief overview of the program">{{ old('excerpt') }}</textarea>
                                        <small class="form-text text-muted">A short summary that appears in program cards and overview sections.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Full Description</label>
                                        <textarea class="form-control texteditor" name="description" id="description">{{ old('description') }}</textarea>
                                        <small class="form-text text-muted">Detailed information about the program.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="requirements">Entry Requirements</label>
                                        <textarea class="form-control texteditor" name="requirements" id="requirements">{{ old('requirements') }}</textarea>
                                        <small class="form-text text-muted">List the admission requirements for this program.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="career_prospects">Career Prospects</label>
                                        <textarea class="form-control texteditor" name="career_prospects" id="career_prospects">{{ old('career_prospects') }}</textarea>
                                        <small class="form-text text-muted">Career opportunities available to graduates.</small>
                                    </div>
                                </div>

                                <!-- Images Tab -->
                                <div class="tab-pane" id="images" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="featured_image">Featured Image</label>
                                                <input type="file" class="form-control" name="featured_image" id="featured_image" accept="image/*">
                                                <small class="form-text text-muted">Recommended size: 800x600px. This appears on program cards and detail page.</small>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="banner_image">Banner Image</label>
                                                <input type="file" class="form-control" name="banner_image" id="banner_image" accept="image/*">
                                                <small class="form-text text-muted">Recommended size: 1920x500px. This appears as the header banner on the detail page.</small>
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
                                        <th>{{ __('field_faculty') }}</th>
                                        <th>Department</th>
                                        <th>Degree Type</th>
                                        {{-- <th>{{ __('field_registration') }}</th> --}}
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
                                        <td>{{ $row->faculty->title ?? '' }}</td>
                                        <td>
                                            @if($row->academicDepartment)
                                            <span class="badge badge-secondary">{{ $row->academicDepartment->shortcode ?? $row->academicDepartment->title }}</span>
                                            @else
                                            <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->degreeType)
                                            <span class="badge badge-info">{{ $row->degreeType->shortcode }}</span>
                                            @else
                                            <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        {{-- <td>
                                            @if( $row->registration == 1 )
                                            <span class="badge badge-primary">{{ __('status_open') }}</span>
                                            @else
                                            <span class="badge badge-warning">{{ __('status_close') }}</span>
                                            @endif
                                        </td> --}}
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