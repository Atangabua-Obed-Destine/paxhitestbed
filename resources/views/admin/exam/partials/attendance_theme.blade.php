{{--
  What this screen adds to the marking screen's visual language.

  Attendance and marking are two halves of one job, done by the same person in
  the same sitting, so this screen reads in the same ink and greys and spends
  colour on STATE the same way — present, absent, eligible, not eligible,
  bypassed, locked. partials/theme carries the tokens and the components both
  screens share; only what is new here lives below.

  Three rules in here exist because the page's own JavaScript depends on them,
  and getting them wrong is silent:

    * the row backgrounds. theme sets `tbody td { background:transparent }`
      so the admin template cannot force its own grey, and the bypass handler
      sets a colour on the ROW (`row.css('background-color', …)`). Transparent
      cells let that show through, which is why the rule is `transparent` and
      not a colour. The two server-rendered row states are restated here
      because the template's own .table-danger is outranked by that rule.

    * `.c-bypass` must be followed by its `<label>`, and the label must hold a
      `.badge` — the handler reads `$(this).next('label').find('.badge')`.
      Styling the badge is fine; moving it out of the label is not.

    * the ineligibility note stays `<small class="d-block">`, because the same
      handler finds it by that selector and appends to it.

  Scoped to .exm, like everything in theme, so none of it reaches the seven
  other views that include common/inc/subject_search_filter.
--}}

