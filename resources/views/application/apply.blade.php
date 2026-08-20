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
        .qualification-card {
            background: #fbfcfe;
            border-color: #d7dded;
            padding: 1.25rem;
        }
        .qualification-card-header {
            border-bottom: 1px solid #e3e6ed;
            padding-bottom: 0.6rem;
            margin-bottom: 1rem;
        }
        .qualification-card-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }
        .qualification-card-description {
            font-size: 0.825rem;
            color: #6c757d;
            margin: 0.25rem 0 0;
        }
        /* Sets the evidence apart from the qualification's detail fields. */
        .qualification-card-documents {
            border-top: 1px dashed #d7dded;
            margin-top: 0.5rem;
            padding-top: 1rem;
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

        body { background: #f8f9fa; }
        .application-card { border: none; border-radius: 0.75rem; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); overflow: hidden; }
        
        .wizard-container { display: flex; flex-direction: column; }
        @media (min-width: 768px) { .wizard-container { flex-direction: row; } }
        
        .wizard-sidebar { background: #fdfdfd; border-right: 1px solid #e9ecef; padding: 2rem; min-width: 250px; }
        .wizard-content { padding: 2rem; flex-grow: 1; background: #fff; }
        
        .wizard-nav { list-style: none; padding: 0; margin: 0; }
        .wizard-nav .nav-item { margin-bottom: 1rem; }
        .wizard-nav .nav-link { 
            display: flex; align-items: flex-start; color: #6c757d; padding: 0.5rem 0; border: none; background: transparent; text-align: left;
        }
        .wizard-nav .nav-link.active { color: #0d6efd; font-weight: 600; }
        .wizard-nav .nav-link.completed { color: #198754; }
        
        .step-indicator { 
            width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            background: #e9ecef; color: #495057; font-size: 0.875rem; font-weight: 600; margin-right: 1rem; flex-shrink: 0;
        }
        .nav-link.active .step-indicator { background: #0d6efd; color: #fff; }
        .nav-link.completed .step-indicator { background: #198754; color: #fff; }
        
        .nav-link small { display: block; font-size: 0.75rem; color: #adb5bd; font-weight: normal; margin-top: 0.25rem; }
        
        /* Sub-tabs styling */
        .step-tabs .nav-link { 
            border-radius: 2rem; padding: 0.5rem 1.5rem; margin-right: 0.5rem; color: #495057; font-weight: 500; font-size: 0.9rem;
        }
        .step-tabs .nav-link.active { background-color: #e7f1ff; color: #0c7cd5; border: 1px solid #0c7cd5; }
        .step-tabs .nav-link i.fa-check-circle { display: none; color: #198754; margin-right: 5px; }
        .step-tabs .nav-link.completed i.fa-check-circle { display: inline-block; }
        
        .wizard-step { display: none; }
        .wizard-step.active { display: block; }
        
        .step-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef; }
        /* Right-aligned status badge: "Profile · 1 of 3" on tabbed steps, "69% complete" elsewhere. */
        .step-header .step-status { float: right; }
        .step-header .step-status:empty { display: none; }
        .step-header h2 { font-size: 1.75rem; font-weight: 600; color: #212529; margin-bottom: 0.5rem; }
        .step-header p { color: #6c757d; margin-bottom: 0; }
        
        .form-section-title { font-size: 1.1rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; color: #343a40; }
        
        .card-radio { border: 1px solid #dee2e6; border-radius: 0.5rem; padding: 1rem; cursor: pointer; transition: all 0.2s; }
        .card-radio:hover { border-color: #adb5bd; }
        .card-radio.active { border-color: #0d6efd; background-color: #f8faff; box-shadow: 0 0 0 1px #0d6efd; }
        
        .review-section { border: 1px solid #e9ecef; border-radius: 0.5rem; margin-bottom: 1.5rem; background: #fff; }
        .review-section-header { background: #f8f9fa; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; border-radius: 0.5rem 0.5rem 0 0; display: flex; justify-content: space-between; align-items: center; }
        .review-section-header h5 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #343a40; }
        .review-section-body { padding: 1.25rem; }
        .review-item { margin-bottom: 1rem; }
        .review-label { font-size: 0.8rem; color: #6c757d; display: block; margin-bottom: 0.25rem; }
        .review-value { font-weight: 500; color: #212529; }
        
        .wizard-footer { display: flex; justify-content: space-between; margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid #e9ecef; }
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
                                        'certificate_file' => $h->certificate_file,
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

                        // Uploaded checklist files, keyed by document_type. Defined once here
                        // because both the Identification tab and the Documents step render
                        // document inputs through the shared partial.
                        $uploadedDocs = $application->documents ? $application->documents->keyBy('document_type') : collect();

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

                    {{-- The form is visible from the start: .wizard-step CSS already hides every
                         step but the active one, so there is no flash of unstyled content, and the
                         form no longer depends on JavaScript running to become usable. --}}
                    <form id="hnd-application-form" class="needs-validation" novalidate action="{{ route('application.update', $application) }}" method="post" enctype="multipart/form-data">
    <div class="wizard-container">
        <!-- Sidebar -->
        <div class="wizard-sidebar">
            <h6 class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 1px;">YOUR APPLICATION</h6>
            {{-- Seeded from the server's saved draft_progress so a returning applicant sees
                 their real completion, and kept in sync with the header bar on every save. --}}
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong id="sidebar-progress-text">{{ $application->draft_progress ?? 0 }}% complete</strong>
                <small class="text-muted" id="sidebar-step-position"></small>
            </div>
            <div class="progress mb-4" style="height: 6px;">
                <div class="progress-bar bg-primary" id="sidebar-progress-bar" role="progressbar" style="width: {{ $application->draft_progress ?? 0 }}%"></div>
            </div>
            
            <ul class="wizard-nav">
                <li class="nav-item">
                    <button type="button" class="nav-link active" data-step="1">
                        <div class="step-indicator">1</div>
                        <div>
                            <strong>Applicant's Information</strong>
                            <small>Personal details, identification and contact</small>
                        </div>
                    </button>
                </li>
                @if(optional(field('application_guardians'))->status == 1)
                <li class="nav-item">
                    <button type="button" class="nav-link" data-step="2">
                        <div class="step-indicator">2</div>
                        <div>
                            <strong>Family & Financial Support</strong>
                            <small>Parents, guardian and sponsor information</small>
                        </div>
                    </button>
                </li>
                @endif
                @if(optional(field('application_academic_history'))->status == 1)
                <li class="nav-item">
                    <button type="button" class="nav-link" data-step="3">
                        <div class="step-indicator">3</div>
                        <div>
                            <strong>Academic Qualifications</strong>
                            <small>Schools attended, qualifications</small>
                        </div>
                    </button>
                </li>
                @endif
                <li class="nav-item">
                    <button type="button" class="nav-link" data-step="4">
                        <div class="step-indicator">4</div>
                        <div>
                            <strong>Programme Choices</strong>
                            <small>Rank your preferred programmes</small>
                        </div>
                    </button>
                </li>
                @if(optional(field('application_language_proficiency'))->status == 1)
                <li class="nav-item">
                    <button type="button" class="nav-link" data-step="5">
                        <div class="step-indicator">5</div>
                        <div>
                            <strong>Languages</strong>
                            <small>Languages you studied and use</small>
                        </div>
                    </button>
                </li>
                @endif
                <li class="nav-item">
                    <button type="button" class="nav-link" data-step="6">
                        <div class="step-indicator">6</div>
                        <div>
                            <strong>Documents</strong>
                            <small>Review uploaded files</small>
                        </div>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" data-step="7">
                        <div class="step-indicator">7</div>
                        <div>
                            <strong>Review & Confirm</strong>
                            <small>Check your application before submission</small>
                        </div>
                    </button>
                </li>
                @if(!empty($admissionFeeSettings['fee_enabled']))
                <li class="nav-item">
                    <button type="button" class="nav-link" data-step="8">
                        <div class="step-indicator">8</div>
                        <div>
                            <strong>Payment</strong>
                            <small>{{ __('Application fee') }}: {{ number_format($admissionFeeSettings['fee_amount'], 0) }} FCFA</small>
                        </div>
                    </button>
                </li>
                @endif
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="wizard-content">
            @csrf
    
    <div class="wizard-step active" id="step-1">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>Applicant's Information</h2>
            <p>Tell us about the applicant. Use information from official documents.</p>
        </div>
        
        <ul class="nav nav-pills step-tabs mb-4" id="step1Tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button" role="tab"><i class="fas fa-check-circle"></i> Profile</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="identification-tab" data-bs-toggle="pill" data-bs-target="#identification" type="button" role="tab"><i class="fas fa-check-circle"></i> Identification</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="contact-tab" data-bs-toggle="pill" data-bs-target="#contact" type="button" role="tab"><i class="fas fa-check-circle"></i> Contact</button>
            </li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i> <strong>Use the applicant's official information.</strong> Enter the applicant's name and date of birth exactly as shown on their birth certificate.
                </div>
                <h4 class="form-section-title">Applicant's name</h4>
                
                            <p class="step-caption">{{ __('Provide your legal personal information exactly as it appears on official identification documents.') }}</p>
                            
                                
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="first_name">{{ __('field_first_name') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="first_name" id="first_name" value="{{ $getValue('first_name') }}" required>
                                        <div class="invalid-feedback">{{ __('Enter your first name.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="other_names">{{ __('Middle / Other name') }} <small class="text-muted">{{ __('Optional') }}</small></label>
                                        <input type="text" class="form-control" name="other_names" id="other_names" value="{{ $getValue('other_names') }}" placeholder="{{ __('e.g. Michael') }}">
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
                                        {{-- max is yesterday, not today: the server rule is
                                             before:today, so a date of today is rejected. Without
                                             it the picker offered future dates that only failed
                                             once the draft reached the server. --}}
                                        <input type="date" class="form-control" name="dob" id="dob" value="{{ $getValue('dob') ? (is_string($getValue('dob')) ? $getValue('dob') : $getValue('dob')->format('Y-m-d')) : '' }}" required max="{{ now()->subDay()->format('Y-m-d') }}" style="cursor: pointer;" onfocus="this.showPicker && this.showPicker()" onclick="this.showPicker && this.showPicker()">
                                        <div class="invalid-feedback">{{ __('Enter a date of birth in the past.') }}</div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="nationality">{{ __('field_nationality') }} <span>*</span></label>
                                        @include('partials.country-select', ['name' => 'nationality', 'id' => 'nationality', 'value' => $getValue('nationality'), 'required' => true])
                                        <div class="invalid-feedback">{{ __('Provide your nationality.') }}</div>
                                    </div>
                                </div>
                            

                            
                                
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
                            

                            
                                
            </div>{{-- /Profile tab --}}

            <div class="tab-pane fade" id="identification" role="tabpanel">
                <div class="alert alert-info mb-4">
                    <i class="fas fa-id-card me-2"></i>
                    <strong>{{ __('Proof of identity.') }}</strong>
                    {{ __('Upload the applicant\'s photograph and identity documents here. The name and date of birth on these files must match the Profile tab.') }}
                </div>

                <h4 class="form-section-title">{{ __('Applicant photograph') }}</h4>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="photo">{{ __('Recent Passport Photograph (max 5MB)') }} <span>*</span></label>
                        {{-- data-has-existing mirrors the server rule: the photo is only
                             mandatory when none has been uploaded on a previous save. --}}
                        <input type="file" class="form-control size-guard" data-max-size-mb="5" data-has-existing="{{ $application->photo ? '1' : '0' }}" name="photo" id="photo" accept="image/jpeg,image/png,image/*" @if(!$application->photo) required @endif>
                        <div class="invalid-feedback">{{ __('Upload a recent passport style photograph.') }}</div>
                        <small class="document-help">{{ __('Use a clear, recent colour photograph with the applicant facing the camera. Avoid selfies, filters and group photographs.') }}</small>
                        @if($application->photo)
                            <small class="text-success d-block mt-1"><i class="fas fa-check-circle me-1"></i>{{ __('A photograph is already on file. Choose a new file only if you want to replace it.') }}</small>
                        @endif
                    </div>
                    <div class="form-group col-md-6">
                        <label for="signature">{{ __('Signature Sample (max 2MB)') }}</label>
                        <input type="file" class="form-control size-guard" data-max-size-mb="2" data-has-existing="{{ $application->signature ? '1' : '0' }}" name="signature" id="signature" accept="image/jpeg,image/png,image/*">
                        @if($application->signature)
                            <small class="text-success d-block mt-1"><i class="fas fa-check-circle me-1"></i>{{ __('A signature is already on file.') }}</small>
                        @endif
                    </div>
                </div>

                @if(optional(field('application_document_checklist'))->status == 1 && count($identityDocuments))
                    <h4 class="form-section-title">{{ __('Identity documents') }}</h4>
                    <p class="document-help">{{ __('Accepted formats: JPG, PNG, PDF. Maximum size per file: 10MB.') }}</p>
                    <div class="row">
                        @foreach($identityDocuments as $key => $document)
                            <div class="col-md-6">
                                @include('application.partials.document-input', ['key' => $key, 'document' => $document, 'uploadedDocs' => $uploadedDocs])
                            </div>
                        @endforeach
                    </div>
                @endif

                <h4 class="form-section-title">{{ __('Identification numbers') }}</h4>
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
                                    <div class="form-group col-md-4">
                                        <label for="national_id_expiry_date">{{ __('National ID Expiry Date') }} <small class="text-muted">{{ __('Optional') }}</small></label>
                                        <input type="date" class="form-control" name="national_id_expiry_date" id="national_id_expiry_date" value="{{ $getValue('national_id_expiry_date') ? (is_string($getValue('national_id_expiry_date')) ? $getValue('national_id_expiry_date') : $getValue('national_id_expiry_date')->format('Y-m-d')) : '' }}">
                                    </div>
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
                                    <div class="form-group col-md-4">
                                        <label for="passport_expiry_date">{{ __('Passport Expiry Date') }} <small class="text-muted">{{ __('Optional') }}</small></label>
                                        <input type="date" class="form-control" name="passport_expiry_date" id="passport_expiry_date" value="{{ $getValue('passport_expiry_date') ? (is_string($getValue('passport_expiry_date')) ? $getValue('passport_expiry_date') : $getValue('passport_expiry_date')->format('Y-m-d')) : '' }}">
                                    </div>
                                </div>
            </div>{{-- /Identification tab --}}

            <div class="tab-pane fade" id="contact" role="tabpanel">
                <div class="alert alert-info mb-4">
                    <i class="fas fa-phone-alt me-2"></i> <strong>How we can reach the applicant.</strong> Use an active phone number and email.
                </div>
                
                            <p class="step-caption">{{ __('Provide current and permanent address information so we can reach you physically or through mail.') }}</p>
                            
                                
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="country">{{ __('field_country') }} <span>*</span></label>
                                        @include('partials.country-select', ['name' => 'country', 'id' => 'country', 'value' => $getValue('country'), 'required' => true])
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
                            
                        
            </div>
        </div>
        
        <div class="wizard-footer">
            <div></div> <!-- Empty div for flex-between spacing when no back button -->
            <button type="button" class="btn btn-primary btn-next">Save and continue <i class="fas fa-arrow-right ms-2"></i></button>
        </div>
    </div>
    
    @if(optional(field('application_guardians'))->status == 1)
    <div class="wizard-step" id="step-2">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>Family & Financial Support</h2>
            <p>Tell us about the applicant's parents and the people responsible for their care and school fees.</p>
        </div>
        
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
                            
        
        <div class="wizard-footer">
            <button type="button" class="btn btn-outline-secondary btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
            <button type="button" class="btn btn-primary btn-next">Save and continue <i class="fas fa-arrow-right ms-2"></i></button>
        </div>
    </div>
    @endif

    @if(optional(field('application_academic_history'))->status == 1)
    <div class="wizard-step" id="step-3">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>Academic Qualifications</h2>
            <p>Schools attended, qualifications and supporting certificates.</p>
        </div>
        <div class="alert alert-info mb-4">
            <i class="fas fa-graduation-cap me-2"></i>
            <strong>{{ __('Complete each required qualification') }}</strong>
            <p class="mb-2 mt-1 small">{{ __('Fill in every card shown below and upload the document named on each card. If your certificate has a different name, enter that name as the equivalent.') }}</p>
            @if(count($qualificationCards))
                <ul class="mb-0 small">
                    @foreach($qualificationCards as $card)
                        <li>{{ $card['description'] ?: $card['label'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="form-section">
            <h4 class="form-section-title">{{ __('Academic qualifications') }} <span class="text-danger">*</span></h4>
            <p class="step-caption">{{ __('Complete every required qualification card below. Add an earlier qualification only if it supports this application.') }}</p>

            <div id="academicHistoryRepeater">
                {{-- Prescribed cards first, in the order an administrator set
                     under Degree Types → Form Configuration. Their index must be
                     stable so the uploads and the details post together. --}}
                @foreach($qualificationCards as $index => $card)
                    @include('application.partials.qualification-card', [
                        'index' => $index,
                        'card' => $card,
                        'uploadedDocs' => $uploadedDocs,
                    ])
                @endforeach

                {{-- Then anything the applicant added themselves. --}}
                @foreach($qualificationExtras as $extraIndex => $extra)
                    @include('application.partials.qualification-card', [
                        'index' => count($qualificationCards) + $extraIndex,
                        'card' => [
                            'key' => null,
                            'label' => __('Additional qualification'),
                            'description' => null,
                            'required' => false,
                            'documents' => [],
                            'history' => $extra,
                        ],
                        'uploadedDocs' => $uploadedDocs,
                    ])
                @endforeach
            </div>

            <div class="d-flex align-items-center gap-3 mt-2">
                <button type="button" class="btn btn-outline-primary" id="addAcademicHistory">
                    <i class="fas fa-plus me-1"></i> {{ __('Add another qualification') }}
                </button>
                <small class="text-muted">{{ __('You can add up to 5 qualifications.') }}</small>
            </div>
        </div>
                            
        
        <div class="wizard-footer">
            <button type="button" class="btn btn-outline-secondary btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
            <button type="button" class="btn btn-primary btn-next">Save and continue <i class="fas fa-arrow-right ms-2"></i></button>
        </div>
    </div>
    @endif

    <div class="wizard-step" id="step-4">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>Programme Choices</h2>
            <p>Review your preferred programmes.</p>
        </div>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> <strong>Choose carefully.</strong> These were chosen when you started this application. To apply for a different programme, start a new application from My Account.
        </div>
        
                            <p class="step-caption">{{ __('These were chosen when you started this application and cannot be changed here. To apply for a different programme or intake, start a new application from My Account.') }}</p>
                            @php
                                $facultyTitle = optional($faculties->firstWhere('id', optional($application->program)->faculty_id))->title;
                                $secondProgTitle = $application->second_program_choice_id ? optional($programs->firstWhere('id', $application->second_program_choice_id))->title : null;
                                $thirdProgTitle = $application->third_program_choice_id ? optional($programs->firstWhere('id', $application->third_program_choice_id))->title : null;
                            @endphp
                            
                                
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
                            
                        
        
        <div class="wizard-footer">
            <button type="button" class="btn btn-outline-secondary btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
            <button type="button" class="btn btn-primary btn-next">Save and continue <i class="fas fa-arrow-right ms-2"></i></button>
        </div>
    </div>
    
    @if(optional(field('application_language_proficiency'))->status == 1)
    <div class="wizard-step" id="step-5">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>Languages</h2>
            <p>Languages you studied and use.</p>
        </div>
        
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
                            
        
        <div class="wizard-footer">
            <button type="button" class="btn btn-outline-secondary btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
            <button type="button" class="btn btn-primary btn-next">Save and continue <i class="fas fa-arrow-right ms-2"></i></button>
        </div>
    </div>
    @endif

    <div class="wizard-step" id="step-6">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>Documents</h2>
            <p>Review uploaded files and provide remaining documents.</p>
        </div>
        <div id="documents-original-container">

                            @if(optional(field('application_document_checklist'))->status == 1)

                                    {{-- Reconciliation panel: identity files were already collected on
                                         Applicant Information → Identification, so this step reports
                                         them rather than asking for them a second time. --}}
                                    @if(count($identityDocuments) || $application->photo)
                                        <div class="alert alert-info mb-4">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>{{ __('Collected under Applicant Information') }}</strong>
                                            <p class="mb-2 mt-1 small">{{ __('These files were received under Applicant Information → Identification. Go back to that step to replace any of them.') }}</p>
                                            <ul class="mb-0 small">
                                                <li>
                                                    @if($application->photo)
                                                        <i class="fas fa-check text-success me-1"></i>
                                                    @else
                                                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                                    @endif
                                                    {{ __('Applicant photograph') }}
                                                    <span class="text-muted">— {{ $application->photo ? __('uploaded') : __('still required') }}</span>
                                                </li>
                                                @foreach($identityDocuments as $key => $document)
                                                    @php
                                                        $identityDoc = $uploadedDocs->get($key);
                                                        $identityUploaded = $identityDoc && $identityDoc->file_path;
                                                    @endphp
                                                    <li>
                                                        @if($identityUploaded)
                                                            <i class="fas fa-check text-success me-1"></i>
                                                        @else
                                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                                        @endif
                                                        {{ $document['label'] }}
                                                        <span class="text-muted">— {{ $identityUploaded ? __('uploaded') : ($document['required'] ? __('still required') : __('optional')) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    {{-- Same reconciliation for the qualification evidence: it belongs
                                         to its card on the Academic Qualifications step, so this step
                                         reports it instead of asking for the same certificate twice. --}}
                                    @if(count($qualificationDocuments))
                                        <div class="alert alert-info mb-4">
                                            <i class="fas fa-graduation-cap me-2"></i>
                                            <strong>{{ __('Collected under Academic Qualifications') }}</strong>
                                            <p class="mb-2 mt-1 small">{{ __('These files were received with the qualification they belong to. Go back to that step to replace any of them.') }}</p>
                                            <ul class="mb-0 small">
                                                @foreach($qualificationCards as $card)
                                                    @foreach($card['documents'] as $key => $document)
                                                        @php
                                                            $qualDoc = $uploadedDocs->get($key);
                                                            $qualUploaded = $qualDoc && $qualDoc->file_path;
                                                        @endphp
                                                        <li>
                                                            @if($qualUploaded)
                                                                <i class="fas fa-check text-success me-1"></i>
                                                            @else
                                                                <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                                            @endif
                                                            {{ $card['label'] }} — {{ $document['label'] }}
                                                            <span class="text-muted">— {{ $qualUploaded ? __('uploaded') : ($document['required'] ? __('still required') : __('optional')) }}</span>
                                                        </li>
                                                    @endforeach
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if(count($remainingDocuments))
                                        <h4 class="form-section-title">{{ __('Remaining application documents') }}</h4>
                                        <p class="document-help">{{ __('Upload clear scans or photos. Accepted formats: JPG, PNG, PDF. Maximum size per file: 10MB.') }}</p>
                                        <div class="row">
                                            @foreach($remainingDocuments as $key => $document)
                                                <div class="col-md-6">
                                                    @include('application.partials.document-input', ['key' => $key, 'document' => $document, 'uploadedDocs' => $uploadedDocs])
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted">{{ __('No further documents are required for this programme.') }}</p>
                                    @endif
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
                                
                            @endif



                            <div class="mt-4 text-end">
                                {{-- Payment lives on its own step. This block used to carry
                                     a fee warning and a link out to the dashboard fee modal,
                                     left over from the single-page form — it sent applicants
                                     away from the wizard two steps before Payment. --}}
                                <button type="button" class="btn btn-outline-secondary me-2" id="saveDraftFinalBtn">
                                    <i class="fas fa-save me-1"></i> {{ __('Save Draft') }}
                                </button>
                            </div>
                        
        </div>
        
        <div class="wizard-footer">
            <button type="button" class="btn btn-outline-secondary btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
            <button type="button" class="btn btn-primary btn-next">Continue to Review <i class="fas fa-arrow-right ms-2"></i></button>
        </div>
    </div>
    
    <div class="wizard-step" id="step-7">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>Review & Confirm</h2>
            <p>Check your application before submission.</p>
        </div>
        <div class="alert alert-info mb-4">
            <i class="fas fa-check-double me-2"></i> <strong>Check the details before you confirm.</strong> Unless resubmission is requested, you cannot change your information once you confirm.
        </div>
        
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <strong>{{ __('Keep a copy') }}</strong>
                <p class="text-muted small mb-0">{{ __('Print or download the information currently saved in your application.') }}</p>
            </div>
            <div>
                {{-- Opens the same printable document the admissions office reads. --}}
                <a href="{{ route('application.print', $application) }}" target="_blank" rel="noopener"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-print me-1"></i> {{ __('Print') }}
                </a>
                <a href="{{ route('application.print', $application) }}?download=1" target="_blank" rel="noopener"
                   class="btn btn-outline-secondary btn-sm ms-1">
                    <i class="fas fa-file-pdf me-1"></i> {{ __('Download PDF') }}
                </a>
            </div>
        </div>

        <div id="review-summary-container">
            <!-- Populated by JS -->
            <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Generating summary...</p></div>
        </div>
        
        {{-- The declaration belongs with the confirmation that governs it.
             It was previously two steps earlier, on Documents. --}}
        @if(optional(field('application_declaration'))->status == 1)
            
                
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
                    {{-- The tick is remembered on the draft, so it has to survive a
                         reload, and un-ticking has to be posted as well as ticking —
                         an unchecked box sends nothing at all on its own. --}}
                    <input type="hidden" name="agree_terms" value="0">
                    <input class="form-check-input" type="checkbox" value="1" id="agree_terms" name="agree_terms" {{ old('agree_terms', \App\Services\ApplicationCompleteness::hasAgreedToTerms($application) ? 1 : 0) ? 'checked' : '' }} required>
                    <label class="form-check-label fw-bold" for="agree_terms">
                        {{ __('I verify that all information is correct and complete, I accept the institute’s admission policies, and I consent to :institution processing this application.', ['institution' => optional($setting ?? null)->title ?? config('app.name')]) }}
                        <span class="text-danger">*</span>
                    </label>
                    <div class="invalid-feedback">{{ __('You must accept the declaration to submit your application.') }}</div>
                </div>
            
        @endif


        
        <div class="wizard-footer">
            <button type="button" class="btn btn-outline-secondary btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
            @if(!empty($admissionFeeSettings['fee_enabled']))
                <button type="button" class="btn btn-primary btn-next" id="continue-to-payment-btn" disabled>{{ __('Continue to payment') }} <i class="fas fa-arrow-right ms-2"></i></button>
            @else
                <button type="submit" class="btn btn-success" id="submit-application-btn" disabled>{{ __('Submit Application') }} <i class="fas fa-paper-plane ms-2"></i></button>
            @endif
        </div>
    </div>

    @if(!empty($admissionFeeSettings['fee_enabled']))
    <div class="wizard-step" id="step-8"
         data-fee-id="{{ $admissionFee->id ?? '' }}"
         data-application-id="{{ $application->id }}">
        <div class="step-header">
            <span class="badge bg-light text-primary border mb-2 step-counter"></span>
            <span class="badge bg-light text-primary border mb-2 step-status"></span>
            <h2>{{ __('Payment') }}</h2>
            <p>{{ __('Application fee') }}: {{ number_format($admissionFeeSettings['fee_amount'], 0) }} FCFA</p>
        </div>

        @php
            $feePaid = empty($admissionFeeRequired);
            $mtnMomoEnabled = (bool) config('momo.providers.mtn.enabled');
            $orangeMomoEnabled = (bool) config('momo.providers.orange.enabled');
            $anyMomoEnabled = $mtnMomoEnabled || $orangeMomoEnabled;
            $receiptPending = $latestPaymentReceipt && $latestPaymentReceipt->verification_status === 'pending';
            $receiptRejected = $latestPaymentReceipt && $latestPaymentReceipt->verification_status === 'rejected';
            // An applicant may not pay until the form is finished, because
            // approving the fee submits the application there and then.
            $paymentBlockers = \App\Services\ApplicationCompleteness::missing($application);
        @endphp

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <small class="text-uppercase text-muted fw-bold" style="font-size:.75rem;letter-spacing:1px;">{{ __('Application fee') }}</small>
                    <div class="fs-4 fw-bold">{{ number_format($admissionFeeSettings['fee_amount'], 0) }} FCFA</div>
                    @if($admissionFee && $admissionFee->due_date)
                        <small class="text-muted">{{ __('Due') }}: {{ \Carbon\Carbon::parse($admissionFee->due_date)->format('M j, Y') }}</small>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <small class="text-uppercase text-muted fw-bold" style="font-size:.75rem;letter-spacing:1px;">{{ __('Outstanding balance') }}</small>
                    <div class="fs-4 fw-bold" id="fee-balance-display">{{ number_format($admissionFeeBalance, 0) }} FCFA</div>
                    <small class="text-muted">
                        @if($feePaid)
                            <span class="text-success"><i class="fas fa-check-circle me-1"></i>{{ __('Payment confirmed') }}</span>
                        @elseif($receiptPending)
                            <span class="text-warning"><i class="fas fa-hourglass-half me-1"></i>{{ __('Receipt awaiting verification') }}</span>
                        @else
                            @if($paymentBlockers){{ __('Complete your application to pay') }}@else{{ __('Payment required before submission') }}@endif
                        @endif
                    </small>
                </div>
            </div>
        </div>

        @if($feePaid)
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>{{ __('Your admission fee is settled.') }}</strong>
                {{ __('You can now submit your application.') }}
            </div>
        @else
            <div id="payment-blocked" class="{{ $paymentBlockers ? '' : 'd-none' }}">
                {{-- Payment is held back rather than merely warned about: once the
                     fee is approved the application submits itself, so anything
                     unfinished at that moment would reach admissions unfinished. --}}
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>{{ __('Finish your application before paying.') }}</strong>
                    <p class="mb-0 mt-1 small">{{ __('As soon as your payment is approved your application is submitted automatically — so there is nothing left to press, and nothing to come back for. That is why we ask for the last few details first.') }}</p>
                </div>

                <h6 class="fw-bold">{{ __('Still outstanding') }}</h6>
                <ul class="list-group mb-4" id="payment-blockers-list">
                    @foreach($paymentBlockers as $blocker)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-circle-exclamation text-warning me-2"></i>{{ $blocker['label'] }}</span>
                            <button type="button" class="btn btn-sm btn-outline-primary go-to-step" data-step="{{ $blocker['step'] }}">
                                {{ __('Go there') }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Both states are rendered and toggled, rather than one being
                 chosen at render time. The applicant fills the form over AJAX,
                 so a list of outstanding items decided when the page loaded is
                 stale by the time they reach this step — it used to insist on
                 fields they had just filled in until they reloaded. --}}
            <div id="payment-controls" class="{{ $paymentBlockers ? 'd-none' : '' }}">
            @if($receiptPending)
                <div class="alert alert-warning">
                    <i class="fas fa-hourglass-half me-2"></i>
                    <strong>{{ __('Your receipt is awaiting verification.') }}</strong>
                    <p class="mb-0 mt-1 small">{{ __('Admissions will confirm your payment, normally within 7 business days. You will be able to submit this application as soon as it is confirmed — no further action is needed from you now.') }}</p>
                </div>
            @elseif($receiptRejected)
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    <strong>{{ __('Your last receipt was not accepted.') }}</strong>
                    @if($latestPaymentReceipt->rejection_reason)
                        <p class="mb-0 mt-1 small">{{ $latestPaymentReceipt->rejection_reason }}</p>
                    @endif
                    <p class="mb-0 mt-1 small">{{ __('Please pay again or upload a clearer receipt below.') }}</p>
                </div>
            @endif

            {{-- data-status-exempt: these are payment-method choices, not sequential
                 sub-steps, so the step badge keeps showing overall completion. --}}
            <ul class="nav nav-pills step-tabs mb-4" id="paymentTabs" role="tablist" data-status-exempt="1">
                @if($mtnMomoEnabled)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#payMtn" type="button" role="tab">{{ __('MTN MoMo') }}</button>
                    </li>
                @endif
                @if($orangeMomoEnabled)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ !$mtnMomoEnabled ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#payOrange" type="button" role="tab">{{ __('Orange Money') }}</button>
                    </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ !$anyMomoEnabled ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#payManual" type="button" role="tab">{{ __('Upload payment proof') }}</button>
                </li>
            </ul>

            <div class="tab-content">
                @if($mtnMomoEnabled)
                    <div class="tab-pane fade show active" id="payMtn" role="tabpanel">
                        <p class="step-caption">{{ __('Pay instantly from your MTN Mobile Money account. Approve the prompt on your phone.') }}</p>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="momo_msisdn_mtn">{{ __('MTN phone number') }}</label>
                                <input type="tel" class="form-control momo-msisdn" id="momo_msisdn_mtn" data-skip-autosave="1" placeholder="670000000">
                            </div>
                        </div>
                        <div class="momo-status alert alert-info d-none mt-3" role="alert"></div>
                        <button type="button" class="btn btn-warning momo-pay-btn" data-provider="mtn">
                            <i class="fas fa-mobile-alt me-1"></i>{{ __('Pay') }} {{ number_format($admissionFeeBalance, 0) }} FCFA
                        </button>
                    </div>
                @endif

                @if($orangeMomoEnabled)
                    <div class="tab-pane fade {{ !$mtnMomoEnabled ? 'show active' : '' }}" id="payOrange" role="tabpanel">
                        <p class="step-caption">{{ __('Pay instantly from your Orange Money account.') }}</p>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="momo_msisdn_orange">{{ __('Orange phone number') }}</label>
                                <input type="tel" class="form-control momo-msisdn" id="momo_msisdn_orange" data-skip-autosave="1" placeholder="690000000">
                            </div>
                        </div>
                        <div class="momo-status alert alert-info d-none mt-3" role="alert"></div>
                        <button type="button" class="btn btn-danger momo-pay-btn" data-provider="orange">
                            <i class="fas fa-mobile-alt me-1"></i>{{ __('Pay') }} {{ number_format($admissionFeeBalance, 0) }} FCFA
                        </button>
                    </div>
                @endif

                <div class="tab-pane fade {{ !$anyMomoEnabled ? 'show active' : '' }}" id="payManual" role="tabpanel">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>{{ __('What to upload') }}</strong>
                        <p class="mb-0 mt-1 small">{{ __('A clear PDF, JPG or PNG of the bank receipt or payment confirmation. Make sure the amount, date and payment reference are legible.') }}</p>
                    </div>
                    @if(!empty($admissionFeeSettings['fee_instructions']))
                        <div class="mb-3">{!! $admissionFeeSettings['fee_instructions'] !!}</div>
                    @endif

                    {{-- Not a nested <form>: these inputs are namespaced under pay_* and
                         posted over AJAX, so they never travel with the application. --}}
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="pay_payment_date">{{ __('Payment date') }} <span>*</span></label>
                            <input type="date" class="form-control" id="pay_payment_date" data-skip-autosave="1" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="pay_amount">{{ __('Amount') }}</label>
                            {{-- The admission fee is set by the institution, not
                                 negotiated at the point of payment. Left editable,
                                 an applicant could declare any amount against a
                                 receipt and leave admissions to reconcile it. --}}
                            <input type="number" step="0.01" class="form-control" id="pay_amount" data-skip-autosave="1"
                                   value="{{ $admissionFeeBalance }}" readonly>
                            <small class="text-muted">{{ __('Set by the institution — this is the outstanding balance.') }}</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="pay_payment_method">{{ __('Payment method') }} <span>*</span></label>
                            <select class="form-control" id="pay_payment_method" data-skip-autosave="1">
                                <option value="4">{{ __('Bank Transfer') }}</option>
                                <option value="2">{{ __('Cash') }}</option>
                                <option value="3">{{ __('Cheque') }}</option>
                                <option value="1">{{ __('Card') }}</option>
                                <option value="5">{{ __('E-wallet') }}</option>
                                <option value="6">{{ __('MTN MoMo') }}</option>
                                <option value="7">{{ __('Orange Money') }}</option>
                                <option value="8">{{ __('Other') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pay_payment_reference">{{ __('Payment reference') }} <span>*</span></label>
                            <input type="text" class="form-control" id="pay_payment_reference" data-skip-autosave="1" placeholder="{{ __('Receipt or transaction reference') }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pay_receipt_file">{{ __('Proof of payment') }} <span>*</span></label>
                            <input type="file" class="form-control" id="pay_receipt_file" data-skip-autosave="1" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="document-help">{{ __('PDF, JPG or PNG. Maximum file size 2 MB.') }}</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-12">
                            <label for="pay_student_note">{{ __('Additional note') }} <small class="text-muted">{{ __('Optional') }}</small></label>
                            <textarea class="form-control" id="pay_student_note" data-skip-autosave="1" rows="2" placeholder="{{ __('Anything that helps Admissions verify the payment') }}"></textarea>
                        </div>
                    </div>
                    <div id="receipt-upload-status" class="alert d-none" role="alert"></div>
                    <button type="button" class="btn btn-primary" id="upload-receipt-btn">
                        <i class="fas fa-upload me-1"></i>{{ __('Upload proof of payment') }}
                    </button>
                </div>
            </div>
            </div>
        @endif

        <div class="wizard-footer">
            <button type="button" class="btn btn-outline-secondary btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
            <button type="submit" class="btn btn-success" id="submit-application-btn" disabled>{{ __('Submit Application') }} <i class="fas fa-paper-plane ms-2"></i></button>
        </div>
    </div>
    @endif

        </div> <!-- End Content -->
    </div> <!-- End Container -->
    </form>
    </div> <!-- Close page-wrapper -->
    </div> <!-- Close main-body -->
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

    
    <script>
    "use strict";
    $(document).ready(function () {

        /* =============================================================
         | Vertical wizard
         |
         | Steps are discovered from the DOM instead of being hard-coded,
         | so when a degree type switches a whole section off (guardians,
         | academic history, languages) the wizard simply has fewer steps
         | and the numbering still reads 1..N.
         |============================================================= */

        var $form = $('#hnd-application-form');
        var $steps = $('.wizard-step');
        var stepIds = $steps.map(function () { return this.id; }).get();
        var totalSteps = stepIds.length;

        if (!$form.length || !totalSteps) {
            return;
        }

        var feeRequired = {{ !empty($admissionFeeRequired) ? 'true' : 'false' }};
        var currentIndex = 0;
        var highestIndexReached = 0;

        function stepIdAt(index) {
            return stepIds[index];
        }

        function indexOfStepId(id) {
            return stepIds.indexOf(id);
        }

        /** Native trim, so the code does not rely on $.trim (removed in jQuery 4). */
        function trim(value) {
            return String(value === null || value === undefined ? '' : value).trim();
        }

        function escapeHtml(value) {
            return $('<div>').text(value === null || value === undefined ? '' : value).html();
        }

        function notify(type, message) {
            if (window.toastr && typeof toastr[type] === 'function') {
                toastr[type](message);
            } else {
                window.alert(message);
            }
        }

        /** Human-readable label for a field, used in validation messages. */
        function labelFor(element) {
            var $el = $(element);
            var text = '';
            var id = $el.attr('id');

            if ($el.closest('label').length) {
                text = $el.closest('label').text();
            }
            if (!text && id) {
                text = $('label[for="' + id + '"]').first().text();
            }
            if (!text) {
                text = $el.closest('.form-group, .form-check').find('label').first().text();
            }
            if (!text) {
                text = $el.attr('name') || 'this field';
            }

            return trim(text.replace(/\*/g, ''));
        }

        /**
         * A field counts as active unless something other than a collapsed
         * wizard step or an inactive tab pane is hiding it. Those two are
         * "not scrolled to yet", not "not applicable" — fields inside them
         * must still be validated before the applicant leaves the step.
         */
        function isFieldActive(element) {
            if (element.disabled) {
                return false;
            }

            var node = element;
            while (node && node !== document.body) {
                var $node = $(node);
                if (!$node.hasClass('wizard-step') && !$node.hasClass('tab-pane')) {
                    if ($node.css('display') === 'none') {
                        return false;
                    }
                }
                node = node.parentElement;
            }

            return true;
        }

        /**
         * Marks every unfilled required field inside $container and returns
         * the first one, or null when the container is complete.
         */
        function validateContainer($container) {
            var firstInvalid = null;

            $container.find('input[required], select[required], textarea[required]').each(function () {
                var $field = $(this);

                if (!isFieldActive(this)) {
                    $field.removeClass('is-invalid');
                    return;
                }

                var ok;
                if (this.type === 'radio' || this.type === 'checkbox') {
                    ok = $form.find('input[name="' + this.name + '"]:checked').length > 0;
                } else if (this.type === 'file') {
                    ok = !!this.value || String($field.attr('data-has-existing')) === '1';
                } else {
                    ok = trim($field.val() || '') !== '';
                }

                $field.toggleClass('is-invalid', !ok);
                if (!ok && !firstInvalid) {
                    firstInvalid = this;
                }
            });

            // Being filled in is not the same as being right. A date outside its
            // allowed range or a malformed email used to pass here and fail only
            // once the draft reached the server — by which time the applicant had
            // already been moved on to the next step.
            $container.find('input, select, textarea').each(function () {
                var $field = $(this);

                if (!isFieldActive(this) || typeof this.checkValidity !== 'function') {
                    return;
                }
                // Emptiness is the loop above's business, not this one's.
                if (this.type !== 'checkbox' && this.type !== 'radio' && trim($field.val() || '') === '') {
                    return;
                }
                if (this.checkValidity()) {
                    return;
                }

                $field.addClass('is-invalid');
                if (!firstInvalid) {
                    firstInvalid = this;
                }
            });

            return firstInvalid;
        }

        /**
         * Why a field is blocking the step.
         *
         * The browser already phrases range and format problems well ("Value must
         * be 2026-08-19 or earlier"), so pass those through rather than replacing
         * them with a generic "please complete".
         */
        function invalidMessage(element) {
            var label = labelFor(element);
            var reason = element && element.validationMessage ? trim(element.validationMessage) : '';
            var filled = trim($(element).val() || '') !== '';

            if (filled && reason) {
                return 'Check "' + label + '": ' + reason;
            }

            return 'Please complete "' + label + '" before continuing.';
        }

        /** Activate a Bootstrap pill, with fallbacks so navigation never dead-ends. */
        function showTab($button) {
            if (!$button || !$button.length) {
                return;
            }

            if (window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance($button[0]).show();
            } else if (typeof $button.tab === 'function') {
                $button.tab('show');
            } else {
                var target = $button.attr('data-bs-target');
                $button.closest('.step-tabs').find('.nav-link').removeClass('active');
                $button.addClass('active');
                $(target).closest('.tab-content').find('.tab-pane').removeClass('show active');
                $(target).addClass('show active');
            }

            // Programmatic tab moves must update the badge too — the click and
            // shown.bs.tab handlers only cover tabs the applicant clicks. Called
            // twice on purpose: now for the synchronous fallbacks, and again on
            // the next tick for Bootstrap, whose shown event is asynchronous.
            refreshStepStatus();
            window.setTimeout(refreshStepStatus, 0);
        }

        /** Bring an invalid field into view, opening its tab first if needed. */
        function revealField(element) {
            var $pane = $(element).closest('.tab-pane');

            if ($pane.length && !$pane.hasClass('active')) {
                showTab($('[data-bs-target="#' + $pane.attr('id') + '"]'));
            }

            setTimeout(function () {
                try {
                    element.focus();
                } catch (e) { /* focusing a hidden control is not fatal */ }
            }, 250);
        }

        /** Step counters and sidebar indicators, numbered over the steps that exist. */
        function paintStaticLabels() {
            $steps.each(function (index) {
                $(this).find('.step-counter').text('STEP ' + (index + 1) + ' OF ' + totalSteps);
            });

            $('.wizard-nav .nav-link').each(function () {
                var index = indexOfStepId('step-' + $(this).data('step'));
                if (index > -1) {
                    $(this).find('.step-indicator').text(index + 1);
                }
            });
        }

        /**
         * The right-hand badge on the current step: the sub-tab position when the
         * step has tabs ("Profile · 1 of 3"), otherwise overall completion.
         */
        function refreshStepStatus() {
            var $step = $('#' + stepIdAt(currentIndex));
            var $tabs = $step.find('.step-tabs:not([data-status-exempt]) .nav-link');
            var $badge = $step.find('.step-status');

            if ($tabs.length) {
                var position = $tabs.index($step.find('.step-tabs:not([data-status-exempt]) .nav-link.active'));
                if (position < 0) {
                    position = 0;
                }
                $badge.text(trim($tabs.eq(position).text()) + ' · ' + (position + 1) + ' of ' + $tabs.length);
                return;
            }

            $badge.text(trim($('#draft-progress-text').text()) + ' complete');
        }

        function updateWizardUI() {
            if (currentIndex > highestIndexReached) {
                highestIndexReached = currentIndex;
            }

            $steps.removeClass('active');
            $('#' + stepIdAt(currentIndex)).addClass('active');

            $('.wizard-nav .nav-link').each(function () {
                var index = indexOfStepId('step-' + $(this).data('step'));
                $(this)
                    .toggleClass('active', index === currentIndex)
                    .toggleClass('completed', index > -1 && index <= highestIndexReached && index !== currentIndex);
            });

            $('#sidebar-step-position').text('Step ' + (currentIndex + 1) + ' of ' + totalSteps);
            refreshStepStatus();

            if (stepIdAt(currentIndex) === 'step-7') {
                generateReviewSummary();
            }

            $(document).trigger('wizard:step-shown', [stepIdAt(currentIndex)]);

            var $anchor = $('.wizard-container');
            if ($anchor.length) {
                $('html, body').animate({ scrollTop: $anchor.offset().top - 50 }, 300);
            }
        }

        function goToIndex(index) {
            if (index < 0 || index >= totalSteps || index === currentIndex) {
                return;
            }
            currentIndex = index;
            updateWizardUI();
        }

        /** Persist through the draft module in the auto-save block, when present. */
        function saveDraftQuietly() {
            var bridge = window.PaxApplicationDraft;
            if (!bridge || typeof bridge.save !== 'function') {
                return null;
            }

            var request = bridge.save(false);
            return (request && typeof request.always === 'function') ? request : null;
        }

        /* ---- Navigation ------------------------------------------------ */

        $('.btn-next').on('click', function () {
            var $button = $(this);
            var $step = $('#' + stepIdAt(currentIndex));
            var $activePane = $step.find('.tab-pane.active');

            // 1. Immediate feedback on what the applicant can actually see.
            if ($activePane.length) {
                var paneInvalid = validateContainer($activePane);
                if (paneInvalid) {
                    notify('error', invalidMessage(paneInvalid));
                    revealField(paneInvalid);
                    return;
                }

                // 2. Move across the remaining tabs before moving down a step.
                var $activeTab = $step.find('.step-tabs:not([data-status-exempt]) .nav-link.active');
                var $nextTab = $activeTab.parent().nextAll('.nav-item').first().find('.nav-link');
                if ($nextTab.length) {
                    $activeTab.addClass('completed');
                    showTab($nextTab);
                    $('html, body').animate({ scrollTop: $step.offset().top - 50 }, 300);
                    return;
                }
                $activeTab.addClass('completed');
            }

            // 3. Leaving the step: check every pane, including any the applicant
            //    skipped past by clicking a tab directly.
            var stepInvalid = validateContainer($step);
            if (stepInvalid) {
                notify('error', invalidMessage(stepInvalid));
                revealField(stepInvalid);
                return;
            }

            // 4. "Save and continue" saves first, and only continues if the
            //    save was accepted. It used to advance on .always(), so a draft
            //    the server rejected — a date of birth in the future, say — still
            //    moved the applicant to the next step, leaving the bad value
            //    behind them and unsaved.
            $button.prop('disabled', true);

            var advance = function () {
                $button.prop('disabled', false);
                goToIndex(currentIndex + 1);
            };

            var stayPut = function (message, fieldName) {
                $button.prop('disabled', false);
                notify('error', message);

                // Put the applicant back on the field the server objected to,
                // wherever it lives, rather than just naming it.
                if (fieldName) {
                    var $field = $form.find('[name="' + fieldName + '"]').first();
                    if ($field.length) {
                        $field.addClass('is-invalid');
                        revealField($field[0]);
                    }
                }
            };

            var pending = saveDraftQuietly();
            if (!pending) {
                advance();
                return;
            }

            pending.done(function (response) {
                // The endpoint answers 200 with success:false for problems it
                // handles itself, so a resolved promise is not proof of a save.
                if (response && response.success === false) {
                    stayPut(response.message || '{{ __('Your answers could not be saved. Please check them and try again.') }}');
                    return;
                }
                advance();
            }).fail(function (xhr) {
                var payload = xhr.responseJSON || {};
                var errors = payload.errors || {};
                var firstField = Object.keys(errors)[0] || null;
                var message = firstField
                    ? [].concat(errors[firstField])[0]
                    : (payload.message || '{{ __('Your answers could not be saved. Please check them and try again.') }}');

                stayPut(message, firstField);
            });
        });

        $('.btn-prev').on('click', function () {
            var $step = $('#' + stepIdAt(currentIndex));
            var $activeTab = $step.find('.step-tabs:not([data-status-exempt]) .nav-link.active');

            if ($activeTab.length) {
                var $prevTab = $activeTab.parent().prevAll('.nav-item').first().find('.nav-link');
                if ($prevTab.length) {
                    showTab($prevTab);
                    $('html, body').animate({ scrollTop: $step.offset().top - 50 }, 300);
                    return;
                }
            }

            goToIndex(currentIndex - 1);
        });

        $('.wizard-nav .nav-link').on('click', function () {
            var target = indexOfStepId('step-' + $(this).data('step'));

            if (target === -1 || target === currentIndex) {
                return;
            }
            if (target <= highestIndexReached) {
                goToIndex(target);
            } else {
                notify('warning', 'Please complete the preceding steps before skipping ahead.');
            }
        });

        /* ---- Declaration and submission gate --------------------------- */

        // #agree_terms is the declaration: named, required, and validated on the
        // server. #declaration-checkbox was an unnamed duplicate of it that has
        // been removed; the lookup stays only so an older cached view cannot
        // throw, and every use below is length-guarded.
        var $declaration = $('#declaration-checkbox');
        var $agreeTerms = $('#agree_terms');

        // A degree type can switch the declaration off entirely, in which case
        // there is nothing to tick — the gate must open rather than lock the
        // applicant out of the step that follows.
        var declarationRequired = $agreeTerms.length > 0 || $declaration.length > 0;
        var $submitButton = $('#submit-application-btn');

        var $continueToPayment = $('#continue-to-payment-btn');

        function syncDeclaration(checked) {
            if ($declaration.length) {
                $declaration.prop('checked', checked);
            }
            if ($agreeTerms.length) {
                $agreeTerms.prop('checked', checked).toggleClass('is-invalid', false);
            }

            // Confirming the declaration lets the applicant reach the payment step;
            // actually submitting additionally requires a settled fee.
            $continueToPayment.prop('disabled', !checked);
            $submitButton.prop('disabled', !checked || feeRequired);
        }

        $declaration.on('change', function () {
            syncDeclaration($(this).is(':checked'));
        });
        $agreeTerms.on('change', function () {
            syncDeclaration($(this).is(':checked'));
        });

        // Restore the gate after a failed submit repopulated the form. With no
        // declaration configured there is nothing to restore and nothing to
        // withhold, so the gate opens.
        syncDeclaration(
            !declarationRequired
                || ($agreeTerms.length ? $agreeTerms.is(':checked') : $declaration.is(':checked'))
        );

        if (feeRequired) {
            // The Payment step already explains the outstanding balance in full,
            // so this only needs to keep the button locked and say why on hover.
            $submitButton
                .prop('disabled', true)
                .attr('title', "{{ __('Please pay your admission fee before submitting your application.') }}");
        }

        /* ---- Final gate: nothing incomplete reaches the server ---------- */

        $form.on('submit', function (event) {
            var invalid = validateContainer($steps);
            if (!invalid) {
                return;
            }

            event.preventDefault();
            // Keep the auto-save module's submit handler from clearing the
            // unsaved-changes flag on a submission that is not going through.
            event.stopImmediatePropagation();

            var ownerIndex = indexOfStepId($(invalid).closest('.wizard-step').attr('id'));
            if (ownerIndex > -1) {
                goToIndex(ownerIndex);
            }

            notify('error', 'Please complete "' + labelFor(invalid) + '" before submitting.');
            revealField(invalid);
        });

        /* ---- Review summary -------------------------------------------- */

        /** Current display value of a named field, resolved across input types. */
        function displayValue(name) {
            var $fields = $form.find('[name="' + name + '"]');
            if (!$fields.length) {
                return null;
            }

            var element = $fields[0];

            if (element.type === 'radio' || element.type === 'checkbox') {
                var $checked = $fields.filter(':checked');
                if (!$checked.length) {
                    return '';
                }
                var text = trim($checked.closest('label').text());
                return text || $checked.val();
            }

            if (element.tagName === 'SELECT') {
                return trim($fields.find('option:selected').text());
            }

            if (element.type === 'file') {
                if (element.files && element.files.length) {
                    return element.files[0].name;
                }
                return String($fields.attr('data-has-existing')) === '1' ? 'Already uploaded' : '';
            }

            return trim($fields.val() || '');
        }

        function buildSection(title, rows, stepId) {
            if (!rows.length) {
                return '';
            }

            var index = indexOfStepId(stepId);
            var html = '<div class="review-section"><div class="review-section-header"><h5>' +
                escapeHtml(title) + '</h5>';

            if (index > -1) {
                html += '<button type="button" class="btn btn-sm btn-link edit-step-btn" data-step-id="' +
                    escapeHtml(stepId) + '">Edit</button>';
            }

            html += '</div><div class="review-section-body"><div class="row">';

            rows.forEach(function (row) {
                var value = row.value === null || row.value === undefined || row.value === ''
                    ? '<span class="text-muted">Not provided</span>'
                    : escapeHtml(row.value);

                html += '<div class="col-md-4 review-item"><span class="review-label">' +
                    escapeHtml(row.label) + '</span><span class="review-value">' + value + '</span></div>';
            });

            return html + '</div></div></div>';
        }

        /** Rows for named fields, skipping any the degree type did not render. */
        function fieldRows(pairs) {
            var rows = [];
            pairs.forEach(function (pair) {
                var value = displayValue(pair[0]);
                if (value !== null) {
                    rows.push({ label: pair[1], value: value });
                }
            });
            return rows;
        }

        /** Rows summarising a repeater, one line per entry. */
        function repeaterRows($items, label, describe) {
            var rows = [];
            $items.each(function (index) {
                var text = describe($(this));
                if (text) {
                    rows.push({ label: label + ' ' + (index + 1), value: text });
                }
            });
            return rows;
        }

        function generateReviewSummary() {
            var html = '';

            html += buildSection("Applicant's Information", fieldRows([
                ['first_name', 'First Name'],
                ['last_name', 'Last Name'],
                ['gender', 'Gender'],
                ['dob', 'Date of Birth'],
                ['nationality', 'Nationality'],
                ['birth_city', 'City of Birth'],
                ['birth_division', 'Division of Birth'],
                ['birth_region', 'Region of Birth'],
                ['birth_country', 'Country of Birth'],
                ['religion', 'Religion'],
                ['mother_tongue', 'Mother Tongue'],
                ['national_id', 'National ID'],
                ['passport_no', 'Passport No.']
            ]), 'step-1');

            html += buildSection('Contact & Address', fieldRows([
                ['country', 'Country of Residence'],
                ['present_province', 'Current Province'],
                ['present_district', 'Current District'],
                ['present_village', 'Current Village'],
                ['present_address', 'Current Address'],
                ['permanent_province', 'Permanent Province'],
                ['permanent_district', 'Permanent District'],
                ['permanent_address', 'Permanent Address'],
                ['phone', 'Phone'],
                ['alternate_phone', 'Alternate Phone'],
                ['email', 'Email']
            ]), 'step-1');

            // Programme choices are read-only mirrors, so read them off the labels.
            var programmeRows = [];
            $('#step-4 .is-mirrored').each(function () {
                var $input = $(this);
                programmeRows.push({
                    label: trim($input.closest('.form-group').find('label').first().text()),
                    value: trim($input.val() || '')
                });
            });
            html += buildSection('Programme Choices', programmeRows, 'step-4');

            html += buildSection('Family & Financial Support',
                repeaterRows($('#guardianRepeater .guardian-item'), 'Guardian', function ($item) {
                    var name = trim($item.find('[name*="[full_name]"]').val() || '');
                    var type = trim($item.find('[name*="[type]"]').val() || '');
                    var phone = trim($item.find('[name*="[phone_primary]"]').val() || '');
                    if (!name) {
                        return '';
                    }
                    return name + (type ? ' (' + type + ')' : '') + (phone ? ' — ' + phone : '');
                }), 'step-2');

            html += buildSection('Academic Qualifications',
                repeaterRows($('#academicHistoryRepeater .academic-item'), 'Qualification', function ($item) {
                    var certificate = trim($item.find('[name*="[certificate_obtained]"]').val() || '');
                    var name = trim($item.find('[name*="[institution_name]"]').val() || '');
                    var endYear = trim($item.find('[name*="[end_year]"]').val() || '');
                    // Lead with the qualification: it is what the card is about,
                    // and the school is the supporting detail.
                    if (!certificate && !name) {
                        return '';
                    }
                    return (certificate || name)
                        + (certificate && name ? ' — ' + name : '')
                        + (endYear ? ' (' + endYear + ')' : '');
                }), 'step-3');

            html += buildSection('Languages',
                repeaterRows($('#languageRepeater .language-item'), 'Language', function ($item) {
                    var language = trim($item.find('[name*="[language]"]').val() || '');
                    var fluency = $item.find('[name*="[fluency_level]"] option:selected').text();
                    if (!language) {
                        return '';
                    }
                    return language + (trim(fluency) ? ' — ' + trim(fluency) : '');
                }), 'step-5');

            var documentRows = fieldRows([
                ['photo', 'Passport Photograph'],
                ['signature', 'Signature']
            ]);
            $('.document-input').each(function () {
                var $input = $(this);
                var label = trim($input.closest('.form-group, td, div').find('label').first().text().replace(/\*/g, ''));
                var value;

                if (this.files && this.files.length) {
                    value = this.files[0].name;
                } else if (String($input.attr('data-has-existing')) === '1') {
                    value = 'Already uploaded';
                } else {
                    value = '';
                }

                documentRows.push({ label: label || $input.data('document-key'), value: value });
            });
            html += buildSection('Documents', documentRows, 'step-6');

            html += buildSection('Declaration', fieldRows([
                ['declaration_name', 'Full Name of Applicant'],
                ['declaration_signed_date', 'Date']
            ]), 'step-6');

            $('#review-summary-container').html(html);
        }

        // Delegated so it survives every regeneration of the summary.
        $('#review-summary-container').on('click', '.edit-step-btn', function () {
            goToIndex(indexOfStepId($(this).data('step-id')));
        });

        // The payment step lists what is still outstanding before an applicant is
        // allowed to pay; each entry jumps to the step that would fix it.
        $(document).on('click', '.go-to-step', function () {
            goToIndex(indexOfStepId('step-' + $(this).data('step')));
        });

        /* ---- Live readiness of the payment step -------------------------- */

        /**
         * Re-ask the server what is still outstanding, and show the payment
         * controls or the outstanding list accordingly.
         *
         * The step is rendered with both, because the answer decided when the
         * page loaded stops being true the moment the applicant fills anything
         * in — the form saves over AJAX and never reloads. Without this the step
         * kept naming details that had already been supplied, and only a manual
         * refresh would let them pay.
         */
        function refreshPaymentReadiness() {
            var $blocked = $('#payment-blocked');
            var $controls = $('#payment-controls');

            if (!$blocked.length && !$controls.length) {
                return;   // fee already settled, or no fee for this degree type
            }

            $.getJSON("{{ route('application.readiness', $application) }}")
                .done(function (data) {
                    if (!data) { return; }

                    $blocked.toggleClass('d-none', !!data.complete);
                    $controls.toggleClass('d-none', !data.complete);

                    if (data.complete) {
                        // The fee is raised at this moment, not when the form was
                        // opened, so the step was rendered without one. Adopt it
                        // now rather than making the applicant reload to find a
                        // payment button that works.
                        if (data.fee_id) {
                            $('#step-8').attr('data-fee-id', data.fee_id).data('fee-id', data.fee_id);
                        }
                        if (data.balance !== null && data.balance !== undefined) {
                            $('#fee-balance-display').text(Number(data.balance).toLocaleString() + ' FCFA');
                        }
                        return;
                    }

                    // Rebuild the list so it names what is outstanding *now*.
                    var $list = $('#payment-blockers-list');
                    if (!$list.length) { return; }

                    $list.empty();
                    $.each(data.missing || [], function (_, item) {
                        $list.append(
                            $('<li class="list-group-item d-flex justify-content-between align-items-center"></li>')
                                .append($('<span></span>')
                                    .append('<i class="fas fa-circle-exclamation text-warning me-2"></i>')
                                    .append($('<span></span>').text(item.label)))
                                .append($('<button type="button" class="btn btn-sm btn-outline-primary go-to-step"></button>')
                                    .attr('data-step', item.step)
                                    .text("{{ __('Go there') }}"))
                        );
                    });
                });
        }

        // Whenever the payment step comes into view, and again after any save,
        // so finishing a field on an earlier step unlocks payment without a reload.
        $(document).on('wizard:step-shown', function (event, stepId) {
            if (stepId === 'step-8') {
                refreshPaymentReadiness();
            }
        });

        $(document).on('application:draft-saved', function () {
            if (stepIdAt(currentIndex) === 'step-8') {
                refreshPaymentReadiness();
            }
        });

        /* ---- Boot ------------------------------------------------------- */

        // Keep the status badge in step with tab changes, however the tab was
        // switched (Bootstrap event, or our own fallback in showTab()).
        $(document).on('shown.bs.tab', '.step-tabs .nav-link', refreshStepStatus);
        $(document).on('click', '.step-tabs .nav-link', function () {
            window.setTimeout(refreshStepStatus, 0);
        });

        // Let the auto-save module refresh the badge after progress changes.
        window.PaxApplicationWizard = { refreshStatus: refreshStepStatus };

        paintStaticLabels();
        updateWizardUI();
    });
    </script>
    
<script src="{{ asset('dashboard/plugins/jquery-validation/js/jquery.validate.min.js') }}"></script>

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

        // Country options for rows added in the browser, built from the same
        // list the server-rendered selects use so the two cannot diverge.
        @include('partials.country-options-js')
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
                    validator.settings.ignore = ":hidden,:disabled";

                    if (!$form.valid()) {
                        if (typeof validator.focusInvalid === 'function') {
                            validator.focusInvalid();
                        }
                        if (window.console && validator.errorList) {
                            console.warn('Application form invalid fields:', validator.errorList.map(function(e){ return { name: e.element.name, message: e.message }; }));
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

            let initialIndex = 0;
            if (typeof $form.steps === 'function') {
                const indexCandidate = $form.steps('getCurrentIndex');
                if (Number.isFinite(indexCandidate)) {
                    initialIndex = indexCandidate;
                }
            }
            toggleFinishButton(Number.isInteger(initialIndex) ? initialIndex : 0);

            @if(!empty($admissionFeeRequired))
                // Disable the wizard's built-in Submit/Finish button while the admission fee is unpaid.
                const $finishLink = $form.find('.actions a[href="#finish"]');
                if ($finishLink.length) {
                    const disabledLabel = "{{ __('Submit Application — Awaiting Fee Payment') }}";
                    $finishLink.text(disabledLabel)
                        .attr('aria-disabled', 'true')
                        .attr('title', "{{ __('Please pay your admission fee before submitting your application.') }}")
                        .css({ 'pointer-events': 'none', 'opacity': '0.55', 'cursor': 'not-allowed' })
                        .closest('li').addClass('disabled');
                }
            @endif

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
                            <select class="form-control" name="guardians[${index}][country]">${countryOptionsHtml()}</select>
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

        // Mirrors resources/views/application/partials/qualification-card.blade.php
        // for an applicant-added qualification: same field names, no configured
        // document slots, so it falls back to its own certificate upload.
        const academicTemplate = () => {
            const index = $(academicRepeaterSelector + ' .academic-item').length;
            const thisYear = new Date().getFullYear();
            let years = '<option value="">{{ __('Select') }}</option>';
            for (let y = thisYear + 1; y >= thisYear - 60; y--) {
                years += '<option value="' + y + '">' + y + '</option>';
            }

            return `
                <div class="repeater-item academic-item qualification-card" data-index="${index}" data-qualification-key="">
                    <div class="repeater-actions">
                        <button type="button" class="remove-academic" aria-label="{{ __('Remove qualification') }}">&times;</button>
                    </div>
                    <div class="qualification-card-header">
                        <h5 class="qualification-card-title">{{ __('Additional qualification') }}</h5>
                    </div>
                    <input type="hidden" name="academic_history[${index}][qualification_key]" value="">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Certificate / Qualification') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][certificate_obtained]">
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ __('Awarding body') }}</label>
                            <input type="text" class="form-control awarding-body-input" name="academic_history[${index}][awarding_body]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('School / Institution attended') }}</label>
                            <input type="text" class="form-control institution-name-input" name="academic_history[${index}][institution_name]">
                            <div class="form-check mt-2">
                                <input type="hidden" name="academic_history[${index}][institution_same_as_awarding_body]" value="0">
                                <input type="checkbox" class="form-check-input same-as-awarding" value="1"
                                       name="academic_history[${index}][institution_same_as_awarding_body]"
                                       id="same_as_awarding_${index}">
                                <label class="form-check-label" for="same_as_awarding_${index}">{{ __('Same as awarding body') }}</label>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ __('Language of instruction') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][instruction_language]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Country where studied') }}</label>
                            <select class="form-control" name="academic_history[${index}][country]">${countryOptionsHtml()}</select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ __('City / Town / Village where studied') }}</label>
                            <input type="text" class="form-control" name="academic_history[${index}][city]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>{{ __('Start year') }}</label>
                            <select class="form-control" name="academic_history[${index}][start_year]">${years}</select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ __('Completion year') }}</label>
                            <select class="form-control" name="academic_history[${index}][end_year]">${years}</select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Upload certificate or result slip') }} <small class="text-muted">{{ __('Optional') }}</small></label>
                        <input type="hidden" name="academic_history[${index}][existing_certificate_file]" value="">
                        <input type="file" class="form-control size-guard" data-max-size-mb="10" name="academic_history[${index}][certificate_file]" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                        <small class="document-help">{{ __('PDF, JPG or PNG. Maximum file size 10 MB.') }}</small>
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
                // Cap matches the note beside the button and the server side.
                if ($container.find('.academic-item').length >= 5) {
                    return;
                }
                $container.append(academicTemplate());
                refreshAcademicControls();
            });

            $container.on('click', '.remove-academic', function() {
                // Prescribed cards have no remove button, but guard anyway so a
                // required qualification can never be deleted from the DOM.
                var $item = $(this).closest('.academic-item');
                if ($item.data('qualification-key')) {
                    return;
                }
                $item.remove();
                refreshAcademicControls();
            });

            // Mirror the awarding body into the institution field on request.
            $container.on('change', '.same-as-awarding', function() {
                var $card = $(this).closest('.academic-item');
                var $institution = $card.find('.institution-name-input');
                if (this.checked) {
                    $institution.val($card.find('.awarding-body-input').val()).prop('readonly', true);
                } else {
                    $institution.prop('readonly', false);
                }
            });

            $container.on('input', '.awarding-body-input', function() {
                var $card = $(this).closest('.academic-item');
                if ($card.find('.same-as-awarding').is(':checked')) {
                    $card.find('.institution-name-input').val($(this).val());
                }
            });

            refreshAcademicControls();
        };

        /** Hide the add button once the cap is reached. */
        const refreshAcademicControls = () => {
            const $container = $(academicRepeaterSelector);
            const atCap = $container.find('.academic-item').length >= 5;
            $('#addAcademicHistory').prop('disabled', atCap).toggleClass('disabled', atCap);
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

        // A file input only needs sending when its current selection has not
        // already been persisted by an earlier save. Without this, every
        // auto-save re-uploaded the photo, signature and every document,
        // orphaning a fresh copy on disk each time.
        const fileSignature = (file) => file.name + ':' + file.size + ':' + file.lastModified;

        const filePending = (input) => {
            if (!input.files || !input.files.length) {
                return false;
            }
            return input.dataset.savedSignature !== fileSignature(input.files[0]);
        };

        const markFilesSaved = () => {
            $('#hnd-application-form').find('input[type="file"]').each(function() {
                if (this.files && this.files.length) {
                    this.dataset.savedSignature = fileSignature(this.files[0]);
                }
            });
        };

        // Get form data as FormData object
        const getFormData = () => {
            const $form = $('#hnd-application-form');
            const formData = new FormData();

            $form.find('input, select, textarea').each(function() {
                // Payment-step controls belong to the fee endpoints, not to the
                // application draft — never ship them with a save.
                if (!this.name || this.disabled || this.hasAttribute('data-skip-autosave')) {
                    return;
                }
                if (this.type === 'file') {
                    if (filePending(this)) {
                        formData.append(this.name, this.files[0]);
                    }
                    return;
                }
                if ((this.type === 'checkbox' || this.type === 'radio') && !this.checked) {
                    return;
                }
                formData.append(this.name, $(this).val());
            });

            return formData;
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

        // Update progress display. The header bar and the sidebar bar show the
        // same server-calculated completion so they can never disagree.
        const updateProgress = (progress) => {
            if (typeof progress !== 'number') {
                return;
            }
            $('#draft-progress-text').text(progress + '%');
            $('#draft-progress-bar').css('width', progress + '%');
            $('#sidebar-progress-text').text(progress + '% complete');
            $('#sidebar-progress-bar').css('width', progress + '%');

            if (window.PaxApplicationWizard && typeof window.PaxApplicationWizard.refreshStatus === 'function') {
                window.PaxApplicationWizard.refreshStatus();
            }
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
                    // The save completed the last outstanding item on an
                    // application whose fee was already paid, so the server
                    // submitted it. Reload rather than leave an editable wizard
                    // standing over an application that is no longer a draft.
                    if (response.auto_submitted) {
                        formChanged = false;
                        toastr.success(response.message, '{{ __("Application Submitted") }}');
                        setTimeout(function () { window.location.reload(); }, 1500);
                        return;
                    }

                    updateAutoSaveStatus('saved');
                    updateLastSavedTime(response.last_saved);
                    updateProgress(response.progress);
                    markFilesSaved();
                    lastSavedData = getFormDataAsString();

                    // Anything that depends on saved data — the payment step's
                    // list of outstanding items — can now re-ask.
                    $(document).trigger('application:draft-saved', [response]);

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

        // Get form data as string for comparison. File selections are folded in
        // as signatures so that picking a new document also counts as a change.
        const getFormDataAsString = () => {
            const $form = $('#hnd-application-form');
            const scalars = $form.find('input:not([type="file"]), select, textarea').serialize();
            const files = $form.find('input[type="file"]').map(function() {
                return this.name + '=' + (filePending(this) ? fileSignature(this.files[0]) : '');
            }).get().join('&');

            return scalars + '|' + files;
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

        // Exposed so the wizard's "Save and continue" button persists the draft
        // before advancing, instead of only navigating.
        window.PaxApplicationDraft = {
            save: saveDraft,
            isDirty: () => formChanged
        };
    })(jQuery);
</script>

{{-- ===================================================================
   | Payment step (step 8)
   |
   | Mobile Money mirrors the dashboard fee modal so both entry points behave
   | identically. The receipt upload posts over AJAX because the wizard is one
   | large <form> and HTML forbids nesting another inside it.
   |=================================================================== --}}
<script type="text/javascript">
    "use strict";
    (function ($) {
        const $step = $('#step-8');
        if (!$step.length) {
            return;
        }

        const CSRF = $('meta[name="csrf-token"]').attr('content') || $('input[name=_token]').val();
        const MOMO_BASE = "{{ url('payment/momo') }}";
        const RECEIPT_URL = "{{ route('application.admission-fee.upload', $application) }}";
        const feeId = $step.data('fee-id');
        const applicationId = $step.data('application-id');

        const showStatus = (el, level, message) => {
            if (!el) { return; }
            el.className = 'momo-status alert alert-' + level + ' mt-3';
            el.textContent = message;
        };

        /* ---- Mobile Money ---------------------------------------------- */

        const pollStatus = (provider, reference, statusBox, btn, intervalSec, timeoutSec) => {
            const started = Date.now();

            const retryOrGiveUp = () => {
                if ((Date.now() - started) / 1000 >= timeoutSec) {
                    btn.disabled = false;
                    showStatus(statusBox, 'warning', "{{ __('Still waiting for the provider. Refresh this page in a moment to check again.') }}");
                    return;
                }
                setTimeout(tick, intervalSec * 1000);
            };

            const tick = () => {
                fetch(MOMO_BASE + '/' + provider + '/status/' + encodeURIComponent(reference))
                    .then(r => r.json())
                    .then(function (data) {
                        if (!data.ok) { retryOrGiveUp(); return; }
                        if (data.status === 'successful') {
                            showStatus(statusBox, 'success', "{{ __('Payment successful. Reloading so you can submit...') }}");
                            setTimeout(() => window.location.reload(), 1500);
                            return;
                        }
                        if (data.status === 'failed' || data.status === 'timeout') {
                            btn.disabled = false;
                            showStatus(statusBox, 'danger', data.reason || "{{ __('Payment did not complete.') }}");
                            return;
                        }
                        retryOrGiveUp();
                    })
                    .catch(retryOrGiveUp);
            };

            tick();
        };

        $step.on('click', '.momo-pay-btn', function () {
            const btn = this;
            const provider = $(btn).data('provider');
            const tabPane = btn.closest('.tab-pane');
            const statusBox = tabPane ? tabPane.querySelector('.momo-status') : null;
            const msisdnInput = tabPane ? tabPane.querySelector('.momo-msisdn') : null;

            if (!feeId) {
                showStatus(statusBox, 'danger', "{{ __('No admission fee is attached to this application.') }}");
                return;
            }

            const payload = { fee_id: feeId, application_id: applicationId };
            if (provider === 'mtn') {
                const msisdn = ((msisdnInput && msisdnInput.value) || '').trim();
                if (!msisdn) {
                    showStatus(statusBox, 'warning', "{{ __('Please enter the phone number.') }}");
                    return;
                }
                payload.msisdn = msisdn;
            }

            btn.disabled = true;
            showStatus(statusBox, 'info', "{{ __('Contacting payment provider...') }}");

            fetch(MOMO_BASE + '/' + provider + '/initiate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload),
            }).then(async function (r) {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('HTTP ' + r.status + ' — ' + (text.substring(0, 200) || 'empty response'));
                }
            }).then(function (data) {
                if (!data.ok) {
                    btn.disabled = false;
                    showStatus(statusBox, 'danger', data.error || "{{ __('Unable to start payment.') }}");
                    return;
                }
                if (provider === 'orange' && data.payment_url) {
                    showStatus(statusBox, 'info', "{{ __('Redirecting to Orange Money...') }}");
                    window.location.href = data.payment_url;
                    return;
                }
                showStatus(statusBox, 'info', "{{ __('Approve the request on your phone. Waiting for confirmation...') }}");
                pollStatus(provider, data.reference, statusBox, btn, data.poll_interval || 3, data.poll_timeout || 90);
            }).catch(function (err) {
                btn.disabled = false;
                showStatus(statusBox, 'danger', (err && err.message) ? err.message : "{{ __('Network error. Please try again.') }}");
            });
        });

        /* ---- Manual receipt upload ------------------------------------- */

        const $receiptStatus = $('#receipt-upload-status');

        const showReceiptStatus = (level, message) => {
            $receiptStatus.attr('class', 'alert alert-' + level).text(message).removeClass('d-none');
        };

        $('#upload-receipt-btn').on('click', function () {
            const $btn = $(this);
            const file = $('#pay_receipt_file')[0].files[0];
            const required = [
                ['#pay_payment_date', "{{ __('Enter the payment date.') }}"],
                ['#pay_amount', "{{ __('Enter the amount paid.') }}"],
                ['#pay_payment_reference', "{{ __('Enter the payment reference.') }}"],
            ];

            for (const [selector, message] of required) {
                if (!String($(selector).val() || '').trim()) {
                    $(selector).addClass('is-invalid').trigger('focus');
                    showReceiptStatus('warning', message);
                    return;
                }
                $(selector).removeClass('is-invalid');
            }

            if (!file) {
                $('#pay_receipt_file').addClass('is-invalid');
                showReceiptStatus('warning', "{{ __('Attach the proof of payment.') }}");
                return;
            }
            $('#pay_receipt_file').removeClass('is-invalid');

            const payload = new FormData();
            payload.append('_token', CSRF);
            payload.append('payment_date', $('#pay_payment_date').val());
            payload.append('amount', $('#pay_amount').val());
            payload.append('payment_reference', $('#pay_payment_reference').val());
            payload.append('payment_method', $('#pay_payment_method').val());
            payload.append('student_note', $('#pay_student_note').val());
            payload.append('receipt_file', file);

            $btn.prop('disabled', true);
            showReceiptStatus('info', "{{ __('Uploading your receipt...') }}");

            $.ajax({
                url: RECEIPT_URL,
                method: 'POST',
                data: payload,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'Accept': 'application/json' },
            }).done(function (response) {
                if (response && response.success) {
                    showReceiptStatus('success', response.message);
                    // A verified fee unlocks submission; a pending one does not,
                    // so reload to pick up whichever state the server recorded.
                    setTimeout(() => window.location.reload(), 1800);
                    return;
                }
                $btn.prop('disabled', false);
                showReceiptStatus('danger', (response && response.message) || "{{ __('Upload failed. Please try again.') }}");
            }).fail(function (xhr) {
                $btn.prop('disabled', false);
                let message = "{{ __('Upload failed. Please try again.') }}";
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) { message = xhr.responseJSON.message; }
                    if (xhr.responseJSON.errors) {
                        message += ': ' + Object.values(xhr.responseJSON.errors).flat().slice(0, 2).join(', ');
                    }
                }
                showReceiptStatus('danger', message);
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

    @include('components.chat-widget')
</body>
</html>
