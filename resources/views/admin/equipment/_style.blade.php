{{-- The lending desks' look: the day-book tokens shared with the dashboards. --}}
<style>
    .eq {
        --eq-ink: #182b49; --eq-ink-2: #334155; --eq-muted: #5f6f86; --eq-line: #e3e7ee; --eq-rule: #eef1f6;
        --eq-surface: #fff; --eq-tint: #f6f8fb; --eq-accent: #1f5fa8; --eq-accent-tint: #e8f0fa;
        --eq-good: #0f7a3d; --eq-good-tint: #e6f4ec; --eq-warn: #9a5b00; --eq-warn-tint: #fdf3e1;
        --eq-bad: #b3261e; --eq-bad-tint: #fbe9e7; --eq-margin: #e8b54a;
        --eq-serif: ui-serif, Georgia, Cambria, "Times New Roman", serif;
        color: var(--eq-ink-2); font-variant-numeric: tabular-nums;
    }
    .eq *, .eq *::before, .eq *::after { box-sizing: border-box; }
    .eq a { color: var(--eq-accent); }
    .eq :focus-visible { outline: 2px solid var(--eq-accent); outline-offset: 2px; }
    .eq [hidden] { display: none !important; }

    .eq-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 24px; margin-bottom: 18px; }
    .eq-title { margin: 0; font-family: var(--eq-serif); font-weight: 400; font-size: 28px; line-height: 1.15; color: var(--eq-ink); }
    .eq-sub { margin: 4px 0 0; color: var(--eq-muted); font-size: 14px; }
    .eq-head-side { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .eq-head-side label { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-size: 13px; font-weight: 600; color: var(--eq-muted); }

    .eq-card { background: var(--eq-surface); border: 1px solid var(--eq-line); border-radius: 12px; padding: 16px; }
    .eq-desk { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (min-width: 992px) { .eq-desk { grid-template-columns: 320px minmax(0, 1fr); } }

    .eq-reader { margin-bottom: 10px; border-radius: 10px; overflow: hidden; background: #22324f; min-height: 200px; }
    .eq-reader video { width: 100% !important; display: block; }
    .eq-lbl { display: block; margin: 12px 0 5px; font-size: 12px; font-weight: 600; color: var(--eq-muted); }
    .eq-scanform { display: flex; gap: 6px; }
    .eq-input, .eq-select, .eq-textarea { width: 100%; min-height: 42px; padding: 8px 11px; border: 1px solid #c7d2e1; border-radius: 8px; background: #fff; color: var(--eq-ink); font-size: 16px; }
    .eq-input.scan { border: 2px solid var(--eq-accent); font-size: 17px; letter-spacing: .02em; }
    .eq-textarea { min-height: 70px; }
    .eq-hint { margin: 8px 0 0; font-size: 12.5px; color: var(--eq-muted); }

    .eq-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 8px 16px; border: 1px solid transparent; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none !important; white-space: nowrap; }
    .eq-btn:disabled { opacity: .45; cursor: not-allowed; }
    .eq-btn-primary { background: var(--eq-accent); color: #fff !important; }
    .eq-btn-good { background: var(--eq-good); color: #fff !important; }
    .eq-btn-ghost { background: #fff; border-color: #c7d2e1; color: var(--eq-ink) !important; }
    .eq-btn-bad { background: #fff; border-color: #e8b4ae; color: var(--eq-bad) !important; }
    .eq-btn-wide { width: 100%; min-height: 48px; font-size: 16px; }
    .eq-btn-sm { min-height: 34px; padding: 5px 11px; font-size: 13px; }

    .eq-flash { margin-top: 12px; padding: 10px 12px; border-radius: 8px; font-size: 14px; display: none; }
    .eq-flash.ok { display: block; background: var(--eq-good-tint); color: var(--eq-good); }
    .eq-flash.no { display: block; background: var(--eq-bad-tint); color: var(--eq-bad); }

    .eq-slip { display: grid; gap: 14px; }
    .eq-ent { display: grid; grid-template-columns: 52px minmax(0, 1fr); gap: 12px; align-items: start; padding-bottom: 14px; border-bottom: 1px solid var(--eq-rule); }
    .eq-ent:last-child { border-bottom: 0; padding-bottom: 0; }
    .eq-av { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; overflow: hidden; background: var(--eq-accent); color: #fff; font-weight: 700; font-size: 17px; }
    .eq-av img { width: 100%; height: 100%; object-fit: cover; }
    .eq-av.it { border-radius: 12px; background: var(--eq-ink-2); font-size: 14px; }
    .eq-av.book { border-radius: 8px; background: #7a4f1d; }
    .eq-av.wait { background: var(--eq-tint); color: var(--eq-muted); border: 1.5px dashed #c7d2e1; }
    .eq-name { display: block; font-size: 17px; font-weight: 700; color: var(--eq-ink); line-height: 1.25; }
    .eq-meta { display: block; margin-top: 2px; font-size: 13.5px; color: var(--eq-muted); }
    .eq-pills { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 7px; }
    .eq-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 700; white-space: nowrap; background: var(--eq-tint); color: var(--eq-ink-2); }
    .eq-pill.good { background: var(--eq-good-tint); color: var(--eq-good); }
    .eq-pill.warn { background: var(--eq-warn-tint); color: var(--eq-warn); }
    .eq-pill.bad { background: var(--eq-bad-tint); color: var(--eq-bad); }
    .eq-pill.acc { background: var(--eq-accent-tint); color: var(--eq-accent); }
    .eq-pill.muted { background: var(--eq-tint); color: var(--eq-muted); }
    .eq-stop { margin-top: 8px; padding: 9px 11px; border-radius: 8px; background: var(--eq-bad-tint); color: var(--eq-bad); font-size: 13.5px; }
    .eq-info { padding: 9px 11px; border-radius: 8px; background: var(--eq-accent-tint); color: #233a63; font-size: 13.5px; }
    .eq-holding { margin: 6px 0 0; padding: 0; list-style: none; font-size: 13.5px; }
    .eq-holding li { padding: 2px 0; }

    .eq-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: 6px; }
    @media (min-width: 576px) { .eq-row { grid-template-columns: 96px minmax(0, 1fr); align-items: center; } }
    .eq-row > .k { font-size: 12.5px; font-weight: 600; color: var(--eq-muted); }
    .eq-seg { display: flex; flex-wrap: wrap; gap: 6px; }
    .eq-seg button { min-height: 38px; padding: 6px 12px; border: 1px solid #c7d2e1; border-radius: 8px; background: #fff; color: var(--eq-ink); font-size: 14px; cursor: pointer; }
    .eq-seg button[aria-pressed="true"] { background: var(--eq-accent); border-color: var(--eq-accent); color: #fff; font-weight: 600; }
    .eq-seg.warn button[aria-pressed="true"] { background: var(--eq-warn); border-color: var(--eq-warn); }
    .eq-ticks { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 6px 14px; }
    .eq-ticks label { display: flex; align-items: center; gap: 8px; margin: 0; min-height: 34px; font-size: 14px; color: var(--eq-ink); cursor: pointer; }
    .eq-ticks input { width: 18px; height: 18px; }
    .eq-empty { padding: 26px 10px; text-align: center; color: var(--eq-muted); font-size: 15px; }

    .eq-sum { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .eq-sum span { padding: 7px 14px; border-radius: 999px; background: #fff; border: 1px solid var(--eq-line); font-size: 14px; }
    .eq-sum b { color: var(--eq-ink); font-size: 16px; margin-right: 3px; }
    .eq-sum .late { border-color: #e8b4ae; background: var(--eq-bad-tint); color: var(--eq-bad); }
    .eq-sum .late b { color: var(--eq-bad); }
    .eq-table-wrap { overflow-x: auto; }
    .eq-table { width: 100%; border-collapse: collapse; background: #fff; font-size: 14px; }
    .eq-table th { padding: 9px 10px; text-align: left; background: var(--eq-tint); color: var(--eq-muted); font-size: 12.5px; font-weight: 600; border-bottom: 1px solid var(--eq-line); white-space: nowrap; }
    .eq-table td { padding: 10px; border-bottom: 1px solid var(--eq-rule); vertical-align: top; }
    .eq-table tr.late td { background: #fffafa; }
    .eq-table .nw { white-space: nowrap; }
    .eq-table small { display: block; color: var(--eq-muted); font-size: 12.5px; }
    .eq-section { margin-top: 26px; }
    .eq-section h5 { margin: 0 0 10px; font-family: var(--eq-serif); font-weight: 400; font-size: 21px; color: var(--eq-ink); }
    .eq-inline { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .eq-inline .eq-input { min-height: 34px; font-size: 14px; width: auto; flex: 1 1 160px; }
    details.eq-act summary { cursor: pointer; color: var(--eq-accent); font-weight: 600; font-size: 13.5px; list-style: none; }
    details.eq-act summary::-webkit-details-marker { display: none; }
    details.eq-act[open] summary { margin-bottom: 6px; }
    .eq-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
    .eq-filters .eq-input, .eq-filters .eq-select { width: auto; min-height: 38px; font-size: 14px; }
    .eq-form { display: grid; gap: 14px; max-width: 760px; }
    .eq-form label.t { display: block; margin: 0 0 5px; font-size: 13px; font-weight: 600; color: var(--eq-ink); }
    .eq-form .help { display: block; margin-top: 4px; font-size: 12.5px; color: var(--eq-muted); }
    .eq-form .err { display: block; margin-top: 4px; font-size: 13px; color: var(--eq-bad); }
    .eq-check { display: flex; align-items: center; gap: 9px; font-size: 14.5px; color: var(--eq-ink); }
    .eq-check input { width: 18px; height: 18px; }
    .eq-grid2 { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
    @media (min-width: 768px) { .eq-grid2 { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
</style>
