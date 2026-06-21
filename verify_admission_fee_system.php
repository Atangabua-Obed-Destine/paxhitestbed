<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ADMISSION FEE SYSTEM VERIFICATION ===\n\n";

// 1. Check environment configuration
echo "1. Environment Configuration:\n";
echo "   ADMISSION_FEE_ENABLED: " . env('ADMISSION_FEE_ENABLED', 'not set') . "\n";
echo "   ADMISSION_FEE_AMOUNT: " . env('ADMISSION_FEE_AMOUNT', 'not set') . "\n";
echo "   ADMISSION_FEE_DUE_DAYS: " . env('ADMISSION_FEE_DUE_DAYS', 'not set') . "\n\n";

// 2. Check admission fee category
echo "2. Admission Fee Category:\n";
$admissionCategory = \App\Models\FeesCategory::where('is_admission', 1)->where('status', 1)->first();
if ($admissionCategory) {
    echo "   ✓ Category: {$admissionCategory->title} (ID: {$admissionCategory->id})\n\n";
} else {
    echo "   ✗ No admission fee category configured\n\n";
}

// 3. Check applications with admission fees
echo "3. Applications with Admission Fees:\n";
$applicationsWithFees = \App\Models\Application::whereNotNull('admission_fee_id')->count();
echo "   Total: {$applicationsWithFees}\n";

if ($applicationsWithFees > 0) {
    $paidFees = \App\Models\Application::whereNotNull('admission_fee_id')
        ->whereHas('admissionFee', function($q) {
            $q->where('status', 1);
        })->count();
    
    $unpaidFees = \App\Models\Application::whereNotNull('admission_fee_id')
        ->whereHas('admissionFee', function($q) {
            $q->where('status', 0);
        })->count();
    
    echo "   Paid: {$paidFees}\n";
    echo "   Unpaid: {$unpaidFees}\n\n";
}

// 4. Check payment receipts
echo "4. Payment Receipts:\n";
$totalReceipts = \App\Models\PaymentReceipt::count();
$pendingReceipts = \App\Models\PaymentReceipt::where('verification_status', 'pending')->count();
$approvedReceipts = \App\Models\PaymentReceipt::where('verification_status', 'approved')->count();
$rejectedReceipts = \App\Models\PaymentReceipt::where('verification_status', 'rejected')->count();

echo "   Total: {$totalReceipts}\n";
echo "   Pending: {$pendingReceipts}\n";
echo "   Approved: {$approvedReceipts}\n";
echo "   Rejected: {$rejectedReceipts}\n\n";

// 5. Sample application details
echo "5. Sample Application (Latest with Fee):\n";
$sampleApp = \App\Models\Application::with('admissionFee.paymentReceipts')
    ->whereNotNull('admission_fee_id')
    ->latest()
    ->first();

if ($sampleApp) {
    echo "   Registration No: {$sampleApp->registration_no}\n";
    echo "   Name: {$sampleApp->first_name} {$sampleApp->last_name}\n";
    echo "   Status: " . ($sampleApp->status == 1 ? 'Pending' : ($sampleApp->status == 2 ? 'Approved' : 'Rejected')) . "\n";
    
    if ($sampleApp->admissionFee) {
        $fee = $sampleApp->admissionFee;
        echo "   Fee Amount: {$fee->fee_amount}\n";
        echo "   Paid Amount: {$fee->paid_amount}\n";
        echo "   Balance: {$fee->remaining_balance}\n";
        echo "   Fee Status: " . ($fee->status == 1 ? 'Paid' : 'Unpaid') . "\n";
        
        $pendingCount = $fee->paymentReceipts()->where('verification_status', 'pending')->count();
        echo "   Pending Receipts: {$pendingCount}\n";
    }
} else {
    echo "   No applications with fees found\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
