@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title"><i class="fas fa-chart-bar"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.course-marksheet') }}" class="btn btn-secondary">
                                <i class="fas fa-file-spreadsheet"></i> {{ __('Course Marksheet') }}
                            </a>
                            <a href="{{ route($route.'.student-results-summary') }}" class="btn btn-success">
                                <i class="fas fa-th-list"></i> {{ __('Student Results') }}
                            </a>
                            <a href="{{ route($route.'.department-report') }}" class="btn btn-primary">
                                <i class="fas fa-building"></i> {{ __('Department Report') }}
                            </a>
                            <a href="{{ route($route.'.faculty-report') }}" class="btn btn-info">
                                <i class="fas fa-university"></i> {{ __('Faculty Report') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Filter Results') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-2">
                                    <label for="faculty">{{ __('field_faculty') }} <span class="text-danger">*</span></label>
                                    <select class="form-control faculty" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @if(isset($faculties))
                                        @foreach($faculties->sortBy('title') as $faculty)
                                        <option value="{{ $faculty->id }}" @if($selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="department">{{ __('field_department') }}</label>
                                    <select class="form-control department" name="department" id="department">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($departments))
                                        @foreach($departments->sortBy('title') as $dept)
                                        <option value="{{ $dept->id }}" @if($selected_department == $dept->id) selected @endif>{{ $dept->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control program" name="program" id="program">
                                        <option value="0">{{ __('all') }}</option>
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
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-block btn-filter">
                                        <i class="fas fa-search"></i> {{ __('btn_search') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @include('admin.results-summary.partials.skipped-semesters')
            @foreach($semester_blocks ?? [] as $block)
            @include('admin.results-summary.partials.index-semester', $block)
            @endforeach
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script type="text/javascript">
"use strict";

// Faculty filter change
$(".faculty").on('change', function(e) {
    e.preventDefault();
    var faculty = $(this).val();
    var department = $(".department");
    var program = $(".program");
    
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Load departments
    $.ajax({
        type: 'POST',
        url: "{{ route('filter-academic-department') }}",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            faculty: faculty
        },
        success: function(response) {
            department.empty();
            department.append('<option value="0">{{ __("all") }}</option>');
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.title
                }).appendTo('.department');
            });
        }
    });
    
    // Load programs
    $.ajax({
        type: 'POST',
        url: "{{ route('filter-program') }}",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            faculty: faculty
        },
        success: function(response) {
            program.empty();
            program.append('<option value="0">{{ __("all") }}</option>');
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.title
                }).appendTo('.program');
            });
        }
    });
});

// Program filter change
$(".program").on('change', function(e) {
    e.preventDefault();
    var program = $(this).val();
    var session = $(".session");
    
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Load sessions
    $.ajax({
        type: 'POST',
        url: "{{ route('filter-session') }}",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            program: program
        },
        success: function(response) {
            session.empty();
            session.append('<option value="">{{ __("select") }}</option>');
            $.each(response, function() {
                $('<option/>', {
                    'value': this.id,
                    'text': this.title
                }).appendTo('.session');
            });
        }
    });
});

</script>
@endsection
