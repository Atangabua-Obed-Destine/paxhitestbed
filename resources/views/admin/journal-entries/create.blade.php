@extends('admin.layouts.master')
@section('title', __('add_journal_entry'))

@push('css')
<style>
    .border-warning {
        border: 2px solid #ffc107 !important;
        background-color: #fff3cd !important;
    }
    .border-success {
        border: 2px solid #28a745 !important;
        background-color: #d4edda !important;
    }
    .alert-info ul {
        padding-left: 20px;
    }
    .alert-info li {
        margin-bottom: 5px;
    }
</style>
@endpush

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('add_journal_entry') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    
                    <!-- Display Validation Errors -->
                    @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible mx-3 mt-3">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-ban"></i> {{ __('validation_errors') }}</h5>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <!-- Accounting Rules Help Box -->
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Accounting Entry Rules</h6>
                        <ul class="mb-0 small">
                            <li><strong>DEBIT accounts:</strong> Assets (Cash, Bank, Inventory, Fixed Assets, Receivables) - increase with debits</li>
                            <li><strong>CREDIT accounts:</strong> Liabilities, Equity, Revenue - increase with credits</li>
                            <li><strong>Expenses</strong> are DEBIT accounts - increase with debits</li>
                            <li>Each line must have EITHER a debit OR credit, not both</li>
                            <li>Total debits must equal total credits before posting</li>
                            <li><span class="badge badge-warning">Yellow border</span> = Contra entry (reduces normal balance) - <strong>requires confirmation</strong></li>
                            <li><span class="badge badge-success">Green border</span> = Normal entry (increases normal balance)</li>
                            <li><i class="fas fa-exclamation-triangle text-warning"></i> <strong>You will be asked to confirm</strong> when debiting a credit account or crediting a debit account</li>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    
                    <form action="{{ route('admin.journal-entries.store') }}" method="POST" id="journalEntryForm">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <!-- Entry Date -->
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="entry_date">{{ __('entry_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('entry_date') is-invalid @enderror" 
                                               id="entry_date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}" required>
                                        @error('entry_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Journal Type -->
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="journal_type">{{ __('journal_type') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('journal_type') is-invalid @enderror" 
                                                id="journal_type" name="journal_type" required>
                                            <option value="">{{ __('select_journal_type') }}</option>
                                            <option value="general" {{ old('journal_type') == 'general' ? 'selected' : '' }}>{{ __('general') }}</option>
                                            <option value="sales" {{ old('journal_type') == 'sales' ? 'selected' : '' }}>{{ __('sales') }}</option>
                                            <option value="purchase" {{ old('journal_type') == 'purchase' ? 'selected' : '' }}>{{ __('purchase') }}</option>
                                            <option value="cash" {{ old('journal_type') == 'cash' ? 'selected' : '' }}>{{ __('cash') }}</option>
                                            <option value="bank" {{ old('journal_type') == 'bank' ? 'selected' : '' }}>{{ __('bank') }}</option>
                                            <option value="adjustment" {{ old('journal_type') == 'adjustment' ? 'selected' : '' }}>{{ __('adjustment') }}</option>
                                        </select>
                                        @error('journal_type')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Reference Number -->
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="reference_number">{{ __('reference_number') }}</label>
                                        <input type="text" class="form-control @error('reference_number') is-invalid @enderror" 
                                               id="reference_number" name="reference_number" value="{{ old('reference_number') }}" 
                                               placeholder="{{ __('invoice_no') }}, {{ __('receipt_no') }}, etc.">
                                        @error('reference_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Fiscal Year (Auto-selected) -->
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fiscal_year_id">{{ __('fiscal_year') }}</label>
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

                                <!-- Description -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }} <span class="text-danger">*</span></label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="2" required>{{ old('description') }}</textarea>
                                        @error('description')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror>
                                    </div>
                                </div>
                            </div>

                            <!-- Journal Entry Lines -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <h5>{{ __('journal_entry_lines') }}</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="entry-lines-table">
                                            <thead>
                                                <tr>
                                                    <th width="5%">{{ __('line') }}</th>
                                                    <th width="30%">{{ __('account') }} <span class="text-danger">*</span></th>
                                                    <th width="30%">{{ __('description') }}</th>
                                                    <th width="12%">{{ __('debit') }}</th>
                                                    <th width="12%">{{ __('credit') }}</th>
                                                    <th width="8%">{{ __('action') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody id="entry-lines-body">
                                                <!-- Lines will be added here dynamically -->
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="text-right"><strong>{{ __('total') }}:</strong></td>
                                                    <td class="text-right">
                                                        <strong id="total-debit">0</strong> FCFA
                                                    </td>
                                                    <td class="text-right">
                                                        <strong id="total-credit">0</strong> FCFA
                                                    </td>
                                                    <td></td>
                                                </tr>
                                                <tr id="balance-row" style="display: none;">
                                                    <td colspan="6" class="text-center">
                                                        <span class="badge badge-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> 
                                                            {{ __('entry_not_balanced') }}: {{ __('difference') }} = <span id="difference">0</span> FCFA
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-success btn-sm" id="add-line-btn">
                                        <i class="fas fa-plus"></i> {{ __('add_line') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" id="save-btn">
                                <i class="fas fa-save"></i> {{ __('save_draft') }}
                            </button>
                            <button type="submit" class="btn btn-success" id="save-post-btn" name="post_immediately" value="1">
                                <i class="fas fa-check"></i> {{ __('save_and_post') }}
                            </button>
                            <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-secondary">
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
        let lineCounter = 0;
        
        // Parse accounts data
        let accounts = [];
        try {
            accounts = @json($accounts);
            console.log('Accounts loaded:', accounts.length);
        } catch (e) {
            console.error('Error parsing accounts:', e);
            alert('Error loading accounts data. Please refresh the page.');
            return;
        }

        // Add initial two lines
        addLine();
        addLine();

        // Attach events to initial lines
        attachLineEvents(1);
        attachLineEvents(2);

        // Add line button - use event delegation for better reliability
        $(document).on('click', '#add-line-btn', function(e) {
            e.preventDefault();
            console.log('Add line button clicked');
            addLine();
        });

        // Add new line function
        function addLine() {
            lineCounter++;
            console.log('Adding line:', lineCounter);
            
            let accountOptions = '<option value="">{{ __("select_account") }}</option>';
            accounts.forEach(account => {
                accountOptions += `<option value="${account.id}">${account.account_code} - ${account.account_name} [${account.normal_balance.toUpperCase()}]</option>`;
            });

            const newRow = `
                <tr class="entry-line" data-line="${lineCounter}">
                    <td class="text-center">${lineCounter}</td>
                    <td>
                        <select class="form-control form-control-sm account-select" name="lines[${lineCounter}][account_id]" required>
                            ${accountOptions}
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="lines[${lineCounter}][description]" placeholder="{{ __('line_description') }}">
                    </td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm debit-input" name="lines[${lineCounter}][debit]" value="0" min="0">
                    </td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm credit-input" name="lines[${lineCounter}][credit]" value="0" min="0">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-xs remove-line-btn" data-line="${lineCounter}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;

            $('#entry-lines-body').append(newRow);
            
            // Initialize Select2 for the new account dropdown if Select2 is available
            try {
                if (typeof $.fn.select2 !== 'undefined') {
                    $(`select[name="lines[${lineCounter}][account_id]"]`).select2({
                        theme: 'bootstrap4',
                        placeholder: '{{ __("select_account") }}',
                        width: '100%'
                    });
                } else {
                    console.warn('Select2 not loaded, using standard select');
                }
            } catch (e) {
                console.error('Error initializing Select2:', e);
            }

            // Attach event handlers
            attachLineEvents(lineCounter);
        }

        // Attach events to line inputs
        function attachLineEvents(lineNum) {
            const debitInput = $(`input[name="lines[${lineNum}][debit]"]`);
            const creditInput = $(`input[name="lines[${lineNum}][credit]"]`);
            const accountSelect = $(`select[name="lines[${lineNum}][account_id]"]`);

            // When debit changes, set credit to 0
            debitInput.on('input', function() {
                if (parseFloat($(this).val()) > 0) {
                    creditInput.val(0);
                    // Highlight the correct side
                    $(this).removeClass('border-warning').addClass('border-success');
                    creditInput.removeClass('border-success border-warning');
                }
                calculateTotals();
                checkAccountBalance(lineNum);
            });

            // When credit changes, set debit to 0
            creditInput.on('input', function() {
                if (parseFloat($(this).val()) > 0) {
                    debitInput.val(0);
                    // Highlight the correct side
                    $(this).removeClass('border-warning').addClass('border-success');
                    debitInput.removeClass('border-success border-warning');
                }
                calculateTotals();
                checkAccountBalance(lineNum);
            });

            // When account changes, check the normal balance
            accountSelect.on('change', function() {
                checkAccountBalance(lineNum);
            });

            // Remove line button
            $(`.remove-line-btn[data-line="${lineNum}"]`).click(function() {
                if ($('.entry-line').length > 2) {
                    $(this).closest('tr').remove();
                    renumberLines();
                    calculateTotals();
                } else {
                    alert('{{ __("minimum_two_lines_required") }}');
                }
            });
        }

        // Check if entry matches account's normal balance
        function checkAccountBalance(lineNum) {
            const accountSelect = $(`select[name="lines[${lineNum}][account_id]"]`);
            const debitInput = $(`input[name="lines[${lineNum}][debit]"]`);
            const creditInput = $(`input[name="lines[${lineNum}][credit]"]`);
            
            const selectedOption = accountSelect.find(':selected');
            const accountText = selectedOption.text();
            const accountId = accountSelect.val();
            
            if (!accountId) return;
            
            // Extract normal balance from option text
            const normalBalance = accountText.includes('[DEBIT]') ? 'debit' : 
                                  accountText.includes('[CREDIT]') ? 'credit' : null;
            
            if (!normalBalance) return;
            
            const debitVal = parseFloat(debitInput.val()) || 0;
            const creditVal = parseFloat(creditInput.val()) || 0;
            
            // Check if entry goes against normal balance (contra entry)
            if (normalBalance === 'debit' && creditVal > 0) {
                creditInput.addClass('border-warning').attr('title', 'Warning: This is a debit account. Credit entries reduce its balance (contra entry).');
                console.log('Warning: Credit entry on debit account');
                
                // Show confirmation dialog
                const accountName = accountText.split('[')[0].trim();
                if (!confirm(`⚠️ CONTRA ENTRY WARNING\n\nYou are CREDITING a DEBIT account:\n"${accountName}"\n\nThis will REDUCE the account balance.\n\nCommon examples:\n• Paying cash (reduces Cash account)\n• Customer payment (reduces Accounts Receivable)\n• Using inventory (reduces Inventory)\n\nIs this correct?`)) {
                    // User clicked Cancel - clear the credit field
                    creditInput.val(0);
                    creditInput.removeClass('border-warning');
                    calculateTotals();
                    return;
                }
            } else if (normalBalance === 'credit' && debitVal > 0) {
                debitInput.addClass('border-warning').attr('title', 'Warning: This is a credit account. Debit entries reduce its balance (contra entry).');
                console.log('Warning: Debit entry on credit account');
                
                // Show confirmation dialog
                const accountName = accountText.split('[')[0].trim();
                if (!confirm(`⚠️ CONTRA ENTRY WARNING\n\nYou are DEBITING a CREDIT account:\n"${accountName}"\n\nThis will REDUCE the account balance.\n\nCommon examples:\n• Paying off a loan (reduces Loan Payable)\n• Refunding revenue (reduces Revenue)\n• Reducing capital (reduces Equity)\n\nIs this correct?`)) {
                    // User clicked Cancel - clear the debit field
                    debitInput.val(0);
                    debitInput.removeClass('border-warning');
                    calculateTotals();
                    return;
                }
            } else {
                // Normal entry, remove warning
                debitInput.removeClass('border-warning').attr('title', '');
                creditInput.removeClass('border-warning').attr('title', '');
            }
        }

        // Renumber lines after deletion
        function renumberLines() {
            $('.entry-line').each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
        }

        // Calculate totals and check balance
        function calculateTotals() {
            let totalDebit = 0;
            let totalCredit = 0;

            $('.debit-input').each(function() {
                totalDebit += parseFloat($(this).val()) || 0;
            });

            $('.credit-input').each(function() {
                totalCredit += parseFloat($(this).val()) || 0;
            });

            $('#total-debit').text(totalDebit.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
            $('#total-credit').text(totalCredit.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}));

            const difference = Math.abs(totalDebit - totalCredit);
            $('#difference').text(difference.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}));

            if (difference > 0.01) {
                $('#balance-row').show();
                $('#save-post-btn').prop('disabled', true);
            } else {
                $('#balance-row').hide();
                $('#save-post-btn').prop('disabled', false);
            }
        }

        // Form submission validation
        $('#journalEntryForm').submit(function(e) {
            console.log('Form submitting...');
            console.log('Form data:', $(this).serialize());
            
            // Check minimum lines
            if ($('.entry-line').length < 2) {
                e.preventDefault();
                alert('{{ __("minimum_two_lines_required") }}');
                return false;
            }

            // Calculate totals
            const totalDebit = parseFloat($('#total-debit').text().replace(/\s/g, '').replace(',', '.')) || 0;
            const totalCredit = parseFloat($('#total-credit').text().replace(/\s/g, '').replace(',', '.')) || 0;
            const difference = Math.abs(totalDebit - totalCredit);
            
            console.log('Totals:', { totalDebit, totalCredit, difference });

            // Check if save and post button was clicked
            const isPosting = $(e.originalEvent.submitter).attr('id') === 'save-post-btn';
            console.log('Is posting:', isPosting);

            if (isPosting && difference > 0.01) {
                e.preventDefault();
                alert('{{ __("cannot_post_unbalanced_entry") }}');
                return false;
            }

            // Check that all lines have valid accounts
            let hasEmptyAccount = false;
            let accountCount = 0;
            $('.account-select').each(function() {
                accountCount++;
                if (!$(this).val()) {
                    hasEmptyAccount = true;
                    console.log('Empty account found at line:', accountCount);
                }
            });

            if (hasEmptyAccount) {
                e.preventDefault();
                alert('{{ __("please_select_account_for_all_lines") }}');
                return false;
            }

            console.log('Form validation passed, submitting...');
            console.log('Total lines:', $('.entry-line').length);
            
            // Disable submit buttons to prevent double submission
            $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            
            return true;
        });
    });
</script>
@endpush
