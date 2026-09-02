@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    /* ===== Payroll Generate Page Styles ===== */
    .payroll-header-card {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        border-radius: 10px;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .payroll-header-card::after {
        content: '';
        position: absolute;
        top: -30px; right: -30px;
        width: 120px; height: 120px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
    }
    .payroll-header-card .card-block { position: relative; z-index: 1; }
    .payroll-header-card .staff-avatar {
        width: 70px; height: 70px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; font-weight: 700;
        border: 3px solid rgba(255,255,255,0.4);
        flex-shrink: 0;
    }
    .payroll-header-card .staff-meta dt { font-size: 11px; opacity: 0.7; margin-bottom: 1px; text-transform: uppercase; letter-spacing: .5px; }
    .payroll-header-card .staff-meta dd { font-size: 14px; font-weight: 600; margin-bottom: 8px; }

    .payroll-period-badge {
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 8px;
        padding: 12px 16px;
        text-align: center;
    }
    .payroll-period-badge .period-month { font-size: 22px; font-weight: 700; line-height: 1.1; }
    .payroll-period-badge .period-label { font-size: 10px; text-transform: uppercase; opacity: 0.7; }

    .salary-highlight {
        background: rgba(255,255,255,0.12);
        border-radius: 8px;
        padding: 10px 14px;
        text-align: center;
    }
    .salary-highlight .sal-label { font-size: 10px; text-transform: uppercase; opacity: 0.7; }
    .salary-highlight .sal-value { font-size: 20px; font-weight: 700; }

    /* Status Pill */
    .payroll-status-pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
    }
    .payroll-status-pill.draft { background: #fff3cd; color: #856404; }
    .payroll-status-pill.approved { background: #d4edda; color: #155724; }
    .payroll-status-pill.new-payroll { background: #cce5ff; color: #004085; }

    /* Attendance Card */
    .attendance-card { border-radius: 10px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .attendance-stat {
        text-align: center; padding: 12px 8px;
        border-radius: 8px; transition: transform 0.15s;
    }
    .attendance-stat:hover { transform: translateY(-2px); }
    .attendance-stat .stat-icon { font-size: 20px; margin-bottom: 4px; }
    .attendance-stat .stat-value { font-size: 22px; font-weight: 700; display: block; }
    .attendance-stat .stat-label { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: .3px; }
    .att-present { background: #e6f9f0; }
    .att-present .stat-icon, .att-present .stat-value { color: #1cc88a; }
    .att-absent { background: #fde8e5; }
    .att-absent .stat-icon, .att-absent .stat-value { color: #e74a3b; }
    .att-leave { background: #fff8e1; }
    .att-leave .stat-icon, .att-leave .stat-value { color: #d48806; }
    .att-paid-leave { background: #e8ecf6; }
    .att-paid-leave .stat-icon, .att-paid-leave .stat-value { color: #4e73df; }
    .att-unpaid-leave { background: #f8d7da; }
    .att-unpaid-leave .stat-icon, .att-unpaid-leave .stat-value { color: #c0392b; }
    .att-holiday { background: #d4edda; }
    .att-holiday .stat-icon, .att-holiday .stat-value { color: #155724; }

    .attendance-summary-bar {
        height: 8px; border-radius: 4px; overflow: hidden;
        background: #e9ecef; display: flex;
    }
    .attendance-summary-bar .bar-segment { height: 100%; transition: width 0.4s ease; }

    /* Section card styling */
    .section-card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .section-card .card-header {
        border-bottom: 2px solid #f1f3f5;
        background: #fff;
        padding: 14px 20px;
    }
    .section-card .card-header h5 {
        font-size: 15px;
        font-weight: 600;
        margin: 0;
    }
    .section-card .card-header .section-desc {
        font-size: 12px;
        color: #858796;
        margin: 2px 0 0 0;
    }

    /* Calculation panel */
    .calc-field-group {
        background: #f8f9fc;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 12px;
        border-left: 4px solid #dee2e6;
        transition: border-color 0.2s;
    }
    .calc-field-group:hover { border-left-color: #4e73df; }
    .calc-field-group.highlight-earning { border-left-color: #4e73df; background: #eef2ff; }
    .calc-field-group.highlight-gross { border-left-color: #36b9cc; background: #e8f8fb; }
    .calc-field-group.highlight-tax { border-left-color: #e74a3b; background: #fef5f4; }
    .calc-field-group.highlight-employer { border-left-color: #f6c23e; background: #fffdf2; }
    .calc-field-group.highlight-net { border-left-color: #1cc88a; background: #e6f9f0; }
    .calc-field-group.highlight-cost { border-left-color: #5a5c69; background: #f0f0f5; }

    .calc-field-group label {
        font-size: 12px;
        font-weight: 600;
        color: #5a5c69;
        text-transform: uppercase;
        letter-spacing: .3px;
        margin-bottom: 4px;
    }
    .calc-field-group .form-control {
        font-size: 16px;
        font-weight: 700;
        border: none;
        background: transparent;
        padding: 2px 0;
        height: auto;
    }
    .calc-field-group .field-desc {
        font-size: 11px;
        color: #858796;
        margin-top: 2px;
    }

    /* Tax breakdown panel */
    .tax-breakdown-panel {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 14px;
        margin-top: 10px;
    }
    .tax-breakdown-panel .tax-section-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #858796;
        padding-bottom: 6px;
        border-bottom: 1px dashed #dee2e6;
        margin-bottom: 8px;
    }
    .tax-item {
        display: flex;
        align-items: flex-start;
        padding: 6px 0;
        border-bottom: 1px solid #f1f3f5;
    }
    .tax-item:last-child { border-bottom: none; }
    .tax-item .tax-icon { width: 24px; flex-shrink: 0; padding-top: 2px; }
    .tax-item .tax-info { flex: 1; }
    .tax-item .tax-name { font-weight: 600; font-size: 13px; }
    .tax-item .tax-detail { font-size: 11px; color: #858796; }
    .tax-item .tax-amount { font-weight: 700; font-size: 13px; text-align: right; white-space: nowrap; }

    /* Payroll summary footer */
    .payroll-summary-footer {
        background: linear-gradient(135deg, #1a1a2e 0%, #2c3e50 100%);
        color: #fff;
        border-radius: 10px;
        padding: 20px;
    }
    .summary-item { text-align: center; padding: 8px; }
    .summary-item .sum-label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; opacity: 0.6; }
    .summary-item .sum-value { font-size: 18px; font-weight: 700; }
    .summary-item .sum-value.text-success-light { color: #5dff9e; }
    .summary-item .sum-value.text-danger-light { color: #ff7675; }
    .summary-item .sum-value.text-warning-light { color: #ffeaa7; }
    .summary-item .sum-value.text-info-light { color: #74b9ff; }
    .summary-arrow { display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.3); font-size: 20px; }

    /* Allowance / Deduction entry styles */
    .entry-card .card-header { display: flex; align-items: center; justify-content: space-between; }
    .entry-card .entry-total {
        font-size: 13px; font-weight: 600; padding: 3px 10px;
        border-radius: 15px; background: rgba(0,0,0,0.05);
    }
    .entry-row {
        padding: 10px 0;
        border-bottom: 1px solid #f1f3f5;
    }
    .entry-row:last-child { border-bottom: none; }

    /* Confirmation Modal */
    .payroll-confirm-modal .modal-header {
        background: linear-gradient(135deg, #1a1a2e 0%, #2c3e50 100%);
        color: #fff;
        border-radius: 10px 10px 0 0;
        padding: 18px 24px;
    }
    .payroll-confirm-modal .modal-header .modal-title {
        font-weight: 700; font-size: 16px;
    }
    .payroll-confirm-modal .modal-header .close { color: #fff; opacity: 0.7; text-shadow: none; }
    .payroll-confirm-modal .modal-header .close:hover { opacity: 1; }
    .payroll-confirm-modal .modal-content {
        border: none;
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .payroll-confirm-modal .modal-body { padding: 24px; }
    .payroll-confirm-modal .modal-footer {
        border-top: 1px solid #f1f3f5;
        padding: 14px 24px;
    }
    .confirm-warning-banner {
        background: #fff8e1;
        border: 1px solid #ffe082;
        border-left: 4px solid #f6c23e;
        border-radius: 6px;
        padding: 12px 16px;
        margin-bottom: 18px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .confirm-warning-banner .warn-icon {
        color: #d48806;
        font-size: 20px;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .confirm-warning-banner .warn-text {
        font-size: 13px;
        color: #856404;
        line-height: 1.5;
    }
    .confirm-warning-banner .warn-text strong {
        display: block;
        font-size: 14px;
        margin-bottom: 2px;
    }
    .confirm-summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }
    .confirm-summary-item {
        background: #f8f9fc;
        border-radius: 8px;
        padding: 12px 14px;
        border-left: 3px solid #dee2e6;
    }
    .confirm-summary-item.item-earning { border-left-color: #4e73df; }
    .confirm-summary-item.item-allowance { border-left-color: #1cc88a; }
    .confirm-summary-item.item-deduction { border-left-color: #e74a3b; }
    .confirm-summary-item.item-gross { border-left-color: #36b9cc; }
    .confirm-summary-item.item-tax { border-left-color: #e74a3b; }
    .confirm-summary-item.item-employer-tax { border-left-color: #f6c23e; }
    .confirm-summary-item.item-net {
        border-left-color: #1cc88a;
        grid-column: 1 / -1;
        background: #e6f9f0;
    }
    .confirm-summary-item.item-cost {
        border-left-color: #5a5c69;
        grid-column: 1 / -1;
        background: #f0f0f5;
    }
    .confirm-summary-item .csi-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: #858796;
        font-weight: 600;
        margin-bottom: 2px;
    }
    .confirm-summary-item .csi-value {
        font-size: 17px;
        font-weight: 700;
        color: #2d3436;
    }
    .confirm-summary-item.item-net .csi-value { color: #1cc88a; font-size: 20px; }
    .confirm-staff-info {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid #f1f3f5;
    }
    .confirm-staff-info .csi-avatar {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4e73df, #224abe);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 16px;
        flex-shrink: 0;
    }
    .confirm-staff-info .csi-name { font-weight: 700; font-size: 15px; color: #2d3436; }
    .confirm-staff-info .csi-meta { font-size: 12px; color: #858796; }

    /* Responsive tweaks */
    @media (max-width: 768px) {
        .payroll-header-card .staff-avatar { width: 50px; height: 50px; font-size: 20px; }
        .payroll-period-badge .period-month { font-size: 16px; }
        .salary-highlight .sal-value { font-size: 16px; }
        .confirm-summary-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">

        @php
        // ===== All PHP calculations (kept at top for clean separation) =====
        $paid_leave = \App\Models\Leave::paid_leave($row->id, $selected_month, $selected_year);
        $unpaid_leave = \App\Models\Leave::unpaid_leave($row->id, $selected_month, $selected_year);

        $present = $attendances->where('attendance', 1)->where('user_id', $row->id)->count();
        $absent = $attendances->where('attendance', 2)->where('user_id', $row->id)->count();
        $leave = $attendances->where('attendance', 3)->where('user_id', $row->id)->count();
        $holiday = $attendances->where('attendance', 4)->where('user_id', $row->id)->count();

        $payable_days = $present + $holiday + $paid_leave;
        $unpayable_days = $absent + $unpaid_leave;

        $basic_salary = ($row->basic_salary != null && $row->basic_salary != '') ? $row->basic_salary : 0;
        $total_earning = $basic_salary;

        // Note: attendance does not reduce pay (policy decision). Absence/leave
        // counts above are kept only for the attendance summary display.

        $total_allowance = round($payroll->total_allowance ?? 0);
        $bonus = round($payroll->bonus ?? 0);
        $total_deduction = round($payroll->total_deduction ?? 0);
        $gross_salary = round($total_earning + $total_allowance + $bonus) - round($total_deduction);
        $tax_amount = 0;
        $employer_tax_amount = 0;

        // Store tax results for dependent tax calculation (2-pass)
        $group_tax_results = [];
        $standalone_tax_results = [];

        if(isset($payroll) && round($total_earning) == round($payroll->total_earning)){
            $tax_amount = round($payroll->tax ?? 0);
            $employer_tax_amount = round($payroll->employer_tax ?? 0);
        }
        else{
            // === PASS 1: Calculate base taxes (non-dependent) ===
            if(isset($tax_groups) && $tax_groups->count() > 0){
                foreach($tax_groups as $group){
                    $groupResult = $group->calculateTax($total_earning, $staff_tax_exemptions);

                    // Employer contribution for the SAME single applicable bracket
                    $groupEmployerTax = 0;
                    $appBracket = $group->applicableBracket($total_earning);
                    if($appBracket && ($appBracket->paid_by == 'employer' || $appBracket->paid_by == 'both')
                        && !$staff_tax_exemptions->has($appBracket->id)){
                        $groupEmployerTax = $appBracket->calculateEmployerContribution($total_earning);
                    }
                    $groupResult['employer'] = $groupEmployerTax;

                    $group_tax_results[$group->id] = $groupResult;
                    $tax_amount += $groupResult['amount'];
                    $employer_tax_amount += $groupEmployerTax;
                }
            }

            if(isset($taxs)){
            foreach($taxs as $tax){
                if($tax->min_amount <= $total_earning && $tax->max_amount >= $total_earning){
                    // Only non-expired exemptions apply (collection is pre-filtered notExpired)
                    $is_exempt = $staff_tax_exemptions->has($tax->id);
                    $current_employee_tax = 0;
                    $current_employer_tax = 0;

                    if($is_exempt) {
                        $exemption = $staff_tax_exemptions->get($tax->id);
                        if($exemption && $exemption->custom_percentage && $tax->tax_type == 1) {
                            $taxable_amount = $total_earning - $tax->max_no_taxable_amount;
                            $current_employee_tax = ($taxable_amount / 100) * $exemption->custom_percentage;
                        } elseif($exemption && $exemption->custom_fixed_amount && $tax->tax_type == 2) {
                            $current_employee_tax = $exemption->custom_fixed_amount;
                        }
                    } else {
                        $taxable_amount = $total_earning - $tax->max_no_taxable_amount;
                        $paid_by = $tax->paid_by ?? 'employee';
                        if($paid_by == 'employee' || $paid_by == 'both') {
                            if($tax->tax_type == 2) { $current_employee_tax = $tax->fixed_amount ?? 0; }
                            else { $current_employee_tax = ($taxable_amount / 100) * ($tax->percentange ?? 0); }
                        }
                        if($paid_by == 'employer' || $paid_by == 'both') {
                            if($tax->tax_type == 2) { $current_employer_tax = $tax->employer_fixed_amount ?? 0; }
                            else { $current_employer_tax = ($taxable_amount / 100) * ($tax->employer_percentage ?? 0); }
                        }
                    }

                    $standalone_tax_results[$tax->id] = [
                        'employee' => $current_employee_tax,
                        'employer' => $current_employer_tax,
                    ];
                    $tax_amount += $current_employee_tax;
                    $employer_tax_amount += $current_employer_tax;
                }
            }}

            // === PASS 2: Calculate dependent taxes ===
            if(isset($dependent_taxs)){
            foreach($dependent_taxs as $dep_tax){
                if($dep_tax->min_amount <= $total_earning && $dep_tax->max_amount >= $total_earning){
                    $source_amount = 0;
                    if($dep_tax->depends_on_type === 'tax_group' && isset($group_tax_results[$dep_tax->depends_on_id])) {
                        // Full assessed tax of the source group (employee + employer)
                        $source_amount = ($group_tax_results[$dep_tax->depends_on_id]['amount'] ?? 0)
                                       + ($group_tax_results[$dep_tax->depends_on_id]['employer'] ?? 0);
                    } elseif($dep_tax->depends_on_type === 'tax_setting' && isset($standalone_tax_results[$dep_tax->depends_on_id])) {
                        $source_result = $standalone_tax_results[$dep_tax->depends_on_id];
                        $source_amount = $source_result['employee'] + $source_result['employer'];
                    }

                    // Only non-expired exemptions apply
                    $is_exempt = $staff_tax_exemptions->has($dep_tax->id);
                    $current_employee_tax = 0;
                    $current_employer_tax = 0;

                    if($is_exempt) {
                        $exemption = $staff_tax_exemptions->get($dep_tax->id);
                        if($exemption && $exemption->custom_percentage && $dep_tax->tax_type == 1) {
                            $current_employee_tax = ($source_amount / 100) * $exemption->custom_percentage;
                        } elseif($exemption && $exemption->custom_fixed_amount && $dep_tax->tax_type == 2) {
                            $current_employee_tax = $exemption->custom_fixed_amount;
                        }
                    } else {
                        $paid_by = $dep_tax->paid_by ?? 'employee';
                        if($paid_by == 'employee' || $paid_by == 'both') {
                            if($dep_tax->tax_type == 2) { $current_employee_tax = $dep_tax->fixed_amount ?? 0; }
                            else { $current_employee_tax = ($source_amount / 100) * ($dep_tax->percentange ?? 0); }
                        }
                        if($paid_by == 'employer' || $paid_by == 'both') {
                            if($dep_tax->tax_type == 2) { $current_employer_tax = $dep_tax->employer_fixed_amount ?? 0; }
                            else { $current_employer_tax = ($source_amount / 100) * ($dep_tax->employer_percentage ?? 0); }
                        }
                    }

                    $standalone_tax_results[$dep_tax->id] = [
                        'employee' => $current_employee_tax,
                        'employer' => $current_employer_tax,
                        'is_dependent' => true,
                        'source_amount' => $source_amount,
                    ];
                    $tax_amount += $current_employee_tax;
                    $employer_tax_amount += $current_employer_tax;
                }
            }}
        }

        $net_salary = round($gross_salary - $tax_amount);
        $total_cost = round($net_salary + $tax_amount + $employer_tax_amount);
        $effective_rate = $total_earning > 0 ? round(($tax_amount / $total_earning) * 100, 2) : 0;
        $total_attendance = $present + $absent + $leave + $holiday;
        $attendance_pct = $total_days > 0 ? round(($payable_days / $total_days) * 100, 1) : 0;

        // Staff initials for avatar
        $initials = strtoupper(substr($row->first_name ?? '', 0, 1) . substr($row->last_name ?? '', 0, 1));
        @endphp

        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">

                {{-- ========================================== --}}
                {{-- SECTION 1: PAGE HEADER + NAVIGATION        --}}
                {{-- ========================================== --}}
                <div class="card section-card mb-3">
                    <div class="card-block py-3 px-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div>
                                <h5 class="mb-1" style="font-weight: 700;">
                                    <i class="fas fa-file-invoice-dollar text-primary mr-2"></i>{{ __('btn_generate') }} {{ $title }}
                                </h5>
                                <p class="mb-0 text-muted" style="font-size: 13px;">
                                    {{ __('Review attendance, configure allowances and deductions, and generate the payroll for this staff member.') }}
                                </p>
                            </div>
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <a href="{{ route($route.'.index') }}" class="btn btn-outline-primary btn-sm mr-2">
                                    <i class="fas fa-arrow-left mr-1"></i> {{ __('btn_back') }}
                                </a>
                                <a href="{{ route($route.'.generate', ['id' => $row->id, 'month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-sync-alt mr-1"></i> {{ __('btn_refresh') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- ========================================== --}}
                {{-- SECTION 2: STAFF PROFILE + PAYROLL PERIOD   --}}
                {{-- ========================================== --}}
                <div class="card payroll-header-card mb-3">
                    <div class="card-block p-4">
                        <div class="row align-items-center">
                            {{-- Staff Identity --}}
                            <div class="col-lg-5 col-md-6 mb-3 mb-lg-0">
                                <div class="d-flex align-items-center">
                                    <div class="staff-avatar mr-3">{{ $initials }}</div>
                                    <div>
                                        <h5 class="mb-0" style="font-weight: 700; font-size: 18px;">
                                            {{ $row->first_name }} {{ $row->last_name }}
                                        </h5>
                                        <div style="font-size: 13px; opacity: 0.8;">
                                            <span class="mr-3"><i class="fas fa-id-badge mr-1"></i> #{{ $row->staff_id }}</span>
                                            @if(isset($payroll))
                                                @if($payroll->status == 0)
                                                <span class="payroll-status-pill draft"><i class="fas fa-edit"></i> {{ __('Draft') }}</span>
                                                @else
                                                <span class="payroll-status-pill approved"><i class="fas fa-check-circle"></i> {{ __('Approved') }}</span>
                                                @endif
                                            @else
                                                <span class="payroll-status-pill new-payroll"><i class="fas fa-plus-circle"></i> {{ __('New Payroll') }}</span>
                                            @endif
                                        </div>
                                        <dl class="staff-meta row mb-0 mt-2" style="font-size: 12px;">
                                            <div class="col-6">
                                                <dt>{{ __('field_department') }}</dt>
                                                <dd>{{ $row->department->title ?? '-' }}</dd>
                                            </div>
                                            <div class="col-6">
                                                <dt>{{ __('field_designation') }}</dt>
                                                <dd>{{ $row->designation->title ?? '-' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>

                            {{-- Employment Details --}}
                            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                                <dl class="staff-meta mb-0">
                                    <dt>{{ __('field_contract_type') }}</dt>
                                    <dd>
                                        @if($row->contract_type == 1)
                                            <i class="fas fa-briefcase mr-1" style="opacity: 0.7;"></i>{{ __('contract_type_full_time') }}
                                        @elseif($row->contract_type == 2)
                                            <i class="fas fa-clock mr-1" style="opacity: 0.7;"></i>{{ __('contract_type_part_time') }}
                                        @else
                                            -
                                        @endif
                                    </dd>
                                    <dt>{{ __('field_salary_type') }}</dt>
                                    <dd>
                                        @if($row->salary_type == 1)
                                            <i class="fas fa-calendar-alt mr-1" style="opacity: 0.7;"></i>{{ __('salary_type_fixed') }}
                                        @elseif($row->salary_type == 2)
                                            <i class="fas fa-hourglass-half mr-1" style="opacity: 0.7;"></i>{{ __('salary_type_hourly') }}
                                        @else
                                            -
                                        @endif
                                    </dd>
                                </dl>
                            </div>

                            {{-- Period and Salary --}}
                            <div class="col-lg-4 col-md-12">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="payroll-period-badge">
                                            <div class="period-label">{{ __('Payroll Period') }}</div>
                                            <div class="period-month">{{ date("M", strtotime($selected_year.'-'.$selected_month.'-01')) }}</div>
                                            <div style="font-size: 13px; opacity: 0.8;">{{ $selected_year }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="salary-highlight">
                                            <div class="sal-label">{{ __('field_basic_salary') }}</div>
                                            <div class="sal-value">
                                                @if(isset($setting->decimal_place))
                                                {{ number_format((float)$basic_salary, $setting->decimal_place, '.', ',') }}
                                                @else
                                                {{ number_format((float)$basic_salary, 0, '.', ',') }}
                                                @endif
                                            </div>
                                            <div style="font-size: 11px; opacity: 0.7;">{!! $setting->currency_symbol !!} / {{ __('month') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- ========================================== --}}
                {{-- SECTION 3: ATTENDANCE OVERVIEW              --}}
                {{-- ========================================== --}}
                <div class="card attendance-card section-card mb-3">
                    <div class="card-header">
                        <div>
                            <h5><i class="fas fa-calendar-check text-primary mr-2"></i>{{ __('field_attendance') }}</h5>
                            <p class="section-desc mb-0">
                                {{ date("F Y", strtotime($selected_year.'-'.$selected_month.'-01')) }}
                                &mdash; {{ $total_days }} {{ __('calendar days') }}
                                &middot; {{ $payable_days }} {{ __('payable') }}, {{ $unpayable_days }} {{ __('unpayable') }}
                            </p>
                        </div>
                    </div>
                    <div class="card-block px-4 pb-3 pt-2">
                        {{-- Attendance Visual Bar --}}
                        <div class="attendance-summary-bar mb-3" title="{{ $attendance_pct }}% attendance rate">
                            @if($total_days > 0)
                            <div class="bar-segment" style="width: {{ ($present/$total_days)*100 }}%; background: #1cc88a;"></div>
                            <div class="bar-segment" style="width: {{ ($holiday/$total_days)*100 }}%; background: #28a745;"></div>
                            <div class="bar-segment" style="width: {{ ($paid_leave/$total_days)*100 }}%; background: #4e73df;"></div>
                            <div class="bar-segment" style="width: {{ ($leave/$total_days)*100 }}%; background: #f6c23e;"></div>
                            <div class="bar-segment" style="width: {{ ($unpaid_leave/$total_days)*100 }}%; background: #e74a3b;"></div>
                            <div class="bar-segment" style="width: {{ ($absent/$total_days)*100 }}%; background: #dc3545;"></div>
                            @endif
                        </div>

                        {{-- Stat Cards --}}
                        <div class="row">
                            <div class="col-4 col-md-2 mb-2">
                                <div class="attendance-stat att-present">
                                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                                    <span class="stat-value">{{ $present }}</span>
                                    <span class="stat-label">{{ __('attendance_present') }}</span>
                                </div>
                            </div>
                            <div class="col-4 col-md-2 mb-2">
                                <div class="attendance-stat att-absent">
                                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                                    <span class="stat-value">{{ $absent }}</span>
                                    <span class="stat-label">{{ __('attendance_absent') }}</span>
                                </div>
                            </div>
                            <div class="col-4 col-md-2 mb-2">
                                <div class="attendance-stat att-leave">
                                    <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                                    <span class="stat-value">{{ $leave }}</span>
                                    <span class="stat-label">{{ __('attendance_leave') }}</span>
                                </div>
                            </div>
                            <div class="col-4 col-md-2 mb-2">
                                <div class="attendance-stat att-paid-leave">
                                    <div class="stat-icon"><i class="fas fa-money-check-alt"></i></div>
                                    <span class="stat-value">{{ $paid_leave }}</span>
                                    <span class="stat-label">{{ __('field_paid_leave') }}</span>
                                </div>
                            </div>
                            <div class="col-4 col-md-2 mb-2">
                                <div class="attendance-stat att-unpaid-leave">
                                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                                    <span class="stat-value">{{ $unpaid_leave }}</span>
                                    <span class="stat-label">{{ __('field_unpaid_leave') }}</span>
                                </div>
                            </div>
                            <div class="col-4 col-md-2 mb-2">
                                <div class="attendance-stat att-holiday">
                                    <div class="stat-icon"><i class="fas fa-flag"></i></div>
                                    <span class="stat-value">{{ $holiday }}</span>
                                    <span class="stat-label">{{ __('attendance_holiday') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- ============================================= --}}
                {{-- SECTION 4: PAYROLL FORM                        --}}
                {{-- ============================================= --}}
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="user_id" value="{{ $row->id }}">
                <input type="hidden" name="basic_salary" value="{{ round($basic_salary, 2) }}">
                <input type="hidden" name="salary_type" value="{{ $row->salary_type }}">
                <input type="hidden" name="salary_month" value="{{ date("Y-m-d", strtotime($selected_year.'-'.$selected_month.'-01')) }}">

                <div class="row">
                    {{-- ---- COLUMN LEFT: Allowances ---- --}}
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card section-card entry-card h-100">
                            <div class="card-header">
                                <div>
                                    <h5><i class="fas fa-plus-circle text-success mr-2"></i>{{ __('field_total_allowance') }}</h5>
                                    <p class="section-desc mb-0">{{ __('Additional earnings such as transport, housing, and performance bonuses.') }}</p>
                                </div>
                                <button id="addAllowance" type="button" class="btn btn-success btn-sm" title="{{ __('Add new allowance') }}">
                                    <i class="fas fa-plus mr-1"></i> {{ __('Add') }}
                                </button>
                            </div>
                            <div class="card-block">
                                @isset($payroll)
                                @foreach($payroll->details->where('status', 1) as $detail)
                                <div id="allowanceFormField" class="row entry-row align-items-end">
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="title" class="form-label" style="font-size: 12px;">{{ __('field_title') }} <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="allowance_titles[]" required>
                                            <option value="{{ $detail->title }}" selected>{{ $detail->title }}</option>
                                            @foreach($allowance_types as $type)
                                                @if($type->title != $detail->title)
                                                <option value="{{ $type->title }}">{{ $type->title }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                                    </div>
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="allowance" class="form-label" style="font-size: 12px;">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm allowance" name="allowances[]" id="allowance" value="{{ round($detail->amount) }}" data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})" required>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_amount') }}</div>
                                    </div>
                                    <div class="form-group col-md-2 mb-1 text-center">
                                        <button id="removeAllowance" type="button" class="btn btn-outline-danger btn-sm btn-icon" title="{{ __('Remove') }}"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                                @endforeach
                                @endisset

                                <div id="newAllowance" class="clearfix"></div>

                                @if(!isset($payroll) || $payroll->details->where('status', 1)->count() == 0)
                                <div class="text-center py-3 text-muted" id="noAllowancePlaceholder">
                                    <i class="fas fa-hand-holding-usd" style="font-size: 28px; opacity: 0.3;"></i>
                                    <p class="mb-0 mt-1" style="font-size: 12px;">{{ __('No allowances added yet. Click +Add to add one.') }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ---- COLUMN MIDDLE: Deductions ---- --}}
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card section-card entry-card h-100">
                            <div class="card-header">
                                <div>
                                    <h5><i class="fas fa-minus-circle text-danger mr-2"></i>{{ __('field_total_deduction') }}</h5>
                                    <p class="section-desc mb-0">{{ __('Deductions such as loans, advances, penalties, or other withholdings.') }}</p>
                                </div>
                                <button id="addDeduction" type="button" class="btn btn-danger btn-sm" title="{{ __('Add new deduction') }}">
                                    <i class="fas fa-plus mr-1"></i> {{ __('Add') }}
                                </button>
                            </div>
                            <div class="card-block">
                                @isset($payroll)
                                @foreach($payroll->details->where('status', 0) as $detail)
                                <div id="deductionFormField" class="row entry-row align-items-end">
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="title" class="form-label" style="font-size: 12px;">{{ __('field_title') }} <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="deduction_titles[]" required>
                                            <option value="{{ $detail->title }}" selected>{{ $detail->title }}</option>
                                            @foreach($deduction_types as $type)
                                                @if($type->title != $detail->title)
                                                <option value="{{ $type->title }}">{{ $type->title }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                                    </div>
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="deduction" class="form-label" style="font-size: 12px;">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm deduction" name="deductions[]" id="deduction" value="{{ round($detail->amount) }}" data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})" required>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_amount') }}</div>
                                    </div>
                                    <div class="form-group col-md-2 mb-1 text-center">
                                        <button id="removeDeduction" type="button" class="btn btn-outline-danger btn-sm btn-icon" title="{{ __('Remove') }}"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                                @endforeach
                                @endisset

                                <div id="newDeduction" class="clearfix"></div>

                                @if(!isset($payroll) || $payroll->details->where('status', 0)->count() == 0)
                                <div class="text-center py-3 text-muted" id="noDeductionPlaceholder">
                                    <i class="fas fa-file-invoice" style="font-size: 28px; opacity: 0.3;"></i>
                                    <p class="mb-0 mt-1" style="font-size: 12px;">{{ __('No deductions added yet. Click +Add to add one.') }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ---- COLUMN RIGHT: Salary Calculation ---- --}}
                    <div class="col-lg-4 col-md-12 mb-3">
                        <div class="card section-card h-100">
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between w-100">
                                    <div>
                                        <h5><i class="fas fa-calculator text-info mr-2"></i>{{ __('field_calculate') }}</h5>
                                        <p class="section-desc mb-0">{{ __('Auto-calculated based on basic salary, allowances, deductions, and applicable taxes.') }}</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-info btn-sm ml-2" data_id="add-{{ $row->id }}" onclick="salaryCalculator('add', {{ $row->id }})" title="{{ __('Recalculate') }}">
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-block">

                                {{-- Total Earning --}}
                                <div class="calc-field-group highlight-earning">
                                    <label for="total_earning"><i class="fas fa-wallet mr-1"></i>{{ __('field_total_earning') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" name="total_earning" id="total_earning" value="{{ round($total_earning, 0) }}" readonly required data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})">
                                    <div class="field-desc">{{ __('Base salary before any allowances or deductions.') }}</div>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_total_earning') }}</div>
                                </div>

                                {{-- Total Allowance --}}
                                <div class="calc-field-group">
                                    <label for="total_allowance"><i class="fas fa-arrow-up text-success mr-1"></i>{{ __('field_total_allowance') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control text-success" name="total_allowance" id="total_allowance" value="{{ round($total_allowance, 0) }}" readonly required data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})">
                                    <div class="field-desc">{{ __('Sum of all allowance entries from the left panel.') }}</div>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_total_allowance') }}</div>
                                </div>

                                {{-- Total Deduction --}}
                                <div class="calc-field-group">
                                    <label for="total_deduction"><i class="fas fa-arrow-down text-danger mr-1"></i>{{ __('field_total_deduction') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control text-danger" name="total_deduction" id="total_deduction" value="{{ round($total_deduction, 0) }}" readonly required data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})">
                                    <div class="field-desc">{{ __('Sum of all deduction entries from the middle panel.') }}</div>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_total_deduction') }}</div>
                                </div>

                                {{-- Gross Salary --}}
                                <div class="calc-field-group highlight-gross">
                                    <label for="gross_salary"><i class="fas fa-coins text-info mr-1"></i>{{ __('field_gross_salary') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" name="gross_salary" id="gross_salary" value="{{ round($gross_salary, 0) }}" readonly required data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})" style="color: #36b9cc;">
                                    <div class="field-desc">{{ __('Earning + Allowances - Deductions. This is the gross pay before tax.') }}</div>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_gross_salary') }}</div>
                                </div>

                                <hr class="my-2" style="border-style: dashed;">

                                {{-- Employee Tax --}}
                                <div class="calc-field-group highlight-tax">
                                    <label for="tax"><i class="fas fa-percentage text-danger mr-1"></i>{{ __('field_employee_tax') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" name="tax" id="tax" value="{{ round($tax_amount, 0) }}" readonly required data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})" style="color: #e74a3b;">
                                    <div class="field-desc">{{ __('Total employee tax deductions (groups + standalone + dependent). Effective rate:') }} <strong>{{ $effective_rate }}%</strong></div>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_tax') }}</div>

                                    {{-- Two things about this figure are not
                                         visible from the number itself, so they are
                                         stated rather than left to be discovered. --}}
                                    <div class="field-desc text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        {{ __('Calculated on basic salary. Allowances and bonuses are paid but not taxed.') }}
                                    </div>

                                    @php
                                        // A salary in an uncovered range is charged the
                                        // band below it and the payslip looks normal, so
                                        // the only way to know is to say so here.
                                        $salary_gaps = [];
                                        foreach($tax_groups as $g) {
                                            $gap = $g->gapFor($total_earning);
                                            if ($gap) { $salary_gaps[] = ['group' => $g->title] + $gap; }
                                        }
                                    @endphp

                                    @if(count($salary_gaps) > 0)
                                    <div class="alert alert-warning py-2 px-2 mt-2 mb-0" style="font-size: 12px;">
                                        <strong><i class="fas fa-exclamation-triangle"></i> {{ __('This salary has no band of its own') }}</strong>
                                        <ul class="mb-0 pl-3 mt-1">
                                            @foreach($salary_gaps as $gap)
                                            <li>
                                                <strong>{{ $gap['group'] }}</strong>:
                                                {{ number_format($total_earning) }}
                                                @if($gap['to'])
                                                    {{ __('falls between bands') }} ({{ number_format($gap['from']) }} – {{ number_format($gap['to']) }})
                                                @else
                                                    {{ __('is above the highest band') }} ({{ __('from') }} {{ number_format($gap['from']) }})
                                                @endif
                                                — {{ __('charged as') }} <em>{{ $gap['charged_as'] }}</em>
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif

                                    {{-- Tax Breakdown Panel --}}
                                    <div class="tax-breakdown-panel" id="taxBreakdownPanel">

                                        {{-- === Tax Groups === --}}
                                        @if(isset($tax_groups) && $tax_groups->count() > 0)
                                        <div class="tax-section-title"><i class="fas fa-layer-group mr-1"></i> {{ __('Tax Groups') }}</div>
                                        @foreach($tax_groups as $group)
                                            @php $groupResult = $group->calculateTax($total_earning, $staff_tax_exemptions); @endphp
                                            @if($groupResult['amount'] > 0 || count($groupResult['breakdown']) > 0)
                                            <div class="tax-item">
                                                <div class="tax-icon"><i class="fas fa-layer-group text-primary"></i></div>
                                                <div class="tax-info">
                                                    <div class="tax-name">
                                                        {{ $group->title }}
                                                        @if($group->is_progressive)
                                                        <span class="badge badge-info" style="font-size: 10px;">{{ __('progressive') }}</span>
                                                        @endif
                                                    </div>
                                                    @foreach($groupResult['breakdown'] as $bracket)
                                                    <div class="tax-detail">
                                                        @if($bracket['is_exempt'])
                                                            @if($bracket['custom_rate'])
                                                            <i class="fas fa-user-tag text-warning"></i> {{ $bracket['title'] }}: <span class="text-warning">{{ $bracket['rate'] }}</span>
                                                            @else
                                                            <i class="fas fa-shield-alt text-success"></i> {{ $bracket['title'] }}: <span class="text-success">{{ __('fully_exempt') }}</span>
                                                            @endif
                                                        @else
                                                        <i class="fas fa-caret-right"></i> {{ $bracket['title'] }}: {{ $bracket['rate'] }} = {{ number_format($bracket['tax_amount'], 2) }} {!! $setting->currency_symbol !!}
                                                        @endif
                                                        <span class="text-muted">({{ $bracket['range'] }})</span>
                                                    </div>
                                                    @endforeach
                                                </div>
                                                <div class="tax-amount text-danger">{{ number_format($groupResult['amount'], 0) }} {!! $setting->currency_symbol !!}</div>
                                            </div>
                                            @endif
                                        @endforeach
                                        @endif

                                        {{-- === Standalone Taxes === --}}
                                        @if(isset($taxs) && collect($taxs)->filter(fn($t) => $t->min_amount <= $total_earning && $t->max_amount >= $total_earning)->count() > 0)
                                        <div class="tax-section-title mt-2"><i class="fas fa-receipt mr-1"></i> {{ __('Standalone Taxes') }}</div>
                                        @foreach($taxs as $tax)
                                            @if($tax->min_amount <= $total_earning && $tax->max_amount >= $total_earning)
                                            @php
                                            $is_exempt = $row->exemptTaxes->contains('id', $tax->id);
                                            $exemption = $staff_tax_exemptions->get($tax->id);
                                            $has_custom_tax = false;
                                            $custom_tax_display = '';
                                            $paid_by = $tax->paid_by ?? 'employee';

                                            if($is_exempt && $exemption) {
                                                if($exemption->custom_percentage && $tax->tax_type == 1) {
                                                    $has_custom_tax = true;
                                                    $custom_tax_display = number_format($exemption->custom_percentage, 2) . '% (' . __('custom_rate') . ')';
                                                } elseif($exemption->custom_fixed_amount && $tax->tax_type == 2) {
                                                    $has_custom_tax = true;
                                                    $custom_tax_display = number_format($exemption->custom_fixed_amount, $setting->decimal_place ?? 2, '.', '') . ' ' . $setting->currency_symbol . ' (' . __('custom_rate') . ')';
                                                }
                                            }
                                            $st_result = $standalone_tax_results[$tax->id] ?? ['employee' => 0, 'employer' => 0];
                                            $st_total = $st_result['employee'] + $st_result['employer'];
                                            @endphp
                                            <div class="tax-item">
                                                <div class="tax-icon">
                                                    @if($has_custom_tax)
                                                    <i class="fas fa-user-tag text-warning"></i>
                                                    @elseif($is_exempt)
                                                    <i class="fas fa-shield-alt text-success"></i>
                                                    @else
                                                    <i class="fas fa-receipt text-secondary"></i>
                                                    @endif
                                                </div>
                                                <div class="tax-info">
                                                    <div class="tax-name">
                                                        {{ $tax->title ?? 'Tax' }}
                                                        @if($paid_by == 'both')
                                                        <span class="badge badge-success" style="font-size: 10px;"><i class="fas fa-handshake"></i> {{ __('shared') }}</span>
                                                        @elseif($paid_by == 'employer')
                                                        <span class="badge badge-primary" style="font-size: 10px;"><i class="fas fa-building"></i> {{ __('employer_only') }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="tax-detail">
                                                        @if($has_custom_tax)
                                                            <span class="text-warning">{{ $custom_tax_display }}</span>
                                                        @elseif($is_exempt)
                                                            <span class="text-success">{{ __('fully_exempt') }}</span>
                                                        @else
                                                            @if($tax->tax_type == 2)
                                                            {{ __('Fixed') }}: {{ number_format((float)$tax->fixed_amount, 2) }} {!! $setting->currency_symbol !!}
                                                            @else
                                                            {{ __('Rate') }}: {{ number_format($tax->percentange, 2) }}%
                                                            @endif
                                                            @if($paid_by == 'both')
                                                            &middot; {{ __('employer') }}: {{ $tax->tax_type == 2 ? number_format((float)$tax->employer_fixed_amount, 2) . ' ' . $setting->currency_symbol : number_format($tax->employer_percentage, 2) . '%' }}
                                                            @endif
                                                        @endif
                                                        <span class="text-muted d-block" style="font-size: 10px;">
                                                            {{ __('field_range') }}: {{ number_format((float)$tax->min_amount, 0) }} - {{ number_format((float)$tax->max_amount, 0) }} {!! $setting->currency_symbol !!}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="tax-amount {{ $is_exempt && !$has_custom_tax ? 'text-success' : 'text-danger' }}">
                                                    @if($is_exempt && !$has_custom_tax)
                                                        0
                                                    @else
                                                        {{ number_format($st_total, 0) }}
                                                    @endif
                                                    {!! $setting->currency_symbol !!}
                                                </div>
                                            </div>
                                            @endif
                                        @endforeach
                                        @endif

                                        {{-- === Dependent Taxes === --}}
                                        @if(isset($dependent_taxs) && $dependent_taxs->count() > 0)
                                        @php $has_applicable_deps = $dependent_taxs->filter(fn($d) => $d->min_amount <= $total_earning && $d->max_amount >= $total_earning)->count() > 0; @endphp
                                        @if($has_applicable_deps)
                                        <div class="tax-section-title mt-2"><i class="fas fa-link mr-1"></i> {{ __('Dependent Taxes') }}</div>
                                        @foreach($dependent_taxs as $dep_tax)
                                            @if($dep_tax->min_amount <= $total_earning && $dep_tax->max_amount >= $total_earning)
                                            @php
                                            $dep_is_exempt = $row->exemptTaxes->contains('id', $dep_tax->id);
                                            $dep_result = $standalone_tax_results[$dep_tax->id] ?? null;
                                            $dep_source_amount = $dep_result['source_amount'] ?? 0;
                                            $dep_employee_tax = $dep_result['employee'] ?? 0;
                                            $dep_employer_tax = $dep_result['employer'] ?? 0;
                                            $dep_source_label = $dep_tax->dependency_label ?? __('unknown');
                                            @endphp
                                            <div class="tax-item">
                                                <div class="tax-icon"><i class="fas fa-link text-warning"></i></div>
                                                <div class="tax-info">
                                                    <div class="tax-name">
                                                        {{ $dep_tax->title ?? 'Tax' }}
                                                        <span class="badge badge-warning" style="font-size: 10px;">{{ __('dependent') }}</span>
                                                    </div>
                                                    <div class="tax-detail">
                                                        @if($dep_is_exempt)
                                                            <span class="text-success">{{ __('fully_exempt') }}</span>
                                                        @else
                                                            @if($dep_tax->tax_type == 1)
                                                            {{ number_format($dep_tax->percentange, 2) }}%
                                                            @else
                                                            {{ number_format($dep_tax->fixed_amount, 2) }} {!! $setting->currency_symbol !!}
                                                            @endif
                                                            {{ __('of') }} <strong>{{ $dep_source_label }}</strong>
                                                        @endif
                                                        <span class="text-muted d-block" style="font-size: 10px;">
                                                            <i class="fas fa-caret-right"></i> {{ __('source_tax_output') }}: {{ number_format($dep_source_amount, 0) }} {!! $setting->currency_symbol !!}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="tax-amount text-danger">{{ number_format($dep_employee_tax + $dep_employer_tax, 0) }} {!! $setting->currency_symbol !!}</div>
                                            </div>
                                            @endif
                                        @endforeach
                                        @endif
                                        @endif
                                    </div>
                                </div>

                                {{-- Employer Tax --}}
                                <input type="hidden" name="employer_tax" id="employer_tax" value="{{ round($employer_tax_amount, 0) }}" data_id="add-{{ $row->id }}">

                                @if($employer_tax_amount > 0)
                                <div class="calc-field-group highlight-employer">
                                    <label for="employer_tax_display"><i class="fas fa-building text-warning mr-1"></i>{{ __('field_employer_tax') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" id="employer_tax_display" value="{{ round($employer_tax_amount, 0) }}" readonly data_id="add-{{ $row->id }}" style="color: #d48806;">
                                    <div class="field-desc">{{ __('Tax contributions borne by the institution. Not deducted from staff salary.') }}</div>
                                </div>
                                @endif

                                <hr class="my-2" style="border-style: dashed;">

                                {{-- Net Salary --}}
                                <div class="calc-field-group highlight-net">
                                    <label for="net_salary"><i class="fas fa-hand-holding-usd text-success mr-1"></i>{{ __('field_net_salary') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" name="net_salary" id="net_salary" value="{{ round($net_salary, 0) }}" readonly required data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})" style="color: #1cc88a; font-size: 20px;">
                                    <div class="field-desc">{{ __('Take-home pay after all employee tax deductions. Gross Salary - Employee Tax.') }}</div>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_net_salary') }}</div>
                                </div>

                                {{-- Total Cost --}}
                                <input type="hidden" name="total_cost" id="total_cost" value="{{ round($total_cost, 0) }}" data_id="add-{{ $row->id }}">

                                @if($employer_tax_amount > 0)
                                <div class="calc-field-group highlight-cost">
                                    <label for="total_cost_display"><i class="fas fa-university mr-1"></i>{{ __('field_total_cost') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" id="total_cost_display" value="{{ round($total_cost, 0) }}" readonly data_id="add-{{ $row->id }}" style="color: #5a5c69; font-size: 18px;">
                                    <div class="field-desc">{{ __('Total institutional cost: Net Salary + Employee Tax + Employer Tax.') }}</div>
                                </div>
                                @endif

                                {{-- Submit --}}
                                <div class="mt-3">
                                    <button type="button" id="btnShowConfirmModal" class="btn btn-success btn-block" style="padding: 10px; font-size: 15px; font-weight: 600;">
                                        <i class="fas fa-check-circle mr-1"></i> {{ __('btn_save') }} {{ $title }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== Confirmation Modal ===== --}}
                <div class="modal fade payroll-confirm-modal" id="payrollConfirmModal" tabindex="-1" role="dialog" aria-labelledby="payrollConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 540px;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="payrollConfirmModalLabel">
                                    <i class="fas fa-clipboard-check mr-2"></i>{{ __('Confirm Payroll Submission') }}
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                {{-- Warning Banner --}}
                                <div class="confirm-warning-banner">
                                    <div class="warn-icon"><i class="fas fa-exclamation-triangle"></i></div>
                                    <div class="warn-text">
                                        <strong>{{ __('Please review carefully before confirming') }}</strong>
                                        {{ __('Ensure all allowances, deductions, and tax calculations are correct. Once saved, this payroll record will be stored and may affect financial reports. Verify the figures below match your expectations.') }}
                                    </div>
                                </div>

                                {{-- Staff Info --}}
                                <div class="confirm-staff-info">
                                    <div class="csi-avatar">{{ $initials }}</div>
                                    <div>
                                        <div class="csi-name">{{ $row->first_name }} {{ $row->last_name }}</div>
                                        <div class="csi-meta">
                                            #{{ $row->staff_id }}
                                            &middot; {{ date("F Y", strtotime($selected_year.'-'.$selected_month.'-01')) }}
                                            &middot; {{ $row->department->title ?? '-' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Summary Grid --}}
                                <div class="confirm-summary-grid">
                                    <div class="confirm-summary-item item-earning">
                                        <div class="csi-label"><i class="fas fa-wallet mr-1"></i>{{ __('field_total_earning') }}</div>
                                        <div class="csi-value" id="confirmEarning">0</div>
                                    </div>
                                    <div class="confirm-summary-item item-gross">
                                        <div class="csi-label"><i class="fas fa-coins mr-1"></i>{{ __('field_gross_salary') }}</div>
                                        <div class="csi-value" id="confirmGross">0</div>
                                    </div>
                                    <div class="confirm-summary-item item-allowance">
                                        <div class="csi-label"><i class="fas fa-arrow-up mr-1"></i>{{ __('field_total_allowance') }}</div>
                                        <div class="csi-value" style="color: #1cc88a;" id="confirmAllowance">0</div>
                                    </div>
                                    <div class="confirm-summary-item item-deduction">
                                        <div class="csi-label"><i class="fas fa-arrow-down mr-1"></i>{{ __('field_total_deduction') }}</div>
                                        <div class="csi-value" style="color: #e74a3b;" id="confirmDeduction">0</div>
                                    </div>
                                    <div class="confirm-summary-item item-tax">
                                        <div class="csi-label"><i class="fas fa-percentage mr-1"></i>{{ __('field_employee_tax') }}</div>
                                        <div class="csi-value" style="color: #e74a3b;" id="confirmTax">0</div>
                                    </div>
                                    <div class="confirm-summary-item item-employer-tax" id="confirmEmployerTaxRow" style="display:none;">
                                        <div class="csi-label"><i class="fas fa-building mr-1"></i>{{ __('field_employer_tax') }}</div>
                                        <div class="csi-value" style="color: #d48806;" id="confirmEmployerTax">0</div>
                                    </div>
                                    <div class="confirm-summary-item item-net">
                                        <div class="csi-label"><i class="fas fa-hand-holding-usd mr-1"></i>{{ __('field_net_salary') }} ({{ __('Take-home Pay') }})</div>
                                        <div class="csi-value" id="confirmNet">0</div>
                                    </div>
                                    <div class="confirm-summary-item item-cost" id="confirmCostRow" style="display:none;">
                                        <div class="csi-label"><i class="fas fa-university mr-1"></i>{{ __('field_total_cost') }} ({{ __('Institutional Cost') }})</div>
                                        <div class="csi-value" id="confirmCost">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-dismiss="modal">
                                    <i class="fas fa-arrow-left mr-1"></i> {{ __('Go Back & Review') }}
                                </button>
                                <button type="button" id="btnConfirmPayroll" class="btn btn-success" style="font-weight: 600; padding: 8px 24px;">
                                    <i class="fas fa-check-double mr-1"></i> {{ __('Confirm & Save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                </form>


                {{-- ========================================== --}}
                {{-- SECTION 5: PAYROLL SUMMARY FOOTER           --}}
                {{-- ========================================== --}}
                <div class="payroll-summary-footer mb-4">
                    <div class="row align-items-center">
                        <div class="col text-center" style="border-right: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_basic_salary') }}</div>
                                <div class="sum-value text-info-light">{{ number_format($basic_salary, 0) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-plus"></i></div>
                        <div class="col text-center" style="border-right: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_total_allowance') }}</div>
                                <div class="sum-value text-success-light" id="summaryAllowance">{{ number_format($total_allowance, 0) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-minus"></i></div>
                        <div class="col text-center" style="border-right: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_total_deduction') }}</div>
                                <div class="sum-value text-danger-light" id="summaryDeduction">{{ number_format($total_deduction, 0) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-minus"></i></div>
                        <div class="col text-center" style="border-right: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_employee_tax') }}</div>
                                <div class="sum-value text-danger-light" id="summaryTax">{{ number_format($tax_amount, 0) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-equals"></i></div>
                        <div class="col text-center">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_net_salary') }}</div>
                                <div class="sum-value text-success-light" style="font-size: 22px;" id="summaryNet">{{ number_format($net_salary, 0) }}</div>
                            </div>
                        </div>
                        @if($employer_tax_amount > 0)
                        <div class="col-auto summary-arrow" style="border-left: 1px solid rgba(255,255,255,0.1); padding-left: 15px;">
                            <i class="fas fa-building" style="opacity: 0.5;"></i>
                        </div>
                        <div class="col text-center">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_total_cost') }}</div>
                                <div class="sum-value text-warning-light" id="summaryCost">{{ number_format($total_cost, 0) }}</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
@section('page_js')
    <script type="text/javascript">
    (function ($) {
        "use strict";
        // Add Allowance
        $(document).on('click', '#addAllowance', function () {
            $('#noAllowancePlaceholder').hide();
            var html = '';
            html += '<hr class="my-1"/>';
            html += '<div id="allowanceFormField" class="row entry-row align-items-end">';
            html += '<div class="form-group col-md-5 mb-1"><label class="form-label" style="font-size: 12px;">{{ __('field_title') }} <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="allowance_titles[]" required>';
            html += '<option value="">{{ __('select') }}</option>';
            @foreach($allowance_types as $type)
            html += '<option value="{{ $type->title }}">{{ $type->title }}</option>';
            @endforeach
            html += '</select><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div></div>';
            html += '<div class="form-group col-md-5 mb-1"><label class="form-label" style="font-size: 12px;">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm allowance" name="allowances[]" value="" data_id="add-{{ $row->id }}" onkeyup="salaryCalculator(\'add\', {{ $row->id }})" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_amount') }}</div></div>';
            html += '<div class="form-group col-md-2 mb-1 text-center"><button id="removeAllowance" type="button" class="btn btn-outline-danger btn-sm btn-icon" title="{{ __('Remove') }}"><i class="fas fa-trash-alt"></i></button></div>';
            html += '</div>';
            $('#newAllowance').append(html);
        });

        // Remove Allowance
        $(document).on('click', '#removeAllowance', function () {
            $(this).closest('#allowanceFormField').prev('hr').remove();
            $(this).closest('#allowanceFormField').remove();
            salaryCalculator('add', {{ $row->id }});
        });
    }(jQuery));

    (function ($) {
        "use strict";
        // Add Deduction
        $(document).on('click', '#addDeduction', function () {
            $('#noDeductionPlaceholder').hide();
            var html = '';
            html += '<hr class="my-1"/>';
            html += '<div id="deductionFormField" class="row entry-row align-items-end">';
            html += '<div class="form-group col-md-5 mb-1"><label class="form-label" style="font-size: 12px;">{{ __('field_title') }} <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="deduction_titles[]" required>';
            html += '<option value="">{{ __('select') }}</option>';
            @foreach($deduction_types as $type)
            html += '<option value="{{ $type->title }}">{{ $type->title }}</option>';
            @endforeach
            html += '</select><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div></div>';
            html += '<div class="form-group col-md-5 mb-1"><label class="form-label" style="font-size: 12px;">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm deduction" name="deductions[]" value="" data_id="add-{{ $row->id }}" onkeyup="salaryCalculator(\'add\', {{ $row->id }})" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_amount') }}</div></div>';
            html += '<div class="form-group col-md-2 mb-1 text-center"><button id="removeDeduction" type="button" class="btn btn-outline-danger btn-sm btn-icon" title="{{ __('Remove') }}"><i class="fas fa-trash-alt"></i></button></div>';
            html += '</div>';
            $('#newDeduction').append(html);
        });

        // Remove Deduction
        $(document).on('click', '#removeDeduction', function () {
            $(this).closest('#deductionFormField').prev('hr').remove();
            $(this).closest('#deductionFormField').remove();
            salaryCalculator('add', {{ $row->id }});
        });
    }(jQuery));
    </script>


    <script type="text/javascript">
    "use strict";
    function salaryCalculator(type, id) {

        // Cal Allowance Sum
        var allowance_sum = 0;
        $("#allowanceFormField .allowance, #newAllowance .allowance").each(function () {
          var get_allowance_value = $(this).val();
          if ($.isNumeric(get_allowance_value)) {
            allowance_sum += parseFloat(get_allowance_value);
          }
        });

        // Cal Deduction Sum
        var deduction_sum = 0;
        $("#deductionFormField .deduction, #newDeduction .deduction").each(function () {
          var get_deduction_value = $(this).val();
          if ($.isNumeric(get_deduction_value)) {
            deduction_sum += parseFloat(get_deduction_value);
          }
        });

      // Get Data
      var total_earning = $("input[name='total_earning'][data_id='"+type+"-"+id+"']").val();
      var total_allowance = $("input[name='total_allowance'][data_id='"+type+"-"+id+"']").val();
      var total_deduction = $("input[name='total_deduction'][data_id='"+type+"-"+id+"']").val();
      var gross_salary = $("input[name='gross_salary'][data_id='"+type+"-"+id+"']").val();
      var tax_amount = $("input[name='tax'][data_id='"+type+"-"+id+"']").val();
      var net_salary = $("input[name='net_salary'][data_id='"+type+"-"+id+"']").val();

      // Valid Data
      if (isNaN(allowance_sum)) allowance_sum = 0;
      if (isNaN(deduction_sum)) deduction_sum = 0;

      // Total Gross
      var total_gross = (parseFloat(total_earning) + parseFloat(allowance_sum)) - parseFloat(deduction_sum);

        // Calculate Tax (based on total_earning only)
        @php
        // Prepare tax groups for JavaScript
        $js_tax_groups = [];
        if(isset($tax_groups)){
            foreach($tax_groups as $group){
                $brackets = [];
                foreach($group->brackets as $bracket){
                    $brackets[] = [
                        'id' => $bracket->id,
                        'title' => $bracket->title,
                        'min_amount' => $bracket->min_amount,
                        'max_amount' => $bracket->max_amount,
                        'tax_type' => $bracket->tax_type,
                        'percentange' => $bracket->percentange,
                        'fixed_amount' => $bracket->fixed_amount,
                        'max_no_taxable_amount' => $bracket->max_no_taxable_amount,
                        'paid_by' => $bracket->paid_by ?? 'employee',
                        'employer_percentage' => $bracket->employer_percentage,
                        'employer_fixed_amount' => $bracket->employer_fixed_amount,
                    ];
                }
                $js_tax_groups[] = [
                    'id' => $group->id,
                    'title' => $group->title,
                    'is_progressive' => $group->is_progressive,
                    'brackets' => $brackets,
                ];
            }
        }

        if(isset($taxs)){
        foreach($taxs as $key =>$value){
            $taxs[$key] = json_decode(json_encode($value));
        }
        }
        // Prepare dependent taxes for JavaScript
        $js_dependent_taxs = [];
        if(isset($dependent_taxs)){
            foreach($dependent_taxs as $dep_tax){
                $js_dependent_taxs[] = [
                    'id' => $dep_tax->id,
                    'title' => $dep_tax->title,
                    'min_amount' => $dep_tax->min_amount,
                    'max_amount' => $dep_tax->max_amount,
                    'tax_type' => $dep_tax->tax_type,
                    'percentange' => $dep_tax->percentange,
                    'fixed_amount' => $dep_tax->fixed_amount,
                    'employer_percentage' => $dep_tax->employer_percentage,
                    'employer_fixed_amount' => $dep_tax->employer_fixed_amount,
                    'paid_by' => $dep_tax->paid_by ?? 'employee',
                    'max_no_taxable_amount' => $dep_tax->max_no_taxable_amount,
                    'depends_on_type' => $dep_tax->depends_on_type,
                    'depends_on_id' => $dep_tax->depends_on_id,
                    'dependency_label' => $dep_tax->dependency_label ?? 'source tax',
                ];
            }
        }
        // Only non-expired exemptions apply (staff_tax_exemptions is pre-filtered notExpired)
        $exempt_tax_ids = $staff_tax_exemptions->keys()->map(fn($k) => (int) $k)->toArray();
        // Get exemption details with custom tax values
        $exemption_details = [];
        foreach($staff_tax_exemptions as $tax_id => $exemption) {
            $exemption_details[$tax_id] = [
                'custom_percentage' => $exemption->custom_percentage,
                'custom_fixed_amount' => $exemption->custom_fixed_amount
            ];
        }
        @endphp

        var tax_groups = <?php echo json_encode($js_tax_groups); ?>;
        var taxs = <?php echo json_encode($taxs ?? []); ?>;
        var dependent_taxs = <?php echo json_encode($js_dependent_taxs ?? []); ?>;
        var exempt_tax_ids = <?php echo json_encode($exempt_tax_ids); ?>;
        var exemption_details = <?php echo json_encode($exemption_details); ?>;

        var i, j;
        var tax_info_html = '';
        var total_tax_amount = 0;
        var total_employer_tax = 0;

        // Track results for dependent tax source lookup (2-pass)
        var group_tax_results = {};
        var standalone_tax_results = {};

        // === PASS 1: Calculate base taxes (non-dependent) ===

        // Calculate tax from Tax Groups (progressive)
        var has_group_taxes = false;
        for (i = 0; i < tax_groups.length; ++i) {
            var group = tax_groups[i];
            var group_tax = 0;
            var group_employer_tax = 0;
            var group_breakdown = [];

            // Step lookup: single applicable bracket = highest min_amount <= earning
            var sel = null;
            for (j = 0; j < group.brackets.length; ++j) {
                var b = group.brackets[j];
                if(parseFloat(total_earning) >= parseFloat(b.min_amount)) {
                    if(sel === null || parseFloat(b.min_amount) >= parseFloat(sel.min_amount)) {
                        sel = b;
                    }
                }
            }

            if(sel !== null) {
                var bracket = sel;
                var is_exempt = exempt_tax_ids.includes(bracket.id);
                var has_custom_tax = exemption_details.hasOwnProperty(bracket.id);
                var taxable_amount = Math.max(0, parseFloat(total_earning) - parseFloat(bracket.max_no_taxable_amount || 0));
                var bracket_tax = 0;

                if(is_exempt && has_custom_tax) {
                    if(bracket.tax_type == 2 && exemption_details[bracket.id].custom_fixed_amount) {
                        bracket_tax = parseFloat(exemption_details[bracket.id].custom_fixed_amount);
                    } else if(bracket.tax_type == 1 && exemption_details[bracket.id].custom_percentage) {
                        bracket_tax = (taxable_amount / 100) * parseFloat(exemption_details[bracket.id].custom_percentage);
                    }
                } else if(!is_exempt) {
                    if(bracket.tax_type == 2) {
                        bracket_tax = parseFloat(bracket.fixed_amount);
                    } else {
                        bracket_tax = (taxable_amount / 100) * parseFloat(bracket.percentange);
                    }
                    // Employer contribution for the same single bracket
                    var g_paid_by = bracket.paid_by || 'employee';
                    if(g_paid_by == 'employer' || g_paid_by == 'both') {
                        if(bracket.tax_type == 2) {
                            group_employer_tax = parseFloat(bracket.employer_fixed_amount || 0);
                        } else {
                            group_employer_tax = (taxable_amount / 100) * parseFloat(bracket.employer_percentage || 0);
                        }
                    }
                }

                group_tax = bracket_tax;
                if(bracket_tax > 0 || is_exempt) {
                    group_breakdown.push({
                        title: bracket.title,
                        tax: bracket_tax,
                        is_exempt: is_exempt
                    });
                }
            }

            total_tax_amount += group_tax;
            total_employer_tax += group_employer_tax;
            group_tax_results[group.id] = { amount: group_tax, employer: group_employer_tax };

            if(group_breakdown.length > 0) {
                if(!has_group_taxes) {
                    tax_info_html += '<div class="tax-section-title"><i class="fas fa-layer-group mr-1"></i> Tax Groups</div>';
                    has_group_taxes = true;
                }
                tax_info_html += '<div class="tax-item"><div class="tax-icon"><i class="fas fa-layer-group text-primary"></i></div>';
                tax_info_html += '<div class="tax-info"><div class="tax-name">' + group.title;
                if(group.is_progressive) {
                    tax_info_html += ' <span class="badge badge-info" style="font-size:10px">progressive</span>';
                }
                tax_info_html += '</div></div>';
                tax_info_html += '<div class="tax-amount text-danger">' + group_tax.toFixed(0) + ' {!! $setting->currency_symbol !!}</div></div>';
            }
        }

        // Calculate tax from standalone brackets
        var has_standalone_taxes = false;
        for (i = 0; i < taxs.length; ++i) {
            if(taxs[i]['min_amount'] <= parseFloat(total_earning) && taxs[i]['max_amount'] >= parseFloat(total_earning)){

                if(!has_standalone_taxes) {
                    tax_info_html += '<div class="tax-section-title mt-2"><i class="fas fa-receipt mr-1"></i> Standalone Taxes</div>';
                    has_standalone_taxes = true;
                }

                var is_exempt = exempt_tax_ids.includes(taxs[i]['id']);
                var has_custom_tax = exemption_details.hasOwnProperty(taxs[i]['id']);
                var taxable_amount = parseFloat(total_earning) - taxs[i]['max_no_taxable_amount'];
                var paid_by = taxs[i]['paid_by'] || 'employee';

                var current_employee_tax = 0;
                var current_employer_tax = 0;
                var tax_display = '';
                var tax_icon = '<i class="fas fa-receipt text-secondary"></i>';

                if(is_exempt && has_custom_tax) {
                    tax_icon = '<i class="fas fa-user-tag text-warning"></i>';
                    if(taxs[i]['tax_type'] == 2 && exemption_details[taxs[i]['id']].custom_fixed_amount) {
                        current_employee_tax = parseFloat(exemption_details[taxs[i]['id']].custom_fixed_amount);
                        tax_display = '<span class="text-warning">Custom: ' + current_employee_tax.toFixed(2) + ' {!! $setting->currency_symbol !!}</span>';
                    } else if(taxs[i]['tax_type'] == 1 && exemption_details[taxs[i]['id']].custom_percentage) {
                        var custom_pct = parseFloat(exemption_details[taxs[i]['id']].custom_percentage);
                        current_employee_tax = (taxable_amount / 100) * custom_pct;
                        tax_display = '<span class="text-warning">Custom: ' + custom_pct.toFixed(2) + '%</span>';
                    } else {
                        current_employee_tax = 0;
                        tax_display = '<span class="text-success">Fully Exempt</span>';
                    }
                } else if(is_exempt) {
                    tax_icon = '<i class="fas fa-shield-alt text-success"></i>';
                    current_employee_tax = 0;
                    tax_display = '<span class="text-success">Fully Exempt</span>';
                } else {
                    if(paid_by == 'employee' || paid_by == 'both') {
                        if(taxs[i]['tax_type'] == 2) {
                            current_employee_tax = parseFloat(taxs[i]['fixed_amount'] || 0);
                        } else {
                            current_employee_tax = (taxable_amount / 100) * parseFloat(taxs[i]['percentange'] || 0);
                        }
                    }
                    if(paid_by == 'employer' || paid_by == 'both') {
                        if(taxs[i]['tax_type'] == 2) {
                            current_employer_tax = parseFloat(taxs[i]['employer_fixed_amount'] || 0);
                        } else {
                            current_employer_tax = (taxable_amount / 100) * parseFloat(taxs[i]['employer_percentage'] || 0);
                        }
                    }
                    if(taxs[i]['tax_type'] == 2) {
                        tax_display = 'Fixed: ' + current_employee_tax.toFixed(2) + ' {!! $setting->currency_symbol !!}';
                    } else {
                        tax_display = 'Rate: ' + parseFloat(taxs[i]['percentange']).toFixed(2) + '%';
                    }
                    if(paid_by == 'both') {
                        tax_display += ' <span class="badge badge-success" style="font-size:10px"><i class="fas fa-handshake"></i> Shared</span>';
                    }
                }

                var tax_title = taxs[i]['title'] ? taxs[i]['title'] : 'Tax';
                var item_total = current_employee_tax + current_employer_tax;
                tax_info_html += '<div class="tax-item"><div class="tax-icon">' + tax_icon + '</div>';
                tax_info_html += '<div class="tax-info"><div class="tax-name">' + tax_title + '</div>';
                tax_info_html += '<div class="tax-detail">' + tax_display + '</div></div>';
                tax_info_html += '<div class="tax-amount ' + (is_exempt && !has_custom_tax ? 'text-success' : 'text-danger') + '">' + item_total.toFixed(0) + ' {!! $setting->currency_symbol !!}</div></div>';

                standalone_tax_results[taxs[i]['id']] = {
                    employee: current_employee_tax,
                    employer: current_employer_tax
                };

                total_tax_amount += current_employee_tax;
                total_employer_tax += current_employer_tax;
            }
        }

        // === PASS 2: Calculate dependent taxes ===
        var has_dependent_taxes = false;
        for (i = 0; i < dependent_taxs.length; ++i) {
            var dep = dependent_taxs[i];

            if(parseFloat(dep.min_amount) > parseFloat(total_earning) || parseFloat(dep.max_amount) < parseFloat(total_earning)) {
                continue;
            }

            if(!has_dependent_taxes) {
                tax_info_html += '<div class="tax-section-title mt-2"><i class="fas fa-link mr-1"></i> Dependent Taxes</div>';
                has_dependent_taxes = true;
            }

            var source_amount = 0;
            if(dep.depends_on_type === 'tax_group' && group_tax_results.hasOwnProperty(dep.depends_on_id)) {
                source_amount = group_tax_results[dep.depends_on_id].amount + (group_tax_results[dep.depends_on_id].employer || 0);
            } else if(dep.depends_on_type === 'tax_setting' && standalone_tax_results.hasOwnProperty(dep.depends_on_id)) {
                var src = standalone_tax_results[dep.depends_on_id];
                source_amount = src.employee + src.employer;
            }

            var is_exempt = exempt_tax_ids.includes(dep.id);
            var has_custom_tax = exemption_details.hasOwnProperty(dep.id);
            var dep_employee_tax = 0;
            var dep_employer_tax = 0;
            var dep_display = '';

            if(is_exempt && has_custom_tax) {
                if(dep.tax_type == 2 && exemption_details[dep.id].custom_fixed_amount) {
                    dep_employee_tax = parseFloat(exemption_details[dep.id].custom_fixed_amount);
                } else if(dep.tax_type == 1 && exemption_details[dep.id].custom_percentage) {
                    dep_employee_tax = (source_amount / 100) * parseFloat(exemption_details[dep.id].custom_percentage);
                }
                dep_display = '<span class="text-warning">Custom rate</span>';
            } else if(is_exempt) {
                dep_display = '<span class="text-success">Fully Exempt</span>';
            } else {
                var dep_paid_by = dep.paid_by || 'employee';
                if(dep_paid_by == 'employee' || dep_paid_by == 'both') {
                    if(dep.tax_type == 2) { dep_employee_tax = parseFloat(dep.fixed_amount || 0); }
                    else { dep_employee_tax = (source_amount / 100) * parseFloat(dep.percentange || 0); }
                }
                if(dep_paid_by == 'employer' || dep_paid_by == 'both') {
                    if(dep.tax_type == 2) { dep_employer_tax = parseFloat(dep.employer_fixed_amount || 0); }
                    else { dep_employer_tax = (source_amount / 100) * parseFloat(dep.employer_percentage || 0); }
                }
                var dep_rate_display = dep.tax_type == 1 ? parseFloat(dep.percentange || 0).toFixed(2) + '%' : parseFloat(dep.fixed_amount || 0).toFixed(2) + ' {!! $setting->currency_symbol !!}';
                dep_display = dep_rate_display + ' of <strong>' + dep.dependency_label + '</strong>';
            }

            var dep_total = dep_employee_tax + dep_employer_tax;
            tax_info_html += '<div class="tax-item"><div class="tax-icon"><i class="fas fa-link text-warning"></i></div>';
            tax_info_html += '<div class="tax-info"><div class="tax-name">' + dep.title + ' <span class="badge badge-warning" style="font-size:10px">dependent</span></div>';
            tax_info_html += '<div class="tax-detail">' + dep_display;
            tax_info_html += '<span class="text-muted d-block" style="font-size:10px"><i class="fas fa-caret-right"></i> Source output: ' + source_amount.toFixed(0) + ' {!! $setting->currency_symbol !!}</span>';
            tax_info_html += '</div></div>';
            tax_info_html += '<div class="tax-amount text-danger">' + dep_total.toFixed(0) + ' {!! $setting->currency_symbol !!}</div></div>';

            standalone_tax_results[dep.id] = { employee: dep_employee_tax, employer: dep_employer_tax };
            total_tax_amount += dep_employee_tax;
            total_employer_tax += dep_employer_tax;
        }

        // Set the total tax amount
        tax_amount = total_tax_amount;

        // Update tax breakdown panel
        var panel = $('#taxBreakdownPanel');
        panel.html(tax_info_html || '<p class="text-muted text-center mb-0" style="font-size:12px"><i class="fas fa-info-circle"></i> No applicable taxes for current salary.</p>');

      // Net Total
      var net_total = parseFloat(total_gross) - parseFloat(tax_amount);

      // Total Cost
      var total_cost = parseFloat(net_total) + parseFloat(tax_amount) + parseFloat(total_employer_tax);

      // Effective rate
      var eff_rate = parseFloat(total_earning) > 0 ? ((tax_amount / parseFloat(total_earning)) * 100).toFixed(2) : '0.00';

      // Update form fields (Math.round to match PHP round() and the tax report)
      $("input[name='total_allowance'][data_id='"+type+"-"+id+"']").val(Math.round(allowance_sum));
      $("input[name='total_deduction'][data_id='"+type+"-"+id+"']").val(Math.round(deduction_sum));
      $("input[name='gross_salary'][data_id='"+type+"-"+id+"']").val(Math.round(total_gross));
      $("input[name='tax'][data_id='"+type+"-"+id+"']").val(Math.round(tax_amount));
      $("input[name='employer_tax'][data_id='"+type+"-"+id+"']").val(Math.round(total_employer_tax));
      $("input[name='net_salary'][data_id='"+type+"-"+id+"']").val(Math.round(net_total));
      $("input[name='total_cost'][data_id='"+type+"-"+id+"']").val(Math.round(total_cost));

      // Update display fields if they exist
      $("input[id='employer_tax_display'][data_id='"+type+"-"+id+"']").val(Math.round(total_employer_tax));
      $("input[id='total_cost_display'][data_id='"+type+"-"+id+"']").val(Math.round(total_cost));

      // Update summary footer
      $('#summaryAllowance').text(Math.round(allowance_sum).toLocaleString());
      $('#summaryDeduction').text(Math.round(deduction_sum).toLocaleString());
      $('#summaryTax').text(Math.round(tax_amount).toLocaleString());
      $('#summaryNet').text(Math.round(net_total).toLocaleString());
      $('#summaryCost').text(Math.round(total_cost).toLocaleString());
    }

    // ===== Confirmation Modal Logic =====
    $(document).ready(function() {
        var currencySymbol = '{!! $setting->currency_symbol !!}';

        function formatNum(val) {
            var n = parseInt(val) || 0;
            return n.toLocaleString();
        }

        // Show modal when Save button is clicked
        $('#btnShowConfirmModal').on('click', function() {
            // Populate modal fields from current form values
            var earning = $('input[name="total_earning"]').val();
            var allowance = $('input[name="total_allowance"]').val();
            var deduction = $('input[name="total_deduction"]').val();
            var gross = $('input[name="gross_salary"]').val();
            var tax = $('input[name="tax"]').val();
            var employerTax = $('input[name="employer_tax"]').val();
            var net = $('input[name="net_salary"]').val();
            var totalCost = $('input[name="total_cost"]').val();

            $('#confirmEarning').text(formatNum(earning) + ' ' + currencySymbol);
            $('#confirmAllowance').text(formatNum(allowance) + ' ' + currencySymbol);
            $('#confirmDeduction').text(formatNum(deduction) + ' ' + currencySymbol);
            $('#confirmGross').text(formatNum(gross) + ' ' + currencySymbol);
            $('#confirmTax').text(formatNum(tax) + ' ' + currencySymbol);
            $('#confirmNet').text(formatNum(net) + ' ' + currencySymbol);

            if(parseInt(employerTax) > 0) {
                $('#confirmEmployerTax').text(formatNum(employerTax) + ' ' + currencySymbol);
                $('#confirmEmployerTaxRow').show();
                $('#confirmCost').text(formatNum(totalCost) + ' ' + currencySymbol);
                $('#confirmCostRow').show();
            } else {
                $('#confirmEmployerTaxRow').hide();
                $('#confirmCostRow').hide();
            }

            $('#payrollConfirmModal').modal('show');
        });

        // Confirm & submit the form
        $('#btnConfirmPayroll').on('click', function() {
            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> {{ __("Saving...") }}');
            $('form.needs-validation').submit();
        });
    });
    </script>
@endsection
