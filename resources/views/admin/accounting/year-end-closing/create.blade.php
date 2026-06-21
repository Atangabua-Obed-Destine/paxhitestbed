@extends('admin.layouts.master')

@section('title', __('Create Year-End Closing'))

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ __('Create Year-End Closing') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.year-end-closing.index') }}">{{ __('Year-End Closing') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Create') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <form action="{{ route('admin.year-end-closing.store') }}" method="POST" id="yearEndForm">
                @csrf
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Fiscal Year Selection -->
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('Fiscal Year Information') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="fiscal_year_id">{{ __('Fiscal Year to Close') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('fiscal_year_id') is-invalid @enderror" 
                                            id="fiscal_year_id" name="fiscal_year_id" required>
                                        <option value="">{{ __('Select Fiscal Year') }}</option>
                                        @foreach($fiscalYears ?? [] as $fy)
                                            @if(!$fy->is_closed)
                                            <option value="{{ $fy->id }}" {{ old('fiscal_year_id') == $fy->id ? 'selected' : '' }}>
                                                {{ $fy->name }} ({{ $fy->start_date->format('Y-m-d') }} - {{ $fy->end_date->format('Y-m-d') }})
                                            </option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @error('fiscal_year_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="closing_date">{{ __('Closing Date') }} <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('closing_date') is-invalid @enderror" 
                                                   id="closing_date" name="closing_date" 
                                                   value="{{ old('closing_date') }}" required>
                                            @error('closing_date')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                            <small class="form-text text-muted">{{ __('Usually the last day of the fiscal year') }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Account Configuration -->
                        <div class="card card-outline card-warning">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('Closing Accounts (OHADA)') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    {{ __('Per OHADA standards, select the accounts for closing entries. Revenue and expense accounts will be closed to the income summary, then to retained earnings.') }}
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="retained_earnings_account_id">
                                                {{ __('Retained Earnings Account') }} <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control select2 @error('retained_earnings_account_id') is-invalid @enderror" 
                                                    id="retained_earnings_account_id" name="retained_earnings_account_id" required>
                                                <option value="">{{ __('Select Account') }}</option>
                                                @foreach($retainedEarningsAccounts ?? [] as $account)
                                                    <option value="{{ $account->id }}" {{ old('retained_earnings_account_id') == $account->id ? 'selected' : '' }}>
                                                        {{ $account->code }} - {{ $account->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('retained_earnings_account_id')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                            <small class="form-text text-muted">{{ __('Usually account 12 - Retained Earnings') }}</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="income_summary_account_id">
                                                {{ __('Income Summary Account') }} <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control select2 @error('income_summary_account_id') is-invalid @enderror" 
                                                    id="income_summary_account_id" name="income_summary_account_id" required>
                                                <option value="">{{ __('Select Account') }}</option>
                                                @foreach($incomeSummaryAccounts ?? [] as $account)
                                                    <option value="{{ $account->id }}" {{ old('income_summary_account_id') == $account->id ? 'selected' : '' }}>
                                                        {{ $account->code }} - {{ $account->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('income_summary_account_id')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                            <small class="form-text text-muted">{{ __('Usually account 13 - Income Summary or similar') }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('Additional Notes') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="notes">{{ __('Notes') }}</label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" 
                                              id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Pre-Closing Checklist Info -->
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('Pre-Closing Checklist') }}</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">{{ __('Before proceeding with year-end closing, ensure the following tasks are completed:') }}</p>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-muted"></i>
                                        {{ __('All journal entries posted') }}
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-muted"></i>
                                        {{ __('Bank reconciliations completed') }}
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-muted"></i>
                                        {{ __('Depreciation entries posted') }}
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-muted"></i>
                                        {{ __('Accruals and deferrals recorded') }}
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-muted"></i>
                                        {{ __('Trial balance verified') }}
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-muted"></i>
                                        {{ __('Financial statements reviewed') }}
                                    </li>
                                </ul>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    {{ __('You can complete this checklist after creating the closing process.') }}
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="card">
                            <div class="card-body">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-plus"></i> {{ __('Create Closing Process') }}
                                </button>
                                <a href="{{ route('admin.year-end-closing.index') }}" class="btn btn-secondary btn-block">
                                    <i class="fas fa-times"></i> {{ __('Cancel') }}
                                </a>
                            </div>
                        </div>

                        <!-- Help -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('OHADA Year-End Process') }}</h3>
                            </div>
                            <div class="card-body">
                                <small class="text-muted">
                                    <p>{{ __('The OHADA year-end closing process involves:') }}</p>
                                    <ol>
                                        <li>{{ __('Closing all revenue accounts (Class 7) to income summary') }}</li>
                                        <li>{{ __('Closing all expense accounts (Class 6) to income summary') }}</li>
                                        <li>{{ __('Transferring net income/loss to retained earnings') }}</li>
                                        <li>{{ __('Opening balances for new fiscal year') }}</li>
                                    </ol>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        allowClear: true
    });

    // Auto-set closing date when fiscal year is selected
    $('#fiscal_year_id').change(function() {
        const fyId = $(this).val();
        if (fyId) {
            // Get the fiscal year end date from the option text
            const optionText = $(this).find('option:selected').text();
            const match = optionText.match(/(\d{4}-\d{2}-\d{2})\)$/);
            if (match) {
                $('#closing_date').val(match[1]);
            }
        }
    });
});
</script>
@endsection
