<!-- Cancel Payment Plan Modal -->
<div id="cancelModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form class="needs-validation" novalidate action="{{ route($route.'.cancel', $row->id) }}" method="post">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title text-danger">{{ __('cancel_payment_plan') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        {{ __('are_you_sure_cancel_payment_plan') }}
                    </div>

                    <p><strong>{{ __('field_student') }}:</strong> {{ $row->student->first_name }} {{ $row->student->last_name }}</p>
                    <p><strong>{{ __('field_total_amount') }}:</strong> {{ number_format($row->total_amount, 2) }} {!! $setting->currency_symbol !!}</p>
                    <p><strong>{{ __('field_paid') }}:</strong> {{ number_format($row->total_paid, 2) }} {!! $setting->currency_symbol !!}</p>

                    <div class="form-group mt-3">
                        <label for="cancellation_reason" class="form-label">{{ __('field_cancellation_reason') }} <span>*</span></label>
                        <textarea class="form-control" name="cancellation_reason" id="cancellation_reason" rows="3" required></textarea>
                        <div class="invalid-feedback">
                            {{ __('required_field') }} {{ __('field_cancellation_reason') }}
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('btn_close') }}
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> {{ __('btn_cancel_plan') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
