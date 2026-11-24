@extends('admin.layouts.master')
@section('title', __('balance_sheet'))

@php
    // Negative amounts (depreciation under fixed assets, a loss in the result)
    // print in brackets, the way a balance sheet shows a deduction.
    $fmt = fn ($v) => format_amount($v, true);
    $cmp = $comparative ?? null;
    $cmpLine = function (string $section, $account) use ($cmp) {
        return $cmp ? ($cmp[$section]->firstWhere('id', $account->id)->balance ?? 0) : null;
    };
    $sections = [
        'actif' => [
            ['key' => 'fixedAssets', 'label' => __('Fixed assets') . ' (' . __('Actif immobilisé') . ')'],
            ['key' => 'inventory', 'label' => __('Inventory') . ' (' . __('Stocks') . ')'],
            ['key' => 'receivables', 'label' => __('Receivables') . ' (' . __('Créances') . ')'],
            ['key' => 'cashAndBanks', 'label' => __('Cash and banks') . ' (' . __('Trésorerie-Actif') . ')'],
        ],
        'passif' => [
            ['key' => 'financialDebts', 'label' => __('Borrowings and provisions') . ' (' . __('Dettes financières') . ')'],
            ['key' => 'payables', 'label' => __('Payables') . ' (' . __('Passif circulant') . ')'],
            ['key' => 'treasuryLiabilities', 'label' => __('Bank overdrafts') . ' (' . __('Trésorerie-Passif') . ')'],
        ],
    ];
    $balanced = abs($difference) < 0.01;
@endphp

