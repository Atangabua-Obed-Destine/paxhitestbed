@extends('application.portal.layout')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="portal-card position-relative mb-4 overflow-hidden" style="background: linear-gradient(135deg, #182b49 0%, #667eea 100%);">
            <div style="position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%); transform: rotate(30deg); pointer-events: none;"></div>
            
            <div class="p-4 p-md-5 position-relative z-index-1">
                <div class="d-flex justify-content-between align-items-start flex-column flex-md-row">
                    <div>
                        <h3 class="mb-1 text-white fw-bold">{{ __('Application Tracking') }}</h3>
                        <p class="mb-0 text-white-50 fs-5">{{ optional($application->program)->title ?? __('Programme not set') }}</p>
                    </div>
                    <div class="mt-3 mt-md-0 text-md-end">
                        <span class="badge bg-white text-primary rounded-pill px-3 py-2 fw-bold shadow-sm">
                            <i class="fas fa-hashtag me-1 opacity-50"></i>{{ $application->registration_no }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="portal-card p-4 p-md-5">
            <h5 class="fw-bold mb-4 border-bottom pb-3">{{ __('Status Updates') }}</h5>
            
            <div class="timeline-container">
                @forelse($timeline as $index => $item)
                    <div class="timeline-item position-relative ps-5 mb-4">
                        <!-- Timeline connector line (handled in CSS, but we can override via inline if needed) -->
                        @if(!$loop->last)
                            <div class="position-absolute" style="left: 11px; top: 24px; bottom: -30px; width: 2px; background-color: #e2e8f0;"></div>
                        @endif
                        
                        <!-- Timeline node -->
                        <div class="position-absolute rounded-circle shadow-sm d-flex align-items-center justify-content-center" 
                             style="left: 0; top: 0; width: 24px; height: 24px; background-color: {{ $loop->first ? '#667eea' : '#e2e8f0' }}; border: 3px solid #fff;">
                            @if($loop->first)
                                <div class="rounded-circle bg-white" style="width: 8px; height: 8px;"></div>
                            @endif
                        </div>
                        
                        <div class="bg-light rounded p-4 border border-1 border-light">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 fw-bold {{ $loop->first ? 'text-primary' : 'text-dark' }}">
                                    {{ $item->title ?? ($application::stageLabelMap()[$item->stage] ?? ucfirst(str_replace('_', ' ', $item->stage))) }}
                                </h6>
                                <span class="badge bg-white text-muted border px-2 py-1 small">
                                    <i class="far fa-clock me-1"></i>{{ $item->created_at->diffForHumans() }}
                                </span>
                            </div>
                            
                            <p class="text-muted small mb-0"><i class="far fa-calendar-alt me-1"></i>{{ $item->created_at->format('F j, Y \a\t g:i A') }}</p>
                            
                            @if($item->note)
                                <div class="mt-3 p-3 bg-white rounded border-start border-3 border-primary text-secondary">
                                    {{ $item->note }}
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center p-5 bg-light rounded">
                        <i class="fas fa-hourglass-half fa-2x text-muted mb-3 opacity-50"></i>
                        <p class="text-muted mb-0">{{ __('No updates yet. Your application is currently being processed.') }}</p>
                    </div>
                @endforelse
            </div>
            
            <div class="text-center mt-4">
                <a href="{{ route('application.dashboard') }}" class="btn btn-light rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}
                </a>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Override global timeline CSS for this specific premium look */
    .timeline-item::before, .timeline-item::after { display: none !important; }
    .timeline-item { padding-left: 0 !important; }
</style>
@endpush
@endsection
