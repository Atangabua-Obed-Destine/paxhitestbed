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
                        <h5>{{ __('btn_edit') }} {{ $title }}</h5>
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post">
                        @csrf
                        @method('PUT')
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="faculty">{{ __('field_faculty') }}</label>
                                    <input type="text" class="form-control" value="{{ $row->program->faculty->title ?? 'N/A' }}" readonly>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <input type="text" class="form-control" value="{{ $row->program->title ?? 'N/A' }}" readonly>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="semester">{{ __('field_semester') }}</label>
                                    <input type="text" class="form-control" value="{{ $row->semester->title ?? 'N/A' }} @if($row->semester && $row->semester->year)(Year {{ $row->semester->year }})@endif" readonly>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="category">{{ __('field_fees_type') }}</label>
                                    <input type="text" class="form-control" value="{{ $row->feesCategory->title ?? 'N/A' }}" readonly>
                                    @if($row->feesCategory)
                                        @if($row->feesCategory->is_first_installment)
                                        <span class="badge badge-info mt-1">1st Installment</span>
                                        @endif
                                        @if($row->feesCategory->is_second_installment)
                                        <span class="badge badge-warning mt-1">2nd Installment</span>
                                        @endif
                                    @endif
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="amount">{{ __('field_amount') }} <span>*</span></label>
                                    <input type="number" class="form-control" name="amount" id="amount" value="{{ old('amount', $row->amount) }}" min="0" step="0.01" required>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_amount') }}
                                    </div>
                                </div>

                                @if($row->feesCategory && $row->feesCategory->is_first_installment && $row->semester && $row->semester->semester_type == 1)
                                <div class="col-md-12">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0 text-primary"><i class="fas fa-list-ul"></i> Fee Breakdown (Form A2)</h6>
                                        <button type="button" class="btn btn-sm btn-success" id="addBreakdownBtn">
                                            <i class="fas fa-plus"></i> Add Item
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mb-3">The sum of all breakdown amounts must equal the total fee amount.</small>
                                    
                                    <div id="breakdownItems">
                                        @foreach($row->breakdowns as $index => $breakdown)
                                        <div class="row mb-2 breakdown-item">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control form-control-sm" name="breakdown_titles[]" placeholder="e.g., Tuition Fee" value="{{ $breakdown->title }}" required>
                                            </div>
                                            <div class="col-md-5">
                                                <input type="number" class="form-control form-control-sm breakdown-amount" name="breakdown_amounts[]" placeholder="0.00" min="0" step="0.01" value="{{ $breakdown->amount }}" required>
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-sm btn-danger remove-breakdown-btn">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    
                                    <div class="mt-2">
                                        <span class="badge badge-light">Total: <span id="breakdownTotal">0.00</span></span>
                                        <span class="badge badge-light">Fee Amount: <span id="feeAmountDisplay">{{ $row->amount }}</span></span>
                                        <span id="breakdownStatus" class="ms-2"></span>
                                    </div>
                                    <hr>
                                </div>
                                @endif

                                <div class="form-group col-md-6">
                                    <label>Due Date <small class="text-muted">(optional)</small></label>
                                    <div class="d-flex">
                                        <select class="form-control me-1" name="due_month" id="due_month" style="width: 60%;">
                                            <option value="">Month</option>
                                            @php $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec']; @endphp
                                            @foreach($months as $m => $name)
                                                <option value="{{ $m }}" @if(old('due_month', $row->due_month) == $m) selected @endif>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" class="form-control" name="due_day" id="due_day" value="{{ old('due_day', $row->due_day) }}" min="1" max="31" placeholder="Day" style="width: 40%;">
                                    </div>
                                    <small class="form-text text-muted">Fixed month and day for the due date</small>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="fine_amount">Fine Amount <small class="text-muted">(optional)</small></label>
                                    <input type="number" class="form-control" name="fine_amount" id="fine_amount" value="{{ old('fine_amount', $row->fine_amount) }}" min="0" step="0.01" placeholder="0.00">
                                    <small class="form-text text-muted">Fine charged after due date</small>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="fine_type">Fine Type <small class="text-muted">(optional)</small></label>
                                    <select class="form-control" name="fine_type" id="fine_type">
                                        <option value="">{{ __('select') }}</option>
                                        <option value="fixed" @if( old('fine_type', $row->fine_type) == 'fixed' ) selected @endif>Fixed Amount</option>
                                        <option value="percentage" @if( old('fine_type', $row->fine_type) == 'percentage' ) selected @endif>Percentage (%)</option>
                                    </select>
                                    <small class="form-text text-muted">How the fine is calculated</small>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="status">{{ __('field_status') }} <span>*</span></label>
                                    <select class="form-control" name="status" id="status" required>
                                        <option value="1" @if( $row->status == 1 ) selected @endif>{{ __('status_active') }}</option>
                                        <option value="0" @if( $row->status == 0 ) selected @endif>{{ __('status_inactive') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_status') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-12">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> {{ __('btn_update') }}</button>
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
    @if($row->feesCategory && $row->feesCategory->is_first_installment && $row->semester && $row->semester->semester_type == 1)
    
    // Update breakdown total on page load
    updateBreakdownTotal();
    
    // Add breakdown item
    $('#addBreakdownBtn').on('click', function() {
        var itemHtml = `
            <div class="row mb-2 breakdown-item">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="breakdown_titles[]" placeholder="e.g., Tuition Fee" required>
                </div>
                <div class="col-md-5">
                    <input type="number" class="form-control form-control-sm breakdown-amount" name="breakdown_amounts[]" placeholder="0.00" min="0" step="0.01" required>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger remove-breakdown-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        $('#breakdownItems').append(itemHtml);
        updateBreakdownTotal();
    });
    
    // Remove breakdown item
    $(document).on('click', '.remove-breakdown-btn', function() {
        $(this).closest('.breakdown-item').remove();
        updateBreakdownTotal();
    });
    
    // Update total when amounts change
    $(document).on('input', '.breakdown-amount, #amount', function() {
        updateBreakdownTotal();
        
        // Update fee amount display
        var feeAmount = parseFloat($('#amount').val()) || 0;
        $('#feeAmountDisplay').text(feeAmount.toFixed(2));
    });
    
    // Function to update breakdown total
    function updateBreakdownTotal() {
        var total = 0;
        $('.breakdown-amount').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        
        var feeAmount = parseFloat($('#amount').val()) || 0;
        
        $('#breakdownTotal').text(total.toFixed(2));
        
        // Validate if totals match
        var statusSpan = $('#breakdownStatus');
        if (Math.abs(total - feeAmount) < 0.01 && total > 0) {
            statusSpan.html('<span class="badge badge-success"><i class="fas fa-check"></i> Valid</span>');
        } else if (total > feeAmount) {
            statusSpan.html('<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Exceeds fee amount</span>');
        } else if (total < feeAmount && total > 0) {
            statusSpan.html('<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Less than fee amount</span>');
        } else {
            statusSpan.html('');
        }
    }
    
    // Form validation on submit
    $('form').on('submit', function(e) {
        var breakdownCount = $('.breakdown-item').length;
        
        if (breakdownCount > 0) {
            var total = 0;
            $('.breakdown-amount').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            
            var feeAmount = parseFloat($('#amount').val()) || 0;
            
            if (Math.abs(total - feeAmount) > 0.01) {
                e.preventDefault();
                alert('Fee Breakdown Validation Failed:\n\nBreakdown total (' + total.toFixed(2) + ') must equal fee amount (' + feeAmount.toFixed(2) + ')');
                return false;
            }
        }
        
        return true;
    });
    
    @endif
});
</script>
@endsection
