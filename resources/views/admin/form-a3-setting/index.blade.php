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
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route('admin.form-a3-setting.update') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="hnd_coordinator_name" class="form-label">HND Coordinator Name <span>*</span></label>
                                        <input type="text" class="form-control" name="hnd_coordinator_name" id="hnd_coordinator_name" value="{{ isset($row->hnd_coordinator_name) ? $row->hnd_coordinator_name : '' }}" required>
                                        <div class="invalid-feedback">
                                            {{ __('required_field') }} HND Coordinator Name
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="dir_acad_name" class="form-label">Director of Academics Name <span>*</span></label>
                                        <input type="text" class="form-control" name="dir_acad_name" id="dir_acad_name" value="{{ isset($row->dir_acad_name) ? $row->dir_acad_name : '' }}" required>
                                        <div class="invalid-feedback">
                                            {{ __('required_field') }} Director of Academics Name
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
