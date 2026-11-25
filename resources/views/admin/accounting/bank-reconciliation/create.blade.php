@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@include('admin.supplier-bill.partials.style')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('New bank reconciliation') }}</h5>
                        <a href="{{ route('admin.bank-reconciliation.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                    </div>
                    <div class="card-block">
                        <div class="ap-note mb-3">{{ __('Take the bank (or cash, or mobile money) statement for the month. Enter its date and its closing balance; the movements in the books up to that date are then listed for you to tick against the statement.') }}</div>

                        <form method="post" action="{{ route('admin.bank-reconciliation.store') }}">
                            @csrf
                            <div class="form-group">
                                <label for="chart_of_account_id">{{ __('Account') }} <span>*</span></label>
                                <select name="chart_of_account_id" id="chart_of_account_id" class="form-control" required>
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('chart_of_account_id', $selected) == $a->id)>{{ $a->account_code }} {{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                                @error('chart_of_account_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="statement_date">{{ __('Statement date') }} <span>*</span></label>
                                    <input type="date" name="statement_date" id="statement_date" class="form-control" required max="{{ now()->toDateString() }}"
                                           value="{{ old('statement_date', now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}">
                                    @error('statement_date')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="statement_closing_balance">{{ __('Closing balance on the statement') }} <span>*</span></label>
                                    <input type="number" step="0.01" name="statement_closing_balance" id="statement_closing_balance" class="form-control" required value="{{ old('statement_closing_balance') }}">
                                    @error('statement_closing_balance')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="statement_reference">{{ __('Statement reference') }}</label>
                                    <input type="text" name="statement_reference" id="statement_reference" class="form-control" maxlength="190" value="{{ old('statement_reference') }}">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="statement_opening_balance">{{ __('Opening balance on the statement') }}</label>
                                    <input type="number" step="0.01" name="statement_opening_balance" id="statement_opening_balance" class="form-control" value="{{ old('statement_opening_balance') }}"
                                           placeholder="{{ __('Taken from the last reconciliation if left empty') }}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="notes">{{ __('Notes') }}</label>
                                <textarea name="notes" id="notes" rows="2" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-success"><i class="fas fa-play"></i> {{ __('Start reconciliation') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
