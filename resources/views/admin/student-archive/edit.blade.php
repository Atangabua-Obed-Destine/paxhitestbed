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
                        <h5><i class="far fa-edit"></i> {{ __('edit') }} {{ __('student') }}</h5>
                        <div class="float-end">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <!-- Photo Section -->
                                <div class="col-md-3">
                                    <div class="form-group text-center">
                                        <label for="photo">{{ __('field_photo') }}</label>
                                        <div class="mb-3">
                                            @if(is_file('uploads/'.$path.'/'.$row->photo))
                                            <img src="{{ asset('uploads/'.$path.'/'.$row->photo) }}" id="photo-preview" class="img-fluid rounded" alt="{{ $row->first_name }}">
                                            @else
                                            <img src="{{ asset('uploads/default.png') }}" id="photo-preview" class="img-fluid rounded" alt="{{ $row->first_name }}">
                                            @endif
                                        </div>
                                        <input type="file" class="form-control @error('photo') is-invalid @enderror" name="photo" id="photo" accept="image/*">
                                        <small class="form-text text-muted">{{ __('max_file_size') }}: 2MB</small>
                                        @error('photo')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-9">
                                    <div class="row">
                                        <!-- Student ID (Read-only) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="student_id">{{ __('field_student_id') }}</label>
                                                <input type="text" class="form-control bg-light" value="{{ $row->student_id }}" readonly>
                                            </div>
                                        </div>

                                        <!-- First Name -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="first_name">{{ __('field_first_name') }} <span>*</span></label>
                                                <input type="text" class="form-control @error('first_name') is-invalid @enderror" name="first_name" id="first_name" value="{{ old('first_name', $row->first_name) }}" required>
                                                @error('first_name')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Last Name -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="last_name">{{ __('field_last_name') }} <span>*</span></label>
                                                <input type="text" class="form-control @error('last_name') is-invalid @enderror" name="last_name" id="last_name" value="{{ old('last_name', $row->last_name) }}" required>
                                                @error('last_name')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Email -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">{{ __('field_email') }} <span>*</span></label>
                                                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="email" value="{{ old('email', $row->email) }}" required>
                                                @error('email')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Phone -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">{{ __('field_phone') }}</label>
                                                <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" id="phone" value="{{ old('phone', $row->phone) }}">
                                                @error('phone')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Gender -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="gender">{{ __('field_gender') }} <span>*</span></label>
                                                <select class="form-control @error('gender') is-invalid @enderror" name="gender" id="gender" required>
                                                    <option value="">{{ __('select') }}</option>
                                                    <option value="1" @if(old('gender', $row->gender) == 1) selected @endif>{{ __('gender_male') }}</option>
                                                    <option value="2" @if(old('gender', $row->gender) == 2) selected @endif>{{ __('gender_female') }}</option>
                                                    <option value="3" @if(old('gender', $row->gender) == 3) selected @endif>{{ __('gender_other') }}</option>
                                                </select>
                                                @error('gender')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Date of Birth -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="dob">{{ __('field_date_of_birth') }}</label>
                                                <input type="date" class="form-control @error('dob') is-invalid @enderror" name="dob" id="dob" value="{{ old('dob', $row->dob) }}">
                                                @error('dob')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Status -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="status_id">{{ __('field_status') }} <span>*</span></label>
                                                <select class="form-control @error('status_id') is-invalid @enderror" name="status_id" id="status_id" required>
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach($statuses as $status)
                                                    <option value="{{ $status->id }}" @if(old('status_id', $row->statuses->first()->id ?? '') == $status->id) selected @endif>{{ $status->title }}</option>
                                                    @endforeach
                                                </select>
                                                @error('status_id')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Address -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="address">{{ __('field_address') }}</label>
                                                <textarea class="form-control @error('address') is-invalid @enderror" name="address" id="address" rows="2">{{ old('address', $row->present_address) }}</textarea>
                                                @error('address')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Country -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="country">{{ __('field_country') }}</label>
                                                <input type="text" class="form-control @error('country') is-invalid @enderror" name="country" id="country" value="{{ old('country', $row->country) }}">
                                                @error('country')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Province -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="province_id">{{ __('field_province') }}</label>
                                                <select class="form-control @error('province_id') is-invalid @enderror" name="province_id" id="province_id">
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach($provinces as $province)
                                                    <option value="{{ $province->id }}" @if(old('province_id', $row->present_province) == $province->id) selected @endif>{{ $province->title }}</option>
                                                    @endforeach
                                                </select>
                                                @error('province_id')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- District -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="district_id">{{ __('field_district') }}</label>
                                                <select class="form-control @error('district_id') is-invalid @enderror" name="district_id" id="district_id">
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach($districts as $district)
                                                    <option value="{{ $district->id }}" @if(old('district_id', $row->present_district) == $district->id) selected @endif>{{ $district->title }}</option>
                                                    @endforeach
                                                </select>
                                                @error('district_id')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Buttons -->
                                    <div class="form-group mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check"></i> {{ __('btn_update') }}
                                        </button>
                                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                                        </a>
                                    </div>
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

@section('scripts')
<script type="text/javascript">
    "use strict";
    
    // Photo preview
    $('#photo').on('change', function(){
        const file = this.files[0];
        if(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#photo-preview').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    // Province-District cascade
    $('#province_id').on('change', function(){
        var province_id = $(this).val();
        $('#district_id').html('<option value="">{{ __("select") }}</option>');
        
        if(province_id) {
            $.ajax({
                url: '{{ url("common/get-district-list") }}/' + province_id,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    $.each(data, function(key, value){
                        $('#district_id').append('<option value="'+ value.id +'">'+ value.title +'</option>');
                    });
                }
            });
        }
    });

    // Form validation
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByClassName('needs-validation');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
</script>
@endsection
