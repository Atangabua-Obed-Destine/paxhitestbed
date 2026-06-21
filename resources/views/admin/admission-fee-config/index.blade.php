@extends('admin.layouts.master')
@section('title', $title)

@section('content')

<!-- Main content -->
<div class="main-body">
    <div class="page-wrapper">
        <!-- Page-header start -->
        @include('admin.layouts.inc.breadcrumb')
        <!-- Page-header end -->

        <div class="page-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-cog"></i> {{ __('Admission Fee Configuration') }}</h5>
                        </div>
                        <form class="needs-validation" novalidate action="{{ route($route.'.update') }}" method="post">
                            @csrf
                            <div class="card-block">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    {{ __('Configure the admission/application fee that will be automatically assigned to applicants when they submit their application.') }}
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        @php
                                            $feeEnabled = env('ADMISSION_FEE_ENABLED', 'true');
                                            $isChecked = in_array(strtolower($feeEnabled), ['true', '1', 'yes', 'on']);
                                        @endphp
                                        <input type="checkbox" class="custom-control-input" name="admission_fee_enabled" id="admission_fee_enabled" value="1" 
                                            {{ old('admission_fee_enabled', $isChecked) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="admission_fee_enabled">
                                            <strong>{{ __('Enable Admission Fee Requirement') }}</strong>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        {{ __('When enabled, applicants must pay the admission fee before their applications can be reviewed. When disabled, applications can be reviewed immediately without payment.') }}
                                    </small>
                                </div>

                                <hr>

                                <div class="form-group">
                                    <label for="fee_category_id">{{ __('Fee Category') }} <span>*</span></label>
                                    <select class="form-control @error('fee_category_id') is-invalid @enderror" name="fee_category_id" id="fee_category_id" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($feeCategories as $category)
                                            <option value="{{ $category->id }}" 
                                                {{ old('fee_category_id', $admissionCategory ? $admissionCategory->id : '') == $category->id ? 'selected' : '' }}
                                                @if($category->is_admission) data-is-admission="true" @endif>
                                                {{ $category->title }}
                                                @if($category->is_admission) ({{ __('Current Admission Category') }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">
                                        {{ __('Select the fee category that represents the admission/application fee. Only one category can be marked as the admission fee.') }}
                                    </small>
                                    @error('fee_category_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="fee_amount">{{ __('Fee Amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                                    <input type="number" 
                                           class="form-control @error('fee_amount') is-invalid @enderror" 
                                           name="fee_amount" 
                                           id="fee_amount" 
                                           value="{{ old('fee_amount', $currentFeeAmount) }}" 
                                           min="0" 
                                           step="0.01"
                                           required>
                                    <small class="form-text text-muted">
                                        {{ __('This amount will be automatically assigned to each applicant upon application submission.') }}
                                    </small>
                                    @error('fee_amount')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="due_days">{{ __('Payment Due (Days)') }} <span>*</span></label>
                                    <input type="number" 
                                           class="form-control @error('due_days') is-invalid @enderror" 
                                           name="due_days" 
                                           id="due_days" 
                                           value="{{ old('due_days', $currentDueDays) }}" 
                                           min="1" 
                                           max="365"
                                           required>
                                    <small class="form-text text-muted">
                                        {{ __('Number of days after application submission when the fee should be due.') }}
                                    </small>
                                    @error('due_days')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="payment_instructions">{{ __('Payment Instructions') }}</label>
                                    <textarea class="form-control @error('payment_instructions') is-invalid @enderror" 
                                              name="payment_instructions" 
                                              id="payment_instructions" 
                                              rows="5"
                                              placeholder="{{ __('Enter payment instructions for applicants (bank details, mobile money numbers, etc.)') }}">{{ old('payment_instructions', env('ADMISSION_FEE_INSTRUCTIONS', '')) }}</textarea>
                                    <small class="form-text text-muted">
                                        {{ __('These instructions will be displayed to applicants on their dashboard. Include bank account details, mobile money numbers, or other payment methods.') }}
                                    </small>
                                    @error('payment_instructions')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> {{ __('btn_save') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> {{ __('Current Configuration') }}</h5>
                        </div>
                        <div class="card-block">
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th>{{ __('Fee Category') }}:</th>
                                        <td>
                                            @if($admissionCategory)
                                                <span class="badge badge-success">{{ $admissionCategory->title }}</span>
                                            @else
                                                <span class="badge badge-warning">{{ __('Not Configured') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Fee Amount') }}:</th>
                                        <td>
                                            <strong>{{ number_format($currentFeeAmount, $setting->decimal_place ?? 2) }}</strong> 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Due Days') }}:</th>
                                        <td><strong>{{ $currentDueDays }}</strong> days</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Requirement') }}:</th>
                                        <td>
                                            @php
                                                $feeEnabled = env('ADMISSION_FEE_ENABLED', 'true');
                                                $isEnabled = in_array(strtolower($feeEnabled), ['true', '1', 'yes', 'on']);
                                            @endphp
                                            @if($isEnabled)
                                                <span class="badge badge-success">{{ __('Enabled') }}</span>
                                            @else
                                                <span class="badge badge-secondary">{{ __('Disabled') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Status') }}:</th>
                                        <td>
                                            @if($admissionCategory && $currentFeeAmount > 0)
                                                <span class="badge badge-success">{{ __('Configured') }}</span>
                                            @else
                                                <span class="badge badge-warning">{{ __('Needs Configuration') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <hr>

                            <h6 class="mb-2">{{ __('How it works') }}:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-2"><i class="fas fa-check text-success"></i> When an applicant submits their application form</li>
                                <li class="mb-2"><i class="fas fa-check text-success"></i> A fee is automatically created and assigned</li>
                                <li class="mb-2"><i class="fas fa-check text-success"></i> Fee appears on applicant's dashboard</li>
                                <li class="mb-2"><i class="fas fa-check text-success"></i> Applicants can pay via manual payment</li>
                                <li class="mb-2"><i class="fas fa-check text-success"></i> Payments are verified in the admin portal</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Main content end -->

@endsection
