@extends('application.portal.layout')

@section('content')
<div class="portal-card card mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">{{ __('Application timeline') }}</h5>
            <small class="text-muted">{{ __('Application ID') }}: #{{ $application->registration_no }}</small>
        </div>
    </div>
    <div class="card-body p-4">
        @forelse($timeline as $item)
            <div class="timeline-item">
                <h6 class="mb-1">{{ $item->title ?? ($application::stageLabelMap()[$item->stage] ?? ucfirst(str_replace('_', ' ', $item->stage))) }}</h6>
                <small class="text-muted d-block">{{ $item->created_at->format('F j, Y g:i A') }}</small>
                @if($item->note)
                    <p class="mb-0 mt-2">{{ $item->note }}</p>
                @endif
            </div>
        @empty
            <p class="text-muted mb-0">{{ __('No updates yet. Your application is currently being processed.') }}</p>
        @endforelse
    </div>
</div>
@endsection
