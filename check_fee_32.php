<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Fee #32 ===\n\n";

$fee = \App\Models\Fee::find(32);

if (!$fee) {
    echo "Fee #32 not found!\n";
    exit;
}

echo "Fee Details:\n";
echo "  - ID: {$fee->id}\n";
echo "  - Status: {$fee->status} (0=pending, 1=paid, 2=cancelled)\n";
echo "  - Amount: {$fee->fee_amount}\n";
echo "  - Paid Amount: {$fee->paid_amount}\n";
echo "  - Student Enroll ID: {$fee->student_enroll_id}\n";

$enrollment = $fee->studentEnroll;
if ($enrollment) {
    echo "\nEnrollment Details:\n";
    echo "  - Student ID: {$enrollment->student_id}\n";
    echo "  - Program ID: {$enrollment->program_id}\n";
    echo "  - Session ID: {$enrollment->session_id}\n";
    echo "  - Semester ID: {$enrollment->semester_id}\n";
    echo "  - Program: " . ($enrollment->program->title ?? 'N/A') . "\n";
}

$resitRequest = $fee->resitRequest;
if ($resitRequest) {
    echo "\nLinked Resit Request:\n";
    echo "  - Request ID: {$resitRequest->id}\n";
    echo "  - State: {$resitRequest->workflow_state}\n";
    echo "  - Payment Status: {$resitRequest->payment_status}\n";
    echo "  - Resit Session ID: " . ($resitRequest->resit_session_id ?? 'NOT SET') . "\n";
    echo "  - Resit Semester ID: " . ($resitRequest->resit_semester_id ?? 'NOT SET') . "\n";
    echo "  - Subject: " . ($resitRequest->subject->code ?? 'N/A') . " - " . ($resitRequest->subject->title ?? 'N/A') . "\n";
} else {
    echo "\n⚠️  WARNING: No resit request linked to this fee!\n";
}

$receipts = $fee->paymentReceipts;
echo "\nPayment Receipts: " . $receipts->count() . "\n";
foreach ($receipts as $receipt) {
    echo "  - Receipt #{$receipt->id}: Status={$receipt->verification_status}, Amount={$receipt->amount}\n";
}

echo "\n=== Done ===\n";
