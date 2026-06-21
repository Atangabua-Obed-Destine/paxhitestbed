<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING PARTIAL PAYMENT FUNCTIONALITY ===\n\n";

// Find a fee with payments
$fee = \App\Models\Fee::with(['paymentReceipts', 'approvedReceipts', 'category', 'studentEnroll.student'])
    ->whereHas('paymentReceipts')
    ->first();

if (!$fee) {
    echo "No fees with payment receipts found.\n";
    exit;
}

echo "Fee ID: {$fee->id}\n";
echo "Student: {$fee->studentEnroll->student->first_name} {$fee->studentEnroll->student->last_name}\n";
echo "Category: {$fee->category->title}\n";
echo str_repeat('-', 80) . "\n\n";

echo "PAYMENT BREAKDOWN:\n";
echo "  Fee Amount:       " . number_format($fee->fee_amount, 2) . "\n";
echo "  Fine Amount:      " . number_format($fee->fine_amount, 2) . "\n";
echo "  Discount Amount:  " . number_format($fee->discount_amount, 2) . "\n";
echo "  " . str_repeat('-', 50) . "\n";
echo "  Total Amount:     " . number_format($fee->total_amount, 2) . "\n";
echo "  Paid Amount:      " . number_format($fee->paid_amount, 2) . " (" . ($fee->paid_amount > 0 ? 'text-success' : 'text-danger') . ")\n";
echo "  " . str_repeat('-', 50) . "\n";
echo "  Remaining Balance: " . number_format($fee->remaining_balance, 2) . " (" . ($fee->remaining_balance > 0 ? 'text-danger' : 'text-success') . ")\n\n";

echo "PAYMENT STATUS:\n";
if ($fee->isFullyPaid()) {
    echo "  ✓ FULLY PAID (Status: {$fee->status})\n";
} elseif ($fee->isPartiallyPaid()) {
    echo "  ⚠ PARTIALLY PAID (Status: {$fee->status})\n";
} else {
    echo "  ✗ UNPAID (Status: {$fee->status})\n";
}

echo "\nStatus Badge: {$fee->status_badge}\n\n";

echo "PAYMENT HISTORY:\n";
$approvedReceipts = $fee->approvedReceipts;
if ($approvedReceipts->count() > 0) {
    echo "  Found {$approvedReceipts->count()} approved receipt(s):\n\n";
    foreach ($approvedReceipts as $receipt) {
        echo "  Receipt #{$receipt->id}\n";
        echo "    Date:   " . date('M d, Y', strtotime($receipt->payment_date)) . "\n";
        echo "    Amount: " . number_format($receipt->amount, 2) . "\n";
        echo "    Status: {$receipt->verification_status}\n";
        echo "\n";
    }
    
    $totalPaid = $approvedReceipts->sum('amount');
    echo "  Total Paid via Receipts: " . number_format($totalPaid, 2) . "\n";
} else {
    echo "  No approved receipts yet.\n";
}

echo "\nPENDING RECEIPTS:\n";
$pendingReceipts = $fee->paymentReceipts()->where('verification_status', 'pending')->get();
if ($pendingReceipts->count() > 0) {
    echo "  Found {$pendingReceipts->count()} pending receipt(s):\n";
    foreach ($pendingReceipts as $receipt) {
        echo "    Receipt #{$receipt->id} - Amount: " . number_format($receipt->amount, 2) . " (Waiting for approval)\n";
    }
} else {
    echo "  No pending receipts.\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "TEST COMPLETE\n";
