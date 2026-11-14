{{--
    The payment plan agreement, as signed.

    Printed straight after the Finance Director prepares it, who signs as the one
    who prepared it, the student signs the undertaking, and the Vice Chancellor
    signs to approve. Until the approval is recorded the sheet is stamped
    "Awaiting approval"; reprinted afterwards, it carries the approver's name.

    When an approved agreement has been revised, or one of its fees changed,
    the sheet lists what changed since the last approval, so the Vice
    Chancellor approves the change knowingly rather than a fresh-looking page.

    The verification code covers the reference, the revision and every line of
    the schedule: a sheet with an altered figure does not verify, and an older
    revision verifies as genuine but out of date.
--}}
@php
    $student = $agreement->student;
    $first = optional($agreement->plans->first())->fee;
    $enrolment = optional($first)->studentEnroll;
    $programme = optional($enrolment)->program;
    $timeline = $agreement->timeline();
    $money = fn ($v) => number_format((float) $v, 2);
    $planner = app(\App\Services\PaymentPlanAgreements::class);
    $approved = $agreement->isApproved();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $agreement->reference }} · {{ $setting->title ?? config('app.name') }}</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#e9edf1;font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#1f2a44}
        .toolbar{max-width:820px;margin:14px auto;text-align:right;padding:0 8px}
        .toolbar button,.toolbar a{border:0;background:#224abe;color:#fff;padding:7px 13px;border-radius:4px;text-decoration:none;font-size:13px;cursor:pointer;margin-left:6px}
        .toolbar a.secondary{background:#6c757d}

        /* Compact, as the fee receipt is: the same letterhead at the same
           scale, one line per detail, and no space that carries nothing. */
        .sheet{max-width:820px;margin:0 auto 28px;background:#fff;padding:16px 24px 12px;box-shadow:0 6px 24px rgba(0,0,0,.08);font-size:11px;line-height:1.3;position:relative}

        .masthead{zoom:.7;font-size:16px;line-height:normal;margin-bottom:2px}
        .masthead .doc-masthead{margin:0}
        .masthead .letterhead table{table-layout:fixed}
        .masthead .letterhead td:first-child:nth-last-child(3),
        .masthead .letterhead td:last-child:nth-child(3){width:41.5% !important}
        .masthead .letterhead td:nth-child(2):nth-last-child(2){width:17% !important}

        .doc-title{display:flex;justify-content:space-between;align-items:center;gap:12px;border-top:2px solid #1e293b;border-bottom:1px solid #cbd5e1;padding:3px 0;margin-bottom:5px}
        .doc-title .name{font-weight:700;letter-spacing:.14em;text-transform:uppercase;font-size:13px;color:#0f172a}
        .doc-title .ref{font-size:11px;color:#475569}
        .stamp{border:1.5px solid #b45309;color:#b45309;padding:2px 8px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;font-size:10px;white-space:nowrap}
        .stamp.ok{border-color:#15803d;color:#15803d}
        .stamp.closed{border-color:#b91c1c;color:#b91c1c}

        .who{display:grid;grid-template-columns:1.45fr 1fr 1fr;gap:2px 14px;margin-bottom:5px}
        .who > div{display:flex;gap:6px;align-items:baseline;border-bottom:1px solid #e2e8f0;padding-bottom:1px;min-width:0}
        .who .k{flex:0 0 auto;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
        .who .v{font-weight:600;color:#0f172a}

        h3{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin:6px 0 2px;font-weight:600}
        .covered{font-size:11px;margin:2px 0 4px}
        .covered .cap{font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:10px;color:#64748b;margin-right:4px}
        .covered .fee + .fee::before{content:" · ";color:#94a3b8}
        table.block{width:100%;border-collapse:collapse;font-size:10.5px}
        table.block th,table.block td{border-bottom:1px solid #e2e8f0;padding:1px 6px;text-align:left}
        table.block th{background:#f8fafc;color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
        table.block .num{text-align:right;white-space:nowrap}
        table.block tr.total td{font-weight:700;border-top:1.5px solid #0f172a;border-bottom:0}

        .changes{border:1px solid #f59e0b;background:#fffbeb;padding:4px 8px;margin-top:6px;font-size:10.5px}
        .changes ul{margin:2px 0 0;padding-left:16px}

        .undertaking{margin-top:7px;padding:4px 8px;border-left:3px solid #1e293b;background:#f8fafc;font-size:11px}

        /* Room to sign — about a centimetre — and nothing more. */
        .sigs{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:8px}
        .sig .role{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
        .sig .space{height:32px;border-bottom:1px solid #475569}
        .sig .who-signs{font-weight:600;margin-top:2px;min-height:14px}
        .sig .when{font-size:10px;color:#64748b}

        .bottom{display:flex;align-items:center;gap:10px;border-top:1px dashed #94a3b8;margin-top:8px;padding-top:4px;font-size:10px;color:#475569}
        .bottom .code{font-family:'Courier New',monospace;font-size:11.5px;letter-spacing:.12em;font-weight:700;color:#0f172a}
        .bottom .url{word-break:break-all}
        .bottom img{width:54px;height:54px;flex:0 0 54px;margin-left:auto}

        @media print{
            @page{size:A4;margin:10mm}
            html,body{background:#fff}
            .toolbar{display:none}
            .sheet{box-shadow:none;margin:0;padding:0;max-width:100%}
            table.block th,.undertaking,.changes{background:#fff !important}
            table.block,.sigs,.bottom,.undertaking{page-break-inside:avoid}
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ route('admin.payment-plan.agreement.show', $agreement->id) }}" class="secondary">&larr; {{ __('Back to the agreement') }}</a>
    <button onclick="window.print()">{{ __('Print') }}</button>
</div>

<div class="sheet">
    <div class="masthead">
        @include('partials.document-header', ['forPdf' => false, 'rule' => false])
    </div>

    <div class="doc-title">
        <div>
            <div class="name">{{ __('Payment Plan Agreement') }}</div>
            <div class="ref">
                {{ __('Reference') }} <b>{{ $agreement->reference }}</b>
                @if($agreement->version > 1) &middot; {{ __('Revision :n', ['n' => $agreement->version]) }} @endif
                &middot; {{ __('Prepared') }} {{ optional($agreement->created_at)->format('d M Y') }}
            </div>
        </div>
        @if($approved)
            <span class="stamp ok">{{ __('Approved') }} {{ optional($agreement->approved_at)->format('d M Y') }}</span>
        @elseif($agreement->isPending())
            <span class="stamp">{{ $agreement->wasApproved() ? __('Awaiting re-approval') : __('Awaiting approval') }}</span>
        @else
            <span class="stamp closed">{{ $agreement->status_label }}</span>
        @endif
    </div>

    <div class="who">
        <div><span class="k">{{ __('Name') }}</span><span class="v">{{ trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) }}</span></div>
        <div><span class="k">{{ __('field_matricule') }}</span><span class="v">{{ $enrolment->matricule ?? ($student->student_id ?? '—') }}</span></div>
        <div><span class="k">{{ __('Academic Year') }}</span><span class="v">{{ optional(optional($enrolment)->session)->title ?? '—' }}</span></div>
        <div><span class="k">{{ __('field_program') }}</span><span class="v">{{ optional($programme)->title ?? '—' }}</span></div>
        <div><span class="k">{{ __('Level') }}</span><span class="v">{{ optional(optional($programme)->degreeType)->title ?? '—' }}</span></div>
        <div><span class="k">{{ __('Phone') }}</span><span class="v">{{ $student->phone ?? '—' }}</span></div>
    </div>

    {{-- The fees, on one line. The schedule below already lists every
         payment; a table here only repeated it. What was paid before the
         plan is said only when there was some. --}}
    <div class="covered" id="fees-covered">
        <span class="cap">{{ __('Fees covered') }}:</span>
        @foreach($agreement->plans as $plan)
            @php
                $fee = $plan->fee;
                $net = (float) optional($fee)->total_amount;
                $before = max(0, $net - $planner->expectedPlanTotal($plan));
            @endphp
            <span class="fee">
                {{ optional(optional($fee)->category)->title ?? '—' }} <b>{{ $money($net) }}</b>@if($before > 0.005),
                    {{ __(':paid already paid, :left under this agreement', ['paid' => $money($before), 'left' => $money($plan->total_amount)]) }}@endif
            </span>
        @endforeach
        <span class="fee">{{ __('Total under this agreement') }} <b>{{ $currency }} {{ $money($agreement->total) }}</b></span>
    </div>

    <h3>{{ __('Schedule of payments') }}</h3>
    <table class="block" id="schedule">
        <thead>
            <tr>
                <th style="width:34px">#</th>
                <th>{{ __('Pay on or before') }}</th>
                <th>{{ __('field_fees_type') }}</th>
                <th class="num">{{ __('field_amount') }} ({{ $currency }})</th>
                @if($timeline->contains(fn ($row) => (float) $row['installment']->paid_amount > 0))
                    <th class="num">{{ __('field_paid_amount') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php $showPaid = $timeline->contains(fn ($row) => (float) $row['installment']->paid_amount > 0); @endphp
            @foreach($timeline as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ optional($row['installment']->due_date)->format('d M Y') }}</td>
                <td>{{ optional(optional($row['fee'])->category)->title }}</td>
                <td class="num">{{ $money($row['installment']->amount) }}</td>
                @if($showPaid)<td class="num">{{ $money($row['installment']->paid_amount) }}</td>@endif
            </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">{{ __('total') }}</td>
                <td class="num">{{ $money($timeline->sum(fn ($row) => (float) $row['installment']->amount)) }}</td>
                @if($showPaid)<td class="num">{{ $money($timeline->sum(fn ($row) => (float) $row['installment']->paid_amount)) }}</td>@endif
            </tr>
        </tbody>
    </table>

    @if($changes)
    <div class="changes" id="changes">
        <strong>{{ __('Changed since it was last approved') }}</strong>
        @if($agreement->review_reason) &mdash; {{ $agreement->review_reason }} @endif
        <ul>
            @foreach($changes as $line)<li>{{ $line }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="undertaking" id="undertaking">
        {{ __('I, :name, agree to pay the amounts above on or before the dates shown. If I miss a payment, the full balance becomes due.', [
            'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
        ]) }}
    </div>

    <div class="sigs">
        <div class="sig" id="sig-finance-director">
            <div class="role">{{ __('Prepared by the Finance Director') }}</div>
            <div class="space"></div>
            <div class="who-signs">{{ optional($agreement->creator)->name ?? '' }}</div>
            <div class="when">{{ __('Date') }}: {{ optional($agreement->created_at)->format('d M Y') }}</div>
        </div>
        <div class="sig" id="sig-student">
            <div class="role">{{ __('Student') }}</div>
            <div class="space"></div>
            <div class="who-signs">{{ trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) }}</div>
            <div class="when">{{ __('Date') }}: ____________</div>
        </div>
        <div class="sig" id="sig-vc">
            <div class="role">{{ __('Approved by the Vice Chancellor') }}</div>
            <div class="space"></div>
            <div class="who-signs">{{ $approved ? optional($agreement->approver)->name : '' }}</div>
            <div class="when">{{ __('Date') }}: {{ $approved ? optional($agreement->approved_at)->format('d M Y') : '____________' }}</div>
        </div>
    </div>

    <div class="bottom">
        <div>
            {{ __('Verification Code') }} <span class="code">{{ $verificationCode }}</span> &middot; <span class="url">{{ $verificationUrl }}</span><br>
            {{ $agreement->reference }} &middot; {{ __('Revision :n', ['n' => $agreement->version]) }} &middot; {{ __('Generated on') }} {{ now()->format('Y-m-d H:i') }}
        </div>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($verificationUrl) }}" alt="QR">
    </div>
</div>

<script>
document.querySelectorAll('.masthead .letterhead p').forEach(function (p) {
    if (!p.querySelector('img') && p.textContent.replace(/ /g, ' ').trim() === '') { p.remove(); }
});
</script>
</body>
</html>
