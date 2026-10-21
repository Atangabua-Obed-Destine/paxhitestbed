{{--
    Context-aware chat assistant — widget.

    Included immediately before </body> on every surface. It renders nothing
    unless an administrator has enabled the assistant for that surface, so
    switching it on or off is purely a settings change.

    The surface is reported to the server, but the server re-checks the claim
    against the actor (ChatContext::resolveSurface) — a student cannot ask for
    the staff knowledge base by posting surface=admin.
--}}
@php
    $chatSettings = \App\Models\ChatSetting::current();
    $chatSurface = \App\Services\Chat\ChatContext::detectSurface(request());

    // Starter questions, per surface: a first-time user opening a blank box
    // has no idea what the assistant can do, and the first question they
    // invent is often one it cannot answer. These are all answerable by the
    // tools that surface actually has.
    $chatStarters = [
        'web' => [
            __('What programmes do you offer?'),
            __('Can I still apply?'),
            __('How do I apply?'),
        ],
        'application' => [
            __('What is the status of my application?'),
            __('What documents do I still need?'),
            __('What happens next?'),
        ],
        'student' => [
            __('What do I still owe?'),
            __('What are my results?'),
            __('What classes do I have today?'),
            __('What is my attendance?'),
        ],
        'admin' => [
            __('How many applications this session?'),
            __('Find a student by name'),
        ],
    ][$chatSurface] ?? [];

    // Short strings the script needs; kept here so they go through the
    // translator rather than being hardcoded in English in the JS.
    $chatStrings = [
        'open' => __('Open'),
        'thinking' => __('Thinking…'),
        'looking' => __('Looking that up…'),
        'still' => __('Still working — this one is taking a moment…'),
        'offline' => __('The assistant could not be reached. Please try again.'),
        'failed' => __('Something went wrong. Please try again.'),
        'timeout' => __('That took too long. Please try asking again.'),
    ];
@endphp

@if($chatSettings->enabledFor($chatSurface))
<div id="pax-chat" data-surface="{{ $chatSurface }}"
     data-message-url="{{ route('chat.message') }}"
     data-history-url="{{ route('chat.history') }}"
     data-reset-url="{{ route('chat.reset') }}"
     data-csrf="{{ csrf_token() }}"
     data-starters="{{ json_encode(array_values($chatStarters), JSON_UNESCAPED_UNICODE) }}"
     data-i18n="{{ json_encode($chatStrings, JSON_UNESCAPED_UNICODE) }}">

    <button type="button" id="pax-chat-toggle"
            aria-expanded="false" aria-controls="pax-chat-panel"
            aria-label="{{ __('Open the assistant') }}">
        <span class="pax-chat-icon" aria-hidden="true">&#128172;</span>
        <span class="pax-chat-toggle-label">{{ $chatSettings->title }}</span>
        <span class="pax-chat-dot" aria-hidden="true"></span>
    </button>

    <section id="pax-chat-panel" role="dialog" aria-modal="false"
             aria-label="{{ $chatSettings->title }}" hidden>
        <header>
            <div>
                <strong>{{ $chatSettings->title }}</strong>
                <small>{{ __('Answers based on your own records') }}</small>
            </div>
            <div class="pax-chat-actions">
                <button type="button" id="pax-chat-reset"
                        title="{{ __('Start a new conversation') }}"
                        aria-label="{{ __('Start a new conversation') }}">&#8635;</button>
                <button type="button" id="pax-chat-close"
                        title="{{ __('Close') }}"
                        aria-label="{{ __('Close') }}">&times;</button>
            </div>
        </header>

        <div id="pax-chat-log" role="log" aria-live="polite" aria-relevant="additions"></div>

        <form id="pax-chat-form" autocomplete="off">
            {{-- A textarea, not an input: questions run to more than one line,
                 and Enter-to-send with Shift+Enter for a new line is what
                 people expect of a chat box. --}}
            <textarea id="pax-chat-input" rows="1" maxlength="1000"
                      placeholder="{{ __('Ask a question…') }}"
                      aria-label="{{ __('Your question') }}"></textarea>
            <button type="submit" id="pax-chat-send"
                    title="{{ __('Send') }}" aria-label="{{ __('Send') }}">&#10148;</button>
        </form>

        <div id="pax-chat-foot">
            <span>{{ __('Shift + Enter for a new line') }}</span>
            <span id="pax-chat-count"></span>
        </div>
    </section>
</div>

<link rel="stylesheet" href="{{ asset('dashboard/css/chat-widget.css') }}?v={{ @filemtime(public_path('dashboard/css/chat-widget.css')) ?: 1 }}">
<script src="{{ asset('dashboard/js/chat-widget.js') }}?v={{ @filemtime(public_path('dashboard/js/chat-widget.js')) ?: 1 }}" defer></script>
@endif
