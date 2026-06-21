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
                        <!-- Basic Information -->
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
                            <label for="status">{{ __('field_status') }} <span>*</span></label>
                            <select class="form-control" name="status" id="status" required>
                                <option value="1" @if($row->status == '1') selected @endif>{{ __('status_active') }}</option>
                                <option value="0" @if($row->status == '0') selected @endif>{{ __('status_inactive') }}</option>
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_status') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            @if(isset($row->banner_image) && upload_exists($path.'/'.$row->banner_image))
                            <img src="{{ upload_asset($path.'/'.$row->banner_image) }}" class="img-thumbnail mb-2" style="max-height: 120px; max-width: 300px;" alt="{{ __('field_banner') }}">
                            <div class="clearfix"></div>
                            @endif

                            <label for="banner_image">{{ __('field_banner') }}: <span>{{ __('image_size', ['height' => 400, 'width' => 1200]) }}</span></label>
                            <input type="file" class="form-control" name="banner_image" id="banner_image">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ $row->title }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="subtitle">{{ __('field_sub_title') }}</label>
                            <input type="text" class="form-control" name="subtitle" id="subtitle" value="{{ $row->subtitle }}">
                        </div>

                        <div class="form-group col-md-12">
                            <label for="description">{{ __('field_description') }}</label>
                            <textarea name="description" id="description" class="form-control texteditor">{{ $row->description }}</textarea>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="requirements">{{ __('field_requirements') }}</label>
                            <textarea name="requirements" id="requirements" class="form-control texteditor">{{ $row->requirements }}</textarea>
                        </div>

                        <!-- Process Steps -->
                        <div class="form-group col-md-12">
                            <label>Application Process Steps</label>
                            <div id="process-steps-container">
                                @if($row->process_steps && is_array($row->process_steps))
                                    @foreach($row->process_steps as $index => $step)
                                    <div class="process-step-item border p-3 mb-2">
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label>Step Title</label>
                                                <input type="text" class="form-control" name="step_titles[]" value="{{ $step['title'] ?? '' }}">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Step Description</label>
                                                <textarea class="form-control" name="step_descriptions[]" rows="2">{{ $step['description'] ?? '' }}</textarea>
                                            </div>
                                            <div class="col-md-12">
                                                <button type="button" class="btn btn-danger btn-sm remove-step">Remove Step</button>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                @endif
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="add-step">Add Process Step</button>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="contact_info">{{ __('field_contact_info') }}</label>
                            <textarea name="contact_info" id="contact_info" class="form-control texteditor">{{ $row->contact_info }}</textarea>
                        </div>

                        <!-- SEO Fields -->
                        <div class="form-group col-md-12">
                            <h5 class="mt-3">SEO Information</h5>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="meta_title">Meta Title</label>
                            <input type="text" class="form-control" name="meta_title" id="meta_title" value="{{ $row->meta_title }}">
                        </div>

                        <div class="form-group col-md-12">
                            <label for="meta_description">Meta Description</label>
                            <textarea name="meta_description" id="meta_description" class="form-control" rows="3">{{ $row->meta_description }}</textarea>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="meta_keywords">Meta Keywords</label>
                            <input type="text" class="form-control" name="meta_keywords" id="meta_keywords" value="{{ $row->meta_keywords }}">
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

@section('page_js')
<script>
    // Add new process step
    document.getElementById('add-step').addEventListener('click', function() {
        const container = document.getElementById('process-steps-container');
        const stepHtml = `
            <div class="process-step-item border p-3 mb-2">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Step Title</label>
                        <input type="text" class="form-control" name="step_titles[]">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Step Description</label>
                        <textarea class="form-control" name="step_descriptions[]" rows="2"></textarea>
                    </div>
                    <div class="col-md-12">
                        <button type="button" class="btn btn-danger btn-sm remove-step">Remove Step</button>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', stepHtml);
    });

    // Remove process step (using event delegation)
    document.getElementById('process-steps-container').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-step')) {
            e.target.closest('.process-step-item').remove();
        }
    });
</script>
@endsection
