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
                                <label for="faculty_id" class="form-label">{{ __('field_faculty') }}</label>
                                <select class="form-control" name="faculty_id" id="faculty_id">
                                    <option value="">{{ __('select') }}</option>
                                    @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="theme" class="form-label">{{ __('field_theme') }}</label>
                                <input type="text" class="form-control" name="theme" id="theme" value="{{ old('theme') }}" placeholder="e.g., Sustainability, Health">
                            </div>

                            <div class="form-group">
                                <label for="lead_researcher" class="form-label">Lead Researcher</label>
                                <input type="text" class="form-control" name="lead_researcher" id="lead_researcher" value="{{ old('lead_researcher') }}">
                            </div>

                            <div class="form-group">
                                <label for="description" class="form-label">{{ __('field_description') }} <span>*</span></label>
                                <textarea class="form-control" name="description" id="description" rows="4" required>{{ old('description') }}</textarea>
                                <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_description') }}</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ old('start_date') }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ old('end_date') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="status" class="form-label">{{ __('field_status') }} <span>*</span></label>
                                <select class="form-control" name="status" id="status" required>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="attach" class="form-label">Project Image</label>
                                <input type="file" class="form-control" name="attach" id="attach">
                                <small class="form-text text-muted">Image (JPG, PNG) - Max 2MB</small>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1">
                                    <label class="form-check-label" for="featured">Featured Project</label>
                                </div>
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
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_faculty') }}</th>
                                        <th>Theme</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>Featured</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->title }}</td>
                                        <td>{{ $row->faculty->title ?? '-' }}</td>
                                        <td>{{ $row->theme ?? '-' }}</td>
                                        <td>
                                            @if( $row->status == 'ongoing' )
                                            <span class="badge badge-pill badge-primary">Ongoing</span>
                                            @else
                                            <span class="badge badge-pill badge-success">Completed</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if( $row->featured )
                                            <span class="badge badge-pill badge-warning">Featured</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(is_file('uploads/'.$path.'/'.$row->attach))
                                            <button type="button" class="btn btn-icon btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#imageModal-{{ $row->id }}">
                                                <i class="fas fa-image"></i>
                                            </button>
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

                                    @if(is_file('uploads/'.$path.'/'.$row->attach))
                                    <!-- Image Modal -->
                                    <div class="modal fade" id="imageModal-{{ $row->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">{{ $row->title }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <img src="{{ asset('uploads/'.$path.'/'.$row->attach) }}" alt="{{ $row->title }}" class="img-fluid">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
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
