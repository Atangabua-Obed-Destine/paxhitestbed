{{--
    A student fee receipt.

    Built to the same shape as the admission receipt, because a school should
    issue one kind of document. It no longer reads print_settings: that carried a
    second copy of the school's name and logo which disagreed with the letterhead
    every other document uses. The letterhead is the single source now.

    The same view serves the bursar's screen and the student's own portal, so
    what the office prints and what the student downloads are the same sheet.

    Expects: fee, receipt, legs, instalments, credits, setting, currency,
             verificationCode, status  (see App\Services\FeeReceipt)
--}}
@php
    $service = app(\App\Services\FeeReceipt::class);
    $student = optional($fee->studentEnroll)->student;
    $programme = optional($fee->studentEnroll)->program;
    $enrolment = $fee->studentEnroll;

    $received = $receipt ? (float) $receipt->amount : (float) ($fee->paid_amount ?? 0);
    $charged = (float) $fee->total_amount;
    $paid = (float) ($fee->paid_amount ?? 0);
    $outstanding = max(0, $charged - $paid);
    $overpaid = max(0, $paid - $charged);

    $verificationUrl = ($verificationCode && $receipt)
        ? route('verify-fee', $receipt->id) . '?code=' . $verificationCode
        : null;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ str_pad($fee->id, 6, '0', STR_PAD_LEFT) }} · {{ $setting->title ?? config('app.name') }}</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#e9edf1;font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#1f2a44}
        .toolbar{max-width:900px;margin:16px auto;text-align:right;padding:0 8px}
        .toolbar button,.toolbar a{border:0;background:#224abe;color:#fff;padding:8px 14px;border-radius:4px;text-decoration:none;font-size:.85rem;cursor:pointer;margin-left:6px}
        .toolbar a.secondary{background:#6c757d}

        .sheet{max-width:900px;margin:0 auto 40px;background:#fff;padding:36px 46px 32px;box-shadow:0 6px 24px rgba(0,0,0,.08);position:relative;overflow:hidden}
        .sheet > *{position:relative;z-index:1}

        .r-title{text-align:center;background:#f1f5f9;color:#1e293b;font-weight:600;letter-spacing:.15em;text-transform:uppercase;padding:8px;border-radius:2px;margin-bottom:20px;font-size:.95rem;border:1px solid #cbd5e1}
        .r-title small{display:block;font-weight:500;letter-spacing:.05em;text-transform:none;color:#64748b;font-size:.7rem;margin-top:2px}

        .r-meta{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:22px;font-size:.8rem}
        .r-meta .cell{background:#f8fafc;border-left:3px solid #224abe;padding:8px 10px}
        .r-meta .cell .k{color:#64748b;text-transform:uppercase;font-size:.65rem;letter-spacing:.08em}
        .r-meta .cell .v{font-weight:600;color:#0f172a;margin-top:2px;word-break:break-all}

        .field{display:flex;align-items:baseline;margin-bottom:14px}
        .field .label{width:220px;flex:0 0 220px;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.05em;color:#1e293b}
        .field .value{flex:1;border-bottom:1px solid #94a3b8;padding:2px 4px 4px;min-height:26px;font-size:.95rem;color:#0f172a}
        .field-row{display:flex;gap:20px}
        .field-row .field{flex:1}
        .field-row .field .label{width:150px;flex:0 0 150px}

        .amount-box{display:flex;justify-content:space-between;align-items:center;background:#0f172a;color:#f8fafc;padding:14px 20px;border-radius:4px;margin:18px 0}
        .amount-box .k{text-transform:uppercase;letter-spacing:.1em;font-size:.75rem;opacity:.8}
        .amount-box .v{font-size:1.6rem;font-weight:700;letter-spacing:.02em}
        .amount-box .status{background:#16a34a;color:#fff;padding:4px 10px;border-radius:12px;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-left:12px}
        .amount-box .status.partial{background:#f59e0b}
        .amount-box .status.unpaid{background:#dc2626}

        table.block{width:100%;border-collapse:collapse;margin:0 0 18px;font-size:.85rem}
        table.block caption{text-align:left;font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.72rem;color:#64748b;padding-bottom:6px}
        table.block th,table.block td{border-bottom:1px solid #e2e8f0;padding:7px 8px;text-align:left}
        table.block th{background:#f8fafc;color:#475569;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em}
        table.block td.num,table.block th.num{text-align:right;white-space:nowrap}
        table.block tr.total td{font-weight:700;border-top:2px solid #0f172a;border-bottom:0}
        table.block tr.settled td{color:#15803d}
        table.block tr.owing td{color:#b45309}

        .sig{display:flex;justify-content:space-between;margin-top:32px;gap:40px}
        .sig .box{flex:1}
        .sig .box .label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:22px}
        .sig .box .line{border-top:1px solid #475569;padding-top:4px;font-size:.85rem}

        .verify{margin-top:24px;padding:12px 14px;border:1px dashed #94a3b8;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;gap:18px;font-size:.75rem}
        .verify .code{font-family:'Courier New',monospace;font-size:1rem;color:#0f172a;letter-spacing:.15em;font-weight:700}
        .verify .qr{flex:0 0 90px;text-align:right}
        .verify .qr img{width:90px;height:90px;background:#fff;border:1px solid #cbd5e1}

        .footer{margin-top:14px;text-align:center;font-size:.7rem;color:#64748b;line-height:1.5}
        .digital-badge{position:absolute;top:22px;right:22px;background:#16a34a;color:#fff;padding:4px 10px;font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;border-radius:2px;z-index:2}

        @media print{
            body{background:#fff}
            .toolbar{display:none}
            .sheet{box-shadow:none;margin:0;padding:20px 30px;max-width:100%}
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ url()->previous() }}" class="secondary">&larr; {{ __('Back') }}</a>
    <button onclick="window.print()">{{ __('Print') }}</button>
</div>

<div class="sheet">
    <div class="digital-badge">{{ __('Digital Receipt') }}</div>

    {{-- The configured letterhead, exactly as authored, so this receipt agrees
         with every other document the same office issues. --}}
    @include('partials.document-header', ['forPdf' => $forPdf ?? false, 'rule' => false])

    <div class="r-title">
        {{ __('Receipt for Payment') }}
        <small>{{ optional($fee->category)->title ?? __('Student Fee') }}</small>
    </div>

    <div class="r-meta">
        <div class="cell">
            <div class="k">{{ __('Receipt No.') }}</div>
            <div class="v">{{ str_pad($fee->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>
        <div class="cell">
            <div class="k">{{ __('Transaction Ref.') }}</div>
            <div class="v">{{ optional($receipt)->payment_reference ?: '—' }}</div>
        </div>
        <div class="cell">
            <div class="k">{{ __('Issued') }}</div>
            <div class="v">{{ optional(optional($receipt)->verified_at ?? optional($receipt)->created_at ?? $fee->pay_date)->format('Y-m-d H:i') ?? '—' }}</div>
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <div class="label">{{ __('Name') }}:</div>
            <div class="value">{{ trim(optional($student)->first_name . ' ' . optional($student)->last_name) ?: '—' }}</div>
        </div>
        <div class="field">
            <div class="label">{{ __('field_matricule') }}:</div>
            <div class="value">{{ optional($enrolment)->matricule ?? optional($student)->student_id ?? '—' }}</div>
        </div>
    </div>

    <div class="field">
        <div class="label">{{ __('Amount in Figures') }}:</div>
        <div class="value">{{ $currency }} {{ number_format($received, 2) }}</div>
    </div>

    <div class="field">
        <div class="label">{{ __('Amount in Words') }}:</div>
        <div class="value">{{ $service->inWords($received) }} {{ $currency }} {{ __('only') }}</div>
    </div>

    <div class="field">
        <div class="label">{{ __('Purpose for Deposit') }}:</div>
        <div class="value">
            {{ optional($fee->category)->title ?? __('Student Fee') }}
            @if($fee->resitRequest && $fee->resitRequest->subject)
                &middot; {{ __('Resit') }}: {{ $fee->resitRequest->subject->code }} {{ $fee->resitRequest->subject->title }}
            @endif
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <div class="label">{{ __('School / Faculty of') }}:</div>
            <div class="value">{{ optional($programme)->title ?? '—' }}</div>
        </div>
        <div class="field">
            <div class="label">{{ __('Level') }}:</div>
            <div class="value">{{ optional(optional($programme)->degreeType)->title ?? '—' }}</div>
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <div class="label">{{ __('Date') }}:</div>
            <div class="value">{{ optional(optional($receipt)->payment_date ?? $fee->pay_date)->format('Y-m-d') ?? '—' }}</div>
        </div>
        <div class="field">
            <div class="label">{{ __('Academic Year') }}:</div>
            <div class="value">{{ optional(optional($enrolment)->session)->title ?? '—' }}</div>
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <div class="label">{{ __('field_semester') }}:</div>
            <div class="value">{{ optional(optional($enrolment)->semester)->title ?? '—' }}</div>
        </div>
        <div class="field">
            <div class="label">{{ __('Payment Method') }}:</div>
            <div class="value">{{ optional($receipt)->payment_method_name ?? ($fee->payment_method ?? '—') }}</div>
        </div>
    </div>

    <div class="amount-box">
        <div>
            <div class="k">{{ __('Amount Received') }}</div>
            <div class="v">{{ $currency }} {{ number_format($received, 2) }}</div>
        </div>
        <div><span class="status {{ $status['class'] }}">{{ $status['label'] }}</span></div>
    </div>

    @if($legs->count() > 1)
    {{-- One handover settled more than one instalment. Without this the student
         is handed a receipt for 150,000 having given 350,000. --}}
    <table class="block">
        <caption>{{ __('This payment settled') }}</caption>
        <thead>
            <tr>
                <th>{{ __('field_fees_type') }}</th>
                <th class="num">{{ __('field_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($legs as $leg)
            <tr>
                <td>{{ optional(optional($leg->fee)->category)->title ?? __('field_fees') }}</td>
                <td class="num">{{ $currency }} {{ number_format((float) $leg->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total">
                <td>{{ __('field_total') }}</td>
                <td class="num">{{ $currency }} {{ number_format((float) $legs->sum('amount'), 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- This fee in full: what was charged, what has been paid against it, and
         what is left. The old receipt clamped these, so an overpayment simply
         vanished from the sheet. --}}
    <table class="block">
        <caption>{{ __('This fee') }}</caption>
        <tbody>
            <tr>
                <td>{{ __('field_fee') }}</td>
                <td class="num">{{ $currency }} {{ number_format((float) $fee->fee_amount, 2) }}</td>
            </tr>
            @if((float) $fee->discount_amount > 0)
            <tr>
                <td>{{ __('field_discount') }}</td>
                <td class="num">&minus; {{ $currency }} {{ number_format((float) $fee->discount_amount, 2) }}</td>
            </tr>
            @endif
            @if((float) $fee->fine_amount > 0)
            <tr>
                <td>{{ __('field_fine_amount') }}</td>
                <td class="num">+ {{ $currency }} {{ number_format((float) $fee->fine_amount, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>{{ __('field_net_amount') }}</td>
                <td class="num">{{ $currency }} {{ number_format($charged, 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('field_amount_paid') }}</td>
                <td class="num">{{ $currency }} {{ number_format($paid, 2) }}</td>
            </tr>
            @if($overpaid > 0.005)
            <tr class="settled">
                <td>{{ __('overpayment_amount') }}</td>
                <td class="num">{{ $currency }} {{ number_format($overpaid, 2) }}</td>
            </tr>
            @endif
            @foreach($credits as $credit)
            <tr class="settled">
                <td>{{ __('overpayment_credit_generated') }}</td>
                <td class="num">{{ $currency }} {{ number_format((float) $credit->original_amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total {{ $outstanding > 0.005 ? 'owing' : 'settled' }}">
                <td>{{ __('field_remaining_balance') }}</td>
                <td class="num">{{ $currency }} {{ number_format($outstanding, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($instalments->count() > 1)
    {{-- Where the student stands overall, so one sheet answers the question
         rather than needing a receipt per instalment. --}}
    <table class="block">
        <caption>{{ __('Your instalments') }}</caption>
        <thead>
            <tr>
                <th>{{ __('field_fees_type') }}</th>
                <th>{{ __('field_due_date') }}</th>
                <th class="num">{{ __('field_fee') }}</th>
                <th class="num">{{ __('field_amount_paid') }}</th>
                <th class="num">{{ __('field_remaining_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $owedTotal = 0; @endphp
            @foreach($instalments as $instalment)
                @php
                    $due = (float) $instalment->total_amount;
                    $done = (float) $instalment->paid_amount;
                    $left = max(0, $due - $done);
                    $owedTotal += $left;
                @endphp
                <tr class="{{ $left > 0.005 ? 'owing' : 'settled' }}">
                    <td>{{ optional($instalment->category)->title }}</td>
                    <td>{{ $instalment->due_date ? \Carbon\Carbon::parse($instalment->due_date)->format('d M Y') : '—' }}</td>
                    <td class="num">{{ number_format($due, 2) }}</td>
                    <td class="num">{{ number_format($done, 2) }}</td>
                    <td class="num">{{ number_format($left, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total {{ $owedTotal > 0.005 ? 'owing' : 'settled' }}">
                <td colspan="4">{{ __('Total still outstanding') }}</td>
                <td class="num">{{ $currency }} {{ number_format($owedTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if($fee->paymentPlan && $fee->paymentPlan->installments->count())
    <table class="block">
        <caption>{{ __('payment_plan') }}</caption>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('field_due_date') }}</th>
                <th class="num">{{ __('field_amount') }}</th>
                <th class="num">{{ __('field_amount_paid') }}</th>
                <th>{{ __('field_status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($fee->paymentPlan->installments as $index => $instalment)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $instalment->due_date ? \Carbon\Carbon::parse($instalment->due_date)->format('d M Y') : '—' }}</td>
                <td class="num">{{ number_format((float) $instalment->amount, 2) }}</td>
                <td class="num">{{ number_format((float) $instalment->paid_amount, 2) }}</td>
                <td>{{ ucfirst((string) $instalment->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="sig">
        <div class="box">
            <div class="label">{{ __('Signature of Depositor') }}</div>
            <div class="line">{{ trim(optional($student)->first_name . ' ' . optional($student)->last_name) ?: '—' }}</div>
        </div>
        <div class="box">
            <div class="label">{{ __('Signature of Cashier') }}</div>
            <div class="line">
                {{ optional(optional($receipt)->verifier)->name ?? __('System (auto-verified)') }}
                <br><small style="color:#64748b">{{ optional(optional($receipt)->verified_at)->format('Y-m-d H:i') }}</small>
            </div>
        </div>
    </div>

    @if($verificationUrl)
    <div class="verify">
        <div>
            <div style="text-transform:uppercase;letter-spacing:.1em;color:#64748b">{{ __('Verification Code') }}</div>
            <div class="code">{{ $verificationCode }}</div>
            <div style="margin-top:4px;color:#475569">{{ __('Scan or verify at') }}: {{ $verificationUrl }}</div>
        </div>
        <div class="qr">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($verificationUrl) }}" alt="QR">
        </div>
    </div>
    @endif

    <div class="footer">
        {{ __('This is a digitally generated receipt and is valid without a physical signature.') }}<br>
        {{ __('Generated on') }} {{ now()->format('Y-m-d H:i:s') }}
    </div>
</div>

</body>
</html>
