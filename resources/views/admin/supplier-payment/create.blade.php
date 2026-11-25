@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@include('admin.supplier-bill.partials.style')

@php
    $oldAlloc = old('allocations', []);
    $totalOwed = round($bills->sum('owed'), 2);
    $today = now()->toDateString();
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header"><h5>{{ $title }}</h5></div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>

                        {{-- Step 1: the supplier. Choosing one reloads with their unpaid bills. --}}
                        <form method="get" action="{{ route($route.'.create') }}" class="ap-filters mt-3">
                            <div class="form-group" style="min-width:320px">
                                <label for="pick_supplier">{{ __('Supplier') }} <span>*</span></label>
                                <select name="supplier_id" id="pick_supplier" class="form-control select2" onchange="this.form.submit()">
                                    <option value="">{{ __('select') }}</option>
                                    @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(optional($supplier)->id == $s->id)>{{ $s->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if($suppliers->isEmpty())
                            <div class="ap-muted">{{ __('No supplier is owed anything. Approve a bill first.') }}</div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            @if($supplier)
            <div class="col-sm-12">
                <form method="post" action="{{ route($route.'.store') }}" class="card needs-validation" novalidate id="pay-form">
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                    <div class="card-block">
                        <h6>{{ __('Unpaid bills of') }} {{ $supplier->title }}</h6>
                        <p class="ap-muted">{{ __('Oldest due first. Type how much of the payment goes to each bill, or use the buttons; the payment is the sum.') }}</p>
                        <div class="table-responsive">
                            <table class="table ap-table" id="alloc-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Bill') }}</th>
                                        <th>{{ __('Their invoice') }}</th>
                                        <th>{{ __('Bill date') }}</th>
                                        <th>{{ __('Due') }}</th>
                                        <th class="ap-num">{{ __('Total') }}</th>
                                        <th class="ap-num">{{ __('Owed') }}</th>
                                        <th class="ap-num" style="width:190px">{{ __('Pay now') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bills as $b)
                                    @php
                                        $value = $oldAlloc[$b->id] ?? ($preselect_bill === (int) $b->id ? $b->owed : '');
                                        $late = $b->due_date < $today;
                                    @endphp
                                    <tr>
                                        <td><a href="{{ route('admin.supplier-bill.show', $b->id) }}" target="_blank">{{ $b->number }}</a>
                                            @if($b->kind === 'opening_balance')<div class="ap-muted">{{ __('Opening balance') }}</div>@endif</td>
                                        <td>{{ $b->supplier_reference ?? '—' }}</td>
                                        <td>{{ format_date($b->bill_date) }}</td>
                                        <td>{{ format_date($b->due_date) }} @if($late)<span class="ap-chip overdue">{{ __('Overdue') }}</span>@endif</td>
                                        <td class="ap-num">{{ format_amount($b->total) }}</td>
                                        <td class="ap-num">{{ format_amount($b->owed) }}</td>
                                        <td><input type="number" step="0.01" min="0" max="{{ $b->owed }}" data-owed="{{ $b->owed }}"
                                                   name="allocations[{{ $b->id }}]" class="form-control ap-num alloc" value="{{ $value }}"></td>
                                    </tr>
                                    @endforeach
                                    <tr class="ap-total-row">
                                        <td colspan="5" class="text-right">
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="pay-all">{{ __('Pay everything owed') }}</button>
                                            <button type="button" class="btn btn-sm btn-light" id="pay-none">{{ __('Clear') }}</button>
                                        </td>
                                        <td class="ap-num">{{ format_amount($totalOwed) }}</td>
                                        <td class="ap-num"><span id="alloc-total">0</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-2">
                            <div class="form-group col-md-3">
                                <label for="amount">{{ __('Amount paid') }} <span>*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control ap-num" name="amount" id="amount" required readonly
                                       value="{{ old('amount') }}">
                                <div class="ap-muted mt-1">{{ __('The sum of the bills above.') }}</div>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="payment_date">{{ __('Payment date') }} <span>*</span></label>
                                <input type="date" class="form-control" name="payment_date" id="payment_date" required max="{{ $today }}"
                                       value="{{ old('payment_date', $today) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="payment_method">{{ __('Payment method') }}</label>
                                <select name="payment_method" id="payment_method" class="form-control">
                                    @foreach($methods as $k => $label)
                                    <option value="{{ $k }}" data-account="{{ $method_accounts[$k] ?? '' }}" @selected(old('payment_method', 1) == $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="reference">{{ __('Cheque or transfer number') }}</label>
                                <input type="text" class="form-control" name="reference" id="reference" maxlength="100" value="{{ old('reference') }}">
                            </div>

                            @if($payment_accounts->isNotEmpty())
                            <div class="form-group col-md-4">
                                <label for="payment_account_id">{{ __('Cash box or bank (Payment Accounts)') }}</label>
                                <select name="payment_account_id" id="payment_account_id" class="form-control">
                                    <option value="">{{ __('Not tracked there') }}</option>
                                    @foreach($payment_accounts as $pa)
                                    <option value="{{ $pa->id }}" data-account="{{ $pa->chart_of_account_id }}" @selected(old('payment_account_id') == $pa->id)>{{ $pa->title }} ({{ __('balance') }} {{ format_amount($pa->current_balance) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="form-group col-md-4">
                                <label for="cash_account_id">{{ __('Paid from (ledger account)') }} <span>*</span></label>
                                <select name="cash_account_id" id="cash_account_id" class="form-control" required>
                                    <option value="">{{ __('select') }}</option>
                                    @foreach($cash_accounts as $acc)
                                    <option value="{{ $acc->id }}" @selected(old('cash_account_id') == $acc->id)>{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="note">{{ __('Note') }}</label>
                                <input type="text" class="form-control" name="note" id="note" maxlength="2000" value="{{ old('note') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-success"
                                data-review-url="{{ route($route.'.preview') }}" data-review-form="pay-form"
                                data-confirm-form="pay-form" data-confirm-label="{{ __('Record and post') }}"
                                data-confirm-check="{{ __('I have checked the amount, the account it is paid from and the bills it pays, and confirm this payment.') }}">
                            <i class="fas fa-search-dollar"></i> {{ __('Review the payment') }}</button>
                        <span class="ap-muted ml-2">{{ __('You will see the ledger entry and each bill before and after, then confirm.') }}</span>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>

@include('admin.supplier-bill.partials.review-modal')

@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('pay-form');
    if (!form) { return; }
    var fmt = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });
    var inputs = form.querySelectorAll('.alloc');

    function sum() {
        var s = 0;
        inputs.forEach(function (i) {
            var owed = parseFloat(i.dataset.owed) || 0;
            var v = parseFloat(i.value) || 0;
            i.classList.toggle('is-invalid', v > owed + 0.005);
            s += v;
        });
        s = Math.round(s * 100) / 100;
        document.getElementById('alloc-total').textContent = fmt.format(s);
        document.getElementById('amount').value = s > 0 ? s.toFixed(2) : '';
    }

    inputs.forEach(function (i) { i.addEventListener('input', sum); });
    // Pressing Enter opens the review rather than posting unseen; only the
    // review's confirm button submits the form.
    var reviewed = false;
    form.addEventListener('submit', function (e) {
        if (!reviewed) { e.preventDefault(); $('[data-review-form="pay-form"]').trigger('click'); }
    });
    document.getElementById('review-confirm').addEventListener('click', function () { reviewed = true; }, true);
    document.getElementById('pay-all').addEventListener('click', function () {
        inputs.forEach(function (i) { i.value = i.dataset.owed; }); sum();
    });
    document.getElementById('pay-none').addEventListener('click', function () {
        inputs.forEach(function (i) { i.value = ''; }); sum();
    });
    sum();

    // The method and the cash box each point at their usual ledger account.
    var cash = document.getElementById('cash_account_id');
    function preselect(el) {
        var opt = el.options[el.selectedIndex];
        var acc = opt ? opt.getAttribute('data-account') : '';
        if (acc) { cash.value = acc; }
    }
    var method = document.getElementById('payment_method');
    method.addEventListener('change', function () { preselect(method); });
    var box = document.getElementById('payment_account_id');
    if (box) {
        box.addEventListener('change', function () {
            preselect(box);
            cash.disabled = !!box.value;
        });
        // A disabled field is not sent; the cash box decides the account then.
        form.addEventListener('submit', function () { cash.disabled = false; });
    }
    if (!cash.value) { preselect(method); }
})();
</script>
@endpush
