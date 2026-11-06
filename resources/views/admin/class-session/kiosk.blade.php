@extends('admin.layouts.master')

@section('title', $title)

@push('styles')
<style>
    .kiosk-container {
        background-color: #eef1f5;
        min-height: 100vh;
        padding: 20px;
    }
    .kiosk-header {
        background: rgba(255,255,255,0.95);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .session-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    .session-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .session-card.active {
        border: 3px solid #28a745;
        background: #f6fcf8;
    }
    .session-card.pending {
        border-left: 5px solid #ffc107;
    }
    .session-card.completed {
        border-left: 5px solid #28a745;
        opacity: 0.8;
    }
    .session-card.cancelled {
        border-left: 5px solid #dc3545;
        opacity: 0.6;
    }
    /* A joint class is marked by its "Joint Class" chip; the left stripe stays the status colour. */
    .session-card.joint-class .joint-class-badge {
        border-bottom: 1px dashed #dee2e6;
        padding-bottom: 8px;
    }
    .session-card.joint-class .joint-sessions-list {
        background: #f8f9fa;
        margin: 0 -20px -20px -20px;
        padding: 10px 20px;
        border-radius: 0 0 15px 15px;
    }
    .scanner-section {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    .stats-card .count {
        font-size: 2.5rem;
        font-weight: bold;
        color: #333;
    }
    .stats-card.present .count { color: #28a745; }
    .stats-card.absent .count { color: #dc3545; }
    .stats-card.late .count { color: #ffc107; }
    .stats-card.total .count { color: #6c757d; }
    #reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        min-height: 300px;
        background: #f8f9fa;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #reader video {
        border-radius: 10px;
    }
    .student-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .student-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
    }
    .student-item:last-child {
        border-bottom: none;
    }
    .student-item.clocked-in {
        background: #d4edda;
    }
    .student-item.clocked-out {
        background: #f8f9fa;
    }
    .student-item.late {
        background: #fff3cd;
    }
    .program-header {
        background: #e9ecef;
        padding: 8px 15px;
        border-left: 4px solid #17a2b8;
        font-size: 0.9rem;
    }
    .joint-badge {
        font-size: 0.7rem;
        vertical-align: middle;
    }
    .logbook-form {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .time-display {
        font-size: 1.5rem;
        font-weight: bold;
        color: #333;
    }
    .recent-scan {
        animation: highlight 2s ease-out;
    }
    @keyframes highlight {
        0% { background: #28a74533; }
        100% { background: transparent; }
    }
    .btn-start-class {
        background: #28a745;
        border: none;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
    }
    .btn-end-class {
        background: #dc3545;
        border: none;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
    }
    /* Student Activity Section */
    .activity-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .activity-tabs {
        display: flex;
        border-bottom: 2px solid #eee;
        margin-bottom: 15px;
    }
    .activity-tab {
        padding: 10px 20px;
        border: none;
        background: none;
        cursor: pointer;
        font-weight: 500;
        color: #666;
        position: relative;
    }
    .activity-tab.active {
        color: #04a9f5;
    }
    .activity-tab.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: #04a9f5;
        border-radius: 2px;
    }
    .activity-tab .badge {
        margin-left: 5px;
    }
    .activity-content {
        max-height: 300px;
        overflow-y: auto;
    }
    .chat-message-lecturer {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 10px;
    }
    .chat-message-lecturer.from-lecturer {
        background: #eaf5fe;
        border-left: 3px solid #2196f3;
    }
    .chat-avatar-sm {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #5b6b82;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .chat-avatar-sm.lecturer {
        background: #04a9f5;
    }
    .lecturer-chat-input {
        border-top: 1px solid #eee;
        padding-top: 15px;
    }
    .question-item {
        cursor: pointer;
        transition: all 0.2s;
    }
    .question-item:hover {
        transform: translateX(5px);
    }
    .chat-content {
        flex: 1;
    }
    .chat-sender-name {
        font-weight: 600;
        font-size: 0.85rem;
        color: #333;
    }
    .chat-text {
        font-size: 0.9rem;
        color: #555;
        margin-top: 3px;
    }
    .chat-time {
        font-size: 0.75rem;
        color: #999;
        margin-top: 3px;
    }
    .alert-item {
        display: flex;
        gap: 12px;
        padding: 12px;
        background: #fff3cd;
        border-radius: 10px;
        margin-bottom: 10px;
        border-left: 4px solid #ffc107;
    }
    .alert-item.exam { border-left-color: #dc3545; background: #f8d7da; }
    .alert-item.assignment { border-left-color: #17a2b8; background: #d1ecf1; }
    .alert-item.deadline { border-left-color: #6f42c1; background: #e2d5f1; }
    .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        color: #ffc107;
        font-size: 1.2rem;
    }
    .alert-item.exam .alert-icon { color: #dc3545; }
    .alert-item.assignment .alert-icon { color: #17a2b8; }
    .alert-item.deadline .alert-icon { color: #6f42c1; }
    .question-item {
        padding: 12px;
        background: #e3f2fd;
        border-radius: 10px;
        margin-bottom: 10px;
        border-left: 4px solid #2196f3;
    }
    .question-item.answered {
        background: #e8f5e9;
        border-left-color: #4caf50;
    }
    .question-badge {
        font-size: 0.7rem;
        padding: 3px 8px;
    }
    .no-activity {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }
    .no-activity i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* ==================================================================
       Alignment & spacing — layout only, colours unchanged.
       Spacing scale: 4 · 8 · 12 · 16 · 24 px.
       ================================================================== */

    /* Bootstrap 4 helpers this page (and its script) still uses. The app
       runs Bootstrap 5, where they no longer exist, so icons sat glued to
       their labels and "block" buttons were only as wide as their text. */
    .kiosk-container .mr-1, .kiosk-ui .mr-1 { margin-right: 4px !important; }
    .kiosk-container .mr-2, .kiosk-ui .mr-2 { margin-right: 8px !important; }
    .kiosk-container .ml-1, .kiosk-ui .ml-1 { margin-left: 4px !important; }
    .kiosk-container .ml-2, .kiosk-ui .ml-2 { margin-left: 8px !important; }
    .kiosk-container .text-right, .kiosk-ui .text-right { text-align: right !important; }
    .kiosk-container .btn-block, .kiosk-ui .btn-block { display: block; width: 100%; }
    .kiosk-container .input-group-append { display: flex; }

    .kiosk-container { padding: 24px; }

    /* Header */
    .kiosk-header { padding: 16px 24px; margin-bottom: 24px; }
    .kiosk-header h2 {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 24px;
        margin-bottom: 4px !important;
    }
    .kiosk-header h2 i { margin: 0 !important; }
    .kiosk-header-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }
    .kiosk-header-right .time-display { line-height: 1.2; font-variant-numeric: tabular-nums; }

    /* Column headings: the three columns start level */
    .kiosk-col-title {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 24px;
        margin: 0 0 16px;
        color: #fff;
        font-size: 16px;
    }
    .kiosk-col-subtitle { margin-top: 24px; font-size: 14px; }

    /* Headings inside cards */
    .kiosk-card-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 16px;
    }

    /* Session cards */
    .session-card { padding: 16px; margin-bottom: 16px; }
    .session-card .session-main { display: flex; align-items: flex-start; gap: 16px; }
    .session-card .session-info { flex: 1 1 auto; min-width: 0; }
    .session-card .session-info h6 { line-height: 1.35; }
    .session-card .session-action { flex: none; }
    .session-card .session-action .btn { white-space: nowrap; }
    .session-card .session-meta { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; }
    .session-card .joint-class-badge { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px !important; }
    .session-card .joint-class-badge .ml-1,
    .session-card .joint-class-badge .ml-2 { margin-left: 0 !important; }
    .session-card.joint-class .joint-sessions-list { margin: 16px -16px -16px; padding: 12px 16px 16px; border-radius: 0 0 15px 15px; }

    /* Program short codes on joint classes. These were badge-light, which
       this theme draws white on white, so the programs could not be read. */
    .program-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; }
    .program-chip {
        display: inline-block;
        padding: 2px 8px;
        border: 1px solid #e3e6ea;
        border-radius: 4px;
        background: #f8f9fa;
        color: #495057;
        font-size: 11px;
        line-height: 16px;
        white-space: nowrap;
    }

    /* One scan button per program, in a wrapping grid */
    .joint-scan-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
    .joint-scan-buttons .btn { margin: 0; white-space: nowrap; }

    /* Middle column */
    .scanner-section { padding: 24px; }
    #activeSessionTitle { line-height: 1.35; margin-bottom: 8px; }
    #activeSessionTime .badge { margin: 0 4px 4px 0 !important; }
    #reader { margin-bottom: 16px !important; }
    #scannerControls { margin-bottom: 24px !important; }
    #scannerControls .btn { margin: 0 4px; }
    .stats-card { padding: 16px; height: 100%; }
    #endSessionBtn { display: inline-flex; align-items: center; gap: 8px; }
    #endSessionBtn i { margin: 0 !important; }

    /* Right column */
    .logbook-form { padding: 16px; }
    .logbook-form .form-group { margin-bottom: 12px; }
    .logbook-form label { margin-bottom: 4px; }
    #studentListSection { margin-bottom: 16px !important; }
    .kiosk-side-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 24px 16px;
        text-align: center;
        color: #6c757d;
    }
    .kiosk-side-placeholder i { font-size: 2rem; opacity: .5; }
    .student-item { gap: 12px; padding: 8px 12px; }
    .student-item > div:first-child { min-width: 0; }
    .student-item > div:last-child { flex: none; }
    .program-header { padding: 8px 12px; }

    .activity-section { padding: 16px; margin-top: 16px; }
    .activity-tabs { margin-bottom: 12px; }
    .activity-tab {
        flex: 1 1 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-width: 0;
        padding: 8px 2px;
        font-size: 12px;
        white-space: nowrap;
    }
    .activity-tab .badge { margin-left: 0; font-size: 10px; padding: 2px 5px; }
    .lecturer-chat-input { padding-top: 12px; }

    @media (max-width: 991.98px) {
        .kiosk-container { padding: 16px; }
        .kiosk-header-right { align-items: flex-start; margin-top: 12px; }
        .col-lg-5 .kiosk-lane,
        .col-lg-3 .kiosk-lane { margin-top: 16px; }
    }

    /* ==================================================================
       Design — a classroom console. Same layout, same behaviour.
       A calm light surface with a faint dot grid (graph paper), a dark
       console bar, white cards with a status stripe, and a camera
       viewfinder. No gradients; colour only where it means something.
       ================================================================== */
    .kiosk-container {
        --k-ink: #1e2a3b;          /* console navy */
        --k-text: #1f2937;
        --k-muted: #64748b;
        --k-line: #e3e8ef;
        --k-blue: #04a9f5;         /* the theme's accent */
        --k-green: #22a55b;
        --k-amber: #f0ad4e;
        --k-red: #dc3545;
        --k-grey: #94a3b8;
        --k-head: #5b4bb7;         /* this screen's purple, solid */
        --k-purple: #7c5cc4;
        background: #f6f8fb;       /* plain, near-white */
        border-radius: 12px;
    }

    /* Header bar */
    .kiosk-header {
        background: var(--k-head);
        color: #fff;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(91, 75, 183, .25);
    }
    .kiosk-header h2 { color: #fff; font-weight: 600; letter-spacing: .01em; }
    .kiosk-header h2 i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .16);
        color: #fff;
        font-size: 20px;
    }
    .kiosk-header .text-muted { color: rgba(255, 255, 255, .8) !important; }
    .kiosk-header .time-display {
        padding: 4px 14px;
        border-radius: 8px;
        background: #fff;
        color: var(--k-head);
        font-size: 1.4rem;
        letter-spacing: .04em;
    }
    .kiosk-header .btn-outline-secondary {
        border-color: rgba(255, 255, 255, .3);
        color: #fff;
        background: transparent;
    }
    .kiosk-header .btn-outline-secondary:hover { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .5); color: #fff; }

    /* Column headings: a coloured icon chip per column */
    .kiosk-col-title {
        gap: 10px;
        color: #1f2937;
        font-size: 15px;
        font-weight: 600;
    }
    .kiosk-col-title i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: #fff4e0;
        color: #d98a12;
        font-size: 14px;
    }
    .lane-attendance .kiosk-col-title i { background: #e3f6ec; color: var(--k-green); }
    .lane-details .kiosk-col-title i { background: #e4f4fe; color: #0b8fd0; }
    .kiosk-col-subtitle { font-size: 14px; }
    .kiosk-col-subtitle i { background: #efe9fb !important; color: var(--k-purple) !important; }

    /* Lanes: each column is a panel that holds its heading and its cards,
       so the screen reads as three clear sections. */
    .kiosk-lane {
        height: 100%;
        padding: 0 16px 16px;
        background: #edf1f7;
        border: 1px solid #dde4ee;
        border-top: 4px solid #f0ad4e;
        border-radius: 14px;
    }
    .kiosk-lane.lane-attendance { border-top-color: var(--k-green); }
    .kiosk-lane.lane-details { border-top-color: #0b8fd0; }
    .kiosk-lane > .kiosk-col-title {
        margin: 0 -16px 16px;
        padding: 12px 16px;
        background: #fff;
        border-bottom: 1px solid #dde4ee;
        border-radius: 10px 10px 0 0;
    }
    .kiosk-lane > .kiosk-col-subtitle {
        margin: 24px 0 12px;
        padding: 0;
        background: none;
        border: 0;
    }
    .kiosk-lane-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
        padding: 2px 10px;
        border-radius: 999px;
        background: #eef1f5;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
        line-height: 20px;
    }
    .lane-classes .kiosk-lane-pill { background: #fff4e0; color: #b9700a; }
    .kiosk-lane-pill.is-live { background: #e3f6ec; color: #1b8a4b; }
    .kiosk-lane-pill.is-live::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--k-green);
        animation: kiosk-live 1.6s ease-in-out infinite;
    }
    @media (prefers-reduced-motion: reduce) { .kiosk-lane-pill.is-live::before { animation: none; } }
    .kiosk-lane .session-card:last-of-type { margin-bottom: 0; }

    /* Cards: white, fine border, quiet shadow */
    .session-card,
    .scanner-section,
    .logbook-form,
    .activity-section {
        border: 1px solid var(--k-line);
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .05);
    }
    .session-card { transition: box-shadow .15s ease, border-color .15s ease; }
    .session-card:hover { transform: none; box-shadow: 0 4px 12px rgba(16, 24, 40, .08); }
    .session-card h6 { color: var(--k-text); font-weight: 600; }

    /* Status stripe */
    .session-card.pending { border-left: 4px solid var(--k-amber); }
    .session-card.in_progress {
        border-left: 4px solid var(--k-green);
        background: #f6fcf8;
        box-shadow: 0 0 0 1px #bfe6cc, 0 4px 12px rgba(34, 165, 91, .08);
    }
    .session-card.completed { border-left: 4px solid var(--k-grey); opacity: 1; }
    .session-card.completed h6 { color: #475569; }
    .session-card.cancelled { border-left: 4px solid var(--k-red); opacity: .7; }
    .session-card.joint-class .joint-class-badge { border-bottom-color: var(--k-line); }
    .session-card.joint-class .joint-sessions-list { background: #f8fafc; border-top: 1px solid var(--k-line); }

    /* "Live" dot on the class in progress */
    .session-card.in_progress .badge-success { display: inline-flex; align-items: center; gap: 5px; }
    .session-card.in_progress .badge-success::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #fff;
        animation: kiosk-live 1.6s ease-in-out infinite;
    }
    @keyframes kiosk-live { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }
    @media (prefers-reduced-motion: reduce) {
        .session-card.in_progress .badge-success::before { animation: none; }
    }

    /* Add Extra Class: a dashed "add" tile in the extra-class purple */
    .kiosk-add-btn {
        padding: 12px 16px;
        border: 1px dashed #b9a6e6;
        border-radius: 12px;
        background: #f6f2fd;
        color: var(--k-purple);
        font-weight: 500;
    }
    .kiosk-add-btn:hover,
    .kiosk-add-btn:focus { border-style: solid; border-color: var(--k-purple); background: #efe9fb; color: #5e40a8; }

    /* Camera viewfinder */
    .kiosk-viewfinder {
        position: relative;
        max-width: 500px;
        margin: 0 auto 16px;
        padding: 12px;
        border-radius: 12px;
        background: var(--k-ink);
    }
    .kiosk-viewfinder #reader {
        max-width: none;
        min-height: 280px;
        margin: 0 !important;
        border-radius: 8px;
        background: #263347;
        color: #cbd5e1;
        overflow: hidden;
    }
    .kiosk-viewfinder #reader:empty::before {
        content: "Camera preview appears here";
        font-size: 13px;
        color: #94a3b8;
    }
    .vf-corner {
        position: absolute;
        z-index: 2;
        width: 24px;
        height: 24px;
        border: 3px solid var(--k-blue);
        pointer-events: none;
    }
    .vf-corner.tl { top: 6px; left: 6px; border-right: 0; border-bottom: 0; border-top-left-radius: 8px; }
    .vf-corner.tr { top: 6px; right: 6px; border-left: 0; border-bottom: 0; border-top-right-radius: 8px; }
    .vf-corner.bl { bottom: 6px; left: 6px; border-right: 0; border-top: 0; border-bottom-left-radius: 8px; }
    .vf-corner.br { bottom: 6px; right: 6px; border-left: 0; border-top: 0; border-bottom-right-radius: 8px; }

    /* Stat tiles: softly tinted, the number in its own colour */
    .stats-card {
        border: 0;
        border-radius: 10px;
        box-shadow: none;
        background: #f1f5f9;
    }
    .stats-card.present { background: #e7f6ee; }
    .stats-card.late { background: #fff4e0; }
    .stats-card.late .count { color: #d98a12; }
    .stats-card .count { font-size: 2rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .stats-card small { color: #475569; text-transform: uppercase; letter-spacing: .06em; font-size: 11px; font-weight: 600; }

    /* The attendance panel shows it is live with a green top edge */
    #scannerSection { border-top: 4px solid var(--k-green); }

    .btn-end-class { border-radius: 10px; box-shadow: 0 2px 6px rgba(220, 53, 69, .25); }
    .btn-end-class:hover { background: #c82333; }

    /* No class running: a friendly placeholder with a tinted icon */
    #noSessionPlaceholder .fa-chalkboard {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 88px;
        height: 88px;
        border-radius: 50%;
        background: #e3f6ec;
        color: var(--k-green) !important;
        font-size: 2.2rem !important;
    }
    #noSessionPlaceholder h4 { color: #1f2937 !important; font-weight: 600; }
    .kiosk-side-placeholder i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #e4f4fe;
        color: #0b8fd0;
        font-size: 1.4rem !important;
        opacity: 1 !important;
    }

    /* Right column: each card has its own colour on the top edge and icon */
    .kiosk-card-title { color: var(--k-text); font-weight: 600; }
    .kiosk-card-title i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 7px;
        font-size: 12px;
        background: #e4f4fe;
        color: #0b8fd0;
    }
    #studentListSection { border-top: 4px solid #0b8fd0; }
    #logbookSection { border-top: 4px solid var(--k-purple); }
    #logbookSection .kiosk-card-title i { background: #efe9fb; color: var(--k-purple); }
    #activitySection { border-top: 4px solid #17a2b8; }
    #activitySection .kiosk-card-title i { background: #e0f5f8; color: #138496; }
    .scanner-section .kiosk-card-title i { background: #f1f5f9; color: #475569; }
    .activity-tabs { border-bottom-color: var(--k-line); }
    .student-item { border-bottom-color: #eef1f5; }
</style>
@endpush

@section('content')
<div class="kiosk-container">
    <!-- Header -->
    <div class="kiosk-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2 class="mb-1">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                    Class Session Kiosk
                </h2>
                <p class="text-muted mb-0">
                    {{ $teacher->first_name ?? '' }} {{ $teacher->last_name ?? '' }} | 
                    {{ date('l, d F Y', strtotime($today)) }}
                </p>
            </div>
            <div class="col-md-6 kiosk-header-right">
                <div class="time-display" id="currentTime">{{ date('H:i:s') }}</div>
                <a href="{{ route('admin.dashboard.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Sessions List -->
        <div class="col-lg-4">
            @php
                // One card per joint class, so count joint groups once.
                $classCount = collect($scheduled_sessions)->map(fn ($s) => $s->joint_key ?? 'id'.$s->id)->unique()->count()
                    + collect($extra_sessions)->map(fn ($s) => $s->joint_key ?? 'id'.$s->id)->unique()->count();
            @endphp
            <div class="kiosk-lane lane-classes">
            <h5 class="kiosk-col-title"><i class="fas fa-calendar-day"></i> Today's Classes <span class="kiosk-lane-pill">{{ $classCount }}</span></h5>

            <!-- Scheduled Sessions -->
            @php
                $displayedJointKeys = [];
            @endphp
            @forelse($scheduled_sessions as $session)
            @php
                $isJoint = $session->is_joint_class ?? false;
                $jointKey = $session->joint_key ?? null;
                $alreadyDisplayed = $jointKey && in_array($jointKey, $displayedJointKeys);
                if ($jointKey && !$alreadyDisplayed) {
                    $displayedJointKeys[] = $jointKey;
                }
            @endphp
            
            @if(!$alreadyDisplayed)
            <div class="session-card {{ $session->status }} {{ $isJoint ? 'joint-class' : '' }}" data-session-id="{{ $session->id }}" @if($isJoint) data-joint-key="{{ $jointKey }}" @endif>
                @if($isJoint)
                <div class="joint-class-badge mb-2">
                    <span class="badge badge-info"><i class="fas fa-users mr-1"></i> Joint Class</span>
                    <small class="text-muted ml-2">
                        {{ count($session->joint_programs) }} programs
                    </small>
                </div>
                @endif
                <div class="session-main">
                    <div class="session-info">
                        <h6 class="mb-1">{{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown' }}</h6>
                        @if($isJoint)
                        <div class="program-chips">
                            @foreach($session->joint_programs as $idx => $prog)
                                <span class="program-chip" title="{{ $prog->title }}">{{ $prog->shortcode ?? $prog->title }}</span>
                            @endforeach
                        </div>
                        @else
                        <small class="text-muted">
                            {{ $session->program->shortcode ?? $session->program->title ?? 'N/A' }} | 
                            {{ $session->semester->title ?? 'N/A' }}
                            @if($session->section)
                             | {{ $session->section->title }}
                            @endif
                        </small>
                        @endif
                        <div class="session-meta">
                            <span class="badge badge-info">
                                {{ \Carbon\Carbon::parse($session->scheduled_start_time)->format('H:i') }} - 
                                {{ \Carbon\Carbon::parse($session->scheduled_end_time)->format('H:i') }}
                            </span>
                            @if($session->status == 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($session->status == 'in_progress')
                                <span class="badge badge-success">In Progress</span>
                            @elseif($session->status == 'completed')
                                <span class="badge badge-secondary">Completed</span>
                            @elseif($session->status == 'cancelled')
                                <span class="badge badge-danger">Cancelled</span>
                            @endif
                        </div>
                    </div>
                    <div class="session-action">
                        @if($session->status == 'pending')
                            @if($isJoint)
                            <button class="btn btn-success btn-sm start-joint-session-btn" 
                                    data-joint-key="{{ $jointKey }}"
                                    data-session-ids="{{ collect($joint_sessions[$jointKey]['sessions'] ?? [])->pluck('id')->implode(',') }}">
                                <i class="fas fa-play mr-1"></i> Start All
                            </button>
                            @else
                            <button class="btn btn-success btn-sm start-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-play mr-1"></i> Start
                            </button>
                            @endif
                        @elseif($session->status == 'in_progress')
                            <button class="btn btn-primary btn-sm select-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-qrcode mr-1"></i> Scan
                            </button>
                        @elseif($session->status == 'completed')
                            <button class="btn btn-secondary btn-sm manage-completed-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-edit mr-1"></i> Manage
                            </button>
                        @endif
                    </div>
                </div>
                
                @if($isJoint && $session->status == 'in_progress')
                {{-- Show all joint session scan buttons when in progress --}}
                <div class="joint-sessions-list mt-2 pt-2 border-top">
                    <small class="text-muted d-block mb-2">Scan attendance for each program:</small>
                    <div class="joint-scan-buttons">
                    @foreach($joint_sessions[$jointKey]['sessions'] ?? [] as $jointSession)
                    <button class="btn btn-outline-primary btn-sm select-session-btn" data-id="{{ $jointSession->id }}" title="{{ $jointSession->program->title }}">
                        <i class="fas fa-qrcode"></i> {{ $jointSession->program->shortcode ?? $jointSession->program->title }}
                    </button>
                    @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif
            @empty
            <div class="session-card text-center">
                <p class="text-muted mb-0">No scheduled classes for today</p>
            </div>
            @endforelse

            <!-- Extra Sessions -->
            @if($extra_sessions->count() > 0)
            <h6 class="kiosk-col-title kiosk-col-subtitle"><i class="fas fa-plus-circle"></i> Extra Classes</h6>
            @php
                $displayedExtraJointKeys = [];
            @endphp
            @foreach($extra_sessions as $session)
            @php
                $isJoint = $session->is_joint_class ?? false;
                $jointKey = $session->joint_key ?? null;
                $alreadyDisplayed = $jointKey && in_array($jointKey, $displayedExtraJointKeys);
                if ($jointKey && !$alreadyDisplayed) {
                    $displayedExtraJointKeys[] = $jointKey;
                }
            @endphp
            
            @if(!$alreadyDisplayed)
            <div class="session-card {{ $session->status }} {{ $isJoint ? 'joint-class' : '' }}" data-session-id="{{ $session->id }}" @if($isJoint) data-joint-key="{{ $jointKey }}" @endif>
                @if($isJoint)
                <div class="joint-class-badge mb-2">
                    <span class="badge badge-info"><i class="fas fa-users mr-1"></i> Joint Class</span>
                    <span class="badge badge-primary ml-1">Extra</span>
                    <small class="text-muted ml-2">
                        {{ count($session->joint_programs) }} programs
                    </small>
                </div>
                @else
                <span class="badge badge-primary mb-2">Extra Class</span>
                @endif
                <div class="session-main">
                    <div class="session-info">
                        <h6 class="mb-1">{{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown' }}</h6>
                        @if($isJoint)
                        <div class="program-chips">
                            @foreach($session->joint_programs as $idx => $prog)
                                <span class="program-chip" title="{{ $prog->title }}">{{ $prog->shortcode ?? $prog->title }}</span>
                            @endforeach
                        </div>
                        @else
                        <small class="text-muted">
                            {{ $session->program->shortcode ?? $session->program->title ?? 'N/A' }} | 
                            {{ $session->semester->title ?? 'N/A' }}
                            @if($session->section)
                             | {{ $session->section->title }}
                            @endif
                        </small>
                        @endif
                        <div class="session-meta">
                            <span class="badge badge-info">
                                {{ \Carbon\Carbon::parse($session->scheduled_start_time)->format('H:i') }} - 
                                {{ \Carbon\Carbon::parse($session->scheduled_end_time)->format('H:i') }}
                            </span>
                            @if($session->status == 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($session->status == 'in_progress')
                                <span class="badge badge-success">In Progress</span>
                            @elseif($session->status == 'completed')
                                <span class="badge badge-secondary">Completed</span>
                            @elseif($session->status == 'cancelled')
                                <span class="badge badge-danger">Cancelled</span>
                            @endif
                        </div>
                    </div>
                    <div class="session-action">
                        @if($session->status == 'pending')
                            @if($isJoint)
                            <button class="btn btn-success btn-sm start-joint-session-btn" 
                                    data-joint-key="{{ $jointKey }}"
                                    data-session-ids="{{ collect($extra_joint_sessions[$jointKey]['sessions'] ?? [])->pluck('id')->implode(',') }}">
                                <i class="fas fa-play mr-1"></i> Start All
                            </button>
                            @else
                            <button class="btn btn-success btn-sm start-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-play mr-1"></i> Start
                            </button>
                            @endif
                        @elseif($session->status == 'in_progress')
                            <button class="btn btn-primary btn-sm select-session-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-qrcode mr-1"></i> Scan
                            </button>
                        @elseif($session->status == 'completed')
                            <button class="btn btn-secondary btn-sm manage-completed-btn" data-id="{{ $session->id }}">
                                <i class="fas fa-edit mr-1"></i> Manage
                            </button>
                        @endif
                    </div>
                </div>
                
                @if($isJoint && $session->status == 'in_progress')
                {{-- Show all joint session scan buttons when in progress --}}
                <div class="joint-sessions-list mt-2 pt-2 border-top">
                    <small class="text-muted d-block mb-2">Scan attendance for each program:</small>
                    <div class="joint-scan-buttons">
                    @foreach($extra_joint_sessions[$jointKey]['sessions'] ?? [] as $jointSession)
                    <button class="btn btn-outline-primary btn-sm select-session-btn" data-id="{{ $jointSession->id }}" title="{{ $jointSession->program->title }}">
                        <i class="fas fa-qrcode"></i> {{ $jointSession->program->shortcode ?? $jointSession->program->title }}
                    </button>
                    @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif
            @endforeach
            @endif

            <!-- Add Extra Class Button -->
            @if($allow_extra_classes)
            <button class="btn kiosk-add-btn btn-block mt-3" id="addExtraClassBtn">
                <i class="fas fa-plus mr-2"></i> Add Extra Class
            </button>
            @endif
            </div>
        </div>

        <!-- Middle Column: Scanner & Stats -->
        <div class="col-lg-5">
            <div class="kiosk-lane lane-attendance">
            <h5 class="kiosk-col-title"><i class="fas fa-qrcode"></i> Attendance
                @if($active_session)
                <span class="kiosk-lane-pill is-live">Live</span>
                @else
                <span class="kiosk-lane-pill">Idle</span>
                @endif
            </h5>
            <!-- Active Session Scanner -->
            <div class="scanner-section" id="scannerSection" style="{{ $active_session ? '' : 'display:none;' }}">
                <div class="text-center mb-3">
                    <h5 id="activeSessionTitle">
                        @if($active_session)
                        {{ $active_session->subject->code ?? '' }} - {{ $active_session->subject->title ?? 'Active Class' }}
                        @else
                        Select a Class to Start
                        @endif
                    </h5>
                    
                    <!-- Session Timing Info -->
                    <div id="activeSessionTime" class="mb-2">
                        @if($active_session)
                        <div class="d-flex justify-content-center align-items-center flex-wrap">
                            <span class="badge badge-info mr-2 mb-1">
                                <i class="fas fa-play-circle"></i> Session: {{ \Carbon\Carbon::parse($active_session->actual_start_time)->format('H:i') }}
                            </span>
                            @if($active_session->first_clock_in_time)
                            <span class="badge badge-success mr-2 mb-1">
                                <i class="fas fa-user-check"></i> First Scan: {{ \Carbon\Carbon::parse($active_session->first_clock_in_time)->format('H:i') }}
                            </span>
                            <span class="badge badge-warning mr-2 mb-1" id="calculatedEndBadge">
                                <i class="fas fa-hourglass-end"></i> Ends: {{ $active_session->calculated_end_time->format('H:i') }}
                            </span>
                            @else
                            <span class="badge badge-secondary mr-2 mb-1">
                                <i class="fas fa-clock"></i> Scheduled: {{ \Carbon\Carbon::parse($active_session->scheduled_start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($active_session->scheduled_end_time)->format('H:i') }}
                            </span>
                            <span class="text-muted small ml-2 mb-1">
                                <i class="fas fa-info-circle"></i> Duration starts when first student scans
                            </span>
                            @endif
                        </div>
                        
                        <!-- Progress Bar -->
                        @if($active_session->first_clock_in_time)
                        <div class="mt-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Progress: <strong id="elapsedMinutes">{{ $active_session->elapsed_minutes }}</strong> / {{ $active_session->scheduled_duration_minutes }} min</span>
                                <span id="remainingTime">
                                    @if($active_session->is_overtime)
                                    <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Overtime: {{ $active_session->overtime_minutes }} min</span>
                                    @else
                                    <span class="text-success"><i class="fas fa-hourglass-half"></i> {{ $active_session->remaining_minutes }} min remaining</span>
                                    @endif
                                </span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar {{ $active_session->is_overtime ? 'bg-danger' : 'bg-success' }}" 
                                     role="progressbar" 
                                     id="sessionProgressBar"
                                     style="width: {{ min(100, $active_session->progress_percentage) }}%"
                                     aria-valuenow="{{ $active_session->progress_percentage }}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>

                <!-- QR Scanner -->
                <div class="kiosk-viewfinder">
                    <span class="vf-corner tl"></span><span class="vf-corner tr"></span><span class="vf-corner bl"></span><span class="vf-corner br"></span>
                    <div id="reader" class="mb-4"></div>
                </div>
                
                <!-- Scanner Controls -->
                <div class="text-center mb-3" id="scannerControls" style="display: none;">
                    <button type="button" class="btn btn-info btn-sm" id="startScannerBtn">
                        <i class="fa fa-camera"></i> Start Scanner
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="stopScannerBtn" style="display: none;">
                        <i class="fa fa-stop"></i> Stop Scanner
                    </button>
                    <div id="scannerStatus" class="text-muted mt-2 small"></div>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-4">
                        <div class="stats-card present">
                            <div class="count" id="presentCount">0</div>
                            <small>Present</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stats-card late">
                            <div class="count" id="lateCount">0</div>
                            <small>Late</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stats-card total">
                            <div class="count" id="totalCount">0</div>
                            <small>Total</small>
                        </div>
                    </div>
                </div>

                <!-- End Session Button -->
                <div class="text-center">
                    <button class="btn btn-end-class text-white" id="endSessionBtn" style="{{ $active_session ? '' : 'display:none;' }}">
                        <i class="fas fa-stop-circle mr-2"></i> End Class Session
                    </button>
                </div>

                <!-- Recent Scans -->
                <div class="mt-4">
                    <h6 class="kiosk-card-title"><i class="fas fa-history"></i> Recent Scans</h6>
                    <div id="recentScans" class="student-list" style="max-height: 200px;">
                        <!-- Populated via JS -->
                    </div>
                </div>
            </div>

            <!-- Placeholder when no session active -->
            <div class="scanner-section text-center" id="noSessionPlaceholder" style="{{ $active_session ? 'display:none;' : '' }}">
                <i class="fas fa-chalkboard fa-5x text-muted mb-4"></i>
                <h4 class="text-muted">No Active Class</h4>
                <p class="text-muted">Start a class from the left panel to begin attendance tracking</p>
            </div>
            </div>
        </div>

        <!-- Right Column: Student List & Logbook -->
        <div class="col-lg-3">
            <div class="kiosk-lane lane-details">
            <h5 class="kiosk-col-title"><i class="fas fa-clipboard-list"></i> Class Details</h5>
            @unless($active_session)
            <div class="logbook-form kiosk-side-placeholder">
                <i class="fas fa-users"></i>
                <p class="mb-0">Students, the logbook and class chat appear here once a class is running.</p>
            </div>
            @endunless
            <!-- Student List -->
            <div class="logbook-form mb-4" id="studentListSection" style="{{ $active_session ? '' : 'display:none;' }}">
                <h6 class="kiosk-card-title"><i class="fas fa-users"></i> Students</h6>
                <div class="student-list" id="studentList">
                    <!-- Populated via JS -->
                </div>
            </div>

            <!-- Logbook Form -->
            <div class="logbook-form" id="logbookSection" style="{{ $active_session ? '' : 'display:none;' }}">
                <h6 class="kiosk-card-title"><i class="fas fa-book"></i> Class Logbook</h6>
                <form id="logbookForm">
                    <input type="hidden" id="logbookSessionId" value="{{ $active_session ? $active_session->id : '' }}">
                    <div class="form-group">
                        <label>Topic Covered</label>
                        <input type="text" class="form-control" name="topic_covered" id="topicCovered" placeholder="Enter topic..." value="{{ $active_session->topic_covered ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>Content Summary</label>
                        <textarea class="form-control" name="content_summary" id="contentSummary" rows="2" placeholder="Brief summary...">{{ $active_session->learning_objectives ?? '' }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Assignments Given</label>
                        <input type="text" class="form-control" name="assignments_given" id="assignmentsGiven" placeholder="Any assignments?" value="{{ $active_session->assignments_given ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Additional remarks...">{{ $active_session->remarks ?? '' }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Class Representative</label>
                        <select class="form-control" id="classRepSelect" data-initial-value="{{ $active_session->class_rep_enroll_id ?? '' }}">
                            <option value="">-- Select (Optional) --</option>
                            <!-- Populated via JS -->
                        </select>
                        <div id="delegateCheckboxContainer" style="{{ ($active_session && $active_session->class_rep_enroll_id) ? '' : 'display: none;' }}">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="delegateLogbook" {{ ($active_session && $active_session->logbook_delegated) ? 'checked' : '' }}>
                                <label class="form-check-label" for="delegateLogbook">
                                    <i class="fas fa-book mr-1"></i> Delegate logbook filling
                                </label>
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="delegateAttendance" {{ ($active_session && $active_session->attendance_delegated) ? 'checked' : '' }}>
                                <label class="form-check-label" for="delegateAttendance">
                                    <i class="fas fa-clipboard-check mr-1"></i> Delegate attendance marking
                                </label>
                            </div>
                            <small class="form-text text-muted mt-1">The class rep can perform these tasks from their student portal</small>
                        </div>
                        <div class="alert alert-info mt-2 py-2" id="delegationStatus" style="{{ ($active_session && ($active_session->logbook_delegated || $active_session->attendance_delegated)) ? '' : 'display: none;' }}">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <span id="delegationStatusText">
                                @if($active_session && $active_session->logbook_delegated && $active_session->attendance_delegated)
                                    Logbook & Attendance delegated to class rep
                                @elseif($active_session && $active_session->logbook_delegated)
                                    Logbook delegated to class rep
                                @elseif($active_session && $active_session->attendance_delegated)
                                    Attendance delegated to class rep
                                @endif
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-block" id="saveLogbookBtn">
                        <i class="fas fa-save mr-2"></i> Save Logbook
                    </button>
                </form>
            </div>

            <!-- Student Activity Section -->
            <div class="activity-section" id="activitySection" style="{{ $active_session ? '' : 'display:none;' }}">
                <h6 class="kiosk-card-title"><i class="fas fa-comments"></i> Student Activity</h6>
                
                <div class="activity-tabs">
                    <button class="activity-tab active" data-tab="chat" onclick="switchActivityTab('chat')">
                        <i class="fas fa-comment-dots"></i> Chat
                        <span class="badge badge-primary" id="chatCount">0</span>
                    </button>
                    <button class="activity-tab" data-tab="questions" onclick="switchActivityTab('questions')">
                        <i class="fas fa-question-circle"></i> Questions
                        <span class="badge badge-info" id="questionsCount">0</span>
                    </button>
                    <button class="activity-tab" data-tab="alerts" onclick="switchActivityTab('alerts')">
                        <i class="fas fa-bell"></i> Alerts
                        <span class="badge badge-warning" id="alertsCount">0</span>
                    </button>
                </div>

                <!-- Chat Tab -->
                <div class="activity-content" id="chatTab">
                    <div id="chatMessagesList">
                        <div class="no-activity">
                            <i class="fas fa-comments"></i>
                            <p>No messages yet</p>
                        </div>
                    </div>
                    <!-- Lecturer Chat Input -->
                    <div class="lecturer-chat-input mt-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="lecturerChatInput" placeholder="Send a message to students..." maxlength="500">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button" id="lecturerSendBtn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Questions Tab -->
                <div class="activity-content" id="questionsTab" style="display: none;">
                    <div id="questionsList">
                        <div class="no-activity">
                            <i class="fas fa-question-circle"></i>
                            <p>No questions yet</p>
                        </div>
                    </div>
                    <!-- Quick Answer Input -->
                    <div class="lecturer-answer-section mt-3" id="answerSection" style="display: none;">
                        <div class="alert alert-info mb-2" id="answeringQuestion"></div>
                        <div class="input-group">
                            <input type="text" class="form-control" id="lecturerAnswerInput" placeholder="Type your answer...">
                            <div class="input-group-append">
                                <button class="btn btn-success" type="button" id="submitAnswerBtn">
                                    <i class="fas fa-check"></i> Answer
                                </button>
                                <button class="btn btn-secondary" type="button" onclick="cancelAnswer()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts Tab -->
                <div class="activity-content" id="alertsTab" style="display: none;">
                    <div id="alertsList">
                        <div class="no-activity">
                            <i class="fas fa-bell"></i>
                            <p>No alerts yet</p>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Extra Class Modal -->
<div class="modal fade kiosk-ui" id="extraClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Extra Class</h5>
                <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="extraClassForm">
                    <div class="form-group">
                        <label>Course <span class="text-danger">*</span></label>
                        <select class="form-control" name="subject_id" id="extraSubject" required>
                            <option value="">-- Select Course --</option>
                            @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" 
                                    data-programs="{{ json_encode($subject->classes->pluck('program_id')->unique()->values()) }}"
                                    data-sessions="{{ json_encode($subject->classes->pluck('session_id')->unique()->values()) }}"
                                    data-semesters="{{ json_encode($subject->classes->pluck('semester_id')->unique()->values()) }}"
                                    data-sections="{{ json_encode($subject->classes->pluck('section_id')->unique()->values()) }}">
                                {{ $subject->code }} - {{ $subject->title }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Program(s) <span class="text-danger">*</span></label>
                        <small class="text-muted d-block mb-2">Select multiple programs to create a joint class</small>
                        <div id="extraProgramCheckboxes" class="program-checkbox-list border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                            @foreach($programs as $program)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input extra-program-checkbox" 
                                       id="extraProgram_{{ $program->id }}" value="{{ $program->id }}">
                                <label class="custom-control-label" for="extraProgram_{{ $program->id }}">
                                    {{ $program->short_form ?? $program->title }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <small class="text-info mt-1 d-block" id="selectedProgramCount">0 program(s) selected</small>
                    </div>
                    <div class="form-group">
                        <label>Academic Year <span class="text-danger">*</span></label>
                        <select class="form-control" name="session_id" id="extraSession" required>
                            <option value="">-- Select Academic Year --</option>
                            @foreach($academic_sessions as $academic_session)
                            <option value="{{ $academic_session->id }}">{{ $academic_session->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('common.inc.semester_year_field', ['year_target' => '#extraSemester'])
                    <div class="form-group">
                        <label>Semester <span class="text-danger">*</span></label>
                        <select class="form-control" name="semester_id" id="extraSemester" required>
                            <option value="">-- Select Semester --</option>
                            @foreach($semesters as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select class="form-control" name="section_id" id="extraSection">
                            <option value="">-- All Sections --</option>
                            @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" id="extraStartTime" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" id="extraEndTime" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="createExtraClassBtn">Create Class</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Completed Session Modal -->
<div class="modal fade kiosk-ui" id="manageCompletedModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Manage Completed Session</h5>
                <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="completedSessionInfo" class="mb-3">
                    <h6 id="completedSessionTitle">Loading...</h6>
                    <small class="text-muted" id="completedSessionTime"></small>
                </div>
                
                <!-- QR Scanner for Clock Out -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-qrcode mr-2"></i>Student Clock Out Scanner</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Students can scan their ID card QR code to clock out.</p>
                        <div id="completedSessionReader" style="width: 100%; max-width: 400px; margin: 0 auto;"></div>
                        <div class="text-center mt-2">
                            <button type="button" class="btn btn-success btn-sm" id="startClockOutScannerBtn">
                                <i class="fas fa-play mr-1"></i> Start Scanner
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="stopClockOutScannerBtn" style="display:none;">
                                <i class="fas fa-stop mr-1"></i> Stop Scanner
                            </button>
                        </div>
                        <div id="clockOutScanResult" class="text-center mt-2"></div>
                    </div>
                </div>

                <!-- Student Attendance List -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-users mr-2"></i>Student Attendance Status</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Matricule</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="completedStudentList">
                                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Students must scan their ID card QR code to clock out.
                        </small>
                    </div>
                </div>

                <!-- Logbook Section -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-book mr-2"></i>Class Logbook</h6>
                    </div>
                    <div class="card-body">
                        <form id="completedLogbookForm">
                            <input type="hidden" name="session_id" id="completedSessionId">
                            <div class="form-group">
                                <label>Topic Covered</label>
                                <input type="text" class="form-control" name="topic_covered" id="completedTopicCovered">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Learning Objectives</label>
                                        <textarea class="form-control" name="learning_objectives" id="completedLearningObjectives" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Teaching Methods</label>
                                        <textarea class="form-control" name="teaching_methods" id="completedTeachingMethods" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Remarks</label>
                                <textarea class="form-control" name="remarks" id="completedRemarks" rows="2"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-primary btn-sm" id="saveCompletedLogbookBtn">
                            <i class="fas fa-save mr-1"></i> Save Logbook
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('dashboard/js/html5-qrcode.min.js') }}"></script>
<script>
(function() {
    'use strict';
    
    // State
    let activeSessionId = {{ $active_session ? $active_session->id : 'null' }};
    let html5QrCode = null;
    let isScanning = false;
    let lastScannedCode = '';
    let lastScanTime = 0;
    const SCAN_COOLDOWN = {{ $settings['scan_cooldown_seconds'] ?? 60 }} * 1000;
    
    // Update clock
    function updateClock() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-GB');
    }
    setInterval(updateClock, 1000);
    
    // Initialize QR Scanner
    function initScanner() {
        if (html5QrCode) {
            return;
        }
        
        // Show scanner controls
        document.getElementById('scannerControls').style.display = 'block';
        document.getElementById('scannerStatus').textContent = 'Initializing scanner...';
        
        try {
            html5QrCode = new Html5Qrcode("reader");
            
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                isScanning = true;
                document.getElementById('startScannerBtn').style.display = 'none';
                document.getElementById('stopScannerBtn').style.display = 'inline-block';
                document.getElementById('scannerStatus').textContent = 'Scanner active - point at student QR code';
            }).catch(err => {
                console.error("Camera error:", err);
                document.getElementById('scannerStatus').textContent = 'Camera failed - click Start Scanner to retry';
                document.getElementById('startScannerBtn').style.display = 'inline-block';
                Swal.fire('Camera Error', 'Unable to access camera. Please check permissions. Error: ' + err, 'error');
            });
        } catch (e) {
            console.error("Scanner init error:", e);
            document.getElementById('scannerStatus').textContent = 'Scanner library error - refresh page';
            Swal.fire('Scanner Error', 'Failed to initialize scanner: ' + e.message, 'error');
        }
    }
    
    // Start Scanner (manual button)
    function startScanner() {
        if (!activeSessionId) {
            Swal.fire('Error', 'Please start a session first', 'warning');
            return;
        }
        
        if (html5QrCode && isScanning) {
            return;
        }
        
        // Reset and reinitialize
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                html5QrCode = null;
                initScanner();
            }).catch(() => {
                html5QrCode = null;
                initScanner();
            });
        } else {
            initScanner();
        }
    }
    
    // Stop Scanner
    function stopScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                document.getElementById('startScannerBtn').style.display = 'inline-block';
                document.getElementById('stopScannerBtn').style.display = 'none';
                document.getElementById('scannerStatus').textContent = 'Scanner stopped';
            }).catch(err => console.error("Stop error:", err));
        }
    }
    
    // Attach scanner button event listeners
    document.getElementById('startScannerBtn')?.addEventListener('click', startScanner);
    document.getElementById('stopScannerBtn')?.addEventListener('click', stopScanner);
    
    // Handle successful scan
    function onScanSuccess(decodedText) {
        const now = Date.now();
        
        // Debounce
        if (decodedText === lastScannedCode && (now - lastScanTime) < SCAN_COOLDOWN) {
            return;
        }
        
        lastScannedCode = decodedText;
        lastScanTime = now;
        
        // Play beep
        playBeep();
        
        // Send to server
        processScan(decodedText);
    }
    
    function onScanError(errorMessage) {
        // Ignore scan errors (no QR found)
    }
    
    // Process scan via AJAX
    function processScan(studentId) {
        if (!activeSessionId) {
            Swal.fire('Error', 'No active session', 'error');
            return;
        }
        
        fetch('{{ route("admin.class-session.scan") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                student_id: studentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const programInfo = data.program ? `<br><small class="text-info">${data.program}</small>` : '';
                Swal.fire({
                    icon: 'success',
                    title: data.action === 'clock_in' ? 'Clock In' : 'Clock Out',
                    html: `<strong>${data.student}</strong><br>${data.matricule}${programInfo}<br>${data.time}`,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Add to recent scans
                addRecentScan(data);
                
                // Update stats
                if (data.stats) {
                    document.getElementById('presentCount').textContent = data.stats.present;
                    if (data.stats.total) {
                        document.getElementById('totalCount').textContent = data.stats.total;
                    }
                }
                
                // Initialize session timer if this was the first clock-in
                if (data.action === 'clock_in' && data.is_first_clock_in && data.first_clock_in_time) {
                    initializeSessionTimer(data.first_clock_in_time, data.calculated_end_time, data.scheduled_duration_minutes);
                }
                
                // Refresh student list
                loadStudentList();
            } else if (data.status === 'pending_approval') {
                // Early clock-out - needs lecturer approval
                showEarlyClockOutModal(data);
            } else if (data.status === 'warning') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Notice',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Scan error:', error);
            Swal.fire('Error', 'Failed to process scan', 'error');
        });
    }
    
    // Show early clock-out confirmation modal
    function showEarlyClockOutModal(data) {
        Swal.fire({
            title: 'Early Clock-Out Request',
            html: `
                <div class="text-left">
                    <p><strong>${data.student}</strong> (${data.matricule}) is trying to clock out.</p>
                    <p class="text-warning"><i class="fa fa-exclamation-triangle"></i> Session has not reached scheduled end time.</p>
                    <p><strong>Remaining time:</strong> ${data.remaining_minutes} minutes<br>
                    <strong>Scheduled end:</strong> ${data.scheduled_end_time}</p>
                    <hr>
                    <p class="mb-0"><strong>Choose an action:</strong></p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fa fa-sign-out-alt"></i> Give Leave Permission',
            denyButtonText: '<i class="fa fa-stop-circle"></i> End Class Now',
            cancelButtonText: '<i class="fa fa-times"></i> Deny Request',
            confirmButtonColor: '#17a2b8',
            denyButtonColor: '#dc3545',
            reverseButtons: false,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Give leave permission - student leaves, class continues
                approveEarlyClockOut(data.attendance_id, 'leave_permission');
            } else if (result.isDenied) {
                // End class - ends for everyone
                Swal.fire({
                    title: 'End Class for Everyone?',
                    text: 'This will end the class session for all students. Are you sure?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, End Class',
                    confirmButtonColor: '#dc3545'
                }).then((confirmResult) => {
                    if (confirmResult.isConfirmed) {
                        approveEarlyClockOut(data.attendance_id, 'end_class');
                    }
                });
            } else {
                // Cancelled - denied, show notification
                Swal.fire({
                    icon: 'info',
                    title: 'Request Denied',
                    text: `${data.student} must remain until ${data.scheduled_end_time}`,
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });
    }
    
    // Approve early clock-out
    function approveEarlyClockOut(attendanceId, approvalType) {
        fetch('{{ route("admin.class-session.approve-early-clockout") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                attendance_id: attendanceId,
                approval_type: approvalType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: approvalType === 'end_class' ? 'Class Ended' : 'Leave Granted',
                    html: data.message,
                    timer: 3000,
                    showConfirmButton: false
                });
                
                // Add to recent scans
                addRecentScan(data);
                
                // Update stats
                if (data.stats) {
                    document.getElementById('presentCount').textContent = data.stats.present;
                }
                
                // Refresh student list
                loadStudentList();
                
                // If session ended, refresh page after delay
                if (data.session_ended) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Approve error:', error);
            Swal.fire('Error', 'Failed to process approval', 'error');
        });
    }
    
    // Add to recent scans display
    function addRecentScan(data) {
        const container = document.getElementById('recentScans');
        const item = document.createElement('div');
        item.className = 'student-item recent-scan ' + (data.action === 'clock_in' ? 'clocked-in' : 'clocked-out');
        item.innerHTML = `
            <div>
                <strong>${data.student}</strong><br>
                <small>${data.matricule}</small>
            </div>
            <div class="text-right">
                <span class="badge ${data.action === 'clock_in' ? 'badge-success' : 'badge-secondary'}">
                    ${data.action === 'clock_in' ? 'IN' : 'OUT'}
                </span><br>
                <small>${data.time}</small>
            </div>
        `;
        container.insertBefore(item, container.firstChild);
        
        // Keep only last 10
        while (container.children.length > 10) {
            container.removeChild(container.lastChild);
        }
    }
    
    // Play beep sound
    function playBeep() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.3;
            oscillator.start();
            setTimeout(() => oscillator.stop(), 150);
        } catch(e) {
            console.log('Audio not available');
        }
    }
    
    // Start session
    document.querySelectorAll('.start-session-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sessionId = this.dataset.id;
            
            Swal.fire({
                title: 'Start Class?',
                text: 'This will begin attendance tracking for this class.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Start',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    startSession(sessionId);
                }
            });
        });
    });
    
    function startSession(sessionId) {
        fetch('{{ route("admin.class-session.start") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ session_id: sessionId })
        })
        .then(response => {
            console.log('Start session response status:', response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    try {
                        const json = JSON.parse(text);
                        throw new Error(json.message || 'Server error: ' + response.status);
                    } catch(e) {
                        throw new Error('Server error: ' + response.status + ' - ' + text.substring(0, 200));
                    }
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Class Started!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Unknown error occurred', 'error');
            }
        })
        .catch(error => {
            console.error('Start error:', error);
            Swal.fire('Error', error.message || 'Failed to start session', 'error');
        });
    }
    
    // Start all joint sessions at once
    document.querySelectorAll('.start-joint-session-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sessionIds = this.dataset.sessionIds.split(',');
            const jointKey = this.dataset.jointKey;
            const programCount = sessionIds.length;
            
            Swal.fire({
                title: 'Start Joint Class?',
                html: `This will begin attendance tracking for <strong>${programCount} programs</strong> at once.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Yes, Start All ${programCount}`,
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    startJointSessions(sessionIds);
                }
            });
        });
    });
    
    async function startJointSessions(sessionIds) {
        Swal.fire({
            title: 'Starting Joint Class...',
            html: 'Starting sessions for all programs...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        let successCount = 0;
        let errorCount = 0;
        
        for (const sessionId of sessionIds) {
            try {
                const response = await fetch('{{ route("admin.class-session.start") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ session_id: sessionId })
                });
                
                const data = await response.json();
                if (data.status === 'success') {
                    successCount++;
                } else {
                    errorCount++;
                }
            } catch (error) {
                console.error('Error starting session ' + sessionId, error);
                errorCount++;
            }
        }
        
        if (errorCount === 0) {
            Swal.fire({
                icon: 'success',
                title: 'Joint Class Started!',
                text: `All ${successCount} program sessions started successfully.`,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Partially Started',
                text: `${successCount} started, ${errorCount} failed.`,
            }).then(() => {
                window.location.reload();
            });
        }
    }

    // End session
    document.getElementById('endSessionBtn')?.addEventListener('click', function() {
        Swal.fire({
            title: 'End Class?',
            text: 'This will complete the class session and sync attendance.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, End Class',
            cancelButtonText: 'Cancel'
        }).then(result => {
            if (result.isConfirmed) {
                endSession();
            }
        });
    });
    
    function endSession() {
        fetch('{{ route("admin.class-session.end") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ session_id: activeSessionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Class Ended!',
                    html: `Duration: ${data.session.duration_minutes} minutes<br>Present: ${data.session.present_count} students`,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('End error:', error);
            Swal.fire('Error', 'Failed to end session', 'error');
        });
    }
    
    // Load student list
    function loadStudentList() {
        if (!activeSessionId) return;
        
        fetch(`{{ url('admin/class-session') }}/${activeSessionId}/students`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const container = document.getElementById('studentList');
                const classRepSelect = document.getElementById('classRepSelect');
                const savedClassRepId = classRepSelect.value; // Save current selection before clearing
                container.innerHTML = '';
                classRepSelect.innerHTML = '<option value="">-- Select (Optional) --</option>';
                
                const isJointClass = data.is_joint_class || false;
                
                // Group by program if joint class
                let studentsByProgram = {};
                if (isJointClass) {
                    data.students.forEach(student => {
                        const prog = student.program || 'Unknown';
                        if (!studentsByProgram[prog]) {
                            studentsByProgram[prog] = [];
                        }
                        studentsByProgram[prog].push(student);
                    });
                }
                
                if (isJointClass) {
                    // Show grouped by program
                    Object.keys(studentsByProgram).sort().forEach(program => {
                        const programHeader = document.createElement('div');
                        programHeader.className = 'program-header mt-2 mb-1';
                        programHeader.innerHTML = `<strong class="text-info"><i class="fas fa-users"></i> ${program}</strong> <span class="badge badge-light">${studentsByProgram[program].length}</span>`;
                        container.appendChild(programHeader);
                        
                        studentsByProgram[program].forEach(student => {
                            container.appendChild(createStudentItem(student, false));
                            addClassRepOption(classRepSelect, student);
                        });
                    });
                } else {
                    // Single program
                    data.students.forEach(student => {
                        container.appendChild(createStudentItem(student, false));
                        addClassRepOption(classRepSelect, student);
                    });
                }
                
                // Restore class rep selection if previously selected or from session data
                // Restore class rep selection - prioritize: current selection > API data > initial Blade value
                const initialClassRepId = classRepSelect.getAttribute('data-initial-value');
                const classRepToSelect = savedClassRepId || data.class_rep_enroll_id || initialClassRepId;
                if (classRepToSelect) {
                    classRepSelect.value = classRepToSelect;
                    // Show delegate checkbox if class rep is selected
                    document.getElementById('delegateCheckboxContainer').style.display = 'block';
                    
                    // Restore delegation checkbox states from API
                    if (data.logbook_delegated) {
                        document.getElementById('delegateLogbook').checked = true;
                    }
                    if (data.attendance_delegated) {
                        document.getElementById('delegateAttendance').checked = true;
                    }
                    updateDelegationStatus();
                }
                
                // Update counts
                document.getElementById('totalCount').textContent = data.students.length;
                document.getElementById('presentCount').textContent = data.students.filter(s => s.clock_in_time).length;
                document.getElementById('lateCount').textContent = data.students.filter(s => s.is_late).length;
                
                // Show joint class indicator if applicable
                const titleEl = document.getElementById('activeSessionTitle');
                if (isJointClass && titleEl && data.program_count) {
                    const jointBadge = titleEl.querySelector('.joint-badge');
                    if (!jointBadge) {
                        titleEl.innerHTML += ` <span class="joint-badge badge badge-info"><i class="fas fa-users"></i> ${data.program_count} Programs</span>`;
                    }
                }
            }
        })
        .catch(error => console.error('Error loading students:', error));
    }
    
    function createStudentItem(student, showProgram = true) {
        const item = document.createElement('div');
        let statusClass = '';
        if (student.has_clocked_out) statusClass = 'clocked-out';
        else if (student.clock_in_time) statusClass = 'clocked-in';
        if (student.is_late) statusClass += ' late';
        
        item.className = 'student-item ' + statusClass;
        item.innerHTML = `
            <div>
                <strong>${escapeText(student.name)}</strong><br>
                <small>${escapeText(student.matricule)}</small>
                ${student.portal_blocked_by_fees ? `<br><small class="text-warning" title="This student cannot open the Class Hub until their fees are settled. It does not stop you marking them present."><i class="fas fa-lock"></i> Portal locked (fees)</small>` : ''}
                ${showProgram && student.program ? `<br><small class="text-info">${student.program}</small>` : ''}
            </div>
            <div class="text-right">
                <span class="badge ${student.status_label === 'Present' ? 'badge-success' : 
                                  student.status_label === 'Late' ? 'badge-warning' : 'badge-secondary'}">
                    ${student.status_label}
                </span>
                ${student.clock_in_time ? `<br><small>In: ${student.clock_in_time}</small>` : ''}
                ${student.clock_out_time ? `<br><small>Out: ${student.clock_out_time}</small>` : ''}
            </div>
        `;
        return item;
    }
    
    function addClassRepOption(select, student) {
        const option = document.createElement('option');
        option.value = student.enroll_id;
        option.textContent = `${student.matricule} - ${student.name}${student.program ? ' (' + student.program + ')' : ''}`;
        select.appendChild(option);
    }
    
    // Save logbook
    document.getElementById('saveLogbookBtn')?.addEventListener('click', function() {
        const formData = {
            session_id: activeSessionId,
            topic_covered: document.getElementById('topicCovered').value,
            content_summary: document.getElementById('contentSummary').value,
            assignments_given: document.getElementById('assignmentsGiven').value,
            remarks: document.getElementById('remarks').value
        };
        
        fetch('{{ route("admin.class-session.update-logbook") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: 'Logbook updated successfully',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Save logbook error:', error);
            Swal.fire('Error', 'Failed to save logbook', 'error');
        });
    });
    
    // Update class rep
    document.getElementById('classRepSelect')?.addEventListener('change', function() {
        if (!activeSessionId) return;
        
        const hasClassRep = this.value ? true : false;
        const delegateCheckbox = document.getElementById('delegateLogbook');
        const delegateContainer = document.getElementById('delegateCheckboxContainer');
        
        // Show/hide delegate checkbox based on class rep selection
        if (hasClassRep) {
            delegateContainer.style.display = 'block';
        } else {
            delegateContainer.style.display = 'none';
            delegateCheckbox.checked = false;
            // Also clear delegation if class rep is removed
            toggleLogbookDelegation();
        }
        
        fetch('{{ route("admin.class-session.update-class-rep") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                class_rep_enroll_id: this.value || null
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Silently updated
            }
        })
        .catch(error => console.error('Update class rep error:', error));
    });
    
    // Toggle logbook delegation to class rep - attach event listener
    document.getElementById('delegateLogbook')?.addEventListener('change', function() {
        if (!activeSessionId) return;
        
        const delegateCheckbox = document.getElementById('delegateLogbook');
        const delegationStatus = document.getElementById('delegationStatus');
        const delegationStatusText = document.getElementById('delegationStatusText');
        const classRepSelect = document.getElementById('classRepSelect');
        const isDelegated = delegateCheckbox.checked;
        
        fetch('{{ route("admin.class-session.toggle-delegation") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                delegated: isDelegated
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const selectedOption = classRepSelect.options[classRepSelect.selectedIndex];
                if (isDelegated) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Logbook Delegated',
                        html: `<strong>${selectedOption.text}</strong> can now fill the logbook from their student portal.`,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
                updateDelegationStatus();
            } else {
                Swal.fire('Error', data.message, 'error');
                delegateCheckbox.checked = !isDelegated;
            }
        })
        .catch(error => {
            console.error('Toggle delegation error:', error);
            delegateCheckbox.checked = !isDelegated;
            Swal.fire('Error', 'Failed to update delegation', 'error');
        });
    });
    
    // Toggle attendance delegation to class rep
    document.getElementById('delegateAttendance')?.addEventListener('change', function() {
        if (!activeSessionId) return;
        
        const delegateCheckbox = document.getElementById('delegateAttendance');
        const classRepSelect = document.getElementById('classRepSelect');
        const isDelegated = delegateCheckbox.checked;
        
        fetch('{{ route("admin.class-session.toggle-delegation") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                type: 'attendance',
                delegated: isDelegated
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                updateDelegationStatus();
                
                if (isDelegated) {
                    const selectedOption = classRepSelect.options[classRepSelect.selectedIndex];
                    Swal.fire({
                        icon: 'success',
                        title: 'Attendance Delegated',
                        html: `<strong>${selectedOption.text}</strong> can now mark attendance from their student portal.`,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            } else {
                Swal.fire('Error', data.message, 'error');
                delegateCheckbox.checked = !isDelegated;
            }
        })
        .catch(error => {
            console.error('Toggle attendance delegation error:', error);
            delegateCheckbox.checked = !isDelegated;
            Swal.fire('Error', 'Failed to update delegation', 'error');
        });
    });
    
    // Update delegation status text
    function updateDelegationStatus() {
        const logbookDelegated = document.getElementById('delegateLogbook')?.checked;
        const attendanceDelegated = document.getElementById('delegateAttendance')?.checked;
        const delegationStatus = document.getElementById('delegationStatus');
        const delegationStatusText = document.getElementById('delegationStatusText');
        const classRepSelect = document.getElementById('classRepSelect');
        const selectedOption = classRepSelect.options[classRepSelect.selectedIndex];
        const repName = selectedOption?.text || 'class rep';
        
        if (logbookDelegated && attendanceDelegated) {
            delegationStatusText.textContent = `Logbook & Attendance delegated to ${repName}`;
            delegationStatus.style.display = 'block';
        } else if (logbookDelegated) {
            delegationStatusText.textContent = `Logbook delegated to ${repName}`;
            delegationStatus.style.display = 'block';
        } else if (attendanceDelegated) {
            delegationStatusText.textContent = `Attendance delegated to ${repName}`;
            delegationStatus.style.display = 'block';
        } else {
            delegationStatus.style.display = 'none';
        }
    }
    
    // Add extra class modal
    document.getElementById('addExtraClassBtn')?.addEventListener('click', function() {
        // Reset form and checkboxes
        document.getElementById('extraClassForm').reset();
        $('#extraSubject').trigger('change.select2');
        document.querySelectorAll('.extra-program-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.custom-control').style.display = 'block';
        });
        updateSelectedProgramCount();
        $('#extraClassModal').modal('show');
    });
    
    // Update selected program count
    function updateSelectedProgramCount() {
        const count = document.querySelectorAll('.extra-program-checkbox:checked').length;
        const countEl = document.getElementById('selectedProgramCount');
        if (countEl) {
            countEl.textContent = count + ' program(s) selected';
            countEl.className = count > 0 ? 'text-success mt-1 d-block' : 'text-info mt-1 d-block';
        }
    }
    
    // Handle program checkbox changes
    document.querySelectorAll('.extra-program-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedProgramCount);
    });
    
    // Searchable course picker. It sits in a modal, so select2 must attach its
    // dropdown there or the search box cannot take focus. The listeners on it
    // are native, and never hear the jQuery change select2 fires, so a pick
    // is passed on as a native change.
    $('#extraSubject').select2({ dropdownParent: $('#extraClassModal'), width: '100%' })
        .on('select2:select select2:clear', function () {
            this.dispatchEvent(new Event('change'));
        });

    // Filter programs when course is selected
    document.getElementById('extraSubject')?.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const validProgramIds = selected.dataset.programs ? JSON.parse(selected.dataset.programs) : [];
        
        // Show/hide program checkboxes based on course assignment
        document.querySelectorAll('.extra-program-checkbox').forEach(cb => {
            const programId = parseInt(cb.value);
            const wrapper = cb.closest('.custom-control');
            if (validProgramIds.length === 0 || validProgramIds.includes(programId)) {
                wrapper.style.display = 'block';
            } else {
                wrapper.style.display = 'none';
                cb.checked = false;
            }
        });
        updateSelectedProgramCount();
    });
    
    document.getElementById('createExtraClassBtn')?.addEventListener('click', function() {
        const form = document.getElementById('extraClassForm');
        
        // Get selected program IDs
        const selectedPrograms = Array.from(document.querySelectorAll('.extra-program-checkbox:checked'))
            .map(cb => cb.value);
        
        if (selectedPrograms.length === 0) {
            Swal.fire('Error', 'Please select at least one program', 'error');
            return;
        }
        
        const formData = {
            subject_id: document.getElementById('extraSubject').value,
            program_ids: selectedPrograms,
            session_id: document.getElementById('extraSession').value,
            semester_id: document.getElementById('extraSemester').value,
            section_id: document.getElementById('extraSection').value || null,
            start_time: document.getElementById('extraStartTime').value,
            end_time: document.getElementById('extraEndTime').value
        };
        
        // Validate required fields
        if (!formData.subject_id || !formData.session_id || !formData.semester_id || !formData.start_time || !formData.end_time) {
            Swal.fire('Error', 'Please fill in all required fields', 'error');
            return;
        }
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
        
        fetch('{{ route("admin.class-session.create-extra") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat().join('\n');
                        throw new Error(errorMessages);
                    }
                    throw new Error(data.message || 'Failed to create extra class');
                }
                return data;
            });
        })
        .then(data => {
            if (data.status === 'success') {
                const sessionCount = data.sessions_created || selectedPrograms.length;
                Swal.fire({
                    icon: 'success',
                    title: sessionCount > 1 ? `${sessionCount} Joint Classes Created!` : 'Extra Class Created!',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                btn.disabled = false;
                btn.innerHTML = 'Create Class';
                Swal.fire('Error', data.message || 'Failed to create extra class', 'error');
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = 'Create Class';
            console.error('Create extra class error:', error);
            Swal.fire('Error', error.message || 'Failed to create extra class', 'error');
        });
    });
    
    // Auto-refresh stats every 30 seconds
    function refreshStats() {
        if (!activeSessionId) return;
        
        fetch(`{{ url('admin/class-session') }}/${activeSessionId}/stats`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('presentCount').textContent = data.stats.present;
                document.getElementById('lateCount').textContent = data.stats.late;
                document.getElementById('totalCount').textContent = data.stats.total;
            }
        })
        .catch(error => console.error('Refresh stats error:', error));
    }
    setInterval(refreshStats, 30000);
    
    // Session timer/progress tracking
    let sessionTimerData = {
        firstClockInTime: null,
        scheduledDurationMinutes: {{ $active_session ? $active_session->scheduled_duration_minutes : 0 }},
        calculatedEndTime: null,
    };
    
    @if($active_session && $active_session->first_clock_in_time)
    sessionTimerData.firstClockInTime = new Date('{{ $active_session->first_clock_in_time->toISOString() }}');
    sessionTimerData.calculatedEndTime = new Date('{{ $active_session->calculated_end_time->toISOString() }}');
    @endif
    
    function updateSessionProgress() {
        if (!sessionTimerData.firstClockInTime || !activeSessionId) return;
        
        const now = new Date();
        const elapsed = Math.floor((now - sessionTimerData.firstClockInTime) / 60000); // minutes
        const scheduledDuration = sessionTimerData.scheduledDurationMinutes;
        const remaining = scheduledDuration - elapsed;
        const progressPercent = Math.min(100, (elapsed / scheduledDuration) * 100);
        const isOvertime = remaining < 0;
        
        // Update elapsed minutes display
        const elapsedEl = document.getElementById('elapsedMinutes');
        if (elapsedEl) elapsedEl.textContent = elapsed;
        
        // Update progress bar
        const progressBar = document.getElementById('sessionProgressBar');
        if (progressBar) {
            progressBar.style.width = `${Math.min(100, progressPercent)}%`;
            progressBar.className = `progress-bar ${isOvertime ? 'bg-danger' : 'bg-success'}`;
        }
        
        // Update remaining time display
        const remainingEl = document.getElementById('remainingTime');
        if (remainingEl) {
            if (isOvertime) {
                remainingEl.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Overtime: ${Math.abs(remaining)} min</span>`;
            } else {
                remainingEl.innerHTML = `<span class="text-success"><i class="fas fa-hourglass-half"></i> ${remaining} min remaining</span>`;
            }
        }
        
        // Update calculated end badge color if overtime
        const endBadge = document.getElementById('calculatedEndBadge');
        if (endBadge && isOvertime) {
            endBadge.className = 'badge badge-danger mr-2 mb-1';
        }
    }
    
    // Initialize session timer when first student clocks in
    function initializeSessionTimer(firstClockInTime, calculatedEndTime, scheduledDurationMinutes) {
        sessionTimerData.firstClockInTime = new Date(firstClockInTime);
        sessionTimerData.calculatedEndTime = new Date(calculatedEndTime);
        sessionTimerData.scheduledDurationMinutes = scheduledDurationMinutes;
        
        // Update the UI to show the timer info
        const activeSessionTimeEl = document.getElementById('activeSessionTime');
        if (activeSessionTimeEl) {
            const firstClockInFormatted = sessionTimerData.firstClockInTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
            const calculatedEndFormatted = sessionTimerData.calculatedEndTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
            
            activeSessionTimeEl.innerHTML = `
                <div class="d-flex justify-content-center align-items-center flex-wrap">
                    <span class="badge badge-info mr-2 mb-1">
                        <i class="fas fa-play-circle"></i> Session Started
                    </span>
                    <span class="badge badge-success mr-2 mb-1">
                        <i class="fas fa-user-check"></i> First Scan: ${firstClockInFormatted}
                    </span>
                    <span class="badge badge-warning mr-2 mb-1" id="calculatedEndBadge">
                        <i class="fas fa-hourglass-end"></i> Ends: ${calculatedEndFormatted}
                    </span>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Progress: <strong id="elapsedMinutes">0</strong> / ${scheduledDurationMinutes} min</span>
                        <span id="remainingTime">
                            <span class="text-success"><i class="fas fa-hourglass-half"></i> ${scheduledDurationMinutes} min remaining</span>
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" id="sessionProgressBar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            `;
        }
        
        // Start the progress updater
        updateSessionProgress();
        setInterval(updateSessionProgress, 60000);
    }
    
    // Update session progress every minute
    if (sessionTimerData.firstClockInTime) {
        updateSessionProgress(); // Initial update
        setInterval(updateSessionProgress, 60000); // Update every minute
    }
    
    // Store all dropdown options for filtering
    const allPrograms = @json($programs);
    const allSessions = @json($academic_sessions);
    const allSemesters = @json($semesters);
    const allSections = @json($sections);
    
    // Filter dropdowns when subject changes
    document.getElementById('extraSubject')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            // Reset all dropdowns
            resetDropdown('extraProgram', allPrograms, 'id', 'title');
            resetDropdown('extraSession', allSessions, 'id', 'title');
            setExtraSemesters(allSemesters);
            resetDropdown('extraSection', allSections, 'id', 'title');
            return;
        }
        
        // Get allowed IDs from data attributes
        const allowedPrograms = JSON.parse(selectedOption.dataset.programs || '[]');
        const allowedSessions = JSON.parse(selectedOption.dataset.sessions || '[]');
        const allowedSemesters = JSON.parse(selectedOption.dataset.semesters || '[]');
        const allowedSections = JSON.parse(selectedOption.dataset.sections || '[]');
        
        // Filter and repopulate dropdowns
        filterDropdown('extraProgram', allPrograms, allowedPrograms, 'id', 'title', 'short_form');
        filterDropdown('extraSession', allSessions, allowedSessions, 'id', 'title');
        setExtraSemesters(allSemesters.filter(item => allowedSemesters.includes(item.id)));
        filterDropdown('extraSection', allSections, allowedSections, 'id', 'title');
    });
    
    // #extraSemester is fronted by the shared Year select
    // (common/inc/semester_year_field), which publishes a handle on the
    // semester select. Every repopulation of #extraSemester must go through
    // that handle: writing <option> into the select directly fills the
    // semesters while leaving the required Year select empty, and the two
    // controls then contradict each other. The fallback keeps the modal
    // usable if the module has not loaded.
    function setExtraSemesters(rows) {
        const handle = window.jQuery && window.jQuery('#extraSemester').data('semesterYearHandle');
        if (handle) {
            handle.setLookup(rows);
            return;
        }
        resetDropdown('extraSemester', rows, 'id', 'title');
    }

    function filterDropdown(selectId, allItems, allowedIds, valueKey, textKey, altTextKey) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        const currentValue = select.value;
        select.innerHTML = '<option value="">-- Select --</option>';
        
        allItems.forEach(item => {
            if (allowedIds.includes(item[valueKey])) {
                const option = document.createElement('option');
                option.value = item[valueKey];
                option.textContent = (altTextKey && item[altTextKey]) ? item[altTextKey] : item[textKey];
                if (item[valueKey] == currentValue) option.selected = true;
                select.appendChild(option);
            }
        });
        
        // Auto-select if only one option
        if (select.options.length === 2) {
            select.selectedIndex = 1;
        }
    }
    
    function resetDropdown(selectId, allItems, valueKey, textKey, altTextKey) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        select.innerHTML = '<option value="">-- Select --</option>';
        allItems.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = (altTextKey && item[altTextKey]) ? item[altTextKey] : item[textKey];
            select.appendChild(option);
        });
    }
    
    // ========== MANAGE COMPLETED SESSION HANDLERS ==========
    let managingSessionId = null;
    
    // Handle Manage Completed Session button click
    document.addEventListener('click', function(e) {
        if (e.target.closest('.manage-completed-btn')) {
            const btn = e.target.closest('.manage-completed-btn');
            managingSessionId = btn.dataset.id;
            loadCompletedSessionData(managingSessionId);
            $('#manageCompletedModal').modal('show');
        }
    });
    
    // Load completed session data
    function loadCompletedSessionData(sessionId) {
        fetch(`{{ url('admin/class-session') }}/${sessionId}/details`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const session = data.session;
                document.getElementById('completedSessionId').value = session.id;
                
                // Build title with joint class indicator
                let titleHtml = (session.subject?.code || '') + ' - ' + (session.subject?.title || 'Class Session');
                if (data.is_joint_class && data.program_count) {
                    titleHtml += ` <span class="badge badge-info ml-2"><i class="fas fa-users"></i> ${data.program_count} Programs</span>`;
                }
                document.getElementById('completedSessionTitle').innerHTML = titleHtml;
                document.getElementById('completedSessionTime').textContent = 
                    'Duration: ' + (session.actual_start_time || 'N/A') + ' - ' + (session.actual_end_time || 'N/A');
                
                // Populate logbook fields
                document.getElementById('completedTopicCovered').value = session.topic_covered || '';
                document.getElementById('completedLearningObjectives').value = session.learning_objectives || '';
                document.getElementById('completedTeachingMethods').value = session.teaching_methods || '';
                document.getElementById('completedRemarks').value = session.remarks || '';
                
                // Populate student list
                renderCompletedStudentList(data.students);
            }
        })
        .catch(err => {
            console.error('Error loading session data:', err);
            Swal.fire('Error', 'Failed to load session data', 'error');
        });
    }
    
    // Render student list in modal
    function renderCompletedStudentList(students) {
        const tbody = document.getElementById('completedStudentList');
        if (!students || students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No students found</td></tr>';
            return;
        }
        
        tbody.innerHTML = students.map(student => `
            <tr data-attendance-id="${student.id}" data-matricule="${student.matricule}">
                <td>
                    ${student.student_name || 'Unknown'}
                    ${student.program ? `<br><small class="badge badge-info">${student.program}</small>` : ''}
                </td>
                <td><code>${student.matricule || 'N/A'}</code></td>
                <td>${student.clock_in_time || '<span class="text-danger">-</span>'}</td>
                <td>${student.clock_out_time || '<span class="text-warning">Pending</span>'}</td>
                <td>
                    ${student.clock_out_time ? 
                        '<span class="badge badge-success"><i class="fas fa-check"></i> Complete</span>' : 
                        (student.clock_in_time ? 
                            '<span class="badge badge-warning"><i class="fas fa-clock"></i> Awaiting Scan</span>' : 
                            '<span class="badge badge-secondary">-</span>')
                    }
                </td>
            </tr>
        `).join('');
    }
    
    // ========== COMPLETED SESSION QR SCANNER ==========
    let completedSessionScanner = null;
    let isCompletedScannerRunning = false;
    
    // Start clock-out scanner for completed session
    document.getElementById('startClockOutScannerBtn')?.addEventListener('click', function() {
        if (!managingSessionId) {
            Swal.fire('Error', 'No session selected', 'error');
            return;
        }
        
        startCompletedSessionScanner();
    });
    
    // Stop clock-out scanner
    document.getElementById('stopClockOutScannerBtn')?.addEventListener('click', function() {
        stopCompletedSessionScanner();
    });
    
    function startCompletedSessionScanner() {
        if (completedSessionScanner) {
            return;
        }
        
        completedSessionScanner = new Html5Qrcode("completedSessionReader");
        
        const config = {
            fps: 10,
            qrbox: { width: 200, height: 200 },
            aspectRatio: 1.0
        };
        
        completedSessionScanner.start(
            { facingMode: "environment" },
            config,
            onCompletedSessionScanSuccess,
            (errorMessage) => { /* Ignore scan errors */ }
        ).then(() => {
            isCompletedScannerRunning = true;
            document.getElementById('startClockOutScannerBtn').style.display = 'none';
            document.getElementById('stopClockOutScannerBtn').style.display = 'inline-block';
        }).catch(err => {
            console.error("Camera error:", err);
            Swal.fire('Camera Error', 'Unable to access camera. Please check permissions.', 'error');
        });
    }
    
    function stopCompletedSessionScanner() {
        if (completedSessionScanner && isCompletedScannerRunning) {
            completedSessionScanner.stop().then(() => {
                completedSessionScanner.clear();
                completedSessionScanner = null;
                isCompletedScannerRunning = false;
                document.getElementById('startClockOutScannerBtn').style.display = 'inline-block';
                document.getElementById('stopClockOutScannerBtn').style.display = 'none';
            }).catch(err => {
                console.error("Error stopping scanner:", err);
            });
        }
    }
    
    let lastCompletedScanCode = '';
    let lastCompletedScanTime = 0;
    
    function onCompletedSessionScanSuccess(decodedText) {
        const now = Date.now();
        
        // Prevent duplicate scans within cooldown
        if (decodedText === lastCompletedScanCode && (now - lastCompletedScanTime) < SCAN_COOLDOWN) {
            return;
        }
        
        lastCompletedScanCode = decodedText;
        lastCompletedScanTime = now;
        
        // Show scanning indicator
        document.getElementById('clockOutScanResult').innerHTML = 
            '<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Processing...</span>';
        
        // Send clock-out scan request
        fetch(`{{ route('admin.class-session.scan-clock-out') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                session_id: managingSessionId,
                student_id: decodedText
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('clockOutScanResult').innerHTML = 
                    `<span class="text-success"><i class="fas fa-check-circle"></i> ${data.student} clocked out at ${data.time}</span>`;
                
                // Refresh the student list
                loadCompletedSessionData(managingSessionId);
                
                // Play success sound or beep (optional)
                playBeep('success');
            } else {
                document.getElementById('clockOutScanResult').innerHTML = 
                    `<span class="text-danger"><i class="fas fa-times-circle"></i> ${data.message}</span>`;
                playBeep('error');
            }
            
            // Clear message after 3 seconds
            setTimeout(() => {
                document.getElementById('clockOutScanResult').innerHTML = '';
            }, 3000);
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('clockOutScanResult').innerHTML = 
                '<span class="text-danger"><i class="fas fa-times-circle"></i> Scan failed. Please try again.</span>';
        });
    }
    
    // Stop scanner when modal closes
    $('#manageCompletedModal').on('hidden.bs.modal', function() {
        stopCompletedSessionScanner();
    });
    
    // Simple beep function
    function playBeep(type) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = type === 'success' ? 800 : 300;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.3;
            
            oscillator.start();
            setTimeout(() => oscillator.stop(), 150);
        } catch (e) {
            // Audio not supported, ignore
        }
    }
    
    // Save completed session logbook
    document.getElementById('saveCompletedLogbookBtn')?.addEventListener('click', function() {
        const form = document.getElementById('completedLogbookForm');
        const formData = new FormData(form);
        formData.append('session_id', managingSessionId);
        
        // Debug: Log what we're sending
        console.log('Saving logbook for session:', managingSessionId);
        
        fetch(`{{ route('admin.class-session.update-logbook') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Server error: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Logbook saved successfully',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to save logbook', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            Swal.fire('Error', err.message || 'Failed to save logbook', 'error');
        });
    });

    // ============================================
    // STUDENT ACTIVITY SECTION
    // ============================================
    
    // Switch activity tabs
    window.switchActivityTab = function(tab) {
        // Update tab buttons
        document.querySelectorAll('.activity-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`.activity-tab[data-tab="${tab}"]`).classList.add('active');
        
        // Show/hide content
        document.getElementById('chatTab').style.display = tab === 'chat' ? 'block' : 'none';
        document.getElementById('questionsTab').style.display = tab === 'questions' ? 'block' : 'none';
        document.getElementById('alertsTab').style.display = tab === 'alerts' ? 'block' : 'none';
    };
    
    // Load student activity
    function escapeText(value) {
        // Student-authored text goes through here before it reaches innerHTML.
        // The XSS middleware permits <img>, so an onerror payload survives
        // input filtering; nothing below may be trusted as markup.
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function loadStudentActivity() {
        if (!activeSessionId) return;
        
        fetch(`{{ url('admin/class-session') }}/${activeSessionId}/activity`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update counts
                document.getElementById('chatCount').textContent = data.messages?.length || 0;
                document.getElementById('questionsCount').textContent = data.questions?.length || 0;
                document.getElementById('alertsCount').textContent = data.alerts?.length || 0;
                
                // Render messages
                renderChatMessages(data.messages || []);
                renderQuestions(data.questions || []);
                renderAlerts(data.alerts || []);
            }
        })
        .catch(error => console.error('Load activity error:', error));
    }
    
    function renderChatMessages(messages) {
        const container = document.getElementById('chatMessagesList');
        if (messages.length === 0) {
            container.innerHTML = `<div class="no-activity"><i class="fas fa-comments"></i><p>No messages yet. Start the conversation!</p></div>`;
            return;
        }
        
        container.innerHTML = messages.map(msg => `
            <div class="chat-message-lecturer ${msg.is_lecturer ? 'from-lecturer' : ''}">
                <div class="chat-avatar-sm ${msg.is_lecturer ? 'lecturer' : ''}">${msg.sender_name?.charAt(0).toUpperCase() || 'S'}</div>
                <div class="chat-content">
                    <div class="chat-sender-name">
                        ${msg.sender_name || 'Student'}
                        ${msg.is_lecturer ? '<span class="badge badge-primary ml-1">Lecturer</span>' : ''}
                        ${msg.program && !msg.is_lecturer ? `<span class="badge badge-info ml-1">${msg.program}</span>` : ''}
                    </div>
                    <div class="chat-text">${escapeText(msg.message)}</div>
                    <div class="chat-time">${msg.formatted_time}</div>
                </div>
            </div>
        `).join('');
    }
    
    function renderQuestions(questions) {
        const container = document.getElementById('questionsList');
        if (questions.length === 0) {
            container.innerHTML = `<div class="no-activity"><i class="fas fa-question-circle"></i><p>No questions yet</p></div>`;
            return;
        }
        
        container.innerHTML = questions.map(q => `
            <div class="question-item ${q.is_answered ? 'answered' : ''}" data-question-id="${q.is_answered ? '' : q.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${q.is_anonymous ? 'Anonymous' : q.student_name}</strong>
                        ${q.program ? `<span class="badge badge-info ml-1">${q.program}</span>` : ''}
                        <span class="badge question-badge ${q.is_answered ? 'badge-success' : 'badge-warning'} ml-2">
                            ${q.is_answered ? 'Answered' : 'Click to Answer'}
                        </span>
                    </div>
                    <small class="text-muted">${q.formatted_time}</small>
                </div>
                <p class="mb-1 mt-2">${escapeText(q.question)}</p>
                ${q.is_answered && q.answer ? `<div class="alert alert-success py-1 px-2 mt-2 mb-0"><small><strong>Answer:</strong> ${q.answer}</small></div>` : ''}
            </div>
        `).join('');
    }
    
    function renderAlerts(alerts) {
        const container = document.getElementById('alertsList');
        if (alerts.length === 0) {
            container.innerHTML = `<div class="no-activity"><i class="fas fa-bell"></i><p>No alerts yet</p></div>`;
            return;
        }
        
        const icons = {
            'exam': 'fa-clipboard-list',
            'assignment': 'fa-tasks',
            'deadline': 'fa-calendar-times',
            'reminder': 'fa-bell',
            'general': 'fa-info-circle'
        };
        
        container.innerHTML = alerts.map(a => `
            <div class="alert-item ${a.alert_type}">
                <div class="alert-icon">
                    <i class="fas ${icons[a.alert_type] || 'fa-bell'}"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <strong>${escapeText(a.title)}</strong>
                        <span class="badge badge-secondary">${a.upvotes || 0} upvotes</span>
                    </div>
                    <p class="mb-1 small">${a.content || ''}</p>
                    ${a.due_date ? `<small class="text-muted"><i class="fas fa-clock mr-1"></i>Due: ${a.due_date} ${a.due_time || ''}</small>` : ''}
                    <div class="small text-muted mt-1">By: ${a.student_name}${a.program ? ` <span class="badge badge-info">${a.program}</span>` : ''}</div>
                </div>
            </div>
        `).join('');
    }
    
    // Poll for new activity every 5 seconds
    setInterval(loadStudentActivity, 5000);
    
    // ============================================
    // LECTURER COMMUNICATION
    // ============================================
    
    let selectedQuestionId = null;
    
    // Lecturer send message
    document.getElementById('lecturerSendBtn')?.addEventListener('click', sendLecturerMessage);
    document.getElementById('lecturerChatInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendLecturerMessage();
    });
    
    function sendLecturerMessage() {
        const input = document.getElementById('lecturerChatInput');
        const message = input.value.trim();
        
        if (!message || !activeSessionId) return;
        
        fetch('{{ route("admin.class-session.send-message") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                input.value = '';
                loadStudentActivity(); // Refresh to show new message
            } else {
                alert(data.message || 'Failed to send message');
            }
        })
        .catch(error => {
            console.error('Send message error:', error);
            alert('Failed to send message');
        });
    }
    
    // Select question to answer
    window.selectQuestionToAnswer = function(questionId, questionText) {
        selectedQuestionId = questionId;
        document.getElementById('answerSection').style.display = 'block';
        // escapeText: this wrote the student's question straight into innerHTML.
        document.getElementById('answeringQuestion').innerHTML =
            '<strong>Answering:</strong> ' + escapeText(questionText);
        document.getElementById('lecturerAnswerInput').focus();
    };

    // The question list used to carry its text in an inline onclick
    // attribute, escaping only single quotes. The item now carries its id
    // alone and the text is read back from the DOM, already escaped.
    document.addEventListener('click', function (event) {
        const item = event.target.closest('[data-question-id]');

        if (!item) {
            return;
        }

        const questionId = item.getAttribute('data-question-id');

        if (!questionId) {
            return; // already answered
        }

        const text = item.querySelector('p');
        window.selectQuestionToAnswer(questionId, text ? text.textContent : '');
    });
    
    window.cancelAnswer = function() {
        selectedQuestionId = null;
        document.getElementById('answerSection').style.display = 'none';
        document.getElementById('answeringQuestion').innerHTML = '';
        document.getElementById('lecturerAnswerInput').value = '';
    };
    
    // Submit answer
    document.getElementById('submitAnswerBtn')?.addEventListener('click', submitAnswer);
    document.getElementById('lecturerAnswerInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') submitAnswer();
    });
    
    function submitAnswer() {
        const answer = document.getElementById('lecturerAnswerInput').value.trim();
        
        if (!answer || !selectedQuestionId || !activeSessionId) return;
        
        fetch('{{ route("admin.class-session.answer-question") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                session_id: activeSessionId,
                question_id: selectedQuestionId,
                answer: answer
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                cancelAnswer();
                loadStudentActivity(); // Refresh to show answered
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Answer submitted!',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                alert(data.message || 'Failed to submit answer');
            }
        })
        .catch(error => {
            console.error('Submit answer error:', error);
            alert('Failed to submit answer');
        });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, activeSessionId:', activeSessionId);
        console.log('Html5Qrcode available:', typeof Html5Qrcode !== 'undefined');
        
        if (activeSessionId) {
            // Show scanner controls
            document.getElementById('scannerControls').style.display = 'block';
            
            // Small delay to ensure library is fully loaded
            setTimeout(function() {
                if (typeof Html5Qrcode === 'undefined') {
                    console.error('Html5Qrcode library not loaded!');
                    document.getElementById('scannerStatus').textContent = 'QR library not loaded - refresh page';
                    return;
                }
                initScanner();
            }, 500);
            
            loadStudentList();
            loadStudentActivity(); // Load activity on page load
        }
    });
})();
</script>
@endpush
