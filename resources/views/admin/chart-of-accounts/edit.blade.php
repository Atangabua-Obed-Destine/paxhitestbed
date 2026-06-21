@extends('admin.layouts.master')
@section('title', __('edit_account'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('edit_account') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.chart-of-accounts.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.chart-of-accounts.update', $account->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            @if($account->is_system)
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> {{ __('this_is_a_system_account') }}. {{ __('some_fields_cannot_be_modified') }}.
                            </div>
                            @endif

                            <div class="row">
                                <!-- Account Code -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_code">{{ __('account_code') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('account_code') is-invalid @enderror" 
                                               id="account_code" name="account_code" value="{{ old('account_code', $account->account_code) }}" 
                                               {{ $account->is_system ? 'readonly' : '' }} required>
                                        @error('account_code')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Class Number -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="class_number">{{ __('classe') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('class_number') is-invalid @enderror" 
                                                id="class_number" name="class_number" {{ $account->is_system ? 'disabled' : '' }} required>
                                            @for($i = 1; $i <= 9; $i++)
                                            <option value="{{ $i }}" {{ old('class_number', $account->class_number) == $i ? 'selected' : '' }}>
                                                {{ __('classe') }} {{ $i }} - {{ __('ohada_class_'.$i.'_name') }}
                                            </option>
                                            @endfor
                                        </select>
                                        @if($account->is_system)
                                        <input type="hidden" name="class_number" value="{{ $account->class_number }}">
                                        @endif
                                        @error('class_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Account Name (English) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_name">{{ __('account_name') }} (English) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('account_name') is-invalid @enderror" 
                                               id="account_name" name="account_name" value="{{ old('account_name', $account->account_name) }}" required>
                                        @error('account_name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Account Name (French) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_name_fr">{{ __('account_name') }} (Français)</label>
                                        <input type="text" class="form-control @error('account_name_fr') is-invalid @enderror" 
                                               id="account_name_fr" name="account_name_fr" value="{{ old('account_name_fr', $account->account_name_fr) }}">
                                        @error('account_name_fr')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Parent Account -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="parent_id">{{ __('parent_account') }}</label>
                                        <select class="form-control select2 @error('parent_id') is-invalid @enderror" 
                                                id="parent_id" name="parent_id" {{ $account->is_system ? 'disabled' : '' }}>
                                            <option value="">{{ __('select_parent_account') }} ({{ __('optional') }})</option>
                                            @foreach($parentAccounts as $parent)
                                            @if($parent->id != $account->id)
                                            <option value="{{ $parent->id }}" {{ old('parent_id', $account->parent_id) == $parent->id ? 'selected' : '' }}>
                                                {{ $parent->account_code }} - {{ $parent->account_name }}
                                            </option>
                                            @endif
                                            @endforeach
                                        </select>
                                        @if($account->is_system)
                                        <input type="hidden" name="parent_id" value="{{ $account->parent_id }}">
                                        @endif
                                        @error('parent_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Account Type -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_type">{{ __('account_type') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('account_type') is-invalid @enderror" 
                                                id="account_type" name="account_type" {{ $account->is_system ? 'disabled' : '' }} required>
                                            <option value="asset" {{ old('account_type', $account->account_type) == 'asset' ? 'selected' : '' }}>{{ __('asset') }}</option>
                                            <option value="liability" {{ old('account_type', $account->account_type) == 'liability' ? 'selected' : '' }}>{{ __('liability') }}</option>
                                            <option value="equity" {{ old('account_type', $account->account_type) == 'equity' ? 'selected' : '' }}>{{ __('equity') }}</option>
                                            <option value="revenue" {{ old('account_type', $account->account_type) == 'revenue' ? 'selected' : '' }}>{{ __('revenue') }}</option>
                                            <option value="expense" {{ old('account_type', $account->account_type) == 'expense' ? 'selected' : '' }}>{{ __('expense') }}</option>
                                            <option value="other" {{ old('account_type', $account->account_type) == 'other' ? 'selected' : '' }}>{{ __('other') }}</option>
                                        </select>
                                        @if($account->is_system)
                                        <input type="hidden" name="account_type" value="{{ $account->account_type }}">
                                        @endif
                                        @error('account_type')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Account Category -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_category">{{ __('account_category') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('account_category') is-invalid @enderror" 
                                                id="account_category" name="account_category" {{ $account->is_system ? 'disabled' : '' }} required>
                                            <option value="detail" {{ old('account_category', $account->account_category) == 'detail' ? 'selected' : '' }}>{{ __('detail_account') }}</option>
                                            <option value="heading" {{ old('account_category', $account->account_category) == 'heading' ? 'selected' : '' }}>{{ __('heading_account') }}</option>
                                            <option value="total" {{ old('account_category', $account->account_category) == 'total' ? 'selected' : '' }}>{{ __('total_account') }}</option>
                                            <option value="subtotal" {{ old('account_category', $account->account_category) == 'subtotal' ? 'selected' : '' }}>{{ __('subtotal_account') }}</option>
                                        </select>
                                        @if($account->is_system)
                                        <input type="hidden" name="account_category" value="{{ $account->account_category }}">
                                        @endif
                                        @error('account_category')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Normal Balance -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="normal_balance">{{ __('normal_balance') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('normal_balance') is-invalid @enderror" 
                                                id="normal_balance" name="normal_balance" {{ $account->is_system ? 'disabled' : '' }} required>
                                            <option value="debit" {{ old('normal_balance', $account->normal_balance) == 'debit' ? 'selected' : '' }}>{{ __('debit') }}</option>
                                            <option value="credit" {{ old('normal_balance', $account->normal_balance) == 'credit' ? 'selected' : '' }}>{{ __('credit') }}</option>
                                        </select>
                                        @if($account->is_system)
                                        <input type="hidden" name="normal_balance" value="{{ $account->normal_balance }}">
                                        @endif
                                        @error('normal_balance')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Opening Balance -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="opening_balance">{{ __('opening_balance') }} (FCFA)</label>
                                        <input type="number" step="0.01" class="form-control @error('opening_balance') is-invalid @enderror" 
                                               id="opening_balance" name="opening_balance" value="{{ old('opening_balance', $account->opening_balance) }}">
                                        @error('opening_balance')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Display Order -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="display_order">{{ __('display_order') }}</label>
                                        <input type="number" class="form-control @error('display_order') is-invalid @enderror" 
                                               id="display_order" name="display_order" value="{{ old('display_order', $account->display_order) }}">
                                        @error('display_order')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="is_active">{{ __('status_title') }}</label>
                                        <select class="form-control @error('is_active') is-invalid @enderror" 
                                                id="is_active" name="is_active">
                                            <option value="1" {{ old('is_active', $account->is_active) == 1 ? 'selected' : '' }}>{{ __('active') }}</option>
                                            <option value="0" {{ old('is_active', $account->is_active) == 0 ? 'selected' : '' }}>{{ __('inactive') }}</option>
                                        </select>
                                        @error('is_active')
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
                            <a href="{{ route('admin.chart-of-accounts.index') }}" class="btn btn-secondary">
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
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: '{{ __("select_parent_account") }}'
        });
    });
</script>
@endpush
