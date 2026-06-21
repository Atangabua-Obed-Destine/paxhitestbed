<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;
use App\Models\FailedLoginAttempt;

echo "=== CHECKING BLOCKED IPS CACHE STATUS ===\n\n";

// Get all database records
$attempts = FailedLoginAttempt::orderBy('last_attempt_at', 'desc')->get();
echo "Total database records: " . $attempts->count() . "\n\n";

foreach ($attempts as $attempt) {
    echo "IP: " . $attempt->ip_address . "\n";
    echo "  Email: " . $attempt->email . "\n";
    echo "  Attempts: " . $attempt->attempts . "\n";
    echo "  Blocked Until: " . ($attempt->blocked_until ?? 'NULL') . "\n";
    echo "  Last Attempt: " . $attempt->last_attempt_at . "\n";
    
    // Check cache status
    $cacheBlocked = Cache::has('ddos_blocked:' . $attempt->ip_address);
    $protectionCount = Cache::get('ddos_protection:' . $attempt->ip_address, 0);
    $botCount = Cache::get('bot_requests:' . $attempt->ip_address, 0);
    
    echo "  Cache Status:\n";
    echo "    - ddos_blocked: " . ($cacheBlocked ? 'YES' : 'NO') . "\n";
    echo "    - ddos_protection count: " . $protectionCount . "\n";
    echo "    - bot_requests count: " . $botCount . "\n";
    
    if ($cacheBlocked) {
        echo "  *** CURRENTLY BLOCKED IN CACHE ***\n";
    }
    
    echo "\n";
}

// Check for common IPs that might be blocked
echo "\n=== CHECKING COMMON IPS ===\n\n";
$commonIps = ['192.168.16.104', '192.168.1.1', '127.0.0.1', '::1'];

foreach ($commonIps as $ip) {
    $blocked = Cache::has('ddos_blocked:' . $ip);
    if ($blocked) {
        echo "IP $ip is BLOCKED in cache\n";
    }
}

echo "\nDone!\n";
