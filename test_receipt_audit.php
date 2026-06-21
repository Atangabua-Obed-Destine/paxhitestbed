<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING PAYMENT RECEIPT AUDIT DESCRIPTION ===\n\n";

// Find a payment receipt
$receipt = \App\Models\PaymentReceipt::first();

if (!$receipt) {
    echo "No payment receipts found.\n";
    exit;
}

echo "Receipt ID: {$receipt->id}\n";
echo "Student ID: {$receipt->student_id}\n\n";

// Check student relationship
echo "Checking student relationship:\n";
if ($receipt->student_id) {
    $student = \App\Models\Student::find($receipt->student_id);
    if ($student) {
        echo "  ✓ Student found: ID {$student->id}\n";
        echo "  - First Name: {$student->first_name}\n";
        echo "  - Last Name: {$student->last_name}\n";
    } else {
        echo "  ✗ Student not found\n";
    }
}

echo "\n--- Testing getAuditDescription ---\n";
$description = $receipt->getAuditDescription('created');
echo "Created: {$description}\n";

$description = $receipt->getAuditDescription('updated');
echo "Updated: {$description}\n";

echo "\n=== TEST COMPLETE ===\n";
