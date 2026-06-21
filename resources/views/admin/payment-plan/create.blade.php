@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('btn_create_payment_plan') }}</h5>
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary btn-sm float-end">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post">
                            @csrf

                            <!-- Student Selection -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="student_id" class="form-label">{{ __('field_student') }} <span>*</span></label>
                                        <select class="form-control select2" name="student_id" id="student_id" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($students as $student)
                                            <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                                #{{ $student->student_id }} - {{ $student->first_name }} {{ $student->last_name }}
                                            </option>
                                            @endforeach
                                        </select>

                                        <div class="invalid-feedback">
                                            {{ __('required_field') }} {{ __('field_student') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fee_id" class="form-label">{{ __('field_fee') }} <span>*</span></label>
                                        <select class="form-control select2" name="fee_id" id="fee_id" required>
                                            <option value="">{{ __('select_student_first') }}</option>
                                        </select>

                                        <div class="invalid-feedback">
                                            {{ __('required_field') }} {{ __('field_fee') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fee Details (Hidden initially, shown when fee is selected) -->
                            <div id="fee-details" style="display: none;">
                                <!-- Hidden fields for fee data -->
                                <input type="hidden" name="total_amount" id="total_amount" value="0">
                                <input type="hidden" name="fee_amount" id="fee_amount" value="0">
                                <input type="hidden" name="discount_amount" id="discount_amount" value="0">
                                <input type="hidden" name="fine_amount" id="fine_amount" value="0">
                                
                                <div class="alert alert-info">
                                    <h5>{{ __('fee_details') }}</h5>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <p><strong>{{ __('field_fee_amount') }}:</strong> <span id="fee-amount-display">0</span> {!! $setting->currency_symbol !!}</p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>{{ __('field_discount') }}:</strong> <span id="discount-display">0</span> {!! $setting->currency_symbol !!}</p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>{{ __('field_fine_amount') }}:</strong> <span id="fine-display">0</span> {!! $setting->currency_symbol !!}</p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>{{ __('field_total_amount') }}:</strong> <span id="total-display">0</span> {!! $setting->currency_symbol !!}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Plan Configuration -->
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="installments_count" class="form-label">{{ __('field_number_of_installments') }} <span>*</span></label>
                                            <input type="number" class="form-control" name="installments_count" id="installments_count" value="{{ old('installments_count', 3) }}" min="2" max="12" required>

                                            <div class="invalid-feedback">
                                                {{ __('required_field') }} {{ __('field_number_of_installments') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="late_fee_percentage" class="form-label">{{ __('field_late_fee_percentage') }} (%)</label>
                                            <input type="number" class="form-control" name="late_fee_percentage" id="late_fee_percentage" value="{{ old('late_fee_percentage', 5) }}" min="0" max="100" step="0.01">
                                            <small class="form-text text-muted">{{ __('late_fee_applied_after_grace_period') }}</small>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="grace_period_days" class="form-label">{{ __('field_grace_period_days') }}</label>
                                            <input type="number" class="form-control" name="grace_period_days" id="grace_period_days" value="{{ old('grace_period_days', 7) }}" min="0" max="30">
                                            <small class="form-text text-muted">{{ __('days_after_due_date') }}</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Installment Schedule -->
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <h5>{{ __('installment_schedule') }}</h5>
                                        <button type="button" class="btn btn-sm btn-primary mb-3" id="generate-schedule">
                                            <i class="fas fa-calendar-alt"></i> {{ __('btn_generate_schedule') }}
                                        </button>

                                        <div id="installment-schedule-container" style="display: none;">
                                            <div class="table-responsive">
                                                <table class="table table-bordered" id="installments-table">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('field_installment_number') }}</th>
                                                            <th>{{ __('field_amount') }} ({!! $setting->currency_symbol !!})</th>
                                                            <th>{{ __('field_due_date') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="installments-body">
                                                        <!-- Installments will be generated here -->
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th>{{ __('total') }}</th>
                                                            <th>
                                                                <span id="installments-total">0</span> {!! $setting->currency_symbol !!}
                                                                <div id="balance-warning" style="display: none; margin-top: 5px;">
                                                                    <small class="text-danger font-weight-bold">
                                                                        <i class="fas fa-exclamation-triangle"></i> 
                                                                        <span id="balance-message"></span>
                                                                    </small>
                                                                </div>
                                                                <div id="balance-success" style="display: none; margin-top: 5px;">
                                                                    <small class="text-success font-weight-bold">
                                                                        <i class="fas fa-check-circle"></i> 
                                                                        Total matches fee amount
                                                                    </small>
                                                                </div>
                                                            </th>
                                                            <th></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="notes" class="form-label">{{ __('field_notes') }}</label>
                                            <textarea class="form-control" name="notes" id="notes" rows="3">{{ old('notes') }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-success" id="submit-btn" disabled>
                                            <i class="fas fa-save"></i> {{ __('btn_create_payment_plan') }}
                                        </button>
                                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script>
$(document).ready(function() {
    // Wrap in try-catch to prevent other script errors from breaking this
    try {
        let feeData = null;

        // When student is selected, load their fees (use select2:select event)
        $('#student_id').on('select2:select change', function(e) {
            const studentId = $(this).val();
            console.log('Student selected:', studentId); // Debug log
            
            $('#fee_id').html('<option value="">{{ __("loading") }}...</option>');
            $('#fee-details').hide();
            $('#installment-schedule-container').hide();
            $('#submit-btn').prop('disabled', true);

            if (studentId) {
                $.ajax({
                    url: '{{ route("admin.payment-plan.get-student-fees") }}',
                    method: 'GET',
                    data: { student_id: studentId },
                    success: function(response) {
                        console.log('Fees loaded:', response); // Debug log
                        
                        let options = '<option value="">{{ __("select") }}</option>';
                        
                        if (response.fees && response.fees.length > 0) {
                            response.fees.forEach(function(fee) {
                                const displayAmount = fee.paid_amount > 0 ? fee.remaining_balance : fee.total_amount;
                                const statusInfo = fee.paid_amount > 0 ? ` (Paid: ${fee.paid_amount}, Remaining: ${fee.remaining_balance})` : '';
                                options += `<option value="${fee.id}" data-fee='${JSON.stringify(fee)}'>
                                    ${fee.category_title} - ${displayAmount} {!! $setting->currency_symbol !!}${statusInfo}
                                </option>`;
                            });
                        } else {
                            options = '<option value="">{{ __("no_fees_available") }}</option>';
                        }
                        
                        $('#fee_id').html(options);
                        // Reinitialize select2 for fee dropdown
                        if ($('#fee_id').hasClass("select2-hidden-accessible")) {
                            $('#fee_id').select2('destroy');
                        }
                        $('#fee_id').select2();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading fees:', error, xhr.responseText); // Debug log
                        $('#fee_id').html('<option value="">{{ __("error_loading_fees") }}</option>');
                        // Reinitialize select2 for fee dropdown
                        if ($('#fee_id').hasClass("select2-hidden-accessible")) {
                            $('#fee_id').select2('destroy');
                        }
                        $('#fee_id').select2();
                    }
                });
            } else {
                $('#fee_id').html('<option value="">{{ __("select_student_first") }}</option>');
                // Reinitialize select2 for fee dropdown
                if ($('#fee_id').hasClass("select2-hidden-accessible")) {
                    $('#fee_id').select2('destroy');
                }
                $('#fee_id').select2();
            }
        });
    } catch (error) {
        console.error('Error in payment plan script:', error);
    }

    // When fee is selected, show details
    $('#fee_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        if (selectedOption.val()) {
            feeData = JSON.parse(selectedOption.attr('data-fee'));
            
            // Use remaining_balance if fee is partially paid, otherwise use total_amount
            const amountToPlan = parseFloat(feeData.remaining_balance || feeData.total_amount);
            const paidAmount = parseFloat(feeData.paid_amount || 0);
            
            // Update display values
            $('#fee-amount-display').text(parseFloat(feeData.fee_amount).toFixed(2));
            $('#discount-display').text(parseFloat(feeData.discount_amount).toFixed(2));
            $('#fine-display').text(parseFloat(feeData.fine_amount).toFixed(2));
            $('#total-display').text(amountToPlan.toFixed(2));
            
            // Show paid amount if exists
            if (paidAmount > 0) {
                $('#total-display').html(`${amountToPlan.toFixed(2)} <small class="text-muted">(Already paid: ${paidAmount.toFixed(2)})</small>`);
            }
            
            // Update hidden input fields with remaining balance
            $('#total_amount').val(amountToPlan);
            $('#fee_amount').val(feeData.fee_amount);
            $('#discount_amount').val(feeData.discount_amount);
            $('#fine_amount').val(feeData.fine_amount);
            
            $('#fee-details').show();
        } else {
            $('#fee-details').hide();
            feeData = null;
        }
    });

    // Generate installment schedule
    $('#generate-schedule').on('click', function() {
        if (!feeData) {
            alert('{{ __("please_select_fee_first") }}');
            return;
        }

        const installmentsCount = parseInt($('#installments_count').val());
        if (installmentsCount < 2 || installmentsCount > 12) {
            alert('{{ __("installments_must_be_between_2_and_12") }}');
            return;
        }

        // Use remaining_balance for planning, not total_amount
        const totalAmount = parseFloat(feeData.remaining_balance || feeData.total_amount);
        const installmentAmount = (totalAmount / installmentsCount).toFixed(2);
        let remainingAmount = totalAmount;

        const tbody = $('#installments-body');
        tbody.empty();

        const today = new Date();
        let installmentsTotal = 0;

        for (let i = 1; i <= installmentsCount; i++) {
            // Calculate amount (last installment gets any remainder)
            let amount = i === installmentsCount ? remainingAmount.toFixed(2) : installmentAmount;
            remainingAmount -= parseFloat(amount);
            installmentsTotal += parseFloat(amount);

            // Calculate due date (30 days apart)
            const dueDate = new Date(today);
            dueDate.setDate(dueDate.getDate() + (i * 30));
            const dueDateStr = dueDate.toISOString().split('T')[0];

            tbody.append(`
                <tr>
                    <td>${i}</td>
                    <td>
                        <input type="number" class="form-control installment-amount" name="installments[${i}][amount]" 
                               value="${amount}" step="0.01" min="0.01" required>
                    </td>
                    <td>
                        <input type="date" class="form-control" name="installments[${i}][due_date]" 
                               value="${dueDateStr}" required>
                    </td>
                </tr>
            `);
        }

        $('#installments-total').text(installmentsTotal.toFixed(2));
        $('#installment-schedule-container').show();
        
        // Check if totals match
        checkInstallmentBalance();

        // Recalculate total when amounts change
        $(document).on('input', '.installment-amount', function() {
            let total = 0;
            $('.installment-amount').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $('#installments-total').text(total.toFixed(2));
            
            // Check balance after each change
            checkInstallmentBalance();
        });
    });
    
    // Function to check if installment total matches fee amount
    function checkInstallmentBalance() {
        if (!feeData) return;
        
        const feeAmount = parseFloat(feeData.remaining_balance || feeData.total_amount);
        let installmentsTotal = 0;
        
        $('.installment-amount').each(function() {
            installmentsTotal += parseFloat($(this).val()) || 0;
        });
        
        const difference = Math.abs(installmentsTotal - feeAmount);
        
        // Allow for small floating point differences (0.01)
        if (difference < 0.01) {
            // Totals match
            $('#balance-warning').hide();
            $('#balance-success').show();
            $('#submit-btn').prop('disabled', false);
        } else {
            // Totals don't match
            $('#balance-success').hide();
            $('#balance-warning').show();
            
            if (installmentsTotal > feeAmount) {
                $('#balance-message').text(`Total exceeds fee amount by ${difference.toFixed(2)} {!! $setting->currency_symbol !!}`);
            } else {
                $('#balance-message').text(`Total is short by ${difference.toFixed(2)} {!! $setting->currency_symbol !!}. Please adjust installments.`);
            }
            
            $('#submit-btn').prop('disabled', true);
        }
    }
});
</script>
@endsection
