{{-- The day-book look shared by the staff and student dashboards (.et-dash). --}}
<style>
    .et-dash {
        --et-ink: #182b49;
        --et-ink-2: #334155;
        --et-muted: #64748b;
        --et-faint: #94a3b8;
        --et-line: #e3e7ee;
        --et-rule: #eef1f6;
        --et-surface: #ffffff;
        --et-tint: #f6f8fb;
        --et-accent: #1f5fa8;
        --et-accent-tint: #e8f0fa;
        --et-margin: #e8b54a;
        --et-good: #0f7a3d;
        --et-good-tint: #e6f4ec;
        --et-warn: #9a5b00;
        --et-warn-tint: #fdf3e1;
        --et-bad: #b3261e;
        --et-bad-tint: #fbe9e7;
        --et-serif: ui-serif, Georgia, Cambria, "Times New Roman", serif;
        color: var(--et-ink-2);
        font-variant-numeric: tabular-nums;
    }
    .et-dash *, .et-dash *::before, .et-dash *::after { box-sizing: border-box; }
    .et-dash a { text-decoration: none; }
    .et-dash a:focus-visible, .et-dash button:focus-visible { outline: 2px solid var(--et-accent); outline-offset: 2px; border-radius: 6px; }

    /* ── Masthead: the day book's page head ───────────────────────────── */
    .et-mast {
        position: relative; overflow: hidden;
        display: grid; grid-template-columns: auto 1fr auto; gap: 0 28px; align-items: center;
        padding: 26px 28px; margin-bottom: 24px;
        background-color: var(--et-surface);
        /* Ruled faintly, like a register page, with its margin line. */
        background-image:
            linear-gradient(to right, transparent 111px, var(--et-margin) 111px, var(--et-margin) 112px, transparent 112px),
            repeating-linear-gradient(to bottom, transparent 0, transparent 31px, var(--et-rule) 31px, var(--et-rule) 32px);
        border: 1px solid var(--et-line); border-radius: 12px;
    }
    .et-date { width: 84px; text-align: center; line-height: 1; color: var(--et-ink); }
    .et-date-day { display: block; font-family: var(--et-serif); font-size: 56px; letter-spacing: -1px; }
    .et-date-week { display: block; margin-top: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: var(--et-muted); }
    .et-date-month { display: block; margin-top: 3px; font-size: 12px; color: var(--et-muted); }
    .et-mast-main { min-width: 0; padding-left: 8px; }
    .et-eyebrow { margin: 0 0 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .14em; color: var(--et-muted); }
    .et-greet { margin: 0; font-family: var(--et-serif); font-weight: 400; font-size: 32px; line-height: 1.15; color: var(--et-ink); text-wrap: balance; }
    .et-lede { margin: 8px 0 0; max-width: 62ch; font-size: 15px; line-height: 1.55; color: var(--et-ink-2); }
    .et-lede strong { color: var(--et-ink); font-weight: 600; }
    .et-mast-side { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; text-align: right; }
    .et-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; font-size: 12px; font-weight: 600; background: var(--et-tint); color: var(--et-ink); border: 1px solid var(--et-line); white-space: nowrap; }
    .et-chip i { color: var(--et-muted); }
    .et-clock { font-size: 12px; color: var(--et-muted); }

    @media (max-width: 767.98px) {
        .et-mast { grid-template-columns: auto 1fr; padding: 20px 18px; background-image:
            linear-gradient(to right, transparent 87px, var(--et-margin) 87px, var(--et-margin) 88px, transparent 88px),
            repeating-linear-gradient(to bottom, transparent 0, transparent 31px, var(--et-rule) 31px, var(--et-rule) 32px); }
        .et-date { width: 64px; }
        .et-date-day { font-size: 42px; }
        .et-greet { font-size: 24px; }
        .et-mast-side { grid-column: 1 / -1; flex-direction: row; justify-content: flex-start; margin-top: 14px; }
    }

    /* ── Sections and panels ───────────────────────────────────────────── */
    .et-section { margin: 0 0 28px; }
    .et-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin: 0 2px 12px; }
    .et-section-title { margin: 0; font-family: var(--et-serif); font-weight: 400; font-size: 22px; color: var(--et-ink); }
    .et-section-note { font-size: 13px; color: var(--et-muted); }

    .et-row { display: flex; flex-wrap: wrap; gap: 20px; align-items: stretch; }
    .et-row > * { flex: 1 1 360px; min-width: 0; }
    .et-row > .et-wide { flex-grow: 1.6; }

    .et-panel { background: var(--et-surface); border: 1px solid var(--et-line); border-radius: 12px; display: flex; flex-direction: column; }
    .et-panel-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 12px; padding: 16px 20px 12px; border-bottom: 1px solid var(--et-rule); }
    .et-panel-title { margin: 0; font-size: 15px; font-weight: 700; color: var(--et-ink); }
    .et-panel-link { font-size: 13px; font-weight: 600; color: var(--et-accent) !important; white-space: nowrap; }
    .et-panel-link:hover { text-decoration: underline !important; }
    .et-panel-body { padding: 16px 20px 18px; flex: 1; }
    .et-empty { display: flex; align-items: center; gap: 12px; padding: 18px 20px; color: var(--et-muted); font-size: 14px; }
    .et-empty i { font-size: 18px; color: var(--et-good); }

    /* ── Waiting on you ────────────────────────────────────────────────── */
    .et-todo { list-style: none; margin: 0; padding: 6px 0; }
    .et-todo a { display: grid; grid-template-columns: 52px 1fr auto; align-items: center; gap: 14px; padding: 11px 20px; color: inherit; transition: background-color .15s; }
    .et-todo a:hover { background: var(--et-tint); }
    .et-todo li + li a { border-top: 1px solid var(--et-rule); }
    .et-count { display: inline-flex; justify-content: center; min-width: 44px; padding: 5px 8px; border-radius: 8px; font-size: 16px; font-weight: 700; background: var(--et-accent-tint); color: var(--et-accent); }
    .et-count.warn { background: var(--et-warn-tint); color: var(--et-warn); }
    .et-count.bad { background: var(--et-bad-tint); color: var(--et-bad); }
    .et-todo-label { display: block; font-size: 14px; font-weight: 600; color: var(--et-ink); }
    .et-todo-note { display: block; margin-top: 1px; font-size: 13px; color: var(--et-muted); }
    .et-todo .fa-chevron-right { color: var(--et-faint); font-size: 12px; }

    /* ── Figures ───────────────────────────────────────────────────────── */
    .et-figures { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; }
    .et-figure { display: block; padding: 18px 20px; background: var(--et-surface); border: 1px solid var(--et-line); border-radius: 12px; color: inherit; transition: border-color .15s; }
    a.et-figure:hover { border-color: #c7d2e1; }
    .et-figure-label { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--et-muted); }
    .et-figure-value { margin: 10px 0 2px; font-size: 30px; font-weight: 700; line-height: 1.1; color: var(--et-ink); letter-spacing: -.5px; }
    .et-figure-value small { font-size: 15px; font-weight: 600; color: var(--et-muted); letter-spacing: 0; }
    .et-figure-sub { margin: 0; font-size: 13px; color: var(--et-muted); line-height: 1.5; }
    .et-figure-sub b { color: var(--et-ink-2); font-weight: 600; }
    .et-meter { display: flex; height: 8px; margin: 12px 0 8px; border-radius: 99px; overflow: hidden; background: var(--et-rule); gap: 2px; }
    .et-meter span { display: block; height: 100%; }
    .et-meter .good { background: var(--et-good); }
    .et-meter .bad { background: var(--et-bad); }
    .et-meter .accent { background: var(--et-accent); }
    .et-key { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 13px; color: var(--et-muted); }
    .et-key span::before { content: ''; display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 2px; background: currentColor; vertical-align: 0; }
    .et-key .good::before { background: var(--et-good); }
    .et-key .bad::before { background: var(--et-bad); }
    .et-key .accent::before { background: var(--et-accent); }
    .et-key .rest::before { background: var(--et-rule); border: 1px solid var(--et-line); }

    /* ── Where the students are ────────────────────────────────────────── */
    .et-tabs { display: inline-flex; gap: 2px; padding: 3px; border-radius: 9px; background: var(--et-tint); border: 1px solid var(--et-line); }
    .et-tab { border: 0; background: transparent; padding: 5px 12px; border-radius: 7px; font-size: 13px; font-weight: 600; color: var(--et-muted); cursor: pointer; }
    .et-tab[aria-selected="true"] { background: var(--et-surface); color: var(--et-ink); box-shadow: 0 1px 2px rgba(24, 43, 73, .12); }
    .et-bars { list-style: none; margin: 0; padding: 0; }
    .et-bars li { display: grid; grid-template-columns: minmax(0, 1fr) 56px; gap: 4px 12px; align-items: center; padding: 7px 0; }
    .et-bars li + li { border-top: 1px solid var(--et-rule); }
    .et-bar-name { font-size: 14px; color: var(--et-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .et-bar-count { grid-row: span 2; text-align: right; font-size: 15px; font-weight: 700; color: var(--et-ink); }
    .et-bar-track { height: 6px; border-radius: 99px; background: var(--et-rule); overflow: hidden; }
    .et-bar-fill { display: block; height: 100%; border-radius: 99px; background: var(--et-accent); min-width: 2px; }
    .et-more summary { margin-top: 8px; font-size: 13px; font-weight: 600; color: var(--et-accent); cursor: pointer; list-style: none; }
    .et-more summary::-webkit-details-marker { display: none; }
    .et-more[open] summary { margin-bottom: 4px; }

    /* ── The day at a glance ───────────────────────────────────────────── */
    .et-daybook { list-style: none; margin: 0; padding: 4px 0; }
    .et-daybook a, .et-daybook .et-line { display: grid; grid-template-columns: 22px 1fr auto; align-items: center; gap: 12px; padding: 9px 20px; color: inherit; }
    .et-daybook a:hover { background: var(--et-tint); }
    .et-daybook li + li > * { border-top: 1px solid var(--et-rule); }
    .et-daybook i { text-align: center; color: var(--et-muted); }
    .et-daybook .et-day-label { font-size: 14px; color: var(--et-ink-2); }
    .et-daybook .et-day-count { font-size: 15px; font-weight: 700; color: var(--et-ink); }
    .et-daybook .is-zero .et-day-count { color: var(--et-faint); font-weight: 600; }
    .et-subhead { padding: 12px 20px 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--et-faint); }

    /* ── Directory of screens ──────────────────────────────────────────── */
    .et-directory { columns: 4 220px; column-gap: 20px; }
    .et-dir-group { break-inside: avoid; margin: 0 0 20px; padding: 14px 16px; background: var(--et-surface); border: 1px solid var(--et-line); border-radius: 12px; }
    .et-dir-title { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--et-muted); }
    .et-dir-title i { width: 16px; text-align: center; }
    .et-dir-links { list-style: none; margin: 0; padding: 0; }
    .et-dir-links a { display: block; padding: 4px 0; font-size: 14px; color: var(--et-ink) !important; }
    .et-dir-links a:hover { color: var(--et-accent) !important; text-decoration: underline !important; }

    /* ── The lecturer's desk ───────────────────────────────────────────── */
    .et-timeline { list-style: none; margin: 0; padding: 8px 0; }
    .et-class { position: relative; display: grid; grid-template-columns: 92px 18px 1fr; gap: 0 14px; padding: 10px 20px; }
    .et-class-time { text-align: right; line-height: 1.3; }
    .et-class-time b { display: block; font-size: 15px; color: var(--et-ink); }
    .et-class-time span { font-size: 12px; color: var(--et-muted); }
    .et-class-node { position: relative; }
    .et-class-node::before { content: ''; position: absolute; left: 8px; top: 0; bottom: -20px; width: 2px; background: var(--et-rule); }
    .et-class:last-child .et-class-node::before { display: none; }
    .et-class-node::after { content: ''; position: absolute; left: 3px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--et-surface); border: 2px solid var(--et-faint); }
    .et-class.is-now .et-class-node::after { background: var(--et-good); border-color: var(--et-good); box-shadow: 0 0 0 4px var(--et-good-tint); }
    .et-class.is-next .et-class-node::after { border-color: var(--et-accent); background: var(--et-accent-tint); }
    .et-class.is-done { opacity: .62; }
    .et-class-title { margin: 0; font-size: 15px; font-weight: 600; color: var(--et-ink); }
    .et-code { display: inline-block; margin-right: 6px; padding: 1px 7px; border-radius: 5px; font-size: 12px; font-weight: 700; background: var(--et-tint); border: 1px solid var(--et-line); color: var(--et-ink); }
    .et-class-meta { margin: 3px 0 0; font-size: 13px; color: var(--et-muted); }
    .et-state { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; vertical-align: 2px; }
    .et-state.now { background: var(--et-good-tint); color: var(--et-good); }
    .et-state.next { background: var(--et-accent-tint); color: var(--et-accent); }
    .et-state.done { background: var(--et-tint); color: var(--et-muted); }

    .et-kiosk { padding: 18px 20px; flex: 1; }
    .et-kiosk-panel.is-live { background: var(--et-good-tint); border-color: #b9dcc7; }
    .et-kiosk-title { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 16px; font-weight: 700; color: var(--et-ink); }
    .et-kiosk-panel.is-live .et-kiosk-title { color: var(--et-good); }
    .et-live-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--et-good); box-shadow: 0 0 0 4px rgba(15, 122, 61, .18); }
    .et-kiosk p { margin: 8px 0 14px; font-size: 14px; line-height: 1.5; color: var(--et-ink-2); }
    .et-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; background: var(--et-ink); color: #fff !important; border: 0; }
    .et-btn:hover { background: #233a63; }
    .et-btn.good { background: var(--et-good); }
    .et-btn.good:hover { background: #0c6532; }
    .et-btn-quiet { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--et-accent) !important; background: var(--et-accent-tint); }
    .et-btn-quiet:hover { background: #dbe7f6; }
    .et-trio { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--et-rule); }
    .et-trio div { padding: 14px 10px; text-align: center; }
    .et-trio div + div { border-left: 1px solid var(--et-rule); }
    .et-trio b { display: block; font-size: 22px; color: var(--et-ink); }
    .et-trio span { font-size: 12px; color: var(--et-muted); }

    .et-courses { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
    .et-course { display: flex; flex-direction: column; padding: 16px; background: var(--et-surface); border: 1px solid var(--et-line); border-radius: 12px; }
    .et-course-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .et-course-title { margin: 6px 0 0; font-size: 14px; line-height: 1.4; color: var(--et-ink-2); }
    .et-course-students { flex-shrink: 0; font-size: 13px; color: var(--et-muted); white-space: nowrap; }
    .et-course-groups { margin: 10px 0 12px; font-size: 13px; color: var(--et-muted); }
    .et-course-groups ul { list-style: none; margin: 6px 0 0; padding: 0; }
    .et-course-groups li { padding: 5px 0; border-top: 1px solid var(--et-rule); }
    .et-course-groups a { color: var(--et-accent) !important; font-weight: 600; }
    .et-course .et-btn { margin-top: auto; justify-content: center; }
    .et-toggle { color: var(--et-accent) !important; font-weight: 600; cursor: pointer; }

    .et-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border: 1px solid var(--et-line); border-radius: 12px; overflow: hidden; background: var(--et-surface); }
    .et-weekday { min-height: 120px; border-left: 1px solid var(--et-rule); }
    .et-weekday:first-child { border-left: 0; }
    .et-weekday-name { padding: 9px 10px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--et-muted); border-bottom: 1px solid var(--et-rule); background: var(--et-tint); }
    .et-weekday.is-today .et-weekday-name { background: var(--et-ink); color: #fff; }
    .et-weekday.is-today { background: #fbfcfe; }
    .et-slot { margin: 8px; padding: 7px 8px; border-radius: 8px; background: var(--et-tint); border-left: 3px solid var(--et-accent); font-size: 12px; line-height: 1.35; }
    .et-slot b { display: block; color: var(--et-ink); }
    .et-slot span { color: var(--et-muted); }
    .et-slot-none { margin: 10px; font-size: 12px; color: var(--et-faint); }
    @media (max-width: 991.98px) {
        .et-week { grid-template-columns: 1fr; }
        .et-weekday { min-height: 0; border-left: 0; border-top: 1px solid var(--et-rule); }
        .et-weekday:first-child { border-top: 0; }
        .et-weekday.is-empty { display: none; }
    }
    @media (max-width: 575.98px) {
        .et-class { grid-template-columns: 70px 18px 1fr; padding: 10px 14px; gap: 0 10px; }
        .et-todo a { grid-template-columns: 46px 1fr auto; padding: 11px 14px; }
        .et-panel-head, .et-panel-body { padding-left: 14px; padding-right: 14px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .et-dash * { transition: none !important; }
    }

    /* ── Messages ──────────────────────────────────────────────────────── */
    .et-msgs { list-style: none; margin: 0; padding: 4px 0; }
    .et-msgs a { display: grid; grid-template-columns: 36px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 20px; color: inherit; }
    .et-msgs a:hover { background: var(--et-tint); }
    .et-msgs li + li a { border-top: 1px solid var(--et-rule); }
    .et-avatar { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; background: var(--et-accent-tint); color: var(--et-accent); }
    .et-avatar.student { background: var(--et-good-tint); color: var(--et-good); }
    .et-msg-who { display: block; font-size: 14px; color: var(--et-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .et-msg-line { display: block; font-size: 13px; color: var(--et-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .et-msg-line b { color: var(--et-ink-2); font-weight: 600; }
    .et-msgs .is-unread .et-msg-who, .et-msgs .is-unread .et-msg-line b { font-weight: 700; color: var(--et-ink); }
    .et-msgs .is-unread .et-msg-who::before { content: ''; display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: var(--et-accent); vertical-align: 1px; }
    .et-msg-when { font-size: 12px; color: var(--et-faint); white-space: nowrap; text-align: right; }
    .et-panel-foot { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; padding: 12px 20px; border-top: 1px solid var(--et-rule); }

    /* ── Notices on the page (registration, progression) ──────────────── */
    .et-alert { display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: center; padding: 16px 20px; margin: 0 0 20px; border-radius: 12px; border: 1px solid var(--et-line); background: var(--et-surface); border-left-width: 4px; }
    .et-alert.warn { border-left-color: var(--et-margin); background: var(--et-warn-tint); }
    .et-alert.bad { border-left-color: var(--et-bad); background: var(--et-bad-tint); }
    .et-alert.info { border-left-color: var(--et-accent); background: var(--et-accent-tint); }
    .et-alert > i { font-size: 22px; color: var(--et-warn); }
    .et-alert.bad > i { color: var(--et-bad); }
    .et-alert.info > i { color: var(--et-accent); }
    .et-alert h3 { margin: 0 0 2px; font-size: 15px; font-weight: 700; color: var(--et-ink); }
    .et-alert p { margin: 0; font-size: 14px; color: var(--et-ink-2); }
    .et-alert .et-when { margin-top: 4px; font-weight: 600; }
    @media (max-width: 575.98px) { .et-alert { grid-template-columns: auto 1fr; } .et-alert > a { grid-column: 1 / -1; justify-self: start; } }

    /* ── Tables (course overview) ──────────────────────────────────────── */
    .et-table-wrap { overflow-x: auto; }
    .et-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .et-table th { padding: 8px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--et-muted); text-align: left; border-bottom: 1px solid var(--et-line); white-space: nowrap; }
    .et-table td { padding: 9px 12px; border-bottom: 1px solid var(--et-rule); color: var(--et-ink-2); vertical-align: top; }
    .et-table td.num, .et-table th.num { text-align: right; }
    .et-pill { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 12px; font-weight: 700; }
    .et-pill.good { background: var(--et-good-tint); color: var(--et-good); }
    .et-pill.bad { background: var(--et-bad-tint); color: var(--et-bad); }
    .et-pill.warn { background: var(--et-warn-tint); color: var(--et-warn); }
    .et-pill.muted { background: var(--et-tint); color: var(--et-muted); border: 1px solid var(--et-line); }
    .et-semester { margin: 0; border-top: 1px solid var(--et-rule); }
    .et-semester summary { display: flex; justify-content: space-between; gap: 12px; padding: 12px 20px; cursor: pointer; list-style: none; font-weight: 600; color: var(--et-ink); }
    .et-semester summary::-webkit-details-marker { display: none; }
    .et-semester summary span { font-weight: 400; color: var(--et-muted); font-size: 13px; }
    .et-semester[open] summary { background: var(--et-tint); }
</style>
