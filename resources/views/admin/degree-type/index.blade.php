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
                                        <th>{{ __('field_matricule_code') }}</th>
                                        <th>{{ __('field_level') }}</th>
                                        <th>{{ __('field_classification') }}</th>
                                        <th>{{ __('field_duration') }}</th>
                                        <th>{{ __('field_credits') }}</th>
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
                                        <td><span class="badge badge-primary">{{ $row->shortcode }}</span></td>
                                        <td>
                                            @if($row->code_append_to_student_matricule)
                                                <span class="badge badge-secondary">{{ $row->code_append_to_student_matricule }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->level == 'Undergraduate')
                                                <span class="badge badge-info">{{ $row->level }}</span>
                                            @elseif($row->level == 'Postgraduate')
                                                <span class="badge badge-success">{{ $row->level }}</span>
                                            @elseif($row->level == 'Certificate')
                                                <span class="badge badge-warning">{{ $row->level }}</span>
                                            @else
                                                <span class="badge badge-secondary">{{ $row->level }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->is_hnd)
                                                <span class="badge badge-info">HND</span>
                                            @endif
                                            @if($row->is_postgraduate)
                                                <span class="badge badge-success">Postgrad</span>
                                            @endif
                                            @if(!$row->is_hnd && !$row->is_postgraduate)
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->duration_years ? $row->duration_years . ' ' . trans_choice('field_year', $row->duration_years) : '-' }}</td>
                                        <td>{{ $row->min_credits ? $row->min_credits . ' credits' : '-' }}</td>
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
                                            <a href="{{ route('admin.degree-type.form-config', $row) }}" class="btn btn-icon btn-sm btn-info" title="{{ __('Application Form Configuration') }}">
                                                <i class="fas fa-sliders-h"></i>
                                            </a>
                                            <a href="{{ route('admin.degree-type.blank-form.download', $row) }}" class="btn btn-icon btn-sm btn-secondary" title="{{ __('Download Blank Application Form') }}" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <button type="button" class="btn btn-icon btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}" title="{{ __('btn_edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @include('admin.degree-type.edit')
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}" title="{{ __('btn_delete') }}">
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

@can($access.'-create')
<!-- Add modal content -->
<div id="addModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post">
            @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">{{ __('btn_add') }} {{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>
                                <small class="form-text text-muted">e.g., Bachelor of Science, Master of Arts</small>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_title') }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="shortcode" class="form-label">{{ __('field_shortcode') }} <span>*</span></label>
                                <input type="text" class="form-control text-uppercase" name="shortcode" id="shortcode" value="{{ old('shortcode') }}" required>
                                <small class="form-text text-muted">e.g., BSc, MA, PhD</small>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_shortcode') }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="code_append_to_student_matricule" class="form-label">{{ __('field_matricule_code') }}</label>
                                <input type="text" class="form-control text-uppercase" name="code_append_to_student_matricule" id="code_append_to_student_matricule" value="{{ old('code_append_to_student_matricule') }}" maxlength="10">
                                <small class="form-text text-muted">Code to append to student matricule (e.g., HND, PG)</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="level" class="form-label">{{ __('field_level') }} <span>*</span></label>
                                <select class="form-control" name="level" id="level" required>
                                    <option value="">{{ __('select') }}</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Undergraduate">Undergraduate</option>
                                    <option value="Postgraduate">Postgraduate</option>
                                </select>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_level') }}
                                </div>
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

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">{{ __('field_classification') }}</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_hnd" id="is_hnd" value="1" {{ old('is_hnd') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_hnd">
                                        Is HND (Higher National Diploma)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_postgraduate" id="is_postgraduate" value="1" {{ old('is_postgraduate') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_postgraduate">
                                        Is Postgraduate
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="duration_years" class="form-label">{{ __('field_duration') }} (Years)</label>
                                <input type="number" class="form-control" name="duration_years" id="duration_years" min="1" max="10" value="{{ old('duration_years') }}">
                                <small class="form-text text-muted">Typical program duration</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="min_credits" class="form-label">{{ __('field_minimum_credits') }}</label>
                                <input type="number" class="form-control" name="min_credits" id="min_credits" min="0" max="500" value="{{ old('min_credits') }}">
                                <small class="form-text text-muted">Minimum credits to complete</small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="description" class="form-label">{{ __('field_description') }}</label>
                                <textarea class="form-control" name="description" id="description" rows="3">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="requirements" class="form-label">{{ __('field_requirements') }}</label>
                                <textarea class="form-control" name="requirements" id="requirements" rows="3">{{ old('requirements') }}</textarea>
                                <small class="form-text text-muted">Entry requirements for this degree type</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sort_order" class="form-label">{{ __('field_sort_order') }}</label>
                                <input type="number" class="form-control" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', 0) }}">
                                <small class="form-text text-muted">Display order in lists</small>
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
