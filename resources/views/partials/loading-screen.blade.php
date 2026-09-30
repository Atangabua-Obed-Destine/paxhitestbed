{{--
    The loading screen, shown when a click really takes you somewhere.

    People double-click a slow page because nothing says the first click landed,
    and a double-clicked "Received" records a payment twice. So this does two
    jobs: it says the click landed, and it stops the same form being sent again
    while it is on its way.

    The hard part is knowing when NOT to show it. A download, a new tab or a
    print popup leaves the current page exactly where it is, so a screen shown on
    those clicks would stay up for ever — worse than showing nothing. Everything
    of that kind is listed below, and three safety nets catch whatever is not:
    Esc, the back button, and a timeout.

    Included once by the admin and student layouts. Remove the include and the
    app behaves exactly as it did before.
--}}

@php
    // The same logo, found the same way, as the header above it
    // (admin/layouts/master.blade.php:130).
    $loadingLogo = optional(\App\Models\Setting::first())->logo_path;
@endphp

<div id="app-loading" class="app-loading" aria-hidden="true" role="status" aria-live="polite">
    <div class="app-loading__panel">
        @if ($loadingLogo && upload_exists('setting/' . $loadingLogo))
            <img class="app-loading__logo" src="{{ upload_asset('setting/' . $loadingLogo) }}" alt="">
        @endif

        <div class="app-loading__brand">EduTrust</div>
        <div class="app-loading__school">{{ institution_name() }}</div>

        <div class="app-loading__track"><span class="app-loading__fill"></span></div>

        <div class="app-loading__word">{{ __('Loading') }}<span class="app-loading__dots"><i>.</i><i>.</i><i>.</i></span></div>
    </div>
</div>

