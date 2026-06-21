<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing ADMISSION_FEE_ENABLED:\n";
echo "Raw env() value: '" . env('ADMISSION_FEE_ENABLED', 'not set') . "'\n";
echo "Type: " . gettype(env('ADMISSION_FEE_ENABLED')) . "\n";

$value = env('ADMISSION_FEE_ENABLED', 'true');
echo "Comparison === 'true': " . ($value === 'true' ? 'true' : 'false') . "\n";
echo "Comparison == 'true': " . ($value == 'true' ? 'true' : 'false') . "\n";

$isEnabled = in_array(strtolower($value), ['true', '1', 'yes', 'on']);
echo "Using in_array check: " . ($isEnabled ? 'ENABLED' : 'DISABLED') . "\n";
