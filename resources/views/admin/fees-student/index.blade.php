@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{-- The fees list.

     Same treatment as the report beside it, so the two read as one module
     rather than a redesigned report next to an old list. Its twelve columns,
     its pay / cancel / unpay / print actions and its overdue highlighting are
     all kept; what changes is that the money is the headline and the figures
     can be read.
--}}

@include('admin.fees-student.partials.theme')

@php
    $decimals = $setting->decimal_place ?? 2;
    $currency = $setting->currency_symbol ?? '';
    $dateFormat = $setting->date_format ?? 'Y-m-d';

    // WITH thousands separators: these cells used an empty separator, so every
    // figure rendered as an unbroken run of digits.
    $money = fn ($value) => number_format((float) $value, $decimals);
@endphp

<div class="main-body fees">
    <div class="page-wrapper">

        @include('admin.fees-student.partials.summary_band')

        <div class="fees-panel">
            <div class="fees-filters">
                @include('admin.fees-student.partials.index_filters')
            </div>
        </div>

        @if(isset($rows))
        <div class="fees-panel">
            @if($rows->isEmpty())
                <div class="fees-empty">
                    <i class="fas fa-folder-open"></i>
                    <p>{{ __('no_fees_found') }}</p>
                    <small>{{ __('fees_no_match_hint') }}</small>
                </div>
            @else
            <div class="fees-scroll">
                <table id="export-table" class="display table nowrap fees-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('field_matricule') }}</th>
                            <th>{{ __('field_fees_type') }}</th>
                            <th class="n">{{ __('field_fee') }}</th>
                            <th class="n">{{ __('field_discount') }}</th>
                            <th class="n">{{ __('field_fine_amount') }}</th>
                            <th class="n">{{ __('field_net_amount') }}</th>
                            <th class="n">{{ __('field_paid') }}</th>
                            <th class="n">{{ __('field_remaining_balance') }}</th>
                            <th>{{ __('field_due_date') }}</th>
                            <th>{{ __('field_status') }}</th>
                            <th class="n">{{ __('field_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse( $rows as $key => $row )
                        @php
                            $display_paid = min((float) $row->paid_amount, (float) $row->total_amount);
                            $display_balance = max(0, (float) $row->remaining_balance);
                            $due = $row->due_date ? \Carbon\Carbon::parse($row->due_date) : null;
                            $overdue = (int) $row->status === 0 && $due && $due->isPast();
                        @endphp
                        <tr>
                            <td class="fees-meta">{{ $key + 1 }}</td>
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
                                @if($row->status == 2)
                                <div class="mt-1"><span class="fees-tag fees-tag-warn">{{ __('partial_payment') }}</span></div>
                                @endif
                            </td>
                            {{-- data-order so DataTables sorts the day and not the
                                 rendered string: the setting is d/m/Y, under which
                                 01/12 sorts before 26/10. On a list whose whole job
                                 is chasing what is overdue, that column has to sort. --}}
                            <td class="fees-when" data-order="{{ $due?->format('Y-m-d') }}">
                                {{ $due?->format($dateFormat) }}
                                @if($overdue)
                                {{-- The row used to carry .table-danger and nothing
                                     else; the word says it for a reader who cannot
                                     see the tint. --}}
                                <div class="mt-1"><span class="fees-tag fees-tag-bad">{{ __('overdue_fees') }}</span></div>
                                @endif
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
                            <td class="fees-acts">
                                @if($row->payment_plan_id && $row->paymentPlan && $row->paymentPlan->status == 'active')
                                <a href="{{ route('admin.payment-plan.show', $row->payment_plan_id) }}" class="btn btn-icon btn-light btn-sm" title="{{ __('view_payment_plan') }}">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @elseif($row->hasPendingMultiPayment())
                                <button type="button" class="btn btn-icon btn-light btn-sm" disabled title="This fee has a pending multi-payment">
                                    <i class="fas fa-clock"></i>
                                </button>
                                @elseif($row->status == 0 || $row->status == 2)
                                <button type="button" class="btn btn-icon btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#payModal-{{ $row->id }}" title="{{ $row->status == 2 ? __('pay_balance') : __('make_payment') }}">
                                    <i class="fas fa-plus"></i>
                                </button>
                                @include($view.'.pay')
                                @can($access.'-action')
                                <button type="button" class="btn btn-icon btn-light btn-sm" title="{{ __('status_canceled') }}" data-bs-toggle="modal" data-bs-target="#cancelModal-{{ $row->id }}">
                                    <i class="fas fa-times"></i>
                                </button>
                                @include($view.'.cancel')
                                @endcan
                                @endif

                                @if($row->status == 1 || $row->status == 2 || ($row->payment_plan_id && $row->paymentPlan))
                                @can($access.'-print')
                                @if(isset($print))
                                <a href="{{ route($route.'.print', ['id' => $row->id]) }}" target="_blank" class="btn btn-icon btn-light btn-sm" title="{{ __('btn_print') }}">
                                    <i class="fas fa-print"></i>
                                </a>
                                @endif
                                @endcan
                                @endif

                                @if($row->status == 1)
                                @can($access.'-action')
                                <button type="button" class="btn btn-icon btn-light btn-sm" title="{{ __('status_unpaid') }}" data-bs-toggle="modal" data-bs-target="#unpayModal-{{ $row->id }}">
                                    <i class="fas fa-undo"></i>
                                </button>
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

{{-- The receipt pop-up that used to live here is gone.

     It called window.open() from a page-load script, which browsers block
     outside a user gesture, so it opened nothing. pay() now redirects to the
     receipt instead, which cannot be blocked, and the receipt's Back link
     returns to this screen with its filters intact. --}}
