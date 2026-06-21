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
                        <h5>{{ $title }}</h5>
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                    </div>
                    <div class="card-block">
                        <form id="feeConfigForm" class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post">
                        @csrf
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="faculty">{{ __('field_faculty') }} <span>*</span></label>
                                    <select class="form-control" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach( $faculties as $faculty )
                                        <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_faculty') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="program">{{ __('field_program') }} <span>*</span></label>
                                    <select class="form-control" name="program" id="program" required>
                                        <option value="">{{ __('select') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_program') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="semester_type">Semester Type <span>*</span></label>
                                    <select class="form-control" name="semester_type" id="semester_type" required>
                                        <option value="">{{ __('select') }}</option>
                                    </select>
                                    <small class="form-text text-muted">Configure fees for all semesters of this type (e.g., all First Semesters)</small>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} Semester Type
                                    </div>
                                </div>

                                <div class="form-group col-md-6" id="semestersPreview" style="display:none;">
                                    <label>Semesters to be Configured:</label>
                                    <div id="semestersList" class="border p-2 rounded bg-light">
                                        <!-- Will show list of semesters -->
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <button type="button" id="loadCategories" class="btn btn-info btn-filter mt-4" disabled>
                                        <i class="fas fa-plus"></i> Load Fee Categories
                                    </button>
                                </div>
                            </div>

                            <hr>

                            <div id="feeCategoriesSection" style="display: none;">
                                <h5 class="mb-3">Fee Category Configuration</h5>
                                <p class="text-muted">Only installment fee categories (First/Second Installment) are shown below.</p>
                                
                                <div id="categoriesContainer">
                                    <!-- Categories will be loaded here -->
                                </div>

                                <div class="row mt-4">
                                    <div class="form-group col-md-12">
                                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> {{ __('btn_save') }}</button>
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

@section('scripts')
<script>
$(document).ready(function() {
    // Faculty change - load programs
    $('#faculty').on('change', function() {
        var facultyId = $(this).val();
        $('#program').empty().append('<option value="">{{ __("select") }}</option>');
        $('#semester').empty().append('<option value="">{{ __("select") }}</option>');
        $('#feeCategoriesSection').hide();
        $('#loadCategories').prop('disabled', true);
        
        if(facultyId) {
            var url = "{{ route('admin.program-semester-fee.get-programs') }}";
            
            $.ajax({
                url: url,
                type: "GET",
                data: {faculty_id: facultyId},
                success: function(data) {
                    console.log('Programs loaded:', data);
                    $.each(data, function(key, value) {
                        $('#program').append('<option value="'+ value.id +'">'+ value.title +'</option>');
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading programs:', error);
                    console.error('Response:', xhr.responseText);
                    alert('Error loading programs. Please check console for details.');
                }
            });
        }
    });

    // Program change - load semester types
    $('#program').on('change', function() {
        var programId = $(this).val();
        $('#semester_type').empty().append('<option value="">{{ __("select") }}</option>');
        $('#semestersPreview').hide();
        $('#semestersList').empty();
        $('#feeCategoriesSection').hide();
        $('#loadCategories').prop('disabled', true);
        
        if(programId) {
            var url = "{{ route('admin.program-semester-fee.get-semester-types') }}";
            
            $.ajax({
                url: url,
                type: "GET",
                data: {program_id: programId},
                success: function(data) {
                    console.log('Semester types loaded:', data);
                    if(data.length === 0) {
                        alert('No semester types found for this program. Please enroll courses first.');
                        return;
                    }
                    
                    $.each(data, function(key, value) {
                        var typeLabel = 'Type ' + value.type + ' (' + value.count + ' semester';
                        if(value.count > 1) typeLabel += 's';
                        typeLabel += ')';
                        if(value.example) {
                            typeLabel += ' - e.g., ' + value.example;
                        }
                        $('#semester_type').append('<option value="'+ value.type +'">'+ typeLabel +'</option>');
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error loading semester types:', error);
                    console.error('Response:', xhr.responseText);
                    alert('Error loading semester types. Please check console for details.');
                }
            });
        }
    });

    // Semester type change - load semesters and show preview
    $('#semester_type').on('change', function() {
        var semesterType = $(this).val();
        var programId = $('#program').val();
        
        $('#semestersPreview').hide();
        $('#semestersList').empty();
        $('#loadCategories').prop('disabled', true);
        
        if(semesterType && programId) {
            var url = "{{ route('admin.program-semester-fee.get-semesters') }}";
            
            $.ajax({
                url: url,
                type: "GET",
                data: {
                    program_id: programId,
                    semester_type: semesterType
                },
                success: function(data) {
                    if(data.length === 0) {
                        alert('No semesters found for this type with enrolled courses.');
                        return;
                    }
                    
                    // Show preview of semesters
                    var semesterHtml = '<ul class="mb-0">';
                    $.each(data, function(key, value) {
                        var semesterText = value.title;
                        if(value.year) {
                            semesterText += ' (Year ' + value.year + ')';
                        }
                        semesterHtml += '<li>' + semesterText + '</li>';
                    });
                    semesterHtml += '</ul>';
                    semesterHtml += '<p class="mb-0 mt-2 text-info"><strong>' + data.length + '</strong> semester(s) will be configured</p>';
                    
                    $('#semestersList').html(semesterHtml);
                    $('#semestersPreview').show();
                    $('#loadCategories').prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    console.error('Error loading semesters:', error);
                    alert('Error loading semesters. Please check console for details.');
                }
            });
        }
    });

    // Load fee categories
    $('#loadCategories').on('click', function() {
        var url = "{{ route('admin.program-semester-fee.get-fee-categories') }}";
        
        $.ajax({
            url: url,
            type: "GET",
            success: function(data) {
                console.log('Fee categories loaded:', data);
                if(data.length === 0) {
                    alert('No eligible fee categories found. Only installment fee categories (First/Second Installment) are allowed.');
                    return;
                }
                
                $('#categoriesContainer').empty();
                
                $.each(data, function(key, category) {
                    var badgeHtml = '';
                    if(category.is_first_installment == 1) {
                        badgeHtml += '<span class="badge badge-info ms-2">1st Installment</span>';
                    }
                    if(category.is_second_installment == 1) {
                        badgeHtml += '<span class="badge badge-warning ms-2">2nd Installment</span>';
                    }
                    
                    var categoryRow = `
                        <div class="row mb-3 category-row border-bottom pb-3" data-category-id="${category.id}" data-category-index="${key}">
                            <div class="col-md-12 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input category-checkbox" type="checkbox" name="fees_category[]" value="${category.id}" id="category_${category.id}" data-is-first-installment="${category.is_first_installment}">
                                    <label class="form-check-label fw-bold" for="category_${category.id}">
                                        ${category.title} ${badgeHtml}
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('field_amount') }} <span class="text-danger">*</span></label>
                                <input type="number" class="form-control amount-input" name="amount[]" placeholder="0.00" min="0" step="0.01" disabled data-category-index="${key}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Due Days <small class="text-muted">(optional)</small></label>
                                <input type="number" class="form-control due-days-input" name="due_days[]" placeholder="e.g., 30" min="1" max="365" disabled>
                                <small class="text-muted">Days from enrollment</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Fine Amount <small class="text-muted">(optional)</small></label>
                                <input type="number" class="form-control fine-amount-input" name="fine_amount[]" placeholder="0.00" min="0" step="0.01" disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Fine Type <small class="text-muted">(optional)</small></label>
                                <select class="form-control fine-type-input" name="fine_type[]" disabled>
                                    <option value="">Select</option>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percentage">Percentage (%)</option>
                                </select>
                            </div>
                            <div class="col-md-12 breakdown-section" id="breakdown_section_${key}" style="display: none;">
                                <hr class="my-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 text-primary"><i class="fas fa-list-ul"></i> Fee Breakdown (Form A2)</h6>
                                    <button type="button" class="btn btn-sm btn-success add-breakdown-btn" data-category-index="${key}">
                                        <i class="fas fa-plus"></i> Add Item
                                    </button>
                                </div>
                                <small class="text-muted d-block mb-2">The sum of all breakdown amounts must equal the total fee amount.</small>
                                <div class="breakdown-items" id="breakdown_items_${key}">
                                    <!-- Breakdown items will be added here -->
                                </div>
                                <div class="mt-2">
                                    <span class="badge badge-light">Total: <span class="breakdown-total" id="breakdown_total_${key}">0.00</span></span>
                                    <span class="badge badge-light">Fee Amount: <span class="fee-amount-display" id="fee_amount_${key}">0.00</span></span>
                                    <span class="breakdown-status ms-2" id="breakdown_status_${key}"></span>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#categoriesContainer').append(categoryRow);
                });
                
                $('#feeCategoriesSection').show();
                
                // Handle checkbox change
                $('.category-checkbox').on('change', function() {
                    var categoryRow = $(this).closest('.category-row');
                    var amountInput = categoryRow.find('.amount-input');
                    var dueDaysInput = categoryRow.find('.due-days-input');
                    var fineAmountInput = categoryRow.find('.fine-amount-input');
                    var fineTypeInput = categoryRow.find('.fine-type-input');
                    
                    if($(this).is(':checked')) {
                        amountInput.prop('disabled', false).prop('required', true);
                        dueDaysInput.prop('disabled', false);
                        fineAmountInput.prop('disabled', false);
                        fineTypeInput.prop('disabled', false);
                    } else {
                        amountInput.prop('disabled', true).prop('required', false).val('');
                        dueDaysInput.prop('disabled', true).val('');
                        fineAmountInput.prop('disabled', true).val('');
                        fineTypeInput.prop('disabled', true).val('');
                        
                        // Hide breakdown section
                        categoryRow.find('.breakdown-section').hide();
                    }
                });

                // Handle amount input for first installment in Type 1
                $('.amount-input').on('input', function() {
                    var categoryRow = $(this).closest('.category-row');
                    var checkbox = categoryRow.find('.category-checkbox');
                    var isFirstInstallment = checkbox.data('is-first-installment') == 1;
                    var semesterType = $('#semester_type').val();
                    var categoryIndex = $(this).data('category-index');
                    var amount = parseFloat($(this).val()) || 0;
                    
                    // Update fee amount display
                    $('#fee_amount_' + categoryIndex).text(amount.toFixed(2));
                    
                    // Show breakdown section for first installment in Type 1
                    if (isFirstInstallment && semesterType == '1' && amount > 0) {
                        var breakdownSection = categoryRow.find('.breakdown-section');
                        breakdownSection.show();
                        
                        // Add initial breakdown item if none exist
                        if (breakdownSection.find('.breakdown-item').length === 0) {
                            addBreakdownItem(categoryIndex);
                        }
                        
                        updateBreakdownTotal(categoryIndex);
                    } else {
                        categoryRow.find('.breakdown-section').hide();
                    }
                });

                // Add breakdown item button
                $(document).on('click', '.add-breakdown-btn', function() {
                    var categoryIndex = $(this).data('category-index');
                    addBreakdownItem(categoryIndex);
                });

                // Remove breakdown item button
                $(document).on('click', '.remove-breakdown-btn', function() {
                    var categoryIndex = $(this).data('category-index');
                    $(this).closest('.breakdown-item').remove();
                    updateBreakdownTotal(categoryIndex);
                });

                // Update breakdown total on input
                $(document).on('input', '.breakdown-amount-input', function() {
                    var categoryIndex = $(this).closest('.category-row').data('category-index');
                    updateBreakdownTotal(categoryIndex);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading fee categories:', error);
                console.error('Response:', xhr.responseText);
                alert('Error loading fee categories. Please check console for details.');
            }
        });
    });

    // Function to add breakdown item
    function addBreakdownItem(categoryIndex) {
        var itemCount = $('#breakdown_items_' + categoryIndex + ' .breakdown-item').length;
        var itemHtml = `
            <div class="row mb-2 breakdown-item">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="breakdown_titles_${categoryIndex}[]" placeholder="e.g., Tuition Fee" required>
                </div>
                <div class="col-md-5">
                    <input type="number" class="form-control form-control-sm breakdown-amount-input" name="breakdown_amounts_${categoryIndex}[]" placeholder="0.00" min="0" step="0.01" required>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger remove-breakdown-btn" data-category-index="${categoryIndex}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        $('#breakdown_items_' + categoryIndex).append(itemHtml);
        updateBreakdownTotal(categoryIndex);
    }

    // Function to update breakdown total
    function updateBreakdownTotal(categoryIndex) {
        var total = 0;
        $('#breakdown_items_' + categoryIndex + ' .breakdown-amount-input').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        
        var feeAmount = parseFloat($('.category-row[data-category-index="' + categoryIndex + '"] .amount-input').val()) || 0;
        
        $('#breakdown_total_' + categoryIndex).text(total.toFixed(2));
        
        // Validate if totals match
        var statusSpan = $('#breakdown_status_' + categoryIndex);
        if (total === feeAmount && total > 0) {
            statusSpan.html('<span class="badge badge-success"><i class="fas fa-check"></i> Valid</span>');
        } else if (total > feeAmount) {
            statusSpan.html('<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Exceeds fee amount</span>');
        } else if (total < feeAmount && total > 0) {
            statusSpan.html('<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Less than fee amount</span>');
        } else {
            statusSpan.html('');
        }
    }

    // Form validation
    $('#feeConfigForm').on('submit', function(e) {
        var checkedCategories = $('.category-checkbox:checked').length;
        
        if(checkedCategories === 0) {
            e.preventDefault();
            alert('Please select at least one fee category and enter the amount.');
            return false;
        }

        // Validate breakdown totals for Type 1 first installment
        var semesterType = $('#semester_type').val();
        if (semesterType == '1') {
            var isValid = true;
            var errorMessages = [];

            $('.category-checkbox:checked').each(function() {
                var categoryRow = $(this).closest('.category-row');
                var isFirstInstallment = $(this).data('is-first-installment') == 1;
                
                if (isFirstInstallment) {
                    var categoryIndex = categoryRow.data('category-index');
                    var feeAmount = parseFloat(categoryRow.find('.amount-input').val()) || 0;
                    var breakdownTotal = 0;
                    
                    $('#breakdown_items_' + categoryIndex + ' .breakdown-amount-input').each(function() {
                        breakdownTotal += parseFloat($(this).val()) || 0;
                    });
                    
                    var breakdownItemCount = $('#breakdown_items_' + categoryIndex + ' .breakdown-item').length;
                    
                    if (feeAmount > 0 && breakdownItemCount === 0) {
                        isValid = false;
                        errorMessages.push('Please add at least one breakdown item for ' + $(this).next('label').text());
                    } else if (Math.abs(breakdownTotal - feeAmount) > 0.01) {
                        isValid = false;
                        errorMessages.push('Breakdown total (' + breakdownTotal.toFixed(2) + ') must equal fee amount (' + feeAmount.toFixed(2) + ') for ' + $(this).next('label').text());
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Fee Breakdown Validation Failed:\n\n' + errorMessages.join('\n'));
                return false;
            }
        }

        return true;
    });
});
</script>
@endsection
