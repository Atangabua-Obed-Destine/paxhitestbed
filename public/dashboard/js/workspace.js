/*
 * Desktop workspace for the admin (styles: public/dashboard/css/workspace.css).
 *
 * On a wide screen: a title bar with menus, the header as a toolbar, the
 * screens opened as tabs, the sidebar as an icon rail, a status bar, and a
 * "Go to…" box (Ctrl+K). The menus and the box are built from the sidebar,
 * so they offer exactly what the sidebar offers, permissions included.
 * Tabs stay alive: each screen opens in a frame of its own, so switching
 * back to one shows it as it was left. Inside such a frame this script only
 * hands keys and Dashboard links back to the window (html.ws-framed).
 * Every wide screen gets this layout; there is no switch back to the
 * template's own (phones and narrow windows keep it, as they must).
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
    var isDesk = function () { return wide.matches; };
    // ── A screen shown inside a workspace tab ──
    var shell = null;
    try { if (window.parent !== window && window.parent.WS_SHELL) shell = window.parent.WS_SHELL; } catch (e) { /* not ours */ }
    if (shell) {
        html.classList.add('ws-framed');
        document.addEventListener('keydown', function (e) {
            var k = (e.key || '').toLowerCase();
            var ctl = e.ctrlKey || e.metaKey;
            if (ctl && !e.shiftKey && k === 'k') { e.preventDefault(); shell.palette(); }
            else if (e.altKey && !ctl && /^[1-9]$/.test(e.key)) { e.preventDefault(); shell.tab(Number(e.key)); }
            else if (e.altKey && !ctl && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) { e.preventDefault(); shell.step(e.key === 'ArrowLeft' ? -1 : 1); }
            // Refresh and print this tab's screen, not the whole window.
            else if ((e.key === 'F5' && !ctl && !e.shiftKey) || (ctl && !e.shiftKey && k === 'r')) { e.preventDefault(); location.reload(); }
            else if (ctl && !e.shiftKey && k === 'p') { e.preventDefault(); window.print(); }
        });
        // On the Dashboard a link opens a tab of its own.
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            var a = e.target.closest && e.target.closest('a[href]');
            if (a && shell.link(window, a)) {
                e.preventDefault();
                // The page's loading screen came up for a page change that is not happening here.
                if (window.appLoading) window.appLoading.hide();
            }
        });
        return;
    }

    var nav = document.querySelector('.pcoded-navbar .pcoded-inner-navbar');
    if (!nav) return;

    html.classList.add('ws');
    // An older version offered a "Classic layout" switch; forget that choice.
    try { localStorage.removeItem('ws.layout'); } catch (e) { /* private mode */ }

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
        ['campus', /\/admin\/(communicate|mailbox|inventory|equipment|hostel|transport|front-desk|frontdesk|visitor|postal|complain|meeting|notice|email|sms|event|item|vehicle|room)/],
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
    var pageName = make('span', 'ws-page', pageTitle);
    bar.append(brand, menubar, pageName);
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
        if (act === 'back') step(-1);
        else if (act === 'forward') step(1);
        else if (act === 'reload') reloadTab();
        else if (act === 'print') printTab();
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

    // ── Tabs: the screens open in this window, kept alive ──
    // The page this window loaded is the first screen. Every other screen
    // opens in a frame of its own laid over it, so going back to a tab shows
    // the screen exactly as it was left: no reload, scroll and typing kept.
    // Besides the pinned Dashboard, two screens stay open at a time; opening
    // a third asks first, naming the one that will close.
    var tabs = make('nav'); tabs.id = 'ws-tabs'; tabs.setAttribute('aria-label', T.open_screens || 'Open screens');
    var main = document.querySelector('.pcoded-main-container');
    if (main) main.parentNode.insertBefore(tabs, main);
    var MAX_TABS = 2;
    var live = isDesk() && !!main;
    var homeKey = new URL(dashboardUrl, location.href).pathname.replace(/\/+$/, '');
    // The dashboard also answers at /admin/dashboard; that is the same pinned tab.
    var keyOf = function (href) {
        var k = new URL(href, location.href).pathname.replace(/\/+$/, '');
        return k === homeKey + '/dashboard' ? homeKey : k;
    };
    var titleOf = function (doc) { return ((doc.title || '').split('|')[0] || '').trim(); };
    var fill = function (s, v) { return String(s).replace(/:(\w+)/g, function (m, k) { return v[k] != null ? v[k] : m; }); };
    var titleSuffix = document.title.indexOf('|') >= 0 ? ' ' + document.title.slice(document.title.indexOf('|')) : '';

    // Tabs belong to this browser window: kept for a refresh, not shared.
    try { localStorage.removeItem('ws.tabs'); } catch (e) { /* private mode */ }
    var LIVE = 'ws.live';
    var saved = (function () { try { return JSON.parse(sessionStorage.getItem(LIVE) || 'null') || {}; } catch (e) { return {}; } })();
    var seq = saved.seq || 1;
    var home = { id: 'home', url: dashboardUrl, title: T.dashboard || 'Dashboard', pinned: true, stack: [], pos: -1 };
    var open = (Array.isArray(saved.tabs) ? saved.tabs : []).filter(function (t) {
        return t && t.id && t.url && keyOf(t.url) !== homeKey;
    }).slice(-MAX_TABS).map(function (t) { return { id: t.id, url: t.url, title: t.title || '', used: t.used || 0, stack: [], pos: -1 }; });
    var all = function () { return [home].concat(open); };
    var save = function () {
        try {
            sessionStorage.setItem(LIVE, JSON.stringify({ seq: seq, active: active ? active.id : null, at: location.href, tabs: open.map(function (t) {
                return { id: t.id, url: t.url, title: t.title, used: t.used };
            }) }));
        } catch (e) { /* private mode */ }
    };
    var notice = function (text) {
        var n = make('div', 'ws-notice', text); n.setAttribute('role', 'status');
        document.body.appendChild(n);
        setTimeout(function () { n.remove(); }, 7000);
    };
    // The tab to close when a third is wanted: the one used longest ago,
    // never the one on screen.
    var leastUsed = function () {
        return open.filter(function (t) { return t !== active; }).sort(function (a, b) { return (a.used || 0) - (b.used || 0); })[0] || null;
    };

    // This page's own content is the screen of one tab.
    var nativeKey = keyOf(location.href);
    var native;
    if (nativeKey === homeKey) native = home;
    else {
        native = open.filter(function (t) { return keyOf(t.url) === nativeKey; })[0];
        if (!native) {
            native = { id: 't' + (seq++), url: location.href, title: pageTitle, used: Date.now(), stack: [], pos: -1 };
            if (open.length >= MAX_TABS) {
                var dropped = open.slice().sort(function (a, b) { return (a.used || 0) - (b.used || 0); })[0];
                open.splice(open.indexOf(dropped), 1);
                notice(fill(T.tab_dropped || '“:old” was closed: besides the Dashboard, two screens stay open at a time.', { old: dropped.title }));
            }
            open.push(native);
        }
    }
    native.url = location.href; native.title = pageTitle || native.title;
    native.isNative = true; native.stack = [location.href]; native.pos = 0;
    var active = native;

    // The frames, laid over the screen area.
    var host = make('div'); host.id = 'ws-frames';
    document.body.appendChild(host);
    var frameLoaded;
    var makeFrame = function (t, name) {
        var f = make('iframe'); f.title = t.title || ''; if (name) f.name = name;
        f.wsTab = t;
        // An empty frame reports a load as soon as it is added; that one is ignored.
        f.addEventListener('load', function () { if (frameLoaded) frameLoaded(f); });
        host.appendChild(f);
        return f;
    };
    // Forms and links on this page's own content load into this frame, so
    // sending one does not reload the window and close the other tabs.
    var nativeFrame = live ? makeFrame(native, 'ws-native') : null;
    var aimForm = function (f) {
        if (!nativeFrame || f.getAttribute('target') || f.closest('.pcoded-header, .pcoded-navbar, #ws-titlebar, #ws-palette, #ws-status')) return;
        f.setAttribute('target', 'ws-native');
    };
    var formWatch = null;
    if (live) {
        document.querySelectorAll('form').forEach(aimForm);
        formWatch = new MutationObserver(function (changes) {
            changes.forEach(function (c) {
                c.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) return;
                    if (n.tagName === 'FORM') aimForm(n);
                    n.querySelectorAll && n.querySelectorAll('form').forEach(aimForm);
                });
            });
        });
        formWatch.observe(document.body, { childList: true, subtree: true });
    }
    // Once this page's content has been replaced, let it go.
    var freeNative = function () {
        if (native.freed) return;
        native.freed = true;
        if (formWatch) formWatch.disconnect();
        var c = main && main.querySelector('.pcoded-content');
        if (c) c.replaceChildren();
        // From now on the address follows the tab on screen.
        try { history.replaceState(history.state, '', active.url); } catch (e) { /* ignore */ }
    };

    var busyTimer = {};
    // The page's loading screen (partials/loading-screen) covers the window
    // while the tab on screen loads, and only then.
    var showLoading = function () {
        if (!window.appLoading) return;
        if (active && active.loading) window.appLoading.show(); else window.appLoading.hide();
    };
    var setBusy = function (t, on) {
        t.loading = on;
        clearTimeout(busyTimer[t.id]);
        // A download never finishes loading a page; stop waiting for it.
        if (on) busyTimer[t.id] = setTimeout(function () { t.loading = false; drawTabs(); if (t === active) showLoading(); }, 20000);
        drawTabs();
        if (t === active) showLoading();
    };

    var show = function (t) {
        showLoading();
        var frameOn = !!t.frame;
        host.classList.toggle('on', frameOn);
        if (main) main.inert = frameOn;
        host.querySelectorAll('iframe').forEach(function (f) { f.classList.toggle('on', f === t.frame); });
        pageName.textContent = t.title;
        document.title = t.title + titleSuffix;
        // The address follows the tab on screen, so a refresh opens it; not
        // while this page's own content is alive, whose forms post to it.
        if (native.freed) { try { history.replaceState(history.state, '', t.url); } catch (e) { /* ignore */ } }
    };
    var drawTabs = function () {
        tabs.replaceChildren();
        all().forEach(function (t) {
            var a = make('a', 'ws-tab' + (t === active ? ' active' : '') + (t.pinned ? ' pinned' : '') + (t.loading ? ' loading' : ''));
            a.href = t.url; a.title = t.title; a.dataset.id = t.id;
            a.setAttribute('role', 'tab'); a.setAttribute('aria-selected', String(t === active));
            a.appendChild(make('span', null, t.title || T.loading || '…'));
            if (!t.pinned) {
                var x = make('button', 'ws-x', '×'); x.type = 'button';
                x.title = T.close_tab || 'Close this tab'; x.setAttribute('aria-label', (T.close_tab || 'Close this tab') + ': ' + t.title);
                a.appendChild(x);
            }
            tabs.appendChild(a);
            if (t === active) requestAnimationFrame(function () { a.scrollIntoView({ inline: 'nearest', block: 'nearest' }); });
        });
    };
    var activate = function (t) {
        if (!t) return;
        if (!live) { location.href = t.url; return; }
        active = t; t.used = Date.now();
        // A tab not loaded yet (after a refresh, or this page's own once let go) loads now.
        if (!t.frame && (!t.isNative || t.freed)) {
            t.frame = makeFrame(t); t.stack = []; t.pos = -1;
            setBusy(t, true);
            t.frame.src = t.url;
        }
        show(t); drawTabs(); save();
        if (t.frame) { try { t.frame.contentWindow.focus(); } catch (e) { /* ignore */ } }
    };
    var closeTab = function (t, keepActive) {
        var i = open.indexOf(t); if (i < 0) return;
        open.splice(i, 1);
        if (t.frame) t.frame.remove();
        if (t.isNative) {
            freeNative();
            if (nativeFrame && nativeFrame !== t.frame) nativeFrame.remove();
            nativeFrame = null;
        }
        if (active === t && !keepActive) activate(open[i] || open[i - 1] || home);
        else { drawTabs(); save(); }
    };
    var labelFor = function (href) {
        var k = keyOf(href);
        var s = screens.filter(function (x) { return keyOf(x.href) === k; })[0];
        return s ? s.text : (T.loading || '…');
    };
    var confirmClose = function (victim, nextTitle, ok) {
        var d = make('dialog', 'ws-confirm');
        d.setAttribute('aria-labelledby', 'ws-confirm-title');
        var h = make('h3', null, T.tabs_full_title || 'Two screens are already open'); h.id = 'ws-confirm-title';
        var v = { 'new': nextTitle, old: victim.title };
        var p = make('p', null, fill(T.tabs_full_body || 'Besides the Dashboard, two screens stay open at a time. Opening “:new” will close “:old”, the one you used longest ago. Anything not saved on “:old” will be lost.', v));
        var row = make('div', 'ws-confirm-row');
        var no = make('button', 'ws-btn', T.cancel || 'Cancel'); no.type = 'button';
        var yes = make('button', 'ws-btn primary', fill(T.tabs_full_ok || 'Close “:old” and open', v)); yes.type = 'button';
        row.append(no, yes); d.append(h, p, row); document.body.appendChild(d);
        var done = function (go) { if (d.open) d.close(); d.remove(); if (go) ok(); };
        no.addEventListener('click', function () { done(false); });
        yes.addEventListener('click', function () { done(true); });
        d.addEventListener('cancel', function (e) { e.preventDefault(); done(false); });
        d.showModal(); yes.focus();
    };
    var openUrl = function (href) {
        if (!live) { location.href = href; return; }
        var key = keyOf(href);
        if (key === homeKey) { activate(home); return; }
        var have = open.filter(function (x) { return keyOf(x.url) === key; })[0];
        if (have) { activate(have); return; }
        var go = function () {
            var t = { id: 't' + (seq++), url: href, title: labelFor(href), stack: [], pos: -1 };
            open.push(t); activate(t);
        };
        if (open.length < MAX_TABS) { go(); return; }
        var victim = leastUsed();
        if (!victim) return;
        confirmClose(victim, labelFor(href), function () { closeTab(victim, true); go(); });
    };
    var navigateNative = function (href) {
        if (!nativeFrame) { location.href = href; return; }
        setBusy(native, true);
        nativeFrame.src = href;
    };

    frameLoaded = function (f) {
        var t = f.wsTab; if (!t) return;
        var w = f.contentWindow, href;
        try { href = w.location.href; } catch (e) { return; }
        if (!href || href === 'about:blank') return;
        var d = w.document;
        // Signed out (the session ended, or signed out in this tab): the whole window goes to sign in.
        if (!d.querySelector('.pcoded-main-container') && d.querySelector('input[type="password"]')) { location.href = href; return; }
        setBusy(t, false);
        // A form on this page's own content was sent here: its loading screen can go.
        if (f === nativeFrame && window.appLoading) window.appLoading.hide();
        if (t.isNative && f === nativeFrame && !t.frame) { t.frame = f; freeNative(); }
        if (t.goingTo != null) { t.pos = t.goingTo; t.goingTo = null; }
        else if (t.stack[t.pos] !== href) { t.stack = t.stack.slice(0, t.pos + 1); t.stack.push(href); t.pos = t.stack.length - 1; }
        t.url = href; t.title = titleOf(d) || t.title; f.title = t.title;
        var key = keyOf(href);
        if (t === home && key !== homeKey) {
            // Something sent on the Dashboard opened another screen: that is a tab of its own.
            var nt = { id: 't' + (seq++), url: href, title: t.title, frame: f, stack: home.stack, pos: home.pos, used: Date.now() };
            f.wsTab = nt; home.frame = null; home.stack = []; home.pos = -1; home.url = dashboardUrl; home.title = T.dashboard || 'Dashboard';
            if (open.length >= MAX_TABS) {
                var gone = leastUsed() || open[0];
                closeTab(gone, true);
                notice(fill(T.tab_dropped || '“:old” was closed: besides the Dashboard, two screens stay open at a time.', { old: gone.title }));
            }
            open.push(nt); activate(nt); return;
        }
        if (t !== home && key === homeKey) {
            // A screen led back to the Dashboard: that is the pinned tab.
            var homeAlive = home.frame || (home.isNative && !home.freed);
            open.splice(open.indexOf(t), 1);
            if (homeAlive) f.remove(); else { home.frame = f; f.wsTab = home; home.stack = t.stack; home.pos = t.pos; }
            activate(home); return;
        }
        if (t === home) { home.title = T.dashboard || 'Dashboard'; }
        if (t === active) show(t);
        drawTabs(); save();
    };

    // Back, forward and refresh act on the tab on screen, and only on it.
    var step = function (d) {
        if (!live) { history.go(d); return; }
        var t = active; if (!t.frame) return;
        var p = t.pos + d; if (p < 0 || p >= t.stack.length) return;
        t.goingTo = p; setBusy(t, true);
        t.frame.contentWindow.location.replace(t.stack[p]);
    };
    var reloadTab = function () {
        if (!live) { location.reload(); return; }
        var t = active;
        if (t.frame) { setBusy(t, true); t.frame.contentWindow.location.reload(); }
        // This page's own screen reloads into its frame, which keeps the other tabs.
        else if (t.isNative) navigateNative(t.url);
    };
    var printTab = function () {
        if (live && active.frame) { active.frame.contentWindow.focus(); active.frame.contentWindow.print(); }
        else window.print();
    };

    tabs.addEventListener('click', function (e) {
        var a = e.target.closest('.ws-tab'); if (!a) return;
        var t = all().filter(function (x) { return x.id === a.dataset.id; })[0];
        if (!t) return;
        e.preventDefault();
        if (e.target.closest('.ws-x')) { e.stopPropagation(); closeTab(t); return; }
        activate(t);
    });
    tabs.addEventListener('auxclick', function (e) {
        var a = e.target.closest('.ws-tab:not(.pinned)'); if (e.button !== 1 || !a) return;
        e.preventDefault();
        var t = all().filter(function (x) { return x.id === a.dataset.id; })[0];
        if (t) closeTab(t);
    });

    // Which links open a screen: this admin's own pages, opened plainly.
    var screenLink = function (a) {
        if (!a || a.hasAttribute('download')) return null;
        var tg = (a.getAttribute('target') || '').toLowerCase(); if (tg && tg !== '_self') return null;
        var raw = (a.getAttribute('href') || '').trim();
        if (!raw || raw.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(raw)) return null;
        var u; try { u = new URL(a.href, location.href); } catch (e) { return null; }
        if (u.origin !== location.origin || !/\/admin(\/|$)/.test(u.pathname) || /\/(login|logout)(\/|$)/.test(u.pathname)) return null;
        return u.href;
    };
    if (live) {
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || a.closest('#ws-tabs')) return;
            var href = screenLink(a); if (!href) return;
            e.preventDefault();
            // The page's loading screen expects the window to change page; it
            // does not, the tab shows its own progress instead.
            if (window.appLoading) window.appLoading.hide();
            closeMenus(); closePalette();
            // The menus, the sidebar and the Dashboard open screens in tabs;
            // a link inside a screen moves that screen on, like a browser tab.
            var inChrome = a.closest('.pcoded-navbar, .pcoded-header, #ws-titlebar, #ws-palette, #ws-status');
            if (inChrome || native === home) openUrl(href); else navigateNative(href);
        });
        document.addEventListener('submit', function (e) {
            if (!e.defaultPrevented && e.target.getAttribute('target') === 'ws-native') setBusy(native, true);
        });
        // The screens in the frames reach the window through this.
        window.WS_SHELL = {
            palette: function () { openPalette(); },
            tab: function (n) { var t = all()[n - 1]; if (t) activate(t); },
            step: step,
            link: function (win, a) {
                var t = all().filter(function (x) { return x.frame && x.frame.contentWindow === win; })[0];
                if (t !== home) return false;
                var href = screenLink(a); if (!href) return false;
                openUrl(href); return true;
            }
        };
    }
    drawTabs(); save();
    // After a refresh, the tab that was on screen comes back on screen.
    // A screen asked for by its address (a bookmark, a link) stays on screen.
    if (live && saved.at === location.href && saved.active && saved.active !== active.id) {
        var back = all().filter(function (x) { return x.id === saved.active; })[0];
        if (back) activate(back);
    }

    // The strip starts where the sidebar ends, rail or full.
    var placeTabs = function () {
        var left = (navbar && isDesk() ? Math.round(navbar.getBoundingClientRect().right) : 0) + 'px';
        tabs.style.left = left; host.style.left = left;
    };
    placeTabs();
    window.addEventListener('resize', placeTabs);
    if (navbar && window.ResizeObserver) new ResizeObserver(placeTabs).observe(navbar);

    // ── Status bar ──
    var status = make('footer'); status.id = 'ws-status';
    var conn = make('span', 'ws-conn'); conn.appendChild(make('span', 'ws-dot'));
    var connText = make('span', null, T.connected || 'Connected'); conn.appendChild(connText);
    var session = document.querySelector('.header-session-badge');
    var go = make('button'); go.type = 'button'; go.dataset.ws = 'palette';
    go.appendChild(make('kbd', null, 'Ctrl K')); go.appendChild(document.createTextNode(' ' + (T.goto || 'Go to…')));
    var clock = make('span'); clock.style.fontVariantNumeric = 'tabular-nums';
    status.append(conn);
    if (session) status.append(make('strong', null, textOf(session)));
    status.append(make('span', 'ws-spacer'), go, clock);
    document.body.appendChild(status);

    // The assistant's launcher floats over the screen's corner; on the
    // desktop layout it is a button here instead, pressing the real one.
    // The widget comes after this script in the page, so wait for it.
    var addAsk = function () {
        var chatToggle = document.getElementById('pax-chat-toggle');
        if (!chatToggle) return;
        var ask = make('button', 'ws-ask', textOf(chatToggle.querySelector('.pax-chat-toggle-label')) || 'Ask PAX');
        ask.type = 'button';
        ask.addEventListener('click', function () {
            var panel = document.getElementById('pax-chat-panel');
            var close = document.getElementById('pax-chat-close');
            if (panel && !panel.hidden && close) close.click(); else chatToggle.click();
        });
        var showUnread = function () { ask.classList.toggle('unread', chatToggle.classList.contains('has-unread')); };
        new MutationObserver(showUnread).observe(chatToggle, { attributes: true, attributeFilter: ['class'] });
        showUnread();
        status.insertBefore(ask, clock);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addAsk); else addAsk();

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
        else if (e.key === 'Enter' && shown[sel]) { e.preventDefault(); closePalette(); openUrl(shown[sel].href); }
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
        var k = (e.key || '').toLowerCase(), ctl = e.ctrlKey || e.metaKey;
        if (ctl && !e.shiftKey && k === 'k') { e.preventDefault(); openPalette(); return; }
        if (!isDesk()) return;
        if (live && e.altKey && !ctl && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) { e.preventDefault(); step(e.key === 'ArrowLeft' ? -1 : 1); return; }
        // With a tab's frame on screen, refresh and print that screen only.
        if (live && active.frame && ((e.key === 'F5' && !ctl && !e.shiftKey) || (ctl && !e.shiftKey && k === 'r'))) { e.preventDefault(); reloadTab(); return; }
        if (live && active.frame && ctl && !e.shiftKey && k === 'p') { e.preventDefault(); printTab(); return; }
        if (e.key === 'F10' && !e.shiftKey) { e.preventDefault(); var f = menubar.querySelector('.ws-top'); if (f) f.focus(); return; }
        if (e.altKey && !e.ctrlKey && !e.metaKey && /^[1-9]$/.test(e.key)) {
            var t = all()[Number(e.key) - 1];
            if (t) { e.preventDefault(); activate(t); }
        }
    });

    // Links in the window's own parts open tabs; the page's loading screen
    // (partials/loading-screen) is for page changes, so it leaves them alone.
    if (live) {
        [tabs, bar, pal, status, navbar, document.querySelector('.pcoded-header')].forEach(function (el) {
            if (el) el.setAttribute('data-no-loading', '');
        });
    }
})();
