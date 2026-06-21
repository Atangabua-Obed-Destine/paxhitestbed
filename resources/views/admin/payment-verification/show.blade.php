@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5>{{ $title }}</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('module_payment_verification') }}</a></li>
                            <li class="breadcrumb-item"><a href="#!">{{ __('btn_view') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12">
                <!-- Status Alert -->
                @php
                    $status = $payment_type == 'fee' ? $row->verification_status : $row->status;
                @endphp
                @if($status == 'pending')
                <div class="alert alert-warning">
                    <h5><i class="fas fa-clock"></i> {{ __('payment_receipt_pending') }}</h5>
                    <p class="mb-0">{{ __('msg_admin_review_receipt') }}</p>
                </div>
                @elseif($status == 'approved')
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> {{ __('payment_receipt_approved') }}</h5>
                    <p class="mb-0">
                        {{ __('msg_verified_by') }}: <strong>{{ $row->verifier->name ?? 'N/A' }}</strong> | 
                        {{ __('field_verified_date') }}: {{ date('Y-m-d H:i', strtotime($row->verified_at)) }}
                    </p>
                </div>
                @else
                <div class="alert alert-danger">
                    <h5><i class="fas fa-times-circle"></i> {{ __('payment_receipt_rejected') }}</h5>
                    <p class="mb-0">
                        {{ __('msg_verified_by') }}: <strong>{{ $row->verifier->name ?? 'N/A' }}</strong> | 
                        {{ __('field_verified_date') }}: {{ date('Y-m-d H:i', strtotime($row->verified_at)) }}
                    </p>
                </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5>
                            {{ __('payment_receipt_details') }}
                            @if($payment_type == 'fee')
                                <span class="badge badge-primary">{{ __('fee_payment') }}</span>
                            @else
                                <span class="badge badge-secondary">{{ __('installment_payment') }}</span>
                            @endif
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Student Information -->
                            <div class="col-md-6">
                                <h6 class="mb-3"><strong>{{ __('student_information') }}</strong></h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_name') }}:</strong></td>
                                        <td>{{ $row->student ? $row->student->first_name . ' ' . $row->student->last_name : 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_student_id') }}:</strong></td>
                                        <td>{{ $row->student->student_id ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_email') }}:</strong></td>
                                        <td>{{ $row->student->email ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_phone') }}:</strong></td>
                                        <td>{{ $row->student->phone ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_program') }}:</strong></td>
                                        <td>
                                            @if($payment_type == 'fee')
                                                {{ $row->fee->studentEnroll->program->title ?? 'N/A' }}
                                            @else
                                                {{ $row->installment->paymentPlan->fee->studentEnroll->program->title ?? 'N/A' }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_section') }}:</strong></td>
                                        <td>
                                            @if($payment_type == 'fee')
                                                {{ $row->fee->studentEnroll->section->title ?? 'N/A' }}
                                            @else
                                                {{ $row->installment->paymentPlan->fee->studentEnroll->section->title ?? 'N/A' }}
                                            @endif
                                        </td>
                                    </tr>
                                </table>

                                <h6 class="mb-3 mt-4"><strong>{{ __('fee_information') }}</strong></h6>
                                <table class="table table-borderless">
                                    @if($payment_type == 'installment')
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_payment_plan') }}:</strong></td>
                                        <td>
                                            <span class="badge badge-info">{{ __('payment_plan') }} #{{ $row->installment->paymentPlan->id ?? '' }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_installment') }}:</strong></td>
                                        <td>{{ __('installment') }} #{{ $row->installment->installment_number ?? '' }} {{ __('of') }} {{ $row->installment->paymentPlan->number_of_installments ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_installment_amount') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->installment->amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->installment->amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_due_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->installment->due_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->installment->due_date)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><hr></td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_fees_type') }}:</strong></td>
                                        <td>
                                            @if($payment_type == 'fee')
                                                {{ $row->fee->category->title ?? '' }}
                                            @else
                                                {{ $row->installment->paymentPlan->fee->category->title ?? '' }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_session') }}:</strong></td>
                                        <td>
                                            @if($payment_type == 'fee')
                                                {{ $row->fee->studentEnroll->session->title ?? '' }}
                                            @else
                                                {{ $row->installment->paymentPlan->fee->studentEnroll->session->title ?? '' }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_semester') }}:</strong></td>
                                        <td>
                                            @if($payment_type == 'fee')
                                                {{ $row->fee->studentEnroll->semester->title ?? '' }}
                                            @else
                                                {{ $row->installment->paymentPlan->fee->studentEnroll->semester->title ?? '' }}
                                            @endif
                                        </td>
                                    </tr>
                                    @if($payment_type == 'fee')
                                    <tr>
                                        <td><strong>{{ __('field_fee_amount') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fee->fee_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fee->fee_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_discount') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fee->discount_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fee->discount_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_fine_amount') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fee->fine_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fee->fine_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_net_amount') }}:</strong></td>
                                        <td>
                                            @php
                                                $net_amount = $row->fee->fee_amount + $row->fee->fine_amount - $row->fee->discount_amount;
                                            @endphp
                                            <strong>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$net_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$net_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_due_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->fee->due_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->fee->due_date)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><hr></td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_paid_amount') }}:</strong></td>
                                        <td>
                                            <span class="text-success">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fee->paid_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fee->paid_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_remaining_balance') }}:</strong></td>
                                        <td>
                                            @php
                                                $remaining = $row->fee->remaining_balance;
                                            @endphp
                                            <strong class="{{ $remaining > 0 ? 'text-danger' : 'text-success' }}">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$remaining, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$remaining, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_payment_status') }}:</strong></td>
                                        <td>{!! $row->fee->status_badge !!}</td>
                                    </tr>
                                    @endif
                                </table>

                                @if($payment_type == 'fee')
                                @php
                                    $approvedReceipts = $row->fee->approvedReceipts()->with('verifier')->get();
                                @endphp

                                @if($approvedReceipts->count() > 0)
                                <h6 class="mb-3 mt-3"><strong>{{ __('field_payment_history') }}</strong></h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th>{{ __('field_date') }}</th>
                                                <th>{{ __('field_amount') }}</th>
                                                <th>{{ __('verified_by') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($approvedReceipts as $approved)
                                            <tr>
                                                <td>{{ $approved->id }}</td>
                                                <td>{{ date('M d, Y', strtotime($approved->payment_date)) }}</td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$approved->amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$approved->amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>{{ $approved->verifier->name ?? 'N/A' }}</td>
                                            </tr>
                                            @endforeach
                                            <tr class="table-secondary">
                                                <td colspan="2" class="text-right"><strong>{{ __('field_total') }}:</strong></td>
                                                <td colspan="2">
                                                    <strong>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$row->fee->paid_amount, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$row->fee->paid_amount, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                    </strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                                @endif
                            </div>

                            <!-- Receipt and Payment Information -->
                            <div class="col-md-6">
                                <h6 class="mb-3"><strong>{{ __('payment_information') }}</strong></h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_payment_reference') }}:</strong></td>
                                        <td>{{ $row->payment_reference ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_payment_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->payment_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->payment_date)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_amount') }}:</strong></td>
                                        <td>
                                            <strong class="text-success h5">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_payment_method') }}:</strong></td>
                                        <td>
                                            @if( $row->payment_method == 2 )
                                            {{ __('payment_method_cash') }}
                                            @elseif( $row->payment_method == 4 )
                                            {{ __('payment_method_bank') }}
                                            @elseif( $row->payment_method == 6 )
                                            {{ __('payment_method_mtn_momo') }}
                                            @elseif( $row->payment_method == 7 )
                                            {{ __('payment_method_orange_money') }}
                                            @elseif( $row->payment_method == 8 )
                                            {{ __('payment_method_other') }}
                                            @else
                                            {{ __('payment_method_other') }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_submitted_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->created_at)) }}
                                            @else
                                            {{ date("Y-m-d H:i", strtotime($row->created_at)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    @if($row->note ?? false)
                                    <tr>
                                        <td colspan="2">
                                            <strong>{{ __('field_student_note') }}:</strong><br>
                                            <div class="alert alert-info p-2 mt-2">
                                                {{ $row->note }}
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                </table>

                                <h6 class="mb-3 mt-3"><strong>{{ __('receipt_file') }}</strong></h6>
                                <div class="text-center mb-3">
                                    @php
                                        $fileExtension = pathinfo($row->receipt_file, PATHINFO_EXTENSION);
                                    @endphp
                                    @if(in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png']))
                                        <img src="{{ asset('uploads/'.$path.'/'.$row->receipt_file) }}" alt="Receipt" class="img-fluid" style="max-height: 400px; border: 2px solid #007bff; padding: 10px; cursor: pointer;" onclick="window.open(this.src, '_blank')">
                                    @elseif(strtolower($fileExtension) == 'pdf')
                                        <div class="alert alert-info">
                                            <i class="fas fa-file-pdf fa-4x mb-2 text-danger"></i>
                                            <p class="mb-0 mt-2">{{ __('pdf_receipt_uploaded') }}</p>
                                        </div>
                                    @else
                                        <div class="alert alert-secondary">
                                            <i class="fas fa-file fa-4x mb-2"></i>
                                            <p class="mb-0 mt-2">{{ __('file_uploaded') }}</p>
                                        </div>
                                    @endif
                                </div>
                                <div class="text-center">
                                    <a href="{{ asset('uploads/'.$path.'/'.$row->receipt_file) }}" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-download"></i> {{ __('btn_download_receipt') }}
                                    </a>
                                </div>

                                @if($row->verification_note)
                                <div class="mt-4">
                                    <h6><strong>{{ __('field_verification_note') }}:</strong></h6>
                                    <div class="alert alert-{{ $row->verification_status == 'approved' ? 'success' : 'danger' }}">
                                        {{ $row->verification_note }}
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="border-top pt-4 mt-4">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>

                            @php
                                $status = $payment_type == 'fee' ? $row->verification_status : $row->status;
                            @endphp
                            @if($status == 'pending')
                                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#approveModal">
                                    <i class="fas fa-check"></i> {{ __('btn_approve') }}
                                </button>
                                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal">
                                    <i class="fas fa-times"></i> {{ __('btn_reject') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route($route.'.approve', [$payment_type, $row->id]) }}" id="approveForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">{{ __('btn_approve_receipt') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <p><strong>{{ __('confirm_approve_receipt') }}</strong></p>
                        <ul class="mb-0">
                            <li>{{ __('msg_fee_will_be_marked_paid') }}</li>
                            <li>{{ __('msg_student_will_be_notified') }}</li>
                            <li>{{ __('msg_action_cannot_be_undone') }}</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label for="payment_account_id_approve">{{ __('field_payment_account') }}</label>
                        <select class="form-control" name="payment_account_id" id="payment_account_id_approve">
                            <option value="">{{ __('select_payment_account_optional') }}</option>
                            @foreach(\App\Models\PaymentAccount::where('status', 1)->orderBy('title')->get() as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->title }} - {{ __('field_balance') }}: {!! $setting->currency_symbol !!}{{ number_format($account->current_balance, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">{{ __('payment_account_help_text_verification') }}</small>
                    </div>

                    <div class="form-group">
                        <label for="verification_note_approve">{{ __('field_verification_note') }} ({{ __('optional') }})</label>
                        <textarea class="form-control" name="verification_note" id="verification_note_approve" rows="3" placeholder="{{ __('placeholder_approval_note') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_cancel') }}</button>
                    <button type="submit" class="btn btn-success" id="approveSubmitBtn"><i class="fas fa-check"></i> {{ __('btn_approve') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route($route.'.reject', [$payment_type, $row->id]) }}" id="rejectForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">{{ __('btn_reject_receipt') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <p><strong>{{ __('confirm_reject_receipt') }}</strong></p>
                        <p class="mb-0">{{ __('msg_student_can_reupload') }}</p>
                    </div>

                    <div class="form-group">
                        <label for="verification_note_reject">{{ __('field_rejection_reason') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="verification_note" id="verification_note_reject" rows="4" placeholder="{{ __('placeholder_rejection_reason') }}" required></textarea>
                        <small class="form-text text-muted">{{ __('msg_rejection_reason_help') }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_cancel') }}</button>
                    <button type="submit" class="btn btn-danger" id="rejectSubmitBtn"><i class="fas fa-times"></i> {{ __('btn_reject') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script>
    $(document).ready(function() {
        console.log('=== Payment verification FINAL version ===');
        
        // Override the trigger button clicks to manually open modals
        $('button[data-target="#approveModal"]').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('>>> Opening Approve modal manually <<<');
            $('#approveModal').modal('show');
        });
        
        $('button[data-target="#rejectModal"]').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('>>> Opening Reject modal manually <<<');
            $('#rejectModal').modal('show');
        });
        
        // When approve modal opens, bind the submit button
        $('#approveModal').on('shown.bs.modal', function() {
            console.log('✓ Approve modal is now visible');
            
            $('#approveSubmitBtn').off('click').on('click', function(e) {
                e.preventDefault();
                console.log('>>> APPROVE SUBMIT CLICKED - SUBMITTING FORM <<<');
                document.getElementById('approveForm').submit();
            });
        });
        
        // When reject modal opens, bind the submit button
        $('#rejectModal').on('shown.bs.modal', function() {
            console.log('✓ Reject modal is now visible');
            
            $('#rejectSubmitBtn').off('click').on('click', function(e) {
                e.preventDefault();
                console.log('>>> REJECT SUBMIT CLICKED <<<');
                
                var note = $('#verification_note_reject').val();
                if (!note || note.trim() === '') {
                    alert('Please provide a rejection reason');
                    console.log('✗ Blocked - no rejection reason');
                    return false;
                }
                
                console.log('✓ Validation passed - SUBMITTING FORM <<<');
                document.getElementById('rejectForm').submit();
            });
        });
        
        console.log('=== Ready - Click Approve or Reject buttons ===');
    });
</script>
@endsection
