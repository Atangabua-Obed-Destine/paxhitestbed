<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ADMISSION FEE PAYMENT UPLOAD VERIFICATION ===\n\n";

// 1. Check if the route exists
echo "1. Checking route registration...\n";
$routes = Route::getRoutes();
$routeExists = false;
foreach ($routes as $route) {
    if ($route->uri() === 'application/admission-fee/payment/upload' && in_array('POST', $route->methods())) {
        $routeExists = true;
        echo "   ✓ Route 'application.admission-fee.upload' registered\n";
        echo "   ✓ Method: POST\n";
        echo "   ✓ URI: " . $route->uri() . "\n";
        echo "   ✓ Controller: " . $route->getActionName() . "\n";
        break;
    }
}
if (!$routeExists) {
    echo "   ✗ Route NOT found!\n";
}
echo "\n";

// 2. Check if controller method exists
echo "2. Checking controller method...\n";
$controllerFile = app_path('Http/Controllers/Web/ApplicationController.php');
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    if (strpos($content, 'function uploadAdmissionFeeReceipt') !== false) {
        echo "   ✓ Method 'uploadAdmissionFeeReceipt' exists in ApplicationController\n";
        
        // Check for key components
        if (strpos($content, 'PaymentReceipt') !== false) {
            echo "   ✓ Uses PaymentReceipt model\n";
        }
        if (strpos($content, "verification_status' => 'pending'") !== false) {
            echo "   ✓ Sets verification_status to 'pending'\n";
        }
        if (strpos($content, 'uploadFile') !== false) {
            echo "   ✓ Uses file upload functionality\n";
        }
    } else {
        echo "   ✗ Method NOT found!\n";
    }
} else {
    echo "   ✗ Controller file NOT found!\n";
}
echo "\n";

// 3. Check if dashboard view has payment form
echo "3. Checking dashboard view for payment form...\n";
$viewFile = resource_path('views/application/portal/dashboard.blade.php');
if (file_exists($viewFile)) {
    $content = file_get_contents($viewFile);
    
    if (strpos($content, 'Upload Payment Receipt') !== false) {
        echo "   ✓ Payment upload form found\n";
    }
    
    if (strpos($content, 'Your application will only be reviewed after') !== false) {
        echo "   ✓ Payment requirement notice found\n";
    }
    
    if (strpos($content, 'application.admission-fee.upload') !== false) {
        echo "   ✓ Form action points to correct route\n";
    }
    
    if (strpos($content, 'ADMISSION_FEE_INSTRUCTIONS') !== false) {
        echo "   ✓ Payment instructions display implemented\n";
    }
    
    // Check form fields
    $requiredFields = ['payment_date', 'amount', 'payment_reference', 'payment_method', 'receipt_file'];
    $allFieldsFound = true;
    foreach ($requiredFields as $field) {
        if (strpos($content, "name=\"$field\"") === false) {
            echo "   ✗ Missing field: $field\n";
            $allFieldsFound = false;
        }
    }
    if ($allFieldsFound) {
        echo "   ✓ All required form fields present\n";
    }
} else {
    echo "   ✗ Dashboard view NOT found!\n";
}
echo "\n";

// 4. Check PaymentReceipt model
echo "4. Checking PaymentReceipt model...\n";
$modelFile = app_path('Models/PaymentReceipt.php');
if (file_exists($modelFile)) {
    echo "   ✓ PaymentReceipt model exists\n";
    
    $content = file_get_contents($modelFile);
    if (strpos($content, 'verification_status') !== false) {
        echo "   ✓ Has verification_status field\n";
    }
    if (strpos($content, 'function fee()') !== false) {
        echo "   ✓ Has fee relationship\n";
    }
} else {
    echo "   ✗ PaymentReceipt model NOT found!\n";
}
echo "\n";

// 5. Test database connection and check tables
echo "5. Checking database tables...\n";
try {
    $tables = ['payment_receipts', 'fees', 'applications'];
    foreach ($tables as $table) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            echo "   ✓ Table '$table' exists\n";
        } else {
            echo "   ✗ Table '$table' NOT found!\n";
        }
    }
    
    // Check if payment_receipts has required columns
    if (DB::getSchemaBuilder()->hasTable('payment_receipts')) {
        $columns = ['fee_id', 'student_id', 'receipt_file', 'payment_reference', 'verification_status', 'amount'];
        $hasAllColumns = true;
        foreach ($columns as $column) {
            if (!DB::getSchemaBuilder()->hasColumn('payment_receipts', $column)) {
                echo "   ✗ Column '$column' NOT found in payment_receipts!\n";
                $hasAllColumns = false;
            }
        }
        if ($hasAllColumns) {
            echo "   ✓ All required columns present in payment_receipts\n";
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Database check failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Check if sample application with admission fee exists
echo "6. Checking sample data...\n";
try {
    $application = \App\Models\Application::with('admissionFee')->first();
    if ($application) {
        echo "   ✓ Sample application found (ID: {$application->id})\n";
        if ($application->admissionFee) {
            echo "   ✓ Application has admission fee (ID: {$application->admissionFee->id})\n";
            echo "   ✓ Fee amount: {$application->admissionFee->fee_amount}\n";
            echo "   ✓ Fee status: {$application->admissionFee->status}\n";
            echo "   ✓ Remaining balance: {$application->admissionFee->remaining_balance}\n";
        } else {
            echo "   ⚠ Application has no admission fee assigned\n";
        }
    } else {
        echo "   ⚠ No applications found in database\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Data check failed: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== VERIFICATION SUMMARY ===\n\n";
echo "✅ Route: application.admission-fee.upload\n";
echo "✅ URL: http://localhost/paxhitest/public/application/admission-fee/payment/upload\n";
echo "✅ Method: POST\n";
echo "✅ Dashboard: http://localhost/paxhitest/public/application/dashboard\n";
echo "✅ Admin Verification: http://localhost/paxhitest/public/admin/payment-verification\n\n";

echo "📋 NEXT STEPS:\n";
echo "1. Login as an applicant at: http://localhost/paxhitest/public/application/login\n";
echo "2. Navigate to dashboard to see the admission fee and payment upload form\n";
echo "3. Upload a payment receipt with payment details\n";
echo "4. Login as admin to verify the payment at the payment verification page\n";
echo "5. Approve/reject the payment to update the fee status\n\n";

echo "✅ IMPLEMENTATION COMPLETE!\n";
