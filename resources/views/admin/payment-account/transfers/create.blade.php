@extends('admin.layouts.master')
@section('title', __('fund_transfer'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('fund_transfer') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.payment-account-transfer.index') }}" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.payment-account-transfer.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> {{ __('transfer_info_text') }}
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="from_account_id">{{ __('from_account') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('from_account_id') is-invalid @enderror" 
                                                id="from_account_id" name="from_account_id" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($payment_accounts as $account)
                                            <option value="{{ $account->id }}" 
                                                    data-balance="{{ $account->current_balance }}"
                                                    {{ (old('from_account_id', $from_account_id ?? '') == $account->id) ? 'selected' : '' }}>
                                                {{ $account->title }} ({{ $account->accountType->title }}) - Balance: {{ number_format($account->current_balance, 2) }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('from_account_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div id="from_balance_display" class="text-muted small mt-1"></div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="to_account_id">{{ __('to_account') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('to_account_id') is-invalid @enderror" 
                                                id="to_account_id" name="to_account_id" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($payment_accounts as $account)
                                            <option value="{{ $account->id }}"
                                                    data-balance="{{ $account->current_balance }}"
                                                    {{ (old('to_account_id', $to_account_id ?? '') == $account->id) ? 'selected' : '' }}>
                                                {{ $account->title }} ({{ $account->accountType->title }}) - Balance: {{ number_format($account->current_balance, 2) }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('to_account_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div id="to_balance_display" class="text-muted small mt-1"></div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">{{ __('amount') }} <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" 
                                               class="form-control @error('amount') is-invalid @enderror" 
                                               id="amount" name="amount" value="{{ old('amount') }}" required>
                                        @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transfer_date">{{ __('transfer_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('transfer_date') is-invalid @enderror" 
                                               id="transfer_date" name="transfer_date" 
                                               value="{{ old('transfer_date', date('Y-m-d')) }}" required>
                                        @error('transfer_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="note">{{ __('note') }}</label>
                                        <textarea class="form-control @error('note') is-invalid @enderror" 
                                                  id="note" name="note" rows="3">{{ old('note') }}</textarea>
                                        @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="attach">{{ __('attachment') }}</label>
                                        <input type="file" class="form-control-file @error('attach') is-invalid @enderror" 
                                               id="attach" name="attach" accept=".jpg,.jpeg,.png,.pdf">
                                        <small class="text-muted">{{ __('allowed_files') }}: JPG, PNG, PDF (Max 2MB)</small>
                                        @error('attach')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-exchange-alt"></i> {{ __('transfer_funds') }}
                            </button>
                        </div>
                    </form>
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Update from account balance display
    $('#from_account_id').on('change', function() {
        var balance = $(this).find(':selected').data('balance');
        if (balance !== undefined) {
            $('#from_balance_display').html('<strong>{{ __('available_balance') }}:</strong> ' + parseFloat(balance).toFixed(2));
        } else {
            $('#from_balance_display').html('');
        }
    });

    // Update to account balance display
    $('#to_account_id').on('change', function() {
        var balance = $(this).find(':selected').data('balance');
        if (balance !== undefined) {
            $('#to_balance_display').html('<strong>{{ __('current_balance') }}:</strong> ' + parseFloat(balance).toFixed(2));
        } else {
            $('#to_balance_display').html('');
        }
    });

    // Validate transfer amount against from account balance
    $('form').on('submit', function(e) {
        var amount = parseFloat($('#amount').val());
        var fromBalance = parseFloat($('#from_account_id').find(':selected').data('balance'));
        
        if (amount > fromBalance) {
            e.preventDefault();
            alert('{{ __('insufficient_balance_in_from_account') }}');
            return false;
        }

        var fromAccountId = $('#from_account_id').val();
        var toAccountId = $('#to_account_id').val();
        
        if (fromAccountId === toAccountId) {
            e.preventDefault();
            alert('{{ __('cannot_transfer_to_same_account') }}');
            return false;
        }
    });
});
</script>
@endsection
