@extends('admin.layouts.master')
@section('title', __('balance_sheet'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="card-title mb-0">{{ __('balance_sheet') }} ({{ __('bilan') }})</h3>
                                <small class="text-muted">{{ __('as_of') }}: {{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }}</small>
                            </div>
                            <div>
                                <a href="{{ route('admin.general-ledger.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> {{ __('back') }}
                                </a>
                                <button type="button" class="btn btn-info btn-sm" onclick="window.print()">
                                    <i class="fas fa-print"></i> {{ __('print') }}
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
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-4 no-print">
                            <div class="col-md-12">
                                <form action="{{ route('admin.general-ledger.balance-sheet') }}" method="GET" class="form-inline">
                                    <div class="form-group mr-3">
                                        <label for="as_of_date" class="mr-2">{{ __('as_of_date') }}:</label>
                                        <input type="date" class="form-control" id="as_of_date" name="as_of_date" 
                                               value="{{ $asOfDate }}" required>
                                    </div>
                                    <div class="form-group mr-3">
                                        <label for="comparative_date" class="mr-2">{{ __('comparative_date') }} ({{ __('optional') }}):</label>
                                        <input type="date" class="form-control" id="comparative_date" name="comparative_date" 
                                               value="{{ $comparativeDate ?? '' }}">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> {{ __('apply_filter') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Balance Sheet Report -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="50%">{{ __('assets') }} ({{ __('actif') }})</th>
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($asOfDate)->format('Y') }}</th>
                                        @if($comparativeDate)
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($comparativeDate)->format('Y') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Fixed Assets -->
                                    <tr class="table-info">
                                        <td><strong>{{ __('fixed_assets') }} ({{ __('immobilisations') }})</strong></td>
                                        <td class="text-right"><strong>{{ number_format($fixedAssets->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($fixedAssets_comparative->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                    @foreach($fixedAssets as $account)
                                    <tr>
                                        <td class="pl-4">{{ $account->account_code }} - {{ $account->account_name }}</td>
                                        <td class="text-right">{{ number_format($account->balance, 0, ',', ' ') }}</td>
                                        @if($comparativeDate)
                                        <td class="text-right">
                                            {{ number_format($fixedAssets_comparative->where('id', $account->id)->first()->balance ?? 0, 0, ',', ' ') }}
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach

                                    <!-- Inventory & Stocks -->
                                    <tr class="table-info">
                                        <td><strong>{{ __('inventory_stocks') }} ({{ __('stocks') }})</strong></td>
                                        <td class="text-right"><strong>{{ number_format($inventory->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($inventory_comparative->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                    @foreach($inventory as $account)
                                    <tr>
                                        <td class="pl-4">{{ $account->account_code }} - {{ $account->account_name }}</td>
                                        <td class="text-right">{{ number_format($account->balance, 0, ',', ' ') }}</td>
                                        @if($comparativeDate)
                                        <td class="text-right">
                                            {{ number_format($inventory_comparative->where('id', $account->id)->first()->balance ?? 0, 0, ',', ' ') }}
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach

                                    <!-- Receivables -->
                                    <tr class="table-info">
                                        <td><strong>{{ __('receivables') }} ({{ __('creances') }})</strong></td>
                                        <td class="text-right"><strong>{{ number_format($receivables->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($receivables_comparative->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                    @foreach($receivables as $account)
                                    <tr>
                                        <td class="pl-4">{{ $account->account_code }} - {{ $account->account_name }}</td>
                                        <td class="text-right">{{ number_format($account->balance, 0, ',', ' ') }}</td>
                                        @if($comparativeDate)
                                        <td class="text-right">
                                            {{ number_format($receivables_comparative->where('id', $account->id)->first()->balance ?? 0, 0, ',', ' ') }}
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach

                                    <!-- Cash & Banks -->
                                    <tr class="table-info">
                                        <td><strong>{{ __('cash_and_banks') }} ({{ __('tresorerie') }})</strong></td>
                                        <td class="text-right"><strong>{{ number_format($cashAndBanks->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($cashAndBanks_comparative->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                    @foreach($cashAndBanks as $account)
                                    <tr>
                                        <td class="pl-4">{{ $account->account_code }} - {{ $account->account_name }}</td>
                                        <td class="text-right">{{ number_format($account->balance, 0, ',', ' ') }}</td>
                                        @if($comparativeDate)
                                        <td class="text-right">
                                            {{ number_format($cashAndBanks_comparative->where('id', $account->id)->first()->balance ?? 0, 0, ',', ' ') }}
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach

                                    <!-- Total Assets -->
                                    <tr class="table-success">
                                        <td><strong>{{ __('total_assets') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalAssets, 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($totalAssets_comparative, 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                </tbody>
                            </table>

                            <!-- LIABILITIES & EQUITY -->
                            <table class="table table-bordered table-hover mt-4">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="50%">{{ __('liabilities_equity') }} ({{ __('passif') }})</th>
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($asOfDate)->format('Y') }}</th>
                                        @if($comparativeDate)
                                        <th width="25%" class="text-right">{{ \Carbon\Carbon::parse($comparativeDate)->format('Y') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Equity & Capital -->
                                    <tr class="table-info">
                                        <td><strong>{{ __('equity_capital') }} ({{ __('capitaux_propres') }})</strong></td>
                                        <td class="text-right"><strong>{{ number_format($equity->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($equity_comparative->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                    @foreach($equity as $account)
                                    <tr>
                                        <td class="pl-4">{{ $account->account_code }} - {{ $account->account_name }}</td>
                                        <td class="text-right">{{ number_format($account->balance, 0, ',', ' ') }}</td>
                                        @if($comparativeDate)
                                        <td class="text-right">
                                            {{ number_format($equity_comparative->where('id', $account->id)->first()->balance ?? 0, 0, ',', ' ') }}
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach

                                    <!-- Payables / Liabilities -->
                                    <tr class="table-info">
                                        <td><strong>{{ __('payables') }} ({{ __('dettes') }})</strong></td>
                                        <td class="text-right"><strong>{{ number_format($payables->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($payables_comparative->sum('balance'), 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                    @foreach($payables as $account)
                                    <tr>
                                        <td class="pl-4">{{ $account->account_code }} - {{ $account->account_name }}</td>
                                        <td class="text-right">{{ number_format($account->balance, 0, ',', ' ') }}</td>
                                        @if($comparativeDate)
                                        <td class="text-right">
                                            {{ number_format($payables_comparative->where('id', $account->id)->first()->balance ?? 0, 0, ',', ' ') }}
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach

                                    <!-- Total Liabilities & Equity -->
                                    <tr class="table-success">
                                        <td><strong>{{ __('total_liabilities_equity') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($totalLiabilities, 0, ',', ' ') }}</strong></td>
                                        @if($comparativeDate)
                                        <td class="text-right"><strong>{{ number_format($totalLiabilities_comparative, 0, ',', ' ') }}</strong></td>
                                        @endif
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Balance Check -->
                        <div class="alert {{ abs($totalAssets - $totalLiabilities) < 0.01 ? 'alert-success' : 'alert-danger' }} mt-3">
                            <h5>
                                <i class="icon fas {{ abs($totalAssets - $totalLiabilities) < 0.01 ? 'fa-check' : 'fa-exclamation-triangle' }}"></i>
                                {{ __('balance_verification') }}
                            </h5>
                            @if(abs($totalAssets - $totalLiabilities) < 0.01)
                                <p class="mb-0">{{ __('balance_sheet_is_balanced') }}: {{ __('assets') }} = {{ __('liabilities_equity') }}</p>
                            @else
                                <p class="mb-0">
                                    {{ __('balance_sheet_not_balanced') }}: 
                                    {{ __('difference') }}: {{ number_format(abs($totalAssets - $totalLiabilities), 0, ',', ' ') }} FCFA
                                </p>
                            @endif
                        </div>

                        <!-- Report Footer -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    {{ __('report_generated_at') }}: {{ now()->format('d/m/Y H:i') }}
                                </small>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection

@push('styles')
<style>
    @media print {
        .no-print {
            display: none !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>
@endpush
