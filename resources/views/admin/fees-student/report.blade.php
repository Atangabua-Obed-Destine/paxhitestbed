@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{-- The fees report.

     Rebuilt around the question it is run to answer — how much was billed, how
     much has come in, and who still owes — with the money as the headline
     rather than a row of count cards above it.

     Three things are preserved exactly, because changing any of them breaks
     behaviour rather than appearance:

       * the sixteen columns in their existing ORDER. The column chooser
         addresses them by index, so moving one hides the wrong column.
       * the bulk-select checkbox and the `.fees` hidden input, which is what
         Print Selected posts.
       * common/inc/fees_search_filter, shared with fees-master and two other
         modules. Its markup is untouched; the new styling reaches it only
         because it sits inside .fees here.
--}}

@include('admin.fees-student.partials.theme')

@php
    $decimals = $setting->decimal_place ?? 2;
    $currency = $setting->currency_symbol ?? '';
    $dateFormat = $setting->date_format ?? 'Y-m-d';

    /*
     * WITH thousands separators. The cells used number_format(..., '.', '') —
     * an empty separator — so every figure on a sixteen-column money report
     * read as an unbroken run of digits.
     */
    $money = fn ($value) => number_format((float) $value, $decimals);
@endphp

<div class="main-body fees">
    <div class="page-wrapper">

        @include('admin.fees-student.partials.summary_band')

        <div class="fees-panel">
            <div class="fees-filters">
                @include('admin.fees-student.partials.filters')
            </div>
        </div>

        @if(isset($rows))
        <div class="fees-bar-actions">
            @can($access.'-print')
            <form class="needs-validation d-inline" novalidate method="get" action="{{ route($route.'.multiprint') }}" target="_blank">
                <input type="hidden" name="fees" class="fees" value="">
                <button type="submit" class="fees-btn print-btn">
                    <i class="fas fa-print"></i> {{ __('btn_print') }} {{ __('field_selected') }}
                </button>
            </form>
            @endcan

            <div class="ms-auto">
                @include('admin.fees-student.partials.column_chooser')
            </div>
        </div>

        <div class="fees-panel">
            @if($rows->isEmpty())
                <div class="fees-empty">
                    <i class="fas fa-folder-open"></i>
                    <p>{{ __('fees_no_match') }}</p>
                    <small>{{ __('fees_no_match_hint') }}</small>
                </div>
            @else
            {{-- Its own scroll container: sixteen columns do not fit a laptop,
                 and without this the whole page goes sideways with them. --}}
            <div class="fees-scroll">
                <table id="export-table" class="display table nowrap fees-table" style="width:100%">
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
                            <th class="n">{{ __('field_fee') }}</th>
                            <th class="n">{{ __('field_discount') }}</th>
                            <th class="n">{{ __('field_fine_amount') }}</th>
                            <th class="n">{{ __('field_net_amount') }}</th>
                            <th class="n">{{ __('field_paid') }}</th>
                            <th class="n">{{ __('field_remaining_balance') }}</th>
                            <th>{{ __('field_pay_date') }}</th>
                            <th>{{ __('field_status') }}</th>
                            <th>{{ __('field_payment_method') }}</th>
                            <th>{{ __('field_note') }}</th>
                            <th class="n">{{ __('field_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach( $rows as $key => $row )
                        @php
                            // Net of overpayment credit moved to another fee,
                            // which counts on that fee instead.
                            $display_paid = min((float) $row->net_paid_amount, (float) $row->total_amount);
                            $display_balance = max(0, $row->net_remaining_balance);
                            $paidOn = in_array((int) $row->status, [1, 2], true) && $row->pay_date
                                ? \Carbon\Carbon::parse($row->pay_date)
                                : null;
                        @endphp
                        <tr>
                            <td>
                                <div class="checkbox checkbox-primary d-inline">
                                    <input type="checkbox" data_id="{{ $row->id }}" id="checkbox-{{ $row->id }}" value="{{ $row->id }}">
                                    <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                </div>
                            </td>
                            <td class="fees-meta">{{ $key + 1 }}</td>
                            <td class="fees-meta fees-num">{{ $print->prefix ?? '' }}{{ str_pad($row->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td class="fees-who">
                                @isset($row->studentEnroll)
                                <a href="{{ route('admin.student.show', $row->studentEnroll->student->id) }}">#{{ $row->studentEnroll->matricule ?? $row->studentEnroll->student->student_id ?? '' }}</a>
                                <div class="fees-meta">{{ $row->studentEnroll->student->first_name ?? '' }} {{ $row->studentEnroll->student->last_name ?? '' }}</div>
                                @endisset
                            </td>
                            <td><span class="fees-tag fees-tag-flow">{{ $row->category->title ?? '—' }}</span></td>
                            <td class="n fees-num">{{ $money($row->fee_amount) }}</td>
                            <td class="n fees-num">{{ $money($row->discount_amount) }}</td>
                            <td class="n fees-num">{{ $money($row->fine_amount) }}</td>
                            <td class="n fees-num">{{ $money($row->total_amount) }}</td>
                            <td class="n fees-num">{{ $money($display_paid) }}</td>
                            <td class="n fees-num {{ $display_balance > 0 ? 'is-due' : '' }}">
                                {{ $money($display_balance) }}
                                @if($row->credit_moved_out > 0)
                                <div class="fees-meta" title="{{ __('Overpayment on this fee, moved as credit to another fee') }}">
                                    <i class="fas fa-exchange-alt"></i> {{ $money($row->credit_moved_out) }} {{ __('moved to another fee') }}
                                </div>
                                @endif
                            </td>
                            {{-- data-order so DataTables sorts the day and not the
                                 rendered string: the setting is d/m/Y, under which
                                 01/12 sorts before 26/10. --}}
                            <td class="fees-when" data-order="{{ $paidOn?->format('Y-m-d') }}">
                                {{ $paidOn?->format($dateFormat) }}
                            </td>
                            <td>
                                {!! $row->status_badge !!}
                                @if($row->payment_plan_id && $row->paymentPlan)
                                <div class="mt-1"><span class="fees-tag">
                                    <i class="fas fa-calendar-alt"></i> {{ __('payment_plan') }}
                                    @if($row->paymentPlan->status == 'active') ({{ __('status_active') }})
                                    @elseif($row->paymentPlan->status == 'completed') ({{ __('status_completed') }})
                                    @elseif($row->paymentPlan->status == 'cancelled') ({{ __('status_cancelled') }})
                                    @endif
                                </span></div>
                                @endif
                                @if($row->hasPendingMultiPayment())
                                <div class="mt-1"><span class="fees-tag fees-tag-warn">
                                    <i class="fas fa-clock"></i> Multi-Pay Pending ({{ $money($row->getPendingMultiPaymentAmount()) }})
                                </span></div>
                                @endif
                            </td>
                            {{-- One vocabulary, from App\Support\FeesPaymentMethod. The
                                 chain this replaces carried the stock template's gateway
                                 list, so 26 cash-office receipts were being reported as
                                 arriving through RazorPay. --}}
                            <td class="fees-meta fees-method">{{ \App\Support\FeesPaymentMethod::label($row->payment_method) }}</td>
                            <td class="fees-meta">{!! $row->note !!}</td>
                            <td class="fees-acts">
                                @if($row->payment_plan_id && $row->paymentPlan && $row->paymentPlan->status == 'active')
                                <a href="{{ route('admin.payment-plan.show', $row->payment_plan_id) }}" class="btn btn-icon btn-light btn-sm" title="{{ __('view_payment_plan') }}">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @elseif($row->hasPendingMultiPayment())
                                <button type="button" class="btn btn-icon btn-light btn-sm" disabled title="This fee has a pending multi-payment">
                                    <i class="fas fa-clock"></i>
                                </button>
                                @elseif($row->status == 0 || $row->status == 1 || $row->status == 2)
                                <button type="button" class="btn btn-icon btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#payModal-{{ $row->id }}" title="{{ $row->status == 1 ? __('add_overpayment') : ($row->status == 2 ? __('pay_balance') : __('make_payment')) }}">
                                    <i class="fas fa-plus"></i>
                                </button>
                                @include($view.'.pay')
                                @endif

                                @if($row->status == 1 || $row->status == 2 || ($row->payment_plan_id && $row->paymentPlan))
                                @can($access.'-print')
                                @if(isset($print))
                                <a href="#" class="btn btn-icon btn-light btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $row->id]) }}', '{{ $title }}', 1000, 600);" title="{{ __('btn_print') }}">
                                    <i class="fas fa-print"></i>
                                </a>
                                @endif
                                @endcan
                                @endif

                                @if($row->isNetOverpaid())
                                <span class="fees-tag fees-tag-flow" title="{{ __('overpayment_credit_generated') }}">
                                    +{{ $money($row->net_overpayment_amount) }}
                                </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @else
        <div class="fees-panel">
            <div class="fees-empty">
                <i class="fas fa-search"></i>
                <p>{{ __('fees_nothing_searched') }}</p>
                <small>{{ __('fees_nothing_searched_hint') }}</small>
            </div>
        </div>
        @endif

    </div>
</div>

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
