@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5>{{ $title }}</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#!">{{ __('reports') }}</a></li>
                            <li class="breadcrumb-item"><a href="#!">{{ $title }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-yellow">{{ $stats['total_fees'] }}</h4>
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
                                    {{ number_format((float)$stats['total_due'], $setting->decimal_place, '.', '') }} 
                                    @else
                                    {{ number_format((float)$stats['total_due'], 2, '.', '') }} 
                                    @endif
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_amount_due') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-dollar-sign f-28"></i>
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
                                <h4 class="text-c-green">
                                    @if(isset($setting->decimal_place))
                                    {{ number_format((float)$stats['total_paid'], $setting->decimal_place, '.', '') }} 
                                    @else
                                    {{ number_format((float)$stats['total_paid'], 2, '.', '') }} 
                                    @endif
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_paid') }}</h6>
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
                                <h4 class="text-c-red">
                                    @if(isset($setting->decimal_place))
                                    {{ number_format((float)$stats['total_remaining'], $setting->decimal_place, '.', '') }} 
                                    @else
                                    {{ number_format((float)$stats['total_remaining'], 2, '.', '') }} 
                                    @endif
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_remaining') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="feather icon-alert-circle f-28"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter and Export Section -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('filter_options') }}</h5>
                    </div>
                    <div class="card-block">
                        <form method="GET" action="{{ route($route.'.index') }}">
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label for="session_id">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session_id" id="session_id">
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $session)
                                        <option value="{{ $session->id }}" {{ request('session_id') == $session->id ? 'selected' : '' }}>
                                            {{ $session->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="semester_id">{{ __('field_semester') }}</label>
                                    <select class="form-control" name="semester_id" id="semester_id">
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($semesters as $semester)
                                        <option value="{{ $semester->id }}" {{ request('semester_id') == $semester->id ? 'selected' : '' }}>
                                            {{ $semester->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="category_id">{{ __('field_fees_type') }}</label>
                                    <select class="form-control" name="category_id" id="category_id">
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="search">{{ __('field_student') }}</label>
                                    <input type="text" class="form-control" name="search" id="search" 
                                           value="{{ request('search') }}" placeholder="{{ __('search_student') }}">
                                </div>

                                <div class="form-group col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> {{ __('btn_filter') }}
                                    </button>
                                    <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i> {{ __('btn_reset') }}
                                    </a>
                                    <a href="{{ route($route.'.export') }}?{{ http_build_query(request()->except('page')) }}" class="btn btn-success float-right">
                                        <i class="fas fa-download"></i> {{ __('btn_export_csv') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Partial Payment Report Table -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('partial_payment_list') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_student') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_total_amount_due') }}</th>
                                        <th>{{ __('field_paid_amount') }}</th>
                                        <th>{{ __('field_remaining_balance') }}</th>
                                        <th>{{ __('field_due_date') }}</th>
                                        <th>{{ __('field_payments') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @forelse( $fees as $key => $fee )
                                    <tr>
                                        <td>{{ $fees->firstItem() + $key }}</td>
                                        <td>
                                            {{ $fee->studentEnroll->student->first_name ?? '' }} 
                                            {{ $fee->studentEnroll->student->last_name ?? '' }}
                                            <br>
                                            <small class="text-muted">ID: {{ $fee->studentEnroll->student->student_id ?? '' }}</small>
                                        </td>
                                        <td>{{ $fee->studentEnroll->session->title ?? '' }}</td>
                                        <td>{{ $fee->studentEnroll->semester->title ?? '' }}</td>
                                        <td>{{ $fee->category->title ?? '' }}</td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fee->total_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fee->total_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            <span class="text-success">
                                            <strong>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fee->paid_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fee->paid_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-danger">
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
                                            <br><span class="badge badge-danger">{{ __('status_overdue') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                {{ $fee->approvedReceipts->count() }} {{ __('payments') }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $pendingReceipt = $fee->pendingReceipt;
                                            @endphp
                                            @if($pendingReceipt)
                                                <a href="{{ route('admin.payment-verification.show', ['fee', $pendingReceipt->id]) }}" 
                                                   class="btn btn-sm btn-warning" title="{{ __('pending_receipt') }}">
                                                    <i class="fas fa-clock"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">{{ __('no_pending') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                  @empty
                                    <tr>
                                        <td colspan="11" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                  @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        {{ $fees->links() }}
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
