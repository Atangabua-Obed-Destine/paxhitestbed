{{-- Pay an instalment.

     One modal, two homes: the payment plan's own page, and the fees report,
     where a fee on a plan is paid without leaving the list. It therefore
     takes what it needs by name — $plan, $installment — and never reads the
     surrounding page's $row, which is a plan on one screen and a fee on the
     other. Every id carries the "pp-" prefix for the same reason: on the
     report this sits beside the ordinary fee modals, whose ids are built from
     fee ids that can equal an instalment's.

     $returnTo  'plan' (default) goes back to the plan; 'report' goes to the
                fee's receipt, as a payment on an ordinary fee does. --}}
@php
    $plan = $plan ?? $row;
    $returnTo = $returnTo ?? 'plan';
    $ppId = $installment->id;
    $ppCurrency = optional($setting ?? null)->currency_symbol ?? '';
    $ppFee = $plan->fee;
    $ppEnroll = optional($ppFee)->studentEnroll;
    $ppStudent = optional($ppEnroll)->student ?? $plan->student;
    $ppSchedule = $plan->installments->sortBy('installment_number');
    $ppBalance = round(max(0, (float) $installment->remaining_balance), 2);
    $ppPlanBalance = round((float) $ppSchedule->sum(fn ($i) => (float) $i->remaining_balance), 2);
    $ppAccounts = $payment_accounts ?? \App\Models\PaymentAccount::where('status', 1)->orderBy('title')->get();
@endphp
<div id="planPayModal-{{ $ppId }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="planPayLabel-{{ $ppId }}" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content text-start">
            <form class="needs-validation" novalidate action="{{ route('admin.payment-plan.process-payment') }}" method="post" enctype="multipart/form-data" id="pp-form-{{ $ppId }}">
                @csrf
                <input type="hidden" name="payment_plan_id" value="{{ $plan->id }}">
                <input type="hidden" name="installment_id" value="{{ $ppId }}">
                <input type="hidden" name="return_to" value="{{ $returnTo }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="planPayLabel-{{ $ppId }}">
                        {{ __('pay_installment') }} #{{ $installment->installment_number }}
                        <small class="text-muted">{{ __('of :count', ['count' => $ppSchedule->count()]) }}</small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" id="pp-body-{{ $ppId }}">
                    {{-- Who and what: the same two cards the ordinary fee modal
                         opens with, so a bursar reads both the same way. --}}
                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user"></i> {{ __('student_information') }}</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_matricule') }}:</strong>
                                        <span class="text-primary">#{{ $ppEnroll->matricule ?? ($ppStudent->student_id ?? '') }}</span>
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_name') }}:</strong> {{ $ppStudent->first_name ?? '' }} {{ $ppStudent->last_name ?? '' }}</p>
                                    <p class="mb-0"><strong>{{ __('field_program') }}:</strong> {{ optional(optional($ppEnroll)->program)->title ?? '' }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_fees_type') }}:</strong> {{ optional(optional($ppFee)->category)->title ?? '' }}</p>
                                    <p class="mb-1"><strong>{{ __('field_session') }}:</strong> {{ optional(optional($ppEnroll)->session)->title ?? '' }}</p>
                                    <p class="mb-0"><strong>{{ __('field_semester') }}:</strong> {{ optional(optional($ppEnroll)->semester)->title ?? '' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($ppFee)
                    <div class="card mb-3 border-info">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-calculator"></i> {{ __('fee_summary') }}</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-4 text-center border-end">
                                    <small class="text-muted">{{ __('field_net_amount') }}</small>
                                    <h5 class="mb-0 text-primary"><strong>{{ number_format($ppFee->total_amount ?? 0, 2) }}</strong></h5>
                                </div>
                                <div class="col-md-4 text-center border-end">
                                    <small class="text-muted">{{ __('field_already_paid') }}</small>
                                    <h5 class="mb-0 text-success">{{ number_format($ppFee->paid_amount ?? 0, 2) }}</h5>
                                </div>
                                <div class="col-md-4 text-center">
                                    <small class="text-muted">{{ __('field_remaining_balance') }}</small>
                                    <h5 class="mb-0 text-warning"><strong>{{ number_format(max(0, $ppFee->remaining_balance ?? 0), 2) }}</strong></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- The plan itself. A bursar paying from the report has
                         not seen the plan's page, so the schedule comes to
                         them: which instalment this is, what came before it
                         and what is still to come. --}}
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-calendar-alt"></i> {{ __('The payment plan') }}</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0" id="pp-schedule-{{ $ppId }}">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_due_date') }}</th>
                                        <th class="text-end">{{ __('field_amount') }}</th>
                                        <th class="text-end">{{ __('field_paid_amount') }}</th>
                                        <th class="text-end">{{ __('field_remaining_balance') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ppSchedule as $ppLine)
                                    <tr class="{{ $ppLine->id === $ppId ? 'table-warning' : '' }}" data-installment="{{ $ppLine->id }}">
                                        <td>
                                            {{ $ppLine->installment_number }}
                                            @if($ppLine->id === $ppId)
                                                <span class="badge badge-info bg-info">{{ __('this instalment') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($ppLine->due_date)->format('d M Y') }}</td>
                                        <td class="text-end">{{ number_format((float) $ppLine->amount + (float) ($ppLine->late_fee ?? 0), 2) }}</td>
                                        <td class="text-end">{{ number_format((float) ($ppLine->paid_amount ?? 0), 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $ppLine->remaining_balance, 2) }}</td>
                                        <td>{!! $ppLine->status_badge !!}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">{{ __('Still to pay on the plan') }}</th>
                                        <th class="text-end">{{ number_format($ppPlanBalance, 2) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- More than this instalment needs.

                         A student pays toward what they owe, not toward one
                         line of a schedule, so this is the ordinary case. The
                         extra is carried onto the plan's next instalments and
                         then the student's other instalment fees — shown, and
                         changeable, before anything is recorded. --}}
                    <div class="alert alert-warning d-none" id="pp-over-{{ $ppId }}">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> {{ __('More than this instalment\'s balance') }}</h6>
                        <p class="mb-1">{{ __('This is more than this instalment needs. The extra is carried onto the plan\'s next instalments, then the student\'s other instalment fees, as shown below.') }}</p>
                        <hr class="my-2">
                        <div class="mb-2">
                            <strong>{{ __('Amount over this instalment') }}:</strong>
                            <span id="pp-over-amount-{{ $ppId }}" class="text-danger fw-bold">0.00</span> {!! $ppCurrency !!}
                        </div>

                        <div id="pp-split-loading-{{ $ppId }}" class="text-muted small d-none">
                            <i class="fas fa-spinner fa-spin"></i> {{ __('Working out how this is spread...') }}
                        </div>

                        <table class="table table-sm mb-2 bg-white d-none" id="pp-split-table-{{ $ppId }}">
                            <thead>
                                <tr>
                                    <th>{{ __('Goes to') }}</th>
                                    <th>{{ __('field_due_date') }}</th>
                                    <th class="text-end">{{ __('field_remaining_balance') }}</th>
                                    <th class="text-end" style="width:170px">{{ __('field_amount_to_pay') }}</th>
                                </tr>
                            </thead>
                            <tbody id="pp-split-rows-{{ $ppId }}"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">{{ __('field_total') }}</th>
                                    <th class="text-end" id="pp-split-total-{{ $ppId }}">0.00</th>
                                </tr>
                            </tfoot>
                        </table>

                        <div id="pp-split-mismatch-{{ $ppId }}" class="alert alert-danger py-2 small mb-2 d-none"></div>

                        {{-- Money beyond everything the student owes. Not
                             taken here: it has to land on something owed. --}}
                        <div class="alert alert-danger mb-0 d-none" id="pp-leftover-{{ $ppId }}">
                            <strong>
                                <i class="fas fa-exclamation-circle"></i>
                                <span id="pp-leftover-amount-{{ $ppId }}">0.00</span> {!! $ppCurrency !!}
                                <span id="pp-leftover-text-{{ $ppId }}"></span>
                            </strong>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pp-date-{{ $ppId }}" class="form-label">{{ __('field_payment_date') }} <span>*</span></label>
                            <input type="date" class="form-control" name="payment_date" id="pp-date-{{ $ppId }}" value="{{ date('Y-m-d') }}" required>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_payment_date') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="pp-amount-{{ $ppId }}" class="form-label">{{ __('field_amount_to_pay') }} ({!! $ppCurrency !!}) <span>*</span></label>
                            {{-- No max: more than this instalment is carried
                                 forward, not refused. --}}
                            <input type="number" class="form-control" name="amount" id="pp-amount-{{ $ppId }}"
                                   value="{{ number_format($ppBalance, 2, '.', '') }}"
                                   step="0.01" min="0.01" required>
                            <small class="form-text text-muted">
                                {{ __('This instalment') }}: {{ number_format($ppBalance, 2) }} {!! $ppCurrency !!}
                                &middot; {{ __('whole plan') }}: {{ number_format($ppPlanBalance, 2) }} {!! $ppCurrency !!}
                            </small>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_amount') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="pp-method-{{ $ppId }}" class="form-label">{{ __('field_payment_method') }} <span>*</span></label>
                            <select class="form-control" name="payment_method" id="pp-method-{{ $ppId }}" required>
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
                            <label for="pp-account-{{ $ppId }}" class="form-label">{{ __('field_payment_account') }}</label>
                            <select class="form-control" name="payment_account_id" id="pp-account-{{ $ppId }}">
                                <option value="">{{ __('select') }}</option>
                                @foreach($ppAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->title }} ({{ number_format($account->current_balance, 2) }})</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                {{ __('Which account received the money. Left blank, the payment shows under Unlinked Transactions until an account is attached.') }}
                            </small>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="pp-reference-{{ $ppId }}" class="form-label">{{ __('field_reference_number') }}</label>
                            <input type="text" class="form-control" name="reference_no" id="pp-reference-{{ $ppId }}" value="">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="pp-receipt-{{ $ppId }}" class="form-label">{{ __('field_receipt') }}</label>
                            <input type="file" class="form-control" name="receipt" id="pp-receipt-{{ $ppId }}" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">{{ __('allowed_formats') }}: PDF, JPG, PNG, 2MB</small>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="pp-note-{{ $ppId }}" class="form-label">{{ __('field_note') }}</label>
                            <textarea class="form-control" name="note" id="pp-note-{{ $ppId }}" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Read the figures back before any money is recorded.

                     Processing an instalment posts to the ledger, credits the
                     payment account and moves the plan on. It can be reversed,
                     but a reversal is itself a permanent entry, so an amount
                     typed wrongly becomes a correction on the record rather
                     than a quiet edit. --}}
                <div class="modal-body d-none" id="pp-confirm-{{ $ppId }}">
                    <div class="alert alert-warning">
                        <h6 class="alert-heading mb-1">
                            <i class="fas fa-hand-holding-usd"></i> {{ __('Check this against the money you received') }}
                        </h6>
                        <p class="mb-0">{{ __('This records the amount below as received from the student. Reversing it later leaves a correction on the record, so confirm the figure now.') }}</p>
                    </div>

                    <div class="text-center my-3">
                        <div class="text-muted small text-uppercase">{{ __('Amount to record as received') }}</div>
                        <div style="font-size:2.4rem;line-height:1.1;font-weight:700;" id="pp-confirm-amount-{{ $ppId }}">0.00</div>
                        <div class="text-muted">{!! $ppCurrency !!}</div>
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="fw-normal text-muted w-50">{{ __('field_name') }}</th>
                                <td class="fw-bold">{{ $ppStudent->first_name ?? '' }} {{ $ppStudent->last_name ?? '' }}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('pay_installment') }}</th>
                                <td class="fw-bold">#{{ $installment->installment_number }} {{ __('of :count', ['count' => $ppSchedule->count()]) }}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_payment_method') }}</th>
                                <td class="fw-bold" id="pp-confirm-method-{{ $ppId }}">&mdash;</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_payment_account') }}</th>
                                <td id="pp-confirm-account-{{ $ppId }}">&mdash;</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('Still to pay on the plan after this') }}</th>
                                <td class="fw-bold" id="pp-confirm-balance-{{ $ppId }}">&mdash;</td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="pp-confirm-notes-{{ $ppId }}"></div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="pp-confirm-check-{{ $ppId }}">
                        <label class="form-check-label" for="pp-confirm-check-{{ $ppId }}">
                            {{ __('I have counted this and it matches the amount the student paid.') }}
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="pp-close-{{ $ppId }}">
                        <i class="fas fa-times"></i> {{ __('btn_close') }}
                    </button>
                    <button type="button" class="btn btn-secondary d-none" id="pp-back-{{ $ppId }}">
                        <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                    </button>
                    {{-- type="button": nothing is recorded until the figures have
                         been read back. --}}
                    <button type="button" class="btn btn-success" id="pp-review-{{ $ppId }}">
                        <i class="fas fa-money-check"></i> {{ __('btn_process_payment') }}
                    </button>
                    {{-- Disabled while hidden, so Enter in a field cannot submit
                         the form past the confirmation. --}}
                    <button type="submit" class="btn btn-success d-none" id="pp-confirm-btn-{{ $ppId }}" disabled>
                        <i class="fas fa-check"></i> {{ __('Yes, record this payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const id = '{{ $ppId }}';
    const el = function (name) { return document.getElementById('pp-' + name + '-' + id); };

    const form        = el('form');
    const formBody    = el('body');
    const reviewBtn   = el('review');
    const confirmBtn  = el('confirm-btn');
    const backBtn     = el('back');
    const closeBtn    = el('close');
    const step        = el('confirm');
    const tick        = el('confirm-check');
    const amountField = el('amount');

    if (!form || !reviewBtn || !confirmBtn || !step || !amountField) { return; }

    const maxAmount   = {{ $ppBalance }};
    const planBalance = {{ $ppPlanBalance }};
    const CURRENCY    = {!! json_encode(strip_tags((string) $ppCurrency)) !!};
    const SPLIT_URL   = {!! json_encode(route('admin.payment-plan.split', $ppId)) !!};
    const THIS_ONE    = {!! json_encode(__('this instalment')) !!};
    const SPLIT_TOO_MUCH = {!! json_encode(__('The split comes to more than the amount received.')) !!};
    const SPLIT_FAILED   = {!! json_encode(__('Could not work out how this payment is spread. Close and try again.')) !!};
    const SPLIT_WAIT     = {!! json_encode(__('Wait for the split to be worked out, then check it.')) !!};
    const BEYOND_ALL     = {!! json_encode(__('is more than this student owes on the plan and their other instalments. Record :owed or less.')) !!};
    const NOT_PLACED     = {!! json_encode(__('of this payment is not on any line. Put it on a line that still has a balance.')) !!};

    const overBox       = el('over');
    const overAmount    = el('over-amount');
    const splitTable    = el('split-table');
    const splitRows     = el('split-rows');
    const splitTotal    = el('split-total');
    const splitLoading  = el('split-loading');
    const splitMismatch = el('split-mismatch');
    const leftoverBox   = el('leftover');
    const leftoverSpan  = el('leftover-amount');
    const leftoverText  = el('leftover-text');

    // What the server last said this student owes across every line offered.
    let owedTotal = planBalance;
    let splitTimer = null;
    let splitPending = false;

    const money = function (value) {
        return (parseFloat(value) || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    };

    const escapeHtml = function (value) {
        const holder = document.createElement('div');
        holder.textContent = value === null || value === undefined ? '' : value;
        return holder.innerHTML;
    };

    const selectedText = function (field) {
        if (!field || field.selectedIndex < 0) { return ''; }
        const option = field.options[field.selectedIndex];
        return option && option.value !== '' ? option.text.trim() : '';
    };

    const entered = function () { return Math.round((parseFloat(amountField.value) || 0) * 100) / 100; };
    const carrying = function () { return entered() - maxAmount > 0.005; };

    // --- Spreading the payment ------------------------------------------------
    //
    // How the money divides is NOT worked out here. The browser asks the
    // server, which runs the one class that knows the rule, so what the bursar
    // is shown is what the money will actually move against.

    function allocated() {
        let sum = 0;
        splitRows.querySelectorAll('.pp-split-amount').forEach(function (input) {
            sum += parseFloat(input.value) || 0;
        });
        return Math.round(sum * 100) / 100;
    }

    function unplaced() {
        return Math.round((entered() - allocated()) * 100) / 100;
    }

    function refreshSplitTotals() {
        const total = allocated();
        const left = unplaced();

        splitTotal.textContent = money(total);

        if (total - entered() > 0.005) {
            splitMismatch.classList.remove('d-none');
            splitMismatch.textContent = SPLIT_TOO_MUCH + ' (' + money(total) + ' / ' + money(entered()) + ')';
        } else {
            splitMismatch.classList.add('d-none');
        }

        if (left > 0.005) {
            leftoverBox.classList.remove('d-none');
            leftoverSpan.textContent = money(left);
            leftoverText.textContent = entered() - owedTotal > 0.005
                ? BEYOND_ALL.replace(':owed', money(owedTotal) + ' ' + CURRENCY)
                : NOT_PLACED;
        } else {
            leftoverBox.classList.add('d-none');
        }
    }

    function renderSplit(data) {
        const legs = data.legs || [];
        owedTotal = parseFloat(data.owed) || owedTotal;
        splitRows.innerHTML = '';

        legs.forEach(function (leg, index) {
            const tr = document.createElement('tr');
            tr.setAttribute('data-label', leg.label || '');
            tr.innerHTML =
                '<td>' + escapeHtml(leg.label) + (leg.is_origin
                    ? ' <span class="badge badge-info bg-info">' + escapeHtml(THIS_ONE) + '</span>'
                    : '') + '</td>'
                + '<td>' + (leg.due_date ? escapeHtml(leg.due_date) : '&mdash;') + '</td>'
                + '<td class="text-end">' + money(leg.outstanding) + '</td>'
                + '<td class="text-end">'
                + '<input type="hidden" name="allocation[' + index + '][type]" value="' + escapeHtml(leg.type) + '">'
                + '<input type="hidden" name="allocation[' + index + '][id]" value="' + parseInt(leg.id, 10) + '">'
                + '<input type="number" step="0.01" min="0" max="' + Number(leg.outstanding) + '"'
                + ' class="form-control form-control-sm text-end pp-split-amount"'
                + ' name="allocation[' + index + '][applied]" value="' + Number(leg.applied).toFixed(2) + '">'
                + '</td>';
            splitRows.appendChild(tr);
        });

        splitRows.querySelectorAll('.pp-split-amount').forEach(function (input) {
            input.addEventListener('input', refreshSplitTotals);
        });

        splitTable.classList.toggle('d-none', legs.length < 1);
        refreshSplitTotals();
    }

    function askForSplit() {
        const asked = entered();
        splitPending = true;

        clearTimeout(splitTimer);
        splitTimer = setTimeout(function () {
            splitLoading.classList.remove('d-none');

            fetch(SPLIT_URL + '?amount=' + asked,
                  { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (response) {
                    if (!response.ok) { throw new Error('split'); }
                    return response.json();
                })
                .then(function (data) {
                    splitLoading.classList.add('d-none');
                    // The bursar may have typed on while this was in flight.
                    if (Math.abs(entered() - asked) > 0.005) { return; }
                    splitPending = false;
                    renderSplit(data);
                })
                .catch(function () {
                    splitLoading.classList.add('d-none');
                    splitMismatch.classList.remove('d-none');
                    splitMismatch.textContent = SPLIT_FAILED;
                });
        }, 300);
    }

    function clearSplit() {
        clearTimeout(splitTimer);
        splitPending = false;
        splitRows.innerHTML = '';
        splitTable.classList.add('d-none');
        splitMismatch.classList.add('d-none');
        leftoverBox.classList.add('d-none');
    }

    function onAmount() {
        if (carrying()) {
            overBox.classList.remove('d-none');
            overAmount.textContent = money(entered() - maxAmount);
            askForSplit();
        } else {
            // Within this instalment's balance: nothing to spread, and no
            // allocation is sent — the server puts it all on this instalment.
            overBox.classList.add('d-none');
            overAmount.textContent = '0.00';
            clearSplit();
        }
    }

    amountField.addEventListener('input', onAmount);

    // --- The confirmation step ------------------------------------------------

    function splitLegs() {
        const legs = [];
        splitRows.querySelectorAll('tr').forEach(function (tr) {
            const input = tr.querySelector('.pp-split-amount');
            const typeField = tr.querySelector('input[name$="[type]"]');
            const amount = input ? (parseFloat(input.value) || 0) : 0;
            if (amount > 0.005) {
                legs.push({
                    label: tr.getAttribute('data-label') || '',
                    type: typeField ? typeField.value : '',
                    amount: amount
                });
            }
        });
        return legs;
    }

    function fill() {
        const amount = entered();
        const legs = carrying() ? splitLegs() : [];

        el('confirm-amount').textContent = money(amount);
        el('confirm-method').textContent = selectedText(el('method')) || {!! json_encode(__('select')) !!};

        const account = selectedText(el('account'));
        el('confirm-account').innerHTML = account
            ? escapeHtml(account)
            : '<span class="text-muted">' + {!! json_encode(__('Not selected - the payment shows under Unlinked Transactions')) !!} + '</span>';

        // Only what lands on the plan's own instalments reduces the plan.
        let toPlan = amount;
        if (legs.length) {
            toPlan = legs.filter(function (leg) { return leg.type === 'installment'; })
                .reduce(function (sum, leg) { return sum + leg.amount; }, 0);
        }
        el('confirm-balance').textContent = money(Math.max(0, planBalance - toPlan)) + ' ' + CURRENCY;

        const notes = [];

        if (legs.length > 1) {
            notes.push({
                level: 'info',
                html: '<strong>' + {!! json_encode(__('This payment settles')) !!} + ':</strong><br>'
                    + legs.map(function (leg) {
                        return escapeHtml(leg.label) + ' &mdash; ' + money(leg.amount) + ' ' + escapeHtml(CURRENCY);
                    }).join('<br>')
            });
        } else if (amount > 0 && amount < maxAmount - 0.005) {
            notes.push({
                level: 'info',
                html: escapeHtml({!! json_encode(__('This is a part payment. The rest of the instalment stays outstanding.')) !!})
            });
        }

        if (amount <= 0) {
            notes.push({
                level: 'danger',
                html: escapeHtml({!! json_encode(__('The amount is zero. Nothing would be recorded as received.')) !!})
            });
        }

        el('confirm-notes').innerHTML = notes.map(function (note) {
            return '<div class="alert alert-' + note.level + ' py-2 mb-0 mt-2 small">' + note.html + '</div>';
        }).join('');
    }

    function showConfirm() {
        fill();
        formBody.classList.add('d-none');
        step.classList.remove('d-none');
        reviewBtn.classList.add('d-none');
        if (closeBtn) { closeBtn.classList.add('d-none'); }
        if (backBtn) { backBtn.classList.remove('d-none'); }
        confirmBtn.classList.remove('d-none');
        // Ticked afresh every time: a confirmation carried over from the
        // previous look is not a confirmation.
        if (tick) { tick.checked = false; }
        confirmBtn.disabled = true;
    }

    function showForm() {
        formBody.classList.remove('d-none');
        step.classList.add('d-none');
        // Going back withdraws the confirmation, so the tick goes with it.
        if (tick) { tick.checked = false; }
        reviewBtn.classList.remove('d-none');
        if (closeBtn) { closeBtn.classList.remove('d-none'); }
        if (backBtn) { backBtn.classList.add('d-none'); }
        confirmBtn.classList.add('d-none');
        confirmBtn.disabled = true;
    }

    // Whether the split on screen may be sent as it stands.
    function splitProblem() {
        if (!carrying()) { return null; }
        if (splitPending || !splitRows.children.length) { return SPLIT_WAIT; }
        if (allocated() - entered() > 0.005) { return SPLIT_TOO_MUCH; }
        if (unplaced() > 0.005) { return leftoverSpan.textContent + ' ' + CURRENCY + ' ' + leftoverText.textContent; }
        return null;
    }

    reviewBtn.addEventListener('click', function () {
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            form.reportValidity();
            return;
        }

        const problem = splitProblem();
        if (problem) {
            splitMismatch.classList.remove('d-none');
            splitMismatch.textContent = problem;
            overBox.scrollIntoView({ block: 'center' });
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

    const modal = document.getElementById('planPayModal-' + id);
    if (modal) { modal.addEventListener('hidden.bs.modal', showForm); }
});
</script>
