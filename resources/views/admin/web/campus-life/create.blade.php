@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('btn_create') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                      <div class="row">
                        <!-- Form Start -->
                        <div class="form-group col-md-6">
                            <label for="language_id">{{ __('field_language') }} <span>*</span></label>
                            <select class="form-control" name="language_id" id="language_id" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($languages as $language)
                                <option value="{{ $language->id }}" @if(old('language_id') == $language->id) selected @endif>{{ $language->name }}</option>
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
                                <option value="activities" @if(old('section_type') == 'activities') selected @endif>Activities</option>
                                <option value="clubs" @if(old('section_type') == 'clubs') selected @endif>Clubs & Organizations</option>
                                <option value="facilities" @if(old('section_type') == 'facilities') selected @endif>Facilities</option>
                                <option value="sports" @if(old('section_type') == 'sports') selected @endif>Sports & Recreation</option>
                                <option value="general" @if(old('section_type') == 'general') selected @endif>General</option>
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_type') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="description">{{ __('field_description') }}</label>
                            <textarea name="description" id="description" class="form-control texteditor">{{ old('description') }}</textarea>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="image">{{ __('field_photo') }}: <span>{{ __('image_size', ['height' => 600, 'width' => 800]) }}</span></label>
                            <input type="file" class="form-control" name="image" id="image">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_photo') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="icon">{{ __('field_icon') }} <span>(FontAwesome class, e.g., "fas fa-users")</span></label>
                            <input type="text" class="form-control" name="icon" id="icon" value="{{ old('icon') }}" placeholder="fas fa-users">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="sort_order">{{ __('field_sort_order') }} <span>*</span></label>
                            <input type="number" class="form-control" name="sort_order" id="sort_order" value="{{ old('sort_order') ?? 0 }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_sort_order') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="status">{{ __('field_status') }} <span>*</span></label>
                            <select class="form-control" name="status" id="status" required>
                                <option value="1" @if(old('status') == '1') selected @endif>{{ __('status_active') }}</option>
                                <option value="0" @if(old('status') == '0') selected @endif>{{ __('status_inactive') }}</option>
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_status') }}
                            </div>
                        </div>
                        <!-- Form End -->
                      </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
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
