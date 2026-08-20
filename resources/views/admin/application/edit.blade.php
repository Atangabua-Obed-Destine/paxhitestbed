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
  .form-section-title {
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding-bottom: .35rem;
    margin-bottom: 1rem;
    text-transform: uppercase;
    font-size: .75rem;
    letter-spacing: .08rem;
  }
  .btn-icon-only {
    padding: .25rem .5rem;
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
  $valueOrNA = function ($value) use ($dateFormat): string {
    if ($value instanceof \Carbon\CarbonInterface) {
      return $value->format($dateFormat);
    }
    if (is_bool($value)) {
      return $value ? __('Yes') : __('No');
    }
    if (is_string($value)) {
      $value = trim($value);
    }
    return filled($value) ? (string) $value : __('N/A');
  };

  $documentRequirements = $documentRequirements ?? [];
  $documentMap = collect($row->documents ?? [])->keyBy('document_type');
  $guardianTypes = $guardianTypes ?? ['Parent', 'Sponsor', 'Guardian'];
  $fluencyOptions = $fluencyOptions ?? ['excellent', 'good', 'fair', 'minimal'];
@endphp

<div class="main-body">
  <div class="page-wrapper">
    <div class="row mb-4">
      @include('admin.application.partials.profile-overview', ['row' => $row, 'path' => $path, 'setting' => $setting ?? null])
    </div>

    <div class="row mb-4">
      @include('admin.application.partials.timeline', ['row' => $row, 'timeline' => $timeline, 'showUpdateForm' => true])
    </div>

    <div class="row">
      <div class="col-lg-8 mb-4">
                <form id="application-update-form" action="{{ route('admin.application.update', $row->id) }}" method="post" enctype="multipart/form-data" class="card needs-validation" novalidate>
          @csrf
          @method('PUT')
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Update applicant profile') }}</h5>
            <div>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ __('Save changes') }}
              </button>
              <a href="{{ route('admin.application.show', $row->id) }}" class="btn btn-light">
                <i class="fas fa-eye"></i> {{ __('View summary') }}
              </a>
            </div>
          </div>
          <div class="card-block">
            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Personal information') }}</h6>
              <div class="row g-3">
                <div class="form-group col-md-6">
                  <label for="first_name">{{ __('field_first_name') }} <span>*</span></label>
                  <input type="text" class="form-control" id="first_name" name="first_name" value="{{ old('first_name', $row->first_name) }}" required>
                </div>
                <div class="form-group col-md-6">
                  <label for="last_name">{{ __('field_last_name') }} <span>*</span></label>
                  <input type="text" class="form-control" id="last_name" name="last_name" value="{{ old('last_name', $row->last_name) }}" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="d-block">{{ __('field_gender') }} <span>*</span></label>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="gender" id="gender_male" value="1" {{ old('gender', $row->gender) == 1 ? 'checked' : '' }} required>
                    <label class="form-check-label" for="gender_male">{{ __('gender_male') }}</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="gender" id="gender_female" value="2" {{ old('gender', $row->gender) == 2 ? 'checked' : '' }} required>
                    <label class="form-check-label" for="gender_female">{{ __('gender_female') }}</label>
                  </div>
                </div>
                <div class="form-group col-md-3">
                  <label for="dob">{{ __('field_dob') }} <span>*</span></label>
                  <input type="date" id="dob" name="dob" class="form-control" value="{{ old('dob', optional($row->dob)->format('Y-m-d')) }}" required>
                </div>
                <div class="form-group col-md-6">
                  <label for="email">{{ __('field_email') }} <span>*</span></label>
                  <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $row->email) }}" required>
                </div>
                <div class="form-group col-md-6">
                  <label for="phone">{{ __('field_phone') }} <span>*</span></label>
                  <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $row->phone) }}" placeholder="+237 6XX XXX XXX" required>
                  <small class="form-text text-muted">{{ __('Include the country code, e.g. +237.') }}</small>
                </div>
                @if($fieldEnabled('application_alternate_phone'))
                <div class="form-group col-md-6">
                  <label for="alternate_phone">{{ __('Alternate phone') }}</label>
                  <input type="text" class="form-control" id="alternate_phone" name="alternate_phone" value="{{ old('alternate_phone', $row->alternate_phone) }}">
                </div>
                @endif
                @if($fieldEnabled('application_emergency_phone'))
                <div class="form-group col-md-6">
                  <label for="emergency_phone">{{ __('field_emergency_phone') }}</label>
                  <input type="text" class="form-control" id="emergency_phone" name="emergency_phone" value="{{ old('emergency_phone', $row->emergency_phone) }}">
                </div>
                @endif
                @if($fieldEnabled('application_religion'))
                <div class="form-group col-md-6">
                  <label for="religion">{{ __('field_religion') }}</label>
                  <select class="form-control" name="religion" id="religion">
                    <option value="">{{ __('select') }}</option>
                    @foreach($religions as $religion)
                    <option value="{{ $religion->id }}" {{ old('religion', $row->religion) == $religion->id ? 'selected' : '' }}>{{ $religion->title }}</option>
                    @endforeach
                  </select>
                </div>
                @endif
                @if($fieldEnabled('application_catholic_baptised'))
                <div class="form-group col-md-12" id="catholic-sacraments-container-admin" style="display: none;">
                  <label class="d-block mb-2">{{ __('Catholic Sacraments') }}</label>
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="is_catholic_baptised" name="is_catholic_baptised" {{ old('is_catholic_baptised', $row->is_catholic_baptised) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_catholic_baptised">
                          {{ __('I am a baptised Catholic with proof of baptism') }}
                        </label>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="is_confirmed" name="is_confirmed" {{ old('is_confirmed', $row->is_confirmed) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_confirmed">
                          {{ __('I have received Confirmation') }}
                        </label>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="has_first_communion" name="has_first_communion" {{ old('has_first_communion', $row->has_first_communion) ? 'checked' : '' }}>
                        <label class="form-check-label" for="has_first_communion">
                          {{ __('I have received First Holy Communion') }}
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                @endif
                @if($fieldEnabled('application_caste'))
                <div class="form-group col-md-6">
                  <label for="caste">{{ __('field_caste') }}</label>
                  <input type="text" class="form-control" id="caste" name="caste" value="{{ old('caste', $row->caste) }}">
                </div>
                @endif
                @if($fieldEnabled('application_marital_status'))
                <div class="form-group col-md-6">
                  <label for="marital_status">{{ __('field_marital_status') }}</label>
                  <select id="marital_status" name="marital_status" class="form-control">
                    <option value="">{{ __('select') }}</option>
                    <option value="1" {{ old('marital_status', $row->marital_status) == 1 ? 'selected' : '' }}>{{ __('marital_status_single') }}</option>
                    <option value="2" {{ old('marital_status', $row->marital_status) == 2 ? 'selected' : '' }}>{{ __('marital_status_married') }}</option>
                    <option value="3" {{ old('marital_status', $row->marital_status) == 3 ? 'selected' : '' }}>{{ __('marital_status_widowed') }}</option>
                    <option value="4" {{ old('marital_status', $row->marital_status) == 4 ? 'selected' : '' }}>{{ __('marital_status_divorced') }}</option>
                    <option value="5" {{ old('marital_status', $row->marital_status) == 5 ? 'selected' : '' }}>{{ __('marital_status_other') }}</option>
                  </select>
                </div>
                @endif
                @if($fieldEnabled('application_blood_group'))
                <div class="form-group col-md-6">
                  <label for="blood_group">{{ __('field_blood_group') }}</label>
                  <select id="blood_group" name="blood_group" class="form-control">
                    <option value="">{{ __('select') }}</option>
                    @foreach([
                      1 => __('A+'),
                      2 => __('A-'),
                      3 => __('B+'),
                      4 => __('B-'),
                      5 => __('AB+'),
                      6 => __('AB-'),
                      7 => __('O+'),
                      8 => __('O-'),
                    ] as $value => $label)
                      <option value="{{ $value }}" {{ old('blood_group', $row->blood_group) == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                @endif
              </div>
            </div>

            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Identity & citizenship') }}</h6>
              <div class="row g-3">
                <div class="form-group col-md-6">
                  <label for="nationality">{{ __('field_nationality') }}</label>
                  @include('partials.country-select', ['name' => 'nationality', 'id' => 'nationality', 'value' => old('nationality', $row->nationality)])
                </div>
                <div class="form-group col-md-6">
                  <label for="national_id">{{ __('field_national_id') }}</label>
                  <input type="text" class="form-control" id="national_id" name="national_id" value="{{ old('national_id', $row->national_id) }}">
                </div>
                @if($fieldEnabled('application_national_id_issue_date'))
                <div class="form-group col-md-6">
                  <label for="national_id_issue_date">{{ __('National ID issue date') }}</label>
                  <input type="date" class="form-control" id="national_id_issue_date" name="national_id_issue_date" value="{{ old('national_id_issue_date', optional($row->national_id_issue_date)->format('Y-m-d')) }}">
                </div>
                @endif
                @if($fieldEnabled('application_national_id_issue_place'))
                <div class="form-group col-md-6">
                  <label for="national_id_issue_place">{{ __('National ID issue place') }}</label>
                  <input type="text" class="form-control" id="national_id_issue_place" name="national_id_issue_place" value="{{ old('national_id_issue_place', $row->national_id_issue_place) }}">
                </div>
                @endif
                <div class="form-group col-md-6">
                  <label for="passport_no">{{ __('field_passport_no') }}</label>
                  <input type="text" class="form-control" id="passport_no" name="passport_no" value="{{ old('passport_no', $row->passport_no) }}">
                </div>
                @if($fieldEnabled('application_passport_issue_date'))
                <div class="form-group col-md-6">
                  <label for="passport_issue_date">{{ __('Passport issue date') }}</label>
                  <input type="date" class="form-control" id="passport_issue_date" name="passport_issue_date" value="{{ old('passport_issue_date', optional($row->passport_issue_date)->format('Y-m-d')) }}">
                </div>
                @endif
                @if($fieldEnabled('application_passport_issue_country'))
                <div class="form-group col-md-6">
                  <label for="passport_issue_country">{{ __('Passport issue country') }}</label>
                  <input type="text" class="form-control" id="passport_issue_country" name="passport_issue_country" value="{{ old('passport_issue_country', $row->passport_issue_country) }}">
                </div>
                @endif
                @if($fieldEnabled('application_birth_city'))
                <div class="form-group col-md-6">
                  <label for="birth_city">{{ __('Birth city') }}</label>
                  <input type="text" class="form-control" id="birth_city" name="birth_city" value="{{ old('birth_city', $row->birth_city) }}">
                </div>
                @endif
                @if($fieldEnabled('application_birth_division'))
                <div class="form-group col-md-6">
                  <label for="birth_division">{{ __('Birth division') }}</label>
                  <input type="text" class="form-control" id="birth_division" name="birth_division" value="{{ old('birth_division', $row->birth_division) }}">
                </div>
                @endif
                @if($fieldEnabled('application_birth_region'))
                <div class="form-group col-md-6">
                  <label for="birth_region">{{ __('Birth region') }}</label>
                  <input type="text" class="form-control" id="birth_region" name="birth_region" value="{{ old('birth_region', $row->birth_region) }}">
                </div>
                @endif
                @if($fieldEnabled('application_birth_country'))
                <div class="form-group col-md-6">
                  <label for="birth_country">{{ __('Birth country') }}</label>
                  <input type="text" class="form-control" id="birth_country" name="birth_country" value="{{ old('birth_country', $row->birth_country) }}">
                </div>
                @endif

              </div>
            </div>

            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Contact & addresses') }}</h6>
              <div class="row g-3">
                <div class="form-group col-md-6">
                  <label for="country">{{ __('field_country') }} <span>*</span></label>
                  @include('partials.country-select', ['name' => 'country', 'id' => 'country', 'value' => old('country', $row->country), 'required' => true])
                </div>
                <div class="form-group col-md-6">
                  <label for="present_province_edit">{{ __('Present province') }}</label>
                  <input type="text" class="form-control" id="present_province_edit" name="present_province" value="{{ old('present_province', $row->present_province) }}">
                </div>
                <div class="form-group col-md-6">
                  <label for="present_district_edit">{{ __('Present district') }}</label>
                  <input type="text" class="form-control" id="present_district_edit" name="present_district" value="{{ old('present_district', $row->present_district) }}">
                </div>
                @if($fieldEnabled('application_address'))
                <div class="form-group col-md-6">
                  <label for="present_village">{{ __('Village / council') }}</label>
                  <input type="text" class="form-control" id="present_village" name="present_village" value="{{ old('present_village', $row->present_village) }}">
                </div>
                <div class="form-group col-md-6">
                  <label for="present_address">{{ __('field_address') }}</label>
                  <input type="text" class="form-control" id="present_address" name="present_address" value="{{ old('present_address', $row->present_address) }}">
                </div>
                @endif
                <div class="col-12">
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="same_as_residence_edit">
                    <label class="form-check-label" for="same_as_residence_edit">{{ __('Same as Current Residence') }}</label>
                  </div>
                </div>
                <div class="form-group col-md-6">
                  <label for="permanent_province_edit">{{ __('Permanent province') }}</label>
                  <input type="text" class="form-control" id="permanent_province_edit" name="permanent_province" value="{{ old('permanent_province', $row->permanent_province) }}">
                </div>
                <div class="form-group col-md-6">
                  <label for="permanent_district_edit">{{ __('Permanent district') }}</label>
                  <input type="text" class="form-control" id="permanent_district_edit" name="permanent_district" value="{{ old('permanent_district', $row->permanent_district) }}">
                </div>
                @if($fieldEnabled('application_address'))
                <div class="form-group col-md-6">
                  <label for="permanent_village">{{ __('Village / council') }}</label>
                  <input type="text" class="form-control" id="permanent_village" name="permanent_village" value="{{ old('permanent_village', $row->permanent_village) }}">
                </div>
                <div class="form-group col-md-6">
                  <label for="permanent_address">{{ __('field_address') }}</label>
                  <input type="text" class="form-control" id="permanent_address" name="permanent_address" value="{{ old('permanent_address', $row->permanent_address) }}">
                </div>
                @endif
                @if($fieldEnabled('application_postal_address'))
                <div class="form-group col-md-6">
                  <label for="postal_address_line1">{{ __('Postal address line 1') }}</label>
                  <input type="text" class="form-control" id="postal_address_line1" name="postal_address_line1" value="{{ old('postal_address_line1', $row->postal_address_line1) }}">
                </div>
                <div class="form-group col-md-6">
                  <label for="postal_address_line2">{{ __('Postal address line 2') }}</label>
                  <input type="text" class="form-control" id="postal_address_line2" name="postal_address_line2" value="{{ old('postal_address_line2', $row->postal_address_line2) }}">
                </div>
                @endif
              </div>
            </div>

            @if($fieldEnabled('application_guardians'))
            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Guardians & emergency contacts') }}</h6>
              <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm align-middle" id="guardian-table">
                  <thead>
                    <tr>
                      <th>{{ __('Primary?') }}</th>
                      <th>{{ __('Name') }}</th>
                      <th>{{ __('Relationship') }}</th>
                      <th>{{ __('Type') }}</th>
                      <th>{{ __('Phone (primary)') }}</th>
                      <th>{{ __('Phone (secondary)') }}</th>
                      <th>{{ __('Email') }}</th>
                      <th>{{ __('Address') }}</th>
                      <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php
                      $guardianRows = old('guardians', $row->guardians->toArray());
                    @endphp
                    @foreach($guardianRows as $index => $guardian)
                      <tr data-index="{{ $index }}">
                        <td class="text-center">
                          <input type="hidden" name="guardians[{{ $index }}][id]" value="{{ $guardian['id'] ?? '' }}">
                          <input type="checkbox" name="guardians[{{ $index }}][is_primary]" value="1" {{ !empty($guardian['is_primary']) ? 'checked' : '' }}>
                        </td>
                        <td>
                          <input type="text" class="form-control" name="guardians[{{ $index }}][full_name]" value="{{ $guardian['full_name'] ?? '' }}">
                        </td>
                        <td><input type="text" class="form-control" name="guardians[{{ $index }}][relationship]" value="{{ $guardian['relationship'] ?? '' }}"></td>
                        <td>
                          <select class="form-control" name="guardians[{{ $index }}][type]">
                            <option value="">{{ __('select') }}</option>
                            @foreach($guardianTypes as $type)
                              <option value="{{ $type }}" {{ ($guardian['type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                          </select>
                        </td>
                        <td><input type="text" class="form-control" name="guardians[{{ $index }}][phone_primary]" value="{{ $guardian['phone_primary'] ?? '' }}"></td>
                        <td><input type="text" class="form-control" name="guardians[{{ $index }}][phone_secondary]" value="{{ $guardian['phone_secondary'] ?? '' }}"></td>
                        <td><input type="email" class="form-control" name="guardians[{{ $index }}][email]" value="{{ $guardian['email'] ?? '' }}"></td>
                        <td>
                          <input type="text" class="form-control mb-1" name="guardians[{{ $index }}][address_line1]" placeholder="{{ __('Address line 1') }}" value="{{ $guardian['address_line1'] ?? '' }}">
                          <input type="text" class="form-control mb-1" name="guardians[{{ $index }}][address_line2]" placeholder="{{ __('Address line 2') }}" value="{{ $guardian['address_line2'] ?? '' }}">
                          <input type="text" class="form-control mb-1" name="guardians[{{ $index }}][city]" placeholder="{{ __('City') }}" value="{{ $guardian['city'] ?? '' }}">
                          <input type="text" class="form-control mb-1" name="guardians[{{ $index }}][state]" placeholder="{{ __('State / region') }}" value="{{ $guardian['state'] ?? '' }}">
                          @include('partials.country-select', ['name' => "guardians[{$index}][country]", 'value' => $guardian['country'] ?? null])
                        </td>
                        <td class="text-center">
                          <button type="button" class="btn btn-danger btn-sm btn-icon-only remove-guardian-row"><i class="fas fa-trash-alt"></i></button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              <button type="button" class="btn btn-outline-primary" id="add-guardian-row">
                <i class="fas fa-plus"></i> {{ __('Add guardian') }}
              </button>
            </div>
            @endif

            @if($fieldEnabled('application_academic_history'))
            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Academic history') }}</h6>
              <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm align-middle" id="academic-history-table">
                  <thead>
                    <tr>
                      <th>{{ __('Qualification') }}</th>
                      <th>{{ __('Awarding body / Institution') }}</th>
                      <th>{{ __('Location') }}</th>
                      <th>{{ __('Language') }}</th>
                      <th>{{ __('Years') }}</th>
                      <th>{{ __('Notes') }}</th>
                      <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php
                      $historyRows = old('academic_history', $row->academicHistories->map(fn($item) => $item->toArray())->toArray());
                      $configuredCards = \App\Services\DegreeTypeFormConfig::qualifications($row->degreeType);
                    @endphp
                    @foreach($historyRows as $index => $history)
                      @php
                        $cardKey = $history['qualification_key'] ?? null;
                        $cardLabel = $cardKey && isset($configuredCards[$cardKey]) ? $configuredCards[$cardKey]['label'] : null;
                      @endphp
                      <tr data-index="{{ $index }}">
                        <td>
                          <input type="hidden" name="academic_history[{{ $index }}][id]" value="{{ $history['id'] ?? '' }}">
                          {{-- Preserved so a staff edit cannot orphan the row from its card. --}}
                          <input type="hidden" name="academic_history[{{ $index }}][qualification_key]" value="{{ $cardKey }}">
                          <input type="text" class="form-control" name="academic_history[{{ $index }}][certificate_obtained]" placeholder="{{ __('Certificate obtained') }}" value="{{ $history['certificate_obtained'] ?? '' }}">
                          @if($cardLabel)
                            <small class="text-muted">{{ $cardLabel }}</small>
                          @else
                            <small class="text-muted">{{ __('Added by applicant') }}</small>
                          @endif
                        </td>
                        <td>
                          <input type="text" class="form-control mb-1" name="academic_history[{{ $index }}][awarding_body]" placeholder="{{ __('Awarding body') }}" value="{{ $history['awarding_body'] ?? '' }}">
                          <input type="text" class="form-control" name="academic_history[{{ $index }}][institution_name]" placeholder="{{ __('Institution name') }}" value="{{ $history['institution_name'] ?? '' }}">
                        </td>
                        <td>
                          @include('partials.country-select', ['name' => "academic_history[{$index}][country]", 'value' => $history['country'] ?? null, 'class' => 'form-control-sm mb-1'])
                          <input type="text" class="form-control" name="academic_history[{{ $index }}][city]" placeholder="{{ __('City') }}" value="{{ $history['city'] ?? '' }}">
                        </td>
                        <td>
                          <input type="text" class="form-control" name="academic_history[{{ $index }}][instruction_language]" value="{{ $history['instruction_language'] ?? '' }}">
                        </td>
                        <td>
                          <input type="number" class="form-control mb-1" name="academic_history[{{ $index }}][start_year]" placeholder="{{ __('From') }}" min="1900" max="2200" value="{{ $history['start_year'] ?? '' }}">
                          <input type="number" class="form-control" name="academic_history[{{ $index }}][end_year]" placeholder="{{ __('To') }}" min="1900" max="2200" value="{{ $history['end_year'] ?? '' }}">
                        </td>
                        <td>
                          <input type="text" class="form-control mb-1" name="academic_history[{{ $index }}][gce_ol_detail]" placeholder="{{ __('GCE O/L or Probatoire detail') }}" value="{{ $history['gce_ol_detail'] ?? '' }}">
                          <input type="text" class="form-control mb-1" name="academic_history[{{ $index }}][gce_al_detail]" placeholder="{{ __('GCE A/L or Baccalaureate detail') }}" value="{{ $history['gce_al_detail'] ?? '' }}">
                          <input type="text" class="form-control mb-1" name="academic_history[{{ $index }}][probatoire_detail]" placeholder="{{ __('Probatoire detail') }}" value="{{ $history['probatoire_detail'] ?? '' }}">
                          <input type="text" class="form-control mb-1" name="academic_history[{{ $index }}][baccalaureate_detail]" placeholder="{{ __('Baccalaureate detail') }}" value="{{ $history['baccalaureate_detail'] ?? '' }}">
                          <textarea class="form-control" name="academic_history[{{ $index }}][notes]" rows="2" placeholder="{{ __('Notes') }}">{{ $history['notes'] ?? '' }}</textarea>
                        </td>
                        <td class="text-center">
                          <button type="button" class="btn btn-danger btn-sm btn-icon-only remove-history-row"><i class="fas fa-trash-alt"></i></button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              <button type="button" class="btn btn-outline-primary" id="add-history-row">
                <i class="fas fa-plus"></i> {{ __('Add academic entry') }}
              </button>
            </div>
            @endif

    @if($fieldEnabled('application_language_proficiency'))
            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Language proficiency') }}</h6>
              <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm align-middle" id="language-table">
                  <thead>
                    <tr>
                      <th>{{ __('Language') }}</th>
                      <th>{{ __('Years of study') }}</th>
                      <th>{{ __('Fluency level') }}</th>
                      <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php
                      $languageRows = old('languages', $row->languages->toArray());
                    @endphp
                    @foreach($languageRows as $index => $language)
                      <tr data-index="{{ $index }}">
                        <td>
                          <input type="hidden" name="languages[{{ $index }}][id]" value="{{ $language['id'] ?? '' }}">
                          <input type="text" class="form-control" name="languages[{{ $index }}][language]" value="{{ $language['language'] ?? '' }}">
                        </td>
                        <td>
                          <input type="number" min="0" class="form-control" name="languages[{{ $index }}][years_of_study]" value="{{ $language['years_of_study'] ?? '' }}">
                        </td>
                        <td>
                          <select class="form-control" name="languages[{{ $index }}][fluency_level]">
                            <option value="">{{ __('select') }}</option>
                            @foreach($fluencyOptions as $option)
                              <option value="{{ $option }}" {{ ($language['fluency_level'] ?? '') === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                            @endforeach
                          </select>
                        </td>
                        <td class="text-center">
                          <button type="button" class="btn btn-danger btn-sm btn-icon-only remove-language-row"><i class="fas fa-trash-alt"></i></button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              <button type="button" class="btn btn-outline-primary" id="add-language-row">
                <i class="fas fa-plus"></i> {{ __('Add language') }}
              </button>
            </div>
            @endif

            @if($fieldEnabled('application_document_checklist'))
            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Document checklist & uploads') }}</h6>
              <div class="table-responsive">
                <table class="table table-striped table-sm align-middle">
                  <thead>
                    <tr>
                      <th>{{ __('Document') }}</th>
                      <th>{{ __('Status') }}</th>
                      <th>{{ __('Resubmission') }}</th>
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
                        $needsResubmission = $document && $document->needs_resubmission;
                        $wasResubmitted = $document && $document->resubmitted_at;
                      @endphp
                      <tr class="{{ $needsResubmission && !$wasResubmitted ? 'table-warning' : '' }}">
                        <td>
                          <span class="d-block">{{ $requirement['label'] }}</span>
                          @if(!empty($requirement['description']))
                            <small class="text-muted document-description">{{ $requirement['description'] }}</small>
                          @endif
                          @unless($requirement['required'])
                            <span class="badge badge-light ml-1">{{ __('Optional') }}</span>
                          @endunless
                        </td>
                        <td>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="documents[{{ $key }}][is_received]" value="1" id="doc-received-{{ $key }}" {{ $document && $document->is_received ? 'checked' : '' }}>
                            <label class="form-check-label" for="doc-received-{{ $key }}">{{ __('Mark as received') }}</label>
                          </div>
                          @if($document && $document->updated_at)
                            <div class="small text-muted">{{ __('Updated') }}: {{ $valueOrNA($document->updated_at) }}</div>
                          @endif
                        </td>
                        <td>
                          <div class="form-check mb-2">
                            <input class="form-check-input resubmission-toggle" type="checkbox" name="documents[{{ $key }}][needs_resubmission]" value="1" id="doc-resubmit-{{ $key }}" {{ $needsResubmission ? 'checked' : '' }} data-key="{{ $key }}">
                            <label class="form-check-label text-danger" for="doc-resubmit-{{ $key }}">
                              <i class="fas fa-exclamation-triangle"></i> {{ __('Request Resubmission') }}
                            </label>
                          </div>
                          <div class="resubmission-reason" id="resubmission-reason-{{ $key }}" style="{{ $needsResubmission ? '' : 'display: none;' }}">
                            <input type="text" class="form-control form-control-sm" name="documents[{{ $key }}][rejection_reason]" placeholder="{{ __('Reason for resubmission (visible to applicant)') }}" value="{{ old("documents.$key.rejection_reason", $document->rejection_reason ?? '') }}">
                          </div>
                          @if($wasResubmitted)
                            <div class="mt-1">
                              <span class="badge badge-success"><i class="fas fa-check"></i> {{ __('Resubmitted') }}</span>
                              <small class="text-muted d-block">{{ $document->resubmitted_at->format('M j, Y g:i A') }}</small>
                            </div>
                          @elseif($needsResubmission)
                            <div class="mt-1">
                              <span class="badge badge-warning"><i class="fas fa-clock"></i> {{ __('Awaiting resubmission') }}</span>
                            </div>
                          @endif
                        </td>
                        <td>
                          <textarea class="form-control" name="documents[{{ $key }}][notes]" rows="2" placeholder="{{ __('Internal notes for staff') }}">{{ old("documents.$key.notes", $document->notes ?? '') }}</textarea>
                        </td>
                        <td>
                          <div class="mb-2">
                            <input type="file" class="form-control" name="documents[{{ $key }}][file]">
                          </div>
                          @if($fileExists)
                            <div class="d-flex flex-column flex-sm-row align-items-start">
                              <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-primary mb-1 mb-sm-0 mr-sm-2" @if($isPreviewable) data-lightbox="doc-{{ $document->id }}" data-title="{{ $requirement['label'] }}" @else target="_blank" @endif>{{ __('View') }}</a>
                              <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-secondary" download>{{ __('Download') }}</a>
                            </div>
                          @else
                            <span class="text-muted">{{ __('No file uploaded') }}</span>
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
            @endif

            <div class="form-section mb-4">
              <h6 class="form-section-title">{{ __('Review & decisions') }}</h6>
              <div class="row g-3">
                <div class="form-group col-md-6">
                  <label for="faculty">{{ __('field_faculty') }} <span>*</span></label>
                  <select id="faculty" name="faculty" class="form-control faculty" data-selected="{{ old('faculty', $row->program ? $row->program->faculty_id : '') }}" required>
                    <option value="">{{ __('select') }}</option>
                    @foreach($faculties as $faculty)
                      <option value="{{ $faculty->id }}" {{ (int) old('faculty', $row->program ? $row->program->faculty_id : '') === $faculty->id ? 'selected' : '' }}>{{ $faculty->title }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-6">
                  <label for="program">{{ __('field_program') }} <span>*</span></label>
                  <select id="program" name="program" class="form-control program" data-selected="{{ old('program', $row->program_id) }}" required>
                    <option value="">{{ __('select') }}</option>
                    @foreach($programs as $program)
                      <option value="{{ $program->id }}" {{ (int) old('program', $row->program_id) === $program->id ? 'selected' : '' }}>{{ $program->title }}</option>
                    @endforeach
                  </select>
                </div>
                @if($fieldEnabled('application_academic_year'))
                <div class="form-group col-md-6">
                  <label for="academic_year">{{ __('field_academic_year') }}</label>
                  <input type="text" class="form-control" id="academic_year" name="academic_year" value="{{ old('academic_year', $row->academic_year) }}">
                </div>
                @endif
                <div class="form-group col-md-4">
                  <label for="status">{{ __('Decision status') }}</label>
                  <select id="status" name="status" class="form-control">
                    <option value="">{{ __('select') }}</option>
                    <option value="2" {{ old('status', $row->status) == 2 ? 'selected' : '' }}>{{ __('status_approved') }}</option>
                    <option value="1" {{ old('status', $row->status) == 1 ? 'selected' : '' }}>{{ __('status_pending') }}</option>
                    <option value="0" {{ old('status', $row->status) == 0 ? 'selected' : '' }}>{{ __('status_rejected') }}</option>
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label for="stage">{{ __('Current stage') }}</label>
                  <select id="stage" name="stage" class="form-control">
                    @foreach(\App\Models\Application::stageLabelMap() as $stageKey => $stageLabel)
                      <option value="{{ $stageKey }}" {{ old('stage', $row->stage) === $stageKey ? 'selected' : '' }}>{{ $stageLabel }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label for="progress">{{ __('Progress (%)') }}</label>
                  <input type="number" min="0" max="100" class="form-control" id="progress" name="progress" value="{{ old('progress', $row->progress) }}">
                </div>
                @if($fieldEnabled('application_program_choice_second'))
                <div class="form-group col-md-6">
                  <label for="second_program_choice_id">{{ __('Second programme choice') }}</label>
                  <select id="second_program_choice_id" name="second_program_choice_id" class="form-control">
                    <option value="">{{ __('select') }}</option>
                    @foreach($programs as $program)
                      <option value="{{ $program->id }}" {{ (int) old('second_program_choice_id', $row->second_program_choice_id) === $program->id ? 'selected' : '' }}>{{ $program->title }}</option>
                    @endforeach
                  </select>
                </div>
                @endif
                @if($fieldEnabled('application_program_choice_third'))
                <div class="form-group col-md-6">
                  <label for="third_program_choice_id">{{ __('Third programme choice') }}</label>
                  <select id="third_program_choice_id" name="third_program_choice_id" class="form-control">
                    <option value="">{{ __('select') }}</option>
                    @foreach($programs as $program)
                      <option value="{{ $program->id }}" {{ (int) old('third_program_choice_id', $row->third_program_choice_id) === $program->id ? 'selected' : '' }}>{{ $program->title }}</option>
                    @endforeach
                  </select>
                </div>
                @endif
                @if($fieldEnabled('application_registration_fee_bank'))
                <div class="form-group col-md-6">
                  <label for="registration_fee_bank">{{ __('Registration fee bank') }}</label>
                  <input type="text" class="form-control" id="registration_fee_bank" name="registration_fee_bank" value="{{ old('registration_fee_bank', $row->registration_fee_bank) }}">
                </div>
                @endif
                @if($fieldEnabled('application_registration_fee_reference'))
                <div class="form-group col-md-6">
                  <label for="registration_fee_reference">{{ __('Registration fee reference') }}</label>
                  <input type="text" class="form-control" id="registration_fee_reference" name="registration_fee_reference" value="{{ old('registration_fee_reference', $row->registration_fee_reference) }}">
                </div>
                @endif
              </div>
              @if($row->admissionFee)
              <div class="row g-3 mt-2">
                <div class="col-12">
                  <div class="alert alert-info">
                    <h6 class="mb-2">{{ __('Admission Fee Status') }}</h6>
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        {!! $row->admissionFee->status_badge !!}
                      </div>
                      <div class="text-end">
                        <small class="d-block">Amount: <strong>{{ number_format($row->admissionFee->fee_amount, $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol ?? '' !!}</strong></small>
                        <small class="d-block">Paid: <strong>{{ number_format($row->admissionFee->paid_amount, $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol ?? '' !!}</strong></small>
                        <small class="d-block">Balance: <strong>{{ number_format($row->admissionFee->remaining_balance, $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol ?? '' !!}</strong></small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              @endif
              @if($fieldEnabled('application_declaration'))
              <div class="row g-3 mt-2">
                <div class="form-group col-md-6">
                  <label for="declaration_name">{{ __('Declaration name') }}</label>
                  <input type="text" class="form-control" id="declaration_name" name="declaration_name" value="{{ old('declaration_name', $row->declaration_name) }}">
                </div>
                <div class="form-group col-md-6">
                  <label for="declaration_signed_date">{{ __('Declaration date') }}</label>
                  <input type="date" class="form-control" id="declaration_signed_date" name="declaration_signed_date" value="{{ old('declaration_signed_date', optional($row->declaration_signed_date)->format('Y-m-d')) }}">
                </div>
              </div>
              @endif
            </div>
          </div>
        </form>
      </div>

      <div class="col-lg-4 mb-4">
  <div class="card mb-4" id="board-review-accordion">
          <div class="card-header">
            <h5 class="mb-0">{{ __('Convert to student record') }}</h5>
          </div>
          <div class="card-block">
            <p class="text-muted">{{ __('Once all requirements are verified you can create the student profile from this application.') }}</p>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#convertApplicationModal">
              <i class="fas fa-user-check"></i> {{ __('Create student record') }}
            </button>

            @php
                $convertedStudent = \App\Models\Student::where('registration_no', $row->registration_no)->first();
            @endphp
            @if($convertedStudent)
            <div class="mt-3">
              <p class="text-muted mb-2"><i class="fas fa-check-circle text-success"></i> {{ __('Student record exists') }} (#{{ $convertedStudent->student_id }}).</p>
              <a href="{{ route('admin.application.acceptance-letter.download', $row->id) }}" class="btn btn-outline-primary btn-sm" target="_blank">
                <i class="fas fa-file-pdf"></i> {{ __('Download letter') }}
              </a>
              <form action="{{ route('admin.application.acceptance-letter.resend', $row->id) }}" method="post" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                  <i class="fas fa-paper-plane"></i> {{ __('Resend letter') }}
                </button>
              </form>
            </div>
            @endif
            <hr>
            <div class="small text-muted">
              <div class="mb-2"><strong>{{ __('Registration no.') }}:</strong> #{{ $row->registration_no }}</div>
              <div class="mb-2"><strong>{{ __('Applied') }}:</strong> {{ $formatDate($row->apply_date) ?? __('N/A') }}</div>
              <div class="mb-2"><strong>{{ __('Stage') }}:</strong> {{ $row->progress_label }}</div>
              <div><strong>{{ __('Portal access') }}:</strong> {{ $row->portal_last_login_at ? $valueOrNA($row->portal_last_login_at) : __('Never') }}</div>
            </div>
          </div>
        </div>

        @if($fieldEnabled('application_board_review'))
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Board review') }}</h5>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#board-review-body" aria-expanded="false" aria-controls="board-review-body">
              <i class="fas fa-pen"></i> {{ __('Edit') }}
            </button>
          </div>
          <div class="card-block collapse" id="board-review-body" data-bs-parent="#board-review-accordion">
            <div class="form-group">
              <label>{{ __('Meets university requirements') }}</label>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="board_meets_university" name="board_review[meets_university_requirements]" value="1" form="application-update-form" {{ old('board_review.meets_university_requirements', optional($row->boardReview)->meets_university_requirements) ? 'checked' : '' }}>
                <label class="form-check-label" for="board_meets_university">{{ __('Yes') }}</label>
              </div>
            </div>
            <div class="form-group">
              <label>{{ __('Meets programme requirements') }}</label>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="board_meets_program" name="board_review[meets_program_requirements]" value="1" form="application-update-form" {{ old('board_review.meets_program_requirements', optional($row->boardReview)->meets_program_requirements) ? 'checked' : '' }}>
                <label class="form-check-label" for="board_meets_program">{{ __('Yes') }}</label>
              </div>
            </div>
            <div class="form-group">
              <label for="first_choice_decision">{{ __('First choice decision') }}</label>
              <input type="text" class="form-control" id="first_choice_decision" name="board_review[first_choice_decision]" value="{{ old('board_review.first_choice_decision', optional($row->boardReview)->first_choice_decision) }}" form="application-update-form">
            </div>
            <div class="form-group">
              <label for="second_choice_decision">{{ __('Second choice decision') }}</label>
              <input type="text" class="form-control" id="second_choice_decision" name="board_review[second_choice_decision]" value="{{ old('board_review.second_choice_decision', optional($row->boardReview)->second_choice_decision) }}" form="application-update-form">
            </div>
            <div class="form-group">
              <label for="third_choice_decision">{{ __('Third choice decision') }}</label>
              <input type="text" class="form-control" id="third_choice_decision" name="board_review[third_choice_decision]" value="{{ old('board_review.third_choice_decision', optional($row->boardReview)->third_choice_decision) }}" form="application-update-form">
            </div>
            <div class="form-group">
              <label for="observation">{{ __('Observation') }}</label>
              <textarea class="form-control" id="observation" name="board_review[observation]" rows="3" form="application-update-form">{{ old('board_review.observation', optional($row->boardReview)->observation) }}</textarea>
            </div>
            <div class="form-group">
              <label for="rejection_reason">{{ __('Rejection reason') }}</label>
              <textarea class="form-control" id="rejection_reason" name="board_review[rejection_reason]" rows="3" form="application-update-form">{{ old('board_review.rejection_reason', optional($row->boardReview)->rejection_reason) }}</textarea>
            </div>
            <div class="form-group">
              <label for="reviewed_at">{{ __('Reviewed at') }}</label>
              <input type="datetime-local" class="form-control" id="reviewed_at" name="board_review[reviewed_at]" value="{{ old('board_review.reviewed_at', optional(optional($row->boardReview)->reviewed_at)->format('Y-m-d\TH:i')) }}" form="application-update-form">
            </div>
            <button type="submit" class="btn btn-primary btn-block" form="application-update-form">{{ __('Save board review') }}</button>
          </div>
        </div>
        @endif

        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">{{ __('Documents on file') }}</h5>
          </div>
          <div class="card-block">
            <div class="row">
              @foreach(['photo' => __('field_photo'), 'signature' => __('field_signature')] as $column => $label)
                @php $file = $row->$column; @endphp
                <div class="col-6 text-center mb-3">
                  <div class="border rounded p-2 h-100">
                    <div class="text-muted small mb-2">{{ $label }}</div>
                    @if($file && is_file(public_path('uploads/'.$path.'/'.$file)))
                      <a href="{{ asset('uploads/'.$path.'/'.$file) }}" data-lightbox="preview-{{ $column }}">
                        <img src="{{ asset('uploads/'.$path.'/'.$file) }}" alt="{{ $label }}" class="img-fluid">
                      </a>
                    @else
                      <span class="text-muted">{{ __('No file') }}</span>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Submitted Documents') }}</h5>
            </div>
            <div class="card-block p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Document') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
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
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        @if($fileExists)
                                            <a href="{{ $fileUrl }}" class="btn btn-sm btn-icon-only btn-outline-primary" @if($isPreviewable) data-lightbox="sidebar-doc-{{ $document->id }}" data-title="{{ $requirement['label'] }}" @else target="_blank" @endif title="{{ __('View') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ $fileUrl }}" class="btn btn-sm btn-icon-only btn-outline-secondary" download title="{{ __('Download') }}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
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
                                        <small class="text-muted">{{ __('Extra') }}</small>
                                    </td>
                                    <td>
                                        <span class="badge {{ $document->is_received ? 'badge-success' : 'badge-secondary' }}">{{ $document->is_received ? __('Received') : __('Stored') }}</span>
                                    </td>
                                    <td>
                                        @if($fileExists)
                                            <a href="{{ $fileUrl }}" class="btn btn-sm btn-icon-only btn-outline-primary" @if($isPreviewable) data-lightbox="sidebar-doc-extra-{{ $document->id }}" data-title="{{ $label }}" @else target="_blank" @endif title="{{ __('View') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ $fileUrl }}" class="btn btn-sm btn-icon-only btn-outline-secondary" download title="{{ __('Download') }}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
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
  </div>
</div>

<div class="modal fade" id="convertApplicationModal" tabindex="-1" aria-labelledby="convertApplicationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="convertApplicationModalLabel">{{ __('Create student record') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="convert-application-form" action="{{ route('admin.application.store') }}" method="post" class="needs-validation" novalidate>
        @csrf
        <div class="modal-body">
          <input type="hidden" name="registration_no" value="{{ $row->registration_no }}">
          <input type="hidden" name="first_name" value="{{ $row->first_name }}">
          <input type="hidden" name="last_name" value="{{ $row->last_name }}">
          <input type="hidden" name="father_name" value="{{ $row->father_name }}">
          <input type="hidden" name="mother_name" value="{{ $row->mother_name }}">
          <input type="hidden" name="father_occupation" value="{{ $row->father_occupation }}">
          <input type="hidden" name="mother_occupation" value="{{ $row->mother_occupation }}">
          <input type="hidden" name="email" value="{{ $row->email }}">
          <input type="hidden" name="gender" value="{{ $row->gender }}">
          <input type="hidden" name="dob" value="{{ optional($row->dob)->format('Y-m-d') }}">
          <input type="hidden" name="phone" value="{{ $row->phone }}">
          <input type="hidden" name="emergency_phone" value="{{ $row->emergency_phone }}">
          <input type="hidden" name="religion" value="{{ $row->religion }}">
          <input type="hidden" name="caste" value="{{ $row->caste }}">
          <input type="hidden" name="mother_tongue" value="{{ $row->mother_tongue }}">
          <input type="hidden" name="marital_status" value="{{ $row->marital_status }}">
          <input type="hidden" name="blood_group" value="{{ $row->blood_group }}">
          <input type="hidden" name="nationality" value="{{ $row->nationality }}">
          <input type="hidden" name="national_id" value="{{ $row->national_id }}">
          <input type="hidden" name="passport_no" value="{{ $row->passport_no }}">
          <input type="hidden" name="country" value="{{ $row->country }}">
          <input type="hidden" name="present_province" value="{{ $row->present_province }}">
          <input type="hidden" name="present_district" value="{{ $row->present_district }}">
          <input type="hidden" name="present_village" value="{{ $row->present_village }}">
          <input type="hidden" name="present_address" value="{{ $row->present_address }}">
          <input type="hidden" name="permanent_province" value="{{ $row->permanent_province }}">
          <input type="hidden" name="permanent_district" value="{{ $row->permanent_district }}">
          <input type="hidden" name="permanent_village" value="{{ $row->permanent_village }}">
          <input type="hidden" name="permanent_address" value="{{ $row->permanent_address }}">
          <input type="hidden" name="school_name" value="{{ $row->school_name }}">
          <input type="hidden" name="school_exam_id" value="{{ $row->school_exam_id }}">
          <input type="hidden" name="school_graduation_year" value="{{ $row->school_graduation_year }}">
          <input type="hidden" name="school_graduation_point" value="{{ $row->school_graduation_point }}">
          <input type="hidden" name="collage_name" value="{{ $row->collage_name }}">
          <input type="hidden" name="collage_exam_id" value="{{ $row->collage_exam_id }}">
          <input type="hidden" name="collage_graduation_year" value="{{ $row->collage_graduation_year }}">
          <input type="hidden" name="collage_graduation_point" value="{{ $row->collage_graduation_point }}">
          <input type="hidden" name="is_catholic_baptised" value="{{ $row->is_catholic_baptised ? '1' : '0' }}">
          <input type="hidden" name="is_confirmed" value="{{ $row->is_confirmed ? '1' : '0' }}">
          <input type="hidden" name="has_first_communion" value="{{ $row->has_first_communion ? '1' : '0' }}">

          <div class="alert alert-info">
            {{ __('Review the admission details below. These fields are required to create the student profile.') }}
          </div>

          <div class="row g-3">
            <div class="form-group col-md-6">
              <label for="convert_student_id">{{ __('field_student_id') }}</label>
              <div class="input-group">
                <input type="text" class="form-control @error('student_id') is-invalid @enderror" id="convert_student_id" name="student_id" value="{{ old('student_id') }}" placeholder="{{ __('Leave empty to auto-generate') }}">
                <button type="button" class="btn btn-outline-secondary" id="generate_student_id_btn" title="{{ __('Generate ID') }}">
                  <i class="fas fa-sync-alt"></i>
                </button>
              </div>
              <small class="form-text text-muted">{{ __('Leave empty to auto-generate, or enter a custom Student ID') }}</small>
              <small class="form-text text-info" id="auto_generated_preview"></small>
              @error('student_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_admission_date">{{ __('field_admission_date') }} <span>*</span></label>
              <input type="date" class="form-control @error('admission_date') is-invalid @enderror" id="convert_admission_date" name="admission_date" value="{{ old('admission_date', now()->format('Y-m-d')) }}" required>
              @error('admission_date')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_batch">{{ __('field_batch') }} <span>*</span></label>
              <select class="form-control batch @error('batch') is-invalid @enderror" id="convert_batch" name="batch" data-selected="{{ old('batch', $row->batch_id) }}" required>
                <option value="">{{ __('select') }}</option>
                @foreach($batches as $batch)
                  <option value="{{ $batch->id }}" {{ (int) old('batch', $row->batch_id) === $batch->id ? 'selected' : '' }}>{{ $batch->title }}</option>
                @endforeach
              </select>
              @error('batch')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_faculty">{{ __('field_faculty') }} <span>*</span></label>
              <select class="form-control faculty @error('faculty') is-invalid @enderror" id="convert_faculty" name="faculty" data-selected="{{ old('faculty', $row->program ? $row->program->faculty_id : '') }}" required>
                <option value="">{{ __('select') }}</option>
                @foreach($faculties as $faculty)
                  <option value="{{ $faculty->id }}" {{ (int) old('faculty', $row->program ? $row->program->faculty_id : '') === $faculty->id ? 'selected' : '' }}>{{ $faculty->title }}</option>
                @endforeach
              </select>
              @error('faculty')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_program">{{ __('field_program') }} <span>*</span></label>
              <select class="form-control program @error('program') is-invalid @enderror" id="convert_program" name="program" data-selected="{{ old('program', $row->program_id) }}" required>
                <option value="">{{ __('select') }}</option>
                @foreach($programs as $program)
                  <option value="{{ $program->id }}" {{ (int) old('program', $row->program_id) === $program->id ? 'selected' : '' }}>{{ $program->title }}</option>
                @endforeach
              </select>
              @error('program')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_session">{{ __('field_session') }} <span>*</span></label>
              <select class="form-control session @error('session') is-invalid @enderror" id="convert_session" name="session" data-selected="{{ old('session', $row->session_id) }}" required>
                <option value="">{{ __('select') }}</option>
              </select>
              @error('session')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_semester">{{ __('field_semester') }} <span>*</span></label>
              <select class="form-control semester @error('semester') is-invalid @enderror" id="convert_semester" name="semester" data-selected="{{ old('semester') }}" required>
                <option value="">{{ __('select') }}</option>
              </select>
              @error('semester')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_section">{{ __('field_section') }} <span>*</span></label>
              <select class="form-control section @error('section') is-invalid @enderror" id="convert_section" name="section" data-selected="{{ old('section') }}" required>
                <option value="">{{ __('select') }}</option>
              </select>
              @error('section')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-group col-md-6">
              <label for="convert_statuses">{{ __('field_status') }}</label>
              @php $statusFieldHasError = $errors->has('statuses') || $errors->has('statuses.*'); @endphp
              <select class="form-control select2{{ $statusFieldHasError ? ' is-invalid' : '' }}" id="convert_statuses" name="statuses[]" multiple>
                @foreach($statuses as $status)
                  <option value="{{ $status->id }}" {{ collect(old('statuses', []))->contains($status->id) ? 'selected' : '' }}>{{ $status->title }}</option>
                @endforeach
              </select>
              @if($statusFieldHasError)
                <div class="invalid-feedback d-block">{{ $errors->first('statuses') ?? $errors->first('statuses.*') }}</div>
              @endif
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('btn_submit') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/lightbox2-master/js/lightbox.min.js') }}"></script>
@php
  $conversionErrorKeys = collect($errors->keys())
    ->filter(function ($key) {
      return in_array($key, ['student_id','admission_date','batch','program','session','semester','section','statuses'], true)
        || \Illuminate\Support\Str::startsWith($key, 'statuses.');
    })
    ->values()
    ->all();
@endphp
<script>
  (function($) {
    'use strict';
    var guardianIndex = {{ isset($guardianRows) ? count($guardianRows) : 0 }};
    var historyIndex = {{ isset($historyRows) ? count($historyRows) : 0 }};
    var languageIndex = {{ isset($languageRows) ? count($languageRows) : 0 }};

    $('#add-guardian-row').on('click', function() {
      var index = guardianIndex++;
      var row = '<tr data-index="' + index + '">' +
        '<td class="text-center"><input type="checkbox" name="guardians[' + index + '][is_primary]" value="1"></td>' +
        '<td><input type="hidden" name="guardians[' + index + '][id]" value=""><input type="text" class="form-control" name="guardians[' + index + '][full_name]"></td>' +
        '<td><input type="text" class="form-control" name="guardians[' + index + '][relationship]"></td>' +
        '<td><select class="form-control" name="guardians[' + index + '][type]">@foreach($guardianTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></td>' +
        '<td><input type="text" class="form-control" name="guardians[' + index + '][phone_primary]"></td>' +
        '<td><input type="text" class="form-control" name="guardians[' + index + '][phone_secondary]"></td>' +
        '<td><input type="email" class="form-control" name="guardians[' + index + '][email]"></td>' +
        '<td>' +
          '<input type="text" class="form-control mb-1" name="guardians[' + index + '][address_line1]" placeholder="{{ __('Address line 1') }}">' +
          '<input type="text" class="form-control mb-1" name="guardians[' + index + '][address_line2]" placeholder="{{ __('Address line 2') }}">' +
          '<input type="text" class="form-control mb-1" name="guardians[' + index + '][city]" placeholder="{{ __('City') }}">' +
          '<input type="text" class="form-control mb-1" name="guardians[' + index + '][state]" placeholder="{{ __('State / region') }}">' +
          '<select class="form-control" name="guardians[' + index + '][country]">' + countryOptionsHtml() + '</select>' +
        '</td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-icon-only remove-guardian-row"><i class="fas fa-trash-alt"></i></button></td>' +
        '</tr>';
      $('#guardian-table tbody').append(row);
    });

    $(document).on('click', '.remove-guardian-row', function() {
      $(this).closest('tr').remove();
    });

@include('partials.country-options-js')
    $('#add-history-row').on('click', function() {
      var index = historyIndex++;
      var row = '<tr data-index="' + index + '">' +
        '<td><input type="hidden" name="academic_history[' + index + '][id]" value="">' +
          '<input type="hidden" name="academic_history[' + index + '][qualification_key]" value="">' +
          '<input type="text" class="form-control" name="academic_history[' + index + '][certificate_obtained]" placeholder="{{ __('Certificate obtained') }}">' +
          '<small class="text-muted">{{ __('Added by applicant') }}</small></td>' +
        '<td><input type="text" class="form-control mb-1" name="academic_history[' + index + '][awarding_body]" placeholder="{{ __('Awarding body') }}"><input type="text" class="form-control" name="academic_history[' + index + '][institution_name]" placeholder="{{ __('Institution name') }}"></td>' +
        '<td><select class="form-control form-control-sm mb-1" name="academic_history[' + index + '][country]">' + countryOptionsHtml() + '</select><input type="text" class="form-control" name="academic_history[' + index + '][city]" placeholder="{{ __('City') }}"></td>' +
        '<td><input type="text" class="form-control" name="academic_history[' + index + '][instruction_language]"></td>' +
        '<td><input type="number" class="form-control mb-1" min="1900" max="2200" name="academic_history[' + index + '][start_year]" placeholder="{{ __('From') }}"><input type="number" class="form-control" min="1900" max="2200" name="academic_history[' + index + '][end_year]" placeholder="{{ __('To') }}"></td>' +
        '<td>' +
          '<input type="text" class="form-control mb-1" name="academic_history[' + index + '][gce_ol_detail]" placeholder="{{ __('GCE O/L or Probatoire detail') }}">' +
          '<input type="text" class="form-control mb-1" name="academic_history[' + index + '][gce_al_detail]" placeholder="{{ __('GCE A/L or Baccalaureate detail') }}">' +
          '<input type="text" class="form-control mb-1" name="academic_history[' + index + '][probatoire_detail]" placeholder="{{ __('Probatoire detail') }}">' +
          '<input type="text" class="form-control mb-1" name="academic_history[' + index + '][baccalaureate_detail]" placeholder="{{ __('Baccalaureate detail') }}">' +
          '<textarea class="form-control" name="academic_history[' + index + '][notes]" rows="2" placeholder="{{ __('Notes') }}"></textarea>' +
        '</td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-icon-only remove-history-row"><i class="fas fa-trash-alt"></i></button></td>' +
        '</tr>';
      $('#academic-history-table tbody').append(row);
    });

    $(document).on('click', '.remove-history-row', function() {
      $(this).closest('tr').remove();
    });

    $('#add-language-row').on('click', function() {
      var index = languageIndex++;
      var row = '<tr data-index="' + index + '">' +
        '<td><input type="hidden" name="languages[' + index + '][id]" value=""><input type="text" class="form-control" name="languages[' + index + '][language]"></td>' +
        '<td><input type="number" min="0" class="form-control" name="languages[' + index + '][years_of_study]"></td>' +
        '<td><select class="form-control" name="languages[' + index + '][fluency_level]">@foreach($fluencyOptions as $option)<option value="{{ $option }}">{{ ucfirst($option) }}</option>@endforeach</select></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-icon-only remove-language-row"><i class="fas fa-trash-alt"></i></button></td>' +
        '</tr>';
      $('#language-table tbody').append(row);
    });

    $(document).on('click', '.remove-language-row', function() {
      $(this).closest('tr').remove();
    });

    var convertPrefill = {
      batch: @json(old('batch', $row->batch_id)),
      program: @json(old('program', $row->program_id)),
      session: @json(old('session')),
      semester: @json(old('semester')),
      section: @json(old('section'))
    };
    var conversionErrorKeys = @json($conversionErrorKeys);

    // Function to generate Student ID in conversion modal
    function generateConversionStudentId(forceUpdate) {
      var facultyId = $('#convert_faculty').val();
      var batchId = $('#convert_batch').val();
      var programId = $('#convert_program').val();
      var $studentId = $('#convert_student_id');
      var $preview = $('#auto_generated_preview');
      
      if (!facultyId || !batchId) {
        console.log('Faculty or Batch not selected yet');
        $preview.text('');
        return;
      }
      
      console.log('Generating student ID for Faculty:', facultyId, 'Batch:', batchId, 'Program:', programId);
      
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
          console.log('Generated Student ID:', response.student_id);
          // Always show preview
          $preview.html('<i class="fas fa-magic"></i> {{ __("Auto-generated ID") }}: <strong>' + response.student_id + '</strong>');
          // Only fill the field if it's empty or forceUpdate is true
          if (forceUpdate || !$studentId.val().trim()) {
            $studentId.val(response.student_id);
          }
        },
        error: function(xhr) {
          console.error('Error generating student ID:', xhr.responseJSON);
          $preview.text('');
          if (xhr.responseJSON && xhr.responseJSON.message) {
            alert('Error: ' + xhr.responseJSON.message);
          }
        }
      });
    }

    // Generate button click handler
    $('#generate_student_id_btn').on('click', function() {
      generateConversionStudentId(true);
    });

    $('#convertApplicationModal').on('shown.bs.modal', function () {
      $('#convert_student_id').trigger('focus');
      var $batch = $('#convert_batch');
      var $faculty = $('#convert_faculty');
      var $program = $('#convert_program');

      // Attach student ID generation to faculty, batch, and program changes
      $('#convert_faculty, #convert_batch, #convert_program').off('change.studentid').on('change.studentid', function() {
        generateConversionStudentId(false);
      });

      if(convertPrefill.batch){
        $batch.val(convertPrefill.batch);
      }

      if(convertPrefill.program){
        $program.val(convertPrefill.program);
        setTimeout(function(){
          $program.trigger('change');
        }, 300);
      }
      
      // Generate student ID if both faculty and batch are already selected
      setTimeout(function(){
        if ($faculty.val() && $batch.val()) {
          generateConversionStudentId(false);
        }
      }, 500);
    });

    if(conversionErrorKeys.length){
      var convertModalElement = document.getElementById('convertApplicationModal');
      if(convertModalElement){
        if(typeof bootstrap !== 'undefined' && bootstrap.Modal){
          var convertModal = bootstrap.Modal.getOrCreateInstance(convertModalElement);
          convertModal.show();
        } else {
          $(convertModalElement).modal('show');
        }
      }
    }

    // Toggle Catholic Sacraments based on selected religion
    var religions = {!! $religions_json !!};
    var isInitialLoad = true;
    
    function toggleCatholicSacramentsAdmin() {
      var $religionDropdown = $('#religion');
      var selectedReligionId = $religionDropdown.length ? $religionDropdown.val() : null;
      
      // Check if religion dropdown exists and has a Catholic religion selected
      var isCatholic = selectedReligionId && religions[selectedReligionId] && religions[selectedReligionId].is_catholic == 1;
      
      if (isCatholic) {
        $('#catholic-sacraments-container-admin').slideDown(300);
      } else {
        $('#catholic-sacraments-container-admin').slideUp(300);
        // Only uncheck checkboxes when user explicitly changes religion (not on initial load)
        // This preserves existing values from the database
        if (!isInitialLoad) {
          $('#is_catholic_baptised, #is_confirmed, #has_first_communion').prop('checked', false);
        }
      }
    }

    // Trigger on religion dropdown change
    $('#religion').on('change', function() {
      isInitialLoad = false;
      toggleCatholicSacramentsAdmin();
    });
    
    // Run on page load to show/hide based on current selection
    toggleCatholicSacramentsAdmin();
    // After initial load, any subsequent toggles should clear checkboxes
    isInitialLoad = false;

    // Toggle resubmission reason field visibility
    $('.resubmission-toggle').on('change', function() {
      var key = $(this).data('key');
      var $reasonContainer = $('#resubmission-reason-' + key);
      if ($(this).is(':checked')) {
        $reasonContainer.slideDown(200);
        $reasonContainer.find('input').focus();
      } else {
        $reasonContainer.slideUp(200);
        $reasonContainer.find('input').val('');
      }
    });

    // "Same as Current Residence" → mirror present address into permanent address.
    (function () {
      var $toggle = $('#same_as_residence_edit');
      if (!$toggle.length) return;
      var pairs = [
        ['present_province_edit', 'permanent_province_edit'],
        ['present_district_edit', 'permanent_district_edit'],
        ['present_village', 'permanent_village'],
        ['present_address', 'permanent_address'],
      ];
      function sync() {
        var on = $toggle.is(':checked');
        pairs.forEach(function (p) {
          var $src = $('#' + p[0]); var $dst = $('#' + p[1]);
          if (!$dst.length) return;
          if (on) { $dst.val($src.val()).prop('readonly', true); }
          else { $dst.prop('readonly', false); }
        });
      }
      $toggle.on('change', sync);
      pairs.forEach(function (p) {
        $('#' + p[0]).on('input', function () {
          if ($toggle.is(':checked')) { $('#' + p[1]).val($(this).val()); }
        });
      });
    })();

  })(jQuery);
</script>
@include('common.js.batch_filter')
@endsection