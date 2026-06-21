<!-- Pay Installment Modal -->
<div id="payModal-{{ $installment->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form class="needs-validation" novalidate action="{{ route($route.'.process-payment') }}" method="post" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="payment_plan_id" value="{{ $row->id }}">
                <input type="hidden" name="installment_id" value="{{ $installment->id }}">

                <div class="modal-header">
                    <h5 class="modal-title">{{ __('pay_installment') }} #{{ $installment->installment_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Installment Details -->
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>{{ __('field_installment_amount') }}:</strong></p>
                                <h5>{{ number_format($installment->amount, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                            @if($installment->late_fee > 0)
                            <div class="col-md-4">
                                <p><strong>{{ __('field_late_fee') }}:</strong></p>
                                <h5 class="text-danger">{{ number_format($installment->late_fee, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                            @endif
                            <div class="col-md-4">
                                <p><strong>{{ __('field_total_due') }}:</strong></p>
                                <h5 class="text-primary">{{ number_format($installment->amount + ($installment->late_fee ?? 0), 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                        </div>
                        @if($installment->paid_amount > 0)
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <p><strong>{{ __('field_already_paid') }}:</strong></p>
                                <h5 class="text-success">{{ number_format($installment->paid_amount, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                            <div class="col-md-6">
                                <p><strong>{{ __('field_remaining_balance') }}:</strong></p>
                                <h5 class="text-warning">{{ number_format($installment->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</h5>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Payment Form -->
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="payment_date-{{ $installment->id }}" class="form-label">{{ __('field_payment_date') }} <span>*</span></label>
                            <input type="date" class="form-control" name="payment_date" id="payment_date-{{ $installment->id }}" value="{{ date('Y-m-d') }}" required>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_payment_date') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="amount-{{ $installment->id }}" class="form-label">{{ __('field_amount_to_pay') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                            <input type="number" class="form-control bg-warning" name="amount" id="amount-{{ $installment->id }}" 
                                   value="{{ number_format($installment->remaining_balance, 2, '.', '') }}" 
                                   step="0.01" min="0.01" max="{{ $installment->remaining_balance }}" required>
                            <small class="form-text text-muted">{{ __('max') }}: {{ number_format($installment->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</small>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_amount') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="payment_method-{{ $installment->id }}" class="form-label">{{ __('field_payment_method') }} <span>*</span></label>
                            <select class="form-control" name="payment_method" id="payment_method-{{ $installment->id }}" required>
                                <option value="">{{ __('select') }}</option>
                                <option value="1">{{ __('payment_method_cash') }}</option>
                                <option value="2">{{ __('payment_method_cheque') }}</option>
                                <option value="3">{{ __('payment_method_bank') }}</option>
                                <option value="6">{{ __('payment_method_mobile_money') }}</option>
                                <option value="7">{{ __('payment_method_other') }}</option>
                            </select>
                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_payment_method') }}
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="reference_no" class="form-label">{{ __('field_reference_number') }}</label>
                            <input type="text" class="form-control" name="reference_no" id="reference_no" value="{{ old('reference_no') }}">
                        </div>

                        <div class="form-group col-md-12">
                            <label for="receipt" class="form-label">{{ __('field_receipt') }}</label>
                            <input type="file" class="form-control" name="receipt" id="receipt" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">{{ __('allowed_formats') }}: PDF, JPG, PNG ({{ __('max') }} 2MB)</small>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="note" class="form-label">{{ __('field_note') }}</label>
                            <textarea class="form-control" name="note" id="note" rows="2">{{ old('note') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('btn_close') }}
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-money-check"></i> {{ __('btn_process_payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