@section('content')
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h3 class="card-title mb-0">{{ __('balance_sheet') }} ({{ __('bilan') }})</h3>
                                <small class="text-muted">
                                    {{ __('as_of') }}: {{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }}
                                    @if($fiscalYear) &middot; {{ $fiscalYear->name }} @endif
                                </small>
                            </div>
                            <div class="no-print">
                                <a href="{{ route('admin.general-ledger.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> {{ __('back') }}
                                </a>
                                <button type="button" class="btn btn-info btn-sm" onclick="window.print()">
                                    <i class="fas fa-print"></i> {{ __('Print') }}
                                </button>
                                @can('balance-sheet-export')
                                <a href="{{ route('admin.general-ledger.balance-sheet-pdf', request()->all()) }}" class="btn btn-danger btn-sm" target="_blank">
                                    <i class="fas fa-file-pdf"></i> {{ __('export_pdf') }}
                                </a>
                                <a href="{{ route('admin.general-ledger.balance-sheet-excel', request()->all()) }}" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel"></i> {{ __('export_excel') }}
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4 no-print">
                            <div class="col-md-12">
                                <form action="{{ route('admin.general-ledger.balance-sheet') }}" method="GET" class="form-inline">
                                    <div class="form-group mr-3">
                                        <label for="as_of_date" class="mr-2">{{ __('as_of_date') }}:</label>
                                        <input type="date" class="form-control" id="as_of_date" name="as_of_date" value="{{ $asOfDate }}" required>
                                    </div>
                                    <div class="form-group mr-3">
                                        <label for="comparative_date" class="mr-2">{{ __('comparative_date') }} ({{ __('optional') }}):</label>
                                        <input type="date" class="form-control" id="comparative_date" name="comparative_date" value="{{ $comparativeDate ?? '' }}">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> {{ __('apply_filter') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive">
                            {{-- ACTIF --}}
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="50%">{{ __('assets') }} ({{ __('actif') }})</th>
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($asOfDate)->format('d/m/Y') }}</th>
                                        @if($cmp)
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($comparativeDate)->format('d/m/Y') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sections['actif'] as $section)
                                    <tr class="table-info">
                                        <td><strong>{{ $section['label'] }}</strong></td>
                                        <td class="text-right"><strong>{{ $fmt(${$section['key']}->sum('balance')) }}</strong></td>
                                        @if($cmp)<td class="text-right"><strong>{{ $fmt($cmp[$section['key']]->sum('balance')) }}</strong></td>@endif
                                    </tr>
                                    @foreach(${$section['key']} as $account)
                                    <tr>
                                        <td class="pl-4">
                                            {{ $account->account_code }} - {{ $account->account_name }}
                                            @unless($account->is_postable)<span class="badge badge-warning" title="{{ __('Entries were posted to a heading or inactive account. Move them to a detail account.') }}">{{ __('heading account') }}</span>@endunless
                                        </td>
                                        <td class="text-right">{{ $fmt($account->balance) }}</td>
                                        @if($cmp)<td class="text-right">{{ $fmt($cmpLine($section['key'], $account)) }}</td>@endif
                                    </tr>
                                    @endforeach
                                    @endforeach
                                    <tr class="table-success">
                                        <td><strong>{{ __('total_assets') }}</strong></td>
                                        <td class="text-right"><strong>{{ $fmt($totalAssets) }}</strong></td>
                                        @if($cmp)<td class="text-right"><strong>{{ $fmt($cmp['totalAssets']) }}</strong></td>@endif
                                    </tr>
                                </tbody>
                            </table>

                            {{-- PASSIF --}}
                            <table class="table table-bordered table-hover mt-4">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="50%">{{ __('liabilities_equity') }} ({{ __('passif') }})</th>
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($asOfDate)->format('d/m/Y') }}</th>
                                        @if($cmp)
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($comparativeDate)->format('d/m/Y') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-info">
                                        <td><strong>{{ __('equity_capital') }} ({{ __('capitaux_propres') }})</strong></td>
                                        <td class="text-right"><strong>{{ $fmt($totalEquity) }}</strong></td>
                                        @if($cmp)<td class="text-right"><strong>{{ $fmt($cmp['totalEquity']) }}</strong></td>@endif
                                    </tr>
                                    @foreach($equity as $account)
                                    <tr>
                                        <td class="pl-4">
                                            {{ $account->account_code }} - {{ $account->account_name }}
                                            @unless($account->is_postable)<span class="badge badge-warning">{{ __('heading account') }}</span>@endunless
                                        </td>
                                        <td class="text-right">{{ $fmt($account->balance) }}</td>
                                        @if($cmp)<td class="text-right">{{ $fmt($cmpLine('equity', $account)) }}</td>@endif
                                    </tr>
                                    @endforeach
                                    @if(abs($priorResult) > 0.004 || ($cmp && abs($cmp['priorResult']) > 0.004))
                                    <tr>
                                        <td class="pl-4">{{ __('Results of earlier years not yet closed') }} ({{ __('Report à nouveau') }})</td>
                                        <td class="text-right">{{ $fmt($priorResult) }}</td>
                                        @if($cmp)<td class="text-right">{{ $fmt($cmp['priorResult']) }}</td>@endif
                                    </tr>
                                    @endif
                                    <tr>
                                        <td class="pl-4">
                                            {{ __('Result for the year') }} ({{ __('Résultat net de l\'exercice') }})
                                            <small class="text-muted d-block">{{ $yearResult >= 0 ? __('Profit') : __('Loss') }} &middot; {{ __('income less expenses (classes 7 and 8 less class 6) not yet closed into equity') }}</small>
                                        </td>
                                        <td class="text-right {{ $yearResult < 0 ? 'text-danger' : '' }}">{{ $fmt($yearResult) }}</td>
                                        @if($cmp)<td class="text-right">{{ $fmt($cmp['yearResult']) }}</td>@endif
                                    </tr>

                                    @foreach($sections['passif'] as $section)
                                    <tr class="table-info">
                                        <td><strong>{{ $section['label'] }}</strong></td>
                                        <td class="text-right"><strong>{{ $fmt(${$section['key']}->sum('balance')) }}</strong></td>
                                        @if($cmp)<td class="text-right"><strong>{{ $fmt($cmp[$section['key']]->sum('balance')) }}</strong></td>@endif
                                    </tr>
                                    @foreach(${$section['key']} as $account)
                                    <tr>
                                        <td class="pl-4">
                                            {{ $account->account_code }} - {{ $account->account_name }}
                                            @unless($account->is_postable)<span class="badge badge-warning">{{ __('heading account') }}</span>@endunless
                                        </td>
                                        <td class="text-right">{{ $fmt($account->balance) }}</td>
                                        @if($cmp)<td class="text-right">{{ $fmt($cmpLine($section['key'], $account)) }}</td>@endif
                                    </tr>
                                    @endforeach
                                    @endforeach

                                    <tr class="table-success">
                                        <td><strong>{{ __('total_liabilities_equity') }}</strong></td>
                                        <td class="text-right"><strong>{{ $fmt($totalLiabilities) }}</strong></td>
                                        @if($cmp)<td class="text-right"><strong>{{ $fmt($cmp['totalLiabilities']) }}</strong></td>@endif
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if(isset($unclassified) && $unclassified->isNotEmpty())
                        <div class="alert alert-warning mt-3">
                            {{ __('Some accounts are outside classes 1 to 8 and are not on the balance sheet:') }}
                            {{ $unclassified->map(fn ($a) => $a->account_code . ' (' . $fmt($a->balance) . ')')->implode(', ') }}
                        </div>
                        @endif

                        <div class="alert {{ $balanced ? 'alert-success' : 'alert-danger' }} mt-3">
                            <h5>
                                <i class="icon fas {{ $balanced ? 'fa-check' : 'fa-exclamation-triangle' }}"></i>
                                {{ __('balance_verification') }}
                            </h5>
                            @if($balanced)
                                <p class="mb-0">{{ __('balance_sheet_is_balanced') }}: {{ __('assets') }} = {{ __('liabilities_equity') }}</p>
                            @else
                                <p class="mb-0">
                                    {{ __('balance_sheet_not_balanced') }}:
                                    {{ __('difference') }}: {{ format_amount(abs($difference)) }} FCFA.
                                    {{ __('The ledger itself does not balance: check the trial balance and the ledger health page.') }}
                                </p>
                            @endif
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    {{ __('report_generated_at') }}: {{ now()->format('d/m/Y H:i') }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('css')
<style>
    @media print {
        .no-print { display: none !important; }
        .card { box-shadow: none !important; border: none !important; }
    }
</style>
@endpush
