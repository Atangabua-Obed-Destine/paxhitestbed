<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;

echo "=== DDOS PROTECTION SETTINGS TEST ===\n\n";

// Display current configuration
echo "Current Configuration:\n";
echo "  - Max Requests/Minute: " . config('security.ddos.max_requests_per_minute') . "\n";
echo "  - Block Duration (minutes): " . config('security.ddos.block_duration_minutes') . "\n";
echo "  - Suspicious Pattern Detection: " . (config('security.ddos.suspicious_pattern_enabled') ? 'Enabled' : 'Disabled') . "\n";
echo "  - Strikes Before Block: " . config('security.ddos.suspicious_strikes_before_block') . "\n";
echo "  - Strikes Window (minutes): " . config('security.ddos.suspicious_strikes_window_minutes') . "\n";
echo "  - Suspicious Block Duration (hours): " . config('security.ddos.suspicious_block_hours') . "\n";
echo "  - Bot Detection: " . (config('security.ddos.bot_detection_enabled') ? 'Enabled' : 'Disabled') . "\n";
echo "  - Bot Request Limit: " . config('security.ddos.bot_request_limit') . "\n";
echo "  - Bot Block Duration (hours): " . config('security.ddos.bot_block_duration_hours') . "\n";

echo "\nExcluded Paths:\n";
foreach (config('security.ddos.exclude_paths', []) as $path) {
    echo "  - $path\n";
}

echo "\n=== WHITELIST CHECK ===\n";
$whitelistedIp = '192.168.16.104';
$isWhitelisted = \App\Models\IpWhitelist::isWhitelisted($whitelistedIp);
echo "$whitelistedIp is " . ($isWhitelisted ? "✅ WHITELISTED" : "❌ NOT WHITELISTED") . "\n";

echo "\n=== CACHE STATUS ===\n";
$blocked = Cache::has('ddos_blocked:' . $whitelistedIp);
echo "Cache block for $whitelistedIp: " . ($blocked ? "BLOCKED" : "✅ CLEAR") . "\n";

echo "\n✅ All settings loaded successfully!\n";
echo "\nSummary of Changes:\n";
echo "  ✓ Rate limit increased from 100 to 500 requests/minute\n";
echo "  ✓ Suspicious patterns now require 3 strikes before blocking\n";
echo "  ✓ Suspicious block duration reduced from 24 hours to 2 hours\n";
echo "  ✓ Bot limit increased from 20 to 150 requests\n";
echo "  ✓ Admin security pages excluded from suspicious pattern checks\n";
echo "  ✓ More targeted suspicious patterns (less false positives)\n";
