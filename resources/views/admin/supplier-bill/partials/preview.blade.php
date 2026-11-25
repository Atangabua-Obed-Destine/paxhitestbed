{{--
    One preview, as SupplierPayablesPreview returns it. Rendered inside the
    review dialog; the dialog's confirm button reads data-ok to decide whether
    it may be pressed.
--}}
<div class="ap-preview" data-ok="{{ $p['ok'] ? '1' : '0' }}">
    @if($p['problems'])
    <div class="ap-note is-bad mb-3">
        <strong>{{ __('This cannot be done yet:') }}</strong>
        <ul class="mb-0 pl-3">
            @foreach($p['problems'] as $problem)<li>{{ $problem }}</li>@endforeach
        </ul>
    </div>
    @endif

    @if($p['warnings'])
    <div class="ap-note is-warn mb-3">
        <strong>{{ __('Check before confirming:') }}</strong>
        <ul class="mb-0 pl-3">
            @foreach($p['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
        </ul>
    </div>
    @endif

    @if($p['summary'])
    <dl class="ap-dl mb-3">
        @foreach($p['summary'] as [$label, $value])
        <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
        @endforeach
    </dl>
    @endif

    @foreach($p['entries'] as $e)
    @if($e)
    <h6 class="mt-3 mb-1">{{ $e['title'] ?? __('Ledger entry to be posted') }}</h6>
    <div class="ap-muted mb-2">{{ $e['date'] }}@if($e['period']) · {{ $e['period'] }}@endif · {{ $e['description'] }}</div>
    <div class="table-responsive">
        <table class="table table-sm ap-table mb-2">
            <thead>
                <tr><th>{{ __('Account') }}</th><th>{{ __('Description') }}</th><th class="ap-num">{{ __('Debit') }}</th><th class="ap-num">{{ __('Credit') }}</th></tr>
            </thead>
            <tbody>
                @foreach($e['lines'] as $l)
                <tr>
                    <td class="text-nowrap">{{ $l['account'] }}</td>
                    <td>{{ $l['description'] }}</td>
                    <td class="ap-num">{{ $l['debit'] > 0 ? format_amount($l['debit']) : '' }}</td>
                    <td class="ap-num">{{ $l['credit'] > 0 ? format_amount($l['credit']) : '' }}</td>
                </tr>
                @endforeach
                <tr class="ap-total-row">
                    <td colspan="2">{{ __('Total') }} @if(abs($e['debit'] - $e['credit']) < 0.01)<span class="ap-chip paid">{{ __('Balanced') }}</span>@else<span class="ap-chip unpaid">{{ __('Not balanced') }}</span>@endif</td>
                    <td class="ap-num">{{ format_amount($e['debit']) }}</td>
                    <td class="ap-num">{{ format_amount($e['credit']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif
    @endforeach

    @foreach($p['effects'] as $effect)
    <h6 class="mt-3 mb-2">{{ $effect['title'] }}</h6>
    <div class="table-responsive">
        <table class="table table-sm ap-table mb-2">
            <thead>
                <tr>@foreach($effect['columns'] as $i => $c)<th class="{{ in_array($i, $effect['numeric'] ?? []) ? 'ap-num' : '' }}">{{ $c }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse($effect['rows'] as $row)
                <tr>@foreach($row as $i => $cell)<td class="{{ in_array($i, $effect['numeric'] ?? []) ? 'ap-num' : '' }}">{{ $cell }}</td>@endforeach</tr>
                @empty
                <tr><td colspan="{{ count($effect['columns']) }}" class="ap-muted">{{ __('Nothing.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endforeach

    @foreach($p['notes'] as $note)
    <div class="ap-muted mt-2">{{ $note }}</div>
    @endforeach
</div>
