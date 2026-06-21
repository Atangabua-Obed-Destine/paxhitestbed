@extends('admin.layouts.master')
@section('title', __('edit_fixed_asset'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('edit_fixed_asset') }}: {{ $asset->name }}</h3>
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

                    <form action="{{ route('admin.fixed-assets.update', $asset->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            <div class="row">
                                <!-- Asset Code (Read-only) -->
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ __('asset_code') }}</label>
                                        <input type="text" class="form-control" value="{{ $asset->asset_code }}" readonly>
                                    </div>
                                </div>

                                <!-- Basic Information -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name">{{ __('asset_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               id="name" name="name" value="{{ old('name', $asset->name) }}" required>
                                        @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="name_fr">{{ __('asset_name_fr') }}</label>
                                        <input type="text" class="form-control @error('name_fr') is-invalid @enderror" 
                                               id="name_fr" name="name_fr" value="{{ old('name_fr', $asset->name_fr) }}">
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
                                            <option value="{{ $category->id }}" {{ old('category_id', $asset->category_id) == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
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
                                               id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}">
                                        @error('serial_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="location">{{ __('location') }}</label>
                                        <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                               id="location" name="location" value="{{ old('location', $asset->location) }}">
                                        @error('location')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Financial Information (Some readonly) -->
                                <div class="col-md-12">
                                    <hr>
                                    <h5>{{ __('financial_information') }}</h5>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ __('acquisition_date') }}</label>
                                        <input type="text" class="form-control" value="{{ \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ __('acquisition_cost') }} (FCFA)</label>
                                        <input type="text" class="form-control" value="{{ number_format($asset->acquisition_cost, 0, ',', ' ') }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ __('accumulated_depreciation') }} (FCFA)</label>
                                        <input type="text" class="form-control" value="{{ number_format($asset->accumulated_depreciation, 0, ',', ' ') }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{ __('net_book_value') }} (FCFA)</label>
                                        <input type="text" class="form-control font-weight-bold" value="{{ number_format($asset->net_book_value, 0, ',', ' ') }}" readonly>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="salvage_value">{{ __('salvage_value') }} (FCFA)</label>
                                        <input type="number" class="form-control @error('salvage_value') is-invalid @enderror" 
                                               id="salvage_value" name="salvage_value" value="{{ old('salvage_value', $asset->salvage_value) }}" min="0" step="1">
                                        @error('salvage_value')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="vendor">{{ __('vendor') }}</label>
                                        <input type="text" class="form-control @error('vendor') is-invalid @enderror" 
                                               id="vendor" name="vendor" value="{{ old('vendor', $asset->vendor) }}">
                                        @error('vendor')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{ __('depreciation_method') }}</label>
                                        <input type="text" class="form-control" value="{{ __(str_replace('_', ' ', $asset->depreciation_method)) }}" readonly>
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
                                               id="warranty_expiry_date" name="warranty_expiry_date" value="{{ old('warranty_expiry_date', $asset->warranty_expiry_date) }}">
                                        @error('warranty_expiry_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="insurance_value">{{ __('insurance_value') }} (FCFA)</label>
                                        <input type="number" class="form-control @error('insurance_value') is-invalid @enderror" 
                                               id="insurance_value" name="insurance_value" value="{{ old('insurance_value', $asset->insurance_value) }}" min="0" step="1">
                                        @error('insurance_value')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="insurance_policy_number">{{ __('insurance_policy_number') }}</label>
                                        <input type="text" class="form-control @error('insurance_policy_number') is-invalid @enderror" 
                                               id="insurance_policy_number" name="insurance_policy_number" value="{{ old('insurance_policy_number', $asset->insurance_policy_number) }}">
                                        @error('insurance_policy_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('description') }}</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" name="description" rows="3">{{ old('description', $asset->description) }}</textarea>
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
