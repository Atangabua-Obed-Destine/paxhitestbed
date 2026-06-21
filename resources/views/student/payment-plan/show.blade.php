@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Payment Plan Details -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('payment_plan_details') }} - #{{ $row->id }}</h5>
                        <a href="{{ route('student.payment-plan.index') }}" class="btn btn-secondary btn-sm float-end">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                    </div>
                    <div class="card-block">
                        <!-- Status Banner -->
                        <div class="alert alert-{{ $row->status == 'active' ? 'success' : ($row->status == 'completed' ? 'primary' : 'danger') }}">
                            <h4>{!! $row->status_badge !!}</h4>
                            @if($row->status == 'cancelled')
                            <p><strong>{{ __('cancellation_reason') }}:</strong> {{ $row->cancellation_reason }}</p>
                            @endif
                        </div>

                        <!-- Fee Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5>{{ __('fee_information') }}</h5>
                            </div>
                            <div class="card-block">
                                <div class="row">
                                    <div class="col-md-3">
                                        <p><strong>{{ __('field_fees_type') }}:</strong></p>
                                        <p>{{ $row->fee->category->title ?? '' }}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>{{ __('field_program') }}:</strong></p>
                                        <p>{{ $row->fee->studentEnroll->program->title ?? '' }}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>{{ __('field_session') }}:</strong></p>
                                        <p>{{ $row->fee->studentEnroll->session->title ?? '' }}</p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>{{ __('field_semester') }}:</strong></p>
                                        <p>{{ $row->fee->studentEnroll->semester->title ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Plan Summary -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ __('payment_plan_summary') }}</h5>
                                    </div>
                                    <div class="card-block">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="card text-center">
                                                    <div class="card-block">
                                                        <h3>{{ number_format($row->total_amount, 2) }} {!! $setting->currency_symbol !!}</h3>
                                                        <p class="text-muted">{{ __('field_total_amount') }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="card text-center bg-c-green">
                                                    <div class="card-block text-white">
                                                        <h3>{{ number_format($row->total_paid, 2) }} {!! $setting->currency_symbol !!}</h3>
                                                        <p>{{ __('field_paid') }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="card text-center bg-c-yellow">
                                                    <div class="card-block text-white">
                                                        <h3>{{ number_format($row->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</h3>
                                                        <p>{{ __('field_remaining') }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="card text-center">
                                                    <div class="card-block">
                                                        <h3>{{ round($row->progress_percentage) }}%</h3>
                                                        <p class="text-muted">{{ __('field_progress') }}</p>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $row->progress_percentage }}%;" aria-valuenow="{{ $row->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mt-3">
                                            <div class="col-md-4">
                                                <p><strong>{{ __('field_installments') }}:</strong> {{ $row->installments_count }}</p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>{{ __('field_late_fee_percentage') }}:</strong> {{ $row->late_fee_percentage }}%</p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>{{ __('field_grace_period') }}:</strong> {{ $row->grace_period_days }} {{ __('days') }}</p>
                                            </div>
                                        </div>

                                        @if($row->overdue_count > 0)
                                        <div class="alert alert-danger mt-3">
                                            <i class="fas fa-exclamation-triangle"></i> <strong>{{ $row->overdue_count }}</strong> {{ __('overdue_installments') }}
                                        </div>
                                        @endif

                                        @if($row->notes)
                                        <div class="mt-3">
                                            <p><strong>{{ __('field_notes') }}:</strong></p>
                                            <p>{{ $row->notes }}</p>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Installments Table -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ __('installment_schedule') }}</h5>
                                    </div>
                                    <div class="card-block">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>{{ __('field_amount') }}</th>
                                                        <th>{{ __('field_due_date') }}</th>
                                                        <th>{{ __('field_paid_amount') }}</th>
                                                        <th>{{ __('field_late_fee') }}</th>
                                                        <th>{{ __('field_remaining') }}</th>
                                                        <th>{{ __('field_status') }}</th>
                                                        <th>{{ __('field_action') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($row->installments as $installment)
                                                    <tr class="{{ $installment->isOverdue() ? 'table-danger' : '' }}">
                                                        <td>{{ $installment->installment_number }}</td>
                                                        <td>{{ number_format($installment->amount, 2) }} {!! $setting->currency_symbol !!}</td>
                                                        <td>{{ date('d M Y', strtotime($installment->due_date)) }}</td>
                                                        <td class="text-success">
                                                            <strong>{{ number_format($installment->paid_amount ?? 0, 2) }} {!! $setting->currency_symbol !!}</strong>
                                                        </td>
                                                        <td>
                                                            @if($installment->late_fee > 0)
                                                            <span class="text-danger">{{ number_format($installment->late_fee, 2) }} {!! $setting->currency_symbol !!}</span>
                                                            @else
                                                            -
                                                            @endif
                                                        </td>
                                                        <td class="text-warning">
                                                            <strong>{{ number_format($installment->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</strong>
                                                        </td>
                                                        <td>
                                                            {!! $installment->status_badge !!}
                                                            
                                                            @php
                                                                $hasRejected = $installment->paymentReceipts->where('status', 'rejected')->isNotEmpty();
                                                                $rejectedReceipt = $installment->paymentReceipts->where('status', 'rejected')->first();
                                                                $hasPendingVerification = $installment->paymentReceipts->where('status', 'pending')->isNotEmpty();
                                                            @endphp
                                                            
                                                            @if($hasRejected)
                                                            <br>
                                                            <span class="badge badge-danger mt-1">
                                                                <i class="fas fa-times-circle"></i> {{ __('payment_rejected') }}
                                                            </span>
                                                            @if($rejectedReceipt && $rejectedReceipt->rejection_reason)
                                                            <br>
                                                            <small class="text-danger">{{ __('reason') }}: {{ $rejectedReceipt->rejection_reason }}</small>
                                                            @endif
                                                            @endif
                                                            
                                                            @if($hasPendingVerification)
                                                            <br>
                                                            <span class="badge badge-warning mt-1">
                                                                <i class="fas fa-clock"></i> {{ __('pending_verification') }}
                                                            </span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @php
                                                                $pendingReceipt = $installment->paymentReceipts->where('status', 'pending')->first();
                                                                $hasPendingMultiPayment = $installment->hasPendingMultiPayment();
                                                            @endphp
                                                            
                                                            @if($pendingReceipt)
                                                            <!-- Has pending receipt -->
                                                            <button type="button" class="btn btn-sm btn-warning" disabled>
                                                                <i class="fas fa-clock"></i> {{ __('status_pending_verification') }}
                                                            </button>
                                                            <br>
                                                            <small class="text-muted">{{ __('submitted_on') }}: {{ $pendingReceipt->created_at->format('d M Y') }}</small>
                                                            @elseif($hasPendingMultiPayment)
                                                            <!-- Has pending multi-payment -->
                                                            <button type="button" class="btn btn-sm btn-secondary" disabled title="This installment has a pending multi-payment">
                                                                <i class="fas fa-clock"></i> Multi-Pay Pending
                                                            </button>
                                                            @elseif($installment->status == 'pending' || $installment->status == 'partial' || $installment->status == 'overdue')
                                                            <!-- Can pay -->
                                                            <a href="{{ route('student.installment-payment.create', $installment->id) }}" class="btn btn-sm btn-success">
                                                                <i class="fas fa-upload"></i> {{ __('btn_pay_now') }}
                                                            </a>
                                                            @endif
                                                            
                                                            @if($installment->payments->count() > 0)
                                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#historyModal-{{ $installment->id }}">
                                                                <i class="fas fa-history"></i> {{ __('btn_history') }}
                                                            </button>
                                                            @include('student.payment-plan.payment-history', ['installment' => $installment])
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="3">{{ __('total') }}</th>
                                                        <th class="text-success">{{ number_format($row->total_paid, 2) }} {!! $setting->currency_symbol !!}</th>
                                                        <th>{{ number_format($row->installments->sum('late_fee'), 2) }} {!! $setting->currency_symbol !!}</th>
                                                        <th class="text-warning">{{ number_format($row->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</th>
                                                        <th colspan="2"></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="alert alert-info mt-3">
                            <h5><i class="fas fa-info-circle"></i> {{ __('payment_information') }}</h5>
                            <p>{{ __('payment_plan_student_info_message') }}</p>
                            <p><strong>{{ __('note') }}:</strong> {{ __('contact_admin_for_payments') }}</p>
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
