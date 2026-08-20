<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Staff Tax Distribution Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            color: #2c3e50;
            line-height: 1.4;
        }

        /* Page setup */
        @page {
            size: landscape;
            margin: 15mm 10mm 20mm 10mm;
        }

        .page-break { page-break-after: always; }

        /* ─── Header ─── */
        .report-header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            border-bottom: 3px solid #4e73df;
            padding-bottom: 8px;
        }
        .header-logo {
            display: table-cell;
            width: 60px;
            vertical-align: middle;
        }
        .header-logo img {
            width: 55px;
            height: auto;
        }
        .header-center {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }
        .header-center h1 {
            font-size: 14pt;
            color: #1a1a2e;
            margin-bottom: 2px;
        }
        .header-center p {
            font-size: 7.5pt;
            color: #555;
        }
        .header-right {
            display: table-cell;
            width: 180px;
            vertical-align: middle;
            text-align: right;
            font-size: 7pt;
            color: #666;
        }
        .header-right .report-title {
            font-size: 9pt;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 2px;
        }

        /* ─── Timestamp Banner ─── */
        .timestamp-banner {
            background: #f0f3ff;
            border: 1px solid #c5cfe8;
            border-radius: 3px;
            padding: 5px 10px;
            margin-bottom: 10px;
            font-size: 7pt;
            color: #444;
        }
        .timestamp-banner strong { color: #2c3e50; }

        /* ─── Filter Summary ─── */
        .filter-summary {
            background: #fffbe6;
            border: 1px solid #ffe58f;
            border-radius: 3px;
            padding: 5px 10px;
            margin-bottom: 10px;
            font-size: 7pt;
        }
        .filter-summary strong { color: #d48806; }

        /* ─── KPI Cards ─── */
        .kpi-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .kpi-card {
            display: table-cell;
            width: 16.66%;
            padding: 0 3px;
            vertical-align: top;
        }
        .kpi-card-inner {
            border-radius: 4px;
            padding: 6px 8px;
            color: #fff;
            text-align: center;
        }
        .kpi-label {
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            opacity: 0.9;
        }
        .kpi-value {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 1px;
        }
        .bg-primary { background: #4e73df; }
        .bg-info { background: #36b9cc; }
        .bg-danger { background: #e74a3b; }
        .bg-warning-dark { background: #f6a821; }
        .bg-success { background: #1cc88a; }
        .bg-secondary { background: #858796; }

        /* ─── Section Headers ─── */
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #4e73df;
            border-bottom: 2px solid #4e73df;
            padding-bottom: 3px;
            margin: 12px 0 6px 0;
        }
        .section-desc {
            font-size: 7pt;
            color: #666;
            margin-bottom: 6px;
        }

        /* ─── Tables ─── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 7.5pt;
        }
        table.data-table th {
            background: #4e73df;
            color: #fff;
            padding: 4px 5px;
            text-align: center;
            font-weight: bold;
            font-size: 7pt;
            border: 1px solid #3b5ec2;
            word-wrap: break-word;
            overflow: hidden;
        }
        table.data-table th.group-header {
            background: #3b5ec2;
        }
        table.data-table th.standalone-header {
            background: #d48806;
            border-color: #b8720a;
        }
        table.data-table th.summary-header {
            background: #2c3e50;
            border-color: #1a252f;
        }
        table.data-table td {
            padding: 3px 5px;
            border: 1px solid #dee2e6;
            color: #333;
            word-wrap: break-word;
            overflow: hidden;
        }
        table.data-table tbody tr:nth-child(even) {
            background: #f8f9fc;
        }
        table.data-table tbody tr:hover {
            background: #eef1fb;
        }
        table.data-table tfoot td {
            background: #e8ecf6;
            font-weight: bold;
            border-top: 2px solid #4e73df;
        }

        /* ─── Compact variant for the main distribution table ─── */
        table.data-table-compact {
            table-layout: fixed;
            font-size: 6.5pt;
        }
        table.data-table-compact th {
            padding: 3px 2px;
            font-size: 6pt;
            line-height: 1.2;
        }
        table.data-table-compact td {
            padding: 2px 3px;
            font-size: 6.5pt;
            line-height: 1.2;
        }
        table.data-table-compact .employer-amount {
            font-size: 5.5pt;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-danger { color: #e74a3b; }
        .text-warning { color: #d48806; }
        .text-success { color: #1cc88a; }
        .text-muted { color: #888; }
        .font-bold { font-weight: bold; }

        .employer-amount {
            font-size: 6.5pt;
            color: #888;
            font-style: italic;
        }

        /* ─── Badges ─── */
        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 6.5pt;
            font-weight: bold;
            color: #fff;
        }
        .badge-success { background: #1cc88a; }
        .badge-warning { background: #f6c23e; color: #333; }
        .badge-danger { background: #e74a3b; }
        .badge-primary { background: #4e73df; }
        .badge-dark { background: #5a5c69; }
        .badge-info { background: #36b9cc; }

        /* ─── Tax Bracket Detail Box ─── */
        .bracket-detail {
            background: #f8f9fc;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 7pt;
        }
        .bracket-detail h4 {
            font-size: 8pt;
            color: #4e73df;
            margin-bottom: 3px;
        }
        .bracket-detail table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
            table-layout: fixed;
        }
        .bracket-detail table th {
            background: #e8ecf6;
            padding: 2px 3px;
            border: 1px solid #dee2e6;
            font-size: 6pt;
            color: #333;
            word-wrap: break-word;
        }
        .bracket-detail table td {
            padding: 2px 3px;
            border: 1px solid #dee2e6;
            color: #333;
            word-wrap: break-word;
        }

        /* ─── Effective Rate Bar ─── */
        .rate-bar-container {
            width: 100%;
            background: #eee;
            border-radius: 2px;
            height: 8px;
            position: relative;
        }
        .rate-bar-fill {
            height: 8px;
            border-radius: 2px;
        }

        /* ─── Summary Boxes ─── */
        .two-col {
            display: table;
            width: 100%;
        }
        .two-col .col-left {
            display: table-cell;
            width: 48%;
            vertical-align: top;
            padding: 0 4px 0 0;
        }
        .two-col .col-right {
            display: table-cell;
            width: 52%;
            vertical-align: top;
            padding: 0 0 0 4px;
        }

        /* ─── Footer ─── */
        .report-footer {
            display: table;
            width: 100%;
            border-top: 2px solid #4e73df;
            padding-top: 6px;
            margin-top: 15px;
            font-size: 7pt;
            color: #666;
        }
        .footer-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .footer-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        /* ─── Legend ─── */
        .legend-box {
            background: #f8f9fc;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            padding: 5px 10px;
            margin-bottom: 8px;
            font-size: 7pt;
        }
        .legend-box strong { color: #2c3e50; }
        .legend-item { display: inline-block; margin-right: 12px; }
        .legend-color {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            vertical-align: middle;
            margin-right: 3px;
        }

        /* ─── Notes ─── */
        .notes-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 3px;
            padding: 5px 10px;
            margin-top: 10px;
            font-size: 7pt;
            color: #664d03;
        }
        .notes-box ul {
            margin: 3px 0 0 15px;
            padding: 0;
        }
        .notes-box li { margin-bottom: 2px; }
    </style>
</head>
<body>
    {{-- ══════════ PAGE 1: Report Header + KPIs + Distribution Table ══════════ --}}
    
    <!-- Header -->
    {{-- The configured letterhead, exactly as authored, in place of a
         masthead this report used to assemble from Settings. --}}
    @include('partials.document-header', ['forPdf' => true, 'rule' => false])

    <div class="report-header">
        <div class="header-right">
            <div class="report-title">STAFF TAX DISTRIBUTION REPORT</div>
            Effective Date: {{ \Carbon\Carbon::parse($effective_date)->format('F d, Y') }}
        </div>
    </div>

    <!-- Timestamp Banner -->
    <div class="timestamp-banner">
        <strong>Report Generated:</strong> {{ $generated_at->format('l, F d, Y \a\t h:i:s A') }} ({{ $generated_at->diffForHumans() }})
        &nbsp;&bull;&nbsp;
        <strong>Generated By:</strong> {{ $generated_by->name ?? 'System' }}
        &nbsp;&bull;&nbsp;
        <strong>Reference:</strong> TAX-RPT-{{ $generated_at->format('Ymd-His') }}
    </div>

    <!-- Active Filters -->
    @if($applied_filters)
    <div class="filter-summary">
        <strong>Active Filters:</strong> {{ $applied_filters }}
    </div>
    @endif

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-card-inner bg-primary">
                <div class="kpi-label">Staff Count</div>
                <div class="kpi-value">{{ $staff_count }}</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-inner bg-info">
                <div class="kpi-label">Total Gross Salary</div>
                <div class="kpi-value">{{ number_format($grand_totals['basic_salary'], 0) }}</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-inner bg-danger">
                <div class="kpi-label">Employee Tax</div>
                <div class="kpi-value">{{ number_format($grand_totals['employee_tax_total'], 0) }}</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-inner bg-warning-dark">
                <div class="kpi-label">Employer Tax</div>
                <div class="kpi-value">{{ number_format($grand_totals['employer_tax_total'], 0) }}</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-inner bg-success">
                <div class="kpi-label">Total Net Pay</div>
                <div class="kpi-value">{{ number_format($grand_totals['net_salary'], 0) }}</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card-inner bg-secondary">
                <div class="kpi-label">Avg Eff. Rate</div>
                <div class="kpi-value">{{ $avg_effective_rate }}%</div>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="legend-box">
        <strong>Legend:</strong>
        <span class="legend-item"><span class="legend-color" style="background:#4e73df;"></span> Group Tax (Employee)</span>
        <span class="legend-item"><span class="legend-color" style="background:#d48806;"></span> Standalone Tax</span>
        <span class="legend-item"><span style="color:#888; font-style:italic;">+amount</span> = Employer contribution</span>
        <span class="legend-item"><span class="badge badge-warning">E</span> = Has exemption(s)</span>
        <span class="legend-item"><span class="badge badge-success">Exempt</span> = Fully exempt</span>
        <span class="legend-item">Eff. Rate: <span style="color:#1cc88a;">●</span> &lt;10% <span style="color:#f6c23e;">●</span> 10-20% <span style="color:#e74a3b;">●</span> &gt;20%</span>
    </div>

    <!-- ═══ MAIN DISTRIBUTION TABLE ═══ -->
    <div class="section-title">Staff Tax Distribution Detail</div>
    <div class="section-desc">Per-staff breakdown of every active tax type. Employee deductions shown in main figure; employer contributions in grey italics below.</div>

    @php
        // Calculate dynamic column widths based on number of tax columns
        $numTaxCols = count($tax_groups) + count($standalone_taxes);
        $fixedPct = 3 + 13 + 7 + 7 + 6 + 5.5 + 5.5 + 6 + 6 + 4; // #, Name, Dept, Desig, Salary, EmplTax, EmprTax, Net, Cost, Rate = 63%
        $remainPct = max(100 - $fixedPct, $numTaxCols * 3); // at least 3% per tax col
        $taxColPct = $numTaxCols > 0 ? round($remainPct / $numTaxCols, 1) : 5;
    @endphp
    <table class="data-table data-table-compact">
        <thead>
            <tr>
                <th style="width:3%;">&#35;</th>
                <th style="width:13%; text-align:left;">Staff Name</th>
                <th style="width:7%;">Dept.</th>
                <th style="width:7%;">Desig.</th>
                <th style="width:6%;">Basic Sal.</th>
                @foreach($tax_groups as $group)
                <th class="group-header" style="width:{{ $taxColPct }}%;" title="{{ $group->title }}">{{ \Illuminate\Support\Str::limit($group->title, 12) }}</th>
                @endforeach
                @foreach($standalone_taxes as $tax)
                <th class="standalone-header" style="width:{{ $taxColPct }}%;" title="{{ $tax->title }}">
                    {{ \Illuminate\Support\Str::limit($tax->title, 12) }}
                    @if($tax->is_dependent)
                    <br><span style="font-size:6px; color:#d48806;">&#x1F517; Dep.</span>
                    @endif
                </th>
                @endforeach
                <th class="summary-header" style="width:5.5%;">Empl. Tax</th>
                <th class="summary-header" style="width:5.5%;">Empr. Tax</th>
                <th class="summary-header" style="width:6%;">Net Sal.</th>
                <th class="summary-header" style="width:6%;">Total Cost</th>
                <th class="summary-header" style="width:4%;">Rate</th>
            </tr>
        </thead>
        <tbody>
            @foreach($staff_rows as $index => $sr)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-left">
                    <strong>{{ \Illuminate\Support\Str::limit($sr['user']->name, 22) }}</strong>
                    @if($sr['exemption_count'] > 0)
                    <span class="badge badge-warning">E</span>
                    @endif
                </td>
                <td class="text-center" style="font-size:5.5pt;">{{ \Illuminate\Support\Str::limit($sr['user']->department->title ?? '-', 14) }}</td>
                <td class="text-center" style="font-size:5.5pt;">{{ \Illuminate\Support\Str::limit($sr['user']->designation->title ?? '-', 14) }}</td>
                <td class="text-right font-bold">{{ number_format($sr['basic_salary'], 0) }}</td>

                {{-- Group taxes --}}
                @foreach($tax_groups as $group)
                <td class="text-right">
                    @if(isset($sr['groups'][$group->id]))
                        @php $g = $sr['groups'][$group->id]; @endphp
                        {{ number_format($g['employee'], 0) }}
                        @if($g['employer'] > 0)
                        <br><span class="employer-amount">+{{ number_format($g['employer'], 0) }}</span>
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
                            <span class="badge badge-success">Exempt</span>
                        @else
                            {{ number_format($s['employee'], 0) }}
                            @if($s['employer'] > 0)
                            <br><span class="employer-amount">+{{ number_format($s['employer'], 0) }}</span>
                            @endif
                        @endif
                    @else
                        0
                    @endif
                </td>
                @endforeach

                {{-- Summaries --}}
                <td class="text-right text-danger font-bold">{{ number_format($sr['employee_tax_total'], 0) }}</td>
                <td class="text-right text-warning">{{ number_format($sr['employer_tax_total'], 0) }}</td>
                <td class="text-right text-success font-bold">{{ number_format($sr['net_salary'], 0) }}</td>
                <td class="text-right">{{ number_format($sr['total_cost'], 0) }}</td>
                <td class="text-center">
                    @php $rate = $sr['basic_salary'] > 0 ? round(($sr['employee_tax_total'] / $sr['basic_salary']) * 100, 1) : 0; @endphp
                    <span class="badge {{ $rate < 10 ? 'badge-success' : ($rate < 20 ? 'badge-warning' : 'badge-danger') }}">{{ $rate }}%</span>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="text-center" colspan="2"><strong>TOTALS ({{ $staff_count }} staff)</strong></td>
                <td colspan="2"></td>
                <td class="text-right font-bold">{{ number_format($grand_totals['basic_salary'], 0) }}</td>

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

                <td class="text-right text-danger font-bold">{{ number_format($grand_totals['employee_tax_total'], 0) }}</td>
                <td class="text-right text-warning font-bold">{{ number_format($grand_totals['employer_tax_total'], 0) }}</td>
                <td class="text-right text-success font-bold">{{ number_format($grand_totals['net_salary'], 0) }}</td>
                <td class="text-right font-bold">{{ number_format($grand_totals['total_cost'], 0) }}</td>
                <td class="text-center"><span class="badge badge-dark">{{ $avg_effective_rate }}%</span></td>
            </tr>
        </tfoot>
    </table>

    {{-- ══════════ PAGE 2: Tax Configuration Details + Summaries ══════════ --}}
    <div class="page-break"></div>

    <!-- Repeat header on page 2 -->
    {{-- The configured letterhead, exactly as authored, in place of a
         masthead this report used to assemble from Settings. --}}
    @include('partials.document-header', ['forPdf' => true, 'rule' => false])

    <div class="report-header">
        <div class="header-right">
            <div class="report-title">PAGE 2 — DETAILS</div>
            Ref: TAX-RPT-{{ $generated_at->format('Ymd-His') }}
        </div>
    </div>

    <!-- ═══ EFFECTIVE TAX RATE ANALYSIS ═══ -->
    <div class="section-title">Effective Tax Rate Analysis</div>
    <div class="section-desc">
        The effective tax rate shows what percentage of each staff member's basic salary is deducted as employee tax. 
        Range: <strong>{{ $min_effective_rate }}%</strong> (lowest) to <strong>{{ $max_effective_rate }}%</strong> (highest), Average: <strong>{{ $avg_effective_rate }}%</strong>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:3%;">#</th>
                <th style="width:22%; text-align:left;">Staff Name</th>
                <th style="width:12%;">Basic Salary</th>
                <th style="width:12%;">Employee Tax</th>
                <th style="width:9%;">Eff. Rate</th>
                <th style="width:42%;">Rate Visualization</th>
            </tr>
        </thead>
        <tbody>
            @foreach($staff_rows as $index => $sr)
            @php
                $rate = $sr['basic_salary'] > 0 ? round(($sr['employee_tax_total'] / $sr['basic_salary']) * 100, 1) : 0;
                $maxRate = max($max_effective_rate, 1);
                $barWidth = min(100, ($rate / $maxRate) * 100);
                $barColor = $rate < 10 ? '#1cc88a' : ($rate < 20 ? '#f6c23e' : '#e74a3b');
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-left">{{ $sr['user']->name }}</td>
                <td class="text-right">{{ number_format($sr['basic_salary'], 0) }}</td>
                <td class="text-right text-danger">{{ number_format($sr['employee_tax_total'], 0) }}</td>
                <td class="text-center">
                    <span class="badge {{ $rate < 10 ? 'badge-success' : ($rate < 20 ? 'badge-warning' : 'badge-danger') }}">{{ $rate }}%</span>
                </td>
                <td>
                    <div class="rate-bar-container">
                        <div class="rate-bar-fill" style="width: {{ $barWidth }}%; background: {{ $barColor }};"></div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══ SALARY BAND SUMMARY ═══ -->
    <div class="section-title">Salary Band Distribution</div>
    <div class="section-desc">Staff grouped by salary range showing the concentration of the workforce and the corresponding tax burden at each level.</div>

    <div class="two-col">
        <div class="col-left">
            <table class="data-table" style="table-layout:fixed;">
                <thead>
                    <tr>
                        <th style="width:32%; text-align:left;">Salary Range (FCFA)</th>
                        <th style="width:12%;">Staff</th>
                        <th style="width:22%;">Total Salary</th>
                        <th style="width:20%;">Total Tax</th>
                        <th style="width:14%;">Avg Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($salary_bands as $label => $band)
                    <tr>
                        <td class="text-left">{{ $label }}</td>
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

        <!-- ═══ TAX BREAKDOWN SUMMARY ═══ -->
        <div class="col-right">
            <table class="data-table" style="table-layout:fixed;">
                <thead>
                    <tr>
                        <th style="width:28%; text-align:left;">Tax Name</th>
                        <th style="width:12%;">Type</th>
                        <th style="width:20%;">Empl. Total</th>
                        <th style="width:20%;">Empr. Total</th>
                        <th style="width:20%;">Combined</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tax_groups as $group)
                    <tr>
                        <td class="text-left">{{ $group->title }}</td>
                        <td class="text-center"><span class="badge badge-primary">Group</span></td>
                        <td class="text-right">{{ number_format($grand_totals['groups'][$group->id]['employee'], 0) }}</td>
                        <td class="text-right">{{ number_format($grand_totals['groups'][$group->id]['employer'], 0) }}</td>
                        <td class="text-right font-bold">{{ number_format($grand_totals['groups'][$group->id]['employee'] + $grand_totals['groups'][$group->id]['employer'], 0) }}</td>
                    </tr>
                    @endforeach
                    @foreach($standalone_taxes as $tax)
                    <tr>
                        <td class="text-left">
                            {{ $tax->title }}
                            @if($tax->is_dependent)
                            <span style="color:#d48806; font-size:8px;"> (Dependent - from {{ $tax->dependency_label ?? 'source tax' }})</span>
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
                        <td class="text-right font-bold">{{ number_format($grand_totals['standalone'][$tax->id]['employee'] + $grand_totals['standalone'][$tax->id]['employer'], 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-left"><strong>Grand Total</strong></td>
                        <td class="text-right text-danger font-bold">{{ number_format($grand_totals['employee_tax_total'], 0) }}</td>
                        <td class="text-right text-warning font-bold">{{ number_format($grand_totals['employer_tax_total'], 0) }}</td>
                        <td class="text-right font-bold">{{ number_format($grand_totals['employee_tax_total'] + $grand_totals['employer_tax_total'], 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ═══ TAX CONFIGURATION REFERENCE ═══ -->
    <div class="section-title">Tax Configuration Reference</div>
    <div class="section-desc">Active tax rules as of {{ \Carbon\Carbon::parse($effective_date)->format('F d, Y') }}. This section documents the brackets, rates, and rules that produced the figures above.</div>

    @foreach($tax_groups as $group)
    <div class="bracket-detail">
        <h4>
            {{ $group->title }}
            @if($group->code) ({{ $group->code }}) @endif
            — {{ $group->is_progressive ? 'Progressive' : 'Flat Rate' }}
        </h4>
        @if($group->description)
        <p style="margin-bottom:3px; color:#666;">{{ $group->description }}</p>
        @endif
        @if($group->brackets->count() > 0)
        <table style="table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:6%;">Order</th>
                    <th style="width:15%;">Min Amt</th>
                    <th style="width:15%;">Max Amt</th>
                    <th style="width:10%;">Type</th>
                    <th style="width:14%;">Rate/Fixed</th>
                    <th style="width:12%;">Paid By</th>
                    <th style="width:14%;">Empr. Rate</th>
                    <th style="width:14%;">Non-Tax.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group->brackets as $bracket)
                <tr>
                    <td class="text-center">{{ $bracket->bracket_order }}</td>
                    <td class="text-right">{{ number_format($bracket->min_amount, 0) }}</td>
                    <td class="text-right">{{ number_format($bracket->max_amount, 0) }}</td>
                    <td class="text-center">{{ $bracket->tax_type == 1 ? 'Percentage' : 'Fixed' }}</td>
                    <td class="text-center">
                        @if($bracket->tax_type == 1)
                            {{ $bracket->percentange }}%
                        @else
                            {{ number_format($bracket->fixed_amount, 0) }}
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $bracket->paid_by == 'employee' ? 'badge-info' : ($bracket->paid_by == 'employer' ? 'badge-warning' : 'badge-dark') }}">
                            {{ ucfirst($bracket->paid_by) }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($bracket->paid_by != 'employee')
                            @if($bracket->tax_type == 1)
                                {{ $bracket->employer_percentage }}%
                            @else
                                {{ number_format($bracket->employer_fixed_amount, 0) }}
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($bracket->max_no_taxable_amount, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p style="color:#888; font-style:italic;">No brackets configured for this tax group.</p>
        @endif
    </div>
    @endforeach

    @if($standalone_taxes->count() > 0)
    <div class="bracket-detail">
        <h4>Standalone Taxes</h4>
        <table style="table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:18%;">Tax Name</th>
                    <th style="width:12%;">Min Amt</th>
                    <th style="width:12%;">Max Amt</th>
                    <th style="width:9%;">Type</th>
                    <th style="width:14%;">Empl. Rate</th>
                    <th style="width:10%;">Paid By</th>
                    <th style="width:14%;">Empr. Rate</th>
                    <th style="width:11%;">Non-Tax.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($standalone_taxes as $tax)
                <tr>
                    <td class="text-left">
                        {{ $tax->title }}
                        @if($tax->is_dependent)
                        <br><small style="color:#d48806;">&rarr; Calculated from: {{ $tax->dependency_label ?? '-' }}</small>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($tax->min_amount, 0) }}</td>
                    <td class="text-right">{{ number_format($tax->max_amount, 0) }}</td>
                    <td class="text-center">{{ $tax->tax_type == 1 ? 'Percentage' : 'Fixed' }}</td>
                    <td class="text-center">
                        @if($tax->tax_type == 1)
                            {{ $tax->percentange }}%
                        @else
                            {{ number_format($tax->fixed_amount, 0) }}
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $tax->paid_by == 'employee' ? 'badge-info' : ($tax->paid_by == 'employer' ? 'badge-warning' : 'badge-dark') }}">
                            {{ ucfirst($tax->paid_by) }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($tax->paid_by != 'employee')
                            @if($tax->tax_type == 1)
                                {{ $tax->employer_percentage }}%
                            @else
                                {{ number_format($tax->employer_fixed_amount, 0) }}
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($tax->max_no_taxable_amount, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- ═══ EXEMPTION DETAILS ═══ -->
    @if($exemption_details->count() > 0)
    <div class="section-title">Staff Tax Exemptions</div>
    <div class="section-desc">Active exemptions that modify or eliminate specific tax obligations for individual staff members.</div>

    <table class="data-table" style="table-layout:fixed;">
        <thead>
            <tr>
                <th style="width:20%; text-align:left;">Staff Name</th>
                <th style="width:22%; text-align:left;">Tax Bracket / Setting</th>
                <th style="width:30%;">Reason</th>
                <th style="width:14%;">Custom Rate</th>
                <th style="width:14%;">Expires</th>
            </tr>
        </thead>
        <tbody>
            @foreach($exemption_details as $ex)
            <tr>
                <td class="text-left">{{ $ex->user->name ?? 'Unknown' }}</td>
                <td class="text-left">{{ $ex->taxSetting->title ?? 'Unknown' }}</td>
                <td>{{ $ex->reason ?? '-' }}</td>
                <td class="text-center">
                    @if($ex->custom_percentage)
                        {{ $ex->custom_percentage }}%
                    @elseif($ex->custom_fixed_amount)
                        {{ number_format($ex->custom_fixed_amount, 0) }}
                    @else
                        Full Exempt
                    @endif
                </td>
                <td class="text-center">
                    @if($ex->expires_at)
                        {{ \Carbon\Carbon::parse($ex->expires_at)->format('M d, Y') }}
                    @else
                        <span class="text-muted">No expiry</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- ═══ HISTORICAL PAYROLL (if available) ═══ -->
    @php $hasHistorical = collect($historical_data)->sum('count') > 0; @endphp
    @if($hasHistorical)
    <div class="section-title">Historical Payroll Tax Trend (Last 6 Months)</div>
    <div class="section-desc">Actual payroll data showing month-over-month tax deductions from processed payrolls.</div>

    <table class="data-table" style="table-layout:fixed;">
        <thead>
            <tr>
                <th style="width:12%;">Month</th>
                <th style="width:10%;">Payrolls</th>
                <th style="width:18%;">Total Gross Sal.</th>
                <th style="width:18%;">Empl. Tax</th>
                <th style="width:16%;">Empr. Tax</th>
                <th style="width:16%;">Total Net Sal.</th>
                <th style="width:10%;">Avg Rate</th>
            </tr>
        </thead>
        <tbody>
            @foreach($historical_data as $h)
            <tr>
                <td class="text-center">{{ $h['label'] }}</td>
                <td class="text-center">{{ $h['count'] }}</td>
                <td class="text-right">{{ number_format($h['total_gross'], 0) }}</td>
                <td class="text-right text-danger">{{ number_format($h['total_tax'], 0) }}</td>
                <td class="text-right text-warning">{{ number_format($h['employer_tax'], 0) }}</td>
                <td class="text-right text-success">{{ number_format($h['total_net'], 0) }}</td>
                <td class="text-center">
                    @if($h['total_gross'] > 0)
                        {{ round(($h['total_tax'] / $h['total_gross']) * 100, 1) }}%
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- ═══ NOTES ═══ -->
    <div class="notes-box">
        <strong>⚠ Important Notes:</strong>
        <ul>
            <li><strong>Estimates vs Actuals:</strong> The tax figures in this report are calculated from each staff member's current basic salary and the active tax configuration as of {{ \Carbon\Carbon::parse($effective_date)->format('F d, Y') }}. They represent estimated monthly deductions, not actual payroll amounts.</li>
            <li><strong>Scope:</strong> Only statutory taxes on basic salary are included. Allowances (transport, housing, etc.), bonuses, and other deductions are not factored into this calculation.</li>
            <li><strong>Progressive Taxes:</strong> For progressive tax groups, only the portion of salary within each bracket range is taxed at that bracket's rate.</li>
            <li><strong>Combined Cost:</strong> The "Total Cost" column represents the full cost to the institution: basic salary + employer tax contributions.</li>
            <li><strong>Historical Data:</strong> The trend section (if shown) uses actual processed payroll records and may differ from estimates due to mid-month salary changes or adjustments.</li>
        </ul>
    </div>

    <!-- ═══ FOOTER ═══ -->
    <div class="report-footer">
        <div class="footer-left">
            <strong>Generated on:</strong> {{ $generated_at->format('F d, Y \a\t h:i:s A') }}<br>
            <strong>Generated by:</strong> {{ $generated_by->name ?? 'System' }}<br>
            <strong>Reference:</strong> TAX-RPT-{{ $generated_at->format('Ymd-His') }}
        </div>
        <div class="footer-right">
            <em>This is a system-generated document from {{ $setting->title ?? 'the Institution' }}.</em><br>
            <em>Confidential — For authorized personnel only.</em><br>
            <em>Report valid as of generation date only.</em>
        </div>
    </div>
</body>
</html>
