@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            
            <!-- Statistics Cards (only shown when student is selected) -->
            @if(isset($stats) && $selected_student)
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-blue">{{ $stats['total_fees'] }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('field_total') }} {{ __('field_fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-file-text f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card bg-c-green text-white">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_collected') }}</h6>
                        <h4 class="text-white">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$stats['total_paid'], $setting->decimal_place, '.', ',') }} 
                            @else
                            {{ number_format((float)$stats['total_paid'], 2, '.', ',') }} 
                            @endif 
                            {!! $setting->currency_symbol !!}
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card bg-c-yellow text-white">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('outstanding_balance') }}</h6>
                        <h4 class="text-white">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$stats['total_remaining'], $setting->decimal_place, '.', ',') }} 
                            @else
                            {{ number_format((float)$stats['total_remaining'], 2, '.', ',') }} 
                            @endif 
                            {!! $setting->currency_symbol !!}
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <h6 class="text-muted">{{ __('payment_progress') }}</h6>
                        <h4>
                            @php
                            $progress = $stats['total_amount_due'] > 0 ? ($stats['total_paid'] / $stats['total_amount_due']) * 100 : 0;
                            @endphp
                            {{ number_format($progress, 1) }}%
                        </h4>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar 
                                @if($progress < 30) bg-danger
                                @elseif($progress < 70) bg-warning
                                @else bg-success
                                @endif" 
                                role="progressbar" 
                                style="width: {{ $progress }}%;" 
                                aria-valuenow="{{ $progress }}" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Filter Section -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route .'.student-fees') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-4">
                                    <label for="student">{{ __('field_student') }} <span>*</span></label>
                                    <select class="form-control select2" name="student" id="student" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($students as $student)
                                        <option value="{{ $student->id }}" @if($selected_student == $student->id) selected @endif>{{ $student->student_id }} - {{ $student->first_name }} {{ $student->last_name }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_student') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="category">{{ __('field_fees_type') }}</label>
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
                                
                                <div class="form-group col-md-3">
                                    <label for="payment_status">{{ __('filter_payment_status') }}</label>
                                    <select class="form-control" name="payment_status" id="payment_status">
                                        <option value="all" @if($selected_payment_status == 'all') selected @endif>{{ __('all_payment_statuses') }}</option>
                                        <option value="0" @if($selected_payment_status == '0') selected @endif>{{ __('filter_unpaid_fees') }}</option>
                                        <option value="2" @if($selected_payment_status == '2') selected @endif>{{ __('filter_partially_paid_fees') }}</option>
                                        <option value="1" @if($selected_payment_status == '1') selected @endif>{{ __('filter_fully_paid_fees') }}</option>
                                        <option value="due" @if($selected_payment_status == 'due') selected @endif>{{ __('fees_due') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        @isset($rows)
                        @if($rows->count() > 0)
                        <div class="table-responsive">
                            <table id="report-table" class="display table nowrap table-striped table-hover" style="width:100%">
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
                                        <th>{{ __('field_paid') }}</th>
                                        <th>{{ __('field_remaining_balance') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_due_date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr class="{{ $row->status == 0 && \Carbon\Carbon::parse($row->due_date)->isPast() ? 'table-danger' : '' }}">
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->studentEnroll->session->title ?? '' }}</td>
                                        <td>{{ $row->studentEnroll->semester->title ?? '' }}</td>
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
                                            <strong>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->total_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->total_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                        </td>
                                        <td>
                                            @php
                                                $display_paid = min((float) $row->paid_amount, (float) $row->total_amount);
                                            @endphp
                                            <span class="text-success">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$display_paid, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$display_paid, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </span>
                                            
                                            @if($row->status == 2 && $row->approvedReceipts->count() > 0)
                                            <br>
                                            <small class="badge badge-info">{{ $row->approvedReceipts->count() }} {{ __('payments') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $display_balance = max(0, $row->remaining_balance);
                                            @endphp
                                            <strong class="{{ $display_balance > 0 ? 'text-danger' : 'text-success' }}">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$display_balance, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$display_balance, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                            
                                            @if($display_balance > 0)
                                            <br>
                                            <div class="progress mt-1" style="height: 4px;">
                                                @php
                                                $payment_progress = $row->total_amount > 0 ? (min((float)$row->paid_amount, (float)$row->total_amount) / $row->total_amount) * 100 : 0;
                                                @endphp
                                                <div class="progress-bar 
                                                    @if($payment_progress < 30) bg-danger
                                                    @elseif($payment_progress < 70) bg-warning
                                                    @else bg-success
                                                    @endif" 
                                                    style="width: {{ $payment_progress }}%;">
                                                </div>
                                            </div>
                                            @endif
                                            @if($row->isOverpaid() && $row->generatedCredits->isNotEmpty())
                                            <br>
                                            <small class="badge badge-info" title="{{ __('overpayment_converted_to_credit') }}"><i class="fas fa-exchange-alt"></i> {{ number_format($row->overpayment_amount, 2) }} credited</small>
                                            @endif
                                        </td>
                                        <td>{!! $row->status_badge !!}</td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->due_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->due_date)) }}
                                            @endif
                                            
                                            @if($row->status != 1 && \Carbon\Carbon::parse($row->due_date)->isPast())
                                            <br>
                                            <small class="badge badge-danger">{{ __('overdue_fees') }}</small>
                                            @endif
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th>{{ __('field_grand_total') }}</th>
                                        <th>{{ number_format((float)$rows->sum('fee_amount'), $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</th>
                                        <th>{{ number_format((float)$rows->sum('discount_amount'), $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</th>
                                        <th>{{ number_format((float)$rows->sum('fine_amount'), $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</th>
                                        <th>{{ number_format((float)$rows->sum(function($row){ return $row->total_amount; }), $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</th>
                                        <th>{{ number_format((float)$rows->sum('paid_amount'), $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</th>
                                        <th>{{ number_format((float)$rows->sum(function($row){ return $row->remaining_balance; }), $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-info">
                            <i class="feather icon-info"></i> {{ __('no_fees_found') }}
                        </div>
                        @endif
                        @else
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle"></i> {{ __('select') }} {{ __('field_student') }}
                        </div>
                        @endisset
                        <!-- [ Data table ] end -->
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
    @include('admin.report.script')
@endsection
