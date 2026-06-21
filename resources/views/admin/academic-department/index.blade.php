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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> {{ __('btn_add') }} {{ $title }}
                </button>
            </div>
            @endcan

            <div class="col-md-12">
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
                                        <th>Head of Department</th>
                                        <th>{{ __('field_programs') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->title }}</td>
                                        <td>
                                            @if($row->shortcode)
                                            <span class="badge badge-primary">{{ $row->shortcode }}</span>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $row->faculty->title ?? 'N/A' }}</span>
                                        </td>
                                        <td>
                                            @if($row->headOfDepartment)
                                            {{ $row->headOfDepartment->first_name }} {{ $row->headOfDepartment->last_name }}
                                            @else
                                            <span class="text-muted">Not assigned</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-dark">{{ $row->programs->count() }}</span>
                                        </td>
                                        <td>
                                            @if( $row->status == 1 )
                                            <span class="badge badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}" title="{{ __('btn_edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @include('admin.academic-department.edit')
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}" title="{{ __('btn_delete') }}">
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

@can($access.'-create')
<!-- Add modal content -->
<div id="addModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">{{ __('btn_add') }} {{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post">
            @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="faculty" class="form-label">{{ __('field_faculty') }} <span>*</span></label>
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
                                <input type="text" class="form-control" name="shortcode" id="shortcode" value="{{ old('shortcode') }}" placeholder="e.g., CS, ENG">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="head_of_department" class="form-label">Head of Department</label>
                                <select class="form-control select2" name="head_of_department" id="head_of_department">
                                    <option value="">{{ __('select') }}</option>
                                    @foreach( $staff as $person )
                                    <option value="{{ $person->id }}" @if(old('head_of_department') == $person->id) selected @endif>{{ $person->first_name }} {{ $person->last_name }} ({{ $person->staff_id }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" class="form-label">{{ __('field_email') }}</label>
                                <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}" placeholder="department@example.com">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" class="form-label">{{ __('field_phone') }}</label>
                                <input type="text" class="form-control" name="phone" id="phone" value="{{ old('phone') }}" placeholder="+237 XXX XXX XXX">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="description" class="form-label">{{ __('field_description') }}</label>
                                <textarea class="form-control" name="description" id="description" rows="3">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sort_order" class="form-label">{{ __('field_sort_order') }}</label>
                                <input type="number" class="form-control" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', 0) }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status" class="form-label">{{ __('select_status') }}</label>
                                <select class="form-control" name="status" id="status">
                                    <option value="1" selected>{{ __('status_active') }}</option>
                                    <option value="0">{{ __('status_inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('btn_save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection
