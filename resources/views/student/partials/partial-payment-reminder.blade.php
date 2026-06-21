@if($fee->isPartiallyPaid())
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fa-2x mr-3"></i>
        <div>
            <h6 class="alert-heading mb-1"><strong>{{ __('msg_partial_payment_reminder_title') }}</strong></h6>
            <p class="mb-1">
                {{ __('msg_partial_payment_reminder_message') }}
            </p>
            <div class="mt-2">
                <strong>{{ __('field_paid_amount') }}:</strong> 
                @if(isset($setting->decimal_place))
                {{ number_format((float)$fee->paid_amount, $setting->decimal_place, '.', '') }} 
                @else
                {{ number_format((float)$fee->paid_amount, 2, '.', '') }} 
                @endif 
                {!! $setting->currency_symbol !!}
                <span class="mx-2">|</span>
                <strong class="text-danger">{{ __('field_remaining_balance') }}:</strong> 
                @if(isset($setting->decimal_place))
                {{ number_format((float)$fee->remaining_balance, $setting->decimal_place, '.', '') }} 
                @else
                {{ number_format((float)$fee->remaining_balance, 2, '.', '') }} 
                @endif 
                {!! $setting->currency_symbol !!}
            </div>
            @php
                $pendingReceipt = $fee->pendingReceipt;
            @endphp
            @if(!$pendingReceipt)
            <div class="mt-2">
                <a href="{{ route('student.manual-payment.create', $fee->id) }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-upload"></i> {{ __('btn_pay_remaining_balance') }}
                </a>
            </div>
            @endif
        </div>
    </div>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif
