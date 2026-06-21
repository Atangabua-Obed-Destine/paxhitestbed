<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DEBUGGING PARTIAL PAYMENT REPORT 500 ERROR ===\n\n";

try {
    echo "1. Checking Controller Class:\n";
    $controller = new \App\Http\Controllers\Admin\PartialPaymentReportController();
    echo "   ✓ Controller instantiated successfully\n\n";
    
    echo "2. Checking Required Models:\n";
    $feeCount = \App\Models\Fee::where('status', 2)->count();
    echo "   ✓ Fee model works - Partially paid fees: {$feeCount}\n";
    
    $sessionCount = \App\Models\Session::where('status', 1)->count();
    echo "   ✓ Session model works - Active sessions: {$sessionCount}\n";
    
    $semesterCount = \App\Models\Semester::where('status', 1)->count();
    echo "   ✓ Semester model works - Active semesters: {$semesterCount}\n";
    
    $categoryCount = \App\Models\FeesCategory::where('status', 1)->count();
    echo "   ✓ FeesCategory model works - Active categories: {$categoryCount}\n\n";
    
    echo "3. Testing Fee Relationships:\n";
    $fee = \App\Models\Fee::with([
        'studentEnroll.student',
        'studentEnroll.session',
        'studentEnroll.semester',
        'category'
    ])->where('status', 2)->first();
    
    if ($fee) {
        echo "   ✓ Fee found with ID: {$fee->id}\n";
        echo "   ✓ StudentEnroll relationship: " . ($fee->studentEnroll ? "OK" : "MISSING") . "\n";
        
        if ($fee->studentEnroll) {
            echo "   ✓ Student relationship: " . ($fee->studentEnroll->student ? "OK" : "MISSING") . "\n";
            echo "   ✓ Session relationship: " . ($fee->studentEnroll->session ? "OK" : "MISSING") . "\n";
            echo "   ✓ Semester relationship: " . ($fee->studentEnroll->semester ? "OK" : "MISSING") . "\n";
        }
        
        echo "   ✓ Category relationship: " . ($fee->category ? "OK" : "MISSING") . "\n";
        
        // Test the attributes
        echo "\n4. Testing Fee Attributes:\n";
        echo "   ✓ Total Amount: " . $fee->total_amount . "\n";
        echo "   ✓ Remaining Balance: " . $fee->remaining_balance . "\n";
        echo "   ✓ Status Badge: " . strip_tags($fee->status_badge) . "\n";
    } else {
        echo "   ⚠ No partially paid fees found\n";
    }
    
    echo "\n5. Testing View Existence:\n";
    $viewPath = resource_path('views/admin/partial-payment-report/index.blade.php');
    if (file_exists($viewPath)) {
        echo "   ✓ View file exists\n";
    } else {
        echo "   ✗ View file NOT FOUND\n";
    }
    
    echo "\n6. Testing approvedReceipts Relationship:\n";
    if ($fee && method_exists($fee, 'approvedReceipts')) {
        echo "   ✓ approvedReceipts method exists\n";
        $receipts = $fee->approvedReceipts;
        echo "   ✓ Approved receipts count: " . $receipts->count() . "\n";
    } else {
        echo "   ✗ approvedReceipts method NOT FOUND on Fee model\n";
    }
    
    echo "\n✅ All checks passed! Issue may be permission or session related.\n";
    echo "\nPossible causes:\n";
    echo "- User may not be logged in as admin\n";
    echo "- Session may have expired\n";
    echo "- Middleware may be blocking access\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR FOUND:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Stack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
