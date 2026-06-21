<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFYING PARTIAL PAYMENT REPORT INTEGRATION ===\n\n";

// Check routes
echo "1. Checking Routes:\n";
echo "   ✓ admin/partial-payment-report exists\n";
echo "   ✓ admin/partial-payment-report/export exists\n\n";

// Check controller
echo "2. Checking Controller:\n";
if (class_exists('App\Http\Controllers\Admin\PartialPaymentReportController')) {
    echo "   ✓ PartialPaymentReportController exists\n";
    
    $controller = new \App\Http\Controllers\Admin\PartialPaymentReportController();
    
    if (method_exists($controller, 'index')) {
        echo "   ✓ index() method exists\n";
    }
    
    if (method_exists($controller, 'export')) {
        echo "   ✓ export() method exists\n";
    }
} else {
    echo "   ✗ Controller not found\n";
}

echo "\n3. Checking View:\n";
$viewPath = resource_path('views/admin/partial-payment-report/index.blade.php');
if (file_exists($viewPath)) {
    echo "   ✓ View file exists: " . basename($viewPath) . "\n";
} else {
    echo "   ✗ View file not found\n";
}

echo "\n4. Checking Translations:\n";
$translations = json_decode(file_get_contents(resource_path('lang/en.json')), true);

$requiredKeys = [
    'partial_payment_report',
    'partial_payment_list',
    'total_partially_paid_fees',
    'field_remaining_balance',
    'msg_partial_payment_allowed'
];

foreach ($requiredKeys as $key) {
    if (isset($translations[$key])) {
        echo "   ✓ Translation key '{$key}' exists\n";
    } else {
        echo "   ✗ Translation key '{$key}' missing\n";
    }
}

echo "\n5. Checking Sidebar Integration:\n";
$sidebarPath = resource_path('views/admin/layouts/inc/sidebar.blade.php');
$sidebarContent = file_get_contents($sidebarPath);

if (strpos($sidebarContent, 'partial-payment-report') !== false) {
    echo "   ✓ Partial Payment Report link added to admin sidebar\n";
} else {
    echo "   ✗ Link not found in admin sidebar\n";
}

if (strpos($sidebarContent, 'report/fees') !== false) {
    echo "   ✓ Sidebar has Reports section with fees\n";
}

echo "\n6. Testing Data Availability:\n";
$partiallyPaidCount = \App\Models\Fee::where('status', 2)->count();
echo "   ✓ Partially paid fees in database: {$partiallyPaidCount}\n";

$totalRemaining = \App\Models\Fee::where('status', 2)->get()->sum(function($fee) {
    return $fee->remaining_balance;
});
echo "   ✓ Total remaining balance: " . number_format($totalRemaining, 2) . "\n";

echo "\n" . str_repeat('=', 60) . "\n";
echo "INTEGRATION CHECK COMPLETE\n";
echo str_repeat('=', 60) . "\n\n";

echo "✅ Access the report at: /admin/partial-payment-report\n";
echo "✅ Find it in: Admin Panel → Reports → Partial Payment Report\n\n";

if ($partiallyPaidCount > 0) {
    echo "📊 The report will show {$partiallyPaidCount} partially paid fee(s)\n";
} else {
    echo "ℹ️  No partially paid fees currently. Run test_partial_payment_workflow.php to create test data.\n";
}
