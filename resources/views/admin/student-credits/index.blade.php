@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-blue">{{ $stats['total_credits'] ?? 0 }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('total_credits') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-credit-card f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-green">{{ $stats['available_credits'] ?? 0 }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('available_credits') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-check-circle f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-yellow">{{ $stats['pending_refunds'] ?? 0 }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('pending_refunds') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-clock f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card bg-c-blue text-white">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_credit_amount') }}</h6>
                        <h3 class="text-white">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)($stats['total_amount'] ?? 0), $setting->decimal_place, '.', ',') }}
                            @else
                            {{ number_format((float)($stats['total_amount'] ?? 0), 2, '.', ',') }}
                            @endif
                            {{ $setting->currency ?? 'GHS' }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- Filter Form -->
                        <form method="get" action="{{ route('admin.student-credits.index') }}" class="mb-4">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label>{{ __('search_student') }}</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="{{ __('student_name_or_id') }}" 
                                           value="{{ request('search') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label>{{ __('status') }}</label>
                                    <select name="status" class="form-control">
                                        <option value="">{{ __('all_statuses') }}</option>
                                        <option value="available" {{ request('status') == 'available' ? 'selected' : '' }}>{{ __('available') }}</option>
                                        <option value="partially_applied" {{ request('status') == 'partially_applied' ? 'selected' : '' }}>{{ __('partially_applied') }}</option>
                                        <option value="fully_applied" {{ request('status') == 'fully_applied' ? 'selected' : '' }}>{{ __('fully_applied') }}</option>
                                        <option value="refund_pending" {{ request('status') == 'refund_pending' ? 'selected' : '' }}>{{ __('refund_pending') }}</option>
                                        <option value="refund_approved" {{ request('status') == 'refund_approved' ? 'selected' : '' }}>{{ __('refund_approved') }}</option>
                                        <option value="refund_rejected" {{ request('status') == 'refund_rejected' ? 'selected' : '' }}>{{ __('refund_rejected') }}</option>
                                        <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>{{ __('refunded') }}</option>
                                        <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>{{ __('expired') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label>{{ __('source_type') }}</label>
                                    <select name="source_type" class="form-control">
                                        <option value="">{{ __('all_sources') }}</option>
                                        <option value="overpayment" {{ request('source_type') == 'overpayment' ? 'selected' : '' }}>{{ __('overpayment') }}</option>
                                        <option value="admin_adjustment" {{ request('source_type') == 'admin_adjustment' ? 'selected' : '' }}>{{ __('adjustment') }}</option>
                                        <option value="refund_reversal" {{ request('source_type') == 'refund_reversal' ? 'selected' : '' }}>{{ __('refund_reversal') }}</option>
                                        <option value="transfer" {{ request('source_type') == 'transfer' ? 'selected' : '' }}>{{ __('transfer') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label>{{ __('date_from') }}</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label>{{ __('date_to') }}</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                                <div class="col-md-1 mb-2">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="feather icon-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Credits Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered nowrap">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('student') }}</th>
                                        <th>{{ __('original_amount') }}</th>
                                        <th>{{ __('remaining_amount') }}</th>
                                        <th>{{ __('source') }}</th>
                                        <th>{{ __('status') }}</th>
                                        <th>{{ __('created_at') }}</th>
                                        <th>{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($credits as $index => $credit)
                                    <tr>
                                        <td>{{ $credits->firstItem() + $index }}</td>
                                        <td>
                                            @if($credit->student)
                                                <strong>{{ $credit->student->first_name }} {{ $credit->student->last_name }}</strong><br>
                                                <small class="text-muted">{{ $credit->student->student_id ?? '' }}</small>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ number_format($credit->original_amount, $setting->decimal_place ?? 2) }}
                                            {{ $setting->currency ?? 'GHS' }}
                                        </td>
                                        <td>
                                            <span class="{{ $credit->remaining_amount > 0 ? 'text-success font-weight-bold' : 'text-muted' }}">
                                                {{ number_format($credit->remaining_amount, $setting->decimal_place ?? 2) }}
                                                {{ $setting->currency ?? 'GHS' }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($credit->source_type == 'overpayment')
                                                <span class="badge badge-info">{{ __('overpayment') }}</span>
                                                @if($credit->sourceFee)
                                                    <br><small class="text-muted">{{ $credit->sourceFee->category->title ?? '' }}</small>
                                                @endif
                                            @elseif($credit->source_type == 'admin_adjustment')
                                                <span class="badge badge-warning">{{ __('adjustment') }}</span>
                                            @else
                                                <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $credit->source_type)) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @switch($credit->status)
                                                @case('available')
                                                    <span class="badge badge-success">{{ __('available') }}</span>
                                                    @break
                                                @case('partially_applied')
                                                    <span class="badge badge-info">{{ __('partially_applied') }}</span>
                                                    @break
                                                @case('fully_applied')
                                                    <span class="badge badge-primary">{{ __('fully_applied') }}</span>
                                                    @break
                                                @case('refunded')
                                                    <span class="badge badge-secondary">{{ __('refunded') }}</span>
                                                    @break
                                                @case('expired')
                                                    <span class="badge badge-dark">{{ __('expired') }}</span>
                                                    @break
                                                @default
                                                    <span class="badge badge-light">{{ ucfirst(str_replace('_', ' ', $credit->status)) }}</span>
                                            @endswitch
                                            @switch($credit->refund_state)
                                                @case('requested')
                                                    <span class="badge badge-warning">{{ __('refund_pending') }}</span>
                                                    @break
                                                @case('approved')
                                                    <span class="badge badge-info">{{ __('refund_approved') }}</span>
                                                    @break
                                                @case('rejected')
                                                    <span class="badge badge-danger">{{ __('refund_rejected') }}</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>{{ $credit->created_at->format('M d, Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('admin.student-credits.show', $credit->id) }}" 
                                               class="btn btn-sm btn-info" title="{{ __('view_details') }}">
                                                <i class="feather icon-eye"></i>
                                            </a>

                                            @if($credit->refund_state === 'requested')
                                                <button type="button" class="btn btn-sm btn-success approve-refund-btn" 
                                                        data-id="{{ $credit->id }}"
                                                        data-student="{{ $credit->student_full_name }}"
                                                        data-amount="{{ $credit->remaining_amount }}"
                                                        title="{{ __('approve_refund') }}">
                                                    <i class="feather icon-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger reject-refund-btn"
                                                        data-id="{{ $credit->id }}"
                                                        data-student="{{ $credit->student_full_name }}"
                                                        title="{{ __('reject_refund') }}">
                                                    <i class="feather icon-x"></i>
                                                </button>
                                            @endif

                                            @if($credit->refund_state === 'approved')
                                                <button type="button" class="btn btn-sm btn-primary process-refund-btn"
                                                        data-id="{{ $credit->id }}"
                                                        data-student="{{ $credit->student_full_name }}"
                                                        data-amount="{{ $credit->remaining_amount }}"
                                                        title="{{ __('process_refund') }}">
                                                    <i class="feather icon-dollar-sign"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('no_credits_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $credits->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>

<!-- Approve Refund Modal -->
<div class="modal fade" id="approveRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('approve_refund') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="approveRefundForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>{{ __('student') }}:</strong> <span id="approveStudentName"></span><br>
                        <strong>{{ __('refund_amount') }}:</strong> 
                        <span id="approveAmount"></span> {{ $setting->currency ?? 'GHS' }}
                    </div>
                    <div class="form-group">
                        <label>{{ __('approval_note') }}</label>
                        <textarea name="note" class="form-control" rows="3" 
                                  placeholder="{{ __('optional_approval_note') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-success">{{ __('approve_refund') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Refund Modal -->
<div class="modal fade" id="rejectRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('reject_refund') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="rejectRefundForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>{{ __('student') }}:</strong> <span id="rejectStudentName"></span>
                    </div>
                    <div class="form-group">
                        <label>{{ __('rejection_reason') }} <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="{{ __('please_provide_reason') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('reject_refund') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Process Refund Modal -->
<div class="modal fade" id="processRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('process_refund') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="processRefundForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>{{ __('student') }}:</strong> <span id="processStudentName"></span><br>
                        <strong>{{ __('refund_amount') }}:</strong> 
                        <span id="processAmount"></span> {{ $setting->currency ?? 'GHS' }}
                    </div>
                    <div class="form-group">
                        <label>{{ __('refund_method') }} <span class="text-danger">*</span></label>
                        <select name="refund_method" class="form-control" required>
                            <option value="">{{ __('select_method') }}</option>
                            <option value="cash">{{ __('cash') }}</option>
                            <option value="bank_transfer">{{ __('bank_transfer') }}</option>
                            <option value="mobile_money">{{ __('mobile_money') }}</option>
                            <option value="cheque">{{ __('cheque') }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('reference_number') }}</label>
                        <input type="text" name="refund_reference" class="form-control" 
                               placeholder="{{ __('transaction_reference') }}">
                    </div>
                    <div class="form-group">
                        <label>{{ __('notes') }}</label>
                        <textarea name="note" class="form-control" rows="2" 
                                  placeholder="{{ __('optional_notes') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('complete_refund') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page-js')
<script>
$(document).ready(function() {
    // Approve Refund
    $('.approve-refund-btn').click(function() {
        var id = $(this).data('id');
        var student = $(this).data('student');
        var amount = $(this).data('amount');
        
        $('#approveStudentName').text(student);
        $('#approveAmount').text(parseFloat(amount).toFixed({{ $setting->decimal_place ?? 2 }}));
        $('#approveRefundForm').attr('action', '{{ url("admin/student-credits") }}/' + id + '/approve-refund');
        $('#approveRefundModal').modal('show');
    });
    
    // Reject Refund
    $('.reject-refund-btn').click(function() {
        var id = $(this).data('id');
        var student = $(this).data('student');
        
        $('#rejectStudentName').text(student);
        $('#rejectRefundForm').attr('action', '{{ url("admin/student-credits") }}/' + id + '/reject-refund');
        $('#rejectRefundModal').modal('show');
    });
    
    // Process Refund
    $('.process-refund-btn').click(function() {
        var id = $(this).data('id');
        var student = $(this).data('student');
        var amount = $(this).data('amount');
        
        $('#processStudentName').text(student);
        $('#processAmount').text(parseFloat(amount).toFixed({{ $setting->decimal_place ?? 2 }}));
        $('#processRefundForm').attr('action', '{{ url("admin/student-credits") }}/' + id + '/process-refund');
        $('#processRefundModal').modal('show');
    });
});
</script>
@endsection
