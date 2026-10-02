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
                                <th>{{ __('field_paid_by') }}</th>
                                <th>{{ __('field_receipt') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($installment->payments as $payment)
                            <tr @if($payment->isReversed()) class="text-muted" @endif>
                                <td>{{ date('d M Y, h:i A', strtotime($payment->payment_date)) }}</td>
                                <td>
                                    <strong @if($payment->isReversed()) class="text-decoration-line-through" @endif>
                                        {{ number_format($payment->amount, 2) }} {!! $setting->currency_symbol !!}
                                    </strong>
                                    @if($payment->isReversed())
                                        <span class="badge bg-danger">{{ __('Reversed') }}</span>
                                    @endif
                                </td>
                                <td>{{ $payment->payment_method_label }}</td>
                                <td>{{ $payment->reference_no ?? '-' }}</td>
                                <td>
                                    @if($payment->paidBy)
                                        {{ $payment->paidBy->name ?? ($payment->paidBy->first_name . ' ' . $payment->paidBy->last_name) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($payment->hasReceipt())
                                    <a href="{{ $payment->receipt_url }}" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fas fa-file"></i> {{ __('view') }}
                                    </a>
                                    @else
                                    -
                                    @endif
                                </td>
                                <td>
                                    @if(!$payment->isReversed() && auth()->user()->can('payment-plan.reverse'))
                                    {{-- Dismisses this modal as it opens the next: stacked modals leave a stuck backdrop. --}}
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-dismiss="modal"
                                            data-bs-toggle="modal" data-bs-target="#reversePaymentModal-{{ $payment->id }}">
                                        <i class="fas fa-undo"></i> {{ __('Reverse') }}
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @if($payment->note)
                            <tr>
                                <td colspan="7">
                                    <small><strong>{{ __('field_note') }}:</strong> {{ $payment->note }}</small>
                                </td>
                            </tr>
                            @endif
                            @if($payment->isReversed())
                            <tr>
                                <td colspan="7">
                                    <small class="text-danger">
                                        <strong>{{ __('Reversed') }}</strong>
                                        @if($payment->reversed_at) {{ date('d M Y, h:i A', strtotime($payment->reversed_at)) }} @endif
                                        @if($payment->reversal_reason) — {{ $payment->reversal_reason }} @endif
                                    </small>
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>{{ __('total_paid') }}</th>
                                <th><strong>{{ number_format($installment->paid_amount, 2) }} {!! $setting->currency_symbol !!}</strong></th>
                                <th colspan="5"></th>
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

{{-- Reverse confirmations: siblings of the history modal, not nested inside it. --}}
@foreach($installment->payments as $payment)
    @if(!$payment->isReversed() && auth()->user()->can('payment-plan.reverse'))
    <div id="reversePaymentModal-{{ $payment->id }}" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route($route.'.reverse', [$row->id, $payment->id]) }}" method="post">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Reverse this payment') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <p>
                            {{ __('This takes :amount off instalment #:number and off the fee, and puts back the ledger entry, the payment account and the student\'s statement with it. The payment is kept, marked reversed.', [
                                'amount' => number_format($payment->amount, 2),
                                'number' => $installment->installment_number,
                            ]) }}
                        </p>

                        <div class="form-group">
                            <label for="reason-{{ $payment->id }}" class="form-label">{{ __('Why is it being reversed?') }} <span>*</span></label>
                            <textarea class="form-control" name="reason" id="reason-{{ $payment->id }}" rows="3" required
                                      placeholder="{{ __('e.g. recorded on the wrong instalment') }}"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> {{ __('btn_close') }}
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-undo"></i> {{ __('Reverse payment') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach
