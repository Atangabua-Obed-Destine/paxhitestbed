@extends('admin.layouts.master')
@section('title', __('edit_fiscal_year'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('edit_fiscal_year') }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.fiscal-years.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form action="{{ route('admin.fiscal-years.update', $fiscalYear->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            @if($fiscalYear->is_closed)
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> {{ __('this_fiscal_year_is_closed') }}. {{ __('reopen_to_make_changes') }}.
                            </div>
                            @endif

                            <div class="row">
                                <!-- Fiscal Year Name -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="name">{{ __('fiscal_year_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               id="name" name="name" value="{{ old('name', $fiscalYear->name) }}" 
                                               {{ $fiscalYear->is_closed ? 'readonly' : '' }} required>
                                        @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Start Date -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date">{{ __('start_date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                               id="start_date" name="start_date" value="{{ old('start_date', $fiscalYear->start_date->format('Y-m-d')) }}" 
                                               {{ $fiscalYear->is_closed ? 'readonly' : '' }} required>
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
                                               id="end_date" name="end_date" value="{{ old('end_date', $fiscalYear->end_date->format('Y-m-d')) }}" 
                                               {{ $fiscalYear->is_closed ? 'readonly' : '' }} required>
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
                                                id="is_active" name="is_active" {{ $fiscalYear->is_closed ? 'disabled' : '' }}>
                                            <option value="0" {{ old('is_active', $fiscalYear->is_active) == 0 ? 'selected' : '' }}>{{ __('no') }}</option>
                                            <option value="1" {{ old('is_active', $fiscalYear->is_active) == 1 ? 'selected' : '' }}>{{ __('yes') }}</option>
                                        </select>
                                        @if($fiscalYear->is_closed)
                                        <input type="hidden" name="is_active" value="{{ $fiscalYear->is_active }}">
                                        @endif
                                        @error('is_active')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            @if(!$fiscalYear->is_closed)
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ __('update') }}
                            </button>
                            @endif
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
