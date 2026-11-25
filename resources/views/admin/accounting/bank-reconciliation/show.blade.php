@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@include('admin.supplier-bill.partials.style')

@php
    $account = $rec->chartOfAccount;
    $done = !$editable;
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
                        <h5 class="mb-0">{{ __('Bank reconciliation') }} — {{ $account->account_code ?? '' }} {{ $account->account_name ?? '' }}
                            · {{ format_date($rec->statement_date) }}
                            @if($done)<span class="ap-chip paid">{{ __('Completed') }}</span>@else<span class="ap-chip part_paid">{{ __('In progress') }}</span>@endif
                        </h5>
                        <div class="ap-toolbar">
                            <a href="{{ route('admin.bank-reconciliation.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                            @if($editable)
                                @can('bank-reconciliation-complete')
                                <form method="post" action="{{ route('admin.bank-reconciliation.complete', $rec->id) }}" id="complete-form" class="d-none">@csrf</form>
                                <button type="button" class="btn btn-success btn-sm"
                                        data-review-url="{{ route('admin.bank-reconciliation.preview-complete', $rec->id) }}"
                                        data-confirm-form="complete-form" data-confirm-label="{{ __('Complete reconciliation') }}"
                                        data-confirm-check="{{ __('I have matched the ticked movements against the bank statement and confirm the reconciliation.') }}">
                                    <i class="fas fa-check"></i> {{ __('Review and complete') }}</button>
                                @endcan
                                @can('bank-reconciliation-delete')
                                <form method="post" action="{{ route('admin.bank-reconciliation.destroy', $rec->id) }}" class="d-inline" data-confirm="{{ __('Delete this reconciliation? Nothing in the ledger is changed.') }}" onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> {{ __('Delete') }}</button>
                                </form>
                                @endcan
                            @endif
                        </div>
                    </div>

                    <div class="card-block">
                        <div class="row">
                            <div class="col-lg-6">
                                <table class="table table-sm ap-table mb-3" id="bank-position">
                                    <tbody>
                                        <tr><td>{{ __('Closing balance on the statement') }}</td><td class="ap-num" data-v="statement">{{ format_amount($position['statement']) }}</td></tr>
                                        <tr><td>+ {{ __('Deposits in transit (in the books, not yet on the statement)') }}</td><td class="ap-num" data-v="in_transit">{{ format_amount($position['in_transit']) }}</td></tr>
                                        <tr><td>− {{ __('Outstanding payments (in the books, not yet on the statement)') }}</td><td class="ap-num" data-v="outstanding">{{ format_amount($position['outstanding']) }}</td></tr>
                                        <tr class="ap-total-row"><td>= {{ __('Adjusted bank balance') }}</td><td class="ap-num" data-v="adjusted">{{ format_amount($position['adjusted']) }}</td></tr>
                                        <tr><td>{{ __('Balance in the books at the statement date') }}</td><td class="ap-num" data-v="book">{{ format_amount($position['book']) }}</td></tr>
                                        <tr class="ap-total-row"><td>{{ __('Difference') }}</td>
                                            <td class="ap-num"><span data-v="difference">{{ format_amount($position['difference']) }}</span>
                                                <span id="diff-chip" class="ap-chip {{ abs($position['difference']) < 0.01 ? 'paid' : 'unpaid' }}">{{ abs($position['difference']) < 0.01 ? __('Agrees') : __('Does not agree') }}</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-lg-6">
                                @if($editable)
                                <div class="ap-note mb-3">{{ __('Tick each movement that appears on the bank statement, then save. The difference must be nil to complete. A charge or interest on the statement that is not in the books can be posted below.') }}</div>
                                <form method="post" action="{{ route('admin.bank-reconciliation.update-balance', $rec->id) }}" class="ap-filters">
                                    @csrf
                                    <div class="form-group">
                                        <label for="statement_closing_balance">{{ __('Statement balance') }}</label>
                                        <input type="number" step="0.01" class="form-control" name="statement_closing_balance" id="statement_closing_balance" value="{{ $rec->statement_closing_balance }}">
                                    </div>
                                    <div class="form-group"><button class="btn btn-outline-primary" type="submit">{{ __('Update') }}</button></div>
                                </form>
                                @else
                                <div class="ap-note mb-3">{{ __('Completed :date by :name.', ['date' => format_date($rec->reconciled_at), 'name' => optional($rec->reconciledBy)->first_name . ' ' . optional($rec->reconciledBy)->last_name]) }}</div>
                                @endif
                            </div>
                        </div>

                        <form method="post" action="{{ route('admin.bank-reconciliation.ticks', $rec->id) }}" id="ticks-form">
                            @csrf
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">{{ __('Movements in the books') }} <span class="ap-muted">({{ $position['ticked'] }} {{ __('ticked') }}, {{ $position['unticked'] }} {{ __('not on the statement') }})</span></h6>
                                @if($editable)
                                <div class="ap-toolbar">
                                    <button type="button" class="btn btn-light btn-sm" id="tick-all">{{ __('Tick all') }}</button>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> {{ __('Save ticks') }}</button>
                                </div>
                                @endif
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover ap-table">
                                    <thead>
                                        <tr>
                                            <th style="width:40px">{{ __('On statement') }}</th>
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Entry') }}</th>
                                            <th>{{ __('Description') }}</th>
                                            <th class="ap-num">{{ __('Money in') }}</th>
                                            <th class="ap-num">{{ __('Money out') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rec->items as $item)
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="cleared[]" value="{{ $item->id }}" class="tick" data-effect="{{ $item->effect }}" data-amount="{{ $item->amount }}"
                                                       aria-label="{{ __('On statement') }}" @checked($item->is_cleared) @disabled(!$editable)>
                                            </td>
                                            <td class="text-nowrap">{{ format_date($item->transaction_date) }}</td>
                                            <td class="text-nowrap">
                                                @if($item->journal_entry_id)<a href="{{ route('admin.journal-entries.show', $item->journal_entry_id) }}">{{ $item->reference_number }}</a>@else{{ $item->reference_number }}@endif
                                                @if(in_array($item->item_type, ['bank_charge', 'bank_interest']))<span class="ap-chip posted">{{ $item->item_type === 'bank_charge' ? __('Charge') : __('Interest') }}</span>@endif
                                            </td>
                                            <td>{{ $item->description }}</td>
                                            <td class="ap-num">{{ $item->effect === 'add' ? format_amount($item->amount) : '' }}</td>
                                            <td class="ap-num">{{ $item->effect === 'subtract' ? format_amount($item->amount) : '' }}</td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="6" class="ap-muted">{{ __('No movement in the books up to this date that has not already been reconciled.') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </form>

                        @if($editable)
                        @can('bank-reconciliation-edit')
                        <h6 class="mt-4 mb-2">{{ __('Post a charge or interest shown on the statement') }}</h6>
                        <form method="post" action="{{ route('admin.bank-reconciliation.add-adjustment', $rec->id) }}" id="adj-form" class="ap-filters">
                            @csrf
                            <div class="form-group">
                                <label for="adj-kind">{{ __('What') }}</label>
                                <select name="kind" id="adj-kind" class="form-control" required>
                                    <option value="bank_charge">{{ __('Bank charges') }}</option>
                                    <option value="bank_interest">{{ __('Bank interest') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="adj-amount">{{ __('Amount') }}</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="adj-amount" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="adj-date">{{ __('Date') }}</label>
                                <input type="date" name="date" id="adj-date" class="form-control" required value="{{ $rec->statement_date->toDateString() }}" max="{{ $rec->statement_date->toDateString() }}">
                            </div>
                            <div class="form-group" style="min-width:260px">
                                <label for="adj-account">{{ __('Category') }}</label>
                                <select name="category_id" id="adj-account" class="form-control" required>
                                    <optgroup label="{{ __('Expense categories (bank charges)') }}" data-kind="bank_charge">
                                        @foreach($chargeCategories as $c)<option value="{{ $c['id'] }}" data-kind="bank_charge" @selected(stripos($c['title'], 'bank') !== false || stripos($c['title'], 'banc') !== false)>{{ $c['title'] }} — {{ $c['line_code'] }} {{ $c['line_name'] }}</option>@endforeach
                                    </optgroup>
                                    <optgroup label="{{ __('Income categories (bank interest)') }}" data-kind="bank_interest">
                                        @foreach($interestCategories as $c)<option value="{{ $c['id'] }}" data-kind="bank_interest">{{ $c['title'] }} — {{ $c['line_code'] }} {{ $c['line_name'] }}</option>@endforeach
                                    </optgroup>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="adj-desc">{{ __('Note') }}</label>
                                <input type="text" name="description" id="adj-desc" class="form-control" maxlength="150">
                            </div>
                            <div class="form-group">
                                <button type="button" class="btn btn-outline-success"
                                        data-review-url="{{ route('admin.bank-reconciliation.preview-adjustment', $rec->id) }}"
                                        data-review-form="adj-form" data-confirm-form="adj-form" data-confirm-label="{{ __('Post and tick') }}"
                                        data-confirm-check="{{ __('I have checked the amount and the account against the bank statement, and confirm this posting.') }}">
                                    <i class="fas fa-search"></i> {{ __('Review and post') }}</button>
                            </div>
                        </form>
                        @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.supplier-bill.partials.review-modal')
@endsection

@push('scripts')
<script>
(function () {
    var ticks = document.querySelectorAll('.tick');
    var pos = @json(['book' => (float) $position['book'], 'statement' => (float) $position['statement']]);
    var book = pos.book;
    var statement = pos.statement;
    var fmt = function (v) { return (Math.round(v * 100) / 100).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 2}); };
    var agrees = @json(__('Agrees'));
    var differs = @json(__('Does not agree'));

    // Recompute the position as boxes are ticked, so the accountant sees the
    // difference close before saving. The server recomputes on save.
    function refresh() {
        var inTransit = 0, outstanding = 0;
        ticks.forEach(function (t) {
            if (t.checked) { return; }
            var a = parseFloat(t.getAttribute('data-amount')) || 0;
            if (t.getAttribute('data-effect') === 'add') { inTransit += a; } else { outstanding += a; }
        });
        var adjusted = statement + inTransit - outstanding;
        var diff = book - adjusted;
        var set = function (k, v) { var el = document.querySelector('[data-v="' + k + '"]'); if (el) { el.textContent = fmt(v); } };
        set('in_transit', inTransit); set('outstanding', outstanding); set('adjusted', adjusted); set('difference', diff);
        var chip = document.getElementById('diff-chip');
        var ok = Math.abs(diff) < 0.01;
        chip.className = 'ap-chip ' + (ok ? 'paid' : 'unpaid');
        chip.textContent = ok ? agrees : differs;
    }

    ticks.forEach(function (t) { t.addEventListener('change', refresh); });
    var all = document.getElementById('tick-all');
    if (all) { all.addEventListener('click', function () { ticks.forEach(function (t) { if (!t.disabled) { t.checked = true; } }); refresh(); }); }

    // Offer only the accounts that suit the kind chosen.
    var kind = document.getElementById('adj-kind');
    var account = document.getElementById('adj-account');
    function filterAccounts() {
        if (!kind || !account) { return; }
        account.querySelectorAll('optgroup').forEach(function (g) {
            var show = g.getAttribute('data-kind') === kind.value;
            g.hidden = !show;
            g.querySelectorAll('option').forEach(function (o) { o.disabled = !show; });
        });
        var first = account.querySelector('optgroup:not([hidden]) option');
        if (account.selectedOptions[0] && account.selectedOptions[0].disabled && first) { first.selected = true; }
    }
    if (kind) { kind.addEventListener('change', filterAccounts); filterAccounts(); }
})();
</script>
@endpush
