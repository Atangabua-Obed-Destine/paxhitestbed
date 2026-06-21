@extends('admin.layouts.master')
@section('title', __('journal_entry_details'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('journal_entry_details') }} - {{ $entry->entry_number }}</h3>
                        <div class="card-tools">
                            @can('journal-entry-edit')
                            @if(!$entry->is_posted)
                            <a href="{{ route('admin.journal-entries.edit', $entry->id) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> {{ __('edit') }}
                            </a>
                            @endif
                            @endcan
                            <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <!-- Entry Information -->
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">{{ __('entry_number') }}</th>
                                        <td><strong>{{ $entry->entry_number }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('entry_date') }}</th>
                                        <td>{{ \Carbon\Carbon::parse($entry->entry_date)->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('fiscal_year') }}</th>
                                        <td>{{ $entry->fiscalYear->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('accounting_period') }}</th>
                                        <td>
                                            @if($entry->accountingPeriod)
                                                {{ $entry->accountingPeriod->name }} ({{ $entry->accountingPeriod->french_name }})
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('journal_type') }}</th>
                                        <td>
                                            <span class="badge badge-info">{{ __(strtolower($entry->journal_type)) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('reference_number') }}</th>
                                        <td>{{ $entry->reference_number ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('description') }}</th>
                                        <td>{{ $entry->description }}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Status & Totals -->
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">{{ __('status_title') }}</th>
                                        <td>
                                            @if($entry->is_posted)
                                            <span class="badge badge-success">{{ __('posted') }}</span>
                                            @else
                                            <span class="badge badge-warning">{{ __('draft') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('total_debit') }}</th>
                                        <td class="text-right">
                                            <strong>{{ number_format($entry->total_debit, 0, ',', ' ') }}</strong> FCFA
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('total_credit') }}</th>
                                        <td class="text-right">
                                            <strong>{{ number_format($entry->total_credit, 0, ',', ' ') }}</strong> FCFA
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('balanced') }}</th>
                                        <td>
                                            @if($entry->isBalanced())
                                            <span class="badge badge-success">{{ __('yes') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('no') }} ({{ __('difference') }}: {{ number_format(abs($entry->total_debit - $entry->total_credit), 0, ',', ' ') }} FCFA)</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($entry->is_posted)
                                    <tr>
                                        <th>{{ __('posted_by') }}</th>
                                        <td>{{ $entry->postedBy->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('posted_at') }}</th>
                                        <td>{{ $entry->posted_at ? \Carbon\Carbon::parse($entry->posted_at)->format('d M Y H:i') : '-' }}</td>
                                    </tr>
                                    @endif
                                    @if($entry->is_system_generated)
                                    <tr>
                                        <th>{{ __('system_generated') }}</th>
                                        <td><span class="badge badge-info">{{ __('yes') }}</span></td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('created_at') }}</th>
                                        <td>{{ $entry->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Journal Entry Lines -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>{{ __('journal_entry_lines') }} ({{ $entry->lines->count() }})</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th width="5%">{{ __('line') }}</th>
                                                <th width="15%">{{ __('account_code') }}</th>
                                                <th width="30%">{{ __('account_name') }}</th>
                                                <th width="25%">{{ __('description') }}</th>
                                                <th width="12%" class="text-right">{{ __('debit') }}</th>
                                                <th width="12%" class="text-right">{{ __('credit') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($entry->lines as $line)
                                            <tr>
                                                <td class="text-center">{{ $line->line_number }}</td>
                                                <td><strong>{{ $line->account->account_code }}</strong></td>
                                                <td>
                                                    <a href="{{ route('admin.chart-of-accounts.show', $line->account_id) }}">
                                                        {{ $line->account->account_name }}
                                                    </a>
                                                    @if($line->account->account_name_fr)
                                                    <br><small class="text-muted">{{ $line->account->account_name_fr }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ $line->description ?? '-' }}</td>
                                                <td class="text-right">
                                                    @if($line->debit > 0)
                                                    <strong>{{ number_format($line->debit, 0, ',', ' ') }}</strong>
                                                    @else
                                                    -
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    @if($line->credit > 0)
                                                    <strong>{{ number_format($line->credit, 0, ',', ' ') }}</strong>
                                                    @else
                                                    -
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-info">
                                                <td colspan="4" class="text-right"><strong>{{ __('total') }}:</strong></td>
                                                <td class="text-right">
                                                    <strong>{{ number_format($entry->total_debit, 0, ',', ' ') }}</strong> FCFA
                                                </td>
                                                <td class="text-right">
                                                    <strong>{{ number_format($entry->total_credit, 0, ',', ' ') }}</strong> FCFA
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>{{ __('quick_actions') }}</h5>
                                <div class="btn-group" role="group">
                                    @can('journal-entry-edit')
                                    @if(!$entry->is_posted)
                                    <a href="{{ route('admin.journal-entries.edit', $entry->id) }}" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> {{ __('edit') }}
                                    </a>
                                    @endif
                                    @endcan
                                    
                                    @can('journal-entry-post')
                                    @if(!$entry->is_posted)
                                    <form action="{{ route('admin.journal-entries.post', $entry->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        <button type="submit" class="btn btn-success" onclick="return confirm('{{ __('confirm_post_entry') }}')">
                                            <i class="fas fa-check"></i> {{ __('post_entry') }}
                                        </button>
                                    </form>
                                    @else
                                    <form action="{{ route('admin.journal-entries.unpost', $entry->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('confirm_unpost_entry') }}')">
                                            <i class="fas fa-undo"></i> {{ __('unpost_entry') }}
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                    
                                    @can('journal-entry-create')
                                    <form action="{{ route('admin.journal-entries.duplicate', $entry->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('{{ __('confirm_duplicate_entry') }}')">
                                            <i class="fas fa-copy"></i> {{ __('duplicate') }}
                                        </button>
                                    </form>
                                    @endcan
                                    
                                    <button type="button" class="btn btn-info" onclick="window.print()">
                                        <i class="fas fa-print"></i> {{ __('print') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection

@push('styles')
<style>
    /* Print styles */
    @media print {
        /* Hide UI elements */
        .main-header,
        .main-sidebar,
        .main-footer,
        .content-header,
        .breadcrumb,
        .card-header,
        nav,
        .sidebar,
        footer,
        .btn,
        button,
        form,
        .quick_actions,
        .btn-group,
        /* Hide chatbot and widgets */
        [class*="chat"],
        [id*="chat"],
        [class*="widget"],
        [id*="widget"],
        [class*="bot"],
        [id*="bot"],
        iframe,
        .tawk-min-container,
        #tawk-bubble-container,
        .crisp-client,
        [data-chat],
        script {
            display: none !important;
            visibility: hidden !important;
        }

        /* Hide the Quick Actions row */
        .card-body > .row:last-child {
            display: none !important;
        }

        /* Reset page */
        body {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Card styling */
        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .card-body {
            padding: 10mm 10mm !important;
        }

        /* Header */
        .card-body::before {
            content: "PAXHI";
            display: block;
            text-align: center;
            font-size: 22pt;
            font-weight: bold;
            margin-bottom: 12px;
            letter-spacing: 3px;
        }

        /* Title */
        .card-body > .row:first-child::before {
            content: "JOURNAL ENTRY - {{ $entry->entry_number }}";
            display: block;
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin: 0 auto 15px;
            padding: 8px 12px;
            border: 2px solid #000;
            width: fit-content;
        }

        /* Tables container */
        .card-body > .row:first-child {
            display: block !important;
            width: 100% !important;
            margin-bottom: 15px !important;
        }

        /* Two columns */
        .card-body > .row:first-child .col-md-6:first-child {
            width: 48% !important;
            float: left !important;
            margin-right: 2% !important;
            padding: 0 !important;
        }

        .card-body > .row:first-child .col-md-6:last-child {
            width: 50% !important;
            float: right !important;
            padding: 0 !important;
        }

        /* Table styles */
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-bottom: 0 !important;
            font-size: 9pt !important;
        }

        .table th {
            background-color: #f5f5f5 !important;
            border: 1px solid #000 !important;
            padding: 5px 6px !important;
            font-weight: bold !important;
            text-align: left !important;
            font-size: 9pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .table td {
            border: 1px solid #000 !important;
            padding: 5px 6px !important;
            font-size: 9pt !important;
        }

        /* Journal Lines section */
        .card-body > .row:nth-child(2) {
            clear: both !important;
            display: block !important;
            width: 100% !important;
            margin-top: 15px !important;
        }

        .card-body > .row:nth-child(2) .col-md-12 {
            width: 100% !important;
            padding: 0 !important;
        }

        .card-body > .row:nth-child(2) h5 {
            font-size: 10pt !important;
            font-weight: bold !important;
            margin-bottom: 8px !important;
            text-transform: uppercase;
        }

        /* Journal lines table header */
        .card-body > .row:nth-child(2) .table thead th {
            background-color: #333 !important;
            color: white !important;
            text-align: center !important;
            padding: 6px 4px !important;
            font-size: 9pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Totals row */
        .table tfoot td {
            background-color: #e8e8e8 !important;
            border: 2px solid #000 !important;
            padding: 6px 8px !important;
            font-weight: bold !important;
            font-size: 9pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Badge */
        .badge {
            border: 1px solid #000 !important;
            padding: 3px 8px !important;
            background: white !important;
            color: #000 !important;
            display: inline-block !important;
            font-weight: normal !important;
        }

        /* Links */
        a {
            color: #000 !important;
            text-decoration: none !important;
        }

        /* Text alignment */
        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        /* Small italic text */
        small {
            font-size: 9pt !important;
            font-style: italic !important;
            color: #666 !important;
        }

        /* Footer timestamp */
        .card-body::after {
            content: "Printed on: {{ now()->format('d M Y H:i') }}";
            display: block;
            text-align: right;
            font-size: 8pt;
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            color: #666;
            font-style: italic;
            clear: both;
        }

        /* Remove extra spacing */
        .mt-4 {
            margin-top: 0 !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        .table-hover tbody tr:hover {
            background-color: transparent !important;
        }
    }

    /* Page setup */
    @page {
        size: A4 portrait;
        margin: 12mm 10mm;
    }

    /* Hide chatbot on screen too when printing */
    @media screen {
        body.printing [class*="chat"],
        body.printing [id*="chat"],
        body.printing [class*="widget"],
        body.printing [id*="widget"] {
            display: none !important;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Hide chatbot before printing
    window.onbeforeprint = function() {
        document.body.classList.add('printing');
    };
    
    window.onafterprint = function() {
        document.body.classList.remove('printing');
    };
</script>
@endpush
