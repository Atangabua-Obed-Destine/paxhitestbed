@extends('admin.layouts.master')

@push('css')
<style>
    /* ---------------------------------------------------------------------
       The sheet is a wide numeric table read down a column and across a row at
       the same time, so the work here is keeping both axes trackable: a heading
       that stays put, quiet banding to follow a row across, and one obvious
       column — Budget — that is the only thing anyone types into.
       --------------------------------------------------------------------- */

    .sheet-scroll { overflow-x: auto; overflow-y: visible; }

    .sheet-table { border-collapse: separate; border-spacing: 0; min-width: 860px; }
    .sheet-table td, .sheet-table th {
        vertical-align: middle;
        border-bottom: 1px solid #edf0f2;
        padding: .38rem .6rem;
    }

    /* Column headings survive scrolling — a column of bare figures is
       unreadable once "Budget" and "Actual" have scrolled away.

       The theme sets `.table thead th { background: #3ea1e4; color: #fff
       !important }`. Overriding the background here without also forcing the
       colour left white text on a near-white band — headings that were there
       but invisible. The !important is not decoration: it is the only way to
       beat the theme's own !important. */
    .sheet-table thead th {
        position: sticky; top: 0; z-index: 3;
        background: #f7f9fa !important;
        border-bottom: 2px solid #cfd8dc;
        font-size: .7rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: .07em;
        color: #55636b !important; white-space: nowrap;
    }

    .sheet-table tbody tr:hover td { background-color: #f4f9f8; }

    .sheet-table .code {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        color: #78868d; width: 62px; font-size: .8rem;
    }

    .sheet-section td {
        background: #34495e; color: #fff; font-weight: 600;
        letter-spacing: .06em; text-transform: uppercase; font-size: .78rem;
        position: sticky; top: 34px; z-index: 2;
    }
    .sheet-section td, .sheet-table tbody tr.sheet-section:hover td { background-color: #34495e; }

    .sheet-header td { background: #eef1f6; font-weight: 600; }
    .sheet-table tbody tr.sheet-header:hover td { background-color: #e7edf3; }
    .sheet-child .name { padding-left: 1.6rem; }

    /* A group's own total, at its foot. Lighter than a section total, which
       closes a whole half of the sheet, but ruled off like one so the eye can
       tell a subtotal from another line of spending. */
    .sheet-subtotal td {
        background: #f7f9fb;
        font-weight: 600;
        border-top: 1px solid #c8d3e0;
        border-bottom: 1px solid #c8d3e0;
    }
    .sheet-subtotal .subtotal-name { padding-left: 1.6rem; font-style: italic; }

    .sheet-total td {
        border-top: 2px solid #34495e; border-bottom: none;
        font-weight: 700; background: #fbfcfd;
    }

    .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
        white-space: nowrap;
    }

    /* The one column anyone edits. Fixed width so the grid does not shift as
       figures are typed, and visibly a field rather than a printed number. */
    .sheet-table input.figure {
        text-align: right; width: 100%; max-width: 148px;
        margin-left: auto; display: block;
        font-variant-numeric: tabular-nums;
        border-color: #cfd8dc;
    }
    .sheet-table input.figure:focus {
        border-color: #34495e;
        box-shadow: 0 0 0 .15rem rgba(52,73,94,.12);
    }
    .budget-col { background: #fdfefe; }

    /* Favourable or not — which depends on the section. Over-collecting income
       is good news; overspending is not. Colouring purely by sign showed a
       shortfall in income as green. */
    .variance-good { color: #1e8449; }
    .variance-bad  { color: #c0392b; font-weight: 600; }

    /* Last year is context, not this year's figures — kept legible but quieter
       so the eye still lands on the Budget column being filled in. */
    .prior-col { color: #6c757d; background: #fbfbfc; font-size: .86rem; }

    .account-ref {
        display: block; font-size: .69rem; line-height: 1.35;
        color: #8a969c; margin-top: .1rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .account-missing { color: #b9770e; }

    /* The assumption behind a forecast figure, printed under it so a reader can
       tell a reasoned number from a typed one at a glance. */
    .forecast-basis {
        display: block; font-size: .68rem; line-height: 1.35;
        color: #1c5cab; margin-top: .15rem;
    }
    .forecast-stale { color: #b9770e; }
    .forecast-btn {
        background: transparent; border: 0; padding: 0; margin-top: .15rem;
        font-size: .68rem; color: #1c5cab; cursor: pointer;
        text-decoration: underline dotted; text-underline-offset: 2px;
    }
    .forecast-btn:hover { color: #0d366b; }

    .fc-prog { width: 100%; font-size: .84rem; }
    .fc-prog th {
        font-size: .64rem; text-transform: uppercase; letter-spacing: .6px;
        color: #8a969c; font-weight: 700; padding: .25rem .4rem; text-align: left;
    }
    .fc-prog td { padding: .25rem .4rem; border-top: 1px solid #eef1f5; }
    .fc-prog .num { text-align: right; font-variant-numeric: tabular-nums; }
    .fc-result {
        background: #f2f7ff; border-left: 3px solid #2a78d6; border-radius: 0 8px 8px 0;
        padding: .75rem 1rem; margin-top: 1rem;
    }
    .fc-result .value { font-size: 1.45rem; font-weight: 800; color: #0a2540; font-variant-numeric: tabular-nums; }
    .fc-warn {
        background: #fdf6ea; border-left: 3px solid #c98b2e; border-radius: 0 8px 8px 0;
        padding: .6rem .85rem; margin-top: .75rem; font-size: .82rem; color: #7a5312;
    }

    .local-flag {
        font-size: .62rem; color: #b9770e; background: #fdf3e3;
        border: 1px solid #f0d9ac; border-radius: 3px;
        padding: .05rem .3rem; margin-left: .4rem;
        text-transform: uppercase; letter-spacing: .04em; vertical-align: middle;
    }

    @media print {
        .sheet-scroll { overflow: visible; }
        .sheet-table { min-width: 0; }
        .sheet-table thead th, .sheet-section td { position: static; }
    }
</style>
@endpush

@section('content')
@php
    /** Money is shown as whole francs — nobody budgets in centimes. */
    $money = fn ($v) => $v ? number_format($v) : '—';
@endphp
<div class="pcoded-content">
    <div class="pcoded-inner-content">
        <div class="main-body">
            <div class="page-wrapper">

                <div class="page-header">
                    <div class="row align-items-end">
                        <div class="col-lg-8">
                            <div class="page-header-title">
                                <div class="d-inline">
                                    <h4>{{ __('Income & Expenditure') }} — {{ $budget->title }}</h4>
                                    <span>
                                        {{ optional($budget->start_date)->format('d M Y') }} &ndash;
                                        {{ optional($budget->end_date)->format('d M Y') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-right">
                            @php
                                /* Only the one step that is actually available is offered.
                                   Showing every button and rejecting four of them would
                                   make the sequence something to discover by trial. */
                                $stage = [
                                    'draft' => ['submit', __('Submit for approval'), 'btn-primary', 'budget-edit'],
                                    'pending_approval' => ['approve', __('Approve'), 'btn-success', 'budget-approve'],
                                    'approved' => ['activate', __('Activate for the year'), 'btn-success', 'budget-activate'],
                                    'active' => ['close', __('Close the year'), 'btn-warning', 'budget-close'],
                                ][$budget->status] ?? null;

                                $statusStyle = [
                                    'draft' => 'secondary', 'pending_approval' => 'info',
                                    'approved' => 'primary', 'active' => 'success',
                                    'closed' => 'dark', 'cancelled' => 'danger',
                                ][$budget->status] ?? 'secondary';
                            @endphp

                            <span class="badge badge-{{ $statusStyle }} mr-2" style="font-size:.8rem;vertical-align:middle">
                                {{ ucfirst(str_replace('_', ' ', $budget->status)) }}
                            </span>

                            @if($stage && auth()->user()->can($stage[3]))
                                <form action="{{ route('admin.budget-sheet.' . $stage[0], $budget->id) }}"
                                      method="post" class="d-inline"
                                      onsubmit="return confirm('{{ __('Continue?') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $stage[2] }}">{{ $stage[1] }}</button>
                                </form>
                            @endif

                            <a href="{{ route('admin.budget-sheet.index') }}" class="btn btn-outline-secondary btn-sm">
                                {{ __('All sheets') }}
                            </a>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('Print') }}
                            </button>
                            {{-- The signed, filed version of this sheet: same figures,
                                 on the school's letterhead, with signature blocks. --}}
                            <a href="{{ route('admin.budget-sheet.pdf', $budget->id) }}"
                               class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-file-pdf"></i> {{ __('PDF') }}
                            </a>
                            {{-- The workbook is for working in, not signing: figures go
                                 out as numbers so they can be summed and modelled. --}}
                            <a href="{{ route('admin.budget-sheet.excel', $budget->id) }}"
                               class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel"></i> {{ __('Excel') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="page-body">

                    {{-- The screen previously opened straight onto a grid of empty
                         boxes with no indication of what to do with them. --}}
                    <div class="alert alert-info">
                        <strong>{{ __('How this sheet works') }}</strong>
                        <p class="mb-1 mt-1 small">
                            {{ __('Type what you plan to spend or receive on each line in the Budget column, then save. The Actual column fills itself from the income and expenses already recorded for this period — you never type into it. Variance is the difference, and only appears once a line has a budget.') }}
                        </p>
                        <p class="mb-0 small text-muted">
                            {{ __('Actual figures shown cover') }}
                            <strong>{{ optional($budget->start_date)->format('d M Y') }}</strong>
                            {{ __('to') }}
                            <strong>{{ optional($budget->end_date)->format('d M Y') }}</strong>.
                            @if($editable)<a href="#correct-period" data-bs-toggle="collapse" role="button">{{ __('Correct the period') }}</a>@endif
                        </p>

                        {{-- Wrong dates report the wrong year's actuals, which is worse
                             than an empty sheet because the figures look authoritative. --}}
                        <div class="collapse mt-3" id="correct-period">
                            <form action="{{ route('admin.budget-sheet.period', $budget->id) }}" method="post" class="form-inline">
                                @csrf
                                <input type="text" name="title" class="form-control form-control-sm mr-2 mb-1"
                                       value="{{ $budget->title }}" required aria-label="{{ __('Period name') }}">
                                <input type="date" name="start_date" class="form-control form-control-sm mr-2 mb-1"
                                       value="{{ optional($budget->start_date)->format('Y-m-d') }}" required>
                                <input type="date" name="end_date" class="form-control form-control-sm mr-2 mb-1"
                                       value="{{ optional($budget->end_date)->format('Y-m-d') }}" required>
                                <button type="submit" class="btn btn-sm btn-secondary mb-1">{{ __('Update period') }}</button>
                            </form>
                        </div>
                    </div>

                    {{-- Where this sheet is in its life, and what happens next.
                         The five stages each mean something different and are not
                         reversible, so "Activate" should never be a button someone
                         presses to find out what it does. --}}
                    @php
                        $stages = [
                            'draft' => [
                                __('Draft'),
                                __('Figures can be typed and changed freely. Nothing is committed. When the sheet is ready, submit it for approval.'),
                            ],
                            'pending_approval' => [
                                __('Awaiting approval'),
                                __('The figures are locked while they are reviewed — this is what stops them shifting underneath the person approving them. An approver either approves the sheet or it goes back to draft.'),
                            ],
                            'approved' => [
                                __('Approved'),
                                __('The figures are agreed but the year has not started. Activate the sheet when the period begins; it then becomes the budget everything is measured against.'),
                            ],
                            'active' => [
                                __('Active'),
                                __('This is the live budget for the year. Actuals fill in by themselves as income and expenses are recorded. Close it once the year ends.'),
                            ],
                            'closed' => [
                                __('Closed'),
                                __('The year is finished and the figures are final. Its closing balance carries forward as the opening balance of the next sheet, and its actuals become the comparison column when the next year is budgeted.'),
                            ],
                            'cancelled' => [
                                __('Cancelled'),
                                __('This sheet was abandoned and is kept only for the record.'),
                            ],
                        ];
                        $order = ['draft', 'pending_approval', 'approved', 'active', 'closed'];
                        $currentIndex = array_search($budget->status, $order, true);
                    @endphp

                    <div class="card">
                        <div class="card-block">
                            <div class="d-flex flex-wrap align-items-center mb-2" style="gap:.35rem;">
                                @foreach($order as $i => $key)
                                    @php
                                        $done = $currentIndex !== false && $i < $currentIndex;
                                        $here = $budget->status === $key;
                                    @endphp
                                    <span class="badge {{ $here ? 'bg-primary' : ($done ? 'bg-success' : 'bg-light text-muted border') }}"
                                          style="font-size:.72rem;padding:.4rem .6rem;">
                                        {{ $i + 1 }}. {{ $stages[$key][0] }}
                                    </span>
                                    @if($i < count($order) - 1)
                                        <span class="text-muted small">&rarr;</span>
                                    @endif
                                @endforeach
                            </div>
                            <p class="mb-0 small">
                                <strong>{{ $stages[$budget->status][0] ?? ucfirst($budget->status) }}.</strong>
                                {{ $stages[$budget->status][1] ?? '' }}
                            </p>
                        </div>
                    </div>

                    @if(count($unallocated))
                        <div class="alert alert-warning">
                            <strong>{{ __('Money with nowhere to go') }}</strong>
                            <p class="mb-1 mt-1 small">
                                {{ __('These amounts fall in the period but no rule says which line they belong to, so they are not counted in the totals below. Map their category to fix this.') }}
                            </p>
                            <ul class="mb-0 small">
                                @foreach($unallocated as $source => $amount)
                                    <li>{{ ucfirst($source) }}: <strong>{{ number_format($amount) }}</strong></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Does this sheet tie back to the accounts? The sheet is grouped by
                         activity and the ledger by nature of expense, so they are two views
                         of the same money and must add to the same figure. Saying so here
                         is what makes the sheet a control rather than a spreadsheet. --}}
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ __('Agreement with the accounts') }}</h5>
                            @if($reconciliation['agrees'])
                                <span class="badge bg-success">{{ __('Reconciled') }}</span>
                            @else
                                <span class="badge bg-danger">{{ __('Needs attention') }}</span>
                            @endif
                        </div>
                        <div class="card-block">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Section') }}</th>
                                            <th class="text-end">{{ __('This sheet') }}</th>
                                            <th class="text-end">{{ __('Ledger') }}</th>
                                            <th class="text-end">{{ __('Difference') }}</th>
                                            <th>{{ __('Accounts') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($reconciliation['sections'] as $s)
                                            <tr>
                                                <td>{{ __($s['label']) }}</td>
                                                <td class="text-end">{{ number_format($s['sheet']) }}</td>
                                                <td class="text-end">{{ number_format($s['ledger']) }}</td>
                                                <td class="text-end {{ $s['agrees'] ? 'text-success' : 'text-danger fw-bold' }}">
                                                    {{ $s['agrees'] ? '—' : number_format($s['difference']) }}
                                                </td>
                                                <td class="small text-muted">
                                                    {{ __('OHADA class :class', ['class' => $s['class']]) }}
                                                    @if($s['section'] === 'capital')
                                                        <span title="{{ __('Capital purchases become assets, so they are not charged to expenses.') }}">
                                                            — {{ __('capitalised, not expensed') }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if(count($reconciliation['issues']))
                                <div class="alert alert-danger mt-3 mb-0">
                                    <strong>{{ __('The sheet cannot be tied to the accounts') }}</strong>
                                    <ul class="mb-0 mt-1 small">
                                        @foreach($reconciliation['issues'] as $issue)
                                            <li>{{ $issue['detail'] }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>

                    <form action="{{ route('admin.budget-sheet.figures', $budget->id) }}" method="post">
                        @csrf

                        <div class="card">
                            <div class="card-block table-border-style">
                                <div class="table-responsive sheet-scroll">
                                    <table class="table table-sm sheet-table">
                                        <thead>
                                            <tr>
                                                <th class="code">{{ __('Code') }}</th>
                                                <th>{{ __('Description') }}</th>
                                                {{-- Last year's actual, because a budget is built from what
                                                     really happened, not from the previous plan. --}}
                                                <th class="num prior-col" style="width:130px">{{ $previous ? $previous->title : __('Last year') }}</th>
                                                <th class="num" style="width:180px">{{ __('Budget') }}</th>
                                                <th class="num" style="width:140px">{{ __('Actual') }}</th>
                                                <th class="num" style="width:140px">{{ __('Variance') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <tr class="sheet-total">
                                                <td></td>
                                                <td>{{ __('OPENING BALANCE') }}</td>
                                                <td class="num prior-col">{{ $previous ? $money($priorTotals['opening']) : '—' }}</td>
                                                <td class="num">
                                                    @if($editable)
                                                        <input type="number" step="0.01" name="opening_balance"
                                                               class="form-control form-control-sm figure"
                                                               value="{{ old('opening_balance', $budget->opening_balance) }}">
                                                    @else
                                                        {{ $money($budget->opening_balance) }}
                                                    @endif
                                                </td>
                                                <td class="num">{{ $money($totals['actual']['opening']) }}</td>
                                                <td class="num">—</td>
                                            </tr>

                                            @php $currentSection = null; @endphp
                                            @foreach($lines as $line)
                                                @if($line->section !== $currentSection)
                                                    @php $currentSection = $line->section; @endphp

                                                    @if($currentSection === 'expenditure')
                                                        {{-- Close income with its total before expenditure starts. --}}
                                                        <tr class="sheet-total">
                                                            <td></td>
                                                            <td>{{ __('TOTAL INCOME') }}</td>
                                                            <td class="num prior-col">{{ $previous ? $money($priorTotals['income']) : '—' }}</td>
                                                            <td class="num">{{ $money($totals['budget']['income']) }}</td>
                                                            <td class="num">{{ $money($totals['actual']['income']) }}</td>
                                                            @php
                                                                $tv = $totals['actual']['income'] - $totals['budget']['income'];
                                                                $tGood = $totals['budget']['income'] ? ($tv >= 0) : null;
                                                            @endphp
                                                            <td class="num {{ is_null($tGood) ? '' : ($tv == 0 ? '' : ($tGood ? 'variance-good' : 'variance-bad')) }}">
                                                                {{ $money($tv) }}
                                                            </td>
                                                        </tr>
                                                    @endif

                                                    @if($currentSection === 'capital')
                                                        <tr class="sheet-total">
                                                            <td></td>
                                                            <td>{{ __('TOTAL EXPENDITURE') }}</td>
                                                            <td class="num prior-col">{{ $previous ? $money($priorTotals['expenditure']) : '—' }}</td>
                                                            <td class="num">{{ $money($totals['budget']['expenditure']) }}</td>
                                                            <td class="num">{{ $money($totals['actual']['expenditure']) }}</td>
                                                            @php
                                                                $tv = $totals['actual']['expenditure'] - $totals['budget']['expenditure'];
                                                                $tGood = $totals['budget']['expenditure'] ? ($tv <= 0) : null;
                                                            @endphp
                                                            <td class="num {{ is_null($tGood) ? '' : ($tv == 0 ? '' : ($tGood ? 'variance-good' : 'variance-bad')) }}">
                                                                {{ $money($tv) }}
                                                            </td>
                                                        </tr>
                                                        <tr class="sheet-total">
                                                            <td></td>
                                                            <td>{{ __('CLOSING BALANCE') }}</td>
                                                            <td class="num prior-col">{{ $previous ? $money($priorTotals['closing']) : '—' }}</td>
                                                            <td class="num">{{ $money($totals['budget']['closing']) }}</td>
                                                            <td class="num">{{ $money($totals['actual']['closing']) }}</td>
                                                            <td class="num">—</td>
                                                        </tr>
                                                    @endif

                                                    <tr class="sheet-section">
                                                        <td colspan="6">
                                                            @if($currentSection === 'income') {{ __('Income') }}
                                                            @elseif($currentSection === 'expenditure') {{ __('Expenditure') }}
                                                            @else {{ __('Capital Expenditure Accounts') }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif

                                                @php
                                                    $b = $budgeted[$line->id] ?? 0;
                                                    $a = $actual[$line->id] ?? 0;
                                                    $v = $a - $b;

                                                    // Whether a variance is good news depends on which side of the
                                                    // sheet it falls on: beating an income target is favourable,
                                                    // beating an expenditure one is an overspend. Colouring by sign
                                                    // alone showed every income shortfall in green.
                                                    $favourable = $line->section === 'income' ? $v >= 0 : $v <= 0;
                                                    $varianceClass = $b ? ($v == 0 ? '' : ($favourable ? 'variance-good' : 'variance-bad')) : '';
                                                @endphp

                                                <tr class="{{ $line->is_header ? 'sheet-header' : 'sheet-child' }}">
                                                    <td class="code">{{ $line->code }}</td>
                                                    <td class="name">
                                                        {{ $line->name }}
                                                        {{-- Set apart from the name: run together, "Tuition Fees
                                                             Agriculture added locally" reads as the name of the line
                                                             rather than a note about it. --}}
                                                        @if($line->is_local)
                                                            <span class="local-flag" title="{{ __('Not on the standard diocesan form — added for this institution') }}">
                                                                {{ __('local') }}
                                                            </span>
                                                        @endif

                                                        {{-- Which statutory account this line's money is posted to.
                                                             Without it the sheet and the chart of accounts look like
                                                             two unrelated structures rather than two views of one. --}}
                                                        @if(!$line->is_header)
                                                            @if(!empty($accountsByLine[$line->id]))
                                                                <span class="account-ref">{{ implode(' · ', $accountsByLine[$line->id]) }}</span>
                                                            @else
                                                                {{-- A line no category reaches can never collect an
                                                                     actual, so it would sit at zero for ever without
                                                                     anything saying why. --}}
                                                                <span class="account-ref account-missing">
                                                                    {{ __('no category maps here — this line will stay empty') }}
                                                                </span>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="num prior-col">
                                                        @php $p = $priorActual[$line->id] ?? 0; @endphp
                                                        {{ $p ? $money($p) : '—' }}
                                                    </td>
                                                    <td class="num budget-col">
                                                        @if($line->is_header)
                                                            {{ $money($b) }}
                                                        @else
                                                            @if($editable)
                                                                <input type="number" step="0.01" min="0"
                                                                       class="form-control form-control-sm figure"
                                                                       name="amounts[{{ $line->id }}]"
                                                                       value="{{ $b ?: '' }}">
                                                            @else
                                                                {{ $money($b) }}
                                                            @endif
                                                            {{-- Tuition can be reasoned from expected enrolment instead
                                                                 of typed. The assumption is printed under the figure so a
                                                                 reader can tell which lines were worked out. --}}
                                                            @php
                                                                $fc = $forecasts[$line->id] ?? null;
                                                                $fcRate = $forecastRates[$line->id] ?? null;

                                                                // Built here, not inline: an array literal inside
                                                                // @json() in an attribute is more than Blade's
                                                                // parser can read.
                                                                $fcPayload = [
                                                                    'line_id' => $line->id,
                                                                    'code' => $line->code,
                                                                    'name' => $line->name,
                                                                ];
                                                            @endphp

                                                            @if($fcRate)
                                                                <button type="button" class="forecast-btn"
                                                                        data-bs-toggle="modal" data-bs-target="#forecastModal"
                                                                        onclick='budgetForecast(@json($fcPayload))'>
                                                                    <i class="fas fa-users me-1"></i>{{ $fc ? __('Edit forecast') : __('Forecast from students') }}
                                                                </button>
                                                            @endif

                                                            @if($fc)
                                                                @php $stale = $fcRate && $fc->isStaleAgainst((float) $fcRate['rate']); @endphp
                                                                <span class="forecast-basis {{ $stale ? 'forecast-stale' : '' }}">
                                                                    {{ trans_choice(':count student|:count students', $fc->student_count, ['count' => number_format($fc->student_count)]) }}
                                                                    &times; {{ number_format($fc->rate) }}
                                                                    @if($stale)
                                                                        <br><i class="fas fa-exclamation-triangle me-1"></i>{{ __('fees have changed since — now') }} {{ number_format($fcRate['rate']) }}
                                                                    @endif
                                                                </span>
                                                            @endif

                                                            @if(($delegated[$line->id] ?? 0) > 0)
                                                                {{-- Part of this line has been handed to a
                                                                     department to spend; the rest is central. --}}
                                                                <small class="d-block text-muted">
                                                                    {{ __('of which') }} {{ number_format($delegated[$line->id]) }} {{ __('delegated') }}
                                                                </small>
                                                                @if($b && $delegated[$line->id] > $b)
                                                                    <small class="d-block text-danger">
                                                                        {{ __('more delegated than budgeted') }}
                                                                    </small>
                                                                @endif
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="num">{{ $money($a) }}</td>
                                                    {{-- Variance only means something once a budget has been set.
                                                         Showing actual-minus-nothing made every unbudgeted line
                                                         report its full spend as a "variance", which reads as an
                                                         alarming overspend against a budget nobody entered. --}}
                                                    <td class="num {{ $varianceClass }}"
                                                        @if($b) title="{{ $favourable ? __('Favourable') : __('Unfavourable') }}" @endif>
                                                        @if($b)
                                                            {{ number_format($v) }}
                                                        @else
                                                            <span class="text-muted" title="{{ __('No budget set for this line') }}">—</span>
                                                        @endif
                                                    </td>
                                                </tr>

                                                {{-- The group's own total, at its foot. The heading row carries
                                                     the same figure at the top, but a reader following a column
                                                     of numbers downwards needs it where the group actually ends
                                                     — that is where the eye stops and where a paper sheet rules
                                                     a line. --}}
                                                @if(isset($groupEnds[$line->id]))
                                                    @php
                                                        $gh = $groupEnds[$line->id];
                                                        $gb = $budgeted[$gh->id] ?? 0;
                                                        $ga = $actual[$gh->id] ?? 0;
                                                        $gp = $priorActual[$gh->id] ?? 0;
                                                        $gv = $ga - $gb;
                                                        $gFavourable = $gh->section === 'income' ? $gv >= 0 : $gv <= 0;
                                                    @endphp
                                                    <tr class="sheet-subtotal">
                                                        <td></td>
                                                        <td class="subtotal-name">
                                                            {{ __('Total') }} {{ $gh->name }}
                                                        </td>
                                                        <td class="num prior-col">{{ $gp ? $money($gp) : '—' }}</td>
                                                        <td class="num">{{ $money($gb) }}</td>
                                                        <td class="num">{{ $money($ga) }}</td>
                                                        <td class="num {{ $gb ? ($gv == 0 ? '' : ($gFavourable ? 'variance-good' : 'variance-bad')) : '' }}">
                                                            @if($gb)
                                                                {{ number_format($gv) }}
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach

                                            <tr class="sheet-total">
                                                <td></td>
                                                <td>{{ __('TOTAL CAPITAL EXPENDITURE') }}</td>
                                                <td class="num prior-col">{{ $previous ? $money($priorTotals['capital']) : '—' }}</td>
                                                <td class="num">{{ $money($totals['budget']['capital']) }}</td>
                                                <td class="num">{{ $money($totals['actual']['capital']) }}</td>
                                                @php
                                                    $tv = $totals['actual']['capital'] - $totals['budget']['capital'];
                                                    $tGood = $totals['budget']['capital'] ? ($tv <= 0) : null;
                                                @endphp
                                                <td class="num {{ is_null($tGood) ? '' : ($tv == 0 ? '' : ($tGood ? 'variance-good' : 'variance-bad')) }}">
                                                    {{ $money($tv) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            @if($editable)
                                <div class="card-footer text-right">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> {{ __('Save figures') }}
                                    </button>
                                </div>
                            @else
                                <div class="card-footer text-muted small">
                                    {{ __('These figures are locked because the sheet has been submitted. Actual figures continue to update on their own.') }}
                                </div>
                            @endif
                        </div>
                    </form>

                    {{-- The closing balance above follows the paper exactly, which means it
                         is not a cash figure: depreciation is deducted but never leaves the
                         bank, and capital spending leaves the bank but is not deducted.
                         Stating the difference is the only way the sheet can be read
                         without being misread. --}}
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('Reconciliation to cash') }}</h5>
                        </div>
                        <div class="card-block">
                            <p class="text-muted small">
                                {{ __('The closing balance above is presented as on the paper form. It is not the bank position, because depreciation is deducted without money moving, and capital spending moves money without being deducted.') }}
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm" style="max-width:520px">
                                    <tbody>
                                        <tr>
                                            <td>{{ __('Closing balance (as presented)') }}</td>
                                            <td class="num">{{ $money($totals['actual']['closing']) }}</td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Add back depreciation (500–503)') }}</td>
                                            <td class="num">{{ $money($totals['actual']['depreciation']) }}</td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Less capital expenditure (540–542)') }}</td>
                                            <td class="num">({{ $money($totals['actual']['capital']) }})</td>
                                        </tr>
                                        <tr class="sheet-total">
                                            <td>{{ __('Expected cash / bank position') }}</td>
                                            <td class="num">{{ $money($totals['actual']['cash']) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- Budgeting tuition from expected enrolment. --}}
@if($editable && !empty($forecastRates))
@php
    $savedForecasts = $forecasts->map(fn ($f) => [
        'student_count' => $f->student_count,
        'rate' => (float) $f->rate,
        'rate_basis' => $f->rate_basis,
        'note' => $f->note,
    ]);
@endphp
<div class="modal fade" id="forecastModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.budget-sheet.forecast', $budget->id) }}">
                @csrf
                <input type="hidden" name="budget_line_id" id="fc_line_id">
                <input type="hidden" name="rate_basis" id="fc_basis" value="weighted">

                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ __('Forecast from student numbers') }}
                        <small class="d-block text-muted" id="fc_line_label"></small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small">
                        {{ __('Budget this line by saying how many students you expect and what they pay, rather than by typing an amount. The rate comes from the fees configured for each programme, weighted by how many students are on each — so it is right for the mix you actually have.') }}
                    </p>

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="fc_students">{{ __('Expected students') }} <span class="text-danger">*</span></label>
                            <input type="number" min="0" step="1" class="form-control" name="student_count" id="fc_students" required>
                            <small class="text-muted" id="fc_students_note"></small>
                        </div>

                        <div class="col-md-7 mb-3">
                            <label for="fc_rate">{{ __('Fee per student, per year') }} <span class="text-danger">*</span></label>
                            <input type="number" min="0" step="0.01" class="form-control" name="rate" id="fc_rate" required>
                            <small class="text-muted" id="fc_rate_note"></small>
                        </div>
                    </div>

                    {{-- The working, not just the answer. A rate asserted without
                         its basis gets accepted; one that shows the programme mix
                         behind it gets checked. --}}
                    <table class="fc-prog">
                        <thead>
                            <tr>
                                <th>{{ __('Programme') }}</th>
                                <th class="num">{{ __('Students now') }}</th>
                                <th class="num">{{ __('Fee per year') }}</th>
                            </tr>
                        </thead>
                        <tbody id="fc_programmes"></tbody>
                    </table>

                    <div class="fc-warn d-none" id="fc_warnings"></div>

                    <div class="fc-result">
                        <div class="text-muted small">{{ __('This line would be budgeted at') }}</div>
                        <div class="value" id="fc_amount">0</div>
                    </div>

                    <div class="mt-3">
                        <label for="fc_note">{{ __('Note') }}</label>
                        <input type="text" class="form-control" name="note" id="fc_note"
                               placeholder="{{ __('Why this number — an expected intake, a known cohort leaving') }}">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Use this figure') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var RATES = @json($forecastRates);
    var SAVED = @json($savedForecasts);

    var fmt = new Intl.NumberFormat();

    function recalc() {
        var students = parseFloat(document.getElementById('fc_students').value) || 0;
        var rate = parseFloat(document.getElementById('fc_rate').value) || 0;
        document.getElementById('fc_amount').textContent = fmt.format(Math.round(students * rate));
    }

    window.budgetForecast = function (line) {
        var info = RATES[line.line_id] || {};
        var saved = SAVED[line.line_id] || null;

        document.getElementById('fc_line_id').value = line.line_id;
        document.getElementById('fc_line_label').textContent = line.code + ' — ' + line.name;

        // Seeded from what is already on record, so the starting point is a
        // fact rather than a blank box.
        document.getElementById('fc_students').value = saved ? saved.student_count : (info.students || 0);
        document.getElementById('fc_rate').value = saved ? saved.rate : Math.round(info.rate || 0);
        document.getElementById('fc_note').value = saved && saved.note ? saved.note : '';
        document.getElementById('fc_basis').value = saved ? saved.rate_basis : 'weighted';

        document.getElementById('fc_students_note').textContent =
            '{{ __('Currently enrolled in this faculty:') }} ' + fmt.format(info.students || 0);
        document.getElementById('fc_rate_note').textContent =
            '{{ __('Weighted by current enrolment across the programmes below.') }}';

        var body = document.getElementById('fc_programmes');
        body.innerHTML = '';
        (info.programmes || []).forEach(function (p) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + p.title + (p.incomplete
                    ? ' <span class="local-flag">{{ __('part-configured') }}</span>' : '')
                + '</td><td class="num">' + fmt.format(p.students)
                + '</td><td class="num">' + (p.fee > 0 ? fmt.format(p.fee) : '—') + '</td>';
            body.appendChild(tr);
        });

        var warn = document.getElementById('fc_warnings');
        if ((info.warnings || []).length) {
            warn.classList.remove('d-none');
            warn.innerHTML = '<strong>{{ __('Check the fee configuration:') }}</strong><br>' + info.warnings.join('<br>');
        } else {
            warn.classList.add('d-none');
            warn.innerHTML = '';
        }

        recalc();
    };

    document.addEventListener('DOMContentLoaded', function () {
        ['fc_students', 'fc_rate'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', recalc); }
        });

        // Typing over the derived rate makes it a decision, not a derivation —
        // and a later change to the configured fees must not then flag it stale.
        var rate = document.getElementById('fc_rate');
        if (rate) {
            rate.addEventListener('change', function () {
                document.getElementById('fc_basis').value = 'manual';
            });
        }
    });
})();
</script>
@endpush
@endif

@endsection
