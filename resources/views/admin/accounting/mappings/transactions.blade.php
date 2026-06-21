@extends('admin.layouts.master')
@section('title', 'Transaction Mappings')

@section('content')
<style>
    .transaction-row {
        border-left: 4px solid transparent;
        transition: all 0.2s;
    }
    .transaction-row.fee {
        border-left-color: #007bff;
    }
    .transaction-row.income {
        border-left-color: #28a745;
    }
    .transaction-row.expense {
        border-left-color: #dc3545;
    }
    .transaction-row.payroll {
        border-left-color: #ffc107;
    }
    .transaction-row.payment_plan_payment {
        border-left-color: #17a2b8;
    }
    .transaction-row:hover {
        background-color: #f8f9fa;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .status-badge {
        min-width: 90px;
    }
    
    /* Select2 Dropdown Styling */
    .select2-container--bootstrap4 .select2-results__options {
        max-height: 300px !important;
        overflow-y: auto !important;
    }
    
    .select2-container--bootstrap4 .select2-selection {
        min-height: 38px !important;
        border: 1px solid #ced4da !important;
    }
    
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        color: #495057 !important;
    }
    
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__placeholder {
        color: #6c757d !important;
    }
    
    .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field {
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
        padding: 8px !important;
    }
    
    .select2-container--bootstrap4 .select2-results__option {
        padding: 8px 12px !important;
    }
    
    .select2-container--bootstrap4 .select2-results__option--highlighted {
        background-color: #007bff !important;
        color: white !important;
    }
    
    /* Stats Cards Styling */
    .small-box {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    
    .small-box .inner {
        padding: 15px;
        position: relative;
        z-index: 5;
    }
    
    .small-box .inner h3 {
        font-size: 2.2rem;
        font-weight: bold;
        margin: 0 0 10px 0;
        color: #ffffff !important;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    }
    
    .small-box .inner p {
        font-size: 1rem;
        margin: 0;
        color: #ffffff !important;
        font-weight: 500;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    }
    
    .small-box .icon {
        font-size: 4rem;
        position: absolute;
        right: 15px;
        top: 15px;
        color: rgba(255,255,255,0.3);
        z-index: 1;
    }
    
    .small-box.bg-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
    }
    
    .small-box.bg-success {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important;
    }
    
    .small-box.bg-warning {
        background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%) !important;
    }
    
    .small-box.bg-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
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
                                <h3 class="card-title mb-0">{{ __('transaction_mappings') }}</h3>
                                <small class="text-muted">{{ __('map_transactions_to_accounts') }}</small>
                            </div>
                            <div>
                                @can('transaction-mapping-settings')
                                <a href="{{ route('admin.accounting.mappings.settings') }}" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-cog"></i> {{ __('mapping_settings') }}
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <form method="GET" action="{{ route('admin.accounting.mappings.transactions') }}" class="form-inline">
                                    <div class="form-group mr-2">
                                        <label class="mr-2">{{ __('type') }}:</label>
                                        <select name="type" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <option value="all" {{ request('type', 'all') == 'all' ? 'selected' : '' }}>{{ __('all') }}</option>
                                            <option value="fee" {{ request('type') == 'fee' ? 'selected' : '' }}>{{ __('fees') }}</option>
                                            <option value="income" {{ request('type') == 'income' ? 'selected' : '' }}>{{ __('income') }}</option>
                                            <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>{{ __('expense') }}</option>
                                            <option value="payroll" {{ request('type') == 'payroll' ? 'selected' : '' }}>{{ __('payroll') }}</option>
                                            <option value="payment_plan_payment" {{ request('type') == 'payment_plan_payment' ? 'selected' : '' }}>{{ __('payment_plans') }}</option>
                                        </select>
                                    </div>
                                    <div class="form-group mr-2">
                                        <label class="mr-2">{{ __('status') }}:</label>
                                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>{{ __('all') }}</option>
                                            <option value="mapped" {{ request('status') == 'mapped' ? 'selected' : '' }}>{{ __('mapped') }}</option>
                                            <option value="unmapped" {{ request('status') == 'unmapped' ? 'selected' : '' }}>{{ __('unmapped') }}</option>
                                        </select>
                                    </div>
                                    <div class="form-group mr-2">
                                        <label class="mr-2">{{ __('from') }}:</label>
                                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                                    </div>
                                    <div class="form-group mr-2">
                                        <label class="mr-2">{{ __('to') }}:</label>
                                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-filter"></i> {{ __('filter') }}
                                    </button>
                                    <a href="{{ route('admin.accounting.mappings.transactions') }}" class="btn btn-secondary btn-sm ml-2">
                                        <i class="fas fa-redo"></i> {{ __('reset') }}
                                    </a>
                                </form>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3>{{ $totalRecords }}</h3>
                                        <p>{{ __('total_transactions') }}</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-list"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3>{{ collect($paginatedTransactions)->where('is_mapped', true)->count() }}</h3>
                                        <p>{{ __('mapped') }}</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box bg-warning">
                                    <div class="inner">
                                        <h3>{{ collect($paginatedTransactions)->where('is_mapped', false)->count() }}</h3>
                                        <p>{{ __('unmapped') }}</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small-box bg-primary">
                                    <div class="inner">
                                        <h3>{{ number_format(collect($paginatedTransactions)->sum('amount'), 0, '.', ',') }}</h3>
                                        <p>{{ __('total_amount') }} FCFA</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transactions Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="3%">#</th>
                                        <th width="10%">{{ __('date') }}</th>
                                        <th width="10%">{{ __('type') }}</th>
                                        <th width="25%">{{ __('description') }}</th>
                                        <th width="22%">{{ __('reference') }}</th>
                                        <th width="12%" class="text-right">{{ __('amount') }}</th>
                                        <th width="8%" class="text-center">{{ __('status') }}</th>
                                        <th width="10%" class="text-center">{{ __('actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($paginatedTransactions as $index => $transaction)
                                    <tr class="transaction-row {{ $transaction['type'] }}">
                                        <td>{{ ($currentPage - 1) * $perPage + $loop->iteration }}</td>
                                        <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $transaction['type'] == 'fee' ? 'primary' : (
                                                $transaction['type'] == 'income' ? 'success' : (
                                                $transaction['type'] == 'expense' ? 'danger' : 'warning'))
                                            }}">
                                                {{ $transaction['type_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $transaction['description'] }}</td>
                                        <td><small class="text-muted">{{ $transaction['reference'] }}</small></td>
                                        <td class="text-right font-weight-bold">{{ number_format($transaction['amount'], 0, '.', ',') }} FCFA</td>
                                        <td class="text-center">
                                            @if($transaction['is_mapped'])
                                                <span class="badge badge-success status-badge">
                                                    <i class="fas fa-check-circle"></i> {{ __('mapped') }}
                                                </span>
                                            @else
                                                <span class="badge badge-warning status-badge">
                                                    <i class="fas fa-exclamation-circle"></i> {{ __('unmapped') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @can('transaction-mapping-manage')
                                            @if($transaction['is_mapped'])
                                                <button class="btn btn-sm btn-warning edit-mapping-btn" 
                                                        data-transaction-type="{{ $transaction['type'] }}"
                                                        data-transaction-id="{{ $transaction['id'] }}"
                                                        data-mapping-id="{{ $transaction['mapping']->id }}"
                                                        data-debit-account="{{ $transaction['mapping']->debit_account_id }}"
                                                        data-credit-account="{{ $transaction['mapping']->credit_account_id }}"
                                                        data-description="{{ $transaction['description'] }}"
                                                        data-amount="{{ $transaction['amount'] }}"
                                                        data-date="{{ $transaction['date'] }}"
                                                        data-journal-entry="{{ $transaction['mapping']->journal_entry_id }}">
                                                    <i class="fas fa-edit"></i> {{ __('edit') }}
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-primary map-transaction-btn" 
                                                        data-transaction-type="{{ $transaction['type'] }}"
                                                        data-transaction-id="{{ $transaction['id'] }}"
                                                        data-description="{{ $transaction['description'] }}"
                                                        data-amount="{{ $transaction['amount'] }}"
                                                        data-date="{{ $transaction['date'] }}">
                                                    <i class="fas fa-link"></i> {{ __('map') }}
                                                </button>
                                                <button class="btn btn-sm btn-success auto-map-btn ml-1" 
                                                        data-transaction-type="{{ $transaction['type'] }}"
                                                        data-transaction-id="{{ $transaction['id'] }}"
                                                        title="{{ __('auto_map_using_defaults') }}">
                                                    <i class="fas fa-magic"></i>
                                                </button>
                                            @endif
                                            @endcan
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>{{ __('no_transactions_found') }}</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($totalPages > 1)
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p class="text-muted">
                                    {{ __('showing') }} {{ ($currentPage - 1) * $perPage + 1 }} {{ __('to') }} 
                                    {{ min($currentPage * $perPage, $totalRecords) }} {{ __('of') }} 
                                    {{ $totalRecords }} {{ __('transactions') }}
                                </p>
                            </div>
                            <div class="col-md-6">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mb-0">
                                        {{-- Previous Page Link --}}
                                        @if($currentPage > 1)
                                            <li class="page-item">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $currentPage - 1]) }}" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        @else
                                            <li class="page-item disabled">
                                                <span class="page-link">&laquo;</span>
                                            </li>
                                        @endif

                                        {{-- Page Numbers --}}
                                        @php
                                            $start = max(1, $currentPage - 2);
                                            $end = min($totalPages, $currentPage + 2);
                                        @endphp

                                        @if($start > 1)
                                            <li class="page-item">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => 1]) }}">1</a>
                                            </li>
                                            @if($start > 2)
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            @endif
                                        @endif

                                        @for($i = $start; $i <= $end; $i++)
                                            <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a>
                                            </li>
                                        @endfor

                                        @if($end < $totalPages)
                                            @if($end < $totalPages - 1)
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            @endif
                                            <li class="page-item">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $totalPages]) }}">{{ $totalPages }}</a>
                                            </li>
                                        @endif

                                        {{-- Next Page Link --}}
                                        @if($currentPage < $totalPages)
                                            <li class="page-item">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $currentPage + 1]) }}" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        @else
                                            <li class="page-item disabled">
                                                <span class="page-link">&raquo;</span>
                                            </li>
                                        @endif
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mapping Modal -->
<div class="modal fade" id="mappingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">{{ __('map_transaction') }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="mappingForm" onsubmit="return false;">
                    <input type="hidden" id="transaction_type" name="transaction_type">
                    <input type="hidden" id="transaction_id" name="transaction_id">
                    <input type="hidden" id="mapping_id" name="mapping_id">

                    <!-- Transaction Info -->
                    <div class="alert alert-info">
                        <h6 class="mb-2"><strong>{{ __('transaction_details') }}:</strong></h6>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">{{ __('date') }}:</small><br>
                                <strong id="transactionDate"></strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ __('type') }}:</small><br>
                                <strong id="transactionType"></strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">{{ __('amount') }}:</small><br>
                                <strong id="transactionAmount"></strong>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">{{ __('description') }}:</small><br>
                            <strong id="transactionDescription"></strong>
                        </div>
                        <div class="mt-2" id="journalEntryInfo" style="display: none;">
                            <small class="text-muted">{{ __('journal_entry') }}:</small><br>
                            <a href="#" id="journalEntryLink" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-external-link-alt"></i> {{ __('view_journal_entry') }}
                            </a>
                        </div>
                    </div>

                    <!-- Account Mapping -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="debit_account_id">
                                    <i class="fas fa-arrow-up text-success"></i> {{ __('debit_account') }} *
                                    <small class="text-muted">({{ __('payment_account') }})</small>
                                </label>
                                <select class="form-control" id="debit_account_id" name="debit_account_id" required>
                                    <option value="">{{ __('select_account') }}</option>
                                    @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">
                                        {{ $account->account_code }} - {{ $account->account_name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fas fa-search"></i> {{ __('type_to_search_accounts') }}
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="credit_account_id">
                                    <i class="fas fa-arrow-down text-danger"></i> {{ __('credit_account') }} *
                                    <small class="text-muted">({{ __('deposit_to') }})</small>
                                </label>
                                <select class="form-control" id="credit_account_id" name="credit_account_id" required>
                                    <option value="">{{ __('select_account') }}</option>
                                    @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">
                                        {{ $account->account_code }} - {{ $account->account_name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fas fa-search"></i> {{ __('type_to_search_accounts') }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mapping_description">{{ __('description') }} <small class="text-muted">({{ __('optional') }})</small></label>
                        <textarea class="form-control" id="mapping_description" name="description" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> {{ __('cancel') }}
                </button>
                <button type="button" class="btn btn-primary" id="saveMappingBtn">
                    <i class="fas fa-save"></i> <span id="saveBtnText">{{ __('save_mapping') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Fix modal close issues
    $('#mappingModal').on('hidden.bs.modal', function (e) {
        // Remove any leftover backdrops
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    });

    // Ensure modal can be closed with escape key
    $(document).on('keydown', function(e) {
        if (e.key === "Escape" && $('#mappingModal').hasClass('show')) {
            $('#mappingModal').modal('hide');
        }
    });

    // Initialize Select2 for account dropdowns
    $('#debit_account_id, #credit_account_id').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#mappingModal'),
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

    let isEditMode = false;

    // Close button handlers
    $('[data-dismiss="modal"]').on('click', function() {
        $('#mappingModal').modal('hide');
    });

    // Map Transaction Button
    $('.map-transaction-btn').click(function() {
        isEditMode = false;
        $('#modalTitle').text('{{ __("map_transaction") }}');
        $('#saveBtnText').text('{{ __("save_mapping") }}');
        $('#journalEntryInfo').hide();
        
        const type = $(this).data('transaction-type');
        const id = $(this).data('transaction-id');
        const description = $(this).data('description');
        const amount = $(this).data('amount');
        const date = $(this).data('date');

        $('#transaction_type').val(type);
        $('#transaction_id').val(id);
        $('#mapping_id').val('');
        $('#transactionDate').text(moment(date).format('DD MMM YYYY'));
        $('#transactionType').text(type.charAt(0).toUpperCase() + type.slice(1));
        $('#transactionAmount').text(parseFloat(amount).toLocaleString() + ' FCFA');
        $('#transactionDescription').text(description);
        $('#mapping_description').val(description);
        $('#debit_account_id').val('').trigger('change');
        $('#credit_account_id').val('').trigger('change');

        $('#mappingModal').modal('show');
    });

    // Edit Mapping Button
    $('.edit-mapping-btn').click(function() {
        isEditMode = true;
        $('#modalTitle').text('{{ __("edit_mapping") }}');
        $('#saveBtnText').text('{{ __("update_mapping") }}');
        
        const type = $(this).data('transaction-type');
        const id = $(this).data('transaction-id');
        const mappingId = $(this).data('mapping-id');
        const description = $(this).data('description');
        const amount = $(this).data('amount');
        const date = $(this).data('date');
        const debitAccount = $(this).data('debit-account');
        const creditAccount = $(this).data('credit-account');
        const journalEntry = $(this).data('journal-entry');

        $('#transaction_type').val(type);
        $('#transaction_id').val(id);
        $('#mapping_id').val(mappingId);
        $('#transactionDate').text(moment(date).format('DD MMM YYYY'));
        $('#transactionType').text(type.charAt(0).toUpperCase() + type.slice(1));
        $('#transactionAmount').text(parseFloat(amount).toLocaleString() + ' FCFA');
        $('#transactionDescription').text(description);
        $('#mapping_description').val(description);
        $('#debit_account_id').val(debitAccount).trigger('change');
        $('#credit_account_id').val(creditAccount).trigger('change');

        if (journalEntry) {
            $('#journalEntryLink').attr('href', '/admin/journal-entries/' + journalEntry);
            $('#journalEntryInfo').show();
        } else {
            $('#journalEntryInfo').hide();
        }

        $('#mappingModal').modal('show');
    });

    // Save Mapping
    $('#saveMappingBtn').click(function() {
        const btn = $(this);
        const originalHtml = btn.html();

        // Validate
        if (!$('#debit_account_id').val() || !$('#credit_account_id').val()) {
            Swal.fire({
                icon: 'warning',
                title: '{{ __("validation_error") }}',
                text: '{{ __("please_select_both_accounts") }}'
            });
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ __("saving") }}...');

        const url = isEditMode 
            ? '{{ url("admin/accounting/mappings") }}/' + $('#mapping_id').val()
            : '{{ route("admin.accounting.mappings.map-transaction") }}';

        const method = isEditMode ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: {
                _token: '{{ csrf_token() }}',
                transaction_type: $('#transaction_type').val(),
                transaction_id: $('#transaction_id').val(),
                debit_account_id: $('#debit_account_id').val(),
                credit_account_id: $('#credit_account_id').val(),
                description: $('#mapping_description').val()
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

    // Auto Map Button
    $('.auto-map-btn').click(function() {
        const btn = $(this);
        const type = $(this).data('transaction-type');
        const id = $(this).data('transaction-id');

        Swal.fire({
            title: '{{ __("auto_map_transaction") }}?',
            text: '{{ __("this_will_use_default_mapping") }}',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '{{ __("yes_auto_map") }}',
            cancelButtonText: '{{ __("cancel") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: '{{ route("admin.accounting.mappings.auto-map") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        transaction_type: type,
                        transaction_id: id
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
                            btn.prop('disabled', false).html('<i class="fas fa-magic"></i>');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("error") }}',
                            text: xhr.responseJSON?.message || '{{ __("an_error_occurred") }}'
                        });
                        btn.prop('disabled', false).html('<i class="fas fa-magic"></i>');
                    }
                });
            }
        });
    });
});
</script>
@endsection

@endsection
