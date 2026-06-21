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

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_title') }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">{{ __('Fee Type') }} <span>*</span></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="fee_type" id="fee_type_regular" value="regular" {{ old('fee_type', 'regular') == 'regular' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="fee_type_regular">
                                        {{ __('Regular Fee') }}
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="fee_type" id="fee_type_resit" value="resit" {{ old('fee_type') == 'resit' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="fee_type_resit">
                                        {{ __('Resit Fee') }}
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="fee_type" id="fee_type_admission" value="admission" {{ old('fee_type') == 'admission' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="fee_type_admission">
                                        {{ __('Admission Fee') }}
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="fee_type" id="fee_type_first" value="first_installment" {{ old('fee_type') == 'first_installment' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="fee_type_first">
                                        {{ __('First Installment') }}
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="fee_type" id="fee_type_second" value="second_installment" {{ old('fee_type') == 'second_installment' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="fee_type_second">
                                        {{ __('Second Installment') }}
                                    </label>
                                </div>
                                <small class="form-text text-muted">{{ __('Select the type of fee category') }}</small>
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
                                        <th>{{ __('Type') }}</th>
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
                                            @if( $row->is_admission == 1 )
                                            <span class="badge badge-pill badge-primary">{{ __('Admission Fee') }}</span>
                                            @elseif( $row->is_resit == 1 )
                                            <span class="badge badge-pill badge-warning">{{ __('Resit Fee') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-info">{{ __('Regular Fee') }}</span>
                                            @endif
                                        </td>
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