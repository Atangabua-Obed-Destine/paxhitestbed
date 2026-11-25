@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@include('admin.supplier-bill.partials.style')

@php
    $kind = old('kind', $row->kind ?? 'bill');
    $lines = old('lines', $row && !$row->isOpeningBalance()
        ? $row->lines->map(fn ($l) => ['description' => $l->description, 'expense_category_id' => $l->expense_category_id, 'amount' => (float) $l->amount])->all()
        : []);
    if (!$lines) { $lines = [['description' => '', 'expense_category_id' => '', 'amount' => '']]; }
    $openingLine = $row && $row->isOpeningBalance() ? $row->lines->first() : null;
    $supplierId = old('supplier_id', $row->supplier_id ?? $selected_supplier);
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <a href="{{ $row ? route($route.'.show', $row->id) : route($route.'.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                    </div>

                    <form class="needs-validation" novalidate method="post" enctype="multipart/form-data"
                          action="{{ $row ? route($route.'.update', $row->id) : route($route.'.store') }}">
                        @csrf
                        @if($row) @method('PUT') @endif

                        <div class="card-block">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label class="d-block">{{ __('What is this?') }}</label>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="kind_bill" name="kind" value="bill" class="custom-control-input" @checked($kind === 'bill')>
                                        <label class="custom-control-label" for="kind_bill">{{ __('A bill from a supplier') }}</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="kind_opening" name="kind" value="opening_balance" class="custom-control-input" @checked($kind === 'opening_balance')>
                                        <label class="custom-control-label" for="kind_opening">{{ __('An opening balance (owed before the school started using this screen)') }}</label>
                                    </div>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="supplier_id">{{ __('Supplier') }} <span>*</span></label>
                                    <select name="supplier_id" id="supplier_id" class="form-control select2" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($suppliers as $s)
                                        <option value="{{ $s->id }}" data-terms="{{ $s->payment_terms_days }}" @selected($supplierId == $s->id)>{{ $s->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="ap-muted mt-1">
                                        @can('item-supplier-create')
                                        <a href="{{ route('admin.item-supplier.create') }}" target="_blank">{{ __('Add a supplier') }}</a>
                                        @endcan
                                    </div>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="supplier_reference">{{ __('Their invoice number') }}</label>
                                    <input type="text" class="form-control" name="supplier_reference" id="supplier_reference" maxlength="100"
                                           value="{{ old('supplier_reference', $row->supplier_reference ?? '') }}">
                                    <div class="ap-muted mt-1">{{ __('Checked on approval, so one invoice cannot be entered twice.') }}</div>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="bill_date">{{ __('Bill date') }} <span>*</span></label>
                                    <input type="date" class="form-control" name="bill_date" id="bill_date" required max="{{ now()->toDateString() }}"
                                           value="{{ old('bill_date', isset($row) ? $row->bill_date->toDateString() : now()->toDateString()) }}">
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="due_date">{{ __('Due date') }}</label>
                                    <input type="date" class="form-control" name="due_date" id="due_date"
                                           value="{{ old('due_date', isset($row) ? $row->due_date->toDateString() : '') }}">
                                    <div class="ap-muted mt-1" id="due-hint">{{ __('Empty: from the supplier terms') }}</div>
                                </div>

                                <div class="form-group col-md-8">
                                    <label for="description">{{ __('Description') }}</label>
                                    <input type="text" class="form-control" name="description" id="description" maxlength="255"
                                           value="{{ old('description', $row->description ?? '') }}">
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="attach">{{ __('Scanned invoice') }}</label>
                                    <input type="file" class="form-control" name="attach" id="attach" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                    @if($row && $row->attach)
                                    <div class="ap-muted mt-1"><a href="{{ asset('uploads/'.$path.'/'.$row->attach) }}" target="_blank">{{ __('Current file') }}</a></div>
                                    @endif
                                </div>
                            </div>

                            {{-- Lines of a bill --}}
                            <div id="bill-lines" @if($kind === 'opening_balance') style="display:none" @endif>
                                <h6 class="mt-2">{{ __('Lines') }}</h6>
                                <p class="ap-muted">{{ __('Each line is filed under an expense category, which decides the ledger account and the budget line, exactly as an expense does.') }}</p>
                                <div class="table-responsive">
                                    <table class="table ap-table" id="lines-table">
                                        <thead>
                                            <tr>
                                                <th style="width:38%">{{ __('Expense category') }} <span>*</span></th>
                                                <th>{{ __('Description') }}</th>
                                                <th class="ap-num" style="width:180px">{{ __('Amount') }} <span>*</span></th>
                                                <th style="width:50px"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($lines as $i => $line)
                                            <tr class="bill-line">
                                                <td>
                                                    <select name="lines[{{ $i }}][expense_category_id]" class="form-control line-category">
                                                        <option value="">{{ __('select') }}</option>
                                                        @foreach($categories as $heading => $items)
                                                        <optgroup label="{{ $heading }}">
                                                            @foreach($items as $c)
                                                            <option value="{{ $c['id'] }}" @selected(($line['expense_category_id'] ?? '') == $c['id'])>{{ $c['title'] }} ({{ $c['line_code'] }})</option>
                                                            @endforeach
                                                        </optgroup>
                                                        @endforeach
                                                        @foreach($kept_categories as $c)
                                                        <option value="{{ $c->id }}" @selected(($line['expense_category_id'] ?? '') == $c->id)>{{ $c->title }} — {{ __('no longer filed under a budget line') }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="text" name="lines[{{ $i }}][description]" class="form-control" maxlength="255" value="{{ $line['description'] ?? '' }}"></td>
                                                <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][amount]" class="form-control ap-num line-amount" value="{{ $line['amount'] ?? '' }}"></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td><button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="fas fa-plus"></i> {{ __('Add a line') }}</button></td>
                                                <td class="text-right"><strong>{{ __('Total') }}</strong></td>
                                                <td class="ap-num"><strong id="lines-total">0</strong></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            {{-- An opening balance --}}
                            <div id="opening-fields" class="row" @if($kind !== 'opening_balance') style="display:none" @endif>
                                <div class="col-md-12">
                                    <div class="ap-note mb-3">{{ __('An opening balance is what the school already owed this supplier on the day it started using this screen. It is paid and aged like any bill, but it was last year\'s cost: it is set against an equity account and never touches this year\'s budget.') }}</div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="opening_account_id">{{ __('Set against') }} <span>*</span></label>
                                    <select name="opening_account_id" id="opening_account_id" class="form-control">
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($opening_accounts as $acc)
                                        <option value="{{ $acc->id }}" @selected(old('opening_account_id', $openingLine->account_id ?? '') == $acc->id)>{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="opening_amount">{{ __('Amount owed') }} <span>*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control ap-num" name="opening_amount" id="opening_amount"
                                           value="{{ old('opening_amount', $openingLine ? (float) $openingLine->amount : '') }}">
                                </div>
                            </div>
                        </div>

                        <div class="card-footer d-flex flex-wrap align-items-center" style="gap:8px">
                            <button type="submit" name="after" value="draft" class="btn btn-outline-success"><i class="fas fa-save"></i> {{ __('Save as draft') }}</button>
                            @can('supplier-bill-approve')
                            <button type="submit" name="after" value="approve" class="btn btn-success">
                                <i class="fas fa-check"></i> {{ __('Save and review for approval') }}</button>
                            @endcan
                            <span class="ap-muted">{{ __('Nothing is posted until you have reviewed the ledger entry and confirmed it.') }}</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="line-template">
    <tr class="bill-line">
        <td>
            <select name="lines[__i__][expense_category_id]" class="form-control line-category">
                <option value="">{{ __('select') }}</option>
                @foreach($categories as $heading => $items)
                <optgroup label="{{ $heading }}">
                    @foreach($items as $c)
                    <option value="{{ $c['id'] }}">{{ $c['title'] }} ({{ $c['line_code'] }})</option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
        </td>
        <td><input type="text" name="lines[__i__][description]" class="form-control" maxlength="255"></td>
        <td><input type="number" step="0.01" min="0" name="lines[__i__][amount]" class="form-control ap-num line-amount"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button></td>
    </tr>
</template>

@endsection

@push('scripts')
<script>
(function () {
    var tbody = document.querySelector('#lines-table tbody');
    var next = tbody.querySelectorAll('tr').length;
    var fmt = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });

    function total() {
        var sum = 0;
        tbody.querySelectorAll('.line-amount').forEach(function (i) { sum += parseFloat(i.value) || 0; });
        document.getElementById('lines-total').textContent = fmt.format(Math.round(sum * 100) / 100);
    }

    document.getElementById('add-line').addEventListener('click', function () {
        var html = document.getElementById('line-template').innerHTML.replace(/__i__/g, next++);
        tbody.insertAdjacentHTML('beforeend', html);
    });

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-line');
        if (!btn) { return; }
        if (tbody.querySelectorAll('tr').length > 1) { btn.closest('tr').remove(); } else {
            btn.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = ''; });
            btn.closest('tr').querySelector('select').value = '';
        }
        total();
    });
    tbody.addEventListener('input', total);
    tbody.addEventListener('change', function (e) {
        if (e.target.matches('select, input')) { e.target.classList.remove('is-invalid'); }
    });
    total();

    // Catch a half-filled line here rather than after a round trip: an
    // amount with no category, or a category with no amount.
    tbody.closest('form').addEventListener('submit', function (e) {
        if (document.getElementById('kind_opening').checked) { return; }
        var bad = 0, filled = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var cat = tr.querySelector('.line-category');
            var amt = tr.querySelector('.line-amount');
            var hasAmt = (parseFloat(amt.value) || 0) > 0;
            var hasCat = cat.value !== '';
            cat.classList.toggle('is-invalid', hasAmt && !hasCat);
            amt.classList.toggle('is-invalid', hasCat && !hasAmt);
            if (hasAmt !== hasCat) { bad++; }
            if (hasAmt && hasCat) { filled++; }
        });
        if (bad || !filled) {
            e.preventDefault();
            alert(bad ? @json(__('Every line needs both an expense category and an amount above zero.'))
                      : @json(__('A bill needs at least one line.')));
        }
    });

    document.querySelectorAll('input[name="kind"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var opening = document.getElementById('kind_opening').checked;
            document.getElementById('bill-lines').style.display = opening ? 'none' : '';
            document.getElementById('opening-fields').style.display = opening ? '' : 'none';
        });
    });

    // Say what the due date will be when it is left empty.
    function hint() {
        var opt = $('#supplier_id option:selected');
        var days = parseInt(opt.data('terms'), 10);
        document.getElementById('due-hint').textContent = days > 0
            ? @json(__('Empty: bill date +')) + ' ' + days + ' ' + @json(__('days'))
            : @json(__('Empty: due on the bill date'));
    }
    $('#supplier_id').on('change', hint);
    hint();
})();
</script>
@endpush
