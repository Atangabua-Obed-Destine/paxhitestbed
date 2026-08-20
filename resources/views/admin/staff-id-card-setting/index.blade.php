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

                        {{-- The card's wording and crest are part of this artwork, not the
                             markup, so replacing it restyles the whole card. Everything the
                             system prints on top — photo, name, staff id — is positioned
                             absolutely and does not move. --}}
                        <div class="form-group col-md-12">
                            <label for="background">{{ __('field_background') }}: <span>{{ __('image_size', ['height' => 1011, 'width' => 639]) }}</span></label>
                            <input type="file" class="form-control" name="background" id="background" accept="image/*">
                            <small class="text-muted d-block mt-1">
                                {{ __('Portrait artwork for the whole card. Leave empty to keep the current one.') }}
                            </small>

                            <div class="mt-2">
                                @if(!empty($row->background) && file_exists(public_path('uploads/card-setting/'.$row->background)))
                                    <img src="{{ asset('uploads/card-setting/'.$row->background) }}" alt="{{ __('field_background') }}"
                                         style="max-width:220px;width:100%;height:auto;border:1px solid #e2e5e8;border-radius:4px;">
                                @else
                                    <img src="{{ asset('uploads/templates/staffid.jpg') }}" alt="{{ __('field_background') }}"
                                         style="max-width:220px;width:100%;height:auto;border:1px solid #e2e5e8;border-radius:4px;">
                                    <small class="text-muted d-block mt-1">{{ __('Built-in default — no background uploaded yet.') }}</small>
                                @endif
                            </div>
                        </div>

                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
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
