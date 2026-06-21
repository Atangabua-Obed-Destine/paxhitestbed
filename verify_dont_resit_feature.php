<?php
/**
 * Quick Verification Script for "Don't Resit Course" Feature
 * 
 * This script checks if all components are properly configured.
 * Run: php verify_dont_resit_feature.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ResitRequest;
use Illuminate\Support\Facades\Route;

echo "=== Don't Resit Course Feature Verification ===\n\n";

// 1. Check if STATE_DECLINED constant exists
echo "1. Checking ResitRequest Model...\n";
if (defined('App\Models\ResitRequest::STATE_DECLINED')) {
    echo "   ✅ STATE_DECLINED constant exists\n";
    echo "   Value: " . ResitRequest::STATE_DECLINED . "\n";
} else {
    echo "   ❌ STATE_DECLINED constant NOT FOUND\n";
}

// 2. Check if decline route exists
echo "\n2. Checking Routes...\n";
$routes = Route::getRoutes();
$declineRouteExists = false;
foreach ($routes as $route) {
    if ($route->getName() === 'student.resit.decline') {
        $declineRouteExists = true;
        echo "   ✅ Route 'student.resit.decline' exists\n";
        echo "   URI: " . $route->uri() . "\n";
        echo "   Method: " . implode(', ', $route->methods()) . "\n";
        echo "   Action: " . $route->getActionName() . "\n";
        break;
    }
}
if (!$declineRouteExists) {
    echo "   ❌ Route 'student.resit.decline' NOT FOUND\n";
}

// 3. Check if decline method exists in controller
echo "\n3. Checking Controller Method...\n";
$controllerClass = 'App\Http\Controllers\Student\ResitController';
if (class_exists($controllerClass)) {
    if (method_exists($controllerClass, 'decline')) {
        echo "   ✅ ResitController::decline() method exists\n";
        
        $reflection = new ReflectionMethod($controllerClass, 'decline');
        $parameters = $reflection->getParameters();
        echo "   Parameters: ";
        foreach ($parameters as $param) {
            echo $param->getName() . " ";
        }
        echo "\n";
    } else {
        echo "   ❌ ResitController::decline() method NOT FOUND\n";
    }
} else {
    echo "   ❌ ResitController class NOT FOUND\n";
}

// 4. Check SemesterProgressionService
echo "\n4. Checking SemesterProgressionService...\n";
$serviceClass = 'App\Services\Academic\SemesterProgressionService';
if (class_exists($serviceClass)) {
    echo "   ✅ SemesterProgressionService exists\n";
    
    if (method_exists($serviceClass, 'passedAllCourses')) {
        echo "   ✅ passedAllCourses() method exists\n";
        
        // Check if method contains declined logic
        $reflection = new ReflectionMethod($serviceClass, 'passedAllCourses');
        $source = file_get_contents($reflection->getFileName());
        
        if (strpos($source, 'STATE_DECLINED') !== false) {
            echo "   ✅ Method contains STATE_DECLINED logic\n";
        } else {
            echo "   ⚠️  Method may not contain STATE_DECLINED logic\n";
        }
    } else {
        echo "   ❌ passedAllCourses() method NOT FOUND\n";
    }
} else {
    echo "   ❌ SemesterProgressionService class NOT FOUND\n";
}

// 5. Check view file
echo "\n5. Checking View File...\n";
$viewPath = resource_path('views/student/resit/index.blade.php');
if (file_exists($viewPath)) {
    echo "   ✅ View file exists: $viewPath\n";
    
    $viewContent = file_get_contents($viewPath);
    
    if (strpos($viewContent, "Don't Resit Course") !== false || strpos($viewContent, "Don\\'t Resit Course") !== false) {
        echo "   ✅ View contains 'Don't Resit Course' button\n";
    } else {
        echo "   ❌ View does NOT contain 'Don't Resit Course' button\n";
    }
    
    if (strpos($viewContent, 'resit.decline') !== false) {
        echo "   ✅ View contains route reference to 'resit.decline'\n";
    } else {
        echo "   ❌ View does NOT reference 'resit.decline' route\n";
    }
    
    if (strpos($viewContent, 'declined') !== false) {
        echo "   ✅ View handles 'declined' state\n";
    } else {
        echo "   ⚠️  View may not handle 'declined' state\n";
    }
} else {
    echo "   ❌ View file NOT FOUND\n";
}

// 6. Check database table
echo "\n6. Checking Database Structure...\n";
try {
    $tableExists = \Schema::hasTable('resit_requests');
    if ($tableExists) {
        echo "   ✅ resit_requests table exists\n";
        
        $columns = \Schema::getColumnListing('resit_requests');
        if (in_array('workflow_state', $columns)) {
            echo "   ✅ workflow_state column exists\n";
        } else {
            echo "   ❌ workflow_state column NOT FOUND\n";
        }
    } else {
        echo "   ❌ resit_requests table NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "   ⚠️  Could not check database: " . $e->getMessage() . "\n";
}

// 7. Count existing declined records
echo "\n7. Checking Existing Data...\n";
try {
    $declinedCount = ResitRequest::where('workflow_state', ResitRequest::STATE_DECLINED)->count();
    echo "   ℹ️  Current declined resit requests: $declinedCount\n";
    
    if ($declinedCount > 0) {
        echo "   📊 Sample declined request:\n";
        $sample = ResitRequest::where('workflow_state', ResitRequest::STATE_DECLINED)
            ->with(['studentEnroll', 'subject'])
            ->first();
        if ($sample) {
            echo "       - Request ID: {$sample->id}\n";
            echo "       - Subject: {$sample->subject->title ?? 'N/A'}\n";
            echo "       - Created: {$sample->created_at}\n";
        }
    }
} catch (Exception $e) {
    echo "   ⚠️  Could not query data: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Verification Summary ===\n";
echo "All core components have been checked.\n";
echo "Review any ❌ or ⚠️  items above.\n";
echo "\nTo test the feature:\n";
echo "1. Log in as a student with failed courses\n";
echo "2. Navigate to: http://localhost/paxhitest/student/resit\n";
echo "3. Select a session/semester with failed courses\n";
echo "4. Look for the orange 'Don't Resit Course' button\n";
echo "5. Click it and confirm the decision\n";
echo "6. Verify auto-progression notification appears\n\n";

echo "✅ Verification complete!\n";
