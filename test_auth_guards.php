<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING AUTH GUARDS ===\n\n";

// Test what guards are available
$guards = ['web', 'student', 'api'];

foreach ($guards as $guardName) {
    echo "Guard: {$guardName}\n";
    
    try {
        $user = \Illuminate\Support\Facades\Auth::guard($guardName)->user();
        
        if ($user) {
            echo "  ✓ User found: ";
            echo get_class($user) . "\n";
            
            if (method_exists($user, 'getAttribute')) {
                echo "  - ID: {$user->id}\n";
                
                if (isset($user->name)) {
                    echo "  - Name: {$user->name}\n";
                } elseif (isset($user->first_name)) {
                    echo "  - Name: {$user->first_name} {$user->last_name}\n";
                }
                
                if (isset($user->email)) {
                    echo "  - Email: {$user->email}\n";
                }
            }
        } else {
            echo "  ✗ No authenticated user\n";
        }
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Check the default guard
echo "Default guard: " . config('auth.defaults.guard') . "\n";
echo "Using Auth::user() returns: ";
$defaultUser = \Illuminate\Support\Facades\Auth::user();
if ($defaultUser) {
    echo get_class($defaultUser) . " (ID: {$defaultUser->id})\n";
} else {
    echo "NULL\n";
}

echo "\n=== Checking Auth::check() for each guard ===\n";
foreach ($guards as $guardName) {
    $isAuth = \Illuminate\Support\Facades\Auth::guard($guardName)->check();
    echo "{$guardName}: " . ($isAuth ? '✓ Authenticated' : '✗ Not authenticated') . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
