@extends('admin.layouts.master')
@section('title', __('add_account'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('add_account') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.chart-of-accounts.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.chart-of-accounts.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <!-- Account Code -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_code">{{ __('account_code') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('account_code') is-invalid @enderror" 
                                               id="account_code" name="account_code" value="{{ old('account_code') }}" 
                                               placeholder="{{ __('enter_account_code') }}" required>
                                        @error('account_code')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                        <small class="form-text text-muted">{{ __('example') }}: 411001, 53001, etc.</small>
                                    </div>
                                </div>

                                <!-- Class Number -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="class_number">{{ __('classe') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('class_number') is-invalid @enderror" 
                                                id="class_number" name="class_number" required>
                                            <option value="">{{ __('select_class') }}</option>
                                            @for($i = 1; $i <= 9; $i++)
                                            <option value="{{ $i }}" {{ old('class_number') == $i ? 'selected' : '' }}>
                                                {{ __('classe') }} {{ $i }} - {{ __('ohada_class_'.$i.'_name') }}
                                            </option>
                                            @endfor
                                        </select>
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
                                               id="account_name" name="account_name" value="{{ old('account_name') }}" 
                                               placeholder="{{ __('enter_account_name') }}" required>
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
                                               id="account_name_fr" name="account_name_fr" value="{{ old('account_name_fr') }}" 
                                               placeholder="Nom du compte en français">
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
                                                id="parent_id" name="parent_id">
                                            <option value="">{{ __('select_parent_account') }} ({{ __('optional') }})</option>
                                            @foreach($parentAccounts as $parent)
                                            <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                                {{ $parent->account_code }} - {{ $parent->account_name }}
                                            </option>
                                            @endforeach
                                        </select>
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
                                                id="account_type" name="account_type" required>
                                            <option value="">{{ __('select_account_type') }}</option>
                                            <option value="asset" {{ old('account_type') == 'asset' ? 'selected' : '' }}>{{ __('asset') }}</option>
                                            <option value="liability" {{ old('account_type') == 'liability' ? 'selected' : '' }}>{{ __('liability') }}</option>
                                            <option value="equity" {{ old('account_type') == 'equity' ? 'selected' : '' }}>{{ __('equity') }}</option>
                                            <option value="revenue" {{ old('account_type') == 'revenue' ? 'selected' : '' }}>{{ __('revenue') }}</option>
                                            <option value="expense" {{ old('account_type') == 'expense' ? 'selected' : '' }}>{{ __('expense') }}</option>
                                            <option value="other" {{ old('account_type') == 'other' ? 'selected' : '' }}>{{ __('other') }}</option>
                                        </select>
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
                                                id="account_category" name="account_category" required>
                                            <option value="">{{ __('select_account_category') }}</option>
                                            <option value="detail" {{ old('account_category') == 'detail' ? 'selected' : '' }}>{{ __('detail_account') }}</option>
                                            <option value="heading" {{ old('account_category') == 'heading' ? 'selected' : '' }}>{{ __('heading_account') }}</option>
                                            <option value="total" {{ old('account_category') == 'total' ? 'selected' : '' }}>{{ __('total_account') }}</option>
                                            <option value="subtotal" {{ old('account_category') == 'subtotal' ? 'selected' : '' }}>{{ __('subtotal_account') }}</option>
                                        </select>
                                        @error('account_category')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                        <small class="form-text text-muted">{{ __('only_detail_accounts_can_post_transactions') }}</small>
                                    </div>
                                </div>

                                <!-- Normal Balance -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="normal_balance">{{ __('normal_balance') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('normal_balance') is-invalid @enderror" 
                                                id="normal_balance" name="normal_balance" required>
                                            <option value="">{{ __('select_normal_balance') }}</option>
                                            <option value="debit" {{ old('normal_balance') == 'debit' ? 'selected' : '' }}>{{ __('debit') }}</option>
                                            <option value="credit" {{ old('normal_balance') == 'credit' ? 'selected' : '' }}>{{ __('credit') }}</option>
                                        </select>
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
                                               id="opening_balance" name="opening_balance" value="{{ old('opening_balance', 0) }}" 
                                               placeholder="0.00">
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
                                               id="display_order" name="display_order" value="{{ old('display_order', 0) }}" 
                                               placeholder="0">
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
                                            <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>{{ __('active') }}</option>
                                            <option value="0" {{ old('is_active') == 0 ? 'selected' : '' }}>{{ __('inactive') }}</option>
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
                                <i class="fas fa-save"></i> {{ __('save') }}
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

        // Auto-suggest normal balance based on account type
        $('#account_type').change(function() {
            var accountType = $(this).val();
            var suggestedBalance = '';

            switch(accountType) {
                case 'asset':
                case 'expense':
                    suggestedBalance = 'debit';
                    break;
                case 'liability':
                case 'equity':
                case 'revenue':
                    suggestedBalance = 'credit';
                    break;
            }

            if(suggestedBalance) {
                $('#normal_balance').val(suggestedBalance);
            }
        });

        // Filter parent accounts by class number
        $('#class_number').change(function() {
            var selectedClass = $(this).val();
            if(selectedClass) {
                $.ajax({
                    url: '{{ route("admin.chart-of-accounts.by-class") }}',
                    type: 'GET',
                    data: { class_number: selectedClass },
                    success: function(response) {
                        var parentSelect = $('#parent_id');
                        parentSelect.empty();
                        parentSelect.append('<option value="">{{ __("select_parent_account") }} ({{ __("optional") }})</option>');
                        
                        response.forEach(function(account) {
                            parentSelect.append(
                                '<option value="' + account.id + '">' + 
                                account.account_code + ' - ' + account.account_name + 
                                '</option>'
                            );
                        });
                    }
                });
            }
        });
    });
</script>
@endpush
