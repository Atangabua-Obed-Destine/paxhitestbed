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
                                                        <button type="button" class="btn btn-sm btn-primary mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#feeModal{{ $app->id }}">
                                                            <i class="fas fa-mobile-alt me-1"></i>{{ __('Pay Admission Fee') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Admission fee payment modal --}}
                                        @php
                                            $mtnMomoEnabled = (bool) config('momo.providers.mtn.enabled');
                                            $orangeMomoEnabled = (bool) config('momo.providers.orange.enabled');
                                            $anyMomoEnabled = $mtnMomoEnabled || $orangeMomoEnabled;
                                        @endphp
                                        <div class="modal fade momo-fee-modal" id="feeModal{{ $app->id }}" tabindex="-1" aria-hidden="true"
                                             data-fee-id="{{ $fee->id }}" data-application-id="{{ $app->id }}" data-balance="{{ $balance }}">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-receipt me-2"></i>{{ __('Pay Admission Fee') }}
                                                            <span class="badge bg-light text-dark ms-2">{{ number_format($balance) }} {{ $currency }}</span>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>

                                                    <ul class="nav nav-tabs px-3 pt-3" role="tablist">
                                                        @if($mtnMomoEnabled)
                                                        <li class="nav-item">
                                                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#momoMtn{{ $app->id }}" type="button">
                                                                <i class="fas fa-mobile-alt text-warning me-1"></i>{{ __('MTN MoMo') }}
                                                            </button>
                                                        </li>
                                                        @endif
                                                        @if($orangeMomoEnabled)
                                                        <li class="nav-item">
                                                            <button class="nav-link {{ !$mtnMomoEnabled ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#momoOrange{{ $app->id }}" type="button">
                                                                <i class="fas fa-mobile-alt text-danger me-1"></i>{{ __('Orange Money') }}
                                                            </button>
                                                        </li>
                                                        @endif
                                                        <li class="nav-item">
                                                            <button class="nav-link {{ !$anyMomoEnabled ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#momoManual{{ $app->id }}" type="button">
                                                                <i class="fas fa-upload me-1"></i>{{ __('Upload Receipt') }}
                                                            </button>
                                                        </li>
                                                    </ul>

                                                    <div class="tab-content p-3">
                                                        @if($mtnMomoEnabled)
                                                        <div class="tab-pane fade show active" id="momoMtn{{ $app->id }}">
                                                            <p class="text-muted small mb-3">{{ __('Enter the MTN phone number to charge. You will receive a prompt on the phone to enter your MoMo PIN.') }}</p>
                                                            <div class="mb-2">
                                                                <label class="form-label">{{ __('MTN Phone Number') }} <span class="text-danger">*</span></label>
                                                                <input type="tel" class="form-control momo-msisdn" placeholder="670000000" required>
                                                                <small class="text-muted">{{ __('Cameroon number without country code, or full international format.') }}</small>
                                                            </div>
                                                            <div class="momo-status alert alert-info d-none mt-3" role="alert"></div>
                                                            <button type="button" class="btn btn-warning w-100 momo-pay-btn" data-provider="mtn">
                                                                <i class="fas fa-bolt me-1"></i>{{ __('Pay') }} {{ number_format($balance) }} {{ $currency }} {{ __('with MTN MoMo') }}
                                                            </button>
                                                            @if(app()->environment('local') && config('momo.providers.mtn.environment') === 'sandbox')
                                                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2 momo-devtest-btn" data-provider="mtn">
                                                                <i class="fas fa-flask me-1"></i>{{ __('DEV: Mark as Paid (bypass MTN sandbox)') }}
                                                            </button>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        @if($orangeMomoEnabled)
                                                        <div class="tab-pane fade {{ !$mtnMomoEnabled ? 'show active' : '' }}" id="momoOrange{{ $app->id }}">
                                                            <p class="text-muted small mb-3">{{ __('You will be redirected to Orange Money to complete the payment.') }}</p>
                                                            <div class="momo-status alert alert-info d-none mt-3" role="alert"></div>
                                                            <button type="button" class="btn btn-danger w-100 momo-pay-btn" data-provider="orange">
                                                                <i class="fas fa-external-link-alt me-1"></i>{{ __('Pay') }} {{ number_format($balance) }} {{ $currency }} {{ __('with Orange Money') }}
                                                            </button>
                                                        </div>
                                                        @endif

                                                        <div class="tab-pane fade {{ !$anyMomoEnabled ? 'show active' : '' }}" id="momoManual{{ $app->id }}">
                                                            <form action="{{ route('application.admission-fee.upload', $app) }}" method="post" enctype="multipart/form-data">
                                                                @csrf
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
                                                                        <option value="6">{{ __('MTN MoMo') }}</option>
                                                                        <option value="7">{{ __('Orange Money') }}</option>
                                                                        <option value="8">{{ __('Other') }}</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label class="form-label">{{ __('Receipt File') }} (PDF/JPG/PNG) <span class="text-danger">*</span></label>
                                                                    <input type="file" name="receipt_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label class="form-label">{{ __('Note (optional)') }}</label>
                                                                    <textarea name="student_note" class="form-control" rows="2"></textarea>
                                                                </div>
                                                                <button type="submit" class="btn btn-primary w-100">{{ __('Submit Receipt for Verification') }}</button>
                                                            </form>
                                                        </div>
                                                    </div>
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

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    const MOMO_BASE = '{{ url('payment/momo') }}';

    // Auto-open a fee modal when arriving with a URL hash like #feeModal123
    function openModalFromHash() {
        const hash = window.location.hash;
        if (!hash || hash.indexOf('#feeModal') !== 0) return;
        const target = document.querySelector(hash);
        if (!target || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        bootstrap.Modal.getOrCreateInstance(target).show();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openModalFromHash);
    } else {
        openModalFromHash();
    }
    window.addEventListener('hashchange', openModalFromHash);

    document.querySelectorAll('.momo-fee-modal').forEach(function (modal) {
        const feeId = modal.dataset.feeId;
        const applicationId = modal.dataset.applicationId;

        modal.querySelectorAll('.momo-devtest-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('DEV ONLY: mark this fee as paid without contacting MTN?')) return;
                const tabPane = btn.closest('.tab-pane');
                const statusBox = tabPane.querySelector('.momo-status');
                btn.disabled = true;
                showStatus(statusBox, 'info', 'Marking as paid...');
                fetch('{{ url('payment/momo/mtn/sandbox-mark-paid') }}', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},
                    body: JSON.stringify({fee_id: feeId, application_id: applicationId}),
                }).then(r => r.json()).then(function (data) {
                    if (data.ok) {
                        showStatus(statusBox, 'success', 'Marked as paid. Reloading...');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        btn.disabled = false;
                        showStatus(statusBox, 'danger', data.error || 'Failed.');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    showStatus(statusBox, 'danger', 'Request failed.');
                });
            });
        });

        modal.querySelectorAll('.momo-pay-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const provider = btn.dataset.provider;
                const tabPane = btn.closest('.tab-pane');
                const statusBox = tabPane.querySelector('.momo-status');
                const msisdnInput = tabPane.querySelector('.momo-msisdn');

                const payload = { fee_id: feeId, application_id: applicationId };
                if (provider === 'mtn') {
                    const msisdn = (msisdnInput?.value || '').trim();
                    if (!msisdn) {
                        showStatus(statusBox, 'warning', '{{ __("Please enter the phone number.") }}');
                        return;
                    }
                    payload.msisdn = msisdn;
                }

                btn.disabled = true;
                showStatus(statusBox, 'info', '{{ __("Contacting payment provider...") }}');

                fetch(MOMO_BASE + '/' + provider + '/initiate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body: JSON.stringify(payload),
                }).then(async function (r) {
                    const text = await r.text();
                    let data;
                    try { data = JSON.parse(text); }
                    catch (_) {
                        console.error('MoMo initiate non-JSON response', r.status, text);
                        throw new Error('HTTP ' + r.status + ' — ' + (text.substring(0, 200) || 'empty response'));
                    }
                    return data;
                }).then(function (data) {
                    if (!data.ok) {
                        btn.disabled = false;
                        showStatus(statusBox, 'danger', data.error || '{{ __("Unable to start payment.") }}');
                        return;
                    }
                    if (provider === 'orange' && data.payment_url) {
                        showStatus(statusBox, 'info', '{{ __("Redirecting to Orange Money...") }}');
                        window.location.href = data.payment_url;
                        return;
                    }
                    showStatus(statusBox, 'info', '{{ __("Approve the request on your phone. Waiting for confirmation...") }}');
                    pollStatus(provider, data.reference, statusBox, btn, data.poll_interval || 3, data.poll_timeout || 90);
                }).catch(function (err) {
                    btn.disabled = false;
                    console.error('MoMo initiate failed', err);
                    showStatus(statusBox, 'danger', (err && err.message) ? err.message : '{{ __("Network error. Please try again.") }}');
                });
            });
        });
    });

    function pollStatus(provider, reference, statusBox, btn, intervalSec, timeoutSec) {
        const started = Date.now();
        const tick = function () {
            fetch(MOMO_BASE + '/' + provider + '/status/' + encodeURIComponent(reference))
                .then(r => r.json()).then(function (data) {
                    if (!data.ok) { retryOrGiveUp(); return; }
                    if (data.status === 'successful') {
                        showStatus(statusBox, 'success', '{{ __("Payment successful! Reloading...") }}');
                        setTimeout(() => window.location.reload(), 1500);
                        return;
                    }
                    if (data.status === 'failed' || data.status === 'timeout') {
                        btn.disabled = false;
                        showStatus(statusBox, 'danger', (data.reason || '{{ __("Payment did not complete.") }}'));
                        return;
                    }
                    retryOrGiveUp();
                }).catch(retryOrGiveUp);
        };
        const retryOrGiveUp = function () {
            if ((Date.now() - started) / 1000 >= timeoutSec) {
                btn.disabled = false;
                showStatus(statusBox, 'warning', '{{ __("Still waiting for the provider. You can refresh this page in a moment to check again.") }}');
                return;
            }
            setTimeout(tick, intervalSec * 1000);
        };
        tick();
    }

    function showStatus(el, level, message) {
        if (!el) return;
        el.className = 'momo-status alert alert-' + level + ' mt-3';
        el.textContent = message;
    }
})();
</script>
@endpush
