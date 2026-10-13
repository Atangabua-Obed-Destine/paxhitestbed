<!-- Pay Installment Modal -->
<div id="payModal-{{ $installment->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form class="needs-validation" novalidate action="{{ route($route.'.process-payment') }}" method="post" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="payment_plan_id" value="{{ $row->id }}">
                <input type="hidden" name="installment_id" value="{{ $installment->id }}">

                <div class="modal-header">
                    <h5 class="modal-title">{{ __('pay_installment') }} #{{ $installment->installment_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Installment Details -->
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>{{ __('field_installment_amount') }}:</strong></p>
                                <h5>{{ number_format($installment->amount, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                            @if($installment->late_fee > 0)
                            <div class="col-md-4">
                                <p><strong>{{ __('field_late_fee') }}:</strong></p>
                                <h5 class="text-danger">{{ number_format($installment->late_fee, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                            @endif
                            <div class="col-md-4">
                                <p><strong>{{ __('field_total_due') }}:</strong></p>
                                <h5 class="text-primary">{{ number_format($installment->amount + ($installment->late_fee ?? 0), 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                        </div>
                        @if($installment->paid_amount > 0)
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <p><strong>{{ __('field_already_paid') }}:</strong></p>
                                <h5 class="text-success">{{ number_format($installment->paid_amount, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                            <div class="col-md-6">
                                <p><strong>{{ __('field_remaining_balance') }}:</strong></p>
                                <h5 class="text-warning">{{ number_format($installment->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Payment Form -->
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="payment_date-{{ $installment->id }}" class="form-label">{{ __('field_payment_date') }} <span>*</span></label>
                            <input type="date" class="form-control" name="payment_date" id="payment_date-{{ $installment->id }}" value="{{ date('Y-m-d') }}" required>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_payment_date') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="amount-{{ $installment->id }}" class="form-label">{{ __('field_amount_to_pay') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                            <input type="number" class="form-control bg-warning" name="amount" id="amount-{{ $installment->id }}" 
                                   value="{{ number_format($installment->remaining_balance, 2, '.', '') }}" 
                                   step="0.01" min="0.01" max="{{ $installment->remaining_balance }}" required>
                            <small class="form-text text-muted">{{ __('max') }}: {{ number_format($installment->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</small>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_amount') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="payment_method-{{ $installment->id }}" class="form-label">{{ __('field_payment_method') }} <span>*</span></label>
                            <select class="form-control" name="payment_method" id="payment_method-{{ $installment->id }}" required>
                                <option value="">{{ __('select') }}</option>
                                <option value="1">{{ __('payment_method_cash') }}</option>
                                <option value="2">{{ __('payment_method_cheque') }}</option>
                                <option value="3">{{ __('payment_method_bank') }}</option>
                                <option value="6">{{ __('payment_method_mobile_money') }}</option>
                                <option value="7">{{ __('payment_method_other') }}</option>
                            </select>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_payment_method') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="payment_account_id-{{ $installment->id }}" class="form-label">{{ __('field_payment_account') }}</label>
                            <select class="form-control" name="payment_account_id" id="payment_account_id-{{ $installment->id }}">
                                <option value="">{{ __('select') }}</option>
                                @foreach($payment_accounts ?? [] as $account)
                                    <option value="{{ $account->id }}">{{ $account->title }} ({{ number_format($account->current_balance, 2) }})</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                {{ __('Which account received the money. Left blank, the payment shows under Unlinked Transactions until an account is attached.') }}
                            </small>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="reference_no" class="form-label">{{ __('field_reference_number') }}</label>
                            <input type="text" class="form-control" name="reference_no" id="reference_no" value="{{ old('reference_no') }}">
                        </div>

                        <div class="form-group col-md-12">
                            <label for="receipt" class="form-label">{{ __('field_receipt') }}</label>
                            <input type="file" class="form-control" name="receipt" id="receipt" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">{{ __('allowed_formats') }}: PDF, JPG, PNG ({{ __('max') }} 2MB)</small>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="note" class="form-label">{{ __('field_note') }}</label>
                            <textarea class="form-control" name="note" id="note" rows="2">{{ old('note') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Read the figures back before any money is recorded.

                     Processing an instalment posts to the ledger, credits the
                     payment account and moves the plan on. It can be reversed,
                     but a reversal is itself a permanent entry, so an amount
                     typed wrongly becomes a correction on the record rather
                     than a quiet edit. --}}
                <div class="modal-body d-none" id="plan-confirm-{{ $installment->id }}">
                    <div class="alert alert-warning">
                        <h6 class="alert-heading mb-1">
                            <i class="fas fa-hand-holding-usd"></i> {{ __('Check this against the money you received') }}
                        </h6>
                        <p class="mb-0">{{ __('This records the amount below as received from the student. Reversing it later leaves a correction on the record, so confirm the figure now.') }}</p>
                    </div>

                    <div class="text-center my-3">
                        <div class="text-muted small text-uppercase">{{ __('Amount to record as received') }}</div>
                        <div style="font-size:2.4rem;line-height:1.1;font-weight:700;" id="plan-confirm-amount-{{ $installment->id }}">0.00</div>
                        <div class="text-muted">{!! $setting->currency_symbol !!}</div>
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="fw-normal text-muted w-50">{{ __('field_name') }}</th>
                                <td class="fw-bold">{{ optional(optional($row->fee)->studentEnroll)->student->first_name ?? '' }} {{ optional(optional($row->fee)->studentEnroll)->student->last_name ?? '' }}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('pay_installment') }}</th>
                                <td class="fw-bold">#{{ $installment->installment_number }}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_total_due') }}</th>
                                <td>{{ number_format($installment->amount + ($installment->late_fee ?? 0), 2) }} {!! $setting->currency_symbol !!}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_payment_method') }}</th>
                                <td class="fw-bold" id="plan-confirm-method-{{ $installment->id }}">&mdash;</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_payment_account') }}</th>
                                <td id="plan-confirm-account-{{ $installment->id }}">&mdash;</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_remaining_balance') }} {{ __('after this') }}</th>
                                <td class="fw-bold" id="plan-confirm-balance-{{ $installment->id }}">&mdash;</td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="plan-confirm-notes-{{ $installment->id }}"></div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="plan-confirm-check-{{ $installment->id }}">
                        <label class="form-check-label" for="plan-confirm-check-{{ $installment->id }}">
                            {{ __('I have counted this and it matches the amount the student paid.') }}
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="plan-close-{{ $installment->id }}">
                        <i class="fas fa-times"></i> {{ __('btn_close') }}
                    </button>
                    <button type="button" class="btn btn-secondary d-none" id="plan-back-{{ $installment->id }}">
                        <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                    </button>
                    {{-- type="button": nothing is recorded until the figures have
                         been read back. --}}
                    <button type="button" class="btn btn-success" id="plan-review-{{ $installment->id }}">
                        <i class="fas fa-money-check"></i> {{ __('btn_process_payment') }}
                    </button>
                    {{-- Disabled while hidden, so Enter in a field cannot submit
                         the form past the confirmation. --}}
                    <button type="submit" class="btn btn-success d-none" id="plan-confirm-btn-{{ $installment->id }}" disabled>
                        <i class="fas fa-check"></i> {{ __('Yes, record this payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const id          = '{{ $installment->id }}';
    const reviewBtn   = document.getElementById('plan-review-' + id);
    const confirmBtn  = document.getElementById('plan-confirm-btn-' + id);
    const backBtn     = document.getElementById('plan-back-' + id);
    const closeBtn    = document.getElementById('plan-close-' + id);
    const step        = document.getElementById('plan-confirm-' + id);
    const tick        = document.getElementById('plan-confirm-check-' + id);
    const amountField = document.getElementById('amount-' + id);

    if (!reviewBtn || !confirmBtn || !step) { return; }

    const form = reviewBtn.closest('form');
    const formBody = form ? form.querySelector('.modal-body') : null;
    const maxAmount = {{ round(max(0, $installment->remaining_balance ?? 0), 2) }};

    const money = (value) => (parseFloat(value) || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });

    const escapeHtml = (value) => {
        const holder = document.createElement('div');
        holder.textContent = value === null || value === undefined ? '' : value;
        return holder.innerHTML;
    };

    const selectedText = (fieldId) => {
        const field = document.getElementById(fieldId);
        if (!field || field.selectedIndex < 0) { return ''; }
        const option = field.options[field.selectedIndex];
        return option && option.value !== '' ? option.text.trim() : '';
    };

    const fill = () => {
        const amount = parseFloat(amountField ? amountField.value : 0) || 0;

        document.getElementById('plan-confirm-amount-' + id).textContent = money(amount);

        const method = selectedText('payment_method-' + id);
        document.getElementById('plan-confirm-method-' + id).textContent =
            method || {!! json_encode(__('select')) !!};

        const account = selectedText('payment_account_id-' + id);
        document.getElementById('plan-confirm-account-' + id).innerHTML = account
            ? escapeHtml(account)
            : '<span class="text-muted">' + {!! json_encode(__('Not selected - the payment shows under Unlinked Transactions')) !!} + '</span>';

        document.getElementById('plan-confirm-balance-' + id).textContent =
            money(Math.max(0, maxAmount - amount)) + ' {!! $setting->currency_symbol !!}';

        const notes = [];

        if (amount > 0 && amount < maxAmount - 0.005) {
            notes.push({
                level: 'info',
                text: {!! json_encode(__('This is a part payment. The rest of the instalment stays outstanding.')) !!}
            });
        }

        if (amount <= 0) {
            notes.push({
                level: 'danger',
                text: {!! json_encode(__('The amount is zero. Nothing would be recorded as received.')) !!}
            });
        }

        document.getElementById('plan-confirm-notes-' + id).innerHTML = notes.map((note) =>
            '<div class="alert alert-' + note.level + ' py-2 mb-0 mt-2 small">' + escapeHtml(note.text) + '</div>'
        ).join('');
    };

    const showConfirm = () => {
        fill();
        if (formBody) { formBody.classList.add('d-none'); }
        step.classList.remove('d-none');
        reviewBtn.classList.add('d-none');
        if (closeBtn) { closeBtn.classList.add('d-none'); }
        if (backBtn) { backBtn.classList.remove('d-none'); }
        confirmBtn.classList.remove('d-none');
        // Ticked afresh every time: a confirmation carried over from the
        // previous look is not a confirmation.
        if (tick) { tick.checked = false; }
        confirmBtn.disabled = true;
    };

    const showForm = () => {
        if (formBody) { formBody.classList.remove('d-none'); }
        step.classList.add('d-none');
        // Going back withdraws the confirmation, so the tick goes with it.
        if (tick) { tick.checked = false; }
        reviewBtn.classList.remove('d-none');
        if (closeBtn) { closeBtn.classList.remove('d-none'); }
        if (backBtn) { backBtn.classList.add('d-none'); }
        confirmBtn.classList.add('d-none');
        confirmBtn.disabled = true;
    };

    reviewBtn.addEventListener('click', function () {
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            form.reportValidity();
            return;
        }
        showConfirm();
    });

    if (backBtn) { backBtn.addEventListener('click', showForm); }

    if (tick) {
        tick.addEventListener('change', function () { confirmBtn.disabled = !this.checked; });
    }

    form.addEventListener('submit', function () {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + {!! json_encode(__('Recording...')) !!};
    });

    const modal = document.getElementById('payModal-' + id);
    if (modal) { modal.addEventListener('hidden.bs.modal', showForm); }
});
</script>
