@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<style>
    /* Chart roles, not raw hex, so the palette swaps in one place. These two
       hues are validated as a categorical pair (CVD ΔE 24.7, normal 33.6). */
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

    .status-chip {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .72rem; font-weight: 700; padding: .1rem .45rem; border-radius: 4px;
        border: 1px solid; margin-top: .3rem;
    }
    .chip-good { color: #056b05; border-color: #b5e3b5; background: #eefaee; }
    .chip-warning { color: #7a5312; border-color: #f2dca7; background: #fdf8ea; }
    .chip-critical { color: #8a1f1f; border-color: #eab8b8; background: #fdeeee; }
    .chip-none { color: #55637a; border-color: #d7dee7; background: #f4f6f9; }

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

    .rank-table { width: 100%; font-size: .87rem; }
    .rank-table td { padding: .35rem .5rem; border-top: 1px solid #eef1f5; }
    .rank-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rank-bar { height: 8px; border-radius: 4px; background: var(--series-out); }
    .rank-track { background: #f2f4f7; border-radius: 4px; }
</style>
@endpush

@section('content')
@php
    $a = $analysis;
    $pacing = $a['pacing'];
    $months = $a['months'];
    $chip = ['good' => 'chip-good', 'warning' => 'chip-warning', 'critical' => 'chip-critical',
             'incomplete' => 'chip-warning', 'none' => 'chip-none'];
@endphp

<div class="main-body viz">
    <div class="page-wrapper">

        <div class="row"><div class="col-12">
            @include('admin.daybook._toolbar', ['route' => 'admin.daybook.analysis'])
        </div></div>

        <div class="db-guide">
            <button class="db-guide-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dbAnalysisGuide">
                <span class="flex-grow-1"><i class="fas fa-lightbulb me-2"></i>{{ __('What this page is for') }}</span>
                <i class="fas fa-chevron-up"></i>
            </button>
            <div class="collapse show" id="dbAnalysisGuide">
                <div class="db-guide-body">
                    <p class="mb-0">
                        {{ __('The book tells you what happened. This tells you what it means. Everything here is the same money as the Daybook and the Income & Expenditure sheet — read a different way, so a pattern shows up while there is still time to act on it.') }}
                    </p>

                    <h6>{{ __('The three questions it answers') }}</h6>
                    <ul class="mb-0">
                        <li><strong>{{ __('When does money arrive?') }}</strong> {{ __('School income is seasonal. Knowing which months carry the collection tells you which months you can afford to spend in, and when to expect a lean period.') }}</li>
                        <li><strong>{{ __('Are we spending faster than the year is passing?') }}</strong> {{ __('An overspend is obvious in December and cheap to fix in March. Pacing is what makes it visible early.') }}</li>
                        <li><strong>{{ __('Where does the money actually go?') }}</strong> {{ __('Not where you assume — where the ledger says. Very often a handful of lines carry most of the year.') }}</li>
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
            <div class="tile">
                <div class="label">{{ __('Net position') }}</div>
                <div class="value">{{ number_format($a['totals']['net']) }}</div>
                <div class="note">
                    {{ $a['totals']['net'] >= 0
                        ? __('Income has covered spending so far this year.')
                        : __('Spending has run ahead of income so far this year.') }}
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
                         unfinished budget than a runaway one. Saying "385%
                         overspent" here would be arithmetically true and
                         practically useless. --}}
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

        {{-- 1. Seasonality and monthly solvency ------------------------------ --}}
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
        </div>

        {{-- 2. Pacing -------------------------------------------------------- --}}
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

        {{-- 3. Concentration ------------------------------------------------- --}}
        @if(count($a['top_expenditure']))
            @php
                $largest = $a['top_expenditure'][0]['amount'] ?: 1;
                $topSum = array_sum(array_column($a['top_expenditure'], 'amount'));
                $share = $a['totals']['out'] > 0 ? ($topSum / $a['totals']['out']) * 100 : 0;
            @endphp
            <div class="viz-card">
                <div class="viz-head">
                    <h5>{{ __('Where the money goes') }}</h5>
                    <p class="sub">
                        {{ __('The ten largest expenditure lines, which together account for') }}
                        <strong>{{ number_format($share, 0) }}%</strong>
                        {{ __('of everything spent this year.') }}
                    </p>
                </div>
                <div class="viz-body">
                    <table class="rank-table mb-0">
                        @foreach($a['top_expenditure'] as $row)
                            <tr>
                                <td style="width:52px" class="text-muted">{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td style="width:38%">
                                    {{-- A bar per row rather than a chart: this is a
                                         ranked comparison, and the label has to stay
                                         readable next to it. --}}
                                    <div class="rank-track">
                                        <div class="rank-bar" style="width: {{ max(2, ($row['amount'] / $largest) * 100) }}%"></div>
                                    </div>
                                </td>
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

        @endif
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
                callback: function (v) { return v >= 1000 ? fmt.format(v / 1000) + 'k' : fmt.format(v); }
            }
        }
    };

    var tooltip = {
        callbacks: {
            label: function (ctx) { return ctx.dataset.label + ': ' + fmt.format(Math.round(ctx.parsed.y)); }
        }
    };

    new Chart(document.getElementById('chartMonthly'), {
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
    });

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

    new Chart(document.getElementById('chartPacing'), {
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
    });
})();
</script>
@endpush
