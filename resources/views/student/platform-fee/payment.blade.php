@extends('student.layouts.master')
@section('title', $platformSetting->title ?? 'Platform Access Fee')

@section('content')
<style>
    .payment-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .welcome-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 40px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        animation: slideInDown 0.6s ease-out;
    }
    
    .welcome-card h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 15px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }
    
    .welcome-card p {
        font-size: 1.1rem;
        opacity: 0.95;
        line-height: 1.6;
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
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        margin-bottom: 25px;
        animation: pulse 2s ease-in-out infinite;
    }
    
    .fee-amount-box h2 {
        font-size: 3rem;
        font-weight: 700;
        margin: 10px 0;
    }
    
    .fee-amount-box p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 0;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .info-item {
        background: #f7fafc;
        padding: 20px;
        border-radius: 10px;
        border-left: 4px solid #667eea;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .info-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .info-item-label {
        font-size: 0.85rem;
        color: #718096;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .info-item-value {
        font-size: 1.1rem;
        color: #2d3748;
        font-weight: 600;
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
        .welcome-card h1 {
            font-size: 1.8rem;
        }
        
        .fee-amount-box h2 {
            font-size: 2.2rem;
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
        <p>{{ $platformSetting->welcome_message }}</p>
    </div>

    @if(session('success'))
    <div class="alert alert-success-custom">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    @if(session('warning'))
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

            @if($platformSetting->payment_instructions)
            <div class="alert-info-custom">
                <strong><i class="fas fa-info-circle"></i> Payment Instructions:</strong><br>
                {{ $platformSetting->payment_instructions }}
            </div>
            @endif
        </div>
    </div>

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
});
</script>
@endsection
