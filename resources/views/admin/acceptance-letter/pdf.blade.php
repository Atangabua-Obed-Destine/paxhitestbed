<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2733; font-size: 12.5px; line-height: 1.6; margin: 0; padding: 0; }
        .sheet { padding: 40px 48px; }

        /* Letter body — renders exactly what is configured in the editor. */
        .body { width: 100%; }
        .body p { margin: 0 0 12px; }
        .body img { max-width: 100%; height: auto; }

        /* Default visible borders for pasted/created tables (inline styles still win). */
        .body table { max-width: 100%; border-collapse: collapse; }
        .body table, .body th, .body td { border: 1px solid #555; }
        .body th, .body td { padding: 6px 8px; vertical-align: top; }

        /* Sane list spacing so bullets don't overlap the text. */
        .body ul, .body ol { margin: 8px 0 12px; padding-left: 26px; }
        .body li { margin: 2px 0; }

        /* Fee breakdown table ([fee_breakdown] placeholder). */
        .body table.fee-breakdown { border-collapse: collapse; margin: 8px 0 14px; }
        .body table.fee-breakdown th,
        .body table.fee-breakdown td { border: 1px solid #555; padding: 5px 10px; }
        .body table.fee-breakdown thead th { background: #ececec; text-align: left; }
        .body table.fee-breakdown tfoot th { background: #ececec; font-weight: bold; }
        .body table.fee-breakdown .amt { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="body">
            {!! $body !!}
        </div>
    </div>
</body>
</html>
