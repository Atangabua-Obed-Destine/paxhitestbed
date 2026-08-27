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

    .db-guide { border: 1px solid #cfe0f7; border-radius: 10px; background: #f5f9ff; margin-bottom: 1rem; }
    .db-guide-toggle {
        display: flex; align-items: center; width: 100%; background: transparent; border: 0;
        padding: .85rem 1.1rem; text-align: left; color: #14396e; cursor: pointer; font-weight: 700;
    }
    .db-guide-body { padding: 0 1.15rem 1.15rem; }
    .db-guide-body p { color: #33507d; font-size: .89rem; line-height: 1.6; margin-bottom: 0; }
    .db-guide-body h6 {
        font-size: .7rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: #7189b0; margin: 1rem 0 .3rem;
    }

    .db-figures { display: flex; gap: 2.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .db-figure {
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px;
        padding: .85rem 1.25rem; min-width: 170px;
    }
    .db-figure .label {
        font-size: .7rem; font-weight: 800; letter-spacing: .9px;
        text-transform: uppercase; color: #8a97ab;
    }
    .db-figure .value {
        font-size: 1.35rem; font-weight: 800; color: #0a2540;
        font-variant-numeric: tabular-nums;
    }
    .db-figure.in .value { color: #0f7a45; }
    .db-figure.out .value { color: #a02020; }

    .db-table { width: 100%; font-size: .86rem; }
    .db-table th {
        background: #f4f6f9; font-size: .7rem; text-transform: uppercase;
        letter-spacing: .7px; color: #55637a; padding: .5rem .6rem; white-space: nowrap;
    }
    .db-table td { padding: .45rem .6rem; border-top: 1px solid #eef1f5; vertical-align: top; }
    .db-table tbody tr:hover td { background: #fafbfd; }
    .db-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .db-in { color: #0f7a45; font-weight: 600; }
    .db-out { color: #a02020; font-weight: 600; }
    .db-ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .78rem; color: #55637a; }
    .db-line { font-size: .74rem; color: #5b7bab; white-space: nowrap; }
    .db-line-none { color: #b3701a; font-weight: 700; }
    .db-source {
        font-size: .62rem; text-transform: uppercase; letter-spacing: .5px;
        border: 1px solid #dde3ea; border-radius: 3px; padding: 0 .25rem; color: #7189b0;
    }
    .db-foot td { border-top: 2px solid #34495e; font-weight: 800; background: #f7f9fb; }
</style>
@endpush

@section('content')
<div class="main-body">
    <div class="page-wrapper">

        <div class="row">
            <div class="col-12">
                @include('admin.daybook._toolbar', ['route' => 'admin.daybook.index'])
            </div>
        </div>

        <div class="db-guide">
            <button class="db-guide-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dbBookGuide">
                <span class="flex-grow-1"><i class="fas fa-lightbulb me-2"></i>{{ __('What the Daybook is, and how to read it') }}</span>
                <i class="fas fa-chevron-up"></i>
            </button>
            <div class="collapse show" id="dbBookGuide">
                <div class="db-guide-body">
                    <h6>{{ __('What it is') }}</h6>
                    <p>{{ __('A cash analysis book: every movement of money in one month, on one row — the date, a reference you can trace, what it was for, and whether cash came in or went out. The last column is the analysis: the budget line that movement belongs to.') }}</p>

                    <h6>{{ __('Nothing here is typed') }}</h6>
                    <p>{{ __('Every row already exists in the system as a fee, an income, an expense or a payroll run. This is those records laid out the way a cash book lays them out, so there is nothing to keep in step and nothing to enter twice.') }}</p>

                    <h6>{{ __('How it relates to the sheet') }}</h6>
                    <p>{{ __('The Income & Expenditure sheet is this same money added up down the analysis column. If a figure on the sheet looks wrong, open the month here and read the entries behind it — the two cannot disagree, because they are built from the same records.') }}</p>

                    <h6>{{ __('The column to watch') }}</h6>
                    <p>{{ __('A row whose analysis column reads "none" is money the sheet cannot report. It happened, the ledger has it, but no budget line claims it — so it shows nowhere in the budget. Those rows are counted at the top of this page whenever there are any.') }}</p>
                </div>
            </div>
        </div>

        {{-- Money with no analysis column cannot appear on the sheet at all, so
             it is stated before the book rather than left to be spotted. --}}
        @if(count($unanalysed))
            <div class="alert alert-warning">
                <strong>
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    {{ trans_choice(':count movement has no analysis column|:count movements have no analysis column', count($unanalysed), ['count' => count($unanalysed)]) }}
                </strong>
                <p class="mb-0 mt-1 small">
                    {{ __('They are listed below and marked. Until a category points them at a budget line they will not appear on the Income & Expenditure sheet, however much money they carry.') }}
                </p>
            </div>
        @endif

        <div class="db-figures">
            <div class="db-figure in">
                <div class="label">{{ __('Money in') }}</div>
                <div class="value">{{ number_format($totalIn) }}</div>
            </div>
            <div class="db-figure out">
                <div class="label">{{ __('Money out') }}</div>
                <div class="value">{{ number_format($totalOut) }}</div>
            </div>
            <div class="db-figure">
                <div class="label">{{ __('Net movement') }}</div>
                <div class="value">{{ number_format($totalIn - $totalOut) }}</div>
            </div>
            <div class="db-figure">
                <div class="label">{{ __('Entries') }}</div>
                <div class="value">{{ number_format(count($rows)) }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $period->name }}</h5>
                <small class="text-muted">
                    {{-- Said plainly: nothing here was typed into this screen. --}}
                    {{ __('Generated from fees, incomes, expenses and payroll already recorded. Nothing is entered here.') }}
                </small>
            </div>

            <div class="card-block table-responsive">
                <table class="table db-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:92px">{{ __('Date') }}</th>
                            <th style="width:110px">{{ __('Ref') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th style="width:210px">{{ __('Analysis column') }}</th>
                            <th style="width:120px" class="db-num">{{ __('Debit IN') }}</th>
                            <th style="width:120px" class="db-num">{{ __('Credit OUT') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M') }}</td>
                                <td class="db-ref">{{ $row['ref'] }}</td>
                                <td>
                                    {{ $row['description'] }}
                                    <span class="db-source">{{ $row['source'] }}</span>
                                </td>
                                <td>
                                    @php $line = $row['line_id'] ? ($lines[$row['line_id']] ?? null) : null; @endphp
                                    @if($line)
                                        <span class="db-line">{{ $line->code }} {{ $line->name }}</span>
                                    @else
                                        <span class="db-line db-line-none">
                                            <i class="fas fa-exclamation-triangle me-1"></i>{{ __('none') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="db-num db-in">
                                    {{ $row['direction'] === 'in' ? number_format($row['amount']) : '' }}
                                </td>
                                <td class="db-num db-out">
                                    {{ $row['direction'] === 'out' ? number_format($row['amount']) : '' }}
                                </td>
                            </tr>
                        @empty
                            {{-- A quiet month is a fact, not a failure. --}}
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    {{ __('No cash moved in :period.', ['period' => $period->name]) }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($rows))
                        <tfoot>
                            <tr class="db-foot">
                                <td colspan="4">{{ __('TOTAL FOR') }} {{ strtoupper($period->name) }}</td>
                                <td class="db-num">{{ number_format($totalIn) }}</td>
                                <td class="db-num">{{ number_format($totalOut) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
