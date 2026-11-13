{{--
    A student fee receipt.

    Built to the same shape as the admission receipt, because a school should
    issue one kind of document. It no longer reads print_settings: that carried a
    second copy of the school's name and logo which disagreed with the letterhead
    every other document uses. The letterhead is the single source now.

    The same view serves the bursar's screen and the student's own portal, so
    what the office prints and what the student downloads are the same sheet.

    Compact by design: everything fits in half an A4 page, so a receipt prints
    on A5 or two to a sheet. Nothing a reader needs was dropped to get there —
    what went was repetition. The amount was printed three times, "This fee"
    restated the instalment row beneath it, and the purpose repeated the title.

    Expects: fee, handover, legs, plan, instalments, credits, setting, currency,
             verificationCode, verificationUrl, status  (see App\Services\FeeReceipt)
--}}
@php
    $service = app(\App\Services\FeeReceipt::class);
    $student = optional($fee->studentEnroll)->student;
    $programme = optional($fee->studentEnroll)->program;
    $enrolment = $fee->studentEnroll;
    $handover = $handover ?? null;
    $plan = $plan ?? null;
    $verificationUrl = $verificationUrl ?? null;

    // What the student handed over, and when. A fee with no recorded payment
    // row at all — older data — falls back to what the fee itself says.
    $received = $handover ? (float) $handover['total'] : (float) ($fee->paid_amount ?? 0);
    $paidOn = $handover ? $handover['date'] : $fee->pay_date;
    $method = $handover['method'] ?? \App\Support\FeesPaymentMethod::label($fee->payment_method);
    $reference = $handover['reference'] ?? null;
    $cashier = $handover['cashier'] ?? null;
    $recordedAt = $handover['recorded_at'] ?? null;
    $lines = $handover ? $handover['legs'] : collect();

    $charged = (float) $fee->total_amount;
    $paid = (float) ($fee->paid_amount ?? 0);
    $outstanding = max(0, $charged - $paid);
    $overpaid = max(0, $paid - $charged);

    // Shown inside the instalments table when this fee is one of them;
    // otherwise this fee gets a line of its own.
    $inInstalments = $instalments->count() > 1 && $instalments->contains('id', $fee->id);
    $planInTable = $plan && $inInstalments;

    $money = fn ($value) => number_format((float) $value, 2);
    $day = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d M Y') : '—';
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
        .toolbar{max-width:820px;margin:14px auto;text-align:right;padding:0 8px}
        .toolbar button,.toolbar a{border:0;background:#224abe;color:#fff;padding:7px 13px;border-radius:4px;text-decoration:none;font-size:13px;cursor:pointer;margin-left:6px}
        .toolbar a.secondary{background:#6c757d}

        .sheet{max-width:820px;margin:0 auto 28px;background:#fff;padding:16px 24px 12px;box-shadow:0 6px 24px rgba(0,0,0,.08);font-size:11px;line-height:1.3}

        /* The configured letterhead, exactly as authored, at receipt scale.
           Its blank spacer lines are dropped (see the script at the end);
           nothing it says is changed. */
        .masthead{zoom:.7;font-size:16px;line-height:normal;margin-bottom:2px}
        .masthead .doc-masthead{margin:0}
        /* The configured letterhead lays its contacts, crest and address out in
           three columns of 47%, 17% and 35%, so the crest sat right of the
           school name above it. On the receipt the two side columns are made
           equal, which puts the crest under the name. Only a three-column row
           is touched; what the letterhead says is not. */
        .masthead .letterhead table{table-layout:fixed}
        .masthead .letterhead td:first-child:nth-last-child(3),
        .masthead .letterhead td:last-child:nth-child(3){width:41.5% !important}
        .masthead .letterhead td:nth-child(2):nth-last-child(2){width:17% !important}

        .r-title{display:flex;justify-content:space-between;align-items:center;gap:10px;border-top:2px solid #1e293b;border-bottom:1px solid #cbd5e1;padding:4px 0;margin-bottom:6px}
        .r-title .name{font-weight:700;letter-spacing:.14em;text-transform:uppercase;font-size:13px;color:#0f172a}
        .r-title .name small{font-weight:500;letter-spacing:.02em;text-transform:none;color:#475569;font-size:12px;margin-left:6px}
        .tag{display:inline-block;border:1px solid #94a3b8;border-radius:10px;padding:1px 8px;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#334155;margin-left:4px;white-space:nowrap}
        .tag.plan{border-color:#1d4ed8;color:#1d4ed8}

        .r-meta{display:flex;flex-wrap:wrap;gap:2px 16px;font-size:11px;color:#475569;margin-bottom:4px}
        .r-meta b{color:#0f172a;font-weight:600}

        .who{display:grid;grid-template-columns:1.45fr 1fr 1fr;gap:3px 14px;margin-bottom:6px}
        .who > div{display:flex;gap:6px;align-items:baseline;border-bottom:1px solid #e2e8f0;padding-bottom:1px;min-width:0}
        .who .k{flex:0 0 auto;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
        .who .v{font-weight:600;color:#0f172a;min-width:0}

        .amount-box{display:flex;justify-content:space-between;align-items:center;gap:12px;background:#0f172a;color:#f8fafc;padding:4px 12px;border-radius:4px;margin:0 0 6px}
        .amount-box .figure{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
        .amount-box .k{text-transform:uppercase;letter-spacing:.1em;font-size:10px;opacity:.8}
        .amount-box .v{font-size:17px;font-weight:700;letter-spacing:.02em;line-height:1.15}
        .amount-box .words{font-size:11px;opacity:.9}
        .amount-box .status{background:#16a34a;color:#fff;padding:3px 9px;border-radius:12px;font-size:10px;text-transform:uppercase;letter-spacing:.1em;white-space:nowrap}
        .amount-box .status.partial{background:#f59e0b}
        .amount-box .status.unpaid{background:#dc2626}

        table.block{width:100%;border-collapse:collapse;margin:0 0 6px;font-size:10.5px}
        table.block caption{text-align:left;font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:10px;color:#64748b;padding-bottom:2px}
        table.block th,table.block td{border-bottom:1px solid #e2e8f0;padding:1px 6px;text-align:left}
        table.block th{background:#f8fafc;color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
        table.block td.num,table.block th.num{text-align:right;white-space:nowrap}
        table.block tr.total td{font-weight:700;border-top:1.5px solid #0f172a;border-bottom:0}
        table.block tr.settled td{color:#15803d}
        table.block tr.owing td{color:#b45309}
        table.block tr.sub td{color:#475569;font-size:10.5px;border-bottom-style:dotted}
        table.block tr.sub td:first-child{padding-left:18px}
        table.block tr.here td{font-weight:700}
        .note{font-size:10.5px;color:#475569;margin:-3px 0 6px}
        .settled-line{font-size:10.5px;margin:0 0 6px;color:#0f172a}
        .settled-line .cap{font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:10px;color:#64748b;margin-right:4px}
        .settled-line .leg{white-space:nowrap}
        .settled-line .leg + .leg::before{content:" · ";color:#94a3b8;white-space:normal}

        .bottom{display:flex;gap:16px;align-items:flex-end;margin-top:8px}
        .sig{flex:1;display:flex;gap:16px}
        .sig .box{flex:1}
        .sig .box .label{font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:12px}
        .sig .box .line{border-top:1px solid #475569;padding-top:2px;font-size:11px}
        .sig .box small{color:#64748b;font-size:10px}

        .verify{flex:0 0 auto;display:flex;align-items:center;gap:8px;border:1px dashed #94a3b8;padding:4px 6px;font-size:10px;max-width:46%}
        .verify .code{font-family:'Courier New',monospace;font-size:12px;color:#0f172a;letter-spacing:.12em;font-weight:700}
        .verify .url{color:#475569;word-break:break-all;font-size:10px}
        .verify img{width:54px;height:54px;flex:0 0 54px}

        .footer{margin-top:6px;text-align:center;font-size:10px;color:#64748b}

        @media print{
            /* The page itself supplies the margin, so the sheet does not have
               to, and nothing is paid for twice. */
            @page{size:A4;margin:10mm}
            html,body{background:#fff}
            .toolbar{display:none}
            .sheet{box-shadow:none;margin:0;padding:0;max-width:100%}

            /* Browsers do not print background colours by default, and the
               setting is off in most print dialogs. Anything that relied on a
               background for its contrast therefore inverts for paper rather
               than printing its own text on white. */
            .amount-box{background:#fff !important;color:#0f172a !important;border:1.5px solid #0f172a}
            .amount-box .k,.amount-box .words{opacity:1;color:#475569 !important}
            .amount-box .status{background:#fff !important;color:#0f172a !important;border:1px solid #0f172a}
            table.block th{background:#fff !important}

            /* A table, the signatures or the verification split across a page
               break is worse than a slightly longer document. */
            table.block,.bottom,.amount-box{page-break-inside:avoid}
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ url()->previous() }}" class="secondary">&larr; {{ __('Back') }}</a>
    <button onclick="window.print()">{{ __('Print') }}</button>
</div>

<div class="sheet">
    <div class="masthead">
        @include('partials.document-header', ['forPdf' => $forPdf ?? false, 'rule' => false])
    </div>

    <div class="r-title">
        <div class="name">
            {{ __('Receipt for Payment') }}
            <small>{{ optional($fee->category)->title ?? __('Student Fee') }}
                @if($fee->resitRequest && $fee->resitRequest->subject)
                    &middot; {{ __('Resit') }}: {{ $fee->resitRequest->subject->code }} {{ $fee->resitRequest->subject->title }}
                @endif
            </small>
        </div>
        <div>
            @if($plan)
                {{-- The fee is being paid, or was paid, through a payment
                     plan. Stays on the receipt after the plan is paid off. --}}
                <span class="tag plan">{{ optional($plan->agreement)->reference ?? __('Payment plan') }} &middot; {{ $plan->isPending() ? __('Awaiting approval') : __('status_' . $plan->status) }}</span>
            @endif
            <span class="tag">{{ __('Digital Receipt') }}</span>
        </div>
    </div>

    <div class="r-meta">
        <span>{{ __('Receipt No.') }} <b>{{ str_pad($fee->id, 6, '0', STR_PAD_LEFT) }}</b></span>
        <span>{{ __('Ref.') }} <b>{{ $reference ?: '—' }}</b></span>
        <span>{{ __('Paid on') }} <b>{{ $paidOn ? \Carbon\Carbon::parse($paidOn)->format('Y-m-d') : '—' }}</b></span>
        <span>{{ __('Payment Method') }} <b>{{ $method ?: '—' }}</b></span>
    </div>

    <div class="who">
        <div>
            <div class="k">{{ __('Name') }}</div>
            <div class="v">{{ trim(optional($student)->first_name . ' ' . optional($student)->last_name) ?: '—' }}</div>
        </div>
        <div>
            <div class="k">{{ __('field_matricule') }}</div>
            <div class="v">{{ optional($enrolment)->matricule ?? optional($student)->student_id ?? '—' }}</div>
        </div>
        <div>
            <div class="k">{{ __('Academic Year') }}</div>
            <div class="v">{{ optional(optional($enrolment)->session)->title ?? '—' }}</div>
        </div>
        <div>
            <div class="k">{{ __('field_program') }}</div>
            <div class="v">{{ optional($programme)->title ?? '—' }}</div>
        </div>
        <div>
            <div class="k">{{ __('Level') }}</div>
            <div class="v">{{ optional(optional($programme)->degreeType)->title ?? '—' }}</div>
        </div>
        <div>
            <div class="k">{{ __('field_semester') }}</div>
            <div class="v">{{ optional(optional($enrolment)->semester)->title ?? '—' }}</div>
        </div>
    </div>

    <div class="amount-box">
        <div>
            <div class="k">{{ __('Amount Received') }}</div>
            <div class="figure">
                <span class="v">{{ $currency }} {{ $money($received) }}</span>
                <span class="words">{{ $service->inWords($received) }} {{ $currency }} {{ __('only') }}</span>
            </div>
        </div>
        <div><span class="status {{ $status['class'] }}">{{ $status['label'] }}</span></div>
    </div>

    @if($lines->count() > 1)
    {{-- One handover settled more than one line. Without this the student
         is handed a receipt for one instalment having paid for several. The
         lines add up to the amount received above. --}}
    <div class="settled-line">
        <span class="cap">{{ __('This payment settled') }}:</span>
        @foreach($lines as $line)
            <span class="leg">{{ $line['label'] }} <b>{{ $currency }} {{ $money($line['amount']) }}</b></span>
        @endforeach
    </div>
    @endif

    @if($inInstalments)
    {{-- Where the student stands overall, so one sheet answers the question
         rather than needing a receipt per instalment. This fee is the bold
         row; its payment plan, if it has one, is laid out beneath it. --}}
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
                <tr class="{{ $left > 0.005 ? 'owing' : 'settled' }} {{ $instalment->id === $fee->id ? 'here' : '' }}">
                    <td>{{ optional($instalment->category)->title }}</td>
                    <td>{{ $day($instalment->due_date) }}</td>
                    <td class="num">{{ $money($due) }}</td>
                    <td class="num">{{ $money($done) }}</td>
                    <td class="num">{{ $money($left) }}</td>
                </tr>
                @if($planInTable && $instalment->id === $fee->id)
                    @foreach($plan->installments->sortBy('installment_number') as $step)
                    <tr class="sub">
                        <td>{{ __('Plan instalment :number of :count', ['number' => $step->installment_number, 'count' => $plan->installments->count()]) }} &middot; {{ ucfirst((string) $step->status) }}</td>
                        <td>{{ $day($step->due_date) }}</td>
                        <td class="num">{{ $money((float) $step->amount + (float) ($step->late_fee ?? 0)) }}</td>
                        <td class="num">{{ $money($step->paid_amount) }}</td>
                        <td class="num">{{ $money($step->remaining_balance) }}</td>
                    </tr>
                    @endforeach
                @endif
            @endforeach
            <tr class="total {{ $owedTotal > 0.005 ? 'owing' : 'settled' }}">
                <td colspan="4">{{ __('Total still outstanding') }}</td>
                <td class="num">{{ $currency }} {{ $money($owedTotal) }}</td>
            </tr>
        </tbody>
    </table>
    @else
    {{-- Not one of the student's instalments (an admission or resit fee, or
         a student with a single instalment): this fee on one line. --}}
    <table class="block">
        <caption>{{ __('This fee') }}</caption>
        <thead>
            <tr>
                <th>{{ __('field_fees_type') }}</th>
                <th class="num">{{ __('field_net_amount') }}</th>
                <th class="num">{{ __('field_amount_paid') }}</th>
                <th class="num">{{ __('field_remaining_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="here {{ $outstanding > 0.005 ? 'owing' : 'settled' }}">
                <td>{{ optional($fee->category)->title ?? __('Student Fee') }}</td>
                <td class="num">{{ $money($charged) }}</td>
                <td class="num">{{ $money($paid) }}</td>
                <td class="num">{{ $money($outstanding) }}</td>
            </tr>
            @if($plan)
                @foreach($plan->installments->sortBy('installment_number') as $step)
                <tr class="sub">
                    <td>{{ __('Plan instalment :number of :count', ['number' => $step->installment_number, 'count' => $plan->installments->count()]) }} &middot; {{ $day($step->due_date) }} &middot; {{ ucfirst((string) $step->status) }}</td>
                    <td class="num">{{ $money((float) $step->amount + (float) ($step->late_fee ?? 0)) }}</td>
                    <td class="num">{{ $money($step->paid_amount) }}</td>
                    <td class="num">{{ $money($step->remaining_balance) }}</td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>
    @endif

    {{-- What changed this fee's figure, said once and only when it applies.
         The old receipt clamped an overpayment away entirely. --}}
    @php
        $notes = [];
        if ((float) $fee->discount_amount > 0) {
            $notes[] = __('Discount') . ' ' . $currency . ' ' . $money($fee->discount_amount);
        }
        if ((float) $fee->fine_amount > 0) {
            $notes[] = __('Late fee') . ' ' . $currency . ' ' . $money($fee->fine_amount);
        }
        if ($overpaid > 0.005) {
            $notes[] = __('overpayment_amount') . ' ' . $currency . ' ' . $money($overpaid);
        }
        foreach ($credits as $credit) {
            $notes[] = __('overpayment_credit_generated') . ' ' . $currency . ' ' . $money($credit->original_amount);
        }
    @endphp
    @if($notes)
        <div class="note">{{ optional($fee->category)->title }}: {{ implode(' · ', $notes) }}</div>
    @endif

    <div class="bottom">
        <div class="sig">
            <div class="box">
                <div class="label">{{ __('Signature of Depositor') }}</div>
                <div class="line">{{ trim(optional($student)->first_name . ' ' . optional($student)->last_name) ?: '—' }}</div>
            </div>
            <div class="box">
                <div class="label">{{ __('Signature of Cashier') }}</div>
                <div class="line">
                    {{ $cashier ?? __('System (auto-verified)') }}
                    @if($recordedAt)<br><small>{{ \Carbon\Carbon::parse($recordedAt)->format('Y-m-d H:i') }}</small>@endif
                </div>
            </div>
        </div>

        @if($verificationUrl)
        <div class="verify">
            <div>
                <div style="text-transform:uppercase;letter-spacing:.08em;color:#64748b">{{ __('Verification Code') }}</div>
                <div class="code">{{ $verificationCode }}</div>
                <div class="url">{{ $verificationUrl }}</div>
            </div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($verificationUrl) }}" alt="QR">
        </div>
        @endif
    </div>

    <div class="footer">
        {{ __('This is a digitally generated receipt and is valid without a physical signature.') }}
        {{ __('Generated on') }} {{ now()->format('Y-m-d H:i') }}
    </div>
</div>

<script>
// The letterhead's blank spacer lines are an editor's way of adding air on a
// full page. At receipt scale they are just empty height, so they go — from
// this sheet only, never from the configured letterhead.
document.querySelectorAll('.masthead .letterhead p').forEach(function (p) {
    if (!p.querySelector('img') && p.textContent.replace(/ /g, ' ').trim() === '') {
        p.remove();
    }
});
</script>
</body>
</html>
