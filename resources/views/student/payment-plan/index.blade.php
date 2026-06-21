@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Payment Plans Summary Cards -->
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-blue">{{ $active_plans_count }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('active_payment_plans') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-calendar-check fa-3x text-c-blue"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-green">
                                    {{ number_format($total_paid, 2) }}
                                    {!! $setting->currency_symbol !!}
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_paid') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-check-circle fa-3x text-c-green"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-yellow">
                                    {{ number_format($total_remaining, 2) }}
                                    {!! $setting->currency_symbol !!}
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_remaining') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-clock fa-3x text-c-yellow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-red">{{ $overdue_installments_count }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('overdue_installments') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-exclamation-triangle fa-3x text-c-red"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Plans List -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('my_payment_plans') }}</h5>
                    </div>
                    <div class="card-block">
                        @if($payment_plans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_fee') }}</th>
                                        <th>{{ __('field_total_amount') }}</th>
                                        <th>{{ __('field_installments') }}</th>
                                        <th>{{ __('field_paid') }}</th>
                                        <th>{{ __('field_remaining') }}</th>
                                        <th>{{ __('field_progress') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payment_plans as $key => $plan)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            {{ $plan->fee->category->title ?? '' }}<br>
                                            <small class="text-muted">{{ $plan->fee->studentEnroll->program->title ?? '' }}</small>
                                        </td>
                                        <td>{{ number_format($plan->total_amount, 2) }} {!! $setting->currency_symbol !!}</td>
                                        <td>
                                            <span class="badge badge-info">{{ $plan->installments_count }} {{ __('installments') }}</span>
                                        </td>
                                        <td class="text-success">
                                            <strong>{{ number_format($plan->total_paid, 2) }} {!! $setting->currency_symbol !!}</strong>
                                        </td>
                                        <td class="text-danger">
                                            <strong>{{ number_format($plan->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</strong>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $plan->progress_percentage }}%;" aria-valuenow="{{ $plan->progress_percentage }}" aria-valuemin="0" aria-valuemax="100">
                                                    {{ round($plan->progress_percentage) }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>{!! $plan->status_badge !!}</td>
                                        <td>
                                            <a href="{{ route('student.payment-plan.show', $plan->id) }}" class="btn btn-sm btn-primary" title="{{ __('btn_view_details') }}">
                                                <i class="fas fa-eye"></i> {{ __('btn_view') }}
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> {{ __('no_payment_plans_found') }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Next Upcoming Installments -->
            @if($upcoming_installments->count() > 0)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('upcoming_installments') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_installment') }}</th>
                                        <th>{{ __('field_fee') }}</th>
                                        <th>{{ __('field_amount') }}</th>
                                        <th>{{ __('field_due_date') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcoming_installments as $installment)
                                    <tr>
                                        <td>#{{ $installment->installment_number }}</td>
                                        <td>{{ $installment->paymentPlan->fee->category->title ?? '' }}</td>
                                        <td>{{ number_format($installment->amount + ($installment->late_fee ?? 0), 2) }} {!! $setting->currency_symbol !!}</td>
                                        <td>{{ date('d M Y', strtotime($installment->due_date)) }}</td>
                                        <td>{!! $installment->status_badge !!}</td>
                                        <td>
                                            <a href="{{ route('student.payment-plan.show', $installment->payment_plan_id) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> {{ __('btn_view') }}
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
