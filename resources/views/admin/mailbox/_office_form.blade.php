<form method="post" action="{{ $action }}" class="row g-2 align-items-end" style="max-width:60rem">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif
    <div class="col-md-4">
        <label class="form-label">{{ __('Name') }}</label>
        <input name="title" class="form-control" required maxlength="120" value="{{ $office?->title }}" placeholder="{{ __('e.g. Bursary') }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">{{ __('What it handles (shown to students)') }}</label>
        <input name="description" class="form-control" maxlength="255" value="{{ $office?->description }}" placeholder="{{ __('e.g. Fees, receipts and payment plans') }}">
    </div>
    <div class="col-md-9">
        <label class="form-label">{{ __('Staff who answer') }}</label>
        <select name="members[]" class="form-control select2-multiple" multiple required style="width:100%">
            @foreach($staff as $s)
            <option value="{{ $s->id }}" @selected($office && $office->members->contains('id', $s->id))>{{ trim($s->first_name . ' ' . $s->last_name) }} ({{ $s->staff_id }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        @if($office)
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="status" value="1" id="office-status-{{ $office->id }}" @checked($office->status)>
            <label class="form-check-label" for="office-status-{{ $office->id }}">{{ __('Open to students') }}</label>
        </div>
        @endif
        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-save"></i> {{ __('Save') }}</button>
    </div>
</form>
