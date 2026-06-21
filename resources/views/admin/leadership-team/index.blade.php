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
                                <label for="name" class="form-label">{{ __('field_name') }} <span>*</span></label>
                                <input type="text" class="form-control" name="name" id="name" value="{{ old('name') }}" required>
                                <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_name') }}</div>
                            </div>

                            <div class="form-group">
                                <label for="designation" class="form-label">Designation <span>*</span></label>
                                <input type="text" class="form-control" name="designation" id="designation" value="{{ old('designation') }}" placeholder="e.g., Vice Chancellor" required>
                                <div class="invalid-feedback">{{ __('required_field') }} Designation</div>
                            </div>

                            <div class="form-group">
                                <label for="bio" class="form-label">Biography</label>
                                <textarea class="form-control" name="bio" id="bio" rows="4">{{ old('bio') }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label">{{ __('field_email') }}</label>
                                <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}">
                            </div>

                            <div class="form-group">
                                <label for="phone" class="form-label">{{ __('field_phone') }}</label>
                                <input type="text" class="form-control" name="phone" id="phone" value="{{ old('phone') }}">
                            </div>

                            <div class="form-group">
                                <label for="photo" class="form-label">Photo</label>
                                <input type="file" class="form-control" name="photo" id="photo">
                                <small class="form-text text-muted">Image (JPG, PNG) - Max 2MB</small>
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
                                        <th>Photo</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>Designation</th>
                                        <th>Contact</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $row->sort_order }}</td>
                                        <td>
                                            @if(is_file('uploads/'.$path.'/'.$row->photo))
                                            <img src="{{ asset('uploads/'.$path.'/'.$row->photo) }}" alt="{{ $row->name }}" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                                            @else
                                            <div style="width: 50px; height: 50px; background: #e0e0e0; border-radius: 4px;"></div>
                                            @endif
                                        </td>
                                        <td>{{ $row->name }}</td>
                                        <td>{{ $row->designation }}</td>
                                        <td>
                                            @if($row->email)
                                            <small><i class="fas fa-envelope"></i> {{ $row->email }}</small><br>
                                            @endif
                                            @if($row->phone)
                                            <small><i class="fas fa-phone"></i> {{ $row->phone }}</small>
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