@push('css')
<style>
    /* ---- the course list ------------------------------------------------
       One element per course, laid out either as a card or as a row. It used
       to be two complete copies of all 283 courses — a card grid and a table
       — which is 96% of this page's 4.7 MB, and the button that would have
       swapped them was bound to a class no element carried, so half of it
       could never be shown at all. One copy, two layouts, and the button
       works. */

    .exm-courses{display:grid;gap:.6rem}
    .exm-courses.is-cards{grid-template-columns:repeat(auto-fill,minmax(290px,1fr))}
    .exm-courses.is-rows{grid-template-columns:1fr}

    .exm-course{
        background:var(--surface);border:1px solid var(--rule);
        border-left:3px solid var(--faint);border-radius:8px;padding:.8rem .9rem;
    }
    .exm-course-top{
        display:flex;align-items:baseline;gap:.5rem;flex-wrap:wrap;
        margin-bottom:.45rem;
    }
    .exm-course-code{
        font-size:.72rem;font-weight:800;letter-spacing:.06em;color:var(--ink);
        font-variant-numeric:tabular-nums;
    }
    .exm-course h6{
        margin:0;font-size:.88rem;font-weight:600;color:var(--ink);line-height:1.3;
    }
    .exm-course-meta{font-size:.74rem;color:var(--muted);margin-top:.1rem}

    /* The four dropdowns. In rows layout they sit on one line with the title
       and the button; in cards they stack two by two underneath it. */
    .exm-course-set{display:grid;grid-template-columns:1fr 1fr;gap:.45rem}
    .exm-course-set label{
        display:block;font-size:.62rem;font-weight:800;letter-spacing:.08em;
        text-transform:uppercase;color:var(--muted);margin-bottom:.15rem;
    }
    .exm-course-set .form-control{
        border:1px solid var(--rule);border-radius:6px;font-size:.8rem;
        padding:.25rem .4rem;height:auto;color:var(--ink);background:var(--surface);
    }
    .exm-course-set .form-control:focus{
        border-color:var(--ink);box-shadow:0 0 0 3px rgba(21,35,59,.08);outline:none;
    }
    .exm-course-go{margin-top:.55rem;width:100%}

    .exm-courses.is-rows .exm-course{
        display:grid;grid-template-columns:minmax(200px,1.4fr) minmax(320px,2fr) auto;
        gap:.9rem;align-items:center;
    }
    .exm-courses.is-rows .exm-course-top{margin-bottom:0}
    .exm-courses.is-rows .exm-course-set{grid-template-columns:repeat(4,1fr)}
    .exm-courses.is-rows .exm-course-go{margin-top:0;width:auto;white-space:nowrap}

    @media (max-width:991px){
        .exm-courses.is-rows .exm-course{grid-template-columns:1fr}
        .exm-courses.is-rows .exm-course-go{width:100%}
    }

    /* The programme a block of courses belongs to. */
    .exm-group{margin-bottom:1.1rem}
    .exm-group-head{
        display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;
        padding-bottom:.35rem;border-bottom:1px solid var(--rule-soft);
    }

    /* How the last check came back, under the button that asked. Only ever
       filled in by JS, so it ships hidden and carries no colour of its own. */
    .exm-course .status-content{
        display:flex;align-items:center;justify-content:space-between;gap:.5rem;
        border:1px solid var(--rule);border-radius:6px;background:var(--ground);
        padding:.3rem .5rem;margin-top:.45rem;font-size:.76rem;color:var(--body);
    }
    .exm-course .status-content.status-completed{background:#eaf6ef;border-color:#bfe2cd;color:#0b6230}
    .exm-course .status-content.status-partial{background:#fdf8ec;border-color:#e8d9a8;color:#7a5406}
    .exm-course .status-content.status-not_taken{background:#fdf0ee;border-color:#f0cdc9;color:#8f2b22}
    .exm-course .status-content.status-no_students{background:var(--ground);color:var(--muted)}
    .exm-course .status-details{
        display:flex;gap:.5rem;white-space:nowrap;font-variant-numeric:tabular-nums;
    }
    .exm-course .status-icon{margin-right:.3rem}
    .exm-course .status-info{display:flex;align-items:center;gap:.1rem}

    /* The handler writes these three as bare numbers side by side, so each
       carries the glyph that says which number it is. Kept from the markup
       this replaced; without them the panel reads "12 3 5". */
    .exm-course .status-details span::before{
        font-family:"Font Awesome 5 Free";font-weight:900;margin-right:.2rem;
    }
    .exm-course .status-details .present-count::before{content:"\f00c"}
    .exm-course .status-details .absent-count::before{content:"\f00d"}
    .exm-course .status-details .pending-count::before{content:"\f017";font-weight:400}

    .exm-course .status-loading{color:var(--muted)}
    .exm-course .status-loading i{animation:exm-spin 1s linear infinite}
    @keyframes exm-spin{to{transform:rotate(360deg)}}
    @media (prefers-reduced-motion:reduce){
        .exm-course .status-loading i{animation:none}
    }

    .exm-seg{display:inline-flex;border:1px solid var(--rule);border-radius:6px;overflow:hidden}
    .exm-seg button{
        border:0;background:var(--surface);color:var(--muted);
        padding:.3rem .6rem;font-size:.8rem;cursor:pointer;
    }
    .exm-seg button + button{border-left:1px solid var(--rule)}
    .exm-seg button.active{background:var(--ink);color:#fff}

    /* ---- the all-programmes switch --------------------------------------
       Hand-rolled because the markup asked for Bootstrap 4's
       .custom-control.custom-switch, and this install ships Bootstrap 5.2,
       where that class does not exist — the switch has been rendering as a
       bare checkbox. The input keeps its id and its change handler. */
    .exm-switch{display:inline-flex;align-items:center;gap:.55rem;cursor:pointer;margin:0}
    .exm-switch input{position:absolute;opacity:0;width:0;height:0}
    .exm-switch .track{
        position:relative;width:38px;height:20px;border-radius:10px;flex:0 0 auto;
        background:var(--rule);border:1px solid var(--rule);transition:background .15s;
    }
    .exm-switch .track::after{
        content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;
        border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2);
        transition:transform .15s;
    }
    .exm-switch input:checked + .track{background:var(--good);border-color:var(--good)}
    .exm-switch input:checked + .track::after{transform:translateX(18px)}
    .exm-switch input:focus-visible + .track{box-shadow:0 0 0 3px rgba(21,35,59,.12)}
    .exm-switch .t{font-size:.86rem;font-weight:600;color:var(--ink)}

    .exm-cross{border-left:3px solid var(--faint)}
    .exm-cross.is-on{border-left-color:var(--good)}

    .exm-pills{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.6rem}
    .exm-pill{
        display:inline-block;padding:.12rem .5rem;border-radius:4px;font-size:.74rem;
        background:var(--ground);border:1px solid var(--rule);color:var(--body);
    }
    .exm-pill.is-here{background:var(--ink);border-color:var(--ink);color:#fff;font-weight:600}
    .exm-pill small{color:inherit;opacity:.75}

    /* ---- notices --------------------------------------------------------
       The screen had seven Bootstrap alerts stacked above the table, in four
       colours, before a single student was visible. They say the same things
       in one line each. */
    .exm-notes{display:grid;gap:.4rem;padding:.9rem 1.1rem 0}
    .exm-note-row{
        display:flex;align-items:flex-start;gap:.5rem;font-size:.82rem;
        color:var(--body);border:1px solid var(--rule);border-left:3px solid var(--faint);
        border-radius:6px;background:var(--ground);padding:.45rem .7rem;
    }
    .exm-note-row i{margin-top:.15rem;color:var(--faint)}
    .exm-note-row b{color:var(--ink)}
    .exm-note-row.is-good{background:#eaf6ef;border-color:#bfe2cd;border-left-color:var(--good);color:#0b6230}
    .exm-note-row.is-good i{color:var(--good)}
    .exm-note-row.is-good b{color:#0b6230}
    .exm-note-row.is-warn{background:#fdf8ec;border-color:#e8d9a8;border-left-color:var(--warn);color:#7a5406}
    .exm-note-row.is-warn i{color:var(--warn)}
    .exm-note-row.is-warn b{color:#7a5406}
    .exm-note-row.is-bad{background:#fdf0ee;border-color:#f0cdc9;border-left-color:var(--bad);color:#8f2b22}
    .exm-note-row.is-bad i{color:var(--bad)}
    .exm-note-row.is-bad b{color:#8f2b22}
    .exm-note-row a{color:inherit;text-decoration:underline}

    /* ---- the roll -------------------------------------------------------
       The two row states the server renders. Restated with !important
       because theme's transparent-cell rule outranks the template's own
       .table-danger, and because the bypass handler needs the cells to stay
       transparent so the colour it sets on the row shows through. */
    table.exm-table tbody tr.table-danger td{background:#fdf0ee !important}
    table.exm-table tbody tr.is-bypassed td{background:#fdf8ec !important}
    table.exm-table tbody tr.table-danger:hover td,
    table.exm-table tbody tr.is-bypassed:hover td{filter:brightness(.985)}

    table.exm-table tbody tr.cross-program-separator td{
        background:var(--ground) !important;
        border-top:1px solid var(--rule) !important;
        padding:.45rem .7rem !important;
    }
    .exm-sep{display:flex;align-items:center;justify-content:space-between;gap:1rem}

    /* Marks and counts read as figures, not prose. */
    table.exm-table td.exm-fig{
        font-variant-numeric:tabular-nums;color:var(--ink) !important;font-weight:600;
    }
    .exm-sub{display:block;font-size:.7rem;color:var(--muted);font-weight:400;margin-top:.1rem}
    .exm-dash{color:var(--faint)}

    .exm-tag-bad{background:#fdf0ee;color:#8f2b22;border-color:#f0cdc9}
    .exm-tag-flat{background:var(--ground);color:var(--muted);border-color:var(--rule)}

    /* Present / Absent. The radios keep their ids, names and classes; only
       the box around them is new, so a row can be read at a glance and hit
       with a thumb. */
    .exm-pa{display:inline-flex;gap:.3rem}
    .exm-pa .form-group{margin:0}
    .exm-pa .radio{
        border:1px solid var(--rule);border-radius:6px;padding:.1rem .5rem .1rem .1rem;
        background:var(--surface);
    }
    .exm-pa .radio label.cr{font-size:.78rem;color:var(--body);margin-bottom:0}
    table.exm-table tbody td .checkbox,table.exm-table tbody td .radio{margin:0}

    .exm-why{display:block;font-size:.72rem;margin-top:.25rem;color:var(--bad)}

    /* The bypass handler appends this same badge at run time with an ml-1,
       which 5.x does not define. Spacing it from here covers both the badge
       the server renders and the one the handler adds. */
    .exm-why .badge{margin-left:.3rem}

    /* ---- the summary the modal shows ------------------------------------ */
    .exm-tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem}
    .exm-tile{
        border:1px solid var(--rule);border-radius:8px;background:var(--ground);
        padding:.65rem .7rem;text-align:center;
    }
    .exm-tile .v{
        display:block;font-size:1.4rem;font-weight:800;line-height:1.1;color:var(--ink);
        font-variant-numeric:tabular-nums;
    }
    .exm-tile .k{
        font-size:.64rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
        color:var(--muted);
    }
    .exm-tile.is-good{background:#eaf6ef;border-color:#bfe2cd}
    .exm-tile.is-good .v{color:#0b6230}
    .exm-tile.is-bad{background:#fdf0ee;border-color:#f0cdc9}
    .exm-tile.is-bad .v{color:#8f2b22}
    .exm-tile.is-warn{background:#fdf8ec;border-color:#e8d9a8}
    .exm-tile.is-warn .v{color:#7a5406}

    .exm-split{height:8px;border-radius:4px;background:var(--rule-soft);overflow:hidden;display:flex}
    .exm-split > div{height:100%}

    .exm-modal .modal-content{border:1px solid var(--rule);border-radius:10px}
    .exm-modal .modal-header{
        background:var(--surface);border-bottom:1px solid var(--rule);
        padding:.9rem 1.1rem;
    }
    .exm-modal .modal-title{font-size:1rem;font-weight:700;color:var(--ink)}
    .exm-modal .modal-body{padding:1.1rem}
    .exm-modal .modal-footer{border-top:1px solid var(--rule);padding:.8rem 1.1rem}
    .exm-modal .exm-kv{
        display:grid;grid-template-columns:auto 1fr;gap:.25rem .75rem;
        font-size:.84rem;color:var(--body);
    }
    .exm-modal .exm-kv dt{
        font-size:.64rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
        color:var(--muted);align-self:center;white-space:nowrap;
    }
    .exm-modal .exm-kv dd{margin:0;color:var(--ink);font-weight:600}

    @media (max-width:767px){
        .exm-tiles{grid-template-columns:repeat(2,1fr)}
        .exm-course-set{grid-template-columns:1fr}
    }
</style>
@endpush
