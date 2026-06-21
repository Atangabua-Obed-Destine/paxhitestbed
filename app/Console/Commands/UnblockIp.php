<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\FailedLoginAttempt;

class UnblockIp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:unblock-ip {ip? : The IP address to unblock (optional - unblocks all if not provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unblock an IP address from DDoS protection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ip = $this->argument('ip');
        
        if ($ip) {
            // Unblock specific IP
            $this->unblockSingleIp($ip);
        } else {
            // Unblock all IPs
            $this->unblockAllIps();
        }
        
        return 0;
    }
    
    /**
     * Unblock a specific IP address
     */
    private function unblockSingleIp($ip)
    {
        // Remove from cache
        Cache::forget('ddos_blocked:' . $ip);
        Cache::forget('ddos_protection:' . $ip);
        Cache::forget('bot_requests:' . $ip);
        
        // Reset failed login attempts
        FailedLoginAttempt::where('ip_address', $ip)->update([
            'attempts' => 0,
            'blocked_until' => null,
        ]);
        
        $this->info("✓ IP address {$ip} has been unblocked successfully.");
        $this->info("The IP can now access the system normally.");
    }
    
    /**
     * Unblock all blocked IPs
     */
    private function unblockAllIps()
    {
        if (!$this->confirm('This will unblock ALL blocked IP addresses. Continue?')) {
            $this->info('Operation cancelled.');
            return;
        }
        
        // Clear all DDoS related cache
        $keys = [
            'ddos_blocked:*',
            'ddos_protection:*',
            'bot_requests:*',
        ];
        
        foreach ($keys as $pattern) {
            Cache::forget($pattern);
        }
        
        // Reset all failed login attempts
        $count = FailedLoginAttempt::where('attempts', '>', 0)->update([
            'attempts' => 0,
            'blocked_until' => null,
        ]);
        
        $this->info("✓ All IP addresses have been unblocked successfully.");
        $this->info("✓ Reset {$count} failed login attempt records.");
        $this->warn("Note: This cleared all DDoS protection data. Monitoring will restart fresh.");
    }
}
