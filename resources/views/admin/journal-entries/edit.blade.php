@extends('admin.layouts.master')
@section('title', __('edit_journal_entry'))

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
                        <h3 class="card-title">{{ __('edit_journal_entry') }} - {{ $entry->entry_number }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->

                    @if(!$entry->is_posted)
                    <!-- Accounting Rules Help Box -->
                    <div class="alert alert-info alert-dismissible fade show mx-3 mt-3" role="alert">
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
                    @endif

                    <form action="{{ route('admin.journal-entries.update', $entry->id) }}" method="POST" id="journalEntryForm">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            @if($entry->is_posted)
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> {{ __('this_entry_is_posted') }}. {{ __('unpost_to_edit') }}.
                            </div>
                            @endif

                            <div class="row">
                                <!-- Entry Number -->
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ __('entry_number') }}</label>
                                        <input type="text" class="form-control" value="{{ $entry->entry_number }}" readonly>
                                    </div>
                                </div>

                                <!-- Entry Date -->
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="entry_date">{{ __('entry_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('entry_date') is-invalid @enderror" 
                                               id="entry_date" name="entry_date" value="{{ old('entry_date', $entry->entry_date->format('Y-m-d')) }}" 
                                               {{ $entry->is_posted ? 'readonly' : '' }} required>
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
                                                id="journal_type" name="journal_type" {{ $entry->is_posted ? 'disabled' : '' }} required>
                                            <option value="general" {{ old('journal_type', $entry->journal_type) == 'general' ? 'selected' : '' }}>{{ __('general') }}</option>
                                            <option value="sales" {{ old('journal_type', $entry->journal_type) == 'sales' ? 'selected' : '' }}>{{ __('sales') }}</option>
                                            <option value="purchase" {{ old('journal_type', $entry->journal_type) == 'purchase' ? 'selected' : '' }}>{{ __('purchase') }}</option>
                                            <option value="cash" {{ old('journal_type', $entry->journal_type) == 'cash' ? 'selected' : '' }}>{{ __('cash') }}</option>
                                            <option value="bank" {{ old('journal_type', $entry->journal_type) == 'bank' ? 'selected' : '' }}>{{ __('bank') }}</option>
                                            <option value="adjustment" {{ old('journal_type', $entry->journal_type) == 'adjustment' ? 'selected' : '' }}>{{ __('adjustment') }}</option>
                                        </select>
                                        @if($entry->is_posted)
                                        <input type="hidden" name="journal_type" value="{{ $entry->journal_type }}">
                                        @endif
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
                                               id="reference_number" name="reference_number" value="{{ old('reference_number', $entry->reference_number) }}" 
                                               {{ $entry->is_posted ? 'readonly' : '' }}>
                                        @error('reference_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Description -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }} <span class="text-danger">*</span></label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="2" 
                                                  {{ $entry->is_posted ? 'readonly' : '' }} required>{{ old('description', $entry->description) }}</textarea>
                                        @error('description')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
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
                                                    @if(!$entry->is_posted)
                                                    <th width="8%">{{ __('action') }}</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody id="entry-lines-body">
                                                @foreach($entry->lines as $index => $line)
                                                <tr class="entry-line" data-line="{{ $index + 1 }}">
                                                    <td class="text-center">{{ $index + 1 }}</td>
                                                    <td>
                                                        <select class="form-control form-control-sm account-select" name="lines[{{ $index + 1 }}][account_id]" {{ $entry->is_posted ? 'disabled' : '' }} required>
                                                            @foreach($accounts as $account)
                                                            <option value="{{ $account->id }}" {{ $line->account_id == $account->id ? 'selected' : '' }}>
                                                                {{ $account->account_code }} - {{ $account->account_name }} [{{ strtoupper($account->normal_balance) }}]
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" name="lines[{{ $index + 1 }}][description]" 
                                                               value="{{ $line->description }}" {{ $entry->is_posted ? 'readonly' : '' }}>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control form-control-sm debit-input" 
                                                               name="lines[{{ $index + 1 }}][debit]" value="{{ $line->debit }}" 
                                                               min="0" {{ $entry->is_posted ? 'readonly' : '' }}>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control form-control-sm credit-input" 
                                                               name="lines[{{ $index + 1 }}][credit]" value="{{ $line->credit }}" 
                                                               min="0" {{ $entry->is_posted ? 'readonly' : '' }}>
                                                    </td>
                                                    @if(!$entry->is_posted)
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-danger btn-xs remove-line-btn" data-line="{{ $index + 1 }}">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                    @endif
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="{{ $entry->is_posted ? 3 : 3 }}" class="text-right"><strong>{{ __('total') }}:</strong></td>
                                                    <td class="text-right">
                                                        <strong id="total-debit">{{ number_format($entry->total_debit, 0, ',', ' ') }}</strong> FCFA
                                                    </td>
                                                    <td class="text-right">
                                                        <strong id="total-credit">{{ number_format($entry->total_credit, 0, ',', ' ') }}</strong> FCFA
                                                    </td>
                                                    @if(!$entry->is_posted)
                                                    <td></td>
                                                    @endif
                                                </tr>
                                                @if(!$entry->is_posted)
                                                <tr id="balance-row" style="display: none;">
                                                    <td colspan="6" class="text-center">
                                                        <span class="badge badge-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> 
                                                            {{ __('entry_not_balanced') }}: {{ __('difference') }} = <span id="difference">0</span> FCFA
                                                        </span>
                                                    </td>
                                                </tr>
                                                @endif
                                            </tfoot>
                                        </table>
                                    </div>
                                    @if(!$entry->is_posted)
                                    <button type="button" class="btn btn-success btn-sm" id="add-line-btn">
                                        <i class="fas fa-plus"></i> {{ __('add_line') }}
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            @if(!$entry->is_posted)
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('update') }}
                            </button>
                            @endif
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
        @if(!$entry->is_posted)
        let lineCounter = {{ $entry->lines->count() }};
        const accounts = @json($accounts);

        // Initialize Select2 for existing dropdowns
        $('.account-select').select2({
            theme: 'bootstrap4',
            width: '100%'
        });

        // Calculate initial totals
        calculateTotals();

        // Add line button
        $('#add-line-btn').click(function() {
            addLine();
        });

        // Add new line function
        function addLine() {
            lineCounter++;
            
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
                        <input type="text" class="form-control form-control-sm" name="lines[${lineCounter}][description]">
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
            
            // Initialize Select2
            $(`select[name="lines[${lineCounter}][account_id]"]`).select2({
                theme: 'bootstrap4',
                width: '100%'
            });

            attachLineEvents(lineCounter);
        }

        // Attach events to all existing lines
        $('.entry-line').each(function() {
            const lineNum = $(this).data('line');
            attachLineEvents(lineNum);
        });

        // Attach events to line inputs
        function attachLineEvents(lineNum) {
            const debitInput = $(`input[name="lines[${lineNum}][debit]"]`);
            const creditInput = $(`input[name="lines[${lineNum}][credit]"]`);
            const accountSelect = $(`select[name="lines[${lineNum}][account_id]"]`);

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

        function renumberLines() {
            $('.entry-line').each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
        }

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
            } else {
                $('#balance-row').hide();
            }
        }
        @endif
    });
</script>
@endpush
