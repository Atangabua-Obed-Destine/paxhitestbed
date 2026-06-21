<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PaymentReceipt;
use App\Models\Fee;
use App\Models\Student;

echo "=== MANUAL PAYMENT WORKFLOW TEST ===\n\n";

// 1. Check existing payment receipts
echo "1. Current Payment Receipts:\n";
$receipts = PaymentReceipt::with(['student', 'fee'])->get();
foreach ($receipts as $receipt) {
    echo "   - Receipt #{$receipt->id}: Status={$receipt->verification_status}, ";
    echo "Fee ID={$receipt->fee_id}, Student ID={$receipt->student_id}, Amount={$receipt->amount}\n";
}
echo "\n";

// 2. Check if corresponding fee was updated
if ($receipts->count() > 0) {
    $firstReceipt = $receipts->first();
    echo "2. Checking Fee #{$firstReceipt->fee_id} status:\n";
    $fee = Fee::find($firstReceipt->fee_id);
    if ($fee) {
        echo "   - Fee Status: " . ($fee->status == 0 ? 'Unpaid' : ($fee->status == 1 ? 'Paid' : 'Cancelled')) . "\n";
        echo "   - Paid Amount: {$fee->paid_amount}\n";
        echo "   - Payment Method: {$fee->payment_method}\n";
        echo "   - Note: {$fee->note}\n";
    }
    echo "\n";
}

// 3. Check available unpaid fees for testing
echo "3. Available Unpaid Fees:\n";
$unpaidFees = Fee::where('status', 0)->take(3)->get();
foreach ($unpaidFees as $fee) {
    echo "   - Fee #{$fee->id}: Amount={$fee->fee_amount}, Student Enroll ID={$fee->student_enroll_id}\n";
}
echo "\n";

// 4. Check audit trail entries
echo "4. Audit Trail Entries (payment_receipts):\n";
try {
    $audits = DB::table('audits')
        ->where('auditable_type', 'App\\Models\\PaymentReceipt')
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get(['id', 'event', 'auditable_id', 'user_id', 'created_at']);
    
    if ($audits->count() > 0) {
        foreach ($audits as $audit) {
            echo "   - Audit #{$audit->id}: Event={$audit->event}, Receipt ID={$audit->auditable_id}, User ID={$audit->user_id}, Date={$audit->created_at}\n";
        }
    } else {
        echo "   - No audit entries found for PaymentReceipt\n";
    }
} catch (Exception $e) {
    echo "   - Error checking audits: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== TEST COMPLETE ===\n";
