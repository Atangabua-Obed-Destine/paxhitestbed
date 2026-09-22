{{--
    The transcript stylesheet, shared by the three views
    that show one: download, print, and the bulk print of many.

    They were near-identical copies of ~870 lines before this - the two that
    existed differed by nine, all of them in the closing script block - so a
    fix applied to one silently missed the other. A bulk view would have made
    that three.
--}}
    <style type="text/css">
    /* ===============================================
       {{ institution_name() }} — Official Transcript Print
       =============================================== */

    @import url('https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700;900&family=Source+Sans+Pro:wght@400;600;700&display=swap');

    @page {
      size: A4 portrait;
      margin: 0;
    }
    @page :footer { display: none; }
    @page :header { display: none; }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      margin: 0;
      padding: 0;
      background: #f5f5f5;
      font-family: 'Source Sans Pro', 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      color: #1a1a1a;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    /* --- Page container --- */
    .tp-page {
      position: relative;
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      background: #fff;
      overflow: hidden;
    }

    /* --- Letterhead background --- */
    .tp-letterhead {
      position: absolute;
      top: 0; left: 0;
      width: 100%;
      height: auto;
      z-index: 0;
      pointer-events: none;
    }

    /* --- Verification QR ---
       Generated locally, not fetched from an image service: a transcript has
       to verify in an office with no internet, and the address of every
       document issued should not be handed to a third party. */
    .tp-verify {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 18px;
      padding-top: 12px;
      border-top: 1px solid #ccc;
    }
    .tp-verify-qr {
      width: 82px;
      height: 82px;
      flex: 0 0 82px;
    }
    .tp-verify-qr svg { width: 100%; height: 100%; display: block; }
    .tp-verify-text {
      font-size: 8.5px;
      color: #444;
      line-height: 1.5;
    }
    .tp-verify-text strong {
      display: block;
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #1a1a1a;
      margin-bottom: 2px;
    }
    .tp-verify-code {
      font-family: 'Consolas', 'Courier New', monospace;
      letter-spacing: 0.6px;
      color: #1a1a1a;
      font-weight: 700;
    }

    /* --- Watermark ---
       The institution's name, from Settings, laid diagonally behind the record.
       It sits at z-index 0 while .tp-content is z-index 1, so it is genuinely
       behind the text rather than over it, and .tp-page already clips overflow.
       Light enough to read straight through: a transcript has to stay legible
       when photocopied, and a watermark that fights the marks defeats itself. */
    .tp-watermark {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      z-index: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
      overflow: hidden;
    }
    .tp-watermark span {
      transform: rotate(-32deg);
      font-family: 'Merriweather', Georgia, serif;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 6px;
      text-align: center;
      line-height: 1.15;
      /* Wraps rather than scaling to fit, so a long diocesan name stays
         readable instead of shrinking to nothing on a wide page. */
      width: 150mm;
      color: rgba(26, 26, 26, 0.055);
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    /* --- Content overlay --- */
    .tp-content {
      position: relative;
      z-index: 1;
      /* Was 190px, reserving room for a letterhead that used to be
         absolutely positioned at the top of the page. The letterhead is
         now normal flow content rendered above this block, so that
         reservation became an empty band sitting under it. */
      padding: 24px 42px 40px 42px;
    }

    /* --- Document title --- */
    .tp-doc-title {
      text-align: center;
      margin-bottom: 16px;
      padding-bottom: 8px;
      border-bottom: 2.5px double #1a1a1a;
    }
    .tp-doc-title h2 {
      font-family: 'Merriweather', Georgia, serif;
      font-size: 16px;
      font-weight: 900;
      letter-spacing: 4px;
      text-transform: uppercase;
      color: #1a1a1a;
      margin: 0;
    }
    .tp-doc-title .tp-doc-subtitle {
      font-size: 9.5px;
      letter-spacing: 1.5px;
      color: #555;
      text-transform: uppercase;
      margin-top: 3px;
    }

    /* --- Student Information Grid --- */
    .tp-info-section {
      margin-bottom: 14px;
    }
    .tp-info-grid {
      display: table;
      width: 100%;
      border-collapse: collapse;
    }
    .tp-info-row {
      display: table-row;
    }
    .tp-info-cell {
      display: table-cell;
      padding: 3.5px 0;
      font-size: 11px;
      vertical-align: top;
    }
    /* The label column shrinks to its widest label rather than taking a fixed
       22%, so the colon sits against the word instead of stranded across the
       page after a short one like SEX - and every colon still lines up, because
       a table column is one width for all its rows. */
    .tp-info-label {
      width: 1%;
      white-space: nowrap;
      font-weight: 700;
      color: #333;
      text-transform: uppercase;
      font-size: 9.5px;
      letter-spacing: 0.4px;
      padding-right: 8px;
    }
    /* The colon belongs to the label. On the value it pushed the first line in
       by two characters, so a wrapping programme title had its second line
       hanging out to the left of its own first line. */
    .tp-info-label::after {
      content: ':';
      color: #555;
      font-weight: 400;
      margin-left: 6px;
    }
    .tp-info-value {
      font-weight: 600;
      color: #1a1a1a;
      padding-right: 26px;
    }
    /* Nothing to separate from on the right-hand pair. */
    .tp-info-row .tp-info-value:last-child {
      padding-right: 0;
    }
    .tp-info-divider {
      height: 1px;
      background: #ccc;
      margin: 6px 0;
    }

    /* --- Matricule highlight --- */
    .tp-matricule {
      font-family: 'Consolas', 'Courier New', monospace;
      font-weight: 700;
      letter-spacing: 1.2px;
      color: #1a1a1a;
    }

    /* --- Summary bar --- */
    .tp-summary-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 7px 14px;
      /* Below the results table now, so it needs room above rather than below. */
      margin-top: 16px;
      margin-bottom: 0;
      page-break-inside: avoid;
      border: 1.5px solid #1a1a1a;
      background: transparent;
    }
    .tp-summary-item {
      text-align: center;
      flex: 1;
    }
    .tp-summary-item + .tp-summary-item {
      border-left: 1px solid #999;
    }
    .tp-summary-label {
      display: block;
      font-size: 8px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #555;
      font-weight: 600;
    }
    .tp-summary-value {
      display: block;
      font-size: 14px;
      font-weight: 900;
      font-family: 'Merriweather', serif;
      color: #1a1a1a;
      line-height: 1.3;
    }
    .tp-summary-value.tp-gpa-big {
      font-size: 18px;
    }

    /* --- Academic Records Table --- */
    .tp-records-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 0;
      font-size: 10px;
    }
    .tp-records-table thead th {
      background: transparent;
      border-top: 2.5px solid #1a1a1a;
      border-bottom: 2.5px double #1a1a1a;
      padding: 5px 4px;
      font-size: 8.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #1a1a1a;
      text-align: center;
      vertical-align: bottom;
    }
    .tp-records-table thead th.tp-col-left {
      text-align: left;
    }

    /* Semester header row */
    .tp-sem-header td {
      padding: 8px 4px 4px;
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #1a1a1a;
      border-bottom: 1px solid #999;
      text-align: left;
      background: transparent;
    }

    /* Per-semester column headers */
    .tp-col-headers th {
      padding: 3px 4px;
      font-size: 8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #444;
      border-bottom: 1px solid #bbb;
      text-align: center;
      background: #f8f8f8;
    }
    .tp-col-headers th.tp-col-left {
      text-align: left;
    }

    /* Course rows */
    .tp-records-table tbody td {
      padding: 3px 4px;
      border-bottom: 0.5px solid #ddd;
      text-align: center;
      font-size: 10px;
      color: #1a1a1a;
      vertical-align: middle;
      background: transparent;
    }
    .tp-records-table tbody td.tp-td-left {
      text-align: left;
    }
    .tp-records-table tbody td.tp-td-code {
      text-align: left;
      font-family: 'Consolas', 'Courier New', monospace;
      font-weight: 600;
      letter-spacing: 0.3px;
    }
    .tp-records-table tbody td.tp-td-title {
      text-align: left;
      font-weight: 500;
    }
    .tp-records-table tbody td.tp-td-type {
      font-size: 9px;
      font-weight: 600;
    }
    .tp-records-table tbody td.tp-td-grade {
      font-weight: 800;
    }

    /* Semester subtotal rows */
    .tp-sem-subtotal td {
      padding: 4px 4px;
      border-top: 1px solid #999;
      border-bottom: none;
      font-weight: 700;
      font-size: 9.5px;
      color: #1a1a1a;
      background: transparent;
    }
    .tp-sem-gpa td {
      padding: 2px 4px 6px;
      border-bottom: 2px solid #1a1a1a;
      font-weight: 700;
      font-size: 9.5px;
      color: #1a1a1a;
      background: transparent;
    }
    .tp-sem-gpa-val {
      font-family: 'Merriweather', serif;
      font-weight: 900;
      font-size: 11px;
    }

    /* Column widths */
    .tp-col-code { width: 9%; }
    .tp-col-title { width: 26%; }
    .tp-col-type { width: 6%; }
    .tp-col-cv { width: 8%; }
    .tp-col-att { width: 9%; }
    .tp-col-ern { width: 9%; }
    .tp-col-gpt { width: 9%; }
    .tp-col-grd { width: 7%; }
    .tp-col-qpt { width: 9%; }

    /* --- Grading Scale --- */
    .tp-grade-scale {
      margin-top: 16px;
      page-break-inside: avoid;
    }
    /* The scale now sits above the results, so its spacing is below it. */
    .tp-grade-scale-top {
      margin-top: 0;
      margin-bottom: 14px;
    }

    /* --- Key to the transcript --- */
    .tp-key {
      margin-top: 16px;
      page-break-inside: avoid;
    }
    /* Columns rather than a wrapping row: the entries then read down one
       column and on to the next, and a line that wraps cannot push the entry
       beside it out of line. */
    .tp-key-grid {
      column-count: 2;
      column-gap: 24px;
    }
    .tp-key-item {
      break-inside: avoid;
      page-break-inside: avoid;
      padding: 2px 0;
      font-size: 8.5px;
      color: #333;
      line-height: 1.45;
    }
    .tp-key-term {
      font-weight: 800;
      color: #1a1a1a;
      display: inline-block;
      min-width: 74px;
    }
    .tp-grade-scale-title {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #1a1a1a;
      margin-bottom: 5px;
      border-bottom: 1px solid #999;
      padding-bottom: 3px;
    }
    .tp-grade-scale-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 0;
    }
    .tp-grade-scale-item {
      flex: 0 0 20%;
      display: flex;
      align-items: baseline;
      gap: 4px;
      padding: 2px 0;
      font-size: 9px;
    }
    .tp-grade-scale-item .tp-gs-grade {
      font-weight: 800;
      min-width: 14px;
    }
    .tp-grade-scale-item .tp-gs-range {
      color: #555;
    }
    .tp-grade-scale-item .tp-gs-point {
      font-weight: 700;
    }

    /* --- Footer / Signatures --- */
    .tp-footer-section {
      margin-top: 24px;
      page-break-inside: avoid;
    }
    .tp-signatures {
      display: flex;
      justify-content: space-between;
      margin-top: 50px;
    }
    .tp-sig-block {
      text-align: center;
      width: 30%;
    }
    .tp-sig-line {
      border-top: 1.5px solid #1a1a1a;
      margin-bottom: 4px;
      width: 100%;
    }
    .tp-sig-label {
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #333;
    }
    .tp-sig-sublabel {
      font-size: 8px;
      color: #666;
      font-style: italic;
    }

    /* --- End-of-document marker --- */
    .tp-end-marker {
      text-align: center;
      margin-top: 18px;
      font-size: 8px;
      color: #999;
      letter-spacing: 3px;
      text-transform: uppercase;
    }
    .tp-end-marker::before,
    .tp-end-marker::after {
      content: '———';
      margin: 0 6px;
    }

    /* --- Print overrides --- */
    @media print {
      body { background: #fff; margin: 0; padding: 0; }
      .tp-page {
        width: 100%;
        margin: 0;
        box-shadow: none;
        page-break-after: always;
      }
      .tp-content { padding-top: 24px; }
      .tp-letterhead { display: block; }
      .tp-summary-bar { border-color: #1a1a1a !important; }
      .tp-records-table thead th { border-color: #1a1a1a !important; }
      .tp-sem-header td { border-color: #999 !important; }
      .tp-col-headers th { border-color: #bbb !important; background: #f5f5f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .tp-sem-gpa td { border-color: #1a1a1a !important; }
      .tp-sig-line { border-color: #1a1a1a !important; }
    }
    @media screen {
      .tp-page { box-shadow: 0 2px 20px rgba(0,0,0,0.12); margin: 20px auto; }
    }
    </style>
@php
    $version = App\Models\Language::version();
@endphp
@if($version->direction == 1)
<style type="text/css">
.tp-page { direction: rtl; }
.tp-info-label { text-align: right; }
.tp-info-value { text-align: right; padding-right: 0; padding-left: 26px; }
.tp-info-row .tp-info-value:last-child { padding-left: 0; }
/* The colon moved onto the label, so the right-to-left sheet has to space it
   on the other side too. Leaving the old rule here would have printed a second
   colon on the value. */
.tp-info-label { padding-right: 0; padding-left: 8px; }
.tp-info-label::after { margin-left: 0; margin-right: 6px; }
.tp-records-table thead th.tp-col-left,
.tp-col-headers th.tp-col-left,
.tp-records-table tbody td.tp-td-left,
.tp-records-table tbody td.tp-td-code,
.tp-records-table tbody td.tp-td-title { text-align: right; }
.tp-sem-header td { text-align: right; }
.tp-col-headers th { text-align: right; }
</style>
@endif
