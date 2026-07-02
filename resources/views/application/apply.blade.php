<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ $applicationSetting->title ?? $title }}</title>
    @include('admin.layouts.common.header_script')
    <link rel="stylesheet" href="{{ asset('dashboard/css/pages/wizard.css') }}">
    <style>
        body {
            background: #f4f6f9;
        }
        .application-card {
            border: none;
            border-radius: 0.75rem;
        }
        .wizard-sec-bg {
            padding: 1.5rem;
        }
        .wizard > .steps .current a {
            background-color: #0c7cd5;
        }
        .wizard .content {
            min-height: auto;
        }
        .step-caption {
            color: #6c757d;
            margin-bottom: 1.25rem;
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
        }
        .document-help {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
    <!-- Institutional portal theme (loads last so it overrides the admin skin) -->
    <link rel="stylesheet" href="{{ asset('dashboard/css/application-portal.css') }}?v={{ filemtime(public_path('dashboard/css/application-portal.css')) }}">
</head>
<body>

@isset($applicationSetting)
<div class="main-body">
    <div class="page-wrapper">
        <div class="card application-card">
            <div class="card-block">
                <!-- Sign In Button (Top Right) -->
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-end mt-3 mb-2">
                            <a href="{{ route('application.login') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sign-in-alt"></i> {{ __('Already Applied? Sign In to Dashboard') }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Header with Logos and Title -->
                <div class="row mt-2 mb-4 align-items-center">
                    <div class="col-sm-2 text-center">
                        <div class="inner">
                            @if(is_file('uploads/application-setting/'.$applicationSetting->logo_left))
                                <img src="{{ asset('uploads/application-setting/'.$applicationSetting->logo_left) }}" class="img-fluid" alt="Left Logo">
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-8 text-center">
                        <h2 class="mb-2">{{ $applicationSetting->title }}</h2>
                        <p class="mb-0">{!! strip_tags($applicationSetting->body, '<br><b><i><strong><u><a><span><del>') !!}</p>
                    </div>
                    <div class="col-sm-2 text-center">
                        <div class="inner">
                            @if(is_file('uploads/application-setting/'.$applicationSetting->logo_right))
                                <img src="{{ asset('uploads/application-setting/'.$applicationSetting->logo_right) }}" class="img-fluid" alt="Right Logo">
                            @endif
                        </div>
                    </div>
                </div>

                @php $feeCfg = $settings ?? null; @endphp
                <div class="alert alert-info" role="alert">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <strong>{{ __('Important: Complete every section and have digital copies of required documents ready before you begin.') }}</strong>
                        @if($feeCfg && !empty($feeCfg['fee_enabled']))
                        <span class="mt-2 mt-md-0">{{ __('Application Fee:') }} {{ number_format($feeCfg['fee_amount']) }} {{ __('FCFA (non-refundable). Upload the payment receipt after submitting.') }}</span>
                        @endif
                    </div>
                </div>
                @if(isset($degreeType) && $degreeType)
                <div class="alert alert-light border" role="alert">
                    <span class="badge badge-pill badge-primary">{{ $degreeType->title }}</span>
                    @if(!empty($settings['intro_html']))
                        <span class="ms-2">{!! strip_tags($settings['intro_html'], '<br><b><i><strong><u><a><span>') !!}</span>
                    @endif
                </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show text-center" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>{{ __('Please review the highlighted fields below.') }}</strong>
                        <ul class="mb-0 mt-2" style="columns: 2;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="wizard-sec-bg">
                    @php
                        // Degree-type-aware field resolver: when an application's degree type is
                        // bound (applicant portal), the per-degree-type override decides the toggle;
                        // otherwise it falls back to the global Field setting.
                        function field($slug) {
                            $dt = app()->bound('applicant.degree_type') ? app('applicant.degree_type') : null;
                            if ($dt) {
                                return (object) ['status' => \App\Services\DegreeTypeFormConfig::fieldEnabled($dt, $slug) ? 1 : 0];
                            }
                            return \App\Models\Field::field($slug);
                        }

                        // Helper to get field value: old() input takes priority, then application draft data, then default
                        $getValue = function($field, $default = null) use ($application) {
                            return old($field, $application->$field ?? $default);
                        };

                        // For nested guardian data
                        $getGuardians = function() use ($application) {
                            $oldGuardians = old('guardians');
                            if ($oldGuardians !== null) {
                                return $oldGuardians;
                            }
                            // Load from saved guardians if available
                            $guardians = $application->guardians ?? collect();
                            if ($guardians->count() > 0) {
                                return $guardians->map(function($g) {
                                    return [
                                        'full_name' => $g->full_name,
                                        'type' => $g->type,
                                        'relationship' => $g->relationship,
                                        'occupation' => $g->occupation,
                                        'email' => $g->email,
                                        'phone_primary' => $g->phone_primary,
                                        'phone_secondary' => $g->phone_secondary,
                                        'address_line1' => $g->address_line1,
                                        'address_line2' => $g->address_line2,
                                        'city' => $g->city,
                                        'state' => $g->state,
                                        'country' => $g->country,
                                        'is_primary' => $g->is_primary,
                                    ];
                                })->toArray();
                            }
                            return [['type' => 'Parent', 'is_primary' => 1]];
                        };

                        // For nested academic history data
                        $getAcademicHistory = function() use ($application) {
                            $oldHistory = old('academic_history');
                            if ($oldHistory !== null) {
                                return $oldHistory;
                            }
                            $histories = $application->academicHistories ?? collect();
                            if ($histories->count() > 0) {
                                return $histories->map(function($h) {
                                    return [
                                        'institution_name' => $h->institution_name,
                                        'city' => $h->city,
                                        'country' => $h->country,
                                        'instruction_language' => $h->instruction_language,
                                        'date_from' => $h->date_from?->format('Y-m-d'),
                                        'date_to' => $h->date_to?->format('Y-m-d'),
                                        'certificate_obtained' => $h->certificate_obtained,
                                        'gce_ol_detail' => $h->gce_ol_detail,
                                        'gce_al_detail' => $h->gce_al_detail,
                                        'probatoire_detail' => $h->probatoire_detail,
                                        'baccalaureate_detail' => $h->baccalaureate_detail,
                                        'notes' => $h->notes,
                                    ];
                                })->toArray();
                            }
                            return [[]];
                        };

                        // For nested language data
                        $getLanguages = function() use ($application) {
                            $oldLangs = old('languages');
                            if ($oldLangs !== null) {
                                return $oldLangs;
                            }
                            $languages = $application->languages ?? collect();
                            if ($languages->count() > 0) {
                                return $languages->map(function($l) {
                                    return [
                                        'language' => $l->language,
                                        'years_of_study' => $l->years_of_study,
                                        'fluency_level' => $l->fluency_level,
                                    ];
                                })->toArray();
                            }
                            return [['language' => 'English', 'fluency_level' => 'excellent']];
                        };

                        $oldGuardians = $getGuardians();
                        $oldAcademicHistory = $getAcademicHistory();
                        $oldLanguages = $getLanguages();

                        // Program ID for pre-selection
                        $programId = old('program', $application->program_id);
                        $facultyId = old('faculty', optional($application->program)->faculty_id);
                    @endphp

                    <!-- Draft Progress & Auto-Save Status Bar -->
                    <div class="alert alert-light border mb-3" id="draft-status-bar">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="text-muted">{{ __('Draft Progress') }}:</span>
                                    <strong id="draft-progress-text">{{ $application->draft_progress ?? 0 }}%</strong>
                                    <div class="progress mt-1" style="width: 120px; height: 6px;">
                                        <div class="progress-bar bg-primary" role="progressbar" id="draft-progress-bar" 
                                             style="width: {{ $application->draft_progress ?? 0 }}%"></div>
                                    </div>
                                </div>
                                <div class="d-none d-md-block border-start ps-3 ms-2">
                                    <span class="text-muted">{{ __('Last Saved') }}:</span>
                                    <span id="last-saved-time">
                                        @if($application->draft_last_saved_at)
                                            {{ $application->draft_last_saved_at->format('M j, Y g:i A') }}
                                        @else
                                            {{ __('Not saved yet') }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 mt-md-0 d-flex align-items-center">
                                <span id="auto-save-status" class="me-2 text-muted small">
                                    <i class="fas fa-circle text-success"></i> {{ __('Auto-save enabled') }}
                                </span>
                                <button type="button" id="save-draft-btn" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-save me-1"></i> {{ __('Save Draft') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <form id="hnd-application-form" class="needs-validation" novalidate action="{{ route('application.update', $application) }}" method="post" enctype="multipart/form-data" style="display: none;">
                        @csrf

                        <h3>{{ __('Programme Selection') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('These were chosen when you started this application and cannot be changed here. To apply for a different programme or intake, start a new application from My Account.') }}</p>
                            @php
                                $facultyTitle = optional($faculties->firstWhere('id', optional($application->program)->faculty_id))->title;
                                $secondProgTitle = $application->second_program_choice_id ? optional($programs->firstWhere('id', $application->second_program_choice_id))->title : null;
                                $thirdProgTitle = $application->third_program_choice_id ? optional($programs->firstWhere('id', $application->third_program_choice_id))->title : null;
                            @endphp
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Programme & Intake') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label>{{ __('Degree Type') }}</label>
                                        <input type="text" class="form-control is-mirrored" value="{{ optional($degreeType)->title }}" readonly>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>{{ __('field_faculty') }}</label>
                                        <input type="text" class="form-control is-mirrored" value="{{ $facultyTitle }}" readonly>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>{{ __('First Choice Programme') }}</label>
                                        <input type="text" class="form-control is-mirrored" value="{{ optional($application->program)->title }}" readonly>
                                    </div>

                                    @if(optional(field('application_program_choice_second'))->status == 1)
                                        <div class="form-group col-md-6">
                                            <label>{{ __('Second Choice Programme') }}</label>
                                            <input type="text" class="form-control is-mirrored" value="{{ $secondProgTitle ?? '—' }}" readonly>
                                        </div>
                                    @endif

                                    @if(optional(field('application_program_choice_third'))->status == 1)
                                        <div class="form-group col-md-6">
                                            <label>{{ __('Third Choice Programme') }}</label>
                                            <input type="text" class="form-control is-mirrored" value="{{ $thirdProgTitle ?? '—' }}" readonly>
                                        </div>
                                    @endif

                                    @if(optional(field('application_academic_year'))->status == 1)
                                        <div class="form-group col-md-6">
                                            <label>{{ __('Academic Year Applied For') }}</label>
                                            <input type="text" class="form-control is-mirrored" value="{{ $application->academic_year }}" readonly>
                                        </div>
                                    @endif
                                </div>

                                {{-- Hidden inputs carry the intake choices so the submission stays valid --}}
                                <input type="hidden" name="program" value="{{ $application->program_id }}">
                                @if(optional(field('application_program_choice_second'))->status == 1)
                                    <input type="hidden" name="second_program_choice_id" value="{{ $application->second_program_choice_id }}">
                                @endif
                                @if(optional(field('application_program_choice_third'))->status == 1)
                                    <input type="hidden" name="third_program_choice_id" value="{{ $application->third_program_choice_id }}">
                                @endif
                                @if(optional(field('application_academic_year'))->status == 1)
                                    <input type="hidden" name="academic_year" value="{{ $application->academic_year }}">
                                @endif
                            </fieldset>
                        </section>

                        <h3>{{ __('Personal Information') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Provide your legal personal information exactly as it appears on official identification documents.') }}</p>
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Names & Identification') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="first_name">{{ __('field_first_name') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="first_name" id="first_name" value="{{ $getValue('first_name') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter your first name.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="last_name">{{ __('field_last_name') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="last_name" id="last_name" value="{{ $getValue('last_name') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter your last name.') }}</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label class="d-block">{{ __('field_gender') }} <span>*</span></label>
                                        <div class="radio-pills">
                                            <label class="radio-pill">
                                                <input type="radio" name="gender" value="1" {{ $getValue('gender') == 1 ? 'checked' : '' }} required>
                                                <span>{{ __('gender_male') }}</span>
                                            </label>
                                            <label class="radio-pill">
                                                <input type="radio" name="gender" value="2" {{ $getValue('gender') == 2 ? 'checked' : '' }} required>
                                                <span>{{ __('gender_female') }}</span>
                                            </label>
                                        </div>
                                        <div class="invalid-feedback">{{ __('Select your gender.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="dob">{{ __('field_dob') }} <span>*</span></label>
                                        <input type="date" class="form-control" name="dob" id="dob" value="{{ $getValue('dob') ? (is_string($getValue('dob')) ? $getValue('dob') : $getValue('dob')->format('Y-m-d')) : '' }}" required>
                                        <div class="invalid-feedback">{{ __('Specify your date of birth.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="nationality">{{ __('field_nationality') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="nationality" id="nationality" value="{{ $getValue('nationality') }}" required>
                                        <div class="invalid-feedback">{{ __('Provide your nationality.') }}</div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Birth & Religious Details') }}</legend>
                                <div class="row">
                                    @if(optional(field('application_birth_city'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_city">{{ __('City / Town of Birth') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_city" id="birth_city" value="{{ $getValue('birth_city') }}" required>
                                        </div>
                                    @endif
                                    @if(optional(field('application_birth_division'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_division">{{ __('Division') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_division" id="birth_division" value="{{ $getValue('birth_division') }}" required>
                                        </div>
                                    @endif
                                    @if(optional(field('application_birth_region'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_region">{{ __('Region') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_region" id="birth_region" value="{{ $getValue('birth_region') }}" required>
                                        </div>
                                    @endif
                                    @if(optional(field('application_birth_country'))->status == 1)
                                        <div class="form-group col-md-3">
                                            <label for="birth_country">{{ __('Country of Birth') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="birth_country" id="birth_country" value="{{ $getValue('birth_country') }}" required>
                                        </div>
                                    @endif
                                </div>
                                <div class="row">
                                    @if(optional(field('application_religion'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="religion">{{ __('field_religion') }} <span>*</span></label>
                                            @php
                                                $currentReligion = $getValue('religion');
                                                $isOther = $currentReligion && !$religions->contains('id', $currentReligion);
                                            @endphp
                                            <select class="form-control" name="religion" id="religion" required>
                                                <option value="">{{ __('select') }}</option>
                                                @foreach($religions as $religion)
                                                <option value="{{ $religion->id }}" {{ $currentReligion == $religion->id ? 'selected' : '' }}>{{ $religion->title }}</option>
                                                @endforeach
                                                <option value="other" {{ $isOther ? 'selected' : '' }}>{{ __('Others') }}</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-4" id="religion_other_container" style="display: none;">
                                            <label for="religion_other">{{ __('Specify Religion') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="religion_other" id="religion_other" value="{{ $isOther ? $currentReligion : old('religion_other') }}">
                                        </div>
                                    @endif
                                    @if(optional(field('application_catholic_baptised'))->status == 1)
                                        <div class="form-group col-md-8" id="catholic-sacraments-container" style="display: none;">
                                            <label class="d-block mb-2">{{ __('Catholic Sacraments') }}</label>
                                            <div class="row">
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="is_catholic_baptised" name="is_catholic_baptised" {{ $getValue('is_catholic_baptised') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="is_catholic_baptised">
                                                            {{ __('I am a baptised Catholic with proof of baptism') }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="is_confirmed" name="is_confirmed" {{ $getValue('is_confirmed') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="is_confirmed">
                                                            {{ __('I have received Confirmation') }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="has_first_communion" name="has_first_communion" {{ $getValue('has_first_communion') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="has_first_communion">
                                                            {{ __('I have received First Holy Communion') }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @if(optional(field('application_mother_tongue'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="mother_tongue">{{ __('field_mother_tongue') }}</label>
                                            <input type="text" class="form-control" name="mother_tongue" id="mother_tongue" value="{{ $getValue('mother_tongue') }}">
                                        </div>
                                    @endif
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Official Identification') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="national_id">{{ __('National Identity Card Number') }}</label>
                                        <input type="text" class="form-control" name="national_id" id="national_id" value="{{ $getValue('national_id') }}">
                                    </div>
                                    @if(optional(field('application_national_id_issue_date'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="national_id_issue_date">{{ __('National ID Issue Date') }}</label>
                                            <input type="date" class="form-control" name="national_id_issue_date" id="national_id_issue_date" value="{{ $getValue('national_id_issue_date') ? (is_string($getValue('national_id_issue_date')) ? $getValue('national_id_issue_date') : $getValue('national_id_issue_date')->format('Y-m-d')) : '' }}">
                                        </div>
                                    @endif
                                    @if(optional(field('application_national_id_issue_place'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="national_id_issue_place">{{ __('National ID Issue Place') }}</label>
                                            <input type="text" class="form-control" name="national_id_issue_place" id="national_id_issue_place" value="{{ $getValue('national_id_issue_place') }}">
                                        </div>
                                    @endif
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="passport_no">{{ __('Passport Number (if Applicable)') }}</label>
                                        <input type="text" class="form-control" name="passport_no" id="passport_no" value="{{ $getValue('passport_no') }}">
                                    </div>
                                    @if(optional(field('application_passport_issue_date'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="passport_issue_date">{{ __('Passport Issue Date') }}</label>
                                            <input type="date" class="form-control" name="passport_issue_date" id="passport_issue_date" value="{{ $getValue('passport_issue_date') ? (is_string($getValue('passport_issue_date')) ? $getValue('passport_issue_date') : $getValue('passport_issue_date')->format('Y-m-d')) : '' }}">
                                        </div>
                                    @endif
                                    @if(optional(field('application_passport_issue_country'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="passport_issue_country">{{ __('Passport Issue Country') }}</label>
                                            <input type="text" class="form-control" name="passport_issue_country" id="passport_issue_country" value="{{ $getValue('passport_issue_country') }}">
                                        </div>
                                    @endif
                                </div>
                            </fieldset>
                        </section>

                        <h3>{{ __('Address & Contact') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Provide current and permanent address information so we can reach you physically or through mail.') }}</p>
                            <fieldset class="scheduler-border">
                                <legend>{{ __('Current Residence') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="country">{{ __('field_country') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="country" id="country" value="{{ $getValue('country', 'Cameroon') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter the country where you currently reside.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="present_province">{{ __('field_province') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="present_province" id="present_province" value="{{ $getValue('present_province') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter your current province.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="present_district">{{ __('field_district') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="present_district" id="present_district" value="{{ $getValue('present_district') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter the district of your current residence.') }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="present_village">{{ __('Village / Quarter') }}</label>
                                        <input type="text" class="form-control" name="present_village" id="present_village" value="{{ $getValue('present_village') }}">
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label for="present_address">{{ __('House / Street Address') }}</label>
                                        <input type="text" class="form-control" name="present_address" id="present_address" value="{{ $getValue('present_address') }}">
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Permanent Address & Postal Details') }}</legend>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="same_as_residence">
                                    <label class="form-check-label" for="same_as_residence">{{ __('Same as Current Residence') }}</label>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="permanent_province">{{ __('field_province') }}</label>
                                        <input type="text" class="form-control" name="permanent_province" id="permanent_province" value="{{ $getValue('permanent_province') }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="permanent_district">{{ __('field_district') }}</label>
                                        <input type="text" class="form-control" name="permanent_district" id="permanent_district" value="{{ $getValue('permanent_district') }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="permanent_village">{{ __('Village / Quarter') }}</label>
                                        <input type="text" class="form-control" name="permanent_village" id="permanent_village" value="{{ $getValue('permanent_village') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="permanent_address">{{ __('House / Street Address') }}</label>
                                        <input type="text" class="form-control" name="permanent_address" id="permanent_address" value="{{ $getValue('permanent_address') }}">
                                    </div>
                                    @if(optional(field('application_postal_address'))->status == 1)
                                        <div class="form-group col-md-6">
                                            <label for="postal_address_line1">{{ __('Postal Address / P.O. Box') }}</label>
                                            <input type="text" class="form-control" name="postal_address_line1" id="postal_address_line1" value="{{ $getValue('postal_address_line1') }}">
                                            <input type="text" class="form-control mt-2" name="postal_address_line2" id="postal_address_line2" value="{{ $getValue('postal_address_line2') }}" placeholder="{{ __('Additional postal details (optional)') }}">
                                        </div>
                                    @endif
                                </div>
                            </fieldset>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Contact Channels') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="phone">{{ __('Primary Phone Number') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="phone" id="phone" value="{{ $getValue('phone') }}" placeholder="+237 6XX XXX XXX" required>
                                        <small class="form-text text-muted">{{ __('Include the country code, e.g. +237.') }}</small>
                                        <div class="invalid-feedback">{{ __('Provide a reachable phone number.') }}</div>
                                    </div>
                                    @if(optional(field('application_alternate_phone'))->status == 1)
                                        <div class="form-group col-md-4">
                                            <label for="alternate_phone">{{ __('Alternate Phone Number') }}</label>
                                            <input type="text" class="form-control" name="alternate_phone" id="alternate_phone" value="{{ $getValue('alternate_phone') }}">
                                        </div>
                                    @endif
                                    <div class="form-group col-md-4">
                                        <label for="email">{{ __('field_email') }} <span>*</span></label>
                                        <input type="email" class="form-control" name="email" id="email" value="{{ $getValue('email') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter a valid email address.') }}</div>
                                    </div>
                                </div>

                                <div class="row">
                                    @if(optional(field('application_studied_in_english'))->status == 1)
                                        <div class="form-group col-md-6">
                                            <label>{{ __('Did you study in English?') }}</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="1" id="studied_in_english" name="studied_in_english" {{ $getValue('studied_in_english') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="studied_in_english">{{ __('Tick if the language of instruction in secondary/high school was English.') }}</label>
                                            </div>
                                        </div>
                                    @endif
                                    @if(optional(field('application_instruction_language_secondary'))->status == 1)
                                        <div class="form-group col-md-6">
                                            <label for="instruction_language_secondary">{{ __('If not, specify the language of instruction') }}</label>
                                            <input type="text" class="form-control" name="instruction_language_secondary" id="instruction_language_secondary" value="{{ $getValue('instruction_language_secondary') }}">
                                        </div>
                                    @endif
                                </div>
                            </fieldset>
                        </section>

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
                                                    <input type="text" class="form-control" name="guardians[{{ $index }}][country]" value="{{ $guardian['country'] ?? '' }}">
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addGuardian">{{ __('Add another guardian / sponsor') }}</button>
                            </section>
                        @endif

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
                                                    <label>{{ __('Institution Name') }} <span>*</span></label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][institution_name]" value="{{ $history['institution_name'] ?? '' }}" required>
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Country') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][country]" value="{{ $history['country'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('City') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][city]" value="{{ $history['city'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Language of Instruction') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][instruction_language]" value="{{ $history['instruction_language'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('From (Year)') }}</label>
                                                    <input type="date" class="form-control" name="academic_history[{{ $index }}][date_from]" value="{{ $history['date_from'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('To (Year)') }}</label>
                                                    <input type="date" class="form-control" name="academic_history[{{ $index }}][date_to]" value="{{ $history['date_to'] ?? '' }}">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>{{ __('Certificate Obtained') }}</label>
                                                    <input type="text" class="form-control" name="academic_history[{{ $index }}][certificate_obtained]" value="{{ $history['certificate_obtained'] ?? '' }}">
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

                        <h3>{{ __('Documents & Declaration') }}</h3>
                        <section class="form-step">
                            <p class="step-caption">{{ __('Upload the required documents, review your declaration, and set up your applicant portal access.') }}</p>

                            <fieldset class="scheduler-border">
                                <legend>{{ __('Passport Photo & Signature') }}</legend>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="photo">{{ __('Recent Passport Photograph (max 5MB)') }} <span>*</span></label>
                                        <input type="file" class="form-control size-guard" data-max-size-mb="5" name="photo" id="photo" accept="image/jpeg,image/png,image/*" required>
                                        <div class="invalid-feedback">{{ __('Upload a recent passport style photograph.') }}</div>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="signature">{{ __('Signature Sample (max 2MB)') }}</label>
                                        <input type="file" class="form-control size-guard" data-max-size-mb="2" name="signature" id="signature" accept="image/jpeg,image/png,image/*">
                                    </div>
                                </div>
                            </fieldset>

                            @if(optional(field('application_document_checklist'))->status == 1)
                                <fieldset class="scheduler-border">
                                    <legend>{{ __('Document Checklist') }}</legend>
                                    <p class="document-help">{{ __('Upload clear scans or photos. Accepted formats: JPG, PNG, PDF. Maximum size per file: 10MB.') }}</p>
                                    @php
                                        // Get already uploaded documents for this application
                                        $uploadedDocs = $application->documents ? $application->documents->keyBy('document_type') : collect();
                                    @endphp
                                    <div class="row">
                                        @foreach($documentRequirements as $key => $document)
                                            @php
                                                $existingDoc = $uploadedDocs->get($key);
                                                $hasExistingFile = $existingDoc && $existingDoc->file_path;
                                            @endphp
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="document_{{ $key }}" class="form-label">{{ $document['label'] }} @if($document['required'] && !$hasExistingFile)<span>*</span>@endif</label>
                                                    @if($hasExistingFile)
                                                        <div class="alert alert-success py-2 px-3 mb-2">
                                                            <i class="fas fa-check-circle me-1"></i> {{ __('Document uploaded') }}
                                                            <a href="{{ asset('uploads/student/'.$existingDoc->file_path) }}" target="_blank" class="ms-2 btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> {{ __('View') }}
                                                            </a>
                                                            <small class="d-block text-muted mt-1">{{ __('Upload a new file below to replace') }}</small>
                                                        </div>
                                                    @endif
                                                    <input type="file" class="form-control document-input" data-document-key="{{ $key }}" data-has-existing="{{ $hasExistingFile ? '1' : '0' }}" data-max-size-mb="10" name="documents[{{ $key }}][file]" id="document_{{ $key }}" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" @if($document['required'] && !$hasExistingFile) required @endif>
                                                    @if(!empty($document['description']))
                                                        <small class="document-help">{{ $document['description'] }}</small>
                                                    @endif
                                                    <textarea class="form-control mt-2" name="documents[{{ $key }}][note]" rows="1" placeholder="{{ __('Notes (optional)') }}">{{ old('documents.'.$key.'.note', $existingDoc->notes ?? '') }}</textarea>
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
                                                            @php
                                                                $existingDoc = $uploadedDocs->get($key);
                                                                $hasExistingFile = $existingDoc && $existingDoc->file_path;
                                                            @endphp
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
                                                                    @if($hasExistingFile)
                                                                        <span class="document-status badge bg-success text-white" data-status="uploaded">{{ __('Uploaded') }}</span>
                                                                    @elseif(!$document['required'])
                                                                        <span class="document-status badge bg-secondary text-white" data-status="optional">{{ __('Optional') }}</span>
                                                                    @else
                                                                        <span class="document-status badge bg-warning text-dark" data-status="pending">{{ __('Awaiting Upload') }}</span>
                                                                    @endif
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



                            @if(optional(field('application_declaration'))->status == 1)
                                <fieldset class="scheduler-border">
                                    <legend>{{ __('Declaration') }}</legend>
                                    <p>{{ __('I certify that the information provided in this application is true and complete. I understand that withholding or misrepresenting information may result in the cancellation of admission.') }}</p>
                                    <div class="row">
                                        <div class="form-group col-md-6">
                                            <label for="declaration_name">{{ __('Full Name of Applicant') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="declaration_name" id="declaration_name" value="{{ $getValue('declaration_name', $getValue('first_name').' '.$getValue('last_name')) }}" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="declaration_signed_date">{{ __('Date') }} <span>*</span></label>
                                            <input type="date" class="form-control" name="declaration_signed_date" id="declaration_signed_date" value="{{ $getValue('declaration_signed_date') ? (is_string($getValue('declaration_signed_date')) ? $getValue('declaration_signed_date') : $getValue('declaration_signed_date')->format('Y-m-d')) : now()->toDateString() }}" required>
                                        </div>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" value="1" id="agree_terms" name="agree_terms" {{ old('agree_terms') ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="agree_terms">{{ __('I agree that the information provided is accurate and I accept the institute’s admission policies.') }}</label>
                                        <div class="invalid-feedback">{{ __('You must accept the declaration to submit your application.') }}</div>
                                    </div>
                                </fieldset>
                            @endif
                            <div class="mt-4 text-end">
                                <button type="button" class="btn btn-outline-secondary me-2" id="saveDraftFinalBtn"><i class="fas fa-save me-1"></i> {{ __('Save Draft') }}</button>
                                <button type="button" class="btn btn-primary d-none" id="wizardSubmitButton">{{ __('Submit Application') }}</button>
                            </div>
                        </section>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endisset

<div class="d-none" id="ps-placeholder">
    <div class="main-friend-cont"></div>
    <div class="main-chat-cont"></div>
    <div class="navbar-content"></div>
</div>

@include('admin.layouts.common.footer_script')
<script src="{{ asset('dashboard/plugins/jquery-validation/js/jquery.validate.min.js') }}"></script>
<script src="{{ asset('dashboard/js/pages/jquery.steps.js') }}"></script>
<script>
    "use strict";

    (function($) {
        const formSelector = "#hnd-application-form";
        const guardianRepeaterSelector = "#guardianRepeater";
        const academicRepeaterSelector = "#academicHistoryRepeater";
        const languageRepeaterSelector = "#languageRepeater";
        const filterDistrictUrl = "{{ route('filter-district') }}";
        const finishButtonSelector = "#wizardSubmitButton";
        const districtCache = {};
    const districtData = <?php echo json_encode($districtOptions ?? []); ?>;
        const documentRequirementsData = <?php echo json_encode($documentRequirements ?? []); ?>;
        const presentDistrictSelect = document.getElementById('present_district');
        const permanentDistrictSelect = document.getElementById('permanent_district');
        if (presentDistrictSelect) {
            presentDistrictSelect.setAttribute('data-districts', JSON.stringify(districtData));
        }
        if (permanentDistrictSelect) {
            permanentDistrictSelect.setAttribute('data-districts', JSON.stringify(districtData));
        }
        const districtMap = Array.isArray(districtData)
            ? districtData.reduce(function(accumulator, item) {
                if (!item || typeof item !== 'object') {
                    return accumulator;
                }

                const key = String(item.province_id || item.provinceId || item.province || '');
                if (!key) {
                    return accumulator;
                }

                if (!Array.isArray(accumulator[key])) {
                    accumulator[key] = [];
                }

                accumulator[key].push(item);
                return accumulator;
            }, {})
            : {};

        const getCachedDistricts = (provinceId) => {
            if (!provinceId) {
                return [];
            }

            const key = String(provinceId);

            if (Array.isArray(districtCache[key])) {
                return districtCache[key];
            }

            if (Array.isArray(districtMap[key])) {
                districtCache[key] = districtMap[key];
                return districtCache[key];
            }

            return [];
        };
    const selectPlaceholder = <?php echo json_encode(__('select')); ?>;
    const documentInputSelector = '.document-input';
    const documentSummaryTableSelector = '#documentSummaryTable';

        const initWizard = () => {
            const $form = $(formSelector);

            if (!$form.length) {
                return;
            }

            $form.show();

            if ($form.data('steps-initialized')) {
                return;
            }

            if (typeof $.fn.steps !== 'function') {
                console.warn('jQuery Steps plugin is not available.');
                return;
            }

            if (typeof $.fn.validate !== 'function') {
                console.warn('jQuery Validate plugin is not available.');
                return;
            }

            const validator = $form.validate({
                errorPlacement: function(error, element) {
                    if (element.hasClass('form-check-input')) {
                        error.appendTo(element.closest('.form-check'));
                        return;
                    }
                    error.insertAfter(element);
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
                if (!$finishButton.length) {
                    return;
                }

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
                    finish: "{{ __('Submit Application') }}",
                    next: "{{ __('btn_next') }}",
                    previous: "{{ __('btn_previous') }}"
                },
                onInit: function(event, currentIndex) {
                    toggleFinishButton(currentIndex);
                },
                onStepChanged: function(event, currentIndex) {
                    toggleFinishButton(currentIndex);
                },
                onStepChanging: function(event, currentIndex, newIndex) {
                    if (currentIndex > newIndex) {
                        return true;
                    }
                    validator.settings.ignore = ":hidden,:disabled";
                    return $form.valid();
                },
                onFinishing: function() {
                    validator.settings.ignore = ":disabled";
                    return $form.valid();
                },
                onFinished: function() {
                    $form.trigger('submit');
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

        const guardianTemplate = () => {
            const index = $(guardianRepeaterSelector + ' .guardian-item').length;
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
                            <input type="text" class="form-control" name="guardians[${index}][country]">
                        </div>
                    </div>
                </div>
            `;
        };

        const initGuardianRepeater = () => {
            const $container = $(guardianRepeaterSelector);
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

        const academicTemplate = () => {
            const index = $(academicRepeaterSelector + ' .academic-item').length;
            return `
                <div class="repeater-item academic-item" data-index="${index}">
                    <div class="repeater-actions">
                        <button type="button" class="remove-academic" aria-label="{{ __('Remove record') }}">&times;</button>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Institution Name') }} <span>*</span></label>
                            <input type="text" class="form-control" name="academic_history[${index}][institution_name]" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Country') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][country]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('City') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][city]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label>{{ __('Language of Instruction') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][instruction_language]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('From (Year)') }}</label>
                            <input type="date" class="form-control" name="academic_history[${index}][date_from]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('To (Year)') }}</label>
                            <input type="date" class="form-control" name="academic_history[${index}][date_to]">
                        </div>
                        <div class="form-group col-md-3">
                            <label>{{ __('Certificate Obtained') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][certificate_obtained]">
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
            const $container = $(academicRepeaterSelector);
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

        const languageTemplate = () => {
            const index = $(languageRepeaterSelector + ' .language-item').length;
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
            const $container = $(languageRepeaterSelector);
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

        const updateDocumentStatus = (key, hasFile, hasExisting = false, hasNewFile = false) => {
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

            if (hasNewFile) {
                // New file selected - ready to submit (will replace existing if any)
                $statusBadge.removeClass('bg-warning text-dark bg-secondary bg-success').addClass('bg-info text-white').text("{{ __('New file ready') }}");
                $statusBadge.attr('data-status', 'ready');
                return;
            }

            if (hasExisting) {
                // Existing file already uploaded
                $statusBadge.removeClass('bg-warning text-dark bg-secondary bg-info').addClass('bg-success text-white').text("{{ __('Uploaded') }}");
                $statusBadge.attr('data-status', 'uploaded');
                return;
            }

            if (hasFile) {
                // File selected (fallback)
                $statusBadge.removeClass('bg-warning text-dark bg-secondary').addClass('bg-success text-white').text("{{ __('Ready to submit') }}");
                $statusBadge.attr('data-status', 'ready');
                return;
            }

            if (isRequired) {
                $statusBadge.removeClass('bg-success text-white bg-secondary bg-info').addClass('bg-warning text-dark').text("{{ __('Awaiting Upload') }}");
                $statusBadge.attr('data-status', 'pending');
            } else {
                $statusBadge.removeClass('bg-success text-white bg-warning text-dark bg-info').addClass('bg-secondary text-white').text("{{ __('Optional') }}");
                $statusBadge.attr('data-status', 'optional');
            }
        };

        const populateDistrictSelect = (provinceId, $districtSelect, selectedValue) => {
            if (!$districtSelect.length) {
                return;
            }

            $districtSelect.empty();
            $districtSelect.append($('<option/>', { value: '', text: selectPlaceholder }));

            if (!provinceId) {
                return;
            }

            const cacheKey = String(provinceId);
            const cached = getCachedDistricts(cacheKey);
            const appendOptions = (items) => {
                items.forEach(function(item) {
                    if (!item || typeof item !== 'object') {
                        return;
                    }

                    const optionValue = item.id ?? item.value ?? null;
                    const optionLabel = item.title ?? item.name ?? '';

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

            const oldPresentDistrict = <?php echo json_encode($getValue('present_district')); ?>;
            const oldPermanentDistrict = <?php echo json_encode($getValue('permanent_district')); ?>;

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

        const initDocumentSummary = () => {
            const $inputs = $(documentInputSelector);
            if (!$inputs.length) {
                return;
            }

            $inputs.each(function() {
                const key = $(this).data('document-key');
                const hasExisting = $(this).data('has-existing') === 1 || $(this).data('has-existing') === '1';
                const hasNewFile = this.files && this.files.length > 0;
                // If has existing file or new file selected, it's considered "has file"
                updateDocumentStatus(key, hasExisting || hasNewFile, hasExisting);
            });

            $inputs.on('change', function() {
                const key = $(this).data('document-key');
                const hasExisting = $(this).data('has-existing') === 1 || $(this).data('has-existing') === '1';
                const maxMb = parseFloat($(this).data('max-size-mb')) || 10;
                const file = this.files && this.files[0];
                if (file && file.size > maxMb * 1024 * 1024) {
                    alert('"' + file.name + '" is ' + (file.size / 1024 / 1024).toFixed(1) + ' MB. Maximum allowed is ' + maxMb + ' MB.');
                    this.value = '';
                    updateDocumentStatus(key, hasExisting, hasExisting, false);
                    return;
                }
                const hasNewFile = this.files && this.files.length > 0;
                updateDocumentStatus(key, hasExisting || hasNewFile, hasExisting, hasNewFile);
            });
        };

        // Copy Current Residence into Permanent Address when "Same as Current Residence" is ticked.
        function initSameAsResidence() {
            var $toggle = $('#same_as_residence');
            if (!$toggle.length) return;

            var pairs = [
                ['present_province', 'permanent_province'],
                ['present_district', 'permanent_district'],
                ['present_village', 'permanent_village'],
                ['present_address', 'permanent_address'],
            ];

            function sync() {
                var on = $toggle.is(':checked');
                pairs.forEach(function (p) {
                    var $src = $('#' + p[0]);
                    var $dst = $('#' + p[1]);
                    if (!$dst.length) return;
                    if (on) {
                        $dst.val($src.val()).prop('readonly', true).addClass('is-mirrored');
                    } else {
                        $dst.prop('readonly', false).removeClass('is-mirrored');
                    }
                });
            }

            $toggle.on('change', sync);
            // Keep permanent fields in step with current residence while the box stays ticked.
            pairs.forEach(function (p) {
                $('#' + p[0]).on('input', function () {
                    if ($toggle.is(':checked')) {
                        $('#' + p[1]).val($(this).val());
                    }
                });
            });
        }

        $(function() {
            initWizard();
            initGuardianRepeater();
            initAcademicRepeater();
            initLanguageRepeater();
            // initDistrictSelects();
            initDocumentSummary();
            initSameAsResidence();
            // Client-side size guard for standalone file inputs (photo, signature).
            $('.size-guard').on('change', function () {
                const maxMb = parseFloat($(this).data('max-size-mb')) || 5;
                const file = this.files && this.files[0];
                if (file && file.size > maxMb * 1024 * 1024) {
                    alert('"' + file.name + '" is ' + (file.size / 1024 / 1024).toFixed(1) + ' MB. Maximum allowed is ' + maxMb + ' MB.');
                    this.value = '';
                }
            });
        });
    })(jQuery);
</script>

<!-- Faculty filter for program dropdown -->
<script type="text/javascript">
    "use strict";
    $(document).ready(function(){
        $(".faculty").on('change',function(e){
            e.preventDefault(e);
            var $faculty = $(this);
            
            // Get all program dropdowns and their selected values BEFORE clearing
            var programDropdowns = {
                'program': { element: $("#program"), selected: $("#program").data('selected') || $("#program").val() },
                'second_program_choice_id': { element: $("#second_program_choice_id"), selected: $("#second_program_choice_id").data('selected') || $("#second_program_choice_id").val() },
                'third_program_choice_id': { element: $("#third_program_choice_id"), selected: $("#third_program_choice_id").data('selected') || $("#third_program_choice_id").val() }
            };
            
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                type:'POST',
                url: "{{ route('filter-program') }}",
                data:{
                    _token:$('input[name=_token]').val(),
                    faculty:$(this).val()
                },
                success:function(response){
                    // Repopulate each program dropdown separately
                    $.each(programDropdowns, function(id, data){
                        if(!data.element.length) return; // Skip if element doesn't exist
                        
                        var $dropdown = data.element;
                        var selectedValue = data.selected;
                        
                        $('option', $dropdown).remove();
                        $dropdown.append('<option value="">{{ __("select") }}</option>');
                        $.each(response, function(){
                            $('<option/>', {
                                'value': this.id,
                                'text': this.title
                            }).appendTo($dropdown);
                        });
                        
                        // Restore each dropdown's own selected value
                        if(selectedValue){
                            $dropdown.val(selectedValue);
                            $dropdown.data('selected', ''); // Clear data-selected after using
                        }
                    });
                }
            });
        });

        // Trigger faculty change if there's an old value (for form repopulation)
        if($(".faculty").length && $(".faculty").data('selected')){
            $(".faculty").trigger('change');
        }
    });
</script>

<!-- Catholic Sacraments Show/Hide based on Religion -->
<script type="text/javascript">
    "use strict";
    $(document).ready(function(){
        // Religion data from server
        var religions = {!! $religions_json !!};
        var isInitialLoad = true;
        
        // Function to show/hide Catholic sacraments AND Other Religion input
        function toggleReligionFields() {
            var selectedReligionId = $('#religion').val();
            var catholicContainer = $('#catholic-sacraments-container');
            var otherReligionContainer = $('#religion_other_container');
            var otherReligionInput = $('#religion_other');

            // Handle "Others"
            if (selectedReligionId === 'other') {
                otherReligionContainer.slideDown(300);
                otherReligionInput.prop('required', true);
                
                // Hide catholic stuff if "other" is selected
                catholicContainer.slideUp(300);
                 if (!isInitialLoad) {
                    $('#is_catholic_baptised, #is_confirmed, #has_first_communion').prop('checked', false);
                }
            } else {
                otherReligionContainer.slideUp(300);
                otherReligionInput.prop('required', false);

                // Handle Catholic Logic
                if (selectedReligionId && religions[selectedReligionId]) {
                    var selectedReligion = religions[selectedReligionId];
                    
                    if (selectedReligion.is_catholic == 1 || selectedReligion.is_catholic === true) {
                        catholicContainer.slideDown(300);
                    } else {
                        catholicContainer.slideUp(300);
                        if (!isInitialLoad) {
                            $('#is_catholic_baptised, #is_confirmed, #has_first_communion').prop('checked', false);
                        }
                    }
                } else {
                    catholicContainer.slideUp(300);
                    if (!isInitialLoad) {
                        $('#is_catholic_baptised, #is_confirmed, #has_first_communion').prop('checked', false);
                    }
                }
            }
        }
        
        // Listen for religion change
        $('#religion').on('change', function() {
            isInitialLoad = false;
            toggleReligionFields();
        });
        
        // Check on page load (for form repopulation with old values)
        toggleReligionFields();
        isInitialLoad = false;
    });
</script>

<!-- Save Draft & Auto-Save Functionality -->
<script type="text/javascript">
    "use strict";
    (function($) {
        const saveDraftUrl = "{{ route('application.save-draft', $application) }}";
        const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name=_token]').val();
        const autoSaveInterval = 60000; // 60 seconds
        let autoSaveTimer = null;
        let formChanged = false;
        let isSaving = false;
        let lastSavedData = null;

        // Track form changes
        const trackFormChanges = () => {
            $('#hnd-application-form').on('change input', 'input, select, textarea', function() {
                formChanged = true;
                updateAutoSaveStatus('unsaved');
            });
        };

        // Get form data as FormData object
        const getFormData = () => {
            const $form = $('#hnd-application-form');
            return new FormData($form[0]);
        };

        // Update auto-save status indicator
        const updateAutoSaveStatus = (status, message) => {
            const $statusEl = $('#auto-save-status');
            switch(status) {
                case 'saving':
                    $statusEl.html('<i class="fas fa-spinner fa-spin text-primary"></i> {{ __("Saving...") }}');
                    break;
                case 'saved':
                    $statusEl.html('<i class="fas fa-check-circle text-success"></i> ' + (message || '{{ __("Draft saved") }}'));
                    break;
                case 'error':
                    $statusEl.html('<i class="fas fa-exclamation-circle text-danger"></i> ' + (message || '{{ __("Save failed") }}'));
                    break;
                case 'unsaved':
                    $statusEl.html('<i class="fas fa-circle text-warning"></i> {{ __("Unsaved changes") }}');
                    break;
                default:
                    $statusEl.html('<i class="fas fa-circle text-success"></i> {{ __("Auto-save enabled") }}');
            }
        };

        // Update progress display
        const updateProgress = (progress) => {
            $('#draft-progress-text').text(progress + '%');
            $('#draft-progress-bar').css('width', progress + '%');
        };

        // Update last saved time
        const updateLastSavedTime = (timeStr) => {
            $('#last-saved-time').text(timeStr);
        };

        // Save draft via AJAX
        const saveDraft = (showNotification = true) => {
            if (isSaving) {
                return Promise.resolve({ success: false, message: 'Save in progress' });
            }

            isSaving = true;
            updateAutoSaveStatus('saving');

            const formData = getFormData();
            formData.append('_token', csrfToken);

            return $.ajax({
                url: saveDraftUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function(response) {
                isSaving = false;
                formChanged = false;

                if (response.success) {
                    updateAutoSaveStatus('saved');
                    updateLastSavedTime(response.last_saved);
                    updateProgress(response.progress);
                    lastSavedData = getFormDataAsString();

                    if (showNotification) {
                        toastr.success(response.message, '{{ __("Draft Saved") }}', {
                            timeOut: 3000,
                            closeButton: true,
                            progressBar: true
                        });
                    }
                } else {
                    updateAutoSaveStatus('error', response.message);
                    if (showNotification) {
                        toastr.warning(response.message, '{{ __("Warning") }}');
                    }
                }

                return response;
            }).fail(function(xhr) {
                isSaving = false;
                let message = '{{ __("Failed to save draft") }}';
                
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON.errors) {
                        const errors = Object.values(xhr.responseJSON.errors).flat();
                        message += ': ' + errors.slice(0, 2).join(', ');
                    }
                }

                updateAutoSaveStatus('error', message);
                if (showNotification) {
                    toastr.error(message, '{{ __("Error") }}');
                }

                return { success: false, message: message };
            });
        };

        // Get form data as string for comparison
        const getFormDataAsString = () => {
            const $form = $('#hnd-application-form');
            return $form.find('input:not([type="file"]), select, textarea').serialize();
        };

        // Start auto-save timer
        const startAutoSave = () => {
            if (autoSaveTimer) {
                clearInterval(autoSaveTimer);
            }

            autoSaveTimer = setInterval(function() {
                if (formChanged && !isSaving) {
                    const currentData = getFormDataAsString();
                    if (currentData !== lastSavedData) {
                        saveDraft(false); // Silent save
                    }
                }
            }, autoSaveInterval);
        };

        // Stop auto-save timer
        const stopAutoSave = () => {
            if (autoSaveTimer) {
                clearInterval(autoSaveTimer);
                autoSaveTimer = null;
            }
        };

        // Warn before leaving with unsaved changes
        const setupBeforeUnload = () => {
            $(window).on('beforeunload', function(e) {
                if (formChanged) {
                    const message = '{{ __("You have unsaved changes. Are you sure you want to leave?") }}';
                    e.returnValue = message;
                    return message;
                }
            });

            // Don't warn when submitting the form
            $('#hnd-application-form').on('submit', function() {
                formChanged = false;
            });
        };

        // Initialize
        $(function() {
            // Initialize last saved data
            lastSavedData = getFormDataAsString();

            // Track changes
            trackFormChanges();

            // Setup auto-save
            startAutoSave();

            // Setup beforeunload warning
            setupBeforeUnload();

            // Bind save draft button in header
            $('#save-draft-btn').on('click', function() {
                saveDraft(true);
            });

            // Bind save draft button at bottom of form
            $('#saveDraftFinalBtn').on('click', function() {
                saveDraft(true);
            });

            // Save draft when losing focus (tab away)
            $(window).on('blur', function() {
                if (formChanged && !isSaving) {
                    saveDraft(false);
                }
            });
        });
    })(jQuery);
</script>

<!-- Toastr for notifications -->
@if(!app()->bound('toastr'))
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "5000"
    };
</script>
@endif

{{-- Dynamic Popup Component for Applicant Portal --}}
@include('components.dynamic-popup', ['area' => 'applicant_portal'])
</body>
</html>
