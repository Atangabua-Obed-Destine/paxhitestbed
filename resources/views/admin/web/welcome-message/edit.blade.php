@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('btn_edit') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                      <div class="row">
                        <!-- Form Start -->
                        <div class="form-group col-md-6">
                            <label for="language_id">{{ __('field_language') }} <span>*</span></label>
                            <select class="form-control" name="language_id" id="language_id" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($languages as $language)
                                <option value="{{ $language->id }}" @if($row->language_id == $language->id) selected @endif>{{ $language->name }}</option>
                                @endforeach
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_language') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ $row->title }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="designation">{{ __('field_designation') }}</label>
                            <input type="text" class="form-control" name="designation" id="designation" value="{{ $row->designation }}">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_designation') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            @if(isset($row->image))
                            @if(upload_exists($path.'/'.$row->image))
                            <img src="{{ upload_asset($path.'/'.$row->image) }}" class="img-thumbnail mb-2" style="max-height: 150px; max-width: 150px;" alt="{{ __('field_photo') }}">
                            <div class="clearfix"></div>
                            @endif
                            @endif

                            <label for="image">{{ __('field_photo') }}: <span>{{ __('image_size', ['height' => 400, 'width' => 400]) }}</span></label>
                            <input type="file" class="form-control" name="image" id="image">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_photo') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="message">{{ __('field_message') }} <span>*</span></label>
                            <textarea name="message" id="message" class="form-control" required>{{ $row->message }}</textarea>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_message') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="sort_order">{{ __('field_sort_order') }} <span>*</span></label>
                            <input type="number" class="form-control" name="sort_order" id="sort_order" value="{{ $row->sort_order }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_sort_order') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="status">{{ __('field_status') }} <span>*</span></label>
                            <select class="form-control" name="status" id="status" required>
                                <option value="1" @if($row->status == '1') selected @endif>{{ __('status_active') }}</option>
                                <option value="0" @if($row->status == '0') selected @endif>{{ __('status_inactive') }}</option>
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_status') }}
                            </div>
                        </div>
                        <!-- Form End -->
                      </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary"><i class="fas fa-times"></i> {{ __('btn_cancel') }}</a>
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

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    "use strict";
    
    $('#message').summernote({
        height: 400,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['height', ['height']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
        fontSizes: ['8', '10', '12', '14', '16', '18', '20', '24', '36', '48'],
        placeholder: 'Enter your welcome message here...',
        tabsize: 2,
        callbacks: {
            onChange: function(contents, $editable) {
                $('#message').val(contents);
            }
        }
    });
});
</script>
@endsection
