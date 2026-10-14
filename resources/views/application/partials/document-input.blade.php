{{--
    A single checklist document upload.

    Used from two places so they can never drift apart:
      - Applicant Information → Identification (identity documents)
      - Documents step (everything else)

    Expects: $key, $document (label/description/required), $uploadedDocs (keyBy document_type)

    The file is sent the moment it is chosen, and this says straight away whether
    it arrived. Before, a file only reached the server when the whole form was
    saved, and the applicant was told "New file ready" - which is not the same
    thing as uploaded, and left them with no way of knowing when it had not
    worked.
--}}
@php
    $existingDoc = $uploadedDocs->get($key);
    $hasExistingFile = $existingDoc && $existingDoc->file_path;
@endphp
<div class="mb-3 document-field" data-document-field="{{ $key }}">
    <label for="document_{{ $key }}" class="form-label d-flex align-items-center flex-wrap gap-2">
        <span>{{ $document['label'] }}</span>

        {{-- Said on every document, not only the ones still missing, so an
             applicant can tell at a glance what they must provide. --}}
        @if($document['required'])
            <span class="badge bg-danger">{{ __('Required') }}</span>
        @else
            <span class="badge bg-secondary">{{ __('Optional') }}</span>
        @endif
    </label>

    <div class="document-state mb-2 {{ $hasExistingFile ? '' : 'd-none' }}"
         data-document-state="{{ $key }}">
        <div class="alert alert-success py-2 px-3 mb-0 d-flex align-items-center flex-wrap gap-2">
            <span><i class="fas fa-check-circle me-1"></i> <span class="document-state-text">{{ __('Uploaded') }}</span></span>
            <a href="{{ $hasExistingFile ? asset('uploads/student/'.$existingDoc->file_path) : '#' }}"
               target="_blank" class="btn btn-sm btn-outline-primary document-state-view">
                <i class="fas fa-eye"></i> {{ __('View') }}
            </a>
            <small class="d-block w-100 text-muted mt-1">{{ __('Choose another file below to replace it.') }}</small>
        </div>
    </div>

    <div class="document-error alert alert-danger py-2 px-3 mb-2 d-none" data-document-error="{{ $key }}"></div>

    <div class="document-progress text-muted small mb-2 d-none" data-document-progress="{{ $key }}">
        <i class="fas fa-spinner fa-spin me-1"></i> {{ __('Uploading...') }}
    </div>

    <input type="file"
           class="form-control document-input"
           data-document-key="{{ $key }}"
           data-has-existing="{{ $hasExistingFile ? '1' : '0' }}"
           data-max-size-mb="10"
           data-upload-url="{{ isset($application) && $application->id ? route('application.document.upload', ['application' => $application->id, 'key' => $key]) : '' }}"
           name="documents[{{ $key }}][file]"
           id="document_{{ $key }}"
           accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
           @if($document['required'] && !$hasExistingFile) required @endif>

    <small class="form-text text-muted d-block">
        {{ __('JPG, PNG or PDF, up to 10 MB.') }}
    </small>

    @if(!empty($document['description']))
        <small class="document-help">{{ $document['description'] }}</small>
    @endif

    <textarea class="form-control mt-2" name="documents[{{ $key }}][note]" rows="1"
              placeholder="{{ __('Notes (optional)') }}">{{ old('documents.'.$key.'.note', $existingDoc->notes ?? '') }}</textarea>
</div>
