@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        
        <!-- Partial Payment Reminders -->
        @php
            $partiallyPaidFees = $fees->filter(function($fee) {
                return $fee->isPartiallyPaid();
            });
        @endphp
        
        @if($partiallyPaidFees->count() > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-triangle fa-2x mr-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-2">
                                <i class="fas fa-info-circle"></i> {{ __('msg_partial_payment_reminder_title') }}
                            </h5>
                            <p class="mb-2">{{ __('msg_partial_payment_reminder_message') }}</p>
                            
                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-bordered bg-white">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ __('field_fees_type') }}</th>
                                            <th>{{ __('field_total_amount_due') }}</th>
                                            <th>{{ __('field_paid_amount') }}</th>
                                            <th>{{ __('field_remaining_balance') }}</th>
                                            <th>{{ __('field_action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($partiallyPaidFees as $fee)
                                        <tr>
                                            <td>{{ $fee->category->title ?? '' }}</td>
                                            <td>
                                                @if(isset($setting->decimal_place))
                                                {{ number_format((float)$fee->total_amount, $setting->decimal_place, '.', '') }} 
                                                @else
                                                {{ number_format((float)$fee->total_amount, 2, '.', '') }} 
                                                @endif 
                                                {!! $setting->currency_symbol !!}
                                            </td>
                                            <td class="text-success">
                                                <strong>
                                                @if(isset($setting->decimal_place))
                                                {{ number_format((float)$fee->paid_amount, $setting->decimal_place, '.', '') }} 
                                                @else
                                                {{ number_format((float)$fee->paid_amount, 2, '.', '') }} 
                                                @endif 
                                                {!! $setting->currency_symbol !!}
                                                </strong>
                                            </td>
                                            <td class="text-danger">
                                                <strong>
                                                @if(isset($setting->decimal_place))
                                                {{ number_format((float)$fee->remaining_balance, $setting->decimal_place, '.', '') }} 
                                                @else
                                                {{ number_format((float)$fee->remaining_balance, 2, '.', '') }} 
                                                @endif 
                                                {!! $setting->currency_symbol !!}
                                                </strong>
                                            </td>
                                            <td>
                                                @php
                                                    $hasActivePaymentPlan = $fee->paymentPlan && in_array($fee->paymentPlan->status, ['active', 'pending']);
                                                    $pendingReceipt = $fee->pendingReceipt;
                                                    $hasPendingMultiPayment = $fee->hasPendingMultiPayment();
                                                @endphp
                                                
                                                @if($hasActivePaymentPlan)
                                                    <!-- Fee is under payment plan -->
                                                    <a href="{{ route('student.payment-plan.show', $fee->paymentPlan->id) }}" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-calendar-alt"></i> {{ __('view_payment_plan') }}
                                                    </a>
                                                @elseif($hasPendingMultiPayment)
                                                    <button class="btn btn-sm btn-secondary" disabled title="This fee has a pending multi-payment">
                                                        <i class="fas fa-clock"></i> Multi-Pay Pending
                                                    </button>
                                                @elseif($pendingReceipt)
                                                    <a href="{{ route($route.'.show', $pendingReceipt->id) }}" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-clock"></i> {{ __('status_pending') }}
                                                    </a>
                                                @else
                                                    <a href="{{ route($route.'.create', $fee->id) }}" class="btn btn-sm btn-success">
                                                        <i class="fas fa-upload"></i> {{ __('btn_pay_now') }}
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
        
        <div class="row">
            <!-- Unpaid Fees Section -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('unpaid_fees') }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_fee') }}</th>
                                        <th>{{ __('field_discount') }}</th>
                                        <th>{{ __('field_fine_amount') }}</th>
                                        <th>{{ __('field_net_amount') }}</th>
                                        <th>{{ __('field_paid_amount') }}</th>
                                        <th>{{ __('field_remaining_balance') }}</th>
                                        <th>{{ __('field_due_date') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @forelse( $fees as $key => $fee )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $fee->studentEnroll->session->title ?? '' }}</td>
                                        <td>{{ $fee->studentEnroll->semester->title ?? '' }}</td>
                                        <td>{{ $fee->category->title ?? '' }}</td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fee->fee_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fee->fee_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fee->discount_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fee->discount_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fee->fine_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fee->fine_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @php
                                                $net_amount = $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
                                            @endphp
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$net_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$net_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            <span class="text-success">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fee->paid_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fee->paid_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="{{ $fee->remaining_balance > 0 ? 'text-danger' : 'text-success' }}">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fee->remaining_balance, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fee->remaining_balance, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                        </td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($fee->due_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($fee->due_date)) }}
                                            @endif

                                            @if($fee->due_date < date('Y-m-d'))
                                            <span class="badge badge-danger">{{ __('status_overdue') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $hasActivePaymentPlan = $fee->paymentPlan && in_array($fee->paymentPlan->status, ['active', 'pending']);
                                                $pendingReceipt = $fee->pendingReceipt;
                                                $hasPendingMultiPayment = $fee->hasPendingMultiPayment();
                                            @endphp
                                            
                                            @if($hasActivePaymentPlan)
                                                <!-- Fee is under payment plan -->
                                                <div class="alert alert-info p-2 mb-0">
                                                    <i class="fas fa-info-circle"></i> 
                                                    <strong>{{ __('payment_plan_active') }}</strong><br>
                                                    <small>{{ __('msg_pay_via_payment_plan') }}</small>
                                                </div>
                                                <a href="{{ route('student.payment-plan.show', $fee->paymentPlan->id) }}" class="btn btn-sm btn-primary mt-2">
                                                    <i class="fas fa-calendar-alt"></i> {{ __('btn_view_payment_plan') }}
                                                </a>
                                            @elseif($hasPendingMultiPayment)
                                                <button class="btn btn-sm btn-secondary" disabled title="This fee has a pending multi-payment">
                                                    <i class="fas fa-clock"></i> Multi-Pay Pending
                                                </button>
                                            @elseif($pendingReceipt)
                                                <a href="{{ route($route.'.show', $pendingReceipt->id) }}" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-clock"></i> {{ __('btn_pending_verification') }}
                                                </a>
                                            @else
                                                <a href="{{ route($route.'.create', $fee->id) }}" class="btn btn-sm btn-success">
                                                    <i class="fas fa-upload"></i> {{ __('btn_upload_receipt') }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                  @empty
                                    <tr>
                                        <td colspan="10" class="text-center">{{ __('no_unpaid_fees') }}</td>
                                    </tr>
                                  @endforelse
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                        
                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $fees->links() }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Receipts History Section -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('payment_receipts_history') }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_payment_reference') }}</th>
                                        <th>{{ __('field_amount') }}</th>
                                        <th>{{ __('field_payment_date') }}</th>
                                        <th>{{ __('field_submitted_date') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @forelse( $receipts as $key => $receipt )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $receipt->fee->category->title ?? '' }}</td>
                                        <td>{{ $receipt->payment_reference ?? '-' }}</td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$receipt->amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$receipt->amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($receipt->payment_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($receipt->payment_date)) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($receipt->created_at)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($receipt->created_at)) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($receipt->verification_status == 'pending')
                                                <span class="badge badge-warning">{{ __('status_pending') }}</span>
                                            @elseif($receipt->verification_status == 'approved')
                                                <span class="badge badge-success">{{ __('status_approved') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ __('status_rejected') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route($route.'.show', $receipt->id) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> {{ __('btn_view') }}
                                            </a>
                                        </td>
                                    </tr>
                                  @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('no_receipts_submitted') }}</td>
                                    </tr>
                                  @endforelse
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                        
                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $receipts->links('pagination::bootstrap-4') }}
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
