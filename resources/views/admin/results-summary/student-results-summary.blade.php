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
                        <h4 class="page-title"><i class="fas fa-th-list"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Results Summary') }}</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                            </a>
                            <a href="{{ route($route.'.course-marksheet') }}" class="btn btn-primary">
                                <i class="fas fa-file-spreadsheet"></i> {{ __('Course Marksheet') }}
                            </a>
                            @if(collect($semester_blocks ?? [])->contains(fn ($b) => count($b['student_results']) > 0))
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('Print') }}
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
                        <h5><i class="fas fa-filter"></i> {{ __('Select Program & Session to Generate Student Results Summary') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.student-results-summary') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-2">
                                    <label for="faculty">{{ __('field_faculty') }} <span class="text-danger">*</span></label>
                                    <select class="form-control faculty" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($faculties))
                                        @foreach($faculties->sortBy('title') as $fac)
                                        <option value="{{ $fac->id }}" @if($selected_faculty == $fac->id) selected @endif>{{ $fac->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="program">{{ __('field_program') }} <span class="text-danger">*</span></label>
                                    <select class="form-control program" name="program" id="program" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($programs))
                                        @foreach($programs->sortBy('title') as $prog)
                                        <option value="{{ $prog->id }}" @if($selected_program == $prog->id) selected @endif>{{ $prog->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="session">{{ __('field_session') }} <span class="text-danger">*</span></label>
                                    <select class="form-control session" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($sessions))
                                        @foreach($sessions->sortByDesc('id') as $sess)
                                        <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                @include('common.inc.semester_type_field', ['type_col' => 'col-md-2', 'semester_col' => 'col-md-2'])

                                <div class="form-group col-md-2">
                                    <label for="section">{{ __('field_section') }}</label>
                                    <select class="form-control section" name="section" id="section">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($sections))
                                        @foreach($sections->sortBy('title') as $sec)
                                        <option value="{{ $sec->id }}" @if($selected_section == $sec->id) selected @endif>{{ $sec->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-block btn-filter">
                                        <i class="fas fa-th-list"></i> {{ __('Generate Summary') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @include('admin.results-summary.partials.skipped-semesters')
            @if(collect($semester_blocks ?? [])->contains(fn ($b) => count($b['student_results']) > 0))
            @if(count($semester_blocks) > 1)
            <div class="col-sm-12 no-print">
                <form method="post" action="{{ route($route.'.export-student-results-summary') }}" class="text-right mb-2">
                    @csrf
                    <input type="hidden" name="faculty" value="{{ $selected_faculty }}">
                    <input type="hidden" name="program" value="{{ $selected_program }}">
                    <input type="hidden" name="session" value="{{ $selected_session }}">
                    <input type="hidden" name="semester_type" value="{{ $selected_semester_type }}">
                    @foreach($semester_blocks as $b)
                    <input type="hidden" name="semester[]" value="{{ $b['semester']->id }}">
                    @endforeach
                    <input type="hidden" name="section" value="{{ $selected_section }}">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> {{ __('Export all semesters to Excel') }} ({{ count($semester_blocks) }})
                    </button>
                </form>
            </div>
            @endif
            @foreach($semester_blocks as $block)
            @include('admin.results-summary.partials.student-semester', $block)
            @endforeach
            @else
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                        <h5>{{ __('Select filters to generate Student Results Summary') }}</h5>
                        <p class="text-muted">Choose Faculty, Program, Session, and Semester to view the student-by-course results matrix.</p>
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
<script>
    "use strict";

    // AJAX filter for programs based on faculty
    $(".faculty").on('change', function(e) {
        e.preventDefault();
        var faculty = $(this).val();
        var program = $(".program");
        
        // Clear dependent dropdowns
        program.empty().append('<option value="">{{ __("select") }}</option>');
        $(".session").empty().append('<option value="">{{ __("select") }}</option>');
        $(".section").empty().append('<option value="0">{{ __("all") }}</option>');
        
        if (!faculty) return;
        
        $.ajax({
            type: 'POST',
            url: "{{ route('filter-program') }}",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                faculty: faculty
            },
            success: function(response) {
                $.each(response, function() {
                    $('<option/>', {
                        'value': this.id,
                        'text': this.title
                    }).appendTo(program);
                });
            }
        });
    });

    // AJAX filter for sessions and semesters based on program
    $(".program").on('change', function(e) {
        e.preventDefault();
        var program = $(this).val();
        var session = $(".session");
        
        // Clear dependent dropdowns
        session.empty().append('<option value="">{{ __("select") }}</option>');
        $(".section").empty().append('<option value="0">{{ __("all") }}</option>');
        
        if (!program) return;
        
        // Load sessions
        $.ajax({
            type: 'POST',
            url: "{{ route('filter-session') }}",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                program: program
            },
            success: function(response) {
                $.each(response, function() {
                    $('<option/>', {
                        'value': this.id,
                        'text': this.title
                    }).appendTo(session);
                });
            }
        });
    });

    // AJAX filter for sections based on semester
    $("#semester").on('change', function(e) {
        e.preventDefault();
        var program = $(".program").val();
        var semester = ($(this).val() || [])[0]; // sections of the first chosen semester
        var section = $(".section");
        
        section.empty().append('<option value="0">{{ __("all") }}</option>');
        
        if (!semester || !program) return;
        
        $.ajax({
            type: 'POST',
            url: "{{ route('filter-section') }}",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                program: program,
                semester: semester
            },
            success: function(response) {
                $.each(response, function() {
                    $('<option/>', {
                        'value': this.id,
                        'text': this.title
                    }).appendTo(section);
                });
            }
        });
    });

    // Toggle view mode
    function toggleView(mode) {
        var tables = document.querySelectorAll('.results-matrix');
        if (mode === 'compact') {
            tables.forEach(function(table) { table.classList.add('table-compact'); });
            document.querySelectorAll('.att-col, .ca-col, .ex-col').forEach(function(el) {
                el.style.display = 'none';
            });
        } else {
            tables.forEach(function(table) { table.classList.remove('table-compact'); });
            document.querySelectorAll('.att-col, .ca-col, .ex-col').forEach(function(el) {
                el.style.display = '';
            });
        }
    }
</script>
@endsection

@section('page_css')
<style>
    /* Sticky columns for horizontal scroll */
    .sticky-col {
        position: sticky;
        background: #fff;
        z-index: 5;
    }
    
    /* Ensure table headers are sticky on vertical scroll */
    .results-matrix thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    /* Compact mode styles */
    .table-compact td, .table-compact th {
        padding: 0.2rem !important;
        font-size: 0.75em !important;
    }
    
    /* Print styles */
    @media print {
        .no-print {
            display: none !important;
        }
        
        body {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
        
        .sticky-col {
            position: static !important;
        }
        
        .results-matrix {
            font-size: 0.65em !important;
        }
        
        .results-matrix thead th {
            position: static !important;
            background: #343a40 !important;
            color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .results-matrix .course-header {
            background: #495057 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .results-matrix .sub-header {
            background: #6c757d !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .table-responsive {
            max-height: none !important;
            overflow: visible !important;
        }
        
        .page-wrapper {
            padding: 0 !important;
        }
        
        .main-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .badge {
            border: 1px solid #000 !important;
            padding: 2px 4px !important;
        }
        
        .badge-success {
            background: #28a745 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .badge-danger {
            background: #dc3545 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .badge-warning {
            background: #ffc107 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .text-success {
            color: #28a745 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        .text-danger {
            color: #dc3545 !important;
            -webkit-print-color-adjust: exact !important;
        }
        
        /* Ensure table fits on page */
        table {
            width: 100% !important;
            table-layout: auto !important;
        }
        
        td, th {
            padding: 2px 3px !important;
        }
        
        /* Page break settings */
        .printable-summary {
            page-break-after: avoid;
        }
        
        @page {
            size: landscape;
            margin: 0.5cm;
        }
    }
    
    /* Course header styling */
    .course-header {
        white-space: nowrap;
    }
    
    /* Badge adjustments */
    .badge {
        font-size: 0.75em;
    }
    
    /* Table row hover */
    .results-matrix tbody tr:hover {
        background-color: #f5f5f5 !important;
    }
    
    /* Scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        height: 10px;
        width: 10px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 5px;
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
</style>
@endsection
