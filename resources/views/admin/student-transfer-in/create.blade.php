@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
    <!-- Wizard css -->
    <link rel="stylesheet" href="{{ asset('dashboard/css/pages/wizard.css') }}">
    <style>
        body { background: #f4f6f9; }
        .matricule-status { background-color: #f8f9fa; }
        .matricule-status.is-ready { background-color: #eafaf1; border-color: #63ed7a !important; }
        .matricule-status.is-blocked { background-color: #fdeced; border-color: #fc544b !important; }
        .card { border: none; border-radius: 0.75rem; }
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
    </style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Card ] start -->
            <div class="col-sm-12">
                <div class="card">
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
                    </div>

                    @php
                        function field($slug){
                            return \App\Models\Field::field($slug);
                        }
                        $oldGuardians = old('guardians', [[
                            'type' => 'Parent',
                            'is_primary' => 1,
                        ]]);
                        $oldAcademicHistory = old('academic_history', [[]]);
                        $oldLanguages = old('languages', [[
                            'language' => 'English',
                            'fluency_level' => 'excellent',
                        ]]);
                    @endphp
                    <div class="wizard-sec-bg">
                    <form id="wizard-advanced-form" class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data" style="display: none;">
                      @csrf

                        <!-- STEP 1: Programme & Admission -->
                        <h3>{{ __('Programme & Admission') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Select the programme and enter admission details for the transfer student.') }}</p>
                            
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
                            <p class="step-caption">{{ __('Provide your legal personal information exactly as it appears on official identification documents.') }}</p>
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Names & Identification') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="first_name">{{ __('field_first_name') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="first_name" id="first_name" value="{{ old('first_name') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter your first name.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="last_name">{{ __('field_last_name') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="last_name" id="last_name" value="{{ old('last_name') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter your last name.') }}</div>
                                    </div>
                                    @if(optional(field('student_other_names'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="other_names">{{ __('Other Names (if any)') }}</label>
                                            <input type="text" class="form-control" name="other_names" id="other_names" value="{{ old('other_names') }}">
                                        </div>
                                    @endif
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="gender">{{ __('field_gender') }} <span>*</span></label>
                                        <select class="form-control" name="gender" id="gender" required>
                                            <option value="">{{ __('select') }}</option>
                                            <option value="1" {{ old('gender') == 1 ? 'selected' : '' }}>{{ __('gender_male') }}</option>
                                            <option value="2" {{ old('gender') == 2 ? 'selected' : '' }}>{{ __('gender_female') }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ __('Select your gender.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="dob">{{ __('field_dob') }} <span>*</span></label>
                                        <input type="date" class="form-control" name="dob" id="dob" value="{{ old('dob') }}" required>
                                        <div class="invalid-feedback">{{ __('Specify your date of birth.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="nationality">{{ __('field_nationality') }} <span>*</span></label>
                                        @include('partials.country-select', ['name' => 'nationality', 'id' => 'nationality', 'value' => old('nationality'), 'required' => true])
                                        <div class="invalid-feedback">{{ __('Provide your nationality.') }}</div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Birth & Religious Details') }}</legend>
                                <div class="row">
                                    @if(optional(field('student_birth_city'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_city">{{ __('City / Town of Birth') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_city" id="birth_city" value="{{ old('birth_city') }}" required>
                                        </div>
                                    @endif
                                    @if(optional(field('student_birth_division'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_division">{{ __('Division') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_division" id="birth_division" value="{{ old('birth_division') }}" required>
                                        </div>
                                    @endif
                                    @if(optional(field('student_birth_region'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_region">{{ __('Region') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_region" id="birth_region" value="{{ old('birth_region') }}" required>
                                        </div>
                                    @endif
                                    @if(optional(field('student_birth_country'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_country">{{ __('Country of Birth') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_country" id="birth_country" value="{{ old('birth_country') }}" required>
                                        </div>
                                    @endif
                                </div>
                                <div class="row">
                                    @if(optional(field('student_religion'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="religion">{{ __('field_religion') }} <span>*</span></label>
                                            <select class="form-control" name="religion" id="religion" required>
                                                <option value="">{{ __('select') }}</option>
                                                @foreach($religions as $religion)
                                                <option value="{{ $religion->id }}" {{ old('religion') == $religion->id ? 'selected' : '' }}>{{ $religion->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    @if(optional(field('student_catholic_baptised'))->status == 1)
                                        <div class="form-group col-md-8" id="catholic-sacraments-container" style="display: none;">
                                            <label class="d-block mb-2">{{ __('Catholic Sacraments') }}</label>
                                            <div class="row">
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="is_catholic_baptised" name="is_catholic_baptised" {{ old('is_catholic_baptised') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="is_catholic_baptised">
                                                            {{ __('I am a baptised Catholic with proof of baptism') }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="is_confirmed" name="is_confirmed" {{ old('is_confirmed') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="is_confirmed">
                                                            {{ __('I have received Confirmation') }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="has_first_communion" name="has_first_communion" {{ old('has_first_communion') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="has_first_communion">
                                                            {{ __('I have received First Holy Communion') }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @if(optional(field('student_mother_tongue'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="mother_tongue">{{ __('field_mother_tongue') }}</label>
                                            <input type="text" class="form-control" name="mother_tongue" id="mother_tongue" value="{{ old('mother_tongue') }}">
                                        </div>
                                    @endif
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Official Identification') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="national_id">{{ __('National Identity Card Number') }}</label>
                                        <input type="text" class="form-control" name="national_id" id="national_id" value="{{ old('national_id') }}">
                                    </div>
                                    @if(optional(field('student_national_id_issue_date'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="national_id_issue_date">{{ __('National ID Issue Date') }}</label>
                                            <input type="date" class="form-control" name="national_id_issue_date" id="national_id_issue_date" value="{{ old('national_id_issue_date') }}">
                                        </div>
                                    @endif
                                    @if(optional(field('student_national_id_issue_place'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="national_id_issue_place">{{ __('National ID Issue Place') }}</label>
                                            <input type="text" class="form-control" name="national_id_issue_place" id="national_id_issue_place" value="{{ old('national_id_issue_place') }}">
                                        </div>
                                    @endif
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="passport_no">{{ __('Passport Number (if Applicable)') }}</label>
                                        <input type="text" class="form-control" name="passport_no" id="passport_no" value="{{ old('passport_no') }}">
                                    </div>
                                    @if(optional(field('student_passport_issue_date'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="passport_issue_date">{{ __('Passport Issue Date') }}</label>
                                            <input type="date" class="form-control" name="passport_issue_date" id="passport_issue_date" value="{{ old('passport_issue_date') }}">
                                        </div>
                                    @endif
                                    @if(optional(field('student_passport_issue_country'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="passport_issue_country">{{ __('Passport Issue Country') }}</label>
                                            <input type="text" class="form-control" name="passport_issue_country" id="passport_issue_country" value="{{ old('passport_issue_country') }}">
                                        </div>
                                    @endif
                                </div>
                            </fieldset>
                        </section>

                        <!-- STEP 3: Contact & Address -->
                        <h3>{{ __('Address & Contact') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Provide current and permanent address information so we can reach you physically or through mail.') }}</p>
                            
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Current Residence') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="country">{{ __('field_country') }} <span>*</span></label>
                                        @include('partials.country-select', ['name' => 'country', 'id' => 'country', 'value' => old('country'), 'required' => true])
                                        <div class="invalid-feedback">{{ __('Enter the country where you currently reside.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="present_province">{{ __('field_province') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="present_province" id="present_province" value="{{ old('present_province') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter your current province.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="present_district">{{ __('field_district') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="present_district" id="present_district" value="{{ old('present_district') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter the district of your current residence.') }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="present_village">{{ __('Village / Quarter') }}</label>
                                        <input type="text" class="form-control" name="present_village" id="present_village" value="{{ old('present_village') }}">
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label for="present_address">{{ __('House / Street Address') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="present_address" id="present_address" value="{{ old('present_address') }}" required>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Permanent Address & Postal Details') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="permanent_province">{{ __('field_province') }}</label>
                                        <input type="text" class="form-control" name="permanent_province" id="permanent_province" value="{{ old('permanent_province') }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="permanent_district">{{ __('field_district') }}</label>
                                        <input type="text" class="form-control" name="permanent_district" id="permanent_district" value="{{ old('permanent_district') }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="permanent_village">{{ __('Village / Quarter') }}</label>
                                        <input type="text" class="form-control" name="permanent_village" id="permanent_village" value="{{ old('permanent_village') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="permanent_address">{{ __('House / Street Address') }}</label>
                                        <input type="text" class="form-control" name="permanent_address" id="permanent_address" value="{{ old('permanent_address') }}">
                                    </div>
                                    @if(optional(field('student_postal_address'))->status == 1)
                                        <div class="form-group col-md-6">
                                            <label for="postal_address_line1">{{ __('Postal Address / P.O. Box') }}</label>
                                            <input type="text" class="form-control" name="postal_address_line1" id="postal_address_line1" value="{{ old('postal_address_line1') }}">
                                            <input type="text" class="form-control mt-2" name="postal_address_line2" id="postal_address_line2" value="{{ old('postal_address_line2') }}" placeholder="{{ __('Additional postal details (optional)') }}">
                                        </div>
                                    @endif
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Contact Channels') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="phone">{{ __('Primary Phone Number') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="phone" id="phone" value="{{ old('phone') }}" required>
                                        <div class="invalid-feedback">{{ __('Provide a reachable phone number.') }}</div>
                                    </div>
                                    @if(optional(field('student_alternate_phone'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="alternate_phone">{{ __('Alternate Phone Number') }}</label>
                                            <input type="text" class="form-control" name="alternate_phone" id="alternate_phone" value="{{ old('alternate_phone') }}">
                                        </div>
                                    @endif
                                    <div class="form-group col-md-4">
                                        <label for="email">{{ __('field_email') }} <span>*</span></label>
                                        <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter a valid email address.') }}</div>
                                    </div>
                                </div>
                            </fieldset>
                        </section>

                        <!-- STEP 4: Guardians & Emergency Contacts -->
                        @if(optional(field('application_guardians'))->status == 1)
                            <h3>{{ __('Guardians & Emergency Contacts') }}</h3>
                            <section class="form-step">
                                <p class="step-caption">{{ __('Provide details for your parents, legal guardians, or sponsors who can be contacted during the admission process.') }}</p>
                                <div id="guardianRepeater">
                                    @foreach($oldGuardians as $index => $guardian)
                                        <div class="repeater-item guardian-item" data-index="{{ $index }}">
                                            <div class="repeater-actions" @if($loop->first && count($oldGuardians) === 1) style="display: none;" @endif>
                                                <button type="button" class="remove-guardian" aria-label="{{ __('Remove guardian') }}">&times;</button>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Full Name') }} <span>*</span></label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][full_name]" value="{{ $guardian['full_name'] ?? '' }}" required>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Relationship to Applicant') }}</label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][relationship]" value="{{ $guardian['relationship'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('Type') }} <span>*</span></label>
                                                    <select class="form-control" name="guardians[{{ $index }}][type]" required>
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($guardianTypes as $type)
                                                            <option value="{{ $type }}" {{ ($guardian['type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('Occupation') }}</label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][occupation]" value="{{ $guardian['occupation'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('Email Address') }}</label>
                                                    <input type="email" class="form-control" name="guardians[{{ $index }}][email]" value="{{ $guardian['email'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('Primary Phone') }} <span>*</span></label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][phone_primary]" value="{{ $guardian['phone_primary'] ?? '' }}" required>
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('Alternate Phone') }}</label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][phone_secondary]" value="{{ $guardian['phone_secondary'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-4 d-flex align-items-center">
                                                    <div class="form-check mt-3">
                                                        <input class="form-check-input" type="checkbox" value="1" name="guardians[{{ $index }}][is_primary]" id="guardian_primary_{{ $index }}" {{ ($guardian['is_primary'] ?? $loop->first) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="guardian_primary_{{ $index }}">{{ __('Primary contact') }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Address Line 1') }}</label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][address_line1]" value="{{ $guardian['address_line1'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Address Line 2') }}</label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][address_line2]" value="{{ $guardian['address_line2'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('City / Town') }}</label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][city]" value="{{ $guardian['city'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('State / Region') }}</label>
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][state]" value="{{ $guardian['state'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>{{ __('Country') }}</label>
                                                    @include('partials.country-select', ['name' => "guardians[{$index}][country]", 'value' => $guardian['country'] ?? null])
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addGuardian">{{ __('Add another guardian / sponsor') }}</button>
                            </section>
                        @endif

                        <!-- STEP 5: Academic Background -->
                        @if(optional(field('application_academic_history'))->status == 1)
                            <h3>{{ __('Academic Background') }}</h3>
                            <section class="form-step">
                                <p class="step-caption">{{ __('List all secondary and post-secondary institutions attended starting with the most recent.') }}</p>
                                <div id="academicHistoryRepeater">
                                    @foreach($oldAcademicHistory as $index => $history)
                                        <div class="repeater-item academic-item" data-index="{{ $index }}">
                                            <div class="repeater-actions" @if($loop->first && count($oldAcademicHistory) === 1) style="display: none;" @endif>
                                                <button type="button" class="remove-academic" aria-label="{{ __('Remove record') }}">&times;</button>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Certificate / Qualification') }} <span>*</span></label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][certificate_obtained]" value="{{ $history['certificate_obtained'] ?? '' }}" required>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Awarding body') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][awarding_body]" value="{{ $history['awarding_body'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('School / Institution attended') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][institution_name]" value="{{ $history['institution_name'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('City') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][city]" value="{{ $history['city'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Country') }}</label>
                                                    @include('partials.country-select', ['name' => "academic_history[{$index}][country]", 'value' => $history['country'] ?? null])
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Language of Instruction') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][instruction_language]" value="{{ $history['instruction_language'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Start year') }}</label>
                                                    <input type="number" class="form-control" min="1900" max="2200" name="academic_history[{{ $index }}][start_year]" value="{{ $history['start_year'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Completion year') }}</label>
                                                    <input type="number" class="form-control" min="1900" max="2200" name="academic_history[{{ $index }}][end_year]" value="{{ $history['end_year'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('GCE O-Level / Probatoire Subjects Passed') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][gce_ol_detail]" value="{{ $history['gce_ol_detail'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('GCE A-Level / Baccalaureate Subjects Passed') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][gce_al_detail]" value="{{ $history['gce_al_detail'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Probatoire Stream (if applicable)') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][probatoire_detail]" value="{{ $history['probatoire_detail'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Baccalaureate Option (if applicable)') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][baccalaureate_detail]" value="{{ $history['baccalaureate_detail'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>{{ __('Additional Notes') }}</label>
                                                <textarea class="form-control" name="academic_history[{{ $index }}][notes]" rows="2">{{ $history['notes'] ?? '' }}</textarea>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addAcademicHistory">{{ __('Add another institution') }}</button>
                            </section>
                        @endif

                        @if(optional(field('application_language_proficiency'))->status == 1)
                            <h3>{{ __('Language Proficiency') }}</h3>
                            <section class="form-step">
                                <p class="step-caption">{{ __('Indicate languages you speak or understand and your proficiency level.') }}</p>
                                <div id="languageRepeater">
                                    @foreach($oldLanguages as $index => $language)
                                        <div class="repeater-item language-item" data-index="{{ $index }}">
                                            <div class="repeater-actions" @if($loop->first && count($oldLanguages) === 1) style="display: none;" @endif>
                                                <button type="button" class="remove-language" aria-label="{{ __('Remove language') }}">&times;</button>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-6">
                                                    <label>{{ __('Language') }}</label>
                                                    <input type="text" class="form-control" name="languages[{{ $index }}][language]" value="{{ $language['language'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Years of Study / Use') }}</label>
                                                    <input type="number" min="0" class="form-control" name="languages[{{ $index }}][years_of_study]" value="{{ $language['years_of_study'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Fluency Level') }}</label>
                                                    <select class="form-control" name="languages[{{ $index }}][fluency_level]">
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($fluencyOptions as $option)
                                                            <option value="{{ $option }}" {{ ($language['fluency_level'] ?? '') === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addLanguage">{{ __('Add another language') }}</button>
                            </section>
                        @endif

                        <!-- STEP 6: Documents -->
                        <h3>{{ __('Documents') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Upload required documents for the student admission.') }}</p>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Passport Photo & Signature') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="photo">{{ __('Recent Passport Photograph (max 5MB)') }}</label>
                                        <input type="file" class="form-control" name="photo" id="photo" accept="image/*">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="signature">{{ __('Signature Sample (max 2MB)') }}</label>
                                        <input type="file" class="form-control" name="signature" id="signature" accept="image/*">
                                    </div>
                                </div>
                            </fieldset>

                            @if(optional(field('application_document_checklist'))->status == 1)
                                <fieldset class="scheduler-border">
                                    <legend>{{ __('Document Checklist') }}</legend>
                                    <p class="document-help">{{ __('Upload clear scans or photos. Accepted formats: JPG, PNG, PDF. Maximum size per file: 10MB.') }}</p>
                                    <div class="row">
                                        @foreach($documentRequirements as $key => $document)
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="document_{{ $key }}" class="form-label">{{ $document['label'] }} @if($document['required'])<span>*</span>@endif</label>
                                                    <input type="file" class="form-control document-input" data-document-key="{{ $key }}" name="documents[{{ $key }}][file]" id="document_{{ $key }}" @if($document['required']) required @endif>
                                                    @if(!empty($document['description']))
                                                        <small class="document-help">{{ $document['description'] }}</small>
                                                    @endif
                                                    <textarea class="form-control mt-2" name="documents[{{ $key }}][note]" rows="1" placeholder="{{ __('Notes (optional)') }}">{{ old('documents.'.$key.'.note') }}</textarea>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="card document-summary mt-3">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3">{{ __('Checklist Status') }}</h5>
                                            <p class="small text-muted mb-3">{{ __('Track which files are attached before you submit. Required documents show a warning until a file is selected.') }}</p>
                                            <div class="table-responsive">
                                                <table class="table table-sm align-middle" id="documentSummaryTable">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('Document') }}</th>
                                                            <th class="text-center">{{ __('Required') }}</th>
                                                            <th>{{ __('Status') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($documentRequirements as $key => $document)
                                                            <tr data-document-row="{{ $key }}">
                                                                <td>{{ $document['label'] }}</td>
                                                                <td class="text-center">
                                                                    @if($document['required'])
                                                                        <span class="badge bg-danger">{{ __('Yes') }}</span>
                                                                    @else
                                                                        <span class="badge bg-secondary">{{ __('Optional') }}</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <span class="document-status badge bg-warning text-dark" data-status="pending">{{ __('Awaiting Upload') }}</span>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </fieldset>
                            @endif

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Additional Documents') }}</legend>
                                <p class="document-help">{{ __('Upload any additional documents not listed above that may be required for this transfer.') }}</p>
                                <div class="container-fluid">
                                    <div id="newDocument" class="clearfix"></div>
                                    <div class="form-group">
                                        <button id="addDocument" type="button" class="btn btn-info"><i class="fas fa-plus"></i> {{ __('btn_add_new') }}</button>
                                    </div>
                                </div>
                            </fieldset>
                        </section>

                        <!-- STEP 7: Transfer Information (DON'T CHANGE - Transfer Specific) -->
                        <h3>{{ __('tab_transfer_info') }}</h3>
                        <section class="form-step">
                            <!-- Form Start--->
                            <fieldset class="row scheduler-border">
                              <div class="form-group col-md-4">
                                  <label for="transfer_id">{{ __('field_transfer_id') }} <span>*</span></label>
                                  <input type="text" class="form-control autonumber" name="transfer_id" id="transfer_id" value="{{ old('transfer_id') }}" required>

                                  <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_transfer_id') }}
                                  </div>
                              </div>

                              <div class="form-group col-md-4">
                                  <label for="university_name">{{ __('field_university_name') }} <span>*</span></label>
                                  <input type="text" class="form-control" name="university_name" id="university_name" value="{{ old('university_name') }}" required>

                                  <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_university_name') }}
                                  </div>
                              </div>

                              <div class="form-group col-md-4">
                                  <label for="date">{{ __('field_date') }} <span>*</span></label>
                                  <input type="date" class="form-control date" name="date" id="date" value="{{ date('Y-m-d') }}" required>

                                  <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_date') }}
                                  </div>
                              </div>

                              <div class="form-group col-md-12">
                                  <label for="note">{{ __('field_note') }}</label>
                                  <textarea class="form-control" name="note" id="note">{{ old('note') }}</textarea>

                                  <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_note') }}
                                  </div>
                              </div>
                            </fieldset>

                            <!-- Academic Info -->
                            <fieldset class="row scheduler-border">
                            <legend>{{ __('field_transfer_credits') }}</legend>
                            <div class="container-fluid">

                            <div id="newTField" class="clearfix"></div>
                            <div class="form-group">
                                <button id="addField" type="button" class="btn btn-info"><i class="fas fa-plus"></i> {{ __('btn_add_new') }}</button>
                            </div>
                            </div>
                            </fieldset>
                            <!-- Form End--->
                        </section>
                    </form>
                    </div>

                </div>
            </div>
            <!-- [ Card ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
    <!-- validate Js -->
    <script src="{{ asset('dashboard/plugins/jquery-validation/js/jquery.validate.min.js') }}"></script>

    <!-- Wizard Js -->
    <script src="{{ asset('dashboard/js/pages/jquery.steps.js') }}"></script>

    <script type="text/javascript">
        "use strict";
        var form = $("#wizard-advanced-form").show();

        form.steps({
            headerTag: "h3",
            bodyTag: "section",
            transitionEffect: "slideLeft",
            labels: 
            {
                finish: "{{ __('btn_finish') }}",
                next: "{{ __('btn_next') }}",
                previous: "{{ __('btn_previous') }}",
            },
            onStepChanging: function (event, currentIndex, newIndex)
            {
                // Allways allow previous action even if the current form is not valid!
                if (currentIndex > newIndex)
                {
                    return true;
                }
                // Needed in some cases if the user went back (clean up)
                if (currentIndex < newIndex)
                {
                    // To remove error styles
                    form.find(".body:eq(" + newIndex + ") label.error").remove();
                    form.find(".body:eq(" + newIndex + ") .error").removeClass("error");
                }
                form.validate().settings.ignore = ":disabled,:hidden";
                return form.valid();
            },
            onStepChanged: function (event, currentIndex, priorIndex)
            {
                
            },
            onFinishing: function (event, currentIndex)
            {
                form.validate().settings.ignore = ":disabled";
                return form.valid();
            },
            onFinished: function (event, currentIndex)
            {
                $("#wizard-advanced-form").submit();
            }
        }).validate({
            errorPlacement: function errorPlacement(error, element) { element.before(error); },
            rules: {

            }
        });
    </script>

    <!-- Student ID Auto-Generation Script -->
    <script type="text/javascript">
    (function ($) {
        "use strict";
        
        // Whether a matricule can be issued — never a matricule itself. A
        // number shown before the record is written is a number a second admin
        // can be shown at the same time; it is claimed as the record is saved.
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
                error: function() {
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
        $('.faculty, .batch, .program').on('change', function() {
            refreshMatriculeStatus();
        });

        if ($('.faculty').val() && $('.batch').val()) {
            refreshMatriculeStatus();
        }
        
    }(jQuery));
    </script>

    <script type="text/javascript">
    (function ($) {
        "use strict";
        // add Field
        $(document).on('click', '#addField', function () {
            var html = '';
            html += '<hr/>';
            html += '<div id="inputTFormField" class="row">';
            html += '<div class="form-group col-md-4"><label for="t_sessions" class="form-label">{{ __('field_session') }} <span>*</span></label><select class="form-control select2" name="t_sessions[]" id="t_sessions" required><option value="">{{ __('select') }}</option> @foreach($sessions as $session) <option value="{{ $session->id }}">{{ $session->title }}</option> @endforeach </select> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_session') }} </div> </div>';
            html += '<div class="form-group col-md-4"> <label for="t_semesters" class="form-label">{{ __('field_semester') }} <span>*</span></label> <select class="form-control select2" name="t_semesters[]" id="t_semesters" required> <option value="">{{ __('select') }}</option> @foreach($semesters as $semester) <option value="{{ $semester->id }}">{{ $semester->title }}</option> @endforeach </select> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_semester') }} </div> </div>';
            html += '<div class="form-group col-md-4"> <label for="t_subjects" class="form-label">{{ __('field_subject') }} <span>*</span></label> <select class="form-control select2" name="t_subjects[]" id="t_subjects" required> <option value="">{{ __('select') }}</option> @foreach($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->title }}</option> @endforeach </select> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_subject') }}</div></div>';
            html += '<div class="form-group col-md-4"> <label for="marks" class="form-label">{{ __('field_mark') }} <span>*</span></label><input type="text" class="form-control autonumber" name="marks[]" id="marks" value="{{ old('marks') }}" data-v-max="999" data-v-min="0" required> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_mark') }} </div> </div>';
            html += '<div class="form-group col-md-4"><button id="removeTField" type="button" class="btn btn-danger btn-filter"><i class="fas fa-trash-alt"></i> {{ __('btn_remove') }}</button></div>';
            html += '</div>';

            $('#newTField').append(html);

            // [ Single Select ] start
            $(".select2").select2();
        });

        // remove Field
        $(document).on('click', '#removeTField', function () {
            $(this).closest('#inputTFormField').remove();

            // [ Single Select ] start
            $(".select2").select2();
        });
    }(jQuery));
    </script>


    <script type="text/javascript">
    (function ($) {
        "use strict";
        // add Field
        $(document).on('click', '#addField', function () {
            var html = '';
            html += '<hr/>';
            html += '<div id="inputFormField" class="row">';
            html += '<div class="form-group col-md-4"><label for="relation" class="form-label">{{ __('field_relation') }} <span>*</span></label><input type="text" class="form-control" name="relations[]" id="relation" value="{{ old('relation') }}" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_relation') }}</div></div>';
            html += '<div class="form-group col-md-4"><label for="relative_name" class="form-label">{{ __('field_name') }} <span>*</span></label><input type="text" class="form-control" name="relative_names[]" id="relative_name" value="{{ old('relative_name') }}" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_name') }}</div></div>';
            html += '<div class="form-group col-md-4"><label for="occupation" class="form-label">{{ __('field_occupation') }} <span>*</span></label><input type="text" class="form-control" name="occupations[]" id="occupation" value="{{ old('occupation') }}" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_occupation') }}</div></div>';
            html += '<div class="form-group col-md-4"><label for="relative_phone" class="form-label">{{ __('field_phone') }} <span>*</span></label><input type="text" class="form-control" name="relative_phones[]" id="relative_phone" value="{{ old('relative_phone') }}" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_phone') }}</div></div>';
            html += '<div class="form-group col-md-4"><label for="address" class="form-label">{{ __('field_address') }} <span>*</span></label><input type="text" class="form-control" name="addresses[]" id="address" value="{{ old('address') }}" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_address') }}</div></div>';
            html += '<div class="form-group col-md-4"><button id="removeField" type="button" class="btn btn-danger btn-filter"><i class="fas fa-trash-alt"></i> {{ __('btn_remove') }}</button></div>';
            html += '</div>';

            $('#newField').append(html);
        });

        // remove Field
        $(document).on('click', '#removeField', function () {
            $(this).closest('#inputFormField').remove();
        });
    }(jQuery));
    </script>
    <script type="text/javascript">
    (function ($) {
        "use strict";
        // add Field
        $(document).on('click', '#addDocument', function () {
            var html = '';
            html += '<hr/>';
            html += '<div id="documentFormField" class="row">';
            html += '<div class="form-group col-md-4"><label for="t_titles" class="form-label">{{ __('field_title') }} <span>*</span></label><input type="text" class="form-control" name="titles[]" id="t_titles" value="{{ old('titles') }}" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div></div>';
            html += '<div class="form-group col-md-4"><label for="document" class="form-label">{{ __('field_document') }} <span>*</span></label><input type="file" class="form-control" name="documents[]" id="document" value="{{ old('document') }}" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_document') }}</div></div>';
            html += '<div class="form-group col-md-4"><button id="removeDocument" type="button" class="btn btn-danger btn-filter"><i class="fas fa-trash-alt"></i> {{ __('btn_remove') }}</button></div>';
            html += '</div>';

            $('#newDocument').append(html);
        });

        // remove Field
        $(document).on('click', '#removeDocument', function () {
            $(this).closest('#documentFormField').remove();
        });
    }(jQuery));
    </script>

    <script type="text/javascript">
    (function ($) {
        "use strict";
        // Show/hide Catholic sacraments section based on religion selection
        $('#religion').on('change', function() {
            var selectedReligionId = $(this).val();
            var selectedReligionText = $(this).find('option:selected').text().toLowerCase();
            
            // Check if "Catholic" or "Roman Catholic" is selected
            if (selectedReligionText.includes('catholic')) {
                $('#catholic-sacraments-container').slideDown();
            } else {
                $('#catholic-sacraments-container').slideUp();
                // Uncheck all sacrament checkboxes when hiding
                $('#is_catholic_baptised, #is_confirmed, #has_first_communion').prop('checked', false);
            }
        });

        // Trigger on page load if religion is already selected
        $(document).ready(function() {
            if ($('#religion').val()) {
                $('#religion').trigger('change');
            }
        });
    }(jQuery));
    </script>

    <script type="text/javascript">
    (function ($) {
        "use strict";
        
        const filterDistrictUrl = "{{ route('filter-district') }}";
        const districtCache = {};
        const documentInputSelector = '.document-input';
        const documentSummaryTableSelector = '#documentSummaryTable';
        const documentRequirementsData = <?php echo json_encode($documentRequirements ?? []); ?>;

        const populateDistrictSelect = (provinceId, $districtSelect, selectedValue) => {
            if (!provinceId || !$districtSelect || !$districtSelect.length) {
                return;
            }

            $districtSelect.empty().append($('<option/>', {
                value: '',
                text: '{{ __("select") }}'
            }));

            const cacheKey = `province_${provinceId}`;
            const cached = districtCache[cacheKey];

            const appendOptions = (items) => {
                $.each(items, function(i, item) {
                    const optionValue = item.id || item.value;
                    const optionLabel = item.title || item.name || item.label;

                    if (!optionValue) {
                        return;
                    }

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
            };

            if (Array.isArray(cached) && cached.length) {
                appendOptions(cached);
                return;
            }

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
                let items = [];

                if (Array.isArray(response)) {
                    items = response;
                } else if (response && Array.isArray(response.data)) {
                    items = response.data;
                }

                if (!items.length) {
                    console.warn('No districts returned for province', provinceId, response);
                    return;
                }

                districtCache[cacheKey] = items;
                appendOptions(items);
            }).fail(function(error) {
                console.warn('Unable to fetch districts', error);
            });
        };

        const initDistrictSelects = () => {
            const $presentProvince = $('#present_province');
            const $permanentProvince = $('#permanent_province');
            const $presentDistrict = $('#present_district');
            const $permanentDistrict = $('#permanent_district');

            if (!$presentProvince.length && !$permanentProvince.length) {
                return;
            }

            const oldPresentDistrict = <?php echo json_encode(old('present_district')); ?>;
            const oldPermanentDistrict = <?php echo json_encode(old('permanent_district')); ?>;

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

        // Guardian Repeater
        const guardianTemplate = () => {
            const index = $('#guardianRepeater .guardian-item').length;
            return `
                <div class="repeater-item guardian-item" data-index="${index}">
                    <div class="repeater-actions">
                        <button type="button" class="remove-guardian" aria-label="{{ __('Remove guardian') }}">&times;</button>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Full Name') }} <span>*</span></label>
                            <input type="text" class="form-control" name="guardians[${index}][full_name]" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ __('Relationship to Applicant') }}</label>
                            <input type="text" class="form-control" name="guardians[${index}][relationship]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>{{ __('Type') }} <span>*</span></label>
                            <select class="form-control" name="guardians[${index}][type]" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($guardianTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ __('Occupation') }}</label>
                            <input type="text" class="form-control" name="guardians[${index}][occupation]">
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ __('Email Address') }}</label>
                            <input type="email" class="form-control" name="guardians[${index}][email]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>{{ __('Primary Phone') }} <span>*</span></label>
                            <input type="text" class="form-control" name="guardians[${index}][phone_primary]" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ __('Alternate Phone') }}</label>
                            <input type="text" class="form-control" name="guardians[${index}][phone_secondary]">
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-center">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" name="guardians[${index}][is_primary]" id="guardian_primary_${index}">
                                <label class="form-check-label" for="guardian_primary_${index}">{{ __('Primary contact') }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Address Line 1') }}</label>
                            <input type="text" class="form-control" name="guardians[${index}][address_line1]">
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ __('Address Line 2') }}</label>
                            <input type="text" class="form-control" name="guardians[${index}][address_line2]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>{{ __('City / Town') }}</label>
                            <input type="text" class="form-control" name="guardians[${index}][city]">
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ __('State / Region') }}</label>
                            <input type="text" class="form-control" name="guardians[${index}][state]">
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ __('Country') }}</label>
                            <select class="form-control" name="guardians[${index}][country]">${countryOptionsHtml()}</select>
                        </div>
                    </div>
                </div>
            `;
        };

        const initGuardianRepeater = () => {
            const $container = $('#guardianRepeater');
            const $addButton = $('#addGuardian');

            if (!$container.length || !$addButton.length) {
                return;
            }

            $addButton.on('click', function() {
                $container.append(guardianTemplate());
            });

            $container.on('click', '.remove-guardian', function() {
                $(this).closest('.guardian-item').remove();
            });
        };

        // Academic History Repeater
@include('partials.country-options-js')
        const academicTemplate = () => {
            const index = $('#academicHistoryRepeater .academic-item').length;
            return `
                <div class="repeater-item academic-item" data-index="${index}">
                    <div class="repeater-actions">
                        <button type="button" class="remove-academic" aria-label="{{ __('Remove record') }}">&times;</button>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Certificate / Qualification') }} <span>*</span></label>
                            <input type="text" class="form-control" name="academic_history[${index}][certificate_obtained]" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ __('Awarding body') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][awarding_body]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('School / Institution attended') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][institution_name]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('City') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][city]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Country') }}</label>
                            <select class="form-control" name="academic_history[${index}][country]">${countryOptionsHtml()}</select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Language of Instruction') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][instruction_language]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Start year') }}</label>
                            <input type="number" class="form-control" min="1900" max="2200" name="academic_history[${index}][start_year]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Completion year') }}</label>
                            <input type="number" class="form-control" min="1900" max="2200" name="academic_history[${index}][end_year]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label>{{ __('GCE O-Level / Probatoire Subjects Passed') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][gce_ol_detail]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('GCE A-Level / Baccalaureate Subjects Passed') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][gce_al_detail]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Probatoire Stream (if applicable)') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][probatoire_detail]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Baccalaureate Option (if applicable)') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][baccalaureate_detail]">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Additional Notes') }}</label>
                        <textarea class="form-control" name="academic_history[${index}][notes]" rows="2"></textarea>
                    </div>
                </div>
            `;
        };

        const initAcademicRepeater = () => {
            const $container = $('#academicHistoryRepeater');
            const $addButton = $('#addAcademicHistory');

            if (!$container.length || !$addButton.length) {
                return;
            }

            $addButton.on('click', function() {
                $container.append(academicTemplate());
            });

            $container.on('click', '.remove-academic', function() {
                $(this).closest('.academic-item').remove();
            });
        };

        // Language Repeater
        const languageTemplate = () => {
            const index = $('#languageRepeater .language-item').length;
            return `
                <div class="repeater-item language-item" data-index="${index}">
                    <div class="repeater-actions">
                        <button type="button" class="remove-language" aria-label="{{ __('Remove language') }}">&times;</button>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Language') }}</label>
                            <input type="text" class="form-control" name="languages[${index}][language]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Years of Study / Use') }}</label>
                            <input type="number" min="0" class="form-control" name="languages[${index}][years_of_study]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Fluency Level') }}</label>
                            <select class="form-control" name="languages[${index}][fluency_level]">
                                <option value="">{{ __('select') }}</option>
                                @foreach($fluencyOptions as $option)
                                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            `;
        };

        const initLanguageRepeater = () => {
            const $container = $('#languageRepeater');
            const $addButton = $('#addLanguage');

            if (!$container.length || !$addButton.length) {
                return;
            }

            $addButton.on('click', function() {
                $container.append(languageTemplate());
            });

            $container.on('click', '.remove-language', function() {
                $(this).closest('.language-item').remove();
            });
        };

        // Document Status Update
        const updateDocumentStatus = (key, hasFile) => {
            const table = $(documentSummaryTableSelector);
            if (!table.length) {
                return;
            }

            const $row = table.find(`[data-document-row="${key}"]`);
            if (!$row.length) {
                return;
            }

            const requirement = documentRequirementsData && typeof documentRequirementsData === 'object'
                ? documentRequirementsData[key] || {}
                : {};
            const isRequired = Boolean(requirement.required);
            const $statusBadge = $row.find('.document-status');

            if (!$statusBadge.length) {
                return;
            }

            if (hasFile) {
                $statusBadge.removeClass('bg-warning text-dark bg-secondary').addClass('bg-success text-white').text("{{ __('Ready to submit') }}");
                $statusBadge.attr('data-status', 'ready');
                return;
            }

            if (isRequired) {
                $statusBadge.removeClass('bg-success text-white bg-secondary').addClass('bg-warning text-dark').text("{{ __('Awaiting Upload') }}");
                $statusBadge.attr('data-status', 'pending');
            } else {
                $statusBadge.removeClass('bg-success text-white bg-warning text-dark').addClass('bg-secondary text-white').text("{{ __('Optional') }}");
                $statusBadge.attr('data-status', 'optional');
            }
        };

        const initDocumentSummary = () => {
            const $inputs = $(documentInputSelector);
            if (!$inputs.length) {
                return;
            }

            $inputs.each(function() {
                const key = $(this).data('document-key');
                updateDocumentStatus(key, this.files && this.files.length > 0);
            });

            $inputs.on('change', function() {
                const key = $(this).data('document-key');
                updateDocumentStatus(key, this.files && this.files.length > 0);
            });
        };

        $(document).ready(function() {
            // initDistrictSelects();
            initGuardianRepeater();
            initAcademicRepeater();
            initLanguageRepeater();
            initDocumentSummary();
        });
        
    }(jQuery));
    </script>

@include('common.js.batch_filter')

@endsection