@extends('admin.layouts.master')
@section('title', __('edit_asset_category'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('edit_asset_category') }}: {{ $category->name }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.fixed-asset-categories.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    
                    @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible mx-3 mt-3">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('admin.fixed-asset-categories.update', $category->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="code">{{ __('category_code') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                               id="code" name="code" value="{{ old('code', $category->code) }}" required maxlength="10">
                                        @error('code')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name">{{ __('category_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               id="name" name="name" value="{{ old('name', $category->name) }}" required>
                                        @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name_fr">{{ __('category_name_fr') }}</label>
                                        <input type="text" class="form-control @error('name_fr') is-invalid @enderror" 
                                               id="name_fr" name="name_fr" value="{{ old('name_fr', $category->name_fr) }}">
                                        @error('name_fr')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="depreciation_method">{{ __('depreciation_method') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('depreciation_method') is-invalid @enderror" 
                                                id="depreciation_method" name="depreciation_method" required>
                                            <option value="straight_line" {{ old('depreciation_method', $category->depreciation_method) == 'straight_line' ? 'selected' : '' }}>{{ __('straight_line') }}</option>
                                            <option value="declining_balance" {{ old('depreciation_method', $category->depreciation_method) == 'declining_balance' ? 'selected' : '' }}>{{ __('declining_balance') }}</option>
                                            <option value="sum_of_years" {{ old('depreciation_method', $category->depreciation_method) == 'sum_of_years' ? 'selected' : '' }}>{{ __('sum_of_years') }}</option>
                                            <option value="units_of_production" {{ old('depreciation_method', $category->depreciation_method) == 'units_of_production' ? 'selected' : '' }}>{{ __('units_of_production') }}</option>
                                        </select>
                                        @error('depreciation_method')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="default_useful_life">{{ __('default_useful_life') }} ({{ __('years') }}) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control @error('default_useful_life') is-invalid @enderror" 
                                               id="default_useful_life" name="default_useful_life" value="{{ old('default_useful_life', $category->default_useful_life) }}" required min="1" max="100">
                                        @error('default_useful_life')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <hr>
                                    <h5>{{ __('linked_gl_accounts') }}</h5>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="asset_account_id">{{ __('asset_account') }} <span class="text-danger">*</span></label>
                                        <select class="form-control select2 @error('asset_account_id') is-invalid @enderror" 
                                                id="asset_account_id" name="asset_account_id" required>
                                            <option value="">{{ __('select_account') }}</option>
                                            @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" {{ old('asset_account_id', $category->asset_account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->account_code }} - {{ $account->account_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('asset_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="depreciation_account_id">{{ __('accumulated_depreciation_account') }} <span class="text-danger">*</span></label>
                                        <select class="form-control select2 @error('depreciation_account_id') is-invalid @enderror" 
                                                id="depreciation_account_id" name="depreciation_account_id" required>
                                            <option value="">{{ __('select_account') }}</option>
                                            @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" {{ old('depreciation_account_id', $category->depreciation_account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->account_code }} - {{ $account->account_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('depreciation_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="expense_account_id">{{ __('depreciation_expense_account') }} <span class="text-danger">*</span></label>
                                        <select class="form-control select2 @error('expense_account_id') is-invalid @enderror" 
                                                id="expense_account_id" name="expense_account_id" required>
                                            <option value="">{{ __('select_account') }}</option>
                                            @foreach($expenseAccounts as $account)
                                            <option value="{{ $account->id }}" {{ old('expense_account_id', $category->expense_account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->account_code }} - {{ $account->account_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('expense_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="disposal_account_id">{{ __('disposal_gain_loss_account') }}</label>
                                        <select class="form-control select2 @error('disposal_account_id') is-invalid @enderror" 
                                                id="disposal_account_id" name="disposal_account_id">
                                            <option value="">{{ __('select_account') }}</option>
                                            @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" {{ old('disposal_account_id', $category->disposal_account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->account_code }} - {{ $account->account_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('disposal_account_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }}</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="2">{{ old('description', $category->description) }}</textarea>
                                        @error('description')
                                        <span class="invalid-feedback">{{ $message }}</span>
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
                            <a href="{{ route('admin.fixed-asset-categories.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> {{ __('cancel') }}
                            </a>
                        </div>
                    </form>
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

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
    });
</script>
@endpush
