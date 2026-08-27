@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<style>
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

    .ms-table { width: 100%; font-size: .85rem; }
    .ms-table th {
        background: #f4f6f9; font-size: .68rem; text-transform: uppercase;
        letter-spacing: .7px; color: #55637a; padding: .5rem .55rem; white-space: nowrap;
    }
    .ms-table td { padding: .38rem .55rem; border-top: 1px solid #eef1f5; }
    .ms-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ms-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .78rem; color: #55637a; }
    .ms-section td {
        background: #212b36; color: #fff; font-weight: 700; text-transform: uppercase;
        letter-spacing: .5px; font-size: .74rem;
    }
    .ms-total td { border-top: 2px solid #34495e; font-weight: 800; background: #f7f9fb; }

    /* Drawn as the Income & Expenditure sheet draws them, so the two read the
       same way: headings at the margin, their lines indented beneath, and a
       lighter rule closing each group. */
    .ms-header td { background: #eef1f6; font-weight: 700; }
    .ms-header .ms-name { text-transform: uppercase; letter-spacing: .3px; }
    .ms-child .ms-name { padding-left: 1.4rem; }
    .ms-subtotal td {
        background: #f7f9fb; font-weight: 600; font-style: italic;
        border-top: 1px solid #c8d3e0; border-bottom: 1px solid #c8d3e0;
    }
    .ms-subtotal .ms-name { padding-left: 1.4rem; }
    .ms-good { color: #0f7a45; }
    .ms-bad { color: #a02020; }
    .ms-quiet { color: #a9b3c1; }

    .db-guide { border: 1px solid #cfe0f7; border-radius: 10px; background: #f5f9ff; margin-bottom: 1rem; }
    .db-guide-toggle {
        display: flex; align-items: center; width: 100%; background: transparent; border: 0;
        padding: .85rem 1.1rem; text-align: left; color: #14396e; cursor: pointer; font-weight: 700;
    }
    .db-guide-body { padding: 0 1.15rem 1.15rem; }
    .db-guide-body p, .db-guide-body li { color: #33507d; font-size: .89rem; line-height: 1.6; }
    .db-guide-body h6 {
        font-size: .7rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: #7189b0; margin: 1rem 0 .3rem;
    }
    .col-key { width: 100%; font-size: .86rem; margin-bottom: 0; }
    .col-key td { padding: .3rem .5rem; border-top: 1px solid #dbe7f8; color: #33507d; }
    .col-key td:first-child { font-weight: 700; white-space: nowrap; width: 130px; }

    .pc-card {
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px;
        padding: 1.1rem 1.25rem; margin-bottom: .75rem;
    }
    .pc-name { font-weight: 700; color: #182b49; }
    .pc-figures { display: flex; gap: 2rem; margin-top: .5rem; flex-wrap: wrap; }
    .pc-figure .label {
        font-size: .68rem; font-weight: 800; letter-spacing: .8px;
        text-transform: uppercase; color: #8a97ab;
    }
    .pc-figure .value { font-size: 1.05rem; font-weight: 700; font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')
@php
    $totals = $summary['totals'];
    $money = fn ($v) => $v == 0 ? '—' : number_format($v);
@endphp

<div class="main-body">
    <div class="page-wrapper">

        <div class="row">
            <div class="col-12">
                @include('admin.daybook._toolbar', ['route' => 'admin.daybook.summary'])
            </div>
        </div>

        <div class="db-guide">
            <button class="db-guide-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dbMsGuide">
                <span class="flex-grow-1"><i class="fas fa-lightbulb me-2"></i>{{ __('What each column means') }}</span>
                <i class="fas fa-chevron-up"></i>
            </button>
            <div class="collapse show" id="dbMsGuide">
                <div class="db-guide-body">
                    <p>{{ __('This is the month turned into a budget report: what was planned, what happened this month, what had happened before it, and what is left. Read a row left to right and it tells the whole story of one line.') }}</p>

                    <table class="col-key">
                        <tr><td>{{ __('Budget') }}</td><td>{{ __('The figure set for this line for the whole year, from the Income & Expenditure sheet. A dash means no figure was ever entered — that line is unbudgeted, not zero.') }}</td></tr>
                        <tr><td>{{ __('Monthly') }}</td><td>{{ __('What this line took or spent in this month alone.') }}</td></tr>
                        <tr><td>{{ __('BB Forward') }}</td><td>{{ __('Balance brought forward: everything on this line since the budget opened, up to the end of last month. The first month of a budget always opens at zero.') }}</td></tr>
                        <tr><td>{{ __('Cumulative') }}</td><td>{{ __('Monthly plus BB Forward — the year to date. This is the figure that must match the Income & Expenditure sheet at year end.') }}</td></tr>
                        <tr><td>{{ __('Balance') }}</td><td>{{ __('Budget minus Cumulative. On expenditure this is headroom left to spend; on income it is what you still expect to collect.') }}</td></tr>
                    </table>

                    <h6>{{ __('How the rows are grouped') }}</h6>
                    <p>{{ __('The rows follow the Income & Expenditure sheet exactly: a heading such as ADMINISTRATION carries the total of the lines filed beneath it, those lines are indented, and the group is closed with its own subtotal. A heading holds no money of its own — it is the sum of its children — so it is never added to the section total a second time.') }}</p>

                    <h6>{{ __('Reading the colours') }}</h6>
                    <p class="mb-0">{{ __('Green is favourable, red is not — and the two mean opposite things on either side of the sheet. Income beating its budget is good news; expenditure beating its budget is an overspend. A balance shown in red on an expenditure line means that line has already spent more than was planned for the whole year.') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Monthly Summary') }} — {{ $period->name }}</h5>
                <small class="text-muted">
                    {{-- The one thing a reader must understand to trust the columns. --}}
                    {{ __('Brought forward is everything since this budget opened, up to the end of last month. Cumulative is that plus this month. Balance is what remains of the budget.') }}
                </small>
            </div>

            <div class="card-block table-responsive">
                <table class="table ms-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:80px">{{ __('Code') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th style="width:120px" class="ms-num">{{ __('Budget') }}</th>
                            <th style="width:120px" class="ms-num">{{ __('Monthly') }}</th>
                            <th style="width:120px" class="ms-num">{{ __('BB Forward') }}</th>
                            <th style="width:120px" class="ms-num">{{ __('Cumulative') }}</th>
                            <th style="width:120px" class="ms-num">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(['income' => __('INCOME'), 'expenditure' => __('EXPENDITURE'), 'capital' => __('CAPITAL EXPENDITURE')] as $section => $label)
                            <tr class="ms-section"><td colspan="7">{{ $label }}</td></tr>

                            @foreach($summary['lines'] as $row)
                                @continue($row['line']->section !== $section)
                                <tr class="{{ $row['is_header'] ? 'ms-header' : 'ms-child' }}">
                                    <td class="ms-code">{{ $row['line']->code }}</td>
                                    <td class="ms-name">
                                        {{ $row['line']->name }}
                                        @if($row['line']->profit_centre)
                                            <span class="badge bg-light text-secondary border">{{ $row['line']->profit_centre }}</span>
                                        @endif
                                    </td>
                                    <td class="ms-num">{{ $money($row['budget']) }}</td>
                                    <td class="ms-num">{{ $money($row['monthly']) }}</td>
                                    <td class="ms-num ms-quiet">{{ $money($row['brought']) }}</td>
                                    <td class="ms-num"><strong>{{ $money($row['cumulative']) }}</strong></td>
                                    <td class="ms-num">
                                        @if($row['budget'] == 0)
                                            <span class="ms-quiet" title="{{ __('No budget set for this line') }}">—</span>
                                        @else
                                            @php
                                                // Income beating its budget is good news; expenditure
                                                // beating it is an overspend. Sign alone would colour
                                                // every income shortfall green.
                                                $favourable = $section === 'income'
                                                    ? $row['balance'] <= 0
                                                    : $row['balance'] >= 0;
                                            @endphp
                                            <span class="{{ $favourable ? 'ms-good' : 'ms-bad' }}">{{ number_format($row['balance']) }}</span>
                                        @endif
                                    </td>
                                </tr>

                                {{-- The group's own total at its foot, where a reader
                                     following a column downwards actually stops. The
                                     heading carries the same figure at the top. --}}
                                @if(isset($summary['group_ends'][$row['line']->id]))
                                    @php
                                        $head = $summary['group_ends'][$row['line']->id];
                                        $g = $summary['lines'][$head->id] ?? null;
                                    @endphp
                                    @if($g)
                                        <tr class="ms-subtotal">
                                            <td></td>
                                            <td class="ms-name">{{ __('Total') }} {{ $head->name }}</td>
                                            <td class="ms-num">{{ $money($g['budget']) }}</td>
                                            <td class="ms-num">{{ $money($g['monthly']) }}</td>
                                            <td class="ms-num ms-quiet">{{ $money($g['brought']) }}</td>
                                            <td class="ms-num">{{ $money($g['cumulative']) }}</td>
                                            <td class="ms-num">
                                                @if($g['budget'] == 0)
                                                    <span class="ms-quiet">—</span>
                                                @else
                                                    @php
                                                        $gFav = $section === 'income'
                                                            ? $g['balance'] <= 0
                                                            : $g['balance'] >= 0;
                                                    @endphp
                                                    <span class="{{ $gFav ? 'ms-good' : 'ms-bad' }}">{{ number_format($g['balance']) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            @endforeach

                            <tr class="ms-total">
                                <td></td>
                                <td>{{ __('TOTAL') }} {{ $label }}</td>
                                <td class="ms-num">{{ $money($totals[$section]['budget']) }}</td>
                                <td class="ms-num">{{ $money($totals[$section]['monthly']) }}</td>
                                <td class="ms-num">{{ $money($totals[$section]['brought']) }}</td>
                                <td class="ms-num">{{ $money($totals[$section]['cumulative']) }}</td>
                                <td class="ms-num">{{ $money($totals[$section]['budget'] - $totals[$section]['cumulative']) }}</td>
                            </tr>
                        @endforeach

                        <tr class="ms-total">
                            <td></td>
                            <td>{{ __('NET BALANCE') }}</td>
                            <td class="ms-num">{{ $money($totals['income']['budget'] - $totals['expenditure']['budget']) }}</td>
                            <td class="ms-num">{{ $money($totals['income']['monthly'] - $totals['expenditure']['monthly']) }}</td>
                            <td class="ms-num">{{ $money($totals['income']['brought'] - $totals['expenditure']['brought']) }}</td>
                            <td class="ms-num">{{ $money($totals['income']['cumulative'] - $totals['expenditure']['cumulative']) }}</td>
                            <td class="ms-num"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Profit centres re-present lines already counted above; nothing here
             is added to any total. --}}
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Profit centres') }}</h5>
                <small class="text-muted">
                    {{ __('Trading activities shown as what they earned against what they cost. These lines are already counted in the sections above — this only sets them side by side so the margin is visible.') }}
                </small>
            </div>

            <div class="card-block">
                @forelse($summary['profit_centres'] as $name => $centre)
                    <div class="pc-card">
                        <div class="pc-name">{{ $name }}</div>
                        <div class="pc-figures">
                            <div class="pc-figure">
                                <div class="label">{{ __('Earned') }}</div>
                                <div class="value ms-good">{{ number_format($centre['income']) }}</div>
                            </div>
                            <div class="pc-figure">
                                <div class="label">{{ __('Cost') }}</div>
                                <div class="value ms-bad">{{ number_format($centre['expenditure']) }}</div>
                            </div>
                            <div class="pc-figure">
                                <div class="label">{{ __('Margin') }}</div>
                                <div class="value {{ $centre['margin'] >= 0 ? 'ms-good' : 'ms-bad' }}">
                                    {{ number_format($centre['margin']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">
                        {{ __('No trading activities are configured yet. Give a pair of budget lines the same profit centre name — one income, one expenditure — and the margin for that activity appears here.') }}
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
