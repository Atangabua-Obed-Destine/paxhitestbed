@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<style>
    /* Chart roles, not raw hex, so the palette swaps in one place. These two
       hues are validated as a categorical pair (CVD ΔE 24.7, normal 33.6) and
       are re-used as the diverging pair for the net chart: cool for a surplus,
       warm for a deficit, neutral at zero. */
    .viz {
        --surface:        #ffffff;
        --text-primary:   #0b0b0b;
        --text-secondary: #52514e;
        --text-muted:     #8a97ab;
        --gridline:       #e1e0d9;
        --axis:           #c3c2b7;
        --series-in:      #2a78d6;   /* money in  */
        --series-out:     #eb6834;   /* money out */
        --good:           #0ca30c;
        --warning:        #fab219;
        --critical:       #d03b3b;

        /* Fee collection is ORDERED severity — fully paid, partly paid, nothing
           paid — so it takes the status roles rather than a new categorical
           set. It cannot reuse the three above as fills: --warning #fab219
           sits outside the lightness band at 0.811 and reaches only 1.79:1 on
           this surface, which is invisible as a bar segment. These are the
           same roles at bar weight, validated in the order they stack:
             all checks pass — worst adjacent CVD ΔE 9.8 (protan),
             normal-vision 20.7, all three >= 3:1 on the surface. */
        --paid-full:      #0f7a3d;
        --paid-part:      #b8860b;
        --paid-none:      #b3261e;
    }

    .db-toolbar {
        display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px;
        padding: 1rem 1.15rem; margin-bottom: 1rem;
    }
    .db-field label {
        display: block; font-size: .7rem; font-weight: 800; letter-spacing: .8px;
        text-transform: uppercase; color: #8a97ab; margin-bottom: .25rem;
    }
    .db-field select { min-width: 190px; }
    .db-actions { margin-left: auto; display: flex; gap: .35rem; flex-wrap: wrap; }

    /* A stat tile is not a chart: one number, its label, and what it means. */
    .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(215px, 1fr)); gap: .85rem; margin-bottom: 1.1rem; }
    .tile {
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px; padding: 1rem 1.15rem;
    }
    .tile .label {
        font-size: .7rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: var(--text-muted); margin-bottom: .2rem;
    }
    .tile .value { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.15; }
    .tile .note { font-size: .78rem; color: var(--text-secondary); margin-top: .35rem; line-height: 1.45; }
    .tile.in .value { color: var(--series-in); }
    .tile.out .value { color: var(--series-out); }
    .tile.deficit .value { color: var(--critical); }

    .status-chip {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .72rem; font-weight: 700; padding: .1rem .45rem; border-radius: 4px;
        border: 1px solid; margin-top: .3rem;
    }
    .chip-good { color: #056b05; border-color: #b5e3b5; background: #eefaee; }
    .chip-warning { color: #7a5312; border-color: #f2dca7; background: #fdf8ea; }
    .chip-critical { color: #8a1f1f; border-color: #eab8b8; background: #fdeeee; }
    .chip-none { color: #55637a; border-color: #d7dee7; background: #f4f6f9; }

    /* ---- Can these figures be presented? -------------------------------
       Above the charts on purpose. A quarter of the income sitting on no
       line is not a footnote, and the accountant should meet it here rather
       than a board member meeting it across the table. */
    .conf {
        background: #fff; border: 1px solid #e3e7ee; border-left: 4px solid var(--good);
        border-radius: 10px; margin-bottom: 1.1rem;
    }
    .conf.has-warning { border-left-color: var(--warning); }
    .conf.has-critical { border-left-color: var(--critical); }
    .conf-head {
        display: flex; align-items: center; gap: .6rem; width: 100%;
        background: transparent; border: 0; text-align: left;
        padding: .9rem 1.15rem; font-weight: 700; color: #182b49; cursor: pointer;
    }
    .conf-head .count { margin-left: auto; font-size: .78rem; font-weight: 700; }
    .conf-list { border-top: 1px solid #eef1f5; }
    .conf-row {
        display: grid; grid-template-columns: 22px minmax(190px, 1.1fr) 150px 2fr;
        gap: .75rem; align-items: start;
        padding: .75rem 1.15rem; border-bottom: 1px solid #f3f5f8;
    }
    .conf-row:last-child { border-bottom: 0; }
    .conf-row .ico { font-size: .95rem; line-height: 1.5; }
    .conf-row .ico.good { color: var(--good); }
    .conf-row .ico.warning { color: #b07d10; }
    .conf-row .ico.critical { color: var(--critical); }
    .conf-row .name { font-weight: 700; color: #182b49; font-size: .88rem; }
    .conf-row .fig {
        font-variant-numeric: tabular-nums; font-weight: 800; font-size: .95rem;
        color: var(--text-primary); text-align: right; white-space: nowrap;
    }
    .conf-row .says { font-size: .83rem; color: var(--text-secondary); line-height: 1.55; }
    .conf-row .says .todo { display: block; margin-top: .2rem; color: #33507d; font-weight: 600; }
    @media (max-width: 900px) {
        .conf-row { grid-template-columns: 22px 1fr; }
        .conf-row .fig { text-align: left; }
    }

    .db-guide { border: 1px solid #cfe0f7; border-radius: 10px; background: #f5f9ff; margin-bottom: 1rem; }
    .db-guide-toggle {
        display: flex; align-items: center; width: 100%; background: transparent; border: 0;
        padding: .85rem 1.1rem; text-align: left; color: #14396e; cursor: pointer; font-weight: 700;
    }
    .db-guide-body { padding: 0 1.15rem 1.15rem; }
    .db-guide-body p, .db-guide-body li { color: #33507d; font-size: .9rem; line-height: 1.6; }
    .db-guide-body h6 {
        font-size: .72rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: #7189b0; margin: 1rem 0 .35rem;
    }

    .viz-card { background: #fff; border: 1px solid #e3e7ee; border-radius: 10px; margin-bottom: 1.1rem; }
    .viz-head { padding: 1rem 1.25rem .5rem; }
    .viz-head h5 { margin: 0 0 .2rem; font-size: 1.02rem; font-weight: 700; color: #182b49; }
    .viz-head .sub { font-size: .84rem; color: var(--text-secondary); margin: 0; line-height: 1.5; }
    .viz-body { padding: .5rem 1.25rem 1rem; }
    .viz-canvas { position: relative; height: 300px; }

    /* How to read it, and what to do about it — the two things a number on a
       screen never says for itself. */
    .viz-read {
        border-top: 1px solid #eef1f5; margin-top: .75rem; padding: .8rem 1.25rem 1rem;
        display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;
    }
    .viz-read h6 {
        font-size: .68rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: var(--text-muted); margin: 0 0 .3rem;
    }
    .viz-read p { font-size: .85rem; color: #33507d; margin: 0; line-height: 1.55; }

    /* Identity is never colour alone: a swatch always travels with its word. */
    .key { display: flex; gap: 1rem; flex-wrap: wrap; padding: 0 1.25rem .4rem; }
    .key span { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: var(--text-secondary); }
    .key i { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }

    .rank-table { width: 100%; font-size: .87rem; }
    .rank-table td { padding: .35rem .5rem; border-top: 1px solid #eef1f5; }
    .rank-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rank-bar { height: 8px; border-radius: 4px; background: var(--series-out); }
    .rank-bar.in { background: var(--series-in); }
    .rank-track { background: #f2f4f7; border-radius: 4px; }
    .rank-share { font-size: .78rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
    /* Only the collection tables, which are the only rank tables with a
       header row. Styling .rank-table thead th would also have restyled the
       movers table above, which was not asked for. */
    .fc-rank thead th {
        font-size: .68rem; text-transform: uppercase; letter-spacing: .7px;
        color: var(--text-muted); font-weight: 800; padding: .35rem .5rem;
        border-top: 0; white-space: nowrap;
    }

    /* ---- Who has paid ---------------------------------------------------
       Three counts, a proportion bar, and the money behind them. Ordered
       severity, so it wears the status roles at bar weight rather than a
       fourth categorical hue — see the token block at the top. */
    .fc-split {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: .85rem; margin-bottom: 1rem;
    }
    .fc-stat {
        border: 1px solid #e3e7ee; border-radius: 10px; padding: .85rem 1rem;
        border-left-width: 3px;
    }
    .fc-stat .label {
        font-size: .7rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: var(--text-muted); margin-bottom: .2rem;
    }
    .fc-stat .value {
        font-size: 1.9rem; font-weight: 800; line-height: 1.1;
        font-variant-numeric: tabular-nums; letter-spacing: -.02em;
    }
    .fc-stat .of { font-size: .78rem; color: var(--text-secondary); margin-top: .2rem; }
    .fc-full { border-left-color: var(--paid-full); }
    .fc-part { border-left-color: var(--paid-part); }
    .fc-none { border-left-color: var(--paid-none); }
    .fc-full .value { color: var(--paid-full); }
    .fc-part .value { color: var(--paid-part); }
    .fc-none .value { color: var(--paid-none); }

    /* A 2px surface gap between segments, so two fills never touch. */
    .fc-bar { display: flex; height: 14px; border-radius: 7px; overflow: hidden; background: #f2f4f7; }
    .fc-bar span { display: block; height: 100%; box-shadow: 0 0 0 2px var(--surface) inset; }
    .fc-bar span:first-child { box-shadow: none; }
    .fc-bar .s-full { background: var(--paid-full); }
    .fc-bar .s-part { background: var(--paid-part); }
    .fc-bar .s-none { background: var(--paid-none); }

    .fc-tiles { padding: 0 1.25rem; margin-bottom: 1rem; }

    .fc-b-full { background: var(--paid-full) !important; }
    .fc-b-part { background: var(--paid-part) !important; }
    .fc-b-none { background: var(--paid-none) !important; }

    .fc-aside { font-size: .76rem; color: var(--text-muted); line-height: 1.5; }

    .fc-tag {
        display: inline-block; padding: .02rem .35rem; border-radius: 3px;
        font-size: .68rem; font-weight: 700; background: #f4f6f9;
        color: #55637a; border: 1px solid #d7dee7;
    }

    .fc-note {
        display: flex; gap: .6rem; align-items: flex-start;
        margin: 0 1.25rem 1rem; padding: .75rem .9rem;
        border: 1px solid #e3e7ee; border-left: 3px solid var(--text-muted);
        border-radius: 8px; font-size: .85rem; color: #33507d; line-height: 1.55;
    }
    .fc-note i { margin-top: .2rem; color: var(--text-muted); }
    .fc-note-warn { border-left-color: var(--paid-part); background: #fdfaf2; }
    .fc-note-warn i { color: var(--paid-part); }
    .fc-note .fc-aside { display: block; margin-top: .15rem; }

    @media (max-width: 767px) {
        .fc-stat .value { font-size: 1.5rem; }
        .fc-tiles { padding: 0 1rem; }
        .fc-note { margin: 0 1rem 1rem; }
    }

    /* The numbers behind every chart — the table view, and what somebody
       actually reads out in a meeting. */
    .numbers { border-top: 1px solid #eef1f5; }
    .numbers summary {
        padding: .6rem 1.25rem; cursor: pointer; font-size: .8rem; font-weight: 700;
        color: #33507d; list-style: none;
    }
    .numbers summary::-webkit-details-marker { display: none; }
    .numbers summary::before { content: '▸ '; }
    .numbers[open] summary::before { content: '▾ '; }
    .numbers table { width: 100%; font-size: .82rem; margin: 0 0 .6rem; }
    .numbers th, .numbers td { padding: .3rem .5rem; border-top: 1px solid #f1f3f7; }
    .numbers th { font-size: .68rem; text-transform: uppercase; letter-spacing: .7px; color: var(--text-muted); font-weight: 800; }
    .numbers td.n, .numbers th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .numbers tbody tr:first-child td { border-top: 1px solid #e3e7ee; }

    .mv-up { color: var(--series-in); font-weight: 700; }
    .mv-down { color: var(--series-out); font-weight: 700; }

    .pack-note { font-size: .8rem; color: var(--text-muted); margin: .35rem 0 0; }
</style>
@endpush

@section('content')
@php
    $a = $analysis;
    $pacing = $a['pacing'];
    $months = $a['months'];
    $chip = ['good' => 'chip-good', 'warning' => 'chip-warning', 'critical' => 'chip-critical',
             'incomplete' => 'chip-warning', 'none' => 'chip-none'];
    $icon = ['good' => 'fa-check-circle', 'warning' => 'fa-exclamation-triangle', 'critical' => 'fa-times-circle'];

    $worst = 'good';
    foreach ($checks as $c) {
        if ($c['state'] === 'critical') { $worst = 'critical'; break; }
        if ($c['state'] === 'warning') { $worst = 'warning'; }
    }

    $cash = $a['cash_position'];
    $closing = count($cash) ? end($cash)['balance'] : 0;
    $lowest = count($cash) ? min(array_column($cash, 'balance')) : 0;
    $lowestLabel = '';
    foreach ($cash as $p) { if ($p['balance'] === $lowest) { $lowestLabel = $p['label']; break; } }
@endphp

<div class="main-body viz">
    <div class="page-wrapper">

        <div class="row"><div class="col-12">
            @include('admin.daybook._toolbar', ['route' => 'admin.daybook.analysis'])
        </div></div>

        {{-- 0. Can these figures be presented? ------------------------------ --}}
        <div class="conf {{ $worst === 'good' ? '' : 'has-' . $worst }}">
            <button class="conf-head" type="button" data-bs-toggle="collapse" data-bs-target="#dbConfidence">
                <i class="fas {{ $icon[$worst] }}" style="color: var(--{{ $worst === 'good' ? 'good' : ($worst === 'warning' ? 'warning' : 'critical') }});"></i>
                <span>
                    @if($allClear)
                        {{ __('These figures are ready to present.') }}
                    @else
                        {{ trans_choice('{1}:count thing to settle before presenting these figures|[2,*]:count things to settle before presenting these figures', $concerns, ['count' => $concerns]) }}
                    @endif
                </span>
                <span class="count status-chip {{ $allClear ? 'chip-good' : ($worst === 'critical' ? 'chip-critical' : 'chip-warning') }}">
                    {{ $allClear ? __('All clear') : __('Review') }}
                </span>
            </button>

            {{-- A clean book is not punished with a wall of ticks: it collapses
                 to the single line above and opens on request. --}}
            <div class="collapse {{ $allClear ? '' : 'show' }}" id="dbConfidence">
                <div class="conf-list">
                    @foreach($checks as $c)
                        <div class="conf-row">
                            {{-- Icon and word, never colour alone. --}}
                            <div class="ico {{ $c['state'] }}"><i class="fas {{ $icon[$c['state']] }}"></i></div>
                            <div class="name">{{ $c['label'] }}</div>
                            <div class="fig">{{ $c['figure'] }}</div>
                            <div class="says">
                                {{ $c['detail'] }}
                                @if(!empty($c['action']))
                                    <span class="todo">{{ $c['action'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="db-guide">
            <button class="db-guide-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dbAnalysisGuide">
                <span class="flex-grow-1"><i class="fas fa-lightbulb me-2"></i>{{ __('What this page is for') }}</span>
                <i class="fas fa-chevron-up"></i>
            </button>
            <div class="collapse" id="dbAnalysisGuide">
                <div class="db-guide-body">
                    <p class="mb-0">
                        {{ __('The book tells you what happened. This tells you what it means. Everything here is the same money as the Daybook and the Income & Expenditure sheet — read a different way, so a pattern shows up while there is still time to act on it.') }}
                    </p>

                    <h6>{{ __('The questions it answers, in the order a meeting asks them') }}</h6>
                    <ul class="mb-0">
                        <li><strong>{{ __('Where do we stand?') }}</strong> {{ __('The cash position curve: what the balance was at the end of every month, and whether it ever went under.') }}</li>
                        <li><strong>{{ __('How did we get here?') }}</strong> {{ __('Money in against money out, month by month, and which months paid for themselves.') }}</li>
                        <li><strong>{{ __('What is driving it?') }}</strong> {{ __('Where income comes from, where spending goes, and how much of each rests on a handful of lines.') }}</li>
                        <li><strong>{{ __('What is coming?') }}</strong> {{ __('Pacing — an overspend is obvious in December and cheap to fix in March.') }}</li>
                        <li><strong>{{ __('What can we not trust?') }}</strong> {{ __('The panel at the top, before any of the rest.') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        @if(!$a['has_data'])
            <div class="alert alert-info">
                {{ __('Nothing has been recorded against this budget yet, so there is no pattern to show. The charts appear as soon as money moves.') }}
            </div>
        @else

        <div class="tiles">
            <div class="tile in">
                <div class="label">{{ __('Money in, year to date') }}</div>
                <div class="value">{{ number_format($a['totals']['in']) }}</div>
                <div class="note">{{ __('Fees, subventions and other income actually received.') }}</div>
            </div>
            <div class="tile out">
                <div class="label">{{ __('Money out, year to date') }}</div>
                <div class="value">{{ number_format($a['totals']['out']) }}</div>
                <div class="note">{{ __('Expenditure and capital, including salaries.') }}</div>
            </div>
            <div class="tile {{ $closing < 0 ? 'deficit' : '' }}">
                <div class="label">{{ __('Closing cash position') }}</div>
                <div class="value">{{ number_format($closing) }}</div>
                <div class="note">
                    {{ __('Opening balance plus everything that has moved since.') }}
                    @if($lowest < 0)
                        <span class="status-chip chip-critical">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ __('Lowest :amount in :month', ['amount' => number_format($lowest), 'month' => $lowestLabel]) }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="tile">
                <div class="label">{{ __('Spending pace') }}</div>
                @if($pacing['status'] === 'none')
                    <div class="value" style="font-size:1.15rem;">{{ __('No budget set') }}</div>
                    <div class="note">
                        {{-- Honest rather than a fabricated percentage: with no
                             allocations there is nothing to pace against. --}}
                        {{ __('Pacing compares spending against the budget. Set figures on the Income & Expenditure sheet and this becomes an early warning.') }}
                    </div>
                @elseif($pacing['status'] === 'incomplete')
                    {{-- Spending several times a budget is far more often an
                         unfinished budget than a runaway one. --}}
                    <div class="value" style="font-size:1.15rem;">{{ __('Budget looks unfinished') }}</div>
                    <div class="note">
                        {{ __('Spending is :consumed% of the budget entered. That is usually a budget still being filled in rather than a real overspend.', ['consumed' => number_format($pacing['consumed'], 0)]) }}
                        <span class="status-chip chip-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ __('Budgeted:') }} {{ number_format($pacing['budgeted']) }} &nbsp;·&nbsp; {{ __('Spent:') }} {{ number_format($pacing['spent']) }}
                        </span>
                    </div>
                @else
                    <div class="value">{{ number_format($pacing['consumed'], 0) }}%</div>
                    <div class="note">
                        {{ __('of the budget spent, with') }} <strong>{{ number_format($pacing['elapsed'], 0) }}%</strong> {{ __('of the year gone.') }}
                        <span class="status-chip {{ $chip[$pacing['status']] }}">
                            <i class="fas {{ $pacing['status'] === 'good' ? 'fa-check' : 'fa-exclamation-triangle' }}"></i>
                            {{ $pacing['gap'] > 0
                                ? number_format($pacing['gap'], 0) . '% ' . __('ahead of the year')
                                : number_format(abs($pacing['gap']), 0) . '% ' . __('behind the year') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- 1. Where do we stand — the cash curve ---------------------------- --}}
        <div class="viz-card">
            <div class="viz-head">
                <h5>{{ __('Cash position, month by month') }}</h5>
                <p class="sub">{{ __('What the balance stood at on the last day of each month, starting from the opening balance of :amount.', ['amount' => number_format($a['opening_balance'])]) }}</p>
            </div>
            <div class="viz-body">
                <div class="viz-canvas"><canvas id="chartCash"></canvas></div>
            </div>
            <div class="viz-read">
                <div>
                    <h6>{{ __('How to read it') }}</h6>
                    <p>{{ __('One line: the balance. Anything below the zero line is a month the school ended owing more than it held. The steepness matters as much as the height — a sharp climb is a collection month, a long shallow decline is running costs eating a surplus.') }}</p>
                </div>
                <div>
                    <h6>{{ __('What to do with it') }}</h6>
                    <p>{{ __('If the curve dips below zero, that is the period to plan around next year: either bring collection forward or push discretionary spending back. A dip that repeats in the same months every year is a structural cash gap, not bad luck.') }}</p>
                </div>
            </div>
            <details class="numbers">
                <summary>{{ __('Show the numbers') }}</summary>
                <div class="viz-body" style="padding-top:0">
                    <table>
                        <thead><tr>
                            <th>{{ __('Month') }}</th><th class="n">{{ __('Net') }}</th><th class="n">{{ __('Balance at month end') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($cash as $p)
                            <tr>
                                <td>{{ $p['label'] }}</td>
                                <td class="n {{ $p['net'] >= 0 ? 'mv-up' : 'mv-down' }}">{{ number_format($p['net']) }}</td>
                                <td class="n">{{ number_format($p['balance']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>

        {{-- 2. How did we get here — in and out ------------------------------ --}}
        <div class="viz-card">
            <div class="viz-head">
                <h5>{{ __('Money in and money out, month by month') }}</h5>
                <p class="sub">{{ __('Each month of :budget, side by side.', ['budget' => $budget->title]) }}</p>
            </div>
            <div class="viz-body">
                <div class="viz-canvas"><canvas id="chartMonthly"></canvas></div>
            </div>
            <div class="viz-read">
                <div>
                    <h6>{{ __('How to read it') }}</h6>
                    <p>{{ __('A blue bar taller than its orange neighbour means that month paid for itself. Bunched blue bars are your collection season — in most schools two or three months carry the year, and the rest live off what they brought in.') }}</p>
                </div>
                <div>
                    <h6>{{ __('What to do with it') }}</h6>
                    <p>{{ __('Plan large purchases into the months that follow a collection peak, and treat a run of orange-over-blue months as a cash warning rather than a surprise. If a peak you expected did not arrive, the fees behind it are probably still uncollected.') }}</p>
                </div>
            </div>
            <details class="numbers">
                <summary>{{ __('Show the numbers') }}</summary>
                <div class="viz-body" style="padding-top:0">
                    <table>
                        <thead><tr>
                            <th>{{ __('Month') }}</th><th class="n">{{ __('In') }}</th><th class="n">{{ __('Out') }}</th><th class="n">{{ __('Net') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($months as $m)
                            <tr>
                                <td>{{ $m['label'] }}</td>
                                <td class="n">{{ number_format($m['in']) }}</td>
                                <td class="n">{{ number_format($m['out']) }}</td>
                                <td class="n {{ $m['net'] >= 0 ? 'mv-up' : 'mv-down' }}">{{ number_format($m['net']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>

        {{-- 3. Which months paid for themselves ------------------------------ --}}
        <div class="viz-card">
            <div class="viz-head">
                <h5>{{ __('Surplus and deficit by month') }}</h5>
                <p class="sub">{{ __('The same months, reduced to one number each: what was left over, or what had to be found.') }}</p>
            </div>
            <div class="key">
                <span><i style="background: var(--series-in)"></i>{{ __('Surplus') }}</span>
                <span><i style="background: var(--series-out)"></i>{{ __('Deficit') }}</span>
            </div>
            <div class="viz-body">
                <div class="viz-canvas"><canvas id="chartNet"></canvas></div>
            </div>
            <div class="viz-read">
                <div>
                    <h6>{{ __('How to read it') }}</h6>
                    <p>{{ __('Bars above the line are months that funded themselves; bars below are months that spent reserves. The run of bars matters more than any one of them — two deficits in a row is a pattern, one is a timing difference.') }}</p>
                </div>
                <div>
                    <h6>{{ __('What to do with it') }}</h6>
                    <p>{{ __('Add the deficit months together: that is the working capital the school needs to carry between collections. If the total is larger than what it holds, the gap is funded by delaying payments, and that is worth naming in the meeting.') }}</p>
                </div>
            </div>
        </div>

        {{-- 4. Pacing -------------------------------------------------------- --}}
        <div class="viz-card">
            <div class="viz-head">
                <h5>{{ __('Spending against the year') }}</h5>
                <p class="sub">{{ __('Cumulative expenditure as the year passes.') }}</p>
            </div>
            <div class="viz-body">
                <div class="viz-canvas"><canvas id="chartPacing"></canvas></div>
            </div>
            <div class="viz-read">
                <div>
                    <h6>{{ __('How to read it') }}</h6>
                    <p>
                        {{ __('The solid line is what you have actually spent, accumulating month by month.') }}
                        @if($pacing['status'] !== 'none')
                            {{ __('The dashed line is the budget spent evenly across the year. Above it means spending faster than the year is passing.') }}
                        @else
                            {{ __('Once a budget is set, a dashed guide line appears showing an even spend across the year, so you can see at a glance whether you are ahead of it.') }}
                        @endif
                    </p>
                </div>
                <div>
                    <h6>{{ __('What to do with it') }}</h6>
                    <p>
                        @if($pacing['status'] === 'none')
                            {{ __('Set budget figures on the Income & Expenditure sheet. Without them this line has nothing to be measured against, and an overspend can only be discovered after it has happened.') }}
                        @elseif($pacing['status'] === 'incomplete')
                            {{ __('Before treating this as an overspend, check the budget itself. Spending several times the figure entered almost always means the budget was never finished — commonly salaries, the largest line of all, were left out.') }}
                            {{ __('Of the :spending lines that spent money this year, :budgeted carry a budget figure.', ['spending' => $pacing['lines_spending'], 'budgeted' => $pacing['lines_budgeted']]) }}
                        @else
                            {{ __('A curve that climbs steeply and early usually means annual costs paid up front rather than genuine overspending — check before reacting. A steady climb above the guide line is the one to act on, and the earlier the cheaper.') }}
                            @if($pacing['projected'] > 0 && $pacing['budgeted'] > 0)
                                <br><strong>{{ __('At the current rate the year would finish at about :amount.', ['amount' => number_format($pacing['projected'])]) }}</strong>
                                {{ __('Budget:') }} {{ number_format($pacing['budgeted']) }}.
                            @endif
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- 5. Where income comes from --------------------------------------- --}}
        @if(count($a['top_income']))
            @php $largestIn = $a['top_income'][0]['amount'] ?: 1; @endphp
            <div class="viz-card">
                <div class="viz-head">
                    <h5>{{ __('Where the money comes from') }}</h5>
                    <p class="sub">
                        {{ __('The largest income lines. The top :n together account for', ['n' => $a['concentration']['income']['count']]) }}
                        <strong>{{ number_format($a['concentration']['income']['share'], 0) }}%</strong>
                        {{ __('of everything received.') }}
                    </p>
                </div>
                <div class="viz-body">
                    <table class="rank-table mb-0">
                        @foreach($a['top_income'] as $row)
                            <tr>
                                <td style="width:52px" class="text-muted">{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td style="width:32%">
                                    <div class="rank-track">
                                        <div class="rank-bar in" style="width: {{ max(2, ($row['amount'] / $largestIn) * 100) }}%"></div>
                                    </div>
                                </td>
                                <td style="width:56px" class="rank-share">{{ number_format($row['share'], 0) }}%</td>
                                <td style="width:130px" class="rank-num">{{ number_format($row['amount']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div class="viz-read">
                    <div>
                        <h6>{{ __('How to read it') }}</h6>
                        <p>{{ __('A school whose income rests on two or three lines is exposed: one programme under-recruiting moves the whole year. The percentage beside each bar is its share of all money received, so the concentration is a figure rather than an impression.') }}</p>
                    </div>
                    <div>
                        <h6>{{ __('What to do with it') }}</h6>
                        <p>{{ __('Compare this against enrolment. If one line carries most of the income, that programme deserves the most attention in recruitment and in fee collection — and the first question when a shortfall appears.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- 6. Where it goes ------------------------------------------------- --}}
        @if(count($a['top_expenditure']))
            @php $largest = $a['top_expenditure'][0]['amount'] ?: 1; @endphp
            <div class="viz-card">
                <div class="viz-head">
                    <h5>{{ __('Where the money goes') }}</h5>
                    <p class="sub">
                        {{ __('The largest expenditure lines. The top :n together account for', ['n' => $a['concentration']['expenditure']['count']]) }}
                        <strong>{{ number_format($a['concentration']['expenditure']['share'], 0) }}%</strong>
                        {{ __('of everything spent.') }}
                    </p>
                </div>
                <div class="viz-body">
                    <table class="rank-table mb-0">
                        @foreach($a['top_expenditure'] as $row)
                            <tr>
                                <td style="width:52px" class="text-muted">{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td style="width:32%">
                                    <div class="rank-track">
                                        <div class="rank-bar" style="width: {{ max(2, ($row['amount'] / $largest) * 100) }}%"></div>
                                    </div>
                                </td>
                                <td style="width:56px" class="rank-share">{{ number_format($row['share'], 0) }}%</td>
                                <td style="width:130px" class="rank-num">{{ number_format($row['amount']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div class="viz-read">
                    <div>
                        <h6>{{ __('How to read it') }}</h6>
                        <p>{{ __('Bars are relative to the largest line, so the shape shows concentration. A single long bar with a tail of short ones means one line effectively is your budget — usually salaries.') }}</p>
                    </div>
                    <div>
                        <h6>{{ __('What to do with it') }}</h6>
                        <p>{{ __('Savings only come from the top of this list; trimming a line near the bottom changes nothing. If a line surprises you, open the Daybook for the month and read the entries behind it.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- 7. What changed since last month --------------------------------- --}}
        @if(!empty($a['movers']['rows']))
            <div class="viz-card">
                <div class="viz-head">
                    <h5>{{ __('What changed, :from to :to', ['from' => $a['movers']['from'], 'to' => $a['movers']['to']]) }}</h5>
                    <p class="sub">{{ __('The lines that moved most between the last two months with activity — measured against the previous month that actually had movement, so a quiet holiday month does not make everything look like a spike.') }}</p>
                </div>
                <div class="viz-body">
                    <table class="numbers-table rank-table mb-0">
                        <thead><tr>
                            <th>{{ __('Line') }}</th>
                            <th class="rank-num">{{ $a['movers']['from'] }}</th>
                            <th class="rank-num">{{ $a['movers']['to'] }}</th>
                            <th class="rank-num">{{ __('Change') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($a['movers']['rows'] as $row)
                            <tr>
                                <td>{{ $row['line'] }}</td>
                                <td class="rank-num">{{ number_format($row['previous']) }}</td>
                                <td class="rank-num">{{ number_format($row['current']) }}</td>
                                <td class="rank-num {{ $row['direction'] === 'up' ? 'mv-up' : 'mv-down' }}">
                                    <i class="fas {{ $row['direction'] === 'up' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                                    {{ number_format(abs($row['change'])) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="viz-read">
                    <div>
                        <h6>{{ __('How to read it') }}</h6>
                        <p>{{ __('An arrow is movement, not judgement: income rising is good and spending rising is not, and the same arrow is used for both. Read the line name first.') }}</p>
                    </div>
                    <div>
                        <h6>{{ __('What to do with it') }}</h6>
                        <p>{{ __('This is the list to walk through before a meeting. Every one of these is a question somebody could ask, and it is cheaper to know the answer now.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- 8. The biggest single movements ---------------------------------- --}}
        @if(count($a['largest_movements']))
            <div class="viz-card">
                <div class="viz-head">
                    <h5>{{ __('The largest single transactions') }}</h5>
                    <p class="sub">{{ __('Because the question in the meeting is almost always about one of these.') }}</p>
                </div>
                <div class="viz-body">
                    <table class="rank-table mb-0">
                        <thead><tr>
                            <th style="width:92px">{{ __('Date') }}</th>
                            <th style="width:92px">{{ __('Ref') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Line') }}</th>
                            <th class="rank-num" style="width:130px">{{ __('Amount') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($a['largest_movements'] as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td class="text-muted">{{ $row['ref'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td class="{{ $row['line'] ? '' : 'text-danger' }}">
                                    {{ $row['line'] ?: __('— no budget line —') }}
                                </td>
                                <td class="rank-num {{ $row['direction'] === 'in' ? 'mv-up' : 'mv-down' }}">
                                    {{ number_format($row['amount']) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- 9. Money the sheet cannot place ---------------------------------- --}}
        @if($a['unallocated']['count'] > 0)
            <div class="viz-card" style="border-left: 4px solid var(--critical);">
                <div class="viz-head">
                    <h5>{{ __('Money on no budget line') }}</h5>
                    <p class="sub">
                        <strong>{{ number_format($a['unallocated']['amount']) }}</strong>
                        {{ __('across :n transaction(s) — :pct% of everything that moved. It is in the book and in the bank, and on no line of the Income & Expenditure sheet.', [
                            'n' => $a['unallocated']['count'],
                            'pct' => number_format($a['unallocated']['share'], 1),
                        ]) }}
                    </p>
                </div>
                <div class="viz-body">
                    <table class="rank-table mb-0">
                        <thead><tr>
                            <th style="width:92px">{{ __('Date') }}</th>
                            <th style="width:92px">{{ __('Ref') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="rank-num" style="width:130px">{{ __('Amount') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($a['unallocated']['rows'] as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td class="text-muted">{{ $row['ref'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td class="rank-num">{{ number_format($row['amount']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="viz-read">
                    <div>
                        <h6>{{ __('Why it matters') }}</h6>
                        <p>{{ __('Every total on this page and on the Income & Expenditure sheet excludes these. The daybook counts them, which is why the book and the sheet still agree — but a reader comparing the sheet against the bank will be short by exactly this amount.') }}</p>
                    </div>
                    <div>
                        <h6>{{ __('What to do with it') }}</h6>
                        <p>{{ __('Give the category behind each of these a budget line. Nothing needs re-entering — the money returns to the sheet the moment the mapping exists.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @endif

        {{-- Who has paid -----------------------------------------------------
             Outside the has_data branch on purpose. Everything above needs
             cash to have moved; this does not. A budget year whose fees are
             assigned and wholly unpaid has no rows for the charts and is
             exactly the year somebody most needs to be told about. --}}
        @include('admin.daybook.partials.collection')

        {{-- The pack ---------------------------------------------------------
             A POST, because the charts travel with it as images this page
             captures itself. Last, because it is the action you take after
             reading rather than part of the reading. --}}
        <form method="post" action="{{ route('admin.daybook.analysis.pdf') }}" id="packForm" class="mb-4">
            @csrf
            <input type="hidden" name="budget_id" value="{{ $budget->id }}">
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <div id="packCharts"></div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-file-pdf me-1"></i>{{ __('Download the meeting pack') }}
            </button>
            <p class="pack-note">
                {{ __('Every chart on this page, with the numbers behind it, on the school letterhead.') }}
            </p>
        </form>
    </div>
</div>
@endsection

@push('scripts')
{{-- Local copy, not a CDN: these institutions run on intermittent connections
     and a chart that needs the internet is a chart that is sometimes blank. --}}
<script src="{{ asset('dashboard/plugins/chart-chartjs/js/chart.min.js') }}"></script>
<script>
(function () {
    'use strict';
    if (typeof Chart === 'undefined') { return; }

    var css = getComputedStyle(document.querySelector('.viz'));
    var role = function (name) { return css.getPropertyValue(name).trim(); };

    var IN = role('--series-in') || '#2a78d6';
    var OUT = role('--series-out') || '#eb6834';
    var GRID = role('--gridline') || '#e1e0d9';
    var AXIS = role('--axis') || '#c3c2b7';
    var INK = role('--text-secondary') || '#52514e';

    var labels = @json(array_column($months, 'label'));
    var moneyIn = @json(array_map(fn ($m) => round($m['in'], 2), $months));
    var moneyOut = @json(array_map(fn ($m) => round($m['out'], 2), $months));
    var cumOut = @json(array_map(fn ($m) => round($m['cum_out'], 2), $months));
    var netByMonth = @json(array_map(fn ($m) => round($m['net'], 2), $months));
    var balance = @json(array_map(fn ($p) => round($p['balance'], 2), $cash));
    var budgetOut = {{ (float) ($analysis['budget']['expenditure'] ?? 0) }};

    var fmt = new Intl.NumberFormat();

    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = INK;

    var axes = {
        x: { grid: { display: false }, border: { color: AXIS }, ticks: { color: INK } },
        y: {
            beginAtZero: true,
            grid: { color: GRID, drawTicks: false },
            border: { display: false },
            ticks: {
                color: INK,
                // Money on an axis is unreadable in full; thousands are enough
                // to judge a shape by.
                callback: function (v) { return v >= 1000 || v <= -1000 ? fmt.format(v / 1000) + 'k' : fmt.format(v); }
            }
        }
    };

    var tooltip = {
        callbacks: {
            label: function (ctx) {
                var name = ctx.dataset.label ? ctx.dataset.label + ': ' : '';
                return name + fmt.format(Math.round(ctx.parsed.y));
            }
        }
    };

    // Every chart is registered so the meeting pack can capture it.
    var captured = [];
    var keep = function (chart) { captured.push(chart); return chart; };

    /* 1. Cash position — one series, so no legend box; the title names it.
          The zero line is drawn heavier than the other gridlines because
          crossing it is the whole point of the chart. */
    keep(new Chart(document.getElementById('chartCash'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '{{ __('Balance') }}',
                data: balance,
                borderColor: IN,
                backgroundColor: 'rgba(42,120,214,0.08)',
                borderWidth: 2, fill: true, tension: 0.25,
                pointRadius: 4, pointHoverRadius: 6,
                pointBackgroundColor: IN, pointBorderColor: '#fff', pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: axes.x,
                y: {
                    grid: {
                        color: function (ctx) { return ctx.tick.value === 0 ? AXIS : GRID; },
                        lineWidth: function (ctx) { return ctx.tick.value === 0 ? 2 : 1; },
                        drawTicks: false
                    },
                    border: { display: false },
                    ticks: axes.y.ticks
                }
            },
            plugins: { legend: { display: false }, tooltip: tooltip }
        }
    }));

    /* 2. In and out, side by side. */
    keep(new Chart(document.getElementById('chartMonthly'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                // A 2px surface gap between adjacent bars, so neighbouring
                // fills never touch.
                { label: '{{ __('Money in') }}', data: moneyIn, backgroundColor: IN,
                  borderRadius: 4, borderSkipped: 'bottom', borderColor: '#fff', borderWidth: { top: 0, right: 1, bottom: 0, left: 1 } },
                { label: '{{ __('Money out') }}', data: moneyOut, backgroundColor: OUT,
                  borderRadius: 4, borderSkipped: 'bottom', borderColor: '#fff', borderWidth: { top: 0, right: 1, bottom: 0, left: 1 } }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: axes,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rectRounded' } },
                tooltip: tooltip
            }
        }
    }));

    /* 3. Surplus and deficit — diverging about a neutral zero.
          Colour follows polarity, which is what a diverging scale is for, and
          the swatch key above the chart carries the words so identity is
          never colour alone. */
    keep(new Chart(document.getElementById('chartNet'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '{{ __('Net') }}',
                data: netByMonth,
                backgroundColor: netByMonth.map(function (v) { return v >= 0 ? IN : OUT; }),
                borderRadius: 4,
                borderColor: '#fff',
                borderWidth: { top: 0, right: 1, bottom: 0, left: 1 }
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, border: { color: AXIS }, ticks: { color: INK } },
                y: {
                    grid: {
                        color: function (ctx) { return ctx.tick.value === 0 ? AXIS : GRID; },
                        lineWidth: function (ctx) { return ctx.tick.value === 0 ? 2 : 1; },
                        drawTicks: false
                    },
                    border: { display: false },
                    ticks: axes.y.ticks
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var v = Math.round(ctx.parsed.y);
                            return (v >= 0 ? '{{ __('Surplus') }}: ' : '{{ __('Deficit') }}: ') + fmt.format(Math.abs(v));
                        }
                    }
                }
            }
        }
    }));

    /* 4. Pacing. */
    var pacingSets = [{
        label: '{{ __('Spent, cumulative') }}',
        data: cumOut,
        borderColor: OUT, backgroundColor: 'rgba(235,104,52,0.08)',
        borderWidth: 2, fill: true, tension: 0.25,
        pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: OUT,
        pointBorderColor: '#fff', pointBorderWidth: 2
    }];

    // An even spend across the year — the line to be above or below. Drawn only
    // when a budget exists; an invented guide would be worse than none.
    if (budgetOut > 0) {
        var even = labels.map(function (_, i) { return budgetOut * ((i + 1) / labels.length); });
        pacingSets.push({
            label: '{{ __('Budget, spread evenly') }}',
            data: even,
            borderColor: AXIS, borderDash: [6, 4], borderWidth: 2,
            pointRadius: 0, fill: false, tension: 0
        });
    }

    keep(new Chart(document.getElementById('chartPacing'), {
        type: 'line',
        data: { labels: labels, datasets: pacingSets },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: axes,
            plugins: {
                legend: {
                    // One series needs no legend box; the title names it.
                    display: pacingSets.length > 1,
                    position: 'top', align: 'end',
                    labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'line' }
                },
                tooltip: tooltip
            }
        }
    }));

    /* 5. Fee collection, month by month.

       Registered AFTER the four above, and that ordering is load-bearing:
       analysis-pdf.blade.php reads $charts[0..3] by POSITION for cash, in/out,
       net and pacing. Registering this one earlier would silently print the
       wrong picture under four headings in the meeting pack. */
    var feeCanvas = document.getElementById('chartFeeCollection');

    if (feeCanvas) {
        var feeLabels = @json(array_column($collection['by_month'], 'label'));
        var feeCash = @json(array_column($collection['by_month'], 'cash'));
        var feeCumulative = @json(array_column($collection['by_month'], 'cumulative'));

        keep(new Chart(feeCanvas, {
            data: {
                labels: feeLabels,
                datasets: [
                    {
                        type: 'bar',
                        label: '{{ __('Received in the month') }}',
                        data: feeCash,
                        backgroundColor: IN,
                        borderRadius: 4,
                        // A 2px surface gap so adjacent bars never touch.
                        borderColor: role('--surface') || '#ffffff',
                        borderWidth: { top: 0, right: 1, bottom: 0, left: 1 },
                        order: 2
                    },
                    {
                        type: 'line',
                        label: '{{ __('Running total') }}',
                        data: feeCumulative,
                        borderColor: INK,
                        backgroundColor: INK,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false,
                        tension: .25,
                        // Its own axis would make this a dual-axis chart, which
                        // is the one chart form never to build. Both series are
                        // money in the same currency, so they share the scale;
                        // the running total simply climbs above the bars.
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: axes,
                plugins: {
                    legend: { display: false },
                    tooltip: tooltip
                }
            }
        }));
    }

    /* The meeting pack.
       dompdf cannot run Chart.js, so each canvas is captured here with the
       library's own toBase64Image() and posted with the form. No html2canvas,
       no CDN — and if a capture throws, the pack still goes, because every
       chart on the page is backed by its own table. */
    var form = document.getElementById('packForm');
    var bucket = document.getElementById('packCharts');

    if (form && bucket) {
        form.addEventListener('submit', function () {
            bucket.innerHTML = '';

            captured.forEach(function (chart, i) {
                try {
                    var png = chart.toBase64Image('image/png', 1);
                    if (!png || png.indexOf('data:image/png;base64,') !== 0) { return; }

                    var field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = 'charts[' + i + ']';
                    field.value = png;
                    bucket.appendChild(field);
                } catch (e) {
                    // A missing picture is a far smaller problem than a
                    // missing pack.
                }
            });
        });
    }
})();
</script>
@endpush
