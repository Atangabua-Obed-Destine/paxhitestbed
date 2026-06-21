@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content -->
<div class="main-body">
    <div class="page-wrapper">
        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-title">
                <h4 class="m-b-10">{{ $title }}</h4>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item">{{ trans_choice('module_admission', 2) }}</li>
                <li class="breadcrumb-item">{{ $title }}</li>
            </ul>
        </div>

        <!-- Page body -->
        <div class="page-body">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    
                    @if(session()->has('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <strong>{{ __('status_success') }}!</strong> {{ session()->get('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    @endif

                    @if(session()->has('error'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <strong>{{ __('status_error') }}!</strong> {{ session()->get('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    @endif

                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-filter m-r-5"></i> {{ __('field_filter') }}</h5>
                        </div>
                        <div class="card-block">
                            <form class="needs-validation" novalidate method="get" action="{{ route('admin.student-form-a2.index') }}">
                                <div class="row">
                                    <!-- Faculty -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="faculty">{{ trans_choice('module_faculty', 1) }} <span class="text-danger">*</span></label>
                                            <select class="form-control" name="faculty" id="faculty" required>
                                                <option value="">{{ __('select') }}</option>
                                                @foreach($faculties as $faculty)
                                                <option value="{{ $faculty->id }}" @if($selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                                @endforeach
                                            </select>
                                            <div class="invalid-feedback">{{ __('required_field') }} {{ trans_choice('module_faculty', 1) }}</div>
                                        </div>
                                    </div>

                                    <!-- Program -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="program">{{ trans_choice('module_program', 1) }} <span class="text-danger">*</span></label>
                                            <select class="form-control" name="program" id="program" required>
                                                <option value="">{{ __('select') }}</option>
                                                @foreach($programs as $program)
                                                <option value="{{ $program->id }}" @if($selected_program == $program->id) selected @endif>{{ $program->title }}</option>
                                                @endforeach
                                            </select>
                                            <div class="invalid-feedback">{{ __('required_field') }} {{ trans_choice('module_program', 1) }}</div>
                                        </div>
                                    </div>

                                    <!-- Buttons -->
                                    <div class="col-md-4">
                                        <div class="form-group" style="padding-top: 30px;">
                                            <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                            <a href="{{ route('admin.student-form-a2.index') }}" class="btn btn-danger btn-sm"><i class="fas fa-redo"></i> {{ __('btn_reset') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-file-alt m-r-5"></i> Student Form A2 List</h5>
                            <div class="card-header-right">
                                <button type="button" class="btn btn-primary btn-sm" id="bulkPreviewBtn" disabled>
                                    <i class="fas fa-eye"></i> Bulk Preview
                                </button>
                                <button type="button" class="btn btn-success btn-sm" id="bulkPrintBtn" disabled>
                                    <i class="fas fa-print"></i> Bulk Print
                                </button>
                            </div>
                        </div>
                        <div class="card-block">
                            <form id="bulkForm" action="{{ route('admin.student-form-a2.bulk') }}" method="POST" target="_blank">
                                @csrf
                                <input type="hidden" name="preview" id="previewInput" value="0">
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered" id="dataTable">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th width="40">
                                                    <input type="checkbox" id="selectAll">
                                                </th>
                                                <th>#</th>
                                                <th>{{ __('Matricule') }}</th>
                                                <th>{{ __('field_name') }}</th>
                                                <th>{{ trans_choice('module_program', 1) }}</th>
                                                <th>{{ trans_choice('module_semester', 1) }}</th>
                                                <th>{{ trans_choice('module_session', 1) }}</th>
                                                <th class="text-center">{{ __('field_action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($enrollments as $key => $enrollment)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="enrollment_ids[]" value="{{ $enrollment->id }}" class="enrollment-checkbox">
                                                </td>
                                                <td>{{ $key + 1 }}</td>
                                                <td>
                                                    <strong>{{ $enrollment->matricule ?? 'N/A' }}</strong>
                                                    @if($enrollment->status == 1)
                                                        <span class="badge badge-success" title="Enrollment Active">Active</span>
                                                    @elseif($enrollment->status == 2)
                                                        <span class="badge badge-info" title="Course Completed">Completed</span>
                                                    @else
                                                        <span class="badge badge-secondary" title="Enrollment Inactive">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <strong>{{ $enrollment->student->first_name ?? '' }} {{ $enrollment->student->last_name ?? '' }}</strong>
                                                    <br><small class="text-muted">{{ $enrollment->student->email ?? '' }}</small>
                                                </td>
                                                <td>{{ $enrollment->program->shortcode ?? $enrollment->program->title ?? 'N/A' }}</td>
                                                <td>{{ $enrollment->semester->title ?? 'N/A' }}</td>
                                                <td>{{ $enrollment->session->title ?? 'N/A' }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('admin.student-form-a2.preview', $enrollment->id) }}" 
                                                       class="btn btn-info btn-sm" target="_blank" title="Preview">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.student-form-a2.download', $enrollment->id) }}" 
                                                       class="btn btn-success btn-sm" target="_blank" title="Print">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">{{ __('status_no_data') }}</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Content -->

@endsection

@section('page_js')
<script type="text/javascript">
$(document).ready(function() {
    // Initialize DataTable
    $('#dataTable').DataTable({
        "order": [[2, "asc"]],
        "pageLength": 25,
        "columnDefs": [
            { "orderable": false, "targets": [0, 7] }
        ]
    });
    
    // Dynamic faculty -> program loading
    $('#faculty').on('change', function() {
        var facultyId = $(this).val();
        var programSelect = $('#program');
        
        // Clear current options
        programSelect.html('<option value="">{{ __("select") }}</option>');
        
        if (facultyId) {
            $.ajax({
                url: "{{ route('admin.program-semester-fee.get-programs') }}",
                method: 'GET',
                data: { faculty_id: facultyId },
                dataType: 'json',
                success: function(programs) {
                    $.each(programs, function(index, program) {
                        programSelect.append('<option value="' + program.id + '">' + program.title + '</option>');
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading programs:', error);
                }
            });
        }
    });
    
    // Select all checkbox
    $('#selectAll').on('change', function() {
        $('.enrollment-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButtons();
    });
    
    // Individual checkbox change
    $(document).on('change', '.enrollment-checkbox', function() {
        updateBulkButtons();
        
        // Update select all state
        var totalCheckboxes = $('.enrollment-checkbox').length;
        var checkedCheckboxes = $('.enrollment-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
    
    // Update bulk button states
    function updateBulkButtons() {
        var checkedCount = $('.enrollment-checkbox:checked').length;
        $('#bulkPreviewBtn, #bulkPrintBtn').prop('disabled', checkedCount === 0);
    }
    
    // Bulk Preview
    $('#bulkPreviewBtn').on('click', function() {
        $('#previewInput').val('1');
        $('#bulkForm').submit();
    });
    
    // Bulk Print
    $('#bulkPrintBtn').on('click', function() {
        $('#previewInput').val('0');
        $('#bulkForm').submit();
    });
});
</script>
@endsection
