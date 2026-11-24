<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Pay Slip</title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #2d3436; }
    table { width: 100%; border-collapse: collapse; }
    .head td { background: #1f2a3c; color: #fff; padding: 14px 16px; }
    .head .inst { font-size: 15px; font-weight: bold; }
    .head .sub { font-size: 9px; color: #c8d0dc; }
    .head .title { font-size: 17px; font-weight: bold; text-align: right; letter-spacing: 1px; }
    .head .period { text-align: right; font-size: 10px; color: #dfe6ee; }
    .bar { background: #eef1f5; font-weight: bold; font-size: 9.5px; letter-spacing: .5px; text-transform: uppercase; padding: 6px 10px; }
    .info td { border-bottom: 1px solid #e6e9ee; padding: 5px 10px; vertical-align: top; }
    .info .lbl { color: #7f8c8d; font-size: 8.5px; text-transform: uppercase; width: 18%; }
    .info .val { width: 32%; }
    .cols > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
    .lines td { padding: 5px 10px; border-bottom: 1px solid #f0f2f5; }
    .lines .amt { text-align: right; white-space: nowrap; }
    .lines .sub td { font-weight: bold; background: #f7f8fa; border-top: 1px solid #dfe3e8; }
    .earn-h { background: #e6f4ea; color: #1e7e34; }
    .ded-h { background: #fdecea; color: #b02a37; }
    .small { color: #8a949e; font-size: 8.5px; }
    .sum td { padding: 6px 12px; border-bottom: 1px solid #eef0f3; }
    .sum .amt { text-align: right; }
    .net td { background: #17a673; color: #fff; font-size: 13px; font-weight: bold; padding: 10px 12px; }
    .net .amt { text-align: right; font-size: 15px; }
    .emp-h { background: #fff8e1; color: #856404; }
    .cost td { background: #2c3e50; color: #fff; font-weight: bold; padding: 8px 12px; }
    .sig td { width: 33%; text-align: center; padding-top: 34px; font-size: 9.5px; }
    .sig .line { border-top: 1px solid #555; margin: 0 14px; padding-top: 4px; }
    .foot { margin-top: 16px; text-align: center; color: #8a949e; font-size: 8.5px; }
</style>
</head>
<body>
@php
    $dp = $setting->decimal_place ?? 2;
    $cs = $setting->currency_symbol ?? '';
    $money = fn ($v) => number_format((float) $v, $dp);
    $staffTaxes = [];
    $employerTaxes = [];
    foreach ($tax_lines as $line) {
        $shared = $line['employee'] > 0 && $line['employer'] > 0;
        if ($line['employee'] > 0) { $staffTaxes[] = ['title' => $line['label'], 'amount' => $line['employee'], 'shared' => $shared]; }
        if ($line['employer'] > 0) { $employerTaxes[] = ['title' => $line['label'], 'amount' => $line['employer'], 'shared' => $shared]; }
    }
    $allowances = $row->details->where('status', 1);
    $deductions = $row->details->where('status', 0);
    $totalEarnings = (float) $row->total_earning + $allowances->sum('amount');
    $methods = [1 => __('payment_method_card'), 2 => __('payment_method_cash'), 3 => __('payment_method_cheque'), 4 => __('payment_method_bank'), 5 => __('payment_method_e_wallet')];
@endphp

<table class="head"><tr>
    <td>
        <div class="inst">{{ $setting->title ?? 'Institution' }}</div>
        <div class="sub">{{ $setting->address ?? '' }}@if(!empty($setting->phone)) &bull; {{ $setting->phone }}@endif @if(!empty($setting->email)) &bull; {{ $setting->email }}@endif</div>
    </td>
    <td>
        <div class="title">PAY SLIP</div>
        <div class="period">{{ date('F Y', strtotime($row->salary_month)) }} &middot; Ref: PS-{{ str_pad($row->id, 5, '0', STR_PAD_LEFT) }}</div>
    </td>
</tr></table>

<div class="bar">Employee information</div>
<table class="info">
    <tr><td class="lbl">Employee name</td><td class="val"><strong>{{ $row->user->first_name ?? '' }} {{ $row->user->last_name ?? '' }}</strong></td><td class="lbl">Staff ID</td><td class="val">{{ $row->user->staff_id ?? '-' }}</td></tr>
    <tr><td class="lbl">Department</td><td class="val">{{ $row->user->department->title ?? '-' }}</td><td class="lbl">Designation</td><td class="val">{{ $row->user->designation->title ?? '-' }}</td></tr>
    <tr><td class="lbl">Salary type</td><td class="val">{{ $row->salary_type == 2 ? 'Hourly' : 'Fixed salary' }}</td><td class="lbl">Basic salary</td><td class="val">{{ $money($row->basic_salary) }} {{ $cs }}{{ $row->salary_type == 2 ? ' / hour' : '' }}</td></tr>
    <tr><td class="lbl">Pay date</td><td class="val">{{ $row->pay_date ? date($setting->date_format ?? 'd M Y', strtotime($row->pay_date)) : '-' }}</td><td class="lbl">Payment method</td><td class="val">{{ $methods[$row->payment_method] ?? '-' }}</td></tr>
    <tr><td class="lbl">Bank account</td><td class="val" colspan="3">{{ $row->bankAccount ? $row->bankAccount->bank_name . ' - ' . $row->bankAccount->account_number : '-' }}</td></tr>
</table>

<table class="cols" style="margin-top: 8px;"><tr>
    <td style="border-right: 1px solid #e6e9ee;">
        <table class="lines">
            <tr><td colspan="2" class="bar earn-h">Earnings</td></tr>
            <tr>
                <td>
                    @if($row->salary_type == 2 && $row->hours_worked !== null)
                        Hours worked ({{ rtrim(rtrim(number_format((float) $row->hours_worked, 2), '0'), '.') }} h &times; {{ $money($row->basic_salary) }})
                    @elseif($row->paid_days !== null)
                        Basic salary ({{ rtrim(rtrim(number_format((float) $row->paid_days, 2), '0'), '.') }} of {{ rtrim(rtrim(number_format((float) $row->period_days, 2), '0'), '.') }} days)
                    @else
                        Basic salary
                    @endif
                </td>
                <td class="amt">{{ $money($row->total_earning) }}</td>
            </tr>
            @foreach($allowances as $d)
            <tr><td>{{ $d->title }}@if($d->is_taxable) <span class="small">(taxed)</span>@endif</td><td class="amt">{{ $money($d->amount) }}</td></tr>
            @endforeach
            <tr class="sub"><td>Total earnings</td><td class="amt">{{ $money($totalEarnings) }} {{ $cs }}</td></tr>
        </table>
    </td>
    <td>
        <table class="lines">
            <tr><td colspan="2" class="bar ded-h">Deductions</td></tr>
            @foreach($staffTaxes as $t)
            <tr><td>{{ $t['title'] }}@if($t['shared']) <span class="small">(employee)</span>@endif</td><td class="amt">{{ $money($t['amount']) }}</td></tr>
            @endforeach
            @if(!$tax_lines_reconcile && (float) $row->tax > 0)
            <tr><td>Taxes (not itemised for this payroll)</td><td class="amt">{{ $money($row->tax) }}</td></tr>
            @endif
            @foreach($deductions as $d)
            <tr><td>{{ $d->title }}@if($d->staff_loan_id) <span class="small">(repayment)</span>@endif</td><td class="amt">{{ $money($d->amount) }}</td></tr>
            @endforeach
            <tr class="sub"><td>Total deductions</td><td class="amt">{{ $money((float) $row->tax + $deductions->sum('amount')) }} {{ $cs }}</td></tr>
        </table>
    </td>
</tr></table>

<table class="sum" style="margin-top: 8px;">
    <tr><td>Total earnings</td><td class="amt">{{ $money($totalEarnings) }} {{ $cs }}</td></tr>
    @if((float) $row->total_deduction > 0)
    <tr><td>Other deductions</td><td class="amt">- {{ $money($row->total_deduction) }} {{ $cs }}</td></tr>
    @endif
    <tr><td>Total tax (employee){{ $row->taxable_salary !== null ? ' - on ' . $money($row->taxable_salary) : '' }}</td><td class="amt">- {{ $money($row->tax) }} {{ $cs }}</td></tr>
</table>
<table class="net"><tr><td>Net pay (take-home)</td><td class="amt">{{ $money($row->net_salary) }} {{ $cs }}</td></tr></table>

@if((float) $row->employer_tax > 0 || count($employerTaxes))
<table class="lines" style="margin-top: 8px;">
    <tr><td colspan="2" class="bar emp-h">Employer contributions (not deducted from your pay)</td></tr>
    @foreach($employerTaxes as $t)
    <tr><td>{{ $t['title'] }}@if($t['shared']) <span class="small">(employer share)</span>@endif</td><td class="amt">{{ $money($t['amount']) }} {{ $cs }}</td></tr>
    @endforeach
    <tr class="sub"><td>Total employer contribution</td><td class="amt">{{ $money($row->employer_tax) }} {{ $cs }}</td></tr>
</table>
<table class="cost"><tr><td>Total cost to institution</td><td style="text-align:right;">{{ $money((float) $row->total_cost > 0 ? $row->total_cost : ((float) $row->net_salary + (float) $row->tax + (float) $row->employer_tax)) }} {{ $cs }}</td></tr></table>
@endif

@if($row->note)
<p style="margin-top: 10px;"><strong>Note:</strong> {{ $row->note }}</p>
@endif

<table class="sig"><tr>
    <td><div class="line">Prepared by</div></td>
    <td><div class="line">Approved by{{ $row->approver ? ': ' . trim($row->approver->first_name . ' ' . $row->approver->last_name) : '' }}</div></td>
    <td><div class="line">Employee signature</div></td>
</tr></table>

<div class="foot">
    Computer-generated pay slip from {{ $setting->title ?? 'the institution' }}. Generated on {{ date($setting->date_format ?? 'd M Y') }}.
    Any discrepancy should be reported to the HR/Accounts office within 7 days.
</div>
</body>
</html>
