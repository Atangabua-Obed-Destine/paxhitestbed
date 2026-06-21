@extends('admin.layouts.master')
@section('title', __('edit_payment_account'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('edit_payment_account') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.payment-account.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.payment-account.update', $payment_account->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="title">{{ __('account_title') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                               id="title" name="title" value="{{ old('title', $payment_account->title) }}" required>
                                        @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_type_id">{{ __('account_type') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('account_type_id') is-invalid @enderror" 
                                                id="account_type_id" name="account_type_id" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($account_types as $type)
                                            <option value="{{ $type->id }}" 
                                                {{ old('account_type_id', $payment_account->account_type_id) == $type->id ? 'selected' : '' }}>
                                                {{ $type->title }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('account_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_number">{{ __('account_number') }}</label>
                                        <input type="text" class="form-control @error('account_number') is-invalid @enderror" 
                                               id="account_number" name="account_number" 
                                               value="{{ old('account_number', $payment_account->account_number) }}">
                                        @error('account_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="opening_balance">{{ __('opening_balance') }}</label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="opening_balance" 
                                               value="{{ number_format($payment_account->opening_balance, 2) }}" 
                                               disabled>
                                        <small class="text-muted">{{ __('opening_balance_cannot_be_changed') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="current_balance">{{ __('current_balance') }}</label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="current_balance" 
                                               value="{{ number_format($payment_account->current_balance, 2) }}" 
                                               disabled>
                                        <small class="text-muted">{{ __('current_balance_updated_by_transactions') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="status">{{ __('status_title') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                                            <option value="1" {{ old('status', $payment_account->status) == 1 ? 'selected' : '' }}>
                                                {{ __('active') }}
                                            </option>
                                            <option value="0" {{ old('status', $payment_account->status) == 0 ? 'selected' : '' }}>
                                                {{ __('inactive') }}
                                            </option>
                                        </select>
                                        @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }}</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="3">{{ old('description', $payment_account->description) }}</textarea>
                                        @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('update') }}
                            </button>
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
