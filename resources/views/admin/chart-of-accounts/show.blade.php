@extends('admin.layouts.master')
@section('title', __('account_details'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('account_details') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.chart-of-accounts.edit', $account->id) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> {{ __('edit') }}
                            </a>
                            <a href="{{ route('admin.chart-of-accounts.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <!-- Account Information -->
                            <div class="col-md-6">
                                <table class="table table-bordered">
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
                                        <th>{{ __('classe') }}</th>
                                        <td>
                                            <span class="badge badge-info">{{ __('classe') }} {{ $account->class_number }}</span>
                                            <br><small>{{ __('class_'.$account->class_number.'_name') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('parent_account') }}</th>
                                        <td>
                                            @if($account->parent)
                                            <a href="{{ route('admin.chart-of-accounts.show', $account->parent->id) }}">
                                                {{ $account->parent->account_code }} - {{ $account->parent->account_name }}
                                            </a>
                                            @else
                                            <span class="text-muted">{{ __('none') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('account_type') }}</th>
                                        <td>{{ __(strtolower($account->account_type)) }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('account_category') }}</th>
                                        <td>{{ __(strtolower($account->account_category).'_account') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('normal_balance') }}</th>
                                        <td>
                                            <span class="badge badge-{{ $account->normal_balance == 'debit' ? 'success' : 'warning' }}">
                                                {{ __($account->normal_balance) }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Balance Information -->
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">{{ __('opening_balance') }}</th>
                                        <td class="text-right">
                                            <strong>{{ number_format($account->opening_balance, 0, ',', ' ') }}</strong> FCFA
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('current_balance') }}</th>
                                        <td class="text-right">
                                            <strong class="text-{{ $account->current_balance >= 0 ? 'success' : 'danger' }}">
                                                {{ number_format($account->current_balance, 0, ',', ' ') }}
                                            </strong> FCFA
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('display_order') }}</th>
                                        <td>{{ $account->display_order }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('status_title') }}</th>
                                        <td>
                                            @if($account->is_active)
                                            <span class="badge badge-success">{{ __('active') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('inactive') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('system_account') }}</th>
                                        <td>
                                            @if($account->is_system)
                                            <span class="badge badge-warning">{{ __('yes') }}</span>
                                            @else
                                            <span class="badge badge-secondary">{{ __('no') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('created_at') }}</th>
                                        <td>{{ $account->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('updated_at') }}</th>
                                        <td>{{ $account->updated_at->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Child Accounts -->
                        @if($account->children->count() > 0)
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>{{ __('child_accounts') }} ({{ $account->children->count() }})</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('account_code') }}</th>
                                                <th>{{ __('account_name') }}</th>
                                                <th>{{ __('account_type') }}</th>
                                                <th>{{ __('current_balance') }}</th>
                                                <th>{{ __('action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($account->children as $child)
                                            <tr>
                                                <td>{{ $child->account_code }}</td>
                                                <td>{{ $child->account_name }}</td>
                                                <td>{{ __(strtolower($child->account_type)) }}</td>
                                                <td class="text-right">{{ number_format($child->current_balance, 0, ',', ' ') }} FCFA</td>
                                                <td>
                                                    <a href="{{ route('admin.chart-of-accounts.show', $child->id) }}" class="btn btn-info btn-xs">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Recent Transactions -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>{{ __('recent_transactions') }}</h5>
                                @if($transactions->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('date') }}</th>
                                                <th>{{ __('entry_number') }}</th>
                                                <th>{{ __('description') }}</th>
                                                <th class="text-right">{{ __('debit') }}</th>
                                                <th class="text-right">{{ __('credit') }}</th>
                                                <th class="text-right">{{ __('balance') }}</th>
                                                <th>{{ __('action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $runningBalance = $account->opening_balance; @endphp
                                            @foreach($transactions as $transaction)
                                            @php
                                                if ($account->normal_balance == 'debit') {
                                                    $runningBalance += $transaction->debit - $transaction->credit;
                                                } else {
                                                    $runningBalance += $transaction->credit - $transaction->debit;
                                                }
                                            @endphp
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($transaction->journalEntry->entry_date)->format('d M Y') }}</td>
                                                <td>{{ $transaction->journalEntry->entry_number }}</td>
                                                <td>{{ $transaction->description }}</td>
                                                <td class="text-right">{{ $transaction->debit > 0 ? number_format($transaction->debit, 0, ',', ' ') : '-' }}</td>
                                                <td class="text-right">{{ $transaction->credit > 0 ? number_format($transaction->credit, 0, ',', ' ') : '-' }}</td>
                                                <td class="text-right"><strong>{{ number_format($runningBalance, 0, ',', ' ') }}</strong></td>
                                                <td>
                                                    <a href="{{ route('admin.journal-entries.show', $transaction->journal_entry_id) }}" class="btn btn-info btn-xs">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    <a href="{{ route('admin.general-ledger.account', $account->id) }}" class="btn btn-primary btn-sm">
                                        {{ __('view_full_ledger') }}
                                    </a>
                                </div>
                                @else
                                <p class="text-muted">{{ __('no_transactions_found') }}</p>
                                @endif
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
