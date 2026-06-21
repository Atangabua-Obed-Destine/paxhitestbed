@extends('admin.layouts.master')
@section('title', __('bank_reconciliation_details'))

@push('css')
<style>
    .reconciliation-item.cleared {
        background-color: #d4edda !important;
    }
    .reconciliation-summary {
        font-size: 1.1em;
    }
    .summary-card {
        border-left: 4px solid;
    }
    .summary-card.book { border-color: #007bff; }
    .summary-card.statement { border-color: #28a745; }
    .summary-card.difference { border-color: #dc3545; }
</style>
@endpush

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Header Card -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            {{ __('bank_reconciliation') }}: {{ $reconciliation->bankAccount->account_name ?? 'N/A' }}
                            <span class="badge badge-{{ $reconciliation->status == 'completed' ? 'success' : ($reconciliation->status == 'in_progress' ? 'warning' : 'secondary') }}">
                                {{ __($reconciliation->status) }}
                            </span>
                        </h3>
                        <div class="card-tools">
                            @if($reconciliation->status != 'completed')
                            @can('bank-reconciliation-complete')
                            <form action="{{ route('admin.bank-reconciliation.complete', $reconciliation->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm mr-2" onclick="return confirm('{{ __('confirm_complete_reconciliation') }}')">
                                    <i class="fas fa-check"></i> {{ __('complete_reconciliation') }}
                                </button>
                            </form>
                            @endcan
                            @endif
                            <a href="{{ route('admin.bank-reconciliation.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Summary Cards -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card summary-card book">
                                    <div class="card-body py-2">
                                        <small class="text-muted">{{ __('book_balance') }}</small>
                                        <h4 class="mb-0">{{ number_format($reconciliation->book_balance, 0, ',', ' ') }} FCFA</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card summary-card statement">
                                    <div class="card-body py-2">
                                        <small class="text-muted">{{ __('statement_ending_balance') }}</small>
                                        <h4 class="mb-0">{{ number_format($reconciliation->statement_ending_balance, 0, ',', ' ') }} FCFA</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card summary-card book">
                                    <div class="card-body py-2">
                                        <small class="text-muted">{{ __('adjusted_book_balance') }}</small>
                                        <h4 class="mb-0">{{ number_format($reconciliation->adjusted_book_balance, 0, ',', ' ') }} FCFA</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                @php
                                    $difference = $reconciliation->statement_ending_balance - $reconciliation->adjusted_book_balance;
                                @endphp
                                <div class="card summary-card difference">
                                    <div class="card-body py-2">
                                        <small class="text-muted">{{ __('difference') }}</small>
                                        <h4 class="mb-0 {{ abs($difference) < 1 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($difference, 0, ',', ' ') }} FCFA
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reconciliation Info -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">{{ __('bank_account') }}</th>
                                        <td>{{ $reconciliation->bankAccount->account_code ?? '' }} - {{ $reconciliation->bankAccount->account_name ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('statement_date') }}</th>
                                        <td>{{ \Carbon\Carbon::parse($reconciliation->statement_date)->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('fiscal_year') }}</th>
                                        <td>{{ $reconciliation->fiscalYear->name ?? 'N/A' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">{{ __('statement_beginning_balance') }}</th>
                                        <td>{{ number_format($reconciliation->statement_beginning_balance, 0, ',', ' ') }} FCFA</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('prepared_by') }}</th>
                                        <td>{{ $reconciliation->preparedBy->name ?? 'N/A' }}</td>
                                    </tr>
                                    @if($reconciliation->completed_at)
                                    <tr>
                                        <th>{{ __('completed_at') }}</th>
                                        <td>{{ \Carbon\Carbon::parse($reconciliation->completed_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Checks -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="card-title mb-0"><i class="fas fa-money-check"></i> {{ __('outstanding_checks') }}</h5>
                        @if($reconciliation->status != 'completed')
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-light" data-toggle="modal" data-target="#addItemModal" data-type="outstanding_check">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('date') }}</th>
                                    <th>{{ __('reference') }}</th>
                                    <th class="text-right">{{ __('amount') }}</th>
                                    @if($reconciliation->status != 'completed')
                                    <th width="10%"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalOutstandingChecks = 0; @endphp
                                @forelse($reconciliation->items->where('item_type', 'outstanding_check')->where('is_cleared', false) as $item)
                                <tr class="reconciliation-item">
                                    <td>{{ \Carbon\Carbon::parse($item->transaction_date)->format('d/m/Y') }}</td>
                                    <td>{{ $item->reference }}</td>
                                    <td class="text-right">{{ number_format($item->amount, 0, ',', ' ') }}</td>
                                    @if($reconciliation->status != 'completed')
                                    <td>
                                        <form action="{{ route('admin.bank-reconciliation.clear-item', [$reconciliation->id, $item->id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success" title="{{ __('clear') }}">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                                @php $totalOutstandingChecks += $item->amount; @endphp
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">{{ __('no_outstanding_checks') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-warning">
                                    <th colspan="2">{{ __('total') }}</th>
                                    <th class="text-right">{{ number_format($totalOutstandingChecks, 0, ',', ' ') }} FCFA</th>
                                    @if($reconciliation->status != 'completed')<th></th>@endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Deposits in Transit -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info">
                        <h5 class="card-title mb-0"><i class="fas fa-piggy-bank"></i> {{ __('deposits_in_transit') }}</h5>
                        @if($reconciliation->status != 'completed')
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-light" data-toggle="modal" data-target="#addItemModal" data-type="deposit_in_transit">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('date') }}</th>
                                    <th>{{ __('reference') }}</th>
                                    <th class="text-right">{{ __('amount') }}</th>
                                    @if($reconciliation->status != 'completed')
                                    <th width="10%"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalDepositsInTransit = 0; @endphp
                                @forelse($reconciliation->items->where('item_type', 'deposit_in_transit')->where('is_cleared', false) as $item)
                                <tr class="reconciliation-item">
                                    <td>{{ \Carbon\Carbon::parse($item->transaction_date)->format('d/m/Y') }}</td>
                                    <td>{{ $item->reference }}</td>
                                    <td class="text-right">{{ number_format($item->amount, 0, ',', ' ') }}</td>
                                    @if($reconciliation->status != 'completed')
                                    <td>
                                        <form action="{{ route('admin.bank-reconciliation.clear-item', [$reconciliation->id, $item->id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success" title="{{ __('clear') }}">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                                @php $totalDepositsInTransit += $item->amount; @endphp
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">{{ __('no_deposits_in_transit') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <th colspan="2">{{ __('total') }}</th>
                                    <th class="text-right">{{ number_format($totalDepositsInTransit, 0, ',', ' ') }} FCFA</th>
                                    @if($reconciliation->status != 'completed')<th></th>@endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bank Adjustments -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-secondary">
                        <h5 class="card-title mb-0"><i class="fas fa-sliders-h"></i> {{ __('adjustments') }}</h5>
                        @if($reconciliation->status != 'completed')
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-light" data-toggle="modal" data-target="#addAdjustmentModal">
                                <i class="fas fa-plus"></i> {{ __('add_adjustment') }}
                            </button>
                        </div>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('type') }}</th>
                                    <th>{{ __('description') }}</th>
                                    <th class="text-right">{{ __('debit') }}</th>
                                    <th class="text-right">{{ __('credit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalAdjustmentDebits = 0; $totalAdjustmentCredits = 0; @endphp
                                @forelse($reconciliation->items->whereIn('item_type', ['bank_charge', 'interest_income', 'nsf_check', 'adjustment']) as $item)
                                <tr>
                                    <td>{{ __($item->item_type) }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-right">{{ $item->amount < 0 ? number_format(abs($item->amount), 0, ',', ' ') : '-' }}</td>
                                    <td class="text-right">{{ $item->amount > 0 ? number_format($item->amount, 0, ',', ' ') : '-' }}</td>
                                </tr>
                                @php 
                                    if ($item->amount > 0) $totalAdjustmentCredits += $item->amount;
                                    else $totalAdjustmentDebits += abs($item->amount);
                                @endphp
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">{{ __('no_adjustments') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <th colspan="2">{{ __('total_adjustments') }}</th>
                                    <th class="text-right">{{ number_format($totalAdjustmentDebits, 0, ',', ' ') }}</th>
                                    <th class="text-right">{{ number_format($totalAdjustmentCredits, 0, ',', ' ') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reconciliation Summary -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary">
                        <h5 class="card-title mb-0"><i class="fas fa-calculator"></i> {{ __('reconciliation_summary') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>{{ __('bank_statement_reconciliation') }}</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>{{ __('statement_ending_balance') }}</td>
                                        <td class="text-right">{{ number_format($reconciliation->statement_ending_balance, 0, ',', ' ') }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ __('add') }}: {{ __('deposits_in_transit') }}</td>
                                        <td class="text-right text-success">+ {{ number_format($totalDepositsInTransit, 0, ',', ' ') }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ __('less') }}: {{ __('outstanding_checks') }}</td>
                                        <td class="text-right text-danger">- {{ number_format($totalOutstandingChecks, 0, ',', ' ') }}</td>
                                    </tr>
                                    <tr class="table-primary font-weight-bold">
                                        <td>{{ __('adjusted_bank_balance') }}</td>
                                        <td class="text-right">{{ number_format($reconciliation->statement_ending_balance + $totalDepositsInTransit - $totalOutstandingChecks, 0, ',', ' ') }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>{{ __('book_balance_reconciliation') }}</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>{{ __('book_balance') }}</td>
                                        <td class="text-right">{{ number_format($reconciliation->book_balance, 0, ',', ' ') }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ __('add') }}: {{ __('credits') }}</td>
                                        <td class="text-right text-success">+ {{ number_format($totalAdjustmentCredits, 0, ',', ' ') }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ __('less') }}: {{ __('debits') }}</td>
                                        <td class="text-right text-danger">- {{ number_format($totalAdjustmentDebits, 0, ',', ' ') }}</td>
                                    </tr>
                                    <tr class="table-primary font-weight-bold">
                                        <td>{{ __('adjusted_book_balance') }}</td>
                                        <td class="text-right">{{ number_format($reconciliation->adjusted_book_balance, 0, ',', ' ') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->

<!-- Add Item Modal -->
@if($reconciliation->status != 'completed')
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.bank-reconciliation.update', $reconciliation->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="action" value="add_item">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('add_reconciliation_item') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ __('type') }} <span class="text-danger">*</span></label>
                        <select name="item_type" class="form-control" id="itemType" required>
                            <option value="outstanding_check">{{ __('outstanding_check') }}</option>
                            <option value="deposit_in_transit">{{ __('deposit_in_transit') }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="transaction_date" class="form-control" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>{{ __('reference') }}</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>{{ __('amount') }} (FCFA) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required min="0" step="1">
                    </div>
                    <div class="form-group">
                        <label>{{ __('description') }}</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('add') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Adjustment Modal -->
<div class="modal fade" id="addAdjustmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.bank-reconciliation.update', $reconciliation->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="action" value="add_adjustment">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('add_adjustment') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ __('adjustment_type') }} <span class="text-danger">*</span></label>
                        <select name="item_type" class="form-control" required>
                            <option value="bank_charge">{{ __('bank_charge') }}</option>
                            <option value="interest_income">{{ __('interest_income') }}</option>
                            <option value="nsf_check">{{ __('nsf_check') }}</option>
                            <option value="adjustment">{{ __('other_adjustment') }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('description') }} <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>{{ __('amount') }} (FCFA) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required step="1">
                        <small class="text-muted">{{ __('positive_for_credit_negative_for_debit') }}</small>
                    </div>
                    <div class="form-group">
                        <label>{{ __('account') }}</label>
                        <select name="account_id" class="form-control">
                            <option value="">{{ __('select_account') }}</option>
                            @foreach($accounts ?? [] as $account)
                            <option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('add') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Set item type when modal is opened
        $('#addItemModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var type = button.data('type');
            if (type) {
                $('#itemType').val(type);
            }
        });
    });
</script>
@endpush
