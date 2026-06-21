<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\IdCardSetting;

try {
    $setting = new IdCardSetting();
    $setting->slug = 'staff-id-card';
    $setting->title = 'Staff ID Card';
    $setting->prefix = 'STAFF-';
    $setting->status = 1;
    $setting->save();
    
    echo "✓ Staff ID Card setting created successfully!\n";
    echo "ID: " . $setting->id . "\n";
    echo "Slug: " . $setting->slug . "\n";
    echo "Title: " . $setting->title . "\n";
    echo "Status: Active\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
