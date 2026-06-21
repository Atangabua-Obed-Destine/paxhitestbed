<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Update the existing exemption for Social Security (ID 7) for user 3
$exemption = \App\Models\StaffTaxExemption::where('user_id', 3)
                                         ->where('tax_setting_id', 7)
                                         ->first();

if($exemption) {
    $exemption->custom_fixed_amount = 900.00;
    $exemption->save();
    echo "Updated Social Security exemption to 900.00 XAF\n";
} else {
    echo "No exemption found for Social Security\n";
}

// Check if there's an exemption for Personal Income Tax (ID 6)
$exemption2 = \App\Models\StaffTaxExemption::where('user_id', 3)
                                          ->where('tax_setting_id', 6)
                                          ->first();

if($exemption2) {
    $exemption2->custom_fixed_amount = 700.00;
    $exemption2->save();
    echo "Updated Personal Income Tax exemption to 700.00 XAF\n";
} else {
    // Create new exemption for Personal Income Tax
    \App\Models\StaffTaxExemption::create([
        'tax_setting_id' => 6,
        'user_id' => 3,
        'reason' => '',
        'custom_fixed_amount' => 700.00,
    ]);
    echo "Created Personal Income Tax exemption with 700.00 XAF\n";
}

echo "\nDone! Refresh the payroll page to see the updated calculation.\n";
