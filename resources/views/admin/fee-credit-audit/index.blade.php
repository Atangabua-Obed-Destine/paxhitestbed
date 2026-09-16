@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@php
    $money = fn ($amount) => number_format((float) $amount, 0);
    $canCorrect = auth()->user()->can('fee-credit-audit-correct');
    $voidable = (float) $duplicates->sum('voidable');
    $alreadySpent = (float) $duplicates->sum('already_spent');
    $correctionTotal = (float) $corrections->sum('difference');
    $landsToday = $corrections->where('lands_today', true)->count();
@endphp

<div class="main-body">
    <div class="page-wrapper">

        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-info">
                    <strong>{{ __('What this page is for') }}</strong><br>
                    {{ __('When an overpaid First Instalment was moved to the Second Instalment as credit, the credit was added to the Second Instalment but the money was never taken off the First, and the ledger posted it as cash a second time. This page shows what that did to the figures and corrects it.') }}
                    <br><strong>{{ __('Take a database backup before using either button.') }}</strong>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-block">
                    <h4 class="text-c-blue">{{ $money($collected['net_paid']) }}</h4>
                    <h6 class="text-muted m-b-0">{{ __('Actually collected (FCFA)') }}</h6>
                    <small class="text-muted">{{ __('paid_amount :paid less :moved moved between fees', ['paid' => $money($collected['paid']), 'moved' => $money($collected['moved_out'])]) }}</small>
                </div></div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-block">
                    <h4 class="{{ $voidable > 0.009 ? 'text-c-red' : 'text-c-green' }}">{{ $money($voidable) }}</h4>
                    <h6 class="text-muted m-b-0">{{ __('Duplicated credit to void') }}</h6>
                    <small class="text-muted">{{ trans_choice(':count fee|:count fees', $duplicates->count(), ['count' => $duplicates->count()]) }}</small>
                </div></div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-block">
                    <h4 class="{{ $corrections->isNotEmpty() ? 'text-c-red' : 'text-c-green' }}">{{ $money($correctionTotal) }}</h4>
                    <h6 class="text-muted m-b-0">{{ __('Posted to the ledger twice') }}</h6>
                    <small class="text-muted">{{ trans_choice(':count fee posting to correct|:count fee postings to correct', $corrections->count(), ['count' => $corrections->count()]) }}</small>
                </div></div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-block">
                    <h4 class="{{ $unevidenced->isNotEmpty() ? 'text-c-yellow' : 'text-c-green' }}">{{ $money($unevidenced->sum('unevidenced')) }}</h4>
                    <h6 class="text-muted m-b-0">{{ __('Paid with no receipt on file') }}</h6>
                    <small class="text-muted">{{ __('Check against the cash book — not changed here') }}</small>
                </div></div>
            </div>
        </div>

        {{-- 1. Duplicated credit --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">{{ __('Credit raised twice for the same overpayment') }}</h5>
                        @if ($canCorrect)
                            <button type="button" class="btn btn-danger btn-sm" id="btn-void-duplicates" @disabled($voidable <= 0.009)>
                                <i class="fas fa-ban me-1"></i>{{ __('Void duplicated credit') }}
                            </button>
                        @endif
                    </div>
                    <div class="card-block">
                        @if ($duplicates->isEmpty())
                            <p class="text-success mb-0"><i class="fas fa-check me-1"></i>{{ __('None. Every credit is backed by a payment.') }}</p>
                        @else
                            <p class="text-muted">{{ __('The student holds more credit than the fee was really overpaid by. Voiding cancels only the unspent part; credit already applied to another fee is left alone and listed for manual review.') }}</p>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr>
                                        <th>{{ __('Fee') }}</th><th>{{ __('Student') }}</th>
                                        <th class="text-end">{{ __('Due') }}</th><th class="text-end">{{ __('Paid') }}</th>
                                        <th class="text-end">{{ __('Really overpaid') }}</th><th class="text-end">{{ __('Credited') }}</th>
                                        <th class="text-end">{{ __('Unbacked') }}</th><th class="text-end">{{ __('Will be voided') }}</th>
                                        <th class="text-end">{{ __('Already spent') }}</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach ($duplicates as $d)
                                            <tr>
                                                <td>#{{ $d['fee_id'] }}</td><td>{{ $d['student_id'] }}</td>
                                                <td class="text-end">{{ $money($d['due']) }}</td><td class="text-end">{{ $money($d['paid']) }}</td>
                                                <td class="text-end">{{ $money($d['genuine_overpayment']) }}</td><td class="text-end">{{ $money($d['credited']) }}</td>
                                                <td class="text-end">{{ $money($d['excess']) }}</td><td class="text-end fw-bold">{{ $money($d['voidable']) }}</td>
                                                <td class="text-end">{{ $money($d['already_spent']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($alreadySpent > 0.009)
                                <div class="alert alert-warning mb-0">{{ __(':amount of unbacked credit was already applied to other fees and needs manual review.', ['amount' => $money($alreadySpent)]) }}</div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Ledger --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">{{ __('Fee postings against cash actually received') }}</h5>
                        @if ($canCorrect)
                            <button type="button" class="btn btn-danger btn-sm" id="btn-correct-ledger" @disabled($corrections->isEmpty())>
                                <i class="fas fa-book me-1"></i>{{ __('Correct the ledger') }}
                            </button>
                        @endif
                    </div>
                    <div class="card-block">
                        <p class="mb-2">
                            {{ __('Fee postings :posted against :collected collected: overstated by :over.', ['posted' => $money($exposure['posted']), 'collected' => $money($exposure['net_paid']), 'over' => $money($exposure['overstated'])]) }}
                        </p>

                        @if ($corrections->isEmpty())
                            <p class="text-success mb-0"><i class="fas fa-check me-1"></i>{{ __('Every fee posting matches the cash the fee received.') }}</p>
                        @else
                            <p class="text-muted">
                                {{ __('Each fee below was posted as cash received (Dr cash, Cr tuition income) for credit that had already been posted on the fee it came from. Correcting reverses the original entry — it is never deleted — and posts the cash actually received. The reversal goes in the original month while that period is open, or is dated today once the period is closed.') }}
                            </p>

                            <div class="row">
                                <div class="col-md-4">
                                    <table class="table table-sm">
                                        <thead><tr><th>{{ __('Month posted') }}</th><th class="text-end">{{ __('Fees') }}</th><th class="text-end">{{ __('Reduction') }}</th></tr></thead>
                                        <tbody>
                                            @foreach ($correctionsByMonth as $m)
                                                <tr><td>{{ $m['month'] }}</td><td class="text-end">{{ $m['fees'] }}</td><td class="text-end">{{ $money($m['amount']) }}</td></tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td class="text-end">{{ $corrections->count() }}</td><td class="text-end">{{ $money($correctionTotal) }}</td></tr></tfoot>
                                    </table>
                                </div>
                                <div class="col-md-8">
                                    <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                        <table class="table table-sm table-striped">
                                            <thead><tr>
                                                <th>{{ __('Fee') }}</th><th>{{ __('Student') }}</th><th>{{ __('Category') }}</th>
                                                <th class="text-end">{{ __('Posted') }}</th><th class="text-end">{{ __('Cash received') }}</th>
                                                <th class="text-end">{{ __('Difference') }}</th><th>{{ __('Correction lands') }}</th>
                                            </tr></thead>
                                            <tbody>
                                                @foreach ($corrections as $c)
                                                    <tr>
                                                        <td>#{{ $c['fee_id'] }}</td><td>{{ $c['student'] }}</td><td>{{ $c['category'] }}</td>
                                                        <td class="text-end">{{ $money($c['posted']) }}</td><td class="text-end">{{ $money($c['cash']) }}</td>
                                                        <td class="text-end fw-bold">{{ $money($c['difference']) }}</td>
                                                        <td>
                                                            @if ($c['lands_today'])
                                                                <span class="badge bg-warning text-dark">{{ __('Today — :period is closed', ['period' => $c['period']]) }}</span>
                                                            @else
                                                                {{ $c['period'] ?? $c['entry_date'] }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Collected by category --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Collected, net of credit moved between fees') }}</h5></div>
                    <div class="card-block table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Fees') }}</th><th class="text-end">{{ __('paid_amount') }}</th><th class="text-end">{{ __('Moved out') }}</th><th class="text-end">{{ __('Collected') }}</th></tr></thead>
                            <tbody>
                                @foreach ($collected['by_category'] as $r)
                                    <tr><td>{{ $r->category ?? '—' }}</td><td class="text-end">{{ $r->fees }}</td><td class="text-end">{{ $money($r->paid) }}</td><td class="text-end">{{ $money($r->moved_out) }}</td><td class="text-end">{{ $money($r->net_paid) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td></td><td class="text-end">{{ $money($collected['paid']) }}</td><td class="text-end">{{ $money($collected['moved_out']) }}</td><td class="text-end">{{ $money($collected['net_paid']) }}</td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 4 & 5. For review only --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Paid with no receipt on file') }}</h5></div>
                    <div class="card-block">
                        @if ($unevidenced->isEmpty())
                            <p class="text-success mb-0">{{ __('None.') }}</p>
                        @else
                            <p class="text-muted">{{ __('Recorded as paid, but no approved receipt or credit accounts for it. Confirm against the cash book; nothing here is changed by the buttons.') }}</p>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm">
                                    <thead><tr><th>{{ __('Fee') }}</th><th>{{ __('Student') }}</th><th>{{ __('Category') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('No receipt') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($unevidenced as $u)
                                            <tr><td>#{{ $u['fee_id'] }}</td><td>{{ $u['student'] }}</td><td>{{ $u['category'] }}</td><td class="text-end">{{ $money($u['paid']) }}</td><td class="text-end">{{ $money($u['unevidenced']) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Credit applications pointing at a deleted fee') }}</h5></div>
                    <div class="card-block">
                        @if ($orphans->isEmpty())
                            <p class="text-success mb-0">{{ __('None.') }}</p>
                        @else
                            <p class="text-muted">{{ __(':count, totalling :amount. Usually an audit trail left when the money was transferred again; check each student.', ['count' => $orphans->count(), 'amount' => $money($orphans->sum('amount_applied'))]) }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@if ($canCorrect)
@push('scripts')
<script>
(function () {
    function escapeHtml(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }

    function run(button, options) {
        if (!button) { return; }

        button.addEventListener('click', function () {
            Swal.fire({
                title: options.title,
                html: options.html + '<br><br><strong>' + escapeHtml(@json(__('Take a database backup first.'))) + '</strong>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: options.confirm,
                cancelButtonText: @json(__('cancel'))
            }).then(function (result) {
                if (!result.isConfirmed) { return; }

                var original = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + escapeHtml(@json(__('Working…')));

                $.ajax({
                    url: options.url,
                    type: 'POST',
                    data: { _token: @json(csrf_token()) },
                    success: function (res) {
                        var html = '<p>' + escapeHtml(res.message) + '</p>';
                        var failed = (res.result && res.result.failed) || [];

                        if (failed.length) {
                            html += '<div class="text-start small"><strong>' + escapeHtml(@json(__('Not corrected:'))) + '</strong><ul class="mb-0">'
                                + failed.map(function (f) { return '<li>' + escapeHtml('Fee #' + f.fee_id + ' — ' + f.reason) + '</li>'; }).join('')
                                + '</ul></div>';
                        }

                        Swal.fire({ icon: failed.length ? 'warning' : 'success', title: @json(__('Done')), html: html })
                            .then(function () { location.reload(); });
                    },
                    error: function (xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) || @json(__('an_error_occurred'));
                        Swal.fire({ icon: 'error', title: @json(__('error')), text: message });
                        button.innerHTML = original;
                        button.disabled = false;
                    }
                });
            });
        });
    }

    run(document.getElementById('btn-void-duplicates'), {
        url: @json(route('admin.fees-credit-audit.void-duplicates')),
        title: @json(__('Void duplicated credit?')),
        html: escapeHtml(@json(__(':amount FCFA of unspent credit across :count fee(s) will be cancelled. The students will no longer be able to use it. Credit already applied to other fees is not touched.', ['amount' => $money($voidable), 'count' => $duplicates->count()]))),
        confirm: @json(__('Void credit'))
    });

    run(document.getElementById('btn-correct-ledger'), {
        url: @json(route('admin.fees-credit-audit.correct-ledger')),
        title: @json(__('Correct the ledger?')),
        html: escapeHtml(@json(__(':count fee posting(s) will be reversed and reposted at the cash actually received. Cash and tuition income each fall by :amount FCFA.', ['count' => $corrections->count(), 'amount' => $money($correctionTotal)])))
            + (@json($landsToday) > 0
                ? '<br>' + escapeHtml(@json(__(':count correction(s) will be dated today because their period is closed.', ['count' => $landsToday])))
                : ''),
        confirm: @json(__('Correct the ledger'))
    });
})();
</script>
@endpush
@endif