<style>
    .app-loading {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(2px);
    }
    .app-loading.is-visible { display: flex; }

    .app-loading__panel {
        text-align: center;
        padding: 28px 34px;
        /* Held back a moment: a page that answers quickly should never flash
           this up, which reads as a glitch rather than as progress. */
        opacity: 0;
        transform: translateY(6px);
        animation: app-loading-in .35s ease-out .25s forwards;
    }
    @keyframes app-loading-in { to { opacity: 1; transform: none; } }

    .app-loading__logo { max-height: 62px; max-width: 200px; margin-bottom: 14px; }

    .app-loading__brand {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 3px;
        text-transform: uppercase;
        /* Solid first: where gradient text is not honoured, the name must still
           be readable rather than transparent. The theme's own gradient
           (public/dashboard/css/style.css) is applied only where it works. */
        color: #16b1d9;
    }
    @supports ((-webkit-background-clip: text) or (background-clip: text)) {
        .app-loading__brand {
            background: linear-gradient(-135deg, #1de9b6 0%, #1dc4e9 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
    }
    .app-loading__school {
        margin-top: 2px;
        font-size: 11px;
        letter-spacing: 1.4px;
        text-transform: uppercase;
        color: #6c757d;
    }

    .app-loading__track {
        position: relative;
        width: 210px;
        height: 3px;
        margin: 18px auto 10px;
        overflow: hidden;
        border-radius: 3px;
        background: rgba(29, 196, 233, 0.16);
    }
    .app-loading__fill {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        width: 40%;
        border-radius: 3px;
        background: linear-gradient(-135deg, #1de9b6 0%, #1dc4e9 100%);
        animation: app-loading-slide 1.1s ease-in-out infinite;
    }
    @keyframes app-loading-slide {
        0% { left: -40%; width: 40%; }
        50% { width: 55%; }
        100% { left: 100%; width: 40%; }
    }

    .app-loading__word { font-size: 12px; color: #6c757d; letter-spacing: .6px; }
    .app-loading__dots i { font-style: normal; animation: app-loading-dot 1.2s infinite; }
    .app-loading__dots i:nth-child(2) { animation-delay: .2s; }
    .app-loading__dots i:nth-child(3) { animation-delay: .4s; }
    @keyframes app-loading-dot { 0%, 60%, 100% { opacity: .25; } 30% { opacity: 1; } }

    /* Nothing moves for anyone who asked for that; the screen still appears. */
    @media (prefers-reduced-motion: reduce) {
        .app-loading__panel { animation: none; opacity: 1; transform: none; }
        .app-loading__fill { animation: none; width: 100%; left: 0; }
        .app-loading__dots i { animation: none; opacity: 1; }
    }

    @media print { .app-loading { display: none !important; } }
</style>

<script>
(function () {
    'use strict';

    var screenEl = document.getElementById('app-loading');

    if (!screenEl) {
        return;
    }

    var SAFETY_MS = 15000;      // never leave anyone staring at a frozen screen
    var safetyTimer = null;

    function show() {
        if (screenEl.classList.contains('is-visible')) {
            return;
        }

        screenEl.classList.add('is-visible');
        screenEl.setAttribute('aria-hidden', 'false');
        clearTimeout(safetyTimer);
        safetyTimer = setTimeout(hide, SAFETY_MS);
    }

    function hide() {
        screenEl.classList.remove('is-visible');
        screenEl.setAttribute('aria-hidden', 'true');
        clearTimeout(safetyTimer);

        // Whatever was sent never arrived, so let the page be used again.
        Array.prototype.forEach.call(document.querySelectorAll('form[data-sending]'), function (form) {
            form.removeAttribute('data-sending');
            releaseButtons(form);
        });
    }

    function releaseButtons(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-loading-locked]'), function (button) {
            button.disabled = false;
            button.removeAttribute('data-loading-locked');
        });
    }

    /** A click that opens something elsewhere leaves this page where it is. */
    function opensElsewhere(event, link) {
        return event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || event.button === 1
            || (link.target && link.target !== '' && link.target !== '_self');
    }

    /** A download streams a file; the page never navigates, so nothing to show. */
    function isDownload(link) {
        if (link.hasAttribute('download')) {
            return true;
        }

        var href = (link.getAttribute('href') || '').toLowerCase();

        return /\/[a-z0-9_-]*(download|export|print|invoice|receipt)[a-z0-9_-]*(\/|\?|$)/.test(href)
            || /\.(pdf|xlsx?|csv|docx?|zip|png|jpe?g)(\?|$)/.test(href);
    }

    /** A link that does something on this page rather than going anywhere. */
    function staysHere(link) {
        var href = link.getAttribute('href');

        if (!href || href === '#' || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) {
            return true;
        }

        return link.hasAttribute('data-bs-toggle')      // modal, dropdown, tab, collapse
            || link.hasAttribute('data-toggle')
            || link.getAttribute('role') === 'button'
            || link.hasAttribute('data-dismiss')
            || link.hasAttribute('data-bs-dismiss');
    }

    /** Anything can opt out, itself or through an ancestor. */
    function optedOut(el) {
        return !!el.closest('.js-no-loading, [data-no-loading]');
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a') : null;

        if (!link || event.defaultPrevented) {
            return;
        }

        if (staysHere(link) || isDownload(link) || opensElsewhere(event, link) || optedOut(link)) {
            return;
        }

        show();
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;

        // Blocked by the page's own validation, or by anything else: the page is
        // staying, so no screen and nothing locked.
        if (event.defaultPrevented) {
            return;
        }

        if (optedOut(form) || form.getAttribute('target') === '_blank') {
            return;
        }

        // The browser's own validation has already run by this point; if it
        // failed, submit never fires. This covers a form asked to skip it.
        if (form.noValidate === false && typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        if (form.hasAttribute('data-sending')) {
            event.preventDefault();     // the second click of a double-click
            return;
        }

        form.setAttribute('data-sending', '1');

        Array.prototype.forEach.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'), function (button) {
            if (button.disabled) {
                return;
            }

            // Fixed width first, so a disabled button does not resize and shift
            // the buttons beside it.
            if (button.offsetWidth) {
                button.style.minWidth = button.offsetWidth + 'px';
            }

            button.disabled = true;
            button.setAttribute('data-loading-locked', '1');
        });

        show();
    });

    // A form submitted to a download still leaves the page in place.
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (/\/(download|export|print)(\/|\?|$)/i.test(form.getAttribute('action') || '')) {
            setTimeout(hide, 1200);
        }
    });

    // Coming back with the Back button restores the page as it was — including
    // a screen that was up when it left.
    window.addEventListener('pageshow', hide);
    window.addEventListener('pagehide', hide);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hide();
        }
    });

    // Anything on the page can ask for it: window.appLoading.show() / .hide().
    window.appLoading = { show: show, hide: hide };
})();
</script>
