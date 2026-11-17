/*
 * Desktop workspace for the admin (styles: public/dashboard/css/workspace.css).
 *
 * On a wide screen: a title bar with menus, the header as a toolbar, the
 * screens opened as tabs, the sidebar as an icon rail, a status bar, and a
 * "Go to…" box (Ctrl+K). The menus and the box are built from the sidebar,
 * so they offer exactly what the sidebar offers, permissions included.
 * "Classic layout" in the status bar puts the template back as it was.
 */
(function () {
    'use strict';

    var T = window.WS_TEXT || {};
    var html = document.documentElement;
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } }
    };
    var wide = window.matchMedia('(min-width: 992px)');
    var isDesk = function () { return wide.matches && !html.classList.contains('ws-classic'); };
    var nav = document.querySelector('.pcoded-navbar .pcoded-inner-navbar');
    if (!nav) return;

    html.classList.add('ws');
    if (store.get('ws.layout') === 'classic') html.classList.add('ws-classic');

    var textOf = function (el) { return el ? el.textContent.replace(/\s+/g, ' ').trim() : ''; };
    // A link's name without its counter or its screen-reader explanation;
    // the counter itself comes along as a badge.
    var linkText = function (a) {
        var c = a.cloneNode(true);
        c.querySelectorAll('.badge, .visually-hidden, .sr-only').forEach(function (x) { x.remove(); });
        return textOf(c);
    };
    var badgeOf = function (a) { var b = a.querySelector('.badge'); return b ? textOf(b) : ''; };
    var make = function (tag, cls, text) { var el = document.createElement(tag); if (cls) el.className = cls; if (text != null) el.textContent = text; return el; };
    var adminPath = function (href) {
        try { var p = new URL(href, location.href).pathname; var i = p.indexOf('/admin/'); return i >= 0 ? p.slice(i) : p; } catch (e) { return ''; }
    };

    // ── Read the sidebar: modules, their groups and links ──
    var modules = [];
    nav.querySelectorAll(':scope > li.nav-item').forEach(function (li) {
        if (li.classList.contains('pcoded-menu-caption')) return;
        var label = textOf(li.querySelector(':scope > a .pcoded-mtext')) || textOf(li.querySelector(':scope > a'));
        var current = li.classList.contains('active');
        var sections = [{ label: null, links: [] }];
        var direct = li.querySelector(':scope > a');
        if (!li.classList.contains('pcoded-hasmenu')) {
            if (direct && direct.getAttribute('href') && direct.getAttribute('href') !== '#!') {
                sections[0].links.push({ text: label, href: direct.href, current: current });
            }
        } else {
            var walk = function (ul, into, prefix) {
                ul.querySelectorAll(':scope > li').forEach(function (sub) {
                    var a = sub.querySelector(':scope > a');
                    if (!a) return;
                    if (sub.classList.contains('pcoded-hasmenu')) {
                        var name = textOf(a);
                        var s = { label: prefix ? prefix + ' › ' + name : name, links: [] };
                        sections.push(s);
                        var inner = sub.querySelector(':scope > ul.pcoded-submenu');
                        if (inner) walk(inner, s, s.label);
                    } else if (a.getAttribute('href') && a.getAttribute('href') !== '#!') {
                        into.links.push({ text: linkText(a), badge: badgeOf(a), href: a.href, current: sub.classList.contains('active') });
                    }
                });
            };
            var ul = li.querySelector(':scope > ul.pcoded-submenu');
            if (ul) walk(ul, sections[0], null);
        }
        sections = sections.filter(function (s) { return s.links.length; });
        if (sections.length) modules.push({ label: label, sections: sections, current: current, icon: li.querySelector('.pcoded-micon') });
    });

    // ── Group the modules into a handful of menus, by where their screens live ──
    var CATS = [
        ['students', /\/admin\/(admission|student|applicant|application|transcript|archive|form-a)/],
        // Routines, examinations and the library are menus of their own;
        // checked before Academic, the first match wins.
        ['routines', /\/admin\/routine/],
        ['exams', /\/admin\/(exam|result|marking|grade|resit|senate)/],
        ['library', /\/admin\/(library|member|e-library|elibrary|book)/],
        ['academic', /\/admin\/(academic|download|content|study|subject|program|semester|session|faculty|course|section|enroll)/],
        ['finance', /\/admin\/(fees|fee-|platform-fee|income|expense|budget|payment|account|accounting|ledger|journal|edutrustpay|finance|tax|bank|asset)/],
        ['people', /\/admin\/(hr|human|staff|user|leave|payroll|designation|department|employee|work-shift|attendance)/],
        ['campus', /\/admin\/(communicate|inventory|hostel|transport|front-desk|frontdesk|visitor|postal|complain|meeting|notice|email|sms|event|item|vehicle|room)/],
        ['reports', /\/admin\/(report|audit)/],
        ['website', /\/admin\/(web|front|marketing|page|slider|news|gallery|faq|testimonial|about|social|topbar|call-to-action|feature|course-web)/],
        ['settings', /\/admin\/(setting|security|role|profile|language|translation|field|mail|sms-setting|schedule)/]
    ];
    var ORDER = ['students', 'academic', 'routines', 'exams', 'library', 'finance', 'people', 'campus', 'reports', 'website', 'settings', 'more'];
    var categoryOf = function (mod) {
        var votes = {};
        mod.sections.forEach(function (s) {
            s.links.forEach(function (l) {
                var p = adminPath(l.href);
                for (var i = 0; i < CATS.length; i++) {
                    if (CATS[i][1].test(p)) { votes[CATS[i][0]] = (votes[CATS[i][0]] || 0) + 1; break; }
                }
            });
        });
        var best = 'more', n = 0;
        Object.keys(votes).forEach(function (k) { if (votes[k] > n) { best = k; n = votes[k]; } });
        return best;
    };

    var dashboardUrl = (document.querySelector('.pcoded-navbar .b-brand') || document.querySelector('.pcoded-inner-navbar a.nav-link') || {}).href || '/admin';
    var menus = {};
    modules.forEach(function (mod) {
        // The dashboard is the brand and the pinned tab, not a menu entry.
        var only = mod.sections.length === 1 && mod.sections[0].links.length === 1 ? mod.sections[0].links[0].href : null;
        if (only && new URL(only, location.href).pathname.replace(/\/+$/, '') === new URL(dashboardUrl, location.href).pathname.replace(/\/+$/, '')) return;
        var key = categoryOf(mod);
        (menus[key] = menus[key] || []).push(mod);
    });

    // ── Title bar ──
    var bar = make('div'); bar.id = 'ws-titlebar';
    var brand = make('a', 'ws-brand'); brand.href = dashboardUrl; brand.title = T.dashboard || 'Dashboard';
    var logo = document.querySelector('.pcoded-navbar .b-brand img');
    if (logo) { var img = make('img'); img.src = logo.src; img.alt = ''; brand.appendChild(img); }
    brand.appendChild(make('span', null, T.school || textOf(document.querySelector('.topbar-title')) || 'Admin'));
    var menubar = make('nav'); menubar.id = 'ws-menubar'; menubar.setAttribute('role', 'menubar');
    var pageTitle = (document.title.split('|')[0] || '').trim();
    bar.append(brand, menubar, make('span', 'ws-page', pageTitle));
    document.body.insertBefore(bar, document.body.firstChild);

    var openItem = null;
    var openMenu = function (item, focusFirst) {
        if (openItem && openItem !== item) closeMenus();
        openItem = item; item.classList.add('open');
        item.firstChild.setAttribute('aria-expanded', 'true');
        if (focusFirst) { var f = item.querySelector('.ws-link'); if (f) f.focus(); }
    };
    var closeMenus = function () {
        if (!openItem) return;
        openItem.classList.remove('open'); openItem.firstChild.setAttribute('aria-expanded', 'false'); openItem = null;
    };

    ORDER.forEach(function (key) {
        var mods = menus[key];
        if (!mods || !mods.length) return;
        var item = make('div', 'ws-mi');
        var top = make('button', 'ws-top', T['menu_' + key] || key);
        top.type = 'button'; top.setAttribute('role', 'menuitem'); top.setAttribute('aria-haspopup', 'true'); top.setAttribute('aria-expanded', 'false');
        var drop = make('div', 'ws-drop'); drop.setAttribute('role', 'menu');
        mods.forEach(function (mod) {
            mod.sections.forEach(function (s) {
                // Within a one-module menu the module's name is the menu's own.
                var heading = mods.length > 1 ? (s.label ? mod.label + ' › ' + s.label : mod.label) : s.label;
                if (mods.length > 1 || s.label) drop.appendChild(make('div', 'ws-head', heading));
                s.links.forEach(function (l) {
                    var a = make('a', 'ws-link' + (l.current ? ' current' : ''), l.text);
                    a.href = l.href; a.setAttribute('role', 'menuitem');
                    if (l.badge) a.appendChild(make('span', 'ws-badge', l.badge));
                    if (l.current) item.classList.add('current');
                    drop.appendChild(a);
                });
            });
        });
        item.append(top, drop);
        menubar.appendChild(item);
        top.addEventListener('click', function () { openItem === item ? closeMenus() : openMenu(item); });
        top.addEventListener('mouseenter', function () { if (openItem && openItem !== item) openMenu(item); });
    });

    menubar.addEventListener('keydown', function (e) {
        var item = e.target.closest('.ws-mi'); if (!item) return;
        var all = Array.prototype.slice.call(menubar.querySelectorAll('.ws-mi'));
        var i = all.indexOf(item);
        var links = Array.prototype.slice.call(item.querySelectorAll('.ws-link'));
        var j = links.indexOf(e.target);
        if (e.key === 'Escape') { closeMenus(); item.firstChild.focus(); e.preventDefault(); }
        else if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            var next = all[(i + (e.key === 'ArrowRight' ? 1 : -1) + all.length) % all.length];
            var wasOpen = !!openItem; closeMenus(); next.firstChild.focus(); if (wasOpen) openMenu(next, true); e.preventDefault();
        } else if (e.key === 'ArrowDown') { if (j < 0) openMenu(item, true); else links[Math.min(j + 1, links.length - 1)].focus(); e.preventDefault(); }
        else if (e.key === 'ArrowUp' && j >= 0) { (j === 0 ? item.firstChild : links[j - 1]).focus(); e.preventDefault(); }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('#ws-menubar')) closeMenus(); });

    // The announcement ticker, when there is one, sits under the title bar.
    var ann = document.querySelector('.site-announcement');
    var fitAnnouncement = function () {
        document.body.style.setProperty('--ws-top', (32 + (ann && isDesk() ? ann.offsetHeight : 0)) + 'px');
    };
    fitAnnouncement();
    window.addEventListener('resize', fitAnnouncement);

    // ── Toolbar: buttons in the header ──
    var headerLeft = document.querySelector('.pcoded-header .header-left');
    if (headerLeft) {
        var tools = make('div', 'ws-tools'); tools.setAttribute('role', 'toolbar');
        var tool = function (act, title, svg, label) {
            var b = make('button'); b.type = 'button'; b.dataset.ws = act; b.title = title; b.setAttribute('aria-label', title);
            b.innerHTML = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + svg + '</svg>';
            if (label) { b.className = 'ws-go'; b.appendChild(make('span', null, label)); }
            return b;
        };
        tools.append(
            tool('back', (T.back || 'Back') + ' (Alt+←)', '<path d="M15 19l-7-7 7-7"/>'),
            tool('forward', (T.forward || 'Forward') + ' (Alt+→)', '<path d="M9 5l7 7-7 7"/>'),
            tool('reload', (T.refresh || 'Refresh') + ' (F5)', '<path d="M4 4v5h.6m14.8 2A8 8 0 004.6 9M4.6 9H9m11 11v-5h-.6m0 0a8 8 0 01-15.4-2m15.4 2H15"/>'),
            make('span', 'ws-sep'),
            tool('print', (T.print || 'Print') + ' (Ctrl+P)', '<path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>'),
            tool('palette', (T.goto_hint || 'Go to any screen') + ' (Ctrl+K)', '<path d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>', T.goto || 'Go to…')
        );
        headerLeft.insertBefore(tools, headerLeft.firstChild);
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-ws]'); if (!b) return;
        var act = b.dataset.ws;
        if (act === 'back') history.back();
        else if (act === 'forward') history.forward();
        else if (act === 'reload') location.reload();
        else if (act === 'print') window.print();
        else if (act === 'palette') openPalette();
    });

    // ── Rail: the template's own collapsed sidebar ──
    var navbar = document.querySelector('.pcoded-navbar');
    if (isDesk() && navbar && store.get('ws.sidebarFull') !== '1') navbar.classList.add('navbar-collapsed');
    document.querySelectorAll('#mobile-collapse, #mobile-collapse1').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!isDesk()) return;
            setTimeout(function () { store.set('ws.sidebarFull', navbar.classList.contains('navbar-collapsed') ? '0' : '1'); }, 50);
        });
    });

    // ── Tabs: the screens opened in this window ──
    var tabs = make('nav'); tabs.id = 'ws-tabs'; tabs.setAttribute('aria-label', T.open_screens || 'Open screens');
    var main = document.querySelector('.pcoded-main-container');
    if (main) main.parentNode.insertBefore(tabs, main);
    var TABS = 'ws.tabs';
    var keyOf = function (href) { return new URL(href, location.href).pathname.replace(/\/+$/, ''); };
    var readTabs = function () { try { var t = JSON.parse(store.get(TABS) || '[]'); return Array.isArray(t) ? t : []; } catch (e) { return []; } };
    var writeTabs = function (t) { store.set(TABS, JSON.stringify(t)); };
    var homeKey = keyOf(dashboardUrl);

    var drawTabs = function () {
        var current = keyOf(location.href);
        tabs.replaceChildren();
        var add = function (t, pinned) {
            var a = make('a', 'ws-tab' + (t.key === current ? ' active' : '') + (pinned ? ' pinned' : ''));
            a.href = t.url; a.title = t.title; a.dataset.key = t.key; a.setAttribute('role', 'tab');
            a.appendChild(make('span', null, t.title));
            if (!pinned) {
                var x = make('button', 'ws-x', '×'); x.type = 'button';
                x.title = T.close_tab || 'Close this tab'; x.setAttribute('aria-label', (T.close_tab || 'Close this tab') + ': ' + t.title);
                a.appendChild(x);
            }
            tabs.appendChild(a);
            if (t.key === current) requestAnimationFrame(function () { a.scrollIntoView({ inline: 'nearest', block: 'nearest' }); });
        };
        add({ key: homeKey, url: dashboardUrl, title: T.dashboard || 'Dashboard' }, true);
        readTabs().forEach(function (t) { add(t, false); });
    };
    var rememberTab = function () {
        var key = keyOf(location.href);
        if (key !== homeKey) {
            var list = readTabs();
            var entry = { key: key, url: location.href, title: pageTitle || document.title };
            var i = list.findIndex(function (t) { return t.key === key; });
            if (i >= 0) list[i] = entry; else list.push(entry);
            while (list.length > 10) list.splice(list.findIndex(function (t) { return t.key !== key; }), 1);
            writeTabs(list);
        }
        drawTabs();
    };
    var closeTab = function (key) {
        var list = readTabs(); var i = list.findIndex(function (t) { return t.key === key; });
        if (i < 0) return;
        list.splice(i, 1); writeTabs(list);
        if (key === keyOf(location.href)) { var next = list[i] || list[i - 1]; location.href = next ? next.url : dashboardUrl; }
        else drawTabs();
    };
    tabs.addEventListener('click', function (e) {
        var x = e.target.closest('.ws-x'); if (!x) return;
        e.preventDefault(); e.stopPropagation(); closeTab(x.closest('.ws-tab').dataset.key);
    });
    tabs.addEventListener('auxclick', function (e) {
        var t = e.target.closest('.ws-tab:not(.pinned)'); if (e.button !== 1 || !t) return;
        e.preventDefault(); closeTab(t.dataset.key);
    });
    window.addEventListener('storage', function (e) { if (e.key === TABS) drawTabs(); });
    rememberTab();

    // The strip starts where the sidebar ends, rail or full.
    var placeTabs = function () { tabs.style.left = (navbar && isDesk() ? Math.round(navbar.getBoundingClientRect().right) : 0) + 'px'; };
    placeTabs();
    window.addEventListener('resize', placeTabs);
    if (navbar && window.ResizeObserver) new ResizeObserver(placeTabs).observe(navbar);

    // ── Status bar ──
    var status = make('footer'); status.id = 'ws-status';
    var conn = make('span', 'ws-conn'); conn.appendChild(make('span', 'ws-dot'));
    var connText = make('span', null, T.connected || 'Connected'); conn.appendChild(connText);
    var session = document.querySelector('.header-session-badge');
    var layout = make('button', null); layout.id = 'ws-layout'; layout.type = 'button'; layout.title = T.layout_hint || '';
    var go = make('button'); go.type = 'button'; go.dataset.ws = 'palette';
    go.appendChild(make('kbd', null, 'Ctrl K')); go.appendChild(document.createTextNode(' ' + (T.goto || 'Go to…')));
    var clock = make('span'); clock.style.fontVariantNumeric = 'tabular-nums';
    status.append(conn);
    if (session) status.append(make('strong', null, textOf(session)));
    status.append(make('span', 'ws-spacer'), go, layout, clock);
    document.body.appendChild(status);

    var showConn = function () {
        status.dataset.conn = navigator.onLine ? 'online' : 'offline';
        connText.textContent = navigator.onLine ? (T.connected || 'Connected') : (T.offline || 'No network');
    };
    window.addEventListener('online', showConn); window.addEventListener('offline', showConn); showConn();
    var tick = function () {
        var d = new Date();
        clock.textContent = d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) + '  ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    };
    tick(); setInterval(tick, 15000);

    var showLayout = function () { layout.textContent = html.classList.contains('ws-classic') ? (T.layout_desktop || 'Desktop layout') : (T.layout_classic || 'Classic layout'); };
    layout.addEventListener('click', function () {
        var classic = html.classList.toggle('ws-classic');
        store.set('ws.layout', classic ? 'classic' : 'desktop');
        if (navbar) navbar.classList.toggle('navbar-collapsed', !classic && store.get('ws.sidebarFull') !== '1');
        showLayout(); fitAnnouncement();
    });
    showLayout();

    // ── "Go to…": every screen in the menus, found by typing ──
    var screens = [];
    modules.forEach(function (mod) {
        mod.sections.forEach(function (s) {
            s.links.forEach(function (l) { screens.push({ text: l.text, group: s.label ? mod.label + ' › ' + s.label : mod.label, href: l.href }); });
        });
    });
    var pal = make('div'); pal.id = 'ws-palette';
    var box = make('div', 'ws-pal');
    var input = make('input'); input.type = 'search'; input.placeholder = T.goto_placeholder || 'Go to a screen…'; input.setAttribute('aria-label', T.goto || 'Go to…');
    var list = make('ul'); list.setAttribute('role', 'listbox');
    box.append(input, list); pal.appendChild(box); document.body.appendChild(pal);
    var sel = 0, shown = [];
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var draw = function () {
        var words = norm(input.value).split(/\s+/).filter(Boolean);
        shown = screens.map(function (s) {
            var name = norm(s.text), group = norm(s.group);
            var score = 0;
            for (var i = 0; i < words.length; i++) {
                if (name.indexOf(words[i]) === 0) score += 4; else if (name.indexOf(words[i]) >= 0) score += 3;
                else if (group.indexOf(words[i]) >= 0) score += 1; else return null;
            }
            return { s: s, score: score };
        }).filter(Boolean).sort(function (a, b) { return b.score - a.score; }).slice(0, 12).map(function (x) { return x.s; });
        sel = Math.min(sel, Math.max(0, shown.length - 1));
        list.replaceChildren();
        if (!shown.length) { list.appendChild(make('li', 'ws-empty', T.nothing || 'Nothing matches.')); return; }
        shown.forEach(function (s, i) {
            var li = make('li'); var a = make('a', i === sel ? 'sel' : ''); a.href = s.href;
            a.append(make('span', null, s.text), make('small', null, s.group));
            li.appendChild(a); list.appendChild(li);
        });
    };
    function openPalette() { pal.classList.add('open'); input.value = ''; sel = 0; draw(); input.focus(); }
    var closePalette = function () { pal.classList.remove('open'); };
    input.addEventListener('input', function () { sel = 0; draw(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { sel = Math.min(sel + 1, shown.length - 1); draw(); e.preventDefault(); }
        else if (e.key === 'ArrowUp') { sel = Math.max(sel - 1, 0); draw(); e.preventDefault(); }
        else if (e.key === 'Enter' && shown[sel]) { location.href = shown[sel].href; }
        else if (e.key === 'Escape') closePalette();
    });
    pal.addEventListener('click', function (e) { if (e.target === pal) closePalette(); });

    // Pop-up windows. On the desktop layout the screen's content sits in a
    // fixed panel, and a fixed panel is its own layer: a pop-up inside it can
    // never rise above the dark backdrop Bootstrap puts on <body>, so it opened
    // dimmed and unclickable. So a pop-up is lifted out to <body> as it opens,
    // beside its backdrop. Its scripts hold it by id and keep working.
    // A pop-up that lives inside a <form> (a confirm step whose button is the
    // form's own submit) must stay inside it; for those the backdrop is moved
    // in next to the pop-up instead, into the same layer.
    var contentPanel = function (el) { return el.closest('.pcoded-main-container'); };
    document.addEventListener('show.bs.modal', function (e) {
        var modal = e.target;
        if (!isDesk() || !modal.classList || !modal.classList.contains('modal') || !contentPanel(modal)) return;
        if (!modal.closest('form')) { document.body.appendChild(modal); return; }
        // Bootstrap adds the backdrop straight after this event.
        setTimeout(function () {
            var backdrops = document.body.querySelectorAll(':scope > .modal-backdrop');
            var backdrop = backdrops[backdrops.length - 1];
            if (backdrop && modal.parentNode) modal.parentNode.insertBefore(backdrop, modal);
        }, 0);
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'k') { e.preventDefault(); openPalette(); return; }
        if (!isDesk()) return;
        if (e.key === 'F10' && !e.shiftKey) { e.preventDefault(); var f = menubar.querySelector('.ws-top'); if (f) f.focus(); return; }
        if (e.altKey && !e.ctrlKey && !e.metaKey && /^[1-9]$/.test(e.key)) {
            var t = tabs.querySelectorAll('.ws-tab')[Number(e.key) - 1];
            if (t) { e.preventDefault(); location.href = t.href; }
        }
    });
})();
