@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@php
    $dp = $setting->decimal_place ?? 2;
    $cs = $setting->currency_symbol ?? '';
    $money = fn ($v) => format_amount((float) $v);
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            @can('staff-loan-action')
            <div class="col-md-4">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post">
                @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('Record a loan or advance') }}</h5>
                        </div>
                        <div class="card-block">
                            <div class="form-group">
                                <label for="user_id" class="form-label">{{ __('Staff member') }} <span>*</span></label>
                                <select class="form-control select2" name="user_id" id="user_id" required>
                                    <option value="">{{ __('select') }}</option>
                                    @foreach($staff as $person)
                                    <option value="{{ $person->id }}" @if(old('user_id') == $person->id) selected @endif>{{ $person->first_name }} {{ $person->last_name }} ({{ $person->staff_id }})</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">{{ __('required_field') }} {{ __('Staff member') }}</div>
                            </div>
                            <div class="form-group">
                                <label for="kind" class="form-label">{{ __('Type') }} <span>*</span></label>
                                <select class="form-control" name="kind" id="kind" required>
                                    @foreach($kinds as $key => $label)
                                    <option value="{{ $key }}" @if(old('kind') == $key) selected @endif>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="title" class="form-label">{{ __('Name on the payslip') }}</label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" maxlength="100" placeholder="{{ __('e.g. Salary advance, Laptop loan') }}">
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="amount" class="form-label">{{ __('Amount lent') }} ({!! $cs !!}) <span>*</span></label>
                                    <input type="number" min="1" step="1" class="form-control" name="amount" id="amount" value="{{ old('amount') }}" required>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('Amount lent') }}</div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="instalment" class="form-label">{{ __('Repaid each month') }} ({!! $cs !!}) <span>*</span></label>
                                    <input type="number" min="1" step="1" class="form-control" name="instalment" id="instalment" value="{{ old('instalment') }}" required>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ __('Repaid each month') }}</div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="given_on" class="form-label">{{ __('Given on') }} <span>*</span></label>
                                    <input type="date" class="form-control" name="given_on" id="given_on" value="{{ old('given_on', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="start_month" class="form-label">{{ __('First repayment') }} <span>*</span></label>
                                    <input type="month" class="form-control" name="start_month" id="start_month" value="{{ old('start_month', now()->addMonthNoOverflow()->format('Y-m')) }}" required>
                                </div>
                            </div>
                            <p class="small text-muted mb-2" id="loanPlan"></p>
                            <div class="form-group">
                                <label for="note" class="form-label">{{ __('field_note') }}</label>
                                <textarea class="form-control" name="note" id="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
                            </div>
                            <p class="small {{ $accounting ? 'text-success' : 'text-muted' }}">
                                @if($accounting)
                                    <i class="fas fa-check-circle"></i> {{ __('The loan will be recorded in the accounts on the day it was given, and each repayment credited back.') }}
                                @else
                                    <i class="fas fa-info-circle"></i> {{ __('Loans are not recorded in the accounts: no accounts are set on the Payroll Settings page.') }}
                                @endif
                            </p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
            @endcan

            <div class="@can('staff-loan-action') col-md-8 @else col-md-12 @endcan">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="btn-group mb-3" role="group">
                            @foreach(['open' => __('Being repaid'), 'repaid' => __('Repaid'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $key => $label)
                            <a href="{{ route($route.'.index', ['status' => $key]) }}" class="btn btn-sm {{ $selected_status === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}@if($key !== 'all') ({{ $counts[$key] ?? 0 }})@endif</a>
                            @endforeach
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ __('Staff') }}</th>
                                        <th>{{ __('Loan') }}</th>
                                        <th class="text-right">{{ __('Lent') }}</th>
                                        <th class="text-right">{{ __('Each month') }}</th>
                                        <th class="text-right">{{ __('Repaid') }}</th>
                                        <th class="text-right">{{ __('Still owed') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $loan)
                                    <tr>
                                        <td>{{ $loan->user->first_name ?? '' }} {{ $loan->user->last_name ?? '' }}<br><small class="text-muted">{{ $loan->user->staff_id ?? '' }}</small></td>
                                        <td>{{ $loan->title }}<br><small class="text-muted">{{ __('Given') }} {{ $loan->given_on->format('j M Y') }} &middot; {{ __('from') }} {{ $loan->start_month->format('M Y') }}</small></td>
                                        <td class="text-right">{{ $money($loan->amount) }}</td>
                                        <td class="text-right">{{ $money($loan->instalment) }}</td>
                                        <td class="text-right">{{ $money($loan->repaid_amount) }}@if($loan->pending_amount > 0)<br><small class="text-muted">+{{ $money($loan->pending_amount) }} {{ __('on unpaid payrolls') }}</small>@endif</td>
                                        <td class="text-right"><strong>{{ $money($loan->balance_amount) }}</strong></td>
                                        <td>
                                            @if($loan->state === 'open')<span class="badge badge-pill badge-primary">{{ __('Being repaid') }}</span>
                                            @elseif($loan->state === 'repaid')<span class="badge badge-pill badge-success">{{ __('Repaid') }}</span>
                                            @else<span class="badge badge-pill badge-secondary">{{ __('Cancelled') }}</span>@endif
                                            @if($loan->journal_entry_id)<br><small class="text-muted">{{ __('In the accounts') }}</small>@endif
                                        </td>
                                        <td>
                                            @can('staff-loan-action')
                                            @if($loan->status === 'active' && !$loan->has_lines)
                                            <form action="{{ route($route.'.cancel', $loan->id) }}" method="post" onsubmit="return confirm('{{ __('Cancel this loan? Use this only for a loan entered by mistake.') }}');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Cancel') }}"><i class="fas fa-times"></i></button>
                                            </form>
                                            @endif
                                            @endcan
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="8" class="text-center text-muted">{{ __('No loans here.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script>
(function () {
    var amount = document.getElementById('amount'), instalment = document.getElementById('instalment'), plan = document.getElementById('loanPlan');
    if (!amount || !instalment || !plan) return;
    function update() {
        var a = parseFloat(amount.value), i = parseFloat(instalment.value);
        if (a > 0 && i > 0 && i <= a) {
            var months = Math.ceil(a / i), last = a - i * (months - 1);
            plan.textContent = months === 1 ? '{{ __('Repaid in one payroll.') }}' : '{{ __('Repaid over') }} ' + months + ' {{ __('payrolls') }}' + (last !== i ? ' ({{ __('the last one') }} ' + Math.round(last).toLocaleString() + ')' : '') + '.';
        } else {
            plan.textContent = '';
        }
    }
    amount.addEventListener('input', update);
    instalment.addEventListener('input', update);
    update();
})();
</script>
@endsection
