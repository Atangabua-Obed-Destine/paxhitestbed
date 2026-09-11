@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<link rel="stylesheet" href="{{ asset('dashboard/css/pages/wizard.css') }}">
<style>
    body { background: #f4f6f9; }
    .application-card { border: none; border-radius: 0.75rem; }
    .wizard-sec-bg { padding: 1.5rem; }
    .wizard > .steps .current a { background-color: #0c7cd5; }
    .wizard .content { min-height: auto; }
    .step-caption {
        color: #6c757d;
        margin-bottom: 1.25rem;
        font-size: 0.95rem;
    }
    fieldset.scheduler-border {
        border: 1px dashed #c5d0dc;
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
    }
    fieldset.scheduler-border legend {
        font-size: 1rem;
        font-weight: 600;
        width: auto;
        padding: 0 0.5rem;
        margin-bottom: 0;
    }
    .repeater-item {
        border: 1px solid #e3e6ed;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        position: relative;
    }
    .repeater-actions {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
    }
    .repeater-actions button {
        border: none;
        background: transparent;
        color: #dc3545;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
        padding: 0;
        width: 25px;
        height: 25px;
    }
    .repeater-actions button:hover {
        color: #a71d2a;
    }
    .matricule-status {
        background-color: #f8f9fa;
    }
    .matricule-status.is-ready {
        background-color: #eafaf1;
        border-color: #63ed7a !important;
    }
    .matricule-status.is-blocked {
        background-color: #fdeced;
        border-color: #fc544b !important;
    }
</style>
@endsection

@section('content')
@php
  $field = fn(string $slug) => \App\Models\Field::field($slug);
  $fieldStatusCache = [];
  $fieldEnabled = function (string $slug) use (&$fieldStatusCache, $field): bool {
    if (!array_key_exists($slug, $fieldStatusCache)) {
      $fieldStatusCache[$slug] = (int) optional($field($slug))->status === 1;
    }
    return $fieldStatusCache[$slug];
  };

  $oldRelatives = old('relations', []);
  $oldDocuments = old('titles', []);
  $documentCount = max(count($oldDocuments), 0);

  $identitySectionEnabled = $fieldEnabled('student_religion')
    || $fieldEnabled('student_caste')
    || $fieldEnabled('student_mother_tongue')
    || $fieldEnabled('student_nationality')
    || $fieldEnabled('student_marital_status')
    || $fieldEnabled('student_blood_group')
    || $fieldEnabled('student_national_id')
    || $fieldEnabled('student_passport_no');
  
  $addressSectionEnabled = $fieldEnabled('student_address');
  
  $familyFieldsEnabled = $fieldEnabled('student_father_name')
    || $fieldEnabled('student_father_occupation')
    || $fieldEnabled('student_mother_name')
    || $fieldEnabled('student_mother_occupation');
  
  $guardianSectionEnabled = $fieldEnabled('student_relatives');
  $familyOrGuardianEnabled = $familyFieldsEnabled || $guardianSectionEnabled;
  
  $academicEnabled = $fieldEnabled('student_school_name')
    || $fieldEnabled('student_collage_name');
  
  $documentsEnabled = $fieldEnabled('student_school_transcript')
    || $fieldEnabled('student_school_certificate')
    || $fieldEnabled('student_collage_transcript')
    || $fieldEnabled('student_collage_certificate')
    || $fieldEnabled('student_photo')
    || $fieldEnabled('student_signature')
    || $fieldEnabled('student_documents');
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="card application-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">{{ __('modal_add') }} {{ $title }}</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route($route.'.create') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                    <a href="{{ route($route.'.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                </div>
            </div>
            <div class="card-block">
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>{{ __('msg_error') }}</strong>
                        <ul class="mb-0 mt-2" style="columns: 2;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="wizard-sec-bg">
                    <form id="admin-student-create" class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data" style="display: none;">
                        @csrf

                        <!-- STEP 1: Programme & Admission -->
                        <h3>{{ __('Programme & Admission') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Select the programme and enter admission details for the new student.') }}</p>
                            
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Student Identification') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label>{{ __('field_student_id') }}</label>
                                        {{-- No matricule is shown here. It is generated when the
                                             record is saved, so a number on screen can never be
                                             claimed by another admin in the meantime. --}}
                                        <div id="matricule_status" class="matricule-status border rounded p-2">
                                            <span class="text-muted">{{ __('Choose a batch and programme.') }}</span>
                                        </div>
                                        <small class="form-text text-muted">{{ __('The matricule is generated automatically when you submit.') }}</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="admission_date">{{ __('field_admission_date') }} <span>*</span></label>
                                        <input type="date" class="form-control" name="admission_date" id="admission_date" value="{{ old('admission_date', now()->format('Y-m-d')) }}" required>
                                        <div class="invalid-feedback">{{ __('Please select admission date') }}</div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Programme Selection') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="batch">{{ __('field_batch') }} <span>*</span></label>
                                        <select class="form-control batch" name="batch" id="batch" data-selected="{{ old('batch') }}" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($batches as $batch)
                                                <option value="{{ $batch->id }}" {{ old('batch') == $batch->id ? 'selected' : '' }}>{{ $batch->title }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select a batch') }}</div>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="faculty">{{ __('field_faculty') }} <span>*</span></label>
                                        <select class="form-control faculty" name="faculty" id="faculty" data-selected="{{ old('faculty') }}" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($faculties as $faculty)
                                                <option value="{{ $faculty->id }}" {{ old('faculty') == $faculty->id ? 'selected' : '' }}>{{ $faculty->title }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select a faculty') }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-12">
                                        <label for="program">{{ __('field_program') }} <span>*</span></label>
                                        <select class="form-control program" name="program" id="program" data-selected="{{ old('program') }}" required>
                                            <option value="">{{ __('select') }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select a program') }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="session">{{ __('field_session') }} <span>*</span></label>
                                        <select class="form-control session" name="session" id="session" data-selected="{{ old('session') }}" required>
                                            <option value="">{{ __('select') }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select a session') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="semester">{{ __('field_semester') }} <span>*</span></label>
                                        <select class="form-control semester" name="semester" id="semester" data-selected="{{ old('semester') }}" required>
                                            <option value="">{{ __('select') }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select a semester') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="section">{{ __('field_section') }} <span>*</span></label>
                                        <select class="form-control section" name="section" id="section" data-selected="{{ old('section') }}" required>
                                            <option value="">{{ __('select') }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select a section') }}</div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Student Status') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-12">
                                        <label for="statuses">{{ __('field_status') }}</label>
                                        <select class="form-control select2" name="statuses[]" id="statuses" multiple>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->id }}" {{ collect(old('statuses', []))->contains($status->id) ? 'selected' : '' }}>{{ $status->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </fieldset>
                        </section>

                        <!-- STEP 2: Personal Information -->
                        <h3>{{ __('Personal Information') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Provide the student\'s personal information and identity details.') }}</p>
                            
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Basic Information') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="first_name">{{ __('field_first_name') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="first_name" id="first_name" value="{{ old('first_name') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter first name') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="last_name">{{ __('field_last_name') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="last_name" id="last_name" value="{{ old('last_name') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter last name') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="gender">{{ __('field_gender') }} <span>*</span></label>
                                        <select class="form-control" name="gender" id="gender" required>
                                            <option value="">{{ __('select') }}</option>
                                            <option value="1" {{ old('gender') == 1 ? 'selected' : '' }}>{{ __('gender_male') }}</option>
                                            <option value="2" {{ old('gender') == 2 ? 'selected' : '' }}>{{ __('gender_female') }}</option>
                                            <option value="3" {{ old('gender') == 3 ? 'selected' : '' }}>{{ __('gender_other') }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select gender') }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="dob">{{ __('field_dob') }} <span>*</span></label>
                                        <input type="date" class="form-control" name="dob" id="dob" value="{{ old('dob') }}" required>
                                        <div class="invalid-feedback">{{ __('Select date of birth') }}</div>
                                    </div>
                                    @if($fieldEnabled('student_nationality'))
                                    <div class="form-group col-md-6">
                                        <label for="nationality">{{ __('field_nationality') }}</label>
                                        @include('partials.country-select', ['name' => 'nationality', 'id' => 'nationality', 'value' => old('nationality')])
                                    </div>
                                    @endif
                                </div>
                            </fieldset>

                            @if($identitySectionEnabled)
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Identity & Background') }}</legend>
                                <div class="row">
                                    @if($fieldEnabled('student_religion'))
                                    <div class="form-group col-md-6">
                                        <label for="religion">{{ __('field_religion') }}</label>
                                        <input type="text" class="form-control" name="religion" id="religion" value="{{ old('religion') }}">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_caste'))
                                    <div class="form-group col-md-6">
                                        <label for="caste">{{ __('field_caste') }}</label>
                                        <input type="text" class="form-control" name="caste" id="caste" value="{{ old('caste') }}">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_mother_tongue'))
                                    <div class="form-group col-md-6">
                                        <label for="mother_tongue">{{ __('field_mother_tongue') }}</label>
                                        <input type="text" class="form-control" name="mother_tongue" id="mother_tongue" value="{{ old('mother_tongue') }}">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_marital_status'))
                                    <div class="form-group col-md-6">
                                        <label for="marital_status">{{ __('field_marital_status') }}</label>
                                        <select class="form-control" name="marital_status" id="marital_status">
                                            <option value="">{{ __('select') }}</option>
                                            <option value="1" {{ old('marital_status') == 1 ? 'selected' : '' }}>{{ __('marital_status_single') }}</option>
                                            <option value="2" {{ old('marital_status') == 2 ? 'selected' : '' }}>{{ __('marital_status_married') }}</option>
                                            <option value="3" {{ old('marital_status') == 3 ? 'selected' : '' }}>{{ __('marital_status_widowed') }}</option>
                                            <option value="4" {{ old('marital_status') == 4 ? 'selected' : '' }}>{{ __('marital_status_divorced') }}</option>
                                            <option value="5" {{ old('marital_status') == 5 ? 'selected' : '' }}>{{ __('marital_status_other') }}</option>
                                        </select>
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_blood_group'))
                                    <div class="form-group col-md-6">
                                        <label for="blood_group">{{ __('field_blood_group') }}</label>
                                        <select class="form-control" name="blood_group" id="blood_group">
                                            <option value="">{{ __('select') }}</option>
                                            <option value="1" {{ old('blood_group') == 1 ? 'selected' : '' }}>{{ __('A+') }}</option>
                                            <option value="2" {{ old('blood_group') == 2 ? 'selected' : '' }}>{{ __('A-') }}</option>
                                            <option value="3" {{ old('blood_group') == 3 ? 'selected' : '' }}>{{ __('B+') }}</option>
                                            <option value="4" {{ old('blood_group') == 4 ? 'selected' : '' }}>{{ __('B-') }}</option>
                                            <option value="5" {{ old('blood_group') == 5 ? 'selected' : '' }}>{{ __('AB+') }}</option>
                                            <option value="6" {{ old('blood_group') == 6 ? 'selected' : '' }}>{{ __('AB-') }}</option>
                                            <option value="7" {{ old('blood_group') == 7 ? 'selected' : '' }}>{{ __('O+') }}</option>
                                            <option value="8" {{ old('blood_group') == 8 ? 'selected' : '' }}>{{ __('O-') }}</option>
                                        </select>
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_national_id'))
                                    <div class="form-group col-md-6">
                                        <label for="national_id">{{ __('field_national_id') }}</label>
                                        <input type="text" class="form-control" name="national_id" id="national_id" value="{{ old('national_id') }}">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_passport_no'))
                                    <div class="form-group col-md-6">
                                        <label for="passport_no">{{ __('field_passport_no') }}</label>
                                        <input type="text" class="form-control" name="passport_no" id="passport_no" value="{{ old('passport_no') }}">
                                    </div>
                                    @endif
                                </div>
                            </fieldset>
                            @endif
                        </section>

                        <!-- STEP 3: Contact & Address -->
                        <h3>{{ __('Contact & Address') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Provide contact details and address information.') }}</p>
                            
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Contact Information') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="phone">{{ __('field_phone') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="phone" id="phone" value="{{ old('phone') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter phone number') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="email">{{ __('field_email') }} <span>*</span></label>
                                        <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter valid email') }}</div>
                                    </div>
                                    @if($fieldEnabled('student_emergency_phone'))
                                    <div class="form-group col-md-4">
                                        <label for="emergency_phone">{{ __('field_emergency_phone') }}</label>
                                        <input type="text" class="form-control" name="emergency_phone" id="emergency_phone" value="{{ old('emergency_phone') }}">
                                    </div>
                                    @endif
                                </div>
                            </fieldset>

                            @if($addressSectionEnabled)
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Present Address') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="country">{{ __('field_country') }}</label>
                                        <input type="text" class="form-control" name="country" id="country" value="{{ old('country', 'Cameroon') }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="present_province">{{ __('field_province') }}</label>
                                        <input type="text" class="form-control" name="present_province" id="present_province" value="{{ old('present_province') }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="present_district">{{ __('field_district') }}</label>
                                        <input type="text" class="form-control" name="present_district" id="present_district" value="{{ old('present_district') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="present_village">{{ __('Village / Quarter') }}</label>
                                        <input type="text" class="form-control" name="present_village" id="present_village" value="{{ old('present_village') }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="present_address">{{ __('House / Street Address') }}</label>
                                        <input type="text" class="form-control" name="present_address" id="present_address" value="{{ old('present_address') }}">
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Permanent Address') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="permanent_province">{{ __('field_province') }}</label>
                                        <input type="text" class="form-control" name="permanent_province" id="permanent_province" value="{{ old('permanent_province') }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="permanent_district">{{ __('field_district') }}</label>
                                        <input type="text" class="form-control" name="permanent_district" id="permanent_district" value="{{ old('permanent_district') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="permanent_village">{{ __('Village / Quarter') }}</label>
                                        <input type="text" class="form-control" name="permanent_village" id="permanent_village" value="{{ old('permanent_village') }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="permanent_address">{{ __('House / Street Address') }}</label>
                                        <input type="text" class="form-control" name="permanent_address" id="permanent_address" value="{{ old('permanent_address') }}">
                                    </div>
                                </div>
                            </fieldset>
                            @endif
                        </section>

                        <!-- STEP 4: Family & Guardians -->
                        @if($familyOrGuardianEnabled)
                        <h3>{{ __('Family & Guardians') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Provide family information and emergency contact details.') }}</p>
                            
                            @if($familyFieldsEnabled)
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Parents Information') }}</legend>
                                <div class="row">
                                    @if($fieldEnabled('student_father_name'))
                                    <div class="form-group col-md-6">
                                        <label for="father_name">{{ __('field_father_name') }}</label>
                                        <input type="text" class="form-control" name="father_name" id="father_name" value="{{ old('father_name') }}">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_father_occupation'))
                                    <div class="form-group col-md-6">
                                        <label for="father_occupation">{{ __('field_father_occupation') }}</label>
                                        <input type="text" class="form-control" name="father_occupation" id="father_occupation" value="{{ old('father_occupation') }}">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_mother_name'))
                                    <div class="form-group col-md-6">
                                        <label for="mother_name">{{ __('field_mother_name') }}</label>
                                        <input type="text" class="form-control" name="mother_name" id="mother_name" value="{{ old('mother_name') }}">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_mother_occupation'))
                                    <div class="form-group col-md-6">
                                        <label for="mother_occupation">{{ __('field_mother_occupation') }}</label>
                                        <input type="text" class="form-control" name="mother_occupation" id="mother_occupation" value="{{ old('mother_occupation') }}">
                                    </div>
                                    @endif
                                </div>
                            </fieldset>
                            @endif

                            @if($guardianSectionEnabled)
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Relatives / Guardians') }}</legend>
                                <div id="relativeRepeater">
                                    @if(count($oldRelatives) > 0)
                                        @foreach($oldRelatives as $index => $relation)
                                            <div class="repeater-item relative-item" data-index="{{ $index }}">
                                                <div class="repeater-actions">
                                                    <button type="button" class="remove-relative" aria-label="{{ __('Remove') }}">&times;</button>
                                                </div>
                                                <div class="row">
                                                    <div class="form-group col-md-3">
                                                        <label>{{ __('Relation') }}</label>
                                                        <input type="text" class="form-control" name="relations[{{ $index }}]" value="{{ $relation }}">
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label>{{ __('Name') }}</label>
                                                        <input type="text" class="form-control" name="relative_names[{{ $index }}]" value="{{ old('relative_names.'.$index) }}">
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label>{{ __('Occupation') }}</label>
                                                        <input type="text" class="form-control" name="occupations[{{ $index }}]" value="{{ old('occupations.'.$index) }}">
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label>{{ __('Phone') }}</label>
                                                        <input type="text" class="form-control" name="relative_phones[{{ $index }}]" value="{{ old('relative_phones.'.$index) }}">
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="form-group col-md-12">
                                                        <label>{{ __('Address') }}</label>
                                                        <input type="text" class="form-control" name="addresses[{{ $index }}]" value="{{ old('addresses.'.$index) }}">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="repeater-item relative-item" data-index="0">
                                            <div class="repeater-actions" style="display: none;">
                                                <button type="button" class="remove-relative" aria-label="{{ __('Remove') }}">&times;</button>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Relation') }}</label>
                                                    <input type="text" class="form-control" name="relations[0]">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Name') }}</label>
                                                    <input type="text" class="form-control" name="relative_names[0]">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Occupation') }}</label>
                                                    <input type="text" class="form-control" name="occupations[0]">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Phone') }}</label>
                                                    <input type="text" class="form-control" name="relative_phones[0]">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-12">
                                                    <label>{{ __('Address') }}</label>
                                                    <input type="text" class="form-control" name="addresses[0]">
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addRelative">
                                    <i class="fas fa-plus"></i> {{ __('Add another relative / guardian') }}
                                </button>
                            </fieldset>
                            @endif
                        </section>
                        @endif

                        <!-- STEP 5: Academic Background -->
                        @if($academicEnabled)
                        <h3>{{ __('Academic Background') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Provide previous educational institution details.') }}</p>
                            
                            @if($fieldEnabled('student_school_name'))
                            <fieldset class="scheduler-border">
                                <legend>{{ __('School / Secondary Education') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="school_name">{{ __('field_school_name') }}</label>
                                        <input type="text" class="form-control" name="school_name" id="school_name" value="{{ old('school_name') }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="school_exam_id">{{ __('field_exam_board_id') }}</label>
                                        <input type="text" class="form-control" name="school_exam_id" id="school_exam_id" value="{{ old('school_exam_id') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="school_graduation_year">{{ __('field_passing_year') }}</label>
                                        <input type="text" class="form-control" name="school_graduation_year" id="school_graduation_year" value="{{ old('school_graduation_year') }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="school_graduation_point">{{ __('field_point_grade') }}</label>
                                        <input type="text" class="form-control" name="school_graduation_point" id="school_graduation_point" value="{{ old('school_graduation_point') }}">
                                    </div>
                                </div>
                            </fieldset>
                            @endif

                            @if($fieldEnabled('student_collage_name'))
                            <fieldset class="scheduler-border">
                                <legend>{{ __('College / Higher Secondary Education') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="collage_name">{{ __('field_collage_name') }}</label>
                                        <input type="text" class="form-control" name="collage_name" id="collage_name" value="{{ old('collage_name') }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="collage_exam_id">{{ __('field_exam_board_id') }}</label>
                                        <input type="text" class="form-control" name="collage_exam_id" id="collage_exam_id" value="{{ old('collage_exam_id') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="collage_graduation_year">{{ __('field_passing_year') }}</label>
                                        <input type="text" class="form-control" name="collage_graduation_year" id="collage_graduation_year" value="{{ old('collage_graduation_year') }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="collage_graduation_point">{{ __('field_point_grade') }}</label>
                                        <input type="text" class="form-control" name="collage_graduation_point" id="collage_graduation_point" value="{{ old('collage_graduation_point') }}">
                                    </div>
                                </div>
                            </fieldset>
                            @endif
                        </section>
                        @endif

                        <!-- STEP 6: Documents & Upload -->
                        @if($documentsEnabled)
                        <h3>{{ __('Documents & Upload') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Upload required documents and student photo/signature.') }}</p>
                            
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Photo & Signature') }}</legend>
                                <div class="row">
                                    @if($fieldEnabled('student_photo'))
                                    <div class="form-group col-md-6">
                                        <label for="photo">{{ __('field_photo') }} ({{ __('Max 5MB') }})</label>
                                        <input type="file" class="form-control" name="photo" id="photo" accept="image/*">
                                        <small class="form-text text-muted">{{ __('Passport-sized photo, JPEG/PNG format') }}</small>
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_signature'))
                                    <div class="form-group col-md-6">
                                        <label for="signature">{{ __('field_signature') }} ({{ __('Max 2MB') }})</label>
                                        <input type="file" class="form-control" name="signature" id="signature" accept="image/*">
                                        <small class="form-text text-muted">{{ __('Signature sample, JPEG/PNG format') }}</small>
                                    </div>
                                    @endif
                                </div>
                            </fieldset>

                            @if($fieldEnabled('student_school_transcript') || $fieldEnabled('student_school_certificate') || $fieldEnabled('student_collage_transcript') || $fieldEnabled('student_collage_certificate'))
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Academic Documents') }}</legend>
                                <div class="row">
                                    @if($fieldEnabled('student_school_transcript'))
                                    <div class="form-group col-md-6">
                                        <label for="school_transcript">{{ __('field_school_transcript') }}</label>
                                        <input type="file" class="form-control" name="school_transcript" id="school_transcript">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_school_certificate'))
                                    <div class="form-group col-md-6">
                                        <label for="school_certificate">{{ __('field_school_certificate') }}</label>
                                        <input type="file" class="form-control" name="school_certificate" id="school_certificate">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_collage_transcript'))
                                    <div class="form-group col-md-6">
                                        <label for="collage_transcript">{{ __('field_collage_transcript') }}</label>
                                        <input type="file" class="form-control" name="collage_transcript" id="collage_transcript">
                                    </div>
                                    @endif
                                    @if($fieldEnabled('student_collage_certificate'))
                                    <div class="form-group col-md-6">
                                        <label for="collage_certificate">{{ __('field_collage_certificate') }}</label>
                                        <input type="file" class="form-control" name="collage_certificate" id="collage_certificate">
                                    </div>
                                    @endif
                                </div>
                            </fieldset>
                            @endif

                            @if($fieldEnabled('student_documents'))
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Additional Documents') }}</legend>
                                <div id="documentRepeater">
                                    @if($documentCount > 0)
                                        @foreach($oldDocuments as $index => $title)
                                            <div class="repeater-item document-item" data-index="{{ $index }}">
                                                <div class="repeater-actions">
                                                    <button type="button" class="remove-document" aria-label="{{ __('Remove') }}">&times;</button>
                                                </div>
                                                <div class="row">
                                                    <div class="form-group col-md-4">
                                                        <label>{{ __('Document Title') }}</label>
                                                        <input type="text" class="form-control" name="titles[{{ $index }}]" value="{{ $title }}">
                                                    </div>
                                                    <div class="form-group col-md-8">
                                                        <label>{{ __('File') }}</label>
                                                        <input type="file" class="form-control" name="documents[{{ $index }}]">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="repeater-item document-item" data-index="0">
                                            <div class="repeater-actions" style="display: none;">
                                                <button type="button" class="remove-document" aria-label="{{ __('Remove') }}">&times;</button>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('Document Title') }}</label>
                                                    <input type="text" class="form-control" name="titles[0]">
                                                </div>
                                                <div class="form-group col-md-8">
                                                    <label>{{ __('File') }}</label>
                                                    <input type="file" class="form-control" name="documents[0]">
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addDocument">
                                    <i class="fas fa-plus"></i> {{ __('Add another document') }}
                                </button>
                            </fieldset>
                            @endif

                            <div class="mt-4 text-end">
                                <button type="button" class="btn btn-primary d-none" id="wizardSubmitButton">
                                    <i class="fas fa-save"></i> {{ __('btn_submit') }}
                                </button>
                            </div>
                        </section>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student ID Confirmation Modal -->
<div class="modal fade" id="studentIdConfirmModal" tabindex="-1" role="dialog" aria-labelledby="studentIdConfirmModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentIdConfirmModalLabel">
                    <i class="fas fa-check-circle"></i> Confirm Student Registration
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-id-card text-primary" style="font-size: 3rem;"></i>
                </div>
                {{-- Filled in when the modal opens. It never shows a matricule:
                     the number is generated and claimed as the record is saved,
                     so that no two admissions can be given the same one. --}}
                <div id="confirmMatricule"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelSubmitBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmSubmitBtn">
                    <i class="fas fa-check"></i> Confirm & Submit
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/jquery-validation/js/jquery.validate.min.js') }}"></script>
<script src="{{ asset('dashboard/js/pages/jquery.steps.js') }}"></script>
<script>
"use strict";
(function($) {
    const formSelector = "#admin-student-create";
    const relativeRepeaterSelector = "#relativeRepeater";
    const documentRepeaterSelector = "#documentRepeater";
    const filterDistrictUrl = "{{ route('filter-district') }}";
    const finishButtonSelector = "#wizardSubmitButton";
    
    // Batch filter functionality - integrated directly
    const attachBatchFilterHandlers = () => {
        console.log('Attaching batch filter handlers...');
        
        // Faculty change handler
        $(".faculty").off('change').on('change', function(e){
            e.preventDefault();
            var $faculty = $(this);
            var $container = $faculty.closest('form');
            if(!$container.length){
                $container = $(document);
            }
            var program = $container.find(".program");
            
            console.log('Faculty changed to:', $faculty.val());
            
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-program') }}",
                data: {
                    _token: $('input[name=_token]').val(),
                    faculty: $faculty.val()
                },
                success: function(response){
                    console.log('Programs received:', response);
                    program.each(function(){
                        var $program = $(this);
                        var selectedValue = $program.data('selected');
                        $('option', $program).remove();
                        $program.append('<option value="">{{ __("select") }}</option>');
                        $.each(response, function(){
                            $('<option/>', {
                                'value': this.id,
                                'text': this.title
                            }).appendTo($program);
                        });
                        if(selectedValue){
                            $program.val(selectedValue);
                            $program.data('selected', '');
                            $program.trigger('change');
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Faculty filter error:', error);
                }
            });
        });
        
        // Batch change handler
        $(".batch").off('change').on('change', function(e){
            e.preventDefault();
            var $batch = $(this);
            var $container = $batch.closest('form');
            if(!$container.length){
                $container = $(document);
            }
            var program = $container.find(".program");
            
            console.log('Batch changed to:', $batch.val());
            
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-batch') }}",
                data: {
                    _token: $('input[name=_token]').val(),
                    batch: $batch.val()
                },
                success: function(response){
                    console.log('Programs received:', response);
                    program.each(function(){
                        var $program = $(this);
                        var selectedValue = $program.data('selected');
                        $('option', $program).remove();
                        $program.append('<option value="">{{ __("select") }}</option>');
                        $.each(response, function(){
                            $('<option/>', {
                                'value': this.id,
                                'text': this.title
                            }).appendTo($program);
                        });
                        if(selectedValue){
                            $program.val(selectedValue);
                            $program.data('selected', '');
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Batch filter error:', error);
                }
            });
        });
        
        // Program change handler
        $(".program").off('change').on('change', function(e){
            e.preventDefault();
            var $program = $(this);
            var $container = $program.closest('form');
            if(!$container.length){
                $container = $(document);
            }
            var session = $container.find(".session");
            var semester = $container.find(".semester");
            
            console.log('Program changed to:', $program.val());
            
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            
            // Fetch sessions
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-session') }}",
                data: {
                    _token: $('input[name=_token]').val(),
                    program: $program.val()
                },
                success: function(response){
                    console.log('Sessions received:', response);
                    session.each(function(){
                        var $session = $(this);
                        var selectedValue = $session.data('selected');
                        $('option', $session).remove();
                        $session.append('<option value="">{{ __("select") }}</option>');
                        $.each(response, function(){
                            $('<option/>', {
                                'value': this.id,
                                'text': this.title
                            }).appendTo($session);
                        });
                        if(selectedValue){
                            $session.val(selectedValue);
                            $session.data('selected', '');
                        }
                    });
                }
            });
            
            // Fetch semesters
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-semester') }}",
                data: {
                    _token: $('input[name=_token]').val(),
                    program: $program.val()
                },
                success: function(response){
                    console.log('Semesters received:', response);
                    semester.each(function(){
                        var $semester = $(this);
                        var selectedValue = $semester.data('selected');
                        $('option', $semester).remove();
                        $semester.append('<option value="">{{ __("select") }}</option>');
                        $.each(response, function(){
                            $('<option/>', {
                                'value': this.id,
                                'text': this.title
                            }).appendTo($semester);
                        });
                        if(selectedValue){
                            $semester.val(selectedValue);
                            $semester.data('selected', '');
                            $semester.trigger('change');
                        }
                    });
                }
            });
        });
        
        // Semester change handler
        $(".semester").off('change').on('change', function(e){
            e.preventDefault();
            var $semester = $(this);
            var $container = $semester.closest('form');
            if(!$container.length){
                $container = $(document);
            }
            var section = $container.find(".section");
            var programId = $container.find('.program').val();
            
            console.log('Semester changed to:', $semester.val());
            
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            
            $.ajax({
                type: 'POST',
                url: "{{ route('filter-section') }}",
                data: {
                    _token: $('input[name=_token]').val(),
                    semester: $semester.val(),
                    program: programId
                },
                success: function(response){
                    console.log('Sections received:', response);
                    section.each(function(){
                        var $section = $(this);
                        var selectedValue = $section.data('selected');
                        $('option', $section).remove();
                        $section.append('<option value="">{{ __("select") }}</option>');
                        $.each(response, function(){
                            $('<option/>', {
                                'value': this.id,
                                'text': this.title
                            }).appendTo($section);
                        });
                        if(selectedValue){
                            $section.val(selectedValue);
                            $section.data('selected', '');
                        }
                        $section.trigger('change');
                    });
                }
            });
        });
        
        console.log('✓ Batch filter handlers attached successfully');
    };
    
    // The theme ships Bootstrap 5, which has no jQuery .modal() plugin — the
    // old $('#studentIdConfirmModal').modal('show') was a silent no-op, so the
    // wizard's Submit did nothing at all.
    const studentIdConfirmModal = () => bootstrap.Modal.getOrCreateInstance(
        document.getElementById('studentIdConfirmModal')
    );

    // What the server last said about whether a matricule can be issued.
    // Deliberately never a matricule: a number displayed before the record is
    // written is a number a second admin can be shown at the same time.
    let matriculeReadiness = null;

    const renderMatriculeStatus = () => {
        const $panel = $('#matricule_status');

        if (!$panel.length) {
            return;
        }

        if (!matriculeReadiness) {
            $panel.removeClass('is-ready is-blocked')
                  .html($('<span class="text-muted"></span>').text("{{ __('Choose a batch and programme.') }}"));
            return;
        }

        if (matriculeReadiness.ready) {
            $panel.removeClass('is-blocked').addClass('is-ready').html(
                '<i class="fas fa-check-circle text-success"></i> <span class="text-success">' +
                "{{ __('A matricule will be generated when you submit.') }}" + '</span>' +
                '<div class="small text-muted mt-1">' + "{{ __('Format') }}" + ': <code>' +
                $('<div>').text(matriculeReadiness.format || '').html() + '</code></div>'
            );
            return;
        }

        const $list = $('<ul class="mb-0 ps-3 small"></ul>');

        $.each(matriculeReadiness.problems || [], function (i, problem) {
            $list.append(
                $('<li></li>')
                    .append($('<span></span>').text(problem.what))
                    .append(' ')
                    .append($('<em class="text-muted"></em>').text(problem.where))
            );
        });

        $panel.removeClass('is-ready').addClass('is-blocked').empty()
              .append('<div class="text-danger mb-1"><i class="fas fa-exclamation-triangle"></i> ' +
                      "{{ __('No matricule can be generated yet:') }}" + '</div>')
              .append($list);
    };

    // What the confirmation modal says just before the record is written.
    const renderConfirmMatricule = () => {
        const $body = $('#confirmMatricule');
        const $confirm = $('#confirmSubmitBtn');

        if (matriculeReadiness && matriculeReadiness.ready) {
            $body.html(
                '<h6 class="mb-2">' + "{{ __('Register this student?') }}" + '</h6>' +
                '<p class="text-muted mb-2">' +
                "{{ __('The matricule is generated now, as the record is saved, and shown on the student profile.') }}" +
                '</p>' +
                '<p class="small text-muted mb-0">' + "{{ __('Format') }}" + ': <code>' +
                $('<div>').text(matriculeReadiness.format || '').html() + '</code></p>'
            );
            $confirm.prop('disabled', false);
            return;
        }

        const $list = $('<ul class="mb-0 ps-3 small text-start"></ul>');

        $.each((matriculeReadiness && matriculeReadiness.problems) || [{
            what: "{{ __('The batch and programme have not been chosen.') }}",
            where: ''
        }], function (i, problem) {
            $list.append(
                $('<li></li>')
                    .append($('<span></span>').text(problem.what))
                    .append(' ')
                    .append($('<em class="text-muted"></em>').text(problem.where || ''))
            );
        });

        $body.empty()
             .append('<h6 class="mb-2 text-danger">' + "{{ __('No matricule can be generated yet') }}" + '</h6>')
             .append('<p class="text-muted small">' + "{{ __('Set the following, then submit again.') }}" + '</p>')
             .append($list);

        $confirm.prop('disabled', true);
    };

    // Ask whether a matricule CAN be generated, and say what is unset if not.
    const refreshMatriculeStatus = () => {
        const facultyId = $('.faculty').val();
        const batchId = $('.batch').val();
        const programId = $('.program').val();

        if (!facultyId || !batchId) {
            matriculeReadiness = null;
            renderMatriculeStatus();
            return;
        }

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $.ajax({
            type: 'POST',
            url: "{{ route('admin.student.generate-id') }}",
            data: {
                _token: $('input[name=_token]').val(),
                faculty_id: facultyId,
                batch_id: batchId,
                program_id: programId
            },
            success: function(response) {
                matriculeReadiness = response;
                renderMatriculeStatus();
            },
            error: function(xhr) {
                matriculeReadiness = {
                    ready: false,
                    problems: [{
                        what: "{{ __('The matricule settings could not be checked.') }}",
                        where: "{{ __('Try again, or submit and the system will report the problem.') }}"
                    }]
                };
                renderMatriculeStatus();
            }
        });
    };

    // Re-check whenever the pieces the matricule is built from change
    const attachStudentIdGenerator = () => {
        $('.faculty, .batch, .program').on('change', function() {
            refreshMatriculeStatus();
        });

        if ($('.faculty').val() && $('.batch').val()) {
            refreshMatriculeStatus();
        }
    };
    
    // Initialize wizard
    const initWizard = () => {
        const $form = $(formSelector);
        if (!$form.length) return;
        
        $form.show();
        
        if ($form.data('steps-initialized')) return;

        const validator = $form.validate({
            errorPlacement: function(error, element) {
                if (element.hasClass('select2-hidden-accessible')) {
                    error.insertAfter(element.next('.select2-container'));
                } else if (element.hasClass('form-check-input')) {
                    error.appendTo(element.closest('.form-check'));
                } else {
                    error.insertAfter(element);
                }
            },
            highlight: function(element) {
                $(element).addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
            }
        });

        const $finishButton = $(finishButtonSelector);
        const resolveLastIndex = () => {
            const sections = $form.find('section');
            return sections.length ? sections.length - 1 : 0;
        };

        const toggleFinishButton = (currentIndex) => {
            if (!$finishButton.length) return;
            const lastIndex = resolveLastIndex();
            const shouldShow = currentIndex === lastIndex;
            $finishButton.toggleClass('d-none', !shouldShow);
        };

        if ($finishButton.length) {
            $finishButton.on('click', function() {
                validator.settings.ignore = ":disabled";
                if (!$form.valid()) {
                    if (typeof validator.focusInvalid === 'function') {
                        validator.focusInvalid();
                    }
                    return;
                }
                if (typeof $form.steps === 'function') {
                    $form.steps('finish');
                    return;
                }
                $form.trigger('submit');
            });
        }

        $form.steps({
            headerTag: "h3",
            bodyTag: "section",
            transitionEffect: "slideLeft",
            autoFocus: true,
            labels: {
                finish: "{{ __('Submit') }}",
                next: "{{ __('btn_next') }}",
                previous: "{{ __('btn_previous') }}"
            },
            onInit: function(event, currentIndex) {
                toggleFinishButton(currentIndex);
                // Attach batch filter handlers after wizard initializes
                setTimeout(function() {
                    attachBatchFilterHandlers();
                    // If batch has a value, trigger it
                    const $batch = $('.batch');
                    if ($batch.length && $batch.val()) {
                        console.log('Triggering batch change with value:', $batch.val());
                        $batch.trigger('change');
                    }
                }, 100);
            },
            onStepChanged: function(event, currentIndex) {
                toggleFinishButton(currentIndex);
            },
            onStepChanging: function(event, currentIndex, newIndex) {
                if (currentIndex > newIndex) return true;
                validator.settings.ignore = ":hidden,:disabled";
                return $form.valid();
            },
            onFinishing: function() {
                validator.settings.ignore = ":disabled";

                return $form.valid();
            },
            onFinished: function() {
                // Confirm before submitting. The matricule itself is generated
                // by the server as the record is written, so there is nothing to
                // show here but whether that will work.
                renderConfirmMatricule();

                studentIdConfirmModal().show();
            }
        });

        let initialIndex = 0;
        if (typeof $form.steps === 'function') {
            const indexCandidate = $form.steps('getCurrentIndex');
            if (Number.isFinite(indexCandidate)) {
                initialIndex = indexCandidate;
            }
        }
        toggleFinishButton(Number.isInteger(initialIndex) ? initialIndex : 0);

        $form.data('steps-initialized', true);
    };

    // Relative repeater template
    const relativeTemplate = () => {
        const index = $(relativeRepeaterSelector + ' .relative-item').length;
        return `
            <div class="repeater-item relative-item" data-index="${index}">
                <div class="repeater-actions">
                    <button type="button" class="remove-relative" aria-label="{{ __('Remove') }}">&times;</button>
                </div>
                <div class="row">
                    <div class="form-group col-md-3">
                        <label>{{ __('Relation') }}</label>
                        <input type="text" class="form-control" name="relations[${index}]">
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Name') }}</label>
                        <input type="text" class="form-control" name="relative_names[${index}]">
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Occupation') }}</label>
                        <input type="text" class="form-control" name="occupations[${index}]">
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Phone') }}</label>
                        <input type="text" class="form-control" name="relative_phones[${index}]">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-12">
                        <label>{{ __('Address') }}</label>
                        <input type="text" class="form-control" name="addresses[${index}]">
                    </div>
                </div>
            </div>
        `;
    };

    // Document repeater template
    const documentTemplate = () => {
        const index = $(documentRepeaterSelector + ' .document-item').length;
        return `
            <div class="repeater-item document-item" data-index="${index}">
                <div class="repeater-actions">
                    <button type="button" class="remove-document" aria-label="{{ __('Remove') }}">&times;</button>
                </div>
                <div class="row">
                    <div class="form-group col-md-4">
                        <label>{{ __('Document Title') }}</label>
                        <input type="text" class="form-control" name="titles[${index}]">
                    </div>
                    <div class="form-group col-md-8">
                        <label>{{ __('File') }}</label>
                        <input type="file" class="form-control" name="documents[${index}]">
                    </div>
                </div>
            </div>
        `;
    };

    // Initialize relative repeater
    const initRelativeRepeater = () => {
        const $container = $(relativeRepeaterSelector);
        const $addButton = $('#addRelative');

        if (!$container.length || !$addButton.length) return;

        $addButton.on('click', function() {
            const $newItem = $(relativeTemplate());
            $container.append($newItem);
            $newItem.find('.repeater-actions').show();
            
            // Show remove button on first item when second is added
            if ($container.find('.relative-item').length > 1) {
                $container.find('.relative-item:first .repeater-actions').show();
            }
        });

        $container.on('click', '.remove-relative', function() {
            const $items = $container.find('.relative-item');
            if ($items.length > 1) {
                $(this).closest('.relative-item').remove();
                
                // Hide remove button if only one item left
                const $remainingItems = $container.find('.relative-item');
                if ($remainingItems.length === 1) {
                    $remainingItems.find('.repeater-actions').hide();
                }
            }
        });
    };

    // Initialize document repeater
    const initDocumentRepeater = () => {
        const $container = $(documentRepeaterSelector);
        const $addButton = $('#addDocument');

        if (!$container.length || !$addButton.length) return;

        $addButton.on('click', function() {
            const $newItem = $(documentTemplate());
            $container.append($newItem);
            $newItem.find('.repeater-actions').show();
            
            // Show remove button on first item when second is added
            if ($container.find('.document-item').length > 1) {
                $container.find('.document-item:first .repeater-actions').show();
            }
        });

        $container.on('click', '.remove-document', function() {
            const $items = $container.find('.document-item');
            if ($items.length > 1) {
                $(this).closest('.document-item').remove();
                
                // Hide remove button if only one item left
                const $remainingItems = $container.find('.document-item');
                if ($remainingItems.length === 1) {
                    $remainingItems.find('.repeater-actions').hide();
                }
            }
        });
    };

    // Initialize district filters
    /*
    const populateDistrictSelect = (provinceId, $districtSelect, selectedValue) => {
        if (!$districtSelect.length) return;
        
        $districtSelect.empty();
        $districtSelect.append($('<option/>', { value: '', text: "{{ __('select') }}" }));
        
        if (!provinceId) return;
        
        const token = $('meta[name="csrf-token"]').attr('content') || $('input[name=_token]').val();
        
        $.ajax({
            method: 'POST',
            url: filterDistrictUrl,
            data: {
                _token: token,
                province: provinceId
            },
            dataType: 'json'
        }).done(function(response) {
            let items = Array.isArray(response) ? response : (response && Array.isArray(response.data) ? response.data : []);
            
            items.forEach(function(item) {
                if (!item || typeof item !== 'object') return;
                
                const optionValue = item.id ?? item.value ?? null;
                const optionLabel = item.title ?? item.name ?? '';
                
                if (!optionValue) return;
                
                const isSelected = selectedValue && String(selectedValue) === String(optionValue);
                const $option = $('<option/>', {
                    value: optionValue,
                    text: optionLabel
                });
                if (isSelected) {
                    $option.prop('selected', true);
                }
                $districtSelect.append($option);
            });
        }).fail(function(error) {
            console.warn('Unable to fetch districts', error);
        });
    };

    const initDistrictSelects = () => {
        const $presentProvince = $('#present_province');
        const $permanentProvince = $('#permanent_province');
        const $presentDistrict = $('#present_district');
        const $permanentDistrict = $('#permanent_district');

        if (!$presentProvince.length && !$permanentProvince.length) return;

        const oldPresentDistrict = "{{ old('present_district') }}";
        const oldPermanentDistrict = "{{ old('permanent_district') }}";

        if ($presentProvince.length && $presentDistrict.length) {
            populateDistrictSelect($presentProvince.val(), $presentDistrict, oldPresentDistrict);
            $presentProvince.on('change', function() {
                populateDistrictSelect(this.value, $presentDistrict);
            });
        }

        if ($permanentProvince.length && $permanentDistrict.length) {
            populateDistrictSelect($permanentProvince.val(), $permanentDistrict, oldPermanentDistrict);
            $permanentProvince.on('change', function() {
                populateDistrictSelect(this.value, $permanentDistrict);
            });
        }
    };
    */

    // Initialize batch filter on load
    const initBatchFilter = () => {
        const $batch = $('.batch');
        const $faculty = $('.faculty');
        const $program = $('.program');
        const $semester = $('.semester');
        
        console.log('=== Batch Filter Debug ===');
        console.log('Faculty element found:', $faculty.length);
        console.log('Faculty current value:', $faculty.val());
        console.log('Batch element found:', $batch.length);
        console.log('Batch current value:', $batch.val());
        console.log('Program element found:', $program.length);
        console.log('Session element found:', $('.session').length);
        console.log('Semester element found:', $semester.length);
        console.log('Section element found:', $('.section').length);
        
        // Check if faculty/batch change handler is attached
        const facultyEvents = $faculty.length ? $._data($faculty[0], 'events') : null;
        const batchEvents = $batch.length ? $._data($batch[0], 'events') : null;
        console.log('Faculty change handler attached:', facultyEvents && facultyEvents.change ? 'YES' : 'NO');
        console.log('Batch change handler attached:', batchEvents && batchEvents.change ? 'YES' : 'NO');
        
        if (!facultyEvents || !facultyEvents.change || !batchEvents || !batchEvents.change) {
            console.warn('⚠ Handlers not attached yet, attaching now...');
            attachBatchFilterHandlers();
        }
        
        // If faculty has a value (including old value), trigger change to load programs
        if ($faculty.length && $faculty.val()) {
            console.log('✓ Triggering faculty change with value:', $faculty.val());
            $faculty.trigger('change');
        }
        
        // If batch has a value (including old value), trigger change to load programs
        if ($batch.length && $batch.val()) {
            console.log('✓ Triggering batch change with value:', $batch.val());
            $batch.trigger('change');
        } else {
            console.log('ℹ Faculty/Batch has no value - user must select manually');
            console.log('ℹ Once selected, Program dropdown should populate automatically');
        }
    };

    // Initialize everything
    $(function() {
        initWizard();
        initRelativeRepeater();
        initDocumentRepeater();
        attachStudentIdGenerator();
        // initDistrictSelects();
        
        // Delay batch filter initialization to ensure wizard is ready
        setTimeout(function() {
            initBatchFilter();
        }, 600);
        
        // Handle modal confirmation buttons
        $('#confirmSubmitBtn').on('click', function() {
            studentIdConfirmModal().hide();
            $(formSelector).trigger('submit');
        });
        
        $('#cancelSubmitBtn').on('click', function() {
            studentIdConfirmModal().hide();
        });
    });
})(jQuery);
</script>
@endsection
