@extends('admin.layouts.master')
@section('title', __('btn_edit') . ' ' . $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('btn_edit') }} {{ $title }}</h5>
                        </div>
                        <div class="card-block">
                            <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-primary mb-3">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="title" id="title" value="{{ $row->title }}" required>
                                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="code" class="form-label">{{ __('field_code') }}</label>
                                        <input type="text" class="form-control" name="code" id="code" value="{{ $row->code }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description" class="form-label">{{ __('field_description') }}</label>
                                <textarea class="form-control" name="description" id="description" rows="2">{{ $row->description }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="is_progressive" class="form-label">{{ __('field_calculation_type') }} <span>*</span></label>
                                        <select class="form-control" name="is_progressive" id="is_progressive" required>
                                            <option value="1" {{ $row->is_progressive ? 'selected' : '' }}>{{ __('progressive_tax') }}</option>
                                            <option value="0" {{ !$row->is_progressive ? 'selected' : '' }}>{{ __('flat_rate_tax') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="display_order" class="form-label">{{ __('field_display_order') }}</label>
                                        <input type="number" class="form-control" name="display_order" id="display_order" value="{{ $row->display_order }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="status" class="form-label">{{ __('field_status') }}</label>
                                        <select class="form-control" name="status" id="status">
                                            <option value="1" {{ $row->status ? 'selected' : '' }}>{{ __('status_active') }}</option>
                                            <option value="0" {{ !$row->status ? 'selected' : '' }}>{{ __('status_inactive') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="effective_from" class="form-label">{{ __('field_effective_from') }}</label>
                                        <input type="date" class="form-control" name="effective_from" id="effective_from" value="{{ $row->effective_from ? $row->effective_from->format('Y-m-d') : '' }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="effective_to" class="form-label">{{ __('field_effective_to') }}</label>
                                        <input type="date" class="form-control" name="effective_to" id="effective_to" value="{{ $row->effective_to ? $row->effective_to->format('Y-m-d') : '' }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> {{ __('btn_update') }}
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
