<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "Program ID 1 exists: " . (\App\Models\Program::find(1) ? 'Yes' : 'No') . "\n";
echo "Province ID 1 exists: " . (\App\Models\Province::find(1) ? 'Yes' : 'No') . "\n";
echo "District ID 1 exists: " . (\App\Models\District::find(1) ? 'Yes' : 'No') . "\n";
