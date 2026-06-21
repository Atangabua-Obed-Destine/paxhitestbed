@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('btn_edit_payment_plan') }} - #{{ $row->id }}</h5>
                        <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-secondary btn-sm float-end">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post">
                            @csrf
                            @method('PUT')

                            <!-- Student and Fee Info (Read-only) -->
                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>{{ __('field_student') }}:</strong> {{ $row->student->first_name }} {{ $row->student->last_name }} (#{{ $row->student->student_id }})</p>
                                        <p><strong>{{ __('field_fee') }}:</strong> {{ $row->fee->category->title ?? '' }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>{{ __('field_total_amount') }}:</strong> {{ number_format($row->total_amount, 2) }} {!! $setting->currency_symbol !!}</p>
                                        <p><strong>{{ __('field_installments') }}:</strong> {{ $row->installments_count }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Editable Fields -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="late_fee_percentage" class="form-label">{{ __('field_late_fee_percentage') }} (%)</label>
                                        <input type="number" class="form-control" name="late_fee_percentage" id="late_fee_percentage" 
                                               value="{{ old('late_fee_percentage', $row->late_fee_percentage) }}" 
                                               min="0" max="100" step="0.01">
                                        <small class="form-text text-muted">{{ __('late_fee_applied_after_grace_period') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="grace_period_days" class="form-label">{{ __('field_grace_period_days') }}</label>
                                        <input type="number" class="form-control" name="grace_period_days" id="grace_period_days" 
                                               value="{{ old('grace_period_days', $row->grace_period_days) }}" 
                                               min="0" max="30">
                                        <small class="form-text text-muted">{{ __('days_after_due_date') }}</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="notes" class="form-label">{{ __('field_notes') }}</label>
                                        <textarea class="form-control" name="notes" id="notes" rows="3">{{ old('notes', $row->notes) }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Warning if payments exist -->
                            @if($row->total_paid > 0)
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong>{{ __('note') }}:</strong> {{ __('payment_plan_has_payments_edit_limited') }}
                            </div>
                            @endif

                            <!-- Submit Button -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> {{ __('btn_update') }}
                                    </button>
                                    <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                                    </a>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
