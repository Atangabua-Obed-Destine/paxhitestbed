@extends('admin.layouts.master')
@section('title', __('add_recurring_entry'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('add_recurring_entry') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.recurring-entries.index') }}" class="btn btn-secondary btn-sm">
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

                    <form action="{{ route('admin.recurring-entries.store') }}" method="POST" id="recurringEntryForm">
                        @csrf
                        <div class="card-body">
                            <!-- Template Information -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="template_name">{{ __('template_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('template_name') is-invalid @enderror" 
                                               id="template_name" name="template_name" value="{{ old('template_name') }}" required>
                                        @error('template_name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="journal_type">{{ __('journal_type') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('journal_type') is-invalid @enderror" 
                                                id="journal_type" name="journal_type" required>
                                            <option value="general" {{ old('journal_type') == 'general' ? 'selected' : '' }}>{{ __('general') }}</option>
                                            <option value="sales" {{ old('journal_type') == 'sales' ? 'selected' : '' }}>{{ __('sales') }}</option>
                                            <option value="purchase" {{ old('journal_type') == 'purchase' ? 'selected' : '' }}>{{ __('purchase') }}</option>
                                            <option value="cash" {{ old('journal_type') == 'cash' ? 'selected' : '' }}>{{ __('cash') }}</option>
                                            <option value="bank" {{ old('journal_type') == 'bank' ? 'selected' : '' }}>{{ __('bank') }}</option>
                                        </select>
                                        @error('journal_type')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
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

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }}</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="2">{{ old('description') }}</textarea>
                                        @error('description')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Information -->
                            <hr>
                            <h5>{{ __('schedule') }}</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="frequency">{{ __('frequency') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('frequency') is-invalid @enderror" 
                                                id="frequency" name="frequency" required>
                                            <option value="daily" {{ old('frequency') == 'daily' ? 'selected' : '' }}>{{ __('daily') }}</option>
                                            <option value="weekly" {{ old('frequency') == 'weekly' ? 'selected' : '' }}>{{ __('weekly') }}</option>
                                            <option value="monthly" {{ old('frequency', 'monthly') == 'monthly' ? 'selected' : '' }}>{{ __('monthly') }}</option>
                                            <option value="quarterly" {{ old('frequency') == 'quarterly' ? 'selected' : '' }}>{{ __('quarterly') }}</option>
                                            <option value="yearly" {{ old('frequency') == 'yearly' ? 'selected' : '' }}>{{ __('yearly') }}</option>
                                        </select>
                                        @error('frequency')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="day_of_month">{{ __('day_of_month') }}</label>
                                        <input type="number" class="form-control @error('day_of_month') is-invalid @enderror" 
                                               id="day_of_month" name="day_of_month" value="{{ old('day_of_month', 1) }}" min="1" max="31">
                                        <small class="text-muted">{{ __('for_monthly_entries') }}</small>
                                        @error('day_of_month')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="start_date">{{ __('start_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                               id="start_date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required>
                                        @error('start_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="end_date">{{ __('end_date') }}</label>
                                        <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                               id="end_date" name="end_date" value="{{ old('end_date') }}">
                                        <small class="text-muted">{{ __('leave_blank_for_indefinite') }}</small>
                                        @error('end_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="total_occurrences">{{ __('total_occurrences') }}</label>
                                        <input type="number" class="form-control @error('total_occurrences') is-invalid @enderror" 
                                               id="total_occurrences" name="total_occurrences" value="{{ old('total_occurrences') }}" min="1">
                                        <small class="text-muted">{{ __('leave_blank_for_unlimited') }}</small>
                                        @error('total_occurrences')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="auto_post">{{ __('auto_post') }}</label>
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox" class="custom-control-input" id="auto_post" name="auto_post" value="1" {{ old('auto_post') ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="auto_post">{{ __('automatically_post_entries') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="notify_before_days">{{ __('notify_before_days') }}</label>
                                        <input type="number" class="form-control @error('notify_before_days') is-invalid @enderror" 
                                               id="notify_before_days" name="notify_before_days" value="{{ old('notify_before_days', 0) }}" min="0">
                                        @error('notify_before_days')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Entry Lines -->
                            <hr>
                            <h5>{{ __('entry_lines') }}</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="lines-table">
                                    <thead>
                                        <tr>
                                            <th width="5%">{{ __('line') }}</th>
                                            <th width="35%">{{ __('account') }} <span class="text-danger">*</span></th>
                                            <th width="25%">{{ __('description') }}</th>
                                            <th width="13%">{{ __('debit') }}</th>
                                            <th width="13%">{{ __('credit') }}</th>
                                            <th width="9%">{{ __('action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lines-body">
                                        <!-- Lines added dynamically -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right"><strong>{{ __('total') }}:</strong></td>
                                            <td class="text-right"><strong id="total-debit">0</strong> FCFA</td>
                                            <td class="text-right"><strong id="total-credit">0</strong> FCFA</td>
                                            <td></td>
                                        </tr>
                                        <tr id="balance-row" style="display: none;">
                                            <td colspan="6" class="text-center">
                                                <span class="badge badge-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    {{ __('entry_not_balanced') }}
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
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('save') }}
                            </button>
                            <a href="{{ route('admin.recurring-entries.index') }}" class="btn btn-secondary">
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
        let accounts = @json($accounts);

        // Add initial lines
        addLine();
        addLine();

        // Add line button
        $('#add-line-btn').click(function() {
            addLine();
        });

        function addLine() {
            lineCounter++;
            
            let accountOptions = '<option value="">{{ __("select_account") }}</option>';
            accounts.forEach(account => {
                accountOptions += `<option value="${account.id}">${account.account_code} - ${account.account_name}</option>`;
            });

            const newRow = `
                <tr class="entry-line" data-line="${lineCounter}">
                    <td class="text-center">${lineCounter}</td>
                    <td>
                        <select class="form-control form-control-sm" name="lines[${lineCounter}][account_id]" required>
                            ${accountOptions}
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="lines[${lineCounter}][description]">
                    </td>
                    <td>
                        <input type="number" step="1" class="form-control form-control-sm debit-input" name="lines[${lineCounter}][debit_amount]" value="0" min="0">
                    </td>
                    <td>
                        <input type="number" step="1" class="form-control form-control-sm credit-input" name="lines[${lineCounter}][credit_amount]" value="0" min="0">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-xs remove-line-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;

            $('#lines-body').append(newRow);
            attachLineEvents();
        }

        function attachLineEvents() {
            // Remove line button
            $('.remove-line-btn').off('click').on('click', function() {
                if ($('.entry-line').length > 2) {
                    $(this).closest('tr').remove();
                    renumberLines();
                    calculateTotals();
                } else {
                    alert('{{ __("minimum_two_lines_required") }}');
                }
            });

            // Debit/Credit mutual exclusion
            $('.debit-input').off('input').on('input', function() {
                if (parseFloat($(this).val()) > 0) {
                    $(this).closest('tr').find('.credit-input').val(0);
                }
                calculateTotals();
            });

            $('.credit-input').off('input').on('input', function() {
                if (parseFloat($(this).val()) > 0) {
                    $(this).closest('tr').find('.debit-input').val(0);
                }
                calculateTotals();
            });
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

            $('#total-debit').text(totalDebit.toLocaleString('fr-FR'));
            $('#total-credit').text(totalCredit.toLocaleString('fr-FR'));

            if (Math.abs(totalDebit - totalCredit) > 0.01) {
                $('#balance-row').show();
            } else {
                $('#balance-row').hide();
            }
        }

        // Form validation
        $('#recurringEntryForm').submit(function(e) {
            const totalDebit = parseFloat($('#total-debit').text().replace(/\s/g, '').replace(',', '.')) || 0;
            const totalCredit = parseFloat($('#total-credit').text().replace(/\s/g, '').replace(',', '.')) || 0;

            if (Math.abs(totalDebit - totalCredit) > 0.01) {
                e.preventDefault();
                alert('{{ __("entry_must_be_balanced") }}');
                return false;
            }
        });
    });
</script>
@endpush
