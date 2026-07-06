@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-md-12 col-lg-11">
                <form class="needs-validation" novalidate action="{{ route($route.'.update') }}" method="post">
                @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ $title }}</h5>
                            <small class="text-muted d-block mt-1">
                                {{ __('Configure MTN Mobile Money and Orange Money for automated admission-fee payments. Values are stored in the .env file — the same as the other payment gateways.') }}
                            </small>
                        </div>
                        <div class="card-block">

                            <ul class="nav nav-tabs mb-3" role="tablist">
                                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-mtn" role="tab">{{ __('MTN Mobile Money') }}</a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-orange" role="tab">{{ __('Orange Money') }}</a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-shared" role="tab">{{ __('Shared Settings') }}</a></li>
                            </ul>

                            <div class="tab-content">

                                {{-- ============================================================
                                     MTN
                                     ============================================================ --}}
                                <div class="tab-pane fade show active" id="tab-mtn" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group form-check mt-2">
                                                <input type="checkbox" class="form-check-input" name="mtn_enabled" id="mtn_enabled" value="1" {{ $mtn['enabled'] ? 'checked' : '' }}>
                                                <label class="form-check-label" for="mtn_enabled">{{ __('Enable MTN Mobile Money') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Environment') }}</label>
                                                <select class="form-control" name="mtn_environment">
                                                    <option value="sandbox" @if($mtn['environment'] === 'sandbox') selected @endif>Sandbox</option>
                                                    <option value="production" @if($mtn['environment'] === 'production') selected @endif>Production</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Target Environment') }}</label>
                                                <select class="form-control" name="mtn_target_environment">
                                                    <option value="sandbox" @if($mtn['target_environment'] === 'sandbox') selected @endif>sandbox</option>
                                                    <option value="mtncameroon" @if($mtn['target_environment'] === 'mtncameroon') selected @endif>mtncameroon (production Cameroon)</option>
                                                </select>
                                                <small class="text-muted">{{ __('MTN provides this per country after KYC.') }}</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">{{ __('Base URL') }}</label>
                                        <input type="text" class="form-control" name="mtn_base_url" value="{{ $mtn['base_url'] }}" placeholder="https://sandbox.momodeveloper.mtn.com">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">{{ __('Collections Subscription Key') }} <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="mtn_subscription_key" value="{{ $mtn['subscription_key'] }}" autocomplete="new-password">
                                        <small class="text-muted">{{ __('From your Collections product subscription on momodeveloper.mtn.com.') }}</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('API User (UUID)') }}</label>
                                                <input type="text" class="form-control" name="mtn_api_user" value="{{ $mtn['api_user'] }}" autocomplete="off" placeholder="00000000-0000-0000-0000-000000000000">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('API Key') }}</label>
                                                <input type="password" class="form-control" name="mtn_api_key" value="{{ $mtn['api_key'] }}" autocomplete="new-password">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">{{ __('Callback Host (no scheme)') }}</label>
                                        <input type="text" class="form-control" name="mtn_callback_host" value="{{ $mtn['callback_host'] }}" placeholder="your-domain.com">
                                        <small class="text-muted">{{ __('Leave blank for localhost / polling-only. If set, MTN will POST to') }} <code>https://{host}/payment/momo/mtn/webhook</code>.</small>
                                    </div>

                                    <div class="alert alert-info">
                                        <strong>{{ __('Sandbox helper:') }}</strong>
                                        {{ __('Save your Subscription Key first, then click below to auto-generate an API User + API Key from MTN sandbox.') }}
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-info" id="mtn-provision-btn">
                                                <i class="fas fa-magic me-1"></i>{{ __('Provision MTN Sandbox Credentials') }}
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-momo-test="mtn">
                                                <i class="fas fa-plug me-1"></i>{{ __('Test MTN Connection') }}
                                            </button>
                                        </div>
                                        <div id="mtn-test-result" class="mt-2 small"></div>
                                    </div>
                                </div>

                                {{-- ============================================================
                                     ORANGE
                                     ============================================================ --}}
                                <div class="tab-pane fade" id="tab-orange" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group form-check mt-2">
                                                <input type="checkbox" class="form-check-input" name="orange_enabled" id="orange_enabled" value="1" {{ $orange['enabled'] ? 'checked' : '' }}>
                                                <label class="form-check-label" for="orange_enabled">{{ __('Enable Orange Money') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Environment') }}</label>
                                                <select class="form-control" name="orange_environment">
                                                    <option value="sandbox" @if($orange['environment'] === 'sandbox') selected @endif>Sandbox</option>
                                                    <option value="production" @if($orange['environment'] === 'production') selected @endif>Production</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">{{ __('Base URL') }}</label>
                                        <input type="text" class="form-control" name="orange_base_url" value="{{ $orange['base_url'] }}" placeholder="https://api.orange.com">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Client ID') }}</label>
                                                <input type="text" class="form-control" name="orange_client_id" value="{{ $orange['client_id'] }}" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Client Secret') }}</label>
                                                <input type="password" class="form-control" name="orange_client_secret" value="{{ $orange['client_secret'] }}" autocomplete="new-password">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">{{ __('Merchant Key') }}</label>
                                        <input type="password" class="form-control" name="orange_merchant_key" value="{{ $orange['merchant_key'] }}" autocomplete="new-password">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">{{ __('Callback Host (no scheme)') }}</label>
                                        <input type="text" class="form-control" name="orange_callback_host" value="{{ $orange['callback_host'] }}" placeholder="your-domain.com">
                                        <small class="text-muted">{{ __('Orange posts payment completion to') }} <code>https://{host}/payment/momo/orange/webhook</code>.</small>
                                    </div>

                                    <div class="alert alert-info">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-momo-test="orange">
                                            <i class="fas fa-plug me-1"></i>{{ __('Test Orange Connection') }}
                                        </button>
                                        <div id="orange-test-result" class="mt-2 small"></div>
                                    </div>
                                </div>

                                {{-- ============================================================
                                     SHARED
                                     ============================================================ --}}
                                <div class="tab-pane fade" id="tab-shared" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Currency') }}</label>
                                                <input type="text" class="form-control" name="momo_currency" value="{{ $shared['currency'] }}" maxlength="3" placeholder="XAF">
                                                <small class="text-muted">{{ __('XAF for Cameroon. EUR works in MTN sandbox.') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Poll Timeout (s)') }}</label>
                                                <input type="number" min="10" max="600" class="form-control" name="momo_poll_timeout" value="{{ $shared['poll_timeout'] }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('Poll Interval (s)') }}</label>
                                                <input type="number" min="1" max="30" class="form-control" name="momo_poll_interval" value="{{ $shared['poll_interval'] }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('HTTP Timeout (s)') }}</label>
                                                <input type="number" min="3" max="120" class="form-control" name="momo_http_timeout" value="{{ $shared['http_timeout'] }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>{{ __('Save Configuration') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    document.querySelectorAll('[data-momo-test]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const provider = btn.dataset.momoTest;
            const out = document.getElementById(provider + '-test-result');
            out.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> {{ __("Testing...") }}</span>';
            btn.disabled = true;
            fetch('{{ url("admin/mobile-money-config/test") }}/' + provider, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            }).then(r => r.json()).then(function (data) {
                btn.disabled = false;
                const cls = data.ok ? 'text-success' : 'text-danger';
                const icon = data.ok ? 'check-circle' : 'times-circle';
                out.innerHTML = '<span class="' + cls + '"><i class="fas fa-' + icon + '"></i> ' + (data.message || '') + '</span>';
            }).catch(function () {
                btn.disabled = false;
                out.innerHTML = '<span class="text-danger">{{ __("Request failed.") }}</span>';
            });
        });
    });

    const provisionBtn = document.getElementById('mtn-provision-btn');
    if (provisionBtn) {
        provisionBtn.addEventListener('click', function () {
            if (!confirm('{{ __("This will call MTN sandbox to create a new API user + key, and overwrite the current values. Continue?") }}')) return;
            const out = document.getElementById('mtn-test-result');
            const host = (document.querySelector('[name=mtn_callback_host]').value || 'webhook.site').trim();
            out.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> {{ __("Provisioning...") }}</span>';
            provisionBtn.disabled = true;
            fetch('{{ url("admin/mobile-money-config/provision-mtn-sandbox") }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
                body: JSON.stringify({host: host}),
            }).then(r => r.json()).then(function (data) {
                provisionBtn.disabled = false;
                if (data.ok) {
                    document.querySelector('[name=mtn_api_user]').value = data.api_user || '';
                    document.querySelector('[name=mtn_api_key]').value = data.api_key || '';
                    out.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ' + data.message + '</span>';
                } else {
                    out.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> ' + data.message + '</span>';
                }
            }).catch(function () {
                provisionBtn.disabled = false;
                out.innerHTML = '<span class="text-danger">{{ __("Provisioning failed.") }}</span>';
            });
        });
    }
})();
</script>
@endpush
