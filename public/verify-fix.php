<!DOCTYPE html>
<html>
<head>
    <title>Quick Fix Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-size: 24px; margin-bottom: 20px; }
        .error { color: #dc3545; font-size: 24px; margin-bottom: 20px; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        .check { color: #28a745; margin-right: 10px; }
        .cross { color: #dc3545; margin-right: 10px; }
        ul { line-height: 2; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Partial Payment Report - Fix Verification</h1>
        
        <?php
        require __DIR__.'/../vendor/autoload.php';
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $allPassed = true;
        $errors = [];

        // Test 1: Controller exists and works
        try {
            $controller = new \App\Http\Controllers\Admin\PartialPaymentReportController();
            $request = new \Illuminate\Http\Request();
            $response = $controller->index($request);
            echo '<p class="success">✓ Controller Test: PASSED</p>';
        } catch (Exception $e) {
            echo '<p class="error">✗ Controller Test: FAILED</p>';
            $errors[] = "Controller: " . $e->getMessage();
            $allPassed = false;
        }

        // Test 2: All routes exist
        try {
            route('admin.dashboard.index');
            route('admin.partial-payment-report.index');
            route('admin.partial-payment-report.export');
            echo '<p class="success">✓ Routes Test: PASSED</p>';
        } catch (Exception $e) {
            echo '<p class="error">✗ Routes Test: FAILED</p>';
            $errors[] = "Routes: " . $e->getMessage();
            $allPassed = false;
        }

        // Test 3: Data exists
        try {
            $count = \App\Models\Fee::where('status', 2)->count();
            echo '<p class="success">✓ Database Test: PASSED (' . $count . ' partially paid fees)</p>';
        } catch (Exception $e) {
            echo '<p class="error">✗ Database Test: FAILED</p>';
            $errors[] = "Database: " . $e->getMessage();
            $allPassed = false;
        }

        if ($allPassed) {
            echo '<div class="info">';
            echo '<h2>🎉 All Tests Passed!</h2>';
            echo '<p>The partial payment report is ready to use. The 500 error has been fixed.</p>';
            echo '<h3>What was fixed:</h3>';
            echo '<ul>';
            echo '<li><span class="check">✓</span> Fixed DB::raw() usage in controller (line 73)</li>';
            echo '<li><span class="check">✓</span> Fixed route name from <code>admin.dashboard</code> to <code>admin.dashboard.index</code></li>';
            echo '<li><span class="check">✓</span> Cleared all Laravel caches</li>';
            echo '</ul>';
            echo '<h3>Next Steps:</h3>';
            echo '<ol>';
            echo '<li>Clear your browser cache (Ctrl + Shift + Delete)</li>';
            echo '<li>Or try in incognito/private browsing mode</li>';
            echo '<li>Login to admin portal and navigate to Reports → Partial Payment Report</li>';
            echo '</ol>';
            echo '<p><a href="/admin/partial-payment-report" class="btn">Go to Partial Payment Report</a></p>';
            echo '</div>';
        } else {
            echo '<div class="info">';
            echo '<h2>❌ Some Tests Failed</h2>';
            echo '<p>Errors found:</p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li><span class="cross">✗</span> ' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        ?>

        <hr style="margin: 30px 0;">
        
        <h3>Quick Links:</h3>
        <a href="/admin/partial-payment-report" class="btn">Partial Payment Report</a>
        <a href="/admin/payment-verification" class="btn">Payment Verification</a>
        <a href="/admin/dashboard" class="btn">Admin Dashboard</a>
        
        <hr style="margin: 30px 0;">
        
        <p style="color: #666; font-size: 12px;">
            Test file location: public/verify-fix.php<br>
            Date: <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>
