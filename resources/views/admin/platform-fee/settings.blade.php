@extends('admin.layouts.master')
@section('title', 'Platform Fee Settings')

@section('content')
<style>
    .settings-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px 15px 0 0;
        margin: -20px -20px 0 -20px;
    }
    
    .settings-container {
        background: white;
        border-radius: 0 0 15px 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        padding: 30px;
        margin: 0 -20px -20px -20px;
    }
    
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .slider {
        background-color: #10b981;
    }
    
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        padding: 12px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .btn-save {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 40px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }
    
    .info-card {
        background: #f8f9fa;
        border-left: 4px solid #667eea;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
</style>

<div class="content-header row">
</div>

<div class="content-body">
    <section id="platform-fee-settings">
        <div class="card">
            <div class="settings-header">
                <h2 class="mb-0"><i class="fas fa-cog"></i> Platform Fee Settings</h2>
                <p class="mb-0 mt-2" style="opacity: 0.9;">Configure one-time platform access fee for students per academic session</p>
            </div>
            
            <div class="settings-container">
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form action="{{ route('admin.platform-fee.settings.update') }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="info-card">
                                <h5><i class="fas fa-lightbulb text-warning"></i> About Platform Fee</h5>
                                <p class="mb-0">This fee is charged once per student per academic session to grant access to the student portal. Students cannot access their dashboard until payment is verified.</p>
                            </div>
                        </div>

                        <!-- Enable/Disable Toggle -->
                        <div class="col-md-12 mb-4">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1"><i class="fas fa-power-off text-primary"></i> Enable Platform Fee</h5>
                                            <p class="text-muted mb-0">Turn on/off the platform fee requirement for all students</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="is_enabled" value="1" {{ $setting->is_enabled ? 'checked' : '' }}>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Online payment: the switch, and what it costs the student -->
                        <div class="col-md-12 mb-4">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1"><i class="fas fa-mobile-alt text-primary"></i> Let students pay online</h5>
                                            <p class="text-muted mb-0">
                                                Students pay by MTN Mobile Money or Orange Money through Fapshi, and the
                                                portal unlocks the moment the payment is confirmed &mdash; no screenshot,
                                                no queue.
                                            </p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="online_payment_enabled" id="online_payment_enabled" value="1" {{ $setting->online_payment_enabled ? 'checked' : '' }}>
                                            <span class="slider"></span>
                                        </label>
                                    </div>

                                    {{-- The three things that have to be true live in two different
                                         places, so the screen says which one is missing rather than
                                         leaving an admin to tick a box and wonder why nothing changed. --}}
                                    @if($gatewayBlocker)
                                        <div class="alert alert-warning mt-3 mb-0">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>Students cannot pay online right now.</strong>
                                            {{ $gatewayBlocker }}
                                            @if(!$gateway->isEnabled() || !$gateway->isConfigured())
                                                <a href="{{ route('admin.mobile-money-config.index') }}" class="alert-link">Open the gateway settings</a>.
                                            @endif
                                        </div>
                                    @else
                                        <div class="alert alert-success mt-3 mb-0">
                                            <i class="fas fa-check-circle"></i>
                                            Online payment is live, against the <strong>{{ $gateway->environment() }}</strong> gateway.
                                            @if($gateway->environment() !== 'live')
                                                No real money moves in sandbox.
                                            @endif
                                        </div>
                                    @endif

                                    <div class="alert alert-light border mt-3 mb-0">
                                        <i class="fas fa-info-circle"></i>
                                        While this is on, students pay online only &mdash; the shortcode and receipt
                                        upload are hidden. Switch it off to go straight back to manual verification;
                                        a payment already in progress will still be credited. For a one-off case,
                                        use <a href="{{ route('admin.platform-fee.exemptions') }}">Exemptions</a>.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Title -->
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="title" class="form-label">
                                    <i class="fas fa-heading"></i> Fee Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title', $setting->title) }}" required>
                                <small class="text-muted">Display name for this fee</small>
                            </div>
                        </div>

                        <!-- Fee Amount -->
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="fee_amount" class="form-label">
                                    <i class="fas fa-money-bill-wave"></i> Fee Amount <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="fee_amount" id="fee_amount" value="{{ old('fee_amount', $setting->fee_amount) }}" step="0.01" min="0" required>
                                    <span class="input-group-text">{!! $systemSetting->currency_symbol !!}</span>
                                </div>
                                <small class="text-muted">Amount to charge per session</small>
                            </div>
                        </div>

                        <!-- What the gateway takes, and therefore what the student is asked for -->
                        <div class="col-md-12 mb-3">
                            <div class="card border">
                                <div class="card-body">
                                    <h6 class="mb-1"><i class="fas fa-percent text-primary"></i> Gateway charge passed on to the student</h6>
                                    <p class="text-muted small">
                                        Fapshi deducts a commission before the money reaches the school. These two
                                        fields add it to what the student pays, so the school receives the full fee.
                                        A percentage rather than a flat figure, because a flat figure stops being
                                        right the moment the fee changes.
                                    </p>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="gateway_fee_percent" class="form-label">Percentage of the fee</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="gateway_fee_percent" id="gateway_fee_percent"
                                                           value="{{ old('gateway_fee_percent', $setting->gateway_fee_percent) }}" step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="gateway_fee_fixed" class="form-label">Plus a fixed amount</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="gateway_fee_fixed" id="gateway_fee_fixed"
                                                           value="{{ old('gateway_fee_fixed', $setting->gateway_fee_fixed) }}" step="1" min="0">
                                                    <span class="input-group-text">{!! $systemSetting->currency_symbol !!}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label">The student will be asked for</label>
                                                {{-- Recomputed as the fields change, by the same arithmetic the
                                                     server uses, so nobody has to save and guess. --}}
                                                <div class="form-control bg-light" id="charge_preview" style="font-weight: 700;">
                                                    {{ number_format($setting->chargeAmount()) }} {{ $setting->currency }}
                                                </div>
                                                <small class="text-muted" id="charge_preview_detail">
                                                    fee {{ number_format((float) $setting->fee_amount) }}
                                                    + charge {{ number_format($setting->surchargeAmount()) }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-light border mb-0 small">
                                        <i class="fas fa-flask"></i>
                                        Fapshi does not publish its rate in the API documentation, so treat this as an
                                        estimate until it is measured. <code>php artisan fapshi:reconcile</code> compares
                                        what each student paid against what actually reached the school and reports the
                                        real rate once there are live payments.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Welcome Message -->
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label for="welcome_message" class="form-label">
                                    <i class="fas fa-comment-dots"></i> Welcome Message
                                </label>
                                <textarea class="form-control" name="welcome_message" id="welcome_message" rows="3">{{ old('welcome_message', $setting->welcome_message) }}</textarea>
                                <small class="text-muted">Message displayed to students on payment page</small>
                            </div>
                        </div>

                        <!-- Payment Instructions -->
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label for="payment_instructions" class="form-label">
                                    <i class="fas fa-list-ol"></i> Payment Instructions
                                </label>
                                <textarea class="form-control" name="payment_instructions" id="payment_instructions" rows="4">{{ old('payment_instructions', $setting->payment_instructions) }}</textarea>
                                <small class="text-muted">Detailed payment instructions for students</small>
                            </div>
                        </div>

                        <!-- Mobile money shortcode: the student taps it instead of typing it -->
                        <div class="col-md-12">
                            <hr class="my-4">
                            <h5 class="mb-1"><i class="fas fa-mobile-alt"></i> {{ __('Mobile money shortcode') }}</h5>
                            <p class="text-muted small">
                                {{ __('Set this and the student gets a button that opens their phone\'s dialler with the code already filled in — no typing, no mistyped merchant number or amount. Leave it empty and they will simply see your written instructions as before.') }}
                            </p>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label for="ussd_template" class="form-label">
                                    <i class="fas fa-hashtag"></i> {{ __('Shortcode pattern') }}
                                </label>
                                <input type="text" class="form-control @error('ussd_template') is-invalid @enderror"
                                       name="ussd_template" id="ussd_template"
                                       value="{{ old('ussd_template', $setting->ussd_template) }}"
                                       placeholder="*126*4*123456*{amount}#">
                                @error('ussd_template')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">
                                    {{ __('Write your merchant number in, and put') }} <code>{amount}</code>
                                    {{ __('where the fee goes — the portal fills that in, so the amount stays right when you change the fee. Example:') }}
                                    <code>*126*4*123456*{amount}#</code>
                                </small>

                                @if($setting->hasDialCode())
                                    <div class="alert alert-success mt-2 mb-0 py-2">
                                        <i class="fas fa-check-circle"></i>
                                        {{ __('Students will be asked to dial') }}
                                        <strong><code>{{ $setting->dialCode() }}</code></strong>
                                        {{ __('for the current fee of') }}
                                        <strong>{{ number_format($setting->fee_amount, 0) }}</strong>.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="merchant_name" class="form-label">
                                    <i class="fas fa-user-check"></i> {{ __('Name the student should see') }}
                                </label>
                                <input type="text" class="form-control" name="merchant_name" id="merchant_name"
                                       value="{{ old('merchant_name', $setting->merchant_name) }}"
                                       placeholder="{{ __('e.g. PAX HIGHER INSTITUTE') }}">
                                <small class="text-muted">
                                    {{ __('Shown so the student can check the name their phone displays before entering their PIN.') }}
                                </small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="merchant_number" class="form-label">
                                    <i class="fas fa-phone"></i> {{ __('Merchant or phone number') }}
                                </label>
                                <input type="text" class="form-control" name="merchant_number" id="merchant_number"
                                       value="{{ old('merchant_number', $setting->merchant_number) }}"
                                       placeholder="{{ __('e.g. 671008494') }}">
                                <small class="text-muted">
                                    {{ __('Shown as a fallback for a student whose phone will not open the shortcode.') }}
                                </small>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-md-12 text-center mt-4">
                            <button type="submit" class="btn btn-save">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<script>
    /*
     * The same arithmetic as PlatformFeeSetting::chargeAmount(), so an admin
     * sees the figure students will be asked for before saving rather than
     * after. The server stays the authority — this only previews it.
     *
     * Rounded up, as the server rounds up: rounding down would have the school
     * absorb the remainder on every single payment.
     */
    (function () {
        var fee = document.getElementById('fee_amount');
        var percent = document.getElementById('gateway_fee_percent');
        var fixed = document.getElementById('gateway_fee_fixed');
        var total = document.getElementById('charge_preview');
        var detail = document.getElementById('charge_preview_detail');

        if (!fee || !percent || !fixed || !total) {
            return;
        }

        var currency = @json($setting->currency);

        function group(value) {
            return value.toLocaleString('en-US');
        }

        function recompute() {
            var feeValue = parseFloat(fee.value) || 0;
            var surcharge = Math.ceil(feeValue * (parseFloat(percent.value) || 0) / 100)
                + Math.ceil(parseFloat(fixed.value) || 0);
            var charge = Math.ceil(feeValue) + surcharge;

            total.textContent = group(charge) + ' ' + currency;
            detail.textContent = 'fee ' + group(Math.ceil(feeValue)) + ' + charge ' + group(surcharge);
        }

        [fee, percent, fixed].forEach(function (field) {
            field.addEventListener('input', recompute);
        });
    })();
</script>
@endsection
