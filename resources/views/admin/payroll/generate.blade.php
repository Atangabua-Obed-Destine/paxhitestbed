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
        // The figures are PayrollCalculator's (see PayrollController::generate),
        // the same class saving uses. The page only shows them; the live preview
        // asks the server again as lines are typed.
        $paid_leave = \App\Models\Leave::paid_leave($row->id, $selected_month, $selected_year);
        $unpaid_leave = \App\Models\Leave::unpaid_leave($row->id, $selected_month, $selected_year);

        $present = $attendances->where('attendance', 1)->count();
        $absent = $attendances->where('attendance', 2)->count();
        $leave = $attendances->where('attendance', 3)->count();
        $holiday = $attendances->where('attendance', 4)->count();

        $payable_days = $present + $holiday + $paid_leave;
        $unpayable_days = $absent + $unpaid_leave;

        $is_hourly = $calc['salary_type'] === 2;
        $basic_salary = $calc['basic_salary'];
        $total_earning = $calc['total_earning'];
        $total_allowance = $calc['total_allowance'];
        $total_deduction = $calc['total_deduction'];
        $gross_salary = $calc['gross_salary'];
        $tax_amount = $calc['tax'];
        $employer_tax_amount = $calc['employer_tax'];
        $net_salary = $calc['net_salary'];
        $total_cost = $calc['total_cost'];
        $effective_rate = $calc['effective_rate'];
        $taxable_titles = $allowance_types->where('is_taxable', true)->pluck('title')->all();

        $total_attendance = $present + $absent + $leave + $holiday;
        $attendance_pct = $total_days > 0 ? round(($payable_days / $total_days) * 100, 1) : 0;

        $initials = strtoupper(substr($row->first_name ?? '', 0, 1) . substr($row->last_name ?? '', 0, 1));
        $money = fn ($v) => format_amount((float) $v);
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
                                                {{ format_amount((float)$basic_salary) }}
                                                @else
                                                {{ format_amount((float)$basic_salary) }}
                                                @endif
                                            </div>
                                            <div style="font-size: 11px; opacity: 0.7;">{!! $setting->currency_symbol !!} / {{ $is_hourly ? __('hour') : __('month') }}</div>
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
                    @if(!empty($carried_from))
                    <div class="col-12 mb-2">
                        <div class="alert alert-info py-2 mb-0" style="font-size: 13px;">
                            <i class="fas fa-copy mr-1"></i> {{ __('Started from the allowances and deductions of :month. Change or remove any that do not apply this month.', ['month' => $carried_from]) }}
                        </div>
                    </div>
                    @endif
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
                                @foreach($start_allowances as $detail)
                                <div id="allowanceFormField" class="row entry-row align-items-end">
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="title" class="form-label" style="font-size: 12px;">{{ __('field_title') }} <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="allowance_titles[]" required>
                                            <option value="{{ $detail['title'] }}" selected>{{ $detail['title'] }}{{ in_array($detail['title'], $taxable_titles) ? ' · '.__('taxed') : '' }}</option>
                                            @foreach($allowance_types as $type)
                                                @if($type->title != $detail['title'])
                                                <option value="{{ $type->title }}">{{ $type->title }}{{ $type->is_taxable ? ' · '.__('taxed') : '' }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                                    </div>
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="allowance" class="form-label" style="font-size: 12px;">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm allowance" name="allowances[]" id="allowance" value="{{ round((float) $detail['amount']) }}" data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})" required>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_amount') }}</div>
                                    </div>
                                    <div class="form-group col-md-2 mb-1 text-center">
                                        <button id="removeAllowance" type="button" class="btn btn-outline-danger btn-sm btn-icon" title="{{ __('Remove') }}"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                                @endforeach

                                <div id="newAllowance" class="clearfix"></div>

                                @if(count($start_allowances) == 0)
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
                                @foreach($start_deductions as $detail)
                                <div id="deductionFormField" class="row entry-row align-items-end">
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="title" class="form-label" style="font-size: 12px;">{{ __('field_title') }} <span class="text-danger">*</span></label>
                                        @if(!empty($detail['loan_id']))
                                        <input type="text" class="form-control form-control-sm" name="deduction_titles[]" value="{{ $detail['title'] }}" readonly title="{{ __('Loan repayment') }}">
                                        <input type="hidden" name="deduction_loans[]" value="{{ $detail['loan_id'] }}">
                                        @php $loanLeft = optional($open_loans->firstWhere('id', $detail['loan_id']))->available(isset($payroll) ? $payroll->id : null); @endphp
                                        <small class="text-muted"><i class="fas fa-hand-holding-usd"></i> {{ __('Loan repayment') }}@if($loanLeft !== null) · {{ __(':left left', ['left' => format_amount($loanLeft)]) }}@endif</small>
                                        @else
                                        <select class="form-control form-control-sm" name="deduction_titles[]" required>
                                            <option value="{{ $detail['title'] }}" selected>{{ $detail['title'] }}</option>
                                            @foreach($deduction_types as $type)
                                                @if($type->title != $detail['title'])
                                                <option value="{{ $type->title }}">{{ $type->title }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="deduction_loans[]" value="">
                                        @endif
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                                    </div>
                                    <div class="form-group col-md-5 mb-1">
                                        <label for="deduction" class="form-label" style="font-size: 12px;">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm deduction" name="deductions[]" id="deduction" value="{{ round((float) $detail['amount']) }}" data_id="add-{{ $row->id }}" onkeyup="salaryCalculator('add', {{ $row->id }})" required>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_amount') }}</div>
                                    </div>
                                    <div class="form-group col-md-2 mb-1 text-center">
                                        <button id="removeDeduction" type="button" class="btn btn-outline-danger btn-sm btn-icon" title="{{ __('Remove') }}"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                                @endforeach

                                <div id="newDeduction" class="clearfix"></div>

                                @if(count($start_deductions) == 0)
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
                                    <div class="field-desc">
                                        @if($is_hourly)
                                            {{ __(':hours hours present this month at :rate an hour.', ['hours' => rtrim(rtrim(number_format((float) $calc['hours_worked'], 2), '0'), '.'), 'rate' => $money($basic_salary)]) }}
                                        @else
                                            {{ __('Base salary before any allowances or deductions.') }}
                                        @endif
                                    </div>
                                    @foreach($calc['notes'] as $note)
                                    <div class="field-desc text-warning"><i class="fas fa-exclamation-triangle"></i> {{ $note }}</div>
                                    @endforeach
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
                                    <div class="field-desc">{{ __('Total employee tax. Effective rate:') }} <strong id="effectiveRate">{{ $effective_rate }}%</strong></div>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_tax') }}</div>

                                    {{-- What the tax was charged on: the basic pay plus any
                                         allowance whose type is set as taxable. --}}
                                    <div class="field-desc text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="taxBaseNote">{{ __('Taxed on :amount: the :basic plus taxable allowances (:taxable).', ['amount' => $money($calc['taxable_salary']), 'basic' => $is_hourly ? __('hours worked') : __('basic salary'), 'taxable' => $money($calc['taxable_allowance'])]) }}</span>
                                        @can('allowance-type-view')
                                        <a href="{{ route('admin.allowance-type.index') }}" target="_blank">{{ __('Which allowances are taxed?') }}</a>
                                        @endcan
                                    </div>

                                    <div class="alert alert-danger py-2 px-2 mt-2 mb-0" id="payrollProblems" style="font-size: 12px; {{ empty($calc['problems']) ? 'display:none;' : '' }}">
                                        <strong><i class="fas fa-exclamation-triangle"></i> {{ __('This payroll cannot be saved yet') }}</strong>
                                        <ul class="mb-0 pl-3 mt-1" id="payrollProblemList">
                                            @foreach($calc['problems'] as $problem)
                                            <li>{{ $problem }}</li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    {{-- Tax Breakdown Panel: the server's own itemised lines --}}
                                    <div class="tax-breakdown-panel" id="taxBreakdownPanel">
                                        @forelse($calc['tax_lines'] as $line)
                                        <div class="tax-item">
                                            <div class="tax-icon"><i class="fas fa-receipt text-secondary"></i></div>
                                            <div class="tax-info">
                                                <div class="tax-name">{{ $line['label'] }}</div>
                                                @if($line['employer'] > 0)
                                                <div class="tax-detail">{{ __('Staff') }} {{ $money($line['employee']) }} · {{ __('Employer') }} {{ $money($line['employer']) }}</div>
                                                @endif
                                            </div>
                                            <div class="tax-amount text-danger">{{ $money($line['employee']) }} {!! $setting->currency_symbol !!}</div>
                                        </div>
                                        @empty
                                        <p class="text-muted text-center mb-0" style="font-size:12px"><i class="fas fa-info-circle"></i> {{ __('No tax applies to this pay.') }}</p>
                                        @endforelse
                                    </div>
                                </div>

                                {{-- Employer Tax --}}
                                <input type="hidden" name="employer_tax" id="employer_tax" value="{{ round($employer_tax_amount, 0) }}" data_id="add-{{ $row->id }}">

                                <div class="calc-field-group highlight-employer" id="employerTaxGroup" style="{{ $employer_tax_amount > 0 ? '' : 'display:none;' }}">
                                    <label for="employer_tax_display"><i class="fas fa-building text-warning mr-1"></i>{{ __('field_employer_tax') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" id="employer_tax_display" value="{{ round($employer_tax_amount, 0) }}" readonly data_id="add-{{ $row->id }}" style="color: #d48806;">
                                    <div class="field-desc">{{ __('Tax contributions borne by the institution. Not deducted from staff salary.') }}</div>
                                </div>

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

                                <div class="calc-field-group highlight-cost" id="totalCostGroup" style="{{ $employer_tax_amount > 0 ? '' : 'display:none;' }}">
                                    <label for="total_cost_display"><i class="fas fa-university mr-1"></i>{{ __('field_total_cost') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" id="total_cost_display" value="{{ round($total_cost, 0) }}" readonly data_id="add-{{ $row->id }}" style="color: #5a5c69; font-size: 18px;">
                                    <div class="field-desc">{{ __('Total institutional cost: Net Salary + Employee Tax + Employer Tax.') }}</div>
                                </div>

                                {{-- Submit --}}
                                <div class="mt-3">
                                    <button type="button" id="btnShowConfirmModal" {{ empty($calc['problems']) ? '' : 'disabled' }} class="btn btn-success btn-block" style="padding: 10px; font-size: 15px; font-weight: 600;">
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
                                <div class="sum-label">{{ __('field_total_earning') }}</div>
                                <div class="sum-value text-info-light">{{ format_amount($total_earning) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-plus"></i></div>
                        <div class="col text-center" style="border-right: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_total_allowance') }}</div>
                                <div class="sum-value text-success-light" id="summaryAllowance">{{ format_amount($total_allowance) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-minus"></i></div>
                        <div class="col text-center" style="border-right: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_total_deduction') }}</div>
                                <div class="sum-value text-danger-light" id="summaryDeduction">{{ format_amount($total_deduction) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-minus"></i></div>
                        <div class="col text-center" style="border-right: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_employee_tax') }}</div>
                                <div class="sum-value text-danger-light" id="summaryTax">{{ format_amount($tax_amount) }}</div>
                            </div>
                        </div>
                        <div class="col-auto summary-arrow"><i class="fas fa-equals"></i></div>
                        <div class="col text-center">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_net_salary') }}</div>
                                <div class="sum-value text-success-light" style="font-size: 22px;" id="summaryNet">{{ format_amount($net_salary) }}</div>
                            </div>
                        </div>
                        @if($employer_tax_amount > 0)
                        <div class="col-auto summary-arrow" style="border-left: 1px solid rgba(255,255,255,0.1); padding-left: 15px;">
                            <i class="fas fa-building" style="opacity: 0.5;"></i>
                        </div>
                        <div class="col text-center">
                            <div class="summary-item">
                                <div class="sum-label">{{ __('field_total_cost') }}</div>
                                <div class="sum-value text-warning-light" id="summaryCost">{{ format_amount($total_cost) }}</div>
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
            html += '<option value="{{ $type->title }}">{{ $type->title }}{{ $type->is_taxable ? ' · '.__('taxed') : '' }}</option>';
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
            html += '</select><input type="hidden" name="deduction_loans[]" value=""><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div></div>';
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
    // The figures come from the server (PayrollController::preview), which
    // uses the same calculator as saving. The page used to carry its own copy
    // of the tax rules in JavaScript; two engines could disagree, and the
    // server trusted whatever this one posted.
    var payrollPreviewTimer = null;
    var payrollPreviewRequest = null;

    function salaryCalculator(type, id) {
        clearTimeout(payrollPreviewTimer);
        payrollPreviewTimer = setTimeout(function () { payrollPreview(type, id); }, 250);
    }

    function payrollPreview(type, id) {
        var $form = $('form.needs-validation');
        var data = $form.find('input[name="_token"], input[name="user_id"], input[name="salary_month"], select[name="allowance_titles[]"], input[name="allowances[]"], select[name="deduction_titles[]"], input[name="deduction_titles[]"], input[name="deduction_loans[]"], input[name="deductions[]"]').serialize();

        if (payrollPreviewRequest) { payrollPreviewRequest.abort(); }
        $('#btnShowConfirmModal').prop('disabled', true);

        payrollPreviewRequest = $.ajax({
            url: '{{ route($route.'.preview') }}',
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (c) {
            var cs = ' {!! $setting->currency_symbol !!}';
            var fmt = function (v) { return Math.round(parseFloat(v) || 0).toLocaleString(); };
            var set = function (name, v) { $("input[name='" + name + "'][data_id='" + type + "-" + id + "']").val(Math.round(parseFloat(v) || 0)); };
            var esc = function (t) { return $('<div>').text(t).html(); };

            set('total_earning', c.total_earning);
            set('total_allowance', c.total_allowance);
            set('total_deduction', c.total_deduction);
            set('gross_salary', c.gross_salary);
            set('tax', c.tax);
            set('employer_tax', c.employer_tax);
            set('net_salary', c.net_salary);
            set('total_cost', c.total_cost);
            $('#employer_tax_display').val(Math.round(c.employer_tax));
            $('#total_cost_display').val(Math.round(c.total_cost));
            $('#employerTaxGroup, #totalCostGroup').toggle(c.employer_tax > 0);
            $('#effectiveRate').text(c.effective_rate + '%');
            $('#taxBaseNote').text(@json(__('Taxed on :amount: the :basic plus taxable allowances (:taxable).'))
                .replace(':amount', fmt(c.taxable_salary))
                .replace(':basic', @json($is_hourly ? __('hours worked') : __('basic salary')))
                .replace(':taxable', fmt(c.taxable_allowance)));

            var html = '';
            $.each(c.tax_lines, function (_, l) {
                html += '<div class="tax-item"><div class="tax-icon"><i class="fas fa-receipt text-secondary"></i></div>';
                html += '<div class="tax-info"><div class="tax-name">' + esc(l.label) + '</div>';
                if (l.employer > 0) {
                    html += '<div class="tax-detail">' + @json(__('Staff')) + ' ' + fmt(l.employee) + ' · ' + @json(__('Employer')) + ' ' + fmt(l.employer) + '</div>';
                }
                html += '</div><div class="tax-amount text-danger">' + fmt(l.employee) + cs + '</div></div>';
            });
            $('#taxBreakdownPanel').html(html || '<p class="text-muted text-center mb-0" style="font-size:12px"><i class="fas fa-info-circle"></i> ' + esc(@json(__('No tax applies to this pay.'))) + '</p>');

            var problems = '';
            $.each(c.problems, function (_, p) { problems += '<li>' + esc(p) + '</li>'; });
            $('#payrollProblemList').html(problems);
            $('#payrollProblems').toggle(c.problems.length > 0);
            $('#btnShowConfirmModal').prop('disabled', c.problems.length > 0);

            $('#summaryAllowance').text(fmt(c.total_allowance));
            $('#summaryDeduction').text(fmt(c.total_deduction));
            $('#summaryTax').text(fmt(c.tax));
            $('#summaryNet').text(fmt(c.net_salary));
            $('#summaryCost').text(fmt(c.total_cost));
        }).fail(function (xhr, status) {
            if (status === 'abort') { return; }
            $('#payrollProblemList').html('<li>' + $('<div>').text(@json(__('The figures could not be worked out. Check the connection and press Recalculate.'))).html() + '</li>');
            $('#payrollProblems').show();
        });
    }

    // Choosing a different allowance can change what is taxed.
    $(document).on('change', 'select[name="allowance_titles[]"], select[name="deduction_titles[]"]', function () {
        salaryCalculator('add', {{ $row->id }});
    });

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
