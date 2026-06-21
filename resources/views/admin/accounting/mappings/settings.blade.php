@extends('admin.layouts.master')
@section('title', 'Account Mapping Settings')

@section('content')
<style>
    .mapping-card {
        border-left: 4px solid #007bff;
        margin-bottom: 20px;
    }
    .mapping-card .card-header {
        color: #ffffff !important;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    }
    .mapping-card .card-header h5 {
        color: #ffffff !important;
        margin: 0;
    }
    .mapping-row {
        padding: 10px 15px;
        border-bottom: 1px solid #e9ecef;
    }
    .mapping-row:last-child {
        border-bottom: none;
    }
    .mapping-row:hover {
        background-color: #f8f9fa;
    }
    .account-select {
        width: 100%;
    }
    .category-name {
        font-weight: 500;
        display: flex;
        align-items: center;
    }
    
    /* Select2 Dropdown Styling */
    .select2-container--bootstrap4 .select2-results__options {
        max-height: 300px !important;
        overflow-y: auto !important;
    }
    
    .select2-container--bootstrap4 .select2-selection {
        min-height: 31px !important;
        border: 1px solid #ced4da !important;
    }
    
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 29px !important;
        color: #495057 !important;
        padding-left: 8px !important;
    }
    
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__placeholder {
        color: #6c757d !important;
    }
    
    .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field {
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
        padding: 6px !important;
    }
    
    .select2-container--bootstrap4 .select2-results__option {
        padding: 6px 10px !important;
        font-size: 0.9rem;
    }
    
    .select2-container--bootstrap4 .select2-results__option--highlighted {
        background-color: #007bff !important;
        color: white !important;
    }
    
    .select2-container {
        width: 100% !important;
    }
