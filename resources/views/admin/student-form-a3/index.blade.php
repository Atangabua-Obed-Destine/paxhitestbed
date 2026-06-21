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
                            <form class="needs-validation" novalidate method="get" action="{{ route('admin.student-form-a3.index') }}">
                                <div class="row">
                                    <!-- Faculty -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="faculty">{{ trans_choice('module_faculty', 1) }} <span class="text-danger">*</span></label>
                                            <select class="form-control" name="faculty" id="faculty">
                                                <option value="">{{ __('All') }}</option>
                                                @foreach($faculties as $facultyItem)
                                                <option value="{{ $facultyItem->id }}" @if($selected_faculty == $facultyItem->id) selected @endif>{{ $facultyItem->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Program -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="program">{{ trans_choice('module_program', 1) }} <span class="text-danger">*</span></label>
                                            <select class="form-control" name="program" id="program">
                                                <option value="">{{ __('All') }}</option>
                                                @foreach($programs as $programItem)
                                                <option value="{{ $programItem->id }}" @if($selected_program == $programItem->id) selected @endif>{{ $programItem->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Session -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="session">{{ trans_choice('module_session', 1) }} <span class="text-danger">*</span></label>
                                            <select class="form-control" name="session" id="session">
                                                <option value="">{{ __('All') }}</option>
                                                @foreach($sessions as $sessionItem)
                                                <option value="{{ $sessionItem->id }}" @if($selected_session == $sessionItem->id) selected @endif>{{ $sessionItem->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Semester -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="semester">{{ trans_choice('module_semester', 1) }} <span class="text-danger">*</span></label>
                                            <select class="form-control" name="semester" id="semester">
                                                <option value="">{{ __('All') }}</option>
                                                @foreach($semesters as $semesterItem)
                                                <option value="{{ $semesterItem->id }}" @if($selected_semester == $semesterItem->id) selected @endif>{{ $semesterItem->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                        <a href="{{ route('admin.student-form-a3.index') }}" class="btn btn-danger btn-sm"><i class="fas fa-redo"></i> {{ __('btn_reset') }}</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-file-alt m-r-5"></i> Student Form A3 List</h5>
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
                            <form id="bulkForm" action="{{ route('admin.student-form-a3.bulk') }}" method="POST" target="_blank">
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
                                                <th>{{ trans_choice('module_session', 1) }}</th>
                                                <th>{{ trans_choice('module_semester', 1) }}</th>
                                                <th>{{ __('Credits') }}</th>
                                                <th class="text-center">{{ __('field_action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($records as $key => $record)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="record_ids[]" value="{{ $record->id }}" class="record-checkbox">
                                                </td>
                                                <td>{{ $key + 1 }}</td>
                                                <td><strong>{{ $record->enrollment->matricule ?? 'N/A' }}</strong></td>
                                                <td>
                                                    <strong>{{ $record->student->first_name ?? '' }} {{ $record->student->last_name ?? '' }}</strong>
                                                    <br><small class="text-muted">{{ $record->student->email ?? '' }}</small>
                                                </td>
                                                <td>{{ $record->enrollment->program->shortcode ?? $record->enrollment->program->title ?? 'N/A' }}</td>
                                                <td>{{ $record->session->title ?? 'N/A' }}</td>
                                                <td>{{ $record->semester->title ?? 'N/A' }}</td>
                                                <td class="text-center">{{ $record->total_credits }}</td>
                                                <td class="text-center">
                                                    @php
                                                        $blockKey = ($record->student_id ?? '') . '|' . ($record->enrollment->program_id ?? '') . '|' . ($record->session_id ?? '') . '|' . ($record->semester_id ?? '');
                                                        $activeBlock = $blockMap[$blockKey] ?? null;
                                                    @endphp
                                                    <a href="{{ route('admin.student-form-a3.preview', $record->id) }}" 
                                                       class="btn btn-info btn-sm" target="_blank" title="Preview">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.student-form-a3.download', $record->id) }}" 
                                                       class="btn btn-success btn-sm" target="_blank" title="Print">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    @if($activeBlock)
                                                        <button type="button"
                                                                class="btn btn-warning btn-sm toggle-access-btn"
                                                                title="Results currently BLOCKED — click to restore"
                                                                data-record-id="{{ $record->id }}"
                                                                data-action-url="{{ route('admin.student-form-a3.toggle-result-access', $record->id) }}"
                                                                data-mode="unblock"
                                                                data-student-name="{{ trim(($record->student->first_name ?? '') . ' ' . ($record->student->last_name ?? '')) }}"
                                                                data-context="{{ ($record->enrollment->program->shortcode ?? $record->enrollment->program->title ?? '') . ' / ' . ($record->session->title ?? '') . ' / ' . ($record->semester->title ?? '') }}"
                                                                data-current-reason="{{ $activeBlock->reason }}">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                                class="btn btn-outline-secondary btn-sm toggle-access-btn"
                                                                title="Block results for this semester"
                                                                data-record-id="{{ $record->id }}"
                                                                data-action-url="{{ route('admin.student-form-a3.toggle-result-access', $record->id) }}"
                                                                data-mode="block"
                                                                data-student-name="{{ trim(($record->student->first_name ?? '') . ' ' . ($record->student->last_name ?? '')) }}"
                                                                data-context="{{ ($record->enrollment->program->shortcode ?? $record->enrollment->program->title ?? '') . ' / ' . ($record->session->title ?? '') . ' / ' . ($record->semester->title ?? '') }}">
                                                            <i class="fas fa-lock-open"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">{{ __('status_no_data') }}</td>
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
            { "orderable": false, "targets": [0, 8] }
        ]
    });
    
    // Dynamic faculty -> program loading
    $('#faculty').on('change', function() {
        var facultyId = $(this).val();
        var programSelect = $('#program');
        
        // Clear current options
        programSelect.html('<option value="">{{ __("All") }}</option>');
        
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
        $('.record-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButtons();
    });
    
    // Individual checkbox change
    $(document).on('change', '.record-checkbox', function() {
        updateBulkButtons();
        
        // Update select all state
        var totalCheckboxes = $('.record-checkbox').length;
        var checkedCheckboxes = $('.record-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
    
    // Update bulk button states
    function updateBulkButtons() {
        var checkedCount = $('.record-checkbox:checked').length;
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

    // Toggle result-access modal
    $(document).on('click', '.toggle-access-btn', function() {
        var $btn = $(this);
        var mode    = $btn.data('mode');                  // 'block' or 'unblock'
        var url     = $btn.data('action-url');
        var name    = $btn.data('student-name') || 'this student';
        var ctx     = $btn.data('context') || '';
        var current = $btn.data('current-reason') || '';

        $('#toggleAccessForm').attr('action', url);
        $('#toggleAccessMode').val(mode);
        $('#toggleAccessTargetName').text(name);
        $('#toggleAccessTargetCtx').text(ctx);

        if (mode === 'block') {
            $('#toggleAccessModalLabel').text('Block Result Access');
            $('#toggleAccessIntro').html('You are about to <strong class="text-danger">BLOCK</strong> result viewing for this student / semester. Please provide a reason — the student will not see results anywhere on the portal until access is restored.');
            $('#toggleAccessReason').attr('required', 'required').val('');
            $('#toggleAccessReasonLabel').html('Reason <span class="text-danger">*</span>');
            $('#toggleAccessSubmitBtn').removeClass('btn-success').addClass('btn-danger').html('<i class="fas fa-lock"></i> Block Access');
            $('#toggleAccessCurrentBlock').addClass('d-none');
        } else {
            $('#toggleAccessModalLabel').text('Restore Result Access');
            $('#toggleAccessIntro').html('You are about to <strong class="text-success">RESTORE</strong> result viewing for this student / semester. They will immediately be able to see their results again.');
            $('#toggleAccessReason').removeAttr('required').val('');
            $('#toggleAccessReasonLabel').text('Note (optional)');
            $('#toggleAccessSubmitBtn').removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-lock-open"></i> Restore Access');
            $('#toggleAccessCurrentReason').text(current || '(no reason recorded)');
            $('#toggleAccessCurrentBlock').removeClass('d-none');
        }
        $('#toggleAccessModal').modal('show');
    });
});
</script>

<!-- Toggle Result Access Modal -->
<div class="modal fade" id="toggleAccessModal" tabindex="-1" role="dialog" aria-labelledby="toggleAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="toggleAccessForm" method="POST" action="">
            @csrf
            <input type="hidden" name="mode" id="toggleAccessMode" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="toggleAccessModalLabel">Toggle Result Access</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="toggleAccessIntro"></p>
                    <div class="alert alert-info py-2 mb-3">
                        <strong>Student:</strong> <span id="toggleAccessTargetName"></span><br>
                        <strong>Context:</strong> <span id="toggleAccessTargetCtx"></span>
                    </div>
                    <div id="toggleAccessCurrentBlock" class="alert alert-warning py-2 mb-3 d-none">
                        <strong>Existing block reason:</strong>
                        <div id="toggleAccessCurrentReason" class="mt-1"></div>
                    </div>
                    <div class="form-group">
                        <label for="toggleAccessReason" id="toggleAccessReasonLabel">Reason</label>
                        <textarea name="reason" id="toggleAccessReason" class="form-control" rows="3" placeholder="e.g. Outstanding tuition balance, disciplinary hold, missing exam attendance..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="toggleAccessSubmitBtn">
                        <i class="fas fa-lock"></i> Confirm
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
