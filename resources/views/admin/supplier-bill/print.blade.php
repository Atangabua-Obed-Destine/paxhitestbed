{{-- A4 print layout for the payables documents: letterhead, title, content. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #0f172a; font-size: 12px; margin: 0; }
        .doc { max-width: 190mm; margin: 0 auto; }
        h1.doc-title { font-size: 17px; margin: 14px 0 4px; letter-spacing: .3px; }
        .doc-sub { color: #475569; margin-bottom: 12px; }
        h6 { font-size: 13px; margin: 16px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 5px 6px; text-align: left; vertical-align: top; }
        thead th { background: #f1f5f9; border-bottom: 1px solid #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: .3px; color: #334155; }
        .ap-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ap-total-row td { font-weight: 700; border-top: 2px solid #475569; }
        .ap-muted { color: #64748b; font-size: 11px; }
        .sign { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 48px; }
        .sign div { border-top: 1px solid #475569; padding-top: 4px; font-size: 11px; color: #475569; }
        .toolbar { margin: 10px 0; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="doc">
    <div class="toolbar"><button type="button" onclick="window.print()">{{ __('btn_print') }}</button></div>
    @include('partials.letterhead')
    @yield('content')
</div>
<script>window.addEventListener('load', function () { window.setTimeout(function () { window.print(); }, 250); });</script>
</body>
</html>
