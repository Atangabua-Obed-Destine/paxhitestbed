<?php
/**
 * Program Change Enhancement - Verification Script
 * 
 * Run this script to verify all components are working correctly
 * Usage: php verify_program_change.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     PROGRAM CHANGE ENHANCEMENT - VERIFICATION SCRIPT         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Test 1: Check ProgramSwapService exists
echo "📋 Test 1: Checking ProgramSwapService...\n";
if (class_exists('App\Services\ProgramSwapService')) {
    echo "   ✅ ProgramSwapService class exists\n";
    
    $reflection = new ReflectionClass('App\Services\ProgramSwapService');
    $methods = $reflection->getMethods();
    
    echo "   📌 Available methods:\n";
    foreach ($methods as $method) {
        if ($method->isPublic()) {
            echo "      - " . $method->getName() . "()\n";
        }
    }
} else {
    echo "   ❌ ProgramSwapService class NOT found\n";
}
echo "\n";

// Test 2: Check Student model has generateEnrollmentMatricule
echo "📋 Test 2: Checking Student::generateEnrollmentMatricule()...\n";
if (class_exists('App\Models\Student')) {
    echo "   ✅ Student model exists\n";
    
    if (method_exists('App\Models\Student', 'generateEnrollmentMatricule')) {
        echo "   ✅ generateEnrollmentMatricule() method exists\n";
    } else {
        echo "   ❌ generateEnrollmentMatricule() method NOT found\n";
    }
} else {
    echo "   ❌ Student model NOT found\n";
}
echo "\n";

// Test 3: Check route exists
echo "📋 Test 3: Checking route configuration...\n";
try {
    $route = \Route::getRoutes()->getByName('admin.single-enroll.validate-swap');
    if ($route) {
        echo "   ✅ Route 'admin.single-enroll.validate-swap' exists\n";
        echo "   📌 URI: " . $route->uri() . "\n";
        echo "   📌 Method: " . implode(', ', $route->methods()) . "\n";
        echo "   📌 Action: " . $route->getActionName() . "\n";
    } else {
        echo "   ❌ Route NOT found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error checking route: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check controller method exists
echo "📋 Test 4: Checking controller method...\n";
if (class_exists('App\Http\Controllers\Admin\StudentSingleEnrollController')) {
    echo "   ✅ StudentSingleEnrollController exists\n";
    
    if (method_exists('App\Http\Controllers\Admin\StudentSingleEnrollController', 'validateProgramSwap')) {
        echo "   ✅ validateProgramSwap() method exists\n";
    } else {
        echo "   ❌ validateProgramSwap() method NOT found\n";
    }
    
    if (method_exists('App\Http\Controllers\Admin\StudentSingleEnrollController', 'store')) {
        echo "   ✅ store() method exists\n";
    } else {
        echo "   ❌ store() method NOT found\n";
    }
} else {
    echo "   ❌ StudentSingleEnrollController NOT found\n";
}
echo "\n";

// Test 5: Check view file exists
echo "📋 Test 5: Checking view file...\n";
$viewPath = resource_path('views/admin/single-enroll/index.blade.php');
if (file_exists($viewPath)) {
    echo "   ✅ View file exists: admin/single-enroll/index.blade.php\n";
    
    $content = file_get_contents($viewPath);
    
    // Check for key components
    $checks = [
        'newMatriculePreview' => 'New matricule preview section',
        'updateMatriculePreview' => 'JavaScript update function',
        'fetchProgramValidation' => 'AJAX validation function',
        'displayValidationWarnings' => 'Display warnings function',
        'buildConfirmationModal' => 'Confirmation modal function',
    ];
    
    echo "   📌 Component checks:\n";
    foreach ($checks as $needle => $description) {
        if (strpos($content, $needle) !== false) {
            echo "      ✅ $description\n";
        } else {
            echo "      ❌ $description NOT found\n";
        }
    }
} else {
    echo "   ❌ View file NOT found\n";
}
echo "\n";

// Test 6: Test matricule generation logic
echo "📋 Test 6: Testing matricule generation...\n";
try {
    // Get a random student
    $student = \App\Models\Student::with(['program', 'batch'])->first();
    
    if ($student) {
        echo "   ✅ Found test student: {$student->first_name} {$student->last_name}\n";
        echo "   📌 Current Program: " . ($student->program->title ?? 'N/A') . "\n";
        echo "   📌 Academic Level: " . ($student->program->academic_level ?? 'A') . "\n";
        
        // Get programs of different levels
        $programs = \App\Models\Program::whereIn('academic_level', ['A', 'M', 'D'])->get();
        
        echo "\n   🎯 Simulating matricule generation for different levels:\n";
        foreach ($programs as $program) {
            try {
                $matricule = \App\Models\Student::generateEnrollmentMatricule(
                    $student->id, 
                    $program->id, 
                    $student->batch_id
                );
                
                $levelName = $program->academic_level == 'A' ? 'Undergraduate' : 
                            ($program->academic_level == 'M' ? 'Masters' : 'Doctoral');
                
                echo "      📍 Level $program->academic_level ($levelName): $matricule\n";
            } catch (Exception $e) {
                echo "      ❌ Error generating matricule: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "   ⚠️  No students found in database\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Test ProgramSwapService validation
echo "📋 Test 7: Testing ProgramSwapService validation...\n";
try {
    $student = \App\Models\Student::with(['program'])->first();
    $differentProgram = \App\Models\Program::where('id', '!=', $student->program_id ?? 0)->first();
    
    if ($student && $differentProgram) {
        echo "   ✅ Test setup ready\n";
        echo "   📌 Student: {$student->first_name} {$student->last_name}\n";
        echo "   📌 Current Program: " . ($student->program->title ?? 'N/A') . "\n";
        echo "   📌 Target Program: {$differentProgram->title}\n";
        
        $service = new \App\Services\ProgramSwapService();
        $validation = $service->validateProgramSwap($student->id, $differentProgram->id);
        
        echo "\n   🎯 Validation Result:\n";
        echo "      can_swap: " . ($validation['can_swap'] ? '✅ Yes' : '❌ No') . "\n";
        echo "      is_program_change: " . ($validation['is_program_change'] ? '✅ Yes' : '❌ No') . "\n";
        echo "      generates_new_matricule: " . ($validation['generates_new_matricule'] ? '✅ Yes' : '❌ No') . "\n";
        echo "      new_matricule: " . ($validation['new_matricule'] ?? 'N/A') . "\n";
        
        if (isset($validation['warnings']) && count($validation['warnings']) > 0) {
            echo "      ⚠️  Warnings: " . count($validation['warnings']) . "\n";
        }
        
        if (isset($validation['blockers']) && count($validation['blockers']) > 0) {
            echo "      ❌ Blockers: " . count($validation['blockers']) . "\n";
        }
        
        if (isset($validation['info']) && count($validation['info']) > 0) {
            echo "      ℹ️  Info messages: " . count($validation['info']) . "\n";
            foreach ($validation['info'] as $info) {
                if (is_array($info)) {
                    echo "         - " . ($info['message'] ?? json_encode($info)) . "\n";
                } else {
                    echo "         - $info\n";
                }
            }
        }
    } else {
        echo "   ⚠️  Could not find test data\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    VERIFICATION COMPLETE                     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "🌐 Test in browser:\n";
echo "   http://localhost/paxhitest/admin/student/single-enroll?student=12133\n";
echo "\n";
echo "📚 Documentation:\n";
echo "   - PROGRAM_CHANGE_ENHANCEMENT_SUMMARY.md\n";
echo "   - MULTI_PROGRAM_ENROLLMENT_COMPLETE.md\n";
echo "   - SEQUENTIAL_ENROLLMENT_EXPLANATION.md\n";
echo "\n";
echo "✨ Expected Behavior:\n";
echo "   1. Select different program → See matricule preview\n";
echo "   2. Academic level change → New matricule generated\n";
echo "   3. Same level transfer → Current matricule retained\n";
echo "   4. Incompatible subjects → Warning (can still proceed)\n";
echo "   5. Confirmation modal → Shows new matricule prominently\n";
echo "\n";
