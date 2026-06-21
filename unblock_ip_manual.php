<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;
use App\Models\FailedLoginAttempt;

$ip = '192.168.16.104';

echo "Unblocking IP: $ip\n\n";

// Remove from cache
Cache::forget('ddos_blocked:' . $ip);
Cache::forget('ddos_protection:' . $ip);
Cache::forget('bot_requests:' . $ip);

echo "✓ Removed from cache\n";

// Reset failed login attempts
FailedLoginAttempt::where('ip_address', $ip)->update([
    'attempts' => 0,
    'blocked_until' => null,
]);

echo "✓ Reset database records\n";

// Verify
$blocked = Cache::has('ddos_blocked:' . $ip);
echo "\nVerification: IP is " . ($blocked ? "STILL BLOCKED" : "UNBLOCKED") . "\n";

echo "\nDone! Your colleague can now access the system.\n";
