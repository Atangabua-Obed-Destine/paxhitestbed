@extends('admin.layouts.master')
@section('title', $title)
@section('page_css')
<link rel="stylesheet" href="{{ asset('dashboard/plugins/lightbox2-master/css/lightbox.min.css') }}">
<style>
    .timeline-item {
        position: relative;
        padding-left: 1.75rem;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        top: .25rem;
        left: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #3f51b5;
    }
    .timeline-item::after {
        content: '';
        position: absolute;
        top: 1.25rem;
        left: 4px;
        width: 2px;
        height: calc(100% - 1.25rem);
        background: rgba(63, 81, 181, 0.2);
    }
    .timeline-item:last-child::after {
        display: none;
    }
    .detail-table th {
        width: 42%;
        white-space: nowrap;
    }
    .detail-table td {
        width: 58%;
    }
    .document-description {
        max-width: 360px;
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

    $dateFormat = config('app.date_format', 'Y-m-d');
    $formatDate = function ($date) use ($dateFormat): ?string {
        if (!$date) {
            return null;
        }

        if ($date instanceof \Carbon\CarbonInterface) {
            return $date->format($dateFormat);
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->format($dateFormat);
        } catch (\Throwable $e) {
            return (string) $date;
        }
    };

    $formatDateTime = function ($dateTime) use ($dateFormat): ?string {
        if (!$dateTime) {
            return null;
        }

        if ($dateTime instanceof \Carbon\CarbonInterface) {
            return $dateTime->format($dateFormat.' H:i');
        }

        try {
            return \Illuminate\Support\Carbon::parse($dateTime)->format($dateFormat.' H:i');
        } catch (\Throwable $e) {
            return (string) $dateTime;
        }
    };

    $valueOrNA = function ($value): string {
        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format($dateFormat);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return filled($value) ? (string) $value : __('N/A');
    };

    $documentRequirements = $documentRequirements ?? [];
    $documentMap = collect($row->documents ?? [])->keyBy('document_type');
    $statusBadges = [
        1 => ['badge-primary', __('status_pending')],
        2 => ['badge-success', __('status_approved')],
        0 => ['badge-danger', __('status_rejected')],
    ];
    $boardReview = $row->boardReview;
@endphp

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row mb-4">
            @include('admin.application.partials.profile-overview', ['row' => $row, 'path' => $path, 'setting' => $setting ?? null])
        </div>

        <div class="row mb-4">
            @include('admin.application.partials.timeline', ['row' => $row, 'timeline' => $timeline, 'showUpdateForm' => true])
        </div>

        <div class="row mb-4">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('Application overview') }}</h5>
                        <span class="badge badge-info">{{ $row->progress_label }}</span>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-borderless table-sm mb-0 detail-table">
                                <tbody>
                                    <tr>
                                        <th>{{ __('Registration No.') }}</th>
                                        <td>#{{ $row->registration_no }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Applied on') }}</th>
                                        <td>{{ $formatDate($row->apply_date) ?? __('N/A') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Current stage') }}</th>
                                        <td><span class="badge badge-primary">{{ $row->progress_label }}</span></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Decision status') }}</th>
                                        <td>
                                            @php [$badgeClass, $badgeLabel] = $statusBadges[$row->status] ?? ['badge-secondary', __('status_pending')]; @endphp
                                            <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                        </td>
                                    </tr>
                                    @if($row->batch)
                                    <tr>
                                        <th>{{ __('Batch') }}</th>
                                        <td>{{ $row->batch->title }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('Programme selected') }}</th>
                                        <td>{{ $row->program->title ?? __('N/A') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('First choice') }}</th>
                                        <td>{{ optional($row->preferredProgramFirst)->title ?? __('N/A') }}</td>
                                    </tr>
                                    @if($fieldEnabled('application_program_choice_second'))
                                    <tr>
                                        <th>{{ __('Second choice') }}</th>
                                        <td>{{ optional($row->preferredProgramSecond)->title ?? __('N/A') }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_program_choice_third'))
                                    <tr>
                                        <th>{{ __('Third choice') }}</th>
                                        <td>{{ optional($row->preferredProgramThird)->title ?? __('N/A') }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_academic_year'))
                                    <tr>
                                        <th>{{ __('Academic year') }}</th>
                                        <td>{{ $valueOrNA($row->academic_year) }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_registration_fee_bank'))
                                    <tr>
                                        <th>{{ __('Registration fee bank') }}</th>
                                        <td>{{ $valueOrNA($row->registration_fee_bank) }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_registration_fee_reference'))
                                    <tr>
                                        <th>{{ __('Registration fee reference') }}</th>
                                        <td>{{ $valueOrNA($row->registration_fee_reference) }}</td>
                                    </tr>
                                    @endif
                                    @if($row->admissionFee)
                                    <tr>
                                        <th>{{ __('Admission Fee Status') }}</th>
                                        <td>
                                            {!! $row->admissionFee->status_badge !!}
                                            <br>
                                            <small class="text-muted">
                                                Amount: <strong>{{ number_format($row->admissionFee->fee_amount, $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol ?? '' !!}</strong> | 
                                                Paid: <strong>{{ number_format($row->admissionFee->paid_amount, $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol ?? '' !!}</strong> | 
                                                Balance: <strong>{{ number_format($row->admissionFee->remaining_balance, $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol ?? '' !!}</strong>
                                            </small>
                                        </td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('Portal last login') }}</th>
                                        <td>{{ $formatDateTime($row->portal_last_login_at) ?? __('Never') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Application completed at') }}</th>
                                        <td>{{ $formatDateTime($row->completed_at) ?? __('N/A') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Contact & communication') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-borderless table-sm mb-0 detail-table">
                                <tbody>
                                    <tr>
                                        <th>{{ __('Email') }}</th>
                                        <td>
                                            @if($row->email)
                                                <a href="mailto:{{ $row->email }}">{{ $row->email }}</a>
                                            @else
                                                {{ __('N/A') }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Primary phone') }}</th>
                                        <td>
                                            @if($row->phone)
                                                <a href="tel:{{ $row->phone }}">{{ $row->phone }}</a>
                                            @else
                                                {{ __('N/A') }}
                                            @endif
                                        </td>
                                    </tr>
                                    @if($fieldEnabled('application_alternate_phone'))
                                    <tr>
                                        <th>{{ __('Alternate phone') }}</th>
                                        <td>
                                            @if($row->alternate_phone)
                                                <a href="tel:{{ $row->alternate_phone }}">{{ $row->alternate_phone }}</a>
                                            @else
                                                {{ __('N/A') }}
                                            @endif
                                        </td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_emergency_phone'))
                                    <tr>
                                        <th>{{ __('Emergency phone') }}</th>
                                        <td>{{ $valueOrNA($row->emergency_phone) }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('Mother tongue') }}</th>
                                        <td>{{ $valueOrNA($row->mother_tongue) }}</td>
                                    </tr>
                                    @if($fieldEnabled('application_instruction_language_secondary'))
                                    <tr>
                                        <th>{{ __('Secondary instruction language') }}</th>
                                        <td>{{ $valueOrNA($row->instruction_language_secondary) }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_postal_address'))
                                    <tr>
                                        <th>{{ __('Postal address') }}</th>
                                        <td>
                                            @php
                                                $postalParts = array_filter([
                                                    $row->postal_address_line1,
                                                    $row->postal_address_line2,
                                                ]);
                                            @endphp
                                            @if(count($postalParts))
                                                {{ implode(', ', $postalParts) }}
                                            @else
                                                {{ __('N/A') }}
                                            @endif
                                        </td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('Portal email verified') }}</th>
                                        <td>{{ $valueOrNA($row->email_verified_at ? __('Yes') : __('No')) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Identity details') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-borderless table-sm mb-0 detail-table">
                                <tbody>
                                    @if($fieldEnabled('application_other_names'))
                                    <tr>
                                        <th>{{ __('Other names') }}</th>
                                        <td>{{ $valueOrNA($row->other_names) }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('Gender') }}</th>
                                        <td>
                                            @if($row->gender == 1)
                                                {{ __('gender_male') }}
                                            @elseif($row->gender == 2)
                                                {{ __('gender_female') }}
                                            @elseif($row->gender == 3)
                                                {{ __('gender_other') }}
                                            @else
                                                {{ __('N/A') }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Date of birth') }}</th>
                                        <td>{{ $formatDate($row->dob) ?? __('N/A') }}</td>
                                    </tr>
                                    @if($fieldEnabled('application_birth_city'))
                                    <tr>
                                        <th>{{ __('Birth city') }}</th>
                                        <td>{{ $valueOrNA($row->birth_city) }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_birth_division'))
                                    <tr>
                                        <th>{{ __('Birth division') }}</th>
                                        <td>{{ $valueOrNA($row->birth_division) }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_birth_region'))
                                    <tr>
                                        <th>{{ __('Birth region') }}</th>
                                        <td>{{ $valueOrNA($row->birth_region) }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_birth_country'))
                                    <tr>
                                        <th>{{ __('Birth country') }}</th>
                                        <td>{{ $valueOrNA($row->birth_country) }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('Nationality') }}</th>
                                        <td>{{ $valueOrNA($row->nationality) }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Religion') }}</th>
                                        <td>{{ $row->religionDetail ? $row->religionDetail->title : ($valueOrNA($row->religion)) }}</td>
                                    </tr>
                                    @if($fieldEnabled('application_catholic_baptised'))
                                    <tr>
                                        <th>{{ __('Catholic baptised') }}</th>
                                        <td>{{ $row->is_catholic_baptised ? __('Yes') : __('No') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Received Confirmation') }}</th>
                                        <td>{{ $row->is_confirmed ? __('Yes') : __('No') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Received First Holy Communion') }}</th>
                                        <td>{{ $row->has_first_communion ? __('Yes') : __('No') }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('National ID number') }}</th>
                                        <td>{{ $valueOrNA($row->national_id) }}</td>
                                    </tr>
                                    @if($fieldEnabled('application_national_id_issue_date'))
                                    <tr>
                                        <th>{{ __('National ID issue date') }}</th>
                                        <td>{{ $formatDate($row->national_id_issue_date) ?? __('N/A') }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_national_id_issue_place'))
                                    <tr>
                                        <th>{{ __('National ID issue place') }}</th>
                                        <td>{{ $valueOrNA($row->national_id_issue_place) }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('Passport number') }}</th>
                                        <td>{{ $valueOrNA($row->passport_no) }}</td>
                                    </tr>
                                    @if($fieldEnabled('application_passport_issue_date'))
                                    <tr>
                                        <th>{{ __('Passport issue date') }}</th>
                                        <td>{{ $formatDate($row->passport_issue_date) ?? __('N/A') }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_passport_issue_country'))
                                    <tr>
                                        <th>{{ __('Passport issue country') }}</th>
                                        <td>{{ $valueOrNA($row->passport_issue_country) }}</td>
                                    </tr>
                                    @endif
                                    @if($fieldEnabled('application_studied_in_english'))
                                    <tr>
                                        <th>{{ __('Studied in English') }}</th>
                                        <td>{{ $row->studied_in_english ? __('Yes') : __('No') }}</td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Addresses') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-2 text-primary">{{ __('Present address') }}</h6>
                                <ul class="list-unstyled small mb-0">
                                    <li><strong>{{ __('Province') }}:</strong> {{ $row->present_province ?? __('N/A') }}</li>
                                    <li><strong>{{ __('District') }}:</strong> {{ $row->present_district ?? __('N/A') }}</li>
                                    @if($fieldEnabled('application_address'))
                                        <li><strong>{{ __('Village / council') }}:</strong> {{ $valueOrNA($row->present_village) }}</li>
                                        <li><strong>{{ __('Address line') }}:</strong> {{ $valueOrNA($row->present_address) }}</li>
                                    @endif
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-2 text-primary">{{ __('Permanent address') }}</h6>
                                <ul class="list-unstyled small mb-0">
                                    <li><strong>{{ __('Province') }}:</strong> {{ $row->permanent_province ?? __('N/A') }}</li>
                                    <li><strong>{{ __('District') }}:</strong> {{ $row->permanent_district ?? __('N/A') }}</li>
                                    @if($fieldEnabled('application_address'))
                                        <li><strong>{{ __('Village / council') }}:</strong> {{ $valueOrNA($row->permanent_village) }}</li>
                                        <li><strong>{{ __('Address line') }}:</strong> {{ $valueOrNA($row->permanent_address) }}</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($fieldEnabled('application_guardians'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Guardians & emergency contacts') }}</h5>
                    </div>
                    <div class="card-block table-border-style">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Relationship') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Phone(s)') }}</th>
                                        <th>{{ __('Email') }}</th>
                                        <th>{{ __('Address') }}</th>
                                        <th>{{ __('Primary') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($row->guardians as $guardian)
                                        @php
                                            $phoneParts = array_filter([$guardian->phone_primary, $guardian->phone_secondary]);
                                            $addressParts = array_filter([
                                                $guardian->address_line1,
                                                $guardian->address_line2,
                                                $guardian->city,
                                                $guardian->state,
                                                $guardian->country,
                                            ]);
                                        @endphp
                                        <tr>
                                            <td>{{ $guardian->full_name }}</td>
                                            <td>{{ $guardian->relationship ?? __('N/A') }}</td>
                                            <td>{{ $guardian->type ?? __('N/A') }}</td>
                                            <td>{{ count($phoneParts) ? implode(', ', $phoneParts) : __('N/A') }}</td>
                                            <td>{{ $guardian->email ?? __('N/A') }}</td>
                                            <td>{{ count($addressParts) ? implode(', ', $addressParts) : __('N/A') }}</td>
                                            <td>
                                                <span class="badge {{ $guardian->is_primary ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ $guardian->is_primary ? __('Yes') : __('No') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">{{ __('No guardians provided.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($fieldEnabled('application_academic_history'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Academic history') }}</h5>
                    </div>
                    <div class="card-block table-border-style">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Institution') }}</th>
                                        <th>{{ __('Location') }}</th>
                                        <th>{{ __('Instruction language') }}</th>
                                        <th>{{ __('Dates attended') }}</th>
                                        <th>{{ __('Certificate / notes') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($row->academicHistories as $history)
                                        <tr>
                                            <td>{{ $history->institution_name }}</td>
                                            <td>
                                                @php
                                                    $locationParts = array_filter([$history->city, $history->country]);
                                                @endphp
                                                {{ count($locationParts) ? implode(', ', $locationParts) : __('N/A') }}
                                            </td>
                                            <td>{{ $history->instruction_language ?? __('N/A') }}</td>
                                            <td>
                                                @php
                                                    $from = $formatDate($history->date_from);
                                                    $to = $formatDate($history->date_to);
                                                @endphp
                                                {{ $from ? $from : __('N/A') }} @if($to || $from) - @endif {{ $to ?? __('Present') }}
                                            </td>
                                            <td>
                                                @php
                                                    $details = array_filter([
                                                        $history->certificate_obtained,
                                                        $history->gce_ol_detail,
                                                        $history->gce_al_detail,
                                                        $history->probatoire_detail,
                                                        $history->baccalaureate_detail,
                                                        $history->notes,
                                                    ]);
                                                @endphp
                                                {{ count($details) ? implode(' | ', $details) : __('N/A') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">{{ __('No academic entries recorded.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($fieldEnabled('application_language_proficiency'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Language proficiency') }}</h5>
                    </div>
                    <div class="card-block table-border-style">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Language') }}</th>
                                        <th>{{ __('Years of study') }}</th>
                                        <th>{{ __('Fluency level') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($row->languages as $language)
                                        <tr>
                                            <td>{{ $language->language }}</td>
                                            <td>{{ $language->years_of_study ?? __('N/A') }}</td>
                                            <td>{{ $language->fluency_level ?? __('N/A') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">{{ __('No language entries recorded.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($fieldEnabled('application_document_checklist'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Document checklist & uploads') }}</h5>
                    </div>
                    <div class="card-block table-border-style">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Document') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Notes') }}</th>
                                        <th>{{ __('Files') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documentRequirements as $key => $requirement)
                                        @php
                                            $document = $documentMap->get($key);
                                            $fileExists = $document && $document->file_path && is_file(public_path('uploads/'.$path.'/'.$document->file_path));
                                            $fileUrl = $fileExists ? asset('uploads/'.$path.'/'.$document->file_path) : null;
                                            $extension = $fileExists ? strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) : null;
                                            $isPreviewable = $extension && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            $statusClass = $document && $document->is_received ? 'badge-success' : ($requirement['required'] ? 'badge-danger' : 'badge-secondary');
                                            $statusLabel = $document && $document->is_received ? __('Received') : ($requirement['required'] ? __('Pending') : __('Optional'));
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="d-block f-w-500">{{ $requirement['label'] }}</span>
                                                @if(!empty($requirement['description']))
                                                    <small class="text-muted document-description">{{ $requirement['description'] }}</small>
                                                @endif
                                                @unless($requirement['required'])
                                                    <span class="badge badge-light ml-1">{{ __('Optional') }}</span>
                                                @endunless
                                            </td>
                                            <td>
                                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                                @if($document && $document->updated_at)
                                                    <div class="small text-muted">{{ __('Updated') }}: {{ $formatDateTime($document->updated_at) }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $document->notes ?? __('N/A') }}</td>
                                            <td>
                                                @if($fileExists)
                               <div class="d-flex flex-column flex-sm-row align-items-start">
                                                        <a href="{{ $fileUrl }}"
                                   class="btn btn-sm btn-outline-primary mb-1 mb-sm-0 mr-sm-2"
                                                           @if($isPreviewable) data-lightbox="doc-{{ $document->id }}" data-title="{{ $requirement['label'] }}" @else target="_blank" @endif>
                                                            {{ __('View') }}
                                                        </a>
                                                        <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-secondary" download>
                                                            {{ __('Download') }}
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-muted">{{ __('No file') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach

                                    @php
                                        $extraDocumentKeys = $documentMap->keys()->diff(collect($documentRequirements)->keys());
                                    @endphp

                                    @foreach($extraDocumentKeys as $extraKey)
                                        @php
                                            $document = $documentMap->get($extraKey);
                                            $fileExists = $document && $document->file_path && is_file(public_path('uploads/'.$path.'/'.$document->file_path));
                                            $fileUrl = $fileExists ? asset('uploads/'.$path.'/'.$document->file_path) : null;
                                            $extension = $fileExists ? strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) : null;
                                            $isPreviewable = $extension && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            $label = ucwords(str_replace(['_', '-'], ' ', $extraKey));
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="d-block f-w-500">{{ $label }}</span>
                                                <small class="text-muted">{{ __('Uploaded outside of the standard checklist') }}</small>
                                            </td>
                                            <td>
                                                <span class="badge {{ $document->is_received ? 'badge-success' : 'badge-secondary' }}">{{ $document->is_received ? __('Received') : __('Stored') }}</span>
                                                @if($document->updated_at)
                                                    <div class="small text-muted">{{ __('Updated') }}: {{ $formatDateTime($document->updated_at) }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $document->notes ?? __('N/A') }}</td>
                                            <td>
                                                @if($fileExists)
                               <div class="d-flex flex-column flex-sm-row align-items-start">
                                                        <a href="{{ $fileUrl }}"
                                   class="btn btn-sm btn-outline-primary mb-1 mb-sm-0 mr-sm-2"
                                                           @if($isPreviewable) data-lightbox="doc-extra-{{ $document->id }}" data-title="{{ $label }}" @else target="_blank" @endif>
                                                            {{ __('View') }}
                                                        </a>
                                                        <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-secondary" download>
                                                            {{ __('Download') }}
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-muted">{{ __('No file') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($fieldEnabled('application_board_review'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Board review summary') }}</h5>
                    </div>
                    <div class="card-block">
                        @if($boardReview)
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm mb-0 detail-table">
                                    <tbody>
                                        <tr>
                                            <th>{{ __('Meets university requirements') }}</th>
                                            <td><span class="badge {{ $boardReview->meets_university_requirements ? 'badge-success' : 'badge-danger' }}">{{ $boardReview->meets_university_requirements ? __('Yes') : __('No') }}</span></td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Meets programme requirements') }}</th>
                                            <td><span class="badge {{ $boardReview->meets_program_requirements ? 'badge-success' : 'badge-danger' }}">{{ $boardReview->meets_program_requirements ? __('Yes') : __('No') }}</span></td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('First choice decision') }}</th>
                                            <td>{{ $valueOrNA($boardReview->first_choice_decision) }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Second choice decision') }}</th>
                                            <td>{{ $valueOrNA($boardReview->second_choice_decision) }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Third choice decision') }}</th>
                                            <td>{{ $valueOrNA($boardReview->third_choice_decision) }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Observation') }}</th>
                                            <td>{{ $boardReview->observation ?? __('N/A') }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Rejection reason') }}</th>
                                            <td>{{ $boardReview->rejection_reason ?? __('N/A') }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Reviewed at') }}</th>
                                            <td>{{ $formatDateTime($boardReview->reviewed_at) ?? __('N/A') }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Reviewed by (user ID)') }}</th>
                                            <td>{{ $boardReview->reviewed_by ?? __('N/A') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            @if($boardReview->signatures && $boardReview->signatures->isNotEmpty())
                                <hr>
                                <h6>{{ __('Board signatures') }}</h6>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Name') }}</th>
                                                <th>{{ __('Position') }}</th>
                                                <th>{{ __('Signed at') }}</th>
                                                <th>{{ __('Signature') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($boardReview->signatures as $signature)
                                                @php
                                                    $signaturePath = $signature->signature_path;
                                                    if ($signaturePath && !preg_match('/^(https?:)?\//', $signaturePath)) {
                                                        $signaturePath = asset('uploads/'.$path.'/'.$signaturePath);
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>{{ $signature->name }}</td>
                                                    <td>{{ $signature->position ?? __('N/A') }}</td>
                                                    <td>{{ $formatDateTime($signature->signed_at) ?? __('N/A') }}</td>
                                                    <td>
                                                        @if($signature->signature_path && $signaturePath)
                                                            <a href="{{ $signaturePath }}" class="btn btn-sm btn-outline-primary" target="_blank">{{ __('View signature') }}</a>
                                                        @else
                                                            <span class="text-muted">{{ __('No file') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <p class="text-muted mb-0">{{ __('Board review has not been recorded for this application.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/lightbox2-master/js/lightbox.min.js') }}"></script>
@endsection