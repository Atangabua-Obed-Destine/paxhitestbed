@extends('admin.layouts.master')
@section('title', __('new_bank_reconciliation'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('new_bank_reconciliation') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.bank-reconciliation.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    
                    @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible mx-3 mt-3">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <h5><i class="icon fas fa-ban"></i> {{ __('validation_errors') }}</h5>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('admin.bank-reconciliation.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> {{ __('bank_reconciliation_instructions') }}:
                                <ol class="mb-0 mt-2">
                                    <li>{{ __('select_bank_account_and_statement_date') }}</li>
                                    <li>{{ __('enter_balances_from_bank_statement') }}</li>
                                    <li>{{ __('system_will_load_outstanding_transactions') }}</li>
                                </ol>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="bank_account_id">{{ __('bank_account') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('bank_account_id') is-invalid @enderror" 
                                                id="bank_account_id" name="bank_account_id" required>
                                            <option value="">{{ __('select_bank_account') }}</option>
                                            @foreach($bankAccounts as $account)
                                            <option value="{{ $account->id }}" {{ old('bank_account_id') == $account->id ? 'selected' : '' }}
                                                    data-balance="{{ $account->current_balance }}">
                                                {{ $account->account_code }} - {{ $account->account_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('bank_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="statement_date">{{ __('statement_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('statement_date') is-invalid @enderror" 
                                               id="statement_date" name="statement_date" value="{{ old('statement_date', date('Y-m-d')) }}" required>
                                        @error('statement_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="fiscal_year_id">{{ __('fiscal_year') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('fiscal_year_id') is-invalid @enderror" 
                                                id="fiscal_year_id" name="fiscal_year_id" required>
                                            @foreach($fiscalYears as $year)
                                            <option value="{{ $year->id }}" {{ ($activeFiscalYear && $activeFiscalYear->id == $year->id) || old('fiscal_year_id') == $year->id ? 'selected' : '' }}>
                                                {{ $year->name }} @if($year->is_active) ({{ __('active') }}) @endif
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('fiscal_year_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h5>{{ __('bank_statement_balances') }}</h5>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="statement_beginning_balance">{{ __('statement_beginning_balance') }} (FCFA) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control @error('statement_beginning_balance') is-invalid @enderror" 
                                               id="statement_beginning_balance" name="statement_beginning_balance" value="{{ old('statement_beginning_balance', 0) }}" required step="1">
                                        @error('statement_beginning_balance')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="statement_ending_balance">{{ __('statement_ending_balance') }} (FCFA) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control @error('statement_ending_balance') is-invalid @enderror" 
                                               id="statement_ending_balance" name="statement_ending_balance" value="{{ old('statement_ending_balance', 0) }}" required step="1">
                                        @error('statement_ending_balance')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="book_balance">{{ __('book_balance') }} (FCFA)</label>
                                        <input type="number" class="form-control" id="book_balance" name="book_balance" value="{{ old('book_balance', 0) }}" readonly>
                                        <small class="text-muted">{{ __('auto_calculated_from_gl') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="notes">{{ __('notes') }}</label>
                                        <textarea class="form-control @error('notes') is-invalid @enderror" 
                                                  id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                                        @error('notes')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('create_and_start_reconciliation') }}
                            </button>
                            <a href="{{ route('admin.bank-reconciliation.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> {{ __('cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Update book balance when bank account changes
        $('#bank_account_id').change(function() {
            var selectedOption = $(this).find(':selected');
            var balance = selectedOption.data('balance') || 0;
            $('#book_balance').val(balance);
        });
    });
</script>
@endpush
