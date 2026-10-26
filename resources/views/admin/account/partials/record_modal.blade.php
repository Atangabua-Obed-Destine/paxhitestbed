{{--
  One income or expense, read back.

  The layout this replaces set every label in a <mark> tag — which renders as
  blue text on a yellow highlight — and separated each line with an <hr/>. The
  information was all there; it read as a highlighter had been over it.

  Here the record leads with its amount, because that is what anyone opening it
  came to check, and the rest is a label/value list on hairlines.

  Rendered inside the list's loop, so it has $row, $title, $path and $view.
--}}

@php
    $decimals = $setting->decimal_place ?? 2;
    $currency = $setting->currency_symbol ?? '';
    $dateFormat = $setting->date_format ?? 'Y-m-d';

    $hasFile = $row->attach && is_file('uploads/'.$path.'/'.$row->attach);
@endphp

<div id="showModal-{{ $row->id }}" class="modal fade acct-record" tabindex="-1"
     aria-labelledby="showModalLabel-{{ $row->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <div class="acct-label">{{ $title }}</div>
                    <h5 class="modal-title mt-1" id="showModalLabel-{{ $row->id }}" style="font-size:.95rem;font-weight:700;">
                        {{ \Carbon\Carbon::parse($row->date)->format($dateFormat) }}
                        @if($row->invoice_id)
                        <span class="acct-ref ms-2">{{ $row->invoice_id }}</span>
                        @endif
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('btn_close') }}"></button>
            </div>

            <div class="modal-body">
                <div class="acct-record-head">
                    <div class="acct-label">{{ __('field_amount') }}</div>
                    <div class="v acct-num mt-1">
                        {{ number_format((float) $row->amount, $decimals) }}<span class="acct-cur" style="font-size:.9rem;margin-left:.25rem;">{!! $currency !!}</span>
                    </div>
                    <div class="t">{{ $row->title }}</div>
                </div>

                <dl class="acct-rows mb-0 pt-2 pb-2">
                    <div class="acct-row">
                        <dt>{{ __('field_category') }}</dt>
                        <dd><span class="acct-tag acct-tag-flow">{{ $row->category->title ?? '—' }}</span></dd>
                    </div>

                    <div class="acct-row">
                        <dt>{{ __('field_date') }}</dt>
                        <dd>{{ \Carbon\Carbon::parse($row->date)->format('j F Y') }}</dd>
                    </div>

                    <div class="acct-row">
                        <dt>{{ __('field_payment_method') }}</dt>
                        {{-- One vocabulary, from App\Support\PaymentMethod. The chain
                             this replaces read an older numbering than the form that
                             wrote the value, so a Bank Transfer showed here as "Cash". --}}
                        <dd>{{ \App\Support\PaymentMethod::label($row->payment_method) }}</dd>
                    </div>

                    @if($row->paymentAccount)
                    <div class="acct-row">
                        <dt>{{ __('field_payment_account') }}</dt>
                        <dd>{{ $row->paymentAccount->title }}</dd>
                    </div>
                    @endif

                    @if($row->invoice_id)
                    <div class="acct-row">
                        <dt>{{ __('field_invoice_id') }}</dt>
                        <dd class="acct-num">{{ $row->invoice_id }}</dd>
                    </div>
                    @endif

                    @if($row->reference)
                    <div class="acct-row">
                        <dt>{{ __('field_reference') }}</dt>
                        <dd>{{ $row->reference }}</dd>
                    </div>
                    @endif

                    <div class="acct-row">
                        <dt>{{ __('field_recorded_by') }}</dt>
                        <dd>
                            {{ $row->recordedBy->name ?? '—' }}
                            @if($row->recordedBy->staff_id ?? null)
                            <span class="acct-ref ms-1">#{{ $row->recordedBy->staff_id }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="acct-row">
                        <dt>{{ __('field_attach') }}</dt>
                        <dd>
                            @if($hasFile)
                            <a href="{{ asset('uploads/'.$path.'/'.$row->attach) }}" target="_blank" rel="noopener">
                                <i class="fas fa-paperclip me-1"></i>{{ $row->attach }}
                            </a>
                            @else
                            <span style="font-weight:500;color:var(--muted);">{{ __('acc_no_attachment') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if($row->note)
                <div class="acct-note">
                    <div class="acct-label mb-1">{{ __('field_note') }}</div>
                    {!! $row->note !!}
                </div>
                @endif
            </div>

            <div class="modal-footer">
                @if($hasFile)
                <a href="{{ asset('uploads/'.$path.'/'.$row->attach) }}" class="acct-btn" download>
                    <i class="fas fa-download"></i>{{ __('btn_download') }}
                </a>
                @endif

                @can($access.'-edit')
                <a href="{{ route($route.'.edit', $row->id) }}" class="acct-btn">
                    <i class="far fa-edit"></i>{{ __('btn_edit') }}
                </a>
                @endcan

                <button type="button" class="acct-btn acct-btn-main" data-bs-dismiss="modal">
                    {{ __('btn_close') }}
                </button>
            </div>

        </div>
    </div>
</div>
