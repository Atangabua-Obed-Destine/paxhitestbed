@extends('application.portal.layout')

@section('content')
{{-- Show Continue Application Banner for Draft Applications --}}
@if($application->stage === 'draft')
<div class="alert alert-info border-info mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div class="d-flex align-items-center mb-2 mb-md-0">
            <i class="fas fa-edit fa-2x me-3 text-info"></i>
            <div>
                <h5 class="mb-1">{{ __('Your application is incomplete') }}</h5>
                <p class="mb-0 text-muted">{{ __('You have saved a draft. Continue filling out your application to submit it.') }}</p>
                @if($application->draft_last_saved_at)
                <small class="text-muted">{{ __('Last saved') }}: {{ $application->draft_last_saved_at->format('F j, Y g:i A') }}</small>
                @endif
            </div>
        </div>
        <a href="{{ route('application.index') }}" class="btn btn-info btn-lg">
            <i class="fas fa-arrow-right me-2"></i>{{ __('Continue Application') }}
        </a>
    </div>
</div>
@endif

{{-- Show Document Resubmission Alert --}}
@php
    $documentsNeedingResubmission = $application->documents->filter(function($doc) {
        return $doc->needs_resubmission && !$doc->resubmitted_at;
    });
@endphp
@if($documentsNeedingResubmission->count() > 0)
<div class="alert alert-warning border-warning mb-4">
    <div class="d-flex align-items-start">
        <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
        <div class="flex-grow-1">
            <h5 class="mb-1">{{ __('Action Required: Document Resubmission') }}</h5>
            <p class="mb-2">{{ __('Some of your documents need to be resubmitted. Please review the issues below and upload corrected documents.') }}</p>
            <a href="#document-resubmission" class="btn btn-warning">
                <i class="fas fa-upload me-2"></i>{{ __('Resubmit Documents') }}
            </a>
        </div>
    </div>
</div>
@endif

