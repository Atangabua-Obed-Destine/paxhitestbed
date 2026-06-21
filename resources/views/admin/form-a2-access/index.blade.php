@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Alert for students with no fee assigned -->
            @if($total_not_assigned > 0)
            <div class="col-12">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong><i class="fas fa-exclamation-triangle"></i> Configuration Issue:</strong> 
                    {{ $total_not_assigned }} student(s) have no First Installment fee assigned. 
                    These students may need fee records created in <a href="{{ route('admin.fees-master.index') }}" class="alert-link">Fees Master</a> 
                    or check if <a href="{{ route('admin.program-semester-fee.index') }}" class="alert-link">Program Semester Fee</a> is configured for their program.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            @endif

            <!-- KPIs -->
            <div class="col-md-2">
                <div class="card text-white bg-primary">
                    <div class="card-body py-2">
                        <h6 class="text-white mb-0">Total (Y1S1)</h6>
                        <h3 class="text-white mb-0">{{ $total_students }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-danger">
                    <div class="card-body py-2">
                        <h6 class="text-white mb-0">Unpaid</h6>
                        <h3 class="text-white mb-0">{{ $total_unpaid }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-warning">
                    <div class="card-body py-2">
                        <h6 class="text-white mb-0">Partial</h6>
                        <h3 class="text-white mb-0">{{ $total_partial }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-success">
                    <div class="card-body py-2">
                        <h6 class="text-white mb-0">Paid</h6>
                        <h3 class="text-white mb-0">{{ $total_paid }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-info">
                    <div class="card-body py-2">
                        <h6 class="text-white mb-0">Bypassed</h6>
                        <h3 class="text-white mb-0">{{ $total_bypassed }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-secondary">
                    <div class="card-body py-2">
                        <h6 class="text-white mb-0">No Fee</h6>
                        <h3 class="text-white mb-0">{{ $total_not_assigned }}</h3>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- Filter Form -->
                        <form class="needs-validation" novalidate method="get" action="{{ route('admin.form-a2-access.index') }}">
                            <div class="row gx-2">
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans_choice('module_session', 1) }}</label>
                                    <select class="form-control" name="session_id" id="session_id">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach($sessions as $session)
                                        <option value="{{ $session->id }}" @if($selected_session == $session->id) selected @endif>{{ $session->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans_choice('module_faculty', 1) }}</label>
                                    <select class="form-control" name="faculty_id" id="faculty_id">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" @if($selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans_choice('module_program', 1) }}</label>
                                    <select class="form-control" name="program_id" id="program_id">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach($programs as $program)
                                        <option value="{{ $program->id }}" @if($selected_program == $program->id) selected @endif>{{ $program->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Payment Status</label>
                                    <select class="form-control" name="payment_status">
                                        <option value="">{{ __('all') }}</option>
                                        <option value="Unpaid" @if($selected_payment_status == 'Unpaid') selected @endif>Unpaid</option>
                                        <option value="Partial" @if($selected_payment_status == 'Partial') selected @endif>Partial</option>
                                        <option value="Paid" @if($selected_payment_status == 'Paid') selected @endif>Paid</option>
                                        <option value="Not Assigned" @if($selected_payment_status == 'Not Assigned') selected @endif>Not Assigned</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Access Status</label>
                                    <select class="form-control" name="bypass_status">
                                        <option value="">{{ __('all') }}</option>
                                        <option value="1" @if($selected_bypass == '1') selected @endif>Access Granted</option>
                                        <option value="0" @if($selected_bypass == '0') selected @endif>Access Restricted</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                    <a href="{{ route('admin.form-a2-access.index') }}" class="btn btn-danger btn-sm"><i class="fas fa-redo"></i> {{ __('btn_reset') }}</a>
                                </div>
                            </div>
                        </form>

                        <!-- Bulk Actions -->
                        <div class="mt-3 mb-2">
                            <form id="bulkForm" action="{{ route('admin.form-a2-access.bulk-update') }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" id="bulk_status" value="">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-success btn-sm" id="btnBulkGrant" disabled>
                                        <i class="fas fa-unlock"></i> Grant Selected (<span id="selectedCount">0</span>)
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" id="btnBulkRevoke" disabled>
                                        <i class="fas fa-lock"></i> Revoke Selected
                                    </button>
                                </div>
                                <div class="d-inline-block ml-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnSelectAll">
                                        <i class="fas fa-check-square"></i> Select All
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDeselectAll">
                                        <i class="fas fa-square"></i> Deselect All
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive mt-2">
                            <table id="report-table" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" id="checkAll" class="form-check-input">
                                        </th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ trans_choice('module_program', 1) }}</th>
                                        <th>{{ trans_choice('module_session', 1) }}</th>
                                        <th>Payment Status</th>
                                        <th>Paid / Total</th>
                                        <th>Payment Plan</th>
                                        <th>Access Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input row-checkbox" 
                                                   name="enrollment_ids[]" 
                                                   value="{{ $row->id }}" 
                                                   form="bulkForm"
                                                   data-bypassed="{{ $row->bypass_payment_restriction }}">
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.student.show', $row->student->id) }}">
                                                #{{ $row->matricule ?? '' }}
                                            </a>
                                            @if($row->status == 1)
                                                <span class="badge badge-success" title="Enrollment Active">Active</span>
                                            @elseif($row->status == 2)
                                                <span class="badge badge-info" title="Course Completed">Completed</span>
                                            @else
                                                <span class="badge badge-secondary" title="Enrollment Inactive">Inactive</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td>{{ $row->program->shortcode ?? '' }}</td>
                                        <td><small>{{ $row->session->title ?? '' }}</small></td>
                                        <td>
                                            @if($row->payment_status == 'Paid')
                                                <span class="badge badge-success">Paid</span>
                                            @elseif($row->payment_status == 'Partial')
                                                <span class="badge badge-warning">Partial</span>
                                            @elseif($row->payment_status == 'Unpaid')
                                                <span class="badge badge-danger">Unpaid</span>
                                            @else
                                                <span class="badge badge-secondary">{{ $row->payment_status }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ number_format(min((float)$row->paid_amount, (float)$row->total_amount), 0) }} / {{ number_format($row->total_amount, 0) }}
                                        </td>
                                        <td>
                                            @if($row->first_installment_fee && $row->first_installment_fee->paymentPlan)
                                                <a href="{{ route('admin.payment-plan.show', $row->first_installment_fee->paymentPlan->id) }}" target="_blank" class="badge badge-info">
                                                    View Plan
                                                </a>
                                                <br>
                                                <small>
                                                    Status: {{ ucfirst($row->first_installment_fee->paymentPlan->status) }}
                                                </small>
                                            @else
                                                <span class="badge badge-secondary">None</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->bypass_payment_restriction == 1)
                                                <span class="badge badge-success">Granted (Bypassed)</span>
                                            @elseif($row->payment_status == 'Paid')
                                                <span class="badge badge-success">Granted (Paid)</span>
                                            @else
                                                <span class="badge badge-danger">Restricted</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->bypass_payment_restriction == 1)
                                                {{-- Bypassed: Show revoke option --}}
                                                <form action="{{ route('admin.form-a2-access.update', $row->id) }}" method="post" style="display: inline;">
                                                    @csrf
                                                    <input type="hidden" name="status" value="0">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to revoke access?')">
                                                        <i class="fas fa-lock"></i> Revoke
                                                    </button>
                                                </form>
                                            @elseif($row->payment_status == 'Paid')
                                                {{-- Paid: No action needed, access is automatic --}}
                                                <span class="text-success"><i class="fas fa-check-circle"></i> Auto</span>
                                            @else
                                                {{-- Not paid and not bypassed: Show grant option --}}
                                                <form action="{{ route('admin.form-a2-access.update', $row->id) }}" method="post" style="display: inline;">
                                                    @csrf
                                                    <input type="hidden" name="status" value="1">
                                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to grant access?')">
                                                        <i class="fas fa-unlock"></i> Grant
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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
<script>
    'use strict';
    $(document).ready(function() {
        // Dynamic Program Filter based on Faculty
        $("#faculty_id").on('change', function(e){
            var faculty_id = $(this).val();
            $.ajax({
                url: "{{ route('admin.program-semester-fee.get-programs') }}",
                type: "POST",
                data: {
                    faculty_id: faculty_id,
                    _token: '{{ csrf_token() }}'
                },
                dataType: 'json',
                success: function(result){
                    $('#program_id').html('<option value="">{{ __("all") }}</option>');
                    $.each(result, function(key, value){
                        $("#program_id").append('<option value="'+value.id+'">'+value.title+'</option>');
                    });
                }
            });
        });

        // Checkbox handling
        function updateSelectedCount() {
            var count = $('.row-checkbox:checked').length;
            $('#selectedCount').text(count);
            
            if (count > 0) {
                $('#btnBulkGrant, #btnBulkRevoke').prop('disabled', false);
            } else {
                $('#btnBulkGrant, #btnBulkRevoke').prop('disabled', true);
            }
        }

        // Check All
        $('#checkAll').on('change', function() {
            $('.row-checkbox').prop('checked', $(this).is(':checked'));
            updateSelectedCount();
        });

        // Individual checkbox
        $(document).on('change', '.row-checkbox', function() {
            updateSelectedCount();
            // Update checkAll state
            if ($('.row-checkbox:checked').length === $('.row-checkbox').length) {
                $('#checkAll').prop('checked', true);
            } else {
                $('#checkAll').prop('checked', false);
            }
        });

        // Select All Button
        $('#btnSelectAll').on('click', function() {
            $('.row-checkbox').prop('checked', true);
            $('#checkAll').prop('checked', true);
            updateSelectedCount();
        });

        // Deselect All Button
        $('#btnDeselectAll').on('click', function() {
            $('.row-checkbox').prop('checked', false);
            $('#checkAll').prop('checked', false);
            updateSelectedCount();
        });

        // Bulk Grant
        $('#btnBulkGrant').on('click', function() {
            var count = $('.row-checkbox:checked').length;
            if (count === 0) {
                alert('Please select at least one student.');
                return;
            }
            if (confirm('Are you sure you want to GRANT access to ' + count + ' student(s)?')) {
                $('#bulk_status').val('1');
                $('#bulkForm').submit();
            }
        });

        // Bulk Revoke
        $('#btnBulkRevoke').on('click', function() {
            var count = $('.row-checkbox:checked').length;
            if (count === 0) {
                alert('Please select at least one student.');
                return;
            }
            if (confirm('Are you sure you want to REVOKE access from ' + count + ' student(s)?')) {
                $('#bulk_status').val('0');
                $('#bulkForm').submit();
            }
        });

        // Initialize count
        updateSelectedCount();
    });
</script>
@endsection
