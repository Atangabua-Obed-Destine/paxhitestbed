@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{--
    Preparing a payment plan agreement.

    A student usually arranges both instalments at once, so the bursar ticks
    every fee the agreement covers and gives each its own schedule — each fee
    has its own due date and balance. Saving prepares the agreement: it is
    printed for the bursar's and the student's signatures and taken to the Vice
    Chancellor, and it takes no money until it is approved.
--}}
@php $currency = $setting->currency_symbol ?? ''; @endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Prepare a payment plan agreement') }}</h5>
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary btn-sm float-end">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                    </div>
                    <div class="card-block">
                        <div class="alert alert-info small mb-3">
                            <i class="fas fa-info-circle"></i>
                            {{ __('Saving prepares the agreement and opens it for printing. The Finance Director and the student sign it, then the Vice Chancellor signs and approves it. Until it is approved, no payment can be taken on the fees it covers.') }}
                        </div>

                        <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" id="agreement-form">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="student_id" class="form-label">{{ __('field_student') }} <span>*</span></label>
                                        <select class="form-control select2" name="student_id" id="student_id" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($students as $student)
                                            <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                                #{{ $student->student_id }} - {{ $student->first_name }} {{ $student->last_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_student') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="grace_period_days" class="form-label">{{ __('field_grace_period_days') }}</label>
                                        <input type="number" class="form-control" name="grace_period_days" id="grace_period_days" value="{{ old('grace_period_days', 7) }}" min="0" max="30">
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-2">{{ __('Fees on this agreement') }}</h6>
                            <p class="text-muted small mb-2">{{ __('Tick each fee the student is arranging to pay, then set its instalments. A fee already on an agreement, fully paid, or with a payment waiting to be verified is not offered.') }}</p>

                            <div id="fees-empty" class="text-muted small">{{ __('select_student_first') }}</div>
                            <div id="fees-list"></div>

                            <div class="card bg-light d-none" id="agreement-summary">
                                <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <strong>{{ __('Agreement total') }}:</strong>
                                        <span id="agreement-total">0.00</span> {!! $currency !!}
                                        <span class="text-muted small ms-2" id="agreement-count"></span>
                                    </div>
                                    <div class="small" id="agreement-state"></div>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <label for="notes" class="form-label">{{ __('field_notes') }}</label>
                                <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="{{ __('Why the student needs the plan, what was agreed') }}">{{ old('notes') }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-success" id="submit-btn" disabled>
                                <i class="fas fa-file-signature"></i> {{ __('Prepare agreement') }}
                            </button>
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="fee-card-template">
    <div class="card mb-3 fee-card">
        <div class="card-header py-2 d-flex align-items-center gap-3 flex-wrap">
            <div class="form-check mb-0">
                <input class="form-check-input fee-include" type="checkbox" value="1">
                <label class="form-check-label fw-bold fee-title"></label>
            </div>
            <span class="text-muted small fee-meta"></span>
            <span class="ms-auto">{{ __('To plan') }}: <strong class="fee-balance"></strong> {!! $currency !!}</span>
        </div>
        <div class="card-body py-2 d-none fee-body">
            <div class="d-flex align-items-end gap-2 flex-wrap mb-2">
                <div>
                    <label class="form-label small mb-0">{{ __('field_number_of_installments') }}</label>
                    <input type="number" class="form-control form-control-sm fee-count" value="2" min="1" max="12" style="width:90px">
                </div>
                <div>
                    <label class="form-label small mb-0">{{ __('First due date') }}</label>
                    <input type="date" class="form-control form-control-sm fee-start">
                </div>
                <button type="button" class="btn btn-sm btn-primary fee-generate">
                    <i class="fas fa-calendar-alt"></i> {{ __('btn_generate_schedule') }}
                </button>
                <small class="text-muted">{{ __('Then a month apart. Every amount and date can be changed.') }} {{ __('One instalment moves the whole balance to a later date.') }}</small>
            </div>
            <table class="table table-sm table-bordered mb-1">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>{{ __('field_amount') }} ({!! $currency !!})</th>
                        <th>{{ __('field_due_date') }}</th>
                    </tr>
                </thead>
                <tbody class="fee-rows"></tbody>
                <tfoot>
                    <tr>
                        <th>{{ __('total') }}</th>
                        <th><span class="fee-sum">0.00</span> <span class="small fee-check"></span></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>

@endsection

@section('page_js')
<script>
$(function () {
    'use strict';

    var FEES_URL = @json(route('admin.payment-plan.get-student-fees'));
    var T = {
        none: @json(__('This student has no fee that can go on an agreement.')),
        loading: @json(__('loading')),
        ok: @json(__('matches')),
        short: @json(__('short by')),
        over: @json(__('over by')),
        pick: @json(__('Tick at least one fee.')),
        fix: @json(__('Every ticked fee must add up to its balance.')),
        ready: @json(__('Ready to prepare.')),
        fees: @json(__('fees')),
        instalments: @json(__('instalments')),
        partly: @json(__('already paid'))
    };
    var fmt = function (v) { return (parseFloat(v) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var round = function (v) { return Math.round((parseFloat(v) || 0) * 100) / 100; };
    var iso = function (d) { return d.toISOString().split('T')[0]; };

    function addMonths(dateStr, months) {
        var d = new Date(dateStr + 'T00:00:00');
        var day = d.getDate();
        d.setMonth(d.getMonth() + months);
        if (d.getDate() !== day) { d.setDate(0); }
        return iso(d);
    }

    function renderFees(fees) {
        var list = $('#fees-list').empty();
        $('#fees-empty').toggle(fees.length === 0).text(fees.length ? '' : T.none);

        var today = iso(new Date());

        fees.forEach(function (fee) {
            var card = $($('#fee-card-template').html());
            var name = 'plans[' + fee.id + ']';

            card.attr('data-fee', fee.id).attr('data-balance', fee.remaining_balance);
            card.find('.fee-include').attr('name', name + '[include]').attr('id', 'include-' + fee.id);
            card.find('.fee-title').attr('for', 'include-' + fee.id).text(fee.category_title);
            card.find('.fee-meta').text([
                fee.session || '',
                fee.due_date ? ('due ' + fee.due_date) : '',
                fee.paid_amount > 0 ? (T.partly + ' ' + fmt(fee.paid_amount)) : ''
            ].filter(Boolean).join(' · '));
            card.find('.fee-balance').text(fmt(fee.remaining_balance));
            card.find('.fee-start').val(fee.due_date && fee.due_date > today ? fee.due_date : addMonths(today, 1));
            list.append(card);
        });

        refresh();
    }

    function generate(card) {
        var count = Math.max(1, Math.min(12, parseInt(card.find('.fee-count').val(), 10) || 1));
        var balance = round(card.data('balance'));
        var each = Math.floor(balance / count * 100) / 100;
        var start = card.find('.fee-start').val() || iso(new Date());
        var name = 'plans[' + card.data('fee') + '][installments]';
        var rows = card.find('.fee-rows').empty();
        var left = balance;

        for (var i = 0; i < count; i++) {
            var amount = i === count - 1 ? round(left) : each;
            left = round(left - amount);
            rows.append('<tr><td>' + (i + 1) + '</td>'
                + '<td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm row-amount" name="' + name + '[' + i + '][amount]" value="' + amount.toFixed(2) + '" required></td>'
                + '<td><input type="date" class="form-control form-control-sm" name="' + name + '[' + i + '][due_date]" value="' + addMonths(start, i) + '" required></td></tr>');
        }

        refresh();
    }

    function refresh() {
        var total = 0, included = 0, balanced = true, count = 0;

        $('#fees-list .fee-card').each(function () {
            var card = $(this);
            var on = card.find('.fee-include').is(':checked');
            card.find('.fee-body').toggleClass('d-none', !on);
            // Inputs of an unticked fee are not sent.
            card.find('.fee-rows input').prop('disabled', !on);

            if (!on) { return; }
            included++;

            if (!card.find('.fee-rows tr').length) { generate(card); return; }

            var sum = 0;
            card.find('.row-amount').each(function () { sum += parseFloat(this.value) || 0; count++; });
            sum = round(sum);
            var diff = round(sum - round(card.data('balance')));
            card.find('.fee-sum').text(fmt(sum));
            card.find('.fee-check')
                .toggleClass('text-success', Math.abs(diff) < 0.01)
                .toggleClass('text-danger', Math.abs(diff) >= 0.01)
                .text(Math.abs(diff) < 0.01 ? '✓ ' + T.ok : (diff < 0 ? T.short + ' ' + fmt(-diff) : T.over + ' ' + fmt(diff)));

            if (Math.abs(diff) >= 0.01) { balanced = false; }
            total += sum;
        });

        $('#agreement-summary').toggleClass('d-none', included === 0);
        $('#agreement-total').text(fmt(total));
        $('#agreement-count').text(included + ' ' + T.fees + ' · ' + count + ' ' + T.instalments);

        var ready = included > 0 && balanced;
        $('#agreement-state')
            .toggleClass('text-success', ready).toggleClass('text-danger', !ready)
            .text(included === 0 ? T.pick : (balanced ? T.ready : T.fix));
        $('#submit-btn').prop('disabled', !ready);
    }

    $('#student_id').on('select2:select change', function () {
        var id = $(this).val();
        $('#fees-list').empty();
        $('#agreement-summary').addClass('d-none');
        $('#submit-btn').prop('disabled', true);

        if (!id) { $('#fees-empty').show(); return; }
        $('#fees-empty').show().text(T.loading + '...');

        $.get(FEES_URL, { student_id: id }).done(function (response) {
            renderFees(response.fees || []);
        });
    });

    $('#fees-list')
        .on('change', '.fee-include', refresh)
        .on('click', '.fee-generate', function () { generate($(this).closest('.fee-card')); })
        .on('input', '.row-amount', refresh);

    if ($('#student_id').val()) { $('#student_id').trigger('change'); }
});
</script>
@endsection
