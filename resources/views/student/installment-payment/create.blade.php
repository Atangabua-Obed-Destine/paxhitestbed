@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('upload_payment_receipt') }} - {{ __('payment_plan') }}</h5>
                        <a href="{{ route('student.payment-plan.show', $installment->payment_plan_id) }}" class="btn btn-sm btn-secondary float-right">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                    </div>
                    <div class="card-block">
                        <!-- Installment Details -->
                        <div class="alert alert-info">
                            <h6 class="mb-2"><strong>{{ __('installment_details') }}</strong></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_fees_type') }}:</strong> {{ $installment->paymentPlan->fee->category->title ?? '' }}</p>
                                    <p class="mb-1"><strong>{{ __('field_session') }}:</strong> {{ $installment->paymentPlan->fee->studentEnroll->session->title ?? '' }}</p>
                                    <p class="mb-1"><strong>{{ __('field_semester') }}:</strong> {{ $installment->paymentPlan->fee->studentEnroll->semester->title ?? '' }}</p>
                                    <p class="mb-1"><strong>{{ __('field_installment_number') }}:</strong> {{ $installment->installment_number }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_due_date') }}:</strong> 
                                        <span class="{{ $installment->isOverdue() ? 'text-danger' : '' }}">
                                            {{ date('d M Y', strtotime($installment->due_date)) }}
                                            @if($installment->isOverdue())
                                            <span class="badge badge-danger">{{ __('status_overdue') }}</span>
                                            @endif
                                        </span>
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_installment_amount') }}:</strong> 
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$installment->amount, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$installment->amount, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                    </p>
                                    @if($installment->late_fee > 0)
                                    <p class="mb-1"><strong>{{ __('field_late_fee') }}:</strong> 
                                        <span class="text-danger">
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$installment->late_fee, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$installment->late_fee, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                        </span>
                                    </p>
                                    @endif
                                    <p class="mb-1"><strong>{{ __('field_paid_amount') }}:</strong> 
                                        <span class="text-success">
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$installment->paid_amount, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$installment->paid_amount, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                        </span>
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_remaining_balance') }}:</strong> 
                                        <span class="text-danger font-weight-bold h5">
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$installment->remaining_balance, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$installment->remaining_balance, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-2">
                                <i class="fas fa-info-circle"></i> <strong>{{ __('msg_partial_payment_allowed') }}</strong>
                                <br><small>{{ __('msg_installment_payment_notice') }}</small>
                            </div>
                        </div>

                        <!-- Upload Form -->
                        <form class="needs-validation" novalidate method="post" action="{{ route($route.'.store', $installment->id) }}" enctype="multipart/form-data">
                            @csrf

                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label for="receipt_file">{{ __('field_receipt_file') }} <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="receipt_file" id="receipt_file" accept="image/jpeg,image/png,application/pdf" required>
                                    <small class="form-text text-muted">
                                        {{ __('msg_allowed_file_types') }}: JPEG, PNG, PDF ({{ __('msg_max_file_size') }}: 2MB)
                                    </small>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_receipt_file') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="payment_date">{{ __('field_payment_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control date" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_payment_date') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="amount">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" name="amount" id="amount" value="{{ old('amount', $installment->remaining_balance) }}" min="0.01" max="{{ $installment->remaining_balance }}" required>
                                    <small class="form-text text-muted">
                                        {{ __('msg_partial_payment_allowed') }} - Max: {{ number_format($installment->remaining_balance, 2) }} {!! $setting->currency_symbol !!}
                                    </small>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_amount') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-12">
                                    <label for="payment_method">{{ __('field_payment_method') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="payment_method" id="payment_method" required>
                                        <option value="">{{ __('select') }}</option>
                                        <option value="2" {{ old('payment_method') == 2 ? 'selected' : '' }}>{{ __('payment_method_cash') }}</option>
                                        <option value="4" {{ old('payment_method') == 4 ? 'selected' : '' }}>{{ __('payment_method_bank') }}</option>
                                        <option value="6" {{ old('payment_method') == 6 ? 'selected' : '' }}>{{ __('payment_method_mtn_momo') }}</option>
                                        <option value="7" {{ old('payment_method') == 7 ? 'selected' : '' }}>{{ __('payment_method_orange_money') }}</option>
                                        <option value="8" {{ old('payment_method') == 8 ? 'selected' : '' }}>{{ __('payment_method_other') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_payment_method') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-12">
                                    <label for="note">{{ __('field_note') }}</label>
                                    <textarea class="form-control" name="note" id="note" rows="3" placeholder="{{ __('placeholder_payment_note') }}">{{ old('note') }}</textarea>
                                    <small class="form-text text-muted">{{ __('msg_optional_payment_note') }}</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert alert-primary">
                                        <h6><i class="fas fa-info-circle"></i> {{ __('important_information') }}</h6>
                                        <ul class="mb-0">
                                            <li>{{ __('msg_upload_clear_receipt') }}</li>
                                            <li>{{ __('msg_admin_will_verify') }}</li>
                                            <li>{{ __('msg_verification_may_take_time') }}</li>
                                            <li>{{ __('msg_payment_recorded_after_approval') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload"></i> {{ __('btn_upload_receipt') }}
                                </button>
                                <a href="{{ route('student.payment-plan.show', $installment->payment_plan_id) }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                                </a>
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
<script type="text/javascript">
    "use strict";
    // Form Validation
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
</script>
@endsection
