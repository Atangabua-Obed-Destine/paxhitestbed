@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.expense') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="category">{{ __('field_category') }}</label>
                                    <select class="form-control" name="category" id="category">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach( $categories as $category )
                                        <option value="{{ $category->id }}" @if($selected_category == $category->id) selected @endif>{{ $category->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_category') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="duration">{{ __('field_duration') }}</label>
                                    <select class="form-control" name="duration" id="duration">
                                        <option value="">{{ __('all') }}</option>
                                        <option value="1" @if($selected_duration == '1') selected @endif>{{ __('cal_1_month') }}</option>
                                        <option value="3" @if($selected_duration == '3') selected @endif>{{ __('cal_3_months') }}</option>
                                        <option value="6" @if($selected_duration == '6') selected @endif>{{ __('cal_6_months') }}</option>
                                        <option value="12" @if($selected_duration == '12') selected @endif>{{ __('cal_1_year') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_duration') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            @isset($rows)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="report-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <caption>{{ date($setting->date_format ?? "Y-m-d", strtotime($start_date)) }} - {{ date($setting->date_format ?? "Y-m-d", strtotime($end_date)) }}</caption>

                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_category') }}</th>
                                        <th>{{ __('field_invoice_id') }}</th>
                                        <th>{{ __('field_date') }}</th>
                                        <th>{{ __('field_amount') }}</th>
                                        <th>{{ __('field_payment_method') }}</th>
                                        <th>{{ __('field_recorded_by') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{!! str_limit($row->title, 30, ' ...') !!}</td>
                                        <td>{{ $row->category->title ?? '' }}</td>
                                        <td>{{ $row->invoice_id }}</td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->date)) }}
                                            @endif
                                        </td>
                                        <td>{{ number_format((float)$row->amount, $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</td>
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
                                            @endif
                                        </td>
                                        <td>#{{ $row->recordedBy->staff_id ?? '' }}</td>
                                    </tr>
                                  @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th>{{ __('field_grand_total') }}</th>
                                        <th>{{ number_format((float)$rows->sum('amount'), $setting->decimal_place ?? 2) }} {!! $setting->currency_symbol !!}</th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>
            </div>
            @endisset
            
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
    @include('admin.report.script')
@endsection