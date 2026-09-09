@extends('admin.layouts.master')
@section('title', __('Tax Remittance'))
@section('content')

{{--
    What the school is holding on somebody else's behalf, and the record of
    paying it over. The unit is the salary month, because that is the unit a
    declaration is made in.
--}}

<style>
    .remit-table th, .remit-table td {
        font-size: 0.82rem;
        padding: 0.45rem 0.6rem;
        vertical-align: middle;
        color: #333 !important;
    }
    .remit-table thead th {
        color: #333 !important;
        background: #f8f9fa;
        border-bottom: 2px solid #e3e6f0;
        white-space: nowrap;
    }
    .remit-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .remit-headline {
        border-left: 4px solid #e74a3b;
        background: #fdf3f2;
        padding: 14px 18px;
        border-radius: 6px;
        margin-bottom: 18px;
    }
    .remit-headline.is-clear {
        border-left-color: #1cc88a;
        background: #f0fbf6;
    }
    .remit-headline .rh-figure { font-size: 22px; font-weight: 700; color: #2d3436; }
    .remit-headline .rh-label { font-size: 12px; color: #858796; text-transform: uppercase; letter-spacing: .4px; }
    /* Bootstrap 5 dropped the badge-* colour variants, so each state carries
       its own colour here rather than relying on a class that no longer
       exists. Status is never colour alone — the label says it too. */
    .badge-undeclared { background: #e74a3b; color: #fff; }
    .badge-part { background: #f6c23e; color: #212529; }
    .badge-settled { background: #1cc88a; color: #fff; }
    .badge-over { background: #4e73df; color: #fff; }
    .badge-voided { background: #858796; color: #fff; }
    .badge-nobreak { background: #eef0f4; color: #5a6172; }
    .remit-breakdown { background: #fbfbfd; border-top: 1px solid #eef0f4; }
    .remit-breakdown .bd-line {
        display: grid;
        grid-template-columns: 1fr 120px 120px 120px 110px;
        gap: 10px;
        align-items: baseline;
        padding: 7px 18px 7px 34px;
        font-size: 0.78rem;
        color: #555;
        border-bottom: 1px solid #f1f3f6;
    }
    .remit-breakdown .bd-line:last-child { border-bottom: none; }
    .remit-breakdown .bd-n {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .remit-breakdown .bd-staff { color: #8a90a0; }
    @media (max-width: 700px) {
        .remit-breakdown .bd-line { grid-template-columns: 1fr auto; }
        .remit-breakdown .bd-label { grid-column: 1 / -1; }
    }
    .remit-void { text-decoration: line-through; opacity: .55; }
    .remit-warn {
        border-left: 4px solid #f6c23e;
        background: #fffaf0;
        padding: 12px 16px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 0.85rem;
    }
</style>

<div class="main-body">
    <div class="page-wrapper">

        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ __('Tax Remittance') }}</h5>
                            <p class="text-muted mb-0" style="font-size:.85rem;">
                                {{ __('Tax withheld from staff pay, and the record of paying it over to the authority it belongs to.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- What is owed, right now, before anything else on the page. --}}
        <div class="remit-headline {{ $total_owing > 0.005 ? '' : 'is-clear' }}">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="rh-label">{{ __('Currently held on behalf of others') }}</div>
                    <div class="rh-figure">{{ number_format($total_owing, 0) }}</div>
                </div>
                <div class="col-md-8">
                    @if($oldest_owing)
                        <div style="font-size:.88rem;">
                            {{ __('The oldest month still undeclared is') }}
                            <strong>{{ $oldest_owing['month_label'] }}</strong>
                            — {{ number_format($oldest_owing['outstanding'], 0) }}
                            {{ __('owed to') }} <strong>{{ $oldest_owing['account_name'] }}</strong>.
                        </div>
                        <div class="text-muted" style="font-size:.8rem; margin-top:4px;">
                            {{ __('This money was withheld from staff pay. It belongs to the authority, not to the school, until it is paid over.') }}
                        </div>
                    @else
                        <div style="font-size:.88rem;">
                            {{ __('Every month withheld so far has been declared and paid.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{--
            The two records of the same thing — the payroll breakdown and the
            posted ledger — checked against each other. When they disagree the
            screen says so; a confident total that nobody can trace is worse
            than an admitted discrepancy.
        --}}
        @php $mismatched = collect($reconciliation)->where('agrees', false); @endphp
        @if($mismatched->count() > 0)
            <div class="remit-warn">
                <strong>{{ __('These balances do not agree with the ledger.') }}</strong>
                {{ __('Something has posted to these accounts outside payroll — a manual journal entry, or a payroll paid before the breakdown was recorded. The figures below come from the ledger and are still what is owed, but the itemisation may be incomplete.') }}
                <ul class="mb-0 mt-2">
                    @foreach($mismatched as $m)
                        <li>
                            <strong>{{ $m['account_code'] }} {{ $m['account_name'] }}</strong>:
                            {{ __('months total') }} {{ number_format($m['per_month'], 2) }},
                            {{ __('ledger holds') }} {{ number_format($m['ledger'], 2) }}
                            ({{ __('difference') }} {{ number_format($m['difference'], 2) }})
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>{{ __('What is owed, by authority and month') }}</h5>
                        @can('tax-remittance-create')
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recordRemittance">
                                <i class="feather icon-plus me-1"></i>{{ __('Record a payment') }}
                            </button>
                        @endcan
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-hover remit-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Authority') }}</th>
                                    <th>{{ __('Salary month') }}</th>
                                    <th class="remit-num">{{ __('From staff') }}</th>
                                    <th class="remit-num">{{ __('School\'s share') }}</th>
                                    <th class="remit-num">{{ __('Total withheld') }}</th>
                                    <th class="remit-num">{{ __('Paid over') }}</th>
                                    <th class="remit-num">{{ __('Outstanding') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    @php $key = $row['account_id'].'|'.$row['month']; @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $row['account_name'] }}</strong>
                                            <span class="text-muted">({{ $row['account_code'] }})</span>
                                        </td>
                                        <td>{{ $row['month_label'] }}</td>
                                        <td class="remit-num">{{ number_format($row['employee'], 0) }}</td>
                                        <td class="remit-num">{{ number_format($row['employer'], 0) }}</td>
                                        <td class="remit-num"><strong>{{ number_format($row['due'], 0) }}</strong></td>
                                        <td class="remit-num">{{ $row['paid'] > 0 ? number_format($row['paid'], 0) : '—' }}</td>
                                        <td class="remit-num">
                                            <strong>{{ number_format($row['outstanding'], 0) }}</strong>
                                        </td>
                                        <td>
                                            @if($row['status'] === 'undeclared')
                                                <span class="badge badge-undeclared">{{ __('Not declared') }}</span>
                                            @elseif($row['status'] === 'part_paid')
                                                <span class="badge badge-part">{{ __('Part paid') }}</span>
                                            @elseif($row['status'] === 'overpaid')
                                                <span class="badge badge-over">{{ __('Overpaid') }}</span>
                                            @else
                                                <span class="badge badge-settled">{{ __('Settled') }}</span>
                                            @endif
                                            @unless($row['itemised'])
                                                <span class="badge badge-nobreak" title="{{ __('This month was paid before the per-tax breakdown was recorded. The amount is right; the itemisation below is unavailable.') }}">{{ __('no breakdown') }}</span>
                                            @endunless
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-link btn-sm p-0" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#bd-{{ $loop->index }}"
                                                    aria-expanded="false" aria-controls="bd-{{ $loop->index }}">
                                                {{ __('Details') }}
                                            </button>
                                            @can('tax-remittance-create')
                                                @if($row['outstanding'] > 0.005)
                                                    <button type="button" class="btn btn-outline-primary btn-sm ms-2 js-pay"
                                                            data-account="{{ $row['account_id'] }}"
                                                            data-month="{{ $row['month'] }}"
                                                            data-amount="{{ $row['outstanding'] }}"
                                                            data-name="{{ $row['account_name'] }}"
                                                            data-label="{{ $row['month_label'] }}">
                                                        {{ __('Pay') }}
                                                    </button>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                    {{--
                                        The collapsing element is the div, not the row.
                                        Bootstrap sets display:block when it opens, which on a
                                        <tr> collapses the whole table layout — so the row is
                                        always present and carries no padding or border, and
                                        the div inside it is what opens and closes.
                                    --}}
                                    <tr class="remit-breakdown-row">
                                        <td colspan="9" class="p-0 border-0">
                                            <div class="collapse" id="bd-{{ $loop->index }}">
                                                <div class="remit-breakdown">
                                                    @forelse($breakdowns[$key] ?? [] as $part)
                                                        <div class="bd-line">
                                                            <span class="bd-label">{{ $part['label'] }}</span>
                                                            <span class="bd-n">{{ number_format($part['employee'], 0) }}</span>
                                                            <span class="bd-n">{{ number_format($part['employer'], 0) }}</span>
                                                            <span class="bd-n"><strong>{{ number_format($part['total'], 0) }}</strong></span>
                                                            <span class="bd-staff">{{ $part['staff_count'] }} {{ __('staff') }}</span>
                                                        </div>
                                                    @empty
                                                        <div class="bd-line">
                                                            <span class="bd-label text-muted">
                                                                {{ __('No per-tax breakdown was recorded for this month. Run') }}
                                                                <code>php artisan payroll:backfill-tax-lines</code>
                                                                {{ __('to work it out from the payrolls.') }}
                                                            </span>
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            {{ __('No payroll has withheld any tax yet, so nothing is owed.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Payments recorded') }}</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-hover remit-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Paid on') }}</th>
                                    <th>{{ __('Authority') }}</th>
                                    <th>{{ __('For salary month') }}</th>
                                    <th class="remit-num">{{ __('Amount') }}</th>
                                    <th>{{ __('Paid from') }}</th>
                                    <th>{{ __('Receipt') }}</th>
                                    <th>{{ __('Recorded by') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($remittances as $r)
                                    <tr class="{{ $r->isVoided() ? 'remit-void' : '' }}">
                                        <td>{{ $r->payment_date ? $r->payment_date->format('d M Y') : '—' }}</td>
                                        <td>{{ $r->liabilityAccount->account_name ?? '—' }}</td>
                                        <td>{{ $r->salary_month ? $r->salary_month->format('F Y') : '—' }}</td>
                                        <td class="remit-num">{{ number_format($r->amount, 0) }}</td>
                                        <td>{{ $r->sourceAccount->account_name ?? '—' }}</td>
                                        <td>{{ $r->reference ?: '—' }}</td>
                                        <td>{{ $r->creator->first_name ?? '—' }}</td>
                                        <td class="text-end">
                                            @if($r->isVoided())
                                                <span class="badge badge-voided">{{ __('Voided') }}</span>
                                            @else
                                                @can('tax-remittance-void')
                                                    {{--
                                                        The message lives in a data attribute rather than
                                                        inline in onsubmit: a translation containing an
                                                        apostrophe would close the JS string and break the
                                                        handler, silently turning a confirmed action into an
                                                        unconfirmed one.
                                                    --}}
                                                    <form action="{{ route('admin.tax-remittance.void', $r->id) }}" method="POST"
                                                          class="js-confirm d-inline"
                                                          data-confirm="{{ __('This will reverse the payment in the ledger and the month will show as owed again. Continue?') }}">
                                                        @csrf
                                                        <button class="btn btn-outline-danger btn-sm">{{ __('Void') }}</button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            {{ __('Nothing has been paid over yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@can('tax-remittance-create')
<div class="modal fade" id="recordRemittance" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form action="{{ route('admin.tax-remittance.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Record a payment to an authority') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">

                    <div class="form-group">
                        <label>{{ __('Authority') }} <span class="text-danger">*</span></label>
                        <select name="liability_account_id" id="rm-account" class="form-control" required>
                            <option value="">{{ __('Select') }}</option>
                            @foreach(collect($rows)->unique('account_id') as $r)
                                <option value="{{ $r['account_id'] }}">{{ $r['account_name'] }} ({{ $r['account_code'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Salary month this covers') }} <span class="text-danger">*</span></label>
                        <select name="salary_month" id="rm-month" class="form-control" required>
                            <option value="">{{ __('Select') }}</option>
                            @foreach(collect($rows)->unique('month') as $r)
                                <option value="{{ $r['month'] }}">{{ $r['month_label'] }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            {{ __('The month the pay related to — not the month you are paying in.') }}
                        </small>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Amount') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="rm-amount" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Date paid') }} <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Paid from') }} <span class="text-danger">*</span></label>
                        <select name="source_account_id" class="form-control" required>
                            <option value="">{{ __('Select') }}</option>
                            @foreach($source_accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            {{ __('Only cash and bank accounts are offered. Tax is not an expense here — the salary was already recorded as one when it was paid.') }}
                        </small>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Receipt or declaration number') }}</label>
                        <input type="text" name="reference" class="form-control" maxlength="191"
                               placeholder="{{ __('As issued by the authority') }}">
                    </div>

                    <div class="form-group">
                        <label>{{ __('Note') }}</label>
                        <textarea name="note" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="allow_overpayment" value="1" id="rm-over">
                        <label class="form-check-label" for="rm-over">
                            {{ __('This is more than was withheld (a penalty or an adjustment)') }}
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="allow_additional" value="1" id="rm-add">
                        <label class="form-check-label" for="rm-add">
                            {{ __('This month has already been paid — record an additional payment') }}
                        </label>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Record and post') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

@push('scripts')
<script>
    // The Pay button on a row fills the form in, so the common case is one
    // click and a receipt number rather than re-picking what the row already
    // says.
    // Bootstrap 5: there is no jQuery .modal() plugin, so the modal is opened
    // through the Bootstrap API. getOrCreateInstance rather than new, so
    // clicking Pay on a second row reuses the instance instead of stacking a
    // fresh backdrop behind the dialog.
    document.querySelectorAll('.js-pay').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('rm-account').value = this.dataset.account;
            document.getElementById('rm-month').value = this.dataset.month;
            document.getElementById('rm-amount').value = this.dataset.amount;

            var dialog = document.getElementById('recordRemittance');

            if (dialog && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(dialog).show();
            }
        });
    });

    // Voiding reverses a posted entry, so it asks first.
    document.querySelectorAll('form.js-confirm').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(this.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
</script>
@endpush

@endsection
