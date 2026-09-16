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

            @if(isset($stats['overpaid_count']) && $stats['overpaid_count'] > 0)
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-purple">{{ $stats['overpaid_count'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('Overpaid Fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-plus-circle f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

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
                                        <option value="4" @if($selected_payment_status == '4') selected @endif>{{ __('payment_plan') }}</option>
                                        <option value="5" @if($selected_payment_status == '5') selected @endif>{{ __('filter_overpaid_fees') }}</option>
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
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @isset($rows)
                            @can($access.'-print')
                            <form class="needs-validation d-inline" novalidate method="get" action="{{ route($route.'.multiprint') }}" target="_blank">
                                <input type="hidden" name="fees" class="fees" value="">
                                <button type="submit" class="btn btn-sm btn-dark print-btn"><i class="fas fa-print"></i> {{ __('btn_print') }} {{ __('field_selected') }}</button>
                            </form>
                            @endcan
                            @endisset
                        </div>
                        
                        <!-- Column Visibility Toggle -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="columnVisibilityBtn" data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-columns"></i> {{ __('columns') }}
                            </button>
                            <div class="dropdown-menu dropdown-menu-right p-3" aria-labelledby="columnVisibilityBtn" style="min-width: 220px; max-height: 400px; overflow-y: auto;">
                                <h6 class="dropdown-header">{{ __('show_hide_columns') }}</h6>
                                <div class="dropdown-divider"></div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-receipt" data-column="2" checked>
                                    <label class="form-check-label" for="col-receipt">{{ __('field_receipt') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-matricule" data-column="3" checked>
                                    <label class="form-check-label" for="col-matricule">{{ __('field_matricule') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-feetype" data-column="4" checked>
                                    <label class="form-check-label" for="col-feetype">{{ __('field_fees_type') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-fee" data-column="5" checked>
                                    <label class="form-check-label" for="col-fee">{{ __('field_fee') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-discount" data-column="6" checked>
                                    <label class="form-check-label" for="col-discount">{{ __('field_discount') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-fine" data-column="7" checked>
                                    <label class="form-check-label" for="col-fine">{{ __('field_fine_amount') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-net" data-column="8" checked>
                                    <label class="form-check-label" for="col-net">{{ __('field_net_amount') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-paid" data-column="9" checked>
                                    <label class="form-check-label" for="col-paid">{{ __('field_paid') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-balance" data-column="10" checked>
                                    <label class="form-check-label" for="col-balance">{{ __('field_remaining_balance') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-paydate" data-column="11" checked>
                                    <label class="form-check-label" for="col-paydate">{{ __('field_pay_date') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-status" data-column="12" checked>
                                    <label class="form-check-label" for="col-status">{{ __('field_status') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-method" data-column="13" checked>
                                    <label class="form-check-label" for="col-method">{{ __('field_payment_method') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-note" data-column="14" checked>
                                    <label class="form-check-label" for="col-note">{{ __('field_note') }}</label>
                                </div>
                                <div class="dropdown-divider"></div>
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-xs btn-outline-primary" id="showAllColumns">{{ __('show_all') }}</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" id="hideOptionalColumns">{{ __('minimal') }}</button>
                                </div>
                            </div>
                        </div>
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
                                        <th>{{ __('field_matricule') }}</th>
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
                                                // Net of overpayment credit moved to another fee, which
                                                // counts on that fee instead.
                                                $display_paid = min((float) $row->net_paid_amount, (float) $row->total_amount);
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
                                            @php
                                                $display_balance = max(0, $row->net_remaining_balance);
                                            @endphp
                                            <strong class="{{ $display_balance > 0 ? 'text-danger' : 'text-success' }}">
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
                                            @if($row->credit_moved_out > 0)
                                            <br>
                                            <small class="badge badge-info" title="{{ __('Overpayment on this fee, moved as credit to another fee') }}"><i class="fas fa-exchange-alt"></i> {{ number_format($row->credit_moved_out, 2) }} {{ __('moved to another fee') }}</small>
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
                                            @if($row->payment_plan_id && $row->paymentPlan && $row->paymentPlan->status == 'active')
                                            <!-- Fee has active payment plan - show View Payment Plan button -->
                                            <a href="{{ route('admin.payment-plan.show', $row->payment_plan_id) }}" class="btn btn-icon btn-success btn-sm" title="{{ __('view_payment_plan') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <span class="badge badge-info">{{ __('payment_plan') }}</span>
                                            @elseif($row->hasPendingMultiPayment())
                                            <!-- Fee has pending multi-payment - show disabled button -->
                                            <button type="button" class="btn btn-icon btn-secondary btn-sm" disabled title="This fee has a pending multi-payment">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            <span class="badge badge-warning">Multi-Pay Pending</span>
                                            @elseif($row->status == 0 || $row->status == 1 || $row->status == 2)
                                            <!-- Fee can be paid directly (including overpayment on fully paid) -->
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payModal-{{ $row->id }}" title="{{ $row->status == 1 ? __('add_overpayment') : ($row->status == 2 ? __('pay_balance') : __('make_payment')) }}">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <!-- Include Pay modal -->
                                            @include($view.'.pay')
                                            @endif

                                            @if($row->status == 1 || $row->status == 2 || ($row->payment_plan_id && $row->paymentPlan))
                                            @can($access.'-print')
                                            @if(isset($print))
                                            <a href="#" class="btn btn-icon btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $row->id]) }}', '{{ $title }}', 1000, 600);" title="{{ __('btn_print') }}">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            @endif

                                            @if($row->isNetOverpaid())
                                            <!-- Overpayment still held on this fee, not yet applied elsewhere -->
                                            <span class="badge badge-primary" title="{{ __('overpayment_credit_generated') }}">
                                                <i class="fas fa-plus-circle"></i> +{{ number_format($row->net_overpayment_amount, 2) }}
                                            </span>
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
        // Wait for DataTable to be initialized by footer_script, then get the instance
        // Use a small delay to ensure footer_script has initialized it first
        setTimeout(function() {
            // Get existing DataTable instance (already initialized with export buttons in footer_script)
            var table = $('#export-table').DataTable();
            
            // Column visibility toggle
            $('.column-toggle').on('change', function() {
                var columnIndex = $(this).data('column');
                var column = table.column(columnIndex);
                column.visible($(this).is(':checked'));
                
                // Save preferences to localStorage
                saveColumnPreferences();
            });
            
            // Show all columns
            $('#showAllColumns').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.column-toggle').prop('checked', true);
                table.columns().visible(true);
                saveColumnPreferences();
            });
            
            // Show minimal columns (hide optional ones)
            $('#hideOptionalColumns').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Show only: checkbox, #, matricule, fee type, net amount, paid, balance, status, action
                var minimalColumns = [0, 1, 3, 4, 8, 9, 10, 12, 15]; // Column indices to show
                
                for (var i = 0; i < table.columns().nodes().length; i++) {
                    table.column(i).visible(minimalColumns.includes(i));
                }
                
                // Update checkboxes
                $('.column-toggle').each(function() {
                    var colIndex = parseInt($(this).data('column'));
                    $(this).prop('checked', minimalColumns.includes(colIndex));
                });
                
                saveColumnPreferences();
            });
            
            // Save column preferences to localStorage
            function saveColumnPreferences() {
                var prefs = {};
                $('.column-toggle').each(function() {
                    prefs[$(this).data('column')] = $(this).is(':checked');
                });
                localStorage.setItem('feesReportColumnPrefs', JSON.stringify(prefs));
            }
            
            // Load column preferences from localStorage
            function loadColumnPreferences() {
                var prefs = localStorage.getItem('feesReportColumnPrefs');
                if (prefs) {
                    prefs = JSON.parse(prefs);
                    $('.column-toggle').each(function() {
                        var colIndex = $(this).data('column');
                        if (prefs.hasOwnProperty(colIndex)) {
                            $(this).prop('checked', prefs[colIndex]);
                            table.column(colIndex).visible(prefs[colIndex]);
                        }
                    });
                }
            }
            
            // Load preferences on page load
            loadColumnPreferences();
        }, 100);
        
        // Prevent dropdown from closing when clicking inside
        $(document).on('click', '#columnVisibilityBtn + .dropdown-menu', function(e) {
            e.stopPropagation();
        });
        
        // Print functionality
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
