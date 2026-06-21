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
                        <form class="needs-validation" novalidate action="{{ route('admin.form-a2-setting.update') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="finance_director_name" class="form-label">Director of Finance Name <span>*</span></label>
                                        <input type="text" class="form-control" name="finance_director_name" id="finance_director_name" value="{{ isset($row->finance_director_name) ? $row->finance_director_name : '' }}" required>
                                        <div class="invalid-feedback">
                                            {{ __('required_field') }} {{ __('Director of Finance Name') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="registrar_name" class="form-label">Registrar Name <span>*</span></label>
                                        <input type="text" class="form-control" name="registrar_name" id="registrar_name" value="{{ isset($row->registrar_name) ? $row->registrar_name : '' }}" required>
                                        <div class="invalid-feedback">
                                            {{ __('required_field') }} {{ __('Registrar Name') }}
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
