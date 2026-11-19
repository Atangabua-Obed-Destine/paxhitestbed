{{--
    The notice bar of the admin and student portals, drawn like the website's
    (web2/partials/announcement-ticker): a gold bar, the navy "Notices" label,
    white one-line notices separated by dots, scrolling in a seamless loop, and
    a button to close it for this page.

    The portals have no Tailwind, so the look is restated here in plain CSS with
    the website's values. The bar is pinned to the top of the window and its
    height is published as --notice-h, which moves the portal's header, sidebar
    and content down under it; closing the bar sets it to 0. The desktop
    workspace measures the bar itself (workspace.js), so closing also fires a
    resize for it to measure again.

    Expects $announcements, already filtered to the live ones.
--}}
@if(isset($announcements) && $announcements->count())
<div class="site-announcement notice-bar" id="portalNoticeBar" role="marquee" aria-label="{{ __('Announcements') }}">
    <div class="notice-bar-label">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
        </svg>
        <span class="notice-bar-word">{{ __('Notices') }}</span>
    </div>

    <div class="notice-bar-track">
        <div class="notice-bar-scroll" style="animation-duration: {{ max($announcements->count() * 12, 20) }}s;">
            @foreach($announcements as $a)
                <span class="notice-bar-item"><span class="notice-bar-dot"></span>{{ $a->ticker_text }}</span>
            @endforeach
            {{-- The same notices again, so the loop has no gap. --}}
            @foreach($announcements as $a)
                <span class="notice-bar-item" aria-hidden="true"><span class="notice-bar-dot"></span>{{ $a->ticker_text }}</span>
            @endforeach
        </div>
    </div>

    <button type="button" class="notice-bar-close" aria-label="{{ __('Dismiss announcements') }}"
            onclick="document.getElementById('portalNoticeBar').style.display = 'none';
                     document.documentElement.style.setProperty('--notice-h', '0px');
                     window.dispatchEvent(new Event('resize'));">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
<style>
    :root { --notice-h: 36px; }

    .notice-bar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 10000;
        display: flex; align-items: stretch;
        height: 36px; box-sizing: border-box; overflow: hidden;
        background: #c8952a; color: #fff;
        font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    .notice-bar-label {
        flex-shrink: 0; position: relative; z-index: 1;
        display: flex; align-items: center; gap: 8px; padding: 0 16px;
        background: #1e3a5f;
    }
    .notice-bar-word { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .notice-bar-track { flex: 1; min-width: 0; overflow: hidden; position: relative; }
    .notice-bar-scroll {
        display: flex; height: 100%; white-space: nowrap; width: max-content;
        animation: notice-bar-scroll linear infinite;
    }
    .notice-bar-scroll:hover { animation-play-state: paused; }
    .notice-bar-item {
        display: inline-flex; align-items: center; padding: 0 32px;
        font-size: 13px; font-weight: 500; line-height: 1; color: #fff;
    }
    .notice-bar-dot { flex-shrink: 0; width: 6px; height: 6px; margin-right: 12px; border-radius: 50%; background: rgba(255, 255, 255, .6); }
    .notice-bar-close {
        flex-shrink: 0; display: flex; align-items: center; padding: 0 12px;
        border: 0; background: transparent; color: #fff; cursor: pointer; transition: background-color .15s;
    }
    .notice-bar-close:hover { background: #a67b22; }
    .notice-bar-close:focus-visible { outline: 2px solid #fff; outline-offset: -4px; }
    @keyframes notice-bar-scroll {
        from { transform: translateX(0); }
        to { transform: translateX(-50%); }
    }
    @media (max-width: 639.98px) {
        .notice-bar-word { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .notice-bar-scroll { animation: none; }
    }

    /* The portal starts under the bar. */
    .pcoded-header { top: var(--notice-h) !important; }
    .pcoded-navbar { top: var(--notice-h) !important; }
    .pcoded-main-container { margin-top: calc(56px + var(--notice-h)) !important; }
</style>
@endif
