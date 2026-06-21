@extends('admin.layouts.master')
@section('title', __('add_fixed_asset'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('add_fixed_asset') }} ({{ __('nouvelle_immobilisation') }})</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.fixed-assets.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    
                    @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible mx-3 mt-3">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <h5><i class="icon fas fa-ban"></i> {{ __('validation_errors') }}</h5>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('admin.fixed-assets.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">{{ __('asset_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               id="name" name="name" value="{{ old('name') }}" required>
                                        @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name_fr">{{ __('asset_name_fr') }}</label>
                                        <input type="text" class="form-control @error('name_fr') is-invalid @enderror" 
                                               id="name_fr" name="name_fr" value="{{ old('name_fr') }}">
                                        @error('name_fr')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="category_id">{{ __('category') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('category_id') is-invalid @enderror" 
                                                id="category_id" name="category_id" required>
                                            <option value="">{{ __('select_category') }}</option>
                                            @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}
                                                    data-useful-life="{{ $category->default_useful_life }}"
                                                    data-depreciation-method="{{ $category->depreciation_method }}">
                                                {{ $category->name }} ({{ $category->default_useful_life }} {{ __('years') }})
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('category_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="serial_number">{{ __('serial_number') }}</label>
                                        <input type="text" class="form-control @error('serial_number') is-invalid @enderror" 
                                               id="serial_number" name="serial_number" value="{{ old('serial_number') }}">
                                        @error('serial_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="location">{{ __('location') }}</label>
                                        <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                               id="location" name="location" value="{{ old('location') }}">
                                        @error('location')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Financial Information -->
                                <div class="col-md-12">
                                    <hr>
                                    <h5>{{ __('financial_information') }}</h5>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="acquisition_date">{{ __('acquisition_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('acquisition_date') is-invalid @enderror" 
                                               id="acquisition_date" name="acquisition_date" value="{{ old('acquisition_date', date('Y-m-d')) }}" required>
                                        @error('acquisition_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="acquisition_cost">{{ __('acquisition_cost') }} (FCFA) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control @error('acquisition_cost') is-invalid @enderror" 
                                               id="acquisition_cost" name="acquisition_cost" value="{{ old('acquisition_cost') }}" required min="0" step="1">
                                        @error('acquisition_cost')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="salvage_value">{{ __('salvage_value') }} (FCFA)</label>
                                        <input type="number" class="form-control @error('salvage_value') is-invalid @enderror" 
                                               id="salvage_value" name="salvage_value" value="{{ old('salvage_value', 0) }}" min="0" step="1">
                                        @error('salvage_value')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="useful_life_years">{{ __('useful_life_years') }} <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control @error('useful_life_years') is-invalid @enderror" 
                                               id="useful_life_years" name="useful_life_years" value="{{ old('useful_life_years') }}" required min="1">
                                        @error('useful_life_years')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="depreciation_method">{{ __('depreciation_method') }} <span class="text-danger">*</span></label>
                                        <select class="form-control @error('depreciation_method') is-invalid @enderror" 
                                                id="depreciation_method" name="depreciation_method" required>
                                            <option value="straight_line" {{ old('depreciation_method') == 'straight_line' ? 'selected' : '' }}>{{ __('straight_line') }}</option>
                                            <option value="declining_balance" {{ old('depreciation_method') == 'declining_balance' ? 'selected' : '' }}>{{ __('declining_balance') }}</option>
                                            <option value="sum_of_years" {{ old('depreciation_method') == 'sum_of_years' ? 'selected' : '' }}>{{ __('sum_of_years') }}</option>
                                            <option value="units_of_production" {{ old('depreciation_method') == 'units_of_production' ? 'selected' : '' }}>{{ __('units_of_production') }}</option>
                                        </select>
                                        @error('depreciation_method')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="depreciation_start_date">{{ __('depreciation_start_date') }}</label>
                                        <input type="date" class="form-control @error('depreciation_start_date') is-invalid @enderror" 
                                               id="depreciation_start_date" name="depreciation_start_date" value="{{ old('depreciation_start_date') }}">
                                        <small class="text-muted">{{ __('leave_blank_to_start_from_acquisition') }}</small>
                                        @error('depreciation_start_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="vendor">{{ __('vendor') }}</label>
                                        <input type="text" class="form-control @error('vendor') is-invalid @enderror" 
                                               id="vendor" name="vendor" value="{{ old('vendor') }}">
                                        @error('vendor')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Additional Information -->
                                <div class="col-md-12">
                                    <hr>
                                    <h5>{{ __('additional_information') }}</h5>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="warranty_expiry_date">{{ __('warranty_expiry_date') }}</label>
                                        <input type="date" class="form-control @error('warranty_expiry_date') is-invalid @enderror" 
                                               id="warranty_expiry_date" name="warranty_expiry_date" value="{{ old('warranty_expiry_date') }}">
                                        @error('warranty_expiry_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="insurance_value">{{ __('insurance_value') }} (FCFA)</label>
                                        <input type="number" class="form-control @error('insurance_value') is-invalid @enderror" 
                                               id="insurance_value" name="insurance_value" value="{{ old('insurance_value') }}" min="0" step="1">
                                        @error('insurance_value')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="insurance_policy_number">{{ __('insurance_policy_number') }}</label>
                                        <input type="text" class="form-control @error('insurance_policy_number') is-invalid @enderror" 
                                               id="insurance_policy_number" name="insurance_policy_number" value="{{ old('insurance_policy_number') }}">
                                        @error('insurance_policy_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }}</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="3">{{ old('description') }}</textarea>
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
                                <i class="fas fa-save"></i> {{ __('save') }}
                            </button>
                            <a href="{{ route('admin.fixed-assets.index') }}" class="btn btn-secondary">
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
        // Auto-fill useful life and depreciation method when category changes
        $('#category_id').change(function() {
            var selectedOption = $(this).find(':selected');
            var usefulLife = selectedOption.data('useful-life');
            var depreciationMethod = selectedOption.data('depreciation-method');
            
            if (usefulLife) {
                $('#useful_life_years').val(usefulLife);
            }
            if (depreciationMethod) {
                $('#depreciation_method').val(depreciationMethod);
            }
        });
    });
</script>
@endpush
