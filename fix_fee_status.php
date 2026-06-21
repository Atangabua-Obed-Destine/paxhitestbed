<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Fee;

echo "Fixing auto-assigned fee status...\n\n";

// Find the fee we created
$fee = Fee::where('student_enroll_id', 89)
    ->where('note', 'LIKE', '%assigned%')
    ->first();

if ($fee) {
    echo "Found fee ID: {$fee->id}\n";
    echo "Current status: {$fee->status}\n";
    echo "Paid amount: {$fee->paid_amount}\n\n";
    
    if ($fee->status == 1 && $fee->paid_amount == 0) {
        $fee->status = 0; // Set to Unpaid
        $fee->save();
        
        echo "✓ Fixed! Status updated to 0 (Unpaid)\n";
    } else {
        echo "ℹ️  Status is already correct or fee is actually paid\n";
    }
} else {
    echo "❌ Fee not found\n";
}

echo "\nDone!\n";
