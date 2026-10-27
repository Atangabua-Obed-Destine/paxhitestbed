{{--
  The fees screens' visual language.

  Same ledger-grade discipline as the account screens — hairlines instead of
  shadows, money in tabular figures, one accent used as a mark — but its own
  components, because a sixteen-column report is not an eight-column ledger
  and parameterising every selector to share them would make both unreadable.
  The measured part, the tokens, IS shared: see common/inc/ledger_tokens.

  Scoped to .fees so none of it reaches fees-master, fee-assignments-history
  or report/fees, which all include the same filter partial this screen does.
  That containment is asserted by scripts/fees_report_test.php.
--}}

@push('css')
<style>
@include('common.inc.ledger_tokens', ['scope' => '.fees', 'accent' => 'fees'])

    /* ---- primitives ---------------------------------------------------- */

    .fees-panel {
        background: var(--surface);
        border: 1px solid var(--rule);
        border-radius: 8px;
        margin-bottom: .75rem;
    }

    .fees-label {
        font-size: .65rem; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted);
    }

    .fees-num {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1, "lnum" 1;
        white-space: nowrap;
    }

    /* ---- the band ------------------------------------------------------- */

    .fees-band {
        background: var(--surface);
        border: 1px solid var(--rule);
        border-left: 3px solid var(--flow);
        border-radius: 8px;
        padding: 1.15rem 1.35rem;
        margin-bottom: .75rem;
    }

    /* Billed, collected, outstanding — the three figures a fees report is run
       to get. Equal weight, because the question is the relationship between
       them, not any one of them. */
    .fees-figures {
        display: flex; flex-wrap: wrap; gap: 1.5rem 2.5rem;
    }
    .fees-figure .v {
        font-size: 1.6rem; font-weight: 800; color: var(--ink);
        line-height: 1; letter-spacing: -.02em; margin-top: .2rem;
    }
    .fees-figure .c { font-size: .8rem; font-weight: 600; color: var(--muted); margin-left: .2rem; }
    .fees-figure.is-out .v { color: var(--warn); }
    .fees-figure.is-over .v { color: var(--flow-ink); }

    /* The proportion, shown as a proportion. A bar answers "how much of what
       we billed has come in" without anyone dividing two numbers. */
    .fees-bar-wrap { margin-top: 1.15rem; }
    .fees-bar {
        height: 8px; border-radius: 4px; background: var(--rule-soft);
        overflow: hidden;
    }
    .fees-bar span { display: block; height: 100%; background: var(--flow); }
    .fees-bar-note {
        margin-top: .45rem; font-size: .78rem; color: var(--body);
    }
    .fees-bar-note b { color: var(--ink); }

    .fees-chips {
        display: flex; flex-wrap: wrap; gap: .35rem;
        margin-top: 1rem; padding-top: .8rem; border-top: 1px solid var(--rule-soft);
    }
    /* Each count wears its word as well as its colour, so none of them depends
       on telling one tint from another. */
    .fees-chip {
        background: var(--ground); border: 1px solid var(--rule); border-radius: 20px;
        padding: .2rem .7rem; font-size: .78rem; color: var(--body);
    }
    .fees-chip b { color: var(--ink); margin-right: .3rem; }
    .fees-chip.is-warn { border-color: #e8d9a8; background: #fdf8ec; }
    .fees-chip.is-bad  { border-color: #f0cdc9; background: #fdf0ee; }
    .fees-chip.is-flow { border-color: transparent; background: var(--flow-wash); color: var(--flow-ink); }

    .fees-empty { text-align: center; padding: 3.5rem 1rem; }
    .fees-empty i { font-size: 1.8rem; color: var(--faint); }
    .fees-empty p { margin: .9rem 0 .25rem; font-weight: 600; color: var(--body); }
    .fees-empty small { color: var(--muted); font-size: .8rem; }

    /* ---- filters -------------------------------------------------------- */

    /* The filter markup is common/inc/fees_search_filter, shared with
       fees-master and two other modules. It is not touched; these rules reach
       it only because it sits inside .fees on these two screens. */
    .fees-filters { padding: .9rem 1.1rem; }
    .fees-filters label {
        font-size: .65rem; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted); margin-bottom: .25rem;
    }
    .fees-filters .form-control, .fees-filters .select2-selection {
        border-color: var(--rule); border-radius: 6px;
        font-size: .86rem; color: var(--ink);
    }
    .fees-filters .form-control:focus {
        border-color: var(--flow); box-shadow: 0 0 0 3px var(--flow-wash);
    }

    .fees-bar-actions {
        display: flex; align-items: center; gap: .35rem;
        flex-wrap: wrap; margin-bottom: .75rem;
    }
    .fees-btn {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .35rem .8rem; border-radius: 6px; font-size: .8rem;
        font-weight: 600; text-decoration: none; border: 1px solid var(--rule);
        background: var(--surface); color: var(--body);
    }
    .fees-btn:hover { background: var(--ground); color: var(--ink); }
    .fees-btn-main { background: var(--ink); border-color: var(--ink); color: #fff; }
    .fees-btn-main:hover { background: #0e1a2e; border-color: #0e1a2e; color: #fff; }

    /* ---- the table ------------------------------------------------------ */

    /* Sixteen columns will not fit a laptop, so the table scrolls inside its
       own panel rather than taking the page sideways with it. */
    .fees-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    /* Every colour carries !important for the reason the account screens
       document: the theme forces its own inside .table —
         .table td, .table th { color: #888 !important }
         .table thead th      { background: #3ea1e4; color: #fff !important }
       Declaring a background with !important and a colour without is what
       rendered those headers white on white. */
    table.fees-table { border-collapse: collapse; }
    table.fees-table thead th {
        font-size: .64rem !important; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted) !important;
        border-bottom: 1px solid var(--rule) !important;
        border-top: 0 !important; padding: .6rem .7rem !important;
        background: transparent !important; white-space: nowrap;
    }
    table.fees-table tbody td {
        border-top: 1px solid var(--rule-soft) !important;
        padding: .55rem .7rem !important; vertical-align: middle;
        font-size: .84rem; color: var(--body) !important;
        background: transparent !important;
    }
    table.fees-table tbody tr:hover td { background: var(--ground) !important; }
    table.fees-table tfoot th {
        border-top: 2px solid var(--rule) !important;
        padding: .7rem !important; font-size: .8rem;
        color: var(--ink) !important; background: transparent !important;
    }

    table.fees-table .n { text-align: right; }
    table.fees-table td.n { color: var(--ink) !important; font-weight: 600; }
    table.fees-table tfoot .n { font-weight: 800; }
    table.fees-table td.is-due { color: var(--warn) !important; }

    table.fees-table td.fees-who { color: var(--ink) !important; font-weight: 600; }
    table.fees-table td.fees-when { color: var(--ink) !important; white-space: nowrap; }
    table.fees-table td.fees-meta { color: var(--muted) !important; font-size: .76rem; }

    .fees-tag {
        display: inline-block; padding: .08rem .45rem; border-radius: 4px;
        font-size: .72rem; font-weight: 600; white-space: nowrap;
        background: var(--ground); color: var(--body); border: 1px solid var(--rule);
    }
    .fees-tag-flow { background: var(--flow-wash); color: var(--flow-ink); border-color: transparent; }
    .fees-tag-warn { background: #fdf8ec; color: #7a5406; border-color: #e8d9a8; }
    .fees-tag-bad  { background: #fdf0ee; color: #8f2b22; border-color: #f0cdc9; }
    .fees-tag-off  { background: var(--ground); color: var(--muted); }

    .fees-acts { white-space: nowrap; text-align: right; }
    .fees-acts .btn { padding: .2rem .45rem; margin-left: .15rem; }

    @media (max-width: 767px) {
        .fees-figures { gap: 1rem 1.5rem; }
        .fees-figure .v { font-size: 1.3rem; }
    }
</style>
@endpush
