<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CREATING PARTIAL PAYMENT TEST SCENARIO ===\n\n";

// Find or create a test fee
$fee = \App\Models\Fee::where('status', 0)->first();

if (!$fee) {
    echo "No unpaid fees found to test with.\n";
    exit;
}

// Reset the fee to unpaid for testing
$fee->update([
    'status' => 0,
    'paid_amount' => 0,
    'pay_date' => null,
    'note' => 'Reset for partial payment testing'
]);

echo "✓ Test fee prepared: Fee #{$fee->id}\n";
echo "  Student: {$fee->studentEnroll->student->first_name} {$fee->studentEnroll->student->last_name}\n";
echo "  Category: {$fee->category->title}\n";
echo "  Total Amount: " . number_format($fee->total_amount, 2) . "\n\n";

// Simulate first partial payment
echo "SCENARIO 1: First Partial Payment (40% of total)\n";
echo str_repeat('-', 80) . "\n";

$firstPayment = $fee->total_amount * 0.4;
echo "Creating payment receipt for: " . number_format($firstPayment, 2) . "\n";

$receipt1 = \App\Models\PaymentReceipt::create([
    'fee_id' => $fee->id,
    'student_id' => $fee->studentEnroll->student_id,
    'receipt_file' => 'test-receipt-1.jpg',
    'payment_reference' => 'TEST-REF-001',
    'payment_date' => now(),
    'amount' => $firstPayment,
    'payment_method' => 2,
    'student_note' => 'First partial payment - testing',
    'verification_status' => 'pending',
]);

echo "✓ Receipt #{$receipt1->id} created (Status: pending)\n";

// Simulate admin approval
$receipt1->update([
    'verification_status' => 'approved',
    'verified_by' => 1,
    'verified_at' => now(),
    'verification_note' => 'Approved - First partial payment'
]);

// Update fee
$newPaidAmount = $fee->paid_amount + $receipt1->amount;
$totalDue = $fee->total_amount;

if ($newPaidAmount >= $totalDue) {
    $status = 1; // Fully Paid
} elseif ($newPaidAmount > 0) {
    $status = 2; // Partially Paid
} else {
    $status = 0; // Unpaid
}

$fee->update([
    'status' => $status,
    'paid_amount' => $newPaidAmount,
    'pay_date' => $receipt1->payment_date,
    'payment_method' => $receipt1->payment_method,
    'note' => 'Payment via receipt #' . $receipt1->id,
]);

$fee->refresh();

echo "✓ Fee updated:\n";
echo "  Status: " . ($status == 2 ? 'PARTIALLY PAID' : 'UNPAID') . " ({$status})\n";
echo "  Paid Amount: " . number_format($fee->paid_amount, 2) . "\n";
echo "  Remaining Balance: " . number_format($fee->remaining_balance, 2) . "\n\n";

// Simulate second partial payment
echo "SCENARIO 2: Second Partial Payment (30% of total)\n";
echo str_repeat('-', 80) . "\n";

$secondPayment = $fee->total_amount * 0.3;
echo "Creating payment receipt for: " . number_format($secondPayment, 2) . "\n";

$receipt2 = \App\Models\PaymentReceipt::create([
    'fee_id' => $fee->id,
    'student_id' => $fee->studentEnroll->student_id,
    'receipt_file' => 'test-receipt-2.jpg',
    'payment_reference' => 'TEST-REF-002',
    'payment_date' => now()->addDays(1),
    'amount' => $secondPayment,
    'payment_method' => 4,
    'student_note' => 'Second partial payment - testing',
    'verification_status' => 'pending',
]);

echo "✓ Receipt #{$receipt2->id} created (Status: pending)\n";

// Simulate admin approval
$receipt2->update([
    'verification_status' => 'approved',
    'verified_by' => 1,
    'verified_at' => now(),
    'verification_note' => 'Approved - Second partial payment'
]);

// Update fee
$newPaidAmount = $fee->paid_amount + $receipt2->amount;

if ($newPaidAmount >= $totalDue) {
    $status = 1; // Fully Paid
} elseif ($newPaidAmount > 0) {
    $status = 2; // Partially Paid
} else {
    $status = 0; // Unpaid
}

$approvedReceipts = $fee->paymentReceipts()->where('verification_status', 'approved')->get();
$receiptIds = $approvedReceipts->pluck('id')->toArray();

