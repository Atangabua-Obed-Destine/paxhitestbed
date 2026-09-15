@php
    $showUpdateForm = $showUpdateForm ?? true;
    $timelineCollection = $timeline ?? collect();
@endphp

<div class="col-md-7">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Application timeline') }}</h5>
            <span class="badge badge-primary">{{ $row->progress_label }}</span>
        </div>
        <div class="card-block">
            <div class="p-3">
                @forelse($timelineCollection as $item)
                    <div class="timeline-item">
                        <h6 class="mb-1">{{ $item->title ?? ($row::stageLabelMap()[$item->stage] ?? ucfirst(str_replace('_', ' ', $item->stage))) }}</h6>
                        <small class="text-muted d-block">{{ $item->created_at->format('F j, Y g:i A') }}</small>
                        <div class="mt-1">
                            <span class="badge badge-light">{{ __('Visible to applicant') }}: {{ $item->is_visible_to_applicant ? __('Yes') : __('No') }}</span>
                        </div>
                        @if($item->note)
                            <p class="mb-0 mt-2">{{ $item->note }}</p>
                        @endif
                        <hr>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('No timeline events yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if($showUpdateForm)
<div class="col-md-5">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Add status update') }}</h5>
        </div>
        <div class="card-block">
            <form action="{{ route('admin.application.status-update', $row->id) }}" method="post" class="p-3 needs-validation" novalidate>
                @csrf
                {{-- This form carried a stage, a decision override and a
                     progress percentage, which made it a second way to approve
                     an admission with no permission check at all. Decisions are
                     taken on the approval panel now. What is left is a note —
                     which is what the form is called. --}}
                <div class="alert alert-light border small mb-3">
                    <i class="fas fa-info-circle text-muted"></i>
                    {{ __('A note against the current stage, :stage. To move the application, use the admission approvals.', ['stage' => $row->progress_label]) }}
                </div>
                <div class="form-group">
                    <label for="title">{{ __('Title') }}</label>
                    <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="note">{{ __('Staff note / applicant message') }} <span>*</span></label>
                    <textarea name="note" id="note" class="form-control" rows="3" maxlength="2000" required>{{ old('note') }}</textarea>
                    <div class="invalid-feedback">{{ __('required_field') }}</div>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="is_visible_to_applicant" name="is_visible_to_applicant" value="1" {{ old('is_visible_to_applicant', '1') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_visible_to_applicant">{{ __('Show update to applicant') }}</label>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">{{ __('Add update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
