<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING PARTIAL PAYMENT REPORT ACCESS ===\n\n";

try {
    // Simulate a request to the controller
    $controller = new \App\Http\Controllers\Admin\PartialPaymentReportController();
    $request = new \Illuminate\Http\Request();
    
    echo "1. Attempting to call index() method...\n";
    $response = $controller->index($request);
    
    echo "   ✓ Method executed successfully!\n";
    echo "   Response type: " . get_class($response) . "\n";
    
    if ($response instanceof \Illuminate\View\View) {
        echo "   ✓ View returned: " . $response->name() . "\n";
        echo "   ✓ View data keys: " . implode(', ', array_keys($response->getData())) . "\n";
    }
    
    echo "\n✅ Controller works fine! Issue must be with authentication or session.\n";
    echo "\nRECOMMENDATION:\n";
    echo "1. Make sure you're logged in as admin\n";
    echo "2. Try clearing browser cache and cookies\n";
    echo "3. Check if your admin session is still valid\n";
    echo "4. Try logging out and logging back in\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR FOUND:\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    
    if (strpos($e->getMessage(), 'program') !== false) {
        echo "⚠️  ISSUE: Missing 'program' relationship on StudentEnroll!\n";
        echo "\nFIX NEEDED: Add program relationship to StudentEnroll model\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