$fee->update([
    'status' => $status,
    'paid_amount' => $newPaidAmount,
    'pay_date' => $receipt2->payment_date,
    'payment_method' => $receipt2->payment_method,
    'note' => 'Payments via receipts: #' . implode(', #', $receiptIds) . ' (Total: ' . $newPaidAmount . '/' . $totalDue . ')',
]);

$fee->refresh();

echo "✓ Fee updated:\n";
echo "  Status: " . ($status == 2 ? 'PARTIALLY PAID' : ($status == 1 ? 'FULLY PAID' : 'UNPAID')) . " ({$status})\n";
echo "  Paid Amount: " . number_format($fee->paid_amount, 2) . "\n";
echo "  Remaining Balance: " . number_format($fee->remaining_balance, 2) . "\n\n";

// Simulate final payment
echo "SCENARIO 3: Final Payment (Remaining 30%)\n";
echo str_repeat('-', 80) . "\n";

$finalPayment = $fee->remaining_balance;
echo "Creating payment receipt for: " . number_format($finalPayment, 2) . "\n";

$receipt3 = \App\Models\PaymentReceipt::create([
    'fee_id' => $fee->id,
    'student_id' => $fee->studentEnroll->student_id,
    'receipt_file' => 'test-receipt-3.jpg',
    'payment_reference' => 'TEST-REF-003',
    'payment_date' => now()->addDays(2),
    'amount' => $finalPayment,
    'payment_method' => 5,
    'student_note' => 'Final payment - completing fee',
    'verification_status' => 'pending',
]);

echo "✓ Receipt #{$receipt3->id} created (Status: pending)\n";

// Simulate admin approval
$receipt3->update([
    'verification_status' => 'approved',
    'verified_by' => 1,
    'verified_at' => now(),
    'verification_note' => 'Approved - Final payment, fee now fully paid'
]);

// Update fee
$newPaidAmount = $fee->paid_amount + $receipt3->amount;

if ($newPaidAmount >= $totalDue) {
    $status = 1; // Fully Paid
} elseif ($newPaidAmount > 0) {
    $status = 2; // Partially Paid
} else {
    $status = 0; // Unpaid
}

$approvedReceipts = $fee->paymentReceipts()->where('verification_status', 'approved')->get();
$receiptIds = $approvedReceipts->pluck('id')->toArray();

$fee->update([
    'status' => $status,
    'paid_amount' => $newPaidAmount,
    'pay_date' => $receipt3->payment_date,
    'payment_method' => $receipt3->payment_method,
    'note' => 'Payments via receipts: #' . implode(', #', $receiptIds) . ' (Total: ' . $newPaidAmount . '/' . $totalDue . ')',
]);

$fee->refresh();

echo "✓ Fee updated:\n";
echo "  Status: " . ($status == 1 ? '✓ FULLY PAID' : 'PARTIALLY PAID') . " ({$status})\n";
echo "  Paid Amount: " . number_format($fee->paid_amount, 2) . "\n";
echo "  Remaining Balance: " . number_format($fee->remaining_balance, 2) . "\n\n";

// Summary
echo str_repeat('=', 80) . "\n";
echo "FINAL SUMMARY\n";
echo str_repeat('=', 80) . "\n\n";

echo "Fee #{$fee->id} Payment History:\n";
echo "  Total Amount Due:  " . number_format($fee->total_amount, 2) . "\n";
echo "  Total Paid:        " . number_format($fee->paid_amount, 2) . "\n";
echo "  Remaining:         " . number_format($fee->remaining_balance, 2) . "\n";
echo "  Status:            " . strip_tags($fee->status_badge) . "\n\n";

echo "Payment Receipts:\n";
$approvedReceipts = $fee->approvedReceipts;
foreach ($approvedReceipts as $idx => $receipt) {
    echo "  " . ($idx + 1) . ". Receipt #{$receipt->id}\n";
    echo "     Date:   " . $receipt->payment_date->format('M d, Y') . "\n";
    echo "     Amount: " . number_format($receipt->amount, 2) . "\n";
    echo "     Ref:    {$receipt->payment_reference}\n";
    echo "     Status: {$receipt->verification_status}\n\n";
}

echo "✓ Partial payment workflow test completed successfully!\n";
echo "\nYou can now view:\n";
echo "  - Student Portal: Manual Payment page\n";
echo "  - Admin Portal: Payment Verification > Fee #{$fee->id}\n";
