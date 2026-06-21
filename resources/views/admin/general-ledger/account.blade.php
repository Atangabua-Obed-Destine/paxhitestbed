@extends('admin.layouts.master')
@section('title', __('account_ledger'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('account_ledger') }} - {{ $account->account_code }}: {{ $account->account_name }}</h3>
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
                        <!-- Account Information -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <th width="40%">{{ __('account_code') }}</th>
                                        <td><strong>{{ $account->account_code }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('account_name') }}</th>
                                        <td>{{ $account->account_name }}</td>
                                    </tr>
                                    @if($account->account_name_fr)
                                    <tr>
                                        <th>{{ __('account_name') }} (FR)</th>
                                        <td>{{ $account->account_name_fr }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>{{ __('account_type') }}</th>
                                        <td>{{ __(strtolower($account->account_type)) }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <th width="40%">{{ __('period') }}</th>
                                        <td>
                                            @if($startDate && $endDate)
                                            {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                                            @else
                                            {{ __('all_periods') }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('opening_balance') }}</th>
                                        <td class="text-right"><strong>{{ number_format($openingBalance, 0, ',', ' ') }}</strong> FCFA</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('closing_balance') }}</th>
                                        <td class="text-right"><strong>{{ number_format($closingBalance, 0, ',', ' ') }}</strong> FCFA</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('movement') }}</th>
                                        <td class="text-right">
                                            <span class="badge badge-{{ $closingBalance >= $openingBalance ? 'success' : 'danger' }}">
                                                {{ number_format(abs($closingBalance - $openingBalance), 0, ',', ' ') }} FCFA
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Filter Form -->
                        <div class="row mb-3 no-print">
                            <div class="col-md-12">
                                <form action="{{ route('admin.general-ledger.account', $account->id) }}" method="GET">
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

                        <!-- Transactions Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th width="8%">{{ __('date') }}</th>
                                        <th width="12%">{{ __('entry_number') }}</th>
                                        <th>{{ __('description') }}</th>
                                        <th width="10%" class="text-right">{{ __('debit') }}</th>
                                        <th width="10%" class="text-right">{{ __('credit') }}</th>
                                        <th width="12%" class="text-right">{{ __('running_balance') }}</th>
                                        <th width="8%" class="no-print">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Opening Balance Row -->
                                    <tr class="table-info">
                                        <td colspan="5" class="text-right"><strong>{{ __('opening_balance') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($openingBalance, 0, ',', ' ') }}</strong></td>
                                        <td class="no-print"></td>
                                    </tr>

                                    @php $runningBalance = $openingBalance; @endphp
                                    @forelse($transactions as $transaction)
                                    @php
                                        // Calculate running balance based on normal balance
                                        if ($account->normal_balance == 'debit') {
                                            $runningBalance += $transaction->debit - $transaction->credit;
                                        } else {
                                            $runningBalance += $transaction->credit - $transaction->debit;
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($transaction->journalEntry->entry_date)->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ route('admin.journal-entries.show', $transaction->journal_entry_id) }}">
                                                {{ $transaction->journalEntry->entry_number }}
                                            </a>
                                        </td>
                                        <td>
                                            {{ $transaction->description }}
                                            @if($transaction->journalEntry->description != $transaction->description)
                                            <br><small class="text-muted">{{ $transaction->journalEntry->description }}</small>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($transaction->debit > 0)
                                            {{ number_format($transaction->debit, 0, ',', ' ') }}
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($transaction->credit > 0)
                                            {{ number_format($transaction->credit, 0, ',', ' ') }}
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <strong class="text-{{ $runningBalance >= 0 ? 'success' : 'danger' }}">
                                                {{ number_format($runningBalance, 0, ',', ' ') }}
                                            </strong>
                                        </td>
                                        <td class="text-center no-print">
                                            <a href="{{ route('admin.journal-entries.show', $transaction->journal_entry_id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('no_transactions_found') }}</td>
                                    </tr>
                                    @endforelse

                                    <!-- Closing Balance Row -->
                                    <tr class="table-success">
                                        <td colspan="5" class="text-right"><strong>{{ __('closing_balance') }}</strong></td>
                                        <td class="text-right">
                                            <strong>{{ number_format($closingBalance, 0, ',', ' ') }}</strong>
                                        </td>
                                        <td class="no-print"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary -->
                        @if($transactions->count() > 0)
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <strong>{{ __('summary') }}:</strong>
                                    {{ __('total_transactions') }}: {{ $transactions->count() }} | 
                                    {{ __('total_debit') }}: {{ number_format($transactions->sum('debit'), 0, ',', ' ') }} FCFA | 
                                    {{ __('total_credit') }}: {{ number_format($transactions->sum('credit'), 0, ',', ' ') }} FCFA | 
                                    {{ __('net_movement') }}: {{ number_format($closingBalance - $openingBalance, 0, ',', ' ') }} FCFA
                                </div>
                            </div>
                        </div>
                        @endif
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
    }
</style>
@endpush
