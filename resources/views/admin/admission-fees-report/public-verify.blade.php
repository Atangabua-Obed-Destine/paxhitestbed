<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Verify Receipt') }} · {{ $receipt->receipt_number }} · {{ institution_name() }}</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <style>
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#e9edf1;font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#1f2a44}
        .wrap{max-width:640px;margin:40px auto;padding:0 12px}
        .card{background:#fff;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.08);overflow:hidden}
        .header{padding:20px 24px;display:flex;align-items:center;gap:16px;border-bottom:1px solid #e2e8f0}
        .header img{width:56px;height:56px;object-fit:contain}
        .header h1{margin:0;font-size:1.1rem;color:#0f172a}
        .header small{color:#64748b;font-size:.75rem;display:block;margin-top:2px}

        .banner{padding:22px 24px;text-align:center;color:#fff;font-weight:600;letter-spacing:.05em}
        .banner.ok{background:linear-gradient(135deg,#16a34a,#0d7a37)}
        .banner.bad{background:linear-gradient(135deg,#dc2626,#8f1717)}
        .banner.info{background:linear-gradient(135deg,#f59e0b,#c67f0a)}
        .banner .icon{font-size:2.2rem;line-height:1;margin-bottom:6px}
        .banner .title{font-size:1.1rem;text-transform:uppercase}
        .banner .sub{font-size:.8rem;opacity:.9;font-weight:400;margin-top:4px;letter-spacing:.02em}

        .body{padding:20px 24px}
        .kv{display:grid;grid-template-columns:180px 1fr;gap:8px 14px;font-size:.9rem}
        .kv .k{color:#64748b;text-transform:uppercase;font-size:.7rem;letter-spacing:.08em;align-self:center}
        .kv .v{color:#0f172a;font-weight:500;word-break:break-word}

        .code{font-family:'Courier New',monospace;font-size:.95rem;background:#f1f5f9;padding:2px 8px;border-radius:3px;letter-spacing:.1em}
        .footer{padding:14px 24px;text-align:center;font-size:.72rem;color:#64748b;border-top:1px solid #e2e8f0;background:#f8fafc}
        .muted{color:#64748b;font-size:.8rem}

        @media(max-width:520px){
            .kv{grid-template-columns:1fr}
            .kv .k{margin-top:6px}
        }
    </style>
</head>
<body>

@php
    $status = (int) optional($receipt->fee)->status;
    $statusLabel = $status === 1 ? __('Fully Paid') : ($status === 2 ? __('Partial Payment') : __('Unpaid'));
    $currency = $setting->currency_symbol ?? ($setting->currency ?? config('momo.currency', 'XAF'));
@endphp

<div class="wrap">
    <div class="card">
        <div class="header">
            @if($setting && $setting->logo_path)
                <img src="{{ asset($setting->logo_path) }}" alt="Logo">
            @endif
            <div>
                <h1>{{ institution_name() }}</h1>
                <small>{{ __('Receipt Verification') }}</small>
            </div>
        </div>

        @if(!$codeProvided)
            <div class="banner info">
                <div class="icon">?</div>
                <div class="title">{{ __('No verification code supplied') }}</div>
                <div class="sub">{{ __('This link is missing the security code — receipt shown for reference only.') }}</div>
            </div>
        @elseif($isValid)
            <div class="banner ok">
                <div class="icon">&#10003;</div>
                <div class="title">{{ __('Receipt Verified') }}</div>
                <div class="sub">{{ __('This receipt matches our records.') }}</div>
            </div>
        @else
            <div class="banner bad">
                <div class="icon">&#10007;</div>
                <div class="title">{{ __('Verification Failed') }}</div>
                <div class="sub">{{ __('The code does not match this receipt. It may have been altered or the link mistyped.') }}</div>
            </div>
        @endif

        <div class="body">
            <div class="kv">
                <div class="k">{{ __('Receipt No.') }}</div>
                <div class="v"><span class="code">{{ $receipt->receipt_number }}</span></div>

                <div class="k">{{ __('Applicant') }}</div>
                <div class="v">
                    {{ trim(($application->first_name ?? '').' '.($application->last_name ?? '')) ?: '—' }}
                    @if($application?->registration_no) <div class="muted">Reg# {{ $application->registration_no }}</div> @endif
                </div>

                <div class="k">{{ __('Purpose') }}</div>
                <div class="v">{{ optional($receipt->fee?->category)->title ?? __('Admission / Application Fee') }}</div>

                <div class="k">{{ __('Program') }}</div>
                <div class="v">
                    {{ optional($application?->program)->title ?? '—' }}
                    @if($application?->degreeType) <div class="muted">{{ $application->degreeType->title }}</div> @endif
                </div>

                <div class="k">{{ __('Amount') }}</div>
                <div class="v"><strong>{{ $currency }} {{ number_format((float)$receipt->amount, 2) }}</strong></div>

                <div class="k">{{ __('Payment Date') }}</div>
                <div class="v">{{ optional($receipt->payment_date)->format('Y-m-d') }}</div>

                <div class="k">{{ __('Method') }}</div>
                <div class="v">{{ $receipt->payment_method_name }}</div>

                <div class="k">{{ __('Transaction Ref.') }}</div>
                <div class="v">{{ $receipt->payment_reference ?: '—' }}</div>

                <div class="k">{{ __('Fee Status') }}</div>
                <div class="v">{{ $statusLabel }}</div>

                <div class="k">{{ __('Issued') }}</div>
                <div class="v">{{ optional($receipt->verified_at ?? $receipt->created_at)->format('Y-m-d H:i:s') }}</div>

                @if($receipt->verifier)
                    <div class="k">{{ __('Cashier') }}</div>
                    <div class="v">{{ $receipt->verifier->name }}</div>
                @endif
            </div>
        </div>

        <div class="footer">
            {{ __('This page is publicly accessible and shows what our records hold for this receipt id. The security code — signed with the institute\'s private key — is what proves authenticity.') }}<br>
            {{ __('Verified on') }} {{ now()->format('Y-m-d H:i:s') }} ({{ config('app.timezone') }})
        </div>
    </div>
</div>

</body>
</html>
