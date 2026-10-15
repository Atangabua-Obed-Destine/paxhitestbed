{{--
    Public verification of a fee receipt.

    Reached by scanning the QR on a printed receipt. No login: the point is that
    anyone holding the sheet can check it against the school's records. It shows
    only what is already printed on the receipt they are holding, and never a
    balance or anything else about the student.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('Verify receipt') }} &middot; {{ $setting->title ?? config('app.name') }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:24px 16px 48px;background:#f6f8fb;font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#1f2a44;line-height:1.5}
        .sheet{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e3e8ef;border-radius:14px;overflow:hidden}
        .banner{padding:22px 24px;text-align:center;color:#fff}
        .banner.ok{background:#16a34a}
        .banner.bad{background:#dc2626}
        .banner.unknown{background:#64748b}
        .banner h1{margin:0 0 4px;font-size:1.15rem}
        .banner p{margin:0;font-size:.88rem;opacity:.92}
        .body{padding:20px 24px}
        table{width:100%;border-collapse:collapse;font-size:.9rem}
        th,td{text-align:left;padding:8px 0;border-bottom:1px solid #eef2f7;vertical-align:top}
        th{color:#64748b;font-weight:500;width:45%}
        td{font-weight:600;text-align:right}
        .code{font-family:'Courier New',monospace;letter-spacing:.12em}
        .foot{padding:14px 24px 20px;border-top:1px solid #eef2f7;font-size:.78rem;color:#64748b;text-align:center}
    </style>
</head>
<body>
<div class="sheet">
    @if($isValid)
        <div class="banner ok">
            <h1>{{ __('This receipt is genuine') }}</h1>
            <p>{{ __('It matches the school\'s records exactly.') }}</p>
        </div>
    @elseif($codeProvided)
        <div class="banner bad">
            <h1>{{ __('This receipt could not be verified') }}</h1>
            <p>{{ __('The code does not match our records. Please bring it to the finance office.') }}</p>
        </div>
    @else
        <div class="banner unknown">
            <h1>{{ __('No verification code given') }}</h1>
            <p>{{ __('Scan the QR on the receipt, or add the code to the address.') }}</p>
        </div>
    @endif

    <div class="body">
        <table>
            <tr><th>{{ __('Receipt No.') }}</th><td class="code">{{ $receipt->payment_reference ?: '—' }}</td></tr>
            <tr><th>{{ __('Name') }}</th><td>{{ trim(optional($student)->first_name . ' ' . optional($student)->last_name) ?: '—' }}</td></tr>
            <tr><th>{{ __('field_matricule') }}</th><td>{{ optional($student)->student_id ?? '—' }}</td></tr>
            <tr><th>{{ __('Purpose for Deposit') }}</th><td>{{ optional(optional($receipt->fee)->category)->title ?? '—' }}</td></tr>
            <tr><th>{{ __('Amount Received') }}</th><td>{{ $currency }} {{ number_format((float) $receipt->amount, 2) }}</td></tr>
            <tr><th>{{ __('Date') }}</th><td>{{ optional($receipt->payment_date)->format('Y-m-d') ?? '—' }}</td></tr>
            <tr><th>{{ __('Verification Code') }}</th><td class="code">{{ $verificationCode }}</td></tr>
        </table>
    </div>

    <div class="foot">
        {{ $setting->title ?? config('app.name') }}<br>
        {{ __('Checked on') }} {{ now()->format('Y-m-d H:i') }}
    </div>
</div>
</body>
</html>
