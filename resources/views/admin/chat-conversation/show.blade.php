@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header">
                <h5>{{ $row->actorName() ?? __('Deleted record') }}</h5>
                <span class="text-muted d-block mt-1" style="font-size:.85rem;">
                    {{ ucfirst($row->actor_type) }} · {{ ucfirst($row->surface) }} ·
                    {{ optional($row->last_message_at)->format('M j, Y g:i A') }}
                </span>
            </div>

            <div class="card-block">
                @foreach($messages as $message)
                    @if($message->role === 'tool')
                        {{-- Audit row: what the assistant looked up, and whether it was allowed. --}}
                        <div class="border rounded p-2 mb-2" style="background:#fbfbfc;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <code>{{ $message->tool_name }}</code>
                                    @if($message->tool_status === 'ok')
                                        <span class="badge bg-success">{{ __('allowed') }}</span>
                                    @elseif($message->tool_status === 'denied')
                                        <span class="badge bg-danger">{{ __('denied') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('error') }}</span>
                                    @endif
                                </div>
                                <small class="text-muted">{{ $message->latency_ms }} ms</small>
                            </div>
                            <small class="d-block text-muted mt-1">
                                {{ __('Arguments') }}: {{ json_encode($message->tool_args) ?: '{}' }}
                            </small>
                            @if($message->error)
                                <small class="d-block text-danger mt-1">{{ $message->error }}</small>
                            @endif
                        </div>
                    @else
                        <div class="mb-3">
                            <strong>{{ $message->role === 'user' ? __('Question') : __('Answer') }}</strong>
                            @if($message->acting_user_id)
                                <span class="badge bg-secondary">{{ __('asked while impersonating') }}</span>
                            @endif
                            <small class="text-muted float-end">{{ optional($message->created_at)->format('M j, g:i A') }}</small>
                            <p class="mb-0 mt-1" style="white-space:pre-wrap;">{{ $message->content }}</p>
                        </div>
                    @endif
                @endforeach

                @if($messages->isEmpty())
                    <p class="text-muted text-center py-4">{{ __('This conversation has no messages.') }}</p>
                @endif
            </div>

            <div class="card-footer">
                <a href="{{ route($route.'.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('btn_back') }}</a>
            </div>
        </div>
    </div>
</div>

@endsection
