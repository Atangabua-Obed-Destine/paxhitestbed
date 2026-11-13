{{--
    Public check of a printed payment plan agreement, reached from its QR code.
    It shows what the printed sheet itself shows, and where the agreement
    stands now — nothing more about the student.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('Verify agreement') }} &middot; {{ $setting->title ?? config('app.name') }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:24px 16px 48px;background:#f6f8fb;font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#1f2a44;line-height:1.5}
        .sheet{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e3e8ef;border-radius:14px;overflow:hidden}
        .banner{padding:22px 24px;text-align:center;color:#fff}
        .banner.ok{background:#16a34a}.banner.old{background:#d97706}.banner.bad{background:#dc2626}.banner.unknown{background:#64748b}
        .banner h1{margin:0 0 4px;font-size:1.15rem}.banner p{margin:0;font-size:.88rem;opacity:.92}
        .body{padding:20px 24px}
        table{width:100%;border-collapse:collapse;font-size:.9rem}
        th,td{text-align:left;padding:8px 0;border-bottom:1px solid #eef2f7;vertical-align:top}
        th{color:#64748b;font-weight:500;width:45%}td{font-weight:600;text-align:right}
        .foot{padding:14px 24px 20px;border-top:1px solid #eef2f7;font-size:.78rem;color:#64748b;text-align:center}
    </style>
</head>
<body>
<div class="sheet">
    @if($isCurrent)
        <div class="banner ok"><h1>{{ __('This agreement is genuine') }}</h1><p>{{ __('It matches the school\'s records exactly.') }}</p></div>
    @elseif($matchedVersion !== null)
        <div class="banner old"><h1>{{ __('Genuine, but out of date') }}</h1><p>{{ __('This copy is revision :old. The agreement has since changed and is now revision :now.', ['old' => $matchedVersion, 'now' => $agreement->version]) }}</p></div>
    @elseif($codeProvided)
        <div class="banner bad"><h1>{{ __('This agreement could not be verified') }}</h1><p>{{ __('The code does not match our records. Please bring it to the finance office.') }}</p></div>
    @else
        <div class="banner unknown"><h1>{{ __('No verification code given') }}</h1><p>{{ __('Scan the QR on the agreement, or add the code to the address.') }}</p></div>
    @endif

    <div class="body">
        <table>
            <tr><th>{{ __('Reference') }}</th><td>{{ $agreement->reference }}</td></tr>
            <tr><th>{{ __('Name') }}</th><td>{{ trim(($agreement->student->first_name ?? '') . ' ' . ($agreement->student->last_name ?? '')) }}</td></tr>
            <tr><th>{{ __('Total under this agreement') }}</th><td>{{ $currency }} {{ number_format($agreement->total, 2) }}</td></tr>
            <tr><th>{{ __('Status now') }}</th><td>{{ $agreement->status_label }}</td></tr>
            @if($agreement->isApproved())
                <tr><th>{{ __('Approved') }}</th><td>{{ optional($agreement->approver)->name }} &middot; {{ optional($agreement->approved_at)->format('d M Y') }}</td></tr>
            @endif
        </table>
    </div>
    <div class="foot">{{ $setting->title ?? config('app.name') }}<br>{{ __('Checked on') }} {{ now()->format('Y-m-d H:i') }}</div>
</div>
</body>
</html>
