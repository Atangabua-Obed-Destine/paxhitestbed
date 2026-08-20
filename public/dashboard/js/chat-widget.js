/**
 * Context-aware chat widget.
 *
 * Deliberately dependency-free: it is injected into four portals with different
 * stacks (and different jQuery versions), so it uses only the DOM API.
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

    var csrf = root.dataset.csrf;
    var surface = root.dataset.surface;   // server-rendered; re-validated server-side
    var loaded = false;
    var busy = false;

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ surface: surface }, body || {}))
        }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    function scrollToEnd() { log.scrollTop = log.scrollHeight; }

    function addMessage(role, text, link) {
        var el = document.createElement('div');
        el.className = 'pax-msg pax-msg-' + role;
        el.textContent = text;

        if (link && link.url) {
            var a = document.createElement('a');
            a.className = 'pax-msg-link';
            a.href = link.url;
            a.textContent = link.label || 'Open';
            el.appendChild(document.createElement('br'));
            el.appendChild(a);
        }

        log.appendChild(el);
        scrollToEnd();
        return el;
    }

    function addTyping() {
        var el = document.createElement('div');
        el.className = 'pax-msg pax-msg-assistant';
        el.innerHTML = '<span class="pax-typing"><span></span><span></span><span></span></span>';
        log.appendChild(el);
        scrollToEnd();
        return el;
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
                data.messages.forEach(function (m) { addMessage(m.role, m.content); });
            } else if (data.greeting) {
                addMessage('assistant', data.greeting);
            }
        }).catch(function () {
            loaded = false;   // allow a retry on next open
        });
    }

    function openPanel() {
        panel.hidden = false;
        toggle.style.display = 'none';
        loadHistory();
        input.focus();
    }

    function closePanel() {
        panel.hidden = true;
        toggle.style.display = '';
    }

    toggle.addEventListener('click', openPanel);
    closeBtn.addEventListener('click', closePanel);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { closePanel(); }
    });

    resetBtn.addEventListener('click', function () {
        post(root.dataset.resetUrl).then(function () {
            log.innerHTML = '';
            loaded = false;
            loadHistory();
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var text = (input.value || '').trim();
        if (!text || busy) { return; }

        busy = true;
        sendBtn.disabled = true;
        input.value = '';
        addMessage('user', text);

        var typing = addTyping();

        post(root.dataset.messageUrl, { message: text }).then(function (data) {
            typing.remove();

            if (data && data.success) {
                addMessage('assistant', data.reply, data.link);
            } else {
                var el = addMessage('assistant', (data && data.message) || 'Something went wrong. Please try again.');
                el.classList.add('pax-msg-error');
            }
        }).catch(function () {
            typing.remove();
            addMessage('assistant', 'The assistant could not be reached. Please try again.')
                .classList.add('pax-msg-error');
        }).finally(function () {
            busy = false;
            sendBtn.disabled = false;
            input.focus();
        });
    });
})();
