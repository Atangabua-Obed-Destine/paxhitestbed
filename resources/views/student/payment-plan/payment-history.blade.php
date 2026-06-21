<!-- Payment History Modal -->
<div id="historyModal-{{ $installment->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('payment_history') }} - {{ __('installment') }} #{{ $installment->installment_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('field_date') }}</th>
                                <th>{{ __('field_amount') }}</th>
                                <th>{{ __('field_payment_method') }}</th>
                                <th>{{ __('field_reference_number') }}</th>
                                <th>{{ __('field_receipt') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($installment->payments as $payment)
                            <tr>
                                <td>{{ date('d M Y, h:i A', strtotime($payment->payment_date)) }}</td>
                                <td><strong>{{ number_format($payment->amount, 2) }} {!! $setting->currency_symbol !!}</strong></td>
                                <td>{{ $payment->payment_method_label }}</td>
                                <td>{{ $payment->reference_no ?? '-' }}</td>
                                <td>
                                    @if($payment->hasReceipt())
                                    <a href="{{ $payment->receipt_url }}" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fas fa-file"></i> {{ __('view') }}
                                    </a>
                                    @else
                                    -
                                    @endif
                                </td>
                            </tr>
                            @if($payment->note)
                            <tr>
                                <td colspan="5">
                                    <small><strong>{{ __('field_note') }}:</strong> {{ $payment->note }}</small>
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>{{ __('total_paid') }}</th>
                                <th><strong>{{ number_format($installment->paid_amount, 2) }} {!! $setting->currency_symbol !!}</strong></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> {{ __('btn_close') }}
                </button>
            </div>
        </div>
    </div>
</div>
