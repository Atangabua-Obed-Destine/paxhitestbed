<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check staff tax exemptions
$exemptions = \App\Models\StaffTaxExemption::with('taxSetting')->where('user_id', 3)->get();

echo "Staff Tax Exemptions for User ID 3:\n";
echo "=====================================\n\n";

foreach($exemptions as $exemption) {
    echo "Exemption ID: " . $exemption->id . "\n";
    echo "Tax Setting ID: " . $exemption->tax_setting_id . "\n";
    echo "Tax Title: " . ($exemption->taxSetting->title ?? 'N/A') . "\n";
    echo "Tax Type: " . ($exemption->taxSetting->tax_type ?? 'N/A') . " (1=percentage, 2=fixed)\n";
    echo "Custom Percentage: " . ($exemption->custom_percentage ?? 'NULL') . "\n";
    echo "Custom Fixed Amount: " . ($exemption->custom_fixed_amount ?? 'NULL') . "\n";
    echo "Reason: " . $exemption->reason . "\n";
    echo "---\n\n";
}

echo "\n\nAll Tax Settings:\n";
echo "=====================================\n\n";
$taxSettings = \App\Models\TaxSetting::where('status', '1')->get();
foreach($taxSettings as $tax) {
    echo "ID: " . $tax->id . "\n";
    echo "Title: " . $tax->title . "\n";
    echo "Tax Type: " . $tax->tax_type . " (1=percentage, 2=fixed)\n";
    echo "Percentage: " . ($tax->percentange ?? 'N/A') . "%\n";
    echo "Fixed Amount: " . ($tax->fixed_amount ?? 'N/A') . "\n";
    echo "Range: " . $tax->min_amount . " - " . $tax->max_amount . "\n";
    echo "---\n\n";
}
