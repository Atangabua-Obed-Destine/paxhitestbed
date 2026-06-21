<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Fee;

$fee = Fee::where('student_enroll_id', 89)->latest()->first();

if ($fee) {
    echo "Fee Details:\n";
    echo "============\n";
    echo "ID: {$fee->id}\n";
    echo "Fee Amount: {$fee->fee_amount}\n";
    echo "Paid Amount: {$fee->paid_amount}\n";
    echo "Discount: {$fee->discount_amount}\n";
    echo "Fine: {$fee->fine_amount}\n";
    echo "Status: {$fee->status}\n";
    echo "Pay Date: " . ($fee->pay_date ?? 'NULL') . "\n";
    echo "Note: {$fee->note}\n";
    echo "\n";
    
    // Check how status is determined
    echo "Status Logic Check:\n";
    echo "===================\n";
    echo "fee_amount: {$fee->fee_amount}\n";
    echo "paid_amount: {$fee->paid_amount}\n";
    echo "Is fully paid? " . ($fee->paid_amount >= $fee->fee_amount ? 'YES' : 'NO') . "\n";
} else {
    echo "No fee found for enrollment 89\n";
}
