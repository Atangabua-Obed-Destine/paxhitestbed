@extends('student.layouts.master')
@section('title', $platformSetting->title ?? 'Platform Access Fee')

@section('content')
<style>
    .payment-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    /* Kept deliberately small. This page exists so a student can pay; the
       welcome is context, not the task, and on a phone a full-height greeting
       pushed the payment steps off the screen entirely. */
    .welcome-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 18px;
        box-shadow: 0 6px 18px rgba(102, 126, 234, 0.25);
        animation: slideInDown 0.6s ease-out;
    }

    .welcome-card h1 {
        font-size: 1.35rem;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .welcome-card p {
        font-size: .92rem;
        opacity: .95;
        line-height: 1.5;
        margin-bottom: 0;
    }

    /* A long welcome is clamped to three lines with a "read more" toggle, so
       the length of the school's message cannot bury the payment steps. */
    .welcome-message {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .welcome-message.is-open {
        display: block;
    }

    .welcome-toggle {
        background: rgba(255, 255, 255, .18);
        border: 0;
        color: #fff;
        font-size: .78rem;
        font-weight: 600;
        border-radius: 20px;
        padding: 3px 12px;
        margin-top: 8px;
        cursor: pointer;
    }
    
    .payment-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        margin-bottom: 25px;
        overflow: hidden;
        animation: fadeInUp 0.6s ease-out;
        animation-delay: 0.2s;
        animation-fill-mode: both;
    }
    
    .card-header-custom {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        padding: 20px 30px;
        border-bottom: 3px solid #667eea;
    }
    
    .card-header-custom h3 {
        margin: 0;
        color: #2d3748;
        font-weight: 600;
        display: flex;
        align-items: center;
    }
    
    .card-header-custom h3 i {
        margin-right: 10px;
        color: #667eea;
    }
    
    .card-body-custom {
        padding: 30px;
    }
    
    .fee-amount-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 14px 18px;
        border-radius: 10px;
        text-align: center;
        margin-bottom: 14px;
    }

    .fee-amount-box h2 {
        font-size: 1.9rem;
        font-weight: 700;
        margin: 2px 0;
    }

    .fee-amount-box p {
        font-size: .82rem;
        opacity: .9;
        margin: 0;
    }

    /* Rows, not cards. Each of these was a full-width panel with its own border
       and hover lift, so four facts the student already knows filled a phone
       screen on their own. */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 0 18px;
        margin-bottom: 14px;
        background: #f7fafc;
        border-radius: 10px;
        padding: 6px 14px;
    }

    .info-item {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 0;
        border-bottom: 1px solid #edf2f7;
    }

    .info-item:last-child {
        border-bottom: 0;
    }

    .info-item-label {
        font-size: .68rem;
        color: #718096;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .5px;
        white-space: nowrap;
    }

    .info-item-value {
        font-size: .88rem;
        color: #2d3748;
        font-weight: 600;
        text-align: right;
    }
    
    .status-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        animation: bounceIn 0.6s ease-out;
    }
    
    .status-pending {
        background: #fef3c7;
        color: #92400e;
        border: 2px solid #f59e0b;
    }
    
    .status-approved {
        background: #d1fae5;
        color: #065f46;
        border: 2px solid #10b981;
    }
    
    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
        border: 2px solid #ef4444;
    }
    
    .upload-area {
        border: 3px dashed #cbd5e0;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        background: #f7fafc;
    }
    
    .upload-area:hover {
        border-color: #667eea;
        background: #edf2f7;
        transform: scale(1.02);
    }
    
    .upload-area i {
        font-size: 3rem;
        color: #667eea;
        margin-bottom: 15px;
    }
    
    .upload-area.dragover {
        border-color: #667eea;
        background: #edf2f7;
        transform: scale(1.05);
    }
    
    .btn-primary-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }
    
    .alert-custom {
        border-radius: 10px;
        border: none;
        padding: 20px;
        margin-bottom: 20px;
        animation: slideInLeft 0.5s ease-out;
    }
    
    /* The three steps: one obvious thing to do at each point. */
    .pay-step {
        display: flex;
        gap: 14px;
        padding-bottom: 20px;
        margin-bottom: 20px;
        border-bottom: 1px solid #eef0f4;
    }

    .pay-step--last {
        border-bottom: 0;
        padding-bottom: 0;
        margin-bottom: 0;
    }

    .pay-step-number {
        flex: 0 0 34px;
        height: 34px;
        border-radius: 50%;
        background: #667eea;
        color: #fff;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pay-step-body h5 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    /* The one button the student is meant to press. */
    .btn-dial {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: #fff;
        font-weight: 700;
        font-size: 17px;
        padding: 14px 26px;
        border-radius: 10px;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(17, 153, 142, .35);
    }

    .btn-dial:hover,
    .btn-dial:focus {
        color: #fff;
        filter: brightness(1.05);
        text-decoration: none;
    }

    .dial-fallback {
        margin-top: 12px;
        font-size: 13px;
        color: #6c757d;
    }

    .dial-fallback code {
        font-size: 15px;
        font-weight: 700;
        color: #333;
        background: #f1f3f7;
        padding: 3px 8px;
        border-radius: 5px;
    }

    .reference-box {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        background: #fff8e1;
        border: 1px dashed #f0ad4e;
        border-radius: 10px;
        padding: 12px 16px;
    }

    .reference-label {
        font-size: 11px;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #8a6d3b;
    }

    .reference-value {
        font-size: 19px;
        font-weight: 800;
        letter-spacing: .04em;
        color: #333;
    }

    .btn-copy {
        border: 1px solid #ced4da;
        background: #fff;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 12px;
        color: #495057;
        cursor: pointer;
    }

    .btn-copy.copied {
        border-color: #28a745;
        color: #28a745;
    }

    /* The first thing on the page once a receipt exists. */
    .status-banner {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        border-radius: 12px;
        padding: 16px 18px;
        margin-bottom: 18px;
        border-left: 6px solid;
        background: #fff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .07);
    }

    .status-banner i {
        font-size: 1.6rem;
        margin-top: 2px;
    }

    .status-banner h4 {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 4px;
    }

    .status-banner p {
        margin: 0 0 4px;
        font-size: .9rem;
        color: #4a5568;
    }

    .status-banner small {
        font-size: .78rem;
        color: #718096;
    }

    .status-banner.is-pending {
        border-left-color: #f0ad4e;
    }

    .status-banner.is-pending i,
    .status-banner.is-pending h4 {
        color: #b9770e;
    }

    .status-banner.is-approved {
        border-left-color: #28a745;
    }

    .status-banner.is-approved i,
    .status-banner.is-approved h4 {
        color: #1e7e34;
    }

    .status-banner.is-rejected {
        border-left-color: #dc3545;
    }

    .status-banner.is-rejected i,
    .status-banner.is-rejected h4 {
        color: #c82333;
    }

    /* Confirmation after an upload: a receipt leaving the phone is the moment a
       student most needs telling that it arrived. */
    .upload-done-icon {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: #eaf7ef;
        color: #28a745;
        font-size: 2.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
    }

    .school-instructions {
        margin-top: 18px;
        border-top: 1px solid #eef0f4;
        padding-top: 14px;
    }

    .school-instructions summary {
        cursor: pointer;
        font-size: .85rem;
        font-weight: 600;
        color: #667eea;
        list-style: none;
    }

    .school-instructions summary::-webkit-details-marker {
        display: none;
    }

    .school-instructions summary::after {
        content: ' ▾';
    }

    .school-instructions[open] summary::after {
        content: ' ▴';
    }

    .school-instructions-body {
        margin-top: 10px;
        font-size: .85rem;
        color: #6c757d;
        white-space: pre-line;
        line-height: 1.6;
    }

    .alert-info-custom {
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        color: #075985;
        border-left: 4px solid #0284c7;
    }
    
    .alert-warning-custom {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
        border-left: 4px solid #f59e0b;
    }
    
    .alert-success-custom {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border-left: 4px solid #10b981;
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }
    
    @keyframes bounceIn {
        0% {
            opacity: 0;
            transform: scale(0.3);
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @media (max-width: 768px) {
        .welcome-card {
            padding: 14px 16px;
        }

        .welcome-card h1 {
            font-size: 1.15rem;
        }

        .fee-amount-box h2 {
            font-size: 1.7rem;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .card-body-custom {
            padding: 20px;
        }
    }
</style>

<div class="payment-container">
    <!-- Welcome Header -->
    <div class="welcome-card">
        <h1><i class="fas fa-graduation-cap"></i> {{ $platformSetting->title }}</h1>
        @if($platformSetting->welcome_message)
            <p class="welcome-message" id="welcomeMessage">{{ $platformSetting->welcome_message }}</p>
            {{-- Only offered when the message is long enough to be clamped;
                 the script hides this button otherwise. --}}
            <button type="button" class="welcome-toggle" id="welcomeToggle" style="display: none;">
                {{ __('Read more') }}
            </button>
        @endif
    </div>

    {{-- Where the student stands, before anything else on the page. The status
         used to sit below the fee details and the payment steps, so a student
         who had already uploaded a receipt met the instructions to pay first
         and reasonably concluded nothing had been received. --}}
    @if($payment)
        @php
            $statusMap = [
                'pending'  => ['class' => 'is-pending',  'icon' => 'fa-hourglass-half',
                               'title' => __('Your receipt is with the school'),
                               'body'  => __('It is being checked. You do not need to pay again or upload anything else — your portal opens as soon as it is approved.')],
                'approved' => ['class' => 'is-approved', 'icon' => 'fa-check-circle',
                               'title' => __('Payment approved'),
                               'body'  => __('You are paid up for this academic year. Nothing further is needed.')],
                'rejected' => ['class' => 'is-rejected', 'icon' => 'fa-times-circle',
                               'title' => __('Your receipt could not be accepted'),
                               'body'  => __('Please read the reason below and upload a clearer or correct receipt.')],
            ];
            $state = $statusMap[$payment->status] ?? $statusMap['pending'];
        @endphp

        <div class="status-banner {{ $state['class'] }}" id="statusBanner">
            <i class="fas {{ $state['icon'] }}"></i>
            <div>
                <h4>{{ $state['title'] }}</h4>
                <p>{{ $state['body'] }}</p>
                <small>
                    {{ __('Submitted') }} {{ $payment->created_at?->format('j M Y') }}
                    @if($payment->status === 'rejected' && $payment->admin_note)
                        — <strong>{{ __('Reason') }}:</strong> {{ $payment->admin_note }}
                    @endif
                </small>
            </div>
        </div>
    @endif

    @if(session('warning') && !$payment)
    <div class="alert alert-warning-custom">
        <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
    </div>
    @endif

    <!-- Fee Amount Card -->
    <div class="payment-card">
        <div class="card-header-custom">
            <h3><i class="fas fa-money-bill-wave"></i> Fee Details</h3>
        </div>
        <div class="card-body-custom">
            <div class="fee-amount-box">
                <p>Amount Payable</p>
                <h2>
                    @if(isset($systemSetting->decimal_place))
                    {{ number_format($platformSetting->fee_amount, $systemSetting->decimal_place, '.', ',') }}
                    @else
                    {{ number_format($platformSetting->fee_amount, 2, '.', ',') }}
                    @endif
                    {!! $systemSetting->currency_symbol !!}
                </h2>
                <p>One-time Platform Access Fee</p>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label">Student Name</div>
                    <div class="info-item-value">{{ $student->first_name }} {{ $student->last_name }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Matricule</div>
                    <div class="info-item-value">
                        <strong style="font-size: 15px; color: #667eea;">{{ $currentEnrollment->matricule ?? $student->student_id ?? 'N/A' }}</strong>
                        @if($currentEnrollment && $currentEnrollment->program)
                            <span class="badge" style="background: {{ $currentEnrollment->program->academic_level == 'M' ? '#f5576c' : ($currentEnrollment->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px; margin-left: 5px;">
                                {{ $currentEnrollment->program->academic_level == 'A' ? 'UG' : ($currentEnrollment->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Program</div>
                    <div class="info-item-value">{{ $currentEnrollment->program->title ?? 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Academic Session</div>
                    <div class="info-item-value">{{ $currentEnrollment->session->title ?? 'N/A' }}</div>
                </div>
            </div>

        </div>
    </div>

    @php
        $matricule = $currentEnrollment->matricule ?? $student->student_id ?? null;
    @endphp

    @php
        // Only show someone how to pay when there is still a payment to make.
        // A student waiting on verification who is shown "How to pay" and a big
        // Pay button reasonably concludes their receipt never arrived — and
        // some will pay a second time.
        $stillToPay = !$payment || $payment->status === 'rejected';
    @endphp

    @if($stillToPay)
    {{-- Three numbered steps. A student who reads nothing else on this page
         should still be able to pay by following 1, 2, 3 in order. --}}
    <div class="payment-card">
        <div class="card-header-custom">
            <h3><i class="fas fa-list-ol"></i> {{ __('How to pay — 3 steps') }}</h3>
        </div>
        <div class="card-body-custom">

            <div class="pay-step">
                <div class="pay-step-number">1</div>
                <div class="pay-step-body">
                    <h5>{{ __('Dial the payment code') }}</h5>

                    @if($platformSetting->hasDialCode())
                        <p class="text-muted mb-2">
                            {{ __('Tap the button on the phone you pay with. Your dialler opens with the code already filled in — you do not type anything.') }}
                        </p>

                        <a href="{{ $platformSetting->dialLink() }}" class="btn-dial" data-no-loading>
                            <i class="fas fa-phone-alt"></i>
                            {{ __('Pay') }}
                            {{ number_format($platformSetting->fee_amount, 0, '.', ',') }}
                            {!! $systemSetting->currency_symbol !!}
                            {{ __('now') }}
                        </a>

                        <div class="dial-fallback">
                            {{ __('If the button does nothing, dial this yourself:') }}
                            <code id="dial-code">{{ $platformSetting->dialCode() }}</code>
                            <button type="button" class="btn-copy" data-copy="{{ $platformSetting->dialCode() }}">
                                <i class="far fa-copy"></i> {{ __('Copy') }}
                            </button>
                        </div>

                        @if($platformSetting->merchant_name)
                            <p class="small text-muted mt-2 mb-0">
                                <i class="fas fa-shield-alt"></i>
                                {{ __('Before entering your PIN, check your phone shows') }}
                                <strong>{{ $platformSetting->merchant_name }}</strong>.
                                {{ __('If it shows a different name, stop and tell the school.') }}
                            </p>
                        @endif
                    @else
                        <p class="text-muted mb-2">
                            {{ __('Pay the amount above using the instructions from the school.') }}
                        </p>
                        @if($platformSetting->merchant_number)
                            <p class="mb-0">
                                {{ __('Pay to') }}:
                                <strong>{{ $platformSetting->merchant_number }}</strong>
                                @if($platformSetting->merchant_name)
                                    ({{ $platformSetting->merchant_name }})
                                @endif
                            </p>
                        @endif
                    @endif
                </div>
            </div>

            <div class="pay-step">
                <div class="pay-step-number">2</div>
                <div class="pay-step-body">
                    <h5>{{ __('Use your matricule as the reference') }}</h5>
                    <p class="text-muted mb-2">
                        {{ __('When the payment asks for a reason, reference or description, enter your matricule exactly as it appears here. It is how the school finds your payment and confirms it is yours.') }}
                    </p>

                    @if($matricule)
                        <div class="reference-box">
                            <span class="reference-label">{{ __('Your reference') }}</span>
                            <span class="reference-value" id="matricule-value">{{ $matricule }}</span>
                            <button type="button" class="btn-copy" data-copy="{{ $matricule }}">
                                <i class="far fa-copy"></i> {{ __('Copy') }}
                            </button>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            <i class="fas fa-exclamation-circle"></i>
                            {{ __('Without your matricule the school may not be able to match the payment to you, and verifying it will take longer.') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="pay-step pay-step--last">
                <div class="pay-step-number">3</div>
                <div class="pay-step-body">
                    <h5>{{ __('Send the proof') }}</h5>
                    <p class="text-muted mb-0">
                        {{ __('Take a screenshot of the confirmation message, or photograph the receipt, and upload it below. The school checks it and your portal opens — you only pay this once for the whole academic year.') }}
                    </p>
                </div>
            </div>

            @if($platformSetting->payment_instructions)
                {{-- The school's own wording, kept but folded away: it is long,
                     and a student following the three steps above does not need
                     to read it first. --}}
                <details class="school-instructions">
                    <summary>
                        <i class="fas fa-info-circle"></i>
                        {{ __('Full instructions from the school') }}
                    </summary>
                    <div class="school-instructions-body">{{ $platformSetting->payment_instructions }}</div>
                </details>
            @endif

        </div>
    </div>
    @endif

    <!-- Payment Status or Upload Form -->
    @if($payment)
        <!-- Existing Payment Status -->
        <div class="payment-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-file-invoice"></i> Payment Status</h3>
            </div>
            <div class="card-body-custom">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-item-label">Payment Date</div>
                        <div class="info-item-value">{{ date('F j, Y', strtotime($payment->payment_date)) }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Amount Paid</div>
                        <div class="info-item-value">
                            @if(isset($systemSetting->decimal_place))
                            {{ number_format($payment->paid_amount, $systemSetting->decimal_place, '.', ',') }}
                            @else
                            {{ number_format($payment->paid_amount, 2, '.', ',') }}
                            @endif
                            {!! $systemSetting->currency_symbol !!}
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Verification Status</div>
                        <div class="info-item-value">
                            @if($payment->status == 'pending')
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> Pending Verification
                                </span>
                            @elseif($payment->status == 'approved')
                                <span class="status-badge status-approved">
                                    <i class="fas fa-check-circle"></i> Approved
                                </span>
                            @elseif($payment->status == 'rejected')
                                <span class="status-badge status-rejected">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            @endif
                        </div>
                    </div>
                    @if($payment->receipt_path)
                    <div class="info-item">
                        <div class="info-item-label">Receipt</div>
                        <div class="info-item-value">
                            <a href="{{ asset('uploads/platform-fees/' . $payment->receipt_path) }}" target="_blank" class="btn btn-sm btn-primary-custom">
                                <i class="fas fa-eye"></i> View Receipt
                            </a>
                        </div>
                    </div>
                    @endif
                </div>

                @if($payment->student_note)
                <div class="alert-info-custom mt-3">
                    <strong><i class="fas fa-comment"></i> Your Note:</strong><br>
                    {{ $payment->student_note }}
                </div>
                @endif

                @if($payment->status == 'pending')
                <div class="alert-warning-custom mt-3">
                    <i class="fas fa-hourglass-half"></i> <strong>Awaiting Verification</strong><br>
                    Your payment receipt is under review by the administration. You will be notified once verified.
                </div>
                @elseif($payment->status == 'approved')
                <div class="alert-success-custom mt-3">
                    <i class="fas fa-check-double"></i> <strong>Payment Verified!</strong><br>
                    Your platform access fee has been approved. You can now access all portal features.
                    @if($payment->admin_note)
                        <br><small>Admin Note: {{ $payment->admin_note }}</small>
                    @endif
                </div>
                @elseif($payment->status == 'rejected')
                <div class="alert alert-danger" style="border-radius: 10px;">
                    <h5><i class="fas fa-exclamation-circle"></i> Payment Rejected</h5>
                    @if($payment->admin_note)
                    <p><strong>Reason:</strong> {{ $payment->admin_note }}</p>
                    @endif
                    <p>Please upload a new payment receipt below.</p>
                </div>
                @endif

                @if($payment->status == 'rejected')
                <!-- Show upload form again if rejected -->
                <hr class="my-4">
                <h5 class="mb-3"><i class="fas fa-upload"></i> Reupload Payment Receipt</h5>
                @include('student.platform-fee.partials.upload-form')
                @endif
            </div>
        </div>
    @else
        <!-- Upload Form for New Payment -->
        <div class="payment-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-upload"></i> Upload Payment Receipt</h3>
            </div>
            <div class="card-body-custom">
                @include('student.platform-fee.partials.upload-form')
            </div>
        </div>
    @endif
</div>

{{-- Confirmation after an upload. A flash message above the fold was easy to
     scroll past, and a student who had just sent their only proof of payment
     was left unsure whether it had gone. --}}
<div class="modal fade" id="uploadDoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: 0;">
            <div class="modal-body text-center p-4">
                <div class="upload-done-icon"><i class="fas fa-check"></i></div>

                <h4 class="mb-2" style="font-weight: 700;">{{ __('Receipt received') }}</h4>

                <p class="text-muted mb-3">
                    {{ session('success') ?: __('Your payment receipt has been sent to the school.') }}
                </p>

                <div class="alert alert-light border text-start mb-3" style="border-radius: 10px;">
                    <strong class="d-block mb-1">{{ __('What happens next') }}</strong>
                    <span class="text-muted" style="font-size: .9rem;">
                        {{ __('The school checks your receipt and approves it. Your portal opens as soon as that is done — you do not need to pay again or upload anything else.') }}
                    </span>
                </div>

                <button type="button" class="btn btn-primary-custom w-100" data-bs-dismiss="modal">
                    {{ __('Got it') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('receipt');
    const fileNameDisplay = document.getElementById('fileName');

    if (uploadArea && fileInput) {
        // Click to upload
        uploadArea.addEventListener('click', () => fileInput.click());

        // File input change
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileNameDisplay.textContent = this.files[0].name;
                fileNameDisplay.style.color = '#667eea';
            }
        });

        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            }, false);
        });

        uploadArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files && files[0]) {
                fileInput.files = files;
                fileNameDisplay.textContent = files[0].name;
                fileNameDisplay.style.color = '#667eea';
            }
        }, false);
    }

    @if(session('success'))
        // Shown once, after an upload. Bootstrap 5 dropped the jQuery plugin,
        // so the native API is what this version answers to.
        var doneModal = document.getElementById('uploadDoneModal');

        if (doneModal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(doneModal).show();

            // Leave them looking at their status, not at the pay instructions.
            doneModal.addEventListener('hidden.bs.modal', function () {
                var banner = document.getElementById('statusBanner');

                if (banner) {
                    banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    @endif

    // The welcome is clamped to three lines. Offer to expand it only when there
    // is actually more to read, so the button never appears over a short one.
    var welcome = document.getElementById('welcomeMessage');
    var welcomeToggle = document.getElementById('welcomeToggle');

    if (welcome && welcomeToggle) {
        if (welcome.scrollHeight - welcome.clientHeight > 4) {
            welcomeToggle.style.display = 'inline-block';
        }

        welcomeToggle.addEventListener('click', function () {
            var open = welcome.classList.toggle('is-open');
            welcomeToggle.textContent = open
                ? '{{ __('Show less') }}'
                : '{{ __('Read more') }}';
        });
    }

    // Copy the shortcode or the matricule. A student typing their own matricule
    // into a payment reference is exactly where a typo costs them a verified
    // payment, so the page offers to do it for them.
    document.querySelectorAll('.btn-copy').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = button.getAttribute('data-copy') || '';
            var original = button.innerHTML;

            var done = function () {
                button.innerHTML = '<i class="fas fa-check"></i> {{ __('Copied') }}';
                button.classList.add('copied');
                setTimeout(function () {
                    button.innerHTML = original;
                    button.classList.remove('copied');
                }, 1800);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done).catch(fallback);
            } else {
                fallback();
            }

            // http:// and older browsers have no clipboard API; this still works.
            function fallback() {
                var field = document.createElement('textarea');
                field.value = text;
                field.setAttribute('readonly', '');
                field.style.position = 'absolute';
                field.style.left = '-9999px';
                document.body.appendChild(field);
                field.select();

                try {
                    document.execCommand('copy');
                    done();
                } catch (error) {
                    // Nothing to do but leave the text on screen to read.
                }

                document.body.removeChild(field);
            }
        });
    });
});
</script>
@endsection
