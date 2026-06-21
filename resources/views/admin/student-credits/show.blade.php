@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Back Button -->
            <div class="col-12 mb-3">
                <a href="{{ route('admin.student-credits.index') }}" class="btn btn-secondary">
                    <i class="feather icon-arrow-left"></i> {{ __('back_to_list') }}
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Credit Details Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('credit_details') }}</h5>
                    </div>
                    <div class="card-block">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ __('credit_id') }}</th>
                                <td>#{{ $credit->id }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('student') }}</th>
                                <td>
                                    <strong>{{ $credit->student_full_name }}</strong><br>
                                    <small class="text-muted">{{ $credit->student->student_id ?? '' }}</small>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('original_amount') }}</th>
                                <td class="font-weight-bold">
                                    {{ number_format($credit->original_amount, $setting->decimal_place ?? 2) }}
                                    {{ $setting->currency ?? 'GHS' }}
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('remaining_amount') }}</th>
                                <td>
                                    <span class="{{ $credit->remaining_amount > 0 ? 'text-success font-weight-bold' : 'text-muted' }}">
                                        {{ number_format($credit->remaining_amount, $setting->decimal_place ?? 2) }}
                                        {{ $setting->currency ?? 'GHS' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('applied_amount') }}</th>
                                <td>
                                    {{ number_format($credit->original_amount - $credit->remaining_amount, $setting->decimal_place ?? 2) }}
                                    {{ $setting->currency ?? 'GHS' }}
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('source_type') }}</th>
                                <td>
                                    @if($credit->source_type == 'overpayment')
                                        <span class="badge badge-info">{{ __('overpayment') }}</span>
                                    @elseif($credit->source_type == 'admin_adjustment')
                                        <span class="badge badge-warning">{{ __('adjustment') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $credit->source_type)) }}</span>
                                    @endif
                                </td>
                            </tr>
                            @if($credit->sourceFee)
                            <tr>
                                <th>{{ __('source_fee') }}</th>
                                <td>
                                    {{ $credit->sourceFee->category->title ?? 'N/A' }}<br>
                                    <small class="text-muted">
                                        {{ optional($credit->sourceFee->studentEnroll->session ?? null)->title ?? '' }}
                                        @if(optional($credit->sourceFee->studentEnroll->semester ?? null)->title)
                                            - {{ $credit->sourceFee->studentEnroll->semester->title }}
                                        @endif
                                    </small>
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <th>{{ __('status') }}</th>
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
                                            <span class="badge badge-warning ml-1">{{ __('refund_pending') }}</span>
                                            @break
                                        @case('approved')
                                            <span class="badge badge-info ml-1">{{ __('refund_approved') }}</span>
                                            @break
                                        @case('rejected')
                                            <span class="badge badge-danger ml-1">{{ __('refund_rejected') }}</span>
                                            @break
                                    @endswitch
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('created_at') }}</th>
                                <td>{{ $credit->created_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('created_by') }}</th>
                                <td>{{ $credit->createdBy->name ?? 'System' }}</td>
                            </tr>
                            @if($credit->note)
                            <tr>
                                <th>{{ __('notes') }}</th>
                                <td>{{ $credit->note }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            <!-- Refund Info Card (if applicable) -->
            <div class="col-md-6">
                @if(in_array($credit->refund_state, ['requested', 'approved', 'processed']))
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="text-white">{{ __('refund_information') }}</h5>
                    </div>
                    <div class="card-block">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ __('refund_status') }}</th>
                                <td>
                                    @switch($credit->refund_state)
                                        @case('requested')
                                            <span class="badge badge-warning">{{ __('pending_approval') }}</span>
                                            @break
                                        @case('approved')
                                            <span class="badge badge-info">{{ __('approved_awaiting_processing') }}</span>
                                            @break
                                        @case('processed')
                                            <span class="badge badge-success">{{ __('completed') }}</span>
                                            @break
                                    @endswitch
                                </td>
                            </tr>
                            @if($credit->refund_requested_at)
                            <tr>
                                <th>{{ __('requested_at') }}</th>
                                <td>{{ $credit->refund_requested_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                            @endif
                            @if($credit->refundRequestedBy)
                            <tr>
                                <th>{{ __('requested_by') }}</th>
                                <td>{{ $credit->refundRequestedBy->name ?? 'N/A' }}</td>
                            </tr>
                            @endif
                            @if($credit->refund_approved_at)
                            <tr>
                                <th>{{ __('approved_at') }}</th>
                                <td>{{ $credit->refund_approved_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                            @endif
                            @if($credit->refundApprovedBy)
                            <tr>
                                <th>{{ __('approved_by') }}</th>
                                <td>{{ $credit->refundApprovedBy->name ?? 'N/A' }}</td>
                            </tr>
                            @endif
                            @if($credit->refund_processed_at)
                            <tr>
                                <th>{{ __('refunded_at') }}</th>
                                <td>{{ $credit->refund_processed_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                            @endif
                            @if($credit->refund_method)
                            <tr>
                                <th>{{ __('refund_method') }}</th>
                                <td>{{ ucfirst(str_replace('_', ' ', $credit->refund_method)) }}</td>
                            </tr>
                            @endif
                            @if($credit->refund_reference)
                            <tr>
                                <th>{{ __('reference_number') }}</th>
                                <td>{{ $credit->refund_reference }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                @endif

                @if($credit->refund_state === 'rejected')
                <div class="card">
                    <div class="card-header bg-danger">
                        <h5 class="text-white">{{ __('refund_rejected') }}</h5>
                    </div>
                    <div class="card-block">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ __('rejected_at') }}</th>
                                <td>{{ optional($credit->refund_rejected_at)->format('M d, Y H:i:s') }}</td>
                            </tr>
                            @if($credit->refundRejectedBy)
                            <tr>
                                <th>{{ __('rejected_by') }}</th>
                                <td>{{ $credit->refundRejectedBy->name ?? 'N/A' }}</td>
                            </tr>
                            @endif
                            @if($credit->refund_rejected_reason)
                            <tr>
                                <th>{{ __('reason') }}</th>
                                <td>{{ $credit->refund_rejected_reason }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                @endif

                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('actions') }}</h5>
                    </div>
                    <div class="card-block">
                        @if($credit->canBeRefunded())
                            <button type="button" class="btn btn-warning btn-block mb-2" id="requestRefundBtn">
                                <i class="feather icon-dollar-sign"></i> {{ __('request_refund') }}
                            </button>
                        @endif

                        @if($credit->remaining_amount > 0 && in_array($credit->status, ['available', 'partially_applied']) && $credit->refund_state === 'none')
                            <button type="button" class="btn btn-primary btn-block mb-2" id="applyToFeeBtn">
                                <i class="feather icon-arrow-right"></i> {{ __('apply_to_fee') }}
                            </button>
                        @endif

                        @if($credit->refund_state === 'requested')
                            <button type="button" class="btn btn-success btn-block mb-2" id="approveRefundBtn">
                                <i class="feather icon-check"></i> {{ __('approve_refund') }}
                            </button>
                            <button type="button" class="btn btn-danger btn-block mb-2" id="rejectRefundBtn">
                                <i class="feather icon-x"></i> {{ __('reject_refund') }}
                            </button>
                        @endif

                        @if($credit->refund_state === 'approved')
                            <button type="button" class="btn btn-primary btn-block mb-2" id="processRefundBtn">
                                <i class="feather icon-dollar-sign"></i> {{ __('process_refund') }}
                            </button>
                        @endif

                        <a href="{{ route('admin.fees-student.report', ['student' => $credit->student_id]) }}" 
                           class="btn btn-info btn-block">
                            <i class="feather icon-file-text"></i> {{ __('view_student_fees') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credit Applications History -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('credit_application_history') }}</h5>
                    </div>
                    <div class="card-block">
                        @if($credit->applications->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('applied_to_fee') }}</th>
                                        <th>{{ __('amount_applied') }}</th>
                                        <th>{{ __('applied_by') }}</th>
                                        <th>{{ __('applied_at') }}</th>
                                        <th>{{ __('note') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($credit->applications as $index => $application)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            @if($application->fee)
                                                {{ $application->fee->category->title ?? 'N/A' }}<br>
                                                <small class="text-muted">
                                                    {{ optional($application->fee->studentEnroll->session ?? null)->title ?? '' }}
                                                    @if(optional($application->fee->studentEnroll->semester ?? null)->title)
                                                        - {{ $application->fee->studentEnroll->semester->title }}
                                                    @endif
                                                </small>
                                            @else
                                                <span class="text-muted">{{ __('fee_deleted') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-success font-weight-bold">
                                            {{ number_format($application->amount_applied, $setting->decimal_place ?? 2) }}
                                            {{ $setting->currency ?? 'GHS' }}
                                        </td>
                                        <td>{{ $application->createdBy->name ?? 'System' }}</td>
                                        <td>{{ $application->created_at ? $application->created_at->format('M d, Y H:i') : 'N/A' }}</td>
                                        <td>{{ $application->note ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-info">
                            {{ __('no_credit_applications_yet') }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>

<!-- Request Refund Modal -->
<div class="modal fade" id="requestRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('request_refund') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('admin.student-credits.request-refund', $credit->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>{{ __('refund_amount') }}:</strong>
                        {{ number_format($credit->remaining_amount, $setting->decimal_place ?? 2) }}
                        {{ $setting->currency ?? 'GHS' }}
                    </div>
                    <div class="form-group">
                        <label>{{ __('reason_for_refund') }}</label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="{{ __('please_provide_reason') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-warning">{{ __('submit_request') }}</button>
                </div>
            </form>
        </div>
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
            <form action="{{ route('admin.student-credits.approve-refund', $credit->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-success">
                        {{ __('you_are_about_to_approve_refund') }}
                        <strong>{{ number_format($credit->remaining_amount, $setting->decimal_place ?? 2) }} {{ $setting->currency ?? 'GHS' }}</strong>
                    </div>
                    <div class="form-group">
                        <label>{{ __('approval_note') }}</label>
                        <textarea name="note" class="form-control" rows="2" 
                                  placeholder="{{ __('optional') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-success">{{ __('approve') }}</button>
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
            <form action="{{ route('admin.student-credits.reject-refund', $credit->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        {{ __('you_are_about_to_reject_this_refund_request') }}
                    </div>
                    <div class="form-group">
                        <label>{{ __('rejection_reason') }} <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="{{ __('please_provide_reason') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('reject') }}</button>
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
            <form action="{{ route('admin.student-credits.process-refund', $credit->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>{{ __('refund_amount') }}:</strong>
                        {{ number_format($credit->remaining_amount, $setting->decimal_place ?? 2) }}
                        {{ $setting->currency ?? 'GHS' }}
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
                                  placeholder="{{ __('optional') }}"></textarea>
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

<!-- Apply to Fee Modal -->
<div class="modal fade" id="applyToFeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('apply_credit_to_fee') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('admin.student-credits.apply-to-fee', $credit->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong>{{ __('available_credit') }}:</strong>
                        {{ number_format($credit->remaining_amount, $setting->decimal_place ?? 2) }}
                        {{ $setting->currency ?? 'GHS' }}
                    </div>
                    
                    @php
                        // Fee has no student_id column — students live on student_enrolls.
                        // Use the model's accessors so fine + discount math is consistent
                        // with the StudentCreditService::applyToFee() path.
                        $unpaidFees = \App\Models\Fee::whereHas('studentEnroll', function ($q) use ($credit) {
                                $q->where('student_id', $credit->student_id);
                            })
                            ->where('status', '!=', 1) // exclude fully paid
                            ->with(['category', 'studentEnroll.session', 'studentEnroll.semester'])
                            ->get()
                            ->filter(fn($f) => ($f->total_amount - ($f->paid_amount ?? 0)) > 0)
                            ->values();
                    @endphp

                    @if($unpaidFees->count() > 0)
                    <div class="form-group">
                        <label>{{ __('select_fee_to_apply') }} <span class="text-danger">*</span></label>
                        <select name="fee_id" class="form-control" required id="feeSelect">
                            <option value="">{{ __('select_fee') }}</option>
                            @foreach($unpaidFees as $fee)
                                @php
                                    $remaining = $fee->total_amount - ($fee->paid_amount ?? 0);
                                @endphp
                                <option value="{{ $fee->id }}" data-remaining="{{ $remaining }}">
                                    {{ $fee->category->title ?? 'N/A' }}
                                    ({{ optional($fee->studentEnroll->session ?? null)->title ?? '' }}
                                    - {{ optional($fee->studentEnroll->semester ?? null)->title ?? '' }})
                                    - {{ __('remaining') }}: {{ number_format($remaining, $setting->decimal_place ?? 2) }} {{ $setting->currency ?? 'GHS' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('amount_to_apply') }} <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required
                               step="0.01" min="0.01" max="{{ $credit->remaining_amount }}"
                               placeholder="{{ __('enter_amount') }}" id="applyAmount">
                        <small class="text-muted">{{ __('max') }}: {{ number_format($credit->remaining_amount, $setting->decimal_place ?? 2) }} {{ $setting->currency ?? 'GHS' }}</small>
                    </div>
                    <div class="form-group">
                        <label>{{ __('note') }}</label>
                        <textarea name="note" class="form-control" rows="2" 
                                  placeholder="{{ __('optional') }}"></textarea>
                    </div>
                    @else
                    <div class="alert alert-warning">
                        {{ __('no_unpaid_fees_found') }}
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    @if($unpaidFees->count() > 0)
                    <button type="submit" class="btn btn-primary">{{ __('apply_credit') }}</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page-js')
<script>
$(document).ready(function() {
    $('#requestRefundBtn').click(function() {
        $('#requestRefundModal').modal('show');
    });
    
    $('#approveRefundBtn').click(function() {
        $('#approveRefundModal').modal('show');
    });
    
    $('#rejectRefundBtn').click(function() {
        $('#rejectRefundModal').modal('show');
    });
    
    $('#processRefundBtn').click(function() {
        $('#processRefundModal').modal('show');
    });
    
    $('#applyToFeeBtn').click(function() {
        $('#applyToFeeModal').modal('show');
    });
    
    // Auto-fill amount based on selected fee
    $('#feeSelect').change(function() {
        var remaining = parseFloat($(this).find(':selected').data('remaining')) || 0;
        var available = {{ $credit->remaining_amount }};
        var suggested = Math.min(remaining, available);
        $('#applyAmount').val(suggested.toFixed(2));
    });
});
</script>
@endsection
