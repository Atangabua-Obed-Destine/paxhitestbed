@extends('admin.layouts.master')
@section('title', __('add_payment_account'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('add_payment_account') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.payment-account.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.payment-account.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="title">{{ __('account_title') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                               id="title" name="title" value="{{ old('title') }}" required>
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
                                            <option value="{{ $type->id }}" {{ old('account_type_id') == $type->id ? 'selected' : '' }}>
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
                                               id="account_number" name="account_number" value="{{ old('account_number') }}">
                                        @error('account_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="opening_balance">{{ __('opening_balance') }} <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control @error('opening_balance') is-invalid @enderror" 
                                               id="opening_balance" name="opening_balance" value="{{ old('opening_balance', 0) }}" required>
                                        @error('opening_balance')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status">{{ __('status_title') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                                            <option value="1" {{ old('status', 1) == 1 ? 'selected' : '' }}>{{ __('active') }}</option>
                                            <option value="0" {{ old('status') == 0 ? 'selected' : '' }}>{{ __('inactive') }}</option>
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
                                                  id="description" name="description" rows="3">{{ old('description') }}</textarea>
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
                                <i class="fas fa-save"></i> {{ __('save') }}
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
