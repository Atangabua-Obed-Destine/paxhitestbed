@extends('admin.layouts.master')
@section('title', __('btn_create') . ' ' . $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('btn_create') }} {{ $title }}</h5>
                        </div>
                        <div class="card-block">
                            <a href="{{ route($route.'.index') }}" class="btn btn-primary mb-3">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" placeholder="e.g., Personal Income Tax, Social Security" required>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="code" class="form-label">{{ __('field_code') }}</label>
                                        <input type="text" class="form-control" name="code" id="code" value="{{ old('code') }}" placeholder="e.g., PIT, SS, VAT">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description" class="form-label">{{ __('field_description') }}</label>
                                <textarea class="form-control" name="description" id="description" rows="2">{{ old('description') }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="is_progressive" class="form-label">{{ __('field_calculation_type') }} <span>*</span></label>
                                        <select class="form-control" name="is_progressive" id="is_progressive" required>
                                            <option value="1" {{ old('is_progressive', 1) == 1 ? 'selected' : '' }}>{{ __('progressive_tax') }}</option>
                                            <option value="0" {{ old('is_progressive') == 0 ? 'selected' : '' }}>{{ __('flat_rate_tax') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ __('calculation_type_help') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="display_order" class="form-label">{{ __('field_display_order') }}</label>
                                        <input type="number" class="form-control" name="display_order" id="display_order" value="{{ old('display_order') }}" min="0" placeholder="{{ __('auto_assign') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="effective_from" class="form-label">{{ __('field_effective_from') }}</label>
                                        <input type="date" class="form-control" name="effective_from" id="effective_from" value="{{ old('effective_from') }}">
                                        <small class="form-text text-muted">{{ __('effective_from_help') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="effective_to" class="form-label">{{ __('field_effective_to') }}</label>
                                        <input type="date" class="form-control" name="effective_to" id="effective_to" value="{{ old('effective_to') }}">
                                        <small class="form-text text-muted">{{ __('effective_to_help') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>{{ __('progressive_tax') }}:</strong> {{ __('progressive_tax_explanation') }}<br>
                                <strong>{{ __('flat_rate_tax') }}:</strong> {{ __('flat_rate_tax_explanation') }}
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> {{ __('btn_save') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
