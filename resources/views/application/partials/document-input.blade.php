{{--
    A single checklist document upload.

    Used from two places so they can never drift apart:
      - Applicant Information → Identification (identity documents)
      - Documents step (everything else)

    Expects: $key, $document (label/description/required), $uploadedDocs (keyBy document_type)
--}}
@php
    $existingDoc = $uploadedDocs->get($key);
    $hasExistingFile = $existingDoc && $existingDoc->file_path;
@endphp
<div class="mb-3">
    <label for="document_{{ $key }}" class="form-label">
        {{ $document['label'] }}
        @if($document['required'] && !$hasExistingFile)<span>*</span>@endif
    </label>

    @if($hasExistingFile)
        <div class="alert alert-success py-2 px-3 mb-2">
            <i class="fas fa-check-circle me-1"></i> {{ __('Document uploaded') }}
            <a href="{{ asset('uploads/student/'.$existingDoc->file_path) }}" target="_blank" class="ms-2 btn btn-sm btn-outline-primary">
                <i class="fas fa-eye"></i> {{ __('View') }}
            </a>
            <small class="d-block text-muted mt-1">{{ __('Upload a new file below to replace') }}</small>
        </div>
    @endif

    <input type="file"
           class="form-control document-input"
           data-document-key="{{ $key }}"
           data-has-existing="{{ $hasExistingFile ? '1' : '0' }}"
           data-max-size-mb="10"
           name="documents[{{ $key }}][file]"
           id="document_{{ $key }}"
           accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
           @if($document['required'] && !$hasExistingFile) required @endif>

    @if(!empty($document['description']))
        <small class="document-help">{{ $document['description'] }}</small>
    @endif

    <textarea class="form-control mt-2" name="documents[{{ $key }}][note]" rows="1"
              placeholder="{{ __('Notes (optional)') }}">{{ old('documents.'.$key.'.note', $existingDoc->notes ?? '') }}</textarea>
</div>
