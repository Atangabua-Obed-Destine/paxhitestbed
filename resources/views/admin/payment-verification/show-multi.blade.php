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
                @if($row->status == 'pending')
                <div class="alert alert-warning">
                    <h5><i class="fas fa-clock"></i> Multi-Payment Pending Verification</h5>
                    <p class="mb-0">This multi-fee payment is awaiting admin review and approval.</p>
                </div>
                @elseif($row->status == 'approved')
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> Multi-Payment Approved</h5>
                    <p class="mb-0">
                        Verified by: <strong>{{ $row->verifiedBy->name ?? 'N/A' }}</strong> | 
                        Date: {{ date('Y-m-d H:i', strtotime($row->verified_at)) }}
                    </p>
                </div>
                @else
                <div class="alert alert-danger">
                    <h5><i class="fas fa-times-circle"></i> Multi-Payment Rejected</h5>
                    <p class="mb-0">
                        Verified by: <strong>{{ $row->verifiedBy->name ?? 'N/A' }}</strong> | 
                        Date: {{ date('Y-m-d H:i', strtotime($row->verified_at)) }}
                    </p>
                </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5>
                            Multi-Fee Payment Details
                            <span class="badge badge-info"><i class="fas fa-layer-group"></i> MULTI-PAYMENT</span>
                            <span class="badge badge-secondary">ID: #{{ $row->id }}</span>
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
                                </table>
                            </div>

                            <!-- Payment Information -->
                            <div class="col-md-6">
                                <h6 class="mb-3"><strong>Payment Information</strong></h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_payment_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->payment_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->payment_date)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_payment_method') }}:</strong></td>
                                        <td><span class="badge badge-primary">{{ ucwords(str_replace('_', ' ', $row->payment_method)) }}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_transaction_id') }}:</strong></td>
                                        <td>{{ $row->transaction_id ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Balance:</strong></td>
                                        <td class="text-info h6">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->total_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->total_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount Paid:</strong></td>
                                        <td class="text-success h6">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->amount_paid, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->amount_paid, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Number of Fees:</strong></td>
                                        <td><span class="badge badge-info">{{ $row->distributions->where('amount_applied', '>', 0)->count() }} items</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Payment Distribution -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6 class="mb-3"><strong><i class="fas fa-chart-pie"></i> Payment Distribution</strong></h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Fee Type / Installment</th>
                                                <th>Session & Semester</th>
                                                <th>Balance Before</th>
                                                <th>Amount Applied</th>
                                                <th>Balance After</th>
                                                <th>Status After</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($row->distributions->where('amount_applied', '>', 0) as $key => $distribution)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>
                                                    <strong>{{ $distribution->fee->category->title ?? 'N/A' }}</strong>
                                                    @if($distribution->installment_id && $distribution->installment)
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-level-up-alt fa-rotate-90"></i> 
                                                            Installment {{ $distribution->installment->installment_number }}
                                                            <span class="badge badge-{{ $distribution->installment->status == 'paid' ? 'success' : ($distribution->installment->status == 'partial' ? 'warning' : 'danger') }} badge-sm">
                                                                {{ $distribution->installment->status }}
                                                            </span>
                                                        </small>
                                                        <br><small class="text-muted">Due: {{ date('M d, Y', strtotime($distribution->installment->due_date)) }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $distribution->fee->studentEnroll->session->title ?? 'N/A' }}
                                                    <br><small class="text-muted">{{ $distribution->fee->studentEnroll->semester->title ?? '' }}</small>
                                                </td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$distribution->balance_before, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$distribution->balance_before, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td class="text-primary">
                                                    <strong>
                                                        @if(isset($setting->decimal_place))
                                                        {{ number_format((float)$distribution->amount_applied, $setting->decimal_place, '.', '') }} 
                                                        @else
                                                        {{ number_format((float)$distribution->amount_applied, 2, '.', '') }} 
                                                        @endif 
                                                        {!! $setting->currency_symbol !!}
                                                    </strong>
                                                </td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$distribution->balance_after, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$distribution->balance_after, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $distribution->fee_status_after == 'paid' ? 'success' : 'warning' }}">
                                                        {{ ucfirst($distribution->fee_status_after) }}
                                                    </span>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No distributions found</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot class="table-info">
                                            <tr>
                                                <th colspan="4" class="text-right">Total Amount Paid:</th>
                                                <th colspan="3" class="text-primary">
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$row->amount_paid, $setting->decimal_place, '.', '') }} 
                                                    @else
                                                    {{ number_format((float)$row->amount_paid, 2, '.', '') }} 
                                                    @endif 
                                                    {!! $setting->currency_symbol !!}
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Receipt Display -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6 class="mb-3"><strong>{{ __('field_payment_receipt') }}</strong></h6>
                                @if($row->receipt_path)
                                    @php
                                        $fileExtension = pathinfo($row->receipt_path, PATHINFO_EXTENSION);
                                        $isPdf = strtolower($fileExtension) === 'pdf';
                                    @endphp

                                    @if($isPdf)
                                        <div class="embed-responsive embed-responsive-16by9" style="height: 600px;">
                                            <embed src="{{ asset('storage/' . $row->receipt_path) }}" 
                                                   type="application/pdf" 
                                                   class="embed-responsive-item">
                                        </div>
                                    @else
                                        <div class="text-center">
                                            <img src="{{ asset('storage/' . $row->receipt_path) }}" 
                                                 alt="Payment Receipt" 
                                                 class="img-fluid" 
                                                 style="max-height: 600px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                    @endif

                                    <div class="text-center mt-3">
                                        <a href="{{ asset('storage/' . $row->receipt_path) }}" 
                                           class="btn btn-outline-primary" 
                                           download 
                                           target="_blank">
                                            <i class="fas fa-download"></i> {{ __('btn_download_receipt') }}
                                        </a>
                                    </div>
                                @else
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> No receipt uploaded
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Admin Note -->
                        @if($row->admin_note)
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <h6><strong>Admin Note:</strong></h6>
                                    <p class="mb-0">{{ $row->admin_note }}</p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Verification Actions -->
                @if($row->status == 'pending')
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-check"></i> Approve Payment</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route($route.'.approve', ['multi', $row->id]) }}" id="approveForm">
                                    @csrf
                                    <div class="form-group">
                                        <label for="payment_account_id">{{ __('field_payment_account') }} (Optional)</label>
                                        <select name="payment_account_id" id="payment_account_id" class="form-control">
                                            <option value="">{{ __('select') }}</option>
                                            @foreach(App\Models\PaymentAccount::where('status', 1)->get() as $account)
                                            <option value="{{ $account->id }}">{{ $account->title }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Select account to record this transaction</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="verification_note">{{ __('field_note') }} (Optional)</label>
                                        <textarea name="verification_note" id="verification_note" class="form-control" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fas fa-check"></i> {{ __('btn_approve_payment') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0"><i class="fas fa-times"></i> Reject Payment</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route($route.'.reject', ['multi', $row->id]) }}" id="rejectForm">
                                    @csrf
                                    <div class="form-group">
                                        <label for="verification_note_reject">{{ __('field_reason') }} <span class="text-danger">*</span></label>
                                        <textarea name="verification_note" id="verification_note_reject" class="form-control" rows="3" required></textarea>
                                        <small class="form-text text-muted">Explain why this payment is being rejected</small>
                                    </div>
                                    <button type="submit" class="btn btn-danger btn-block">
                                        <i class="fas fa-times"></i> {{ __('btn_reject_payment') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="row mt-3">
                    <div class="col-md-12">
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
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
<script type="text/javascript">
    'use strict';
    
    // Approve form confirmation
    $('#approveForm').on('submit', function(e) {
        if (!confirm('Are you sure you want to approve this multi-payment? This will update all associated fees and installments.')) {
            e.preventDefault();
        }
    });
    
    // Reject form confirmation
    $('#rejectForm').on('submit', function(e) {
        if (!confirm('Are you sure you want to reject this multi-payment? The student will be notified.')) {
            e.preventDefault();
        }
    });
</script>
@endsection
