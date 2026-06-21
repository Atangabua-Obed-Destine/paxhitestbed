<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$fee = App\Models\Fee::find(45);
if (!$fee) {
    echo "Fee 45 not found\n";
    exit;
}

echo "Fee #45:\n";
echo "  fee_amount: " . $fee->fee_amount . "\n";
echo "  fine_amount: " . $fee->fine_amount . "\n";
echo "  discount_amount: " . $fee->discount_amount . "\n";
echo "  paid_amount: " . $fee->paid_amount . "\n";
echo "  status: " . $fee->status . "\n";
echo "  total_amount (accessor): " . $fee->total_amount . "\n";
echo "  remaining_balance (accessor): " . $fee->remaining_balance . "\n";

// Check the report view - what does it show?
echo "\nReport would show remaining_balance = total_amount - paid_amount = " . ($fee->total_amount - $fee->paid_amount) . "\n";

// Check if there's a discount that was applied
echo "\nNet = fee_amount + fine - discount = " . $fee->fee_amount . " + " . $fee->fine_amount . " - " . $fee->discount_amount . " = " . ($fee->fee_amount + $fee->fine_amount - $fee->discount_amount) . "\n";
