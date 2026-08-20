{{--
    Context-aware chat widget.

    Included immediately before </body> on every surface. It renders nothing
    unless an administrator has enabled the assistant for that surface, so
    switching it on or off is purely a settings change.
--}}
@php
    $chatSettings = \App\Models\ChatSetting::current();
    $chatSurface = \App\Services\Chat\ChatContext::detectSurface(request());
@endphp

@if($chatSettings->enabledFor($chatSurface))
<div id="pax-chat" data-surface="{{ $chatSurface }}"
     data-message-url="{{ route('chat.message') }}"
     data-history-url="{{ route('chat.history') }}"
     data-reset-url="{{ route('chat.reset') }}"
     data-csrf="{{ csrf_token() }}">

    <button type="button" id="pax-chat-toggle" aria-label="{{ __('Open the assistant') }}">
        <span class="pax-chat-icon">&#128172;</span>
        <span class="pax-chat-toggle-label">{{ $chatSettings->title }}</span>
    </button>

    <section id="pax-chat-panel" role="dialog" aria-label="{{ $chatSettings->title }}" hidden>
        <header>
            <div>
                <strong>{{ $chatSettings->title }}</strong>
                <small>{{ __('Answers based on your own records') }}</small>
            </div>
            <div class="pax-chat-actions">
                <button type="button" id="pax-chat-reset" title="{{ __('Start a new conversation') }}">&#8635;</button>
                <button type="button" id="pax-chat-close" aria-label="{{ __('Close') }}">&times;</button>
            </div>
        </header>

        <div id="pax-chat-log" aria-live="polite"></div>

        <form id="pax-chat-form" autocomplete="off">
            <input type="text" id="pax-chat-input" maxlength="1000"
                   placeholder="{{ __('Ask a question…') }}" aria-label="{{ __('Your question') }}">
            <button type="submit" id="pax-chat-send">{{ __('Send') }}</button>
        </form>
    </section>
</div>

<link rel="stylesheet" href="{{ asset('dashboard/css/chat-widget.css') }}?v={{ @filemtime(public_path('dashboard/css/chat-widget.css')) ?: 1 }}">
<script src="{{ asset('dashboard/js/chat-widget.js') }}?v={{ @filemtime(public_path('dashboard/js/chat-widget.js')) ?: 1 }}" defer></script>
@endif