<div class="portal-card card mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">{{ __('Welcome back') }}, {{ $application->first_name }}!</h5>
            <small class="text-muted">{{ __('Your application ID') }}: <strong>#{{ $application->registration_no }}</strong></small>
        </div>
        <span class="badge bg-primary">{{ $application->progress_label }}</span>
    </div>
    <div class="card-body p-4">
        <div class="mb-3">
            <label class="form-label text-muted">{{ __('Application progress') }}</label>
            <div class="progress">
                <div class="progress-bar bg-success" role="progressbar" style="width: {{ (int) $application->progress }}%" aria-valuenow="{{ (int) $application->progress }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="text-muted d-block mt-2">{{ __('Current stage') }}: {{ $application->progress_label }}</small>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="p-3 border rounded">
                    <h6 class="text-muted text-uppercase fw-bold">{{ __('Program choice') }}</h6>
                    <p class="mb-0">{{ $application->program->title ?? __('Not assigned yet') }}</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded">
                    <h6 class="text-muted text-uppercase fw-bold">{{ __('Submitted on') }}</h6>
                    <p class="mb-0">{{ optional($application->apply_date)->format('F j, Y') }}</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded">
                    <h6 class="text-muted text-uppercase fw-bold">{{ __('Last portal login') }}</h6>
                    <p class="mb-0">{{ optional($application->portal_last_login_at)->format('F j, Y g:i A') ?? __('First visit') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admission Fee Details -->
@if($application->admissionFee)
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>{{ __('Admission Fee Details') }}</h5>
    </div>
    <div class="card-body p-4">
        <!-- Important Notice -->
        @if($application->admissionFee->status == 0 || $application->admissionFee->remaining_balance > 0)
        <div class="alert alert-warning border-warning mb-4">
            <div class="d-flex">
                <div class="me-3">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-2"><strong>{{ __('Important Notice') }}</strong></h6>
                    <p class="mb-2">{{ __('Your application will only be reviewed after your admission fee has been paid and verified by our administration team.') }}</p>
                    @if(env('ADMISSION_FEE_INSTRUCTIONS'))
                    <div class="mt-2 p-2 bg-light rounded">
                        <small class="text-muted"><strong>{{ __('Payment Instructions') }}:</strong></small>
                        <p class="mb-0 small">{{ env('ADMISSION_FEE_INSTRUCTIONS') }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6 class="text-muted mb-2">{{ __('Fee Category') }}</h6>
                    <p class="mb-0 fw-bold">{{ $application->admissionFee->category->title ?? 'Admission Fee' }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6 class="text-muted mb-2">{{ __('Amount') }}</h6>
                    <p class="mb-0 fw-bold text-primary">
                        @if(isset($setting->decimal_place))
                        {{ number_format($application->admissionFee->fee_amount, $setting->decimal_place, '.', '') }} 
                        @else
                        {{ number_format($application->admissionFee->fee_amount, 2, '.', '') }} 
                        @endif 
                        {!! $setting->currency_symbol !!}
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6 class="text-muted mb-2">{{ __('Paid Amount') }}</h6>
                    <p class="mb-0 fw-bold text-success">
                        @if(isset($setting->decimal_place))
                        {{ number_format($application->admissionFee->paid_amount, $setting->decimal_place, '.', '') }} 
                        @else
                        {{ number_format($application->admissionFee->paid_amount, 2, '.', '') }} 
                        @endif 
                        {!! $setting->currency_symbol !!}
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6 class="text-muted mb-2">{{ __('Balance') }}</h6>
                    <p class="mb-0 fw-bold text-danger">
                        @if(isset($setting->decimal_place))
                        {{ number_format($application->admissionFee->remaining_balance, $setting->decimal_place, '.', '') }} 
                        @else
                        {{ number_format($application->admissionFee->remaining_balance, 2, '.', '') }} 
                        @endif 
                        {!! $setting->currency_symbol !!}
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6 class="text-muted mb-2">{{ __('Due Date') }}</h6>
                    <p class="mb-0">{{ $application->admissionFee->due_date ? date('F j, Y', strtotime($application->admissionFee->due_date)) : 'N/A' }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6 class="text-muted mb-2">{{ __('Payment Status') }}</h6>
                    <p class="mb-0">{!! $application->admissionFee->status_badge !!}</p>
                </div>
            </div>
        </div>

        <!-- Payment Upload Form -->
        @php
            $hasPendingReceipt = $application->admissionFee->paymentReceipts && 
                                 $application->admissionFee->paymentReceipts->where('verification_status', 'pending')->count() > 0;
        @endphp
        @if($application->admissionFee->remaining_balance > 0 && !$hasPendingReceipt)
        <div class="mt-4">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-upload me-2"></i>{{ __('Upload Payment Receipt') }}</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('application.admission-fee.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="payment_date" class="form-label">{{ __('Payment Date') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('payment_date') is-invalid @enderror" id="payment_date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                                @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="amount" class="form-label">{{ __('Amount Paid') }} <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control-plaintext border rounded px-3 py-2 bg-light" id="amount" name="amount" value="{{ old('amount', $application->admissionFee->remaining_balance) }}" readonly required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="payment_reference" class="form-label">{{ __('Payment Reference/Transaction ID') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('payment_reference') is-invalid @enderror" id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" required>
                                @error('payment_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="payment_method" class="form-label">{{ __('Payment Method') }} <span class="text-danger">*</span></label>
                                <select class="form-select @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method" required>
                                    <option value="">{{ __('Select Payment Method') }}</option>
                                    <option value="4" {{ old('payment_method') == '4' ? 'selected' : '' }}>{{ __('Bank Transfer') }}</option>
                                    <option value="5" {{ old('payment_method') == '5' ? 'selected' : '' }}>{{ __('Mobile Money') }}</option>
                                    <option value="2" {{ old('payment_method') == '2' ? 'selected' : '' }}>{{ __('Cash') }}</option>
                                    <option value="3" {{ old('payment_method') == '3' ? 'selected' : '' }}>{{ __('Cheque') }}</option>
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label for="receipt_file" class="form-label">{{ __('Upload Receipt') }} <span class="text-danger">*</span></label>
                                <input type="file" class="form-control @error('receipt_file') is-invalid @enderror" id="receipt_file" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small class="form-text text-muted">{{ __('Accepted formats: PDF, JPG, PNG. Max size: 2MB') }}</small>
                                @error('receipt_file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label for="student_note" class="form-label">{{ __('Additional Note (Optional)') }}</label>
                                <textarea class="form-control @error('student_note') is-invalid @enderror" id="student_note" name="student_note" rows="2">{{ old('student_note') }}</textarea>
                                @error('student_note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>{{ __('Submit Payment Receipt') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @elseif($hasPendingReceipt)
        <div class="alert alert-info mt-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fa-2x me-3"></i>
                <div>
                    <strong>{{ __('Payment Verification in Progress') }}</strong>
                    <p class="mb-0 mt-1">{{ __('You have a pending payment receipt awaiting verification. You can upload another receipt once the current one has been reviewed.') }}</p>
                </div>
            </div>
        </div>
        @endif

        @if($application->admissionFee->paymentReceipts && $application->admissionFee->paymentReceipts->count() > 0)
        <div class="mt-4">
            <h6 class="mb-3">{{ __('Payment Receipts') }}</h6>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($application->admissionFee->paymentReceipts as $receipt)
                        <tr>
                            <td>{{ $receipt->payment_date ? date('M j, Y', strtotime($receipt->payment_date)) : 'N/A' }}</td>
                            <td>
                                @if(isset($setting->decimal_place))
                                {{ number_format($receipt->amount, $setting->decimal_place, '.', '') }} 
                                @else
                                {{ number_format($receipt->amount, 2, '.', '') }} 
                                @endif 
                                {!! $setting->currency_symbol !!}
                            </td>
                            <td>{{ $receipt->payment_reference ?? 'N/A' }}</td>
                            <td>
                                @if($receipt->verification_status == 'approved')
                                    <span class="badge bg-success">{{ __('Verified') }}</span>
                                @elseif($receipt->verification_status == 'rejected')
                                    <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                @else
                                    <span class="badge bg-warning">{{ __('Pending Verification') }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle me-2"></i>{{ __('No payment receipts uploaded yet.') }}
        </div>
        @endif
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="portal-card card h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Latest updates') }}</h5>
                <a href="{{ route('application.timeline') }}" class="btn btn-link p-0">{{ __('View full timeline') }}</a>
            </div>
            <div class="card-body p-4">
                @forelse($timeline->take(4) as $item)
                    <div class="timeline-item mb-3">
                        <h6 class="mb-1">{{ $item->title ?? ($application::stageLabelMap()[$item->stage] ?? ucfirst(str_replace('_', ' ', $item->stage))) }}</h6>
                        <small class="text-muted d-block">{{ $item->created_at->format('F j, Y g:i A') }}</small>
                        @if($item->note)
                            <p class="mb-0 mt-2">{{ $item->note }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('No updates yet. We will notify you as soon as there is progress.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="portal-card card h-100">
            <div class="card-header py-3">
                <h5 class="mb-0">{{ __('Quick details') }}</h5>
            </div>
            <div class="card-body p-4">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">{{ __('field_email') }}</dt>
                    <dd class="col-7">{{ $application->email }}</dd>
                    <dt class="col-5 text-muted">{{ __('field_phone') }}</dt>
                    <dd class="col-7">{{ $application->phone }}</dd>
                    <dt class="col-5 text-muted">{{ __('field_gender') }}</dt>
                    <dd class="col-7">
                        @if( $application->gender == 1 )
                            {{ __('gender_male') }}
                        @elseif( $application->gender == 2 )
                            {{ __('gender_female') }}
                        @elseif( $application->gender == 3 )
                            {{ __('gender_other') }}
                        @endif
                    </dd>
                    <dt class="col-5 text-muted">{{ __('field_dob') }}</dt>
                    <dd class="col-7">{{ optional($application->dob)->format('F j, Y') }}</dd>
                    <dt class="col-5 text-muted">{{ __('field_status') }}</dt>
                    <dd class="col-7">
                        @if( $application->status == 0 )
                            <span class="badge bg-secondary">{{ __('Draft') }}</span>
                        @elseif( $application->status == 1 )
                            <span class="badge bg-primary">{{ __('status_pending') }}</span>
                        @elseif( $application->status == 2 )
                            <span class="badge bg-success">{{ __('status_approved') }}</span>
                        @elseif( $application->status == 3 )
                            <span class="badge bg-danger">{{ __('status_rejected') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('Unknown') }}</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Personal Information Section -->
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Personal Information') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Full Name') }}</small>
                <p class="mb-0 fw-bold">{{ $application->first_name }} {{ $application->last_name }} {{ $application->other_names }}</p>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Date of Birth') }}</small>
                <p class="mb-0">{{ optional($application->dob)->format('F j, Y') }}</p>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Gender') }}</small>
                <p class="mb-0">
                    @if( $application->gender == 1 )
                        {{ __('gender_male') }}
                    @elseif( $application->gender == 2 )
                        {{ __('gender_female') }}
                    @elseif( $application->gender == 3 )
                        {{ __('gender_other') }}
                    @endif
                </p>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Nationality') }}</small>
                <p class="mb-0">{{ $application->nationality ?? 'N/A' }}</p>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('National ID') }}</small>
                <p class="mb-0">{{ $application->national_id ?? 'N/A' }}</p>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Passport Number') }}</small>
                <p class="mb-0">{{ $application->passport_no ?? 'N/A' }}</p>
            </div>
            @if($application->birth_city || $application->birth_country)
            <div class="col-md-12">
                <small class="text-muted d-block">{{ __('Place of Birth') }}</small>
                <p class="mb-0">{{ $application->birth_city }}, {{ $application->birth_region }} {{ $application->birth_country }}</p>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Religion & Faith Section -->
@if($application->religionDetail || $application->is_catholic_baptised || $application->is_confirmed || $application->has_first_communion)
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-cross me-2"></i>{{ __('Religion & Faith') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-muted d-block">{{ __('Religion') }}</small>
                <p class="mb-0 fw-bold">{{ $application->religionDetail->title ?? 'N/A' }}</p>
            </div>
            @if($application->religionDetail && $application->religionDetail->is_catholic)
            <div class="col-md-12">
                <hr class="my-2">
                <small class="text-muted d-block mb-2">{{ __('Catholic Sacraments') }}</small>
                <div class="d-flex gap-3 flex-wrap">
                    <div>
                        @if($application->is_catholic_baptised)
                            <span class="badge bg-success"><i class="fas fa-check"></i> {{ __('Baptised') }}</span>
                        @else
                            <span class="badge bg-secondary"><i class="fas fa-times"></i> {{ __('Not Baptised') }}</span>
                        @endif
                    </div>
                    <div>
                        @if($application->is_confirmed)
                            <span class="badge bg-success"><i class="fas fa-check"></i> {{ __('Confirmed') }}</span>
                        @else
                            <span class="badge bg-secondary"><i class="fas fa-times"></i> {{ __('Not Confirmed') }}</span>
                        @endif
                    </div>
                    <div>
                        @if($application->has_first_communion)
                            <span class="badge bg-success"><i class="fas fa-check"></i> {{ __('First Communion') }}</span>
                        @else
                            <span class="badge bg-secondary"><i class="fas fa-times"></i> {{ __('No First Communion') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

<!-- Contact & Address Information -->
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>{{ __('Contact & Address Information') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="text-primary mb-3">{{ __('Contact Details') }}</h6>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">{{ __('Email') }}</dt>
                    <dd class="col-7">{{ $application->email }}</dd>
                    <dt class="col-5 text-muted">{{ __('Phone') }}</dt>
                    <dd class="col-7">{{ $application->phone }}</dd>
                    @if($application->alternate_phone)
                    <dt class="col-5 text-muted">{{ __('Alternate Phone') }}</dt>
                    <dd class="col-7">{{ $application->alternate_phone }}</dd>
                    @endif
                </dl>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary mb-3">{{ __('Present Address') }}</h6>
                <p class="mb-1">
                    @if($application->present_province)
                        {{ $application->present_province }},
                    @endif
                    @if($application->present_district)
                        {{ $application->present_district }}
                    @endif
                </p>
                @if($application->present_village)
                    <p class="mb-1">{{ __('Village/Sector') }}: {{ $application->present_village }}</p>
                @endif
                @if($application->present_address)
                    <p class="mb-0 text-muted">{{ $application->present_address }}</p>
                @endif
            </div>
            @if($application->permanent_province || $application->permanent_address)
            <div class="col-md-6">
                <h6 class="text-primary mb-3">{{ __('Permanent Address') }}</h6>
                <p class="mb-1">
                    @if($application->permanent_province)
                        {{ $application->permanent_province }},
                    @endif
                    @if($application->permanent_district)
                        {{ $application->permanent_district }}
                    @endif
                </p>
                @if($application->permanent_village)
                    <p class="mb-1">{{ __('Village/Sector') }}: {{ $application->permanent_village }}</p>
                @endif
                @if($application->permanent_address)
                    <p class="mb-0 text-muted">{{ $application->permanent_address }}</p>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Program Choices -->
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>{{ __('Program Choices') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('First Choice') }}</small>
                <p class="mb-0 fw-bold">{{ $application->program->title ?? 'N/A' }}</p>
            </div>
            @if($application->second_program_choice_id)
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Second Choice') }}</small>
                <p class="mb-0">{{ optional($application->preferredProgramSecond)->title ?? 'N/A' }}</p>
            </div>
            @endif
            @if($application->third_program_choice_id)
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Third Choice') }}</small>
                <p class="mb-0">{{ optional($application->preferredProgramThird)->title ?? 'N/A' }}</p>
            </div>
            @endif
            @if($application->academic_year)
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Academic Year') }}</small>
                <p class="mb-0">{{ $application->academic_year }}</p>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Guardian Information -->
@if($application->guardians && $application->guardians->count() > 0)
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-users me-2"></i>{{ __('Guardian/Parent Information') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            @foreach($application->guardians as $guardian)
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0">{{ $guardian->full_name }}</h6>
                        @if($guardian->is_primary)
                            <span class="badge bg-primary">Primary</span>
                        @endif
                    </div>
                    <p class="text-muted mb-1"><small>{{ __('Type') }}: {{ $guardian->type }}</small></p>
                    @if($guardian->relationship)
                        <p class="text-muted mb-1"><small>{{ __('Relationship') }}: {{ $guardian->relationship }}</small></p>
                    @endif
                    @if($guardian->occupation)
                        <p class="text-muted mb-1"><small>{{ __('Occupation') }}: {{ $guardian->occupation }}</small></p>
                    @endif
                    <hr class="my-2">
                    <p class="mb-1"><i class="fas fa-phone me-2"></i>{{ $guardian->phone_primary }}</p>
                    @if($guardian->email)
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i>{{ $guardian->email }}</p>
                    @endif
                    @if($guardian->address_line1)
                        <p class="mb-0 text-muted"><small><i class="fas fa-map-marker-alt me-2"></i>{{ $guardian->address_line1 }}, {{ $guardian->city }} {{ $guardian->country }}</small></p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<!-- Academic History -->
@if($application->academicHistories && $application->academicHistories->count() > 0)
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-school me-2"></i>{{ __('Academic History') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Institution') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Certificate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($application->academicHistories->sortBy('display_order') as $history)
                    <tr>
                        <td>{{ $history->institution_name }}</td>
                        <td>{{ $history->city }}, {{ $history->country }}</td>
                        <td>
                            @if($history->date_from && $history->date_to)
                                {{ date('Y', strtotime($history->date_from)) }} - {{ date('Y', strtotime($history->date_to)) }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ $history->certificate_obtained ?? 'N/A' }}</td>
                    </tr>
                    @if($history->gce_ol_detail || $history->gce_al_detail || $history->probatoire_detail || $history->baccalaureate_detail)
                    <tr>
                        <td colspan="4" class="bg-light">
                            <small class="text-muted">
                                @if($history->gce_ol_detail)<strong>GCE O-Level:</strong> {{ $history->gce_ol_detail }}<br>@endif
                                @if($history->gce_al_detail)<strong>GCE A-Level:</strong> {{ $history->gce_al_detail }}<br>@endif
                                @if($history->probatoire_detail)<strong>Probatoire:</strong> {{ $history->probatoire_detail }}<br>@endif
                                @if($history->baccalaureate_detail)<strong>Baccalaureate:</strong> {{ $history->baccalaureate_detail }}@endif
                            </small>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Language Proficiency -->
@if($application->languages && $application->languages->count() > 0)
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-language me-2"></i>{{ __('Language Proficiency') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            @foreach($application->languages as $language)
            <div class="col-md-4">
                <div class="border rounded p-3">
                    <h6 class="mb-1">{{ $language->language }}</h6>
                    <p class="mb-0 text-muted">
                        <small>{{ __('Years of Study') }}: {{ $language->years_of_study ?? 'N/A' }}</small><br>
                        <small>{{ __('Fluency') }}: 
                            <span class="badge bg-info">{{ ucfirst($language->fluency_level ?? 'N/A') }}</span>
                        </small>
                    </p>
                </div>
            </div>
            @endforeach
        </div>
        @if($application->mother_tongue)
        <div class="mt-3">
            <small class="text-muted">{{ __('Mother Tongue') }}: <strong>{{ $application->mother_tongue }}</strong></small>
        </div>
        @endif
    </div>
</div>
@endif

<!-- Documents Uploaded -->
@if($application->documents && $application->documents->count() > 0)
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Uploaded Documents') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Document Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($application->documents as $document)
                    <tr>
                        <td>
                            <strong>{{ ucwords(str_replace('_', ' ', $document->document_type)) }}</strong>
                            @if($document->is_optional)
                                <span class="badge bg-secondary ms-2">Optional</span>
                            @else
                                <span class="badge bg-warning ms-2">Required</span>
                            @endif
                        </td>
                        <td>
                            @if($document->is_received && $document->file_path)
                                <span class="badge bg-success"><i class="fas fa-check"></i> Uploaded</span>
                            @else
                                <span class="badge bg-danger"><i class="fas fa-times"></i> Not Uploaded</span>
                            @endif
                        </td>
                        <td>
                            @if($document->file_path)
                                <a href="{{ asset('uploads/student/'.$document->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> {{ __('View') }}
                                </a>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                    @if($document->notes)
                    <tr>
                        <td colspan="3" class="bg-light">
                            <small class="text-muted"><strong>Note:</strong> {{ $document->notes }}</small>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Legacy Documents (if any) -->
@php($uploadsPath = 'uploads/student')
@if($application->school_transcript || $application->school_certificate || $application->collage_transcript || $application->collage_certificate || $application->photo || $application->signature)
<div class="portal-card card mt-4">
    <div class="card-header py-3 bg-light">
        <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i>{{ __('Additional Documents') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="list-group">
            @if($application->photo)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-image me-2"></i>{{ __('Photo') }}</span>
                    <a href="{{ asset($uploadsPath.'/'.$application->photo) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                </div>
            @endif
            @if($application->signature)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-signature me-2"></i>{{ __('Signature') }}</span>
                    <a href="{{ asset($uploadsPath.'/'.$application->signature) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                </div>
            @endif
            @if($application->school_transcript)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-pdf me-2"></i>{{ __('School transcript') }}</span>
                    <a href="{{ asset($uploadsPath.'/'.$application->school_transcript) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                </div>
            @endif
            @if($application->school_certificate)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-pdf me-2"></i>{{ __('School certificate') }}</span>
                    <a href="{{ asset($uploadsPath.'/'.$application->school_certificate) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                </div>
            @endif
            @if($application->collage_transcript)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-pdf me-2"></i>{{ __('College transcript') }}</span>
                    <a href="{{ asset($uploadsPath.'/'.$application->collage_transcript) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                </div>
            @endif
            @if($application->collage_certificate)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-pdf me-2"></i>{{ __('College certificate') }}</span>
                    <a href="{{ asset($uploadsPath.'/'.$application->collage_certificate) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- Document Resubmission Section --}}
@if($documentsNeedingResubmission->count() > 0)
<div class="portal-card card mt-4" id="document-resubmission">
    <div class="card-header py-3 bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Documents Requiring Resubmission') }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            {{ __('The documents listed below were reviewed and require correction or resubmission. Please upload new documents addressing the issues mentioned.') }}
        </div>
        
        <form action="{{ route('application.resubmit-documents') }}" method="POST" enctype="multipart/form-data" id="resubmit-documents-form">
            @csrf
            
            @foreach($documentsNeedingResubmission as $document)
            <div class="card mb-3 border-warning">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">
                        <i class="fas fa-file-alt me-2"></i>
                        {{ \App\Support\ApplicationDocumentRequirements::all()[$document->document_type]['label'] ?? ucwords(str_replace('_', ' ', $document->document_type)) }}
                    </span>
                    <span class="badge bg-warning text-dark">{{ __('Resubmission Required') }}</span>
                </div>
                <div class="card-body">
                    @if($document->rejection_reason)
                    <div class="alert alert-danger mb-3">
                        <strong><i class="fas fa-times-circle me-1"></i> {{ __('Issue') }}:</strong>
                        {{ $document->rejection_reason }}
                    </div>
                    @endif
                    
                    @if($document->file_path)
                    <div class="mb-3">
                        <label class="form-label text-muted">{{ __('Current Document') }}:</label>
                        <div>
                            <a href="{{ asset('uploads/student/'.$document->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-eye me-1"></i> {{ __('View Current') }}
                            </a>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mb-2">
                        <label for="document_{{ $document->document_type }}" class="form-label">
                            {{ __('Upload New Document') }} <span class="text-danger">*</span>
                        </label>
                        <input type="file" 
                               class="form-control @error('documents.'.$document->document_type) is-invalid @enderror" 
                               id="document_{{ $document->document_type }}" 
                               name="documents[{{ $document->document_type }}]"
                               accept=".jpg,.jpeg,.png,.pdf"
                               required>
                        <small class="form-text text-muted">{{ __('Accepted formats: JPG, PNG, PDF. Maximum size: 10MB') }}</small>
                        @error('documents.'.$document->document_type)
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            @endforeach
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-upload me-2"></i>{{ __('Submit Resubmitted Documents') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
