@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{--
    Revising an agreement's schedule.

    Instalments that have taken money are history and stay exactly as they
    are. Everything still unpaid can be re-dated, re-split, added to or
    removed, as long as each fee's schedule adds up to what it now owes —
    which, after a fee changed, is the very thing that needs fixing. Saving
    sends the agreement (back) to the Vice Chancellor, with the change listed.
--}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Revise schedule') }} · {{ $agreement->reference }}</h5>
                <a href="{{ route('admin.payment-plan.agreement.show', $agreement->id) }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
            </div>
            <div class="card-block">
                <div class="alert alert-warning small">
                    <i class="fas fa-info-circle"></i>
                    {{ __('Saving a revision sends the agreement to the Vice Chancellor for approval again. No payment can be taken on it until then.') }}
                </div>

                @if($mismatches)
                    <div class="alert alert-danger small">
                        <ul class="mb-0 ps-3">@foreach($mismatches as $line)<li>{{ $line }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="post" action="{{ route('admin.payment-plan.agreement.revise.store', $agreement->id) }}" id="revise-form">
                    @csrf

                    @foreach($agreement->plans as $plan)
                        @continue(!in_array($plan->status, ['pending', 'active'], true))
                        @php
                            $lockedIds = $locked[$plan->id] ?? [];
                            $lockedRows = $plan->installments->whereIn('id', $lockedIds)->sortBy('installment_number');
                            $openRows = $plan->installments->whereNotIn('id', $lockedIds)->sortBy('due_date')->values();
                            $target = round(($expected[$plan->id] ?? 0) - $lockedRows->sum(fn ($i) => (float) $i->amount), 2);
                        @endphp
                        <div class="card mb-3 plan-card" data-plan="{{ $plan->id }}" data-target="{{ $target }}">
                            {{-- Sent even when every unpaid row is removed, so the
                                 plan is still known to be part of the revision. --}}
                            <input type="hidden" name="plans[{{ $plan->id }}][present]" value="1">
                            <div class="card-header py-2 d-flex justify-content-between flex-wrap gap-2">
                                <strong>{{ optional(optional($plan->fee)->category)->title }}</strong>
                                <span class="small">
                                    {{ __('Owed on it now') }}: <b>{{ $money($expected[$plan->id] ?? 0) }}</b>
                                    &middot; {{ __('Left to schedule') }}: <b>{{ $money($target) }}</b>
                                </span>
                            </div>
                            <div class="card-body py-2">
                                <table class="table table-sm table-bordered mb-1">
                                    <thead>
                                        <tr>
                                            <th>{{ __('field_amount') }}</th>
                                            <th>{{ __('field_due_date') }}</th>
                                            <th style="width:50px"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="rows">
                                        @foreach($lockedRows as $row)
                                        <tr class="table-light">
                                            <td>{{ $money($row->amount) }} <span class="text-muted small">({{ __('has taken :paid, stays as it is', ['paid' => $money($row->paid_amount)]) }})</span></td>
                                            <td>{{ optional($row->due_date)->format('d M Y') }}</td>
                                            <td><i class="fas fa-lock text-muted"></i></td>
                                        </tr>
                                        @endforeach
                                        @foreach($openRows as $i => $row)
                                        <tr class="open-row">
                                            <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm row-amount" name="plans[{{ $plan->id }}][installments][{{ $i }}][amount]" value="{{ old('plans.' . $plan->id . '.installments.' . $i . '.amount', number_format((float) $row->amount, 2, '.', '')) }}" required></td>
                                            <td><input type="date" class="form-control form-control-sm" name="plans[{{ $plan->id }}][installments][{{ $i }}][due_date]" value="{{ old('plans.' . $plan->id . '.installments.' . $i . '.due_date', optional($row->due_date)->format('Y-m-d')) }}" required></td>
                                            <td><button type="button" class="btn btn-sm btn-light remove-row" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>{{ __('Scheduled') }}: <span class="sum">0.00</span> <span class="check small"></span></th>
                                            <th colspan="2"><button type="button" class="btn btn-sm btn-outline-primary add-row"><i class="fas fa-plus"></i> {{ __('Add instalment') }}</button></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <div class="form-group">
                        <label class="form-label">{{ __('What changed, and why') }} <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required>{{ old('reason') }}</textarea>
                        <small class="text-muted">{{ __('Printed on the revised agreement and shown to the Vice Chancellor.') }}</small>
                    </div>

                    <button type="submit" class="btn btn-warning" id="save-revision"><i class="fas fa-paper-plane"></i> {{ __('Save and send for approval') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script>
$(function () {
    'use strict';
    var fmt = function (v) { return (parseFloat(v) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var round = function (v) { return Math.round((parseFloat(v) || 0) * 100) / 100; };
    var T = { ok: @json(__('matches')), short: @json(__('short by')), over: @json(__('over by')) };

    function renumber(card) {
        var plan = card.data('plan');
        card.find('tr.open-row').each(function (i) {
            $(this).find('input').each(function () {
                this.name = this.name.replace(/\[installments\]\[\d+\]/, '[installments][' + i + ']');
            });
        });
    }

    function refresh() {
        var ok = true;
        $('.plan-card').each(function () {
            var card = $(this), sum = 0;
            card.find('.row-amount').each(function () { sum += parseFloat(this.value) || 0; });
            sum = round(sum);
            var diff = round(sum - round(card.data('target')));
            card.find('.sum').text(fmt(sum));
            card.find('.check').toggleClass('text-success', Math.abs(diff) < 0.01).toggleClass('text-danger', Math.abs(diff) >= 0.01)
                .text(Math.abs(diff) < 0.01 ? '✓ ' + T.ok : (diff < 0 ? T.short + ' ' + fmt(-diff) : T.over + ' ' + fmt(diff)));
            if (Math.abs(diff) >= 0.01) { ok = false; }
        });
        $('#save-revision').prop('disabled', !ok);
    }

    $(document).on('input', '.row-amount', refresh);
    $(document).on('click', '.remove-row', function () {
        var card = $(this).closest('.plan-card');
        $(this).closest('tr').remove();
        renumber(card);
        refresh();
    });
    $(document).on('click', '.add-row', function () {
        var card = $(this).closest('.plan-card');
        var plan = card.data('plan');
        var i = card.find('tr.open-row').length;
        card.find('tbody.rows').append('<tr class="open-row">'
            + '<td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm row-amount" name="plans[' + plan + '][installments][' + i + '][amount]" value="0.00" required></td>'
            + '<td><input type="date" class="form-control form-control-sm" name="plans[' + plan + '][installments][' + i + '][due_date]" required></td>'
            + '<td><button type="button" class="btn btn-sm btn-light remove-row"><i class="fas fa-times"></i></button></td></tr>');
        refresh();
    });

    refresh();
});
</script>
@endsection
