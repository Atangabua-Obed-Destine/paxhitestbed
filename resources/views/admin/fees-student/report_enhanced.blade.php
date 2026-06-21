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
                                <h4 class="text-c-blue">{{ $stats['total_fees'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('total_fees_processed') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-file-text f-28"></i>
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
                                <h4 class="text-c-green">{{ $stats['fully_paid_count'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('total_fully_paid_fees') }}</h6>
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
                                <h4 class="text-c-red">{{ $stats['cancelled_count'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('total_cancelled_fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-x-circle f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card bg-c-blue text-white">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_amount') }}</h6>
                        <h3 class="text-white">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$stats['total_amount'], $setting->decimal_place, '.', ',') }}
                            @else
                            {{ number_format((float)$stats['total_amount'], 2, '.', ',') }}
                            @endif
                            {!! $setting->currency_symbol !!}
                        </h3>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card bg-c-green text-white">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_collected') }}</h6>
                        <h3 class="text-white">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$stats['total_collected'], $setting->decimal_place, '.', ',') }}
                            @else
                            {{ number_format((float)$stats['total_collected'], 2, '.', ',') }}
                            @endif
                            {!! $setting->currency_symbol !!}
                        </h3>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
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
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.report') }}">
                            <div class="row gx-2">

                                @include('common.inc.fees_search_filter')

                                <div class="form-group col-md-2">
                                    <label for="category">{{ __('field_fees_type') }}</label>
                                    <select class="form-control" name="category" id="category">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $categories as $category )
                                        <option value="{{ $category->id }}" @if( $selected_category == $category->id) selected @endif>{{ $category->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="payment_status">{{ __('filter_payment_status') }}</label>
                                    <select class="form-control" name="payment_status" id="payment_status">
                                        <option value="all" @if($selected_payment_status == 'all') selected @endif>{{ __('all_payment_statuses') }}</option>
                                        <option value="1" @if($selected_payment_status == '1') selected @endif>{{ __('filter_fully_paid_fees') }}</option>
                                        <option value="2" @if($selected_payment_status == '2') selected @endif>{{ __('filter_partially_paid_fees') }}</option>
                                        <option value="3" @if($selected_payment_status == '3') selected @endif>{{ __('status_canceled') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="student_id">{{ __('field_student_id') }}</label>
                                    <input type="text" class="form-control" name="student_id" id="student_id" value="{{ $selected_student_id }}">
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
                    <div class="card-header">
                        @isset($rows)
                        @can($access.'-print')
                        <form class="needs-validation d-inline" novalidate method="get" action="{{ route($route.'.multiprint') }}" target="_blank">
                            <input type="hidden" name="fees" class="fees" value="">
                            <button type="submit" class="btn btn-sm btn-dark print-btn"><i class="fas fa-print"></i> {{ __('btn_print') }} {{ __('field_selected') }}</button>
                        </form>
                        @endcan
                        @endisset
                    </div>

                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="export-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="checkbox checkbox-success d-inline">
                                                <input type="checkbox" id="checkbox" class="all_select">
                                                <label for="checkbox" class="cr" style="margin-bottom: 0px;"></label>
                                            </div>
                                        </th>
                                        <th>#</th>
                                        <th>{{ __('field_receipt') }}</th>
                                        <th>{{ __('field_student_id') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_fee') }}</th>
                                        <th>{{ __('field_discount') }}</th>
                                        <th>{{ __('field_fine_amount') }}</th>
                                        <th>{{ __('field_net_amount') }}</th>
                                        <th>{{ __('field_paid') }}</th>
                                        <th>{{ __('field_remaining_balance') }}</th>
                                        <th>{{ __('field_pay_date') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_payment_method') }}</th>
                                        <th>{{ __('field_note') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" data_id="{{ $row->id }}" id="checkbox-{{ $row->id }}" value="{{ $row->id }}">
                                                <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                            </div>
                                        </td>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $print->prefix ?? '' }}{{ str_pad($row->id, 6, '0', STR_PAD_LEFT) }}</td>
                                        <td>
                                            @isset($row->studentEnroll->student->student_id)
                                            <a href="{{ route('admin.student.show', $row->studentEnroll->student->id) }}">
                                            #{{ $row->studentEnroll->student->student_id ?? '' }}
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
                                            <span class="{{ $row->paid_amount > 0 ? 'text-success' : 'text-muted' }}">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->paid_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->paid_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="{{ $row->remaining_balance > 0 ? 'text-danger' : 'text-success' }}">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->remaining_balance, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->remaining_balance, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                            
                                            @if($row->status == 2)
                                            <br>
                                            <small class="badge badge-warning">{{ __('partial_payment') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status == 1 || $row->status == 2)
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->pay_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->pay_date)) }}
                                            @endif
                                            @endif
                                        </td>
                                        <td>
                                            {!! $row->status_badge !!}
                                            @if($row->hasPendingMultiPayment())
                                            <br><span class="badge badge-warning mt-1">
                                                <i class="fas fa-clock"></i> Multi-Pay Pending ({{ number_format($row->getPendingMultiPaymentAmount(), 2) }})
                                            </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if( $row->payment_method == 1 )
                                            {{ __('payment_method_card') }}
                                            @elseif( $row->payment_method == 2 )
                                            {{ __('payment_method_cash') }}
                                            @elseif( $row->payment_method == 3 )
                                            {{ __('payment_method_cheque') }}
                                            @elseif( $row->payment_method == 4 )
                                            {{ __('payment_method_bank') }}
                                            @elseif( $row->payment_method == 5 )
                                            {{ __('payment_method_e_wallet') }}
                                            @elseif( $row->payment_method == 6 )
                                            {{ __('PayPal') }}
                                            @elseif( $row->payment_method == 7 )
                                            {{ __('Stripe') }}
                                            @elseif( $row->payment_method == 8 )
                                            {{ __('RazorPay') }}
                                            @elseif( $row->payment_method == 9 )
                                            {{ __('PayStack') }}
                                            @elseif( $row->payment_method == 10 )
                                            {{ __('Flutterwave') }}
                                            @endif
                                        </td>
                                        <td>{!! $row->note !!}</td>
                                        <td>
                                            @if($row->hasPendingMultiPayment())
                                            <!-- Fee has pending multi-payment - show disabled button -->
                                            <button type="button" class="btn btn-icon btn-secondary btn-sm" disabled title="This fee has a pending multi-payment">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            <span class="badge badge-warning">Multi-Pay Pending</span>
                                            @elseif($row->status == 0 || $row->status == 2)
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payModal-{{ $row->id }}" title="{{ $row->status == 2 ? __('pay_balance') : __('make_payment') }}">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <!-- Include Pay modal -->
                                            @include($view.'.pay')

                                            @elseif($row->status == 1)
                                            @can($access.'-print')
                                            @if(isset($print))
                                            <a href="#" class="btn btn-icon btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $row->id]) }}', '{{ $title }}', 1000, 600);">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            
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
                                  @endforeach
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
<script type="text/javascript">
    "use strict";
    $(document).ready(function() {
        $(".print-btn").on('click',function(e){

            var numberOfChecked = $("input[data_id]:checked").length;
            if(numberOfChecked <= 0){
                e.preventDefault();
                alert("{{ __('select') }} {{ __('field_receipt') }}");
            }

            var fees = [];
            $.each($("input[data_id]:checked"), function(){
                fees.push($(this).val());
            });

            $(".fees").val( fees.join(',') );
        });
    });

    // checkbox all-check-button selector
    $(".all_select").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $("input:checkbox").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $("input:checkbox").prop('checked', false);
        }
    });
</script>
@endsection