</style>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="card-title mb-0">{{ __('account_mapping_settings') }}</h3>
                                <small class="text-muted">{{ __('configure_default_account_mappings') }}</small>
                            </div>
                            <div>
                                <a href="{{ route('admin.accounting.mappings.transactions') }}" class="btn btn-info btn-sm">
                                    <i class="fas fa-list"></i> {{ __('view_transactions') }}
                                </a>
                                @can('transaction-mapping-settings')
                                <button type="button" id="saveAllMappings" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save"></i> {{ __('save_all_mappings') }}
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Instructions -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>{{ __('instructions') }}:</strong>
                            {{ __('configure_default_debit_credit_accounts') }}
                        </div>

                        <form id="mappingsForm">
                            @csrf

                            <!-- Fee Categories Mapping -->
                            <div class="card mapping-card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-money-bill-wave"></i> {{ __('student_fee_categories') }}
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    @forelse($feeCategories as $category)
                                    <div class="mapping-row" data-row-id="fee_{{ $category->id }}">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-primary mr-2">{{ $loop->iteration }}</span>
                                                    {{ $category->title }}
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Payment From)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="fee_{{ $category->id }}">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['fee_category']) && 
                                                            $existingMappings['fee_category']->where('category_id', $category->id)->first() && 
                                                            $existingMappings['fee_category']->where('category_id', $category->id)->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Revenue Account)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="fee_{{ $category->id }}">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['fee_category']) && 
                                                            $existingMappings['fee_category']->where('category_id', $category->id)->first() && 
                                                            $existingMappings['fee_category']->where('category_id', $category->id)->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="fee_category" 
                                                        data-category-id="{{ $category->id }}"
                                                        data-row-id="fee_{{ $category->id }}">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['fee_category']) && $existingMappings['fee_category']->where('category_id', $category->id)->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    @empty
                                    <div class="p-3 text-center text-muted">
                                        {{ __('no_fee_categories_found') }}
                                    </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Income Categories Mapping -->
                            <div class="card mapping-card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-hand-holding-usd"></i> {{ __('income_categories') }}
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    @forelse($incomeCategories as $category)
                                    <div class="mapping-row" data-row-id="income_{{ $category->id }}">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-success mr-2">{{ $loop->iteration }}</span>
                                                    {{ $category->title }}
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Payment From)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="income_{{ $category->id }}">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['income_category']) && 
                                                            $existingMappings['income_category']->where('category_id', $category->id)->first() && 
                                                            $existingMappings['income_category']->where('category_id', $category->id)->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Revenue Account)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="income_{{ $category->id }}">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['income_category']) && 
                                                            $existingMappings['income_category']->where('category_id', $category->id)->first() && 
                                                            $existingMappings['income_category']->where('category_id', $category->id)->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="income_category" 
                                                        data-category-id="{{ $category->id }}"
                                                        data-row-id="income_{{ $category->id }}">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['income_category']) && $existingMappings['income_category']->where('category_id', $category->id)->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    @empty
                                    <div class="p-3 text-center text-muted">
                                        {{ __('no_income_categories_found') }}
                                    </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Expense Categories Mapping -->
                            <div class="card mapping-card">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-receipt"></i> {{ __('expense_categories') }}
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    @forelse($expenseCategories as $category)
                                    <div class="mapping-row" data-row-id="expense_{{ $category->id }}">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-danger mr-2">{{ $loop->iteration }}</span>
                                                    {{ $category->title }}
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Expense Account)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="expense_{{ $category->id }}">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['expense_category']) && 
                                                            $existingMappings['expense_category']->where('category_id', $category->id)->first() && 
                                                            $existingMappings['expense_category']->where('category_id', $category->id)->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Payment From)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="expense_{{ $category->id }}">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['expense_category']) && 
                                                            $existingMappings['expense_category']->where('category_id', $category->id)->first() && 
                                                            $existingMappings['expense_category']->where('category_id', $category->id)->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="expense_category" 
                                                        data-category-id="{{ $category->id }}"
                                                        data-row-id="expense_{{ $category->id }}">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['expense_category']) && $existingMappings['expense_category']->where('category_id', $category->id)->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    @empty
                                    <div class="p-3 text-center text-muted">
                                        {{ __('no_expense_categories_found') }}
                                    </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Payroll Mapping -->
                            <div class="card mapping-card">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">
                                        <i class="fas fa-users"></i> {{ __('payroll') }}
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <!-- Main Payroll Entry (Basic/Gross Salary) -->
                                    <div class="mapping-row" data-row-id="payroll">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-warning mr-2">1</span>
                                                    {{ __('Gross Salary Expense') }}
                                                    <small class="d-block text-muted">Main salary entry</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Salary Expense - Class 6)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="payroll">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll']) && 
                                                            $existingMappings['payroll']->first() && 
                                                            $existingMappings['payroll']->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Bank/Cash - Class 5)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="payroll">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll']) && 
                                                            $existingMappings['payroll']->first() && 
                                                            $existingMappings['payroll']->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="payroll" 
                                                        data-category-id=""
                                                        data-row-id="payroll">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['payroll']) && $existingMappings['payroll']->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tax Withholding Entry -->
                                    <div class="mapping-row" data-row-id="payroll_tax">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-warning mr-2">2</span>
                                                    {{ __('Tax Withholding') }}
                                                    <small class="d-block text-muted">IRPP/Income Tax</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Staff Payable - Class 4)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="payroll_tax">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_tax']) && 
                                                            $existingMappings['payroll_tax']->first() && 
                                                            $existingMappings['payroll_tax']->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Tax Payable - Class 4)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="payroll_tax">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_tax']) && 
                                                            $existingMappings['payroll_tax']->first() && 
                                                            $existingMappings['payroll_tax']->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="payroll_tax" 
                                                        data-category-id=""
                                                        data-row-id="payroll_tax">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['payroll_tax']) && $existingMappings['payroll_tax']->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Staff Payable (Net Salary Due) -->
                                    <div class="mapping-row" data-row-id="payroll_staff_payable">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-warning mr-2">3</span>
                                                    {{ __('Staff Payable') }}
                                                    <small class="d-block text-muted">Net Salary Due</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Salary Expense - Class 6)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="payroll_staff_payable">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_staff_payable']) && 
                                                            $existingMappings['payroll_staff_payable']->first() && 
                                                            $existingMappings['payroll_staff_payable']->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Staff Payable - Class 4)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="payroll_staff_payable">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_staff_payable']) && 
                                                            $existingMappings['payroll_staff_payable']->first() && 
                                                            $existingMappings['payroll_staff_payable']->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="payroll_staff_payable" 
                                                        data-category-id=""
                                                        data-row-id="payroll_staff_payable">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['payroll_staff_payable']) && $existingMappings['payroll_staff_payable']->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Allowances -->
                                    <div class="mapping-row" data-row-id="payroll_allowance">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-warning mr-2">4</span>
                                                    {{ __('Allowances') }}
                                                    <small class="d-block text-muted">All allowance types</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Allowance Expense - Class 6)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="payroll_allowance">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_allowance']) && 
                                                            $existingMappings['payroll_allowance']->first() && 
                                                            $existingMappings['payroll_allowance']->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Staff Payable - Class 4)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="payroll_allowance">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_allowance']) && 
                                                            $existingMappings['payroll_allowance']->first() && 
                                                            $existingMappings['payroll_allowance']->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="payroll_allowance" 
                                                        data-category-id=""
                                                        data-row-id="payroll_allowance">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['payroll_allowance']) && $existingMappings['payroll_allowance']->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Deductions -->
                                    <div class="mapping-row" data-row-id="payroll_deduction">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="category-name">
                                                    <span class="badge badge-warning mr-2">5</span>
                                                    {{ __('Deductions') }}
                                                    <small class="d-block text-muted">All deduction types</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('debit_account') }} (Staff Payable - Class 4)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-debit-select="payroll_deduction">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_deduction']) && 
                                                            $existingMappings['payroll_deduction']->first() && 
                                                            $existingMappings['payroll_deduction']->first()->debit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1 small text-muted">{{ __('credit_account') }} (Deduction Payable - Class 4)</label>
                                                <select class="form-control form-control-sm account-select" 
                                                        data-credit-select="payroll_deduction">
                                                    <option value="">{{ __('select_account') }}</option>
                                                    @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}"
                                                        @if(isset($existingMappings['payroll_deduction']) && 
                                                            $existingMappings['payroll_deduction']->first() && 
                                                            $existingMappings['payroll_deduction']->first()->credit_account_id == $account->id) 
                                                            selected 
                                                        @endif>
                                                        {{ $account->account_code }} - {{ $account->account_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <button type="button" class="btn btn-sm btn-success save-single-mapping" 
                                                        data-mapping-type="payroll_deduction" 
                                                        data-category-id=""
                                                        data-row-id="payroll_deduction">
                                                    <i class="fas fa-save"></i> {{ __('save') }}
                                                </button>
                                                <br>
                                                <small class="mt-1">
                                                    @if(isset($existingMappings['payroll_deduction']) && $existingMappings['payroll_deduction']->first())
                                                    <i class="fas fa-check-circle text-success" title="{{ __('configured') }}"></i>
                                                    @else
                                                    <i class="fas fa-exclamation-circle text-warning" title="{{ __('not_configured') }}"></i>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Help text for payroll -->
                                    <div class="p-3 bg-light">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Payroll Accounting Guide:</strong><br>
                                            • <strong>Gross Salary:</strong> DR Salary Expense (661), CR Bank/Cash (521) for net payment<br>
                                            • <strong>Tax Withholding:</strong> DR Staff Payable (421), CR Tax Payable (4424) for tax withheld<br>
                                            • <strong>Allowances:</strong> DR Allowance Expense (6412), CR Staff Payable (421)<br>
                                            • <strong>Deductions:</strong> DR Staff Payable (421), CR Deduction Payable (431)
                                        </small>
                                    </div>
                                </div>
                            </div>

                        </form>

                        @can('transaction-mapping-settings')
                        <div class="mt-3 text-center">
                            <button type="button" id="saveAllMappingsBottom" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> {{ __('save_all_mappings') }}
                            </button>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@section('scripts')
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initialize Select2 for better account selection with search and scrolling
    $('.account-select').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Select an account',
        allowClear: true,
        minimumResultsForSearch: 0, // Always show search box
        language: {
            noResults: function() {
                return "No accounts found";
            },
            searching: function() {
                return "Searching...";
            }
        }
    });

    // Save all mappings
    $('#saveAllMappings, #saveAllMappingsBottom').click(function() {
        const btn = $(this);
        const originalHtml = btn.html();
        
        // Validate form
        let isValid = true;
        $('.account-select[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        if (!isValid) {
            Swal.fire({
                icon: 'warning',
                title: '{{ __("validation_error") }}',
                text: '{{ __("please_fill_all_required_fields") }}'
            });
            return;
        }

        // Disable button and show loading
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ __("saving") }}...');

        // Prepare data
        const formData = $('#mappingsForm').serializeArray();
        const mappings = [];

        // Group mappings
        const groupedMappings = {};
        formData.forEach(item => {
            const match = item.name.match(/mappings\[([^\]]+)\]\[([^\]]+)\]/);
            if (match) {
                const key = match[1];
                const field = match[2];
                if (!groupedMappings[key]) {
                    groupedMappings[key] = {};
                }
                groupedMappings[key][field] = item.value;
            }
        });

        // Convert to array
        Object.values(groupedMappings).forEach(mapping => {
            if (mapping.debit_account_id && mapping.credit_account_id) {
                mappings.push(mapping);
            }
        });

        // Send AJAX request
        $.ajax({
            url: '{{ route("admin.accounting.mappings.save-default") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                mappings: mappings
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '{{ __("success") }}',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __("error") }}',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("error") }}',
                    text: xhr.responseJSON?.message || '{{ __("an_error_occurred") }}'
                });
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Save single mapping
    $('.save-single-mapping').click(function() {
        const btn = $(this);
        const mappingType = btn.data('mapping-type');
        const categoryId = btn.data('category-id');
        const rowId = btn.data('row-id');
        
        const debitSelect = $('[data-debit-select="' + rowId + '"]');
        const creditSelect = $('[data-credit-select="' + rowId + '"]');
        const debitAccountId = debitSelect.val();
        const creditAccountId = creditSelect.val();
        
        // Validate both accounts are selected
        if (!debitAccountId || !creditAccountId) {
            debitSelect.toggleClass('is-invalid', !debitAccountId);
            creditSelect.toggleClass('is-invalid', !creditAccountId);
            
            Swal.fire({
                icon: 'warning',
                title: '{{ __("validation_error") }}',
                text: '{{ __("please_select_both_debit_and_credit_accounts") }}'
            });
            return;
        }
        
        // Clear validation styling
        debitSelect.removeClass('is-invalid');
        creditSelect.removeClass('is-invalid');
        
        // Disable button and show loading
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ __("saving") }}...');
        
        // Send AJAX request
        $.ajax({
            url: '{{ route("admin.accounting.mappings.save-default") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                mappings: [{
                    mapping_type: mappingType,
                    category_id: categoryId || null,
                    debit_account_id: debitAccountId,
                    credit_account_id: creditAccountId
                }]
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '{{ __("success") }}',
                        text: '{{ __("mapping_saved_successfully") }}',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    // Update status icon to show configured
                    const statusIcon = $('[data-row-id="' + rowId + '"]').find('small i');
                    statusIcon.removeClass('fa-exclamation-circle text-warning')
                              .addClass('fa-check-circle text-success')
                              .attr('title', '{{ __("configured") }}');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __("error") }}',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("error") }}',
                    text: xhr.responseJSON?.message || '{{ __("an_error_occurred") }}'
                });
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
});
</script>
@endsection

@endsection
