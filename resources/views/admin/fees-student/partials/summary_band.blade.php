{{--
  What the fees come to.

  Billed, collected, outstanding — the three figures the report is run to get —
  then the proportion as a bar, then the status counts.

  The figures come from App\Support\FeesSummary, which normalises the two
  different names the list and the report give the same quantities. Nothing
  here queries anything: it reads statistics the controller already computed
  from the rows on the page, so the band cannot disagree with the table under
  it.

  Expects $stats (either controller's shape, or absent) and optionally
  $bandTitle.
--}}

@php
    $fx = \App\Support\FeesSummary::from($stats ?? null);

    $decimals = $setting->decimal_place ?? 2;
    $currency = $setting->currency_symbol ?? '';

    $money = fn ($value) => number_format((float) $value, $decimals);
@endphp

@if($fx['has'])
<div class="fees-band">
    <div class="fees-figures">
        <div class="fees-figure">
            <div class="fees-label">{{ __('fees_billed') }}</div>
            <div class="v fees-num">{{ $money($fx['billed']) }}<span class="c">{!! $currency !!}</span></div>
        </div>

        <div class="fees-figure">
            <div class="fees-label">{{ __('fees_collected') }}</div>
            <div class="v fees-num">{{ $money($fx['collected']) }}<span class="c">{!! $currency !!}</span></div>
        </div>

        <div class="fees-figure is-out">
            <div class="fees-label">{{ __('fees_outstanding') }}</div>
            <div class="v fees-num">{{ $money($fx['outstanding']) }}<span class="c">{!! $currency !!}</span></div>
        </div>

        {{-- Only when there is any. A nil overpayment figure beside three real
             ones reads as a fourth thing to check rather than as nothing. --}}
        @if($fx['overpaid'] > 0)
        <div class="fees-figure is-over">
            <div class="fees-label">{{ __('fees_overpaid_held') }}</div>
            <div class="v fees-num">{{ $money($fx['overpaid']) }}<span class="c">{!! $currency !!}</span></div>
        </div>
        @endif
    </div>

    @if($fx['billed'] > 0)
    <div class="fees-bar-wrap">
        {{-- role="img" with the figure in the label, because the bar is the
             only place the proportion is drawn. --}}
        <div class="fees-bar" role="img"
             aria-label="{{ $fx['rate'] }}% {{ __('fees_collected') }}">
            <span style="width: {{ $fx['rate'] }}%;"></span>
        </div>
        <div class="fees-bar-note">
            <b>{{ $fx['rate'] }}%</b> {{ __('fees_of_billed_collected') }}
        </div>
    </div>
    @endif

    <div class="fees-chips">
        <span class="fees-chip"><b>{{ number_format($fx['counts']['total']) }}</b>{{ __('fees_count_total') }}</span>
        <span class="fees-chip is-flow"><b>{{ number_format($fx['counts']['paid']) }}</b>{{ __('fees_count_paid') }}</span>
        <span class="fees-chip is-warn"><b>{{ number_format($fx['counts']['partial']) }}</b>{{ __('fees_count_partial') }}</span>
        <span class="fees-chip is-bad"><b>{{ number_format($fx['counts']['unpaid']) }}</b>{{ __('fees_count_unpaid') }}</span>

        @if($fx['counts']['cancelled'] > 0)
        <span class="fees-chip"><b>{{ number_format($fx['counts']['cancelled']) }}</b>{{ __('fees_count_cancelled') }}</span>
        @endif

        @if($fx['counts']['overpaid'] > 0)
        <span class="fees-chip is-flow"><b>{{ number_format($fx['counts']['overpaid']) }}</b>{{ __('fees_count_overpaid') }}</span>
        @endif
    </div>
</div>
@endif
