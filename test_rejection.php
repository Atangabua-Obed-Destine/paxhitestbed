<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PaymentReceipt;
use App\Models\Fee;
use Illuminate\Support\Facades\Auth;

echo "=== TESTING REJECTION WORKFLOW ===\n\n";

// Simulate admin login (user ID 1)
Auth::loginUsingId(1);

// Find a pending receipt or create one for testing
$pendingReceipt = PaymentReceipt::where('verification_status', 'pending')->first();

if (!$pendingReceipt) {
    echo "No pending receipts found. Let's check if we can simulate rejection:\n";
    
    // Get an unpaid fee
    $unpaidFee = Fee::where('status', 0)->first();
    
    if ($unpaidFee) {
        echo "Found unpaid Fee #{$unpaidFee->id}\n";
        echo "To test rejection, a student would need to:\n";
        echo "1. Upload a receipt via: /student/manual-payment/create/{$unpaidFee->id}\n";
        echo "2. Admin would then reject it via: /admin/payment-verification/{receipt_id}\n\n";
    }
    
    echo "Skipping rejection test - no pending receipts.\n";
} else {
    echo "Found pending receipt: #{$pendingReceipt->id}\n";
    echo "Status: {$pendingReceipt->verification_status}\n";
    echo "Fee ID: {$pendingReceipt->fee_id}\n\n";
    
    echo "Testing rejection...\n";
    $pendingReceipt->update([
        'verification_status' => 'rejected',
        'verification_note' => 'Test rejection - receipt is unclear',
        'verified_by' => Auth::id(),
        'verified_at' => now(),
    ]);
    
    echo "✓ Receipt rejected successfully\n";
    echo "Status: {$pendingReceipt->verification_status}\n";
    echo "Note: {$pendingReceipt->verification_note}\n";
    echo "Verified by: User #{$pendingReceipt->verified_by}\n\n";
    
    // Check that fee is still unpaid
    $fee = Fee::find($pendingReceipt->fee_id);
    echo "Fee #{$fee->id} status: " . ($fee->status == 0 ? 'Unpaid ✓' : 'Paid (should be unpaid!)') . "\n";
    echo "Student can now re-upload receipt\n";
}

echo "\n=== TEST COMPLETE ===\n";
