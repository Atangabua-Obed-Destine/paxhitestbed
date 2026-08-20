{{--
    One qualification card: the details of a qualification together with the
    document(s) that evidence it.

    Shared by the applicant wizard and the admin application forms so neither can
    drift into asking for a certificate the other already collected.

    Expects:
      $index    integer position in the academic_history array
      $card     ['key','label','description','required','documents','history']
                key === null means an applicant-added extra qualification
      $uploadedDocs  documents keyed by document_type
      $readonly (optional) render as a summary instead of inputs
--}}
@php
    $history = $card['history'] ?? [];
    $isPrescribed = !empty($card['key']);
    $field = fn ($name, $default = null) => $history[$name] ?? $default;

    $currentYear = (int) date('Y');
    $yearFloor = $currentYear - 60;

    // A prescribed card names the qualification it wants; the applicant may
    // overwrite it when their certificate is an equivalent with another name.
    $qualificationValue = $field('certificate_obtained') ?: ($isPrescribed ? $card['label'] : '');
    $sameAsAwarding = (bool) $field('institution_same_as_awarding_body', false);
@endphp

<div class="repeater-item academic-item qualification-card" data-index="{{ $index }}" data-qualification-key="{{ $card['key'] ?? '' }}">
    @unless($isPrescribed)
        <div class="repeater-actions">
            <button type="button" class="remove-academic" aria-label="{{ __('Remove qualification') }}">&times;</button>
        </div>
    @endunless

    <div class="qualification-card-header">
        <h5 class="qualification-card-title">
            {{ $card['label'] }}
            @if($isPrescribed && ($card['required'] ?? true))<span class="text-danger">*</span>@endif
        </h5>
        @if(!empty($card['description']))
            <p class="qualification-card-description">{{ $card['description'] }}</p>
        @endif
    </div>

    {{-- Binds the row back to its card when the form is re-rendered. --}}
    <input type="hidden" name="academic_history[{{ $index }}][qualification_key]" value="{{ $card['key'] ?? '' }}">

    <div class="row">
        <div class="form-group col-md-6">
            <label>{{ __('Certificate / Qualification') }} @if($isPrescribed)<span>*</span>@endif</label>
            <input type="text" class="form-control" name="academic_history[{{ $index }}][certificate_obtained]"
                   value="{{ $qualificationValue }}" @if($isPrescribed) required @endif>
            <small class="document-help">{{ __('If your certificate has a different name, enter that name as the equivalent.') }}</small>
        </div>
        <div class="form-group col-md-6">
            <label>{{ __('Awarding body') }} @if($isPrescribed)<span>*</span>@endif</label>
            <input type="text" class="form-control awarding-body-input" name="academic_history[{{ $index }}][awarding_body]"
                   value="{{ $field('awarding_body') }}" @if($isPrescribed) required @endif>
            <small class="document-help">{{ __('The organisation that issued the qualification.') }}</small>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-6">
            <label>{{ __('School / Institution attended') }} @if($isPrescribed)<span>*</span>@endif</label>
            <input type="text" class="form-control institution-name-input" name="academic_history[{{ $index }}][institution_name]"
                   value="{{ $field('institution_name') }}" @if($isPrescribed) required @endif @if($sameAsAwarding) readonly @endif>
            <small class="document-help">{{ __('The school, university or training centre where the applicant studied.') }}</small>
            <div class="form-check mt-2">
                {{-- Mirrors the awarding body into the institution field; most
                     applicants sat the examination at the school itself. --}}
                <input type="hidden" name="academic_history[{{ $index }}][institution_same_as_awarding_body]" value="0">
                <input type="checkbox" class="form-check-input same-as-awarding" value="1"
                       name="academic_history[{{ $index }}][institution_same_as_awarding_body]"
                       id="same_as_awarding_{{ $index }}" @if($sameAsAwarding) checked @endif>
                <label class="form-check-label" for="same_as_awarding_{{ $index }}">
                    {{ __('Same as awarding body') }} <small class="text-muted">{{ __('Optional') }}</small>
                </label>
            </div>
        </div>
        <div class="form-group col-md-6">
            <label>{{ __('Language of instruction') }}</label>
            <input type="text" class="form-control" name="academic_history[{{ $index }}][instruction_language]"
                   value="{{ $field('instruction_language') }}">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-6">
            <label>{{ __('Country where studied') }}</label>
            @include('partials.country-select', [
                'name' => "academic_history[{$index}][country]",
                'value' => $field('country'),
            ])
        </div>
        <div class="form-group col-md-6">
            <label>{{ __('City / Town / Village where studied') }}</label>
            <input type="text" class="form-control" name="academic_history[{{ $index }}][city]"
                   value="{{ $field('city') }}">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-md-6">
            <label>{{ __('Start year') }}</label>
            <select class="form-control" name="academic_history[{{ $index }}][start_year]">
                <option value="">{{ __('Select') }}</option>
                @for($year = $currentYear + 1; $year >= $yearFloor; $year--)
                    <option value="{{ $year }}" @if((int) $field('start_year') === $year) selected @endif>{{ $year }}</option>
                @endfor
            </select>
        </div>
        <div class="form-group col-md-6">
            <label>{{ __('Completion year') }}</label>
            <select class="form-control" name="academic_history[{{ $index }}][end_year]">
                <option value="">{{ __('Select') }}</option>
                @for($year = $currentYear + 1; $year >= $yearFloor; $year--)
                    <option value="{{ $year }}" @if((int) $field('end_year') === $year) selected @endif>{{ $year }}</option>
                @endfor
            </select>
        </div>
    </div>

    @if(!empty($card['documents']))
        {{-- The evidence for this qualification, collected here and nowhere
             else. Each slot is a checklist document an administrator assigned
             to this card, so it still stores under its own document key. --}}
        <div class="qualification-card-documents">
            @foreach($card['documents'] as $documentKey => $document)
                @include('application.partials.document-input', [
                    'key' => $documentKey,
                    'document' => $document,
                    'uploadedDocs' => $uploadedDocs,
                ])
            @endforeach
        </div>
    @elseif(!$isPrescribed)
        {{-- An applicant-added qualification has no configured document key, so
             it keeps its own upload on the history row itself. --}}
        <div class="form-group">
            <label>{{ __('Upload certificate or result slip') }} <small class="text-muted">{{ __('Optional') }}</small></label>
            <input type="hidden" name="academic_history[{{ $index }}][existing_certificate_file]" value="{{ $field('certificate_file') }}">
            @if($field('certificate_file'))
                <div class="alert alert-success py-2 px-3 mb-2">
                    <i class="fas fa-check-circle me-1"></i> {{ __('Document uploaded') }}
                    <a href="{{ asset('uploads/student/'.$field('certificate_file')) }}" target="_blank" class="ms-2 btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i> {{ __('View') }}
                    </a>
                    <small class="d-block text-muted mt-1">{{ __('Upload a new file below to replace') }}</small>
                </div>
            @endif
            <input type="file" class="form-control size-guard" data-max-size-mb="10"
                   name="academic_history[{{ $index }}][certificate_file]"
                   accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
            <small class="document-help">{{ __('PDF, JPG or PNG. Maximum file size 10 MB.') }}</small>
        </div>
    @endif
</div>
