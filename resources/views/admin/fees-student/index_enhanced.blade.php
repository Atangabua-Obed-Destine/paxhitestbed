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
                                        <th>{{ __('field_student_id') }}</th>
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
                                            <strong class="text-danger">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->remaining_balance, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->remaining_balance, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                            
                                            @if($row->remaining_balance > 0 && $row->status == 2)
                                            <br>
                                            <small class="badge badge-info">{{ $row->approvedReceipts->count() }} {{ __('payments') }}</small>
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
                                        </td>
                                        <td>
                                            @if($row->status == 0 || $row->status == 2)
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

                                            @elseif($row->status == 1)
                                            @can($access.'-print')
                                            @if(isset($print))
                                            <a href="{{ route($route.'.print', ['id' => $row->id]) }}" target="_blank" class="btn btn-icon btn-dark btn-sm">
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
