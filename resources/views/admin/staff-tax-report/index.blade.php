@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<style>
    .tax-report-table th, .tax-report-table td {
        font-size: 0.8rem;
        padding: 0.35rem 0.5rem;
        white-space: nowrap;
        vertical-align: middle;
        color: #333 !important;
    }
    .tax-report-table thead th {
        color: #333 !important;
    }
    .tax-report-table .sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #fff;
    }
    .tax-report-table thead th.sticky-col {
        z-index: 3;
    }
    .tax-report-table .sticky-col-2 {
        position: sticky;
        left: 40px;
        z-index: 2;
        background: #fff;
    }
    .tax-report-table thead th.sticky-col-2 {
        z-index: 3;
    }
    .tax-report-table .sticky-col-3 {
        position: sticky;
        left: 200px;
        z-index: 2;
        background: #fff;
    }
    .tax-report-table thead th.sticky-col-3 {
        z-index: 3;
    }
    .tax-group-header {
        background: #e8f4fd !important;
        border-left: 2px solid #2196F3 !important;
        color: #333 !important;
    }
    .standalone-header {
        background: #fef8e7 !important;
        border-left: 2px solid #FF9800 !important;
        color: #333 !important;
    }
    .summary-header {
        background: #e8f5e9 !important;
        color: #333 !important;
    }
    .totals-row {
        font-weight: bold;
        background: #f5f5f5 !important;
    }
    .totals-row td {
        border-top: 2px solid #333 !important;
        color: #333 !important;
    }
    .exempt-badge {
        font-size: 0.65rem;
        padding: 0.15rem 0.35rem;
    }
    .employer-amount {
        color: #888 !important;
        font-size: 0.72rem;
    }
    .kpi-card {
        border-radius: 8px;
        transition: transform 0.2s;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
    }
    .kpi-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff !important;
    }
    .kpi-label {
        font-size: 0.8rem;
        opacity: 0.9;
        color: #fff !important;
    }
    .effective-rate-bar {
        height: 6px;
        border-radius: 3px;
        background: #e9ecef;
    }
    .effective-rate-fill {
        height: 100%;
        border-radius: 3px;
        background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
    }
    .chart-container {
        position: relative;
        height: 250px;
    }
    /* Ensure all card text is dark */
    .card h5, .card h6, .card label, .card .form-label,
    .card p, .card span, .card small, .card div, .card a:not(.btn) {
        color: #333;
    }
    /* Override for specific colored text utilities */
    .text-danger { color: #dc3545 !important; }
    .text-success { color: #28a745 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-muted { color: #6c757d !important; }
    .text-primary { color: #04a9f5 !important; }
    .text-right { color: #333; }
    .font-weight-bold { color: #333; }
    /* Ensure KPI cards keep white text */
    .kpi-card .kpi-value, .kpi-card .kpi-label { color: #fff !important; }
    /* Ensure badges are readable */
    .badge { color: #fff !important; }
    .badge-light { color: #333 !important; }
    .badge-warning { color: #333 !important; }
    /* Table header text */
    .table thead th, .table-sm thead th {
        color: #333 !important;
    }
    /* Form controls */
    .form-control, .form-control-sm, select.form-control {
        color: #333 !important;
    }
    /* Card header override */
    .card .card-header h5, .card .card-header h6 {
        color: #000 !important;
    }
    /* Effective rate text */
    .effective-rate-bar + div, .text-truncate {
        color: #333 !important;
    }
</style>

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-file-invoice-dollar mr-2"></i>{{ $title }}</h5>
                        <div>
                            <a href="{{ route('admin.staff-tax-report.excel', request()->query()) }}" class="btn btn-sm btn-success">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </a>
                            <a href="{{ route('admin.staff-tax-report.pdf', request()->query()) }}" class="btn btn-sm btn-danger" target="_blank">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </a>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-block">
                        <!-- Report Description -->
                        <div class="alert alert-light border mb-3" style="color: #555 !important;">
                            <i class="fas fa-info-circle text-primary mr-1"></i>
                            <strong>About this report:</strong> This report calculates the tax liability for every active staff member based on their <strong>basic salary</strong> and the currently configured tax groups and standalone taxes. 
                            It shows how each tax is distributed across staff, including both <strong>employee deductions</strong> (withheld from salary) and <strong>employer contributions</strong> (paid by the institution on top of salary). 
                            Taxes marked <span class="badge badge-light" style="color:#333!important;">Progressive</span> are applied in brackets (only the portion of salary within each range is taxed at that rate). 
                            Taxes marked <span class="badge badge-info">Shared</span> have both employee and employer portions.
                            The <strong>Effective Tax Rate</strong> shows what percentage of a staff member's salary goes to employee taxes overall.
                        </div>

                        <!-- Filter Form -->
                        <form method="get" action="{{ route('admin.staff-tax-report.index') }}" class="mb-3">
                            <div class="row gx-2 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">Department</label>
                                    <select class="form-control form-control-sm" name="department_id">
                                        <option value="">All</option>
                                        @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ $selected_department == $dept->id ? 'selected' : '' }}>{{ $dept->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Designation</label>
                                    <select class="form-control form-control-sm" name="designation_id">
                                        <option value="">All</option>
                                        @foreach($designations as $des)
                                        <option value="{{ $des->id }}" {{ $selected_designation == $des->id ? 'selected' : '' }}>{{ $des->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Min Salary</label>
                                    <input type="number" class="form-control form-control-sm" name="salary_min" value="{{ $selected_salary_min }}" placeholder="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Max Salary</label>
                                    <input type="number" class="form-control form-control-sm" name="salary_max" value="{{ $selected_salary_max }}" placeholder="No limit">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Effective Date</label>
                                    <input type="date" class="form-control form-control-sm" name="effective_date" value="{{ $effective_date }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-search"></i> Filter</button>
                                    <a href="{{ route('admin.staff-tax-report.index') }}" class="btn btn-danger btn-sm"><i class="fas fa-redo"></i></a>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1"><i class="fas fa-filter mr-1"></i> Use the filters to narrow results by department, designation, or salary range. The <strong>Effective Date</strong> shows only tax rules that are active on that date (defaults to today). Click <i class="fas fa-redo text-danger"></i> to reset all filters.</small>
                        </form>

                        <!-- KPI Summary Cards -->
                        <p class="text-muted mb-2" style="font-size: 0.82rem;"><i class="fas fa-tachometer-alt mr-1"></i> <strong>Summary:</strong> Key figures for the {{ $staff_count }} staff member(s) matching your filters. All amounts are monthly estimates based on current salary records.</p>
                        <div class="row mb-3">
                            <div class="col-xl-2 col-md-4 col-6 mb-2">
                                <div class="card kpi-card bg-primary text-white mb-0">
                                    <div class="card-body py-2 px-3">
                                        <div class="kpi-label">Staff Count</div>
                                        <div class="kpi-value">{{ $staff_count }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-2">
                                <div class="card kpi-card bg-info text-white mb-0">
                                    <div class="card-body py-2 px-3">
                                        <div class="kpi-label">Total Gross Salary</div>
                                        <div class="kpi-value">{{ number_format($grand_totals['basic_salary'], 0) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-2">
                                <div class="card kpi-card bg-danger text-white mb-0">
                                    <div class="card-body py-2 px-3">
                                        <div class="kpi-label">Employee Tax</div>
                                        <div class="kpi-value">{{ number_format($grand_totals['employee_tax_total'], 0) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-2">
                                <div class="card kpi-card bg-warning text-white mb-0">
                                    <div class="card-body py-2 px-3">
                                        <div class="kpi-label">Employer Tax</div>
                                        <div class="kpi-value">{{ number_format($grand_totals['employer_tax_total'], 0) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-2">
                                <div class="card kpi-card bg-success text-white mb-0">
                                    <div class="card-body py-2 px-3">
                                        <div class="kpi-label">Total Net Pay</div>
                                        <div class="kpi-value">{{ number_format($grand_totals['net_salary'], 0) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-2">
                                <div class="card kpi-card bg-dark text-white mb-0">
                                    <div class="card-body py-2 px-3">
                                        <div class="kpi-label">Avg Effective Rate</div>
                                        <div class="kpi-value">{{ $avg_effective_rate }}%</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <p class="text-muted mb-2" style="font-size: 0.82rem;"><i class="fas fa-chart-pie mr-1"></i> <strong>Visual Analysis:</strong> These charts provide a quick visual overview of how taxes are distributed, how staff salaries are spread across bands, and the split between employee and employer contributions.</p>
                        <div class="row mb-3">
                            <!-- Tax Distribution by Type (Pie) -->
                            <div class="col-md-4">
                                <div class="card mb-0">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Tax Distribution by Type</h6>
                                        <small class="text-muted">Combined employee + employer tax by category</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="chart-container">
                                            <canvas id="taxPieChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Salary Band Distribution (Bar) -->
                            <div class="col-md-4">
                                <div class="card mb-0">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Staff by Salary Band</h6>
                                        <small class="text-muted">Number of staff and total tax per salary range</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="chart-container">
                                            <canvas id="salaryBandChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Employee vs Employer (Bar) -->
                            <div class="col-md-4">
                                <div class="card mb-0">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Employee vs Employer Tax Burden</h6>
                                        <small class="text-muted">Who pays what for each tax type</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="chart-container">
                                            <canvas id="burdenChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tax Effective Rate Summary -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="card mb-0">
                                    <div class="card-header py-2 d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-0">Effective Tax Rate by Staff</h6>
                                            <small class="text-muted">The effective rate is the total employee tax as a percentage of basic salary. <span style="color:#28a745;">Green</span> = under 10%, <span style="color:#ffc107;">Yellow</span> = 10-20%, <span style="color:#dc3545;">Red</span> = over 20%.</small>
                                        </div>
                                        <small class="text-muted">Min: {{ $min_effective_rate }}% | Avg: {{ $avg_effective_rate }}% | Max: {{ $max_effective_rate }}%</small>
                                    </div>
                                    <div class="card-body py-2">
                                        @foreach($staff_rows as $sr)
                                        @php
                                            $rate = $sr['basic_salary'] > 0 ? round(($sr['employee_tax_total'] / $sr['basic_salary']) * 100, 2) : 0;
                                            $barWidth = $max_effective_rate > 0 ? ($rate / $max_effective_rate * 100) : 0;
                                            $barColor = $rate < 10 ? '#28a745' : ($rate < 20 ? '#ffc107' : '#dc3545');
                                        @endphp
                                        <div class="d-flex align-items-center mb-1" style="font-size: 0.78rem;">
                                            <div style="width: 180px; overflow: hidden; text-overflow: ellipsis;" class="text-truncate">{{ $sr['user']->name }}</div>
                                            <div class="flex-grow-1 mx-2">
                                                <div class="effective-rate-bar">
                                                    <div class="effective-rate-fill" style="width: {{ $barWidth }}%; background: {{ $barColor }};"></div>
                                                </div>
                                            </div>
                                            <div style="width: 50px; text-align: right;">{{ $rate }}%</div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Distribution Table -->
                        <p class="text-muted mb-2" style="font-size: 0.82rem;">
                            <i class="fas fa-table mr-1"></i> <strong>Detailed Distribution Table:</strong> Each row shows one staff member. 
                            Tax columns show the <strong>employee deduction</strong> in the main figure; if an employer contribution applies, it appears below in grey as <span class="employer-amount">+amount</span>. 
                            <span class="badge badge-warning exempt-badge" style="color:#333!important;">E</span> next to a name means they have one or more tax exemptions. 
                            <span class="badge badge-success exempt-badge">Exempt</span> in a tax cell means the staff is fully exempt from that specific tax. 
                            The <strong>Eff. Rate</strong> column shows each person's overall employee tax burden as a percentage.
                        </p>
                        <div class="table-responsive" style="max-height: 600px; overflow: auto;">
                            <table class="table table-bordered table-striped tax-report-table mb-0">
                                <thead class="thead-light" style="position: sticky; top: 0; z-index: 4;">
                                    <tr>
                                        <th class="sticky-col" style="min-width: 40px;">#</th>
                                        <th class="sticky-col-2" style="min-width: 160px;">Staff Name</th>
                                        <th class="sticky-col-3" style="min-width: 100px;">Basic Salary</th>
                                        
                                        {{-- Group tax columns --}}
                                        @foreach($tax_groups as $group)
                                        <th class="text-center tax-group-header" title="{{ $group->description ?? $group->title }}">
                                            {{ $group->code ?? \Illuminate\Support\Str::limit($group->title, 15) }}
                                            @if($group->is_progressive)
                                            <br><span class="badge badge-light exempt-badge">Progressive</span>
                                            @endif
                                        </th>
                                        @endforeach

                                        {{-- Standalone tax columns --}}
                                        @foreach($standalone_taxes as $tax)
                                        <th class="text-center standalone-header" title="{{ $tax->title }}{{ $tax->is_dependent ? ' (Dependent - calculated from ' . ($tax->dependency_label ?? 'source tax') . ')' : '' }}">
                                            {{ \Illuminate\Support\Str::limit($tax->title, 15) }}
                                            @if($tax->is_dependent)
                                            <br><span class="badge badge-warning exempt-badge" title="Calculated from {{ $tax->dependency_label ?? 'source tax' }}"><i class="fas fa-link"></i> Dep.</span>
                                            @endif
                                            @if($tax->is_shared)
                                            <br><span class="badge badge-info exempt-badge">Shared</span>
                                            @endif
                                        </th>
                                        @endforeach

                                        {{-- Summary Columns --}}
                                        <th class="text-center summary-header">Employee Tax</th>
                                        <th class="text-center summary-header">Employer Tax</th>
                                        <th class="text-center summary-header">Net Salary</th>
                                        <th class="text-center summary-header">Total Cost</th>
                                        <th class="text-center summary-header">Eff. Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($staff_rows as $index => $sr)
                                    <tr>
                                        <td class="sticky-col">{{ $index + 1 }}</td>
                                        <td class="sticky-col-2">
                                            <strong>{{ $sr['user']->name }}</strong>
                                            @if($sr['exemption_count'] > 0)
                                            <span class="badge badge-warning exempt-badge" title="Has {{ $sr['exemption_count'] }} exemption(s)">E</span>
                                            @endif
                                        </td>
                                        <td class="sticky-col-3 text-right">{{ number_format($sr['basic_salary'], 0) }}</td>
                                        
                                        {{-- Group taxes --}}
                                        @foreach($tax_groups as $group)
                                        <td class="text-right">
                                            @if(isset($sr['groups'][$group->id]))
                                                @php $g = $sr['groups'][$group->id]; @endphp
                                                {{ number_format($g['employee'], 0) }}
                                                @if($g['employer'] > 0)
                                                <br><span class="employer-amount" title="Employer pays">+{{ number_format($g['employer'], 0) }}</span>
                                                @endif
                                            @else
                                                0
                                            @endif
                                        </td>
                                        @endforeach

                                        {{-- Standalone taxes --}}
                                        @foreach($standalone_taxes as $tax)
                                        <td class="text-right">
                                            @if(isset($sr['standalone'][$tax->id]))
                                                @php $s = $sr['standalone'][$tax->id]; @endphp
                                                @if($s['is_exempt'])
                                                    <span class="badge badge-success exempt-badge">Exempt</span>
                                                @else
                                                    {{ number_format($s['employee'], 0) }}
                                                    @if($s['employer'] > 0)
                                                    <br><span class="employer-amount" title="Employer pays">+{{ number_format($s['employer'], 0) }}</span>
                                                    @endif
                                                @endif
                                            @else
                                                0
                                            @endif
                                        </td>
                                        @endforeach

                                        {{-- Summaries --}}
                                        <td class="text-right text-danger font-weight-bold">{{ number_format($sr['employee_tax_total'], 0) }}</td>
                                        <td class="text-right text-warning">{{ number_format($sr['employer_tax_total'], 0) }}</td>
                                        <td class="text-right text-success font-weight-bold">{{ number_format($sr['net_salary'], 0) }}</td>
                                        <td class="text-right">{{ number_format($sr['total_cost'], 0) }}</td>
                                        <td class="text-center">
                                            @php $rate = $sr['basic_salary'] > 0 ? round(($sr['employee_tax_total'] / $sr['basic_salary']) * 100, 1) : 0; @endphp
                                            <span class="badge {{ $rate < 10 ? 'badge-success' : ($rate < 20 ? 'badge-warning' : 'badge-danger') }}">
                                                {{ $rate }}%
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td class="sticky-col"></td>
                                        <td class="sticky-col-2">TOTALS ({{ $staff_count }} staff)</td>
                                        <td class="sticky-col-3 text-right">{{ number_format($grand_totals['basic_salary'], 0) }}</td>
                                        
                                        @foreach($tax_groups as $group)
                                        <td class="text-right">
                                            {{ number_format($grand_totals['groups'][$group->id]['employee'], 0) }}
                                            @if($grand_totals['groups'][$group->id]['employer'] > 0)
                                            <br><span class="employer-amount">+{{ number_format($grand_totals['groups'][$group->id]['employer'], 0) }}</span>
                                            @endif
                                        </td>
                                        @endforeach
                                        
                                        @foreach($standalone_taxes as $tax)
                                        <td class="text-right">
                                            {{ number_format($grand_totals['standalone'][$tax->id]['employee'], 0) }}
                                            @if($grand_totals['standalone'][$tax->id]['employer'] > 0)
                                            <br><span class="employer-amount">+{{ number_format($grand_totals['standalone'][$tax->id]['employer'], 0) }}</span>
                                            @endif
                                        </td>
                                        @endforeach
                                        
                                        <td class="text-right text-danger">{{ number_format($grand_totals['employee_tax_total'], 0) }}</td>
                                        <td class="text-right text-warning">{{ number_format($grand_totals['employer_tax_total'], 0) }}</td>
                                        <td class="text-right text-success">{{ number_format($grand_totals['net_salary'], 0) }}</td>
                                        <td class="text-right">{{ number_format($grand_totals['total_cost'], 0) }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-dark">{{ $avg_effective_rate }}%</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Salary Band Summary Table -->
                        <p class="text-muted mt-4 mb-2" style="font-size: 0.82rem;">
                            <i class="fas fa-layer-group mr-1"></i> <strong>Summary Tables:</strong> 
                            The <strong>Salary Band Summary</strong> groups staff into salary ranges so you can see where most of your workforce falls and how the tax burden varies by pay level. 
                            The <strong>Tax Breakdown Summary</strong> totals every tax type across all staff, showing the combined cost to both employee and institution.
                        </p>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="card mb-0">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Salary Band Summary</h6>
                                        <small class="text-muted">Staff grouped by salary range with average effective rate</small>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-bordered table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Salary Range</th>
                                                    <th class="text-center">Staff</th>
                                                    <th class="text-right">Total Salary</th>
                                                    <th class="text-right">Total Tax</th>
                                                    <th class="text-center">Avg Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($salary_bands as $label => $band)
                                                <tr>
                                                    <td>{{ $label }}</td>
                                                    <td class="text-center">{{ $band['count'] }}</td>
                                                    <td class="text-right">{{ number_format($band['total_salary'], 0) }}</td>
                                                    <td class="text-right">{{ number_format($band['total_tax'], 0) }}</td>
                                                    <td class="text-center">
                                                        @if($band['total_salary'] > 0)
                                                            {{ round(($band['total_tax'] / $band['total_salary']) * 100, 1) }}%
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-0">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Tax Breakdown Summary</h6>
                                        <small class="text-muted">Total collection per tax type (employee + employer)</small>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-bordered table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Tax Name</th>
                                                    <th class="text-center">Type</th>
                                                    <th class="text-right">Employee Total</th>
                                                    <th class="text-right">Employer Total</th>
                                                    <th class="text-right">Combined</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($tax_groups as $group)
                                                <tr>
                                                    <td><i class="fas fa-layer-group text-primary mr-1"></i> {{ $group->title }}</td>
                                                    <td class="text-center"><span class="badge badge-primary">Group</span></td>
                                                    <td class="text-right">{{ number_format($grand_totals['groups'][$group->id]['employee'], 0) }}</td>
                                                    <td class="text-right">{{ number_format($grand_totals['groups'][$group->id]['employer'], 0) }}</td>
                                                    <td class="text-right font-weight-bold">{{ number_format($grand_totals['groups'][$group->id]['employee'] + $grand_totals['groups'][$group->id]['employer'], 0) }}</td>
                                                </tr>
                                                @endforeach
                                                @foreach($standalone_taxes as $tax)
                                                <tr>
                                                    <td>
                                                        @if($tax->is_dependent)
                                                        <i class="fas fa-link text-warning mr-1"></i> {{ $tax->title }}
                                                        <small class="text-muted d-block">From: {{ $tax->dependency_label ?? '-' }}</small>
                                                        @else
                                                        <i class="fas fa-receipt text-warning mr-1"></i> {{ $tax->title }}
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if($tax->is_dependent)
                                                        <span class="badge badge-warning">Dependent</span>
                                                        @else
                                                        <span class="badge badge-warning">Standalone</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-right">{{ number_format($grand_totals['standalone'][$tax->id]['employee'], 0) }}</td>
                                                    <td class="text-right">{{ number_format($grand_totals['standalone'][$tax->id]['employer'], 0) }}</td>
                                                    <td class="text-right font-weight-bold">{{ number_format($grand_totals['standalone'][$tax->id]['employee'] + $grand_totals['standalone'][$tax->id]['employer'], 0) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr class="font-weight-bold bg-light">
                                                    <td colspan="2">Grand Total</td>
                                                    <td class="text-right text-danger">{{ number_format($grand_totals['employee_tax_total'], 0) }}</td>
                                                    <td class="text-right text-warning">{{ number_format($grand_totals['employer_tax_total'], 0) }}</td>
                                                    <td class="text-right">{{ number_format($grand_totals['employee_tax_total'] + $grand_totals['employer_tax_total'], 0) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Historical Payroll Trend (only if data exists) -->
                        @php $hasHistorical = collect($historical_data)->sum('count') > 0; @endphp
                        @if($hasHistorical)
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card mb-0">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Historical Payroll Tax Trend (Last 6 Months)</h6>
                                        <small class="text-muted">Actual payroll tax data from processed payrolls — shows month-over-month changes in tax deductions and employer contributions</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div style="height: 250px;">
                                            <canvas id="historicalChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Footer Note -->
                        <div class="alert alert-secondary mt-4 mb-0" style="color: #555 !important; font-size: 0.82rem;">
                            <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                            <strong>Important notes:</strong>
                            <ul class="mb-0 pl-3 mt-1">
                                <li><strong>Estimates, not actuals:</strong> The figures above are calculated from each staff member's current <em>basic salary</em> and the active tax configuration. They represent what <em>would</em> be deducted if payroll were run today. For actual deducted amounts, refer to processed payroll records.</li>
                                <li><strong>Allowances &amp; deductions not included:</strong> This report does not factor in allowances (transport, housing, etc.) or other deductions — only the statutory tax on basic salary.</li>
                                <li><strong>Exemptions:</strong> Staff with tax exemptions (custom rates or full exemption) are reflected in the calculations. Look for the <span class="badge badge-warning" style="color:#333!important;">E</span> badge in the table.</li>
                                <li><strong>Historical Trend:</strong> The trend chart only appears when at least one payroll has been processed; it uses actual payroll data, not estimates.</li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
'use strict';
document.addEventListener('DOMContentLoaded', function() {
    // Color palette
    var colors = [
        '#2196F3', '#FF9800', '#4CAF50', '#E91E63', '#9C27B0',
        '#00BCD4', '#795548', '#607D8B', '#FF5722', '#3F51B5'
    ];

    // 1. Tax Distribution Pie Chart
    var pieLabels = [];
    var pieData = [];
    var pieColors = [];
    var colorIdx = 0;

    @foreach($tax_groups as $group)
        pieLabels.push('{{ addslashes($group->title) }}');
        pieData.push({{ $grand_totals['groups'][$group->id]['employee'] + $grand_totals['groups'][$group->id]['employer'] }});
        pieColors.push(colors[colorIdx % colors.length]);
        colorIdx++;
    @endforeach

    @foreach($standalone_taxes as $tax)
        pieLabels.push('{{ addslashes($tax->title) }}');
        pieData.push({{ $grand_totals['standalone'][$tax->id]['employee'] + $grand_totals['standalone'][$tax->id]['employer'] }});
        pieColors.push(colors[colorIdx % colors.length]);
        colorIdx++;
    @endforeach

    if (document.getElementById('taxPieChart')) {
        new Chart(document.getElementById('taxPieChart'), {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieData,
                    backgroundColor: pieColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 10 }, padding: 8 }
                    }
                }
            }
        });
    }

    // 2. Salary Band Bar Chart
    var bandLabels = [];
    var bandCounts = [];
    var bandTaxes = [];

    @foreach($salary_bands as $label => $band)
        bandLabels.push('{{ $label }}');
        bandCounts.push({{ $band['count'] }});
        bandTaxes.push({{ $band['total_tax'] }});
    @endforeach

    if (document.getElementById('salaryBandChart')) {
        new Chart(document.getElementById('salaryBandChart'), {
            type: 'bar',
            data: {
                labels: bandLabels,
                datasets: [
                    {
                        label: 'Staff Count',
                        data: bandCounts,
                        backgroundColor: '#2196F3',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Total Tax',
                        data: bandTaxes,
                        backgroundColor: '#FF9800',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { position: 'left', beginAtZero: true, title: { display: true, text: 'Count' } },
                    y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Tax Amount' } }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10 } } }
                }
            }
        });
    }

    // 3. Employee vs Employer Burden
    var burdenLabels = [];
    var employeeData = [];
    var employerData = [];

    @foreach($tax_groups as $group)
        burdenLabels.push('{{ addslashes($group->code ?? \Illuminate\Support\Str::limit($group->title, 12)) }}');
        employeeData.push({{ $grand_totals['groups'][$group->id]['employee'] }});
        employerData.push({{ $grand_totals['groups'][$group->id]['employer'] }});
    @endforeach

    @foreach($standalone_taxes as $tax)
        burdenLabels.push('{{ addslashes(\Illuminate\Support\Str::limit($tax->title, 12)) }}');
        employeeData.push({{ $grand_totals['standalone'][$tax->id]['employee'] }});
        employerData.push({{ $grand_totals['standalone'][$tax->id]['employer'] }});
    @endforeach

    if (document.getElementById('burdenChart')) {
        new Chart(document.getElementById('burdenChart'), {
            type: 'bar',
            data: {
                labels: burdenLabels,
                datasets: [
                    { label: 'Employee', data: employeeData, backgroundColor: '#dc3545' },
                    { label: 'Employer', data: employerData, backgroundColor: '#ffc107' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, ticks: { font: { size: 9 } } },
                    y: { stacked: true, beginAtZero: true }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10 } } }
                }
            }
        });
    }

    // 4. Historical Trend (if data exists)
    @if($hasHistorical ?? false)
    var histLabels = [];
    var histTax = [];
    var histEmployerTax = [];
    var histNet = [];

    @foreach($historical_data as $h)
        histLabels.push('{{ $h['label'] }}');
        histTax.push({{ $h['total_tax'] }});
        histEmployerTax.push({{ $h['employer_tax'] }});
        histNet.push({{ $h['total_net'] }});
    @endforeach

    if (document.getElementById('historicalChart')) {
        new Chart(document.getElementById('historicalChart'), {
            type: 'line',
            data: {
                labels: histLabels,
                datasets: [
                    { label: 'Employee Tax', data: histTax, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', fill: true, tension: 0.3 },
                    { label: 'Employer Tax', data: histEmployerTax, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.1)', fill: true, tension: 0.3 },
                    { label: 'Net Salary', data: histNet, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', fill: true, tension: 0.3 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } }
            }
        });
    }
    @endif
});
</script>
@endsection
