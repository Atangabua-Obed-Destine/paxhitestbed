@extends('application.portal.layout')

@section('content')
@php
    $currency = $setting->currency_symbol ?? 'FCFA';
    $stageBadge = [
        'draft' => 'secondary',
        'submitted' => 'info',
        'under_review' => 'primary',
        'documents_required' => 'warning',
        'interview' => 'primary',
        'decision_pending' => 'primary',
        'decision_approved' => 'success',
        'decision_rejected' => 'danger',
    ];
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="portal-card position-relative mb-2" style="background: linear-gradient(135deg, #182b49 0%, #667eea 100%);">
            <!-- Decorative background elements -->
            <div style="position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%); transform: rotate(30deg); pointer-events: none;"></div>
            
            <div class="p-5 position-relative z-index-1">
                <h3 class="mb-2 text-white fw-bold">{{ __('Welcome back,') }} {{ mb_convert_case($applicant->full_name, MB_CASE_TITLE) }}!</h3>
                <p class="mb-0 text-white-50" style="max-width: 600px; font-size: 1.05rem;">
                    {{ __('Manage your applications, track your admission status, and upload any required documents right from your personal dashboard.') }}
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center p-4">
                <h5 class="mb-2 mb-md-0 fw-bold text-dark">{{ __('My Applications') }}</h5>
                <a href="{{ route('application.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="fas fa-plus me-1"></i> {{ __('New Application') }}
                </a>
            </div>
            <div class="card-body p-0">
                @if($applications->isEmpty())
                    <div class="text-center py-5">
                        <div class="mb-3 d-inline-block p-4 rounded-circle bg-light">
                            <i class="fas fa-folder-open fa-3x text-muted opacity-50"></i>
                        </div>
                        <h5 class="text-dark fw-bold">{{ __('No applications yet') }}</h5>
                        <p class="text-muted mb-4">{{ __('You haven\'t started any applications. Ready to begin?') }}</p>
                        <a href="{{ route('application.create') }}" class="btn btn-primary rounded-pill px-4">
                            {{ __('Start your first application') }} <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0" style="border-collapse: separate; border-spacing: 0;">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">{{ __('Programme') }}</th>
                                    <th>{{ __('Degree Type') }}</th>
                                    <th>{{ __('Academic Year') }}</th>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($applications as $app)
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <strong class="text-dark d-block mb-1">{{ optional($app->program)->title ?? __('Programme not set') }}</strong>
                                            <span class="text-muted small">#{{ $app->registration_no }}</span>
                                        </td>
                                        <td class="py-3 text-muted">{{ optional($app->degreeType)->title ?? '—' }}</td>
                                        <td class="py-3 text-muted">{{ optional($app->session)->title ?? $app->academic_year ?? '—' }}</td>
                                        <td class="py-3">
                                            <span class="badge rounded-pill bg-{{ $stageBadge[$app->stage] ?? 'secondary' }} bg-opacity-10 text-{{ $stageBadge[$app->stage] ?? 'secondary' }} px-3 py-2 border border-{{ $stageBadge[$app->stage] ?? 'secondary' }} border-opacity-25" style="font-weight: 600;">
                                                <i class="fas fa-circle me-1" style="font-size: 8px; vertical-align: middle;"></i> {{ $app->progress_label }}
                                            </span>
                                            @if($app->stage === 'draft')
                                                <div class="progress mt-2 bg-light" style="height:4px;width:120px;">
                                                    <div class="progress-bar bg-primary" style="width: {{ $app->draft_progress ?? 0 }}%"></div>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4 py-3">
                                            @if($app->stage === 'draft')
                                                <a href="{{ route('application.edit', $app) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    {{ __('Continue') }} <i class="fas fa-arrow-right ms-1"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('application.timeline', $app) }}" class="btn btn-sm btn-light rounded-pill px-3 text-primary fw-semibold">
                                                    {{ __('Track Status') }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Admission fee / receipt status for applications that carry a fee --}}
                                    @if($app->admissionFee)
                                        @php
                                            $fee = $app->admissionFee;
                                            $balance = ($fee->fee_amount + $fee->fine_amount - $fee->discount_amount) - $fee->paid_amount;
                                            $latestReceipt = $fee->paymentReceipts->sortByDesc('id')->first();
                                        @endphp
                                        <tr class="bg-light">
                                            <td colspan="6" class="px-3 py-2">
                                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                                                    <div class="small">
                                                        <i class="fas fa-receipt me-1 text-muted"></i>
                                                        {{ __('Admission fee') }}:
                                                        <strong>{{ number_format($fee->fee_amount) }} {{ $currency }}</strong>
                                                        @if($fee->paid_amount > 0)
                                                            · {{ __('Paid') }}: {{ number_format($fee->paid_amount) }} {{ $currency }}
                                                        @endif
                                                        @if($balance > 0)
                                                            · <span class="text-danger">{{ __('Balance') }}: {{ number_format($balance) }} {{ $currency }}</span>
                                                        @else
                                                            · <span class="text-success">{{ __('Settled') }}</span>
                                                        @endif
                                                        @if($latestReceipt)
                                                            · {{ __('Receipt') }}:
                                                            <span class="badge badge-{{ $latestReceipt->verification_status === 'approved' ? 'success' : ($latestReceipt->verification_status === 'rejected' ? 'danger' : 'warning') }}">
                                                                {{ ucfirst($latestReceipt->verification_status) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($balance > 0)
                                                        {{-- Sends the applicant back to the form rather than
                                                             opening a payment box here. Paying is the last step of
                                                             the application, not a separate errand: approval submits
                                                             the application outright, so anything still unfinished
                                                             would be submitted unfinished. The wizard walks them
                                                             through what is left and takes the payment at the end. --}}
                                                        <a href="{{ route('application.edit', $app) }}"
                                                           class="btn btn-sm btn-primary mt-2 mt-md-0">
                                                            <i class="fas fa-arrow-right me-1"></i>{{ __('Continue and pay') }}
                                                        </a>
                                                    @elseif($app->stage === 'draft')
                                                        {{-- Paid, but back in draft — an admissions officer sent it
                                                             back for something to be corrected. There is nothing to
                                                             pay, so "Continue and pay" would be wrong; the applicant
                                                             fixes what was asked and it submits itself again once
                                                             complete. Without this they saw no action at all. --}}
                                                        <a href="{{ route('application.edit', $app) }}"
                                                           class="btn btn-sm btn-warning mt-2 mt-md-0">
                                                            <i class="fas fa-rotate-left me-1"></i>{{ __('Update and resubmit') }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="portal-card p-4 mb-4 position-relative overflow-hidden">
            <!-- Top accent bar -->
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #667eea, #764ba2);"></div>
            
            <div class="d-flex align-items-center mb-3 mt-2">
                <div class="bg-light rounded-circle p-3 me-3 text-primary">
                    <i class="fas fa-headset fa-lg"></i>
                </div>
                <h5 class="mb-0 fw-bold text-dark">{{ __('Need Help?') }}</h5>
            </div>
            
            <p class="mb-4 text-muted">{{ __('Our Admissions Office is here to support you through your application process.') }}</p>
            
            <div class="bg-light p-3 rounded mb-4">
                @if(!empty($setting->email))
                    <div class="d-flex align-items-center mb-2">
                        <i class="far fa-envelope text-primary me-3 w-15px text-center"></i>
                        <span class="text-dark fw-medium">{{ $setting->email }}</span>
                    </div>
                @endif
                @if(!empty($setting->phone))
                    <div class="d-flex align-items-center">
                        <i class="fas fa-phone text-primary me-3 w-15px text-center"></i>
                        <span class="text-dark fw-medium">{{ $setting->phone }}</span>
                    </div>
                @endif
            </div>
            
            <a href="{{ route('application.create') }}" class="btn btn-outline-primary w-100 rounded-pill">
                <i class="fas fa-plus me-2"></i>{{ __('Start a New Application') }}
            </a>
        </div>
    </div>
</div>
@endsection

