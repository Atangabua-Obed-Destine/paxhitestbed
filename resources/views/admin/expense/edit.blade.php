@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{-- The form is shared with the other side of the sheet, and with the create form.
     The four files had already drifted — the income edit form offered a
     different list of payment methods than the income create form that wrote
     the values. --}}
@include('admin.account.partials.transaction_form', [
    'mode' => 'edit',
    'account_help' => 'payment_account_help_text_expense',
    'extra' => 'admin.account.partials.expense_budget_fields',
])

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        const currentAllocationId = '{{ $row->budget_allocation_id }}';
        
        // Load allocations when budget is selected
        $('#budget_id').on('change', function() {
            const budgetId = $(this).val();
            const allocationSelect = $('#budget_allocation_id');
            const categorySelect = $('#category');
            
            allocationSelect.html('<option value="">{{ __("loading") }}...</option>');
            $('#allocation-remaining').text('');
            
            if (budgetId) {
                $.ajax({
                    url: '{{ url("admin/budget") }}/' + budgetId + '/allocations-data',
                    method: 'GET',
                    success: function(data) {
                        console.log('Allocations loaded:', data);
                        allocationSelect.html('<option value="">{{ __("select_allocation") }}</option>');
                        
                        const selectedCategory = categorySelect.val();
                        
                        data.forEach(function(allocation) {
                            if (!selectedCategory || allocation.expense_category_id == selectedCategory) {
                                const isSelected = allocation.id == currentAllocationId ? 'selected' : '';
                                allocationSelect.append(
                                    '<option value="' + allocation.id + '" ' +
                                    'data-remaining="' + allocation.remaining_amount + '" ' + isSelected + '>' +
                                    allocation.category_title + ' - Remaining: ' + 
                                    allocation.remaining_formatted + 
                                    '</option>'
                                );
                            }
                        });
                        
                        // Trigger change to update remaining display
                        allocationSelect.trigger('change');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading allocations:', xhr.responseText);
                        allocationSelect.html('<option value="">{{ __("error_loading_allocations") }}</option>');
                    }
                });
            } else {
                allocationSelect.html('<option value="">{{ __("select_budget_first") }}</option>');
            }
        });
        
        // Update remaining amount display
        $('#budget_allocation_id').on('change', function() {
            const selected = $(this).find('option:selected');
            const remaining = parseFloat(selected.data('remaining'));
            
            if (!isNaN(remaining)) {
                $('#allocation-remaining').text('{{ __("remaining_amount") }}: ' + remaining.toFixed(2));
                $('#amount').attr('max', remaining);
            } else {
                $('#allocation-remaining').text('');
                $('#amount').removeAttr('max');
            }
        });
        
        // Validate amount
        $('form').on('submit', function(e) {
            const allocationId = $('#budget_allocation_id').val();
            const amount = parseFloat($('#amount').val().replace(/,/g, '')) || 0;
            const selected = $('#budget_allocation_id').find('option:selected');
            const remaining = parseFloat(selected.data('remaining'));
            
            if (allocationId && !isNaN(remaining) && amount > remaining) {
                e.preventDefault();
                alert('{{ __("expense_exceeds_allocation") }}: ' + remaining.toFixed(2));
                return false;
            }
        });
        
        $('#category').on('change', function() {
            if ($('#budget_id').val()) {
                $('#budget_id').trigger('change');
            }
        });
        
        // Initialize on page load
        if ($('#budget_id').val()) {
            $('#budget_id').trigger('change');
        }
    });
</script>
@endsection
