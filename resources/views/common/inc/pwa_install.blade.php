{{--
    "Put this portal on your phone".

    One implementation, two placements. $variant = 'inline' sits in the page
    (the login column); 'floating' hovers over the portal once the student is
    signed in. It used to exist only on the login page, so a student who
    stayed signed in — which is most of them — was never offered it again.

    What it can honestly do depends on the browser:

      - Chrome and the Chromium family fire beforeinstallprompt, so there is a
        real button that opens the real installer.
      - iPhone and iPad expose no install API whatsoever. The only honest
        thing is to show where Apple's own button is.
      - Firefox and Safari on the desktop offer neither, so nothing is shown
        rather than instructions that lead nowhere.

    The event can fire before this script runs, so the head captures it early
    and leaves it on window.__paxInstallPrompt; this picks up whatever is
    already waiting as well as anything that arrives later.
--}}
@php
    $pwaVariant = $variant ?? 'floating';
@endphp

@if(config('pwa.enabled'))
<div id="pwa-install" class="pwa-install pwa-install--{{ $pwaVariant }}" hidden>
    <div class="pwa-install-head">
        <i class="fas fa-mobile-alt" aria-hidden="true"></i>
        <strong>{{ __('Put this portal on your phone') }}</strong>
        <button type="button" id="pwa-dismiss" aria-label="{{ __('Close') }}">&times;</button>
    </div>

    <p class="pwa-install-text" id="pwa-text">
        {{ __('Add it to your home screen and it opens like an app, without the browser.') }}
    </p>

    <button type="button" id="pwa-install-button" class="pwa-install-button" hidden>
        <i class="fas fa-download" aria-hidden="true"></i> {{ __('Install') }}
    </button>

    <ol class="pwa-steps" id="pwa-steps" hidden>
        <li>{{ __('Tap the Share button at the bottom of Safari') }} <i class="fas fa-share-square" aria-hidden="true"></i></li>
        <li>{{ __('Scroll down and choose "Add to Home Screen"') }}</li>
        <li>{{ __('Tap Add. The portal will appear with your other apps.') }}</li>
    </ol>
</div>

<style>
    .pwa-install {
        padding: 14px 16px;
        border: 1px solid #e3e8ef;
        border-radius: 12px;
        background: #fff;
        text-align: left;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    .pwa-install[hidden] { display: none; }

    /* In the login column, as it has always been. */
    .pwa-install--inline { margin: 18px 0 4px; background: #f8fafc; }

    /* Over the portal. Bottom LEFT on purpose: the assistant's launcher is
       fixed bottom right, and two floating cards in one corner cover each
       other. */
    .pwa-install--floating {
        position: fixed;
        left: max(16px, env(safe-area-inset-left, 0px));
        bottom: max(16px, env(safe-area-inset-bottom, 0px));
        width: 330px;
        max-width: calc(100vw - 32px);
        z-index: 10400;
        box-shadow: 0 16px 40px rgba(16, 32, 44, .22);
    }

    .pwa-install-head {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .95rem;
        margin-bottom: 6px;
        color: #1f2a37;
    }
    .pwa-install-head button {
        margin-left: auto;
        background: none;
        border: 0;
        font-size: 1.3rem;
        line-height: 1;
        color: #90a0b7;
        cursor: pointer;
        width: 32px;
        height: 32px;
    }
    .pwa-install-text { font-size: .87rem; color: #6b7a8d; margin: 0 0 10px; }
    .pwa-install-button {
        width: 100%;
        padding: 11px;
        font-size: .95rem;
        font-weight: 600;
        color: #fff;
        background: {{ config('pwa.theme_color') }};
        border: 0;
        border-radius: 9px;
        cursor: pointer;
    }
    .pwa-steps { margin: 0; padding-left: 20px; font-size: .86rem; color: #6b7a8d; }
    .pwa-steps li { margin-bottom: 4px; }

    /* On a phone the assistant's launcher sits in the bottom-right corner,
       so the card is lifted clear of it rather than laid over it. */
    @media (max-width: 560px) {
        .pwa-install--floating {
            left: max(12px, env(safe-area-inset-left, 0px));
            right: max(12px, env(safe-area-inset-right, 0px));
            width: auto;
            bottom: calc(84px + env(safe-area-inset-bottom, 0px));
        }
    }
</style>

<script>
(function () {
    var card = document.getElementById('pwa-install');
    if (!card) { return; }

    var button = document.getElementById('pwa-install-button');
    var steps = document.getElementById('pwa-steps');
    var text = document.getElementById('pwa-text');
    var dismiss = document.getElementById('pwa-dismiss');

    var KEY = 'pax-install-dismissed';
    // Dismissal used to be permanent, so one stray tap meant the offer was
    // never made again on that device. It now sleeps for a month.
    var SNOOZE_DAYS = 30;

    // Already installed: it opens in its own window, so there is nothing to
    // offer. Checked both ways because iOS uses its own flag.
    var standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    if (standalone) { return; }

    try {
        var until = parseInt(localStorage.getItem(KEY) || '0', 10);
        if (until && Date.now() < until) { return; }
    } catch (e) {
        // Private browsing: show it rather than fail.
    }

    function snooze() {
        card.hidden = true;
        try {
            localStorage.setItem(KEY, String(Date.now() + SNOOZE_DAYS * 86400000));
        } catch (e) {}
    }

    function show() { card.hidden = false; }

    dismiss.addEventListener('click', snooze);

    // --- Chrome and the Chromium family --------------------------------
    var deferred = window.__paxInstallPrompt || null;

    function offer(event) {
        deferred = event;
        button.hidden = false;
        steps.hidden = true;
        show();
    }

    if (deferred) { offer(deferred); }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        offer(event);
    });

    button.addEventListener('click', function () {
        if (!deferred) { return; }

        deferred.prompt();
        deferred.userChoice.then(function () {
            deferred = null;
            window.__paxInstallPrompt = null;
            snooze();
        });
    });

    // Installed from the browser's own menu rather than this button: the
    // offer has been taken up, so stop making it.
    window.addEventListener('appinstalled', function () {
        window.__paxInstallPrompt = null;
        snooze();
    });

    // --- iPhone and iPad -----------------------------------------------
    // Apple provides no API, so the most that is possible is showing where
    // its own button is.
    var ua = window.navigator.userAgent;
    var isIos = /iPad|iPhone|iPod/.test(ua)
        || (/Macintosh/.test(ua) && 'ontouchend' in document);

    if (isIos) {
        text.textContent = {!! json_encode(__('Add it to your home screen in three taps and it opens like an app.')) !!};
        steps.hidden = false;
        button.hidden = true;
        show();
    }
})();
</script>
@endif
