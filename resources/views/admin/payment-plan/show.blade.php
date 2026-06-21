@extends('admin.layouts.master')
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
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary btn-sm float-end">
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

                        <!-- Student and Fee Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ __('student_information') }}</h5>
                                    </div>
                                    <div class="card-block">
                                        @php
                                            // Get latest enrollment for payment plan
                                            $paymentPlanEnroll = \App\Models\StudentEnroll::where('student_id', $row->student->id)
                                                ->with('program')->orderBy('id', 'desc')->first();
                                        @endphp
                                        <p><strong>{{ __('field_matricule') }}:</strong> 
                                            <span style="font-size: 14px; color: #667eea;">#{{ $paymentPlanEnroll->matricule ?? $row->student->student_id }}</span>
                                            @if($paymentPlanEnroll && $paymentPlanEnroll->program)
                                                <span class="badge" style="background: {{ $paymentPlanEnroll->program->academic_level == 'M' ? '#f5576c' : ($paymentPlanEnroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 8px; margin-left: 5px;">
                                                    {{ $paymentPlanEnroll->program->academic_level == 'A' ? 'UG' : ($paymentPlanEnroll->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            @endif
                                        </p>
                                        <p><strong>{{ __('field_name') }}:</strong> {{ $row->student->first_name }} {{ $row->student->last_name }}</p>
                                        <p><strong>{{ __('field_email') }}:</strong> {{ $row->student->email ?? 'N/A' }}</p>
                                        <p><strong>{{ __('field_phone') }}:</strong> {{ $row->student->phone ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ __('fee_information') }}</h5>
                                    </div>
                                    <div class="card-block">
                                        <p><strong>{{ __('field_fees_type') }}:</strong> {{ $row->fee->category->title ?? '' }}</p>
                                        <p><strong>{{ __('field_program') }}:</strong> {{ $row->fee->studentEnroll->program->title ?? '' }}</p>
                                        <p><strong>{{ __('field_session') }}:</strong> {{ $row->fee->studentEnroll->session->title ?? '' }}</p>
                                        <p><strong>{{ __('field_semester') }}:</strong> {{ $row->fee->studentEnroll->semester->title ?? '' }}</p>
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
                                                        <th>{{ __('field_grace_period_ends') }}</th>
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
                                                        <td>
                                                            @if($installment->grace_period_ends)
                                                            {{ date('d M Y', strtotime($installment->grace_period_ends)) }}
                                                            @else
                                                            -
                                                            @endif
                                                        </td>
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
                                                                $hasPendingVerification = $installment->paymentReceipts->where('status', 'pending')->isNotEmpty();
                                                                $hasPendingMultiPayment = $installment->hasPendingMultiPayment();
                                                            @endphp
                                                            
                                                            @if($hasRejected)
                                                            <br>
                                                            <span class="badge badge-danger mt-1">
                                                                <i class="fas fa-times-circle"></i> {{ __('payment_rejected') }}
                                                            </span>
                                                            @endif
                                                            
                                                            @if($hasPendingVerification)
                                                            <br>
                                                            <span class="badge badge-warning mt-1">
                                                                <i class="fas fa-clock"></i> {{ __('pending_verification') }}
                                                            </span>
                                                            @endif
                                                            
                                                            @if($hasPendingMultiPayment)
                                                            <br>
                                                            <span class="badge badge-warning mt-1">
                                                                <i class="fas fa-clock"></i> Multi-Pay Pending ({{ number_format($installment->getPendingMultiPaymentAmount(), 2) }} {!! $setting->currency_symbol !!})
                                                            </span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($hasPendingMultiPayment)
                                                                @can($access.'.pay')
                                                                <button type="button" class="btn btn-sm btn-secondary" disabled title="This installment has a pending multi-payment">
                                                                    <i class="fas fa-clock"></i> Multi-Pay Pending
                                                                </button>
                                                                @endcan
                                                            @elseif(!$installment->isPaid() && $row->isActive())
                                                                @can($access.'.pay')
                                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal-{{ $installment->id }}">
                                                                    <i class="fas fa-money-bill"></i> {{ __('btn_pay') }}
                                                                </button>
                                                                @include('admin.payment-plan.pay', ['installment' => $installment])
                                                                @endcan
                                                            @endif

                                                            @if($installment->payments->count() > 0)
                                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#historyModal-{{ $installment->id }}">
                                                                <i class="fas fa-history"></i> {{ __('btn_history') }}
                                                            </button>
                                                            @include('admin.payment-plan.payment-history', ['installment' => $installment])
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="4">{{ __('total') }}</th>
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

                        <!-- Approval Information -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ __('created_by') }}</h5>
                                    </div>
                                    <div class="card-block">
                                        <p><strong>{{ __('field_name') }}:</strong> {{ $row->creator->name ?? 'N/A' }}</p>
                                        <p><strong>{{ __('field_date') }}:</strong> {{ date('d M Y, h:i A', strtotime($row->created_at)) }}</p>
                                    </div>
                                </div>
                            </div>

                            @if($row->approved_by)
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ __('approved_by') }}</h5>
                                    </div>
                                    <div class="card-block">
                                        <p><strong>{{ __('field_name') }}:</strong> {{ $row->approver->name ?? 'N/A' }}</p>
                                        <p><strong>{{ __('field_date') }}:</strong> {{ date('d M Y, h:i A', strtotime($row->approved_at)) }}</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        @if($row->isActive())
                        <div class="row mt-3">
                            <div class="col-md-12">
                                @can($access.'.edit')
                                <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> {{ __('btn_edit') }}
                                </a>
                                @endcan

                                @can($access.'.cancel')
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal-{{ $row->id }}">
                                    <i class="fas fa-ban"></i> {{ __('btn_cancel_plan') }}
                                </button>
                                @include('admin.payment-plan.cancel')
                                @endcan
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
