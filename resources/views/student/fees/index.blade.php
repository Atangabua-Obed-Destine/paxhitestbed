@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Multi-Fee Payment Cart Sidebar -->
<div class="fee-cart-sidebar" id="feeCartSidebar">
    <div class="cart-header">
        <h5><i class="fas fa-shopping-cart"></i> Selected Fees</h5>
        <button class="btn-close-cart" onclick="toggleCart()"><i class="fas fa-times"></i></button>
    </div>
    <div class="cart-body" id="cartBody">
        <p class="text-center text-muted">No fees selected</p>
    </div>
    <div class="cart-footer">
        <div class="cart-total">
            <strong>Total Balance:</strong>
            <span id="cartTotal">0.00</span> {!! $setting->currency_symbol ?? '' !!}
        </div>
        <button class="btn btn-primary btn-block" id="paySelectedBtn" onclick="proceedToPayment()" disabled>
            <i class="fas fa-credit-card"></i> Proceed to Payment
        </button>
    </div>
</div>

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- All Programs Info -->
            @if(isset($programs) && $programs->count() > 1)
            <div class="col-12 mb-3">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ __('Showing fees across all programs') }}</strong>
                    ({{ $programs->count() }} {{ trans_choice('module_program', $programs->count()) }})
                    — {{ __('Use the Program filter below to narrow results') }}
                </div>
            </div>
            @endif
            
            <!-- Statistics Cards -->
            <div class="col-md-6 col-xl-4">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-blue">{{ $total_fees }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('total_fees_assigned') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-file-invoice fa-3x text-c-blue"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-green">
                                    @if(isset($setting->decimal_place))
                                    {{ number_format((float)$total_net_amount, $setting->decimal_place, '.', '') }}
                                    @else
                                    {{ number_format((float)$total_net_amount, 2, '.', '') }}
                                    @endif
                                    {!! $setting->currency_symbol ?? '' !!}
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_net_amount') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-dollar-sign fa-3x text-c-green"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-green">{{ $paid_fees_count }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('fully_paid_fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-check-circle fa-3x text-c-green"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-yellow">{{ $partial_fees_count }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('partially_paid_fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-clock fa-3x text-c-yellow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-red">{{ $unpaid_fees_count }}</h4>
                                <h6 class="text-muted m-b-0">{{ __('unpaid_fees') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-times-circle fa-3x text-c-red"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="card">
                    <div class="card-block">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="text-c-red">
                                    @if(isset($setting->decimal_place))
                                    {{ number_format((float)$total_remaining_balance, $setting->decimal_place, '.', '') }}
                                    @else
                                    {{ number_format((float)$total_remaining_balance, 2, '.', '') }}
                                    @endif
                                    {!! $setting->currency_symbol ?? '' !!}
                                </h4>
                                <h6 class="text-muted m-b-0">{{ __('total_remaining_balance') }}</h6>
                            </div>
                            <div class="col-4 text-right">
                                <i class="fas fa-exclamation-circle fa-3x text-c-red"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fees Table -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route .'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="session">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session" id="session">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $sessions as $session )
                                        <option value="{{ $session->session_id }}" @if( $selected_session == $session->session_id) selected @endif>{{ $session->session->title ?? 'N/A' }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_session') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="semester">{{ __('field_semester') }}</label>
                                    <select class="form-control" name="semester" id="semester">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $semesters as $semester )
                                        <option value="{{ $semester->semester_id }}" @if( $selected_semester == $semester->semester_id) selected @endif>{{ $semester->semester->title ?? 'N/A' }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_semester') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="category">{{ __('field_fees_type') }}</label>
                                    <select class="form-control" name="category" id="category" required>
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $categories as $category )
                                        <option value="{{ $category->id }}" @if( $selected_category == $category->id) selected @endif>{{ $category->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_fees_type') }}
                                    </div>
                                </div>

                                @if(isset($programs) && $programs->count() > 1)
                                <div class="form-group col-md-3">
                                    <label for="program">{{ trans_choice('module_program', 1) }}</label>
                                    <select class="form-control" name="program" id="program">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach($programs as $prog)
                                        <option value="{{ $prog->matricule }}" @if(isset($selected_program) && $selected_program == $prog->matricule) selected @endif>
                                            {{ $prog->program->title ?? 'N/A' }} ({{ $prog->matricule }})
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif

                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        @isset($rows)
                        <div class="mb-3">
                            <button class="btn btn-info btn-sm" onclick="toggleCart()">
                                <i class="fas fa-shopping-cart"></i> View Selected (<span id="selectedCount">0</span>)
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                                <i class="fas fa-times"></i> Clear Selection
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                            </div>
                                        </th>
                                        <th>#</th>
                                        <th>{{ trans_choice('module_program', 1) }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_fee') }}</th>
                                        <th>{{ __('field_discount') }}</th>
                                        <th>{{ __('field_fine_amount') }}</th>
                                        <th>{{ __('field_net_amount') }}</th>
                                        <th>{{ __('field_amount_paid') }}</th>
                                        <th>{{ __('field_remaining_balance') }}</th>
                                        <th>{{ __('field_due_date') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_pay_date') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    @php
                                        // Calculate remaining balance for this row
                                        $discount_amount_calc = 0;
                                        $fine_amount_calc = 0;
                                        $today = date('Y-m-d');
                                        
                                        if($row->status == 0) {
                                            // Calculate discount
                                            if(isset($row->category)) {
                                                foreach($row->category->discounts->where('status', '1') as $discount) {
                                                    $availability = \App\Models\FeesDiscount::availability($discount->id, $row->studentEnroll->student_id);
                                                    if(isset($availability) && $discount->start_date <= $today && $discount->end_date >= $today) {
                                                        if($discount->type == '1') {
                                                            $discount_amount_calc += $discount->amount;
                                                        } else {
                                                            $discount_amount_calc += ($row->fee_amount / 100) * $discount->amount;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            // Calculate fine
                                            if(empty($row->pay_date) || $row->due_date < $row->pay_date) {
                                                $due_date = strtotime($row->due_date);
                                                $today_time = strtotime($today);
                                                $days = (int)(($today_time - $due_date)/86400);
                                                
                                                if($row->due_date < $today && isset($row->category)) {
                                                    foreach($row->category->fines->where('status', '1') as $fine) {
                                                        if($fine->start_day <= $days && $fine->end_day >= $days) {
                                                            if($fine->type == '1') {
                                                                $fine_amount_calc += $fine->amount;
                                                            } else {
                                                                $fine_amount_calc += ($row->fee_amount / 100) * $fine->amount;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            $net_amount_calc = ($row->fee_amount - $discount_amount_calc) + $fine_amount_calc;
                                        } else {
                                            $discount_amount_calc = $row->discount_amount;
                                            $fine_amount_calc = $row->fine_amount;
                                            $net_amount_calc = $row->total_amount;
                                        }
                                        
                                        $amount_paid_calc = $row->paid_amount ?? 0;
                                        $remaining_balance_calc = max(0, $net_amount_calc - $amount_paid_calc);
                                    @endphp
                                    <tr>
                                        <td>
                                            @php
                                                // Check for pending multi-payment
                                                $hasPendingMultiPayment = $row->hasPendingMultiPayment();
                                                $pendingMultiAmount = $hasPendingMultiPayment ? $row->getPendingMultiPaymentAmount() : 0;
                                            @endphp
                                            @if(($row->status == 0 || $row->status == 2) && $remaining_balance_calc > 0 && !$hasPendingMultiPayment)
                                            <div class="form-check">
                                                @php
                                                    $hasPaymentPlan = $row->paymentPlan && $row->paymentPlan->status === 'active';
                                                    $installmentsData = [];
                                                    if ($hasPaymentPlan) {
                                                        foreach ($row->paymentPlan->installments as $installment) {
                                                            $installmentBalance = $installment->amount - ($installment->paid_amount ?? 0);
                                                            if ($installmentBalance > 0) {
                                                                $installmentsData[] = [
                                                                    'id' => $installment->id,
                                                                    'name' => 'Installment ' . $installment->installment_number,
                                                                    'number' => $installment->installment_number,
                                                                    'amount' => number_format($installment->amount, 2, '.', ''),
                                                                    'paid' => number_format($installment->paid_amount ?? 0, 2, '.', ''),
                                                                    'balance' => number_format($installmentBalance, 2, '.', ''),
                                                                    'due_date' => $installment->due_date,
                                                                    'status' => $installment->status
                                                                ];
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                <input class="form-check-input fee-checkbox" 
                                                       type="checkbox" 
                                                       value="{{ $row->id }}"
                                                       data-fee-id="{{ $row->id }}"
                                                       data-fee-type="{{ $row->category->title ?? 'Fee' }}"
                                                       data-session="{{ $row->studentEnroll->session->title ?? '' }}"
                                                       data-semester="{{ $row->studentEnroll->semester->title ?? '' }}"
                                                       data-balance="{{ number_format($remaining_balance_calc, 2, '.', '') }}"
                                                       data-due-date="{{ $row->due_date }}"
                                                       data-has-payment-plan="{{ $hasPaymentPlan ? 'true' : 'false' }}"
                                                       data-payment-plan-name="{{ $hasPaymentPlan ? $row->paymentPlan->plan_name : '' }}"
                                                       data-installments='@json($installmentsData)'
                                                       onchange="updateCart()">
                                            </div>
                                            @endif
                                        </td>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark" style="font-size: 11px; font-weight: 500;">
                                                {{ Str::limit($row->studentEnroll->program->title ?? 'N/A', 25) }}
                                            </span>
                                            <br><small class="text-muted">{{ $row->studentEnroll->matricule ?? '' }}</small>
                                        </td>
                                        <td>{{ $row->studentEnroll->session->title ?? '' }}</td>
                                        <td>{{ $row->studentEnroll->semester->title ?? '' }}</td>
                                        <td>{{ $row->category->title ?? '' }}</td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fee_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fee_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol ?? '' !!}
                                        </td>
                                        <td>
                                            @if($row->status == 0)
                                            @php 
                                            $discount_amount = 0;
                                            $today = date('Y-m-d');
                                            @endphp

                                            @isset($row->category)
                                            @foreach($row->category->discounts->where('status', '1') as $discount)

                                            @php
                                            $availability = \App\Models\FeesDiscount::availability($discount->id, $row->studentEnroll->student_id);
                                            @endphp

                                            @if(isset($availability))
                                            @if($discount->start_date <= $today && $discount->end_date >= $today)
                                                @if($discount->type == '1')
                                                    @php
                                                    $discount_amount = $discount_amount + $discount->amount;
                                                    @endphp
                                                @else
                                                    @php
                                                    $discount_amount = $discount_amount + ( ($row->fee_amount / 100) * $discount->amount);
                                                    @endphp
                                                @endif
                                            @endif
                                            @endif
                                            @endforeach
                                            @endisset

                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$discount_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$discount_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol ?? '' !!}
                                            @else
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->discount_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->discount_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol ?? '' !!}
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status == 0)
                                            @php
                                                $fine_amount = 0;
                                            @endphp
                                            @if(empty($row->pay_date) || $row->due_date < $row->pay_date)
                                                
                                                @php
                                                $due_date = strtotime($row->due_date);
                                                $today = strtotime(date('Y-m-d')); 
                                                $days = (int)(($today - $due_date)/86400);
                                                @endphp

                                                @if($row->due_date < date("Y-m-d"))
                                                @isset($row->category)
                                                @foreach($row->category->fines->where('status', '1') as $fine)
                                                @if($fine->start_day <= $days && $fine->end_day >= $days)
                                                    @if($fine->type == '1')
                                                        @php
                                                        $fine_amount = $fine_amount + $fine->amount;
                                                        @endphp
                                                    @else
                                                        @php
                                                        $fine_amount = $fine_amount + ( ($row->fee_amount / 100) * $fine->amount);
                                                        @endphp
                                                    @endif
                                                @endif
                                                @endforeach
                                                @endisset
                                                @endif
                                            @endif

                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$fine_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$fine_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol ?? '' !!}
                                            @else
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fine_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fine_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol ?? '' !!}
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status == 0)
                                            @php
                                            $net_amount = ($row->fee_amount - $discount_amount) + $fine_amount;
                                            @endphp
                                            @else
                                            @php
                                            $net_amount = $row->total_amount;
                                            @endphp
                                            @endif

                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$net_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$net_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol ?? '' !!}
                                        </td>
                                        <td>
                                            @php
                                            // Cap displayed paid at the fee's net amount: overpayment beyond
                                            // this fee was extracted as a StudentCredit and shown separately
                                            // via the "credited" badge below; counting the raw paid_amount
                                            // here would double-show the same money.
                                            $amount_paid = min((float) ($row->paid_amount ?? 0), (float) $net_amount);
                                            @endphp
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$amount_paid, $setting->decimal_place, '.', '') }}
                                            @else
                                            {{ number_format((float)$amount_paid, 2, '.', '') }}
                                            @endif
                                            {!! $setting->currency_symbol ?? '' !!}
                                        </td>
                                        <td>
                                            @php
                                            $remaining_balance = max(0, $net_amount - $amount_paid);
                                            @endphp
                                            <span class="badge badge-pill @if($remaining_balance == 0) badge-success @else badge-warning @endif">
                                                @if(isset($setting->decimal_place))
                                                {{ number_format((float)$remaining_balance, $setting->decimal_place, '.', '') }}
                                                @else
                                                {{ number_format((float)$remaining_balance, 2, '.', '') }}
                                                @endif
                                                {!! $setting->currency_symbol ?? '' !!}
                                            </span>
                                            @if($row->isOverpaid() && $row->generatedCredits->isNotEmpty())
                                            <br>
                                            <small class="text-info" title="Overpayment converted to credit"><i class="fas fa-exchange-alt"></i> {{ number_format($row->overpayment_amount, 2) }} credited</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->due_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->due_date)) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status == 1)
                                            <span class="badge badge-pill badge-success">{{ __('status_paid') }}</span>
                                            @elseif($row->status == 2)
                                            <span class="badge badge-pill badge-warning">{{ __('status_partial') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-primary">{{ __('status_pending') }}</span>
                                            @endif
                                            
                                            @if($hasPendingMultiPayment)
                                            <br><span class="badge badge-warning mt-1" title="This fee has a pending multi-payment verification">
                                                <i class="fas fa-clock"></i> Multi-Pay Pending ({{ number_format($pendingMultiAmount, 2) }} {!! $setting->currency_symbol ?? '' !!})
                                            </span>
                                            @endif
                                            
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
                                        </td>
                                        <td>
                                            @if($row->pay_date)
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->pay_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->pay_date)) }}
                                            @endif
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status == 1 || $row->status == 2)
                                            <!-- Print Receipt Button for Paid/Partial Fees -->
                                            <a href="#" class="btn btn-dark btn-sm" onclick="PopupWin('{{ route('student.fees.print', $row->id) }}', '{{ __('field_receipt') }}', 1000, 600);" title="{{ __('btn_print') }}">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif

                                            @if($row->payment_plan_id && $row->paymentPlan && $row->paymentPlan->status == 'active')
                                                <!-- Fee has active payment plan -->
                                                <a href="{{ route('student.payment-plan.show', $row->payment_plan_id) }}" class="btn btn-success btn-sm" title="{{ __('view_payment_plan') }}">
                                                    <i class="fas fa-eye"></i> {{ __('view_payment_plan') }}
                                                </a>
                                            @elseif($row->status == 0 || $row->status == 2)
                                                <div class="btn-group" role="group">
                                                    @if(config('payment.status') != 'none')
                                                        @if(config('payment.status') == 'paypal')
                                                        <form method="post" action="{{ route('payment.paypal.process') }}" style="display: inline-block;">
                                                            @csrf
                                                            <input type="hidden" name="fee_id" value="{{ $row->id }}">

                                                            <button type="submit" class="btn btn-primary btn-sm" title="{{ __('pay_online') }}"><i class="fas fa-credit-card"></i> {{ __('btn_pay_online') }}</button>
                                                        </form>
                                                        @elseif(config('payment.status') == 'stripe')
                                                        <a href="{{ route($route.'.pay', $row->id) }}" class="btn btn-primary btn-sm" title="{{ __('pay_online') }}">
                                                            <i class="fas fa-credit-card"></i> {{ __('btn_pay_online') }}
                                                        </a>
                                                        @elseif(config('payment.status') == 'razorpay')
                                                        <form method="post" action="{{ route('payment.razorpay.process') }}" style="display: inline-block;">
                                                            @csrf
                                                            <input type="hidden" name="fee_id" value="{{ $row->id }}">

                                                            <script src="https://checkout.razorpay.com/v1/checkout.js"
                                                                data-key="{{ env('RAZORPAY_KEY') }}"
                                                                data-amount="{{ $remaining_balance * 100 }}"
                                                                data-buttontext="{{ __('btn_pay_online') }}"
                                                                data-name="Student Fees"
                                                                data-description="{{ __('field_receipt') .': ' . str_pad($row->id, 6, '0', STR_PAD_LEFT) }}"
                                                                data-prefill.name="{{ $row->studentEnroll->student->first_name ?? '' }}"
                                                                data-prefill.email="{{ $row->studentEnroll->student->email ?? '' }}">
                                                            </script>
                                                        </form>
                                                        @elseif(config('payment.status') == 'paystack')
                                                        <form method="post" action="{{ route('payment.paystack.process') }}" style="display: inline-block;">
                                                            @csrf
                                                            <input type="hidden" name="fee_id" value="{{ $row->id }}">

                                                            <button type="submit" class="btn btn-primary btn-sm" title="{{ __('pay_online') }}"><i class="fas fa-credit-card"></i> {{ __('btn_pay_online') }}</button>
                                                        </form>
                                                        @elseif(config('payment.status') == 'flutterwave')
                                                        <form method="post" action="{{ route('payment.flutterwave.process') }}" style="display: inline-block;">
                                                            @csrf
                                                            <input type="hidden" name="fee_id" value="{{ $row->id }}">

                                                            <button type="submit" class="btn btn-primary btn-sm" title="{{ __('pay_online') }}"><i class="fas fa-credit-card"></i> {{ __('btn_pay_online') }}</button>
                                                        </form>
                                                        @endif
                                                    @endif

                                                @if(config('momo.providers.mtn.enabled'))
                                                <button type="button" class="btn btn-warning btn-sm momo-open" data-bs-toggle="modal" data-bs-target="#studentMomoModal"
                                                        data-provider="mtn" data-fee-id="{{ $row->id }}" data-amount="{{ $remaining_balance }}">
                                                    <i class="fas fa-mobile-alt"></i> {{ __('MTN MoMo') }}
                                                </button>
                                                @endif
                                                @if(config('momo.providers.orange.enabled'))
                                                <button type="button" class="btn btn-danger btn-sm momo-open" data-bs-toggle="modal" data-bs-target="#studentMomoModal"
                                                        data-provider="orange" data-fee-id="{{ $row->id }}" data-amount="{{ $remaining_balance }}">
                                                    <i class="fas fa-mobile-alt"></i> {{ __('Orange Money') }}
                                                </button>
                                                @endif
                                                
                                                @if(!$hasPendingMultiPayment)
                                                <a href="{{ route('student.manual-payment.create', $row->id) }}" class="btn btn-success btn-sm" title="{{ __('upload_receipt') }}">
                                                    <i class="fas fa-upload"></i> {{ __('btn_upload_receipt') }}
                                                </a>
                                                @else
                                                <button class="btn btn-secondary btn-sm" disabled title="This fee has a pending multi-payment">
                                                    <i class="fas fa-ban"></i> Payment Pending
                                                </button>
                                                @endif
                                            </div>
                                            @endif
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
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


<!-- Multi-Payment Modal -->
<div class="modal fade" id="multiPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-credit-card"></i> Multi-Fee Payment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="multiPaymentForm" action="{{ route('student.multi-payment.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <!-- Selected Fees Summary -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Payment Summary</h6>
                        <div id="selectedFeesSummary"></div>
                        <hr>
                        <strong>Total Balance to Pay:</strong> 
                        <span id="modalTotalBalance" class="text-primary h5">0.00</span> {!! $setting->currency_symbol ?? '' !!}
                    </div>

                    <input type="hidden" name="selected_fees" id="selectedFeesInput">
                    <input type="hidden" name="distribution_data" id="distributionDataInput">

                    <!-- Payment Amount -->
                    <div class="form-group">
                        <label for="payment_amount">Amount to Pay <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                               step="0.01" min="0.01" required onkeyup="calculateDistribution()">
                        <small class="form-text text-muted">
                            Enter the amount you want to pay. It can be less than or equal to the total balance.
                        </small>
                    </div>

                    <!-- Distribution Preview -->
                    <div id="distributionPreview" style="display: none;">
                        <h6 class="mt-3"><i class="fas fa-chart-pie"></i> Payment Distribution Preview</h6>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <small>You can manually adjust the amounts below. The system automatically distributes based on due dates (earliest first).</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Fee / Installment</th>
                                        <th>Current Balance</th>
                                        <th>Amount to Apply</th>
                                        <th>Remaining Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="distributionTableBody">
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <th colspan="2" class="text-right">Total to Distribute:</th>
                                        <th id="totalDistributed">0.00</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="alert alert-warning" id="distributionWarning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i> <span id="distributionWarningText"></span>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-group mt-3">
                        <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Transaction ID -->
                    <div class="form-group">
                        <label for="transaction_id">Transaction/Reference ID</label>
                        <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                               placeholder="Enter transaction reference number">
                    </div>

                    <!-- Payment Receipt -->
                    <div class="form-group">
                        <label for="receipt">Upload Payment Receipt <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="receipt" name="receipt" 
                               accept="image/*,.pdf" required>
                        <small class="form-text text-muted">
                            Upload proof of payment (Image or PDF, Max: 2MB)
                        </small>
                    </div>

                    <!-- Payment Date -->
                    <div class="form-group">
                        <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>

                    <!-- Note -->
                    <div class="form-group">
                        <label for="note">Note (Optional)</label>
                        <textarea class="form-control" id="note" name="note" rows="2" 
                                  placeholder="Add any additional information..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitPaymentBtn">
                        <i class="fas fa-paper-plane"></i> Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if(config('momo.providers.mtn.enabled') || config('momo.providers.orange.enabled'))
<div class="modal fade" id="studentMomoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-mobile-alt me-2"></i>{{ __('Mobile Money Payment') }}</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><strong>{{ __('Provider') }}:</strong> <span id="momoProviderLabel"></span></div>
                <div class="mb-2"><strong>{{ __('Amount') }}:</strong> <span id="momoAmountLabel"></span></div>
                <div class="mb-2 momo-msisdn-row">
                    <label class="form-label">{{ __('Phone Number') }} <span class="text-danger">*</span></label>
                    <input type="tel" id="studentMomoMsisdn" class="form-control" placeholder="670000000">
                </div>
                <div id="studentMomoStatus" class="alert alert-info d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="studentMomoPayBtn">
                    <i class="fas fa-bolt me-1"></i>{{ __('Pay') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('styles')
<style>
    .fee-cart-sidebar {
        position: fixed;
        right: -400px;
        top: 0;
        width: 400px;
        height: 100vh;
        background: white;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1050;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
    }
    
    .fee-cart-sidebar.active {
        right: 0;
    }
    
    .cart-header {
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .cart-header h5 {
        margin: 0;
        color: white;
    }
    
    .btn-close-cart {
        background: transparent;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-close-cart:hover {
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
    }
    
    .cart-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }
    
    .cart-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        position: relative;
        border-left: 4px solid #667eea;
    }
    
    .cart-item-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 10px;
    }
    
    .cart-item-title {
        font-weight: 600;
        color: #333;
        font-size: 0.95rem;
    }
    
    .cart-item-remove {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 0.8rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }
    
    .cart-item-remove:hover {
        background: #c82333;
    }
    
    .cart-item-details {
        font-size: 0.85rem;
        color: #666;
    }
    
    .cart-item-details div {
        margin-bottom: 5px;
    }
    
    .cart-item-balance {
        font-weight: 600;
        color: #667eea;
        font-size: 1.1rem;
        margin-top: 8px;
    }
    
    /* Payment Plan Styles */
    .cart-item.has-payment-plan {
        border-left-color: #17a2b8;
    }
    
    .cart-item-payment-plan {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #dee2e6;
    }
    
    .payment-plan-badge {
        display: inline-block;
        padding: 4px 10px;
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 500;
        margin-bottom: 8px;
    }
    
    .installments-list {
        background: white;
        border-radius: 4px;
        padding: 8px;
        margin-top: 6px;
    }
    
    .installment-item {
        padding: 6px 8px;
        border-left: 3px solid #17a2b8;
        margin-bottom: 6px;
        background: #f8f9fa;
    }
    
    .installment-item:last-child {
        margin-bottom: 0;
    }
    
    .installment-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }
    
    .installment-name {
        font-weight: 600;
        font-size: 0.85rem;
        color: #333;
    }
    
    .installment-details {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 0.75rem;
    }
    
    .installment-details small {
        color: #666;
        background: white;
        padding: 2px 6px;
        border-radius: 3px;
    }
    
    .badge-sm {
        font-size: 0.7rem;
        padding: 2px 6px;
    }
    
    .cart-footer {
        padding: 20px;
        border-top: 2px solid #e9ecef;
        background: #f8f9fa;
    }
    
    .cart-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        font-size: 1.1rem;
    }
    
    .cart-total span {
        color: #667eea;
        font-weight: 700;
        font-size: 1.3rem;
    }
    
    #paySelectedBtn {
        width: 100%;
        padding: 12px;
        font-weight: 600;
    }
    
    #paySelectedBtn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .fee-checkbox {
        cursor: pointer;
        width: 18px;
        height: 18px;
    }
    
    /* Distribution Table Styles */
    .installment-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .amount-input {
        font-weight: 600;
        color: #667eea;
        border: 2px solid #e9ecef;
        transition: border-color 0.2s;
    }
    
    .amount-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    #distributionTableBody tr[data-item-index] {
        transition: background-color 0.2s;
    }
    
    #distributionTableBody tr[data-item-index]:hover {
        background-color: #f8f9fa;
    }
    
    .btn-filter {
        margin-top: 28px;
    }
    
    @media (max-width: 768px) {
        .fee-cart-sidebar {
            width: 100%;
            right: -100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    let selectedFees = new Map();
    const currencySymbol = '{!! $setting->currency_symbol ?? "" !!}';
    const decimalPlaces = {{ $setting->decimal_place ?? 2 }};

    // Toggle cart sidebar
    function toggleCart() {
        document.getElementById('feeCartSidebar').classList.toggle('active');
    }

    // Toggle select all checkboxes
    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.fee-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateCart();
    }

    // Update cart when checkbox changes
    function updateCart() {
        selectedFees.clear();
        const checkboxes = document.querySelectorAll('.fee-checkbox:checked');
        
        checkboxes.forEach(checkbox => {
            const feeId = checkbox.dataset.feeId;
            const hasPaymentPlan = checkbox.dataset.hasPaymentPlan === 'true';
            let installments = [];
            
            if (hasPaymentPlan && checkbox.dataset.installments) {
                try {
                    installments = JSON.parse(checkbox.dataset.installments);
                } catch (e) {
                    console.error('Error parsing installments:', e);
                }
            }
            
            selectedFees.set(feeId, {
                id: feeId,
                type: checkbox.dataset.feeType,
                session: checkbox.dataset.session,
                semester: checkbox.dataset.semester,
                balance: parseFloat(checkbox.dataset.balance),
                dueDate: checkbox.dataset.dueDate,
                hasPaymentPlan: hasPaymentPlan,
                paymentPlanName: checkbox.dataset.paymentPlanName || '',
                installments: installments
            });
        });

        renderCart();
        updateSelectAllCheckbox();
    }

    // Render cart UI
    function renderCart() {
        const cartBody = document.getElementById('cartBody');
        const cartTotal = document.getElementById('cartTotal');
        const selectedCount = document.getElementById('selectedCount');
        const payBtn = document.getElementById('paySelectedBtn');

        if (selectedFees.size === 0) {
            cartBody.innerHTML = '<p class="text-center text-muted">No fees selected</p>';
            cartTotal.textContent = '0.00';
            selectedCount.textContent = '0';
            payBtn.disabled = true;
            return;
        }

        let total = 0;
        let html = '';

        selectedFees.forEach((fee, id) => {
            total += fee.balance;
            
            // Build installment breakdown if payment plan exists
            let installmentHtml = '';
            if (fee.hasPaymentPlan && fee.installments && fee.installments.length > 0) {
                installmentHtml = `
                    <div class="cart-item-payment-plan">
                        <div class="payment-plan-badge">
                            <i class="fas fa-calendar-check"></i> ${fee.paymentPlanName}
                        </div>
                        <div class="installments-list">
                `;
                
                fee.installments.forEach(inst => {
                    const statusClass = inst.status === 'paid' ? 'success' : 
                                      inst.status === 'partial' ? 'warning' : 'danger';
                    installmentHtml += `
                        <div class="installment-item">
                            <div class="installment-info">
                                <span class="installment-name">${inst.name}</span>
                                <span class="badge badge-${statusClass} badge-sm">${inst.status}</span>
                            </div>
                            <div class="installment-details">
                                <small>Amount: ${formatCurrency(inst.amount)} ${currencySymbol}</small>
                                <small>Paid: ${formatCurrency(inst.paid)} ${currencySymbol}</small>
                                <small>Balance: ${formatCurrency(inst.balance)} ${currencySymbol}</small>
                                <small>Due: ${formatDate(inst.due_date)}</small>
                            </div>
                        </div>
                    `;
                });
                
                installmentHtml += `
                        </div>
                    </div>
                `;
            }
            
            html += `
                <div class="cart-item ${fee.hasPaymentPlan ? 'has-payment-plan' : ''}">
                    <div class="cart-item-header">
                        <div class="cart-item-title">${fee.type}</div>
                        <button type="button" class="cart-item-remove" onclick="removeFeeFromCart('${id}')" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="cart-item-details">
                        <div><i class="fas fa-calendar"></i> ${fee.session} - ${fee.semester}</div>
                        ${!fee.hasPaymentPlan ? `<div><i class="fas fa-clock"></i> Due: ${formatDate(fee.dueDate)}</div>` : ''}
                    </div>
                    ${installmentHtml}
                    <div class="cart-item-balance">
                        ${formatCurrency(fee.balance)} ${currencySymbol}
                    </div>
                </div>
            `;
        });

        cartBody.innerHTML = html;
        cartTotal.textContent = formatCurrency(total);
        selectedCount.textContent = selectedFees.size;
        payBtn.disabled = false;
    }

    // Remove fee from cart
    function removeFeeFromCart(feeId) {
        const checkbox = document.querySelector(`.fee-checkbox[data-fee-id="${feeId}"]`);
        if (checkbox) {
            checkbox.checked = false;
        }
        updateCart();
    }

    // Clear all selections
    function clearSelection() {
        const checkboxes = document.querySelectorAll('.fee-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        updateCart();
    }

    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        const allCheckboxes = document.querySelectorAll('.fee-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.fee-checkbox:checked');
        const selectAllCheckbox = document.getElementById('selectAll');
        
        if (allCheckboxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCheckboxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCheckboxes.length === allCheckboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Proceed to payment
    function proceedToPayment() {
        if (selectedFees.size === 0) {
            alert('Please select at least one fee to pay');
            return;
        }

        // Populate modal
        const summaryHtml = Array.from(selectedFees.values()).map(fee => `
            <div class="d-flex justify-content-between mb-2">
                <span>${fee.type} (${fee.session} - ${fee.semester})</span>
                <strong>${formatCurrency(fee.balance)} ${currencySymbol}</strong>
            </div>
        `).join('');

        document.getElementById('selectedFeesSummary').innerHTML = summaryHtml;

        const totalBalance = Array.from(selectedFees.values()).reduce((sum, fee) => sum + fee.balance, 0);
        document.getElementById('modalTotalBalance').textContent = formatCurrency(totalBalance);

        // Set hidden input with selected fee IDs
        document.getElementById('selectedFeesInput').value = JSON.stringify(Array.from(selectedFees.keys()));

        // Set max payment amount
        document.getElementById('payment_amount').setAttribute('max', totalBalance.toFixed(decimalPlaces));

        // Show modal
        $('#multiPaymentModal').modal('show');
    }

    // Calculate distribution
    function calculateDistribution() {
        const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
        const totalBalance = Array.from(selectedFees.values()).reduce((sum, fee) => sum + fee.balance, 0);

        if (paymentAmount <= 0) {
            document.getElementById('distributionPreview').style.display = 'none';
            return;
        }

        if (paymentAmount > totalBalance) {
            document.getElementById('distributionWarning').style.display = 'block';
            document.getElementById('distributionWarningText').textContent = 
                'Payment amount exceeds total balance. It will be adjusted to the maximum balance.';
            document.getElementById('payment_amount').value = totalBalance.toFixed(decimalPlaces);
            return;
        } else {
            document.getElementById('distributionWarning').style.display = 'none';
        }

        // Build distribution items (fees and installments)
        const distributionItems = [];
        
        selectedFees.forEach((fee, feeId) => {
            if (fee.hasPaymentPlan && fee.installments && fee.installments.length > 0) {
                // Add each installment as a separate item
                fee.installments.forEach(inst => {
                    const instBalance = parseFloat(inst.balance);
                    if (instBalance > 0) {
                        distributionItems.push({
                            type: 'installment',
                            feeId: feeId,
                            installmentId: inst.id,
                            feeType: fee.type,
                            session: fee.session,
                            semester: fee.semester,
                            installmentName: inst.name,
                            dueDate: inst.due_date,
                            currentBalance: instBalance,
                            status: inst.status,
                            hasPaymentPlan: true,
                            paymentPlanName: fee.paymentPlanName
                        });
                    }
                });
            } else {
                // Regular fee without payment plan
                distributionItems.push({
                    type: 'fee',
                    feeId: feeId,
                    feeType: fee.type,
                    session: fee.session,
                    semester: fee.semester,
                    dueDate: fee.dueDate,
                    currentBalance: fee.balance,
                    hasPaymentPlan: false
                });
            }
        });

        // Sort by due date (closest first)
        distributionItems.sort((a, b) => {
            return new Date(a.dueDate) - new Date(b.dueDate);
        });

        // Auto-distribute payment based on due dates
        let remainingAmount = paymentAmount;
        distributionItems.forEach(item => {
            if (remainingAmount >= item.currentBalance) {
                // Can fully pay this item
                item.amountApplied = item.currentBalance;
                remainingAmount -= item.currentBalance;
            } else if (remainingAmount > 0) {
                // Partial payment
                item.amountApplied = remainingAmount;
                remainingAmount = 0;
            } else {
                item.amountApplied = 0;
            }
            
            item.remainingBalance = item.currentBalance - item.amountApplied;
            item.paymentStatus = item.remainingBalance === 0 ? 'Fully Paid' : 
                                 item.amountApplied > 0 ? 'Partially Paid' : 'Unpaid';
        });

        // Render distribution table with editable amounts
        renderDistributionTable(distributionItems);
        
        document.getElementById('distributionPreview').style.display = 'block';
    }

    // Render distribution table
    function renderDistributionTable(items) {
        const tableBody = document.getElementById('distributionTableBody');
        
        let html = '';
        items.forEach((item, index) => {
            const isInstallment = item.type === 'installment';
            const itemId = isInstallment ? `${item.feeId}_${item.installmentId}` : item.feeId;
            
            html += `
                <tr data-item-index="${index}">
                    <td>
                        ${isInstallment ? `
                            <div class="installment-row">
                                <i class="fas fa-level-up-alt fa-rotate-90 text-muted"></i>
                                <strong>${item.installmentName}</strong>
                                <span class="badge badge-${item.status === 'paid' ? 'success' : item.status === 'partial' ? 'warning' : 'danger'} badge-sm ml-1">${item.status}</span>
                            </div>
                            <small class="text-muted ml-3">${item.feeType} - ${item.session} ${item.semester}</small>
                            <br><small class="text-muted ml-3"><span class="badge badge-info badge-sm"><i class="fas fa-calendar-check"></i> ${item.paymentPlanName}</span></small>
                        ` : `
                            <strong>${item.feeType}</strong><br>
                            <small class="text-muted">${item.session} - ${item.semester}</small>
                        `}
                    </td>
                    <td>
                        <strong>${formatCurrency(item.currentBalance)} ${currencySymbol}</strong><br>
                        <small class="text-muted">Due: ${formatDate(item.dueDate)}</small>
                    </td>
                    <td>
                        <input type="number" 
                               class="form-control form-control-sm amount-input" 
                               data-item-index="${index}"
                               data-max-balance="${item.currentBalance}"
                               value="${formatCurrency(item.amountApplied)}" 
                               step="0.01" 
                               min="0" 
                               max="${item.currentBalance}"
                               onchange="handleAmountChange(${index})"
                               style="width: 120px;">
                    </td>
                    <td id="remaining-${index}">
                        ${formatCurrency(item.remainingBalance)} ${currencySymbol}
                    </td>
                    <td id="status-${index}">
                        <span class="badge ${item.paymentStatus === 'Fully Paid' ? 'badge-success' : item.paymentStatus === 'Partially Paid' ? 'badge-warning' : 'badge-secondary'}">
                            ${item.paymentStatus}
                        </span>
                    </td>
                </tr>
            `;
        });
        
        tableBody.innerHTML = html;
        
        // Store distribution items globally for later use
        window.distributionItems = items;
        
        updateTotalDistributed();
    }

    // Handle manual amount change
    function handleAmountChange(index) {
        const input = document.querySelector(`input[data-item-index="${index}"]`);
        const item = window.distributionItems[index];
        
        let newAmount = parseFloat(input.value) || 0;
        const maxBalance = parseFloat(input.dataset.maxBalance);
        
        // Validate amount
        if (newAmount < 0) newAmount = 0;
        if (newAmount > maxBalance) newAmount = maxBalance;
        
        // Update input
        input.value = formatCurrency(newAmount);
        
        // Update item
        item.amountApplied = newAmount;
        item.remainingBalance = item.currentBalance - newAmount;
        item.paymentStatus = item.remainingBalance === 0 ? 'Fully Paid' : 
                            newAmount > 0 ? 'Partially Paid' : 'Unpaid';
        
        // Update display
        document.getElementById(`remaining-${index}`).innerHTML = 
            `${formatCurrency(item.remainingBalance)} ${currencySymbol}`;
        
        const statusBadge = document.getElementById(`status-${index}`);
        statusBadge.innerHTML = `
            <span class="badge ${item.paymentStatus === 'Fully Paid' ? 'badge-success' : item.paymentStatus === 'Partially Paid' ? 'badge-warning' : 'badge-secondary'}">
                ${item.paymentStatus}
            </span>
        `;
        
        updateTotalDistributed();
    }

    // Update total distributed amount
    function updateTotalDistributed() {
        const total = window.distributionItems.reduce((sum, item) => sum + item.amountApplied, 0);
        const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
        
        document.getElementById('totalDistributed').innerHTML = 
            `${formatCurrency(total)} ${currencySymbol}`;
        
        // Show warning if total doesn't match payment amount
        const difference = Math.abs(total - paymentAmount);
        if (difference > 0.01) {
            document.getElementById('distributionWarning').style.display = 'block';
            document.getElementById('distributionWarningText').textContent = 
                `Total distributed (${formatCurrency(total)}) does not match payment amount (${formatCurrency(paymentAmount)}). Difference: ${formatCurrency(difference)} ${currencySymbol}`;
        } else {
            document.getElementById('distributionWarning').style.display = 'none';
        }
    }

    // Format currency
    function formatCurrency(amount) {
        return parseFloat(amount).toFixed(decimalPlaces);
    }

    // Format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    // Form submission
    document.getElementById('multiPaymentForm').addEventListener('submit', function(e) {
        // Validate distribution
        if (window.distributionItems && window.distributionItems.length > 0) {
            const total = window.distributionItems.reduce((sum, item) => sum + item.amountApplied, 0);
            const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
            const difference = Math.abs(total - paymentAmount);
            
            if (difference > 0.01) {
                e.preventDefault();
                alert(`Total distributed amount (${formatCurrency(total)}) must match payment amount (${formatCurrency(paymentAmount)}). Please adjust the amounts.`);
                return false;
            }
            
            // Prepare distribution data
            const distributionData = window.distributionItems.map(item => ({
                feeId: item.feeId,
                installmentId: item.installmentId || null,
                type: item.type,
                amountApplied: item.amountApplied,
                currentBalance: item.currentBalance,
                remainingBalance: item.remainingBalance
            }));
            
            // Set hidden input with distribution data
            document.getElementById('distributionDataInput').value = JSON.stringify(distributionData);
        }
        
        const submitBtn = document.getElementById('submitPaymentBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCart();
    });
</script>

@if(config('momo.providers.mtn.enabled') || config('momo.providers.orange.enabled'))
<script>
(function () {
    const modal = document.getElementById('studentMomoModal');
    if (!modal) return;
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    const MOMO_BASE = '{{ url('payment/momo') }}';
    let currentProvider = null, currentFeeId = null, currentAmount = null;

    document.querySelectorAll('.momo-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentProvider = btn.dataset.provider;
            currentFeeId = btn.dataset.feeId;
            currentAmount = btn.dataset.amount;
            document.getElementById('momoProviderLabel').textContent = currentProvider === 'mtn' ? 'MTN MoMo' : 'Orange Money';
            document.getElementById('momoAmountLabel').textContent = currentAmount;
            modal.querySelector('.momo-msisdn-row').style.display = (currentProvider === 'mtn') ? 'block' : 'none';
            document.getElementById('studentMomoStatus').classList.add('d-none');
            document.getElementById('studentMomoPayBtn').disabled = false;
        });
    });

    document.getElementById('studentMomoPayBtn').addEventListener('click', function () {
        const btn = this;
        const statusBox = document.getElementById('studentMomoStatus');
        const payload = { fee_id: currentFeeId };
        if (currentProvider === 'mtn') {
            const m = document.getElementById('studentMomoMsisdn').value.trim();
            if (!m) { show(statusBox, 'warning', '{{ __("Please enter the phone number.") }}'); return; }
            payload.msisdn = m;
        }
        btn.disabled = true;
        show(statusBox, 'info', '{{ __("Contacting payment provider...") }}');

        fetch(MOMO_BASE + '/' + currentProvider + '/initiate', {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify(payload),
        }).then(r => r.json()).then(function (data) {
            if (!data.ok) { btn.disabled = false; show(statusBox, 'danger', data.error || 'Error'); return; }
            if (currentProvider === 'orange' && data.payment_url) {
                window.location.href = data.payment_url; return;
            }
            show(statusBox, 'info', '{{ __("Approve the request on your phone. Waiting...") }}');
            poll(currentProvider, data.reference, statusBox, btn, data.poll_interval || 3, data.poll_timeout || 90);
        }).catch(function () {
            btn.disabled = false;
            show(statusBox, 'danger', '{{ __("Network error. Please try again.") }}');
        });
    });

    function poll(provider, reference, statusBox, btn, intervalSec, timeoutSec) {
        const started = Date.now();
        const tick = function () {
            fetch(MOMO_BASE + '/' + provider + '/status/' + encodeURIComponent(reference))
                .then(r => r.json()).then(function (data) {
                    if (!data.ok) return retryOrGiveUp();
                    if (data.status === 'successful') {
                        show(statusBox, 'success', '{{ __("Payment successful! Reloading...") }}');
                        setTimeout(() => window.location.reload(), 1500); return;
                    }
                    if (data.status === 'failed' || data.status === 'timeout') {
                        btn.disabled = false;
                        show(statusBox, 'danger', data.reason || '{{ __("Payment did not complete.") }}'); return;
                    }
                    retryOrGiveUp();
                }).catch(retryOrGiveUp);
        };
        const retryOrGiveUp = function () {
            if ((Date.now() - started) / 1000 >= timeoutSec) {
                btn.disabled = false;
                show(statusBox, 'warning', '{{ __("Still waiting. Refresh in a moment to check.") }}'); return;
            }
            setTimeout(tick, intervalSec * 1000);
        };
        tick();
    }

    function show(el, level, msg) { el.className = 'alert alert-' + level; el.textContent = msg; }
})();
</script>
@endif
@endpush
