<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;
use App\Models\FailedLoginAttempt;
use App\Models\IpWhitelist;

$ip = '192.168.16.104';
$description = 'Colleague workstation - auto-added';

echo "Adding IP to whitelist: $ip\n\n";

// Check if already whitelisted
$existing = IpWhitelist::where('ip_address', $ip)->first();

if ($existing) {
    echo "✓ IP already in whitelist (ID: {$existing->id})\n";
    if (!$existing->is_active) {
        $existing->is_active = true;
        $existing->save();
        echo "✓ Activated existing whitelist entry\n";
    }
} else {
    $whitelist = IpWhitelist::create([
        'ip_address' => $ip,
        'description' => $description,
        'is_active' => true,
        'created_by' => 1, // Assuming admin ID 1
    ]);
    echo "✓ Added to whitelist (ID: {$whitelist->id})\n";
}

// Also unblock this IP if it's currently blocked
Cache::forget('ddos_blocked:' . $ip);
Cache::forget('ddos_protection:' . $ip);
Cache::forget('bot_requests:' . $ip);
echo "✓ Cleared cache blocks\n";

FailedLoginAttempt::where('ip_address', $ip)->update([
    'attempts' => 0,
    'blocked_until' => null,
]);
echo "✓ Reset database records\n";

echo "\n✅ Done! IP $ip is now whitelisted and will bypass all security checks.\n";
echo "\nYou can manage whitelisted IPs at: http://localhost/paxhitest/admin/security/whitelist\n";
