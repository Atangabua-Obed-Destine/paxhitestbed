{{--
  Read the figures back before anything is recorded.

  Modelled on the fees receipt screen, for the same reason it was built there:
  what follows is not a draft. Saving an income or an expense posts to the
  ledger, moves the Income & Expenditure sheet and, on a new record, credits or
  debits a payment account. It can be corrected, but a correction is itself a
  permanent entry — so the figure is confirmed now rather than reversed later.

  On an edit the stakes are higher still, and the modal says so: changing the
  amount, the category or the date reverses the existing posting and writes a
  new one. The "what changes" list is built from the values the form was
  rendered with, so the Bursar sees what they are actually about to alter
  rather than only what they are about to save.

  Lives inside the <form>, so the confirm button is the form's own submit.
--}}

@php
    // What the record looked like when the form was rendered. Compared in the
    // browser against what is in the fields now.
    $original = $isEdit ? [
        'category' => (string) $row->category_id,
        'title' => (string) $row->title,
        'amount' => (string) $row->amount,
        'date' => \Carbon\Carbon::parse($row->date)->format('Y-m-d'),
        'payment_method' => (string) $row->payment_method,
    ] : null;
@endphp

<div class="modal fade acct-confirm" id="acctConfirm" tabindex="-1"
     aria-labelledby="acctConfirmLabel" aria-hidden="true"
     data-original="{{ $original ? json_encode($original) : '' }}"
     data-currency="{{ $setting->currency_symbol ?? '' }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="acctConfirmLabel">
                    <i class="fas fa-clipboard-check me-1"></i>{{ __('acc_confirm_title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('btn_close') }}"></button>
            </div>

            <div class="modal-body">
                <div class="acct-confirm-note">
                    <p class="mb-0">
                        {{ $isEdit ? __('acc_confirm_warning_edit') : __('acc_confirm_warning_create') }}
                    </p>
                </div>

                <div class="text-center my-3">
                    <div class="text-muted small text-uppercase">{{ __('acc_confirm_amount_label') }}</div>
                    <div class="acct-confirm-amount" id="acct-confirm-amount">0.00</div>
                    <div class="text-muted">{!! $setting->currency_symbol ?? '' !!}</div>
                </div>

                <table class="table table-sm acct-confirm-table mb-0">
                    <tbody>
                        <tr>
                            <th class="fw-normal text-muted w-40">{{ __('field_category') }}</th>
                            <td class="fw-bold" id="acct-confirm-category">&mdash;</td>
                        </tr>
                        <tr>
                            <th class="fw-normal text-muted">{{ __('field_title') }}</th>
                            <td class="fw-bold" id="acct-confirm-title">&mdash;</td>
                        </tr>
                        <tr>
                            <th class="fw-normal text-muted">{{ __('field_date') }}</th>
                            <td class="fw-bold" id="acct-confirm-date">&mdash;</td>
                        </tr>
                        <tr>
                            <th class="fw-normal text-muted">{{ __('field_payment_method') }}</th>
                            <td class="fw-bold" id="acct-confirm-method">&mdash;</td>
                        </tr>
                        <tr>
                            <th class="fw-normal text-muted">{{ __('field_payment_account') }}</th>
                            <td id="acct-confirm-account">&mdash;</td>
                        </tr>
                    </tbody>
                </table>

                {{-- Only on an edit, and only when something actually differs. --}}
                <div id="acct-confirm-changes" class="d-none mt-3">
                    <div class="acct-confirm-changes-head">{{ __('acc_confirm_changes') }}</div>
                    <ul id="acct-confirm-changes-list" class="acct-confirm-changes-list"></ul>
                    <p class="acct-confirm-reposts mb-0 d-none" id="acct-confirm-reposts">
                        {{ __('acc_confirm_reposts') }}
                    </p>
                </div>

                <div id="acct-confirm-nochange" class="d-none mt-3 text-muted small">
                    {{ __('acc_confirm_nochange') }}
                </div>

                {{-- A deliberate act, not a second reflex click on the same spot:
                     the confirm button stays disabled until this is ticked, and it
                     is cleared every time the modal opens. --}}
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="acct-confirm-check">
                    <label class="form-check-label" for="acct-confirm-check">
                        {{ $isEdit ? __('acc_confirm_tick_edit') : __('acc_confirm_tick_create') }}
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-1"></i>{{ __('acc_confirm_back') }}
                </button>
                {{-- Disabled until the tick, so Enter cannot carry the form past
                     the confirmation. --}}
                <button type="submit" class="btn btn-success" id="acct-confirm-submit" disabled>
                    <i class="fas fa-check me-1"></i>{{ $isEdit ? __('acc_confirm_save_edit') : __('acc_confirm_save_create') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var form = document.querySelector('.acct-form form');
    var modalEl = document.getElementById('acctConfirm');
    var reviewBtn = document.getElementById('acct-review');

    if (!form || !modalEl || !reviewBtn) { return; }

    /*
     * The amount field is deliberately NOT .autonumber: the shared initialiser
     * runs AutoNumeric with decimalPlaces: 0, which rounded the field on load
     * and undid the precision the server had just sent. Its own instance here,
     * with two places, grouping for readability, and unformatOnSubmit so what
     * is posted is a plain number the 'numeric' rule accepts.
     */
    var amountField = document.getElementById('amount');
    var autoNumeric = null;

    if (amountField && typeof AutoNumeric !== 'undefined') {
        try {
            autoNumeric = new AutoNumeric(amountField, {
                minimumValue: '0',
                maximumValue: '9999999999999',
                decimalPlaces: 2,
                decimalCharacter: '.',
                digitGroupSeparator: ',',
                unformatOnSubmit: true
            });
        } catch (e) {
            autoNumeric = null;
        }
    }

    function amount() {
        if (autoNumeric) {
            var n = autoNumeric.getNumber();
            return isNaN(n) ? 0 : n;
        }
        // Without AutoNumeric the field is plain text; strip grouping only.
        return parseFloat(String(amountField ? amountField.value : '0').replace(/,/g, '')) || 0;
    }

    var currency = modalEl.getAttribute('data-currency') || '';

    function money(value) {
        return Number(value).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function text(id) {
        var el = document.getElementById(id);
        if (!el) { return ''; }
        if (el.tagName === 'SELECT') {
            return el.selectedIndex >= 0 ? el.options[el.selectedIndex].text.trim() : '';
        }
        return (el.value || '').trim();
    }

    function set(id, value) {
        var el = document.getElementById(id);
        if (el) { el.textContent = value && value.length ? value : '—'; }
    }

    var original = null;

    try {
        original = JSON.parse(modalEl.getAttribute('data-original') || 'null');
    } catch (e) {
        original = null;
    }

    function fill() {
        set('acct-confirm-amount', money(amount()));
        set('acct-confirm-category', text('category'));
        set('acct-confirm-title', text('title'));
        set('acct-confirm-method', text('payment_method'));

        var dateField = document.getElementById('date');
        var when = dateField && dateField.value ? new Date(dateField.value + 'T00:00:00') : null;
        set('acct-confirm-date', when
            ? when.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' })
            : '');

        // On edit the account is a disabled field and cannot be changed here,
        // so it is read either way rather than special-cased.
        set('acct-confirm-account', text('payment_account_id'));

        describeChanges();
    }

    /*
     * What is actually being altered. An edit that changes the amount, the
     * category or the date reverses the existing journal entry and writes a
     * new one, which is worth saying out loud before it happens rather than
     * discovering in the ledger afterwards.
     */
    function describeChanges() {
        var box = document.getElementById('acct-confirm-changes');
        var list = document.getElementById('acct-confirm-changes-list');
        var none = document.getElementById('acct-confirm-nochange');
        var reposts = document.getElementById('acct-confirm-reposts');

        if (!original || !box || !list) { return; }

        var labels = {
            category: {!! json_encode(__('field_category')) !!},
            title: {!! json_encode(__('field_title')) !!},
            amount: {!! json_encode(__('field_amount')) !!},
            date: {!! json_encode(__('field_date')) !!},
            payment_method: {!! json_encode(__('field_payment_method')) !!}
        };

        // The three the observers watch: changing any of them re-posts.
        var ledgerFields = ['amount', 'category', 'date'];

        var now = {
            category: (document.getElementById('category') || {}).value || '',
            title: text('title'),
            amount: String(amount()),
            date: (document.getElementById('date') || {}).value || '',
            payment_method: (document.getElementById('payment_method') || {}).value || ''
        };

        var shown = {
            category: text('category'),
            title: text('title'),
            amount: money(amount()),
            date: text('date'),
            payment_method: text('payment_method')
        };

        list.innerHTML = '';

        var changed = [];

        Object.keys(labels).forEach(function (field) {
            var before = String(original[field] === null ? '' : original[field]);
            var after = String(now[field]);

            // Amounts compare as numbers: "1500" and "1500.00" are the same money.
            if (field === 'amount') {
                if (Math.abs(parseFloat(before || 0) - parseFloat(after || 0)) < 0.005) { return; }
            } else if (before === after) {
                return;
            }

            changed.push(field);

            var li = document.createElement('li');
            var was = field === 'amount' ? money(parseFloat(before || 0)) : before;

            li.innerHTML = labels[field] + ': <span class="was"></span> &rarr; <b></b>';
            li.querySelector('.was').textContent = was.length ? was : '—';
            li.querySelector('b').textContent = shown[field];
            list.appendChild(li);
        });

        if (changed.length) {
            box.classList.remove('d-none');
            none.classList.add('d-none');

            var touchesLedger = changed.some(function (f) { return ledgerFields.indexOf(f) !== -1; });
            reposts.classList.toggle('d-none', !touchesLedger);
        } else {
            box.classList.add('d-none');
            none.classList.remove('d-none');
        }
    }

    var modal = new bootstrap.Modal(modalEl);
    var tick = document.getElementById('acct-confirm-check');
    var submit = document.getElementById('acct-confirm-submit');

    reviewBtn.addEventListener('click', function () {
        // The form carries novalidate, so the browser's own messages only
        // appear when they are asked for. Better here than a server round trip
        // that loses the file input.
        if (!form.reportValidity()) { return; }

        fill();
        modal.show();
    });

    modalEl.addEventListener('show.bs.modal', function () {
        // Ticked afresh every time: a confirmation carried over from the
        // previous look is not a confirmation.
        if (tick) { tick.checked = false; }
        if (submit) { submit.disabled = true; }
    });

    if (tick && submit) {
        tick.addEventListener('change', function () {
            submit.disabled = !tick.checked;
        });
    }

    // One submission. A slow save used to invite a second click, and the
    // second one posted the figure again.
    form.addEventListener('submit', function () {
        if (submit) {
            submit.disabled = true;
            submit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + {!! json_encode(__('acc_confirm_saving')) !!};
        }
    });
})();
</script>
@endpush
