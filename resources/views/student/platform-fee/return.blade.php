@extends('student.layouts.master')
@section('title', __('Payment result'))

@section('content')
{{--
    Where Fapshi sends the student back to.

    The page never takes the redirect's word for it: the controller has already
    asked the gateway what happened and what it says is rendered here. A student
    who abandons the checkout and then edits the URL lands on "not completed",
    not on a success page.

    The three outcomes a student can actually be in need different things from
    them, so each gets its own instruction rather than a single generic message:
    paid (go to the portal), still processing (wait, and this page refreshes
    itself), or not paid (try again).
--}}
<style>
    .result-wrap { max-width: 640px; margin: 28px auto; padding: 0 16px; }
    .result-card {
        background: #fff; border-radius: 14px; padding: 32px 28px;
        box-shadow: 0 2px 14px rgba(0,0,0,.08); text-align: center;
    }
    .result-icon { font-size: 54px; margin-bottom: 14px; }
    .result-card h2 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
    .result-card p { color: #5a6474; line-height: 1.6; margin-bottom: 8px; }
    .is-good .result-icon { color: #16a34a; }
    .is-wait .result-icon { color: #d97706; }
    .is-bad  .result-icon { color: #dc2626; }
    .result-meta {
        margin-top: 22px; padding-top: 18px; border-top: 1px solid #eef0f4;
        font-size: 12.5px; color: #78808d; text-align: left;
    }
    .result-meta div { margin-bottom: 4px; }
    .result-actions { margin-top: 22px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .result-actions .btn { min-width: 170px; }
</style>

@php
    $credited = $result['credited'] ?? false;
    $status = $result['status'] ?? 'pending';
    $waiting = in_array($status, ['created', 'pending'], true);

    $tone = $credited ? 'is-good' : ($waiting ? 'is-wait' : 'is-bad');
@endphp

<div class="result-wrap">
    <div class="result-card {{ $tone }}">

        @if($credited)
            <div class="result-icon"><i class="fas fa-check-circle"></i></div>
            <h2>{{ __('Payment received') }}</h2>
            <p>
                {{ __('Your platform access fee for this academic year is settled. Your portal is open — there is nothing further to do and nothing to upload.') }}
            </p>

        @elseif($waiting)
            <div class="result-icon"><i class="fas fa-hourglass-half"></i></div>
            <h2>{{ __('Still going through') }}</h2>
            <p>
                {{ __('The mobile money operator has not confirmed this payment yet. This can take a minute or two.') }}
            </p>
            <p>
                <strong>{{ __('You do not need to pay again.') }}</strong>
                {{ __('This page checks for you every 15 seconds, and your portal opens on its own as soon as the payment lands.') }}
            </p>

        @elseif($status === 'refundable')
            <div class="result-icon"><i class="fas fa-info-circle"></i></div>
            <h2>{{ __('This payment was not needed') }}</h2>
            <p>
                {{ __('Your fee for this academic year was already settled before this payment arrived, so you have paid twice.') }}
            </p>
            <p>{{ __('The registry has been notified and will arrange a refund. Keep your mobile money message as proof.') }}</p>

        @elseif($status === 'mismatched')
            <div class="result-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h2>{{ __('We could not match this payment') }}</h2>
            <p>{{ $result['message'] ?? '' }}</p>
            <p>{{ __('Your money has not been lost and the registry has been notified. Please take your mobile money message to the registry so they can settle it.') }}</p>

        @elseif($status === 'expired')
            <div class="result-icon"><i class="fas fa-clock"></i></div>
            <h2>{{ __('This payment link expired') }}</h2>
            <p>{{ __('A payment link lasts 24 hours. Nothing was charged. Start again and you will get a fresh one.') }}</p>

        @else
            <div class="result-icon"><i class="fas fa-times-circle"></i></div>
            <h2>{{ __('The payment did not go through') }}</h2>
            <p>{{ $result['message'] ?? __('The payment was not completed.') }}</p>
            <p>{{ __('Nothing has been charged. You can try again — check you have enough in your mobile money wallet first.') }}</p>
        @endif

        <div class="result-actions">
            @if($credited)
                <a href="{{ route('student.dashboard.index') }}" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> {{ __('Go to my portal') }}
                </a>
            @elseif($waiting)
                <a href="{{ route('payment.fapshi.return', $transaction->id) }}" class="btn btn-primary" data-no-loading>
                    <i class="fas fa-sync"></i> {{ __('Check again now') }}
                </a>
            @else
                <a href="{{ route('student.platform-fee.payment') }}" class="btn btn-primary">
                    <i class="fas fa-redo"></i> {{ __('Try again') }}
                </a>
            @endif
        </div>

        {{-- The reference, always. It is the one thing that lets the registry
             trace a payment, and a student who leaves this page without it has
             nothing to bring them. --}}
        <div class="result-meta">
            <div><strong>{{ __('Reference') }}:</strong> {{ $transaction->external_id }}</div>
            @if($transaction->trans_id)
                <div><strong>{{ __('Gateway reference') }}:</strong> {{ $transaction->trans_id }}</div>
            @endif
            @if($transaction->financial_trans_id)
                <div><strong>{{ __('Operator reference') }}:</strong> {{ $transaction->financial_trans_id }}</div>
            @endif
            <div>
                <strong>{{ __('Amount') }}:</strong>
                {{ number_format((float) $transaction->charged_amount, 0, '.', ',') }} {{ $transaction->currency }}
            </div>
            <div><strong>{{ __('Started') }}:</strong> {{ $transaction->created_at?->format('j M Y, H:i') }}</div>
        </div>
    </div>
</div>

@if($waiting)
<script>
    /*
     * Fapshi notifies this server directly, and the five-minute reconciliation
     * catches anything that notification misses — so this refresh is for the
     * student standing in front of the screen, not for correctness. Fifteen
     * seconds stays well inside the gateway's six-checks-a-minute limit.
     */
    setTimeout(function () {
        window.location.reload();
    }, 15000);
</script>
@endif
@endsection
