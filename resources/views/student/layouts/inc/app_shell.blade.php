{{--
  The portal, on a phone.

  Everything here is inside @media (max-width: 991px) — the breakpoint the
  portal's own stylesheet already uses — so a laptop sees exactly what it saw
  before. That containment is the whole safety story of this change, and it is
  asserted by scripts/student_app_shell_test.php rather than trusted.

  Colours were measured, not chosen. The brand blue #04a9f5 is 2.63:1 on white
  and fails even as a mark, so it appears only as a wash behind an icon; the
  active tab's text is #0a6fa3 at 5.50:1. And the active tab never depends on
  colour alone — it carries a filled pill and a heavier label as well, so it
  reads for someone who cannot separate the two blues.
--}}

@push('styles')
<style>
    :root {
        /* One number for the bar's height. The chat launcher and the install
           card both clear it by reading this, so there is one place to change
           it and nothing to keep in sync by hand. */
        --app-nav-h: 58px;
        --app-nav-total: calc(var(--app-nav-h) + env(safe-area-inset-bottom, 0px));

        --app-ink:    #1b2b44;  /* 14.23:1 */
        --app-muted:  #64748b;  /*  4.76:1 */
        --app-on:     #0a6fa3;  /*  5.50:1 — the active label */
        --app-wash:   #e6f4fd;
        --app-rule:   #e4e8f0;
        --app-bad:    #d03b3b;
    }

    @media (max-width: 991px) {

        /* ---- the bar --------------------------------------------------- */

        .app-nav {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            z-index: 10600;           /* above the chat launcher (10500) */
            display: flex;
            align-items: stretch;
            height: var(--app-nav-total);
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: rgba(255, 255, 255, .92);
            -webkit-backdrop-filter: saturate(180%) blur(14px);
            backdrop-filter: saturate(180%) blur(14px);
            border-top: 1px solid var(--app-rule);
            transition: transform .25s cubic-bezier(.32, .72, 0, 1);
        }

        /*
         * Out of the way while a dialog is open.
         *
         * The portal's .modal sits at z-index 1072 and the bar at 10600, so
         * the bar floated on top of every dialog — including the More sheet
         * it opens itself, covering its own footer. Lowering the bar instead
         * would put it under the chat launcher (10500), which it has to stay
         * above. So it leaves: the bar slides down as the sheet rises, which
         * is what it should do anyway.
         */
        body.modal-open .app-nav { transform: translateY(100%); }

        .app-tab {
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            min-height: 44px;          /* the floor for a thumb */
            padding: 6px 2px;
            color: var(--app-muted);
            text-decoration: none;
            background: none;
            border: 0;
            /* More is a <button>, and a button inherits no font family. Without
               this its label rendered in the browser's own UI font while the
               four links beside it used the theme's. */
            font-family: inherit;
            font-size: .66rem;
            font-weight: 600;
            letter-spacing: .01em;
            /* No 300ms wait, and no grey flash over the tap. */
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            transition: color .12s ease;
        }
        .app-tab:hover { color: var(--app-muted); text-decoration: none; }

        /* One line, always. A longer translation — "Résultats", "Paiements" —
           wrapping to two lines would push the bar taller than the padding the
           page was given, and hide the last row of every screen. */
        .app-tab > span:last-child {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Five tabs on a 320px screen is 64px each. */
        @media (max-width: 359px) {
            .app-tab { font-size: .6rem; }
            .app-tab-ico { width: 38px; }
        }

        /* A phone on its side has little height to spare, and the bar should
           not take 58px of it. */
        @media (orientation: landscape) and (max-height: 460px) {
            :root { --app-nav-h: 46px; }
            .app-tab { font-size: .58rem; gap: 1px; }
            .app-tab-ico { height: 20px; }
        }

        .app-tab-ico {
            /* The dot is absolutely positioned against this. Without it the
               dot resolved against .app-nav — the nearest positioned ancestor
               — and sat at the far left of the bar instead of on the icon. */
            position: relative;
            display: flex; align-items: center; justify-content: center;
            width: 46px; height: 26px; border-radius: 13px;
            font-size: 1rem; line-height: 1;
            transition: background .16s ease, transform .12s ease;
        }

        /* Active is three things at once — colour, a filled pill, and weight —
           so it never rests on telling one blue from another. */
        .app-tab.is-on { color: var(--app-on); font-weight: 800; }
        .app-tab.is-on .app-tab-ico { background: var(--app-wash); }

        /* The press itself. Instant, small, and on the icon rather than the
           whole tab, so the row does not jump. */
        .app-tab:active .app-tab-ico { transform: scale(.88); }

        .app-tab-dot {
            position: absolute;
            top: -1px; right: 9px;
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--app-bad);
            box-shadow: 0 0 0 2px #fff;
        }

        /* The page needs to end above the bar, or its last row is unreachable. */
        body { padding-bottom: var(--app-nav-total) !important; }

        /* ---- the More sheet -------------------------------------------- */

        .app-sheet .modal-dialog {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            margin: 0;
            max-width: none;
        }
        .app-sheet .modal-content {
            border: 0;
            border-radius: 18px 18px 0 0;
            max-height: 86vh;
            max-height: 86dvh;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .app-sheet .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; }

        /* The grab handle. It says "this came from the bottom and goes back
           there", which a centred dialog never has to say. */
        .app-grab {
            width: 38px; height: 4px; border-radius: 2px;
            background: #d7dce5; margin: 10px auto 2px;
        }

        .app-sheet-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: .4rem 1.15rem .8rem;
        }
        .app-sheet-head h6 {
            margin: 0; font-size: 1rem; font-weight: 700; color: var(--app-ink);
        }
        .app-sheet-close {
            border: 0; background: #f1f4f9; color: var(--app-muted);
            width: 32px; height: 32px; border-radius: 50%; line-height: 1;
            touch-action: manipulation; -webkit-tap-highlight-color: transparent;
        }

        .app-group { padding: 0 1.15rem; }
        .app-group-head {
            font-size: .64rem; font-weight: 800; letter-spacing: .09em;
            text-transform: uppercase; color: var(--app-muted);
            padding: .9rem 0 .35rem;
        }
        .app-link {
            display: flex; align-items: center; gap: .85rem;
            min-height: 48px;          /* the floor for a thumb */
            padding: .55rem 0;
            border-top: 1px solid #f1f4f9;
            color: var(--app-ink); text-decoration: none;
            font-size: .9rem; font-weight: 600;
            touch-action: manipulation; -webkit-tap-highlight-color: transparent;
        }
        .app-group .app-link:first-of-type { border-top: 0; }
        .app-link:active { background: #f7f9fc; }
        .app-link:hover { color: var(--app-ink); text-decoration: none; }
        .app-link i { width: 22px; text-align: center; color: var(--app-muted); font-size: .95rem; }
        .app-link .chev { margin-left: auto; color: #c3cbd8; font-size: .75rem; }
        .app-link.is-on { color: var(--app-on); }
        .app-link.is-on i { color: var(--app-on); }

        /* ---- every other dialog, as a sheet ----------------------------- */

        /* Markup-free, on purpose. None of the portal's twelve dialogs is
           touched, every one of them rises from the bottom, and any dialog
           added later gets the same treatment without anyone remembering to
           ask for it. */
        .modal:not(.app-sheet) .modal-dialog {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            margin: 0;
            max-width: none;
            transform: translateY(100%);
            transition: transform .25s cubic-bezier(.32, .72, 0, 1);
        }
        .modal:not(.app-sheet).show .modal-dialog { transform: translateY(0); }
        .modal:not(.app-sheet) .modal-content {
            border: 0;
            border-radius: 18px 18px 0 0;
            max-height: 88vh;
            max-height: 88dvh;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .modal:not(.app-sheet) .modal-body {
            overflow-y: auto; -webkit-overflow-scrolling: touch;
        }
        /* The handle, drawn rather than marked up. */
        .modal:not(.app-sheet) .modal-content::before {
            content: '';
            display: block;
            width: 38px; height: 4px; border-radius: 2px;
            background: #d7dce5; margin: 10px auto 0;
        }
        /* A full-width action is easier to hit than two small ones in a row. */
        .modal:not(.app-sheet) .modal-footer { padding-bottom: 1rem; }
        .modal:not(.app-sheet) .modal-footer .btn { min-height: 44px; }

        /* The hamburger sidebar is still reachable on a phone, and it is
           fixed and full height, so its last item sat behind the bar. */
        .pcoded-navbar .navbar-content { padding-bottom: var(--app-nav-total); }

        /* ---- clearing the bar ------------------------------------------- */

        /* Both of these already ask for the safe-area inset; until
           viewport-fit=cover was set it resolved to zero and they sat in the
           home-indicator strip. Now they clear the inset AND the bar. */
        #pax-chat {
            bottom: calc(var(--app-nav-h) + 14px + env(safe-area-inset-bottom, 0px)) !important;
        }
        .pwa-install--floating {
            bottom: calc(var(--app-nav-h) + 14px + env(safe-area-inset-bottom, 0px)) !important;
        }
    }

    /* Desktop keeps the sidebar and never sees any of this. */
    @media (min-width: 992px) {
        .app-nav, .app-sheet { display: none !important; }
    }
</style>
@endpush
