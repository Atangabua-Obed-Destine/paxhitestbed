@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@php
    $yesNo = function ($name, $value, $yes, $no) {
        $html = '<select class="form-control" name="' . $name . '" id="' . $name . '">';
        $html .= '<option value="0"' . (!$value ? ' selected' : '') . '>' . e($no) . '</option>';
        $html .= '<option value="1"' . ($value ? ' selected' : '') . '>' . e($yes) . '</option>';
        return $html . '</select>';
    };
    $chosen = $settings->bankColumns();
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <form class="needs-validation" novalidate action="{{ route($route.'.update') }}" method="post">
        @csrf
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Approval') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="form-group">
                            <label for="require_approval" class="form-label">{{ __('Payrolls must be approved before they are paid') }}</label>
                            {!! $yesNo('require_approval', $settings->require_approval, __('Yes, approve before paying'), __('No, pay straight after generating')) !!}
                            <small class="form-text text-muted">{{ __('Approving needs the payroll-approve permission. Give it to the role that checks payroll (Settings › Roles). Changing a payroll\'s figures clears its approval.') }}</small>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Generating') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="form-group">
                            <label for="carry_over_lines" class="form-label">{{ __('Start each new payroll from last month\'s allowances and deductions') }}</label>
                            {!! $yesNo('carry_over_lines', $settings->carry_over_lines, __('Yes, copy last month\'s lines'), __('No, start empty')) !!}
                            <small class="form-text text-muted">{{ __('Loan repayments are always added from the loan itself and are never copied.') }}</small>
                        </div>
                        <div class="form-group">
                            <label for="prorate_partial_months" class="form-label">{{ __('Fixed salaries for part months') }}</label>
                            {!! $yesNo('prorate_partial_months', $settings->prorate_partial_months, __('Pay only the days between joining and leaving'), __('Always pay the full month')) !!}
                            <small class="form-text text-muted">{{ __('Uses the joining date and ending date on the staff record.') }}</small>
                        </div>
                        <div class="form-group">
                            <label for="proration_basis" class="form-label">{{ __('Count part months in') }}</label>
                            <select class="form-control" name="proration_basis" id="proration_basis">
                                @foreach($bases as $key => $label)
                                <option value="{{ $key }}" @if($settings->proration_basis === $key) selected @endif>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Payslips') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="form-group">
                            <label for="staff_payslips_enabled" class="form-label">{{ __('Staff can see and download their own payslips') }}</label>
                            {!! $yesNo('staff_payslips_enabled', $settings->staff_payslips_enabled, __('Yes, under My Payslips'), __('No')) !!}
                        </div>
                        <div class="form-group">
                            <label for="email_payslip_on_pay" class="form-label">{{ __('Email the payslip when a payroll is paid') }}</label>
                            {!! $yesNo('email_payslip_on_pay', $settings->email_payslip_on_pay, __('Yes, email it as a PDF'), __('No')) !!}
                            <small class="form-text text-muted">{{ __('Sent to the email on the staff record, from the sender in the mail settings. A payslip can also be emailed from its row at any time.') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Bank file') }}</h5>
                    </div>
                    <div class="card-block">
                        <p class="text-muted small">{{ __('Ask your bank for the layout it accepts, then match it here. Tick the columns and number them in the order the bank wants.') }}</p>
                        <table class="table table-sm">
                            <thead><tr><th>{{ __('Column') }}</th><th style="width: 110px;">{{ __('Position') }}</th></tr></thead>
                            <tbody>
                            @foreach($columns as $key => $label)
                            <tr>
                                <td>
                                    <label class="mb-0"><input type="checkbox" name="bank_file_columns[]" value="{{ $key }}" @if(in_array($key, $chosen)) checked @endif> {{ __($label) }}</label>
                                </td>
                                <td><input type="number" min="1" max="20" class="form-control form-control-sm" name="bank_file_order[{{ $key }}]" value="{{ in_array($key, $chosen) ? array_search($key, $chosen) + 1 : '' }}"></td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="bank_file_delimiter" class="form-label">{{ __('Separator') }}</label>
                                <select class="form-control" name="bank_file_delimiter" id="bank_file_delimiter">
                                    @foreach([',' => __('Comma ( , )'), ';' => __('Semicolon ( ; )'), 'tab' => __('Tab'), '|' => __('Bar ( | )')] as $value => $label)
                                    <option value="{{ $value }}" @if($settings->bank_file_delimiter === $value) selected @endif>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="bank_file_header" class="form-label">{{ __('First row') }}</label>
                                {!! $yesNo('bank_file_header', $settings->bank_file_header, __('Column names'), __('No header, data only')) !!}
                            </div>
                            <div class="form-group col-md-8">
                                <label for="bank_file_reference" class="form-label">{{ __('Payment reference') }}</label>
                                <input type="text" class="form-control" name="bank_file_reference" id="bank_file_reference" value="{{ $settings->bank_file_reference }}" maxlength="120" required>
                                <small class="form-text text-muted">{{ __('You can use {MONTH} (NOV), {MONTH_NUMBER} (11), {YEAR}, {STAFF_ID} and {NAME}.') }}</small>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="bank_file_decimals" class="form-label">{{ __('Decimals in amounts') }}</label>
                                <select class="form-control" name="bank_file_decimals" id="bank_file_decimals">
                                    @foreach([0, 1, 2] as $d)
                                    <option value="{{ $d }}" @if((int) $settings->bank_file_decimals === $d) selected @endif>{{ $d }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Loans and advances in the accounts') }}</h5>
                    </div>
                    <div class="card-block">
                        <p class="text-muted small">{{ __('Leave both empty to keep loans out of the accounts: repayments then reduce the pay like any other deduction. Set both, after agreeing them with your accountant, to record each loan when it is given and credit each repayment back. Under OHADA the staff advances account is usually 425 (Personnel, avances et acomptes); create it in the Chart of Accounts if it is missing.') }}</p>
                        <div class="form-group">
                            <label for="advance_account_id" class="form-label">{{ __('Staff advances account') }}</label>
                            <select class="form-control select2" name="advance_account_id" id="advance_account_id">
                                <option value="">{{ __('Not set') }}</option>
                                @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @if((int) $settings->advance_account_id === $account->id) selected @endif>{{ $account->account_code }} - {{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="advance_payment_account_id" class="form-label">{{ __('Account loans are paid out from') }}</label>
                            <select class="form-control select2" name="advance_payment_account_id" id="advance_payment_account_id">
                                <option value="">{{ __('Not set') }}</option>
                                @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @if((int) $settings->advance_payment_account_id === $account->id) selected @endif>{{ $account->account_code }} - {{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="text-right mb-4">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                </div>
            </div>
        </div>
        </form>
    </div>
</div>

@endsection
