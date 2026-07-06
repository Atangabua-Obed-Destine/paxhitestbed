@extends('admin.layouts.master')
@section('title', $title)

@section('content')
<style>
    .aff-summary-card{border-radius:.5rem;color:#fff;padding:1rem 1.25rem;height:100%}
    .aff-summary-card .label{font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;opacity:.85}
    .aff-summary-card .value{font-size:1.5rem;font-weight:600;line-height:1.2}
    .aff-summary-billed{background:linear-gradient(135deg,#4e73df,#224abe)}
    .aff-summary-paid{background:linear-gradient(135deg,#1cc88a,#13855c)}
    .aff-summary-outstanding{background:linear-gradient(135deg,#e74a3b,#a52a1e)}
    .aff-summary-count{background:linear-gradient(135deg,#f6c23e,#c69120)}
    .aff-table td, .aff-table th{vertical-align:middle;font-size:.85rem}
    .aff-badge-stage{font-size:.7rem}
</style>
<div class="main-body">
    <div class="page-wrapper">
        @include('admin.layouts.inc.breadcrumb')

        <div class="page-body">

            {{-- Summary cards --}}
            <div class="row mb-3">
                <div class="col-md-3 mb-2">
                    <div class="aff-summary-card aff-summary-count">
                        <div class="label">{{ __('Applicant Fees') }}</div>
                        <div class="value">{{ number_format($totals->fee_count ?? 0) }}</div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="aff-summary-card aff-summary-billed">
                        <div class="label">{{ __('Total Billed') }}</div>
                        <div class="value">{{ number_format((float) ($totals->billed ?? 0), 2) }}</div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="aff-summary-card aff-summary-paid">
                        <div class="label">{{ __('Total Received') }}</div>
                        <div class="value">{{ number_format((float) ($totals->paid ?? 0), 2) }}</div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="aff-summary-card aff-summary-outstanding">
                        <div class="label">{{ __('Outstanding') }}</div>
                        <div class="value">{{ number_format(max(0, (float) ($totals->billed ?? 0) - (float) ($totals->paid ?? 0)), 2) }}</div>
                    </div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-filter"></i> {{ __('Filters') }}</h5>
                </div>
                <div class="card-block">
                    <form method="GET" action="{{ route($route.'.index') }}" class="row">
                        <div class="col-md-2 form-group">
                            <label>{{ __('Session') }}</label>
                            <select name="session" class="form-control">
                                <option value="">{{ __('All') }}</option>
                                @foreach($sessions as $s)
                                    <option value="{{ $s->id }}" {{ (string)$filters['session']===(string)$s->id?'selected':'' }}>{{ $s->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>{{ __('Program') }}</label>
                            <select name="program" class="form-control">
                                <option value="">{{ __('All') }}</option>
                                @foreach($programs as $p)
                                    <option value="{{ $p->id }}" {{ (string)$filters['program']===(string)$p->id?'selected':'' }}>{{ $p->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>{{ __('Payment Status') }}</label>
                            <select name="payment_status" class="form-control">
                                <option value="all" {{ $filters['payment_status']==='all'?'selected':'' }}>{{ __('All') }}</option>
                                <option value="unpaid" {{ $filters['payment_status']==='unpaid'?'selected':'' }}>{{ __('Unpaid') }}</option>
                                <option value="partial" {{ $filters['payment_status']==='partial'?'selected':'' }}>{{ __('Partial') }}</option>
                                <option value="paid" {{ $filters['payment_status']==='paid'?'selected':'' }}>{{ __('Fully Paid') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>{{ __('Stage') }}</label>
                            <select name="stage" class="form-control">
                                <option value="all" {{ $filters['stage']==='all'?'selected':'' }}>{{ __('All') }}</option>
                                <option value="applicant" {{ $filters['stage']==='applicant'?'selected':'' }}>{{ __('Applicant') }}</option>
                                <option value="enrolled" {{ $filters['stage']==='enrolled'?'selected':'' }}>{{ __('Enrolled') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>{{ __('Search') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Reg#, name, email, phone') }}">
                        </div>
                        <div class="col-md-2 form-group">
                            <label>{{ __('From') }}</label>
                            <input type="date" name="from" class="form-control" value="{{ $filters['from'] }}">
                        </div>
                        <div class="col-md-2 form-group">
                            <label>{{ __('To') }}</label>
                            <input type="date" name="to" class="form-control" value="{{ $filters['to'] }}">
                        </div>
                        <div class="col-md-8 form-group d-flex align-items-end">
                            <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> {{ __('Apply') }}</button>
                            <a href="{{ route($route.'.index') }}" class="btn btn-light"><i class="fas fa-undo"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Table --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-list"></i> {{ __('Applicant Admission Fees') }}</h5>
                    <small class="text-muted">{{ __('Showing :n results', ['n' => $fees->total()]) }}</small>
                </div>
                <div class="card-block table-responsive">
                    <table class="table table-hover aff-table">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('Applicant') }}</th>
                                <th>{{ __('Program / Intake') }}</th>
                                <th class="text-right">{{ __('Billed') }}</th>
                                <th class="text-right">{{ __('Paid') }}</th>
                                <th class="text-right">{{ __('Balance') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Stage') }}</th>
                                <th>{{ __('Assigned') }}</th>
                                <th class="text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($fees as $fee)
                                @php
                                    $app = $fee->applicant;
                                    $balance = max(0, (float)$fee->fee_amount + (float)$fee->fine_amount - (float)$fee->discount_amount - (float)$fee->paid_amount);
                                    $statusMap = [0=>['label'=>'Unpaid','class'=>'danger'],2=>['label'=>'Partial','class'=>'warning'],1=>['label'=>'Paid','class'=>'success']];
                                    $st = $statusMap[$fee->status] ?? ['label'=>'—','class'=>'secondary'];
                                    $isEnrolled = $app && (int)$app->status === 2;
                                    $lastReceipt = $fee->paymentReceipts->where('verification_status','approved')->sortByDesc('created_at')->first();
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration + (($fees->currentPage()-1)*$fees->perPage()) }}</td>
                                    <td>
                                        @if($app)
                                            <strong>{{ trim($app->first_name.' '.$app->last_name) ?: '—' }}</strong><br>
                                            <small class="text-muted">{{ $app->registration_no }}</small>
                                            @if($app->email)<br><small><i class="fas fa-envelope"></i> {{ $app->email }}</small>@endif
                                        @else
                                            <span class="text-muted">{{ __('Deleted application') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ optional($app?->program)->title ?? '—' }}<br>
                                        <small class="text-muted">
                                            {{ optional($app?->degreeType)->title ?? '' }}
                                            @if($app?->session) · {{ $app->session->title }} @endif
                                        </small>
                                    </td>
                                    <td class="text-right">{{ number_format((float)$fee->fee_amount,2) }}</td>
                                    <td class="text-right">{{ number_format((float)$fee->paid_amount,2) }}</td>
                                    <td class="text-right"><strong>{{ number_format($balance,2) }}</strong></td>
                                    <td><span class="badge badge-{{ $st['class'] }}">{{ __($st['label']) }}</span></td>
                                    <td>
                                        @if($isEnrolled)
                                            <span class="badge badge-info aff-badge-stage">{{ __('Enrolled') }}</span>
                                        @else
                                            <span class="badge badge-secondary aff-badge-stage">{{ __('Applicant') }}</span>
                                        @endif
                                    </td>
                                    <td><small>{{ optional($fee->assign_date)->format('Y-m-d') }}</small></td>
                                    <td class="text-right">
                                        @if($balance > 0)
                                            <button type="button" class="btn btn-sm btn-primary walkin-open"
                                                data-bs-toggle="modal"
                                                data-bs-target="#walkinModal"
                                                data-fee-id="{{ $fee->id }}"
                                                data-applicant="{{ trim(($app->first_name ?? '').' '.($app->last_name ?? '')) }}"
                                                data-reg="{{ $app->registration_no ?? '' }}"
                                                data-balance="{{ number_format($balance,2,'.','') }}"
                                                data-action="{{ route($route.'.walk-in', $fee->id) }}">
                                                <i class="fas fa-cash-register"></i> {{ __('Record Walk-in') }}
                                            </button>
                                        @endif
                                        @if($lastReceipt)
                                            <a href="{{ route($route.'.receipt', $lastReceipt->id) }}" target="_blank" class="btn btn-sm btn-outline-success" title="{{ __('View latest receipt') }}">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted py-4">{{ __('No admission fees match these filters.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $fees->links() }}
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Walk-in payment modal --}}
<div class="modal fade" id="walkinModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form id="walkinForm" method="POST" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cash-register"></i> {{ __('Record Walk-in Payment') }}</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong id="walkinApplicant">—</strong>
                        <small class="text-muted d-block">Reg# <span id="walkinReg">—</span> · Outstanding <strong id="walkinBalance">—</strong></small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="walkinAmount" step="0.01" min="0.01" class="form-control" required>
                            <small class="text-muted">{{ __('Cannot exceed outstanding balance.') }}</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Payment Date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Payment Method') }} <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-control" required>
                                <option value="2">{{ __('Cash') }}</option>
                                <option value="4">{{ __('Bank Transfer / Deposit') }}</option>
                                <option value="3">{{ __('Cheque') }}</option>
                                <option value="8">{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Payment Account') }}</label>
                            <select name="payment_account_id" class="form-control">
                                <option value="">{{ __('—') }}</option>
                                @foreach($paymentAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->title }} @if($acc->account_number)({{ $acc->account_number }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>{{ __('Reference / Slip Number') }}</label>
                            <input type="text" name="payment_reference" class="form-control" placeholder="{{ __('Auto-generated if left empty') }}">
                        </div>
                        <div class="col-md-12 form-group">
                            <label>{{ __('Note') }}</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="{{ __('Optional note for the record') }}"></textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="printAfter" name="print_after" value="1" checked>
                                <label class="custom-control-label" for="printAfter">{{ __('Open printable receipt after saving') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('Save Payment') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('walkinModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            var form = document.getElementById('walkinForm');
            form.action = btn.getAttribute('data-action');
            document.getElementById('walkinApplicant').textContent = btn.getAttribute('data-applicant') || '—';
            document.getElementById('walkinReg').textContent = btn.getAttribute('data-reg') || '—';
            document.getElementById('walkinBalance').textContent = btn.getAttribute('data-balance') || '—';
            var amt = document.getElementById('walkinAmount');
            amt.value = btn.getAttribute('data-balance');
            amt.setAttribute('max', btn.getAttribute('data-balance'));
        });
    })();
</script>
@endpush
