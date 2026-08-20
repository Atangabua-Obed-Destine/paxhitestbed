@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12 col-lg-8">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block row">
                        
                        <!-- Form Start -->
                        <input name="id" type="hidden" value="{{ (isset($row)) ? $row->id : '' }}">
                        <input name="slug" type="hidden" value="student-card">

                        <div class="form-group col-md-6">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ isset($row->title)?$row->title:'' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="subtitle">{{ __('field_subtitle') }} <span>*</span></label>
                            <input type="text" class="form-control" name="subtitle" id="subtitle" value="{{ isset($row->subtitle)?$row->subtitle:'' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_subtitle') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="validity">{{ __('field_validity') }} <span>*</span></label>
                            <input type="text" class="form-control autonumber" name="validity" id="validity" value="{{ isset($row->validity)?$row->validity:'' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_validity') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="address">{{ __('field_address') }} <span>*</span></label>
                            <input type="text" class="form-control" name="address" id="address" value="{{ isset($row->address)?$row->address:'' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_address') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="prefix" class="form-label">{{ __('field_prefix') }}</label>
                            <input type="text" class="form-control" name="prefix" id="prefix" value="{{ isset($row->prefix)?$row->prefix:'' }}">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_prefix') }}
                            </div>
                        </div>

                        {{-- The card's wording and crest are part of this artwork, not the
                             markup, so replacing it restyles the whole card. Everything the
                             system prints on top — photo, matricule, name, validity — is
                             positioned absolutely and does not move. --}}
                        <div class="form-group col-md-12">
                            <label for="background">{{ __('field_background') }}: <span>{{ __('image_size', ['height' => 639, 'width' => 1011]) }}</span></label>
                            <input type="file" class="form-control" name="background" id="background" accept="image/*">
                            <small class="text-muted d-block mt-1">
                                {{ __('Landscape artwork for the whole card. Leave empty to keep the current one.') }}
                            </small>

                            <div class="mt-2">
                                @if(!empty($row->background) && file_exists(public_path('uploads/card-setting/'.$row->background)))
                                    <img src="{{ asset('uploads/card-setting/'.$row->background) }}" alt="{{ __('field_background') }}"
                                         style="max-width:320px;width:100%;height:auto;border:1px solid #e2e5e8;border-radius:4px;">
                                @else
                                    <img src="{{ asset('uploads/templates/paxid.jpg') }}" alt="{{ __('field_background') }}"
                                         style="max-width:320px;width:100%;height:auto;border:1px solid #e2e5e8;border-radius:4px;">
                                    <small class="text-muted d-block mt-1">{{ __('Built-in default — no background uploaded yet.') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">@isset($row) <i class="fas fa-check"></i> {{ __('btn_update') }} @else <i class="fas fa-check"></i> {{ __('btn_save') }} @endif</button>
                    </div>

                </div>
                </form>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection