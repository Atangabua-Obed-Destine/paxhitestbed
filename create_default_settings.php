<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Setting;

echo "Checking settings...\n";

$setting = Setting::first();

if (!$setting) {
    echo "No settings found. Creating default settings...\n";
    
    $setting = Setting::create([
        'title' => 'Your School Name',
        'address' => 'School Address',
        'phone' => 'Phone Number',
        'email' => 'school@example.com',
        'date_format' => 'd-m-Y',
        'status' => 1,
    ]);
    
    echo "✅ Default settings created!\n";
    echo "Please update the settings in your admin panel.\n";
} else {
    echo "✅ Settings already exist:\n";
    echo "  Title: " . $setting->title . "\n";
}
