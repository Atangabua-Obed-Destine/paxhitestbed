<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }} · {{ $setting->title ?? 'PAX HIGHER INSTITUTE' }}</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        /* ---------- Base ---------- */
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#e9edf1;font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#1f2a44}
        .toolbar{max-width:900px;margin:16px auto;text-align:right;padding:0 8px}
        .toolbar button, .toolbar a{border:0;background:#224abe;color:#fff;padding:8px 14px;border-radius:4px;text-decoration:none;font-size:.85rem;cursor:pointer;margin-left:6px}
        .toolbar a.secondary{background:#6c757d}

        /* ---------- Receipt sheet ---------- */
        .sheet{
            max-width:900px;margin:0 auto 40px;background:#fff;padding:36px 46px 32px;
            box-shadow:0 6px 24px rgba(0,0,0,.08);
            position:relative;overflow:hidden;
        }
        .sheet::before{
            content:"";position:absolute;top:20%;left:8%;right:8%;bottom:20%;
            background-repeat:no-repeat;background-position:center;background-size:contain;
            opacity:.06;pointer-events:none;z-index:0;
            background-image:url('{{ $setting && $setting->logo_path ? asset($setting->logo_path) : '' }}');
        }
        .sheet > *{position:relative;z-index:1}

        /* ---------- Header ---------- */
        .r-head{display:flex;align-items:flex-start;gap:24px;border-bottom:2px solid #224abe;padding-bottom:16px;margin-bottom:20px}
        .r-head .logo{flex:0 0 110px}
        .r-head .logo img{width:110px;height:110px;object-fit:contain}
        .r-head .school{flex:1;text-align:right}
        .r-head .school h1{font-size:1.6rem;margin:0 0 4px;letter-spacing:.02em}
        .r-head .school div{font-size:.85rem;line-height:1.35;color:#334155}
        .r-head .school .contact{margin-top:6px;font-size:.8rem;color:#1e40af}
        .r-title{
            text-align:center;background:#f1f5f9;color:#1e293b;
            font-weight:600;letter-spacing:.15em;text-transform:uppercase;
            padding:8px;border-radius:2px;margin-bottom:20px;font-size:.95rem;
            border:1px solid #cbd5e1;
        }
        .r-title small{display:block;font-weight:500;letter-spacing:.05em;text-transform:none;color:#64748b;font-size:.7rem;margin-top:2px}

        /* ---------- Meta strip ---------- */
        .r-meta{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:22px;font-size:.8rem}
        .r-meta .cell{background:#f8fafc;border-left:3px solid #224abe;padding:8px 10px}
        .r-meta .cell .k{color:#64748b;text-transform:uppercase;font-size:.65rem;letter-spacing:.08em}
        .r-meta .cell .v{font-weight:600;color:#0f172a;margin-top:2px;word-break:break-all}

        /* ---------- Field rows ---------- */
        .field{display:flex;align-items:baseline;margin-bottom:14px}
        .field .label{
            width:220px;flex:0 0 220px;font-weight:600;text-transform:uppercase;
            font-size:.8rem;letter-spacing:.05em;color:#1e293b;
        }
        .field .value{
            flex:1;border-bottom:1px solid #94a3b8;padding:2px 4px 4px;min-height:26px;
            font-size:.95rem;color:#0f172a;
        }
        .field-row{display:flex;gap:20px}
        .field-row .field{flex:1}
        .field-row .field .label{width:150px;flex:0 0 150px}

        /* ---------- Amount box ---------- */
        .amount-box{
            display:flex;justify-content:space-between;align-items:center;
            background:#0f172a;color:#f8fafc;padding:14px 20px;border-radius:4px;margin:18px 0;
        }
        .amount-box .k{text-transform:uppercase;letter-spacing:.1em;font-size:.75rem;opacity:.8}
        .amount-box .v{font-size:1.6rem;font-weight:700;letter-spacing:.02em}
        .amount-box .status{
            background:#16a34a;color:#fff;padding:4px 10px;border-radius:12px;
            font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-left:12px;
        }
        .amount-box .status.partial{background:#f59e0b}
        .amount-box .status.unpaid{background:#dc2626}

        /* ---------- Signatures & footer ---------- */
        .sig{display:flex;justify-content:space-between;margin-top:32px;gap:40px}
        .sig .box{flex:1}
        .sig .box .label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:22px}
        .sig .box .line{border-top:1px solid #475569;padding-top:4px;font-size:.85rem}

        .verify{
            margin-top:24px;padding:12px 14px;border:1px dashed #94a3b8;background:#f8fafc;
            display:flex;justify-content:space-between;align-items:center;gap:18px;font-size:.75rem;
        }
        .verify .code{font-family:'Courier New',monospace;font-size:1rem;color:#0f172a;letter-spacing:.15em;font-weight:700}
        .verify .qr{flex:0 0 90px;text-align:right}
        .verify .qr img{width:90px;height:90px;background:#fff;border:1px solid #cbd5e1}

        .footer{margin-top:14px;text-align:center;font-size:.7rem;color:#64748b;line-height:1.5}

        .digital-badge{
            position:absolute;top:22px;right:22px;background:#16a34a;color:#fff;
            padding:4px 10px;font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;
            border-radius:2px;z-index:2;
        }

        /* ---------- Print ---------- */
        @media print{
            body{background:#fff}
            .toolbar{display:none}
            .sheet{box-shadow:none;margin:0;padding:20px 30px;max-width:100%}
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ url()->previous() }}" class="secondary"><span>&larr; {{ __('Back') }}</span></a>
    <button onclick="window.print()">{{ __('Print') }}</button>
</div>

@php
    $status = (int) optional($receipt->fee)->status;
    $statusLabel = $status === 1 ? 'Fully Paid' : ($status === 2 ? 'Partial Payment' : 'Unpaid');
    $statusClass = $status === 1 ? '' : ($status === 2 ? 'partial' : 'unpaid');

    // Number-to-words for CFA amounts. Fall back gracefully if intl not present.
    $amountInWords = null;
    if (class_exists(\NumberFormatter::class)) {
        try {
            $fmt = new \NumberFormatter(app()->getLocale(), \NumberFormatter::SPELLOUT);
            $whole = (int) floor((float) $receipt->amount);
            $amountInWords = ucfirst($fmt->format($whole));
        } catch (\Throwable $e) { /* noop */ }
    }
    $amountInWords = $amountInWords ?? number_format((float)$receipt->amount, 0);

    $currency = $setting->currency_symbol ?? ($setting->currency ?? config('momo.currency', 'XAF'));
    // Public verification URL — no auth required, includes the HMAC code so scanners
    // can be told immediately whether the receipt they hold matches our records.
    $verificationUrl = route('verify-admission-fee', $receipt->id) . '?code=' . $verificationCode;
    $qrPayload = urlencode($verificationUrl);
@endphp

<div class="sheet">
    <div class="digital-badge">{{ __('Digital Receipt') }}</div>

    <div class="r-head">
        <div class="logo">
            @if($setting && $setting->logo_path)
                <img src="{{ asset($setting->logo_path) }}" alt="Logo">
            @endif
        </div>
        <div class="school">
            <h1>{{ $setting->title ?? 'PAX HIGHER INSTITUTE (PAXHI)' }}</h1>
            <div>{{ $setting->address ?? 'BAMUNKA-NDOP, NGOKETUNJIA DIVISION, NORTH-WEST REGION, CAMEROON' }}</div>
            <div><em>{{ __('Motto') }}: Pax, Innovatio, et Scientia</em></div>
            <div class="contact">
                @if($setting?->phone) {{ __('Tel') }}: {{ $setting->phone }} @endif
                @if($setting?->email) · {{ $setting->email }} @endif
            </div>
        </div>
    </div>

    <div class="r-title">
        {{ __('Receipt for Registration') }}
        <small>{{ __('Admission / Application Fee') }}</small>
    </div>

    <div class="r-meta">
        <div class="cell">
            <div class="k">{{ __('Receipt No.') }}</div>
            <div class="v">{{ $receipt->receipt_number }}</div>
        </div>
        <div class="cell">
            <div class="k">{{ __('Transaction Ref.') }}</div>
            <div class="v">{{ $receipt->payment_reference ?: '—' }}</div>
        </div>
        <div class="cell">
            <div class="k">{{ __('Issued') }}</div>
            <div class="v">{{ optional($receipt->verified_at ?? $receipt->created_at)->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>

    <div class="field">
        <div class="label">{{ __('Name') }}:</div>
        <div class="value">
            {{ trim(($application->first_name ?? '').' '.($application->middle_name ?? '').' '.($application->last_name ?? '')) ?: '—' }}
        </div>
    </div>

    <div class="field">
        <div class="label">{{ __('Amount in Figures') }}:</div>
        <div class="value">{{ $currency }} {{ number_format((float)$receipt->amount, 2) }}</div>
    </div>

    <div class="field">
        <div class="label">{{ __('Amount in Words') }}:</div>
        <div class="value">{{ $amountInWords }} {{ __(is_numeric($amountInWords) ? '' : ($currency . ' only')) }}</div>
    </div>

    <div class="field">
        <div class="label">{{ __('Purpose for Deposit') }}:</div>
        <div class="value">
            {{ optional($receipt->fee?->category)->title ?? __('Admission / Application Fee') }}
            @if($application?->registration_no) · Ref App: {{ $application->registration_no }} @endif
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <div class="label">{{ __('School / Faculty of') }}:</div>
            <div class="value">
                {{ optional($application?->program)->title ?? '—' }}
            </div>
        </div>
        <div class="field">
            <div class="label">{{ __('Level') }}:</div>
            <div class="value">{{ optional($application?->degreeType)->title ?? '—' }}</div>
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <div class="label">{{ __('Date') }}:</div>
            <div class="value">{{ optional($receipt->payment_date)->format('Y-m-d') }}</div>
        </div>
        <div class="field">
            <div class="label">{{ __('Academic Year') }}:</div>
            <div class="value">{{ optional($application?->session)->title ?? '—' }}</div>
        </div>
    </div>

    <div class="field">
        <div class="label">{{ __('Payment Method') }}:</div>
        <div class="value">{{ $receipt->payment_method_name }}</div>
    </div>

    <div class="amount-box">
        <div>
            <div class="k">{{ __('Amount Received') }}</div>
            <div class="v">{{ $currency }} {{ number_format((float)$receipt->amount, 2) }}</div>
        </div>
        <div>
            <span class="status {{ $statusClass }}">{{ __($statusLabel) }}</span>
        </div>
    </div>

    <div class="sig">
        <div class="box">
            <div class="label">{{ __('Signature of Depositor') }}</div>
            <div class="line">
                {{ trim(($application->first_name ?? '').' '.($application->last_name ?? '')) ?: '—' }}
            </div>
        </div>
        <div class="box">
            <div class="label">{{ __('Signature of Cashier') }}</div>
            <div class="line">
                {{ optional($receipt->verifier)->name ?? __('System (auto-verified)') }}
                <br><small style="color:#64748b">{{ optional($receipt->verified_at)->format('Y-m-d H:i') }}</small>
            </div>
        </div>
    </div>

    <div class="verify">
        <div>
            <div style="text-transform:uppercase;letter-spacing:.1em;color:#64748b">{{ __('Verification Code') }}</div>
            <div class="code">{{ $verificationCode }}</div>
            <div style="margin-top:4px;color:#475569">
                {{ __('Scan or verify at') }}: {{ $verificationUrl }}
            </div>
        </div>
        <div class="qr">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ $qrPayload }}" alt="QR">
        </div>
    </div>

    <div class="footer">
        {{ __('This is a digitally generated receipt and is valid without a physical signature.') }}<br>
        {{ __('Generated on') }} {{ now()->format('Y-m-d H:i:s') }}
        @if($receipt->verifier) · {{ __('by') }} {{ $receipt->verifier->name }} @endif
    </div>
</div>

</body>
</html>
