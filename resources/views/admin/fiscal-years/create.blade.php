@extends('admin.layouts.master')
@section('title', __('add_fiscal_year'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('add_fiscal_year') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.fiscal-years.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.fiscal-years.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <!-- Fiscal Year Name -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="name">{{ __('fiscal_year_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               id="name" name="name" value="{{ old('name') }}" 
                                               placeholder="{{ __('example') }}: 2024-2025" required>
                                        @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                        <small class="form-text text-muted">{{ __('enter_fiscal_year_name_example') }}</small>
                                    </div>
                                </div>

                                <!-- Start Date -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date">{{ __('start_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                               id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                                        @error('start_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- End Date -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date">{{ __('end_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                               id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                                        @error('end_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Is Active -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="is_active">{{ __('set_as_active_year') }}</label>
                                        <select class="form-control @error('is_active') is-invalid @enderror" 
                                                id="is_active" name="is_active">
                                            <option value="0" {{ old('is_active', 0) == 0 ? 'selected' : '' }}>{{ __('no') }}</option>
                                            <option value="1" {{ old('is_active') == 1 ? 'selected' : '' }}>{{ __('yes') }}</option>
                                        </select>
                                        @error('is_active')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                        <small class="form-text text-muted">{{ __('only_one_fiscal_year_can_be_active') }}</small>
                                    </div>
                                </div>

                                <!-- Generate Periods -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="generate_periods">{{ __('generate_monthly_periods') }}</label>
                                        <select class="form-control @error('generate_periods') is-invalid @enderror" 
                                                id="generate_periods" name="generate_periods">
                                            <option value="1" {{ old('generate_periods', 1) == 1 ? 'selected' : '' }}>{{ __('yes') }}</option>
                                            <option value="0" {{ old('generate_periods') == 0 ? 'selected' : '' }}>{{ __('no') }}</option>
                                        </select>
                                        @error('generate_periods')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror>
                                        <small class="form-text text-muted">{{ __('automatically_create_12_monthly_periods') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('save') }}
                            </button>
                            <a href="{{ route('admin.fiscal-years.index') }}" class="btn btn-secondary">
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
        // Auto-calculate end date (12 months from start date)
        $('#start_date').change(function() {
            var startDate = new Date($(this).val());
            if(startDate) {
                var endDate = new Date(startDate);
                endDate.setFullYear(endDate.getFullYear() + 1);
                endDate.setDate(endDate.getDate() - 1); // Last day of 12th month
                
                var year = endDate.getFullYear();
                var month = String(endDate.getMonth() + 1).padStart(2, '0');
                var day = String(endDate.getDate()).padStart(2, '0');
                
                $('#end_date').val(year + '-' + month + '-' + day);
                
                // Auto-generate name
                var startYear = startDate.getFullYear();
                var endYear = endDate.getFullYear();
                $('#name').val(startYear + '-' + endYear);
            }
        });
    });
</script>
@endpush
