@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Card ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('modal_add') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>

                        <a href="{{ route($route.'.create') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                    </div>

                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card-block">
                      <div class="row">
                        <!-- Form Start -->
                        <div class="form-group col-md-4">
                            <label for="category">{{ __('field_category') }} <span>*</span></label>
                            <select class="form-control" name="category" id="category" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach( $categories as $category )
                                <option value="{{ $category->id }}" @if(old('category') == $category->id) selected @endif>{{ $category->title }}</option>
                                @endforeach
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_category') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="budget_id">{{ __('field_budget') }}</label>
                            <select class="form-control" name="budget_id" id="budget_id">
                                <option value="">{{ __('select_optional') }}</option>
                                @foreach( $activeBudgets as $budget )
                                <option value="{{ $budget->id }}" @if(old('budget_id') == $budget->id) selected @endif>
                                    {{ $budget->code }} - {{ $budget->title }}
                                </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">{{ __('link_expense_to_budget') }}</small>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="budget_allocation_id">{{ __('field_budget_allocation') }}</label>
                            <select class="form-control" name="budget_allocation_id" id="budget_allocation_id">
                                <option value="">{{ __('select_budget_first') }}</option>
                            </select>
                            <small class="form-text text-muted" id="allocation-remaining"></small>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="invoice_id">{{ __('field_invoice_id') }}</label>
                            <input type="text" class="form-control" name="invoice_id" id="invoice_id" value="{{ old('invoice_id') }}">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_invoice_id') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="amount">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                            <input type="text" class="form-control autonumber" name="amount" id="amount" value="{{ old('amount') }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_amount') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="date">{{ __('field_date') }} <span>*</span></label>
                            <input type="date" class="form-control date" name="date" id="date" value="{{ date('Y-m-d') }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_date') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="reference">{{ __('field_reference') }}</label>
                            <input type="text" class="form-control" name="reference" id="reference" value="{{ old('reference') }}">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_reference') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="attach">{{ __('field_attach') }}</label>
                            <input type="file" class="form-control" name="attach" id="attach" value="{{ old('attach') }}">

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_attach') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="note">{{ __('field_note') }}</label>
                            <textarea class="form-control" name="note" id="note">{{ old('note') }}</textarea>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_note') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="payment_method" class="form-label">{{ __('field_payment_method') }} <span>*</span></label>
                            <select class="form-control" name="payment_method" id="payment_method" required>
                                <option value="">{{ __('select') }}</option>
                                <option value="1" @if( old('payment_method') == 1 ) selected @endif>{{ __('payment_method_cash') }}</option>
                                <option value="2" @if( old('payment_method') == 2 ) selected @endif>{{ __('payment_method_bank') }}</option>
                                <option value="3" @if( old('payment_method') == 3 ) selected @endif>{{ __('payment_method_mtn_momo') }}</option>
                                <option value="4" @if( old('payment_method') == 4 ) selected @endif>{{ __('payment_method_orange_money') }}</option>
                                <option value="5" @if( old('payment_method') == 5 ) selected @endif>{{ __('payment_method_other') }}</option>
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_payment_method') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="payment_account_id" class="form-label">{{ __('field_payment_account') }}</label>
                            <select class="form-control" name="payment_account_id" id="payment_account_id">
                                <option value="">{{ __('select_payment_account_optional') }}</option>
                                @foreach(\App\Models\PaymentAccount::where('status', 1)->orderBy('title')->get() as $account)
                                    <option value="{{ $account->id }}" @if(old('payment_account_id') == $account->id) selected @endif>
                                        {{ $account->title }} - {{ __('field_balance') }}: {!! $setting->currency_symbol !!}{{ number_format($account->current_balance, 2) }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">{{ __('payment_account_help_text_expense') }}</small>
                        </div>
                        <!-- Form End -->
                      </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                    </div>
                    </form>
                </div>
            </div>
            <!-- [ Card ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('scripts')
<script>
    // Test if script loads
    console.log('=== EXPENSE FORM SCRIPT LOADED ===');
    
    $(document).ready(function() {
        console.log('jQuery ready event fired');
        console.log('Budget dropdown found:', $('#budget_id').length);
        console.log('Allocation dropdown found:', $('#budget_allocation_id').length);
        
        // Load allocations when budget is selected
        $('#budget_id').on('change', function() {
            const budgetId = $(this).val();
            console.log('=== BUDGET CHANGED ===');
            console.log('Budget changed to:', budgetId);
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
                        
                        // Filter allocations by selected category
                        const selectedCategory = categorySelect.val();
                        
                        data.forEach(function(allocation) {
                            if (!selectedCategory || allocation.expense_category_id == selectedCategory) {
                                allocationSelect.append(
                                    '<option value="' + allocation.id + '" ' +
                                    'data-remaining="' + allocation.remaining_amount + '">' +
                                    allocation.category_title + ' - Remaining: ' + 
                                    allocation.remaining_formatted + 
                                    '</option>'
                                );
                            }
                        });
                        
                        if (allocationSelect.find('option').length === 1) {
                            allocationSelect.html('<option value="">{{ __("no_allocations_available") }}</option>');
                        }
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
        
        // Update remaining amount display when allocation is selected
        $('#budget_allocation_id').on('change', function() {
            const selected = $(this).find('option:selected');
            const remaining = parseFloat(selected.data('remaining'));
            
            if (!isNaN(remaining)) {
                $('#allocation-remaining').text('{{ __("remaining_amount") }}: ' + remaining.toFixed(2));
                
                // Set max validation on amount field
                $('#amount').attr('max', remaining);
            } else {
                $('#allocation-remaining').text('');
                $('#amount').removeAttr('max');
            }
        });
        
        // Validate amount against remaining allocation
        $('form').on('submit', function(e) {
            const allocationId = $('#budget_allocation_id').val();
            const amount = parseFloat($('#amount').val().replace(/,/g, '')) || 0;
            const selected = $('#budget_allocation_id').find('option:selected');
            const remaining = selected.data('remaining');
            
            if (allocationId && remaining !== undefined && amount > remaining) {
                e.preventDefault();
                alert('{{ __("expense_exceeds_allocation") }}: ' + remaining.toFixed(2));
                return false;
            }
        });
        
        // Also filter allocations when category changes
        $('#category').on('change', function() {
            if ($('#budget_id').val()) {
                $('#budget_id').trigger('change');
            }
        });
    });
</script>
@endsection