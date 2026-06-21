@extends('admin.layouts.master')
@section('title', __('edit_transaction'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card {{ $transaction->transaction_type == 'credit' ? 'card-success' : 'card-warning' }}">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('edit_transaction') }}: {{ $transaction->paymentAccount->title }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.payment-account.account-book', $transaction->payment_account_id) }}" 
                               class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.payment-account.transaction.update', $transaction->id) }}" 
                          method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>{{ __('current_balance') }}:</strong> {{ number_format($transaction->paymentAccount->current_balance, 2) }}<br>
                                <strong>{{ __('transaction_type') }}:</strong> 
                                <span class="badge badge-{{ $transaction->transaction_type == 'credit' ? 'success' : 'danger' }}">
                                    {{ __($transaction->transaction_type) }}
                                </span>
                                <hr>
                                <small><i class="fas fa-info-circle"></i> {{ __('edit_transaction_info') }}</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">{{ __('amount') }} <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" 
                                               class="form-control @error('amount') is-invalid @enderror" 
                                               id="amount" name="amount" value="{{ old('amount', $transaction->amount) }}" required>
                                        @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_date">{{ __('transaction_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('transaction_date') is-invalid @enderror" 
                                               id="transaction_date" name="transaction_date" 
                                               value="{{ old('transaction_date', $transaction->transaction_date->format('Y-m-d')) }}" required>
                                        @error('transaction_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="title">{{ __('title') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                               id="title" name="title" value="{{ old('title', $transaction->title) }}" required>
                                        @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="payment_method">{{ __('payment_method') }}</label>
                                        <input type="text" class="form-control @error('payment_method') is-invalid @enderror" 
                                               id="payment_method" name="payment_method" 
                                               value="{{ old('payment_method', $transaction->payment_method) }}"
                                               placeholder="{{ __('e.g., Cash, Bank Transfer, Cheque') }}">
                                        @error('payment_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }}</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="3">{{ old('description', $transaction->description) }}</textarea>
                                        @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="attach">{{ __('attachment') }}</label>
                                        @if($transaction->attach)
                                        <div class="mb-2">
                                            <a href="{{ asset($transaction->attach) }}" target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-paperclip"></i> {{ __('view_current_attachment') }}
                                            </a>
                                        </div>
                                        @endif
                                        <input type="file" class="form-control-file @error('attach') is-invalid @enderror" 
                                               id="attach" name="attach" accept=".jpg,.jpeg,.png,.pdf">
                                        <small class="text-muted">{{ __('allowed_files') }}: JPG, PNG, PDF (Max 2MB)</small>
                                        @error('attach')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('update_transaction') }}
                            </button>
                            <a href="{{ route('admin.payment-account.account-book', $transaction->payment_account_id) }}" 
                               class="btn btn-secondary">
                                {{ __('cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
@endsection
