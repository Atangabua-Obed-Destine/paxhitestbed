@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12 no-print">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title"><i class="fas fa-university"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Results Summary') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Faculty Report') }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                            </a>
                            @if(collect($semester_blocks ?? [])->contains(fn ($b) => count($b['department_summaries']) > 0))
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('Print Report') }}
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Generate Faculty Summary Report') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.faculty-report') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="faculty">{{ __('field_faculty') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($faculties))
                                        @foreach($faculties->sortBy('title') as $faculty)
                                        <option value="{{ $faculty->id }}" @if($selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="degree_type">{{ __('Degree Type') }}</label>
                                    <select class="form-control" name="degree_type" id="degree_type">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($degree_types))
                                        @foreach($degree_types as $dt)
                                        <option value="{{ $dt->id }}" @if($selected_degree_type == $dt->id) selected @endif>{{ $dt->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="session">{{ __('field_session') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($sessions))
                                        @foreach($sessions->sortByDesc('id') as $sess)
                                        <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                @include('common.inc.semester_type_field', ['type_col' => 'col-md-2', 'semester_col' => 'col-md-3'])

                                <div class="form-group col-md-3">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-block btn-filter">
                                        <i class="fas fa-file-alt"></i> {{ __('Generate Report') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @include('admin.results-summary.partials.skipped-semesters')
            @if(collect($semester_blocks ?? [])->contains(fn ($b) => count($b['department_summaries']) > 0))
            @foreach($semester_blocks as $block)
            @include('admin.results-summary.partials.faculty-semester', $block)
            @endforeach
            @else
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ __('Please select filters to generate the faculty summary report') }}</h5>
                        <p class="text-muted">Select Faculty, Session, and Semester to view the summary report.</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<style>
/* Print styles */
@media print {
    /* Hide all navigation and non-essential elements */
    .pcoded-header,
    .pcoded-navbar,
    .page-header,
    .breadcrumb,
    nav.navbar,
    .sidebar,
    footer,
    .btn,
    .btn-group,
    form,
    .card-header,
    canvas,
    .no-print {
        display: none !important;
        visibility: hidden !important;
    }
    
    /* Reset page layout for print */
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    
    .pcoded-main-container {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    .pcoded-wrapper,
    .pcoded-content,
    .pcoded-inner-content,
    .main-body,
    .page-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
        margin: 0 0 10px 0 !important;
    }
    
    .card-body {
        padding: 5px !important;
    }
    
    /* Table styling for print */
    table {
        font-size: 9px !important;
        width: 100% !important;
        border-collapse: collapse !important;
    }
    
    table th, table td {
        padding: 3px 4px !important;
        border: 1px solid #000 !important;
    }
    
    .table-responsive {
        overflow: visible !important;
    }
    
    /* Hide charts and KPI cards on print */
    .col-xl-3.col-md-6,
    .col-xl-6.col-md-12 {
        display: none !important;
    }
    
    /* Ensure colors print */
    .badge-success { background-color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-warning { background-color: #ffc107 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-danger { background-color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-success { color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-danger { color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .thead-dark th { background-color: #343a40 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .progress { display: none !important; }
    
    /* Page setup */
    @page {
        size: A4 landscape;
        margin: 5mm;
    }
}

.badge-lg {
    font-size: 14px;
    padding: 8px 12px;
}
</style>
@endsection
