<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width,maximum-scale=1.0">
    <title>{{ $title }} - {{ $row->user->first_name ?? '' }} {{ $row->user->last_name ?? '' }}</title>
    
    <style type="text/css" media="print">
    @media print {
      @page { size: A4 portrait; margin: 12mm 10mm; }
      @page :footer { display: none; }
      @page :header { display: none; }
      body { margin: 0; }
      .no-print { display: none !important; }
      .payslip-container { border: 2px solid #333; }
    }
    table, img, svg { break-inside: avoid; }
    </style>
    
    <style type="text/css" media="screen, print">
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
        font-size: 12px;
        line-height: 1.5;
        color: #2d3436;
        background: #f0f2f5;
    }

    /* ===== Container ===== */
    .payslip-container {
        width: 100%;
        max-width: 800px;
        margin: 20px auto;
        background: #fff;
        border: 2px solid #34495e;
        border-radius: 4px;
        overflow: hidden;
    }

    /* ===== Header ===== */
    .payslip-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: linear-gradient(135deg, #1a1a2e 0%, #2c3e50 100%);
        color: #fff;
        border-bottom: 3px solid #3498db;
    }
    .header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .header-left img {
        max-height: 55px;
        max-width: 70px;
        object-fit: contain;
    }
    .header-left .inst-info h2 {
        font-size: 17px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }
    .header-left .inst-info .inst-sub {
        font-size: 10.5px;
        opacity: 0.75;
        letter-spacing: 0.3px;
    }
    .header-right {
        text-align: right;
    }
    .header-right .slip-title {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 2px;
        text-transform: uppercase;
    }
    .header-right .slip-period {
        font-size: 12px;
        opacity: 0.8;
        margin-top: 2px;
    }
    .header-right .slip-ref {
        font-size: 10px;
        opacity: 0.6;
        margin-top: 1px;
    }

    /* ===== Section Titles ===== */
    .section-bar {
        background: #ecf0f1;
        padding: 6px 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #2c3e50;
        border-bottom: 1px solid #dee2e6;
        border-top: 1px solid #dee2e6;
    }

    /* ===== Employee Info Grid ===== */
    .emp-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        padding: 0;
    }
    .emp-info-grid .info-block {
        display: flex;
        border-bottom: 1px solid #e9ecef;
    }
    .emp-info-grid .info-block:nth-child(odd) {
        border-right: 1px solid #e9ecef;
    }
    .emp-info-grid .info-block .lbl {
        width: 130px;
        flex-shrink: 0;
        font-weight: 600;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #636e72;
        background: #f8f9fa;
        padding: 7px 12px;
        border-right: 1px solid #e9ecef;
    }
    .emp-info-grid .info-block .val {
        flex: 1;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 500;
        color: #2d3436;
    }
    .status-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 10px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .status-paid { background: #d4edda; color: #155724; }
    .status-unpaid { background: #fff3cd; color: #856404; }

    /* ===== Salary Table ===== */
    .salary-table-wrap {
        display: flex;
        border-bottom: 1px solid #dee2e6;
    }
    .salary-col {
        flex: 1;
    }
    .salary-col:first-child {
        border-right: 2px solid #dee2e6;
    }
    .salary-col-header {
        padding: 8px 14px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        border-bottom: 1px solid #dee2e6;
    }
    .salary-col:first-child .salary-col-header {
        background: #e8f5e9;
        color: #1b5e20;
    }
    .salary-col:last-child .salary-col-header {
        background: #ffebee;
        color: #b71c1c;
    }
    .sal-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 14px;
        border-bottom: 1px solid #f1f3f5;
        font-size: 11.5px;
    }
    .sal-row:last-child { border-bottom: none; }
    .sal-row .sal-label { color: #555; flex: 1; }
    .sal-row .sal-label small { color: #999; font-size: 10px; }
    .sal-row .sal-amount {
        font-weight: 600;
        text-align: right;
        white-space: nowrap;
        min-width: 90px;
    }
    .sal-row.sub-total {
        background: #f8f9fa;
        font-weight: 700;
        border-top: 1px solid #dee2e6;
        padding: 7px 14px;
        font-size: 12px;
    }
    .sal-row.sub-total .sal-amount { color: #2d3436; }
    .earn-amount { color: #1b5e20; }
    .deduct-amount { color: #c0392b; }

    /* ===== Gross / Net / Summary ===== */
    .summary-section {
        border-bottom: 1px solid #dee2e6;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 20px;
        border-bottom: 1px solid #f1f3f5;
    }
    .summary-row:last-child { border-bottom: none; }
    .summary-row .s-label {
        font-size: 12px;
        font-weight: 600;
        color: #555;
    }
    .summary-row .s-value {
        font-size: 13px;
        font-weight: 700;
        text-align: right;
    }
    .summary-row.row-gross {
        background: #e3f2fd;
    }
    .summary-row.row-gross .s-value { color: #1565c0; }
    .summary-row.row-tax .s-value { color: #c0392b; }

    /* Net Pay Highlight */
    .net-pay-bar {
        background: linear-gradient(135deg, #1cc88a 0%, #17a673 100%);
        color: #fff;
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .net-pay-bar .np-label {
        font-size: 15px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .net-pay-bar .np-value {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: 0.5px;
    }

    /* Employer Contributions */
    .employer-section {
        border-top: 2px solid #dee2e6;
    }
    .employer-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 20px;
        border-bottom: 1px solid #f1f3f5;
        font-size: 11.5px;
    }
    .employer-row .emp-label { color: #555; }
    .employer-row .emp-amount { font-weight: 600; color: #d48806; }
    .employer-total {
        display: flex;
        justify-content: space-between;
        padding: 8px 20px;
        background: #fff8e1;
        font-weight: 700;
        font-size: 12px;
        border-top: 1px solid #ffe082;
    }
    .total-cost-bar {
        background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        color: #fff;
        padding: 10px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
        font-size: 13px;
    }

    /* ===== Note ===== */
    .note-section {
        padding: 10px 20px;
        border-bottom: 1px solid #dee2e6;
        font-size: 11.5px;
        color: #555;
    }
    .note-section strong { color: #2d3436; }

    /* ===== Signature Area ===== */
    .signature-section {
        display: flex;
        padding: 20px;
        gap: 20px;
    }
    .sig-block {
        flex: 1;
        text-align: center;
    }
    .sig-line {
        margin-top: 45px;
        border-top: 1px solid #555;
        padding-top: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #555;
    }
    .sig-date {
        font-size: 10px;
        color: #999;
        margin-top: 2px;
    }

    /* ===== Footer Disclaimer ===== */
    .payslip-footer {
        background: #f8f9fa;
        border-top: 1px solid #dee2e6;
        padding: 8px 20px;
        text-align: center;
        font-size: 9.5px;
        color: #999;
        line-height: 1.6;
    }

    /* ===== Print Button ===== */
    .print-actions {
        text-align: center;
        padding: 16px;
    }
    .btn-print {
        display: inline-block;
        padding: 10px 30px;
        background: #3498db;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-print:hover { background: #2980b9; }

    /* ===== RTL ===== */
    @php $version = App\Models\Language::version(); @endphp
    @if($version->direction == 1)
    .payslip-container { direction: rtl; }
    .emp-info-grid .info-block:nth-child(odd) { border-right: none; border-left: 1px solid #e9ecef; }
    .salary-col:first-child { border-right: none; border-left: 2px solid #dee2e6; }
    .sal-row .sal-amount { text-align: left; }
    @endif

    /* responsive for screen */
    @media screen and (max-width: 600px) {
        .payslip-header { flex-direction: column; text-align: center; gap: 10px; }
        .header-right { text-align: center; }
        .emp-info-grid { grid-template-columns: 1fr; }
        .emp-info-grid .info-block:nth-child(odd) { border-right: none; }
        .salary-table-wrap { flex-direction: column; }
        .salary-col:first-child { border-right: none; border-bottom: 2px solid #dee2e6; }
        .net-pay-bar .np-value { font-size: 18px; }
    }
    </style>
</head>
<body>

@php
    // ===== Tax Calculation (2-pass approach matching generate page) =====
    $applicable_taxes = [];
    $employer_taxes = [];
    $group_tax_results = [];
    $standalone_tax_results = [];
    $total_calculated_tax = 0;
    $total_calculated_employer_tax = 0;
    $base_earning = (float) $row->total_earning;
    $dp = $setting->decimal_place ?? 2;
    $cs = $setting->currency_symbol ?? '';
    
    // --- PASS 1: Tax Groups (progressive) ---
    if(isset($tax_groups) && $tax_groups->count() > 0) {
        foreach($tax_groups as $group) {
            $groupResult = $group->calculateTax($base_earning, $staff_tax_exemptions ?? collect());
            if($groupResult['amount'] > 0) {
                $applicable_taxes[] = [
                    'title' => $group->title . ($group->is_progressive ? ' (Progressive)' : ''),
                    'amount' => $groupResult['amount'],
                    'display_info' => '',
                    'is_shared' => false,
                    'is_group' => true
                ];
                $total_calculated_tax += $groupResult['amount'];
                $group_tax_results[$group->id] = $groupResult['amount'];
            }
        }
    }
    
    // --- PASS 1b: Standalone base taxes ---
    $exempt_tax_ids = $row->user->exemptTaxes ? $row->user->exemptTaxes->pluck('id')->toArray() : [];
    
    if(isset($taxs)) {
        foreach($taxs as $tax) {
            if($tax->min_amount <= $base_earning && $tax->max_amount >= $base_earning) {
                $is_exempt = in_array($tax->id, $exempt_tax_ids);
                $employee_tax_amount = 0;
                $employer_tax_amount = 0;
                $tax_display_info = '';
                $paid_by = $tax->paid_by ?? 'employee';
                
                if($is_exempt) {
                    $exemption = isset($staff_tax_exemptions) ? $staff_tax_exemptions->get($tax->id) : null;
                    if($exemption && $exemption->custom_percentage && $tax->tax_type == 1) {
                        $taxable_amount = $base_earning - $tax->max_no_taxable_amount;
                        $employee_tax_amount = ($taxable_amount / 100) * $exemption->custom_percentage;
                        $tax_display_info = ' (Custom: ' . number_format($exemption->custom_percentage, 2) . '%)';
                    } elseif($exemption && $exemption->custom_fixed_amount && $tax->tax_type == 2) {
                        $employee_tax_amount = $exemption->custom_fixed_amount;
                        $tax_display_info = ' (Custom)';
                    }
                } else {
                    $taxable_amount = $base_earning - $tax->max_no_taxable_amount;
                    if($paid_by == 'employee' || $paid_by == 'both') {
                        $employee_tax_amount = ($tax->tax_type == 2) ? ($tax->fixed_amount ?? 0)
                            : (($taxable_amount / 100) * ($tax->percentange ?? 0));
                    }
                    if($paid_by == 'employer' || $paid_by == 'both') {
                        $employer_tax_amount = ($tax->tax_type == 2) ? ($tax->employer_fixed_amount ?? 0)
                            : (($taxable_amount / 100) * ($tax->employer_percentage ?? 0));
                    }
                }
                
                if($employee_tax_amount > 0) {
                    $applicable_taxes[] = [
                        'title' => $tax->title ?? 'Tax',
                        'amount' => $employee_tax_amount,
                        'display_info' => $tax_display_info,
                        'is_shared' => ($paid_by == 'both'),
                        'is_group' => false
                    ];
                    $total_calculated_tax += $employee_tax_amount;
                    $standalone_tax_results[$tax->id] = $employee_tax_amount;
                }
                if($employer_tax_amount > 0) {
                    $employer_taxes[] = [
                        'title' => $tax->title ?? 'Tax',
                        'amount' => $employer_tax_amount,
                        'is_shared' => ($paid_by == 'both')
                    ];
                    $total_calculated_employer_tax += $employer_tax_amount;
                }
            }
        }
    }
    
    // --- PASS 2: Dependent taxes ---
    if(isset($dependent_taxs)) {
        foreach($dependent_taxs as $dep_tax) {
            if($dep_tax->min_amount <= $base_earning && $dep_tax->max_amount >= $base_earning) {
                $source_amount = 0;
                $source_type = $dep_tax->dependent_source_type ?? 'standalone';
                $source_id = $dep_tax->dependent_on_tax_id ?? null;
                
                if($source_type === 'group' && $source_id && isset($group_tax_results[$source_id])) {
                    $source_amount = $group_tax_results[$source_id];
                } elseif($source_id && isset($standalone_tax_results[$source_id])) {
                    $source_amount = $standalone_tax_results[$source_id];
                }
                
                if($source_amount > 0) {
                    $dep_employee_amount = 0;
                    $dep_employer_amount = 0;
                    $paid_by = $dep_tax->paid_by ?? 'employee';
                    
                    if($paid_by == 'employee' || $paid_by == 'both') {
                        $dep_employee_amount = ($dep_tax->tax_type == 2) ? ($dep_tax->fixed_amount ?? 0)
                            : (($source_amount / 100) * ($dep_tax->percentange ?? 0));
                    }
                    if($paid_by == 'employer' || $paid_by == 'both') {
                        $dep_employer_amount = ($dep_tax->tax_type == 2) ? ($dep_tax->employer_fixed_amount ?? 0)
                            : (($source_amount / 100) * ($dep_tax->employer_percentage ?? 0));
                    }
                    
                    if($dep_employee_amount > 0) {
                        $source_label = ($source_type === 'group' && $source_id && isset($tax_groups))
                            ? ($tax_groups->firstWhere('id', $source_id)->title ?? 'Group')
                            : (isset($taxs) ? ($taxs->firstWhere('id', $source_id)->title ?? 'Base Tax') : 'Base Tax');
                        $applicable_taxes[] = [
                            'title' => ($dep_tax->title ?? 'Dependent Tax') . ' (on ' . $source_label . ')',
                            'amount' => $dep_employee_amount,
                            'display_info' => '',
                            'is_shared' => ($paid_by == 'both'),
                            'is_group' => false
                        ];
                        $total_calculated_tax += $dep_employee_amount;
                    }
                    if($dep_employer_amount > 0) {
                        $employer_taxes[] = [
                            'title' => ($dep_tax->title ?? 'Dependent Tax'),
                            'amount' => $dep_employer_amount,
                            'is_shared' => ($paid_by == 'both')
                        ];
                        $total_calculated_employer_tax += $dep_employer_amount;
                    }
                }
            }
        }
    }
    
    // Totals from stored record (authoritative)
    $total_earnings = $base_earning + $row->details->where('status', 1)->sum('amount');
    $total_deductions_amount = $row->details->where('status', 0)->sum('amount');
    $stored_tax = (float) $row->tax;
    $stored_employer_tax = (float) ($row->employer_tax ?? 0);
    $stored_net = (float) $row->net_salary;
    $stored_gross = (float) $row->gross_salary;
    $stored_total_cost = (float) ($row->total_cost ?? 0);
    $stored_basic = (float) $row->basic_salary;
    $stored_total_allowance = (float) $row->total_allowance;
    $stored_total_deduction = (float) $row->total_deduction;
    
    // Staff initials
    $initials = strtoupper(substr($row->user->first_name ?? '', 0, 1) . substr($row->user->last_name ?? '', 0, 1));
@endphp

<div class="payslip-container printable">

    <!-- ===== HEADER ===== -->
    <div class="payslip-header">
        <div class="header-left">
            @if(isset($print) && $print->logo_left && is_file('uploads/'.$path.'/'.$print->logo_left))
                <img src="{{ asset('uploads/'.$path.'/'.$print->logo_left) }}" alt="Logo">
            @elseif($setting->logo_path && is_file('uploads/setting/'.$setting->logo_path))
                <img src="{{ asset('uploads/setting/'.$setting->logo_path) }}" alt="Logo">
            @endif
            <div class="inst-info">
                <h2>{{ $setting->title ?? 'Institution Name' }}</h2>
                <div class="inst-sub">
                    @if($setting->address){{ $setting->address }}@endif
                    @if($setting->phone) &bull; {{ $setting->phone }}@endif
                    @if($setting->email) &bull; {{ $setting->email }}@endif
                </div>
            </div>
        </div>
        <div class="header-right">
            <div class="slip-title">Pay Slip</div>
            <div class="slip-period">{{ date("F Y", strtotime($row->salary_month)) }}</div>
            <div class="slip-ref">Ref: PS-{{ str_pad($row->id, 5, '0', STR_PAD_LEFT) }}</div>
        </div>
    </div>

    <!-- ===== EMPLOYEE INFORMATION ===== -->
    <div class="section-bar"><i>&#9654;</i> Employee Information</div>
    <div class="emp-info-grid">
        <div class="info-block">
            <div class="lbl">Employee Name</div>
            <div class="val"><strong>{{ $row->user->first_name ?? '' }} {{ $row->user->last_name ?? '' }}</strong></div>
        </div>
        <div class="info-block">
            <div class="lbl">Staff ID</div>
            <div class="val">{{ $row->user->staff_id ?? '-' }}</div>
        </div>
        <div class="info-block">
            <div class="lbl">Department</div>
            <div class="val">{{ $row->user->department->title ?? '-' }}</div>
        </div>
        <div class="info-block">
            <div class="lbl">Designation</div>
            <div class="val">{{ $row->user->designation->title ?? '-' }}</div>
        </div>
        <div class="info-block">
            <div class="lbl">Salary Type</div>
            <div class="val">
                @if($row->salary_type == 1) Fixed Salary
                @elseif($row->salary_type == 2) Hourly
                @else -
                @endif
            </div>
        </div>
        <div class="info-block">
            <div class="lbl">Basic Salary</div>
            <div class="val"><strong>{{ number_format($stored_basic, $dp) }} {{ $cs }}</strong></div>
        </div>
        <div class="info-block">
            <div class="lbl">Pay Date</div>
            <div class="val">{{ $row->pay_date ? date($setting->date_format ?? 'd M Y', strtotime($row->pay_date)) : '-' }}</div>
        </div>
        <div class="info-block">
            <div class="lbl">Status</div>
            <div class="val">
                @if($row->status == 1)
                    <span class="status-badge status-paid">Paid</span>
                @else
                    <span class="status-badge status-unpaid">Unpaid</span>
                @endif
            </div>
        </div>
        <div class="info-block">
            <div class="lbl">Payment Method</div>
            <div class="val">
                @if($row->payment_method == 1) {{ __('payment_method_card') }}
                @elseif($row->payment_method == 2) {{ __('payment_method_cash') }}
                @elseif($row->payment_method == 3) {{ __('payment_method_cheque') }}
                @elseif($row->payment_method == 4) {{ __('payment_method_bank') }}
                @elseif($row->payment_method == 5) {{ __('payment_method_e_wallet') }}
                @else -
                @endif
            </div>
        </div>
        <div class="info-block">
            <div class="lbl">Bank Account</div>
            <div class="val">
                @if($row->bankAccount)
                    {{ $row->bankAccount->bank_name }} - {{ $row->bankAccount->account_number }}
                @elseif($row->user->bank_name && $row->user->bank_account_no)
                    {{ $row->user->bank_name }} - {{ $row->user->bank_account_no }}
                @else
                    -
                @endif
            </div>
        </div>
    </div>

    <!-- ===== EARNINGS & DEDUCTIONS SIDE-BY-SIDE ===== -->
    <div class="salary-table-wrap">
        <!-- Earnings Column -->
        <div class="salary-col">
            <div class="salary-col-header">&#9650; Earnings</div>
            <div class="sal-row">
                <div class="sal-label">Basic Salary (Total Earning)</div>
                <div class="sal-amount earn-amount">{{ number_format($base_earning, $dp) }}</div>
            </div>
            @foreach($row->details->where('status', 1) as $detail)
            <div class="sal-row">
                <div class="sal-label">{{ $detail->title }}</div>
                <div class="sal-amount earn-amount">{{ number_format((float)$detail->amount, $dp) }}</div>
            </div>
            @endforeach
            @if($row->details->where('status', 1)->count() == 0)
            <div class="sal-row">
                <div class="sal-label" style="color: #aaa; font-style: italic;">No additional allowances</div>
                <div class="sal-amount">-</div>
            </div>
            @endif
            <div class="sal-row sub-total">
                <div class="sal-label">Total Earnings</div>
                <div class="sal-amount earn-amount">{{ number_format($total_earnings, $dp) }} {{ $cs }}</div>
            </div>
        </div>

        <!-- Deductions Column -->
        <div class="salary-col">
            <div class="salary-col-header">&#9660; Deductions</div>
            @foreach($applicable_taxes as $tax_item)
            <div class="sal-row">
                <div class="sal-label">
                    {{ $tax_item['title'] }}{{ $tax_item['display_info'] }}
                    @if($tax_item['is_shared'])
                    <small>(Employee)</small>
                    @endif
                </div>
                <div class="sal-amount deduct-amount">{{ number_format((float)$tax_item['amount'], $dp) }}</div>
            </div>
            @endforeach
            @foreach($row->details->where('status', 0) as $detail)
            <div class="sal-row">
                <div class="sal-label">{{ $detail->title }}</div>
                <div class="sal-amount deduct-amount">{{ number_format((float)$detail->amount, $dp) }}</div>
            </div>
            @endforeach
            @if(count($applicable_taxes) == 0 && $row->details->where('status', 0)->count() == 0)
            <div class="sal-row">
                <div class="sal-label" style="color: #aaa; font-style: italic;">No deductions</div>
                <div class="sal-amount">-</div>
            </div>
            @endif
            <div class="sal-row sub-total">
                <div class="sal-label">Total Deductions</div>
                <div class="sal-amount deduct-amount">{{ number_format($stored_tax + $total_deductions_amount, $dp) }} {{ $cs }}</div>
            </div>
        </div>
    </div>

    <!-- ===== SUMMARY ===== -->
    <div class="summary-section">
        <div class="summary-row row-gross">
            <div class="s-label">Gross Salary (Before Deductions)</div>
            <div class="s-value">{{ number_format($stored_gross, $dp) }} {{ $cs }}</div>
        </div>
        <div class="summary-row row-tax">
            <div class="s-label">Total Tax (Employee)</div>
            <div class="s-value">- {{ number_format($stored_tax, $dp) }} {{ $cs }}</div>
        </div>
        @if($stored_total_deduction > 0)
        <div class="summary-row">
            <div class="s-label">Other Deductions</div>
            <div class="s-value" style="color: #e74a3b;">- {{ number_format($stored_total_deduction, $dp) }} {{ $cs }}</div>
        </div>
        @endif
    </div>

    <!-- ===== NET PAY BAR ===== -->
    <div class="net-pay-bar">
        <div class="np-label">&#10004; Net Pay (Take-home)</div>
        <div class="np-value">{{ number_format($stored_net, $dp) }} {{ $cs }}</div>
    </div>

    <!-- ===== EMPLOYER CONTRIBUTIONS (if any) ===== -->
    @if($stored_employer_tax > 0 || count($employer_taxes) > 0)
    <div class="employer-section">
        <div class="section-bar" style="background: #fff8e1; color: #856404;">&#9654; Employer Contributions</div>
        @foreach($employer_taxes as $emp_tax)
        <div class="employer-row">
            <div class="emp-label">
                {{ $emp_tax['title'] }}
                @if($emp_tax['is_shared']) <small style="color: #999;">(Employer Share)</small> @endif
            </div>
            <div class="emp-amount">{{ number_format((float)$emp_tax['amount'], $dp) }} {{ $cs }}</div>
        </div>
        @endforeach
        <div class="employer-total">
            <div>Total Employer Contribution</div>
            <div>{{ number_format($stored_employer_tax, $dp) }} {{ $cs }}</div>
        </div>
        <div class="total-cost-bar">
            <div>&#9632; Total Cost to Institution</div>
            <div>{{ number_format($stored_total_cost > 0 ? $stored_total_cost : ($stored_net + $stored_tax + $stored_employer_tax), $dp) }} {{ $cs }}</div>
        </div>
    </div>
    @endif

    <!-- ===== NOTE ===== -->
    @if($row->note)
    <div class="note-section">
        <strong>Note:</strong> {{ $row->note }}
    </div>
    @endif

    <!-- ===== SIGNATURES ===== -->
    <div class="signature-section">
        <div class="sig-block">
            <div class="sig-line">Prepared By</div>
            <div class="sig-date">Date: _______________</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Approved By</div>
            <div class="sig-date">Date: _______________</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Employee Signature</div>
            <div class="sig-date">Date: _______________</div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="payslip-footer">
        This is a computer-generated pay slip from <strong>{{ $setting->title ?? 'the Institution' }}</strong>.<br>
        Printed on: {{ date($setting->date_format ?? 'd M Y') }} at {{ date($setting->time_format == 1 ? 'H:i' : 'h:i A') }}
        &bull; Any discrepancies should be reported to the HR/Accounts department within 7 days.
    </div>

</div>

<!-- Print Button (screen only) -->
<div class="print-actions no-print">
    <button class="btn-print" onclick="window.print();">&#128438; Print Pay Slip</button>
</div>

<!-- Print JS -->
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>
<script src="{{ asset('dashboard/plugins/print/js/jQuery.print.min.js') }}"></script>
<script type="text/javascript">
$(document).ready(function() {
    "use strict";
    $.print(".printable");
});
</script>

</body>
</html>
