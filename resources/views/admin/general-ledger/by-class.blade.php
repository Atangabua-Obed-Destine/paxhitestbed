@extends('admin.layouts.master')
@section('title', __('accounts_by_class'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('ohada_class') }} {{ $classNumber }}: {{ $className }}</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                                <i class="fas fa-print"></i> {{ __('print') }}
                            </button>
                            <a href="{{ route('admin.general-ledger.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Class Information -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <h5>{{ $className }} ({{ $className_fr }})</h5>
                                    <p class="mb-0">{{ __('class_description', ['class' => $classNumber]) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Date Range Filter -->
                        <div class="row mb-3 no-print">
                            <div class="col-md-12">
                                <form action="{{ route('admin.general-ledger.by-class', $classNumber) }}" method="GET">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="start_date">{{ __('start_date') }}</label>
                                                <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="{{ request('start_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="end_date">{{ __('end_date') }}</label>
                                                <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="{{ request('end_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                    <i class="fas fa-filter"></i> {{ __('filter') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Accounts Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th width="12%">{{ __('account_code') }}</th>
                                        <th>{{ __('account_name') }}</th>
                                        <th width="10%" class="text-center">{{ __('type') }}</th>
                                        <th width="12%" class="text-right">{{ __('debit') }}</th>
                                        <th width="12%" class="text-right">{{ __('credit') }}</th>
                                        <th width="12%" class="text-right">{{ __('balance') }}</th>
                                        <th width="8%" class="no-print">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $classDebitTotal = 0;
                                        $classCreditTotal = 0;
                                        $currentParent = null;
                                    @endphp

                                    @foreach($accounts as $account)
                                        @php
                                            // Calculate balance based on normal balance type
                                            if ($account->normal_balance == 'debit') {
                                                $balance = $account->total_debit - $account->total_credit;
                                            } else {
                                                $balance = $account->total_credit - $account->total_debit;
                                            }

                                            $classDebitTotal += $account->total_debit;
                                            $classCreditTotal += $account->total_credit;
                                        @endphp

                                        <!-- Show parent account header if it changes -->
                                        @if($account->account_category == 'detail' && $account->parent_account_id && $currentParent != $account->parent_account_id)
                                            @php
                                                $currentParent = $account->parent_account_id;
                                                $parentAccount = $accounts->firstWhere('id', $currentParent);
                                            @endphp
                                            @if($parentAccount)
                                            <tr class="table-secondary">
                                                <td colspan="7">
                                                    <strong>{{ $parentAccount->account_code }} - {{ $parentAccount->account_name }}</strong>
                                                </td>
                                            </tr>
                                            @endif
                                        @endif

                                        <tr class="{{ $account->account_category == 'header' ? 'table-primary' : '' }}">
                                            <td>
                                                <strong>{{ $account->account_code }}</strong>
                                            </td>
                                            <td>
                                                {{ $account->account_name }}
                                                @if($account->account_name_fr)
                                                <br><small class="text-muted">{{ $account->account_name_fr }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($account->account_category == 'header')
                                                <span class="badge badge-secondary">{{ __('header') }}</span>
                                                @elseif($account->account_category == 'summary')
                                                <span class="badge badge-info">{{ __('summary') }}</span>
                                                @else
                                                <span class="badge badge-success">{{ __('detail') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if($account->total_debit > 0)
                                                {{ number_format($account->total_debit, 0, ',', ' ') }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if($account->total_credit > 0)
                                                {{ number_format($account->total_credit, 0, ',', ' ') }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <strong class="text-{{ $balance >= 0 ? 'success' : 'danger' }}">
                                                    {{ number_format($balance, 0, ',', ' ') }}
                                                </strong>
                                            </td>
                                            <td class="text-center no-print">
                                                @if($account->account_category == 'detail')
                                                <a href="{{ route('admin.general-ledger.account', $account->id) }}" class="btn btn-info btn-xs" title="{{ __('view_ledger') }}">
                                                    <i class="fas fa-book"></i>
                                                </a>
                                                @endif
                                                <a href="{{ route('admin.chart-of-accounts.show', $account->id) }}" class="btn btn-secondary btn-xs" title="{{ __('view') }}">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach

                                    <!-- Class Total Row -->
                                    <tr class="table-success">
                                        <td colspan="3" class="text-right"><strong>{{ __('class_total') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($classDebitTotal, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($classCreditTotal, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right">
                                            <strong>{{ number_format(abs($classDebitTotal - $classCreditTotal), 0, ',', ' ') }}</strong>
                                        </td>
                                        <td class="no-print"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary -->
                        @if($accounts->count() > 0)
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <strong>{{ __('summary') }}:</strong>
                                    {{ __('total_accounts') }}: {{ $accounts->count() }} | 
                                    {{ __('total_debit') }}: {{ number_format($classDebitTotal, 0, ',', ' ') }} FCFA | 
                                    {{ __('total_credit') }}: {{ number_format($classCreditTotal, 0, ',', ' ') }} FCFA | 
                                    {{ __('difference') }}: {{ number_format(abs($classDebitTotal - $classCreditTotal), 0, ',', ' ') }} FCFA
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="alert alert-warning">
                            {{ __('no_accounts_found_in_class') }}
                        </div>
                        @endif

                        <!-- Quick Navigation -->
                        <div class="row mt-3 no-print">
                            <div class="col-md-12">
                                <div class="btn-group btn-group-sm">
                                    @for($i = 1; $i <= 8; $i++)
                                    <a href="{{ route('admin.general-ledger.by-class', $i) }}" 
                                       class="btn btn-{{ $i == $classNumber ? 'primary' : 'outline-primary' }}">
                                        {{ __('class') }} {{ $i }}
                                    </a>
                                    @endfor
                                </div>
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
        .card-header .card-tools {
            display: none !important;
        }
        body {
            font-size: 12px;
        }
        .table-sm td, .table-sm th {
            padding: 0.2rem;
        }
    }
</style>
@endpush
