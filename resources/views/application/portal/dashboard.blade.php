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
        <div class="portal-card p-4 mb-1">
            <h3 class="mb-1">{{ __('My Account') }}</h3>
            <p class="mb-1 text-muted">{{ __('Welcome,') }} <strong>{{ strtoupper($applicant->full_name) }}</strong>.</p>
            <p class="mb-0 text-muted">{{ __('From here you can start a new application, continue a draft, and track the status of each application you submit.') }}</p>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3">
                <h5 class="mb-2 mb-md-0">{{ __('My Applications') }}</h5>
                <a href="{{ route('application.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> {{ __('Create a New Application') }}
                </a>
            </div>
            <div class="card-body p-0">
                @if($applications->isEmpty())
                    <div class="text-center p-5">
                        <i class="fas fa-folder-open fa-2x text-muted mb-3"></i>
                        <p class="text-muted mb-3">{{ __('You have not started any applications yet.') }}</p>
                        <a href="{{ route('application.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> {{ __('Start your first application') }}
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">{{ __('Programme') }}</th>
                                    <th>{{ __('Degree Type') }}</th>
                                    <th>{{ __('Intake') }}</th>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($applications as $app)
                                    <tr>
                                        <td class="ps-3">
                                            <strong>{{ optional($app->program)->title ?? __('Programme not set') }}</strong>
                                        </td>
                                        <td>{{ optional($app->degreeType)->title ?? '—' }}</td>
                                        <td>{{ optional($app->session)->title ?? $app->academic_year ?? '—' }}</td>
                                        <td><span class="text-muted">#{{ $app->registration_no }}</span></td>
                                        <td>
                                            <span class="badge badge-pill badge-{{ $stageBadge[$app->stage] ?? 'secondary' }}">
                                                {{ $app->progress_label }}
                                            </span>
                                            @if($app->stage === 'draft')
                                                <div class="progress mt-2" style="height:6px;width:120px;">
                                                    <div class="progress-bar bg-primary" style="width: {{ $app->draft_progress ?? 0 }}%"></div>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if($app->stage === 'draft')
                                                <a href="{{ route('application.edit', $app) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-pen me-1"></i>{{ __('Continue') }}
                                                </a>
                                            @else
                                                <a href="{{ route('application.timeline', $app) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="far fa-eye me-1"></i>{{ __('View') }}
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
                                                        <button type="button" class="btn btn-sm btn-primary mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#feeModal{{ $app->id }}">
                                                            <i class="fas fa-upload me-1"></i>{{ __('Upload Payment Receipt') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Receipt upload modal --}}
                                        <div class="modal fade" id="feeModal{{ $app->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('application.admission-fee.upload', $app) }}" method="post" enctype="multipart/form-data">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('Upload Payment Receipt') }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-2">
                                                                <label class="form-label">{{ __('Payment Date') }} <span class="text-danger">*</span></label>
                                                                <input type="date" name="payment_date" class="form-control" max="{{ date('Y-m-d') }}" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">{{ __('Amount') }} <span class="text-danger">*</span></label>
                                                                <input type="number" step="0.01" name="amount" class="form-control" value="{{ $balance }}" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">{{ __('Payment Reference') }} <span class="text-danger">*</span></label>
                                                                <input type="text" name="payment_reference" class="form-control" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">{{ __('Payment Method') }} <span class="text-danger">*</span></label>
                                                                <select name="payment_method" class="form-control" required>
                                                                    <option value="4">{{ __('Bank Transfer') }}</option>
                                                                    <option value="2">{{ __('Cash') }}</option>
                                                                    <option value="3">{{ __('Cheque') }}</option>
                                                                    <option value="1">{{ __('Card') }}</option>
                                                                    <option value="5">{{ __('E-wallet') }}</option>
                                                                    <option value="6">{{ __('Other') }}</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">{{ __('Receipt File') }} (PDF/JPG/PNG) <span class="text-danger">*</span></label>
                                                                <input type="file" name="receipt_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                                            </div>
                                                            <div class="mb-0">
                                                                <label class="form-label">{{ __('Note (optional)') }}</label>
                                                                <textarea name="student_note" class="form-control" rows="2"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                                            <button type="submit" class="btn btn-primary">{{ __('Submit Receipt') }}</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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
        <div class="portal-card p-4 mb-4">
            <div class="border-top border-3 border-danger mb-3"></div>
            <h5>{{ __('Need Help?') }}</h5>
            <p class="mb-2 text-muted">{{ __('Contact the Admissions Office') }}</p>
            @if(!empty($setting->email))
                <p class="mb-1"><i class="far fa-envelope me-2 text-muted"></i>{{ $setting->email }}</p>
            @endif
            @if(!empty($setting->phone))
                <p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i>{{ $setting->phone }}</p>
            @endif
            <a href="{{ route('application.create') }}" class="btn btn-danger w-100 mt-3">
                <i class="fas fa-plus me-1"></i>{{ __('Start a New Application') }}
            </a>
        </div>
    </div>
</div>
@endsection
