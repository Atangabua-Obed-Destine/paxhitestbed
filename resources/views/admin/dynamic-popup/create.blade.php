@extends('admin.layouts.master')

@section('title', $title)

@section('content')

<!-- Start Content -->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Form Column -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> {{ __('Add New Dynamic Popup') }}</h5>
                    </div>
                    <div class="card-block">
                        <form action="{{ route($route.'.store') }}" method="POST" enctype="multipart/form-data" id="popupForm">
                            @csrf

                            <h6 class="mb-3 text-primary"><i class="fas fa-info-circle"></i> {{ __('Basic Information') }}</h6>
                            
                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="title">{{ __('Title') }} <span class="text-danger">*</span></label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title') }}" 
                                           maxlength="100" required>
                                    <small class="form-text text-muted">{{ __('Best within 50 characters') }}</small>
                                    @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="summary">{{ __('Summary') }}</label>
                                <div class="col-md-9">
                                    <textarea class="form-control @error('summary') is-invalid @enderror" 
                                              id="summary" name="summary" rows="3" 
                                              maxlength="500">{{ old('summary') }}</textarea>
                                    <small class="form-text text-muted">{{ __('Best within 200 characters') }}</small>
                                    @error('summary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="image">{{ __('Image') }}</label>
                                <div class="col-md-9">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input @error('image') is-invalid @enderror" 
                                               id="image" name="image" accept="image/*">
                                        <label class="custom-file-label" for="image">{{ __('Choose file') }}</label>
                                    </div>
                                    <small class="form-text text-muted">{{ __('Recommended: 512px × 280px. Max 2MB.') }}</small>
                                    @error('image')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div id="imagePreview" class="mt-2" style="display: none;">
                                        <img src="" alt="Preview" class="img-thumbnail" style="max-width: 200px;">
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3 text-primary"><i class="fas fa-mouse-pointer"></i> {{ __('Button Configuration') }}</h6>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="button_text">{{ __('Button Text') }}</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control @error('button_text') is-invalid @enderror" 
                                           id="button_text" name="button_text" value="{{ old('button_text') }}" 
                                           maxlength="50" placeholder="{{ __('e.g., Learn More, Visit Now') }}">
                                    <small class="form-text text-muted">{{ __('Leave empty to hide button') }}</small>
                                    @error('button_text')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="link">{{ __('Button Link') }}</label>
                                <div class="col-md-9">
                                    <input type="url" class="form-control @error('link') is-invalid @enderror" 
                                           id="link" name="link" value="{{ old('link') }}" 
                                           placeholder="https://example.com">
                                    @error('link')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="button_color">{{ __('Button Color') }} <span class="text-danger">*</span></label>
                                <div class="col-md-9">
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('button_color') is-invalid @enderror" 
                                               id="button_color" name="button_color" 
                                               value="{{ old('button_color', '#007bff') }}" required>
                                        <div class="input-group-append">
                                            <input type="color" id="colorPicker" class="form-control" 
                                                   value="{{ old('button_color', '#007bff') }}" 
                                                   style="width: 50px; padding: 0; height: 38px;">
                                        </div>
                                    </div>
                                    @error('button_color')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">{{ __('Button Text Color') }} <span class="text-danger">*</span></label>
                                <div class="col-md-9">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="button_text_color" 
                                               id="textLight" value="light" {{ old('button_text_color', 'light') == 'light' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="textLight">
                                            <span class="badge badge-dark">{{ __('Light') }}</span>
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="button_text_color" 
                                               id="textDark" value="dark" {{ old('button_text_color') == 'dark' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="textDark">
                                            <span class="badge badge-light">{{ __('Dark') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3 text-primary"><i class="fas fa-bullseye"></i> {{ __('Targeting & Display') }}</h6>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">{{ __('Target Areas') }} <span class="text-danger">*</span></label>
                                <div class="col-md-9">
                                    @foreach($targetAreas as $key => $label)
                                    <div class="form-check">
                                        <input class="form-check-input target-area-checkbox" type="checkbox" 
                                               name="target_areas[]" value="{{ $key }}" 
                                               id="area_{{ $key }}"
                                               {{ in_array($key, old('target_areas', [])) ? 'checked' : '' }}
                                               {{ $key == 'all' ? 'data-select-all="true"' : '' }}>
                                        <label class="form-check-label" for="area_{{ $key }}">
                                            {{ $label }}
                                        </label>
                                    </div>
                                    @endforeach
                                    @error('target_areas')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="display_frequency">{{ __('Display Frequency') }} <span class="text-danger">*</span></label>
                                <div class="col-md-9">
                                    <select class="form-control @error('display_frequency') is-invalid @enderror" 
                                            id="display_frequency" name="display_frequency" required>
                                        @foreach($displayFrequencies as $key => $label)
                                        <option value="{{ $key }}" {{ old('display_frequency', 'once_session') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('display_frequency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="popup_position">{{ __('Popup Position') }} <span class="text-danger">*</span></label>
                                <div class="col-md-9">
                                    <select class="form-control @error('popup_position') is-invalid @enderror" 
                                            id="popup_position" name="popup_position" required>
                                        @foreach($popupPositions as $key => $label)
                                        <option value="{{ $key }}" {{ old('popup_position', 'center') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('popup_position')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3 text-primary"><i class="fas fa-calendar"></i> {{ __('Scheduling') }}</h6>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="start_date">{{ __('Start Date') }}</label>
                                <div class="col-md-9">
                                    <input type="datetime-local" class="form-control @error('start_date') is-invalid @enderror" 
                                           id="start_date" name="start_date" value="{{ old('start_date') }}">
                                    <small class="form-text text-muted">{{ __('Leave empty to start immediately') }}</small>
                                    @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="end_date">{{ __('End Date') }}</label>
                                <div class="col-md-9">
                                    <input type="datetime-local" class="form-control @error('end_date') is-invalid @enderror" 
                                           id="end_date" name="end_date" value="{{ old('end_date') }}">
                                    <small class="form-text text-muted">{{ __('Leave empty to run indefinitely') }}</small>
                                    @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3 text-primary"><i class="fas fa-cogs"></i> {{ __('Options') }}</h6>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label" for="priority">{{ __('Priority') }} <span class="text-danger">*</span></label>
                                <div class="col-md-9">
                                    <input type="number" class="form-control @error('priority') is-invalid @enderror" 
                                           id="priority" name="priority" value="{{ old('priority', 0) }}" 
                                           min="0" max="100" required>
                                    <small class="form-text text-muted">{{ __('Higher priority popups show first (0-100)') }}</small>
                                    @error('priority')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">{{ __('Options') }}</label>
                                <div class="col-md-9">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_dismissible" 
                                               id="is_dismissible" value="1" {{ old('is_dismissible', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_dismissible">
                                            {{ __('Allow users to dismiss/close the popup') }}
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="status" 
                                               id="status" value="1" {{ old('status', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="status">
                                            {{ __('Active (enable this popup)') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group row mt-4">
                                <div class="col-md-9 offset-md-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> {{ __('Save Popup') }}
                                    </button>
                                    <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preview Column -->
            <div class="col-md-4">
                <div class="card position-sticky" style="top: 80px;">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-eye"></i> {{ __('Live Preview') }}</h5>
                    </div>
                    <div class="card-block p-0">
                        <div id="livePreview" class="bg-light" style="min-height: 300px;">
                            <!-- Preview will be rendered here -->
                            <div class="dynamic-popup-preview">
                                <div class="popup-image-preview" style="height: 140px; background: #ddd; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                                <div class="p-3">
                                    <h5 class="popup-title-preview">{{ __('Your Title Here') }}</h5>
                                    <p class="popup-summary-preview text-muted small mb-3">{{ __('Your summary text will appear here...') }}</p>
                                    <button class="btn btn-sm popup-button-preview" style="background-color: #007bff; color: #fff;">
                                        {{ __('Button Text') }} <i class="fas fa-arrow-right ml-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> {{ __('This is how your popup will appear to users') }}
                        </small>
                    </div>
                </div>

                <!-- Target Areas Guide -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-question-circle"></i> {{ __('Target Areas Guide') }}</h6>
                    </div>
                    <div class="card-block">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <strong>{{ __('All Areas') }}:</strong>
                                <small class="text-muted d-block">{{ __('Shows everywhere in the system') }}</small>
                            </li>
                            <li class="mb-2">
                                <strong>{{ __('Front Website') }}:</strong>
                                <small class="text-muted d-block">{{ __('Public pages (home, about, programs, etc.)') }}</small>
                            </li>
                            <li class="mb-2">
                                <strong>{{ __('Student Portal') }}:</strong>
                                <small class="text-muted d-block">{{ __('Student dashboard and related pages') }}</small>
                            </li>
                            <li class="mb-2">
                                <strong>{{ __('Applicant Portal') }}:</strong>
                                <small class="text-muted d-block">{{ __('Application form and applicant dashboard') }}</small>
                            </li>
                            <li class="mb-2">
                                <strong>{{ __('Admin Portal') }}:</strong>
                                <small class="text-muted d-block">{{ __('Admin dashboard (staff only)') }}</small>
                            </li>
                            <li>
                                <strong>{{ __('Login Pages') }}:</strong>
                                <small class="text-muted d-block">{{ __('All login screens') }}</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content -->

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Custom file input label
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || '{{ __("Choose file") }}');
        
        // Image preview
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').show().find('img').attr('src', e.target.result);
                $('.popup-image-preview').html('<img src="' + e.target.result + '" class="w-100" style="height: 140px; object-fit: cover;">');
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Color picker sync
    $('#colorPicker').on('input', function() {
        $('#button_color').val($(this).val());
        updateButtonPreview();
    });
    
    $('#button_color').on('input', function() {
        $('#colorPicker').val($(this).val());
        updateButtonPreview();
    });

    // Live preview updates
    $('#title').on('input', function() {
        $('.popup-title-preview').text($(this).val() || '{{ __("Your Title Here") }}');
    });

    $('#summary').on('input', function() {
        $('.popup-summary-preview').text($(this).val() || '{{ __("Your summary text will appear here...") }}');
    });

    $('#button_text').on('input', function() {
        var text = $(this).val();
        if (text) {
            $('.popup-button-preview').show().html(text + ' <i class="fas fa-arrow-right ml-1"></i>');
        } else {
            $('.popup-button-preview').hide();
        }
    });

    $('input[name="button_text_color"]').change(function() {
        updateButtonPreview();
    });

    function updateButtonPreview() {
        var bgColor = $('#button_color').val();
        var textColor = $('input[name="button_text_color"]:checked').val() === 'light' ? '#fff' : '#000';
        $('.popup-button-preview').css({
            'background-color': bgColor,
            'color': textColor,
            'border-color': bgColor
        });
    }

    // Target area "All" checkbox logic
    $('[data-select-all="true"]').change(function() {
        if ($(this).is(':checked')) {
            $('.target-area-checkbox').not(this).prop('checked', false).prop('disabled', true);
        } else {
            $('.target-area-checkbox').prop('disabled', false);
        }
    });

    $('.target-area-checkbox').not('[data-select-all="true"]').change(function() {
        if ($('.target-area-checkbox:checked').not('[data-select-all="true"]').length > 0) {
            $('[data-select-all="true"]').prop('checked', false);
        }
    });

    // Initialize on page load
    if ($('[data-select-all="true"]').is(':checked')) {
        $('.target-area-checkbox').not('[data-select-all="true"]').prop('disabled', true);
    }
});
</script>
@endpush
