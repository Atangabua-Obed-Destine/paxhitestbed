/**
 * Context-aware chat assistant — widget behaviour.
 *
 * Dependency-free on purpose: it is injected into four portals with different
 * stacks and different jQuery versions, so it uses only the DOM API.
 *
 * Two rules hold throughout:
 *
 *  - Model output is never assigned to innerHTML. It is parsed into a small,
 *    fixed set of elements (paragraph, bold, italic, code, list) and every
 *    piece of text is set with textContent, so a reply containing markup is
 *    shown as the characters the model wrote and can never execute.
 *  - Identity is never sent. The server resolves who is asking from the auth
 *    guards; the only thing this file reports is which surface it is embedded
 *    in, and the server re-checks even that against the actor.
 */
(function () {
    'use strict';

    var root = document.getElementById('pax-chat');
    if (!root) { return; }

    var panel = document.getElementById('pax-chat-panel');
    var toggle = document.getElementById('pax-chat-toggle');
    var closeBtn = document.getElementById('pax-chat-close');
    var resetBtn = document.getElementById('pax-chat-reset');
    var form = document.getElementById('pax-chat-form');
    var input = document.getElementById('pax-chat-input');
    var sendBtn = document.getElementById('pax-chat-send');
    var log = document.getElementById('pax-chat-log');
    var count = document.getElementById('pax-chat-count');

    var csrf = root.dataset.csrf;
    var surface = root.dataset.surface;
    var maxLen = parseInt(input.getAttribute('maxlength'), 10) || 1000;

    var starters = [];
    try {
        starters = JSON.parse(root.dataset.starters || '[]');
    } catch (e) {
        starters = [];
    }

    var loaded = false;
    var busy = false;

    /* --------------------------------------------------------- host theme
       The widget floats on somebody else's page, so it takes its light or
       dark scheme from that page rather than from the operating system. A
       visitor whose OS is dark, reading the light student portal, was being
       shown a dark panel on a light page.

       The host's effective background is found by walking up from the widget
       until an element actually paints one; a transparent body would
       otherwise read as black. */

    function effectiveBackground() {
        var el = document.body;

        while (el && el !== document.documentElement.parentNode) {
            var bg = getComputedStyle(el).backgroundColor;

            if (bg && bg !== 'transparent' && !/rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\s*\)/.test(bg)) {
                return bg;
            }

            el = el.parentElement;
        }

        return 'rgb(255, 255, 255)';
    }

    function applyHostScheme() {
        var match = effectiveBackground().match(/\d+(\.\d+)?/g);

        if (!match || match.length < 3) { return; }

        var r = +match[0], g = +match[1], b = +match[2];
        // Rec. 601 luma is plenty for a light/dark decision.
        var luma = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

        root.setAttribute('data-scheme', luma < 0.5 ? 'dark' : 'light');
    }

    applyHostScheme();

    // A portal with its own theme toggle changes the page under us.
    if (typeof MutationObserver !== 'undefined') {
        var watcher = new MutationObserver(applyHostScheme);
        watcher.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
        watcher.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme'] });
    }

    /* ---------------------------------------------------------------- utils */

    function t(key, fallback) {
        var dict = {};
        try { dict = JSON.parse(root.dataset.i18n || '{}'); } catch (e) { dict = {}; }
        return dict[key] || fallback;
    }

    function clockNow() {
        var d = new Date();
        return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    }

    function scrollToEnd() { log.scrollTop = log.scrollHeight; }

    /* ------------------------------------------------------------- markdown
       A deliberately tiny subset, matching exactly what the system prompt
       permits: paragraphs, "- " and "1." lists, **bold**, *italic*, `code`.
       Anything else stays literal. Nothing here touches innerHTML with model
       text; inline spans are built element by element. */

    function appendInline(parent, text) {
        // Split on the inline markers, keeping the delimiters.
        var pattern = /(\*\*[^*]+\*\*|\*[^*\n]+\*|`[^`\n]+`)/g;
        var pieces = String(text).split(pattern);

        pieces.forEach(function (piece) {
            if (!piece) { return; }

            var el = null;
            var inner = piece;

            if (piece.length > 4 && piece.slice(0, 2) === '**' && piece.slice(-2) === '**') {
                el = document.createElement('strong');
                inner = piece.slice(2, -2);
            } else if (piece.length > 2 && piece[0] === '*' && piece.slice(-1) === '*') {
                el = document.createElement('em');
                inner = piece.slice(1, -1);
            } else if (piece.length > 2 && piece[0] === '`' && piece.slice(-1) === '`') {
                el = document.createElement('code');
                inner = piece.slice(1, -1);
            }

            if (el) {
                el.textContent = inner;
                parent.appendChild(el);
            } else {
                parent.appendChild(document.createTextNode(piece));
            }
        });
    }

    function renderRich(container, text) {
        var lines = String(text).replace(/\r/g, '').split('\n');
        var list = null;
        var para = null;

        function closeList() { list = null; }
        function closePara() { para = null; }

        lines.forEach(function (raw) {
            var line = raw.replace(/\s+$/, '');
            var bullet = line.match(/^\s*[-*•]\s+(.*)$/);
            var numbered = line.match(/^\s*(\d+)[.)]\s+(.*)$/);

            if (line.trim() === '') {
                closeList();
                closePara();
                return;
            }

            if (bullet || numbered) {
                closePara();
                var wanted = bullet ? 'UL' : 'OL';

                if (!list || list.tagName !== wanted) {
                    list = document.createElement(bullet ? 'ul' : 'ol');
                    container.appendChild(list);
                }

                var li = document.createElement('li');
                appendInline(li, bullet ? bullet[1] : numbered[2]);
                list.appendChild(li);
                return;
            }

            closeList();

            if (!para) {
                para = document.createElement('p');
                container.appendChild(para);
            } else {
                para.appendChild(document.createElement('br'));
            }

            appendInline(para, line);
        });

        // A reply that produced nothing (all blank) still needs to show.
        if (!container.childNodes.length) {
            var p = document.createElement('p');
            p.textContent = String(text);
            container.appendChild(p);
        }
    }

    /* -------------------------------------------------------------- messages */

    function addMessage(role, text, options) {
        options = options || {};

        var el = document.createElement('div');
        el.className = 'pax-msg pax-msg-' + role;

        if (role === 'assistant') {
            renderRich(el, text);
        } else {
            // The person's own words are shown exactly as typed.
            var p = document.createElement('p');
            p.textContent = text;
            el.appendChild(p);
        }

        if (options.degraded) { el.classList.add('pax-msg-degraded'); }
        if (options.error) { el.classList.add('pax-msg-error'); }

        if (options.link && options.link.url) {
            var a = document.createElement('a');
            a.className = 'pax-msg-link';
            a.href = options.link.url;
            a.rel = 'noopener';
            a.textContent = options.link.label || t('open', 'Open');
            el.appendChild(a);
        }

        if (options.time !== false) {
            var time = document.createElement('span');
            time.className = 'pax-msg-time';
            time.textContent = options.time || clockNow();
            el.appendChild(time);
        }

        log.appendChild(el);
        scrollToEnd();
        return el;
    }

    function addStarters() {
        if (!starters.length) { return; }

        var wrap = document.createElement('div');
        wrap.className = 'pax-starters';

        starters.forEach(function (question) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pax-starter';
            b.textContent = question;
            b.addEventListener('click', function () { send(question); });
            wrap.appendChild(b);
        });

        log.appendChild(wrap);
        scrollToEnd();
    }

    /**
     * The suggestions are an opening offer, not a permanent menu. They used
     * to stay put once the person typed their own question, leaving four
     * buttons stranded above a live conversation.
     */
    function clearStarters() {
        var wrap = log.querySelector('.pax-starters');

        if (wrap) {
            wrap.remove();
        }
    }

    /**
     * The waiting state.
     *
     * Dots alone were not enough: an answer here takes fifteen seconds or
     * more, and three small circles in a bubble look like an empty bubble —
     * especially once a host page's styles have had a go at them. So it says
     * what it is doing in words, and changes those words as the wait grows,
     * because silence that long reads as a hang rather than as thinking.
     */
    function addTyping() {
        var el = document.createElement('div');
        el.className = 'pax-msg pax-msg-assistant';

        var wrap = document.createElement('span');
        wrap.className = 'pax-thinking';

        var dots = document.createElement('span');
        dots.className = 'pax-typing';
        dots.setAttribute('aria-hidden', 'true');
        for (var i = 0; i < 3; i++) { dots.appendChild(document.createElement('span')); }

        var label = document.createElement('span');
        label.className = 'pax-thinking-label';
        label.textContent = t('thinking', 'Thinking…');

        wrap.appendChild(dots);
        wrap.appendChild(label);
        el.appendChild(wrap);

        // Announced once, not on every change, so a screen reader is told the
        // assistant is working without the message being repeated.
        el.setAttribute('role', 'status');

        var stages = [
            [6000, t('looking', 'Looking that up…')],
            [14000, t('still', 'Still working — this one is taking a moment…')]
        ];

        var timers = stages.map(function (stage) {
            return setTimeout(function () { label.textContent = stage[1]; }, stage[0]);
        });

        el.remove = (function (original) {
            return function () {
                timers.forEach(clearTimeout);
                original.call(el);
            };
        })(el.remove);

        log.appendChild(el);
        scrollToEnd();
        return el;
    }

    /* ------------------------------------------------------------- transport */

    function post(url, body) {
        // A hung request must not leave the dots spinning forever. The server
        // allows 45s per upstream call and may retry, so the ceiling here is
        // generous but finite.
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, 90000) : null;

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            body: JSON.stringify(Object.assign({ surface: surface }, body || {}))
        }).then(function (r) {
            if (timer) { clearTimeout(timer); }
            return r.json().catch(function () { return {}; });
        }, function (err) {
            if (timer) { clearTimeout(timer); }
            throw err;
        });
    }

    function loadHistory() {
        if (loaded) { return; }
        loaded = true;

        fetch(root.dataset.historyUrl + '?surface=' + encodeURIComponent(surface), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.success) { return; }

            if (data.messages && data.messages.length) {
                data.messages.forEach(function (m) {
                    addMessage(m.role, m.content, { time: false });
                });
            } else {
                if (data.greeting) { addMessage('assistant', data.greeting, { time: false }); }
                addStarters();
            }
        }).catch(function () {
            loaded = false;   // allow a retry on the next open
            addMessage('assistant', t('offline', 'The assistant could not be reached. Please try again.'),
                { error: true, time: false });
        });
    }

    /* ---------------------------------------------------------------- panel */

    function openPanel() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.classList.remove('has-unread');
        toggle.style.display = 'none';
        loadHistory();
        input.focus();
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.style.display = '';
        // Focus must come back to something, or a keyboard user is stranded
        // at the top of the document.
        toggle.focus();
    }

    toggle.addEventListener('click', openPanel);
    closeBtn.addEventListener('click', closePanel);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { closePanel(); }
    });

    // Keep tab focus inside the dialog while it is open.
    panel.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') { return; }

        var focusable = panel.querySelectorAll('button, textarea, a[href], input');
        if (!focusable.length) { return; }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    resetBtn.addEventListener('click', function () {
        if (busy) { return; }

        post(root.dataset.resetUrl).then(function (data) {
            if (data && data.success === false) {
                addMessage('assistant', data.message || t('offline', 'That did not work. Please try again.'),
                    { error: true, time: false });
                return;
            }

            log.innerHTML = '';     // our own markup only; no model text here
            loaded = false;
            loadHistory();
        }).catch(function () {
            addMessage('assistant', t('offline', 'The assistant could not be reached. Please try again.'),
                { error: true, time: false });
        });
    });

    /* -------------------------------------------------------------- sending */

    function setBusy(state) {
        busy = state;
        sendBtn.disabled = state;
        input.disabled = state;
    }

    function send(text) {
        if (!text || busy) { return; }

        setBusy(true);
        clearStarters();
        addMessage('user', text);

        var typing = addTyping();

        post(root.dataset.messageUrl, { message: text }).then(function (data) {
            typing.remove();

            if (data && data.success) {
                // degraded means the model could not be reached and this is a
                // fallback line, not an answer. It used to be returned by the
                // API and ignored here, so the two looked identical.
                addMessage('assistant', data.reply, {
                    link: data.link,
                    degraded: !!data.degraded
                });
            } else {
                addMessage('assistant', (data && data.message) || t('failed', 'Something went wrong. Please try again.'),
                    { error: true });
            }
        }).catch(function (err) {
            typing.remove();
            var msg = (err && err.name === 'AbortError')
                ? t('timeout', 'That took too long. Please try asking again.')
                : t('offline', 'The assistant could not be reached. Please try again.');
            addMessage('assistant', msg, { error: true });
        }).finally(function () {
            setBusy(false);
            if (panel.hidden) { toggle.classList.add('has-unread'); }
            input.focus();
            autoGrow();
            updateCount();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = (input.value || '').trim();
        input.value = '';
        autoGrow();
        updateCount();
        send(text);
    });

    /* -------------------------------------------------------------- composer */

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }

    function updateCount() {
        if (!count) { return; }
        var used = (input.value || '').length;
        count.textContent = used + ' / ' + maxLen;
        count.classList.toggle('is-near', used > maxLen * 0.9);
    }

    input.addEventListener('input', function () { autoGrow(); updateCount(); });

    // Enter sends, Shift+Enter makes a new line — what people expect of a
    // chat box, and the reason this is a textarea rather than an input.
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    updateCount();
})();
