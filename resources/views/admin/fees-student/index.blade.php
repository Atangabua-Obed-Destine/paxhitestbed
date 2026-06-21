@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Statistics Cards -->
            @if(isset($stats))
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-yellow">{{ $stats['total_fees'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('fees_due') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-alert-triangle f-28"></i>
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
                                <h4 class="text-c-red">{{ $stats['unpaid_count'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('total_unpaid_fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-alert-circle f-28"></i>
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
                                <h4 class="text-c-yellow">{{ $stats['partially_paid_count'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('total_partially_paid_fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-alert-triangle f-28"></i>
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
                                <h4 class="text-c-blue">
                                    @if(isset($setting->decimal_place))
                                    {{ number_format((float)$stats['total_amount_due'], $setting->decimal_place, '.', ',') }}
                                    @else
                                    {{ number_format((float)$stats['total_amount_due'], 2, '.', ',') }}
                                    @endif
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_amount_due') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-file-text f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-6">
                <div class="card bg-c-green text-white">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_collected') }}</h6>
                        <h3 class="text-white">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$stats['total_paid'], $setting->decimal_place, '.', ',') }}
                            @else
                            {{ number_format((float)$stats['total_paid'], 2, '.', ',') }}
                            @endif
                            {!! $setting->currency_symbol !!}
                        </h3>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-6">
                <div class="card bg-c-yellow text-white">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('outstanding_balance') }}</h6>
                        <h3 class="text-white">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$stats['total_remaining'], $setting->decimal_place, '.', ',') }}
                            @else
                            {{ number_format((float)$stats['total_remaining'], 2, '.', ',') }}
                            @endif
                            {!! $setting->currency_symbol !!}
                        </h3>
                    </div>
                </div>
            </div>
            @endif

            <!-- Filter Section -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">

                                @include('common.inc.fees_search_filter')

                                <div class="form-group col-md-2">
                                    <label for="category">{{ __('field_fees_type') }} <span>*</span></label>
                                    <select class="form-control" name="category" id="category">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $categories as $category )
                                        <option value="{{ $category->id }}" @if( $selected_category == $category->id) selected @endif>{{ $category->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_fees_type') }}
                                    </div>
                                </div>
                                
                                <div class="form-group col-md-2">
                                    <label for="payment_status">{{ __('filter_payment_status') }}</label>
                                    <select class="form-control" name="payment_status" id="payment_status">
                                        <option value="all" @if($selected_payment_status == 'all') selected @endif>{{ __('all_payment_statuses') }}</option>
                                        <option value="0" @if($selected_payment_status == '0') selected @endif>{{ __('filter_unpaid_fees') }}</option>
                                        <option value="2" @if($selected_payment_status == '2') selected @endif>{{ __('filter_partially_paid_fees') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="student_id">{{ __('field_student_id') }}</label>
                                    <input type="text" class="form-control" name="student_id" id="student_id" value="{{ $selected_student_id }}" placeholder="Search...">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_student_id') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="col-sm-12">
                <div class="card">
                    @if(isset($rows))
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="export-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_fee') }}</th>
                                        <th>{{ __('field_discount') }}</th>
                                        <th>{{ __('field_fine_amount') }}</th>
                                        <th>{{ __('field_net_amount') }}</th>
                                        <th>{{ __('field_paid') }}</th>
                                        <th>{{ __('field_remaining_balance') }}</th>
                                        <th>{{ __('field_due_date') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @forelse( $rows as $key => $row )
                                    <tr class="{{ $row->status == 0 && \Carbon\Carbon::parse($row->due_date)->isPast() ? 'table-danger' : '' }}">
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            @isset($row->studentEnroll)
                                            <a href="{{ route('admin.student.show', $row->studentEnroll->student->id) }}">
                                            #{{ $row->studentEnroll->matricule ?? $row->studentEnroll->student->student_id ?? '' }}
                                            </a>
                                            <br>
                                            <small>{{ $row->studentEnroll->student->first_name ?? '' }} {{ $row->studentEnroll->student->last_name ?? '' }}</small>
                                            @endisset
                                        </td>
                                        <td>{{ $row->category->title ?? '' }}</td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fee_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fee_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->discount_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->discount_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fine_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fine_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->total_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->total_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @php
                                                $display_paid = min((float) $row->paid_amount, (float) $row->total_amount);
                                                $display_balance = max(0, (float) $row->remaining_balance);
                                            @endphp
                                            <span class="{{ $display_paid > 0 ? 'text-success' : 'text-muted' }}">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$display_paid, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$display_paid, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-danger">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$display_balance, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$display_balance, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                            
                                            @if($row->status == 2)
                                            <br>
                                            <small class="badge badge-warning">{{ __('partial_payment') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->due_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->due_date)) }}
                                            @endif
                                            
                                            @if($row->status == 0 && \Carbon\Carbon::parse($row->due_date)->isPast())
                                            <br>
                                            <small class="badge badge-danger">{{ __('overdue_fees') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            {!! $row->status_badge !!}
                                            @if($row->payment_plan_id && $row->paymentPlan)
                                            <br><span class="badge badge-info mt-1">
                                                <i class="fas fa-calendar-alt"></i> {{ __('payment_plan') }}
                                                @if($row->paymentPlan->status == 'active')
                                                ({{ __('status_active') }})
                                                @elseif($row->paymentPlan->status == 'completed')
                                                ({{ __('status_completed') }})
                                                @elseif($row->paymentPlan->status == 'cancelled')
                                                ({{ __('status_cancelled') }})
                                                @endif
                                            </span>
                                            @endif
                                            @if($row->hasPendingMultiPayment())
                                            <br><span class="badge badge-warning mt-1">
                                                <i class="fas fa-clock"></i> Multi-Pay Pending ({{ number_format($row->getPendingMultiPaymentAmount(), 2) }})
                                            </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->payment_plan_id && $row->paymentPlan && $row->paymentPlan->status == 'active')
                                            <!-- Fee has active payment plan - show View Payment Plan button -->
                                            <a href="{{ route('admin.payment-plan.show', $row->payment_plan_id) }}" class="btn btn-icon btn-success btn-sm" title="{{ __('view_payment_plan') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @elseif($row->hasPendingMultiPayment())
                                            <!-- Fee has pending multi-payment - show disabled button -->
                                            <button type="button" class="btn btn-icon btn-secondary btn-sm" disabled title="This fee has a pending multi-payment">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            @elseif($row->status == 0 || $row->status == 2)
                                            <!-- Fee can be paid directly -->
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payModal-{{ $row->id }}" title="{{ $row->status == 2 ? __('pay_balance') : __('make_payment') }}">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <!-- Include Pay modal -->
                                            @include($view.'.pay')

                                            @can($access.'-action')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" title="{{ __('status_canceled') }}" data-bs-toggle="modal" data-bs-target="#cancelModal-{{ $row->id }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <!-- Include Cancel modal -->
                                            @include($view.'.cancel')
                                            @endcan
                                            @endif

                                            @if($row->status == 1 || $row->status == 2 || ($row->payment_plan_id && $row->paymentPlan))
                                            @can($access.'-print')
                                            @if(isset($print))
                                            <a href="{{ route($route.'.print', ['id' => $row->id]) }}" target="_blank" class="btn btn-icon btn-dark btn-sm" title="{{ __('btn_print') }}">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            @endif
                                            
                                            @if($row->status == 1)
                                            @can($access.'-action')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" title="{{ __('status_unpaid') }}" data-bs-toggle="modal" data-bs-target="#unpayModal-{{ $row->id }}">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <!-- Include Unpay modal -->
                                            @include($view.'.unpay')
                                            @endcan
                                            @endif
                                        </td>
                                    </tr>
                                  @empty
                                    <tr>
                                        <td colspan="12" class="text-center">{{ __('no_fees_found') }}</td>
                                    </tr>
                                  @endforelse
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    @endif

                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
@isset($print)
@if (\Session::has('receipt'))
<script type="text/javascript">
    PopupWin('{{ route($route.'.print', ['id' => \Session::get('receipt')]) }}', '{{ $title }}', 1000, 600);
</script>
@endif
@endisset
@endsection
