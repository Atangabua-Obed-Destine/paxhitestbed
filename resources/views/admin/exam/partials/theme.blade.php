{{--
  The marking screen's visual language.

  Income, expense and fees each carry an accent that says which money this is.
  Marking does not get one, and that is a finding rather than a shortcut: a
  fourth hue was tried against the three, and the best candidate (#a21caf)
  still landed at CVD ΔE 6.6 — inside the 6–8 band the palette validator only
  allows with secondary encoding — while every other candidate failed outright.

  So this screen reads in ink and greys, and spends colour on STATE instead:
  open, locked, out of range. Which is what a marks screen should be spending
  it on. Those three are already measured, in common/inc/ledger_tokens:

    good  #0f7a3d  5.42:1   saved / in range
    warn  #9a6a06  4.73:1   locked
    bad   #b3261e  6.54:1   out of range

  Scoped to .exm so none of it reaches the seven other views that include
  common/inc/subject_search_filter — fees-master, the subject-marking screens,
  report/subject and the rest. That containment is asserted.
--}}

@push('css')
<style>
@include('common.inc.ledger_tokens', ['scope' => '.exm', 'accent' => 'income'])

    /* The accent inherited above is deliberately never used for identity on
       this screen; only --ink, --body, --muted, --rule and the three status
       values are. It is here so the token block stays one shape everywhere. */

    .exm-panel{
        background:var(--surface);border:1px solid var(--rule);
        border-radius:8px;margin-bottom:.75rem;
    }

    .exm-label{
        font-size:.65rem;font-weight:800;letter-spacing:.09em;
        text-transform:uppercase;color:var(--muted);
    }

    .exm-num{
        font-variant-numeric:tabular-nums;
        font-feature-settings:"tnum" 1,"lnum" 1;white-space:nowrap;
    }

    /* ---- the band: what is being marked, and how far along ------------- */

    .exm-band{
        background:var(--surface);border:1px solid var(--rule);
        border-left:3px solid var(--ink);border-radius:8px;
        padding:1.1rem 1.3rem;margin-bottom:.75rem;
    }
    .exm-band-top{
        display:flex;align-items:flex-end;justify-content:space-between;
        gap:1.5rem;flex-wrap:wrap;
    }
    .exm-band h4{
        margin:.15rem 0 0;font-size:1.05rem;font-weight:700;color:var(--ink);
        letter-spacing:-.01em;
    }
    .exm-band-sub{font-size:.78rem;color:var(--muted);margin-top:.15rem}

    .exm-progress{text-align:right}
    .exm-progress .v{
        font-size:1.5rem;font-weight:800;color:var(--ink);line-height:1;
        letter-spacing:-.02em;
    }
    .exm-progress .c{font-size:.8rem;font-weight:600;color:var(--muted);margin-left:.25rem}

    .exm-bar{
        height:8px;border-radius:4px;background:var(--rule-soft);
        overflow:hidden;margin-top:1rem;
    }
    .exm-bar span{display:block;height:100%;background:var(--good)}

    /* So far: count, average, lowest, highest, how many at zero. A column
       typed one row out of step shows up here before it is published. */
    .exm-stats{
        display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.9rem;
        padding-top:.75rem;border-top:1px solid var(--rule-soft);
    }
    .exm-stat{
        background:var(--ground);border:1px solid var(--rule);border-radius:20px;
        padding:.2rem .7rem;font-size:.78rem;color:var(--body);
    }
    .exm-stat b{color:var(--ink);margin-right:.3rem}
    .exm-stat.is-bad{background:#fdf0ee;border-color:#f0cdc9;color:#8f2b22}
    .exm-stat.is-bad b{color:#8f2b22}

    /* ---- filters -------------------------------------------------------
       The markup is common/inc/subject_search_filter, shared with six other
       views. It is untouched; these rules reach it only because it sits
       inside .exm here. */
    .exm-filters{padding:.9rem 1.1rem}
    .exm-filters label{
        font-size:.65rem;font-weight:800;letter-spacing:.09em;
        text-transform:uppercase;color:var(--muted);margin-bottom:.25rem;
    }
    .exm-filters .form-control,.exm-filters .select2-selection{
        border-color:var(--rule);border-radius:6px;font-size:.86rem;color:var(--ink);
    }
    .exm-filters .form-control:focus{
        border-color:var(--ink);box-shadow:0 0 0 3px rgba(21,35,59,.08);
    }

    .exm-actions{
        display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;
        padding:.85rem 1.1rem;border-top:1px solid var(--rule-soft);
    }
    .exm-actions .exm-right{margin-left:auto;display:flex;align-items:center;gap:.6rem}

    .exm-btn{
        display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .9rem;
        border-radius:6px;font-size:.84rem;font-weight:600;text-decoration:none;
        border:1px solid var(--rule);background:var(--surface);color:var(--body);
    }
    .exm-btn:hover{background:var(--ground);color:var(--ink)}
    .exm-btn-main{background:var(--ink);border-color:var(--ink);color:#fff}
    .exm-btn-main:hover{background:#0e1a2e;border-color:#0e1a2e;color:#fff}
    .exm-btn-main[disabled]{opacity:.5;cursor:not-allowed}

    /* ---- the table ------------------------------------------------------ */

    .exm-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}

    /* Every colour carries !important for the reason the account screens
       document: the theme forces its own inside .table —
         .table td,.table th { color:#888 !important }
         .table thead th     { background:#3ea1e4; color:#fff !important }
       which is what rendered those headers white on white. */
    table.exm-table{border-collapse:collapse}
    table.exm-table thead th{
        font-size:.64rem !important;font-weight:800;letter-spacing:.09em;
        text-transform:uppercase;color:var(--muted) !important;
        border-bottom:1px solid var(--rule) !important;border-top:0 !important;
        padding:.55rem .7rem !important;background:transparent !important;
        white-space:nowrap;
    }
    table.exm-table tbody td{
        border-top:1px solid var(--rule-soft) !important;
        padding:.4rem .7rem !important;vertical-align:middle;
        font-size:.84rem;color:var(--body) !important;background:transparent !important;
    }
    table.exm-table tbody tr:hover td{background:var(--ground) !important}
    table.exm-table td.exm-who{color:var(--ink) !important;font-weight:600}
    table.exm-table td.exm-meta{color:var(--muted) !important;font-size:.76rem}
    table.exm-table .n{text-align:right}

    /* The row being typed into. A column of 88 is easy to lose your place in. */
    table.exm-table tbody tr:focus-within td{background:#f2f6fc !important}

    /* The mark box. Wide enough for 59.5, no wider — a box that fits a
       phone number invites one. */
    .exm-mark{
        width:88px;text-align:right;font-variant-numeric:tabular-nums;
        border:1px solid var(--rule);border-radius:6px;padding:.3rem .5rem;
        font-size:.9rem;font-weight:600;color:var(--ink);background:#fff;
    }
    .exm-mark:focus{
        outline:none;border-color:var(--ink);box-shadow:0 0 0 3px rgba(21,35,59,.08);
    }
    .exm-mark:disabled{background:var(--ground);color:var(--muted);cursor:not-allowed}
    .exm-mark.is-invalid{border-color:var(--bad);box-shadow:0 0 0 3px rgba(179,38,30,.1)}

    .exm-note{
        width:170px;border:1px solid var(--rule);border-radius:6px;
        padding:.3rem .5rem;font-size:.82rem;color:var(--ink);background:#fff;
    }
    .exm-note:disabled{background:var(--ground);color:var(--muted)}

    .exm-feedback{
        display:block;color:var(--bad);font-size:.7rem;margin-top:.2rem;
        max-width:200px;
    }

    /* State wears a word as well as a colour. */
    .exm-tag{
        display:inline-block;padding:.08rem .45rem;border-radius:4px;
        font-size:.7rem;font-weight:600;white-space:nowrap;
        background:var(--ground);color:var(--body);border:1px solid var(--rule);
    }
    .exm-tag-locked{background:#fdf8ec;color:#7a5406;border-color:#e8d9a8}
    .exm-tag-open{background:#eaf6ef;color:#0b6230;border-color:#bfe2cd}

    .exm-count-bad{color:var(--bad);font-size:.82rem;font-weight:600}
    .exm-count-ok{color:var(--muted);font-size:.82rem}

    .exm-empty{text-align:center;padding:3.5rem 1rem}
    .exm-empty i{font-size:1.8rem;color:var(--faint)}
    .exm-empty p{margin:.9rem 0 .25rem;font-weight:600;color:var(--body)}
    .exm-empty small{color:var(--muted);font-size:.8rem}

    @media (max-width:767px){
        .exm-progress{text-align:left}
        .exm-progress .v{font-size:1.25rem}
    }
</style>
@endpush
