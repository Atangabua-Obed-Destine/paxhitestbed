<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Report')</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header Styles */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #444;
            margin: 10px 0;
        }
        
        .report-date {
            font-size: 11px;
            color: #666;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            color: #333;
        }
        
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .text-success {
            color: #28a745;
        }
        
        .text-warning {
            color: #ffc107;
        }
        
        /* Summary Box */
        .summary-box {
            display: inline-block;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 5px;
            min-width: 150px;
            text-align: center;
        }
        
        .summary-box .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        
        .summary-box .value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .summary-section {
            margin-bottom: 25px;
            text-align: center;
        }
        
        /* Footer */
        .report-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin: 20px 0 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        
        /* Totals Row */
        .totals-row {
            background-color: #e9ecef !important;
            font-weight: bold;
        }
        
        /* Print-specific styles */
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        /* Print button (hidden when printing) */
        .print-actions {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: #fff;
            border: none;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
            border: none;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        /* Indent for sub-items */
        .indent-1 { padding-left: 25px; }
        .indent-2 { padding-left: 50px; }
        .indent-3 { padding-left: 75px; }
    </style>
</head>
<body>
    <div class="container">
        @if(isset($printable) && $printable)
        <div class="print-actions no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> {{ __('Print Report') }}
            </button>
            <button class="btn btn-secondary" onclick="window.history.back()">
                {{ __('Back') }}
            </button>
        </div>
        @endif
        
        {{-- The configured letterhead, exactly as authored, in place of a
             company name assembled here. --}}
        @include('partials.document-header', ['forPdf' => true, 'rule' => false])

        <div class="report-header">
            <div class="report-title">@yield('report_title')</div>
            <div class="report-date">{{ __('Generated on') }}: {{ now()->format('F d, Y H:i') }}</div>
        </div>
        
        @yield('content')
        
        <div class="report-footer">
            <p>{{ institution_name() }} - {{ __('Accounting Module') }}</p>
            <p>{{ __('This report is system generated') }}</p>
        </div>
    </div>
</body>
</html>
