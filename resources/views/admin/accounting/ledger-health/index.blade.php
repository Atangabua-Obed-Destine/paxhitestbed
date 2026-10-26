@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<style>
    /* Status wears an icon and a word as well as a colour: a finding that can
       only be seen by people who distinguish red from green is a finding half
       the room misses. */
    .lh {
        --ok:       #0ca30c;
        --warning:  #b07d10;
        --critical: #d03b3b;
        --ink:      #2c3e50;
        --muted:    #8a97ab;
        --rule:     #e3e7ee;
    }

    .lh-head {
        background: #fff; border: 1px solid var(--rule); border-radius: 10px;
        padding: 1.1rem 1.25rem; margin-bottom: 1rem;
        border-left: 4px solid var(--ok);
    }
    .lh-head.is-warning { border-left-color: var(--warning); }
    .lh-head.is-critical { border-left-color: var(--critical); }
    .lh-head h4 { margin: 0 0 .25rem; font-size: 1.1rem; font-weight: 700; color: #182b49; }
    .lh-head p { margin: 0; color: #55637a; font-size: .88rem; line-height: 1.6; }
    .lh-scale { margin-top: .6rem; font-size: .78rem; color: var(--muted); }

    .lh-filters { display: flex; gap: .35rem; flex-wrap: wrap; margin-bottom: 1rem; }

    .lh-card {
        background: #fff; border: 1px solid var(--rule); border-radius: 10px;
        margin-bottom: .85rem; border-left: 4px solid var(--rule);
    }
    .lh-card.is-critical { border-left-color: var(--critical); }
    .lh-card.is-warning { border-left-color: var(--warning); }
    .lh-card.is-ok { border-left-color: var(--ok); }

    .lh-row { display: flex; align-items: flex-start; gap: .75rem; padding: .9rem 1.15rem; }
    .lh-ico { font-size: 1rem; line-height: 1.5; flex-shrink: 0; }
    .lh-ico.is-ok { color: var(--ok); }
    .lh-ico.is-warning { color: var(--warning); }
    .lh-ico.is-critical { color: var(--critical); }

    .lh-body { flex-grow: 1; min-width: 0; }
    .lh-label { font-weight: 700; color: #182b49; font-size: .93rem; }
    .lh-group {
        font-size: .66rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase;
        color: var(--muted); margin-left: .45rem;
    }
    .lh-detail { font-size: .85rem; color: #55637a; margin: .25rem 0 0; line-height: 1.55; }
    .lh-action {
        font-size: .83rem; color: #33507d; margin: .35rem 0 0; font-weight: 600;
    }
    /* Scoped to the whole panel, not just the action line. The command in the
       header was falling through to the theme's default <code> colour, so the
       same command appeared in two different styles on one page — which reads
       as two different kinds of thing rather than one. */
    .lh code {
        background: #f2f5fa; border: 1px solid #dde4ee; border-radius: 4px;
        padding: .05rem .3rem; color: #2c4a7a; font-size: .8rem;
        white-space: nowrap;
    }
    .lh-figure {
        text-align: right; flex-shrink: 0; font-variant-numeric: tabular-nums;
    }
    .lh-count { font-size: 1.25rem; font-weight: 800; color: #182b49; line-height: 1.1; }
    .lh-value { font-size: .78rem; color: var(--muted); }

    .lh-rows { border-top: 1px solid #f1f3f7; padding: 0; margin: 0; }
    .lh-rows summary {
        padding: .55rem 1.15rem; cursor: pointer; font-size: .8rem; font-weight: 700;
        color: #33507d; list-style: none;
    }
    .lh-rows summary::-webkit-details-marker { display: none; }
    .lh-rows summary::before { content: '▸ '; }
    .lh-rows[open] summary::before { content: '▾ '; }
    .lh-rows table { width: 100%; font-size: .8rem; margin-bottom: .5rem; }
    .lh-rows th, .lh-rows td { padding: .3rem .6rem; border-top: 1px solid #f1f3f7; }
    .lh-rows th {
        font-size: .66rem; text-transform: uppercase; letter-spacing: .7px;
        color: var(--muted); font-weight: 800;
    }
    .lh-rows td.n { text-align: right; font-variant-numeric: tabular-nums; }

    .lh-passing .lh-row { padding: .5rem 1.15rem; }
    .lh-passing .lh-label { font-weight: 600; font-size: .87rem; color: #55637a; }
</style>
@endpush

@section('content')
@php
    $icons = ['ok' => 'fa-check-circle', 'warning' => 'fa-exclamation-triangle', 'critical' => 'fa-times-circle'];

    // Which columns a check's rows carry varies by check, so the table is
    // built from whatever the first row actually has rather than from a list
    // written here that would quietly go stale.
    $columnsOf = function (array $rows) {
        return $rows === [] ? [] : array_keys((array) $rows[0]);
    };

    $isMoney = fn ($key) => str_contains($key, 'amount') || str_contains($key, 'debit')
        || str_contains($key, 'credit') || str_contains($key, 'total');
@endphp

<div class="main-body lh">
    <div class="page-wrapper">

        <div class="lh-head {{ $worst === 'ok' ? '' : 'is-' . $worst }}">
            <h4>
                <i class="fas {{ $icons[$worst] }}" style="color: var(--{{ $worst }})"></i>
                @if($worst === 'ok')
                    {{ __('Every transaction, mapping and journal entry agrees.') }}
                @else
                    {{ trans_choice(
                        '{1}:count check found something|[2,*]:count checks found something',
                        $concerns, ['count' => $concerns]) }}
                @endif
            </h4>
            <p>
                {{ __('Three records describe the same money — the transaction, the mapping that says which accounts it posts to, and the journal entry. An edit is meant to carry all three. This asks whether it did.') }}
                @if($worst === 'critical')
                    <br><strong>{{ __('Some of what follows changes what the reports say. Settle it before presenting figures.') }}</strong>
                @endif
            </p>
            <div class="lh-scale">
                {{ number_format($scale['mappings']) }} {{ __('transactions mapped') }}
                &nbsp;·&nbsp; {{ number_format($scale['entries']) }} {{ __('journal entries') }},
                {{ number_format($scale['live']) }} {{ __('live') }}
                &nbsp;·&nbsp; {{ __('checked') }} {{ $checkedAt->format('j M Y, H:i') }}
                &nbsp;·&nbsp; <code>php artisan accounting:ledger-doctor</code>
            </div>
        </div>

        <div class="lh-filters">
            <a href="{{ route('admin.accounting.ledger-health') }}"
               class="btn btn-sm {{ $group ? 'btn-outline-primary' : 'btn-primary' }}">
                {{ __('Everything') }}
            </a>
            @foreach($groups as $name)
                <a href="{{ route('admin.accounting.ledger-health', ['group' => $name]) }}"
                   class="btn btn-sm {{ strcasecmp((string) $group, $name) === 0 ? 'btn-primary' : 'btn-outline-primary' }}">
                    {{ __($name) }}
                </a>
            @endforeach
            <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-sm btn-light ms-auto">
                <i class="fas fa-book me-1"></i>{{ __('Journal entries') }}
            </a>
            <a href="{{ route('admin.accounting.mappings.transactions') }}" class="btn btn-sm btn-light">
                <i class="fas fa-link me-1"></i>{{ __('Transaction mappings') }}
            </a>
        </div>

        @foreach($findings as $check)
            <div class="lh-card is-{{ $check['state'] }}">
                <div class="lh-row">
                    <div class="lh-ico is-{{ $check['state'] }}">
                        <i class="fas {{ $icons[$check['state']] }}"></i>
                    </div>
                    <div class="lh-body">
                        <span class="lh-label">{{ $check['label'] }}</span>
                        <span class="lh-group">{{ $check['group'] }}</span>
                        <p class="lh-detail">{{ $check['detail'] }}</p>
                        @if($check['action'])
                            <p class="lh-action">
                                {{-- A command in the text is marked up as one, so it can be
                                     copied rather than retyped from a sentence. --}}
                                @if(str_contains($check['action'], 'php artisan'))
                                    @php([$before, $cmd] = explode('php artisan', $check['action'], 2))
                                    {{ trim($before) }} <code>php artisan{{ rtrim($cmd, '.') }}</code>
                                @else
                                    {{ $check['action'] }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <div class="lh-figure">
                        <div class="lh-count">{{ number_format($check['count']) }}</div>
                        @if($check['value'] > 0)
                            <div class="lh-value">{{ number_format($check['value'], 2) }}</div>
                        @endif
                    </div>
                </div>

                @if(!empty($check['rows']))
                    <details class="lh-rows">
                        <summary>
                            {{ trans_choice('{1}Show the row|[2,*]Show the :count rows',
                                count($check['rows']), ['count' => count($check['rows'])]) }}
                        </summary>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        @foreach($columnsOf($check['rows']) as $column)
                                            <th class="{{ $isMoney($column) ? 'n' : '' }}">
                                                {{ str_replace('_', ' ', $column) }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($check['rows'] as $row)
                                    <tr>
                                        @foreach((array) $row as $key => $value)
                                            <td class="{{ $isMoney($key) ? 'n' : '' }}">
                                                {{ $isMoney($key) && is_numeric($value)
                                                    ? number_format((float) $value, 2)
                                                    : $value }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>
        @endforeach

        @if(!empty($passing))
            <div class="lh-card is-ok lh-passing">
                <div class="lh-row">
                    <div class="lh-ico is-ok"><i class="fas fa-check-circle"></i></div>
                    <div class="lh-body">
                        <span class="lh-label" style="font-weight:700; color:#182b49;">
                            {{ trans_choice('{1}:count check passed|[2,*]:count checks passed',
                                count($passing), ['count' => count($passing)]) }}
                        </span>
                        <p class="lh-detail">
                            {{ __('Listed so it is clear what was actually examined, not only what failed.') }}
                        </p>
                    </div>
                </div>
                @foreach($passing as $check)
                    <div class="lh-row" style="border-top:1px solid #f1f3f7;">
                        <div class="lh-ico is-ok"><i class="fas fa-check"></i></div>
                        <div class="lh-body">
                            <span class="lh-label">{{ $check['label'] }}</span>
                            <span class="lh-group">{{ $check['group'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
@endsection
