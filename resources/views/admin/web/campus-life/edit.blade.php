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
                            <label for="section_type">{{ __('field_type') }} <span>*</span></label>
                            <select class="form-control" name="section_type" id="section_type" required>
                                <option value="">{{ __('select') }}</option>
                                <option value="activities" @if($row->section_type == 'activities') selected @endif>Activities</option>
                                <option value="clubs" @if($row->section_type == 'clubs') selected @endif>Clubs & Organizations</option>
                                <option value="facilities" @if($row->section_type == 'facilities') selected @endif>Facilities</option>
                                <option value="sports" @if($row->section_type == 'sports') selected @endif>Sports & Recreation</option>
                                <option value="general" @if($row->section_type == 'general') selected @endif>General</option>
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_type') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ $row->title }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="description">{{ __('field_description') }}</label>
                            <textarea name="description" id="description" class="form-control texteditor">{{ $row->description }}</textarea>
                        </div>

                        <div class="form-group col-md-6">
                            @if(isset($row->image) && upload_exists($path.'/'.$row->image))
                            <img src="{{ upload_asset($path.'/'.$row->image) }}" class="img-thumbnail mb-2" style="max-height: 150px; max-width: 150px;" alt="{{ __('field_photo') }}">
                            <div class="clearfix"></div>
                            @endif

                            <label for="image">{{ __('field_photo') }}: <span>{{ __('image_size', ['height' => 600, 'width' => 800]) }}</span></label>
                            <input type="file" class="form-control" name="image" id="image">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_photo') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="icon">{{ __('field_icon') }} <span>(FontAwesome class, e.g., "fas fa-users")</span></label>
                            <input type="text" class="form-control" name="icon" id="icon" value="{{ $row->icon }}" placeholder="fas fa-users">
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
